#!/usr/bin/env php
<?php
/**
 * Database Migration Runner
 *
 * Usage: php database/migrate.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/bootstrap.php';

function runMigrations(PDO $db, string $migrationsPath): void
{
    echo "Running database migrations...\n\n";

    // Get all migration files
    $files = glob($migrationsPath . '/*.sql');
    sort($files);

    if (empty($files)) {
        echo "No migration files found.\n";
        return;
    }

    foreach ($files as $file) {
        $filename = basename($file);
        echo "Running migration: {$filename}... ";

        try {
            $sql = file_get_contents($file);
            $db->exec($sql);
            echo "✓ Success\n";
        } catch (PDOException $e) {
            echo "✗ Failed\n";
            echo "Error: " . $e->getMessage() . "\n\n";
            exit(1);
        }
    }

    echo "\n✓ All migrations completed successfully!\n";
}

try {
    $migrationsPath = __DIR__ . '/migrations';
    runMigrations($db, $migrationsPath);
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
