# 🧪 E-Wallet Transfer QA Testing Guide

> **Prerequisites:** You must have the e-wallet transfer feature installed (Migration 006 applied) and at least 2 member accounts plus 1 admin account.  
> **Goal:** Test every part of the e-wallet transfer, top-up, fee, limit, monitoring, and **non-withdrawable balance** system.

---

## Before You Start — Setup Checklist

1. Log in as **admin**.
2. Go to **Settings** → scroll down to **💱 E-Wallet Transfers** section.
3. Set these values for easy testing:
   - **Transfer Fee:** `10.00`
   - **Minimum Transfer:** `50.00`
   - **Daily Limit:** `500.00`
   - **Weekly Limit:** `2000.00`
4. Click **Save Settings**.
5. Make sure at least 2 members exist with e-wallet balances (you can use admin top-up to give them money).

---

## Test 1 — Database & Settings

### 1.1 Verify Tables Exist
1. Open your database manager (phpMyAdmin, HeidiSQL, or MySQL CLI).
2. Check that these tables exist:
   - `ewallet_transfers`
   - `ewallet_admin_topups`
3. Check that `users` table has column `withdrawable_balance`.
4. Check that `ewallet_ledger.ref_type` column allows `'transfer'` and `'topup'` values.

**Expected:** All checks pass. No errors.

### 1.2 Verify Settings Are Saved
1. As admin, go to **Settings**.
2. Reload the page.
3. **Expected:** The 4 transfer settings show the values you just saved (₱10 fee, ₱50 min, ₱500 daily, ₱2000 weekly).

### 1.3 Verify Settings in Database
Run this SQL:
```sql
SELECT key_name, value FROM settings 
WHERE key_name IN ('ewallet_transfer_fee','ewallet_min_transfer','ewallet_transfer_daily_limit','ewallet_transfer_weekly_limit');
```
**Expected:** All 4 rows exist with correct values.

---

## Test 2 — Admin Top-Up (Fund Your Test Accounts)

### 2.1 Top-Up a Member
1. As **admin**, click **💰 Top-Up Member** in the sidebar.
2. Enter a member's username (e.g., `member1`).
3. Enter amount: `1000.00`
4. Note: `Test funds`
5. Click **💰 Top Up Account**.

**Expected:**
- Success flash message: "Top-up completed successfully."
- The member's **total** e-wallet balance increased by ₱1,000.
- The member's **withdrawable** balance did **NOT** increase (stays the same).

**Verify with SQL:**
```sql
SELECT username, ewallet_balance, withdrawable_balance 
FROM users WHERE username = 'member1';
```
**Expected:** `ewallet_balance = 1000.00`, `withdrawable_balance = 0.00`

### 2.2 Verify Top-Up in Monitor
1. Go to **📊 E-Wallet Monitor**.
2. Click the **💰 Top-Ups** tab.
3. **Expected:** Your top-up appears in the table.
4. Check the **System Non-Withdrawable** stat card.
5. **Expected:** It shows `₱1,000.00` (or more if other members have top-ups).

### 2.3 Verify Top-Up in Recipient Ledger
1. Log in as the member you just topped up.
2. Go to **💰 Earnings** (or any page showing the e-wallet ledger).
3. **Expected:** A credit entry appears:
   - Type: `credit`
   - Ref: `topup`
   - Note: "Admin top-up by @admin"
   - Amount: `+₱1,000.00`

### 2.4 Top-Up Second Member
1. Repeat 2.1 for a second member (e.g., `member2`) with amount `500.00`.
2. **Expected:** Same success behavior. `withdrawable_balance` stays `0.00`.

---

## Test 3 — Non-Withdrawable Balance (Core Feature)

### 3.1 Member Dashboard Shows Split
1. Log in as **member1** (who received a ₱1,000 top-up).
2. Look at the **E-Wallet Balance** card on the dashboard.
3. **Expected:** It shows:
   - Total: `₱1,000.00`
   - Sub-text: `₱0.00 withdrawable · ₱1,000.00 locked`

### 3.2 Payout Page Blocks Non-Withdrawable Funds
1. As **member1**, go to **💳 Request Payout**.
2. Look at the balance hero card.
3. **Expected:**
   - Total: `₱1,000.00`
   - ✅ Withdrawable: `₱0.00`
   - 🔒 Non-Withdrawable: `₱1,000.00 (internal use only)`
