import 'package:flutter/material.dart';
import '../../config/theme.dart';

class AppScaffold extends StatelessWidget {
  final String title;
  final Widget body;
  final List<Widget>? actions;
  final Widget? leading;
  final Widget? floatingActionButton;
  final FloatingActionButtonLocation? floatingActionButtonLocation;
  final Widget? drawer;
  final Future<void> Function()? onRefresh;
  final bool showAppBar;
  final PreferredSizeWidget? bottom;
  final Color? backgroundColor;

  const AppScaffold({
    super.key,
    required this.title,
    required this.body,
    this.actions,
    this.leading,
    this.floatingActionButton,
    this.floatingActionButtonLocation,
    this.drawer,
    this.onRefresh,
    this.showAppBar = true,
    this.bottom,
    this.backgroundColor,
  });

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;
    final bg = backgroundColor ?? theme.scaffoldBackgroundColor;
    final foreground =
        isDark ? AppColors.darkTextPrimary : AppColors.textPrimary;

    Widget content = body;

    if (onRefresh != null) {
      content = RefreshIndicator(
        onRefresh: onRefresh!,
        color: AppColors.primary,
        child: content,
      );
    }

    return Scaffold(
      backgroundColor: bg,
      appBar: showAppBar
          ? AppBar(
              title: Text(
                title,
                style: TextStyle(
                  color: foreground,
                  fontWeight: FontWeight.w600,
                  fontSize: 18,
                ),
              ),
              backgroundColor: bg,
              surfaceTintColor: Colors.transparent,
              elevation: 0,
              scrolledUnderElevation: 0.5,
              centerTitle: false,
              leading: leading,
              actions: actions,
              bottom: bottom,
              iconTheme: IconThemeData(color: foreground),
            )
          : null,
      body: SafeArea(
        top: !showAppBar,
        child: content,
      ),
      floatingActionButton: floatingActionButton,
      floatingActionButtonLocation: floatingActionButtonLocation,
      drawer: drawer,
    );
  }
}
