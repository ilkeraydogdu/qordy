<?php
namespace App\Support;

/**
 * Queue display theme registry — Qordy yeme-içme sektörüne odaklı.
 *
 * Her kayıt:
 *   - key, name_tr, name_en, description_*
 *   - swatches, font, font_family, intensity
 *   - library: "general" | "sector"
 *   - preview: admin kartı için benzersiz SVG kompozisyonu
 *
 * Sektör temalarının kendi `themes/{key}.php` dosyası vardır; CSS override
 * katmanı kullanılmaz. Her sektör yeme-içme (restoran, kafe, bar, tatlı,
 * sosyal tesis vb.) için özel olarak çizilmiş bir kompozisyondur.
 */
class QueueThemeRegistry
{
    public const DEFAULT = 'modern';

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        $base = [
            'modern' => [
                'key' => 'modern',
                'library' => 'general',
                'preview' => 'panels',
                'name_tr' => 'Modern',
                'name_en' => 'Modern',
                'description_tr' => 'Cam paneller, yumuşak ışık — genel kullanım.',
                'description_en' => 'Glass panels, soft glow — universal layout.',
                'swatches' => ['#0f172a', '#f97316', '#ffffff'],
                'font' => 'Plus+Jakarta+Sans:wght@400;600;700;800',
                'font_family' => "'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif",
                'intensity' => 'dark',
            ],
            'elegant' => [
                'key' => 'elegant',
                'library' => 'general',
                'preview' => 'paper',
                'name_tr' => 'Zarif',
                'name_en' => 'Elegant',
                'description_tr' => 'Serif, krem tonlar — fine dining & otel lobisi.',
                'description_en' => 'Serif, cream tones — fine dining & hotel lobby.',
                'swatches' => ['#1a120b', '#c9a86a', '#f5ebd7'],
                'font' => 'Cormorant+Garamond:wght@500;600;700&family=Inter:wght@400;600;700',
                'font_family' => "'Cormorant Garamond', 'Inter', serif",
                'intensity' => 'light',
            ],
            'bold' => [
                'key' => 'bold',
                'library' => 'general',
                'preview' => 'marquee',
                'name_tr' => 'Enerjik',
                'name_en' => 'Bold',
                'description_tr' => 'Sticker / marquee — genç marka, street food.',
                'description_en' => 'Sticker / marquee energy — street food vibes.',
                'swatches' => ['#0b0b0f', '#22d3ee', '#f43f5e'],
                'font' => 'Space+Grotesk:wght@500;700',
                'font_family' => "'Space Grotesk', ui-sans-serif, sans-serif",
                'intensity' => 'dark',
            ],
            'minimal' => [
                'key' => 'minimal',
                'library' => 'general',
                'preview' => 'minimal',
                'name_tr' => 'Sade',
                'name_en' => 'Minimal',
                'description_tr' => 'Beyaz alan, ince tipografi — kurumsal & cafe.',
                'description_en' => 'Whitespace, thin type — boutique cafés.',
                'swatches' => ['#ffffff', '#0f172a', '#f1f5f9'],
                'font' => 'Inter:wght@300;500;700',
                'font_family' => "'Inter', ui-sans-serif, sans-serif",
                'intensity' => 'light',
            ],
            'sunset' => [
                'key' => 'sunset',
                'library' => 'general',
                'preview' => 'aurora',
                'name_tr' => 'Gün batımı',
                'name_en' => 'Sunset',
                'description_tr' => 'Turuncu–pembe — kafe ve lounge.',
                'description_en' => 'Orange–magenta — café & lounge.',
                'swatches' => ['#1a0a0f', '#f97316', '#ec4899'],
                'font' => 'Plus+Jakarta+Sans:wght@400;600;700;800',
                'font_family' => "'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif",
                'intensity' => 'dark',
            ],
            'noir' => [
                'key' => 'noir',
                'library' => 'general',
                'preview' => 'noir',
                'name_tr' => 'Noir',
                'name_en' => 'Noir',
                'description_tr' => 'Gece siyahı — bar ve gece kulübü.',
                'description_en' => 'Deep black — bar & nightlife.',
                'swatches' => ['#020617', '#0ea5e9', '#94a3b8'],
                'font' => 'Plus+Jakarta+Sans:wght@400;600;700;800',
                'font_family' => "'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif",
                'intensity' => 'dark',
            ],
        ];