4. Try to enter amount `100.00`.
5. **Expected:** The max hint shows "Max withdrawable: ₱0.00". If you try to submit, error: "Withdrawable balance insufficient. You can withdraw up to ₱0.00."

### 3.3 Transfer Page Shows Split
1. As **member1**, go to **💱 Send Money**.
2. **Expected:** Balance card shows:
   - Total: `₱1,000.00`
   - Sub-text: `₱0.00 withdrawable · ₱1,000.00 internal use`

### 3.4 Give Member Some Earned (Withdrawable) Money
This requires triggering a commission. The simplest way:
1. As **admin**, use **💰 Top-Up Member** to give member1 `₱500.00` (this is non-withdrawable).
2. Alternatively, register a new member under member1 to trigger a direct referral bonus.

For testing simplicity, let's simulate by running SQL:
```sql
UPDATE users SET withdrawable_balance = 300.00 WHERE username = 'member1';
```
*(In real testing, trigger an actual pairing or referral commission instead.)*

Now member1 has:
- Total: ₱1,500 (₱1,000 top-up + ₱500 top-up)
- Withdrawable: ₱300
- Non-Withdrawable: ₱1,200

### 3.5 Payout Now Works for Withdrawable Amount Only
1. As **member1**, go to **💳 Request Payout**.
2. **Expected:** Max withdrawable shows `₱300.00`.
3. Try to request `₱400.00`.
4. **Expected:** Error: "Withdrawable balance insufficient. You can withdraw up to ₱300.00."
5. Try to request `₱200.00`.
6. **Expected:** Success (assuming min payout ≤ 200).

### 3.6 Verify Payout Debit Reduces Both Columns
After requesting a ₱200 payout, run:
```sql
SELECT ewallet_balance, withdrawable_balance FROM users WHERE username = 'member1';
```
**Expected:**
- `ewallet_balance` decreased by ₱200
- `withdrawable_balance` decreased by ₱200

---

## Test 4 — Member-to-Member Transfer (Happy Path)

### 4.1 Basic Transfer
1. Make sure **member1** has at least ₱110 total balance (some withdrawable, some non-withdrawable).
2. Log in as **member1**.
3. Click **💱 Send Money**.
4. Recipient: `member2`
5. Amount: `100.00`
6. Note: `Lunch payment`
7. Password: enter member1's password
8. Click **💸 Send Transfer**.

**Expected:**
- Success flash: "Transfer completed successfully."
- You are redirected back to the Send Money page.

### 4.2 Verify Balances After Transfer
Run this SQL:
```sql
SELECT username, ewallet_balance, withdrawable_balance FROM users WHERE username IN ('member1','member2');
```
**Expected:**
- `member1`: `ewallet_balance` decreased by ₱110 (₱100 + ₱10 fee). `withdrawable_balance` decreased by `max(0, 110 - non_withdrawable)` — non-withdrawable spent first.
- `member2`: `ewallet_balance` increased by ₱100. `withdrawable_balance` **unchanged** (transfer in is non-withdrawable).

### 4.3 Verify Transfer in Sender Ledger
1. Still logged in as **member1**, go to **💰 Earnings**.
2. **Expected:** A debit entry appears:
   - Amount: `-₱110.00`
   - Ref: `transfer`
   - Note: "Transfer to @member2 — Lunch payment"

### 4.4 Verify Transfer in Recipient Ledger
1. Log in as **member2**.
2. Go to **💰 Earnings**.
3. **Expected:** A credit entry appears:
   - Amount: `+₱100.00`
   - Ref: `transfer`
   - Note: "Transfer from @member1 — Lunch payment"

### 4.5 Verify Recent Transfers Table
1. As **member1**, go to **💱 Send Money**.
2. Scroll down to **📋 Recent Transfers**.
3. **Expected:** The transfer shows:
   - Direction: `SENT`
   - Counterparty: `@member2`
   - Amount: `₱100.00`
   - Fee: `₱10.00`
   - Note: `Lunch payment`

### 4.6 Verify Transfer in Admin Monitor
1. Log in as **admin**.
2. Go to **📊 E-Wallet Monitor**.
3. **Expected:**
   - **💱 Transfers** tab shows the transfer row.
   - **💸 Fee Credits** tab shows a ₱10.00 fee row.
   - Stat card "Total Transfers" shows `₱100.00`.
   - Stat card "Total Fees Collected" shows `₱10.00`.

### 4.7 Verify Admin E-Wallet Got the Fee
Run this SQL:
```sql
SELECT username, ewallet_balance, withdrawable_balance FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1;
```
**Expected:** The admin `ewallet_balance` increased by ₱10.00. `withdrawable_balance` unchanged (fee is non-withdrawable for admin too).

