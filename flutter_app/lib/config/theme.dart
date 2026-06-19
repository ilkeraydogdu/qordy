import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:qordy_app/core/theme/brand_styles.dart';

/// Brand palette derived from the Qordy logo.
///
/// The wordmark is a saturated mid-blue "Q" sitting on a deep slate "ordy".
/// We mirror the web app palette here so the marketing site, login portal
/// and the staff/manager mobile app feel like one product.
class AppColors {
  AppColors._();

  // ---------- Brand blue (the "Q") ----------
  /// Primary action / brand accent. Mirrors `--fire-600` on the web.
  static const Color primary = Color(0xFF1F5AAB);

  /// Hover / pressed and lighter accents. Mirrors `--fire-500` on the web.
  static const Color primaryLight = Color(0xFF2B7AC9);

  /// Stronger ink-blue for splash / outline. Mirrors `--fire-700`.
  static const Color primaryDark = Color(0xFF1A4A8C);

  /// Soft tint used as primaryContainer.
  static const Color primarySoft = Color(0xFFE8F0FE);

  // ---------- Surfaces ----------
  static const Color background = Color(0xFFFFFFFF);
  static const Color surface = Color(0xFFF8FAFC);
  static const Color surfaceMuted = Color(0xFFF1F5F9);
  static const Color card = Color(0xFFFFFFFF);

  // ---------- Slate ink (the "ordy" wordmark) ----------
  static const Color textPrimary = Color(0xFF0F172A);
  static const Color textSecondary = Color(0xFF475569);
  static const Color textHint = Color(0xFF94A3B8);

  static const Color border = Color(0xFFE2E8F0);
  static const Color divider = Color(0xFFF1F5F9);

  // ---------- Status ----------
  static const Color success = Color(0xFF16A34A);
  static const Color error = Color(0xFFDC2626);
  static const Color warning = Color(0xFFD97706);
  static const Color info = Color(0xFF2563EB);

  /// Brighter accent variants used on pill chips, dots and destructive CTAs.
  /// The core `success/error/warning/info` tokens drive typography and
  /// buttons where contrast matters; these "bright" variants are the
  /// lighter, more vivid tones you reach for on pills and badges.
  static const Color successBright = Color(0xFF22C55E);
  static const Color successAlt = Color(0xFF10B981);
  static const Color errorBright = Color(0xFFEF4444);
  static const Color warningBright = Color(0xFFF59E0B);
  static const Color infoBright = Color(0xFF3B82F6);

  /// Warning surface palette (amber-50/200/700/800). Matches the
  /// "Önemli not" callouts so the whole block feels curated.
  static const Color warningSurface = Color(0xFFFFFBEB);
  static const Color warningSurfaceBorder = Color(0xFFFDE68A);
  static const Color warningText = Color(0xFFB45309);
  static const Color warningTextStrong = Color(0xFF92400E);

  /// Secondary brand accents used by dashboard quick-actions and role hero.
  static const Color accentOrange = Color(0xFFF97316);
  static const Color accentPurple = Color(0xFF8B5CF6);
  static const Color accentIndigo = Color(0xFF6366F1);
  static const Color accentRose = Color(0xFFEC4899);
  static const Color accentCyan = Color(0xFF06B6D4);

  /// Soft grey used for disabled / placeholder iconography (gray-300).
  static const Color iconDisabled = Color(0xFFD1D5DB);

  static const Color scaffoldBackground = Color(0xFFF6F8FB);

