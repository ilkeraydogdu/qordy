import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../../../app/theme/design_tokens.dart';

class KpiCard extends StatelessWidget {
 const KpiCard({
 super.key,
 required this.label,
 required this.value,
 this.icon,
 this.iconColor,
 this.trend,
 this.onTap,
 });

 final String label;
 final String value;
 final IconData? icon;
 final Color? iconColor;
 final String? trend;
 final VoidCallback? onTap;

 @override
 Widget build(BuildContext context) {
 final theme = Theme.of(context);
 return Card(
 clipBehavior: Clip.antiAlias,
 child: InkWell(
 onTap: onTap,
 child: Padding(
 padding: const EdgeInsets.all(QordySpacing.lg),
 child: Column(
 crossAxisAlignment: CrossAxisAlignment.start,
 mainAxisSize: MainAxisSize.min,
 children: [
 Row(
 children: [
 if (icon != null) ...[
 Container(
 padding: const EdgeInsets.all(QordySpacing.sm),
 decoration: BoxDecoration(
 color: (iconColor ?? theme.colorScheme.primary)
 .withValues(alpha: 0.12),
 borderRadius: QordyRadius.brSm,
 ),
 child: Icon(icon,
 size: 20,
 color: iconColor ?? theme.colorScheme.primary),
 ),
 const SizedBox(width: QordySpacing.sm),
 ],
 Expanded(
 child: Text(
 label,
 style: QordyTypography.labelSmall.copyWith(
 color: QordyColors.onSurfaceVariant,
 ),
 maxLines: 1,
 overflow: TextOverflow.ellipsis,
 ),
 ),
 ],
 ),
 const SizedBox(height: QordySpacing.md),
 Text(
 value,
 style: QordyTypography.headlineMedium.copyWith(
 fontFeatures: const [FontFeature.tabularFigures()],
 ),
 ),
 if (trend != null) ...[
 const SizedBox(height: QordySpacing.xs),
 Text(
 trend!,
 style: QordyTypography.bodySmall.copyWith(
 color: QordyColors.tertiary,
 fontWeight: FontWeight.w600,
 ),
 ),
 ],
 ],
 ),
 ),
 ),
 );
 }
}

String formatCurrency(num value) {
 final formatter = NumberFormat.currency(
 locale: 'tr_TR',
 symbol: '₺',
 decimalDigits: 2,
 );
 return formatter.format(value);
}

String formatCount(int value) {
 return NumberFormat.decimalPattern('tr_TR').format(value);
}
