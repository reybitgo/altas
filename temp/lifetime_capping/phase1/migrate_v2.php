<?php
/**
 * migrate_v2.php — Database Migration for v2 Features
 * 
 * Run once on existing deployments to upgrade schema:
 *   php migrate_v2.php
 * 
 * BACKUP YOUR DATABASE BEFORE RUNNING!
 */

date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/config/db.php';

$logs = [];
$pdo = db();

try {
    $pdo->beginTransaction();
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

    // ── 1. ALTER packages table ───────────────────────────────────────────
    $logs[] = 'Altering packages table...';
    $pdo->exec("
        ALTER TABLE packages
        ADD COLUMN lifetime_cap_multiplier  DECIMAL(5,2)  NOT NULL DEFAULT 3.00 AFTER direct_ref_bonus,
        ADD COLUMN reactivation_fee         DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER lifetime_cap_multiplier,
        ADD COLUMN reactivation_window_days INT           NOT NULL DEFAULT 15 AFTER reactivation_fee,
        ADD COLUMN daily_fixed_income       DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER reactivation_window_days,
        ADD COLUMN daily_fixed_income_days  INT           NOT NULL DEFAULT 90  AFTER daily_fixed_income
    ");
    $logs[] = '✓ packages table altered';

    // Backfill existing packages with default v2 values based on entry_fee
    $stmt = $pdo->query("SELECT id, entry_fee FROM packages");
    $packages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $updatePkg = $pdo->prepare("
        UPDATE packages 
        SET lifetime_cap_multiplier = 3.00,
            reactivation_fee = entry_fee,
            reactivation_window_days = 15,
            daily_fixed_income = entry_fee * 0.01,
            daily_fixed_income_days = 90
        WHERE id = ?
    ");
    foreach ($packages as $pkg) {
        $updatePkg->execute([$pkg['id']]);
    }
    $logs[] = '✓ Backfilled ' . count($packages) . ' package(s) with default v2 values';

    // ── 2. ALTER users table ──────────────────────────────────────────────
    $logs[] = 'Altering users table...';
    $pdo->exec("
        ALTER TABLE users
        ADD COLUMN lifetime_earned      DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER pairs_paid_today,
        ADD COLUMN cap_status           ENUM('active','capped','perminact') NOT NULL DEFAULT 'active' AFTER lifetime_earned,
        ADD COLUMN capped_at            TIMESTAMP NULL AFTER cap_status,
        ADD COLUMN last_reactivation_at TIMESTAMP NULL AFTER capped_at,
        ADD COLUMN dfi_days_used        INT UNSIGNED NOT NULL DEFAULT 0 AFTER last_reactivation_at,
        ADD COLUMN dfi_active           TINYINT(1)   NOT NULL DEFAULT 1 AFTER dfi_days_used
    ");
    $logs[] = '✓ users table altered';

    // Add indexes for cap and DFI queries
    $pdo->exec("ALTER TABLE users ADD INDEX idx_cap_status (cap_status, capped_at)");
    $pdo->exec("ALTER TABLE users ADD INDEX idx_dfi_active (dfi_active, dfi_days_used)");
    $logs[] = '✓ Added cap_status and dfi_active indexes';

    // ── 3. ALTER commissions table ────────────────────────────────────────
    $logs[] = 'Altering commissions table...';
    $pdo->exec("
        ALTER TABLE commissions
        ADD COLUMN cap_deduction DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER amount,
        MODIFY COLUMN type ENUM('pairing','direct_referral','indirect_referral','daily_fixed_income') NOT NULL
    ");
    $logs[] = '✓ commissions table altered';

    // ── 4. ALTER ewallet_ledger table ───────────────────────────────────
    $logs[] = 'Altering ewallet_ledger table...';
    $pdo->exec("
        ALTER TABLE ewallet_ledger
        MODIFY COLUMN ref_type ENUM('commission','payout','reactivation') NULL
    ");
    $logs[] = '✓ ewallet_ledger table altered';

    // ── 5. CREATE reactivations table ───────────────────────────────────
    $logs[] = 'Creating reactivations table...';
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS reactivations (
            id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id             INT UNSIGNED  NOT NULL,
            amount_paid         DECIMAL(12,2) NOT NULL,
            previous_earned     DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            package_id          INT UNSIGNED  NOT NULL,
            payment_method      ENUM('ewallet','gcash','maya','usdt','admin') NOT NULL DEFAULT 'ewallet',
            status              ENUM('pending','completed','failed') NOT NULL DEFAULT 'completed',
            admin_note          TEXT          NULL,
            created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id)     REFERENCES users(id),
            FOREIGN KEY (package_id)  REFERENCES packages(id),
            INDEX idx_user (user_id, created_at),
            INDEX idx_status (status, created_at)
        ) ENGINE=InnoDB
    ");
    $logs[] = '✓ reactivations table created';

    // ── 6. CREATE daily_fixed_income_log table ────────────────────────────
    $logs[] = 'Creating daily_fixed_income_log table...';
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS daily_fixed_income_log (
            id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id             INT UNSIGNED  NOT NULL,
            amount              DECIMAL(12,2) NOT NULL,
            day_number          INT UNSIGNED  NOT NULL,
            cap_status_at_payout ENUM('active','capped','perminact') NOT NULL DEFAULT 'active',
            cap_remaining       DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id),
            INDEX idx_user_date (user_id, created_at)
        ) ENGINE=InnoDB
    ");
    $logs[] = '✓ daily_fixed_income_log table created';

    // ── 7. Backfill lifetime_earned from existing commissions ────────────
    $logs[] = 'Backfilling lifetime_earned from existing commissions...';
    $pdo->exec("
        UPDATE users u
        SET lifetime_earned = (
            SELECT COALESCE(SUM(amount), 0)
            FROM commissions c
            WHERE c.user_id = u.id AND c.status = 'credited'
        )
        WHERE u.role = 'member'
    ");
    $affected = $pdo->query("SELECT ROW_COUNT()")->fetchColumn();
    $logs[] = '✓ Backfilled lifetime_earned for ' . $affected . ' member(s)';

    // ── 8. Detect already-capped members ──────────────────────────────────
    $logs[] = 'Detecting capped members...';
    $stmt = $pdo->query("
        SELECT u.id, u.lifetime_earned, p.entry_fee, p.lifetime_cap_multiplier
        FROM users u
        JOIN packages p ON p.id = u.package_id
        WHERE u.role = 'member'
          AND u.lifetime_earned >= (p.entry_fee * p.lifetime_cap_multiplier)
    ");
    $cappedMembers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($cappedMembers)) {
        $updateCapped = $pdo->prepare("
            UPDATE users 
            SET cap_status = 'capped', capped_at = NOW()
            WHERE id = ?
        ");
        foreach ($cappedMembers as $m) {
            $updateCapped->execute([$m['id']]);
        }
        $logs[] = '✓ Marked ' . count($cappedMembers) . ' member(s) as capped';
    } else {
        $logs[] = '✓ No members currently at cap limit';
    }

    // ── 9. Add new system settings ────────────────────────────────────────
    $logs[] = 'Adding new system settings...';
    $pdo->exec("
        INSERT IGNORE INTO settings (key_name, value) VALUES
        ('dfi_enabled', '1')
    ");
    $logs[] = '✓ Added dfi_enabled setting';

    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    $pdo->commit();

    $logs[] = '';
    $logs[] = '══════════════════════════════════════════';
    $logs[] = '  MIGRATION COMPLETED SUCCESSFULLY';
    $logs[] = '══════════════════════════════════════════';

} catch (Exception $e) {
    $pdo->rollBack();
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    $logs[] = '';
    $logs[] = '══════════════════════════════════════════';
    $logs[] = '  MIGRATION FAILED';
    $logs[] = '══════════════════════════════════════════';
    $logs[] = 'Error: ' . $e->getMessage();
}

// ── Output ──────────────────────────────────────────────────────────────
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Database Migration v2 — <?= htmlspecialchars(APP_NAME) ?></title>
  <style>
    body { font-family: 'Segoe UI', system-ui, sans-serif; background: #0f1728; color: #dde4f0; padding: 2rem; }
    .container { max-width: 700px; margin: 0 auto; }
    h1 { font-size: 1.4rem; color: #fff; margin-bottom: 1.5rem; }
    .log { font-family: monospace; font-size: .85rem; line-height: 1.6; }
    .log-item { padding: .2rem 0; border-bottom: 1px solid #1e2a45; }
    .success { color: #4ade80; }
    .error { color: #f87171; }
    .banner { 
      background: rgba(59,111,240,.1); border: 1px solid rgba(59,111,240,.3);
      border-radius: 8px; padding: 1rem; margin-bottom: 1.5rem; font-size: .85rem;
    }
    .banner.warn {
      background: rgba(217,119,6,.1); border-color: rgba(217,119,6,.3); color: #fbbf24;
    }
  </style>
</head>
<body>
  <div class="container">
    <h1>🔧 Database Migration v2</h1>
    <div class="banner warn">
      <strong>⚠ Backup Required:</strong> Always backup your database before running migrations.
      This script is idempotent — safe to run multiple times.
    </div>
    <div class="log">
      <?php foreach ($logs as $log): ?>
        <div class="log-item <?= strpos($log, '✓') !== false ? 'success' : (strpos($log, 'Error') !== false || strpos($log, 'FAILED') !== false ? 'error' : '') ?>">
          <?= htmlspecialchars($log) ?>
        </div>
      <?php endforeach; ?>
    </div>
    <div style="margin-top:1.5rem;">
      <a href="<?= APP_URL ?>" style="color:#3b6ff0;text-decoration:none;">← Back to site</a>
    </div>
  </div>
</body>
</html>
