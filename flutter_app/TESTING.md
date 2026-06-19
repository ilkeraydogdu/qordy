# Qordy mobil — test ve CI

## Yerel

```bash
cd /var/www/vhosts/qordy.com/flutter_app
/opt/flutter/bin/flutter pub get
/opt/flutter/bin/flutter test
```

## Önerilen CI adımı

Aynı komutlar; başarısız testte build durmalı.

### Kapsam

- `test/features/auth/auth_cubit_test.dart` — subdomain doğrulama / durum makinesi
- `test/core/navigation/role_home_test.dart` — rol yönlendirme
- `test/core/network/safe_json_test.dart` — JSON güvenliği
- `test/smoke/brand_widgets_smoke_test.dart` — tema + marka bileşenleri duman testi

## PHP (httpdocs)

```bash
cd /var/www/vhosts/qordy.com/httpdocs
/opt/plesk/php/8.3/bin/php /usr/local/psa/var/modules/composer/composer.phar dump-autoload -o
/opt/plesk/php/8.3/bin/php vendor/bin/phpunit -c phpunit.xml.dist
```

İlk çalıştırmada `composer install` gerekebilir.
