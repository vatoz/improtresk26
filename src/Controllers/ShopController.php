<?php
namespace App\Controllers;

class ShopController extends BaseController
{
    public function tickets()
    {
        $stmt = $this->db->query("SELECT * FROM tickets WHERE is_active = 1 ORDER BY date, time");
        $tickets = $stmt->fetchAll();

        $userPurchasedIds = [];
        if ($user = $this->getCurrentUser()) {
            $stmt = $this->db->prepare("
                SELECT item_id FROM purchases
                WHERE user_id = ? AND item_type = 'ticket' AND payment_status != 'cancelled'
            ");
            $stmt->execute([$user['id']]);
            $userPurchasedIds = array_column($stmt->fetchAll(), 'item_id');
        }

        echo $this->twig->render('pages/tickets.twig', [
            'user'               => $this->getCurrentUser(),
            'active_page'        => 'tickets',
            'tickets'            => $tickets,
            'user_purchased_ids' => $userPurchasedIds,
            'csrf'               => csrf_token('shop'),
        ]);
    }

    public function merch()
    {
        $stmt = $this->db->query("SELECT * FROM merch WHERE is_active = 1 ORDER BY name");
        $items = $stmt->fetchAll();

        $userPurchasedIds = [];
        if ($user = $this->getCurrentUser()) {
            $stmt = $this->db->prepare("
                SELECT item_id FROM purchases
                WHERE user_id = ? AND item_type = 'merch' AND payment_status != 'cancelled'
            ");
            $stmt->execute([$user['id']]);
            $userPurchasedIds = array_column($stmt->fetchAll(), 'item_id');
        }

        echo $this->twig->render('pages/merch.twig', [
            'user'               => $this->getCurrentUser(),
            'active_page'        => 'merch',
            'items'              => $items,
            'user_purchased_ids' => $userPurchasedIds,
            'csrf'               => csrf_token('shop'),
        ]);
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

        $table = $itemType === 'ticket' ? 'tickets' : 'merch';
        $stmt  = $this->db->prepare("SELECT id FROM $table WHERE id = ? AND is_active = 1");
        $stmt->execute([$itemId]);
        if (!$stmt->fetch()) {
            $_SESSION['error'] = 'Položka nebyla nalezena.';
            header('Location: /' . ($itemType === 'ticket' ? 'tickets' : 'merch'));
            exit;
        }

        $stmt = $this->db->prepare("
            SELECT id FROM purchases
            WHERE user_id = ? AND item_type = ? AND item_id = ? AND payment_status != 'cancelled'
        ");
        $stmt->execute([$user['id'], $itemType, $itemId]);
        $existing = $stmt->fetch();

        if ($existing && $itemType === 'ticket') {
            $_SESSION['info'] = 'Tuto vstupenku již máte v objednávce.';
            header('Location: /tickets');
            exit;
        }

        if ($existing) {
            // merch: increment quantity on the existing row
            $this->db->prepare("
                UPDATE purchases SET quantity = quantity + 1 WHERE id = ?
            ")->execute([$existing['id']]);
        } else {
            $this->db->prepare("
                INSERT INTO purchases (user_id, item_type, item_id) VALUES (?, ?, ?)
            ")->execute([$user['id'], $itemType, $itemId]);
        }

        $_SESSION['success'] = 'Položka byla přidána do vaší objednávky.';
        header('Location: /' . ($itemType === 'ticket' ? 'tickets' : 'merch'));
        exit;
    }

    public function cancelPurchase()
    {
        $this->requireAuth();

        if (!csrf_validate('dashboard', $_POST['_csrf'] ?? null)) {
            $_SESSION['error'] = 'Neplatný bezpečnostní token.';
            header('Location: /dashboard');
            exit;
        }

        $purchaseId = (int)($_POST['purchase_id'] ?? 0);
        $user       = $this->getCurrentUser();

        $stmt = $this->db->prepare("SELECT * FROM purchases WHERE id = ? AND user_id = ?");
        $stmt->execute([$purchaseId, $user['id']]);
        $purchase = $stmt->fetch();

        if (!$purchase) {
            $_SESSION['error'] = 'Objednávka nebyla nalezena.';
            header('Location: /dashboard');
            exit;
        }

        if ($purchase['payment_status'] !== 'pending') {
            $_SESSION['error'] = 'Lze zrušit pouze čekající objednávky.';
            header('Location: /dashboard');
            exit;
        }

        $this->db->prepare("UPDATE purchases SET payment_status = 'cancelled' WHERE id = ?")
            ->execute([$purchaseId]);

        $_SESSION['success'] = 'Objednávka byla zrušena.';
        header('Location: /dashboard');
        exit;
    }
}
