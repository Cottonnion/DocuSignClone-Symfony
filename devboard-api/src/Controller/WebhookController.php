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

/**
 * Controller handling webhook management operations.
 * 
 * This controller provides endpoints for:
 * - Listing all webhooks {@see WebhookController::list()}
 * - Creating new webhooks {@see WebhookController::create()}
 * - Retrieving webhook details {@see WebhookController::get()}
 * - Updating webhook configurations {@see WebhookController::update()}
 * - Deleting webhooks {@see WebhookController::delete()}
 * 
 * Webhooks are used to notify external systems about document-related events
 * such as document creation, updates, and signing status changes.
 */
#[Route('api/v1/webhooks')]
class WebhookController extends AbstractController
{
    public function __construct(
        private WebhookRepository $webhookRepository,
        private EntityManagerInterface $entityManager,
        private SerializerInterface $serializer
    ) {
    }

    /**
     * Retrieves a list of all webhooks in the system.
     * 
     * @return JsonResponse List of webhook objects
     */
    #[Route('', name: 'webhooks_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $webhooks = $this->webhookRepository->findAll();
        return $this->json($webhooks);
    }

    /**
     * Creates a new webhook configuration.
     * 
     * @param Request $request The request containing webhook configuration
     * @return JsonResponse The created webhook object with HTTP 201 status
     */
    #[Route('', name: 'webhooks_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $webhook = $this->serializer->deserialize($request->getContent(), Webhook::class, 'json');
        
        $this->entityManager->persist($webhook);
        $this->entityManager->flush();

        return $this->json($webhook, Response::HTTP_CREATED);
    }

    /**
     * Retrieves details of a specific webhook.
     * 
     * @param Webhook $webhook The webhook to retrieve
     * @return JsonResponse The webhook object
     */
    #[Route('/{id}', name: 'webhooks_get', methods: ['GET'])]
    public function get(Webhook $webhook): JsonResponse
    {
        return $this->json($webhook);
    }

    /**
     * Updates an existing webhook configuration.
     * 
     * @param Webhook $webhook The webhook to update
     * @param Request $request The request containing updated webhook configuration
     * @return JsonResponse The updated webhook object
     */
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

    /**
     * Deletes a webhook configuration.
     * 
     * @param Webhook $webhook The webhook to delete
     * @return JsonResponse Empty response with HTTP 204 status
     */
    #[Route('/{id}', name: 'webhooks_delete', methods: ['DELETE'])]
    public function delete(Webhook $webhook): JsonResponse
    {
        $this->entityManager->remove($webhook);
        $this->entityManager->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
} 