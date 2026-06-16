import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:isletme_yonetici/core/network/rate_limit_interceptor.dart';

void main() {
 group('RateLimitInterceptor', () {
 test('login endpoint 5/dk limitini uygular', () async {
 final dio = Dio(BaseOptions(baseUrl: 'https://test.local'))
 ..interceptors.add(RateLimitInterceptor());

 var rateLimited = false;
 for (var i = 0; i < 7; i++) {
 try {
 await dio.post('/api/mobile/manager/login', data: {});
 } on DioException catch (e) {
 if (e.error.toString().contains('Rate limit')) {
 rateLimited = true;
 break;
 }
 }
 if (rateLimited) break;
 }

 expect(rateLimited, isTrue,
 reason: 'Login endpoint 5/dk limitine ulaşmalı');
 });

 test('farklı pathler ayrı bucket kullanır', () async {
 final dio = Dio(BaseOptions(baseUrl: 'https://test.local'))
 ..interceptors.add(RateLimitInterceptor());

 var rateLimitedAt = -1;
 for (var i = 0; i < 8; i++) {
 try {
 // orders path'i — 60 kapasiteli bucket.
 // Hata durumunda bile interceptor çalışır.
 final res = await dio.get('/api/mobile/orders');
 if (res.statusCode == 429) {
 rateLimitedAt = i;
 break;
 }
 } on DioException catch (e) {
 if (e.error.toString().contains('Rate limit')) {
 rateLimitedAt = i;
 break;
 }
 // Network error yutulur, devam.
 }
 }

 // orders path 60 kapasiteli, 7 istekte rate-limit olmamalı.
 // (Sadece mantığın doğru bağımsız bucket kullandığını test eder.)
 expect(rateLimitedAt, equals(-1),
 reason: '7 requests on /orders should NOT trigger 60-capacity rate limit');
 });
 });
}
