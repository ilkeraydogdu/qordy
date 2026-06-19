import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:shimmer/shimmer.dart';

import 'package:qordy_app/config/theme.dart';
import 'package:qordy_app/core/widgets/app_error_widget.dart';
import 'package:qordy_app/models/staff.dart';

import '../cubit/staff_cubit.dart';
import '../cubit/staff_state.dart';

/// Cubit provided by the router.
class StaffManagementScreen extends StatefulWidget {
  const StaffManagementScreen({super.key});

  @override
  State<StaffManagementScreen> createState() => _StaffManagementScreenState();
}

class _StaffManagementScreenState extends State<StaffManagementScreen> {
  final _searchController = TextEditingController();

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  Future<void> _onRefresh() async {
    await context.read<StaffCubit>().refresh();
  }

  void _showAddStaffSheet() {
    _showStaffFormSheet(null);
  }

  void _showEditStaffSheet(Staff staff) {
    _showStaffFormSheet(staff);
  }

  void _showStaffFormSheet(Staff? existing) {
    final isEditing = existing != null;
    final nameCtrl = TextEditingController(text: existing?.name ?? '');
    final emailCtrl = TextEditingController(text: existing?.email ?? '');
    final phoneCtrl = TextEditingController(text: existing?.phone ?? '');
    final pinCtrl = TextEditingController(text: existing?.pin ?? '');
    String? selectedRoleId = existing?.roleId;
    final formKey = GlobalKey<FormState>();

    final cubit = context.read<StaffCubit>();
    final roles = cubit.state is StaffLoaded
        ? (cubit.state as StaffLoaded).roles
        : <Map<String, dynamic>>[];

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Theme.of(context).cardColor,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (ctx) {
        return StatefulBuilder(
          builder: (ctx, setSheetState) {
            return Padding(
              padding: EdgeInsets.fromLTRB(
                  20, 20, 20, MediaQuery.of(ctx).viewInsets.bottom + 20),
              child: Form(
                key: formKey,
                child: SingleChildScrollView(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Center(
                        child: Container(
                          width: 40,
                          height: 4,
                          decoration: BoxDecoration(
                            color: AppColors.iconDisabled,
                            borderRadius: BorderRadius.circular(2),
                          ),
                        ),
                      ),
                      const SizedBox(height: 20),
                      Text(
                        isEditing ? 'Personel Düzenle' : 'Yeni Personel',
                        style: TextStyle(
                          fontSize: 20,
                          fontWeight: FontWeight.w600,
                          color: context.brandTextPrimary,
                        ),
                      ),
                      const SizedBox(height: 20),
                      TextFormField(
                        controller: nameCtrl,
                        decoration: const InputDecoration(
                          labelText: 'Ad Soyad',
                          prefixIcon: Icon(Icons.person_outline),
                        ),
                        validator: (v) =>
                            (v == null || v.trim().isEmpty) ? 'Zorunlu' : null,
                      ),
                      const SizedBox(height: 14),
                      TextFormField(
                        controller: emailCtrl,
                        decoration: const InputDecoration(
                          labelText: 'E-posta',
                          prefixIcon: Icon(Icons.email_outlined),
                        ),
                        keyboardType: TextInputType.emailAddress,
                      ),
                      const SizedBox(height: 14),
                      TextFormField(
                        controller: phoneCtrl,
                        decoration: const InputDecoration(
                          labelText: 'Telefon',
                          prefixIcon: Icon(Icons.phone_outlined),
                        ),
                        keyboardType: TextInputType.phone,
                      ),
                      const SizedBox(height: 14),
                      DropdownButtonFormField<String>(
                        initialValue: selectedRoleId,
                        decoration: const InputDecoration(
                          labelText: 'Rol',
                          prefixIcon: Icon(Icons.badge_outlined),
                        ),
                        items: roles.map((role) {
                          return DropdownMenuItem<String>(
                            value: role['roleId']?.toString() ??
                                role['id']?.toString(),
                            child: Text(
                              role['name']?.toString() ??
                                  role['roleName']?.toString() ??
                                  '-',
                            ),
                          );
                        }).toList(),
                        onChanged: (v) =>
                            setSheetState(() => selectedRoleId = v),
                        validator: (v) =>
                            (v == null || v.isEmpty) ? 'Rol seçiniz' : null,
                      ),
                      const SizedBox(height: 14),
                      TextFormField(
                        controller: pinCtrl,
                        decoration: const InputDecoration(
                          labelText: 'PIN',
                          prefixIcon: Icon(Icons.pin_outlined),
                        ),
                        keyboardType: TextInputType.number,
                        obscureText: true,
                        maxLength: 6,
                      ),
                      const SizedBox(height: 24),
                      SizedBox(
                        width: double.infinity,
                        child: ElevatedButton(
                          onPressed: () {
                            if (!formKey.currentState!.validate()) return;
                            final data = <String, dynamic>{
                              'name': nameCtrl.text.trim(),
                              if (emailCtrl.text.trim().isNotEmpty)
                                'email': emailCtrl.text.trim(),
                              if (phoneCtrl.text.trim().isNotEmpty)
                                'phone': phoneCtrl.text.trim(),
                              if (selectedRoleId != null)
                                'role_id': selectedRoleId,
                              if (selectedRoleId != null)
                                'roleId': selectedRoleId,
                              if (pinCtrl.text.trim().isNotEmpty)
                                'pin': pinCtrl.text.trim(),
                            };

                            if (isEditing) {
                              data['user_id'] = existing.userId;
                              data['userId'] = existing.userId;
                              cubit.updateStaff(data);
                            } else {
                              cubit.createStaff(data);
                            }
                            Navigator.of(ctx).pop();
                          },
                          child: Text(isEditing ? 'Güncelle' : 'Ekle'),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            );
          },
        );
      },
    );
  }

  void _confirmDelete(Staff staff) {
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Personel Sil'),
        content: Text(
            '${staff.name ?? 'Bu personel'} silinecek. Emin misiniz?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(ctx).pop(),
            child: const Text('İptal'),
          ),
          TextButton(
            onPressed: () {
              Navigator.of(ctx).pop();
              if (staff.userId != null) {
                context.read<StaffCubit>().deleteStaff(staff.userId!);
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
        title: const Text('Personel Yönetimi'),
        backgroundColor: Theme.of(context).scaffoldBackgroundColor,
        surfaceTintColor: Colors.transparent,
      ),
      floatingActionButton: FloatingActionButton(
        onPressed: _showAddStaffSheet,
        child: const Icon(Icons.add),
      ),
      body: Column(
        children: [
          _buildSearch(),
          _buildRoleFilters(),
          Expanded(
            child: BlocConsumer<StaffCubit, StaffState>(
              listener: (context, state) {
                if (state is StaffError) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(
                      content: Text(state.message),
                      backgroundColor: AppColors.error,
                    ),
                  );
                }
              },
              builder: (context, state) {
                if (state is StaffLoading) return _buildShimmer();

                if (state is StaffError && state is! StaffLoaded) {
                  return AppErrorWidget(
                    message: state.message,
                    onRetry: _onRefresh,
                  );
                }

                if (state is StaffLoaded) {
                  return _buildStaffList(state);
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
        onChanged: (v) => context.read<StaffCubit>().search(v),
        decoration: InputDecoration(
          hintText: 'Personel ara...',
          prefixIcon: const Icon(Icons.search, color: AppColors.textHint),
          suffixIcon: _searchController.text.isNotEmpty
              ? IconButton(
                  icon: const Icon(Icons.close, size: 20),
                  onPressed: () {
                    _searchController.clear();
                    context.read<StaffCubit>().search('');
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

  Widget _buildRoleFilters() {
    return BlocBuilder<StaffCubit, StaffState>(
      buildWhen: (prev, curr) =>
          curr is StaffLoaded || curr is StaffInitial,
      builder: (context, state) {
        if (state is! StaffLoaded || state.roles.isEmpty) {
          return const SizedBox.shrink();
        }

        return SingleChildScrollView(
          scrollDirection: Axis.horizontal,
          padding: const EdgeInsets.fromLTRB(20, 0, 20, 8),
          child: Row(
            children: [
              Padding(
                padding: const EdgeInsets.only(right: 8),
                child: ChoiceChip(
                  label: const Text('Tümü'),
                  selected: state.filterRole == null,
                  onSelected: (_) =>
                      context.read<StaffCubit>().filterByRole(null),
                  selectedColor: AppColors.primary.withValues(alpha: 0.1),
                  backgroundColor: AppColors.surface,
                  side: BorderSide(
                    color: state.filterRole == null
                        ? AppColors.primary
                        : AppColors.border,
                  ),
                  labelStyle: TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w500,
                    color: state.filterRole == null
                        ? AppColors.primary
                        : AppColors.textSecondary,
                  ),
                  shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(8)),
                  showCheckmark: false,
                  materialTapTargetSize: MaterialTapTargetSize.padded,
                  padding: const EdgeInsets.symmetric(
                      horizontal: 10, vertical: 8),
                ),
              ),
              ...state.roles.map((role) {
                final roleName = role['name']?.toString() ??
                    role['roleName']?.toString() ??
                    '';
                final isSelected = state.filterRole?.toUpperCase() ==
                    roleName.toUpperCase();
                return Padding(
                  padding: const EdgeInsets.only(right: 8),
                  child: ChoiceChip(
                    label: Text(roleName),
                    selected: isSelected,
                    onSelected: (_) =>
                        context.read<StaffCubit>().filterByRole(roleName),
                    selectedColor:
                        AppColors.primary.withValues(alpha: 0.1),
                    backgroundColor: AppColors.surface,
                    side: BorderSide(
                      color: isSelected
                          ? AppColors.primary
                          : AppColors.border,
                    ),
                    labelStyle: TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w500,
                      color: isSelected
                          ? AppColors.primary
                          : AppColors.textSecondary,
                    ),
                    shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(8)),
                    showCheckmark: false,
                    materialTapTargetSize: MaterialTapTargetSize.padded,
                    padding: const EdgeInsets.symmetric(
                        horizontal: 10, vertical: 8),
                  ),
                );
              }),
            ],
          ),
        );
      },
    );
  }

  Widget _buildStaffList(StaffLoaded state) {
    final staff = state.filteredStaff;

    if (staff.isEmpty) {
      return RefreshIndicator(
        onRefresh: _onRefresh,
        color: AppColors.primary,
        child: ListView(
          children: [
            SizedBox(
              height: MediaQuery.of(context).size.height * 0.4,
              child: Center(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(Icons.people_outline,
                        size: 48, color: AppColors.border),
                    const SizedBox(height: 12),
                    Text(
                      state.searchQuery.isNotEmpty
                          ? 'Sonuç bulunamadı'
                          : 'Henüz personel yok',
                      style: TextStyle(
                          fontSize: 14, color: AppColors.textSecondary),
                    ),
                  ],
                ),
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
        itemCount: staff.length,
        itemBuilder: (context, index) {
          return _buildStaffCard(staff[index]);
        },
      ),
    );
  }

  Widget _buildStaffCard(Staff staff) {
    return Dismissible(
      key: ValueKey(staff.userId ?? staff.hashCode),
      direction: DismissDirection.endToStart,
      confirmDismiss: (_) async {
        _confirmDelete(staff);
        return false;
      },
      background: Container(
        alignment: Alignment.centerRight,
        padding: const EdgeInsets.only(right: 20),
        margin: const EdgeInsets.only(bottom: 8),
        decoration: BoxDecoration(
          color: AppColors.error.withValues(alpha: 0.1),
          borderRadius: BorderRadius.circular(12),
        ),
        child: const Icon(Icons.delete_outline, color: AppColors.error),
      ),
      child: GestureDetector(
        onTap: () => _showEditStaffSheet(staff),
        child: Container(
          margin: const EdgeInsets.only(bottom: 8),
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
color: Theme.of(context).cardColor,
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: AppColors.border),
          ),
          child: Row(
            children: [
              Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(
                  color: AppColors.primary.withValues(alpha: 0.08),
                  shape: BoxShape.circle,
                ),
                alignment: Alignment.center,
                child: Text(
                  (staff.name ?? '?').substring(0, 1).toUpperCase(),
                  style: const TextStyle(
                    fontSize: 18,
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
                    Row(
                      children: [
                        Flexible(
                          child: Text(
                            staff.name ?? '-',
                            style: TextStyle(
                              fontSize: 15,
                              fontWeight: FontWeight.w500,
                              color: context.brandTextPrimary,
                            ),
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                        if (staff.isActive == false) ...[
                          const SizedBox(width: 8),
                          Container(
                            padding: const EdgeInsets.symmetric(
                                horizontal: 6, vertical: 2),
                            decoration: BoxDecoration(
                              color: AppColors.error.withValues(alpha: 0.1),
                              borderRadius: BorderRadius.circular(4),
                            ),
                            child: const Text(
                              'Pasif',
                              style: TextStyle(
                                  fontSize: 10,
                                  color: AppColors.error,
                                  fontWeight: FontWeight.w500),
                            ),
                          ),
                        ],
                      ],
                    ),
                    const SizedBox(height: 4),
                    Row(
                      children: [
                        if (staff.roleName != null ||
                            staff.role != null) ...[
                          Container(
                            padding: const EdgeInsets.symmetric(
                                horizontal: 6, vertical: 2),
                            decoration: BoxDecoration(
                              color:
                                  AppColors.primary.withValues(alpha: 0.08),
                              borderRadius: BorderRadius.circular(4),
                            ),
                            child: Text(
                              staff.roleName ?? staff.role ?? '',
                              style: const TextStyle(
                                fontSize: 11,
                                fontWeight: FontWeight.w500,
                                color: AppColors.primary,
                              ),
                            ),
                          ),
                          const SizedBox(width: 8),
                        ],
                        if (staff.phone != null &&
                            staff.phone!.isNotEmpty)
                          Flexible(
                            child: Text(
                              staff.phone!,
                              style: const TextStyle(
                                fontSize: 12,
                                color: AppColors.textHint,
                              ),
                              overflow: TextOverflow.ellipsis,
                            ),
                          ),
                      ],
                    ),
                  ],
                ),
              ),
              const Icon(Icons.chevron_right,
                  size: 20, color: AppColors.iconDisabled),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildShimmer() {
    return Shimmer.fromColors(
      baseColor: AppColors.border,
      highlightColor: AppColors.surfaceMuted,
      child: ListView.builder(
        padding: const EdgeInsets.fromLTRB(20, 0, 20, 32),
        itemCount: 6,
        itemBuilder: (_, __) => Padding(
          padding: const EdgeInsets.only(bottom: 8),
          child: Container(
            height: 76,
            decoration: BoxDecoration(
color: Theme.of(context).cardColor,
              borderRadius: BorderRadius.circular(12),
            ),
          ),
        ),
      ),
    );
  }
}
