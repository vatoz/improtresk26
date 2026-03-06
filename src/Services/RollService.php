<?php
namespace App\Services;

use App\Models\MailQueue;
use App\Models\Workshop;
use PDO;

/**
 * RollService
 *
 * Fills workshop spots that opened up after the initial lottery by promoting
 * users who originally had a cancelled registration for that workshop.
 *
 * For each workshop with free spots (capacity > approved+paid count):
 *   - Find up to N cancelled registrations ordered by user priority.
 *   - For each candidate:
 *
 *     Case A – No approved/paid registration in an overlapping timeslot:
 *       → Set registration to 'approved', reset updated_at, enqueue approval mail.
 *
 *     Case B – Exactly one conflicting 'approved' (not paid) registration exists,
 *              and it has a higher priority number (= less preferred) than this one:
 *       → Swap the two statuses (approve this, cancel the other),
 *         enqueue an upgrade mail.
 *
 *     Otherwise → skip.
 */
class RollService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Run the roll for all workshops with free spots.
     *
     * @return int  Total number of registrations approved (Case A + Case B).
     */
    public function run(): int
    {
        $total = 0;

        foreach ($this->getWorkshopsWithFreeSpots() as $workshop) {
            $free       = (int) $workshop['free_spots'];
            $approved   = 0;

            foreach ($this->getCancelledCandidates($workshop['id'], $free) as $candidate) {
                if ($approved >= $free) {
                    break;
                }

                if ($this->processCandidate($candidate, $workshop)) {
                    $approved++;
                    $total++;
                }
            }
        }

        return $total;
    }

    // -------------------------------------------------------------------------
    // Core logic
    // -------------------------------------------------------------------------

    private function processCandidate(array $candidate, array $workshop): bool
    {
        $conflicts = $this->getTimeslotConflicts($candidate['user_id'], $workshop['timeslot']);

        if (empty($conflicts)) {
            // Case A: no timeslot conflict — approve directly
            $this->approve($candidate['reg_id']);
            $this->enqueueApprovedMail($candidate, $workshop);
            return true;
        }

        // Case B: exactly one approved (not paid) conflict with worse priority → offer upgrade
        if (count($conflicts) === 1) {
            $conflict = $conflicts[0];

            if (
                $conflict['payment_status'] === 'approved'
                && $this->isBetterPriority($candidate['priority'], $conflict['priority'])
            ) {
                $this->setUpgradable($candidate['reg_id'], $conflict['id']);
                $this->enqueueUpgradeMail($candidate, $workshop, $conflict);
                return true;
            }
        }

        return false;
    }

    // -------------------------------------------------------------------------
    // Queries
    // -------------------------------------------------------------------------

    /**
     * Workshops where approved+paid count is below capacity, ordered by fewest free spots
     * first (most urgent to fill).
     */
    private function getWorkshopsWithFreeSpots(): array
    {
        $stmt = $this->db->query("
            SELECT w.id, w.name, w.timeslot, w.capacity, w.price, w.date, w.time,
                   COUNT(r.id)                   AS approved_count,
                   (w.capacity - COUNT(r.id))    AS free_spots
            FROM workshops w
            LEFT JOIN registrations r
                   ON w.id = r.workshop_id
                  AND r.payment_status IN ('approved', 'paid', 'upgradable')
            WHERE w.is_active = 1
            GROUP BY w.id
            HAVING free_spots > 0
            ORDER BY free_spots ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cancelled registrations for the given workshop, in priority order (best first).
     * Capped at $limit rows — the number of currently free spots.
     */
    private function getCancelledCandidates(int $workshopId, int $limit): array
    {
        $stmt = $this->db->prepare("
            SELECT r.id   AS reg_id,
                   r.user_id,
                   r.priority,
                   u.name  AS user_name,
                   u.email
            FROM registrations r
            JOIN users u ON r.user_id = u.id
            WHERE r.workshop_id    = ?
              AND r.payment_status = 'cancelled'
            ORDER BY COALESCE(r.priority, 2147483647) ASC, r.created_at ASC
            LIMIT ?
        ");
        $stmt->execute([$workshopId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Returns approved/paid registrations for $userId whose workshop timeslot
     * overlaps with $timeslot.
     */
    private function getTimeslotConflicts(int $userId, ?string $timeslot): array
    {
        if (empty($timeslot)) {
            return [];
        }

        $stmt = $this->db->prepare("
            SELECT r.id, r.priority, r.payment_status,
                   w.name    AS workshop_name,
                   w.timeslot,
                   w.date,
                   w.time,
                   w.price
            FROM registrations r
            JOIN workshops w ON r.workshop_id = w.id
            WHERE r.user_id        = ?
              AND r.payment_status IN ('approved', 'paid')
              AND w.timeslot       IS NOT NULL
              AND w.timeslot REGEXP CONCAT('[', ?, ']')
        ");
        $stmt->execute([$userId, $timeslot]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // -------------------------------------------------------------------------
    // DB mutations
    // -------------------------------------------------------------------------

    /**
     * Approve a registration and explicitly reset updated_at so the 5-day
     * unpaid window starts from now.
     */
    private function approve(int $regId): void
    {
        $this->db->prepare("
            UPDATE registrations
            SET payment_status = 'approved', updated_at = NOW()
            WHERE id = ?
        ")->execute([$regId]);
    }

    /**
     * Mark $newRegId as 'upgradable' so the user can review the offer in their dashboard.
     * The conflicting approved registration is left untouched until the user decides.
     */
    private function setUpgradable(int $newRegId, int $conflictsWithRegId): void
    {
        $this->db->prepare("
            UPDATE registrations
            SET payment_status = 'upgradable', updated_at = NOW()
            WHERE id = ?
        ")->execute([$newRegId]);
    }

    // -------------------------------------------------------------------------
    // Mail
    // -------------------------------------------------------------------------

    private function enqueueApprovedMail(array $candidate, array $workshop): void
    {
        MailQueue::addWithTemplate(
            $this->db,
            $candidate['email'],
            'Uvolnilo se místo na workshop – Improtřesk 2026',
            'roll-approved.twig',
            [
                'name'     => $candidate['user_name'],
                'workshop' => $workshop,
            ]
        );
    }

    private function enqueueUpgradeMail(array $candidate, array $workshop, array $replaced): void
    {
        MailQueue::addWithTemplate(
            $this->db,
            $candidate['email'],
            'Upgrade workshopu – Improtřesk 2026',
            'roll-upgrade.twig',
            [
                'name'     => $candidate['user_name'],
                'workshop' => $workshop,
                'replaced' => $replaced,
            ]
        );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Returns true when $newPriority is strictly better (lower number) than
     * $existingPriority.  NULL is treated as the lowest possible priority.
     */
    private function isBetterPriority(?int $new, ?int $existing): bool
    {
        return ($new ?? PHP_INT_MAX) < ($existing ?? PHP_INT_MAX);
    }
}
