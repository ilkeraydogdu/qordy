import 'package:flutter/material.dart';
import 'package:get_it/get_it.dart';

import '../../../config/theme.dart';
import '../../../core/ui/primitives.dart';
import '../data/admin_api.dart';
import '../widgets/admin_list_scaffold.dart';
import 'admin_helpers.dart';

// ═══════════════════════════════════════════════════════════════════
// Invoices
// ═══════════════════════════════════════════════════════════════════

class InvoicesScreen extends StatelessWidget {
  const InvoicesScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final api = GetIt.instance<AdminApi>();
    return AdminListScaffold<Map<String, dynamic>>(
      title: 'Faturalar',
      emptyIcon: Icons.receipt_outlined,
      emptyTitle: 'Fatura yok',
      addLabel: 'Fatura Ekle',
      onAdd: () async {
        final res = await showFormSheet(
          context,
          title: 'Yeni Fatura',
          fields: const [
            FormFieldDef(key: 'invoice_number', label: 'Fatura no'),
            FormFieldDef(
                key: 'invoice_date',
                label: 'Tarih (YYYY-MM-DD)',
                keyboardType: TextInputType.datetime),
            FormFieldDef(
                key: 'total_amount',
                label: 'Tutar',
                keyboardType:
                    TextInputType.numberWithOptions(decimal: true)),
            FormFieldDef(key: 'supplier_id', label: 'Tedarikçi ID (ops.)'),
            FormFieldDef(key: 'notes', label: 'Not', multiline: true),
          ],
        );
        if (res == null || (res['invoice_number'] ?? '').isEmpty) return;
        final r = await api.createInvoice(res);
        if (!context.mounted) return;
        snack(context, r.isSuccess ? 'Fatura eklendi' : (r.error ?? ''));
      },
      loader: () async {
        final r = await api.getInvoices();
        if (!r.isSuccess || r.data == null) {
          throw Exception(r.error ?? 'Yüklenemedi');
        }
        final raw = r.data!['invoices'];
        if (raw is! List) return const [];
        return raw
            .map((e) =>
                e is Map ? Map<String, dynamic>.from(e) : <String, dynamic>{})
            .toList();
      },
      builder: (context, inv, refresh) {
        final id = (inv['invoice_id'] ?? '').toString();
        final no = (inv['invoice_number'] ?? '—').toString();
        final date = (inv['invoice_date'] ?? '').toString();
        final total = (inv['total_amount'] ?? 0).toString();
        final status = (inv['status'] ?? 'pending').toString();
        final statusColor = status == 'paid'
            ? AppColors.success
            : (status == 'overdue' ? AppColors.error : AppColors.warning);
        return QCard(
          padding: const EdgeInsets.all(AppSpacing.md),
          child: Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(no,
                        style: TextStyle(
                            fontSize: 15,
                            fontWeight: FontWeight.w700,
                            color: context.brandTextPrimary)),
                    const SizedBox(height: 2),
                    Text(
                      [date, '₺$total'].where((e) => e.isNotEmpty).join(' • '),
                      style: TextStyle(
                          fontSize: 12.5,
                          color: context.brandTextSecondary),
                    ),
                  ],
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                decoration: BoxDecoration(
                  color: statusColor.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(999),
                ),
                child: Text(status,
                    style: TextStyle(
                        fontSize: 11,
                        color: statusColor,
                        fontWeight: FontWeight.w700)),
              ),
              IconButton(
                icon: Icon(Icons.delete_outline,
                    color: context.brandTextSecondary),
                onPressed: () async {
                  final ok = await confirm(context,
                      title: 'Faturayı sil', message: '$no silinsin mi?');
                  if (!ok) return;
                  final r = await api.deleteInvoice(id);
                  if (!context.mounted) return;
                  snack(context,
                      r.isSuccess ? 'Silindi' : (r.error ?? ''));
                  if (r.isSuccess) refresh();
                },
              ),
            ],
          ),
        );
      },
    );
  }
}

