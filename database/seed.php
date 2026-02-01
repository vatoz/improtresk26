#!/usr/bin/env php
<?php
/**
 * Database Seeder
 *
 * Usage: php database/seed.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/bootstrap.php';

function runSeeders(PDO $db, string $seedsPath): void
{
    echo "Running database seeders...\n\n";

    // Get all seed files
    $files = glob($seedsPath . '/*.sql');
    sort($files);

    if (empty($files)) {
        echo "No seed files found.\n";
        return;
    }

    foreach ($files as $file) {
        $filename = basename($file);
        echo "Running seeder: {$filename}... ";

        try {
            $sql = file_get_contents($file);
            $db->exec($sql);
            echo "✓ Success\n";
        } catch (PDOException $e) {
            echo "✗ Failed\n";
            echo "Error: " . $e->getMessage() . "\n\n";
            // Continue with other seeders even if one fails
            continue;
        }
    }

    echo "\n✓ All seeders completed!\n";
}

try {
    $seedsPath = __DIR__ . '/seeds';
    runSeeders($db, $seedsPath);
} catch (Exception $e) {
    echo "Seeding failed: " . $e->getMessage() . "\n";
    exit(1);
}
