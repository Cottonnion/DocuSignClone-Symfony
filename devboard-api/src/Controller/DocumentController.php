<?php

namespace App\Controller;

use App\Entity\Document;
use App\Entity\Signatory;
use App\Repository\DocumentRepository;
use App\Repository\AuditLogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('api/v1/documents')]
class DocumentController extends AbstractController
{
    public function __construct(
        private DocumentRepository $documentRepository,
        private EntityManagerInterface $entityManager,
        private SerializerInterface $serializer
    ) {
    }

    #[Route('', name: 'documents_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $status = $request->query->get('status');
        $isTemplate = $request->query->get('is_template');
        $createdBy = $request->query->get('created_by');

        $documents = $this->documentRepository->findByFilters($status, $isTemplate, $createdBy);
        
        return $this->json($documents);
    }

    #[Route('', name: 'documents_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $document = $this->serializer->deserialize($request->getContent(), Document::class, 'json');
        $document->setCreatedBy($this->getUser());
        
        $this->entityManager->persist($document);
        $this->entityManager->flush();

        return $this->json($document, Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'documents_get', methods: ['GET'])]
    public function get(Document $document): JsonResponse
    {
        return $this->json($document);
    }

    #[Route('/{id}', name: 'documents_update', methods: ['PUT'])]
    public function update(Document $document, Request $request): JsonResponse
    {
        $this->serializer->deserialize(
            $request->getContent(),
            Document::class,
            'json',
            ['object_to_populate' => $document]
        );
        
        $this->entityManager->flush();

        return $this->json($document);
    }

    #[Route('/{id}', name: 'documents_delete', methods: ['DELETE'])]
    public function delete(Document $document): JsonResponse
    {
        $this->entityManager->remove($document);
        $this->entityManager->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}/audit', name: 'documents_audit', methods: ['GET'])]
    public function audit(Document $document, AuditLogRepository $auditLogRepository): JsonResponse
    {
        $auditLogs = $auditLogRepository->findBy(['document' => $document], ['timestamp' => 'DESC']);
        return $this->json($auditLogs);
    }

    #[Route('/{id}/send', name: 'documents_send', methods: ['POST'])]
    public function send(Document $document): JsonResponse
    {
        if ($document->getStatus() !== 'draft') {
            return $this->json(['error' => 'Document must be in draft status'], Response::HTTP_BAD_REQUEST);
        }

        $document->setStatus('sent');
        $this->entityManager->flush();

        return $this->json($document);
    }

    #[Route('/{id}/remind', name: 'documents_remind', methods: ['POST'])]
    public function remind(Document $document): JsonResponse
    {
        if ($document->getStatus() !== 'sent') {
            return $this->json(['error' => 'Document must be in sent status'], Response::HTTP_BAD_REQUEST);
        }

        // TODO: Implement reminder logic
        return $this->json(['message' => 'Reminders sent']);
    }

    #[Route('/{id}/download', name: 'documents_download', methods: ['GET'])]
    public function download(Document $document): Response
    {
        if ($document->getStatus() !== 'signed') {
            return $this->json(['error' => 'Document must be signed'], Response::HTTP_BAD_REQUEST);
        }

        return $this->file($document->getFilePath());
    }
} 