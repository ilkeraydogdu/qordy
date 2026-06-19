import 'dart:async';

import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/di/injection.dart';
import '../../../models/package_model.dart';
import '../../packages/data/packages_repository.dart';

/// Süper admin tarafından bu müşteri için hazırlanmış özel teklifleri yöneten
/// üst seviye overlay. Shell'in üzerine oturur:
///   * Sağ altta kalıcı bir "Size Özel Teklif" rozeti
///   * Sayfa açıldıktan birkaç saniye sonra ve sonra periyodik olarak açılan
///     ödeme popup'ı. Kullanıcı kapatırsa 45 dakika cooldown uygulanır
///     (backend ile senkron) ve ayrıca satın alım gerçekleşene kadar rozet
///     görünür kalır.
class CustomOfferGate extends StatefulWidget {
  final Widget child;
  const CustomOfferGate({super.key, required this.child});

  @override
  State<CustomOfferGate> createState() => _CustomOfferGateState();
}

class _CustomOfferGateState extends State<CustomOfferGate> {
  static const _localCooldownKey = 'cpl_dismiss_';
  static const _pollInterval = Duration(minutes: 15);
  static const _autoPopupDelay = Duration(seconds: 4);

  final List<AssignedOffer> _offers = <AssignedOffer>[];
  Timer? _poller;
  Timer? _autoPopup;
  bool _modalOpen = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _bootstrap());
  }

  Future<void> _bootstrap() async {
    await _load();
    _poller?.cancel();
    _poller = Timer.periodic(_pollInterval, (_) => _load());
  }

  Future<void> _load() async {
    try {
      final repo = getIt<PackagesRepository>();
      final res = await repo.getCustomOffers();
      if (!mounted) return;
      if (res.isSuccess && res.data != null) {
        setState(() {
          _offers
            ..clear()
            ..addAll(res.data!);
        });
        _maybeScheduleAutoPopup();
      }
    } catch (_) {
      // Ağ hatalarını sessizce yut — rozet olmadan bir süre beklenebilir.
    }
  }

  Future<void> _maybeScheduleAutoPopup() async {
    _autoPopup?.cancel();
    if (_modalOpen) return;
    final candidate = await _nextAutoOffer();
    if (candidate == null) return;
    _autoPopup = Timer(_autoPopupDelay, () {
      if (!mounted || _modalOpen) return;
      _openOfferModal(candidate, auto: true);
    });
  }

  Future<AssignedOffer?> _nextAutoOffer() async {
    if (_offers.isEmpty) return null;
    final prefs = await SharedPreferences.getInstance();
    final now = DateTime.now().millisecondsSinceEpoch;
    for (final offer in _offers) {
      if (offer.linkId == null || offer.linkId!.isEmpty) continue;
      if (!offer.shouldShowPopup) continue;
      final cooldownMs = offer.cooldownMinutes * 60 * 1000;
      final lastLocal = prefs.getInt('$_localCooldownKey${offer.linkId}') ?? 0;
      if (now - lastLocal < cooldownMs) continue;
      return offer;
    }
    return null;
  }

  Future<void> _openOfferModal(AssignedOffer offer, {bool auto = false}) async {
    if (!mounted) return;
    _modalOpen = true;
    await showDialog<void>(
      context: context,
      barrierDismissible: !auto,
      barrierColor: Colors.black.withValues(alpha: 0.55),
      builder: (ctx) => _CustomOfferDialog(
        offer: offer,
        onClose: () async {
          await _dismiss(offer);
          if (ctx.mounted) Navigator.of(ctx).pop();
        },
        onPurchase: () async {
          if (ctx.mounted) Navigator.of(ctx).pop();
          await _openCheckout(offer);
        },
      ),
    );
    _modalOpen = false;
  }

  Future<void> _dismiss(AssignedOffer offer) async {
    final linkId = offer.linkId;
    if (linkId == null || linkId.isEmpty) return;
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setInt(
        '$_localCooldownKey$linkId',
        DateTime.now().millisecondsSinceEpoch,
      );
    } catch (_) {}
    try {
      await getIt<PackagesRepository>().dismissCustomOffer(linkId);
    } catch (_) {}
  }

  Future<void> _openCheckout(AssignedOffer offer) async {
    final url = offer.publicUrl;
    if (url == null || url.isEmpty) return;
    final uri = Uri.tryParse(url);
    if (uri == null) return;
    try {
      await launchUrl(uri, mode: LaunchMode.externalApplication);
    } catch (_) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Ödeme sayfası açılamadı')),
      );
    }
  }

  void _openBadgeSheet() {
    if (_offers.isEmpty) return;
    _modalOpen = true;
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) => _OffersSheet(
        offers: _offers,
        onSelect: (offer) {
          Navigator.of(ctx).pop();
          _openOfferModal(offer);
        },
      ),
    ).whenComplete(() => _modalOpen = false);
  }

  @override
  void dispose() {
    _poller?.cancel();
    _autoPopup?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final count = _offers.length;
    return Stack(
      children: [
        Positioned.fill(child: widget.child),
        if (count > 0)
          Positioned(
            right: 16,
            bottom: 96,
            child: _OfferBadge(count: count, onTap: _openBadgeSheet),
          ),
      ],
    );
  }
}

