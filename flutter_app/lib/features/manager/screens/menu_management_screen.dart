import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:shimmer/shimmer.dart';

import 'package:qordy_app/config/theme.dart';
import 'package:qordy_app/core/ui/primitives.dart';
import 'package:qordy_app/core/widgets/app_error_widget.dart';
import 'package:qordy_app/models/category.dart';
import 'package:qordy_app/models/menu_item.dart';

import '../cubit/menu_cubit.dart';
import '../cubit/menu_state.dart';

/// Cubit provided by the router.
class MenuManagementScreen extends StatefulWidget {
  const MenuManagementScreen({super.key});

  @override
  State<MenuManagementScreen> createState() => _MenuManagementScreenState();
}

class _MenuManagementScreenState extends State<MenuManagementScreen> {
  final _searchController = TextEditingController();

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  Future<void> _onRefresh() async {
    await context.read<MenuManagementCubit>().refresh();
  }

  void _showAddItemForm() {
    _openItemForm(null);
  }

  void _showEditItemForm(MenuItem item) {
    _openItemForm(item);
  }

  void _openItemForm(MenuItem? existing) {
    final isEditing = existing != null;
    final nameCtrl = TextEditingController(text: existing?.name ?? '');
    final descCtrl =
        TextEditingController(text: existing?.description ?? '');
    final priceCtrl = TextEditingController(
        text: existing?.price?.toStringAsFixed(2) ?? '');
    final prepTimeCtrl = TextEditingController(
        text: existing?.preparationTime?.toString() ?? '');
    String? selectedCategoryId = existing?.categoryId;
    final formKey = GlobalKey<FormState>();

    final cubit = context.read<MenuManagementCubit>();
    final categories = cubit.state is MenuManagementLoaded
        ? (cubit.state as MenuManagementLoaded).categories
        : <Category>[];

    // Dispose local TextEditingControllers when the edit route pops,
    // otherwise re-opening the form leaks them on every edit cycle.
    Navigator.of(context)
        .push(
      MaterialPageRoute(
        builder: (ctx) {
          return StatefulBuilder(
            builder: (ctx, setFormState) {
              return Scaffold(
                backgroundColor: Theme.of(context).scaffoldBackgroundColor,
                appBar: AppBar(
                  title: Text(isEditing ? 'Ürün Düzenle' : 'Yeni Ürün'),
                  backgroundColor: Theme.of(context).scaffoldBackgroundColor,
                  surfaceTintColor: Colors.transparent,
                ),
                body: Form(
                  key: formKey,
                  child: ListView(
                    padding: const EdgeInsets.all(20),
                    children: [
                      TextFormField(
                        controller: nameCtrl,
                        decoration: const InputDecoration(
                          labelText: 'Ürün Adı',
                          prefixIcon: Icon(Icons.restaurant_menu_outlined),
                        ),
                        validator: (v) => (v == null || v.trim().isEmpty)
                            ? 'Ürün adı zorunlu'
                            : null,
                      ),
                      const SizedBox(height: 16),
                      TextFormField(
                        controller: descCtrl,
                        decoration: const InputDecoration(
                          labelText: 'Açıklama',
                          prefixIcon: Icon(Icons.description_outlined),
                        ),
                        maxLines: 3,
                        minLines: 1,
                      ),
                      const SizedBox(height: 16),
                      TextFormField(
                        controller: priceCtrl,
                        decoration: const InputDecoration(
                          labelText: 'Fiyat (₺)',
                          prefixIcon: Icon(Icons.payments_outlined),
                        ),
                        keyboardType: const TextInputType.numberWithOptions(
                            decimal: true),
                        validator: (v) {
                          if (v == null || v.trim().isEmpty) {
                            return 'Fiyat zorunlu';
                          }
                          if (double.tryParse(v.trim()) == null) {
                            return 'Geçerli bir fiyat girin';
                          }
                          return null;
                        },
                      ),
                      const SizedBox(height: 16),
                      DropdownButtonFormField<String>(
                        initialValue: selectedCategoryId,
                        decoration: const InputDecoration(
                          labelText: 'Kategori',
                          prefixIcon: Icon(Icons.category_outlined),
                        ),
                        items: categories.map((cat) {
                          return DropdownMenuItem<String>(
                            value: cat.categoryId,
                            child: Text(cat.name ?? '-'),
                          );
                        }).toList(),
                        onChanged: (v) =>
                            setFormState(() => selectedCategoryId = v),
                        validator: (v) => (v == null || v.isEmpty)
                            ? 'Kategori seçiniz'
                            : null,
                      ),
                      const SizedBox(height: 16),
                      TextFormField(
                        controller: prepTimeCtrl,
                        decoration: const InputDecoration(
                          labelText: 'Hazırlanma Süresi (dk)',
                          prefixIcon: Icon(Icons.timer_outlined),
                        ),
                        keyboardType: TextInputType.number,
                      ),
                      const SizedBox(height: 32),
                      SizedBox(
                        width: double.infinity,
                        child: ElevatedButton(
                          onPressed: () {
                            if (!formKey.currentState!.validate()) return;
                            final data = <String, dynamic>{
                              'name': nameCtrl.text.trim(),
                              if (descCtrl.text.trim().isNotEmpty)
                                'description': descCtrl.text.trim(),
                              'price':
                                  double.parse(priceCtrl.text.trim()),
                              if (selectedCategoryId != null)
                                'category_id': selectedCategoryId,
                              if (selectedCategoryId != null)
                                'categoryId': selectedCategoryId,
                              if (prepTimeCtrl.text.trim().isNotEmpty)
                                'preparation_time':
                                    int.tryParse(prepTimeCtrl.text.trim()),
                              if (prepTimeCtrl.text.trim().isNotEmpty)
                                'preparationTime':
                                    int.tryParse(prepTimeCtrl.text.trim()),
                            };

                            if (isEditing) {
                              data['menu_item_id'] = existing.menuItemId;
                              data['menuItemId'] = existing.menuItemId;
                              cubit.updateItem(data);
                            } else {
                              cubit.addItem(data);
                            }
                            Navigator.of(ctx).pop();
                          },
                          child:
                              Text(isEditing ? 'Güncelle' : 'Ürün Ekle'),
                        ),
                      ),
                    ],
                  ),
                ),
              );
            },
          );
        },
      ),
    )
        .whenComplete(() {
      nameCtrl.dispose();
      descCtrl.dispose();
      priceCtrl.dispose();
      prepTimeCtrl.dispose();
    });
  }

