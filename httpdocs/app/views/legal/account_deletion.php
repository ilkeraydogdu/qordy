<?php
if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../../config/config.php';
}
if (!function_exists('getAppConfig')) {
    require_once __DIR__ . '/../../helpers/functions.php';
}

$appName = 'Qordy';
try { $appName = htmlspecialchars(getAppConfig()->getAppName()); } catch (\Exception $e) {}
$baseUrl = defined('BASE_URL') ? BASE_URL : '';
$csrfToken = $csrf_token ?? '';
$sent = !empty($sent);
$error = (string)($error ?? '');

$errorMessages = [
    'csrf'    => 'Güvenlik doğrulaması başarısız. Lütfen sayfayı yenileyip tekrar deneyin.',
    'rate'    => 'Çok sık talep gönderdiniz. Lütfen birkaç dakika sonra tekrar deneyin.',
    'email'   => 'Geçerli bir e-posta adresi girmelisiniz.',
    'confirm' => 'Lütfen silme işlemini onayladığınıza dair kutucuğu işaretleyin.',
    'length'  => 'Girdiğiniz metin çok uzun. Lütfen kısaltın.',
    'send'    => 'Talebiniz kaydedildi ama e-posta gönderimi başarısız oldu. destek@qordy.com adresine doğrudan ulaşın.',
];
$errText = $errorMessages[$error] ?? '';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="index,follow">
    <title>Hesap Silme Talebi - <?php echo $appName; ?></title>
    <meta name="description" content="Qordy (com.pofuduk.qordy) mobil uygulamasına veya web sitesine ait hesabınızı ve kişisel verilerinizi silmek için talep oluşturun.">
    <link rel="icon" type="image/png" href="<?php echo $baseUrl; ?>/assets/images/favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body { font-family: 'Inter', system-ui, -apple-system, Segoe UI, sans-serif; background: #f8fafc; color: #0f172a; margin: 0; }
        .nav { display: flex; align-items: center; justify-content: space-between; max-width: 960px; margin: 0 auto; padding: 1rem 1.5rem; }
        .nav a { color: #64748b; text-decoration: none; font-size: 0.875rem; font-weight: 600; }
        .nav a:hover { color: #6366f1; }
        .nav img { height: 28px; }

        .hero { background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #fff; padding: 3rem 0 4rem; position: relative; overflow: hidden; }
        .hero::before { content: ""; position: absolute; top: -40%; right: -10%; width: 520px; height: 520px; border-radius: 50%; background: radial-gradient(circle, rgba(99,102,241,0.35), transparent 60%); pointer-events: none; }
        .hero::after  { content: ""; position: absolute; bottom: -60%; left: -10%; width: 520px; height: 520px; border-radius: 50%; background: radial-gradient(circle, rgba(244,63,94,0.18), transparent 60%); pointer-events: none; }
        .hero .container { max-width: 960px; margin: 0 auto; padding: 0 1.5rem; position: relative; z-index: 1; }
        .hero .eyebrow { display: inline-flex; align-items: center; gap: 0.5rem; font-size: 0.75rem; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; color: #c7d2fe; padding: 0.4rem 0.8rem; border: 1px solid rgba(199,210,254,0.4); border-radius: 999px; }
        .hero h1 { font-size: 2.25rem; font-weight: 800; letter-spacing: -0.03em; margin: 1rem 0 0.75rem; line-height: 1.15; }
        .hero p { color: #cbd5e1; max-width: 680px; line-height: 1.7; margin: 0; }

        .wrap { max-width: 960px; margin: -2rem auto 0; padding: 0 1.5rem 4rem; position: relative; z-index: 2; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
        @media (max-width: 820px) { .grid { grid-template-columns: 1fr; } }

        .card { background: #fff; border-radius: 20px; padding: 2rem; border: 1px solid #e2e8f0; box-shadow: 0 10px 30px -15px rgba(15,23,42,0.18); }
        .card h2 { font-size: 1.15rem; font-weight: 700; margin: 0 0 0.75rem; color: #0f172a; display: flex; align-items: center; gap: 0.6rem; }
        .card h2 .icon { width: 32px; height: 32px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; font-size: 16px; }
        .icon.info { background: #eef2ff; color: #4f46e5; }
        .icon.data { background: #ecfdf5; color: #059669; }
        .icon.timing { background: #fef3c7; color: #b45309; }
        .card p, .card li { color: #475569; line-height: 1.75; font-size: 0.95rem; }
        .card ul { padding-left: 1.15rem; margin: 0.5rem 0 0; }
        .card li { margin-bottom: 0.35rem; }

        .form-card { grid-column: 1 / -1; }
        .form-card h2 { font-size: 1.25rem; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 1rem; }
        @media (max-width: 640px) { .form-grid { grid-template-columns: 1fr; } }
        label { display: block; font-size: 0.8rem; font-weight: 600; color: #334155; margin-bottom: 0.35rem; letter-spacing: 0.01em; }
        label .req { color: #ef4444; margin-left: 0.2rem; }
        input[type="email"], input[type="text"], textarea {
            width: 100%; padding: 0.75rem 0.9rem; border-radius: 12px; border: 1px solid #e2e8f0;
            font-family: inherit; font-size: 0.95rem; color: #0f172a; background: #fff;
            transition: border-color .15s, box-shadow .15s;
        }
        input:focus, textarea:focus { outline: none; border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.15); }
        textarea { min-height: 120px; resize: vertical; }

        .checkbox-row { display: flex; align-items: flex-start; gap: 0.6rem; margin-top: 1rem; padding: 1rem; background: #fef2f2; border: 1px solid #fecaca; border-radius: 12px; }
        .checkbox-row input[type="checkbox"] { margin-top: 0.25rem; width: 18px; height: 18px; accent-color: #ef4444; }
        .checkbox-row label { margin: 0; color: #991b1b; font-weight: 500; font-size: 0.875rem; line-height: 1.55; }

        .actions { display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap; margin-top: 1.25rem; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.9rem 1.4rem; border-radius: 12px; font-weight: 700; text-decoration: none; cursor: pointer; border: none; font-size: 0.95rem; font-family: inherit; transition: transform .08s, box-shadow .15s; }
        .btn:active { transform: translateY(1px); }
        .btn-primary { background: linear-gradient(135deg, #ef4444, #b91c1c); color: #fff; box-shadow: 0 8px 20px -8px rgba(239,68,68,.6); }
        .btn-primary:hover { box-shadow: 0 12px 26px -8px rgba(239,68,68,.7); }
        .btn-ghost { background: transparent; color: #475569; }
        .btn-ghost:hover { color: #0f172a; }

        .alert { border-radius: 12px; padding: 1rem 1.2rem; margin-bottom: 1.25rem; font-size: 0.95rem; line-height: 1.55; }
        .alert-success { background: #ecfdf5; color: #065f46; border: 1px solid #6ee7b7; }
        .alert-error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }

        .contact-inline { display: inline-flex; align-items: center; gap: 0.4rem; color: #4f46e5; font-weight: 600; text-decoration: none; }
        .contact-inline:hover { text-decoration: underline; }

        .pkg-badge { display: inline-flex; align-items: center; gap: 0.4rem; font-family: 'JetBrains Mono', ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 0.75rem; background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.15); color: #e2e8f0; padding: 0.3rem 0.65rem; border-radius: 8px; margin-top: 0.75rem; }

        .footer { border-top: 1px solid #e2e8f0; padding: 1.5rem; margin-top: 3rem; }
        .footer-inner { max-width: 960px; margin: 0 auto; display: flex; justify-content: space-between; flex-wrap: wrap; gap: 1rem; color: #64748b; font-size: 0.85rem; }
        .footer a { color: #64748b; text-decoration: none; margin-left: 1rem; }
        .footer a:hover { color: #6366f1; }
    </style>
</head>
<body>
    <nav class="nav">
        <a href="<?php echo $baseUrl; ?>/">
            <img src="<?php echo $baseUrl; ?>/assets/images/logo.png" alt="<?php echo $appName; ?>">
        </a>
        <a href="<?php echo $baseUrl; ?>/">← Ana Sayfa</a>
    </nav>

    <header class="hero">
        <div class="container">
            <span class="eyebrow">Hesap Silme</span>
            <h1>Hesap ve Veri Silme Talebi</h1>
            <p>
                Qordy mobil uygulaması ve web hesabınız için sildirmek istediğiniz tüm kişisel verileri
                bu sayfadan talep edebilirsiniz. Talebiniz destek ekibimize iletilir ve en geç
                <strong style="color:#fff;">7 iş günü</strong> içinde sonuçlandırılır.
            </p>
            <span class="pkg-badge">Google Play paketi: com.pofuduk.qordy</span>
        </div>
    </header>

    <main class="wrap">
        <?php if ($sent): ?>
            <div class="alert alert-success">
                <strong>Talebiniz alındı.</strong> Destek ekibimiz e-posta adresinize dönüş yapacak
                ve verilerinizi sistemlerimizden silecektir. Kopya bir onay e-postası
                <strong>destek@qordy.com</strong> tarafından size gönderildi.
            </div>
        <?php elseif ($errText !== ''): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($errText); ?></div>
        <?php endif; ?>

        <div class="grid">
            <div class="card">
                <h2><span class="icon data">●</span> Silinen veriler</h2>
                <p>Talebiniz onaylandıktan sonra aşağıdaki veriler sistemlerimizden kalıcı olarak silinir:</p>
                <ul>
                    <li>Hesap bilgileri (ad, e-posta, telefon, şifre özetleri)</li>
                    <li>İşletme profili ve personel kayıtları</li>
                    <li>Cihaz / oturum bilgileri, PIN &amp; desen kilidi özetleri</li>
                    <li>Push bildirim token'ları, analitik cihaz tanımlayıcıları</li>
                    <li>Menü, stok, rezervasyon ve sipariş geçmişi (işletme hesabı silinirse)</li>
                </ul>
            </div>

            <div class="card">
                <h2><span class="icon timing">●</span> Saklanan veriler</h2>
                <p>Yasal yükümlülükler gereği aşağıdaki veriler sınırlı süre boyunca <em>anonim</em> olarak saklanır:</p>
                <ul>
                    <li>e-Arşiv / e-Fatura kayıtları — <strong>10 yıl</strong> (VUK)</li>
                    <li>Ödeme ve abonelik makbuzları — <strong>10 yıl</strong> (VUK)</li>
                    <li>Güvenlik / kötüye kullanım logları — <strong>6 ay</strong></li>
                </ul>
                <p style="margin-top:0.75rem;">Bu kayıtlardaki kişisel tanımlayıcılar pseudonymize edilir ve hesabınızla ilişkilendirilemez.</p>
            </div>

            <div class="card">
                <h2><span class="icon info">●</span> Alternatifler</h2>
                <p>Hesabınızı tamamen silmek yerine:</p>
                <ul>
                    <li>Bildirimlerinizi uygulama ayarlarından kapatabilirsiniz</li>
                    <li>Biyometri / desen kilidini devre dışı bırakıp PIN'inizi sıfırlayabilirsiniz</li>
                    <li>Aboneliğinizi Google Play veya web panelinden dondurabilirsiniz</li>
                </ul>
                <p style="margin-top:0.75rem;">
                    Yardıma ihtiyacınız varsa
                    <a class="contact-inline" href="mailto:destek@qordy.com">destek@qordy.com</a>
                    ile iletişime geçin.
                </p>
            </div>

            <div class="card form-card">
                <h2><span class="icon info">●</span> Talep Formu</h2>
                <p>Formu gönderdiğinizde talebiniz destek ekibimize iletilir. E-posta adresinize ayrıca onay mesajı gönderilir.</p>

                <form method="POST" action="<?php echo $baseUrl; ?>/hesap-sil" autocomplete="off" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES); ?>">

                    <div class="form-grid">
                        <div>
                            <label for="email">Kayıtlı e-posta adresiniz <span class="req">*</span></label>
                            <input id="email" type="email" name="email" required maxlength="200" placeholder="ornek@isletme.com" value="<?php echo htmlspecialchars((string)($_POST['email'] ?? ''), ENT_QUOTES); ?>">
                        </div>
                        <div>
                            <label for="business_name">İşletme adı (varsa)</label>
                            <input id="business_name" type="text" name="business_name" maxlength="200" placeholder="Pofuduk Cafe" value="<?php echo htmlspecialchars((string)($_POST['business_name'] ?? ''), ENT_QUOTES); ?>">
                        </div>
                    </div>

                    <div style="margin-top:1rem;">
                        <label for="reason">Silme gerekçesi (opsiyonel)</label>
                        <textarea id="reason" name="reason" maxlength="2000" placeholder="Hizmeti kullanmayı bıraktım / Başka bir platforma geçtim / Verilerimin tamamen silinmesini istiyorum ..."><?php echo htmlspecialchars((string)($_POST['reason'] ?? ''), ENT_QUOTES); ?></textarea>
                    </div>

                    <div class="checkbox-row">
                        <input id="confirm" type="checkbox" name="confirm" value="yes" required>
                        <label for="confirm">
                            Hesabımın ve kişisel verilerimin silineceğini, bu işlemin geri alınamayacağını ve
                            yasal saklama zorunluluğu bulunan mali/güvenlik kayıtlarının anonim olarak
                            saklanmaya devam edeceğini okudum ve onaylıyorum.
                        </label>
                    </div>

                    <div class="actions">
                        <button type="submit" class="btn btn-primary">Hesabımı Silme Talebi Gönder</button>
                        <a href="mailto:destek@qordy.com?subject=Qordy%20hesap%20silme%20talebi" class="btn btn-ghost">
                            veya e-posta ile gönder →
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <footer class="footer">
        <div class="footer-inner">
            <span>&copy; <?php echo date('Y'); ?> <?php echo $appName; ?>. Tüm hakları saklıdır.</span>
            <div>
                <a href="<?php echo $baseUrl; ?>/">Ana Sayfa</a>
                <a href="mailto:destek@qordy.com">destek@qordy.com</a>
            </div>
        </div>
    </footer>
</body>
</html>
