import 'package:flutter/material.dart';
import 'package:get_it/get_it.dart';

import '../../../config/theme.dart';
import '../../../core/ui/primitives.dart';
import '../data/admin_api.dart';
import '../widgets/admin_list_scaffold.dart';
import 'admin_helpers.dart';

// ═══════════════════════════════════════════════════════════════════
// Payment gateways
// ═══════════════════════════════════════════════════════════════════

class PaymentGatewaysScreen extends StatelessWidget {
  const PaymentGatewaysScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final api = GetIt.instance<AdminApi>();
    return AdminListScaffold<Map<String, dynamic>>(
      title: 'Ödeme Entegrasyonları',
      emptyIcon: Icons.credit_card_outlined,
      emptyTitle: 'Kayıt yok',
      loader: () async {
        final r = await api.getPaymentGateways();
        if (!r.isSuccess || r.data == null) {
          throw Exception(r.error ?? 'Yüklenemedi');
        }
        final raw = r.data!['gateways'];
        if (raw is! List) return const [];
        return raw
            .map((e) =>
                e is Map ? Map<String, dynamic>.from(e) : <String, dynamic>{})
            .toList();
      },
      builder: (context, g, refresh) {
        final id = (g['gateway_id'] ?? g['id'] ?? '').toString();
        final name = (g['name'] ?? g['provider'] ?? '—').toString();
        final description = (g['description'] ?? g['provider'] ?? '').toString();
        final enabled = _bool(g['is_enabled'] ?? g['enabled']);
        return QCard(
          padding: const EdgeInsets.all(AppSpacing.md),
          child: Row(
            children: [
              Container(
                width: 40,
                height: 40,
                decoration: BoxDecoration(
                  color: AppColors.success.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(AppRadius.md),
                ),
                child: Icon(Icons.credit_card_outlined,
                    color: AppColors.success, size: 20),
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
                    if (description.isNotEmpty)
                      Text(description,
                          style: TextStyle(
                              fontSize: 12,
                              color: context.brandTextSecondary)),
                  ],
                ),
              ),
              Switch.adaptive(
                value: enabled,
                activeThumbColor: AppColors.primary,
                onChanged: (v) async {
                  final r = await api.togglePaymentGateway(id, v);
                  if (!context.mounted) return;
                  snack(context, r.isSuccess ? 'Güncellendi' : (r.error ?? ''));
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
// POS devices
// ═══════════════════════════════════════════════════════════════════

class PosDevicesScreen extends StatelessWidget {
  const PosDevicesScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final api = GetIt.instance<AdminApi>();
    return AdminListScaffold<Map<String, dynamic>>(
      title: 'POS Cihazları',
      emptyIcon: Icons.point_of_sale_outlined,
      emptyTitle: 'Cihaz yok',
      loader: () async {
        final r = await api.getPosDevices();
        if (!r.isSuccess || r.data == null) {
          throw Exception(r.error ?? 'Yüklenemedi');
        }
        final raw = r.data!['devices'];
        if (raw is! List) return const [];
        return raw
            .map((e) =>
                e is Map ? Map<String, dynamic>.from(e) : <String, dynamic>{})
            .toList();
      },
      builder: (context, d, refresh) {
        final id = (d['device_id'] ?? d['id'] ?? '').toString();
        final name = (d['device_name'] ?? d['name'] ?? '—').toString();
        final type = (d['device_type'] ?? d['type'] ?? '').toString();
        final serial = (d['serial_number'] ?? d['serial'] ?? '').toString();
        return QCard(
          padding: const EdgeInsets.all(AppSpacing.md),
          child: Row(
            children: [
              Container(
                width: 40,
                height: 40,
                decoration: BoxDecoration(
                  color: AppColors.primary.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(AppRadius.md),
                ),
                child: Icon(Icons.point_of_sale_outlined,
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
                    Text(
                      [type, serial].where((e) => e.isNotEmpty).join(' • '),
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
                      title: 'Cihazı sil',
                      message: '$name silinsin mi?');
                  if (!ok) return;
                  final r = await api.deletePosDevice(id);
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

// ═══════════════════════════════════════════════════════════════════
// Feature flags
// ═══════════════════════════════════════════════════════════════════

class FeatureFlagsScreen extends StatelessWidget {
  const FeatureFlagsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final api = GetIt.instance<AdminApi>();
    return AdminListScaffold<Map<String, dynamic>>(
      title: 'Özellik Bayrakları',
      emptyIcon: Icons.flag_outlined,
      emptyTitle: 'Özellik yok',
      loader: () async {
        final r = await api.getFeatureFlags();
        if (!r.isSuccess || r.data == null) {
          throw Exception(r.error ?? 'Yüklenemedi');
        }
        final raw = r.data!['features'];
        if (raw is! List) return const [];
        return raw
            .map((e) =>
                e is Map ? Map<String, dynamic>.from(e) : <String, dynamic>{})
            .toList();
      },
      builder: (context, f, refresh) {
        final id = (f['feature_id'] ?? f['id'] ?? f['key'] ?? '').toString();
        final name = (f['name'] ?? f['feature_name'] ?? id).toString();
        final desc = (f['description'] ?? '').toString();
        final enabled = _bool(f['is_enabled'] ?? f['enabled']);
        return QCard(
          padding: const EdgeInsets.all(AppSpacing.md),
          child: Row(
            children: [
              Container(
                width: 40,
                height: 40,
                decoration: BoxDecoration(
                  color: AppColors.warning.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(AppRadius.md),
                ),
                child: Icon(Icons.flag_outlined,
                    color: AppColors.warning, size: 20),
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
                    if (desc.isNotEmpty)
                      Text(desc,
                          style: TextStyle(
                              fontSize: 12,
                              color: context.brandTextSecondary)),
                  ],
                ),
              ),
              Switch.adaptive(
                value: enabled,
                activeThumbColor: AppColors.primary,
                onChanged: (v) async {
                  final r = await api.toggleFeatureFlag(id, v);
                  if (!context.mounted) return;
                  snack(context, r.isSuccess ? 'Güncellendi' : (r.error ?? ''));
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
// Error logs
// ═══════════════════════════════════════════════════════════════════

class ErrorLogsScreen extends StatelessWidget {
  const ErrorLogsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final api = GetIt.instance<AdminApi>();
    return AdminListScaffold<Map<String, dynamic>>(
      title: 'Hata Günlükleri',
      emptyIcon: Icons.bug_report_outlined,
      emptyTitle: 'Son zamanlarda hata yok',
      loader: () async {
        final r = await api.getErrorLogs();
        if (!r.isSuccess || r.data == null) {
          throw Exception(r.error ?? 'Yüklenemedi');
        }
        final raw = r.data!['logs'];
        if (raw is! List) return const [];
        return raw
            .map((e) =>
                e is Map ? Map<String, dynamic>.from(e) : <String, dynamic>{})
            .toList();
      },
      builder: (context, log, refresh) {
        final lvl = (log['level'] ?? 'error').toString().toUpperCase();
        final msg = (log['message'] ?? '').toString();
        final createdAt = (log['created_at'] ?? '').toString();
        final color = lvl == 'ERROR' || lvl == 'CRITICAL'
            ? AppColors.error
            : (lvl == 'WARNING' ? AppColors.warning : AppColors.info);
        return QCard(
          padding: const EdgeInsets.all(AppSpacing.md),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Container(
                    padding:
                        const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                    decoration: BoxDecoration(
                      color: color.withValues(alpha: 0.12),
                      borderRadius: BorderRadius.circular(999),
                    ),
                    child: Text(lvl,
                        style: TextStyle(
                            color: color,
                            fontWeight: FontWeight.w700,
                            fontSize: 11)),
                  ),
                  const Spacer(),
                  Text(createdAt,
                      style: TextStyle(
                          fontSize: 11, color: context.brandTextSecondary)),
                ],
              ),
              const SizedBox(height: 6),
              Text(msg,
                  style: TextStyle(
                      fontSize: 13, color: context.brandTextPrimary)),
            ],
          ),
        );
      },
    );
  }
}

// ═══════════════════════════════════════════════════════════════════
// Reports
// ═══════════════════════════════════════════════════════════════════

class ReportsScreen extends StatefulWidget {
  const ReportsScreen({super.key});

  @override
  State<ReportsScreen> createState() => _ReportsScreenState();
}

class _ReportsScreenState extends State<ReportsScreen> {
  String _period = 'week';
  Future<Map<String, dynamic>>? _future;

  @override
  void initState() {
    super.initState();
    _reload();
  }

  void _reload() {
    setState(() {
      _future = _load();
    });
  }

  Future<Map<String, dynamic>> _load() async {
    final api = GetIt.instance<AdminApi>();
    final r = await api.getReports(type: 'sales', period: _period);
    if (!r.isSuccess || r.data == null) {
      throw Exception(r.error ?? 'Rapor alınamadı');
    }
    final payload = r.data!['report'];
    return payload is Map ? Map<String, dynamic>.from(payload) : {};
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        title: Text('Raporlar',
            style: TextStyle(
                color: context.brandTextPrimary,
                fontWeight: FontWeight.w700,
                fontSize: 18)),
        backgroundColor: Theme.of(context).scaffoldBackgroundColor,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        actions: [
          IconButton(
            onPressed: _reload,
            icon: const Icon(Icons.refresh_rounded),
            color: context.brandTextSecondary,
          ),
        ],
      ),
      body: RefreshIndicator(
        color: AppColors.primary,
        onRefresh: () async => _reload(),
        child: ListView(
          padding: const EdgeInsets.all(AppSpacing.md),
          children: [
            _PeriodPicker(
              value: _period,
              onChanged: (v) {
                setState(() => _period = v);
                _reload();
              },
            ),
            const SizedBox(height: 16),
            FutureBuilder<Map<String, dynamic>>(
              future: _future,
              builder: (context, snap) {
                if (snap.connectionState == ConnectionState.waiting) {
                  return const Padding(
                    padding: EdgeInsets.all(40),
                    child: Center(
                        child: CircularProgressIndicator(
                            color: AppColors.primary)),
                  );
                }
                if (snap.hasError) {
                  return QEmptyState(
                    icon: Icons.error_outline_rounded,
                    title: 'Yüklenemedi',
                    message: '${snap.error}',
                  );
                }
                final data = snap.data ?? const {};
                final total = (data['total'] ?? 0).toString();
                final count = (data['count'] ?? 0).toString();
                final from = (data['from'] ?? '').toString();
                final to = (data['to'] ?? '').toString();
                return Column(
                  children: [
                    _MetricCard(
                      label: 'Toplam Ciro',
                      value: '₺$total',
                      icon: Icons.trending_up_rounded,
                      color: AppColors.success,
                    ),
                    const SizedBox(height: 12),
                    _MetricCard(
                      label: 'Sipariş Sayısı',
                      value: count,
                      icon: Icons.receipt_long_outlined,
                      color: AppColors.primary,
                    ),
                    const SizedBox(height: 12),
                    QCard(
                      padding: const EdgeInsets.all(AppSpacing.md),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text('Dönem',
                              style: TextStyle(
                                  fontSize: 12,
                                  color: context.brandTextSecondary)),
                          const SizedBox(height: 4),
                          Text('$from\n$to',
                              style: TextStyle(
                                  fontSize: 13.5,
                                  color: context.brandTextPrimary)),
                        ],
                      ),
                    ),
                  ],
                );
              },
            ),
          ],
        ),
      ),
    );
  }
}

class _PeriodPicker extends StatelessWidget {
  const _PeriodPicker({required this.value, required this.onChanged});

  final String value;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    final isDark = context.isDark;
    final options = const [
      ['today', 'Bugün'],
      ['week', '7 gün'],
      ['month', 'Bu ay'],
    ];
    return Container(
      padding: const EdgeInsets.all(4),
      decoration: BoxDecoration(
        color: isDark ? AppColors.darkSurfaceMuted : AppColors.surfaceMuted,
        borderRadius: BorderRadius.circular(12),
      ),
      child: Row(
        children: options.map((o) {
          final selected = value == o[0];
          final bg = selected
              ? (isDark ? AppColors.darkCard : Colors.white)
              : Colors.transparent;
          final fg = selected
              ? (isDark ? AppColors.primaryDarkMode : AppColors.primary)
              : (isDark
                  ? AppColors.darkTextSecondary
                  : AppColors.textSecondary);
          return Expanded(
            child: InkWell(
              borderRadius: BorderRadius.circular(9),
              onTap: () => onChanged(o[0]),
              child: Container(
                padding: const EdgeInsets.symmetric(vertical: 10),
                decoration: BoxDecoration(
                  color: bg,
                  borderRadius: BorderRadius.circular(9),
                ),
                alignment: Alignment.center,
                child: Text(
                  o[1],
                  style: TextStyle(
                    color: fg,
                    fontWeight:
                        selected ? FontWeight.w700 : FontWeight.w500,
                    fontSize: 13,
                  ),
                ),
              ),
            ),
          );
        }).toList(),
      ),
    );
  }
}

class _MetricCard extends StatelessWidget {
  const _MetricCard({
    required this.label,
    required this.value,
    required this.icon,
    required this.color,
  });

  final String label;
  final String value;
  final IconData icon;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return QCard(
      padding: const EdgeInsets.all(AppSpacing.md),
      child: Row(
        children: [
          Container(
            width: 44,
            height: 44,
            decoration: BoxDecoration(
              color: color.withValues(alpha: 0.12),
              borderRadius: BorderRadius.circular(AppRadius.md),
            ),
            child: Icon(icon, color: color, size: 22),
          ),
          const SizedBox(width: AppSpacing.md),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(label,
                    style: TextStyle(
                        fontSize: 12,
                        color: context.brandTextSecondary)),
                const SizedBox(height: 4),
                Text(value,
                    style: TextStyle(
                        fontSize: 20,
                        fontWeight: FontWeight.w800,
                        color: context.brandTextPrimary)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

bool _bool(dynamic v) {
  if (v is bool) return v;
  if (v is num) return v != 0;
  if (v is String) {
    final s = v.toLowerCase();
    return s == 'true' || s == '1' || s == 'yes' || s == 'on';
  }
  return false;
}
