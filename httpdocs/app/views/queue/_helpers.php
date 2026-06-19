<?php
/**
 * Shared helpers for the public queue views.
 *
 *   qd_business_name($biz)          - resolved business display name
 *   qd_logo_url($biz)               - absolute logo url or empty
 *   qd_logo_markup($biz, $size)     - <img> when logo exists, initials avatar otherwise
 *   qd_dict($lang)                  - i18n dictionary for the form/status pages
 *   qd_display_dict($lang)          - dictionary for the door display
 *   qd_accent_pair($settings)       - [$theme, $accent] colour pair
 *   qd_safe(string $v)              - shorthand for htmlspecialchars
 */

if (!function_exists('qd_safe')) {
    function qd_safe($v): string
    {
        return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('qd_youtube_video_id')) {
    /**
     * Extract an 11-character YouTube video id from a user-supplied URL, or null.
     */
    function qd_youtube_video_id(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }
        if (preg_match('#^(?:https?://)?(?:www\.)?youtu\.be/([a-zA-Z0-9_-]{11})\b#i', $url, $m)) {
            return $m[1];
        }
        if (preg_match('#[?&]v=([a-zA-Z0-9_-]{11})\b#', $url, $m)) {
            return $m[1];
        }
        if (preg_match('#/embed/([a-zA-Z0-9_-]{11})\b#', $url, $m)) {
            return $m[1];
        }
        if (preg_match('#/shorts/([a-zA-Z0-9_-]{11})\b#', $url, $m)) {
            return $m[1];
        }
        return null;
    }
}

if (!function_exists('qd_business_name')) {
    function qd_business_name(array $biz): string
    {
        $n = trim((string) ($biz['company_name'] ?? ''));
        if ($n !== '') return $n;
        $n = trim(($biz['first_name'] ?? '') . ' ' . ($biz['last_name'] ?? ''));
        return $n !== '' ? $n : 'Qordy';
    }
}

if (!function_exists('qd_logo_url')) {
    function qd_logo_url(array $biz): string
    {
        $url = trim((string) ($biz['logo_url'] ?? ''));
        if ($url !== '') return $url;

        $path = trim((string) ($biz['logo_path'] ?? ''));
        if ($path === '') return '';

        if (preg_match('#^https?://#i', $path)) return $path;
        // Relative path -> project absolute URL (dynamic)
        if (class_exists('\App\Services\BaseUrlService')) {
            try {
                $base = rtrim(\App\Services\BaseUrlService::getBaseUrl(), '/');
                return $base . '/' . ltrim($path, '/');
            } catch (\Throwable $e) {
                // ignore
            }
        }
        return '/' . ltrim($path, '/');
    }
}

if (!function_exists('qd_logo_markup')) {
    function qd_logo_markup(array $biz, int $size = 64, string $extraClass = ''): string
    {
        $url = qd_logo_url($biz);
        $name = qd_business_name($biz);
        $initials = '';
        $parts = preg_split('/\s+/', trim($name));
        foreach ($parts as $p) {
            if ($p === '') continue;
            $initials .= mb_strtoupper(mb_substr($p, 0, 1));
            if (mb_strlen($initials) >= 2) break;
        }
        if ($initials === '') $initials = 'Q';

        $cls = 'qd-logo ' . qd_safe($extraClass);
        $style = "width:{$size}px;height:{$size}px";
        if ($url !== '') {
            return '<div class="' . $cls . '" style="' . $style . '">'
                 . '<img src="' . qd_safe($url) . '" alt="' . qd_safe($name) . '" />'
                 . '</div>';
        }
        return '<div class="' . $cls . ' qd-logo-initials" style="' . $style . '">'
             . qd_safe($initials) . '</div>';
    }
}

if (!function_exists('qd_accent_pair')) {
    function qd_accent_pair(array $settings): array
    {
        $theme = $settings['display_theme_color']  ?? '#0f172a';
        $accent = $settings['display_accent_color'] ?? '#f97316';
        return [$theme, $accent];
    }
}

