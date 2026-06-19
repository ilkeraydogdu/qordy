# ─── Flutter embedding ─────────────────────────────────────────
# R8 must not obfuscate anything the Flutter engine looks up by name.
-keep class io.flutter.embedding.** { *; }
-keep class io.flutter.plugin.** { *; }
-keep class io.flutter.plugins.** { *; }
-keep class io.flutter.view.** { *; }
-keep class io.flutter.util.** { *; }
-keep class androidx.lifecycle.DefaultLifecycleObserver

# ─── flutter_secure_storage (androidx.security / Tink) ─────────
-keep class com.google.crypto.tink.** { *; }
-dontwarn com.google.crypto.tink.**
-keep class androidx.security.crypto.** { *; }
-dontwarn androidx.security.crypto.**

# ─── Dio / Retrofit style reflection users ────────────────────
# Keep names referenced from JSON-serialised model classes; these are
# picked up by reflection-free code_gen, but we guard against stray
# reflection accessors (e.g., equatable/toString) stripping too much.
-keepattributes *Annotation*,Signature,InnerClasses,EnclosingMethod

# ─── OkHttp (bundled by Dio via Cronet on some channels) ───────
-dontwarn okio.**
-dontwarn okhttp3.**

# ─── Conscrypt / BoringSSL ─────────────────────────────────────
-dontwarn org.conscrypt.**
-dontwarn com.google.android.gms.**

# ─── Firebase (messaging + core) ──────────────────────────────
-keep class com.google.firebase.** { *; }
-dontwarn com.google.firebase.**
-keep class com.google.android.gms.common.** { *; }

# ─── Qordy native integrity probe ──────────────────────────────
# Keep our platform-channel handler class + methods so the Dart
# side's MethodChannel.invokeMethod("inspect") keeps resolving
# after obfuscation.
-keep class com.qordy.qordy_app.MainActivity { *; }

# ─── Play Core / deferred components ───────────────────────────
# Flutter embedding references Play Core split-install classes even when the
# app is not using deferred components. Without Play Core on the classpath
# R8 would otherwise fail; silence the missing-class warnings so release
# builds succeed without pulling the (now archived) Play Core dependency.
-dontwarn com.google.android.play.core.**
-keep class com.google.android.play.core.** { *; }

# Enable source-file and line-number info so stack traces remain
# de-obfuscatable given the mapping.txt shipped with the release.
-keepattributes SourceFile,LineNumberTable
-renamesourcefileattribute SourceFile
