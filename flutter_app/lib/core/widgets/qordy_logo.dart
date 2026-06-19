import 'package:flutter/material.dart';

import '../../config/theme.dart';

/// Official Qordy wordmark.
///
/// Two PNG variants ship with the app:
///   * `assets/images/qordy_logo.png`        — slate "ordy" + blue "Q"  (light bg)
///   * `assets/images/qordy_logo_light.png`  — full-white wordmark      (dark bg)
///
/// We keep the wordmark itself untouched (it's the actual brand asset)
/// and just pick the variant that contrasts with the surface it sits on.
class QordyLogo extends StatelessWidget {
  const QordyLogo({
    super.key,
    this.height = 36,
    this.onDarkBackground = false,
    this.semanticLabel = 'Qordy',
  });

  final double height;
  final bool onDarkBackground;
  final String semanticLabel;

  @override
  Widget build(BuildContext context) {
    final asset = onDarkBackground
        ? 'assets/images/qordy_logo_light.png'
        : 'assets/images/qordy_logo.png';

    return Image.asset(
      asset,
      height: height,
      fit: BoxFit.contain,
      semanticLabel: semanticLabel,
      filterQuality: FilterQuality.high,
    );
  }
}

/// Compact "Q" mark — used on tight surfaces (avatars, app launcher tiles,
/// the leading slot of an app bar). Drawn with the brand gradient so it
/// scales crisply at any size without raster blur.
class QordyMark extends StatelessWidget {
  const QordyMark({
    super.key,
    this.size = 44,
    this.borderRadius = 14,
  });

  final double size;
  final double borderRadius;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        gradient: AppColors.brandGradient,
        borderRadius: BorderRadius.circular(borderRadius),
        boxShadow: [
          BoxShadow(
            color: AppColors.primary.withValues(alpha: 0.35),
            blurRadius: 18,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      alignment: Alignment.center,
      child: SizedBox(
        width: size * 0.55,
        height: size * 0.55,
        child: CustomPaint(
          painter: _QGlyphPainter(color: Colors.white),
        ),
      ),
    );
  }
}

class _QGlyphPainter extends CustomPainter {
  _QGlyphPainter({required this.color});

  final Color color;

  @override
  void paint(Canvas canvas, Size size) {
    final stroke = size.width * 0.18;
    final paint = Paint()
      ..color = color
      ..style = PaintingStyle.stroke
      ..strokeCap = StrokeCap.round
      ..strokeWidth = stroke;

    final ringRect = Rect.fromCircle(
      center: Offset(size.width * 0.46, size.height * 0.46),
      radius: size.width * 0.36,
    );
    canvas.drawOval(ringRect, paint);

    canvas.drawLine(
      Offset(size.width * 0.66, size.height * 0.68),
      Offset(size.width * 0.92, size.height * 0.94),
      paint,
    );
  }

  @override
  bool shouldRepaint(covariant _QGlyphPainter oldDelegate) =>
      oldDelegate.color != color;
}
