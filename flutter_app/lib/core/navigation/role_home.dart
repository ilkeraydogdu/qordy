import '../../models/user.dart';

/// Centralised role model + navigation rules.
///
/// The mobile app is now manager-only — the staff role enum cases
/// (cashier / kitchen / preparation / waiter) are retained as wire
/// constants so older user records still deserialize, but no route in
/// the app branches on them any more. They all collapse to
/// [AppRole.manager] for landing-route purposes.
enum AppRole {
 admin('ADMIN'),
 manager('MANAGER'),
 owner('OWNER'),
 cashier('CASHIER'),
 kitchen('KITCHEN'),
 preparation('PREPARATION'),
 waiter('WAITER'),
 unknown('');

 const AppRole(this.wire);

 /// Uppercase string the backend sends in `user.role`.
 final String wire;

 /// Resolve a wire string (or a [User]) to a typed [AppRole].
 /// Falls back to [unknown] when the backend ships something we don't
 /// recognise.
 static AppRole fromUser(User? user) => fromRoleName(user?.role);

 /// Like [fromUser] but accepts a raw wire string directly — useful on
 /// responses that haven't been materialised into a [User] yet.
 static AppRole fromRoleName(String? roleWire) {
 final raw = roleWire?.toUpperCase().trim();
 if (raw == null || raw.isEmpty) return AppRole.unknown;
 for (final r in AppRole.values) {
 if (r.wire == raw) return r;
 }
 // Managers sometimes arrive with a bespoke string ("BUSINESS_OWNER",
 // "BUSINESS_MANAGER", "OWNER_ADMIN" etc). Treat anything that
 // *contains* OWNER/MANAGER/ADMIN as the closest known super-role.
 if (raw.contains('OWNER')) return AppRole.owner;
 if (raw.contains('MANAGER')) return AppRole.manager;
 if (raw.contains('ADMIN')) return AppRole.admin;
 if (raw.contains('WAITER')) return AppRole.waiter;
 if (raw.contains('KITCHEN')) return AppRole.kitchen;
 if (raw.contains('PREPARATION')) return AppRole.preparation;
 if (raw.contains('CASHIER')) return AppRole.cashier;
 return AppRole.unknown;
 }

 /// User-visible Turkish label (matches Profile screen copy).
 String get label {
 switch (this) {
 case AppRole.admin:
 return 'Yönetici';
 case AppRole.manager:
 case AppRole.owner:
 return 'İşletme Sahibi';
 case AppRole.cashier:
 return 'Kasiyer';
 case AppRole.kitchen:
 return 'Mutfak';
 case AppRole.preparation:
 return 'Hazırlık';
 case AppRole.waiter:
 return 'Garson';
 case AppRole.unknown:
 return 'Personel';
 }
 }

 /// True when this role is one of the "super" roles (admin / manager /
 /// owner) that gets the full manager shell. Staff wire names collapse
 /// here too so legacy records still land on the dashboard.
 bool get isManagerOrOwner =>
 this == AppRole.admin ||
 this == AppRole.manager ||
 this == AppRole.owner;
}

/// Central navigation ruleset used by the router redirect and the shell.
class RoleHome {
 RoleHome._();

 /// Landing route after a successful login / on cold start.
 ///
 /// Staff wire roles (cashier / kitchen / preparation / waiter) still
 /// resolve on the backend — we keep the enum cases for backwards
 /// compatibility with older user records — but the mobile app no
 /// longer ships dedicated home screens for them. They all land on
 /// the manager dashboard, which the manager-only guard in
 /// [AppRouter] will allow if the user's effective role is super.
 static String initialRouteFor(AppRole role) {
 if (role.isManagerOrOwner) return '/dashboard';
 return '/profile';
 }

 /// True when [role] is allowed to open [path] (full path from
 /// GoRouter). With the personnel feature set retired, every
 /// authenticated user is treated as a manager for navigation
 /// purposes — staff login is no longer reachable from this app.
 static bool canAccess(AppRole role, String path) {
 if (_matchesAny(path, const [
 '/security',
 '/profile',
 '/notifications',
 '/notification-settings',
 ])) {
 return true;
 }
 if (role.isManagerOrOwner) return true;
 // Anyone authenticated but not on the super-role list falls back
 // to self-service only (profile / security / notifications).
 return false;
 }

 /// True when [role] should see the manager shell (5-tab bottom
 /// navigation with Dashboard / Orders / Notifications / Profile).
 static bool showsFullShell(AppRole role) => role.isManagerOrOwner;

 static bool _matchesAny(String path, List<String> allowed) {
 for (final p in allowed) {
 if (path == p || path.startsWith('$p/')) return true;
 }
 return false;
 }
}
