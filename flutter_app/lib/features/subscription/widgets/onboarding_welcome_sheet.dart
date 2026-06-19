import 'package:flutter/material.dart';
import 'package:qordy_app/config/theme.dart';

/// Kayıt sonrası dashboard'a düşerken bir kez gösterilen karşılama sheet'i.
/// Kullanıcıya 7 günlük ücretsiz denemeyi ve ilk adımları özetler.
class OnboardingWelcomeSheet extends StatelessWidget {
  final String businessName;
  final String subdomain;

  const OnboardingWelcomeSheet({
    super.key,
    required this.businessName,
    required this.subdomain,
  });

  static Future<void> show(
    BuildContext context, {
    required String businessName,
    required String subdomain,
  }) {
    return showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      backgroundColor: Colors.transparent,
      builder: (_) => OnboardingWelcomeSheet(
        businessName: businessName,
        subdomain: subdomain,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: const BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      padding: const EdgeInsets.fromLTRB(24, 12, 24, 24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Center(
            child: Container(
              width: 40,
              height: 4,
              decoration: BoxDecoration(
                color: Colors.black12,
                borderRadius: BorderRadius.circular(4),
              ),
            ),
          ),
          const SizedBox(height: 20),
          Row(
            children: [
              Container(
                width: 48,
                height: 48,
                decoration: BoxDecoration(
                  color: AppColors.success.withValues(alpha: 0.15),
                  borderRadius: BorderRadius.circular(14),
                ),
                child: const Icon(Icons.celebration_outlined,
                    color: AppColors.success, size: 26),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Hoş geldin, $businessName',
                      style: const TextStyle(
                          fontSize: 18, fontWeight: FontWeight.w700),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      'Hesabınız hazır — 7 gün ücretsiz deneme başladı.',
                      style: const TextStyle(
                          fontSize: 12.5, color: Colors.black54),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 20),
          _step(
            icon: Icons.restaurant_menu,
            title: 'Menünü oluştur',
            body: 'Kategori ve ürünleri ekle, fiyatları belirle.',
          ),
          _step(
            icon: Icons.table_restaurant,
            title: 'Bölge ve masa düzenini kur',
            body: 'Mobil ve POS birlikte hızlıca sipariş alır.',
          ),
          _step(
            icon: Icons.groups_2_outlined,
            title: 'Personeli davet et',
            body: 'Yönetici / kasiyer / garson rollerini ata.',
          ),
          _step(
            icon: Icons.shopping_bag_outlined,
            title: '7 gün içinde paket seç',
            body: 'Deneme bitmeden satın alırsan hiç kesinti yaşamazsın.',
          ),
          const SizedBox(height: 16),
          Row(
            children: [
              Expanded(
                child: OutlinedButton(
                  onPressed: () => Navigator.pop(context),
                  style: OutlinedButton.styleFrom(
                    padding: const EdgeInsets.symmetric(vertical: 14),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                  ),
                  child: const Text('Daha sonra'),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: ElevatedButton.icon(
                  onPressed: () => Navigator.pop(context),
                  icon: const Icon(Icons.arrow_forward),
                  label: const Text('Başla'),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppColors.primary,
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(vertical: 14),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _step({
    required IconData icon,
    required String title,
    required String body,
  }) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        children: [
          Container(
            width: 36,
            height: 36,
            decoration: BoxDecoration(
              color: AppColors.primarySoft,
              borderRadius: BorderRadius.circular(10),
            ),
            child: Icon(icon, size: 18, color: AppColors.primaryDark),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title,
                    style: const TextStyle(
                        fontSize: 13.5, fontWeight: FontWeight.w600)),
                Text(body,
                    style: const TextStyle(
                        fontSize: 12, color: Colors.black54, height: 1.3)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
