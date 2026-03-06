<?php
namespace App\Controllers;
use vplacek\QRPlatba\QRPlatba;

class PaymentController extends BaseController
{
    public function index()
    {
        $this->requireAuth();

        $user = $this->getCurrentUser();

        // Fetch pending payment data
        $stmt = $this->db->prepare("
            SELECT r.*, w.name as workshop_name, w.price
            FROM registrations r
            LEFT JOIN workshops w ON r.workshop_id = w.id
            WHERE r.user_id = ? AND r.payment_status = 'approved'
            ORDER BY r.created_at DESC LIMIT 1
        ");
        $stmt->execute([$user['id']]);
        $registration = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$registration) {
            header('Location: /dashboard');
            exit;
        }

        // Generate payment details from environment
        $paymentConfig = payment_config();
        $paymentDetails = [
            'iban' => $paymentConfig['iban'],
            'account_number' => $paymentConfig['iban'], // Can format this for display
            'variable_symbol' => str_pad($registration['id'], 10, '0', STR_PAD_LEFT),
            'amount' => $registration['price'],
            'currency' => $paymentConfig['currency'],
            'message' => $paymentConfig['message']
        ];

        
    $qrPlatba = new QRPlatba();
    $qrPlatba->setIban($paymentConfig['iban'])
	->setAmount($registration['price'])
	->setScale(5) //velikost QR kodu
	->setVariableSymbol(str_pad($registration['id'], 10, '0', STR_PAD_LEFT));



        echo $this->twig->render('pages/payment.twig', [
            'user' => $user,
            'active_page' => 'payment',
            'registration' => $registration,
            'payment' => $paymentDetails,
            'qr'=>"data:image/png;base64,". base64_encode($qrPlatba->generateQr())
        ]);
    }
}