// ═══════════════════════════════════════════════════════════════════
// Suppliers
// ═══════════════════════════════════════════════════════════════════

class SuppliersScreen extends StatelessWidget {
  const SuppliersScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final api = GetIt.instance<AdminApi>();
    return AdminListScaffold<Map<String, dynamic>>(
      title: 'Tedarikçiler',
      emptyIcon: Icons.local_shipping_outlined,
      emptyTitle: 'Tedarikçi yok',
      addLabel: 'Tedarikçi Ekle',
      onAdd: () async {
        final res = await showFormSheet(
          context,
          title: 'Yeni Tedarikçi',
          fields: const [
            FormFieldDef(key: 'name', label: 'Ad'),
            FormFieldDef(
                key: 'phone', label: 'Telefon', keyboardType: TextInputType.phone),
            FormFieldDef(
                key: 'email', label: 'E-posta', keyboardType: TextInputType.emailAddress),
            FormFieldDef(key: 'address', label: 'Adres', multiline: true),
            FormFieldDef(key: 'notes', label: 'Not', multiline: true),
          ],
        );
        if (res == null || (res['name'] ?? '').isEmpty) return;
        final r = await api.createSupplier(res);
        if (!context.mounted) return;
        snack(context, r.isSuccess ? 'Tedarikçi eklendi' : (r.error ?? ''));
      },
      loader: () async {
        final r = await api.getSuppliers();
        if (!r.isSuccess || r.data == null) {
          throw Exception(r.error ?? 'Yüklenemedi');
        }
        final raw = r.data!['suppliers'];
        if (raw is! List) return const [];
        return raw
            .map((e) =>
                e is Map ? Map<String, dynamic>.from(e) : <String, dynamic>{})
            .toList();
      },
      builder: (context, s, refresh) {
        final id = (s['supplier_id'] ?? '').toString();
        final name = (s['name'] ?? '—').toString();
        final phone = (s['phone'] ?? '').toString();
        final email = (s['email'] ?? '').toString();
        return QCard(
          padding: const EdgeInsets.all(AppSpacing.md),
          child: Row(
            children: [
              Container(
                width: 40,
                height: 40,
                decoration: BoxDecoration(
                  color: AppColors.info.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(AppRadius.md),
                ),
                child: Icon(Icons.local_shipping_outlined,
                    color: AppColors.info, size: 20),
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
                      [phone, email].where((e) => e.isNotEmpty).join(' • '),
                      style: TextStyle(
                          fontSize: 12.5,
                          color: context.brandTextSecondary),
                    ),
                  ],
                ),
              ),
              PopupMenuButton<String>(
                icon: Icon(Icons.more_vert_rounded,
                    color: context.brandTextSecondary),
                onSelected: (v) async {
                  if (v == 'edit') {
                    final res = await showFormSheet(context,
                        title: 'Tedarikçiyi Düzenle',
                        fields: const [
                          FormFieldDef(key: 'name', label: 'Ad'),
                          FormFieldDef(
                              key: 'phone',
                              label: 'Telefon',
                              keyboardType: TextInputType.phone),
                          FormFieldDef(
                              key: 'email',
                              label: 'E-posta',
                              keyboardType:
                                  TextInputType.emailAddress),
                          FormFieldDef(
                              key: 'address',
                              label: 'Adres',
                              multiline: true),
                          FormFieldDef(
                              key: 'notes', label: 'Not', multiline: true),
                        ],
                        initial: {
                          'name': name,
                          'phone': phone,
                          'email': email,
                          'address': (s['address'] ?? '').toString(),
                          'notes': (s['notes'] ?? '').toString(),
                        });
                    if (res == null) return;
                    final r = await api.updateSupplier(id, res);
                    if (!context.mounted) return;
                    snack(context,
                        r.isSuccess ? 'Güncellendi' : (r.error ?? ''));
                    if (r.isSuccess) refresh();
                  } else if (v == 'delete') {
                    final ok = await confirm(context,
                        title: 'Tedarikçiyi sil',
                        message: '$name silinsin mi?');
                    if (!ok) return;
                    final r = await api.deleteSupplier(id);
                    if (!context.mounted) return;
                    snack(context, r.isSuccess ? 'Silindi' : (r.error ?? ''));
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
        );
      },
    );
  }
}

// ═══════════════════════════════════════════════════════════════════
// Waste / Fire
// ═══════════════════════════════════════════════════════════════════

class WasteScreen extends StatelessWidget {
  const WasteScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final api = GetIt.instance<AdminApi>();
    return AdminListScaffold<Map<String, dynamic>>(
      title: 'Fire / Zayiat',
      emptyIcon: Icons.delete_sweep_outlined,
      emptyTitle: 'Kayıt yok',
      addLabel: 'Kayıt Ekle',
      onAdd: () async {
        final res = await showFormSheet(
          context,
          title: 'Yeni Fire Kaydı',
          fields: const [
            FormFieldDef(key: 'item_name', label: 'Ürün'),
            FormFieldDef(
                key: 'quantity',
                label: 'Miktar',
                keyboardType:
                    TextInputType.numberWithOptions(decimal: true)),
            FormFieldDef(key: 'unit', label: 'Birim (adet/kg/lt)'),
            FormFieldDef(key: 'reason', label: 'Sebep'),
            FormFieldDef(
                key: 'waste_date',
                label: 'Tarih (YYYY-MM-DD)',
                keyboardType: TextInputType.datetime),
            FormFieldDef(key: 'notes', label: 'Not', multiline: true),
          ],
        );
        if (res == null || (res['item_name'] ?? '').isEmpty) return;
        final r = await api.createWaste(res);
        if (!context.mounted) return;
        snack(context, r.isSuccess ? 'Kayıt eklendi' : (r.error ?? ''));
      },
      loader: () async {
        final r = await api.getWaste();
        if (!r.isSuccess || r.data == null) {
          throw Exception(r.error ?? 'Yüklenemedi');
        }
        final raw = r.data!['records'];
        if (raw is! List) return const [];
        return raw
            .map((e) =>
                e is Map ? Map<String, dynamic>.from(e) : <String, dynamic>{})
            .toList();
      },
      builder: (context, w, refresh) {
        final id = (w['waste_id'] ?? '').toString();
        final name = (w['item_name'] ?? '—').toString();
        final qty = (w['quantity'] ?? '').toString();
        final unit = (w['unit'] ?? '').toString();
        final reason = (w['reason'] ?? '').toString();
        final date = (w['waste_date'] ?? '').toString();
        return QCard(
          padding: const EdgeInsets.all(AppSpacing.md),
          child: Row(
            children: [
              Container(
                width: 40,
                height: 40,
                decoration: BoxDecoration(
                  color: AppColors.error.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(AppRadius.md),
                ),
                child: Icon(Icons.delete_sweep_outlined,
                    color: AppColors.error, size: 20),
              ),
              const SizedBox(width: AppSpacing.md),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('$name — $qty $unit',
                        style: TextStyle(
                            fontSize: 14,
                            fontWeight: FontWeight.w700,
                            color: context.brandTextPrimary)),
                    const SizedBox(height: 2),
                    Text(
                      [reason, date].where((e) => e.isNotEmpty).join(' • '),
                      style: TextStyle(
                          fontSize: 12, color: context.brandTextSecondary),
                    ),
                  ],
                ),
              ),
              IconButton(
                icon: Icon(Icons.delete_outline,
                    color: context.brandTextSecondary),
                onPressed: () async {
                  final ok = await confirm(context,
                      title: 'Kaydı sil',
                      message: '$name kaydı silinsin mi?');
                  if (!ok) return;
                  final r = await api.deleteWaste(id);
                  if (!context.mounted) return;
                  snack(context, r.isSuccess ? 'Silindi' : (r.error ?? ''));
                  if (r.isSuccess) refresh();
                },
              ),
            ],
          ),
        );
      },
    );
  }
}
