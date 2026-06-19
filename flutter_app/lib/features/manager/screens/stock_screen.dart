import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:get_it/get_it.dart';
import 'package:qordy_app/core/network/api_response.dart';
import 'package:qordy_app/core/ui/primitives.dart';
import 'package:qordy_app/features/subscription/readonly_guard.dart';
import 'package:qordy_app/models/stock_item.dart';

import '../data/manager_repository.dart';
import '../../../config/theme.dart';

class StockScreen extends StatefulWidget {
  const StockScreen({super.key});

  @override
  State<StockScreen> createState() => _StockScreenState();
}

enum _SortBy { name, quantity, supplier }

class _StockScreenState extends State<StockScreen> {
  final _repository = GetIt.instance<ManagerRepository>();
  List<StockItem> _items = [];
  List<StockItem> _filteredItems = [];
  bool _isLoading = true;
  String? _error;
  String _searchQuery = '';
  _SortBy _sortBy = _SortBy.name;

  @override
  void initState() {
    super.initState();
    _loadStock();
  }

  Future<void> _loadStock() async {
    setState(() {
      _isLoading = true;
      _error = null;
    });
    try {
      final response = await _repository.getStock();
      if (response.isSuccess) {
        setState(() {
          _items = response.data ?? [];
          _isLoading = false;
          _applyFilters();
        });
      } else {
        setState(() {
          _error = response.error ?? 'Stok yüklenemedi';
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

  void _applyFilters() {
    var result = List<StockItem>.from(_items);

    if (_searchQuery.isNotEmpty) {
      final query = _searchQuery.toLowerCase();
      result = result.where((item) {
        return (item.name ?? '').toLowerCase().contains(query) ||
            (item.supplierName ?? '').toLowerCase().contains(query);
      }).toList();
    }

    switch (_sortBy) {
      case _SortBy.name:
        result.sort((a, b) =>
            (a.name ?? '').compareTo(b.name ?? ''));
      case _SortBy.quantity:
        result.sort((a, b) =>
            (a.quantity ?? 0).compareTo(b.quantity ?? 0));
      case _SortBy.supplier:
        result.sort((a, b) =>
            (a.supplierName ?? '').compareTo(b.supplierName ?? ''));
    }

    setState(() => _filteredItems = result);
  }

  void _onSearchChanged(String query) {
    _searchQuery = query;
    _applyFilters();
  }

  void _onSortChanged(_SortBy sort) {
    _sortBy = sort;
    _applyFilters();
  }

  Future<void> _openMovementSheet(StockItem item, _StockAction action) async {
    if (!ReadonlyGuard.ensureCanMutate(context)) return;
    final result = await showModalBottomSheet<_MovementResult>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Theme.of(context).cardColor,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (_) => _StockMovementSheet(item: item, action: action),
    );
    if (result == null) return;
    if (!mounted) return;

    final messenger = ScaffoldMessenger.of(context);
    ApiResponse<Map<String, dynamic>> res;
    switch (action) {
      case _StockAction.add:
        res = await _repository.addStock(
          itemId: item.stockId ?? '',
          quantity: result.quantity,
          unit: item.unit ?? 'adet',
          notes: result.notes,
        );
      case _StockAction.remove:
        res = await _repository.removeStock(
          itemId: item.stockId ?? '',
          quantity: result.quantity,
          unit: item.unit ?? 'adet',
          notes: result.notes,
        );
      case _StockAction.adjust:
        res = await _repository.adjustStock(
          itemId: item.stockId ?? '',
          newQuantity: result.quantity,
          unit: item.unit ?? 'adet',
          notes: result.notes,
        );
    }

    if (!mounted) return;
    if (res.isSuccess) {
      messenger.showSnackBar(SnackBar(
        content: Text('${_actionLabel(action)} başarılı'),
        backgroundColor: Colors.green.shade600,
      ));
      _loadStock();
    } else {
      messenger.showSnackBar(SnackBar(
        content: Text(res.error ?? 'İşlem başarısız'),
        backgroundColor: Colors.red.shade600,
      ));
    }
  }

  String _actionLabel(_StockAction a) {
    switch (a) {
      case _StockAction.add:
        return 'Stok girişi';
      case _StockAction.remove:
        return 'Stok çıkışı';
      case _StockAction.adjust:
        return 'Stok düzeltme';
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        title: Text(
          'Stok',
          style: TextStyle(
            color: context.brandTextPrimary,
            fontWeight: FontWeight.w700,
            fontSize: 18,
          ),
        ),
        backgroundColor: Theme.of(context).scaffoldBackgroundColor,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        centerTitle: false,
        actions: [
          PopupMenuButton<_SortBy>(
            icon: Icon(Icons.sort_rounded, color: context.brandTextPrimary),
            onSelected: _onSortChanged,
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(AppRadius.md),
            ),
            itemBuilder: (_) => [
              _sortMenuItem(_SortBy.name, 'Ada Göre', Icons.sort_by_alpha_rounded),
              _sortMenuItem(_SortBy.quantity, 'Miktara Göre',
                  Icons.format_list_numbered_rounded),
              _sortMenuItem(
                  _SortBy.supplier, 'Tedarikçiye Göre', Icons.local_shipping_rounded),
            ],
          ),
        ],
      ),
      body: _isLoading
          ? _buildSkeleton()
          : _error != null
              ? QEmptyState(
                  icon: Icons.error_outline_rounded,
                  title: 'Stok yüklenemedi',
                  message: _error!,
                  action: FilledButton.icon(
                    onPressed: _loadStock,
                    icon: const Icon(Icons.refresh_rounded, size: 18),
                    label: const Text('Tekrar Dene'),
                    style: FilledButton.styleFrom(
                      backgroundColor: AppColors.primary,
                      padding: const EdgeInsets.symmetric(
                          horizontal: 20, vertical: 12),
                    ),
                  ),
                )
              : RefreshIndicator(
                  onRefresh: _loadStock,
                  color: AppColors.primary,
                  child: Column(
                    children: [
                      Padding(
                        padding: const EdgeInsets.fromLTRB(
                            AppSpacing.lg, AppSpacing.sm, AppSpacing.lg, AppSpacing.sm),
                        child: _StockSearchField(onChanged: _onSearchChanged),
                      ),
                      if (_items.isNotEmpty)
                        Padding(
                          padding: const EdgeInsets.fromLTRB(
                              AppSpacing.lg, 0, AppSpacing.lg, AppSpacing.sm),
                          child: _StockSummary(items: _items),
                        ),
                      Expanded(
                        child: _filteredItems.isEmpty
                            ? QEmptyState(
                                icon: Icons.inventory_2_rounded,
                                title: 'Stok bulunamadı',
                                message:
                                    'Aradığınız kritere uygun stok kalemi yok.',
                              )
                            : ListView.separated(
                                padding: const EdgeInsets.fromLTRB(
                                    AppSpacing.lg,
                                    AppSpacing.xs,
                                    AppSpacing.lg,
                                    100),
                                itemCount: _filteredItems.length,
                                separatorBuilder: (_, __) =>
                                    const SizedBox(height: AppSpacing.sm),
                                itemBuilder: (context, index) {
                                  final item = _filteredItems[index];
                                  return _StockCard(
                                    item: item,
                                    onAction: (a) =>
                                        _openMovementSheet(item, a),
                                  );
                                },
                              ),
                      ),
                    ],
                  ),
                ),
      floatingActionButton: _items.isEmpty
          ? null
          : _StockFab(
              onPressed: () async {
                if (_items.isEmpty) return;
                HapticFeedback.lightImpact();
                final item = await showModalBottomSheet<StockItem>(
                  context: context,
                  isScrollControlled: true,
                  backgroundColor: Theme.of(context).cardColor,
                  shape: const RoundedRectangleBorder(
                    borderRadius:
                        BorderRadius.vertical(top: Radius.circular(20)),
                  ),
                  builder: (_) => _StockPickerSheet(items: _items),
                );
                if (item != null && mounted) {
                  _openMovementSheet(item, _StockAction.add);
                }
              },
            ),
    );
  }

  PopupMenuItem<_SortBy> _sortMenuItem(
      _SortBy value, String label, IconData icon) {
    final active = _sortBy == value;
    return PopupMenuItem(
      value: value,
      child: Row(
        children: [
          Icon(
            icon,
            size: 16,
            color: active ? AppColors.primary : context.brandTextHint,
          ),
          const SizedBox(width: 10),
          Text(
            label,
            style: TextStyle(
              fontSize: 13,
              fontWeight: active ? FontWeight.w800 : FontWeight.w600,
              color: active
                  ? AppColors.primary
                  : context.brandTextPrimary,
            ),
          ),
          if (active) ...[
            const Spacer(),
            const Icon(Icons.check_rounded,
                size: 16, color: AppColors.primary),
          ],
        ],
      ),
    );
  }

  Widget _buildSkeleton() {
    return ListView(
      padding: const EdgeInsets.fromLTRB(
          AppSpacing.lg, AppSpacing.md, AppSpacing.lg, AppSpacing.xl),
      children: [
        const QSkeleton(height: 46, radius: AppRadius.md),
        const SizedBox(height: AppSpacing.sm),
        const QSkeleton(height: 70, radius: AppRadius.md),
        const SizedBox(height: AppSpacing.md),
        for (var i = 0; i < 6; i++) ...[
          const QSkeleton(height: 84, radius: AppRadius.lg),
          const SizedBox(height: AppSpacing.sm),
        ],
      ],
    );
  }
}

class _StockSearchField extends StatelessWidget {
  final ValueChanged<String> onChanged;
  const _StockSearchField({required this.onChanged});

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: context.brandSurfaceMuted,
        borderRadius: BorderRadius.circular(AppRadius.md),
        border: Border.all(color: context.brandBorder, width: 0.8),
      ),
      child: TextField(
        onChanged: onChanged,
        style: TextStyle(
          fontSize: 14,
          fontWeight: FontWeight.w500,
          color: context.brandTextPrimary,
        ),
        decoration: InputDecoration(
          hintText: 'Stok ara...',
          hintStyle: TextStyle(
            color: context.brandTextHint,
            fontSize: 13.5,
            fontWeight: FontWeight.w500,
          ),
          prefixIcon: Icon(Icons.search_rounded,
              size: 20, color: context.brandTextHint),
          border: InputBorder.none,
          enabledBorder: InputBorder.none,
          focusedBorder: InputBorder.none,
          isDense: true,
          contentPadding:
              const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
        ),
      ),
    );
  }
}

