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
                (w.capacity - COUNT(r.id)) as available_spots
            FROM workshops w
            LEFT JOIN registrations r ON w.id = r.workshop_id AND r.payment_status != 'cancelled'
            WHERE w.is_active = 1
            GROUP BY w.id
            ORDER BY w.date, w.time
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
            ORDER BY w.date, w.time
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
            ORDER BY w.date, w.time
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
                name, description, instructor, date, time,
                duration_minutes, price, capacity, location, level, is_active
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $data['name'],
            $data['description'] ?? null,
            $data['instructor'] ?? null,
            $data['date'],
            $data['time'],
            $data['duration_minutes'] ?? 120,
            $data['price'],
            $data['capacity'] ?? 20,
            $data['location'] ?? null,
            $data['level'] ?? 'all',
            $data['is_active'] ?? true
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
            'name', 'description', 'instructor', 'date', 'time',
            'duration_minutes', 'price', 'capacity', 'location', 'level', 'is_active'
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
     * Get workshop level label
     *
     * @param string $level
     * @return string
     */
    public static function getLevelLabel(string $level): string
    {
        $labels = [
            'beginner' => 'Začátečníci',
            'intermediate' => 'Mírně pokročilí',
            'advanced' => 'Pokročilí',
            'all' => 'Všechny úrovně'
        ];
        return $labels[$level] ?? $level;
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
}
