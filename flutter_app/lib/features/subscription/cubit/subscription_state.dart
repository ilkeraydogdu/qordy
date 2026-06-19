import 'package:equatable/equatable.dart';
import 'package:qordy_app/models/package_model.dart';

class SubscriptionState extends Equatable {
  final bool isLoading;
  final Subscription? subscription;
  final String? error;

  /// Tek bir oturum içinde Satın Al hatırlatıcısını en fazla günde bir göster
  final DateTime? lastUpsellReminderAt;

  const SubscriptionState({
    this.isLoading = false,
    this.subscription,
    this.error,
    this.lastUpsellReminderAt,
  });

  SubscriptionPhase get phase =>
      subscription?.phase ?? SubscriptionPhase.none;

  int get daysLeft => subscription?.daysLeft ?? 0;
  int get graceDaysLeft => subscription?.graceDaysLeft ?? 0;
  bool get readOnly => subscription?.readOnly ?? false;
  bool get isTrial => subscription?.isTrialPhase ?? false;
  bool get isGrace => subscription?.isGrace ?? false;
  bool get isSuspended => subscription?.isSuspended ?? false;
  bool get canMutate => subscription?.canMutate ?? true;
  bool get hasAccess => subscription?.hasActiveAccess ?? false;

  SubscriptionState copyWith({
    bool? isLoading,
    Subscription? subscription,
    String? error,
    DateTime? lastUpsellReminderAt,
    bool clearError = false,
    bool clearSubscription = false,
  }) {
    return SubscriptionState(
      isLoading: isLoading ?? this.isLoading,
      subscription: clearSubscription ? null : (subscription ?? this.subscription),
      error: clearError ? null : (error ?? this.error),
      lastUpsellReminderAt: lastUpsellReminderAt ?? this.lastUpsellReminderAt,
    );
  }

  @override
  List<Object?> get props => [
        isLoading,
        subscription,
        error,
        lastUpsellReminderAt,
      ];
}
