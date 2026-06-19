import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../../config/theme.dart';

/// Draggable 3x3 swipe-pattern input reminiscent of Android's lock
/// screen. Emits the ordered list of connected dot indices
/// (`0..=8`, left-to-right, top-to-bottom) via [onCompleted] when the
/// user releases their finger.
///
/// Design choices:
///   * 3x3 grid is the industry-standard size — bigger grids feel
///     fiddly on phone screens and don't add much entropy (4x4 is
///     ~8M permutations, 3x3 is already 389k valid patterns).
///   * Dots auto-snap when the finger passes within
///     `dotSize / 1.6` — Android-style forgiveness, otherwise drawing
///     through the center is annoyingly precise.
///   * Middle dots are auto-included when the finger passes *through*
///     a dot to reach a farther one (e.g. 0 → 2 automatically picks
///     up 1). This matches user expectation from Android.
///   * Colors come from the active theme — purple in dark mode, brand
///     gradient-primary in light mode. Errors flash red for 600ms.
class PatternLock extends StatefulWidget {
  /// Fired when the user lifts their finger with at least one dot
  /// connected. Callers are responsible for deciding what to do with
  /// short (< minLength) patterns — we still fire so the setup UI can
  /// show an inline error.
  final ValueChanged<List<int>> onCompleted;

  /// Fired every time a new dot joins the current stroke. UI uses
  /// this for haptic feedback + "X dot" hint text.
  final ValueChanged<List<int>>? onProgress;

  /// When non-null the widget enters error state (red glow) until the
  /// user starts a new gesture.
  final String? errorText;

  /// Size of each dot in logical px.
  final double dotSize;

  /// Optional forced size; when null the widget takes all the space
  /// its parent grants it (up to [maxSize]).
  final double? size;

  /// Upper bound for the grid when [size] is not provided.
  final double maxSize;

  const PatternLock({
    super.key,
    required this.onCompleted,
    this.onProgress,
    this.errorText,
    this.dotSize = 18,
    this.size,
    this.maxSize = 320,
  });

  @override
  State<PatternLock> createState() => _PatternLockState();
}

class _PatternLockState extends State<PatternLock> {
  final List<int> _picked = [];
  Offset? _pointer;
  bool _errorFlash = false;

  @override
  void didUpdateWidget(covariant PatternLock oldWidget) {
    super.didUpdateWidget(oldWidget);
    // When parent flags a new error (wrong pattern / too short), clear
    // the current stroke so the user can redraw.
    if (widget.errorText != null && widget.errorText != oldWidget.errorText) {
      setState(() {
        _picked.clear();
        _pointer = null;
        _errorFlash = true;
      });
      HapticFeedback.heavyImpact();
      Future.delayed(const Duration(milliseconds: 600), () {
        if (mounted) setState(() => _errorFlash = false);
      });
    }
  }

  void _handleStart(Offset local, double grid) {
    final idx = _dotAt(local, grid);
    setState(() {
      _picked.clear();
      if (idx != null) _picked.add(idx);
      _pointer = local;
      _errorFlash = false;
    });
    if (idx != null) {
      HapticFeedback.selectionClick();
      widget.onProgress?.call(List.unmodifiable(_picked));
    }
  }

  void _handleUpdate(Offset local, double grid) {
    final idx = _dotAt(local, grid);
    setState(() => _pointer = local);
    if (idx == null) return;
    if (_picked.contains(idx)) return;
    // If the straight line from the last picked dot to `idx` passes
    // through another unpicked dot, include that intermediate too.
    if (_picked.isNotEmpty) {
      final prev = _picked.last;
      final mid = _midpoint(prev, idx);
      if (mid != null && !_picked.contains(mid)) {
        _picked.add(mid);
      }
    }
    _picked.add(idx);
    HapticFeedback.selectionClick();
    widget.onProgress?.call(List.unmodifiable(_picked));
  }

  void _handleEnd() {
    if (_picked.isEmpty) return;
    HapticFeedback.mediumImpact();
    widget.onCompleted(List.unmodifiable(_picked));
    // Keep the picked dots rendered for a brief moment so the user sees
    // the completed shape before the parent screen replaces us or we
    // reset via errorText.
    setState(() => _pointer = null);
  }

  /// Returns the dot index under [local] (in grid coordinates) within
  /// the auto-snap tolerance, or null if the finger isn't near any
  /// dot.
  int? _dotAt(Offset local, double grid) {
    final step = grid / 3;
    final snap = widget.dotSize * 1.6;
    for (var i = 0; i < 9; i++) {
      final r = i ~/ 3;
      final c = i % 3;
      final dotCenter = Offset(step * (c + 0.5), step * (r + 0.5));
      if ((local - dotCenter).distance <= snap) return i;
    }
    return null;
  }

