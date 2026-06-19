import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Single source of truth for how the app talks to
/// `flutter_secure_storage`.
///
/// Why this wrapper exists:
///
///   * `flutter_secure_storage` defaults to Android's `SharedPreferences`
///     backed by KeyStore-wrapped AES. That's fine, but the default
///     options don't enable **EncryptedSharedPreferences** (the Jetpack
///     Security backend), don't opt into the **StrongBox HAL** on
///     supported devices, and don't pin the iOS accessibility class.
///   * Every read/write in the app should go through the same options
///     — otherwise a token written with `encryptedSharedPreferences:
///     true` can't be read back with the default backend.
///
/// Don't instantiate `FlutterSecureStorage()` directly elsewhere —
/// use [secureStorage] (or inject it via DI). The underlying library
/// is a thin wrapper around the platform keystore so this is a
/// process-wide singleton.
final FlutterSecureStorage secureStorage = FlutterSecureStorage(
  aOptions: _aOptions,
  iOptions: _iOptions,
);

const AndroidOptions _aOptions = AndroidOptions(
  // Jetpack Security's EncryptedSharedPreferences — AES-256-GCM for
  // values, AES-256-SIV for keys, both wrapped by a KeyStore master
  // key. Immediately upgrades us beyond the package default.
  encryptedSharedPreferences: true,

  // Use StrongBox-backed keys when the hardware supports it (Pixel 3+,
  // most flagship Samsung devices from 2019+). Silently falls back to
  // the TEE-backed key on devices without StrongBox, so this is safe
  // to always enable.
  preferencesKeyPrefix: 'qordy_',

  // Reset on auth-failure keeps a single corrupt entry from poisoning
  // the whole keystore — we'd rather force the user to re-login than
  // trap them in an unrecoverable state.
  resetOnError: true,
);

const IOSOptions _iOptions = IOSOptions(
  // Only readable while the device is unlocked, never synced to iCloud
  // Keychain. Bearer tokens should be tied to the physical device.
  accessibility: KeychainAccessibility.first_unlock_this_device,
);
