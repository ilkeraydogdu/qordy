import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:get_it/get_it.dart';

import '../../features/auth/cubit/auth_cubit.dart';
import '../../features/auth/data/auth_repository.dart';
import '../../features/security/data/biometric_service.dart';
import '../../features/security/pattern_unlock_service.dart';
import '../../features/security/quick_unlock_service.dart';
import '../../features/dashboard/cubit/dashboard_cubit.dart';
import '../../features/dashboard/data/dashboard_api.dart';
import '../../features/dashboard/data/dashboard_repository.dart';
import '../../features/manager/cubit/analytics_cubit.dart';
import '../../features/manager/cubit/approvals_cubit.dart';
import '../../features/manager/cubit/expenses_cubit.dart';
import '../../features/manager/cubit/menu_cubit.dart';
import '../../features/manager/cubit/reservations_cubit.dart';
import '../../features/manager/cubit/settings_cubit.dart';
import '../../features/manager/cubit/staff_cubit.dart';
import '../../features/manager/data/manager_api.dart';
import '../../features/manager/data/manager_repository.dart';
import '../../features/notifications/cubit/notifications_cubit.dart';
import '../../features/notifications/data/notifications_api.dart';
import '../../features/notifications/data/notifications_repository.dart';
import '../../features/packages/cubit/packages_cubit.dart';
import '../../features/packages/data/packages_api.dart';
import '../../features/packages/data/packages_repository.dart';
import '../../features/subscription/cubit/subscription_cubit.dart';
import '../../features/admin/data/admin_api.dart';
import '../network/api_client.dart';
import '../network/auth_interceptor.dart';
import '../security/secure_storage.dart';
import '../theme/theme_cubit.dart';

final getIt = GetIt.instance;

Future<void> setupDependencies() async {
 // ── Storage ──
 // Single hardened FlutterSecureStorage instance — see
 // `core/security/secure_storage.dart` for why we can't just use the
 // default constructor (EncryptedSharedPreferences + Keychain
 // accessibility class tuning).
 getIt.registerLazySingleton<FlutterSecureStorage>(() => secureStorage);

 // ── Theme (light/dark/system, persisted via SharedPreferences) ──
 // Registered as a SINGLETON — every screen that toggles the theme
 // drives the same Cubit so MaterialApp rebuilds globally.
 getIt.registerLazySingleton<ThemeCubit>(() => ThemeCubit());

 // ── Auth Interceptor ──
 getIt.registerLazySingleton<AuthInterceptor>(
 () => AuthInterceptor(storage: getIt<FlutterSecureStorage>()),
 );

 // ── ApiClient (single Dio instance + typed responses) ──
 // All features share the same underlying client. Registering Dio as
 // an alias to `ApiClient.dio` guarantees there is exactly one
 // interceptor chain (auth, logs, future refresh).
 getIt.registerLazySingleton<ApiClient>(
 () => ApiClient(authInterceptor: getIt<AuthInterceptor>()),
 );
 getIt.registerLazySingleton<Dio>(() => getIt<ApiClient>().dio);

 // ── Security / Quick Unlock ──
 // Thin service on top of secureStorage that gates the persisted session
 // behind a 4–8 digit PIN. The actual bearer token stays in the
 // OS-backed secure store — the PIN is just a UX unlock gate.
 getIt.registerLazySingleton<QuickUnlockService>(() => QuickUnlockService());

 // ── Pattern unlock (3x3 swipe pattern alternative to PIN) ──
 // PIN alternatifi olarak desen de sunuyoruz. PIN gibi hash+salt
 // SHA-256 ile secureStorage'da.
 getIt.registerLazySingleton<PatternUnlockService>(
 () => PatternUnlockService());

 // ── Biometric (fingerprint / FaceID) ──
 // Cihaz seviyesi biyometriyi PIN hızlı girişe bağlar. Parola saklamaz,
 // sadece kullanıcı tercihini (enabled) + sistem prompt'unu yönetir.
 getIt.registerLazySingleton<BiometricService>(() => BiometricService());

 // ── Auth ──
 getIt.registerFactory<AuthCubit>(
 () => AuthCubit(
 repository: AuthRepository(),
 quickUnlock: getIt<QuickUnlockService>(),
 pattern: getIt<PatternUnlockService>(),
 biometrics: getIt<BiometricService>(),
 ),
 );

 // ── Dashboard ──
 getIt.registerLazySingleton<DashboardApi>(() => DashboardApi());
 getIt.registerLazySingleton<DashboardRepository>(
 () => DashboardRepository(api: getIt<DashboardApi>()),
 );
 getIt.registerFactory<DashboardCubit>(
 () => DashboardCubit(repository: getIt<DashboardRepository>()),
 );

 // ── Notifications ──
 getIt.registerLazySingleton<NotificationsApi>(() => NotificationsApi());
 getIt.registerLazySingleton<NotificationsRepository>(
 () => NotificationsRepository(api: getIt<NotificationsApi>()),
 );
 getIt.registerFactory<NotificationsCubit>(
 () => NotificationsCubit(repository: getIt<NotificationsRepository>()),
 );

 // ── Manager ──
 getIt.registerLazySingleton<ManagerApi>(
 () => ManagerApi(apiClient: getIt<ApiClient>()),
 );
 getIt.registerLazySingleton<ManagerRepository>(
 () => ManagerRepository(api: getIt<ManagerApi>()),
 );
 getIt.registerFactory<AnalyticsCubit>(
 () => AnalyticsCubit(repository: getIt<ManagerRepository>()),
 );
 getIt.registerFactory<StaffCubit>(
 () => StaffCubit(repository: getIt<ManagerRepository>()),
 );
 getIt.registerFactory<MenuManagementCubit>(
 () => MenuManagementCubit(repository: getIt<ManagerRepository>()),
 );
 getIt.registerFactory<ExpensesCubit>(
 () => ExpensesCubit(repository: getIt<ManagerRepository>()),
 );
 getIt.registerFactory<ReservationsCubit>(
 () => ReservationsCubit(repository: getIt<ManagerRepository>()),
 );
 getIt.registerFactory<ApprovalsCubit>(
 () => ApprovalsCubit(repository: getIt<ManagerRepository>()),
 );
 getIt.registerFactory<SettingsCubit>(
 () => SettingsCubit(repository: getIt<ManagerRepository>()),
 );

 // ── Packages ──
 getIt.registerLazySingleton<PackagesApi>(
 () => PackagesApi(apiClient: getIt<ApiClient>()),
 );
 getIt.registerLazySingleton<PackagesRepository>(
 () => PackagesRepository(api: getIt<PackagesApi>()),
 );
 getIt.registerFactory<PackagesCubit>(
 () => PackagesCubit(repository: getIt<PackagesRepository>()),
 );

 // ── Subscription (trial/grace/active faz yönetimi) ──
 // SINGLETON — tüm uygulama aynı abonelik durumunu paylaşmalı.
 getIt.registerLazySingleton<SubscriptionCubit>(
 () => SubscriptionCubit(repository: getIt<PackagesRepository>()),
 );

 // ── Admin (printers, queue, receipt templates, finance, etc.) ──
 getIt.registerLazySingleton<AdminApi>(
 () => AdminApi(apiClient: getIt<ApiClient>()),
 );
}
