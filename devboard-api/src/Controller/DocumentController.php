<?php

namespace App\Controller;

use App\Entity\Document;
use App\Service\DocumentService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('api/v1/documents')]
class DocumentController extends AbstractController
{
    public function __construct(
        private DocumentService $documentService,
        private EntityManagerInterface $entityManager,
        private string $uploadDirectory,
        private SluggerInterface $slugger,
        private SerializerInterface $serializer
    ) {
    }

    #[Route('', name: 'documents_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $status = $request->query->get('status');
        $isTemplate = $request->query->get('is_template');
        $createdBy = $request->query->get('created_by');

        $documents = $this->documentService->getDocuments($status, $isTemplate, $createdBy);
        
        $json = $this->serializer->serialize($documents, 'json', ['groups' => 'document:read']);
        return new JsonResponse($json, Response::HTTP_OK, [], true);
    }

    #[Route('', name: 'documents_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        try {
            $document = $this->documentService->createDocument(
                $request->getContent(),
                $this->getUser()
            );
            $json = $this->serializer->serialize($document, 'json', ['groups' => 'document:read']);
            return new JsonResponse($json, Response::HTTP_CREATED, [], true);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}', name: 'documents_get', methods: ['GET'])]
    public function get(Document $document): JsonResponse
    {
        $json = $this->serializer->serialize($document, 'json', ['groups' => 'document:read']);
        return new JsonResponse($json, Response::HTTP_OK, [], true);
    }

    #[Route('/{id}', name: 'documents_update', methods: ['PUT'])]
    public function update(Document $document, Request $request): JsonResponse
    {
        try {
            $document = $this->documentService->updateDocument(
                $document,
                $request->getContent()
            );
            $json = $this->serializer->serialize($document, 'json', ['groups' => 'document:read']);
            return new JsonResponse($json, Response::HTTP_OK, [], true);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}', name: 'documents_delete', methods: ['DELETE'])]
    public function delete(Document $document): JsonResponse
    {
        try {
            $this->documentService->deleteDocument($document);
            return $this->json(null, Response::HTTP_NO_CONTENT);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}/audit', name: 'documents_audit', methods: ['GET'])]
    public function audit(Document $document): JsonResponse
    {
        $auditLogs = $this->documentService->getAuditLogs($document);
        return $this->json($auditLogs);
    }

    #[Route('/{id}/send', name: 'documents_send', methods: ['POST'])]
    public function send(Document $document): JsonResponse
    {
        try {
            $document = $this->documentService->sendDocument($document);
            return $this->json($document);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}/remind', name: 'documents_remind', methods: ['POST'])]
    public function remind(Document $document): JsonResponse
    {
        try {
            $this->documentService->remindSignatories($document);
            return $this->json(['message' => 'Reminders sent']);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/upload', name: 'documents_upload', methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
        /** @var UploadedFile $file */
        $file = $request->files->get('file');
        
        if (!$file) {
            return $this->json(['error' => 'No file uploaded'], Response::HTTP_BAD_REQUEST);
        }

        // Validate file type
        $allowedMimeTypes = ['application/pdf', 'image/jpeg', 'image/png'];
        if (!in_array($file->getMimeType(), $allowedMimeTypes)) {
            return $this->json(['error' => 'Invalid file type. Allowed types: PDF, JPEG, PNG'], Response::HTTP_BAD_REQUEST);
        }

        // Generate unique filename
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $newFilename = $safeFilename.'-'.uniqid().'.'.$file->guessExtension();

        try {
            // Ensure upload directory exists
            if (!is_dir($this->uploadDirectory)) {
                mkdir($this->uploadDirectory, 0777, true);
            }

            // Move file to upload directory
            $file->move($this->uploadDirectory, $newFilename);

            // Create document entity
            $document = new Document();
            $document->setTitle($originalFilename);
            $document->setFilePath($newFilename); // Store only the filename
            $document->setCreatedBy($this->getUser());

            $this->entityManager->persist($document);
            $this->entityManager->flush();

            // Serialize with groups
            $json = $this->serializer->serialize($document, 'json', ['groups' => 'document:read']);
            return new JsonResponse($json, Response::HTTP_CREATED, [], true);
        } catch (\Exception $e) {
            return $this->json(
                [
                    'error' => 'Failed to upload file',
                    'details' => [
                        'msg' => $e->getMessage(),
                        'upload_dir' => $this->uploadDirectory,
                        'filename' => $newFilename
                    ]
                ],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    #[Route('/{id}/download', name: 'documents_download', methods: ['GET'])]
    public function download(Document $document): Response
    {
        try {
            $filePath = $document->getFilePath();
            $fullPath = $this->uploadDirectory.'/'.$filePath;
            
            if (!file_exists($fullPath)) {
                return $this->json([
                    'error' => 'File not found',
                    'details' => [
                        'filepath' => $filePath,
                        'full_path' => $fullPath,
                        'upload_dir' => $this->uploadDirectory
                    ]
                ], Response::HTTP_NOT_FOUND);
            }

            return $this->file($fullPath);
        } catch (\Exception $e) {
            return $this->json([
                'error' => $e->getMessage(),
                'details' => [
                    'filepath' => $document->getFilePath(),
                    'upload_dir' => $this->uploadDirectory
                ]
            ], Response::HTTP_BAD_REQUEST);
        }
    }
} 