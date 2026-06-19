import 'dart:ui' show lerpDouble;

import 'package:flutter/material.dart';

import '../../config/theme.dart';

/// [ThemeExtension] — bileşenlerde tutarlı yarıçap, boşluk ve yoğunluk.
///
/// Tek bir ekrana özel sihirli sayılar yerine semantik adlarla kullanmayı
/// amaçlıyor: `BrandStyles.of(context).tileRadius` vs `12`. Yeni bir Figma
/// tweak geldiğinde sadece burayı güncellemek yetiyor.
@immutable
class BrandStyles extends ThemeExtension<BrandStyles> {
  const BrandStyles({
    required this.cardRadius,
    required this.sheetRadius,
    required this.chipRadius,
    required this.tileRadius,
    required this.buttonRadius,
    required this.inputRadius,
    required this.pillRadius,
    required this.tagRadius,
    required this.densePadding,
    required this.cardPadding,
    required this.tilePadding,
  });

  /// Üst seviye kartların yumuşak köşesi (dashboard, analytics kart blokları).
  final double cardRadius;

  /// BottomSheet / modal köşeleri.
  final double sheetRadius;

  /// Filter / content chip köşeleri (seçim pili).
  final double chipRadius;

  /// Liste satırı / list tile tarzı kutular.
  final double tileRadius;

  /// Elevated / outlined / text buton köşeleri (ThemeData ile birebir).
  final double buttonRadius;

  /// Form input alanları.
  final double inputRadius;

  /// Tamamen yuvarlak pill (status dot, action FAB).
  final double pillRadius;

  /// Küçük tag / kategori etiketi.
  final double tagRadius;

  /// Dense layout padding (küçük bileşen iç boşluğu).
  final EdgeInsets densePadding;

  /// Default kart içi padding (BrandCard varsayılanı).
  final EdgeInsets cardPadding;

  /// Liste tile içi padding.
  final EdgeInsets tilePadding;

  static const BrandStyles light = BrandStyles(
    cardRadius: 16,
    sheetRadius: 20,
    chipRadius: 10,
    tileRadius: 12,
    buttonRadius: 12,
    inputRadius: 12,
    pillRadius: 999,
    tagRadius: 6,
    densePadding: EdgeInsets.symmetric(horizontal: 12, vertical: 8),
    cardPadding: EdgeInsets.all(16),
    tilePadding: EdgeInsets.symmetric(horizontal: 14, vertical: 12),
  );

  static BrandStyles of(BuildContext context) {
    final ext = Theme.of(context).extension<BrandStyles>();
    return ext ?? BrandStyles.light;
  }

  @override
  BrandStyles copyWith({
    double? cardRadius,
    double? sheetRadius,
    double? chipRadius,
    double? tileRadius,
    double? buttonRadius,
    double? inputRadius,
    double? pillRadius,
    double? tagRadius,
    EdgeInsets? densePadding,
    EdgeInsets? cardPadding,
    EdgeInsets? tilePadding,
  }) {
    return BrandStyles(
      cardRadius: cardRadius ?? this.cardRadius,
      sheetRadius: sheetRadius ?? this.sheetRadius,
      chipRadius: chipRadius ?? this.chipRadius,
      tileRadius: tileRadius ?? this.tileRadius,
      buttonRadius: buttonRadius ?? this.buttonRadius,
      inputRadius: inputRadius ?? this.inputRadius,
      pillRadius: pillRadius ?? this.pillRadius,
      tagRadius: tagRadius ?? this.tagRadius,
      densePadding: densePadding ?? this.densePadding,
      cardPadding: cardPadding ?? this.cardPadding,
      tilePadding: tilePadding ?? this.tilePadding,
    );
  }

