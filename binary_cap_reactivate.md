I'll analyze the simulator and existing codebase to design a complete implementation plan for the capping & reactivation mechanics. Let me first examine the current package structure and database schema to understand what exists.
Now I have a thorough understanding of both the simulator's capping mechanics and the existing codebase. Let me provide a complete phased implementation plan.

---

# MLM Binary System — Lifetime Income Cap & Reactivation Implementation Plan

## Overview

This implementation adds a **maximum lifetime income cap** that covers **all commission types** (binary pairing, direct referral, indirect/unilevel, and any future commission streams). Once a member's cumulative credited commissions reach this cap, their account becomes **inactive** — they stop earning pairing bonuses and are skipped during pair counting. To resume earning, the member pays a **Reactivation Fee** within a **Reactivation Window**. If they miss the window, they become **permanently inactive**. Reactivation resets the cap counter to zero for a fresh earning cycle.

---

## Architecture Summary

| Component             | Change                                                                                                                                                                                  |
| --------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Database**          | Add `income_cap`, `reactivation_fee`, `reactivation_window` to `packages` table; add `lifetime_earned`, `cap_reached_at`, `reactivation_count`, `permanently_inactive` to `users` table |
| **Models**            | Extend `Package.php` with cap/reactivation fields; extend `User.php` with cap tracking and reactivation methods                                                                         |
| **Commission Engine** | Modify `Commission.php` to check cap before crediting any commission; skip capped/inactive members in binary placement                                                                  |
| **Member UI**         | Add cap progress widget on dashboard; reactivation form when capped; permanent inactivity notice                                                                                        |
| **Admin UI**          | Add cap/reactivation settings to package editor; member status indicators for capped/permanently inactive states                                                                        |
| **API**               | New endpoints for reactivation flow and cap status                                                                                                                                      |

---

## Phase 1: Database Schema Changes

### 1.1 Alter `packages` Table

Add three new columns to store the cap and reactivation configuration per package:

```sql
-- Add to packages table
ALTER TABLE packages
  ADD COLUMN income_cap DECIMAL(14,2) NOT NULL DEFAULT 0.00
    COMMENT 'Maximum lifetime earnings cap (0 = unlimited)',
  ADD COLUMN reactivation_fee DECIMAL(12,2) NOT NULL DEFAULT 0.00
    COMMENT 'Fee to pay to reactivate after capping',
  ADD COLUMN reactivation_window TINYINT UNSIGNED NOT NULL DEFAULT 0
    COMMENT 'Days to reactivate before permanent inactivation (0 = no window)';
```

**Rationale:**

- `income_cap = 0` means no capping (backward compatible with existing packages)
- `reactivation_window = 0` means no automatic permanent inactivation (admin must manually handle)
- All monetary fields use `DECIMAL` to prevent floating-point errors in financial calculations

### 1.2 Alter `users` Table

Add tracking columns for each member's cap status:

```sql
-- Add to users table
ALTER TABLE users
  ADD COLUMN lifetime_earned DECIMAL(14,2) NOT NULL DEFAULT 0.00
    COMMENT 'Cumulative credited commissions this cycle',
  ADD COLUMN cap_reached_at TIMESTAMP NULL DEFAULT NULL
    COMMENT 'When the member hit the income cap',
  ADD COLUMN reactivation_count INT UNSIGNED NOT NULL DEFAULT 0
    COMMENT 'How many times the member has reactivated',
  ADD COLUMN permanently_inactive TINYINT(1) NOT NULL DEFAULT 0
    COMMENT '1 = missed reactivation window, permanently inactive',
  ADD COLUMN last_reactivation_at TIMESTAMP NULL DEFAULT NULL
    COMMENT 'Last successful reactivation timestamp';
```

**Rationale:**

- `lifetime_earned` resets to 0 on each reactivation (new cycle)
- `cap_reached_at` enables the reactivation window countdown
- `permanently_inactive` is a separate flag so admins can still see history and manually intervene if needed

### 1.3 New Table: `reactivation_payments`

Track reactivation payments as formal financial records:

```sql
CREATE TABLE reactivation_payments (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id       INT UNSIGNED NOT NULL,
  package_id    INT UNSIGNED NOT NULL,
  amount        DECIMAL(12,2) NOT NULL,
  payment_method ENUM('gcash','maya','usdt','manual') NOT NULL DEFAULT 'manual',
  processed_by  INT UNSIGNED NULL,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (package_id) REFERENCES packages(id),
  FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
```

**Rationale:**

- Reactivation payments are real financial transactions that need audit trails
- Supports both self-service (member pays from e-wallet) and admin-assisted reactivation
- Links back to the package that defined the fee, in case package terms change later

---

## Phase 2: Package Model Extension

### 2.1 Update `Package.php`

Extend `find()`, `all()`, `save()`, and `withLevels()` to include the new fields.

**In `find()` and `all()` queries:**
The existing queries already use `SELECT * FROM packages`, so the new columns are automatically included. No query changes needed.

**In `save()`:**
Add the three new fields to the `$fields` array:

```php
$fields = [
    'name'              => $data['name'],
    'entry_fee'         => $data['entry_fee'],
    'pairing_bonus'     => $data['pairing_bonus'],
    'daily_pair_cap'    => $data['daily_pair_cap'],
    'direct_ref_bonus'  => $data['direct_ref_bonus'],
    'status'            => $data['status'] ?? 'active',
    'income_cap'        => $data['income_cap'] ?? 0,
    'reactivation_fee'  => $data['reactivation_fee'] ?? 0,
    'reactivation_window' => $data['reactivation_window'] ?? 0,
];
```

**Add validation helper:**

```php
public static function hasCap(int $packageId): bool
{
    $pkg = self::find($packageId);
    return $pkg && (float)$pkg['income_cap'] > 0;
}
```

### 2.2 Update Package Admin Form (`packages.php`)

Add a new section in the create/edit form, after the indirect levels:

```html
<!-- Income Cap & Reactivation Section -->
<div
  class="mb-3"
  style="border:1px solid var(--bs-border-color);border-radius:.6rem;padding:1rem;background:#fafafa;"
>
  <label class="form-label fw-bold">🛡️ Income Capping & Reactivation</label>
  <div class="form-text mb-3">
    Set a lifetime earnings cap. When reached, the member stops earning until
    they reactivate. Set all to 0 to disable capping for this package.
  </div>

  <div class="row g-3 mb-3">
    <div class="col-md-4">
      <label class="form-label">Lifetime Income Cap (₱)</label>
      <input
        type="number"
        name="income_cap"
        class="form-control"
        inputmode="decimal"
        min="0"
        step="0.01"
        value="<?= e($editPkg['income_cap'] ?? 0) ?>"
        placeholder="0 = unlimited"
      />
      <div class="form-text">Max total commissions per cycle</div>
    </div>
    <div class="col-md-4">
      <label class="form-label">Reactivation Fee (₱)</label>
      <input
        type="number"
        name="reactivation_fee"
        class="form-control"
        inputmode="decimal"
        min="0"
        step="0.01"
        value="<?= e($editPkg['reactivation_fee'] ?? 0) ?>"
        placeholder="0 = free"
      />
      <div class="form-text">Fee to reset cap counter</div>
    </div>
    <div class="col-md-4">
      <label class="form-label">Reactivation Window (days)</label>
      <input
        type="number"
        name="reactivation_window"
        class="form-control"
        inputmode="numeric"
        min="0"
        max="365"
        value="<?= e($editPkg['reactivation_window'] ?? 0) ?>"
        placeholder="0 = no limit"
      />
      <div class="form-text">Days before permanent inactivation</div>
    </div>
  </div>

  <!-- Visual indicator of cap relative to entry fee -->
  <div
    id="capRatioHint"
    class="form-text"
    style="color:var(--primary);font-weight:500;"
  >
    <?php $cap = (float)($editPkg['income_cap'] ?? 0); $entry =
    (float)($editPkg['entry_fee'] ?? 1); if ($cap > 0) echo 'Cap is ' .
    number_format($cap / $entry, 1) . '× the entry fee'; ?>
  </div>
</div>
```

---

## Phase 3: User Model — Cap Tracking & Reactivation

### 3.1 Add Cap-Aware Methods to `User.php`