class _OfferBadge extends StatelessWidget {
  final int count;
  final VoidCallback onTap;
  const _OfferBadge({required this.count, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(32),
        child: Container(
          decoration: BoxDecoration(
            gradient: const LinearGradient(
              colors: [Color(0xFF6366F1), Color(0xFF8B5CF6)],
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
            ),
            borderRadius: BorderRadius.circular(32),
            boxShadow: [
              BoxShadow(
                color: const Color(0xFF6366F1).withValues(alpha: 0.45),
                blurRadius: 16,
                offset: const Offset(0, 6),
              ),
            ],
          ),
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.card_giftcard_rounded,
                  color: Colors.white, size: 18),
              const SizedBox(width: 8),
              const Text(
                'Size Özel Teklif',
                style: TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w700,
                  fontSize: 13,
                ),
              ),
              if (count > 1) ...[
                const SizedBox(width: 8),
                Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                  decoration: BoxDecoration(
                    color: Colors.white.withValues(alpha: 0.25),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Text(
                    '$count',
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 11,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

class _CustomOfferDialog extends StatelessWidget {
  final AssignedOffer offer;
  final VoidCallback onClose;
  final VoidCallback onPurchase;

  const _CustomOfferDialog({
    required this.offer,
    required this.onClose,
    required this.onPurchase,
  });

  String _formatPrice(double? price) {
    if (price == null) return '—';
    final f = NumberFormat.currency(locale: 'tr_TR', symbol: '₺', decimalDigits: 2);
    return f.format(price);
  }

  String _durationText(int? months) {
    if (months == null || months <= 0) return 'Özel süre';
    if (months % 12 == 0) {
      final years = months ~/ 12;
      return '$years yıl';
    }
    return '$months ay';
  }

  @override
  Widget build(BuildContext context) {
    return Dialog(
      insetPadding: const EdgeInsets.all(20),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
      backgroundColor: Colors.white,
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 420),
        child: Stack(
          children: [
            Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 22, vertical: 22),
                  decoration: const BoxDecoration(
                    gradient: LinearGradient(
                      colors: [Color(0xFF4F46E5), Color(0xFF7C3AED)],
                      begin: Alignment.topLeft,
                      end: Alignment.bottomRight,
                    ),
                    borderRadius: BorderRadius.only(
                      topLeft: Radius.circular(24),
                      topRight: Radius.circular(24),
                    ),
                  ),
                  child: Row(
                    children: [
                      Container(
                        width: 44,
                        height: 44,
                        decoration: BoxDecoration(
                          color: Colors.white.withValues(alpha: 0.22),
                          borderRadius: BorderRadius.circular(14),
                        ),
                        child: const Icon(Icons.card_giftcard_rounded,
                            color: Colors.white),
                      ),
                      const SizedBox(width: 14),
                      const Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              'Size Özel Teklif',
                              style: TextStyle(
                                color: Colors.white,
                                fontSize: 17,
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                            SizedBox(height: 2),
                            Text(
                              'Yalnızca size hazırlandı',
                              style: TextStyle(
                                color: Colors.white70,
                                fontSize: 12,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
                Padding(
                  padding: const EdgeInsets.fromLTRB(22, 18, 22, 22),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        offer.packageName ?? 'Özel Paket',
                        style: const TextStyle(
                          fontSize: 20,
                          fontWeight: FontWeight.w900,
                          color: Color(0xFF0F172A),
                        ),
                      ),
                      const SizedBox(height: 6),
                      Text(
                        _durationText(offer.durationMonths),
                        style: const TextStyle(
                          fontSize: 13,
                          color: Color(0xFF64748B),
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                      const SizedBox(height: 14),
                      Container(
                        padding: const EdgeInsets.all(16),
                        decoration: BoxDecoration(
                          color: const Color(0xFFF1F5F9),
                          borderRadius: BorderRadius.circular(16),
                        ),
                        child: Row(
                          children: [
                            const Icon(Icons.local_offer_rounded,
                                color: Color(0xFF4F46E5)),
                            const SizedBox(width: 10),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  const Text(
                                    'Size Özel Fiyat',
                                    style: TextStyle(
                                      fontSize: 12,
                                      color: Color(0xFF64748B),
                                      fontWeight: FontWeight.w600,
                                    ),
                                  ),
                                  const SizedBox(height: 2),
                                  Text(
                                    _formatPrice(offer.customPrice),
                                    style: const TextStyle(
                                      fontSize: 22,
                                      fontWeight: FontWeight.w900,
                                      color: Color(0xFF0F172A),
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ],
                        ),
                      ),
                      if ((offer.note ?? '').isNotEmpty) ...[
                        const SizedBox(height: 14),
                        Text(
                          offer.note!,
                          style: const TextStyle(
                            fontSize: 13,
                            color: Color(0xFF475569),
                            height: 1.4,
                          ),
                        ),
                      ],
                      const SizedBox(height: 20),
                      SizedBox(
                        width: double.infinity,
                        child: FilledButton(
                          onPressed: onPurchase,
                          style: FilledButton.styleFrom(
                            backgroundColor: const Color(0xFF4F46E5),
                            padding:
                                const EdgeInsets.symmetric(vertical: 14),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(14),
                            ),
                          ),
                          child: const Text(
                            'Şimdi Satın Al',
                            style: TextStyle(
                              fontSize: 15,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                        ),
                      ),
                      const SizedBox(height: 8),
                      SizedBox(
                        width: double.infinity,
                        child: TextButton(
                          onPressed: onClose,
                          child: const Text(
                            'Daha sonra hatırlat',
                            style: TextStyle(
                              color: Color(0xFF64748B),
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
            Positioned(
              top: 12,
              right: 12,
              child: IconButton(
                icon: const Icon(Icons.close_rounded, color: Colors.white),
                onPressed: onClose,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _OffersSheet extends StatelessWidget {
  final List<AssignedOffer> offers;
  final ValueChanged<AssignedOffer> onSelect;

  const _OffersSheet({required this.offers, required this.onSelect});

  @override
  Widget build(BuildContext context) {
    return DraggableScrollableSheet(
      initialChildSize: 0.55,
      minChildSize: 0.35,
      maxChildSize: 0.92,
      expand: false,
      builder: (_, controller) => Container(
        decoration: const BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
        ),
        child: ListView(
          controller: controller,
          padding: const EdgeInsets.fromLTRB(16, 14, 16, 24),
          children: [
            Center(
              child: Container(
                width: 40,
                height: 4,
                decoration: BoxDecoration(
                  color: const Color(0xFFCBD5E1),
                  borderRadius: BorderRadius.circular(2),
                ),
              ),
            ),
            const SizedBox(height: 16),
            const Text(
              'Size Özel Teklifler',
              style: TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.w900,
                color: Color(0xFF0F172A),
              ),
            ),
            const SizedBox(height: 4),
            const Text(
              'Süper admin tarafından sizin için hazırlandı.',
              style: TextStyle(color: Color(0xFF64748B), fontSize: 13),
            ),
            const SizedBox(height: 14),
            for (final o in offers) ...[
              _OfferCard(offer: o, onTap: () => onSelect(o)),
              const SizedBox(height: 10),
            ],
          ],
        ),
      ),
    );
  }
}

class _OfferCard extends StatelessWidget {
  final AssignedOffer offer;
  final VoidCallback onTap;
  const _OfferCard({required this.offer, required this.onTap});

  @override
  Widget build(BuildContext context) {
    final price = offer.customPrice;
    final formatted = price == null
        ? '—'
        : NumberFormat.currency(locale: 'tr_TR', symbol: '₺', decimalDigits: 2)
            .format(price);
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(18),
      child: Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: const Color(0xFFF8FAFC),
          borderRadius: BorderRadius.circular(18),
          border: Border.all(color: const Color(0xFFE2E8F0)),
        ),
        child: Row(
          children: [
            Container(
              width: 44,
              height: 44,
              decoration: BoxDecoration(
                gradient: const LinearGradient(
                  colors: [Color(0xFF6366F1), Color(0xFF8B5CF6)],
                ),
                borderRadius: BorderRadius.circular(12),
              ),
              child: const Icon(Icons.local_offer_rounded,
                  color: Colors.white),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    offer.packageName ?? 'Özel Paket',
                    style: const TextStyle(
                      fontWeight: FontWeight.w800,
                      color: Color(0xFF0F172A),
                      fontSize: 15,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    offer.durationMonths != null
                        ? '${offer.durationMonths} ay'
                        : 'Özel süre',
                    style: const TextStyle(
                      fontSize: 12,
                      color: Color(0xFF64748B),
                    ),
                  ),
                ],
              ),
            ),
            Column(
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                Text(
                  formatted,
                  style: const TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.w900,
                    color: Color(0xFF4F46E5),
                  ),
                ),
                const SizedBox(height: 4),
                const Icon(Icons.arrow_forward_rounded,
                    color: Color(0xFF64748B), size: 18),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
