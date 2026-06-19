<?php
// Blog Management Dashboard
require_once __DIR__ . '/../../helpers/functions.php';

$title = 'Blog Yönetimi - Otomatik İçerik Üretimi';
$page = 'blog_management';
$seoParams = [];
?>

<div class="q-page q-biz-theme animate-slide-up">
  <div class="q-container">
    <div class="mb-6">
        <h1 class="text-2xl sm:text-3xl lg:text-4xl font-black text-slate-900 tracking-tighter mb-2">Blog Yönetimi</h1>
        <p class="text-slate-500 font-bold">AI destekli otomatik blog içerik üretimi ve SEO optimizasyonu</p>
    </div>

    <!-- Statistics -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 sm:gap-6 mb-6">
        <div class="bg-white rounded-xl shadow-soft p-4 sm:p-6">
            <h3 class="text-sm sm:text-base font-semibold text-slate-700 mb-2">Toplam Konu</h3>
            <p class="text-2xl sm:text-3xl font-bold text-blue-600"><?php echo $total_count ?? 0; ?></p>
        </div>
        <div class="bg-white rounded-xl shadow-soft p-4 sm:p-6">
            <h3 class="text-sm sm:text-base font-semibold text-slate-700 mb-2">Yayınlanan</h3>
            <p class="text-2xl sm:text-3xl font-bold text-green-600"><?php echo $published_count ?? 0; ?></p>
        </div>
        <div class="bg-white rounded-xl shadow-soft p-4 sm:p-6">
            <h3 class="text-sm sm:text-base font-semibold text-slate-700 mb-2">Bekleyen</h3>
            <p class="text-2xl sm:text-3xl font-bold text-indigo-600"><?php echo count($unpublished_topics ?? []); ?></p>
        </div>
    </div>

    <!-- Actions -->
    <div class="bg-white rounded-xl shadow-soft p-4 sm:p-6 mb-6">
        <h2 class="text-xl font-bold text-slate-800 mb-4">Hızlı İşlemler</h2>
        <div class="flex flex-wrap gap-3 sm:gap-4">
            <button onclick="generateNextPost()" class="bg-blue-600 text-white px-4 sm:px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors text-sm sm:text-base font-semibold">
                Sonraki Yazıyı Üret
            </button>
            <button onclick="optimizeAll()" class="bg-green-600 text-white px-4 sm:px-6 py-2 rounded-lg hover:bg-green-700 transition-colors text-sm sm:text-base font-semibold">
                Tüm Yazıları Optimize Et
            </button>
            <button onclick="refreshList()" class="bg-slate-600 text-white px-4 sm:px-6 py-2 rounded-lg hover:bg-slate-700 transition-colors text-sm sm:text-base font-semibold">
                Listeyi Yenile
            </button>
        </div>
    </div>

    <!-- Unpublished Topics -->
    <div class="bg-white rounded-xl shadow-soft p-4 sm:p-6">
        <h2 class="text-xl font-bold text-slate-800 mb-4">Yayınlanmamış Konular</h2>
        
        <?php if (empty($unpublished_topics)): ?>
            <div class="text-center py-8">
                <p class="text-slate-600 text-lg mb-4">Tüm konular yayınlandı! 🎉</p>
                <p class="text-slate-500 text-sm">Yeni konular eklemek için BlogContentGeneratorService.php dosyasını düzenleyebilirsiniz.</p>
            </div>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($unpublished_topics as $index => $topic): ?>
                    <div class="border border-slate-200 rounded-lg p-4 sm:p-6 hover:bg-slate-50 transition-colors">
                        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
                            <div class="flex-1">
                                <h3 class="font-semibold text-slate-800 mb-2 text-lg"><?php echo htmlspecialchars($topic['title']); ?></h3>
                                <p class="text-sm text-slate-600 mb-3"><?php echo htmlspecialchars($topic['excerpt']); ?></p>
                                <div class="flex flex-wrap gap-2 mb-2">
                                    <?php foreach ($topic['keywords'] as $keyword): ?>
                                        <span class="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full"><?php echo htmlspecialchars($keyword); ?></span>
                                    <?php endforeach; ?>
                                </div>
                                <p class="text-xs text-slate-500 mt-2">
                                    Öncelik: <span class="font-semibold"><?php echo $topic['priority'] ?? 0; ?></span> | 
                                    Kategori: <span class="font-semibold"><?php echo htmlspecialchars($topic['category_slug']); ?></span>
                                </p>
                            </div>
                            <button onclick="generateTopic(<?php echo $index; ?>)" class="ml-auto sm:ml-4 bg-blue-600 text-white px-4 sm:px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors text-sm sm:text-base font-semibold whitespace-nowrap">
                                Üret
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

  </div>
