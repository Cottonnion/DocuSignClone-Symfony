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

/**
 * Controller handling document management operations.
 * 
 * This controller provides endpoints for:
 * - Listing documents with various filters
 * - Creating new documents
 * - Retrieving document details
 * - Updating document information
 * - Deleting documents
 * - Managing document audit logs
 * - Sending documents for signing
 * - Sending reminders to signatories
 * - Uploading document files
 * - Downloading document files
 * 
 * Documents can be in various states:
 * - draft: Initial state when created
 * - sent: When sent to signatories
 * - signed: When all signatories have signed
 * - cancelled: When the signing process is cancelled
 * 
 * @Route('api/v1/documents')
 */
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

    /**
     * List documents with optional filters.
     * 
     * Retrieves a list of documents, optionally filtered by:
     * - Status (draft, sent, signed, cancelled)
     * - Template status
     * - Creator
     * 
     * @param Request $request The request containing optional query parameters
     * @return JsonResponse
     * 
     * @Route('', name: 'documents_list', methods: ['GET'])
     * 
     * Query parameters:
     * - status: Filter by document status
     * - is_template: Filter by template status (true/false)
     * - created_by: Filter by creator ID
     * 
     * Response format:
     * [
     *   {
     *     "id": 1,
     *     "title": "Document Title",
     *     "filePath": "document.pdf",
     *     "status": "draft",
     *     "createdBy": {
     *       "id": 1,
     *       "username": "user1"
     *     },
     *     "createdAt": "2025-05-04T11:32:33+00:00",
     *     "updatedAt": "2025-05-04T11:32:33+00:00",
     *     "signDeadline": "2025-05-11T11:32:33+00:00",
     *     "isTemplate": false
     *   },
     *   ...
     * ]
     */
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

    /**
     * Create a new document.
     * 
     * Creates a new document with the provided information.
     * 
     * @param Request $request The request containing document data
     * @return JsonResponse
     * 
     * @Route('', name: 'documents_create', methods: ['POST'])
     * 
     * Request body:
     * {
     *   "title": "Document Title",
     *   "status": "draft",
     *   "signDeadline": "2025-05-11T11:32:33+00:00",
     *   "isTemplate": false
     * }
     * 
     * Response format:
     * {
     *   "id": 1,
     *   "title": "Document Title",
     *   "filePath": "document.pdf",
     *   "status": "draft",
     *   "createdBy": {
     *     "id": 1,
     *     "username": "user1"
     *   },
     *   "createdAt": "2025-05-04T11:32:33+00:00",
     *   "updatedAt": "2025-05-04T11:32:33+00:00",
     *   "signDeadline": "2025-05-11T11:32:33+00:00",
     *   "isTemplate": false
     * }
     */
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

    /**
     * Get document details.
     * 
     * Retrieves detailed information about a specific document.
     * 
     * @param Document $document The document to retrieve
     * @return JsonResponse
     * 
     * @Route('/{id}', name: 'documents_get', methods: ['GET'])
     * 
     * Response format:
     * {
     *   "id": 1,
     *   "title": "Document Title",
     *   "filePath": "document.pdf",
     *   "status": "draft",
     *   "createdBy": {
     *     "id": 1,
     *     "username": "user1"
     *   },
     *   "createdAt": "2025-05-04T11:32:33+00:00",
     *   "updatedAt": "2025-05-04T11:32:33+00:00",
     *   "signDeadline": "2025-05-11T11:32:33+00:00",
     *   "isTemplate": false
     * }
     */
    #[Route('/{id}', name: 'documents_get', methods: ['GET'])]
    public function get(Document $document): JsonResponse
    {
        $json = $this->serializer->serialize($document, 'json', ['groups' => 'document:read']);
        return new JsonResponse($json, Response::HTTP_OK, [], true);
    }

    /**
     * Update document information.
     * 
     * Updates the information of an existing document.
     * 
     * @param Document $document The document to update
     * @param Request $request The request containing updated document data
     * @return JsonResponse
     * 
     * @Route('/{id}', name: 'documents_update', methods: ['PUT'])
     * 
     * Request body:
     * {
     *   "title": "Updated Title",
     *   "status": "sent",
     *   "signDeadline": "2025-05-11T11:32:33+00:00",
     *   "isTemplate": false
     * }
     * 
     * Response format:
     * {
     *   "id": 1,
     *   "title": "Updated Title",
     *   "filePath": "document.pdf",
     *   "status": "sent",
     *   "createdBy": {
     *     "id": 1,
     *     "username": "user1"
     *   },
     *   "createdAt": "2025-05-04T11:32:33+00:00",
     *   "updatedAt": "2025-05-04T11:32:33+00:00",
     *   "signDeadline": "2025-05-11T11:32:33+00:00",
     *   "isTemplate": false
     * }
     */
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

    /**
     * Delete a document.
     * 
     * Removes a document and all associated data from the system.
     * 
     * @param Document $document The document to delete
     * @return JsonResponse
     * 
     * @Route('/{id}', name: 'documents_delete', methods: ['DELETE'])
     * 
     * Response: 204 No Content
     */
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

    /**
     * Get document audit logs.
     * 
     * Retrieves the audit history of a document, including all actions
     * performed on it and by whom.
     * 
     * @param Document $document The document to get audit logs for
     * @return JsonResponse
     * 
     * @Route('/{id}/audit', name: 'documents_audit', methods: ['GET'])
     * 
     * Response format:
     * [
     *   {
     *     "id": 1,
     *     "action": "created",
     *     "timestamp": "2025-05-04T11:32:33+00:00",
     *     "performedBy": {
     *       "id": 1,
     *       "username": "user1"
     *     },
     *     "meta": {
     *       "field": "status",
     *       "oldValue": "draft",
     *       "newValue": "sent"
     *     }
     *   },
     *   ...
     * ]
     */
    #[Route('/{id}/audit', name: 'documents_audit', methods: ['GET'])]
    public function audit(Document $document): JsonResponse
    {
        $auditLogs = $this->documentService->getAuditLogs($document);
        return $this->json($auditLogs);
    }

    /**
     * Send a document for signing.
     * 
     * Initiates the signing process for a document, notifying all signatories
     * and updating the document status.
     * 
     * @param Document $document The document to send for signing
     * @return JsonResponse
     * 
     * @Route('/{id}/send', name: 'documents_send', methods: ['POST'])
     * 
     * Response format:
     * {
     *   "id": 1,
     *   "title": "Document Title",
     *   "status": "sent",
     *   "signatories": [
     *     {
     *       "id": 1,
     *       "email": "signatory@example.com",
     *       "name": "John Doe",
     *       "signingOrder": 1,
     *       "signed": false
     *     },
     *     ...
     *   ]
     * }
     */
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

    /**
     * Send reminders to signatories.
     * 
     * Sends reminder emails to signatories who haven't signed the document yet.
     * 
     * @param Document $document The document to send reminders for
     * @return JsonResponse
     * 
     * @Route('/{id}/remind', name: 'documents_remind', methods: ['POST'])
     * 
     * Response format:
     * {
     *   "message": "Reminders sent"
     * }
     */
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

    /**
     * Upload a document file.
     * 
     * Uploads a new document file to the system. Supported file types:
     * - PDF (.pdf)
     * - JPEG images (.jpg, .jpeg)
     * - PNG images (.png)
     * 
     * @param Request $request The request containing the file to upload
     * @return JsonResponse
     * 
     * @Route('/upload', name: 'documents_upload', methods: ['POST'])
     * 
     * Request format:
     * - Content-Type: multipart/form-data
     * - Body: file: [binary file data]
     * 
     * Response format:
     * {
     *   "id": 1,
     *   "title": "Original Filename",
     *   "filePath": "unique-filename.pdf",
     *   "status": "draft",
     *   "createdBy": {
     *     "id": 1,
     *     "username": "user1"
     *   },
     *   "createdAt": "2025-05-04T11:32:33+00:00",
     *   "updatedAt": "2025-05-04T11:32:33+00:00"
     * }
     */
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

    /**
     * Download a document file.
     * 
     * Downloads the file associated with a document.
     * 
     * @param Document $document The document to download
     * @return Response
     * 
     * @Route('/{id}/download', name: 'documents_download', methods: ['GET'])
     * 
     * Response:
     * - Content-Type: application/octet-stream
     * - Content-Disposition: attachment; filename="document.pdf"
     * - Body: Binary file data
     */
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