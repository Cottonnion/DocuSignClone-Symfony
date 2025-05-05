<?php

namespace App\Controller;

use App\Entity\Document;
use App\Entity\DocumentVersion;
use App\Repository\DocumentRepository;
use App\Repository\DocumentVersionRepository;
use App\Service\Document\DocumentVersionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Psr\Log\LoggerInterface;

/**
 * Controller handling document version management operations.
 * 
 * This controller provides endpoints for:
 * - Listing all versions of a document
 * - Creating new versions of a document
 * - Retrieving specific versions
 * - Restoring previous versions
 * 
 * Each version maintains:
 * - Version number (e.g., "1.0", "1.1")
 * - File path to the versioned document
 * - Change description
 * - Creation timestamp
 * - Creator information
 * 
 * @Route('api/v1/documents/{documentId}/versions')
 */
#[Route('/api/v1/documents/{documentId}/versions')]
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

    /**
     * List all versions of a document.
     * 
     * Returns a chronological list of all versions for the specified document,
     * ordered by version number in descending order (newest first).
     * 
     * @param int $documentId The ID of the document to list versions for
     * @return JsonResponse
     * 
     * @Route('', name: 'document_versions_list', methods: ['GET'])
     * 
     * Response format:
     * [
     *   {
     *     "id": 1,
     *     "versionNumber": "1.1",
     *     "filePath": "document-v1.1.pdf",
     *     "changeDescription": "Updated content",
     *     "createdBy": {
     *       "id": 1,
     *       "username": "user1"
     *     },
     *     "createdAt": "2025-05-04T11:32:33+00:00"
     *   },
     *   ...
     * ]
     */
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

    /**
     * Create a new version of a document.
     * 
     * Creates a new version of the specified document, either by uploading a new file
     * or copying the current file. Supports version metadata and tags.
     * 
     * @param int $documentId The ID of the document to version
     * @param Request $request The request containing version information and optional file
     * @return JsonResponse
     * 
     * @Route('', name: 'document_versions_create', methods: ['POST'])
     * 
     * Request format:
     * - Content-Type: multipart/form-data
     * 
     * Request fields:
     * - file: [binary file data] (optional)
     * - changeDescription: Description of changes
     * - tags: Array of version tags (e.g., ["draft", "reviewed"])
     * - metadata: JSON object with custom metadata
     * - isMajor: Boolean to increment major version (1.0 → 2.0)
     * 
     * Response format:
     * {
     *   "id": 1,
     *   "versionNumber": "1.1",
     *   "filePath": "document-v1.1.pdf",
     *   "changeDescription": "Description of changes",
     *   "status": "draft",
     *   "tags": ["draft", "reviewed"],
     *   "metadata": {
     *     "reviewer": "John Doe",
     *     "reviewDate": "2025-05-04"
     *   },
     *   "fileSize": 1024,
     *   "fileType": "application/pdf",
     *   "isMajor": false,
     *   "createdBy": {
     *     "id": 1,
     *     "username": "user1"
     *   },
     *   "createdAt": "2025-05-04T11:32:33+00:00"
     * }
     */
    #[Route('', name: 'document_versions_create', methods: ['POST'])]
    public function create(int $documentId, Request $request): JsonResponse
    {
        $document = $this->documentRepository->find($documentId);
        if (!$document) {
            return $this->json(['error' => 'Document not found'], Response::HTTP_NOT_FOUND);
        }

        // Get file if uploaded
        $file = $request->files->get('file');

        // Get other fields from form data
        $changeDescription = $request->request->get('changeDescription');
        $tags = $request->request->get('tags') ? json_decode($request->request->get('tags'), true) : null;
        $metadata = $request->request->get('metadata') ? json_decode($request->request->get('metadata'), true) : null;
        $isMajor = filter_var($request->request->get('isMajor', false), FILTER_VALIDATE_BOOLEAN);

        try {
            $version = $this->versionService->createVersion(
                $document,
                $changeDescription,
                $file,
                $tags,
                $metadata,
                $isMajor
            );
            
            $json = $this->serializer->serialize($version, 'json', ['groups' => 'document:read']);
            return new JsonResponse($json, Response::HTTP_CREATED, [], true);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            $this->logger->error('Error creating version', ['error' => $e->getMessage()]);
            return $this->json(
                [
                    'error' => 'Failed to create version',
                    'details' => [
                        'msg'  => $e->getMessage()
                    ]
                ], 
                Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get a specific version of a document.
     * 
     * Retrieves the details of a specific version of a document.
     * 
     * @param int $documentId The ID of the document
     * @param int $id The ID of the version to retrieve
     * @return JsonResponse
     * 
     * @Route('/{id}', name: 'document_versions_get', methods: ['GET'])
     * 
     * Response format:
     * {
     *   "id": 1,
     *   "versionNumber": "1.1",
     *   "filePath": "document-v1.1.pdf",
     *   "changeDescription": "Description of changes in this version",
     *   "createdBy": {
     *     "id": 1,
     *     "username": "user1"
     *   },
     *   "createdAt": "2025-05-04T11:32:33+00:00"
     * }
     */
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

    /**
     * Restore a previous version of a document.
     * 
     * Restores a specific version of a document, making it the current version.
     * This copies the versioned file back to the main document location.
     * 
     * @param int $documentId The ID of the document
     * @param int $id The ID of the version to restore
     * @return JsonResponse
     * 
     * @Route('/{id}/restore', name: 'document_versions_restore', methods: ['POST'])
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
     *   "updatedAt": "2025-05-04T11:32:33+00:00"
     * }
     */
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

    /**
     * Update a version's status.
     * 
     * @param int $documentId The ID of the document
     * @param int $id The ID of the version to update
     * @param Request $request The request containing the new status
     * @return JsonResponse
     * 
     * @Route('/{id}', name: 'document_versions_update', methods: ['PATCH'])
     */
    #[Route('/{id}', name: 'document_versions_update', methods: ['PATCH'])]
    public function update(int $documentId, int $id, Request $request): JsonResponse
    {
        $document = $this->documentRepository->find($documentId);
        if (!$document) {
            return $this->json(['error' => 'Document not found'], Response::HTTP_NOT_FOUND);
        }

        $version = $this->versionRepository->find($id);
        if (!$version || $version->getDocument() !== $document) {
            return $this->json(['error' => 'Version not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        if (!isset($data['status'])) {
            return $this->json(['error' => 'Status is required'], Response::HTTP_BAD_REQUEST);
        }

        $version->setStatus($data['status']);
        $this->entityManager->flush();

        $json = $this->serializer->serialize($version, 'json', ['groups' => 'document:read']);
        return new JsonResponse($json, Response::HTTP_OK, [], true);
    }
} 