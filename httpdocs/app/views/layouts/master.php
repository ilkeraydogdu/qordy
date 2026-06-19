<?php
// Global layout for business admin
$css = <<<CSS
/* CSS Variables */
:root {
 --primary: #3b82f6;
 --primary-hover: #2563eb;
 --secondary: #64748b;
 --success: #10b981;
 --warning: #f59e0b;
 --error: #ef4444;
 --info: #06b6d4;
 --background: #f8fafc;
 --card-bg: #ffffff;
 --text-primary: #0f172a;
 --text-secondary: #475569;
 --border-color: #e2e8f0;
 --border-radius: 12px;
 --shadow-sm: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
 --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
 --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
 --transition: all 0.2s ease-in-out;
 --font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
}

/* Base Styles */
body {
 font-family: var(--font-family);
 background-color: var(--background);
 color: var(--text-primary);
 line-height: 1.5;
}

/* Component Classes */
.card {
 background: var(--card-bg);
 border: 1px solid var(--border-color);
 border-radius: var(--border-radius);
 box-shadow: var(--shadow-sm);
 transition: var(--transition);
}

.card:hover {
 box-shadow: var(--shadow-md);
}

.card-header {
 padding: 1rem 1.5rem;
 border-bottom: 1px solid var(--border-color);
 font-weight: 600;
 font-size: 1.125rem;
 color: var(--text-primary);
}

.card-body {
 padding: 1.5rem;
}

.card-footer {
 padding: 1rem 1.5rem;
 border-top: 1px solid var(--border-color);
 background: #f9fafb;
}

.btn {
 display: inline-flex;
 align-items: center;
 justify-content: center;
 padding: 0.5rem 1rem;
 border-radius: 8px;
 font-weight: 500;
 font-size: 0.875rem;
 transition: var(--transition);
 border: 1px solid transparent;
 cursor: pointer;
 text-decoration: none;
 gap: 0.5rem;
}

.btn:disabled {
 opacity: 0.5;
 cursor: not-allowed;
}

.btn-primary {
 background-color: var(--primary);
 color: white;
}

.btn-primary:hover:not(:disabled) {
 background-color: var(--primary-hover);
}

.btn-secondary {
 background-color: #e2e8f0;
 color: var(--text-secondary);
}

.btn-secondary:hover:not(:disabled) {
 background-color: #cbd5e1;
}

.btn-success {
 background-color: var(--success);
 color: white;
}

.btn-warning {
 background-color: var(--warning);
 color: white;
}

.btn-danger {
 background-color: var(--error);
 color: white;
}

/* Icon Components */
.icon {
 width: 24px;
 height: 24px;
 display: inline-flex;
 align-items: center;
 justify-content: center;
}

.icon-sm {
 width: 16px;
 height: 16px;
}

.icon-lg {
 width: 32px;
 height: 32px;
}

/* Stats Card */
.stat-card {
 background: var(--card-bg);
 border: 1px solid var(--border-color);
 border-radius: var(--border-radius);
 padding: 1.5rem;
 transition: var(--transition);
}

.stat-card:hover {
 box-shadow: var(--shadow-md);
}

.stat-card__icon {
 width: 48px;
 height: 48px;
 border-radius: 12px;
 display: flex;
 align-items: center;
 justify-content: center;
 margin-bottom: 1rem;
}

.stat-card__title {
 font-size: 0.875rem;
 color: var(--text-secondary);
 font-weight: 600;
 margin-bottom: 0.5rem;
}

.stat-card__value {
 font-size: 2rem;
 font-weight: 700;
 color: var(--text-primary);
}

/* Badge */
.badge {
 display: inline-flex;
 align-items: center;
 justify-content: center;
 padding: 0.25rem 0.75rem;
 border-radius: 9999px;
 font-size: 0.75rem;
 font-weight: 700;
 text-transform: uppercase;
 letter-spacing: 0.05em;
}

.badge--success {
 background-color: #d1fae5;
 color: #065f46;
}

.badge--warning {
 background-color: #fef3c7;
 color: #92400e;
}

.badge--error {
 background-color: #fee2e2;
 color: #991b1b;
}

.badge--info {
 background-color: #cffafe;
 color: #0e7490;
}

.badge--primary {
 background-color: #dbeafe;
 color: #1e40af;
}

/* Table Styles */
.table {
 width: 100%;
 border-collapse: collapse;
}

.table th {
 text-align: left;
 padding: 0.75rem;
 font-weight: 600;
 color: var(--text-secondary);
 border-bottom: 1px solid var(--border-color);
}

.table td {
 padding: 0.75rem;
 border-bottom: 1px solid var(--border-color);
}

.table tr:hover {
 background-color: #f9fafb;
}

/* Grid Layout */
.grid {
 display: grid;
}

.grid-cols-1 { grid-template-columns: repeat(1, minmax(0, 1fr)); }
.grid-cols-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
.grid-cols-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
.grid-cols-4 { grid-template-columns: repeat(4, minmax(0, 1fr)); }

.gap-2 { gap: 0.5rem; }
.gap-4 { gap: 1rem; }
.gap-6 { gap: 1.5rem; }

/* Responsive */
@media (max-width: 640px) {
 .hide-sm { display: none; }
 .text-sm { font-size: 0.875rem; }
 .p-sm { padding: 0.75rem; }
 .p-4-sm { padding: 0.75rem; }
}
CSS;

// Global JS
$js = <<<JS
// Global utility functions
function formatCurrency(amount) {
 return '₺' + parseFloat(amount).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

function formatDate(dateString) {
 return new Date(dateString).toLocaleDateString('tr-TR', {
 year: 'numeric',
 month: '2-digit',
 day: '2-digit',
 hour: '2-digit',
 minute: '2-digit'
 });
}

// Load component
function loadComponent(componentName, containerId, params = {}) {
 fetch('/api/components/load/' + componentName, {
 method: 'POST',
 headers: {
 'Content-Type': 'application/json',
 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content
 },
 body: JSON.stringify(params)
 })
 .then(response => response.json())
 .then(data => {
 document.getElementById(containerId).innerHTML = data.html;
 })
 .catch(error => console.error('Component load failed:', error));
}
JS;
?>
<!DOCTYPE html>
<html lang="tr">
<head>
 <meta charset="UTF-8">
 <meta name="viewport" content="width=device-width, initial-scale=1.0">
 <title>Qordy İşletme Yönetimi</title>
 <style><?php echo $css; ?></style>
 <script src="/assets/js/csrf.js"></script>
</head>
<body>
 <?php require_once __DIR__ . '/components/Header.php'; ?>
 <main class="container mx-auto px-4 py-6">
 <?php echo $content; ?>
 </main>
 <script><?php echo $js; ?></script>
 <?php require_once __DIR__ . '/components/Footer.php'; ?>
</body>
</html>