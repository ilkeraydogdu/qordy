import 'package:flutter/material.dart';
import 'package:qordy_app/config/theme.dart';
import 'package:shimmer/shimmer.dart';

/// ─────────────────────────────────────────────────────────────────────
///  Qordy — design system primitives
///
///  These widgets are the building blocks of every screen. Always
///  compose UI from them (or extend them) rather than hand-rolling
///  `Container(decoration: BoxDecoration(...))` variants at call-sites,
///  so the product stays visually consistent even as we iterate.
///
///  Reference: https://qordy.com — the mobile app mirrors the web
///  product's palette (Plus Jakarta Sans, slate ink, QORDY blue).
/// ─────────────────────────────────────────────────────────────────────

/// A premium card surface with soft shadow and subtle border. Replaces
/// the built-in [Card] when you need:
///   * custom padding / radius
///   * a soft "floating" feel without Material 1 ink-blob shadows
///   * optional tap ripple that stays inside the card radius
class QCard extends StatelessWidget {
  const QCard({
    super.key,
    required this.child,
    this.padding = const EdgeInsets.all(AppSpacing.lg),
    this.margin,
    this.onTap,
    this.borderRadius,
    this.color,
    this.border,
    this.shadow,
    this.gradient,
  });

  final Widget child;
  final EdgeInsetsGeometry padding;
  final EdgeInsetsGeometry? margin;
  final VoidCallback? onTap;
  final BorderRadius? borderRadius;
  final Color? color;
  final BoxBorder? border;
  final List<BoxShadow>? shadow;
  final Gradient? gradient;

  @override
  Widget build(BuildContext context) {
    final radius =
        borderRadius ?? BorderRadius.circular(AppRadius.lg);
    final bg = gradient == null
        ? (color ?? context.brandCard)
        : null;

    final decoration = BoxDecoration(
      color: bg,
      gradient: gradient,
      borderRadius: radius,
      border: border ??
          Border.all(color: context.brandBorder, width: 0.6),
      boxShadow: shadow ?? AppShadows.card(context.isDark),
    );

    final content = Padding(padding: padding, child: child);

    return Container(
      margin: margin,
      decoration: decoration,
      clipBehavior: Clip.antiAlias,
      child: onTap == null
          ? content
          : Material(
              color: Colors.transparent,
              child: InkWell(
                borderRadius: radius,
                onTap: onTap,
                child: content,
              ),
            ),
    );
  }
}

/// Section title + optional trailing action, sized to sit above a
/// [QCard]. Matches the web product's `.section-heading` pattern.
class QSectionHeader extends StatelessWidget {
  const QSectionHeader({
    super.key,
    required this.title,
    this.subtitle,
    this.trailing,
    this.leadingAccent = true,
    this.padding = const EdgeInsets.fromLTRB(
      AppSpacing.xs,
      AppSpacing.sm,
      AppSpacing.xs,
      AppSpacing.md,
    ),
  });

  final String title;
  final String? subtitle;
  final Widget? trailing;
  final bool leadingAccent;
  final EdgeInsetsGeometry padding;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Padding(
      padding: padding,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          if (leadingAccent) ...[
            Container(
              width: 3,
              height: 18,
              decoration: BoxDecoration(
                color: theme.colorScheme.primary,
                borderRadius: BorderRadius.circular(3),
              ),
            ),
            const SizedBox(width: AppSpacing.sm),
          ],
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w700,
                    letterSpacing: -0.2,
                  ),
                ),
                if (subtitle != null) ...[
                  const SizedBox(height: 2),
                  Text(
                    subtitle!,
                    style: TextStyle(
                      fontSize: 12,
                      color: context.brandTextSecondary,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                ],
              ],
            ),
          ),
          if (trailing != null) trailing!,
        ],
      ),
    );
  }
}

/// Colourful stat tile used on dashboards. Shows a value with an
/// emphasis color and a secondary label. Optional [trend] renders a
/// "+%12" style delta in the corner.
class QStatCard extends StatelessWidget {
  const QStatCard({
    super.key,
    required this.label,
    required this.value,
    required this.icon,
    required this.color,
    this.trend,
    this.onTap,
  });

