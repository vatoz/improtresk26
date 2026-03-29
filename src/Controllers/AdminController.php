<?php
namespace App\Controllers;

use App\Models\MailQueue;
use App\Models\TransactionList;
use App\Services\FioService;

class AdminController extends BaseController
{
    public function participants()
    {
        $this->requireAdmin();

        $stmt = $this->db->query("
            SELECT u.name, u.email, r.payment_status, w.name AS workshop_name, r.created_at
            FROM registrations r
            JOIN users u ON r.user_id = u.id
            LEFT JOIN workshops w ON r.workshop_id = w.id
            ORDER BY r.created_at DESC
        ");

        echo $this->twig->render('pages/admin.twig', [
            'user'         => $this->getCurrentUser(),
            'active_page'  => 'admin',
            'participants' => $stmt->fetchAll(\PDO::FETCH_ASSOC),
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
    {
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
                }
            }
        }

        header('Location: /admin/payments');
        exit;
    }

    /**
     * Enrich pending transactions with matched user and pending workshop sum.
     * variable_symbol encodes user_id as: variable_symbol - current_year = user_id
     */
    private function enrichTransactions(array $transactions): array
    {
        $year = (int) date('Y');

        foreach ($transactions as &$t) {
            $t['matched_user']  = null;
            $t['pending_sum']   = null;
            $t['amount_int']    = (int) $t['amount'];
            $t['can_mark_paid'] = false;

            if (empty($t['variable_symbol'])) {
                continue;
            }

            $userId = (int) $t['variable_symbol'] - $year;
            if ($userId <= 0) {
                continue;
            }

            $stmt = $this->db->prepare("SELECT id, name, email FROM users WHERE id = ? LIMIT 1");
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
                u.name  AS user_name,
                u.email AS user_email,
                r.payment_status,
                r.variable_symbol
            FROM workshops w
            LEFT JOIN timeslots ts ON ts.code = w.timeslot
            LEFT JOIN registrations r ON w.id = r.workshop_id
                AND r.payment_status IN ('paid', 'approved')
            LEFT JOIN users u ON r.user_id = u.id
            WHERE w.is_active = 1
            ORDER BY ts.start_datetime, w.name, u.name
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
                    'name'   => $row['user_name'],
                    'email'  => $row['user_email'],
                    'status' => $row['payment_status'],
                    'vs'     => $row['variable_symbol'],
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
}
