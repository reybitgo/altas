I'll analyze the simulator and existing codebase thoroughly, then produce a complete phased implementation plan.

---

## PHASE 0: ANALYSIS & FOUNDATION

### Current System Architecture (from uploaded files)

| Component               | File                                  | Current State                                                                                                                                                                                                   |
| ----------------------- | ------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Database Schema**     | `install.sql`                         | Users table has `left_count`, `right_count`, `pairs_paid`, `pairs_flushed`, `pairs_paid_today`, `ewallet_balance`, `status` (active/suspended/pending). No cap tracking, no reactivation fields, no DFI fields. |
| **Packages**            | `Package.php`, `packages.php`         | `entry_fee`, `pairing_bonus`, `daily_pair_cap`, `direct_ref_bonus`, `indirect_levels[1-10]`. No `income_cap`, `reactivation_fee`, `reactivation_window`, `daily_fixed_income`, `daily_fixed_days`.              |
| **Commission Engine**   | `Commission.php`                      | `processBinaryPlacement()` fires pairing bonuses with daily cap flush. `processDirectReferral()` and `processIndirectReferral()` fire immediately to e-wallet. No cap checks anywhere.                          |
| **User Model**          | `User.php`                            | `register()` calls commission processors. No cap enforcement. `todayPairingStatus()` returns daily cap status only.                                                                                             |
| **Member Dashboard**    | `dashboard(1).php`                    | Shows e-wallet, pairing earnings, direct/indirect totals, daily pairing cap widget, binary legs. No DFI display, no lifetime cap progress.                                                                      |
| **Admin Dashboard**     | `dashboard.php`                       | Member counts, code revenue, pending payouts, total paid, commissions paid, e-wallet holdings. No cap/reactivation metrics.                                                                                     |
| **E-Wallet**            | `Ewallet.php`                         | Simple credit/debit with ledger. No cap-aware logic.                                                                                                                                                            |
| **Payouts**             | `Payout.php`                          | Request/approve/reject/complete. No cap consideration.                                                                                                                                                          |
| **Settings**            | `settings.php`, `AdminController.php` | Site name, fees, min payout, maintenance, USDT gas. No package-level cap/DFI settings.                                                                                                                          |
| **Simulator Reference** | `mlm_binary_simulator_v5 (2).html`    | Defines: `incap = 3× entry fee` (all income combined), reactivation fee/window/rate%, DFI amount/duration, probabilistic reactivation day-after-capping, DFI pauses while inactive, cap resets on reactivation. |

### Key Gaps Identified

1. **No lifetime earnings tracking per cycle** — need `cycle_earned`, `cycle_started_at`, `capped_at`, `reactivation_count`
2. **No DFI infrastructure** — need `daily_fixed_income`, `max_dfi_days`, `dfi_days_used`, `last_dfi_date`
3. **No reactivation workflow** — need UI for admin-triggered or member self-reactivation with payment
4. **Commission bypass** — all 3 commission types must check cap before crediting
5. **Binary placement skip** — inactive/perminact members must be excluded from ancestor pair counting
6. **Package settings expansion** — 4 new fields needed

---

## COMPLETE PHASED IMPLEMENTATION PLAN

---

### **PHASE 1: DATABASE SCHEMA & PACKAGE MODEL**

_Self-contained: All SQL changes + Package.php updates. No UI changes yet._

**1.1 Schema Changes (`install.sql` modifications)**

