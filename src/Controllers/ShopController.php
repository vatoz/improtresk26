<?php
namespace App\Controllers;

use App\Models\Merch;
use vplacek\QRPlatba\QRPlatba;

class ShopController extends BaseController
{
    public function ticketPlease()
    {
        $this->requireAuth();
        $user = $this->getCurrentUser();

        $stmt = $this->db->prepare("SELECT * FROM tickets WHERE id = 1 LIMIT 1");
        $stmt->execute();
        $ticket = $stmt->fetch();

        if (!$ticket) {
            echo $this->twig->render('pages/ticket-please.twig', [
                'user'        => $user,
                'active_page' => '',
                'error'       => 'Vstupenka nebyla nalezena.',
            ]);
            return;
        }

        // Create pending purchase if not already existing
        $existing = $this->db->prepare("
            SELECT id FROM purchases
            WHERE user_id = ? AND item_type = 'ticket' AND item_id = 1 AND payment_status != 'cancelled'
        ");
        $existing->execute([$user['id']]);
        if (!$existing->fetch()) {
            $this->db->prepare(
                "INSERT INTO purchases (user_id, item_type, item_id) VALUES (?, 'ticket', 1)"
            )->execute([$user['id']]);
        }

        $paymentConfig  = payment_config();
        $variableSymbol = $user['id'];
        $amount         = (float) $ticket['price'];

        $qrPlatba = new QRPlatba();
        $qrPlatba->setIban($paymentConfig['iban'])
            ->setAmount($amount)
            ->setScale(5)
            ->setVariableSymbol($variableSymbol);

        echo $this->twig->render('pages/ticket-please.twig', [
            'user'        => $user,
            'active_page' => '',
            'ticket'      => $ticket,
            'payment'     => [
                'readable'        => $paymentConfig['readable'],
                'variable_symbol' => $variableSymbol,
                'amount'          => $amount,
                'currency'        => $paymentConfig['currency'],
                'message'         => $paymentConfig['message'],
            ],
            'qr'          => 'data:image/png;base64,' . base64_encode($qrPlatba->generateQr()),
        ]);
    }

    /**
     * GET /tickets – standard tickets grouped by day.
     */
    public function tickets()
    {
        $stmt = $this->db->query("
            SELECT * FROM tickets
            WHERE is_active = 1 AND type = 'standard'
            ORDER BY date IS NULL, date, time, name
        ");

        $days = [];
        foreach ($stmt->fetchAll() as $ticket) {
            $key = $ticket['date'] ?? '';
            $days[$key]['date']      = $ticket['date'];
            $days[$key]['tickets'][] = $ticket;
        }

        $user = $this->getCurrentUser();
        $userPurchases = [];
        if ($user) {
            $stmt = $this->db->prepare("
                SELECT item_id, payment_status FROM purchases
                WHERE user_id = ? AND item_type = 'ticket' AND payment_status != 'cancelled'
            ");
            $stmt->execute([$user['id']]);
            $userPurchases = array_column($stmt->fetchAll(), 'payment_status', 'item_id');
        }

        $session = $this->getSessionMessages();

        echo $this->twig->render('pages/tickets.twig', [
            'user'               => $user,
            'active_page'        => 'tickets',
            'days'               => array_values($days),
            'user_purchases'     => $userPurchases,
            'csrf'               => csrf_token('shop'),
            'error'              => $session['error'],
            'success'            => $session['success'] ?? $session['info'],
        ]);
    }

    public function merch()
    {
        if (!merch_enabled()) {
            header('Location: /');
            exit;
        }

        $groups = Merch::getActiveGrouped($this->db);

        $user = $this->getCurrentUser();
        $userPurchasedIds = [];
        if ($user) {
            $stmt = $this->db->prepare("
                SELECT item_id FROM purchases
                WHERE user_id = ? AND item_type = 'merch' AND payment_status != 'cancelled'
            ");
            $stmt->execute([$user['id']]);
            $userPurchasedIds = array_column($stmt->fetchAll(), 'item_id');
        }

        $session = $this->getSessionMessages();

        echo $this->twig->render('pages/merch.twig', [
            'user'               => $user,
            'active_page'        => 'merch',
            'groups'             => $groups,
            'user_purchased_ids' => $userPurchasedIds,
            'csrf'               => csrf_token('shop'),
            'error'              => $session['error'],
            'success'            => $session['success'] ?? $session['info'],
        ]);
    }

    /**
     * POST /shop/remove-ticket – cancel the user's unpaid order of a ticket.
     */
    public function removeTicket()
    {
        $this->requireAuth();

        if (!csrf_validate('shop', $_POST['_csrf'] ?? null)) {
            $_SESSION['error'] = 'Neplatný bezpečnostní token.';
            header('Location: /tickets');
            exit;
        }

        $ticketId = (int)($_POST['ticket_id'] ?? 0);
        $user     = $this->getCurrentUser();

        $stmt = $this->db->prepare("
            UPDATE purchases SET payment_status = 'cancelled'
            WHERE user_id = ? AND item_type = 'ticket' AND item_id = ? AND payment_status NOT IN ('paid', 'cancelled')
        ");
        $stmt->execute([$user['id'], $ticketId]);

        if ($stmt->rowCount() > 0) {
            $_SESSION['success'] = 'Vstupenka byla odebrána z objednávky.';
            $this->markCartChanged();
        }
        header('Location: /tickets');
        exit;
    }

    public function buy()
    {
        $this->requireAuth();

        if (!csrf_validate('shop', $_POST['_csrf'] ?? null)) {
            $_SESSION['error'] = 'Neplatný bezpečnostní token.';
            header('Location: /dashboard');
            exit;
        }

        $itemType = $_POST['item_type'] ?? null;
        $itemId   = (int)($_POST['item_id'] ?? 0);
        $user     = $this->getCurrentUser();

        if (!in_array($itemType, ['ticket', 'merch']) || !$itemId) {
            $_SESSION['error'] = 'Neplatný požadavek.';
            header('Location: /dashboard');
            exit;
        }

        if ($itemType === 'merch') {
            if (!merch_enabled()) {
                $_SESSION['error'] = 'Merch momentálně není v nabídce.';
                header('Location: /cart');
                exit;
            }
            $this->buyMerch($user, $itemId);
        }

        $stmt = $this->db->prepare("SELECT id FROM tickets WHERE id = ? AND is_active = 1");
        $stmt->execute([$itemId]);
        if (!$stmt->fetch()) {
            $_SESSION['error'] = 'Položka nebyla nalezena.';
            header('Location: /tickets');
            exit;
        }

        $stmt = $this->db->prepare("
            SELECT id FROM purchases
            WHERE user_id = ? AND item_type = ? AND item_id = ? AND payment_status != 'cancelled'
        ");
        $stmt->execute([$user['id'], $itemType, $itemId]);
        $existing = $stmt->fetch();

        if ($existing) {
            $_SESSION['info'] = 'Tuto vstupenku již máte v objednávce.';
            header('Location: /tickets');
            exit;
        }

        $this->db->prepare("
            INSERT INTO purchases (user_id, item_type, item_id) VALUES (?, 'ticket', ?)
        ")->execute([$user['id'], $itemId]);

        $_SESSION['success'] = 'Položka byla přidána do vaší objednávky.';
        $this->markCartChanged();
        header('Location: /tickets');
        exit;
    }

    /**
     * Add one piece of merch to the cart. Checks the sale deadline and stock
     * under a row lock so two buyers can't take the last piece. Always redirects.
     */
    private function buyMerch(array $user, int $itemId): never
    {
        $this->db->beginTransaction();

        $stmt = $this->db->prepare("SELECT * FROM merch WHERE id = ? AND is_active = 1 FOR UPDATE");
        $stmt->execute([$itemId]);
        $item = $stmt->fetch();

        if ($item) {
            Merch::recountStock($this->db, $itemId);
            $stmt->execute([$itemId]);
            $item = $stmt->fetch();
        }

        $error = match (true) {
            !$item                                   => 'Položka nebyla nalezena.',
            Merch::saleState($item) === 'ended'      => 'Objednávky tohoto merche již skončily.',
            Merch::saleState($item) === 'soldout'    => 'Tento merch je bohužel vyprodaný.',
            default                                  => null,
        };
        if ($error) {
            $this->db->rollBack();
            $_SESSION['error'] = $error;
            header('Location: /merch');
            exit;
        }

        $stmt = $this->db->prepare("
            SELECT id FROM purchases
            WHERE user_id = ? AND item_type = 'merch' AND item_id = ? AND payment_status = 'pending'
            LIMIT 1
        ");
        $stmt->execute([$user['id'], $itemId]);
        if ($existing = $stmt->fetch()) {
            $this->db->prepare("UPDATE purchases SET quantity = quantity + 1 WHERE id = ?")
                ->execute([$existing['id']]);
        } else {
            $this->db->prepare("
                INSERT INTO purchases (user_id, item_type, item_id) VALUES (?, 'merch', ?)
            ")->execute([$user['id'], $itemId]);
        }

        Merch::recountStock($this->db, $itemId);
        $this->db->commit();

        $_SESSION['success'] = 'Položka byla přidána do vaší objednávky.';
        $this->markCartChanged();
        header('Location: /merch#merch_' . (int) ($item['parent_merch'] ?: $item['id']));
        exit;
    }

    /**
     * POST /children/add-to-cart – add DětskýTřesk tickets (tickets[id] => quantity) to the cart.
     */
    public function addChildrenTickets()
    {
        $this->requireAuth();
        $user   = $this->getCurrentUser();
        $itemId = (int)($_POST['children_item_id'] ?? 0);
        $back   = '/children' . ($itemId ? '#item_' . $itemId : '');

        if (!csrf_validate('shop', $_POST['_csrf'] ?? null)) {
            $_SESSION['error'] = 'Neplatný bezpečnostní token.';
            header('Location: ' . $back);
            exit;
        }

        $added = 0;
        foreach ($_POST['tickets'] ?? [] as $ticketId => $qty) {
            $ticketId = (int)$ticketId;
            $qty      = min(20, max(0, (int)$qty));
            if (!$ticketId || !$qty) continue;

            $stmt = $this->db->prepare("
                SELECT id FROM tickets
                WHERE id = ? AND is_active = 1 AND type IN ('child', 'adult', 'family')
            ");
            $stmt->execute([$ticketId]);
            if (!$stmt->fetch()) continue;

            $stmt = $this->db->prepare("
                SELECT id FROM purchases
                WHERE user_id = ? AND item_type = 'ticket' AND item_id = ? AND payment_status = 'pending'
                LIMIT 1
            ");
            $stmt->execute([$user['id'], $ticketId]);
            if ($existing = $stmt->fetch()) {
                $this->db->prepare("UPDATE purchases SET quantity = quantity + ? WHERE id = ?")
                    ->execute([$qty, $existing['id']]);
            } else {
                $this->db->prepare("
                    INSERT INTO purchases (user_id, item_type, item_id, quantity) VALUES (?, 'ticket', ?, ?)
                ")->execute([$user['id'], $ticketId, $qty]);
            }
            $added += $qty;
        }

        if ($added) {
            $_SESSION['success'] = 'Vstupenky byly přidány do košíku.';
            $this->markCartChanged();
        } else {
            $_SESSION['error'] = 'Vyberte prosím alespoň jednu vstupenku.';
        }
        header('Location: ' . $back);
        exit;
    }

    public function cancelPurchase()
    {
        $this->requireAuth();

        if (!csrf_validate('dashboard', $_POST['_csrf'] ?? null)) {
            $_SESSION['error'] = 'Neplatný bezpečnostní token.';
            header('Location: ' . $this->backUrl());
            exit;
        }

        $purchaseId = (int)($_POST['purchase_id'] ?? 0);
        $user       = $this->getCurrentUser();

        $stmt = $this->db->prepare("SELECT * FROM purchases WHERE id = ? AND user_id = ?");
        $stmt->execute([$purchaseId, $user['id']]);
        $purchase = $stmt->fetch();

        if (!$purchase) {
            $_SESSION['error'] = 'Objednávka nebyla nalezena.';
            header('Location: ' . $this->backUrl());
            exit;
        }

        if ($purchase['payment_status'] !== 'pending') {
            $_SESSION['error'] = 'Lze zrušit pouze čekající objednávky.';
            header('Location: ' . $this->backUrl());
            exit;
        }

        $this->db->prepare("UPDATE purchases SET payment_status = 'cancelled' WHERE id = ?")
            ->execute([$purchaseId]);
        Merch::recountForPurchase($this->db, $purchaseId);

        $_SESSION['success'] = 'Objednávka byla zrušena.';
        $this->markCartChanged();
        header('Location: ' . $this->backUrl());
        exit;
    }
}
