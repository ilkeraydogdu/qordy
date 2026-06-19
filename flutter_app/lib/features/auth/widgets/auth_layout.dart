import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../../config/theme.dart';
import '../../../core/widgets/qordy_logo.dart';

/// Shared layout chrome for every auth screen.
///
/// Every step of the login / register journey shares the same hero (a soft
/// blue radial wash + Qordy logo + headline + subtitle) so the user
/// always knows they're inside the brand.
class AuthLayout extends StatelessWidget {
  const AuthLayout({
    super.key,
    required this.title,
    required this.child,
    this.subtitle,
    this.showBack = true,
    this.scrollable = true,
    this.bottom,
  });

  final String title;
  final String? subtitle;
  final Widget child;
  final bool showBack;
  final bool scrollable;
  final Widget? bottom;

  @override
  Widget build(BuildContext context) {
    final mediaQuery = MediaQuery.of(context);
    final dark = context.isDark;

    final body = Padding(
      padding: const EdgeInsets.fromLTRB(24, 8, 24, 24),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const SizedBox(height: 8),
          Row(
            children: [
              const QordyLogo(height: 32, onDarkBackground: false),
              const SizedBox(width: 10),
              Container(
                padding: const EdgeInsets.symmetric(
                    horizontal: 8, vertical: 3),
                decoration: BoxDecoration(
                  color: AppColors.primary
                      .withValues(alpha: dark ? 0.22 : 0.10),
                  borderRadius: BorderRadius.circular(999),
                  border: Border.all(
                    color: AppColors.primary
                        .withValues(alpha: dark ? 0.35 : 0.18),
                    width: 0.6,
                  ),
                ),
                child: Text(
                  'İşletme Yönetimi',
                  style: TextStyle(
                    fontSize: 10.5,
                    fontWeight: FontWeight.w700,
                    letterSpacing: 0.3,
                    color: AppColors.primary,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 32),
          Text(
            title,
            style: Theme.of(context).textTheme.headlineMedium?.copyWith(
                  letterSpacing: -0.5,
                  fontWeight: FontWeight.w800,
                ),
          ),
          if (subtitle != null) ...[
            const SizedBox(height: 8),
            Text(
              subtitle!,
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    color: context.brandTextSecondary,
                    height: 1.45,
                  ),
            ),
          ],
          const SizedBox(height: 28),
          child,
        ],
      ),
    );

    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        backgroundColor: Colors.transparent,
        elevation: 0,
        scrolledUnderElevation: 0,
        leading: showBack
            ? IconButton(
                icon: const Icon(Icons.arrow_back_ios_new_rounded, size: 18),
                onPressed: () => Navigator.of(context).maybePop(),
              )
            : const SizedBox.shrink(),
      ),
      body: Stack(
        children: [
          Positioned(
            top: -120,
            right: -80,
            child: Container(
              width: 320,
              height: 320,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                gradient: RadialGradient(
                  colors: [
                    AppColors.primary.withValues(alpha: dark ? 0.22 : 0.14),
                    AppColors.primary.withValues(alpha: 0.0),
                  ],
                ),
              ),
            ),
          ),
          Positioned(
            bottom: -180,
            left: -120,
            child: Container(
              width: 360,
              height: 360,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                gradient: RadialGradient(
                  colors: [
                    AppColors.primaryLight.withValues(alpha: dark ? 0.14 : 0.09),
                    AppColors.primaryLight.withValues(alpha: 0.0),
                  ],
                ),
              ),
            ),
          ),
          SafeArea(
            top: false,
            child: scrollable
                ? SingleChildScrollView(
                    padding: EdgeInsets.only(
                      bottom: mediaQuery.viewInsets.bottom + 24,
                    ),
                    child: body,
                  )
                : body,
          ),
        ],
      ),
      bottomNavigationBar: bottom == null
          ? null
          : SafeArea(
              top: false,
              child: Padding(
                padding: const EdgeInsets.fromLTRB(24, 0, 24, 16),
                child: bottom,
              ),
            ),
    );
  }
}

/// Material-style "input group": a single bordered row that hosts the text
/// field + an optional left/right addon (icon, ".qordy.com" suffix, etc.)
/// without overlapping the typed text.
///
/// Behaviour worth calling out:
///   * Focus-aware border + subtle shadow — when any TextField under the
///     group gains focus, we animate the border to brand blue and add a
///     soft glow. The previous version had zero visual feedback when a
///     user tapped in, which felt broken on Android where there's no
///     caret blink until the first keystroke.
///   * Dark-mode safe — instead of hard-coding `Theme.of(context).cardColor`
///     for the background AND `AppColors.surfaceMuted` for the addons
///     (which drift apart in dark mode), both are now derived from the
///     `isDark` flag so the light/dark palettes stay consistent.
///   * Addons render OUTSIDE the input padding so typed text never slides
///     behind a leading icon or a trailing suffix.
class BrandInputGroup extends StatefulWidget {
  const BrandInputGroup({
    super.key,
    required this.child,
    this.leading,
    this.trailing,
    this.errorText,
    this.focusNode,
    this.height = 56,
  });

  final Widget child;
  final Widget? leading;
  final Widget? trailing;
  final String? errorText;
  final FocusNode? focusNode;
  final double height;

  @override
  State<BrandInputGroup> createState() => _BrandInputGroupState();
}

class _BrandInputGroupState extends State<BrandInputGroup> {
  bool _focused = false;

