I'll analyze the uploaded files and the simulator reference to create a comprehensive phased implementation plan. Let me first review the existing codebase structure and the simulator's mechanics.
Now I have a thorough understanding of both the existing codebase and the simulator mechanics. Let me compile the comprehensive phased implementation plan.

---

# MLM Binary System — Lifetime Income Capping & Daily Fixed Income Implementation Plan

## Executive Summary

This plan adds **Lifetime Income Capping** (covering ALL commission types: pairing, direct referral, indirect referral, and daily fixed income) and **Daily Fixed Income (DFI)** as brand-new compensation streams to the existing Altas Farm MLM system. The implementation is split into **6 self-contained phases**, each building on the previous one.

---

## Phase 1: Database Schema & Package Settings Foundation

**Status: READY TO START** | **Estimated Effort: ~2-3 hours**

### 1.1 Schema Changes (`install.sql` + migration script)

| Table                    | Changes                                                                                                                                                                                                                                                                            |
| ------------------------ | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `packages`               | Add 5 new columns: `lifetime_cap_multiplier` (DECIMAL 5,2, default 3.00), `reactivation_fee` (DECIMAL 12,2, default 0.00), `reactivation_window_days` (INT, default 15), `daily_fixed_income` (DECIMAL 12,2, default 0.00), `daily_fixed_income_days` (INT, default 90)            |
| `users`                  | Add 6 new columns: `lifetime_earned` (DECIMAL 14,2, default 0.00), `cap_status` (ENUM 'active','capped','perminact', default 'active'), `capped_at` (TIMESTAMP NULL), `dfi_days_used` (INT, default 0), `dfi_active` (TINYINT, default 1), `last_reactivation_at` (TIMESTAMP NULL) |
| `reactivations`          | **NEW TABLE** — tracks reactivation history: `id`, `user_id`, `amount_paid`, `previous_earned`, `new_cycle_started_at`, `created_at`                                                                                                                                               |
| `daily_fixed_income_log` | **NEW TABLE** — tracks DFI payouts: `id`, `user_id`, `amount`, `day_number`, `cap_status_at_payout`, `created_at`                                                                                                                                                                  |
| `commissions`            | Add `cap_deduction` (DECIMAL 12,2, default 0.00) to track how much was blocked by cap                                                                                                                                                                                              |

### 1.2 Package CRUD Updates (`AdminController::savePackage()`, `views/admin/packages.php`)

- Add 5 new form fields to package create/edit form:
  - **Lifetime Income Cap** = `entry_fee × lifetime_cap_multiplier` (auto-displayed, non-editable directly)
  - **Lifetime Cap Multiplier** (editable, default 3.00)
  - **Reactivation Fee** (₱, default 0)
  - **Reactivation Window** (days, default 15)
  - **Daily Fixed Income** (₱/day, default 0 = disabled)
  - **DFI Max Days** (days, default 90)

- Update `Package::save()` model to persist new fields
- Update `Package::find()` / `Package::all()` to return new fields

### 1.3 Seed Data Update

- Update default Starter package seed to include new defaults:
  ```sql
  lifetime_cap_multiplier = 3.00,
  reactivation_fee = 10000.00,
  reactivation_window_days = 15,
  daily_fixed_income = 100.00,
  daily_fixed_income_days = 90
  ```

### 1.4 Migration Script

- Create `migrate_v2.php` one-time script for existing deployments:
  - Alters `packages` and `users` tables
  - Creates `reactivations` and `daily_fixed_income_log` tables
  - Backfills `lifetime_earned` from existing commissions
  - Sets `cap_status` based on whether `lifetime_earned >= entry_fee × 3`

### Phase 1 Deliverables

- [ ] Updated `install.sql` with new schema
- [ ] `migrate_v2.php` migration script
- [ ] Updated package CRUD in admin panel
- [ ] All existing tests still pass

---

## Phase 2: Core Capping Engine & Commission Integration

**Builds on: Phase 1** | **Estimated Effort: ~3-4 hours**

### 2.1 Cap Tracking Service (`core/CapEngine.php` — NEW FILE)

