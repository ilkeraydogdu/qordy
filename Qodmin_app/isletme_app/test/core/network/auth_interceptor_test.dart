import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:isletme_yonetici/core/network/auth_interceptor.dart';
import 'package:isletme_yonetici/core/storage/secure_storage.dart';
import 'package:mocktail/mocktail.dart';

class _MockStorage extends Mock implements SecureStorageService {}

void main() {
 late _MockStorage storage;
 late Dio client;
 late Dio refreshDio;
 late AuthInterceptor interceptor;

 setUp(() {
 storage = _MockStorage();
 refreshDio = Dio(BaseOptions(baseUrl: 'https://test.local'));
 interceptor = AuthInterceptor(
 storage: storage,
 refreshDio: refreshDio,
 );
 client = Dio(BaseOptions(baseUrl: 'https://test.local'));
 client.interceptors.add(interceptor);
 });

 group('AuthInterceptor.onRequest — token injection', () {
 test('login endpointine token eklemez', () async {
 when(() => storage.readAccessToken())
 .thenAnswer((_) async => 'token-123');

 RequestOptions? captured;
 client.interceptors.add(InterceptorsWrapper(
 onRequest: (opts, handler) {
 captured = opts;
 handler.next(opts);
 },
 ));

 try {
 await client.post('/api/mobile/manager/login', data: {});
 } catch (_) {
 // Network error bekleniyor, önemli değil.
 }

 expect(captured, isNotNull);
 expect(captured!.headers.containsKey('Authorization'), isFalse);
 });

 test('normal endpointte bearer token ekler', () async {
 when(() => storage.readAccessToken())
 .thenAnswer((_) async => 'token-xyz');

 RequestOptions? captured;
 client.interceptors.add(InterceptorsWrapper(
 onRequest: (opts, handler) {
 captured = opts;
 handler.next(opts);
 },
 ));

 try {
 await client.get('/api/mobile/orders');
 } catch (_) {}

 expect(captured, isNotNull);
 expect(captured!.headers['Authorization'], equals('Bearer token-xyz'));
 });

 test('token yoksa Authorization header eklenmez', () async {
 when(() => storage.readAccessToken()).thenAnswer((_) async => null);

 RequestOptions? captured;
 client.interceptors.add(InterceptorsWrapper(
 onRequest: (opts, handler) {
 captured = opts;
 handler.next(opts);
 },
 ));

 try {
 await client.get('/api/mobile/orders');
 } catch (_) {}

 expect(captured, isNotNull);
 expect(captured!.headers.containsKey('Authorization'), isFalse);
 });

 test('refresh-token endpointine token EKLEMEZ (mevcut token olsa bile)', () async {
 when(() => storage.readAccessToken())
 .thenAnswer((_) async => 'should-not-be-used');

 RequestOptions? captured;
 client.interceptors.add(InterceptorsWrapper(
 onRequest: (opts, handler) {
 captured = opts;
 handler.next(opts);
 },
 ));

 try {
 await client.post('/api/mobile/refresh-token', data: {});
 } catch (_) {}

 expect(captured, isNotNull);
 expect(captured!.headers.containsKey('Authorization'), isFalse);
 });
 });
}
