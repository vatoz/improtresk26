<?php
namespace App\Models;

use PDO;

class MailQueue
{
    /**
     * Queue a mail using a Twig template.
     *
     * @param PDO $db
     * @param string $to
     * @param string $subject
     * @param string $template  Template path relative to templates/emails/ (e.g. 'password-reset.twig')
     * @param array  $vars      Variables passed to the template
     * @return int Inserted row ID
     */
    public static function addWithTemplate(PDO $db, string $to, string $subject, string $template, array $vars = []): int
    {
        $stmt = $db->prepare("
            INSERT INTO mail_queue (to_email, subject, template, vars, body, status, queued_at)
            VALUES (?, ?, ?, ?, NULL, 'pending', NOW())
        ");
        $stmt->execute([$to, $subject, $template, json_encode($vars, JSON_UNESCAPED_UNICODE)]);
        return (int) $db->lastInsertId();
    }

    /**
     * Queue a mail with a pre-rendered or plain body (no template).
     *
     * @param PDO    $db
     * @param string $to
     * @param string $subject
     * @param string $body     HTML or plain-text body
     * @return int Inserted row ID
     */
    public static function addWithBody(PDO $db, string $to, string $subject, string $body): int
    {
        $stmt = $db->prepare("
            INSERT INTO mail_queue (to_email, subject, template, vars, body, status, queued_at)
            VALUES (?, ?, NULL, NULL, ?, 'pending', NOW())
        ");
        $stmt->execute([$to, $subject, $body]);
        return (int) $db->lastInsertId();
    }

    /**
     * Get all pending mails ordered by queue time (oldest first).
     *
     * @param PDO $db
     * @param int $limit
     * @return array
     */
    public static function getPending(PDO $db, int $limit = 50): array
    {
        $stmt = $db->prepare("
            SELECT * FROM mail_queue
            WHERE status = 'pending'
            ORDER BY queued_at ASC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }

    /**
     * Find a queued mail by ID.
     *
     * @param PDO $db
     * @param int $id
     * @return array|false
     */
    public static function findById(PDO $db, int $id)
    {
        $stmt = $db->prepare("SELECT * FROM mail_queue WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    /**
     * Mark a queued mail as sent.
     *
     * @param PDO $db
     * @param int $id
     * @return bool
     */
    public static function markAsSent(PDO $db, int $id): bool
    {
        $stmt = $db->prepare("
            UPDATE mail_queue
            SET status = 'sent', sent_at = NOW()
            WHERE id = ?
        ");
        return $stmt->execute([$id]);
    }

    /**
     * Mark a queued mail as failed.
     *
     * @param PDO $db
     * @param int $id
     * @return bool
     */
    public static function markAsFailed(PDO $db, int $id): bool
    {
        $stmt = $db->prepare("
            UPDATE mail_queue
            SET status = 'failed'
            WHERE id = ?
        ");
        return $stmt->execute([$id]);
    }

    /**
     * Queue a "payment confirmed + items assigned" email for a user.
     *
     * @param PDO    $db
     * @param string $to           Recipient e-mail
     * @param string $name         Recipient name
     * @param array  $workshops    Workshop name strings
     * @param array  $tickets      Ticket rows: [['name'=>…,'quantity'=>…], …]
     * @param array  $merch        Merch rows:  [['name'=>…,'quantity'=>…], …]
     * @param string $dashboardUrl Full URL to the user's dashboard
     * @return int Inserted row ID
     */
    public static function sendPaymentConfirmed(
        PDO    $db,
        string $to,
        string $name,
        array  $workshops    = [],
        array  $tickets      = [],
        array  $merch        = [],
        string $dashboardUrl = ''
    ): int {
        return self::addWithTemplate($db, $to, 'Platba za Improtřesk přijata – máš přidělené workshopy! ', 'payment-confirmed.twig', [
            'name'         => $name,
            'workshops'    => $workshops,
            'tickets'      => $tickets,
            'merch'        => $merch,
            'dashboardUrl' => $dashboardUrl,
        ]);
    }

    /**
     * Queue a "payment pending" email with QR code and payment details.
     *
     * @param PDO    $db
     * @param string $to      Recipient e-mail
     * @param string $name    Recipient name
     * @param int    $userId  User ID (used as variable symbol)
     * @param float  $amount  Amount awaiting payment
     * @return int Inserted row ID
     */
    public static function sendAwaitingPayment(
        PDO    $db,
        string $to,
        string $name,
        int    $userId,
        float  $amount
    ): int {
        $paymentConfig = payment_config();

        $qrPlatba = new \vplacek\QRPlatba\QRPlatba();
        $qrPlatba->setIban($paymentConfig['iban'])
            ->setAmount($amount)
            ->setScale(5)
            ->setVariableSymbol((string) $userId);

        $qr = 'data:image/png;base64,' . base64_encode($qrPlatba->generateQr());

        return self::addWithTemplate($db, $to, 'Platba za Improtřesk – platební instrukce', 'payment-pending.twig', [
            'name'            => $name,
            'amount'          => $amount,
            'iban'            => $paymentConfig['iban'],
            'readable'        => $paymentConfig['readable'],
            'variable_symbol' => $userId,
            'currency'        => $paymentConfig['currency'],
            'message'         => $paymentConfig['message'],
            'qr'              => $qr,
        ]);
    }

    /**
     * Delete sent and failed mails older than the given number of days.
     *
     * @param PDO $db
     * @param int $days
     * @return int Number of deleted rows
     */
    public static function cleanup(PDO $db, int $days = 30): int
    {
        $stmt = $db->prepare("
            DELETE FROM mail_queue
            WHERE status IN ('sent', 'failed')
            AND queued_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $stmt->execute([$days]);
        return $stmt->rowCount();
    }
}
