import 'package:flutter_test/flutter_test.dart';
import 'package:qordy_app/models/package_model.dart';

void main() {
  group('Subscription.fromJson', () {
    test('parses trial phase with days left', () {
      final s = Subscription.fromJson({
        'phase': 'trial',
        'daysLeft': 5,
        'graceDaysLeft': 0,
        'readOnly': false,
        'isTrial': true,
      });
      expect(s.phase, SubscriptionPhase.trial);
      expect(s.daysLeft, 5);
      expect(s.isTrialPhase, true);
      expect(s.canMutate, true);
      expect(s.hasActiveAccess, true);
    });

    test('parses grace phase as read-only and non-mutable', () {
      final s = Subscription.fromJson({
        'phase': 'grace',
        'daysLeft': 0,
        'graceDaysLeft': 4,
        'readOnly': true,
      });
      expect(s.phase, SubscriptionPhase.grace);
      expect(s.isGrace, true);
      expect(s.readOnly, true);
      expect(s.canMutate, false);
      expect(s.hasActiveAccess, false);
    });

    test('parses suspended phase as suspended', () {
      final s = Subscription.fromJson({
        'phase': 'suspended',
        'daysLeft': 0,
        'graceDaysLeft': 0,
        'readOnly': true,
      });
      expect(s.phase, SubscriptionPhase.suspended);
      expect(s.isSuspended, true);
      expect(s.canMutate, false);
    });

    test('defaults to none for unknown phase', () {
      final s = Subscription.fromJson({'phase': 'something-else'});
      expect(s.phase, SubscriptionPhase.none);
      expect(s.canMutate, true);
    });
  });
}