```sql
-- Add to packages table:
ALTER TABLE packages ADD COLUMN income_cap_multiplier DECIMAL(3,1) NOT NULL DEFAULT 3.0
    COMMENT 'Cap = entry_fee * this multiplier';
ALTER TABLE packages ADD COLUMN reactivation_fee DECIMAL(12,2) NOT NULL DEFAULT 0.00;
ALTER TABLE packages ADD COLUMN reactivation_window TINYINT UNSIGNED NOT NULL DEFAULT 15
    COMMENT 'Days to reactivate before permanent deactivation';
ALTER TABLE packages ADD COLUMN daily_fixed_income DECIMAL(12,2) NOT NULL DEFAULT 0.00;
ALTER TABLE packages ADD COLUMN daily_fixed_days SMALLINT UNSIGNED NOT NULL DEFAULT 90;

-- Add to users table:
ALTER TABLE users ADD COLUMN cycle_earned DECIMAL(14,2) NOT NULL DEFAULT 0.00
    COMMENT 'Total earned in current active cycle (resets on reactivation)';
ALTER TABLE users ADD COLUMN cycle_started_at TIMESTAMP NULL
    COMMENT 'When current earning cycle began';
ALTER TABLE users ADD COLUMN capped_at TIMESTAMP NULL
    COMMENT 'When user hit income cap';
ALTER TABLE users ADD COLUMN reactivation_count INT UNSIGNED NOT NULL DEFAULT 0;
ALTER TABLE users ADD COLUMN dfi_days_used SMALLINT UNSIGNED NOT NULL DEFAULT 0
    COMMENT 'Days of fixed income consumed in current cycle';
ALTER TABLE users ADD COLUMN last_dfi_date DATE NULL;
ALTER TABLE users ADD COLUMN next_dfi_date DATE NULL
    COMMENT 'Next scheduled DFI payout date';

-- Modify users.status enum:
ALTER TABLE users MODIFY COLUMN status ENUM('active','suspended','pending','capped','perminact')
    NOT NULL DEFAULT 'active';
-- 'capped' = hit limit, within reactivation window
-- 'perminact' = missed window, permanently inactive

-- New table: reactivation_history (audit + payment tracking)
CREATE TABLE reactivation_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    amount_paid DECIMAL(12,2) NOT NULL,
    previous_cycle_earned DECIMAL(14,2) NOT NULL,
    new_cycle_started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_by INT UNSIGNED NULL COMMENT 'admin ID, or NULL for self-service',
    payment_method ENUM('ewallet','gcash','maya','usdt','manual') DEFAULT 'ewallet',
    status ENUM('completed','pending','failed') DEFAULT 'completed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user (user_id, created_at)
) ENGINE=InnoDB;

-- New table: daily_fixed_income_log (DFI audit trail)
CREATE TABLE daily_fixed_income_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    day_number SMALLINT UNSIGNED NOT NULL COMMENT 'Which day in the cycle (1-based)',
    cap_remaining_before DECIMAL(14,2) NOT NULL,
    cap_remaining_after DECIMAL(14,2) NOT NULL,
    status ENUM('paid','blocked_by_cap','blocked_by_inactive','blocked_by_limit') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_user_date (user_id, created_at)
) ENGINE=InnoDB;

-- Indexes for performance
ALTER TABLE users ADD INDEX idx_status_capped (status, capped_at);
ALTER TABLE users ADD INDEX idx_cycle_dates (cycle_started_at, next_dfi_date);
```

**1.2 Package Model Updates (`Package.php`)**

- Add new fields to `find()`, `all()`, `save()`, `withLevels()`
- Update `save()` to handle `indirect_levels` + 4 new cap/DFI fields
- Add validation: `income_cap_multiplier >= 1.0`, `reactivation_window >= 1`, `daily_fixed_days >= 1`

**1.3 Seeder Update**

- Update default 'Starter' package: `income_cap_multiplier=3.0`, `reactivation_fee=10000`, `reactivation_window=15`, `daily_fixed_income=100`, `daily_fixed_days=90`

**Deliverable:** Running `install.sql` (or migration script) creates all new tables/columns. Package CRUD handles new fields. Admin can create packages with cap/DFI settings via existing package form.

---

### **PHASE 2: CAP-AWARE COMMISSION ENGINE (Backend Core)**

_Self-contained: All commission logic rewritten with cap enforcement. No new UI pages yet, existing pages continue to work._

**2.1 Core Cap Logic Class (`core/CapEngine.php` — new file)**

```php
class CapEngine {
    /**
     * Check if user can earn more. Returns array:
     * ['can_earn' => bool, 'cap' => float, 'remaining' => float, 'status' => string]
     */
    public static function checkCapacity(int $userId): array;

    /**
     * Attempt to credit amount. Returns ['credited' => float, 'blocked' => float, 'new_status' => string]
     * If credit would exceed cap, partial credit up to cap, then auto-set status='capped'
     */
    public static function attemptCredit(int $userId, float $amount, string $type, int $sourceId, string $description): array;

    /**
     * Called when user hits cap — sets status='capped', capped_at=NOW(), triggers notifications
     */
    public static function triggerCap(int $userId): void;

    /**
     * Get user's current cycle stats for display
     */
    public static function cycleStats(int $userId): array;
}
```

