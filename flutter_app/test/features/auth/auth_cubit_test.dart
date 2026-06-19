import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:qordy_app/features/auth/cubit/auth_cubit.dart';
import 'package:qordy_app/features/auth/cubit/auth_state.dart';
import 'package:qordy_app/features/auth/data/auth_api.dart';
import 'package:qordy_app/features/auth/data/auth_repository.dart';
import 'package:qordy_app/features/security/quick_unlock_service.dart';

void main() {
  group('AuthCubit.validateSubdomain', () {
    test('success emits SubdomainValidated with canonical subdomain', () async {
      final dio = Dio(BaseOptions(baseUrl: 'https://test.qordy.local'));
      dio.interceptors.add(
        InterceptorsWrapper(
          onRequest: (options, handler) {
            handler.resolve(
              Response<Map<String, dynamic>>(
                requestOptions: options,
                data: {
                  'success': true,
                  'data': {
                    'business': {
                      'name': 'Cadde Cafe',
                      'subdomain': 'caddecafe',
                    },
                  },
                },
              ),
            );
          },
        ),
      );
      final cubit = AuthCubit(
        repository: AuthRepository(api: AuthApi(dio: dio)),
        quickUnlock: QuickUnlockService(),
      );
      await cubit.validateSubdomain('Cadde Cafe');
      expect(cubit.state, isA<SubdomainValidated>());
      final v = cubit.state as SubdomainValidated;
      expect(v.subdomain, 'caddecafe');
      expect(v.businessName, 'Cadde Cafe');
      await cubit.close();
    });

    test('missing subdomain in response emits AuthError', () async {
      final dio = Dio(BaseOptions(baseUrl: 'https://test.qordy.local'));
      dio.interceptors.add(
        InterceptorsWrapper(
          onRequest: (options, handler) {
            handler.resolve(
              Response<Map<String, dynamic>>(
                requestOptions: options,
                data: {
                  'success': true,
                  'data': {
                    'business': {'name': 'X'},
                  },
                },
              ),
            );
          },
        ),
      );
      final cubit = AuthCubit(
        repository: AuthRepository(api: AuthApi(dio: dio)),
        quickUnlock: QuickUnlockService(),
      );
      await cubit.validateSubdomain('x');
      expect(cubit.state, isA<AuthError>());
      await cubit.close();
    });
  });
}
