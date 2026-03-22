<?php
namespace App\Models;

use PDO;

class UserAnswer
{
    public static function upsert(PDO $db, int $userId, int $questionId, string $value): void
    {
        $stmt = $db->prepare("
            INSERT INTO user_answers (user_id, question_id, value, updated_at)
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = NOW()
        ");
        $stmt->execute([$userId, $questionId, $value]);
    }
}
