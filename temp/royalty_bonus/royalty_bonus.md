# Royalty Bonus — Leadership Ranks

## Overview

A multi-rank incentive system that rewards members based on organization-building (direct sponsors + qualified downline leaders) and sales volume (personal & group PV from repeat purchases). Rank qualification uses an **OR gate** (personal PV OR group PV) at the QA entry point, then cascades through hierarchically nested rank requirements.

Based on `sim/v9b2/index.html` — Card 7, `evaluateAndPayRoyalty()`.

---

## Phase 1 — Database Schema

### 1.1 New `users` columns

```sql
-- Reuse existing columns:
--   personal_pv   DECIMAL(14,2)  -- buyer's own repeat-purchase PV (already exists)
--   group_pv      DECIMAL(14,2)  -- group PV from downline (already exists)
--   direct_count -- not stored on users table. Must compute or cache.

-- New column:
ALTER TABLE users
  ADD COLUMN rank_royalty ENUM('qa','supervisor','manager','director','chairman') NULL DEFAULT NULL AFTER group_pv,
  ADD COLUMN royalty_last_processed_at TIMESTAMP NULL AFTER rank_royalty;
```

### 1.2 New `settings` keys

| key | default | description |
|-----|---------|-------------|
| `royalty_enabled` | `0` | master toggle |
| `royalty_qa_directs` | `3` | min personally sponsored to be QA |
| `royalty_qa_personal_pv` | `200` | min personal PV for QA (OR gate) |
| `royalty_qa_group_pv` | `1000` | min group PV for QA (OR gate) |
| `royalty_supervisor_group_pct` | `3` | % of group PV paid to Supervisors |
| `royalty_supervisor_repeat_pct` | `5` | % of repeat net paid to Supervisors |
| `royalty_manager_group_pct` | `5` | % of group PV paid to Managers |
| `royalty_manager_repeat_pct` | `10` | % of repeat net paid to Managers |
| `royalty_director_group_pct` | `10` | % of group PV paid to Directors |
| `royalty_director_repeat_pct` | `15` | % of repeat net paid to Directors |
| `royalty_chairman_group_pct` | `12` | % of group PV paid to Chairmen |
| `royalty_chairman_repeat_pct` | `20` | % of repeat net paid to Chairmen |

### 1.3 Extend `commissions` type ENUM

```sql
ALTER TABLE commissions
  MODIFY COLUMN type ENUM('pairing','direct_referral','indirect_referral',
                          'daily_fixed_income','unilevel_product','royalty')
  NOT NULL;
```

### 1.4 Migration SQL

`migrations/027_royalty_bonus.sql` — single file containing all of 1.1–1.3.

---

## Phase 2 — Core Logic: `core/Royalty.php`

New class `Royalty` (global namespace, matching existing style). All static methods.

### 2.1 `Royalty::evaluateAndPay(array $memberIds, float $repeatNetIncome): array`

Called at end of each royalty period (daily cron by default). Follows the simulation's `evaluateAndPayRoyalty()`.

**Step 1 — Determine Qualified Associates (QA)**
```php
foreach active members:
  if direct_sponsor_count < setting('royalty_qa_directs'): skip
  personal_ok = personal_pv >= setting('royalty_qa_personal_pv')
  group_ok    = group_pv >= setting('royalty_qa_group_pv')
  is_qa = personal_ok || group_ok
```

**Step 2 — Supervisors** (QA + 10 directs + 5 QA among them)
```php
foreach QA:
  if direct_sponsor_count < 10: skip
  count QA legs (directly sponsored QAs)
  if qa_legs >= 5: is_supervisor
```

**Step 3 — Managers** (QA + 3 Supervisor legs)
```php
foreach QA:
  count directly sponsored Supervisors
  if sup_legs >= 3: is_manager
```

**Step 4 — Directors** (QA + 3 Manager legs)

**Step 5 — Chairmen** (QA + 3 Director legs)

**Step 6 — Pay Bonuses**
```php
foreach member with any rank:
  group_bonus  = rank_group_pct  * group_pv * pv_per_peso_rate
  repeat_bonus = rank_repeat_pct * repeatNetIncome
  total_bonus  = group_bonus + repeat_bonus

  // Apply income cap check (reuse CapEngine)
  if CapEngine::canEarn(member_id, total_bonus):
    insert commission (type='royalty')
    CapEngine::recordEarning(member_id, total_bonus)
    update member.rank_royalty = current_rank
    update member.ewallet_balance += total_bonus
```

Returns array of [member_id => ['rank', 'bonus', 'group_bonus', 'repeat_bonus']].

### 2.2 `Royalty::qualify(int $memberId): ?string`

Standalone qualification check (no payments). Returns the highest rank the member currently qualifies for, or null.

