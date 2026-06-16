import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../features/auth/presentation/pages/login_page.dart';
import '../../features/dashboard/presentation/pages/dashboard_page.dart';
import '../../features/orders/presentation/pages/orders_list_page.dart';
import '../../features/orders/presentation/pages/order_detail_page.dart';
import '../../features/menu/presentation/pages/menu_page.dart';
import '../../features/tables/presentation/pages/tables_page.dart';
import '../../features/staff/presentation/pages/staff_page.dart';
import '../../features/reports/presentation/pages/reports_page.dart';
import '../../features/notifications/presentation/pages/notifications_page.dart';
import '../../features/auth/presentation/pages/two_factor_page.dart';
import 'route_guards.dart';

class AppRoutes {
 AppRoutes._();
 static const String login = '/login';
 static const String twoFactor = '/two-factor';
 static const String dashboard = '/';
 static const String orders = '/orders';
 static const String orderDetail = '/orders/:id';
 static const String menu = '/menu';
 static const String tables = '/tables';
 static const String staff = '/staff';
 static const String reports = '/reports';
 static const String notifications = '/notifications';
}

final GoRouter appRouter = GoRouter(
 initialLocation: AppRoutes.login,
 debugLogDiagnostics: false,
 refreshListenable: authStateListenable,
 redirect: (context, state) {
 final loggedIn = isAuthenticated();
 final isAuthRoute = state.matchedLocation == AppRoutes.login ||
 state.matchedLocation == AppRoutes.twoFactor;
 if (!loggedIn && !isAuthRoute) return AppRoutes.login;
 if (loggedIn && isAuthRoute) return AppRoutes.dashboard;
 return null;
 },
 routes: <RouteBase>[
 GoRoute(
 path: AppRoutes.login,
 builder: (context, state) => const LoginPage(),
 ),
 GoRoute(
 path: AppRoutes.twoFactor,
 builder: (context, state) => const TwoFactorPage(),
 ),
 GoRoute(
 path: AppRoutes.dashboard,
 builder: (context, state) => const DashboardPage(),
 ),
 GoRoute(
 path: AppRoutes.orders,
 builder: (context, state) => const OrdersListPage(),
 routes: <RouteBase>[
 GoRoute(
 path: ':id',
 builder: (context, state) =>
 OrderDetailPage(orderId: state.pathParameters['id']!),
 ),
 ],
 ),
 GoRoute(
 path: AppRoutes.menu,
 builder: (context, state) => const MenuPage(),
 ),
 GoRoute(
 path: AppRoutes.tables,
 builder: (context, state) => const TablesPage(),
 ),
 GoRoute(
 path: AppRoutes.staff,
 builder: (context, state) => const StaffPage(),
 ),
 GoRoute(
 path: AppRoutes.reports,
 builder: (context, state) => const ReportsPage(),
 ),
 GoRoute(
 path: AppRoutes.notifications,
 builder: (context, state) => const NotificationsPage(),
 ),
 ],
 errorBuilder: (context, state) => Scaffold(
 appBar: AppBar(title: const Text('Sayfa bulunamadı')),
 body: Center(
 child: Text('Hata: ${state.error?.message ?? "Bilinmeyen"}'),
 ),
 ),
);
