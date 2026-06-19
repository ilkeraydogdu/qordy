import 'package:flutter/material.dart';
import 'package:get_it/get_it.dart';

import '../../../config/theme.dart';
import '../../../core/ui/primitives.dart';
import '../data/admin_api.dart';
import '../widgets/admin_list_scaffold.dart';
import 'admin_helpers.dart';

/// Mobile mirror of `/business/queue` — waiting-list / customer queue.
class QueueScreen extends StatefulWidget {
  const QueueScreen({super.key});

  @override
  State<QueueScreen> createState() => _QueueScreenState();
}

class _QueueScreenState extends State<QueueScreen> {
  String _status = 'waiting';

  @override
  Widget build(BuildContext context) {
    final api = GetIt.instance<AdminApi>();
    return AdminListScaffold<Map<String, dynamic>>(
      title: 'Sıra Yönetimi',
      emptyIcon: Icons.queue_outlined,
      emptyTitle: 'Sırada kimse yok',
      actions: [
        PopupMenuButton<String>(
          icon: Icon(Icons.filter_list_rounded,
              color: context.brandTextSecondary),
          onSelected: (v) => setState(() => _status = v),
          itemBuilder: (_) => const [
            PopupMenuItem(value: 'waiting', child: Text('Bekleyen')),
            PopupMenuItem(value: 'notified', child: Text('Çağrılan')),
            PopupMenuItem(value: 'seated', child: Text('Oturan')),
            PopupMenuItem(value: 'cancelled', child: Text('İptal')),
            PopupMenuItem(value: 'no_show', child: Text('Gelmedi')),
          ],
        ),
        IconButton(
          tooltip: 'Sıradaki kişiyi çağır',
          icon: const Icon(Icons.campaign_outlined),
          color: AppColors.primary,
          onPressed: () async {
            final r = await api.callNextQueueTicket();
            if (!context.mounted) return;
            snack(context,
                r.isSuccess ? 'Çağrıldı' : (r.error ?? 'Hata'));
            if (r.isSuccess) setState(() {});
          },
        ),
      ],
      loader: () async {
        final r = await api.getQueue(status: _status);
        if (!r.isSuccess || r.data == null) {
          throw Exception(r.error ?? 'Sıra alınamadı');
        }
        final raw = r.data!['tickets'];
        if (raw is! List) return const [];
        return raw
            .map((e) =>
                e is Map ? Map<String, dynamic>.from(e) : <String, dynamic>{})
            .toList();
      },
      builder: (context, t, refresh) {
        final id = (t['queue_id'] ?? t['id'] ?? '').toString();
        final name = (t['customer_name'] ?? t['name'] ?? '—').toString();
        final party = (t['party_size'] ?? t['guest_count'] ?? '').toString();
        final phone = (t['phone'] ?? t['customer_phone'] ?? '').toString();
        final created = (t['created_at'] ?? '').toString();
        return QCard(
          padding: const EdgeInsets.all(AppSpacing.md),
          child: Row(
            children: [
              Container(
                width: 42,
                height: 42,
                decoration: BoxDecoration(
                  color: AppColors.primary.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(AppRadius.md),
                ),
                child: Icon(Icons.person_outline_rounded,
                    color: AppColors.primary, size: 20),
              ),
              const SizedBox(width: AppSpacing.md),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(name,
                        style: TextStyle(
                            fontSize: 15,
                            fontWeight: FontWeight.w700,
                            color: context.brandTextPrimary)),
                    const SizedBox(height: 2),
                    Text(
                      [
                        if (party.isNotEmpty) '$party kişi',
                        if (phone.isNotEmpty) phone,
                        if (created.isNotEmpty) created,
                      ].join(' • '),
                      style: TextStyle(
                          fontSize: 12, color: context.brandTextSecondary),
                    ),
                  ],
                ),
              ),
              PopupMenuButton<String>(
                icon: Icon(Icons.more_vert_rounded,
                    color: context.brandTextSecondary),
                onSelected: (v) async {
                  final r = await api.updateQueueTicketStatus(id, v);
                  if (!context.mounted) return;
                  snack(context, r.isSuccess ? 'Güncellendi' : (r.error ?? ''));
                  if (r.isSuccess) refresh();
                },
                itemBuilder: (_) => const [
                  PopupMenuItem(value: 'notified', child: Text('Çağır')),
                  PopupMenuItem(value: 'seated', child: Text('Oturdu')),
                  PopupMenuItem(value: 'no_show', child: Text('Gelmedi')),
                  PopupMenuItem(value: 'cancelled', child: Text('İptal')),
                ],
              ),
            ],
          ),
        );
      },
    );
  }
}
