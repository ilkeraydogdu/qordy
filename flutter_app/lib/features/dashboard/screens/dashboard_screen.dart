import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:qordy_app/config/theme.dart';
import 'package:qordy_app/core/navigation/role_home.dart';
import 'package:qordy_app/core/ui/primitives.dart';
import 'package:qordy_app/core/widgets/app_error_widget.dart';
import 'package:qordy_app/core/widgets/order_card.dart';
import 'package:qordy_app/features/auth/cubit/auth_cubit.dart';
import 'package:qordy_app/features/auth/cubit/auth_state.dart';
import 'package:qordy_app/features/dashboard/cubit/dashboard_cubit.dart';
import 'package:qordy_app/features/dashboard/cubit/dashboard_state.dart';
import 'package:qordy_app/core/di/injection.dart';
import 'package:qordy_app/features/subscription/widgets/onboarding_welcome_sheet.dart';
import 'package:qordy_app/models/dashboard_stats.dart';
import 'package:qordy_app/models/order.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Owner/Manager dashboard — the hero landing page after login for
/// users who have access to the full management shell. Composed almost
/// entirely of `Q*` primitives so the visual language stays consistent
/// with the rest of the product (see `core/ui/primitives.dart`).
class DashboardScreen extends StatefulWidget {
  const DashboardScreen({super.key});

  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> {
  String _period = 'today';

  static const _periods = <QSegment<String>>[
    QSegment(value: 'today', label: 'Bugün'),
    QSegment(value: 'week', label: 'Bu Hafta'),
    QSegment(value: 'month', label: 'Bu Ay'),
  ];

  @override
  void initState() {
    super.initState();
    context.read<DashboardCubit>().loadDashboard();
    WidgetsBinding.instance
        .addPostFrameCallback((_) => _maybeShowOnboarding());
  }

  Future<void> _maybeShowOnboarding() async {
    final prefs = await SharedPreferences.getInstance();
    if (prefs.getBool('qordy_pending_onboarding') != true) return;
    final name = prefs.getString('qordy_pending_onboarding_name') ?? 'Qordy';
    final sub = prefs.getString('qordy_pending_onboarding_sub') ?? '';
    await prefs.remove('qordy_pending_onboarding');
    await prefs.remove('qordy_pending_onboarding_name');
    await prefs.remove('qordy_pending_onboarding_sub');
    if (!mounted) return;
    await OnboardingWelcomeSheet.show(
      context,
      businessName: name,
      subdomain: sub,
    );
  }

  void _changePeriod(String v) {
    if (v == _period) return;
    setState(() => _period = v);
    context.read<DashboardCubit>().loadDashboard(period: v);
  }

  Future<void> _onRefresh() =>
      context.read<DashboardCubit>().refresh();

  @override
  Widget build(BuildContext context) {
    // Only listen to the authenticated identity snapshot instead of every
    // AuthState transition — avoids dashboard-wide rebuilds on unrelated
    // auth events (token refresh, ephemeral flags, etc.).
    final authData = context.select<AuthCubit, _AuthSnapshot>((cubit) {
      final state = cubit.state;
      if (state is Authenticated) {
        return _AuthSnapshot(
          userId: state.user.userId,
          name: state.user.displayName,
          role: AppRole.fromUser(state.user),
          companyName: state.business.companyName,
          businessId: state.business.customerId,
        );
      }
      return const _AuthSnapshot.empty();
    });
    final role = authData.role;
    final name = (authData.name ?? '').split(' ').first;
    final businessName = authData.companyName;

    return Scaffold(
      backgroundColor: context.brandScaffoldBg,
      body: SafeArea(
        child: RefreshIndicator(
          onRefresh: _onRefresh,
          color: AppColors.primary,
          child: BlocBuilder<DashboardCubit, DashboardState>(
            builder: (context, state) {
              if (state is DashboardError) {
                return ListView(
                  children: [
                    SizedBox(
                      height: MediaQuery.of(context).size.height * 0.7,
                      child: AppErrorWidget(
                        message: state.message,
                        onRetry: () =>
                            context.read<DashboardCubit>().refresh(),
                      ),
                    ),
                  ],
                );
              }

              return ListView(
                padding: const EdgeInsets.fromLTRB(
                  AppSpacing.lg,
                  AppSpacing.md,
                  AppSpacing.lg,
                  AppSpacing.xxxl,
                ),
                children: [
                  QGreetingHero(
                    name: name.isEmpty ? 'QORDY' : name,
                    subtitle: businessName,
                    avatarLabel: name,
                  ),
                  // NOTE: TrialCountdownCard artık MainShell seviyesinde
                  // her sayfanın üstünde gösteriliyor — burada tekrar
                  // çizilmesin diye kaldırıldı. Tasarım boşluğu için
                  // spacer'ı aynen bırakıyoruz.
                  const SizedBox(height: AppSpacing.md),
                  Align(
                    alignment: Alignment.centerLeft,
                    child: QSegmented<String>(
                      value: _period,
                      segments: _periods,
                      onChanged: _changePeriod,
                    ),
                  ),
                  const SizedBox(height: AppSpacing.xl),
                  if (state is DashboardLoading) _buildStatsSkeleton(),
                  if (state is DashboardLoaded) _buildStats(state.stats),
                  const SizedBox(height: AppSpacing.xxl),
                  const QSectionHeader(title: 'Hızlı İşlemler'),
                  _buildQuickActions(role),
                  const SizedBox(height: AppSpacing.xxl),
                  QSectionHeader(
                    title: 'Son Siparişler',
                    trailing: TextButton(
                      onPressed: () => context.go('/orders'),
                      child: const Text('Tümü'),
                    ),
                  ),
                  if (state is DashboardLoading) _buildOrdersSkeleton(),
                  if (state is DashboardLoaded)
                    _buildRecentOrders(state.recentOrders),
                ],
              );
            },
          ),
        ),
      ),
    );
  }

  // ── Stats ─────────────────────────────────────────────────────────
  Widget _buildStats(DashboardStats s) {
    return GridView.count(
      crossAxisCount: 2,
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      mainAxisSpacing: AppSpacing.md,
      crossAxisSpacing: AppSpacing.md,
      childAspectRatio: 1.35,
      children: [
        QStatCard(
          label: 'Toplam Satış',
          value: '₺${_formatMoney(s.totalRevenue ?? 0)}',
          icon: Icons.payments_rounded,
          color: AppColors.successAlt,
        ),
        QStatCard(
          label: 'Sipariş Sayısı',
          value: '${s.totalOrders ?? 0}',
          icon: Icons.receipt_long_rounded,
          color: AppColors.primary,
        ),
        QStatCard(
          label: 'Aktif Siparişler',
          value: '${s.activeOrders ?? 0}',
          icon: Icons.pending_actions_rounded,
          color: AppColors.accentOrange,
        ),
        QStatCard(
          label: 'Ortalama Sipariş',
          value: '₺${_formatMoney(s.averageOrderValue ?? 0)}',
          icon: Icons.analytics_rounded,
          color: AppColors.accentPurple,
        ),
      ],
    );
  }

  Widget _buildStatsSkeleton() {
    return GridView.count(
      crossAxisCount: 2,
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      mainAxisSpacing: AppSpacing.md,
      crossAxisSpacing: AppSpacing.md,
      childAspectRatio: 1.35,
      children: List.generate(4, (_) => const QSkeleton(height: 120)),
    );
  }

  // ── Quick actions ─────────────────────────────────────────────────
  Widget _buildQuickActions(AppRole role) {
    final super_ = role == AppRole.admin ||
        role == AppRole.manager ||
        role == AppRole.owner;
    final actions = <_QuickAction>[];

    if (super_ || role == AppRole.cashier) {
      actions.add(const _QuickAction(
          Icons.point_of_sale_rounded, 'POS', '/pos', AppColors.primary));
    }
    if (super_ || role == AppRole.kitchen) {
      actions.add(const _QuickAction(Icons.soup_kitchen_rounded, 'Mutfak',
          '/kitchen', AppColors.errorBright));
    }
    if (super_ || role == AppRole.preparation) {
      actions.add(const _QuickAction(Icons.restaurant_rounded, 'Hazırlık',
          '/preparation', AppColors.warningBright));
    }
    if (super_ || role == AppRole.waiter) {
      actions.add(const _QuickAction(Icons.room_service_rounded, 'Garson',
          '/waiter', AppColors.successBright));
    }
    actions.add(const _QuickAction(Icons.table_restaurant_rounded, 'Masalar',
        '/tables', AppColors.infoBright));

    if (super_) {
      actions.addAll(const [
        _QuickAction(Icons.restaurant_menu_rounded, 'Menü',
            '/menu-management', AppColors.accentPurple),
        _QuickAction(Icons.category_rounded, 'Kategoriler',
            '/category-management', Color(0xFFA855F7)),
        _QuickAction(Icons.people_alt_rounded, 'Personel',
            '/staff-management', AppColors.accentCyan),
        _QuickAction(Icons.view_quilt_rounded, 'Bölgeler',
            '/zone-management', Color(0xFF0EA5E9)),
        _QuickAction(Icons.inventory_2_rounded, 'Stok', '/stock',
            AppColors.successAlt),
        _QuickAction(Icons.receipt_rounded, 'Fişler', '/receipts',
            AppColors.accentIndigo),
        _QuickAction(Icons.event_available_rounded, 'Rezervasyonlar',
            '/reservations', Color(0xFFD946EF)),
        _QuickAction(Icons.trending_up_rounded, 'Ürün Satışları',
            '/product-sales', AppColors.accentOrange),
        _QuickAction(Icons.summarize_rounded, 'Z-Raporu', '/z-report',
            Color(0xFF64748B)),
        _QuickAction(Icons.account_balance_wallet_rounded, 'Giderler',
            '/expenses', AppColors.error),
        _QuickAction(Icons.verified_rounded, 'Onay Bekleyen',
            '/order-approvals', Color(0xFFEAB308)),
        _QuickAction(Icons.insights_rounded, 'Analitik', '/analytics',
            AppColors.accentRose),
        _QuickAction(Icons.workspace_premium_rounded, 'Paketler',
            '/packages', AppColors.primaryDark),
        _QuickAction(Icons.settings_rounded, 'Ayarlar', '/settings',
            AppColors.textSecondary),
      ]);
    }

    return GridView.builder(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 4,
        mainAxisSpacing: AppSpacing.sm,
        crossAxisSpacing: AppSpacing.sm,
        childAspectRatio: 0.82,
      ),
      itemCount: actions.length,
      itemBuilder: (_, i) {
        final a = actions[i];
        return QActionTile(
          icon: a.icon,
          label: a.label,
          color: a.color,
          onTap: () {
            const tabRoutes = {
              '/tables',
              '/orders',
              '/notifications',
              '/profile',
            };
            if (tabRoutes.contains(a.route)) {
              context.go(a.route);
            } else {
              context.push(a.route);
            }
          },
        );
      },
    );
  }

