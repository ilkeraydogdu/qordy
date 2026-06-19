import 'package:flutter/material.dart';
import 'package:get_it/get_it.dart';

import '../../../config/theme.dart';
import '../../../core/ui/primitives.dart';
import '../data/admin_api.dart';
import '../widgets/admin_list_scaffold.dart';

/// Mobile mirror of `/business/order-approval-history` — read-only
/// audit log of approved/rejected order edits.
class ApprovalHistoryScreen extends StatelessWidget {
  const ApprovalHistoryScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final api = GetIt.instance<AdminApi>();
    return AdminListScaffold<Map<String, dynamic>>(
      title: 'Onay Geçmişi',
      emptyIcon: Icons.fact_check_outlined,
      emptyTitle: 'Geçmiş boş',
      loader: () async {
        final r = await api.getApprovalHistory();
        if (!r.isSuccess || r.data == null) {
          throw Exception(r.error ?? 'Yüklenemedi');
        }
        final raw = r.data!['history'];
        if (raw is! List) return const [];
        return raw
            .map((e) =>
                e is Map ? Map<String, dynamic>.from(e) : <String, dynamic>{})
            .toList();
      },
      builder: (context, h, refresh) {
        final status =
            (h['status'] ?? h['decision'] ?? '').toString().toLowerCase();
        final isApproved = status == 'approved';
        final isRejected = status == 'rejected';
        final color = isApproved
            ? AppColors.success
            : (isRejected ? AppColors.error : AppColors.warning);
        final icon = isApproved
            ? Icons.check_circle_outline_rounded
            : (isRejected
                ? Icons.cancel_outlined
                : Icons.hourglass_bottom_rounded);
        return QCard(
          padding: const EdgeInsets.all(AppSpacing.md),
          child: Row(
            children: [
              Container(
                width: 40,
                height: 40,
                decoration: BoxDecoration(
                  color: color.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(AppRadius.md),
                ),
                child: Icon(icon, color: color, size: 20),
              ),
              const SizedBox(width: AppSpacing.md),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      (h['description'] ?? h['action'] ?? 'Onay talebi')
                          .toString(),
                      style: TextStyle(
                          fontSize: 14,
                          fontWeight: FontWeight.w700,
                          color: context.brandTextPrimary),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      [
                        (h['requested_by_name'] ?? h['user_name'] ?? '')
                            .toString(),
                        (h['decided_at'] ?? h['created_at'] ?? '').toString(),
                      ].where((e) => e.isNotEmpty).join(' • '),
                      style: TextStyle(
                          fontSize: 12, color: context.brandTextSecondary),
                    ),
                  ],
                ),
              ),
            ],
          ),
        );
      },
    );
  }
}
