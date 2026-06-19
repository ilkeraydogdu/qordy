import 'dart:math' as math;

import 'package:fl_chart/fl_chart.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import 'package:qordy_app/config/theme.dart';
import 'package:qordy_app/core/ui/primitives.dart';
import 'package:qordy_app/core/widgets/app_error_widget.dart';
import 'package:qordy_app/models/analytics.dart';

import '../cubit/analytics_cubit.dart';
import '../cubit/analytics_state.dart';

/// Cubit provided by the router. Do NOT self-wrap a second
/// `BlocProvider<AnalyticsCubit>` here — it would shadow the router's
/// instance and leak a duplicate.
class AnalyticsScreen extends StatefulWidget {
  const AnalyticsScreen({super.key});

  @override
  State<AnalyticsScreen> createState() => _AnalyticsScreenState();
}

class _AnalyticsScreenState extends State<AnalyticsScreen> {
  int _selectedPeriod = 0;
  static const _periods = [
    _PeriodOption('Bugün', 'today'),
    _PeriodOption('Bu Hafta', 'week'),
    _PeriodOption('Bu Ay', 'month'),
    _PeriodOption('Bu Yıl', 'year'),
  ];

  /// Kategori çubuklarının renk paleti — rastgele değil kategoriye
  /// sabit eşlenir ki aynı kategori her zaman aynı tonu alsın.
  static const _categoryPalette = <Color>[
    AppColors.primary,
    AppColors.successAlt,
    AppColors.accentOrange,
    AppColors.accentPurple,
    AppColors.accentRose,
    AppColors.accentCyan,
    AppColors.accentIndigo,
    AppColors.warningBright,
  ];

  Color _colorForIndex(int i) =>
      _categoryPalette[i % _categoryPalette.length];

  void _onPeriodChanged(int index) {
    setState(() => _selectedPeriod = index);
    context.read<AnalyticsCubit>().loadAnalytics(
          period: _periods[index].value,
        );
  }

  Future<void> _onRefresh() async {
    await context.read<AnalyticsCubit>().refresh();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        title: Text(
          'Analiz',
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
      body: RefreshIndicator(
        onRefresh: _onRefresh,
        color: AppColors.primary,
        child: BlocBuilder<AnalyticsCubit, AnalyticsState>(
          builder: (context, state) {
            if (state is AnalyticsError) {
              return ListView(
                children: [
                  SizedBox(
                    height: MediaQuery.of(context).size.height * 0.7,
                    child: AppErrorWidget(
                      message: state.message,
                      onRetry: _onRefresh,
                    ),
                  ),
                ],
              );
            }

            return ListView(
              padding: const EdgeInsets.fromLTRB(20, 8, 20, 32),
              children: [
                _buildPeriodSelector(),
                const SizedBox(height: 20),
                if (state is AnalyticsLoading) _buildShimmer(),
                if (state is AnalyticsLoaded) ...[
                  _buildSummaryCards(state.analytics),
                  const SizedBox(height: 24),
                  _buildRevenueChart(state.analytics),
                  const SizedBox(height: 24),
                  _buildCategoryBreakdown(state.categorySales),
                  const SizedBox(height: 24),
                  _buildTopProducts(state.analytics.topProducts ?? []),
                ],
              ],
            );
          },
        ),
      ),
    );
  }

  Widget _buildPeriodSelector() {
    return Align(
      alignment: Alignment.centerLeft,
      child: QSegmented<int>(
        value: _selectedPeriod,
        segments: [
          for (var i = 0; i < _periods.length; i++)
            QSegment(value: i, label: _periods[i].label),
        ],
        onChanged: _onPeriodChanged,
      ),
    );
  }

  Widget _buildSummaryCards(AnalyticsData data) {
    return GridView.count(
      crossAxisCount: 2,
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      mainAxisSpacing: AppSpacing.md,
      crossAxisSpacing: AppSpacing.md,
      childAspectRatio: 1.35,
      children: [
        QStatCard(
          icon: Icons.payments_rounded,
          color: AppColors.successAlt,
          label: 'Toplam Ciro',
          value: '₺${_formatNumber(data.totalRevenue ?? 0)}',
        ),
        QStatCard(
          icon: Icons.receipt_long_rounded,
          color: AppColors.primary,
          label: 'Sipariş',
          value: '${data.totalOrders ?? 0}',
        ),
        QStatCard(
          icon: Icons.analytics_rounded,
          color: AppColors.accentPurple,
          label: 'Ortalama',
          value: '₺${_formatNumber(data.averageOrderValue ?? 0)}',
        ),
        QStatCard(
          icon: Icons.category_rounded,
          color: AppColors.accentOrange,
          label: 'Kategori',
          value: '${data.categorySales?.length ?? 0}',
        ),
      ],
    );
  }

