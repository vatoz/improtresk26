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

    /**
     * Create password reset token
     *
     * @param PDO $db
     * @param string $email
     * @return string|false Token or false if user not found
     */
    public static function createPasswordResetToken(PDO $db, string $email)
    {
        // Check if user exists
        $user = self::findByEmail($db, $email);
        if (!$user) {
            return false;
        }

        // Generate token
        $token = bin2hex(random_bytes(32));

        // Delete any existing tokens for this email
        $stmt = $db->prepare("DELETE FROM password_resets WHERE email = ?");
        $stmt->execute([$email]);

        // Insert new token
        $stmt = $db->prepare("
            INSERT INTO password_resets (email, token, created_at)
            VALUES (?, ?, NOW())
        ");
        $stmt->execute([$email, $token]);

        return $token;
    }

    /**
     * Verify password reset token
     *
     * @param PDO $db
     * @param string $token
     * @return array|false Email if valid, false otherwise
     */
    public static function verifyPasswordResetToken(PDO $db, string $token)
    {
        $stmt = $db->prepare("
            SELECT email, created_at
            FROM password_resets
            WHERE token = ?
            LIMIT 1
        ");
        $stmt->execute([$token]);
        $reset = $stmt->fetch();

        if (!$reset) {
            return false;
        }

        // Check if token is expired (24 hours)
        $createdAt = new \DateTime($reset['created_at']);
        $now = new \DateTime();
        $diff = $now->getTimestamp() - $createdAt->getTimestamp();

        if ($diff > 86400) { // 24 hours in seconds
            return false;
        }

        return $reset['email'];
    }

    /**
     * Reset password using token
     *
     * @param PDO $db
     * @param string $token
     * @param string $newPassword
     * @return bool
     */
    public static function resetPassword(PDO $db, string $token, string $newPassword): bool
    {
        $email = self::verifyPasswordResetToken($db, $token);
        if (!$email) {
            return false;
        }

        // Update password
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE users SET password = ? WHERE email = ?");
        $result = $stmt->execute([$passwordHash, $email]);

        if ($result) {
            // Delete the used token
            $stmt = $db->prepare("DELETE FROM password_resets WHERE token = ?");
            $stmt->execute([$token]);
        }

        return $result;
    }

    /**
     * Clean up expired password reset tokens
     *
     * @param PDO $db
     * @return int Number of deleted tokens
     */
    public static function cleanupExpiredTokens(PDO $db): int
    {
        $stmt = $db->prepare("
            DELETE FROM password_resets
            WHERE created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $stmt->execute();
        return $stmt->rowCount();
    }
}
