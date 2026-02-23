<?php
/**
 * Shared helpers for migration scripts.
 */

/**
 * Ensure the settings table and the db_version key exist.
 * This is the bootstrap step that must run before any version check.
 */
function bootstrapSettings(PDO $db): void
{
    $db->exec("
        CREATE TABLE IF NOT EXISTS `settings` (
            `key` VARCHAR(100) NOT NULL,
            `value` TEXT NULL,
            PRIMARY KEY (`key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $db->exec("INSERT IGNORE INTO `settings` (`key`, `value`) VALUES ('db_version', '0')");
}

/**
 * Read the current DB version from the settings table.
 */
function getDbVersion(PDO $db): int
{
    $stmt = $db->query("SELECT `value` FROM `settings` WHERE `key` = 'db_version' LIMIT 1");
    $row  = $stmt->fetch();
    return $row ? (int) $row['value'] : 0;
}

/**
 * Persist the new DB version to the settings table.
 */
function setDbVersion(PDO $db, int $version): void
{
    $stmt = $db->prepare("UPDATE `settings` SET `value` = ? WHERE `key` = 'db_version'");
    $stmt->execute([$version]);
}

/**
 * Extract the numeric prefix from a migration filename.
 * E.g. "007_add_track.sql" → 7, "010_create_settings.sql" → 10.
 * Returns 0 if no prefix is found.
 */
function getMigrationNumber(string $file): int
{
    if (preg_match('/^(\d+)_/', basename($file), $m)) {
        return (int) $m[1];
    }
    return 0;
}

/**
 * Run all pending migrations (those with a number > current db_version).
 * Updates db_version after each successful migration.
 * Exits with code 1 on failure.
 *
 * @return int Number of migrations applied
 */
function runMigrations(PDO $db, string $migrationsPath): int
{
    bootstrapSettings($db);
    $currentVersion = getDbVersion($db);

    echo "  Current DB version: {$currentVersion}\n";

    $files = glob($migrationsPath . '/*.sql');
    sort($files);

    $pending = array_filter($files, fn($f) => getMigrationNumber($f) > $currentVersion);

    if (empty($pending)) {
        echo "  Nothing to migrate – database is up to date.\n";
        return 0;
    }

    $applied = 0;
    foreach ($pending as $file) {
        $num      = getMigrationNumber($file);
        $filename = basename($file);
        echo "  → [{$num}] {$filename}... ";

        try {
            $sql = file_get_contents($file);
            $db->exec($sql);
            setDbVersion($db, $num);
            $applied++;
            echo "✓\n";
        } catch (PDOException $e) {
            echo "✗ Failed\n";
            echo "  Error: " . $e->getMessage() . "\n";
            exit(1);
        }
    }

    echo "  DB version is now: " . getDbVersion($db) . "\n";
    return $applied;
}
