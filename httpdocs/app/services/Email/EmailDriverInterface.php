<?php
namespace App\Services\Email;

/**
 * Email Driver Interface
 * Defines the contract for email sending implementations
 */
interface EmailDriverInterface {
    /**
     * Send an email
     * @param string $to Recipient email address
     * @param string $subject Email subject
     * @param string $body Email body (HTML)
     * @param string|null $fromEmail Sender email address
     * @param string|null $fromName Sender name
     * @return bool True on success, false on failure
     */
    public function send(string $to, string $subject, string $body, ?string $fromEmail = null, ?string $fromName = null): bool;
    
    /**
     * Check if the driver is properly configured
     * @return bool True if configured, false otherwise
     */
    public function isConfigured(): bool;
}

