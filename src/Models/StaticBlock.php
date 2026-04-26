<?php
namespace App\Models;

use PDO;

class StaticBlock
{
    public static function getAll(PDO $db): array
    {
        $stmt = $db->query("
            SELECT *
            FROM static_blocks
            WHERE is_active = 1
            ORDER BY block_name
        ");
        return loadImages($stmt->fetchAll());
    }

    
    public static function getByName(PDO $db, string $blockName): array|false
    {
        $stmt = $db->prepare("
            SELECT *
            FROM static_blocks
            WHERE block_name = ? AND is_active = 1
        ");
        $stmt->execute([$blockName]);
        $data=loadImages([$stmt->fetch()]);
        return $data[0];
    }

    /**
     * Return all active blocks whose block_name starts with the given prefix.
     * E.g. getByPrefix($db, 'info_') returns all blocks named info_*.
     */
    public static function getByPrefix(PDO $db, string $prefix): array
    {
        $stmt = $db->prepare("
            SELECT *
            FROM static_blocks
            WHERE block_name LIKE ? AND is_active = 1
            ORDER BY block_name
        ");
        
        $stmt->execute([str_replace(['%', '_'], ['\\%', '\\_'], $prefix) . '%']);
        return loadImages($stmt->fetchAll());
    }

    public static function findById(PDO $db, int $id): array|false
    {
        $stmt = $db->prepare("SELECT * FROM static_blocks WHERE id = ?");
        $stmt->execute([$id]);
        $data=loadImages([$stmt->fetch()]);
        return $data[0];
    }

    public static function create(PDO $db, array $data): int
    {
        $stmt = $db->prepare("
            INSERT INTO static_blocks (block_name, title, block_description, content, is_active)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['block_name'],
            $data['title'],
            $data['block_description'] ?? null,
            $data['content'] ?? null,
            $data['is_active'] ?? 1,
        ]);
        return (int) $db->lastInsertId();
    }

    public static function update(PDO $db, int $id, array $data): bool
    {
        $stmt = $db->prepare("
            UPDATE static_blocks
            SET block_name = ?, title = ?, block_description = ?, content = ?, is_active = ?
            WHERE id = ?
        ");
        return $stmt->execute([
            $data['block_name'],
            $data['title'],
            $data['block_description'] ?? null,
            $data['content'] ?? null,
            $data['is_active'] ?? 1,
            $id,
        ]);
    }

    public static function delete(PDO $db, int $id): bool
    {
        $stmt = $db->prepare("DELETE FROM static_blocks WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
