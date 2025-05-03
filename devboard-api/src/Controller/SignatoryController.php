<?php

namespace App\Controller;

use App\Entity\Document;
use App\Entity\Signatory;
use App\Repository\DocumentRepository;
use App\Repository\SignatoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Controller handling signatory management operations.
 * Provides endpoints for listing, creating, updating, and deleting signatories for documents.
 */
#[Route('api/v1/documents/{documentId}/signatories')]
class SignatoryController extends AbstractController
{
    public function __construct(
        private DocumentRepository $documentRepository,
        private SignatoryRepository $signatoryRepository,
        private EntityManagerInterface $entityManager,
        private SerializerInterface $serializer
    ) {
    }

    /**
     * Lists all signatories for a specific document.
     * 
     * @param int $documentId The ID of the document
     * @return JsonResponse Returns a list of signatories if found, or an error response
     * 
     * @throws JsonResponse Returns 404 if document not found
     * 
     * Response example:
     * [
     *     {
     *         "id": 1,
     *         "email": "signer1@example.com",
     *         "name": "John Doe",
     *         "signingOrder": 1,
     *         "status": "pending",
     *         "token": "550e8400-e29b-41d4-a716-446655440000",
     *         "signed": false
     *     },
     *     {
     *         "id": 2,
     *         "email": "signer2@example.com",
     *         "name": "Jane Smith",
     *         "signingOrder": 2,
     *         "status": "pending",
     *         "token": "550e8400-e29b-41d4-a716-446655440001",
     *         "signed": false
     *     }
     * ]
     */
    #[Route('', name: 'signatory_list', methods: ['GET'])]
    public function list(int $documentId): JsonResponse
    {
        $document = $this->documentRepository->find($documentId);
        if (!$document) {
            return $this->json(['error' => 'Document not found'], Response::HTTP_NOT_FOUND);
        }

        $signatories = $this->signatoryRepository->findBy(['document' => $document], ['signingOrder' => 'ASC']);
        return $this->json($signatories, Response::HTTP_OK, [], ['groups' => ['signatory:read']]);
    }

