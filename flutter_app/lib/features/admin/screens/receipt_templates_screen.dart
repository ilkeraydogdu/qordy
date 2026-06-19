import 'package:flutter/material.dart';
import 'package:get_it/get_it.dart';

import '../../../config/theme.dart';
import '../../../core/ui/primitives.dart';
import '../data/admin_api.dart';
import '../widgets/admin_list_scaffold.dart';
import 'admin_helpers.dart';

/// Mobile mirror of `/business/receipt-templates` — lets the business
/// manage header/footer text templates used when printing adisyon/fiş.
class ReceiptTemplatesScreen extends StatelessWidget {
  const ReceiptTemplatesScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final api = GetIt.instance<AdminApi>();
    return AdminListScaffold<Map<String, dynamic>>(
      title: 'Fiş Şablonları',
      emptyIcon: Icons.receipt_long_outlined,
      emptyTitle: 'Şablon yok',
      emptyMessage: 'İlk şablonu oluşturmak için sağ alttaki butona dokunun.',
      addLabel: 'Şablon Ekle',
      onAdd: () async {
        final res = await showFormSheet(
          context,
          title: 'Yeni Fiş Şablonu',
          fields: const [
            FormFieldDef(key: 'name', label: 'Şablon adı'),
            FormFieldDef(key: 'header', label: 'Başlık', multiline: true),
            FormFieldDef(key: 'footer', label: 'Altlık', multiline: true),
          ],
        );
        if (res == null || (res['name'] ?? '').isEmpty) return;
        final r = await api.createReceiptTemplate(res);
        if (!context.mounted) return;
        snack(context, r.isSuccess ? 'Şablon eklendi' : (r.error ?? ''));
      },
      loader: () async {
        final r = await api.getReceiptTemplates();
        if (!r.isSuccess || r.data == null) {
          throw Exception(r.error ?? 'Şablonlar alınamadı');
        }
        final raw = r.data!['templates'];
        if (raw is! List) return const [];
        return raw
            .map((e) =>
                e is Map ? Map<String, dynamic>.from(e) : <String, dynamic>{})
            .toList();
      },
      builder: (context, t, refresh) {
        final id = (t['template_id'] ?? t['id'] ?? '').toString();
        final name = (t['name'] ?? 'Şablon').toString();
        final header = (t['header'] ?? '').toString();
        final footer = (t['footer'] ?? '').toString();
        return QCard(
          padding: const EdgeInsets.all(AppSpacing.md),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(name,
                        style: TextStyle(
                            fontSize: 15,
                            fontWeight: FontWeight.w700,
                            color: context.brandTextPrimary)),
                  ),
                  PopupMenuButton<String>(
                    icon: Icon(Icons.more_vert_rounded,
                        color: context.brandTextSecondary),
                    onSelected: (v) async {
                      if (v == 'edit') {
                        final res = await showFormSheet(context,
                            title: 'Şablonu Düzenle',
                            fields: const [
                              FormFieldDef(key: 'name', label: 'Şablon adı'),
                              FormFieldDef(
                                  key: 'header',
                                  label: 'Başlık',
                                  multiline: true),
                              FormFieldDef(
                                  key: 'footer',
                                  label: 'Altlık',
                                  multiline: true),
                            ],
                            initial: {
                              'name': name,
                              'header': header,
                              'footer': footer,
                            });
                        if (res == null) return;
                        final r = await api.updateReceiptTemplate(id, res);
                        if (!context.mounted) return;
                        snack(context,
                            r.isSuccess ? 'Güncellendi' : (r.error ?? ''));
                        if (r.isSuccess) refresh();
                      } else if (v == 'delete') {
                        final ok = await confirm(context,
                            title: 'Şablonu sil',
                            message: '$name silinsin mi?');
                        if (!ok) return;
                        final r = await api.deleteReceiptTemplate(id);
                        if (!context.mounted) return;
                        snack(context,
                            r.isSuccess ? 'Silindi' : (r.error ?? ''));
                        if (r.isSuccess) refresh();
                      }
                    },
                    itemBuilder: (_) => const [
                      PopupMenuItem(value: 'edit', child: Text('Düzenle')),
                      PopupMenuItem(value: 'delete', child: Text('Sil')),
                    ],
                  ),
                ],
              ),
              if (header.isNotEmpty) ...[
                const SizedBox(height: 8),
                _MiniField(label: 'Başlık', text: header),
              ],
              if (footer.isNotEmpty) ...[
                const SizedBox(height: 6),
                _MiniField(label: 'Altlık', text: footer),
              ],
            ],
          ),
        );
      },
    );
  }
}

class _MiniField extends StatelessWidget {
  const _MiniField({required this.label, required this.text});
  final String label;
  final String text;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(10),
      decoration: BoxDecoration(
        color: context.isDark
            ? AppColors.darkSurfaceMuted
            : AppColors.surfaceMuted,
        borderRadius: BorderRadius.circular(8),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label,
              style: TextStyle(
                  fontSize: 11,
                  fontWeight: FontWeight.w600,
                  color: context.brandTextSecondary,
                  letterSpacing: 0.4)),
          const SizedBox(height: 4),
          Text(
            text,
            style: TextStyle(
                fontSize: 12.5, color: context.brandTextPrimary),
          ),
        ],
      ),
    );
  }
}
