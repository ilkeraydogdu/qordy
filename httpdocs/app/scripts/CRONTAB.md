# Qordy Cron Görevleri

Trial / Grace Period / Abonelik otomasyonu için iki script:

| Script | Amaç | Önerilen Frekans |
|--------|------|------------------|
| `check_trial_expiry.php` | Trial süresi bitenleri `grace_period`'a alır, uyarı/bitiş mail'lerini gönderir, ayrıca grace'i bitenleri `suspended`'a alır | 6 saatte bir (günde min. 1) |
| `check_grace_expiry.php` | Grace süresi bitenleri `suspended`'a alır, hesap askıya alındı bildirimi atar | 6 saatte bir (günde min. 1) |
| `push_trial_reminders.php` | Son 3/1/0 gün kalan trial'lara mobil push bildirimi (FCM) | Günde 1 (örn. 09:00) |

## Örnek crontab

```cron
# Trial süresi ve grace period kontrolü (6 saatte bir)
0 */6 * * * /opt/plesk/php/8.2/bin/php /var/www/vhosts/qordy.com/httpdocs/app/scripts/check_trial_expiry.php >> /var/log/qordy/trial.log 2>&1
15 */6 * * * /opt/plesk/php/8.2/bin/php /var/www/vhosts/qordy.com/httpdocs/app/scripts/check_grace_expiry.php >> /var/log/qordy/grace.log 2>&1

# Trial push hatırlatıcıları (günde bir, sabah 09:00)
0 9 * * * /opt/plesk/php/8.2/bin/php /var/www/vhosts/qordy.com/httpdocs/app/scripts/push_trial_reminders.php >> /var/log/qordy/push.log 2>&1
```

## Faz geçişleri

```
trial (7 gün) ─ süresi dolunca ──▶ grace_period (7 gün, salt okunur)
                                     │
                                     ├─ paket satın alırsa ──▶ active
                                     └─ grace da dolunca ──▶ suspended
```

`TrialMiddleware` her request'te `checkAndExpireTrials()` ve `checkAndSuspendGraceExpired()`'ı çağırır — cron yedek güvencedir.
