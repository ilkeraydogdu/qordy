import 'package:flutter/foundation.dart';

/// Global auth state — Bloc tarafından güncellenir.
///
/// Router bu listenable'ı dinler; auth değişince redirect yeniden çalışır.
class AuthStateNotifier extends ChangeNotifier {
 bool _isAuthenticated = false;

 bool get isAuthenticated => _isAuthenticated;

 void setAuthenticated(bool value) {
 if (_isAuthenticated == value) return;
 _isAuthenticated = value;
 notifyListeners();
 }
}

final AuthStateNotifier authStateNotifier = AuthStateNotifier();

/// GoRouter'ın dinleyebileceği listenable.
final ValueListenable<bool> authStateListenable =
 ValueNotifier<bool>(false);

bool isAuthenticated() => authStateListenable.value;

/// Auth state'i güncellemek için tek giriş noktası.
void setAuthState(bool isAuth) {
 (authStateListenable as ValueNotifier<bool>).value = isAuth;
 authStateNotifier.setAuthenticated(isAuth);
}
