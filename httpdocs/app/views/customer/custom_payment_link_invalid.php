<?php
$message = $message ?? 'Bu ödeme bağlantısı geçersiz veya süresi dolmuş.';
$title = $title ?? 'Bağlantı geçersiz';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title); ?> — QORDY</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 min-h-screen">
<div class="max-w-lg mx-auto py-20 px-4 text-center">
    <div class="bg-white rounded-3xl shadow-xl p-10">
        <div class="mx-auto w-16 h-16 rounded-full bg-red-50 flex items-center justify-center">
            <svg class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </div>
        <h1 class="mt-6 text-2xl font-black text-slate-900"><?php echo htmlspecialchars($title); ?></h1>
        <p class="mt-3 text-slate-500"><?php echo htmlspecialchars($message); ?></p>
        <a href="<?php echo BASE_URL; ?>/" class="inline-block mt-8 px-5 py-2.5 bg-slate-900 text-white rounded-xl font-bold text-sm">
            Ana Sayfaya Dön
        </a>
    </div>
</div>
</body>
</html>
