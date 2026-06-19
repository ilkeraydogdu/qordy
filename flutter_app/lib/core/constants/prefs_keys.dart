/// Canonical SharedPreferences key names.
///
/// Before this file, SharedPreferences keys were scattered across the
/// app as string literals: `qordy_pending_onboarding`, `qordy.theme_mode`,
/// update-skip keys, per-feature cooldowns, etc. Drift between call
/// sites (typo → silent write to a different key → "nothing saved")
/// is the common failure mode, so pin everything down here.
class PrefsKeys {
  const PrefsKeys._();

  // Auth / onboarding
  static const String pendingOnboarding = 'qordy_pending_onboarding';
  static const String pendingOnboardingBusinessId =
      'qordy_pending_onboarding_business_id';
  static const String pendingOnboardingToken =
      'qordy_pending_onboarding_token';

  // Theme
  static const String themeMode = 'qordy.theme_mode';

  // Update gate
  static const String updateSkipVersion = 'qordy_update_skip_version';
  static const String updateSkipUntil = 'qordy_update_skip_until';

  // Shell reminders / banners
  static const String shellReminderDismissed = 'qordy_shell_reminder_dismissed';
  static const String customOfferCooldown = 'qordy_custom_offer_cooldown';

  // Notifications
  static const String notificationPrefs = 'qordy_notification_prefs';
  static const String fcmTokenSynced = 'qordy_fcm_token_synced';
}
