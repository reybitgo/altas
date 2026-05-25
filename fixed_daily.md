I'll analyze the simulator's Daily Fixed Income mechanics and extend the previous implementation plan. Let me first examine the simulator's DFI parameters in detail.

Based on my analysis of `mlm_binary_simulator_v5 (2).html`, the Daily Fixed Income (DFI) has these key mechanics:

| Parameter          | Simulator Default | Description                                          |
| ------------------ | ----------------- | ---------------------------------------------------- |
| Daily Fixed Income | ₱100/day          | Fixed amount paid to each active member per day      |
| Max Income Days    | 90 days           | Maximum days of DFI per member                       |
| Cap Subject        | Yes               | DFI counts toward the 3× entry fee lifetime cap      |
| Inactive Pause     | Yes               | Clock pauses while inactive; resumes on reactivation |
| Cap Reset          | Full reset        | DFI duration counter resets to 0 on reactivation     |

The DFI is **independent of binary pairing** — it's a guaranteed daily payout for active members, but still subject to the lifetime cap. When the cap is reached, DFI stops permanently for that cycle.

---

# Extended Implementation Plan: Daily Fixed Income + Lifetime Cap & Reactivation

This builds directly on **Phase 1–10** from the previous plan. Each phase below is self-contained and references the established cap/reactivation foundation.

---

## Phase 11: Database Schema — Daily Fixed Income

### 11.1 Alter `packages` Table

Add DFI configuration columns:

```sql
ALTER TABLE packages
  ADD COLUMN daily_fixed_income DECIMAL(12,2) NOT NULL DEFAULT 0.00
    COMMENT 'Fixed amount paid daily to active members (0 = disabled)',
  ADD COLUMN daily_fixed_days TINYINT UNSIGNED NOT NULL DEFAULT 0
    COMMENT 'Maximum days of fixed income per member (0 = unlimited)';
```

**Rationale:**

- `daily_fixed_income = 0` disables DFI entirely (backward compatible)
- `daily_fixed_days = 0` means no duration limit (pays indefinitely while active and under cap)
- Separate from `daily_pair_cap` — DFI is not a pairing mechanism

### 11.2 Alter `users` Table

Add DFI tracking per member:

```sql
ALTER TABLE users
  ADD COLUMN dfi_days_used TINYINT UNSIGNED NOT NULL DEFAULT 0
    COMMENT 'Days of fixed income consumed this cycle',
  ADD COLUMN dfi_last_paid_at DATE NULL DEFAULT NULL
    COMMENT 'Last date fixed income was paid (prevents double-pay on same day)',
  ADD COLUMN dfi_total_earned DECIMAL(14,2) NOT NULL DEFAULT 0.00
    COMMENT 'Cumulative fixed income earned this cycle';
```

**Rationale:**

- `dfi_days_used` counts down from the package limit (resets on reactivation)
- `dfi_last_paid_at` prevents duplicate payments if cron runs twice or member refreshes
- `dfi_total_earned` tracks for display and cap calculations (though cap uses `lifetime_earned` which aggregates all types)

### 11.3 New Table: `daily_fixed_income_log`

Audit trail for transparency:

```sql
CREATE TABLE daily_fixed_income_log (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  amount      DECIMAL(12,2) NOT NULL,
  day_number  TINYINT UNSIGNED NOT NULL COMMENT 'Which DFI day this was (1-based)',
  cap_blocked TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = blocked by lifetime cap',
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user_date (user_id, created_at)
) ENGINE=InnoDB;
```

---

## Phase 12: Package Model — DFI Settings

### 12.1 Update `Package.php`

Add DFI fields to `$fields` in `save()`:

```php
$fields = [
    'name'                => $data['name'],
    'entry_fee'           => $data['entry_fee'],
    'pairing_bonus'       => $data['pairing_bonus'],
    'daily_pair_cap'      => $data['daily_pair_cap'],
    'direct_ref_bonus'    => $data['direct_ref_bonus'],
    'status'              => $data['status'] ?? 'active',
    'income_cap'          => $data['income_cap'] ?? 0,
    'reactivation_fee'    => $data['reactivation_fee'] ?? 0,
    'reactivation_window' => $data['reactivation_window'] ?? 0,
    'daily_fixed_income'  => $data['daily_fixed_income'] ?? 0,
    'daily_fixed_days'    => $data['daily_fixed_days'] ?? 0,
];
```

Add helper:

