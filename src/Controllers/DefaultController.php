<?php
namespace App\Controllers;

use App\Models\ProgramItem;
use App\Models\FAQ;

class DefaultController extends BaseController
{
    public function index()
    {
        echo $this->twig->render('pages/home.twig', [
            'user' => $this->getCurrentUser(),
            'active_page' => 'home'
        ]);
    }

    public function program()
    {
        // Get program items grouped by date
        $programItems = ProgramItem::getGroupedByDate($this->db);

        echo $this->twig->render('pages/program.twig', [
            'user' => $this->getCurrentUser(),
            'active_page' => 'program',
            'program_items' => $programItems
        ]);
    }

    public function info()
    {
        echo $this->twig->render('pages/info.twig', [
            'user' => $this->getCurrentUser(),
            'active_page' => 'info'
        ]);
    }

    public function faq()
    {
        // Get FAQ items grouped by category
        $faqItems = FAQ::getGroupedByCategory($this->db);

        echo $this->twig->render('pages/faq.twig', [
            'user' => $this->getCurrentUser(),
            'active_page' => 'faq',
            'faq_items' => $faqItems
        ]);
    }

    public function contact()
    {
        echo $this->twig->render('pages/contact.twig', [
            'user' => $this->getCurrentUser(),
            'active_page' => 'contact'
        ]);
    }
}
