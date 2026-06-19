import 'package:flutter/material.dart';

import '../../../config/theme.dart';
import '../../../core/ui/primitives.dart';

/// Shared scaffold used by every admin list page:
/// appbar + pull-to-refresh + loading/empty/error state + floating
/// action button. Screens only have to provide `loader`, `builder`,
/// and (optional) `onAdd`.
class AdminListScaffold<T> extends StatefulWidget {
  const AdminListScaffold({
    super.key,
    required this.title,
    required this.loader,
    required this.builder,
    this.onAdd,
    this.addLabel = 'Ekle',
    this.emptyIcon = Icons.inbox_outlined,
    this.emptyTitle = 'Kayıt yok',
    this.emptyMessage,
    this.controller,
    this.actions,
  });

  /// App bar title.
  final String title;

  /// Async loader; returns the item list (or throws).
  final Future<List<T>> Function() loader;

  /// Per-item builder.
  final Widget Function(BuildContext context, T item, VoidCallback refresh)
      builder;

  /// Optional "+" action — omit to hide the FAB.
  final Future<void> Function()? onAdd;

  /// FAB label tooltip.
  final String addLabel;

  final IconData emptyIcon;
  final String emptyTitle;
  final String? emptyMessage;

  /// External controller to trigger a refresh from the outside.
  final AdminListController<T>? controller;

  /// Extra AppBar actions.
  final List<Widget>? actions;

  @override
  State<AdminListScaffold<T>> createState() => _AdminListScaffoldState<T>();
}

class _AdminListScaffoldState<T> extends State<AdminListScaffold<T>> {
  late Future<List<T>> _future;

  @override
  void initState() {
    super.initState();
    _future = widget.loader();
    widget.controller?._attach(_refresh);
  }

  @override
  void dispose() {
    widget.controller?._detach();
    super.dispose();
  }

  void _refresh() {
    setState(() => _future = widget.loader());
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        title: Text(
          widget.title,
          style: TextStyle(
            color: context.brandTextPrimary,
            fontWeight: FontWeight.w700,
            fontSize: 18,
          ),
        ),
        backgroundColor: Theme.of(context).scaffoldBackgroundColor,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        scrolledUnderElevation: 0.5,
        actions: [
          ...?widget.actions,
          IconButton(
            icon: const Icon(Icons.refresh_rounded),
            color: context.brandTextSecondary,
            onPressed: _refresh,
            tooltip: 'Yenile',
          ),
        ],
      ),
      floatingActionButton: widget.onAdd == null
          ? null
          : FloatingActionButton.extended(
              onPressed: () async {
                await widget.onAdd!();
                _refresh();
              },
              backgroundColor: AppColors.primary,
              icon: const Icon(Icons.add_rounded, color: Colors.white),
              label: Text(
                widget.addLabel,
                style: const TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
      body: RefreshIndicator(
        color: AppColors.primary,
        onRefresh: () async => _refresh(),
        child: FutureBuilder<List<T>>(
          future: _future,
          builder: (context, snap) {
            if (snap.connectionState == ConnectionState.waiting) {
              return const Center(
                child: CircularProgressIndicator(color: AppColors.primary),
              );
            }
            if (snap.hasError) {
              return QEmptyState(
                icon: Icons.error_outline_rounded,
                title: 'Yüklenemedi',
                message: '${snap.error}',
                action: FilledButton.icon(
                  onPressed: _refresh,
                  icon: const Icon(Icons.refresh_rounded, size: 18),
                  label: const Text('Tekrar Dene'),
                  style: FilledButton.styleFrom(
                    backgroundColor: AppColors.primary,
                  ),
                ),
              );
            }
            final items = snap.data ?? const [];
            if (items.isEmpty) {
              return ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                children: [
                  const SizedBox(height: 80),
                  QEmptyState(
                    icon: widget.emptyIcon,
                    title: widget.emptyTitle,
                    message: widget.emptyMessage,
                  ),
                ],
              );
            }
            return ListView.separated(
              padding: const EdgeInsets.fromLTRB(12, 12, 12, 80),
              physics: const AlwaysScrollableScrollPhysics(),
              itemCount: items.length,
              separatorBuilder: (_, __) => const SizedBox(height: 8),
              itemBuilder: (context, i) =>
                  widget.builder(context, items[i], _refresh),
            );
          },
        ),
      ),
    );
  }
}

class AdminListController<T> {
  VoidCallback? _refresh;

  void _attach(VoidCallback cb) => _refresh = cb;
  void _detach() => _refresh = null;

  void refresh() => _refresh?.call();
}
