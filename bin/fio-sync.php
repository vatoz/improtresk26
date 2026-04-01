<?php
/**
 * CLI-only script: fetches latest Fio bank transactions and stores them in the DB.
 * Usage: php bin/fio-sync.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Access denied.' . PHP_EOL);
}

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use App\Services\FioService;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// DB connection
$dsn = sprintf(
    'mysql:host=%s;dbname=%s;charset=utf8mb4',
    $_ENV['DB_HOST'],
    $_ENV['DB_NAME']
);

$db = new PDO($dsn, $_ENV['DB_USER'], $_ENV['DB_PASS'], [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
]);

try {
    $result = (new FioService($db))->fetchAndStore();

    echo sprintf(
        "OK: log uložen do %s | vloženo: %d | přeskočeno: %d%s",
        $result['file'],
        $result['inserted'],
        $result['skipped'],
        PHP_EOL
    );
} catch (\RuntimeException $e) {
    echo 'CHYBA: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}
