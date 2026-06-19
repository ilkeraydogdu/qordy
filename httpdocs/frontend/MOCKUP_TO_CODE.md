# Mockup → Kod (Claude Code CLI)

Qordy landing mockupları (`webui1.png` gündüz, `webui2.png` gece) için Claude Code CLI iş akışı.

## Mockup özeti

| | Light (`webui1.png`) | Dark (`webui2.png`) |
|---|---|---|
| **Zemin** | `#F8FAFC` / beyaz kartlar | `#0B1020` / `#08090F` ink canvas |
| **Primary** | `#6366F1` indigo | `#6D5DF6` violet + cyan `#00D4FF` glow |
| **Metin** | `#0F172A` / `#64748B` | Beyaz başlık, `#E5E7EB` gövde |
| **Kartlar** | Beyaz, soft shadow | Glass + `border-white/10`, mor glow |
| **Butonlar** | Solid mor, `rounded-lg` (~8px) | Gradient mor, neon halo |
| **Tipografi** | Inter (H1 48px, H2 32px, body 16px) | Aynı scale |
| **Bölümler** | Hero, dashboard preview, 6 feature, mobil mockup, style guide | Aynı layout, dark skin |

## Mevcut kod vs mockup (gap)

**Zaten hizalı**
- Violet primary `#6D5DF6` → `tailwind.config.js` `brand`/`amber` ramp
- Inter font, ink neutral scale, card/button utility sınıfları
- Dark landing canvas (`bg-ink-950`, glass cards)