  Widget _buildRevenueChart(AnalyticsData data) {
    final dailySales = data.dailySales ?? [];
    if (dailySales.isEmpty) {
      return _buildEmptySection('Gelir Grafiği', 'Grafik verisi bulunamadı');
    }

    final spots = dailySales.asMap().entries.map((entry) {
      return FlSpot(
        entry.key.toDouble(),
        entry.value.revenue ?? 0,
      );
    }).toList();

    final maxY = spots.fold<double>(
            0, (prev, s) => math.max(prev, s.y)) *
        1.2;

    // En son data noktası ile ilk data noktası arasındaki % değişim —
    // header'a küçük bir trend göstergesi olarak konuyor.
    final firstY = spots.first.y;
    final lastY = spots.last.y;
    final hasTrend = firstY > 0;
    final trendPct = hasTrend ? ((lastY - firstY) / firstY) * 100 : 0;
    final trendUp = trendPct >= 0;
    final trendColor =
        trendUp ? AppColors.successAlt : AppColors.errorBright;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const QSectionHeader(title: 'Gelir Grafiği'),
        QCard(
          padding: const EdgeInsets.fromLTRB(
              AppSpacing.md, AppSpacing.md, AppSpacing.md, AppSpacing.sm),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Text(
                    '₺${_formatNumber(lastY)}',
                    style: TextStyle(
                      fontSize: 24,
                      fontWeight: FontWeight.w800,
                      color: context.brandTextPrimary,
                      letterSpacing: -0.4,
                    ),
                  ),
                  const SizedBox(width: 10),
                  if (hasTrend)
                    Container(
                      padding: const EdgeInsets.symmetric(
                          horizontal: 8, vertical: 3),
                      decoration: BoxDecoration(
                        color: trendColor.withValues(alpha: 0.14),
                        borderRadius: BorderRadius.circular(999),
                        border: Border.all(
                          color: trendColor.withValues(alpha: 0.28),
                          width: 0.6,
                        ),
                      ),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Icon(
                            trendUp
                                ? Icons.trending_up_rounded
                                : Icons.trending_down_rounded,
                            size: 12,
                            color: trendColor,
                          ),
                          const SizedBox(width: 3),
                          Text(
                            '${trendPct.toStringAsFixed(1)}%',
                            style: TextStyle(
                              fontSize: 11,
                              fontWeight: FontWeight.w700,
                              color: trendColor,
                            ),
                          ),
                        ],
                      ),
                    ),
                  const Spacer(),
                  Text(
                    'Son gün',
                    style: TextStyle(
                        fontSize: 11,
                        color: context.brandTextHint,
                        fontWeight: FontWeight.w500),
                  ),
                ],
              ),
              const SizedBox(height: AppSpacing.sm),
              SizedBox(
                height: 200,
                child: LineChart(
                  LineChartData(
                    gridData: FlGridData(
                      show: true,
                      drawVerticalLine: false,
                      horizontalInterval: maxY > 0 ? maxY / 4 : 1,
                      getDrawingHorizontalLine: (value) => FlLine(
                        color: context.brandBorder.withValues(alpha: 0.4),
                        strokeWidth: 1,
                        dashArray: const [4, 4],
                      ),
                    ),
                    titlesData: FlTitlesData(
                      leftTitles: AxisTitles(
                        sideTitles: SideTitles(
                          showTitles: true,
                          reservedSize: 56,
                          getTitlesWidget: (value, meta) {
                            return Padding(
                              padding: const EdgeInsets.only(right: 8),
                              child: Text(
                                '₺${_formatCompact(value)}',
                                style: TextStyle(
                                  fontSize: 10,
                                  color: context.brandTextHint,
                                  fontWeight: FontWeight.w500,
                                ),
                              ),
                            );
                          },
                        ),
                      ),
                      bottomTitles: AxisTitles(
                        sideTitles: SideTitles(
                          showTitles: true,
                          reservedSize: 28,
                          interval: math.max(
                              1, (dailySales.length / 6).ceil().toDouble()),
                          getTitlesWidget: (value, meta) {
                            final idx = value.toInt();
                            if (idx < 0 || idx >= dailySales.length) {
                              return const SizedBox.shrink();
                            }
                            final date = dailySales[idx].date ?? '';
                            final label = date.length >= 5
                                ? date.substring(date.length - 5)
                                : date;
                            return Padding(
                              padding: const EdgeInsets.only(top: 6),
                              child: Text(
                                label,
                                style: TextStyle(
                                  fontSize: 10,
                                  color: context.brandTextHint,
                                  fontWeight: FontWeight.w500,
                                ),
                              ),
                            );
                          },
                        ),
                      ),
                      topTitles: const AxisTitles(
                          sideTitles: SideTitles(showTitles: false)),
                      rightTitles: const AxisTitles(
                          sideTitles: SideTitles(showTitles: false)),
                    ),
                    borderData: FlBorderData(show: false),
                    minX: 0,
                    maxX: (spots.length - 1).toDouble(),
                    minY: 0,
                    maxY: maxY > 0 ? maxY : 1,
                    lineBarsData: [
                      LineChartBarData(
                        spots: spots,
                        isCurved: true,
                        curveSmoothness: 0.35,
                        gradient: const LinearGradient(
                          begin: Alignment.centerLeft,
                          end: Alignment.centerRight,
                          colors: [
                            AppColors.primaryLight,
                            AppColors.primary,
                          ],
                        ),
                        barWidth: 3,
                        isStrokeCapRound: true,
                        dotData: FlDotData(
                          show: spots.length <= 14,
                          getDotPainter: (spot, _, __, ___) =>
                              FlDotCirclePainter(
                            radius: 3.5,
                            color: context.brandCard,
                            strokeWidth: 2,
                            strokeColor: AppColors.primary,
                          ),
                        ),
                        belowBarData: BarAreaData(
                          show: true,
                          gradient: LinearGradient(
                            begin: Alignment.topCenter,
                            end: Alignment.bottomCenter,
                            colors: [
                              AppColors.primary.withValues(alpha: 0.22),
                              AppColors.primary.withValues(alpha: 0.0),
                            ],
                          ),
                        ),
                      ),
                    ],
                    lineTouchData: LineTouchData(
                      touchTooltipData: LineTouchTooltipData(
                        getTooltipColor: (_) => context.isDark
                            ? AppColors.darkSurfaceMuted
                            : AppColors.textPrimary,
                        tooltipRoundedRadius: 10,
                        tooltipPadding: const EdgeInsets.symmetric(
                            horizontal: 10, vertical: 6),
                        getTooltipItems: (touchedSpots) {
                          return touchedSpots.map((spot) {
                            return LineTooltipItem(
                              '₺${_formatNumber(spot.y)}',
                              const TextStyle(
                                color: Colors.white,
                                fontSize: 13,
                                fontWeight: FontWeight.w700,
                              ),
                            );
                          }).toList();
                        },
                      ),
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildCategoryBreakdown(List<CategorySales> categories) {
    if (categories.isEmpty) {
      return _buildEmptySection(
          'Kategori Dağılımı', 'Kategori verisi bulunamadı');
    }

    final maxRevenue = categories.fold<double>(
        0, (prev, c) => math.max(prev, c.revenue ?? 0));

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const QSectionHeader(title: 'Kategori Dağılımı'),
        QCard(
          padding: const EdgeInsets.all(AppSpacing.md),
          child: Column(
            children: [
              for (var i = 0; i < categories.length; i++) ...[
                if (i > 0) const SizedBox(height: 14),
                _buildCategoryRow(
                  categories[i],
                  _colorForIndex(i),
                  maxRevenue,
                ),
              ],
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildCategoryRow(
      CategorySales cat, Color color, double maxRevenue) {
    final progress = maxRevenue > 0 ? (cat.revenue ?? 0) / maxRevenue : 0.0;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Container(
              width: 10,
              height: 10,
              decoration: BoxDecoration(
                color: color,
                shape: BoxShape.circle,
                boxShadow: [
                  BoxShadow(
                    color: color.withValues(alpha: 0.45),
                    blurRadius: 4,
                  ),
                ],
              ),
            ),
            const SizedBox(width: 8),
            Expanded(
              child: Text(
                cat.categoryName ?? '-',
                style: TextStyle(
                  fontSize: 13.5,
                  fontWeight: FontWeight.w600,
                  color: context.brandTextPrimary,
                  letterSpacing: -0.1,
                ),
              ),
            ),
            Text(
              '₺${_formatNumber(cat.revenue ?? 0)}',
              style: TextStyle(
                fontSize: 14,
                fontWeight: FontWeight.w800,
                color: context.brandTextPrimary,
                letterSpacing: -0.2,
              ),
            ),
            const SizedBox(width: 8),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2),
              decoration: BoxDecoration(
                color: color.withValues(alpha: 0.14),
                borderRadius: BorderRadius.circular(999),
                border: Border.all(
                    color: color.withValues(alpha: 0.28), width: 0.6),
              ),
              child: Text(
                '${cat.orderCount ?? 0}',
                style: TextStyle(
                  fontSize: 11,
                  fontWeight: FontWeight.w700,
                  color: color,
                ),
              ),
            ),
          ],
        ),
        const SizedBox(height: 8),
        ClipRRect(
          borderRadius: BorderRadius.circular(4),
          child: Container(
            height: 8,
            color: context.brandBorder.withValues(alpha: 0.45),
            child: FractionallySizedBox(
              alignment: Alignment.centerLeft,
              widthFactor: progress.clamp(0.02, 1.0),
              child: Container(
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    colors: [color.withValues(alpha: 0.7), color],
                  ),
                ),
              ),
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildTopProducts(List<ProductSale> products) {
    if (products.isEmpty) {
      return _buildEmptySection('En Çok Satan Ürünler', 'Ürün verisi bulunamadı');
    }

    final top5 = products.take(5).toList();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const QSectionHeader(title: 'En Çok Satan Ürünler'),
        QCard(
          padding: EdgeInsets.zero,
          child: Column(
            children: top5.asMap().entries.map((entry) {
              final idx = entry.key;
              final product = entry.value;
              final rankColor = _rankColor(idx);
              return Column(
                children: [
                  Padding(
                    padding: const EdgeInsets.symmetric(
                        horizontal: AppSpacing.md, vertical: 14),
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
                                rankColor.withValues(
                                    alpha: context.isDark ? 0.45 : 0.22),
                                rankColor.withValues(
                                    alpha: context.isDark ? 0.25 : 0.10),
                              ],
                            ),
                            shape: BoxShape.circle,
                            border: Border.all(
                              color: rankColor.withValues(alpha: 0.35),
                              width: 0.8,
                            ),
                            boxShadow: idx < 3
                                ? [
                                    BoxShadow(
                                      color:
                                          rankColor.withValues(alpha: 0.30),
                                      blurRadius: 6,
                                      offset: const Offset(0, 2),
                                    ),
                                  ]
                                : null,
                          ),
                          alignment: Alignment.center,
                          child: Text(
                            '${idx + 1}',
                            style: TextStyle(
                              fontSize: 13,
                              fontWeight: FontWeight.w800,
                              color: rankColor,
                            ),
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Text(
                            product.productName ?? '-',
                            style: TextStyle(
                              fontSize: 14,
                              fontWeight: FontWeight.w600,
                              color: context.brandTextPrimary,
                              letterSpacing: -0.1,
                            ),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                        Column(
                          crossAxisAlignment: CrossAxisAlignment.end,
                          children: [
                            Text(
                              '₺${_formatNumber(product.revenue ?? 0)}',
                              style: TextStyle(
                                fontSize: 14,
                                fontWeight: FontWeight.w800,
                                color: context.brandTextPrimary,
                                letterSpacing: -0.2,
                              ),
                            ),
                            Text(
                              '${product.quantity ?? 0} adet',
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
                  ),
                  if (idx < top5.length - 1)
                    Divider(
                        height: 1,
                        indent: 60,
                        color: context.brandBorder
                            .withValues(alpha: 0.6)),
                ],
              );
            }).toList(),
          ),
        ),
      ],
    );
  }

  /// İlk 3 sırada altın / gümüş / bronz; sonrasında marka mavisi.
  Color _rankColor(int idx) {
    switch (idx) {
      case 0:
        return const Color(0xFFD4A017); // gold
      case 1:
        return const Color(0xFF94A3B8); // silver
      case 2:
        return const Color(0xFFB87333); // bronze
      default:
        return AppColors.primary;
    }
  }

  Widget _buildEmptySection(String title, String subtitle) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        QSectionHeader(title: title),
        QCard(
          padding: const EdgeInsets.symmetric(vertical: 32, horizontal: 16),
          child: Column(
            children: [
              Container(
                width: 56,
                height: 56,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  gradient: RadialGradient(
                    colors: [
                      AppColors.primary.withValues(alpha: 0.14),
                      AppColors.primary.withValues(alpha: 0.0),
                    ],
                  ),
                ),
                alignment: Alignment.center,
                child: Icon(
                  Icons.bar_chart_rounded,
                  size: 28,
                  color: context.brandTextHint,
                ),
              ),
              const SizedBox(height: 12),
              Text(
                subtitle,
                style: TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.w500,
                  color: context.brandTextSecondary,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildShimmer() {
    return Column(
      children: [
        GridView.count(
          crossAxisCount: 2,
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          mainAxisSpacing: AppSpacing.md,
          crossAxisSpacing: AppSpacing.md,
          childAspectRatio: 1.35,
          children: List.generate(
              4, (_) => const QSkeleton(height: 120, radius: AppRadius.lg)),
        ),
        const SizedBox(height: AppSpacing.lg),
        const QSkeleton(height: 240, radius: AppRadius.lg),
        const SizedBox(height: AppSpacing.lg),
        const QSkeleton(height: 180, radius: AppRadius.lg),
      ],
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

  String _formatCompact(double value) {
    if (value >= 1000000) return '${(value / 1000000).toStringAsFixed(1)}M';
    if (value >= 1000) return '${(value / 1000).toStringAsFixed(0)}K';
    return value.toStringAsFixed(0);
  }
}

class _PeriodOption {
  final String label;
  final String value;

  const _PeriodOption(this.label, this.value);
}
