<?php

namespace App\Controller;

use App\Entity\Webhook;
use App\Repository\WebhookRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('api/v1/webhooks')]
class WebhookController extends AbstractController
{
    public function __construct(
        private WebhookRepository $webhookRepository,
        private EntityManagerInterface $entityManager,
        private SerializerInterface $serializer
    ) {
    }

    #[Route('', name: 'webhooks_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $webhooks = $this->webhookRepository->findAll();
        return $this->json($webhooks);
    }

    #[Route('', name: 'webhooks_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $webhook = $this->serializer->deserialize($request->getContent(), Webhook::class, 'json');
        
        $this->entityManager->persist($webhook);
        $this->entityManager->flush();

        return $this->json($webhook, Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'webhooks_get', methods: ['GET'])]
    public function get(Webhook $webhook): JsonResponse
    {
        return $this->json($webhook);
    }

    #[Route('/{id}', name: 'webhooks_update', methods: ['PUT'])]
    public function update(Webhook $webhook, Request $request): JsonResponse
    {
        $this->serializer->deserialize(
            $request->getContent(),
            Webhook::class,
            'json',
            ['object_to_populate' => $webhook]
        );
        
        $this->entityManager->flush();

        return $this->json($webhook);
    }

    #[Route('/{id}', name: 'webhooks_delete', methods: ['DELETE'])]
    public function delete(Webhook $webhook): JsonResponse
    {
        $this->entityManager->remove($webhook);
        $this->entityManager->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
} 