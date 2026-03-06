<?php
namespace App\Models;

use PDO;

class TransactionList
{
    /**
     * Insert a transaction. Skips duplicates (by fio_id).
     *
     * @param PDO   $db
     * @param array $data Associative array of transaction fields
     * @return bool True if inserted, false if duplicate
     */
    public static function insertIgnore(PDO $db, array $data): bool
    {
        $stmt = $db->prepare("
            INSERT IGNORE INTO `transaction_lists`
                (fio_id, date, amount, currency, counter_account, counter_account_name,
                 bank_code, bank_name, constant_symbol, variable_symbol, specific_symbol,
                 user_identification, message, type, executor, account_name, comment,
                 bic, instruction_id)
            VALUES
                (:fio_id, :date, :amount, :currency, :counter_account, :counter_account_name,
                 :bank_code, :bank_name, :constant_symbol, :variable_symbol, :specific_symbol,
                 :user_identification, :message, :type, :executor, :account_name, :comment,
                 :bic, :instruction_id)
        ");

        $stmt->execute([
            ':fio_id'               => (string) ($data['fio_id'] ?? ''),
            ':date'                 => (string) ($data['date'] ?? ''),
            ':amount'               => (string) ($data['amount'] ?? ''),
            ':currency'             => (string) ($data['currency'] ?? ''),
            ':counter_account'      => (string) ($data['counter_account'] ?? ''),
            ':counter_account_name' => (string) ($data['counter_account_name'] ?? ''),
            ':bank_code'            => (string) ($data['bank_code'] ?? ''),
            ':bank_name'            => (string) ($data['bank_name'] ?? ''),
            ':constant_symbol'      => (string) ($data['constant_symbol'] ?? ''),
            ':variable_symbol'      => (string) ($data['variable_symbol'] ?? ''),
            ':specific_symbol'      => (string) ($data['specific_symbol'] ?? ''),
            ':user_identification'  => (string) ($data['user_identification'] ?? ''),
            ':message'              => (string) ($data['message'] ?? ''),
            ':type'                 => (string) ($data['type'] ?? ''),
            ':executor'             => (string) ($data['executor'] ?? ''),
            ':account_name'         => (string) ($data['account_name'] ?? ''),
            ':comment'              => (string) ($data['comment'] ?? ''),
            ':bic'                  => (string) ($data['bic'] ?? ''),
            ':instruction_id'       => (string) ($data['instruction_id'] ?? ''),
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Find a transaction by its primary key.
     *
     * @param PDO $db
     * @param int $id
     * @return array|false
     */
    public static function findById(PDO $db, int $id)
    {
        $stmt = $db->prepare("SELECT * FROM `transaction_lists` WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    /**
     * Set completed = NOW() for a transaction.
     *
     * @param PDO $db
     * @param int $id
     * @return void
     */
    public static function markCompleted(PDO $db, int $id): void
    {
        $stmt = $db->prepare("UPDATE `transaction_lists` SET completed = NOW() WHERE id = ?");
        $stmt->execute([$id]);
    }

    /**
     * Find a transaction by its Fio ID.
     *
     * @param PDO    $db
     * @param string $fioId
     * @return array|false
     */
    public static function findByFioId(PDO $db, string $fioId)
    {
        $stmt = $db->prepare("SELECT * FROM `transaction_lists` WHERE fio_id = ? LIMIT 1");
        $stmt->execute([$fioId]);
        return $stmt->fetch();
    }

    /**
     * Find transactions by variable symbol.
     *
     * @param PDO    $db
     * @param string $variableSymbol
     * @return array
     */
    public static function findByVariableSymbol(PDO $db, string $variableSymbol): array
    {
        $stmt = $db->prepare("SELECT * FROM `transaction_lists` WHERE variable_symbol = ? ORDER BY date DESC");
        $stmt->execute([$variableSymbol]);
        return $stmt->fetchAll();
    }

    /**
     * Return pending (unprocessed) transactions – completed IS NULL – ordered by date descending.
     *
     * @param PDO $db
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public static function getPending(PDO $db, int $limit = 100, int $offset = 0): array
    {
        $stmt = $db->prepare("
            SELECT * FROM `transaction_lists`
            WHERE completed IS NULL
            ORDER BY date DESC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Return all transactions ordered by date descending.
     *
     * @param PDO $db
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public static function getAll(PDO $db, int $limit = 100, int $offset = 0): array
    {
        $stmt = $db->prepare("SELECT * FROM `transaction_lists` ORDER BY date DESC LIMIT :limit OFFSET :offset");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
