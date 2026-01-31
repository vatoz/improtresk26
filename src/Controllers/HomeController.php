<?php
namespace App\Controllers;

class HomeController extends BaseController
{
    public function index()
    {
        echo $this->twig->render('pages/home.twig', [
            'user' => $this->getCurrentUser(),
            'active_page' => 'home'
        ]);
    }
}