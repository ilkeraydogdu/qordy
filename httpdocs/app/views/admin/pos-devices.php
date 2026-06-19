<?php
require_once __DIR__ . '/../../core/Authorization.php';
$auth = \App\Core\Authorization::getInstance();
$canEdit = $auth->hasPermission('settings.edit');
$devices = $devices ?? [];
$isSuperAdmin = $is_super_admin ?? false;
$apiPrefix = $isSuperAdmin ? '/api/qodmin' : '/api/business';
$adminPrefix = $isSuperAdmin ? '/qodmin' : '/business';
?>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/business-theme.css?v=<?php echo @filemtime(dirname(__DIR__, 3) . '/public/assets/css/business-theme.css'); ?>">
<div class="q-page q-biz-theme animate-slide-up">
  <div class="q-container q-stack q-stack--lg">
    <header class="q-page-header">
      <div class="min-w-0">
        <p class="q-page-header__eyebrow">Ayarlar</p>
        <h1 class="q-page-header__title"><?php echo t('pos.devices.title', 'POS Cihaz Yönetimi'); ?></h1>
        <p class="q-page-header__subtitle">Yazarkasa ve ödeme terminali bağlantılarını yönetin</p>
      </div>
      <?php if ($canEdit): ?>
      <div class="q-page-header__actions">
        <button type="button" onclick="showAddDeviceModal()" class="q-btn q-btn--primary q-btn--sm whitespace-nowrap">
          + Yeni Cihaz
        </button>
      </div>
      <?php endif; ?>
    </header>

    <?php if (empty($devices)): ?>
    <div class="q-card q-card--pad q-empty q-empty--inline">
      <p class="q-empty__title">Henüz POS cihazı tanımlanmamış</p>
      <?php if ($canEdit): ?>
      <button type="button" onclick="showAddDeviceModal()" class="q-btn q-btn--primary q-btn--sm mt-4">İlk cihazı ekle</button>
      <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="q-stack q-stack--md min-w-0">
      <?php foreach ($devices as $device): ?>
      <div class="q-card q-card--pad q-stack q-stack--md min-w-0">
        <div class="q-toolbar q-toolbar--between flex-wrap gap-3">
          <div class="min-w-0 flex-1">
            <h2 class="q-page-header__title text-base sm:text-lg truncate" style="margin:0;"><?php echo htmlspecialchars($device['device_name']); ?></h2>
            <p class="q-hint mt-1">
              Tip: <?php echo htmlspecialchars($device['device_type']); ?> ·
              Bağlantı: <?php echo htmlspecialchars($device['connection_type']); ?>
            </p>
          </div>
          <div class="q-toolbar flex-wrap">
            <button type="button" onclick="testDevice('<?php echo htmlspecialchars($device['device_id']); ?>')" class="q-btn q-btn--ghost q-btn--sm">
              Test Et
            </button>
            <label class="relative inline-flex items-center cursor-pointer shrink-0">
              <input type="checkbox"
                     class="sr-only peer"
                     data-device-id="<?php echo htmlspecialchars($device['device_id']); ?>"
                     <?php echo ($device['is_enabled'] == 1) ? 'checked' : ''; ?>
                     <?php echo $canEdit ? '' : 'disabled'; ?>
                     onchange="toggleDevice('<?php echo htmlspecialchars($device['device_id']); ?>', this.checked)">
              <span class="w-11 h-6 bg-slate-300 rounded-full peer peer-checked:bg-indigo-600 transition-all relative after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:w-5 after:h-5 after:bg-white after:rounded-full after:transition-all peer-checked:after:translate-x-4"></span>
            </label>
          </div>
        </div>

        <?php if ($canEdit): ?>
        <div class="q-stack q-stack--sm">
          <?php if ($device['connection_type'] === 'serial'): ?>
          <div class="q-stack q-stack--sm">
            <label class="q-label">Seri Port</label>
            <input type="text" class="q-input"
                   value="<?php echo htmlspecialchars($device['serial_port'] ?? ''); ?>"
                   data-device-id="<?php echo htmlspecialchars($device['device_id']); ?>"
                   data-field="serial_port">
          </div>
          <?php elseif ($device['connection_type'] === 'network'): ?>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 min-w-0">
            <div class="q-stack q-stack--sm min-w-0">
              <label class="q-label">Host</label>
              <input type="text" class="q-input"
                     value="<?php echo htmlspecialchars($device['network_host'] ?? ''); ?>"
                     data-device-id="<?php echo htmlspecialchars($device['device_id']); ?>"
                     data-field="network_host">
            </div>
            <div class="q-stack q-stack--sm min-w-0">
              <label class="q-label">Port</label>
              <input type="number" class="q-input"
                     value="<?php echo htmlspecialchars($device['network_port'] ?? 9100); ?>"
                     data-device-id="<?php echo htmlspecialchars($device['device_id']); ?>"
                     data-field="network_port">
            </div>
          </div>
          <?php elseif ($device['connection_type'] === 'api'): ?>
          <div class="q-stack q-stack--sm">
            <label class="q-label">API Endpoint</label>
            <input type="text" class="q-input"
                   value="<?php echo htmlspecialchars($device['api_endpoint'] ?? ''); ?>"
                   data-device-id="<?php echo htmlspecialchars($device['device_id']); ?>"
                   data-field="api_endpoint">
          </div>
          <div class="q-stack q-stack--sm">
            <label class="q-label">API Key</label>
            <input type="password" class="q-input"
                   value="<?php echo htmlspecialchars($device['api_key'] ?? ''); ?>"
                   data-device-id="<?php echo htmlspecialchars($device['device_id']); ?>"
                   data-field="api_key">
          </div>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Add Device Modal -->
