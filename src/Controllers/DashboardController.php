<?php
namespace App\Controllers;

use App\Models\UserQuestion;
use App\Models\UserAnswer;
use App\Models\StaticBlock;
use App\Models\User;
use App\Models\Workshop;
use vplacek\QRPlatba\QRPlatba;

class DashboardController extends BaseController
{
    public function index()
    {
        $this->requireAuth();

        $user = $this->getCurrentUser();

        $stmt = $this->db->prepare("
            SELECT r.id, r.workshop_id, r.payment_status, r.priority, r.created_at,
                   w.name AS workshop_name, ts.start_datetime, ts.end_datetime, w.price, w.timeslot,
                   ts.name AS timeslot_name, ts.order AS timeslot_order
            FROM registrations r
            LEFT JOIN workshops w ON r.workshop_id = w.id
            LEFT JOIN timeslots ts ON ts.code = w.timeslot
            WHERE r.user_id = ?
            ORDER BY
                COALESCE(ts.order, 2147483647) ASC,
                COALESCE(r.priority, 2147483647) ASC,
                r.created_at ASC
        ");
        $stmt->execute([$user['id']]);
        $registrations = $stmt->fetchAll();

        $stmt = $this->db->prepare("
            SELECT p.id, p.item_type, p.item_id, p.quantity, p.payment_status, p.created_at,
                   t.name AS item_name, t.price, t.date, t.time
            FROM purchases p
            JOIN tickets t ON t.id = p.item_id
            WHERE p.user_id = ? AND p.item_type = 'ticket'
            UNION ALL
            SELECT p.id, p.item_type, p.item_id, p.quantity, p.payment_status, p.created_at,
                   m.name AS item_name, m.price, NULL AS date, NULL AS time
            FROM purchases p
            JOIN merch m ON m.id = p.item_id
            WHERE p.user_id = ? AND p.item_type = 'merch'
            ORDER BY created_at DESC
        ");
        $stmt->execute([$user['id'], $user['id']]);
        $purchases = $stmt->fetchAll();

        $questions = UserQuestion::getWithAnswersForUser($this->db, $user['id']);

        $questionsBlocking = $this->hasBlockingQuestions($user['id'], $questions);

        $session = $this->getSessionMessages();

        $registrationStart = $_ENV['REGISTRATION_START'] ?? null;
        echo $this->twig->render('pages/dashboard.twig', [
            'user'               => $user,
            'active_page'        => 'dashboard',
            'registration_start' => $registrationStart,
            'registrations'      => $registrations,
            'purchases'          => $purchases,
            'questions'          => $questions,
            'questions_blocking' => $questionsBlocking,
            'error'              => $session['error'],
            'success'            => $session['success'],
            'csrf'               => csrf_token('dashboard'),
            'static' => (new StaticBlock)->getByPrefix($this->db,'dashboard')
        ]);
    }

    public function cancelRegistration()
    {
        $this->requireAuth();

        if (!csrf_validate('dashboard', $_POST['_csrf'] ?? null)) {
            $_SESSION['error'] = 'Neplatný bezpečnostní token.';
            header('Location: ' . $this->backUrl());
            exit;
        }

        $registrationId = (int)($_POST['registration_id'] ?? 0);
        $user = $this->getCurrentUser();

        // Load the registration and verify it belongs to this user
        $stmt = $this->db->prepare("
            SELECT * FROM registrations WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$registrationId, $user['id']]);
        $registration = $stmt->fetch();

        if (!$registration) {
            $_SESSION['error'] = 'Registrace nebyla nalezena.';
            header('Location: ' . $this->backUrl());
            exit;
        }

        if (!in_array($registration['payment_status'], ['pending', 'upgradable'])) {
            $_SESSION['error'] = 'Lze zrušit pouze registrace čekající na platbu nebo nabídnutý upgrade.';
            header('Location: ' . $this->backUrl());
            exit;
        }

        $this->db->prepare("UPDATE registrations SET  payment_status='cancelled' WHERE id = ?")
                 ->execute([$registrationId]);

        $_SESSION['success'] = 'Registrace byla úspěšně zrušena.';
        $this->markCartChanged();
        header('Location: ' . $this->backUrl());
        exit;
    }

    public function saveAnswer()
    {
        $this->requireAuth();

        if (!csrf_validate('dashboard', $_POST['_csrf'] ?? null)) {
            $_SESSION['error'] = 'Neplatný bezpečnostní token.';
            header('Location: /dashboard');
            exit;
        }

        $questionId = (int)($_POST['question_id'] ?? 0);
        $value      = trim($_POST['value'] ?? '');
        $user       = $this->getCurrentUser();

        if ($questionId <= 0) {
            $_SESSION['error'] = 'Neplatná otázka.';
            header('Location: /dashboard');
            exit;
        }

        // Verify question exists and is active
        $stmt = $this->db->prepare("SELECT id, type, ticket_id FROM user_questions WHERE id = ? AND is_active = 1");
        $stmt->execute([$questionId]);
        $question = $stmt->fetch();

        if (!$question) {
            $_SESSION['error'] = 'Otázka nebyla nalezena.';
            header('Location: /dashboard');
            exit;
        }

        // Validate by type
        if ($question['type'] === 'yes_no' && !in_array($value, ['yes', 'no'])) {
            $_SESSION['error'] = 'Neplatná odpověď.';
            header('Location: /dashboard');
            exit;
        }

        if ($value !== '') {
            $this->storeAnswer($user['id'], $question, $value);

            $_SESSION['success'] = 'Odpověď byla uložena.';
        }

        header('Location: /dashboard');
        exit;
    }

    public function reorderRegistrations()
    {
        $this->requireAuth();

        header('Content-Type: application/json');

        $body = file_get_contents('php://input');
        $data = json_decode($body, true);

        if (!isset($data['ids']) || !is_array($data['ids'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid input']);
            exit;
        }

        $user = $this->getCurrentUser();
        $ids  = array_values(array_map('intval', $data['ids']));

        if (empty($ids)) {
            echo json_encode(['ok' => true]);
            exit;
        }

        // Verify all supplied IDs belong to this user
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare("
            SELECT id FROM registrations
            WHERE id IN ($placeholders)
              AND user_id = ?
        ");
        $stmt->execute([...$ids, $user['id']]);
        $validIds = array_column($stmt->fetchAll(), 'id');

        if (count($validIds) !== count($ids)) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid registration IDs']);
            exit;
        }

        
        $stmt = $this->db->prepare("
            SELECT count(*) as c
            FROM registrations r
            WHERE r.user_id = ? and r.id not IN ($placeholders)            
        ");
        $stmt->execute([...$ids, $user['id']]);
        $count_existing= $stmt->fetch()['c'];

        // Write priority 1…n in the supplied order
        $update = $this->db->prepare("UPDATE registrations SET priority = ? WHERE id = ?");
        foreach ($ids as $position => $id) {
            $update->execute([$position + 1, $id]);
        }

        echo json_encode(['ok' => true]);
        exit;
    }

    /**
     * Save an answer; a yes_no question linked to a ticket adds/cancels that ticket purchase.
     */
    private function storeAnswer(int $userId, array $question, string $value): void
    {
        UserAnswer::upsert($this->db, $userId, (int)$question['id'], $value);

        // If this yes_no question has an associated ticket, manage the purchase automatically
        if ($question['type'] === 'yes_no' && !empty($question['ticket_id'])) {
            $ticketId = (int)$question['ticket_id'];
            $this->markCartChanged();

            if ($value === 'yes') {
                // Add pending purchase only if user has no active (pending/paid) purchase for this ticket
                $stmt = $this->db->prepare("
                    SELECT id FROM purchases
                    WHERE user_id = ? AND item_type = 'ticket' AND item_id = ?
                      AND payment_status IN ('pending', 'paid')
                    LIMIT 1
                ");
                $stmt->execute([$userId, $ticketId]);
                if (!$stmt->fetch()) {
                    $this->db->prepare("
                        INSERT INTO purchases (user_id, item_type, item_id, quantity, payment_status)
                        VALUES (?, 'ticket', ?, 1, 'pending')
                    ")->execute([$userId, $ticketId]);
                }
            } else {
                // Answer changed to 'no' — cancel any pending purchase for this ticket
                $this->db->prepare("
                    UPDATE purchases
                    SET payment_status = 'cancelled'
                    WHERE user_id = ? AND item_type = 'ticket' AND item_id = ?
                      AND payment_status = 'pending'
                ")->execute([$userId, $ticketId]);
            }
        }
    }

    /**
     * GET /cart – shopping cart: all unpaid workshops, tickets, DětskýTřesk tickets and merch
     * with remove buttons and the total. Payment details are shown only after "Chci zaplatit".
     */
    public function cart()
    {
        $this->requireAuth();
        $this->renderCart($this->getCurrentUser(), false);
    }

    /**
     * POST /cart/pay – "Chci zaplatit": saves required answers (always enforced), stores the reminder choice, then redirects to GET /cart/pay.
     * GET  /cart/pay – cart with QR code and bank details.
     */
    public function pay()
    {
        $this->requireAuth();
        $user = $this->getCurrentUser();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            if ($this->cartNeedsQuestions($user['id'])) {
                $_SESSION['error'] = 'Před platbou prosím vyplňte povinné otázky.';
                header('Location: /cart');
                exit;
            }
            $this->renderCart($user, true);
            return;
        }

        if (!csrf_validate('dashboard', $_POST['_csrf'] ?? null)) {
            $_SESSION['error'] = 'Neplatný bezpečnostní token.';
            header('Location: /cart');
            exit;
        }

        // Save answers to required questions submitted with the pay form
        if ($this->cartNeedsQuestions($user['id'])) {
            $stmt = $this->db->prepare("SELECT id, type, ticket_id FROM user_questions WHERE id = ? AND is_active = 1");
            foreach ($_POST['answers'] ?? [] as $questionId => $value) {
                $value = trim((string)$value);
                $stmt->execute([(int)$questionId]);
                $question = $stmt->fetch();
                if ($question && $value !== '' && ($question['type'] !== 'yes_no' || in_array($value, ['yes', 'no'], true))) {
                    $this->storeAnswer($user['id'], $question, $value);
                }
            }
            if ($this->cartNeedsQuestions($user['id'])) {
                $_SESSION['error'] = 'Před platbou prosím vyplňte všechny povinné otázky.';
                header('Location: /cart');
                exit;
            }
        }

        // Reminder e-mail: ReminderService sends it when awaiting_payment > 0
        $cart = $this->buildCart($user);
        User::update($this->db, $user['id'], [
            'awaiting_payment' => !empty($_POST['remind']) ? $cart['total'] : 0,
        ]);

        header('Location: /cart/pay');
        exit;
    }

    /**
     * Required questions must be answered before paying for anything in the cart
     * (tickets and merch can be ordered without them, only payment is blocked).
     */
    private function cartNeedsQuestions(int $userId): bool
    {
        return $this->hasBlockingQuestions($userId);
    }

    private function renderCart(array $user, bool $showPayment): void
    {
        $cart = $this->buildCart($user);

        $vars = [
            'user'          => $user,
            'active_page'   => 'cart',
            'csrf'          => csrf_token('dashboard'),
            'registrations' => $cart['registrations'],
            'purchases'     => $cart['purchases'],
            'total'         => $cart['total'],
            'show_payment'  => $showPayment,
            'static'        => (new StaticBlock)->getByPrefix($this->db, 'wizard'),
        ];

        if ($showPayment) {
            $paymentConfig = payment_config();
            $qrPlatba = new QRPlatba();
            $qrPlatba->setIban($paymentConfig['iban'])
                ->setAmount($cart['total'])
                ->setScale(5)
                ->setVariableSymbol($user['id']);

            $vars['payment'] = [
                'account_number'  => $paymentConfig['iban'],
                'readable'        => $paymentConfig['readable'],
                'variable_symbol' => $user['id'],
                'amount'          => $cart['total'],
                'currency'        => $paymentConfig['currency'],
                'message'         => $paymentConfig['message'],
            ];
            $vars['qr'] = 'data:image/png;base64,' . base64_encode($qrPlatba->generateQr());
            $vars['reminder_on'] = (float)(User::findById($this->db, $user['id'])['awaiting_payment'] ?? 0) > 0;
        } else {
            $vars['needs_questions'] = $this->cartNeedsQuestions($user['id'])
                ? array_values(array_filter(
                    UserQuestion::getWithAnswersForUser($this->db, $user['id']),
                    fn($q) => $q['is_required'] && ($q['value'] === null || $q['value'] === '')
                ))
                : [];
        }

        $session = $this->getSessionMessages();
        $vars['error']   = $session['error'];
        $vars['success'] = $session['success'];

        echo $this->twig->render('pages/cart.twig', $vars);
    }

    /**
     * Unpaid cart contents and total. Workshops the user most likely won't get into
     * (queue beyond capacity) and overlapping timeslots are priced at 0.
     */
    private function buildCart(array $user): array
    {
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
                   COALESCE(t.price, m.price) AS item_price,
                   CASE
                       WHEN p.item_type = 'merch' THEN 'merch'
                       WHEN t.type IN ('child', 'adult', 'family') THEN 'children'
                       ELSE 'ticket'
                   END AS cart_group
            FROM purchases p
            LEFT JOIN tickets t ON t.id = p.item_id AND p.item_type = 'ticket'
            LEFT JOIN merch m ON m.id = p.item_id AND p.item_type = 'merch'
            WHERE p.user_id = ? AND p.payment_status IN ('pending','paid')
            ORDER BY p.item_type, p.created_at
        ");
        $stmt->execute([$user['id']]);
        $purchases = $stmt->fetchAll();

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

        return ['registrations' => $registrations, 'purchases' => $purchases, 'total' => $total];
    }
}
