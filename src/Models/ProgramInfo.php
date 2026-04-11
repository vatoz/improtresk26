<?php
namespace App\Models;

use PDO;

class ProgramInfo
{
    public static function getAll(PDO $db): array
    {
        $stmt = $db->query("
            SELECT pi.*, pr.title AS program_item_title, pr.date, pr.start_time, pr.end_time, pr.location
            FROM program_info pi
            LEFT JOIN program_items pr ON pi.program_item_id = pr.id
            WHERE pi.is_active = 1
            ORDER BY pi.`order`, pi.name
        ");
        return $stmt->fetchAll();
    }

    public static function getGroupedByGroup(PDO $db): array
    {
        $items = self::getAll($db);
        $grouped = [];
        foreach ($items as $item) {
            $group = $item['description_group'];
            if (!isset($grouped[$group])) {
                $grouped[$group] = [];
            }
            $grouped[$group][] = $item;
        }
        return $grouped;
    }
}
