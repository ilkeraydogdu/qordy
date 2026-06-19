import java.util.Properties
import java.io.FileInputStream

plugins {
    id("com.android.application")
    id("kotlin-android")
    // The Flutter Gradle Plugin must be applied after the Android and Kotlin Gradle plugins.
    id("dev.flutter.flutter-gradle-plugin")
}

// Load upload-key credentials from android/key.properties. This file is
// gitignored — it lives outside source control and is mounted manually
// on build machines. If missing we fall back to the debug key so local
// dev builds keep working without a keystore.
val keystoreProperties = Properties().apply {
    val keystorePropertiesFile = rootProject.file("key.properties")
    if (keystorePropertiesFile.exists()) {
        load(FileInputStream(keystorePropertiesFile))
    }
}

// Only apply the google-services plugin if the Firebase config file has
// been placed in the project. This lets the app compile without push
// enabled; once ops drops `google-services.json` into `android/app/`,
// the next build wires Firebase in automatically.
if (file("google-services.json").exists()) {
    apply(plugin = "com.google.gms.google-services")
}

android {
    namespace = "com.pofuduk.qordy"
    compileSdk = flutter.compileSdkVersion
    ndkVersion = flutter.ndkVersion

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
        // Required by flutter_local_notifications (and other plugins that
        // use java.time) for minSdk < 26.
        isCoreLibraryDesugaringEnabled = true
    }

    kotlinOptions {
        jvmTarget = JavaVersion.VERSION_17.toString()
    }

    defaultConfig {
        // TODO: Specify your own unique Application ID (https://developer.android.com/studio/build/application-id.html).
        applicationId = "com.pofuduk.qordy"
        // You can update the following values to match your application needs.
        // For more information, see: https://flutter.dev/to/review-gradle-config.
        minSdk = maxOf(flutter.minSdkVersion, 23)
        targetSdk = flutter.targetSdkVersion
        versionCode = flutter.versionCode
        versionName = flutter.versionName
        multiDexEnabled = true
    }

    signingConfigs {
        // Play-store upload key. Real credentials live in
        // android/key.properties (gitignored). If that file hasn't been
        // dropped on the build machine yet, this block silently skips
        // configuring the key and the release build falls back to debug
        // signing below — so local dev work doesn't break.
        create("release") {
            val storeFile = keystoreProperties["storeFile"] as String?
            if (storeFile != null) {
                this.storeFile = file(storeFile)
                this.storePassword = keystoreProperties["storePassword"] as String?
                this.keyAlias = keystoreProperties["keyAlias"] as String?
                this.keyPassword = keystoreProperties["keyPassword"] as String?
            }
        }
    }

    buildTypes {
        release {
            // Use the upload keystore when configured, otherwise fall
            // back to debug signing so that contributors without the
            // keystore can still produce a local release build.
            signingConfig = if (keystoreProperties["storeFile"] != null) {
                signingConfigs.getByName("release")
            } else {
                signingConfigs.getByName("debug")
            }
            // R8 (Android's successor to Proguard) strips unused classes
            // and obfuscates symbol names in release APKs. Both reduce
            // APK size and make reverse-engineering the business-logic
            // layer materially harder.
            isMinifyEnabled = true
            isShrinkResources = true
            proguardFiles(
                getDefaultProguardFile("proguard-android-optimize.txt"),
                "proguard-rules.pro",
            )
        }
        debug {
            isMinifyEnabled = false
            isShrinkResources = false
        }
    }
}

flutter {
    source = "../.."
}

dependencies {
    coreLibraryDesugaring("com.android.tools:desugar_jdk_libs:2.1.4")
}