```php
class CapEngine {
    // Centralized cap checking for ALL commission types
    public static function canEarn(int $userId, float $amount): array;
    // Returns: ['allowed' => float, 'blocked' => float, 'status' => 'active|capped|perminact']

    public static function recordEarning(int $userId, float $amount, string $type): void;
    // Updates lifetime_earned, checks if cap reached, updates cap_status if needed

    public static function getCapStatus(int $userId): array;
    // Returns full cap state: earned, cap, remaining, status, dfi_days_used, etc.

    public static function isActiveForPairs(int $userId): bool;
    // Returns true only if user is 'active' (not capped or permanently inactive)

    public static function applyCap(int $userId): void;
    // Called when cap is reached: sets cap_status='capped', capped_at=NOW()
}
```

### 2.2 Commission.php Integration

Modify ALL commission-crediting methods to check cap BEFORE crediting:

| Method                      | Change                                                                                                                                           |
| --------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------ |
| `creditPairing()`           | Check `CapEngine::canEarn()` → credit only `allowed` amount → record `blocked` as `cap_deduction` → if cap reached, call `CapEngine::applyCap()` |
| `processDirectReferral()`   | Same cap check — direct referral now counts toward lifetime cap                                                                                  |
| `processIndirectReferral()` | Same cap check — indirect referral now counts toward lifetime cap                                                                                |
| `recordFlush()`             | No change (already 0 amount, but add `cap_deduction` tracking)                                                                                   |

### 2.3 Binary Placement Engine Update (`Commission::processBinaryPlacement()`)

- Before processing pairs for any ancestor, check `CapEngine::isActiveForPairs($ancestorId)`
- **If capped or permanently inactive**: skip entirely (do NOT increment `pairs_paid`, do NOT credit bonus)
- This implements "skipped in pair counting" from the simulator

### 2.4 User Model Updates (`models/User.php`)

- Add `User::getCapStatus($userId)` — delegates to `CapEngine`
- Add `User::isCapActive($userId)` — shorthand for pair eligibility
- Update `User::todayPairingStatus()` to include cap status in returned data

### Phase 2 Deliverables

- [ ] `CapEngine.php` with full cap logic
- [ ] All commission types respect lifetime cap
- [ ] Capped members skipped in binary pair counting
- [ ] Unit tests for cap scenarios

---

## Phase 3: Daily Fixed Income (DFI) Engine

**Builds on: Phase 2** | **Estimated Effort: ~3-4 hours**

### 3.1 DFI Service (`core/DailyFixedIncome.php` — NEW FILE)

```php
class DailyFixedIncome {
    // Called by midnight cron or manual trigger
    public static function processDailyPayout(): array;
    // For every active member with DFI-enabled package:
    //   1. Check cap_status === 'active'
    //   2. Check dfi_days_used < package.daily_fixed_income_days
    //   3. Check CapEngine::canEarn() for DFI amount
    //   4. Credit allowed amount, record in daily_fixed_income_log
    //   5. Increment dfi_days_used
    //   6. If cap reached during DFI, trigger CapEngine::applyCap()

    public static function getMemberDFIStatus(int $userId): array;
    // Returns: total_dfi_earned, days_used, days_remaining, daily_rate, next_payout_date, status

    public static function getDFIHistory(int $userId, int $page = 1): array;
    // Paginated DFI payout history
}
```

### 3.2 DFI Cron Integration

- Add to existing `cron/midnight_reset.php` (or create `cron/daily_fixed_income.php`):
  ```php
  // After pairs_paid_today reset:
  DailyFixedIncome::processDailyPayout();
  ```
- Update `AdminController::manualReset()` to optionally trigger DFI payout
- Add DFI toggle to settings: `dfi_enabled` (global on/off)

### 3.3 DFI & Cap Interaction Rules (per simulator)

| Scenario                                  | Behavior                                            |
| ----------------------------------------- | --------------------------------------------------- |
| Member active, under cap, under day limit | Full DFI paid, counts toward cap                    |
| Member active, near cap                   | Partial DFI paid (only up to cap), cap triggered    |
| Member capped                             | No DFI, days counter PAUSED (does not increment)    |
| Member permanently inactive               | No DFI, days counter frozen                         |
| Member reactivates                        | `dfi_days_used` resets to 0, fresh DFI cycle starts |

### Phase 3 Deliverables

- [ ] `DailyFixedIncome.php` with full DFI logic
- [ ] Midnight cron processes DFI automatically
- [ ] DFI respects lifetime cap and day limits
- [ ] DFI pauses/resumes correctly with cap status changes

---

## Phase 4: Reactivation System & UI

**Builds on: Phase 3** | **Estimated Effort: ~4-5 hours**

### 4.1 Reactivation Service (`core/Reactivation.php` — NEW FILE)

