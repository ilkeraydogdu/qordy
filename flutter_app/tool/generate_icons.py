#!/usr/bin/env python3
"""
Generate a corporate QORDY launcher icon set (adaptive + legacy) using PIL
only, with no external dependencies beyond Pillow.

Design rationale
----------------
* Brand palette mirrors web/mobile theme (#1F5AAB → #2B7AC9 deep-blue
  diagonal gradient).
* Monogram is a geometric Q — ring + angled rounded tail + inner spark dot
  — so it reads clearly even down to 48×48 px on a launcher grid.
* Two variants are exported at every dpi bucket:
    - ic_launcher.png           → legacy full icon (mask applied ourselves)
    - ic_launcher_foreground.png → adaptive layer (transparent outside a
      66 % safe zone so that Android 8+ round/squircle masks never crop
      the monogram).
* A high-resolution 1024 × 1024 PlayStore version is also emitted.
"""
from __future__ import annotations

import math
import os
from pathlib import Path

from PIL import Image, ImageDraw, ImageFilter

# --------------------------------------------------------------------------
# Paths
# --------------------------------------------------------------------------
ROOT = Path("/var/www/vhosts/qordy.com/flutter_app")
RES = ROOT / "android/app/src/main/res"
ASSETS = ROOT / "assets/icons"
ASSETS.mkdir(parents=True, exist_ok=True)

# Android mipmap size buckets: (folder, legacy size, foreground size).
# Adaptive foreground must be sized 108×108 dp → 432 px @ xxxhdpi.
BUCKETS = [
    ("mipmap-mdpi", 48, 108),
    ("mipmap-hdpi", 72, 162),
    ("mipmap-xhdpi", 96, 216),
    ("mipmap-xxhdpi", 144, 324),
    ("mipmap-xxxhdpi", 192, 432),
]

# Brand palette (matches lib/config/theme.dart → AppColors).
PRIMARY = (31, 90, 171, 255)       # #1F5AAB
PRIMARY_LIGHT = (43, 122, 201, 255) # #2B7AC9
PRIMARY_DARK = (26, 74, 140, 255)   # #1A4A8C
ACCENT = (96, 165, 250, 255)        # #60A5FA
WHITE = (255, 255, 255, 255)


# --------------------------------------------------------------------------
# Helpers
# --------------------------------------------------------------------------
def _vertical_gradient(size: int, top: tuple, bottom: tuple) -> Image.Image:
    """Linear top→bottom gradient with a subtle diagonal warp for depth."""
    img = Image.new("RGBA", (size, size), top)
    draw = ImageDraw.Draw(img)
    for y in range(size):
        t = y / (size - 1)
        r = int(top[0] + (bottom[0] - top[0]) * t)
        g = int(top[1] + (bottom[1] - top[1]) * t)
        b = int(top[2] + (bottom[2] - top[2]) * t)
        draw.line([(0, y), (size, y)], fill=(r, g, b, 255))

    # Add a diagonal highlight band for a premium sheen.
    overlay = Image.new("RGBA", (size, size), (0, 0, 0, 0))
    od = ImageDraw.Draw(overlay)
    for i in range(size):
        alpha = int(30 * math.sin(math.pi * (i / size)))
        if alpha > 0:
            od.line([(0, i), (i, 0)], fill=(255, 255, 255, alpha))
    img.alpha_composite(overlay)
    return img


def _rounded_mask(size: int, radius_pct: float) -> Image.Image:
    """Return a white L-mode mask of a rounded square."""
    radius = int(size * radius_pct)
    mask = Image.new("L", (size, size), 0)
    d = ImageDraw.Draw(mask)
    d.rounded_rectangle([(0, 0), (size - 1, size - 1)], radius=radius, fill=255)
    return mask


