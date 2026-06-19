import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../../config/theme.dart';

/// System-keyboard-backed 6-digit OTP input.
///
/// Replaces the custom tap-dial number pad that earlier 2FA screens
/// used. The user feedback was explicit: typing an authenticator code
/// on a 10-button custom pad is slower than the phone keyboard and
/// paste-from-clipboard is impossible. This widget:
///
///   * Uses a single hidden [TextField] bound to the system numeric
///     keyboard, so Android/iOS autofill and paste work for free.
///   * Renders six visually distinct digit "boxes" that mirror the
///     current value of the field.
///   * Surfaces an explicit "Yapıştır" button that pulls the clipboard
///     and auto-submits when 6 consecutive digits are found.
///   * Auto-fires [onCompleted] as soon as the sixth digit arrives so
///     callers don't have to wire that themselves.
class OtpCodeInput extends StatefulWidget {
  const OtpCodeInput({
    super.key,
    required this.controller,
    required this.onCompleted,
    this.length = 6,
    this.enabled = true,
    this.autofocus = true,
    this.showPaste = true,
  });

  final TextEditingController controller;
  final ValueChanged<String> onCompleted;
  final int length;
  final bool enabled;
  final bool autofocus;
  final bool showPaste;

  @override
  State<OtpCodeInput> createState() => _OtpCodeInputState();
}

class _OtpCodeInputState extends State<OtpCodeInput> {
  final FocusNode _focusNode = FocusNode();
  bool _completedFired = false;

  @override
  void initState() {
    super.initState();
    widget.controller.addListener(_onChange);
    if (widget.autofocus) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted && widget.enabled) _focusNode.requestFocus();
      });
    }
  }

  @override
  void didUpdateWidget(covariant OtpCodeInput oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.controller != widget.controller) {
      oldWidget.controller.removeListener(_onChange);
      widget.controller.addListener(_onChange);
    }
  }

  @override
  void dispose() {
    widget.controller.removeListener(_onChange);
    _focusNode.dispose();
    super.dispose();
  }

  void _onChange() {
    final text = widget.controller.text;
    if (text.length < widget.length) {
      _completedFired = false;
    }
    if (text.length == widget.length && !_completedFired) {
      _completedFired = true;
      HapticFeedback.selectionClick();
      widget.onCompleted(text);
    }
    if (mounted) setState(() {});
  }

  Future<void> _paste() async {
    final data = await Clipboard.getData(Clipboard.kTextPlain);
    final raw = (data?.text ?? '').replaceAll(RegExp(r'\D'), '');
    if (raw.isEmpty) return;
    final trimmed =
        raw.length > widget.length ? raw.substring(0, widget.length) : raw;
    widget.controller.text = trimmed;
    widget.controller.selection =
        TextSelection.collapsed(offset: trimmed.length);
    HapticFeedback.lightImpact();
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final value = widget.controller.text;
    final borderColor = isDark ? AppColors.darkBorder : AppColors.border;

    return GestureDetector(
      behavior: HitTestBehavior.opaque,
      onTap: widget.enabled ? () => _focusNode.requestFocus() : null,
      child: Stack(
        children: [
          // The actual text input. We keep it on-screen (not offstage)
          // so on iOS the toolbar with "Paste" shows correctly when the
          // user long-presses. It's 1×1 pixel with zero opacity.
          Positioned.fill(
            child: Opacity(
              opacity: 0.0,
              child: TextField(
                controller: widget.controller,
                focusNode: _focusNode,
                autofocus: widget.autofocus,
                enabled: widget.enabled,
                keyboardType: TextInputType.number,
                textInputAction: TextInputAction.done,
                enableInteractiveSelection: true,
                inputFormatters: [
                  FilteringTextInputFormatter.digitsOnly,
                  LengthLimitingTextInputFormatter(widget.length),
                ],
                style: const TextStyle(color: Colors.transparent),
                cursorColor: Colors.transparent,
                decoration: const InputDecoration(
                  border: InputBorder.none,
                  counterText: '',
                ),
              ),
            ),
          ),
          Column(
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: List.generate(widget.length, (i) {
                  final filled = i < value.length;
                  final active = i == value.length && widget.enabled;
                  return AnimatedContainer(
                    duration: const Duration(milliseconds: 140),
                    margin: const EdgeInsets.symmetric(horizontal: 5),
                    width: 44,
                    height: 54,
                    alignment: Alignment.center,
                    decoration: BoxDecoration(
                      color: isDark ? AppColors.darkSurface : Colors.white,
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(
                        color: active ? AppColors.primary : borderColor,
                        width: active ? 2 : 1,
                      ),
                      boxShadow: active
                          ? [
                              BoxShadow(
                                color: AppColors.primary
                                    .withValues(alpha: 0.12),
                                blurRadius: 8,
                              ),
                            ]
                          : null,
                    ),
                    child: Text(
                      filled ? value[i] : '',
                      style: TextStyle(
                        fontSize: 24,
                        fontWeight: FontWeight.w700,
                        color: isDark
                            ? AppColors.darkTextPrimary
                            : AppColors.textPrimary,
                      ),
                    ),
                  );
                }),
              ),
              if (widget.showPaste) ...[
                const SizedBox(height: 10),
                TextButton.icon(
                  onPressed: widget.enabled ? _paste : null,
                  icon: const Icon(Icons.content_paste_rounded, size: 16),
                  label: const Text(
                    'Kodu yapıştır',
                    style:
                        TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
                  ),
                ),
              ],
            ],
          ),
        ],
      ),
    );
  }
}
