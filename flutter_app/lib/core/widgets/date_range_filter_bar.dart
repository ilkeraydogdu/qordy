import 'package:flutter/material.dart';

import '../../config/theme.dart';

/// Represents the currently selected date-range preset.
///
/// The mobile list screens share this vocabulary so a user picking
/// "Bu Hafta" in Siparişler also reads naturally in Raporlar.
enum DateRangePreset {
  today,
  yesterday,
  last7Days,
  thisMonth,
  lastMonth,
  custom,
}

extension DateRangePresetLabels on DateRangePreset {
  String get label {
    switch (this) {
      case DateRangePreset.today:
        return 'Bugün';
      case DateRangePreset.yesterday:
        return 'Dün';
      case DateRangePreset.last7Days:
        return 'Son 7 Gün';
      case DateRangePreset.thisMonth:
        return 'Bu Ay';
      case DateRangePreset.lastMonth:
        return 'Geçen Ay';
      case DateRangePreset.custom:
        return 'Özel Aralık';
    }
  }

  /// Returns start/end as midnight-aligned `DateTime` pair. `custom`
  /// returns null so callers can fall back to user-provided range.
  DateTimeRange? toRange([DateTime? now]) {
    final ref = now ?? DateTime.now();
    final startOfToday = DateTime(ref.year, ref.month, ref.day);
    switch (this) {
      case DateRangePreset.today:
        return DateTimeRange(
          start: startOfToday,
          end: startOfToday.add(const Duration(days: 1)),
        );
      case DateRangePreset.yesterday:
        final yesterday = startOfToday.subtract(const Duration(days: 1));
        return DateTimeRange(start: yesterday, end: startOfToday);
      case DateRangePreset.last7Days:
        return DateTimeRange(
          start: startOfToday.subtract(const Duration(days: 6)),
          end: startOfToday.add(const Duration(days: 1)),
        );
      case DateRangePreset.thisMonth:
        final start = DateTime(ref.year, ref.month, 1);
        final nextMonth = DateTime(ref.year, ref.month + 1, 1);
        return DateTimeRange(start: start, end: nextMonth);
      case DateRangePreset.lastMonth:
        final start = DateTime(ref.year, ref.month - 1, 1);
        final end = DateTime(ref.year, ref.month, 1);
        return DateTimeRange(start: start, end: end);
      case DateRangePreset.custom:
        return null;
    }
  }
}

/// Compact scrollable chip row for selecting a date range.
///
/// Usage:
/// ```dart
/// DateRangeFilterBar(
///   preset: currentPreset,
///   customRange: currentRange,
///   onChanged: (preset, range) => cubit.setDateRange(preset, range),
/// )
/// ```
///
/// A "Özel Aralık" selection triggers [showDateRangePicker] so the user
/// can pick arbitrary from/to dates; the callback receives
/// [DateRangePreset.custom] plus the chosen [DateTimeRange].
class DateRangeFilterBar extends StatelessWidget {
  const DateRangeFilterBar({
    super.key,
    required this.preset,
    required this.onChanged,
    this.customRange,
    this.padding = const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
  });

  final DateRangePreset preset;
  final DateTimeRange? customRange;
  final EdgeInsetsGeometry padding;
  final void Function(DateRangePreset preset, DateTimeRange? range) onChanged;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: padding,
      child: SizedBox(
        height: 36,
        child: ListView.separated(
          scrollDirection: Axis.horizontal,
          itemCount: DateRangePreset.values.length,
          separatorBuilder: (_, __) => const SizedBox(width: 8),
          itemBuilder: (context, i) {
            final p = DateRangePreset.values[i];
            final selected = p == preset;
            final label = p == DateRangePreset.custom && customRange != null
                ? _formatRange(customRange!)
                : p.label;
            return _FilterChip(
              label: label,
              selected: selected,
              onTap: () => _handleTap(context, p),
            );
          },
        ),
      ),
    );
  }

  Future<void> _handleTap(BuildContext context, DateRangePreset p) async {
    if (p == DateRangePreset.custom) {
      final now = DateTime.now();
      final picked = await showDateRangePicker(
        context: context,
        firstDate: DateTime(now.year - 2, 1, 1),
        lastDate: DateTime(now.year, 12, 31),
        initialDateRange: customRange ??
            DateTimeRange(
              start: now.subtract(const Duration(days: 6)),
              end: now,
            ),
        helpText: 'Tarih Aralığı Seç',
        cancelText: 'İptal',
        confirmText: 'Uygula',
        saveText: 'Uygula',
        builder: (ctx, child) => Theme(
          data: Theme.of(ctx).copyWith(
            colorScheme: Theme.of(ctx).colorScheme.copyWith(
                  primary: AppColors.primary,
                ),
          ),
          child: child!,
        ),
      );
      if (picked == null) return;
      final normalised = DateTimeRange(
        start: DateTime(picked.start.year, picked.start.month, picked.start.day),
        end: DateTime(picked.end.year, picked.end.month, picked.end.day)
            .add(const Duration(days: 1)),
      );
      onChanged(DateRangePreset.custom, normalised);
      return;
    }
    onChanged(p, p.toRange());
  }

  String _formatRange(DateTimeRange range) {
    final startLabel = _formatDay(range.start);
    final endExclusive = range.end;
    final endLabel = _formatDay(
      endExclusive.subtract(const Duration(days: 1)),
    );
    if (startLabel == endLabel) return startLabel;
    return '$startLabel → $endLabel';
  }

  String _formatDay(DateTime dt) {
    final months = [
      'Oca', 'Şub', 'Mar', 'Nis', 'May', 'Haz',
      'Tem', 'Ağu', 'Eyl', 'Eki', 'Kas', 'Ara',
    ];
    return '${dt.day} ${months[dt.month - 1]}';
  }
}

class _FilterChip extends StatelessWidget {
  const _FilterChip({
    required this.label,
    required this.selected,
    required this.onTap,
  });

  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final bg = selected ? AppColors.primary : context.brandSurface;
    final fg = selected ? Colors.white : context.brandTextSecondary;
    final border = selected ? AppColors.primary : context.brandBorder;
    return Material(
      color: bg,
      borderRadius: BorderRadius.circular(10),
      child: InkWell(
        borderRadius: BorderRadius.circular(10),
        onTap: onTap,
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(10),
            border: Border.all(color: border, width: 1),
          ),
          alignment: Alignment.center,
          child: Text(
            label,
            style: TextStyle(
              color: fg,
              fontSize: 12.5,
              fontWeight: FontWeight.w600,
              letterSpacing: 0.1,
            ),
          ),
        ),
      ),
    );
  }
}
