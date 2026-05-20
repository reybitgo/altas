# MLM Binary Income Capping & Reactivation — Implementation Plan

## Overview

This document provides a complete, phased implementation plan for adding **lifetime binary income capping** and **account reactivation** mechanics to the existing MLM Binary System. Each phase is self-contained and builds upon the previous phase.

**Core Mechanics (from simulator):**

- Each member has a **maximum lifetime binary income cap per cycle**
- Once cumulative binary earnings reach this cap → account becomes **inactive**
- Inactive accounts: no pairing bonuses, skipped in pair counting
- To resume: member pays **Reactivation Fee** (company revenue)
- Only pairs formed **after** reactivation count toward new cycle
- **Reactivation Window**: days allowed to reactivate before permanent deactivation
- Reactivation **resets cap counter to zero** (new cycle begins)

---

## Phase 1: Database Schema & Core Settings

### 1.1 Migration SQL (`migrations/001_add_capping_schema.sql`)

```sql
-- ============================================================
-- Migration 001: Add Income Capping & Reactivation Schema
-- ============================================================

-- 1. Add capping columns to packages (default values for new packages)
ALTER TABLE packages
  ADD COLUMN income_cap DECIMAL(12,2) NOT NULL DEFAULT 30000.00
    COMMENT 'Maximum lifetime binary earnings per cycle'
    AFTER direct_ref_bonus,
  ADD COLUMN reactivation_fee DECIMAL(12,2) NOT NULL DEFAULT 10000.00
    COMMENT 'Fee to reactivate after hitting cap'
    AFTER income_cap,
  ADD COLUMN reactivation_window_days TINYINT UNSIGNED NOT NULL DEFAULT 15
    COMMENT 'Days allowed to reactivate before permanent deactivation'
    AFTER reactivation_fee;

-- 2. Add member-level capping state to users
ALTER TABLE users
  ADD COLUMN binary_earned_this_cycle DECIMAL(12,2) NOT NULL DEFAULT 0.00
    COMMENT 'Cumulative binary earnings in current cycle'
    AFTER pairs_paid_today,
  ADD COLUMN cap_status ENUM('active','capped','perminact') NOT NULL DEFAULT 'active'
    COMMENT 'Current capping status'
    AFTER binary_earned_this_cycle,
  ADD COLUMN capped_at TIMESTAMP NULL
    COMMENT 'When member hit the income cap'
    AFTER cap_status,
  ADD COLUMN reactivation_window_expires TIMESTAMP NULL
    COMMENT 'Deadline to reactivate before permanent deactivation'
    AFTER capped_at,
  ADD COLUMN reactivation_count INT UNSIGNED NOT NULL DEFAULT 0
    COMMENT 'How many times member has reactivated'
    AFTER reactivation_window_expires,
  ADD COLUMN last_reactivated_at TIMESTAMP NULL
    COMMENT 'Most recent reactivation timestamp'
    AFTER reactivation_count;

-- 3. Add reactivation revenue tracking to company
-- (Uses existing payout_requests structure — reactivations are payments TO company)

-- 4. Create reactivation payments table (member pays company to reactivate)
CREATE TABLE reactivation_payments (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id       INT UNSIGNED NOT NULL,
  package_id    INT UNSIGNED NOT NULL,
  amount        DECIMAL(12,2) NOT NULL,
  payment_method ENUM('gcash','maya','usdt','manual') NOT NULL DEFAULT 'manual',
  payment_ref   VARCHAR(100) NULL COMMENT 'External transaction reference',
  status        ENUM('pending','confirmed','rejected') NOT NULL DEFAULT 'pending',
  processed_by  INT UNSIGNED NULL,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  confirmed_at  TIMESTAMP NULL,
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (package_id) REFERENCES packages(id),
  FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_user_status (user_id, status),
  INDEX idx_pending (status, created_at)
) ENGINE=InnoDB;

-- 5. Add indexes for performance
ALTER TABLE users ADD INDEX idx_cap_status (cap_status, capped_at);
ALTER TABLE users ADD INDEX idx_reactivation_window (reactivation_window_expires, cap_status);

-- 6. Update existing members: set cap_status based on current earnings
-- (Backfill: members who already exceeded cap get 'capped' status)
UPDATE users u
  JOIN packages p ON p.id = u.package_id
  SET u.cap_status = 'capped',
      u.capped_at = u.joined_at
  WHERE u.binary_earned_this_cycle >= p.income_cap
    AND u.cap_status = 'active'
    AND u.role = 'member';
```

### 1.2 Settings Migration (`migrations/002_add_capping_settings.sql`)

```sql
-- ============================================================
-- Migration 002: Add Capping Settings to System Settings
-- ============================================================

INSERT IGNORE INTO settings (key_name, value) VALUES
  ('income_cap_default', '30000'),
  ('reactivation_fee_default', '10000'),
  ('reactivation_window_default', '15'),
  ('reactivation_rate_default', '100'),  -- % of capped members who reactivate (for simulator)
  ('enable_income_capping', '1');        -- Master toggle to enable/disable capping system
```

### 1.3 Update `Package.php` Model

Add to `models/Package.php`:

```php
// Add to find(), all(), withLevels() return data
// No code changes needed — columns auto-loaded by SELECT *

public static function getCappingConfig(int $packageId): array
{
    $pkg = self::find($packageId);
    if (!$pkg) return [
        'income_cap' => (float)setting('income_cap_default', '30000'),
        'reactivation_fee' => (float)setting('reactivation_fee_default', '10000'),
        'reactivation_window_days' => (int)setting('reactivation_window_default', '15'),
    ];
    return [
        'income_cap' => (float)$pkg['income_cap'],
        'reactivation_fee' => (float)$pkg['reactivation_fee'],
        'reactivation_window_days' => (int)$pkg['reactivation_window_days'],
    ];
}
```

---

## Phase 2: Admin Settings UI — Capping Configuration

### 2.1 Update `views/admin/settings.php`

Add new section to the settings form (after "Payout Service Fees"):