    /**
     * Creates a new signatory for a document.
     * 
     * @param int $documentId The ID of the document
     * @param Request $request The HTTP request containing signatory data
     * @return JsonResponse Returns the created signatory if successful, or an error response
     * 
     * @throws JsonResponse Returns 404 if document not found
     * @throws JsonResponse Returns 400 if document is not in draft status
     * @throws JsonResponse Returns 400 if required fields are missing
     * 
     * Request body example:
     * {
     *     "email": "signer@example.com",
     *     "name": "John Doe",
     *     "signingOrder": 1
     * }
     * 
     * Required fields:
     * - email: valid email address of the signatory
     * - name: full name of the signatory
     * - signingOrder: integer indicating the order in which the signatory should sign
     * 
     * Response example:
     * {
     *     "id": 1,
     *     "email": "signer@example.com",
     *     "name": "John Doe",
     *     "signingOrder": 1,
     *     "status": "pending",
     *     "token": "550e8400-e29b-41d4-a716-446655440000",
     *     "signed": false
     * }
     */
    #[Route('', name: 'signatory_create', methods: ['POST'])]
    public function create(int $documentId, Request $request): JsonResponse
    {
        $document = $this->documentRepository->find($documentId);
        if (!$document) {
            return $this->json(['error' => 'Document not found'], Response::HTTP_NOT_FOUND);
        }

        if ($document->getStatus() !== 'draft') {
            return $this->json(['error' => 'Document must be in draft status'], Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode($request->getContent(), true);
        if (!isset($data['signingOrder'])) {
            // Get the next available signing order
            $lastSignatory = $this->signatoryRepository->findOneBy(
                ['document' => $document],
                ['signingOrder' => 'DESC']
            );
            $data['signingOrder'] = $lastSignatory ? $lastSignatory->getSigningOrder() + 1 : 1;
        }

        $signatory = $this->serializer->deserialize(
            json_encode($data),
            Signatory::class,
            'json',
            ['groups' => ['signatory:write']]
        );
        $signatory->setDocument($document);
        $signatory->setToken(Uuid::v4()->toRfc4122());
        
        $this->entityManager->persist($signatory);
        $this->entityManager->flush();

        return $this->json($signatory, Response::HTTP_CREATED, [], ['groups' => ['signatory:read']]);
    }

    /**
     * Updates an existing signatory.
     * 
     * @param int $documentId The ID of the document
     * @param int $id The ID of the signatory to update
     * @param Request $request The HTTP request containing updated signatory data
     * @return JsonResponse Returns the updated signatory if successful, or an error response
     * 
     * @throws JsonResponse Returns 404 if document or signatory not found
     * @throws JsonResponse Returns 400 if document is not in draft status
     * @throws JsonResponse Returns 400 if signatory belongs to a different document
     * 
     * Request body example:
     * {
     *     "email": "updated@example.com",
     *     "name": "Updated Name",
     *     "signingOrder": 2
     * }
     * 
     * All fields are optional. Only provided fields will be updated.
     * 
     * Response example:
     * {
     *     "id": 1,
     *     "email": "updated@example.com",
     *     "name": "Updated Name",
     *     "signingOrder": 2,
     *     "status": "pending",
     *     "token": "550e8400-e29b-41d4-a716-446655440000",
     *     "signed": false
     * }
     */
    #[Route('/{id}', name: 'signatory_update', methods: ['PUT'])]
    public function update(int $documentId, int $id, Request $request): JsonResponse
    {
        $document = $this->documentRepository->find($documentId);
        if (!$document) {
            return $this->json(['error' => 'Document not found'], Response::HTTP_NOT_FOUND);
        }

        $signatory = $this->signatoryRepository->find($id);
        if (!$signatory) {
            return $this->json(['error' => 'Signatory not found'], Response::HTTP_NOT_FOUND);
        }

        if ($signatory->getDocument() !== $document) {
            return $this->json(['error' => 'Signatory not found for this document'], Response::HTTP_NOT_FOUND);
        }

        if ($document->getStatus() !== 'draft') {
            return $this->json(['error' => 'Document must be in draft status'], Response::HTTP_BAD_REQUEST);
        }

        $this->serializer->deserialize(
            $request->getContent(),
            Signatory::class,
            'json',
            [
                'object_to_populate' => $signatory,
                'groups' => ['signatory:write']
            ]
        );
        
        $this->entityManager->flush();

        return $this->json($signatory, Response::HTTP_OK, [], ['groups' => ['signatory:read']]);
    }

    /**
     * Deletes a signatory from a document.
     * 
     * @param int $documentId The ID of the document
     * @param int $id The ID of the signatory to delete
     * @return JsonResponse Returns success message if deleted, or an error response
     * 
     * @throws JsonResponse Returns 404 if document or signatory not found
     * @throws JsonResponse Returns 400 if document is not in draft status
     * @throws JsonResponse Returns 400 if signatory belongs to a different document
     * 
     * Response example:
     * {
     *     "message": "Signatory deleted successfully"
     * }
     */
    #[Route('/{id}', name: 'signatory_delete', methods: ['DELETE'])]
    public function delete(int $documentId, int $id): JsonResponse
    {
        $document = $this->documentRepository->find($documentId);
        if (!$document) {
            return $this->json(['error' => 'Document not found'], Response::HTTP_NOT_FOUND);
        }

        $signatory = $this->signatoryRepository->find($id);
        if (!$signatory) {
            return $this->json(['error' => 'Signatory not found'], Response::HTTP_NOT_FOUND);
        }

        if ($signatory->getDocument() !== $document) {
            return $this->json(['error' => 'Signatory not found for this document'], Response::HTTP_NOT_FOUND);
        }

        if ($document->getStatus() !== 'draft') {
            return $this->json(['error' => 'Document must be in draft status'], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->remove($signatory);
        $this->entityManager->flush();

        return $this->json(['message' => 'Signatory deleted successfully'], Response::HTTP_NO_CONTENT);
    }
} 