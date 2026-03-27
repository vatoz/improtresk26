<?php
namespace App\Models;

use PDO;

class Person
{
    public static function getAll(PDO $db): array
    {
        $stmt = $db->query("
            SELECT * FROM people
            WHERE is_active = 1
            ORDER BY section, `order`, name
        ");
        $people = $stmt->fetchAll();

        // Fetch all workshop links
        $stmt = $db->query("
            SELECT pw.person_id, pw.workshop_id, w.name AS workshop_name
            FROM people_workshops pw
            JOIN workshops w ON pw.workshop_id = w.id
        ");
        $links = $stmt->fetchAll();

        $workshopsByPerson = [];
        foreach ($links as $link) {
            $workshopsByPerson[$link['person_id']][] = [
                'id'   => $link['workshop_id'],
                'name' => $link['workshop_name'],
            ];
        }

        foreach ($people as &$person) {
            $person['workshops'] = $workshopsByPerson[$person['id']] ?? [];
        }

        return $people;
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

    public static function getGroupedByWorkshop(PDO $db): array
    {
        $stmt = $db->query("
            SELECT pw.workshop_id, p.*
            FROM people_workshops pw
            JOIN people p ON pw.person_id = p.id
            WHERE p.is_active = 1
            ORDER BY pw.`order`, p.section, p.`order`, p.name
        ");
        $rows = $stmt->fetchAll();

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row['workshop_id']][] = $row;
        }
        return $grouped;
    }
}