```php
<!-- Income Capping & Reactivation Settings -->
<hr class="my-4">
<p class="fw-bold mb-3" style="font-size:.9rem;">🛡️ Income Capping & Reactivation</p>
<div class="form-text mb-3">
  Control the maximum lifetime binary earnings per cycle, reactivation costs, and grace period.
  Members who hit the cap become inactive until they pay the reactivation fee.
</div>

<div class="row g-3 mb-3">
  <div class="col-md-4">
    <label class="form-label" style="color:var(--purple);font-weight:700;font-size:.8rem;">
      Default Income Cap (₱)
    </label>
    <div class="input-group input-group-sm">
      <span class="input-group-text">₱</span>
      <input type="number" name="income_cap_default" class="form-control font-mono"
        min="5000" max="500000" step="1000"
        value="<?= e(setting('income_cap_default', '30000')) ?>">
    </div>
    <div class="form-text">Max binary earnings before cap triggers</div>
  </div>
  <div class="col-md-4">
    <label class="form-label" style="color:var(--purple);font-weight:700;font-size:.8rem;">
      Default Reactivation Fee (₱)
    </label>
    <div class="input-group input-group-sm">
      <span class="input-group-text">₱</span>
      <input type="number" name="reactivation_fee_default" class="form-control font-mono"
        min="0" max="50000" step="500"
        value="<?= e(setting('reactivation_fee_default', '10000')) ?>">
    </div>
    <div class="form-text">Fee member pays to reset cap cycle</div>
  </div>
  <div class="col-md-4">
    <label class="form-label" style="color:var(--purple);font-weight:700;font-size:.8rem;">
      Reactivation Window (days)
    </label>
    <div class="input-group input-group-sm">
      <input type="number" name="reactivation_window_default" class="form-control font-mono"
        min="1" max="180" step="1"
        value="<?= e(setting('reactivation_window_default', '15')) ?>">
      <span class="input-group-text">days</span>
    </div>
    <div class="form-text">Days to reactivate before permanent deactivation</div>
  </div>
</div>

<div class="mb-3">
  <div class="form-check form-switch">
    <input class="form-check-input" type="checkbox" name="enable_income_capping"
      id="enableCapping" value="1"
      <?= setting('enable_income_capping', '1') === '1' ? 'checked' : '' ?>>
    <label class="form-check-label" for="enableCapping" style="font-weight:600;">
      Enable Income Capping System
    </label>
  </div>
  <div class="form-text">Disable to allow unlimited binary earnings (legacy mode)</div>
</div>
```

### 2.2 Update `AdminController::saveSettings()`

Add to allowed settings array in `controllers/AdminController.php`:

```php
$allowed = [
    // ... existing settings ...
    'income_cap_default',
    'reactivation_fee_default',
    'reactivation_window_default',
    'enable_income_capping',
];
```

---

## Phase 3: Package Management — Per-Package Capping

### 3.1 Update `views/admin/packages.php`

Add capping fields to the create/edit form (after "Direct Referral Bonus"):

```php
<!-- Income Capping Section -->
<hr class="my-3">
<p class="fw-bold mb-2" style="font-size:.82rem;">🛡️ Income Capping (Optional)</p>
<div class="form-text mb-3">
  Leave at 0 to use system defaults. Set custom values to override for this package.
</div>

<div class="row g-3 mb-3">
  <div class="col-md-4">
    <label class="form-label">Income Cap (₱)</label>
    <div class="input-group input-group-sm">
      <span class="input-group-text">₱</span>
      <input type="number" name="income_cap" class="form-control"
        min="0" step="1000"
        value="<?= e($editPkg['income_cap'] ?? '') ?>"
        placeholder="<?= e(setting('income_cap_default', '30000')) ?>">
    </div>
    <div class="form-text">0 = use default (<?= fmt_money((float)setting('income_cap_default', '30000')) ?>)</div>
  </div>
  <div class="col-md-4">
    <label class="form-label">Reactivation Fee (₱)</label>
    <div class="input-group input-group-sm">
      <span class="input-group-text">₱</span>
      <input type="number" name="reactivation_fee" class="form-control"
        min="0" step="500"
        value="<?= e($editPkg['reactivation_fee'] ?? '') ?>"
        placeholder="<?= e(setting('reactivation_fee_default', '10000')) ?>">
    </div>
    <div class="form-text">0 = use default (<?= fmt_money((float)setting('reactivation_fee_default', '10000')) ?>)</div>
  </div>
  <div class="col-md-4">
    <label class="form-label">Reactivation Window</label>
    <div class="input-group input-group-sm">
      <input type="number" name="reactivation_window_days" class="form-control"
        min="0" max="180" step="1"
        value="<?= e($editPkg['reactivation_window_days'] ?? '') ?>"
        placeholder="<?= e(setting('reactivation_window_default', '15')) ?>">
      <span class="input-group-text">days</span>
    </div>
    <div class="form-text">0 = use default (<?= e(setting('reactivation_window_default', '15')) ?> days)</div>
  </div>
</div>
```

### 3.2 Update `Package::save()` in `models/Package.php`

```php
$fields = [
    'name'             => $data['name'],
    'entry_fee'        => $data['entry_fee'],
    'pairing_bonus'    => $data['pairing_bonus'],
    'daily_pair_cap'   => $data['daily_pair_cap'],
    'direct_ref_bonus' => $data['direct_ref_bonus'],
    'status'           => $data['status'] ?? 'active',
    // New capping fields — 0 means "use system default"
    'income_cap'               => (float)($data['income_cap'] ?? 0),
    'reactivation_fee'         => (float)($data['reactivation_fee'] ?? 0),
    'reactivation_window_days' => (int)($data['reactivation_window_days'] ?? 0),
];
```

---

## Phase 4: Core Capping Logic — Commission Engine

### 4.1 Update `Commission::processBinaryPlacement()` in `core/Commission.php`

Replace the existing pairing bonus logic with cap-aware logic:

