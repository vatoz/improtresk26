<?php
namespace App\Models;

use PDO;

class ProgramItem
{
    /**
     * Get all active program items
     *
     * @param PDO $db
     * @return array
     */
    public static function getAll(PDO $db): array
    {
        $stmt = $db->query("
            SELECT *
            FROM program_items
            WHERE is_active = 1
            ORDER BY date, start_time
        ");
        return $stmt->fetchAll();
    }

    /**
     * Get program items by date
     *
     * @param PDO $db
     * @param string $date
     * @return array
     */
    public static function getByDate(PDO $db, string $date): array
    {
        $stmt = $db->prepare("
            SELECT *
            FROM program_items
            WHERE is_active = 1 AND date = ?
            ORDER BY start_time
        ");
        $stmt->execute([$date]);
        return $stmt->fetchAll();
    }

    /**
     * Get program items grouped by date
     *
     * @param PDO $db
     * @return array
     */
    public static function getGroupedByDate(PDO $db): array
    {
        $stmt = $db->query("
            SELECT *
            FROM program_items
            WHERE is_active = 1
            ORDER BY date, start_time, track
        ");
        $items = $stmt->fetchAll();

        $grouped = [];
        foreach ($items as $item) {
            $date = $item['date'];
            if (!isset($grouped[$date])) {
                $grouped[$date] = [];
            }
            $grouped[$date][] = $item;
        }

        return $grouped;
    }

    /**
     * Get program items grouped by date with track support
     * Returns structure: [date => [time_slot => ['full_width' => item, 'track_a' => item, 'track_b' => item]]]
     *
     * @param PDO $db
     * @return array
     */
    public static function getGroupedByDateAndTrack(PDO $db): array
    {
        $stmt = $db->query("
            SELECT *
            FROM program_items
            WHERE is_active = 1
            ORDER BY date, start_time, track
        ");
        $items = $stmt->fetchAll();

        $grouped = [];
        foreach ($items as $item) {
            $date = $item['date'];
            $timeSlot = $item['start_time'] . '-' . $item['end_time'];

            if (!isset($grouped[$date])) {
                $grouped[$date] = [];
            }

            if (!isset($grouped[$date][$timeSlot])) {
                $grouped[$date][$timeSlot] = [
                    'start_time' => $item['start_time'],
                    'end_time' => $item['end_time'],
                    'full_width' => null,
                    'track_a' => null,
                    'track_b' => null
                ];
            }

            // Assign item to appropriate track
            if ($item['track'] === null) {
                $grouped[$date][$timeSlot]['full_width'] = $item;
            } elseif ($item['track'] === 'A') {
                $grouped[$date][$timeSlot]['track_a'] = $item;
            } elseif ($item['track'] === 'B') {
                $grouped[$date][$timeSlot]['track_b'] = $item;
            }
        }

        return $grouped;
    }

    /**
     * Find program item by ID
     *
     * @param PDO $db
     * @param int $id
     * @return array|false
     */
    public static function findById(PDO $db, int $id)
    {
        $stmt = $db->prepare("SELECT * FROM program_items WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    /**
     * Create new program item
     *
     * @param PDO $db
     * @param array $data
     * @return int
     */
    public static function create(PDO $db, array $data): int
    {
        $stmt = $db->prepare("
            INSERT INTO program_items (
                title, description, performer, type, date,
                start_time, end_time, location, is_free, max_capacity, image_url, is_active
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $data['title'],
            $data['description'] ?? null,
            $data['performer'] ?? null,
            $data['type'] ?? 'performance',
            $data['date'],
            $data['start_time'],
            $data['end_time'],
            $data['location'] ?? null,
            $data['is_free'] ?? false,
            $data['max_capacity'] ?? null,
            $data['image_url'] ?? null,
            $data['is_active'] ?? true
        ]);

        return (int) $db->lastInsertId();
    }

    /**
     * Get type label
     *
     * @param string $type
     * @return string
     */
    public static function getTypeLabel(string $type): string
    {
        $labels = [
            'performance' => 'Představení',
            'workshop' => 'Workshop',
            'discussion' => 'Diskuse',
            'party' => 'Párty',
            'other' => 'Jiné'
        ];
        return $labels[$type] ?? $type;
    }
}
