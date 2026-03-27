<?php
namespace App\Controllers;

use App\Models\Workshop;
use App\Models\Timeslot;
use App\Models\Person;

class WorkshopController extends BaseController
{
    public function index()
    {
        $workshops = Workshop::getAll($this->db);
        $timeslots = Timeslot::getAll($this->db);

        $workshopsByTimeslot = [];
        $workshopsNoTimeslot = [];
        foreach ($workshops as $workshop) {
            if ($workshop['timeslot']) {
                $workshopsByTimeslot[$workshop['timeslot']][] = $workshop;
            } else {
                $workshopsNoTimeslot[] = $workshop;
            }
        }

        $peopleByWorkshop = Person::getGroupedByWorkshop($this->db);

        $photo=[];
        $dir = __DIR__ . '/../../public/img/';   // cesta ke složce
        $allowedPattern = '/^(\d+)\.(jpg|png)$/i';
        $files = scandir($dir);
        foreach ($files as $file) {
            if (preg_match($allowedPattern, $file, $matches)) {
                $photo[(int)$matches[1]]=$file;
            }
        }
        //$photo[1]="logo.png";



        echo $this->twig->render('pages/workshops.twig', [
            'user'                  => $this->getCurrentUser(),
            'active_page'           => 'workshops',
            'timeslots'             => $timeslots,
            'workshops_by_timeslot' => $workshopsByTimeslot,
            'workshops_no_timeslot' => $workshopsNoTimeslot,
            'photo' => $photo,
            'people_by_workshop'    => $peopleByWorkshop,
        ]);
    }


    public function unregister()
    {
        $this->requireAuth();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $workshopId = $_POST['workshop_id'] ?? null;
        }
        $user = $this->getCurrentUser();
        Workshop::unregister($this->db ,$user['id'], $workshopId);    

        $this->chooseWorkshops();

    }

    public function register()
    {
        $this->requireAuth();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $workshopId = $_POST['workshop_id'] ?? null;
           

            if (!$workshopId) {
                $_SESSION['error'] = 'Vyberte prosím workshop.';
            }else{
                if (!csrf_validate('choose_workshops', $_POST['_csrf'] ?? null)) {
                $_SESSION['error'] = 'Neplatný bezpečnostní token. Zkuste to znovu.';
                }else{
            
                $user = $this->getCurrentUser();

                
                Workshop::register($this->db ,$user['id'], $workshopId);                  

            }
        }
        }
            $this->chooseWorkshops();

    }

    public function chooseWorkshops()
    {
        $this->requireAuth();
        $user = $this->getCurrentUser();


        // Build data for the view
        $timeslots = Timeslot::getAll($this->db);
        $workshopsByTimeslot = Workshop::getAvailableGroupedByTimeslot($this->db);

        // IDs of workshops the user is already registered for
        $stmt = $this->db->prepare("
            SELECT workshop_id FROM registrations
            WHERE user_id = ? AND payment_status != 'cancelled'
        ");
        $stmt->execute([$user['id']]);
        $userRegisteredIds = array_column($stmt->fetchAll(), 'workshop_id');

        $session = $this->getSessionMessages();

        echo $this->twig->render('pages/choose-workshops.twig', [
            'user'                  => $user,
            'active_page'           => 'choose_workshops',
            'timeslots'             => $timeslots,
            'workshops_by_timeslot' => $workshopsByTimeslot,
            'user_registered_ids'   => $userRegisteredIds,
            'csrf'                  => csrf_token('choose_workshops'),
            'error'                 => $session['error'],
            'success'               => $session['success'],
        ]);
    }
}