```php
public static function processBinaryPlacement(
    int $newUserId,
    int $parentId,
    string $position
): void {
    $pdo  = db();
    $cur  = $parentId;
    $side = $position;

    while ($cur !== null) {
        // 1. Increment leg count
        $col = ($side === 'left') ? 'left_count' : 'right_count';
        $pdo->prepare("UPDATE users SET {$col} = {$col} + 1 WHERE id = ?")
            ->execute([$cur]);

        // 2. Read fresh state with package + capping info
        $st = $pdo->prepare("
            SELECT u.id, u.left_count, u.right_count,
                   u.pairs_paid, u.pairs_flushed, u.pairs_paid_today,
                   u.cap_status, u.binary_earned_this_cycle,
                   u.capped_at, u.reactivation_window_expires,
                   p.pairing_bonus, p.daily_pair_cap,
                   p.income_cap, p.reactivation_fee, p.reactivation_window_days
            FROM   users u
            LEFT JOIN packages p ON p.id = u.package_id
            WHERE  u.id = ?
              AND  p.pairing_bonus IS NOT NULL
        ");
        $st->execute([$cur]);
        $ancestor = $st->fetch();

        if (!$ancestor) {
            // Move up tree regardless
            $upRow = $pdo->prepare('SELECT binary_parent_id, binary_position FROM users WHERE id = ?');
            $upRow->execute([$cur]);
            $up = $upRow->fetch();
            $side = $up['binary_position'] ?? null;
            $cur  = isset($up['binary_parent_id']) ? (int)$up['binary_parent_id'] : null;
            continue;
        }

        // ── CAP CHECK: Skip entirely if capped or permanently inactive ──
        if ($ancestor['cap_status'] === 'perminact') {
            // Permanently inactive — skip all bonuses, but still walk tree
            goto NEXT_ANCESTOR;
        }

        if ($ancestor['cap_status'] === 'capped') {
            // Capped but not yet permanent — check if window expired
            if ($ancestor['reactivation_window_expires']
                && strtotime($ancestor['reactivation_window_expires']) <= time()) {
                // Window expired → permanent deactivation
                $pdo->prepare("
                    UPDATE users
                    SET cap_status = 'perminact'
                    WHERE id = ?
                ")->execute([$cur]);
                goto NEXT_ANCESTOR;
            }
            // Still in window — skip bonuses but don't walk past (they might reactivate)
            goto NEXT_ANCESTOR;
        }

        // ── ACTIVE MEMBER: Process pairs with cap tracking ──
        $processed = $ancestor['pairs_paid'] + $ancestor['pairs_flushed'];
        $available = min($ancestor['left_count'], $ancestor['right_count']);
        $newPairs  = $available - $processed;

        if ($newPairs > 0) {
            $capRemaining = (int)$ancestor['daily_pair_cap'] - (int)$ancestor['pairs_paid_today'];
            $payNow       = min($newPairs, max(0, $capRemaining));
            $flushNow     = $newPairs - $payNow;

            // Calculate potential earnings from this batch
            $pairBonus    = (float)$ancestor['pairing_bonus'];
            $batchEarnings = $payNow * $pairBonus;

            // ── LIFETIME CAP CHECK ──
            $currentEarned = (float)$ancestor['binary_earned_this_cycle'];
            $incomeCap     = (float)$ancestor['income_cap'] > 0
                ? (float)$ancestor['income_cap']
                : (float)setting('income_cap_default', '30000');

            $capRemainingLifetime = $incomeCap - $currentEarned;

            if ($capRemainingLifetime <= 0) {
                // Already at cap — shouldn't happen if status is correct, but handle it
                self::triggerCap($cur, $ancestor);
                goto NEXT_ANCESTOR;
            }

            if ($batchEarnings > $capRemainingLifetime) {
                // Partial payment — only pay up to cap, rest is "cap-saved"
                $maxPayablePairs = (int)floor($capRemainingLifetime / $pairBonus);
                $actualPayNow = min($payNow, max(0, $maxPayablePairs));
                $capSavedPairs = $payNow - $actualPayNow;
                $flushNow += $capSavedPairs; // Count as flushed (lost to cap)
                $payNow = $actualPayNow;
                $batchEarnings = $payNow * $pairBonus;
            }

            // Credit earned pairs
            if ($payNow > 0) {
                self::creditPairing($cur, $batchEarnings, $payNow, $newUserId);

                // Update lifetime earnings
                $pdo->prepare("
                    UPDATE users
                    SET binary_earned_this_cycle = binary_earned_this_cycle + ?
                    WHERE id = ?
                ")->execute([$batchEarnings, $cur]);
            }

            // Record flushed pairs (daily cap excess)
            if ($flushNow > 0) {
                self::recordFlush($cur, $flushNow, $newUserId);
            }

            // Update counters
            $pdo->prepare("
                UPDATE users
                SET pairs_paid       = pairs_paid       + :pay,
                    pairs_flushed    = pairs_flushed    + :flush,
                    pairs_paid_today = pairs_paid_today + :pay2
                WHERE id = :id
            ")->execute([
                ':pay'   => $payNow,
                ':flush' => $flushNow,
                ':pay2'  => $payNow,
                ':id'    => $cur,
            ]);

            // ── CHECK IF CAP REACHED AFTER THIS BATCH ──
            $newTotalEarned = $currentEarned + $batchEarnings;
            if ($newTotalEarned >= $incomeCap) {
                self::triggerCap($cur, $ancestor);
            }
        }

        NEXT_ANCESTOR:
        // 3. Move to ancestor's parent
        $upRow = $pdo->prepare('SELECT binary_parent_id, binary_position FROM users WHERE id = ?');
        $upRow->execute([$cur]);
        $up = $upRow->fetch();

        $side = $up['binary_position'] ?? null;
        $cur  = isset($up['binary_parent_id']) ? (int)$up['binary_parent_id'] : null;
        if (!$cur) break;
    }
}

/**
 * Trigger income cap on a member — deactivate and start reactivation window
 */
private static function triggerCap(int $userId, array $userData): void
{
    $pdo = db();
    $windowDays = (int)$userData['reactivation_window_days'] > 0
        ? (int)$userData['reactivation_window_days']
        : (int)setting('reactivation_window_default', '15');

    $windowExpires = date('Y-m-d H:i:s', strtotime("+{$windowDays} days"));

    $pdo->prepare("
        UPDATE users
        SET cap_status = 'capped',
            capped_at = NOW(),
            reactivation_window_expires = ?
        WHERE id = ?
    ")->execute([$windowExpires, $userId]);

    // Log the cap event
    $pdo->prepare("
        INSERT INTO commissions
          (user_id, type, amount, source_user_id, description, status)
        VALUES (?, 'pairing', 0.00, ?, ?, 'flushed')
    ")->execute([
        $userId,
        $userId,
        "Income cap reached — cycle ended. Reactivate by {$windowExpires}."
    ]);
}
```

### 4.2 Add Reactivation Logic to `User.php`

```php
/**
 * Reactivate a capped member — resets cycle, requires payment
 */
public static function reactivate(int $userId, float $paymentAmount, string $method = 'manual'): array
{
    $pdo = db();
    $user = self::find($userId);

    if (!$user) return ['ok' => false, 'error' => 'User not found.'];
    if ($user['cap_status'] !== 'capped') {
        return ['ok' => false, 'error' => 'Account is not capped — no reactivation needed.'];
    }
    if ($user['role'] !== 'member') {
        return ['ok' => false, 'error' => 'Only member accounts can reactivate.'];
    }

    $pkg = Package::find((int)$user['package_id']);
    $requiredFee = (float)($pkg['reactivation_fee'] ?? 0) > 0
        ? (float)$pkg['reactivation_fee']
        : (float)setting('reactivation_fee_default', '10000');

    // Validate payment amount
    if ($paymentAmount < $requiredFee) {
        return ['ok' => false, 'error' =>
            "Reactivation fee is " . fmt_money($requiredFee) .
            ". Received " . fmt_money($paymentAmount)
        ];
    }

    $pdo->beginTransaction();
    try {
        // Reset cap cycle
        $pdo->prepare("
            UPDATE users
            SET cap_status = 'active',
                binary_earned_this_cycle = 0.00,
                pairs_paid = 0,
                pairs_flushed = 0,
                pairs_paid_today = 0,
                reactivation_count = reactivation_count + 1,
                last_reactivated_at = NOW(),
                capped_at = NULL,
                reactivation_window_expires = NULL
            WHERE id = ?
        ")->execute([$userId]);

        // Record reactivation payment
        $pdo->prepare("
            INSERT INTO reactivation_payments
              (user_id, package_id, amount, payment_method, status, confirmed_at)
            VALUES (?, ?, ?, ?, 'confirmed', NOW())
        ")->execute([
            $userId,
            $user['package_id'],
            $paymentAmount,
            $method
        ]);

        $pdo->commit();
        return ['ok' => true, 'fee' => $requiredFee, 'new_cycle' => true];
    } catch (\Exception $e) {
        $pdo->rollBack();
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Check and process expired reactivation windows (cron job)
 */
public static function processExpiredWindows(): int
{
    $pdo = db();
    $st = $pdo->prepare("
        UPDATE users
        SET cap_status = 'perminact'
        WHERE cap_status = 'capped'
          AND reactivation_window_expires IS NOT NULL
          AND reactivation_window_expires <= NOW()
    ");
    $st->execute();
    return $st->rowCount();
}
```

