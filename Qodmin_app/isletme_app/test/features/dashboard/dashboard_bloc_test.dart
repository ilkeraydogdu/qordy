import 'package:bloc_test/bloc_test.dart';
import 'package:dartz/dartz.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:isletme_yonetici/core/error/failures.dart';
import 'package:isletme_yonetici/core/usecase/usecase.dart';
import 'package:isletme_yonetici/features/dashboard/domain/entities/dashboard_summary.dart';
import 'package:isletme_yonetici/features/dashboard/domain/usecases/get_dashboard_summary_usecase.dart';
import 'package:isletme_yonetici/features/dashboard/presentation/bloc/dashboard_bloc.dart';
import 'package:isletme_yonetici/features/dashboard/presentation/bloc/dashboard_event.dart';
import 'package:isletme_yonetici/features/dashboard/presentation/bloc/dashboard_state.dart';
import 'package:mocktail/mocktail.dart';

class _MockGetSummary extends Mock implements GetDashboardSummaryUseCase {}

class _FakeNoParams extends Fake implements NoParams {}

void main() {
 late _MockGetSummary mockUC;
 late DashboardBloc bloc;

 final tSummary = DashboardSummary(
 todayOrders: 42,
 todayRevenue: 5230.50,
 activeOrders: 7,
 occupiedTables: 8,
 totalTables: 12,
 pendingKitchen: 3,
 avgOrderValue: 124.50,
 topSellingItems: const [
 TopItem(id: '1', name: 'Köfte Tabağı', soldCount: 25, revenue: 1750),
 ],
 revenueTrend: const [
 RevenuePoint(hour: 12, amount: 800),
 RevenuePoint(hour: 13, amount: 1200),
 ],
 );

 setUpAll(() {
 registerFallbackValue(_FakeNoParams());
 });

 setUp(() {
 mockUC = _MockGetSummary();
 bloc = DashboardBloc(getSummaryUseCase: mockUC);
 });

 group('DashboardBloc', () {
 blocTest<DashboardBloc, DashboardState>(
 'load → loading → loaded',
 build: () {
 when(() => mockUC(any())).thenAnswer((_) async => Right(tSummary));
 return bloc;
 },
 act: (b) => b.add(const DashboardLoadRequested()),
 expect: () => [const DashboardLoading(), DashboardLoaded(tSummary)],
 );

 blocTest<DashboardBloc, DashboardState>(
 'refresh → loading → loaded (aynı davranış)',
 build: () {
 when(() => mockUC(any())).thenAnswer((_) async => Right(tSummary));
 return bloc;
 },
 act: (b) => b.add(const DashboardRefreshRequested()),
 expect: () => [const DashboardLoading(), DashboardLoaded(tSummary)],
 );

 blocTest<DashboardBloc, DashboardState>(
 'hata → loading → error',
 build: () {
 when(() => mockUC(any())).thenAnswer(
 (_) async => const Left(NetworkFailure(message: 'Ağ hatası')),
 );
 return bloc;
 },
 act: (b) => b.add(const DashboardLoadRequested()),
 expect: () => [
 const DashboardLoading(),
 const DashboardError('Ağ hatası'),
 ],
 );

 test('tableOccupancyRate doğru hesaplar', () {
 expect(tSummary.tableOccupancyRate, closeTo(66.67, 0.01));
 });
 });
}