---

## Test 5 — Transfer Validation & Error Cases

### 5.1 Transfer to Self (Blocked)
1. As **member1**, go to **💱 Send Money**.
2. Recipient: `member1` (your own username).
3. Amount: `50.00`
4. Password: correct password
5. Click **Send Transfer**.

**Expected:** Error flash: "You cannot transfer to yourself." No balance changes.

### 5.2 Transfer to Non-Existent User (Blocked)
1. As **member1**, go to **💱 Send Money**.
2. Recipient: `fakeuser123`
3. Amount: `50.00`
4. Password: correct password
5. Click **Send Transfer**.

**Expected:** Error flash: "Recipient not found." No balance changes.

### 5.3 Wrong Password (Blocked)
1. As **member1**, go to **💱 Send Money**.
2. Recipient: `member2`
3. Amount: `50.00`
4. Password: `wrongpassword`
5. Click **Send Transfer**.

**Expected:** Error flash: "Password confirmation is incorrect." No balance changes.

### 5.4 Below Minimum Transfer (Blocked)
1. As **member1**, go to **💱 Send Money**.
2. Recipient: `member2`
3. Amount: `10.00` (below ₱50 minimum).
4. Password: correct password
5. Click **Send Transfer**.

**Expected:** Error flash: "Minimum transfer is ₱50.00." No balance changes.

### 5.5 Zero/Negative Amount (Blocked)
1. As **member1**, go to **💱 Send Money**.
2. Recipient: `member2`
3. Amount: `0.00`
4. Password: correct password
5. Click **Send Transfer**.

**Expected:** Error flash: "Invalid amount." No balance changes.

### 5.6 Insufficient Balance (Blocked)
1. Check member1's current total balance.
2. Try to send an amount **greater than balance + fee**.
3. Password: correct password
4. Click **Send Transfer**.

**Expected:** Error flash: "Insufficient balance." No balance changes.

**Verify with SQL:**
```sql
SELECT ewallet_balance, withdrawable_balance FROM users WHERE username = 'member1';
```
**Expected:** Both values are exactly the same as before the failed attempt.

---

## Test 6 — Transfer Limits

### 6.1 Daily Limit
1. As **member1**, note your current balance.
2. Send ₱200 to member2. **Should succeed.**
3. Send another ₱200 to member2. **Should succeed.**
4. Try to send ₱200 more.

**Expected:** If total sent today would exceed ₱500 daily limit, error: "Daily transfer limit exceeded."

**Tip:** Run this SQL to check today's total:
```sql
SELECT COALESCE(SUM(amount),0) FROM ewallet_transfers 
WHERE sender_id = (SELECT id FROM users WHERE username = 'member1') 
  AND status = 'completed' AND DATE(created_at) = CURDATE();
```

### 6.2 Weekly Limit
1. Continue sending from member1 until approaching the weekly limit.
2. Or temporarily lower the weekly limit in Settings to a small number (e.g., ₱100) and try to send ₱200.

**Expected:** Error flash: "Weekly transfer limit exceeded."

---

## Test 7 — Admin Transfer (No Fee)

### 7.1 Admin Sends Money to Member
1. Log in as **admin**.
2. Click **💱 E-Wallet Transfer** in the sidebar.
3. **Expected:** You see the same transfer form but with a badge: "👤 Admin Mode — No Fee".
4. Recipient: `member2`
5. Amount: `100.00`
6. Note: `Admin gift`
7. Password: admin password
8. Click **💸 Send Transfer**.

**Expected:** Success flash. Admin balance decreased by exactly ₱100.00 (no fee). Member2 balance increased by exactly ₱100.00.

### 7.2 Verify Recipient Got Non-Withdrawable Credit
Run this SQL:
```sql
SELECT ewallet_balance, withdrawable_balance FROM users WHERE username = 'member2';
```
**Expected:** `ewallet_balance` increased by ₱100. `withdrawable_balance` unchanged.

### 7.3 Verify No Fee in Monitor
1. Go to **📊 E-Wallet Monitor**.
2. Find the transfer row.
3. **Expected:** Fee column shows `—` (dash).

---

## Test 8 — Member Transfer to Admin

### 8.1 Member Sends Money to Admin
1. Log in as **member1**.
2. Go to **💱 Send Money**.
3. Recipient: `admin`
4. Amount: `50.00`
5. Password: member1's password
6. Click **Send Transfer**.

