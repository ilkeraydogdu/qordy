<?php
/**
 * Qordy — Kategori İkon Sistemi (v2 — Lucide uyumlu, font-tabanlı SVG)
 *
 * Tasarım:
 * - Tek tip, line-based SVG ikonlar (Lucide/Feather ailesi)
 * - stroke="currentColor" → tasarımın rengini miras alır (tutarlı görünüm)
 * - 24x24 viewBox, stroke-width 1.75 (ince, modern, font hissi)
 * - Renkli emoji YOK — hepsi design-system ile uyumlu
 * - 50+ özel ikon + ada göre otomatik eşleştirme
 * - Eski 'emoji' alanı kaldırıldı; yeni 'svg' alanı eklendi (geriye dönük uyumlu)
 */

if (!function_exists('posCategoryIconSvg')) {
 /**
 * Kategori için Lucide-tarzı SVG yolu döndürür.
 * 24x24 viewBox, stroke-width 1.75 — `currentColor` ile çalışır.
 *
 * @param string $iconType İkon anahtarı
 * @return string SVG path / element içeriği (svg etiketi olmadan)
 */
 function posCategoryIconSvg(string $iconType): string {
 switch ($iconType) {
 // ── İçecekler ──────────────────────────────────────────
 case 'drink':
 return '<path d="M8 2h8l-1.5 18a2 2 0 0 1-2 1.83h-1a2 2 0 0 1-2-1.83L8 2Z"/><path d="M7 6h10"/><path d="M10 10v4"/><path d="M14 10v4"/><path d="M10 2v2"/><path d="M14 2v2"/>';
 case 'coffee':
 return '<path d="M17 8h1a4 4 0 1 1 0 8h-1"/><path d="M3 8h14v9a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4Z"/><path d="M6 1v3"/><path d="M10 1v3"/><path d="M14 1v3"/>';
 case 'tea':
 return '<path d="M8 2h8l-1 7H9L8 2Z"/><path d="M9 9l-.5 11a2 2 0 0 0 2 2h3a2 2 0 0 0 2-2L15 9"/><path d="M7 2H5a2 2 0 0 0 0 4h2"/><path d="M12 6v2"/>';
 case 'wine':
 return '<path d="M8 22h8"/><path d="M7 10h10v4a5 5 0 0 1-10 0v-4Z"/><path d="M12 15v7"/><path d="M17 10c1.5-2 2-4.5 2-7H5c0 2.5.5 5 2 7"/>';
 case 'beer':
 return '<path d="M17 11h1a3 3 0 0 1 0 6h-1"/><path d="M3 11h14v8a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4Z"/><path d="M3 15h14"/><path d="M7 7c.5-1 .5-2 0-3"/><path d="M11 7c.5-1 .5-2 0-3"/>';
 case 'juice':
 return '<path d="M8 2h8l-1 6H9L8 2Z"/><rect x="7" y="8" width="10" height="13" rx="2"/><path d="M7 12h10"/><circle cx="11" cy="16" r=".5"/><circle cx="14" cy="18" r=".5"/>';
 case 'water':
 return '<path d="M12 2.5c-3 5-7 8-7 12.5a7 7 0 0 0 14 0c0-4.5-4-7.5-7-12.5Z"/>';

 // ── Yemekler ───────────────────────────────────────────
 case 'plate':
 return '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"/><path d="M7 2v20"/><path d="M21 15V2v0a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"/>';
 case 'pizza':
 return '<path d="M15 11h.01"/><path d="M11 15h.01"/><path d="M16 16h.01"/><path d="m2 16 20 6-6-20A20 20 0 0 0 2 16"/><path d="M5.71 17.11a17.04 17.04 0 0 1 11.4-11.4"/>';
 case 'burger':
 return '<path d="M3 12h18a0 0 0 0 1 0 0v2a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4Z"/><path d="M3 6a9 9 0 0 1 18 0v6H3Z"/><path d="M7 14a1 1 0 1 0 2 0 1 1 0 1 0-2 0"/><path d="M15 14a1 1 0 1 0 2 0 1 1 0 1 0-2 0"/>';
 case 'sandwich':
 return '<path d="M2 8h20l-1 3H3Z"/><path d="M3 11h18v2H3Z"/><path d="M3 13h18l-1 6H4Z"/><path d="M7 14v3"/><path d="M11 14v3"/><path d="M15 14v3"/>';
 case 'soup':
 return '<path d="M3 11h18"/><path d="M3 11a9 9 0 0 0 18 0"/><path d="M9 4c.5 1 .5 2 0 3"/><path d="M12 3c.5 1 .5 2 0 3"/><path d="M15 4c.5 1 .5 2 0 3"/>';
 case 'pasta':
 return '<path d="M3 6c0 4 4 4 4 8s-4 4-4 8"/><path d="M9 6c0 4 4 4 4 8s-4 4-4 8"/><path d="M15 6c0 4 4 4 4 8s-4 4-4 8"/><path d="M21 6c0 4 4 4 4 8s-4 4-4 8"/>';
 case 'noodle':
 return '<path d="M2 12h20"/><path d="M2 12a10 10 0 0 0 20 0"/><path d="M5 5c0 1.5 1 2 1 3.5S5 10.5 5 12"/><path d="M9 4c0 1.5 1 2 1 3.5S9 9.5 9 11"/><path d="M13 4c0 1.5 1 2 1 3.5s-1 2-1 3.5"/><path d="M17 5c0 1.5 1 2 1 3.5s-1 2-1 3.5"/>';
 case 'rice':
 return '<path d="M3 11h18l-2 8H5Z"/><path d="M3 11a9 9 0 0 1 18 0"/><path d="M8 7c.5-.8.5-1.5 0-2"/><path d="M12 6c.5-.8.5-1.5 0-2"/><path d="M16 7c.5-.8.5-1.5 0-2"/>';
 case 'meat':
 return '<path d="M6.5 17.5 12 23l5.5-5.5"/><path d="M2 12a10 10 0 0 1 20 0c0 2-1 3-2 3-2 0-2-1-4-1s-2 1-4 1-2-1-4-1-2 1-4 1c-1 0-2-1-2-3Z"/><circle cx="12" cy="12" r="1.5"/>';
 case 'grill':
 return '<path d="M3 4h18l-1 4H4Z"/><path d="M5 8l1 13h12l1-13"/><path d="M9 12v6"/><path d="M15 12v6"/><path d="M3 4 5 1"/><path d="M21 4l-2-3"/>';
 case 'kebab':
 return '<path d="M3 3l18 18"/><circle cx="6" cy="6" r="2"/><circle cx="10" cy="10" r="2"/><circle cx="14" cy="14" r="2"/><circle cx="18" cy="18" r="2"/>';
 case 'chicken':
 return '<path d="M15.5 3a4.5 4.5 0 0 0-3.5 7 5 5 0 0 0-1 3 5 5 0 0 0 4 5c2 0 3-1 4-2l3-3a2 2 0 0 0-2-2l-2 2"/><path d="M9 13a4 4 0 0 0 1 3"/><path d="M3 21l8-8"/>';
 case 'fish':
 return '<path d="M6.5 12c.94-3.46 4.94-6 8.5-6 3.56 0 6.06 2.54 7 6-.94 3.47-3.44 6-7 6s-7.56-2.53-8.5-6Z"/><path d="M18 12v.5"/><path d="M16 17.93a9.77 9.77 0 0 1 0-11.86"/><path d="M7 10.67C7 8 5.58 5.97 2.73 5.5c-1 1.5-1 5 .23 6.5-1.24 1.5-1.24 5-.23 6.5C5.58 18.03 7 16 7 13.33"/><path d="M10.46 7.26C10.2 5.88 9.17 4.24 8 3h5.8a2 2 0 0 1 1.98 1.67l.23 1.4"/><path d="m16.01 17.93-.23 1.4A2 2 0 0 1 13.8 21H9.5a5.96 5.96 0 0 0 1.49-3.98"/>';
 case 'seafood':
 return '<path d="M3 12c0-3 3-5 6-5s5 2 7 4 4 3 6 3"/><path d="M22 14c-2 0-4 1-6 3s-4 4-7 4-6-2-6-5"/><path d="M9 9c.5-.5 1-1 1-2"/><path d="M13 10c.5-.5 1-1 1-2"/><path d="M17 12c.5-.5 1-1 1-2"/><circle cx="6" cy="11" r=".5"/>';
 case 'sushi':
 return '<path d="M2 12h20"/><path d="M2 12c0-2 1-3 3-3h14c2 0 3 1 3 3"/><path d="M2 12c0 2 1 3 3 3h14c2 0 3-1 3-3"/><path d="M8 9v6"/><path d="M16 9v6"/><path d="M12 9v6"/>';

 // ── Tatlı & kahvaltı ──────────────────────────────────
 case 'dessert':
 return '<path d="M12 2v10"/><path d="m9 7 3-3 3 3"/><path d="M7 12h10l-5 10Z"/><path d="M9 16h6"/>';
 case 'cake':
 return '<path d="M20 21v-8a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8"/><path d="M4 16h16"/><path d="M12 11v5"/><path d="M2 11h20"/><path d="M12 3a3 3 0 0 0 3 3 3 3 0 0 0-6 0 3 3 0 0 0 3-3Z"/>';
 case 'cookie':
 return '<path d="M12 2a10 10 0 1 0 10 10c0-.5 0-1-.1-1.5a3 3 0 0 1-3.4-3.4c-.5.1-1 .1-1.5.1Z"/><circle cx="8.5" cy="8.5" r=".5"/><circle cx="15.5" cy="9.5" r=".5"/><circle cx="10" cy="14" r=".5"/><circle cx="16" cy="14" r=".5"/>';
 case 'donut':
 return '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="3"/><path d="M3 12h.01"/><path d="M21 12h.01"/><path d="M12 3v.01"/><path d="M12 21v.01"/>';
 case 'icecream':
 return '<circle cx="9" cy="7" r="3"/><circle cx="15" cy="7" r="3"/><circle cx="12" cy="11" r="3"/><path d="m7 10-3 11h16L17 10"/><path d="M9 14v3"/><path d="M15 14v3"/>';
 case 'bakery':
 return '<path d="M4 16c0-4 4-7 8-7s8 3 8 7v3H4Z"/><path d="M8 9V5a4 4 0 0 1 8 0v4"/><path d="M8 19v2"/><path d="M16 19v2"/>';
 case 'bread':
 return '<path d="M4 10c0-3 3-5 8-5s8 2 8 5-3 5-8 5-8-2-8-5Z"/><path d="M4 10v6a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-6"/><path d="M8 8v8"/><path d="M16 8v8"/>';
 case 'breakfast':
 return '<path d="M6 12h12"/><path d="M6 12a6 6 0 0 1 12 0"/><path d="M6 12a6 6 0 0 0 12 0"/><path d="M9 9a3 3 0 0 1 6 0c0 2-1 3-1 4s-1 2-2 2-2-1-2-2-1-2-1-4Z"/>';
 case 'egg':
 return '<path d="M12 2c-4 4-6 7-6 11a6 6 0 0 0 12 0c0-4-2-7-6-11Z"/>';
 case 'salad':
 return '<path d="M3 11h18"/><path d="M3 11a9 9 0 0 0 18 0"/><path d="M7 8c1-1 2-2 4-2"/><path d="M11 7c1-2 3-3 5-2"/><path d="M14 8c0-2 1-3 3-3"/>';
 case 'leaf':
 return '<path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19.2 2.5c.5 7.5-1.5 13-7 17.5-2.5.5-7 .5-7-3a7 7 0 0 1 1-3.5"/><path d="M2 21c0-3 1.85-5.36 5.08-6"/>';
 case 'vegan':
 return '<path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19.2 2.5c.5 7.5-1.5 13-7 17.5-2.5.5-7 .5-7-3a7 7 0 0 1 1-3.5"/><path d="M2 21c0-3 1.85-5.36 5.08-6"/>';
 case 'fruit':
 return '<path d="M12 7c0-3 2-5 5-5-1 4-3 6-5 6Z"/><path d="M12 7c0-2-1-3-3-3 0 2 1 3 3 3Z"/><path d="M20 12c0 4-3 9-8 9s-8-5-8-9a5 5 0 0 1 8-4 5 5 0 0 1 8 4Z"/>';

 // ── Atıştırmalık ──────────────────────────────────────
 case 'snack':
 return '<path d="M5 6h14v3a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2Z"/><path d="M6 11l1 9"/><path d="M18 11l-1 9"/><path d="M10 11v9"/><path d="M14 11v9"/><path d="M9 2l1 3"/><path d="M15 2l-1 3"/>';
 case 'popcorn':
 return '<path d="M4 10h16l-1 10H5Z"/><path d="M4 10c0-2 1-3 3-3 0-2 1-3 3-3 1-1 2-1 3 0 2 0 3 1 3 3 2 0 3 1 3 3"/><circle cx="8" cy="7" r=".5"/><circle cx="12" cy="6" r=".5"/><circle cx="16" cy="7" r=".5"/>';
 case 'nachos':
 return '<path d="M3 4h18l-3 16H6Z"/><path d="M7 8l1 2"/><path d="M11 8l1 2"/><path d="M15 8l1 2"/><path d="M9 12l1 2"/><path d="M13 12l1 2"/><path d="M17 12l-1 2"/>';

 // ── Genel / yardımcı ──────────────────────────────────
 case 'menu':
 return '<path d="M4 6h16"/><path d="M4 12h16"/><path d="M4 18h16"/>';
 case 'grid':
 return '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>';
 case 'utensils':
  return '<path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"/><path d="M7 2v20"/><path d="M21 15V2v0a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"/>';
 case 'chef':
 return '<path d="M6 13.87A4 4 0 0 1 7.41 6a5.11 5.11 0 0 1 1.05-1.54 5 5 0 0 1 7.08 0A5.11 5.11 0 0 1 16.59 6 4 4 0 0 1 18 13.87V21H6Z"/><path d="M6 17h12"/>';
 case 'star':
 return '<path d="m12 2 3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2Z"/>';
 case 'sparkles':
 return '<path d="M9.937 15.5A2 2 0 0 0 8.5 14.063l-6.135-1.582a.5.5 0 0 1 0-.962L8.5 9.936A2 2 0 0 0 9.937 8.5l1.582-6.135a.5.5 0 0 1 .963 0L14.063 8.5A2 2 0 0 0 15.5 9.937l6.135 1.581a.5.5 0 0 1 0 .964L15.5 14.063a2 2 0 0 0-1.437 1.437l-1.582 6.135a.5.5 0 0 1-.963 0z"/>';
 case 'flame':
 return '<path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5Z"/>';
 case 'heart':
 return '<path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.29 1.51 4.04 3 5.5l7 7Z"/>';
 case 'tag':
 return '<path d="M12 2H2v10l9.29 9.29c.94.94 2.48.94 3.42 0l6.58-6.58c.94-.94.94-2.48 0-3.42L12 2Z"/><path d="M7 7h.01"/>';
 case 'package':
 return '<path d="m16.5 9.4-9-5.19"/><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><path d="M3.27 6.96 12 12.01l8.73-5.05"/><path d="M12 22.08V12"/>';
 case 'gift':
 return '<path d="M20 12v10H4V12"/><path d="M2 7h20v5H2Z"/><path d="M12 22V7"/><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7Z"/><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7Z"/>';
 case 'store':
 return '<path d="m2 7 4.41-4.41A2 2 0 0 1 7.83 2h8.34a2 2 0 0 1 1.42.59L22 7"/><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/><path d="M15 22v-4a2 2 0 0 0-2-2h-2a2 2 0 0 0-2 2v4"/><path d="M2 7h20"/><path d="M22 7v3a2 2 0 0 1-2 2v0a2 2 0 0 1-2-2v0"/><path d="M18 12v0a2 2 0 0 1-2-2v0"/><path d="M14 12v0a2 2 0 0 1-2-2v0"/><path d="M10 12v0a2 2 0 0 1-2-2v0"/><path d="M6 12v0a2 2 0 0 1-2-2v0"/>';
 case 'shopping-bag':
 return '<path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/>';
 case 'cart':
 return '<circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>';
 case 'percent':
 return '<line x1="19" x2="5" y1="5" y2="19"/><circle cx="6.5" cy="6.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/>';
 case 'clock':
 return '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>';
 case 'calendar':
 return '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>';
 case 'image':
 return '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>';
 case 'box':
 return '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/>';

 default:
 return '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"/><path d="M7 2v20"/><path d="M21 15V2v0a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"/>';
 }
 }
}

