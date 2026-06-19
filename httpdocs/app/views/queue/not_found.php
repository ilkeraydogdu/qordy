<?php
$businessName = $business['company_name'] ?? 'Qordy';
?>
<!DOCTYPE html>
<html lang="tr"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Bilet bulunamadı - <?php echo htmlspecialchars($businessName, ENT_QUOTES); ?></title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen flex items-center justify-center p-6">
  <div class="max-w-sm text-center">
    <div class="text-6xl">🫤</div>
    <h1 class="mt-4 text-2xl font-bold">Bilet bulunamadı</h1>
    <p class="mt-2 text-slate-400">Bu sıra numarası artık aktif değil ya da silinmiş olabilir. Lütfen yeniden QR kodu okutarak sıraya katılın.</p>
    <a href="/q" class="mt-6 inline-block bg-orange-500 text-black font-bold px-5 py-3 rounded-xl">Ekrana dön</a>
  </div>
</body></html>
