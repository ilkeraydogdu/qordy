<?php
namespace App\Exceptions;

use App\Core\Logger;
use Throwable;

/**
 * Global exception handler
 * Renders consistent JSON error responses for API/ajax, redirects for views
 */
class ExceptionHandler {
 public static function handle(Throwable $e, bool $isApiRequest = false, bool $debugMode = false): void {
 $context = [
 'file' => $e->getFile(),
 'line' => $e->getLine(),
 'trace' => $debugMode ? $e->getTraceAsString() : null,
 ];

 if ($e instanceof AppException) {
 $context = array_merge($context, $e->getContext());
 $httpStatus = $e->getHttpStatus();
 $errorCode = $e->getErrorCode();
 $userMessage = $e->getMessage();
 } else {
 $httpStatus = 500;
 $errorCode = 'INTERNAL_ERROR';
 $userMessage = $debugMode ? $e->getMessage() : 'Beklenmeyen bir hata oluştu';
 }

 Logger::error('Uncaught exception: ' . $e->getMessage(), $context);

 if (!headers_sent()) {
 http_response_code($httpStatus);
 header('Content-Type: application/json; charset=utf-8');
 }

 if ($isApiRequest) {
 $response = [
 'success' => false,
 'error_code' => $errorCode,
 'message' => $userMessage,
 ];
 if ($e instanceof ValidationException) {
 $response['errors'] = $e->getErrors();
 }
 if ($debugMode) {
 $response['debug'] = [
 'file' => $e->getFile(),
 'line' => $e->getLine(),
 'trace' => $e->getTraceAsString(),
 ];
 }
 echo json_encode($response, JSON_UNESCAPED_UNICODE);
 } else {
 echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Hata ' . $httpStatus . '</title></head>';
 echo '<body style="font-family:sans-serif;padding:40px;text-align:center;">';
 echo '<h1>Hata ' . $httpStatus . '</h1>';
 echo '<p>' . htmlspecialchars($userMessage, ENT_QUOTES, 'UTF-8') . '</p>';
 echo '<p><a href="/">Ana sayfaya dön</a></p>';
 echo '</body></html>';
 }
 exit;
 }
}