**2.2 Commission.php Refactoring**

- `processBinaryPlacement()`: Before calling `creditPairing()`, call `CapEngine::checkCapacity()`. If `remaining < bonus`, skip pair (don't even increment `pairs_paid` — the pair is "cap-flushed", distinct from "daily-flushed"). If partial room, credit partial and cap.
- `processDirectReferral()`: Same cap check. If capped, record as "blocked_by_cap" in commissions table with `status='blocked'` (new enum value).
- `processIndirectReferral()`: Same for each level. If ancestor is capped/perminact, skip entirely (no commission, no ledger entry).
- `creditPairing()`: Replace direct `Ewallet::credit()` with `CapEngine::attemptCredit()`.

**2.3 Daily Fixed Income Cron/Processor (`core/DailyFixedIncome.php` — new file)**

```php
class DailyFixedIncome {
    /**
     * Run daily at midnight (or via admin manual trigger). For each active user:
     * 1. Check if DFI enabled in their package
     * 2. Check if dfi_days_used < daily_fixed_days
     * 3. Check cap remaining via CapEngine
     * 4. If all pass: credit min(dfi_amount, cap_remaining), increment dfi_days_used, set last_dfi_date=today, next_dfi_date=tomorrow
     * 5. If cap would be hit: partial credit, trigger cap
     * 6. If inactive: skip, don't increment days (clock pauses)
     * 7. Log to daily_fixed_income_log
     */
    public static function processDaily(): array; // returns stats for admin notification

    /**
     * Get DFI status for a user (for dashboard display)
     */
    public static function userStatus(int $userId): array;
}
```

**2.4 User Registration Update (`User.php::register()`)**

- After inserting user, set `cycle_started_at = NOW()`, initialize `cycle_earned = 0`, `dfi_days_used = 0`, `next_dfi_date = DATE(NOW()) + INTERVAL 1 DAY` (or today if DFI pays on day 0).

**2.5 E-Wallet Integration**

- `Ewallet::credit()` gets optional `$checkCap = true` parameter. When called from commission engine, cap already checked. When called from other places (e.g., manual admin credit), cap check enforced.

**Deliverable:** All existing commission flows respect caps. DFI runs correctly via cron. Users can hit caps and stop earning. No UI changes yet — member dashboard may show "capped" status but no special handling.

---

### **PHASE 3: REACTIVATION SYSTEM (Backend + Member UI)**

_Self-contained: Full reactivation workflow. Members can self-reactivate. Admins can reactivate members. Reactivation history tracked._

**3.1 Reactivation Logic (`core/Reactivation.php`)**

```php
class Reactivation {
    /**
     * Can this user reactivate? Checks:
     * - status is 'capped' (not perminact, not active, not suspended)
     * - within reactivation_window days of capped_at
     * - has sufficient balance if self-service (reactivation_fee)
     */
    public static function canReactivate(int $userId): array; // ['ok'=>bool, 'reason'=>string, 'fee'=>float, 'deadline'=>string]

    /**
     * Perform reactivation. Deducts fee from e-wallet (or records external payment), resets:
     * - cycle_earned = 0
     * - cycle_started_at = NOW()
     * - dfi_days_used = 0
     * - last_dfi_date = NULL, next_dfi_date = DATE(NOW()) + INTERVAL 1 DAY
     * - status = 'active'
     * - reactivation_count += 1
     * - pairs_paid, pairs_flushed, pairs_paid_today = 0 (fresh start — but left_count/right_count preserved for tree structure)
     * Logs to reactivation_history
     */
    public static function reactivate(int $userId, string $paymentMethod = 'ewallet', ?int $adminId = null): array;

    /**
     * Cron: Check for capped users past window → status='perminact'
     */
    public static function expireWindows(): int; // returns count expired
}
```

**3.2 Member Reactivation UI (`views/member/reactivate.php` — new page)**

- Accessible when `status='capped'`
- Shows: "Your account reached the income cap of ₱X", "Cycle earned: ₱Y", "Reactivation fee: ₱Z", "Deadline: DATE"
- Progress bar: days remaining in window
- Button: "Reactivate Now (₱Z from E-Wallet)" — disabled if insufficient balance
- Alternative: "Pay via GCash/Maya/USDT" — generates payment instructions (manual process, admin verifies)
- After reactivation: Success message, redirect to dashboard

**3.3 Admin Reactivation UI (integrated into `user_view.php`)**

- New card on member view: "Income Cap & Reactivation"
- Shows: Current cycle earned / cap, reactivation count, DFI days used, status timeline
- Admin button: "Reactivate Member" (waives fee or charges manually)
- Admin button: "Extend Reactivation Window" (emergency override)
- Shows reactivation history table

**3.4 Dashboard Updates for Capped State**

- `dashboard(1).php`: If `status='capped'`, show prominent banner with reactivation CTA instead of normal stats
- `sidebar_member.php`: Add "⚠️ Reactivate" nav item when capped (highlighted in warning color)

**3.5 Cron Jobs**

- Midnight cron expanded: Run `DailyFixedIncome::processDaily()` then `Reactivation::expireWindows()`

**Deliverable:** Complete reactivation flow. Capped members see reactivation page. Admins can manage reactivations. Expired windows become permanent.

---

### **PHASE 4: DAILY FIXED INCOME MEMBER DASHBOARD (UI/UX)**

_Self-contained: Rich DFI monitoring for members. Builds on Phase 2+3 backend._

**4.1 Member DFI Widget (`views/member/dashboard.php` enhancement)**

New card row:

- **"Daily Fixed Income"** card showing:
  - Current DFI rate (₱X/day)
  - Days used / total days (progress bar)
  - Next payout date
  - Total DFI earned this cycle
  - Status: "Active and earning" / "Paused — account capped" / "Completed — all days used" / "Permanently inactive"

**4.2 Dedicated DFI Page (`views/member/dfi.php` — new page)**

- Full DFI calendar/schedule view
- Table: Date | Day # | Amount | Status (Paid/Blocked/Upcoming) | Cap Remaining Before
- Visual timeline showing cycle progress
- Projection: "If you stay active, you'll earn ₱X more over Y days"
- History link to past cycles (via reactivation_history)

**4.3 Sidebar Integration**

- Add "📅 Fixed Income" nav item under "Account" section
- Badge showing days remaining if low

**4.4 Mobile Optimization**

- DFI widget collapses to compact view on mobile
- Touch-friendly calendar

**Deliverable:** Members have full visibility into DFI earnings, projections, and history.

---

### **PHASE 5: ADMIN CAP/DFI MANAGEMENT PANEL**

_Self-contained: Complete admin oversight. Builds on all previous phases._

**5.1 Admin Dashboard Enhancements (`views/admin/dashboard.php`)**

New stat cards:

- **Members Capped Today** (with link to capped members list)
- **Pending Reactivations** (within window, not yet reactivated)
- **Permanently Inactive** (lost members)
- **DFI Payouts Today** (total amount, count)
- **Cap Savings** (total commissions blocked by cap — company protection metric)

**5.2 Capped Members List (`views/admin/capped.php` — new page)**

- Filterable table: Username | Package | Cap | Cycle Earned | Capped At | Window Deadline | Status | Actions
- Bulk actions: Extend window, Force reactivate, Mark perminact
- Export to CSV

**5.3 DFI Administration (`views/admin/dfi_admin.php` — new page)**

- Run DFI manually (for testing/corrections)
- Adjust individual user's DFI days (admin override)
- View DFI log with filters
- DFI settings summary across packages

**5.4 Package Settings Enhancement (`views/admin/packages.php`)**

Expanded form with new sections:

- **Income Capping**: Cap multiplier (× entry fee), Reactivation fee, Reactivation window (days)
- **Daily Fixed Income**: Amount per day, Maximum days
- Visual calculator: "With these settings, member can earn max ₱X from DFI + ₱Y from pairing before capping"

**5.5 User View Enhancements (`views/admin/user_view.php`)**

New tab: **"Cap & DFI Cycle"**

- Cycle timeline visualization
- Earnings composition pie chart (pairing vs direct vs indirect vs DFI)
- Cap progress bar with color zones (green <50%, yellow 50-80%, red 80-100%, capped)
- DFI calendar for current cycle

**Deliverable:** Admins have complete control and visibility over cap/DFI system.

---

### **PHASE 6: SIMULATOR INTEGRATION & REPORTING**

_Self-contained: Business intelligence. Optional but valuable._

**6.1 Company Profit Simulator Integration**

- Embed/adapt `mlm_binary_simulator_v5 (2).html` logic into admin panel
- Live projection using actual package settings + member growth rate
- "What-if" scenario: Change cap/DFI settings, see projected impact on company profitability

**6.2 Cap Impact Reports**

- Monthly report: How much did caps save the company? (blocked commissions)
- Reactivation revenue report: Fees collected from reactivations
- DFI cost analysis: Total DFI paid out vs. entry fee revenue

**6.3 Member-Facing Transparency**

- Optional: Show member their "fair cap" progress in friendly terms
- "You've earned 73% of your maximum cycle earnings"

---

### **PHASE 7: TESTING, EDGE CASES & POLISH**

_Self-contained: Hardening and refinement._

**7.1 Edge Case Handling**

- Member hits cap mid-pair: Partial pair credit, proper accounting
- Multiple commissions same second: Race condition protection (DB row locking)
- Reactivation during DFI processing: Transaction isolation
- Package change mid-cycle: Handle gracefully (rare but possible)

**7.2 Notification System**

- Email/push when member approaches cap (80%, 90%, 95%)
- Email when cap hit with reactivation instructions
- Email when window about to expire (24h, 48h, 72h warnings)
- Admin alert when many members capping simultaneously

**7.3 API Endpoints**

- `GET /?page=api_cap_status` — JSON for member app
- `POST /?page=api_reactivate` — Mobile app reactivation

**7.4 Performance**

- Cap checks must be fast (indexed queries)
- DFI batch processing efficient (thousands of members)

---

## IMPLEMENTATION ORDER SUMMARY

| Phase | Focus                     | Files Modified/Created                                                | Risk Level                  |
| ----- | ------------------------- | --------------------------------------------------------------------- | --------------------------- |
| 1     | Schema + Package settings | `install.sql`, `Package.php`, `packages.php`                          | Low                         |
| 2     | Cap engine + DFI backend  | `CapEngine.php`, `DailyFixedIncome.php`, `Commission.php`, `User.php` | **High** — core money logic |
| 3     | Reactivation workflow     | `Reactivation.php`, `reactivate.php`, `user_view.php`, cron           | Medium                      |
| 4     | DFI member UI             | `dashboard(1).php`, `dfi.php`, `sidebar_member.php`                   | Low                         |
| 5     | Admin management          | `dashboard.php`, `capped.php`, `dfi_admin.php`, `packages.php`        | Low                         |
| 6     | Simulator + BI            | New reporting pages, simulator adaptation                             | Low                         |
| 7     | Hardening                 | Tests, notifications, edge cases                                      | Medium                      |

---

## CRITICAL DESIGN DECISIONS (documented for consistency)

| Decision                                             | Rationale                                               |
| ---------------------------------------------------- | ------------------------------------------------------- |
| **Cap = multiplier × entry_fee**                     | Matches simulator exactly; scales with package value    |
| **Cycle reset on reactivation**                      | Per simulator: "fresh start" incentivizes reactivation  |
| **DFI days pause while inactive**                    | Per simulator: clock stops, doesn't consume days        |
| **left_count/right_count preserved on reactivation** | Tree structure must remain; only earning counters reset |
| **Cap check BEFORE e-wallet credit**                 | Prevents overpayment; atomic with status change         |
| **Blocked commissions logged**                       | Audit trail for "cap savings" reporting                 |
| **Self-service reactivation from e-wallet**          | Frictionless; alternative manual payment for cash users |

---

**Ready for Phase 1 command when you are.**