def _draw_q_monogram(size: int, ring_color=WHITE) -> Image.Image:
    """
    Draw the Q monogram on a transparent canvas sized `size × size`.

    Geometry (all normalised to canvas size):
      * ring outer diameter ≈ 74 % of canvas (centred)
      * ring stroke width   ≈ 11 % of canvas
      * tail: rounded bar from centre→bottom-right, same stroke, rotated 35°
      * inner spark dot     ≈ 10 % dia, positioned upper-right of centre
    """
    img = Image.new("RGBA", (size, size), (0, 0, 0, 0))
    d = ImageDraw.Draw(img)

    cx = size / 2
    cy = size / 2
    outer_d = size * 0.76
    stroke = size * 0.118

    # Outer ring (drawn as filled disc + hole) for perfectly smooth edges.
    disc = Image.new("RGBA", (size, size), (0, 0, 0, 0))
    dd = ImageDraw.Draw(disc)
    dd.ellipse(
        [
            (cx - outer_d / 2, cy - outer_d / 2),
            (cx + outer_d / 2, cy + outer_d / 2),
        ],
        fill=ring_color,
    )
    dd.ellipse(
        [
            (cx - outer_d / 2 + stroke, cy - outer_d / 2 + stroke),
            (cx + outer_d / 2 - stroke, cy + outer_d / 2 - stroke),
        ],
        fill=(0, 0, 0, 0),
    )
    img.alpha_composite(disc)

    # Tail: rounded rectangle, rotated so it "cuts through" the ring at ~38°.
    tail_len = size * 0.34
    tail_w = stroke * 1.02
    tail = Image.new("RGBA", (int(tail_len * 2), int(tail_len * 2)), (0, 0, 0, 0))
    td = ImageDraw.Draw(tail)
    tw, th = tail.size
    td.rounded_rectangle(
        [
            (tw / 2 - tail_len / 2, th / 2 - tail_w / 2),
            (tw / 2 + tail_len / 2, th / 2 + tail_w / 2),
        ],
        radius=tail_w / 2,
        fill=ring_color,
    )
    tail = tail.rotate(-38, resample=Image.BICUBIC)
    # Anchor the tail so its mid-point sits on the bottom-right of the ring.
    anchor_x = cx + (outer_d / 2) * math.cos(math.radians(38))
    anchor_y = cy + (outer_d / 2) * math.sin(math.radians(38))
    tx = int(anchor_x - tail.size[0] / 2)
    ty = int(anchor_y - tail.size[1] / 2)
    img.alpha_composite(tail, (tx, ty))

    # Accent spark dot — a subtle brand cue inside the ring, upper-right.
    spark_d = size * 0.10
    sx = cx + outer_d * 0.18
    sy = cy - outer_d * 0.18
    d.ellipse(
        [(sx - spark_d / 2, sy - spark_d / 2), (sx + spark_d / 2, sy + spark_d / 2)],
        fill=ACCENT,
    )

    return img


