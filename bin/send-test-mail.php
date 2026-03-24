<?php
/**
 * CLI-only script: sends a test e-mail to test@improtresk.cz
 * Usage: php bin/send-test-mail.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Access denied.' . PHP_EOL);
}

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailerException;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$to = 'vaclav.vatoz.cerny@gmail.com';

try {
    $mailer = new PHPMailer(true);

    $mailer->isSMTP();
    $mailer->Host     = $_ENV['MAIL_HOST'];
    $mailer->Port     = (int) $_ENV['MAIL_PORT'];
    $mailer->Username = $_ENV['MAIL_USERNAME'];
    $mailer->Password = $_ENV['MAIL_PASSWORD'];

    $encryption = strtolower($_ENV['MAIL_ENCRYPTION'] ?? 'tls');
    if ($encryption === 'ssl') {
        $mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mailer->SMTPAuth   = true;
    } elseif ($encryption === 'tls') {
        $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mailer->SMTPAuth   = true;
    } else {
        $mailer->SMTPSecure = '';
        $mailer->SMTPAuth   = !empty($_ENV['MAIL_USERNAME']);
    }

    $mailer->setFrom(
        $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@improtresk.cz',
        $_ENV['MAIL_FROM_NAME']    ?? 'Improtřesk'
    );

    $mailer->CharSet = PHPMailer::CHARSET_UTF8;
    $mailer->isHTML(true);

    $mailer->addAddress($to);
    $mailer->Subject = 'Test e-mail – Improtřesk';
    $mailer->Body    = '<p>Toto je testovací e-mail odeslaný ze skriptu <code>bin/send-test-mail.php</code>.</p>'
                     . '<p>Odesláno: ' . date('Y-m-d H:i:s') . '</p>';
    $mailer->AltBody = 'Toto je testovací e-mail odeslaný ze skriptu bin/send-test-mail.php. Odesláno: ' . date('Y-m-d H:i:s');

    $mailer->send();
    echo "OK: testovací e-mail odeslán na {$to}" . PHP_EOL;
} catch (MailerException $e) {
    echo 'CHYBA: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}
