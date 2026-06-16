import 'package:equatable/equatable.dart';

sealed class AuthEvent extends Equatable {
 const AuthEvent();
 @override
 List<Object?> get props => [];
}

class AuthCheckRequested extends AuthEvent {
 const AuthCheckRequested();
}

class AuthLoginRequested extends AuthEvent {
 const AuthLoginRequested({
 required this.email,
 required this.password,
 required this.subdomain,
 });
 final String email;
 final String password;
 final String subdomain;

 @override
 List<Object?> get props => [email, password, subdomain];
}

class Auth2FARequested extends AuthEvent {
 const Auth2FARequested({required this.code, required this.method});
 final String code;
 final String method;

 @override
 List<Object?> get props => [code, method];
}

class AuthLogoutRequested extends AuthEvent {
 const AuthLogoutRequested();
}

class AuthReset extends AuthEvent {
 const AuthReset();
}