```php
/**
 * Check if user has reached their income cap.
 */
public static function isCapped(int $userId): bool
{
    $user = self::find($userId);
    if (!$user) return false;

    $cap = (float)($user['income_cap'] ?? 0);
    if ($cap <= 0) return false; // No cap configured

    return (float)$user['lifetime_earned'] >= $cap;
}

/**
 * Check if user is permanently inactive (missed reactivation window).
 */
public static function isPermanentlyInactive(int $userId): bool
{
    $user = self::find($userId);
    return $user && (int)$user['permanently_inactive'] === 1;
}

/**
 * Check if user can earn commissions (active, not capped, not permanently inactive).
 */
public static function canEarn(int $userId): bool
{
    $user = self::find($userId);
    if (!$user) return false;
    if ($user['status'] !== 'active') return false;
    if (self::isPermanentlyInactive($userId)) return false;
    if (self::isCapped($userId)) return false;
    return true;
}

/**
 * Add to lifetime earnings. Returns true if this caused the cap to be reached.
 */
public static function addLifetimeEarnings(int $userId, float $amount): bool
{
    $pdo = db();
    $pdo->prepare("
        UPDATE users
        SET lifetime_earned = lifetime_earned + ?
        WHERE id = ?
    ")->execute([$amount, $userId]);

    return self::isCapped($userId);
}

/**
 * Record that cap was reached and start reactivation window.
 */
public static function recordCapReached(int $userId): void
{
    db()->prepare("
        UPDATE users
        SET cap_reached_at = NOW(), status = 'capped'
        WHERE id = ?
    ")->execute([$userId]);
}

/**
 * Reactivate a capped member. Deducts fee from e-wallet or records manual payment.
 * Returns ['ok' => bool, 'error' => string|null].
 */
public static function reactivate(int $userId, string $paymentMethod = 'ewallet'): array
{
    $pdo = db();
    $user = self::find($userId);

    if (!$user) return ['ok' => false, 'error' => 'User not found.'];
    if ((int)$user['permanently_inactive'] === 1) {
        return ['ok' => false, 'error' => 'Account is permanently inactive. Contact support.'];
    }
    if ($user['status'] !== 'capped') {
        return ['ok' => false, 'error' => 'Account is not capped.'];
    }

    $fee = (float)$user['reactivation_fee'];
    $packageId = (int)$user['package_id'];

    $pdo->beginTransaction();

    try {
        // Deduct fee if applicable
        if ($fee > 0) {
            if ($paymentMethod === 'ewallet') {
                $ok = Ewallet::debit($userId, $fee, 0, 'reactivation', 'Reactivation fee');
                if (!$ok) {
                    throw new RuntimeException('Insufficient e-wallet balance. Add funds or contact admin.');
                }
            }
            // Record the payment
            $pdo->prepare("
                INSERT INTO reactivation_payments
                (user_id, package_id, amount, payment_method, processed_by)
                VALUES (?, ?, ?, ?, ?)
            ")->execute([
                $userId, $packageId, $fee,
                $paymentMethod === 'ewallet' ? 'manual' : $paymentMethod,
                Auth::check() ? Auth::id() : null
            ]);
        }

        // Reset cap counter and restore active status
        $pdo->prepare("
            UPDATE users
            SET lifetime_earned = 0,
                cap_reached_at = NULL,
                reactivation_count = reactivation_count + 1,
                last_reactivation_at = NOW(),
                status = 'active'
            WHERE id = ?
        ")->execute([$userId]);

        $pdo->commit();

        return ['ok' => true, 'error' => null];

    } catch (\Exception $e) {
        $pdo->rollBack();
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Check and process expired reactivation windows (run via cron or on page load).
 * Returns count of members newly marked permanently inactive.
 */
public static function processExpiredWindows(): int
{
    $pdo = db();

    // Find capped members whose window has expired
    $st = $pdo->query("
        SELECT u.id, u.cap_reached_at, p.reactivation_window
        FROM users u
        JOIN packages p ON p.id = u.package_id
        WHERE u.status = 'capped'
          AND u.permanently_inactive = 0
          AND p.reactivation_window > 0
          AND u.cap_reached_at IS NOT NULL
          AND DATE_ADD(u.cap_reached_at, INTERVAL p.reactivation_window DAY) < NOW()
    ");

    $expired = $st->fetchAll();
    $count = 0;

    foreach ($expired as $user) {
        $pdo->prepare("
            UPDATE users
            SET permanently_inactive = 1, status = 'permanently_inactive'
            WHERE id = ?
        ")->execute([$user['id']]);
        $count++;
    }

    return $count;
}
```

### 3.2 Add Reactivation Status to User Queries

Update `find()` to include cap-related fields in the JOIN:

```php
// In User::find() — add to SELECT
// u.lifetime_earned, u.cap_reached_at, u.reactivation_count,
// u.permanently_inactive, u.last_reactivation_at,
// p.income_cap, p.reactivation_fee, p.reactivation_window
```

---

## Phase 4: Commission Engine — Cap Enforcement

### 4.1 Modify `Commission.php` — Add Cap Check Wrapper

Add a private helper that all commission methods call before crediting:

```php
/**
 * Central cap check. Returns true if commission can be paid, false if blocked by cap.
 * If cap is reached by this commission, records the event.
 */
private static function canCredit(int $userId, float $amount): bool
{
    if (!User::canEarn($userId)) {
        return false;
    }

    $user = User::find($userId);
    $cap = (float)($user['income_cap'] ?? 0);

    if ($cap <= 0) {
        // No cap configured — proceed normally
        User::addLifetimeEarnings($userId, $amount);
        return true;
    }

    $current = (float)$user['lifetime_earned'];
    $remaining = $cap - $current;

    if ($remaining <= 0) {
        // Already capped — should have been caught by canEarn, but double-check
        if (!$user['cap_reached_at']) {
            User::recordCapReached($userId);
        }
        return false;
    }

    // Partial credit if this commission would exceed cap
    $actualAmount = min($amount, $remaining);

    // Credit the (possibly reduced) amount
    User::addLifetimeEarnings($userId, $actualAmount);

    // Check if cap was reached by this credit
    if ($current + $actualAmount >= $cap) {
        User::recordCapReached($userId);
        // Log that the final commission was prorated
        self::logCapEvent($userId, $amount, $actualAmount, $cap);
    }

    return $actualAmount > 0;
}

/**
 * Log when a commission is reduced or blocked by cap.
 */
private static function logCapEvent(int $userId, float $requested, float $actual, float $cap): void
{
    db()->prepare("
        INSERT INTO commissions
        (user_id, type, amount, description, status, created_at)
        VALUES (?, 'pairing', ?, ?, 'flushed', NOW())
    ")->execute([
        $userId,
        $requested - $actual,
        "Cap reached: requested " . fmt_money($requested) . ", paid " .
        fmt_money($actual) . " of " . fmt_money($cap) . " cap"
    ]);
}
```

### 4.2 Update `creditPairing()` to Use Cap Check

```php
private static function creditPairing(
    int $userId,
    float $amount,
    int $pairs,
    int $sourceId
): void {
    // Check cap before proceeding
    if (!self::canCredit($userId, $amount)) {
        // Record flushed pairs due to cap
        self::recordFlush($userId, $pairs, $sourceId, 'Income cap reached');
        return;
    }

    $user = User::find($userId);
    $actualAmount = min($amount, (float)$user['income_cap'] - (float)$user['lifetime_earned'] + $amount);
    // ... rest of existing logic, but use $actualAmount instead of $amount
}
```