**Expected:** Success flash. Transfer completes with the ₱10 fee applied (member sender = fee applies).

### 8.2 Verify
1. Member1 balance decreased by ₱60.00 (₱50 + ₱10 fee).
2. Admin balance increased by ₱50.00 (transfer) + ₱10.00 (fee) = ₱60.00 total.
3. Admin `withdrawable_balance` unchanged (both are non-withdrawable credits).

---

## Test 9 — Admin User View Tab

### 9.1 View Member Transfer History
1. As **admin**, go to **Members**.
2. Click **View** on `member1`.
3. Click the **💱 Transfers** tab.
4. **Expected:** A table showing all transfers where member1 was sender or recipient.

### 9.2 Verify Direction Badges
1. Look at rows where member1 is the sender.
2. **Expected:** Red `SENT` badge.
3. Look at rows where member1 is the recipient.
4. **Expected:** Green `RECEIVED` badge.

### 9.3 Verify Split Balance in Profile
1. On the same admin user view, look at the **E-Wallet Balance** stat card.
2. **Expected:** It shows the total balance with a sub-line like:
   - `₱300.00 withdrawable · ₱1,200.00 internal`

---

## Test 10 — Reactivation Uses Non-Withdrawable First

### 10.1 Setup
1. Cap a member so they need reactivation. Or find a capped member.
2. Ensure the member has:
   - Some non-withdrawable balance (from transfers/top-ups)
   - Some withdrawable balance (from commissions)

### 10.2 Reactivate Using E-Wallet
1. As the capped member, go to reactivation.
2. Choose **E-Wallet** payment method.
3. Pay the reactivation fee.

**Expected:** Reactivation succeeds. The fee is deducted from e-wallet.

### 10.3 Verify Non-Withdrawable Spent First
Run SQL before and after:
```sql
SELECT ewallet_balance, withdrawable_balance FROM users WHERE username = 'capped_member';
```

**Example:**
- Before: `ewallet_balance = 1000`, `withdrawable_balance = 300`
- Fee: `500`
- After: `ewallet_balance = 500`, `withdrawable_balance = 300` (if non-withdrawable was ₱700, fee used ₱500 from non-withdrawable)
- OR: `ewallet_balance = 500`, `withdrawable_balance = 200` (if non-withdrawable was only ₱400, fee used ₱400 non-withdrawable + ₱100 withdrawable)

**Rule:** Non-withdrawable is spent first. Withdrawable is only touched if non-withdrawable is insufficient.

---

## Test 11 — Admin Monitor System Stats

### 11.1 System Balance Overview
1. As **admin**, go to **📊 E-Wallet Monitor**.
2. Scroll to the **System Balance Overview** row (below the main stats).
3. **Expected:**
   - **System Withdrawable** card: sum of all members' `withdrawable_balance`
   - **System Non-Withdrawable** card: sum of all members' non-withdrawable funds
   - **Total E-Wallet Funds** card: combined total with a green/locked progress bar

---

## Test 12 — Reset Behavior

### 12.1 Tables Cleared on Reset
1. As admin, go to `reset.php` in your browser.
2. Click **Reset Database**.
3. After reset, run this SQL:
```sql
SELECT COUNT(*) FROM ewallet_transfers;
SELECT COUNT(*) FROM ewallet_admin_topups;
SELECT withdrawable_balance FROM users WHERE role = 'member';
```
**Expected:**
- Both counts return `0`.
- All members have `withdrawable_balance = 0.00`.

---

## Test 13 — Sidebar Navigation

### 13.1 Member Sidebar
1. Log in as any member.
2. **Expected:** Sidebar shows **💱 Send Money** between Payouts and Profile.

### 13.2 Admin Sidebar
1. Log in as admin.
2. **Expected:** Sidebar under **Finance** shows:
   - 💸 Payouts
   - 💱 E-Wallet Transfer
   - 💰 Top-Up Member
   - 📊 E-Wallet Monitor

---

## Regression Tests (Make Sure Old Stuff Still Works)

