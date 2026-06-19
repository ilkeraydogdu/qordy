<?php
/**
 * Yeni methodları MobileAPIController'a eklemek için script.
 * Bu scripti bir kere çalıştırıp sonra sileceğiz.
 */

$file = __DIR__ . '/MobileAPIController.php';
$content = file_get_contents($file);

// Sondaki kapanışı bul
$lastBrace = strrpos($content, '}');
if ($lastBrace === false) {
 die("File has no closing brace\n");
}

// Önce staffLogin içindeki resolveBusinessForMobileTenant -> resolveBusinessForMobileLogin
// Sadece staffLogin içindeki çağrıyı değiştir
$content = str_replace(
 ' $biz = $this->resolveBusinessForMobileTenant($db, $subdomain);',
 ' $biz = $this->resolveBusinessForMobileLogin($db, $subdomain);',
 $content
);

// staffLogin input parametresi adını değiştir
$content = str_replace(
 " \$subdomain = trim(\$data['subdomain'] ?? '');",
 " \$subdomain = trim(\$data['subdomain'] ?? \$data['business_number'] ?? '');",
 $content
);

// staffLogin response'a business_number ekle
$content = str_replace(
 "'subdomain' => \$biz['subdomain'],",
 "'subdomain' => \$biz['subdomain'],\n 'business_number' => \$biz['business_number'] ?? null,",
 $content
);

// Eklenecek methodlar
$newMethods = <<<'PHP'

 /**
 * Validate a 4-6 digit business number for mobile staff login.
 *
 * Personel artık "caddecafe" gibi kısaltmalar yerine işletme
 * sahibinin dashboard'unda gördüğü 4-6 haneli numarayı yazar.
 */
 public function validateBusinessNumber() {
 $data = $this->input();
 $businessNumber = trim($data['business_number'] ?? '');

 if ($businessNumber === '') {
 $this->json(['success' => false, 'error' => 'İşletme numarası gerekli'], 400);
 }

 if (!preg_match('/^\d{4,6}$/', $businessNumber)) {
 $this->json(['success' => false, 'error' => 'İşletme numarası 4-6 haneli rakam olmalıdır'], 400);
 }

 try {
 $db = DependencyFactory::getDatabase();
 $stmt = $db->prepare(
 "SELECT customer_id, company_name, business_number, subdomain,
 logo_url, logo_path, is_active, is_demo
 FROM customers
 WHERE business_number = ?
 AND (status IS NULL OR LOWER(status) != 'deleted')
 LIMIT 1"
 );
 $stmt->execute([$businessNumber]);
 $biz = $stmt->fetch(\PDO::FETCH_ASSOC);

 if (!$biz) {
 $this->json([
 'success' => false,
 'error' => 'Bu numaraya sahip bir işletme bulunamadı. Lütfen işletme numaranızı kontrol edin.'
 ], 404);
 }

 if (isset($biz['is_active']) && (int)$biz['is_active'] === 0) {
 $this->json([
 'success' => false,
 'error' => 'İşletmeniz pasife alınmıştır. Lütfen yöneticinizle iletişime geçin.'
 ], 403);
 }

 $logoUrl = $this->absoluteAssetUrl(
 $biz['logo_url'] ?? $biz['logo_path'] ?? null
 );

 $this->json(['success' => true, 'data' => [
 'valid' => true,
 'business' => [
 'id' => $biz['customer_id'],
 'name' => $biz['company_name'],
 'business_number' => $biz['business_number'],
 'subdomain' => $biz['subdomain'] ?? null,
 'logo' => $logoUrl,
 ],
 ]]);
 } catch (\Exception $e) {
 \App\Core\Logger::error('validateBusinessNumber error', ['error' => $e->getMessage()]);
 $this->json(['success' => false, 'error' => 'Doğrulama hatası'], 500);
 }
 }

 /**
 * Resolve a customer row by either a business_number or a legacy
 * subdomain. Mobile login accepts both so the rollout of the
 * 6-digit ID can be gradual.
 */
 private function resolveBusinessForMobileLogin(\PDO $db, string $rawInput): ?array {
 $input = trim($rawInput);
 if ($input === '') {
 return null;
 }

 // Pure digits: business_number path
 if (preg_match('/^\d{4,6}$/', $input)) {
 $stmt = $db->prepare(
 "SELECT * FROM customers
 WHERE business_number = ?
 AND (status IS NULL OR LOWER(status) != 'deleted')
 LIMIT 1"
 );
 $stmt->execute([$input]);
 $row = $stmt->fetch(\PDO::FETCH_ASSOC);
 if ($row) {
 return $row;
 }
 }

 // Fallback: subdomain / işletme adı eşleşmesi
 return $this->resolveBusinessForMobileTenant($db, $input);
 }
}

PHP;

// Sondaki }'yi kaldır, yeni methodları ekle, sonra }'yi geri koy
$newContent = substr($content, 0, $lastBrace) . $newMethods;

file_put_contents($file, $newContent);
echo "✓ Added validateBusinessNumber and resolveBusinessForMobileLogin methods\n";
echo "✓ Updated staffLogin to accept business_number\n";

// Syntax check
$out = shell_exec('/opt/plesk/php/8.3/bin/php -l ' . escapeshellarg($file) . ' 2>&1');
echo $out;
