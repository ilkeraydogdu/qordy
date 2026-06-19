<?php
/**
 * Trial Grace Reminder — kurumsal ama sıcak bir hatırlatma.
 *
 * Vars (template bazlı): $fullName, $firstName, $companyName
 *   $graceDay (1..7), $graceDaysLeft (6..0), $ctaUrl, $pricingUrl, $baseUrl
 * Vars (AbstractEmailType'tan merkezi): $supportEmail, $supportPhone,
 *   $supportPhoneE164, $siteName
 */
$first = htmlspecialchars($firstName ?: $fullName, ENT_QUOTES, 'UTF-8');
$left  = (int)$graceDaysLeft;
$day   = (int)$graceDay;
$supportEmail      = $supportEmail      ?? 'destek@qordy.com';
$supportPhone      = $supportPhone      ?? '0850 309 32 53';
$supportPhoneE164  = $supportPhoneE164  ?? '+908503093253';

// Güne göre üst başlık + ton
if ($left <= 0) {
    $headline = 'Bugün son gün.';
    $subline  = 'Hesabınız yarın otomatik olarak askıya alınacak — tüm verileriniz bizde güvende, ancak sisteme giriş yapamayacaksınız.';
    $accent   = '#dc2626';
} elseif ($left === 1) {
    $headline = '1 gün kaldı.';
    $subline  = 'Sistemi kesintisiz kullanmaya devam etmek için bugün planınızı seçin.';
    $accent   = '#dc2626';
} elseif ($left <= 3) {
    $headline = "Son {$left} gün.";
    $subline  = 'Deneme süreniz bitti, verileriniz güvende tutuluyor. Kesintisiz devam için planınızı seçin.';
    $accent   = '#f59e0b';
} else {
    $headline = "7 günlük bekleme sürecindeyiz.";
    $subline  = 'Deneme süreniz bitti — hesabınız şu an salt-okunur modda. Bir plan seçtiğinizde tüm özellikler ilk gündeki gibi açılacak.';
    $accent   = '#0ea5e9';
}

$ctaUrlSafe = htmlspecialchars($ctaUrl, ENT_QUOTES, 'UTF-8');
$pricingUrlSafe = htmlspecialchars($pricingUrl, ENT_QUOTES, 'UTF-8');
?>

<h2 style="color:#0f172a; margin:0 0 8px 0; font-size:22px;">Merhaba <?= $first ?>,</h2>
<?php if (!empty($companyName)): ?>
<p style="color:#64748b; margin:0 0 20px 0; font-size:14px;">
    <?= htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8') ?> için son bir hatırlatma.
</p>
<?php endif; ?>

<div style="background:linear-gradient(135deg,#eef2ff,#f8fafc); border:1px solid #e2e8f0; border-left:5px solid <?= $accent ?>; padding:22px 24px; border-radius:12px; margin:18px 0 24px 0;">
    <div style="font-size:13px; letter-spacing:.08em; text-transform:uppercase; color:<?= $accent ?>; font-weight:700; margin-bottom:8px;">
        Deneme Süreniz Bitti
    </div>
    <div style="font-size:22px; font-weight:800; color:#0f172a; line-height:1.2; margin-bottom:6px;">
        <?= htmlspecialchars($headline, ENT_QUOTES, 'UTF-8') ?>
    </div>
    <div style="font-size:15px; color:#475569; line-height:1.55;">
        <?= htmlspecialchars($subline, ENT_QUOTES, 'UTF-8') ?>
    </div>
</div>

<p style="color:#334155; font-size:15px; line-height:1.65; margin:0 0 16px 0;">
    Qordy ile kurduğunuz menüler, QR kodlar, personel ayarları ve raporların tamamı hesabınızda duruyor. 7 günlük bekleme süresinde sistemi <strong>salt-okunur</strong> modda görebilir, ancak sipariş alamaz, QR kod paylaşamazsınız.
</p>

<p style="color:#334155; font-size:15px; line-height:1.65; margin:0 0 20px 0;">
    Sizin için hazırladığımız kişisel bağlantıyla tek tıkla plan seçimine geçebilirsiniz — işletmeniz için en uygun paketi 2 dakikadan kısa sürede aktifleştirebilirsiniz.
</p>

<div style="text-align:center; margin:28px 0 12px 0;">
    <a href="<?= $ctaUrlSafe ?>" style="display:inline-block; background:linear-gradient(135deg,#6366f1,#8b5cf6); color:#ffffff; padding:16px 36px; text-decoration:none; border-radius:12px; font-weight:700; font-size:16px; box-shadow:0 6px 20px rgba(99,102,241,.25);">
        Planımı Seç ve Devam Et →
    </a>
</div>

<p style="text-align:center; color:#94a3b8; font-size:12px; margin:0 0 28px 0;">
    <a href="<?= $pricingUrlSafe ?>" style="color:#6366f1; text-decoration:none;">veya tüm planları karşılaştırın</a>
</p>

<div style="border-top:1px solid #e2e8f0; padding-top:18px; margin-top:24px;">
    <div style="font-size:13px; color:#64748b; line-height:1.6;">
        <strong style="color:#334155;">Seçimi zorlaştırmayalım:</strong>
        Hangi paketin size uygun olduğundan emin değilseniz <a href="mailto:<?= htmlspecialchars($supportEmail, ENT_QUOTES, 'UTF-8') ?>" style="color:#6366f1;"><?= htmlspecialchars($supportEmail, ENT_QUOTES, 'UTF-8') ?></a> veya <a href="tel:<?= htmlspecialchars($supportPhoneE164, ENT_QUOTES, 'UTF-8') ?>" style="color:#6366f1;"><?= htmlspecialchars($supportPhone, ENT_QUOTES, 'UTF-8') ?></a> numarasından tek bir mesaj atın; 15 dakika içinde sizin için işletmenize en uygun yıllık/aylık seçeneği hazırlayalım.
    </div>
</div>

<?php if ($left > 0 && $left < 7): ?>
<div style="margin-top:22px; padding:14px 16px; background:#fff7ed; border:1px solid #fed7aa; border-radius:10px; font-size:13px; color:#9a3412;">
    <strong>Bilgi:</strong> Bekleme süresinin sonunda hesabınız otomatik olarak askıya alınır. Daha sonra giriş yapmaya çalıştığınızda doğrudan paket seçim ekranına yönlendirilirsiniz — panel içinde çalışmaya devam etmek yalnızca aktif bir planla mümkündür.
</div>
<?php endif; ?>
