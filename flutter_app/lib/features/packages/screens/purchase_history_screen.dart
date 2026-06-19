import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../../core/di/injection.dart';
import '../data/packages_repository.dart';

/// Müşterinin tüm abonelik ve ödeme geçmişini listeleyen ekran.
class PurchaseHistoryScreen extends StatefulWidget {
  const PurchaseHistoryScreen({super.key});

  @override
  State<PurchaseHistoryScreen> createState() => _PurchaseHistoryScreenState();
}

class _PurchaseHistoryScreenState extends State<PurchaseHistoryScreen> {
  final _priceFmt =
      NumberFormat.currency(locale: 'tr_TR', symbol: '₺', decimalDigits: 2);
  final _dateFmt = DateFormat('dd.MM.yyyy HH:mm');

  bool _loading = true;
  String? _error;
  List<Map<String, dynamic>> _history = const [];

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final res = await getIt<PackagesRepository>().getSubscriptionHistory();
      if (!mounted) return;
      if (res.isSuccess && res.data != null) {
        setState(() {
          _history = res.data!;
          _loading = false;
        });
      } else {
        setState(() {
          _error = res.error ?? 'Geçmiş yüklenemedi';
          _loading = false;
        });
      }
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.toString();
        _loading = false;
      });
    }
  }

  DateTime? _parseDate(dynamic raw) {
    if (raw == null) return null;
    final s = raw.toString();
    if (s.isEmpty) return null;
    return DateTime.tryParse(s);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: AppBar(
        title: const Text('Satın Alma Geçmişi'),
        centerTitle: true,
      ),
      body: RefreshIndicator(
        onRefresh: _load,
        child: _buildBody(),
      ),
    );
  }

  Widget _buildBody() {
    if (_loading) {
      return const Center(child: CircularProgressIndicator());
    }
    if (_error != null) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              const Icon(Icons.error_outline, size: 48, color: Colors.redAccent),
              const SizedBox(height: 12),
              Text(_error!, textAlign: TextAlign.center),
              const SizedBox(height: 16),
              FilledButton(onPressed: _load, child: const Text('Tekrar Dene')),
            ],
          ),
        ),
      );
    }
    if (_history.isEmpty) {
      return ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        children: const [
          SizedBox(height: 120),
          Icon(Icons.inbox_rounded, size: 64, color: Color(0xFFCBD5E1)),
          SizedBox(height: 16),
          Center(
            child: Text(
              'Henüz satın alma kaydınız yok',
              style: TextStyle(color: Color(0xFF64748B), fontSize: 15),
            ),
          ),
        ],
      );
    }

    return ListView.separated(
      padding: const EdgeInsets.all(16),
      itemCount: _history.length,
      separatorBuilder: (_, __) => const SizedBox(height: 12),
      itemBuilder: (_, i) => _buildCard(_history[i]),
    );
  }

  Widget _buildCard(Map<String, dynamic> row) {
    final packageName = (row['package_name'] ?? 'Paket').toString();
    final status = (row['status'] ?? '').toString().toLowerCase();
    final isTrial = row['is_trial'] == 1 ||
        row['is_trial'] == true ||
        row['is_trial'] == '1';
    final amount = (row['amount'] as num?)?.toDouble() ?? 0;
    final cycle = (row['billing_cycle'] ?? '').toString();
    final start = _parseDate(row['current_period_start']);
    final end = _parseDate(row['current_period_end']);
    final payments = (row['payments'] is List)
        ? List<Map<String, dynamic>>.from(
            (row['payments'] as List).whereType<Map>().map(
                  (e) => Map<String, dynamic>.from(e),
                ),
          )
        : <Map<String, dynamic>>[];

    final statusTag = _statusTag(isTrial && status == 'active' ? 'trial' : status);
    final cycleLabel = cycle == 'yearly'
        ? 'Yıllık'
        : (cycle == 'monthly' ? 'Aylık' : (cycle.isEmpty ? '—' : cycle));

    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFFE2E8F0)),
      ),
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      packageName,
                      style: const TextStyle(
                        fontWeight: FontWeight.w800,
                        fontSize: 16,
                        color: Color(0xFF0F172A),
                      ),
                    ),
                    const SizedBox(height: 4),
                    Wrap(
                      spacing: 8,
                      runSpacing: 4,
                      children: [
                        statusTag,
                        if (isTrial)
                          _chip('7 Gün Ücretsiz',
                              color: const Color(0xFF6366F1)),
                      ],
                    ),
                  ],
                ),
              ),
              Text(
                _priceFmt.format(amount),
                style: const TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.w900,
                  color: Color(0xFF0F172A),
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          DefaultTextStyle(
            style: const TextStyle(fontSize: 12, color: Color(0xFF64748B)),
            child: Wrap(
              spacing: 12,
              runSpacing: 4,
              children: [
                Text('Faturalama: $cycleLabel'),
                if (start != null)
                  Text('Başlangıç: ${DateFormat('dd.MM.yyyy').format(start)}'),
                if (end != null)
                  Text('Bitiş: ${DateFormat('dd.MM.yyyy').format(end)}'),
              ],
            ),
          ),
          if (payments.isNotEmpty) ...[
            const SizedBox(height: 12),
            Container(
              decoration: BoxDecoration(
                color: const Color(0xFFF8FAFC),
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: const Color(0xFFE2E8F0)),
              ),
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
              child: Column(
                children: [
                  for (var i = 0; i < payments.length; i++) ...[
                    if (i > 0)
                      const Divider(height: 16, color: Color(0xFFE2E8F0)),
                    _buildPaymentRow(payments[i]),
                  ],
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildPaymentRow(Map<String, dynamic> p) {
    final amt = (p['amount'] as num?)?.toDouble() ?? 0;
    final status = (p['payment_status'] ?? '').toString().toLowerCase();
    final method = (p['payment_method'] ?? '').toString();
    final dt = _parseDate(p['payment_date'] ?? p['created_at']);
    final statusColor = {
      'completed': const Color(0xFF10B981),
      'pending': const Color(0xFFF59E0B),
      'failed': const Color(0xFFEF4444),
      'refunded': const Color(0xFF64748B),
    }[status] ??
        const Color(0xFF64748B);
    final statusLabel = {
      'completed': 'Başarılı',
      'pending': 'Bekliyor',
      'failed': 'Başarısız',
      'refunded': 'İade Edildi',
    }[status] ??
        (status.isEmpty ? '—' : status);
    return Row(
      children: [
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                dt != null ? _dateFmt.format(dt) : '—',
                style: const TextStyle(
                    fontSize: 12, color: Color(0xFF0F172A), fontWeight: FontWeight.w700),
              ),
              const SizedBox(height: 2),
              Text(
                method.isEmpty
                    ? 'Ödeme'
                    : method[0].toUpperCase() + method.substring(1),
                style: const TextStyle(fontSize: 11, color: Color(0xFF64748B)),
              ),
            ],
          ),
        ),
        Text(
          _priceFmt.format(amt),
          style: const TextStyle(
            fontSize: 13,
            fontWeight: FontWeight.w800,
            color: Color(0xFF0F172A),
          ),
        ),
        const SizedBox(width: 10),
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
          decoration: BoxDecoration(
            color: statusColor.withValues(alpha: 0.12),
            borderRadius: BorderRadius.circular(8),
          ),
          child: Text(
            statusLabel,
            style: TextStyle(
              fontSize: 11,
              color: statusColor,
              fontWeight: FontWeight.w700,
            ),
          ),
        ),
      ],
    );
  }

  Widget _statusTag(String status) {
    final map = {
      'active': ['Aktif', const Color(0xFF10B981)],
      'pending': ['Ödeme Bekliyor', const Color(0xFFF59E0B)],
      'cancelled': ['İptal', const Color(0xFF64748B)],
      'expired': ['Süresi Doldu', const Color(0xFFEF4444)],
      'suspended': ['Askıda', const Color(0xFFEF4444)],
      'trial': ['Deneme', const Color(0xFF6366F1)],
    };
    final item = map[status] ?? [status.isEmpty ? '—' : status, const Color(0xFF64748B)];
    return _chip(item[0] as String, color: item[1] as Color);
  }

  Widget _chip(String text, {required Color color}) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Text(
        text,
        style: TextStyle(
          fontSize: 11,
          color: color,
          fontWeight: FontWeight.w800,
        ),
      ),
    );
  }
}
