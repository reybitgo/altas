# PV-Centered Architecture Adoption Plan

> Based on `sim/v9b2/index.html` — Binary MLM Simulator v9 PV Architecture  
> Focus: PV data model, flow, and conversion of existing bonuses to be PV-derived.  
> Out of scope: new compensation plans from the simulator (harvest, royalty, upline support, etc.).

---

## Executive Summary

Adopt **Points Value (PV)** as the universal internal currency of the system:

- **Package PV** = `entry_fee × package_pv_rate`
- **Product PV** = configured per product
- **Bonus conversion** = `pv_amount × percentage × pv_per_peso_rate`
- **Binary pairing** compares left/right **PV totals**, not member counts
- **Group PV** flows up the sponsor chain
- **Personal PV** gates repeat-purchase commissions

This plan converts the current system in **self-contained phases**. Each phase is deployable on its own and builds only on previously completed phases.

---

## Phase 1 — PV Foundation (Data Model & Admin UI)

**Goal:** Introduce PV columns and settings without changing any commission logic.

**Deliverables:**

1. **Database migrations**
   - `packages.package_pv_rate` DECIMAL(5,2) DEFAULT 100.00
   - `users.left_pv` DECIMAL(14,2) DEFAULT 0.00
   - `users.right_pv` DECIMAL(14,2) DEFAULT 0.00
   - `users.flushed_pv` DECIMAL(14,2) DEFAULT 0.00
   - `users.personal_pv` DECIMAL(14,2) DEFAULT 0.00
   - `users.group_pv` DECIMAL(14,2) DEFAULT 0.00
   - `users.total_package_pv` DECIMAL(14,2) DEFAULT 0.00
   - New system setting `pv_per_peso_rate` DECIMAL(10,4) DEFAULT 1.0000

2. **Package admin form (`views/admin/packages.php`)**
   - Add "Package PV Rate (%)" field
   - Show calculated "Package PV" preview = entry_fee × rate

3. **System settings (`views/admin/settings.php`)**
   - Add "PV per Peso Rate" global setting
   - Help text: "Pesos paid per 1 PV when converting bonuses"

4. **Package model (`models/Package.php`)**
   - Persist `package_pv_rate`
   - Helper: `Package::packagePv($packageId)` returns entry_fee × rate

5. **Backward compatibility**
   - All existing commission code remains untouched
   - New columns default to 0, so existing data is safe

**Acceptance Criteria:**
- Admin can save `package_pv_rate` per package
- Admin can save global `pv_per_peso_rate`
- No commission amounts change for existing members

---

## Phase 2 — Package PV Generation & Flow

**Goal:** When a member joins, generate Package PV and flow it through the network.

**Deliverables:**

1. **On member registration/activation**
   - Calculate `package_pv = entry_fee × package_pv_rate`
   - Store in `users.total_package_pv` for the new member

2. **Personal Sales PV**
   - Package purchase: sponsor receives the PV
   - Add `personal_pv` to the sponsor (not the buyer)
   - Record in a new `pv_transactions` ledger:
     ```sql
     CREATE TABLE pv_transactions (
       id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
       user_id INT UNSIGNED NOT NULL,
       type ENUM('package_personal','package_group','product_personal','product_group','binary_left','binary_right','binary_paired','binary_flushed') NOT NULL,
       amount DECIMAL(14,2) NOT NULL,
       source_user_id INT UNSIGNED NULL,
       source_type ENUM('registration','activation','repeat_purchase') NOT NULL,
       created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
     );
     ```

3. **Group Sales PV (sponsor chain)**
   - Walk from buyer's sponsor up to root
   - Add `package_pv` to each ancestor's `group_pv`
   - Insert `pv_transactions` rows of type `package_group`

4. **Commission engine hook (read-only for now)**
   - Add `Commission::processPackagePV($newUserId, $packageId)` called after registration
   - For Phase 2 this function only records PV; it does NOT pay anything

5. **Member dashboard preview**
   - Display "Package PV" and "Group PV" cards (read-only)

**Acceptance Criteria:**
- After registering a member, sponsor's `personal_pv` and ancestors' `group_pv` update correctly
- `pv_transactions` contains accurate audit rows
- Existing peso-based commissions still work exactly as before

---

## Phase 3 — Binary Engine Becomes PV-Based

**Goal:** Replace count-based pairing with PV-based pairing.

**Deliverables:**

1. **Data migration**
   - Convert existing `left_count/right_count` into approximate PV by multiplying each side by the member's own package PV
   - Backfill `users.left_pv` and `users.right_pv`
   - Keep legacy columns temporarily; mark deprecated

