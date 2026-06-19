<?php
$user = $user ?? null;
$baseUrl = BASE_URL;

if (!$user || empty($user['user_id'])) {
    header('Location: ' . BASE_URL . '/qodmin/users');
    exit;
}
?>

<div class="q-page q-biz-theme animate-slide-up">
  <div class="q-container" style="--max-w: 640px;">

    <header class="q-page-header">
      <div>
        <p class="q-page-header__eyebrow">Personel</p>
        <h1 class="q-page-header__title">Personel Düzenle</h1>
        <p class="q-page-header__subtitle"><?php echo htmlspecialchars($user['name'] ?? ''); ?></p>
      </div>
    </header>

    <section class="q-card q-card--pad q-stack">
      <form method="POST" action="<?php echo $baseUrl; ?>/qodmin/users/edit?id=<?php echo htmlspecialchars($user['user_id']); ?>">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['user_id']); ?>">

        <div class="q-field">
          <label class="q-label" for="edit-name">İsim</label>
          <input type="text" id="edit-name" name="name" value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" required class="q-input"/>
        </div>

        <div class="q-field">
          <label class="q-label" for="edit-pin">Yeni PIN</label>
          <input type="password" id="edit-pin" name="pin" maxlength="10" pattern="[0-9]{4,10}" class="q-input" style="font-family:monospace;letter-spacing:0.1em;" placeholder="Boş bırakırsanız değişmez"/>
          <p class="q-hint">PIN sadece rakamlardan oluşmalıdır (4-10 haneli).</p>
        </div>

        <div class="q-field">
          <label class="q-label" for="edit-role">Görev</label>
          <select id="edit-role" name="role" required class="q-select">
            <?php
            $allRoles = getAllRoles();
            $currentRole = $user['role'] ?? '';
            $currentLang = getCurrentLanguage();
            foreach ($allRoles as $role) {
                $roleCode = $role['constant_key'] ?? $role['role_code'] ?? '';
                $roleLabel = getRoleLabel($roleCode, $currentLang);
                $selected = ($currentRole === $roleCode) ? ' selected' : '';
                echo '<option value="' . htmlspecialchars($roleCode) . '"' . $selected . '>' . htmlspecialchars($roleLabel) . '</option>';
            }
            ?>
          </select>
        </div>

        <div style="display:flex;gap:var(--space-3);margin-top:var(--space-4);">
          <a href="<?php echo $baseUrl; ?>/qodmin/users" class="q-btn q-btn--ghost" style="flex:1;">İptal</a>
          <button type="submit" class="q-btn q-btn--primary" style="flex:1;">Kaydet</button>
        </div>
      </form>
    </section>

  </div>
</div>