if (!function_exists('qd_dict')) {
    function qd_dict(string $lang): array
    {
        $d = [
            'tr' => [
                'welcome'     => 'Hoş geldiniz',
                'join_title'  => 'Sıraya katılın',
                'join_sub'    => 'Bu formu doldurun; sıranız geldiğinde WhatsApp ve e-posta ile haber vereceğiz.',
                'name'        => 'Ad',
                'surname'     => 'Soyad',
                'phone'       => 'Telefon',
                'email'       => 'E-posta',
                'party'       => 'Kişi sayısı',
                'baby'        => 'Bebeğim var',
                'acc'         => 'Erişilebilirlik ihtiyacı',
                'note'        => 'Not (isteğe bağlı)',
                'consent'     => 'Kampanya ve duyuruları almayı kabul ediyorum.',
                'submit'      => 'Sıraya katıl',
                'language'    => 'Dil',
                'invalid_tok' => 'QR kodun süresi doldu.',
                'refresh_tok' => 'Lütfen ekrandaki yeni QR kodu tekrar okutun.',
                'kvkk'        => 'Bilgileriniz yalnızca bu hizmet için kullanılır.',
                'net_err'     => 'Ağ hatası, lütfen tekrar deneyin.',
                'recent_err'  => 'Aynı numara kısa süre önce kullanıldı. Birkaç dakika sonra tekrar deneyin.',
                'invalid_phone'  => 'Lütfen geçerli bir telefon numarası girin.',
                'missing_fields' => 'Lütfen zorunlu alanları doldurun.',
                'party_too_large'=> 'Grup büyüklüğü çok fazla',
                'queue_disabled' => 'Sıra sistemi şu anda kapalı.',
                'queue_not_accepting' => 'Şu an sıra alınmıyor. Masalar uygun olduğunda tekrar deneyin veya ekrandaki yönlendirmeyi izleyin.',
                'door_featured' => 'Menüden',
                'join_confirmed' => 'Sayın {name}, {business} ailesine hoş geldiniz. Kısa bir bekleme olacak; masanız hazırlanır hazırlanmaz haber vereceğiz.',
                'welcome_known' => 'Sayın misafirimiz',
                'busy_note' => 'Yoğunluk nedeniyle kısa bir bekleme var — misafirperverliğiniz için teşekkürler.',
                'your_table_is' => 'Masanız',
                'goodbye_title' => 'Afiyet olsun!',
                'goodbye_sub' => 'İçeri buyurun, keyifli vakit geçirmeniz dileğiyle.',
                'share_restaurant' => 'Bizi paylaş',
                'leave_review' => 'Google\'da değerlendir',
                'follow_social' => 'Bizi takip edin',
                'visit_again' => 'Tekrar bekleriz!',
                'no_show_title' => 'Biletiniz iptal edildi',
                'no_show_sub' => 'Çağrıldıktan sonra belirlenen sürede gelmediğiniz için biletiniz kapatıldı.',

                'ticket'      => 'Biletiniz',
                'position_of' => 'Sıradaki yeriniz',
                'ahead'       => 'Önünüzde {n} kişi var',
                'eta'         => 'Tahmini Bekleme',
                'minutes'     => 'dakika',
                'party_short' => 'Grup',
                'people'      => 'kişi',
                'last_update' => 'Son güncelleme',
                'ready_title' => 'Masanız hazır!',
                'ready_sub'   => 'Lütfen kapıya gelin ve bilet numaranızı gösterin.',
                'enjoy'       => 'Afiyet olsun!',
                'seated'      => 'Oturdu',
                'notified'    => 'Çağrıldı',
                'waiting'     => 'Sırada',
                'inactive'    => 'Biletiniz artık aktif değil.',
                'cancel_link' => 'Sıramı iptal et',
                'cancel_confirm' => 'Sıradaki biletinizi iptal etmek istiyor musunuz?',
                'keep_open'   => 'Bu sayfayı açık tutabilirsiniz. Sıranız geldiğinde bildirim alacaksınız.',
                'scan_cta'    => 'Telefonunuzla QR kodu okutun',
                'all_full'    => 'Bir masa hazırlayana kadar sabrınız için teşekkürler',
                'all_full_sub'=> 'QR kodunu okutun, sıranız geldiğinde haber verelim.',
                'active_now'  => 'Şu an sırada',
                'waiting_lbl' => 'Sırada',
                'groups'      => 'misafir',
                'no_line'     => 'Şu an sırada kimse yok. Doğrudan masaya geçebilirsiniz.',

                'welcome_subtitle'=> 'İçeride yerimiz var — buyurun, afiyet olsun.',
                'welcome_tagline' => 'Lezzet, sohbet, sıcak bir mola.',
                'welcome_hours'   => 'Her gün 09.00 – 23.00',
                'queue_mode_cta'  => 'Sıraya katılmak için QR\'ı okutun',
                'queue_mode_sub'  => 'Masanız hazır olduğunda WhatsApp ve e-posta ile haber vereceğiz.',
                'follow_us'      => 'Bizi takip edin',
                'menu'           => 'Menü',
                'website'        => 'Web sitesi',
                'call_us'        => 'Ara',
                'qr_refresh'     => 'QR sürekli yenilenir, sorun değil — istediğiniz anda okutun.',
                'qr_rotating'    => 'QR yenileniyor',
            ],
            'en' => [
                'welcome'     => 'Welcome',
                'join_title'  => 'Join the queue',
                'join_sub'    => 'Fill in the form. We will notify you via WhatsApp & email when your table is ready.',
                'name'        => 'First name',
                'surname'     => 'Last name',
                'phone'       => 'Phone',
                'email'       => 'Email',
                'party'       => 'Party size',
                'baby'        => 'I have a baby',
                'acc'         => 'Accessibility need',
                'note'        => 'Note (optional)',
                'consent'     => 'I agree to receive promotional messages.',
                'submit'      => 'Join queue',
                'language'    => 'Language',
                'invalid_tok' => 'The QR code has expired.',
                'refresh_tok' => 'Please scan the new QR on the screen again.',
                'kvkk'        => 'Your data is used only for this service.',
                'net_err'     => 'Network error, please try again.',
                'recent_err'  => 'This number was used recently. Please try again in a few minutes.',
                'invalid_phone'  => 'Please enter a valid phone number.',
                'missing_fields' => 'Please fill in the required fields.',
                'party_too_large'=> 'Party size is too large',
                'queue_disabled' => 'Queue system is currently disabled.',
                'queue_not_accepting' => 'We are not taking queue entries right now. Please try again when tables are available, or follow the on-screen information.',
                'door_featured' => 'From our menu',
                'join_confirmed' => 'Welcome to {business}, {name}. Please expect a short wait — we will let you know the moment your table is ready.',
                'welcome_known' => 'Dear guest',
                'busy_note' => 'A short wait due to demand — thank you for your patience.',
                'your_table_is' => 'Your table',
                'goodbye_title' => 'Enjoy your meal!',
                'goodbye_sub' => 'Please head in — have a great time.',
                'share_restaurant' => 'Share us',
                'leave_review' => 'Leave a Google review',
                'follow_social' => 'Follow us',
                'visit_again' => 'We hope to see you again!',
                'no_show_title' => 'Ticket closed',
                'no_show_sub' => 'Your ticket was closed because you did not arrive within the window after being called.',

                'ticket'      => 'Your ticket',
                'position_of' => 'Your position',
                'ahead'       => '{n} people ahead of you',
                'eta'         => 'Estimated wait',
                'minutes'     => 'minutes',
                'party_short' => 'Party',
                'people'      => 'people',
                'last_update' => 'Last update',
                'ready_title' => 'Your table is ready!',
                'ready_sub'   => 'Please come to the door and show your ticket number.',
                'enjoy'       => 'Enjoy!',
                'seated'      => 'Seated',
                'notified'    => 'Called',
                'waiting'     => 'Waiting',
                'inactive'    => 'Your ticket is no longer active.',
                'cancel_link' => 'Cancel my spot',
                'cancel_confirm' => 'Do you want to cancel your queue ticket?',
                'keep_open'   => 'Keep this page open. You will be notified when your turn comes.',
                'scan_cta'    => 'Scan the QR code with your phone',
                'all_full'    => 'Thank you for your patience while we prepare a table',
                'all_full_sub'=> 'Scan the QR code — we will notify you when it is ready.',
                'active_now'  => 'Now in queue',
                'waiting_lbl' => 'In line',
                'groups'      => 'guests',
                'no_line'     => 'No one is waiting right now. Please walk in.',

                'welcome_subtitle'=> 'Tables available — walk right in.',
                'welcome_tagline' => 'Great food. Warm vibes. Enjoy your stay.',
                'welcome_hours'   => 'Open daily 09.00 – 23.00',
                'queue_mode_cta'  => 'Scan the QR to join the line',
                'queue_mode_sub'  => 'We will notify you via WhatsApp and email when your table is ready.',
                'follow_us'      => 'Follow us',
                'menu'           => 'Menu',
                'website'        => 'Website',
                'call_us'        => 'Call',
                'qr_refresh'     => 'The QR refreshes automatically — scan whenever you like.',
                'qr_rotating'    => 'QR refreshing',
            ],
            'de' => [
                'welcome'     => 'Willkommen',
                'join_title'  => 'In die Warteschlange',
                'join_sub'    => 'Bitte füllen Sie das Formular aus. Sie erhalten eine Nachricht, sobald Ihr Tisch frei ist.',
                'name'        => 'Vorname',
                'surname'     => 'Nachname',
                'phone'       => 'Telefon',
                'email'       => 'E-Mail',
                'party'       => 'Personen',
                'baby'        => 'Mit Baby',
                'acc'         => 'Barrierefreiheit',
                'note'        => 'Notiz (optional)',
                'consent'     => 'Ich möchte Werbenachrichten erhalten.',
                'submit'      => 'Einreihen',
                'language'    => 'Sprache',
                'invalid_tok' => 'Der QR-Code ist abgelaufen.',
                'refresh_tok' => 'Bitte scannen Sie den neuen QR-Code auf dem Bildschirm.',
                'kvkk'        => 'Ihre Daten werden nur für diesen Service verwendet.',
                'net_err'     => 'Netzwerkfehler, bitte erneut versuchen.',
                'recent_err'  => 'Diese Nummer wurde kürzlich verwendet. Bitte später erneut versuchen.',
                'invalid_phone'  => 'Bitte eine gültige Telefonnummer eingeben.',
                'missing_fields' => 'Bitte die Pflichtfelder ausfüllen.',
                'party_too_large'=> 'Gruppengröße ist zu groß',
                'queue_disabled' => 'Die Warteschlange ist derzeit deaktiviert.',
                'queue_not_accepting' => 'Zur Zeit keine Aufnahme in die Warteschlange. Bitte versuchen Sie es später erneut.',
                'door_featured' => 'Aus unserer Speisekarte',
                'join_confirmed' => 'Willkommen bei {business}, {name}. Bitte rechnen Sie mit kurzer Wartezeit.',
                'welcome_known' => 'Sehr geehrter Gast',
                'busy_note' => 'Aufgrund des Andrangs bitten wir um etwas Geduld.',
                'your_table_is' => 'Ihr Tisch',
                'goodbye_title' => 'Guten Appetit!',
                'goodbye_sub' => 'Kommen Sie bitte herein.',
                'share_restaurant' => 'Teilen',
                'leave_review' => 'Bei Google bewerten',
                'follow_social' => 'Folgen Sie uns',
                'visit_again' => 'Auf ein Wiedersehen!',
                'no_show_title' => 'Ticket beendet',
                'no_show_sub' => 'Ihr Ticket wurde geschlossen.',

                'ticket'      => 'Ihr Ticket',
                'position_of' => 'Ihre Position',
                'ahead'       => '{n} Gäste vor Ihnen',
                'eta'         => 'Geschätzte Wartezeit',
                'minutes'     => 'Minuten',
                'party_short' => 'Gruppe',
                'people'      => 'Personen',
                'last_update' => 'Letzte Aktualisierung',
                'ready_title' => 'Ihr Tisch ist bereit!',
                'ready_sub'   => 'Bitte kommen Sie zur Tür und zeigen Sie Ihre Ticketnummer.',
                'enjoy'       => 'Guten Appetit!',
                'seated'      => 'Platziert',
                'notified'    => 'Gerufen',
                'waiting'     => 'Wartend',
                'inactive'    => 'Ihr Ticket ist nicht mehr aktiv.',
                'cancel_link' => 'Meinen Platz stornieren',
                'cancel_confirm' => 'Möchten Sie Ihr Ticket wirklich stornieren?',
                'keep_open'   => 'Halten Sie diese Seite geöffnet. Sie werden informiert, sobald Sie an der Reihe sind.',
                'scan_cta'    => 'Scannen Sie den QR-Code',
                'all_full'    => 'Alle Tische sind belegt',
                'all_full_sub'=> 'Scannen Sie den QR-Code, um sich einzureihen',
                'active_now'  => 'Aktuell in der Schlange',
                'waiting_lbl' => 'Wartend',
                'groups'      => 'Personen / Gruppen',
                'no_line'     => 'Niemand wartet.',
            ],
            'ar' => [
                'welcome'     => 'أهلاً بك',
                'join_title'  => 'انضم إلى الطابور',
                'join_sub'    => 'املأ النموذج؛ سنُعلمك عبر واتساب والبريد الإلكتروني عندما تصبح طاولتك جاهزة.',
                'name'        => 'الاسم',
                'surname'     => 'اللقب',
                'phone'       => 'الهاتف',
                'email'       => 'البريد الإلكتروني',
                'party'       => 'عدد الأشخاص',
                'baby'        => 'لدي رضيع',
                'acc'         => 'احتياج لإمكانية وصول',
                'note'        => 'ملاحظة (اختياري)',
                'consent'     => 'أوافق على استلام الرسائل الترويجية.',
                'submit'      => 'انضم',
                'language'    => 'اللغة',
                'invalid_tok' => 'انتهت صلاحية رمز QR.',
                'refresh_tok' => 'الرجاء مسح الرمز الجديد من الشاشة.',
                'kvkk'        => 'تُستخدم بياناتك لهذه الخدمة فقط.',
                'net_err'     => 'خطأ في الشبكة، يرجى المحاولة مرة أخرى.',
                'recent_err'  => 'تم استخدام هذا الرقم مؤخراً. الرجاء المحاولة بعد دقائق.',
                'invalid_phone'  => 'يرجى إدخال رقم هاتف صحيح.',
                'missing_fields' => 'يرجى ملء الحقول المطلوبة.',
                'party_too_large'=> 'عدد الأشخاص كبير جداً',
                'queue_disabled' => 'نظام الطابور معطّل حالياً.',
                'queue_not_accepting' => 'لا نستقبل انضمامات للطابور حالياً. حاول لاحقاً.',
                'door_featured' => 'من قائمتنا',
                'join_confirmed' => 'مرحبًا بك في {business}, {name}. سيكون هناك انتظار قصير.',
                'welcome_known' => 'عزيزنا الضيف',
                'busy_note' => 'انتظار قصير بسبب الازدحام — شكراً لصبرك.',
                'your_table_is' => 'طاولتك',
                'goodbye_title' => 'بالهناء والشفاء!',
                'goodbye_sub' => 'تفضل بالدخول.',
                'share_restaurant' => 'شاركنا',
                'leave_review' => 'قيمنا على Google',
                'follow_social' => 'تابعنا',
                'visit_again' => 'نتطلع لعودتك!',
                'no_show_title' => 'تم إغلاق التذكرة',
                'no_show_sub' => 'تم إغلاق التذكرة لعدم الحضور في الوقت المناسب.',

                'ticket'      => 'تذكرتك',
                'position_of' => 'موقعك في الطابور',
                'ahead'       => 'يوجد {n} أمامك',
                'eta'         => 'الوقت المتوقع',
                'minutes'     => 'دقيقة',
                'party_short' => 'المجموعة',
                'people'      => 'أشخاص',
                'last_update' => 'آخر تحديث',
                'ready_title' => 'طاولتك جاهزة!',
                'ready_sub'   => 'يرجى التوجه إلى الباب وإظهار رقم تذكرتك.',
                'enjoy'       => 'بالهناء والشفاء!',
                'seated'      => 'جالس',
                'notified'    => 'تم النداء',
                'waiting'     => 'ينتظر',
                'inactive'    => 'لم تعد تذكرتك فعّالة.',
                'cancel_link' => 'إلغاء تذكرتي',
                'cancel_confirm' => 'هل تريد إلغاء تذكرتك في الطابور؟',
                'keep_open'   => 'أبقِ هذه الصفحة مفتوحة. سنُعلمك عند حلول دورك.',
                'scan_cta'    => 'امسح رمز QR بالكاميرا',
                'all_full'    => 'جميع طاولاتنا محجوزة',
                'all_full_sub'=> 'امسح رمز QR للانضمام إلى الطابور',
                'active_now'  => 'في الطابور الآن',
                'waiting_lbl' => 'ينتظرون',
                'groups'      => 'أشخاص / مجموعات',
                'no_line'     => 'لا يوجد أحد في الطابور.',
            ],
        ];
        return $d[$lang] ?? $d['en'];
    }
}

