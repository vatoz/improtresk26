<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/bootstrap.php';

use AltoRouter;

// Router
$router = new AltoRouter();
$router->setBasePath('/'); // pokud je v subdiru, tady nastavit

$router->map('GET', '/', 'HomeController#index', 'home');
$router->map('GET|POST', '/login', 'AuthController#login', 'login');
$router->map('GET|POST', '/register', 'AuthController#register', 'register');
$router->map('GET', '/logout', 'AuthController#logout', 'logout');
$router->map('GET', '/workshops', 'WorkshopController#index', 'workshops');
$router->map('GET|POST', '/profile', 'UserController#profile', 'profile');

// Admin
$router->map('GET', '/admin', 'AdminController#index', 'admin_dashboard');
$router->map('GET|POST', '/admin/payments', 'AdminController#payments', 'admin_payments');

$match = $router->match();

if ($match) {
    list($controller, $action) = explode('#', $match['target']);
    $controllerClass = "\\App\\Controllers\\$controller";
    if (class_exists($controllerClass)) {
        $c = new $controllerClass($twig, $db);
        call_user_func_array([$c, $action], $match['params']);
    } else {
        header("HTTP/1.0 404 Not Found");
        echo "Controller not found.";
    }
} else {
    header("HTTP/1.0 404 Not Found");
    echo "Page not found.";
}
