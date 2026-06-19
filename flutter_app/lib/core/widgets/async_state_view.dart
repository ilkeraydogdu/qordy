import 'package:flutter/material.dart';

import 'app_error_widget.dart';
import 'empty_state.dart';
import 'loading_widget.dart';

/// Tri-state container used by list / detail screens to render loading,
/// empty, error and success in a single declarative expression.
///
/// Motivation — most screens (orders, tables, notifications, analytics,
/// reports, staff, menu, etc.) hand-rolled their own loading / empty /
/// error branches. This caused inconsistent UX and a lot of copy-pasted
/// `if/else` ladders. `AsyncStateView` centralises that pattern so that
/// every data screen behaves identically.
///
/// Example:
/// ```dart
/// AsyncStateView<List<Order>>(
///   status: _status,
///   data: _orders,
///   errorMessage: _error,
///   isEmpty: (list) => list.isEmpty,
///   emptyTitle: 'Henüz sipariş yok',
///   emptyDescription: 'Yeni siparişler burada görünecek.',
///   onRetry: _load,
///   builder: (list) => OrdersList(orders: list),
/// )
/// ```
class AsyncStateView<T> extends StatelessWidget {
  const AsyncStateView({
    super.key,
    required this.status,
    required this.builder,
    this.data,
    this.errorMessage,
    this.isEmpty,
    this.loadingMessage,
    this.emptyIcon = Icons.inbox_outlined,
    this.emptyTitle = 'Gösterilecek veri yok',
    this.emptyDescription,
    this.emptyActionLabel,
    this.onEmptyAction,
    this.onRetry,
  });

  final AsyncStatus status;
  final T? data;
  final String? errorMessage;

  /// Treated as empty when this predicate returns true.
  final bool Function(T data)? isEmpty;

  final String? loadingMessage;

  final IconData emptyIcon;
  final String emptyTitle;
  final String? emptyDescription;
  final String? emptyActionLabel;
  final VoidCallback? onEmptyAction;

  final VoidCallback? onRetry;

  /// Called when we have non-empty data.
  final Widget Function(T data) builder;

  @override
  Widget build(BuildContext context) {
    switch (status) {
      case AsyncStatus.initial:
      case AsyncStatus.loading:
        return LoadingWidget(message: loadingMessage);
      case AsyncStatus.failure:
        return AppErrorWidget(
          message: errorMessage ?? 'Bir hata oluştu',
          onRetry: onRetry,
        );
      case AsyncStatus.success:
        final value = data;
        if (value == null || (isEmpty?.call(value) ?? false)) {
          return EmptyState(
            icon: emptyIcon,
            title: emptyTitle,
            subtitle: emptyDescription ?? '',
            actionLabel: emptyActionLabel,
            onAction: onEmptyAction,
          );
        }
        return builder(value);
    }
  }
}

/// Coarse-grained async status used by [AsyncStateView].
///
/// Most Cubits already expose a richer sealed-state hierarchy, but those
/// typically map 1:1 to these four buckets. Keeping the surface minimal
/// avoids leaking feature-specific states into reusable UI.
enum AsyncStatus { initial, loading, success, failure }
