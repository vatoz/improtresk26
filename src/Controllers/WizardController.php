<?php
namespace App\Controllers;

use App\Models\Workshop;
use App\Models\Timeslot;
use vplacek\QRPlatba\QRPlatba;

class WizardController extends BaseController
{
    /**
     * Step 1 – Workshop selection, one timeslot at a time.
     *
     * GET  /wizard/workshops          → redirect to first remaining free timeslot
     * GET  /wizard/workshops?slot=X   → show timeslot X with toggle buttons
     * POST /wizard/workshops          → register selected workshops (or skip), then redirect
     */
    public function workshops()
    {
        $this->requireAuth();
        $user = $this->getCurrentUser();

        if (!isset($_SESSION['wizard_skipped_slots'])) {
            $_SESSION['wizard_skipped_slots'] = [];
        }
        if (isset($_GET['slot_done'])){
            $_SESSION['wizard_skipped_slots'][]=$_GET['slot_done'];
        }

         
        $requestedSlot = $_GET['slot'] ?? ($_POST['slot']?? null);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!csrf_validate('wizard', $_POST['_csrf'] ?? null)) {
                $_SESSION['error'] = 'Neplatný bezpečnostní token. Zkuste to znovu.';
                header('Location: /wizard/workshops?csrferrror');
                exit;
            }

