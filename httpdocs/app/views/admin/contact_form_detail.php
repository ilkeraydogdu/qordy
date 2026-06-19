<?php
require_once __DIR__ . '/../../helpers/translations.php';

$contactForm = $contactForm ?? null;

if (!$contactForm) {
    header('Location: ' . BASE_URL . '/qodmin/contact-forms');
    exit;
}

$title = 'İletişim Formu Detayı' . ' - ' . getAppConfig()->getAppName();
?>

<div class="q-page q-biz-theme animate-slide-up">
  <div class="q-container">
    <div class="mb-4 sm:mb-6">
        <a href="<?php echo BASE_URL; ?>/qodmin/contact-forms" class="text-indigo-600 hover:text-indigo-700 font-bold text-sm sm:text-base">
            ← İletişim Formları Listesi
        </a>
    </div>
    
    <header class="flex flex-col sm:flex-row justify-between sm:items-center mb-4 sm:mb-6 lg:mb-8 gap-3 sm:gap-4">
        <div>
            <h1 class="text-xl sm:text-2xl lg:text-3xl font-black text-slate-900 tracking-tighter">İletişim Formu Detayı</h1>
            <p class="text-slate-400 font-bold uppercase text-[8px] sm:text-[9px] lg:text-[10px] tracking-widest mt-1">Form #<?php echo htmlspecialchars($contactForm['contact_id']); ?></p>
        </div>
    </header>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6">
        <!-- Contact Form Information -->
        <div class="bg-white rounded-xl sm:rounded-2xl p-4 sm:p-6 lg:p-8 shadow-soft border border-slate-100">
            <h2 class="text-lg sm:text-xl font-black text-slate-900 mb-4 sm:mb-6">İletişim Bilgileri</h2>
            
            <div class="space-y-4">
                <div>
                    <label class="text-xs sm:text-sm font-bold text-slate-500 uppercase tracking-wider block mb-1">Ad Soyad</label>
                    <p class="text-sm sm:text-base font-bold text-slate-900">
                        <?php echo htmlspecialchars($contactForm['full_name'] ?? '-'); ?>
                    </p>
                </div>
                
                <div>
                    <label class="text-xs sm:text-sm font-bold text-slate-500 uppercase tracking-wider block mb-1">E-posta</label>
                    <p class="text-sm sm:text-base font-bold text-slate-900">
                        <a href="mailto:<?php echo htmlspecialchars($contactForm['email'] ?? ''); ?>" class="text-indigo-600 hover:text-indigo-700">
                            <?php echo htmlspecialchars($contactForm['email'] ?? '-'); ?>
                        </a>
                    </p>
                </div>
                
                <?php if (!empty($contactForm['phone'])): ?>
                <div>
                    <label class="text-xs sm:text-sm font-bold text-slate-500 uppercase tracking-wider block mb-1">Telefon</label>
                    <p class="text-sm sm:text-base font-bold text-slate-900">
                        <a href="tel:<?php echo htmlspecialchars($contactForm['phone']); ?>" class="text-indigo-600 hover:text-indigo-700">
                            <?php echo htmlspecialchars($contactForm['phone']); ?>
                        </a>
                    </p>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($contactForm['company_name'])): ?>
                <div>
                    <label class="text-xs sm:text-sm font-bold text-slate-500 uppercase tracking-wider block mb-1">İşletme Adı</label>
                    <p class="text-sm sm:text-base font-bold text-slate-900">
                        <?php echo htmlspecialchars($contactForm['company_name']); ?>
                    </p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Status and Metadata -->
        <div class="bg-white rounded-xl sm:rounded-2xl p-4 sm:p-6 lg:p-8 shadow-soft border border-slate-100">
            <h2 class="text-lg sm:text-xl font-black text-slate-900 mb-4 sm:mb-6">Durum ve Bilgiler</h2>
            
            <div class="space-y-4">
                <div>
                    <label class="text-xs sm:text-sm font-bold text-slate-500 uppercase tracking-wider block mb-1">Durum</label>
                    <p class="mt-1">
                        <?php
                        $statusColors = [
                            'new' => 'bg-blue-100 text-blue-700',
                            'contacted' => 'bg-yellow-100 text-yellow-700',
                            'closed' => 'bg-green-100 text-green-700'
                        ];
                        $statusLabels = [
                            'new' => 'Yeni',
                            'contacted' => 'İletişime Geçildi',
                            'closed' => 'Kapatıldı'
                        ];
                        $currentStatus = $contactForm['status'] ?? 'new';
                        ?>
                        <span class="px-3 py-2 rounded-lg text-sm font-bold <?php echo $statusColors[$currentStatus] ?? 'bg-slate-100 text-slate-700'; ?>">
                            <?php echo $statusLabels[$currentStatus] ?? 'Bilinmiyor'; ?>
                        </span>
                    </p>
                </div>
                
                <div>
                    <label class="text-xs sm:text-sm font-bold text-slate-500 uppercase tracking-wider block mb-1">Gönderilme Tarihi</label>
                    <p class="text-sm sm:text-base font-bold text-slate-900">
                        <?php 
                        $createdAt = !empty($contactForm['created_at']) ? new DateTime($contactForm['created_at']) : null;
                        echo $createdAt ? $createdAt->format('d.m.Y H:i') : '-';
                        ?>
                    </p>
                </div>
                
                <?php if (!empty($contactForm['contacted_at'])): ?>
                <div>
                    <label class="text-xs sm:text-sm font-bold text-slate-500 uppercase tracking-wider block mb-1">İletişime Geçilme Tarihi</label>
                    <p class="text-sm sm:text-base font-bold text-slate-900">
                        <?php 
                        $contactedAt = new DateTime($contactForm['contacted_at']);
                        echo $contactedAt->format('d.m.Y H:i');
                        ?>
                    </p>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($contactForm['ip_address'])): ?>
                <div>
                    <label class="text-xs sm:text-sm font-bold text-slate-500 uppercase tracking-wider block mb-1">IP Adresi</label>
                    <p class="text-sm sm:text-base font-mono text-slate-600">
                        <?php echo htmlspecialchars($contactForm['ip_address']); ?>
                    </p>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Status Update Actions -->
            <div class="mt-6 pt-6 border-t border-slate-200">
                <h3 class="text-sm sm:text-base font-black text-slate-700 mb-3 sm:mb-4">Durum Güncelle</h3>
                <div class="flex flex-wrap gap-2 sm:gap-3">
                    <?php if ($currentStatus === 'new'): ?>
                        <button onclick="updateStatus('<?php echo htmlspecialchars($contactForm['contact_id']); ?>', 'contacted')" 
                                class="px-4 sm:px-5 py-2 sm:py-2.5 bg-yellow-500 text-white rounded-lg sm:rounded-xl text-xs sm:text-sm font-black hover:bg-yellow-600 transition-all shadow-lg hover:shadow-xl active:scale-95">
                            İletişime Geçildi Olarak İşaretle
                        </button>
                    <?php endif; ?>
                    <?php if ($currentStatus !== 'closed'): ?>
                        <button onclick="updateStatus('<?php echo htmlspecialchars($contactForm['contact_id']); ?>', 'closed')" 
                                class="px-4 sm:px-5 py-2 sm:py-2.5 bg-green-500 text-white rounded-lg sm:rounded-xl text-xs sm:text-sm font-black hover:bg-green-600 transition-all shadow-lg hover:shadow-xl active:scale-95">
                            Kapat
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Message -->
    <?php if (!empty($contactForm['message'])): ?>
    <div class="mt-4 sm:mt-6 bg-white rounded-xl sm:rounded-2xl p-4 sm:p-6 lg:p-8 shadow-soft border border-slate-100">
        <h2 class="text-lg sm:text-xl font-black text-slate-900 mb-4">Mesaj</h2>
        <div class="bg-slate-50 rounded-lg p-4">
            <p class="text-sm sm:text-base text-slate-700 whitespace-pre-wrap">
                <?php echo nl2br(htmlspecialchars($contactForm['message'])); ?>
            </p>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Reply Section -->
    <div class="mt-4 sm:mt-6 bg-white rounded-xl sm:rounded-2xl p-4 sm:p-6 lg:p-8 shadow-soft border border-slate-100">
        <h2 class="text-lg sm:text-xl font-black text-slate-900 mb-4 sm:mb-6">Yanıt Gönder</h2>
        <div class="space-y-4 sm:space-y-5">
            <div>
                <label class="block text-xs sm:text-sm font-black text-slate-700 mb-2 uppercase tracking-wider">Yanıt Metni</label>
                <textarea id="replyMessage" rows="6" class="w-full px-4 py-3 border border-slate-300 rounded-lg sm:rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm font-bold text-slate-900 placeholder:text-slate-400" placeholder="Yanıt metninizi buraya yazın..."></textarea>
            </div>
            <div class="flex flex-wrap gap-2 sm:gap-3">
                <button onclick="improveTextWithGemini(event)" class="px-4 sm:px-5 md:px-6 py-2 sm:py-2.5 md:py-3 bg-purple-500 text-white rounded-lg sm:rounded-xl text-xs sm:text-sm font-black hover:bg-purple-600 transition-all shadow-lg hover:shadow-xl active:scale-95 flex items-center gap-2">
                    <span>✨</span>
                    <span>Gemini ile Düzelt</span>
                </button>
                <button onclick="sendReply(event)" class="px-4 sm:px-5 md:px-6 py-2 sm:py-2.5 md:py-3 bg-indigo-500 text-white rounded-lg sm:rounded-xl text-xs sm:text-sm font-black hover:bg-indigo-700 transition-all shadow-lg hover:shadow-xl active:scale-95 flex items-center gap-2">
                    <span>📧</span>
                    <span>Yanıtla ve Gönder</span>
                </button>
            </div>
        </div>
    </div>
    
    <?php if (!empty($contactForm['notes'])): ?>
    <div class="mt-4 sm:mt-6 bg-white rounded-xl sm:rounded-2xl p-4 sm:p-6 lg:p-8 shadow-soft border border-slate-100">
        <h2 class="text-lg sm:text-xl font-black text-slate-900 mb-4">Notlar</h2>
        <div class="bg-yellow-50 rounded-lg p-4 border border-yellow-200">
            <p class="text-sm sm:text-base text-slate-700 whitespace-pre-wrap">
                <?php echo nl2br(htmlspecialchars($contactForm['notes'])); ?>
            </p>
        </div>
    </div>
    <?php endif; ?>

  </div>
