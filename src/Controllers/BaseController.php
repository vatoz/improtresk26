<?php
namespace App\Controllers;

use Twig\Environment;
use PDO;

class BaseController
{
    protected $twig;
    protected $db;

    public function __construct(Environment $twig, PDO $db)
    {
        $this->twig = $twig;
        $this->db = $db;
    }

    protected function requireAuth()
    {
        if (!isset($_SESSION['user'])) {
            header('Location: /login');
            exit;
        }
    }

    protected function requireAdmin()
    {
        $this->requireAuth();
        if ($_SESSION['user']['role'] !== 'admin') {
            header('HTTP/1.0 403 Forbidden');
            echo "Access denied.";
            exit;
        }
    }

    protected function getCurrentUser()
    {
        return $_SESSION['user'] ?? null;
    }

    protected function getSessionMessages(): array
    {
        $messages = [
            'success' => $_SESSION['success'] ?? null,
            'error' => $_SESSION['error'] ?? null,
            'info' => $_SESSION['info'] ?? null,
        ];

        // Clear messages after retrieving
        unset($_SESSION['success'], $_SESSION['error'], $_SESSION['info']);

        return $messages;
    }
}
