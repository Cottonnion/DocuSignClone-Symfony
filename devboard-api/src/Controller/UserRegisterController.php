<?php

namespace App\Controller;

use App\Service\User\RegisterService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;


final class UserRegisterController extends AbstractController
{

    public function __construct(private RegisterService $registrationService){}


    #[Route('/api/user/register', name: 'app_user_register', methods:['POST'])]
    public function index(Request $req): Response
    {

        $reqData = json_decode($req->getContent());

        $result = $this->registrationService->register(['name' => $reqData->name?? 'John Doe']);

        return $this->json($result, 201);
    }
}
