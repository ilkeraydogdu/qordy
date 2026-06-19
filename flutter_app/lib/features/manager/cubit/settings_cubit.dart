import 'package:flutter_bloc/flutter_bloc.dart';

import '../data/manager_repository.dart';
import 'settings_state.dart';

class SettingsCubit extends Cubit<SettingsState> {
  final ManagerRepository _repository;
  SettingsLoaded? _currentSettings;

  SettingsCubit({required ManagerRepository repository})
      : _repository = repository,
        super(const SettingsInitial());

  Future<void> loadSettings() async {
    emit(const SettingsLoading());
    try {
      final response = await _repository.getSettings();
      if (response.isSuccess && response.data != null) {
        final data = response.data!;
        // Backend can nest business info under `business` and system
        // settings under `settings`; also tolerate flat/legacy payloads.
        final business = (data['business'] is Map)
            ? Map<String, dynamic>.from(data['business'] as Map)
            : <String, dynamic>{};
        final settings = (data['settings'] is Map)
            ? Map<String, dynamic>.from(data['settings'] as Map)
            : <String, dynamic>{};

        String pick(String snake, String camel) {
          final v = business[snake] ??
              business[camel] ??
              settings[snake] ??
              settings[camel] ??
              data[snake] ??
              data[camel];
          return v?.toString() ?? '';
        }

        bool pickBool(String snake, String camel) {
          final v = settings[snake] ??
              settings[camel] ??
              data[snake] ??
              data[camel];
          if (v is bool) return v;
          if (v is num) return v != 0;
          if (v is String) return v == '1' || v.toLowerCase() == 'true';
          return false;
        }

        _currentSettings = SettingsLoaded(
          businessName: pick('company_name', 'businessName').isNotEmpty
              ? pick('company_name', 'businessName')
              : pick('name', 'name'),
          address: pick('address', 'address'),
          workingHoursStart: pick('working_hours_start', 'workingHoursStart')
                  .isNotEmpty
              ? pick('working_hours_start', 'workingHoursStart')
              : '09:00',
          workingHoursEnd: pick('working_hours_end', 'workingHoursEnd')
                  .isNotEmpty
              ? pick('working_hours_end', 'workingHoursEnd')
              : '23:00',
          approvalRequired:
              pickBool('approval_required', 'approvalRequired'),
          approvalRole: pick('approval_role', 'approvalRole').isNotEmpty
              ? pick('approval_role', 'approvalRole')
              : 'manager',
          wifiName: pick('wifi_name', 'wifiName'),
          wifiPassword: pick('wifi_password', 'wifiPassword'),
          showWifiToCustomer:
              pickBool('show_wifi_to_customer', 'showWifiToCustomer'),
          currency: pick('currency', 'currency').isNotEmpty
              ? pick('currency', 'currency')
              : 'TRY',
        );
        emit(_currentSettings!);
      } else {
        emit(SettingsError(response.error ?? 'Ayarlar yüklenemedi'));
      }
    } catch (e) {
      emit(SettingsError(e.toString()));
    }
  }

  void updateField(SettingsLoaded Function(SettingsLoaded current) updater) {
    if (_currentSettings != null) {
      _currentSettings = updater(_currentSettings!);
      emit(_currentSettings!);
    }
  }

  Future<void> saveSettings() async {
    if (_currentSettings == null) return;
    emit(const SettingsSaving());
    try {
      final response = await _repository.updateSettings(
        _currentSettings!.toJson(),
      );
      if (response.isSuccess) {
        emit(const SettingsSaved());
        emit(_currentSettings!);
      } else {
        emit(SettingsError(response.error ?? 'Ayarlar kaydedilemedi'));
        emit(_currentSettings!);
      }
    } catch (e) {
      emit(SettingsError(e.toString()));
      if (_currentSettings != null) emit(_currentSettings!);
    }
  }
}
