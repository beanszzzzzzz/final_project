<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        return $this->render('landingpage/home.html.twig');
    }

    #[Route('/about', name: 'app_about')]
    public function about(): Response
    {
        return $this->render('landingpage/about.html.twig');
    }

    #[Route('/menu', name: 'app_menu')]
    public function menu(): Response
    {
        return $this->render('landingpage/menu.html.twig');
    }

    #[Route('/contact', name: 'app_contact')]
    public function contact(): Response
    {
        return $this->render('landingpage/contact.html.twig');
    }

    #[Route('/reserve', name: 'app_reservation')]
    public function reservation(): Response
    {
        return $this->render('landingpage/reservation.html.twig');
    }
}