#!/usr/bin/env python3
"""AuthApi'ye yeni validateBusinessNumber + güncellenmiş staffLogin ekle"""

file_path = "/var/www/vhosts/qordy.com/httpdocs/flutter_app/lib/features/auth/data/auth_api.dart"
with open(file_path, 'r') as f:
 content = f.read()

# 1) validateSubdomain sonrasına validateBusinessNumber ekle (2 boşluk indent)
old_block = """ Future<Map<String, dynamic>> validateSubdomain(String subdomain) async {
 final response = await _dio.post(
 ApiConfig.validateSubdomain,
 data: {'subdomain': subdomain},
 );
 return _asPayload(response.data);
 }

 Future<Map<String, dynamic>> staffLogin(String pin, String subdomain) async {"""

new_block = """ Future<Map<String, dynamic>> validateSubdomain(String subdomain) async {
 final response = await _dio.post(
 ApiConfig.validateSubdomain,
 data: {'subdomain': subdomain},
 );
 return _asPayload(response.data);
 }

 /// 4-6 haneli benzersiz işletme numarasını doğrula. Personel bu
 /// numarayı yazıp PIN ekranına geçer. Aktif olmayan işletmeler
 /// 404 ile reddedilir; backend hata kodu istemci tarafında metne
 /// çevrilir.
 Future<Map<String, dynamic>> validateBusinessNumber(
 String businessNumber) async {
 final response = await _dio.post(
 ApiConfig.validateBusinessNumber,
 data: {'business_number': businessNumber},
 );
 return _asPayload(response.data);
 }

 Future<Map<String, dynamic>> staffLogin(
 String pin, {
 String? subdomain,
 String? businessNumber,
 }) async {"""

if old_block not in content:
 print("ERROR: old_block not found")
 exit(1)

content = content.replace(old_block, new_block)

# 2) staffLogin içindeki data payload'unu güncelle
old_data = """ final response = await _dio.post(
 ApiConfig.staffLogin,
 data: {'pin': pin, 'subdomain': subdomain},
 );
 return _asPayload(response.data);
 }"""

new_data = """ final payload = <String, dynamic>{'pin': pin};
 if (businessNumber != null && businessNumber.isNotEmpty) {
 payload['business_number'] = businessNumber;
 } else if (subdomain != null && subdomain.isNotEmpty) {
 payload['subdomain'] = subdomain;
 }
 final response = await _dio.post(
 ApiConfig.staffLogin,
 data: payload,
 );
 return _asPayload(response.data);
 }"""

if old_data not in content:
 print("ERROR: old_data block not found")
 exit(1)

content = content.replace(old_data, new_data)

with open(file_path, 'w') as f:
 f.write(content)
print("✓ AuthApi updated with validateBusinessNumber and updated staffLogin")
