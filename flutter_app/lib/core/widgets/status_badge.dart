import 'package:flutter/material.dart';
import '../../config/theme.dart';

class StatusBadge extends StatelessWidget {
  final String status;
  final String? label;
  final double fontSize;

  const StatusBadge({
    super.key,
    required this.status,
    this.label,
    this.fontSize = 12,
  });

  static const Map<String, _BadgeStyle> _styles = {
    'PENDING': _BadgeStyle(AppColors.warningBright, 'Bekliyor'),
    'PREPARING': _BadgeStyle(AppColors.primary, 'Hazırlanıyor'),
    'READY': _BadgeStyle(AppColors.successAlt, 'Hazır'),
    'SERVED': _BadgeStyle(AppColors.textSecondary, 'Teslim Edildi'),
    'CANCELLED': _BadgeStyle(AppColors.errorBright, 'İptal'),
    'FREE': _BadgeStyle(AppColors.successAlt, 'Boş'),
    'OCCUPIED': _BadgeStyle(AppColors.primary, 'Dolu'),
    'RESERVED': _BadgeStyle(AppColors.primaryDark, 'Rezerve'),
    'PAYMENT_PENDING': _BadgeStyle(AppColors.accentPurple, 'Ödeme Bekliyor'),
  };

  @override
  Widget build(BuildContext context) {
    final style = _styles[status.toUpperCase()] ??
        const _BadgeStyle(AppColors.textSecondary, 'Bilinmiyor');

    return Container(
      padding: EdgeInsets.symmetric(
        horizontal: fontSize * 0.9,
        vertical: fontSize * 0.32,
      ),
      decoration: BoxDecoration(
        // Rozeti tanınabilir yapan temel öğe: hafif dolgu + ince
        // renk sınırı + yüksek radius. Böylece kart üzerinde şeffaf
        // yüzerken bile statü rengi net okunuyor.
        color: style.color.withValues(alpha: 0.14),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(
          color: style.color.withValues(alpha: 0.28),
          width: 0.6,
        ),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: fontSize * 0.55,
            height: fontSize * 0.55,
            decoration: BoxDecoration(
              color: style.color,
              shape: BoxShape.circle,
              boxShadow: [
                BoxShadow(
                  color: style.color.withValues(alpha: 0.45),
                  blurRadius: 4,
                ),
              ],
            ),
          ),
          SizedBox(width: fontSize * 0.4),
          Text(
            label ?? style.defaultLabel,
            style: TextStyle(
              color: style.color,
              fontSize: fontSize,
              fontWeight: FontWeight.w700,
              letterSpacing: 0.1,
            ),
          ),
        ],
      ),
    );
  }

  static Color colorForStatus(String status) {
    return _styles[status.toUpperCase()]?.color ?? AppColors.textSecondary;
  }
}

class _BadgeStyle {
  final Color color;
  final String defaultLabel;

  const _BadgeStyle(this.color, this.defaultLabel);
}
