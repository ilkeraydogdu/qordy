import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:go_router/go_router.dart';

import '../../../config/theme.dart';

/// Hub / landing page for the admin pages that were previously only
/// available on the web dashboard (printer bridges, queue, receipt
/// templates, finance…).
///
/// Entries are grouped into sections so the list stays scannable when
/// we add more. SUPERADMIN-only features (payment gateway switches,
/// platform feature flags, global error log) are **not** shown here —
/// those belong in `https://qordy.com/qodmin` and must never leak into
/// the tenant-owner surface.
class AdminHubScreen extends StatelessWidget {
  const AdminHubScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final sections = <_AdminSection>[
      _AdminSection(
        title: 'Operasyon',
        entries: [
          _AdminEntry(
            icon: Icons.print_outlined,
            label: 'Yazıcılar & Köprüler',
            subtitle: 'Köprü oluştur, yazıcı ata, bağlantı testi',
            accent: AppColors.primary,
            route: '/admin/printers',
          ),
          _AdminEntry(
            icon: Icons.queue_outlined,
            label: 'Sıra Yönetimi',
            subtitle: 'Aktif sıra, ayarlar ve sıradan çağır',
            accent: AppColors.info,
            route: '/admin/queue',
          ),
          _AdminEntry(
            icon: Icons.receipt_long_outlined,
            label: 'Fiş Şablonları',
            subtitle: 'Fiş başlık/altlık şablonlarını yönet',
            accent: AppColors.warning,
            route: '/admin/receipt-templates',
          ),
          _AdminEntry(
            icon: Icons.point_of_sale_outlined,
            label: 'POS Cihazları',
            subtitle: 'İşletmenize kayıtlı terminal ve tabletler',
            accent: AppColors.primary,
            route: '/admin/pos-devices',
          ),
        ],
      ),
      _AdminSection(
        title: 'Personel & Yetkilendirme',
        entries: [
          _AdminEntry(
            icon: Icons.shield_outlined,
            label: 'Roller & İzinler',
            subtitle: 'Rol bazlı izin matrisi',
            accent: AppColors.accentPurple,
            route: '/admin/roles-permissions',
          ),
          _AdminEntry(
            icon: Icons.fact_check_outlined,
            label: 'Sipariş Onay Geçmişi',
            subtitle: 'Onaylanan/reddedilen talepler',
            accent: AppColors.success,
            route: '/admin/approval-history',
          ),
        ],
      ),
      _AdminSection(
        title: 'Finans',
        entries: [
          _AdminEntry(
            icon: Icons.receipt_outlined,
            label: 'Faturalar',
            subtitle: 'Gelen faturaları yönet',
            accent: AppColors.primary,
            route: '/admin/invoices',
          ),
          _AdminEntry(
            icon: Icons.local_shipping_outlined,
            label: 'Tedarikçiler',
            subtitle: 'Tedarikçi rehberi',
            accent: AppColors.info,
            route: '/admin/suppliers',
          ),
          _AdminEntry(
            icon: Icons.delete_sweep_outlined,
            label: 'Fire / Zayiat',
            subtitle: 'Günlük kayıp kayıtları',
            accent: AppColors.error,
            route: '/admin/waste',
          ),
        ],
      ),
      _AdminSection(
        title: 'Analiz',
        entries: [
          _AdminEntry(
            icon: Icons.insert_chart_outlined,
            label: 'Raporlar',
            subtitle: 'Satış, ürün ve dönem raporları',
            accent: AppColors.accentPurple,
            route: '/admin/reports',
          ),
        ],
      ),
    ];

    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        title: Text(
          'Gelişmiş Yönetim',
          style: TextStyle(
            color: context.brandTextPrimary,
            fontWeight: FontWeight.w700,
            fontSize: 18,
          ),
        ),
        backgroundColor: Theme.of(context).scaffoldBackgroundColor,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        scrolledUnderElevation: 0.5,
      ),
      body: ListView.builder(
        padding: const EdgeInsets.fromLTRB(
          AppSpacing.md,
          AppSpacing.sm,
          AppSpacing.md,
          AppSpacing.xxl,
        ),
        itemCount: sections.length + 1,
        itemBuilder: (_, idx) {
          if (idx == 0) return const _AdminHero();
          return _SectionBlock(section: sections[idx - 1]);
        },
      ),
    );
  }
}

