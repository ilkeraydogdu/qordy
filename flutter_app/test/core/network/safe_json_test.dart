import 'package:flutter_test/flutter_test.dart';
import 'package:qordy_app/core/network/safe_json.dart';

/// Guards the parsing layer against the loosely-typed responses returned
/// by the PHP backend. A screen should never crash just because an `int`
/// was wire-serialized as a `String`.
void main() {
  group('asJsonMap / asJsonList', () {
    test('asJsonMap tolerates non-map inputs', () {
      expect(asJsonMap(null), const <String, dynamic>{});
      expect(asJsonMap(42), const <String, dynamic>{});
      expect(asJsonMap('oops'), const <String, dynamic>{});
    });

    test('asJsonMap normalises dynamic-keyed maps', () {
      final dynamic raw = <Object, Object>{1: 'a', 'b': 2};
      final out = asJsonMap(raw);
      expect(out['1'], 'a');
      expect(out['b'], 2);
    });

    test('asJsonList returns empty on non-list', () {
      expect(asJsonList(null), isEmpty);
      expect(asJsonList('not a list'), isEmpty);
    });
  });

  group('SafeJsonMap extension', () {
    test('intOr / doubleOr accept stringified numbers', () {
      final m = <String, dynamic>{'a': '42', 'b': 3.14, 'c': 'x'};
      expect(m.intOr('a', 0), 42);
      expect(m.doubleOr('b', 0), 3.14);
      expect(m.intOr('c', -1), -1);
      expect(m.intOr('missing', 9), 9);
    });

    test('boolOr handles common PHP-style truthy/falsy values', () {
      final m = <String, dynamic>{
        'a': true,
        'b': 'true',
        'c': '1',
        'd': 0,
        'e': 'false',
      };
      expect(m.boolOr('a', false), isTrue);
      expect(m.boolOr('b', false), isTrue);
      expect(m.boolOr('c', false), isTrue);
      expect(m.boolOr('d', true), isFalse);
      expect(m.boolOr('e', true), isFalse);
    });

    test('listOf maps through the provided factory', () {
      final m = <String, dynamic>{
        'items': [
          {'id': 1, 'name': 'A'},
          {'id': 2, 'name': 'B'},
        ],
      };
      final names =
          m.listOf<String>('items', (e) => e.stringOr('name', ''));
      expect(names, ['A', 'B']);
    });

    test('stringOrNull trims empty strings to null', () {
      final m = <String, dynamic>{'a': '', 'b': 'ok'};
      expect(m.stringOrNull('a'), isNull);
      expect(m.stringOrNull('b'), 'ok');
      expect(m.stringOrNull('missing'), isNull);
    });
  });
}