  @override
  Widget build(BuildContext context) {
    final hasError = widget.errorText != null && widget.errorText!.isNotEmpty;
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final bg = isDark ? AppColors.darkCard : AppColors.card;
    final addonBg =
        isDark ? AppColors.darkSurfaceMuted : AppColors.surfaceMuted;
    final baseBorder =
        isDark ? AppColors.darkBorder : AppColors.border;

    final activeBorder = hasError
        ? AppColors.error
        : (_focused ? AppColors.primary : baseBorder);
    final activeWidth = (hasError || _focused) ? 1.5 : 1.0;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        // FocusScope listens for any descendant getting focus — covers the
        // case where the TextField is deeply nested (e.g. wrapped in a
        // Form / Padding / gesture detector).
        Focus(
          canRequestFocus: false,
          onFocusChange: (f) {
            if (_focused != f) setState(() => _focused = f);
          },
          child: AnimatedContainer(
            duration: const Duration(milliseconds: 180),
            curve: Curves.easeOutCubic,
            height: widget.height,
            decoration: BoxDecoration(
              color: bg,
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: activeBorder, width: activeWidth),
              boxShadow: (_focused && !hasError)
                  ? [
                      BoxShadow(
                        color:
                            AppColors.primary.withValues(alpha: 0.12),
                        blurRadius: 10,
                        spreadRadius: 0,
                        offset: const Offset(0, 2),
                      ),
                    ]
                  : null,
            ),
            clipBehavior: Clip.antiAlias,
            child: Row(
              children: [
                if (widget.leading != null)
                  Container(
                    height: double.infinity,
                    padding: const EdgeInsets.symmetric(horizontal: 12),
                    decoration: BoxDecoration(
                      color: addonBg,
                      border: Border(
                        right: BorderSide(color: baseBorder, width: 1),
                      ),
                    ),
                    alignment: Alignment.center,
                    child: widget.leading,
                  ),
                Expanded(
                  child: Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 14),
                    child: widget.child,
                  ),
                ),
                if (widget.trailing != null)
                  Container(
                    height: double.infinity,
                    padding: const EdgeInsets.symmetric(horizontal: 4),
                    decoration: BoxDecoration(
                      color: addonBg,
                      border: Border(
                        left: BorderSide(color: baseBorder, width: 1),
                      ),
                    ),
                    alignment: Alignment.center,
                    child: widget.trailing,
                  ),
              ],
            ),
          ),
        ),
        if (hasError) ...[
          const SizedBox(height: 6),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Icon(
                Icons.error_outline_rounded,
                size: 13,
                color: AppColors.error,
              ),
              const SizedBox(width: 6),
              Expanded(
                child: Text(
                  widget.errorText!,
                  style: const TextStyle(
                    color: AppColors.error,
                    fontSize: 12,
                    fontWeight: FontWeight.w500,
                  ),
                ),
              ),
            ],
          ),
        ],
      ],
    );
  }
}

/// Pre-styled primary CTA used at the bottom of every auth step.
/// Adds haptic feedback on tap and subtle press/scale animation
/// for a more tactile, premium feel.
class PrimaryActionButton extends StatefulWidget {
  const PrimaryActionButton({
    super.key,
    required this.label,
    required this.onPressed,
    this.loading = false,
    this.icon,
  });

  final String label;
  final VoidCallback? onPressed;
  final bool loading;
  final IconData? icon;

  @override
  State<PrimaryActionButton> createState() => _PrimaryActionButtonState();
}

class _PrimaryActionButtonState extends State<PrimaryActionButton> {
  bool _pressed = false;

  void _setPressed(bool v) {
    if (_pressed == v) return;
    setState(() => _pressed = v);
  }

  @override
  Widget build(BuildContext context) {
    final disabled = widget.loading || widget.onPressed == null;
    return SizedBox(
      width: double.infinity,
      height: 54,
      child: GestureDetector(
        onTapDown: disabled ? null : (_) => _setPressed(true),
        onTapCancel: disabled ? null : () => _setPressed(false),
        onTapUp: disabled ? null : (_) => _setPressed(false),
        onTap: disabled
            ? null
            : () {
                HapticFeedback.lightImpact();
                widget.onPressed?.call();
              },
        child: AnimatedScale(
          scale: _pressed ? 0.98 : 1.0,
          duration: const Duration(milliseconds: 120),
          curve: Curves.easeOut,
          child: AnimatedContainer(
            duration: const Duration(milliseconds: 160),
            decoration: BoxDecoration(
              gradient: disabled ? null : AppColors.brandGradient,
              color: disabled ? AppColors.border : null,
              borderRadius: BorderRadius.circular(14),
              boxShadow: disabled
                  ? null
                  : [
                      BoxShadow(
                        color: AppColors.primary.withValues(
                            alpha: _pressed ? 0.18 : 0.35),
                        blurRadius: _pressed ? 10 : 18,
                        offset: Offset(0, _pressed ? 4 : 8),
                      ),
                    ],
            ),
            alignment: Alignment.center,
            child: widget.loading
                ? const SizedBox(
                    width: 22,
                    height: 22,
                    child: CircularProgressIndicator(
                      strokeWidth: 2.5,
                      color: Colors.white,
                    ),
                  )
                : Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(
                        widget.label,
                        style: TextStyle(
                          color: disabled
                              ? AppColors.textHint
                              : Colors.white,
                          fontSize: 15,
                          fontWeight: FontWeight.w700,
                          letterSpacing: -0.1,
                        ),
                      ),
                      if (widget.icon != null) ...[
                        const SizedBox(width: 8),
                        Icon(
                          widget.icon,
                          size: 18,
                          color: disabled
                              ? AppColors.textHint
                              : Colors.white,
                        ),
                      ],
                    ],
                  ),
          ),
        ),
      ),
    );
  }
}
