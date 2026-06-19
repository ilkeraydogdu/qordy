import 'dart:async';
import 'dart:io';

import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';

/// Categories of integrity violations we explicitly care about.
///
/// Each value is surfaced in [AppIntegrity.violations] and is used by
/// the UI layer to decide whether to warn the user or hard-block
/// interactions. Values are intentionally coarse — we don't want to
/// leak our exact detection strategy in a string that's easy to grep
/// from a reverse-engineered binary.
enum IntegrityFinding {
  /// The running binary is a debug build, OR a debugger is currently
  /// attached (commonly a sign of tampering in the wild).
  debugBuild,

  /// Device-level signs of rooting / jailbreaking — `su` binary,
  /// Magisk files, Cydia, etc.
  rootedDevice,

  /// App is running inside an emulator. Not strictly malicious on its
  /// own but — combined with any of the above — is a strong signal.
  emulator,
}

/// Client-side integrity / tamper-evidence probe.
///
/// QORDY's threat model for the installed APK is:
///
///   1. Someone side-loads the APK onto a rooted phone to try to
///      harvest bearer tokens from the Keystore-backed secure storage.
///   2. Someone attaches Frida / a debugger to intercept API calls
///      and replay them (e.g., to fake a POS sale for a suspended
///      tenant).
///   3. Someone decompiles the APK and re-signs it with a patched
///      client.
///
/// We can't *prevent* a determined attacker locally — that's the
/// backend's job (and the reason every API call is authenticated,
/// rate-limited and validated server-side). But we can raise the bar:
///
///   * Never ship a debuggable release build (enforced at the manifest
///     level, see `android/app/src/main/AndroidManifest.xml`).
///   * Refuse to run sensitive flows if the device is rooted or a
///     debugger is attached, so a casual attacker has to work harder.
///   * Strip strings / obfuscate with R8 in release so decompilation
///     yields less readable code.
class AppIntegrity {
  AppIntegrity._();
  static final AppIntegrity instance = AppIntegrity._();

  /// MethodChannel used by the native Android layer to report quick
  /// integrity signals (root / emulator / debuggable). The iOS side is
  /// a no-op for now because we don't ship an iOS build.
  static const MethodChannel _channel =
      MethodChannel('com.pofuduk.qordy/integrity');

  final Set<IntegrityFinding> _findings = {};

  /// Findings detected in the last [runChecks] pass. Empty set means
  /// we consider the current runtime trustworthy.
  Set<IntegrityFinding> get violations => Set.unmodifiable(_findings);

  /// Convenience: has anything tripped that should block sensitive
  /// flows (payments, payouts, subscription changes)?
  bool get shouldBlockSensitiveFlows {
    // Debug build on its own is fine during development.
    if (kDebugMode) return false;
    // Root OR (debug attached at runtime) OR emulator in release is
    // enough to pull the rip-cord.
    return _findings.contains(IntegrityFinding.rootedDevice) ||
        _findings.contains(IntegrityFinding.debugBuild) ||
        _findings.contains(IntegrityFinding.emulator);
  }

  /// Run all configured integrity checks. Safe to invoke multiple
  /// times — findings simply accumulate until the process restarts.
  Future<void> runChecks() async {
    // Debug builds of our own app are never shipped to end users; any
    // debug binary running on a device with a real user is a red flag.
    if (kDebugMode) {
      // Still record the finding for completeness, but we don't act
      // on it because developers are always running debug builds.
      _findings.add(IntegrityFinding.debugBuild);
      return;
    }

    if (Platform.isAndroid) {
      await _runAndroidChecks();
    }
    // iOS integrity stub — will be filled in when/if an iOS build
    // is shipped.
  }

  Future<void> _runAndroidChecks() async {
    try {
      final Map<dynamic, dynamic>? result =
          await _channel.invokeMethod<Map<dynamic, dynamic>>('inspect');
      if (result == null) return;

      if (result['isRooted'] == true) {
        _findings.add(IntegrityFinding.rootedDevice);
      }
      if (result['isDebuggable'] == true) {
        _findings.add(IntegrityFinding.debugBuild);
      }
      if (result['isEmulator'] == true) {
        _findings.add(IntegrityFinding.emulator);
      }
    } on PlatformException catch (e) {
      if (kDebugMode) {
        debugPrint('AppIntegrity: native probe failed — ${e.message}');
      }
      // We deliberately FAIL OPEN here — if the native channel isn't
      // wired up yet (or fails for a benign reason like a missing
      // plugin on a dev device), we don't want to brick the app. The
      // backend is still the authoritative security boundary.
    } on MissingPluginException {
      // Native handler hasn't been registered on this build variant —
      // same reasoning as above.
    } catch (_) {}
  }
}
