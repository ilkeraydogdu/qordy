/// Canonical order lifecycle states.
///
/// The backend normalises statuses to these UPPER_SNAKE strings, so
/// screens and cubits should compare against these constants instead
/// of string-literalling them everywhere (which drifts over time —
/// e.g. `'PENDING'` vs `'pending'` vs `'NEW'`).
class OrderStatus {
  const OrderStatus._();

  static const String pending = 'PENDING';
  static const String preparing = 'PREPARING';
  static const String ready = 'READY';
  static const String served = 'SERVED';
  static const String completed = 'COMPLETED';
  static const String cancelled = 'CANCELLED';

  /// Ordered list — useful for building filter tab strips.
  static const List<String> all = [
    pending,
    preparing,
    ready,
    served,
    completed,
    cancelled,
  ];

  /// Statuses a kitchen station cares about (active work).
  static const List<String> kitchenActive = [pending, preparing, ready];

  /// Statuses a waiter station cares about (deliverable).
  static const List<String> waiterActive = [ready];

  /// Terminal states — the order is no longer actionable.
  static const Set<String> terminal = {completed, cancelled};

  /// Normalises any free-form status string to a canonical value, or
  /// returns the input trimmed/uppercased if we don't recognise it.
  static String normalise(String? raw) {
    final v = (raw ?? '').trim().toUpperCase();
    if (v.isEmpty) return pending;
    return v;
  }

  /// Human-readable Turkish label for a status. Kept here (not in the
  /// screen) so every screen reads the same copy.
  static String label(String status) {
    switch (normalise(status)) {
      case pending:
        return 'Bekleyen';
      case preparing:
        return 'Hazırlanıyor';
      case ready:
        return 'Hazır';
      case served:
        return 'Servis Edildi';
      case completed:
        return 'Tamamlandı';
      case cancelled:
        return 'İptal Edildi';
    }
    return status;
  }
}
