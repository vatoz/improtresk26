<?php
namespace App\Models;

use PDO;

class Timeslot
{
    /**
     * Get all timeslots ordered by display order
     *
     * @param PDO $db
     * @return array
     */
    public static function getAll(PDO $db): array
    {
        $stmt = $db->query("
            SELECT * FROM timeslots
            ORDER BY `order`, code
        ");
        return $stmt->fetchAll();
    }

    /**
     * Get all timeslots as code => name map
     *
     * @param PDO $db
     * @return array
     */
    public static function getMap(PDO $db): array
    {
        $rows = self::getAll($db);
        $map = [];
        foreach ($rows as $row) {
            $map[$row['code']] = $row['name'];
        }
        return $map;
    }

    /**
     * Find timeslot by ID
     *
     * @param PDO $db
     * @param int $id
     * @return array|false
     */
    public static function findById(PDO $db, int $id)
    {
        $stmt = $db->prepare("SELECT * FROM timeslots WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    /**
     * Find timeslot by code
     *
     * @param PDO $db
     * @param string $code
     * @return array|false
     */
    public static function findByCode(PDO $db, string $code)
    {
        $stmt = $db->prepare("SELECT * FROM timeslots WHERE code = ?");
        $stmt->execute([$code]);
        return $stmt->fetch();
    }

    /**
     * Create new timeslot
     *
     * @param PDO $db
     * @param array $data
     * @return int Timeslot ID
     */
    public static function create(PDO $db, array $data): int
    {
        $stmt = $db->prepare("
            INSERT INTO timeslots (code, name, `order`)
            VALUES (?, ?, ?)
        ");

        $stmt->execute([
            strtoupper($data['code']),
            $data['name'],
            $data['order'] ?? 0
        ]);

        return (int) $db->lastInsertId();
    }

    /**
     * Update timeslot
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

        $allowedFields = ['code', 'name', 'order'];

        foreach ($data as $key => $value) {
            if (in_array($key, $allowedFields)) {
                $fields[] = "`$key` = ?";
                $values[] = ($key === 'code') ? strtoupper($value) : $value;
            }
        }

        if (empty($fields)) {
            return false;
        }

        $values[] = $id;
        $sql = "UPDATE timeslots SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        return $stmt->execute($values);
    }

    /**
     * Update display order for multiple timeslots at once
     * Expects array of ['id' => int, 'order' => int]
     *
     * @param PDO $db
     * @param array $items
     * @return void
     */
    public static function updateOrder(PDO $db, array $items): void
    {
        $stmt = $db->prepare("UPDATE timeslots SET `order` = ? WHERE id = ?");
        foreach ($items as $item) {
            $stmt->execute([(int) $item['order'], (int) $item['id']]);
        }
    }

    /**
     * Delete timeslot
     *
     * @param PDO $db
     * @param int $id
     * @return bool
     */
    public static function delete(PDO $db, int $id): bool
    {
        $stmt = $db->prepare("DELETE FROM timeslots WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