</div>
<script>
async function generateNextPost() {
    let confirmed = false;
    if (window.NotificationManager && window.NotificationManager.confirm) {
        confirmed = await window.NotificationManager.confirm('Sonraki öncelikli yazıyı üretmek istediğinize emin misiniz?', 'Onay');
    } else {
        confirmed = confirm('Sonraki öncelikli yazıyı üretmek istediğinize emin misiniz?');
    }
    if (!confirmed) return;
    
    const button = event.target;
    const originalText = button.textContent;
    button.disabled = true;
    button.textContent = 'Üretiliyor...';
    
    try {
        const response = await fetch('<?php echo BASE_URL; ?>/admin/blog-management/generate-post', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': window.CSRF_TOKEN || ''
            },
            body: JSON.stringify({})
        });
        const data = await response.json();
        button.disabled = false;
        button.textContent = originalText;
        
        if (data.success) {
            if (window.NotificationManager) window.NotificationManager.success('Yazı başarıyla oluşturuldu! Başlık: ' + data.title);
            location.reload();
        } else {
            if (window.NotificationManager) window.NotificationManager.error('Hata: ' + (data.error || 'Bilinmeyen hata'));
        }
    } catch (error) {
        console.error('Error:', error);
        button.disabled = false;
        button.textContent = originalText;
        if (window.NotificationManager) window.NotificationManager.error('Bir hata oluştu: ' + error.message);
    }
}

async function generateTopic(topicIndex) {
    let confirmed = false;
    if (window.NotificationManager && window.NotificationManager.confirm) {
        confirmed = await window.NotificationManager.confirm('Bu yazıyı üretmek istediğinize emin misiniz?', 'Onay');
    } else {
        confirmed = confirm('Bu yazıyı üretmek istediğinize emin misiniz?');
    }
    if (!confirmed) return;
    
    const button = event.target;
    const originalText = button.textContent;
    button.disabled = true;
    button.textContent = 'Üretiliyor...';
    
    try {
        const response = await fetch('<?php echo BASE_URL; ?>/admin/blog-management/generate-post', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': window.CSRF_TOKEN || ''
            },
            body: JSON.stringify({ topic_id: topicIndex })
        });
        const data = await response.json();
        button.disabled = false;
        button.textContent = originalText;
        
        if (data.success) {
            if (window.NotificationManager) window.NotificationManager.success('Yazı başarıyla oluşturuldu! Başlık: ' + data.title);
            location.reload();
        } else {
            if (window.NotificationManager) window.NotificationManager.error('Hata: ' + (data.error || 'Bilinmeyen hata'));
        }
    } catch (error) {
        console.error('Error:', error);
        button.disabled = false;
        button.textContent = originalText;
        if (window.NotificationManager) window.NotificationManager.error('Bir hata oluştu: ' + error.message);
    }
}

async function optimizeAll() {
    let confirmed = false;
    if (window.NotificationManager && window.NotificationManager.confirm) {
        confirmed = await window.NotificationManager.confirm('Tüm blog yazılarını SEO açısından optimize etmek istediğinize emin misiniz? Bu işlem biraz zaman alabilir.', 'Onay');
    } else {
        confirmed = confirm('Tüm blog yazılarını SEO açısından optimize etmek istediğinize emin misiniz? Bu işlem biraz zaman alabilir.');
    }
    if (!confirmed) return;
    
    const button = event.target;
    const originalText = button.textContent;
    button.disabled = true;
    button.textContent = 'Optimize ediliyor...';
    
    try {
        const response = await fetch('<?php echo BASE_URL; ?>/admin/blog-management/optimize-all', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': window.CSRF_TOKEN || ''
            },
            body: JSON.stringify({})
        });
        const data = await response.json();
        button.disabled = false;
        button.textContent = originalText;
        
        if (data.success) {
            if (window.NotificationManager) window.NotificationManager.success('Optimizasyon tamamlandı! Optimize edilen: ' + data.optimized + ', Toplam: ' + data.total);
            location.reload();
        } else {
            if (window.NotificationManager) window.NotificationManager.error('Hata: ' + (data.error || 'Bilinmeyen hata'));
        }
    } catch (error) {
        console.error('Error:', error);
        button.disabled = false;
        button.textContent = originalText;
        if (window.NotificationManager) window.NotificationManager.error('Bir hata oluştu: ' + error.message);
    }
}

function refreshList() {
    location.reload();
}
</script>
