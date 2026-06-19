<?php
require_once __DIR__ . '/../../../helpers/translations.php';
require_once __DIR__ . '/../../../helpers/url_helper.php';

$screen = $screen ?? null;
$categories = $categories ?? [];
$screenCategoryIds = $screenCategoryIds ?? [];
$baseUrl = BASE_URL;
$isEdit = true;

if (!$screen) {
    header('Location: ' . getAdminUrl('preparation-screens'));
    exit;
}
?>

<div class="q-page q-biz-theme q-prep-screens-page animate-slide-up">
  <div class="q-container q-stack q-stack--lg">
    <header class="q-page-header">
        <div>
            <p class="q-page-header__eyebrow">Mutfak</p>
            <h1 class="q-page-header__title"><?php echo t('preparation_screens.edit_title', 'Hazırlık Ekranı Düzenle'); ?></h1>
            <p class="q-page-header__subtitle"><?php echo htmlspecialchars((string) ($screen['name'] ?? ''), ENT_QUOTES); ?> · <?php echo htmlspecialchars((string) ($screen['slug'] ?? ''), ENT_QUOTES); ?></p>
        </div>
        <div class="q-page-header__actions">
            <a href="<?php echo getAdminUrl('preparation-screens/' . ($screen['slug'] ?? '')); ?>" target="_blank" rel="noopener" class="q-btn q-btn--ghost q-btn--sm">
                <?php echo t('common.open', 'Ekranı Aç'); ?>
            </a>
            <a href="<?php echo getAdminUrl('preparation-screens'); ?>" class="q-btn q-btn--ghost q-btn--sm">
                <?php echo t('common.back', 'Geri Dön'); ?>
            </a>
        </div>
    </header>

    <div class="q-prep-form-shell">
        <form id="screenForm">
            <?php echo csrf_field(); ?>
            <?php include __DIR__ . '/_form_fields.php'; ?>
        </form>
    </div>
  </div>
</div>

<script>
const baseUrl = <?php echo json_encode($baseUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
const screenId = <?php echo json_encode($screen['screen_id'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

function generateSlug(text) {
    const turkish = ['ş', 'Ş', 'ı', 'İ', 'ğ', 'Ğ', 'ü', 'Ü', 'ö', 'Ö', 'ç', 'Ç'];
    const english = ['s', 'S', 'i', 'I', 'g', 'G', 'u', 'U', 'o', 'O', 'c', 'C'];
    let slug = text;
    turkish.forEach((char, index) => {
        slug = slug.replace(new RegExp(char, 'g'), english[index]);
    });
    slug = slug.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
    return slug;
}

let allPrinters = [];
let assignedPrinters = [];

async function loadPrinters() {
    try {
        const response = await fetch(`${baseUrl}/api/qodmin/printer/all`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': window.CSRF_TOKEN || '',
                'Accept': 'application/json'
            },
            credentials: 'same-origin'
        });

        const data = await response.json();
        allPrinters = Array.isArray(data) ? data : (data.printers || []);

        const printerSelect = document.getElementById('printer-select');
        if (!printerSelect) return;
        printerSelect.innerHTML = '<option value=""><?php echo htmlspecialchars(t('preparation_screens.select_printer', 'Yazıcı Seçin...'), ENT_QUOTES, 'UTF-8'); ?></option>';
        allPrinters.forEach(printer => {
            const option = document.createElement('option');
            option.value = printer.printer_id;
            option.textContent = printer.printer_name + ' (' + printer.printer_location + ')';
            printerSelect.appendChild(option);
        });
    } catch (error) {
        console.error('Error loading printers:', error);
        allPrinters = [];
    }
}

async function loadAssignedPrinters() {
    try {
        const apiPrefix = <?php echo json_encode(($is_super_admin ?? false) ? '/api/qodmin' : '/api/business'); ?>;
        const response = await fetch(`${baseUrl}${apiPrefix}/preparation-screens/${screenId}/printers`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': window.CSRF_TOKEN || '',
                'Accept': 'application/json'
            },
            credentials: 'same-origin'
        });

        const data = await response.json();
        assignedPrinters = data.success && data.printers ? data.printers : [];
        renderAssignedPrinters();
    } catch (error) {
        console.error('Error loading assigned printers:', error);
        assignedPrinters = [];
        renderAssignedPrinters();
    }
}

function renderAssignedPrinters() {
    const container = document.getElementById('assigned-printers-list');
    if (!container) return;

    if (assignedPrinters.length === 0) {
        container.innerHTML = '<div class="q-hint text-sm text-center py-3"><?php echo htmlspecialchars(t('preparation_screens.no_printers_assigned', 'Henüz yazıcı atanmamış'), ENT_QUOTES, 'UTF-8'); ?></div>';
        return;
    }

    container.innerHTML = assignedPrinters.map(printer => `
        <div class="q-prep-printer-row">
            <div>
                <div class="q-prep-printer-row__name">${escapeHtml(printer.printer_name || 'Yazıcı')}</div>
                <div class="q-hint text-xs">${escapeHtml(printer.printer_location || '')}</div>
            </div>
            <button type="button" onclick="removePrinter('${escapeHtml(printer.printer_id)}')" class="q-btn q-btn--ghost q-btn--sm" style="color:var(--color-status-danger);">
                <?php echo htmlspecialchars(t('common.remove', 'Kaldır'), ENT_QUOTES, 'UTF-8'); ?>
            </button>
        </div>
    `).join('');
}

