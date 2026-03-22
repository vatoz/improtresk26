<?php
namespace App\Models;

use PDO;

class UserQuestion
{
    public static function getAllActive(PDO $db): array
    {
        $stmt = $db->query("
            SELECT * FROM user_questions
            WHERE is_active = 1
            ORDER BY `order` ASC, id ASC
        ");
        return $stmt->fetchAll();
    }

    public static function getWithAnswersForUser(PDO $db, int $userId): array
    {
        $stmt = $db->prepare("
            SELECT q.id, q.question, q.question_name, q.description, q.type,
                   ua.value, ua.updated_at
            FROM user_questions q
            LEFT JOIN user_answers ua ON ua.question_id = q.id AND ua.user_id = ?
            WHERE q.is_active = 1
            ORDER BY q.`order` ASC, q.id ASC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
}
