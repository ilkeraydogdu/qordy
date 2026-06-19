import 'package:flutter/material.dart';

import '../../../config/theme.dart';

/// Large, tap-friendly numeric keypad used by PIN entry screens
/// (quick-unlock, quick-unlock setup, 2FA challenge). Shared widget
/// so visual behavior stays consistent across flows.
class PinKeypad extends StatelessWidget {
  final ValueChanged<String> onDigit;
  final VoidCallback onBackspace;
  final VoidCallback? onBiometric;
  final bool busy;

  const PinKeypad({
    super.key,
    required this.onDigit,
    required this.onBackspace,
    this.onBiometric,
    this.busy = false,
  });

  @override
  Widget build(BuildContext context) {
    final rows = <List<_KeyDef>>[
      [
        _KeyDef.digit('1'),
        _KeyDef.digit('2'),
        _KeyDef.digit('3'),
      ],
      [
        _KeyDef.digit('4'),
        _KeyDef.digit('5'),
        _KeyDef.digit('6'),
      ],
      [
        _KeyDef.digit('7'),
        _KeyDef.digit('8'),
        _KeyDef.digit('9'),
      ],
      [
        if (onBiometric != null)
          _KeyDef.action(Icons.fingerprint_rounded, onBiometric!)
        else
          _KeyDef.empty(),
        _KeyDef.digit('0'),
        _KeyDef.action(Icons.backspace_outlined, onBackspace),
      ],
    ];
    return LayoutBuilder(builder: (context, constraints) {
      final maxH = constraints.maxHeight;
      final rowH = (maxH / 4).clamp(54.0, 80.0);
      return Column(
        children: rows.map((row) {
          return SizedBox(
            height: rowH,
            child: Row(
              children: row
                  .map((k) => Expanded(
                        child: _Key(
                          def: k,
                          onDigit: onDigit,
                          disabled: busy,
                        ),
                      ))
                  .toList(),
            ),
          );
        }).toList(),
      );
    });
  }
}

class _KeyDef {
  final String? digit;
  final IconData? icon;
  final VoidCallback? onTap;
  final bool empty;

  const _KeyDef._({this.digit, this.icon, this.onTap, this.empty = false});

  factory _KeyDef.digit(String d) => _KeyDef._(digit: d);
  factory _KeyDef.action(IconData i, VoidCallback onTap) =>
      _KeyDef._(icon: i, onTap: onTap);
  factory _KeyDef.empty() => const _KeyDef._(empty: true);
}

class _Key extends StatelessWidget {
  final _KeyDef def;
  final ValueChanged<String> onDigit;
  final bool disabled;

  const _Key({
    required this.def,
    required this.onDigit,
    this.disabled = false,
  });

  @override
  Widget build(BuildContext context) {
    if (def.empty) return const SizedBox.shrink();
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final fg =
        isDark ? AppColors.darkTextPrimary : AppColors.textPrimary;
    final callback = disabled
        ? null
        : (def.digit != null ? () => onDigit(def.digit!) : def.onTap);
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: callback,
        borderRadius: BorderRadius.circular(999),
        child: Center(
          child: Container(
            width: 68,
            height: 68,
            alignment: Alignment.center,
            child: def.digit != null
                ? Text(
                    def.digit!,
                    style: TextStyle(
                      fontSize: 28,
                      fontWeight: FontWeight.w600,
                      color: fg,
                      letterSpacing: -0.5,
                    ),
                  )
                : Icon(
                    def.icon,
                    size: 26,
                    color: fg,
                  ),
          ),
        ),
      ),
    );
  }
}
