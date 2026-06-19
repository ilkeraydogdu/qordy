<?php
/**
 * Translation Helper Functions
 * Wrapper functions for TranslationService - Maintains backward compatibility
 */

if (!function_exists('getTranslationService')) {
    /**
     * Get TranslationService instance (singleton pattern)
     * @return \App\Services\TranslationService
     */
    function getTranslationService() {
        require_once __DIR__ . '/../services/TranslationService.php';
        return \App\Services\TranslationService::getInstance();
    }
}

if (!function_exists('getCurrentLanguage')) {
    /**
     * Get current language from session or default
     * @return string - Language code ('tr' or 'en')
     */
    function getCurrentLanguage() {
        return getTranslationService()->getCurrentLanguage();
    }
}

if (!function_exists('setLanguage')) {
    /**
     * Set language in session
     * @param string $lang - Language code ('tr' or 'en')
     * @return void
     */
    function setLanguage($lang) {
        getTranslationService()->setLanguage($lang);
    }
}

if (!function_exists('t')) {
    /**
     * Translate a key (uses TranslationService)
     * @param string $key - Translation key
     * @param string|null $default - Default value if translation not found (optional)
     * @param string|null $lang - Language (default: current language from session)
     * @param array $params - Parameters for string replacement
     * @return string - Translated text or default value
     */
    function t($key, $default = null, $lang = null, $params = []) {
        // Support old signature: t($key, $lang, $params)
        // If second param is array, it's params, not default
        if (is_array($default) && $lang === null && $params === []) {
            $params = $default;
            $default = null;
            $lang = null;
        }
        // If second param is string and looks like lang code, treat as lang
        elseif (is_string($default) && strlen($default) === 2 && $lang === null) {
            $lang = $default;
            $default = null;
        }
        // If third param is array, it's params
        elseif (is_string($lang) && is_array($params)) {
            // Keep as is
        }
        // If second param is not array and not 2-char string, it's default
        elseif (!is_array($default) && (!is_string($default) || strlen($default) !== 2)) {
            // Keep as is - $default is default value
        }
        
        $translation = getTranslationService()->translate($key, $lang, $params);
        
        // Return translation if found, otherwise return default or null
        return $translation !== null ? $translation : ($default !== null ? $default : '');
    }
}

