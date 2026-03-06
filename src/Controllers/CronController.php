<?php
namespace App\Controllers;

use App\Services\CronService;

class CronController extends BaseController
{
    public function run()
    {
        $secret = $_ENV['CRON_SECRET'] ?? '';

        if ($secret === '' || ($_GET['secret'] ?? '') !== $secret) {
            http_response_code(403);
            echo 'Forbidden';
            exit;
        }

        (new CronService($this->db, $this->twig))->run();

        echo 'OK';
    }
}