  // ── Recent orders ─────────────────────────────────────────────────
  Widget _buildRecentOrders(List<Order> orders) {
    if (orders.isEmpty) {
      return const Padding(
        padding: EdgeInsets.only(top: AppSpacing.md),
        child: QEmptyState(
          icon: Icons.receipt_long_rounded,
          title: 'Henüz sipariş yok',
          message: 'Yeni siparişler geldikçe burada görünecek.',
        ),
      );
    }

    return Column(
      children: orders.take(5).map((order) {
        final items = order.items
                ?.map((i) => OrderCardItem(
                      name: i.name ?? '',
                      quantity: i.quantity ?? 1,
                    ))
                .toList() ??
            const [];
        return Padding(
          padding: const EdgeInsets.only(bottom: AppSpacing.md),
          child: OrderCard(
            orderId: order.orderId ?? '',
            tableName: order.tableName ?? '-',
            status: order.status ?? 'PENDING',
            time: _formatTime(order.createdAt),
            total: order.totalAmount ?? 0,
            items: items,
          ),
        );
      }).toList(),
    );
  }

  Widget _buildOrdersSkeleton() {
    return Column(
      children: List.generate(
        3,
        (_) => const Padding(
          padding: EdgeInsets.only(bottom: AppSpacing.md),
          child: QSkeleton(height: 120, radius: AppRadius.lg),
        ),
      ),
    );
  }

