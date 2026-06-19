import 'package:flutter_test/flutter_test.dart';
import 'package:qordy_app/core/navigation/role_home.dart';
import 'package:qordy_app/models/user.dart';

/// Smoke tests for the role-based routing rules.
///
/// These are the guarantees the rest of the app relies on:
///   * a cashier cannot open the manager dashboard,
///   * a waiter cannot open `/tables`,
///   * a manager sees the full bottom-nav shell.
void main() {
  group('AppRole.fromUser', () {
    test('maps backend role strings case-insensitively', () {
      expect(
        AppRole.fromUser(const User(userId: '1', role: 'CASHIER')),
        AppRole.cashier,
      );
      expect(
        AppRole.fromUser(const User(userId: '1', role: 'cashier')),
        AppRole.cashier,
      );
      expect(
        AppRole.fromUser(const User(userId: '1', role: 'manager')),
        AppRole.manager,
      );
    });

    test('returns unknown for missing or unrecognised role', () {
      expect(AppRole.fromUser(null), AppRole.unknown);
      expect(
        AppRole.fromUser(const User(userId: '1', role: 'robot')),
        AppRole.unknown,
      );
    });
  });

  group('RoleHome.initialRouteFor', () {
    test('operational roles land on their role home', () {
      expect(RoleHome.initialRouteFor(AppRole.cashier), '/pos');
      expect(RoleHome.initialRouteFor(AppRole.kitchen), '/kitchen');
      expect(RoleHome.initialRouteFor(AppRole.preparation), '/preparation');
      expect(RoleHome.initialRouteFor(AppRole.waiter), '/waiter');
    });

    test('manager and owner land on the dashboard', () {
      expect(RoleHome.initialRouteFor(AppRole.manager), '/dashboard');
      expect(RoleHome.initialRouteFor(AppRole.owner), '/dashboard');
      expect(RoleHome.initialRouteFor(AppRole.admin), '/dashboard');
    });
  });

  group('RoleHome.canAccess', () {
    test('cashier is sandboxed to /pos and operational masalar', () {
      expect(RoleHome.canAccess(AppRole.cashier, '/pos'), isTrue);
      expect(RoleHome.canAccess(AppRole.cashier, '/tables-floor'), isTrue);
      expect(
        RoleHome.canAccess(AppRole.cashier, '/tables-floor/detail/t1'),
        isTrue,
      );
      expect(RoleHome.canAccess(AppRole.cashier, '/dashboard'), isFalse);
      expect(RoleHome.canAccess(AppRole.cashier, '/tables'), isFalse);
      expect(RoleHome.canAccess(AppRole.cashier, '/packages'), isFalse);
    });

    test('waiter is sandboxed to /waiter and operational masalar', () {
      expect(RoleHome.canAccess(AppRole.waiter, '/waiter'), isTrue);
      expect(RoleHome.canAccess(AppRole.waiter, '/tables-floor'), isTrue);
      expect(RoleHome.canAccess(AppRole.waiter, '/tables'), isFalse);
      expect(RoleHome.canAccess(AppRole.waiter, '/dashboard'), isFalse);
    });

    test('manager can reach every tab + drawer screen', () {
      for (final path in [
        '/dashboard',
        '/orders',
        '/tables',
        '/notifications',
        '/profile',
        '/packages',
        '/settings',
      ]) {
        expect(
          RoleHome.canAccess(AppRole.manager, path),
          isTrue,
          reason: 'manager should access $path',
        );
      }
    });
  });

  group('RoleHome.showsFullShell', () {
    test('only admin / owner / manager see the full 5-tab shell', () {
      expect(RoleHome.showsFullShell(AppRole.admin), isTrue);
      expect(RoleHome.showsFullShell(AppRole.owner), isTrue);
      expect(RoleHome.showsFullShell(AppRole.manager), isTrue);
      expect(RoleHome.showsFullShell(AppRole.cashier), isFalse);
      expect(RoleHome.showsFullShell(AppRole.kitchen), isFalse);
      expect(RoleHome.showsFullShell(AppRole.preparation), isFalse);
      expect(RoleHome.showsFullShell(AppRole.waiter), isFalse);
      expect(RoleHome.showsFullShell(AppRole.unknown), isFalse);
    });
  });
}
