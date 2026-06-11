# QA Tester Guide — Disable Binary Pairing Bonuses

**Version:** v1.0  
**Date:** 2026-05-28  
**System:** Altas Farm MLM Binary System  
**Feature:** Binary Enable/Disable Toggle (`binary_enabled` setting)

---

## Table of Contents

1. [What is This Testing?](#1-what-is-this-testing)
2. [Prerequisites](#2-prerequisites)
3. [Test Environment Setup](#3-test-environment-setup)
4. [Test Cases](#4-test-cases)
   - 4.1 [Admin Toggle](#41-admin-toggle)
   - 4.2 [Commission Behavior](#42-commission-behavior)
   - 4.3 [Member Dashboard & Sidebar](#43-member-dashboard--sidebar)
   - 4.4 [Member Genealogy & Earnings](#44-member-genealogy--earnings)
   - 4.5 [Registration Flow](#45-registration-flow)
   - 4.6 [Admin Panel Visibility](#46-admin-panel-visibility)
   - 4.7 [Frontend Landing Page](#47-frontend-landing-page)
   - 4.8 [Re-enabling Binary (Regression)](#48-re-enabling-binary-regression)
5. [Bug Reporting Template](#5-bug-reporting-template)
6. [Pass/Fail Criteria](#6-passfail-criteria)
7. [FAQ](#7-faq)

---

## 1. What is This Testing?

This guide tests the **Binary Enable/Disable Toggle** — a system-wide switch that controls whether the binary pairing bonus system is active.

When **enabled** (default): members see binary trees, choose positions during registration, and earn pairing bonuses when left-right pairs form.

When **disabled**: pairing bonuses stop firing, binary UI is hidden everywhere, and registration auto-assigns placement under the sponsor.

### What's Being Tested

| Component | What It Does | Where to Test |
|-----------|-------------|---------------|
| `binary_enabled` setting | Master switch stored in `settings` table | Admin → Settings |
| `Commission::processBinaryPlacement()` | Skips pairing bonus logic when disabled | Register a member, check commissions |
| Member views | Conditionally hide binary UI | Dashboard, Genealogy, Earnings, Sidebar |
| Admin views | Conditionally hide binary fields | Packages, Users, User View |
| Registration | Auto-assign placement when disabled | `?page=register` |
| Frontend | Adjust marketing copy when disabled | Landing page |

### What's NOT Being Tested Here

- ❌ Direct referral bonuses — always active, unaffected
- ❌ Indirect/unilevel bonuses — controlled by separate toggle
- ❌ Daily Fixed Income — controlled by separate toggle
- ❌ E-wallet transfers — unrelated feature
- ❌ Lifetime capping — still applies to direct/indirect

> **Rule of Thumb:** If it doesn't say "binary," "pairing," "pair," "tree," or "left/right leg," it's out of scope.

---

## 2. Prerequisites

Before you start testing, ensure you have:

### Required Access
- [ ] Admin login credentials
- [ ] At least **two member accounts** with an active sponsor relationship
- [ ] Database access (phpMyAdmin, MySQL CLI, or similar)

### Required Settings
Verify these settings exist in **Admin → System Settings**:

| Setting | Default | Where It Lives |
|---------|---------|----------------|
| `binary_enabled` | `1` | Admin → Settings → Compensation Plan Defaults |
| `indirect_referral_enabled` | `1` | Admin → Settings (for comparison) |

**Quick Check:**
```sql
SELECT key_name, value FROM settings WHERE key_name = 'binary_enabled';
```

**Expected Result:**
```
+---------------+-------+
| key_name      | value |
+---------------+-------+
| binary_enabled| 1     |
+---------------+-------+
```

### Migration Must Be Applied
- [ ] `migrations/013_add_binary_toggle.sql` has been run

**Verify:**
```sql
SELECT * FROM settings WHERE key_name = 'binary_enabled';
```

If no row exists, run:
```sql
INSERT INTO settings (`key_name`, `value`) VALUES ('binary_enabled', '1');
```

---

## 3. Test Environment Setup

### Step 1: Backup (CRITICAL)

```bash
mysqldump -u your_username -p u938213108_altas_db > backup_before_binary_toggle_test.sql
```

### Step 2: Verify Files Are Deployed

Run these commands on your server:

```bash
# Check Commission.php has the binary guard
grep -n "binary_enabled" /path/to/site/core/Commission.php

# Check MemberController has the redirect
grep -n "binary_enabled" /path/to/site/controllers/MemberController.php

# Check register view has the conditional
grep -n "BINARY_ENABLED\|binaryEnabled" /path/to/site/views/auth/register.php
```

**Expected Result:**
- `Commission.php` contains `setting('binary_enabled', '1') !== '1'`
- `MemberController.php` contains `binary_enabled` check in `genealogy()`
- `register.php` contains `BINARY_ENABLED` JS constant and `binaryEnabled` PHP var

### Step 3: Prepare Test Members

You need at least **one sponsor** with at least **one member already placed** in their binary tree.

```sql
-- Find a member who has children in the binary tree
SELECT u.id, u.username, u.cap_status,
       COUNT(c.id) AS child_count
FROM users u
LEFT JOIN users c ON c.binary_parent_id = u.id
WHERE u.role = 'member'
GROUP BY u.id
HAVING child_count > 0
LIMIT 1;
```

Note the `id` as your **test sponsor**. You'll register new members under this sponsor.

### Step 4: Start With Binary ENABLED

Ensure binary is enabled before starting:

```sql
UPDATE settings SET value = '1' WHERE key_name = 'binary_enabled';
```

Clear any browser cache/cookies for your test domain.

---

## 4. Test Cases

### 4.1 Admin Toggle

**Purpose:** Verify the toggle exists, saves correctly, and persists.

---

#### Test 1.1: Toggle Exists in Settings

**Steps:**
1. Log in as admin
2. Go to **Admin → System Settings**
3. Scroll to **Compensation Plan Defaults**

**Expected Result:**
- A switch labeled **"Enable Binary Pairing Bonuses"** is visible
- It is currently **ON** (checked)
- Below it reads: *"When disabled, no pairing bonuses are paid, binary placement is hidden during registration, and all binary-related UI is hidden from members and admin."*

---

#### Test 1.2: Disable Binary on a Clean System (No Members)

> ⚠️ **CRITICAL CONSTRAINT:** Binary can only be disabled when **zero members** exist in the system (admin-only state). If any member accounts exist, the admin must run `reset.php` first.

**Prerequisite:**
```sql
-- Confirm no members exist (only admin should show up)
SELECT COUNT(*) FROM users WHERE role = 'member';
-- Expected: 0
```

**Steps:**
1. Ensure the system has **no members** (only admin)
2. In Admin → Settings, uncheck **Enable Binary Pairing Bonuses**
3. Click **Save Settings**
4. Wait for the success flash message
5. Refresh the page (F5)

**Expected Result:**
- Settings saved successfully
- After refresh, the toggle is still **OFF** (unchecked)

**Verify:**
```sql
SELECT value FROM settings WHERE key_name = 'binary_enabled';
-- Expected: 0
```

---

#### Test 1.2b: Disable Attempt Blocked When Members Exist

**Purpose:** Verify the guard prevents disabling binary on a live system with existing members.

**Setup:** Ensure at least one member exists:
```sql
SELECT COUNT(*) FROM users WHERE role = 'member';
-- Expected: > 0
```

**Steps:**
1. As admin, go to **System Settings**
2. Ensure the toggle **Enable Binary Pairing Bonuses** is currently **ON**
3. Uncheck it (try to disable)
4. Click **Save Settings**

**Expected Result:**
- ❌ Error flash message appears: *"Cannot disable binary pairing: X member(s) already exist..."*
- Toggle remains **ON** after redirect
- `binary_enabled` in DB is still `1`

**Verify:**
```sql
SELECT value FROM settings WHERE key_name = 'binary_enabled';
-- Expected: 1 (unchanged)
```

> **Note:** To actually disable binary after members exist, the admin must run `reset.php` (which clears all member data), **then** disable binary **before** anyone registers again.

---

#### Test 1.3: Toggle Re-enables Correctly

**Steps:**
1. Check **Enable Binary Pairing Bonuses** back ON
2. Click **Save Settings**
3. Refresh the page

**Expected Result:**
- Toggle is **ON** after refresh

**Verify:**
```sql
SELECT value FROM settings WHERE key_name = 'binary_enabled';
-- Expected: 1
```

> **Important:** After each binary-off test below, re-enable binary before the next test suite unless the test explicitly says "with binary disabled."
>
> **For testing on a system WITH members:** You can only fully test "binary disabled" behavior by either:
> 1. Running `reset.php` to clear all members → disable binary → test → re-enable → re-register test members, OR
> 2. Manually setting `UPDATE settings SET value = '0' WHERE key_name = 'binary_enabled'` via SQL for read-only UI verification (bypassing the guard). Don't forget to re-enable afterward.

---

### 4.2 Commission Behavior

**Purpose:** Verify pairing bonuses fire when enabled and stop when disabled.

---

#### Test 2.1: Pairing Bonus Fires When Enabled

**Setup:**
```sql
-- Ensure binary is ON
UPDATE settings SET value = '1' WHERE key_name = 'binary_enabled';
```

**Steps:**
1. Log in as a member (your test sponsor)
2. Note their current `pairs_paid` and `ewallet_balance`
3. Register a new member under this sponsor
4. Place the new member in the sponsor's binary tree (choose an empty slot)

**Expected Result:**
- Sponsor receives a **pairing bonus** if a pair formed
- Sponsor's `pairs_paid` increments
- Sponsor's `ewallet_balance` increases by the pairing bonus amount

**Verify:**
```sql
-- After registration
SELECT pairs_paid, ewallet_balance
FROM users
WHERE id = [sponsor_id];

-- Check commission record
SELECT amount, type, status
FROM commissions
WHERE user_id = [sponsor_id] AND type = 'pairing'
ORDER BY created_at DESC
LIMIT 1;
```

---

#### Test 2.2: Pairing Bonus Does NOT Fire When Disabled

**Setup:**
```sql
-- Turn binary OFF
UPDATE settings SET value = '0' WHERE key_name = 'binary_enabled';
```

**Steps:**
1. Note sponsor's current `pairs_paid` and `ewallet_balance`
2. Register a new member under the same sponsor
3. The registration will auto-assign binary placement (no UI to choose)

**Expected Result:**
- Registration succeeds
- **NO pairing bonus** is credited
- Sponsor's `pairs_paid` does **NOT** increment
- Sponsor's `ewallet_balance` does **NOT** increase from pairing
- Direct referral bonus **still fires** (unaffected)

**Verify:**
```sql
-- After registration
SELECT pairs_paid, ewallet_balance
FROM users
WHERE id = [sponsor_id];
-- pairs_paid should be SAME as before

-- Check commissions
SELECT amount, type, status
FROM commissions
WHERE user_id = [sponsor_id]
ORDER BY created_at DESC
LIMIT 5;
-- Should see 'direct_referral' but NO 'pairing'
```

---

#### Test 2.3: Tree Structure Still Updates When Disabled

**Purpose:** Confirm that disabling binary only stops **bonuses**, not tree placement.

**Setup:**
- Binary is OFF
- Register a new member

**Steps:**
1. After registering a member with binary disabled, check their placement

**Verify:**
```sql
SELECT id, username, sponsor_id, binary_parent_id, binary_position
FROM users
ORDER BY id DESC
LIMIT 1;

-- Expected:
-- binary_parent_id is set (not NULL)
-- binary_position is 'left' or 'right'
```

**Expected Result:**
- The member **is still placed** in the binary tree
- `binary_parent_id` and `binary_position` are populated
- Only the **bonus** is skipped — the data structure remains intact

---

### 4.3 Member Dashboard & Sidebar

**Purpose:** Verify all binary-related UI is hidden from members when disabled.

For these tests, **log in as a regular member** (not admin).

---

#### Test 3.1: Sidebar Hides Binary Tree Link

**Setup:**
```sql
UPDATE settings SET value = '0' WHERE key_name = 'binary_enabled';
```

**Steps:**
1. Log in as any member
2. Look at the left sidebar

**Expected Result:**
- **"👥 Referral Network"** link is visible
- **"🌳 Binary Tree"** link is **NOT visible**

---

#### Test 3.2: Dashboard Hides Pairing Earnings Card

**Steps:**
1. Go to **Member Dashboard**
2. Look at the stat cards at the top

**Expected Result (binary OFF):**
- 💰 E-Wallet Balance — visible
- 🤝 Pairing Earnings — **HIDDEN**
- 👥 Direct Referral — visible
- 🔗 Indirect Referral — visible (if enabled)

**Expected Result (binary ON):**
- 🤝 Pairing Earnings — visible

---

#### Test 3.3: Dashboard Hides Binary Legs Section

**Steps:**
1. On the Member Dashboard, scroll down

**Expected Result (binary OFF):**
- **"🌳 Binary Legs"** section is **completely absent**
- No left/right leg counts
- No "Lifetime pairs paid," "Pairs flushed," or "Bonus / pair" rows

**Expected Result (binary ON):**
- Binary Legs section is present with all data

---

#### Test 3.4: Recent Activity Excludes Pairing

**Setup:**
- You need a member who has earned pairing bonuses in the past (before binary was disabled)

**Steps:**
1. On the Member Dashboard, look at **Recent Activity**

**Expected Result (binary OFF):**
- Pairing bonus entries (🤝) are **NOT shown**
- Direct referral (👥), DFI (📅), and indirect (🔗) entries still appear

---

#### Test 3.5: Cap Status Page Hides Pairing

**Steps:**
1. Go to **Lifetime Cap** page
2. Scroll to the earning timeline and breakdown

**Expected Result (binary OFF):**
- Timeline does **NOT** show "🤝 Pairing Bonuses" even if the member has pairing earnings
- Breakdown table does **NOT** include a "Pairing" row

---

### 4.4 Member Genealogy & Earnings

**Purpose:** Verify genealogy and earnings pages adapt when binary is disabled.

---

#### Test 4.1: Genealogy Redirects Binary → Referral

**Setup:**
```sql
UPDATE settings SET value = '0' WHERE key_name = 'binary_enabled';
```

**Steps:**
1. Manually visit `/?page=genealogy&view=binary`

**Expected Result:**
- Page automatically redirects to `/?page=genealogy&view=referral`
- URL in address bar shows `view=referral`

---

#### Test 4.2: Genealogy Shows Only Referral Tab

**Steps:**
1. Go to **Referral Network** (or let the redirect above take you there)
2. Look at the tabs at the top

**Expected Result:**
- Only **"👥 Referral Network"** tab is visible
- **"🌳 Binary Tree"** tab is **hidden**

---

#### Test 4.3: Earnings Page Hides Pairing

**Steps:**
1. Go to **Earnings** page
2. Look at stat cards

**Expected Result:**
- **"Pairing Bonuses"** stat card is **hidden**
- Filter tabs at the top do **NOT** include "🤝 Pairing"

---

#### Test 4.4: Earnings Filter Tabs Are Correct

**Steps:**
1. On the Earnings page, look at the filter tabs

**Expected Result (binary OFF, indirect ON):**
- All
- 👥 Direct
- 🔗 Indirect
- 📅 DFI

**Expected Result (binary OFF, indirect OFF):**
- All
- 👥 Direct
- 📅 DFI

---

### 4.5 Registration Flow

**Purpose:** Verify registration hides binary placement and auto-assigns when disabled.

---

#### Test 5.1: Registration Hides Binary Placement Fields

**Setup:**
```sql
UPDATE settings SET value = '0' WHERE key_name = 'binary_enabled';
```

**Steps:**
1. Log in as a member
2. Go to **Register Member**
3. Fill in Step 1 (username, password, package)
4. Click **Continue →**

**Expected Result:**
- Step 2 shows:
  - ✅ Sponsor Username field
  - ❌ **NO** Binary Upline Username field
  - ❌ **NO** Binary Position (Left/Right) radio buttons
  - ℹ️ Info alert: *"Binary placement is auto-assigned by the system. You only need a sponsor."*

---

#### Test 5.2: Registration Review Step Hides Upline/Position

**Steps:**
1. Continue from Step 2 to Step 3 (Review)

**Expected Result:**
- Review table shows:
  - Username
  - Sponsor
  - Package
  - ❌ **NO** Upline row
  - ❌ **NO** Position row
- ❌ **NO** "⚠️ Binary position cannot be changed after registration." warning

---

#### Test 5.3: Registration Succeeds Without Binary Fields

**Steps:**
1. Submit the registration form

**Expected Result:**
- Registration succeeds
- New member is created
- New member is placed in the binary tree (auto-assigned)

**Verify:**
```sql
SELECT id, username, sponsor_id, binary_parent_id, binary_position
FROM users
ORDER BY id DESC
LIMIT 1;

-- Expected:
-- sponsor_id is set
-- binary_parent_id is set (auto-assigned)
-- binary_position is 'left' or 'right'
```

---

#### Test 5.4: Guest Registration Also Hides Binary

**Steps:**
1. Log out
2. Visit `/?page=register` as a guest
3. Use a registration code
4. Proceed through the form

**Expected Result:**
- Same as logged-in registration: no binary upline or position fields
- Sponsor field still required
- Placement auto-assigned under the sponsor

---

### 4.6 Admin Panel Visibility

**Purpose:** Verify admin views hide binary-related fields when disabled.

---

#### Test 6.1: Packages List Hides Pair Column

**Setup:**
```sql
UPDATE settings SET value = '0' WHERE key_name = 'binary_enabled';
```

**Steps:**
1. Log in as admin
2. Go to **Admin → Packages**

**Expected Result:**
- Package table shows columns: Name, Entry, Cap, DFI, Status, Actions
- **Pair** column is **hidden**

---

#### Test 6.2: Package Form Hides Binary Fields

**Steps:**
1. On Packages page, click **Edit** on any package (or click **New Package**)

**Expected Result:**
- Form shows: Package Name, Entry Fee
- ❌ **NO** Pairing Bonus field
- ❌ **NO** Daily Pair Cap field
- ✅ Direct Referral Bonus field is still visible
- ✅ Lifetime Cap fields are still visible

---

#### Test 6.3: Users List Hides Pairs Column

**Steps:**
1. Go to **Admin → Members**

**Expected Result:**
- Table shows: #, Username, Full Name, Package, Balance, Joined, Status, Actions
- **Pairs** column is **hidden**

---

#### Test 6.4: User View Hides Binary Stats

**Steps:**
1. Click **View** on any member
2. Look at the stat cards and info table

**Expected Result:**
- Stat cards do **NOT** include:
  - "Pairs Paid / Today"
  - "Pairs Flushed"
- Info table does **NOT** include:
  - "Upline"
  - "Pairing Bonus"
- Commission history does **NOT** show "🤝 Pairing" entries

---

### 4.7 Frontend Landing Page

**Purpose:** Verify the public landing page adjusts its copy when binary is disabled.

---

#### Test 7.1: Hero Stats Hide Per Pair Bonus

**Setup:**
```sql
UPDATE settings SET value = '0' WHERE key_name = 'binary_enabled';
```

**Steps:**
1. Visit the homepage (`/altas/` or `/`) in an incognito/private window
2. Look at the hero section stats

**Expected Result:**
- **"Per Pair Bonus"** stat is **hidden**
- Other stats (Unilevel Levels, Real Farm Products) still visible

---

#### Test 7.2: How It Works Hides Pair Bonus Step

**Steps:**
1. Scroll to **"How It Works"** section

**Expected Result:**
- Step 1: Get Your Code — visible
- Step 2: Register & Place — visible (no mention of left/right leg)
- Step 3: Build Your Team — visible
- Step 4: **Earn Pair Bonuses — HIDDEN**
- Step 5/6: Withdraw Earnings — visible, correctly renumbered

---

#### Test 7.3: Compensation Plan Hides Binary Card

**Steps:**
1. Scroll to **Compensation Plan** section

**Expected Result:**
- **"Binary Pairing Bonus"** card is **absent**
- Title reads "X Streams. One Entry." (with correct count)
- Direct Referral, Unilevel (if enabled), and DFI (if enabled) cards still present

---

#### Test 7.4: Package Features Hide Binary

**Steps:**
1. Scroll to **Packages** section
2. Look at the package feature list

**Expected Result:**
- ❌ **NO** "Full binary tree placement" bullet
- ❌ **NO** "₱X per binary pair · capped at Y pairs per day" bullet
- ✅ "Real-time dashboard — wallet, full history" (no mention of binary tree)

---

### 4.8 Re-enabling Binary (Regression)

**Purpose:** Verify everything comes back correctly when binary is re-enabled.

---

#### Test 8.1: Re-enable Binary and Verify UI Returns

**Setup:**
```sql
UPDATE settings SET value = '1' WHERE key_name = 'binary_enabled';
```

**Steps:**
1. As admin, turn binary **back ON**
2. As a member, check:
   - Dashboard (Pairing card, Binary Legs section)
   - Sidebar (Binary Tree link)
   - Genealogy (Binary Tree tab)
   - Earnings (Pairing stat card and filter)
3. As a guest, check the landing page (hero stats, How It Works steps)
4. Try registration (binary placement fields should reappear)

**Expected Result:**
- All binary UI elements **reappear**
- No broken layouts or missing elements
- Commission firing resumes on next registration

---

#### Test 8.2: Commission Fires After Re-enable

**Steps:**
1. Ensure binary is ON
2. Register a new member under a sponsor
3. Place in an empty binary slot

**Expected Result:**
- Pairing bonus **fires normally**
- Sponsor's `pairs_paid` increments
- Commission record shows `type = 'pairing'`

---

## 5. Bug Reporting Template

When you find an issue, use this format:

```markdown
### Bug Report — Disable Binary

**Tester:** [Your Name]
**Date:** [YYYY-MM-DD HH:MM]
**Test Case:** [e.g., 3.2 Dashboard Hides Pairing Earnings Card]
**Severity:** [Critical / High / Medium / Low]

**Steps to Reproduce:**
1. [Step 1]
2. [Step 2]
3. [Step 3]

**Expected Result:**
[What should happen]

**Actual Result:**
[What actually happened]

**Binary Setting:**
```sql
SELECT value FROM settings WHERE key_name = 'binary_enabled';
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
| 1 | Toggle exists in Admin Settings | Test 1.1 |
| 2 | Toggle saves and persists | Test 1.2, 1.3 |
| 2b | Guard blocks disable when members exist | Test 1.2b |
| 3 | Pairing bonus fires when enabled | Test 2.1 |
| 4 | Pairing bonus stops when disabled | Test 2.2 |
| 5 | Tree still builds when disabled | Test 2.3 |
| 6 | Member sidebar hides Binary Tree link | Test 3.1 |
| 7 | Dashboard hides Pairing card and Binary Legs | Test 3.2, 3.3 |
| 8 | Recent activity excludes pairing | Test 3.4 |
| 9 | Cap status hides pairing breakdown | Test 3.5 |
| 10 | Genealogy redirects binary → referral | Test 4.1 |
| 11 | Earnings hides pairing stat and filter | Test 4.3, 4.4 |
| 12 | Registration hides binary fields | Test 5.1 |
| 13 | Registration auto-assigns placement | Test 5.3 |
| 14 | Admin packages hide pair column/fields | Test 6.1, 6.2 |
| 15 | Admin users list hides pairs column | Test 6.3 |
| 16 | Admin user view hides pair stats | Test 6.4 |
| 17 | Frontend hides binary hero stat | Test 7.1 |
| 18 | Frontend hides pair bonus step | Test 7.2 |
| 19 | Re-enabling restores all UI | Test 8.1 |
| 20 | Re-enabling restores commission firing | Test 8.2 |

### Feature FAILS if ANY of these are true:

- ❌ Pairing bonus fires while binary is disabled
- ❌ Pairing bonus fails while binary is enabled
- ❌ Binary UI still visible after disabling
- ❌ Registration breaks when binary is disabled
- ❌ Member cannot register due to missing hidden fields
- ❌ Admin cannot save package without pairing_bonus (when hidden)
- ❌ Re-enabling binary doesn't restore UI
- ❌ PHP fatal errors when toggling binary
- ❌ Guard allows disabling binary while members exist (data integrity risk)

---

## 7. FAQ

### Q1: If binary is disabled, are members still placed in a tree?

**A:** Yes. The binary tree structure still exists in the database — placement is just auto-assigned by the system instead of chosen by the user. This preserves data integrity and makes re-enabling binary seamless.

### Q2: What happens to existing pairing bonuses when I disable binary?

**A:** Nothing. Previously earned pairing bonuses remain in the member's history, e-wallet, and commission records. Disabling binary only stops **new** pairing bonuses from firing.

### Q3: Can I disable binary and still use the genealogy page?

**A:** Yes, but only the **Referral Network** view. The Binary Tree view is hidden and auto-redirects to the referral view.

### Q4: Will disabling binary break member registration?

**A:** No. Registration works normally — it just skips the binary placement step and auto-assigns the new member under their sponsor.

### Q5: Do I need to restart the server or clear cache after toggling binary?

**A:** No. The setting is read from the database on every request. Changes take effect immediately.

### Q6: What happens to the `pairs_paid_today` midnight cron when binary is disabled?

**A:** The cron still runs and resets `pairs_paid_today = 0`. The counter still tracks (for potential re-enable), but no pairing bonuses are paid regardless.

### Q7: If binary is disabled, should I set `pairing_bonus` and `daily_pair_cap` to 0 in packages?

**A:** Not necessary. Those fields are hidden from the admin form when binary is disabled, and the values are simply not used. They'll still be there when you re-enable.

### Q8: Can I test both enabled and disabled states quickly?

**A:** Yes, but with a caveat. Use phpMyAdmin or SQL:
```sql
-- Enable
UPDATE settings SET value = '1' WHERE key_name = 'binary_enabled';

-- Disable (only works when no members exist, or bypass guard via SQL)
UPDATE settings SET value = '0' WHERE key_name = 'binary_enabled';
```
Refresh the page after each change.

> ⚠️ **Warning:** Direct SQL bypasses the member-count guard. Only do this on a test environment. On production, run `reset.php` first if you need to disable binary.

### Q9: Does the `binary_enabled` toggle affect admin top-ups or e-wallet transfers?

**A:** No. Admin top-ups and member-to-member transfers are completely independent of the binary system.

### Q10: What if a member tries to access `?page=genealogy&view=binary` directly via URL while binary is disabled?

**A:** The `MemberController::genealogy()` method detects this and redirects to `?page=genealogy&view=referral` automatically.

### Q11: Why can't I disable binary when members already exist?

**A:** Disabling binary on a system with existing members would leave orphaned binary tree data, inconsistent commission records, and potential accounting discrepancies. The guard ensures binary is only disabled on a **clean system** (admin-only state), so when re-enabled later, the tree starts fresh from a known state.

**Safe workflow to disable binary on a live system:**
1. Export/backup all data
2. Run `reset.php` (clears all member accounts, commissions, e-wallet history)
3. Disable binary in Admin → Settings
4. System is now in "binary-disabled, admin-only" state
5. New registrations will not have binary placement
