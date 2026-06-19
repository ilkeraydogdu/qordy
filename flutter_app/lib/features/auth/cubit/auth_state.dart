import 'package:equatable/equatable.dart';
import 'package:qordy_app/models/business.dart';
import 'package:qordy_app/models/user.dart';

abstract class AuthState extends Equatable {
  const AuthState();

  @override
  List<Object?> get props => [];
}

class AuthInitial extends AuthState {
  const AuthInitial();
}

class AuthLoading extends AuthState {
  const AuthLoading();
}

class Authenticated extends AuthState {
  final User user;
  final Business business;
  final String token;

  /// Backend returns permissions as a JSON array of dotted strings
  /// (e.g. `["*"]` for admins, `["orders.view", "tables.view", ...]` for
  /// staff). `["*"]` means "all permissions".
  final List<String> permissions;
  final Map<String, dynamic>? stats;

  /// Abonelik/deneme durumu. `trial`, `active`, `grace`, `expired`,
  /// `suspended`, `none`. Null ise henüz fetch edilmemiştir (giriş
  /// hemen sonrası) — router null durumunda gating uygulamaz.
  final String? subscriptionPhase;
  final bool subscriptionReadOnly;
  final int subscriptionDaysLeft;
  final int subscriptionGraceDaysLeft;

  const Authenticated({
    required this.user,
    required this.business,
    required this.token,
    this.permissions = const [],
    this.stats,
    this.subscriptionPhase,
    this.subscriptionReadOnly = false,
    this.subscriptionDaysLeft = 0,
    this.subscriptionGraceDaysLeft = 0,
  });

  bool hasPermission(String key) =>
      permissions.contains('*') || permissions.contains(key);

  /// Uygulama üzerinden ödeme ekranına zorunlu yönlendirme yapılmalı mı?
  /// Deneme bitmiş (expired) ya da askıya alınmış (suspended) işletmeler
  /// paywall'a kilitlenir. Grace fazında kullanıcı hâlâ veri görebilir
  /// ama write blocklu — paywall değil, in-app warning banner'ı yeterli.
  bool get isPaywalled {
    final p = (subscriptionPhase ?? '').toLowerCase();
    return p == 'expired' || p == 'suspended';
  }

  Authenticated copyWith({
    User? user,
    Business? business,
    String? token,
    List<String>? permissions,
    Map<String, dynamic>? stats,
    String? subscriptionPhase,
    bool? subscriptionReadOnly,
    int? subscriptionDaysLeft,
    int? subscriptionGraceDaysLeft,
  }) {
    return Authenticated(
      user: user ?? this.user,
      business: business ?? this.business,
      token: token ?? this.token,
      permissions: permissions ?? this.permissions,
      stats: stats ?? this.stats,
      subscriptionPhase: subscriptionPhase ?? this.subscriptionPhase,
      subscriptionReadOnly: subscriptionReadOnly ?? this.subscriptionReadOnly,
      subscriptionDaysLeft: subscriptionDaysLeft ?? this.subscriptionDaysLeft,
      subscriptionGraceDaysLeft:
          subscriptionGraceDaysLeft ?? this.subscriptionGraceDaysLeft,
    );
  }

  @override
  List<Object?> get props => [
        user,
        business,
        token,
        permissions,
        stats,
        subscriptionPhase,
        subscriptionReadOnly,
        subscriptionDaysLeft,
        subscriptionGraceDaysLeft,
      ];
}

class AuthError extends AuthState {
  final String message;

  const AuthError(this.message);

  @override
  List<Object?> get props => [message];
}

class SubdomainValidated extends AuthState {
  final String businessName;
  final String subdomain;

  const SubdomainValidated({
    required this.businessName,
    required this.subdomain,
  });

  @override
  List<Object?> get props => [businessName, subdomain];
}



/// 4-6 haneli rakamdan oluşan benzersiz işletme numarası doğrulandı.
/// Personel artık "caddecafe.qordy.com" gibi kısaltmalar yerine
/// işletme sahibinin dashboard'unda gördüğü bu numarayı kullanır.
class BusinessNumberValidated extends AuthState {
 final String businessId;
 final String businessName;
 final String businessNumber;
 final String? businessLogo;

 const BusinessNumberValidated({
 required this.businessId,
 required this.businessName,
 required this.businessNumber,
 this.businessLogo,
 });

 @override
 List<Object?> get props =>
 [businessId, businessName, businessNumber, businessLogo];
}

class EmailValidated extends AuthState {
  final String email;
  final Map<String, dynamic>? data;

  const EmailValidated({
    required this.email,
    this.data,
  });

  @override
  List<Object?> get props => [email, data];
}

