<?php
namespace App\Controllers;

use App\Models\Workshop;

class WorkshopController extends BaseController
{
    public function index()
    {
        // Fetch workshops with enrollment counts
        $workshops = Workshop::getAll($this->db);

        echo $this->twig->render('pages/workshops.twig', [
            'user' => $this->getCurrentUser(),
            'active_page' => 'workshops',
            'workshops' => $workshops
        ]);
    }

    public function register()
    {
        $this->requireAuth();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $workshopId = $_POST['workshop_id'] ?? null;
            $companionProgram = isset($_POST['companion_program']) ? 1 : 0;

            if (!$workshopId) {
                $_SESSION['error'] = 'Vyberte prosím workshop.';
                header('Location: /workshop/register');
                exit;
            }

            // Check if workshop is full
            if (Workshop::isFull($this->db, $workshopId)) {
                $_SESSION['error'] = 'Vybraný workshop je již plný.';
                header('Location: /workshop/register');
                exit;
            }

            $user = $this->getCurrentUser();

            // Check if user already registered for this workshop
            $stmt = $this->db->prepare("
                SELECT id FROM registrations
                WHERE user_id = ? AND workshop_id = ?
            ");
            $stmt->execute([$user['id'], $workshopId]);

            if ($stmt->fetch()) {
                $_SESSION['error'] = 'Již jste registrován na tento workshop.';
                header('Location: /workshop/register');
                exit;
            }

            // Get workshop price
            $workshop = Workshop::findById($this->db, $workshopId);

            // Create registration
            $stmt = $this->db->prepare("
                INSERT INTO registrations (user_id, workshop_id, companion_program, payment_status)
                VALUES (?, ?, ?, 'pending')
            ");
            $stmt->execute([$user['id'], $workshopId, $companionProgram]);

            $_SESSION['success'] = 'Registrace byla úspěšná. Prosím dokončete platbu.';
            header('Location: /payment');
            exit;
        }

        // Get available workshops for dropdown
        $workshops = Workshop::getAvailableForRegistration($this->db);

        echo $this->twig->render('pages/registration.twig', [
            'user' => $this->getCurrentUser(),
            'active_page' => 'registration',
            'workshops' => $workshops,
            'session' => $this->getSessionMessages()
        ]);
    }
}

