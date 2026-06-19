import 'package:flutter/material.dart';
import 'package:get_it/get_it.dart';
import 'package:shimmer/shimmer.dart';

import 'package:qordy_app/config/theme.dart';
import 'package:qordy_app/core/network/api_response.dart';
import 'package:qordy_app/models/analytics.dart';

import '../data/manager_repository.dart';

class ProductSalesScreen extends StatefulWidget {
  const ProductSalesScreen({super.key});

  @override
  State<ProductSalesScreen> createState() => _ProductSalesScreenState();
}

class _ProductSalesScreenState extends State<ProductSalesScreen> {
  final _repository = GetIt.instance<ManagerRepository>();
  final _searchController = TextEditingController();

  List<ProductSale> _products = [];
  bool _isLoading = true;
  String? _error;
  int _selectedPeriod = 0;
  String _sortBy = 'revenue';
  String _searchQuery = '';

  static const _periods = [
    _PeriodOption('Bugün', 'today'),
    _PeriodOption('Bu Hafta', 'week'),
    _PeriodOption('Bu Ay', 'month'),
    _PeriodOption('Bu Yıl', 'year'),
  ];

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _isLoading = true;
      _error = null;
    });

    try {
      final ApiResponse<List<ProductSale>> response =
          await _repository.getProductSales(
        period: _periods[_selectedPeriod].value,
      );

      if (response.isSuccess) {
        setState(() {
          _products = response.data ?? [];
          _isLoading = false;
        });
      } else {
        setState(() {
          _error = response.error ?? 'Veriler yüklenemedi';
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

  List<ProductSale> get _filtered {
    var result = _products;

    if (_searchQuery.isNotEmpty) {
      final query = _searchQuery.toLowerCase();
      result = result
          .where(
              (p) => p.productName?.toLowerCase().contains(query) ?? false)
          .toList();
    }

    if (_sortBy == 'revenue') {
      result.sort((a, b) => (b.revenue ?? 0).compareTo(a.revenue ?? 0));
    } else {
      result.sort((a, b) => (b.quantity ?? 0).compareTo(a.quantity ?? 0));
    }

    return result;
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        title: const Text('Ürün Satışları'),
        backgroundColor: Theme.of(context).scaffoldBackgroundColor,
        surfaceTintColor: Colors.transparent,
      ),
      body: Column(
        children: [
          _buildFilters(),
          Expanded(
            child: RefreshIndicator(
              onRefresh: _load,
              color: AppColors.primary,
              child: _buildBody(),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildFilters() {
    return Container(
      padding: const EdgeInsets.fromLTRB(20, 8, 20, 12),
      child: Column(
        children: [
          TextField(
            controller: _searchController,
            onChanged: (v) => setState(() => _searchQuery = v),
            decoration: InputDecoration(
              hintText: 'Ürün ara...',
              prefixIcon: const Icon(Icons.search, color: AppColors.textHint),
              suffixIcon: _searchQuery.isNotEmpty
                  ? IconButton(
                      icon: const Icon(Icons.close, size: 20),
                      onPressed: () {
                        _searchController.clear();
                        setState(() => _searchQuery = '');
                      },
                      padding: EdgeInsets.zero,
                      constraints: const BoxConstraints(
                        minWidth: kMinInteractiveDimension,
                        minHeight: kMinInteractiveDimension,
                      ),
                    )
                  : null,
              contentPadding:
                  const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
            ),
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: SingleChildScrollView(
                  scrollDirection: Axis.horizontal,
                  child: Row(
                    children: List.generate(_periods.length, (index) {
                      final selected = _selectedPeriod == index;
                      return Padding(
                        padding: EdgeInsets.only(
                            right:
                                index < _periods.length - 1 ? 8 : 0),
                        child: ChoiceChip(
                          label: Text(_periods[index].label),
                          selected: selected,
                          onSelected: (_) {
                            setState(() => _selectedPeriod = index);
                            _load();
                          },
                          selectedColor:
                              AppColors.primary.withValues(alpha: 0.1),
                          backgroundColor: AppColors.surface,
                          side: BorderSide(
                            color: selected
                                ? AppColors.primary
                                : AppColors.border,
                          ),
                          labelStyle: TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.w500,
                            color: selected
                                ? AppColors.primary
                                : AppColors.textSecondary,
                          ),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(8),
                          ),
                          showCheckmark: false,
                          materialTapTargetSize:
                              MaterialTapTargetSize.padded,
                          padding: const EdgeInsets.symmetric(
                            horizontal: 10,
                            vertical: 8,
                          ),
                        ),
                      );
                    }),
                  ),
                ),
              ),
              const SizedBox(width: 8),
              PopupMenuButton<String>(
                onSelected: (v) => setState(() => _sortBy = v),
                icon: const Icon(Icons.sort, color: AppColors.textSecondary),
                shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(10)),
                itemBuilder: (_) => [
                  PopupMenuItem(
                    value: 'revenue',
                    child: Row(
                      children: [
                        Icon(
                          _sortBy == 'revenue'
                              ? Icons.radio_button_checked
                              : Icons.radio_button_off,
                          size: 18,
                          color: _sortBy == 'revenue'
                              ? AppColors.primary
                              : AppColors.textHint,
                        ),
                        const SizedBox(width: 8),
                        const Text('Ciroya Göre'),
                      ],
                    ),
                  ),
                  PopupMenuItem(
                    value: 'quantity',
                    child: Row(
                      children: [
                        Icon(
                          _sortBy == 'quantity'
                              ? Icons.radio_button_checked
                              : Icons.radio_button_off,
                          size: 18,
                          color: _sortBy == 'quantity'
                              ? AppColors.primary
                              : AppColors.textHint,
                        ),
                        const SizedBox(width: 8),
                        const Text('Adede Göre'),
                      ],
                    ),
                  ),
                ],
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildBody() {
    if (_isLoading) {
      return _buildShimmer();
    }

    if (_error != null) {
      return ListView(
        children: [
          SizedBox(
            height: MediaQuery.of(context).size.height * 0.5,
            child: Center(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Icon(Icons.error_outline,
                      size: 48, color: AppColors.border),
                  const SizedBox(height: 12),
                  Text(
                    _error!,
                    style: TextStyle(
                        fontSize: 14, color: AppColors.textSecondary),
                    textAlign: TextAlign.center,
                  ),
                  const SizedBox(height: 16),
                  TextButton(
                    onPressed: _load,
                    child: const Text('Tekrar Dene'),
                  ),
                ],
              ),
            ),
          ),
        ],
      );
    }

    final items = _filtered;

    if (items.isEmpty) {
      return ListView(
        children: [
          SizedBox(
            height: MediaQuery.of(context).size.height * 0.4,
            child: Center(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Icon(Icons.inventory_2_outlined,
                      size: 48, color: AppColors.border),
                  const SizedBox(height: 12),
                  Text(
                    _searchQuery.isNotEmpty
                        ? 'Sonuç bulunamadı'
                        : 'Satış verisi bulunamadı',
                    style: TextStyle(
                        fontSize: 14, color: AppColors.textSecondary),
                  ),
                ],
              ),
            ),
          ),
        ],
      );
    }

    return ListView.separated(
      padding: const EdgeInsets.fromLTRB(20, 0, 20, 32),
      itemCount: items.length,
      separatorBuilder: (_, __) => const Divider(height: 1),
      itemBuilder: (context, index) {
        final product = items[index];
        return Padding(
          padding: const EdgeInsets.symmetric(vertical: 12),
          child: Row(
            children: [
              Container(
                width: 36,
                height: 36,
                decoration: BoxDecoration(
                  color: AppColors.primary.withValues(alpha: 0.08),
                  shape: BoxShape.circle,
                ),
                alignment: Alignment.center,
                child: Text(
                  '${index + 1}',
                  style: const TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w600,
                    color: AppColors.primary,
                  ),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      product.productName ?? '-',
                      style: const TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w500,
                        color: AppColors.textSecondary,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      '${product.quantity ?? 0} adet satıldı',
                      style: const TextStyle(
                        fontSize: 12,
                        color: AppColors.textHint,
                      ),
                    ),
                  ],
                ),
              ),
              Text(
                '₺${_formatNumber(product.revenue ?? 0)}',
                style: TextStyle(
                  fontSize: 15,
                  fontWeight: FontWeight.w600,
                  color: context.brandTextPrimary,
                ),
              ),
            ],
          ),
        );
      },
    );
  }

  Widget _buildShimmer() {
    return Shimmer.fromColors(
      baseColor: AppColors.border,
      highlightColor: AppColors.surfaceMuted,
      child: ListView.builder(
        padding: const EdgeInsets.fromLTRB(20, 0, 20, 32),
        itemCount: 8,
        itemBuilder: (_, __) => Padding(
          padding: const EdgeInsets.symmetric(vertical: 12),
          child: Row(
            children: [
              Container(
                width: 36,
                height: 36,
                decoration: const BoxDecoration(
                  color: Colors.white,
                  shape: BoxShape.circle,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Container(
                        width: 140,
                        height: 14,
                        color: Colors.white),
                    const SizedBox(height: 6),
                    Container(
                        width: 80,
                        height: 12,
                        color: Colors.white),
                  ],
                ),
              ),
              Container(width: 60, height: 16, color: Colors.white),
            ],
          ),
        ),
      ),
    );
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

class _PeriodOption {
  final String label;
  final String value;

  const _PeriodOption(this.label, this.value);
}