  /// Returns the dot index between [a] and [b] if they're exactly two
  /// steps apart on a straight line (horizontal / vertical /
  /// diagonal). Otherwise null.
  int? _midpoint(int a, int b) {
    final ar = a ~/ 3, ac = a % 3;
    final br = b ~/ 3, bc = b % 3;
    final dr = br - ar, dc = bc - ac;
    final absDr = dr.abs(), absDc = dc.abs();
    final straight = dr == 0 || dc == 0 || absDr == absDc;
    if (!straight) return null;
    if (absDr <= 1 && absDc <= 1) return null; // adjacent
    if (absDr == 2 || absDc == 2) {
      final mr = ar + dr ~/ 2;
      final mc = ac + dc ~/ 2;
      return mr * 3 + mc;
    }
    return null;
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return LayoutBuilder(
      builder: (context, cons) {
        final size = widget.size ??
            math.min(
              math.min(cons.maxWidth, cons.maxHeight),
              widget.maxSize,
            );
        return SizedBox(
          width: size,
          height: size,
          child: GestureDetector(
            behavior: HitTestBehavior.opaque,
            onPanStart: (d) => _handleStart(d.localPosition, size),
            onPanUpdate: (d) => _handleUpdate(d.localPosition, size),
            onPanEnd: (_) => _handleEnd(),
            onPanCancel: _handleEnd,
            child: CustomPaint(
              painter: _PatternPainter(
                picked: _picked,
                pointer: _pointer,
                dotSize: widget.dotSize,
                isDark: isDark,
                isError: _errorFlash || widget.errorText != null,
              ),
            ),
          ),
        );
      },
    );
  }
}

class _PatternPainter extends CustomPainter {
  final List<int> picked;
  final Offset? pointer;
  final double dotSize;
  final bool isDark;
  final bool isError;

  _PatternPainter({
    required this.picked,
    required this.pointer,
    required this.dotSize,
    required this.isDark,
    required this.isError,
  });

  @override
  void paint(Canvas canvas, Size size) {
    final grid = math.min(size.width, size.height);
    final step = grid / 3;
    final centers = List<Offset>.generate(9, (i) {
      final r = i ~/ 3;
      final c = i % 3;
      return Offset(step * (c + 0.5), step * (r + 0.5));
    });

    final accent = isError
        ? AppColors.error
        : AppColors.primary;
    final dotIdle = isDark
        ? AppColors.darkTextSecondary.withValues(alpha: 0.35)
        : AppColors.textSecondary.withValues(alpha: 0.30);
    final dotActive = accent;
    final ringHalo = accent.withValues(alpha: 0.18);

    // Halo rings around picked dots (makes connection feedback pop).
    for (final i in picked) {
      final c = centers[i];
      canvas.drawCircle(
        c,
        dotSize * 1.8,
        Paint()
          ..style = PaintingStyle.fill
          ..color = ringHalo,
      );
    }

    // Dots themselves.
    for (var i = 0; i < 9; i++) {
      final c = centers[i];
      final active = picked.contains(i);
      canvas.drawCircle(
        c,
        dotSize / 2,
        Paint()
          ..style = PaintingStyle.fill
          ..color = active ? dotActive : dotIdle,
      );
      if (active) {
        canvas.drawCircle(
          c,
          dotSize / 2 + 2,
          Paint()
            ..style = PaintingStyle.stroke
            ..strokeWidth = 1.5
            ..color = accent.withValues(alpha: 0.55),
        );
      }
    }

    // Connecting strokes.
    if (picked.isNotEmpty) {
      final stroke = Paint()
        ..color = accent.withValues(alpha: 0.9)
        ..strokeWidth = 3.5
        ..strokeCap = StrokeCap.round
        ..style = PaintingStyle.stroke;
      final path = Path()..moveTo(centers[picked.first].dx, centers[picked.first].dy);
      for (var i = 1; i < picked.length; i++) {
        path.lineTo(centers[picked[i]].dx, centers[picked[i]].dy);
      }
      if (pointer != null) {
        path.lineTo(pointer!.dx, pointer!.dy);
      }
      canvas.drawPath(path, stroke);
    }
  }

  @override
  bool shouldRepaint(covariant _PatternPainter old) {
    return old.picked != picked ||
        old.pointer != pointer ||
        old.isError != isError;
  }
}