```php
class Reactivation {
    // Member-initiated reactivation (pays from e-wallet or external)
    public static function requestReactivation(int $userId): array;
    // Validates: user is 'capped', within window, has sufficient balance
    // Deducts reactivation_fee from e-wallet OR generates payment instruction
    // Creates reactivation record, resets cap state

    public static function processReactivation(int $userId, string $paymentMethod): array;
    // Actually performs the reset:
    //   1. Set cap_status = 'active'
    //   2. Reset lifetime_earned = 0
    //   3. Reset dfi_days_used = 0
    //   4. Reset dfi_active = 1
    //   5. Set last_reactivation_at = NOW()
    //   6. Record in reactivations table
    //   7. Create e-wallet debit entry for fee

    public static function expireOldCappedUsers(): int;
    // Called by cron: finds users where cap_status='capped'
    // AND capped_at + window < NOW(), sets to 'perminact'
    // Returns count of users expired

    public static function getReactivationHistory(int $userId): array;
    // Full reactivation history for member view
}
```

### 4.2 Member Reactivation UI (`views/member/reactivate.php` — NEW FILE)

**Cap Status Banner** (shown on dashboard when capped):

```
┌─────────────────────────────────────────┐
│  ⚠️ Lifetime Income Cap Reached        │
│  You've earned ₱30,000 of ₱30,000 cap   │
│  [Reactivate Account] — ₱10,000 fee     │
│  Window closes in 12 days                 │
└─────────────────────────────────────────┘
```

**Reactivation Page** (`/?page=reactivate`):

- Shows current cap status, earnings history, window countdown
- Payment options:
  - **Deduct from E-Wallet** (if balance ≥ reactivation_fee)
  - **Pay via GCash/Maya** (external payment flow)
  - **Pay via USDT** (external payment flow)
- Confirmation modal with terms:
  - "Reactivation resets your lifetime earnings counter to zero"
  - "You will start earning from zero in a new cycle"
  - "Previous earnings are retained but do not count toward new cap"
- Success page: shows new cycle start date, fresh cap amount

### 4.3 Admin Reactivation Management

- New admin page: `/?page=admin_reactivations`
- List all reactivation requests (pending/completed)
- Manual reactivation override (for support cases)
- Reactivation revenue stats in dashboard

### 4.4 Cron Integration

- Add to midnight cron:
  ```php
  Reactivation::expireOldCappedUsers();
  ```
- This handles the "Reactivation Window" expiration automatically

### Phase 4 Deliverables

- [ ] `Reactivation.php` with full reactivation logic
- [ ] Member reactivation UI/UX flow
- [ ] Admin reactivation management page
- [ ] Automatic window expiration via cron

---

## Phase 5: Member Dashboard & Monitoring UI

**Builds on: Phase 4** | **Estimated Effort: ~3-4 hours**

### 5.1 Enhanced Member Dashboard (`views/member/dashboard.php`)

**New Cap Status Widget** (prominent placement):

```
┌─────────────────────────────────────────┐
│  🛡️ Lifetime Income Cap                │
│  ━━━━━━━━━━━━━━━━━━━━━━━━               │
│  ₱24,500 earned / ₱30,000 cap           │
│  ████████████████████░░░░  81.7%        │
│  Status: Active ✅                      │
│  [View Details →]                       │
└─────────────────────────────────────────┘
```

**New DFI Widget**:

```
┌─────────────────────────────────────────┐
│  📅 Daily Fixed Income                  │
│  ━━━━━━━━━━━━━━━━━━━━━━━━               │
│  ₱100 / day × 45 of 90 days used         │
│  Next payout: Tomorrow, 12:00 AM        │
│  Total DFI earned: ₱4,500               │
│  [View DFI History →]                   │
└─────────────────────────────────────────┘
```

**Updated Pairing Cap Widget** (now shows cap context):

- Adds: "Lifetime cap: ₱24,500 / ₱30,000" below daily cap bar

### 5.2 New Member Pages

| Page               | Route                        | Description                                                  |
| ------------------ | ---------------------------- | ------------------------------------------------------------ |
| Cap Details        | `/?page=cap_status`          | Full cap breakdown: earned by type, remaining, history graph |
| DFI History        | `/?page=dfi_history`         | Day-by-day DFI log with cap status per payout                |
| Reactivation       | `/?page=reactivate`          | Phase 4 reactivation flow                                    |
| Earnings Breakdown | `/?page=earnings` (enhanced) | Now shows cap impact per commission                          |

