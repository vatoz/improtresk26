<?php
namespace App\Controllers;

use App\Models\Workshop;
use App\Models\Timeslot;
use App\Models\User;
use vplacek\QRPlatba\QRPlatba;

class WizardController extends BaseController
{
    /**
     * Precompute the ordered list of wizard pages:
     * all timeslots → tickets → merch (only if any active) → payment
     */
    private function getWizardPages(): array
    {
        $pages = [];
        $id=1;
        foreach (Timeslot::getAll($this->db) as $ts) {
            $pages[] = [
                'num'   => $id,
                'type'  => 'workshops',
                'slot'  => $ts['code'],
                'url'   => '/wizard/workshops?slot=' . urlencode($ts['code']),
                'label' => $ts['name'],
            ];
            $id++;
        }

        $pages[] = ['num' => $id,'type' => 'tickets', 'url' => '/wizard/tickets', 'label' => 'Vstupenky'];
        $id++;

        $stmt = $this->db->query("SELECT COUNT(*) AS cnt FROM merch WHERE is_active = 1");
        if ((int)$stmt->fetch()['cnt'] > 0) {
            $pages[] = ['num' => $id,'type' => 'merch', 'url' => '/wizard/merch', 'label' => 'Merch'];
            $id++;
        }

        $pages[] = ['num' => $id,'type' => 'payment', 'url' => '/wizard/payment', 'label' => 'Platba'];
        $id++;

        return $pages;
    }

    /**
     * Return prev/next URLs for the given position within wizard pages.
     */
    private function getPageNavigation(array $pages, string $type, ?string $slot = null): array
    {
        foreach ($pages as $i => $page) {
            $match = $page['type'] === $type &&
                     ($type !== 'workshops' || ($page['slot'] ?? null) === $slot);
            if ($match) {
                return [
                    'prev' => $i > 0 ? $pages[$i - 1]['url'] : null,
                    'cur'  => $pages[$i]['num'],
                    'next' => isset($pages[$i + 1]) ? $pages[$i + 1]['url'] : null,
                ];
            }
        }
        return ['prev' => null, 'next' => null];
    }

    /**
     * Step 1 – Workshop selection, one timeslot at a time.
     *
     * GET  /wizard/workshops          → redirect to first timeslot
     * GET  /wizard/workshops?slot=X   → show timeslot X
     * POST /wizard/workshops          → register/unregister workshop, redirect back
     */
    public function workshops()
    {
        $this->requireAuth();
        $user = $this->getCurrentUser();

        $wizardPages   = $this->getWizardPages();
        $workshopPages = array_values(array_filter($wizardPages, fn($p) => $p['type'] === 'workshops'));

        $requestedSlot = $_GET['slot'] ?? ($_POST['slot'] ?? null);

        $isHero = (int)($user['hero'] ?? 0) > 0;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $isAjax = !empty($_POST['_ajax']);

            if (!csrf_validate('wizard', $_POST['_csrf'] ?? null)) {
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode(['ok' => false, 'error' => 'Neplatný bezpečnostní token.']);
                    exit;
                }
                $_SESSION['error'] = 'Neplatný bezpečnostní token. Zkuste to znovu.';
                $redir = '/wizard/workshops' . ($requestedSlot ? '?slot=' . urlencode($requestedSlot) : '');
                header('Location: ' . $redir);
                exit;
            }

            $selectedWorkshopId = intval($_POST['workshop_id'] ?? 0);
            if ($selectedWorkshopId) {
                $ws = Workshop::findById($this->db, $selectedWorkshopId);
                $blocked = false;

                if (!$isHero && $ws) {
                    if ((int)$ws['paid'] >= (int)$ws['capacity']) {
                        $_SESSION['error'] = 'Workshop je plně obsazen.';
                        $blocked = true;
                    } elseif ((int)$ws['enrolled_count'] >= (int)$ws['capacity']) {
                        $_SESSION['error'] = 'Čekací listina workshopu je plná.';
                        $blocked = true;
                    }
                }

                if (!$blocked) {
                    $w = new Workshop();
                    $w->register($this->db, $user['id'], $selectedWorkshopId);
                }
            }

            $canceledWorkshopId = intval($_POST['cancel_workshop_id'] ?? 0);
            if ($canceledWorkshopId) {
                $w = new Workshop();
                $w->unregister($this->db, $user['id'], $canceledWorkshopId);
            }

