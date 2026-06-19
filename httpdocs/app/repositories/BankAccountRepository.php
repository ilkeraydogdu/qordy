<?php
namespace App\Repositories;

use App\Core\BaseRepository;

class BankAccountRepository extends BaseRepository {
    protected $table = 'bank_accounts';
    protected $primaryKey = 'account_id';

    public function getActiveAccounts(): array {
        try {
            $sql = "SELECT * FROM {$this->table} WHERE is_active = 1 ORDER BY sort_order ASC, bank_name ASC";
            return $this->fetchAll($sql) ?: [];
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getAll(): array {
        try {
            $sql = "SELECT * FROM {$this->table} ORDER BY sort_order ASC, bank_name ASC";
            return $this->fetchAll($sql) ?: [];
        } catch (\Exception $e) {
            return [];
        }
    }
}
