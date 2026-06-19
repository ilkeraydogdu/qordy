import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:qordy_app/config/theme.dart';
import 'package:qordy_app/core/theme/brand_styles.dart';

void main() {
  testWidgets('BrandCard and SectionHeader render inside theme', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.light,
        home: Scaffold(
          body: ListView(
            padding: const EdgeInsets.all(16),
            children: [
              const SectionHeader(
                title: 'Bölüm',
                subtitle: 'Alt başlık',
              ),
              BrandCard(
                child: const Text('İçerik'),
              ),
            ],
          ),
        ),
      ),
    );

    expect(find.text('Bölüm'), findsOneWidget);
    expect(find.text('İçerik'), findsOneWidget);
    final ctx = tester.element(find.text('İçerik'));
    expect(BrandStyles.of(ctx).cardRadius, 16);
  });

  testWidgets('BrandPanel renders without shadow', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.light,
        home: const Scaffold(
          body: Center(
            child: BrandPanel(child: Text('Panel')),
          ),
        ),
      ),
    );
    expect(find.text('Panel'), findsOneWidget);
  });

  testWidgets('BrandInfoCallout renders all tones', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.light,
        home: Scaffold(
          body: ListView(
            padding: const EdgeInsets.all(12),
            children: const [
              BrandInfoCallout(
                message: 'Warning text',
                title: 'Dikkat',
              ),
              SizedBox(height: 12),
              BrandInfoCallout(
                message: 'Info text',
                tone: BrandCalloutTone.info,
              ),
              SizedBox(height: 12),
              BrandInfoCallout(
                message: 'Success text',
                tone: BrandCalloutTone.success,
              ),
              SizedBox(height: 12),
              BrandInfoCallout(
                message: 'Danger text',
                tone: BrandCalloutTone.danger,
              ),
            ],
          ),
        ),
      ),
    );

    expect(find.text('Warning text'), findsOneWidget);
    expect(find.text('Info text'), findsOneWidget);
    expect(find.text('Success text'), findsOneWidget);
    expect(find.text('Danger text'), findsOneWidget);
    expect(find.text('Dikkat'), findsOneWidget);
  });

  testWidgets('BrandStyles tokens are populated in dark theme too', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.light,
        darkTheme: AppTheme.dark,
        themeMode: ThemeMode.dark,
        home: const Scaffold(body: Text('probe')),
      ),
    );
    final ctx = tester.element(find.text('probe'));
    final styles = BrandStyles.of(ctx);
    expect(styles.cardRadius, 16);
    expect(styles.sheetRadius, 20);
    expect(styles.tileRadius, 12);
    expect(styles.buttonRadius, 12);
    expect(styles.inputRadius, 12);
    expect(styles.pillRadius, greaterThan(100));
    expect(styles.tagRadius, 6);
  });
}