/// Listenin üstünde hızlı bir "durum çubuğu" — toplam kalem, düşük
/// ve kritik stokları tek bakışta göster.
class _StockSummary extends StatelessWidget {
  final List<StockItem> items;
  const _StockSummary({required this.items});

  @override
  Widget build(BuildContext context) {
    int low = 0;
    int critical = 0;
    for (final it in items) {
      final q = it.quantity ?? 0;
      final minQ = it.minQuantity ?? 0;
      if (minQ > 0) {
        if (q <= minQ * 0.5) {
          critical++;
        } else if (q <= minQ) {
          low++;
        }
      }
    }
    return Row(
      children: [
        Expanded(
          child: _SummaryTile(
            icon: Icons.inventory_2_rounded,
            color: AppColors.primary,
            label: 'Toplam',
            value: '${items.length}',
          ),
        ),
        const SizedBox(width: AppSpacing.sm),
        Expanded(
          child: _SummaryTile(
            icon: Icons.trending_down_rounded,
            color: AppColors.warningBright,
            label: 'Düşük',
            value: '$low',
          ),
        ),
        const SizedBox(width: AppSpacing.sm),
        Expanded(
          child: _SummaryTile(
            icon: Icons.warning_amber_rounded,
            color: AppColors.errorBright,
            label: 'Kritik',
            value: '$critical',
          ),
        ),
      ],
    );
  }
}