        // ==========================================================
        // YEME & İÇME SEKTÖR KİTAPLIĞI — her layout tamamen farklı
        // ==========================================================
        $sectors = [
            'sector_restaurant' => [
                'key' => 'sector_restaurant',
                'library' => 'sector',
                'preview' => 'chalkboard',
                'sector_tr' => 'Restoran',
                'sector_en' => 'Restaurant',
                'name_tr' => 'Kara tahta menü',
                'name_en' => 'Chalkboard menu',
                'description_tr' => 'Tebeşir menü tahtası, mum ışıklı QR kartı — klasik restoran.',
                'description_en' => 'Chalk menu board, candlelit QR card — classic restaurant.',
                'swatches' => ['#12160f', '#fde68a', '#b45309'],
                'font' => 'Cormorant+Garamond:wght@500;600;700&family=Caveat:wght@400;600;700&family=Plus+Jakarta+Sans:wght@400;600;700;800',
                'font_family' => "'Plus Jakarta Sans', 'Caveat', ui-sans-serif, sans-serif",
                'intensity' => 'dark',
            ],
            'sector_cafe' => [
                'key' => 'sector_cafe',
                'library' => 'sector',
                'preview' => 'receipt',
                'sector_tr' => 'Kafe',
                'sector_en' => 'Café',
                'name_tr' => 'Kasa fişi',
                'name_en' => 'Receipt roll',
                'description_tr' => 'Termal kasa fişi, barkod & kahve çekirdeği — kafe & kahveci.',
                'description_en' => 'Thermal receipt, barcode & coffee beans — café & coffee shop.',
                'swatches' => ['#fffdf4', '#2a1a0f', '#d97706'],
                'font' => 'Cormorant+Garamond:wght@500;600;700&family=Roboto+Mono:wght@400;500;700',
                'font_family' => "'Roboto Mono', 'Cormorant Garamond', ui-monospace, monospace",
                'intensity' => 'dark',
            ],
            'sector_bakery' => [
                'key' => 'sector_bakery',
                'library' => 'sector',
                'preview' => 'recipe',
                'sector_tr' => 'Fırın & pastane',
                'sector_en' => 'Bakery',
                'name_tr' => 'Tarif defteri',
                'name_en' => 'Recipe notebook',
                'description_tr' => 'Çizgili tarif defteri, fırıncı damgası — fırın, pastane, börekçi.',
                'description_en' => 'Lined recipe page, baker stamp QR — bakery & patisserie.',
                'swatches' => ['#fdf5e6', '#b45309', '#7c2d12'],
                'font' => 'Caveat:wght@400;600;700&family=Cormorant+Garamond:wght@500;600;700',
                'font_family' => "'Caveat', 'Cormorant Garamond', cursive",
                'intensity' => 'light',
            ],
            'sector_pizzeria' => [
                'key' => 'sector_pizzeria',
                'library' => 'sector',
                'preview' => 'pizzeria',
                'sector_tr' => 'Pizzeria',
                'sector_en' => 'Pizzeria',
                'name_tr' => 'Trattoria kareli',
                'name_en' => 'Trattoria checker',
                'description_tr' => 'Kırmızı-beyaz kareli masa örtüsü, pizza dilimi — pizzeria & İtalyan.',
                'description_en' => 'Red-white checker tablecloth, pizza slice — pizzeria & Italian.',
                'swatches' => ['#fff1f2', '#dc2626', '#166534'],
                'font' => 'Cormorant+Garamond:wght@500;600;700&family=Inter:wght@400;600;700',
                'font_family' => "'Cormorant Garamond', 'Inter', serif",
                'intensity' => 'light',
            ],
            'sector_kebab' => [
                'key' => 'sector_kebab',
                'library' => 'sector',
                'preview' => 'kebab',
                'sector_tr' => 'Kebap & ızgara',
                'sector_en' => 'Grill & kebab',
                'name_tr' => 'Alev & şiş',
                'name_en' => 'Flame & skewer',
                'description_tr' => 'Kömür ızgara alevi, şiş motifi — kebapçı, ocakbaşı, döner.',
                'description_en' => 'Charcoal flame, skewer motif — grill house, döner.',
                'swatches' => ['#1a0806', '#ea580c', '#fde68a'],
                'font' => 'Cormorant+Garamond:wght@500;600;700&family=Inter:wght@400;600;700',
                'font_family' => "'Cormorant Garamond', 'Inter', serif",
                'intensity' => 'dark',
            ],
            'sector_breakfast' => [
                'key' => 'sector_breakfast',
                'library' => 'sector',
                'preview' => 'breakfast',
                'sector_tr' => 'Kahvaltı salonu',
                'sector_en' => 'Breakfast house',
                'name_tr' => 'Serpme sofra',
                'name_en' => 'Serpme spread',
                'description_tr' => 'Güneşli pastel, küçük tabak ızgarası — serpme kahvaltı.',
                'description_en' => 'Sunny pastel, small-plate grid — Turkish breakfast.',
                'swatches' => ['#fff7ed', '#f59e0b', '#84cc16'],
                'font' => 'Caveat:wght@400;600;700&family=Plus+Jakarta+Sans:wght@400;600;700;800',
                'font_family' => "'Plus Jakarta Sans', 'Caveat', ui-sans-serif, sans-serif",
                'intensity' => 'light',
            ],
            'sector_seafood' => [
                'key' => 'sector_seafood',
                'library' => 'sector',
                'preview' => 'seafood',
                'sector_tr' => 'Balık restoranı',
                'sector_en' => 'Seafood',
                'name_tr' => 'Martı & dalga',
                'name_en' => 'Gull & wave',
                'description_tr' => 'Deniz mavisi, dalga & halat — balıkçı, meyhane, iskele.',
                'description_en' => 'Sea blue, wave & rope — seafood & marina.',
                'swatches' => ['#0c4a6e', '#e0f2fe', '#f59e0b'],
                'font' => 'Cormorant+Garamond:wght@500;600;700&family=Inter:wght@400;600;700',
                'font_family' => "'Cormorant Garamond', 'Inter', serif",
                'intensity' => 'dark',
            ],
            'sector_icecream' => [
                'key' => 'sector_icecream',
                'library' => 'sector',
                'preview' => 'icecream',
                'sector_tr' => 'Dondurma & tatlı',
                'sector_en' => 'Ice cream & dessert',
                'name_tr' => 'Pastel külah',
                'name_en' => 'Pastel cone',
                'description_tr' => 'Pastel şerbet tonları, waffle külah — dondurmacı, tatlıcı.',
                'description_en' => 'Sorbet pastels, waffle cone — gelato & dessert.',
                'swatches' => ['#fff1f7', '#f472b6', '#38bdf8'],
                'font' => 'Caveat:wght@400;600;700&family=Plus+Jakarta+Sans:wght@400;600;700;800',
                'font_family' => "'Plus Jakarta Sans', 'Caveat', ui-sans-serif, sans-serif",
                'intensity' => 'light',
            ],
            'sector_teahouse' => [
                'key' => 'sector_teahouse',
                'library' => 'sector',
                'preview' => 'teahouse',
                'sector_tr' => 'Çay bahçesi',
                'sector_en' => 'Tea house',
                'name_tr' => 'İnce belli bardak',
                'name_en' => 'Tulip glass',
                'description_tr' => 'Anadolu kilim motifi, ince belli bardak — çay bahçesi, nargile.',
                'description_en' => 'Kilim motif, tulip tea glass — tea garden & hookah.',
                'swatches' => ['#7c2d12', '#fef3c7', '#991b1b'],
                'font' => 'Cormorant+Garamond:wght@500;600;700&family=Inter:wght@400;600;700',
                'font_family' => "'Cormorant Garamond', 'Inter', serif",
                'intensity' => 'dark',
            ],
            'sector_foodtruck' => [
                'key' => 'sector_foodtruck',
                'library' => 'sector',
                'preview' => 'foodtruck',
                'sector_tr' => 'Street food',
                'sector_en' => 'Street food',
                'name_tr' => 'Sokak lezzeti',
                'name_en' => 'Street feast',
                'description_tr' => 'Renkli marquee, plaka & kağıt paket — food truck, büfe, burger.',
                'description_en' => 'Bright marquee, plate & paper wrap — food truck & burger.',
                'swatches' => ['#0f172a', '#facc15', '#ef4444'],
                'font' => 'Space+Grotesk:wght@500;700&family=Archivo+Black',
                'font_family' => "'Archivo Black', 'Space Grotesk', ui-sans-serif, sans-serif",
                'intensity' => 'dark',
            ],
            'sector_social_facility' => [
                'key' => 'sector_social_facility',
                'library' => 'sector',
                'preview' => 'canteen',
                'sector_tr' => 'Sosyal tesis',
                'sector_en' => 'Canteen / social facility',
                'name_tr' => 'Günün menüsü',
                'name_en' => 'Menu of the day',
                'description_tr' => 'Kurumsal lokanta: günün menü tahtası, tepsi sıra — yemekhane.',
                'description_en' => 'Daily menu board, tray queue — institutional canteen.',
                'swatches' => ['#f1f5f9', '#0891b2', '#f59e0b'],
                'font' => 'Inter:wght@400;600;700;800',
                'font_family' => "'Inter', ui-sans-serif, sans-serif",
                'intensity' => 'light',
            ],
            'sector_nightbar' => [
                'key' => 'sector_nightbar',
                'library' => 'sector',
                'preview' => 'neon',
                'sector_tr' => 'Bar & gece',
                'sector_en' => 'Bar & nightlife',
                'name_tr' => 'Neon tabela',
                'name_en' => 'Neon sign',
                'description_tr' => 'Tuğla duvar, neon tabela, şişe siluetleri — bar, kokteyl, gece.',
                'description_en' => 'Brick wall, neon sign, bottle silhouettes — cocktail bar.',
                'swatches' => ['#0a0118', '#a855f7', '#22d3ee'],
                'font' => 'Caveat:wght@400;600;700&family=Space+Grotesk:wght@500;700',
                'font_family' => "'Space Grotesk', 'Caveat', ui-sans-serif, sans-serif",
                'intensity' => 'dark',
            ],
        ];