```php
public static function hasDailyFixedIncome(int $packageId): bool
{
    $pkg = self::find($packageId);
    return $pkg && (float)$pkg['daily_fixed_income'] > 0;
}
```

### 12.2 Update Package Admin Form (`packages.php`)

Add DFI section after the cap/reactivation section:

```html
<!-- Daily Fixed Income Section -->
<div
  class="mb-3"
  style="border:1px solid var(--bs-border-color);border-radius:.6rem;padding:1rem;background:#fdf8ff;"
>
  <label class="form-label fw-bold" style="color:#a855f7;">
    📅 Daily Fixed Income (DFI)
  </label>
  <div class="form-text mb-3">
    Guaranteed daily payout for active members, independent of binary pairing.
    Still subject to the lifetime income cap. Set to 0 to disable.
  </div>

  <div class="row g-3">
    <div class="col-md-6">
      <label class="form-label">Daily Fixed Income (₱/day)</label>
      <input
        type="number"
        name="daily_fixed_income"
        class="form-control"
        inputmode="decimal"
        min="0"
        step="0.01"
        value="<?= e($editPkg['daily_fixed_income'] ?? 0) ?>"
        placeholder="0 = disabled"
      />
      <div class="form-text">Paid every day the member is active</div>
    </div>
    <div class="col-md-6">
      <label class="form-label">Max DFI Days</label>
      <input
        type="number"
        name="daily_fixed_days"
        class="form-control"
        inputmode="numeric"
        min="0"
        max="730"
        value="<?= e($editPkg['daily_fixed_days'] ?? 0) ?>"
        placeholder="0 = unlimited"
      />
      <div class="form-text">Maximum days per reactivation cycle</div>
    </div>
  </div>

  <!-- Live projection -->
  <div
    id="dfiProjection"
    class="mt-2 form-text"
    style="color:#a855f7;font-weight:500;"
  >
    <?php $dfi = (float)($editPkg['daily_fixed_income'] ?? 0); $dfiDays =
    (int)($editPkg['daily_fixed_days'] ?? 0); $cap =
    (float)($editPkg['income_cap'] ?? 0); if ($dfi > 0 && $dfiDays > 0) {
    $maxDfi = $dfi * $dfiDays; echo "Max DFI potential: " . fmt_money($maxDfi) .
    " over {$dfiDays} days"; if ($cap > 0) { echo " · Cap-limited to " .
    fmt_money(min($maxDfi, $cap)); } } ?>
  </div>
</div>
```

---

## Phase 13: DFI Engine — Daily Payout Processing

### 13.1 Create `DailyFixedIncome.php` Model