---

## Phase 5: Member Reactivation UI/UX

### 5.1 Create `views/member/reactivate.php`

```php
<?php
/**
 * @file   views/member/reactivate.php
 * @brief  Member account reactivation page
 */
?>
<?php
$pageTitle = 'Reactivate Account';
$user = Auth::user();
$pkg = Package::find((int)$user['package_id']);
$fee = (float)($pkg['reactivation_fee'] ?? 0) > 0
    ? (float)$pkg['reactivation_fee']
    : (float)setting('reactivation_fee_default', '10000');
$windowDays = (int)($pkg['reactivation_window_days'] ?? 0) > 0
    ? (int)$pkg['reactivation_window_days']
    : (int)setting('reactivation_window_default', '15');
$deadline = $user['reactivation_window_expires']
    ? fmt_datetime($user['reactivation_window_expires'])
    : 'Unknown';
$daysLeft = $user['reactivation_window_expires']
    ? max(0, ceil((strtotime($user['reactivation_window_expires']) - time()) / 86400))
    : 0;
?>
<?php require 'views/partials/head.php'; ?>
<?php require 'views/partials/sidebar_member.php'; ?>

<div class="main-content">
  <?php require 'views/partials/topbar.php'; ?>
  <div class="page-content">
    <?= render_flash() ?>

    <!-- Status Banner -->
    <div class="alert alert-warning mb-4" style="border-left:4px solid var(--warning);">
      <div class="d-flex align-items-start gap-3">
        <div style="font-size:2rem;">🛡️</div>
        <div>
          <h5 class="fw-bold mb-1">Income Cap Reached</h5>
          <p class="mb-2" style="font-size:.9rem;line-height:1.6;">
            Your account has reached the maximum lifetime binary income cap of
            <strong><?= fmt_money((float)($pkg['income_cap'] ?? setting('income_cap_default', '30000'))) ?></strong>
            for this cycle. You cannot earn pairing bonuses until you reactivate.
          </p>
          <div class="d-flex gap-3 flex-wrap" style="font-size:.8rem;">
            <span class="badge bg-danger-subtle text-danger">
              ⏰ <?= $daysLeft ?> day<?= $daysLeft !== 1 ? 's' : '' ?> left to reactivate
            </span>
            <span class="badge bg-secondary-subtle text-secondary">
              Deadline: <?= $deadline ?>
            </span>
          </div>
        </div>
      </div>
    </div>

    <div class="row g-3">
      <!-- Reactivation Form -->
      <div class="col-12 col-lg-7">
        <div class="card">
          <div class="card-header">
            <span class="card-title">💳 Pay Reactivation Fee</span>
          </div>
          <div class="card-body">
            <div class="text-center mb-4 p-3 rounded" style="background:linear-gradient(135deg,#f8fafd,#e8ecf5);">
              <div style="font-size:.75rem;color:var(--muted);text-transform:uppercase;letter-spacing:1px;">
                Reactivation Fee
              </div>
              <div style="font-size:2.5rem;font-weight:800;font-family:var(--font-mono);color:var(--purple);">
                <?= fmt_money($fee) ?>
              </div>
              <div style="font-size:.8rem;color:var(--muted);">
                Resets your earning cap to zero · New cycle begins
              </div>
            </div>

            <form method="POST" action="<?= APP_URL ?>/?page=do_reactivate" id="reactivateForm">
              <?= csrf_field() ?>

              <div class="mb-3">
                <label class="form-label">Payment Method <span class="text-danger">*</span></label>
                <div class="d-flex gap-2 flex-wrap" id="methodBtns">
                  <?php
                  $gcashEnabled = setting('gcash_enabled', '1') === '1';
                  $mayaEnabled = setting('maya_enabled', '1') === '1';
                  $methods = [];
                  if ($gcashEnabled) $methods[] = ['gcash', 'GCash', '#0070d8', $user['gcash_number'] ?? ''];
                  if ($mayaEnabled) $methods[] = ['maya', 'Maya', '#48b0db', $user['maya_number'] ?? ''];
                  $methods[] = ['usdt', 'USDT TRC20', '#26a17b', $user['usdt_address'] ?? ''];
                  $methods[] = ['manual', 'Manual/Bank Transfer', '#6b7a99', ''];

                  foreach ($methods as $idx => [$val, $label, $color, $saved]):
                  ?>
                    <label class="method-option" style="--mc:<?= $color ?>;">
                      <input type="radio" name="payment_method" value="<?= $val ?>"
                        <?= $idx === 0 ? 'checked' : '' ?>
                        onchange="switchMethod('<?= $val ?>')">
                      <span><?= $label ?></span>
                      <?php if ($saved): ?><small><?= e(mask_account($saved)) ?></small><?php endif; ?>
                    </label>
                  <?php endforeach; ?>
                </div>
              </div>

              <div class="mb-3" id="accountGroup">
                <label class="form-label" id="accountLabel">Account / Reference Number <span class="text-danger">*</span></label>
                <input type="text" name="payment_ref" id="paymentRef" class="form-control"
                  placeholder="Enter transaction reference or account number" required>
                <div class="form-text" id="accountHint">
                  Enter the transaction reference number from your payment
                </div>
              </div>

              <div class="mb-3">
                <label class="form-label">Amount Paid <span class="text-danger">*</span></label>
                <div class="input-group">
                  <span class="input-group-text">₱</span>
                  <input type="number" name="amount_paid" class="form-control font-mono"
                    min="<?= $fee ?>" step="0.01" value="<?= $fee ?>" required>
                </div>
                <div class="form-text">Minimum: <?= fmt_money($fee) ?></div>
              </div>

              <div class="alert alert-info py-2 mb-3" style="font-size:.8rem;">
                <strong>ℹ How it works:</strong> After submitting your payment reference,
                an admin will verify and confirm your reactivation. Your cap will reset to zero
                and you can start earning pairing bonuses again immediately.
              </div>

              <button type="submit" class="btn btn-primary w-100 btn-lg" id="submitBtn">
                🚀 Submit Reactivation Request
              </button>
            </form>
          </div>
        </div>
      </div>

      <!-- Sidebar Info -->
      <div class="col-12 col-lg-5">
        <div class="card mb-3">
          <div class="card-header"><span class="card-title">📊 Current Cycle Stats</span></div>
          <div class="card-body">
            <table class="info-table">
              <tr>
                <td>Cap Status</td>
                <td><span class="badge bg-warning-subtle text-warning">⏸ Capped</span></td>
              </tr>
              <tr>
                <td>Earned This Cycle</td>
                <td class="font-mono fw-bold text-danger">
                  <?= fmt_money((float)$user['binary_earned_this_cycle']) ?>
                </td>
              </tr>
              <tr>
                <td>Income Cap</td>
                <td class="font-mono fw-bold">
                  <?= fmt_money((float)($pkg['income_cap'] ?? setting('income_cap_default', '30000'))) ?>
                </td>
              </tr>
              <tr>
                <td>Previous Reactivations</td>
                <td class="font-mono"><?= (int)$user['reactivation_count'] ?></td>
              </tr>
              <tr>
                <td>Capped On</td>
                <td><?= $user['capped_at'] ? fmt_datetime($user['capped_at']) : '—' ?></td>
              </tr>
            </table>
          </div>
        </div>

        <div class="card">
          <div class="card-header"><span class="card-title">⚠️ What Happens If I Don't Reactivate?</span></div>
          <div class="card-body">
            <ul class="list-unstyled mb-0" style="font-size:.85rem;line-height:1.8;">
              <li>❌ No pairing bonuses earned</li>
              <li>❌ Your position is skipped in pair counting</li>
              <li>❌ After <?= $windowDays ?> days, account becomes <strong>permanently inactive</strong></li>
              <li>✅ Direct referrals still work (one-time bonus)</li>
              <li>✅ Unilevel bonuses still flow to your upline</li>
              <li>✅ Your downline remains intact</li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
.method-option {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
  padding: .55rem 1rem;
  background: #f8fafd;
  border: 1.5px solid #dde3ef;
  border-radius: .6rem;
  cursor: pointer;
  font-size: .8rem;
  font-weight: 600;
  color: #374151;
  transition: all .15s;
  min-width: 100px;
  text-align: center;
}
.method-option small {
  font-size: .65rem;
  font-weight: 400;
  color: #9ca3af;
}
.method-option input[type=radio] { display: none; }
.method-option:has(input:checked) {
  border-color: var(--mc, var(--primary));
  background: color-mix(in srgb, var(--mc, var(--primary)) 10%, white);
  color: var(--mc, var(--primary));
}
</style>

<script>
function switchMethod(method) {
  const hints = {
    gcash: 'Enter your GCash transaction reference number',
    maya: 'Enter your Maya transaction reference number',
    usdt: 'Enter your USDT TRC20 transaction hash (TXID)',
    manual: 'Enter bank transfer reference or receipt number'
  };
  document.getElementById('accountHint').textContent = hints[method] || 'Enter transaction reference';
}
</script>

<?php require 'views/partials/footer.php'; ?>
```