Wait — better approach: Modify `canCredit()` to return the allowed amount, not just boolean:

```php
/**
 * Returns the amount that can actually be credited (0 if blocked by cap).
 */
private static function getCreditableAmount(int $userId, float $requestedAmount): float
{
    if (!User::canEarn($userId)) {
        return 0;
    }

    $user = User::find($userId);
    $cap = (float)($user['income_cap'] ?? 0);

    if ($cap <= 0) {
        User::addLifetimeEarnings($userId, $requestedAmount);
        return $requestedAmount;
    }

    $current = (float)$user['lifetime_earned'];
    $remaining = $cap - $current;

    if ($remaining <= 0) {
        if (!$user['cap_reached_at']) {
            User::recordCapReached($userId);
        }
        return 0;
    }

    $actual = min($requestedAmount, $remaining);
    User::addLifetimeEarnings($userId, $actual);

    if ($current + $actual >= $cap) {
        User::recordCapReached($userId);
    }

    return $actual;
}
```

Then update all credit methods:

```php
// In creditPairing():
$actualAmount = self::getCreditableAmount($userId, $amount);
if ($actualAmount <= 0) {
    self::recordFlush($userId, $pairs, $sourceId, 'Income cap reached');
    return;
}
// Use $actualAmount in the INSERT

// In processDirectReferral():
$bonus = (float)$pkg['direct_ref_bonus'];
$actual = self::getCreditableAmount($sponsorId, $bonus);
if ($actual <= 0) return;
// Use $actual in the INSERT

// In processIndirectReferral():
$bonus = (float)($levels[$lvl] ?? 0);
$actual = self::getCreditableAmount($cur, $bonus);
if ($actual <= 0) continue;
// Use $actual in the INSERT
```

### 4.3 Update `processBinaryPlacement()` — Skip Capped/Inactive Members

In the binary placement engine, when walking up the tree, skip members who are capped or permanently inactive:

```php
// In processBinaryPlacement(), inside the while loop:
$st = $pdo->prepare("
    SELECT u.id, u.left_count, u.right_count,
           u.pairs_paid, u.pairs_flushed, u.pairs_paid_today,
           u.status, u.permanently_inactive,
           u.lifetime_earned, p.income_cap,
           p.pairing_bonus, p.daily_pair_cap
    FROM   users u
    LEFT JOIN packages p ON p.id = u.package_id
    WHERE  u.id = ?
      AND  u.status = 'active'
      AND  u.permanently_inactive = 0
      AND  p.pairing_bonus IS NOT NULL
");

// ... after fetch:
if (!$ancestor) {
    // Member is inactive, capped, or permanently inactive — skip
    // Move to parent without incrementing leg counts or paying bonuses
    // Actually, we still need to increment leg counts for tree structure,
    // but we don't pay bonuses
}
```

**Important distinction:** Leg counts must still update for tree structure integrity, but pairing bonuses are not paid to capped/inactive members. The existing code already checks `u.status = 'active'` — we just need to add `AND u.permanently_inactive = 0` and ensure capped members have `status = 'capped'` not `'active'`.

### 4.4 Update `recordFlush()` to Accept Reason

```php
private static function recordFlush(
    int $userId,
    int $pairs,
    int $sourceId,
    string $reason = 'Daily cap reached'
): void {
    db()->prepare("
        INSERT INTO commissions
          (user_id, type, amount, source_user_id, pairs_count, description, status)
        VALUES (?, 'pairing', 0.00, ?, ?, ?, 'flushed')
    ")->execute([
        $userId,
        $sourceId,
        $pairs,
        "{$pairs} pair(s) flushed — {$reason}"
    ]);
}
```

---

## Phase 5: Member UI — Cap Awareness & Reactivation

### 5.1 Dashboard Cap Widget (`dashboard(1).php`)

Add a new stat card showing cap progress, and a prominent alert when capped:

```php
<?php
// Fetch cap status
$user = Auth::user();
$cap = (float)($user['income_cap'] ?? 0);
$earned = (float)($user['lifetime_earned'] ?? 0);
$isCapped = $user['status'] === 'capped';
$isPermInactive = (int)($user['permanently_inactive'] ?? 0) === 1;
$capPercent = $cap > 0 ? min(100, round(($earned / $cap) * 100)) : 0;
?>

<?php if ($isCapped || $isPermInactive): ?>
  <!-- Capped / Permanently Inactive Alert -->
  <div class="alert alert-danger mb-3">
    <div class="d-flex align-items-center gap-3">
      <div style="font-size:2rem;">🚫</div>
      <div>
        <h5 class="mb-1">
          <?= $isPermInactive ? 'Account Permanently Inactive' : 'Income Cap Reached' ?>
        </h5>
        <p class="mb-2" style="font-size:.85rem;">
          <?php if ($isPermInactive): ?>
            You missed the <?= (int)$user['reactivation_window'] ?>-day reactivation window.
            Contact support for assistance.
          <?php else: ?>
            You've earned <?= fmt_money($earned) ?> of your <?= fmt_money($cap) ?> lifetime cap.
            Reactivate to keep earning.
          <?php endif; ?>
        </p>
        <?php if ($isCapped && !$isPermInactive): ?>
          <a href="<?= APP_URL ?>/?page=reactivate" class="btn btn-warning btn-sm">
            ⚡ Reactivate Now (<?= fmt_money($user['reactivation_fee']) ?>)
          </a>
        <?php endif; ?>
      </div>
    </div>
  </div>
<?php elseif ($cap > 0): ?>
  <!-- Cap Progress Card -->
  <div class="col-12 col-md-6">
    <div class="card">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <div class="card-title">🛡️ Lifetime Cap Progress</div>
          <span class="badge bg-secondary-subtle text-secondary">
            <?= $capPercent ?>%
          </span>
        </div>
        <div class="cap-bar-track mb-2">
          <div class="cap-bar-fill <?= $capPercent >= 90 ? 'full' : '' ?>"
            style="width:<?= $capPercent ?>%"></div>
        </div>
        <div class="d-flex justify-content-between" style="font-size:.78rem;">
          <span>Earned: <strong><?= fmt_money($earned) ?></strong></span>
          <span>Cap: <strong><?= fmt_money($cap) ?></strong></span>
        </div>
        <?php if ($capPercent >= 75): ?>
          <div class="alert alert-warning py-2 mb-0 mt-2" style="font-size:.78rem;">
            ⚠️ Approaching cap — <?= fmt_money($cap - $earned) ?> remaining
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
<?php endif; ?>
```

