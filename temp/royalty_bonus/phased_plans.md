# Royalty Bonus — Phased Development Plan

## Guiding Principles

- **Each phase is self-contained** — after every phase the system is stable, nothing is broken, and the new capability is usable
- **No half-baked states** — a phase either delivers a complete feature slice or it doesn't ship
- **Backward compatible** — existing data and behavior are preserved across phases (except where explicitly deprecated)
- **Money safety** — the pool never pays out until the distribution engine is complete and verified

---

## Phase 1: Schema + Settings + Backend Config

**Goal**: All configuration surfaces exist. Nothing changes in behavior yet — no accumulation, no payout.

### 1a. Migration: `migrations/032_royalty_bonus_pool.sql`

```sql
-- Royalty pool table
CREATE TABLE IF NOT EXISTS royalty_pool (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  period_date DATE NOT NULL COMMENT 'First of month (YYYY-MM-01)',
  total_sales DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  pool_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  pool_rate   DECIMAL(5,2) NOT NULL COMMENT 'e.g. 10.00 = 10%',
  status      ENUM('open','closed','distributed') NOT NULL DEFAULT 'open',
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY idx_period (period_date)
) ENGINE=InnoDB;

-- Insert open row for current month
INSERT INTO royalty_pool (period_date, total_sales, pool_amount, pool_rate, status)
VALUES (DATE_FORMAT(NOW(), '%Y-%m-01'), 0, 0, 0, 'open')
ON DUPLICATE KEY UPDATE id = id;
```

### 1b. Settings Keys (`install.sql` + migration INSERT)

| Key | Default | Purpose |
|-----|---------|---------|
| `royalty_enabled` | `0` | Master toggle |
| `royalty_pool_rate` | `10.00` | % of monthly repeat sales (e.g. `10.00` = 10%) |
| `royalty_min_pool` | `500.00` | Minimum pool amount to trigger distribution |
| `royalty_supervisor_rate` | `25` | % of pool to supervisor tier |
| `royalty_manager_rate` | `25` | % of pool to manager tier |
| `royalty_director_rate` | `25` | % of pool to director tier |
| `royalty_chairman_rate` | `25` | % of pool to chairman tier |
| `royalty_qa_directs` | `3` | Directs required for QA qualification |
| `royalty_qa_personal_pv` | `200` | Personal PV gate for QA |
| `royalty_qa_group_pv` | `1000` | Group PV gate for QA |
| `royalty_spv_directs` | `10` | Directs required for Supervisor |
| `royalty_spv_qa_legs` | `5` | QA legs required for Supervisor |
| `royalty_mgr_sup_legs` | `3` | Supervisor legs required for Manager |
| `royalty_dir_mgr_legs` | `3` | Manager legs required for Director |
| `royalty_chm_dir_legs` | `3` | Director legs required for Chairman |

> Existing `rank_royalty` column on `users` + `commissions.type = 'royalty'` already exist from migration 031.

### 1c. Rank-Rate Validation (No Auto-Normalization)

The 4 rank rates must sum to exactly 100. There is **no automatic adjustment** — the admin must manually correct the values.

**Backend validation** (in `AdminController` settings save):

```php
$rates = [
    (float) ($_POST['royalty_supervisor_rate'] ?? 25),
    (float) ($_POST['royalty_manager_rate'] ?? 25),
    (float) ($_POST['royalty_director_rate'] ?? 25),
    (float) ($_POST['royalty_chairman_rate'] ?? 25),
];
$sum = array_sum($rates);
if (abs($sum - 100) > 0.01) {
    flash('error', "Rank rates must sum to 100 (current: {$sum}). No settings saved.");
    // Keep existing values, do not persist
}
```

**Frontend validation** (in admin settings JS, before form submit):

```js
const rates = document.querySelectorAll('.royalty-rank-rate');
let sum = 0;
rates.forEach(el => sum += parseFloat(el.value) || 0);
if (Math.abs(sum - 100) > 0.01) {
    alert('Rank rates must sum to 100. Current sum: ' + sum.toFixed(2));
    event.preventDefault();
}
```

### 1d. Open Pool Row on Cron Bootstrap

On every cron run that touches royalty, ensure an `open` row exists for the current month:

```php
$pdo->exec("
    INSERT IGNORE INTO royalty_pool (period_date, total_sales, pool_amount, pool_rate, status)
    VALUES (DATE_FORMAT(NOW(), '%Y-%m-01'), 0, 0, 0, 'open')
");
```