def render_legacy(size: int) -> Image.Image:
    """Full-frame legacy launcher icon: rounded gradient + monogram."""
    master = 1024
    bg = _vertical_gradient(master, PRIMARY_LIGHT, PRIMARY_DARK)

    # Add a soft radial glow to the top-left for depth.
    glow = Image.new("RGBA", (master, master), (0, 0, 0, 0))
    gd = ImageDraw.Draw(glow)
    for r in range(master, 0, -10):
        alpha = int(22 * (r / master))
        gd.ellipse(
            [(master * 0.2 - r / 2, master * 0.15 - r / 2),
             (master * 0.2 + r / 2, master * 0.15 + r / 2)],
            fill=(255, 255, 255, 0),
        )
    # Simpler: radial vignette highlight
    for i in range(40):
        alpha = int(3 + i * 0.8)
        gd.ellipse(
            [(master * 0.18 - i * 8, master * 0.10 - i * 8),
             (master * 0.32 + i * 8, master * 0.24 + i * 8)],
            outline=(255, 255, 255, max(0, 45 - i * 2)),
        )
    bg.alpha_composite(glow)

    mono = _draw_q_monogram(master)
    bg.alpha_composite(mono)

    # Soft inner shadow under monogram for a subtle 3D feel.
    shadow = _draw_q_monogram(master, ring_color=(0, 0, 0, 60))
    shadow = shadow.filter(ImageFilter.GaussianBlur(radius=12))
    shadow_layer = Image.new("RGBA", (master, master), (0, 0, 0, 0))
    shadow_layer.alpha_composite(shadow, (0, 6))
    # Draw shadow first: rebuild composition.
    composed = _vertical_gradient(master, PRIMARY_LIGHT, PRIMARY_DARK)
    composed.alpha_composite(glow)
    composed.alpha_composite(shadow_layer)
    composed.alpha_composite(mono)

    # Apply rounded-square mask (Android's legacy launcher on most OEMs
    # still renders whatever the app ships, so giving it rounded corners
    # looks consistent outside adaptive-icon launchers).
    mask = _rounded_mask(master, radius_pct=0.22)
    out = Image.new("RGBA", (master, master), (0, 0, 0, 0))
    out.paste(composed, (0, 0), mask=mask)

    return out.resize((size, size), Image.LANCZOS)


def render_foreground(size: int) -> Image.Image:
    """
    Adaptive icon foreground: transparent background, monogram inside the
    66 % central safe zone so OEM masks never clip it.
    """
    master = 1024
    img = Image.new("RGBA", (master, master), (0, 0, 0, 0))
    # Safe zone = centre 66 %, so monogram canvas ≈ 680 px.
    safe = int(master * 0.66)
    mono = _draw_q_monogram(safe)
    offset = (master - safe) // 2
    img.alpha_composite(mono, (offset, offset))
    return img.resize((size, size), Image.LANCZOS)


def render_round(size: int) -> Image.Image:
    legacy = render_legacy(size)
    mask = Image.new("L", (size, size), 0)
    ImageDraw.Draw(mask).ellipse([(0, 0), (size - 1, size - 1)], fill=255)
    out = Image.new("RGBA", (size, size), (0, 0, 0, 0))
    out.paste(legacy, (0, 0), mask=mask)
    return out


# --------------------------------------------------------------------------
# Emit
# --------------------------------------------------------------------------
def main() -> None:
    # PlayStore-sized master (1024² PNG) for future store listings.
    render_legacy(1024).save(ASSETS / "app_icon_1024.png")
    render_foreground(1024).save(ASSETS / "app_icon_foreground_1024.png")

    for folder, legacy_px, fg_px in BUCKETS:
        dst = RES / folder
        dst.mkdir(parents=True, exist_ok=True)
        render_legacy(legacy_px).save(dst / "ic_launcher.png")
        render_round(legacy_px).save(dst / "ic_launcher_round.png")
        render_foreground(fg_px).save(dst / "ic_launcher_foreground.png")
        print(f"wrote {folder} legacy={legacy_px} foreground={fg_px}")

    # Adaptive background tint = brand blue so launchers that drop the
    # foreground layer during masked animations still show brand colour.
    colors_xml = RES / "values/colors.xml"
    colors_xml.write_text(
        "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n"
        "<resources>\n"
        "    <color name=\"ic_launcher_background\">#1F5AAB</color>\n"
        "</resources>\n",
        encoding="utf-8",
    )
    print(f"wrote {colors_xml}")

    # Also ship a night-mode variant (same colour — works on dark launchers).
    night_colors = RES / "values-night/colors.xml"
    night_colors.parent.mkdir(parents=True, exist_ok=True)
    night_colors.write_text(
        "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n"
        "<resources>\n"
        "    <color name=\"ic_launcher_background\">#1A4A8C</color>\n"
        "</resources>\n",
        encoding="utf-8",
    )
    print(f"wrote {night_colors}")


if __name__ == "__main__":
    main()
