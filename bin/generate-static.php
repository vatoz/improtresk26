<?php
/**
 * CLI-only script: generates a static HTML mirror of the public site into _static/.
 *
 * - Renders public-facing Twig pages via the existing controllers.
 * - Copies every non-PHP file from public/ into _static/.
 * - Writes an .htaccess so /workshops, /program, ... are served from .html files.
 *
 * Usage: php bin/generate-static.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Access denied.' . PHP_EOL);
}

use Dotenv\Dotenv;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use App\Controllers\DefaultController;
use App\Controllers\WorkshopController;

require_once __DIR__ . '/../vendor/autoload.php';

$root      = dirname(__DIR__);
$publicDir = $root . '/public';
$outDir    = $root . '/_static';

// --- Bootstrap (mirrors src/bootstrap.php, minus session/cron) ---------------

$dotenv = Dotenv::createImmutable($root);
$dotenv->load();
$dotenv->required(['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'])->notEmpty();

$dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $_ENV['DB_HOST'], $_ENV['DB_NAME']);
$db  = new PDO($dsn, $_ENV['DB_USER'], $_ENV['DB_PASS'], [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
]);

// Twig filters from src/bootstrap.php (kept here so we don't trigger session_start in CLI)
if (!function_exists('fixCzechOrphans')) {
    function fixCzechOrphans(string $text): string {
        return preg_replace('/\s([sSzZvVkKaAiI])\s/u', ' $1&nbsp;', $text);
    }
}
if (!function_exists('trimStartZero')) {
    function trimStartZero(string $text): string {
        return substr($text, 0, 1) === '0' ? substr($text, 1, 22000) : $text;
    }
}

$twig = new Environment(new FilesystemLoader($root . '/templates'), [
    'debug' => false,
    'cache' => false,
]);
$twig->addFilter(new \Twig\TwigFilter('cz_nbsp', 'fixCzechOrphans', ['is_safe' => ['html']]));
$twig->addFilter(new \Twig\TwigFilter('trimStartZero', 'trimStartZero', ['is_safe' => ['html']]));

// Sessions are not started in CLI; expose an empty array so csrf_token() / getCurrentUser() work.
$_SESSION = $_SESSION ?? [];

// --- Output directory --------------------------------------------------------

if (!is_dir($outDir) && !mkdir($outDir, 0775, true) && !is_dir($outDir)) {
    fwrite(STDERR, "Cannot create $outDir\n");
    exit(1);
}

// --- 1. Render public pages --------------------------------------------------

$default  = new DefaultController($twig, $db);
$workshop = new WorkshopController($twig, $db);

$pages = [
    'index'       => [$default,  'index'],
    'workshops'   => [$workshop, 'index'],
    'program'     => [$default,  'program'],
    'harmonogram' => [$default,  'harmonogram'],
    'children'    => [$default,  'children'],
    'info'        => [$default,  'info'],
    'medailonky'  => [$default,  'medailonky'],
    'faq'         => [$default,  'faq'],
    'contact'     => [$default,  'contact'],
];

foreach ($pages as $slug => [$controller, $method]) {
    ob_start();
    try {
        $controller->$method();
    } catch (\Throwable $e) {
        ob_end_clean();
        fwrite(STDERR, sprintf("FAIL %s: %s\n", $slug, $e->getMessage()));
        exit(1);
    }
    $html = ob_get_clean();
    file_put_contents($outDir . '/' . $slug . '.html', $html);
    echo "  rendered $slug.html\n";
}

// --- 2. Copy public/ assets (excluding *.php) --------------------------------

$iter = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($publicDir, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

$copied = 0;
foreach ($iter as $item) {
    /** @var SplFileInfo $item */
    $rel  = substr($item->getPathname(), strlen($publicDir) + 1);
    $dest = $outDir . '/' . $rel;

    if ($item->isDir()) {
        if (!is_dir($dest)) {
            mkdir($dest, 0775, true);
        }
        continue;
    }
    if (strtolower($item->getExtension()) === 'php') {
        continue;
    }
    $parent = dirname($dest);
    if (!is_dir($parent)) {
        mkdir($parent, 0775, true);
    }
    copy($item->getPathname(), $dest);
    $copied++;
}
echo "  copied $copied asset(s)\n";

// --- 3. Write .htaccess ------------------------------------------------------

$htaccess = <<<HTACCESS
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /

    # Serve /foo as /foo.html when the static file exists
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{DOCUMENT_ROOT}%{REQUEST_URI}.html -f
    RewriteRule ^(.+?)/?\$ \$1.html [L]
</IfModule>

DirectoryIndex index.html
ErrorDocument 404 /index.html

HTACCESS;

file_put_contents($outDir . '/.htaccess', $htaccess);
echo "  wrote .htaccess\n";

$style=".auth-buttons,.nostatic{display:none !important}";
file_put_contents($outDir . '/style.css', $style,FILE_APPEND);
echo "  update style\n";

echo "Done -> $outDir\n";
