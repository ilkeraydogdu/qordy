import 'dart:async';

import 'package:dio/dio.dart';

import '../constants/api_constants.dart';
import '../error/exceptions.dart';

/// Client-side rate limit — token bucket.
///
/// Login gibi hassas endpoint'ler için [ApiConstants.loginMaxPerMinute],
/// diğer endpoint'ler için [ApiConstants.defaultMaxPerMinute].
class RateLimitInterceptor extends Interceptor {
 RateLimitInterceptor();

 final Map<String, _Bucket> _buckets = <String, _Bucket>{};

 @override
 Future<void> onRequest(
 RequestOptions options,
 RequestInterceptorHandler handler,
 ) async {
 final key = _bucketKey(options);
 final max = _maxForPath(options.path);

 final bucket = _buckets.putIfAbsent(
 key,
 () => _Bucket(capacity: max.toDouble(), refillPerSecond: max / 60.0),
 );

 if (!bucket.tryConsume(1.0)) {
 final retryAfter = bucket.timeToRefill(1.0);
 handler.reject(
 DioException(
 requestOptions: options,
 type: DioExceptionType.cancel,
 error: RateLimitException(retryAfter: retryAfter),
 message: 'Client-side rate limit exceeded',
 ),
 );
 return;
 }
 handler.next(options);
 }

 String _bucketKey(RequestOptions options) {
 final host = options.uri.host;
 final path = _normalizePath(options.path);
 return '$host$path';
 }

 String _normalizePath(String path) {
 // Sadece ilk 2 path segment'i tut (parametreleri grupla).
 final parts = path.split('/').where((p) => p.isNotEmpty).toList();
 if (parts.length <= 2) return '/${parts.join('/')}';
 return '/${parts.sublist(0, 2).join('/')}';
 }

 int _maxForPath(String path) {
 if (path.contains('/manager/login') || path.contains('/auth/2fa/')) {
 return ApiConstants.loginMaxPerMinute;
 }
 return ApiConstants.defaultMaxPerMinute;
 }
}

class _Bucket {
 _Bucket({required this.capacity, required this.refillPerSecond})
 : _tokens = capacity.toDouble();

 final double capacity;
 final double refillPerSecond;
 double _tokens;
 DateTime _last = DateTime.now();

 bool tryConsume(double amount) {
 _refill();
 if (_tokens >= amount) {
 _tokens -= amount;
 return true;
 }
 return false;
 }

 Duration timeToRefill(double amount) {
 if (refillPerSecond <= 0) return const Duration(seconds: 60);
 final needed = amount - _tokens;
 final seconds = needed / refillPerSecond;
 return Duration(milliseconds: (seconds * 1000).round());
 }

 void _refill() {
 final now = DateTime.now();
 final delta = now.difference(_last).inMilliseconds / 1000.0;
 _tokens = (_tokens + delta * refillPerSecond).clamp(0.0, capacity);
 _last = now;
 }
}
