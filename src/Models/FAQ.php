<?php
namespace App\Models;

use PDO;

class FAQ
{
    /**
     * Get all active FAQ items
     *
     * @param PDO $db
     * @return array
     */
    public static function getAll(PDO $db): array
    {
        $stmt = $db->query("
            SELECT *
            FROM faq
            WHERE is_active = 1
            ORDER BY `order`, id
        ");
        return $stmt->fetchAll();
    }

    /**
     * Get FAQ items grouped by category
     *
     * @param PDO $db
     * @return array
     */
    public static function getGroupedByCategory(PDO $db): array
    {
        $stmt = $db->query("
            SELECT *
            FROM faq
            WHERE is_active = 1
            ORDER BY category, `order`, id
        ");
        $items = $stmt->fetchAll();

        $grouped = [];
        foreach ($items as $item) {
            $category = $item['category'] ?? 'Obecné';
            if (!isset($grouped[$category])) {
                $grouped[$category] = [];
            }
            $grouped[$category][] = $item;
        }

        return $grouped;
    }

    /**
     * Get FAQ items by category
     *
     * @param PDO $db
     * @param string $category
     * @return array
     */
    public static function getByCategory(PDO $db, string $category): array
    {
        $stmt = $db->prepare("
            SELECT *
            FROM faq
            WHERE is_active = 1 AND category = ?
            ORDER BY `order`, id
        ");
        $stmt->execute([$category]);
        return $stmt->fetchAll();
    }

    /**
     * Find FAQ item by ID
     *
     * @param PDO $db
     * @param int $id
     * @return array|false
     */
    public static function findById(PDO $db, int $id)
    {
        $stmt = $db->prepare("SELECT * FROM faq WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    /**
     * Create new FAQ item
     *
     * @param PDO $db
     * @param array $data
     * @return int
     */
    public static function create(PDO $db, array $data): int
    {
        $stmt = $db->prepare("
            INSERT INTO faq (question, answer, category, `order`, is_active)
            VALUES (?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $data['question'],
            $data['answer'],
            $data['category'] ?? null,
            $data['order'] ?? 0,
            $data['is_active'] ?? true
        ]);

        return (int) $db->lastInsertId();
    }

    /**
     * Update FAQ item
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

        $allowedFields = ['question', 'answer', 'category', 'order', 'is_active'];

        foreach ($data as $key => $value) {
            if (in_array($key, $allowedFields)) {
                $fields[] = "`$key` = ?";
                $values[] = $value;
            }
        }

        if (empty($fields)) {
            return false;
        }

        $values[] = $id;
        $sql = "UPDATE faq SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        return $stmt->execute($values);
    }

    /**
     * Delete FAQ item
     *
     * @param PDO $db
     * @param int $id
     * @return bool
     */
    public static function delete(PDO $db, int $id): bool
    {
        $stmt = $db->prepare("DELETE FROM faq WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