</div>
<script>
const contactId = '<?php echo htmlspecialchars($contactForm['contact_id']); ?>';

async function updateStatus(contactId, status) {
    let confirmed = false;
    if (window.NotificationManager && window.NotificationManager.confirm) {
        confirmed = await window.NotificationManager.confirm('Durumu güncellemek istediğinize emin misiniz?', 'Onay');
    } else {
        confirmed = confirm('Durumu güncellemek istediğinize emin misiniz?');
    }
    if (!confirmed) return;
    
    try {
        const response = await fetch('<?php echo BASE_URL; ?>/qodmin/contact-forms/update-status', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                contact_id: contactId,
                status: status
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            if (window.NotificationManager) window.NotificationManager.success('✓ ' + (result.message || 'Durum başarıyla güncellendi'));
            setTimeout(() => {
                location.reload();
            }, 1000);
        } else {
            if (window.NotificationManager) window.NotificationManager.error('✗ ' + (result.message || 'Bir hata oluştu'));
        }
    } catch (error) {
        console.error('Error updating status:', error);
        if (window.NotificationManager) window.NotificationManager.error('✗ Bir hata oluştu');
    }
}

async function sendReply() {
    const replyMessage = document.getElementById('replyMessage').value.trim();
    
    if (!replyMessage) {
        if (window.NotificationManager) window.NotificationManager.warning('Lütfen yanıt metnini girin');
        return;
    }
    
    // Show preview modal with confirmation
    const confirmed = await showReplyPreview(replyMessage);
    
    if (!confirmed) {
        return;
    }
    
    const sendButton = event?.target || document.querySelector('button[onclick*="sendReply"]');
    const originalButtonText = sendButton?.innerHTML || '';
    
    // Show loading state
    if (sendButton) {
        sendButton.disabled = true;
        sendButton.innerHTML = '<span>⏳</span> <span>Gönderiliyor...</span>';
        sendButton.classList.add('opacity-75', 'cursor-not-allowed');
    }
    
    try {
        const response = await fetch('<?php echo BASE_URL; ?>/qodmin/contact-forms/send-reply', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                contact_id: contactId,
                reply_message: replyMessage
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            if (window.NotificationManager) window.NotificationManager.success('✓ Yanıt e-postası başarıyla gönderildi');
            showSentMessagePreview(replyMessage);
            document.getElementById('replyMessage').value = '';
            setTimeout(() => {
                location.reload();
            }, 2000);
        } else {
            if (window.NotificationManager) window.NotificationManager.error('✗ ' + (result.message || 'Bir hata oluştu'));
        }
    } catch (error) {
        console.error('Error sending reply:', error);
        if (window.NotificationManager) window.NotificationManager.error('✗ Bir hata oluştu: ' + error.message);
    } finally {
        if (sendButton) {
            sendButton.disabled = false;
            sendButton.innerHTML = originalButtonText;
            sendButton.classList.remove('opacity-75', 'cursor-not-allowed');
        }
    }
}