### Deliverables Checklist

- [ ] Migration 032 creates `royalty_pool` table + inserts current month open row
- [ ] Migration inserts/updates all settings keys in `install.sql` and `reset.php`
- [ ] Backend validation (sum must = 100) in settings save handler
- [ ] `install.sql` updated with new table + settings
- [ ] `reset.php` clears `royalty_pool` table
- [ ] After Phase 1: system runs as before (no behavioral change)

---

## Phase 2: Pool Accumulation (Per-Transaction Tracking)

**Goal**: Every completed repeat purchase order contributes its total to the open pool row. Old per-transaction royalty payout is removed.

### 2a. Modify `Commission.php` — `processProductPV()` or dedicated method

In the repeat purchase flow (around line 480 in current `Commission.php`), replace:

```php
// ★ Step 5: Royalty Bonus — Leadership Ranks (real-time on each purchase)
$orderTotal = (float)$order['total_price'];
Royalty::processRepeatPurchase($memberId, $totalPv, $orderTotal);
```

With:

```php
// ★ Step 5: Royalty Bonus — Accumulate pool (per-transaction tracking)
Royalty::accumulatePool($order['total_price']);
```

### 2b. New method: `Royalty::accumulatePool()`

```php
public static function accumulatePool(float $orderTotal): void
{
    if (setting('royalty_enabled', '0') !== '1') return;
    if ($orderTotal <= 0) return;

    $rate = (float) setting('royalty_pool_rate', '10.00');
    if ($rate <= 0) return;

    $pdo = db();
    $period = date('Y-m-01');

    // Ensure open row exists
    $pdo->prepare("
        INSERT IGNORE INTO royalty_pool (period_date, total_sales, pool_amount, pool_rate, status)
        VALUES (?, 0, 0, ?, 'open')
    ")->execute([$period, $rate]);

    // Accumulate
    $pdo->prepare("
        UPDATE royalty_pool
        SET total_sales = total_sales + ?,
            pool_amount = total_sales * ? / 100
        WHERE period_date = ? AND status = 'open'
    ")->execute([$orderTotal, $rate, $period]);
}
```

### 2c. Keep `Royalty::highestRank()` + rank_royalty column update

The dynamic rank determination (`highestRank()`) and `rank_royalty` column update on `users` are still needed for qualification checking at month-end. But the payout logic inside `processRepeatPurchase()` is removed entirely.

**What stays:**
- `Royalty::highestRank()` — static method for determining a member's rank
- `Royalty::rankLabel()` — display helper
- `Royalty::rankStyle()` — display helper
- The `rank_royalty` column update — but only during monthly distribution, not per-transaction

Actually, the `rank_royalty` column was being updated per-transaction in the old code. In the new model, it should be updated monthly during distribution. But the `highestRank()` method already computes rank dynamically without relying on the stored column. The column can serve as a cache/display value updated during monthly distribution.

### Deliverables Checklist

- [ ] `Royalty::accumulatePool()` implemented
- [ ] Old `Royalty::processRepeatPurchase()` removed or gutted (keep helper methods)
- [ ] `Commission.php` calls `accumulatePool()` instead of `processRepeatPurchase()`
- [ ] After Phase 2: pool accumulates on every purchase, but no money is paid out yet
- [ ] No regression — all other bonuses (binary, direct, indirect, unilevel, DFI) unaffected

---

## Phase 3: Monthly Distribution Engine (Cron)

**Goal**: On the 1st of each month, the prior month's pool is distributed to qualified members.

### 3a. Create: `cron/monthly_royalty_distribution.php`

