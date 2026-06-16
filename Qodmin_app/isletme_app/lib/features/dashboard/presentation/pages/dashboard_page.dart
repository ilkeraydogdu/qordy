import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:go_router/go_router.dart';
import 'package:fl_chart/fl_chart.dart';

import '../../../../app/router/app_router.dart';
import '../../../../app/theme/design_tokens.dart';
import '../../../../app/di/injector.dart';
import '../../../auth/presentation/bloc/auth_bloc.dart';
import '../../../auth/presentation/bloc/auth_event.dart';
import '../../domain/entities/dashboard_summary.dart';
import '../../domain/usecases/get_dashboard_summary_usecase.dart';
import '../bloc/dashboard_bloc.dart';
import '../bloc/dashboard_event.dart';
import '../bloc/dashboard_state.dart';
import '../widgets/kpi_card.dart';

class DashboardPage extends StatelessWidget {
 const DashboardPage({super.key});

 @override
 Widget build(BuildContext context) {
 return BlocProvider<DashboardBloc>(
 create: (_) => DashboardBloc(
 getSummaryUseCase: sl<GetDashboardSummaryUseCase>(),
 )..add(const DashboardLoadRequested()),
 child: const _DashboardView(),
 );
 }
}

class _DashboardView extends StatelessWidget {
 const _DashboardView();

 @override
 Widget build(BuildContext context) {
 return Scaffold(
 appBar: AppBar(
 title: const Text('Dashboard'),
 actions: [
 IconButton(
 icon: const Icon(Icons.notifications_outlined),
 onPressed: () => context.push(AppRoutes.notifications),
 ),
 IconButton(
 icon: const Icon(Icons.logout),
 onPressed: () => _confirmLogout(context),
 ),
 ],
 ),
 body: BlocBuilder<DashboardBloc, DashboardState>(
 builder: (context, state) {
 if (state is DashboardLoading || state is DashboardInitial) {
 return const Center(child: CircularProgressIndicator());
 }
 if (state is DashboardError) {
 return _ErrorView(
 message: state.message,
 onRetry: () => context
 .read<DashboardBloc>()
 .add(const DashboardRefreshRequested()),
 );
 }
 if (state is DashboardLoaded) {
 return RefreshIndicator(
 onRefresh: () async {
 context
 .read<DashboardBloc>()
 .add(const DashboardRefreshRequested());
 await Future<void>.delayed(const Duration(milliseconds: 300));
 },
 child: _DashboardContent(summary: state.summary),
 );
 }
 return const SizedBox.shrink();
 },
 ),
 );
 }

 void _confirmLogout(BuildContext context) {
 showDialog<void>(
 context: context,
 builder: (dialogCtx) => AlertDialog(
 title: const Text('Çıkış yap'),
 content: const Text('Oturumunuz kapatılacak. Devam edilsin mi?'),
 actions: [
 TextButton(
 onPressed: () => Navigator.of(dialogCtx).pop(),
 child: const Text('İptal'),
 ),
 FilledButton(
 onPressed: () {
 Navigator.of(dialogCtx).pop();
 context.read<AuthBloc>().add(const AuthLogoutRequested());
 },
 child: const Text('Çıkış'),
 ),
 ],
 ),
 );
 }
}

class _DashboardContent extends StatelessWidget {
 const _DashboardContent({required this.summary});
 final DashboardSummary summary;

 @override
 Widget build(BuildContext context) {
 return ListView(
 padding: const EdgeInsets.all(QordySpacing.lg),
 children: [
 _kpiGrid(context),
 const SizedBox(height: QordySpacing.lg),
 _RevenueCard(trend: summary.revenueTrend),
 const SizedBox(height: QordySpacing.lg),
 _TopItemsCard(items: summary.topSellingItems),
 const SizedBox(height: QordySpacing.lg),
 _QuickActionsRow(),
 ],
 );
 }

 Widget _kpiGrid(BuildContext context) {
 return GridView.count(
 crossAxisCount: 2,
 shrinkWrap: true,
 physics: const NeverScrollableScrollPhysics(),
 mainAxisSpacing: QordySpacing.md,
 crossAxisSpacing: QordySpacing.md,
 childAspectRatio: 1.6,
 children: [
 KpiCard(
 label: 'Bugün Sipariş',
 value: formatCount(summary.todayOrders),
 icon: Icons.receipt_long_outlined,
 iconColor: QordyColors.primary,
 onTap: () => context.push(AppRoutes.orders),
 ),
 KpiCard(
 label: 'Bugün Ciro',
 value: formatCurrency(summary.todayRevenue),
 icon: Icons.payments_outlined,
 iconColor: QordyColors.tertiary,
 ),
 KpiCard(
 label: 'Aktif Sipariş',
 value: formatCount(summary.activeOrders),
 icon: Icons.local_fire_department_outlined,
 iconColor: QordyColors.secondary,
 onTap: () => context.push(AppRoutes.orders),
 ),
 KpiCard(
 label: 'Masa Doluluk',
 value: '${summary.occupiedTables}/${summary.totalTables}',
 icon: Icons.table_restaurant_outlined,
 iconColor: QordyColors.primary,
 trend: '%${summary.tableOccupancyRate.toStringAsFixed(0)}',
 onTap: () => context.push(AppRoutes.tables),
 ),
 ],
 );
 }
}