```php
<?php

/**
 * @file models/DailyFixedIncome.php
 * @brief Daily Fixed Income payout engine
 */
class DailyFixedIncome
{
    /**
     * Process DFI payouts for all eligible members.
     * Called by midnight cron. Returns count of payouts processed.
     */
    public static function processDailyPayouts(): int
    {
        $today = date('Y-m-d');
        $pdo = db();

        // Find all active members with DFI-enabled packages who haven't been paid today
        $st = $pdo->prepare("
            SELECT u.id, u.package_id, u.dfi_days_used, u.lifetime_earned,
                   u.cap_reached_at, u.status, u.permanently_inactive,
                   p.daily_fixed_income, p.daily_fixed_days, p.income_cap
            FROM   users u
            JOIN   packages p ON p.id = u.package_id
            WHERE  u.role = 'member'
              AND  u.status = 'active'
              AND  u.permanently_inactive = 0
              AND  p.daily_fixed_income > 0
              AND  (u.dfi_last_paid_at IS NULL OR u.dfi_last_paid_at < ?)
        ");
        $st->execute([$today]);
        $members = $st->fetchAll();

        $processed = 0;

        foreach ($members as $m) {
            $result = self::payoutMember($m, $today);
            if ($result['paid']) $processed++;
        }

        return $processed;
    }

    /**
     * Attempt DFI payout for a single member. Returns ['paid' => bool, 'reason' => string].
     */
    public static function payoutMember(array $member, string $date): array
    {
        $pdo = db();
        $userId = (int)$member['id'];
        $dfiAmount = (float)$member['daily_fixed_income'];
        $maxDays = (int)$member['daily_fixed_days'];
        $daysUsed = (int)$member['dfi_days_used'];
        $cap = (float)$member['income_cap'];
        $lifetimeEarned = (float)$member['lifetime_earned'];

        // Check duration limit
        if ($maxDays > 0 && $daysUsed >= $maxDays) {
            return ['paid' => false, 'reason' => 'DFI duration exhausted'];
        }

        // Check lifetime cap (using Commission's centralized cap logic)
        $creditable = Commission::getCreditableAmount($userId, $dfiAmount);

        if ($creditable <= 0) {
            // Cap reached — log blocked DFI
            $pdo->prepare("
                INSERT INTO daily_fixed_income_log
                (user_id, amount, day_number, cap_blocked, created_at)
                VALUES (?, ?, ?, 1, NOW())
            ")->execute([
                $userId,
                $dfiAmount,  // Log the full intended amount for transparency
                $daysUsed + 1
            ]);

            return ['paid' => false, 'reason' => 'Lifetime cap reached'];
        }

        // Credit the DFI
        $pdo->beginTransaction();

        try {
            // Record commission
            $pdo->prepare("
                INSERT INTO commissions
                  (user_id, type, amount, description, status)
                VALUES (?, 'daily_fixed', ?, ?, 'credited')
            ")->execute([
                $userId,
                $creditable,
                "Daily Fixed Income — Day " . ($daysUsed + 1) .
                ($creditable < $dfiAmount ? " (prorated by cap)" : "")
            ]);

            $commId = (int)$pdo->lastInsertId();

            // Credit e-wallet
            Ewallet::credit($userId, $creditable, $commId, 'commission', 'Daily Fixed Income');

            // Update user DFI counters
            $pdo->prepare("
                UPDATE users
                SET dfi_days_used = dfi_days_used + 1,
                    dfi_last_paid_at = ?,
                    dfi_total_earned = dfi_total_earned + ?
                WHERE id = ?
            ")->execute([$date, $creditable, $userId]);

            // Log DFI payment
            $pdo->prepare("
                INSERT INTO daily_fixed_income_log
                (user_id, amount, day_number, cap_blocked, created_at)
                VALUES (?, ?, ?, 0, NOW())
            ")->execute([
                $userId,
                $creditable,
                $daysUsed + 1
            ]);

            $pdo->commit();
            return ['paid' => true, 'reason' => 'Paid'];

        } catch (\Exception $e) {
            $pdo->rollBack();
            return ['paid' => false, 'reason' => 'Error: ' . $e->getMessage()];
        }
    }

    /**
     * Get DFI history for a member.
     */
    public static function history(int $userId, int $page = 1, int $perPage = 30): array
    {
        return paginate(
            "SELECT * FROM daily_fixed_income_log
             WHERE user_id = ?
             ORDER BY created_at DESC",
            [$userId],
            $page,
            $perPage
        );
    }

    /**
     * Get DFI summary stats for a member.
     */
    public static function summary(int $userId): array
    {
        $st = db()->prepare("
            SELECT
              COALESCE(SUM(CASE WHEN cap_blocked = 0 THEN amount END), 0) AS total_paid,
              COALESCE(SUM(CASE WHEN cap_blocked = 1 THEN amount END), 0) AS total_blocked,
              COUNT(CASE WHEN cap_blocked = 0 THEN 1 END) AS days_paid,
              COUNT(CASE WHEN cap_blocked = 1 THEN 1 END) AS days_blocked,
              MAX(day_number) AS highest_day
            FROM daily_fixed_income_log
            WHERE user_id = ?
        ");
        $st->execute([$userId]);
        return $st->fetch();
    }
}
```

### 13.2 Update `Commission.php` — Add `daily_fixed` Type Support

Update the `summary()` method to include DFI:

```php
public static function summary(int $userId): array
{
    $st = db()->prepare("
        SELECT
          COALESCE(SUM(CASE WHEN type='pairing'           AND status='credited' THEN amount END), 0) AS total_pairing,
          COALESCE(SUM(CASE WHEN type='direct_referral'   AND status='credited' THEN amount END), 0) AS total_direct,
          COALESCE(SUM(CASE WHEN type='indirect_referral' AND status='credited' THEN amount END), 0) AS total_indirect,
          COALESCE(SUM(CASE WHEN type='daily_fixed'       AND status='credited' THEN amount END), 0) AS total_dfi,
          COALESCE(SUM(CASE WHEN status='credited'                              THEN amount END), 0) AS total_earned,
          COALESCE(SUM(CASE WHEN type='pairing' AND status='flushed' THEN pairs_count END), 0) AS total_flushed_pairs
        FROM commissions
        WHERE user_id = ?
    ");
    $st->execute([$userId]);
    return $st->fetch();
}
```

