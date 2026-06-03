# QA Tester Guide — Referral Link Registration

**Version:** v1.1
**Date:** 2026-06-03
**System:** Altas Farm MLM Binary System
**Feature:** Referral Link Registration (Pending Activation Flow)

---

## Table of Contents

1. [What is Referral Link Registration Testing?](#1-what-is-referral-link-registration-testing)
2. [Prerequisites](#2-prerequisites)
3. [Test Environment Setup](#3-test-environment-setup)
4. [Test Cases](#4-test-cases)
   - 4.1 [Referral Link Generation & UI](#41-referral-link-generation--ui)
   - 4.2 [Referral Mode Registration Flow](#42-referral-mode-registration-flow)
   - 4.3 [Auto-Placement (BFS Left-First)](#43-auto-placement-bfs-left-first)
   - 4.4 [Pending Status & Database State](#44-pending-status--database-state)
   - 4.5 [Commission Guards for Pending Users](#45-commission-guards-for-pending-users)
   - 4.6 [Activation Flow](#46-activation-flow)
   - 4.7 [Activation Commission Firing](#47-activation-commission-firing)
   - 4.8 [Seat Limit Integration](#48-seat-limit-integration)
   - 4.9 [Regression Testing](#49-regression-testing)
   - 4.10 [UI & UX Improvements](#410-ui--ux-improvements)
5. [Bug Reporting Template](#5-bug-reporting-template)
6. [Pass/Fail Criteria](#6-passfail-criteria)
7. [FAQ](#7-faq)

---

## 1. What is Referral Link Registration Testing?

Referral Link Registration lets anyone sign up through a special link (e.g., `/?page=register&sponsor=alice&ref=1`) **without needing a registration code or e-wallet upfront**. Their binary position is reserved immediately, but they stay in `pending` status until they activate later.

### What's New in This Feature

| Component | What It Does | Where to Test |
|-----------|-------------|-------------|
| **Referral Link** | `?sponsor=USERNAME&ref=1` triggers referral mode | Browser URL bar, Genealogy page |
| **Auto-Placement** | System finds next open slot (left-first BFS) | Register multiple users, check tree |
| **Pending Status** | User gets `status = 'pending'`, no package yet | Database, Dashboard banner |
| **Activation Page** | Pending users pay later via code or e-wallet | `/?page=activate` |
| **Commission Guards** | Pending users can't earn or trigger commissions | Register under pending sponsor |
| **Seat Limit** | Pending users still count toward the seat limit | Dashboard seat counter |
| **Lazy Tree Loading** | Binary tree loads 4 levels at a time, + expands deeper | Genealogy → Binary Tree |
| **E-Wallet Transfer** | Non-withdrawable funds can be transferred | Send Money page |

### What's NOT Changed (Don't Test These as New)

- ✅ Normal registration (`?page=register` without `&ref=1`) still works exactly as before
- ✅ E-wallet registration by logged-in members still works
- ✅ Admin user management, packages, codes, payouts unchanged
- ✅ Cap engine, DFI, reactivation unchanged

> **Rule of Thumb:** If the URL doesn't have `&ref=1`, it's the old flow and should work exactly as before.

---

## 2. Prerequisites

Before you start testing, ensure you have:

### Required Access
- [ ] Admin login credentials (username: `admin`, password: `Admin@1234`)
- [ ] Database access (phpMyAdmin, MySQL CLI, or similar)
- [ ] At least one active member account with a known username (to use as sponsor)
- [ ] An unused registration code (generate via admin panel if needed)

### Required Knowledge
- [ ] How to copy/paste URLs and modify URL parameters
- [ ] How to run basic SQL queries (SELECT, UPDATE)
- [ ] How to use browser DevTools (F12 → Console, Network tabs)
- [ ] How to check PHP error logs

### Required Tools
| Tool | Purpose | Free? |
|------|---------|-------|
| Web Browser (Chrome/Firefox) | UI testing | Yes |
| Browser DevTools (F12) | Check for JS errors | Yes |
| phpMyAdmin or MySQL CLI | Verify database state | Yes |
| Text Editor | Check PHP files if needed | Yes |

### Pre-Test Checklist
- [ ] `migrations/002_add_pending_payment_method.sql` has been run (extends `reg_payment_method` ENUM)
- [ ] `core/Commission.php` has the pending-user guards
- [ ] `models/User.php` has the `activate()` method
- [ ] `controllers/AuthController.php` has `findNextBinarySlot()`
- [ ] Seat limit is not reached (`seat_limit` setting > current member count)

---

## 3. Test Environment Setup

### Step 1: Backup (CRITICAL)

```bash
# Backup database before testing
mysqldump -u your_username -p u938213108_altas_db > backup_before_referral_link.sql
```

### Step 2: Verify Files Are Deployed

Run these checks on your server:

```bash
# Check AuthController has referral mode logic
grep -n "findNextBinarySlot\|isReferralMode" /path/to/site/controllers/AuthController.php

# Check MemberController has activation methods
grep -n "function activate\|function doActivate" /path/to/site/controllers/MemberController.php

# Check User model has activate method
grep -n "function activate" /path/to/site/models/User.php

# Check Commission has pending guards
grep -n "newUserIsActive" /path/to/site/core/Commission.php
```

**Expected Result:**
- All `grep` commands return matching lines
- No "pattern not found" errors

### Step 3: Verify Database Schema

```sql
-- Check reg_payment_method accepts 'pending'
SHOW COLUMNS FROM users LIKE 'reg_payment_method';
-- Expected: Type = enum('code','ewallet','pending')

-- Check status accepts 'pending'
SHOW COLUMNS FROM users LIKE 'status';
-- Expected: Type = enum('active','suspended','pending')
```

### Step 4: Prepare Test Sponsor

Pick an active member to be your test sponsor. If you only have `admin`:

```sql
-- Find any active member
SELECT id, username, status FROM users WHERE role = 'member' AND status = 'active' LIMIT 1;
```

If none exist, register one normally first (using a code), then use their username as sponsor.

**Write down:**
- Sponsor username: `_________________`
- Sponsor ID: `_________________`

---

## 4. Test Cases

### 4.1 Referral Link Generation & UI

**Purpose:** Verify members can find and copy their referral link.

#### Test 1.1: Genealogy Page Shows Referral Link

**Steps:**
1. Log in as any active member
2. Go to **Genealogy** page (`/?page=genealogy`)
3. Look at the top of the page, right of the "Binary Tree / Referral Network" tabs

**Expected Result:**
- A small input field shows: `https://yoursite.com/?page=register&sponsor=YOURNAME&ref=1`
- A 📋 (clipboard) button sits to the right of the input
- On mobile (narrow screen), the link input wraps below the tabs

**Verify:**
1. Click the 📋 button
2. Paste into a text editor (Ctrl+V)
3. The pasted URL should match exactly

---

#### Test 1.2: Referral Link Contains Correct Sponsor

**Steps:**
1. Log in as member `@alice`
2. Go to Genealogy page
3. Copy the referral link

**Expected Result:**
- URL contains `sponsor=alice`
- URL contains `ref=1`

**Verify:**
```
https://yoursite.com/?page=register&sponsor=alice&ref=1
```

---

#### Test 1.3: Normal Registration Link Still Works

**Steps:**
1. Visit `/?page=register` (no sponsor, no ref)
2. Visit `/?page=register&sponsor=alice` (no ref parameter)

**Expected Result:**
- Both show the **normal registration form**
- Step 1 shows "Registration Code" input (guests)
- Or payment method toggle (logged-in)
- Sponsor field is editable (not locked)

**Verify:**
- The form does NOT show the blue "You are registering via a referral link" info box

---

### 4.2 Referral Mode Registration Flow

**Purpose:** Verify the referral registration form behaves differently from normal registration.

#### Test 2.1: Guest Registers via Referral Link

**Steps:**
1. While **logged out**, visit:
   ```
   /?page=register&sponsor=alice&ref=1
   ```
   (Replace `alice` with your test sponsor's username)

**Expected Result:**
- Form shows a **blue info box**: "🔗 You are registering via a referral link..."
- **Step bar** shows only 2 steps: "1. Account Setup" → "2. Confirm"
- **Sponsor field** is pre-filled with `alice` and is **readonly** (grey background)
- **Upline field** is auto-filled with a username and is **editable** (not readonly)
- **Position** (Left/Right) is pre-selected but **can be changed** (clickable)
- There is **NO payment step** — no code input, no package selector

---

#### Test 2.2: Step 1 is Skipped in Referral Mode

**Steps:**
1. Visit referral link from Test 2.1
2. Observe the first visible form section

**Expected Result:**
- The form starts directly at "Account Setup" (username, password, sponsor, upline, position)
- There is NO "Registration Code" input visible
- There is NO "Payment Method" toggle
- The "Continue →" button from Step 1 is not shown

---

#### Test 2.3: Normal Fields Still Required

**Steps:**
1. Visit referral link
2. Leave username empty
3. Click "Review →"

**Expected Result:**
- Form validation prevents proceeding
- Error hint: "Please choose a valid, available username"

**Steps (continue):**
4. Enter a username that already exists
5. Click "Review →"

**Expected Result:**
- Error hint shows: "Username is taken"

**Steps (continue):**
6. Enter password "123"
7. Click "Review →"

**Expected Result:**
- Alert: "Password must be at least 8 characters"

**Steps (continue):**
8. Enter password "password123" in both password fields
9. Click "Review →"

**Expected Result:**
- Proceeds to Step 2 (Review)
- Review table shows "Activation: Pending" badge
- Does NOT show Payment, Code, or Package rows

---

#### Test 2.4: Submit Referral Registration

**Steps:**
1. Fill in all required fields correctly on referral form
2. Click "✓ Complete Registration"

**Expected Result:**
- Registration succeeds
- Flash message: "Welcome! Your account is pending activation. Activate now to unlock earning features."
- User is automatically logged in
- Redirected to Dashboard

**Verify (Database):**
```sql
SELECT username, status, package_id, reg_payment_method, sponsor_id, binary_parent_id, binary_position
FROM users
ORDER BY id DESC
LIMIT 1;
```

**Expected Query Result:**
| Column | Value |
|--------|-------|
| `username` | The new username |
| `status` | `pending` |
| `package_id` | `NULL` |
| `reg_payment_method` | `pending` |
| `sponsor_id` | ID of `alice` |
| `binary_parent_id` | ID of selected upline |
| `binary_position` | `left` or `right` |

---

#### Test 2.5: Editable Upline and Position in Referral Mode

**Steps:**
1. Visit `/?page=register&sponsor=alice&ref=1`
2. Observe the Upline Username field
3. Try editing the Upline Username field
4. Try clicking the Left/Right position radio buttons

**Expected Result:**
- Upline field is **editable** (not greyed out, not readonly)
- Position radios are **clickable** and can be changed
- Changing position shows slot availability hints (✓ Free / ✗ Taken)

**Verify:**
- Clear the upline field and type a different valid username
- The slot status updates dynamically

---

### 4.3 Auto-Placement (BFS Left-First)

**Purpose:** Verify the system places users in the correct binary slots.

#### Test 3.1: First Referral Gets Left Slot

**Prerequisites:**
- Sponsor `@alice` has NO referrals yet (empty left and right slots)

**Steps:**
1. Visit `/?page=register&sponsor=alice&ref=1`
2. Register user `@bob1`

**Expected Result:**
- `binary_parent_id` = Alice's ID
- `binary_position` = `left`

**Verify:**
```sql
SELECT username, binary_parent_id, binary_position
FROM users
WHERE username = 'bob1';
```

---

#### Test 3.2: Second Referral Gets Right Slot

**Steps:**
1. Visit `/?page=register&sponsor=alice&ref=1`
2. Register user `@bob2`

**Expected Result:**
- `binary_parent_id` = Alice's ID
- `binary_position` = `right`

**Verify:**
```sql
SELECT username, binary_parent_id, binary_position
FROM users
WHERE username = 'bob2';
```

---

#### Test 3.3: Third Referral Goes to Next Level (BFS)

**Prerequisites:**
- Alice has `@bob1` on left and `@bob2` on right

**Steps:**
1. Visit `/?page=register&sponsor=alice&ref=1`
2. Register user `@bob3`

**Expected Result:**
- `binary_parent_id` = Bob1's ID (left child of Alice)
- `binary_position` = `left` (Bob1's left slot)

**Verify:**
```sql
SELECT u.username, u.binary_parent_id, p.username AS parent_name, u.binary_position
FROM users u
LEFT JOIN users p ON p.id = u.binary_parent_id
WHERE u.username = 'bob3';
-- Expected: parent_name = 'bob1', position = 'left'
```

---

#### Test 3.4: Fourth Referral Fills Bob1's Right

**Steps:**
1. Visit `/?page=register&sponsor=alice&ref=1`
2. Register user `@bob4`

**Expected Result:**
- `binary_parent_id` = Bob1's ID
- `binary_position` = `right`

---

#### Test 3.5: Fifth Referral Goes to Bob2's Left

**Steps:**
1. Visit `/?page=register&sponsor=alice&ref=1`
2. Register user `@bob5`

**Expected Result:**
- `binary_parent_id` = Bob2's ID
- `binary_position` = `left`

**Verify Pattern:**
The placement order should be:
1. Alice → Left (Bob1)
2. Alice → Right (Bob2)
3. Bob1 → Left (Bob3)
4. Bob1 → Right (Bob4)
5. Bob2 → Left (Bob5)
6. Bob2 → Right (Bob6)
7. Bob3 → Left (Bob7)
...and so on (Breadth-First, left before right)

---

#### Test 3.6: Tree Full Fallback

**Purpose:** Verify what happens when the sponsor's entire tree is full.

**Setup (Rare):** This is hard to trigger naturally. Simulate by creating a deep full tree or testing the logic directly:

```php
<?php
// Save as test_tree_full.php
require_once 'config/db.php';
require_once 'controllers/AuthController.php';

// Find a sponsor with no slots (create a tiny tree first)
$result = AuthController::findNextBinarySlot(1); // admin = root
var_dump($result);
// If admin tree is huge, this may return null
```

**Expected Result:**
- If `findNextBinarySlot()` returns `null`, the registration form falls back to **normal mode**
- The user sees the standard registration form (not referral mode)
- Sponsor is still pre-filled but editable
- No "pending activation" message

---

### 4.4 Pending Status & Database State

**Purpose:** Verify pending users are stored correctly and don't break existing features.

#### Test 4.1: Pending User Dashboard

**Steps:**
1. Log in as a pending user (registered via referral link)
2. View the dashboard (`/?page=dashboard`)

**Expected Result:**
- Yellow banner at top: "⏳ Account Pending Activation"
- Banner text: "Your binary position is reserved. Activate your account with a registration code or e-wallet to unlock all earning features."
- Banner has an "⚡ Activate Now" button
- KPI cards show ₱0.00 for all earnings (no commissions yet)
- Binary tree shows the user's position correctly

---

#### Test 4.2: Pending User Cannot Earn

**Setup:**
- You need an active sponsor `@alice`
- A pending user `@pending1` registered under Alice

**Steps:**
1. Register a NEW member using **normal registration** (code) under `@alice` as sponsor
2. Check if `@pending1` (the pending user) received any commission

**Expected Result:**
- `@pending1` receives **ZERO** commissions
- Only active members earn

**Verify:**
```sql
-- Check if pending user has any commission records
SELECT COUNT(*) FROM commissions WHERE user_id = [pending1_id];
-- Expected: 0
```

---

#### Test 4.3: Pending User Counts Toward Seat Limit

**Steps:**
1. Note the current "Remaining seats" on dashboard or admin settings
2. Register a new user via referral link (pending)
3. Check "Remaining seats" again

**Expected Result:**
- Remaining seats decreased by 1
- Pending users count toward the seat limit

**Verify:**
```sql
SELECT COUNT(*) FROM users WHERE role = 'member';
-- This count includes pending users
```

---

#### Test 4.4: Pending User in Admin Users List

**Steps:**
1. Log in as admin
2. Go to **Members** (`/?page=admin_users`)
3. Look at the stats row at top

**Expected Result:**
- Stat card shows: "Pending" with the correct count
- The pending user appears in the list with a **yellow/orange** "Pending" badge
- Pending users do **NOT** have an "Activate/Unsuspend" toggle button

**Steps (continue):**
4. Use the "Status" filter dropdown → select "pending"

**Expected Result:**
- List filters to show only pending users
- Your test user appears

---

#### Test 4.5: Pending User in Admin Dashboard

**Steps:**
1. Log in as admin
2. View admin dashboard (`/?page=admin`)

**Expected Result:**
- Stat card shows: "Pending Activation" with count
- Clicking "View →" links to `/?page=admin_users&status=pending`

---

#### Test 4.6: Pending User in Binary Tree

**Steps:**
1. Log in as the pending user's sponsor
2. Go to Genealogy → Binary Tree
3. Find the pending user in the tree

**Expected Result:**
- User appears in their correct position
- Pending users show in **amber/orange** color (#f59e0b)
- Legend at bottom shows "Pending" with amber dot
- Tree structure is intact

---

### 4.5 Commission Guards for Pending Users

**Purpose:** Verify pending users cannot trigger or receive commissions.

#### Test 5.1: Pending Sponsor Gets No Direct Referral

**Setup:**
- `@pending1` is a pending user
- Register a new member (normal or referral) with `@pending1` as sponsor

**Steps:**
1. Visit `/?page=register&sponsor=pending1&ref=1` (or normal registration with sponsor = pending1)
2. Register a new user

**Expected Result:**
- New user registers successfully
- `@pending1` receives **₱0** direct referral bonus
- Commission record shows `status = 'flushed'` or no record at all

**Verify:**
```sql
SELECT amount, cap_deduction, status
FROM commissions
WHERE user_id = [pending1_id] AND type = 'direct_referral'
ORDER BY created_at DESC
LIMIT 1;
-- Expected: amount = 0 or no rows
```

---

#### Test 5.2: Pending Upline Gets No Pairing Bonus

**Setup:**
- `@pending1` is in the binary tree as an ancestor
- Place a new active user under `@pending1`'s subtree

**Steps:**
1. Log in as an active member who can register others
2. Register a new member in a position that would form a pair involving `@pending1`

**Expected Result:**
- `@pending1` receives **no pairing bonus**
- The pairing bonus check in `processBinaryPlacement` skips pending users

**Verify:**
```sql
SELECT pairs_paid, pairs_paid_today
FROM users
WHERE id = [pending1_id];
-- Should remain unchanged after registration
```

---

#### Test 5.3: Pending User Gets No DFI

**Setup:**
- DFI is enabled in settings
- A pending user has `dfi_active = 1`

**Steps:**
1. Run the midnight cron (or DFI process)
2. Check if pending users received DFI

**Expected Result:**
- Pending users receive **₱0** DFI
- The DFI SQL query filters: `AND u.status = 'active'`

**Verify:**
```sql
SELECT COUNT(*) FROM daily_fixed_income_log
WHERE user_id = [pending1_id];
-- Expected: 0
```

---

### 4.6 Activation Flow

**Purpose:** Verify pending users can activate via code or e-wallet.

#### Test 6.1: Activation Page Access

**Steps:**
1. Log in as a pending user
2. Click the "⚡ Activate Now" button on dashboard
   OR visit `/?page=activate` directly

**Expected Result:**
- Page loads: "Activate Account"
- Yellow banner: "Account Pending Activation"
- Two payment options: 🎫 Code (default) and 💳 E-Wallet (if balance sufficient)

---

#### Test 6.2: Activation with Registration Code

**Prerequisites:**
- You have an **unused** registration code
- Generate one via admin if needed: `/?page=admin_codes` → Generate

**Steps:**
1. Log in as pending user
2. Go to `/?page=activate`
3. Select "🎫 Code" payment method
4. Enter a valid registration code
5. Click "Validate" button
6. Code should show "✓ Code is valid!" with package details
7. Click "⚡ Activate Account"

**Expected Result:**
- Flash message: "🎉 Account activated! You can now start earning commissions."
- Redirected to Dashboard
- Dashboard no longer shows pending banner

**Verify (Database):**
```sql
SELECT status, package_id, reg_payment_method, joined_at
FROM users
WHERE id = [pending_user_id];
-- Expected:
-- status = 'active'
-- package_id = the code's package_id
-- reg_payment_method = 'code'
-- joined_at = NOW() (or recent timestamp)
```

**Verify (Code marked used):**
```sql
SELECT status, used_by, used_at
FROM reg_codes
WHERE code = 'YOUR-CODE-HERE';
-- Expected: status = 'used', used_by = [user_id], used_at = [timestamp]
```

---

#### Test 6.3: Activation with E-Wallet

**Prerequisites:**
- Pending user has sufficient e-wallet balance (top up via admin if needed)
- Multiple packages exist (or at least one)

**Steps:**
1. Log in as pending user
2. Go to `/?page=activate`
3. Select "💳 E-Wallet"
4. Select a package from dropdown (if multiple)
5. Click "⚡ Activate Account"

**Expected Result:**
- Activation succeeds
- E-wallet balance decreased by package entry fee
- Admin's e-wallet balance increased by the same amount (revenue)

**Verify:**
```sql
-- Check user's balance decreased
SELECT ewallet_balance FROM users WHERE id = [pending_user_id];
-- Should be old_balance - entry_fee

-- Check admin received revenue
SELECT ewallet_balance FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1;
-- Should be admin_old_balance + entry_fee

-- Check ledger record
SELECT * FROM ewallet_ledger
WHERE user_id = [pending_user_id] AND type = 'debit'
ORDER BY id DESC LIMIT 1;
-- Should show: amount = entry_fee, ref_type = 'registration', note mentions activation
```

---

#### Test 6.4: E-Wallet Insufficient Balance

**Setup:**
- Pending user's e-wallet balance is below the cheapest package's entry fee

**Steps:**
1. Log in as pending user with low balance
2. Go to `/?page=activate`
3. Select "💳 E-Wallet"
4. Try to activate

**Expected Result:**
- Error flash: "Insufficient e-wallet balance. Required: ₱X,XXX.00"
- Redirected back to activation page
- User remains pending

---

#### Test 6.5: Invalid Code Rejected

**Steps:**
1. Log in as pending user
2. Go to `/?page=activate`
3. Enter an invalid code: `FAKE-CODE-1234`
4. Click "Validate"

**Expected Result:**
- Validation fails
- Hint: "Invalid code." or "Code is invalid, used, or expired."
- Activate button remains disabled

---

#### Test 6.6: Already Active User Cannot Access Activation

**Steps:**
1. Log in as an **active** user (not pending)
2. Try to visit `/?page=activate`

**Expected Result:**
- Flash message: "Your account is already active."
- Redirected to Dashboard

---

#### Test 6.7: Activation Page While Logged Out

**Steps:**
1. While logged out, visit `/?page=activate`

**Expected Result:**
- Redirected to login page
- Flash: "Please log in to continue."

---

### 4.7 Activation Commission Firing

**Purpose:** Verify commissions fire correctly when a pending user activates.

#### Test 7.1: Direct Referral Fires on Activation

**Setup:**
- `@alice` is active sponsor
- `@pending1` was registered under Alice via referral link
- `@pending1` has not activated yet

**Steps:**
1. Note Alice's current `lifetime_earned` and `ewallet_balance`
2. Activate `@pending1` using a code

**Expected Result:**
- Alice receives direct referral bonus (e.g., ₱500)
- Alice's `ewallet_balance` increases by ₱500
- Alice's `lifetime_earned` increases by ₱500

**Verify:**
```sql
-- Before activation, note these values
SELECT ewallet_balance, lifetime_earned FROM users WHERE username = 'alice';

-- After activation
SELECT ewallet_balance, lifetime_earned FROM users WHERE username = 'alice';
-- Both should increase by direct_ref_bonus amount

-- Check commission record
SELECT amount, type, status
FROM commissions
WHERE user_id = (SELECT id FROM users WHERE username = 'alice')
  AND source_user_id = (SELECT id FROM users WHERE username = 'pending1')
  AND type = 'direct_referral';
-- Expected: amount = 500.00, status = 'credited'
```

---

#### Test 7.2: Pairing Bonus Fires on Activation (Correct Count)

**Setup:**
- `@pending1` is placed in the binary tree under `@alice` (left)
- `@alice` already has another member on her right side
- `@pending1` is still pending
- **CRITICAL:** Multiple pending users exist in the tree (e.g., `@pending2`, `@pending3` also pending under Alice's subtree)

**Steps:**
1. Note Alice's `pairs_paid` before activation
2. Activate `@pending1`

**Expected Result:**
- Alice receives **exactly 1 pairing bonus** (or 0 if legs are still unbalanced)
- Alice does **NOT** receive pairing bonuses for OTHER pending users who haven't activated yet
- `left_count`/`right_count` are incremented at activation time (they were 0 during pending placement)

**Verify:**
```sql
SELECT pairs_paid, pairs_paid_today, left_count, right_count
FROM users
WHERE username = 'alice';

-- Check pairing commission
SELECT amount, type, pairs_count
FROM commissions
WHERE user_id = (SELECT id FROM users WHERE username = 'alice')
  AND source_user_id = (SELECT id FROM users WHERE username = 'pending1')
  AND type = 'pairing';
```

> **Important:** The description should show "1 pair(s) × ₱X,XXX.00" — NOT a multiple like "4 pair(s) × ₱X,XXX.00". If you see multiple pairs for a single activation, that's a bug.

---

#### Test 7.3: Indirect Referral Fires on Activation (if enabled)

**Prerequisites:**
- `indirect_referral_enabled` setting = `1`

**Setup:**
- Chain: Alice → Bob (active) → Pending1 (pending)
- Pending1 was registered under Bob

**Steps:**
1. Activate Pending1

**Expected Result:**
- Alice receives indirect referral bonus (Level 2)
- Bob receives direct referral bonus (Level 1)

**Verify:**
```sql
SELECT u.username, c.amount, c.type, c.level
FROM commissions c
JOIN users u ON u.id = c.user_id
WHERE c.source_user_id = (SELECT id FROM users WHERE username = 'pending1')
  AND c.type IN ('direct_referral', 'indirect_referral')
ORDER BY c.type, c.level;

-- Expected:
-- Bob  | 500.00 | direct_referral   | NULL
-- Alice| 200.00 | indirect_referral | 2 (or whatever L2 rate is)
```

---

#### Test 7.4: Leg Counts Incremented at Activation (Not at Registration)

**Purpose:** Verify that pending registration does NOT increment leg counts, and activation DOES increment them.

**Setup:**
- `@pending1` is pending, placed under `@alice`
- Note Alice's `left_count` and `right_count`

**Steps:**
1. **Before pending registration:** Record Alice's counts
2. Register `@pending1` via referral link (pending)
3. Record Alice's counts again
4. Activate `@pending1`
5. Record Alice's counts again

**Expected Result:**
- **Step 2→3:** Alice's counts are **UNCHANGED** (pending registration does NOT increment)
- **Step 3→4:** Alice's counts **INCREASE by 1** (activation increments the appropriate leg)

**Verify:**
```sql
-- Before pending registration
SELECT left_count, right_count FROM users WHERE username = 'alice';
-- e.g., left_count = 3, right_count = 2

-- After pending registration (before activation)
SELECT left_count, right_count FROM users WHERE username = 'alice';
-- Should still be: left_count = 3, right_count = 2

-- After activation
SELECT left_count, right_count FROM users WHERE username = 'alice';
-- Should be: left_count = 4, right_count = 2 (or whichever leg pending1 is on)
```

---

#### Test 7.5: Multiple Pending Activations Don't Retroactively Pair

**Purpose:** Verify that activating one pending user doesn't pay for other pending users.

**Setup:**
- Alice has 4 pending users in her left leg: `@p1`, `@p2`, `@p3`, `@p4`
- Alice has 1 active user in her right leg: `@active1`
- Alice's `pairs_paid` = 1 (from `@active1`)

**Steps:**
1. Activate `@p1`
2. Check Alice's pairing commission

**Expected Result:**
- Alice receives **1 pair** (from `@p1` only)
- Not 4 pairs (which would include `@p2`, `@p3`, `@p4`)

**Steps (continue):**
3. Activate `@p2`
4. Check Alice's pairing commission

**Expected Result:**
- Alice receives **1 pair** (from `@p2` only)
- Total pairs_paid should now be 3 (1 from @active1 + 1 from @p1 + 1 from @p2)

**Verify:**
```sql
SELECT pairs_paid, left_count, right_count
FROM users
WHERE username = 'alice';
-- After all 4 pending users activate:
-- left_count = 5, right_count = 1, pairs_paid = 5 (not 1 + 4 all at once)
```

---

### 4.8 Seat Limit Integration

**Purpose:** Verify seat limit still blocks referral registrations when full.

#### Test 8.1: Seat Limit Blocks Referral Registration

**Setup:**
- Seat limit is reached or very close

**Steps:**
1. Set seat limit to current member count (or use SQL to simulate)
2. Visit referral link: `/?page=register&sponsor=alice&ref=1`

**Expected Result:**
- Redirected to "Registration Closed" page
- Message: "Registration is closed. The member seat limit has been reached."

**Verify:**
```sql
-- Check seat settings
SELECT value FROM settings WHERE key_name = 'seat_limit';

-- Check current count
SELECT COUNT(*) FROM users WHERE role = 'member';
-- If count >= seat_limit, registration should be blocked
```

---

#### Test 8.2: Pending Users Count Toward Limit

**Setup:**
- Seat limit = current active members + 1

**Steps:**
1. Register one user via referral link (pending)
2. Try to register another user via referral link

**Expected Result:**
- First registration succeeds (pending)
- Second registration is blocked (seat limit reached)

---

### 4.9 Regression Testing

**Purpose:** Ensure existing features still work after this feature deployment.

#### Test 9.1: Normal Code Registration Still Works

**Steps:**
1. While logged out, visit `/?page=register` (no ref)
2. Enter a valid registration code
3. Fill in username, password, sponsor, upline, position
4. Submit

**Expected Result:**
- Registration succeeds
- User is active immediately
- All commissions fire normally
- Sponsor receives direct referral bonus
- Ancestors receive pairing bonuses

---

#### Test 9.2: Logged-In E-Wallet Registration Still Works

**Steps:**
1. Log in as an active member with sufficient e-wallet balance
2. Go to `/?page=register`
3. Select "💳 E-Wallet"
4. Select a package
5. Fill in new member details
6. Submit

**Expected Result:**
- Registration succeeds
- E-wallet debited for entry fee
- New member is active
- All commissions fire

---

#### Test 9.3: Member Dashboard Still Works for Active Users

**Steps:**
1. Log in as an active member
2. View dashboard, earnings, genealogy, profile, payout pages

**Expected Result:**
- All pages load without errors
- No pending banner shown
- All stats display correctly
- No JavaScript errors in console

---

#### Test 9.4: Admin Pages Still Work

**Steps:**
1. Log in as admin
2. View dashboard, users, packages, codes, payouts, settings

**Expected Result:**
- All pages load without errors
- Pending users visible in user list with correct status
- Suspended users show "Unsuspend" button (not "Activate")
- Pending users do NOT show toggle button
- No PHP notices or warnings

---

#### Test 9.5: Genealogy Tree Still Displays Correctly

**Steps:**
1. Log in as any member
2. Go to Genealogy → Binary Tree
3. Expand/collapse nodes, zoom, pan
4. Click a node with `+` (blue circle) to load deeper levels

**Expected Result:**
- Tree renders correctly
- Pending members appear in amber/orange color
- Legend shows: Active (green), Suspended (red), Pending (amber)
- Nodes with deeper children show a blue `+` toggle circle
- Clicking `+` loads the next 4 levels without long dangling lines
- Empty slots show as dashed grey boxes

---

### 4.10 UI & UX Improvements

**Purpose:** Verify recent UI/UX improvements work correctly.

#### Test 10.1: Topbar Shows Full Username

**Steps:**
1. Log in as any member
2. Look at the top-right of the page

**Expected Result:**
- Topbar shows a pill/badge with "User @username" (full username, not just first letter)
- Styled like the "Balance" badge next to it
- Clicking it goes to Profile page

---

#### Test 10.2: E-Wallet Transfer Password Toggle

**Steps:**
1. Log in as any member
2. Go to Send Money (`/?page=ewallet_transfer`)
3. Look at the "Confirm Password" field

**Expected Result:**
- Password field has an eye (👁) button next to it
- Clicking the eye shows the password in plain text
- Clicking again hides it

---

#### Test 10.3: Admin Toggle Button Labels

**Steps:**
1. Log in as admin
2. Go to Members (`/?page=admin_users`)
3. Look at suspended users
4. Look at pending users

**Expected Result:**
- Suspended users show "🔓 Unsuspend" button (not "Activate")
- Pending users show **NO** toggle button
- Active users show "🔒 Suspend" button
- Confirmation modal shows correct username (e.g., "Are you sure you want to unsuspend @altas08?")

---

#### Test 10.4: Binary Tree Pending Color

**Steps:**
1. Log in as any member with pending users in their tree
2. Go to Genealogy → Binary Tree
3. Look at the legend at the bottom

**Expected Result:**
- Legend shows: Active (green), Suspended (red), Pending (amber/orange)
- No "Open Slot" legend item
- Pending nodes render in amber (#f59e0b)

---

## 5. Bug Reporting Template

When you find an issue, use this format:

```markdown
### Bug Report — Referral Link Registration

**Tester:** [Your Name]
**Date:** [YYYY-MM-DD HH:MM]
**Test Case:** [e.g., 2.4 Submit Referral Registration]
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

### This Feature PASSES if ALL of these are true:

| # | Criteria | How to Verify |
|---|----------|---------------|
| 1 | Referral link generates correctly in Genealogy | Test 1.1, 1.2 |
| 2 | Copy button works | Test 1.1 |
| 3 | Referral mode form shows correctly (readonly sponsor, editable upline/position, no payment) | Test 2.1, 2.2, 2.5 |
| 4 | Normal registration still works without `&ref=1` | Test 1.3, 9.1 |
| 5 | Auto-placement finds correct BFS slot | Test 3.1–3.5 |
| 6 | User is created with `status = 'pending'` | Test 2.4, 4.1 |
| 7 | Pending user has `package_id = NULL` | Test 4.1 (SQL) |
| 8 | Pending user has `reg_payment_method = 'pending'` | Test 4.1 (SQL) |
| 9 | Binary position is reserved at registration | Test 3.1–3.5 |
| 10 | Pending user sees dashboard banner with "Activate Now" | Test 4.1 |
| 11 | Pending users count toward seat limit | Test 4.3, 8.1 |
| 12 | Pending sponsors cannot earn direct referral | Test 5.1 |
| 13 | Pending uplines cannot earn pairing bonus | Test 5.2 |
| 14 | Pending users cannot receive DFI | Test 5.3 |
| 15 | Activation page loads for pending users | Test 6.1 |
| 16 | Activation with code works and marks code used | Test 6.2 |
| 17 | Activation with e-wallet works and debits correctly | Test 6.3 |
| 18 | Insufficient e-wallet shows friendly error | Test 6.4 |
| 19 | Invalid code is rejected | Test 6.5 |
| 20 | Active users redirected from activation page | Test 6.6 |
| 21 | Commissions fire on activation (direct, indirect, pairing) | Test 7.1–7.3 |
| 22 | **Pairing bonus is exactly 1 pair per activation** (not multiple) | Test 7.2, 7.5 |
| 23 | Leg counts incremented at activation, not at pending registration | Test 7.4 |
| 24 | Admin dashboard shows pending count | Test 4.5 |
| 25 | Admin user list filters by pending status | Test 4.4 |
| 26 | Admin shows "Unsuspend" for suspended, no button for pending | Test 10.3 |
| 27 | Binary tree lazy-loads deeper levels correctly | Test 9.5 |
| 28 | No PHP fatal errors in logs | Check after all tests |

### This Feature FAILS if ANY of these are true:

- ❌ Referral link missing or copy button broken
- ❌ Referral mode form shows payment fields (code/ewallet)
- ❌ Sponsor field is editable in referral mode
- ❌ User is created as `active` instead of `pending`
- ❌ Auto-placement puts user in wrong slot
- ❌ Pending user triggers commissions for ancestors
- ❌ Pending sponsor earns from their downlines
- ❌ Activation fails with valid code or sufficient e-wallet
- ❌ Pairing bonus shows multiple pairs for a single activation
- ❌ Leg counts double-increment or increment at pending registration
- ❌ Seat limit ignores pending users
- ❌ Normal registration flow is broken
- ❌ PHP fatal errors in server logs

---

## 7. FAQ

### Q: How do I quickly create a pending user for testing?
**A:** Use the referral link while logged out:
```
/?page=register&sponsor=ALICE&ref=1
```
Fill in the form and submit. The user is automatically pending.

---

### Q: How do I check if a user is pending?
**A:**
```sql
SELECT username, status, package_id, reg_payment_method
FROM users
WHERE username = 'pendinguser';
-- status = 'pending', package_id = NULL, reg_payment_method = 'pending'
```

---

### Q: Can I activate a pending user from the admin panel?
**A:** Not directly through a UI button in this version. The pending user must log in and visit `/?page=activate` themselves. Admins can simulate activation by running SQL, but that's not the intended flow.

---

### Q: What happens if I visit `?ref=1` without a `sponsor` parameter?
**A:** The system treats it as a normal registration. `ref=1` alone doesn't trigger referral mode — you need BOTH `sponsor=USERNAME` and `ref=1`.

---

### Q: The auto-placed upline is wrong. How do I debug?
**A:** Check the sponsor's binary tree:
```sql
-- Show all children of a sponsor
SELECT u.username, u.binary_position, u.status
FROM users u
WHERE u.binary_parent_id = (SELECT id FROM users WHERE username = 'alice')
ORDER BY u.binary_position;
```
The BFS algorithm fills left before right, level by level.

---

### Q: Can a pending user log in?
**A:** Yes! Pending users can log in, view their dashboard, see their binary position, and access the activation page. They just can't earn commissions until activated.

---

### Q: What happens to pending users if the seat limit is reached?
**A:** They count toward the limit. If the seat limit is reached, NO new registrations are allowed — neither normal nor referral.

---

### Q: How do I test e-wallet activation without real money?
**A:** Admin can top up any member's e-wallet:
1. Go to `/?page=admin_ewallet_topup`
2. Enter the pending user's username
3. Enter amount (e.g., ₱5,000)
4. Submit

---

### Q: Can I change the sponsor after a pending user is registered?
**A:** No. Sponsor, upline, and position are locked at registration time and cannot be changed.

---

### Q: The referral mode form doesn't show. Why?
**A:** Check:
1. URL has BOTH `sponsor=USERNAME` and `ref=1`
2. The sponsor username actually exists in the database
3. The sponsor's binary tree is not completely full
4. The seat limit has not been reached

---

### Q: How do I run the DB migration manually?
**A:**
```bash
mysql -u your_username -p u938213108_altas_db < migrations/002_add_pending_payment_method.sql
```
Or run it in phpMyAdmin by copying the SQL from the migration file.

---

### Q: Why did my pending user's activation give 4 pairs instead of 1?
**A:** This was a bug in v1.0 where leg counts were incremented at pending registration time, then pairing bonuses paid for ALL pending increments at once during activation. This is fixed in v1.1 — leg counts are now only incremented at activation time, so each activation creates exactly 1 new pair.

---

## Quick Reference Card

| Test | What to Do | What to Check |
|------|-----------|-------------|
| Link works | Copy link from Genealogy | URL has `sponsor=` and `ref=1` |
| Referral mode | Visit link while logged out | Readonly sponsor, editable upline/position, no payment step |
| Auto-placement | Register 3+ users via same link | BFS order: parent's left, parent's right, then children |
| Pending created | Submit referral form | DB: `status='pending'`, `package_id=NULL` |
| No commissions | Register under pending sponsor | Sponsor gets ₱0 direct referral |
| Activation (code) | Log in as pending → Activate → Enter code | Status becomes `active`, code marked used |
| Activation (ewallet) | Log in as pending → Activate → Select package | Balance debited, admin credited |
| Correct pairing | Activate user, check ancestor commission | Exactly 1 pair per activation (not multiple) |
| Leg counts | Check counts before/after pending reg and activation | Unchanged at pending reg, +1 at activation |
| Seat limit | Fill seats to limit, try referral link | Blocked with "Registration Closed" |
| Regression | Register normally without `&ref=1` | Works exactly as before |
| Admin labels | View admin users list | Suspended = "Unsuspend", Pending = no button |
| Tree lazy load | Click blue `+` on deep node | Loads 4 more levels, standard line spacing |

---

**End of Guide**
