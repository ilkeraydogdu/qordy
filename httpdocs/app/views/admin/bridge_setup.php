<?php
// Simplified Bridge Setup - TEK KOD SİSTEMİ
$api_base_url = $api_base_url ?? BASE_URL;
$business_name = $business_name ?? '';
$isSuperAdmin = $is_super_admin ?? false;
$adminPrefix = $isSuperAdmin ? '/qodmin' : '/business';
$hasBusinessContext = !empty($business_id ?? null);
$needsBusinessSelection = $isSuperAdmin && !$hasBusinessContext;
?>

<?php if ($needsBusinessSelection): ?>
<div class="q-page q-biz-theme animate-slide-up">
  <div class="q-container">
    <div id="business-selection-view">
        <header class="flex flex-col sm:flex-row justify-between sm:items-end mb-5 sm:mb-6 lg:mb-8 gap-4 sm:gap-5">
            <div class="flex flex-col gap-3 sm:gap-4 min-w-0 flex-1">
                <h1 class="text-2xl sm:text-3xl lg:text-4xl font-black text-slate-900 tracking-tighter">Yazıcı Köprüsü - İşletme Seçin</h1>
                <p class="text-slate-600 font-medium">Yazıcı köprüsü kurulumunu yapmak istediğiniz işletmeyi seçin</p>
            </div>
            <div class="flex-shrink-0">
                <div class="relative">
                    <input type="text" id="business-search" placeholder="İşletme ara..." onkeyup="BusinessSelector.searchBusinesses(this.value)"
                           class="w-full sm:w-64 px-4 py-2.5 pl-10 bg-white rounded-xl border border-slate-200 text-sm font-bold outline-none focus:border-indigo-500 transition-all">
                    <svg class="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </div>
            </div>
        </header>
        <div id="business-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 sm:gap-6">
            <div class="col-span-full text-center py-12">
                <div class="inline-block animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-indigo-500"></div>
                <p class="mt-4 text-slate-600 font-bold">İşletmeler yükleniyor...</p>
            </div>
        </div>
    </div>

  </div>
