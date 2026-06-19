<?php
// QORDY Notification Component Helper

function toast_scripts() {
    // Toast notification scripts already included in main layout
}

function display_queued_toasts() {
    // Display queued toast notifications from session
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'] ?? 'info';
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
        
        echo "<script>";
        echo "document.addEventListener('DOMContentLoaded', function() {";
        echo "if (window.showToast) window.showToast('" . addslashes($message) . "', '" . $type . "');";
        echo "});";
        echo "</script>";
    }
}
