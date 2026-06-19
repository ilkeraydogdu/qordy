import 'package:flutter/material.dart';
import 'package:get_it/get_it.dart';
import 'package:intl/intl.dart';
import 'package:shimmer/shimmer.dart';

import 'package:qordy_app/config/theme.dart';

import '../data/manager_repository.dart';

class ZReportScreen extends StatefulWidget {
  const ZReportScreen({super.key});

  @override
  State<ZReportScreen> createState() => _ZReportScreenState();
}

class _ZReportScreenState extends State<ZReportScreen> {
  final _repository = GetIt.instance<ManagerRepository>();

  DateTime _selectedDate = DateTime.now();
  Map<String, dynamic>? _report;
  bool _isLoading = true;
  bool _isPrinting = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _isLoading = true;
      _error = null;
    });

    try {
      final dateStr = DateFormat('yyyy-MM-dd').format(_selectedDate);
      final response = await _repository.getZReport(date: dateStr);

      if (response.isSuccess) {
        setState(() {
          _report = response.data;
          _isLoading = false;
        });
      } else {
        setState(() {
          _error = response.error ?? 'Rapor yüklenemedi';
          _isLoading = false;
        });
      }
    } catch (e) {
      setState(() {
        _error = e.toString();
        _isLoading = false;
      });
    }
  }

  Future<void> _print() async {
    setState(() => _isPrinting = true);
    try {
      final dateStr = DateFormat('yyyy-MM-dd').format(_selectedDate);
      final response = await _repository.printZReport(date: dateStr);
      if (mounted) {
        setState(() => _isPrinting = false);
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              response.isSuccess
                  ? 'Rapor yazdırma isteği gönderildi'
                  : response.error ?? 'Yazdırılamadı',
            ),
            backgroundColor:
                response.isSuccess ? AppColors.success : AppColors.error,
          ),
        );
      }
    } catch (e) {
      if (mounted) {
        setState(() => _isPrinting = false);
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(e.toString()),
            backgroundColor: AppColors.error,
          ),
        );
      }
    }
  }

  Future<void> _pickDate() async {
    // Date picker inherits the ambient theme (light or dark) from
    // MaterialApp; we only override the primary accent so the selected
    // day matches the brand colour in both modes.
    final picked = await showDatePicker(
      context: context,
      initialDate: _selectedDate,
      firstDate: DateTime(2020),
      lastDate: DateTime.now(),
      builder: (context, child) {
        final base = Theme.of(context);
        return Theme(
          data: base.copyWith(
            colorScheme: base.colorScheme.copyWith(
              primary: AppColors.primary,
              onPrimary: Colors.white,
            ),
          ),
          child: child!,
        );
      },
    );
    if (picked != null && picked != _selectedDate) {
      setState(() => _selectedDate = picked);
      _load();
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        title: const Text('Z Raporu'),
        backgroundColor: Theme.of(context).scaffoldBackgroundColor,
        surfaceTintColor: Colors.transparent,
        actions: [
          IconButton(
            onPressed: _isPrinting ? null : _print,
            icon: _isPrinting
                ? const SizedBox(
                    width: 20,
                    height: 20,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : const Icon(Icons.print_outlined),
            tooltip: 'Yazdır',
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: _load,
        color: AppColors.primary,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(20, 8, 20, 32),
          children: [
            _buildDateSelector(),
            const SizedBox(height: 20),
            if (_isLoading) _buildShimmer(),
            if (_error != null) _buildError(),
            if (!_isLoading && _error == null && _report != null)
              _buildReport(),
          ],
        ),
      ),
    );
  }

  Widget _buildDateSelector() {
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: _pickDate,
        borderRadius: BorderRadius.circular(10),
        child: Ink(
          decoration: BoxDecoration(
color: Theme.of(context).cardColor,
            borderRadius: BorderRadius.circular(10),
            border: Border.all(color: AppColors.border),
          ),
          child: ConstrainedBox(
            constraints: const BoxConstraints(
              minHeight: kMinInteractiveDimension,
            ),
            child: Padding(
              padding:
                  const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
              child: Row(
                children: [
                  const Icon(Icons.calendar_today_outlined,
                      size: 20, color: AppColors.primary),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Text(
                      DateFormat('dd MMMM yyyy', 'tr').format(_selectedDate),
                      style: const TextStyle(
                        fontSize: 15,
                        fontWeight: FontWeight.w500,
                        color: AppColors.textSecondary,
                      ),
                    ),
                  ),
                  const Icon(Icons.chevron_right, color: AppColors.textHint),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildReport() {
    final r = _report!;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _buildSummaryCard(r),
        const SizedBox(height: 20),
        _buildDetailSection(r),
      ],
    );
  }

  Widget _buildSummaryCard(Map<String, dynamic> r) {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [AppColors.primary, AppColors.primaryDark],
        ),
        borderRadius: BorderRadius.circular(16),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Gün Sonu Özeti',
            style: TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.w600,
              color: Colors.white,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            DateFormat('dd.MM.yyyy').format(_selectedDate),
            style: TextStyle(
              fontSize: 13,
              color: Colors.white.withValues(alpha: 0.7),
            ),
          ),
          const SizedBox(height: 20),
          Text(
            '₺${_formatNumber(_toDouble(r['totalSales'] ?? r['toplam_satis'] ?? 0))}',
            style: const TextStyle(
              fontSize: 32,
              fontWeight: FontWeight.w700,
              color: Colors.white,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            'Toplam Satış',
            style: TextStyle(
              fontSize: 14,
              color: Colors.white.withValues(alpha: 0.8),
            ),
          ),
          const SizedBox(height: 20),
          Row(
            children: [
              _summaryChip(
                  'Nakit',
                  '₺${_formatNumber(_toDouble(r['cashTotal'] ?? r['nakit'] ?? 0))}'),
              const SizedBox(width: 8),
              _summaryChip(
                  'Kart',
                  '₺${_formatNumber(_toDouble(r['cardTotal'] ?? r['kart'] ?? 0))}'),
            ],
          ),
        ],
      ),
    );
  }

  Widget _summaryChip(String label, String value) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.15),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label,
            style: TextStyle(
              fontSize: 12,
              color: Colors.white.withValues(alpha: 0.7),
            ),
          ),
          const SizedBox(height: 2),
          Text(
            value,
            style: const TextStyle(
              fontSize: 15,
              fontWeight: FontWeight.w600,
              color: Colors.white,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildDetailSection(Map<String, dynamic> r) {
    final rows = <_DetailRow>[
      _DetailRow(
        icon: Icons.schedule,
        label: 'Açılış Saati',
        value: r['openTime']?.toString() ?? r['acilis']?.toString() ?? '-',
      ),
      _DetailRow(
        icon: Icons.schedule,
        label: 'Kapanış Saati',
        value: r['closeTime']?.toString() ?? r['kapanis']?.toString() ?? '-',
      ),
      _DetailRow(
        icon: Icons.receipt_long_outlined,
        label: 'Toplam Sipariş',
        value: '${r['totalOrders'] ?? r['toplam_siparis'] ?? 0}',
      ),
      _DetailRow(
        icon: Icons.payments_outlined,
        label: 'Toplam Satış',
        value:
            '₺${_formatNumber(_toDouble(r['totalSales'] ?? r['toplam_satis'] ?? 0))}',
      ),
      _DetailRow(
        icon: Icons.money_outlined,
        label: 'Nakit',
        value:
            '₺${_formatNumber(_toDouble(r['cashTotal'] ?? r['nakit'] ?? 0))}',
      ),
      _DetailRow(
        icon: Icons.credit_card_outlined,
        label: 'Kredi Kartı',
        value:
            '₺${_formatNumber(_toDouble(r['cardTotal'] ?? r['kart'] ?? 0))}',
      ),
      _DetailRow(
        icon: Icons.cancel_outlined,
        label: 'İptal Edilen',
        value:
            '₺${_formatNumber(_toDouble(r['cancelledTotal'] ?? r['iptal'] ?? 0))}',
        isNegative: true,
      ),
      _DetailRow(
        icon: Icons.discount_outlined,
        label: 'İndirimler',
        value:
            '₺${_formatNumber(_toDouble(r['discountTotal'] ?? r['indirim'] ?? 0))}',
      ),
    ];

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'Detay Dökümü',
          style: TextStyle(
            fontSize: 17,
            fontWeight: FontWeight.w600,
            color: context.brandTextPrimary,
          ),
        ),
        const SizedBox(height: 12),
        Container(
          decoration: BoxDecoration(
color: Theme.of(context).cardColor,
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: AppColors.border),
          ),
          child: Column(
            children: rows.asMap().entries.map((entry) {
              final idx = entry.key;
              final row = entry.value;
              return Column(
                children: [
                  Padding(
                    padding: const EdgeInsets.symmetric(
                        horizontal: 16, vertical: 14),
                    child: Row(
                      children: [
                        Container(
                          width: 36,
                          height: 36,
                          decoration: BoxDecoration(
                            color: row.isNegative
                                ? AppColors.error.withValues(alpha: 0.08)
                                : AppColors.primary.withValues(alpha: 0.08),
                            shape: BoxShape.circle,
                          ),
                          child: Icon(
                            row.icon,
                            size: 18,
                            color: row.isNegative
                                ? AppColors.error
                                : AppColors.primary,
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Text(
                            row.label,
                            style: const TextStyle(
                              fontSize: 14,
                              color: AppColors.textSecondary,
                            ),
                          ),
                        ),
                        Text(
                          row.value,
                          style: TextStyle(
                            fontSize: 15,
                            fontWeight: FontWeight.w600,
                            color: row.isNegative
                                ? AppColors.error
                                : AppColors.textPrimary,
                          ),
                        ),
                      ],
                    ),
                  ),
                  if (idx < rows.length - 1)
                    const Divider(height: 1, indent: 64),
                ],
              );
            }).toList(),
          ),
        ),
      ],
    );
  }

  Widget _buildError() {
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 48),
      child: Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.error_outline, size: 48, color: AppColors.border),
            const SizedBox(height: 12),
            Text(
              _error!,
              style: TextStyle(fontSize: 14, color: AppColors.textSecondary),
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 16),
            TextButton(onPressed: _load, child: const Text('Tekrar Dene')),
          ],
        ),
      ),
    );
  }

  Widget _buildShimmer() {
    return Shimmer.fromColors(
      baseColor: AppColors.border,
      highlightColor: AppColors.surfaceMuted,
      child: Column(
        children: [
          Container(
            height: 200,
            decoration: BoxDecoration(
color: Theme.of(context).cardColor,
              borderRadius: BorderRadius.circular(16),
            ),
          ),
          const SizedBox(height: 20),
          ...List.generate(6, (_) {
            return Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: Container(
                height: 52,
                decoration: BoxDecoration(
color: Theme.of(context).cardColor,
                  borderRadius: BorderRadius.circular(8),
                ),
              ),
            );
          }),
        ],
      ),
    );
  }

  double _toDouble(dynamic value) {
    if (value is num) return value.toDouble();
    if (value is String) return double.tryParse(value) ?? 0;
    return 0;
  }

  String _formatNumber(double value) {
    if (value >= 1000) {
      return value.toStringAsFixed(0).replaceAllMapped(
            RegExp(r'(\d)(?=(\d{3})+(?!\d))'),
            (m) => '${m[1]}.',
          );
    }
    return value.toStringAsFixed(value.truncateToDouble() == value ? 0 : 2);
  }
}

class _DetailRow {
  final IconData icon;
  final String label;
  final String value;
  final bool isNegative;

  const _DetailRow({
    required this.icon,
    required this.label,
    required this.value,
    this.isNegative = false,
  });
}
