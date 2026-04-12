<?php
namespace App\Models;

use PDO;

class ProgramItem
{
    /**
     * Get all active program items
     *
     * @param PDO $db
     * @return array
     */
    public static function getAll(PDO $db): array
    {
        $stmt = $db->query("
            SELECT *
            FROM program_items
            WHERE is_active = 1
            ORDER BY date, start_time
        ");
        return $stmt->fetchAll();
    }

    /**
     * Get program items by date
     *
     * @param PDO $db
     * @param string $date
     * @return array
     */
    public static function getByDate(PDO $db, string $date): array
    {
        $stmt = $db->prepare("
            SELECT *
            FROM program_items
            WHERE is_active = 1 AND date = ?
            ORDER BY start_time
        ");
        $stmt->execute([$date]);
        return $stmt->fetchAll();
    }

    /**
     * Get program items grouped by date
     *
     * @param PDO $db
     * @return array
     */
    public static function getGroupedByDate(PDO $db): array
    {
        $stmt = $db->query("
            SELECT *
            FROM program_items
            WHERE is_active = 1
            ORDER BY date, start_time, track
        ");
        $items = $stmt->fetchAll();

        $grouped = [];
        foreach ($items as $item) {
            $date = $item['date'];
            if (!isset($grouped[$date])) {
                $grouped[$date] = [];
            }
            $grouped[$date][] = $item;
        }

        return $grouped;
    }

    /**
     * Get program items grouped by date with track support.
     * Overlapping track items (across A/B) are merged into a single slot so that
     * time offsets can be rendered as proportional spacers.
     *
     * Returns: [date => [ [start_time, end_time, total_mins, full_width, track_a, track_b], ... ]]
     * track_a / track_b each have: {item, start_offset_mins, end_offset_mins, item_mins}
     *
     * @param PDO $db
     * @return array
     */
    public static function getGroupedByDateAndTrack(PDO $db): array
    {
        $stmt = $db->query("
            SELECT *
            FROM program_items
            WHERE is_active = 1
            ORDER BY date, start_time, track
        ");
        $items = $stmt->fetchAll();

        // Bucket by date
        $byDate = [];
        foreach ($items as $item) {
            $byDate[$item['date']][] = $item;
        }

        $grouped = [];
        foreach ($byDate as $date => $dayItems) {
            $trackItems = array_values(array_filter($dayItems, fn($i) => $i['track'] !== null));
            $fullItems  = array_values(array_filter($dayItems, fn($i) => $i['track'] === null));

            // Full-width items become standalone slots
            $allSlots = [];
            foreach ($fullItems as $item) {
                $allSlots[] = [
                    'start_time' => $item['start_time'],
                    'end_time'   => $item['end_time'],
                    'total_mins' => self::timeDiffMinutes($item['start_time'], $item['end_time']),
                    'full_width' => $item,
                    'track_a'    => ['item' => null, 'start_offset_mins' => 0, 'end_offset_mins' => 0, 'item_mins' => 0],
                    'track_b'    => ['item' => null, 'start_offset_mins' => 0, 'end_offset_mins' => 0, 'item_mins' => 0],
                ];
            }

            // Group overlapping track items into shared slots
            $groups = [];
            foreach ($trackItems as $item) {
                $trackKey      = 'track_' . strtolower((string)$item['track']); // track_a | track_b
                $otherTrackKey = ($trackKey === 'track_a') ? 'track_b' : 'track_a';
                $placed = false;

                foreach ($groups as &$group) {
                    // Slot is free for this track AND the item overlaps the group's time window
                    if ($group[$trackKey]['item'] === null
                        && $item['start_time'] < $group['end_time']
                        && $item['end_time']   > $group['start_time']
                    ) {
                        $group[$trackKey] = ['item' => $item];
                        if ($item['start_time'] < $group['start_time']) {
                            $group['start_time'] = $item['start_time'];
                        }
                        if ($item['end_time'] > $group['end_time']) {
                            $group['end_time'] = $item['end_time'];
                        }
                        $placed = true;
                        break;
                    }
                }
                unset($group);

                if (!$placed) {
                    $groups[] = [
                        'start_time' => $item['start_time'],
                        'end_time'   => $item['end_time'],
                        'full_width' => null,
                        $trackKey      => ['item' => $item],
                        $otherTrackKey => ['item' => null],
                    ];
                }
            }

            // Resolve offsets and item_mins for every group
            foreach ($groups as &$group) {
                $gs        = $group['start_time'];
                $ge        = $group['end_time'];
                $totalMins = self::timeDiffMinutes($gs, $ge);
                $group['total_mins'] = $totalMins;

                foreach (['track_a', 'track_b'] as $tk) {
                    $it = $group[$tk]['item'];
                    if ($it !== null) {
                        $startOff = self::timeDiffMinutes($gs, $it['start_time']);
                        $endOff   = self::timeDiffMinutes($it['end_time'], $ge);
                        $itemMins = self::timeDiffMinutes($it['start_time'], $it['end_time']);
                        $group[$tk] = [
                            'item'               => $it,
                            'start_offset_mins'  => $startOff,
                            'end_offset_mins'    => $endOff,
                            'item_mins'          => $itemMins,
                        ];
                    } else {
                        $group[$tk] = [
                            'item'               => null,
                            'start_offset_mins'  => 0,
                            'end_offset_mins'    => 0,
                            'item_mins'          => $totalMins,
                        ];
                    }
                }
            }
            unset($group);

            // Merge full-width slots with track groups, order by start time
            $allSlots = array_merge($allSlots, $groups);
            usort($allSlots, fn($a, $b) => strcmp($a['start_time'], $b['start_time']));

            $grouped[$date] = $allSlots;
        }

        return $grouped;
    }

    /**
     * Return the number of whole minutes between two HH:MM[:SS] time strings.
     */
    private static function timeDiffMinutes(string $from, string $to): int
    {
        [$fh, $fm] = explode(':', $from);
        [$th, $tm] = explode(':', $to);
        return max(0, (int)$th * 60 + (int)$tm - ((int)$fh * 60 + (int)$fm));
    }

    /**
     * Find program item by ID
     *
     * @param PDO $db
     * @param int $id
     * @return array|false
     */
    public static function findById(PDO $db, int $id)
    {
        $stmt = $db->prepare("SELECT * FROM program_items WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    /**
     * Create new program item
     *
     * @param PDO $db
     * @param array $data
     * @return int
     */
    public static function create(PDO $db, array $data): int
    {
        $stmt = $db->prepare("
            INSERT INTO program_items (
                title, description, performer, type, date,
                start_time, end_time, location, is_free, max_capacity, image_url, is_active
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $data['title'],
            $data['description'] ?? null,
            $data['performer'] ?? null,
            $data['type'] ?? 'performance',
            $data['date'],
            $data['start_time'],
            $data['end_time'],
            $data['location'] ?? null,
            $data['is_free'] ?? false,
            $data['max_capacity'] ?? null,
            $data['image_url'] ?? null,
            $data['is_active'] ?? true
        ]);

        return (int) $db->lastInsertId();
    }

    /**
     * Get type label
     *
     * @param string $type
     * @return string
     */
    public static function getTypeLabel(string $type): string
    {
        $labels = [
            'performance' => 'Představení',
            'workshop' => 'Workshop',
            'discussion' => 'Diskuse',
            'party' => 'Párty',
            'other' => 'Jiné'
        ];
        return $labels[$type] ?? $type;
    }
}
