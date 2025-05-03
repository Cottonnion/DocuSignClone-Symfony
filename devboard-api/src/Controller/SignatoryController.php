<?php

namespace App\Controller;

use App\Entity\Document;
use App\Entity\Signatory;
use App\Repository\SignatoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('api/v1/documents/{documentId}/signatories')]
class SignatoryController extends AbstractController
{
    public function __construct(
        private SignatoryRepository $signatoryRepository,
        private EntityManagerInterface $entityManager,
        private SerializerInterface $serializer
    ) {
    }

    #[Route('', name: 'signatories_list', methods: ['GET'])]
    public function list(Document $document): JsonResponse
    {
        $signatories = $this->signatoryRepository->findBy(['document' => $document]);
        return $this->json($signatories);
    }

    #[Route('', name: 'signatories_create', methods: ['POST'])]
    public function create(Document $document, Request $request): JsonResponse
    {
        if ($document->getStatus() !== 'draft') {
            return $this->json(['error' => 'Document must be in draft status'], Response::HTTP_BAD_REQUEST);
        }

        $signatory = $this->serializer->deserialize($request->getContent(), Signatory::class, 'json');
        $signatory->setDocument($document);
        
        $this->entityManager->persist($signatory);
        $this->entityManager->flush();

        return $this->json($signatory, Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'signatories_update', methods: ['PUT'])]
    public function update(Document $document, Signatory $signatory, Request $request): JsonResponse
    {
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
            ['object_to_populate' => $signatory]
        );
        
        $this->entityManager->flush();

        return $this->json($signatory);
    }

    #[Route('/{id}', name: 'signatories_delete', methods: ['DELETE'])]
    public function delete(Document $document, Signatory $signatory): JsonResponse
    {
        if ($signatory->getDocument() !== $document) {
            return $this->json(['error' => 'Signatory not found for this document'], Response::HTTP_NOT_FOUND);
        }

        if ($document->getStatus() !== 'draft') {
            return $this->json(['error' => 'Document must be in draft status'], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->remove($signatory);
        $this->entityManager->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
} 