</div>
<script>
(function() {
    const bsScript = document.createElement('script');
    bsScript.src = '<?php echo BASE_URL; ?>/assets/js/business-selector.js?v=<?php echo is_file($_SERVER['DOCUMENT_ROOT'] . '/assets/js/business-selector.js') ? filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/js/business-selector.js') : time(); ?>';
    bsScript.onload = function() {
        if (typeof BusinessSelector === 'undefined') return;
        BusinessSelector.init({ baseUrl: <?php echo json_encode(BASE_URL); ?> });
        BusinessSelector.loadBusinesses().then(function() {
            BusinessSelector.renderBusinessGrid('business-grid', function(businessId) {
                window.location.href = '<?php echo BASE_URL . $adminPrefix; ?>/printers/bridge-setup?business_id=' + businessId;
            });
        });
    };
    document.head.appendChild(bsScript);
})();
</script>
<?php else: ?>
<div class="q-page q-biz-theme animate-slide-up">
  <div class="q-container">
    <!-- Header -->
    <div class="mb-6">
        <h1 class="text-3xl sm:text-4xl font-black mb-2 tracking-tighter flex items-center gap-4">
            <div class="w-12 h-12 q-gradient-brand rounded-2xl flex items-center justify-center shadow-lg">
                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                </svg>
            </div>
            <span class="bg-gradient-to-r from-slate-900 to-slate-700 bg-clip-text text-transparent">
                Yazıcı Köprüsü Kurulumu
            </span>
        </h1>
        <p class="text-sm text-slate-600 ml-16 font-medium">Masaüstü uygulaması ile yazıcılarınızı bağlayın</p>
    </div>

    <div class="max-w-6xl mx-auto space-y-6">
        <!-- Kurulum Adımları -->
        <div class="q-bg-brand-soft border-l-4 border-blue-500 p-6 rounded-xl shadow-sm">
            <div class="flex items-start gap-4">
                <div class="flex-shrink-0">
                    <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div class="flex-1">
                    <h3 class="text-lg font-bold text-blue-900 mb-3">Nasıl Çalışır?</h3>
                    <ol class="space-y-2 text-sm text-blue-800">
                        <li class="flex items-start gap-2">
                            <span class="flex-shrink-0 w-6 h-6 q-btn q-btn--primary rounded-full flex items-center justify-center text-xs font-bold">1</span>
                            <a href="https://qordy.com/download/QORDY_Printer_Bridge_Setup.exe" target="_blank" class="inline-flex items-center gap-2 px-3 py-1.5 q-btn q-btn--primary text-white rounded-lg font-bold text-xs transition-colors shadow-sm">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                                </svg>
                                Masaüstü Uygulamasını İndirin
                            </a>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="flex-shrink-0 w-6 h-6 q-btn q-btn--primary rounded-full flex items-center justify-center text-xs font-bold">2</span>
                            <span>Aşağıda <strong>"Yeni Köprü Oluştur"</strong> butonuna tıklayın ve köprüye bir isim verin</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="flex-shrink-0 w-6 h-6 q-btn q-btn--primary rounded-full flex items-center justify-center text-xs font-bold">3</span>
                            <span>Oluşturulan <strong>kodu kopyalayın</strong> ve masaüstü uygulamasına yapıştırın</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="flex-shrink-0 w-6 h-6 q-btn q-btn--primary rounded-full flex items-center justify-center text-xs font-bold">4</span>
                            <span>Masaüstü uygulaması <strong>otomatik olarak</strong> bağlanır, yazıcıları tespit eder ve ekranlara atama yapmanızı sağlar</span>
                        </li>
                    </ol>
                </div>
            </div>
        </div>

        <!-- Yeni Köprü Oluştur Butonu -->
        <div class="flex justify-center">
            <button 
                onclick="openCreateModal()"
                class="px-8 py-4 q-gradient-brand hover:from-green-500 hover:to-green-400 text-white font-bold rounded-xl transition-all shadow-lg hover:shadow-xl flex items-center gap-3 text-lg">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Yeni Köprü Oluştur
            </button>
        </div>

        <!-- Köprü Listesi -->
        <div class="bg-white rounded-2xl shadow-lg border border-slate-200 overflow-hidden">
            <div class="bg-gradient-to-r from-slate-800 to-slate-700 px-6 py-4">
                <h3 class="text-lg font-bold text-white flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path>
                    </svg>
                    Köprülerim
                </h3>
            </div>
            <div class="p-6">
                <!-- Loading -->
                <div id="bridgeListLoading" class="text-center py-8">
                    <svg class="animate-spin h-8 w-8 mx-auto text-amber-600" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <p class="mt-2 text-slate-500">Yükleniyor...</p>
                </div>

                <!-- Empty -->
                <div id="bridgeListEmpty" class="hidden text-center py-12">
                    <svg class="w-16 h-16 mx-auto text-slate-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                    </svg>
                    <p class="text-slate-500 font-medium mb-2">Henüz köprü oluşturulmamış</p>
                    <p class="text-sm text-slate-400">Yukarıdaki "Yeni Köprü Oluştur" butonuna tıklayın</p>
                </div>

                <!-- Table -->
                <div id="bridgeListTable" class="hidden overflow-x-auto">
                    <table class="q-table">
                        <thead class="bg-slate-50 border-b-2 border-slate-200">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-bold text-slate-700 uppercase">İsim</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-slate-700 uppercase">Bağlantı Kodu</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-slate-700 uppercase">Durum</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-slate-700 uppercase">Son Görülme</th>
                                <th class="px-4 py-3 text-right text-xs font-bold text-slate-700 uppercase">İşlemler</th>
                            </tr>
                        </thead>
                        <tbody id="bridgeListBody" class="divide-y divide-slate-200">
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

  </div>
</div>

