<?php
namespace App\Services;

use PDO;
use Twig\Environment as Twig;

/**
 * CronService
 *
 * Probability-based pseudo-cron that runs inside normal web requests.
 * Each service has its own minimum interval stored in the `settings` table
 * under a dedicated key so runs are not duplicated across requests.
 *
 * Intervals:
 *   MailService    – 5 minutes   (key: cron_last_run_mail)
 *   FioService     – 1 hour      (key: cron_last_run_fio)
 *   UnpaidService  – 1 hour      (key: cron_last_run_unpaid)
 *
 * Note: RollService is called directly by UnpaidService after each eviction,
 * so it does not need its own cron slot.
 */
class CronService
{
    private const JOBS = [
        'mail'    => ['key' => 'cron_last_run_mail',    'interval' => 60*3],    
        'fio'     => ['key' => 'cron_last_run_fio',     'interval' => 60*5],   // 1 hour má být 3600        
        'unpaid'  => ['key' => 'cron_last_run_unpaid',  'interval' => 3600]    // 1 hour má být 3600        
    ];

    private PDO  $db;
    private Twig $twig;

    public function __construct(PDO $db, Twig $twig)
    {
        $this->db   = $db;
        $this->twig = $twig;
    }

    /**
     * Check each job's last-run timestamp and execute it if its interval has elapsed.
     */
    public function run(): void
    {
        $now       = time();
        $lastRuns  = $this->loadLastRuns();

        if ($this->isDue('mail', $lastRuns, $now)) {
            $this->runMail();
            $this->saveLastRun('mail', $now);
        }

        if ($this->isDue('fio', $lastRuns, $now)) {
            $this->runFio();
            $this->saveLastRun('fio', $now);
        }
        
        if ($this->isDue('unpaid', $lastRuns, $now)) {
            //$this->runUnpaid();
            $this->saveLastRun('unpaid', $now);
        }        
    }

    // -------------------------------------------------------------------------
    // Job runners
    // -------------------------------------------------------------------------

    private function runMail(): void
    {
        try {
            (new MailService($this->db, $this->twig))->processQueue();
        } catch (\Throwable $e) {
            error_log('CronService [mail]: ' . $e->getMessage());
        }
    }

    private function runFio(): void
    {
        try {
            (new FioService($this->db))->fetchAndStore();
        } catch (\Throwable $e) {
            error_log('CronService [fio]: ' . $e->getMessage());
        }
    }
    
    private function runUnpaid(): void
    {
        try {
            (new UnpaidService($this->db))->cancelOverdue();
        } catch (\Throwable $e) {
            error_log('CronService [unpaid]: ' . $e->getMessage());
        }
    }

    
    // -------------------------------------------------------------------------
    // Settings helpers
    // -------------------------------------------------------------------------

    /**
     * Load all cron last-run timestamps from the settings table.
     *
     * @return array<string, int>  job name => unix timestamp (0 if never run)
     */
    private function loadLastRuns(): array
    {
        $keys = array_column(self::JOBS, 'key');
        $placeholders = implode(',', array_fill(0, count($keys), '?'));

        $stmt = $this->db->prepare("SELECT `key`, `value` FROM settings WHERE `key` IN ($placeholders)");
        $stmt->execute($keys);
        $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);   // ['key' => 'value', ...]

        $result = [];
        foreach (self::JOBS as $job => $cfg) {
            $result[$job] = isset($rows[$cfg['key']]) ? (int)$rows[$cfg['key']] : 0;
        }
        return $result;
    }

    private function saveLastRun(string $job, int $timestamp): void
    {
        $key = self::JOBS[$job]['key'];
        $this->db->prepare("
            INSERT INTO settings (`key`, `value`) VALUES (?, ?)
            ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)
        ")->execute([$key, (string)$timestamp]);
    }

    private function isDue(string $job, array $lastRuns, int $now): bool
    {
        $interval = self::JOBS[$job]['interval'];

        if ($interval === 'daily') {
            return date('Y-m-d', $lastRuns[$job]) !== date('Y-m-d', $now);
        }

        return ($now - $lastRuns[$job]) >= $interval;
    }
}