class _SummaryTile extends StatelessWidget {
  final IconData icon;
  final Color color;
  final String label;
  final String value;

  const _SummaryTile({
    required this.icon,
    required this.color,
    required this.label,
    required this.value,
  });

  @override
  Widget build(BuildContext context) {
    final dark = context.isDark;
    return Container(
      padding: const EdgeInsets.symmetric(
          horizontal: 10, vertical: 10),
      decoration: BoxDecoration(
        color: Theme.of(context).cardColor,
        borderRadius: BorderRadius.circular(AppRadius.md),
        border: Border.all(
          color: color.withValues(alpha: dark ? 0.35 : 0.18),
          width: 0.8,
        ),
      ),
      child: Row(
        children: [
          Container(
            width: 32,
            height: 32,
            decoration: BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
                colors: [
                  color.withValues(alpha: dark ? 0.40 : 0.20),
                  color.withValues(alpha: dark ? 0.22 : 0.08),
                ],
              ),
              borderRadius: BorderRadius.circular(AppRadius.sm),
            ),
            alignment: Alignment.center,
            child: Icon(icon, color: color, size: 17),
          ),
          const SizedBox(width: 8),
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                value,
                style: TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.w800,
                  color: context.brandTextPrimary,
                  height: 1,
                ),
              ),
              Text(
                label,
                style: TextStyle(
                  fontSize: 10.5,
                  color: context.brandTextSecondary,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _StockFab extends StatelessWidget {
  final VoidCallback onPressed;
  const _StockFab({required this.onPressed});

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(16),
        gradient: AppColors.brandGradient,
        boxShadow: [
          BoxShadow(
            color: AppColors.primary.withValues(alpha: 0.40),
            blurRadius: 18,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Material(
        color: Colors.transparent,
        borderRadius: BorderRadius.circular(16),
        child: InkWell(
          borderRadius: BorderRadius.circular(16),
          onTap: onPressed,
          splashColor: Colors.white.withValues(alpha: 0.15),
          child: Padding(
            padding: const EdgeInsets.symmetric(
                horizontal: 18, vertical: 14),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: const [
                Icon(Icons.add_box_rounded,
                    color: Colors.white, size: 20),
                SizedBox(width: 8),
                Text(
                  'Stok Girişi',
                  style: TextStyle(
                    color: Colors.white,
                    fontSize: 14,
                    fontWeight: FontWeight.w800,
                    letterSpacing: 0.1,
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

enum _StockAction { add, remove, adjust }

class _MovementResult {
  final double quantity;
  final String? notes;
  const _MovementResult({required this.quantity, this.notes});
}

class _StockCard extends StatelessWidget {
  final StockItem item;
  final ValueChanged<_StockAction>? onAction;

  const _StockCard({required this.item, this.onAction});

  @override
  Widget build(BuildContext context) {
    final isLow = item.isLowStock == true ||
        (item.quantity != null &&
            item.minQuantity != null &&
            item.quantity! <= item.minQuantity!);
    final isCritical = item.quantity != null &&
        item.minQuantity != null &&
        item.quantity! <= (item.minQuantity! * 0.5);

    // Sol şerit rengi — yeşil (sağlıklı), sarı (düşük), kırmızı (kritik).
    // Kullanıcı listeye bakar bakmaz hangi kalem acil müdahale istiyor
    // anında okuyabilsin.
    final Color accent = isCritical
        ? AppColors.errorBright
        : isLow
            ? AppColors.warningBright
            : AppColors.successAlt;
    final dark = context.isDark;

    return Container(
      decoration: BoxDecoration(
        color: Theme.of(context).cardColor,
        borderRadius: BorderRadius.circular(AppRadius.lg),
        border: Border.all(
          color: isCritical || isLow
              ? accent.withValues(alpha: dark ? 0.40 : 0.22)
              : context.brandBorder,
          width: 0.8,
        ),
        boxShadow: AppShadows.card(dark),
      ),
      clipBehavior: Clip.antiAlias,
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: onAction == null
              ? null
              : () {
                  HapticFeedback.selectionClick();
                  showModalBottomSheet<void>(
                    context: context,
                    backgroundColor: Theme.of(context).cardColor,
                    shape: const RoundedRectangleBorder(
                      borderRadius:
                          BorderRadius.vertical(top: Radius.circular(20)),
                    ),
                    builder: (_) => SafeArea(
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          const SizedBox(height: 8),
                          Container(
                            width: 44,
                            height: 4,
                            decoration: BoxDecoration(
                              color: context.brandBorder,
                              borderRadius: BorderRadius.circular(2),
                            ),
                          ),
                          const SizedBox(height: 12),
                          _ActionTile(
                            icon: Icons.add_box_rounded,
                            color: AppColors.successAlt,
                            title: 'Stok Girişi',
                            subtitle: 'Yeni mal girişi ekle',
                            onTap: () {
                              Navigator.pop(context);
                              onAction?.call(_StockAction.add);
                            },
                          ),
                          _ActionTile(
                            icon: Icons.remove_circle_outline_rounded,
                            color: AppColors.accentOrange,
                            title: 'Stok Çıkışı',
                            subtitle: 'Tüketimi / fireyi düş',
                            onTap: () {
                              Navigator.pop(context);
                              onAction?.call(_StockAction.remove);
                            },
                          ),
                          _ActionTile(
                            icon: Icons.tune_rounded,
                            color: AppColors.accentIndigo,
                            title: 'Stok Düzeltme',
                            subtitle: 'Sayım sonucunu yaz',
                            onTap: () {
                              Navigator.pop(context);
                              onAction?.call(_StockAction.adjust);
                            },
                          ),
                          const SizedBox(height: 8),
                        ],
                      ),
                    ),
                  );
                },
          splashColor: accent.withValues(alpha: 0.08),
          highlightColor: accent.withValues(alpha: 0.04),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Container(width: 4, color: accent),
              Expanded(
                child: Padding(
                  padding: const EdgeInsets.all(AppSpacing.md),
                  child: Row(
                    children: [
                      Container(
                        width: 44,
                        height: 44,
                        decoration: BoxDecoration(
                          gradient: LinearGradient(
                            begin: Alignment.topLeft,
                            end: Alignment.bottomRight,
                            colors: [
                              accent.withValues(alpha: dark ? 0.40 : 0.20),
                              accent.withValues(alpha: dark ? 0.22 : 0.08),
                            ],
                          ),
                          borderRadius:
                              BorderRadius.circular(AppRadius.md),
                          border: Border.all(
                            color: accent.withValues(
                                alpha: dark ? 0.42 : 0.24),
                            width: 0.6,
                          ),
                        ),
                        alignment: Alignment.center,
                        child: Icon(
                          Icons.inventory_2_rounded,
                          color: accent,
                          size: 20,
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              children: [
                                Expanded(
                                  child: Text(
                                    item.name ?? '',
                                    style: TextStyle(
                                      fontSize: 14.5,
                                      fontWeight: FontWeight.w800,
                                      color: context.brandTextPrimary,
                                      letterSpacing: -0.1,
                                    ),
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                  ),
                                ),
                                if (isLow)
                                  Container(
                                    padding: const EdgeInsets.symmetric(
                                      horizontal: 8,
                                      vertical: 3,
                                    ),
                                    decoration: BoxDecoration(
                                      color: accent.withValues(alpha: 0.14),
                                      borderRadius:
                                          BorderRadius.circular(999),
                                      border: Border.all(
                                        color: accent.withValues(alpha: 0.28),
                                        width: 0.6,
                                      ),
                                    ),
                                    child: Text(
                                      isCritical ? 'Kritik' : 'Düşük',
                                      style: TextStyle(
                                        color: accent,
                                        fontSize: 10.5,
                                        fontWeight: FontWeight.w800,
                                      ),
                                    ),
                                  ),
                              ],
                            ),
                            const SizedBox(height: 6),
                            Row(
                              children: [
                                Text(
                                  item.quantity?.toStringAsFixed(1) ?? '0',
                                  style: TextStyle(
                                    fontSize: 15,
                                    fontWeight: FontWeight.w800,
                                    color: accent,
                                    letterSpacing: -0.2,
                                  ),
                                ),
                                const SizedBox(width: 3),
                                Text(
                                  item.unit ?? '',
                                  style: TextStyle(
                                    fontSize: 11.5,
                                    fontWeight: FontWeight.w600,
                                    color: context.brandTextSecondary,
                                  ),
                                ),
                                if (item.minQuantity != null) ...[
                                  const SizedBox(width: 10),
                                  Text(
                                    'min ${item.minQuantity?.toStringAsFixed(1)}',
                                    style: TextStyle(
                                      fontSize: 11,
                                      fontWeight: FontWeight.w600,
                                      color: context.brandTextHint,
                                    ),
                                  ),
                                ],
                                const Spacer(),
                                if (item.supplierName != null &&
                                    item.supplierName!.isNotEmpty)
                                  Row(
                                    mainAxisSize: MainAxisSize.min,
                                    children: [
                                      Icon(
                                        Icons.local_shipping_rounded,
                                        size: 12,
                                        color: context.brandTextHint,
                                      ),
                                      const SizedBox(width: 4),
                                      Text(
                                        item.supplierName!,
                                        style: TextStyle(
                                          fontSize: 11,
                                          color: context.brandTextHint,
                                          fontWeight: FontWeight.w500,
                                        ),
                                      ),
                                    ],
                                  ),
                              ],
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _ActionTile extends StatelessWidget {
  final IconData icon;
  final Color color;
  final String title;
  final String subtitle;
  final VoidCallback onTap;

  const _ActionTile({
    required this.icon,
    required this.color,
    required this.title,
    required this.subtitle,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final dark = context.isDark;
    return ListTile(
      onTap: onTap,
      contentPadding: const EdgeInsets.symmetric(
          horizontal: AppSpacing.lg, vertical: 4),
      leading: Container(
        width: 40,
        height: 40,
        decoration: BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [
              color.withValues(alpha: dark ? 0.40 : 0.20),
              color.withValues(alpha: dark ? 0.22 : 0.08),
            ],
          ),
          borderRadius: BorderRadius.circular(AppRadius.md),
          border: Border.all(
            color: color.withValues(alpha: dark ? 0.42 : 0.24),
            width: 0.6,
          ),
        ),
        child: Icon(icon, color: color, size: 20),
      ),
      title: Text(
        title,
        style: TextStyle(
          fontSize: 14.5,
          fontWeight: FontWeight.w800,
          color: context.brandTextPrimary,
          letterSpacing: -0.1,
        ),
      ),
      subtitle: Text(
        subtitle,
        style: TextStyle(
          fontSize: 12,
          color: context.brandTextSecondary,
          fontWeight: FontWeight.w500,
        ),
      ),
      trailing: Icon(Icons.arrow_forward_ios_rounded,
          size: 14, color: context.brandTextHint),
    );
  }
}

class _StockPickerSheet extends StatefulWidget {
  final List<StockItem> items;
  const _StockPickerSheet({required this.items});

  @override
  State<_StockPickerSheet> createState() => _StockPickerSheetState();
}

class _StockPickerSheetState extends State<_StockPickerSheet> {
  String _query = '';

  @override
  Widget build(BuildContext context) {
    final q = _query.toLowerCase();
    final items = q.isEmpty
        ? widget.items
        : widget.items
            .where((i) => (i.name ?? '').toLowerCase().contains(q))
            .toList();
    return DraggableScrollableSheet(
      expand: false,
      initialChildSize: 0.7,
      minChildSize: 0.4,
      maxChildSize: 0.95,
      builder: (_, scroll) => Padding(
        padding: EdgeInsets.only(
          bottom: MediaQuery.of(context).viewInsets.bottom,
        ),
        child: Column(
          children: [
            const SizedBox(height: 8),
            Container(
              width: 40,
              height: 4,
              decoration: BoxDecoration(
                color: Colors.grey.shade300,
                borderRadius: BorderRadius.circular(2),
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
              child: Row(
                children: [
                  const Expanded(
                    child: Text('Ürün seç',
                        style: TextStyle(
                            fontSize: 17, fontWeight: FontWeight.w700)),
                  ),
                  IconButton(
                    icon: const Icon(Icons.close),
                    onPressed: () => Navigator.pop(context),
                  ),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
              child: TextField(
                onChanged: (v) => setState(() => _query = v),
                decoration: InputDecoration(
                  hintText: 'Ürün ara...',
                  prefixIcon: const Icon(Icons.search),
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                ),
              ),
            ),
            Expanded(
              child: ListView.separated(
                controller: scroll,
                itemCount: items.length,
                separatorBuilder: (_, __) => const Divider(height: 1),
                itemBuilder: (_, i) {
                  final it = items[i];
                  return ListTile(
                    title: Text(it.name ?? ''),
                    subtitle: Text(
                      '${it.quantity?.toStringAsFixed(1) ?? '0'} ${it.unit ?? ''}',
                    ),
                    onTap: () => Navigator.pop(context, it),
                  );
                },
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _StockMovementSheet extends StatefulWidget {
  final StockItem item;
  final _StockAction action;
  const _StockMovementSheet({required this.item, required this.action});

  @override
  State<_StockMovementSheet> createState() => _StockMovementSheetState();
}

class _StockMovementSheetState extends State<_StockMovementSheet> {
  final _qtyCtrl = TextEditingController();
  final _notesCtrl = TextEditingController();
  final _formKey = GlobalKey<FormState>();

  @override
  void dispose() {
    _qtyCtrl.dispose();
    _notesCtrl.dispose();
    super.dispose();
  }

  String get _title {
    switch (widget.action) {
      case _StockAction.add:
        return 'Stok Girişi';
      case _StockAction.remove:
        return 'Stok Çıkışı';
      case _StockAction.adjust:
        return 'Stok Düzeltme';
    }
  }

  String get _qtyLabel {
    switch (widget.action) {
      case _StockAction.add:
        return 'Eklenecek miktar';
      case _StockAction.remove:
        return 'Çıkarılacak miktar';
      case _StockAction.adjust:
        return 'Yeni toplam miktar';
    }
  }

  @override
  Widget build(BuildContext context) {
    final unit = widget.item.unit ?? '';
    return Padding(
      padding: EdgeInsets.only(
        bottom: MediaQuery.of(context).viewInsets.bottom,
        left: 20,
        right: 20,
        top: 16,
      ),
      child: Form(
        key: _formKey,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Center(
              child: Container(
                width: 40,
                height: 4,
                decoration: BoxDecoration(
                  color: Colors.grey.shade300,
                  borderRadius: BorderRadius.circular(2),
                ),
              ),
            ),
            const SizedBox(height: 16),
            Text(_title,
                style: const TextStyle(
                    fontSize: 18, fontWeight: FontWeight.w700)),
            const SizedBox(height: 4),
            Text(
              '${widget.item.name ?? ''} • mevcut: ${widget.item.quantity?.toStringAsFixed(1) ?? '0'} $unit',
              style: TextStyle(color: AppColors.textSecondary),
            ),
            const SizedBox(height: 16),
            TextFormField(
              controller: _qtyCtrl,
              autofocus: true,
              keyboardType: const TextInputType.numberWithOptions(decimal: true),
              inputFormatters: [
                FilteringTextInputFormatter.allow(RegExp(r'[0-9.,]')),
              ],
              decoration: InputDecoration(
                labelText: _qtyLabel,
                suffixText: unit,
                border: const OutlineInputBorder(),
              ),
              validator: (v) {
                final raw = (v ?? '').replaceAll(',', '.');
                final parsed = double.tryParse(raw);
                if (parsed == null) return 'Geçerli bir sayı girin';
                if (widget.action != _StockAction.adjust && parsed <= 0) {
                  return 'Pozitif bir değer girin';
                }
                if (parsed < 0) return 'Negatif olamaz';
                return null;
              },
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _notesCtrl,
              decoration: const InputDecoration(
                labelText: 'Not (opsiyonel)',
                border: OutlineInputBorder(),
              ),
            ),
            const SizedBox(height: 20),
            SizedBox(
              width: double.infinity,
              child: FilledButton(
                style: FilledButton.styleFrom(
                  backgroundColor: AppColors.primary,
                  padding: const EdgeInsets.symmetric(vertical: 14),
                ),
                onPressed: () {
                  if (!_formKey.currentState!.validate()) return;
                  final qty = double.parse(
                      _qtyCtrl.text.replaceAll(',', '.'));
                  Navigator.pop(
                    context,
                    _MovementResult(
                      quantity: qty,
                      notes: _notesCtrl.text.trim().isEmpty
                          ? null
                          : _notesCtrl.text.trim(),
                    ),
                  );
                },
                child: Text(_title),
              ),
            ),
            const SizedBox(height: 12),
          ],
        ),
      ),
    );
  }
}
