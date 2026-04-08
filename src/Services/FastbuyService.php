<?php
namespace App\Services;

use App\Models\MailQueue;
use App\Models\TransactionList;
use App\Models\User;
use PDO;

/**
 * Processes pending transactions whose variable symbol encodes a registration:
 *   variable_symbol = {user_id}{workshop_id_4digits}
 *
 * For each match where the transaction amount equals the workshop price and the
 * registration is still unpaid, the service marks the registration as paid,
 * closes the transaction, and enqueues a confirmation e-mail.
 */
class FastbuyService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * @return array{processed: int, skipped: int}
     */
    public function run(): array
    {
        $transactions = TransactionList::getPending($this->db, 500);

        $processed = 0;
        $skipped   = 0;

        foreach ($transactions as $tx) {
            $vs = (string) ($tx['variable_symbol'] ?? '');

            if (strlen($vs) < 5) {
                $skipped++;
                continue;
            }

            $workshopId = (int) substr($vs, -4);
            $userId     = (int) substr($vs, 0, -4);

            if ($userId <= 0 || $workshopId <= 0) {
                $skipped++;
                continue;
            }

            $registration = $this->findUnpaidRegistration($userId, $workshopId);
            if (!$registration) {
                $skipped++;
                continue;
            }

            $workshop = $this->fetchWorkshop($workshopId);
            if (!$workshop) {
                $skipped++;
                continue;
            }

            // Amount must match workshop price exactly (compare as floats, allow tiny rounding)
            if (abs((float) $tx['amount'] - (float) $workshop['price']) > 0.009) {
                $skipped++;
                continue;
            }

            $this->markRegistrationPaid($registration['id']);
            TransactionList::markCompleted($this->db, (int) $tx['id']);
            $this->queueConfirmationMail($userId);

            $processed++;
        }

        return ['processed' => $processed, 'skipped' => $skipped];
    }

    // -------------------------------------------------------------------------

    private function findUnpaidRegistration(int $userId, int $workshopId): array|false
    {
        $stmt = $this->db->prepare("
            SELECT id
            FROM registrations
            WHERE user_id = ? AND workshop_id = ? AND payment_status = 'pending'
            LIMIT 1
        ");
        $stmt->execute([$userId, $workshopId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function fetchWorkshop(int $workshopId): array|false
    {
        $stmt = $this->db->prepare("SELECT id, price FROM workshops WHERE id = ? LIMIT 1");
        $stmt->execute([$workshopId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function markRegistrationPaid(int $registrationId): void
    {
        $stmt = $this->db->prepare("
            UPDATE registrations
            SET payment_status = 'paid', paid_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$registrationId]);
    }

    private function queueConfirmationMail(int $userId): void
    {
        $user = User::findById($this->db, $userId);
        if (!$user) {
            return;
        }

        $stmt = $this->db->prepare("
            SELECT w.name
            FROM registrations r
            JOIN workshops w ON r.workshop_id = w.id
            WHERE r.user_id = ? AND r.payment_status = 'paid'
            ORDER BY w.name
        ");
        $stmt->execute([$userId]);
        $workshops = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $stmt = $this->db->prepare("
            SELECT p.item_type, p.quantity,
                   COALESCE(t.name, m.name) AS name
            FROM purchases p
            LEFT JOIN tickets t ON t.id = p.item_id AND p.item_type = 'ticket'
            LEFT JOIN merch   m ON m.id = p.item_id AND p.item_type = 'merch'
            WHERE p.user_id = ? AND p.payment_status = 'paid'
            ORDER BY p.item_type, p.created_at
        ");
        $stmt->execute([$userId]);
        $purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $tickets = array_values(array_filter($purchases, fn($p) => $p['item_type'] === 'ticket'));
        $merch   = array_values(array_filter($purchases, fn($p) => $p['item_type'] === 'merch'));

        $dashboardUrl = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'improtresk.cz') . '/dashboard';

        MailQueue::sendPaymentConfirmed($this->db, $user['email'], $user['name'], $workshops, $tickets, $merch, $dashboardUrl);
    }
}
