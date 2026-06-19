import 'package:flutter/material.dart';
import 'package:get_it/get_it.dart';

import '../../../config/theme.dart';
import '../../../core/ui/primitives.dart';
import '../data/admin_api.dart';
import '../widgets/admin_list_scaffold.dart';
import 'admin_helpers.dart';

/// Mobile mirror of the `/business/printers/bridge-setup` page. Shows
/// every printer bridge (mini PC daemon) registered for the business,
/// lets the user rename / delete them, copy the API key for pairing a
/// new machine, and drill into the printers registered under each
/// bridge for rename / delete / connection tests.
class PrintersScreen extends StatelessWidget {
  const PrintersScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final api = GetIt.instance<AdminApi>();
    return AdminListScaffold<Map<String, dynamic>>(
      title: 'Yazıcı Köprüleri',
      loader: () async {
        final res = await api.getBridges();
        if (!res.isSuccess || res.data == null) {
          throw Exception(res.error ?? 'Köprüler alınamadı');
        }
        final raw = res.data!['bridges'];
        if (raw is! List) return const [];
        return raw
            .map((e) =>
                e is Map ? Map<String, dynamic>.from(e) : <String, dynamic>{})
            .toList();
      },
      emptyIcon: Icons.print_disabled_outlined,
      emptyTitle: 'Henüz köprü yok',
      emptyMessage:
          'Yazıcı bağlayabilmek için önce bir köprü oluşturmalısınız.',
      addLabel: 'Köprü Ekle',
      onAdd: () async {
        final name = await promptText(
          context,
          title: 'Yeni Köprü',
          label: 'Köprü adı',
          hint: 'Ör: Kasa-PC',
        );
        if (name == null || name.trim().isEmpty) return;
        final r = await api.createBridge(name.trim());
        if (!context.mounted) return;
        snack(context, r.isSuccess ? 'Köprü oluşturuldu' : (r.error ?? ''));
      },
      builder: (context, bridge, refresh) {
        return _BridgeCard(
          bridge: bridge,
          onChanged: refresh,
        );
      },
    );
  }
}

class _BridgeCard extends StatelessWidget {
  const _BridgeCard({required this.bridge, required this.onChanged});

  final Map<String, dynamic> bridge;
  final VoidCallback onChanged;