### 5.2 New Reactivation Page (`reactivate.php`)

Create a dedicated reactivation flow page:

```php
<?php
/**
 * @file views/member/reactivate.php
 * @brief Member reactivation UI
 */
$pageTitle = 'Reactivate Account';
require 'views/partials/head.php';
require 'views/partials/sidebar_member.php';

$user = Auth::user();
$isCapped = $user['status'] === 'capped';
$isPermInactive = (int)$user['permanently_inactive'] === 1;
$fee = (float)($user['reactivation_fee'] ?? 0);
$balance = (float)$user['ewallet_balance'];
$canAfford = $balance >= $fee;
$windowDays = (int)($user['reactivation_window'] ?? 0);

// Process expired windows on page load
if ($isCapped && !$isPermInactive && $windowDays > 0) {
    User::processExpiredWindows();
    // Refresh user data
    $user = Auth::user();
    $isPermInactive = (int)$user['permanently_inactive'] === 1;
}
?>

<div class="main-content">
  <?php require 'views/partials/topbar.php'; ?>
  <div class="page-content">
    <?= render_flash() ?>

    <div class="row justify-content-center">
      <div class="col-12 col-md-8 col-lg-6">
        <div class="card">
          <div class="card-header text-center" style="background:linear-gradient(135deg,#d97706,#f59e0b);">
            <div style="font-size:3rem;margin-bottom:.5rem;">⚡</div>
            <h4 class="text-white mb-1">Account Reactivation</h4>
            <p class="text-white mb-0" style="opacity:.8;font-size:.85rem;">
              Reset your earning potential
            </p>
          </div>

          <div class="card-body">
            <?php if ($isPermInactive): ?>
              <!-- Permanently Inactive State -->
              <div class="text-center py-4">
                <div style="font-size:4rem;margin-bottom:1rem;">🔒</div>
                <h5 class="text-danger mb-2">Permanently Inactive</h5>
                <p class="text-muted mb-3" style="font-size:.9rem;line-height:1.6;">
                  Your <?= $windowDays ?>-day reactivation window has expired.
                  Your account can no longer earn commissions automatically.
                </p>
                <div class="alert alert-info" style="font-size:.85rem;">
                  <strong>Contact Support</strong><br>
                  Email: <?= e(setting('contact_email', 'support@mlm.local')) ?><br>
                  Include your username: <strong>@<?= e($user['username']) ?></strong>
                </div>
              </div>

            <?php elseif (!$isCapped): ?>
              <!-- Not Capped -->
              <div class="text-center py-4">
                <div style="font-size:3rem;margin-bottom:1rem;">✅</div>
                <h5 class="text-success mb-2">Account Active</h5>
                <p class="text-muted">Your account is active and earning. No reactivation needed.</p>
                <a href="<?= APP_URL ?>/?page=dashboard" class="btn btn-primary">Back to Dashboard</a>
              </div>

            <?php else: ?>
              <!-- Reactivation Form -->
              <div class="text-center mb-4">
                <div class="mb-3">
                  <span class="badge bg-warning text-dark" style="font-size:.85rem;padding:.5em 1em;">
                    ⏳ Cap Reached: <?= fmt_money($user['lifetime_earned']) ?> / <?= fmt_money($user['income_cap']) ?>
                  </span>
                </div>
                <?php if ($windowDays > 0): ?>
                  <div class="alert alert-warning py-2" style="font-size:.8rem;">
                    ⚠️ You have <strong><?= $windowDays ?> days</strong> from capping to reactivate
                    before permanent inactivation.
                  </div>
                <?php endif; ?>
              </div>

              <!-- Fee Breakdown -->
              <div class="rounded p-3 mb-4" style="background:#f8fafd;border:1px solid #dde3ef;">
                <div class="d-flex justify-content-between mb-2">
                  <span class="text-muted">Reactivation Fee</span>
                  <span class="fw-bold"><?= fmt_money($fee) ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                  <span class="text-muted">Your Balance</span>
                  <span class="fw-bold <?= $canAfford ? 'text-success' : 'text-danger' ?>">
                    <?= fmt_money($balance) ?>
                  </span>
                </div>
                <hr class="my-2">
                <div class="d-flex justify-content-between fw-bold">
                  <span>After Reactivation</span>
                  <span class="<?= $canAfford ? 'text-success' : 'text-danger' ?>">
                    <?= fmt_money($balance - $fee) ?>
                  </span>
                </div>
              </div>

              <?php if (!$canAfford): ?>
                <div class="alert alert-danger">
                  <strong>Insufficient Balance</strong><br>
                  You need <?= fmt_money($fee - $balance) ?> more in your e-wallet.
                  Earn more commissions or contact your sponsor.
                </div>
              <?php endif; ?>

              <form method="POST" action="<?= APP_URL ?>/?page=do_reactivate">
                <?= csrf_field() ?>

                <div class="mb-3">
                  <label class="form-label">Payment Method</label>
                  <div class="d-flex gap-2">
                    <label class="method-option" style="--mc:#3b6ff0;">
                      <input type="radio" name="payment_method" value="ewallet" checked>
                      <span>💰 E-Wallet</span>
                      <small>Balance: <?= fmt_money($balance) ?></small>
                    </label>
                    <?php if (Auth::isAdmin()): ?>
                      <label class="method-option" style="--mc:#12a05c;">
                        <input type="radio" name="payment_method" value="manual">
                        <span>🔧 Manual (Admin)</span>
                        <small>Waive fee</small>
                      </label>
                    <?php endif; ?>
                  </div>
                </div>

                <div class="alert alert-info mb-3" style="font-size:.8rem;">
                  <strong>What happens when you reactivate:</strong>
                  <ul class="mb-0 mt-1 ps-3">
                    <li>Your lifetime earnings counter resets to zero</li>
                    <li>You can earn pairing bonuses again immediately</li>
                    <li>Your position in the binary tree is preserved</li>
                    <li>Previous downline members remain under you</li>
                  </ul>
                </div>

                <button type="submit" class="btn btn-warning w-100 btn-lg"
                  <?= !$canAfford ? 'disabled' : '' ?>>
                  ⚡ Reactivate for <?= fmt_money($fee) ?>
                </button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require 'views/partials/footer.php'; ?>
```