```php
<?php
/**
 * Monthly Royalty Bonus Distribution
 *
 * Runs on the 1st of each month.
 * Distributes the prior month's royalty pool to qualified members
 * based on rank-tier allocation rates.
 *
 * Cron: 0 0 1 * * /usr/bin/php /path/cron/monthly_royalty_distribution.php
 */

date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/../config/db.php';

// Ensure autoloader (same pattern as other cron scripts)
spl_autoload_register(function ($class) {
    $file = __DIR__ . '/../core/' . $class . '.php';
    if (file_exists($file)) require $file;
});

$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) mkdir($logDir, 0755, true);

$logFile = $logDir . '/royalty_distribution_' . date('Y-m') . '.log';
$log = function ($msg) use ($logFile) {
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg;
    file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND);
    echo $line . PHP_EOL;
};

try {
    $pdo    = db();
    $period = date('Y-m-01', strtotime('-1 month')); // last month

    $log("Starting royalty distribution for {$period}");

    // ── 1. Close prior month pool ──────────────────────────────
    $stmt = $pdo->prepare("
        UPDATE royalty_pool
        SET status = 'closed'
        WHERE period_date = ? AND status = 'open'
    ");
    $stmt->execute([$period]);

    if ($stmt->rowCount() === 0) {
        $log("No open pool found for {$period} — nothing to distribute");
        exit(0);
    }

    // ── 2. Fetch pool data ─────────────────────────────────────
    $pool = $pdo->prepare("
        SELECT id, total_sales, pool_amount, pool_rate
        FROM royalty_pool
        WHERE period_date = ? AND status = 'closed'
    ");
    $pool->execute([$period]);
    $pool = $pool->fetch();

    if (!$pool) {
        $log("Pool row not found after close — aborting");
        exit(1);
    }

    $poolAmount = (float) $pool['pool_amount'];
    $minPool    = (float) setting('royalty_min_pool', '500.00');

    $log("Pool: total_sales={$pool['total_sales']}, rate={$pool['pool_rate']}%, computed_amount={$poolAmount}");

    // ── 3. Minimum pool threshold check ────────────────────────
    if ($poolAmount < $minPool) {
        $pdo->prepare("UPDATE royalty_pool SET status = 'distributed' WHERE id = ?")
            ->execute([$pool['id']]);
        $log("Pool amount {$poolAmount} below minimum {$minPool} — forfeited");
        exit(0);
    }

    // ── 4. Qualification gates (from settings) ─────────────────
    $qaDirects   = (int) setting('royalty_qa_directs', '3');
    $qaPersonal  = (float) setting('royalty_qa_personal_pv', '200');
    $qaGroup     = (float) setting('royalty_qa_group_pv', '1000');
    $spvDirects  = (int) setting('royalty_spv_directs', '10');
    $spvQaLegs   = (int) setting('royalty_spv_qa_legs', '5');
    $mgrSupLegs  = (int) setting('royalty_mgr_sup_legs', '3');
    $dirMgrLegs  = (int) setting('royalty_dir_mgr_legs', '3');
    $chmDirLegs  = (int) setting('royalty_chm_dir_legs', '3');

    // ── 5. Rank rates (must sum to 100) ────────────────────────
    $rates = [
        'supervisor' => (float) setting('royalty_supervisor_rate', '25'),
        'manager'    => (float) setting('royalty_manager_rate', '25'),
        'director'   => (float) setting('royalty_director_rate', '25'),
        'chairman'   => (float) setting('royalty_chairman_rate', '25'),
    ];
    $rateSum = array_sum($rates);
    if (abs($rateSum - 100) > 0.01) {
        $log("Rank rates sum to {$rateSum}, not 100 — skipping distribution");
        exit(1);
    }

    // ── 6. Count qualifiers per rank ───────────────────────────
    // Each member qualifies at their HIGHEST rank only (no double-dipping)

    $qualifiers = [];

    // Chairman: 3 Director legs
    // (query simplified for illustration — uses highestRank() logic)
    foreach (['chairman', 'director', 'manager', 'supervisor'] as $rank) {
        $qualifiers[$rank] = Royalty::countQualifiersAtRank(
            $rank, $qaDirects, $qaPersonal, $qaGroup,
            $spvDirects, $spvQaLegs, $mgrSupLegs, $dirMgrLegs, $chmDirLegs
        );
        $log("Qualified {$rank}: {$qualifiers[$rank]} member(s)");
    }

    $totalQualified = array_sum($qualifiers);
    if ($totalQualified === 0) {
        $pdo->prepare("UPDATE royalty_pool SET status = 'distributed' WHERE id = ?")
            ->execute([$pool['id']]);
        $log("No qualifiers — pool of {$poolAmount} forfeited");
        exit(0);
    }

    // ── 7. Distribute per rank tier ────────────────────────────
    $totalDistributed = 0;
    $cdActive         = (bool) setting('cd_active', '0');
    $distributions    = []; // for logging

    foreach ($rates as $rank => $pct) {
        $count = $qualifiers[$rank];
        if ($count === 0) continue;

        $tierSlice = $poolAmount * ($pct / 100);
        $perMember = round($tierSlice / $count, 2);

        // Get member IDs at this rank
        $memberIds = Royalty::getQualifierIdsAtRank(
            $rank, $qaDirects, $qaPersonal, $qaGroup,
            $spvDirects, $spvQaLegs, $mgrSupLegs, $dirMgrLegs, $chmDirLegs
        );

        foreach ($memberIds as $uid) {
            // Cap check
            $capCheck = CapEngine::canEarn((int)$uid, $perMember);
            $actual   = $capCheck['allowed'];
            $blocked  = $capCheck['blocked'];

            if ($actual <= 0) {
                $distributions[] = "{$rank} #{$uid}: skipped (lifetime cap reached)";
                continue;
            }

            // Determine final amount after CD
            $cdDeduction = 0;
            $finalAmount = $actual;
            if ($cdActive) {
                $cdStatus = UserCdStatus::getActiveForUser((int)$uid);
                if ($cdStatus) {
                    $cdDeduction  = CommissionDeduct::calculate($actual, $cdStatus);
                    $finalAmount  = $actual - $cdDeduction;
                }
            }

            // Record commission
            $pdo->prepare("
                INSERT INTO commissions
                  (user_id, type, amount, cap_deduction, source_user_id, description, status)
                VALUES (?, 'royalty', ?, ?, NULL, ?, 'credited')
            ")->execute([
                $uid,
                $finalAmount + $cdDeduction, // gross before CD
                $blocked,
                "Royalty {$rank} — {$period} pool (tier {$pct}%)"
            ]);

            $commId = (int) $pdo->lastInsertId();

            // Credit withdrawable: only the CD-able portion is non-withdrawable
            if ($cdDeduction > 0 && $cdActive) {
                // Credit net to ewallet; CD amount goes to CD ledger
                Ewallet::credit($uid, $finalAmount, $commId, 'commission', "Royalty bonus ({$rank}) — {$period}");

                // Record CD deduction
                CommissionDeduct::record($uid, $commId, $cdDeduction, $finalAmount, $uid, $rank);
            } else {
                Ewallet::credit($uid, $finalAmount, $commId, 'commission', "Royalty bonus ({$rank}) — {$period}");
            }

            // Record lifetime earning
            CapEngine::recordEarning($uid, $finalAmount, 'royalty');

            // Update stored rank
            $pdo->prepare("UPDATE users SET rank_royalty = ? WHERE id = ?")->execute([$rank, $uid]);

            $totalDistributed += $finalAmount;
            $distributions[] = "{$rank} #{$uid}: gross={$perMember}, cap_blocked={$blocked}, cd={$cdDeduction}, net={$finalAmount}";
        }
    }

    // ── 8. Mark pool distributed ───────────────────────────────
    $pdo->prepare("UPDATE royalty_pool SET status = 'distributed' WHERE id = ?")
        ->execute([$pool['id']]);

    // Log results
    $log("Distribution complete: {$totalDistributed} of {$poolAmount} distributed");
    foreach ($distributions as $d) {
        $log("  {$d}");
    }

    // ── 9. Ensure current month open row exists ────────────────
    $currentRate = (float) setting('royalty_pool_rate', '10.00');
    $pdo->prepare("
        INSERT IGNORE INTO royalty_pool (period_date, total_sales, pool_amount, pool_rate, status)
        VALUES (DATE_FORMAT(NOW(), '%Y-%m-01'), 0, 0, ?, 'open')
    ")->execute([$currentRate]);

    $log("Royalty distribution for {$period} completed successfully");

} catch (\Exception $e) {
    $log("ERROR: " . $e->getMessage());
    exit(1);
}
```

