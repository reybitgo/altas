# QA Tester Guide — E-Wallet Transfer Daily & Weekly Limits

**Version:** v1.0  
**Date:** 2026-05-28  
**System:** Altas Farm MLM Binary System  
**Feature:** E-Wallet Transfer Limit Tracking (`cron/fund_transfer_limit_reset.php`)

---

## Table of Contents

1. [What is This Testing?](#1-what-is-this-testing)
2. [Prerequisites](#2-prerequisites)
3. [Test Environment Setup](#3-test-environment-setup)
4. [Test Cases](#4-test-cases)
   - 4.1 [Daily Limit Enforcement](#41-daily-limit-enforcement)
   - 4.2 [Weekly Limit Enforcement](#42-weekly-limit-enforcement)
   - 4.3 [Combined Daily + Weekly Limits](#43-combined-daily--weekly-limits)
   - 4.4 [Admin Exemption](#44-admin-exemption)
   - 4.5 [Limit Reset Cron](#45-limit-reset-cron)
   - 4.6 [Tracking Column Accuracy](#46-tracking-column-accuracy)
   - 4.7 [Regression Testing](#47-regression-testing)
5. [Bug Reporting Template](#5-bug-reporting-template)
6. [Pass/Fail Criteria](#6-passfail-criteria)
7. [FAQ](#7-faq)

---

## 1. What is This Testing?

This guide tests the **E-Wallet Transfer Limits** feature. Members can send money to each other, but the system now enforces:

- **Daily Limit** — max amount a member can send in one day (default: ₱5,000)
- **Weekly Limit** — max amount a member can send in one week (default: ₱20,000)

These limits are tracked in fast cache columns on the `users` table:

| Column | What It Tracks | When It Resets |
|--------|---------------|----------------|
| `ewallet_sent_today` | Total sent today | Every midnight |
| `ewallet_sent_this_week` | Total sent since Monday | Every Monday midnight |

The cron job `cron/fund_transfer_limit_reset.php` handles both resets with a single schedule.

### What's Being Tested

| Component | What It Does | Where to Test |
|-----------|-------------|---------------|
| `Ewallet::transfer()` | Checks limits before allowing a transfer | Member → E-Wallet Transfer page |
| `users.ewallet_sent_today` | Counter for today's sent amount | Database / transfer flow |
| `users.ewallet_sent_this_week` | Counter for this week's sent amount | Database / transfer flow |
| `cron/fund_transfer_limit_reset.php` | Resets both counters at midnight | Run manually + check DB |

### What's NOT Being Tested Here

- ❌ Transfer fee calculation — tested separately
- ❌ Withdrawable vs non-withdrawable balance — tested separately
- ❌ Admin top-ups — exempt from limits by design
- ❌ Commission or pairing logic — that's capping/DFI testing

> **Rule of Thumb:** If it doesn't involve the Daily Limit or Weekly Limit fields in Admin → Settings → E-Wallet Transfers, it's not in scope here.

---

## 2. Prerequisites

Before you start testing, ensure you have:

### Required Access
- [ ] Admin login credentials
- [ ] At least **two member accounts** with e-wallet balances
- [ ] Database access (phpMyAdmin, MySQL CLI, or similar)
- [ ] Server/terminal access to run cron manually

### Required Knowledge
- [ ] How to run SQL queries (`SELECT`, `UPDATE`)
- [ ] How to read PHP error logs
- [ ] How to run PHP scripts from command line (`php script.php`)
- [ ] How to use browser DevTools (F12 → Console)

### Required Settings
Verify these settings exist in **Admin → System Settings**:

| Setting | Default | Where It Lives |
|---------|---------|----------------|
| `ewallet_transfer_daily_limit` | 5000.00 | Admin → Settings |
| `ewallet_transfer_weekly_limit` | 20000.00 | Admin → Settings |
| `ewallet_min_transfer` | 50.00 | Admin → Settings |
| `ewallet_transfer_fee` | 0.00 | Admin → Settings |

**Quick Check:**
```sql
SELECT key_name, value FROM settings
WHERE key_name IN ('ewallet_transfer_daily_limit','ewallet_transfer_weekly_limit');
```

**Expected Result:**
```
+-------------------------------+----------+
| key_name                      | value    |
+-------------------------------+----------+
| ewallet_transfer_daily_limit  | 5000.00  |
| ewallet_transfer_weekly_limit | 20000.00 |
+-------------------------------+----------+
```

### Migration Must Be Applied
- [ ] `migrations/012_add_transfer_limit_tracking.sql` has been run
- [ ] `users` table has `ewallet_sent_today` and `ewallet_sent_this_week` columns

**Verify:**
```sql
SHOW COLUMNS FROM users WHERE Field LIKE 'ewallet_sent%';
```

**Expected Result:**
```
+------------------------+---------------+------+-----+---------+-------+
| Field                  | Type          | Null | Key | Default | Extra |
+------------------------+---------------+------+-----+---------+-------+
| ewallet_sent_today     | decimal(12,2) | NO   |     | 0.00    |       |
| ewallet_sent_this_week | decimal(12,2) | NO   |     | 0.00    |       |
+------------------------+---------------+------+-----+---------+-------+
```

---

## 3. Test Environment Setup

### Step 1: Backup (CRITICAL)

```bash
mysqldump -u your_username -p u938213108_altas_db > backup_before_transfer_limit_test.sql
```

### Step 2: Verify Files Are Deployed

Run these commands on your server:

```bash
# Check cron file exists
ls -la /path/to/site/cron/fund_transfer_limit_reset.php

# Check Ewallet model uses tracking columns
grep -n "ewallet_sent_today\|ewallet_sent_this_week" /path/to/site/models/Ewallet.php
```

**Expected Result:**
- `fund_transfer_limit_reset.php` exists
- `Ewallet.php` references both columns

### Step 3: Prepare Test Members

You need at least **two members** with enough balance. If you don't have them, top up two test accounts:

```sql
-- Find two members
SELECT id, username, ewallet_balance
FROM users
WHERE role = 'member'
LIMIT 2;
```

If their balances are too low, give them enough for testing via Admin → E-Wallet Monitor → Top Up Member.

**Recommended starting balances:**
- Sender: ₱25,000+ (needs enough to hit weekly limit)
- Recipient: ₱0+ (just needs to exist)

### Step 4: Reset Counters to Known State

Before starting tests, zero out the tracking columns:

```sql
UPDATE users
SET ewallet_sent_today = 0.00,
    ewallet_sent_this_week = 0.00
WHERE role = 'member';
```

---

## 4. Test Cases

### 4.1 Daily Limit Enforcement

**Purpose:** Verify members cannot send more than the daily limit in a single calendar day.

For these tests, assume:
- `ewallet_transfer_daily_limit` = ₱5,000
- `ewallet_transfer_weekly_limit` = ₱20,000
- Transfer fee = ₱0

---

#### Test 1.1: Transfer Below Daily Limit

**Setup:**
```sql
-- Pick sender and recipient
SELECT id, username, ewallet_balance
FROM users WHERE role = 'member' LIMIT 2;
```

Note `sender_id` (first row) and `recipient_id` (second row).

```sql
-- Ensure sender has enough balance and counters are zero
UPDATE users
SET ewallet_sent_today = 0.00,
    ewallet_sent_this_week = 0.00
WHERE id = [sender_id];
```

**Steps:**
1. Log in as the sender member
2. Go to **E-Wallet Transfer**
3. Send **₱1,000** to the recipient
4. Confirm with password

**Expected Result:**
- Transfer succeeds
- Success message appears
- Sender's balance decreases by ₱1,000
- Recipient's balance increases by ₱1,000

**Verify:**
```sql
SELECT ewallet_balance, ewallet_sent_today, ewallet_sent_this_week
FROM users
WHERE id = [sender_id];

-- Expected:
-- ewallet_balance: previous - 1000
-- ewallet_sent_today: 1000.00
-- ewallet_sent_this_week: 1000.00
```

---

#### Test 1.2: Transfer Exactly at Daily Limit

**Setup:**
```sql
-- Set sender at 4,000 already sent today
UPDATE users
SET ewallet_sent_today = 4000.00,
    ewallet_sent_this_week = 4000.00
WHERE id = [sender_id];
```

**Steps:**
1. Log in as sender
2. Go to **E-Wallet Transfer**
3. Send **₱1,000** to recipient

**Expected Result:**
- Transfer succeeds
- Sender's `ewallet_sent_today` becomes exactly ₱5,000

**Verify:**
```sql
SELECT ewallet_sent_today, ewallet_sent_this_week
FROM users
WHERE id = [sender_id];

-- Expected:
-- ewallet_sent_today = 5000.00
-- ewallet_sent_this_week = 5000.00
```

---

#### Test 1.3: Transfer One Peso Over Daily Limit

**Setup:**
```sql
-- Set sender at 4,500 already sent today
UPDATE users
SET ewallet_sent_today = 4500.00,
    ewallet_sent_this_week = 4500.00
WHERE id = [sender_id];
```

**Steps:**
1. Log in as sender
2. Go to **E-Wallet Transfer**
3. Send **₱1,000** to recipient (this would make total ₱5,500)

**Expected Result:**
- Transfer is **blocked**
- Error message: **"Daily transfer limit exceeded."**
- No money moves
- `ewallet_sent_today` stays at ₱4,500

**Verify:**
```sql
SELECT ewallet_balance, ewallet_sent_today
FROM users
WHERE id = [sender_id];

-- Expected:
-- ewallet_balance: unchanged
-- ewallet_sent_today: 4500.00
```

---

#### Test 1.4: Multiple Small Transfers That Sum to Limit

**Setup:**
```sql
UPDATE users
SET ewallet_sent_today = 0.00,
    ewallet_sent_this_week = 0.00
WHERE id = [sender_id];
```

**Steps:**
1. Send ₱1,000 → should succeed
2. Send ₱1,500 → should succeed  
3. Send ₱2,000 → should succeed (total now ₱4,500)
4. Send ₱1,000 → should **fail** (would exceed ₱5,000)
5. Send ₱500 → should succeed (exactly hits ₱5,000)
6. Send ₱100 → should **fail**

**Expected Result:**
- Steps 1–3: success
- Step 4: blocked with "Daily transfer limit exceeded"
- Step 5: success
- Step 6: blocked

**Verify:**
```sql
SELECT ewallet_sent_today
FROM users
WHERE id = [sender_id];

-- Expected: 5000.00
```

---

#### Test 1.5: Different Sender, Same Day

**Purpose:** Confirm limits are **per-member**, not global.

**Setup:**
- Sender A: `ewallet_sent_today = 5000.00` (at daily limit)
- Sender B: `ewallet_sent_today = 0.00` (has not sent anything)

**Steps:**
1. Try to send ₱1,000 from Sender A → should fail
2. Send ₱1,000 from Sender B → should succeed

**Expected Result:**
- Sender A is blocked
- Sender B succeeds
- Global system is NOT limited

---

### 4.2 Weekly Limit Enforcement

**Purpose:** Verify members cannot send more than the weekly limit across the current Monday–Sunday window.

---

#### Test 2.1: Transfer Below Weekly Limit

**Setup:**
```sql
UPDATE users
SET ewallet_sent_today = 0.00,
    ewallet_sent_this_week = 5000.00
WHERE id = [sender_id];
```

**Steps:**
1. Send ₱5,000 from sender to recipient

**Expected Result:**
- Transfer succeeds
- `ewallet_sent_this_week` becomes ₱10,000

**Verify:**
```sql
SELECT ewallet_sent_today, ewallet_sent_this_week
FROM users
WHERE id = [sender_id];

-- Expected:
-- ewallet_sent_today = 5000.00
-- ewallet_sent_this_week = 10000.00
```

---

#### Test 2.2: Transfer Exactly at Weekly Limit

**Setup:**
```sql
UPDATE users
SET ewallet_sent_today = 0.00,
    ewallet_sent_this_week = 15000.00
WHERE id = [sender_id];
```

**Steps:**
1. Send ₱5,000 from sender to recipient

**Expected Result:**
- Transfer succeeds
- `ewallet_sent_this_week` becomes exactly ₱20,000

**Verify:**
```sql
SELECT ewallet_sent_this_week
FROM users
WHERE id = [sender_id];

-- Expected: 20000.00
```

---

#### Test 2.3: Transfer Over Weekly Limit

**Setup:**
```sql
UPDATE users
SET ewallet_sent_today = 0.00,
    ewallet_sent_this_week = 19500.00
WHERE id = [sender_id];
```

**Steps:**
1. Send ₱1,000 from sender (would make weekly total ₱20,500)

**Expected Result:**
- Transfer is **blocked**
- Error message: **"Weekly transfer limit exceeded."**
- No money moves

**Verify:**
```sql
SELECT ewallet_balance, ewallet_sent_this_week
FROM users
WHERE id = [sender_id];

-- Expected:
-- ewallet_balance: unchanged
-- ewallet_sent_this_week: 19500.00
```

---

#### Test 2.4: Weekly Limit Persists Across Multiple Days

**Purpose:** Confirm weekly limit is **NOT** reset each day. A member can be well within their daily limit but still blocked by the weekly limit.

**Setup:**
- Today is Wednesday
- Manually set `ewallet_sent_this_week = 18000.00`
- `ewallet_sent_today = 0.00`

**Steps:**
1. Send ₱1,000 → succeeds (weekly now ₱19,000)
2. Send ₱1,500 → fails (would exceed ₱20,000 weekly)

**Expected Result:**
- Step 1 succeeds. Daily counter becomes ₱1,000; weekly counter becomes ₱19,000.
- Step 2 is **blocked** by the weekly limit.
- Why blocked? Daily would allow it (₱1,000 + ₱1,500 = ₱2,500 < ₱5,000 daily limit), but weekly would not (₱19,000 + ₱1,500 = ₱20,500 > ₱20,000 weekly limit).
- The daily counter is low (only ₱1,000), yet the weekly limit still enforces.

---

### 4.3 Combined Daily + Weekly Limits

**Purpose:** Verify both limits work together — the **lower effective limit wins**.

---

#### Test 3.1: Daily Limit Is the Blocking Factor

**Setup:**
```sql
UPDATE users
SET ewallet_sent_today = 4900.00,
    ewallet_sent_this_week = 5000.00
WHERE id = [sender_id];
```

**Steps:**
1. Send ₱500 from sender

**Expected Result:**
- Transfer is **blocked**
- Error message: **"Daily transfer limit exceeded."**
- Why: ₱4,900 + ₱500 = ₱5,400 > daily limit of ₱5,000
- Weekly limit would allow it (₱5,000 + ₱500 = ₱5,500 < ₱20,000), but daily is stricter

---

#### Test 3.2: Weekly Limit Is the Blocking Factor

**Setup:**
```sql
UPDATE users
SET ewallet_sent_today = 0.00,
    ewallet_sent_this_week = 19500.00
WHERE id = [sender_id];
```

**Steps:**
1. Send ₱1,000 from sender

**Expected Result:**
- Transfer is **blocked**
- Error message: **"Weekly transfer limit exceeded."**
- Why: daily would allow (0 + 1000 = 1000 < 5000), but weekly would not

---

#### Test 3.3: Both Limits Allow Transfer

**Setup:**
```sql
UPDATE users
SET ewallet_sent_today = 1000.00,
    ewallet_sent_this_week = 5000.00
WHERE id = [sender_id];
```

**Steps:**
1. Send ₱1,000 from sender

**Expected Result:**
- Transfer succeeds
- New daily: ₱2,000
- New weekly: ₱6,000

---

### 4.4 Admin Exemption

**Purpose:** Confirm admin transfers are NOT subject to limits.

---

#### Test 4.1: Admin Can Transfer Past Member Limits

**Setup:**
1. Log in as admin
2. Go to **E-Wallet Monitor → Top Up Member** or use admin transfer (admin → member)

**Steps:**
1. Top up a member with ₱10,000
2. Top up the same member again with ₱15,000
3. Total: ₱25,000 in one day

**Expected Result:**
- Both top-ups succeed
- Admin's own `ewallet_sent_today` and `ewallet_sent_this_week` do NOT change
- No limit error appears

**Verify:**
```sql
SELECT ewallet_sent_today, ewallet_sent_this_week
FROM users
WHERE role = 'admin';

-- Expected: both are 0.00 (unchanged)
```

---

#### Test 4.2: Member Still Cannot Exceed Limit After Admin Top-Up

**Setup:**
- Member received ₱25,000 admin top-up (balance is high)
- Member's `ewallet_sent_today = 0.00`

**Steps:**
1. Log in as that member
2. Try to send ₱6,000 to another member

**Expected Result:**
- Transfer is **blocked** by daily limit
- Having a high balance from admin top-up does NOT bypass transfer limits

---

### 4.5 Limit Reset Cron

**Purpose:** Verify `cron/fund_transfer_limit_reset.php` resets counters correctly.

---

#### Test 5.1: Cron Runs Without Errors

**Steps:**
1. Run the cron manually:
```bash
php /path/to/site/cron/fund_transfer_limit_reset.php
```

**Expected Result:**
- No PHP fatal errors
- Output similar to:
```
[INFO ] ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
[INFO ] Transfer limit reset started — Altas Farm
[INFO ] Date      : Wed, 28 May 2026
[INFO ] Is Monday : NO (daily reset only)
[OK   ] Database connection established.
[INFO ] Members (total)              : 16
[INFO ] Members with daily usage     : 3
[INFO ] Members with weekly usage    : 3
[OK   ] Daily limit reset. Rows updated: 16
[INFO ] Weekly limit reset skipped (not Monday).
[OK   ] Verification passed — all daily counters confirmed at 0.
[INFO ] ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
[OK   ] Reset complete. Duration: 23.45ms
[INFO ] ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
```

---

#### Test 5.2: Daily Counter Resets After Cron

**Setup:**
```sql
UPDATE users
SET ewallet_sent_today = 5000.00,
    ewallet_sent_this_week = 5000.00
WHERE id = [sender_id];
```

**Steps:**
1. Confirm member cannot send ₱100 (daily limit reached)
2. Run `php cron/fund_transfer_limit_reset.php`
3. Try sending ₱100 again

**Expected Result:**
- Before cron: blocked
- After cron: succeeds
- `ewallet_sent_today` is now ₱100
- `ewallet_sent_this_week` is now ₱5,100

---

#### Test 5.3: Weekly Counter Resets on Monday

**Setup:**
- Today must be **Monday**, OR
- Temporarily modify the cron for testing (see note below)

```sql
UPDATE users
SET ewallet_sent_today = 1000.00,
    ewallet_sent_this_week = 20000.00
WHERE id = [sender_id];
```

**Steps:**
1. Confirm member cannot send ₱100 (weekly limit reached)
2. Run `php cron/fund_transfer_limit_reset.php` on a Monday
3. Try sending ₱100 again

**Expected Result:**
- Before cron: blocked
- After cron: succeeds
- `ewallet_sent_today` is reset to 0, then becomes ₱100
- `ewallet_sent_this_week` is reset to 0, then becomes ₱100

> **Testing Tip:** If today is not Monday, you can fake it temporarily by editing the cron file:
> ```php
> // Change this line for testing only:
> $isMonday = true; // instead of (date('N') === '1')
> ```
> Remember to change it back!

---

#### Test 5.4: Non-Monday Cron Does NOT Reset Weekly Counter

**Setup:**
- Ensure today is NOT Monday
- Set `ewallet_sent_this_week = 20000.00`

**Steps:**
1. Run `php cron/fund_transfer_limit_reset.php`
2. Check `ewallet_sent_this_week`

**Expected Result:**
- `ewallet_sent_today` resets to 0
- `ewallet_sent_this_week` **remains** at ₱20,000
- Log shows: `Weekly limit reset skipped (not Monday).`

---

#### Test 5.5: Cron Log File Created

**Steps:**
1. Run the cron
2. Check the logs directory:
```bash
ls -la /path/to/site/cron/logs/
```

**Expected Result:**
- File exists: `transfer_limit_reset_2026-05.log` (current month)
- File contains the cron output

---

### 4.6 Tracking Column Accuracy

**Purpose:** Confirm `ewallet_sent_today` and `ewallet_sent_this_week` always match actual transfer totals.

---

#### Test 6.1: Counter Equals Sum of Completed Transfers

**Setup:**
```sql
-- Pick a sender and note their ID
SELECT id, username FROM users WHERE role = 'member' LIMIT 1;
```

**Steps:**
1. Zero out their counters:
```sql
UPDATE users
SET ewallet_sent_today = 0.00,
    ewallet_sent_this_week = 0.00
WHERE id = [sender_id];
```
2. Send ₱1,000, then ₱2,000, then ₱1,500

**Verify:**
```sql
-- Tracking columns
SELECT ewallet_sent_today, ewallet_sent_this_week
FROM users WHERE id = [sender_id];

-- Actual sum from transfer log
SELECT COALESCE(SUM(amount),0) AS actual_sent
FROM ewallet_transfers
WHERE sender_id = [sender_id]
  AND status = 'completed'
  AND DATE(created_at) = CURDATE();

-- Expected: ewallet_sent_today == actual_sent == 4500.00
```

---

#### Test 6.2: Failed Transfers Do NOT Increment Counters

**Setup:**
- Set `ewallet_sent_today = 4900.00`

**Steps:**
1. Try to send ₱500 (should fail due to daily limit)

**Verify:**
```sql
SELECT ewallet_sent_today
FROM users WHERE id = [sender_id];

-- Expected: still 4900.00 (not 5400.00)
```

---

#### Test 6.3: Counter Increments by Transfer Amount, Not Amount + Fee

**Setup:**
- Set `ewallet_transfer_fee = 10.00` in Admin → Settings
- Zero out sender counters

**Steps:**
1. Send ₱1,000 from sender

**Verify:**
```sql
SELECT ewallet_sent_today, ewallet_sent_this_week
FROM users WHERE id = [sender_id];

-- Expected: both are 1000.00
-- The fee is NOT counted toward the limit
```

> **Important:** The limit applies to the **transfer amount**, not the total debit (amount + fee).

---

### 4.7 Regression Testing

**Purpose:** Ensure the new limit feature doesn't break existing functionality.

---

#### Test 7.1: Normal Transfer Still Works When Within Limits

**Steps:**
1. Zero out a member's counters
2. Send ₱100 to another member

**Expected Result:**
- Transfer succeeds
- Both balances update correctly
- Ledger entries created for sender and recipient

**Verify:**
```sql
SELECT * FROM ewallet_ledger
WHERE user_id IN ([sender_id], [recipient_id])
ORDER BY created_at DESC
LIMIT 4;
```

---

#### Test 7.2: Transfer History Page Still Loads

**Steps:**
1. Log in as any member
2. Go to **E-Wallet Transfer**

**Expected Result:**
- Page loads without errors
- Recent transfers shown
- No JavaScript console errors

---

#### Test 7.3: Settings Page Still Saves Limits

**Steps:**
1. Log in as admin
2. Go to **Admin → System Settings**
3. Change Daily Limit to ₱3,000 and Weekly Limit to ₱12,000
4. Click Save Settings
5. Refresh the page

**Expected Result:**
- Settings save successfully
- New values persist after refresh

**Verify:**
```sql
SELECT key_name, value FROM settings
WHERE key_name IN ('ewallet_transfer_daily_limit','ewallet_transfer_weekly_limit');

-- Expected:
-- daily_limit  = 3000.00
-- weekly_limit = 12000.00
```

> **Restore after testing:** Set limits back to defaults (₱5,000 / ₱20,000).

---

#### Test 7.4: Minimum Transfer Still Enforced

**Steps:**
1. Set `ewallet_min_transfer = 50.00`
2. Try to send ₱10

**Expected Result:**
- Transfer blocked by minimum transfer rule
- Limit counters do NOT increment

---

## 5. Bug Reporting Template

When you find an issue, use this format:

```markdown
### Bug Report — Transfer Limits

**Tester:** [Your Name]
**Date:** [YYYY-MM-DD HH:MM]
**Test Case:** [e.g., 1.3 Transfer One Peso Over Daily Limit]
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

### Feature PASSES if ALL of these are true:

| # | Criteria | How to Verify |
|---|----------|---------------|
| 1 | `fund_transfer_limit_reset.php` deployed | `ls cron/fund_transfer_limit_reset.php` |
| 2 | Tracking columns exist on `users` | `SHOW COLUMNS FROM users` |
| 3 | Members blocked when daily limit exceeded | Test 1.3 |
| 4 | Members blocked when weekly limit exceeded | Test 2.3 |
| 5 | Members allowed when within both limits | Test 3.3 |
| 6 | Counters increment correctly on successful transfer | Test 6.1 |
| 7 | Failed transfers do NOT increment counters | Test 6.2 |
| 8 | Admin transfers exempt from limits | Test 4.1 |
| 9 | Daily counter resets at midnight | Test 5.2 |
| 10 | Weekly counter resets on Monday midnight | Test 5.3 |
| 11 | Cron runs without errors | Test 5.1 |
| 12 | Cron log file created correctly | Test 5.5 |
| 13 | Existing transfer flow unbroken | Test 7.1, 7.2, 7.4 |
| 14 | Settings save correctly | Test 7.3 |
| 15 | No PHP fatal errors in logs | Check error log after all tests |

### Feature FAILS if ANY of these are true:

- ❌ Members can send past daily limit
- ❌ Members can send past weekly limit
- ❌ Failed transfers increment counters
- ❌ Admin transfers are limited
- ❌ Cron crashes or throws fatal errors
- ❌ Cron resets weekly counter on non-Monday
- ❌ Cron fails to reset daily counter
- ❌ Existing transfer/payout features broken
- ❌ PHP fatal errors in server logs

---

## 7. FAQ

### Q1: What happens if I set the daily limit to 0?

**A:** All member transfers will be blocked. Admins can still transfer/top-up. Only set to 0 if you want to disable member-to-member transfers entirely.

### Q2: Does the weekly limit reset on Sunday or Monday?

**A:** Monday at midnight. The cron checks `date('N') === '1'` (Monday). The weekly counter tracks from the most recent Monday.

### Q3: What timezone does the cron use?

**A:** The cron uses the server's default timezone. If your server is set to UTC but your members are in the Philippines, midnight reset might happen at 8:00 AM Philippine time. Set your server timezone to `Asia/Manila` for correct behavior.

### Q4: Why are my counters not resetting even though I ran the cron?

**A:** Check these:
1. Did the cron actually run? Check `cron/logs/transfer_limit_reset_YYYY-MM.log`
2. Are you checking the right member's row?
3. Did another transfer happen AFTER the cron ran, re-incrementing the counters?

### Q5: Can I run the cron more than once per day safely?

**A:** Yes. Running it multiple times is safe — it will set `ewallet_sent_today = 0` again (no harm). However, if a member made a transfer between runs, that transfer will be "lost" from the counter. Only run it at the scheduled midnight time.

### Q6: Do transfer limits apply to admin top-ups?

**A:** **No.** Admin top-ups and admin-to-member transfers are exempt from both daily and weekly limits.

### Q7: Are fees counted toward the limit?

**A:** **No.** Only the `amount` sent to the recipient counts. The transfer fee is separate.

### Q8: What if a transfer is exactly at the limit — does it pass or fail?

**A:** It passes. The check is `>` (strictly greater than), not `>=`. So ₱5,000 on a ₱5,000 daily limit is allowed. ₱5,000.01 is blocked.

### Q9: How do I test the Monday weekly reset if today isn't Monday?

**A:** Temporarily change the cron line `$isMonday = (date('N') === '1');` to `$isMonday = true;`, run the cron, then change it back. Do NOT leave it as `true` on production.

### Q10: Where can I see the current usage for a member?

**A:** Run this SQL:
```sql
SELECT username,
       ewallet_sent_today,
       ewallet_sent_this_week
FROM users
WHERE id = [member_id];
```
