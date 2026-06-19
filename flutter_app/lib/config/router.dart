import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';

import '../core/di/injection.dart';
import '../core/navigation/role_home.dart';
import '../features/auth/cubit/auth_cubit.dart';
import '../features/auth/cubit/auth_state.dart';
import '../features/auth/screens/manager_email_screen.dart';
import '../features/auth/screens/manager_password_screen.dart';
import '../features/auth/screens/register_screen.dart';
import '../features/auth/screens/unified_login_screen.dart';
import '../features/dashboard/cubit/dashboard_cubit.dart';
import '../features/dashboard/screens/dashboard_screen.dart';
import '../features/manager/cubit/analytics_cubit.dart';
import '../features/manager/cubit/approvals_cubit.dart';
import '../features/manager/cubit/expenses_cubit.dart';
import '../features/manager/cubit/menu_cubit.dart';
import '../features/manager/cubit/reservations_cubit.dart';
import '../features/manager/cubit/staff_cubit.dart';
import '../features/manager/screens/analytics_screen.dart';
import '../features/manager/screens/category_management_screen.dart';
import '../features/manager/screens/expenses_screen.dart';
import '../features/manager/screens/menu_management_screen.dart';
import '../features/manager/screens/order_approvals_screen.dart';
import '../features/manager/screens/product_sales_screen.dart';
import '../features/manager/screens/receipts_screen.dart';
import '../features/manager/screens/reservations_screen.dart';
import '../features/manager/screens/settings_screen.dart';
import '../features/manager/screens/staff_management_screen.dart';
import '../features/manager/screens/stock_screen.dart';
import '../features/manager/screens/z_report_screen.dart';
import '../features/manager/screens/zone_management_screen.dart';
import '../features/notifications/cubit/notifications_cubit.dart';
import '../features/notifications/screens/notifications_screen.dart';
import '../features/packages/screens/packages_screen.dart';
import '../features/packages/screens/purchase_history_screen.dart';
import '../features/profile/screens/profile_screen.dart';
import '../features/security/screens/quick_unlock_screen.dart';
import '../features/security/screens/quick_unlock_setup_screen.dart';
import '../features/security/screens/security_screen.dart';
import '../features/notifications/screens/notification_settings_screen.dart';
import '../features/security/screens/totp_challenge_screen.dart';
import '../features/admin/screens/admin_hub_screen.dart';
import '../features/admin/screens/approval_history_screen.dart';
import '../features/admin/screens/finance_screens.dart';
import '../features/admin/screens/printers_screen.dart';
import '../features/admin/screens/queue_screen.dart';
import '../features/admin/screens/receipt_templates_screen.dart';
import '../features/admin/screens/roles_permissions_screen.dart';
import '../features/admin/screens/system_screens.dart';
import '../features/shell/main_shell.dart';

class AppRouter {
  AppRouter._();

  static final _rootNavigatorKey = GlobalKey<NavigatorState>();
  static final _dashboardNavigatorKey =
      GlobalKey<NavigatorState>(debugLabel: 'dashboard');
  static final _ordersNavigatorKey =
      GlobalKey<NavigatorState>(debugLabel: 'orders');
  static final _notificationsNavigatorKey =
      GlobalKey<NavigatorState>(debugLabel: 'notifications');
  static final _profileNavigatorKey =
      GlobalKey<NavigatorState>(debugLabel: 'profile');

  /// Routes that are visible only while unauthenticated. The mobile app
  /// is now manager-only — staff PIN / staff subdomain flows were
  /// removed when the personnel feature set was retired.
  static const _authOnlyRoutes = <String>{
    '/login',
    '/manager-email',
    '/manager-password',
    '/register',
  };

  /// Quick-unlock gate routes — only visible when the auth state is in
  /// a transitional phase (PendingUnlock / QuickUnlockSetupRequired).
  static const _unlockRoute = '/quick-unlock';
  static const _unlockSetupRoute = '/quick-unlock/setup';
  static const _totpChallengeRoute = '/totp-challenge';