### 5.2 Add Reactivation Route & Controller

In `index.php` routes:

```php
'reactivate'      => ['MemberController', 'reactivate',      'member'],
'do_reactivate'   => ['MemberController', 'doReactivate',    'member'],
```

In `controllers/MemberController.php`:

```php
public function reactivate(): void
{
    Auth::guard('member');
    $user = Auth::user();

    // Only show if actually capped
    if ($user['cap_status'] !== 'capped') {
        flash('info', 'Your account is active — no reactivation needed.');
        redirect('/?page=dashboard');
    }

    require 'views/member/reactivate.php';
}

public function doReactivate(): void
{
    Auth::guard('member');
    csrf_verify();

    $userId = Auth::id();
    $amount = (float)($_POST['amount_paid'] ?? 0);
    $method = trim($_POST['payment_method'] ?? 'manual');
    $ref = trim($_POST['payment_ref'] ?? '');

    if (!$ref) {
        flash('error', 'Please provide a payment reference number.');
        redirect('/?page=reactivate');
    }

    // For manual payments, create pending record for admin approval
    if ($method === 'manual') {
        $pdo = db();
        $pdo->prepare("
            INSERT INTO reactivation_payments
              (user_id, package_id, amount, payment_method, payment_ref, status)
            VALUES (?, ?, ?, ?, ?, 'pending')
        ")->execute([
            $userId,
            Auth::user()['package_id'],
            $amount,
            $method,
            $ref
        ]);
        flash('success', 'Reactivation request submitted. Admin will verify your payment shortly.');
        redirect('/?page=reactivate');
    }

    // Auto-confirm for digital payments (in production, integrate with payment gateway)
    $result = User::reactivate($userId, $amount, $method);

    if ($result['ok']) {
        flash('success', 'Account reactivated successfully! Your earning cap has been reset.');
        redirect('/?page=dashboard');
    } else {
        flash('error', $result['error']);
        redirect('/?page=reactivate');
    }
}
```

---

## Phase 6: Admin Reactivation Management

### 6.1 Update `views/admin/dashboard.php`

Add reactivation stats to dashboard KPIs:

```php
// Add to stats query in AdminController::dashboard()
$reactivationStats = db()->query("
    SELECT
        COUNT(*) as total_pending,
        COALESCE(SUM(amount),0) as pending_revenue
    FROM reactivation_payments
    WHERE status = 'pending'
")->fetch();

// Add to dashboard cards array:
['Pending Reactivations', number_format((int)$reactivationStats['total_pending']), 'purple', '⏳',
    fmt_money((float)$reactivationStats['pending_revenue']) . ' awaiting confirmation'],
```

### 6.2 Create `views/admin/reactivations.php`

