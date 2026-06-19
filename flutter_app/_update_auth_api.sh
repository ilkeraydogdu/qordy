#!/bin/bash
# auth_api.dart dosyasını güncelle

FILE="/var/www/vhosts/qordy.com/httpdocs/flutter_app/lib/features/auth/data/auth_api.dart"

# 1. Eski staffLogin signature'ını bulup değiştir
sed -i 's/Future<Map<String, dynamic>> staffLogin(String pin, String subdomain) async {/Future<Map<String, dynamic>> staffLogin(\n String pin, {\n String? subdomain,\n String? businessNumber,\n }) async {/g' "$FILE"

# 2. Eski data payload'unu bulup değiştir
sed -i "s|data: {'pin': pin, 'subdomain': subdomain},|final payload = <String, dynamic>{'pin': pin};\n if (businessNumber != null \&\& businessNumber.isNotEmpty) {\n payload['business_number'] = businessNumber;\n } else if (subdomain != null \&\& subdomain.isNotEmpty) {\n payload['subdomain'] = subdomain;\n }\n final response|g" "$FILE"

# 3. data satırını payload olarak değiştir
sed -i "s|final payload = <String, dynamic>{'pin': pin};|final payload = <String, dynamic>{'pin': pin};|g" "$FILE"

# 4. validateBusinessNumber'ı validateSubdomain'den sonra ekle
# Python yerine direkt echo ile ekleyemeyiz, doğrudan cat ile satır sonrasına
# Geçici olarak validateSubdomain sonrasına ekle
awk '
/^ Future<Map<String, dynamic>> staffLogin/ && !inserted {
 print " /// 4-6 haneli benzersiz işletme numarasını doğrula. Personel bu"
 print " /// numarayı yazıp PIN ekranına geçer. Aktif olmayan işletmeler"
 print " /// 404 ile reddedilir; backend hata kodu istemci tarafında metne"
 print " /// çevrilir."
 print " Future<Map<String, dynamic>> validateBusinessNumber("
 print " String businessNumber) async {"
 print " final response = await _dio.post("
 print "  ApiConfig.validateBusinessNumber,"
 print " data: {\x27business_number\x27: businessNumber},"
 print " );"
 print " return _asPayload(response.data);"
 print " }"
 print ""
 inserted=1
 }
 { print }
' "$FILE" > /tmp/auth_api_new.dart

cp /tmp/auth_api_new.dart "$FILE"
echo "✓ AuthApi updated"