  /// Routes that are subscription / operational management — only
  /// owners / managers / admins should ever see them.
  static const _managerOnlyRoutes = <String>{
    '/dashboard',
    '/orders',
    '/packages',
    '/analytics',
    '/staff-management',
    '/menu-management',
    '/category-management',
    '/zone-management',
    '/expenses',
    '/stock',
    '/reservations',
    '/z-report',
    '/receipts',
    '/product-sales',
    '/order-approvals',
    '/settings',
    '/admin',
  };

  static GoRouter router(AuthCubit authCubit) {
    return GoRouter(
      navigatorKey: _rootNavigatorKey,
      initialLocation: '/login',
      debugLogDiagnostics: false,
      refreshListenable: _GoRouterRefreshStream(authCubit.stream),
      redirect: (context, state) {
        final authState = authCubit.state;
        final isAuthenticated = authState is Authenticated;
        final currentPath = state.matchedLocation;

        // Legacy `/role-select` deeplinks still exist in older builds
        // and printed materials; silently promote them to the new
        // unified `/login` landing so nothing breaks.
        if (currentPath == '/role-select') return '/login';

        final isOnAuthPage = _authOnlyRoutes.contains(currentPath);
        final isOnUnlock = currentPath == _unlockRoute;
        final isOnUnlockSetup = currentPath == _unlockSetupRoute;
        final isOnTotpChallenge = currentPath == _totpChallengeRoute;

        // 2FA challenge gate: password/PIN OK but the server is still
        // holding the bearer until the TOTP code verifies. Only the
        // dedicated challenge screen is reachable in this state.
        if (authState is TwoFactorChallengeRequired) {
          return isOnTotpChallenge ? null : _totpChallengeRoute;
        }
        // Quick-unlock gate: persisted session but waiting for PIN.
        if (authState is PendingUnlock) {
          return isOnUnlock ? null : _unlockRoute;
        }
        // First-time setup gate: authenticated but user hasn't been
        // asked whether they want quick unlock yet.
        if (authState is QuickUnlockSetupRequired) {
          return isOnUnlockSetup ? null : _unlockSetupRoute;
        }

        // Unauthenticated users can only see the login flow.
        if (!isAuthenticated) {
          return isOnAuthPage ? null : '/login';
        }

        // Authenticated users never see the auth or unlock screens.
        final role = AppRole.fromUser(authState.user);
        if (isOnAuthPage ||
            isOnUnlock ||
            isOnUnlockSetup ||
            isOnTotpChallenge) {
          return RoleHome.initialRouteFor(role);
        }

        // Paywall gate: deneme süresi bitmiş veya abonelik askıya
        // alınmış işletme yöneticilerini /packages ekranına kilitle.
        if (authState.isPaywalled && RoleHome.showsFullShell(role)) {
          final allowedUnderPaywall = currentPath.startsWith('/packages') ||
              currentPath.startsWith('/purchase-history') ||
              currentPath.startsWith('/profile') ||
              currentPath.startsWith('/security') ||
              ;
          if (!allowedUnderPaywall) {
            return '/packages';
          }
        }

        // Manager-only pages are forbidden for non-manager roles.
        final isManagerOnly = _managerOnlyRoutes.any(
          (r) => currentPath == r || currentPath.startsWith('$r/'),
        );
        if (isManagerOnly && !RoleHome.showsFullShell(role)) {
          return RoleHome.initialRouteFor(role);
        }

        // Role-level guard: a non-manager should not deep-link into a
        // forbidden page.
        if (!RoleHome.canAccess(role, currentPath)) {
          return RoleHome.initialRouteFor(role);
        }

        return null;
      },
      routes: [
        // ── Auth Routes ──
        // `/login` is the unified entry point: a single input that
        // routes to manager-password based on whether the user typed
        // an e-mail or a business name.
        GoRoute(
          path: '/login',
          name: 'login',
          parentNavigatorKey: _rootNavigatorKey,
          builder: (context, state) => const UnifiedLoginScreen(),
        ),
        GoRoute(
          path: '/manager-email',
          name: 'managerEmail',
          parentNavigatorKey: _rootNavigatorKey,
          builder: (context, state) => const ManagerEmailScreen(),
        ),
        GoRoute(
          path: '/manager-password',
          name: 'managerPassword',
          parentNavigatorKey: _rootNavigatorKey,
          builder: (context, state) {
            final extra = state.extra as Map<String, dynamic>? ?? {};
            return ManagerPasswordScreen(
              email: extra['email'] as String? ?? '',
            );
          },
        ),
        GoRoute(
          path: '/register',
          name: 'register',
          parentNavigatorKey: _rootNavigatorKey,
          builder: (context, state) => const RegisterScreen(),
        ),

        // ── Quick Unlock Gate ──
        // These two routes are only reachable via the redirect above;
        // they render solely based on [AuthCubit] state so they're safe
        // to place at the root level.
        GoRoute(
          path: '/quick-unlock',
          name: 'quickUnlock',
          parentNavigatorKey: _rootNavigatorKey,
          builder: (context, state) => const QuickUnlockScreen(),
        ),
        GoRoute(
          path: '/quick-unlock/setup',
          name: 'quickUnlockSetup',
          parentNavigatorKey: _rootNavigatorKey,
          builder: (context, state) => const QuickUnlockSetupScreen(),
        ),
        GoRoute(
          path: '/totp-challenge',
          name: 'totpChallenge',
          parentNavigatorKey: _rootNavigatorKey,
          builder: (context, state) => const TotpChallengeScreen(),
        ),

        // ── Manager Shell (4-tab bottom navigation) ──
        StatefulShellRoute.indexedStack(
          parentNavigatorKey: _rootNavigatorKey,
          builder: (context, state, navigationShell) =>
              MainShell(navigationShell: navigationShell),
          branches: [
            StatefulShellBranch(
              navigatorKey: _dashboardNavigatorKey,
              routes: [
                GoRoute(
                  path: '/dashboard',
                  name: 'dashboard',
                  builder: (context, state) => BlocProvider(
                    create: (_) => getIt<DashboardCubit>(),
                    child: const DashboardScreen(),
                  ),
                ),
              ],
            ),
            StatefulShellBranch(
              navigatorKey: _ordersNavigatorKey,
              routes: [
                GoRoute(
                  path: '/orders',
                  name: 'orders',
                  builder: (context, state) => const _OrdersPlaceholder(),
                ),
              ],
            ),
            StatefulShellBranch(
              navigatorKey: _notificationsNavigatorKey,
              routes: [
                GoRoute(
                  path: '/notifications',
                  name: 'notifications',
                  builder: (context, state) => BlocProvider(
                    create: (_) => getIt<NotificationsCubit>(),
                    child: const NotificationsScreen(),
                  ),
                ),
              ],
            ),
            StatefulShellBranch(
              navigatorKey: _profileNavigatorKey,
              routes: [
                GoRoute(
                  path: '/profile',
                  name: 'profile',
                  builder: (context, state) => const ProfileScreen(),
                ),
              ],
            ),
          ],
        ),

        // ── Manager Drawer Routes ──
        GoRoute(
          path: '/analytics',
          name: 'analytics',
          parentNavigatorKey: _rootNavigatorKey,
          builder: (_, __) => BlocProvider(
            create: (_) => getIt<AnalyticsCubit>()..loadAnalytics(),
            child: const AnalyticsScreen(),
          ),
        ),
        GoRoute(
          path: '/staff-management',
          name: 'staffManagement',
          parentNavigatorKey: _rootNavigatorKey,
          builder: (_, __) => BlocProvider(
            create: (_) => getIt<StaffCubit>()..loadStaff(),
            child: const StaffManagementScreen(),
          ),
        ),
        GoRoute(
          path: '/menu-management',
          name: 'menuManagement',
          parentNavigatorKey: _rootNavigatorKey,
          builder: (_, __) => BlocProvider(
            create: (_) => getIt<MenuManagementCubit>()..loadMenu(),
            child: const MenuManagementScreen(),
          ),
        ),
        GoRoute(
          path: '/zone-management',
          name: 'zoneManagement',
          parentNavigatorKey: _rootNavigatorKey,
          builder: (_, __) => const ZoneManagementScreen(),
        ),
        GoRoute(
          path: '/category-management',
          name: 'categoryManagement',
          parentNavigatorKey: _rootNavigatorKey,
          builder: (_, __) => const CategoryManagementScreen(),
        ),
        GoRoute(
          path: '/expenses',
          name: 'expenses',
          parentNavigatorKey: _rootNavigatorKey,
          builder: (_, __) => BlocProvider(
            create: (_) => getIt<ExpensesCubit>()..loadExpenses(),
            child: const ExpensesScreen(),
          ),
        ),
        GoRoute(
          path: '/stock',
          name: 'stock',
          parentNavigatorKey: _rootNavigatorKey,
          builder: (_, __) => const StockScreen(),
        ),
        GoRoute(
          path: '/reservations',
          name: 'reservations',
          parentNavigatorKey: _rootNavigatorKey,
          builder: (_, __) => BlocProvider(
            create: (_) => getIt<ReservationsCubit>()..loadReservations(),
            child: const ReservationsScreen(),
          ),
        ),
        GoRoute(
          path: '/z-report',
          name: 'zReport',
          parentNavigatorKey: _rootNavigatorKey,
          builder: (_, __) => const ZReportScreen(),
        ),
        GoRoute(
          path: '/receipts',
          name: 'receipts',
          parentNavigatorKey: _rootNavigatorKey,
          builder: (_, __) => const ReceiptsScreen(),
        ),
        GoRoute(
          path: '/product-sales',
          name: 'productSales',
          parentNavigatorKey: _rootNavigatorKey,
          builder: (_, __) => const ProductSalesScreen(),
        ),
        GoRoute(
          path: '/order-approvals',
          name: 'orderApprovals',
          parentNavigatorKey: _rootNavigatorKey,
          builder: (_, __) => BlocProvider(
            create: (_) => getIt<ApprovalsCubit>()
              ..loadApprovals()
              ..startAutoRefresh(),
            child: const OrderApprovalsScreen(),
          ),
        ),
        // Settings screen provides its own cubit via BlocProvider internally.
        GoRoute(
          path: '/settings',
          name: 'settings',
          parentNavigatorKey: _rootNavigatorKey,
          builder: (_, __) => const SettingsScreen(),
        ),

        // Security (quick unlock PIN + 2FA TOTP)
        GoRoute(
          path: '/security',
          name: 'security',
          parentNavigatorKey: _rootNavigatorKey,
          builder: (_, __) => const SecurityScreen(),
        ),

        // Bildirim tercihleri (ses, titreşim, kategori bazlı toggle)
        GoRoute(
          path: '/notification-settings',
          name: 'notification-settings',
          parentNavigatorKey: _rootNavigatorKey,
          builder: (_, __) => const NotificationSettingsScreen(),
        ),

        // ── Manager-only subscription / support ──
        GoRoute(
          path: '/packages',
          name: 'packages',
          parentNavigatorKey: _rootNavigatorKey,
          builder: (_, __) => const PackagesScreen(),
        ),
        GoRoute(
          path: '/purchase-history',
          name: 'purchaseHistory',
          parentNavigatorKey: _rootNavigatorKey,
          builder: (_, __) => const PurchaseHistoryScreen(),
        ),
        // ── Admin hub (printers, queue, receipt templates, finance…) ──
        GoRoute(
          path: '/admin',
          name: 'adminHub',
          parentNavigatorKey: _rootNavigatorKey,
          builder: (_, __) => const AdminHubScreen(),
        ),
        GoRoute(
          path: '/admin/printers',
          name: 'adminPrinters',
          parentNavigatorKey: _rootNavigatorKey,
          builder: (_, __) => const PrintersScreen(),
        ),
        GoRoute(
          path: '/admin/queue',
          name: 'adminQueue',
          parentNavigatorKey: _rootNavigatorKey,
          builder: (_, __) => const QueueScreen(),
        ),
        GoRoute(
          path: '/admin/receipt-templates',
          name: 'adminReceiptTemplates',
          parentNavigatorKey: _rootNavigatorKey,
          builder: (_, __) => const ReceiptTemplatesScreen(),
        ),
        GoRoute(
          path: '/admin/roles-permissions',
          name: 'adminRolesPermissions',
          parentNavigatorKey: _rootNavigatorKey,
          builder: (_, __) => const RolesPermissionsScreen(),
        ),
        GoRoute(
          path: '/admin/approval-history',
          name: 'adminApprovalHistory',
          parentNavigatorKey: _rootNavigatorKey,
          builder: (_, __) => const ApprovalHistoryScreen(),
        ),
        GoRoute(
          path: '/admin/invoices',
          name: 'adminInvoices',
          parentNavigatorKey: _rootNavigatorKey,
          builder: (_, __) => const InvoicesScreen(),
        ),
        GoRoute(
          path: '/admin/suppliers',
          name: 'adminSuppliers',
          parentNavigatorKey: _rootNavigatorKey,
          builder: (_, __) => const SuppliersScreen(),
        ),
        GoRoute(
          path: '/admin/waste',
          name: 'adminWaste',
          parentNavigatorKey: _rootNavigatorKey,
          builder: (_, __) => const WasteScreen(),
        ),
        // POS cihazları işletme-seviyesi: kendi terminallerini yönetmek
        // için açık kalsın. Ödeme ağ geçitleri, feature flag ve global
        // error log SUPERADMIN-only; mobilde gösterilmiyor.
        GoRoute(
          path: '/admin/pos-devices',
          name: 'adminPosDevices',
          parentNavigatorKey: _rootNavigatorKey,
          builder: (_, __) => const PosDevicesScreen(),
        ),
        GoRoute(
          path: '/admin/reports',
          name: 'adminReports',
          parentNavigatorKey: _rootNavigatorKey,
          builder: (_, __) => const ReportsScreen(),
        ),
      ],
    );
  }
}

class _OrdersPlaceholder extends StatelessWidget {
  const _OrdersPlaceholder();

  @override
  Widget build(BuildContext context) {
    return const _UnavailableScreen(
      title: 'Siparişler',
      message:
          'Sipariş yönetimi mobil uygulamada henüz hazır değil. '
          'Lütfen web paneli kullanın.',
    );
  }
}

class _UnavailableScreen extends StatelessWidget {
  const _UnavailableScreen({required this.title, required this.message});

  final String title;
  final String message;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(title)),
      body: Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.info_outline, size: 56),
              const SizedBox(height: 16),
              Text(
                title,
                style: Theme.of(context).textTheme.headlineSmall,
              ),
              const SizedBox(height: 8),
              Text(
                message,
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.bodyMedium,
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _GoRouterRefreshStream extends ChangeNotifier {
  _GoRouterRefreshStream(Stream<dynamic> stream) {
    notifyListeners();
    _subscription = stream.asBroadcastStream().listen((_) => notifyListeners());
  }

  late final StreamSubscription<dynamic> _subscription;

  @override
  void dispose() {
    _subscription.cancel();
    super.dispose();
  }
}
