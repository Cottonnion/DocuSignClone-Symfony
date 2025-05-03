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

#[Route('api/v1/templates')]
class TemplateController extends AbstractController
{
    public function __construct(
        private TemplateRepository $templateRepository,
        private EntityManagerInterface $entityManager,
        private SerializerInterface $serializer
    ) {
    }

    #[Route('', name: 'templates_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $templates = $this->templateRepository->findAll();
        return $this->json($templates);
    }

    #[Route('', name: 'templates_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $template = $this->serializer->deserialize($request->getContent(), Template::class, 'json');
        $template->setCreatedBy($this->getUser());
        
        $this->entityManager->persist($template);
        $this->entityManager->flush();

        return $this->json($template, Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'templates_get', methods: ['GET'])]
    public function get(Template $template): JsonResponse
    {
        return $this->json($template);
    }

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

    #[Route('/{id}', name: 'templates_delete', methods: ['DELETE'])]
    public function delete(Template $template): JsonResponse
    {
        $this->entityManager->remove($template);
        $this->entityManager->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

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