  @override
  BrandStyles lerp(ThemeExtension<BrandStyles>? other, double t) {
    if (other is! BrandStyles) return this;
    return BrandStyles(
      cardRadius: lerpDouble(cardRadius, other.cardRadius, t)!,
      sheetRadius: lerpDouble(sheetRadius, other.sheetRadius, t)!,
      chipRadius: lerpDouble(chipRadius, other.chipRadius, t)!,
      tileRadius: lerpDouble(tileRadius, other.tileRadius, t)!,
      buttonRadius: lerpDouble(buttonRadius, other.buttonRadius, t)!,
      inputRadius: lerpDouble(inputRadius, other.inputRadius, t)!,
      pillRadius: lerpDouble(pillRadius, other.pillRadius, t)!,
      tagRadius: lerpDouble(tagRadius, other.tagRadius, t)!,
      densePadding: EdgeInsets.lerp(densePadding, other.densePadding, t)!,
      cardPadding: EdgeInsets.lerp(cardPadding, other.cardPadding, t)!,
      tilePadding: EdgeInsets.lerp(tilePadding, other.tilePadding, t)!,
    );
  }
}

/// Marka uyumlu yüzey + gölge (kart, liste kutusu).
///
/// `elevated: false` ile gölgesiz düz panel elde edilir — bu durum
/// `BrandPanel`'ın varsayılan davranışıyla eşdeğerdir, ama iki isim de
/// kalıyor çünkü semantik farklılar: "card" = yüzen blok, "panel" = dolgu.
class BrandCard extends StatelessWidget {
  const BrandCard({
    super.key,
    required this.child,
    this.padding,
    this.onTap,
    this.elevated = true,
    this.tint,
  });

  final Widget child;
  final EdgeInsets? padding;
  final VoidCallback? onTap;
  final bool elevated;

  /// Arka plan rengini manuel override etmek için (örn. success callout).
  /// Dark mode'da da olduğu gibi uygulanır.
  final Color? tint;

  @override
  Widget build(BuildContext context) {
    final styles = BrandStyles.of(context);
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final bg = tint ?? (isDark ? AppColors.darkCard : AppColors.card);
    final border = isDark ? AppColors.darkBorder : AppColors.border;
    final radius = BorderRadius.circular(styles.cardRadius);

    final content = Padding(
      padding: padding ?? styles.cardPadding,
      child: child,
    );

    final decoration = BoxDecoration(
      color: bg,
      borderRadius: radius,
      border: Border.all(color: border.withValues(alpha: 0.65)),
      boxShadow: elevated
          ? [
              BoxShadow(
                color: Colors.black.withValues(alpha: isDark ? 0.2 : 0.04),
                blurRadius: 12,
                offset: const Offset(0, 4),
              ),
            ]
          : const [],
    );

    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: radius,
        child: Container(decoration: decoration, child: content),
      ),
    );
  }
}

/// Gölgesiz, tam düz yüzey. BrandCard'ın `elevated: false` hali için
/// semantik kısayol. Form blokları, inline panel, sekmeli içerik kutusu
/// gibi "kart" demek yerine "panel" demek istediğiniz yerler için.
class BrandPanel extends StatelessWidget {
  const BrandPanel({
    super.key,
    required this.child,
    this.padding,
    this.onTap,
    this.tint,
    this.borderless = false,
  });

  final Widget child;
  final EdgeInsets? padding;
  final VoidCallback? onTap;
  final Color? tint;
  final bool borderless;

  @override
  Widget build(BuildContext context) {
    final styles = BrandStyles.of(context);
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final bg = tint ??
        (isDark ? AppColors.darkSurfaceMuted : AppColors.surfaceMuted);
    final border = isDark ? AppColors.darkBorder : AppColors.border;
    final radius = BorderRadius.circular(styles.tileRadius);

    final decoration = BoxDecoration(
      color: bg,
      borderRadius: radius,
      border: borderless
          ? null
          : Border.all(color: border.withValues(alpha: 0.65)),
    );

    final content = Padding(
      padding: padding ?? styles.tilePadding,
      child: child,
    );

    if (onTap == null) {
      return Container(decoration: decoration, child: content);
    }
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: radius,
        child: Container(decoration: decoration, child: content),
      ),
    );
  }
}

