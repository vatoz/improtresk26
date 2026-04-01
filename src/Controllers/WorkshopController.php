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

   
}

