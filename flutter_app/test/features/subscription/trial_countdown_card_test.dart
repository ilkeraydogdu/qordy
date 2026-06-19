import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:qordy_app/features/subscription/cubit/subscription_cubit.dart';
import 'package:qordy_app/features/subscription/cubit/subscription_state.dart';
import 'package:qordy_app/features/subscription/widgets/trial_countdown_card.dart';
import 'package:qordy_app/models/package_model.dart';

Widget _wrap(SubscriptionState state) {
  return MaterialApp(
    home: BlocProvider<SubscriptionCubit>(
      create: (_) => SubscriptionCubit.forTesting(state),
      child: const Scaffold(body: TrialCountdownCard()),
    ),
  );
}

void main() {
  testWidgets('TrialCountdownCard shows days left in trial', (tester) async {
    await tester.pumpWidget(_wrap(const SubscriptionState(
      subscription: Subscription(
        phase: SubscriptionPhase.trial,
        daysLeft: 4,
        isTrial: true,
      ),
    )));
    expect(find.textContaining('4 gün'), findsOneWidget);
    expect(find.text('Şimdi Satın Al'), findsOneWidget);
  });

  testWidgets('TrialCountdownCard shows grace text', (tester) async {
    await tester.pumpWidget(_wrap(const SubscriptionState(
      subscription: Subscription(
        phase: SubscriptionPhase.grace,
        graceDaysLeft: 2,
        readOnly: true,
      ),
    )));
    expect(find.textContaining('Deneme süreniz doldu'), findsOneWidget);
  });

  testWidgets('TrialCountdownCard hidden for active subscription',
      (tester) async {
    await tester.pumpWidget(_wrap(const SubscriptionState(
      subscription: Subscription(phase: SubscriptionPhase.active),
    )));
    expect(find.byType(TrialCountdownCard), findsOneWidget);
    expect(find.text('Şimdi Satın Al'), findsNothing);
  });
}
