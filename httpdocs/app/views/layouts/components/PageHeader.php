<?php
/**
 * PageHeader — Standart sayfa başlık bloğu.
 *
 * Sayfanın üst kısmındaki başlık + alt başlık + aksiyon butonları
 * bloğunu standartlaştırır. Tüm sayfalarda aynı görünür.
 *
 * Kullanım:
 * echo PageHeader::render([
 * 'title' => 'Yönetim Paneli',
 * 'subtitle' => 'İşletmenizin anlık durumu',
 * 'actions' => [
 * ['label' => 'AI Danışman', 'icon' => 'sparkles', 'variant' => 'primary', 'attrs' => ['data-ai-trigger' => '']],
 * ['label' => 'Yenile', 'icon' => 'arrow-right', 'variant' => 'ghost', 'href' => '?refresh=1'],
 * ],
 * 'breadcrumbs' => [
 * ['label' => 'Yönetim', 'href' => '/admin'],
 * ['label' => 'Genel Bakış'],
 * ],
 * ]);
 */

namespace App\Views\Components;

class PageHeader {
 public static function render(array $props): string {
 $title = htmlspecialchars((string)($props['title'] ?? ''), ENT_QUOTES, 'UTF-8');
 $subtitle = htmlspecialchars((string)($props['subtitle'] ?? ''), ENT_QUOTES, 'UTF-8');
 $actions = $props['actions'] ?? [];
 $breadcrumbs = $props['breadcrumbs'] ?? [];

 $breadcrumbsHtml = '';
 if (!empty($breadcrumbs)) {
 $items = [];
 foreach ($breadcrumbs as $i => $crumb) {
 $label = htmlspecialchars((string)($crumb['label'] ?? ''), ENT_QUOTES, 'UTF-8');
 $href = $crumb['href'] ?? null;
 $isLast = ($i === count($breadcrumbs) - 1);
 if ($href && !$isLast) {
 $items[] = sprintf(
 '<a href="%s" class="text-slate-500 hover:text-slate-700 transition">%s</a>',
 htmlspecialchars($href, ENT_QUOTES, 'UTF-8'),
 $label
 );
 } else {
 $items[] = sprintf('<span class="text-slate-700 font-semibold">%s</span>', $label);
 }
 if (!$isLast) {
 $items[] = '<span class="text-slate-300">/</span>';
 }
 }
 $breadcrumbsHtml = sprintf(
 '<nav class="flex items-center gap-2 text-xs sm:text-sm mb-2" aria-label="breadcrumb">%s</nav>',
 implode('', $items)
 );
 }

 $actionsHtml = '';
 if (!empty($actions)) {
 $actionHtmls = [];
 foreach ($actions as $action) {
 $type = $action['type'] ?? 'button';
 if ($type === 'raw' || $type === 'html') {
 $actionHtmls[] = (string)($action['html'] ?? '');
 } elseif ($type === 'link') {
 $actionHtmls[] = Button::renderLink($action);
 } else {
 $actionHtmls[] = Button::render($action);
 }
 }
 $actionsHtml = sprintf(
 '<div class="flex flex-wrap items-center gap-2 sm:gap-3 shrink-0">%s</div>',
 implode('', $actionHtmls)
 );
 }

 $subtitleHtml = $subtitle !== ''
 ? sprintf(
 '<p class="q-text-label mt-1" data-page-subtitle>%s</p>',
 $subtitle
 )
 : '';

 return sprintf(
 '<header class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-4 mb-4 sm:mb-5">%s<div class="min-w-0 flex-1"><h1 class="q-text-h1" data-page-title>%s</h1>%s</div>%s</header>',
 $breadcrumbsHtml,
 $title,
 $subtitleHtml,
 $actionsHtml
 );
 }
}
