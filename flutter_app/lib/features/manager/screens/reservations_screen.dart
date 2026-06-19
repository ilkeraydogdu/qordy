import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:intl/intl.dart';
import 'package:qordy_app/models/reservation.dart';

import '../cubit/reservations_cubit.dart';
import '../cubit/reservations_state.dart';
import '../../../config/theme.dart';
import '../../../core/ui/primitives.dart';

/// Cubit provided by the router.
class ReservationsScreen extends StatefulWidget {
  const ReservationsScreen({super.key});

  @override
  State<ReservationsScreen> createState() => _ReservationsScreenState();
}

class _ReservationsScreenState extends State<ReservationsScreen> {
  String _searchQuery = '';
  final TextEditingController _searchCtrl = TextEditingController();

  @override
  void dispose() {
    _searchCtrl.dispose();
    super.dispose();
  }

  List<Reservation> _applySearch(List<Reservation> list) {
    if (_searchQuery.trim().isEmpty) return list;
    final q = _searchQuery.toLowerCase().trim();
    return list.where((r) {
      final n = (r.customerName ?? '').toLowerCase();
      final p = (r.phone ?? '').toLowerCase();
      final t = (r.tableName ?? '').toLowerCase();
      final notes = (r.notes ?? '').toLowerCase();
      return n.contains(q) || p.contains(q) || t.contains(q) || notes.contains(q);
    }).toList();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        title: Text(
          'Rezervasyonlar',
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
      ),
      floatingActionButton: _GradientFab(
        onPressed: () => _showReservationForm(context),
      ),
      body: BlocConsumer<ReservationsCubit, ReservationsState>(
        listener: (context, state) {
          if (state is ReservationActionSuccess) {
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(
                content: Text(state.message),
                backgroundColor: Colors.green,
                behavior: SnackBarBehavior.floating,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
              ),
            );
          } else if (state is ReservationsError) {
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(
                content: Text(state.message),
                backgroundColor: Colors.red,
                behavior: SnackBarBehavior.floating,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
              ),
            );
          }
        },
        builder: (context, state) {
          if (state is ReservationsLoading) {
            return _buildSkeleton();
          }
          if (state is! ReservationsLoaded) {
            return QEmptyState(
              icon: Icons.error_outline_rounded,
              title: 'Veri yüklenemedi',
              message: 'Rezervasyonlar alınamadı. Tekrar deneyin.',
            );
          }

          final filtered = _applySearch(state.filteredReservations);
          return Column(
            children: [
              _DateSelector(selectedDate: state.selectedDate),
              Padding(
                padding: const EdgeInsets.fromLTRB(
                    AppSpacing.lg, 4, AppSpacing.lg, 4),
                child: _SearchField(
                  controller: _searchCtrl,
                  onChanged: (v) => setState(() => _searchQuery = v),
                  hasText: _searchQuery.isNotEmpty,
                  onClear: () {
                    _searchCtrl.clear();
                    setState(() => _searchQuery = '');
                  },
                ),
              ),
              _StatusTabs(selected: state.statusFilter),
              Expanded(
                child: filtered.isEmpty
                    ? QEmptyState(
                        icon: Icons.event_busy_rounded,
                        title: 'Rezervasyon bulunamadı',
                        message:
                            'Seçili tarih veya filtrede rezervasyon yok.',
                      )
                    : ListView.separated(
                        padding: const EdgeInsets.fromLTRB(
                            AppSpacing.lg,
                            AppSpacing.sm,
                            AppSpacing.lg,
                            100),
                        itemCount: filtered.length,
                        separatorBuilder: (_, __) =>
                            const SizedBox(height: AppSpacing.md),
                        itemBuilder: (context, index) {
                          return _ReservationCard(
                            reservation: filtered[index],
                          );
                        },
                      ),
              ),
            ],
          );
        },
      ),
    );
  }

  Widget _buildSkeleton() {
    return ListView(
      padding: const EdgeInsets.fromLTRB(
          AppSpacing.lg, AppSpacing.md, AppSpacing.lg, AppSpacing.xl),
      children: [
        const QSkeleton(height: 54, radius: AppRadius.md),
        const SizedBox(height: AppSpacing.sm),
        const QSkeleton(height: 44, radius: AppRadius.md),
        const SizedBox(height: AppSpacing.sm),
        const QSkeleton(height: 40, radius: 999),
        const SizedBox(height: AppSpacing.md),
        for (var i = 0; i < 5; i++) ...[
          const QSkeleton(height: 140, radius: AppRadius.lg),
          const SizedBox(height: AppSpacing.md),
        ],
      ],
    );
  }

  void _showReservationForm(BuildContext context) {
    final nameController = TextEditingController();
    final phoneController = TextEditingController();
    final guestController = TextEditingController();
    final tableController = TextEditingController();
    final notesController = TextEditingController();
    DateTime selectedDate = DateTime.now();
    TimeOfDay selectedTime = TimeOfDay.now();
    final formKey = GlobalKey<FormState>();

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Theme.of(context).cardColor,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setModalState) => Padding(
          padding: EdgeInsets.fromLTRB(
            24,
            24,
            24,
            MediaQuery.of(ctx).viewInsets.bottom + 24,
          ),
          child: Form(
            key: formKey,
            child: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Center(
                    child: Container(
                      width: 44,
                      height: 4,
                      decoration: BoxDecoration(
                        color: ctx.brandBorder,
                        borderRadius: BorderRadius.circular(2),
                      ),
                    ),
                  ),
                  const SizedBox(height: AppSpacing.lg),
                  Row(
                    children: [
                      Container(
                        width: 38,
                        height: 38,
                        decoration: BoxDecoration(
                          gradient: LinearGradient(
                            begin: Alignment.topLeft,
                            end: Alignment.bottomRight,
                            colors: [
                              AppColors.primary.withValues(
                                  alpha: ctx.isDark ? 0.42 : 0.20),
                              AppColors.primary.withValues(
                                  alpha: ctx.isDark ? 0.25 : 0.08),
                            ],
                          ),
                          borderRadius: BorderRadius.circular(AppRadius.md),
                          border: Border.all(
                            color: AppColors.primary
                                .withValues(alpha: 0.28),
                            width: 0.6,
                          ),
                        ),
                        alignment: Alignment.center,
                        child: const Icon(
                          Icons.event_available_rounded,
                          color: AppColors.primary,
                          size: 20,
                        ),
                      ),
                      const SizedBox(width: 12),
                      Text(
                        'Yeni Rezervasyon',
                        style: TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.w800,
                          color: ctx.brandTextPrimary,
                          letterSpacing: -0.2,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: AppSpacing.lg),
                  TextFormField(
                    controller: nameController,
                    decoration: _inputDecoration('Müşteri Adı', ctx),
                    validator: (v) =>
                        v == null || v.isEmpty ? 'Ad gerekli' : null,
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: phoneController,
                    decoration: _inputDecoration('Telefon', ctx),
                    keyboardType: TextInputType.phone,
                    validator: (v) =>
                        v == null || v.isEmpty ? 'Telefon gerekli' : null,
                  ),
                  const SizedBox(height: 12),
                  Row(
                    children: [
                      Expanded(
                        child: InkWell(
                          onTap: () async {
                            final picked = await showDatePicker(
                              context: ctx,
                              initialDate: selectedDate,
                              firstDate: DateTime.now(),
                              lastDate: DateTime.now().add(const Duration(days: 365)),
                              builder: (c, child) => Theme(
                                data: Theme.of(c).copyWith(
                                  colorScheme: const ColorScheme.light(
                                    primary: AppColors.primary,
                                  ),
                                ),
                                child: child!,
                              ),
                            );
                            if (picked != null) {
                              setModalState(() => selectedDate = picked);
                            }
                          },
                          child: InputDecorator(
                            decoration: _inputDecoration('Tarih', ctx).copyWith(
                              suffixIcon: const Icon(Icons.calendar_today, size: 18),
                            ),
                            child: Text(
                              DateFormat('dd.MM.yyyy').format(selectedDate),
                            ),
                          ),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: InkWell(
                          onTap: () async {
                            final picked = await showTimePicker(
                              context: ctx,
                              initialTime: selectedTime,
                              builder: (c, child) => Theme(
                                data: Theme.of(c).copyWith(
                                  colorScheme: const ColorScheme.light(
                                    primary: AppColors.primary,
                                  ),
                                ),
                                child: child!,
                              ),
                            );
                            if (picked != null) {
                              setModalState(() => selectedTime = picked);
                            }
                          },
                          child: InputDecorator(
                            decoration: _inputDecoration('Saat', ctx).copyWith(
                              suffixIcon: const Icon(Icons.access_time, size: 18),
                            ),
                            child: Text(
                              '${selectedTime.hour.toString().padLeft(2, '0')}:${selectedTime.minute.toString().padLeft(2, '0')}',
                            ),
                          ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  Row(
                    children: [
                      Expanded(
                        child: TextFormField(
                          controller: guestController,
                          decoration: _inputDecoration('Kişi Sayısı', ctx),
                          keyboardType: TextInputType.number,
                          validator: (v) {
                            if (v == null || v.isEmpty) return 'Gerekli';
                            if (int.tryParse(v) == null) return 'Geçersiz';
                            return null;
                          },
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: TextFormField(
                          controller: tableController,
                          decoration: _inputDecoration('Masa', ctx),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: notesController,
                    decoration: _inputDecoration('Not', ctx),
                    maxLines: 2,
                  ),
                  const SizedBox(height: AppSpacing.lg),
                  SizedBox(
                    width: double.infinity,
                    height: 52,
                    child: FilledButton.icon(
                      onPressed: () {
                        if (!formKey.currentState!.validate()) return;
                        HapticFeedback.lightImpact();
                        Navigator.pop(ctx);
                        final timeStr =
                            '${selectedTime.hour.toString().padLeft(2, '0')}:${selectedTime.minute.toString().padLeft(2, '0')}';
                        context.read<ReservationsCubit>().createReservation(
                              customerName: nameController.text.trim(),
                              phone: phoneController.text.trim(),
                              date: DateFormat('yyyy-MM-dd')
                                  .format(selectedDate),
                              time: timeStr,
                              guestCount: int.parse(guestController.text),
                              tableId: tableController.text.trim().isEmpty
                                  ? null
                                  : tableController.text.trim(),
                              notes: notesController.text.trim().isEmpty
                                  ? null
                                  : notesController.text.trim(),
                            );
                      },
                      icon: const Icon(Icons.check_rounded, size: 20),
                      label: const Text(
                        'Rezervasyonu Oluştur',
                        style: TextStyle(
                          fontSize: 15,
                          fontWeight: FontWeight.w800,
                          letterSpacing: 0.2,
                        ),
                      ),
                      style: FilledButton.styleFrom(
                        backgroundColor: AppColors.primary,
                        foregroundColor: Colors.white,
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(AppRadius.lg),
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  InputDecoration _inputDecoration(String label, BuildContext context) {
    return InputDecoration(
      labelText: label,
      labelStyle: TextStyle(
        color: context.brandTextSecondary,
        fontSize: 13,
        fontWeight: FontWeight.w500,
      ),
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(AppRadius.md),
        borderSide: BorderSide(color: context.brandBorder, width: 0.8),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(AppRadius.md),
        borderSide: BorderSide(color: context.brandBorder, width: 0.8),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(AppRadius.md),
        borderSide: const BorderSide(color: AppColors.primary, width: 1.5),
      ),
      filled: true,
      fillColor: context.brandSurfaceMuted,
      contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
    );
  }
}

/// Gradient FAB — sade `FloatingActionButton` yerine brand glow'lu,
/// biraz daha karakterli bir "yeni rezervasyon" eylemi veriyor.
class _GradientFab extends StatelessWidget {
  final VoidCallback onPressed;
  const _GradientFab({required this.onPressed});

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        gradient: AppColors.brandGradient,
        boxShadow: [
          BoxShadow(
            color: AppColors.primary.withValues(alpha: 0.45),
            blurRadius: 18,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Material(
        color: Colors.transparent,
        shape: const CircleBorder(),
        child: InkWell(
          customBorder: const CircleBorder(),
          onTap: () {
            HapticFeedback.lightImpact();
            onPressed();
          },
          splashColor: Colors.white.withValues(alpha: 0.15),
          child: const SizedBox(
            width: 56,
            height: 56,
            child: Icon(Icons.add_rounded, color: Colors.white, size: 26),
          ),
        ),
      ),
    );
  }
}

class _SearchField extends StatelessWidget {
  final TextEditingController controller;
  final ValueChanged<String> onChanged;
  final VoidCallback onClear;
  final bool hasText;

  const _SearchField({
    required this.controller,
    required this.onChanged,
    required this.onClear,
    required this.hasText,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: context.brandSurfaceMuted,
        borderRadius: BorderRadius.circular(AppRadius.md),
        border: Border.all(color: context.brandBorder, width: 0.8),
      ),
      child: TextField(
        controller: controller,
        onChanged: onChanged,
        style: TextStyle(
          fontSize: 14,
          fontWeight: FontWeight.w500,
          color: context.brandTextPrimary,
        ),
        decoration: InputDecoration(
          hintText: 'İsim, telefon, masa veya not...',
          hintStyle: TextStyle(
            color: context.brandTextHint,
            fontSize: 13.5,
            fontWeight: FontWeight.w500,
          ),
          prefixIcon: Icon(Icons.search_rounded,
              size: 20, color: context.brandTextHint),
          suffixIcon: !hasText
              ? null
              : IconButton(
                  icon: Icon(Icons.clear_rounded,
                      size: 18, color: context.brandTextHint),
                  onPressed: onClear,
                ),
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

class _DateSelector extends StatelessWidget {
  final String selectedDate;

  const _DateSelector({required this.selectedDate});

  @override
  Widget build(BuildContext context) {
    final date = DateTime.tryParse(selectedDate) ?? DateTime.now();
    final today = DateTime.now();
    final isToday = date.year == today.year &&
        date.month == today.month &&
        date.day == today.day;
    final dark = context.isDark;

    return Padding(
      padding: const EdgeInsets.fromLTRB(
          AppSpacing.lg, AppSpacing.sm, AppSpacing.lg, AppSpacing.sm),
      child: Material(
        color: Colors.transparent,
        borderRadius: BorderRadius.circular(AppRadius.md),
        child: InkWell(
          borderRadius: BorderRadius.circular(AppRadius.md),
          splashColor: AppColors.primary.withValues(alpha: 0.08),
          onTap: () async {
            HapticFeedback.selectionClick();
            final picked = await showDatePicker(
              context: context,
              initialDate: date,
              firstDate: DateTime(2020),
              lastDate: DateTime.now().add(const Duration(days: 365)),
            );
            if (picked != null && context.mounted) {
              context.read<ReservationsCubit>().setDate(
                    DateFormat('yyyy-MM-dd').format(picked),
                  );
            }
          },
          child: Ink(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
            decoration: BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
                colors: [
                  AppColors.primary.withValues(alpha: dark ? 0.16 : 0.08),
                  AppColors.primary.withValues(alpha: dark ? 0.06 : 0.02),
                ],
              ),
              borderRadius: BorderRadius.circular(AppRadius.md),
              border: Border.all(
                color: AppColors.primary
                    .withValues(alpha: dark ? 0.35 : 0.20),
                width: 0.8,
              ),
            ),
            child: Row(
              children: [
                Container(
                  width: 36,
                  height: 36,
                  decoration: BoxDecoration(
                    gradient: AppColors.brandGradient,
                    borderRadius: BorderRadius.circular(AppRadius.sm),
                    boxShadow: [
                      BoxShadow(
                        color:
                            AppColors.primary.withValues(alpha: 0.30),
                        blurRadius: 8,
                        offset: const Offset(0, 3),
                      ),
                    ],
                  ),
                  alignment: Alignment.center,
                  child: const Icon(
                    Icons.calendar_today_rounded,
                    size: 18,
                    color: Colors.white,
                  ),
                ),
                const SizedBox(width: 12),
                Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      DateFormat('dd MMMM yyyy', 'tr').format(date),
                      style: TextStyle(
                        fontSize: 15,
                        fontWeight: FontWeight.w800,
                        color: context.brandTextPrimary,
                        letterSpacing: -0.1,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      DateFormat('EEEE', 'tr').format(date),
                      style: TextStyle(
                        fontSize: 11.5,
                        fontWeight: FontWeight.w600,
                        color: context.brandTextSecondary,
                      ),
                    ),
                  ],
                ),
                const Spacer(),
                if (isToday)
                  Container(
                    padding: const EdgeInsets.symmetric(
                        horizontal: 8, vertical: 3),
                    margin: const EdgeInsets.only(right: 6),
                    decoration: BoxDecoration(
                      color: AppColors.successAlt.withValues(alpha: 0.14),
                      borderRadius: BorderRadius.circular(999),
                      border: Border.all(
                        color: AppColors.successAlt.withValues(alpha: 0.28),
                        width: 0.6,
                      ),
                    ),
                    child: const Text(
                      'Bugün',
                      style: TextStyle(
                        fontSize: 10.5,
                        fontWeight: FontWeight.w800,
                        color: AppColors.successAlt,
                      ),
                    ),
                  ),
                Icon(Icons.expand_more_rounded,
                    color: AppColors.primary.withValues(alpha: 0.8)),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _StatusTabs extends StatelessWidget {
  final String selected;

  const _StatusTabs({required this.selected});

  // Her statü filtresi kendi semantik rengine sahip — seçildiğinde
  // chip o renge boyanıyor, aynı zamanda rozetlerle de eşleşiyor.
  static const _tabs = <({String id, String label, Color color})>[
    (id: 'all', label: 'Tümü', color: AppColors.primary),
    (id: 'pending', label: 'Bekleyen', color: AppColors.accentOrange),
    (id: 'confirmed', label: 'Onaylı', color: AppColors.successAlt),
    (id: 'cancelled', label: 'İptal', color: AppColors.errorBright),
  ];

  @override
  Widget build(BuildContext context) {
    final dark = context.isDark;
    return SizedBox(
      height: 48,
      child: ListView.separated(
        padding: const EdgeInsets.symmetric(horizontal: AppSpacing.lg),
        scrollDirection: Axis.horizontal,
        itemCount: _tabs.length,
        separatorBuilder: (_, __) => const SizedBox(width: 8),
        itemBuilder: (context, index) {
          final tab = _tabs[index];
          final isSelected = selected == tab.id;
          return Material(
            color: Colors.transparent,
            borderRadius: BorderRadius.circular(999),
            child: InkWell(
              borderRadius: BorderRadius.circular(999),
              onTap: () {
                HapticFeedback.selectionClick();
                context
                    .read<ReservationsCubit>()
                    .setStatusFilter(tab.id);
              },
              child: AnimatedContainer(
                duration: const Duration(milliseconds: 180),
                padding: const EdgeInsets.symmetric(
                    horizontal: 14, vertical: 8),
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: isSelected
                      ? tab.color.withValues(alpha: dark ? 0.22 : 0.12)
                      : context.brandSurfaceMuted,
                  borderRadius: BorderRadius.circular(999),
                  border: Border.all(
                    color: isSelected
                        ? tab.color.withValues(alpha: dark ? 0.5 : 0.32)
                        : context.brandBorder,
                    width: 0.8,
                  ),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Container(
                      width: 8,
                      height: 8,
                      decoration: BoxDecoration(
                        color: tab.color,
                        shape: BoxShape.circle,
                        boxShadow: isSelected
                            ? [
                                BoxShadow(
                                  color: tab.color.withValues(alpha: 0.5),
                                  blurRadius: 4,
                                ),
                              ]
                            : null,
                      ),
                    ),
                    const SizedBox(width: 7),
                    Text(
                      tab.label,
                      style: TextStyle(
                        fontSize: 12.5,
                        fontWeight: FontWeight.w700,
                        color: isSelected
                            ? tab.color
                            : context.brandTextSecondary,
                        letterSpacing: 0.1,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          );
        },
      ),
    );
  }
}

class _ReservationCard extends StatelessWidget {
  final Reservation reservation;

  const _ReservationCard({required this.reservation});

  Color _statusColor(String? status) {
    switch (status) {
      case 'confirmed':
        return AppColors.successAlt;
      case 'cancelled':
        return AppColors.errorBright;
      case 'pending':
        return AppColors.accentOrange;
      default:
        return AppColors.primary;
    }
  }

  String _statusLabel(String? status) {
    switch (status) {
      case 'confirmed':
        return 'Onaylı';
      case 'cancelled':
        return 'İptal';
      case 'pending':
        return 'Bekleyen';
      default:
        return status ?? 'Bilinmiyor';
    }
  }

  @override
  Widget build(BuildContext context) {
    final accent = _statusColor(reservation.status);
    final dark = context.isDark;
    return Container(
      decoration: BoxDecoration(
        color: Theme.of(context).cardColor,
        borderRadius: BorderRadius.circular(AppRadius.lg),
        border: Border.all(color: context.brandBorder, width: 0.6),
        boxShadow: AppShadows.card(dark),
      ),
      clipBehavior: Clip.antiAlias,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Container(width: 4, color: accent),
          Expanded(
            child: Padding(
              padding: const EdgeInsets.all(AppSpacing.md),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Container(
                        width: 36,
                        height: 36,
                        decoration: BoxDecoration(
                          gradient: LinearGradient(
                            begin: Alignment.topLeft,
                            end: Alignment.bottomRight,
                            colors: [
                              accent.withValues(alpha: dark ? 0.38 : 0.20),
                              accent.withValues(alpha: dark ? 0.22 : 0.08),
                            ],
                          ),
                          borderRadius:
                              BorderRadius.circular(AppRadius.sm),
                          border: Border.all(
                            color: accent.withValues(
                                alpha: dark ? 0.42 : 0.24),
                            width: 0.6,
                          ),
                        ),
                        alignment: Alignment.center,
                        child: Text(
                          _initials(reservation.customerName),
                          style: TextStyle(
                            fontSize: 13,
                            fontWeight: FontWeight.w800,
                            color: accent,
                          ),
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              reservation.customerName ?? 'Misafir',
                              style: TextStyle(
                                fontSize: 15,
                                fontWeight: FontWeight.w800,
                                color: context.brandTextPrimary,
                                letterSpacing: -0.1,
                              ),
                            ),
                            if ((reservation.phone ?? '').isNotEmpty)
                              Text(
                                reservation.phone!,
                                style: TextStyle(
                                  fontSize: 11.5,
                                  color: context.brandTextHint,
                                  fontWeight: FontWeight.w500,
                                ),
                              ),
                          ],
                        ),
                      ),
                      Container(
                        padding: const EdgeInsets.symmetric(
                            horizontal: 9, vertical: 4),
                        decoration: BoxDecoration(
                          color: accent.withValues(alpha: 0.14),
                          borderRadius: BorderRadius.circular(999),
                          border: Border.all(
                            color: accent.withValues(alpha: 0.28),
                            width: 0.6,
                          ),
                        ),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Container(
                              width: 6,
                              height: 6,
                              decoration: BoxDecoration(
                                color: accent,
                                shape: BoxShape.circle,
                                boxShadow: [
                                  BoxShadow(
                                    color:
                                        accent.withValues(alpha: 0.45),
                                    blurRadius: 4,
                                  ),
                                ],
                              ),
                            ),
                            const SizedBox(width: 6),
                            Text(
                              _statusLabel(reservation.status),
                              style: TextStyle(
                                color: accent,
                                fontSize: 11.5,
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 10),
                  Wrap(
                    spacing: 14,
                    runSpacing: 6,
                    children: [
                      _MetaChip(
                        icon: Icons.access_time_rounded,
                        label: reservation.time ?? '-',
                        color: AppColors.accentIndigo,
                      ),
                      _MetaChip(
                        icon: Icons.people_alt_rounded,
                        label: '${reservation.guestCount ?? 0} kişi',
                        color: AppColors.accentPurple,
                      ),
                      if ((reservation.tableName ?? '').isNotEmpty)
                        _MetaChip(
                          icon: Icons.table_restaurant_rounded,
                          label: reservation.tableName!,
                          color: AppColors.accentOrange,
                        ),
                    ],
                  ),
                  if (reservation.notes != null &&
                      reservation.notes!.isNotEmpty) ...[
                    const SizedBox(height: 10),
                    Container(
                      padding: const EdgeInsets.symmetric(
                          horizontal: 10, vertical: 8),
                      decoration: BoxDecoration(
                        color: context.brandSurfaceMuted,
                        borderRadius: BorderRadius.circular(AppRadius.sm),
                      ),
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Icon(Icons.sticky_note_2_rounded,
                              size: 14,
                              color: context.brandTextHint),
                          const SizedBox(width: 6),
                          Expanded(
                            child: Text(
                              reservation.notes!,
                              style: TextStyle(
                                fontSize: 12.5,
                                color: context.brandTextSecondary,
                                height: 1.4,
                                fontStyle: FontStyle.italic,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                  if (reservation.status == 'pending') ...[
                    const SizedBox(height: 12),
                    Row(
                      children: [
                        Expanded(
                          child: OutlinedButton.icon(
                            onPressed: () {
                              if (reservation.reservationId != null) {
                                HapticFeedback.selectionClick();
                                context
                                    .read<ReservationsCubit>()
                                    .cancelReservation(
                                        reservation.reservationId!);
                              }
                            },
                            icon: const Icon(Icons.close_rounded,
                                size: 16),
                            label: const Text('İptal'),
                            style: OutlinedButton.styleFrom(
                              foregroundColor: AppColors.errorBright,
                              side: BorderSide(
                                color: AppColors.errorBright
                                    .withValues(alpha: 0.4),
                                width: 0.8,
                              ),
                              padding: const EdgeInsets.symmetric(
                                  vertical: 10),
                              shape: RoundedRectangleBorder(
                                borderRadius:
                                    BorderRadius.circular(AppRadius.md),
                              ),
                              textStyle: const TextStyle(
                                fontSize: 13,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ),
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: FilledButton.icon(
                            onPressed: () {
                              if (reservation.reservationId != null) {
                                HapticFeedback.lightImpact();
                                context
                                    .read<ReservationsCubit>()
                                    .confirmReservation(
                                        reservation.reservationId!);
                              }
                            },
                            icon: const Icon(Icons.check_rounded,
                                size: 16),
                            label: const Text('Onayla'),
                            style: FilledButton.styleFrom(
                              backgroundColor: AppColors.successAlt,
                              foregroundColor: Colors.white,
                              padding: const EdgeInsets.symmetric(
                                  vertical: 10),
                              shape: RoundedRectangleBorder(
                                borderRadius:
                                    BorderRadius.circular(AppRadius.md),
                              ),
                              textStyle: const TextStyle(
                                fontSize: 13,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ),
                        ),
                      ],
                    ),
                  ],
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  String _initials(String? name) {
    if (name == null || name.trim().isEmpty) return '?';
    final parts = name.trim().split(RegExp(r'\s+'));
    if (parts.length == 1) return parts.first.substring(0, 1).toUpperCase();
    return (parts.first.substring(0, 1) + parts.last.substring(0, 1))
        .toUpperCase();
  }
}

class _MetaChip extends StatelessWidget {
  final IconData icon;
  final String label;
  final Color color;
  const _MetaChip({
    required this.icon,
    required this.label,
    required this.color,
  });

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(icon, size: 14, color: color),
        const SizedBox(width: 5),
        Text(
          label,
          style: TextStyle(
            fontSize: 12.5,
            fontWeight: FontWeight.w700,
            color: context.brandTextSecondary,
            letterSpacing: -0.1,
          ),
        ),
      ],
    );
  }
}
