<?php
namespace App\Controllers;

use App\Models\Workshop;

class DashboardController extends BaseController
{
    public function index()
    {
        $this->requireAuth();

        $user = $this->getCurrentUser();

        $stmt = $this->db->prepare("
            SELECT r.id, r.workshop_id, r.payment_status, r.created_at,
                   w.name AS workshop_name, w.date, w.time, w.price, w.timeslot
            FROM registrations r
            LEFT JOIN workshops w ON r.workshop_id = w.id
            WHERE r.user_id = ?
              AND r.payment_status != 'cancelled'
            ORDER BY r.created_at DESC
        ");
        $stmt->execute([$user['id']]);
        $registrations = $stmt->fetchAll();

        $session = $this->getSessionMessages();

        echo $this->twig->render('pages/dashboard.twig', [
            'user'          => $user,
            'active_page'   => 'dashboard',
            'registrations' => $registrations,
            'error'         => $session['error'],
            'success'       => $session['success'],
            'csrf'          => csrf_token('dashboard'),
        ]);
    }

    public function cancelRegistration()
    {
        $this->requireAuth();

        if (!csrf_validate('dashboard', $_POST['_csrf'] ?? null)) {
            $_SESSION['error'] = 'Neplatný bezpečnostní token.';
            header('Location: /dashboard');
            exit;
        }

        $registrationId = (int)($_POST['registration_id'] ?? 0);
        $user = $this->getCurrentUser();

        // Load the registration and verify it belongs to this user
        $stmt = $this->db->prepare("
            SELECT r.*, w.registered AS workshop_registered
            FROM registrations r
            LEFT JOIN workshops w ON r.workshop_id = w.id
            WHERE r.id = ? AND r.user_id = ?
        ");
        $stmt->execute([$registrationId, $user['id']]);
        $registration = $stmt->fetch();

        if (!$registration) {
            $_SESSION['error'] = 'Registrace nebyla nalezena.';
            header('Location: /dashboard');
            exit;
        }

        if ($registration['payment_status'] !== 'pending') {
            $_SESSION['error'] = 'Lze zrušit pouze registrace čekající na platbu.';
            header('Location: /dashboard');
            exit;
        }

        // Cancel the registration
        $stmt = $this->db->prepare("
            UPDATE registrations SET payment_status = 'cancelled' WHERE id = ?
        ");
        $stmt->execute([$registrationId]);

        // Decrement the workshop's registered counter
        Workshop::decrementRegistered($this->db, $registration['workshop_id']);

        $_SESSION['success'] = 'Registrace byla úspěšně zrušena.';
        header('Location: /dashboard');
        exit;
    }
}