Update `history()` to include `daily_fixed` in the type filter:

```php
if ($type && in_array($type, ['pairing', 'direct_referral', 'indirect_referral', 'daily_fixed'])) {
    $where  .= ' AND c.type = ?';
    $params[] = $type;
}
```

### 13.3 Update `User.php` — Reset DFI on Reactivation

In `reactivate()`, add DFI counter reset:

```php
$pdo->prepare("
    UPDATE users
    SET lifetime_earned = 0,
        cap_reached_at = NULL,
        reactivation_count = reactivation_count + 1,
        last_reactivation_at = NOW(),
        status = 'active',
        dfi_days_used = 0,           // RESET DFI
        dfi_total_earned = 0,        // RESET DFI total
        dfi_last_paid_at = NULL      // Allow immediate DFI payment
    WHERE id = ?
")->execute([$userId]);
```

---

## Phase 14: Member UI — DFI Monitoring

### 14.1 Dashboard DFI Widget (`dashboard(1).php`)

Add DFI to the KPI cards and a dedicated DFI progress section:

```php
<?php
// Fetch DFI data
$dfiSummary = DailyFixedIncome::summary($user['id']);
$dfiEnabled = (float)($user['daily_fixed_income'] ?? 0) > 0;
$dfiMaxDays = (int)($user['daily_fixed_days'] ?? 0);
$dfiDaysUsed = (int)($user['dfi_days_used'] ?? 0);
$dfiRemaining = $dfiMaxDays > 0 ? max(0, $dfiMaxDays - $dfiDaysUsed) : '∞';
$dfiPercent = $dfiMaxDays > 0 ? min(100, round(($dfiDaysUsed / $dfiMaxDays) * 100)) : 0;
?>

<!-- Add to KPI cards array -->
<?php $cards = [
    [$user['ewallet_balance'], 'E-Wallet Balance',   '💰', 'primary', 'primary', 'Withdraw →', '/?page=payout'],
    [$summary['total_pairing'],  'Pairing Earnings', '🤝', 'success', 'success', number_format($user['pairs_paid']) . ' pairs lifetime', null],
    [$summary['total_direct'],   'Direct Referral',  '👥', 'orange',  'warning', null, '/?page=genealogy&view=referral'],
    [$summary['total_indirect'], 'Indirect Referral', '🔗', 'purple',  'purple',  'Up to 10 levels', null],
    [$summary['total_dfi'],      'Fixed Daily Income', '📅', 'pink',    'danger',  // Using pink/danger for DFI
      $dfiEnabled ? "{$dfiDaysUsed}/{$dfiMaxDays} days" : 'Not enabled', null],
];
// ... rest of existing card loop
?>

<?php if ($dfiEnabled): ?>
<!-- DFI Progress Card -->
<div class="col-12 col-md-6">
    <div class="card" style="border-color:rgba(244,114,182,.3);">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="card-title" style="color:#ec4899;">📅 Daily Fixed Income</div>
                <span class="badge" style="background:rgba(244,114,182,.15);color:#ec4899;">
                    <?= $dfiDaysUsed ?>/<?= $dfiMaxDays ?> days
                </span>
            </div>

            <!-- DFI progress bar -->
            <div class="cap-bar-track mb-2">
                <div class="cap-bar-fill"
                    style="width:<?= $dfiPercent ?>%;background:linear-gradient(90deg,#ec4899,#f472b6);">
                </div>
            </div>

            <div class="d-flex justify-content-between mb-2" style="font-size:.78rem;">
                <span class="text-muted">Earned: <strong><?= fmt_money($summary['total_dfi']) ?></strong></span>
                <span class="text-muted">Rate: <strong><?= fmt_money($user['daily_fixed_income']) ?>/day</strong></span>
            </div>

            <!-- DFI status indicators -->
            <div class="d-flex gap-2 mt-2">
                <?php if ($dfiRemaining === 0): ?>
                    <span class="badge bg-danger-subtle text-danger">Duration exhausted</span>
                <?php elseif ((float)$user['lifetime_earned'] >= (float)$user['income_cap']): ?>
                    <span class="badge bg-warning-subtle text-warning">Blocked by cap</span>
                <?php else: ?>
                    <span class="badge bg-success-subtle text-success">
                        <?= $dfiRemaining ?> days remaining
                    </span>
                    <span class="badge bg-info-subtle text-info">
                        Next: <?= fmt_money(min(
                            (float)$user['daily_fixed_income'],
                            (float)$user['income_cap'] - (float)$user['lifetime_earned']
                        )) ?>
                    </span>
                <?php endif; ?>
            </div>

            <?php if ($dfiPercent >= 75 && $dfiRemaining !== 0): ?>
                <div class="alert alert-warning py-2 mb-0 mt-2" style="font-size:.78rem;">
                    ⚠️ DFI duration nearly exhausted — reactivate to reset
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>
```

