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

#[Route('api/v1/signing')]
class SigningController extends AbstractController
{
    public function __construct(
        private SignatoryRepository $signatoryRepository,
        private EntityManagerInterface $entityManager,
        private SerializerInterface $serializer
    ) {
    }

    #[Route('/{token}', name: 'signing_get', methods: ['GET'])]
    public function getDocument(Signatory $signatory): JsonResponse
    {
        if ($signatory->getStatus() !== 'pending') {
            return $this->json(['error' => 'Document already signed or declined'], Response::HTTP_BAD_REQUEST);
        }

        return $this->json([
            'document' => $signatory->getDocument(),
            'signatory' => $signatory
        ]);
    }

    #[Route('/{token}', name: 'signing_submit', methods: ['POST'])]
    public function submitSignature(Signatory $signatory, Request $request): JsonResponse
    {
        if ($signatory->getStatus() !== 'pending') {
            return $this->json(['error' => 'Document already signed or declined'], Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode($request->getContent(), true);
        $signature = $data['signature'] ?? null;

        if (!$signature) {
            return $this->json(['error' => 'Signature is required'], Response::HTTP_BAD_REQUEST);
        }

        $signatory->setStatus('signed');
        $signatory->setSignedAt(new \DateTimeImmutable());
        $signatory->setIpAddress($request->getClientIp());
        $signatory->setUserAgent($request->headers->get('User-Agent'));

        $this->entityManager->flush();

        return $this->json(['message' => 'Document signed successfully']);
    }

    #[Route('/{token}/fields', name: 'signing_fields', methods: ['GET'])]
    public function getFields(Signatory $signatory): JsonResponse
    {
        if ($signatory->getStatus() !== 'pending') {
            return $this->json(['error' => 'Document already signed or declined'], Response::HTTP_BAD_REQUEST);
        }

        // TODO: Return the required fields for signing
        return $this->json([
            'fields' => [
                'signature' => ['type' => 'signature', 'required' => true],
                'name' => ['type' => 'text', 'required' => true],
                'date' => ['type' => 'date', 'required' => true],
            ]
        ]);
    }

    #[Route('/{token}/fields', name: 'signing_submit_fields', methods: ['POST'])]
    public function submitFields(Signatory $signatory, Request $request): JsonResponse
    {
        if ($signatory->getStatus() !== 'pending') {
            return $this->json(['error' => 'Document already signed or declined'], Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode($request->getContent(), true);
        
        // TODO: Validate and process the submitted fields
        // For now, just return success
        return $this->json(['message' => 'Fields submitted successfully']);
    }
} 