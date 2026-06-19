import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:qordy_app/config/theme.dart';
import 'package:qordy_app/features/packages/cubit/packages_cubit.dart';
import 'package:qordy_app/features/packages/cubit/packages_state.dart';
import 'package:webview_flutter/webview_flutter.dart';

/// iyzico hosted checkout form'unu WebView içinde çalıştırır. Backend
/// `PackagesCubit.beginCheckout` çağrısı sonrası cubit'ten gelen
/// [PackageCheckoutReady] HTML'i render eder ve iyzico bizi deeplink
/// callback'ine (qordy://payment/return?status=…&token=…) geri
/// gönderdiğinde navigation request'i yakalayıp
/// [PackagesCubit.finalizePurchase] ile cubit'e sonucu bildirir.
class PaymentCheckoutScreen extends StatefulWidget {
  final PackageCheckoutReady checkout;

  const PaymentCheckoutScreen({super.key, required this.checkout});

  @override
  State<PaymentCheckoutScreen> createState() => _PaymentCheckoutScreenState();
}

class _PaymentCheckoutScreenState extends State<PaymentCheckoutScreen> {
  late final WebViewController _controller;
  bool _loading = true;
  bool _deeplinkHandled = false;

  @override
  void initState() {
    super.initState();
    _controller = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setBackgroundColor(Colors.white)
      ..setNavigationDelegate(NavigationDelegate(
        onNavigationRequest: (req) => _maybeCatchDeeplink(req.url),
        onUrlChange: (change) {
          final url = change.url;
          if (url != null) _maybeCatchDeeplink(url);
        },
        onPageStarted: (_) {
          if (mounted) setState(() => _loading = true);
        },
        onPageFinished: (_) {
          if (mounted) setState(() => _loading = false);
        },
        onWebResourceError: (err) {
          // Bazı Android WebView sürümleri custom scheme'i
          // ERR_UNKNOWN_URL_SCHEME olarak raporluyor — bu bir hata değil,
          // deeplink yakalayamadık demek. Ignore.
        },
      ));

    _loadContent();
  }

  Future<void> _loadContent() async {
    final checkout = widget.checkout;
    final pageUrl = checkout.paymentPageUrl;
    if (pageUrl != null && pageUrl.isNotEmpty) {
      await _controller.loadRequest(Uri.parse(pageUrl));
    } else {
      await _controller.loadHtmlString(_wrapHtml(checkout.checkoutHtml));
    }
  }

  NavigationDecision _maybeCatchDeeplink(String url) {
    if (_deeplinkHandled) return NavigationDecision.prevent;

    final isReturn = url.startsWith(widget.checkout.returnUrl) ||
        url.startsWith('qordy://payment');
    if (!isReturn) return NavigationDecision.navigate;

    _deeplinkHandled = true;
    final uri = Uri.tryParse(url);
    final status = uri?.queryParameters['status'] ?? 'fail';
    final token = uri?.queryParameters['token'];
    final error = uri?.queryParameters['error'];

    // Cubit'e bildir ve ekrandan çık.
    final cubit = context.read<PackagesCubit>();
    cubit.finalizePurchase(
      status: status,
      token: token ?? widget.checkout.token,
      conversationId: widget.checkout.conversationId,
      subscriptionId: widget.checkout.subscriptionId,
      errorMessage: error,
    );
    Navigator.of(context).pop();
    return NavigationDecision.prevent;
  }

  String _wrapHtml(String body) => '''
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1,user-scalable=yes" />
  <title>Güvenli Ödeme</title>
  <style>
    body{margin:0;padding:0;background:#fff;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;}
    #iyzipay-checkout-form{padding:12px;}
  </style>
</head>
<body>
  <div id="iyzipay-checkout-form" class="responsive"></div>
  $body
</body>
</html>
''';

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return PopScope(
      canPop: true,
      onPopInvokedWithResult: (didPop, _) {
        if (didPop && !_deeplinkHandled) {
          context.read<PackagesCubit>().cancelCheckout();
        }
      },
      child: Scaffold(
        backgroundColor: Colors.white,
        appBar: AppBar(
          backgroundColor: Colors.white,
          foregroundColor: AppColors.textPrimary,
          surfaceTintColor: Colors.white,
          elevation: 0,
          leading: IconButton(
            icon: const Icon(Icons.close),
            onPressed: () {
              context.read<PackagesCubit>().cancelCheckout();
              Navigator.of(context).pop();
            },
          ),
          title: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              const Text(
                'Güvenli Ödeme',
                style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700),
              ),
              Text(
                '${widget.checkout.packageName} · ₺${widget.checkout.amount.toStringAsFixed(2)}',
                style: TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w500,
                  color: AppColors.textSecondary,
                ),
              ),
            ],
          ),
          actions: [
            Padding(
              padding: const EdgeInsets.only(right: 12),
              child: Center(
                child: Row(
                  children: [
                    const Icon(Icons.lock_outline, size: 16, color: AppColors.success),
                    const SizedBox(width: 4),
                    Text(
                      'iyzico',
                      style: TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.w600,
                        color: AppColors.textSecondary,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
        body: Stack(
          children: [
            WebViewWidget(controller: _controller),
            if (_loading)
              Container(
                color: isDark ? Colors.black : Colors.white,
                child: const Center(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      CircularProgressIndicator(color: AppColors.primary),
                      SizedBox(height: 16),
                      Text(
                        'Güvenli ödeme formu hazırlanıyor…',
                        style: TextStyle(
                          fontSize: 14,
                          color: AppColors.textSecondary,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}
