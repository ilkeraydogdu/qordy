import 'package:flutter/material.dart';
import 'package:get_it/get_it.dart';
import 'package:intl/intl.dart';

import '../data/manager_repository.dart';
import '../../../config/theme.dart';

class ReceiptsScreen extends StatefulWidget {
  const ReceiptsScreen({super.key});

  @override
  State<ReceiptsScreen> createState() => _ReceiptsScreenState();
}

class _ReceiptsScreenState extends State<ReceiptsScreen> {
  final _repository = GetIt.instance<ManagerRepository>();
  List<Map<String, dynamic>> _receipts = [];
  bool _isLoading = true;
  String? _error;
  DateTime _startDate = DateTime.now().subtract(const Duration(days: 30));
  DateTime _endDate = DateTime.now();
  String _searchQuery = '';
  final TextEditingController _searchCtrl = TextEditingController();

  @override
  void dispose() {
    _searchCtrl.dispose();
    super.dispose();
  }

  List<Map<String, dynamic>> get _filteredReceipts {
    if (_searchQuery.trim().isEmpty) return _receipts;
    final q = _searchQuery.toLowerCase().trim();
    return _receipts.where((r) {
      final id = (r['orderId']?.toString() ?? '').toLowerCase();
      final amt = (r['amount']?.toString() ?? '').toLowerCase();
      final method = (r['paymentMethod']?.toString() ?? '').toLowerCase();
      final tbl = (r['tableName']?.toString() ?? '').toLowerCase();
      return id.contains(q) ||
          amt.contains(q) ||
          method.contains(q) ||
          tbl.contains(q);
    }).toList();
  }

  @override
  void initState() {
    super.initState();
    _loadReceipts();
  }