if (!function_exists('posCategoryIconRender')) {
 /**
 * Hazır SVG ikonu (currentColor, 24x24) döndürür.
 *
 * @param int $size Piksel (varsayılan 24)
 * @param float $stroke Stroke genişliği (varsayılan 1.75 — font hissi)
 */
 function posCategoryIconRender(string $iconType, int $size = 24, float $stroke = 1.75): string {
 $path = posCategoryIconSvg($iconType);
 return sprintf(
 '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="%.2f" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">%s</svg>',
 $size,
 $size,
 $stroke,
  $path
 );
 }
}

if (!function_exists('posCategoryIcon')) {
 /**
 * Kategori adına göre uygun ikon + renk paleti döndürür.
 * 'gradient' tasarımın accent rengiyle uyumlu, muted bir yüzeydir.
 *
 * @return array{icon: string, svg?: string, gradient: string, ring: string, text: string, bg: string}
 */
 function posCategoryIcon(string $categoryName): array {
 $name = mb_strtolower(trim($categoryName));

 // İçecekler
 if (preg_match('/(şarap|sarap|wine|martini|şampanya|sampanya|viski|wiski|whiskey|votka|bira|beer)/u', $name)) {
 return posCategoryIconByKey('wine');
 }
 if (preg_match('/(kahve|coffee|espresso|cappuccino|latte|americano|türk kahvesi)/u', $name)) {
 return posCategoryIconByKey('coffee');
  }
 if (preg_match('/(çay|cay|tea|bitki çayı|yeşil çay|chai)/u', $name)) {
 return posCategoryIconByKey('tea');
 }
 if (preg_match('/(meyve suyu|juice|şerbet|serbet|limonata|smoothie|ayran|soda|meşrubat|mesrubat|kola|cola|fanta|salgam|şalgam)/u', $name)) {
 return posCategoryIconByKey('juice');
 }
 if (preg_match('/(içecek|icecek|drink|beverage|su|water|içecekler)/u', $name)) {
 return posCategoryIconByKey('drink');
 }

 // Tatlı + dondurma
 if (preg_match('/(dondurma|ice.?cream|gelato)/u', $name)) {
 return posCategoryIconByKey('icecream');
 }
 if (preg_match('/(pasta|kek|cake|cheesecake|pudding|muhallebi|sütlaç|sutlac|tiramisu)/u', $name)) {
 return posCategoryIconByKey('cake');
 }
 if (preg_match('/(künefe|kunefe|baklava|şeker|seker|tatlı|tatli|dessert|donut|şekerleme|sekerleme|helva)/u', $name)) {
 return posCategoryIconByKey('dessert');
 }
 if (preg_match('/(kurabiye|cookie|bisküvi|biskuvi|gofret|çikolata|cikolata|chocolate)/u', $name)) {
 return posCategoryIconByKey('cookie');
 }

 // Sağlıklı / sebze
 if (preg_match('/(vegan|vejetaryen|sağlıklı|saglikli|healthy)/u', $name)) {
 return posCategoryIconByKey('vegan');
 }
 if (preg_match('/(salata|salad|sebze|vegetable|bowl)/u', $name)) {
 return posCategoryIconByKey('salad');
 }
 if (preg_match('/(çorba|corba|soup)/u', $name)) {
 return posCategoryIconByKey('soup');
 }
 if (preg_match('/(pilav|rice)/u', $name)) {
 return posCategoryIconByKey('rice');
 }
 if (preg_match('/(makarna|mantı|manti|noodle|erişte|eriste|spagetti|lasagna|ravioli)/u', $name)) {
 return posCategoryIconByKey('noodle');
 }

 // Pizza
 if (preg_match('/(pizza)/u', $name)) {
 return posCategoryIconByKey('pizza');
 }
 // Burger + sandviç
 if (preg_match('/(burger)/u', $name)) {
 return posCategoryIconByKey('burger');
 }
 if (preg_match('/(sandviç|sandvic|sandwich|tost|döner|doner|kumpir|wrap|taco|fast.?food|durum)/u', $name)) {
 return posCategoryIconByKey('sandwich');
 }
 // Et
 if (preg_match('/(kebap|kebab|ızgara|izgara|steak|barbekü|barbeku|adana|kofte|köfte|şiş|sis|tantuni|ciger|ciğer)/u', $name)) {
 return posCategoryIconByKey('kebab');
 }
 if (preg_match('/(tavuk|chicken|piliç|pilic)/u', $name)) {
 return posCategoryIconByKey('chicken');
 }
 if (preg_match('/(et|meat|sucuk|sosis|salam|pastırma|pastirma)/u', $name)) {
 return posCategoryIconByKey('meat');
 }
 // Deniz
 if (preg_match('/(balık|balik|fish|karides|midye|kalamar|levrek|hamsi|çipura|cipura|somon)/u', $name)) {
 return posCategoryIconByKey('fish');
 }
 if (preg_match('/(deniz|sea|seafood|sushi)/u', $name)) {
 return posCategoryIconByKey('sushi');
 }

 // Atıştırmalık
 if (preg_match('/(patates|nugget|nuggets)/u', $name)) {
 return posCategoryIconByKey('snack');
 }
 if (preg_match('/(cips|chips|kraker|atıştırmalık|atistirmalik|appetizer|meze|aperatif|kanape)/u', $name)) {
 return posCategoryIconByKey('nachos');
 }
 if (preg_match('/(popcorn|patlamış mısır|patlamis misir)/u', $name)) {
 return posCategoryIconByKey('popcorn');
 }

 // Ekmek + fırın
 if (preg_match('/(ekmek|bread|börek|borek|simit|poğaça|pogaca|çörek|corek|pide|lavaş|lavas)/u', $name)) {
 return posCategoryIconByKey('bread');
 }
 if (preg_match('/(fırın|firin|bakery|pastane)/u', $name)) {
 return posCategoryIconByKey('bakery');
 }

 // Kahvaltı
 if (preg_match('/(kahvaltı|kahvalti|breakfast)/u', $name)) {
 return posCategoryIconByKey('breakfast');
 }
 if (preg_match('/(yumurta|egg|menemen|omlet|omelet|sahanda)/u', $name)) {
 return posCategoryIconByKey('egg');
 }

 // Meyve
 if (preg_match('/(meyve|fruit)/u', $name)) {
 return posCategoryIconByKey('fruit');
 }

 // Ana yemek / menü (fallback)
 if (preg_match('/(ana|main|food|yemek|menü|menu|tabak|dish|öğle|ogle|akşam|aksam|kase)/u', $name)) {
 return posCategoryIconByKey('plate');
 }

 // Hash-tutarlı fallback
 $palettes = ['plate', 'menu', 'utensils', 'chef', 'box', 'package', 'grid'];
 $idx = abs(crc32($name)) % count($palettes);
 return posCategoryIconByKey($palettes[$idx]);
 }
}