### 14.2 New DFI Detail Page (`daily_fixed.php`)

```php
<?php
/**
 * @file views/member/daily_fixed.php
 * @brief Daily Fixed Income detail & history
 */
$pageTitle = 'Daily Fixed Income';
require 'views/partials/head.php';
require 'views/partials/sidebar_member.php';

$user = Auth::user();
$dfiEnabled = (float)($user['daily_fixed_income'] ?? 0) > 0;
$summary = DailyFixedIncome::summary($user['id']);
$history = DailyFixedIncome::history($user['id'], max(1, (int)($_GET['pg'] ?? 1)), 30);

$maxDays = (int)($user['daily_fixed_days'] ?? 0);
$daysUsed = (int)($user['dfi_days_used'] ?? 0);
$daysRemaining = $maxDays > 0 ? max(0, $maxDays - $daysUsed) : '∞';
$percentUsed = $maxDays > 0 ? min(100, round(($daysUsed / $maxDays) * 100)) : 0;

// Projections
$dailyRate = (float)($user['daily_fixed_income'] ?? 0);
$cap = (float)($user['income_cap'] ?? 0);
$lifetimeEarned = (float)($user['lifetime_earned'] ?? 0);
$capRemaining = $cap > 0 ? max(0, $cap - $lifetimeEarned) : '∞';
$projectedDaysByCap = is_numeric($capRemaining) && $dailyRate > 0
    ? floor($capRemaining / $dailyRate)
    : '∞';
$projectedDaysByDuration = is_numeric($daysRemaining) ? $daysRemaining : '∞';
$limitingFactor = is_numeric($projectedDaysByCap) && is_numeric($projectedDaysByDuration)
    ? min($projectedDaysByCap, $projectedDaysByDuration)
    : (is_numeric($projectedDaysByDuration) ? $projectedDaysByDuration : $projectedDaysByCap);
?>

<div class="main-content">
    <?php require 'views/partials/topbar.php'; ?>
    <div class="page-content">
        <?= render_flash() ?>

        <?php if (!$dfiEnabled): ?>
            <!-- DFI Not Enabled -->
            <div class="text-center py-5">
                <div style="font-size:3rem;margin-bottom:1rem;">📅</div>
                <h5 class="text-muted">Daily Fixed Income Not Enabled</h5>
                <p class="text-muted" style="font-size:.85rem;">
                    Your current package does not include fixed daily income.
                    Contact your sponsor or admin to upgrade.
                </p>
            </div>
        <?php else: ?>

            <!-- Header Stats -->
            <div class="row g-3 mb-3">
                <div class="col-6 col-xl-3">
                    <div class="card stat-card" style="border-top:3px solid #ec4899;">
                        <div class="card-body pt-4">
                            <div class="stat-label" style="color:#ec4899;">Daily Rate</div>
                            <div class="stat-value" style="color:#ec4899;"><?= fmt_money($dailyRate) ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-xl-3">
                    <div class="card stat-card" style="border-top:3px solid #ec4899;">
                        <div class="card-body pt-4">
                            <div class="stat-label" style="color:#ec4899;">Days Used</div>
                            <div class="stat-value" style="color:#ec4899;"><?= $daysUsed ?>/<?= $maxDays ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-xl-3">
                    <div class="card stat-card" style="border-top:3px solid #ec4899;">
                        <div class="card-body pt-4">
                            <div class="stat-label" style="color:#ec4899;">Total DFI Earned</div>
                            <div class="stat-value" style="color:#ec4899;"><?= fmt_money($summary['total_paid']) ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-xl-3">
                    <div class="card stat-card" style="border-top:3px solid #ec4899;">
                        <div class="card-body pt-4">
                            <div class="stat-label" style="color:#ec4899;">Cap Blocked</div>
                            <div class="stat-value" style="color:#ec4899;"><?= fmt_money($summary['total_blocked']) ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Visual Timeline -->
            <div class="card mb-3">
                <div class="card-header"><span class="card-title">📊 DFI Timeline</span></div>
                <div class="card-body">
                    <!-- Duration progress -->
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span style="font-size:.8rem;font-weight:600;">Duration Usage</span>
                            <span style="font-size:.8rem;"><?= $daysUsed ?> of <?= $maxDays ?> days</span>
                        </div>
                        <div class="progress" style="height:12px;">
                            <div class="progress-bar" role="progressbar"
                                style="width:<?= $percentUsed ?>%;background:linear-gradient(90deg,#ec4899,#f472b6);"
                                aria-valuenow="<?= $percentUsed ?>" aria-valuemin="0" aria-valuemax="100">
                            </div>
                        </div>
                    </div>

                    <!-- Cap impact -->
                    <?php if ($cap > 0): ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span style="font-size:.8rem;font-weight:600;">Lifetime Cap Impact</span>
                            <span style="font-size:.8rem;">
                                <?= fmt_money($lifetimeEarned) ?> of <?= fmt_money($cap) ?>
                            </span>
                        </div>
                        <div class="progress" style="height:12px;">
                            <div class="progress-bar bg-warning" role="progressbar"
                                style="width:<?= min(100, round(($lifetimeEarned / $cap) * 100)) ?>%;"
                                aria-valuenow="<?= round(($lifetimeEarned / $cap) * 100) ?>"
                                aria-valuemin="0" aria-valuemax="100">
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Projection -->
                    <div class="rounded p-3" style="background:#fdf2f8;border:1px solid #fbcfe8;">
                        <div class="row text-center">
                            <div class="col-4">
                                <div style="font-size:1.5rem;font-weight:800;color:#ec4899;">
                                    <?= is_numeric($limitingFactor) ? $limitingFactor : '∞' ?>
                                </div>
                                <div style="font-size:.72rem;color:#9d174d;">Projected Days Left</div>
                            </div>
                            <div class="col-4">
                                <div style="font-size:1.5rem;font-weight:800;color:#ec4899;">
                                    <?= fmt_money($dailyRate * (is_numeric($limitingFactor) ? $limitingFactor : 30)) ?>
                                </div>
                                <div style="font-size:.72rem;color:#9d174d;">Max Future DFI</div>
                            </div>
                            <div class="col-4">
                                <div style="font-size:1.5rem;font-weight:800;color:#ec4899;">
                                    <?= is_numeric($projectedDaysByCap) && is_numeric($projectedDaysByDuration)
                                        ? ($projectedDaysByCap < $projectedDaysByDuration ? 'Cap' : 'Duration')
                                        : '—' ?>
                                </div>
                                <div style="font-size:.72rem;color:#9d174d;">Limiting Factor</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payment History -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span class="card-title">📋 DFI Payment History</span>
                    <span class="badge bg-secondary-subtle text-secondary">
                        <?= $history['total'] ?> entries
                    </span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Day #</th>
                                <th>Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($history['data'])): ?>
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">
                                        No DFI payments yet. Check back tomorrow!
                                    </td>
                                </tr>
                            <?php else: foreach ($history['data'] as $row): ?>
                                <tr>
                                    <td class="td-muted" style="font-size:.75rem;">
                                        <?= fmt_datetime($row['created_at']) ?>
                                    </td>
                                    <td class="font-mono fw-bold" style="color:#ec4899;">
                                        Day <?= $row['day_number'] ?>
                                    </td>
                                    <td class="font-mono">
                                        <?php if ($row['cap_blocked']): ?>
                                            <span class="text-muted text-decoration-line-through">
                                                <?= fmt_money($row['amount']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="td-green">+<?= fmt_money($row['amount']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($row['cap_blocked']): ?>
                                            <span class="badge bg-warning-subtle text-warning">
                                                Blocked by Cap
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-success-subtle text-success">
                                                Paid
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($history['total_pages'] > 1): ?>
                    <div class="card-footer">
                        <?= pagination_links($history, APP_URL . '/?page=daily_fixed') ?>
                    </div>
                <?php endif; ?>
            </div>

        <?php endif; ?>
    </div>
</div>

<?php require 'views/partials/footer.php'; ?>
```

