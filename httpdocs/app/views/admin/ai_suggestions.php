<?php
/**
 * Kaydedilmiş AI / kural tabanlı öneriler
 */
if (!function_exists('getAdminUrl')) {
    require_once __DIR__ . '/../../helpers/url_helper.php';
}
$apiPrefix = $api_prefix ?? '/api/business';
$baseUrl = BASE_URL;
?>

<div class="q-page q-biz-theme animate-slide-up">
  <div class="q-container q-stack q-stack--lg">
    <header class="q-page-header">
      <div>
        <p class="q-page-header__eyebrow">Raporlar</p>
        <h1 class="q-page-header__title">AI Önerileri</h1>
        <p class="q-page-header__subtitle">Beğendiğiniz ve kaydettiğiniz veri tabanlı öneriler</p>
      </div>
      <div class="q-page-header__actions">
        <a href="<?php echo htmlspecialchars(getAdminUrl('dashboard'), ENT_QUOTES, 'UTF-8'); ?>" class="q-btn q-btn--ghost q-btn--sm">Panele dön</a>
      </div>
    </header>

    <div class="q-panel-card">
      <div class="q-panel-card__head">
        <span class="q-panel-card__title">Kaydedilen öneriler</span>
        <span class="q-panel-card__meta q-panel-badge--muted">Kişisel liste</span>
      </div>
      <div id="ai-saved-root" class="q-ai-saved-list" data-api-prefix="<?php echo htmlspecialchars($apiPrefix, ENT_QUOTES, 'UTF-8'); ?>">
        <div class="q-panel-empty">
          <div class="q-spinner q-spinner--sm" role="status" aria-label="Yükleniyor"></div>
          <p>Liste yükleniyor…</p>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  'use strict';
  var root = document.getElementById('ai-saved-root');
  if (!root) return;
  var apiPrefix = root.getAttribute('data-api-prefix') || '/api/business';
  var baseUrl = <?php echo json_encode($baseUrl); ?>;

  function csrf() {
    var m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.getAttribute('content') : '';
  }

  function esc(s) {
    var d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
  }

  function rowHtml(item) {
    return ''
      + '<article class="q-ai-feed__row q-panel-insight--info" data-saved-id="' + esc(item.insight_id) + '">'
      + '<div class="q-ai-feed__row-head">'
      + '<span class="q-ai-feed__badge">' + esc(item.category_label || item.category_key || '') + '</span>'
      + '<span class="q-ai-feed__source">' + esc(item.source === 'auto' ? 'Otomatik' : 'Kural') + '</span>'
      + '<button type="button" class="q-ai-feed__save is-saved" data-unsave="' + esc(item.insight_id) + '" aria-label="Kaydı kaldır">'
      + '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2"><path d="M19 21l-7-5-7 5V5a2 2 0 012-2h10a2 2 0 012 2z"/></svg>'
      + '</button></div>'
      + (item.metric ? '<div class="q-ai-feed__metric">' + esc(item.metric) + '</div>' : '')
      + '<h4 class="q-ai-feed__title">' + esc(item.title) + '</h4>'
      + (item.body_text ? '<p class="q-ai-feed__text">' + esc(item.body_text) + '</p>' : '')
      + (item.action_hint ? '<p class="q-ai-feed__action">' + esc(item.action_hint) + '</p>' : '')
      + '<p class="q-hint" style="margin:0.375rem 0 0;">Kaydedildi: ' + esc(item.saved_at || '') + '</p>'
      + '</article>';
  }

  function render(items) {
    if (!items || !items.length) {
      root.innerHTML = '<div class="q-panel-empty"><p>Henüz kaydedilmiş öneri yok. Pano &gt; AI Danışman kartından beğenebilirsiniz.</p></div>';
      return;
    }
    root.innerHTML = items.map(rowHtml).join('');
    root.querySelectorAll('[data-unsave]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var id = btn.getAttribute('data-unsave');
        fetch(baseUrl + apiPrefix + '/ai-insights/unsave', {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf(), 'Accept': 'application/json' },
          body: JSON.stringify({ insight_id: id })
        }).then(function (r) { return r.json(); }).then(function (data) {
          if (data && data.success) load();
        });
      });
    });
  }

  function load() {
    fetch(baseUrl + apiPrefix + '/ai-insights/saved', {
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'X-CSRF-Token': csrf() }
    })
      .then(function (r) { return r.json(); })
      .then(function (data) { render((data && data.items) || []); })
      .catch(function () {
        root.innerHTML = '<div class="q-panel-empty"><p>Liste yüklenemedi.</p></div>';
      });
  }

  load();
})();
</script>
