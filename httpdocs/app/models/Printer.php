<?php
namespace App\Models;

require_once __DIR__ . '/../core/Model.php';

class Printer extends \App\Core\Model {
    protected $table = 'printers';
    
    public function getAll() {
        return $this->query()
            ->orderBy('printer_name')
            ->get();
    }
    
    public function getById($printerId) {
        return $this->query()
            ->where('printer_id', $printerId)
            ->first();
    }
    
    public function getByLocation($location) {
        return $this->query()
            ->where('printer_location', $location)
            ->where('is_active', 1)
            ->get();
    }
    
    public function getBySerial($serial) {
        return $this->query()
            ->where('printer_serial', $serial)
            ->first();
    }
    
    public function getActive() {
        return $this->query()
            ->where('is_active', 1)
            ->where('status', 'ACTIVE')
            ->orderBy('printer_name')
            ->get();
    }
    
    public function create($data) {
        if (!isset($data['printer_id'])) {
            $data['printer_id'] = generateId('pr');
        }
        return $this->query()->insert($data);
    }
    
    public function updatePrinter($printerId, $data) {
        return $this->query()
            ->where('printer_id', $printerId)
            ->update($data);
    }
    
    public function deletePrinter($printerId) {
        return $this->query()
            ->where('printer_id', $printerId)
            ->delete();
    }
    
    public function updateStatus($printerId, $status) {
        return $this->query()
            ->where('printer_id', $printerId)
            ->update(['status' => $status]);
    }
}

