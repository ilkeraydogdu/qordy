/**
 * Security Module
 * Functions for XSS prevention and input sanitization
 */

/**
 * Escape HTML to prevent XSS
 * @param {string} text - Text to escape
 * @returns {string} Escaped text
 */
export function escapeHtml(text) {
    if (text === null || text === undefined) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Sanitize input string
 * @param {string} input - Input to sanitize
 * @returns {string} Sanitized string
 */
export function sanitizeInput(input) {
    if (typeof input !== 'string') return '';
    return input.trim().replace(/[<>]/g, '');
}

/**
 * Validate email format
 * @param {string} email - Email to validate
 * @returns {boolean} True if valid email
 */
export function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

/**
 * Validate phone number format (Turkish format)
 * @param {string} phone - Phone number to validate
 * @returns {boolean} True if valid phone
 */
export function validatePhone(phone) {
    const re = /^(\+90|0)?[5][0-9]{9}$/;
    return re.test(phone.replace(/\s/g, ''));
}

/**
 * Generate a secure random ID
 * Uses crypto API if available, falls back to timestamp + random
 * @param {number} length - Length of ID (default: 21)
 * @returns {string} Generated ID
 */
export function generateId(length = 21) {
    // Use crypto API if available (more secure)
    if (typeof crypto !== 'undefined' && crypto.getRandomValues) {
        const array = new Uint8Array(length);
        crypto.getRandomValues(array);
        return Array.from(array, byte => byte.toString(36)).join('').substring(0, length);
    }
    
    // Fallback: timestamp + random (less secure but better than pure random)
    const timestamp = Date.now().toString(36);
    const random = Math.random().toString(36).substring(2);
    return (timestamp + random).substring(0, length);
}

