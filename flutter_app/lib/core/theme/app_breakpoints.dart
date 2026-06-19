import 'package:flutter/material.dart';

/// Responsive layout helpers (telefon, tablet, geniş ekran).
class AppBreakpoints {
  AppBreakpoints._();

  static double widthOf(BuildContext context) =>
      MediaQuery.sizeOf(context).width;

  static double shortestSideOf(BuildContext context) =>
      MediaQuery.sizeOf(context).shortestSide;

  /// Küçük tablet ve üzeri (600dp kısa kenar — Material guideline).
  static bool isTablet(BuildContext context) =>
      shortestSideOf(context) >= 600;

  static bool isDesktopWide(BuildContext context) =>
      widthOf(context) >= 900;

  /// İki sütunlu içerik (ör. mutfak listesi + detay).
  static bool useTwoColumn(BuildContext context) =>
      widthOf(context) >= 720;

  static EdgeInsets pagePadding(BuildContext context) {
    final w = widthOf(context);
    if (w >= 900) return const EdgeInsets.symmetric(horizontal: 32, vertical: 16);
    if (w >= 600) return const EdgeInsets.symmetric(horizontal: 24, vertical: 12);
    return const EdgeInsets.symmetric(horizontal: 16, vertical: 8);
  }
}
