<?php
namespace App\Services;

use App\Models\MailQueue;
use PDO;

/**
 * ReminderService
 *
 * Finds users with a pending payment amount (awaiting_payment > 0) whose
 * record has not been updated in the last 4 minutes, sends them a payment
 * reminder e-mail, and resets awaiting_payment to zero so the reminder is
 * not sent again until a new amount is set.
 */
class ReminderService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Send payment reminders and reset awaiting_payment for each eligible user.
     *
     * @return int Number of reminders sent.
     */
    public function sendReminders(): int
    {
        $stmt = $this->db->prepare("
            SELECT id, email, name, awaiting_payment
            FROM users
            WHERE awaiting_payment > 0
              AND updated_at < DATE_SUB(NOW(), INTERVAL 4 MINUTE)
        ");
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $sent = 0;
        foreach ($users as $user) {
            MailQueue::sendAwaitingPayment(
                $this->db,
                $user['email'],
                $user['name'],
                (int) $user['id'],
                (float) $user['awaiting_payment']
            );

            $this->db->prepare("
                UPDATE users SET awaiting_payment = 0 WHERE id = ?
            ")->execute([$user['id']]);

            $sent++;
        }

        return $sent;
    }
}
