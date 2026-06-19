<?php
/**
 * Toast Notification Helper Functions
 * Centralized toast notification system using ToastNotificationService
 * MVC, OOP, Dynamic - No hardcoded messages
 */

if (!function_exists('toastSuccess')) {
    /**
     * Show success toast notification
     * @param string $key - Translation key (e.g., 'notifications.success.order_updated')
     * @param array $params - Parameters for string replacement
     * @return string - JavaScript code to show toast
     */
    function toastSuccess($key, $params = []) {
        $toastService = getToastNotificationService();
        $message = $toastService->showSuccess($key, $params);
        
        return "
        <script>
        if (window.NotificationManager) {
            window.NotificationManager.success(" . json_encode($message, JSON_UNESCAPED_UNICODE) . ");
        } else if (window.Toast) {
            window.Toast.success(" . json_encode($message, JSON_UNESCAPED_UNICODE) . ");
        } else if (window.showToast) {
            window.showToast(" . json_encode($message, JSON_UNESCAPED_UNICODE) . ", 'success');
        }
        </script>";
    }
}

if (!function_exists('toastError')) {
    /**
     * Show error toast notification
     * @param string $key - Translation key (e.g., 'notifications.error.invalid_data')
     * @param array $params - Parameters for string replacement
     * @return string - JavaScript code to show toast
     */
    function toastError($key, $params = []) {
        $toastService = getToastNotificationService();
        $message = $toastService->showError($key, $params);
        
        return "
        <script>
        if (window.NotificationManager) {
            window.NotificationManager.error(" . json_encode($message, JSON_UNESCAPED_UNICODE) . ");
        } else if (window.Toast) {
            window.Toast.error(" . json_encode($message, JSON_UNESCAPED_UNICODE) . ");
        } else if (window.showToast) {
            window.showToast(" . json_encode($message, JSON_UNESCAPED_UNICODE) . ", 'error');
        }
        </script>";
    }
}

if (!function_exists('toastWarning')) {
    /**
     * Show warning toast notification
     * @param string $key - Translation key (e.g., 'notifications.warning.confirm_action')
     * @param array $params - Parameters for string replacement
     * @return string - JavaScript code to show toast
     */
    function toastWarning($key, $params = []) {
        $toastService = getToastNotificationService();
        $message = $toastService->showWarning($key, $params);
        
        return "
        <script>
        if (window.NotificationManager) {
            window.NotificationManager.warning(" . json_encode($message, JSON_UNESCAPED_UNICODE) . ");
        } else if (window.Toast) {
            window.Toast.warning(" . json_encode($message, JSON_UNESCAPED_UNICODE) . ");
        } else if (window.showToast) {
            window.showToast(" . json_encode($message, JSON_UNESCAPED_UNICODE) . ", 'warning');
        }
        </script>";
    }
}

if (!function_exists('toastInfo')) {
    /**
     * Show info toast notification
     * @param string $key - Translation key (e.g., 'notifications.info.loading')
     * @param array $params - Parameters for string replacement
     * @return string - JavaScript code to show toast
     */
    function toastInfo($key, $params = []) {
        $toastService = getToastNotificationService();
        $message = $toastService->showInfo($key, $params);
        
        return "
        <script>
        if (window.NotificationManager) {
            window.NotificationManager.info(" . json_encode($message, JSON_UNESCAPED_UNICODE) . ");
        } else if (window.Toast) {
            window.Toast.info(" . json_encode($message, JSON_UNESCAPED_UNICODE) . ");
        } else if (window.showToast) {
            window.showToast(" . json_encode($message, JSON_UNESCAPED_UNICODE) . ", 'info');
        }
        </script>";
    }
}

if (!function_exists('flashSuccess')) {
    /**
     * Set success flash message in session
     * @param string $key - Translation key
     * @param array $params - Parameters for string replacement
     * @return void
     */
    function flashSuccess($key, $params = []) {
        $toastService = getToastNotificationService();
        $toastService->setFlash('success', $key, $params);
    }
}

if (!function_exists('flashError')) {
    /**
     * Set error flash message in session
     * @param string $key - Translation key
     * @param array $params - Parameters for string replacement
     * @return void
     */
    function flashError($key, $params = []) {
        $toastService = getToastNotificationService();
        $toastService->setFlash('error', $key, $params);
    }
}

if (!function_exists('flashWarning')) {
    /**
     * Set warning flash message in session
     * @param string $key - Translation key
     * @param array $params - Parameters for string replacement
     * @return void
     */
    function flashWarning($key, $params = []) {
        $toastService = getToastNotificationService();
        $toastService->setFlash('warning', $key, $params);
    }
}

if (!function_exists('flashInfo')) {
    /**
     * Set info flash message in session
     * @param string $key - Translation key
     * @param array $params - Parameters for string replacement
     * @return void
     */
    function flashInfo($key, $params = []) {
        $toastService = getToastNotificationService();
        $toastService->setFlash('info', $key, $params);
    }
}

// Backward compatibility - showToast function
if (!function_exists('showToast')) {
    /**
     * Show toast notification (backward compatibility)
     * @param string $message - Direct message (for backward compatibility) or translation key
     * @param string $type - Toast type: 'success', 'error', 'info', 'warning'
     * @return string - JavaScript code to show toast
     */
    function showToast($message, $type = 'success') {
        // If message looks like a translation key (contains dots), use it as key
        // Otherwise, treat as direct message (backward compatibility)
        if (strpos($message, 'notifications.') === 0 || strpos($message, '.') !== false) {
            $toastService = getToastNotificationService();
            $translatedMessage = $toastService->translate($message);
            $message = $translatedMessage ?: $message;
        }
        
        $validTypes = ['success', 'error', 'info', 'warning'];
        $type = in_array($type, $validTypes) ? $type : 'success';
        
        return "
        <script>
        if (window.NotificationManager) {
            window.NotificationManager." . $type . "(" . json_encode($message, JSON_UNESCAPED_UNICODE) . ");
        } else if (window.Toast) {
            window.Toast." . $type . "(" . json_encode($message, JSON_UNESCAPED_UNICODE) . ");
        } else if (window.showToast) {
            window.showToast(" . json_encode($message, JSON_UNESCAPED_UNICODE) . ", " . json_encode($type, JSON_UNESCAPED_UNICODE) . ");
        }
        </script>";
    }
}

// Backward compatibility - getToastScript function
if (!function_exists('getToastScript')) {
    /**
     * Get toast script tag (backward compatibility)
     * Now returns notification.js script tag
     * @return string - Script tag for notification.js
     */
    function getToastScript() {
        // notification.js is already loaded in layouts, but for standalone pages:
        return '<script src="' . BASE_URL . '/assets/js/notification.js"></script>';
    }
}
