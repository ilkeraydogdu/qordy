import 'dart:developer' as developer;

import 'package:flutter/foundation.dart';

/// Merkezi logger — release'te debug print YOK, PII maskeleme aktif.
class AppLogger {
 AppLogger._();

 /// Log seviyeleri.
 static const int _levelDebug = 0;
 static const int _levelInfo = 1;
 static const int _levelWarn = 2;
 static const int _levelError = 3;

 /// Release'te sadece warn+error gösterilir.
 static int get _minLevel => kReleaseMode ? _levelWarn : _levelDebug;

 static void d(String tag, String message) {
 _log(_levelDebug, tag, message);
 }

 static void i(String tag, String message) {
 _log(_levelInfo, tag, message);
 }

 static void w(String tag, String message, [Object? error]) {
 _log(_levelWarn, tag, message, error);
 }

 static void e(String tag, String message, [Object? error, StackTrace? stack]) {
 _log(_levelError, tag, message, error, stack);
 }

 static void _log(
 int level,
 String tag,
 String message, [
 Object? error,
 StackTrace? stack,
 ]) {
 if (level < _minLevel) return;
 final safe = _maskSensitive(message);
 final safeError = error == null ? null : _maskSensitive(error.toString());
 developer.log(
 safe,
 name: tag,
 error: safeError,
 stackTrace: stack,
 level: 800 + level * 100,
 );
 }

 /// Token, e-posta, telefon, parola gibi hassas verileri maskele.
 static String _maskSensitive(String input) {
 // Bearer token: "Bearer eyJ..." → "Bearer ***"
 var s = input.replaceAllMapped(
 RegExp(r'Bearer\s+[A-Za-z0-9\-_.]+'),
 (m) => 'Bearer ***',
 );
 // E-posta: user@example.com → u***@e***.com
 s = s.replaceAllMapped(
 RegExp(r'([A-Za-z0-9._%+\-])[A-Za-z0-9._%+\-]*(@[A-Za-z0-9.\-]+\.[A-Za-z]{2,})'),
 (m) => '${m[1]}***${m[2]}',
 );
 // Parola alanları: "password":"xxx" → "password":"***"
 s = s.replaceAllMapped(
 RegExp(r'("(?:password|passwd|secret|token|api_key)"\s*:\s*")[^"]+"',
 caseSensitive: false),
 (m) => '${m[1]}***"',
 );
 return s;
 }
}