### 14.3 Add DFI to Member Sidebar

In `sidebar_member.php`, add after earnings:

```php
// After 'earnings' nav item:
if ((float)($user['daily_fixed_income'] ?? 0) > 0) {
    $nav[] = ['page' => 'daily_fixed', 'icon' => '📅', 'label' => 'Fixed Income', 'pages' => ['daily_fixed']];
}
```

### 14.4 Add DFI to Earnings Page Filter

In `earnings.php`, update the filter pills:

```php
<?php foreach ([
    '' => 'All',
    'pairing' => '🤝 Pairing',
    'direct_referral' => '👥 Direct',
    'indirect_referral' => '🔗 Indirect',
    'daily_fixed' => '📅 Fixed Daily'
] as $val => $label): ?>
```

---

## Phase 15: Admin UI — DFI Oversight

### 15.1 Admin Dashboard DFI Stats

In `AdminController::dashboard()`:

```php
$totalDfiPaid = (float)db()->query("
    SELECT COALESCE(SUM(amount),0) FROM commissions
    WHERE type='daily_fixed' AND status='credited'
")->fetchColumn();

$dfiPendingToday = (int)db()->query("
    SELECT COUNT(*) FROM users u
    JOIN packages p ON p.id = u.package_id
    WHERE u.role='member' AND u.status='active'
      AND p.daily_fixed_income > 0
      AND (u.dfi_last_paid_at IS NULL OR u.dfi_last_paid_at < CURDATE())
")->fetchColumn();
```

