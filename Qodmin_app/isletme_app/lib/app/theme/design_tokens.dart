import 'package:flutter/material.dart';

/// Qordy marka renkleri ve tasarım token'ları.
class QordyColors {
 QordyColors._();

 /// Birincil — Qordy mavi.
 static const Color primary = Color(0xFF1F3A8A);

 /// Birincil container (M3 tonal).
 static const Color primaryContainer = Color(0xFFDBE3FF);

 /// İkincil — Qordy amber.
 static const Color secondary = Color(0xFFF59E0B);
 static const Color secondaryContainer = Color(0xFFFFE8B0);

 /// Üçüncül — başarı.
 static const Color tertiary = Color(0xFF10B981);
 static const Color tertiaryContainer = Color(0xFFD1FAE5);

 /// Hata.
 static const Color error = Color(0xFFDC2626);
 static const Color errorContainer = Color(0xFFFEE2E2);

 /// Yüzey.
 static const Color surface = Color(0xFFFFFFFF);
 static const Color surfaceDim = Color(0xFFF8FAFC);
 static const Color surfaceContainer = Color(0xFFF1F5F9);

 /// On-surface varyantları.
 static const Color onSurface = Color(0xFF0F172A);
 static const Color onSurfaceVariant = Color(0xFF475569);
 static const Color outline = Color(0xFFCBD5E1);
 static const Color outlineVariant = Color(0xFFE2E8F0);

 /// Dark.
 static const Color darkPrimary = Color(0xFF93B0FF);
 static const Color darkSurface = Color(0xFF0F172A);
 static const Color darkOnSurface = Color(0xFFE2E8F0);
}

/// Spacing — 4pt grid.
class QordySpacing {
 QordySpacing._();
 static const double xxs = 2;
 static const double xs = 4;
 static const double sm = 8;
 static const double md = 12;
 static const double lg = 16;
 static const double xl = 24;
 static const double xxl = 32;
 static const double xxxl = 48;
}

/// Köşe yuvarlaklıkları.
class QordyRadius {
 QordyRadius._();
 static const double sm = 6;
 static const double md = 10;
 static const double lg = 16;
 static const double xl = 24;
 static const BorderRadius brSm = BorderRadius.all(Radius.circular(sm));
 static const BorderRadius brMd = BorderRadius.all(Radius.circular(md));
 static const BorderRadius brLg = BorderRadius.all(Radius.circular(lg));
 static const BorderRadius brXl = BorderRadius.all(Radius.circular(xl));
}

/// Tipografi skalası.
class QordyTypography {
 QordyTypography._();

 static const TextStyle displayLarge = TextStyle(
 fontSize: 32,
 fontWeight: FontWeight.w700,
 height: 1.2,
 letterSpacing: -0.5,
 );

 static const TextStyle headlineMedium = TextStyle(
 fontSize: 24,
 fontWeight: FontWeight.w700,
 height: 1.25,
 );

 static const TextStyle titleLarge = TextStyle(
 fontSize: 20,
 fontWeight: FontWeight.w600,
 height: 1.3,
 );

 static const TextStyle titleMedium = TextStyle(
 fontSize: 16,
 fontWeight: FontWeight.w600,
 height: 1.4,
 );

 static const TextStyle bodyLarge = TextStyle(
 fontSize: 16,
 fontWeight: FontWeight.w400,
 height: 1.5,
 );

 static const TextStyle bodyMedium = TextStyle(
 fontSize: 14,
 fontWeight: FontWeight.w400,
 height: 1.45,
 );

 static const TextStyle bodySmall = TextStyle(
 fontSize: 12,
 fontWeight: FontWeight.w400,
 height: 1.4,
 );

 static const TextStyle labelLarge = TextStyle(
 fontSize: 14,
 fontWeight: FontWeight.w600,
 height: 1.2,
 letterSpacing: 0.1,
 );

 static const TextStyle labelSmall = TextStyle(
 fontSize: 11,
 fontWeight: FontWeight.w600,
 height: 1.2,
 letterSpacing: 0.5,
 );
}
