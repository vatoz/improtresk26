#!/usr/bin/env php
<?php
/**
 * Database Migration Runner
 *
 * Runs only migrations newer than the version stored in settings.db_version.
 * Usage: php database/migrate.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/migration_helpers.php';

echo "Running database migrations...\n\n";

try {
    $applied = runMigrations($db, __DIR__ . '/migrations');
    echo "\n✓ Done" . ($applied > 0 ? " – {$applied} migration(s) applied." : " – nothing to do.") . "\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
