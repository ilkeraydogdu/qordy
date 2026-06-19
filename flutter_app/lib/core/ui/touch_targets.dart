import 'package:flutter/material.dart';

/// Material 3 minimum touch target (48 logical pixels).
Widget minTouchHeightInkWell({
  required VoidCallback onTap,
  required Widget child,
  BorderRadius? borderRadius,
}) {
  final br = borderRadius ?? BorderRadius.circular(24);
  return SizedBox(
    height: kMinInteractiveDimension,
    child: Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: br,
        child: Center(child: child),
      ),
    ),
  );
}

/// Centers a smaller visual control inside a 48×48 tap area.
Widget minTouchSquareInkWell({
  required VoidCallback onTap,
  required Widget child,
  BorderRadius? borderRadius,
}) {
  final br = borderRadius ?? BorderRadius.circular(8);
  return Material(
    color: Colors.transparent,
    child: InkWell(
      onTap: onTap,
      borderRadius: br,
      child: SizedBox(
        width: kMinInteractiveDimension,
        height: kMinInteractiveDimension,
        child: Center(child: child),
      ),
    ),
  );
}
