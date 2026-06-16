import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:isletme_yonetici/core/storage/secure_storage.dart';
import 'package:mocktail/mocktail.dart';

class _MockSecureStorage extends Mock implements FlutterSecureStorage {}

void main() {
 late _MockSecureStorage mockStorage;
 late SecureStorageService service;

 setUp(() {
 mockStorage = _MockSecureStorage();
 service = SecureStorageService(storage: mockStorage);
 });

 group('SecureStorageService', () {
 test('writeAccessToken delegates to storage', () async {
 when(() => mockStorage.write(
 key: any(named: 'key'),
 value: any(named: 'value'),
 )).thenAnswer((_) async {});

 await service.writeAccessToken('abc123');

 verify(() => mockStorage.write(
 key: 'auth.access_token',
 value: 'abc123',
 )).called(1);
 });

 test('readAccessToken returns storage value', () async {
 when(() => mockStorage.read(key: 'auth.access_token'))
 .thenAnswer((_) async => 'token-x');

 final result = await service.readAccessToken();

 expect(result, equals('token-x'));
 });

 test('writeRefreshToken writes to correct key', () async {
 when(() => mockStorage.write(
 key: any(named: 'key'),
 value: any(named: 'value'),
 )).thenAnswer((_) async {});

 await service.writeRefreshToken('refresh-y');

 verify(() => mockStorage.write(
 key: 'auth.refresh_token',
 value: 'refresh-y',
 )).called(1);
 });

 test('clearAuth removes all auth-related keys', () async {
 when(() => mockStorage.delete(key: any(named: 'key')))
 .thenAnswer((_) async {});

 await service.clearAuth();

 final captured = verify(() => mockStorage.delete(
 key: captureAny(named: 'key'),
 )).captured;
 final keys = captured.cast<String>();
 expect(keys, contains('auth.access_token'));
 expect(keys, contains('auth.refresh_token'));
 expect(keys, contains('auth.user_id'));
 expect(keys, contains('auth.user_email'));
 });

 test('wipe calls deleteAll', () async {
 when(() => mockStorage.deleteAll()).thenAnswer((_) async {});

 await service.wipe();

 verify(() => mockStorage.deleteAll()).called(1);
 });
 });
}
