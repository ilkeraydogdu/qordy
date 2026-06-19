import 'package:equatable/equatable.dart';

abstract class SettingsState extends Equatable {
  const SettingsState();

  @override
  List<Object?> get props => [];
}

class SettingsInitial extends SettingsState {
  const SettingsInitial();
}

class SettingsLoading extends SettingsState {
  const SettingsLoading();
}

class SettingsLoaded extends SettingsState {
  final String businessName;
  final String address;
  final String workingHoursStart;
  final String workingHoursEnd;
  final bool approvalRequired;
  final String approvalRole;
  final String wifiName;
  final String wifiPassword;
  final bool showWifiToCustomer;
  final String currency;

  const SettingsLoaded({
    this.businessName = '',
    this.address = '',
    this.workingHoursStart = '09:00',
    this.workingHoursEnd = '23:00',
    this.approvalRequired = false,
    this.approvalRole = 'manager',
    this.wifiName = '',
    this.wifiPassword = '',
    this.showWifiToCustomer = false,
    this.currency = 'TRY',
  });

  SettingsLoaded copyWith({
    String? businessName,
    String? address,
    String? workingHoursStart,
    String? workingHoursEnd,
    bool? approvalRequired,
    String? approvalRole,
    String? wifiName,
    String? wifiPassword,
    bool? showWifiToCustomer,
    String? currency,
  }) {
    return SettingsLoaded(
      businessName: businessName ?? this.businessName,
      address: address ?? this.address,
      workingHoursStart: workingHoursStart ?? this.workingHoursStart,
      workingHoursEnd: workingHoursEnd ?? this.workingHoursEnd,
      approvalRequired: approvalRequired ?? this.approvalRequired,
      approvalRole: approvalRole ?? this.approvalRole,
      wifiName: wifiName ?? this.wifiName,
      wifiPassword: wifiPassword ?? this.wifiPassword,
      showWifiToCustomer: showWifiToCustomer ?? this.showWifiToCustomer,
      currency: currency ?? this.currency,
    );
  }

  Map<String, dynamic> toJson() => {
        'company_name': businessName,
        'address': address,
        'working_hours_start': workingHoursStart,
        'working_hours_end': workingHoursEnd,
        'approval_required': approvalRequired ? 1 : 0,
        'approval_role': approvalRole,
        'wifi_name': wifiName,
        'wifi_password': wifiPassword,
        'show_wifi_to_customer': showWifiToCustomer ? 1 : 0,
        'currency': currency,
      };

  @override
  List<Object?> get props => [
        businessName,
        address,
        workingHoursStart,
        workingHoursEnd,
        approvalRequired,
        approvalRole,
        wifiName,
        wifiPassword,
        showWifiToCustomer,
        currency,
      ];
}

class SettingsSaving extends SettingsState {
  const SettingsSaving();
}

class SettingsSaved extends SettingsState {
  const SettingsSaved();
}

class SettingsError extends SettingsState {
  final String message;

  const SettingsError(this.message);

  @override
  List<Object?> get props => [message];
}
