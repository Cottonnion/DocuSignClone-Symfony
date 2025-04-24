<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class userAuthController extends AbstractController
{
    #[Route('api/hello/', name: 'user_auth_controller')]
    public function index(): Response
    {

        return $this->json([
            'message' => 'Hello Api!!',
            'someData' => [
                'file' => file_get_contents(__FILE__),
                'path' => __FILE__,
                'line' => __LINE__,
            ]
        ]);
    }
}
