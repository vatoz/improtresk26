<?php
namespace App\Controllers;

class DashboardController extends BaseController
{
    public function index()
    {
        $this->requireAuth();

        $user = $this->getCurrentUser();

        $stmt = $this->db->prepare("
            SELECT r.id, r.workshop_id, r.payment_status, r.priority, r.created_at,
                   w.name AS workshop_name, w.date, w.time, w.price, w.timeslot,
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

        $session = $this->getSessionMessages();

        echo $this->twig->render('pages/dashboard.twig', [
            'user'          => $user,
            'active_page'   => 'dashboard',
            'registrations' => $registrations,
            'purchases'     => $purchases,
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
            SELECT * FROM registrations WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$registrationId, $user['id']]);
        $registration = $stmt->fetch();

        if (!$registration) {
            $_SESSION['error'] = 'Registrace nebyla nalezena.';
            header('Location: /dashboard');
            exit;
        }

        if (!in_array($registration['payment_status'], ['pending', 'upgradable'])) {
            $_SESSION['error'] = 'Lze zrušit pouze registrace čekající na platbu nebo nabídnutý upgrade.';
            header('Location: /dashboard');
            exit;
        }

        $this->db->prepare("UPDATE registrations SET  payment_status='cancelled' WHERE id = ?")
                 ->execute([$registrationId]);

        $_SESSION['success'] = 'Registrace byla úspěšně zrušena.';
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
}
