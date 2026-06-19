<?php
/**
 * Admin Profile Page — Warm Ember Ops design system (.q-* components)
 * Müşteriler için profil düzenleme sayfası
 */

require_once __DIR__ . '/../../helpers/translations.php';

$user = $user ?? [];
$customer = $customer ?? null;

$userEmail = $user['name'] ?? '';
$firstName = $customer['first_name'] ?? '';
$lastName  = $customer['last_name'] ?? '';
$phone     = $customer['phone'] ?? '';

$initials = strtoupper(mb_substr(trim($firstName) ?: ($userEmail ?: 'Q'), 0, 1, 'UTF-8'));
?>

<div class="q-page q-biz-theme animate-slide-up">
  <div class="q-container" style="--max-w: 760px;">

    <header class="q-page-header">
      <div>
        <p class="q-page-header__eyebrow">Hesap</p>
        <h1 class="q-page-header__title">Profil Düzenle</h1>
        <p class="q-page-header__subtitle">Profil bilgilerinizi buradan güncelleyebilirsiniz.</p>
      </div>
    </header>

    <div class="q-card q-card--pad q-stack">
      <div class="q-card__header" style="padding-left:0;padding-right:0;padding-top:0;">
        <div style="display:flex;align-items:center;gap:var(--space-3);">
          <div aria-hidden="true" style="width:48px;height:48px;border-radius:var(--radius-lg);background:var(--color-amber-soft);color:var(--color-brand-accent-hover);display:flex;align-items:center;justify-content:center;font-family:var(--font-display);font-weight:900;font-size:1.25rem;">
            <?php echo htmlspecialchars($initials); ?>
          </div>
          <div>
            <p class="q-card__title" style="margin:0;"><?php echo htmlspecialchars(trim($firstName . ' ' . $lastName)) ?: 'Profilim'; ?></p>
            <p class="q-hint" style="margin-top:2px;"><?php echo htmlspecialchars($userEmail); ?></p>
          </div>
        </div>
      </div>

      <form id="profile-form" method="POST" action="<?php echo BASE_URL; ?>/qodmin/profile/update" novalidate>
        <?php echo csrf_field(); ?>

        <div class="q-field">
          <label class="q-label" for="email">E-posta</label>
          <input type="email" id="email" value="<?php echo htmlspecialchars($userEmail); ?>" disabled class="q-input">
          <p class="q-hint">E-posta adresi değiştirilemez.</p>
        </div>

        <div class="q-grid q-grid--2">
          <div class="q-field">
            <label class="q-label" for="first_name">Ad</label>
            <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($firstName); ?>" class="q-input" autocomplete="given-name">
          </div>
          <div class="q-field">
            <label class="q-label" for="last_name">Soyad</label>
            <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($lastName); ?>" class="q-input" autocomplete="family-name">
          </div>
        </div>

        <div class="q-field">
          <label class="q-label" for="phone">Telefon</label>
          <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($phone); ?>" class="q-input" autocomplete="tel">
        </div>

        <div class="q-field">
          <label class="q-label" for="password">Yeni Şifre</label>
          <input type="password" id="password" name="password" placeholder="Değiştirmek istemiyorsanız boş bırakın" class="q-input" autocomplete="new-password" minlength="6">
          <p class="q-hint" id="password-hint">Şifre en az 6 karakter olmalıdır.</p>
        </div>

        <div style="display:flex;gap:var(--space-3);margin-top:var(--space-5);">
          <button type="submit" class="q-btn q-btn--primary q-btn--lg" style="flex:1;">Kaydet</button>
          <a href="<?php echo BASE_URL; ?>/qodmin/dashboard" class="q-btn q-btn--ghost q-btn--lg" style="flex:1;">İptal</a>
        </div>
      </form>
    </div>

  </div>
</div>

<script>
// Client-side validation (server-side validation still authoritative)
document.getElementById('profile-form')?.addEventListener('submit', function (e) {
    var pwd = document.getElementById('password');
    var hint = document.getElementById('password-hint');
    var password = pwd ? pwd.value : '';
    if (password && password.length < 6) {
        e.preventDefault();
        if (pwd) pwd.classList.add('q-input--error');
        if (hint) hint.classList.add('q-hint--error');
        if (window.NotificationManager) window.NotificationManager.warning('Şifre en az 6 karakter olmalıdır.');
        else alert('Şifre en az 6 karakter olmalıdır.');
        return false;
    }
});
</script>
