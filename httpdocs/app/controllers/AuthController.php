<?php
/**
 * AuthController — DEPRECATED (Q4 2026)
 *
 * The 3020-line god class that used to live here has been split into
 * 4 focused controllers under App\Controllers\Auth\*:
 *
 * - AuthBaseController
 * The shared base. Hosts the construction-time bootstrap and the
 * cross-cutting helpers (IP detection, role-redirect, Redis-backed
 * PIN/IP mapping, signed-cookie encoding, subdomain detection).
 *
 * - Auth/SessionController
 * index, login, refreshCsrfToken, logout, unauthorized, apiCheckAuth
 *
 * - Auth/TwoFactorController
 * show2FAVerify, switch2FAMethod, resend2FACode, verify2FA,
 * completeManagerLoginAfter2FA (private)
 *
 * - Auth/RegistrationController
 * publicLogin, register, checkSubdomainAvailability,
 * sendRegisterEmailCode, verifyRegisterEmail,
 * sendRegisterPhoneCode, verifyRegisterPhone
 *
 * - Auth/QordyAdminLoginController
 * qodminLogin
 *
 * Routes now point directly at the new namespaces (see
 * app/config/routes.php). The two thin delegator classes that were
 * still forwarding to the old god class — auth/PublicAuthController
 * and auth/RegisterController — have been re-wired to the new
 * concrete classes.
 *
 * This file is intentionally left empty. It must NOT be reintroduced
 * as a working controller: doing so would silently shadow the new
 * routes via the autoloader (App\Controllers\AuthController).
 */
declare(strict_types=1);

namespace App\Controllers;

if (!class_exists(__NAMESPACE__ . '\AuthController', false)) {
 // Sentinel stub. We do not extend Controller on purpose: the
 // `class_exists` check is a deliberate no-op so the autoloader
 // keeps the rest of the class file empty. Touching this stub from
 // the outside (e.g. via new AuthController()) will explode loudly
 // with "Class not found" because we never actually define the
 // class below.
}

// Intentionally no class definition.
// See header comment for context.
