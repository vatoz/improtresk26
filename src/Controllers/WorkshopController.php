<?php
namespace App\Controllers;

use App\Models\Workshop;
use App\Models\Timeslot;
use App\Models\Person;
use App\Models\StaticBlock;

class WorkshopController extends BaseController
{
    /**
     * GET /workshops – all workshops on one page, grouped by timeslot (workshop length).
     * Logged-in users can register/unregister directly on the page.
     */
    public function index()
    {
        $user   = $this->getCurrentUser();
        $isHero = (int)($user['hero'] ?? 0) > 0;

        $timeslots = [];
        foreach (Timeslot::getAll($this->db) as $ts) {
            $timeslots[$ts['code']] = $ts;
        }

        $workshops = Workshop::getAll($this->db);

        $registrationOpen  = $this->isRegistrationOpen($user);

        // Per-user registration state
        $registrationByWorkshop = [];
        $queueByWorkshop        = [];
        if ($user) {
            foreach (Workshop::getUserRegistrations($this->db, $user['id']) as $r) {
                $registrationByWorkshop[$r['workshop_id']] = $r['payment_status'];
            }
            foreach (Workshop::getQueuePositions($this->db, $user['id']) as $qp) {
                $queueByWorkshop[$qp['workshop_id']] = $qp['queue_position'];
            }
        }

        foreach ($workshops as &$w) {
            $w['ts']                = $timeslots[$w['timeslot'] ?? ''] ?? null;
            $w['alreadyRegistered'] = $registrationByWorkshop[$w['id']] ?? null;
            if (isset($queueByWorkshop[$w['id']])) {
                $w['queue_position'] = $queueByWorkshop[$w['id']];
            }
            // Registration allowed only while paid seats and the waitlist have room (heroes bypass this)
            $w['can_register'] = !$w['cancelled'] && $w['timeslot'] && ($isHero || (
                (int)$w['paid'] < (int)$w['capacity']
                && (int)$w['enrolled_count'] < (int)$w['capacity']
            ));
        }
        unset($w);

        // Group by timeslot (= workshop length), in timeslot order; workshops without a timeslot go last
        $groups = [];
        foreach ($timeslots as $code => $ts) {
            $groups[$code] = ['ts' => $ts, 'workshops' => []];
        }
        $groups[''] = ['ts' => null, 'workshops' => []];
        foreach ($workshops as $w) {
            $groups[isset($timeslots[$w['timeslot'] ?? '']) ? $w['timeslot'] : '']['workshops'][] = $w;
        }
        $groups = array_values(array_filter($groups, fn($g) => !empty($g['workshops'])));

        $session = $this->getSessionMessages();

        echo $this->twig->render('pages/workshops.twig', [
            'user'               => $user,
            'active_page'        => 'workshops',
            'groups'             => $groups,
            'people_by_workshop' => Person::getGroupedByWorkshop($this->db),
            'registration_open'  => $registrationOpen,
            'is_hero'            => $isHero,
            'csrf'               => $user ? csrf_token('workshops') : null,
            'error'              => $session['error'],
            'success'            => $session['success'],
            'static'             => (new StaticBlock)->getByPrefix($this->db, 'wizard'),
        ]);
    }

    /**
     * POST /workshops/register – register (workshop_id) or unregister (cancel_workshop_id).
     * Responds with JSON when _ajax is set, otherwise redirects back to /workshops.
     */
    public function register()
    {
        $this->requireAuth();
        $user   = $this->getCurrentUser();
        $isHero = (int)($user['hero'] ?? 0) > 0;
        $isAjax = !empty($_POST['_ajax']);

        $selectedWorkshopId = (int)($_POST['workshop_id'] ?? 0);
        $canceledWorkshopId = (int)($_POST['cancel_workshop_id'] ?? 0);

        if (!csrf_validate('workshops', $_POST['_csrf'] ?? null)) {
            $this->respond($isAjax, 'Neplatný bezpečnostní token. Zkuste to znovu.');
        }
        if (!$this->isRegistrationOpen($user)) {
            $this->respond($isAjax, 'Přihlašování na workshopy ještě nebylo spuštěno.');
        }

        if ($selectedWorkshopId) {
            $ws = Workshop::findById($this->db, $selectedWorkshopId);
            if ($ws && $ws['cancelled']) {
                $_SESSION['error'] = 'Tento workshop byl zrušen.';
            } elseif ($ws && !$ws['timeslot']) {
                $_SESSION['error'] = 'Na tento workshop se nelze přihlásit.';
            } elseif (!$isHero && $ws && (int)$ws['paid'] >= (int)$ws['capacity']) {
                $_SESSION['error'] = 'Workshop je plně obsazen.';
            } elseif (!$isHero && $ws && (int)$ws['enrolled_count'] >= (int)$ws['capacity']) {
                $_SESSION['error'] = 'Čekací listina workshopu je plná.';
            } elseif (Workshop::register($this->db, $user['id'], $selectedWorkshopId)) {
                $this->markCartChanged();
            }
        } elseif ($canceledWorkshopId) {
            if (Workshop::unregister($this->db, $user['id'], $canceledWorkshopId)) {
                $this->markCartChanged();
            }
        }

        $workshopId = $selectedWorkshopId ?: $canceledWorkshopId;

        if (!$isAjax) {
            header('Location: /workshops' . ($workshopId ? '#workshop-' . $workshopId : ''));
            exit;
        }

        $errorMsg = $_SESSION['error'] ?? null;
        // AJAX animates the cart icon itself – don't replay the animation on the next page load
        unset($_SESSION['error'], $_SESSION['success'], $_SESSION['cart_changed']);
        if ($errorMsg) {
            $this->respond(true, $errorMsg);
        }

        $ws = Workshop::findById($this->db, $workshopId);

        $queuePosition = null;
        if ($selectedWorkshopId) {
            foreach (Workshop::getQueuePositions($this->db, $user['id']) as $qp) {
                if ((int)$qp['workshop_id'] === $workshopId) {
                    $queuePosition = (int)$qp['queue_position'];
                    break;
                }
            }
        }

        header('Content-Type: application/json');
        echo json_encode([
            'ok'             => true,
            'action'         => $selectedWorkshopId ? 'registered' : 'cancelled',
            'workshop_id'    => $workshopId,
            'queue_position' => $queuePosition,
            'enrolled_count' => (int)($ws['enrolled_count'] ?? 0),
            'paid'           => (int)($ws['paid'] ?? 0),
            'capacity'       => (int)($ws['capacity'] ?? 0),
            'cart_count'     => cart_count($this->db, (int)$user['id']),
        ]);
        exit;
    }

    /**
     * Send an error either as JSON (AJAX) or as a flash message + redirect.
     */
    private function respond(bool $isAjax, string $error): void
    {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => $error]);
        } else {
            $_SESSION['error'] = $error;
            header('Location: /workshops');
        }
        exit;
    }
}