<div id="addDeviceModal" class="hidden fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="add-device-title">
  <div class="flex items-end sm:items-center justify-center min-h-screen p-4">
    <div class="fixed inset-0 bg-slate-900/50" onclick="closeAddDeviceModal()" aria-hidden="true"></div>
    <div class="relative q-card q-card--pad w-full max-w-lg q-stack q-stack--lg min-w-0">
      <div class="q-toolbar q-toolbar--between">
        <h3 id="add-device-title" class="q-page-header__title text-lg" style="margin:0;">Yeni POS Cihaz Ekle</h3>
        <button type="button" onclick="closeAddDeviceModal()" class="q-icon-btn" aria-label="Kapat">×</button>
      </div>
      <form id="addDeviceForm" onsubmit="submitAddDevice(event)" class="q-stack q-stack--md">
        <div class="q-stack q-stack--sm">
          <label class="q-label" for="device_name">Cihaz Adı *</label>
          <input type="text" id="device_name" required class="q-input">
        </div>
        <div class="q-stack q-stack--sm">
          <label class="q-label" for="device_type">Cihaz Tipi</label>
          <input type="text" id="device_type" value="POS" class="q-input">
        </div>
        <div class="q-stack q-stack--sm">
          <label class="q-label" for="connection_type">Bağlantı Tipi *</label>
          <select id="connection_type" required onchange="toggleConnectionFields()" class="q-input">
            <option value="serial">Seri Port</option>
            <option value="network">Ağ (Network)</option>
            <option value="api">API</option>
          </select>
        </div>
        <div id="serialFields" class="q-stack q-stack--sm">
          <label class="q-label" for="serial_port">Seri Port</label>
          <input type="text" id="serial_port" class="q-input" placeholder="Örn: COM1, /dev/ttyUSB0">
        </div>
        <div id="networkFields" class="hidden grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div class="q-stack q-stack--sm">
            <label class="q-label" for="network_host">Host</label>
            <input type="text" id="network_host" class="q-input" placeholder="192.168.1.100">
          </div>
          <div class="q-stack q-stack--sm">
            <label class="q-label" for="network_port">Port</label>
            <input type="number" id="network_port" value="9100" class="q-input">
          </div>
        </div>
        <div id="apiFields" class="hidden q-stack q-stack--sm">
          <div class="q-stack q-stack--sm">
            <label class="q-label" for="api_endpoint">API Endpoint</label>
            <input type="text" id="api_endpoint" class="q-input" placeholder="https://api.example.com/pos">
          </div>
          <div class="q-stack q-stack--sm">
            <label class="q-label" for="api_key">API Key</label>
            <input type="password" id="api_key" class="q-input">
          </div>
        </div>
        <div class="q-toolbar q-toolbar--between flex-wrap gap-3 pt-2">
          <button type="button" onclick="closeAddDeviceModal()" class="q-btn q-btn--ghost">İptal</button>
          <button type="submit" class="q-btn q-btn--primary">Ekle</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function toggleDevice(deviceId, enabled) {
    const csrfToken = window.CSRF_TOKEN || '';
    fetch('<?php echo BASE_URL . $apiPrefix; ?>/pos-device/update', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
        body: JSON.stringify({ device_id: deviceId, is_enabled: enabled })
    }).then(r => r.json()).then(data => {
        if (!data.success && window.NotificationManager) {
            window.NotificationManager.error('Hata: ' + (data.error || 'Bilinmeyen hata'));
        }
    });
}

