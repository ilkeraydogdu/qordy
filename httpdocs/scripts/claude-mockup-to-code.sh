#!/usr/bin/env bash
# Qordy — Claude Code CLI: mockup → React/Tailwind implementation
# Usage:
#   ./scripts/claude-mockup-to-code.sh gap          # read-only gap analysis
#   ./scripts/claude-mockup-to-code.sh consult      # design Q&A (no edits)
#   ./scripts/claude-mockup-to-code.sh hero       # implement hero + stats only
#   ./scripts/claude-mockup-to-code.sh landing    # full landing page pass
#   ./scripts/claude-mockup-to-code.sh interactive # open TUI session with images
#
# Requirements: claude CLI (claude auth login or ANTHROPIC_API_KEY)

set -euo pipefail

ROOT="/var/www/vhosts/qordy.com/httpdocs"
FRONTEND="$ROOT/frontend"
MOCKUP_LIGHT="$ROOT/webui1.png"
MOCKUP_DARK="$ROOT/webui2.png"

# Vision + code quality: opus. Faster iteration: sonnet.
MODEL="${CLAUDE_MODEL:-opus}"
EFFORT="${CLAUDE_EFFORT:-high}"

MODE="${1:-gap}"

if ! command -v claude >/dev/null 2>&1; then
  echo "ERROR: claude CLI not found. Install: https://docs.anthropic.com/en/docs/claude-code" >&2
  exit 1
fi

for f in "$MOCKUP_LIGHT" "$MOCKUP_DARK"; do
  if [[ ! -f "$f" ]]; then
    echo "ERROR: Mockup not found: $f" >&2
    exit 1
  fi
done

cd "$FRONTEND"

COMMON_FLAGS=(
  --model "$MODEL"
  --effort "$EFFORT"
  --add-dir "$ROOT"
  --add-dir "$FRONTEND"
)

# Interactive: approve edits per prompt. Print (-p): non-interactive.
run_print() {
  claude -p "$1" "${COMMON_FLAGS[@]}" "${@:2}" </dev/null
}

run_interactive() {
  claude "$1" "${COMMON_FLAGS[@]}" "${@:2}"
}

IMAGE_BLOCK="Light mockup (gündüz): $MOCKUP_LIGHT
Dark mockup (gece): $MOCKUP_DARK

Her iki görseli Read aracıyla aç ve piksel düzeyinde incele."

STACK_CONTEXT="Proje: Qordy — restoran/franchise SaaS (POS, QR menü, mutfak, stok, finans).
Stack: React 18 + Vite + TypeScript + Tailwind CSS + Framer Motion.
Tasarım tokenları: frontend/tailwind.config.js ve frontend/src/index.css (violet #6D5DF6, ink scale).
Ana sayfa: frontend/src/pages/LandingPage.tsx
Kurallar: Mevcut token isimlerini koru (amber/* aslında violet). Fraunces sadece hero display için opsiyonel; mockup Inter kullanıyor.
Light + dark tema: mockuptaki iki modu destekle (prefers-color-scheme veya data-theme toggle)."

case "$MODE" in
  gap|consult)
    run_print "$IMAGE_BLOCK

$STACK_CONTEXT

GÖREV (salt okunur — dosya değiştirme):
1. İki mockup arasındaki light/dark farklarını özetle (renk, yüzey, tipografi, glow).
2. Mockuptaki bölümleri listele (hero, dashboard preview, feature grid, mobil mockup, style guide).
3. Mevcut LandingPage.tsx + tailwind.config.js + index.css ile gap analizi yap (madde madde, öncelik: kritik / orta / düşük).
4. Uygulama planı: 3 aşamalı (token hizalama → hero/dashboard preview → light tema).

Sadece rapor yaz; kod yazma." \
      --allowedTools "Read,Grep,Glob"
    ;;

  hero)
    run_interactive "$IMAGE_BLOCK

$STACK_CONTEXT

GÖREV: Sadece hero + trust bar + stats satırını mockupa yaklaştır.
- Başlık: \"500+ Şubeyi Tek Panelden Yönetin\" (Yönetin vurgulu/gradient)
- CTA: \"Ücretsiz Demo Talep Et\" + \"Canlı İncele\"
- Trust: 15 dk kurulum, kredi kartı yok, 7/24 destek
- Stats: 500+ marka, 10.000+ kullanıcı, 250M+ işlem, %99.9 uptime
- Sağda dashboard preview kartı (mockuptaki gibi katmanlı)
- Dark modu varsayılan; light mod için CSS değişkenleri hazırla

Dosyalar: LandingPage.tsx, gerekirse küçük parça bileşenler src/components/landing/
Bitince: npm run build çalıştır ve hataları düzelt." \
      --allowedTools "Read,Edit,Write,Grep,Glob,Bash(npm run build),Bash(npm run dev)"
    ;;

  landing)
    run_interactive "$IMAGE_BLOCK

$STACK_CONTEXT

GÖREV: LandingPage.tsx'i mockupa göre yeniden düzenle (aşamalı, tek PR mantığı):
1. Navbar: Ürünler, Çözümler, Fiyatlandırma, Kaynaklar, Şirket + Giriş + Demo
2. Hero + stats (hero modundaki gibi)
3. Dashboard preview section (sidebar, 4 KPI, grafik placeholder'ları — Recharts yoksa CSS/SVG mock)
4. 6 feature kartı (QR Menü, POS, Mutfak, Stok, Finans, Franchise)
5. Mobil preview: garson + mutfak ekranı (statik mockup yeterli)
6. Footer trust bar
7. Light/dark tema toggle veya prefers-color-scheme

Mevcut ticker/persona bölümlerini mockup öncelikli ise kaldır veya birleştir.
Token'ları tailwind.config.js'den kullan; yeni hardcode hex minimum.
Bitince npm run build." \
      --allowedTools "Read,Edit,Write,Grep,Glob,Bash(npm run build),Bash(npm run dev)"
    ;;

  interactive|*)
    echo "Starting interactive Claude Code session in $FRONTEND"
    echo "Mockups: $MOCKUP_LIGHT | $MOCKUP_DARK"
    echo "Tip: paste this as your first message:"
    echo "---"
    cat <<EOF
$IMAGE_BLOCK

$STACK_CONTEXT

Mockuplara göre landing page'i uygula. Önce gap analizi yap, sonra onayım olmadan büyük silme yapma.
EOF
    echo "---"
    run_interactive "" \
      --allowedTools "Read,Edit,Write,Grep,Glob,Bash(npm run build),Bash(npm run dev)"
    ;;
esac