### 5.3 Cap Status Page (`views/member/cap_status.php`)

**Visual Timeline**:

```
Joined: Jan 15, 2026
├── Pairing: ₱12,000
├── Direct Ref: ₱8,000
├── Indirect: ₱4,000
├── DFI: ₱500
│
⚠️ CAPPED: Feb 20, 2026 (reached ₱30,000)
│
🔄 REACTIVATED: Feb 21, 2026 (paid ₱10,000)
│
New cycle started: ₱0 / ₱30,000
```

**Cap Breakdown Table**:
| Type | Amount | % of Cap | Status |
|------|--------|----------|--------|
| Pairing | ₱12,000 | 40% | ✅ Credited |
| Direct Referral | ₱8,000 | 26.7% | ✅ Credited |
| Indirect Referral | ₱4,000 | 13.3% | ✅ Credited |
| Daily Fixed Income | ₱500 | 1.7% | ✅ Credited |
| Blocked by Cap | ₱5,500 | — | ⛔ Not Paid |

### 5.4 DFI History Page (`views/member/dfi_history.php`)

**Calendar View**:

```
March 2026
Su  Mo  Tu  We  Th  Fr  Sa
    1✅  2✅  3✅  4✅  5✅  6✅
7✅  8✅  9✅  10⛔ 11⛔ 12⛔ ...
```

- ✅ = DFI paid (hover shows amount)
- ⛔ = Cap reached, DFI blocked
- ⏸️ = Capped/paused (no day counted)
- 🔄 = Reactivation day

### Phase 5 Deliverables

- [ ] Enhanced member dashboard with cap & DFI widgets
- [ ] `cap_status.php` — full cap monitoring
- [ ] `dfi_history.php` — DFI monitoring with calendar
- [ ] Updated `earnings.php` with cap impact column

---

## Phase 6: Admin Monitoring, Reporting & Final Integration

**Builds on: Phase 5** | **Estimated Effort: ~3-4 hours**

### 6.1 Admin Dashboard Enhancements (`views/admin/dashboard.php`)

**New Stats Cards**:
| Card | Value | Color |
|------|-------|-------|
| Capped Members | `COUNT(*) WHERE cap_status='capped'` | Warning |
| Permanently Inactive | `COUNT(*) WHERE cap_status='perminact'` | Danger |
| Reactivation Revenue | `SUM(amount_paid) FROM reactivations` | Success |
| DFI Paid Today | `SUM(amount) FROM daily_fixed_income_log WHERE DATE(created_at)=CURDATE()` | Primary |

**New Admin Pages**:

| Page             | Route                        | Features                                                    |
| ---------------- | ---------------------------- | ----------------------------------------------------------- |
| Cap Monitoring   | `/?page=admin_cap_monitor`   | All members' cap status, filter by active/capped/perminact  |
| DFI Admin        | `/?page=admin_dfi`           | Global DFI stats, manual DFI trigger, DFI settings override |
| Reactivation Log | `/?page=admin_reactivations` | All reactivation history, revenue report                    |

### 6.2 User View Enhancement (`views/admin/user_view.php`)

**New Tab: "Cap & DFI"**:

- Lifetime cap progress bar
- DFI days used / remaining
- Reactivation history table
- Cap-triggered commissions list (showing what was blocked)

### 6.3 Settings Integration (`views/admin/settings.php`)

**New Settings Section: "Compensation Plan"**:

- Global DFI toggle: `dfi_enabled` (master switch)
- Default cap multiplier for new packages (can be overridden per package)
- Reactivation payment methods (e-wallet, external, both)

### 6.4 API Endpoints (`index.php` routes)

| Route                 | Controller::Method               | Auth   | Purpose                        |
| --------------------- | -------------------------------- | ------ | ------------------------------ |
| `api_cap_status`      | `MemberController::apiCapStatus` | member | JSON cap data for AJAX widgets |
| `api_dfi_status`      | `MemberController::apiDfiStatus` | member | JSON DFI data for AJAX widgets |
| `admin_cap_monitor`   | `AdminController::capMonitor`    | admin  | Cap monitoring page            |
| `admin_dfi`           | `AdminController::dfiAdmin`      | admin  | DFI admin page                 |
| `admin_reactivations` | `AdminController::reactivations` | admin  | Reactivation log               |
| `reactivate`          | `MemberController::reactivate`   | member | Reactivation page              |
| `do_reactivate`       | `MemberController::doReactivate` | member | Process reactivation           |
| `dfi_history`         | `MemberController::dfiHistory`   | member | DFI history page               |
| `cap_status`          | `MemberController::capStatus`    | member | Cap status page                |