<!-- Yeni Köprü Oluştur Modal -->
<div id="createBridgeModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full">
        <div class="q-gradient-brand px-6 py-4 rounded-t-2xl">
            <h3 class="text-xl font-bold text-white flex items-center gap-2">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Yeni Köprü Oluştur
            </h3>
        </div>
        <form id="createBridgeForm" class="p-6">
            <div class="mb-6">
                <label for="bridgeName" class="block text-sm font-bold text-slate-700 mb-2">
                    Köprü Adı <span class="text-red-500">*</span>
                </label>
                <input 
                    type="text" 
                    id="bridgeName" 
                    name="bridge_name"
                    placeholder="Örn: Kasa Bilgisayarı, Mutfak PC"
                    required
                    minlength="3"
                    maxlength="100"
                    class="w-full px-4 py-3 border-2 border-slate-300 rounded-xl focus:outline-none focus:border-amber-500">
                <p class="mt-1 text-xs text-slate-500">Bu bilgisayarı tanımlamak için bir isim verin</p>
            </div>
            <div class="flex gap-3">
                <button 
                    type="button" 
                    onclick="closeCreateModal()"
                    class="flex-1 px-4 py-3 bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold rounded-xl">
                    İptal
                </button>
                <button 
                    type="submit"
                    id="createBridgeBtn"
                    class="flex-1 px-4 py-3 q-gradient-brand hover:from-green-500 hover:to-green-400 text-white font-bold rounded-xl shadow-lg flex items-center justify-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    Oluştur
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Kod Göster Modal -->
<div id="showCodeModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full">
        <div class="bg-gradient-to-r from-purple-600 to-purple-500 px-6 py-4 rounded-t-2xl">
            <h3 class="text-xl font-bold text-white flex items-center gap-2">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path>
                </svg>
                Bağlantı Kodu
            </h3>
        </div>
        <div class="p-6">
            <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6">
                <p class="text-sm font-bold text-yellow-800 mb-1">⚠️ Bu kodu masaüstü uygulamasına girin</p>
                <p class="text-xs text-yellow-700">
                    Bu kod ile masaüstü uygulaması otomatik olarak işletmenize bağlanacak, 
                    hazırlık ekranlarınızı çekecek ve yazıcı ataması yapmanızı sağlayacak.
                </p>
            </div>
            
            <div class="mb-6">
                <label class="block text-sm font-bold text-slate-700 mb-2">Bağlantı Kodu:</label>
                <div class="flex items-center gap-2">
                    <code id="modalConfigCode" class="flex-1 text-xs font-mono text-slate-800 bg-slate-100 px-4 py-3 rounded-lg border-2 border-purple-300 break-all"></code>
                    <button 
                        onclick="copyModalCode()"
                        class="px-4 py-3 q-btn q-btn--primary text-white rounded-lg transition-colors flex items-center gap-2 font-bold">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                        </svg>
                        Kopyala
                    </button>
                </div>
            </div>
            
            <div class="flex gap-3">
                <button 
                    onclick="closeCodeModal()"
                    class="flex-1 px-4 py-3 bg-slate-600 hover:bg-slate-700 text-white font-bold rounded-xl">
                    Kapat
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// ========== API Functions ==========
const API_BASE = '<?php echo $api_base_url; ?>';
const BUSINESS_ID = '<?php echo htmlspecialchars($business_id ?? '', ENT_QUOTES); ?>';
const BUSINESS_QUERY = BUSINESS_ID ? `?business_id=${encodeURIComponent(BUSINESS_ID)}` : '';
const BUSINESS_QUERY_AMP = BUSINESS_ID ? `&business_id=${encodeURIComponent(BUSINESS_ID)}` : '';

