<?php

namespace App\Controller;

use App\Entity\Document;
use App\Entity\DocumentVersion;
use App\Repository\DocumentRepository;
use App\Repository\DocumentVersionRepository;
use App\Service\DocumentVersionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Psr\Log\LoggerInterface;

#[Route('api/v1/documents/{documentId}/versions')]
class DocumentVersionController extends AbstractController
{
    public function __construct(
        private DocumentRepository $documentRepository,
        private DocumentVersionRepository $versionRepository,
        private DocumentVersionService $versionService,
        private EntityManagerInterface $entityManager,
        private SerializerInterface $serializer,
        private LoggerInterface $logger
    ) {
    }

    #[Route('', name: 'document_versions_list', methods: ['GET'])]
    public function list(int $documentId): JsonResponse
    {
        $document = $this->documentRepository->find($documentId);
        if (!$document) {
            return $this->json(['error' => 'Document not found'], Response::HTTP_NOT_FOUND);
        }

        $versions = $this->versionRepository->findByDocumentOrderedByVersion($document);
        $json = $this->serializer->serialize($versions, 'json', ['groups' => 'document:read']);
        return new JsonResponse($json, Response::HTTP_OK, [], true);
    }

    #[Route('', name: 'document_versions_create', methods: ['POST'])]
    public function create(int $documentId, Request $request): JsonResponse
    {
        $document = $this->documentRepository->find($documentId);
        if (!$document) {
            return $this->json(['error' => 'Document not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        $this->logger->info('Creating version with data', ['data' => $data]);
        
        $changeDescription = $data['changeDescription'] ?? null;
        $this->logger->info('Change description', ['changeDescription' => $changeDescription]);

        $version = $this->versionService->createVersion($document, $changeDescription);
        
        $json = $this->serializer->serialize($version, 'json', ['groups' => 'document:read']);
        return new JsonResponse($json, Response::HTTP_CREATED, [], true);
    }

    #[Route('/{id}', name: 'document_versions_get', methods: ['GET'])]
    public function get(int $documentId, int $id): JsonResponse
    {
        $document = $this->documentRepository->find($documentId);
        if (!$document) {
            return $this->json(['error' => 'Document not found'], Response::HTTP_NOT_FOUND);
        }

        $version = $this->versionRepository->find($id);
        if (!$version || $version->getDocument() !== $document) {
            return $this->json(['error' => 'Version not found'], Response::HTTP_NOT_FOUND);
        }

        $json = $this->serializer->serialize($version, 'json', ['groups' => 'document:read']);
        return new JsonResponse($json, Response::HTTP_OK, [], true);
    }

    #[Route('/{id}/restore', name: 'document_versions_restore', methods: ['POST'])]
    public function restore(int $documentId, int $id): JsonResponse
    {
        $document = $this->documentRepository->find($documentId);
        if (!$document) {
            return $this->json(['error' => 'Document not found'], Response::HTTP_NOT_FOUND);
        }

        $version = $this->versionRepository->find($id);
        if (!$version || $version->getDocument() !== $document) {
            return $this->json(['error' => 'Version not found'], Response::HTTP_NOT_FOUND);
        }

        $this->versionService->restoreVersion($document, $version);
        
        $json = $this->serializer->serialize($document, 'json', ['groups' => 'document:read']);
        return new JsonResponse($json, Response::HTTP_OK, [], true);
    }
} 