### 3b. New methods on `Royalty`

```php
/**
 * Count members who qualify at a specific rank (their highest).
 */
public static function countQualifiersAtRank(
    string $rank,
    int $qaDirects, float $qaPersonal, float $qaGroup,
    int $spvDirects, int $spvQaLegs,
    int $mgrSupLegs, int $dirMgrLegs, int $chmDirLegs
): int {
    // Build and execute SQL using same logic as highestRank()
    // but only counting, not returning individual rows
}

/**
 * Get member IDs qualifying at a specific rank.
 */
public static function getQualifierIdsAtRank(
    string $rank,
    int $qaDirects, float $qaPersonal, float $qaGroup,
    int $spvDirects, int $spvQaLegs,
    int $mgrSupLegs, int $dirMgrLegs, int $chmDirLegs
): array {
    // Similar to countQualifiersAtRank but returns user IDs
}
```

### 3c. Integration: Cron Setup

Add to cron documentation (and install notes):

```
0 0 1 * * /usr/bin/php /path/cron/monthly_royalty_distribution.php >> /path/cron/logs/royalty_distribution.log 2>&1
```

### Deliverables Checklist

- [ ] `cron/monthly_royalty_distribution.php` created and tested
- [ ] `Royalty::countQualifiersAtRank()` implemented
- [ ] `Royalty::getQualifierIdsAtRank()` implemented
- [ ] CapEngine integration (lifetime cap check)
- [ ] CD integration (commission-deduct if active)
- [ ] E-wallet credit + commission record
- [ ] Logging to `cron/logs/`
- [ ] Cron setup documented
- [ ] After Phase 3: pool accumulates all month, distributes on 1st — full cycle works

