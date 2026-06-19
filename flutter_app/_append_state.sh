#!/bin/bash
# auth_state.dart'a BusinessNumberValidated state'ini ekle
STATE_FILE="/var/www/vhosts/qordy.com/httpdocs/flutter_app/lib/features/auth/cubit/auth_state.dart"

# SubdomainValidated class'ından hemen sonra ekle
# Class'ın kapanış `}`'ini bul
python3 << 'PYEOF'
import re

file_path = "/var/www/vhosts/qordy.com/httpdocs/flutter_app/lib/features/auth/cubit/auth_state.dart"
with open(file_path, 'r') as f:
 content = f.read()

# SubdomainValidated class'ının kapanışından sonra BusinessNumberValidated ekle
new_class = '''

/// 4-6 haneli rakamdan oluşan benzersiz işletme numarası doğrulandı.
/// Personel artık "caddecafe.qordy.com" gibi kısaltmalar yerine
/// işletme sahibinin dashboard'unda gördüğü bu numarayı kullanır.
class BusinessNumberValidated extends AuthState {
 final String businessId;
 final String businessName;
 final String businessNumber;
 final String? businessLogo;

 const BusinessNumberValidated({
 required this.businessId,
 required this.businessName,
 required this.businessNumber,
 this.businessLogo,
 });

 @override
 List<Object?> get props =>
 [businessId, businessName, businessNumber, businessLogo];
}'''

# "class EmailValidated" kelimesini ara, ondan önce ekle
new_content = content.replace("class EmailValidated", new_class + "\n\nclass EmailValidated", 1)

if new_content == content:
 print("ERROR: Could not find replacement target")
 exit(1)

with open(file_path, 'w') as f:
 f.write(new_content)
print("✓ Added BusinessNumberValidated state class")
PYEOF