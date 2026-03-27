<?php
namespace App\Models;

use PDO;

class Workshop
{
    /**
     * Get all active workshops
     *
     * @param PDO $db
     * @return array
     */
    public static function getAll(PDO $db): array
    {
        $stmt = $db->query("
            SELECT
                w.*,
                COUNT(r.id) as enrolled_count,
                (w.capacity - w.registered) as available_spots
            FROM workshops w
            LEFT JOIN registrations r ON w.id = r.workshop_id AND r.payment_status != 'cancelled'
            WHERE w.is_active = 1
            GROUP BY w.id
            ORDER BY  w.name
        ");
        return $stmt->fetchAll();
    }

    /**
     * Get all workshops (including inactive) with registration counts
     *
     * @param PDO $db
     * @return array
     */
    public static function getAllWithStats(PDO $db): array
    {
        $stmt = $db->query("
            SELECT
                w.*,
                COUNT(r.id) as enrolled_count,
                SUM(CASE WHEN r.payment_status = 'paid' THEN 1 ELSE 0 END) as paid_count,
                (w.capacity - COUNT(r.id)) as available_spots
            FROM workshops w
            LEFT JOIN registrations r ON w.id = r.workshop_id AND r.payment_status != 'cancelled'
            GROUP BY w.id
            ORDER BY w.timeslot, w.name
        ");
        return $stmt->fetchAll();
    }

    /**
     * Get active workshops for registration dropdown
     *
     * @param PDO $db
     * @return array
     */
    public static function getAvailableForRegistration(PDO $db): array
    {
        $stmt = $db->query("
            SELECT
                w.*,
                COUNT(r.id) as enrolled_count,
                (w.capacity - COUNT(r.id)) as available_spots
            FROM workshops w
            LEFT JOIN registrations r ON w.id = r.workshop_id AND r.payment_status != 'cancelled'
            WHERE w.is_active = 1
            GROUP BY w.id
            HAVING available_spots > 0
            ORDER BY w.timeslot, w.name
        ");
        return $stmt->fetchAll();
    }

    /**
     * Find workshop by ID
     *
     * @param PDO $db
     * @param int $id
     * @return array|false
     */
    public static function findById(PDO $db, int $id)
    {
        $stmt = $db->prepare("
            SELECT
                w.*,
                COUNT(r.id) as enrolled_count,
                (w.capacity - COUNT(r.id)) as available_spots
            FROM workshops w
            LEFT JOIN registrations r ON w.id = r.workshop_id AND r.payment_status != 'cancelled'
            WHERE w.id = ?
            GROUP BY w.id
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    /**
     * Create new workshop
     *
     * @param PDO $db
     * @param array $data
     * @return int Workshop ID
     */
    public static function create(PDO $db, array $data): int
    {
        $stmt = $db->prepare("
            INSERT INTO workshops (
                name, description, instructor,
                price, capacity, location, level, is_active, timeslot, registered
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $data['name'],
            $data['description'] ?? null,
            $data['instructor'] ?? null,
            $data['price'],
            $data['capacity'] ?? 20,
            $data['location'] ?? null,
            $data['level'] ?? 'all',
            $data['is_active'] ?? true,
            $data['timeslot'] ?? null,
            $data['registered'] ?? 0
        ]);

        return (int) $db->lastInsertId();
    }

    /**
     * Update workshop
     *
     * @param PDO $db
     * @param int $id
     * @param array $data
     * @return bool
     */
    public static function update(PDO $db, int $id, array $data): bool
    {
        $fields = [];
        $values = [];

        $allowedFields = [
            'name', 'description', 'instructor',
            'price', 'capacity', 'location', 'level', 'is_active', 'timeslot', 'registered'
        ];

        foreach ($data as $key => $value) {
            if (in_array($key, $allowedFields)) {
                $fields[] = "$key = ?";
                $values[] = $value;
            }
        }

        if (empty($fields)) {
            return false;
        }

        $values[] = $id;
        $sql = "UPDATE workshops SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        return $stmt->execute($values);
    }

    /**
     * Delete workshop
     *
     * @param PDO $db
     * @param int $id
     * @return bool
     */
    public static function delete(PDO $db, int $id): bool
    {
        $stmt = $db->prepare("DELETE FROM workshops WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Increment registered count by 1
     *
     * @param PDO $db
     * @param int $id
     * @return bool
     */
    public static function incrementRegistered(PDO $db, int $id): bool
    {
        $stmt = $db->prepare("UPDATE workshops SET registered = registered + 1 WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Decrement registered count by 1 (floor at 0)
     *
     * @param PDO $db
     * @param int $id
     * @return bool
     */
    public static function decrementRegistered(PDO $db, int $id): bool
    {
        $stmt = $db->prepare("UPDATE workshops SET registered = GREATEST(0, registered - 1) WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Check if workshop is full
     *
     * @param PDO $db
     * @param int $id
     * @return bool
     */
    public static function isFull(PDO $db, int $id): bool
    {
        $workshop = self::findById($db, $id);
        return $workshop && $workshop['available_spots'] <= 0;
    }

    
    /**
     * Get workshop level badge class
     *
     * @param string $level
     * @return string
     */
    public static function getLevelBadgeClass(string $level): string
    {
        $classes = [
            'beginner' => 'badge-success',
            'intermediate' => 'badge-warning',
            'advanced' => 'badge-danger',
            'all' => 'badge-info'
        ];
        return $classes[$level] ?? 'badge-secondary';
    }

    /**
     * Format workshop date and time
     *
     * @param string $date
     * @param string $time
     * @return string
     */
    public static function formatDateTime(string $date, string $time): string
    {
        $dateObj = new \DateTime($date . ' ' . $time);
        return $dateObj->format('j.n.Y H:i');
    }

    /**
     * Check if two timeslots overlap
     * E.g., "abc" and "bcd" overlap on "bc"
     *
     * @param string|null $timeslot1
     * @param string|null $timeslot2
     * @return bool
     */
    public static function timeslotsOverlap(?string $timeslot1, ?string $timeslot2): bool
    {
        if (empty($timeslot1) || empty($timeslot2)) {
            return false;
        }

        $chars1 = str_split($timeslot1);
        $chars2 = str_split($timeslot2);

        return !empty(array_intersect($chars1, $chars2));
    }

    /**
     * Get available workshops (capacity > registered) grouped by their timeslot code
     *
     * @param PDO $db
     * @return array  map of timeslot_code => workshop[]
     */
    public static function getAvailableGroupedByTimeslot(PDO $db): array
    {
        $stmt = $db->query("
            SELECT w.*,
                   COUNT(r.id) AS enrolled_count,
                   (w.capacity - COUNT(r.id)) AS available_spots
            FROM workshops w
            LEFT JOIN registrations r ON w.id = r.workshop_id AND r.payment_status != 'cancelled'
            WHERE w.is_active = 1
              AND w.timeslot IS NOT NULL
            GROUP BY w.id
            ORDER BY w.timeslot, w.date, w.time
        ");
        $rows = $stmt->fetchAll();

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row['timeslot']][] = $row;
        }
        return $grouped;
    }

    /**
     * Return distinct timeslot codes from workshops a user is currently registered for
     *
     * @param PDO $db
     * @param int $userId
     * @return string[]
     */
    public static function getUserRegisteredTimeslots(PDO $db, int $userId): array
    {
        $stmt = $db->prepare("
            SELECT DISTINCT w.timeslot
            FROM workshops w
            INNER JOIN registrations r ON w.id = r.workshop_id
            WHERE r.user_id = ?
              AND r.payment_status != 'cancelled'
              AND w.timeslot IS NOT NULL
        ");
        $stmt->execute([$userId]);
        return array_column($stmt->fetchAll(), 'timeslot');
    }

    /**
     * Return noncancelled user registrations
     *
     * @param PDO $db
     * @param int $userId
     * @return string[]
     */
    public static function getUserRegistrations(PDO $db, int $userId): array
    {
        $stmt = $db->prepare("
            SELECT * FROM  registrations r 
            WHERE r.user_id = ?
              AND r.payment_status != 'cancelled'
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }


    /**
     * Get workshops by timeslot filter
     * Returns workshops that have at least one matching timeslot character
     *
     * @param PDO $db
     * @param string $timeslot
     * @return array
     */
    public static function getByTimeslot(PDO $db, string $timeslot): array
    {
        $chars = str_split($timeslot);
        $placeholders = implode(',', array_fill(0, count($chars), '?'));

        $stmt = $db->prepare("
            SELECT
                w.*,
                COUNT(r.id) as enrolled_count,
                (w.capacity - COUNT(r.id)) as available_spots
            FROM workshops w
            LEFT JOIN registrations r ON w.id = r.workshop_id AND r.payment_status != 'cancelled'
            WHERE w.is_active = 1
            AND w.timeslot REGEXP CONCAT('[', ?, ']')
            GROUP BY w.id
            ORDER BY w.date, w.time
        ");

        $stmt->execute([$timeslot]);
        return $stmt->fetchAll();
    }

    /**
     * Get conflicting workshops for a user based on timeslot
     * Returns workshops user is registered for that share timeslot characters
     *
     * @param PDO $db
     * @param int $userId
     * @param string $timeslot
     * @return array
     */
    public static function getUserConflicts(PDO $db, int $userId, string $timeslot): array
    {
        if (empty($timeslot)) {
            return [];
        }

        $stmt = $db->prepare("
            SELECT w.*
            FROM workshops w
            INNER JOIN registrations r ON w.id = r.workshop_id
            WHERE r.user_id = ?
            AND r.payment_status != 'cancelled'
            AND w.timeslot IS NOT NULL
            AND w.timeslot REGEXP CONCAT('[', ?, ']')
        ");

        $stmt->execute([$userId, $timeslot]);
        return $stmt->fetchAll();
    }

    public static function register(PDO $db,$userId,$workshopId){
        
            $workshop = Workshop::findById($db, $workshopId);
            if (!$workshop || !$workshop['is_active']) {
                $_SESSION['error'] = 'Workshop nebyl nalezen.';
               return false;
            }

            // Duplicate registration check
            $stmt = $db->prepare("
                SELECT id FROM registrations WHERE user_id = ? AND workshop_id = ? and payment_status <> 'cancelled'
            ");
            $stmt->execute([$userId, $workshopId]);
            if ($stmt->fetch()) {
                $_SESSION['error'] = 'Na tento workshop jste již registrován/a.';                
                return false;
            }

            // Create registration
            $stmt = $db->prepare("
                INSERT INTO registrations (user_id, workshop_id, payment_status) VALUES (?, ?, 'pending')
            ");
            $stmt->execute([$userId, $workshopId]);

            $_SESSION['success'] = 'Byli jste úspěšně přihlášeni na workshop.';
            return true;
    }

    public static function unregister(PDO $db,int $userId,int $workshopId){
        $workshop = Workshop::findById($db, $workshopId);
        if (!$workshop || !$workshop['is_active']) {
            $_SESSION['error'] = 'Workshop '.$workshopId.' nebyl nalezen.';
            return false;
        }
    
                
        // Load the registration and verify it belongs to this user
        $stmt = $db->prepare("
            SELECT * FROM registrations WHERE workshop_id = ? AND user_id = ?
            and payment_status<>'paid' and payment_status<> 'cancelled'
        ");
        $stmt->execute([$workshopId, $userId]);
        $registration = $stmt->fetch();

        if (!$registration) {
            $_SESSION['error'] = 'Registrace nebyla nalezena.';
            return false;
        }


        $db->prepare("DELETE FROM  registrations  WHERE workshop_id = ? AND user_id = ? and payment_status<>'paid' and payment_status<>'cancelled'")
                 ->execute([$workshopId,$userId]);

        $_SESSION['success'] = 'Registrace byla úspěšně zrušena.';
        return true;


    }


}