---

## Phase 4: Admin UI — Settings Management

**Goal**: Admin can configure all royalty parameters from the settings page.

### 4a. Core/Royalty.php — Settings Save Handler

```php
public static function saveSettings(array $input): array
{
    $errors = [];

    $fields = [
        'royalty_enabled'          => ['type' => 'toggle'],
        'royalty_pool_rate'        => ['type' => 'float', 'min' => 0, 'max' => 100],
        'royalty_min_pool'         => ['type' => 'float', 'min' => 0],
        'royalty_qa_directs'       => ['type' => 'int', 'min' => 0],
        'royalty_qa_personal_pv'   => ['type' => 'float', 'min' => 0],
        'royalty_qa_group_pv'      => ['type' => 'float', 'min' => 0],
        'royalty_spv_directs'      => ['type' => 'int', 'min' => 0],
        'royalty_spv_qa_legs'      => ['type' => 'int', 'min' => 0],
        'royalty_mgr_sup_legs'     => ['type' => 'int', 'min' => 0],
        'royalty_dir_mgr_legs'     => ['type' => 'int', 'min' => 0],
        'royalty_chm_dir_legs'     => ['type' => 'int', 'min' => 0],
    ];

    // Validate rank rates sum to 100 (no auto-normalization)
    $rankRates = [];
    foreach (['supervisor', 'manager', 'director', 'chairman'] as $rank) {
        $key = "royalty_{$rank}_rate";
        if (isset($input[$key])) {
            $rankRates[$rank] = (float) $input[$key];
        }
    }
    if (count($rankRates) === 4) {
        $sum = array_sum($rankRates);
        if (abs($sum - 100) > 0.01) {
            $errors[] = "Rank rates must sum to 100 (current: {$sum}). No settings saved.";
        }
    }

    if ($errors) return $errors;

    foreach ($input as $key => $value) {
        if (str_starts_with($key, 'royalty_')) {
            setting_save($key, $value);
        }
    }

    return [];
}
```

### 4b. Admin Settings View — Royalty Tab Pane

Add a "Royalty Bonus" tab to `views/admin/settings.php` containing:

**Pool Configuration Section:**
- Master toggle (enabled/disabled)
- Pool rate (% of monthly repeat sales) — number input, step 0.01
- Minimum pool threshold (₱) — number input, step 0.01

**Rank Rate Allocation Section:**
- 4 inputs (Supervisor, Manager, Director, Chairman)
- Displayed as percentages, with a live running total
- **Validation on form submit** (no auto-normalization):
  ```js
  document.getElementById('settingsForm').addEventListener('submit', function(e) {
      const rates = document.querySelectorAll('.royalty-rank-rate');
      let sum = 0;
      rates.forEach(el => sum += parseFloat(el.value) || 0);
      if (Math.abs(sum - 100) > 0.01) {
          alert('Rank rates must sum to 100%. Current sum: ' + sum.toFixed(2));
          e.preventDefault();
      }
  });
  ```
- Live sum display that turns red when ≠ 100
- Visual indicator (color-coded bar or pie showing allocation)

**Qualification Gates Section:**
| Setting | Input |
|---------|-------|
| QA: minimum directs | number |
| QA: personal PV gate | number (₱) |
| QA: group PV gate | number (₱) |
| Supervisor: minimum directs | number |
| Supervisor: minimum QA legs | number |
| Manager: minimum Supervisor legs | number |
| Director: minimum Manager legs | number |
| Chairman: minimum Director legs | number |

### 4c. Partial: `views/partials/settings_offcanvas.php`

Add a "Royalty Bonus" nav item linking to the new tab pane.

### Deliverables Checklist

