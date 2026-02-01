<?php
namespace App\Controllers;

class AdminController extends BaseController
{
    public function index()
    {
        $this->requireAdmin();

        // Fetch participants
        $stmt = $this->db->query("
            SELECT u.name, u.email, r.payment_status, w.name as workshop_name, r.created_at
            FROM registrations r
            JOIN users u ON r.user_id = u.id
            LEFT JOIN workshops w ON r.workshop_id = w.id
            ORDER BY r.created_at DESC
        ");
        $participants = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Fetch workshops with participant counts
        $stmt = $this->db->query("
            SELECT w.*,
                   COUNT(r.id) as registered_count,
                   SUM(CASE WHEN r.payment_status = 'paid' THEN 1 ELSE 0 END) as paid_count
            FROM workshops w
            LEFT JOIN registrations r ON w.id = r.workshop_id
            GROUP BY w.id
        ");
        $workshops = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        echo $this->twig->render('pages/admin.twig', [
            'user' => $this->getCurrentUser(),
            'active_page' => 'admin',
            'participants' => $participants,
            'workshops' => $workshops
        ]);
    }

    public function exportCsv()
    {
        $this->requireAdmin();

        $stmt = $this->db->query("
            SELECT u.name, u.email, w.name as workshop, r.payment_status, r.created_at
            FROM registrations r
            JOIN users u ON r.user_id = u.id
            LEFT JOIN workshops w ON r.workshop_id = w.id
        ");
        $data = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=registrations.csv');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['Name', 'Email', 'Workshop', 'Payment Status', 'Created At']);

        foreach ($data as $row) {
            fputcsv($output, $row);
        }

        fclose($output);
        exit;
    }
}
