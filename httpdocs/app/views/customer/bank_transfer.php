<?php
/**
 * Bank Transfer Payment Page
 * Shows bank account info, unique code, and receipt upload form
 */
require_once __DIR__ . '/../../helpers/translations.php';

if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../../config/config.php';
}

$subscription = $subscription ?? null;
$package = $package ?? null;
$amount = $amount ?? 0;
$uniqueCode = $uniqueCode ?? '';
$transferId = $transferId ?? '';
$bankAccounts = $bankAccounts ?? [];
$baseUrl = defined('BASE_URL') ? BASE_URL : '';

require_once __DIR__ . '/../../core/Security/CSRFManager.php';
$csrfToken = \App\Core\Security\CSRFManager::generateToken();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <?php require_once __DIR__ . '/../partials/google_analytics.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrfToken); ?>">
    <title>Havale ile Ödeme - Qordy</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-slate-50 via-white to-slate-50 min-h-screen">

<div class="min-h-screen py-6 sm:py-8 px-4 sm:px-6 lg:px-8">
    <div class="max-w-2xl mx-auto space-y-5">

        <!-- Header -->
        <div class="bg-white p-5 sm:p-6 rounded-2xl shadow-sm border border-slate-100">
            <div class="flex items-center gap-3 mb-3">
                <a href="<?php echo $baseUrl; ?>/business/dashboard" class="p-2 hover:bg-slate-100 rounded-lg transition-all">
                    <svg class="w-5 h-5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                </a>
                <h1 class="text-xl sm:text-2xl font-black text-slate-800">Havale ile Ödeme</h1>
            </div>
            <p class="text-sm text-slate-500">Paket: <span class="font-bold text-slate-700"><?php echo htmlspecialchars($package['name'] ?? ''); ?></span></p>
        </div>

        <!-- Unique Code -->
        <div class="bg-amber-50 border-2 border-amber-300 p-5 sm:p-6 rounded-2xl">
            <h2 class="text-sm font-black text-amber-800 uppercase tracking-wider mb-2">Havale Açıklama Kodu</h2>
            <p class="text-xs text-amber-700 mb-3">Bu kodu havale yaparken açıklama kısmına <strong>mutlaka</strong> yazın.</p>
            <div class="bg-white border-2 border-amber-400 rounded-xl p-4 flex items-center justify-between">
                <span id="unique-code" class="text-lg sm:text-xl font-black text-slate-900 tracking-wider"><?php echo htmlspecialchars($uniqueCode); ?></span>
                <button onclick="copyCode()" class="px-3 py-1.5 bg-amber-500 hover:bg-amber-600 text-white rounded-lg text-xs font-bold transition-colors">Kopyala</button>
            </div>
        </div>

        <!-- Amount -->
        <div class="bg-indigo-50 border border-indigo-200 p-5 rounded-2xl flex justify-between items-center">
            <span class="text-sm font-bold text-indigo-700">Ödenecek Tutar</span>
            <span class="text-2xl font-black text-indigo-600"><?php echo number_format($amount, 2, ',', '.'); ?> ₺</span>
        </div>

        <!-- Bank Accounts -->
        <?php if (!empty($bankAccounts)): ?>
        <div class="bg-white p-5 sm:p-6 rounded-2xl shadow-sm border border-slate-100">
            <h2 class="text-sm font-black text-slate-400 uppercase tracking-wider mb-4">Banka Hesap Bilgileri</h2>
            <div class="space-y-3">
                <?php foreach ($bankAccounts as $acc): ?>
                <div class="bg-slate-50 rounded-xl p-4 border border-slate-200">
                    <div class="font-bold text-slate-800 mb-1"><?php echo htmlspecialchars($acc['bank_name']); ?></div>
                    <div class="text-sm text-slate-600 space-y-0.5">
                        <div><span class="font-semibold">IBAN:</span> <span class="font-mono"><?php echo htmlspecialchars($acc['iban']); ?></span>
                            <button onclick="navigator.clipboard.writeText('<?php echo htmlspecialchars($acc['iban']); ?>')" class="ml-2 text-xs text-indigo-600 hover:text-indigo-800 font-bold">Kopyala</button>
                        </div>
                        <div><span class="font-semibold">Hesap Sahibi:</span> <?php echo htmlspecialchars($acc['account_holder']); ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 text-center">
            <p class="text-sm text-slate-500">Banka hesap bilgileri henüz tanımlanmamış. Lütfen destek ile iletişime geçin.</p>
        </div>
        <?php endif; ?>

        <!-- Receipt Upload -->
        <div class="bg-white p-5 sm:p-6 rounded-2xl shadow-sm border border-slate-100">
            <h2 class="text-sm font-black text-slate-400 uppercase tracking-wider mb-4">Dekont Yükleme</h2>
            <form id="receipt-form" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="transfer_id" value="<?php echo htmlspecialchars($transferId); ?>">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

                <div>
                    <label class="block text-xs font-bold text-slate-500 mb-1.5">Gönderen Ad Soyad</label>
                    <input type="text" name="sender_name" placeholder="Havaleyi yapan kişinin adı soyadı"
                           class="w-full p-3 bg-slate-50 rounded-xl border-2 border-slate-200 focus:border-indigo-500 focus:outline-none text-sm font-bold">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-500 mb-1.5">Gönderen IBAN (opsiyonel)</label>
                    <input type="text" name="sender_iban" placeholder="TR..."
                           class="w-full p-3 bg-slate-50 rounded-xl border-2 border-slate-200 focus:border-indigo-500 focus:outline-none text-sm font-mono">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-500 mb-1.5">Dekont (JPG, PNG, PDF - maks 5MB)</label>
                    <input type="file" name="receipt" accept="image/*,.pdf" required
                           class="w-full p-3 bg-slate-50 rounded-xl border-2 border-dashed border-slate-300 focus:border-indigo-500 focus:outline-none text-sm file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-indigo-100 file:text-indigo-700 file:font-bold file:text-xs">
                </div>

                <button type="submit" id="submit-btn"
                        class="w-full py-3.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-black text-sm shadow-lg hover:shadow-xl transition-all">
                    Dekont Yükle ve Gönder
                </button>
            </form>

            <div id="success-message" class="hidden mt-4 bg-green-50 border border-green-300 p-4 rounded-xl text-center">
                <div class="text-2xl mb-2">✅</div>
                <p class="text-sm font-bold text-green-800">Dekontunuz başarıyla yüklendi!</p>
                <p class="text-xs text-green-700 mt-1">Ödemeniz onay aşamasındadır. Kısa süre içerisinde onay verilecektir.</p>
                <a href="<?php echo $baseUrl; ?>/business/dashboard" class="inline-block mt-3 px-5 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg text-xs font-bold transition-colors">Dashboard'a Dön</a>
            </div>
        </div>

        <!-- Instructions -->
        <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
            <h3 class="text-xs font-black text-slate-500 uppercase tracking-wider mb-2">Havale Adımları</h3>
            <ol class="text-xs text-slate-600 space-y-1.5 list-decimal list-inside">
                <li>Yukarıdaki banka hesabına <strong><?php echo number_format($amount, 2, ',', '.'); ?> ₺</strong> tutarında havale yapın</li>
                <li>Açıklama kısmına <strong><?php echo htmlspecialchars($uniqueCode); ?></strong> kodunu yazın</li>
                <li>Havale dekontunuzu yukarıdaki forma yükleyin</li>
                <li>Ödemeniz en kısa sürede kontrol edilip onaylanacaktır</li>
            </ol>
        </div>

    </div>
