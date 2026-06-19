<?php
/**
 * Standalone premium layout for the Qordy blog.
 *
 * Visual language is aligned 1:1 with the React landing page
 * (frontend/src/…): dark ink background, Fraunces display serif for
 * headlines, Inter for body, fire-blue accents for CTAs.
 *
 * Required variables provided by SoroBlogController / views:
 *   $page_title, $meta_description, $canonical, $customSEOTags,
 *   $og_image (optional), $hero (optional: ['eyebrow','title','subtitle'])
 *
 * Optional section variables rendered by child views after this header:
 *   render the body markup, then end with <?php include _layout_footer.php ?>
 */
require_once __DIR__ . '/../../helpers/functions.php';

$base = defined('BASE_URL') ? BASE_URL : '';
$pageTitle       = $page_title       ?? 'Qordy Blog';
$metaDescription = $meta_description ?? 'Qordy — Restoran yönetim sistemleri, QR menü ve dijitalleşme rehberleri.';
$canonical       = $canonical        ?? ($base . '/blog');
$ogImage         = $og_image         ?? ($base . '/assets/images/og-default.jpg');
$hero            = $hero             ?? null;
$currentSlug     = $current_slug     ?? null;
$bodyClass       = $body_class       ?? 'is-blog-index';

// Build a cache-buster tied to the compiled assets so edits propagate
// instantly while still allowing 1-year immutable caching on each hash.
$twMtime  = @filemtime(__DIR__ . '/../../../public/assets/css/tailwind.min.css') ?: 1;
$cssMtime = @filemtime(__DIR__ . '/../../../public/assets/css/blog.css')         ?: 1;
?><!doctype html>
<html lang="tr" dir="ltr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#050912">
<meta name="color-scheme" content="dark light">

<!-- Core SEO -->
<title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
<meta name="description" content="<?= htmlspecialchars($metaDescription, ENT_QUOTES, 'UTF-8') ?>">
<link rel="canonical" href="<?= htmlspecialchars($canonical, ENT_QUOTES, 'UTF-8') ?>">

<!-- Resource hints — paralelleştirilmiş 3rd party bağlantıları -->
<link rel="preconnect" href="https://app.trysoro.com" crossorigin>
<link rel="dns-prefetch" href="https://app.trysoro.com">
<link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="preconnect" href="https://afocirmbqdxnkyescnev.supabase.co" crossorigin>
<link rel="dns-prefetch" href="https://afocirmbqdxnkyescnev.supabase.co">

<!-- Fraunces + Inter — landing sayfasıyla aynı tipografi, non-blocking -->
<link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600;9..144,700;9..144,800&family=Inter:wght@300;400;500;600;700;800&display=swap">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600;9..144,700;9..144,800&family=Inter:wght@300;400;500;600;700;800&display=swap" media="print" onload="this.media='all';this.onload=null">
<noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600;9..144,700;9..144,800&family=Inter:wght@300;400;500;600;700;800&display=swap"></noscript>

<!-- Derlenmiş Tailwind (23KB gzip) + özel blog stilleri -->
<link rel="preload" as="style" href="<?= $base ?>/assets/css/tailwind.min.css?v=<?= $twMtime ?>" fetchpriority="high">
<link rel="stylesheet"          href="<?= $base ?>/assets/css/tailwind.min.css?v=<?= $twMtime ?>" fetchpriority="high">
<link rel="preload" as="style" href="<?= $base ?>/assets/css/blog.css?v=<?= $cssMtime ?>">
<link rel="stylesheet"          href="<?= $base ?>/assets/css/blog.css?v=<?= $cssMtime ?>">