Add to dashboard stats:

```php
['DFI Paid Today', fmt_money($totalDfiPaid), 'pink', '📅',
 number_format($dfiPendingToday) . ' pending today'],
```

### 15.2 Member Detail DFI Section (`user_view.php`)

Add to the info table:

```php
<tr>
    <td>Daily Fixed Income</td>
    <td>
        <?php if ((float)($user['daily_fixed_income'] ?? 0) > 0): ?>
            <?= fmt_money($user['daily_fixed_income']) ?>/day ·
            <?= (int)$user['dfi_days_used'] ?>/<?= (int)$user['daily_fixed_days'] ?> days used
        <?php else: ?>
            <span class="text-muted">Not enabled</span>
        <?php endif; ?>
    </td>
</tr>
<tr>
    <td>DFI Total Earned</td>
    <td><?= fmt_money($user['dfi_total_earned'] ?? 0) ?></td>
</tr>
```

---

## Phase 16: Cron Integration

### 16.1 Update Midnight Cron

```php
<?php
/**
 * Midnight cron — full daily processing
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/DailyFixedIncome.php';

// 1. Reset daily pair counters
$affected = db()->exec("UPDATE users SET pairs_paid_today = 0 WHERE role = 'member'");

// 2. Process expired reactivation windows
$expiredCount = User::processExpiredWindows();

// 3. Process Daily Fixed Income payouts
$dfiCount = DailyFixedIncome::processDailyPayouts();

// 4. Update last reset timestamp
db()->prepare("UPDATE settings SET value = ? WHERE key_name = 'last_reset'")
    ->execute([date('Y-m-d H:i:s')]);

// 5. Log
error_log(sprintf(
    "[MLM Cron] %s | Pairs reset: %d | Caps expired: %d | DFI paid: %d members",
    date('Y-m-d H:i:s'),
    $affected,
    $expiredCount,
    $dfiCount
));
```

### 16.2 Add DFI Settings to System Settings (Optional)

If admins need global DFI controls, add to `settings.php`:

```html
<div class="mb-3">
    <label class="form-label">Daily Fixed Income Global Toggle</label>
    <select name="dfi_enabled" class="form-select">
        <option value="1" <?= setting('dfi_enabled', '1') === '1' ? 'selected' : '' ?>>
            Enabled — Packages define their own rates
        </option>
        <option value="0" <?= setting('dfi_enabled') === '0' ? 'selected' : '' ?>>
            Disabled — No DFI payouts system-wide
        </option>
    </select>
    <div class="form-text">Emergency override for all packages</div>
</div>
```

---

## Phase 17: Routing & Controllers

### 17.1 Add Routes to `index.php`

```php
// Member routes
'daily_fixed'        => ['MemberController', 'dailyFixed',        'member'],
```

### 17.2 Add Method to `MemberController.php`

```php
public function dailyFixed(): void
{
    Auth::guard('member');
    require 'views/member/daily_fixed.php';
}
```

---

## Phase 18: Backward Compatibility & Testing

### 18.1 Safe Defaults

| Setting              | Default | Behavior                                     |
| -------------------- | ------- | -------------------------------------------- |
| `daily_fixed_income` | 0       | No DFI — existing packages unaffected        |
| `daily_fixed_days`   | 0       | Unlimited duration if enabled                |
| `dfi_days_used`      | 0       | Starts fresh for all members                 |
| `dfi_last_paid_at`   | NULL    | Eligible for immediate payout if DFI enabled |