  Future<void> _loadReceipts() async {
    setState(() {
      _isLoading = true;
      _error = null;
    });
    try {
      final response = await _repository.getReceipts(
        from: _startDate,
        to: _endDate,
      );
      if (response.isSuccess) {
        setState(() {
          _receipts = response.data ?? [];
          _isLoading = false;
        });
      } else {
        setState(() {
          _error = response.error ?? 'Fişler yüklenemedi';
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

  Future<void> _selectDateRange() async {
    final range = await showDateRangePicker(
      context: context,
      firstDate: DateTime(2020),
      lastDate: DateTime.now(),
      initialDateRange: DateTimeRange(start: _startDate, end: _endDate),
      builder: (c, child) => Theme(
        data: Theme.of(c).copyWith(
          colorScheme: const ColorScheme.light(primary: AppColors.primary),
        ),
        child: child!,
      ),
    );
    if (range != null) {
      setState(() {
        _startDate = range.start;
        _endDate = range.end;
      });
      _loadReceipts();
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        title: const Text('Fişler'),
        backgroundColor: Theme.of(context).scaffoldBackgroundColor,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
      ),
      body: Column(
        children: [
          InkWell(
            onTap: _selectDateRange,
            child: Container(
              margin: const EdgeInsets.fromLTRB(16, 8, 16, 8),
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
              decoration: BoxDecoration(
                color: AppColors.primary.withValues(alpha: 0.05),
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: AppColors.primary.withValues(alpha: 0.2)),
              ),
              child: Row(
                children: [
                  const Icon(Icons.date_range, size: 20, color: AppColors.primary),
                  const SizedBox(width: 12),
                  Text(
                    '${DateFormat('dd.MM.yyyy').format(_startDate)} - ${DateFormat('dd.MM.yyyy').format(_endDate)}',
                    style: const TextStyle(
                      fontWeight: FontWeight.w500,
                      color: AppColors.primary,
                    ),
                  ),
                  const Spacer(),
                  const Icon(Icons.arrow_drop_down, color: AppColors.primary),
                ],
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
            child: TextField(
              controller: _searchCtrl,
              onChanged: (v) => setState(() => _searchQuery = v),
              decoration: InputDecoration(
                hintText: 'Fiş, tutar, masa veya ödeme...',
                prefixIcon: const Icon(Icons.search, size: 20),
                suffixIcon: _searchQuery.isEmpty
                    ? null
                    : IconButton(
                        icon: const Icon(Icons.clear, size: 18),
                        onPressed: () {
                          _searchCtrl.clear();
                          setState(() => _searchQuery = '');
                        },
                      ),
                isDense: true,
                contentPadding:
                    const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
              ),
            ),
          ),
          Expanded(
            child: _isLoading
                ? const Center(
                    child: CircularProgressIndicator(color: AppColors.primary),
                  )
                : _error != null
                    ? Center(
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Icon(Icons.error_outline, size: 48, color: AppColors.textHint),
                            const SizedBox(height: 16),
                            Text(_error!, style: TextStyle(color: AppColors.textSecondary)),
                            const SizedBox(height: 16),
                            OutlinedButton(
                              onPressed: _loadReceipts,
                              child: const Text('Tekrar Dene'),
                            ),
                          ],
                        ),
                      )
                    : () {
                        final list = _filteredReceipts;
                        if (list.isEmpty) {
                          return Center(
                            child: Column(
                              mainAxisAlignment: MainAxisAlignment.center,
                              children: [
                                Icon(Icons.receipt_long,
                                    size: 64, color: AppColors.border),
                                const SizedBox(height: 16),
                                Text(
                                  _receipts.isEmpty
                                      ? 'Fiş bulunamadı'
                                      : 'Aramayla eşleşen fiş yok',
                                  style: TextStyle(
                                      fontSize: 16,
                                      color: AppColors.textSecondary),
                                ),
                              ],
                            ),
                          );
                        }
                        return RefreshIndicator(
                          onRefresh: _loadReceipts,
                          color: AppColors.primary,
                          child: ListView.builder(
                            padding:
                                const EdgeInsets.fromLTRB(16, 8, 16, 16),
                            itemCount: list.length,
                            itemBuilder: (context, index) {
                              return _ReceiptCard(receipt: list[index]);
                            },
                          ),
                        );
                      }(),
          ),
        ],
      ),
    );
  }
}

class _ReceiptCard extends StatelessWidget {
  final Map<String, dynamic> receipt;

  const _ReceiptCard({required this.receipt});

  @override
  Widget build(BuildContext context) {
    final orderId = receipt['orderId']?.toString() ?? '';
    final date = receipt['date']?.toString() ?? '';
    final amount = (receipt['amount'] as num?)?.toDouble() ?? 0;
    final paymentMethod = receipt['paymentMethod']?.toString() ?? '';

    IconData methodIcon;
    String methodLabel;
    switch (paymentMethod.toLowerCase()) {
      case 'cash':
      case 'nakit':
        methodIcon = Icons.payments;
        methodLabel = 'Nakit';
      case 'card':
      case 'kart':
        methodIcon = Icons.credit_card;
        methodLabel = 'Kart';
      default:
        methodIcon = Icons.payment;
        methodLabel = paymentMethod.isNotEmpty ? paymentMethod : 'Bilinmiyor';
    }

    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      elevation: 0,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
        side: BorderSide(color: AppColors.border),
      ),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Row(
          children: [
            Container(
              width: 44,
              height: 44,
              decoration: BoxDecoration(
                color: AppColors.primary.withValues(alpha: 0.1),
                borderRadius: BorderRadius.circular(10),
              ),
              child: const Icon(
                Icons.receipt,
                color: AppColors.primary,
                size: 22,
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    '#$orderId',
                    style: const TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Row(
                    children: [
                      Icon(Icons.calendar_today, size: 13, color: AppColors.textSecondary),
                      const SizedBox(width: 4),
                      Text(
                        date,
                        style: TextStyle(fontSize: 13, color: AppColors.textSecondary),
                      ),
                      const SizedBox(width: 12),
                      Icon(methodIcon, size: 13, color: AppColors.textSecondary),
                      const SizedBox(width: 4),
                      Text(
                        methodLabel,
                        style: TextStyle(fontSize: 13, color: AppColors.textSecondary),
                      ),
                    ],
                  ),
                ],
              ),
            ),
            Text(
              '₺${amount.toStringAsFixed(2)}',
              style: const TextStyle(
                fontSize: 16,
                fontWeight: FontWeight.w600,
                color: AppColors.primary,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
