# QA Tester Guide — PV-Centered System (Complete)

**Version:** v1.0  
**Date:** 2026-05-28  
**System:** Altas Farm MLM Binary System  
**Scope:** Phases 1–7 of the PV-centered architecture (all implemented)

---

## Table of Contents

1. [What is PV-Centered Testing?](#1-what-is-pv-centered-testing)
2. [Prerequisites](#2-prerequisites)
3. [Test Environment Setup](#3-test-environment-setup)
4. [Quick Formula Reference](#4-quick-formula-reference)
5. [Test Cases](#5-test-cases)
   - 5.1 [Global PV Settings](#51-global-pv-settings)
   - 5.2 [Package PV Configuration](#52-package-pv-configuration)
   - 5.3 [Binary Pairing (PV-Based)](#53-binary-pairing-pv-based)
   - 5.4 [Direct & Indirect Referrals](#54-direct--indirect-referrals)
   - 5.5 [Lifetime Cap with PV Earnings](#55-lifetime-cap-with-pv-earnings)
   - 5.6 [Daily Fixed Income (DFI)](#56-daily-fixed-income-dfi)
   - 5.7 [Products & Repeat Purchases](#57-products--repeat-purchases)
   - 5.8 [Personal PV Gate](#58-personal-pv-gate)
   - 5.9 [Midnight Cron & Resets](#59-midnight-cron--resets)
   - 5.10 [Frontend Copy & Reports](#510-frontend-copy--reports)
   - 5.11 [Regression & Legacy Cleanup](#511-regression--legacy-cleanup)
6. [Bug Reporting Template](#6-bug-reporting-template)
7. [Pass/Fail Criteria](#7-passfail-criteria)
8. [FAQ](#8-faq)
9. [Quick Reference Card](#9-quick-reference-card)

---

## 1. What is PV-Centered Testing?

The system now uses **Points Value (PV)** as its internal currency for all commissions:

- **Package PV** = `entry_fee × (package_pv_rate / 100)`
- **Pairing, direct, indirect, and DFI bonuses** are calculated from PV, then converted to pesos using the global `pv_per_peso_rate`.
- **Product purchases** generate product PV that flows to the buyer's personal PV, up the sponsor chain as group PV, and up the binary tree as left/right PV.
- **Lifetime capping** still protects the system in peso terms, but cap/DFI views also show PV equivalents.

This guide walks a complete beginner through verifying every PV behavior end-to-end.

### What's New vs. the Old Fixed-Peso System

| Component             | Old Way                    | New Way                                           | Where to Test                               |
| --------------------- | -------------------------- | ------------------------------------------------- | ------------------------------------------- |
| **Binary pairing**    | Counted left/right members | Adds package/product PV to legs                   | Register members / approve repeat purchases |
| **Pairing bonus**     | Fixed ₱ per pair           | `% of paired PV × pv_per_peso_rate`               | Admin package form, member dashboard        |
| **Direct referral**   | Fixed ₱ amount             | `% of package PV × pv_per_peso_rate`              | Register under a sponsor                    |
| **Indirect/unilevel** | Fixed ₱ per level          | `% of package PV per level`                       | Deep sponsor chains                         |
| **DFI**               | Fixed ₱/day                | Fixed ₱/day OR `% of package PV`                  | Admin package form, DFI history             |
| **Product purchases** | No PV impact               | Personal/group/binary PV flow                     | Repeat purchase approval                    |
| **Personal PV gate**  | Not used                   | Blocks repeat-purchase PV flow for low-PV uplines | Settings + repeat purchase                  |

### What This Guide Covers

- ✅ PV settings and conversion math
- ✅ Package configuration with live PV previews
- ✅ PV-based binary pairing and caps
- ✅ PV-based direct/indirect referrals
- ✅ Cap engine with PV-derived peso amounts
- ✅ Fixed and PV-based DFI
- ✅ Product/repeat-purchase PV flow
- ✅ Personal PV requirement gate
- ✅ Midnight cron behavior
- ✅ Frontend copy and admin/member reports
- ✅ Legacy column cleanup

---

## 2. Prerequisites

### Required Access

- ✅ Admin login credentials (`admin` / `Admin@1234` by default — change immediately in production)
- ✅ Database access (phpMyAdmin, MySQL CLI, or similar)
- ✅ Server/terminal access to run cron manually
- ✅ At least one active member account for testing

### Required Knowledge

- ✅ How to run SQL `SELECT`, `UPDATE`, and `INSERT`
- ✅ How to read PHP error logs
- ✅ How to use browser DevTools (F12 → Console / Network)
- ✅ Basic understanding of sponsor chain vs. binary tree

### Required Tools

| Tool                   | Purpose                | Free? |
| ---------------------- | ---------------------- | ----- |
| Web browser            | UI testing             | Yes   |
| Browser DevTools       | Check JS/PHP errors    | Yes   |
| phpMyAdmin / MySQL CLI | Verify database state  | Yes   |
| SSH / terminal         | Run cron manually      | Yes   |
| Text editor            | Inspect code if needed | Yes   |

### System Must Be Ready

- ✅ All migrations `001` through `022` have been applied
- ✅ `core/Commission.php`, `core/CapEngine.php`, `core/DailyFixedIncome.php` are deployed
- ✅ `models/Product.php` and `models/RepeatPurchase.php` are deployed
- ✅ `pv_per_peso_rate` setting exists (default `1.0000`)

---

## 3. Test Environment Setup

### Step 1: Backup the Database (CRITICAL)

```bash
mysqldump -u your_username -p u938213108_altas_db > backup_before_pv_testing.sql
```

### Step 2: Verify PV Files Are Deployed

Run these commands on the server:

```bash
# Required core files
ls -la /path/to/site/core/Commission.php
ls -la /path/to/site/core/CapEngine.php
ls -la /path/to/site/core/DailyFixedIncome.php
ls -la /path/to/site/models/Product.php
ls -la /path/to/site/models/RepeatPurchase.php

# Verify PV helpers exist in Commission.php
grep -n "processBinaryPlacement\|processProductPV\|recordPvTransaction" /path/to/site/core/Commission.php

# Verify DFI PV support
grep -n "dfi_pv_pct\|dailyFixedIncome" /path/to/site/models/Package.php
```

**Expected Result:** All files exist and the grep commands return matching lines.

### Step 3: Prepare Test Members

If you don't have enough members, register at least:

- 1 admin (default exists)
- 1 sponsor member (Member A)
- 1 downline member under Member A (Member B)
- 1 downline member under Member B (Member C)

This gives you a sponsor chain of at least 3 levels and a binary tree of at least 2 levels.

Quick SQL to see current members:

```sql
SELECT id, username, role, sponsor_id, binary_parent_id, binary_position, status, cap_status
FROM users
WHERE role = 'member'
ORDER BY id;
```

### Step 4: Note the Default Package Settings

```sql
SELECT id, name, entry_fee, package_pv_rate, binary_pv_pct, daily_pair_pv_cap,
       direct_ref_pv_pct, lifetime_cap_multiplier, daily_fixed_income,
       daily_fixed_income_days, dfi_pv_pct
FROM packages
WHERE status = 'active';
```

---

## 4. Quick Formula Reference

Use this table to predict any test outcome.

| Calculation                      | Formula                                                     |
| -------------------------------- | ----------------------------------------------------------- |
| **Package PV**                   | `entry_fee × (package_pv_rate / 100)`                       |
| **Pairing bonus (peso)**         | `paired_pv × pv_per_peso_rate`                              |
| **Direct referral (peso)**       | `package_pv × (direct_ref_pv_pct / 100) × pv_per_peso_rate` |
| **Indirect level N (peso)**      | `package_pv × (level_n_pv_pct / 100) × pv_per_peso_rate`    |
| **DFI — fixed mode (peso)**      | `daily_fixed_income` (used when `dfi_pv_pct = 0`)           |
| **DFI — PV mode (peso)**         | `package_pv × (dfi_pv_pct / 100) × pv_per_peso_rate`        |
| **Lifetime cap (peso)**          | `entry_fee × lifetime_cap_multiplier`                       |
| **Lifetime cap (PV equivalent)** | `cap_peso / pv_per_peso_rate`                               |

---

## 5. Test Cases

### 5.1 Global PV Settings

#### Test 1.1: Set and Verify `pv_per_peso_rate`

**Purpose:** Confirm the global PV conversion rate is saved and used.

**Setup:**

1. Log in as admin.
2. Go to **Admin → Settings**.

**Steps:**

1. Find the **PV per Peso Rate** field.
2. Change it to `0.5000` (meaning 1 PV = ₱0.50).
3. Save.
4. Check the same field again.

**Expected Result:**

- The field shows `0.5000` after save.
- No PHP errors or warnings.

**Verify in database:**

```sql
SELECT value FROM settings WHERE key_name = 'pv_per_peso_rate';
-- Expected: 0.5000
```

**Cleanup:** Set it back to `1.0000` if you want simpler mental math for later tests.

---

### 5.2 Package PV Configuration

#### Test 2.1: Package PV Basis Preview

**Purpose:** Confirm the admin package form shows the correct package PV and peso previews.

**Setup:**

1. Log in as admin.
2. Go to **Admin → Packages**.
3. Click **New Package** or **Edit** an existing package.

**Steps:**

1. Set **Entry Fee** to `10000.00`.
2. Set **Package PV Rate** to `100.00`.
3. Observe the live preview under the PV rate field.

**Expected Result:**

- Preview shows **Package PV basis = ₱10,000.00**.

**Why:** `10000 × (100 / 100) = 10000 PV`.

---

#### Test 2.2: Pairing Bonus Preview

**Purpose:** Confirm pairing % converts to the expected peso amount in the form.

**Steps:**

1. With entry fee `10000.00` and package PV rate `100.00`, set **Pairing Bonus (% of paired PV)** to `20.00`.
2. Set `pv_per_peso_rate` to `1.0000` first for easy checking.
3. Observe the preview.

**Expected Result:**

- Preview shows **≈ ₱2,000.00/PV**.

**Why:** `10000 package PV × 20% × 1.0 = ₱2000` per package-PV paired.

---

#### Test 2.3: Direct Referral Preview

**Steps:**

1. Set **Direct Referral (% of Package PV)** to `5.00`.
2. Observe the preview.

**Expected Result:**

- Preview shows **≈ ₱500.00 per direct recruit**.

**Why:** `10000 × 5% × 1.0 = ₱500`.

---

#### Test 2.4: DFI PV Mode Preview

**Steps:**

1. Set **DFI (% of Package PV)** to `5.00`.
2. Leave fixed daily income at `0`.
3. Set **Max DFI Days** to `90`.
4. Save the package.

**Expected Result:**

- Package table shows the computed DFI amount (e.g., ₱500/day if PV rate = 1.0).
- The label says something like “5% of PV · 90 days”.

**Verify:**

```sql
SELECT name, dfi_pv_pct, daily_fixed_income, daily_fixed_income_days
FROM packages
ORDER BY id DESC
LIMIT 1;
```

---

### 5.3 Binary Pairing (PV-Based)

#### Test 3.1: New Registration Adds Package PV to Binary Legs

**Purpose:** Confirm joining a member adds package PV (not just a count) to ancestor legs.

**Setup:**

```sql
-- Pick an active member who will be the binary parent
SELECT id, username, left_pv, right_pv
FROM users
WHERE role = 'member' AND status = 'active'
LIMIT 1;
```

**Steps:**

1. Note the binary parent's `id` and current `left_pv` / `right_pv`.
2. Register a new member under that parent in the **left** leg.
3. Use the default active package.

**Expected Result:**

- The parent's `left_pv` increases by the new member's **package PV** (not by 1).
- The parent's `left_count` also increases by 1 (legacy counter, kept for reference).

**Verify:**

```sql
-- Replace [parent_id] with the actual parent ID
SELECT left_pv, right_pv, left_count, right_count
FROM users
WHERE id = [parent_id];
```

**Example:** If the package PV rate is 100% and entry fee is ₱10,000, `left_pv` should increase by `10000.00`.

---

#### Test 3.2: Pairing Bonus Fires on Matched PV

**Purpose:** Confirm a pair forms when left and right PV match, and the bonus is peso-derived.

**Setup:**

1. Find or create a member with one member in the left leg and one in the right leg.
2. Both new members should have the same package PV.

**Steps:**

1. Note the sponsor/binary parent member's `id`.
2. Register the second leg member if needed.

**Expected Result:**

- The ancestor's `paired_pv` increases by the matched PV amount.
- The ancestor's e-wallet receives a pairing bonus in pesos.
- `pairs_paid` legacy counter also increases (kept for reference).

**Verify:**

```sql
-- Replace [ancestor_id]
SELECT left_pv, right_pv, paired_pv, pairs_paid, lifetime_earned
FROM users
WHERE id = [ancestor_id];

-- Check the commission record
SELECT type, amount, cap_deduction, description
FROM commissions
WHERE user_id = [ancestor_id] AND type = 'pairing'
ORDER BY created_at DESC
LIMIT 1;
```

**Expected math:** If both legs received `10000` PV and `pv_per_peso_rate = 1.0`, the bonus is `10000 × 1.0 = ₱10000.00`.

---

#### Test 3.3: Daily Pair PV Cap

**Purpose:** Confirm the daily cap is enforced in PV terms, not count terms.

**Setup:**

1. Find an ancestor member.
2. Check the active package's `daily_pair_pv_cap`.

```sql
SELECT u.id, u.username, p.daily_pair_pv_cap, u.paired_pv_today
FROM users u
JOIN packages p ON p.id = u.package_id
WHERE u.id = [ancestor_id];
```

**Steps:**

1. Register enough downline members to exceed the daily pair PV cap.
   - For example, if cap is `30000` PV and each package is `10000` PV, register enough pairs to exceed `30000` paired PV in one day.
2. Watch the member's earnings.

**Expected Result:**

- Pairing bonuses are paid until `paired_pv_today` reaches `daily_pair_pv_cap`.
- Additional paired PV beyond the cap is recorded as **flushed PV** and earns nothing today.

**Verify:**

```sql
SELECT paired_pv_today, flushed_pv
FROM users
WHERE id = [ancestor_id];

-- Flushed PV record
SELECT type, amount, description
FROM pv_transactions
WHERE user_id = [ancestor_id] AND type = 'binary_flushed'
ORDER BY created_at DESC
LIMIT 5;
```

---

#### Test 3.4: Carryover of Unmatched PV

**Purpose:** Confirm stronger-leg PV carries over to the next day.

**Setup:**

1. Create a strong left leg and a weak right leg.
2. Wait for or manually trigger the midnight cron (see Test 9.1).

**Steps:**

1. Note `left_pv`, `right_pv`, and `paired_pv` before cron.
2. Run the cron.

**Expected Result:**

- `paired_pv_today` resets to `0`.
- `left_pv` and `right_pv` remain unchanged.
- Tomorrow, new PV can pair against the carried-over balance.

**Verify:**

```sql
SELECT left_pv, right_pv, paired_pv, paired_pv_today
FROM users
WHERE id = [ancestor_id];
```

---

### 5.4 Direct & Indirect Referrals

#### Test 4.1: Direct Referral is % of Package PV

**Purpose:** Confirm direct referral bonus is computed from package PV.

**Setup:**

1. Find an active sponsor member.
2. Note the package's `direct_ref_pv_pct`.

```sql
SELECT u.id, u.username, p.direct_ref_pv_pct, p.entry_fee, p.package_pv_rate
FROM users u
JOIN packages p ON p.id = u.package_id
WHERE u.id = [sponsor_id];
```

**Steps:**

1. Register a new member using this sponsor's referral code.

**Expected Result:**

- Sponsor receives `package_pv × (direct_ref_pv_pct / 100) × pv_per_peso_rate`.

**Verify:**

```sql
SELECT amount, type, cap_deduction, status
FROM commissions
WHERE user_id = [sponsor_id] AND type = 'direct_referral'
ORDER BY created_at DESC
LIMIT 1;
```

---

#### Test 4.2: Indirect Referral is % of Package PV

**Purpose:** Confirm unilevel bonuses are percentages of package PV.

**Setup:**

1. Build a sponsor chain at least 3 levels deep.
2. Check the package's indirect levels:

```sql
SELECT level, pv_pct
FROM package_indirect_levels
WHERE package_id = [package_id]
ORDER BY level;
```

**Steps:**

1. Register a new member at the bottom of the chain.

**Expected Result:**

- Each qualifying upline receives `package_pv × (pv_pct / 100) × pv_per_peso_rate`.
- Level 1 gets the highest configured percentage (if configured that way).

**Verify:**

```sql
-- Replace [new_member_id]
SELECT c.user_id, u.username, c.level, c.amount, c.type
FROM commissions c
JOIN users u ON u.id = c.user_id
WHERE c.source_user_id = [new_member_id] AND c.type = 'indirect_referral'
ORDER BY c.level;
```

---

#### Test 4.3: Cap Stops Indirect Chain

**Purpose:** Confirm a capped upline stops receiving indirect bonuses.

**Setup:**

1. Cap a middle member in the sponsor chain:

```sql
UPDATE users u
JOIN packages p ON p.id = u.package_id
SET u.cap_status = 'capped',
    u.lifetime_earned = (p.entry_fee * p.lifetime_cap_multiplier),
    u.capped_at = NOW()
WHERE u.id = [middle_member_id];
```

**Steps:**

1. Register a new member below the capped member.

**Expected Result:**

- The capped member receives no indirect bonus.
- Uplines **above** the capped member still receive their indirect bonuses normally.

**Verify:**

```sql
SELECT c.user_id, u.username, c.level, c.amount, c.status
FROM commissions c
JOIN users u ON u.id = c.user_id
WHERE c.source_user_id = [new_member_id] AND c.type = 'indirect_referral'
ORDER BY c.level;
```

---

### 5.5 Lifetime Cap with PV Earnings

#### Test 5.1: Cap Reduces a Pairing Bonus

**Purpose:** Confirm the cap engine applies to PV-derived peso amounts.

**Setup:**

1. Find an active member near their lifetime cap.

```sql
SELECT u.id, u.username, u.lifetime_earned,
       (p.entry_fee * p.lifetime_cap_multiplier) AS cap,
       ((p.entry_fee * p.lifetime_cap_multiplier) - u.lifetime_earned) AS remaining
FROM users u
JOIN packages p ON p.id = u.package_id
WHERE u.role = 'member' AND u.cap_status = 'active'
HAVING remaining > 0 AND remaining < 2000
LIMIT 1;
```

**Steps:**

1. Trigger a pairing bonus under this member (register a downline member or approve a repeat purchase that causes a pair).

**Expected Result:**

- The member receives only the remaining amount.
- The rest is blocked (`cap_deduction`).
- `lifetime_earned` reaches exactly the cap.
- `cap_status` becomes `capped`.

**Verify:**

```sql
SELECT lifetime_earned, cap_status, capped_at
FROM users
WHERE id = [member_id];

SELECT amount, cap_deduction, status
FROM commissions
WHERE user_id = [member_id]
ORDER BY created_at DESC
LIMIT 1;
```

---

#### Test 5.2: Capped Member Earns Nothing

**Purpose:** Confirm a capped member receives zero on all commission types.

**Setup:**

1. Use the member capped in Test 5.1, or cap one manually.

**Steps:**

1. Register a new member under the capped sponsor.
2. If possible, trigger a pairing bonus for the capped member.

**Expected Result:**

- Direct referral commission shows `amount = 0.00`, `cap_deduction = full_bonus`, `status = 'flushed'`.
- Pairing commission does not credit the capped member.

**Verify:**

```sql
SELECT amount, cap_deduction, status, type
FROM commissions
WHERE user_id = [capped_member_id]
ORDER BY created_at DESC
LIMIT 5;
```

---

### 5.6 Daily Fixed Income (DFI)

#### Test 6.1: Fixed DFI Mode

**Purpose:** Confirm fixed DFI still works when `dfi_pv_pct = 0`.

**Setup:**

1. In **Admin → Packages**, edit the active package.
2. Set **DFI (% of Package PV)** to `0.00`.
3. Set **Fixed Daily Income** to `100.00`.
4. Set **Max DFI Days** to `90`.
5. Save.

**Steps:**

1. Find an active member on this package with `dfi_active = 1` and `dfi_days_used < 90`.
2. Run the midnight cron or call `DailyFixedIncome::processDailyPayout()` from a test script.

**Expected Result:**

- Member receives exactly `₱100.00`.
- `dfi_days_used` increases by 1.

**Verify:**

```sql
SELECT dfi_days_used, lifetime_earned
FROM users
WHERE id = [member_id];

SELECT amount, day_number
FROM daily_fixed_income_log
WHERE user_id = [member_id]
ORDER BY created_at DESC
LIMIT 1;
```

---

#### Test 6.2: PV-Based DFI Mode

**Purpose:** Confirm DFI is computed from package PV when `dfi_pv_pct > 0`.

**Setup:**

1. Edit the active package.
2. Set **DFI (% of Package PV)** to `5.00`.
3. Set **Fixed Daily Income** to `0`.
4. Set `pv_per_peso_rate` to `1.0000`.
5. Save.

**Steps:**

1. Find an active member on this package.
2. Run the DFI payout.

**Expected Result:**

- Member receives `package_pv × 5% × pv_per_peso_rate`.
  - For entry fee ₱10,000 and package PV rate 100%, this is `10000 × 5% × 1.0 = ₱500.00`.

**Verify:**

```sql
SELECT amount, day_number
FROM daily_fixed_income_log
WHERE user_id = [member_id]
ORDER BY created_at DESC
LIMIT 1;
```

---

#### Test 6.3: DFI Stops When Days Run Out

**Purpose:** Confirm DFI stops after `daily_fixed_income_days`.

**Setup:**

```sql
-- Set a member's days used to one less than max
UPDATE users
SET dfi_days_used = (SELECT daily_fixed_income_days - 1 FROM packages WHERE id = users.package_id)
WHERE id = [member_id];
```

**Steps:**

1. Run the DFI payout twice.

**Expected Result:**

- First run: DFI paid, `dfi_days_used` reaches max.
- Second run: No DFI paid.

**Verify:**

```sql
SELECT dfi_days_used
FROM users
WHERE id = [member_id];
```

---

### 5.7 Products & Repeat Purchases

#### Test 7.1: Create a Product

**Purpose:** Confirm admin can add products with PV.

**Steps:**

1. Log in as admin.
2. Go to **Admin → Products**.
3. Click **New Product**.
4. Enter name, price, and **PV Value**.
5. Save.

**Expected Result:**

- Product appears in the list.
- `pv_value` is saved correctly.

**Verify:**

```sql
SELECT name, price, pv_value, status
FROM products
ORDER BY id DESC
LIMIT 1;
```

---

#### Test 7.2: Member Submits a Repeat Purchase

**Purpose:** Confirm a member can request a repeat purchase.

**Steps:**

1. Log in as a member.
2. Go to **Repeat Purchases** or **Shop**.
3. Select a product and quantity.
4. Submit.

**Expected Result:**

- A pending repeat purchase record is created.
- No PV is distributed yet.

**Verify:**

```sql
SELECT id, member_id, product_id, quantity, total_pv, total_price, status
FROM repeat_purchases
ORDER BY id DESC
LIMIT 1;
```

---

#### Test 7.3: Approve Repeat Purchase Distributes PV

**Purpose:** Confirm approving a repeat purchase triggers PV flow.

**Setup:**

1. Have a pending repeat purchase.
2. Note the buyer's `id`, the product's `pv_value`, and the quantity.

**Steps:**

1. Log in as admin.
2. Go to **Admin → Repeat Purchases**.
3. Click **Approve** on the pending purchase.

**Expected Result:**

- Buyer's `personal_pv` increases by `total_pv`.
- Sponsor chain receives `group_pv` (if they qualify; see Test 8).
- Binary tree receives `left_pv` or `right_pv` (if ancestors qualify).
- `pv_transactions` records are created.

**Verify:**

```sql
-- Buyer personal PV
SELECT personal_pv
FROM users
WHERE id = [buyer_id];

-- Group PV up sponsor chain
SELECT u.id, u.username, u.group_pv
FROM users u
WHERE u.id IN ([buyer_id], [sponsor_id], [grand_sponsor_id]);

-- PV transactions
SELECT type, amount, source_user_id, source_type
FROM pv_transactions
WHERE source_user_id = [buyer_id]
ORDER BY id;
```

---

#### Test 7.4: Repeat Purchase Can Trigger Pairing

**Purpose:** Confirm product PV can cause a binary pair and pairing bonus.

**Setup:**

1. Find an ancestor member whose left and right legs are close to matching.
2. Submit and approve a repeat purchase in the weaker leg.

**Steps:**

1. Approve the repeat purchase.

**Expected Result:**

- Ancestor's `paired_pv` increases.
- Pairing bonus commission is created.

**Verify:**

```sql
SELECT left_pv, right_pv, paired_pv, paired_pv_today
FROM users
WHERE id = [ancestor_id];

SELECT amount, type
FROM commissions
WHERE user_id = [ancestor_id] AND type = 'pairing'
ORDER BY created_at DESC
LIMIT 1;
```

---

### 5.8 Personal PV Gate

#### Test 8.1: Gate Blocks Repeat-Purchase PV Flow

**Purpose:** Confirm the personal PV requirement filters repeat-purchase commissions.

**Setup:**

1. Go to **Admin → Settings**.
2. Set **Personal PV Requirement** to `1000.00`.
3. Ensure a sponsor in the chain has `personal_pv < 1000`.

```sql
-- Find a sponsor with low personal PV
SELECT id, username, personal_pv
FROM users
WHERE role = 'member' AND personal_pv < 1000
LIMIT 1;
```

**Steps:**

1. Submit and approve a repeat purchase by a member whose sponsor is the low-PV member.

**Expected Result:**

- The low-PV sponsor does **not** receive group_pv from this purchase.
- The low-PV ancestor does **not** receive binary PV from this purchase.
- The buyer still receives personal_pv.
- Uplines above the low-PV member who meet the gate still receive PV normally.

**Verify:**

```sql
SELECT personal_pv, group_pv
FROM users
WHERE id = [low_pv_sponsor_id];

SELECT type, amount
FROM pv_transactions
WHERE user_id = [low_pv_sponsor_id]
  AND source_user_id = [buyer_id];
-- Expected: no rows for product_group
```

---

#### Test 8.2: Gate Allows Flow When Requirement Is Met

**Purpose:** Confirm the gate opens when personal PV is high enough.

**Setup:**

1. Set the same sponsor's `personal_pv` above the gate:

```sql
UPDATE users SET personal_pv = 1500 WHERE id = [low_pv_sponsor_id];
```

**Steps:**

1. Submit and approve another repeat purchase from the same buyer.

**Expected Result:**

- Sponsor's `group_pv` increases by the product PV.

**Verify:**

```sql
SELECT group_pv
FROM users
WHERE id = [sponsor_id];

SELECT type, amount
FROM pv_transactions
WHERE user_id = [sponsor_id]
  AND source_user_id = [buyer_id]
  AND type = 'product_group'
ORDER BY id DESC
LIMIT 1;
```

**Cleanup:** Set `personal_pv_requirement` back to `0.0000` if you don't want the gate active.

---

### 5.9 Midnight Cron & Resets

#### Test 9.1: Run the Cron Manually

**Purpose:** Confirm the midnight cron runs without errors.

**Steps:**

1. SSH to the server.
2. Run:

```bash
php /path/to/site/cron/midnight_reset.php
```

**Expected Result:**

- Script completes without fatal errors.
- Log file is created/updated in `cron/logs/`.

**Verify:**

```bash
tail -n 20 /path/to/site/cron/logs/reset_$(date +%Y-%m).log
```

---

#### Test 9.2: Daily Pair PV Counter Resets

**Setup:**

```sql
-- Give a member some paired_pv_today
UPDATE users SET paired_pv_today = 9999 WHERE id = [member_id];
```

**Steps:**

1. Run the midnight cron.

**Expected Result:**

- `paired_pv_today` becomes `0.00`.

**Verify:**

```sql
SELECT paired_pv_today
FROM users
WHERE id = [member_id];
```

---

#### Test 9.3: Personal PV Resets Monthly

**Purpose:** Confirm personal_pv resets on the first day of the month.

**Setup:**

```sql
-- Temporarily set a member's personal_pv
UPDATE users SET personal_pv = 5000 WHERE id = [member_id];
```

**Steps:**

1. Run the cron on the first day of the month, or temporarily edit `cron/monthly_pv_reset.php` to test in isolation.

**Expected Result:**

- `personal_pv` becomes `0.00` for all members.

**Verify:**

```sql
SELECT personal_pv
FROM users
WHERE id = [member_id];
```

---

#### Test 9.4: DFI Payout via Cron

**Purpose:** Confirm the cron triggers DFI payouts.

**Setup:**

1. Ensure DFI is enabled globally (`dfi_enabled` setting = `1`).
2. Ensure an active member has `dfi_active = 1` and `dfi_days_used < max days`.

**Steps:**

1. Run the midnight cron.

**Expected Result:**

- The member receives DFI.
- A record appears in `daily_fixed_income_log`.
- `dfi_days_used` increases by 1.

**Verify:**

```sql
SELECT dfi_days_used
FROM users
WHERE id = [member_id];

SELECT amount, day_number
FROM daily_fixed_income_log
WHERE user_id = [member_id]
ORDER BY created_at DESC
LIMIT 1;
```

---

### 5.10 Frontend Copy & Reports

#### Test 10.1: Public Landing Page Shows PV-Based Copy

**Purpose:** Confirm the public site no longer shows legacy fixed-peso text.

**Steps:**

1. Open the site homepage while logged out.
2. Scroll through Hero, Compensation Plan, Packages, Why AltasFarm, and Terms sections.

**Expected Result:**

- Pairing bonus is shown in pesos derived from PV.
- Cap is described as “PV per day,” not “pairs per day.”
- Unilevel shows percentages with peso equivalents.
- DFI shows the computed amount (fixed or PV-based).
- No references to legacy columns like `pairing_bonus` fixed ₱2000 unless they match the computed value.

**Verify:**

- Right-click → **View Page Source** and search for `pairing_bonus`, `daily_pair_cap`, `direct_ref_bonus`.
- Expected: no matches.

---

#### Test 10.2: Member Dashboard PV Cards

**Purpose:** Confirm members see PV stats.

**Steps:**

1. Log in as a member.
2. View the dashboard.

**Expected Result:**

- Cards show Personal PV, Group PV, Left PV, Right PV, Paired PV, Flushed PV, or similar.
- DFI card shows the daily rate and PV equivalent.

**Verify:**

- No PHP notices or warnings.
- Values match database:

```sql
SELECT personal_pv, group_pv, left_pv, right_pv, paired_pv, flushed_pv
FROM users
WHERE id = [member_id];
```

---

#### Test 10.3: Admin PV Transaction Audit

**Purpose:** Confirm every PV movement is auditable.

**Steps:**

1. Register a member and approve a repeat purchase.
2. Query the `pv_transactions` table.

**Expected Result:**

- Rows exist for `binary_left`, `binary_right`, `binary_paired`, `binary_flushed`, `product_personal`, `product_group`, etc.

**Verify:**

```sql
SELECT type, amount, source_user_id, source_type, created_at
FROM pv_transactions
ORDER BY id DESC
LIMIT 20;
```

---

### 5.11 Regression & Legacy Cleanup

#### Test 11.1: Legacy Columns Are Not Used by Active Code

**Purpose:** Confirm active code no longer depends on `pairing_bonus`, `daily_pair_cap`, or `direct_ref_bonus`.

**Steps:**

1. In your code editor, search the active directories:

```bash
grep -R "pairing_bonus\|daily_pair_cap\|direct_ref_bonus" \
  /path/to/site/controllers \
  /path/to/site/core \
  /path/to/site/models \
  /path/to/site/views \
  /path/to/site/frontend \
  /path/to/site/cron
```

**Expected Result:**

- No matches in active code (legacy columns may still exist in the database and in `temp/` backups, which is fine).

---

#### Test 11.2: Registration Still Works End-to-End

**Purpose:** Confirm the whole registration flow is unbroken.

**Steps:**

1. Generate a registration code as admin.
2. Register a new member using the code.
3. Log in as the new member.

**Expected Result:**

- Registration succeeds.
- Sponsor receives direct referral bonus.
- Binary parent receives package PV in the correct leg.
- No PHP errors.

---

#### Test 11.3: Payout Workflow Still Works

**Purpose:** Confirm withdrawals are unaffected by PV changes.

**Steps:**

1. Log in as a member with e-wallet balance.
2. Submit a payout request.
3. As admin, approve and complete the payout.

**Expected Result:**

- Payout is recorded.
- Member balance decreases correctly.

---

## 6. Bug Reporting Template

When you find an issue, use this format:

````markdown
### Bug Report — PV-Centered System

**Tester:** [Your Name]
**Date:** [YYYY-MM-DD HH:MM]
**Test Case:** [e.g., 3.2 Pairing Bonus Fires on Matched PV]
**Severity:** [Critical / High / Medium / Low]

**Steps to Reproduce:**

1. [Step 1]
2. [Step 2]
3. [Step 3]

**Expected Result:**
[What should happen]

**Actual Result:**
[What actually happened]

**Database State (Before):**

```sql
[Paste relevant query results]
```
````

**Database State (After):**

```sql
[Paste relevant query results]
```

**PHP Error Log:**

```
[Paste any errors from /var/log/apache2/error.log or similar]
```

**Screenshots:**
[Attach if applicable]

**Environment:**

- Browser: [Chrome/Firefox/Safari] v[X.X]
- OS: [Windows/Mac/Linux]
- Server: PHP [version], MySQL [version]

````

---

## 7. Pass/Fail Criteria

### The PV-Centered System PASSES if ALL of these are true:

| # | Criteria | How to Verify |
|---|----------|---------------|
| 1 | `pv_per_peso_rate` can be saved and is used | Test 1.1 |
| 2 | Package form shows correct PV and peso previews | Test 2.1–2.4 |
| 3 | New registration adds package PV to binary legs | Test 3.1 |
| 4 | Pairing bonus is PV-derived and matches formula | Test 3.2 |
| 5 | Daily pair cap is enforced in PV terms | Test 3.3 |
| 6 | Unmatched PV carries over after cron | Test 3.4 |
| 7 | Direct referral is % of package PV | Test 4.1 |
| 8 | Indirect referral is % of package PV | Test 4.2 |
| 9 | Cap stops indirect chain | Test 4.3 |
| 10 | Cap reduces PV-derived bonuses | Test 5.1 |
| 11 | Capped members earn zero | Test 5.2 |
| 12 | Fixed DFI works | Test 6.1 |
| 13 | PV-based DFI works | Test 6.2 |
| 14 | DFI stops after max days | Test 6.3 |
| 15 | Product CRUD works | Test 7.1 |
| 16 | Repeat purchase creates pending record | Test 7.2 |
| 17 | Approval distributes PV correctly | Test 7.3 |
| 18 | Repeat purchase can trigger pairing | Test 7.4 |
| 19 | Personal PV gate blocks low-PV uplines | Test 8.1 |
| 20 | Personal PV gate opens when met | Test 8.2 |
| 21 | Midnight cron runs without errors | Test 9.1 |
| 22 | Daily pair PV counter resets | Test 9.2 |
| 23 | Personal PV resets monthly | Test 9.3 |
| 24 | Cron triggers DFI | Test 9.4 |
| 25 | Frontend copy is PV-aware and legacy-free | Test 10.1 |
| 26 | Member dashboard shows PV stats | Test 10.2 |
| 27 | PV transaction audit log is complete | Test 10.3 |
| 28 | Active code no longer references legacy columns | Test 11.1 |
| 29 | Registration and payout workflows still work | Test 11.2, 11.3 |
| 30 | No PHP fatal errors in logs | Check logs after all tests |

### The PV-Centered System FAILS if ANY of these are true:

- ❌ Bonus amounts don't match the PV formula
- ❌ Binary pairing still uses member counts instead of PV
- ❌ Daily pair cap is still a count cap
- ❌ Direct/indirect bonuses are still fixed peso amounts
- ❌ Capped members still earn commissions
- ❌ Personal PV gate doesn't block repeat-purchase PV
- ❌ DFI ignores `dfi_pv_pct`
- ❌ Repeat purchase approval doesn't create `pv_transactions`
- ❌ Frontend still shows legacy fixed-peso copy
- ❌ Cron crashes or throws fatal errors
- ❌ Registration or payout workflows are broken

---

## 8. FAQ

### Q: How do I quickly set a member's personal PV for testing?
**A:**
```sql
UPDATE users SET personal_pv = 1500 WHERE id = [member_id];
````

### Q: How do I find a member's sponsor chain?

**A:**

```sql
WITH RECURSIVE chain AS (
    SELECT id, username, sponsor_id, 0 AS depth FROM users WHERE id = [member_id]
    UNION ALL
    SELECT p.id, p.username, p.sponsor_id, c.depth + 1
    FROM users p
    JOIN chain c ON p.id = c.sponsor_id
    WHERE p.sponsor_id IS NOT NULL
)
SELECT * FROM chain ORDER BY depth;
```

### Q: How do I find a member's binary ancestors?

**A:**

```sql
WITH RECURSIVE ancestors AS (
    SELECT id, username, binary_parent_id, binary_position, 0 AS depth
    FROM users WHERE id = [member_id]
    UNION ALL
    SELECT p.id, p.username, p.binary_parent_id, p.binary_position, a.depth + 1
    FROM users p
    JOIN ancestors a ON p.id = a.binary_parent_id
    WHERE p.binary_parent_id IS NOT NULL
)
SELECT * FROM ancestors ORDER BY depth;
```

### Q: How do I run only the DFI payout without the full cron?

**A:**

```bash
php -r "require 'config/db.php'; require 'core/DailyFixedIncome.php'; var_dump(DailyFixedIncome::processDailyPayout());"
```

### Q: How do I reset personal_pv for everyone without waiting for the monthly cron?

**A:**

```bash
php /path/to/site/cron/monthly_pv_reset.php
```

### Q: Can I test the monthly reset without changing the server date?

**A:** Yes — run the `monthly_pv_reset.php` script directly. It resets all members' `personal_pv` to `0`.

### Q: Where can I see all PV movements for one member?

**A:**

```sql
SELECT type, amount, source_user_id, source_type, created_at
FROM pv_transactions
WHERE user_id = [member_id]
ORDER BY created_at DESC;
```

### Q: The pairing bonus amount looks wrong. What should I check?

**A:**

1. Verify `package_pv_rate` and `entry_fee`.
2. Verify `pv_per_peso_rate` (global setting).
3. Verify the package's `binary_pv_pct` (binary PV basis).
4. Check how much PV was actually paired (`paired_pv_today`, `flushed_pv`).

### Q: A member didn't get indirect bonus. Why?

**A:**

1. Check `indirect_referral_enabled` setting is `1`.
2. Check `package_indirect_levels` has percentages for that package.
3. Verify the member is in the sponsor chain (not just binary tree).
4. Check if the member is capped or permanently inactive.

### Q: What does `cap_deduction` mean in the commissions table?

**A:** It is the amount blocked by the lifetime cap. If `amount + cap_deduction = total_bonus`, the cap reduced or eliminated the payout.

---

## 9. Quick Reference Card

| Test            | What to Do                            | What to Check                           |
| --------------- | ------------------------------------- | --------------------------------------- |
| PV rate         | Change `pv_per_peso_rate` in settings | Database value updates                  |
| Package PV      | Set entry fee + PV rate               | Preview shows correct package PV        |
| Pairing payout  | Set global `pv_per_peso_rate`         | Preview matches formula                 |
| Direct %        | Set `direct_ref_pv_pct`               | Preview matches formula                 |
| DFI PV mode     | Set `dfi_pv_pct > 0`                  | DFI amount computed from package PV     |
| Binary PV       | Register a member                     | Ancestor leg PV increases by package PV |
| Pair fires      | Add members to both legs              | `paired_pv` and wallet update           |
| PV cap          | Exceed daily pair PV cap              | `flushed_pv` records appear             |
| Direct ref      | Register under sponsor                | Sponsor gets `% of package PV`          |
| Indirect        | Register deep in chain                | Uplines get level percentages           |
| Cap             | Set member near cap, trigger bonus    | Partial/zeroed payout, status change    |
| Repeat purchase | Member buys product + admin approves  | PV flows, transactions logged           |
| PV gate         | Set gate above sponsor's personal PV  | Sponsor gets no repeat-purchase PV      |
| Cron            | Run `midnight_reset.php`              | No errors, counters reset, DFI paid     |
| Frontend        | View public pages                     | No legacy fixed-peso copy               |
| Legacy cleanup  | Grep active code                      | No references to old bonus columns      |

---

**End of Guide**
