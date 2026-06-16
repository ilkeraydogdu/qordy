import 'package:dio/dio.dart';
import 'package:flutter_jailbreak_detection/flutter_jailbreak_detection.dart';
import 'package:get_it/get_it.dart';

import '../../core/error/failures.dart';
import '../../core/network/api_client.dart';
import '../../core/network/auth_interceptor.dart';
import '../../core/network/rate_limit_interceptor.dart';
import '../../core/storage/secure_storage.dart';
import '../../features/auth/data/datasources/auth_remote_datasource.dart';
import '../../features/auth/data/repositories/auth_repository_impl.dart';
import '../../features/auth/domain/repositories/auth_repository.dart';
import '../../features/auth/domain/usecases/login_usecase.dart';
import '../../features/auth/domain/usecases/logout_usecase.dart';
import '../../features/auth/domain/usecases/verify_2fa_usecase.dart';
import '../../features/auth/presentation/bloc/auth_bloc.dart';
import '../../features/dashboard/data/datasources/dashboard_remote_datasource.dart';
import '../../features/dashboard/data/repositories/dashboard_repository_impl.dart';
import '../../features/dashboard/domain/repositories/dashboard_repository.dart';
import '../../features/dashboard/domain/usecases/get_dashboard_summary_usecase.dart';

/// Global DI container.
final GetIt sl = GetIt.instance;

/// Tüm servisleri kayıt et.
Future<void> setupInjector() async {
 // === Core ===
 sl.registerLazySingleton<SecureStorageService>(() => SecureStorageService());

 final apiClient = ApiClient();
 sl.registerSingleton<ApiClient>(apiClient);

 // Refresh için ayrı Dio — interceptor döngüsünü önler.
 final refreshDio = Dio();
 sl.registerSingleton<Dio>(refreshDio, instanceName: 'refreshDio');

 // Interceptor'ları bağla.
 final storage = sl<SecureStorageService>();
 apiClient.addInterceptors([
 RateLimitInterceptor(),
 AuthInterceptor(
 storage: storage,
 refreshDio: refreshDio,
 onUnauthenticated: () async {
 // Global logout — auth state'i sıfırla, storage temizle.
 await storage.clearAuth();
 // Auth bloc dışarıdan dinlenebilir; burada router redirect çalışır.
 },
 ),
 ]);

 // === Features ===
 // Auth
 sl.registerLazySingleton<AuthRemoteDataSource>(
 () => AuthRemoteDataSourceImpl(
 dio: apiClient.raw,
 baseUrl: apiClient.baseUrl,
 ),
 );
 sl.registerLazySingleton<AuthRepository>(
 () => AuthRepositoryImpl(
 remote: sl(),
 storage: sl(),
 onJailbroken: _checkJailbreak,
 ),
 );
 sl.registerFactory(() => LoginUseCase(sl()));
 sl.registerFactory(() => Verify2FAUseCase(sl()));
 sl.registerFactory(() => LogoutUseCase(sl()));
 sl.registerFactory(() => AuthBloc(
 loginUseCase: sl(),
 verify2FAUseCase: sl(),
 logoutUseCase: sl(),
 ));

 // Dashboard
 sl.registerLazySingleton<DashboardRemoteDataSource>(
 () => DashboardRemoteDataSourceImpl(dio: apiClient.raw),
 );
 sl.registerLazySingleton<DashboardRepository>(
 () => DashboardRepositoryImpl(remote: sl()),
 );
 sl.registerFactory(() => GetDashboardSummaryUseCase(sl()));
}

/// Jailbreak kontrolü — production'da cihazı reddet.
Future<Failure?> _checkJailbreak() async {
 try {
 final compromised = await FlutterJailbreakDetection.jailbroken;
 final developerMode = await FlutterJailbreakDetection.developerMode;
 if (compromised || developerMode) {
 return const JailbreakFailure();
 }
 return null;
 } catch (_) {
 // Platform desteklemiyor → güvenli tarafta kal.
 return null;
 }
}
