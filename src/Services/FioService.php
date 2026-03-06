<?php
namespace App\Services;

use App\Models\TransactionList;
use PDO;

class FioService
{
    private const API_URL = 'https://fioapi.fio.cz/v1/rest/last/%s/transactions.xml';
    private const LOG_DIR = __DIR__ . '/../../var/log';

    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Fetch the latest transactions from Fio API, save the raw XML to a log file,
     * and persist each transaction to the database.
     *
     * @return array{file: string, inserted: int, skipped: int}
     * @throws \RuntimeException on HTTP or XML parse failure
     */
    public function fetchAndStore(): array
    {
        $token = $_ENV['BANK_TOKEN'] ?? '';
        if ($token === '') {
            throw new \RuntimeException('FioService: BANK_TOKEN is not set in environment.');
        }

        $xml = $this->downloadXml($token);
        $logFile = $this->saveToLog($xml);
        $stats = $this->parseAndStore($xml);

        return array_merge(['file' => $logFile], $stats);
    }

    // -------------------------------------------------------------------------

    private function downloadXml(string $token): string
    {
        $url = sprintf(self::API_URL, urlencode($token));

        $ctx = stream_context_create([
            'http' => [
                'method'  => 'GET',
                'timeout' => 30,
                'header'  => "Accept: application/xml\r\n",
            ],
        ]);

        $body = @file_get_contents($url, false, $ctx);

        if ($body === false) {
            throw new \RuntimeException('FioService: Failed to download XML from Fio API.');
        }

        return $body;
    }

    private function saveToLog(string $content): string
    {
        if (!is_dir(self::LOG_DIR)) {
            mkdir(self::LOG_DIR, 0755, true);
        }

        $filename = date('Y-m-d_H-i-s') . '_fio_transactions.xml';
        $path = self::LOG_DIR . '/' . $filename;

        if (file_put_contents($path, $content) === false) {
            throw new \RuntimeException('FioService: Failed to write log file: ' . $path);
        }

        return $path;
    }

    /**
     * Parse the XML and insert each transaction via TransactionList model.
     *
     * @return array{inserted: int, skipped: int}
     */
    private function parseAndStore(string $xml): array
    {
        libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml);

        if ($doc === false) {
            $errors = array_map(fn($e) => $e->message, libxml_get_errors());
            libxml_clear_errors();
            throw new \RuntimeException('FioService: XML parse error – ' . implode('; ', $errors));
        }

        $inserted = 0;
        $skipped  = 0;

        foreach ($doc->TransactionList->Transaction ?? [] as $tx) {
            $data = $this->mapTransaction($tx);

            if (empty($data['fio_id'])) {
                $skipped++;
                continue;
            }

            $wasInserted = TransactionList::insertIgnore($this->db, $data);
            $wasInserted ? $inserted++ : $skipped++;
        }

        return ['inserted' => $inserted, 'skipped' => $skipped];
    }

    /**
     * Map a SimpleXMLElement transaction node to a flat array.
     * All values are cast to string to avoid DB type errors.
     */
    private function mapTransaction(\SimpleXMLElement $tx): array
    {
        $col = static function (\SimpleXMLElement $tx, string $id): string {
            foreach ($tx->children() as $node) {
                if ((string) $node->attributes()['id'] === $id) {
                    return (string) $node;
                }
            }
            return '';
        };

        return [
            'fio_id'               => $col($tx, '22'),
            'date'                 => $col($tx, '0'),
            'amount'               => $col($tx, '1'),
            'currency'             => $col($tx, '14'),
            'counter_account'      => $col($tx, '2'),
            'counter_account_name' => $col($tx, '10'),
            'bank_code'            => $col($tx, '3'),
            'bank_name'            => $col($tx, '12'),
            'constant_symbol'      => $col($tx, '4'),
            'variable_symbol'      => $col($tx, '5'),
            'specific_symbol'      => $col($tx, '6'),
            'user_identification'  => $col($tx, '7'),
            'message'              => $col($tx, '16'),
            'type'                 => $col($tx, '8'),
            'executor'             => $col($tx, '9'),
            'account_name'         => $col($tx, '18'),
            'comment'              => $col($tx, '25'),
            'bic'                  => $col($tx, '26'),
            'instruction_id'       => $col($tx, '17'),
        ];
    }
}
