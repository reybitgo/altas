# Royalty Bonus — Leadership Ranks QA Test Guide

> **Target audience:** A QA tester who knows how to open a browser and log in, but has no knowledge of the codebase.
> **Test environment:** `http://localhost/altas/`
> **Time estimate:** 60–90 minutes

---

## Table of Contents

1. [Pre-test Setup](#1-pre-test-setup)
2. [Admin: Enable Royalty Bonus](#2-admin-enable-royalty-bonus)
3. [Admin: Configure Rank Requirements](#3-admin-configure-rank-requirements)
4. [Member: Register Test Accounts — Sponsor Chain](#4-member-register-test-accounts--sponsor-chain)
5. [Verify: Qualified Associate (QA) Gate](#5-verify-qualified-associate-qa-gate)
6. [Verify: Supervisor Rank + Bonus Payment](#6-verify-supervisor-rank--bonus-payment)
7. [Verify: Manager Rank (3 Supervisor Legs)](#7-verify-manager-rank-3-supervisor-legs)
8. [Verify: Director and Chairman Ranks](#8-verify-director-and-chairman-ranks)
9. [UI: Dashboard Rank Badge](#9-ui-dashboard-rank-badge)
10. [UI: Royalty Bonus Page](#10-ui-royalty-bonus-page)
11. [UI: Earnings and Recent Activity](#11-ui-earnings-and-recent-activity)
12. [Edge Cases](#12-edge-cases)
13. [Side-by-Side Comparison Table](#13-side-by-side-comparison-table)
14. [Manual Test Checklist](#14-manual-test-checklist)

---

## 1. Pre-test Setup

### 1.1 Login as Admin

1. Open `http://localhost/altas/`
2. Login with:
   - **Username:** `admin`
   - **Password:** `Admin@1234`

### 1.2 Confirm admin access

You should see an admin sidebar with Dashboard, Members, Packages, Settings, etc.

### 1.3 Reset database (optional)

Visit `http://localhost/altas/reset.php`, type `RESET` and submit. This deletes all members but preserves admin + packages + products.

> ⚠️ Skip if you already have test members you want to keep.

### 1.4 Ensure you have at least one product

1. Go to **Products** (sidebar) or `http://localhost/altas/?page=admin_products`
2. If no product exists, create one:
   - Click **Add Product**
   - Name: `Test Supplement`
   - Price: `5000`
   - Product PV: `10`
   - Status: **Active**
   - Click **Save**

---

## 2. Admin: Enable Royalty Bonus

### 2.1 Open Settings

1. Click **Settings** in the sidebar (or go to `http://localhost/altas/?page=admin_settings`)

### 2.2 Navigate to Royalty Bonus tab

1. In the settings offcanvas (right side), click the **Royalty Bonus** tab (⭐ icon)

### 2.3 Enable the feature

1. Find the **Enable Royalty Bonus** toggle
2. Make sure it is **ON** (checked)
3. If it was off, turn it ON

### 2.4 Verify QA Requirements (defaults)

- **Min Directs:** `3`
- **Personal Sales PV (OR):** `200`
- **Group Sales PV (OR):** `1000`

These are the default values. Leave them for now.

### 2.5 Verify Rank Percentages (defaults)

| Rank | Group PV % | Repeat Net % |
|------|-----------|-------------|
| Supervisor | 3% | 5% |
| Manager | 5% | 10% |
| Director | 10% | 15% |
| Chairman | 12% | 20% |

Leave these at defaults for the test.

### 2.6 Save

1. Click **Save Settings**
2. You should see a green success: "Settings saved."

---

## 3. Admin: Confirm Settings in Database (optional)

If you want to double-check, you can run these SQL queries in phpMyAdmin:

```sql
SELECT * FROM settings WHERE key_name LIKE 'royalty_%';
```

Expected: 12 rows with default values as shown above.

Also check the `users` table has the new column:

```sql
DESCRIBE users rank_royalty;
```

Expected: `rank_royalty` column exists, type `enum('qa','supervisor','manager','director','chairman')`, allows NULL.

---

## 4. Member: Register Test Accounts — Sponsor Chain

You need to build a sponsor chain that lets you test all 5 ranks. Here is the plan:

### 4.1 The Rank Ladder (visual diagram)

```
Chairman U
  └─ Director T
       └─ Manager S
            └─ Supervisor R
                 └─ QA P (has 5 QA legs among 10 directs)
```

But for simplicity, we'll build a smaller test that still exercises all ranks:

### 4.2 Test accounts needed

| Username | Role | Sponsor | Directs | Personal PV | Group PV | Expected Rank |
|----------|------|---------|---------|------------|---------|--------------|
| `richard` | Admin sponsor | admin | — | — | — | — |
| `supervisorA` | Member | richard | 10+ (5+ QA) | ≥200 OR ≥1000 | ≥1000 | Supervisor |
| `managerB` | Member | richard | 3 Supervisor legs | ≥200 OR ≥1000 | ≥1000 | Manager |
| `directorC` | Member | richard | 3 Manager legs | ≥200 OR ≥1000 | ≥1000 | Director |
| `chairmanD` | Member | richard | 3 Director legs | ≥200 OR ≥1000 | ≥1000 | Chairman |
| `direct1`–`direct10` | Member | supervisorA | 0 | 300 | 1500 | QA (legs) |
| `supLeg1`–`supLeg3` | Member | managerB | 10 (5 QA legs) | 300 | 1500 | Supervisor |
| `mgrLeg1`–`mgrLeg3` | Member | directorC | 10 (5 QA legs) | 300 | 1500 | Supervisor→Manager |
| `dirLeg1`–`dirLeg3` | Member | chairmanD | 10 (5 QA legs) | 300 | 1500 | Supervisor→Manager→Director |
| `buyer1` | Member | supervisorA | 0 | 0 | 0 | — (purchaser) |

### 4.3 Step-by-step registration

**Step 1 — Register `supervisorA`**

1. Open a new incognito/private window or log out
2. Go to `http://localhost/altas/?page=register`
3. Fill in:
   - Sponsor: `admin`
   - Binary Parent: `admin`
   - Binary Position: **Left**
   - Username: `supervisorA`
   - Password: `Test@1234`
   - Package: any available package
   - Payment: **E-Wallet** (admin balance should suffice)
4. Submit

**Step 2 — Register 10 direct referrals under `supervisorA`**

For each direct (1 through 10):

1. Go to `http://localhost/altas/?page=register`
2. Fill in:
   - Sponsor: `supervisorA`
   - Binary Parent: `supervisorA` (alternate Left/Right)
   - Username: `supdirect_1`, `supdirect_2`, ..., `supdirect_10`
   - Password: `Test@1234`
   - Package: any
   - Payment: **E-Wallet**
3. Submit

**Step 3 — Give 5 of them Personal PV ≥ 200 (make them QA)**

You need to make repeat purchases for `supdirect_1` through `supdirect_5`. For each:

1. Login as the direct member
2. Go to **Shop / Repeat Purchase**
3. Add the test product (₱5,000, 10 PV) to cart
4. Checkout with **E-Wallet**
5. Choose any binary position
6. Submit order
7. Log out and log in as **admin**
8. Go to **Repeat Purchases** in sidebar
9. Find the pending order, click **Approve**

> Only 1 purchase per direct is needed (10 PV each → personal PV ≥ 200 needs 20 purchases worth 200 PV total, or you can just set it directly via SQL for the test).

**Alternative: Direct SQL update (faster)**

Since this is a QA test and you have phpMyAdmin access, run:

```sql
UPDATE users SET personal_pv = 300, group_pv = 1500
WHERE username LIKE 'supdirect_%' AND username IN ('supdirect_1','supdirect_2','supdirect_3','supdirect_4','supdirect_5');
```

This sets 5 directs to have Personal PV ≥ 200 and Group PV ≥ 1000, making them QA-qualified legs.

> 📝 **Why 5 QA legs?** Supervisor requires 10 directs AND 5 QA legs among them. 5 of the 10 directs being QA meets this.

---

## 5. Verify: Qualified Associate (QA) Gate

### 5.1 Check `supervisorA`'s rank after registration

1. Login as `supervisorA`
2. Go to **Royalty Bonus** page (sidebar, ⭐ icon) — if you don't see it, the feature may be disabled or `supervisorA` doesn't qualify yet
3. The page should show:
   - **Current Rank:** `Qualified Associate` (🟡 badge)
   - **Directs:** 10 / 3 ✓
   - **Personal PV:** 0 / 200 ✗
   - **Group PV:** 0 / 1000 ✗

Wait — Personal PV and Group PV are 0 because no purchases have been made yet. The OR gate says: qualify if `Personal PV ≥ 200` **OR** `Group PV ≥ 1000`. Since both are 0, `supervisorA` is **NOT** QA yet!

### 5.2 Make a purchase to trigger QA + rank evaluation

1. Login as a direct (e.g., `supdirect_1`)
2. Go to **Repeat Purchases** → **Shop**
3. Add product to cart, checkout with e-wallet, choose Left position
4. Log in as **admin**, go to **Repeat Purchases**, find the order, **Approve**

### 5.3 Re-check `supervisorA`'s rank

1. Login as `supervisorA`
2. Go to **Royalty Bonus** page
3. You should now see:
   - **Current Rank:** `Supervisor` (🥉 badge) — because the purchase added Group PV to supervisorA
   - **Directs:** 10 / 10 ✓
   - **QA Legs:** 5 / 5 ✓
   - **Total Royalty Earned:** some amount (should be > 0)

---

## 6. Verify: Supervisor Rank + Bonus Payment

### 6.1 Understand the bonus calculation

When `supdirect_1` buys a ₱5,000 product with 10 PV:

**Supervisor bonus for `supervisorA`:**
- **Group bonus:** 3% × 10 PV × ₱1,000/PV = **₱300**
- **Repeat bonus:** 5% × ₱5,000 = **₱250**
- **Total:** **₱550**

### 6.2 Verify the bonus was paid

1. Login as `supervisorA`
2. Go to **Royalty Bonus** page
3. Check **Total Royalty Earned**: should show `₱550.00`
4. Scroll to **Royalty Commission History**: should show a row with the description containing "Royalty supervisor: 3% group × 10 PV + 5% repeat"

### 6.3 Verify the e-wallet was credited

1. Login as `supervisorA`
2. Go to **Dashboard**
3. Check **E-Wallet Balance**: it should have increased by ₱550 (minus any CD deductions)
4. Go to **Earnings** page
5. Find the "Royalty Bonus" entry with +₱550

### 6.4 Verify the commission record in DB (optional)

```sql
SELECT * FROM commissions WHERE type = 'royalty' ORDER BY id DESC LIMIT 5;
```

Expected:
- `user_id` = supervisorA's ID
- `type` = `royalty`
- `amount` = 550.00
- `status` = `credited`

```sql
SELECT username, rank_royalty FROM users WHERE username = 'supervisorA';
```

Expected: `rank_royalty` = `supervisor`

---

## 7. Verify: Manager Rank (3 Supervisor Legs)

### 7.1 Create 3 Supervisor legs under `managerB`

First, register `managerB` as a direct of `richard` (or whoever the top sponsor is).

Then register 3 members under `managerB`, each with 10 directs (5 QA), and make purchases to push their Group PV above QA threshold.

> **Shortcut:** Use SQL to set up the test data faster:

```sql
-- Create managerB
INSERT INTO users (username, password_hash, role, sponsor_id, status, personal_pv, group_pv)
SELECT 'managerB', '$2y$12$...', 'member', id, 'active', 500, 5000
FROM users WHERE username = 'richard';

-- Create 3 Supervisor legs under managerB
-- (For each leg: need 10 directs with 5 QA)
-- This is complex to do manually. For testing, you can directly set rank_royalty:
UPDATE users SET rank_royalty = 'supervisor' WHERE username = 'supLeg1';
UPDATE users SET rank_royalty = 'supervisor' WHERE username = 'supLeg2';
UPDATE users SET rank_royalty = 'supervisor' WHERE username = 'supLeg3';
```

### 7.2 Trigger rank re-evaluation

Make a repeat purchase by any member under `managerB`'s downline. This causes `processRepeatPurchase` to walk the sponsor chain and re-evaluate `managerB`.

### 7.3 Verify Manager rank

```sql
SELECT username, rank_royalty FROM users WHERE username = 'managerB';
```

Expected: `rank_royalty` = `manager`

### 7.4 Verify Manager bonus rates

A Manager gets:
- 5% Group PV bonus (instead of 3%)
- 10% Repeat bonus (instead of 5%)

So on a ₱5,000 purchase with 10 PV:
- Group: 5% × 10 × 1000 = ₱500
- Repeat: 10% × 5000 = ₱500
- Total: ₱1,000

---

## 8. Verify: Director and Chairman Ranks

### 8.1 Director

**Requirement:** QA + 3 Manager legs (each Manager must have 3 Supervisor legs).

**Bonus rates (default):** 10% Group + 15% Repeat.

```sql
-- Quick setup for testing (set legs directly)
UPDATE users SET rank_royalty = 'manager' WHERE username = 'mgrLeg1';
UPDATE users SET rank_royalty = 'manager' WHERE username = 'mgrLeg2';
UPDATE users SET rank_royalty = 'manager' WHERE username = 'mgrLeg3';
```

Then trigger a purchase to re-evaluate `directorC`.

Verify:
```sql
SELECT username, rank_royalty FROM users WHERE username = 'directorC';
-- Expected: 'director'
```

### 8.2 Chairman

**Requirement:** QA + 3 Director legs.

**Bonus rates (default):** 12% Group + 20% Repeat.

```sql
UPDATE users SET rank_royalty = 'director' WHERE username = 'dirLeg1';
UPDATE users SET rank_royalty = 'director' WHERE username = 'dirLeg2';
UPDATE users SET rank_royalty = 'director' WHERE username = 'dirLeg3';
```

Then trigger a purchase.

Verify:
```sql
SELECT username, rank_royalty FROM users WHERE username = 'chairmanD';
-- Expected: 'chairman'
```

### 8.3 Verify Chairman bonus on a ₱5,000 purchase (10 PV)

- Group: 12% × 10 × 1000 = ₱1,200
- Repeat: 20% × 5000 = ₱1,000
- **Total: ₱2,200**

---

## 9. UI: Dashboard Rank Badge

### 9.1 Login as a ranked member

1. Login as `supervisorA`
2. Go to **Dashboard**

### 9.2 Find the rank badge

Look near the welcome message at the top:

```
Welcome back, supervisorA! 👋
Package Name · Joined Jan 1, 2025  [🥉 Supervisor]
```

The badge should show:
- Color matches the rank (Supervisor = info blue, Manager = primary blue, Director = warning yellow, Chairman = danger red)
- Icon matches: 🥉 Supervisor, 🥈 Manager, 🥇 Director, 👑 Chairman

### 9.3 Verify the Royalty KPI card

The dashboard should show a **Royalty Bonus** stat card with:
- Icon: ⭐
- Total royalty earnings
- Clicking the "Withdraw →" link takes you to the Royalty Bonus page

> **Note:** The Royalty Bonus card only appears when the feature is enabled (`royalty_enabled = 1`).

---

## 10. UI: Royalty Bonus Page

### 10.1 Navigate

1. Click **Royalty Bonus** in the sidebar (⭐ icon)
2. You should see the dedicated page at `http://localhost/altas/?page=member_royalty`

### 10.2 Verify page content

The page has 4 sections:

**Section 1 — Current Rank Card (top)**
- Large rank icon (🥉🥈🥇👑)
- Rank name (e.g., "Supervisor")
- Badge with rank name
- If feature is disabled, shows "Royalty bonus is currently disabled by admin."

**Section 2 — Rank Requirements (middle)**
- 5 requirement cards: QA, Supervisor, Manager, Director, Chairman
- Each card shows the specific requirements for that rank
- Requirements show ✓ (met) or ✗ (not met) status

**Section 3 — Total Royalty Earned**
- Shows cumulative royalty bonus amount

**Section 4 — Royalty Commission History**
- Lists each royalty commission with:
  - ⭐ icon
  - Description (e.g., "Royalty supervisor: 3% group × 10 PV + 5% repeat")
  - Date/time
  - Amount (e.g., "+₱550.00")

### 10.3 Test with an unranked member

1. Login as a member with no rank (e.g., one of the directs)
2. Go to **Royalty Bonus** page
3. You should see:
   - Icon: ⚪
   - Label: "—" (em dash)
   - Badge: "Not ranked yet"
   - All requirements show ✗

---

## 11. UI: Earnings and Recent Activity

### 11.1 Earnings page

1. Login as `supervisorA`
2. Go to **Earnings** (sidebar, 💰)
3. Look for a "Royalty Bonus" row

### 11.2 Recent Activity on Dashboard

1. Go to **Dashboard**
2. Scroll to **Recent Activity** (bottom section)
3. Look for a ⭐ entry with "Royalty Bonus" and +₱550.00

---

## 12. Edge Cases

### 12.1 Feature toggle OFF

1. Login as **admin**
2. Go to **Settings** → **Royalty Bonus**
3. Turn **Enable Royalty Bonus** **OFF**
4. Save
5. Make a repeat purchase as any member
6. Verify: no royalty commission is created
7. Verify: the sidebar **Royalty Bonus** link disappears for members
8. Verify: the dashboard **Royalty Bonus** KPI card disappears
9. Verify: the **Rank badge** disappears from the welcome row

### 12.2 Feature toggle ON (re-enable)

1. Turn Royalty Bonus back **ON**
2. Make another purchase
3. Verify: royalty bonus is paid again

### 12.3 Cap interaction (lifetime income cap)

1. Find a member who is close to their lifetime cap (or temporarily lower the cap multiplier for testing)
2. Make a repeat purchase that would trigger a royalty bonus
3. Verify: if the royalty bonus would exceed the remaining cap, only the allowed portion is paid and the rest is blocked
4. Check the **Cap Monitor** to see the blocked amount

### 12.4 Zero PV product

1. Create a product with `product_pv = 0` but a price > 0
2. Make a repeat purchase of this product
3. Verify: no royalty bonus is processed (`totalPv <= 0` guard returns early)

### 12.5 Member does not meet QA gate

1. Register a member with 0 directs (or < 3 directs)
2. Make a repeat purchase under them
3. Verify: no royalty bonus is paid to this member (they don't meet the minimum directs requirement)

### 12.6 OR gate — Personal PV only

1. Find a member with ≥3 directs, personal PV ≥ 200, but group PV < 1000
2. Make a purchase under them
3. Verify: they qualify as QA (personal PV meets threshold even though group PV doesn't)

### 12.7 OR gate — Group PV only

1. Find a member with ≥3 directs, group PV ≥ 1000, but personal PV < 200
2. Make a purchase under them
3. Verify: they qualify as QA (group PV meets threshold even though personal PV doesn't)

### 12.8 Multiple qualifying uplines earn simultaneously

1. Register a chain: A sponsors B, B sponsors C, C sponsors D
2. A is Chairman, B is Director, C is Manager, D is Supervisor
3. D buys a product
4. Verify: A, B, C, and D all receive a royalty bonus at their respective rates

### 12.9 Direct/referral chain vs binary tree

The royalty bonus uses the **sponsor chain** (referral tree), not the binary tree. Verify:

1. Member X sponsors Y and is also X's binary parent
2. Make a purchase in Y's downline
3. Verify: bonus flows up the sponsor chain (Y → Y's sponsor → ...), not the binary tree

### 12.10 Commission ENUM check

Verify the `commissions` table accepts `type = 'royalty'`:

```sql
INSERT INTO commissions (user_id, type, amount, description, status)
VALUES (1, 'royalty', 100, 'test', 'credited');
-- Should succeed
```

Then delete the test row.

### 12.11 Rank_royalty column update

When a member's rank changes (e.g., from Supervisor to Manager), the `rank_royalty` column should reflect the new highest rank:

```sql
SELECT username, rank_royalty FROM users WHERE rank_royalty IS NOT NULL;
```

### 12.12 No duplicate payments

Make the same purchase twice (two identical orders). Verify:
- First purchase: royalty bonus paid
- Second purchase: royalty bonus paid again (each purchase is independent)
- The totals are additive (not duplicated for the same purchase)

### 12.13 Re-activation after cap

1. Cap a member out (reach lifetime cap)
2. They reactivate (cap_status returns to 'active')
3. Make a purchase under them
4. Verify: royalty bonus fires again (they are active again)

### 12.14 Zero percent rank

Set Supervisor Group % to `0` and Repeat % to `0`. Make a purchase. Verify:
- `totalBonus = 0 + 0 = 0`
- No commission is recorded (bonus ≤ 0 guard)

Reset to defaults after.

---

## 13. Side-by-Side Comparison Table

| Feature | Binary Pairing | Unilevel Product | Royalty Bonus |
|---------|---------------|-----------------|---------------|
| **Tree** | Binary tree (left/right legs) | Sponsor chain | Sponsor chain |
| **Trigger** | Package registration + repeat PV | Repeat purchase (per item) | Repeat purchase |
| **Who earns** | Ancestors (binary parents) | Upline sponsors (10 levels) | Upline with rank ≥ Supervisor |
| **Gate** | Personal PV requirement | Personal PV requirement | QA (directs + PV OR gate) |
| **Calculation** | Paired PV × package pairing% × rate | Product PV × level% × rate | Group PV% × PV × rate + Repeat% × purchase |
| **Cap** | Daily pair cap + lifetime cap | Lifetime cap only | Lifetime cap only |
| **Real-time?** | Yes | Yes | Yes |
| **Toggle** | `binary_enabled` + `binary_repeat_enabled` | `unilevel_product_enabled` | `royalty_enabled` |

### Rank Progression Diagram

```
                          ┌──────────┐
                          │  CHAIRMAN │  👑  12% group + 20% repeat
                          │  (3 Dir)  │
                          └─────┬─────┘
                                │
                          ┌─────▼─────┐
                          │  DIRECTOR  │  🥇  10% group + 15% repeat
                          │  (3 Mgr)   │
                          └─────┬─────┘
                                │
                          ┌─────▼─────┐
                          │  MANAGER   │  🥈  5% group + 10% repeat
                          │  (3 Sup)   │
                          └─────┬─────┘
                                │
                          ┌─────▼──────┐
                          │ SUPERVISOR  │  🥉  3% group + 5% repeat
                          │ (10 dir,5QA)│
                          └─────┬──────┘
                                │
                          ┌─────▼──────┐
                          │ QA (entry)  │  🟡  No payout — prerequisite only
                          │ (3 dir, OR)│
                          └────────────┘
```

---

## 14. Manual Test Checklist

### Pre-test
- [ ] Logged in as admin
- [ ] Royalty Bonus toggle is ON
- [ ] QA requirements set to defaults (3 directs, 200 personal PV, 1000 group PV)
- [ ] Rank percentages at defaults (3/5, 5/10, 10/15, 12/20)
- [ ] At least one active product exists

### Rank Achievement Tests
- [ ] Member with < 3 directs → no QA → no rank → no bonus
- [ ] Member with 3+ directs + PV ≥ threshold → QA (🟡) → no bonus (QA is entry only)
- [ ] Member with 10 directs + 5 QA legs → Supervisor (🥉) → bonus paid
- [ ] Member with 3 Supervisor legs → Manager (🥈) → higher bonus rate
- [ ] Member with 3 Manager legs → Director (🥇) → higher bonus rate
- [ ] Member with 3 Director legs → Chairman (👑) → highest bonus rate

### Bonus Amount Tests
- [ ] Supervisor: 3% × 10 PV × 1000 + 5% × 5000 = ₱550
- [ ] Manager: 5% × 10 PV × 1000 + 10% × 5000 = ₱1,000
- [ ] Director: 10% × 10 PV × 1000 + 15% × 5000 = ₱1,750
- [ ] Chairman: 12% × 10 PV × 1000 + 20% × 5000 = ₱2,200

### UI Tests
- [ ] Dashboard shows rank badge (🥉🥈🥇👑)
- [ ] Royalty Bonus page shows correct current rank
- [ ] Royalty Bonus page shows requirement progress (✓/✗)
- [ ] Royalty Bonus page shows total earned
- [ ] Royalty Bonus page shows commission history
- [ ] Unranked member sees "Not ranked yet"
- [ ] Sidebar link appears when enabled, disappears when disabled
- [ ] KPI card appears on dashboard when enabled
- [ ] Recent activity shows royalty entries

### Edge Case Tests
- [ ] Toggle OFF → no bonus, no sidebar, no badge
- [ ] Toggle ON → bonus resumes
- [ ] Group PV = 0 on a rank-qualified member → only repeat bonus paid (group bonus = 0)
- [ ] Repeat purchase amount = 0 → only group bonus paid (repeat bonus = 0)
- [ ] Both percentages = 0 → no commission recorded
- [ ] Lifetime cap reached → bonus blocked, cap record created
- [ ] Reactivated after cap → bonus resumes
- [ ] OR gate works (personal PV alone, group PV alone)
- [ ] Multiple ranked uplines all earn simultaneously
- [ ] Zero PV product → no bonus
- [ ] Zero directs → no bonus

### Commission Record Tests
- [ ] Commission type = `royalty` in DB
- [ ] Gross amount = group bonus + repeat bonus
- [ ] `cap_deduction` > 0 when cap is hit
- [ ] `source_user_id` = buyer
- [ ] `status` = `credited` for successful payments
- [ ] `status` = `flushed` for cap-blocked payments
- [ ] `rank_royalty` column updated correctly

---

> **End of test plan.** After completing all checks, reset settings to defaults and clean up test members if needed.