if (!function_exists('qd_display_dict')) {
    function qd_display_dict(string $lang): array
    {
        return qd_dict($lang);
    }
}

/**
 * İşletme sıra TV ekranında canlı istatistikleri aç/kapa (varsayılan: kapalı).
 */
if (!function_exists('qd_queue_bool_setting')) {
    function qd_queue_bool_setting($v): bool
    {
        if ($v === null || $v === '') {
            return false;
        }
        if (is_bool($v)) {
            return $v;
        }
        $s = strtolower(trim((string) $v));
        return in_array($s, ['1', 'true', 'yes', 'on'], true);
    }
}

/**
 * Kapı ekranı için sosyal / iletişim linkleri (_welcome ile aynı kurallar).
 *
 * @return list<array{platform:string,url:string,value:string}>
 */
if (!function_exists('qd_social_nav_items')) {
    function qd_social_nav_items(array $sl): array
    {
        $socialUrl = static function (string $platform, string $value): string {
            $v = trim($value);
            if ($v === '') {
                return '';
            }
            if (preg_match('#^https?://#i', $v)) {
                return $v;
            }
            $handle = ltrim($v, '@');
            switch ($platform) {
                case 'instagram':
                    return 'https://instagram.com/' . rawurlencode($handle);
                case 'facebook':
                    return 'https://facebook.com/' . rawurlencode($handle);
                case 'tiktok':
                    return 'https://tiktok.com/@' . rawurlencode($handle);
                case 'whatsapp':
                    $digits = preg_replace('/\D+/', '', $v);
                    return $digits ? 'https://wa.me/' . $digits : '';
                case 'phone':
                    $digits = preg_replace('/[^\d+]/', '', $v);
                    return $digits ? 'tel:' . $digits : '';
                case 'menu':
                case 'website':
                    return 'https://' . ltrim($v, '/');
                case 'address':
                    return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($v);
            }
            return $v;
        };

        $items = [];
        foreach (['instagram', 'facebook', 'tiktok', 'whatsapp', 'website', 'menu', 'phone', 'address'] as $platform) {
            $val = $sl[$platform] ?? '';
            $url = $socialUrl($platform, (string) $val);
            if ($url === '') {
                continue;
            }
            $items[] = ['platform' => $platform, 'url' => $url, 'value' => (string) $val];
        }
        return $items;
    }
}