**Eksik / farklı (öncelik sırasıyla)**
1. **Light landing modu yok** — site varsayılan dark; mockup iki tam tema sunuyor
2. **Hero metni farklı** — mockup: *"500+ Şubeyi Tek Panelden Yönetin"*; kod: *"Restoranınız kendi işletim sistemini hak ediyor"*
3. **Dashboard preview section yok** — mockupta sidebar + 4 KPI + line/donut/bar chart + sipariş tablosu + Türkiye haritası
4. **Navbar basit** — mockup dropdown menüler (Ürünler, Çözümler…); kod anchor link (#features, #fiyat)
5. **Buton radius** — mockup `rounded-lg`; kod pill (`rounded-full` / `btn-base`)
6. **İstatistikler** — mockup 500+/10K/250M/%99.9; kod 1200+/14M sipariş/4.9 puan
7. **Mobil mockup bölümü** — garson + mutfak ekranı mockupta ayrı section; kod device composite hero içinde
8. **Style guide strip** — mockup altında renk/tipografi/button referansı; kodda yok
9. **Display font** — kod Fraunces italic hero; mockup düz Inter bold

## CLI kurulum

```bash
which claude && claude --version
# Beklenen: 2.x (test edildi: 2.1.158)

# Auth (birini seçin)
claude auth login          # Claude Pro/Max abonelik
# veya
export ANTHROPIC_API_KEY="sk-ant-..."
```

## Görsel gönderme (multimodal)

Claude Code CLI görseli **dosya yolu ile** okur (Read tool + vision). `-p` modunda da çalışır.

```bash
cd /var/www/vhosts/qordy.com/httpdocs/frontend

claude -p "Analyze:
Light: /var/www/vhosts/qordy.com/httpdocs/webui1.png
Dark:  /var/www/vhosts/qordy.com/httpdocs/webui2.png" \
  --model opus \
  --add-dir /var/www/vhosts/qordy.com/httpdocs \
  --allowedTools "Read" </dev/null
```

**Not:** Clipboard paste Linux/WSL'de güvenilir değil; scriptlerde mutlaka **absolute path** kullanın. `</dev/null` stdin uyarısını kaldırır.

## Önerilen model ve bayraklar

| Amaç | Model | Effort | Araçlar |
|------|-------|--------|---------|
| Gap analizi (salt okunur) | `opus` | `high` | `Read,Grep,Glob` |
| Hero / küçük parça | `opus` | `high` | `Read,Edit,Write,Grep,Glob,Bash(npm run build)` |
| Hızlı iterasyon | `sonnet` | `medium` | aynı |
| Tam landing rebuild | `opus` | `max` | Edit + build |

```bash
export CLAUDE_MODEL=opus      # veya sonnet
export CLAUDE_EFFORT=high     # low | medium | high | max
```

## Hazır script

```bash
chmod +x /var/www/vhosts/qordy.com/httpdocs/scripts/claude-mockup-to-code.sh

# Salt okunur gap raporu
./scripts/claude-mockup-to-code.sh gap

# İnteraktif oturum (TUI, onaylı düzenleme)
./scripts/claude-mockup-to-code.sh interactive

# Sadece hero + stats
./scripts/claude-mockup-to-code.sh hero

# Tam landing pass
./scripts/claude-mockup-to-code.sh landing
```

## Tek satır kopyala-yapıştır (gap analizi)

```bash
cd /var/www/vhosts/qordy.com/httpdocs/frontend && \
claude -p "$(cat <<'PROMPT'
Light mockup: /var/www/vhosts/qordy.com/httpdocs/webui1.png
Dark mockup:  /var/www/vhosts/qordy.com/httpdocs/webui2.png

Qordy restoran SaaS landing — React+Vite+TS+Tailwind.
Read both images and compare to src/pages/LandingPage.tsx, tailwind.config.js, src/index.css.
Output: (1) light vs dark summary TR, (2) section inventory, (3) prioritized gap list, (4) 3-step implementation plan.
Do not edit files.
PROMPT
)" --model opus --effort high \
  --add-dir /var/www/vhosts/qordy.com/httpdocs \
  --allowedTools "Read,Grep,Glob" </dev/null
```

## Tek satır — hero uygulama (interaktif)

```bash
cd /var/www/vhosts/qordy.com/httpdocs/frontend && \
claude "Light: /var/www/vhosts/qordy.com/httpdocs/webui1.png
Dark: /var/www/vhosts/qordy.com/httpdocs/webui2.png

Mockup hero'yu LandingPage.tsx'e uygula (başlık, CTA, stats, dashboard preview).
Stack: React+Tailwind. Token: tailwind.config.js. Bitince npm run build." \
  --model opus --effort high \
  --add-dir /var/www/vhosts/qordy.com/httpdocs \
  --allowedTools "Read,Edit,Write,Grep,Glob,Bash(npm run build)"
```

## Doğrulama adımları

```bash
cd /var/www/vhosts/qordy.com/httpdocs/frontend
npm run build          # TypeScript + Vite production build
npm run dev            # http://localhost:5173 — görsel kontrol
npm run deploy         # opsiyonel: production asset deploy
```

Kontrol listesi:
- [ ] Light ve dark modda hero okunabilirliği (kontrast)
- [ ] Mobile breakpoint (mockup desktop-first; `md:`/`lg:` grid)
- [ ] CTA linkleri `/register` ve demo formu
- [ ] Görseller `public/assets/landing/` altında lazy-load
- [ ] `npm run build` hatasız

## Prompt şablonu (Türkçe, tam sayfa)

```
Light mockup: /var/www/vhosts/qordy.com/httpdocs/webui1.png
Dark mockup:  /var/www/vhosts/qordy.com/httpdocs/webui2.png

Bağlam: Qordy — Türkiye restoran/franchise yönetim SaaS.
Stack: React 18, Vite, TypeScript, Tailwind, Framer Motion.
Dosyalar: src/pages/LandingPage.tsx, tailwind.config.js, src/index.css.

İstek:
1. Mockupları referans al; mevcut violet token sistemini koru (#6D5DF6).
2. Light + dark tema: data-theme veya class="dark" + CSS variables.
3. Bölümler: navbar dropdown, hero, stats, dashboard preview (KPI+chart mock), 6 feature card, mobil waiter/kitchen mockup, footer trust.
4. Chart için Recharts ekleme — CSS/SVG placeholder yeterli (bundle şişmesin).
5. Her bölüm sonrası npm run build.

Önce kısa gap listesi, sonra uygula.
```

## CLI test sonucu (2026-06-16)

- `claude` kurulu: `/root/.local/bin/claude` v2.1.158
- Auth: çalışıyor (`-p` ile yanıt alındı)
- Görsel yolu ile vision: **başarılı** (light/dark 3 cümle özet üretildi)
- Görsel olmadan prompt: "görsel bulunamadı" — path şart

## Blocker'lar

- Linux sunucuda **clipboard paste** (Ctrl+V görsel) çalışmaz → dosya yolu kullanın
- İlk `claude doctor` oturumu MCP health check nedeniyle yavaş olabilir (normal)
- Tam dashboard/charts production kalitesi için ayrı sprint (Recharts + gerçek data) gerekir
