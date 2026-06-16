import 'package:equatable/equatable.dart';

import '../../domain/entities/auth_session.dart';

sealed class AuthState extends Equatable {
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

class AuthAuthenticated extends AuthState {
 const AuthAuthenticated(this.session);
 final AuthSession session;

 @override
 List<Object?> get props => [session];
}

class AuthUnauthenticated extends AuthState {
 const AuthUnauthenticated({this.message, this.requires2fa = false, this.twoFactorMethod});
 final String? message;
 final bool requires2fa;
 final String? twoFactorMethod;

 @override
 List<Object?> get props => [message, requires2fa, twoFactorMethod];
}

class AuthTwoFactorRequired extends AuthState {
 const AuthTwoFactorRequired({required this.method, this.preliminaryToken});
 final String method;
 final String? preliminaryToken;

 @override
 List<Object?> get props => [method, preliminaryToken];
}

class AuthError extends AuthState {
 const AuthError(this.message, {this.field});
 final String message;
 final String? field;

 @override
 List<Object?> get props => [message, field];
}