function showReplyPreview(message) {
    return new Promise((resolve) => {
        const overlay = document.createElement('div');
        overlay.className = 'fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4';
        
        const modal = document.createElement('div');
        modal.className = 'bg-white rounded-xl sm:rounded-2xl p-4 sm:p-6 max-w-2xl w-full max-h-[90vh] overflow-y-auto shadow-2xl';
        
        modal.innerHTML = `
            <div class="mb-4 sm:mb-6">
                <h3 class="text-lg sm:text-xl font-black text-slate-900 mb-2">Yanıt Önizleme</h3>
                <p class="text-sm text-slate-600">Gönderilecek e-posta içeriği:</p>
            </div>
            <div class="bg-slate-50 rounded-lg p-4 mb-4 sm:mb-6 border border-slate-200">
                <div class="text-sm text-slate-700 whitespace-pre-wrap font-bold">${escapeHtml(message)}</div>
            </div>
            <div class="flex flex-wrap gap-2 sm:gap-3 justify-end">
                <button class="cancel-btn px-4 sm:px-5 py-2 sm:py-2.5 bg-slate-200 text-slate-700 rounded-lg sm:rounded-xl text-xs sm:text-sm font-black hover:bg-slate-300 transition-all">
                    İptal
                </button>
                <button class="confirm-btn px-4 sm:px-5 py-2 sm:py-2.5 bg-indigo-500 text-white rounded-lg sm:rounded-xl text-xs sm:text-sm font-black hover:bg-indigo-700 transition-all shadow-lg">
                    Gönder
                </button>
            </div>
        `;
        
        overlay.appendChild(modal);
        document.body.appendChild(overlay);
        
        const confirmBtn = modal.querySelector('.confirm-btn');
        const cancelBtn = modal.querySelector('.cancel-btn');
        
        confirmBtn.addEventListener('click', () => {
            document.body.removeChild(overlay);
            resolve(true);
        });
        
        cancelBtn.addEventListener('click', () => {
            document.body.removeChild(overlay);
            resolve(false);
        });
        
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) {
                document.body.removeChild(overlay);
                resolve(false);
            }
        });
    });
}