/// Amber "bilgi / önemli not" şeridi. `order_detail`, `stock` ve ödeme
/// akışlarındaki ikon + metin yapısını standartlaştırır.
class BrandInfoCallout extends StatelessWidget {
  const BrandInfoCallout({
    super.key,
    required this.message,
    this.icon = Icons.info_outline,
    this.title,
    this.tone = BrandCalloutTone.warning,
  });

  final String message;
  final IconData icon;
  final String? title;
  final BrandCalloutTone tone;

  @override
  Widget build(BuildContext context) {
    final styles = BrandStyles.of(context);
    final palette = _paletteFor(tone, Theme.of(context).brightness);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      decoration: BoxDecoration(
        color: palette.surface,
        borderRadius: BorderRadius.circular(styles.tileRadius),
        border: Border.all(color: palette.border),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, size: 18, color: palette.icon),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (title != null && title!.isNotEmpty)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 2),
                    child: Text(
                      title!,
                      style: Theme.of(context).textTheme.labelLarge?.copyWith(
                            color: palette.titleText,
                            fontWeight: FontWeight.w700,
                          ),
                    ),
                  ),
                Text(
                  message,
                  style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                        color: palette.bodyText,
                        height: 1.35,
                      ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  _CalloutPalette _paletteFor(BrandCalloutTone tone, Brightness brightness) {
    switch (tone) {
      case BrandCalloutTone.warning:
        return _CalloutPalette(
          surface: AppColors.warningSurface,
          border: AppColors.warningSurfaceBorder,
          icon: AppColors.warningBright,
          titleText: AppColors.warningTextStrong,
          bodyText: AppColors.warningText,
        );
      case BrandCalloutTone.info:
        return _CalloutPalette(
          surface: AppColors.primarySoft,
          border: AppColors.primary.withValues(alpha: 0.35),
          icon: AppColors.primary,
          titleText: AppColors.primaryDark,
          bodyText: AppColors.primaryDark,
        );
      case BrandCalloutTone.success:
        return _CalloutPalette(
          surface: AppColors.successAlt.withValues(alpha: 0.12),
          border: AppColors.successAlt.withValues(alpha: 0.35),
          icon: AppColors.successAlt,
          titleText: AppColors.success,
          bodyText: AppColors.success,
        );
      case BrandCalloutTone.danger:
        return _CalloutPalette(
          surface: AppColors.errorBright.withValues(alpha: 0.08),
          border: AppColors.errorBright.withValues(alpha: 0.35),
          icon: AppColors.errorBright,
          titleText: AppColors.error,
          bodyText: AppColors.error,
        );
    }
  }
}

enum BrandCalloutTone { warning, info, success, danger }

class _CalloutPalette {
  const _CalloutPalette({
    required this.surface,
    required this.border,
    required this.icon,
    required this.titleText,
    required this.bodyText,
  });

  final Color surface;
  final Color border;
  final Color icon;
  final Color titleText;
  final Color bodyText;
}

class SectionHeader extends StatelessWidget {
  const SectionHeader({
    super.key,
    required this.title,
    this.subtitle,
    this.action,
  });

  final String title;
  final String? subtitle;
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    final primary = Theme.of(context).colorScheme.onSurface;
    final secondary = Theme.of(context).colorScheme.onSurfaceVariant;

    return Padding(
      padding: const EdgeInsets.only(bottom: 12, top: 4),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w700,
                        color: primary,
                        letterSpacing: -0.2,
                      ),
                ),
                if (subtitle != null && subtitle!.isNotEmpty) ...[
                  const SizedBox(height: 4),
                  Text(
                    subtitle!,
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(
                          color: secondary,
                          height: 1.35,
                        ),
                  ),
                ],
              ],
            ),
          ),
          if (action != null) action!,
        ],
      ),
    );
  }
}