  // ── Helpers ──────────────────────────────────────────────────────
  String _formatMoney(num v) {
    final asInt = v.toInt();
    if (asInt.abs() < 1000) return asInt.toString();
    final s = asInt.toString();
    final buf = StringBuffer();
    for (var i = 0; i < s.length; i++) {
      if (i > 0 && (s.length - i) % 3 == 0) buf.write('.');
      buf.write(s[i]);
    }
    return buf.toString();
  }

  String _formatTime(String? isoDate) {
    if (isoDate == null) return '';
    try {
      final dt = DateTime.parse(isoDate);
      final diff = DateTime.now().difference(dt);
      if (diff.inMinutes < 1) return 'Az önce';
      if (diff.inMinutes < 60) return '${diff.inMinutes} dk önce';
      if (diff.inHours < 24) return '${diff.inHours} saat önce';
      return '${dt.day.toString().padLeft(2, '0')}.${dt.month.toString().padLeft(2, '0')} '
          '${dt.hour.toString().padLeft(2, '0')}:${dt.minute.toString().padLeft(2, '0')}';
    } catch (_) {
      return isoDate;
    }
  }
}

class _QuickAction {
  const _QuickAction(this.icon, this.label, this.route, this.color);
  final IconData icon;
  final String label;
  final String route;
  final Color color;
}

/// Immutable snapshot of the pieces of the auth state the dashboard actually
/// needs. Wrapped in a value-equatable class so `context.select` can bail
/// out of unnecessary rebuilds.
class _AuthSnapshot {
  final String? userId;
  final String? name;
  final AppRole role;
  final String? companyName;
  final String? businessId;

  const _AuthSnapshot({
    required this.userId,
    required this.name,
    required this.role,
    required this.companyName,
    required this.businessId,
  });

  const _AuthSnapshot.empty()
      : userId = null,
        name = null,
        role = AppRole.unknown,
        companyName = null,
        businessId = null;

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      (other is _AuthSnapshot &&
          other.userId == userId &&
          other.name == name &&
          other.role == role &&
          other.companyName == companyName &&
          other.businessId == businessId);

  @override
  int get hashCode =>
      Object.hash(userId, name, role, companyName, businessId);
}
