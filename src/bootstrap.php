<?php
use Twig\Loader\FilesystemLoader;
use Twig\Environment;
use Dotenv\Dotenv;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Validate required environment variables
$dotenv->required(['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'])->notEmpty();

// DB connection using environment variables
$dsn = sprintf(
    "mysql:host=%s;dbname=%s;charset=utf8mb4",
    $_ENV['DB_HOST'],
    $_ENV['DB_NAME']
);

$db = new PDO($dsn, $_ENV['DB_USER'], $_ENV['DB_PASS'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

// Twig
$loader = new FilesystemLoader(__DIR__ . '/../templates');
$twig = new Environment($loader, [
    'debug' => $_ENV['APP_ENV'] === 'dev',
    'cache' => $_ENV['APP_ENV'] === 'production' ? __DIR__ . '/../var/cache/twig' : false,
]);

// Sessions
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
