<?php
namespace App\Models;

use PDO;

class Person
{
    public static function getAll(PDO $db): array
    {
        $stmt = $db->query("
            SELECT p.*, w.name AS workshop_name
            FROM people p
            LEFT JOIN workshops w ON p.workshop_id = w.id
            WHERE p.is_active = 1
            ORDER BY p.section, p.`order`, p.name
        ");
        return $stmt->fetchAll();
    }

    public static function getGroupedBySection(PDO $db): array
    {
        $items = self::getAll($db);

        $grouped = [];
        foreach ($items as $item) {
            $section = $item['section'];
            if (!isset($grouped[$section])) {
                $grouped[$section] = [];
            }
            $grouped[$section][] = $item;
        }

        return $grouped;
    }
}