  final String label;
  final String value;
  final IconData icon;
  final Color color;
  final String? trend;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final dark = context.isDark;
    // Generate a soft tinted surface from the seed color so each tile
    // feels like its own little hero without clashing with the white
    // background around it.
    final surface = Color.alphaBlend(
      color.withValues(alpha: dark ? 0.22 : 0.08),
      dark ? AppColors.darkCard : Colors.white,
    );
    // Trend indicator yön/renk çıkarımı: "+12.5%" gibi stringlerde
    // + / - işaretine bakıp yeşil-kırmızı vermek KPI hissi için önemli.
    final trendValue = trend?.trim() ?? '';
    final isPositive = trendValue.startsWith('+');
    final isNegative = trendValue.startsWith('-') ||
        trendValue.startsWith('↓') ||
        trendValue.startsWith('−');
    final trendColor = isPositive
        ? AppColors.successAlt
        : isNegative
            ? AppColors.errorBright
            : color;

    return QCard(
      color: surface,
      onTap: onTap,
      border: Border.all(
        color: color.withValues(alpha: dark ? 0.45 : 0.18),
        width: 0.8,
      ),
      padding: const EdgeInsets.symmetric(
          horizontal: AppSpacing.lg, vertical: AppSpacing.lg),
      child: Stack(
        clipBehavior: Clip.none,
        children: [
          // Dekoratif mega-icon: kart dolu dolu his versin ama
          // alpha çok düşük olsun ki metni gölgelemesin.
          Positioned(
            right: -14,
            bottom: -14,
            child: Icon(
              icon,
              size: 110,
              color: color.withValues(alpha: dark ? 0.08 : 0.05),
            ),
          ),
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Container(
                    width: 40,
                    height: 40,
                    decoration: BoxDecoration(
                      gradient: LinearGradient(
                        begin: Alignment.topLeft,
                        end: Alignment.bottomRight,
                        colors: [
                          color.withValues(alpha: dark ? 0.45 : 0.22),
                          color.withValues(alpha: dark ? 0.25 : 0.10),
                        ],
                      ),
                      borderRadius: BorderRadius.circular(AppRadius.md),
                      border: Border.all(
                        color: color.withValues(alpha: dark ? 0.5 : 0.22),
                        width: 0.6,
                      ),
                      boxShadow: [
                        BoxShadow(
                          color: color.withValues(alpha: dark ? 0.25 : 0.12),
                          blurRadius: 8,
                          offset: const Offset(0, 3),
                        ),
                      ],
                    ),
                    alignment: Alignment.center,
                    child: Icon(icon, color: color, size: 20),
                  ),
                  const Spacer(),
                  if (trend != null && trendValue.isNotEmpty)
                    Container(
                      padding: const EdgeInsets.symmetric(
                          horizontal: 8, vertical: 3),
                      decoration: BoxDecoration(
                        color: trendColor.withValues(alpha: 0.14),
                        borderRadius: BorderRadius.circular(999),
                        border: Border.all(
                          color: trendColor.withValues(alpha: 0.28),
                          width: 0.6,
                        ),
                      ),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Icon(
                            isPositive
                                ? Icons.trending_up_rounded
                                : isNegative
                                    ? Icons.trending_down_rounded
                                    : Icons.trending_flat_rounded,
                            size: 12,
                            color: trendColor,
                          ),
                          const SizedBox(width: 3),
                          Text(
                            trendValue,
                            style: TextStyle(
                              fontSize: 11,
                              fontWeight: FontWeight.w700,
                              color: trendColor,
                            ),
                          ),
                        ],
                      ),
                    ),
                ],
              ),
              const SizedBox(height: AppSpacing.md),
              Text(
                value,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  fontSize: 24,
                  fontWeight: FontWeight.w800,
                  color: context.brandTextPrimary,
                  letterSpacing: -0.5,
                  height: 1.1,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                label,
                style: TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w600,
                  color: context.brandTextSecondary,
                  letterSpacing: -0.1,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

/// A compact "pill" button used in tab rows (Today / Week / Month etc).
class QSegmented<T> extends StatelessWidget {
  const QSegmented({
    super.key,
    required this.value,
    required this.segments,
    required this.onChanged,
  });

  final T value;
  final List<QSegment<T>> segments;
  final ValueChanged<T> onChanged;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Container(
      padding: const EdgeInsets.all(4),
      decoration: BoxDecoration(
        color: context.brandSurface,
        borderRadius: BorderRadius.circular(AppRadius.pill),
        border: Border.all(color: context.brandBorder, width: 0.6),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          for (final s in segments)
            _Segment(
              selected: s.value == value,
              label: s.label,
              onTap: () => onChanged(s.value),
              accent: theme.colorScheme.primary,
            ),
        ],
      ),
    );
  }
}

