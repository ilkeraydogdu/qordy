import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:google_fonts/google_fonts.dart';

import 'design_tokens.dart';

/// Material 3 tema — Qordy marka kimliği, light + dark.
class AppTheme {
 AppTheme._();

 static ThemeData light() {
 final scheme = ColorScheme.fromSeed(
 seedColor: QordyColors.primary,
 brightness: Brightness.light,
 primary: QordyColors.primary,
 secondary: QordyColors.secondary,
 tertiary: QordyColors.tertiary,
 error: QordyColors.error,
 surface: QordyColors.surface,
 onSurface: QordyColors.onSurface,
 );

 return _baseTheme(scheme).copyWith(
 scaffoldBackgroundColor: QordyColors.surfaceDim,
 appBarTheme: AppBarTheme(
 backgroundColor: QordyColors.surface,
 foregroundColor: QordyColors.onSurface,
 elevation: 0,
 scrolledUnderElevation: 1,
 centerTitle: false,
 systemOverlayStyle: SystemUiOverlayStyle.dark,
 titleTextStyle: GoogleFonts.inter(
 textStyle: QordyTypography.titleLarge.copyWith(
 color: QordyColors.onSurface,
 ),
 ),
 ),
 cardTheme: CardThemeData(
 color: QordyColors.surface,
 elevation: 0,
 shape: RoundedRectangleBorder(
 borderRadius: QordyRadius.brLg,
 side: const BorderSide(color: QordyColors.outlineVariant, width: 1),
 ),
 ),
 );
 }

 static ThemeData dark() {
 final scheme = ColorScheme.fromSeed(
 seedColor: QordyColors.primary,
 brightness: Brightness.dark,
 primary: QordyColors.darkPrimary,
 secondary: QordyColors.secondary,
 tertiary: QordyColors.tertiary,
 error: QordyColors.error,
 surface: QordyColors.darkSurface,
 onSurface: QordyColors.darkOnSurface,
 );

 return _baseTheme(scheme).copyWith(
 scaffoldBackgroundColor: const Color(0xFF0A0F1F),
 appBarTheme: AppBarTheme(
 backgroundColor: QordyColors.darkSurface,
 foregroundColor: QordyColors.darkOnSurface,
 elevation: 0,
 scrolledUnderElevation: 1,
 centerTitle: false,
 systemOverlayStyle: SystemUiOverlayStyle.light,
 titleTextStyle: GoogleFonts.inter(
 textStyle: QordyTypography.titleLarge.copyWith(
 color: QordyColors.darkOnSurface,
 ),
 ),
 ),
 cardTheme: CardThemeData(
 color: const Color(0xFF1A2235),
 elevation: 0,
 shape: RoundedRectangleBorder(
 borderRadius: QordyRadius.brLg,
 side: const BorderSide(color: Color(0xFF2A3550), width: 1),
 ),
 ),
 );
 }

 static ThemeData _baseTheme(ColorScheme scheme) {
 final base = ThemeData(useMaterial3: true, colorScheme: scheme);
 return base.copyWith(
 textTheme: GoogleFonts.interTextTheme(base.textTheme).copyWith(
 displayLarge: QordyTypography.displayLarge,
 headlineMedium: QordyTypography.headlineMedium,
 titleLarge: QordyTypography.titleLarge,
 titleMedium: QordyTypography.titleMedium,
 bodyLarge: QordyTypography.bodyLarge,
 bodyMedium: QordyTypography.bodyMedium,
 bodySmall: QordyTypography.bodySmall,
 labelLarge: QordyTypography.labelLarge,
 labelSmall: QordyTypography.labelSmall,
 ),
 elevatedButtonTheme: ElevatedButtonThemeData(
 style: ElevatedButton.styleFrom(
 minimumSize: const Size.fromHeight(48),
 shape: RoundedRectangleBorder(borderRadius: QordyRadius.brMd),
 textStyle: QordyTypography.labelLarge,
 ),
 ),
 filledButtonTheme: FilledButtonThemeData(
 style: FilledButton.styleFrom(
 minimumSize: const Size.fromHeight(48),
 shape: RoundedRectangleBorder(borderRadius: QordyRadius.brMd),
 textStyle: QordyTypography.labelLarge,
 ),
 ),
 outlinedButtonTheme: OutlinedButtonThemeData(
 style: OutlinedButton.styleFrom(
 minimumSize: const Size.fromHeight(48),
 shape: RoundedRectangleBorder(borderRadius: QordyRadius.brMd),
 textStyle: QordyTypography.labelLarge,
 side: const BorderSide(color: QordyColors.outline),
 ),
 ),
 inputDecorationTheme: InputDecorationTheme(
 filled: true,
 fillColor: scheme.brightness == Brightness.light
 ? QordyColors.surfaceContainer
 : const Color(0xFF1A2235),
 contentPadding: const EdgeInsets.symmetric(
 horizontal: QordySpacing.lg,
 vertical: QordySpacing.md,
 ),
 border: OutlineInputBorder(
 borderRadius: QordyRadius.brMd,
 borderSide: BorderSide(color: QordyColors.outline.withValues(alpha: 0.5)),
 ),
 enabledBorder: OutlineInputBorder(
 borderRadius: QordyRadius.brMd,
 borderSide: const BorderSide(color: QordyColors.outline),
 ),
 focusedBorder: OutlineInputBorder(
 borderRadius: QordyRadius.brMd,
 borderSide: BorderSide(color: scheme.primary, width: 2),
 ),
 errorBorder: OutlineInputBorder(
 borderRadius: QordyRadius.brMd,
 borderSide: const BorderSide(color: QordyColors.error),
 ),
 focusedErrorBorder: OutlineInputBorder(
 borderRadius: QordyRadius.brMd,
 borderSide: const BorderSide(color: QordyColors.error, width: 2),
 ),
 ),
 navigationBarTheme: NavigationBarThemeData(
 backgroundColor: QordyColors.surface,
 indicatorColor: scheme.primaryContainer,
 labelTextStyle: WidgetStatePropertyAll(QordyTypography.labelSmall),
 ),
 dividerTheme: const DividerThemeData(
 color: QordyColors.outlineVariant,
 thickness: 1,
 space: 1,
 ),
 );
 }
}
