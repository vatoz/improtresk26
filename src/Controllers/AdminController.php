<?php
namespace App\Controllers;

use App\Models\ChildrenItem;
use App\Models\MailQueue;
use App\Models\Workshop;
use App\Models\TransactionList;
use App\Services\FioService;

class AdminController extends BaseController
{
    public function participants()
    {
        $this->requireAdmin();

        $stmt = $this->db->query("
            SELECT r.user_id, u.name, u.email, r.payment_status, w.name AS workshop_name, r.created_at
            FROM registrations r
            JOIN users u ON r.user_id = u.id
            LEFT JOIN workshops w ON r.workshop_id = w.id
            ORDER BY r.created_at DESC limit 15
        ");

        $purchaseStmt = $this->db->query("
            SELECT p.id, p.user_id, u.name AS user_name, u.email AS user_email,
                   p.item_type, p.quantity, p.payment_status, p.created_at,
                   COALESCE(t.name, m.name) AS item_name,
                   COALESCE(t.price, m.price) AS price
            FROM purchases p
            JOIN users u ON p.user_id = u.id
            LEFT JOIN tickets t ON t.id = p.item_id AND p.item_type = 'ticket'
            LEFT JOIN merch m ON m.id = p.item_id AND p.item_type = 'merch'
            ORDER BY p.created_at DESC limit 30
        ");

        echo $this->twig->render('pages/admin.twig', [
            'user'         => $this->getCurrentUser(),
            'active_page'  => 'admin',
            'participants' => $stmt->fetchAll(\PDO::FETCH_ASSOC),
            'purchases'    => $purchaseStmt->fetchAll(\PDO::FETCH_ASSOC),
        ]);
    }

    public function workshops()
    {
        $this->requireAdmin();

        $stmt = $this->db->query("
            SELECT w.*,
                   COUNT(r.id) AS registered_count,
                   SUM(CASE WHEN r.payment_status = 'paid' THEN 1 ELSE 0 END) AS paid_count
            FROM workshops w
            LEFT JOIN registrations r ON w.id = r.workshop_id
            WHERE r.payment_status not in ('skipped','cancelled','notpaid')
            GROUP BY w.id
        ");

        echo $this->twig->render('pages/admin-workshops.twig', [
            'user'        => $this->getCurrentUser(),
            'active_page' => 'admin',
            'workshops'   => $stmt->fetchAll(\PDO::FETCH_ASSOC),
        ]);
    }

    public function payments()
    {
        $this->requireAdmin();

        echo $this->twig->render('pages/admin-payments.twig', [
            'user'         => $this->getCurrentUser(),
            'active_page'  => 'admin',
            'transactions' => $this->enrichTransactions(TransactionList::getPending($this->db)),
        ]);
    }

    public function syncFio()
    {
        $this->requireAdmin();

        $syncResult = null;
        $error = null;

        try {
            $fio = new FioService($this->db);
            $syncResult = $fio->fetchAndStore();
        } catch (\RuntimeException $e) {
            $error = $e->getMessage();
        }

        echo $this->twig->render('pages/admin-payments.twig', [
            'user'         => $this->getCurrentUser(),
            'active_page'  => 'admin',
            'transactions' => $this->enrichTransactions(TransactionList::getPending($this->db)),
            'sync_result'  => $syncResult,
            'error'        => $error,
        ]);
    }

    public function markPaid()
    { die();
        $this->requireAdmin();

        $txId = (int) ($_POST['transaction_id'] ?? 0);

        if ($txId > 0) {
            $tx = TransactionList::findById($this->db, $txId);

            if ($tx && $tx['completed'] === null && !empty($tx['variable_symbol'])) {
                $userId = (int) $tx['variable_symbol'] - (int) date('Y');

                if ($userId > 0) {
                    $this->db->prepare("
                        UPDATE registrations
                        SET payment_status = 'paid', paid_at = NOW()
                        WHERE user_id = ? AND payment_status = 'pending'
                    ")->execute([$userId]);

                    TransactionList::markCompleted($this->db, $txId);

                    $this->queuePaymentConfirmedMail($userId);
                }
            }
        }

        header('Location: /admin/payments');
        exit;
    }

    /**
     * Enrich pending transactions with matched user and pending workshop sum.
     * variable_symbol encodes user_id as: variable_symbol = user_id
     */
    private function enrichTransactions(array $transactions): array
    {
        
        foreach ($transactions as &$t) {
            $t['matched_user']  = null;
            $t['pending_sum']   = null;
            $t['amount_int']    = (int) $t['amount'];
            $t['can_mark_paid'] = false;

            if (empty($t['variable_symbol'])) {
                continue;
            }

            $userId = (int) $t['variable_symbol'] ;
            if ($userId <= 0) {
                continue;
            }

            $stmt = $this->db->prepare("SELECT id, name, email, hero FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$user) {
                continue;
            }

            $t['matched_user'] = $user;

            $stmt = $this->db->prepare("
                SELECT COALESCE(SUM(w.price), 0) AS total
                FROM registrations r
                JOIN workshops w ON r.workshop_id = w.id
                WHERE r.user_id = ? AND r.payment_status = 'pending'
            ");
            $stmt->execute([$userId]);
            $pendingSum = (int) $stmt->fetchColumn();

            $t['pending_sum']   = $pendingSum;
            $t['can_mark_paid'] = $pendingSum > 0 && $pendingSum === $t['amount_int'];
        }
        unset($t);

        return $transactions;
    }


    public function attendance()
    {
        $this->requireAdmin();

        $stmt = $this->db->query("
            SELECT
                w.id AS workshop_id,
                w.name AS workshop_name,
                w.timeslot,
                w.capacity,
                ts.start_datetime,
                ts.end_datetime,
                u.id    AS user_id,
                u.name  AS user_name,
                u.email AS user_email,
                u.note AS user_note,
                u.checked_in,
                r.payment_status,
                r.variable_symbol
            FROM workshops w
            LEFT JOIN timeslots ts ON ts.code = w.timeslot
            LEFT JOIN registrations r ON w.id = r.workshop_id
                AND r.payment_status IN ('paid', 'approved')
            LEFT JOIN users u ON r.user_id = u.id
            WHERE w.is_active = 1
            ORDER BY ts.start_datetime, w.name, r.created_at, u.name
        ");

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Group participants under each workshop
        $workshops = [];
        foreach ($rows as $row) {
            $wid = $row['workshop_id'];
            if (!isset($workshops[$wid])) {
                $workshops[$wid] = [
                    'id'             => $wid,
                    'name'           => $row['workshop_name'],
                    'timeslot'       => $row['timeslot'],
                    'start_datetime' => $row['start_datetime'],
                    'end_datetime'   => $row['end_datetime'],
                    'capacity'       => $row['capacity'],
                    'participants'   => [],
                ];
            }
            if ($row['user_name'] !== null) {
                $workshops[$wid]['participants'][] = [
                    'id'         => $row['user_id'],
                    'name'       => $row['user_name'],
                    'email'      => $row['user_email'],
                    'note'       => $row['user_note'],
                    'checked_in' => $row['checked_in'],
                    'status'     => $row['payment_status'],
                    'vs'         => $row['variable_symbol'],
                ];
            }
        }

        echo $this->twig->render('pages/admin-attendance.twig', [
            'user'        => $this->getCurrentUser(),
            'active_page' => 'admin',
            'workshops'   => array_values($workshops),
        ]);
    }

    public function mailQueue()
    {
        $this->requireAdmin();

        $stmt = $this->db->query("
            SELECT id, to_email, subject, template, status, queued_at, sent_at
            FROM mail_queue
            ORDER BY queued_at DESC
            LIMIT 200
        ");

        echo $this->twig->render('pages/admin-mail-queue.twig', [
            'user'        => $this->getCurrentUser(),
            'active_page' => 'admin',
            'mails'       => $stmt->fetchAll(\PDO::FETCH_ASSOC),
        ]);
    }

    public function sendMailForm()
    {
        $this->requireAdmin();

        $users = $this->db->query("SELECT id, name, email FROM users ORDER BY name")->fetchAll(\PDO::FETCH_ASSOC);
        $mailTemplates = $this->db->query("SELECT id, title, subject, text FROM mail_templates WHERE is_valid = 1 ORDER BY title")->fetchAll(\PDO::FETCH_ASSOC);

        echo $this->twig->render('pages/admin-send-mail.twig', [
            'user'           => $this->getCurrentUser(),
            'active_page'    => 'admin',
            'users'          => $users,
            'mail_templates' => $mailTemplates,
            'form'           => $_SESSION['send_mail_form'] ?? [],
            'success'        => $_SESSION['send_mail_success'] ?? null,
            'error'          => $_SESSION['send_mail_error'] ?? null,
            'csrf'           => csrf_token('admin-send-mail'),
        ]);
        unset($_SESSION['send_mail_form'], $_SESSION['send_mail_success'], $_SESSION['send_mail_error']);
    }

    public function sendMail()
    {
        $this->requireAdmin();

        if (!csrf_validate('admin-send-mail', $_POST['_csrf'] ?? null)) {
            http_response_code(403);
            echo 'Neplatný CSRF token.';
            exit;
        }

        $recipientsType  = $_POST['recipients_type'] ?? 'all';
        $recipientsRaw   = $_POST['recipients_custom'] ?? '';
        $subject         = trim($_POST['subject'] ?? '');
        $body            = $_POST['body'] ?? '';

        if ($subject === '' || trim($body) === '') {
            $_SESSION['send_mail_error'] = 'Předmět a text e-mailu jsou povinné.';
            $_SESSION['send_mail_form']  = $_POST;
            header('Location: /admin/send-mail');
            exit;
        }

        if ($recipientsType === 'all') {
            $rows = $this->db->query("SELECT email FROM users ORDER BY id")->fetchAll(\PDO::FETCH_COLUMN);
        } else {
            // Split by newlines and/or commas, trim, filter empty
            $rows = array_values(array_filter(
                array_map('trim', preg_split('/[\r\n,]+/', $recipientsRaw)),
                fn($e) => filter_var($e, FILTER_VALIDATE_EMAIL) !== false
            ));
        }

        if (empty($rows)) {
            $_SESSION['send_mail_error'] = 'Žádná platná e-mailová adresa.';
            $_SESSION['send_mail_form']  = $_POST;
            header('Location: /admin/send-mail');
            exit;
        }

        $count = 0;
        foreach ($rows as $email) {
            MailQueue::addWithTemplate($this->db, $email, $subject, 'custom.twig', [
                'subject' => $subject,
                'body'    => $body,
            ]);
            $count++;
        }

        $_SESSION['send_mail_success'] = "Zařazeno do fronty: $count e-mail(ů).";
        header('Location: /admin/send-mail');
        exit;
    }

    public function sendMailPreview()
    {
        $this->requireAdmin();

        if (!csrf_validate('admin-send-mail', $_POST['_csrf'] ?? null)) {
            http_response_code(403);
            echo 'Neplatný CSRF token.';
            exit;
        }

        $subject = trim($_POST['subject'] ?? '(bez předmětu)');
        $body    = $_POST['body'] ?? '';

        header('Content-Type: text/html; charset=utf-8');
        echo $this->twig->render('emails/custom.twig', [
            'subject' => $subject,
            'body'    => $body,
        ]);
    }

    public function saveMailTemplate()
    {
        $this->requireAdmin();

        if (!csrf_validate('admin-send-mail', $_POST['_csrf'] ?? null)) {
            http_response_code(403); echo 'Neplatný CSRF token.'; exit;
        }

        $id       = (int) ($_POST['template_id'] ?? 0);
        $title    = trim($_POST['tpl_title']   ?? '');
        $subject  = trim($_POST['tpl_subject'] ?? '');
        $text     = $_POST['tpl_text']         ?? '';
        $isValid  = isset($_POST['tpl_is_valid']) ? 1 : 0;

        if ($title === '') {
            $_SESSION['send_mail_error'] = 'Název šablony je povinný.';
            header('Location: /admin/send-mail'); exit;
        }

        if ($id > 0) {
            $this->db->prepare("UPDATE mail_templates SET title=?, subject=?, text=?, is_valid=? WHERE id=?")
                     ->execute([$title, $subject, $text, $isValid, $id]);
        } else {
            $this->db->prepare("INSERT INTO mail_templates (title, subject, text, is_valid) VALUES (?,?,?,?)")
                     ->execute([$title, $subject, $text, $isValid]);
        }

        $_SESSION['send_mail_success'] = 'Šablona uložena.';
        header('Location: /admin/send-mail'); exit;
    }

    public function deleteMailTemplate()
    {
        $this->requireAdmin();

        if (!csrf_validate('admin-send-mail', $_POST['_csrf'] ?? null)) {
            http_response_code(403); echo 'Neplatný CSRF token.'; exit;
        }

        $id = (int) ($_POST['template_id'] ?? 0);
        if ($id > 0) {
            $this->db->prepare("DELETE FROM mail_templates WHERE id = ?")->execute([$id]);
        }

        $_SESSION['send_mail_success'] = 'Šablona smazána.';
        header('Location: /admin/send-mail'); exit;
    }

    public function mailQueuePreview(int $id)
    {
        $this->requireAdmin();

        $mail = MailQueue::findById($this->db, $id);

        if (!$mail) {
            http_response_code(404);
            echo 'E-mail nenalezen.';
            return;
        }

        if (!empty($mail['template'])) {
            $vars = !empty($mail['vars']) ? json_decode($mail['vars'], true) : [];
            $body = $this->twig->render('emails/' . $mail['template'], $vars ?? []);
        } else {
            $body = (string) $mail['body'];
        }

        header('Content-Type: text/html; charset=utf-8');
        echo $body;
    }

    public function export()
    {
        $this->requireAdmin();

        echo $this->twig->render('pages/admin-export.twig', [
            'user'        => $this->getCurrentUser(),
            'active_page' => 'admin',
        ]);
    }

    public function exportCsv()
    {
        $this->requireAdmin();

        $stmt = $this->db->query("
            SELECT u.name, u.email, w.name AS workshop, r.payment_status, r.created_at
            FROM registrations r
            JOIN users u ON r.user_id = u.id
            LEFT JOIN workshops w ON r.workshop_id = w.id
        ");

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=registrations.csv');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['Name', 'Email', 'Workshop', 'Payment Status', 'Created At']);

        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            fputcsv($output, $row);
        }

        fclose($output);
        exit;
    }

    public function questions()
    {
        $this->requireAdmin();

        // Fetch all yes_no questions with in_admin = 1, together with users who answered 'yes'
        // Fetch all yes_no questions with in_admin = -1, together with users who answered 'no'

        $stmt = $this->db->query("
            SELECT q.id, q.question_name, q.question,q.in_admin,
                   u.name AS user_name, u.email AS user_email
            FROM user_questions q
            LEFT JOIN user_answers ua ON ua.question_id = q.id 
            LEFT JOIN users u ON u.id = ua.user_id
            WHERE 
                q.type = 'yes_no' AND
                 ((q.in_admin = 1 AND ua.value = 'yes') or (q.in_admin = -1 and  ua.value = 'no' ))
                   AND q.is_active = 1
            ORDER BY q.`order` ASC, q.id ASC, u.name ASC
        ");

        // Group rows by question
        $questions = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $qid = $row['id'];
            if (!isset($questions[$qid])) {
                $questions[$qid] = [
                    'id'            => $row['id'],
                    'in_admin'            => $row['in_admin'],
                    'question_name' => $row['question_name'],
                    'question'      => $row['question'],
                    'users'         => [],
                ];
            }
            if ($row['user_name'] !== null) {
                $questions[$qid]['users'][] = [
                    'name'  => $row['user_name'],
                    'email' => $row['user_email'],
                ];
            }
        }

        echo $this->twig->render('pages/admin-questions.twig', [
            'user'        => $this->getCurrentUser(),
            'active_page' => 'admin',
            'questions'   => array_values($questions),
        ]);
    }

    public function detailedRegistrations()
    {
        $this->requireAdmin();

        $stmt = $this->db->query("
            SELECT
                w.id AS workshop_id,
                w.name AS workshop_name,
                w.timeslot,
                r.id AS registration_id,
                r.payment_status,
                r.created_at,
                u.name AS user_name,
                u.email AS user_email
            FROM workshops w
            LEFT JOIN registrations r ON w.id = r.workshop_id AND r.payment_status != 'cancelled'
            LEFT JOIN users u ON r.user_id = u.id
            WHERE w.is_active = 1
            ORDER BY w.timeslot, w.name, r.created_at
        ");

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $threshold = new \DateTime('-6 hours');

        $workshops = [];
        foreach ($rows as $row) {
            $wid = $row['workshop_id'];
            if (!isset($workshops[$wid])) {
                $workshops[$wid] = [
                    'id'            => $wid,
                    'name'          => $row['workshop_name'],
                    'timeslot'      => $row['timeslot'],
                    'registrations' => [],
                ];
            }
            if ($row['registration_id'] !== null) {
                $createdAt = new \DateTime($row['created_at']);
                $workshops[$wid]['registrations'][] = [
                    'id'             => $row['registration_id'],
                    'status'         => $row['payment_status'],
                    'created_at'     => $row['created_at'],
                    'user_name'      => $row['user_name'],
                    'user_email'     => $row['user_email'],
                    'can_set_unpaid' => $row['payment_status'] === 'pending' && $createdAt < $threshold,
                ];
            }
        }

        echo $this->twig->render('pages/admin-registrations.twig', [
            'user'       => $this->getCurrentUser(),
            'active_page' => 'admin',
            'workshops'  => array_values($workshops),
            'csrf'       => csrf_token('admin-registrations'),
        ]);
    }

    public function setUnpaid()
    {
        $this->requireAdmin();

        header('Content-Type: application/json');

        if (!csrf_validate('admin-registrations', $_POST['_csrf'] ?? null)) {
            http_response_code(403);
            echo json_encode(['error' => 'Neplatný CSRF token.']);
            exit;
        }

        $regId = (int) ($_POST['registration_id'] ?? 0);

        if ($regId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Neplatné ID registrace.']);
            exit;
        }

        $stmt = $this->db->prepare("SELECT id, payment_status, created_at FROM registrations WHERE id = ? LIMIT 1");
        $stmt->execute([$regId]);
        $reg = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$reg || $reg['payment_status'] !== 'pending') {
            http_response_code(400);
            echo json_encode(['error' => 'Registrace není ve stavu pending.']);
            exit;
        }

        $threshold = new \DateTime('-6 hours');
        if (new \DateTime($reg['created_at']) >= $threshold) {
            http_response_code(400);
            echo json_encode(['error' => 'Registrace není dostatečně stará.']);
            exit;
        }

        $this->db->prepare("UPDATE registrations SET payment_status = 'notpaid' WHERE id = ?")
                 ->execute([$regId]);

        echo json_encode(['ok' => true]);
        exit;
    }

    public function pairing()
    {
        $this->requireAdmin();

        // Load all transactions (including completed), newest first
        $stmt = $this->db->query("
            SELECT id, date, amount, currency, variable_symbol,
                   counter_account_name, message, completed
            FROM transaction_lists
            ORDER BY completed ASC, date DESC, id DESC
            LIMIT 200
        ");
        $transactions = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Enrich each transaction with matched user + their registrations + purchases
        foreach ($transactions as &$tx) {
            $tx['amount_int']   = (int) $tx['amount'];
            $tx['matched_user'] = null;
            $tx['registrations'] = [];
            $tx['purchases']     = [];

            if (empty($tx['variable_symbol'])) {
                continue;
            }

            $userId = (int) $tx['variable_symbol'] ;
            if ($userId <= 0) {
                continue;
            }

            $uStmt = $this->db->prepare("SELECT id, name, email, hero FROM users WHERE id = ? LIMIT 1");
            $uStmt->execute([$userId]);
            $user = $uStmt->fetch(\PDO::FETCH_ASSOC);
            if (!$user) {
                continue;
            }
            $tx['matched_user'] = $user;

            // Registrations (non-cancelled)
            $rStmt = $this->db->prepare("
                SELECT r.id, r.payment_status, w.name AS workshop_name, w.price,
                       ts.start_datetime, r.created_at , w.timeslot, w.capacity, w.paid
                FROM registrations r
                LEFT JOIN workshops w ON r.workshop_id = w.id
                LEFT JOIN timeslots ts ON ts.code = w.timeslot
                WHERE r.user_id = ? 
                ORDER BY w.timeslot asc ,r.priority asc, r.id ASC
            ");
            $rStmt->execute([$userId]);
            $tx['registrations'] = $rStmt->fetchAll(\PDO::FETCH_ASSOC);

            // Attach queue position to each registration
            $queuePositions = Workshop::getQueuePositions($this->db, $userId);
            foreach ($tx['registrations'] as &$reg) {
                $reg['queue_position'] = $queuePositions[(int)$reg['id']]['queue_position'] ?? null;
            }
            unset($reg);

            // Purchases (non-cancelled)
            $pStmt = $this->db->prepare("
                SELECT p.id, p.item_type, p.quantity, p.payment_status,
                       COALESCE(t.name, m.name) AS item_name,
                       COALESCE(t.price, m.price) AS price
                FROM purchases p
                LEFT JOIN tickets t ON t.id = p.item_id AND p.item_type = 'ticket'
                LEFT JOIN merch   m ON m.id = p.item_id AND p.item_type = 'merch'
                WHERE p.user_id = ? 
                ORDER BY p.created_at ASC
            ");
            $pStmt->execute([$userId]);
            $tx['purchases'] = $pStmt->fetchAll(\PDO::FETCH_ASSOC);
        }
        unset($tx);

        $threshold= new \DateTime('-6 hours');

        echo $this->twig->render('pages/admin-pairing.twig', [
            'user'         => $this->getCurrentUser(),
            'active_page'  => 'admin',
            'transactions' => $transactions,
            'threshold'    => $threshold->format("U"),
            'csrf'         => csrf_token('admin-pairing'),
        ]);
    }

    public function userList()
    {
        $this->requireAdmin();

        $stmt = $this->db->query("
            SELECT u.id, u.name, u.email, u.hero, u.created_at, u.note,
                   (SELECT COUNT(*) FROM registrations r WHERE r.user_id = u.id AND r.payment_status != 'cancelled') AS registration_count,
                   (SELECT COUNT(*) FROM purchases pu WHERE pu.user_id = u.id AND pu.payment_status != 'cancelled') AS purchase_count,
                   (SELECT COUNT(*) FROM transaction_lists tl WHERE  CAST(tl.variable_symbol AS INT) = u.id ) AS transaction_count
            FROM users u
            ORDER BY u.id
        ");

        echo $this->twig->render('pages/admin-users.twig', [
            'user'        => $this->getCurrentUser(),
            'active_page' => 'admin',
            'users'       => $stmt->fetchAll(\PDO::FETCH_ASSOC),
        ]);
    }

    public function userDetail(int $id)
    {
        $this->requireAdmin();

        $uStmt = $this->db->prepare("SELECT id, name, email, hero, created_at, awaiting_payment, note, checked_in FROM users WHERE id = ? LIMIT 1");
        $uStmt->execute([$id]);
        $profileUser = $uStmt->fetch(\PDO::FETCH_ASSOC);

        if (!$profileUser) {
            http_response_code(404);
            echo 'Uživatel nenalezen.';
            return;
        }

        // Transactions for this user
        $txStmt = $this->db->prepare("
            SELECT id, date, amount, currency, variable_symbol,
                   counter_account_name, message, completed
            FROM transaction_lists
            WHERE cast(variable_symbol as int) = ? 
            ORDER BY date DESC, id DESC
        ");
        $txStmt->execute([$id]);
        $transactions = $txStmt->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($transactions as &$tx) {
            $tx['amount_int'] = (int) $tx['amount'];
        }
        unset($tx);

        // Registrations
        $rStmt = $this->db->prepare("
            SELECT r.id, r.payment_status, w.name AS workshop_name, w.price,
                   ts.start_datetime, r.created_at, w.timeslot, w.capacity, w.registered
            FROM registrations r
            LEFT JOIN workshops w ON r.workshop_id = w.id
            LEFT JOIN timeslots ts ON ts.code = w.timeslot
            WHERE r.user_id = ?
            ORDER BY w.timeslot ASC, r.priority ASC, r.id ASC
        ");
        $rStmt->execute([$id]);
        $registrations = $rStmt->fetchAll(\PDO::FETCH_ASSOC);

        $queuePositions = Workshop::getQueuePositions($this->db, $id);
        foreach ($registrations as &$reg) {
            $reg['queue_position'] = $queuePositions[(int)$reg['id']]['queue_position'] ?? null;
        }
        unset($reg);

        // Purchases
        $pStmt = $this->db->prepare("
            SELECT p.id, p.item_type, p.quantity, p.payment_status,
                   COALESCE(t.name, m.name) AS item_name,
                   COALESCE(t.price, m.price) AS price
            FROM purchases p
            LEFT JOIN tickets t ON t.id = p.item_id AND p.item_type = 'ticket'
            LEFT JOIN merch   m ON m.id = p.item_id AND p.item_type = 'merch'
            WHERE p.user_id = ?
            ORDER BY p.created_at ASC
        ");
        $pStmt->execute([$id]);
        $purchases = $pStmt->fetchAll(\PDO::FETCH_ASSOC);

        $threshold = new \DateTime('-6 hours');

        $mailTemplates = $this->db->query(
            "SELECT id, title, subject FROM mail_templates WHERE is_valid = 1 ORDER BY title"
        )->fetchAll(\PDO::FETCH_ASSOC);

        $allWorkshops = $this->db->query(
            "SELECT id, name, timeslot, price FROM workshops ORDER BY timeslot, name"
        )->fetchAll(\PDO::FETCH_ASSOC);

        $allTickets = $this->db->query(
            "SELECT id, name, price FROM tickets ORDER BY name"
        )->fetchAll(\PDO::FETCH_ASSOC);

        // Mails sent to this user (by e-mail address)
        $mStmt = $this->db->prepare("
            SELECT id, subject, template, status, queued_at, sent_at
            FROM mail_queue
            WHERE to_email = ?
            ORDER BY queued_at DESC
        ");
        $mStmt->execute([$profileUser['email']]);
        $sentMails = $mStmt->fetchAll(\PDO::FETCH_ASSOC);

        // Q&A
        $questionsWithAnswers = \App\Models\UserQuestion::getWithAnswersForUser($this->db, $id);

        echo $this->twig->render('pages/admin-user-detail.twig', [
            'user'                  => $this->getCurrentUser(),
            'active_page'           => 'admin',
            'profile_user'          => $profileUser,
            'transactions'          => $transactions,
            'registrations'         => $registrations,
            'purchases'             => $purchases,
            'threshold'             => $threshold->format('U'),
            'csrf'                  => csrf_token('admin-pairing'),
            'mail_sent'             => $_GET['mail_sent'] ?? null,
            'mail_templates'        => $mailTemplates,
            'sent_mails'            => $sentMails,
            'questions_with_answers'=> $questionsWithAnswers,
            'all_workshops'         => $allWorkshops,
            'all_tickets'           => $allTickets,
        ]);
    }

    public function sendUserMail(int $id)
    {
        $this->requireAdmin();
        csrf_validate('admin-pairing', $_POST['_csrf'] ?? null);

        $uStmt = $this->db->prepare("SELECT id, name, email FROM users WHERE id = ? LIMIT 1");
        $uStmt->execute([$id]);
        $user = $uStmt->fetch(\PDO::FETCH_ASSOC);

        if (!$user) {
            http_response_code(404);
            echo 'Uživatel nenalezen.';
            return;
        }

        $type = $_POST['mail_type'] ?? '';
        $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
            . '://' . $_SERVER['HTTP_HOST'];

        switch ($type) {
            case 'registration':
                MailQueue::addWithTemplate($this->db, $user['email'], 'Vítej na Improtřesku 2026!', 'registration.twig', [
                    'name'     => $user['name'],
                    'email'    => $user['email'],
                    'loginUrl' => $baseUrl . '/login',
                ]);
                break;

            case 'payment-confirmed':
                $this->queuePaymentConfirmedMail($id);
                break;

            case 'schedule':
                $this->queueScheduleMail($id);
                break;

            case 'password-reset':
                $token = \App\Models\User::createPasswordResetToken($this->db, $user['email']);
                if ($token) {
                    MailQueue::addWithTemplate($this->db, $user['email'], 'Obnova hesla - Improtřesk 2026', 'password-reset.twig', [
                        'resetUrl' => $baseUrl . '/reset-password?token=' . $token,
                        'email'    => $user['email'],
                    ]);
                }
                break;

            case 'template':
                $templateId = (int) ($_POST['template_id'] ?? 0);
                if ($templateId > 0) {
                    $stmt = $this->db->prepare("SELECT subject, text FROM mail_templates WHERE id = ? AND is_valid = 1 LIMIT 1");
                    $stmt->execute([$templateId]);
                    $tpl = $stmt->fetch(\PDO::FETCH_ASSOC);
                    if ($tpl) {
                        MailQueue::addWithTemplate($this->db, $user['email'], $tpl['subject'], 'custom.twig', [
                            'subject' => $tpl['subject'],
                            'body'    => $tpl['text'],
                        ]);
                    }
                }
                break;

            default:
                header('Location: /admin/users/' . $id);
                exit;
        }

        header('Location: /admin/users/' . $id . '?mail_sent=' . urlencode($type));
        exit;
    }

    public function setAwaitingPayment(int $id)
    {
        $this->requireAdmin();
        csrf_validate('admin-pairing', $_POST['_csrf'] ?? null);

        $amount = (float) str_replace(',', '.', $_POST['awaiting_payment'] ?? '0');
        if ($amount < 0) {
            $amount = 0;
        }

        $this->db->prepare("UPDATE users SET awaiting_payment = ? WHERE id = ?")->execute([$amount, $id]);

        header('Location: /admin/users/' . $id);
        exit;
    }

    public function setUserNote(int $id)
    {
        $this->requireAdmin();
        csrf_validate('admin-pairing', $_POST['_csrf'] ?? null);

        $note = trim($_POST['note'] ?? '');
        $this->db->prepare("UPDATE users SET note = ? WHERE id = ?")->execute([$note ?: null, $id]);

        header('Location: /admin/users/' . $id);
        exit;
    }

    public function checkIn(int $id)
    {
        $this->requireAdmin();
        csrf_validate('admin-pairing', $_POST['_csrf'] ?? null);

        $this->db->prepare("UPDATE users SET checked_in = NOW() WHERE id = ?")->execute([$id]);

        header('Location: /admin/users/' . $id);
        exit;
    }

    public function addUserRegistration(int $id)
    {
        $this->requireAdmin();
        csrf_validate('admin-pairing', $_POST['_csrf'] ?? null);

        $workshopId = (int) ($_POST['workshop_id'] ?? 0);
        $status     = $_POST['payment_status'] ?? 'pending';
        $validStatuses = ['pending', 'paid', 'approved', 'upgradable', 'notpaid', 'cancelled', 'refunded', 'skipped'];

        if (!$workshopId || !in_array($status, $validStatuses)) {
            header('Location: /admin/users/' . $id);
            exit;
        }

        $this->db->prepare(
            "INSERT INTO registrations (user_id, workshop_id, payment_status) VALUES (?, ?, ?)"
        )->execute([$id, $workshopId, $status]);

        if ($status === 'paid') {
            \App\Models\Workshop::recountRegistered($this->db, $workshopId);
        }

        header('Location: /admin/users/' . $id);
        exit;
    }

    public function addUserPurchase(int $id)
    {
        $this->requireAdmin();
        csrf_validate('admin-pairing', $_POST['_csrf'] ?? null);

        $ticketId      = (int) ($_POST['ticket_id'] ?? 0);
        $status        = $_POST['payment_status'] ?? 'pending';
        $validStatuses = ['pending', 'paid', 'cancelled'];

        if (!$ticketId || !in_array($status, $validStatuses)) {
            header('Location: /admin/users/' . $id);
            exit;
        }

        $this->db->prepare(
            "INSERT INTO purchases (user_id, item_type, item_id, quantity, payment_status) VALUES (?, 'ticket', ?, 1, ?)"
        )->execute([$id, $ticketId, $status]);

        header('Location: /admin/users/' . $id);
        exit;
    }

    public function setRegistrationStatus()
    {
        $this->requireAdmin();

        if (!csrf_validate('admin-pairing', $_POST['_csrf'] ?? null)) {
            http_response_code(403);
            echo 'Neplatný CSRF token.';
            exit;
        }

        $regId  = (int) ($_POST['registration_id'] ?? 0);
        $status = $_POST['status'] ?? '';
        $allowed = ['pending', 'paid', 'approved', 'upgradable', 'notpaid', 'cancelled', 'refunded', 'skipped'];

        if ($regId > 0 && in_array($status, $allowed)) {
            $extra = $status === 'paid' ? ', paid_at = NOW()' : '';
            $this->db->prepare("UPDATE registrations SET payment_status = ?$extra WHERE id = ?")
                     ->execute([$status, $regId]);
            Workshop::recountRegistered($this->db,0,$regId);
        }

        $back = $_POST['back'] ?? '/admin/pairing';
        header('Location: ' . $back);
        exit;
    }

    public function setPurchaseStatus()
    {
        $this->requireAdmin();

        if (!csrf_validate('admin-pairing', $_POST['_csrf'] ?? null)) {
            http_response_code(403);
            echo 'Neplatný CSRF token.';
            exit;
        }

        $purchId = (int) ($_POST['purchase_id'] ?? 0);
        $status  = $_POST['status'] ?? '';
        $allowed = ['pending', 'paid', 'cancelled'];

        if ($purchId > 0 && in_array($status, $allowed)) {
            $this->db->prepare("UPDATE purchases SET payment_status = ? WHERE id = ?")
                     ->execute([$status, $purchId]);
        }

        $back = $_POST['back'] ?? '/admin/pairing';
        header('Location: ' . $back);
        exit;
    }

    public function completeTransaction()
    {
        $this->requireAdmin();

        if (!csrf_validate('admin-pairing', $_POST['_csrf'] ?? null)) {
            http_response_code(403);
            echo 'Neplatný CSRF token.';
            exit;
        }

        $txId = (int) ($_POST['transaction_id'] ?? 0);
        if ($txId > 0) {
            $tx = TransactionList::findById($this->db, $txId);
            TransactionList::markCompleted($this->db, $txId);

            if ($tx && !empty($tx['variable_symbol'])) {
                $userId = (int) $tx['variable_symbol'];
                if ($userId > 0) {
                    $this->queuePaymentConfirmedMail($userId);
                }
            }
        }

        $back = $_POST['back'] ?? '/admin/pairing';
        header('Location: ' . $back);
        exit;
    }

    /**
     * Build and queue the payment-confirmed e-mail for a user.
     * Includes their paid workshops, tickets and merch.
     */
    private function queuePaymentConfirmedMail(int $userId): void
    {
        $user = \App\Models\User::findById($this->db, $userId);
        if (!$user) {
            return;
        }

        // Paid workshops
        $stmt = $this->db->prepare("
            SELECT w.name
            FROM registrations r
            JOIN workshops w ON r.workshop_id = w.id
            WHERE r.user_id = ? AND r.payment_status = 'paid'
            ORDER BY w.name
        ");
        $stmt->execute([$userId]);
        $workshops = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        // Paid purchases split by type
        $stmt = $this->db->prepare("
            SELECT p.item_type, p.quantity,
                   COALESCE(t.name, m.name) AS name
            FROM purchases p
            LEFT JOIN tickets t ON t.id = p.item_id AND p.item_type = 'ticket'
            LEFT JOIN merch   m ON m.id = p.item_id AND p.item_type = 'merch'
            WHERE p.user_id = ? AND p.payment_status = 'paid'
            ORDER BY p.item_type, p.created_at
        ");
        $stmt->execute([$userId]);
        $purchases = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $tickets = array_values(array_filter($purchases, fn($p) => $p['item_type'] === 'ticket'));
        $merch   = array_values(array_filter($purchases, fn($p) => $p['item_type'] === 'merch'));

        $dashboardUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
            . '://' . $_SERVER['HTTP_HOST'] . '/dashboard';

        MailQueue::sendPaymentConfirmed($this->db, $user['email'], $user['name'], $workshops, $tickets, $merch, $dashboardUrl);
    }

    private function queueScheduleMail(int $userId): void
    {
        $user = \App\Models\User::findById($this->db, $userId);
        if (!$user) {
            return;
        }

        
        // User's paid workshops
        $stmt = $this->db->prepare("
            SELECT w.name, w.instructor, w.timeslot, w.mailnote, w.location
            FROM registrations r
            JOIN workshops w ON r.workshop_id = w.id
            WHERE r.user_id = ? AND r.payment_status = 'paid'
            ORDER BY w.timeslot, w.name
        ");
        $stmt->execute([$userId]);
        $workshopsByTimeslot = [];
    
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $w) {
            $workshopsByTimeslot[$w['timeslot']] = $w;
            if($w['timeslot']=='ABCD'){
                $workshopsByTimeslot['AB'] = $w;
                $workshopsByTimeslot['CD'] = $w;
            }
        }
        
        // User's paid tickets
        $stmt = $this->db->prepare("
            SELECT t.id,t.name, p.quantity, t.mailnote
            FROM purchases p
            JOIN tickets t ON t.id = p.item_id AND p.item_type = 'ticket'
            WHERE p.user_id = ? AND p.payment_status = 'paid'
            ORDER BY t.name
        ");
        $stmt->execute([$userId]);
        $tickets=[];
        foreach($stmt->fetchAll(\PDO::FETCH_ASSOC) as $ticket){
                $tickets[$ticket['id']]=$ticket;

        };

        $dashboardUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
            . '://' . $_SERVER['HTTP_HOST'] . '/dashboard';

        MailQueue::addWithTemplate($this->db, $user['email'], 'Informace k festivalu Improtřesk 2026', 'schedule.twig', [
            'name'         => $user['name'],
            'workshops'         => $workshopsByTimeslot,
            'tickets'      => $tickets,
            'wa_skaut' => $_ENV['WA_SKAUT'],
            'wa_default' => $_ENV['WA_DEFAULT'],
            'user'         => $user,
            'dashboardUrl' => $dashboardUrl,
        ]);
    }

    public function staticBlocks()
    {
        $this->requireAdmin();

        $success = $_SESSION['static_blocks_success'] ?? null;
        $error   = $_SESSION['static_blocks_error']   ?? null;
        unset($_SESSION['static_blocks_success'], $_SESSION['static_blocks_error']);

        $stmt = $this->db->query("SELECT * FROM static_blocks ORDER BY block_name");

        echo $this->twig->render('pages/admin-static-blocks.twig', [
            'user'        => $this->getCurrentUser(),
            'active_page' => 'admin',
            'blocks'      => $stmt->fetchAll(\PDO::FETCH_ASSOC),
            'csrf'        => csrf_token('admin-static-blocks'),
            'success'     => $success,
            'error'       => $error,
        ]);
    }

    public function saveStaticBlock()
    {
        $this->requireAdmin();

        if (!csrf_validate('admin-static-blocks', $_POST['_csrf'] ?? null)) {
            http_response_code(403);
            echo 'Neplatný CSRF token.';
            exit;
        }

        $id          = (int) ($_POST['block_id'] ?? 0);
        $blockName   = trim($_POST['block_name'] ?? '');
        $title       = trim($_POST['title'] ?? '');
        $description = trim($_POST['block_description'] ?? '');
        $content     = $_POST['content'] ?? '';
        $isActive    = isset($_POST['is_active']) ? 1 : 0;

        if ($blockName === '' || $title === '') {
            $_SESSION['static_blocks_error'] = 'Název bloku a nadpis jsou povinné.';
            header('Location: /admin/static-blocks');
            exit;
        }

        $data = [
            'block_name'        => $blockName,
            'title'             => $title,
            'block_description' => $description !== '' ? $description : null,
            'content'           => $content !== '' ? $content : null,
            'is_active'         => $isActive,
        ];

        if ($id > 0) {
            \App\Models\StaticBlock::update($this->db, $id, $data);
            $_SESSION['static_blocks_success'] = 'Blok byl uložen.';
        } else {
            \App\Models\StaticBlock::create($this->db, $data);
            $_SESSION['static_blocks_success'] = 'Blok byl vytvořen.';
        }

        header('Location: /admin/static-blocks');
        exit;
    }

    public function deleteStaticBlock()
    {
        $this->requireAdmin();

        if (!csrf_validate('admin-static-blocks', $_POST['_csrf'] ?? null)) {
            http_response_code(403);
            echo 'Neplatný CSRF token.';
            exit;
        }

        $id = (int) ($_POST['block_id'] ?? 0);
        if ($id > 0) {
            \App\Models\StaticBlock::delete($this->db, $id);
            $_SESSION['static_blocks_success'] = 'Blok byl smazán.';
        }

        header('Location: /admin/static-blocks');
        exit;
    }

    public function ticketSales()
    {
        $this->requireAdmin();

        $stmt = $this->db->query("
            SELECT t.id AS ticket_id, t.name AS ticket_name, t.price,
                   p.id AS purchase_id, p.quantity, p.payment_status, p.created_at,
                   u.id AS user_id, u.name AS user_name, u.email AS user_email,
                   u.note AS user_note, u.checked_in
            FROM tickets t
            LEFT JOIN purchases p ON p.item_id = t.id AND p.item_type = 'ticket' AND p.payment_status = 'paid'
            LEFT JOIN users u ON u.id = p.user_id
            ORDER BY t.id ASC, p.created_at ASC
        ");

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Group by ticket type
        $byTicket = [];
        foreach ($rows as $row) {
            $tid = $row['ticket_id'];
            if (!isset($byTicket[$tid])) {
                $byTicket[$tid] = [
                    'id'    => $tid,
                    'name'  => $row['ticket_name'],
                    'price' => $row['price'],
                    'sales' => [],
                ];
            }
            if ($row['purchase_id'] !== null) {
                $byTicket[$tid]['sales'][] = $row;
            }
        }

        $tickets = $this->db->query("SELECT id, mailnote FROM tickets ORDER BY id ASC")->fetchAll(\PDO::FETCH_ASSOC);
        $mailnotes = [];
        foreach ($tickets as $t) {
            $mailnotes[$t['id']] = $t['mailnote'];
        }

        echo $this->twig->render('pages/admin-tickets.twig', [
            'user'       => $this->getCurrentUser(),
            'active_page' => 'admin',
            'by_ticket'  => array_values($byTicket),
            'mailnotes'  => $mailnotes,
        ]);
    }

    public function updateWorkshopMailnote($id)
    {
        $this->requireAdmin();

        $id       = (int) $id;
        $mailnote = trim($_POST['mailnote'] ?? '');

        $stmt = $this->db->prepare("UPDATE workshops SET mailnote = ? WHERE id = ?");
        $stmt->execute([$mailnote !== '' ? $mailnote : null, $id]);

        header('Location: /admin/workshops');
        exit;
    }

    public function updateTicketMailnote($id)
    {
        $this->requireAdmin();

        $id       = (int) $id;
        $mailnote = trim($_POST['mailnote'] ?? '');

        $stmt = $this->db->prepare("UPDATE tickets SET mailnote = ? WHERE id = ?");
        $stmt->execute([$mailnote !== '' ? $mailnote : null, $id]);

        header('Location: /admin/tickets');
        exit;
    }

    public function imageManager(): void
    {
        $this->requireAdmin();

        $imgDir = realpath(__DIR__ . '/../../public/img') . '/';

        // Build sets of IDs that already have an image
        $pairedPeople        = [];
        $pairedProgramInfo   = [];
        $pairedStaticBlocks  = [];
        $pairedChildrenItem  = [];
        foreach (glob($imgDir . '*.{jpg,png}', GLOB_BRACE) ?: [] as $path) {
            $name = basename($path);
            if (str_contains($name, ':')) continue;
            if (preg_match('/^c(\d+)\.(jpg|png)$/i', $name, $m)) {
                $pairedProgramInfo[(int) $m[1]] = $name;
            } elseif (preg_match('/^s(\d+)\.(jpg|png)$/i', $name, $m)) {
                $pairedStaticBlocks[(int) $m[1]] = $name;
            } elseif (preg_match('/^d(\d+)\.(jpg|png)$/i', $name, $m)) {
                $pairedChildrenItem[(int) $m[1]] = $name;
            } elseif (preg_match('/^(\d+)\.(jpg|png)$/i', $name, $m)) {
                $pairedPeople[(int) $m[1]] = $name;
            }
        }

        // People without an image
        $rows = $this->db->query("SELECT id, name, section FROM people ORDER BY id")->fetchAll(\PDO::FETCH_ASSOC);
        $unpairedPeople = [];
        foreach ($rows as $r) {
            $id = (int) $r['id'];
            if (!isset($pairedPeople[$id])) {
                $unpairedPeople[] = $r;
            }
        }

        // Program info items without an image
        $rows = $this->db->query("SELECT id, name FROM program_info ORDER BY id")->fetchAll(\PDO::FETCH_ASSOC);
        $unpairedProgramInfo = [];
        foreach ($rows as $r) {
            $id = (int) $r['id'];
            if (!isset($pairedProgramInfo[$id])) {
                $unpairedProgramInfo[] = $r;
            }
        }

        // Static blocks without an image
        $rows = $this->db->query("SELECT id, block_name, title FROM static_blocks ORDER BY id")->fetchAll(\PDO::FETCH_ASSOC);
        $unpairedStaticBlocks = [];
        foreach ($rows as $r) {
            $id = (int) $r['id'];
            if (!isset($pairedStaticBlocks[$id])) {
                $unpairedStaticBlocks[] = $r;
            }
        }

        //Children items without an image
        $rows = $this->db->query("SELECT id, title name FROM children_items ORDER BY id")->fetchAll(\PDO::FETCH_ASSOC);
        $unpairedChildrenItems = [];
        foreach ($rows as $r) {
            $id = (int) $r['id'];
            if (!isset($pairedChildrenItem[$id])) {
                $unpairedChildrenItems[] = $r;
            }
        }

        // All existing images for the gallery
        $images = [];
        foreach (array_merge($pairedPeople, $pairedProgramInfo, $pairedStaticBlocks,$pairedChildrenItem) as $name) {
            $images[] = ['name' => $name, 'size' => filesize($imgDir . $name)];
        }
        usort($images, fn($a, $b) => strnatcmp($a['name'], $b['name']));

        $success = $_SESSION['admin_images_success'] ?? null;
        $error   = $_SESSION['admin_images_error']   ?? null;
        unset($_SESSION['admin_images_success'], $_SESSION['admin_images_error']);

        echo $this->twig->render('pages/admin-images.twig', [
            'user'                  => $this->getCurrentUser(),
            'active_page'           => 'admin',
            'unpaired_people'       => $unpairedPeople,
            'unpaired_program'      => $unpairedProgramInfo,
            'unpaired_static_blocks'=> $unpairedStaticBlocks,
            'unpaired_children_item'=> $unpairedChildrenItems,
            'images'                => $images,
            'csrf'                  => csrf_token('admin-images'),
            'success'               => $success,
            'error'                 => $error,
        ]);
    }

    public function uploadImage(): void
    {
        $this->requireAdmin();

        if (!csrf_validate('admin-images', $_POST['_csrf'] ?? null)) {
            http_response_code(403);
            echo 'Invalid token.';
            exit;
        }

        $targetType = $_POST['target_type'] ?? '';
        $targetId   = (int) ($_POST['target_id'] ?? 0);

        if (!in_array($targetType, ['person', 'program_info', 'static_block','children_item'], true) || $targetId <= 0) {
            $_SESSION['admin_images_error'] = 'Neplatný cíl nahrání.';
            header('Location: /admin/images');
            exit;
        }

        // Verify target exists in DB
        $table = match($targetType) {
            'person'       => 'people',
            'program_info' => 'program_info',
            'static_block' => 'static_blocks',
            'children_item' => 'children_items'
        };
        $stmt = $this->db->prepare("SELECT id FROM {$table} WHERE id = ?");
        $stmt->execute([$targetId]);
        if (!$stmt->fetch()) {
            $_SESSION['admin_images_error'] = 'Záznam v databázi nenalezen.';
            header('Location: /admin/images');
            exit;
        }

        $imgDir = realpath(__DIR__ . '/../../public/img') . '/';
        $prefix = match($targetType) {
            'program_info' => "c{$targetId}",
            'static_block' => "s{$targetId}",
            'children_item' => "d{$targetId}",
            default        => (string) $targetId,
        };

        // Reject if an image already exists for this target
        foreach (['jpg',  'png'] as $e) {
            if (file_exists($imgDir . $prefix . '.' . $e)) {
                $_SESSION['admin_images_error'] = "Obrázek pro tento záznam (ID {$targetId}) již existuje.";
                header('Location: /admin/images');
                exit;
            }
        }

        $file = $_FILES['image'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['admin_images_error'] = 'Soubor nebyl nahrán.';
            header('Location: /admin/images');
            exit;
        }

        if ($file['size'] > 204800) {
            $_SESSION['admin_images_error'] = 'Soubor je příliš velký (max. 200 kB).';
            header('Location: /admin/images');
            exit;
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']);
        if (!in_array($mime, ['image/jpeg', 'image/png'], true)) {
            $_SESSION['admin_images_error'] = 'Povoleny jsou pouze soubory JPG a PNG.';
            header('Location: /admin/images');
            exit;
        }

        $ext      = $mime === 'image/png' ? 'png' : 'jpg';
        $filename = $prefix . '.' . $ext;

        if (!move_uploaded_file($file['tmp_name'], $imgDir . $filename)) {
            $_SESSION['admin_images_error'] = 'Nepodařilo se uložit soubor.';
            header('Location: /admin/images');
            exit;
        }

        $_SESSION['admin_images_success'] = "Soubor byl nahrán jako {$filename}.";
        header('Location: /admin/images');
        exit;
    }

}