async function loadBridges() {
    const loading = document.getElementById('bridgeListLoading');
    const empty = document.getElementById('bridgeListEmpty');
    const table = document.getElementById('bridgeListTable');
    
    try {
        const response = await fetch(`${API_BASE}/api/business/printer-bridges${BUSINESS_QUERY}`);
        const data = await response.json();
        
        loading.classList.add('hidden');
        
        if (data.success && data.bridges && data.bridges.length > 0) {
            empty.classList.add('hidden');
            table.classList.remove('hidden');
            renderBridges(data.bridges);
        } else {
            empty.classList.remove('hidden');
            table.classList.add('hidden');
        }
    } catch (error) {
        loading.classList.add('hidden');
        
        // Show user-friendly error message
        const errorDiv = document.createElement('div');
        errorDiv.className = 'bg-red-50 border-l-4 border-red-500 p-4 rounded';
        errorDiv.innerHTML = `
            <div class="flex items-start">
                <svg class="w-5 h-5 text-red-600 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <div>
                    <p class="text-sm font-bold text-red-800">Köprüler yüklenemedi</p>
                    <p class="text-xs text-red-700 mt-1">Lütfen sayfayı yenileyin veya giriş yapın.</p>
                </div>
            </div>
        `;
        
        empty.innerHTML = '';
        empty.appendChild(errorDiv);
        empty.classList.remove('hidden');
        table.classList.add('hidden');
    }
}

function renderBridges(bridges) {
    const tbody = document.getElementById('bridgeListBody');
    tbody.innerHTML = '';
    
    bridges.forEach(bridge => {
        const row = document.createElement('tr');
        row.className = 'hover:bg-slate-50 transition-colors';
        
        const statusClass = bridge.status === 'ONLINE' ? 'bg-green-100 text-green-800' : 'bg-slate-100 text-slate-600';
        const statusIcon = bridge.status === 'ONLINE' ? '🟢' : '🔴';
        
        row.innerHTML = `
            <td class="px-4 py-4">
                <div class="font-bold text-slate-900">${escapeHtml(bridge.bridge_name || bridge.name || 'Adsız Köprü')}</div>
            </td>
            <td class="px-4 py-4">
                <div class="flex items-center gap-2">
                    <code class="text-xs font-mono text-slate-600 bg-slate-100 px-2 py-1 rounded">${escapeHtml((bridge.config_code || '').substring(0, 16))}...</code>
                    <button 
                        onclick="showCode('${escapeHtml(bridge.config_code)}')"
                        class="px-2 py-1 q-btn q-btn--primary text-white text-xs rounded transition-colors">
                        Göster
                    </button>
                    <button 
                        onclick="copyCode('${escapeHtml(bridge.config_code)}')"
                        class="px-2 py-1 bg-blue-600 hover:bg-blue-700 text-white text-xs rounded transition-colors">
                        Kopyala
                    </button>
                </div>
            </td>
            <td class="px-4 py-4">
                <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-bold ${statusClass}">
                    ${statusIcon} ${bridge.status === 'ONLINE' ? 'Çevrimiçi' : 'Çevrimdışı'}
                </span>
            </td>
            <td class="px-4 py-4 text-sm text-slate-600">
                ${bridge.last_seen ? formatDate(bridge.last_seen) : 'Hiç görülmedi'}
            </td>
            <td class="px-4 py-4 text-right">
                <button 
                    onclick="deleteBridge('${bridge.bridge_id}')"
                    class="px-3 py-1.5 bg-red-600 hover:bg-red-700 text-white text-xs rounded transition-colors font-bold">
                    Sil
                </button>
            </td>
        `;
        
        tbody.appendChild(row);
    });
}

// ========== Modal Functions ==========
function openCreateModal() {
    document.getElementById('createBridgeModal').classList.remove('hidden');
    document.getElementById('bridgeName').focus();
}

function closeCreateModal() {
    document.getElementById('createBridgeModal').classList.add('hidden');
    document.getElementById('createBridgeForm').reset();
}

function showCode(code) {
    document.getElementById('modalConfigCode').textContent = code;
    document.getElementById('showCodeModal').classList.remove('hidden');
}

function closeCodeModal() {
    document.getElementById('showCodeModal').classList.add('hidden');
}

