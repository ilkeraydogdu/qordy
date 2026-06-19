import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:qordy_app/features/subscription/cubit/subscription_cubit.dart';
import 'package:qordy_app/features/subscription/cubit/subscription_state.dart';
import 'package:qordy_app/features/subscription/widgets/readonly_banner.dart';
import 'package:qordy_app/models/package_model.dart';

Widget _wrap(SubscriptionState state) {
  return MaterialApp(
    home: BlocProvider<SubscriptionCubit>(
      create: (_) => SubscriptionCubit.forTesting(state),
      child: const Scaffold(body: ReadonlyBanner()),
    ),
  );
}

void main() {
  testWidgets('ReadonlyBanner hides for active phase', (tester) async {
    await tester.pumpWidget(_wrap(const SubscriptionState(
      subscription: Subscription(phase: SubscriptionPhase.active),
    )));
    expect(find.byType(ReadonlyBanner), findsOneWidget);
    expect(find.textContaining('askıya'), findsNothing);
    expect(find.textContaining('salt-okunur'), findsNothing);
  });

  testWidgets('ReadonlyBanner shows grace message', (tester) async {
    await tester.pumpWidget(_wrap(const SubscriptionState(
      subscription: Subscription(
        phase: SubscriptionPhase.grace,
        graceDaysLeft: 3,
        readOnly: true,
      ),
    )));
    expect(find.textContaining('salt-okunur'), findsOneWidget);
  });

  testWidgets('ReadonlyBanner shows suspended message', (tester) async {
    await tester.pumpWidget(_wrap(const SubscriptionState(
      subscription: Subscription(
        phase: SubscriptionPhase.suspended,
        readOnly: true,
      ),
    )));
    expect(find.textContaining('askıya'), findsOneWidget);
  });
}