class QSegment<T> {
  const QSegment({required this.value, required this.label});
  final T value;
  final String label;
}

class _Segment extends StatelessWidget {
  const _Segment({
    required this.selected,
    required this.label,
    required this.onTap,
    required this.accent,
  });

  final bool selected;
  final String label;
  final VoidCallback onTap;
  final Color accent;

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      behavior: HitTestBehavior.opaque,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 180),
        curve: Curves.easeOutCubic,
        padding: const EdgeInsets.symmetric(
            horizontal: AppSpacing.lg, vertical: AppSpacing.sm),
        decoration: BoxDecoration(
          color: selected ? accent : Colors.transparent,
          borderRadius: BorderRadius.circular(AppRadius.pill),
          boxShadow: selected
              ? [
                  BoxShadow(
                    color: accent.withValues(alpha: 0.28),
                    blurRadius: 10,
                    offset: const Offset(0, 4),
                  ),
                ]
              : null,
        ),
        child: Text(
          label,
          style: TextStyle(
            fontSize: 13,
            fontWeight: FontWeight.w600,
            color:
                selected ? Colors.white : context.brandTextSecondary,
          ),
        ),
      ),
    );
  }
}

/// Quick-action tile for dashboard grids. Shows an iconified square
/// with a label underneath. Reacts to touch with a subtle scale
/// animation so the tile feels "pressable" even without a solid
/// background.
class QActionTile extends StatefulWidget {
  const QActionTile({
    super.key,
    required this.icon,
    required this.label,
    required this.color,
    this.onTap,
    this.badge,
  });

  final IconData icon;
  final String label;
  final Color color;
  final VoidCallback? onTap;
  final String? badge;

  @override
  State<QActionTile> createState() => _QActionTileState();
}

class _QActionTileState extends State<QActionTile> {
  bool _pressed = false;

  void _setPressed(bool v) {
    if (_pressed == v) return;
    setState(() => _pressed = v);
  }

  @override
  Widget build(BuildContext context) {
    final dark = context.isDark;
    return GestureDetector(
      behavior: HitTestBehavior.opaque,
      onTapDown: widget.onTap == null ? null : (_) => _setPressed(true),
      onTapCancel: () => _setPressed(false),
      onTapUp: (_) => _setPressed(false),
      onTap: widget.onTap,
      child: AnimatedScale(
        scale: _pressed ? 0.95 : 1.0,
        duration: const Duration(milliseconds: 120),
        curve: Curves.easeOut,
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: AppSpacing.sm),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Stack(
                clipBehavior: Clip.none,
                children: [
                  AnimatedContainer(
                    duration: const Duration(milliseconds: 180),
                    width: 56,
                    height: 56,
                    decoration: BoxDecoration(
                      gradient: LinearGradient(
                        begin: Alignment.topLeft,
                        end: Alignment.bottomRight,
                        colors: [
                          widget.color
                              .withValues(alpha: dark ? 0.28 : 0.14),
                          widget.color
                              .withValues(alpha: dark ? 0.18 : 0.08),
                        ],
                      ),
                      borderRadius:
                          BorderRadius.circular(AppRadius.lg),
                      border: Border.all(
                        color: widget.color.withValues(
                            alpha: dark ? 0.42 : 0.22),
                        width: 0.8,
                      ),
                      boxShadow: _pressed
                          ? null
                          : [
                              BoxShadow(
                                color: widget.color.withValues(
                                    alpha: dark ? 0.18 : 0.10),
                                blurRadius: 10,
                                offset: const Offset(0, 4),
                              ),
                            ],
                    ),
                    alignment: Alignment.center,
                    child: Icon(widget.icon, color: widget.color, size: 24),
                  ),
                  if (widget.badge != null)
                    Positioned(
                      right: -4,
                      top: -4,
                      child: Container(
                        padding: const EdgeInsets.symmetric(
                            horizontal: 6, vertical: 2),
                        decoration: BoxDecoration(
                          color: AppColors.error,
                          borderRadius:
                              BorderRadius.circular(AppRadius.pill),
                          border: Border.all(
                              color: context.brandCard, width: 1.6),
                        ),
                        child: Text(
                          widget.badge!,
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 10,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ),
                    ),
                ],
              ),
              const SizedBox(height: AppSpacing.sm),
              Text(
                widget.label,
                textAlign: TextAlign.center,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  fontSize: 11.5,
                  fontWeight: FontWeight.w600,
                  color: context.brandTextPrimary,
                  height: 1.2,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Status pill used to colour-code order statuses (PENDING, READY…)
class QStatusBadge extends StatelessWidget {
  const QStatusBadge({
    super.key,
    required this.label,
    required this.color,
    this.compact = false,
  });

  final String label;
  final Color color;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: compact
          ? const EdgeInsets.symmetric(horizontal: 8, vertical: 3)
          : const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.14),
        borderRadius: BorderRadius.circular(AppRadius.pill),
        border: Border.all(
          color: color.withValues(alpha: 0.35),
          width: 0.6,
        ),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 6,
            height: 6,
            decoration: BoxDecoration(
              color: color,
              shape: BoxShape.circle,
            ),
          ),
          const SizedBox(width: 6),
          Text(
            label,
            style: TextStyle(
              fontSize: compact ? 10.5 : 11.5,
              fontWeight: FontWeight.w700,
              color: color,
              letterSpacing: 0.2,
            ),
          ),
        ],
      ),
    );
  }
}

