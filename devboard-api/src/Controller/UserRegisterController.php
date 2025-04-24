<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;


final class UserRegisterController extends AbstractController
{
    #[Route('/api/user/register', name: 'app_user_register', methods:['POST', 'GET'])]
    public function index(Request $req): Response
    {
        return $this->json([
            'message' => 'User registered succesfully!',
            'your name' => $req->get('name')?? 'no name',
            'status' => 'success'
        ], 201);
    }
}