<!-- Critical above-the-fold CSS (~1.4KB) — garantili FCP, FOUC yok -->
<style>
:root{--ink-950:#050912;--ink-900:#0B1220;--ink-800:#0F172A;--ink-700:#1E293B;--ink-200:#CBD5E1;--fire-500:#2B7AC9;--fire-400:#5483F2;--amber-300:#93C5FD;--amber-400:#60A5FA;--cream-50:#FFFFFF;--cream-200:#E2E8F0}
*,*::before,*::after{box-sizing:border-box}
html,body{margin:0;padding:0}
html{scroll-behavior:smooth;-webkit-text-size-adjust:100%}
body{background:var(--ink-950);color:var(--cream-200);font-family:Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif;line-height:1.6;-webkit-font-smoothing:antialiased;min-height:100vh;text-rendering:optimizeLegibility}
.font-display{font-family:Fraunces,"Playfair Display",ui-serif,Georgia,serif;letter-spacing:-0.02em}
.blog-hero{background:radial-gradient(60% 50% at 50% 0%,rgba(31,90,171,.30) 0%,rgba(31,90,171,0) 60%),radial-gradient(50% 40% at 80% 20%,rgba(59,130,246,.22) 0%,rgba(59,130,246,0) 70%),linear-gradient(180deg,#0B1220 0%,#050912 100%);position:relative;overflow:hidden}
.blog-hero::after{content:"";position:absolute;inset:0;background-image:radial-gradient(rgba(255,255,255,.04) 1px,transparent 1px);background-size:18px 18px;pointer-events:none;mix-blend-mode:screen}
a{color:inherit;text-decoration:none}
img{max-width:100%;height:auto;display:block}
.container{width:100%;max-width:1200px;margin-left:auto;margin-right:auto;padding-left:clamp(1rem,3vw,2rem);padding-right:clamp(1rem,3vw,2rem)}
.nav-shell{position:sticky;top:0;z-index:40;backdrop-filter:saturate(140%) blur(12px);-webkit-backdrop-filter:saturate(140%) blur(12px);background:rgba(5,9,18,.72);border-bottom:1px solid rgba(203,213,225,.08)}
.btn-cta{display:inline-flex;align-items:center;gap:.5rem;padding:.6rem 1.1rem;border-radius:9999px;font-weight:600;background:linear-gradient(135deg,#1F5AAB 0%,#3B82F6 100%);color:#fff;box-shadow:0 8px 24px -8px rgba(31,90,171,.55);transition:transform .18s ease,box-shadow .18s ease}
.btn-cta:hover{transform:translateY(-1px);box-shadow:0 12px 32px -10px rgba(31,90,171,.75)}
.skeleton-card{background:linear-gradient(90deg,rgba(203,213,225,.06) 0%,rgba(203,213,225,.12) 50%,rgba(203,213,225,.06) 100%);background-size:200% 100%;animation:skel 1.4s linear infinite;border-radius:1rem;min-height:260px}
@keyframes skel{0%{background-position:200% 0}100%{background-position:-200% 0}}
@media (prefers-reduced-motion:reduce){*{animation:none!important;transition:none!important}}
</style>

<?php if (!empty($ogImage)): ?>
<link rel="preload" as="image" href="<?= htmlspecialchars($ogImage, ENT_QUOTES, 'UTF-8') ?>" fetchpriority="high">
<?php endif; ?>

<?php if (!empty($customSEOTags)) echo $customSEOTags; ?>

<!-- Favicon -->
<?php $fm = @filemtime(__DIR__ . '/../../../public/assets/images/favicon.png') ?: 1; ?>
<link rel="icon" type="image/png" href="<?= $base ?>/assets/images/favicon.png?v=<?= $fm ?>">
<link rel="apple-touch-icon" href="<?= $base ?>/assets/images/favicon.png?v=<?= $fm ?>">
<link rel="manifest" href="<?= $base ?>/manifest.json">

<!-- Google Analytics — async, sayfa yüklenmesini bloklamaz -->
<link rel="dns-prefetch" href="https://www.googletagmanager.com">
<script async src="https://www.googletagmanager.com/gtag/js?id=G-VGLNCRHSYM"></script>
<script>
window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments)}window.gtag=gtag;
gtag('js',new Date());gtag('config','G-VGLNCRHSYM',{send_page_view:true,page_path:location.pathname});
</script>
</head>
<body class="<?= htmlspecialchars($bodyClass, ENT_QUOTES, 'UTF-8') ?> bg-ink-950 text-slate-200 antialiased">

<!-- ========= Top Nav (landing ile uyumlu) ========= -->
<header class="nav-shell">
  <div class="container flex items-center justify-between h-16">
    <a href="<?= $base ?>/" class="flex items-center gap-2 font-display text-xl font-semibold text-white hover:opacity-90 transition-opacity" aria-label="Qordy ana sayfa">
      <svg width="28" height="28" viewBox="0 0 32 32" fill="none" aria-hidden="true">
        <circle cx="16" cy="16" r="14" stroke="url(#qg)" stroke-width="2.25"/>
        <path d="M22 22 L27 27" stroke="url(#qg)" stroke-width="2.5" stroke-linecap="round"/>
        <defs><linearGradient id="qg" x1="0" y1="0" x2="32" y2="32" gradientUnits="userSpaceOnUse"><stop stop-color="#2B7AC9"/><stop offset="1" stop-color="#3B82F6"/></linearGradient></defs>
      </svg>
      <span>Qordy</span>
      <span class="hidden sm:inline text-slate-400 font-normal text-base">/ Blog</span>
    </a>
    <nav class="hidden md:flex items-center gap-7 text-sm text-slate-300" aria-label="Birincil gezinme">
      <a href="<?= $base ?>/" class="hover:text-white transition-colors">Ana Sayfa</a>
      <a href="<?= $base ?>/blog" class="hover:text-white transition-colors <?= $bodyClass === 'is-blog-index' ? 'text-white' : '' ?>">Blog</a>
      <a href="<?= $base ?>/#pricing" class="hover:text-white transition-colors">Fiyatlar</a>
      <a href="<?= $base ?>/#contact" class="hover:text-white transition-colors">İletişim</a>
    </nav>
    <div class="flex items-center gap-3">
      <a href="<?= $base ?>/login" class="hidden sm:inline text-sm text-slate-300 hover:text-white transition-colors">Giriş</a>
      <a href="<?= $base ?>/register" class="btn-cta text-sm">
        <span>Ücretsiz Dene</span>
        <svg width="14" height="14" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M3 8h10M9 4l4 4-4 4" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg>
      </a>
    </div>
  </div>
</header>

<?php if ($hero): ?>
<!-- ========= Hero ========= -->
<section class="blog-hero">
  <div class="container py-14 sm:py-20 lg:py-24 relative z-10">
    <?php if (!empty($hero['eyebrow'])): ?>
    <p class="inline-flex items-center gap-2 text-xs font-medium uppercase tracking-[0.18em] text-amber-300 bg-white/5 border border-white/10 rounded-full px-3 py-1 mb-5">
      <span class="block w-1.5 h-1.5 rounded-full bg-amber-300"></span>
      <?= htmlspecialchars($hero['eyebrow'], ENT_QUOTES, 'UTF-8') ?>
    </p>
    <?php endif; ?>
    <h1 class="font-display text-white font-semibold text-4xl sm:text-5xl lg:text-6xl max-w-4xl leading-[1.05]">
      <?= $hero['title'] ?? '' ?>
    </h1>
    <?php if (!empty($hero['subtitle'])): ?>
    <p class="mt-5 text-slate-300 text-lg sm:text-xl max-w-2xl leading-relaxed">
      <?= htmlspecialchars($hero['subtitle'], ENT_QUOTES, 'UTF-8') ?>
    </p>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>

<main id="main" class="relative z-10">