/// Device has a persisted session for [userId] but the user has enabled
/// quick unlock — the app must prompt for the saved PIN before entering
/// the authenticated shell. The pending payload is fully decoded and
/// held in memory so that on PIN success we can immediately emit
/// [Authenticated] without another round-trip.
class PendingUnlock extends AuthState {
  final String userId;
  final String displayName;
  final String? avatarUrl;
  final String? businessName;
  final String? businessLogo;
  final String? roleLabel;
  final User user;
  final Business business;
  final String token;
  final List<String> permissions;
  final Map<String, dynamic>? stats;

  /// `true` → ekranda PIN keypad gösterilmesin, yalnızca biyometri /
  /// TOTP yeniden doğrulama sunulsun. Personel rolünde kullanılır —
  /// kullanıcının talebi: "PIN ile giriş yapıyor, yeniden PIN istemek
  /// saçma; pattern / Face ID / 2FA sorulsun".
  ///
  /// `bioOnly` true ise kullanıcının sadece biyometri (+ opsiyonel
  /// TOTP reauth) ile açabilmesi gerekir. Pattern veya PIN tanımlıysa
  /// `bioOnly` false'a zorlanır çünkü o faktörleri ekranda göstermek
  /// istiyoruz.
  final bool bioOnly;

  /// Bu kullanıcı için bu cihazda desen kayıtlı mı? QuickUnlockScreen
  /// bu flag'i kullanarak desen paneline geçiş butonunu / tab'ı
  /// gösteriyor.
  final bool patternEnabled;

  /// Bu kullanıcı için bu cihazda PIN kayıtlı mı? Pattern-only
  /// senaryoda (manager desen kurup PIN'i sildi) PIN paneli
  /// gizleniyor.
  final bool pinEnabled;

  const PendingUnlock({
    required this.userId,
    required this.displayName,
    required this.user,
    required this.business,
    required this.token,
    this.avatarUrl,
    this.businessName,
    this.businessLogo,
    this.roleLabel,
    this.permissions = const [],
    this.stats,
    this.bioOnly = false,
    this.patternEnabled = false,
    this.pinEnabled = true,
  });

  @override
  List<Object?> get props => [
        userId,
        displayName,
        avatarUrl,
        businessName,
        businessLogo,
        roleLabel,
        user,
        business,
        token,
        permissions,
        stats,
        bioOnly,
        patternEnabled,
        pinEnabled,
      ];
}

/// First factor (password / PIN) succeeded but the account has TOTP
/// 2FA enabled. The server withheld the bearer token and returned a
/// short-lived [challengeToken] instead. The router routes the user to
/// `/totp-challenge` where they enter the 6-digit code from their
/// authenticator app. On success the repo exchanges the challenge for
/// a real auth bundle and the cubit re-emits [Authenticated] (or
/// [QuickUnlockSetupRequired]).
class TwoFactorChallengeRequired extends AuthState {
  final String challengeToken;

  /// Seconds until the challenge expires server-side. The screen shows
  /// a countdown so the user knows when they'd need to retry login.
  final int expiresInSeconds;

  /// Cosmetic context for the waiting screen — shows the user what
  /// account they're verifying without leaking any session data.
  final String? displayName;
  final String? businessName;
  final String? businessLogo;
  final String? roleLabel;

  /// Flow that produced this challenge (`manager` or `staff`). Used by
  /// the UI to decide whether to route back to the manager or staff
  /// login tab on cancel.
  final String flow;

  /// The 2FA methods the user can choose from for THIS login (intersection
  /// of admin-allowed + user-enrolled). e.g. `['totp','whatsapp']`.
  final List<String> methods;

  /// Server's default/recommended method (usually the first item of
  /// [methods]). The UI lands on this method initially; user can switch.
  final String defaultMethod;

  const TwoFactorChallengeRequired({
    required this.challengeToken,
    required this.flow,
    this.expiresInSeconds = 300,
    this.displayName,
    this.businessName,
    this.businessLogo,
    this.roleLabel,
    this.methods = const ['totp'],
    this.defaultMethod = 'totp',
  });

  @override
  List<Object?> get props => [
        challengeToken,
        flow,
        expiresInSeconds,
        displayName,
        businessName,
        businessLogo,
        roleLabel,
        methods,
        defaultMethod,
      ];
}

/// Logged in, but quick unlock has never been configured for this user
/// on this device. The router pushes `/quick-unlock/setup` so the user
/// can opt-in once; they can also skip forever.
class QuickUnlockSetupRequired extends AuthState {
  final User user;
  final Business business;
  final String token;
  final List<String> permissions;
  final Map<String, dynamic>? stats;

  const QuickUnlockSetupRequired({
    required this.user,
    required this.business,
    required this.token,
    this.permissions = const [],
    this.stats,
  });

  @override
  List<Object?> get props =>
      [user, business, token, permissions, stats];
}
