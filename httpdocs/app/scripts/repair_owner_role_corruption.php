<?php
/**
 * Repair script: fix staff role corruption caused by the historic
 * bulk UPDATE bug in:
 *   - SuperAdmin\BusinessesController::changeOwnerRole
 *   - Services\SubscriptionService::activateSubscription
 *
 * Both endpoints used to update every user matching `tenant_id` without
 * scoping to a single owner. For each business this could flip every
 * staff row's role to BUSINESS_MANAGER / BUSINESS_OWNER, which in turn
 * made the staff list appear empty (Admin\UsersController::users
 * filtered by role code).
 *
 * What this script does:
 *   1. For every customer_id in `customers`, resolve the canonical
 *      owner via BusinessOwnerResolver.
 *   2. Leave the owner's role untouched (we cannot recover the original
 *      owner role name; whatever it currently is will remain).
 *   3. For every OTHER user with that tenant whose role is
 *      BUSINESS_OWNER, BUSINESS_MANAGER or TRIAL, move them to WAITER
 *      (the safe default staff role — business managers can
 *      re-assign them from the UI afterwards).
 *   4. Write a full audit log to storage/logs/role_repair_YYYYMMDD.log
 *      containing the before/after row for every changed user.
 *
 * Usage:
 *   php app/scripts/repair_owner_role_corruption.php             (dry-run)
 *   php app/scripts/repair_owner_role_corruption.php --apply     (mutates DB)
 *   php app/scripts/repair_owner_role_corruption.php --apply --business=CUST_xxx
 *
 * The script is idempotent — running it twice after --apply is a no-op.
 */

$envFile = __DIR__ . '/../../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
            (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
            $value = substr($value, 1, -1);
        }
        $_ENV[$name] = $value;
    }
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../services/BusinessOwnerResolver.php';

$argvIn       = $argv ?? [];
$apply        = in_array('--apply', $argvIn, true);
$onlyBusiness = null;
foreach ($argvIn as $a) {
    if (strpos($a, '--business=') === 0) {
        $onlyBusiness = substr($a, strlen('--business='));
    }
}

$mode = $apply ? 'APPLY' : 'DRY-RUN';
echo "[" . date('Y-m-d H:i:s') . "] role-repair ({$mode}) started\n";

$logDir = __DIR__ . '/../../storage/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0775, true);
}
$logFile = $logDir . '/role_repair_' . date('Ymd_His') . ($apply ? '_apply' : '_dryrun') . '.log';
$logHandle = @fopen($logFile, 'a');

function logLine($handle, string $msg): void {
    $stamp = '[' . date('Y-m-d H:i:s') . '] ';
    echo $stamp . $msg . "\n";
    if ($handle) {
        fwrite($handle, $stamp . $msg . "\n");
    }
}

try {
    $db = \App\Core\DependencyFactory::getDatabase();
    $resolver = new \App\Services\BusinessOwnerResolver($db);

    // Pick a safe default staff role; prefer WAITER because it's the
    // lowest-privilege role on the system. Fall back to whatever staff-ish
    // role actually exists.
    $defaultRoleCode = 'WAITER';
    $defaultRoleId   = null;
    foreach (['WAITER', 'CASHIER', 'KITCHEN', 'MANAGER'] as $code) {
        try {
            $st = $db->prepare("SELECT role_id FROM roles WHERE role_code = ? AND is_active = 1 LIMIT 1");
            $st->execute([$code]);
            $row = $st->fetch(\PDO::FETCH_ASSOC);
            if ($row && !empty($row['role_id'])) {
                $defaultRoleCode = $code;
                $defaultRoleId   = $row['role_id'];
                break;
            }
        } catch (\Throwable $e) {
            // keep looking
        }
    }
    if (!$defaultRoleId) {
        logLine($logHandle, "FATAL: No default staff role (WAITER/CASHIER/KITCHEN/MANAGER) active in roles table. Aborting.");
        exit(1);
    }
    logLine($logHandle, "Default staff role for repair: {$defaultRoleCode} ({$defaultRoleId})");

    // Roles we treat as "possibly corrupted owner-like roles" for NON-owner users.
    $ownerLikeRoles = ['BUSINESS_OWNER', 'BUSINESS_MANAGER', 'TRIAL'];

    $customersSql = "SELECT customer_id FROM customers";
    $params = [];
    if (!empty($onlyBusiness)) {
        $customersSql .= " WHERE customer_id = ?";
        $params[] = $onlyBusiness;
    }
    $cStmt = $db->prepare($customersSql);
    $cStmt->execute($params);
    $customers = $cStmt->fetchAll(\PDO::FETCH_COLUMN);

    $totalBusinesses = 0;
    $totalChanged    = 0;
    $totalSkipped    = 0;

    foreach ($customers as $customerId) {
        $totalBusinesses++;
        $ownerUserId = $resolver->resolve((string)$customerId);
        if (empty($ownerUserId)) {
            logLine($logHandle, "SKIP business={$customerId}: no owner resolvable (tenant has no users)");
            $totalSkipped++;
            continue;
        }

        $uStmt = $db->prepare("SELECT user_id, name, role, role_id FROM users WHERE tenant_id = ?");
        $uStmt->execute([$customerId]);
        $users = $uStmt->fetchAll(\PDO::FETCH_ASSOC);

        $affectedHere = 0;
        foreach ($users as $u) {
            $uid = $u['user_id'];
            if ($uid === $ownerUserId) {
                continue;
            }
            $currentRole = strtoupper(trim((string)($u['role'] ?? '')));
            if (!in_array($currentRole, $ownerLikeRoles, true)) {
                continue;
            }

            logLine($logHandle, sprintf(
                "CHANGE business=%s user=%s name=%s role %s(%s) -> %s(%s)",
                $customerId,
                $uid,
                $u['name'] ?? '',
                $currentRole,
                $u['role_id'] ?? '-',
                $defaultRoleCode,
                $defaultRoleId
            ));

            if ($apply) {
                try {
                    $upd = $db->prepare(
                        "UPDATE users SET role = ?, role_id = ? WHERE user_id = ? LIMIT 1"
                    );
                    $upd->execute([$defaultRoleCode, $defaultRoleId, $uid]);
                } catch (\Throwable $e) {
                    logLine($logHandle, "  ERROR updating user={$uid}: " . $e->getMessage());
                    continue;
                }
            }

            $affectedHere++;
            $totalChanged++;
        }

        if ($affectedHere > 0) {
            logLine($logHandle, "business={$customerId} owner={$ownerUserId} changes={$affectedHere}");
        }
    }

    logLine($logHandle, sprintf(
        "Done. Mode=%s businesses_scanned=%d users_changed=%d skipped=%d",
        $mode, $totalBusinesses, $totalChanged, $totalSkipped
    ));
    echo "Log written to: {$logFile}\n";
    if (!$apply) {
        echo "Re-run with --apply to persist these changes.\n";
    }
} catch (\Throwable $e) {
    logLine($logHandle, "FATAL: " . $e->getMessage());
    echo $e->getTraceAsString() . "\n";
    if ($logHandle) fclose($logHandle);
    exit(1);
}

if ($logHandle) fclose($logHandle);
exit(0);
