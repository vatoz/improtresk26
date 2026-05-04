<?php
namespace App\Services;

use App\Models\MailQueue;
use PDO;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as MailerException;
use Twig\Environment as Twig;

class MailService
{
    private PDO $db;
    private Twig $twig;

    public function __construct(PDO $db, Twig $twig)
    {
        $this->db   = $db;
        $this->twig = $twig;
    }

    /**
     * Process pending mails from the queue.
     *
     * @param int $limit Maximum number of mails to process in one run
     * @return array{sent: int, failed: int}
     */
    public function processQueue(int $limit = 10): array
    {
        $pending = MailQueue::getPending($this->db, $limit);
        $stats   = ['sent' => 0, 'failed' => 0];

        foreach ($pending as $mail) {
            if ($this->send($mail)) {
                MailQueue::markAsSent($this->db, $mail['id']);
                $stats['sent']++;
            } else {
                MailQueue::markAsFailed($this->db, $mail['id']);
                $stats['failed']++;
            }
        }

        return $stats;
    }

    /**
     * Send a single queued mail entry.
     */
    private function send(array $mail): bool
    {
        try {
            $body = $this->resolveBody($mail);
            $mailer = $this->buildMailer();

            $mailer->addAddress($mail['to_email']);
            $mailer->Subject = $mail['subject'];
            $mailer->Body    = $body;
            $mailer->AltBody = strip_tags($body);

            return $mailer->send();
        } catch (MailerException $e) {
            error_log('MailService: PHPMailer error for mail #' . $mail['id'] . ': ' . $e->getMessage());
            return false;
        } catch (\Throwable $e) {
            error_log('MailService: Unexpected error for mail #' . $mail['id'] . ': ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Resolve the HTML body – render Twig template when set, otherwise use stored body.
     *
     * @throws \Throwable on Twig rendering failure
     */
    private function resolveBody(array $mail): string
    {
        if (!empty($mail['template'])) {
            $vars = !empty($mail['vars']) ? json_decode($mail['vars'], true) : [];
            return $this->twig->render('emails/' . $mail['template'], $vars ?? []);
        }

        return (string) $mail['body'];
    }

    /**
     * Build and configure a PHPMailer instance from environment variables.
     *
     * Required env vars:
     *   MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD
     *
     * Optional env vars (with defaults):
     *   MAIL_ENCRYPTION  – 'tls' (default) | 'ssl' | '' (none)
     *   MAIL_FROM_ADDRESS, MAIL_FROM_NAME, MAIL_REPLY_TO
     */
    private function buildMailer(): PHPMailer
    {
        $mailer = new PHPMailer(true); // true = throw exceptions

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

        $replyTo = $_ENV['MAIL_REPLY_TO'] ?? null;
        if ($replyTo) {
            $mailer->addReplyTo($replyTo);
        }

        $mailer->CharSet  = PHPMailer::CHARSET_UTF8;
        $mailer->isHTML(true);

        return $mailer;
    }
}
