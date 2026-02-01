<?php
namespace App\Controllers;

class DashboardController extends BaseController
{
    public function index()
    {
        $this->requireAuth();

        $user = $this->getCurrentUser();

        // Fetch user's registration data
        $stmt = $this->db->prepare("
            SELECT r.*, w.name as workshop_name, w.price
            FROM registrations r
            LEFT JOIN workshops w ON r.workshop_id = w.id
            WHERE r.user_id = ?
        ");
        $stmt->execute([$user['id']]);
        $registration = $stmt->fetch(\PDO::FETCH_ASSOC);

        echo $this->twig->render('pages/dashboard.twig', [
            'user' => $user,
            'active_page' => 'dashboard',
            'registration' => $registration
        ]);
    }
}
