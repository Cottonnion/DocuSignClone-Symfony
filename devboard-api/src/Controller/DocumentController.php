<?php

namespace App\Controller;

use App\Entity\Document;
use App\Service\DocumentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('api/v1/documents')]
class DocumentController extends AbstractController
{
    public function __construct(
        private DocumentService $documentService
    ) {
    }

    #[Route('', name: 'documents_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $status = $request->query->get('status');
        $isTemplate = $request->query->get('is_template');
        $createdBy = $request->query->get('created_by');

        $documents = $this->documentService->getDocuments($status, $isTemplate, $createdBy);
        
        return $this->json($documents);
    }

    #[Route('', name: 'documents_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        try {
            $document = $this->documentService->createDocument(
                $request->getContent(),
                $this->getUser()
            );
            return $this->json($document, Response::HTTP_CREATED);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/{id}', name: 'documents_get', methods: ['GET'])]
    public function get(Document $document): JsonResponse
    {
        return $this->json($document);
    }

    #[Route('/{id}', name: 'documents_update', methods: ['PUT'])]
    public function update(Document $document, Request $request): JsonResponse
    {
        try {
            $document = $this->documentService->updateDocument(
                $document,
                $request->getContent()
            );
            return $this->json($document);
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

    #[Route('/{id}/download', name: 'documents_download', methods: ['GET'])]
    public function download(Document $document): Response
    {
        try {
            $filePath = $this->documentService->getDocumentFile($document);
            return $this->file($filePath);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }
} 