```php
<?php
/**
 * @file   views/admin/reactivations.php
 * @brief  Admin reactivation management UI
 */
?>
<?php $pageTitle = 'Reactivation Requests'; ?>
<?php require 'views/partials/head.php'; ?>
<?php require 'views/partials/sidebar_admin.php'; ?>

<div class="main-content">
  <?php require 'views/partials/topbar.php'; ?>
  <div class="page-content">
    <?= render_flash() ?>

    <!-- Stats -->
    <div class="row g-3 mb-3">
      <?php
      $stats = db()->query("
        SELECT
          COUNT(*) as total,
          SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) as pending,
          SUM(CASE WHEN status='confirmed' THEN 1 ELSE 0 END) as confirmed,
          COALESCE(SUM(CASE WHEN status='confirmed' THEN amount END),0) as revenue
        FROM reactivation_payments
      ")->fetch();
      foreach ([
        ['Total Requests', number_format((int)$stats['total']), 'primary', 'primary'],
        ['Pending', number_format((int)$stats['pending']), 'warning', 'warning'],
        ['Confirmed', number_format((int)$stats['confirmed']), 'success', 'success'],
        ['Revenue', fmt_money((float)$stats['revenue']), 'purple', 'purple'],
      ] as [$label, $val, $accent, $color]):
      ?>
        <div class="col-6 col-xl-3">
          <div class="card stat-card">
            <div class="stat-accent stat-accent-<?= $accent ?>"></div>
            <div class="card-body pt-4">
              <div class="stat-label"><?= $label ?></div>
              <div class="stat-value text-<?= $color ?>"><?= $val ?></div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Filters -->
    <ul class="nav nav-pills mb-3">
      <?php foreach (['pending' => '⏳ Pending', 'confirmed' => '✅ Confirmed', 'rejected' => '❌ Rejected', '' => '📋 All'] as $s => $label): ?>
        <li class="nav-item">
          <a class="nav-link <?= ($status ?? '') === $s ? 'active' : '' ?>"
            href="<?= APP_URL ?>/?page=admin_reactivations&status=<?= $s ?>">
            <?= $label ?>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>

    <!-- Table -->
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span class="card-title">🔄 Reactivation Requests</span>
        <span class="badge bg-secondary-subtle text-secondary"><?= $result['total'] ?? 0 ?> records</span>
      </div>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead>
            <tr>
              <th>#</th>
              <th>Member</th>
              <th>Amount</th>
              <th>Method</th>
              <th>Reference</th>
              <th>Requested</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($result['data'])): ?>
              <tr><td colspan="8" class="text-center py-5 text-muted">No reactivation requests.</td></tr>
            <?php else: foreach ($result['data'] as $r): ?>
              <tr>
                <td class="td-muted" style="font-size:.72rem;"><?= $r['id'] ?></td>
                <td>
                  <a href="<?= APP_URL ?>/?page=admin_user_view&id=<?= $r['user_id'] ?>"
                    class="fw-bold text-decoration-none">@<?= e($r['username']) ?></a>
                  <div class="text-muted" style="font-size:.72rem;"><?= e($r['full_name'] ?? '') ?></div>
                </td>
                <td class="font-mono fw-bold td-green"><?= fmt_money($r['amount']) ?></td>
                <td>
                  <span class="badge" style="background:<?= match($r['payment_method']){'gcash'=>'#0070d820','maya'=>'#48b0db20','usdt'=>'#26a17b20',default=>'#6b7a9920'} ?>;color:<?= match($r['payment_method']){'gcash'=>'#0070d8','maya'=>'#48b0db','usdt'=>'#26a17b',default=>'#6b7a99'} ?>;">
                    <?= strtoupper($r['payment_method']) ?>
                  </span>
                </td>
                <td class="font-mono" style="font-size:.78rem;"><?= e($r['payment_ref']) ?></td>
                <td class="td-muted" style="font-size:.75rem;"><?= fmt_datetime($r['created_at']) ?></td>
                <td>
                  <?php $b = match($r['status']) {
                    'pending' => 'bg-warning-subtle text-warning',
                    'confirmed' => 'bg-success-subtle text-success',
                    'rejected' => 'bg-danger-subtle text-danger',
                    default => 'bg-secondary-subtle'
                  }; ?>
                  <span class="badge <?= $b ?>"><?= ucfirst($r['status']) ?></span>
                </td>
                <td>
                  <?php if ($r['status'] === 'pending'): ?>
                    <div class="d-flex gap-1">
                      <button class="btn btn-sm btn-success"
                        onclick="confirmReactivation(<?= $r['id'] ?>, '<?= e($r['username']) ?>', <?= $r['amount'] ?>)">
                        ✓ Confirm
                      </button>
                      <button class="btn btn-sm btn-danger"
                        onclick="rejectReactivation(<?= $r['id'] ?>, '<?= e($r['username']) ?>')">
                        ✕ Reject
                      </button>
                    </div>
                  <?php else: ?>
                    <span class="td-muted" style="font-size:.75rem;">
                      <?= $r['confirmed_at'] ? fmt_datetime($r['confirmed_at']) : '—' ?>
                    </span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
      <?php if (($result['total_pages'] ?? 0) > 1): ?>
        <div class="card-footer">
          <?= pagination_links($result, APP_URL . '/?page=admin_reactivations&status=' . urlencode($status ?? '')) ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Confirm Modal -->
<div class="modal fade" id="reactivateModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Confirm Reactivation</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p id="reactivateDesc" style="font-size:.9rem;"></p>
        <div class="alert alert-info py-2" style="font-size:.8rem;">
          This will reset the member's binary earned counter to zero and restore active status.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <form method="POST" action="<?= APP_URL ?>/?page=admin_confirm_reactivation" class="m-0">
          <?= csrf_field() ?>
          <input type="hidden" name="id" id="reactivateId">
          <button type="submit" class="btn btn-success">✓ Confirm Reactivation</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
function confirmReactivation(id, user, amount) {
  document.getElementById('reactivateDesc').innerHTML =
    `Confirm reactivation for <strong>@${user}</strong> with payment of <strong>${amount.toLocaleString('en-PH',{style:'currency',currency:'PHP'})}</strong>?`;
  document.getElementById('reactivateId').value = id;
  new bootstrap.Modal(document.getElementById('reactivateModal')).show();
}
function rejectReactivation(id, user) {
  showConfirm({
    title: 'Reject Reactivation',
    message: `Reject reactivation request from <strong>@${user}</strong>?`,
    confirmText: 'Reject',
    confirmClass: 'btn-danger',
    onConfirm: () => {
      const f = document.createElement('form');
      f.method = 'POST';
      f.action = '<?= APP_URL ?>/?page=admin_reject_reactivation';
      f.innerHTML = `<?= csrf_field() ?><input type="hidden" name="id" value="${id}">`;
      document.body.appendChild(f);
      f.submit();
    }
  });
}
</script>

<?php require 'views/partials/footer.php'; ?>
```

### 6.3 Add Admin Reactivation Methods to `AdminController.php`

