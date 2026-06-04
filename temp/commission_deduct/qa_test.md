# QA Tester Guide — Commission-Deduct (CD) Feature

**Version:** v1.0  
**Date:** 2026-05-28  
**System:** Altas Farm MLM Binary System  
**Feature:** Commission-Deduct (CD) Bucket & Ledger

---

## Table of Contents

1. [What is CD Testing?](#1-what-is-cd-testing)
2. [Prerequisites](#2-prerequisites)
3. [Test Environment Setup](#3-test-environment-setup)
4. [Test Cases](#4-test-cases)
   - 4.1 [Admin CD Assignment](#41-admin-cd-assignment)
   - 4.2 [CD Commission Split — Pairing Bonus](#42-cd-commission-split--pairing-bonus)
   - 4.3 [CD Commission Split — Direct Referral](#43-cd-commission-split--direct-referral)
   - 4.4 [CD Commission Split — Indirect Referral](#44-cd-commission-split--indirect-referral)
   - 4.5 [CD Auto-Completion](#45-cd-auto-completion)
   - 4.6 [CD & Lifetime Cap Interaction](#46-cd--lifetime-cap-interaction)
   - 4.7 [CD & DFI Interaction](#47-cd--dfi-interaction)
   - 4.8 [Admin Manual Complete / Cancel](#48-admin-manual-complete--cancel)
   - 4.9 [CD Target Update on Package Change](#49-cd-target-update-on-package-change)
   - 4.10 [Member Dashboard & Ledger UI](#410-member-dashboard--ledger-ui)
   - 4.11 [Admin UI Indicators](#411-admin-ui-indicators)
   - 4.12 [Regression Testing](#412-regression-testing)
5. [Bug Reporting Template](#5-bug-reporting-template)
6. [Pass/Fail Criteria](#6-passfail-criteria)
7. [FAQ](#7-faq)

---

## 1. What is CD Testing?

Commission-Deduct (CD) is a system where **a portion of every commission a member earns is diverted into a "CD bucket"** until a target amount is filled. While the CD is active, the member's Daily Fixed Income (DFI) is paused. Once the bucket is full (or manually completed by admin), DFI resumes.

### What's New in CD

| Component | What It Does | Where to Test |
|-----------|-------------|-------------|
| **Admin CD Assignment** | Admin assigns a target amount and activates CD on a member | Admin → Users → View User |
| **CD Commission Split** | Every pairing, direct, and indirect bonus is split between CD bucket and wallet | Database + member earnings |
| **CD Auto-Completion** | When `filled_amount >= target_amount`, CD automatically completes | Trigger via commissions |
| **CD Ledger** | Audit trail of every commission split (gross, to CD, to wallet) | Member → Earnings page |
| **DFI Pause** | Members with active CD are excluded from DFI payouts | Midnight cron / DFI eligibility |
| **Manual Complete/Cancel** | Admin can force-complete or cancel (forfeit) a CD | Admin → View User |
| **Target Update** | CD target adjusts when member upgrades/downgrades package | Change member's package |
| **UI Badges** | CD indicators on admin users list, member dashboard, earnings | Visual inspection |

### What's NOT in CD (Don't Test These Here)

- ❌ Creating new packages — test in Package Management
- ❌ General registration flow — test in Registration QA
- ❌ Lifetime capping mechanics — test in Capping QA
- ❌ E-wallet transfers / payouts — test in Wallet QA
- ❌ Reactivation workflow — test in Reactivation QA

> **Rule of Thumb:** If it doesn't involve the `user_cd_status` table, `cd_ledger` table, or `users.cd_active` flag, it's not a CD test.

---

## 2. Prerequisites

Before you start testing, ensure you have:

### Required Access
- [ ] Admin login credentials (username: `admin`, password: `Admin@1234`)
- [ ] Database access (phpMyAdmin, MySQL CLI, or similar)
- [ ] At least one active member account to test with

### Required Knowledge
- [ ] How to run SQL queries (SELECT, UPDATE)
- [ ] How to read PHP error logs
- [ ] How to use browser DevTools (F12 → Console, Network tabs)
- [ ] Basic understanding of the three commission types: pairing, direct referral, indirect referral

### Required Tools
| Tool | Purpose | Free? |
|------|---------|-------|
| Web Browser (Chrome/Firefox) | UI testing | Yes |
| Browser DevTools (F12) | Check for JS errors | Yes |
| phpMyAdmin or MySQL CLI | Verify database state | Yes |
| Text Editor (VS Code, Notepad++) | Check PHP files | Yes |

### Schema Must Be Deployed
- [ ] `migrations/003_add_cd_schema.sql` has been run successfully
- [ ] `models/CdStatus.php` exists
- [ ] `core/Commission.php` contains `CdStatus::fillBucket` calls
- [ ] `core/DailyFixedIncome.php` excludes `cd_active = 1` members

**Quick Schema Check:**
```sql
-- Verify tables exist
SHOW TABLES LIKE 'user_cd_status';
SHOW TABLES LIKE 'cd_ledger';

-- Verify column exists
SHOW COLUMNS FROM users LIKE 'cd_active';
```

---

## 3. Test Environment Setup

### Step 1: Backup (CRITICAL)

```bash
# Backup database before testing
mysqldump -u your_username -p u938213108_altas_db > backup_before_cd_test.sql
```

### Step 2: Verify CD Files Are Deployed

Run these commands on your server:

```bash
# Check CdStatus model exists
ls -la /path/to/site/models/CdStatus.php

# Check Commission.php has CD logic
grep -n "CdStatus::fillBucket" /path/to/site/core/Commission.php

# Check DFI excludes CD members
grep -n "cd_active" /path/to/site/core/DailyFixedIncome.php
```

**Expected Result:**
- `CdStatus.php` exists
- `Commission.php` contains 3 `CdStatus::fillBucket` calls (pairing, direct, indirect)
- `DailyFixedIncome.php` contains `u.cd_active = 0`

### Step 3: Prepare Test Data

You'll need at least 3 active members in a sponsor chain. If you don't have enough, register them first using the demo code `DEMO-STAR-TKIT`.

```sql
-- Find active members far from cap (safe test subjects)
SELECT id, username, package_id, ewallet_balance, cd_active
FROM users
WHERE role = 'member' AND status = 'active'
ORDER BY id
LIMIT 5;

-- Verify no existing CD status (clean slate)
SELECT COUNT(*) FROM user_cd_status;
SELECT COUNT(*) FROM cd_ledger;
```

If counts are non-zero and you want a clean test:
```sql
-- WARNING: Only in test environment!
DELETE FROM cd_ledger;
DELETE FROM user_cd_status;
UPDATE users SET cd_active = 0 WHERE role = 'member';
```

### Step 4: Note Package Values

```sql
SELECT id, name, entry_fee, pairing_bonus, direct_ref_bonus
FROM packages WHERE status = 'active';
```

**Write these down — you'll need them for calculations:**
- `entry_fee` (used as default CD target)
- `pairing_bonus` (per pair)
- `direct_ref_bonus` (per direct signup)

---

## 4. Test Cases

---

### 4.1 Admin CD Assignment

**Purpose:** Verify admins can assign, and the system prevents invalid assignments.

#### Test 1.1: Assign CD to Active Member

**Setup:**
```sql
-- Pick an active member with no existing CD
SELECT id, username, status, cd_active
FROM users
WHERE role = 'member' AND status = 'active' AND cd_active = 0
LIMIT 1;
```

**Steps:**
1. Log in as **admin**
2. Go to **Admin → Users**
3. Click **View** on the chosen member
4. Scroll to the **Commission-Deduct Status** card
5. In the **CD Target Amount** field, enter `5000.00`
6. Click **⏳ Assign CD**

**Expected Result:**
- Success message: "Commission-Deduct status assigned successfully."
- The page reloads and shows a CD status card with:
  - Target: ₱5,000.00
  - Filled: ₱0.00
  - Remaining: ₱5,000.00
  - Progress bar at 0%
  - Badge: "Active"
- **Mark Complete** and **Cancel** buttons appear

**Verify:**
```sql
SELECT * FROM user_cd_status WHERE user_id = [member_id];
-- Should show: status = 'active', target_amount = 5000.00, filled_amount = 0.00

SELECT cd_active FROM users WHERE id = [member_id];
-- Should show: cd_active = 1
```

---

#### Test 1.2: Cannot Assign CD to Pending Member

**Setup:**
```sql
-- Find a pending member
SELECT id, username FROM users WHERE status = 'pending' LIMIT 1;
```

**Steps:**
1. As admin, view the pending member's profile
2. Look for the **Assign CD** form

**Expected Result:**
- The **Assign CD** form is **hidden** (only shown for `active` members)
- OR if visible, submitting it shows error: "CD can only be assigned to active users."

**Verify:**
```sql
SELECT COUNT(*) FROM user_cd_status WHERE user_id = [pending_member_id];
-- Should return 0
```

---

#### Test 1.3: Cannot Assign Second CD While One is Active

**Setup:** Use the member from Test 1.1 (already has active CD).

**Steps:**
1. As admin, view the same member's profile again
2. Try to assign another CD with a different target (e.g., `3000.00`)

**Expected Result:**
- Error message: "User already has an active CD."
- No new row is created in `user_cd_status`

**Verify:**
```sql
SELECT COUNT(*) FROM user_cd_status
WHERE user_id = [member_id] AND status = 'active';
-- Should return exactly 1
```

---

#### Test 1.4: Cannot Assign CD with Zero or Negative Target

**Setup:** Use any active member without a CD.

**Steps:**
1. As admin, view the member
2. Enter `0` or leave the target field empty
3. Click **Assign CD**

**Expected Result:**
- Error message: "Invalid user or target amount."
- No CD row created

---

### 4.2 CD Commission Split — Pairing Bonus

**Purpose:** Verify pairing bonuses are split between CD bucket and wallet.

#### Test 2.1: Full Pairing Bonus Goes to CD (Empty Bucket, Large Target)

**Setup:**
```sql
-- Pick an active member in the binary tree with an active CD
-- The CD target should be LARGE (e.g., 50000) so it won't fill quickly
-- Ensure the member has an empty side to place a new member under

SELECT u.id, u.username, u.cd_active,
       cd.target_amount, cd.filled_amount
FROM users u
LEFT JOIN user_cd_status cd ON cd.user_id = u.id AND cd.status = 'active'
WHERE u.id = [member_id];
```

**Steps:**
1. Ensure the member has an active CD with target = ₱50,000
2. Note the member's `binary_parent_id` (their upline in the tree)
3. Register a new member under the **upline's opposite side** so that a pair forms for the member
   - Or, place a new member directly under the member's empty side, then place another on the opposite side to trigger a pair
4. Trigger a pairing bonus for the member

**Expected Result:**
- The pairing bonus (e.g., ₱2,000 per pair × N pairs) is **split**:
  - The **entire pairing amount** goes to the CD bucket (if target is large)
  - Wallet receives **₱0** from this commission
- `user_cd_status.filled_amount` increases by the pairing amount
- `users.cd_active` remains `1`

**Verify:**
```sql
-- Check the commission record
SELECT amount, description, status
FROM commissions
WHERE user_id = [member_id] AND type = 'pairing'
ORDER BY created_at DESC
LIMIT 1;
-- amount = [gross pairing amount]
-- description should contain "to CD" (e.g., "1 pair(s) × ₱2,000.00 — ₱2,000.00 to CD")

-- Check CD status
SELECT target_amount, filled_amount, status
FROM user_cd_status
WHERE user_id = [member_id] AND status = 'active';
-- filled_amount should have increased

-- Check CD ledger
SELECT gross_amount, cd_amount, withdrawable_amount, type
FROM cd_ledger
WHERE user_id = [member_id]
ORDER BY created_at DESC
LIMIT 1;
-- cd_amount = gross_amount (if all went to CD)
-- withdrawable_amount = 0.00
```

---

#### Test 2.2: Partial Pairing Split (CD Nearly Full)

**Setup:**
```sql
-- Set the member's CD to be nearly full
UPDATE user_cd_status
SET filled_amount = target_amount - 500,
    status = 'active'
WHERE user_id = [member_id] AND status = 'active';
-- Now only ₱500 remains to fill
```

**Steps:**
1. Trigger a pairing bonus for the member (e.g., ₱2,000)
2. This should fill the remaining ₱500 and put ₱1,500 in the wallet

**Expected Result:**
- CD bucket receives ₱500 (fills it completely)
- Wallet receives ₱1,500
- CD status automatically changes to `completed`
- `users.cd_active` changes to `0`
- DFI is now eligible for this member

**Verify:**
```sql
-- Check CD status
SELECT target_amount, filled_amount, status, completed_at
FROM user_cd_status
WHERE user_id = [member_id]
ORDER BY assigned_at DESC
LIMIT 1;
-- status should be 'completed', filled_amount = target_amount

-- Check user flag
SELECT cd_active FROM users WHERE id = [member_id];
-- Should be 0

-- Check CD ledger
SELECT gross_amount, cd_amount, withdrawable_amount
FROM cd_ledger
WHERE user_id = [member_id]
ORDER BY created_at DESC
LIMIT 1;
-- cd_amount = 500.00, withdrawable_amount = 1500.00
```

---

#### Test 2.3: No CD Split When Member Has No Active CD

**Setup:**
```sql
-- Pick an active member with cd_active = 0 and no active CD row
SELECT id, username FROM users
WHERE role = 'member' AND status = 'active' AND cd_active = 0
LIMIT 1;
```

**Steps:**
1. Trigger a pairing bonus for this member (register under their tree)

**Expected Result:**
- Full pairing bonus goes to wallet
- No CD ledger entry created
- Commission description does NOT contain "to CD"

**Verify:**
```sql
-- Check commissions
SELECT amount, description
FROM commissions
WHERE user_id = [member_id] AND type = 'pairing'
ORDER BY created_at DESC
LIMIT 1;
-- description should NOT contain "to CD"

-- Verify no CD ledger
SELECT COUNT(*) FROM cd_ledger WHERE user_id = [member_id];
-- Should be 0 (or same as before)
```

---

### 4.3 CD Commission Split — Direct Referral

**Purpose:** Verify direct referral bonuses are split correctly.

#### Test 3.1: Direct Referral Split — Full to CD

**Setup:**
- Member A has active CD with large target (e.g., ₱50,000)
- Member A's `cd_active = 1`

**Steps:**
1. Register a new member with Member A as the **sponsor**
2. Direct referral bonus = ₱500 (or package default)

**Expected Result:**
- Member A's CD bucket receives ₱500
- Member A's wallet receives ₱0 from this direct bonus
- CD ledger records: gross = 500, cd_amount = 500, withdrawable = 0

**Verify:**
```sql
-- Check commission
SELECT amount, description
FROM commissions
WHERE user_id = [member_a_id] AND type = 'direct_referral'
ORDER BY created_at DESC
LIMIT 1;
-- description should contain "to CD"

-- Check CD ledger
SELECT type, gross_amount, cd_amount, withdrawable_amount
FROM cd_ledger
WHERE user_id = [member_a_id]
ORDER BY created_at DESC
LIMIT 1;
-- type = 'direct_referral', cd_amount = 500.00, withdrawable_amount = 0.00

-- Check CD fill
SELECT filled_amount FROM user_cd_status
WHERE user_id = [member_a_id] AND status = 'active';
-- Should have increased by 500
```

---

#### Test 3.2: Direct Referral Split — Partial (CD Fills + Auto-Completes)

**Setup:**
```sql
-- Set Member A's CD to need only ₱300 more
UPDATE user_cd_status
SET filled_amount = target_amount - 300
WHERE user_id = [member_a_id] AND status = 'active';
```

**Steps:**
1. Register a new member with Member A as sponsor (bonus = ₱500)

**Expected Result:**
- CD receives ₱300 (completes the bucket)
- Wallet receives ₱200
- CD auto-completes
- `cd_active` becomes `0`

**Verify:**
```sql
SELECT status, filled_amount, completed_at
FROM user_cd_status
WHERE user_id = [member_a_id]
ORDER BY assigned_at DESC
LIMIT 1;
-- status = 'completed', filled_amount = target_amount

SELECT cd_active FROM users WHERE id = [member_a_id];
-- cd_active = 0

SELECT cd_amount, withdrawable_amount
FROM cd_ledger
WHERE user_id = [member_a_id]
ORDER BY created_at DESC
LIMIT 1;
-- cd_amount = 300.00, withdrawable_amount = 200.00
```

---

### 4.4 CD Commission Split — Indirect Referral

**Purpose:** Verify indirect (unilevel) referral bonuses are split correctly.

#### Test 4.1: Indirect Referral Split

**Setup:**
- Create a sponsor chain: Member A → Member B → New Member
- Member A has an active CD with a large target
- Member B is the direct sponsor of the new member

**Steps:**
1. Register a new member under Member B
2. Member A receives indirect referral bonus (e.g., ₱300 for L1)

**Expected Result:**
- Member A's CD bucket receives ₱300
- Wallet receives ₱0
- CD ledger records type = `indirect_referral`

**Verify:**
```sql
-- Check commission
SELECT amount, description, level
FROM commissions
WHERE user_id = [member_a_id] AND type = 'indirect_referral'
ORDER BY created_at DESC
LIMIT 1;

-- Check CD ledger
SELECT type, gross_amount, cd_amount, withdrawable_amount
FROM cd_ledger
WHERE user_id = [member_a_id]
ORDER BY created_at DESC
LIMIT 1;
-- type = 'indirect_referral', cd_amount > 0
```

---

#### Test 4.2: Indirect Chain Stops at Capped Member (CD + Cap Together)

**Setup:**
- Chain: Member A (active, has CD) → Member B (capped) → Member C → New Member
- Register New Member under Member C

**Steps:**
1. Ensure Member B is `capped` or `perminact`
2. Register new member under Member C

**Expected Result:**
- Member C receives direct referral bonus
- Member B receives nothing (capped, chain stops)
- Member A receives nothing (chain stopped at Member B)
- No CD ledger entries for Member A or B for this registration

**Verify:**
```sql
SELECT type, amount, status
FROM commissions
WHERE user_id IN ([member_a_id], [member_b_id])
  AND source_user_id = [new_member_id]
ORDER BY user_id, type;
-- Member B: should show 'flushed' or no rows
-- Member A: should show no rows
```

---

### 4.5 CD Auto-Completion

**Purpose:** Verify CD automatically completes when the target is reached.

#### Test 5.1: Auto-Complete via Pairing Bonus

**Setup:**
```sql
-- Find a member with active CD
-- Manually set filled_amount so that ONE pairing bonus will complete it
UPDATE user_cd_status
SET filled_amount = target_amount - [pairing_bonus_amount]
WHERE user_id = [member_id] AND status = 'active';
```

**Steps:**
1. Trigger exactly one pairing bonus for this member

**Expected Result:**
- CD status changes from `active` to `completed`
- `completed_at` is set to current timestamp
- `users.cd_active` becomes `0`
- Member becomes eligible for DFI again

**Verify:**
```sql
SELECT status, filled_amount, target_amount, completed_at
FROM user_cd_status
WHERE user_id = [member_id]
ORDER BY assigned_at DESC
LIMIT 1;

SELECT cd_active FROM users WHERE id = [member_id];
```

---

#### Test 5.2: Auto-Complete via Direct Referral

**Setup:** Same as 5.1, but set the remaining amount equal to direct_ref_bonus.

**Steps:**
1. Register a new member under this sponsor

**Expected Result:**
- CD completes automatically
- Wallet receives any overflow (direct bonus - remaining CD)

---

#### Test 5.3: Multiple Small Commissions Fill CD Gradually

**Setup:**
- Member has CD target = ₱5,000
- `filled_amount` starts at 0

**Steps:**
1. Trigger multiple small commissions (e.g., 10 direct referrals at ₱500 each)
2. On the 10th referral, the CD should complete

**Expected Result:**
- After 9 referrals: `filled_amount = 4500`, status = `active`
- After 10th referral: `filled_amount = 5000`, status = `completed`
- 10th referral's wallet portion = ₱0 (exact fill)

**Verify:**
```sql
SELECT status, filled_amount, target_amount
FROM user_cd_status
WHERE user_id = [member_id]
ORDER BY assigned_at DESC
LIMIT 1;

SELECT COUNT(*) FROM cd_ledger WHERE user_id = [member_id];
-- Should be 10 entries
```

---

### 4.6 CD & Lifetime Cap Interaction

**Purpose:** Verify CD split happens **BEFORE** lifetime cap, and only the wallet overflow is cap-checked.

#### Test 6.1: CD Reduces What Gets Cap-Checked

**Setup:**
- Member has active CD with large target
- Member is near lifetime cap (e.g., ₱200 remaining before cap)
- Direct referral bonus = ₱500

**Steps:**
1. Set member near cap:
```sql
UPDATE users u
JOIN packages p ON p.id = u.package_id
SET u.lifetime_earned = (p.entry_fee * p.lifetime_cap_multiplier) - 200
WHERE u.id = [member_id];
```
2. Register a new member under this sponsor

**Expected Result:**
- CD portion (e.g., ₱500) goes to CD bucket **first**
- Wallet portion after CD = ₱0
- Lifetime cap check runs on wallet portion = ₱0
- **Nothing is blocked by the cap** because the entire bonus went to CD
- `lifetime_earned` does NOT increase (no wallet portion)
- `cap_status` remains `active`

**Verify:**
```sql
-- Check commission
SELECT amount, cap_deduction, description
FROM commissions
WHERE user_id = [member_id] AND type = 'direct_referral'
ORDER BY created_at DESC
LIMIT 1;
-- cap_deduction should be 0.00 (CD absorbed the whole bonus)

-- Check lifetime_earned
SELECT lifetime_earned, cap_status
FROM users WHERE id = [member_id];
-- Should be unchanged from before (or only increased by wallet portion)
```

---

#### Test 6.2: Cap Blocks Wallet Overflow After CD Split

**Setup:**
- Member has active CD but it is nearly full (only ₱100 remaining)
- Member is near lifetime cap (₱50 remaining)
- Direct referral bonus = ₱500

**Steps:**
1. Set CD remaining = ₱100:
```sql
UPDATE user_cd_status
SET filled_amount = target_amount - 100
WHERE user_id = [member_id] AND status = 'active';
```
2. Set lifetime remaining = ₱50:
```sql
UPDATE users u
JOIN packages p ON p.id = u.package_id
SET u.lifetime_earned = (p.entry_fee * p.lifetime_cap_multiplier) - 50
WHERE u.id = [member_id];
```
3. Register a new member

**Expected Result:**
- CD receives ₱100 (completes)
- Wallet portion = ₱400
- Cap allows only ₱50 of the ₱400
- ₱350 is blocked by cap
- Member becomes `capped`

**Verify:**
```sql
SELECT status, filled_amount FROM user_cd_status
WHERE user_id = [member_id] ORDER BY assigned_at DESC LIMIT 1;
-- status = 'completed', filled_amount = target_amount

SELECT lifetime_earned, cap_status FROM users WHERE id = [member_id];
-- cap_status = 'capped'

SELECT amount, cap_deduction, description
FROM commissions
WHERE user_id = [member_id] AND type = 'direct_referral'
ORDER BY created_at DESC LIMIT 1;
-- description should show CD split AND cap info
```

---

### 4.7 CD & DFI Interaction

**Purpose:** Verify DFI is skipped while CD is active.

#### Test 7.1: DFI Skipped for CD-Active Member

**Setup:**
- Member has `cd_active = 1` and active CD
- Member has `dfi_active = 1` and `dfi_days_used < daily_fixed_income_days`
- DFI is enabled globally

**Steps:**
1. Check DFI eligibility:
```sql
SELECT u.id, u.username, u.cd_active, u.dfi_active, u.dfi_days_used,
       p.daily_fixed_income, p.daily_fixed_income_days
FROM users u
JOIN packages p ON p.id = u.package_id
WHERE u.id = [member_id];
```
2. Run the midnight cron or trigger DFI payout manually

**Expected Result:**
- Member is **excluded** from DFI payout
- `daily_fixed_income_log` gets **no new row** for this member
- `dfi_days_used` does **not** increment

**Verify:**
```sql
SELECT COUNT(*) FROM daily_fixed_income_log
WHERE user_id = [member_id]
  AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR);
-- Should return 0

SELECT dfi_days_used FROM users WHERE id = [member_id];
-- Should be unchanged
```

---

#### Test 7.2: DFI Resumes After CD Completes

**Setup:**
- Member had active CD, now completed (Test 5.1 or 5.2 result)
- `cd_active = 0`
- `dfi_active = 1`

**Steps:**
1. Verify CD is completed:
```sql
SELECT cd_active FROM users WHERE id = [member_id];
-- Should be 0
```
2. Run midnight cron / trigger DFI

**Expected Result:**
- Member **is included** in DFI payout
- `daily_fixed_income_log` gets a new row
- `dfi_days_used` increments by 1

**Verify:**
```sql
SELECT COUNT(*) FROM daily_fixed_income_log
WHERE user_id = [member_id]
  AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR);
-- Should return 1

SELECT dfi_days_used FROM users WHERE id = [member_id];
-- Should have increased by 1
```

---

### 4.8 Admin Manual Complete / Cancel

**Purpose:** Verify admin can force-complete or cancel a CD.

#### Test 8.1: Manual Complete

**Setup:**
- Member has active CD that is NOT full (e.g., filled ₱1,000 of ₱5,000)

**Steps:**
1. As admin, go to the member's **View User** page
2. In the CD status card, click **✓ Mark Complete**
3. Confirm the dialog

**Expected Result:**
- Success message: "CD status marked as completed."
- CD status changes to `completed`
- `completed_at` is set
- `users.cd_active` becomes `0`
- Member is now DFI-eligible
- **The remaining unfilled amount is forgiven** (not collected from future earnings)

**Verify:**
```sql
SELECT status, filled_amount, target_amount, completed_at
FROM user_cd_status
WHERE user_id = [member_id]
ORDER BY assigned_at DESC
LIMIT 1;
-- status = 'completed', completed_at IS NOT NULL

SELECT cd_active FROM users WHERE id = [member_id];
-- cd_active = 0
```

---

#### Test 8.2: Cancel CD (Forfeit Filled Amount)

**Setup:**
- Member has active CD with some filled amount (e.g., ₱2,000 of ₱5,000)

**Steps:**
1. As admin, view the member
2. Click **✕ Cancel**
3. Enter a reason in the dialog (if prompted) and confirm

**Expected Result:**
- Success message: "CD status cancelled. Filled amount is forfeited."
- CD status changes to `cancelled`
- `cancelled_at` is set
- `users.cd_active` becomes `0`
- The ₱2,000 filled amount is **forfeited** (not returned)
- Member can now be assigned a new CD if needed

**Verify:**
```sql
SELECT status, filled_amount, cancelled_at, notes
FROM user_cd_status
WHERE user_id = [member_id]
ORDER BY assigned_at DESC
LIMIT 1;
-- status = 'cancelled', cancelled_at IS NOT NULL

SELECT cd_active FROM users WHERE id = [member_id];
-- cd_active = 0
```

---

#### Test 8.3: No Actions on Member Without Active CD

**Setup:**
- Member has no active CD (either never had one, or it was completed/cancelled)

**Steps:**
1. As admin, view the member

**Expected Result:**
- **Mark Complete** and **Cancel** buttons are **hidden**
- Only the **Assign CD** form is visible

---

### 4.9 CD Target Update on Package Change

**Purpose:** Verify CD target updates when member changes package.

#### Test 9.1: Target Updates on Package Upgrade

**Setup:**
- Member has active CD with target equal to old package's `entry_fee`
- There is a second package with higher `entry_fee`

**Steps:**
1. Note current CD target:
```sql
SELECT target_amount FROM user_cd_status
WHERE user_id = [member_id] AND status = 'active';
```
2. As admin, change the member's package to the higher-entry package
   - Admin → Users → View User → Change Package
3. Check if `CdStatus::updateTarget()` was called

**Expected Result:**
- CD target updates to the new package's `entry_fee`
- If the new target is lower than current `filled_amount`, CD should auto-complete

**Verify:**
```sql
SELECT target_amount, filled_amount, status
FROM user_cd_status
WHERE user_id = [member_id]
ORDER BY assigned_at DESC
LIMIT 1;
-- target_amount should match new package entry_fee
```

---

### 4.10 Member Dashboard & Ledger UI

**Purpose:** Verify members see correct CD information in their UI.

#### Test 10.1: Dashboard Shows CD Banner When Active

**Setup:** Member has active CD.

**Steps:**
1. Log in as the member
2. Go to **Member Dashboard**

**Expected Result:**
- A yellow/amber banner appears near the top with:
  - Title: "Commission-Deduct Bucket"
  - Target, Filled, Remaining amounts
  - Progress bar showing percentage
  - Badge: "CD Active"
  - Note: "DFI paused until full"

---

#### Test 10.2: Dashboard Hides CD Banner When Inactive

**Setup:** Member has no active CD (`cd_active = 0`).

**Steps:**
1. Log in as the member
2. View dashboard

**Expected Result:**
- No CD banner is shown
- DFI section appears normally (if eligible)

---

#### Test 10.3: Earnings Page Shows CD Ledger

**Setup:** Member has at least one CD ledger entry.

**Steps:**
1. Log in as the member
2. Go to **Earnings** page
3. Scroll to the **CD Bucket Ledger** section

**Expected Result:**
- A table shows all CD ledger entries with columns:
  - Date
  - Type (🤝 Pairing, 👤 Direct Referral, 🔗 Indirect Referral)
  - Gross Amount
  - To CD Bucket (amber/warning color)
  - To Wallet (green/success color)
  - From (source username)
- Badge shows current progress: "₱X / ₱Y (Z%)"
- If CD is completed, badge shows "Completed" in green

---

#### Test 10.4: Earnings Page Shows Empty Ledger

**Setup:** Member has active CD but no commissions yet.

**Steps:**
1. Log in as the member
2. Go to **Earnings**

**Expected Result:**
- CD Bucket Ledger section is visible
- Table shows message: "No CD entries yet."
- Progress badge shows "₱0.00 / ₱[target] (0.0%)"

---

### 4.11 Admin UI Indicators

**Purpose:** Verify admin sees CD status at a glance.

#### Test 11.1: Users List Shows CD Badge

**Setup:** At least one member has `cd_active = 1`.

**Steps:**
1. Log in as admin
2. Go to **Admin → Users**

**Expected Result:**
- Members with `cd_active = 1` display an amber badge: "⏳ CD"
- Members without CD do NOT show this badge

---

#### Test 11.2: User View Shows CD Status Card

**Setup:** Member has active CD.

**Steps:**
1. As admin, click **View** on a member with active CD

**Expected Result:**
- A card titled "⏳ Commission-Deduct Status" is visible
- Shows Target, Filled, Remaining with progress bar
- Badge: "Active" (amber)
- Action buttons: Mark Complete, Cancel

---

#### Test 11.3: User View Shows Completed CD History

**Setup:** Member had a CD that was completed or cancelled.

**Steps:**
1. As admin, view the member

**Expected Result:**
- Card shows last CD summary:
  - "Last CD: ₱X target, ₱Y filled, Completed on [date]"
- Badge: "Completed" (green) or "Cancelled" (red)
- Assign CD form is visible below

---

### 4.12 Regression Testing

**Purpose:** Ensure existing features still work when no CD is involved.

#### Test 12.1: Normal Registration (No CD)

**Setup:**
- Sponsor has `cd_active = 0`
- Sponsor is far from lifetime cap

**Steps:**
1. Register a new member under this sponsor

**Expected Result:**
- Registration succeeds
- Sponsor receives full direct referral bonus in wallet
- Full pairing bonuses paid to all active ancestors
- No CD ledger entries created
- Commission descriptions do NOT contain "to CD"

---

#### Test 12.2: E-Wallet Balance Correct with CD

**Setup:** Member has active CD.

**Steps:**
1. Note member's e-wallet balance before a commission
2. Trigger a commission that gets split (e.g., ₱500 → ₱300 to CD, ₱200 to wallet)

**Expected Result:**
- E-wallet increases by ONLY the wallet portion (₱200)
- NOT the gross amount (₱500)
- NOT the CD portion (₱300)

**Verify:**
```sql
-- Before: ewallet_balance = 1000.00
-- After:  ewallet_balance = 1200.00 (increased by 200, not 500)
SELECT ewallet_balance FROM users WHERE id = [member_id];
```

---

#### Test 12.3: Member Dashboard Loads Without Errors

**Steps:**
1. Log in as a regular member (with and without CD)
2. View dashboard, earnings, genealogy

**Expected Result:**
- All pages load without PHP errors
- No JavaScript errors in console
- Existing stats display correctly

---

#### Test 12.4: Admin Dashboard Loads Without Errors

**Steps:**
1. Log in as admin
2. View users list, user details, settings

**Expected Result:**
- All pages load without errors
- No PHP notices or warnings
- CD badges display correctly

---

## 5. Bug Reporting Template

When you find an issue, use this format:

```markdown
### Bug Report — CD Feature

**Tester:** [Your Name]
**Date:** [YYYY-MM-DD HH:MM]
**Test Case:** [e.g., 2.2 Partial Pairing Split]
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

**Database State (After):**
```sql
[Paste relevant query results]
```

**PHP Error Log:**
```
[Paste any errors from server logs]
```

**Screenshots:**
[Attach if applicable]

**Environment:**
- Browser: [Chrome/Firefox/Safari] v[X.X]
- OS: [Windows/Mac/Linux]
- Server: PHP [version], MySQL [version]
```

---

## 6. Pass/Fail Criteria

### CD Feature PASSES if ALL of these are true:

| # | Criteria | How to Verify |
|---|----------|---------------|
| 1 | Admin can assign CD to active member | Test 1.1 |
| 2 | Cannot assign CD to pending/non-active member | Test 1.2 |
| 3 | Cannot assign second active CD | Test 1.3 |
| 4 | CD splits pairing bonuses correctly | Test 2.1, 2.2 |
| 5 | CD splits direct referral bonuses correctly | Test 3.1, 3.2 |
| 6 | CD splits indirect referral bonuses correctly | Test 4.1 |
| 7 | CD auto-completes when target reached | Test 5.1, 5.2 |
| 8 | CD split happens BEFORE lifetime cap | Test 6.1 |
| 9 | Lifetime cap still works on wallet overflow | Test 6.2 |
| 10 | DFI is skipped while CD is active | Test 7.1 |
| 11 | DFI resumes after CD completes | Test 7.2 |
| 12 | Admin can manually complete CD | Test 8.1 |
| 13 | Admin can cancel CD (forfeits filled) | Test 8.2 |
| 14 | CD target updates on package change | Test 9.1 |
| 15 | Member dashboard shows CD banner | Test 10.1, 10.2 |
| 16 | Member earnings shows CD ledger | Test 10.3, 10.4 |
| 17 | Admin users list shows CD badge | Test 11.1 |
| 18 | Admin user view shows CD card | Test 11.2, 11.3 |
| 19 | Normal operations unaffected without CD | Test 12.1, 12.3, 12.4 |
| 20 | E-wallet only credited for wallet portion | Test 12.2 |

### CD Feature FAILS if ANY of these are true:

- ❌ CD is not split from any commission type
- ❌ Full commission goes to wallet while CD is active
- ❌ CD auto-complete doesn't trigger when target reached
- ❌ Lifetime cap blocks CD portion (CD should be before cap)
- ❌ DFI is paid to CD-active members
- ❌ E-wallet credited for CD portion
- ❌ Admin cannot assign/complete/cancel CD
- ❌ CD ledger doesn't record splits
- ❌ Member dashboard crashes when CD is active
- ❌ Existing registration/earnings features broken
- ❌ PHP fatal errors in server logs

---

## 7. FAQ

### Q: How do I quickly assign a CD for testing?
**A:** As admin, go to the member's View User page, scroll to the CD card, enter a target amount, and click **Assign CD**.

### Q: How do I check if a member's CD is active?
**A:**
```sql
SELECT cd_active FROM users WHERE id = [member_id];
-- OR
SELECT status, target_amount, filled_amount
FROM user_cd_status
WHERE user_id = [member_id] AND status = 'active';
```

### Q: Can a member have multiple CDs?
**A:** Yes, but only **one at a time** can be `active`. After a CD is completed or cancelled, a new one can be assigned.

### Q: What happens to the filled amount when admin cancels a CD?
**A:** It is **forfeited**. The member does NOT get that money back. The admin should communicate this clearly before cancelling.

### Q: How does CD interact with the lifetime cap?
**A:** CD split happens **first**. The portion that goes to the CD bucket is NOT subject to the lifetime cap. Only the wallet portion is cap-checked.

### Q: My member hit the lifetime cap but the CD is still active. Is that a bug?
**A:** No — that's expected. The cap only blocks the **wallet portion**. The CD portion continues to fill the bucket even if the member is capped. Once the CD completes, the member can reactivate (if within window) and resume earning.

### Q: How do I simulate a package change to test target updates?
**A:** As admin, go to the member's profile and change their package. Then check:
```sql
SELECT target_amount FROM user_cd_status
WHERE user_id = [member_id] ORDER BY assigned_at DESC LIMIT 1;
```

### Q: The CD ledger shows `withdrawable_amount = 0` but the wallet increased. Why?
**A:** Check if there was a cap deduction. The `withdrawable_amount` in the CD ledger is the amount AFTER the CD split but BEFORE the lifetime cap. If the cap blocked part of the wallet portion, the actual wallet credit will be less. Check `commissions.cap_deduction` for the full picture.

### Q: Can I test DFI without waiting for midnight?
**A:** Yes — run the cron manually:
```bash
php /path/to/site/cron/midnight_reset.php
```
Or trigger the DFI payout code directly if you have a test script.

### Q: What does "DFI paused until full" mean on the dashboard?
**A:** It means the member will NOT receive Daily Fixed Income payouts while their CD bucket is active. DFI resumes automatically when the CD completes.

### Q: I see a CD badge on the admin users list but the member says they don't see a CD banner. Why?
**A:** Check if `users.cd_active = 1` but there's no matching `user_cd_status` row with `status = 'active'`. This can happen if the flag is stale. The `CdStatus::fillBucket()` method has a safety check that resets `cd_active = 0` if no active CD row is found.

### Q: How do I clean up test CD data?
**A:**
```sql
-- WARNING: Test environment only!
DELETE FROM cd_ledger;
DELETE FROM user_cd_status;
UPDATE users SET cd_active = 0 WHERE role = 'member';
```

---

## Quick Reference Card

| Test | What to Do | What to Check |
|------|-----------|---------------|
| Assign CD | Admin assigns target on member profile | `cd_active = 1`, row in `user_cd_status` |
| Pairing split | Trigger pairing bonus on CD-active member | CD ledger entry, wallet portion credited |
| Direct split | Register under CD-active sponsor | `cd_ledger` type = `direct_referral` |
| Indirect split | Register under chain with CD-active ancestor | `cd_ledger` type = `indirect_referral` |
| Auto-complete | Fill CD to target via commissions | Status = `completed`, `cd_active = 0` |
| CD before cap | Near-cap member with CD gets commission | CD absorbs bonus, cap blocks 0 |
| Cap after CD | CD nearly full + near cap | CD fills, cap blocks wallet overflow |
| DFI skip | Run DFI while CD active | No DFI log row for CD member |
| DFI resume | Complete CD, run DFI | DFI log row created |
| Manual complete | Admin clicks Mark Complete | Status = `completed`, DFI eligible |
| Cancel CD | Admin clicks Cancel | Status = `cancelled`, filled forfeited |
| Target update | Change member's package | `target_amount` matches new `entry_fee` |
| Dashboard UI | Member views dashboard | CD banner visible with progress bar |
| Earnings UI | Member views earnings | CD ledger table visible |
| Admin badges | Admin views users list | Amber "⏳ CD" badge on CD members |

---

**End of Guide**

*CD Feature is ready for production when all 20 pass criteria are met.*
