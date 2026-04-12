<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/bootstrap.php';

// Probability-based pseudo-cron (2 % of requests)
if (mt_rand(1, 100) <= 2) {
    register_shutdown_function(function () use ($db, $twig) {
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();   // flush response to browser before running cron
        }
        (new \App\Services\CronService($db, $twig))->run();
    });
}

// Router
$router = new AltoRouter();
//$router->setBasePath('/'); // pokud je v subdiru, tady nastavit

// Default pages
$router->map('GET', '/', 'DefaultController#index', 'home');
$router->map('GET', '/program', 'DefaultController#program', 'program');
$router->map('GET', '/harmonogram', 'DefaultController#harmonogram', 'harmonogram');
$router->map('GET', '/info', 'DefaultController#info', 'info');
$router->map('GET', '/faq', 'DefaultController#faq', 'faq');
$router->map('GET', '/contact', 'DefaultController#contact', 'contact');
$router->map('GET', '/medailonky', 'DefaultController#medailonky', 'medailonky');

// Auth
$router->map('GET|POST', '/login', 'AuthController#login', 'login');
$router->map('GET|POST', '/register', 'AuthController#register', 'register');
$router->map('GET', '/logout', 'AuthController#logout', 'logout');
$router->map('GET', '/reset-password', 'AuthController#showResetPasswordForm', 'show_reset_password');
$router->map('POST', '/reset-password', 'AuthController#resetPassword', 'reset_password');
$router->map('POST', '/request-password-reset', 'AuthController#requestPasswordReset', 'request_password_reset');

// Workshops
$router->map('GET', '/workshops', 'WorkshopController#index', 'workshops');
//$router->map('GET|POST', '/workshop/register', 'WorkshopController#register', 'workshop_register');
//$router->map('GET|POST', '/workshop/unregister', 'WorkshopController#unregister', 'workshop_unregister');


// User dashboard
$router->map('GET', '/dashboard', 'DashboardController#index', 'dashboard');
$router->map('GET', '/profile', 'DashboardController#index', 'profile');
$router->map('POST', '/dashboard/cancel-registration', 'DashboardController#cancelRegistration', 'cancel_registration');
$router->map('POST', '/dashboard/reorder-registrations', 'DashboardController#reorderRegistrations', 'reorder_registrations');
$router->map('POST', '/dashboard/save-answer', 'DashboardController#saveAnswer', 'save_answer');

// Shop
$router->map('GET',  '/tickets',              'ShopController#tickets',       'tickets');
$router->map('GET',  '/merch',                'ShopController#merch',         'merch');
$router->map('POST', '/shop/buy',             'ShopController#buy',           'shop_buy');
$router->map('POST', '/shop/cancel-purchase', 'ShopController#cancelPurchase','shop_cancel_purchase');

// Registration wizard
$router->map('GET|POST', '/wizard/workshops', 'WizardController#workshops', 'wizard_workshops');
$router->map('GET|POST', '/wizard/tickets',   'WizardController#tickets',   'wizard_tickets');
$router->map('GET|POST', '/wizard/merch',     'WizardController#merch',     'wizard_merch');
$router->map('GET',      '/wizard/payment',   'WizardController#payment',   'wizard_payment');
$router->map('GET', '/wizard', 'WizardController#workshops', 'wizard');

// QR platba
$router->map('GET', '/qr-platba', 'DefaultController#qrPlatba', 'qr_platba');

// Secret hero pages
$router->map('GET', '/hero', 'DefaultController#hero', 'hero');
$router->map('GET', '/heroine', 'DefaultController#heroine', 'heroine');

// Cron
$router->map('GET', '/cron', 'CronController#run', 'cron');

// Admin
$router->map('GET', '/admin', 'AdminController#participants', 'admin');
$router->map('GET', '/admin/workshops', 'AdminController#workshops', 'admin_workshops');
$router->map('GET', '/admin/payments', 'AdminController#payments', 'admin_payments');
$router->map('POST', '/admin/sync-fio', 'AdminController#syncFio', 'admin_sync_fio');
$router->map('POST', '/admin/payments/mark-paid', 'AdminController#markPaid', 'admin_mark_paid');
$router->map('GET', '/admin/export', 'AdminController#export', 'admin_export');
$router->map('GET', '/admin/export-csv', 'AdminController#exportCsv', 'admin_export_csv');
$router->map('GET', '/admin/attendance', 'AdminController#attendance', 'admin_attendance');
$router->map('GET', '/admin/mail-queue', 'AdminController#mailQueue', 'admin_mail_queue');
$router->map('GET', '/admin/mail-queue/[i:id]/preview', 'AdminController#mailQueuePreview', 'admin_mail_queue_preview');
$router->map('GET',  '/admin/send-mail',                  'AdminController#sendMailForm',       'admin_send_mail');
$router->map('POST', '/admin/send-mail',                  'AdminController#sendMail',           'admin_send_mail_post');
$router->map('POST', '/admin/send-mail/preview',          'AdminController#sendMailPreview',    'admin_send_mail_preview');
$router->map('POST', '/admin/send-mail/template/save',    'AdminController#saveMailTemplate',   'admin_send_mail_tpl_save');
$router->map('POST', '/admin/send-mail/template/delete',  'AdminController#deleteMailTemplate', 'admin_send_mail_tpl_delete');
$router->map('GET', '/admin/questions', 'AdminController#questions', 'admin_questions');
$router->map('GET', '/admin/registrations', 'AdminController#detailedRegistrations', 'admin_registrations');
$router->map('POST', '/admin/registrations/set-unpaid', 'AdminController#setUnpaid', 'admin_registrations_set_unpaid');
$router->map('GET', '/admin/users', 'AdminController#userList', 'admin_users');
$router->map('GET', '/admin/users/[i:id]', 'AdminController#userDetail', 'admin_user_detail');
$router->map('POST', '/admin/users/[i:id]/send-mail', 'AdminController#sendUserMail', 'admin_user_send_mail');
$router->map('POST', '/admin/users/[i:id]/set-awaiting-payment', 'AdminController#setAwaitingPayment', 'admin_user_set_awaiting_payment');
$router->map('GET', '/admin/tickets', 'AdminController#ticketSales', 'admin_tickets');
$router->map('GET', '/admin/pairing', 'AdminController#pairing', 'admin_pairing');
$router->map('POST', '/admin/pairing/set-registration-status', 'AdminController#setRegistrationStatus', 'admin_pairing_reg_status');
$router->map('POST', '/admin/pairing/set-purchase-status', 'AdminController#setPurchaseStatus', 'admin_pairing_purch_status');
$router->map('POST', '/admin/pairing/complete-transaction', 'AdminController#completeTransaction', 'admin_pairing_complete');
$router->map('GET',  '/admin/static-blocks',        'AdminController#staticBlocks',       'admin_static_blocks');
$router->map('POST', '/admin/static-blocks/save',   'AdminController#saveStaticBlock',    'admin_static_blocks_save');
$router->map('POST', '/admin/static-blocks/delete', 'AdminController#deleteStaticBlock',  'admin_static_blocks_delete');

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
