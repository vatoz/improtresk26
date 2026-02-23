#!/usr/bin/env php
<?php
/**
 * Complete Database Setup Script
 *
 * Runs migrations (version-aware) and seeds in one command.
 * Usage: php database/setup.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/migration_helpers.php';

echo "═══════════════════════════════════════════════════════\n";
echo "  Improtřesk 2026 - Database Setup\n";
echo "═══════════════════════════════════════════════════════\n\n";

// ── Step 1: Migrations ──────────────────────────────────────
echo "STEP 1: Running migrations...\n";
echo "───────────────────────────────────────────────────────\n";

try {
    $applied = runMigrations($db, __DIR__ . '/migrations');
    echo "\n✓ Migrations completed" . ($applied > 0 ? " ({$applied} applied)." : " (already up to date).") . "\n\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}

// ── Step 2: Seeds ───────────────────────────────────────────
echo "STEP 2: Running seeds...\n";
echo "───────────────────────────────────────────────────────\n";

$seedsPath = __DIR__ . '/seeds';
$files = glob($seedsPath . '/*.sql');
sort($files);

foreach ($files as $file) {
    $filename = basename($file);
    echo "  → {$filename}... ";

    try {
        $sql = file_get_contents($file);
        $db->exec($sql);
        echo "✓\n";
    } catch (PDOException $e) {
        echo "✗\n";
        echo "  Warning: " . $e->getMessage() . "\n";
        // Non-fatal – continue with remaining seeds
    }
}

echo "\n✓ Seeds completed!\n\n";
echo "═══════════════════════════════════════════════════════\n";
echo "  Database setup completed successfully!\n";
echo "═══════════════════════════════════════════════════════\n\n";
echo "Default users created:\n";
echo "  Admin: admin@improtresk.cz / password\n";
echo "  User:  user@improtresk.cz / password\n\n";