- [ ] Admin settings tab for royalty exists
- [ ] Pool rate, min threshold, rank rates, qualification gates all adjustable
- [ ] Backend validation rejects rank rates not summing to 100
- [ ] Frontend JS validation alerts on submit if sum ≠ 100
- [ ] Live sum display turns red when ≠ 100
- [ ] Settings save correctly to DB
- [ ] Validation errors displayed
- [ ] After Phase 4: admin fully controls royalty configuration

---

## Phase 5: Member UI — Dashboard & Visibility

**Goal**: Members can see pool status and their projected/earned royalty bonuses.

### 5a. Member Dashboard: Royalty Card

On `views/member/dashboard.php`, add a royalty card showing:

- **Current month pool size** (live, updating as sales accumulate)
  ```sql
  SELECT total_sales, pool_amount FROM royalty_pool WHERE status = 'open' LIMIT 1;
  ```
- **Your current rank** (from `rank_royalty` column or computed dynamically)
- **Your projected share** (based on current rank rate and current pool, assuming current qualifier count)
  - Note: "Projected — actual depends on month-end qualifier count"
- **Last month's payout** (from commissions table where type = 'royalty')

### 5b. Member Royalty Page: `views/member/royalty.php`

A dedicated page (already exists from previous implementation) that shows:

- **Rank progress** — current rank, requirements for next rank
- **Pool history** — last 6 months of pool size, qualifier count, and your payout per month
- **Rank qualification status** — whether you currently meet each rank's gates

### 5c. Sidebar Update

Already exists from previous implementation — verify the `views/partials/sidebar_member.php` link to `?page=royalty` works.

### Deliverables Checklist

- [ ] Member dashboard shows pool size + rank + projected share
- [ ] `views/member/royalty.php` shows rank progress + pool history
- [ ] Sidebar link functional
- [ ] After Phase 5: full visibility for members

---

## Phase 6: Legacy Cleanup + Testing

**Goal**: Remove old code, update install.sql and reset.php, QA test.

### 6a. Remove Dead Code

- Remove `Royalty::processRepeatPurchase()` entirely
- Remove `Royalty::recordCapBlocked()` (no longer needed per-transaction)
- Update `Commission.php` to only call `Royalty::accumulatePool()`

### 6b. Update `install.sql`

- Add `royalty_pool` table creation
- Add all new settings keys
- Update `reset.php` to:
  - Clear `royalty_pool` table
  - Reset `rank_royalty` column on admin (already done)

### 6c. Update Settings Defaults in Existing Migration

Create `migrations/033_update_royalty_settings.sql` to add any missing keys from Phase 1 that weren't in migration 031.

### 6d. QA Test Guide

Create `temp/royalty_bonus/royalty_bonus_qa_test.md` with test scenarios:

1. **Pool accumulation**: Complete a repeat purchase → verify `royalty_pool.total_sales` increased
2. **Monthly distribution**: Manually run cron → verify qualifying members received correct payout
3. **Rank rate validation**: Set rates to 30/30/30/30 → verify save rejected with error; set to 25/25/25/25 → verify save succeeds
4. **Minimum pool threshold**: Set min pool above current pool → verify pool forfeited
5. **Lifetime cap**: Member at cap → verify royalty bypassed by cap engine
6. **CD deduction**: Active CD member → verify CD deducted from royalty payout
7. **Zero qualifiers**: Ensure pool forfeits gracefully
8. **Edge case**: Rate change mid-month → prior month unaffected, current month uses new rates

### Deliverables Checklist

- [ ] Old `processRepeatPurchase()` removed
- [ ] `install.sql` complete with all schema + settings
- [ ] `reset.php` handles royalty_pool table
- [ ] Migration 033 for any missing settings
- [ ] QA test guide written
- [ ] All 8 test scenarios pass

---

## Summary: Phase Dependency Graph

```
Phase 1 (Schema + Settings)
    │
    ▼
Phase 2 (Pool Accumulation) ── no payout yet, safe
    │
    ▼
Phase 3 (Distribution Engine) ── money moves, full cycle
    │
    ├──► Phase 4 (Admin UI) ── can be done in parallel with Phase 5
    │
    ├──► Phase 5 (Member UI) ── depends on Phase 3 for data
    │
    ▼
Phase 6 (Cleanup + Testing) ── polishes everything
```

Each phase can be deployed independently. The system is stable after every phase:
- After P1: config exists, no behavioral change
- After P2: pool accumulates silently, no payout
- After P3: full royalty cycle works (cron-driven)
- After P4: admin configures via UI
- After P5: members see results
- After P6: clean code, tested