</div>

<script>
function copyCode() {
    const code = document.getElementById('unique-code').textContent;
    navigator.clipboard.writeText(code).then(() => {
        const btn = event.target;
        btn.textContent = 'Kopyalandı!';
        btn.classList.replace('bg-amber-500', 'bg-green-500');
        setTimeout(() => { btn.textContent = 'Kopyala'; btn.classList.replace('bg-green-500', 'bg-amber-500'); }, 1500);
    });
}

document.getElementById('receipt-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('submit-btn');
    const originalText = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Yükleniyor...';
    btn.classList.add('opacity-60');

    try {
        const formData = new FormData(this);
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

        const resp = await fetch('<?php echo $baseUrl; ?>/customer/payment/upload-receipt', {
            method: 'POST',
            headers: { 'X-CSRF-Token': csrfToken },
            body: formData
        });
        const data = await resp.json();

        if (data.success) {
            document.getElementById('receipt-form').classList.add('hidden');
            document.getElementById('success-message').classList.remove('hidden');
        } else {
            if (window.NotificationManager) { window.NotificationManager.error(data.message || 'Bir hata oluştu.'); } else { alert(data.message || 'Bir hata oluştu.'); }
            btn.disabled = false;
            btn.textContent = originalText;
            btn.classList.remove('opacity-60');
        }
    } catch (err) {
        if (window.NotificationManager) { window.NotificationManager.error('Bağlantı hatası: ' + err.message); } else { alert('Bağlantı hatası: ' + err.message); }
        btn.disabled = false;
        btn.textContent = originalText;
        btn.classList.remove('opacity-60');
    }
});
</script>
</body>
</html>