function showSentMessagePreview(message) {
    const preview = document.createElement('div');
    preview.className = 'fixed top-4 right-4 bg-green-50 border-2 border-green-500 rounded-xl sm:rounded-2xl p-4 sm:p-6 max-w-md shadow-2xl z-50 animate-slide-up';
    preview.innerHTML = `
        <div class="flex items-start gap-3">
            <div class="flex-shrink-0">
                <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center">
                    <span class="text-white font-black text-sm">✓</span>
                </div>
            </div>
            <div class="flex-1 min-w-0">
                <h4 class="text-sm sm:text-base font-black text-green-900 mb-2">E-posta Gönderildi</h4>
                <div class="bg-white rounded-lg p-3 mb-3 border border-green-200">
                    <p class="text-xs sm:text-sm text-slate-700 whitespace-pre-wrap font-bold">${escapeHtml(message)}</p>
                </div>
                <p class="text-xs text-green-700">Yukarıdaki mesaj müşteriye gönderildi.</p>
            </div>
            <button class="close-preview flex-shrink-0 text-green-700 hover:text-green-900">
                <span class="text-xl font-black">×</span>
            </button>
        </div>
    `;
    
    document.body.appendChild(preview);
    
    const closeBtn = preview.querySelector('.close-preview');
    closeBtn.addEventListener('click', () => {
        preview.style.opacity = '0';
        preview.style.transition = 'opacity 0.3s';
        setTimeout(() => preview.remove(), 300);
    });
    
    setTimeout(() => {
        preview.style.opacity = '0';
        preview.style.transition = 'opacity 0.3s';
        setTimeout(() => preview.remove(), 300);
    }, 5000);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

async function improveTextWithGemini(event) {
    const textarea = document.getElementById('replyMessage');
    const text = textarea.value.trim();
    
    if (!text) {
        if (window.NotificationManager) window.NotificationManager.warning('Lütfen önce metin girin');
        return;
    }
    
    let confirmed = false;
    if (window.NotificationManager && window.NotificationManager.confirm) {
        confirmed = await window.NotificationManager.confirm('Metni Gemini AI ile düzeltmek istediğinize emin misiniz? Mevcut metin değiştirilecektir.', 'Onay');
    } else {
        confirmed = confirm('Metni Gemini AI ile düzeltmek istediğinize emin misiniz? Mevcut metin değiştirilecektir.');
    }
    if (!confirmed) return;
    
    // Show loading state
    const originalText = textarea.value;
    const improveButton = event?.target || document.querySelector('button[onclick*="improveTextWithGemini"]');
    const originalButtonText = improveButton?.innerHTML || '';
    
    textarea.disabled = true;
    textarea.value = 'Metin düzeltiliyor...';
    if (improveButton) {
        improveButton.disabled = true;
        improveButton.innerHTML = '<span>⏳</span> <span>Düzeltiliyor...</span>';
        improveButton.classList.add('opacity-75', 'cursor-not-allowed');
    }
    
    try {
        const response = await fetch('<?php echo BASE_URL; ?>/qodmin/contact-forms/improve-text', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                text: text
            })
        });
        
        const result = await response.json();
        
        if (result.success && result.improved_text) {
            textarea.value = result.improved_text;
            if (window.NotificationManager) window.NotificationManager.success('✓ Metin başarıyla düzeltildi');
        } else {
            textarea.value = originalText;
            if (window.NotificationManager) window.NotificationManager.error('✗ ' + (result.message || 'Metin düzeltilemedi'));
        }
    } catch (error) {
        console.error('Error improving text:', error);
        textarea.value = originalText;
        if (window.NotificationManager) window.NotificationManager.error('✗ Bir hata oluştu');
    } finally {
        textarea.disabled = false;
        if (improveButton) {
            improveButton.disabled = false;
            improveButton.innerHTML = originalButtonText;
            improveButton.classList.remove('opacity-75', 'cursor-not-allowed');
        }
    }
}
</script>
