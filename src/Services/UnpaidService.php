<?php
namespace App\Services;

use PDO;

/**
 * UnpaidService
 *
 * Finds the single user whose approved registration has been waiting longest
 * (oldest updated_at, past the 5-day window), cancels all of their overdue
 * approved registrations to free up spots, then calls RollService so those
 * spots can immediately be offered to next-in-line candidates.
 *
 * Processing one user per cron tick keeps the load low and ensures RollService
 * runs between each eviction.
 */
class UnpaidService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Cancel overdue approved registrations for the single user with the
     * oldest overdue registration, then trigger RollService.
     *
     * @return int  Number of registrations cancelled (0 if nobody is overdue).
     */
    public function cancelOverdue(): int
    {
        $userId = $this->findOldestOverdueUser();

        if ($userId === null) {
            return 0;
        }

        $stmt = $this->db->prepare("
            UPDATE registrations
            SET payment_status = 'unpaid'
            WHERE user_id          = ?
              AND payment_status   in ('approved','cancelled')
              AND updated_at < DATE_SUB(NOW(), INTERVAL 5 DAY)
        ");
        $stmt->execute([$userId]);
        $cancelled = $stmt->rowCount();

        if ($cancelled > 0) {
            (new RollService($this->db))->run();
        }

        return $cancelled;
    }

    /**
     * Return the user_id of the user whose oldest overdue approved registration
     * has been waiting the longest, or null if no one is overdue.
     */
    private function findOldestOverdueUser(): ?int
    {
        $stmt = $this->db->query("
            SELECT user_id
            FROM registrations
            WHERE payment_status = 'approved'
              AND updated_at < DATE_SUB(NOW(), INTERVAL 5 DAY)
            ORDER BY updated_at ASC
            LIMIT 1
        ");

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (int) $row['user_id'] : null;
    }
}
