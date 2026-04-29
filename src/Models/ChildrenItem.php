<?php
namespace App\Models;

use PDO;

class ChildrenItem
{
    public static function getAll(PDO $db): array
    {
        $stmt = $db->query("
            SELECT * FROM children_items
            WHERE is_active = 1
            ORDER BY date, start_time
        ");
        return  loadImages( $stmt->fetchAll(),"d"); ;
    }

    
}
