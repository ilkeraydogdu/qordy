<?php
namespace App\Controllers\Admin;

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../core/DependencyFactory.php';
require_once __DIR__ . '/../../helpers/functions.php';

use App\Core\Controller;

class MedicalReportController extends Controller {
    protected $medicalReportService;
    
    public function __construct() {
        parent::__construct();
        $this->medicalReportService = \App\Core\DependencyFactory::getMedicalReportService();
    }
    
    public function getMedicalReport($reportId = null) {
        $this->requirePermission('staff.view');
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $reportId = $reportId ?? $queryParams['id'] ?? '';
        
        if (empty($reportId)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            return;
        }
        
        $report = $this->medicalReportService->findById($reportId);
        
        if ($report) {
            $this->apiResponse($report);
        } else {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.not_found', [], 404);
        }
    }
    
    public function addMedicalReport() {
        $this->requirePermission('staff.edit');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 400);
            return;
        }
        
        $requestData = \App\Core\RequestParser::getRequestData();
        $userId = $requestData['user_id'] ?? '';
        $startDate = $requestData['start_date'] ?? '';
        $endDate = $requestData['end_date'] ?? '';
        $reportNumber = $requestData['report_number'] ?? '';
        $hospitalName = $requestData['hospital_name'] ?? '';
        $doctorName = $requestData['doctor_name'] ?? '';
        $notes = $requestData['notes'] ?? '';
        
        if (empty($userId) || empty($startDate) || empty($endDate)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.missing_fields', [], 400);
            return;
        }
        
        $start = \DateTime::createFromFormat('Y-m-d', $startDate);
        $end = \DateTime::createFromFormat('Y-m-d', $endDate);
        
        if (!$start || !$end) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            return;
        }
        
        if ($end < $start) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.invalid_date_range', [], 400);
            return;
        }
        
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.missing_fields', [], 400);
            return;
        }
        
        $file = $_FILES['file'];
        
        $allowedTypes = ['application/pdf'];
        $fileType = mime_content_type($file['tmp_name']);
        
        if (!in_array($fileType, $allowedTypes) && pathinfo($file['name'], PATHINFO_EXTENSION) !== 'pdf') {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_file_type', [], 400);
            return;
        }
        
        $maxSize = 10 * 1024 * 1024;
        if ($file['size'] > $maxSize) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.file_too_large', [], 400);
            return;
        }
        
        $uploadDir = __DIR__ . '/../../public/uploads/medical_reports/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $timestamp = time();
        $originalName = basename($file['name']);
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $filename = $userId . '_' . $timestamp . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
        $filepath = $uploadDir . $filename;
        
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.file_upload_failed', [], 500);
            return;
        }
        
        $totalDays = $this->medicalReportService->calculateDays($startDate, $endDate);
        
        $reportData = [
            'report_id' => generateId('mr'),
            'user_id' => $userId,
            'report_number' => sanitizeInput($reportNumber),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total_days' => $totalDays,
            'hospital_name' => sanitizeInput($hospitalName),
            'doctor_name' => sanitizeInput($doctorName),
            'file_path' => '/public/uploads/medical_reports/' . $filename,
            'file_name' => $originalName,
            'file_size' => $file['size'],
            'notes' => sanitizeInput($notes)
        ];
        
        $result = $this->medicalReportService->create($reportData);
        
        if ($result) {
            $this->toastNotificationService->sendApiResponse('success', 'notifications.success.medical_report_added', ['report_id' => $reportData['report_id']], 200);
        } else {
            @unlink($filepath);
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.create_failed', [], 500);
        }
    }
    
    public function updateMedicalReport($reportId = null) {
        $this->requirePermission('staff.edit');
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $requestData = \App\Core\RequestParser::getRequestData();
        $reportId = $reportId ?? $queryParams['id'] ?? $requestData['id'] ?? '';
        
        if (empty($reportId)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 400);
            return;
        }
        
        $updateData = [];
        
        $requestData = \App\Core\RequestParser::getRequestData();
        if (isset($requestData['start_date']) && isset($requestData['end_date'])) {
            $startDate = $requestData['start_date'];
            $endDate = $requestData['end_date'];
            
            $start = \DateTime::createFromFormat('Y-m-d', $startDate);
            $end = \DateTime::createFromFormat('Y-m-d', $endDate);
            
            if (!$start || !$end) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
                return;
            }
            
            if ($end < $start) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.invalid_date_range', [], 400);
                return;
            }
            
            $updateData['start_date'] = $startDate;
            $updateData['end_date'] = $endDate;
            $updateData['total_days'] = $this->medicalReportService->calculateDays($startDate, $endDate);
        }
        
        if (isset($requestData['report_number'])) {
            $updateData['report_number'] = sanitizeInput($requestData['report_number']);
        }
        
        if (isset($requestData['hospital_name'])) {
            $updateData['hospital_name'] = sanitizeInput($requestData['hospital_name']);
        }
        
        if (isset($requestData['doctor_name'])) {
            $updateData['doctor_name'] = sanitizeInput($requestData['doctor_name']);
        }
        
        if (isset($requestData['notes'])) {
            $updateData['notes'] = sanitizeInput($requestData['notes']);
        }
        
        if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['file'];
            
            $allowedTypes = ['application/pdf'];
            $fileType = mime_content_type($file['tmp_name']);
            
            if (!in_array($fileType, $allowedTypes) && pathinfo($file['name'], PATHINFO_EXTENSION) !== 'pdf') {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_file_type', [], 400);
                return;
            }
            
            $maxSize = 10 * 1024 * 1024;
            if ($file['size'] > $maxSize) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.file_too_large', [], 400);
                return;
            }
            
            $existingReport = $this->medicalReportService->findById($reportId);
            
            $uploadDir = __DIR__ . '/../../public/uploads/medical_reports/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $userId = $existingReport['user_id'] ?? $requestData['user_id'] ?? '';
            $timestamp = time();
            $originalName = basename($file['name']);
            $filename = $userId . '_' . $timestamp . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
            $filepath = $uploadDir . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                if ($existingReport && isset($existingReport['file_path'])) {
                    $oldFilePath = __DIR__ . '/../..' . $existingReport['file_path'];
                    @unlink($oldFilePath);
                }
                
                $updateData['file_path'] = '/public/uploads/medical_reports/' . $filename;
                $updateData['file_name'] = $originalName;
                $updateData['file_size'] = $file['size'];
            } else {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.file_upload_failed', [], 500);
                return;
            }
        }
        
        if (empty($updateData)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            return;
        }
        
        $result = $this->medicalReportService->update($reportId, $updateData);
        
        if ($result) {
            $this->toastNotificationService->sendApiResponse('success', 'notifications.success.medical_report_updated', [], 200);
        } else {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.update_failed', [], 500);
        }
    }
    
    public function deleteMedicalReport($reportId = null) {
        $this->requirePermission('staff.edit');
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $requestData = \App\Core\RequestParser::getRequestData();
        $reportId = $reportId ?? $queryParams['id'] ?? $requestData['id'] ?? '';
        
        if (empty($reportId)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            return;
        }
        
        $report = $this->medicalReportService->findById($reportId);
        
        $result = $this->medicalReportService->delete($reportId);
        
        if ($result) {
            if ($report && isset($report['file_path'])) {
                $filePath = __DIR__ . '/../..' . $report['file_path'];
                @unlink($filePath);
            }
            
            $this->toastNotificationService->sendApiResponse('success', 'notifications.success.medical_report_deleted', [], 200);
        } else {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.delete_failed', [], 500);
        }
    }
    
    public function downloadMedicalReport($reportId = null) {
        $this->requirePermission('staff.view');
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $reportId = $reportId ?? $queryParams['id'] ?? '';
        
        if (empty($reportId)) {
            header('Location: ' . BASE_URL . '/admin/users');
            exit;
        }
        
        $report = $this->medicalReportService->findById($reportId);
        
        if (!$report || !isset($report['file_path'])) {
            $this->toastNotificationService->setFlash('error', 'notifications.error.not_found');
            header('Location: ' . BASE_URL . '/admin/users');
            exit;
        }
        
        $filePath = __DIR__ . '/../..' . $report['file_path'];
        
        if (!file_exists($filePath)) {
            $this->toastNotificationService->setFlash('error', 'notifications.error.not_found');
            header('Location: ' . BASE_URL . '/admin/users');
            exit;
        }
        
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $report['file_name'] . '"');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit;
    }
}

