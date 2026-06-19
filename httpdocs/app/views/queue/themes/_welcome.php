<?php
/**
 * Welcome / marketing layout — rendered when is_accepting_queue = 0.
 *
 * Çok amaçlı alan: metin + sosyal + (isteğe bağlı) YouTube tanıtım / reklam alanı.
 */

$sl = $socialLinks ?? [];

$socialUrl = static function (string $platform, string $value): string {
    $v = trim($value);
    if ($v === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $v)) {
        return $v;
    }
    $handle = ltrim($v, '@');
    switch ($platform) {
        case 'instagram':
            return 'https://instagram.com/' . rawurlencode($handle);
        case 'facebook':
            return 'https://facebook.com/' . rawurlencode($handle);
        case 'tiktok':
            return 'https://tiktok.com/@' . rawurlencode($handle);
        case 'whatsapp':
            $digits = preg_replace('/\D+/', '', $v);
            return $digits ? 'https://wa.me/' . $digits : '';
        case 'phone':
            $digits = preg_replace('/[^\d+]/', '', $v);
            return $digits ? 'tel:' . $digits : '';
        case 'menu':
        case 'website':
            return 'https://' . ltrim($v, '/');
        case 'address':
            return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($v);
    }
    return $v;
};

$socialItems = [];
foreach (['instagram', 'facebook', 'tiktok', 'whatsapp', 'website', 'menu', 'phone', 'address'] as $platform) {
    $val = $sl[$platform] ?? '';
    $url = $socialUrl($platform, (string) $val);
    if ($url === '') {
        continue;
    }
    $socialItems[] = [
        'platform' => $platform,
        'url' => $url,
        'value' => $val,
    ];
}

$socialIcon = static function (string $platform): string {
    $ic = [
        'instagram' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="w-6 h-6"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor"/></svg>',
        'facebook' => '<svg viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6"><path d="M13 22v-8h3l1-4h-4V7.5c0-1.1.4-2 2-2h2V2h-3c-3 0-5 1.8-5 5v3H6v4h3v8h4z"/></svg>',
        'tiktok' => '<svg viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6"><path d="M16 3v3a5 5 0 0 0 5 5v3a8 8 0 0 1-5-1.8V16a6 6 0 1 1-6-6h1v4h-1a2 2 0 1 0 2 2V3h4z"/></svg>',
        'whatsapp' => '<svg viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6"><path d="M12 2a10 10 0 0 0-8.5 15.2L2 22l4.9-1.4A10 10 0 1 0 12 2zm5.7 14.3c-.2.6-1.2 1.1-1.7 1.2-.5 0-1 .2-1.7 0-.4-.1-1-.3-1.7-.6-3-1.3-4.9-4.2-5-4.4-.2-.2-1.3-1.7-1.3-3.3s.8-2.3 1.1-2.6c.3-.3.7-.4.9-.4h.6c.2 0 .5 0 .7.5l1 2.4c.1.3.2.6 0 .9l-.4.6c-.2.2-.4.4-.2.8.2.4.9 1.4 1.9 2.3 1.3 1.1 2.3 1.5 2.7 1.7.4.2.7.1.9-.1l1-1.1c.2-.3.5-.2.8-.1l2.3 1.1c.3.1.6.2.7.4.1.2.1.9-.1 1.5z"/></svg>',
        'website' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="w-6 h-6"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c3 3 3 15 0 18M12 3c-3 3-3 15 0 18"/></svg>',
        'menu' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="w-6 h-6"><path d="M5 4h14v16H5z"/><path d="M9 8h6M9 12h6M9 16h4"/></svg>',
        'phone' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="w-6 h-6"><path d="M3 5a2 2 0 0 1 2-2h2.2a1 1 0 0 1 .96.73l1.07 3.73a1 1 0 0 1-.28 1L7.5 10a12 12 0 0 0 6.5 6.5l1.55-1.45a1 1 0 0 1 1-.28l3.73 1.07a1 1 0 0 1 .73.96V19a2 2 0 0 1-2 2h-1A15 15 0 0 1 3 6V5z"/></svg>',
        'address' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="w-6 h-6"><path d="M12 22s7-7.3 7-12a7 7 0 0 0-14 0c0 4.7 7 12 7 12z"/><circle cx="12" cy="10" r="2.5"/></svg>',
    ];
    return $ic[$platform] ?? '';
};