2. **Binary placement update (`Commission::processBinaryPlacement`)**
   - When a new member joins, add their `package_pv` to ancestors' `left_pv` or `right_pv` instead of incrementing counts
   - For product PV (Phase 5) also add product PV to binary sides

3. **Pairing logic refactor**
   - Current: pair when `min(left_count, right_count) - processed > 0`
   - New: pair when `min(left_pv, right_pv) - paired_pv > 0`
   - Use `users.paired_pv` (new column) instead of `pairs_paid` counts
   - Paired amount = `min(unpaired_left, unpaired_right)`

4. **Pairing bonus calculation**
   - Replace `pairing_bonus` fixed peso with `pairing_pv_pct` on packages
   - Bonus = `paired_pv × (pairing_pv_pct / 100) × pv_per_peso_rate`
   - Update `Commission::creditPairing()` to credit peso amount derived from PV

5. **Flush logic**
   - Track flushed PV in `users.flushed_pv`
   - Excess unpaired PV remains on the stronger leg (carries over)

6. **Midnight cron update**
   - Reset daily pair counters still applies, but now resets daily pair PV limit instead of count limit

7. **Admin package form**
   - Replace "Pairing Bonus (₱)" with "Pairing Bonus (% of paired PV)"
   - Keep old column as `legacy_pairing_bonus` or drop after data migration

8. **Views update**
   - Genealogy shows PV totals per leg
   - Cap status shows paired/flushed PV
   - Dashboard Pairing card shows PV-based earnings

**Acceptance Criteria:**
- New registrations increase `left_pv`/`right_pv`
- Pairing bonus is calculated from paired PV, not member count
- `pairs_paid` count columns are no longer used by commission engine
- Daily pair cap is enforced in PV terms

---

## Phase 4 — Direct & Indirect Referrals Become % of PV

**Goal:** Convert fixed-peso direct/indirect bonuses to percentages of Package PV.

**Deliverables:**

1. **Package schema changes**
   - `packages.direct_ref_pv_pct` DECIMAL(5,2) DEFAULT 0.00
   - `package_indirect_levels.pv_pct` DECIMAL(5,2) DEFAULT 0.00
   - Keep old `direct_ref_bonus` and `bonus` columns as legacy during transition

2. **Admin package form**
   - Replace "Direct Referral Bonus (₱)" with "Direct Referral (% of Package PV)"
   - Replace each indirect level "₱" input with "%" input
   - Show peso-equivalent preview using current entry fee and PV rate

3. **Commission engine updates**
   - `Commission::processDirectReferral()`:
     - `bonus_peso = package_pv × (direct_ref_pv_pct / 100) × pv_per_peso_rate`
   - `Commission::processIndirectReferral()`:
     - For each level: `bonus_peso = package_pv × (pv_pct / 100) × pv_per_peso_rate`
   - Keep existing cap/CD logic unchanged (it operates on the final peso amount)

4. **Data migration**
   - Convert legacy fixed-peso bonuses to equivalent percentages based on current package PV
   - Example: `direct_ref_pv_pct = (direct_ref_bonus / package_pv) × 100`

5. **Reports**
   - Earnings page shows "PV basis" and "Peso payout" for each commission

**Acceptance Criteria:**
- Direct/indirect bonuses are computed from package PV percentages
- Total payouts match (or are intentionally adjusted from) legacy fixed amounts
- Commission records store both PV basis and peso amount

---

## Phase 5 — Product / Repeat Purchase PV

**Goal:** Allow products to carry PV and flow that PV through the network.

**Deliverables:**

1. **Products table**
   ```sql
   CREATE TABLE products (
     id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
     name VARCHAR(120) NOT NULL,
     price DECIMAL(12,2) NOT NULL,
     pv_value DECIMAL(14,2) NOT NULL,
     status ENUM('active','inactive') DEFAULT 'active',
     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
   );
   ```

2. **Admin products UI**
   - CRUD for products
   - Field: "PV Value" (not a rate; fixed PV per product)

3. **Repeat purchase flow**
   - New table `repeat_purchases` linking member, product, quantity, total_pv, total_price, status
   - On approved purchase, call `Commission::processProductPV($purchaseId)`

4. **PV flow for product purchases**
   - Buyer receives `personal_pv` += product_pv
   - `group_pv` flows up sponsor chain
   - `left_pv` or `right_pv` flows up binary tree from buyer
   - Binary pairing is checked at each ancestor (same as Phase 3)

5. **Personal PV gate**
   - Add system setting `personal_pv_requirement`
   - For repeat-purchase indirect/PV bonuses, skip uplines whose `personal_pv` < requirement

6. **PV reset cron**
   - Monthly cron resets `users.personal_pv` to 0