```php
public function reactivations(): void
{
    Auth::guard('admin');
    $page = max(1, (int)($_GET['pg'] ?? 1));
    $status = $_GET['status'] ?? 'pending';

    $where = '1=1';
    $params = [];
    if ($status) {
        $where .= ' AND rp.status = ?';
        $params[] = $status;
    }

    $result = paginate(
        "SELECT rp.*, u.username, u.full_name, u.cap_status
         FROM reactivation_payments rp
         JOIN users u ON u.id = rp.user_id
         WHERE {$where}
         ORDER BY rp.created_at DESC",
        $params,
        $page,
        25
    );

    require 'views/admin/reactivations.php';
}

public function confirmReactivation(): void
{
    Auth::guard('admin');
    csrf_verify();

    $id = (int)($_POST['id'] ?? 0);
    $pdo = db();

    $payment = $pdo->prepare("
        SELECT rp.*, u.cap_status
        FROM reactivation_payments rp
        JOIN users u ON u.id = rp.user_id
        WHERE rp.id = ?
    ")->execute([$id])->fetch();

    if (!$payment || $payment['status'] !== 'pending') {
        flash('error', 'Invalid or already-processed reactivation.');
        redirect('/?page=admin_reactivations');
    }

    // Process reactivation
    $result = User::reactivate(
        (int)$payment['user_id'],
        (float)$payment['amount'],
        $payment['payment_method']
    );

    if ($result['ok']) {
        // Mark payment as confirmed
        $pdo->prepare("
            UPDATE reactivation_payments
            SET status = 'confirmed', processed_by = ?, confirmed_at = NOW()
            WHERE id = ?
        ")->execute([Auth::id(), $id]);

        flash('success', 'Reactivation confirmed and member account restored.');
    } else {
        flash('error', $result['error']);
    }

    redirect('/?page=admin_reactivations');
}

public function rejectReactivation(): void
{
    Auth::guard('admin');
    csrf_verify();

    $id = (int)($_POST['id'] ?? 0);
    db()->prepare("
        UPDATE reactivation_payments
        SET status = 'rejected', processed_by = ?
        WHERE id = ? AND status = 'pending'
    ")->execute([Auth::id(), $id]);

    flash('success', 'Reactivation request rejected.');
    redirect('/?page=admin_reactivations');
}
```

---

## Phase 7: Member Dashboard — Cap Status Widget

### 7.1 Update `views/member/dashboard.php`

Add cap status widget (before "Today's Pairing Cap"):

```php
<?php if ($user['cap_status'] !== 'active'): ?>
  <!-- Cap Alert Banner -->
  <div class="col-12 mb-3">
    <div class="card" style="border-left:4px solid <?= $user['cap_status'] === 'capped' ? 'var(--warning)' : 'var(--danger)' ?>;">
      <div class="card-body d-flex align-items-center gap-3">
        <div style="font-size:2rem;"><?= $user['cap_status'] === 'capped' ? '⏸️' : '🚫' ?></div>
        <div class="flex-grow-1">
          <h5 class="fw-bold mb-1">
            <?= $user['cap_status'] === 'capped' ? 'Income Cap Reached' : 'Permanently Inactive' ?>
          </h5>
          <p class="mb-2" style="font-size:.85rem;">
            <?php if ($user['cap_status'] === 'capped'): ?>
              You have <?= max(0, ceil((strtotime($user['reactivation_window_expires']) - time()) / 86400)) ?>
              days left to reactivate.
              <a href="<?= APP_URL ?>/?page=reactivate" class="fw-bold">Reactivate now →</a>
            <?php else: ?>
              Your account is permanently inactive due to missed reactivation window.
              Contact support for assistance.
            <?php endif; ?>
          </p>
        </div>
        <?php if ($user['cap_status'] === 'capped'): ?>
          <a href="<?= APP_URL ?>/?page=reactivate" class="btn btn-warning">Reactivate</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
<?php endif; ?>
```

### 7.2 Update Dashboard Stats Query

In `MemberController::dashboard()`, add cap status to summary:

```php
$summary = Commission::summary($user['id']);
$summary['cap_status'] = $user['cap_status'];
$summary['binary_earned_this_cycle'] = (float)$user['binary_earned_this_cycle'];
$summary['reactivation_count'] = (int)$user['reactivation_count'];
```

---

## Phase 8: Admin User View — Cap Management

### 8.1 Update `views/admin/user_view.php`

Add cap management section to profile card:

```php
<!-- Capping Status (Admin Override) -->
<div class="card mb-3">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span class="card-title">🛡️ Cap Status</span>
    <span class="badge <?= match($user['cap_status']) {
      'active' => 'bg-success-subtle text-success',
      'capped' => 'bg-warning-subtle text-warning',
      'perminact' => 'bg-danger-subtle text-danger',
      default => 'bg-secondary-subtle'
    } ?>">
      <?= ucfirst($user['cap_status']) ?>
    </span>
  </div>
  <div class="card-body">
    <table class="info-table">
      <tr>
        <td>Binary Earned (Cycle)</td>
        <td class="font-mono fw-bold"><?= fmt_money((float)$user['binary_earned_this_cycle']) ?></td>
      </tr>
      <tr>
        <td>Income Cap</td>
        <td class="font-mono">
          <?= fmt_money((float)($pkg['income_cap'] ?? setting('income_cap_default', '30000'))) ?>
        </td>
      </tr>
      <tr>
        <td>Reactivations</td>
        <td class="font-mono"><?= (int)$user['reactivation_count'] ?></td>
      </tr>
      <tr>
        <td>Capped At</td>
        <td><?= $user['capped_at'] ? fmt_datetime($user['capped_at']) : '—' ?></td>
      </tr>
      <tr>
        <td>Window Expires</td>
        <td><?= $user['reactivation_window_expires'] ? fmt_datetime($user['reactivation_window_expires']) : '—' ?></td>
      </tr>
    </table>

    <?php if ($user['cap_status'] !== 'active'): ?>
      <hr class="my-3">
      <form method="POST" action="<?= APP_URL ?>/?page=admin_force_reactivate" class="m-0">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $user['id'] ?>">
        <div class="d-flex gap-2">
          <button type="submit" class="btn btn-success btn-sm"
            onclick="return confirm('Force reactivate @<?= e($user['username']) ?>? This resets their cap counter.')">
            🔄 Force Reactivate (Free)
          </button>
          <?php if ($user['cap_status'] === 'capped'): ?>
            <button type="submit" name="action" value="extend_window" class="btn btn-warning btn-sm"
              formaction="<?= APP_URL ?>/?page=admin_extend_window">
              ⏰ Extend Window (+7 days)
            </button>
          <?php endif; ?>
        </div>
      </form>
    <?php endif; ?>
  </div>
</div>
```

### 8.2 Add Admin Override Methods

In `AdminController.php`:

```php
public function forceReactivate(): void
{
    Auth::guard('admin');
    csrf_verify();

    $id = (int)($_POST['id'] ?? 0);
    $user = User::find($id);

    if (!$user || $user['role'] !== 'member') {
        flash('error', 'Invalid member.');
        redirect('/?page=admin_users');
    }

    // Force reactivation without payment (admin override)
    db()->prepare("
        UPDATE users
        SET cap_status = 'active',
            binary_earned_this_cycle = 0.00,
            pairs_paid = 0,
            pairs_flushed = 0,
            pairs_paid_today = 0,
            reactivation_count = reactivation_count + 1,
            last_reactivated_at = NOW(),
            capped_at = NULL,
            reactivation_window_expires = NULL
        WHERE id = ?
    ")->execute([$id]);

    flash('success', "@{$user['username']} force-reactivated. Cap counter reset.");
    redirect("/?page=admin_user_view&id={$id}");
}

public function extendWindow(): void
{
    Auth::guard('admin');
    csrf_verify();

    $id = (int)($_POST['id'] ?? 0);
    $newExpiry = date('Y-m-d H:i:s', strtotime('+7 days'));

    db()->prepare("
        UPDATE users
        SET reactivation_window_expires = ?
        WHERE id = ? AND cap_status = 'capped'
    ")->execute([$newExpiry, $id]);

    flash('success', 'Reactivation window extended by 7 days.');
    redirect("/?page=admin_user_view&id={$id}");
}
```

