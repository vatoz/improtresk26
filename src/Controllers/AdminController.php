<?php
namespace App\Controllers;

use App\Models\TransactionList;
use App\Services\FioService;
use App\Services\LotteryService;

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

    public function lottery()
    {
        $this->requireAdmin();

        echo $this->twig->render('pages/admin-lottery.twig', [
            'user'        => $this->getCurrentUser(),
            'active_page' => 'admin',
            'results'     => null,
        ]);
    }

    public function runLottery()
    {
        $this->requireAdmin();

        $lottery = new LotteryService($this->db);
        $results = $lottery->run();

        echo $this->twig->render('pages/admin-lottery.twig', [
            'user'        => $this->getCurrentUser(),
            'active_page' => 'admin',
            'results'     => $results,
        ]);
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
}