async function assignPrinter() {
    const printerSelect = document.getElementById('printer-select');
    const printerId = printerSelect?.value;
    if (!printerId) {
        window.NotificationManager.warning('<?php echo htmlspecialchars(t('preparation_screens.select_printer_first', 'Lütfen bir yazıcı seçin'), ENT_QUOTES, 'UTF-8'); ?>');
        return;
    }

    try {
        const apiPrefix = <?php echo json_encode(($is_super_admin ?? false) ? '/api/qodmin' : '/api/business'); ?>;
        const response = await fetch(`${baseUrl}${apiPrefix}/preparation-screens/${screenId}/assign-printer`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': window.CSRF_TOKEN || '',
                'Accept': 'application/json'
            },
            credentials: 'same-origin',
            body: JSON.stringify({ printer_id: printerId, priority: 1 })
        });

        const data = await response.json();
        if (data.success) {
            printerSelect.value = '';
            await loadAssignedPrinters();
        } else {
            window.NotificationManager.error('<?php echo htmlspecialchars(t('preparation_screens.assign_failed', 'Yazıcı atanamadı'), ENT_QUOTES, 'UTF-8'); ?>: ' + (data.error || 'Bilinmeyen hata'));
        }
    } catch (error) {
        console.error('Error assigning printer:', error);
        window.NotificationManager.error('<?php echo htmlspecialchars(t('common.error_occurred', 'Bir hata oluştu.'), ENT_QUOTES, 'UTF-8'); ?>');
    }
}

async function removePrinter(printerId) {
    let confirmed = false;
    if (window.NotificationManager && window.NotificationManager.confirm) {
        confirmed = await window.NotificationManager.confirm('<?php echo htmlspecialchars(t('preparation_screens.remove_printer_confirm', 'Bu yazıcıyı kaldırmak istediğinize emin misiniz?'), ENT_QUOTES, 'UTF-8'); ?>', 'Onay');
    } else {
        confirmed = confirm('<?php echo htmlspecialchars(t('preparation_screens.remove_printer_confirm', 'Bu yazıcıyı kaldırmak istediğinize emin misiniz?'), ENT_QUOTES, 'UTF-8'); ?>');
    }
    if (!confirmed) return;

    try {
        const apiPrefix = <?php echo json_encode(($is_super_admin ?? false) ? '/api/qodmin' : '/api/business'); ?>;
        const response = await fetch(`${baseUrl}${apiPrefix}/preparation-screens/${screenId}/remove-printer`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': window.CSRF_TOKEN || '',
                'Accept': 'application/json'
            },
            credentials: 'same-origin',
            body: JSON.stringify({ printer_id: printerId })
        });

        const data = await response.json();
        if (data.success) {
            await loadAssignedPrinters();
        } else {
            window.NotificationManager.error('<?php echo htmlspecialchars(t('preparation_screens.remove_failed', 'Yazıcı kaldırılamadı'), ENT_QUOTES, 'UTF-8'); ?>: ' + (data.error || 'Bilinmeyen hata'));
        }
    } catch (error) {
        console.error('Error removing printer:', error);
        window.NotificationManager.error('<?php echo htmlspecialchars(t('common.error_occurred', 'Bir hata oluştu.'), ENT_QUOTES, 'UTF-8'); ?>');
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

document.addEventListener('DOMContentLoaded', function() {
    loadPrinters();
    loadAssignedPrinters();
});

document.getElementById('screenForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = new FormData(this);
    const categoryIds = Array.from(formData.getAll('category_ids[]'));

    if (categoryIds.length === 0) {
        window.NotificationManager.warning(<?php echo json_encode(t('preparation_screens.categories_required', 'En az bir kategori seçilmelidir'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);
        return;
    }

    const name = formData.get('name');
    const data = {
        name: name,
        slug: generateSlug(name),
        production_point: formData.get('production_point') || null,
        is_active: formData.get('is_active') ? 1 : 0,
        category_ids: categoryIds
    };

    fetch(`<?php echo getAdminUrl('preparation-screens/update'); ?>/${screenId}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': window.CSRF_TOKEN || '',
            'Accept': 'application/json'
        },
        credentials: 'same-origin',
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.href = `<?php echo getAdminUrl('preparation-screens'); ?>`;
        } else {
            window.NotificationManager.error(<?php echo json_encode(t('preparation_screens.update_failed', 'Güncelleme işlemi başarısız oldu: '), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?> + (data.message || <?php echo json_encode(t('common.unknown_error', 'Bilinmeyen hata'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        window.NotificationManager.error(<?php echo json_encode(t('common.error_occurred', 'Bir hata oluştu.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);
    });
});
</script>