  @override
  Widget build(BuildContext context) {
    final api = GetIt.instance<AdminApi>();
    final name = (bridge['bridge_name'] ?? '—').toString();
    final id = (bridge['bridge_id'] ?? '').toString();
    final masked = (bridge['api_key_masked'] ?? '•••').toString();
    final online =
        bridge['is_online'] == true || bridge['is_online'] == 1 || bridge['is_online'] == '1';

    return QCard(
      padding: const EdgeInsets.all(AppSpacing.md),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 40,
                height: 40,
                decoration: BoxDecoration(
                  color: AppColors.primary.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(AppRadius.md),
                ),
                child: Icon(Icons.dns_outlined,
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
                    Row(
                      children: [
                        Container(
                          width: 8,
                          height: 8,
                          decoration: BoxDecoration(
                            color: online ? AppColors.success : AppColors.textSecondary,
                            shape: BoxShape.circle,
                          ),
                        ),
                        const SizedBox(width: 6),
                        Text(
                          online ? 'Çevrim içi' : 'Çevrim dışı',
                          style: TextStyle(
                            fontSize: 11.5,
                            color: context.brandTextSecondary,
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
              PopupMenuButton<String>(
                icon: Icon(Icons.more_vert_rounded,
                    color: context.brandTextSecondary),
                onSelected: (v) async {
                  if (v == 'rename') {
                    final n = await promptText(context,
                        title: 'Yeniden Adlandır',
                        label: 'Köprü adı',
                        initial: name);
                    if (n == null || n.trim().isEmpty) return;
                    final r = await api.updateBridge(id, n.trim());
                    if (!context.mounted) return;
                    snack(context,
                        r.isSuccess ? 'Güncellendi' : (r.error ?? ''));
                    if (r.isSuccess) onChanged();
                  } else if (v == 'reveal') {
                    final r = await api.revealBridgeKey(id);
                    if (!context.mounted) return;
                    if (r.isSuccess) {
                      final key = (r.data?['api_key'] ?? '').toString();
                      await _showApiKey(context, name, key);
                    } else {
                      snack(context, r.error ?? 'Hata');
                    }
                  } else if (v == 'delete') {
                    final ok = await confirm(context,
                        title: 'Köprüyü sil',
                        message:
                            'Bu köprüye bağlı tüm yazıcılar da silinir.');
                    if (!ok) return;
                    final r = await api.deleteBridge(id);
                    if (!context.mounted) return;
                    snack(context,
                        r.isSuccess ? 'Silindi' : (r.error ?? ''));
                    if (r.isSuccess) onChanged();
                  }
                },
                itemBuilder: (_) => const [
                  PopupMenuItem(
                      value: 'rename', child: Text('Yeniden adlandır')),
                  PopupMenuItem(
                      value: 'reveal', child: Text('API anahtarını göster')),
                  PopupMenuItem(value: 'delete', child: Text('Sil')),
                ],
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.sm),
          Container(
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(
              color: context.isDark
                  ? AppColors.darkSurfaceMuted
                  : AppColors.surfaceMuted,
              borderRadius: BorderRadius.circular(AppRadius.sm),
            ),
            child: Row(
              children: [
                Icon(Icons.vpn_key_outlined,
                    size: 16, color: context.brandTextSecondary),
                const SizedBox(width: 6),
                Expanded(
                  child: Text(
                    masked,
                    style: TextStyle(
                      fontFamily: 'monospace',
                      fontSize: 12,
                      color: context.brandTextSecondary,
                    ),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: AppSpacing.sm),
          Align(
            alignment: Alignment.centerRight,
            child: TextButton.icon(
              onPressed: () => _openPrinters(context, id, name),
              icon: const Icon(Icons.print_outlined, size: 18),
              label: const Text('Yazıcılar'),
            ),
          ),
        ],
      ),
    );
  }

  Future<void> _openPrinters(
      BuildContext context, String bridgeId, String bridgeName) async {
    await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => _BridgePrintersScreen(
          bridgeId: bridgeId,
          bridgeName: bridgeName,
        ),
      ),
    );
    onChanged();
  }
}

class _BridgePrintersScreen extends StatelessWidget {
  const _BridgePrintersScreen({
    required this.bridgeId,
    required this.bridgeName,
  });

  final String bridgeId;
  final String bridgeName;

  @override
  Widget build(BuildContext context) {
    final api = GetIt.instance<AdminApi>();
    return AdminListScaffold<Map<String, dynamic>>(
      title: '$bridgeName — Yazıcılar',
      loader: () async {
        final r = await api.getPrintersForBridge(bridgeId);
        if (!r.isSuccess || r.data == null) {
          throw Exception(r.error ?? 'Yazıcılar alınamadı');
        }
        final raw = r.data!['printers'];
        if (raw is! List) return const [];
        return raw
            .map((e) =>
                e is Map ? Map<String, dynamic>.from(e) : <String, dynamic>{})
            .toList();
      },
      emptyIcon: Icons.print_outlined,
      emptyTitle: 'Bu köprüde yazıcı yok',
      emptyMessage:
          'Yeni yazıcılar köprü PC\'sinde kurulur; liste otomatik güncellenir.',
      builder: (context, p, refresh) {
        final pid = (p['printer_id'] ?? '').toString();
        final name = (p['printer_name'] ?? '—').toString();
        final serial = (p['printer_serial'] ?? '').toString();
        final assigned = (p['assigned_screens'] as List?) ?? const [];
        final assignedText = assigned.isEmpty
            ? 'Ekran atanmamış'
            : assigned
                .map((s) => (s is Map ? s['screen_name'] : null)?.toString() ?? '')
                .where((v) => v.isNotEmpty)
                .join(', ');
        return QCard(
          padding: const EdgeInsets.all(AppSpacing.md),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Icon(Icons.print_outlined,
                      color: AppColors.primary, size: 20),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      name,
                      style: TextStyle(
                          fontSize: 15,
                          fontWeight: FontWeight.w700,
                          color: context.brandTextPrimary),
                    ),
                  ),
                  PopupMenuButton<String>(
                    icon: Icon(Icons.more_vert_rounded,
                        color: context.brandTextSecondary),
                    onSelected: (v) async {
                      if (v == 'rename') {
                        final n = await promptText(context,
                            title: 'Yazıcı adı',
                            label: 'Ad',
                            initial: name);
                        if (n == null || n.trim().isEmpty) return;
                        final r = await api.updatePrinter(
                            printerId: pid, printerName: n.trim());
                        if (!context.mounted) return;
                        snack(context,
                            r.isSuccess ? 'Güncellendi' : (r.error ?? ''));
                        if (r.isSuccess) refresh();
                      } else if (v == 'test') {
                        final r = await api.testPrinter(serial);
                        if (!context.mounted) return;
                        snack(context,
                            r.isSuccess ? 'Bağlantı başarılı' : (r.error ?? 'Başarısız'));
                      } else if (v == 'delete') {
                        final ok = await confirm(context,
                            title: 'Yazıcıyı sil',
                            message: '$name silinsin mi?');
                        if (!ok) return;
                        final r = await api.deletePrinter(pid);
                        if (!context.mounted) return;
                        snack(context,
                            r.isSuccess ? 'Silindi' : (r.error ?? ''));
                        if (r.isSuccess) refresh();
                      }
                    },
                    itemBuilder: (_) => const [
                      PopupMenuItem(value: 'rename', child: Text('Yeniden adlandır')),
                      PopupMenuItem(value: 'test', child: Text('Bağlantı testi')),
                      PopupMenuItem(value: 'delete', child: Text('Sil')),
                    ],
                  ),
                ],
              ),
              const SizedBox(height: 4),
              Text(
                'Seri: $serial',
                style: TextStyle(
                    fontSize: 12, color: context.brandTextSecondary),
              ),
              const SizedBox(height: 2),
              Text(
                assignedText,
                style: TextStyle(
                    fontSize: 12, color: context.brandTextSecondary),
              ),
            ],
          ),
        );
      },
    );
  }
}

Future<void> _showApiKey(
    BuildContext context, String title, String apiKey) async {
  await showDialog<void>(
    context: context,
    builder: (_) => AlertDialog(
      title: Text(title),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
              'Bu anahtarı yazıcı köprü PC\'sine yazın. Kimseyle paylaşmayın.'),
          const SizedBox(height: 12),
          Container(
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(
              color: context.isDark
                  ? AppColors.darkSurfaceMuted
                  : AppColors.surfaceMuted,
              borderRadius: BorderRadius.circular(8),
            ),
            child: SelectableText(
              apiKey,
              style: const TextStyle(
                  fontFamily: 'monospace', fontSize: 12),
            ),
          ),
        ],
      ),
      actions: [
        TextButton(
          onPressed: () async {
            await copyToClipboard(context, apiKey);
            if (!context.mounted) return;
            Navigator.of(context).pop();
          },
          child: const Text('Kopyala'),
        ),
        FilledButton(
          style: FilledButton.styleFrom(backgroundColor: AppColors.primary),
          onPressed: () => Navigator.of(context).pop(),
          child: const Text('Tamam'),
        ),
      ],
    ),
  );
}