if (!function_exists('posCategoryIconLibrary')) {
 /**
 * Seçilebilir kategori ikon kütüphanesi (admin picker + POS).
 * Tümü line-based SVG, currentColor ile çalışır.
 * Eski 'emoji' alanı kaldırıldı; yeni 'svg' alanı eklendi.
 *
 * @return array<string, array{label: string, svg: string, gradient: string, ring: string, text: string, bg: string}>
 */
 function posCategoryIconLibrary(): array {
 $gradients = [
 'sky' => ['gradient' => 'from-sky-500 to-cyan-600', 'ring' => 'ring-sky-200', 'text' => 'text-sky-700', 'bg' => 'bg-sky-50'],
 'pink' => ['gradient' => 'from-pink-500 to-rose-600', 'ring' => 'ring-pink-200', 'text' => 'text-pink-700', 'bg' => 'bg-pink-50'],
 'emerald' => ['gradient' => 'from-emerald-500 to-teal-600', 'ring' => 'ring-emerald-200','text' => 'text-emerald-700','bg' => 'bg-emerald-50'],
 'red' => ['gradient' => 'from-red-500 to-orange-600', 'ring' => 'ring-red-200', 'text' => 'text-red-700', 'bg' => 'bg-red-50'],
 'amber' => ['gradient' => 'from-amber-500 to-orange-600', 'ring' => 'ring-amber-200', 'text' => 'text-amber-700',  'bg' => 'bg-amber-50'],
 'yellow' => ['gradient' => 'from-yellow-500 to-amber-600', 'ring' => 'ring-yellow-200', 'text' => 'text-yellow-700', 'bg' => 'bg-yellow-50'],
 'orange' => ['gradient' => 'from-orange-500 to-red-600', 'ring' => 'ring-orange-200', 'text' => 'text-orange-700', 'bg' => 'bg-orange-50'],
 'rose' => ['gradient' => 'from-rose-500 to-pink-600', 'ring' => 'ring-rose-200', 'text' => 'text-rose-700', 'bg' => 'bg-rose-50'],
 'fuchsia' => ['gradient' => 'from-fuchsia-500 to-pink-600', 'ring' => 'ring-fuchsia-200','text' => 'text-fuchsia-700','bg' => 'bg-fuchsia-50'],
 'blue' => ['gradient' => 'from-blue-500 to-cyan-600', 'ring' => 'ring-blue-200', 'text' => 'text-blue-700', 'bg' => 'bg-blue-50'],
 'cyan' => ['gradient' => 'from-cyan-500 to-sky-600', 'ring' => 'ring-cyan-200', 'text' => 'text-cyan-700', 'bg' => 'bg-cyan-50'],
 'teal' => ['gradient' => 'from-teal-500 to-cyan-600', 'ring' => 'ring-teal-200', 'text' => 'text-teal-700', 'bg' => 'bg-teal-50'],
 'green' => ['gradient' => 'from-green-500 to-emerald-600', 'ring' => 'ring-green-200', 'text' => 'text-green-700', 'bg' => 'bg-green-50'],
 'lime' => ['gradient' => 'from-lime-500 to-green-600', 'ring' => 'ring-lime-200', 'text' => 'text-lime-700', 'bg' => 'bg-lime-50'],
 'violet' => ['gradient' => 'from-violet-500 to-purple-600', 'ring' => 'ring-violet-200', 'text' => 'text-violet-700', 'bg' => 'bg-violet-50'],
 'purple' => ['gradient' => 'from-purple-500 to-fuchsia-600', 'ring' => 'ring-purple-200', 'text' => 'text-purple-700', 'bg' => 'bg-purple-50'],
 'indigo' => ['gradient' => 'from-indigo-500 to-violet-600',  'ring' => 'ring-indigo-200', 'text' => 'text-indigo-700', 'bg' => 'bg-indigo-50'],
 'slate' => ['gradient' => 'from-slate-600 to-gray-800', 'ring' => 'ring-slate-200', 'text' => 'text-slate-700',  'bg' => 'bg-slate-50'],
 'amber-warm' => ['gradient' => 'from-amber-700 to-yellow-800', 'ring' => 'ring-amber-300', 'text' => 'text-amber-800', 'bg' => 'bg-amber-50'],
 'wine' => ['gradient' => 'from-purple-700 to-rose-800', 'ring' => 'ring-purple-200', 'text' => 'text-purple-700', 'bg' => 'bg-purple-50'],
 ];

 $lib = [
 'drink' => ['label' => 'İçecek', 'icon' => 'drink', 'palette' => 'sky'],
 'coffee' => ['label' => 'Kahve', 'icon' => 'coffee', 'palette' => 'amber-warm'],
 'tea' => ['label' => 'Çay', 'icon' => 'tea', 'palette' => 'emerald'],
 'wine' => ['label' => 'Şarap', 'icon' => 'wine', 'palette' => 'wine'],
 'beer' => ['label' => 'Bira', 'icon' => 'beer', 'palette' => 'amber'],
 'juice' => ['label' => 'Meyve Suyu', 'icon' => 'juice', 'palette' => 'rose'],
 'water' => ['label' => 'Su', 'icon' => 'water', 'palette' => 'cyan'],
 'plate' => ['label' => 'Ana Yemek', 'icon' => 'plate', 'palette' => 'indigo'],
 'pizza' => ['label' => 'Pizza', 'icon' => 'pizza', 'palette' => 'red'],
 'burger' => ['label' => 'Burger', 'icon' => 'burger', 'palette' => 'amber'],
 'sandwich' => ['label' => 'Sandviç', 'icon' => 'sandwich', 'palette' => 'orange'],
 'kebab' => ['label' => 'Kebap / Izgara', 'icon' => 'kebab', 'palette' => 'red'],
 'grill' => ['label' => 'Izgara', 'icon' => 'grill', 'palette' => 'orange'],
 'chicken' => ['label' => 'Tavuk', 'icon' => 'chicken', 'palette' => 'amber'],
 'meat' => ['label' => 'Et', 'icon' => 'meat', 'palette' => 'rose'],
 'fish' => ['label' => 'Balık', 'icon' => 'fish', 'palette' => 'blue'],
 'seafood' => ['label' => 'Deniz Ürünleri', 'icon' => 'seafood', 'palette' => 'cyan'],
 'sushi' => ['label' => 'Sushi', 'icon' => 'sushi', 'palette' => 'teal'],
 'soup' => ['label' => 'Çorba', 'icon' => 'soup', 'palette' => 'orange'],
 'pasta' => ['label' => 'Makarna', 'icon' => 'pasta', 'palette' => 'yellow'],
 'noodle' => ['label' => 'Noodle', 'icon' => 'noodle', 'palette' => 'amber'],
 'rice' => ['label' => 'Pilav', 'icon' => 'rice', 'palette' => 'lime'],
 'salad' => ['label' => 'Salata', 'icon' => 'salad', 'palette' => 'green'],
 'leaf' => ['label' => 'Yeşillik', 'icon' => 'leaf', 'palette' => 'emerald'],
 'vegan' => ['label' => 'Vegan', 'icon' => 'vegan', 'palette' => 'green'],
 'fruit' => ['label' => 'Meyve', 'icon' => 'fruit', 'palette' => 'rose'],
  'egg' => ['label' => 'Yumurta', 'icon' => 'egg', 'palette' => 'yellow'],
 'breakfast' => ['label' => 'Kahvaltı', 'icon' => 'breakfast', 'palette' => 'amber'],
 'dessert' => ['label' => 'Tatlı', 'icon' => 'dessert', 'palette' => 'pink'],
 'cake' => ['label' => 'Pasta / Kek', 'icon' => 'cake', 'palette' => 'pink'],
 'cookie' => ['label' => 'Kurabiye', 'icon' => 'cookie', 'palette' => 'amber-warm'],
 'donut' => ['label' => 'Donut', 'icon' => 'donut', 'palette' => 'fuchsia'],
 'icecream' => ['label' => 'Dondurma', 'icon' => 'icecream', 'palette' => 'pink'],
 'snack' => ['label' => 'Atıştırmalık', 'icon' => 'snack', 'palette' => 'amber'],
 'popcorn' => ['label' => 'Popcorn', 'icon' => 'popcorn', 'palette' => 'yellow'],
 'nachos' => ['label' => 'Cips', 'icon' => 'nachos', 'palette' => 'orange'],
 'bread' => ['label' => 'Ekmek', 'icon' => 'bread', 'palette' => 'amber'],
 'bakery' => ['label' => 'Fırın', 'icon' => 'bakery', 'palette' => 'orange'],
 'utensils'  => ['label' => 'Menü / Genel', 'icon' => 'utensils', 'palette' => 'indigo'],
 'menu' => ['label' => 'Liste', 'icon' => 'menu', 'palette' => 'slate'],
 'grid' => ['label' => 'Kategori', 'icon' => 'grid', 'palette' => 'violet'],
 'chef' => ['label' => 'Şef / Mutfak', 'icon' => 'chef', 'palette' => 'purple'],
 'store' => ['label' => 'Mağaza', 'icon' => 'store', 'palette' => 'violet'],
 'cart' => ['label' => 'Sipariş', 'icon' => 'cart', 'palette' => 'blue'],
 'gift' => ['label' => 'Hediye / Kampanya', 'icon' => 'gift', 'palette' => 'pink'],
 'percent' => ['label' => 'İndirim', 'icon' => 'percent', 'palette' => 'red'],
 'star' => ['label' => 'Öne Çıkan', 'icon' => 'star', 'palette' => 'amber'],
 'sparkles' => ['label' => 'Özel', 'icon' => 'sparkles', 'palette' => 'violet'],
  'flame' => ['label' => 'Acılı / Sıcak', 'icon' => 'flame', 'palette' => 'red'],
 'heart' => ['label' => 'Favori', 'icon' => 'heart', 'palette' => 'rose'],
 'package' => ['label' => 'Paket', 'icon' => 'package', 'palette' => 'slate'],
 'box' => ['label' => 'Kutu / Ürün', 'icon' => 'box', 'palette' => 'indigo'],
 'clock' => ['label' => 'Saatlik / Taze', 'icon' => 'clock', 'palette' => 'blue'],
 'calendar' => ['label' => 'Günün / Tarih', 'icon' => 'calendar', 'palette' => 'teal'],
 'image' => ['label' => 'Genel Görsel', 'icon' => 'image', 'palette' => 'slate'],
 ];

 $out = [];
 foreach ($lib as $key => $meta) {
 $palette = $gradients[$meta['palette']];
 $out[$key] = array_merge(
 ['label' => $meta['label'], 'svg' => posCategoryIconSvg($meta['icon'])],
 $palette
 );
 }
 return $out;
 }
}