/// Decorative hero block at the top of the admin hub. Acts as a
/// visual anchor so the section list below feels intentional rather
/// than a lost plain ListView.
class _AdminHero extends StatelessWidget {
  const _AdminHero();

  @override
  Widget build(BuildContext context) {
    final dark = context.isDark;
    return Container(
      margin: const EdgeInsets.only(bottom: AppSpacing.lg),
      padding: const EdgeInsets.all(AppSpacing.lg),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: dark
              ? const [Color(0xFF17223F), Color(0xFF0F1628)]
              : [
                  AppColors.primary.withValues(alpha: 0.06),
                  AppColors.accentPurple.withValues(alpha: 0.05),
                ],
        ),
        borderRadius: BorderRadius.circular(AppRadius.lg),
        border: Border.all(color: context.brandBorder, width: 0.6),
      ),
      child: Row(
        children: [
          Container(
            width: 48,
            height: 48,
            decoration: BoxDecoration(
              gradient: AppColors.brandGradient,
              borderRadius: BorderRadius.circular(AppRadius.md),
              boxShadow: AppShadows.hero(dark),
            ),
            child: const Icon(Icons.tune_rounded,
                color: Colors.white, size: 22),
          ),
          const SizedBox(width: AppSpacing.md),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'İşletme Yönetimi',
                  style: TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.w800,
                    color: context.brandTextPrimary,
                    letterSpacing: -0.2,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  'Yazıcı, sıra, fiş ve finans ayarlarınız tek yerde',
                  style: TextStyle(
                    fontSize: 12.5,
                    color: context.brandTextSecondary,
                    height: 1.3,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _AdminSection {
  final String title;
  final List<_AdminEntry> entries;

  const _AdminSection({required this.title, required this.entries});
}

class _AdminEntry {
  final IconData icon;
  final String label;
  final String subtitle;
  final Color accent;
  final String route;

  const _AdminEntry({
    required this.icon,
    required this.label,
    required this.subtitle,
    required this.accent,
    required this.route,
  });
}

class _SectionBlock extends StatelessWidget {
  const _SectionBlock({required this.section});

  final _AdminSection section;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: AppSpacing.lg),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(4, 8, 4, 10),
            child: Text(
              section.title.toUpperCase(),
              style: TextStyle(
                fontSize: 11,
                fontWeight: FontWeight.w700,
                color: context.brandTextHint,
                letterSpacing: 0.8,
              ),
            ),
          ),
          Container(
            decoration: BoxDecoration(
              color: Theme.of(context).cardColor,
              borderRadius: BorderRadius.circular(AppRadius.lg),
              border: Border.all(color: context.brandBorder, width: 0.6),
              boxShadow: AppShadows.card(context.isDark),
            ),
            child: Column(
              children: [
                for (var i = 0; i < section.entries.length; i++) ...[
                  if (i > 0)
                    Divider(
                      height: 1,
                      thickness: 1,
                      color: context.brandBorder.withValues(alpha: 0.5),
                      indent: 68,
                    ),
                  _AdminTile(entry: section.entries[i]),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _AdminTile extends StatelessWidget {
  const _AdminTile({required this.entry});

  final _AdminEntry entry;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: () {
        HapticFeedback.selectionClick();
        context.push(entry.route);
      },
      borderRadius: BorderRadius.circular(AppRadius.lg),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(
          AppSpacing.md,
          AppSpacing.md,
          AppSpacing.md,
          AppSpacing.md,
        ),
        child: Row(
          children: [
            Container(
              width: 42,
              height: 42,
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                  colors: [
                    entry.accent.withValues(alpha: 0.18),
                    entry.accent.withValues(alpha: 0.08),
                  ],
                ),
                borderRadius: BorderRadius.circular(AppRadius.md),
                border: Border.all(
                  color: entry.accent.withValues(alpha: 0.18),
                  width: 0.6,
                ),
              ),
              child: Icon(entry.icon, color: entry.accent, size: 20),
            ),
            const SizedBox(width: AppSpacing.md),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    entry.label,
                    style: TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.w700,
                      color: context.brandTextPrimary,
                      letterSpacing: -0.1,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    entry.subtitle,
                    style: TextStyle(
                      fontSize: 12.5,
                      color: context.brandTextSecondary,
                      height: 1.25,
                    ),
                  ),
                ],
              ),
            ),
            Icon(
              Icons.chevron_right_rounded,
              size: 20,
              color: context.brandTextHint,
            ),
          ],
        ),
      ),
    );
  }
}
