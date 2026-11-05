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

function fixCzechOrphans(string $text): string
{    return preg_replace( '/\s([sSzZvVkKaAiI])\s/u', ' $1&nbsp;', $text   ); }

$twig->addFilter(new \Twig\TwigFilter('cz_nbsp', 'fixCzechOrphans', ['is_safe' => ['html']]));

function trimStartZero(string $text): string
{   if(substr($text,0,1)=="0"){return substr($text,1,22000);}else{return $text;}}

$twig->addFilter(new \Twig\TwigFilter('trimStartZero', 'trimStartZero', ['is_safe' => ['html']]));
$twig->addGlobal('merch_enabled', merch_enabled());




// Sessions
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cart icon in navigation: number of unpaid items + "cart just changed" flag (set by
// BaseController::markCartChanged() before a redirect; consumed by the next full page load)
if (!empty($_SESSION['user']['id'])) {
    $twig->addGlobal('cart_count', cart_count($db, (int) $_SESSION['user']['id']));

    $isPageLoad = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET'
        && str_contains($_SERVER['HTTP_ACCEPT'] ?? 'text/html', 'text/html');
    if ($isPageLoad && !empty($_SESSION['cart_changed'])) {
        unset($_SESSION['cart_changed']);
        $twig->addGlobal('cart_changed', true);
    }
}

//ini_set('error_log', '../var/err.log');