/// Greeting hero used at the top of role home / dashboard screens.
/// Shows "Good morning/afternoon/evening, [name]" with a subtle
/// brand-gradient background.
class QGreetingHero extends StatelessWidget {
  const QGreetingHero({
    super.key,
    required this.name,
    this.subtitle,
    this.avatarLabel,
    this.trailing,
  });

  final String name;
  final String? subtitle;
  final String? avatarLabel;
  final Widget? trailing;

  String _greetingTr() {
    final h = DateTime.now().hour;
    if (h < 6) return 'İyi Geceler,';
    if (h < 12) return 'Günaydın,';
    if (h < 18) return 'İyi Günler,';
    return 'İyi Akşamlar,';
  }

  @override
  Widget build(BuildContext context) {
    final initials = (avatarLabel?.trim().isNotEmpty ?? false)
        ? avatarLabel!.trim().substring(0, 1).toUpperCase()
        : (name.trim().isNotEmpty ? name.trim().substring(0, 1).toUpperCase() : 'Q');
    final dark = context.isDark;

    return ClipRRect(
      borderRadius: BorderRadius.circular(AppRadius.lg),
      child: Stack(
        children: [
          // Premium base surface — deep navy gradient in dark mode,
          // glass-tinted ink-wash in light mode. The two decorative
          // blobs peeking in from the corners add a little motion /
          // brand-warmth without needing an actual image asset.
          Positioned.fill(
            child: DecoratedBox(
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                  colors: dark
                      ? const [Color(0xFF17223F), Color(0xFF0F1628)]
                      : const [Color(0xFFEFF4FF), Color(0xFFF8FBFF)],
                ),
                border: Border.all(color: context.brandBorder, width: 0.6),
                borderRadius: BorderRadius.circular(AppRadius.lg),
              ),
            ),
          ),
          Positioned(
            top: -40,
            right: -30,
            child: _HeroBlob(
              size: 160,
              color: AppColors.primary.withValues(alpha: dark ? 0.22 : 0.14),
            ),
          ),
          Positioned(
            bottom: -60,
            left: -20,
            child: _HeroBlob(
              size: 140,
              color: AppColors.primaryLight
                  .withValues(alpha: dark ? 0.14 : 0.10),
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(AppSpacing.xl),
            child: Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Row(
                        children: [
                          Container(
                            width: 6,
                            height: 6,
                            decoration: const BoxDecoration(
                              color: AppColors.successBright,
                              shape: BoxShape.circle,
                            ),
                          ),
                          const SizedBox(width: 8),
                          Text(
                            _greetingTr(),
                            style: TextStyle(
                              fontSize: 12.5,
                              fontWeight: FontWeight.w600,
                              letterSpacing: 0.2,
                              color: context.brandTextSecondary,
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 6),
                      Text(
                        name,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                          fontSize: 26,
                          fontWeight: FontWeight.w800,
                          letterSpacing: -0.7,
                          color: context.brandTextPrimary,
                        ),
                      ),
                      if (subtitle != null && subtitle!.trim().isNotEmpty) ...[
                        const SizedBox(height: 4),
                        Row(
                          children: [
                            Icon(
                              Icons.storefront_rounded,
                              size: 13,
                              color: context.brandTextSecondary,
                            ),
                            const SizedBox(width: 5),
                            Flexible(
                              child: Text(
                                subtitle!,
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: TextStyle(
                                  fontSize: 12.5,
                                  fontWeight: FontWeight.w600,
                                  color: context.brandTextSecondary,
                                ),
                              ),
                            ),
                          ],
                        ),
                      ],
                    ],
                  ),
                ),
                const SizedBox(width: AppSpacing.md),
                if (trailing != null)
                  trailing!
                else
                  Container(
                    width: 52,
                    height: 52,
                    decoration: BoxDecoration(
                      gradient: AppColors.brandGradient,
                      borderRadius: BorderRadius.circular(AppRadius.md),
                      boxShadow: AppShadows.hero(dark),
                    ),
                    alignment: Alignment.center,
                    child: Text(
                      initials,
                      style: const TextStyle(
                        color: Colors.white,
                        fontWeight: FontWeight.w800,
                        fontSize: 20,
                      ),
                    ),
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// Decorative soft circle used behind [QGreetingHero].
class _HeroBlob extends StatelessWidget {
  const _HeroBlob({required this.size, required this.color});

  final double size;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        gradient: RadialGradient(
          colors: [color, color.withValues(alpha: 0)],
        ),
      ),
    );
  }
}

/// Friendly empty-state with icon, title and optional CTA.
class QEmptyState extends StatelessWidget {
  const QEmptyState({
    super.key,
    required this.icon,
    required this.title,
    this.message,
    this.action,
  });

  final IconData icon;
  final String title;
  final String? message;
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 320),
        child: Padding(
          padding: const EdgeInsets.all(AppSpacing.xl),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 72,
                height: 72,
                decoration: BoxDecoration(
                  color: AppColors.primary
                      .withValues(alpha: context.isDark ? 0.2 : 0.08),
                  shape: BoxShape.circle,
                ),
                alignment: Alignment.center,
                child: Icon(
                  icon,
                  size: 32,
                  color: AppColors.primary,
                ),
              ),
              const SizedBox(height: AppSpacing.lg),
              Text(
                title,
                textAlign: TextAlign.center,
                style: TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.w700,
                  color: context.brandTextPrimary,
                ),
              ),
              if (message != null) ...[
                const SizedBox(height: AppSpacing.sm),
                Text(
                  message!,
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    fontSize: 13,
                    height: 1.5,
                    color: context.brandTextSecondary,
                  ),
                ),
              ],
              if (action != null) ...[
                const SizedBox(height: AppSpacing.lg),
                action!,
              ],
            ],
          ),
        ),
      ),
    );
  }
}

/// Skeleton row for loading states — replaces dumb spinners with a
/// moving shimmer so the UI feels alive while data streams in.
/// The colour pair auto-adapts to light/dark theme.
class QSkeleton extends StatelessWidget {
  const QSkeleton({super.key, this.height = 16, this.width, this.radius});
  final double height;
  final double? width;
  final double? radius;

  @override
  Widget build(BuildContext context) {
    final dark = context.isDark;
    final base = dark ? AppColors.darkSurfaceMuted : AppColors.surfaceMuted;
    final highlight = dark
        ? AppColors.darkBorder.withValues(alpha: 0.4)
        : Colors.white.withValues(alpha: 0.9);
    return Shimmer.fromColors(
      baseColor: base,
      highlightColor: highlight,
      period: const Duration(milliseconds: 1400),
      child: Container(
        width: width,
        height: height,
        decoration: BoxDecoration(
          color: base,
          borderRadius: BorderRadius.circular(radius ?? AppRadius.sm),
        ),
      ),
    );
  }
}
