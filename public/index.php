<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/bootstrap.php';

use AltoRouter;

// Router
$router = new AltoRouter();
//$router->setBasePath('/'); // pokud je v subdiru, tady nastavit

// Default pages
$router->map('GET', '/', 'DefaultController#index', 'home');
$router->map('GET', '/program', 'DefaultController#program', 'program');
$router->map('GET', '/info', 'DefaultController#info', 'info');
$router->map('GET', '/faq', 'DefaultController#faq', 'faq');
$router->map('GET', '/contact', 'DefaultController#contact', 'contact');

// Auth
$router->map('GET|POST', '/login', 'AuthController#login', 'login');
$router->map('GET|POST', '/register', 'AuthController#register', 'register');
$router->map('GET', '/logout', 'AuthController#logout', 'logout');

// Workshops
$router->map('GET', '/workshops', 'WorkshopController#index', 'workshops');
$router->map('GET|POST', '/workshop/register', 'WorkshopController#register', 'workshop_register');

// User dashboard
$router->map('GET', '/dashboard', 'DashboardController#index', 'dashboard');
$router->map('GET', '/profile', 'DashboardController#index', 'profile');

// Payment
$router->map('GET', '/payment', 'PaymentController#index', 'payment');

// Admin
$router->map('GET', '/admin', 'AdminController#index', 'admin');
$router->map('GET', '/admin/export-csv', 'AdminController#exportCsv', 'admin_export_csv');

$match = $router->match();

if ($match) {
    list($controller, $action) = explode('#', $match['target']);
    $controllerClass = "\\App\\Controllers\\$controller";
    if (class_exists($controllerClass)) {
        $c = new $controllerClass($twig, $db);
        call_user_func_array([$c, $action], $match['params']);
    } else {
        header("HTTP/1.0 404 Not Found");
        echo "Controller ". $controller." not found.";
    }
} else {
    header("HTTP/1.0 404 Not Found");
    echo "Page not found.";
}
