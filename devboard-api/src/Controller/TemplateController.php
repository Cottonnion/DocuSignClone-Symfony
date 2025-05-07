<?php

namespace App\Controller;

use App\Entity\Document;
use App\Entity\Template;
use App\Repository\TemplateRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Controller handling document template management operations.
 * 
 * This controller provides endpoints for:
 * - Listing all document templates {@see TemplateController::list()}
 * - Creating new templates {@see TemplateController::create()}
 * - Retrieving template details {@see TemplateController::get()}
 * - Updating template configurations {@see TemplateController::update()}
 * - Deleting templates {@see TemplateController::delete()}
 * - Using templates to create new documents {@see TemplateController::use()}
 * 
 * Templates serve as reusable document blueprints that can be used
 * to quickly create new documents with predefined content and structure.
 */
#[Route('api/v1/templates')]
class TemplateController extends AbstractController
{
    public function __construct(
        private TemplateRepository $templateRepository,
        private EntityManagerInterface $entityManager,
        private SerializerInterface $serializer
    ) {
    }

    /**
     * Retrieves a list of all document templates.
     * 
     * @return JsonResponse List of template objects
     */
    #[Route('', name: 'templates_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $templates = $this->templateRepository->findAll();
        return $this->json($templates);
    }

    /**
     * Creates a new document template.
     * 
     * @param Request $request The request containing template configuration
     * @return JsonResponse The created template object with HTTP 201 status
     */
    #[Route('', name: 'templates_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $template = $this->serializer->deserialize($request->getContent(), Template::class, 'json');
        $template->setCreatedBy($this->getUser());
        
        $this->entityManager->persist($template);
        $this->entityManager->flush();

        return $this->json($template, Response::HTTP_CREATED);
    }

    /**
     * Retrieves details of a specific template.
     * 
     * @param Template $template The template to retrieve
     * @return JsonResponse The template object
     */
    #[Route('/{id}', name: 'templates_get', methods: ['GET'])]
    public function get(Template $template): JsonResponse
    {
        return $this->json($template);
    }

    /**
     * Updates an existing template configuration.
     * 
     * @param Template $template The template to update
     * @param Request $request The request containing updated template configuration
     * @return JsonResponse The updated template object
     */
    #[Route('/{id}', name: 'templates_update', methods: ['PUT'])]
    public function update(Template $template, Request $request): JsonResponse
    {
        $this->serializer->deserialize(
            $request->getContent(),
            Template::class,
            'json',
            ['object_to_populate' => $template]
        );
        
        $this->entityManager->flush();

        return $this->json($template);
    }

    /**
     * Deletes a template.
     * 
     * @param Template $template The template to delete
     * @return JsonResponse Empty response with HTTP 204 status
     */
    #[Route('/{id}', name: 'templates_delete', methods: ['DELETE'])]
    public function delete(Template $template): JsonResponse
    {
        $this->entityManager->remove($template);
        $this->entityManager->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Creates a new document from a template.
     * 
     * @param Template $template The template to use
     * @return JsonResponse The created document object with HTTP 201 status
     */
    #[Route('/{id}/use', name: 'templates_use', methods: ['POST'])]
    public function use(Template $template): JsonResponse
    {
        $document = new Document();
        $document->setTitle($template->getDocument()->getTitle());
        $document->setFilePath($template->getDocument()->getFilePath());
        $document->setCreatedBy($this->getUser());
        $document->setIsTemplate(false);

        $this->entityManager->persist($document);
        $this->entityManager->flush();

        return $this->json($document, Response::HTTP_CREATED);
    }
} 