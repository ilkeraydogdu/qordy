import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:qordy_app/models/approval.dart';

import '../cubit/approvals_cubit.dart';
import '../cubit/approvals_state.dart';
import '../../../config/theme.dart';

/// Cubit provided by the router.
class OrderApprovalsScreen extends StatelessWidget {
  const OrderApprovalsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        title: const Text('Sipariş Onayları'),
        backgroundColor: Theme.of(context).scaffoldBackgroundColor,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: () => context.read<ApprovalsCubit>().loadApprovals(),
          ),
        ],
      ),
      body: BlocConsumer<ApprovalsCubit, ApprovalsState>(
        listener: (context, state) {
          if (state is ApprovalActionSuccess) {
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(
                content: Text(state.message),
                backgroundColor: Colors.green,
                behavior: SnackBarBehavior.floating,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
              ),
            );
          } else if (state is ApprovalsError) {
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(
                content: Text(state.message),
                backgroundColor: Colors.red,
                behavior: SnackBarBehavior.floating,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
              ),
            );
          }
        },
        builder: (context, state) {
          if (state is ApprovalsLoading) {
            return const Center(
              child: CircularProgressIndicator(color: AppColors.primary),
            );
          }
          if (state is ApprovalsLoaded) {
            if (state.approvals.isEmpty) {
              return Center(
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Container(
                      width: 80,
                      height: 80,
                      decoration: BoxDecoration(
                        color: Colors.green.shade50,
                        shape: BoxShape.circle,
                      ),
                      child: Icon(
                        Icons.check_circle_outline,
                        size: 40,
                        color: Colors.green.shade400,
                      ),
                    ),
                    const SizedBox(height: 20),
                    Text(
                      'Bekleyen onay yok',
                      style: TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.w500,
                        color: AppColors.textSecondary,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      'Her 10 saniyede otomatik kontrol edilir',
                      style: TextStyle(
                        fontSize: 13,
                        color: AppColors.textHint,
                      ),
                    ),
                  ],
                ),
              );
            }
            return ListView.builder(
              padding: const EdgeInsets.all(16),
              itemCount: state.approvals.length,
              itemBuilder: (context, index) {
                return _ApprovalCard(approval: state.approvals[index]);
              },
            );
          }
          if (state is ApprovalsError) {
            return Center(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(Icons.error_outline, size: 48, color: AppColors.textHint),
                  const SizedBox(height: 16),
                  Text(state.message, style: TextStyle(color: AppColors.textSecondary)),
                  const SizedBox(height: 16),
                  OutlinedButton(
                    onPressed: () => context.read<ApprovalsCubit>().loadApprovals(),
                    child: const Text('Tekrar Dene'),
                  ),
                ],
              ),
            );
          }
          return const SizedBox.shrink();
        },
      ),
    );
  }
}

class _ApprovalCard extends StatelessWidget {
  final OrderApproval approval;

  const _ApprovalCard({required this.approval});

  @override
  Widget build(BuildContext context) {
    final isDelete = approval.actionType == 'delete' ||
        approval.actionType == 'Silme';
    final actionLabel = isDelete ? 'Silme' : 'Azaltma';
    final actionColor = isDelete ? Colors.red : Colors.orange;

    String timeAgo = '';
    if (approval.requestedAt != null) {
      final dt = DateTime.tryParse(approval.requestedAt!);
      if (dt != null) {
        final diff = DateTime.now().difference(dt);
        if (diff.inMinutes < 1) {
          timeAgo = 'Az önce';
        } else if (diff.inMinutes < 60) {
          timeAgo = '${diff.inMinutes} dk önce';
        } else if (diff.inHours < 24) {
          timeAgo = '${diff.inHours} saat önce';
        } else {
          timeAgo = '${diff.inDays} gün önce';
        }
      }
    }

    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      elevation: 0,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
        side: BorderSide(color: actionColor.withValues(alpha: 0.3)),
      ),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 8,
                    vertical: 4,
                  ),
                  decoration: BoxDecoration(
                    color: actionColor.withValues(alpha: 0.1),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Text(
                    actionLabel,
                    style: TextStyle(
                      color: actionColor,
                      fontSize: 12,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
                const Spacer(),
                if (timeAgo.isNotEmpty)
                  Text(
                    timeAgo,
                    style: TextStyle(fontSize: 12, color: AppColors.textSecondary),
                  ),
              ],
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                Icon(Icons.table_restaurant, size: 18, color: AppColors.textSecondary),
                const SizedBox(width: 6),
                Text(
                  approval.tableName ?? 'Masa',
                  style: const TextStyle(fontWeight: FontWeight.w500),
                ),
              ],
            ),
            const SizedBox(height: 6),
            Text(
              approval.itemName ?? 'Ürün',
              style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w600),
            ),
            if (!isDelete &&
                approval.oldQuantity != null &&
                approval.newQuantity != null) ...[
              const SizedBox(height: 4),
              Text(
                'Miktar: ${approval.oldQuantity} → ${approval.newQuantity}',
                style: TextStyle(fontSize: 13, color: AppColors.textSecondary),
              ),
            ],
            const SizedBox(height: 6),
            Row(
              children: [
                Icon(Icons.person_outline, size: 16, color: AppColors.textSecondary),
                const SizedBox(width: 4),
                Text(
                  approval.requestedByName ?? 'Bilinmiyor',
                  style: TextStyle(fontSize: 13, color: AppColors.textSecondary),
                ),
                if (approval.requestedByRole != null) ...[
                  const SizedBox(width: 6),
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 6,
                      vertical: 2,
                    ),
                    decoration: BoxDecoration(
                      color: AppColors.surfaceMuted,
                      borderRadius: BorderRadius.circular(4),
                    ),
                    child: Text(
                      approval.requestedByRole!,
                      style: TextStyle(fontSize: 11, color: AppColors.textSecondary),
                    ),
                  ),
                ],
              ],
            ),
            const SizedBox(height: 16),
            Row(
              children: [
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: () {
                      if (approval.approvalId != null) {
                        context.read<ApprovalsCubit>().reject(approval.approvalId!);
                      }
                    },
                    icon: const Icon(Icons.close, size: 18),
                    label: const Text('Reddet'),
                    style: OutlinedButton.styleFrom(
                      foregroundColor: Colors.red,
                      side: const BorderSide(color: Colors.red),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(10),
                      ),
                      padding: const EdgeInsets.symmetric(vertical: 12),
                    ),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: FilledButton.icon(
                    onPressed: () {
                      if (approval.approvalId != null) {
                        context.read<ApprovalsCubit>().approve(approval.approvalId!);
                      }
                    },
                    icon: const Icon(Icons.check, size: 18),
                    label: const Text('Onayla'),
                    style: FilledButton.styleFrom(
                      backgroundColor: Colors.green,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(10),
                      ),
                      padding: const EdgeInsets.symmetric(vertical: 12),
                    ),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
