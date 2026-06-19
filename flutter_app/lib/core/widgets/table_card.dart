import 'package:flutter/material.dart';
import 'package:qordy_app/core/widgets/status_badge.dart';
import 'package:qordy_app/config/theme.dart';

class TableCard extends StatelessWidget {
  final String name;
  final String? zone;
  final String status;
  final double? totalAmount;
  final int activeOrderCount;
  final int capacity;
  final VoidCallback? onTap;

  const TableCard({
    super.key,
    required this.name,
    this.zone,
    required this.status,
    this.totalAmount,
    this.activeOrderCount = 0,
    this.capacity = 4,
    this.onTap,
  });

  Color get _accentColor => StatusBadge.colorForStatus(status);

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final textPrimary =
        isDark ? AppColors.darkTextPrimary : AppColors.textPrimary;
    final textSecondary =
        isDark ? AppColors.darkTextSecondary : AppColors.textSecondary;
    final muted = isDark
        ? AppColors.darkSurfaceMuted
        : AppColors.surfaceMuted;
    final accent = _accentColor;

    return Material(
      color: Colors.transparent,
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(16),
        splashColor: accent.withValues(alpha: 0.10),
        highlightColor: accent.withValues(alpha: 0.06),
        child: Ink(
          decoration: BoxDecoration(
            // Statüye göre yumuşak arka plan gradient'i: dolu masalar
            // sıcak turuncu-hale, boş masalar temiz yeşil parıltı, dolu-
            // talep'li olanlar (servis bekliyor) pembe vurgu alır.
            // Böylece hatırlatıcı olarak masanın durumu kart rengine
            // bakınca hemen okunur.
            gradient: LinearGradient(
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
              colors: [
                Theme.of(context).cardColor,
                accent.withValues(alpha: isDark ? 0.10 : 0.05),
              ],
            ),
            borderRadius: BorderRadius.circular(16),
            border: Border.all(
                color: accent.withValues(alpha: isDark ? 0.40 : 0.22),
                width: 0.8),
            boxShadow: [
              BoxShadow(
                color: accent.withValues(alpha: isDark ? 0.18 : 0.08),
                blurRadius: 14,
                offset: const Offset(0, 4),
              ),
            ],
          ),
          child: ClipRRect(
            borderRadius: BorderRadius.circular(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  height: 4,
                  decoration: BoxDecoration(
                    gradient: LinearGradient(
                      begin: Alignment.centerLeft,
                      end: Alignment.centerRight,
                      colors: [
                        accent,
                        accent.withValues(alpha: 0.55),
                      ],
                    ),
                  ),
                ),
              Padding(
                padding: const EdgeInsets.all(14),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            name,
                            style: TextStyle(
                              fontSize: 16,
                              fontWeight: FontWeight.w800,
                              color: textPrimary,
                              letterSpacing: -0.2,
                            ),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                        StatusBadge(status: status, fontSize: 10.5),
                      ],
                    ),
                    if (zone != null) ...[
                      const SizedBox(height: 6),
                      Row(
                        children: [
                          Icon(Icons.place_outlined,
                              size: 13,
                              color: textSecondary.withValues(alpha: 0.7)),
                          const SizedBox(width: 4),
                          Expanded(
                            child: Text(
                              zone!,
                              style: TextStyle(
                                fontSize: 12,
                                color: textSecondary,
                              ),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                            ),
                          ),
                        ],
                      ),
                    ],
                    const SizedBox(height: 12),
                    Wrap(
                      spacing: 8,
                      runSpacing: 6,
                      children: [
                        _InfoChip(
                          icon: Icons.people_outline_rounded,
                          label: '$capacity kişi',
                          muted: muted,
                          textColor: textSecondary,
                        ),
                        if (activeOrderCount > 0)
                          _InfoChip(
                            icon: Icons.receipt_long_outlined,
                            label: '$activeOrderCount sipariş',
                            muted: muted,
                            textColor: textSecondary,
                          ),
                      ],
                    ),
                    if (totalAmount != null && totalAmount! > 0) ...[
                      const SizedBox(height: 12),
                      Container(
                        width: double.infinity,
                        padding: const EdgeInsets.symmetric(
                            horizontal: 12, vertical: 10),
                        decoration: BoxDecoration(
                          color: accent.withValues(
                              alpha: isDark ? 0.22 : 0.1),
                          borderRadius: BorderRadius.circular(10),
                          border: Border.all(
                            color: accent.withValues(alpha: 0.3),
                            width: 0.5,
                          ),
                        ),
                        child: Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Text(
                              'Toplam',
                              style: TextStyle(
                                fontSize: 12,
                                fontWeight: FontWeight.w500,
                                color: textSecondary,
                              ),
                            ),
                            Text(
                              '₺${totalAmount!.toStringAsFixed(2)}',
                              style: TextStyle(
                                fontSize: 15,
                                fontWeight: FontWeight.w800,
                                color: textPrimary,
                                letterSpacing: -0.2,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ],
                ),
              ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _InfoChip extends StatelessWidget {
  final IconData icon;
  final String label;
  final Color muted;
  final Color textColor;

  const _InfoChip({
    required this.icon,
    required this.label,
    required this.muted,
    required this.textColor,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: muted,
        borderRadius: BorderRadius.circular(999),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 13, color: textColor.withValues(alpha: 0.75)),
          const SizedBox(width: 4),
          Text(
            label,
            style: TextStyle(
              fontSize: 11.5,
              fontWeight: FontWeight.w600,
              color: textColor,
            ),
          ),
        ],
      ),
    );
  }
}