### 6.5 Final Testing Checklist

| Test Case                              | Expected Result                                                                                |
| -------------------------------------- | ---------------------------------------------------------------------------------------------- |
| Member earns exactly at cap            | Cap triggered, status='capped', no more earnings                                               |
| Capped member in binary tree           | Skipped in pair counting, ancestors don't get their pairs                                      |
| DFI near cap                           | Partial DFI paid, cap triggered, remaining DFI blocked                                         |
| Reactivation within window             | Fee paid, cap resets, DFI days reset, fresh cycle                                              |
| Reactivation after window              | Cannot reactivate, status='perminact' permanently                                              |
| Direct referral to capped member       | Direct referral still credited to sponsor (immediate, cap-independent per simulator)           |
| Indirect referral through capped chain | Capped members in upline still get indirect bonus (it's about THEIR cap, not the new member's) |

### Phase 6 Deliverables

- [ ] All admin monitoring pages
- [ ] API endpoints for real-time widgets
- [ ] Final integration testing
- [ ] Documentation update

---

## Summary of New/Modified Files

### New Files (12)

| File                            | Phase | Purpose                |
| ------------------------------- | ----- | ---------------------- |
| `core/CapEngine.php`            | 2     | Central cap checking   |
| `core/DailyFixedIncome.php`     | 3     | DFI processing         |
| `core/Reactivation.php`         | 4     | Reactivation logic     |
| `views/member/reactivate.php`   | 4     | Reactivation UI        |
| `views/member/cap_status.php`   | 5     | Cap monitoring         |
| `views/member/dfi_history.php`  | 5     | DFI monitoring         |
| `views/admin/cap_monitor.php`   | 6     | Admin cap monitoring   |
| `views/admin/dfi_admin.php`     | 6     | Admin DFI control      |
| `views/admin/reactivations.php` | 6     | Admin reactivation log |
| `migrate_v2.php`                | 1     | Database migration     |
| `cron/daily_fixed_income.php`   | 3     | DFI cron (if separate) |

### Modified Files (15)

| File                                | Phases | Changes                                     |
| ----------------------------------- | ------ | ------------------------------------------- |
| `install.sql`                       | 1      | Schema updates                              |
| `models/Package.php`                | 1      | New fields                                  |
| `models/User.php`                   | 2      | Cap queries                                 |
| `core/Commission.php`               | 2      | Cap integration                             |
| `controllers/AdminController.php`   | 1,4,6  | Package CRUD, reactivation admin, DFI admin |
| `controllers/MemberController.php`  | 4,5,6  | Reactivation, cap/DFI pages, API            |
| `views/admin/packages.php`          | 1      | New form fields                             |
| `views/admin/dashboard.php`         | 6      | New stat cards                              |
| `views/admin/user_view.php`         | 6      | Cap/DFI tab                                 |
| `views/admin/settings.php`          | 6      | Global DFI toggle                           |
| `views/member/dashboard.php`        | 5      | Cap & DFI widgets                           |
| `views/member/earnings.php`         | 5      | Cap impact column                           |
| `views/partials/sidebar_member.php` | 5      | New nav items                               |
| `index.php`                         | 6      | New routes                                  |
| `cron/midnight_reset.php`           | 3,4    | DFI + reactivation expiration               |

---

## Key Design Decisions

1. **Cap applies to ALL earnings combined** (per simulator): pairing + direct + indirect + DFI all count toward the same lifetime cap
2. **Direct/Indirect referrals are immediate** and DO count toward the recipient's cap (not the new member's cap) — the simulator pays these immediately regardless of sponsor status
3. **Capped members are SKIPPED in binary pair counting** — their sub-tree exists but contributes no pairing bonuses to ancestors
4. **DFI days PAUSE when capped** — the duration clock does not advance while inactive, but resets on reactivation
5. **Reactivation is probabilistic in simulator, deterministic in system** — members choose to reactivate by paying the fee
6. **Reactivation resets ALL counters** — lifetime_earned, dfi_days_used, dfi_active — fresh cycle from zero

---

**Command "start phase 1" when ready to begin implementation.**