function testDevice(deviceId) {
    const csrfToken = window.CSRF_TOKEN || '';
    fetch('<?php echo BASE_URL . $apiPrefix; ?>/pos-device/test', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
        body: JSON.stringify({ device_id: deviceId })
    }).then(r => r.json()).then(data => {
        if (!window.NotificationManager) return;
        if (data.success && data.connected) {
            window.NotificationManager.success('Cihaz bağlantısı başarılı!');
        } else {
            window.NotificationManager.error('Cihaz bağlantısı başarısız: ' + (data.error || 'Bilinmeyen hata'));
        }
    });
}

function showAddDeviceModal() {
    document.getElementById('addDeviceModal').classList.remove('hidden');
    document.getElementById('addDeviceForm').reset();
    document.getElementById('device_type').value = 'POS';
    document.getElementById('connection_type').value = 'serial';
    toggleConnectionFields();
}

function closeAddDeviceModal() {
    document.getElementById('addDeviceModal').classList.add('hidden');
}

function toggleConnectionFields() {
    const connectionType = document.getElementById('connection_type').value;
    ['serialFields', 'networkFields', 'apiFields'].forEach(id => document.getElementById(id).classList.add('hidden'));
    if (connectionType === 'serial') document.getElementById('serialFields').classList.remove('hidden');
    else if (connectionType === 'network') document.getElementById('networkFields').classList.remove('hidden');
    else if (connectionType === 'api') document.getElementById('apiFields').classList.remove('hidden');
}

function submitAddDevice(event) {
    event.preventDefault();
    const connectionType = document.getElementById('connection_type').value;
    const requestData = {
        device_name: document.getElementById('device_name').value.trim(),
        device_type: document.getElementById('device_type').value.trim() || 'POS',
        connection_type: connectionType
    };
    if (connectionType === 'serial') requestData.serial_port = document.getElementById('serial_port').value.trim();
    else if (connectionType === 'network') {
        requestData.network_host = document.getElementById('network_host').value.trim();
        requestData.network_port = parseInt(document.getElementById('network_port').value, 10) || 9100;
    } else if (connectionType === 'api') {
        requestData.api_endpoint = document.getElementById('api_endpoint').value.trim();
        requestData.api_key = document.getElementById('api_key').value.trim();
    }
    const csrfToken = window.CSRF_TOKEN || '';
    const submitBtn = event.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    submitBtn.disabled = true;
    submitBtn.textContent = 'Ekleniyor...';
    fetch('<?php echo BASE_URL . $apiPrefix; ?>/pos-device/add', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
        body: JSON.stringify(requestData)
    }).then(r => r.json()).then(data => {
        if (data.success) {
            if (window.NotificationManager) window.NotificationManager.success('Cihaz başarıyla eklendi');
            closeAddDeviceModal();
            setTimeout(() => window.location.reload(), 500);
        } else {
            if (window.NotificationManager) window.NotificationManager.error('Hata: ' + (data.error || 'Bilinmeyen hata'));
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
    }).catch(error => {
        if (window.NotificationManager) window.NotificationManager.error('Bir hata oluştu: ' + error.message);
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
    });
}
</script>