  /// Linear gradient used on hero buttons / brand splashes.
  static const LinearGradient brandGradient = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: [Color(0xFF3B82F6), Color(0xFF1F5AAB)],
  );

  // ──────────────────── Dark palette ─────────────────────────────
  // Paired tokens that keep the brand identity after ThemeMode flip.
  // All backgrounds use a slight blue tint so the product still feels
  // like "QORDY" (not generic Material dark).
  //
  // Elevation hiyerarşisi (koyudan açığa):
  //   scaffoldBackground  → background → surface → surfaceMuted → card
  // Kart kademesi scaffold'dan net bir adım yüksekte olduğu için
  // gölgeye bağımlı kalmadan "yukarı kaldırılmış" hissi veriyor.
  static const Color darkScaffoldBackground = Color(0xFF070B15); // deepest
  static const Color darkBackground = Color(0xFF0B1220);         // appbar
  static const Color darkSurface = Color(0xFF0F1A30);            // sheets / bars
  static const Color darkSurfaceMuted = Color(0xFF15213A);       // row / chip
  static const Color darkCard = Color(0xFF18253D);               // lifted card

  static const Color darkTextPrimary = Color(0xFFEDF1F8);
  static const Color darkTextSecondary = Color(0xFFAEB8CC);
  static const Color darkTextHint = Color(0xFF7486A3);
  static const Color darkBorder = Color(0xFF2A3A5C);
  static const Color darkDivider = Color(0xFF1E2A48);

  /// Accent blue used for dark-mode primary. Slightly brightened so it
  /// passes WCAG contrast on the navy backgrounds.
  static const Color primaryDarkMode = Color(0xFF4A90F2);
  static const Color primarySoftDark = Color(0xFF1A2E55);
}

/// Centralised spacing scale — 4-point grid. Matches Material 3 density
/// guidance and the web product's `--space-*` tokens. Always prefer these
/// over hand-coded `EdgeInsets.all(N)` so the visual rhythm stays tight.
class AppSpacing {
  AppSpacing._();
  static const double xxs = 2;
  static const double xs = 4;
  static const double sm = 8;
  static const double md = 12;
  static const double lg = 16;
  static const double xl = 20;
  static const double xxl = 24;
  static const double xxxl = 32;
  static const double huge = 40;
}

/// Corner radii scale. `card` and `chip` match the Material 3 shapes we
/// ship in [AppTheme] so a hand-rolled Container blends with the
/// themed `Card` / `Chip` siblings around it.
class AppRadius {
  AppRadius._();
  static const double sm = 8;
  static const double md = 12;
  static const double lg = 14; // cards
  static const double xl = 16; // dialogs / bottom-sheets
  static const double pill = 999;
}

/// Elevation / shadow presets. Flutter's built-in elevation casts a harsh
/// Material-1 shadow on light backgrounds; these softer custom shadows
/// give cards the lift we want without the grey blob.
class AppShadows {
  AppShadows._();
  static List<BoxShadow> card(bool isDark) => [
        BoxShadow(
          color: (isDark ? Colors.black : const Color(0xFF0F172A))
              .withValues(alpha: isDark ? 0.55 : 0.05),
          blurRadius: 16,
          offset: const Offset(0, 6),
        ),
        if (isDark)
          // Dark mode'da kartın üst kenarına soft inner highlight
          // veriyor: scaffold siyaha yakınken kartı metalik bir
          // "yüzey" gibi ayrıştırıyor.
          BoxShadow(
            color: Colors.white.withValues(alpha: 0.015),
            blurRadius: 0,
            offset: const Offset(0, -1),
            spreadRadius: 0.5,
          ),
      ];
  static List<BoxShadow> hero(bool isDark) => [
        BoxShadow(
          color: AppColors.primary
              .withValues(alpha: isDark ? 0.35 : 0.18),
          blurRadius: 28,
          offset: const Offset(0, 12),
        ),
      ];
}

/// Semantic aliases that resolve at runtime to the active [ThemeData].
/// Prefer `Theme.of(context).colorScheme.*` inside widgets — this helper
/// exists for legacy widgets that still read `AppColors.card` directly.
extension BrandThemeX on BuildContext {
  bool get isDark => Theme.of(this).brightness == Brightness.dark;
  Color get brandCard =>
      isDark ? AppColors.darkCard : AppColors.card;
  Color get brandSurface =>
      isDark ? AppColors.darkSurface : AppColors.surface;
  Color get brandSurfaceMuted =>
      isDark ? AppColors.darkSurfaceMuted : AppColors.surfaceMuted;
  Color get brandBorder =>
      isDark ? AppColors.darkBorder : AppColors.border;
  Color get brandTextPrimary =>
      isDark ? AppColors.darkTextPrimary : AppColors.textPrimary;
  Color get brandTextSecondary =>
      isDark ? AppColors.darkTextSecondary : AppColors.textSecondary;
  Color get brandTextHint =>
      isDark ? AppColors.darkTextHint : AppColors.textHint;
  Color get brandScaffoldBg =>
      isDark ? AppColors.darkScaffoldBackground : AppColors.scaffoldBackground;