if (!function_exists('getTranslations')) {
    /**
     * Get translation array for a language
     * @param string $lang - Language code
     * @return array - Translation array
     */
    function getTranslations($lang = 'tr') {
        // NOW FULLY DYNAMIC - Get from database via TranslationService
        $translationService = getTranslationService();
        return $translationService->getAllTranslations($lang);
        
        // OLD HARDCODED FALLBACK - REMOVED FOR FULLY DYNAMIC SYSTEM
        // This code is kept for reference but never executed
        // All translations are now loaded from database
        /*
        $translations = [
            'tr' => [
                // Customer translations
                'welcome' => 'Hoş Geldiniz',
                'scanQR' => 'Sipariş vermek için lütfen masanızdaki QR kodu okutun.',
                'demoSelect' => 'Demo: Masa Seçin',
                'table' => 'Masa',
                'preparing' => 'Hazırlanıyor',
                'all' => 'Tümü',
                'addToCart' => 'Sepete Ekle',
                'cart' => 'Sepetim',
                'viewCart' => 'Sepeti Görüntüle',
                'total' => 'Toplam',
                'orderNote' => 'Sipariş Notu',
                'placeOrder' => 'Siparişi Onayla',
                'ingredients' => 'İçindekiler (Çıkarmak için dokun)',
                'extras' => 'Ekstralar (Eklemek için dokun)',
                'specialNote' => 'Özel Not',
                'orderSuccess' => 'Sipariş Alındı!',
                'orderSuccessMsg' => 'Mutfak ekibi işe koyuldu.',
                'callWaiter' => 'Garson Çağır',
                'requestBill' => 'Hesap İste',
                'waiterCalled' => 'Garson çağrıldı.',
                'billRequested' => 'Hesap istendi.',
                'insufficientStock' => 'Yetersiz Stok!',
                'maxStockReached' => 'Maksimum stok adedine ulaştınız.',
                
                // Admin translations
                'dashboard' => 'Genel Bakış',
                'pos' => 'POS Panel',
                'kitchen' => [
                    'title' => 'Mutfak',
                    'subtitle' => 'Sipariş Yönetim Ekranı',
                    'activeOrders' => 'Aktif Sipariş',
                    'kitchenQuiet' => 'Mutfak Sessiz',
                    'prepare' => 'Hazırla',
                    'serve' => 'Servis Et',
                    'ready' => 'Hazır',
                    'exclude' => 'Çıkar',
                    'product' => 'Ürün',
                    'loadingDetails' => 'Yükleniyor...',
                ],
                'menu' => [
                    'title' => 'Menü',
                    'add' => 'Ekle',
                    'edit' => 'Düzenle',
                    'delete' => 'Sil',
                    'name' => 'Ad',
                    'price' => 'Fiyat',
                    'category' => 'Kategori',
                    'description' => 'Açıklama',
                    'available' => 'Mevcut',
                    'unavailable' => 'Mevcut Değil',
                ],
                'tables' => 'Masalar',
                'settings' => 'Ayarlar',
                'revenue' => 'Günlük Ciro',
                'activeTables' => 'Dolu Masa',
                'pendingOrders' => 'Bekleyen',
                'notifications' => 'Bildirimler',
                'markRead' => 'Okundu İşaretle',
                'print' => 'Yazdır',
                'edit' => 'Düzenle',
                'delete' => 'Sil',
                'save' => 'Kaydet',
                'cancel' => 'İptal',
                'bulkUpdate' => '% Toplu Fiyat',
                'newItem' => 'Yeni Ürün',
                'outOfStock' => 'TÜKENDİ',
                'aiWrite' => 'AI ile Yaz',
                'writing' => 'Yazılıyor...',
                
                // Order status translations
                'PENDING' => 'Onay Bekliyor',
                'PREPARING' => 'Hazırlanıyor',
                'READY' => 'Servise Hazır',
                'SERVED' => 'Tamamlandı',
                'CANCELLED' => 'İptal',
                'ISSUE' => 'SORUN VAR',
                'ON_DELIVERY' => 'Teslimatta',
                'DELIVERED' => 'Teslim Edildi',
                
                'order' => [
                    'title' => 'Sipariş Yönetimi',
                    'all' => 'Tüm Siparişler',
                    'new' => 'Yeni Sipariş',
                    'pending' => 'Onay Bekliyor',
                    'preparing' => 'Hazırlanıyor',
                    'ready' => 'Hazır',
                    'served' => 'Servis Edildi',
                    'cancelled' => 'İptal Edildi',
                    'status' => 'Sipariş Durumu',
                    'refresh' => 'Yenile',
                    'export' => 'Dışa Aktar',
                    'allStatus' => 'Tümü',
                    'waiting' => 'Beklemede',
                    'readyToServe' => 'Servis Hazır',
                    'completed' => 'Tamamlandı',
                    'cancelledStatus' => 'İptal Edildi',
                    'orderId' => 'Sipariş ID',
                    'table' => 'Masa',
                    'customer' => 'Müşteri',
                    'amount' => 'Tutar',
                    'date' => 'Tarih',
                    'actions' => 'İşlemler',
                    'prepare' => 'Hazırla',
                    'serve' => 'Servis Et',
                    'orderInfo' => 'Sipariş Bilgileri',
                    'unknown' => 'Bilinmiyor',
                    'items' => 'Ürünler',
                    'total' => 'Toplam',
                    'orderDetailsFailed' => 'Sipariş bilgileri alınamadı.',
                ],
                'table' => [
                    'title' => 'Masalar',
                    'free' => 'Boş',
                    "occupied" => 'Dolu',
                    "payment_pending" => 'Ödeme Bekliyor',
                    "dirty" => 'Temizlenmeli',
                    "reserved" => 'Rezerve',
                ],
                'common' => [
                    'save' => 'Kaydet',
                    'cancel' => 'İptal',
                    'user' => 'Kullanıcı',
                    'delete' => 'Sil',
                    'edit' => 'Düzenle',
                    'add' => 'Ekle',
                    'search' => 'Ara',
                    'filter' => 'Filtrele',
                    'close' => 'Kapat',
                    'yes' => 'Evet',
                    'no' => 'Hayır',
                    'loading' => 'Yükleniyor...',
                    'error' => 'Hata',
                    'success' => 'Başarılı',
                    'warning' => 'Uyarı',
                    'info' => 'Bilgi',
                    'back' => 'Geri',
                    'reload' => 'Yenile',
                ],
                'dashboard' => [
                    'title' => 'Genel Bakış',
                    'subtitle' => 'Canlı İşletme Durumu',
                    'dailyRevenue' => 'GÜNLÜK CİRO',
                    'occupancy' => 'DOLULUK',
                    'pending' => 'BEKLEYEN',
                    'estimatedProfit' => 'TAHMİNİ KÂR',
                    'aiAdvisor' => 'AI Danışman Analizi',
                    'aiInsights' => 'Yapay Zeka Önerileri',
                    'aiInsightsPlaceholder' => 'Veriler analiz edildikten sonra burada öneriler göreceksiniz.',
                    'liveNotifications' => 'Canlı Bildirimler',
                    'noNotifications' => 'Yeni bildirim yok.',
                    'unknown' => 'Bilinmeyen',
                ],
                'menu' => [
                    'management' => 'Menü Yönetimi',
                    'items' => 'Ürünler',
                    'categories' => 'Kategoriler',
                    'newItem' => 'Yeni Ürün',
                    'newCategory' => 'Yeni Kategori',
                    'addNew' => 'Yeni Ekle',
                    'name' => 'İSİM',
                    'price' => 'FİYAT (₺)',
                    'selectCategory' => 'Kategori Seçin',
                    'description' => 'AÇIKLAMA',
                    'imageUrl' => 'RESİM URL',
                    'stock' => 'STOK',
                    'available' => 'Mevcut',
                    'unavailable' => 'Mevcut Değil',
                    'ingredients' => 'İÇİNDEKİLER',
                    'addIngredient' => 'İçindekiler Ekle',
                    'product' => 'Ürün',
                    'category' => 'Kategori',
                    'type' => 'Tür',
                    'actions' => 'İşlem',
                ],
                'users' => [
                    'title' => 'Personel Yönetimi',
                    'titleShort' => 'Personel',
                    'newStaff' => 'Yeni Personel',
                    'name' => 'İSİM',
                    'pin' => 'PIN',
                    'role' => 'GÖREV',
                    'action' => 'İşlem',
                    'waiter' => 'Garson',
                    'kitchen' => 'Mutfak',
                    'manager' => 'Yönetici',
                    'save' => 'KAYDET',
                    'deleteConfirm' => 'Bu personeli silmek istediğinizden emin misiniz? Bu işlem geri alınamaz.',
                    'fillAllFields' => 'Lütfen tüm alanları doldurun.',
                    'pinMustBe4Digits' => 'PIN kodu 4 haneli sayı olmalıdır.',
                    'staffDeleted' => 'Personel başarıyla silindi.',
                    'staffSaved' => 'Personel başarıyla kaydedildi.',
                    'deleteFailed' => 'Silme işlemi başarısız oldu.',
                    'saveFailed' => 'Kaydetme işlemi başarısız oldu.',
                ],
                'tables' => [
                    'title' => 'Masa Yönetimi',
                    'subtitle' => 'Tüm Masalar',
                    'addTable' => 'Yeni Masa Ekle',
                    'editTable' => 'Masayı Düzenle',
                    'all' => 'Tümü',
                    'free' => 'Boş',
                    'occupied' => 'Dolu',
                    'paymentPending' => 'Ödeme Bekliyor',
                    'dirty' => 'Temizlenmeli',
                    'reserved' => 'Rezerve',
                    'tableName' => 'MASA ADI',
                    'zone' => 'BÖLGE',
                    'selectZone' => 'Bölge Seçin',
                    'capacity' => 'KAPASİTE',
                    'status' => 'DURUM',
                    'edit' => 'Düzenle',
                    'delete' => 'Sil',
                    'zoneLabel' => 'Bölge',
                    'capacityLabel' => 'Kapasite',
                    'person' => 'kişi',
                    'fillRequiredFields' => 'Lütfen gerekli alanları doldurunuz.',
                    'deleteConfirm' => 'Bu masayı silmek istediğinizden emin misiniz?',
                    'statusUpdateFailed' => 'Masa durumu güncellenemedi',
                    'tableDeleted' => 'Masa başarıyla silindi.',
                    'tableSaved' => 'Masa başarıyla kaydedildi.',
                    'deleteFailed' => 'Masa silinemedi',
                    'saveFailed' => 'Masa kaydedilemedi',
                    'error' => 'Bir hata oluştu',
                ],
                'settings' => [
                    'title' => 'Ayarlar',
                    'systemSettings' => 'Sistem Ayarları',
                    'general' => 'Genel',
                    'financial' => 'Finansal',
                    'ai' => 'AI',
                    'language' => 'Dil',
                    'system' => 'Sistem',
                    'danger' => 'Tehlikeli',
                    'generalSettings' => 'Genel Ayarlar',
                    'financialSettings' => 'Finansal Ayarlar',
                    'aiSettings' => 'AI Ayarları',
                    'languageSettings' => 'Çoklu Dil Ayarları',
                    'systemSettings' => 'Sistem Ayarları',
                    'dangerousOperations' => 'Tehlikeli İşlemler',
                    'siteName' => 'SİTE ADI',
                    'logo' => 'LOGO',
                    'favicon' => 'FAVİCON',
                    'phone' => 'TELEFON',
                    'email' => 'E-POSTA',
                    'address' => 'ADRES',
                    'workingHours' => 'ÇALIŞMA SAATLERİ',
                    'serviceChargeRate' => 'SERVİS ÜCRETİ (%)',
                    'coverCharge' => 'KUVER ÜCRETİ',
                    'currency' => 'PARA BİRİMİ',
                    'geminiApiKey' => 'GEMİNİ API ANAHTARI',
                    'environment' => 'ORTAM',
                    'debugMode' => 'DEBUG MODU',
                    'timezone' => 'SAAT DİLİMİ',
                    'defaultLanguage' => 'VARSAYILAN DİL',
                    'sessionTimeout' => 'OTURUM ZAMAN AŞIMI (Dakika)',
                    'maxUploadSize' => 'MAKSİMUM YÜKLEME BOYUTU (MB)',
                    'supportedLanguages' => 'DESTEKLENEN DİLLER',
                    'languageSwitcherEnabled' => 'Dil Değiştiriciyi Aktif Et',
                    'autoDetectLanguage' => 'Otomatik Dil Algılama',
                    'languageUsageStats' => 'Dil Kullanım İstatistikleri',
                    'clearAllData' => 'Tüm Verileri Temizle',
                    'clearAllDataDesc' => 'Bu işlem menü ve siparişler dahil her şeyi siler.',
                    'resetSystem' => 'SİSTEMİ SIFIRLA',
                    'saveSettings' => 'Ayarları Kaydet',
                    'uploadLogo' => 'Logo Yükle',
                    'uploadFavicon' => 'Favicon Yükle',
                    'default' => 'Varsayılan',
                    'turkish' => 'Türkçe',
                    'english' => 'English',
                    'englishCode' => 'EN',
                    'newDefault' => 'Yeni kullanıcılar için varsayılan dil.',
                    'selectLanguages' => 'Kullanıcıların seçebileceği dilleri belirleyin. En az bir dil aktif olmalıdır.',
                    'allowLanguageSwitch' => 'Kullanıcıların dil değiştirmesine izin ver',
                    'autoDetectBrowserLang' => 'Tarayıcı diline göre otomatik dil seçimi',
                    'statsComingSoon' => 'Dil kullanım istatistikleri yakında eklenecek',
                    'irreversible' => 'Bu işlem geri alınamaz!',
                ],
                'pages' => [
                    'dashboard' => 'Genel Bakış',
                    'menu' => 'Menü',
                    'orders' => 'Siparişler',
                    'tables' => 'Masalar',
                    'users' => 'Personel',
                    'settings' => 'Ayarlar',
                    'pos' => 'POS Panel',
                    'kitchen' => 'Mutfak',
                ],
                'navigation' => [
                    'dashboard' => 'Özet',
                    'pos' => 'Kasa (POS)',
                    'kitchen' => 'Mutfak',
                    'menu' => 'Menü',
                    'reservations' => 'Rezervasyon',
                    'finance' => 'Finans',
                    'staff' => 'Personel',
                    'roles' => 'Rol ve Yetkiler',
                    'settings' => 'Ayarlar',
                ],
                'site' => [
                    'name' => 'Qordy - Akıllı Restoran Sistemi',
                ],
                'seo' => [
                    'home' => [
                        'title' => 'Qordy - Restoran Yönetim Sistemi | QR Menü, POS, Mutfak Yönetimi',
                        'description' => 'Restoran yönetimini dijitalleştirin. QR menü sistemi, POS sistemi ve mutfak yönetimi. Tüm ihtiyaçlarınız tek platformda, modern ve sezgisel bir deneyimle.',
                        'keywords' => 'QR menü, restoran yönetim sistemi, dijital menü, POS sistemi, mutfak yönetim sistemi, rezervasyon sistemi, stok takip sistemi, restoran yazılımı, dijital restoran çözümleri, QR kod menü, restoran otomasyonu',
                    ],
                    'dashboard' => [
                        'title' => 'Genel Bakış - Restoran Yönetim Paneli',
                        'description' => 'Restoran yönetim paneli - Canlı işletme durumu ve analitikler. Sipariş takibi, gelir analizi ve performans metrikleri.',
                        'keywords' => 'restoran yönetim paneli, dashboard, analitik, sipariş takibi, gelir analizi, restoran yönetim sistemi',
                    ],
                    'menu' => [
                        'title' => 'Menü Yönetimi - Dijital Menü Oluşturma',
                        'description' => 'Restoran menüsünü dijitalleştirin - Ürün ve kategori ekleyin, düzenleyin. QR menü sistemi ile müşterilerinize dijital menü sunun.',
                        'keywords' => 'menü yönetimi, dijital menü, QR menü, ürün yönetimi, kategori yönetimi, restoran menü sistemi',
                    ],
                    'features' => [
                        'title' => 'Özellikler - Restoran Yönetim Sistemi Özellikleri',
                        'description' => 'Qordy restoran yönetim sisteminin tüm özellikleri: QR menü, POS sistemi, mutfak yönetimi, rezervasyon takibi, stok yönetimi ve daha fazlası.',
                        'keywords' => 'restoran yönetim sistemi özellikleri, QR menü özellikleri, POS sistemi özellikleri, mutfak yönetim sistemi, rezervasyon sistemi, stok yönetim sistemi',
                    ],
                    'pricing' => [
                        'title' => 'Fiyatlandırma - Restoran Yönetim Sistemi Fiyatları',
                        'description' => 'Restoran yönetim sistemi fiyatlandırma planları. İşletmenizin ihtiyacına uygun paketi seçin. Başlangıç paketinden kurumsal çözümlere kadar.',
                        'keywords' => 'restoran yönetim sistemi fiyatları, QR menü fiyatları, POS sistemi fiyatları, restoran yazılımı fiyatlandırma, dijital menü fiyatları',
                    ],
                    'contact' => [
                        'title' => 'İletişim - Restoran Yönetim Sistemi Desteği',
                        'description' => 'Qordy restoran yönetim sistemi ile ilgili sorularınız için bizimle iletişime geçin. Teknik destek, satış ve genel bilgi için 7/24 destek.',
                        'keywords' => 'restoran yönetim sistemi destek, QR menü destek, POS sistemi destek, iletişim, teknik destek',
                    ],
                    'register' => [
                        'title' => 'Kayıt Ol - Restoran Yönetim Sistemi Hesabı Oluştur',
                        'description' => 'Qordy restoran yönetim sistemine kaydolun. Ücretsiz deneme başlatın ve restoranınızı dijitalleştirin. QR menü, POS ve mutfak yönetimi tek platformda.',
                        'keywords' => 'restoran yönetim sistemi kayıt, QR menü kayıt, ücretsiz deneme, restoran yazılımı kayıt, dijital menü kayıt',
                    ],
                    'default' => [
                        'description' => 'Qordy - Akıllı Restoran Yönetim Sistemi. QR menü, POS sistemi ve mutfak yönetimi. Restoranınızı dijitalleştirin.',
                        'keywords' => 'restoran yönetim sistemi, QR menü, dijital menü, POS sistemi, mutfak yönetim sistemi, rezervasyon sistemi, stok takip sistemi, restoran yazılımı, dijital restoran çözümleri, QR kod menü, restoran otomasyonu, restoran yönetim yazılımı',
                    ],
                ],
                'errors' => [
                    '404' => [
                        'title' => '404',
                        'heading' => 'Sayfa Bulunamadı',
                        'message' => 'Aradığınız sayfa mevcut değil.',
                        'backHome' => 'Ana Sayfaya Dön',
                    ],
                    '500' => [
                        'title' => '500',
                        'heading' => 'Sunucu Hatası',
                        'message' => 'Bir hata oluştu. Lütfen daha sonra tekrar deneyin.',
                        'backHome' => 'Ana Sayfaya Dön',
                    ],
                ],
            ],
            'en' => [
                // Customer translations
                'welcome' => 'Welcome',
                'scanQR' => 'Please scan the QR code on your table to order.',
                'demoSelect' => 'Demo: Select Table',
                'table' => 'Table',
                'preparing' => 'Preparing',
                'all' => 'All',
                'addToCart' => 'Add to Cart',
                'cart' => 'My Cart',
                'viewCart' => 'View Cart',
                'total' => 'Total',
                'orderNote' => 'Order Note',
                'placeOrder' => 'Place Order',
                'ingredients' => 'Ingredients (Tap to remove)',
                'extras' => 'Extras (Tap to add)',
                'specialNote' => 'Special Note',
                'orderSuccess' => 'Order Received!',
                'orderSuccessMsg' => 'Kitchen team is on it.',
                'callWaiter' => 'Call Waiter',
                'requestBill' => 'Request Bill',
                'waiterCalled' => 'Waiter has been called.',
                'billRequested' => 'Bill has been requested.',
                'insufficientStock' => 'Insufficient Stock!',
                'maxStockReached' => 'Maximum stock limit reached.',
                
                // Admin translations
                'dashboard' => 'Overview',
                'pos' => 'POS Panel',
                'kitchen' => [
                    'title' => 'Kitchen',
                    'subtitle' => 'Order Management Display',
                    'activeOrders' => 'Active Orders',
                    'kitchenQuiet' => 'Kitchen is Quiet',
                    'prepare' => 'Prepare',
                    'serve' => 'Serve',
                    'ready' => 'Ready',
                    'exclude' => 'Exclude',
                    'product' => 'Product',
                    'loadingDetails' => 'Loading...',
                ],
                'menu' => [
                    'title' => 'Menu',
                    'add' => 'Add',
                    'edit' => 'Edit',
                    'delete' => 'Delete',
                    'name' => 'Name',
                    'price' => 'Price',
                    'category' => 'Category',
                    'description' => 'Description',
                    'available' => 'Available',
                    'unavailable' => 'Unavailable',
                ],
                'tables' => 'Tables',
                'settings' => 'Settings',
                'revenue' => 'Daily Revenue',
                'activeTables' => 'Active Tables',
                'pendingOrders' => 'Pending',
                'notifications' => 'Notifications',
                'markRead' => 'Mark Read',
                'print' => 'Print',
                'edit' => 'Edit',
                'delete' => 'Delete',
                'save' => 'Save',
                'cancel' => 'Cancel',
                'bulkUpdate' => 'Bulk Price Update',
                'newItem' => 'New Item',
                'outOfStock' => 'SOLD OUT',
                'aiWrite' => 'Write with AI',
                'writing' => 'Writing...',
                
                // Order status translations
                'PENDING' => 'Pending',
                'PREPARING' => 'Preparing',
                'READY' => 'Ready to Serve',
                'SERVED' => 'Completed',
                'CANCELLED' => 'Cancelled',
                'ISSUE' => 'KITCHEN ISSUE',
                'ON_DELIVERY' => 'On Delivery',
                'DELIVERED' => 'Delivered',
                
                'order' => [
                    'title' => 'Orders',
                    'new' => 'New Order',
                    'pending' => 'Pending',
                    'preparing' => 'Preparing',
                    'ready' => 'Ready',
                    'served' => 'Served',
                    'cancelled' => 'Cancelled',
                ],
                'table' => [
                    'title' => 'Tables',
                    'free' => 'Free',
                    'occupied' => 'Occupied',
                    'payment_pending' => 'Payment Pending',
                    'dirty' => 'Dirty',
                    'reserved' => 'Reserved',
                ],
                'common' => [
                    'save' => 'Save',
                    'cancel' => 'Cancel',
                    'user' => 'User',
                    'delete' => 'Delete',
                    'edit' => 'Edit',
                    'add' => 'Add',
                    'search' => 'Search',
                    'filter' => 'Filter',
                    'close' => 'Close',
                    'details' => 'Details',
                    'yes' => 'Yes',
                    'no' => 'No',
                    'loading' => 'Loading...',
                    'error' => 'Error',
                    'success' => 'Success',
                    'warning' => 'Warning',
                    'info' => 'Info',
                    'back' => 'Back',
                    'reload' => 'Reload',
                ],
                'dashboard' => [
                    'title' => 'Overview',
                    'subtitle' => 'Live Business Status',
                    'dailyRevenue' => 'DAILY REVENUE',
                    'occupancy' => 'OCCUPANCY',
                    'pending' => 'PENDING',
                    'estimatedProfit' => 'ESTIMATED PROFIT',
                    'aiAdvisor' => 'AI Advisor Analysis',
                    'aiInsights' => 'AI Recommendations',
                    'aiInsightsPlaceholder' => 'You will see recommendations here after data analysis.',
                    'liveNotifications' => 'Live Notifications',
                    'noNotifications' => 'No new notifications.',
                    'unknown' => 'Unknown',
                ],
                'menu' => [
                    'management' => 'Menu Management',
                    'items' => 'Items',
                    'categories' => 'Categories',
                    'newItem' => 'New Item',
                    'newCategory' => 'New Category',
                    'addNew' => 'Add New',
                    'name' => 'NAME',
                    'price' => 'PRICE (₺)',
                    'selectCategory' => 'Select Category',
                    'description' => 'DESCRIPTION',
                    'imageUrl' => 'IMAGE URL',
                    'stock' => 'STOCK',
                    'available' => 'Available',
                    'unavailable' => 'Unavailable',
                    'ingredients' => 'INGREDIENTS',
                    'addIngredient' => 'Add Ingredient',
                    'product' => 'Product',
                    'category' => 'Category',
                    'type' => 'Type',
                    'actions' => 'Actions',
                ],
                'users' => [
                    'title' => 'Staff Management',
                    'newStaff' => 'New Staff',
                    'name' => 'NAME',
                    'pin' => 'PIN',
                    'role' => 'ROLE',
                    'waiter' => 'Waiter',
                    'kitchen' => 'Kitchen',
                    'manager' => 'Manager',
                    'save' => 'SAVE',
                ],
                'tables' => [
                    'title' => 'Table Management',
                    'subtitle' => 'All Tables',
                    'addTable' => 'Add New Table',
                    'editTable' => 'Edit Table',
                    'all' => 'All',
                    'free' => 'Free',
                    'occupied' => 'Occupied',
                    'paymentPending' => 'Payment Pending',
                    'dirty' => 'Dirty',
                    'reserved' => 'Reserved',
                    'tableName' => 'TABLE NAME',
                    'zone' => 'ZONE',
                    'selectZone' => 'Select Zone',
                    'capacity' => 'CAPACITY',
                    'status' => 'STATUS',
                    'edit' => 'Edit',
                    'delete' => 'Delete',
                    'zoneLabel' => 'Zone',
                    'capacityLabel' => 'Capacity',
                    'person' => 'person',
                    'fillRequiredFields' => 'Please fill in the required fields.',
                    'deleteConfirm' => 'Are you sure you want to delete this table?',
                    'statusUpdateFailed' => 'Table status could not be updated',
                    'tableDeleted' => 'Table successfully deleted.',
                    'tableSaved' => 'Table successfully saved.',
                    'deleteFailed' => 'Table could not be deleted',
                    'saveFailed' => 'Table could not be saved',
                    'error' => 'An error occurred',
                ],
                'settings' => [
                    'title' => 'Settings',
                    'systemSettings' => 'System Settings',
                    'general' => 'General',
                    'financial' => 'Financial',
                    'ai' => 'AI',
                    'language' => 'Language',
                    'system' => 'System',
                    'danger' => 'Dangerous',
                    'generalSettings' => 'General Settings',
                    'financialSettings' => 'Financial Settings',
                    'aiSettings' => 'AI Settings',
                    'languageSettings' => 'Multi-Language Settings',
                    'systemSettings' => 'System Settings',
                    'dangerousOperations' => 'Dangerous Operations',
                    'siteName' => 'SITE NAME',
                    'logo' => 'LOGO',
                    'favicon' => 'FAVICON',
                    'phone' => 'PHONE',
                    'email' => 'EMAIL',
                    'address' => 'ADDRESS',
                    'workingHours' => 'WORKING HOURS',
                    'serviceChargeRate' => 'SERVICE CHARGE (%)',
                    'coverCharge' => 'COVER CHARGE',
                    'currency' => 'CURRENCY',
                    'geminiApiKey' => 'GEMINI API KEY',
                    'environment' => 'ENVIRONMENT',
                    'debugMode' => 'DEBUG MODE',
                    'timezone' => 'TIMEZONE',
                    'defaultLanguage' => 'DEFAULT LANGUAGE',
                    'sessionTimeout' => 'SESSION TIMEOUT (Minutes)',
                    'maxUploadSize' => 'MAX UPLOAD SIZE (MB)',
                    'supportedLanguages' => 'SUPPORTED LANGUAGES',
                    'languageSwitcherEnabled' => 'Enable Language Switcher',
                    'autoDetectLanguage' => 'Auto Detect Language',
                    'languageUsageStats' => 'Language Usage Statistics',
                    'clearAllData' => 'Clear All Data',
                    'clearAllDataDesc' => 'This will delete everything including menu and orders.',
                    'resetSystem' => 'RESET SYSTEM',
                    'saveSettings' => 'Save Settings',
                    'uploadLogo' => 'Upload Logo',
                    'uploadFavicon' => 'Upload Favicon',
                    'default' => 'Default',
                    'turkish' => 'Turkish',
                    'english' => 'English',
                    'englishCode' => 'EN',
                    'newDefault' => 'Default language for new users.',
                    'selectLanguages' => 'Select languages that users can choose. At least one language must be active.',
                    'allowLanguageSwitch' => 'Allow users to change language',
                    'autoDetectBrowserLang' => 'Automatic language selection based on browser language',
                    'statsComingSoon' => 'Language usage statistics coming soon',
                    'irreversible' => 'This operation cannot be undone!',
                    'noEditPermission' => 'Warning: You do not have permission to edit settings. You are in view-only mode.',
                    'noEditPermissionShort' => 'You do not have permission to edit settings.',
                    'saving' => 'Saving...',
                    'resetConfirm1' => 'Are you sure you want to delete all data? This action cannot be undone!',
                    'resetConfirm2' => 'Please confirm one more time. All menu, orders and other data will be permanently deleted.',
                    'resetSuccess' => 'System successfully reset.',
                    'resetFailed' => 'System reset operation failed.',
                ],
                'pages' => [
                    'dashboard' => 'Overview',
                    'menu' => 'Menu',
                    'orders' => 'Orders',
                    'tables' => 'Tables',
                    'users' => 'Staff',
                    'settings' => 'Settings',
                    'pos' => 'POS Panel',
                    'kitchen' => 'Kitchen',
                ],
                'site' => [
                    'name' => 'Qordy - Smart Restaurant System',
                ],
                'seo' => [
                    'home' => [
                        'title' => 'Qordy - Restaurant Management System | QR Menu, POS, Kitchen Management',
                        'description' => 'Digitize restaurant management. QR menu system, POS system and kitchen management. All your needs on a single platform, with a modern and intuitive experience.',
                        'keywords' => 'QR menu, restaurant management system, digital menu, POS system, kitchen management system, reservation system, inventory management system, restaurant software, digital restaurant solutions, QR code menu, restaurant automation',
                    ],
                    'dashboard' => [
                        'title' => 'Overview - Restaurant Management Panel',
                        'description' => 'Restaurant management panel - Live business status and analytics. Order tracking, revenue analysis and performance metrics.',
                        'keywords' => 'restaurant management panel, dashboard, analytics, order tracking, revenue analysis, restaurant management system',
                    ],
                    'menu' => [
                        'title' => 'Menu Management - Digital Menu Creation',
                        'description' => 'Digitize your restaurant menu - Add and edit products and categories. Offer digital menu to your customers with QR menu system.',
                        'keywords' => 'menu management, digital menu, QR menu, product management, category management, restaurant menu system',
                    ],
                    'features' => [
                        'title' => 'Features - Restaurant Management System Features',
                        'description' => 'All features of Qordy restaurant management system: QR menu, POS system, kitchen management, reservation tracking, inventory management and more.',
                        'keywords' => 'restaurant management system features, QR menu features, POS system features, kitchen management system, reservation system, inventory management system',
                    ],
                    'pricing' => [
                        'title' => 'Pricing - Restaurant Management System Prices',
                        'description' => 'Restaurant management system pricing plans. Choose the package that suits your business needs. From starter package to enterprise solutions.',
                        'keywords' => 'restaurant management system prices, QR menu prices, POS system prices, restaurant software pricing, digital menu prices',
                    ],
                    'contact' => [
                        'title' => 'Contact - Restaurant Management System Support',
                        'description' => 'Contact us for questions about Qordy restaurant management system. 24/7 support for technical support, sales and general information.',
                        'keywords' => 'restaurant management system support, QR menu support, POS system support, contact, technical support',
                    ],
                    'register' => [
                        'title' => 'Sign Up - Create Restaurant Management System Account',
                        'description' => 'Sign up for Qordy restaurant management system. Start free trial and digitize your restaurant. QR menu, POS and kitchen management on a single platform.',
                        'keywords' => 'restaurant management system sign up, QR menu sign up, free trial, restaurant software sign up, digital menu sign up',
                    ],
                    'default' => [
                        'description' => 'Qordy - Smart Restaurant Management System. QR menu, POS system and kitchen management. Digitize your restaurant.',
                        'keywords' => 'restaurant management system, QR menu, digital menu, POS system, kitchen management system, reservation system, inventory management system, restaurant software, digital restaurant solutions, QR code menu, restaurant automation',
                    ],
                ],
                'errors' => [
                    '404' => [
                        'title' => '404',
                        'heading' => 'Page Not Found',
                        'message' => 'The page you are looking for does not exist.',
                        'backHome' => 'Back to Home',
                    ],
                    '500' => [
                        'title' => '500',
                        'heading' => 'Server Error',
                        'message' => 'An error occurred. Please try again later.',
                        'backHome' => 'Back to Home',
                    ],
                ],
            ],
        ];
        
        return $translations[$lang] ?? $translations['tr'];
        */
    }
}

