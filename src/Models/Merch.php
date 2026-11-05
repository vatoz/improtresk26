<?php
namespace App\Models;

use PDO;

class Merch
{
    /**
     * Active merch items with photo and computed sale state:
     * 'state' = 'available' | 'soldout' | 'ended'.
     */
    public static function getActive(PDO $db): array
    {
        self::recountStock($db);
        $items = $db->query("SELECT * FROM merch WHERE is_active = 1 ORDER BY `order`, name")->fetchAll();
        foreach ($items as &$item) {
            $item['state'] = self::saleState($item);
        }
        return loadImages($items, 'm');
    }

    /**
     * Active merch grouped into "chapters" by parent_merch. Each group:
     *   ['id', 'name', 'description', 'photo', 'variants' => [...], 'state']
     * The header (name/description/photo) comes from the parent record, even if the
     * parent itself is inactive (then it is only a header, not a sellable variant).
     * Group state = 'available' if any variant is available, else 'soldout'/'ended'.
     */
    public static function getActiveGrouped(PDO $db): array
    {
        $variants = self::getActive($db);

        $groupIds = [];
        foreach ($variants as $v) {
            $groupIds[(int) ($v['parent_merch'] ?: $v['id'])] = true;
        }
        if (!$groupIds) {
            return [];
        }

        $in = implode(',', array_fill(0, count($groupIds), '?'));
        $stmt = $db->prepare("SELECT * FROM merch WHERE id IN ($in)");
        $stmt->execute(array_keys($groupIds));
        $parents = [];
        foreach (loadImages($stmt->fetchAll(), 'm') as $p) {
            $parents[(int) $p['id']] = $p;
        }

        $groups = [];
        foreach ($variants as $v) {
            $gid = (int) ($v['parent_merch'] ?: $v['id']);
            $parent = $parents[$gid] ?? $v;   // orphan (missing parent) = own group
            if (!isset($groups[$gid])) {
                $groups[$gid] = [
                    'id'          => $gid,
                    'name'        => $parent['name'],
                    'order'       => (int) $parent['order'],
                    'description' => $parent['description'],
                    'photo'       => $parent['photo'] ?? null,
                    'variants'    => [],
                ];
            }
            $v['is_parent'] = ((int) $v['id'] === $gid);
            $groups[$gid]['variants'][] = $v;
        }

        foreach ($groups as &$g) {
            // by `order`, then by name
            usort($g['variants'], fn($a, $b) =>
                [(int) $a['order'], $a['name']] <=> [(int) $b['order'], $b['name']]);
            $states = array_column($g['variants'], 'state');
            $g['state'] = in_array('available', $states, true) ? 'available'
                : (in_array('soldout', $states, true) ? 'soldout' : 'ended');
            $g['photo'] ??= array_values(array_filter(array_column($g['variants'], 'photo')))[0] ?? null;
        }
        unset($g);

        usort($groups, fn($a, $b) => [$a['order'], $a['name']] <=> [$b['order'], $b['name']]);
        return $groups;
    }

    public static function saleState(array $item): string
    {
        if ($item['sale_until'] !== null && $item['sale_until'] < date('Y-m-d')) {
            return 'ended';
        }
        if ($item['stock_total'] !== null && (int) $item['stock_available'] <= 0) {
            return 'soldout';
        }
        return 'available';
    }

    /**
     * Recompute stock_available = stock_total − ordered quantity (pending + paid).
     * Without $merchId recounts all items; items without a limit get NULL.
     */
    public static function recountStock(PDO $db, int $merchId = 0): void
    {
        $sql = "
            UPDATE merch m
            SET m.stock_available = IF(m.stock_total IS NULL, NULL,
                CAST(m.stock_total AS SIGNED) - (
                    SELECT COALESCE(SUM(p.quantity), 0) FROM purchases p
                    WHERE p.item_type = 'merch' AND p.item_id = m.id
                      AND p.payment_status != 'cancelled'
                ))
        ";
        if ($merchId > 0) {
            $db->prepare($sql . " WHERE m.id = ?")->execute([$merchId]);
        } else {
            $db->exec($sql);
        }
    }

    /** Recount the merch item a purchase belongs to (no-op for tickets). */
    public static function recountForPurchase(PDO $db, int $purchaseId): void
    {
        $stmt = $db->prepare("SELECT item_id FROM purchases WHERE id = ? AND item_type = 'merch'");
        $stmt->execute([$purchaseId]);
        $merchId = (int) $stmt->fetchColumn();
        if ($merchId) {
            self::recountStock($db, $merchId);
        }
    }
}
