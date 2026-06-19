<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ödeme Sonucu - <?php echo getAppConfig()->getAppName(); ?></title>
    <?php echo getAssetManager()->getTailwindCssScript(); ?>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex items-center justify-center px-4">
        <div class="max-w-md w-full bg-white rounded-lg shadow-lg p-8 text-center">
            <?php if ($status === 'success'): ?>
                <div class="mb-6">
                    <svg class="mx-auto h-16 w-16 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <h1 class="text-2xl font-bold text-gray-900 mb-4">Ödeme Başarılı</h1>
                <p class="text-gray-600 mb-6"><?php echo htmlspecialchars($message ?? 'Ödemeniz başarıyla tamamlandı.'); ?></p>
            <?php else: ?>
                <div class="mb-6">
                    <svg class="mx-auto h-16 w-16 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <h1 class="text-2xl font-bold text-gray-900 mb-4">Ödeme Başarısız</h1>
                <p class="text-gray-600 mb-6"><?php echo htmlspecialchars($message ?? 'Ödeme işlemi tamamlanamadı. Lütfen tekrar deneyin.'); ?></p>
            <?php endif; ?>
            
            <?php if (!empty($table_id)): ?>
                <div class="mt-6">
                    <a href="<?php echo BASE_URL; ?>/masa/<?php echo htmlspecialchars($table_id); ?>" 
                       class="inline-block bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition">
                        Menüye Dön
                    </a>
                </div>
            <?php endif; ?>
            
            <div class="mt-4">
                <button onclick="window.close()" class="text-gray-500 hover:text-gray-700">
                    Pencereyi Kapat
                </button>
            </div>
        </div>
    </div>
    
    <script>
        // Auto-close after 5 seconds if opened in popup
        if (window.opener) {
            setTimeout(function() {
                window.close();
            }, 5000);
        }
        
        // Notify parent window if in iframe
        if (window.parent !== window) {
            window.parent.postMessage({
                type: 'payment_callback',
                status: '<?php echo $status; ?>',
                table_id: '<?php echo $table_id ?? ''; ?>'
            }, '*');
        }
    </script>
</body>
</html>
