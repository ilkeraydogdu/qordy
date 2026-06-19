<?php
namespace App\Models;

require_once __DIR__ . '/../core/Model.php';

class Invoice extends \App\Core\Model {
    protected $table = 'invoices';
    
    public function getAll() {
        return $this->query()
            ->select(['i.*', 's.name as supplier_name'])
            ->from('invoices i')
            ->leftJoin('suppliers s', 'i.supplier_id', '=', 's.supplier_id')
            ->orderBy('i.date', 'DESC')
            ->get();
    }
    
    public function getById($invoiceId) {
        return $this->query()
            ->select(['i.*', 's.name as supplier_name'])
            ->from('invoices i')
            ->leftJoin('suppliers s', 'i.supplier_id', '=', 's.supplier_id')
            ->where('i.invoice_id', $invoiceId)
            ->first();
    }
    
    public function getBySupplier($supplierId) {
        return $this->query()
            ->where('supplier_id', $supplierId)
            ->orderBy('date', 'DESC')
            ->get();
    }
    
    public function getByDateRange($startDate, $endDate) {
        return $this->query()
            ->select(['i.*', 's.name as supplier_name'])
            ->from('invoices i')
            ->leftJoin('suppliers s', 'i.supplier_id', '=', 's.supplier_id')
            ->whereBetween('i.date', [$startDate, $endDate])
            ->orderBy('i.date', 'DESC')
            ->get();
    }
    
    public function create($data) {
        return $this->query()
            ->insert($data);
    }

    public function updateInvoice($invoiceId, $data) {
        return $this->query()
            ->where('invoice_id', $invoiceId)
            ->update($data);
    }

    public function deleteInvoice($invoiceId) {
        return $this->query()
            ->where('invoice_id', $invoiceId)
            ->delete();
    }
    
    public function markAsPaid($invoiceId) {
        return $this->query()
            ->where('invoice_id', $invoiceId)
            ->update(['is_paid' => 1]);
    }
    
    public function getUnpaid() {
        return $this->query()
            ->select(['i.*', 's.name as supplier_name'])
            ->from('invoices i')
            ->leftJoin('suppliers s', 'i.supplier_id', '=', 's.supplier_id')
            ->where('i.is_paid', 0)
            ->orderBy('i.date')
            ->get();
    }
    
    public function getOverdue() {
        return $this->query()
            ->select(['i.*', 's.name as supplier_name'])
            ->from('invoices i')
            ->leftJoin('suppliers s', 'i.supplier_id', '=', 's.supplier_id')
            ->where('i.is_paid', 0)
            ->whereRaw('i.due_date < CURDATE()')
            ->orderBy('i.due_date')
            ->get();
    }
    
    public function getTotalAmountByDateRange($startDate, $endDate) {
        $result = $this->query()
            ->whereBetween('date', [$startDate, $endDate])
            ->sum('amount');
        return (float)($result ?: 0);
    }
    
    public function getPaidTotalByDateRange($startDate, $endDate) {
        $result = $this->query()
            ->whereBetween('date', [$startDate, $endDate])
            ->where('is_paid', 1)
            ->sum('amount');
        return (float)($result ?: 0);
    }
}