// ========== Create Bridge ==========
document.getElementById('createBridgeForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const btn = document.getElementById('createBridgeBtn');
    const bridgeName = document.getElementById('bridgeName').value;
    
    btn.disabled = true;
    btn.innerHTML = '<svg class="animate-spin h-5 w-5 mx-auto" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>';
    
    try {
        const response = await fetch(`${API_BASE}/api/business/printer-bridges/create${BUSINESS_QUERY}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ bridge_name: bridgeName })
        });
        
        const data = await response.json();
        
        if (data.success) {
            closeCreateModal();
            showCode(data.config_code);
            loadBridges();
            
            if (window.NotificationManager) window.NotificationManager.success('Köprü oluşturuldu! Kodu masaüstü uygulamasına girin.');
        } else {
            if (window.NotificationManager) window.NotificationManager.error(data.error || 'Köprü oluşturulamadı');
        }
    } catch (error) {
        if (window.NotificationManager) window.NotificationManager.error('Köprü oluşturulamadı. Lütfen tekrar deneyin.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg> Oluştur';
    }
});

// ========== Delete Bridge ==========
async function deleteBridge(bridgeId) {
    let confirmed = false;
    if (window.NotificationManager && window.NotificationManager.confirm) {
        confirmed = await window.NotificationManager.confirm('Bu köprüyü silmek istediğinizden emin misiniz?', 'Onay');
    } else {
        confirmed = confirm('Bu köprüyü silmek istediğinizden emin misiniz?');
    }
    if (!confirmed) return;
    
    try {
        const response = await fetch(`${API_BASE}/api/business/printer-bridges/${bridgeId}${BUSINESS_QUERY}`, {
            method: 'DELETE'
        });
        
        const data = await response.json();
        
        if (data.success) {
            if (window.NotificationManager) window.NotificationManager.success('Köprü silindi');
            loadBridges();
        } else {
            if (window.NotificationManager) window.NotificationManager.error(data.error || 'Silinemedi');
        }
    } catch (error) {
        if (window.NotificationManager) window.NotificationManager.error('Köprü silinemedi. Lütfen tekrar deneyin.');
    }
}

// ========== Regenerate Code ==========
async function regenerateCode(bridgeId) {
    let confirmed = false;
    if (window.NotificationManager && window.NotificationManager.confirm) {
        confirmed = await window.NotificationManager.confirm('Bu köprü için yeni bir yapılandırma kodu oluşturulsun mu?\n\n⚠️ Eski kod geçersiz olacak ve masaüstü uygulamasını yeniden yapılandırmanız gerekecek.', 'Onay');
    } else {
        confirmed = confirm('Bu köprü için yeni bir yapılandırma kodu oluşturulsun mu?\n\n⚠️ Eski kod geçersiz olacak ve masaüstü uygulamasını yeniden yapılandırmanız gerekecek.');
    }
    if (!confirmed) return;
    
    try {
        const response = await fetch(`${API_BASE}/api/business/printer-bridges/${bridgeId}/regenerate${BUSINESS_QUERY}`, {
            method: 'POST'
        });

        const data = await response.json();
        
        if (data.success && data.config_code) {
            showCode(data.config_code);
            if (window.NotificationManager) window.NotificationManager.success('Yeni kod oluşturuldu!');
            loadBridges();
        } else {
            if (window.NotificationManager) window.NotificationManager.error(data.error || 'Kod oluşturulamadı');
        }
    } catch (error) {
        if (window.NotificationManager) window.NotificationManager.error('Kod oluşturulamadı. Lütfen tekrar deneyin.');
    }
}

// ========== Copy Functions ==========
function copyCode(code) {
    navigator.clipboard.writeText(code).then(() => {
        if (window.NotificationManager) window.NotificationManager.success('Kod kopyalandı!');
    });
}

function copyModalCode() {
    const code = document.getElementById('modalConfigCode').textContent;
    navigator.clipboard.writeText(code).then(() => {
        if (window.NotificationManager) window.NotificationManager.success('Kod kopyalandı!');
    });
}

// ========== Helpers ==========
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatDate(dateStr) {
    const date = new Date(dateStr);
    const now = new Date();
    const diff = now - date;
    const minutes = Math.floor(diff / 60000);
    
    if (minutes < 1) return 'Az önce';
    if (minutes < 60) return `${minutes} dakika önce`;
    if (minutes < 1440) return `${Math.floor(minutes / 60)} saat önce`;
    return date.toLocaleDateString('tr-TR');
}

// ========== Initialize ==========
document.addEventListener('DOMContentLoaded', () => {
    loadBridges();
});
</script>
<?php endif; ?>
