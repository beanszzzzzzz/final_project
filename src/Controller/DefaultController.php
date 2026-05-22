<?php
// src/Controller/DefaultController.php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DefaultController extends AbstractController
{
    #[Route('/login-redirect', name: 'home')]
    public function index(): Response
    {
        // Optional helper route to redirect explicitly to login.
        return $this->redirectToRoute('app_login');
    }
}
