# QA Tester Guide — Phase 2: Core Capping Engine & Commission Integration

**Version:** v1.0  
**Date:** 2026-05-27  
**System:** Altas Farm MLM Binary System  
**Phase:** 2 of 6 (Core Capping Engine & Commission Integration)

---

## Table of Contents

1. [What is Phase 2 Testing?](#1-what-is-phase-2-testing)
2. [Prerequisites](#2-prerequisites)
3. [Test Environment Setup](#3-test-environment-setup)
4. [Test Cases](#4-test-cases)
   - 4.1 [CapEngine Core Logic](#41-capengine-core-logic)
   - 4.2 [Binary Placement & Pair Skipping](#42-binary-placement--pair-skipping)
   - 4.3 [Direct Referral Cap Integration](#43-direct-referral-cap-integration)
   - 4.4 [Indirect Referral Cap Integration](#44-indirect-referral-cap-integration)
   - 4.5 [Cap Trigger & Status Changes](#45-cap-trigger--status-changes)
   - 4.6 [Cron & Batch Operations](#46-cron--batch-operations)
   - 4.7 [Regression Testing](#47-regression-testing)
5. [Bug Reporting Template](#5-bug-reporting-template)
6. [Pass/Fail Criteria](#6-passfail-criteria)
7. [FAQ](#7-faq)

---

## 1. What is Phase 2 Testing?

Phase 2 makes the **Lifetime Income Cap actually work**. In Phase 1, the cap was just a number stored in the database. Now it **enforces limits** on every commission type in real-time.

### What's New in Phase 2

| Component | What It Does | Where to Test |
|-----------|-------------|-------------|
| **CapEngine.php** | Central service that checks "can this member earn?" | Database queries, member dashboard |
| **Binary Pair Skipping** | Capped members are skipped in pair counting | Register members under capped sponsors |
| **Direct Referral Cap** | Sponsor's direct bonus is reduced if near cap | Register member under near-cap sponsor |
| **Indirect Referral Cap** | Unilevel chain stops if any level is capped | Register member under deep unilevel chain |
| **Cap Status Tracking** | `active` → `capped` → `perminact` transitions | Database state changes |
| **Midnight Cron v2** | Expires capped members past reactivation window | Run cron manually or wait for midnight |

### What's NOT in Phase 2 (Don't Test These Yet)

- ❌ Daily Fixed Income payouts — Phase 3
- ❌ Member reactivation workflow — Phase 4
- ❌ Member dashboard cap widgets — Phase 5
- ❌ Admin cap monitoring pages — Phase 6

> **Rule of Thumb:** If it involves clicking a "Reactivate" button or seeing DFI earnings, it's NOT in Phase 2.

---

## 2. Prerequisites

Before you start testing, ensure you have:

### Required Access
- [ ] Admin login credentials (username: `admin`, password: `Admin@1234`)
- [ ] Database access (phpMyAdmin, MySQL CLI, or similar)
- [ ] Server access to run cron manually (SSH or cron job control panel)

### Required Knowledge
- [ ] How to run SQL queries (SELECT, UPDATE)
- [ ] How to read PHP error logs (`/var/log/apache2/error.log` or similar)
- [ ] How to run PHP scripts from command line (`php script.php`)
- [ ] How to use browser DevTools (F12 → Console, Network tabs)

### Required Tools
| Tool | Purpose | Free? |
|------|---------|-------|
| Web Browser (Chrome/Firefox) | UI testing | Yes |
| Browser DevTools (F12) | Check for JS errors | Yes |
| phpMyAdmin or MySQL CLI | Verify database state | Yes |
| SSH or Terminal | Run cron manually | Yes |
| Text Editor (VS Code, Notepad++) | Check PHP files | Yes |

### Phase 1 Must Be Complete
- [ ] `install_v2.sql` or `migrate_v2.php` has been run
- [ ] `CapEngine.php` is deployed to `core/CapEngine.php`
- [ ] `Commission.php` is replaced with Phase 2 version
- [ ] `midnight_reset.php` is replaced with Phase 2 version
- [ ] Package settings show v2 fields (cap multiplier, reactivation fee, etc.)

---

## 3. Test Environment Setup

### Step 1: Backup (CRITICAL)

```bash
# Backup database before testing
mysqldump -u your_username -p u938213108_altas_db > backup_before_phase2.sql
```

### Step 2: Verify Phase 2 Files Are Deployed

Run these commands on your server:

```bash
# Check CapEngine exists
ls -la /path/to/site/core/CapEngine.php

# Check Commission.php has v2 changes
grep -n "CapEngine::" /path/to/site/core/Commission.php

# Check midnight_reset has v2 additions
grep -n "expireOldCappedUsers\|DailyFixedIncome" /path/to/site/cron/midnight_reset.php
```

**Expected Result:**
- `CapEngine.php` exists
- `Commission.php` contains multiple `CapEngine::` references
- `midnight_reset.php` contains `expireOldCappedUsers` and `DailyFixedIncome`

### Step 3: Prepare Test Data

You'll need members at different cap states. Run these SQL queries to create test scenarios:

```sql
-- Find a member with low earnings (safe to test with)
SELECT id, username, lifetime_earned, cap_status
FROM users
WHERE role = 'member'
ORDER BY lifetime_earned ASC
LIMIT 5;

-- Find a member with high earnings (near cap)
SELECT u.id, u.username, u.lifetime_earned,
       (p.entry_fee * p.lifetime_cap_multiplier) AS cap
FROM users u
JOIN packages p ON p.id = u.package_id
WHERE u.role = 'member'
HAVING lifetime_earned > (cap * 0.8)
LIMIT 5;
```

If no members exist yet, register 3-5 test members first using the registration code `DEMO-STAR-TKIT`.

---

## 4. Test Cases

### 4.1 CapEngine Core Logic

**Purpose:** Verify the central cap checking service works correctly.

#### Test 1.1: canEarn() — Member Far From Cap

**Setup:**
```sql
-- Find a member with lifetime_earned < 50% of cap
SELECT u.id, u.username, u.lifetime_earned,
       (p.entry_fee * p.lifetime_cap_multiplier) AS cap,
       ((p.entry_fee * p.lifetime_cap_multiplier) - u.lifetime_earned) AS remaining
FROM users u
JOIN packages p ON p.id = u.package_id
WHERE u.role = 'member' AND u.cap_status = 'active'
HAVING remaining > (cap * 0.5)
LIMIT 1;
```

**Steps:**
1. Note the member's `id` and `remaining` amount
2. Register a new member under this member as sponsor
3. Check if the sponsor received their direct referral bonus

**Expected Result:**
- Sponsor receives **full** direct referral bonus (e.g., ₱500)
- `lifetime_earned` increases by exactly ₱500
- `cap_status` remains `active`

**Verify:**
```sql
-- After registration
SELECT lifetime_earned, cap_status
FROM users
WHERE id = [sponsor_id];
-- Should show: lifetime_earned + 500, cap_status = 'active'
```

---

#### Test 1.2: canEarn() — Member Near Cap

**Setup:**
```sql
-- Find a member within 1 direct referral of cap
SELECT u.id, u.username, u.lifetime_earned,
       (p.entry_fee * p.lifetime_cap_multiplier) AS cap,
       ((p.entry_fee * p.lifetime_cap_multiplier) - u.lifetime_earned) AS remaining
FROM users u
JOIN packages p ON p.id = u.package_id
WHERE u.role = 'member' AND u.cap_status = 'active'
HAVING remaining BETWEEN 100 AND 500
LIMIT 1;
```

If no such member exists, manually set one up:
```sql
-- Manually set a member near cap (USE WITH CAUTION — test environment only!)
UPDATE users u
JOIN packages p ON p.id = u.package_id
SET u.lifetime_earned = (p.entry_fee * p.lifetime_cap_multiplier) - 200
WHERE u.id = [member_id];
```

**Steps:**
1. Note the member's `remaining` amount (e.g., ₱200)
2. Register a new member under this sponsor
3. Direct referral bonus is ₱500

**Expected Result:**
- Sponsor receives **only ₱200** (the remaining amount)
- ₱300 is **blocked** by the cap
- `lifetime_earned` reaches exactly the cap
- `cap_status` changes to `capped`
- `capped_at` is set to current timestamp

**Verify:**
```sql
-- After registration
SELECT lifetime_earned, cap_status, capped_at
FROM users
WHERE id = [sponsor_id];
-- Should show: lifetime_earned = cap, cap_status = 'capped', capped_at = NOW()

-- Check commission record
SELECT amount, cap_deduction, status
FROM commissions
WHERE user_id = [sponsor_id]
ORDER BY created_at DESC
LIMIT 1;
-- Should show: amount = 200.00, cap_deduction = 300.00, status = 'credited'
```

---

#### Test 1.3: canEarn() — Member Already Capped

**Setup:**
```sql
-- Find or create a capped member
SELECT id, username, cap_status, lifetime_earned
FROM users
WHERE cap_status = 'capped'
LIMIT 1;

-- If none exist, cap one manually:
UPDATE users u
JOIN packages p ON p.id = u.package_id
SET u.cap_status = 'capped',
    u.capped_at = NOW(),
    u.lifetime_earned = (p.entry_fee * p.lifetime_cap_multiplier)
WHERE u.id = [member_id];
```

**Steps:**
1. Note the capped member's `id`
2. Register a new member under this capped sponsor

**Expected Result:**
- Sponsor receives **₱0** direct referral bonus
- Full ₱500 is **blocked** by cap
- `lifetime_earned` does not change
- `cap_status` remains `capped`

**Verify:**
```sql
-- Check commission record
SELECT amount, cap_deduction, status
FROM commissions
WHERE user_id = [sponsor_id] AND type = 'direct_referral'
ORDER BY created_at DESC
LIMIT 1;
-- Should show: amount = 0.00, cap_deduction = 500.00, status = 'flushed'
```

---

#### Test 1.4: getCapStatus() — Returns Correct Data

**Steps:**
1. Pick any member and note their `id`
2. Run this test script (save as `test_cap_status.php`):

```php
<?php
require_once 'config/db.php';
require_once 'core/CapEngine.php';

$userId = [member_id]; // Replace with actual ID
$status = CapEngine::getCapStatus($userId);

echo "Cap Status for User {$userId}:
";
echo "  lifetime_earned:      " . $status['lifetime_earned'] . "
";
echo "  lifetime_cap:         " . $status['lifetime_cap'] . "
";
echo "  remaining:            " . $status['remaining'] . "
";
echo "  cap_status:           " . $status['cap_status'] . "
";
echo "  capped_at:            " . ($status['capped_at'] ?? 'NULL') . "
";
echo "  dfi_days_used:        " . $status['dfi_days_used'] . "
";
echo "  dfi_active:           " . $status['dfi_active'] . "
";
echo "  reactivation_fee:     " . $status['reactivation_fee'] . "
";
echo "  reactivation_window:  " . $status['reactivation_window'] . "
";
```

**Expected Result:**
- All values match what's in the database
- `remaining` = `lifetime_cap` - `lifetime_earned`
- `remaining` is never negative (should be 0 if capped)

---

### 4.2 Binary Placement & Pair Skipping

**Purpose:** Verify capped members are skipped in binary pair counting.

#### Test 2.1: Capped Member in Binary Tree — Pairs Skipped

**Setup:**
You need a binary tree structure like this:
```
        Grandparent (active)
        /         \
    Parent A     Parent B (capped)
    /    \        /    \
  Left   Right  Left   Right
```

**Steps:**
1. Find or create a capped member with children in the binary tree:
```sql
-- Find capped members with binary children
SELECT u.id, u.username, u.cap_status,
       COUNT(c.id) AS child_count
FROM users u
LEFT JOIN users c ON c.binary_parent_id = u.id
WHERE u.cap_status = 'capped'
GROUP BY u.id
HAVING child_count > 0
LIMIT 1;
```

2. Note the capped member's `id` and their parent (grandparent's `id`)
3. Register a new member that would create a pair for the capped member's parent
   - Place under the capped member's sibling (the other child of grandparent)

**Expected Result:**
- The **capped member (Parent B)** does NOT receive pairing bonus — they are skipped entirely
- The **grandparent** DOES still receive pairing bonus if a pair forms, because only the capped member is skipped; active ancestors above them continue to earn normally
- `pairs_paid` for the capped member does not increase
- The capped member's own `left_count`/`right_count` still increments (tree structure maintained)

**Verify:**
```sql
-- Before registration, note both Parent B and grandparent's pairs_paid
SELECT id, pairs_paid, pairs_paid_today
FROM users
WHERE id IN ([parent_b_id], [grandparent_id]);

-- After registration
SELECT id, pairs_paid, pairs_paid_today
FROM users
WHERE id IN ([parent_b_id], [grandparent_id]);
-- Parent B: SAME as before (no increase — capped, skipped)
-- Grandparent: MAY increase if a pair formed (grandparent is still active)
```

---

#### Test 2.2: Active Member in Binary Tree — Pairs Count Normally

**Setup:** Same tree structure, but Parent B is `active` instead of `capped`.

**Steps:**
1. Ensure Parent B has `cap_status = 'active'`
2. Register a new member under Parent B's empty side

**Expected Result:**
- Grandparent DOES receive pairing bonus
- `pairs_paid` increases normally

**Verify:**
```sql
-- After registration
SELECT pairs_paid, pairs_paid_today
FROM users
WHERE id = [grandparent_id];
-- Should show: pairs_paid increased by 1 (or more if multiple pairs formed)
```

---

#### Test 2.3: Mixed Tree — Some Capped, Some Active

**Setup:** Create a deeper tree with multiple capped and active members.

**Steps:**
1. Build a tree 3-4 levels deep
2. Cap 2-3 members at various levels (use SQL UPDATE)
3. Register a new member at the bottom
4. Trace the pairing bonuses up the tree

**Expected Result:**
- At each level, if the ancestor is `active`, they get pairing bonus (subject to their own cap)
- If ancestor is `capped` or `perminact`, they are skipped entirely (they earn no pairs themselves)
- The skip does NOT break the tree structure — leg counts still increment, and active ancestors above continue to earn normally

**Verify:**
```sql
-- Check all ancestors' commission records
SELECT u.username, u.cap_status, c.amount, c.type, c.status
FROM commissions c
JOIN users u ON u.id = c.user_id
WHERE c.source_user_id = [new_member_id]
   OR c.description LIKE '%[new_member_username]%'
ORDER BY c.created_at DESC;
```

---

### 4.3 Direct Referral Cap Integration

**Purpose:** Verify direct referral bonus respects lifetime cap.

#### Test 3.1: Full Direct Referral — Sponsor Far From Cap

**Steps:**
1. Find an active sponsor with `remaining > direct_ref_bonus`
2. Register a new member with this sponsor

**Expected Result:**
- Sponsor receives full `direct_ref_bonus` (e.g., ₱500)
- `ewallet_balance` increases by ₱500
- `lifetime_earned` increases by ₱500

**Verify:**
```sql
SELECT ewallet_balance, lifetime_earned
FROM users
WHERE id = [sponsor_id];
-- Both increased by 500

SELECT amount, cap_deduction
FROM commissions
WHERE user_id = [sponsor_id] AND type = 'direct_referral'
ORDER BY created_at DESC
LIMIT 1;
-- amount = 500.00, cap_deduction = 0.00
```

---

#### Test 3.2: Partial Direct Referral — Sponsor Near Cap

**Steps:**
1. Set sponsor's `lifetime_earned` to `cap - 100` (₱100 remaining)
2. Register a new member (direct bonus = ₱500)

**Expected Result:**
- Sponsor receives only ₱100
- ₱400 is blocked
- Sponsor immediately becomes `capped`
- `ewallet_balance` increases by only ₱100

**Verify:**
```sql
SELECT ewallet_balance, lifetime_earned, cap_status, capped_at
FROM users
WHERE id = [sponsor_id];
-- lifetime_earned = cap, cap_status = 'capped', capped_at = timestamp

SELECT amount, cap_deduction, status
FROM commissions
WHERE user_id = [sponsor_id] AND type = 'direct_referral'
ORDER BY created_at DESC
LIMIT 1;
-- amount = 100.00, cap_deduction = 400.00, status = 'credited'
```

---

#### Test 3.3: Zero Direct Referral — Sponsor Already Capped

**Steps:**
1. Ensure sponsor has `cap_status = 'capped'`
2. Register a new member

**Expected Result:**
- Sponsor receives ₱0
- Full bonus blocked
- Commission record shows `status = 'flushed'`

**Verify:**
```sql
SELECT amount, cap_deduction, status
FROM commissions
WHERE user_id = [sponsor_id] AND type = 'direct_referral'
ORDER BY created_at DESC
LIMIT 1;
-- amount = 0.00, cap_deduction = 500.00, status = 'flushed'
```

---

### 4.4 Indirect Referral Cap Integration

**Purpose:** Verify unilevel chain stops when any level is capped.

#### Test 4.1: Full Unilevel Chain — All Active

**Setup:**
Create a sponsor chain: Member A → Member B → Member C → Member D (A sponsored B, B sponsored C, C sponsored D)

**Steps:**
1. Ensure all members are `active` and far from cap
2. Register a new member under Member D

**Expected Result:**
- Member C (direct sponsor, L1) receives full L1 bonus
- Member B (L2) receives full L2 bonus
- Member A (L3) receives full L3 bonus
- Higher levels (if any) receive their bonuses

**Verify:**
```sql
SELECT u.username, u.level, c.amount, c.cap_deduction
FROM commissions c
JOIN users u ON u.id = c.user_id
WHERE c.source_user_id = [new_member_id]
  AND c.type = 'indirect_referral'
ORDER BY c.level ASC;
-- All amounts should be full bonuses, cap_deduction = 0.00
```

---

#### Test 4.2: Chain Stops at Capped Member

**Setup:**
Same chain, but Member B is `capped`.

**Steps:**
1. Set Member B to `capped`:
```sql
UPDATE users u
JOIN packages p ON p.id = u.package_id
SET u.cap_status = 'capped',
    u.capped_at = NOW(),
    u.lifetime_earned = (p.entry_fee * p.lifetime_cap_multiplier)
WHERE u.id = [member_b_id];
```

2. Register a new member under Member D

**Expected Result:**
- Member C (L1) receives full bonus
- Member B (L2) receives **₱0** — chain **stops here**
- Member A (L3) receives **nothing** — chain already stopped
- No L4-L10 bonuses paid at all

**Verify:**
```sql
SELECT u.username, c.amount, c.cap_deduction, c.status, c.level
FROM commissions c
JOIN users u ON u.id = c.user_id
WHERE c.source_user_id = [new_member_id]
  AND c.type = 'indirect_referral'
ORDER BY c.level ASC;

-- Expected:
-- Member C | 300.00 | 0.00 | credited | 1
-- Member B | 0.00   | 200.00 | flushed | 2
-- (Member A should NOT appear — chain stopped)
```

---

#### Test 4.3: Partial Chain — Member Near Cap

**Setup:**
Member B is near cap (₱50 remaining), L2 bonus is ₱200.

**Steps:**
1. Set Member B's `lifetime_earned` to `cap - 50`
2. Register a new member under Member D

**Expected Result:**
- Member C (L1) receives full ₱300
- Member B (L2) receives only ₱50, then becomes `capped`
- Member A (L3+) receives **nothing** — chain stopped at Member B

**Verify:**
```sql
SELECT u.username, c.amount, c.cap_deduction, c.status, c.level
FROM commissions c
JOIN users u ON u.id = c.user_id
WHERE c.source_user_id = [new_member_id]
  AND c.type = 'indirect_referral'
ORDER BY c.level ASC;

-- Expected:
-- Member C | 300.00 | 0.00  | credited | 1
-- Member B | 50.00  | 150.00 | credited | 2
-- (Member A should NOT appear)

-- Member B should now be capped
SELECT cap_status, lifetime_earned
FROM users
WHERE id = [member_b_id];
-- cap_status = 'capped', lifetime_earned = cap
```

---

### 4.5 Cap Trigger & Status Changes

**Purpose:** Verify `active` → `capped` → `perminact` transitions work correctly.

#### Test 5.1: active → capped Transition

**Steps:**
1. Find an active member with `lifetime_earned = 0`
2. Register enough members under them to reach cap
   - For Starter package: cap = ₱30,000
   - Direct referral = ₱500 each → need 60 registrations
   - Or use SQL to simulate:
```sql
UPDATE users u
JOIN packages p ON p.id = u.package_id
SET u.lifetime_earned = (p.entry_fee * p.lifetime_cap_multiplier) - 500
WHERE u.id = [member_id];
```
3. Register one more member

**Expected Result:**
- Before: `cap_status = 'active'`
- After: `cap_status = 'capped'`, `capped_at = NOW()`

**Verify:**
```sql
SELECT cap_status, capped_at, lifetime_earned
FROM users
WHERE id = [member_id];
-- cap_status = 'capped', capped_at = [timestamp], lifetime_earned = 30000.00
```

---

#### Test 5.2: capped → perminact Transition (via Cron)

**Steps:**
1. Find a capped member:
```sql
SELECT id, username, capped_at
FROM users
WHERE cap_status = 'capped'
LIMIT 1;
```

2. If `capped_at` is recent, manually set it to past the window:
```sql
UPDATE users
SET capped_at = DATE_SUB(NOW(), INTERVAL 20 DAY)
WHERE id = [member_id];
-- Window is 15 days, so 20 days ago = expired
```

3. Run the midnight cron manually:
```bash
php /path/to/site/cron/midnight_reset.php
```

**Expected Result:**
- Member's `cap_status` changes from `capped` to `perminact`
- Log shows: `Cap expiration: 1 member(s) moved from 'capped' to 'perminact'`

**Verify:**
```sql
SELECT cap_status, capped_at
FROM users
WHERE id = [member_id];
-- cap_status = 'perminact', capped_at still shows original timestamp
```

---

#### Test 5.3: perminact Member Cannot Earn

**Steps:**
1. Find a `perminact` member
2. Register a new member under them

**Expected Result:**
- `perminact` sponsor receives **₱0** direct referral
- Full bonus blocked
- Commission shows `status = 'flushed'`

**Verify:**
```sql
SELECT amount, cap_deduction, status
FROM commissions
WHERE user_id = [perminact_member_id]
  AND type = 'direct_referral'
ORDER BY created_at DESC
LIMIT 1;
-- amount = 0.00, cap_deduction = 500.00, status = 'flushed'
```

---

#### Test 5.4: capped Member Still Within Window

**Steps:**
1. Cap a member (set `capped_at = NOW()`)
2. Do NOT run cron
3. Register a new member under them

**Expected Result:**
- Sponsor is `capped` → receives **₱0**
- But `cap_status` remains `capped` (not yet `perminact`)
- Member can still reactivate (Phase 4)

**Verify:**
```sql
SELECT cap_status, capped_at
FROM users
WHERE id = [member_id];
-- cap_status = 'capped', capped_at = [recent timestamp]
```

---

### 4.6 Cron & Batch Operations

**Purpose:** Verify midnight cron handles all v2 operations correctly.

#### Test 6.1: Cron Runs Without Errors

**Steps:**
1. Run cron manually:
```bash
php /path/to/site/cron/midnight_reset.php
```

**Expected Result:**
- No PHP fatal errors
- Log output shows all steps:
```
[INFO ] ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
[INFO ] Midnight reset started — Altas Farm
...
[OK   ] pairs_paid_today reset to 0. Rows updated: 150
[OK   ] Verification passed — all pairs_paid_today confirmed at 0.
[OK   ] last_reset timestamp updated: 2026-05-28 00:00:00
[OK   ] Cap expiration: 3 member(s) moved from 'capped' to 'perminact'.
[INFO ] DFI payout: Stub active — Phase 3 not yet deployed.
[OK   ] Reset complete. Duration: 45.23ms
```

---

#### Test 6.2: Cron with No Capped Members to Expire

**Steps:**
1. Ensure no members are past their reactivation window
```sql
SELECT COUNT(*) FROM users
WHERE cap_status = 'capped'
  AND capped_at < DATE_SUB(NOW(), INTERVAL 15 DAY);
-- Should return 0
```

2. Run cron

**Expected Result:**
- Log shows: `Cap expiration: No members past reactivation window.`

---

#### Test 6.3: Cron with Multiple Capped Members

**Steps:**
1. Create multiple capped members with expired windows:
```sql
-- Create 3 test capped members with expired windows
UPDATE users
SET cap_status = 'capped',
    capped_at = DATE_SUB(NOW(), INTERVAL 20 DAY)
WHERE id IN ([id1], [id2], [id3]);
```

2. Run cron

**Expected Result:**
- Log shows: `Cap expiration: 3 member(s) moved from 'capped' to 'perminact'.`
- All 3 members now have `cap_status = 'perminact'`

---

#### Test 6.4: Cron with DailyFixedIncome Stub (Phase 2)

**Steps:**
1. Ensure `core/DailyFixedIncome.php` does NOT exist (Phase 2 state)
2. Run cron

**Expected Result:**
- Cron completes successfully
- Log shows: `DFI payout: Stub active — Phase 3 not yet deployed.`
- No fatal errors

---

#### Test 6.5: Cron Log File Rotation

**Steps:**
1. Run cron multiple times across different months
2. Check log directory:
```bash
ls -la /path/to/site/cron/logs/
```

**Expected Result:**
- Log files named: `reset_2026-05.log`, `reset_2026-06.log`, etc.
- Each file contains only entries from that month

---

### 4.7 Regression Testing

**Purpose:** Ensure existing features still work after Phase 2 deployment.

#### Test 7.1: Normal Registration (No Cap Involved)

**Steps:**
1. Register a new member under an active sponsor far from cap

**Expected Result:**
- Registration succeeds
- Sponsor receives full direct referral bonus
- Binary placement works normally
- All commissions credited normally
- No cap-related side effects

---

#### Test 7.2: E-Wallet Balance Correct

**Steps:**
1. Check sponsor's e-wallet before registration
2. Register member
3. Check sponsor's e-wallet after

**Expected Result:**
- E-wallet increases by exactly the `amount` (not `amount + cap_deduction`)
- If capped, e-wallet does NOT increase (or increases by partial amount only)

**Verify:**
```sql
-- Before
SELECT ewallet_balance FROM users WHERE id = [sponsor_id];
-- Returns: 5000.00

-- After (full bonus)
SELECT ewallet_balance FROM users WHERE id = [sponsor_id];
-- Returns: 5500.00 (increased by 500)

-- After (capped, partial)
SELECT ewallet_balance FROM users WHERE id = [sponsor_id];
-- Returns: 5050.00 (increased by 50 only)
```

---

#### Test 7.3: Admin Dashboard Still Works

**Steps:**
1. Log in as admin
2. View admin dashboard
3. View members list
4. View package settings

**Expected Result:**
- All pages load without errors
- Existing stats display correctly
- No PHP notices or warnings

---

#### Test 7.4: Member Dashboard Still Works

**Steps:**
1. Log in as a regular member
2. View dashboard
3. View earnings page
4. View genealogy

**Expected Result:**
- All pages load without errors
- Existing stats (balance, pairs, earnings) display correctly
- No JavaScript errors in console

---

## 5. Bug Reporting Template

When you find an issue, use this format:

```markdown
### Bug Report — Phase 2

**Tester:** [Your Name]
**Date:** [YYYY-MM-DD HH:MM]
**Test Case:** [e.g., 3.2 Partial Direct Referral]
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
[Paste any errors from /var/log/apache2/error.log or similar]
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

### Phase 2 PASSES if ALL of these are true:

| # | Criteria | How to Verify |
|---|----------|---------------|
| 1 | `CapEngine.php` deployed and loads | `ls core/CapEngine.php` + no fatal errors |
| 2 | `canEarn()` returns correct allowed/blocked | Test 1.1, 1.2, 1.3 |
| 3 | `getCapStatus()` returns accurate data | Test 1.4 + SQL comparison |
| 4 | Capped members skipped in binary pairs | Test 2.1, 2.2, 2.3 |
| 5 | Direct referral respects cap (full/partial/zero) | Test 3.1, 3.2, 3.3 |
| 6 | Indirect chain stops at capped member | Test 4.1, 4.2, 4.3 |
| 7 | `active` → `capped` transition works | Test 5.1 |
| 8 | `capped` → `perminact` transition works | Test 5.2 |
| 9 | `perminact` members earn nothing | Test 5.3 |
| 10 | Midnight cron runs without errors | Test 6.1 |
| 11 | Cron expires capped members correctly | Test 6.2, 6.3 |
| 12 | Cron handles missing DFI gracefully | Test 6.4 |
| 13 | E-wallet only credited for `allowed` amount | Test 7.2 |
| 14 | Existing features unbroken | Test 7.1, 7.3, 7.4 |
| 15 | No PHP fatal errors in logs | Check error log after all tests |

### Phase 2 FAILS if ANY of these are true:

- ❌ Cap not enforced on any commission type
- ❌ Capped members still earn pairing bonuses
- ❌ Indirect chain continues past capped member
- ❌ E-wallet credited for blocked amounts
- ❌ `cap_status` doesn't change when cap reached
- ❌ Cron crashes or throws fatal errors
- ❌ Existing registration/payout/dashboard features broken
- ❌ PHP fatal errors in server logs

---

## 7. FAQ

### Q: How do I create a capped member quickly for testing?
**A:** Use SQL (test environment only!):
```sql
UPDATE users u
JOIN packages p ON p.id = u.package_id
SET u.cap_status = 'capped',
    u.capped_at = NOW(),
    u.lifetime_earned = (p.entry_fee * p.lifetime_cap_multiplier)
WHERE u.id = [member_id];
```

### Q: The indirect chain didn't stop at the capped member. Why?
**A:** Check that the capped member is actually in the sponsor chain (not just the binary tree). Unilevel uses `sponsor_id`, not `binary_parent_id`. Verify:
```sql
SELECT id, username, sponsor_id FROM users WHERE id = [capped_member_id];
SELECT id, username, sponsor_id FROM users WHERE id = [member_above_capped];
-- The member above should have sponsor_id = capped_member_id
```

### Q: How do I run the cron manually?
**A:**
```bash
php /path/to/site/cron/midnight_reset.php
```
Or via browser (if accessible):
```
https://yoursite.com/cron/midnight_reset.php
```

### Q: Can I test with the demo registration code?
**A:** Yes — `DEMO-STAR-TKIT` works for all test registrations. Each use consumes the code, so you may need to generate more via admin panel.

### Q: What if a member should be capped but isn't?
**A:** Check:
1. `lifetime_earned` >= `entry_fee * lifetime_cap_multiplier`?
2. `cap_status` is still `active`?
3. Any PHP errors during the registration that triggered the cap?

### Q: The cron log shows "CapEngine not available." What do I do?
**A:** Deploy `core/CapEngine.php` from Phase 2. The cron gracefully degrades without it, but cap expiration won't run.

### Q: How do I check if a member is in someone's binary tree?
**A:**
```sql
-- Find all ancestors in binary tree
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

### Q: Can I test cap expiration without waiting 15 days?
**A:** Yes — manually set `capped_at` to a past date:
```sql
UPDATE users SET capped_at = DATE_SUB(NOW(), INTERVAL 20 DAY)
WHERE id = [member_id];
```
Then run the cron.

### Q: What does "DFI payout: Stub active" mean in the cron log?
**A:** It means `core/DailyFixedIncome.php` hasn't been deployed yet (Phase 3). This is expected in Phase 2. The cron completes successfully — it just skips DFI payout.

### Q: I see `cap_deduction` in commissions but the member wasn't near cap. Is that a bug?
**A:** No — `cap_deduction` should be `0.00` for members far from cap. If it's non-zero, that's a bug. Report it.

---

## Quick Reference Card

| Test | What to Do | What to Check |
|------|-----------|-------------|
| Far from cap | Register under active sponsor | Full bonus paid |
| Near cap | Set `lifetime_earned` near cap, then register | Partial bonus, then capped |
| Already capped | Register under capped sponsor | Zero bonus, flushed status |
| Binary skip | Register under capped member's subtree | Ancestor pairs don't increase |
| Indirect stop | Cap a middle member, register below | Chain stops at capped member |
| Cap transition | Trigger cap via registration | `active` → `capped` |
| Window expiration | Set `capped_at` to past, run cron | `capped` → `perminact` |
| Cron run | `php midnight_reset.php` | No errors, correct log output |

---

**End of Guide**

*Ready for Phase 3 when all 15 pass criteria are met.*