### 5.3 Add Reactivation to Member Sidebar

In `sidebar_member.php`, add a conditional nav item when capped:

```php
// After the 'payout' nav item:
if ($user['status'] === 'capped' && !(int)$user['permanently_inactive']) {
    $nav[] = ['page' => 'reactivate', 'icon' => '⚡', 'label' => 'Reactivate', 'pages' => ['reactivate']];
}
```

---

## Phase 6: Admin UI Enhancements

### 6.1 Member List Cap Indicators (`users.php`)

Update the status badge logic to show cap states:

```php
<?php
$status = $m['status'];
$isPerm = (int)($m['permanently_inactive'] ?? 0) === 1;

$b = match(true) {
    $isPerm => 'bg-dark text-white',           // Permanently inactive
    $status === 'capped' => 'bg-warning text-dark',  // Capped
    $status === 'active' => 'bg-success-subtle text-success',
    $status === 'suspended' => 'bg-danger-subtle text-danger',
    default => 'bg-secondary-subtle text-secondary'
};

$statusLabel = match(true) {
    $isPerm => 'Perm. Inactive',
    $status === 'capped' => 'Capped',
    default => ucfirst($status)
};
?>
<span class="badge <?= $b ?>"><?= $statusLabel ?></span>
```

Add cap progress column:

```php
<td>
    <?php if ((float)($m['income_cap'] ?? 0) > 0): ?>
        <div class="d-flex align-items-center gap-2">
            <div style="flex:1;min-width:60px;">
                <div class="progress" style="height:6px;">
                    <div class="progress-bar <?= $m['status'] === 'capped' ? 'bg-warning' : 'bg-success' ?>"
                        style="width:<?= min(100, round(((float)$m['lifetime_earned'] / (float)$m['income_cap']) * 100)) ?>%"></div>
                </div>
            </div>
            <span style="font-size:.7rem;" class="font-mono">
                <?= fmt_money($m['lifetime_earned']) ?>/<?= fmt_money($m['income_cap']) ?>
            </span>
        </div>
    <?php else: ?>
        <span class="text-muted" style="font-size:.72rem;">—</span>
    <?php endif; ?>
</td>
```

### 6.2 Member Detail Cap Section (`user_view.php`)

Add a new card showing cap history and reactivation controls:

