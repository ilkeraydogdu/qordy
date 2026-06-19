import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../../config/theme.dart';

/// Single-field text prompt used by every admin screen that needs a
/// small dialog for "rename" / "add" style interactions.
Future<String?> promptText(
  BuildContext context, {
  required String title,
  required String label,
  String? initial,
  String? hint,
  int? maxLength,
  TextInputType? keyboardType,
}) async {
  final controller = TextEditingController(text: initial ?? '');
  return showDialog<String>(
    context: context,
    builder: (_) => AlertDialog(
      title: Text(title),
      content: TextField(
        controller: controller,
        autofocus: true,
        maxLength: maxLength,
        keyboardType: keyboardType,
        decoration: InputDecoration(
          labelText: label,
          hintText: hint,
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.of(context).pop(),
          child: const Text('İptal'),
        ),
        FilledButton(
          style: FilledButton.styleFrom(backgroundColor: AppColors.primary),
          onPressed: () => Navigator.of(context).pop(controller.text),
          child: const Text('Kaydet'),
        ),
      ],
    ),
  );
}

/// Destructive-action confirmation dialog. Returns `true` on confirm.
Future<bool> confirm(
  BuildContext context, {
  required String title,
  required String message,
  String confirmLabel = 'Sil',
  Color? confirmColor,
}) async {
  final ok = await showDialog<bool>(
    context: context,
    builder: (_) => AlertDialog(
      title: Text(title),
      content: Text(message),
      actions: [
        TextButton(
          onPressed: () => Navigator.of(context).pop(false),
          child: const Text('İptal'),
        ),
        FilledButton(
          style: FilledButton.styleFrom(
            backgroundColor: confirmColor ?? AppColors.error,
          ),
          onPressed: () => Navigator.of(context).pop(true),
          child: Text(confirmLabel),
        ),
      ],
    ),
  );
  return ok == true;
}

/// Floating snackbar used in place of `.showSnackBar(...)`-calls that
/// the old manager screens scattered everywhere.
void snack(BuildContext context, String msg) {
  ScaffoldMessenger.of(context).showSnackBar(
    SnackBar(
      content: Text(msg.isEmpty ? 'Tamamlandı' : msg),
      behavior: SnackBarBehavior.floating,
    ),
  );
}

/// Copy-to-clipboard helper used by the printer-bridge API-key dialog.
Future<void> copyToClipboard(BuildContext context, String value,
    {String label = 'Kopyalandı'}) async {
  await Clipboard.setData(ClipboardData(text: value));
  if (!context.mounted) return;
  snack(context, label);
}

/// Multi-field bottom sheet for "New supplier / invoice / waste" style
/// forms. Every field is a simple TextField driven by [fields] — key
/// names must match what the backend expects. Returns the filled-in
/// map (with trimmed values) on save, or null on cancel.
Future<Map<String, String>?> showFormSheet(
  BuildContext context, {
  required String title,
  required List<FormFieldDef> fields,
  Map<String, String> initial = const {},
}) async {
  final controllers = {
    for (final f in fields) f.key: TextEditingController(
      text: initial[f.key] ?? f.initial ?? '',
    ),
  };

  return showModalBottomSheet<Map<String, String>>(
    context: context,
    isScrollControlled: true,
    backgroundColor: Theme.of(context).scaffoldBackgroundColor,
    shape: const RoundedRectangleBorder(
      borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
    ),
    builder: (ctx) {
      final viewInsets = MediaQuery.of(ctx).viewInsets;
      return Padding(
        padding: EdgeInsets.only(bottom: viewInsets.bottom),
        child: SafeArea(
          top: false,
          child: SingleChildScrollView(
            padding: const EdgeInsets.fromLTRB(20, 20, 20, 20),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              mainAxisSize: MainAxisSize.min,
              children: [
                Center(
                  child: Container(
                    width: 40,
                    height: 4,
                    margin: const EdgeInsets.only(bottom: 12),
                    decoration: BoxDecoration(
                      color: context.brandBorder,
                      borderRadius: BorderRadius.circular(2),
                    ),
                  ),
                ),
                Text(title,
                    style: TextStyle(
                        fontSize: 17,
                        fontWeight: FontWeight.w700,
                        color: context.brandTextPrimary)),
                const SizedBox(height: 14),
                ...fields.map((f) => Padding(
                      padding: const EdgeInsets.only(bottom: 10),
                      child: TextField(
                        controller: controllers[f.key],
                        keyboardType: f.keyboardType,
                        maxLines: f.multiline ? 3 : 1,
                        minLines: f.multiline ? 2 : 1,
                        decoration: InputDecoration(
                          labelText: f.label,
                          hintText: f.hint,
                          border: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(10),
                          ),
                        ),
                      ),
                    )),
                const SizedBox(height: 8),
                FilledButton(
                  style: FilledButton.styleFrom(
                    backgroundColor: AppColors.primary,
                    padding: const EdgeInsets.symmetric(vertical: 14),
                  ),
                  onPressed: () {
                    final out = <String, String>{};
                    for (final f in fields) {
                      out[f.key] = controllers[f.key]!.text.trim();
                    }
                    Navigator.of(ctx).pop(out);
                  },
                  child: const Text(
                    'Kaydet',
                    style: TextStyle(
                        color: Colors.white,
                        fontWeight: FontWeight.w700,
                        fontSize: 15),
                  ),
                ),
              ],
            ),
          ),
        ),
      );
    },
  );
}

class FormFieldDef {
  final String key;
  final String label;
  final String? hint;
  final String? initial;
  final bool multiline;
  final TextInputType? keyboardType;

  const FormFieldDef({
    required this.key,
    required this.label,
    this.hint,
    this.initial,
    this.multiline = false,
    this.keyboardType,
  });
}
