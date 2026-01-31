#!/usr/bin/env php
<?php
/**
 * Complete Database Setup Script
 *
 * Runs migrations and seeds in one command
 * Usage: php database/setup.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/bootstrap.php';

echo "═══════════════════════════════════════════════════════\n";
echo "  Improtřesk 2026 - Database Setup\n";
echo "═══════════════════════════════════════════════════════\n\n";

// Run migrations
echo "STEP 1: Running migrations...\n";
echo "───────────────────────────────────────────────────────\n";

$migrationsPath = __DIR__ . '/migrations';
$files = glob($migrationsPath . '/*.sql');
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
        echo "  Error: " . $e->getMessage() . "\n";
        exit(1);
    }
}

echo "\n✓ Migrations completed!\n\n";

// Run seeds
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
        // Continue with other seeds
        continue;
    }
}

echo "\n✓ Seeds completed!\n\n";
echo "═══════════════════════════════════════════════════════\n";
echo "  Database setup completed successfully!\n";
echo "═══════════════════════════════════════════════════════\n\n";
echo "Default users created:\n";
echo "  Admin: admin@improtresk.cz / password\n";
echo "  User:  user@improtresk.cz / password\n\n";