class _RevenueCard extends StatelessWidget {
 const _RevenueCard({required this.trend});
 final List<RevenuePoint> trend;

 @override
 Widget build(BuildContext context) {
 if (trend.isEmpty) return const SizedBox.shrink();
 return Card(
 child: Padding(
 padding: const EdgeInsets.all(QordySpacing.lg),
 child: Column(
 crossAxisAlignment: CrossAxisAlignment.start,
 children: [
 Text('Saatlik Ciro', style: QordyTypography.titleMedium),
 const SizedBox(height: QordySpacing.md),
 SizedBox(
 height: 160,
 child: LineChart(
 LineChartData(
 gridData: const FlGridData(show: false),
 titlesData: const FlTitlesData(show: false),
 borderData: FlBorderData(show: false),
 lineBarsData: [
 LineChartBarData(
 spots: trend
 .map((p) => FlSpot(p.hour.toDouble(), p.amount))
 .toList(),
 isCurved: true,
 color: QordyColors.primary,
 barWidth: 2,
 dotData: const FlDotData(show: false),
 belowBarData: BarAreaData(
 show: true,
 color: QordyColors.primary.withValues(alpha: 0.1),
 ),
 ),
 ],
 ),
 ),
 ),
 ],
 ),
 ),
 );
 }
}

class _TopItemsCard extends StatelessWidget {
 const _TopItemsCard({required this.items});
 final List<TopItem> items;

 @override
 Widget build(BuildContext context) {
 if (items.isEmpty) return const SizedBox.shrink();
 return Card(
 child: Padding(
 padding: const EdgeInsets.all(QordySpacing.lg),
 child: Column(
 crossAxisAlignment: CrossAxisAlignment.start,
 children: [
 Text('Çok Satanlar', style: QordyTypography.titleMedium),
 const SizedBox(height: QordySpacing.md),
 ...items.take(5).map(
 (it) => Padding(
 padding: const EdgeInsets.symmetric(vertical: QordySpacing.xs),
 child: Row(
 children: [
 const Icon(Icons.star_outline,
 size: 18, color: QordyColors.secondary),
 const SizedBox(width: QordySpacing.sm),
 Expanded(child: Text(it.name, style: QordyTypography.bodyMedium)),
 Text('${it.soldCount} adet',
 style: QordyTypography.bodySmall.copyWith(
 color: QordyColors.onSurfaceVariant,
 )),
 const SizedBox(width: QordySpacing.md),
 Text(formatCurrency(it.revenue),
 style: QordyTypography.bodyMedium.copyWith(
 fontWeight: FontWeight.w600,
 fontFeatures: const [FontFeature.tabularFigures()],
 )),
 ],
 ),
 ),
 ),
 ],
 ),
 ),
 );
 }
}

class _QuickActionsRow extends StatelessWidget {
 @override
 Widget build(BuildContext context) {
 return Card(
 child: Padding(
 padding: const EdgeInsets.all(QordySpacing.md),
 child: Row(
 mainAxisAlignment: MainAxisAlignment.spaceAround,
 children: [
 _QuickAction(icon: Icons.table_restaurant, label: 'Masalar',
 onTap: () => context.push(AppRoutes.tables)),
 _QuickAction(icon: Icons.restaurant_menu, label: 'Menü',
 onTap: () => context.push(AppRoutes.menu)),
 _QuickAction(icon: Icons.people, label: 'Personel',
 onTap: () => context.push(AppRoutes.staff)),
 _QuickAction(icon: Icons.bar_chart, label: 'Raporlar',
 onTap: () => context.push(AppRoutes.reports)),
 ],
 ),
 ),
 );
 }
}

class _QuickAction extends StatelessWidget {
 const _QuickAction({
 required this.icon,
 required this.label,
 required this.onTap,
 });
 final IconData icon;
 final String label;
 final VoidCallback onTap;

 @override
 Widget build(BuildContext context) {
 return InkWell(
 onTap: onTap,
 borderRadius: QordyRadius.brMd,
 child: Padding(
 padding: const EdgeInsets.all(QordySpacing.md),
 child: Column(
 mainAxisSize: MainAxisSize.min,
 children: [
 Icon(icon, color: QordyColors.primary, size: 28),
 const SizedBox(height: QordySpacing.xs),
 Text(label, style: QordyTypography.labelSmall),
 ],
 ),
 ),
 );
 }
}

class _ErrorView extends StatelessWidget {
 const _ErrorView({required this.message, required this.onRetry});
 final String message;
 final VoidCallback onRetry;

 @override
 Widget build(BuildContext context) {
 return Center(
 child: Column(
 mainAxisSize: MainAxisSize.min,
 children: [
 const Icon(Icons.error_outline, size: 48, color: QordyColors.error),
 const SizedBox(height: QordySpacing.md),
 Text(message, textAlign: TextAlign.center,
 style: QordyTypography.bodyMedium),
 const SizedBox(height: QordySpacing.lg),
 FilledButton(onPressed: onRetry, child: const Text('Tekrar Dene')),
 ],
 ),
 );
 }
}
