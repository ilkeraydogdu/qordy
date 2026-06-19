import 'package:flutter/widgets.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:qordy_app/features/auth/cubit/auth_cubit.dart';
import 'package:qordy_app/features/auth/cubit/auth_state.dart';

/// İşletme adı + kısa rol etiketi (operasyon ekranları için).
String businessTaggedSubtitle(BuildContext context, String tagline) {
  final s = context.watch<AuthCubit>().state;
  if (s is Authenticated) {
    final n = (s.business.companyName ?? '').trim();
    if (n.isNotEmpty) {
      return '$n · $tagline';
    }
  }
  return tagline;
}