  void _confirmDelete(MenuItem item) {
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Ürün Sil'),
        content:
            Text('${item.name ?? 'Bu ürün'} silinecek. Emin misiniz?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(ctx).pop(),
            child: const Text('İptal'),
          ),
          TextButton(
            onPressed: () {
              Navigator.of(ctx).pop();
              if (item.menuItemId != null) {
                context
                    .read<MenuManagementCubit>()
                    .deleteItem(item.menuItemId!);
              }
            },
            style: TextButton.styleFrom(foregroundColor: AppColors.error),
            child: const Text('Sil'),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        title: Text(
          'Menü Yönetimi',
          style: TextStyle(
            color: context.brandTextPrimary,
            fontWeight: FontWeight.w700,
            fontSize: 18,
          ),
        ),
        backgroundColor: Theme.of(context).scaffoldBackgroundColor,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        scrolledUnderElevation: 0.5,
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: _showAddItemForm,
        backgroundColor: AppColors.primary,
        foregroundColor: Colors.white,
        icon: const Icon(Icons.add_rounded),
        label: const Text(
          'Yeni Ürün',
          style: TextStyle(fontWeight: FontWeight.w700),
        ),
      ),
      body: Column(
        children: [
          _buildSearch(),
          _buildCategoryTabs(),
          Expanded(
            child: BlocConsumer<MenuManagementCubit, MenuManagementState>(
              listenWhen: (prev, curr) => curr is MenuManagementError,
              listener: (context, state) {
                if (state is MenuManagementError) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(
                      content: Text(state.message),
                      backgroundColor: AppColors.error,
                    ),
                  );
                }
              },
              buildWhen: (prev, curr) =>
                  curr is MenuManagementLoaded ||
                  curr is MenuManagementLoading ||
                  curr is MenuManagementInitial ||
                  (curr is MenuManagementError &&
                      prev is! MenuManagementLoaded),
              builder: (context, state) {
                if (state is MenuManagementLoading) return _buildShimmer();

                if (state is MenuManagementError) {
                  return AppErrorWidget(
                    message: state.message,
                    onRetry: _onRefresh,
                  );
                }

                if (state is MenuManagementLoaded) {
                  return _buildMenuList(state);
                }

                return const SizedBox.shrink();
              },
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildSearch() {
    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 8, 20, 8),
      child: TextField(
        controller: _searchController,
        onChanged: (v) =>
            context.read<MenuManagementCubit>().search(v),
        decoration: InputDecoration(
          hintText: 'Ürün ara...',
          prefixIcon: const Icon(Icons.search, color: AppColors.textHint),
          suffixIcon: _searchController.text.isNotEmpty
              ? IconButton(
                  icon: const Icon(Icons.close, size: 20),
                  onPressed: () {
                    _searchController.clear();
                    context.read<MenuManagementCubit>().search('');
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
    );
  }

  Widget _buildCategoryTabs() {
    return BlocBuilder<MenuManagementCubit, MenuManagementState>(
      buildWhen: (prev, curr) =>
          curr is MenuManagementLoaded ||
          curr is MenuManagementInitial,
      builder: (context, state) {
        if (state is! MenuManagementLoaded ||
            state.categories.isEmpty) {
          return const SizedBox.shrink();
        }

        return SizedBox(
          height: 44,
          child: ListView(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.fromLTRB(
                AppSpacing.lg, 0, AppSpacing.lg, AppSpacing.sm),
            children: [
              _CategoryChip(
                label: 'Tümü',
                selected: state.selectedCategoryId == null,
                onTap: () => context
                    .read<MenuManagementCubit>()
                    .selectCategory(null),
              ),
              ...state.categories.map((cat) {
                final isSelected =
                    state.selectedCategoryId == cat.categoryId;
                return _CategoryChip(
                  label: cat.name ?? '-',
                  selected: isSelected,
                  onTap: () => context
                      .read<MenuManagementCubit>()
                      .selectCategory(cat.categoryId),
                );
              }),
            ],
          ),
        );
      },
    );
  }

  Widget _buildMenuList(MenuManagementLoaded state) {
    final items = state.filteredItems;

    if (items.isEmpty) {
      return RefreshIndicator(
        onRefresh: _onRefresh,
        color: AppColors.primary,
        child: ListView(
          children: [
            SizedBox(
              height: MediaQuery.of(context).size.height * 0.55,
              child: QEmptyState(
                icon: Icons.restaurant_menu_rounded,
                title: state.searchQuery.isNotEmpty
                    ? 'Sonuç bulunamadı'
                    : 'Menüde ürün yok',
                message: state.searchQuery.isNotEmpty
                    ? 'Arama kriterinize uygun ürün yok. Farklı bir kelime deneyin.'
                    : 'İlk ürününüzü ekleyerek menünüzü oluşturmaya başlayın.',
                action: state.searchQuery.isEmpty
                    ? FilledButton.icon(
                        onPressed: _showAddItemForm,
                        icon: const Icon(Icons.add_rounded, size: 18),
                        label: const Text('Ürün Ekle'),
                        style: FilledButton.styleFrom(
                          backgroundColor: AppColors.primary,
                          padding: const EdgeInsets.symmetric(
                              horizontal: 20, vertical: 12),
                        ),
                      )
                    : null,
              ),
            ),
          ],
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: _onRefresh,
      color: AppColors.primary,
      child: ListView.builder(
        padding: const EdgeInsets.fromLTRB(20, 0, 20, 100),
        itemCount: items.length,
        itemBuilder: (context, index) => RepaintBoundary(
          child: _buildMenuCard(items[index]),
        ),
      ),
    );
  }

  Widget _buildMenuCard(MenuItem item) {
    final isAvailable = item.isAvailable ?? true;
    return RepaintBoundary(
      child: GestureDetector(
        onTap: () => _showEditItemForm(item),
        onLongPress: () => _confirmDelete(item),
        child: Container(
          margin: const EdgeInsets.only(bottom: AppSpacing.md),
          padding: const EdgeInsets.all(AppSpacing.md),
          decoration: BoxDecoration(
            color: Theme.of(context).cardColor,
            borderRadius: BorderRadius.circular(AppRadius.lg),
            border: Border.all(color: context.brandBorder, width: 0.6),
            boxShadow: AppShadows.card(context.isDark),
          ),
          child: Row(
            children: [
              _buildItemImage(item),
              const SizedBox(width: AppSpacing.md),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      item.name ?? '-',
                      style: TextStyle(
                        fontSize: 15,
                        fontWeight: FontWeight.w700,
                        color: context.brandTextPrimary,
                      ),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                    if (item.categoryName != null) ...[
                      const SizedBox(height: 3),
                      Text(
                        item.categoryName!,
                        style: TextStyle(
                          fontSize: 12,
                          color: context.brandTextHint,
                        ),
                      ),
                    ],
                    const SizedBox(height: 6),
                    Text(
                      '₺${item.price?.toStringAsFixed(2) ?? '0.00'}',
                      style: const TextStyle(
                        fontSize: 15,
                        fontWeight: FontWeight.w800,
                        color: AppColors.primary,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: AppSpacing.sm),
              Column(
                children: [
                  Switch(
                    value: isAvailable,
                    onChanged: (v) {
                      if (item.menuItemId != null) {
                        context
                            .read<MenuManagementCubit>()
                            .toggleAvailability(item.menuItemId!, v);
                      }
                    },
                    activeThumbColor: AppColors.primary,
                  ),
                  Container(
                    padding: const EdgeInsets.symmetric(
                        horizontal: 8, vertical: 2),
                    decoration: BoxDecoration(
                      color: (isAvailable
                              ? AppColors.success
                              : AppColors.error)
                          .withValues(alpha: 0.12),
                      borderRadius:
                          BorderRadius.circular(AppRadius.pill),
                    ),
                    child: Text(
                      isAvailable ? 'Aktif' : 'Pasif',
                      style: TextStyle(
                        fontSize: 10.5,
                        fontWeight: FontWeight.w700,
                        letterSpacing: 0.3,
                        color: isAvailable
                            ? AppColors.success
                            : AppColors.error,
                      ),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildItemImage(MenuItem item) {
    final muted = context.isDark
        ? AppColors.darkSurfaceMuted
        : AppColors.surfaceMuted;
    if (item.imageUrl != null && item.imageUrl!.isNotEmpty) {
      return ClipRRect(
        borderRadius: BorderRadius.circular(AppRadius.md),
        child: CachedNetworkImage(
          imageUrl: item.imageUrl!,
          width: 60,
          height: 60,
          fit: BoxFit.cover,
          fadeInDuration: const Duration(milliseconds: 180),
          placeholder: (_, __) => Container(
            width: 60,
            height: 60,
            color: muted,
            child: const Icon(Icons.image_outlined,
                size: 24, color: AppColors.iconDisabled),
          ),
          errorWidget: (_, __, ___) => Container(
            width: 60,
            height: 60,
            color: muted,
            child: const Icon(Icons.broken_image_outlined,
                size: 24, color: AppColors.iconDisabled),
          ),
        ),
      );
    }

    return Container(
      width: 60,
      height: 60,
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [
            AppColors.primary.withValues(alpha: 0.18),
            AppColors.primary.withValues(alpha: 0.08),
          ],
        ),
        borderRadius: BorderRadius.circular(AppRadius.md),
      ),
      child: const Icon(Icons.restaurant_rounded,
          size: 24, color: AppColors.primary),
    );
  }

  Widget _buildShimmer() {
    return Shimmer.fromColors(
      baseColor: context.isDark
          ? AppColors.darkSurfaceMuted
          : AppColors.border,
      highlightColor: context.isDark
          ? AppColors.darkCard
          : AppColors.surfaceMuted,
      child: ListView.builder(
        padding: const EdgeInsets.fromLTRB(
            AppSpacing.lg, 0, AppSpacing.lg, AppSpacing.xl),
        itemCount: 6,
        itemBuilder: (_, __) => Padding(
          padding: const EdgeInsets.only(bottom: AppSpacing.md),
          child: Container(
            height: 92,
            decoration: BoxDecoration(
              color: Theme.of(context).cardColor,
              borderRadius: BorderRadius.circular(AppRadius.lg),
            ),
          ),
        ),
      ),
    );
  }
}

class _CategoryChip extends StatelessWidget {
  const _CategoryChip({
    required this.label,
    required this.selected,
    required this.onTap,
  });

  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(right: AppSpacing.sm),
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(AppRadius.pill),
          child: AnimatedContainer(
            duration: const Duration(milliseconds: 220),
            curve: Curves.easeOutCubic,
            padding: const EdgeInsets.symmetric(
                horizontal: AppSpacing.md, vertical: 8),
            decoration: BoxDecoration(
              color: selected
                  ? AppColors.primary
                  : Theme.of(context).cardColor,
              borderRadius: BorderRadius.circular(AppRadius.pill),
              border: Border.all(
                color: selected ? AppColors.primary : context.brandBorder,
                width: 0.8,
              ),
              boxShadow: selected
                  ? [
                      BoxShadow(
                        color: AppColors.primary.withValues(alpha: 0.28),
                        blurRadius: 10,
                        offset: const Offset(0, 3),
                      ),
                    ]
                  : null,
            ),
            child: Text(
              label,
              style: TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w600,
                color: selected ? Colors.white : context.brandTextSecondary,
              ),
            ),
          ),
        ),
      ),
    );
  }
}
