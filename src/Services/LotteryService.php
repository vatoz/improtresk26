<?php
namespace App\Services;

use App\Models\MailQueue;
use App\Models\Workshop;
use PDO;

/**
 * LotteryService
 *
 * Draws all users with pending registrations in a random order and, for each
 * user, walks through their pending registrations sorted by priority.
 *
 * For each registration it checks:
 *   1. The workshop still has a free spot (capacity > approved+paid count).
 *   2. The user has no approved/paid workshop in an overlapping timeslot.
 *
 * On success → status set to 'approved' (awaiting payment).
 * On failure → status set to 'cancelled'.
 *
 * After all registrations for a user are resolved, a result e-mail is queued.
 */
class LotteryService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Run the full lottery.
     *
     * @return array  Per-user result rows:
     *                [['user' => [...], 'approved' => [...], 'cancelled' => [...]], ...]
     */
    public function run(): array
    {
        $users = $this->fetchUsersInRandomOrder();

        $results = [];
        foreach ($users as $user) {
            [$approved, $cancelled] = $this->processUser($user);

            MailQueue::addWithTemplate(
                $this->db,
                $user['email'],
                'Výsledky losování workshopů – Improtřesk 2026',
                'lottery-result.twig',
                [
                    'name'      => $user['name'],
                    'approved'  => $approved,
                    'cancelled' => $cancelled,
                ]
            );

            $results[] = [
                'user'      => $user,
                'approved'  => $approved,
                'cancelled' => $cancelled,
            ];
        }

        return $results;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Return distinct users who have at least one pending registration,
     * shuffled into a random order.
     */
    private function fetchUsersInRandomOrder(): array
    {
        $stmt = $this->db->query("
            SELECT DISTINCT u.id, u.name, u.email
            FROM registrations r
            JOIN users u ON r.user_id = u.id
            WHERE r.payment_status = 'pending'
            ORDER BY RAND()
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Process all pending registrations for one user.
     *
     * Approvals are written to the DB immediately so that subsequent capacity
     * and timeslot checks within the same lottery run see the up-to-date state.
     *
     * @return array{0: array, 1: array}  [$approvedRows, $cancelledRows]
     */
    private function processUser(array $user): array
    {
        $registrations = $this->fetchPendingRegistrations($user['id']);

        $approved  = [];
        $cancelled = [];

        foreach ($registrations as $reg) {
            if ($this->hasCapacity($reg['workshop_id']) && !$this->hasTimeslotConflict($user['id'], $reg['timeslot'])) {
                $this->setStatus($reg['id'], 'approved');
                $approved[] = $reg;
            } else {
                $this->setStatus($reg['id'], 'cancelled');
                $cancelled[] = $reg;
            }
        }

        return [$approved, $cancelled];
    }

    /**
     * Pending registrations for a user, ordered by priority (nulls last) then created_at.
     */
    private function fetchPendingRegistrations(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT r.id, r.workshop_id, r.priority, r.created_at,
                   w.name    AS workshop_name,
                   w.timeslot,
                   w.capacity,
                   w.date,
                   w.time
            FROM registrations r
            JOIN workshops w ON r.workshop_id = w.id
            WHERE r.user_id        = ?
              AND r.payment_status = 'pending'
            ORDER BY COALESCE(r.priority, 2147483647) ASC, r.created_at ASC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Returns true if the workshop still has at least one free spot.
     * A spot is considered taken when payment_status is 'approved' or 'paid'.
     */
    private function hasCapacity(int $workshopId): bool
    {
        $stmt = $this->db->prepare("
            SELECT w.capacity,
                   COUNT(r.id) AS taken
            FROM workshops w
            LEFT JOIN registrations r
                   ON w.id = r.workshop_id
                  AND r.payment_status IN ('approved', 'paid')
            WHERE w.id = ?
            GROUP BY w.id
        ");
        $stmt->execute([$workshopId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row && (int)$row['capacity'] > (int)$row['taken'];
    }

    /**
     * Returns true if the user already has an approved or paid registration
     * in any timeslot that overlaps with $timeslot.
     *
     * Because approvals are committed immediately, the DB always reflects the
     * current state of the lottery run.
     */
    private function hasTimeslotConflict(int $userId, ?string $timeslot): bool
    {
        if (empty($timeslot)) {
            return false;
        }

        $stmt = $this->db->prepare("
            SELECT w.timeslot
            FROM registrations r
            JOIN workshops w ON r.workshop_id = w.id
            WHERE r.user_id        = ?
              AND r.payment_status IN ('approved', 'paid')
              AND w.timeslot       IS NOT NULL
        ");
        $stmt->execute([$userId]);
        $existing = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'timeslot');

        foreach ($existing as $existingTimeslot) {
            if (Workshop::timeslotsOverlap($timeslot, $existingTimeslot)) {
                return true;
            }
        }

        return false;
    }

    private function setStatus(int $registrationId, string $status): void
    {
        $this->db->prepare("UPDATE registrations SET payment_status = ? WHERE id = ?")
                 ->execute([$status, $registrationId]);
    }
}