### 18.2 Migration Script Extension

Add to `migrate_cap.php`:

```php
// DFI columns
$pdo->exec("
    ALTER TABLE packages
    ADD COLUMN IF NOT EXISTS daily_fixed_income DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    ADD COLUMN IF NOT EXISTS daily_fixed_days TINYINT UNSIGNED NOT NULL DEFAULT 0
");

$pdo->exec("
    ALTER TABLE users
    ADD COLUMN IF NOT EXISTS dfi_days_used TINYINT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS dfi_last_paid_at DATE NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS dfi_total_earned DECIMAL(14,2) NOT NULL DEFAULT 0.00
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS daily_fixed_income_log (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      user_id INT UNSIGNED NOT NULL,
      amount DECIMAL(12,2) NOT NULL,
      day_number TINYINT UNSIGNED NOT NULL,
      cap_blocked TINYINT(1) NOT NULL DEFAULT 0,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
      INDEX idx_user_date (user_id, created_at)
    ) ENGINE=InnoDB
");
```

### 18.3 Testing Checklist — DFI Specific

| Test Case                         | Expected Result                                  |
| --------------------------------- | ------------------------------------------------ |
| Package with DFI = 0              | No DFI UI, no cron payout                        |
| Package with DFI = 100, days = 30 | Member sees DFI widget, gets ₱100/day            |
| Member hits cap mid-DFI cycle     | DFI prorated on cap day, subsequent days blocked |
| Capped member reactivates         | `dfi_days_used` resets to 0, new 30-day cycle    |
| Member inactive (suspended)       | DFI paused — no payout, days not consumed        |
| Cron runs twice same day          | `dfi_last_paid_at` prevents double-pay           |
| Member upgrades to DFI package    | DFI starts immediately if never paid today       |
| Admin views member DFI history    | Shows all payments, blocked amounts, day numbers |

---

## Complete File Summary (All Phases 1–18)

| File                                | Status       | Purpose                                                                                     |
| ----------------------------------- | ------------ | ------------------------------------------------------------------------------------------- |
| `install.sql`                       | **Modified** | Schema: cap, reactivation, DFI columns + `reactivation_payments` + `daily_fixed_income_log` |
| `migrate_cap.php`                   | **New**      | One-time migration for existing installs                                                    |
| `models/Package.php`                | **Modified** | Cap, reactivation, DFI CRUD                                                                 |
| `models/User.php`                   | **Modified** | Cap tracking, reactivation, DFI reset                                                       |
| `models/Commission.php`             | **Modified** | `getCreditableAmount()`, `daily_fixed` type support                                         |
| `models/DailyFixedIncome.php`       | **New**      | DFI payout engine, history, projections                                                     |
| `models/Ewallet.php`                | Unchanged    | Already supports generic credit/debit                                                       |
| `controllers/MemberController.php`  | **Modified** | `reactivate()`, `doReactivate()`, `dailyFixed()`                                            |
| `controllers/AdminController.php`   | **Modified** | `forceReactivate()`, DFI stats                                                              |
| `views/admin/packages.php`          | **Modified** | Cap/reactivation + DFI form sections                                                        |
| `views/admin/dashboard.php`         | **Modified** | Capped, DFI stats cards                                                                     |
| `views/admin/users.php`             | **Modified** | Cap progress, DFI indicators                                                                |
| `views/admin/user_view.php`         | **Modified** | Cap details, DFI history, force reactivate                                                  |
| `views/admin/settings.php`          | **Modified** | Cron status, optional DFI global toggle                                                     |
| `views/member/dashboard(1).php`     | **Modified** | Cap alert, DFI widget, reactivation CTA                                                     |
| `views/member/reactivate.php`       | **New**      | Self-service reactivation flow                                                              |
| `views/member/daily_fixed.php`      | **New**      | DFI timeline, projections, payment history                                                  |
| `views/member/earnings.php`         | **Modified** | `daily_fixed` filter pill                                                                   |
| `views/partials/sidebar_member.php` | **Modified** | Conditional reactivate + DFI nav items                                                      |
| `index.php`                         | **Modified** | Routes: `reactivate`, `do_reactivate`, `daily_fixed`                                        |
| `cron/midnight_reset.php`           | **Modified** | DFI payout + cap expiry + pair reset                                                        |

This completes the full implementation of **Lifetime Income Capping**, **Reactivation Mechanics**, and **Daily Fixed Income** as specified in the simulator reference, with user-friendly monitoring UI and admin oversight tools.