---

## Phase 9: Cron Jobs & Automation

### 9.1 Create `cron/process_capping.php`

```php
<?php
/**
 * @file   cron/process_capping.php
 * @brief  Midnight cron: Process expired reactivation windows
 *
 * Crontab: 0 0 * * * php /path/to/site/cron/process_capping.php
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../models/User.php';

// Process expired windows
$expired = User::processExpiredWindows();
echo date('Y-m-d H:i:s') . " — Processed {$expired} expired reactivation windows.\n";

// Also reset daily pair caps (existing functionality)
$affected = db()->exec("UPDATE users SET pairs_paid_today = 0 WHERE role = 'member'");
db()->prepare("UPDATE settings SET value = ? WHERE key_name = 'last_reset'")
    ->execute([date('Y-m-d H:i:s')]);
echo date('Y-m-d H:i:s') . " — Reset daily pair caps for {$affected} members.\n";
```

### 9.2 Update Settings Page Cron Display

In `views/admin/settings.php`, update the cron section:

```php
<div class="rounded p-3 mb-3 font-mono" style="background:#f4f6fb;font-size:.75rem;color:var(--muted);">
  Crontab (Combined):<br>
  <strong style="color:#111;">0 0 * * * php /path/to/site/cron/process_capping.php</strong>
</div>
```

---

## Phase 10: Route Registration & Final Integration

### 10.1 Complete Route Table Updates (`index.php`)

```php
// ── Member Reactivation ───────────────────────────
'reactivate'        => ['MemberController', 'reactivate',        'member'],
'do_reactivate'     => ['MemberController', 'doReactivate',      'member'],

// ── Admin Reactivation Management ─────────────────
'admin_reactivations'        => ['AdminController', 'reactivations',        'admin'],
'admin_confirm_reactivation' => ['AdminController', 'confirmReactivation',  'admin'],
'admin_reject_reactivation'  => ['AdminController', 'rejectReactivation',   'admin'],
'admin_force_reactivate'     => ['AdminController', 'forceReactivate',      'admin'],
'admin_extend_window'        => ['AdminController', 'extendWindow',         'admin'],
```

### 10.2 Add Admin Navigation Link

In `views/partials/sidebar_admin.php`, add to Finance section:

```php
<a href="<?= APP_URL ?>/?page=admin_reactivations"
   class="nav-item-link <?= $cp === 'admin_reactivations' ? 'active' : '' ?>">
  <span class="nav-icon">🔄</span> Reactivations
  <?php
  $pendingReacts = (int)db()->query("
      SELECT COUNT(*) FROM reactivation_payments WHERE status='pending'
  ")->fetchColumn();
  if ($pendingReacts):
  ?>
    <span class="nav-badge"><?= $pendingReacts ?></span>
  <?php endif; ?>
</a>
```

### 10.3 Update `Commission::summary()` to Exclude Capped Earnings

```php
public static function summary(int $userId): array
{
    $st = db()->prepare("
        SELECT
          COALESCE(SUM(CASE WHEN type='pairing' AND status='credited' THEN amount END), 0) AS total_pairing,
          COALESCE(SUM(CASE WHEN type='direct_referral' AND status='credited' THEN amount END), 0) AS total_direct,
          COALESCE(SUM(CASE WHEN type='indirect_referral' AND status='credited' THEN amount END), 0) AS total_indirect,
          COALESCE(SUM(CASE WHEN status='credited' THEN amount END), 0) AS total_earned,
          COALESCE(SUM(CASE WHEN type='pairing' AND status='flushed' THEN pairs_count END), 0) AS total_flushed_pairs
        FROM commissions
        WHERE user_id = ?
          AND created_at >= COALESCE(
            (SELECT last_reactivated_at FROM users WHERE id = ?),
            '1970-01-01'
          )
    ");
    $st->execute([$userId, $userId]);
    return $st->fetch();
}
```

---

## Summary of Changes by File

| File                                      | Change Type | Description                                   |
| ----------------------------------------- | ----------- | --------------------------------------------- |
| `migrations/001_add_capping_schema.sql`   | New         | Database schema for capping                   |
| `migrations/002_add_capping_settings.sql` | New         | System settings defaults                      |
| `models/Package.php`                      | Modify      | Add `getCappingConfig()`                      |
| `models/User.php`                         | Modify      | Add `reactivate()`, `processExpiredWindows()` |
| `core/Commission.php`                     | Modify      | Cap-aware pairing logic                       |
| `controllers/AdminController.php`         | Modify      | Settings save, reactivation management        |
| `controllers/MemberController.php`        | Modify      | Reactivation request handling                 |
| `views/admin/settings.php`                | Modify      | Capping settings UI                           |
| `views/admin/packages.php`                | Modify      | Per-package capping fields                    |
| `views/admin/dashboard.php`               | Modify      | Reactivation stats                            |
| `views/admin/user_view.php`               | Modify      | Cap status & admin override                   |
| `views/admin/reactivations.php`           | New         | Admin reactivation queue                      |
| `views/member/reactivate.php`             | New         | Member reactivation payment                   |
| `views/member/dashboard.php`              | Modify      | Cap alert banner                              |
| `views/partials/sidebar_admin.php`        | Modify      | Reactivations nav link                        |
| `cron/process_capping.php`                | New         | Midnight cron job                             |
| `index.php`                               | Modify      | Route registrations                           |

---

## Testing Checklist

- [ ] **Phase 1**: Run migrations, verify schema changes
- [ ] **Phase 2**: Admin can update default capping settings
- [ ] **Phase 3**: Per-package capping overrides work
- [ ] **Phase 4**: Member hits cap → status changes to `capped`
- [ ] **Phase 4**: Capped member skipped in pair counting
- [ ] **Phase 5**: Member sees reactivation page, can submit payment
- [ ] **Phase 6**: Admin sees pending reactivations, can confirm/reject
- [ ] **Phase 6**: Confirmed reactivation resets cap counter
- [ ] **Phase 7**: Dashboard shows cap alert when capped
- [ ] **Phase 8**: Admin can force-reactivate or extend window
- [ ] **Phase 9**: Cron expires windows after deadline
- [ ] **Edge**: Member with 0 cap (unlimited) works if capping disabled
- [ ] **Edge**: Reactivation during partial pair batch handles correctly
