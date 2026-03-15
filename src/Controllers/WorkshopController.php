<?php
namespace App\Controllers;

use App\Models\Workshop;
use App\Models\Timeslot;

class WorkshopController extends BaseController
{
    public function index()
    {
        $workshops = Workshop::getAll($this->db);
        $timeslots = Timeslot::getAll($this->db);

        $workshopsByTimeslot = [];
        $workshopsNoTimeslot = [];
        foreach ($workshops as $workshop) {
            if ($workshop['timeslot']) {
                $workshopsByTimeslot[$workshop['timeslot']][] = $workshop;
            } else {
                $workshopsNoTimeslot[] = $workshop;
            }
        }

        echo $this->twig->render('pages/workshops.twig', [
            'user'                  => $this->getCurrentUser(),
            'active_page'           => 'workshops',
            'timeslots'             => $timeslots,
            'workshops_by_timeslot' => $workshopsByTimeslot,
            'workshops_no_timeslot' => $workshopsNoTimeslot,
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


            $user = $this->getCurrentUser();

            // Check if user already registered for this workshop
            $stmt = $this->db->prepare("
                SELECT id FROM registrations
                WHERE user_id = ? AND workshop_id = ? and payment_status <> 'cancelled'
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

    public function chooseWorkshops()
    {
        $this->requireAuth();
        $user = $this->getCurrentUser();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!csrf_validate('choose_workshops', $_POST['_csrf'] ?? null)) {
                $_SESSION['error'] = 'Neplatný bezpečnostní token. Zkuste to znovu.';
                header('Location: /choose-workshops');
                exit;
            }

            $workshopId = (int)($_POST['workshop_id'] ?? 0);

            if (!$workshopId) {
                $_SESSION['error'] = 'Neplatný workshop.';
                header('Location: /choose-workshops');
                exit;
            }

            $workshop = Workshop::findById($this->db, $workshopId);
            if (!$workshop || !$workshop['is_active']) {
                $_SESSION['error'] = 'Workshop nebyl nalezen.';
                header('Location: /choose-workshops');
                exit;
            }

            // Duplicate registration check
            $stmt = $this->db->prepare("
                SELECT id FROM registrations WHERE user_id = ? AND workshop_id = ? and payment_status <> 'cancelled'
            ");
            $stmt->execute([$user['id'], $workshopId]);
            if ($stmt->fetch()) {
                $_SESSION['error'] = 'Na tento workshop jste již registrován/a.';
                header('Location: /choose-workshops');
                exit;
            }

            // Create registration
            $stmt = $this->db->prepare("
                INSERT INTO registrations (user_id, workshop_id, payment_status) VALUES (?, ?, 'pending')
            ");
            $stmt->execute([$user['id'], $workshopId]);

            $_SESSION['success'] = 'Byli jste úspěšně přihlášeni na workshop.';
            header('Location: /choose-workshops');
            exit;
        }

        // Build data for the view
        $timeslots = Timeslot::getAll($this->db);
        $workshopsByTimeslot = Workshop::getAvailableGroupedByTimeslot($this->db);

        // IDs of workshops the user is already registered for
        $stmt = $this->db->prepare("
            SELECT workshop_id FROM registrations
            WHERE user_id = ? AND payment_status != 'cancelled'
        ");
        $stmt->execute([$user['id']]);
        $userRegisteredIds = array_column($stmt->fetchAll(), 'workshop_id');

        $session = $this->getSessionMessages();

        echo $this->twig->render('pages/choose-workshops.twig', [
            'user'                  => $user,
            'active_page'           => 'choose_workshops',
            'timeslots'             => $timeslots,
            'workshops_by_timeslot' => $workshopsByTimeslot,
            'user_registered_ids'   => $userRegisteredIds,
            'csrf'                  => csrf_token('choose_workshops'),
            'error'                 => $session['error'],
            'success'               => $session['success'],
        ]);
    }
}

