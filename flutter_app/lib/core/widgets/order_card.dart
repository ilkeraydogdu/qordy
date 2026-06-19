import 'package:flutter/material.dart';
import 'package:qordy_app/core/widgets/status_badge.dart';
import '../../config/theme.dart';

class OrderCardItem {
  final String name;
  final int quantity;

  const OrderCardItem({required this.name, required this.quantity});
}

class OrderCard extends StatelessWidget {
  final String orderId;
  final String tableName;
  final String status;
  final String time;
  final double total;
  final List<OrderCardItem> items;
  final ValueChanged<String>? onStatusChange;

  const OrderCard({
    super.key,
    required this.orderId,
    required this.tableName,
    required this.status,
    required this.time,
    required this.total,
    required this.items,
    this.onStatusChange,
  });

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final borderCol = isDark ? AppColors.darkBorder : AppColors.border;
    return Container(
      decoration: BoxDecoration(
        color: Theme.of(context).cardColor,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: borderCol, width: 0.6),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: isDark ? 0.3 : 0.04),
            blurRadius: 12,
            offset: const Offset(0, 3),
          ),
          BoxShadow(
            color: Colors.black.withValues(alpha: isDark ? 0.18 : 0.02),
            blurRadius: 4,
            offset: const Offset(0, 1),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _buildHeader(context, isDark),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16),
            child: Divider(height: 1, color: borderCol.withValues(alpha: 0.6)),
          ),
          _buildItemList(context, isDark),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16),
            child: Divider(height: 1, color: borderCol.withValues(alpha: 0.6)),
          ),
          _buildFooter(context, isDark),
        ],
      ),
    );
  }

  Widget _buildHeader(BuildContext context, bool isDark) {
    final textSecondary =
        isDark ? AppColors.darkTextSecondary : AppColors.textSecondary;
    final textHint =
        isDark ? AppColors.darkTextHint : AppColors.textHint;
    final chipBg = isDark
        ? AppColors.darkSurfaceMuted
        : AppColors.divider;
    return Padding(
      padding: const EdgeInsets.all(16),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Text(
                      '#$orderId',
                      style: const TextStyle(
                        fontSize: 15,
                        fontWeight: FontWeight.w700,
                        color: AppColors.primary,
                      ),
                    ),
                    const SizedBox(width: 10),
                    Container(
                      padding: const EdgeInsets.symmetric(
                          horizontal: 8, vertical: 3),
                      decoration: BoxDecoration(
                        color: chipBg,
                        borderRadius: BorderRadius.circular(6),
                      ),
                      child: Text(
                        tableName,
                        style: TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.w600,
                          color: textSecondary,
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 6),
                Row(
                  children: [
                    Icon(Icons.access_time_rounded,
                        size: 14, color: textHint),
                    const SizedBox(width: 4),
                    Text(
                      time,
                      style: TextStyle(
                        fontSize: 12,
                        color: textHint,
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
          StatusBadge(status: status),
        ],
      ),
    );
  }

  Widget _buildItemList(BuildContext context, bool isDark) {
    final textSecondary =
        isDark ? AppColors.darkTextSecondary : AppColors.textSecondary;
    final chipBg =
        isDark ? AppColors.darkSurfaceMuted : AppColors.divider;
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      child: Column(
        children: items.map((item) {
          return Padding(
            padding: const EdgeInsets.symmetric(vertical: 3),
            child: Row(
              children: [
                Container(
                  width: 6,
                  height: 6,
                  decoration: BoxDecoration(
                    color: AppColors.primary.withValues(alpha: 0.55),
                    shape: BoxShape.circle,
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    item.name,
                    style: TextStyle(
                      fontSize: 13,
                      color: textSecondary,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 1),
                  decoration: BoxDecoration(
                    color: chipBg,
                    borderRadius: BorderRadius.circular(4),
                  ),
                  child: Text(
                    'x${item.quantity}',
                    style: TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w600,
                      color: textSecondary,
                    ),
                  ),
                ),
              ],
            ),
          );
        }).toList(),
      ),
    );
  }

  Widget _buildFooter(BuildContext context, bool isDark) {
    final actions = _actionsForStatus(status);
    final textPrimary =
        isDark ? AppColors.darkTextPrimary : AppColors.textPrimary;
    return Padding(
      padding: const EdgeInsets.all(16),
      child: Row(
        children: [
          Text(
            '₺${total.toStringAsFixed(2)}',
            style: TextStyle(
              fontSize: 18,
              fontWeight: FontWeight.w800,
              color: textPrimary,
            ),
          ),
          const Spacer(),
          ...actions.map((action) {
            return Padding(
              padding: const EdgeInsets.only(left: 8),
              child: SizedBox(
                height: kMinInteractiveDimension,
                child: ElevatedButton(
                  onPressed: onStatusChange != null
                      ? () => onStatusChange!(action.targetStatus)
                      : null,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: action.color,
                    foregroundColor: Colors.white,
                    elevation: 0,
                    padding: const EdgeInsets.symmetric(horizontal: 16),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(10),
                    ),
                    textStyle: const TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  child: Text(action.label),
                ),
              ),
            );
          }),
        ],
      ),
    );
  }

  List<_OrderAction> _actionsForStatus(String status) {
    switch (status.toUpperCase()) {
      case 'PENDING':
        return [
          const _OrderAction('Hazırla', 'PREPARING', AppColors.primary),
          const _OrderAction('İptal', 'CANCELLED', AppColors.errorBright),
        ];
      case 'PREPARING':
        return [
          const _OrderAction('Hazır', 'READY', AppColors.successBright),
        ];
      case 'READY':
        return [
          const _OrderAction('Teslim Et', 'SERVED', AppColors.primary),
        ];
      default:
        return [];
    }
  }
}

class _OrderAction {
  final String label;
  final String targetStatus;
  final Color color;

  const _OrderAction(this.label, this.targetStatus, this.color);
}
