# QA Test Guide: Disable Indirect Referral Toggle

> **What is this feature?**
> The admin can now turn **Indirect Referral (Unilevel) Bonuses** ON or OFF from System Settings.
> - **ON (default):** Members earn unilevel bonuses up to 10 levels. All UI is visible.
> - **OFF:** No new unilevel bonuses are paid. All indirect referral UI is hidden from members. Past earnings remain visible in history.

---

## Table of Contents
1. [Before You Start](#before-you-start)
2. [How to Find the Toggle](#how-to-find-the-toggle)
3. [Test A: Toggle ON — Default Behavior](#test-a-toggle-on--default-behavior)
4. [Test B: Toggle OFF — Admin Perspective](#test-b-toggle-off--admin-perspective)
5. [Test C: Toggle OFF — Member Perspective](#test-c-toggle-off--member-perspective)
6. [Test D: Toggle OFF — Registration Impact](#test-d-toggle-off--registration-impact)
7. [Test E: Re-enable Toggle](#test-e-re-enable-toggle)
8. [How to Verify in the Database](#how-to-verify-in-the-database)
9. [Quick Reference](#quick-reference)

---

## Before You Start

### What You Need
- Access to the AltasFarm website
- An **admin account** (`admin` / `admin123`) — to flip the toggle
- A **member account** (`test1` / `password123`) — to check member UI
- A second **member account** with e-wallet balance (`test5` with ₱15,000+) — to register downlines

### Quick Setup Check
1. Log in as **admin**
2. Go to **System Settings** (sidebar → bottom section)
3. Scroll to **📋 Compensation Plan Defaults**
4. You should see a toggle: **"Enable Indirect Referral (Unilevel) Bonuses"**
5. It should be **ON** (blue / checked) by default

> ⚠️ **Important:** After each test, check the toggle state. Some tests require it to be ON, others OFF.

---

## How to Find the Toggle

### Step-by-Step (Admin)
1. Log in as `admin`
2. Look at the left sidebar
3. Click **System Settings** (gear icon, near the bottom)
4. On the settings page, scroll down to the section titled **"📋 Compensation Plan Defaults"**
5. The toggle is the first item in that section:
   > ☑️ **Enable Indirect Referral (Unilevel) Bonuses**
   >
   > *When disabled, no unilevel bonuses are paid and all indirect referral UI is hidden from members.*

---

## Test A: Toggle ON — Default Behavior

> **Goal:** Confirm that when the toggle is ON (default), everything works exactly as before.

### Steps (Admin)
1. Log in as **admin**
2. Go to **System Settings**
3. Find the toggle **"Enable Indirect Referral (Unilevel) Bonuses"**
4. **Make sure it is ON** (checked / blue). If it's off, click it to turn it on.
5. Click **💾 Save Settings** at the bottom of the form
6. You should see a green success message: *"Settings saved."*

### Steps (Member)
1. Log in as **test1** (or any member)
2. Go to the **Dashboard**
3. Look at the **KPI Cards** row (the 4 stat cards)

### Expected Result — Dashboard
- [ ] You see **4 cards**: E-Wallet Balance, Pairing Earnings, Direct Referral, **Indirect Referral**
- [ ] The **Indirect Referral** card shows an amount (may be ₱0.00 if member hasn't earned any yet)
- [ ] The card has a 🔗 icon and purple accent

### Steps (Member)
1. Go to **Earnings** (sidebar or click "View all" on Dashboard)
2. Look at the **filter tabs** at the top of the page

### Expected Result — Earnings Page
- [ ] You see **5 stat cards**: Total Earned, Pairing Bonuses, Direct Referral, **Indirect Referral**, DFI
- [ ] The filter tabs show: **All, 🤝 Pairing, 👥 Direct, 🔗 Indirect, 📅 DFI**
- [ ] The **🔗 Indirect** tab is clickable

### Steps (Member)
1. Go to **Genealogy** (sidebar)
2. If you see the binary tree, look for a **"👥 Referral Network (10 Levels)"** card below it

### Expected Result — Genealogy Page
- [ ] The **Referral Network** section is **visible**
- [ ] It shows "Level 1", "Level 2", etc. (or "You haven't referred anyone yet" if empty)

### Steps (Member)
1. Go to **Cap Status** (sidebar or click the Lifetime Income Cap widget)
2. Scroll down to the **Lifetime Cap Breakdown** table

### Expected Result — Cap Status Page
- [ ] The breakdown table shows rows for Pairing, Direct Referral, **Indirect Referral**, Daily Fixed Income
- [ ] If the member has earned indirect bonuses, the **🔗 Indirect Referrals** row appears in the timeline too

### Steps (Admin)
1. Go to **Packages** (sidebar)
2. Click **➕ Create Package** (or edit any existing package)
3. Scroll down the form

### Expected Result — Package Settings
- [ ] You see the **"🔗 Indirect Referral Bonuses (10 Levels)"** section
- [ ] It has 10 input boxes: Level 1, Level 2, … Level 10
- [ ] You can type values into them

### Pass / Fail
- [ ] **PASS** — All indirect UI is visible and functional
- [ ] **FAIL** — Any indirect element is missing while toggle is ON

---

## Test B: Toggle OFF — Admin Perspective

> **Goal:** Confirm that when the admin turns the toggle OFF, the indirect configuration is hidden from package settings.

### Steps
1. Log in as **admin**
2. Go to **System Settings**
3. Find **"Enable Indirect Referral (Unilevel) Bonuses"**
4. **Click the toggle to turn it OFF** (uncheck it)
5. Click **💾 Save Settings**
6. Green success message appears

### Check: Package Settings
1. Go to **Packages**
2. Click **➕ Create Package**
3. Scroll through the form

### Expected Result
- [ ] The **"🔗 Indirect Referral Bonuses (10 Levels)"** section is **completely gone**
- [ ] You do NOT see Level 1–10 input boxes
- [ ] The form ends after Status, Direct Ref Bonus, etc.
- [ ] You can still create/save the package normally

### Check: Admin User View
1. Go to **Members** (sidebar)
2. Click on any member to view their detail page
3. Click the **💰 Commissions** tab
4. If the member has past indirect commissions, look at the Type column

### Expected Result
- [ ] Past indirect commissions still appear in the table
- [ ] They show as `🔗 Indirect (disabled)` instead of `🔗 Indirect Lvl X`

### Pass / Fail
- [ ] **PASS** — Package settings hide indirect levels; admin views show "(disabled)" label
- [ ] **FAIL** — Indirect levels still visible in package form, or past data is hidden

---

## Test C: Toggle OFF — Member Perspective

> **Goal:** Confirm that members no longer see any indirect referral UI.

### Prerequisites
- Toggle is **OFF** (from Test B)

### Steps
1. Log in as **test1** (or any member)
2. Go to the **Dashboard**

### Expected Result — Dashboard
- [ ] The KPI cards row shows only **3 cards**: E-Wallet Balance, Pairing Earnings, Direct Referral
- [ ] The **Indirect Referral** card is **completely gone**
- [ ] The layout still looks good (no broken spacing)

### Steps
1. Go to **Earnings**

### Expected Result — Earnings Page
- [ ] The stat cards row shows only: Total Earned, Pairing Bonuses, Direct Referral, DFI
- [ ] The **Indirect Referral** stat card is **gone**
- [ ] The filter tabs show: **All, 🤝 Pairing, 👥 Direct, 📅 DFI**
- [ ] The **🔗 Indirect** tab is **gone**
- [ ] If the member has past indirect earnings in history, they still appear in the table but without the "🔗 Indirect Lvl X" label

### Steps
1. Go to **Genealogy**

### Expected Result — Genealogy Page
- [ ] The **"👥 Referral Network (10 Levels)"** section is **completely gone**
- [ ] Only the **Binary Tree** section remains
- [ ] No empty placeholder or error message where the referral network used to be

### Steps
1. Go to **Cap Status**

### Expected Result — Cap Status Page
- [ ] The Lifetime Cap Breakdown table shows only: Pairing, Direct Referral, Daily Fixed Income
- [ ] The **Indirect Referral** row is **gone**
- [ ] The timeline widget does NOT show the "🔗 Indirect Referrals" dot

### Pass / Fail
- [ ] **PASS** — All indirect UI is cleanly hidden from every member page
- [ ] **FAIL** — Any indirect element still appears on any member page

---

## Test D: Toggle OFF — Registration Impact

> **Goal:** Confirm that when a new member registers while the toggle is OFF, no indirect referral commissions are paid.

### Prerequisites
- Toggle is **OFF**
- You have a member with enough e-wallet balance to register a downline (e.g., `test5` with ₱15,000+)

### Before You Register
1. Log in as the payer member (e.g., `test5`)
2. Go to **Dashboard** and note down the **E-Wallet Balance**
3. (Optional) As admin, note down the system admin e-wallet balance too

### Steps
1. Log in as `test5`
2. Click **➕ Register Member**
3. Choose either **🎫 Code** or **💳 E-Wallet** payment
4. Fill in the registration form:
   - Username: `qa_no_indirect_01`
   - Password: `password123`
   - Sponsor: `test5`
   - Upline: any member with a free slot
   - Position: Left or Right (whichever is free)
5. Complete the registration

### Immediately After Registration
1. Go to **Dashboard**
2. Check your **E-Wallet Balance**

### Expected Result
- [ ] Your balance decreased by the **entry fee only** (e.g., ₱10,000 for Starter)
- [ ] Your balance did **NOT** decrease by any indirect referral amounts
- [ ] No new "Unilevel Level X" entries appear in your E-Wallet Ledger

### Verify as Admin
1. Log in as **admin**
2. Go to **Members** → find the new user `qa_no_indirect_01`
3. Click to view their detail page
4. Go to the **💰 Commissions** tab

### Expected Result
- [ ] You see **Direct Referral** and **Pairing** commissions
- [ ] You do **NOT** see any **Indirect Referral** commissions
- [ ] The commission count is lower than when toggle is ON

### Verify in E-Wallet Monitor (Admin)
1. Go to **E-Wallet Monitor**
2. Click the **Transfers** tab
3. Look for any "Unilevel" entries for the sponsor/upline members

### Expected Result
- [ ] No new "Unilevel" entries exist for this registration

### Pass / Fail
- [ ] **PASS** — Registration succeeds, no indirect commissions paid, no indirect ledger entries
- [ ] **FAIL** — Indirect commissions were still paid, or ledger shows unilevel entries

---

## Test E: Re-enable Toggle

> **Goal:** Confirm that turning the toggle back ON restores everything to normal.

### Steps
1. Log in as **admin**
2. Go to **System Settings**
3. Turn the toggle **back ON** (check it)
4. Save settings
5. Log in as `test5`
6. Register another member: `qa_reenabled_01`

### Expected Result
- [ ] The new registration **does** create indirect referral commissions
- [ ] The sponsor's e-wallet balance decreases by entry fee **plus** indirect amounts are credited to uplines
- [ ] Member UI shows all indirect elements again (Dashboard 4 cards, Earnings tab, Genealogy network, Cap Status row)
- [ ] Admin package settings show the 10 Level inputs again

### Pass / Fail
- [ ] **PASS** — Re-enabling restores full indirect functionality
- [ ] **FAIL** — Indirect bonuses don't resume after re-enabling

---

## How to Verify in the Database

If you have phpMyAdmin or MySQL access, run these queries to double-check:

### Check the toggle state
```sql
SELECT value FROM settings WHERE key_name = 'indirect_referral_enabled';
```
- `1` = enabled (ON)
- `0` = disabled (OFF)

### Check commissions for a specific registration
```sql
-- Replace 999 with the new user's ID
SELECT type, amount, level, description
FROM commissions
WHERE source_user_id = 999
ORDER BY type;
```

**When toggle is ON:**
- You should see rows with `type = 'indirect_referral'`

**When toggle is OFF:**
- You should see `pairing` and `direct_referral` rows
- You should see **zero** `indirect_referral` rows

### Check e-wallet ledger for unilevel entries
```sql
SELECT user_id, type, amount, note, created_at
FROM ewallet_ledger
WHERE note LIKE '%Unilevel%'
ORDER BY created_at DESC
LIMIT 10;
```

**When toggle is OFF after a new registration:**
- No new rows should appear

---

## Quick Reference

### Test Account Cheat Sheet
| Account | Username | Password | Role | Use For |
|---------|----------|----------|------|---------|
| Admin | `admin` | `admin123` | admin | Flip the toggle |
| Member A | `test1` | `password123` | member | Check member UI |
| Member B | `test5` | `password123` | member | Register downlines (needs ₱15,000+ balance) |

### Toggle Location
**Admin → System Settings → 📋 Compensation Plan Defaults → Enable Indirect Referral (Unilevel) Bonuses**

### What Should Change When Toggle is OFF
| Page | Element | Hidden? |
|------|---------|---------|
| Member Dashboard | Indirect Referral KPI card | ✅ Yes |
| Member Dashboard | Indirect activity in Recent Activity | ✅ Yes |
| Member Earnings | Indirect Referral stat card | ✅ Yes |
| Member Earnings | 🔗 Indirect filter tab | ✅ Yes |
| Member Genealogy | 👥 Referral Network section | ✅ Yes |
| Member Cap Status | Indirect Referrals timeline dot | ✅ Yes |
| Member Cap Status | Indirect Referral breakdown row | ✅ Yes |
| Admin Packages | Level 1–10 input grid | ✅ Yes |
| Commission Engine | New indirect payouts | ✅ Yes |

### What Should NOT Change
| Page | Element | Still Visible? |
|------|---------|----------------|
| Member Dashboard | E-Wallet, Pairing, Direct cards | ✅ Yes |
| Member Earnings | Pairing, Direct, DFI tabs | ✅ Yes |
| Member Genealogy | Binary Tree | ✅ Yes |
| Registration | New member creation | ✅ Yes |
| Commission Engine | Direct referral bonus | ✅ Yes |
| Commission Engine | Pairing bonus | ✅ Yes |
| All History | Past indirect commissions | ✅ Yes (labeled "disabled") |

---

## Reporting Bugs

If any test fails, please include:
1. **Which test** failed (e.g., "Test C: Member Dashboard")
2. **Toggle state** at the time (ON or OFF)
3. **What you expected** to see
4. **What you actually saw** (screenshot if possible)
5. **The exact page URL** (e.g., `/?page=dashboard`)

---

*Last updated: 2026-05-30*