$ytId = qd_youtube_video_id(trim((string) ($settings['welcome_youtube_url'] ?? '')));
$ytEmbed = $ytId
    ? 'https://www.youtube-nocookie.com/embed/' . rawurlencode($ytId) . '?rel=0&modestbranding=1&playsinline=1&autoplay=1&mute=1'
    : '';
?>
<style>
  body.qd-door {
    background:
      radial-gradient(1200px 600px at 10% -10%, color-mix(in srgb, var(--accent) 22%, transparent), transparent 60%),
      radial-gradient(900px 600px at 110% 110%, color-mix(in srgb, var(--theme) 40%, transparent), transparent 60%),
      linear-gradient(180deg, var(--theme) 0%, #0b1222 100%)
      <?php if ($bgUrl): ?>, url('<?php echo qd_safe($bgUrl); ?>') center/cover<?php endif; ?>;
    color: #f8fafc;
    background-blend-mode: multiply;
  }
  .qd-welcome-root {
    box-sizing: border-box;
    width: 100%;
    min-height: 100dvh;
    max-height: 100dvh;
    overflow: hidden;
  }
  .qd-welcome-copy {
    box-sizing: border-box;
  }
  .qd-welcome-copy-inner {
    box-sizing: border-box;
    width: 100%;
    max-width: 42rem;
    margin: 0 auto;
  }
  .qd-welcome-with-media .qd-welcome-copy-inner {
    max-width: 36rem;
  }
  @media (min-width: 1024px) {
    .qd-welcome-with-media .qd-welcome-copy-inner {
      max-width: 28rem;
    }
  }
  .qd-card {
    background: rgba(255,255,255,.06);
    border: 1px solid rgba(255,255,255,.10);
    backdrop-filter: blur(6px);
    border-radius: 32px;
  }
  .qd-pill {
    display:inline-flex; align-items:center; gap:.65rem; padding: .9rem 1.25rem; border-radius: 9999px;
    background: rgba(255,255,255,.08); color:#fff; font-weight: 600; border: 1px solid rgba(255,255,255,.12);
    transition: background .2s; text-decoration: none;
  }
  .qd-pill:hover { background: rgba(255,255,255,.14); }
  .qd-pill svg { color: var(--accent); }
  .qd-handle { color: rgba(255,255,255,.75); font-weight: 500; }
  .qd-cta-badge {
    display:inline-flex; align-items:center; gap:.5rem; padding: .35rem .8rem; border-radius: 9999px;
    font-size: clamp(10px, 1.8vw, 12px); letter-spacing: .2em; text-transform: uppercase;
    color: #0b0b0b; background: var(--accent); font-weight: 800;
  }
  .qd-soft { color: rgba(255,255,255,.72); }
  .qd-welcome-title {
    font-size: clamp(1.75rem, 4.2vw, 3.75rem);
    line-height: 1.05;
    font-weight: 900;
    letter-spacing: -0.02em;
  }
  .qd-welcome-sub {
    font-size: clamp(1rem, 2.2vw, 1.5rem);
    line-height: 1.35;
  }
  .qd-welcome-video-panel {
    box-sizing: border-box;
    background: rgba(0,0,0,.35);
    border-left: 1px solid rgba(255,255,255,.08);
  }
  .qd-welcome-video-frame {
    position: relative;
    width: 100%;
    aspect-ratio: 16 / 9;
    max-height: min(38dvh, 360px);
    border-radius: 20px;
    overflow: hidden;
    background: #000;
    box-shadow: 0 24px 50px -20px rgba(0,0,0,.65);
  }
  @media (min-width: 1024px) {
    .qd-welcome-video-frame {
      max-height: none;
      height: min(72dvh, 640px);
      aspect-ratio: auto;
    }
  }
  .qd-welcome-video-frame iframe {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    border: 0;
  }
<?php if (!empty($theme['welcome_extra_css'] ?? '')) { ?>
<?php echo $theme['welcome_extra_css']; ?>

<?php } ?>
</style>
<div class="qd-welcome-root flex flex-col lg:flex-row <?php echo $ytId ? 'qd-welcome-with-media' : ''; ?>">
  <?php if ($ytId): ?>
  <aside class="qd-welcome-video-panel order-first lg:order-none w-full lg:w-[min(44%,560px)] shrink-0 flex flex-col justify-center px-3 pt-3 pb-2 lg:p-6 lg:pl-8">
    <div class="qd-welcome-video-frame mx-auto w-full max-w-2xl lg:max-w-none ring-1 ring-white/10">
      <iframe src="<?php echo qd_safe($ytEmbed); ?>"
              title="YouTube tanıtım"
              allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
              allowfullscreen
              loading="lazy"
              referrerpolicy="strict-origin-when-cross-origin"></iframe>
    </div>
    <p class="text-center text-[10px] uppercase tracking-[0.25em] text-white/45 mt-2 font-bold">YouTube · tanıtım</p>
  </aside>
  <?php endif; ?>

  <div class="qd-welcome-copy order-2 lg:order-none flex-1 min-h-0 min-w-0 flex items-center justify-center px-4 py-6 sm:px-8 sm:py-10 overflow-y-auto">
    <div class="qd-welcome-copy-inner w-full">
      <div class="qd-card p-6 sm:p-8 lg:p-10 shadow-2xl text-center">
        <?php if ($showLogo): ?>
          <div class="flex justify-center mb-5 sm:mb-7"><?php echo qd_logo_markup($business, 96, 'ring-4 ring-white/10'); ?></div>
        <?php endif; ?>

        <div class="qd-cta-badge mb-4 sm:mb-5"><?php echo qd_safe($welcomeTagline !== '' ? $welcomeTagline : $dict['welcome_tagline']); ?></div>

        <h1 class="qd-welcome-title">
          <?php echo qd_safe($welcomeTitle); ?>
        </h1>
        <div class="mt-3 sm:mt-4 qd-welcome-sub qd-soft max-w-2xl mx-auto">
          <?php echo qd_safe($welcomeSubtitle); ?>
        </div>

        <?php
        $doorMenuItems = $doorMenuItems ?? [];
        if (!empty($doorMenuItems)) {
            $dl = $settings['default_language'] ?? 'tr';
            $fd = function_exists('qd_dict') ? qd_dict($dl) : [];
            $doorCap = (string) ($fd['door_featured'] ?? 'Menu');
        ?>
        <div class="mt-6 sm:mt-8" id="qdDoorMenuSlider">
          <p class="text-[10px] sm:text-xs uppercase tracking-[0.25em] qd-soft mb-2 text-center sm:text-left"><?php echo qd_safe($doorCap); ?></p>
          <div class="relative w-full max-w-2xl mx-auto rounded-2xl overflow-hidden border border-white/10 bg-white/5 ring-1 ring-white/5" style="min-height:7.5rem;max-height:11rem;aspect-ratio:2/1">
            <?php foreach ($doorMenuItems as $ix => $row):
                $img = trim((string) ($row['image_url'] ?? ''));
                $pr = $row['price'] ?? null;
                $name = (string) ($row['name'] ?? '');
                $ch = function_exists('mb_substr') ? mb_substr($name, 0, 1, 'UTF-8') : substr($name, 0, 1);
            ?>
            <div class="qd-welcome-menu-slide absolute inset-0 flex items-stretch transition-opacity duration-700 ease-out
              <?php echo $ix === 0 ? 'opacity-100 is-active z-[1]' : 'opacity-0 z-0 pointer-events-none'; ?>">
              <div class="flex-1 min-w-0 p-3 sm:p-4 flex flex-col justify-center text-left pl-3 sm:pl-5">
                <div class="font-extrabold text-sm sm:text-base text-white leading-snug line-clamp-2"><?php echo qd_safe($name); ?></div>
                <?php if ($pr !== null && (float) $pr > 0): ?>
                <div class="mt-1.5 text-xs sm:text-sm font-extrabold tabular-nums" style="color: var(--accent)">
                  <?php echo qd_safe(number_format((float) $pr, 0, ',', '.')); ?>
                </div>
                <?php endif; ?>
              </div>
              <div class="w-[38%] sm:w-2/5 min-h-0 shrink-0 relative bg-gradient-to-br from-slate-900/40 to-slate-950/60">
                <?php if ($img !== ''): ?>
                <img src="<?php echo qd_safe($img); ?>" alt="" class="absolute inset-0 w-full h-full object-cover" loading="lazy" decoding="async" fetchpriority="low">
                <?php else: ?>
                <div class="absolute inset-0 flex items-center justify-center text-2xl sm:text-4xl font-black text-white/20 select-none" aria-hidden="true"><?php echo qd_safe($ch); ?></div>
                <?php endif; ?>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <script>
        (function(){
          var root = document.getElementById('qdDoorMenuSlider');
          if (!root) return;
          var slides = root.querySelectorAll('.qd-welcome-menu-slide');
          if (slides.length < 2) return;
          var i = 0;
          setInterval(function(){
            slides[i].classList.add('opacity-0','z-0','pointer-events-none');
            slides[i].classList.remove('opacity-100','z-[1]','is-active');
            i = (i + 1) % slides.length;
            slides[i].classList.remove('opacity-0','z-0','pointer-events-none');
            slides[i].classList.add('opacity-100','z-[1]','is-active');
          }, 5000);
        })();
        </script>
        <?php } ?>

        <?php if (!empty($socialItems)): ?>
        <div class="mt-8 sm:mt-10 text-[10px] sm:text-xs uppercase tracking-[0.3em] qd-soft"><?php echo qd_safe($dict['follow_us']); ?></div>
        <div class="mt-4 sm:mt-5 flex flex-wrap justify-center gap-2 sm:gap-3 lg:gap-4">
          <?php foreach ($socialItems as $item): ?>
            <a class="qd-pill" target="_blank" rel="noopener" href="<?php echo qd_safe($item['url']); ?>">
              <?php echo $socialIcon($item['platform']); ?>
              <?php
                $label = $item['platform'] === 'menu' ? $dict['menu']
                    : ($item['platform'] === 'website' ? $dict['website']
                    : ($item['platform'] === 'phone' ? $dict['call_us']
                    : ($item['platform'] === 'address' ? ($item['value'])
                    : '@' . ltrim(preg_replace('#^https?://[^/]+/@?#i', '', (string) $item['value']), '@'))));
              ?>
              <span class="qd-handle"><?php echo qd_safe($label); ?></span>
            </a>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php
        $hoursText = trim((string) ($settings['welcome_hours'] ?? ''));
        if ($hoursText === '') {
            $hoursText = $dict['welcome_hours'] ?? '';
        }
        ?>
        <div class="mt-8 sm:mt-10 flex flex-wrap items-center justify-center gap-2 sm:gap-3 text-[10px] sm:text-xs tracking-[0.3em] uppercase qd-soft">
          <span id="qdClock" class="clock"></span>
          <?php if ($hoursText !== ''): ?>
            <span class="hidden sm:inline">·</span>
            <span><?php echo qd_safe($hoursText); ?></span>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($showPowered): ?>
        <div class="mt-5 sm:mt-6 text-center text-white/40 text-[10px] sm:text-xs tracking-[0.3em] uppercase">Powered by Qordy</div>
      <?php endif; ?>
    </div>
  </div>
</div>
