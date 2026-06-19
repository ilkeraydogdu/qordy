import 'package:flutter/material.dart';
import '../../config/theme.dart';

/// Marka kimliğine uygun yükleniyor göstergesi. İki katmanlı bir dış
/// hale ile birlikte küçük spinner'ı "float" ettirir — çıplak
/// `CircularProgressIndicator`'a kıyasla ekrana biraz nefes katıyor.
class LoadingWidget extends StatelessWidget {
  final String? message;
  final double size;

  const LoadingWidget({
    super.key,
    this.message,
    this.size = 40,
  });

  @override
  Widget build(BuildContext context) {
    final dark = Theme.of(context).brightness == Brightness.dark;
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: size + 16,
            height: size + 16,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              gradient: RadialGradient(
                colors: [
                  AppColors.primary.withValues(alpha: dark ? 0.22 : 0.12),
                  AppColors.primary.withValues(alpha: 0),
                ],
              ),
            ),
            alignment: Alignment.center,
            child: SizedBox(
              width: size,
              height: size,
              child: const CircularProgressIndicator(
                color: AppColors.primary,
                strokeWidth: 3,
              ),
            ),
          ),
          if (message != null) ...[
            const SizedBox(height: 16),
            Text(
              message!,
              style: TextStyle(
                fontSize: 13.5,
                fontWeight: FontWeight.w500,
                color: dark
                    ? AppColors.darkTextSecondary
                    : AppColors.textSecondary,
              ),
              textAlign: TextAlign.center,
            ),
          ],
        ],
      ),
    );
  }
}

class LoadingOverlay extends StatelessWidget {
  final Widget child;
  final bool isLoading;
  final String? message;
  final Color barrierColor;

  const LoadingOverlay({
    super.key,
    required this.child,
    required this.isLoading,
    this.message,
    this.barrierColor = const Color(0x80FFFFFF),
  });

  @override
  Widget build(BuildContext context) {
    return Stack(
      children: [
        child,
        if (isLoading)
          Positioned.fill(
            child: AbsorbPointer(
              child: Container(
                color: barrierColor,
                child: LoadingWidget(message: message),
              ),
            ),
          ),
      ],
    );
  }
}
