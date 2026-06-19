/// Defensive helpers for parsing untrusted JSON payloads.
///
/// The Qordy backend is loosely typed PHP — a numeric field can come back
/// as an `int`, `String` or even `null`. Before introducing these helpers
/// features would blindly `as Map<String, dynamic>` the response body,
/// which crashed the entire screen on any schema drift.
///
/// Usage:
/// ```dart
/// final map = data.asMap();
/// final items = map.listOf<MenuItem>('items', MenuItem.fromJson);
/// final total = map.numOr('total', 0).toDouble();
/// ```
library;

Map<String, dynamic> asJsonMap(dynamic value) {
  if (value is Map<String, dynamic>) return value;
  if (value is Map) {
    return value.map((k, v) => MapEntry(k.toString(), v));
  }
  return const <String, dynamic>{};
}

List<dynamic> asJsonList(dynamic value) {
  if (value is List) return value;
  return const <dynamic>[];
}

/// Extracts a `List` out of a loosely-shaped JSON payload.
///
/// The Qordy mobile API is inconsistent: some endpoints wrap payloads in
/// `{data: [...]}`, some in `{data: {items: [...]}}`, and some at the
/// root (`{success: true, orders: [...]}`). Rather than special-case every
/// call site this helper walks the input looking for the most plausible
/// list.
///
/// Lookup order:
///   1. If [value] is already a `List`, return it.
///   2. If [preferKeys] is provided, try each of those keys first and
///      return the first one whose value is a `List` (or a Map containing
///      a `List`).
///   3. Fall back to a small set of well-known keys
///      (`items`, `data`, `list`, `results`, `rows`, `records`, `entries`).
///   4. As a last resort, return the first `List` value found in the Map.
///   5. If nothing matches, returns an empty list.
List<dynamic> pickList(dynamic value, [List<String>? preferKeys]) {
  if (value is List) return value;
  if (value is! Map) return const <dynamic>[];

  final map = value is Map<String, dynamic>
      ? value
      : value.map<String, dynamic>((k, v) => MapEntry(k.toString(), v));

  List<dynamic>? lookup(String key) {
    final v = map[key];
    if (v is List) return v;
    if (v is Map) {
      for (final inner in v.values) {
        if (inner is List) return inner;
      }
    }
    return null;
  }

  if (preferKeys != null) {
    for (final k in preferKeys) {
      final hit = lookup(k);
      if (hit != null) return hit;
    }
  }

  const commonKeys = <String>[
    'items',
    'data',
    'list',
    'results',
    'rows',
    'records',
    'entries',
  ];
  for (final k in commonKeys) {
    final hit = lookup(k);
    if (hit != null) return hit;
  }

  for (final v in map.values) {
    if (v is List) return v;
  }
  return const <dynamic>[];
}

/// Convenience over [pickList] that also maps each element into [T] using
/// [from]. Gracefully skips non-map entries instead of throwing.
List<T> pickListOf<T>(
  dynamic value,
  T Function(Map<String, dynamic> item) from, {
  List<String>? preferKeys,
}) {
  final raw = pickList(value, preferKeys);
  final out = <T>[];
  for (final e in raw) {
    if (e is Map<String, dynamic>) {
      out.add(from(e));
    } else if (e is Map) {
      out.add(from(e.map((k, v) => MapEntry(k.toString(), v))));
    }
  }
  return out;
}

extension SafeJsonMap on Map<String, dynamic> {
  Map<String, dynamic> mapOf(String key) {
    final v = this[key];
    return asJsonMap(v);
  }

  List<T> listOf<T>(String key, T Function(Map<String, dynamic>) from) {
    final v = asJsonList(this[key]);
    return v
        .whereType<Object>()
        .map((e) => from(asJsonMap(e)))
        .toList(growable: false);
  }

  List<String> stringList(String key) {
    final v = asJsonList(this[key]);
    return v.map((e) => e?.toString() ?? '').toList(growable: false);
  }

  String stringOr(String key, String fallback) {
    final v = this[key];
    if (v == null) return fallback;
    return v.toString();
  }

  String? stringOrNull(String key) {
    final v = this[key];
    if (v == null) return null;
    final s = v.toString();
    return s.isEmpty ? null : s;
  }

  int intOr(String key, int fallback) {
    final v = this[key];
    if (v is int) return v;
    if (v is num) return v.toInt();
    if (v is String) return int.tryParse(v) ?? fallback;
    return fallback;
  }

  double doubleOr(String key, double fallback) {
    final v = this[key];
    if (v is double) return v;
    if (v is num) return v.toDouble();
    if (v is String) return double.tryParse(v) ?? fallback;
    return fallback;
  }

  num numOr(String key, num fallback) {
    final v = this[key];
    if (v is num) return v;
    if (v is String) return num.tryParse(v) ?? fallback;
    return fallback;
  }

  int? intOrNull(String key) {
    final v = this[key];
    if (v == null) return null;
    if (v is int) return v;
    if (v is num) return v.toInt();
    if (v is String) return int.tryParse(v);
    return null;
  }

  double? doubleOrNull(String key) {
    final v = this[key];
    if (v == null) return null;
    if (v is double) return v;
    if (v is num) return v.toDouble();
    if (v is String) return double.tryParse(v);
    return null;
  }

  bool? boolOrNull(String key) {
    final v = this[key];
    if (v == null) return null;
    if (v is bool) return v;
    if (v is num) return v != 0;
    if (v is String) {
      final s = v.toLowerCase();
      if (s == 'true' || s == '1' || s == 'yes') return true;
      if (s == 'false' || s == '0' || s == 'no' || s.isEmpty) return false;
    }
    return null;
  }

  /// Reads the first non-null value across a list of candidate keys and
  /// coerces to [String]. Useful when the backend ships camelCase and
  /// snake_case variants side-by-side.
  String? pickString(List<String> keys) {
    for (final k in keys) {
      final v = this[k];
      if (v == null) continue;
      if (v is String) return v.isEmpty ? null : v;
      return v.toString();
    }
    return null;
  }

  int? pickInt(List<String> keys) {
    for (final k in keys) {
      final v = this[k];
      if (v == null) continue;
      if (v is int) return v;
      if (v is num) return v.toInt();
      if (v is String) {
        final p = int.tryParse(v);
        if (p != null) return p;
      }
    }
    return null;
  }

  double? pickDouble(List<String> keys) {
    for (final k in keys) {
      final v = this[k];
      if (v == null) continue;
      if (v is double) return v;
      if (v is num) return v.toDouble();
      if (v is String) {
        final p = double.tryParse(v);
        if (p != null) return p;
      }
    }
    return null;
  }

  bool? pickBool(List<String> keys) {
    for (final k in keys) {
      final v = this[k];
      if (v == null) continue;
      if (v is bool) return v;
      if (v is num) return v != 0;
      if (v is String) {
        final s = v.toLowerCase();
        if (s == 'true' || s == '1' || s == 'yes') return true;
        if (s == 'false' || s == '0' || s == 'no' || s.isEmpty) return false;
      }
    }
    return null;
  }

  bool boolOr(String key, bool fallback) {
    final v = this[key];
    if (v is bool) return v;
    if (v is num) return v != 0;
    if (v is String) {
      final s = v.toLowerCase();
      if (s == 'true' || s == '1' || s == 'yes') return true;
      if (s == 'false' || s == '0' || s == 'no' || s.isEmpty) return false;
    }
    return fallback;
  }

  DateTime? dateTimeOrNull(String key) {
    final v = this[key];
    if (v is DateTime) return v;
    if (v is String && v.isNotEmpty) return DateTime.tryParse(v);
    return null;
  }
}
