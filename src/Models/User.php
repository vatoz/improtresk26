<?php
namespace App\Models;

use PDO;

class User
{
    /**
     * Find user by email
     *
     * @param PDO $db
     * @param string $email
     * @return array|false
     */
    public static function findByEmail(PDO $db, string $email)
    {
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        return $stmt->fetch();
    }

    /**
     * Find user by ID
     *
     * @param PDO $db
     * @param int $id
     * @return array|false
     */
    public static function findById(PDO $db, int $id)
    {
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    /**
     * Create new user
     *
     * @param PDO $db
     * @param string $name
     * @param string $email
     * @param string $passwordHash
     * @param string $role
     * @return int User ID
     */
    public static function create(PDO $db, string $name, string $email, string $passwordHash, string $role = 'user'): int
    {
        $stmt = $db->prepare("
            INSERT INTO users (name, email, password, role, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$name, $email, $passwordHash, $role]);
        return (int) $db->lastInsertId();
    }

    /**
     * Update user
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

        foreach ($data as $key => $value) {
            $fields[] = "$key = ?";
            $values[] = $value;
        }
        $values[] = $id;

        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        return $stmt->execute($values);
    }

    /**
     * Delete user
     *
     * @param PDO $db
     * @param int $id
     * @return bool
     */
    public static function delete(PDO $db, int $id): bool
    {
        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