  /// Uygulama genelinde scaffold veya tam-ekran sayfaların arkasına
  /// konulan sıcak atmosferik dekorasyon. Koyu modda marka mavisinin
  /// üst kenardan sızan çok yumuşak radial aydınlanması kartların
  /// kontrastını ve derinlik hissini artırıyor.
  BoxDecoration get brandScaffoldDecoration {
    if (isDark) {
      return BoxDecoration(
        gradient: RadialGradient(
          center: const Alignment(0, -1.2),
          radius: 1.6,
          colors: [
            AppColors.primary.withValues(alpha: 0.08),
            AppColors.darkScaffoldBackground,
          ],
          stops: const [0.0, 0.8],
        ),
      );
    }
    return BoxDecoration(
      gradient: LinearGradient(
        begin: Alignment.topCenter,
        end: Alignment.bottomCenter,
        colors: [
          AppColors.surface,
          AppColors.scaffoldBackground,
        ],
      ),
    );
  }
}

class AppTheme {
  AppTheme._();

  static ThemeData get light {
    return ThemeData(
      useMaterial3: true,
      brightness: Brightness.light,
      fontFamily: GoogleFonts.plusJakartaSans().fontFamily,
      primaryColor: AppColors.primary,
      scaffoldBackgroundColor: AppColors.scaffoldBackground,
      colorScheme: const ColorScheme.light(
        primary: AppColors.primary,
        onPrimary: Colors.white,
        primaryContainer: AppColors.primarySoft,
        onPrimaryContainer: AppColors.primaryDark,
        secondary: AppColors.primaryLight,
        onSecondary: Colors.white,
        surface: AppColors.surface,
        onSurface: AppColors.textPrimary,
        error: AppColors.error,
        onError: Colors.white,
        outline: AppColors.border,
      ),
      appBarTheme: const AppBarTheme(
        backgroundColor: AppColors.background,
        foregroundColor: AppColors.textPrimary,
        elevation: 0,
        scrolledUnderElevation: 0.5,
        centerTitle: false,
        titleTextStyle: TextStyle(
          color: AppColors.textPrimary,
          fontSize: 18,
          fontWeight: FontWeight.w600,
        ),
        iconTheme: IconThemeData(color: AppColors.textPrimary),
      ),
      cardTheme: CardThemeData(
        color: AppColors.card,
        elevation: 0,
        shadowColor: Colors.black.withValues(alpha: 0.05),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(14),
          side: const BorderSide(color: AppColors.border, width: 0.5),
        ),
        margin: EdgeInsets.zero,
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: AppColors.background,
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: const BorderSide(color: AppColors.border),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: const BorderSide(color: AppColors.border),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: const BorderSide(color: AppColors.primary, width: 1.5),
        ),
        errorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: const BorderSide(color: AppColors.error),
        ),
        focusedErrorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: const BorderSide(color: AppColors.error, width: 1.5),
        ),
        hintStyle: const TextStyle(
          color: AppColors.textHint,
          fontSize: 14,
          fontWeight: FontWeight.w400,
        ),
        labelStyle: const TextStyle(
          color: AppColors.textSecondary,
          fontSize: 14,
        ),
        errorStyle: const TextStyle(
          color: AppColors.error,
          fontSize: 12,
        ),
      ),
      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          backgroundColor: AppColors.primary,
          foregroundColor: Colors.white,
          elevation: 0,
          padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 14),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(12),
          ),
          textStyle: const TextStyle(
            fontSize: 15,
            fontWeight: FontWeight.w600,
            letterSpacing: -0.1,
          ),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: AppColors.primary,
          side: const BorderSide(color: AppColors.primary),
          padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 14),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(12),
          ),
          textStyle: const TextStyle(
            fontSize: 15,
            fontWeight: FontWeight.w600,
          ),
        ),
      ),
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(
          foregroundColor: AppColors.primary,
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(10),
          ),
          textStyle: const TextStyle(
            fontSize: 14,
            fontWeight: FontWeight.w600,
          ),
        ),
      ),
      bottomNavigationBarTheme: const BottomNavigationBarThemeData(
        backgroundColor: AppColors.background,
        elevation: 0,
        type: BottomNavigationBarType.fixed,
        selectedItemColor: AppColors.primary,
        unselectedItemColor: AppColors.textHint,
        selectedLabelStyle: TextStyle(
          fontSize: 12,
          fontWeight: FontWeight.w600,
        ),
        unselectedLabelStyle: TextStyle(
          fontSize: 12,
          fontWeight: FontWeight.w400,
        ),
        showUnselectedLabels: true,
      ),
      navigationBarTheme: NavigationBarThemeData(
        backgroundColor: AppColors.background,
        elevation: 0,
        height: 64,
        indicatorColor: AppColors.primary.withValues(alpha: 0.12),
        labelTextStyle: WidgetStateProperty.resolveWith((states) {
          if (states.contains(WidgetState.selected)) {
            return const TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w600,
              color: AppColors.primary,
            );
          }
          return const TextStyle(
            fontSize: 12,
            fontWeight: FontWeight.w400,
            color: AppColors.textHint,
          );
        }),
        iconTheme: WidgetStateProperty.resolveWith((states) {
          if (states.contains(WidgetState.selected)) {
            return const IconThemeData(color: AppColors.primary, size: 24);
          }
          return const IconThemeData(color: AppColors.textHint, size: 24);
        }),
      ),
      dividerTheme: const DividerThemeData(
        color: AppColors.divider,
        thickness: 1,
        space: 1,
      ),
      chipTheme: ChipThemeData(
        backgroundColor: AppColors.surface,
        selectedColor: AppColors.primary.withValues(alpha: 0.12),
        labelStyle: const TextStyle(fontSize: 13, fontWeight: FontWeight.w500),
        side: const BorderSide(color: AppColors.border),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(8),
        ),
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
      ),
      floatingActionButtonTheme: const FloatingActionButtonThemeData(
        backgroundColor: AppColors.primary,
        foregroundColor: Colors.white,
        elevation: 2,
        shape: CircleBorder(),
      ),
      dialogTheme: DialogThemeData(
        backgroundColor: AppColors.background,
        elevation: 4,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(16),
        ),
        titleTextStyle: const TextStyle(
          color: AppColors.textPrimary,
          fontSize: 18,
          fontWeight: FontWeight.w600,
        ),
      ),
      snackBarTheme: SnackBarThemeData(
        backgroundColor: AppColors.textPrimary,
        contentTextStyle: const TextStyle(color: Colors.white, fontSize: 14),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(10),
        ),
        behavior: SnackBarBehavior.floating,
      ),
      textTheme: TextTheme(
        headlineLarge: const TextStyle(
          fontSize: 28,
          fontWeight: FontWeight.w700,
          color: AppColors.textPrimary,
          letterSpacing: -0.5,
        ),
        headlineMedium: const TextStyle(
          fontSize: 24,
          fontWeight: FontWeight.w700,
          color: AppColors.textPrimary,
          letterSpacing: -0.3,
        ),
        headlineSmall: const TextStyle(
          fontSize: 20,
          fontWeight: FontWeight.w600,
          color: AppColors.textPrimary,
        ),
        titleLarge: const TextStyle(
          fontSize: 18,
          fontWeight: FontWeight.w600,
          color: AppColors.textPrimary,
        ),
        titleMedium: const TextStyle(
          fontSize: 16,
          fontWeight: FontWeight.w600,
          color: AppColors.textPrimary,
        ),
        titleSmall: const TextStyle(
          fontSize: 14,
          fontWeight: FontWeight.w600,
          color: AppColors.textPrimary,
        ),
        bodyLarge: const TextStyle(
          fontSize: 16,
          fontWeight: FontWeight.w400,
          color: AppColors.textPrimary,
        ),
        bodyMedium: const TextStyle(
          fontSize: 14,
          fontWeight: FontWeight.w400,
          color: AppColors.textPrimary,
        ),
        bodySmall: const TextStyle(
          fontSize: 12,
          fontWeight: FontWeight.w400,
          color: AppColors.textSecondary,
        ),
        labelLarge: const TextStyle(
          fontSize: 14,
          fontWeight: FontWeight.w600,
          color: AppColors.textPrimary,
        ),
        labelMedium: const TextStyle(
          fontSize: 12,
          fontWeight: FontWeight.w500,
          color: AppColors.textSecondary,
        ),
        labelSmall: const TextStyle(
          fontSize: 11,
          fontWeight: FontWeight.w500,
          color: AppColors.textSecondary,
          letterSpacing: 0.5,
        ),
      ),
      extensions: const <ThemeExtension<dynamic>>[
        BrandStyles.light,
      ],
    );
  }

  /// Dark variant. Shares structure with [light] — only colours flip — so
  /// visual density, radii, typography and motion stay identical.
  /// Rationale: users who flip to dark still recognise QORDY the instant
  /// they reopen the app.
  static ThemeData get dark {
    return ThemeData(
      useMaterial3: true,
      brightness: Brightness.dark,
      fontFamily: GoogleFonts.plusJakartaSans().fontFamily,
      primaryColor: AppColors.primaryDarkMode,
      scaffoldBackgroundColor: AppColors.darkScaffoldBackground,
      canvasColor: AppColors.darkBackground,
      colorScheme: const ColorScheme.dark(
        primary: AppColors.primaryDarkMode,
        onPrimary: Colors.white,
        primaryContainer: AppColors.primarySoftDark,
        onPrimaryContainer: Color(0xFFDCE8FF),
        secondary: AppColors.primaryLight,
        onSecondary: Colors.white,
        surface: AppColors.darkSurface,
        onSurface: AppColors.darkTextPrimary,
        surfaceContainerHighest: AppColors.darkSurfaceMuted,
        error: AppColors.error,
        onError: Colors.white,
        outline: AppColors.darkBorder,
      ),
      appBarTheme: const AppBarTheme(
        backgroundColor: AppColors.darkBackground,
        foregroundColor: AppColors.darkTextPrimary,
        elevation: 0,
        scrolledUnderElevation: 0.5,
        centerTitle: false,
        titleTextStyle: TextStyle(
          color: AppColors.darkTextPrimary,
          fontSize: 18,
          fontWeight: FontWeight.w600,
        ),
        iconTheme: IconThemeData(color: AppColors.darkTextPrimary),
      ),
      cardTheme: CardThemeData(
        color: AppColors.darkCard,
        elevation: 0,
        shadowColor: Colors.black.withValues(alpha: 0.35),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(14),
          side: const BorderSide(color: AppColors.darkBorder, width: 0.6),
        ),
        margin: EdgeInsets.zero,
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: AppColors.darkSurfaceMuted,
        contentPadding:
            const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: const BorderSide(color: AppColors.darkBorder),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: const BorderSide(color: AppColors.darkBorder),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: const BorderSide(
              color: AppColors.primaryDarkMode, width: 1.5),
        ),
        errorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: const BorderSide(color: AppColors.error),
        ),
        focusedErrorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: const BorderSide(color: AppColors.error, width: 1.5),
        ),
        hintStyle: const TextStyle(
          color: AppColors.darkTextHint,
          fontSize: 14,
        ),
        labelStyle: const TextStyle(
          color: AppColors.darkTextSecondary,
          fontSize: 14,
        ),
      ),
      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          backgroundColor: AppColors.primaryDarkMode,
          foregroundColor: Colors.white,
          elevation: 0,
          padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 14),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(12),
          ),
          textStyle: const TextStyle(
            fontSize: 15,
            fontWeight: FontWeight.w600,
            letterSpacing: -0.1,
          ),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: AppColors.primaryDarkMode,
          side: const BorderSide(color: AppColors.primaryDarkMode),
          padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 14),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(12),
          ),
        ),
      ),
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(
          foregroundColor: AppColors.primaryDarkMode,
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(10),
          ),
        ),
      ),
      bottomNavigationBarTheme: const BottomNavigationBarThemeData(
        backgroundColor: AppColors.darkBackground,
        elevation: 0,
        type: BottomNavigationBarType.fixed,
        selectedItemColor: AppColors.primaryDarkMode,
        unselectedItemColor: AppColors.darkTextHint,
        selectedLabelStyle:
            TextStyle(fontSize: 12, fontWeight: FontWeight.w600),
        unselectedLabelStyle:
            TextStyle(fontSize: 12, fontWeight: FontWeight.w400),
        showUnselectedLabels: true,
      ),
      navigationBarTheme: NavigationBarThemeData(
        backgroundColor: AppColors.darkBackground,
        elevation: 0,
        height: 64,
        indicatorColor:
            AppColors.primaryDarkMode.withValues(alpha: 0.18),
        labelTextStyle: WidgetStateProperty.resolveWith((states) {
          if (states.contains(WidgetState.selected)) {
            return const TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w600,
              color: AppColors.primaryDarkMode,
            );
          }
          return const TextStyle(
            fontSize: 12,
            fontWeight: FontWeight.w400,
            color: AppColors.darkTextHint,
          );
        }),
        iconTheme: WidgetStateProperty.resolveWith((states) {
          if (states.contains(WidgetState.selected)) {
            return const IconThemeData(
                color: AppColors.primaryDarkMode, size: 24);
          }
          return const IconThemeData(
              color: AppColors.darkTextHint, size: 24);
        }),
      ),
      dividerTheme: const DividerThemeData(
        color: AppColors.darkDivider,
        thickness: 1,
        space: 1,
      ),
      chipTheme: ChipThemeData(
        backgroundColor: AppColors.darkSurfaceMuted,
        selectedColor: AppColors.primaryDarkMode.withValues(alpha: 0.18),
        labelStyle:
            const TextStyle(fontSize: 13, fontWeight: FontWeight.w500),
        side: const BorderSide(color: AppColors.darkBorder),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(8),
        ),
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
      ),
      floatingActionButtonTheme: const FloatingActionButtonThemeData(
        backgroundColor: AppColors.primaryDarkMode,
        foregroundColor: Colors.white,
        elevation: 2,
        shape: CircleBorder(),
      ),
      dialogTheme: DialogThemeData(
        backgroundColor: AppColors.darkSurface,
        elevation: 4,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(16),
        ),
        titleTextStyle: const TextStyle(
          color: AppColors.darkTextPrimary,
          fontSize: 18,
          fontWeight: FontWeight.w600,
        ),
      ),
      snackBarTheme: SnackBarThemeData(
        backgroundColor: AppColors.darkSurface,
        contentTextStyle: const TextStyle(
            color: AppColors.darkTextPrimary, fontSize: 14),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(10),
        ),
        behavior: SnackBarBehavior.floating,
      ),
      textTheme: TextTheme(
        headlineLarge: const TextStyle(
          fontSize: 28,
          fontWeight: FontWeight.w700,
          color: AppColors.darkTextPrimary,
          letterSpacing: -0.5,
        ),
        headlineMedium: const TextStyle(
          fontSize: 24,
          fontWeight: FontWeight.w700,
          color: AppColors.darkTextPrimary,
        ),
        headlineSmall: const TextStyle(
          fontSize: 20,
          fontWeight: FontWeight.w600,
          color: AppColors.darkTextPrimary,
        ),
        titleLarge: const TextStyle(
          fontSize: 18,
          fontWeight: FontWeight.w600,
          color: AppColors.darkTextPrimary,
        ),
        titleMedium: const TextStyle(
          fontSize: 16,
          fontWeight: FontWeight.w600,
          color: AppColors.darkTextPrimary,
        ),
        titleSmall: const TextStyle(
          fontSize: 14,
          fontWeight: FontWeight.w600,
          color: AppColors.darkTextPrimary,
        ),
        bodyLarge: const TextStyle(
          fontSize: 16,
          color: AppColors.darkTextPrimary,
        ),
        bodyMedium: const TextStyle(
          fontSize: 14,
          color: AppColors.darkTextPrimary,
        ),
        bodySmall: const TextStyle(
          fontSize: 12,
          color: AppColors.darkTextSecondary,
        ),
        labelLarge: const TextStyle(
          fontSize: 14,
          fontWeight: FontWeight.w600,
          color: AppColors.darkTextPrimary,
        ),
        labelMedium: const TextStyle(
          fontSize: 12,
          fontWeight: FontWeight.w500,
          color: AppColors.darkTextSecondary,
        ),
        labelSmall: const TextStyle(
          fontSize: 11,
          fontWeight: FontWeight.w500,
          color: AppColors.darkTextSecondary,
          letterSpacing: 0.5,
        ),
      ),
      extensions: const <ThemeExtension<dynamic>>[
        BrandStyles.light,
      ],
    );
  }
}