**Acceptance Criteria:**
- Product purchase creates `repeat_purchases` record
- Product PV flows to sponsor chain and binary tree
- Repeat purchase can trigger binary pairing
- Personal PV gate filters repeat-purchase commissions

---

## Phase 6 — Cap & DFI PV Awareness

**Goal:** Make lifetime capping and DFI understand PV-based earnings.

**Deliverables:**

1. **Lifetime cap**
   - Cap limit remains `entry_fee × lifetime_cap_multiplier`
   - Continue to track peso `lifetime_earned`
   - No schema change needed; cap engine already operates on peso amounts
   - Add a secondary "PV cap" view: `cap_pv = cap_peso / pv_per_peso_rate`

2. **DFI optional PV basis**
   - Add `packages.dfi_pv_pct` DECIMAL(5,2)
   - If set, DFI = `package_pv × dfi_pv_pct × pv_per_peso_rate`
   - If 0, fall back to existing fixed `daily_fixed_income`

3. **Cap engine**
   - Ensure cap checks happen on final peso amounts from Phase 3/4 calculations
   - No structural change; the engine already caps peso earnings

4. **Reports**
   - Cap status page shows lifetime earned in both peso and PV

**Acceptance Criteria:**
- Lifetime cap still protects system correctly
- DFI can be configured as % of Package PV or fixed amount
- Cap status UI reflects PV-based earnings

---

## Phase 7 — PV Reporting & Member/Admin UI

**Goal:** Surface PV everywhere it matters.

**Deliverables:**

1. **Member dashboard**
   - Cards: Personal PV, Group PV, Left PV, Right PV, Paired PV, Flushed PV
   - Earnings history with PV basis column

2. **Admin reports**
   - Network PV report (total package PV, product PV, left/right PV per member)
   - PV transaction audit log
   - Export to CSV

3. **Genealogy**
   - Toggle between "Members" and "PV" tree views
   - Show PV contribution per node

4. **Package cards**
   - Display Package PV and bonus previews in peso and PV terms

5. **Cleanup (after all phases stable)**
   - Drop deprecated columns: `left_count`, `right_count`, `pairs_paid`, `pairs_flushed`, `pairing_bonus`, `direct_ref_bonus`, `package_indirect_levels.bonus`
   - Remove legacy commission code paths

**Acceptance Criteria:**
- Members can see their PV metrics
- Admins can audit PV flow
- All UI copy references PV where appropriate
- Legacy columns safely removed

---

## Cross-Cutting Concerns

### Database Migrations

Create one numbered migration per phase:

```
migrations/014_add_pv_columns.sql
migrations/015_add_pv_transactions.sql
migrations/016_add_paired_pv_and_pair_pct.sql
migrations/017_add_pv_based_referral_pcts.sql
migrations/018_add_products_table.sql
migrations/019_add_repeat_purchases.sql
migrations/020_add_dfi_pv_pct.sql
```

### Backward Compatibility

- Each phase must keep existing member earnings unchanged until explicitly migrated
- Add feature flags if necessary (e.g., `use_pv_based_pairing` setting)
- Only remove legacy columns in Phase 7

### Testing Strategy

For each phase:
1. Run unit tests on new helpers/models
2. Seed test members and verify PV math manually
3. Compare total system payouts before/after to catch regressions
4. Update QA testing guides

### Files Likely to Change

- `models/Package.php`
- `models/User.php`
- `core/Commission.php`
- `core/CapEngine.php`
- `core/DailyFixedIncome.php`
- `controllers/AdminController.php`
- `views/admin/packages.php`
- `views/admin/settings.php`
- `views/member/dashboard.php`
- `views/member/earnings.php`
- `views/member/genealogy.php`
- `views/member/cap_status.php`
- `cron/midnight_reset.php`

---

## Summary Table

| Phase | What Changes | Commission Impact | Member Impact |
|-------|--------------|-------------------|---------------|
| 1 | Schema + admin UI | None | None |
| 2 | Package PV tracking | None (PV only recorded) | New PV stats visible |
| 3 | Binary pairing uses PV | Pairing bonus now PV-derived | Earnings may change per package config |
| 4 | Direct/indirect % of PV | Direct/indirect bonuses PV-derived | Earnings depend on new percentages |
| 5 | Product/repeat purchase PV | Repeat purchase can trigger pairing/indirect | Product PV contributes to stats/commissions |
| 6 | Cap/DFI PV-aware | DFI can be % of PV | Cap/DFI displayed in PV terms |
| 7 | UI/UX + cleanup | Legacy columns removed | Full PV visibility |

---

## Recommended First Step

Start with **Phase 1**: add `package_pv_rate`, `pv_per_peso_rate`, and the user PV columns. This is a safe schema-only change that lets the team begin testing PV previews in the admin without affecting live commissions.