```php
<!-- Cap & Reactivation Card -->
<div class="col-12">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span class="card-title">🛡️ Income Cap & Reactivation</span>
            <?php if ($user['status'] === 'capped'): ?>
                <span class="badge bg-warning text-dark">Capped</span>
            <?php elseif ((int)$user['permanently_inactive']): ?>
                <span class="badge bg-dark">Permanently Inactive</span>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <table class="info-table">
                <tr>
                    <td>Lifetime Cap</td>
                    <td><?= (float)$user['income_cap'] > 0 ? fmt_money($user['income_cap']) : '<span class="text-muted">Unlimited</span>' ?></td>
                </tr>
                <tr>
                    <td>Earned This Cycle</td>
                    <td><?= fmt_money($user['lifetime_earned']) ?></td>
                </tr>
                <tr>
                    <td>Cap Reached</td>
                    <td>
                        <?php if ($user['cap_reached_at']): ?>
                            <?= fmt_datetime($user['cap_reached_at']) ?>
                            (<?= $user['reactivation_window'] > 0 ? (int)$user['reactivation_window'] . ' day window' : 'No window' ?>)
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td>Reactivation Count</td>
                    <td><?= (int)$user['reactivation_count'] ?></td>
                </tr>
                <tr>
                    <td>Last Reactivation</td>
                    <td><?= $user['last_reactivation_at'] ? fmt_datetime($user['last_reactivation_at']) : '<span class="text-muted">—</span>' ?></td>
                </tr>
                <tr>
                    <td>Reactivation Fee</td>
                    <td><?= fmt_money($user['reactivation_fee']) ?></td>
                </tr>
            </table>

            <?php if ($user['status'] === 'capped' && !(int)$user['permanently_inactive']): ?>
                <hr class="my-3">
                <div class="d-flex gap-2">
                    <form method="POST" action="<?= APP_URL ?>/?page=admin_reactivate_user" class="m-0">
                        <?= csrf_field() ?>
                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                        <button type="button" class="btn btn-warning"
                            onclick="showConfirm({
                                title: 'Force Reactivate Member',
                                message: 'Reactivate @<?= e($user['username']) ?> and reset their cap counter?<br>Fee: <?= fmt_money($user['reactivation_fee']) ?>',
                                confirmText: 'Reactivate',
                                confirmClass: 'btn-warning',
                                onConfirm: () => this.closest('form').submit()
                            })">
                            ⚡ Force Reactivate
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
```

### 6.3 Admin Dashboard Cap Stats

Add to admin dashboard stats row:

```php
// In AdminController::dashboard():
$cappedCount = (int)db()->query("
    SELECT COUNT(*) FROM users
    WHERE status = 'capped' AND permanently_inactive = 0
")->fetchColumn();

$permInactiveCount = (int)db()->query("
    SELECT COUNT(*) FROM users WHERE permanently_inactive = 1
")->fetchColumn();

// Add to stats array in dashboard.php:
['Capped Members', number_format($cappedCount), 'warning', '⚡',
 '<a href="' . APP_URL . '/?page=admin_users&status=capped" class="text-decoration-none">View →</a>'],
['Perm. Inactive', number_format($permInactiveCount), 'dark', '🔒',
 'Missed reactivation window'],
```

---

## Phase 7: Routing & Controllers

### 7.1 Add Routes to `index.php`

```php
// Member routes
'reactivate'         => ['MemberController', 'reactivate',        'member'],
'do_reactivate'      => ['MemberController', 'doReactivate',      'member'],

// Admin routes
'admin_reactivate_user' => ['AdminController', 'forceReactivate', 'admin'],
```

### 7.2 Add Methods to `MemberController.php`

```php
public function reactivate(): void
{
    Auth::guard('member');
    // Process expired windows before showing page
    User::processExpiredWindows();
    require 'views/member/reactivate.php';
}

public function doReactivate(): void
{
    Auth::guard('member');
    csrf_verify();

    $paymentMethod = $_POST['payment_method'] ?? 'ewallet';
    $result = User::reactivate(Auth::id(), $paymentMethod);

    if ($result['ok']) {
        flash('success', 'Account reactivated successfully! Your earning cap has been reset.');
    } else {
        flash('error', $result['error']);
    }

    redirect('/?page=reactivate');
}
```

### 7.3 Add Method to `AdminController.php`

```php
public function forceReactivate(): void
{
    Auth::guard('admin');
    csrf_verify();

    $userId = (int)($_POST['user_id'] ?? 0);
    $user = User::find($userId);

    if (!$user || $user['role'] === 'admin') {
        flash('error', 'Invalid user.');
        redirect('/?page=admin_users');
    }

    // Admin can reactivate even if window expired, but warn
    $result = User::reactivate($userId, 'manual');

    if ($result['ok']) {
        flash('success', "@{$user['username']} has been reactivated by admin.");
    } else {
        flash('error', $result['error']);
    }

    redirect('/?page=admin_user_view&id=' . $userId);
}
```

---

## Phase 8: Cron & Background Processing

### 8.1 Midnight Cron Extension

Add to the existing midnight reset cron (`midnight_reset.php` or similar):

```php
<?php
/**
 * Midnight cron — extended with cap window processing
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../models/User.php';

// 1. Reset daily pair counters (existing)
$affected = db()->exec("UPDATE users SET pairs_paid_today = 0 WHERE role = 'member'");
db()->prepare("UPDATE settings SET value = ? WHERE key_name = 'last_reset'")
    ->execute([date('Y-m-d H:i:s')]);

// 2. Process expired reactivation windows (NEW)
$expiredCount = User::processExpiredWindows();

// 3. Log results
error_log(sprintf(
    "[MLM Cron] %s | Reset pairs: %d members | Expired caps: %d members",
    date('Y-m-d H:i:s'),
    $affected,
    $expiredCount
));
```

### 8.2 Settings UI — Cron Status

Add to admin settings page:

