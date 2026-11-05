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

    /**
     * Registrations are open once REGISTRATION_START has passed; admins may register any time.
     */
    protected function isRegistrationOpen(?array $user): bool
    {
        if (!$user) {
            return false;
        }
        if (($user['role'] ?? '') === 'admin') {
            return true;
        }
        $start = $_ENV['REGISTRATION_START'] ?? null;
        return !$start || strtotime($start) <= time();
    }

    /**
     * True when the user still has an unanswered required question.
     */
    protected function hasBlockingQuestions(int $userId, ?array $questions = null): bool
    {
        $questions ??= \App\Models\UserQuestion::getWithAnswersForUser($this->db, $userId);
        foreach ($questions as $q) {
            if ($q['is_required'] && ($q['value'] === null || $q['value'] === '')) {
                return true;
            }
        }
        return false;
    }

    /**
     * Flag that the cart content changed – the next page load animates the cart icon.
     */
    protected function markCartChanged(): void
    {
        $_SESSION['cart_changed'] = true;
    }

    /**
     * Where to redirect after a POST action: back to the cart when the form says so, else the default.
     */
    protected function backUrl(string $default = '/dashboard'): string
    {
        return ($_POST['_back'] ?? '') === 'cart' ? '/cart' : $default;
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