            $selectedWorkshopId =intval(  $_POST['workshop_id'] ?? 0);
            if($selectedWorkshopId){                    
                  $workshop = Workshop::findById($this->db, $selectedWorkshopId);
                    if ($workshop && $workshop['is_active']) {
                        

                        error_log("kontrol");
                    $stmt = $this->db->prepare("
                        SELECT id FROM registrations
                        WHERE user_id = ? AND workshop_id = ? 
                        AND payment_status != 'cancelled'
                    ");
                    $stmt->execute([$user['id'], $selectedWorkshopId]);
                    if (!$stmt->fetch()) {
                        error_log("přidej");
                        $this->db->prepare("
                        INSERT INTO registrations (user_id, workshop_id, payment_status) VALUES (?, ?, 'pending')
                    ")->execute([$user['id'], $selectedWorkshopId]); 


                    }                        

                }                  
            }
                
            $canceledWorkshopId =intval(  $_POST['cancel_workshop_id'] ?? 0);
            if($canceledWorkshopId){
                
                $this->db->prepare("
                        delete from registrations where user_id = ? AND workshop_id =? and payment_status!='paid'
                    ")->execute([$user['id'], $canceledWorkshopId]);
            }
            


            if(!$selectedWorkshopId && !$canceledWorkshopId ){
                // User explicitly skipped this slot
                if ($requestedSlot && !in_array($requestedSlot, $_SESSION['wizard_skipped_slots'], true)) {
                    $_SESSION['wizard_skipped_slots'][] = $requestedSlot;
                }
            } 
        
        }

        // Compute remaining free timeslots (no uncancelled reg + not skipped)
        $allTimeslots           = Timeslot::getAll($this->db);
        $userRegisteredTimeslots = Workshop::getUserRegisteredTimeslots($this->db, $user['id']);
        $skipped                = $_SESSION['wizard_skipped_slots'];

        $freeTimeslots = array_values(array_filter(
            $allTimeslots,
            fn($ts) => !in_array($ts['code'], $userRegisteredTimeslots, true)
                    && !in_array($ts['code'], $skipped, true)
        ));
        

        foreach($userRegisteredTimeslots as $rTimeslot){
            foreach($freeTimeslots as $ft=>$FTimeslot){
                if(Workshop::timeslotsOverlap($FTimeslot['code'], $rTimeslot)){
                    //error_log("Odebírám překryvný timeslot (".$rTimeslot.", ".$FTimeslot['code'].")" );
                    unset($freeTimeslots[$ft]);                    
                }
            }
        }
        $freeTimeslots = array_values($freeTimeslots);
        //error_log(var_export($freeTimeslots,true)) ;
    


        // No slot requested → redirect to first free, or move on
       

        if (!$requestedSlot) {
            if (empty($freeTimeslots)) {
                unset($_SESSION['wizard_skipped_slots']);
                header('Location: /wizard/tickets');
                exit;
            }
            $requestedSlot=$freeTimeslots[0]['code'];            
        }

        // Validate the requested slot exists
        $currentTimeslot = null;
        foreach ($allTimeslots as $ts) {
            if ($ts['code'] === $requestedSlot) {
                $currentTimeslot = $ts;
                break;
            }
        }
        if (!$currentTimeslot) {
            unset($_SESSION['wizard_skipped_slots']);
            header('Location: /wizard/tickets');
            exit;
        }

        // Workshops for this timeslot
        $workshopsByTimeslot = Workshop::getAvailableGroupedByTimeslot($this->db);
        $currentWorkshops    = $workshopsByTimeslot[$currentTimeslot['code']] ?? [];
        
        $userWorkshops= Workshop::getUserRegistrations($this->db, $user['id']);
        foreach($currentWorkshops as $workshopId=>$workshop){
        error_log(var_export($workshop,true))    ;    
            foreach($userWorkshops as $uW){
    
                if($uW['workshop_id']==$currentWorkshops[$workshopId]['id']){
                     $currentWorkshops[$workshopId]['alreadyRegistered'] =$uW['payment_status'];
                }
            }

        }





        // Progress numbers
        $totalTimeslots = count($allTimeslots);
        $doneTimeslots  = $totalTimeslots - count($freeTimeslots);

        $session = $this->getSessionMessages();

        echo $this->twig->render('pages/wizard-workshops.twig', [
            'user'            => $user,
            'active_page'     => 'wizard',
            'timeslot'        => $currentTimeslot,
            'workshops'       => $currentWorkshops,
            'total_timeslots' => $totalTimeslots,
            'done_timeslots'  => $doneTimeslots,
            'csrf'            => csrf_token('wizard'),
            'error'           => $session['error'],
            'success'         => $session['success'],
        ]);
    }

    /**
     * Step 2 – Ticket purchase.
     * Skipped automatically when the user already has any uncancelled workshop registration.
     */
    public function tickets()
    {
        $this->requireAuth();
        $user = $this->getCurrentUser();

        // Check for existing uncancelled workshop registrations
        $stmt = $this->db->prepare("
            SELECT COUNT(*) AS cnt FROM registrations
            WHERE user_id = ? AND payment_status != 'cancelled'
        ");
        $stmt->execute([$user['id']]);
        $hasRegistrations = (int)$stmt->fetch()['cnt'] > 0;

        if ($hasRegistrations) {
            //header('Location: /wizard/merch');
            echo $this->twig->render('pages/wizard-tickets.twig', [
            'user'               => $user,
            'active_page'        => 'wizard',
            'noticket'           => true,
            'csrf'               => csrf_token('wizard'),
            'error'              => $session['error'],
            'success'            => $session['success'],
            ]);


            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!csrf_validate('wizard', $_POST['_csrf'] ?? null)) {
                $_SESSION['error'] = 'Neplatný bezpečnostní token. Zkuste to znovu.';
                header('Location: /wizard/tickets');
                exit;
            }

            $selectedTicketIds = $_POST['ticket_ids'] ?? [];

            foreach ($selectedTicketIds as $ticketId) {
                $ticketId = (int)$ticketId;
                if (!$ticketId) {
                    continue;
                }

                $stmt = $this->db->prepare("SELECT id FROM tickets WHERE id = ? AND is_active = 1");
                $stmt->execute([$ticketId]);
                if (!$stmt->fetch()) {
                    continue;
                }

                // Tickets are unique per user – skip if already ordered
                $stmt = $this->db->prepare("
                    SELECT id FROM purchases
                    WHERE user_id = ? AND item_type = 'ticket' AND item_id = ? AND payment_status != 'cancelled'
                ");
                $stmt->execute([$user['id'], $ticketId]);
                if ($stmt->fetch()) {
                    continue;
                }

                $this->db->prepare("
                    INSERT INTO purchases (user_id, item_type, item_id) VALUES (?, 'ticket', ?)
                ")->execute([$user['id'], $ticketId]);
            }

            header('Location: /wizard/merch');
            exit;
        }

        $stmt = $this->db->query("SELECT * FROM tickets WHERE is_active = 1 ORDER BY date, time");
        $tickets = $stmt->fetchAll();

        $stmt = $this->db->prepare("
            SELECT item_id FROM purchases
            WHERE user_id = ? AND item_type = 'ticket' AND payment_status != 'cancelled'
        ");
        $stmt->execute([$user['id']]);
        $userPurchasedIds = array_column($stmt->fetchAll(), 'item_id');

        $session = $this->getSessionMessages();

        echo $this->twig->render('pages/wizard-tickets.twig', [
            'user'               => $user,
            'active_page'        => 'wizard',
            'tickets'            => $tickets,
            'user_purchased_ids' => $userPurchasedIds,
            'csrf'               => csrf_token('wizard'),
            'error'              => $session['error'],
            'success'            => $session['success'],
        ]);
    }

    /**
     * Step 3 – Merch selection.
     */
    public function merch()
    {
        $this->requireAuth();
        $user = $this->getCurrentUser();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!csrf_validate('wizard', $_POST['_csrf'] ?? null)) {
                $_SESSION['error'] = 'Neplatný bezpečnostní token. Zkuste to znovu.';
                header('Location: /wizard/merch');
                exit;
            }

            $selectedMerchIds = $_POST['merch_ids'] ?? [];

            foreach ($selectedMerchIds as $merchId) {
                $merchId = (int)$merchId;
                if (!$merchId) {
                    continue;
                }

                $stmt = $this->db->prepare("SELECT id FROM merch WHERE id = ? AND is_active = 1");
                $stmt->execute([$merchId]);
                if (!$stmt->fetch()) {
                    continue;
                }

                // For merch, increment quantity if already ordered
                $stmt = $this->db->prepare("
                    SELECT id FROM purchases
                    WHERE user_id = ? AND item_type = 'merch' AND item_id = ? AND payment_status != 'cancelled'
                ");
                $stmt->execute([$user['id'], $merchId]);
                $existing = $stmt->fetch();

                if ($existing) {
                    $this->db->prepare("UPDATE purchases SET quantity = quantity + 1 WHERE id = ?")
                        ->execute([$existing['id']]);
                } else {
                    $this->db->prepare("
                        INSERT INTO purchases (user_id, item_type, item_id) VALUES (?, 'merch', ?)
                    ")->execute([$user['id'], $merchId]);
                }
            }

            header('Location: /wizard/payment');
            exit;
        }

        $stmt = $this->db->query("SELECT * FROM merch WHERE is_active = 1 ORDER BY name");
        $items = $stmt->fetchAll();

        $stmt = $this->db->prepare("
            SELECT item_id FROM purchases
            WHERE user_id = ? AND item_type = 'merch' AND payment_status != 'cancelled' AND payment_status != 'paid'
        ");
        $stmt->execute([$user['id']]);
        $userPurchasedIds = array_column($stmt->fetchAll(), 'item_id');

        $session = $this->getSessionMessages();

        echo $this->twig->render('pages/wizard-merch.twig', [
            'user'               => $user,
            'active_page'        => 'wizard',
            'items'              => $items,
            'user_purchased_ids' => $userPurchasedIds,
            'csrf'               => csrf_token('wizard'),
            'error'              => $session['error'],
            'success'            => $session['success'],
        ]);
    }

    /**
     * Step 4 – Payment summary.
     * Shows all pending registrations and purchases with total amount and QR code.
     */
    public function payment()
    {
        $this->requireAuth();
        $user = $this->getCurrentUser();

        // All pending/approved workshop registrations
        $stmt = $this->db->prepare("
            SELECT r.*, w.name AS workshop_name, w.price,
                   ts.name AS timeslot_name, ts.code AS timeslot_code
            FROM registrations r
            LEFT JOIN workshops w ON r.workshop_id = w.id
            LEFT JOIN timeslots ts ON ts.code = w.timeslot
            WHERE r.user_id = ? AND r.payment_status IN ('pending', 'approved')
            ORDER BY COALESCE(ts.order, 2147483647), r.created_at
        ");
        $stmt->execute([$user['id']]);
        $registrations = $stmt->fetchAll();

        // All pending/approved purchases (tickets + merch)
        $stmt = $this->db->prepare("
            SELECT p.*,
                   COALESCE(t.name, m.name) AS item_name,
                   COALESCE(t.price, m.price) AS item_price
            FROM purchases p
            LEFT JOIN tickets t ON t.id = p.item_id AND p.item_type = 'ticket'
            LEFT JOIN merch m ON m.id = p.item_id AND p.item_type = 'merch'
            WHERE p.user_id = ? AND p.payment_status IN ('pending', 'approved')
            ORDER BY p.item_type, p.created_at
        ");
        $stmt->execute([$user['id']]);
        $purchases = $stmt->fetchAll();

        if (empty($registrations) && empty($purchases)) {
            header('Location: /dashboard');
            exit;
        }

        // Calculate total
        $timeslots_taken=[];
        $total = 0;
        foreach ($registrations as $i=>$r) {
            foreach($timeslots_taken as $slot){
                if(Workshop::timeslotsOverlap($slot,$r['timeslot_code'] )){
                    $r['price']=0;
                    $registrations[$i]['payment_status']="timeblock";
                    $registrations[$i]['price']=0;
                }
            }
            $total += (float)$r['price'];
            $timeslots_taken[]=$r['timeslot_code'];
        }

        foreach ($purchases as $p) {
            $total += (float)$p['item_price'] * (int)$p['quantity'];
        }

        $paymentConfig = payment_config();
        $variableSymbol = str_pad($user['id'], 10, '0', STR_PAD_LEFT);

        $qrPlatba = new QRPlatba();
        $qrPlatba->setIban($paymentConfig['iban'])
            ->setAmount($total)
            ->setScale(5)
            ->setVariableSymbol($variableSymbol);

        $paymentDetails = [
            'account_number'  => $paymentConfig['iban'],
            'variable_symbol' => $variableSymbol,
            'amount'          => $total,
            'currency'        => $paymentConfig['currency'],
            'message'         => $paymentConfig['message'],
        ];

        echo $this->twig->render('pages/wizard-payment.twig', [
            'user'          => $user,
            'active_page'   => 'wizard',
            'registrations' => $registrations,
            'purchases'     => $purchases,
            'total'         => $total,
            'payment'       => $paymentDetails,
            'qr'            => 'data:image/png;base64,' . base64_encode($qrPlatba->generateQr()),
        ]);
    }
}