Used for display/profile use.

### 2.3 Data source notes

- `personal_pv` and `group_pv` are **already accumulated** by `Commission::processProductPV()` — no changes needed in the commission pipeline.
- `direct_sponsor_count` = `SELECT COUNT(*) FROM users WHERE sponsor_id = ? AND status = 'active'` (computed fresh each period; caching not needed at this scale).
- `repeatNetIncome` = total peso value of repeat product purchases in the current period (configurable: daily/weekly/monthly). Calculated from orders table.

---

## Phase 3 — Processing Schedule

### 3.1 Cron-based (recommended for v1)

New cron: `cron/daily_royalty.php` — runs after midnight reset (`cron/midnight_reset.php`).

```
0 1 * * * /usr/bin/php /path/cron/daily_royalty.php
```

Logic:
1. Check if `royalty_enabled` is ON
2. Calculate `repeatNetIncome` from yesterday's product orders
3. Load all active members
4. Call `Royalty::evaluateAndPay($memberIds, $repeatNetIncome)`
5. Set `royalty_last_processed_at` timestamp on processed members

### 3.2 Alternative: real-time on purchase

Add call to `Royalty::qualify()` inside `Commission::processProductPV()` after the existing pipeline. This would update the rank instantly but not pay out until cron.

For v1, keep payout as cron-only. Qualification display can be real-time with `Royalty::qualify()`.

---

## Phase 4 — Admin Settings UI

### 4.1 New settings section in `AdminController::settings()`

Add a "Royalty Bonus" card after the existing "Repeat Purchase / Product Settings" card with:

- Master toggle (checkbox)
- QA requirements: directs (1–20), personal PV (0–5000), group PV (0–50000)
- Rank percentages (6 pairs of group + repeat sliders/inputs, 0–30%)

### 4.2 Validation

- Percentages must be 0–30
- QA direct minimum must be ≥ 1
- Save to `settings` table (existing pattern)

---

## Phase 5 — Member UI

### 5.1 Rank badge on member dashboard

In `views/member/dashboard.php` — show current `rank_royalty` as a badge (color-coded per rank).

### 5.2 Royalty earnings table

New section on member dashboard or dedicated `?page=member_royalty`:

- Current rank
- Personal PV / Group PV (progress bars toward next rank)
- Direct count / QA-leq count (progress toward next rank)
- Historical royalty commissions (from `commissions WHERE type='royalty'`)

### 5.3 Rank requirements reference

Static table showing all 5 ranks and their requirements.

---

## Phase 6 — Testing

### 6.1 Manual test scenarios

See `temp/royalty_bonus/royalty_qa_test.md` (TBD — use same structure as `repeat_purchase_qa_test.md`).

### 6.2 Key assertions

1. **QA gate** — member with 3 directs + personal PV >= threshold qualifies; member with same directs but neither PV threshold met does not
2. **Rank cascade** — Supervisor requires 10 directs + 5 QA legs; Manager requires 3 Supervisor legs; Director requires 3 Manager legs; Chairman requires 3 Director legs
3. **OR gate** — member with 0 personal PV but high group PV qualifies as QA
4. **Bonus calculation** — group bonus = pct × group_pv × pv_per_peso_rate; repeat bonus = pct × repeat_net_income
5. **Income cap interaction** — capped members (cap_status != 'active') skip payout
6. **Toggle off** — `royalty_enabled = 0` skips all processing
7. **Cron idempotency** — running royalty cron twice on same period pays only once (check `royalty_last_processed_at`)
8. **Zero net income** — repeat bonus = 0 when no repeat purchases in period

---

## Future Considerations

| Enhancement | Notes |
|-------------|-------|
| Weekly/monthly periods | Add `royalty_period` setting (`daily`/`weekly`/`monthly`) |
| Configurable rank thresholds | Make Supervisor 10-directs and 5-QA-legs configurable |
| Rank downgrade logic | Define grace period before rank drops |
| Real-time rank achievements | Flash notifications on rank-up |
| CSV export of rank report | Admin report of all members' ranks |
| Upline Support integration | Already handled: Royalty triggers `triggerUplineSupport()` in sim |
| Pool-based bonus | Instead of individual %, pool total and divide among qualifiers |

---

## File Checklist

| File | Purpose |
|------|---------|
| `migrations/027_royalty_bonus.sql` | Schema changes |
| `core/Royalty.php` | Core evaluation + payout logic |
| `cron/daily_royalty.php` | Daily cron runner |
| `controllers/AdminController.php` | Settings UI addition |
| `views/admin/settings.php` | Royalty settings form fields |
| `views/member/dashboard.php` | Rank badge |
| `views/member/royalty.php` | Dedicated royalty page |
| `controllers/MemberController.php` | royalty action handler |