if (!function_exists('posCategoryIconByKey')) {
 /**
 * Kütüphaneden ikon meta bilgisi döndürür.
 *
 * @return array{icon: string, svg: string, gradient: string, ring: string, text: string, bg: string}
 */
 function posCategoryIconByKey(string $key): array {
 $library = posCategoryIconLibrary();
 if (isset($library[$key])) {
 $m = $library[$key];
 return [
 'icon' => $key,
 'svg' => $m['svg'],
 'gradient' => $m['gradient'],
 'ring' => $m['ring'],
 'text' => $m['text'],
 'bg' => $m['bg'],
 ];
 }
 $fallback = posCategoryIconLibrary()['plate'];
 return [
 'icon' => 'plate',
 'svg' => $fallback['svg'],
 'gradient' => $fallback['gradient'],
 'ring' => $fallback['ring'],
  'text' => $fallback['text'],
 'bg' => $fallback['bg'],
 ];
 }
}

if (!function_exists('resolveCategoryVisual')) {
 /**
 * Kategori görseli: image_url > seçili ikon > ada göre otomatik ikon
 *
 * @param array<string, mixed> $category
 * @return array{type: string, image_url?: string, icon?: string, svg?: string, gradient?: string, ring?: string, text?: string, bg?: string, label?: string}
 */
 function resolveCategoryVisual(array $category): array {
 $imageUrl = trim((string)($category['image_url'] ?? ''));
 if ($imageUrl !== '') {
 return ['type' => 'image', 'image_url' => $imageUrl];
 }

 $iconKey = trim((string)($category['icon'] ?? ''));
 if ($iconKey !== '' && isset(posCategoryIconLibrary()[$iconKey])) {
 $meta = posCategoryIconByKey($iconKey);
 $meta['label'] = posCategoryIconLibrary()[$iconKey]['label'];
 return array_merge(['type' => 'icon'], $meta);
 }

 $meta = posCategoryIcon($category['name'] ?? '');
 return array_merge(['type' => 'icon'], $meta);
 }
}
