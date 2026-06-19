import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// User's selected theme preference. Persisted via [SharedPreferences] so
/// the choice survives app relaunches and hot restarts.
enum AppThemeMode {
  system,
  light,
  dark;

  ThemeMode get materialMode {
    switch (this) {
      case AppThemeMode.system:
        return ThemeMode.system;
      case AppThemeMode.light:
        return ThemeMode.light;
      case AppThemeMode.dark:
        return ThemeMode.dark;
    }
  }

  static AppThemeMode fromKey(String? key) {
    switch (key) {
      case 'light':
        return AppThemeMode.light;
      case 'dark':
        return AppThemeMode.dark;
      case 'system':
      default:
        return AppThemeMode.system;
    }
  }

  String get key => name;
}

/// Owns the app-wide theme preference. A single instance is registered as
/// a singleton in `injection.dart` so every screen that needs to toggle
/// the theme uses the same Cubit (and therefore the MaterialApp rebuild
/// is global).
class ThemeCubit extends Cubit<AppThemeMode> {
  ThemeCubit() : super(AppThemeMode.system);

  static const _prefKey = 'qordy.theme_mode';

  Future<void> load() async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString(_prefKey);
    emit(AppThemeMode.fromKey(raw));
  }

  Future<void> setMode(AppThemeMode mode) async {
    if (mode == state) return;
    emit(mode);
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_prefKey, mode.key);
  }
}