```html
<div class="rounded p-3 mb-3" style="background:#f4f6fb;">
  <div
    class="text-muted mb-1"
    style="font-size:.68rem;font-weight:700;letter-spacing:.5px;text-transform:uppercase;"
  >
    Cap Window Processing
  </div>
  <div class="fw-600 font-mono" style="font-size:.875rem;">
    <?= User::processExpiredWindows() ?> members marked permanently inactive on
    last run
  </div>
</div>
```

---

## Phase 9: Backward Compatibility & Migration

### 9.1 Default Values Ensure Safety

All new columns have safe defaults:

- `income_cap = 0` → No capping (existing behavior preserved)
- `reactivation_fee = 0` → Free reactivation
- `reactivation_window = 0` → No automatic permanent inactivation

### 9.2 Migration Script

For existing installations, provide a migration:

```php
<?php
/**
 * migrate_cap.php — One-time migration for capping feature
 * Run: php migrate_cap.php
 */
require_once __DIR__ . '/config/db.php';

try {
    $pdo = db();

    // Add columns if not exist (idempotent)
    $pdo->exec("
        ALTER TABLE packages
        ADD COLUMN IF NOT EXISTS income_cap DECIMAL(14,2) NOT NULL DEFAULT 0.00,
        ADD COLUMN IF NOT EXISTS reactivation_fee DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        ADD COLUMN IF NOT EXISTS reactivation_window TINYINT UNSIGNED NOT NULL DEFAULT 0
    ");

    $pdo->exec("
        ALTER TABLE users
        ADD COLUMN IF NOT EXISTS lifetime_earned DECIMAL(14,2) NOT NULL DEFAULT 0.00,
        ADD COLUMN IF NOT EXISTS cap_reached_at TIMESTAMP NULL DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS reactivation_count INT UNSIGNED NOT NULL DEFAULT 0,
        ADD COLUMN IF NOT EXISTS permanently_inactive TINYINT(1) NOT NULL DEFAULT 0,
        ADD COLUMN IF NOT EXISTS last_reactivation_at TIMESTAMP NULL DEFAULT NULL
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS reactivation_payments (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          user_id INT UNSIGNED NOT NULL,
          package_id INT UNSIGNED NOT NULL,
          amount DECIMAL(12,2) NOT NULL,
          payment_method ENUM('gcash','maya','usdt','manual') NOT NULL DEFAULT 'manual',
          processed_by INT UNSIGNED NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
          FOREIGN KEY (package_id) REFERENCES packages(id),
          FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB
    ");

    echo "✅ Migration completed successfully.\n";

} catch (\Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
}
```

---

## Phase 10: Testing Checklist

| Test Case                                     | Expected Result                                                      |
| --------------------------------------------- | -------------------------------------------------------------------- |
| Member with no cap configured                 | Earns normally, no UI changes                                        |
| Member earns and approaches 75% of cap        | Warning shown on dashboard                                           |
| Member earns exactly at cap                   | Status changes to `capped`, last commission prorated                 |
| Capped member tries to earn more              | Commission blocked, flush recorded                                   |
| Capped member's ancestors place new members   | Leg counts update, but no pairing bonus paid to capped member        |
| Capped member within window clicks reactivate | Fee deducted, `lifetime_earned` reset to 0, status `active`          |
| Capped member with insufficient balance       | Reactivate button disabled, helpful message shown                    |
| Member misses reactivation window             | Cron marks `permanently_inactive = 1`, status `permanently_inactive` |
| Permanently inactive member's page            | Shows lock icon, support contact info, no reactivate button          |
| Admin force reactivates expired member        | Works despite window, logged as manual, counter reset                |
| Package editor saves cap = 0                  | Existing behavior, no cap enforcement                                |
| Package editor sets cap = 3× entry            | Simulator matches v5 reference behavior                              |

---

## Summary of Files Modified/Created

| File                                | Action                                               | Phase |
| ----------------------------------- | ---------------------------------------------------- | ----- |
| `install.sql`                       | Add new columns and table                            | 1     |
| `models/Package.php`                | Add cap/reactivation fields to CRUD                  | 2     |
| `views/admin/packages.php`          | Add cap section to form                              | 2     |
| `models/User.php`                   | Add cap tracking & reactivation methods              | 3     |
| `core/Commission.php`               | Add `getCreditableAmount()`, update all credit paths | 4     |
| `views/member/dashboard(1).php`     | Add cap widget & alerts                              | 5     |
| `views/member/reactivate.php`       | **New** — Reactivation flow UI                       | 5     |
| `views/partials/sidebar_member.php` | Conditional reactivate nav item                      | 5     |
| `views/admin/users.php`             | Cap progress bars & status badges                    | 6     |
| `views/admin/user_view.php`         | Cap details & force reactivate button                | 6     |
| `views/admin/dashboard.php`         | Capped/perm-inactive stats                           | 6     |
| `index.php`                         | New routes                                           | 7     |
| `controllers/MemberController.php`  | `reactivate()`, `doReactivate()`                     | 7     |
| `controllers/AdminController.php`   | `forceReactivate()`                                  | 7     |
| `cron/midnight_reset.php`           | Add `processExpiredWindows()` call                   | 8     |
| `views/admin/settings.php`          | Cron status display                                  | 8     |
| `migrate_cap.php`                   | **New** — One-time migration script                  | 9     |

This implementation is fully self-contained per phase, builds sequentially, and maintains backward compatibility with existing installations through safe defaults and explicit opt-in via package configuration.
