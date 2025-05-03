<?php

namespace App\Controller;

use App\Entity\Signatory;
use App\Repository\SignatoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Controller handling document signing operations.
 * Provides endpoints for retrieving document details, submitting signatures, and managing signing fields.
 */
#[Route('api/v1/signing')]
class SigningController extends AbstractController
{
    public function __construct(
        private SignatoryRepository $signatoryRepository,
        private EntityManagerInterface $entityManager,
        private SerializerInterface $serializer
    ) {
    }

    /**
     * Retrieves document and signatory details for a given signing token.
     * 
     * @param string $token The unique token identifying the signatory
     * @return JsonResponse Returns document and signatory details if found and pending, or an error response
     * 
     * @throws JsonResponse Returns 404 if signatory not found
     * @throws JsonResponse Returns 400 if document is already signed or declined
     * 
     * Response example:
     * {
     *     "document": {
     *         "id": 123,
     *         "title": "Sample Document",
     *         "status": "pending",
     *         "filePath": "documents/sample.pdf"
     *     },
     *     "signatory": {
     *         "id": 456,
     *         "email": "signer@example.com",
     *         "name": "John Doe",
     *         "status": "pending",
     *         "signingOrder": 1
     *     }
     * }
     */
    #[Route('/{token}', name: 'signing_get', methods: ['GET'])]
    public function getDocument(string $token): JsonResponse
    {
        $signatory = $this->signatoryRepository->findOneBy(['token' => $token]);
        if (!$signatory) {
            return $this->json(['error' => 'Signatory not found'], Response::HTTP_NOT_FOUND);
        }

        if ($signatory->getStatus() !== 'pending') {
            return $this->json(['error' => 'Document already signed or declined'], Response::HTTP_BAD_REQUEST);
        }

        return $this->json([
            'document' => $signatory->getDocument(),
            'signatory' => $signatory
        ], Response::HTTP_OK, [], ['groups' => ['signatory:read', 'document:read']]);
    }

    /**
     * Submits a signature for a document identified by the signatory token.
     * 
     * @param string $token The unique token identifying the signatory
     * @param Request $request The HTTP request containing the signature data
     * @return JsonResponse Returns success message if signature is submitted successfully, or an error response
     * 
     * @throws JsonResponse Returns 404 if signatory not found
     * @throws JsonResponse Returns 400 if document is already signed/declined or signature is missing
     * 
     * Request body example:
     * {
     *     "signature": "base64_encoded_{signature_data}"
     * }
     * 
     * The signature should be a base64-encoded string of the signature image or data.
     * This can be obtained from a signature pad or similar input method.
     * 
     * Response example:
     * {
     *     "message": "Document signed successfully"
     * }
     */
    #[Route('/{token}', name: 'signing_submit', methods: ['POST'])]
    public function submitSignature(string $token, Request $request): JsonResponse
    {
        $signatory = $this->signatoryRepository->findOneBy(['token' => $token]);
        if (!$signatory) {
            return $this->json(['error' => 'Signatory not found'], Response::HTTP_NOT_FOUND);
        }

        if ($signatory->getStatus() !== 'pending') {
            return $this->json(['error' => 'Document already signed or declined'], Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode($request->getContent(), true);
        $signature = $data['signature'] ?? null;

        if (!$signature) {
            return $this->json(['error' => 'Signature is required'], Response::HTTP_BAD_REQUEST);
        }

        $signatory->setStatus('signed');
        $signatory->setSigned(true);
        $signatory->setSignedAt(new \DateTimeImmutable());
        $signatory->setIpAddress($request->getClientIp());
        $signatory->setUserAgent($request->headers->get('User-Agent'));

        $this->entityManager->flush();

        return $this->json(['message' => 'Document signed successfully']);
    }

    /**
     * Retrieves the required fields for document signing.
     * 
     * @param string $token The unique token identifying the signatory
     * @return JsonResponse Returns the list of required signing fields if signatory is found and pending
     * 
     * @throws JsonResponse Returns 404 if signatory not found
     * @throws JsonResponse Returns 400 if document is already signed or declined
     * 
     * Response example:
     * {
     *     "fields": {
     *         "signature": {
     *             "type": "signature",
     *             "required": true
     *         },
     *         "name": {
     *             "type": "text",
     *             "required": true
     *         },
     *         "date": {
     *             "type": "date",
     *             "required": true
     *         }
     *     }
     * }
     */
    #[Route('/{token}/fields', name: 'signing_fields', methods: ['GET'])]
    public function getFields(string $token): JsonResponse
    {
        $signatory = $this->signatoryRepository->findOneBy(['token' => $token]);
        if (!$signatory) {
            return $this->json(['error' => 'Signatory not found'], Response::HTTP_NOT_FOUND);
        }

        if ($signatory->getStatus() !== 'pending') {
            return $this->json(['error' => 'Document already signed or declined'], Response::HTTP_BAD_REQUEST);
        }

        return $this->json([
            'fields' => [
                'signature' => ['type' => 'signature', 'required' => true],
                'name' => ['type' => 'text', 'required' => true],
                'date' => ['type' => 'date', 'required' => true],
            ]
        ]);
    }

    /**
     * Submits the completed signing fields for a document.
     * 
     * @param string $token The unique token identifying the signatory
     * @param Request $request The HTTP request containing the completed fields
     * @return JsonResponse Returns success message if fields are submitted successfully, or an error response
     * 
     * @throws JsonResponse Returns 404 if signatory not found
     * @throws JsonResponse Returns 400 if document is already signed or declined
     * 
     * Request body example:
     * {
     *     "signature": "base64_encoded_{signature_data}",
     *     "name": "John Doe",
     *     "date": "2024-05-03"
     * }
     * 
     * All fields are required:
     * - signature: base64-encoded signature image/data
     * - name: full name of the signatory
     * - date: signing date in YYYY-MM-DD format
     * 
     * Response example:
     * {
     *     "message": "Fields submitted successfully"
     * }
     */
    #[Route('/{token}/fields', name: 'signing_submit_fields', methods: ['POST'])]
    public function submitFields(string $token, Request $request): JsonResponse
    {
        $signatory = $this->signatoryRepository->findOneBy(['token' => $token]);
        if (!$signatory) {
            return $this->json(['error' => 'Signatory not found'], Response::HTTP_NOT_FOUND);
        }

        if ($signatory->getStatus() !== 'pending') {
            return $this->json(['error' => 'Document already signed or declined'], Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode($request->getContent(), true);
        
        // TODO: Validate and process the submitted fields
        // For now, just return success
        return $this->json(['message' => 'Fields submitted successfully']);
    }
} 