        return array_merge($base, $sectors);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function generalLibrary(): array
    {
        return array_filter(self::all(), static fn ($t) => ($t['library'] ?? 'general') === 'general');
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function sectorLibrary(): array
    {
        return array_filter(self::all(), static fn ($t) => ($t['library'] ?? 'general') === 'sector');
    }

    /**
     * Admin kartı önizlemesi — her tema için tamamen farklı SVG kompozisyonu.
     *
     * `preview` anahtarı kompozisyonu seçer. Üretilen SVG `display:block` +
     * `position:absolute` ile stilenir, böylece `overflow-hidden` container'ı
     * her zaman doldurur (boş/kopuk görünme sorunu çözüldü).
     */
    public static function previewSvg(array $t): string
    {
        $sw = $t['swatches'] ?? ['#0f172a', '#f97316', '#ffffff'];
        $a = htmlspecialchars((string) ($sw[0] ?? '#0f172a'), ENT_QUOTES, 'UTF-8');
        $b = htmlspecialchars((string) ($sw[1] ?? '#f97316'), ENT_QUOTES, 'UTF-8');
        $c = htmlspecialchars((string) ($sw[2] ?? '#ffffff'), ENT_QUOTES, 'UTF-8');
        $kind = (string) ($t['preview'] ?? 'panels');
        $key = preg_replace('/[^a-z0-9_-]/i', '', (string) ($t['key'] ?? 'theme'));
        $gid = 'qdpg_' . $key;

        $style = 'display:block;position:absolute;inset:0;width:100%;height:100%';
        $svgOpen = '<svg viewBox="0 0 160 100" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="xMidYMid slice" style="' . $style . '">';
        $svgClose = '</svg>';

        switch ($kind) {
            // ========== GENERAL ==========
            case 'panels':
                return $svgOpen
                    . '<defs><linearGradient id="' . $gid . '" x1="0" x2="1" y1="0" y2="1"><stop offset="0" stop-color="' . $a . '"/><stop offset="1" stop-color="' . $b . '"/></linearGradient></defs>'
                    . '<rect width="160" height="100" fill="url(#' . $gid . ')"/>'
                    . '<rect x="10" y="14" width="60" height="72" rx="8" fill="rgba(255,255,255,0.10)" stroke="rgba(255,255,255,0.35)" stroke-width="0.6"/>'
                    . '<rect x="78" y="14" width="72" height="30" rx="4" fill="rgba(255,255,255,0.08)"/>'
                    . '<rect x="78" y="50" width="72" height="36" rx="4" fill="' . $c . '" opacity="0.12"/>'
                    . '<rect x="22" y="32" width="36" height="36" rx="4" fill="' . $c . '"/>'
                    . $svgClose;

            case 'paper':
                return $svgOpen
                    . '<rect width="160" height="100" fill="' . $a . '"/>'
                    . '<rect x="8" y="8" width="144" height="84" rx="6" fill="' . $c . '"/>'
                    . '<rect x="14" y="18" width="132" height="1" fill="' . $b . '" opacity="0.6"/>'
                    . '<text x="80" y="42" text-anchor="middle" font-family="Georgia, serif" font-size="14" font-style="italic" fill="' . $a . '">Maison</text>'
                    . '<rect x="62" y="50" width="36" height="36" fill="' . $a . '" opacity="0.12"/>'
                    . '<rect x="64" y="52" width="32" height="32" fill="' . $a . '"/>'
                    . $svgClose;

            case 'marquee':
                return $svgOpen
                    . '<rect width="160" height="100" fill="' . $a . '"/>'
                    . '<rect x="-10" y="10" width="180" height="14" fill="' . $b . '" transform="rotate(-3 80 17)"/>'
                    . '<rect x="-10" y="76" width="180" height="12" fill="' . $c . '" transform="rotate(3 80 82)"/>'
                    . '<rect x="40" y="34" width="80" height="40" rx="6" fill="rgba(255,255,255,0.08)" stroke="' . $b . '" stroke-width="1"/>'
                    . '<rect x="56" y="42" width="48" height="24" fill="' . $c . '"/>'
                    . $svgClose;

            case 'minimal':
                return $svgOpen
                    . '<rect width="160" height="100" fill="' . $a . '"/>'
                    . '<rect x="18" y="18" width="124" height="64" rx="3" fill="none" stroke="' . $b . '" stroke-width="0.6"/>'
                    . '<line x1="30" y1="40" x2="90" y2="40" stroke="' . $b . '" stroke-width="1"/>'
                    . '<line x1="30" y1="50" x2="70" y2="50" stroke="' . $b . '" stroke-width="0.5" opacity="0.6"/>'
                    . '<rect x="110" y="36" width="28" height="28" fill="' . $b . '"/>'
                    . $svgClose;

            case 'aurora':
                return $svgOpen
                    . '<defs><radialGradient id="' . $gid . '" cx="50%" cy="0%" r="70%"><stop offset="0%" stop-color="' . $b . '"/><stop offset="60%" stop-color="' . $a . '"/><stop offset="100%" stop-color="' . $a . '"/></radialGradient></defs>'
                    . '<rect width="160" height="100" fill="url(#' . $gid . ')"/>'
                    . '<circle cx="30" cy="22" r="24" fill="' . $b . '" opacity="0.55"/>'
                    . '<circle cx="130" cy="80" r="28" fill="' . $c . '" opacity="0.35"/>'
                    . '<rect x="52" y="32" width="56" height="42" rx="10" fill="rgba(255,255,255,0.1)" stroke="rgba(255,255,255,0.25)"/>'
                    . '<rect x="66" y="42" width="28" height="24" fill="' . $c . '"/>'
                    . $svgClose;

            case 'noir':
                return $svgOpen
                    . '<rect width="160" height="100" fill="' . $a . '"/>'
                    . '<circle cx="140" cy="-10" r="70" fill="' . $b . '" opacity="0.18"/>'
                    . '<rect x="18" y="32" width="124" height="46" rx="6" fill="rgba(148,163,184,0.08)" stroke="rgba(148,163,184,0.25)"/>'
                    . '<text x="30" y="52" font-family="Georgia, serif" font-size="12" fill="' . $c . '">CLUB</text>'
                    . '<rect x="104" y="40" width="30" height="30" fill="' . $c . '" opacity="0.9"/>'
                    . $svgClose;

            // ========== SECTOR — all food/drink, all unique ==========
            case 'chalkboard':
                return $svgOpen
                    . '<rect width="160" height="100" fill="#5a3a1b"/>'
                    . '<rect x="6" y="6" width="148" height="88" rx="6" fill="' . $a . '"/>'
                    . '<text x="80" y="20" text-anchor="middle" font-family="Georgia, serif" font-style="italic" font-size="9" fill="' . $b . '">Today\'s menu</text>'
                    . '<line x1="16" y1="28" x2="70" y2="28" stroke="#fff7ed" stroke-width="0.5" stroke-dasharray="2,2"/>'
                    . '<line x1="16" y1="38" x2="70" y2="38" stroke="#fff7ed" stroke-width="0.5" stroke-dasharray="2,2"/>'
                    . '<line x1="16" y1="48" x2="70" y2="48" stroke="#fff7ed" stroke-width="0.5" stroke-dasharray="2,2"/>'
                    . '<line x1="16" y1="58" x2="70" y2="58" stroke="#fff7ed" stroke-width="0.5" stroke-dasharray="2,2"/>'
                    . '<line x1="16" y1="68" x2="70" y2="68" stroke="#fff7ed" stroke-width="0.5" stroke-dasharray="2,2"/>'
                    . '<rect x="86" y="28" width="58" height="58" rx="3" fill="#fdf3d9"/>'
                    . '<rect x="94" y="36" width="42" height="42" fill="' . $a . '"/>'
                    // fork & knife silhouette
                    . '<path d="M14 80 l4-2 2 4 -4 2 z" fill="' . $b . '" opacity="0.6"/>'
                    . '<rect x="22" y="76" width="12" height="2" fill="' . $b . '" opacity="0.5"/>'
                    // candle flame on card
                    . '<ellipse cx="115" cy="24" rx="2" ry="3" fill="' . $b . '"/>'
                    . '<rect x="114" y="24" width="2" height="4" fill="#fbbf24"/>'
                    . $svgClose;

            case 'receipt':
                return $svgOpen
                    . '<rect width="160" height="100" fill="#1c1410"/>'
                    // steam blob
                    . '<circle cx="20" cy="20" r="18" fill="' . $b . '" opacity="0.15"/>'
                    . '<circle cx="140" cy="80" r="20" fill="#78350f" opacity="0.25"/>'
                    // receipt paper
                    . '<rect x="44" y="4" width="72" height="92" fill="#fffdf4"/>'
                    // torn edges
                    . '<path d="M44 96 L46 100 L48 96 L50 100 L52 96 L54 100 L56 96 L58 100 L60 96 L62 100 L64 96 L66 100 L68 96 L70 100 L72 96 L74 100 L76 96 L78 100 L80 96 L82 100 L84 96 L86 100 L88 96 L90 100 L92 96 L94 100 L96 96 L98 100 L100 96 L102 100 L104 96 L106 100 L108 96 L110 100 L112 96 L114 100 L116 96 L116 96" fill="#1c1410"/>'
                    . '<text x="80" y="20" text-anchor="middle" font-family="Georgia, serif" font-size="9" fill="#2a1a0f">CAFÉ</text>'
                    . '<line x1="50" y1="26" x2="110" y2="26" stroke="#2a1a0f" stroke-dasharray="2,1" opacity="0.5"/>'
                    . '<rect x="54" y="32" width="52" height="26" fill="#fffdf4" stroke="#2a1a0f"/>'
                    . '<rect x="58" y="36" width="44" height="18" fill="#2a1a0f"/>'
                    // barcode
                    . '<g transform="translate(52 64)" fill="#2a1a0f">'
                    . '<rect x="0" width="2" height="12"/><rect x="4" width="1" height="12"/><rect x="7" width="3" height="12"/><rect x="12" width="1" height="12"/><rect x="15" width="2" height="12"/><rect x="19" width="3" height="12"/><rect x="24" width="1" height="12"/><rect x="27" width="2" height="12"/><rect x="31" width="3" height="12"/><rect x="36" width="1" height="12"/><rect x="39" width="2" height="12"/><rect x="43" width="3" height="12"/><rect x="48" width="2" height="12"/><rect x="52" width="1" height="12"/>'
                    . '</g>'
                    // coffee bean
                    . '<ellipse cx="28" cy="56" rx="7" ry="4" fill="#3e2a1a" transform="rotate(-20 28 56)"/>'
                    . '<path d="M22 56 L34 56" stroke="#1c100a" stroke-width="0.8" transform="rotate(-20 28 56)"/>'
                    . '<ellipse cx="132" cy="32" rx="7" ry="4" fill="#3e2a1a" transform="rotate(22 132 32)"/>'
                    . $svgClose;

            case 'recipe':
                return $svgOpen
                    . '<rect width="160" height="100" fill="' . $a . '"/>'
                    . '<rect x="8" y="8" width="144" height="84" rx="4" fill="#fffdf6"/>'
                    . '<g stroke="#94a3b8" stroke-width="0.3" opacity="0.5">'
                    . '<line x1="20" y1="22" x2="150" y2="22"/><line x1="20" y1="32" x2="150" y2="32"/><line x1="20" y1="42" x2="150" y2="42"/><line x1="20" y1="52" x2="150" y2="52"/><line x1="20" y1="62" x2="150" y2="62"/><line x1="20" y1="72" x2="150" y2="72"/><line x1="20" y1="82" x2="150" y2="82"/>'
                    . '</g>'
                    . '<line x1="20" y1="10" x2="20" y2="92" stroke="#ef4444" stroke-width="1" opacity="0.6"/>'
                    // punch holes
                    . '<circle cx="14" cy="22" r="1.4" fill="' . $a . '"/><circle cx="14" cy="50" r="1.4" fill="' . $a . '"/><circle cx="14" cy="78" r="1.4" fill="' . $a . '"/>'
                    . '<text x="26" y="24" font-family="cursive" font-weight="700" font-size="10" fill="' . $c . '">Grandma\'s recipe</text>'
                    . '<text x="26" y="40" font-family="serif" font-size="6" fill="#3e2a17">· 4 cups love</text>'
                    . '<text x="26" y="50" font-family="serif" font-size="6" fill="#3e2a17">· 2 tbsp warmth</text>'
                    . '<text x="26" y="60" font-family="serif" font-size="6" fill="#3e2a17">· golden crust</text>'
                    // baker stamp circle
                    . '<circle cx="122" cy="56" r="22" fill="#fff7e6" stroke="' . $b . '" stroke-width="2"/>'
                    . '<circle cx="122" cy="56" r="19" fill="none" stroke="' . $b . '" stroke-width="0.6" stroke-dasharray="1,1"/>'
                    . '<rect x="110" y="44" width="24" height="24" fill="' . $c . '"/>'
                    // wheat decor
                    . '<path d="M138 14 l2 8 -2 2 -2 -2 z" fill="' . $b . '" opacity="0.7"/>'
                    . '<path d="M138 22 l3 6 -3 2 -3 -2 z" fill="' . $b . '" opacity="0.7"/>'
                    . $svgClose;

            case 'pizzeria':
                return $svgOpen
                    // red-white checker
                    . '<defs>'
                    . '<pattern id="' . $gid . '" x="0" y="0" width="20" height="20" patternUnits="userSpaceOnUse">'
                    . '<rect width="10" height="10" fill="' . $b . '"/><rect x="10" y="10" width="10" height="10" fill="' . $b . '"/>'
                    . '<rect x="10" width="10" height="10" fill="' . $a . '"/><rect y="10" width="10" height="10" fill="' . $a . '"/>'
                    . '</pattern>'
                    . '</defs>'
                    . '<rect width="160" height="100" fill="url(#' . $gid . ')"/>'
                    // Italian flag ribbon
                    . '<rect x="0" y="0" width="160" height="12" fill="#f1f5f9"/>'
                    . '<rect x="0" y="0" width="53" height="12" fill="' . $c . '"/>'
                    . '<rect x="107" y="0" width="53" height="12" fill="' . $b . '"/>'
                    . '<text x="80" y="9" text-anchor="middle" font-family="Georgia, serif" font-size="6" font-weight="700" fill="#0f172a" letter-spacing="2">TRATTORIA</text>'
                    // pizza slice on left
                    . '<g transform="translate(18 38) rotate(-10)">'
                    . '<path d="M0 36 L40 0 L40 36 Z" fill="#fde68a" stroke="#b45309" stroke-width="1"/>'
                    . '<path d="M4 34 L38 4 L38 34 Z" fill="#fbbf24"/>'
                    . '<circle cx="16" cy="24" r="2.5" fill="#b91c1c"/>'
                    . '<circle cx="26" cy="16" r="2.5" fill="#b91c1c"/>'
                    . '<circle cx="30" cy="28" r="2" fill="#166534"/>'
                    . '<circle cx="18" cy="30" r="1.6" fill="#166534"/>'
                    . '</g>'
                    // menu card
                    . '<rect x="78" y="24" width="74" height="62" rx="4" fill="#fffaf0" stroke="' . $b . '" stroke-width="1"/>'
                    . '<text x="115" y="38" text-anchor="middle" font-family="Georgia, serif" font-size="8" font-style="italic" fill="' . $b . '">Menu</text>'
                    . '<line x1="86" y1="42" x2="144" y2="42" stroke="' . $b . '" stroke-width="0.4"/>'
                    . '<rect x="98" y="48" width="34" height="32" fill="' . $a . '"/>'
                    . $svgClose;

            case 'kebab':
                return $svgOpen
                    . '<rect width="160" height="100" fill="' . $a . '"/>'
                    // coal glow
                    . '<defs><radialGradient id="' . $gid . '" cx="50%" cy="100%" r="80%"><stop offset="0%" stop-color="' . $b . '" stop-opacity="0.75"/><stop offset="100%" stop-color="' . $a . '" stop-opacity="0"/></radialGradient></defs>'
                    . '<rect width="160" height="100" fill="url(#' . $gid . ')"/>'
                    // flames
                    . '<path d="M30 90 Q28 70 34 62 Q32 76 38 70 Q36 84 42 72 Q40 86 46 76 Q48 92 36 94 Z" fill="' . $b . '" opacity="0.9"/>'
                    . '<path d="M118 92 Q114 74 122 64 Q118 78 126 70 Q124 84 132 74 Q130 88 136 78 Q140 94 124 96 Z" fill="' . $b . '" opacity="0.85"/>'
                    . '<path d="M30 90 Q28 78 34 74 Q34 86 40 80 Q40 92 32 92 Z" fill="#facc15"/>'
                    // skewer
                    . '<rect x="20" y="40" width="120" height="3" fill="' . $c . '"/>'
                    . '<circle cx="16" cy="41.5" r="3" fill="' . $c . '"/>'
                    . '<circle cx="144" cy="41.5" r="3" fill="' . $c . '"/>'
                    // meat cubes on skewer
                    . '<rect x="36" y="34" width="14" height="14" rx="2" fill="#7f1d1d"/>'
                    . '<rect x="54" y="34" width="14" height="14" rx="2" fill="#78350f"/>'
                    . '<rect x="72" y="34" width="14" height="14" rx="2" fill="#7f1d1d"/>'
                    . '<rect x="90" y="34" width="14" height="14" rx="2" fill="#78350f"/>'
                    . '<rect x="108" y="34" width="14" height="14" rx="2" fill="#7f1d1d"/>'
                    // QR "ember" card
                    . '<rect x="54" y="54" width="52" height="36" rx="3" fill="#0a0a0a" stroke="' . $b . '" stroke-width="0.8"/>'
                    . '<rect x="66" y="60" width="28" height="24" fill="' . $c . '"/>'
                    . '<text x="80" y="16" text-anchor="middle" font-family="Georgia, serif" font-size="9" font-weight="700" fill="' . $c . '" letter-spacing="3">OCAKBAŞI</text>'
                    . $svgClose;

            case 'breakfast':
                return $svgOpen
                    . '<defs><linearGradient id="' . $gid . '" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="#fef9c3"/><stop offset="1" stop-color="' . $a . '"/></linearGradient></defs>'
                    . '<rect width="160" height="100" fill="url(#' . $gid . ')"/>'
                    // sun
                    . '<circle cx="30" cy="22" r="10" fill="' . $b . '"/>'
                    . '<g stroke="' . $b . '" stroke-width="1.2" stroke-linecap="round">'
                    . '<line x1="30" y1="6" x2="30" y2="10"/><line x1="30" y1="34" x2="30" y2="38"/><line x1="14" y1="22" x2="18" y2="22"/><line x1="42" y1="22" x2="46" y2="22"/><line x1="19" y1="11" x2="21" y2="13"/><line x1="39" y1="11" x2="41" y2="13"/><line x1="19" y1="33" x2="21" y2="31"/><line x1="39" y1="33" x2="41" y2="31"/>'
                    . '</g>'
                    // small plates grid
                    . '<g>'
                    // plate 1 tomato
                    . '<circle cx="70" cy="30" r="9" fill="#fff"/><circle cx="70" cy="30" r="5" fill="#ef4444"/>'
                    // plate 2 cucumber
                    . '<circle cx="90" cy="30" r="9" fill="#fff"/><ellipse cx="90" cy="30" rx="5" ry="3" fill="' . $c . '"/>'
                    // plate 3 olive
                    . '<circle cx="110" cy="30" r="9" fill="#fff"/><circle cx="110" cy="30" r="3" fill="#166534"/>'
                    // plate 4 cheese
                    . '<circle cx="130" cy="30" r="9" fill="#fff"/><rect x="126" y="26" width="8" height="8" fill="#fef3c7"/>'
                    // plate 5 honey
                    . '<circle cx="70" cy="52" r="9" fill="#fff"/><rect x="66" y="48" width="8" height="8" fill="#fbbf24"/>'
                    // plate 6 jam
                    . '<circle cx="90" cy="52" r="9" fill="#fff"/><rect x="86" y="48" width="8" height="8" fill="#ec4899"/>'
                    // plate 7 egg
                    . '<circle cx="110" cy="52" r="9" fill="#fff"/><circle cx="110" cy="52" r="4" fill="#fef9c3"/><circle cx="110" cy="52" r="2" fill="#f59e0b"/>'
                    // plate 8 bread
                    . '<circle cx="130" cy="52" r="9" fill="#fff"/><rect x="125" y="48" width="10" height="8" rx="2" fill="#d97706"/>'
                    . '</g>'
                    // tea glass (ince belli)
                    . '<g transform="translate(22 52)">'
                    . '<path d="M0 0 L14 0 L13 6 L8 10 L8 16 L13 20 L14 26 L0 26 L1 20 L6 16 L6 10 L1 6 Z" fill="#7f1d1d"/>'
                    . '<path d="M1 1 L13 1 L12 6 L7.5 10 L7.5 16 L12 20 L13 25 L1 25 L2 20 L6.5 16 L6.5 10 L2 6 Z" fill="#991b1b"/>'
                    . '</g>'
                    // qr mini card
                    . '<rect x="60" y="72" width="80" height="22" rx="3" fill="#fff" stroke="' . $b . '" stroke-width="0.6"/>'
                    . '<rect x="66" y="76" width="14" height="14" fill="' . $a . '"/>'
                    . '<text x="122" y="86" text-anchor="middle" font-family="cursive" font-size="9" fill="' . $b . '">serpme kahvaltı</text>'
                    . $svgClose;

            case 'seafood':
                return $svgOpen
                    . '<defs><linearGradient id="' . $gid . '" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="' . $a . '"/><stop offset="1" stop-color="#082f49"/></linearGradient></defs>'
                    . '<rect width="160" height="100" fill="url(#' . $gid . ')"/>'
                    // waves
                    . '<g fill="none" stroke="' . $b . '" stroke-width="1.2" opacity="0.6">'
                    . '<path d="M0 40 Q20 34 40 40 T80 40 T120 40 T160 40"/>'
                    . '<path d="M0 48 Q20 42 40 48 T80 48 T120 48 T160 48"/>'
                    . '<path d="M0 56 Q20 50 40 56 T80 56 T120 56 T160 56"/>'
                    . '</g>'
                    // sun on horizon
                    . '<circle cx="130" cy="24" r="12" fill="' . $c . '" opacity="0.9"/>'
                    // rope border
                    . '<g stroke="' . $b . '" stroke-width="1.5" stroke-dasharray="3,2" fill="none">'
                    . '<rect x="6" y="6" width="148" height="88" rx="4"/>'
                    . '</g>'
                    // fish silhouette
                    . '<g transform="translate(20 72)">'
                    . '<path d="M0 6 Q12 -2 26 6 Q12 14 0 6 Z" fill="' . $b . '"/>'
                    . '<path d="M26 6 L34 0 L34 12 Z" fill="' . $b . '"/>'
                    . '<circle cx="6" cy="5" r="1" fill="' . $a . '"/>'
                    . '</g>'
                    // anchor
                    . '<g transform="translate(60 64)" stroke="' . $c . '" stroke-width="1.2" fill="none">'
                    . '<line x1="8" y1="0" x2="8" y2="18"/>'
                    . '<circle cx="8" cy="2" r="2"/>'
                    . '<path d="M0 14 Q8 24 16 14"/>'
                    . '<line x1="2" y1="8" x2="14" y2="8"/>'
                    . '</g>'
                    // QR on white card
                    . '<rect x="92" y="58" width="44" height="32" rx="3" fill="#fff" stroke="' . $b . '" stroke-width="0.6"/>'
                    . '<rect x="98" y="62" width="24" height="24" fill="' . $a . '"/>'
                    . '<text x="80" y="22" text-anchor="middle" font-family="Georgia, serif" font-style="italic" font-size="11" fill="' . $c . '">Iskele · balıkçı</text>'
                    . $svgClose;

            case 'icecream':
                return $svgOpen
                    . '<defs><linearGradient id="' . $gid . '" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="' . $a . '"/><stop offset="1" stop-color="#fbcfe8"/></linearGradient></defs>'
                    . '<rect width="160" height="100" fill="url(#' . $gid . ')"/>'
                    // sprinkles
                    . '<g>'
                    . '<rect x="12" y="10" width="4" height="1.5" rx="0.7" fill="' . $b . '" transform="rotate(20 14 11)"/>'
                    . '<rect x="140" y="16" width="4" height="1.5" rx="0.7" fill="' . $c . '" transform="rotate(-15 142 17)"/>'
                    . '<rect x="24" y="86" width="4" height="1.5" rx="0.7" fill="' . $b . '" transform="rotate(40 26 87)"/>'
                    . '<rect x="136" y="80" width="4" height="1.5" rx="0.7" fill="#a78bfa" transform="rotate(-30 138 81)"/>'
                    . '<rect x="48" y="12" width="4" height="1.5" rx="0.7" fill="#facc15" transform="rotate(60 50 13)"/>'
                    . '<rect x="100" y="8" width="4" height="1.5" rx="0.7" fill="#34d399"/>'
                    . '</g>'
                    // cone left
                    . '<g transform="translate(22 28)">'
                    . '<path d="M0 30 L20 30 L10 58 Z" fill="#d97706"/>'
                    . '<path d="M2 30 L4 58 M6 30 L8 58 M10 30 L12 58 M14 30 L16 58 M18 30 L16 58" stroke="#78350f" stroke-width="0.6"/>'
                    // scoops: 3
                    . '<circle cx="10" cy="24" r="10" fill="' . $b . '"/>'
                    . '<circle cx="6" cy="16" r="8" fill="#fde68a"/>'
                    . '<circle cx="14" cy="8" r="6" fill="' . $c . '"/>'
                    // cherry
                    . '<circle cx="14" cy="3" r="2" fill="#ef4444"/>'
                    . '<path d="M14 1 L16 -3" stroke="#166534" stroke-width="0.8" fill="none"/>'
                    . '</g>'
                    // QR card like a gelato ticket
                    . '<rect x="66" y="26" width="82" height="56" rx="8" fill="#fff" stroke="' . $b . '" stroke-width="1.2"/>'
                    . '<text x="107" y="40" text-anchor="middle" font-family="cursive" font-weight="700" font-size="11" fill="' . $b . '">Gelato</text>'
                    . '<line x1="74" y1="44" x2="140" y2="44" stroke="' . $b . '" stroke-width="0.4" stroke-dasharray="2,1"/>'
                    . '<rect x="90" y="50" width="34" height="28" fill="' . $a . '"/>'
                    . '<text x="107" y="90" text-anchor="middle" font-family="cursive" font-size="8" fill="' . $c . '">scoop & smile</text>'
                    . $svgClose;

            case 'teahouse':
                return $svgOpen
                    . '<rect width="160" height="100" fill="' . $a . '"/>'
                    // kilim pattern band (top)
                    . '<g>'
                    . '<rect x="0" y="0" width="160" height="14" fill="' . $c . '" opacity="0.4"/>'
                    . '<g fill="' . $b . '">'
                    . '<polygon points="8,2 14,8 8,14 2,8"/>'
                    . '<polygon points="28,2 34,8 28,14 22,8"/>'
                    . '<polygon points="48,2 54,8 48,14 42,8"/>'
                    . '<polygon points="68,2 74,8 68,14 62,8"/>'
                    . '<polygon points="88,2 94,8 88,14 82,8"/>'
                    . '<polygon points="108,2 114,8 108,14 102,8"/>'
                    . '<polygon points="128,2 134,8 128,14 122,8"/>'
                    . '<polygon points="148,2 154,8 148,14 142,8"/>'
                    . '</g>'
                    . '</g>'
                    // kilim pattern band (bottom)
                    . '<g>'
                    . '<rect x="0" y="86" width="160" height="14" fill="' . $c . '" opacity="0.4"/>'
                    . '<g stroke="' . $b . '" stroke-width="0.8" fill="none">'
                    . '<path d="M0 93 L10 88 L20 93 L30 88 L40 93 L50 88 L60 93 L70 88 L80 93 L90 88 L100 93 L110 88 L120 93 L130 88 L140 93 L150 88 L160 93"/>'
                    . '</g>'
                    . '</g>'
                    // hanging lamp
                    . '<line x1="80" y1="14" x2="80" y2="24" stroke="' . $c . '" stroke-width="0.6"/>'
                    . '<path d="M72 30 Q80 20 88 30 L86 36 L74 36 Z" fill="' . $b . '"/>'
                    // ince belli tea glass left
                    . '<g transform="translate(22 42)">'
                    . '<path d="M0 0 L18 0 L17 8 L11 13 L11 22 L17 28 L18 36 L0 36 L1 28 L7 22 L7 13 L1 8 Z" fill="' . $c . '" opacity="0.35"/>'
                    . '<path d="M2 2 L16 2 L15 8 L10 13 L10 22 L15 28 L16 34 L2 34 L3 28 L8 22 L8 13 L3 8 Z" fill="' . $b . '" opacity="0.9"/>'
                    // saucer
                    . '<ellipse cx="9" cy="38" rx="14" ry="2" fill="' . $c . '"/>'
                    . '</g>'
                    // ince belli tea glass right
                    . '<g transform="translate(120 42)">'
                    . '<path d="M0 0 L18 0 L17 8 L11 13 L11 22 L17 28 L18 36 L0 36 L1 28 L7 22 L7 13 L1 8 Z" fill="' . $c . '" opacity="0.35"/>'
                    . '<path d="M2 2 L16 2 L15 8 L10 13 L10 22 L15 28 L16 34 L2 34 L3 28 L8 22 L8 13 L3 8 Z" fill="' . $b . '" opacity="0.9"/>'
                    . '<ellipse cx="9" cy="38" rx="14" ry="2" fill="' . $c . '"/>'
                    . '</g>'
                    // QR plaque
                    . '<rect x="56' . '" y="40" width="48" height="40" rx="3" fill="' . $c . '" stroke="' . $b . '" stroke-width="0.8"/>'
                    . '<rect x="64" y="46" width="32" height="28" fill="' . $a . '"/>'
                    . $svgClose;

            case 'foodtruck':
                return $svgOpen
                    . '<defs><linearGradient id="' . $gid . '" x1="0" x2="1" y1="0" y2="0"><stop offset="0" stop-color="' . $a . '"/><stop offset="0.5" stop-color="#1e293b"/><stop offset="1" stop-color="' . $a . '"/></linearGradient></defs>'
                    . '<rect width="160" height="100" fill="url(#' . $gid . ')"/>'
                    // sky / city skyline dots
                    . '<g fill="' . $b . '" opacity="0.6">'
                    . '<circle cx="16" cy="12" r="0.8"/><circle cx="40" cy="8" r="0.8"/><circle cx="70" cy="14" r="0.8"/><circle cx="110" cy="10" r="0.8"/><circle cx="140" cy="16" r="0.8"/>'
                    . '</g>'
                    // marquee lights top
                    . '<g fill="' . $b . '">'
                    . '<circle cx="10" cy="22" r="1.6"/><circle cx="26" cy="22" r="1.6"/><circle cx="42" cy="22" r="1.6"/><circle cx="58" cy="22" r="1.6"/><circle cx="74" cy="22" r="1.6"/><circle cx="90" cy="22" r="1.6"/><circle cx="106" cy="22" r="1.6"/><circle cx="122" cy="22" r="1.6"/><circle cx="138" cy="22" r="1.6"/><circle cx="150" cy="22" r="1.6"/>'
                    . '</g>'
                    // truck body
                    . '<rect x="20" y="30" width="120" height="48" rx="6" fill="' . $c . '"/>'
                    . '<rect x="20" y="30" width="120" height="12" rx="6" fill="' . $b . '"/>'
                    . '<text x="80" y="39" text-anchor="middle" font-family="Arial, sans-serif" font-weight="900" font-size="8" fill="' . $a . '" letter-spacing="2">FOOD TRUCK</text>'
                    // serving window
                    . '<rect x="36" y="48" width="48" height="22" rx="2" fill="' . $a . '" opacity="0.85"/>'
                    // chalk menu on right
                    . '<rect x="92" y="48" width="38" height="22" rx="2" fill="#1f2937"/>'
                    . '<line x1="96" y1="54" x2="126" y2="54" stroke="' . $c . '" stroke-width="0.4" stroke-dasharray="2,1" opacity="0.5"/>'
                    . '<line x1="96" y1="60" x2="120" y2="60" stroke="' . $c . '" stroke-width="0.4" stroke-dasharray="2,1" opacity="0.5"/>'
                    . '<line x1="96" y1="66" x2="124" y2="66" stroke="' . $c . '" stroke-width="0.4" stroke-dasharray="2,1" opacity="0.5"/>'
                    // QR "order card" hanging under window
                    . '<rect x="54" y="56" width="12" height="12" fill="#fff"/>'
                    . '<rect x="56" y="58" width="8" height="8" fill="' . $a . '"/>'
                    // wheels
                    . '<circle cx="44" cy="80" r="7" fill="#0f172a" stroke="#374151" stroke-width="1.5"/>'
                    . '<circle cx="44" cy="80" r="2" fill="#6b7280"/>'
                    . '<circle cx="116" cy="80" r="7" fill="#0f172a" stroke="#374151" stroke-width="1.5"/>'
                    . '<circle cx="116" cy="80" r="2" fill="#6b7280"/>'
                    // burger icon floating
                    . '<g transform="translate(132 50)">'
                    . '<ellipse cx="6" cy="2" rx="6" ry="2" fill="#d97706"/>'
                    . '<rect x="0" y="3" width="12" height="2" fill="#166534"/>'
                    . '<rect x="0" y="5" width="12" height="2" fill="#7f1d1d"/>'
                    . '<ellipse cx="6" cy="9" rx="6" ry="2" fill="#d97706"/>'
                    . '</g>'
                    . $svgClose;

            case 'canteen':
                return $svgOpen
                    . '<rect width="160" height="100" fill="' . $a . '"/>'
                    // wall tile pattern
                    . '<g stroke="' . $c . '" stroke-width="0.2" opacity="0.35">'
                    . '<line x1="0" y1="30" x2="160" y2="30"/><line x1="0" y1="60" x2="160" y2="60"/>'
                    . '<line x1="40" y1="0" x2="40" y2="60"/><line x1="80" y1="0" x2="80" y2="60"/><line x1="120" y1="0" x2="120" y2="60"/>'
                    . '</g>'
                    // top menu board
                    . '<rect x="10" y="8" width="90" height="60" rx="4" fill="#0f172a"/>'
                    . '<rect x="10" y="8" width="90" height="12" rx="4" fill="' . $b . '"/>'
                    . '<text x="55" y="17" text-anchor="middle" font-family="Arial, sans-serif" font-size="7" font-weight="800" fill="#fff" letter-spacing="3">GÜNÜN MENÜSÜ</text>'
                    // menu items (three rows)
                    . '<g font-family="Arial, sans-serif" font-size="5" fill="' . $c . '">'
                    . '<text x="16" y="30">· Mercimek çorbası</text><text x="92" y="30" text-anchor="end" fill="' . $b . '">45 ₺</text>'
                    . '<text x="16" y="40">· Et sote &amp; pirinç pilavı</text><text x="92" y="40" text-anchor="end" fill="' . $b . '">120 ₺</text>'
                    . '<text x="16" y="50">· Mevsim salatası</text><text x="92" y="50" text-anchor="end" fill="' . $b . '">35 ₺</text>'
                    . '<text x="16" y="60">· Sütlaç</text><text x="92" y="60" text-anchor="end" fill="' . $b . '">40 ₺</text>'
                    . '</g>'
                    // tray queue right
                    . '<g>'
                    // tray with food + QR
                    . '<rect x="108" y="12" width="44" height="30" rx="4" fill="#cbd5e1"/>'
                    . '<circle cx="118" cy="27" r="5" fill="#fde68a" stroke="' . $b . '" stroke-width="0.6"/>'
                    . '<circle cx="132" cy="27" r="5" fill="' . $b . '"/>'
                    . '<rect x="140" y="20" width="10" height="14" fill="#fff"/>'
                    . '<rect x="142" y="22" width="6" height="10" fill="' . $a . '"/>'
                    . '<rect x="108" y="46" width="44" height="30" rx="4" fill="#e2e8f0"/>'
                    . '<circle cx="118" cy="61" r="5" fill="#f87171"/>'
                    . '<circle cx="132" cy="61" r="5" fill="#86efac"/>'
                    . '<rect x="108" y="80" width="44" height="6" rx="2" fill="#94a3b8"/>'
                    . '</g>'
                    // floor tile / rail
                    . '<rect x="10" y="74" width="90" height="16" rx="2" fill="#cbd5e1"/>'
                    . '<rect x="14" y="78" width="82" height="4" rx="1" fill="#94a3b8"/>'
                    . $svgClose;

            case 'neon':
                return $svgOpen
                    . '<defs><radialGradient id="' . $gid . '" cx="50%" cy="30%" r="70%"><stop offset="0%" stop-color="' . $b . '" stop-opacity="0.35"/><stop offset="100%" stop-color="' . $a . '"/></radialGradient></defs>'
                    . '<rect width="160" height="100" fill="url(#' . $gid . ')"/>'
                    . '<g stroke="rgba(255,255,255,0.06)" stroke-width="0.4">'
                    . '<line x1="0" y1="20" x2="160" y2="20"/><line x1="0" y1="60" x2="160" y2="60"/><line x1="40" y1="0" x2="40" y2="100"/><line x1="120" y1="0" x2="120" y2="100"/>'
                    . '</g>'
                    // chain
                    . '<line x1="80" y1="0" x2="80" y2="26" stroke="rgba(255,255,255,0.4)" stroke-dasharray="2,2"/>'
                    // sign
                    . '<rect x="48" y="26" width="64" height="56" rx="10" fill="rgba(0,0,0,0.4)" stroke="' . $b . '" stroke-width="1.5"/>'
                    . '<text x="80" y="46" text-anchor="middle" font-family="cursive" font-size="15" fill="' . $b . '">BAR</text>'
                    . '<rect x="64" y="52" width="32" height="26" fill="' . $c . '"/>'
                    // bottles
                    . '<rect x="12" y="66" width="4" height="26" rx="1" fill="' . $c . '" opacity="0.4"/>'
                    . '<rect x="144" y="66" width="4" height="26" rx="1" fill="' . $c . '" opacity="0.4"/>'
                    . '<rect x="13" y="60" width="2" height="8" fill="' . $c . '" opacity="0.5"/>'
                    . '<rect x="145" y="60" width="2" height="8" fill="' . $c . '" opacity="0.5"/>'
                    . $svgClose;
        }

        // Fallback
        return $svgOpen
            . '<rect width="160" height="100" fill="' . $a . '"/>'
            . '<rect x="14" y="18" width="60" height="64" rx="6" fill="' . $c . '" opacity="0.14"/>'
            . '<rect x="82" y="18" width="64" height="28" rx="3" fill="rgba(255,255,255,0.09)"/>'
            . '<rect x="82" y="52" width="64" height="30" rx="3" fill="rgba(255,255,255,0.07)"/>'
            . $svgClose;
    }

    /**
     * themes/{key}.php dosyası yoksa varsayılana düşer.
     */
    public static function templateKey(array $theme): string
    {
        $k = strtolower((string) ($theme['key'] ?? self::DEFAULT));
        $all = self::all();
        if (isset($all[$k])) {
            return $k;
        }
        $ext = isset($theme['extends']) ? strtolower(trim((string) $theme['extends'])) : '';
        if ($ext !== '' && isset($all[$ext])) {
            return $ext;
        }
        return self::DEFAULT;
    }

    public static function resolve(?string $key): array
    {
        $all = self::all();
        $key = strtolower((string) $key);
        return $all[$key] ?? $all[self::DEFAULT];
    }

    /**
     * @return string[]
     */
    public static function keys(): array
    {
        return array_keys(self::all());
    }
}
