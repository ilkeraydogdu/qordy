<?php
namespace App\Models;

require_once __DIR__ . '/../core/Model.php';

class ReceiptPrintQueue extends \App\Core\Model {
    protected $table = 'receipt_print_queue';
    
    public function getByReceipt($receiptId) {
        return $this->query()
            ->where('receipt_id', $receiptId)
            ->orderBy('created_at', 'DESC')
            ->get();
    }
    
    public function getByPrinter($printerId) {
        return $this->query()
            ->where('printer_id', $printerId)
            ->where('status', 'PENDING')
            ->orderBy('created_at', 'ASC')
            ->get();
    }
    
    public function getPending() {
        return $this->query()
            ->where('status', 'PENDING')
            ->orderBy('created_at', 'ASC')
            ->get();
    }
    
    public function create($data) {
        if (!isset($data['queue_id'])) {
            $data['queue_id'] = generateId('q');
        }
        return $this->query()->insert($data);
    }
    
    public function updateStatus($queueId, $status, $errorMessage = null) {
        $updateData = [
            'status' => $status,
            'printed_at' => $status === 'PRINTED' ? date('Y-m-d H:i:s') : null
        ];
        
        // Clear processing fields when status changes from PRINTING
        if ($status !== 'PRINTING') {
            $updateData['processing_bridge_id'] = null;
            $updateData['processing_started_at'] = null;
        }
        
        if ($errorMessage) {
            $updateData['error_message'] = $errorMessage;
        }
        
        if ($status === 'FAILED') {
            // Increment retry count
            $queue = $this->query()
                ->where('queue_id', $queueId)
                ->first();
            if ($queue) {
                $updateData['retry_count'] = intval($queue['retry_count'] ?? 0) + 1;
            }
        }
        
        return $this->query()
            ->where('queue_id', $queueId)
            ->update($updateData);
    }
}

