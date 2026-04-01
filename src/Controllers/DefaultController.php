<?php
namespace App\Controllers;

use App\Models\ProgramItem;
use App\Models\FAQ;
use App\Models\Person;

class DefaultController extends BaseController
{
    public function index()
    {
        $registrationStart = $_ENV['REGISTRATION_START'] ?? null;
        echo $this->twig->render('pages/home.twig', [
            'user' => $this->getCurrentUser(),
            'active_page' => 'home',
            'registration_start' => $registrationStart,
        ]);
    }

    public function program()
    {
        // Get program items grouped by date and track for concurrent display
        $programItems = ProgramItem::getGroupedByDateAndTrack($this->db);

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

    public function hero()
    {
        $this->handleHeroPage(1, 'hero');
    }

    public function heroine()
    {
        $this->handleHeroPage(2, 'heroine');
    }

    private function handleHeroPage(int $heroValue, string $page)
    {
        $this->requireAuth();

        $secret = $_GET['secret'] ?? '';
        if ($secret !== ($_ENV['HERO_SECRET'] ?? '')) {
            http_response_code(403);
            echo $this->twig->render('pages/hero.twig', [
                'user' => $this->getCurrentUser(),
                'message' => null,
                'page' => $page,
            ]);
            return;
        }

        $user = $this->getCurrentUser();
        if ($user) {
            \App\Models\User::update($this->db, $user['id'], ['hero' => $heroValue]);
            $_SESSION['user']['hero']= $heroValue;
        }

        echo $this->twig->render('pages/hero.twig', [
            'user' => $user,
            'message' => $heroValue === 1 ? 'Jsi náš hrdina!' : 'Jsi naše hrdinka!',
            'page' => $page,
        ]);
    }

    public function medailonky()
    {
        $people = Person::getGroupedBySection($this->db);

        $photo=[];
        $dir = __DIR__ . '/../../public/img/';   // cesta ke složce
        $allowedPattern = '/^(\d+)\.(jpg|png)$/i';
        $files = scandir($dir);
        foreach ($files as $file) {
            if (preg_match($allowedPattern, $file, $matches)) {
                $photo[(int)$matches[1]]=$file;        
            }
        }
        //$photo[1]="logo.png";


        echo $this->twig->render('pages/medailonky.twig', [
            'user' => $this->getCurrentUser(),
            'photo' => $photo,
            'active_page' => 'medailonky',
            'people' => $people,
        ]);
    }
}
