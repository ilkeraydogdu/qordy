import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:qordy_app/config/theme.dart';
import 'package:qordy_app/core/push/push_service.dart';
import 'package:qordy_app/core/ui/primitives.dart';
import 'package:qordy_app/core/widgets/app_error_widget.dart';
import 'package:qordy_app/features/notifications/cubit/notifications_cubit.dart';
import 'package:qordy_app/features/notifications/cubit/notifications_state.dart';
import 'package:qordy_app/models/notification_model.dart';

class NotificationsScreen extends StatefulWidget {
  const NotificationsScreen({super.key});

  @override
  State<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationsScreenState extends State<NotificationsScreen> {
  final TextEditingController _searchController = TextEditingController();
  String _searchQuery = '';
  String _filterType = 'all';

  @override
  void initState() {
    super.initState();
    final cubit = context.read<NotificationsCubit>();
    cubit.loadNotifications();
    cubit.startPolling();
    PushService.instance.clearAll();
  }

  @override
  void dispose() {
    _searchController.dispose();
    context.read<NotificationsCubit>().stopPolling();
    super.dispose();
  }

  Future<void> _onRefresh() async {
    await context.read<NotificationsCubit>().loadNotifications();
  }

  bool _matches(AppNotification n) {
    if (_filterType == 'unread' && n.isRead == true) return false;
    if (_filterType == 'calls' &&
        !(n.type == 'CALL_WAITER' || n.type == 'REQUEST_BILL')) {
      return false;
    }
    if (_filterType == 'orders' &&
        !(n.type == 'NEW_ORDER' ||
            n.type == 'ORDER_READY' ||
            n.type == 'ORDER_SERVED')) {
      return false;
    }
    if (_filterType == 'alerts' &&
        !(n.type == 'KITCHEN_ISSUE' ||
            n.type == 'CANCEL_ORDER' ||
            n.type == 'EDIT_APPROVAL' ||
            n.type == 'ORDER_EDIT_APPROVAL')) {
      return false;
    }
    if (_filterType == 'payment' &&
        !(n.type == 'PAYMENT_RECEIVED')) {
      return false;
    }
    if (_searchQuery.isEmpty) return true;
    final q = _searchQuery.toLowerCase();
    return (n.title ?? '').toLowerCase().contains(q) ||
        (n.message ?? '').toLowerCase().contains(q) ||
        (n.tableName ?? '').toLowerCase().contains(q);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        title: Text(
          'Bildirimler',
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
        centerTitle: false,
        actions: [
          IconButton(
            tooltip: 'Bildirim Ayarları',
            icon: Icon(Icons.tune_rounded, color: context.brandTextPrimary),
            onPressed: () => context.push('/notification-settings'),
          ),
          BlocBuilder<NotificationsCubit, NotificationsState>(
            builder: (context, state) {
              if (state is NotificationsLoaded && state.unreadCount > 0) {
                return TextButton(
                  onPressed: () {
                    HapticFeedback.lightImpact();
                    context.read<NotificationsCubit>().markAllRead();
                    PushService.instance.clearAll();
                  },
                  child: const Text(
                    'Tümünü Oku',
                    style: TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w600,
                      color: AppColors.primary,
                    ),
                  ),
                );
              }
              return const SizedBox.shrink();
            },
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: _onRefresh,
        color: AppColors.primary,
        child: BlocBuilder<NotificationsCubit, NotificationsState>(
          builder: (context, state) {
            if (state is NotificationsLoading) {
              return ListView.separated(
                padding: const EdgeInsets.symmetric(
                    horizontal: AppSpacing.lg, vertical: AppSpacing.md),
                itemCount: 7,
                separatorBuilder: (_, __) => const SizedBox(height: 10),
                itemBuilder: (_, __) =>
                    const QSkeleton(height: 82, radius: AppRadius.lg),
              );
            }

            if (state is NotificationsError) {
              return AppErrorWidget(
                message: state.message,
                onRetry: _onRefresh,
              );
            }

            if (state is NotificationsLoaded) {
              final filtered = state.notifications.where(_matches).toList();
              return _buildNotificationsList(state, filtered);
            }

            return const SizedBox.shrink();
          },
        ),
      ),
    );
  }

  Widget _buildSearchBar() {
    return Padding(
      padding: const EdgeInsets.fromLTRB(
          AppSpacing.lg, AppSpacing.sm, AppSpacing.lg, AppSpacing.sm),
      child: Container(
        decoration: BoxDecoration(
          color: context.brandSurface,
          borderRadius: BorderRadius.circular(AppRadius.md),
          border: Border.all(color: context.brandBorder, width: 0.6),
        ),
        child: TextField(
          controller: _searchController,
          onChanged: (v) => setState(() => _searchQuery = v.trim()),
          style: TextStyle(fontSize: 14, color: context.brandTextPrimary),
          decoration: InputDecoration(
            hintText: 'Masa adı, başlık veya mesaj ara…',
            hintStyle:
                TextStyle(color: context.brandTextHint, fontSize: 13.5),
            prefixIcon: Icon(Icons.search_rounded,
                color: context.brandTextHint, size: 20),
            suffixIcon: _searchQuery.isNotEmpty
                ? IconButton(
                    icon: Icon(Icons.close_rounded,
                        color: context.brandTextHint, size: 18),
                    onPressed: () {
                      _searchController.clear();
                      setState(() => _searchQuery = '');
                    },
                  )
                : null,
            border: InputBorder.none,
            contentPadding:
                const EdgeInsets.symmetric(horizontal: 8, vertical: 14),
          ),
        ),
      ),
    );
  }

  Widget _buildFilterChips() {
    const options = [
      ('all', 'Tümü'),
      ('unread', 'Okunmamış'),
      ('calls', 'Çağrılar'),
      ('orders', 'Siparişler'),
      ('alerts', 'Uyarılar'),
      ('payment', 'Ödeme'),
    ];
    return SizedBox(
      height: 36,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: AppSpacing.lg),
        itemCount: options.length,
        separatorBuilder: (_, __) => const SizedBox(width: 8),
        itemBuilder: (context, i) {
          final opt = options[i];
          final selected = _filterType == opt.$1;
          return ChoiceChip(
            label: Text(
              opt.$2,
              style: TextStyle(
                fontSize: 12.5,
                fontWeight: FontWeight.w600,
                color: selected ? Colors.white : context.brandTextSecondary,
              ),
            ),
            selected: selected,
            selectedColor: AppColors.primary,
            backgroundColor: context.brandSurface,
            showCheckmark: false,
            side: BorderSide(
              color: selected
                  ? AppColors.primary
                  : context.brandBorder,
              width: 0.6,
            ),
            onSelected: (v) {
              HapticFeedback.selectionClick();
              setState(() => _filterType = opt.$1);
            },
          );
        },
      ),
    );
  }

  Widget _buildNotificationsList(
      NotificationsLoaded state, List<AppNotification> filtered) {
    final hasAny = state.notifications.isNotEmpty;
    if (!hasAny) {
      return ListView(
        children: [
          SizedBox(
            height: MediaQuery.of(context).size.height * 0.6,
            child: const QEmptyState(
              icon: Icons.notifications_none_rounded,
              title: 'Bildirim yok',
              message:
                  'Henüz bildiriminiz bulunmuyor. Yeni sipariş ya da olay geldiğinde burada listelenir.',
            ),
          ),
        ],
      );
    }

    DateTime? parseIso(String? iso) {
      if (iso == null) return null;
      try {
        return DateTime.parse(iso);
      } catch (_) {
        return null;
      }
    }

    final now = DateTime.now();
    final today = filtered.where((n) {
      final dt = parseIso(n.createdAt);
      return dt != null &&
          dt.year == now.year &&
          dt.month == now.month &&
          dt.day == now.day;
    }).toList();
    final yest = now.subtract(const Duration(days: 1));
    final yesterday = filtered.where((n) {
      final dt = parseIso(n.createdAt);
      return dt != null &&
          dt.year == yest.year &&
          dt.month == yest.month &&
          dt.day == yest.day;
    }).toList();
    final older = filtered
        .where((n) => !today.contains(n) && !yesterday.contains(n))
        .toList();

    return ListView(
      padding: const EdgeInsets.only(top: 8, bottom: 24),
      children: [
        _buildSearchBar(),
        _buildFilterChips(),
        if (filtered.isEmpty)
          SizedBox(
            height: MediaQuery.of(context).size.height * 0.45,
            child: QEmptyState(
              icon: Icons.search_off_rounded,
              title: _searchQuery.isNotEmpty
                  ? 'Sonuç bulunamadı'
                  : 'Bu filtreye uyan bildirim yok',
              message: _searchQuery.isNotEmpty
                  ? '"$_searchQuery" için eşleşen bir bildirim yok.'
                  : 'Farklı bir filtre seçerek tekrar dene.',
            ),
          )
        else ...[
          if (today.isNotEmpty) ...[
            _buildGroupHeader('Bugün'),
            ...today.map(_buildNotificationCard),
          ],
          if (yesterday.isNotEmpty) ...[
            _buildGroupHeader('Dün'),
            ...yesterday.map(_buildNotificationCard),
          ],
          if (older.isNotEmpty) ...[
            _buildGroupHeader('Daha Önce'),
            ...older.map(_buildNotificationCard),
          ],
        ],
      ],
    );
  }

  Widget _buildGroupHeader(String title) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(
          AppSpacing.lg, AppSpacing.lg, AppSpacing.lg, AppSpacing.sm),
      child: Text(
        title.toUpperCase(),
        style: TextStyle(
          fontSize: 11,
          fontWeight: FontWeight.w700,
          color: context.brandTextHint,
          letterSpacing: 1.2,
        ),
      ),
    );
  }

  Widget _buildNotificationCard(AppNotification notification) {
    final isUnread = notification.isRead != true;
    final accent = _iconColorForType(notification.type);
    final dark = context.isDark;

    return Padding(
      padding: const EdgeInsets.symmetric(
          horizontal: AppSpacing.lg, vertical: 5),
      child: Material(
        color: Colors.transparent,
        borderRadius: BorderRadius.circular(AppRadius.lg),
        child: InkWell(
          onTap: () {
            if (isUnread && notification.notificationId != null) {
              HapticFeedback.selectionClick();
              context
                  .read<NotificationsCubit>()
                  .markRead(notification.notificationId!);
            }
          },
          borderRadius: BorderRadius.circular(AppRadius.lg),
          splashColor: accent.withValues(alpha: 0.10),
          highlightColor: accent.withValues(alpha: 0.05),
          child: Ink(
            decoration: BoxDecoration(
              // Okunmamış kartlar markanın soft wash'ını alarak
              // listede anında gözükür; okunmuşlar sakin kart.
              color: isUnread
                  ? null
                  : Theme.of(context).cardColor,
              gradient: isUnread
                  ? LinearGradient(
                      begin: Alignment.topLeft,
                      end: Alignment.bottomRight,
                      colors: [
                        Theme.of(context).cardColor,
                        accent.withValues(alpha: dark ? 0.14 : 0.07),
                      ],
                    )
                  : null,
              borderRadius: BorderRadius.circular(AppRadius.lg),
              border: Border.all(
                color: isUnread
                    ? accent.withValues(alpha: dark ? 0.40 : 0.22)
                    : context.brandBorder,
                width: 0.8,
              ),
              boxShadow: isUnread
                  ? [
                      BoxShadow(
                        color: accent.withValues(alpha: dark ? 0.18 : 0.08),
                        blurRadius: 12,
                        offset: const Offset(0, 3),
                      ),
                    ]
                  : AppShadows.card(dark),
            ),
            child: ClipRRect(
              borderRadius: BorderRadius.circular(AppRadius.lg),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  // Sol kenar şeridi — type rengini ilk bakışta belli
                  // eder, görsel hiyerarşiyi güçlendirir.
                  Container(
                    width: 4,
                    color: accent,
                  ),
                  Expanded(
                    child: Padding(
                      padding: const EdgeInsets.all(AppSpacing.md),
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Container(
                            width: 42,
                            height: 42,
                            decoration: BoxDecoration(
                              gradient: LinearGradient(
                                begin: Alignment.topLeft,
                                end: Alignment.bottomRight,
                                colors: [
                                  accent.withValues(
                                      alpha: dark ? 0.35 : 0.18),
                                  accent.withValues(
                                      alpha: dark ? 0.20 : 0.08),
                                ],
                              ),
                              borderRadius:
                                  BorderRadius.circular(AppRadius.md),
                              border: Border.all(
                                color: accent.withValues(
                                    alpha: dark ? 0.40 : 0.22),
                                width: 0.6,
                              ),
                            ),
                            child: Icon(
                              _iconForType(notification.type),
                              color: accent,
                              size: 20,
                            ),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Column(
                              crossAxisAlignment:
                                  CrossAxisAlignment.start,
                              children: [
                                Row(
                                  children: [
                                    Expanded(
                                      child: Text(
                                        notification.title ?? '',
                                        style: TextStyle(
                                          fontSize: 14,
                                          fontWeight: isUnread
                                              ? FontWeight.w800
                                              : FontWeight.w600,
                                          color: context.brandTextPrimary,
                                          letterSpacing: -0.1,
                                        ),
                                      ),
                                    ),
                                    if (isUnread)
                                      Container(
                                        width: 8,
                                        height: 8,
                                        decoration: BoxDecoration(
                                          color: accent,
                                          shape: BoxShape.circle,
                                          boxShadow: [
                                            BoxShadow(
                                              color: accent.withValues(
                                                  alpha: 0.55),
                                              blurRadius: 6,
                                            ),
                                          ],
                                        ),
                                      ),
                                  ],
                                ),
                                const SizedBox(height: 4),
                                Text(
                                  notification.message ?? '',
                                  style: TextStyle(
                                    fontSize: 13,
                                    color: context.brandTextSecondary,
                                    height: 1.4,
                                  ),
                                  maxLines: 2,
                                  overflow: TextOverflow.ellipsis,
                                ),
                                const SizedBox(height: 6),
                                Row(
                                  children: [
                                    Icon(
                                      Icons.schedule_rounded,
                                      size: 11,
                                      color: context.brandTextHint,
                                    ),
                                    const SizedBox(width: 3),
                                    Text(
                                      _formatTime(notification.createdAt),
                                      style: TextStyle(
                                        fontSize: 11,
                                        color: context.brandTextHint,
                                        fontWeight: FontWeight.w500,
                                      ),
                                    ),
                                    if ((notification.tableName ?? '')
                                        .isNotEmpty) ...[
                                      const SizedBox(width: 10),
                                      Icon(
                                        Icons.table_restaurant_rounded,
                                        size: 11,
                                        color: context.brandTextHint,
                                      ),
                                      const SizedBox(width: 3),
                                      Text(
                                        notification.tableName!,
                                        style: TextStyle(
                                          fontSize: 11,
                                          color: context.brandTextHint,
                                          fontWeight: FontWeight.w500,
                                        ),
                                      ),
                                    ],
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
        ),
      ),
    );
  }

  /// Backend notification tipleri büyük harfli enum string'leri olarak
  /// gelir (`CALL_WAITER`, `NEW_ORDER`…). Eski küçük harfli kategoriler de
  /// sistemde hala dolaşıyor — her ikisini de karşılayacak şekilde
  /// normalize edip eşleştiriyoruz.
  IconData _iconForType(String? type) {
    final t = (type ?? '').toUpperCase();
    switch (t) {
      case 'NEW_ORDER':
      case 'ORDER':
        return Icons.receipt_long_rounded;
      case 'ORDER_READY':
        return Icons.check_circle_rounded;
      case 'ORDER_SERVED':
        return Icons.room_service_rounded;
      case 'CALL_WAITER':
        return Icons.back_hand_rounded;
      case 'REQUEST_BILL':
        return Icons.request_quote_rounded;
      case 'KITCHEN_ISSUE':
      case 'KITCHEN':
        return Icons.soup_kitchen_rounded;
      case 'CANCEL_ORDER':
        return Icons.cancel_rounded;
      case 'EDIT_APPROVAL':
      case 'ORDER_EDIT_APPROVAL':
        return Icons.edit_note_rounded;
      case 'PAYMENT_RECEIVED':
      case 'PAYMENT':
        return Icons.payments_rounded;
      case 'TABLE':
        return Icons.table_restaurant_rounded;
      case 'STAFF':
        return Icons.people_alt_rounded;
      case 'SYSTEM':
        return Icons.settings_rounded;
      case 'ALERT':
        return Icons.warning_amber_rounded;
      default:
        return Icons.notifications_active_rounded;
    }
  }

  Color _iconColorForType(String? type) {
    final t = (type ?? '').toUpperCase();
    switch (t) {
      case 'NEW_ORDER':
      case 'ORDER':
        return AppColors.primary;
      case 'ORDER_READY':
      case 'ORDER_SERVED':
        return AppColors.successAlt;
      case 'CALL_WAITER':
      case 'REQUEST_BILL':
        return AppColors.accentOrange;
      case 'KITCHEN_ISSUE':
      case 'KITCHEN':
        return AppColors.warningBright;
      case 'CANCEL_ORDER':
        return AppColors.errorBright;
      case 'EDIT_APPROVAL':
      case 'ORDER_EDIT_APPROVAL':
        return AppColors.accentPurple;
      case 'PAYMENT_RECEIVED':
      case 'PAYMENT':
        return AppColors.successAlt;
      case 'TABLE':
        return AppColors.accentPurple;
      case 'STAFF':
        return AppColors.accentIndigo;
      case 'ALERT':
        return AppColors.errorBright;
      case 'SYSTEM':
        return AppColors.textSecondary;
      default:
        return AppColors.primary;
    }
  }

  String _formatTime(String? isoDate) {
    if (isoDate == null) return '';
    try {
      final dt = DateTime.parse(isoDate);
      final now = DateTime.now();
      final diff = now.difference(dt);

      if (diff.inMinutes < 1) return 'Az önce';
      if (diff.inMinutes < 60) return '${diff.inMinutes} dk önce';
      if (diff.inHours < 24) return '${diff.inHours} saat önce';
      if (diff.inDays < 7) return '${diff.inDays} gün önce';
      return '${dt.day.toString().padLeft(2, '0')}.${dt.month.toString().padLeft(2, '0')}.${dt.year}';
    } catch (_) {
      return isoDate;
    }
  }
}
