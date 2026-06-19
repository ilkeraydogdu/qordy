#!/bin/bash
FILE="/var/www/vhosts/qordy.com/httpdocs/flutter_app/lib/features/auth/cubit/auth_cubit.dart"

# validateSubdomain methodundan sonra validateBusinessNumber metodu ekle
awk '
/^ Future<void> validateSubdomain\(/ { found=1 }
found && /^ \}$/ {
 print
 print ""
 print " /// 4-6 haneli benzersiz işletme numarasını doğrula."
 print " Future<void> validateBusinessNumber(String businessNumber) async {"
 print " emit(const AuthLoading());"
 print " try {"
 print " final response = await _repository.validateBusinessNumber(businessNumber);"
 print " if (response['\''success'\''] == true) {"
 print " final data = _asMapOrNull(response['\''data'\'']);"
 print " final business = _asMapOrNull(data?['\''business'\'']) ?? data;"
 print " final bid = business?['\''id'\'']?.toString() ?? business?['\''customer_id'\'']?.toString() ?? '\'''\'';"
 print " final name = business?['\''name'\'']?.toString() ?? business?['\''company_name'\'']?.toString() ?? '\''Isletme'\'';"
 print " final num = business?['\''business_number'\'']?.toString() ?? businessNumber;"
 print " final logo = business?['\''logo'\'']?.toString();"
 print " if (bid.isEmpty) {"
 print " emit(const AuthError('\''Isletme bilgisi alinamadi. Lutfen tekrar deneyin.'\''));"
 print " return;"
 print " }"
 print " emit(BusinessNumberValidated("
 print " businessId: bid,"
 print " businessName: name,"
 print " businessNumber: num,"
 print " businessLogo: logo,"
 print " ));"
 print " } else {"
 print " emit(AuthError("
 print " response['\''error'\'']?.toString() ?? '\''Gecersiz isletme numarasi'\'',"
 print " ));"
 print " }"
 print " } on DioException catch (e) {"
 print " emit(AuthError(_extractError(e)));"
 print " } catch (e) {"
 print " emit(AuthError(_friendly(e)));"
 print " }"
 print " }"
 found=0
 next
}
{ print }
' "$FILE" > /tmp/auth_cubit_new.dart
mv /tmp/auth_cubit_new.dart "$FILE"
echo "Done"