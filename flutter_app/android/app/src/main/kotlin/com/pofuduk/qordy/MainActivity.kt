package com.pofuduk.qordy

import android.content.pm.ApplicationInfo
import android.os.Build
import io.flutter.embedding.android.FlutterFragmentActivity
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.plugin.common.MethodChannel
import java.io.File

/**
 * Qordy mobile host activity.
 *
 * Bu sınıf, Flutter'ın standart embedding kurulumuna ek olarak yalnızca
 * native tarafta yapılabilecek olan **app integrity probe**'u sağlar:
 * bir MethodChannel üzerinden rooted cihaz, emülatör ve debuggable build
 * sinyallerini Flutter katmanına iletiyor (bkz.
 * `lib/core/security/app_integrity.dart`).
 *
 * Not: Daha önce FLAG_SECURE ile tüm pencereye ekran görüntüsü / ekran
 * kaydı bloğu uygulanıyordu. Ürün ekibi tarafından ekran görüntüsü
 * alınabilmesi talep edildiği için bu flag kaldırıldı. Hassas bir
 * ekranda tekrar açılmak istenirse `window.addFlags(FLAG_SECURE)` o
 * Activity/route düzeyinde nokta atışı çağrılabilir.
 */
class MainActivity : FlutterFragmentActivity() {
    private val integrityChannel = "com.pofuduk.qordy/integrity"

    override fun configureFlutterEngine(flutterEngine: FlutterEngine) {
        super.configureFlutterEngine(flutterEngine)
        MethodChannel(flutterEngine.dartExecutor.binaryMessenger, integrityChannel)
            .setMethodCallHandler { call, result ->
                when (call.method) {
                    "inspect" -> result.success(collectIntegritySignals())
                    else -> result.notImplemented()
                }
            }
    }

    private fun collectIntegritySignals(): Map<String, Any> {
        return mapOf(
            "isRooted" to isDeviceRooted(),
            "isDebuggable" to isAppDebuggable(),
            "isEmulator" to isRunningOnEmulator(),
        )
    }

    private fun isAppDebuggable(): Boolean {
        return (applicationInfo.flags and ApplicationInfo.FLAG_DEBUGGABLE) != 0
    }

    /**
     * Heuristic root detection. No silver bullet here — determined
     * attackers can bypass any of these — but we deliberately check
     * a range of common signals so a *casual* root setup (typical
     * Magisk install, su binary in $PATH) is flagged.
     */
    private fun isDeviceRooted(): Boolean {
        val suspiciousPaths = arrayOf(
            "/system/app/Superuser.apk",
            "/sbin/su",
            "/system/bin/su",
            "/system/xbin/su",
            "/data/local/xbin/su",
            "/data/local/bin/su",
            "/system/sd/xbin/su",
            "/system/bin/failsafe/su",
            "/data/local/su",
            "/su/bin/su",
            // Magisk — modern, hidden root
            "/sbin/.magisk",
            "/data/adb/magisk",
            "/sbin/.core/mirror",
        )
        if (suspiciousPaths.any { File(it).exists() }) return true

        // test-keys builds almost always indicate a custom / rooted ROM.
        val tags = Build.TAGS
        if (tags != null && tags.contains("test-keys")) return true

        return false
    }

    /**
     * Emulator fingerprint detection. Flags Genymotion, the stock
     * Android emulator and BlueStacks with high confidence; doesn't
     * flag physical devices of any OEM we've tested against.
     */
    private fun isRunningOnEmulator(): Boolean {
        val fingerprint = Build.FINGERPRINT ?: ""
        val model = Build.MODEL ?: ""
        val manufacturer = Build.MANUFACTURER ?: ""
        val brand = Build.BRAND ?: ""
        val device = Build.DEVICE ?: ""
        val product = Build.PRODUCT ?: ""
        val hardware = Build.HARDWARE ?: ""

        return fingerprint.startsWith("generic")
            || fingerprint.startsWith("unknown")
            || fingerprint.contains("vbox")
            || fingerprint.contains("test-keys")
            || model.contains("google_sdk")
            || model.contains("Emulator")
            || model.contains("Android SDK built for")
            || manufacturer.contains("Genymotion")
            || brand.startsWith("generic") && device.startsWith("generic")
            || product == "google_sdk"
            || product.contains("sdk_gphone")
            || hardware.contains("goldfish")
            || hardware.contains("ranchu")
    }
}