| Test | Steps | Expected |
|------|-------|----------|
| R1. Member registration | Register a new member | Member gets active cap status, ₱0 balance, ₱0 withdrawable |
| R2. Pairing bonus | Trigger a pairing | Commission credited, `withdrawable_balance` also increases |
| R3. Payout request (withdrawable) | Member with earned commissions requests payout | Uses `withdrawable_balance` as max limit |
| R4. Admin payout approval | Admin approves payout | E-wallet and withdrawable both debited |
| R5. E-wallet ledger | View earnings page | Old commission/payout entries still show correctly |
| R6. Reactivation | Capped member reactivates | Cap resets to active, fee debited (non-withdrawable first) |
| R7. VIP toggle | Admin grants VIP | Badge shows, cap bypassed |
| R8. DFI | Run midnight reset | DFI pays correctly, `withdrawable_balance` increases |

---

## Quick SQL Cheat Sheet

```sql
-- Check member balances (both columns)
SELECT username, ewallet_balance, withdrawable_balance, (ewallet_balance - withdrawable_balance) AS non_withdrawable 
FROM users WHERE role = 'member';

-- Check all transfers today
SELECT * FROM ewallet_transfers WHERE DATE(created_at) = CURDATE() ORDER BY created_at DESC;

-- Check all top-ups
SELECT * FROM ewallet_admin_topups ORDER BY created_at DESC;

-- Check admin fee earnings
SELECT * FROM ewallet_ledger WHERE ref_type = 'transfer' AND note LIKE '%fee%' ORDER BY created_at DESC;

-- Check total sent by a member today
SELECT COALESCE(SUM(amount),0) FROM ewallet_transfers 
WHERE sender_id = (SELECT id FROM users WHERE username = 'member1') 
  AND status = 'completed' AND DATE(created_at) = CURDATE();

-- Check total sent this week
SELECT COALESCE(SUM(amount),0) FROM ewallet_transfers 
WHERE sender_id = (SELECT id FROM users WHERE username = 'member1') 
  AND status = 'completed' 
  AND created_at >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY);

-- Verify data integrity (should return 0 rows)
SELECT id, username FROM users 
WHERE withdrawable_balance > ewallet_balance OR withdrawable_balance < 0;
```

---

## Test Completion Checklist

- [ ] 1.1 — Tables and columns exist
- [ ] 1.2 — Settings persist in UI
- [ ] 1.3 — Settings persist in DB
- [ ] 2.1 — Admin top-up succeeds (non-withdrawable)
- [ ] 2.2 — Top-up shows in monitor
- [ ] 2.3 — Top-up shows in recipient ledger (withdrawable unchanged)
- [ ] 2.4 — Second top-up works
- [ ] 3.1 — Dashboard shows split balance
- [ ] 3.2 — Payout page blocks non-withdrawable
- [ ] 3.3 — Transfer page shows split
- [ ] 3.4 — Give member some withdrawable funds
- [ ] 3.5 — Payout works only for withdrawable amount
- [ ] 3.6 — Payout debit reduces both columns equally
- [ ] 4.1 — Member transfer succeeds
- [ ] 4.2 — Balances correct after transfer (recipient withdrawable unchanged)
- [ ] 4.3 — Sender ledger entry correct
- [ ] 4.4 — Recipient ledger entry correct
- [ ] 4.5 — Recent transfers table shows transfer
- [ ] 4.6 — Admin monitor shows transfer + fee
- [ ] 4.7 — Admin e-wallet got the fee
- [ ] 5.1 — Self-transfer blocked
- [ ] 5.2 — Fake recipient blocked
- [ ] 5.3 — Wrong password blocked
- [ ] 5.4 — Below minimum blocked
- [ ] 5.5 — Zero amount blocked
- [ ] 5.6 — Insufficient balance blocked
- [ ] 6.1 — Daily limit enforced
- [ ] 6.2 — Weekly limit enforced
- [ ] 7.1 — Admin transfer no fee
- [ ] 7.2 — Recipient got non-withdrawable credit
- [ ] 7.3 — No fee in monitor
- [ ] 8.1 — Member can send to admin
- [ ] 8.2 — Fee applies when member sends to admin
- [ ] 9.1 — Transfers tab on user view works
- [ ] 9.2 — Direction badges correct
- [ ] 9.3 — Split balance shown in admin user view
- [ ] 10.1 — Reactivation debit uses non-withdrawable first
- [ ] 10.2 — Withdrawable only touched if non-withdrawable insufficient
- [ ] 11.1 — System balance overview cards show correct totals
- [ ] 12.1 — Reset clears transfer tables and withdrawable_balance
- [ ] 13.1 — Member sidebar has Send Money
- [ ] 13.2 — Admin sidebar has all 3 links
- [ ] R1–R8 — All regression tests pass

**All tests passing?** ✅ The e-wallet transfer system with non-withdrawable tracking is fully operational!