            if ($isAjax) {
                $errorMsg = $_SESSION['error'] ?? null;
                unset($_SESSION['error'], $_SESSION['success']);

                if ($errorMsg) {
                    header('Content-Type: application/json');
                    echo json_encode(['ok' => false, 'error' => $errorMsg]);
                    exit;
                }

                $workshopId = $selectedWorkshopId ?: $canceledWorkshopId;
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
                    'capacity'       => (int)($ws['capacity'] ?? 0),
                ]);
                exit;
            }

            $redir = '/wizard/workshops' . ($requestedSlot ? '?slot=' . urlencode($requestedSlot) : '');
            header('Location: ' . $redir);
            exit;
        }

        // No slot → redirect to first timeslot
        if (!$requestedSlot) {
            if (!empty($workshopPages)) {
                header('Location: ' . $workshopPages[0]['url']);
            } else {
                header('Location: /wizard/tickets');
            }
            exit;
        }

        // Validate the requested slot exists
        $allTimeslots    = Timeslot::getAll($this->db);
        $currentTimeslot = null;
        foreach ($allTimeslots as $ts) {
            if ($ts['code'] === $requestedSlot) {
                $currentTimeslot = $ts;
                break;
            }
        }
        if (!$currentTimeslot) {
            header('Location: /wizard/tickets');
            exit;
        }

        // Workshops for this timeslot with registration state
        $workshopsByTimeslot = Workshop::getAvailableGroupedByTimeslot($this->db);
        $currentWorkshops    = $workshopsByTimeslot[$currentTimeslot['code']] ?? [];
        $userWorkshops       = Workshop::getUserRegistrations($this->db, $user['id']);
        $queuePositions      = Workshop::getQueuePositions($this->db, $user['id']);

        // For non-heroes hide workshops where all paid seats are taken
        if (!$isHero) {
            $currentWorkshops = array_values(array_filter(
                $currentWorkshops,
                fn($w) => (int)$w['paid'] < (int)$w['capacity']
            ));
        }

        // Build workshop_id → queue_position lookup
        $queueByWorkshop = [];
        foreach ($queuePositions as $qp) {
            $queueByWorkshop[$qp['workshop_id']] = $qp['queue_position'];
        }

        foreach ($currentWorkshops as $idx => $workshop) {
            foreach ($userWorkshops as $uW) {
                if ($uW['workshop_id'] == $currentWorkshops[$idx]['id']) {
                    $currentWorkshops[$idx]['alreadyRegistered'] = $uW['payment_status'];
                    if (isset($queueByWorkshop[$uW['workshop_id']])) {
                        $currentWorkshops[$idx]['queue_position'] = $queueByWorkshop[$uW['workshop_id']];
                    }
                }
            }
            // Registration allowed only while waitlist has room (heroes bypass this)
            $currentWorkshops[$idx]['can_register'] = $isHero
                || (int)$currentWorkshops[$idx]['enrolled_count'] < (int)$currentWorkshops[$idx]['capacity'];
        }

        // Position within timeslots for sub-progress display
        $totalTimeslots   = count($workshopPages);
        $currentSlotIndex = 0;
        foreach ($workshopPages as $i => $p) {
            if ($p['slot'] === $requestedSlot) {
                $currentSlotIndex = $i;
                break;
            }
        }

        $nav     = $this->getPageNavigation($wizardPages, 'workshops', $requestedSlot);
        $session = $this->getSessionMessages();

        echo $this->twig->render('pages/wizard-workshops.twig', [
            'user'            => $user,
            'active_page'     => 'wizard',
            'timeslot'        => $currentTimeslot,
            'workshops'       => $currentWorkshops,
            'total_timeslots' => $totalTimeslots,
            'done_timeslots'  => $currentSlotIndex,
            'all_timeslots'   => $workshopPages,
            'csrf'            => csrf_token('wizard'),
            'error'           => $session['error'],
            'success'         => $session['success'],
            'wizard_pages'    => $wizardPages,
            'wizard_step'     => 'workshops',
            'wizard_slot'     => $requestedSlot,
            'prev_url'        => $nav['prev'],
            'next_url'        => $nav['next'],
            'cur'             => $nav['cur'],
            'is_hero'         => $isHero,
        ]);
    }

    /**
     * Step 2 – Ticket purchase.
     * Each ticket is added/removed instantly via individual POST actions.
     */
    public function tickets()
    {
        $this->requireAuth();
        $user = $this->getCurrentUser();

        $wizardPages = $this->getWizardPages();
        $nav         = $this->getPageNavigation($wizardPages, 'tickets');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!csrf_validate('wizard', $_POST['_csrf'] ?? null)) {
                $_SESSION['error'] = 'Neplatný bezpečnostní token. Zkuste to znovu.';
                header('Location: /wizard/tickets');
                exit;
            }

            $ticketId = intval($_POST['ticket_id'] ?? 0);
            if ($ticketId) {
                $stmt = $this->db->prepare("SELECT id FROM tickets WHERE id = ? AND is_active = 1");
                $stmt->execute([$ticketId]);
                if ($stmt->fetch()) {
                    $stmt = $this->db->prepare("
                        SELECT id FROM purchases
                        WHERE user_id = ? AND item_type = 'ticket' AND item_id = ? AND payment_status != 'cancelled'
                    ");
                    $stmt->execute([$user['id'], $ticketId]);
                    if (!$stmt->fetch()) {
                        $this->db->prepare("
                            INSERT INTO purchases (user_id, item_type, item_id) VALUES (?, 'ticket', ?)
                        ")->execute([$user['id'], $ticketId]);
                    }
                }
            }

            $cancelTicketId = intval($_POST['cancel_ticket_id'] ?? 0);
            if ($cancelTicketId) {
                $this->db->prepare("
                    UPDATE purchases SET payment_status = 'cancelled'
                    WHERE user_id = ? AND item_type = 'ticket' AND item_id = ? AND payment_status NOT IN ('paid', 'cancelled')
                ")->execute([$user['id'], $cancelTicketId]);
            }

            header('Location: /wizard/tickets');
            exit;
        }

        $stmt = $this->db->query("SELECT * FROM tickets WHERE is_active = 1 ORDER BY date, time");
        $tickets = $stmt->fetchAll();

        $stmt = $this->db->prepare("
            SELECT item_id FROM purchases
            WHERE user_id = ? AND item_type = 'ticket' AND payment_status != 'cancelled'
        ");
        $stmt->execute([$user['id']]);
        $userPurchasedIds = array_column($stmt->fetchAll(), 'item_id');

        $session = $this->getSessionMessages();

        echo $this->twig->render('pages/wizard-tickets.twig', [
            'user'               => $user,
            'active_page'        => 'wizard',
            'tickets'            => $tickets,
            'user_purchased_ids' => $userPurchasedIds,
            'csrf'               => csrf_token('wizard'),
            'error'              => $session['error'],
            'success'            => $session['success'],
            'wizard_pages'       => $wizardPages,
            'wizard_step'        => 'tickets',
            'prev_url'           => $nav['prev'],
            'next_url'           => $nav['next'],
             'cur'        => $nav['cur'],
        ]);
    }

    /**
     * Step 3 – Merch selection.
     */
    public function merch()
    {
        $this->requireAuth();
        $user = $this->getCurrentUser();

        $wizardPages = $this->getWizardPages();
        $nav         = $this->getPageNavigation($wizardPages, 'merch');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!csrf_validate('wizard', $_POST['_csrf'] ?? null)) {
                $_SESSION['error'] = 'Neplatný bezpečnostní token. Zkuste to znovu.';
                header('Location: /wizard/merch');
                exit;
            }

            foreach ($_POST['merch_ids'] ?? [] as $merchId) {
                $merchId = (int)$merchId;
                if (!$merchId) continue;

                $stmt = $this->db->prepare("SELECT id FROM merch WHERE id = ? AND is_active = 1");
                $stmt->execute([$merchId]);
                if (!$stmt->fetch()) continue;

                $stmt = $this->db->prepare("
                    SELECT id FROM purchases
                    WHERE user_id = ? AND item_type = 'merch' AND item_id = ? AND payment_status != 'cancelled'
                ");
                $stmt->execute([$user['id'], $merchId]);
                $existing = $stmt->fetch();

                if ($existing) {
                    $this->db->prepare("UPDATE purchases SET quantity = quantity + 1 WHERE id = ?")
                        ->execute([$existing['id']]);
                } else {
                    $this->db->prepare("
                        INSERT INTO purchases (user_id, item_type, item_id) VALUES (?, 'merch', ?)
                    ")->execute([$user['id'], $merchId]);
                }
            }

            header('Location: ' . ($nav['next'] ?? '/wizard/payment'));
            exit;
        }

        $stmt = $this->db->query("SELECT * FROM merch WHERE is_active = 1 ORDER BY name");
        $items = $stmt->fetchAll();

        $stmt = $this->db->prepare("
            SELECT item_id FROM purchases
            WHERE user_id = ? AND item_type = 'merch' AND payment_status != 'cancelled' AND payment_status != 'paid'
        ");
        $stmt->execute([$user['id']]);
        $userPurchasedIds = array_column($stmt->fetchAll(), 'item_id');

        $session = $this->getSessionMessages();

        echo $this->twig->render('pages/wizard-merch.twig', [
            'user'               => $user,
            'active_page'        => 'wizard',
            'items'              => $items,
            'user_purchased_ids' => $userPurchasedIds,
            'csrf'               => csrf_token('wizard'),
            'error'              => $session['error'],
            'success'            => $session['success'],
            'wizard_pages'       => $wizardPages,
            'wizard_step'        => 'merch',
            'prev_url'           => $nav['prev'],
            'next_url'           => $nav['next'],
             'cur'        => $nav['cur'],
        ]);
    }

    /**
     * Step 4 – Payment summary.
     */
    public function payment()
    {
        $this->requireAuth();
        $user = $this->getCurrentUser();

        $wizardPages = $this->getWizardPages();
        $nav         = $this->getPageNavigation($wizardPages, 'payment');

        $stmt = $this->db->prepare("
            SELECT r.*, w.name AS workshop_name, w.price, w.capacity,
                   ts.name AS timeslot_name, ts.code AS timeslot_code
            FROM registrations r
            LEFT JOIN workshops w ON r.workshop_id = w.id
            LEFT JOIN timeslots ts ON ts.code = w.timeslot
            WHERE r.user_id = ? AND r.payment_status IN ('pending', 'paid')
            ORDER BY COALESCE(ts.order, 2147483647), r.created_at
        ");
        $stmt->execute([$user['id']]);
        $registrations = $stmt->fetchAll();

        $stmt = $this->db->prepare("
            SELECT p.*,
                   COALESCE(t.name, m.name) AS item_name,
                   COALESCE(t.price, m.price) AS item_price
            FROM purchases p
            LEFT JOIN tickets t ON t.id = p.item_id AND p.item_type = 'ticket'
            LEFT JOIN merch m ON m.id = p.item_id AND p.item_type = 'merch'
            WHERE p.user_id = ? AND p.payment_status IN ('pending','paid')
            ORDER BY p.item_type, p.created_at
        ");
        $stmt->execute([$user['id']]);
        $purchases = $stmt->fetchAll();

        if (empty($registrations) && empty($purchases)) {
            header('Location: /dashboard');
            exit;
        }

        // Build workshop_id → queue_position lookup
        $queuePositions  = Workshop::getQueuePositions($this->db, $user['id']);
        $queueByWorkshop = [];
        foreach ($queuePositions as $qp) {
            $queueByWorkshop[$qp['workshop_id']] = $qp['queue_position'];
        }

        if(!isset($user['hero'])){
            $user['hero']=0;
        }

        // Calculate total; overlapping timeslots and unlikely spots are priced at 0
        $timeslots_taken = [];
        $total = 0;
        foreach ($registrations as $i => $r) {
            // Queue position beyond capacity → user likely won't get in
            $queuePos = $queueByWorkshop[$r['workshop_id']] ?? null;
            if($registrations[$i]['payment_status'] == "paid"){
                $registrations[$i]['price']         = 0;
            }elseif ($queuePos !== null) {
                $registrations[$i]['queue_position'] = $queuePos;
                if ($queuePos >= (int)$r['capacity']                 
                && ( $user['hero'] <1  ) ){
                    $registrations[$i]['gray_price']= $registrations[$i]['price'];
                    $registrations[$i]['price']         = 0;
                    $registrations[$i]['likely_no_spot'] = true;
                }
            }

            foreach ($timeslots_taken as $slot) {
                if (Workshop::timeslotsOverlap($slot, $r['timeslot_code'])) {
                    $registrations[$i]['payment_status'] = 'timeblock';
                    $registrations[$i]['gray_price']= $registrations[$i]['price'];
                    $registrations[$i]['price']          = 0;
                }
            }
            $total += (float)$registrations[$i]['price'];
            if($registrations[$i]['price']>0 ||  $registrations[$i]['payment_status'] == "paid"){
                $timeslots_taken[] = $r['timeslot_code'];
            }
            
        }

        foreach ($purchases as $i   => $p) {
            if($p['payment_status']!= 'paid'){
                $total += (float)$p['item_price'] * (int)$p['quantity'];
            }else{
                $purchases[$i]['item_price']=0;

            }
        }

        $paymentConfig  = payment_config();
        $variableSymbol = $user['id'];

        $qrPlatba = new QRPlatba();
        $qrPlatba->setIban($paymentConfig['iban'])
            ->setAmount($total)
            ->setScale(5)
            ->setVariableSymbol($variableSymbol);

        $paymentDetails = [
            'account_number'  => $paymentConfig['iban'],
            'readable'  => $paymentConfig['readable'],
            'variable_symbol' => $variableSymbol,
            'amount'          => $total,
            'currency'        => $paymentConfig['currency'],
            'message'         => $paymentConfig['message'],
        ];

            $uu=new User();
            $uu->update($this->db,$user['id'],['awaiting_payment'=>$total]);

        echo $this->twig->render('pages/wizard-payment.twig', [
            'user'          => $user,
            'active_page'   => 'wizard',
            'registrations' => $registrations,
            'purchases'     => $purchases,
            'total'         => $total,
            'payment'       => $paymentDetails,
            'qr'            => 'data:image/png;base64,' . base64_encode($qrPlatba->generateQr()),
            'wizard_pages'  => $wizardPages,
            'wizard_step'   => 'payment',
            'prev_url'      => $nav['prev'],
            'next_url'      => $nav['next'],
             'cur'        => $nav['cur'],
        ]);
    }
}
