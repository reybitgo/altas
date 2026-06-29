# Repeat Purchase (Binary + Unilevel) — QA Test Guide for Beginners

> **Target audience:** A QA tester who knows how to open a browser and log in, but has no knowledge of the codebase.
> **Test environment:** `http://localhost/altas/`

---

## Table of Contents

1. [Pre-test Setup](#1-pre-test-setup)
2. [Admin: Enable Binary Repeat + Unilevel Features](#2-admin-enable-binary-repeat--unilevel-features)
3. [Admin: Configure Unilevel Levels per Product](#3-admin-configure-unilevel-levels-per-product)
4. [Member: Register and Prepare Test Accounts](#4-member-register-and-prepare-test-accounts)
5. [Member: Make a Repeat Purchase (ewallet)](#5-member-make-a-repeat-purchase-ewallet)
6. [Verify: Binary PV Flows Up the Tree](#6-verify-binary-pv-flows-up-the-tree)
7. [Verify: Unilevel Product Commissions](#7-verify-unilevel-product-commissions)
8. [Verify: Binary Pairing Bonus](#8-verify-binary-pairing-bonus)
9. [UI: Binary PV and Pairing Display](#9-ui-binary-pv-and-pairing-display)
10. [UI: Product Unilevel on Earnings Page](#10-ui-product-unilevel-on-earnings-page)
11. [UI: Product Unilevel on Genealogy Page](#11-ui-product-unilevel-on-genealogy-page)
12. [UI: Dashboard — Stat Cards and Recent Activity](#12-ui-dashboard--stat-cards-and-recent-activity)
13. [Edge Cases and Regression](#13-edge-cases-and-regression)
14. [Side-by-Side Comparison Table](#14-side-by-side-comparison-table)
15. [Test Checklist Summary](#15-test-checklist-summary)

---

## 1. Pre-test Setup

### 1.1 Login as Admin

1. Open `http://localhost/altas/`
2. Login with:
   - **Username:** `admin`
   - **Password:** `Admin@1234`

### 1.2 Confirm the admin menu

You should see a sidebar. If you see a "Members" or "Admin View" link, you're logged in as admin.

### 1.3 Reset the database (optional, for a clean slate)

Visit `http://localhost/altas/reset.php`, type `RESET` and submit. This deletes all members except the admin, and preserves packages and products.

> **⚠️ Warning:** Only do this if you're OK losing all test member data. If you already have test accounts, skip this step.

---

## 2. Admin: Enable Binary Repeat + Unilevel Features

### 2.1 Go to Settings

1. In the sidebar, click **Settings** (or visit `http://localhost/altas/?page=admin_settings`)

### 2.2 Enable Binary Repeat Purchase

1. Look for the **Binary Repeat Purchase** toggle
2. Make sure it is **ON** (checked/enabled)
3. If it was OFF, turn it ON

### 2.3 Enable Unilevel Product Bonus

1. Look for the **Enable Unilevel Product Bonus** toggle
2. Make sure it is **ON** (checked/enabled)

### 2.4 Save

1. Click **Save Settings** at the bottom of the page
2. You should see a green success message: "Settings saved."

---

## 3. Admin: Configure Unilevel Levels per Product

### 3.1 Go to Products

1. In the sidebar, click **Products** (or visit `http://localhost/altas/?page=admin_products`)

### 3.2 Edit a Product

1. Find an existing product (e.g. a product with price > 0)
2. Click the **Edit** button (pencil icon) on that product
3. The product edit modal will open

### 3.3 Turn on Unilevel for the Product

1. Scroll down to **Unilevel Bonuses** section
2. Check the **Enable Unilevel Bonuses for this product** checkbox/toggle

### 3.4 Set Level Percentages

Once enabled, 10 level percentage fields appear (Level 1 through Level 10):
   - Level 1: Enter `15`
   - Level 2: Enter `5`
   - Level 3: Enter `3`
   - Level 4: Enter `2.5`
   - Levels 5–10: Enter `0`

> **Test data tip:** These percentages mean: Level 1 gets 15% of the product PV, Level 2 gets 5%, etc.

### 3.5 Save the Product

1. Click **Save Product** (or **Update Product**) at the bottom of the modal
2. You should see a success message

### 3.6 Verify the saved percentages

1. Click **Edit** on the same product again
2. Verify the Unilevel Bonuses toggle is still **checked**
3. Verify the level fields still show the values you entered
4. Close the modal without saving

---

## 4. Member: Register and Prepare Test Accounts

### 4.1 What we need

To test both Binary Repeat PV and Unilevel Product commissions **side-by-side**, we need **5 members** in a specific sponsor + binary structure:

```
Binary Tree (how PV flows):      Sponsor Chain (how unilevel flows):
                                    Admin
       Admin (root)                  │
       /        \                    A
      Ø         A                    │
               / \                   B
              B   C                  │
                  |                  C
                  D                  │
                  |                  D
                  E
```

- **Member A** — sponsored by admin, binary position: admin's **right**
- **Member B** — sponsored by A, binary position: A's **left**
- **Member C** — sponsored by B, binary position: A's **right** (B's right)
- **Member D** — sponsored by C, binary position: C's **left**
- **Member E** — sponsored by D (optional, for deeper chain)

> **Why this structure?** The binary tree uses breadth-first filling (left before right). By placing A on admin's right, B on A's left, and C on A's right, we create a structure where:
> - D buying on C's left sends binary PV up: D's left ← C's right ← A's left ← admin
> - B buying on A's left sends binary PV: B's left ← A's left
> - This creates **both** left and right PV on A, triggering a **pairing bonus**

### 4.2 Get your referral link

1. **Log out** of admin if you're still logged in
2. Login as an **existing regular member** (or register a new one)
   - If registering: Go to the front page, click Register, fill in details
3. Once logged in as Member A, go to **Referral Network** in the sidebar
4. Copy your **referral link** from the input field at the top

### 4.3 Register Member B

1. Open a **private/incognito browser window**
2. Paste the referral link (from Member A)
3. Fill in the registration form:
   - Username: `rp_b`
   - Password: `Test@1234`
   - Confirm password: `Test@1234`
   - Other fields: fill as needed
4. Submit the registration
5. **Activate** rp_b's account if needed

### 4.4 Register Member C

1. From Member B's account, get their referral link
2. Open a **third private browser window**
3. Register using Member B's referral link:
   - Username: `rp_c`
   - Password: `Test@1234`
4. Activate the account

### 4.5 Register Member D

1. From Member C's account, get their referral link
2. Open a **fourth private browser window**
3. Register using Member C's referral link:
   - Username: `rp_d`
   - Password: `Test@1234`
4. Activate the account

### 4.6 Register Member E (optional)

1. From Member D's account, get their referral link
2. Open a **fifth private browser window**
3. Register using Member D's referral link:
   - Username: `rp_e`
   - Password: `Test@1234`
4. Activate the account

### 4.7 Verify the sponsor chain

1. Login as **Member A** in your main browser
2. Go to **Referral Network**
3. You should see:
   - Level 1: rp_b
   - Level 2: rp_c
   - Level 3: rp_d
   - Level 4: rp_e (if created)

### 4.8 Verify binary placement

1. Login as **admin**
2. Go to **Binary Tree** (or visit Binary Tree view while logged in as Member A)
3. You should see:
   - Admin's right → rp_a
   - rp_a's left → rp_b
   - rp_a's right → rp_c
   - rp_c's left → rp_d
   - rp_d's left → rp_e (if created)

> If binary placements are wrong, you may need to adjust by having members re-register with correct binary positions, or use the admin panel to move members.

---

## 5. Member: Make a Repeat Purchase (ewallet)

### 5.1 Fund Member D's e-wallet

Member D needs e-wallet balance to make a purchase:

1. **Login as admin**
2. Go to **Members** page
3. Find **rp_d** and click to view their profile
4. Add at least 500 to rp_d's e-wallet using the "Credit" button

### 5.2 Member D buys a product

1. **Login as rp_d** (Member D)
2. Go to **Repeat Purchases** in the sidebar
3. Click **Add to Cart** on a product that has Unilevel Bonuses enabled
4. Click **View Cart** or go to the cart page
5. Set quantity to **1**
6. Click **Checkout**
7. Select **E-Wallet** as payment method
8. Click **Place Order**
9. You should see a success message (order auto-approved with e-wallet)

### 5.3 Record the product details

Write down the following for verification later:
- Product name: ____________
- Product price: ____________
- Product PV: ____________ (look at your order confirmation or check the product in admin)

---

## 6. Verify: Binary PV Flows Up the Tree

When D buys a product, binary PV flows:

```
D (buyer) → D.left_pv increases
    ↓
C (parent) → C.right_pv increases (D is on C's left, PV flows opposite side)
    ↓
A (C's parent) → A.left_pv increases
    ↓
Admin (A's parent) → Admin.right_pv increases
```

Additionally, B's PV is **unchanged** because B is on A's other leg.

### 6.1 Check binary PV amounts on Binary Tree

1. **Login as Member A** (not admin)
2. Go to **Binary Tree** tab in the genealogy view
3. Hover over or examine each member's node. You should see:
   - **Member D (rp_d):** `Left PV` should be > 0
   - **Member C (rp_c):** `Right PV` should be > 0
   - **Member A (rp_a):** `Left PV` should be > 0
   - **Member B (rp_b):** `Left PV` / `Right PV` should be 0 or unchanged
4. Verify the amounts make sense: the PV value should match the product's effective PV

> **What's "effective PV"?** It's `product_pv × (pv_value / 100) × quantity`. If your product has product_pv=2.00 and pv_value=100%, the effective PV is 2.00 per item. If the product's `binary_pv_pct` is 100% (the default), then 100% of the product PV flows up the binary tree.

### 6.2 Check My Pairs page for pair volume

1. While logged in as **Member A**, go to **My Pairs**
2. Look at the **Left PV** and **Right PV** columns
3. You should see the PV from D's purchase reflected in Member A's pair volume

---

## 7. Verify: Unilevel Product Commissions

When D buys a product, the sponsor chain walk gives commissions:

| Upline | Role | Level | % | Amount (with rate=1000) |
|--------|------|-------|---|------------------------|
| rp_c | D's sponsor | Level 1 | 15% | ₱7,500 |
| rp_b | C's sponsor | Level 2 | 5% | ₱2,500 |
| rp_a | B's sponsor | Level 3 | 3% | ₱1,500 |
| admin | A's sponsor | Level 4 | 2.5% | ₱1,250 |

**Formula:** `effPV × (pct / 100) × pv_per_peso_rate`

Example with effPV=50, rate=1000:
- C (L1, 15%): 50 × 0.15 × 1000 = ₱7,500
- B (L2, 5%): 50 × 0.05 × 1000 = ₱2,500
- A (L3, 3%): 50 × 0.03 × 1000 = ₱1,500
- admin (L4, 2.5%): 50 × 0.025 × 1000 = ₱1,250

### 7.1 Check Member C's commissions (Level 1)

1. **Login as rp_c**
2. Go to **Earnings**
3. Look at the stat cards at the top. You should see a **Product Unilevel** card with an amount > 0
4. Click the **Prod Unilevel** filter tab
5. You should see a commission row with type "Prod Unilevel Lvl 1" from @rp_d
6. The amount should match the product's effPV × 15% × pv_per_peso_rate

### 7.2 Check Member B's commissions (Level 2)

1. **Login as rp_b**
2. Go to **Earnings**
3. Click the **Prod Unilevel** filter tab
4. You should see a commission row with type "Prod Unilevel Lvl 2" from @rp_d
5. The amount should be smaller than C's (5% vs 15%)

### 7.3 Check Member A's commissions (Level 3)

1. **Login as rp_a**
2. Go to **Earnings**
3. Click the **Prod Unilevel** filter tab
4. You should see "Prod Unilevel Lvl 3" from @rp_d
5. The amount should be smaller than B's (3% vs 5%)

### 7.4 Check E-Wallet balances

1. While logged in as each member (A, B, C), check the dashboard
2. The **E-Wallet Balance** should have increased by the commission amount
3. Admin's e-wallet should also show the Level 4 commission

---

## 8. Verify: Binary Pairing Bonus

To see a **pairing bonus**, we need both left and right PV on the same ancestor.

Currently (after D's purchase), Member A has:
- Left PV: > 0 (from D's purchase flowing up through C → A's left)
- Right PV: 0 (nothing on the right side yet)

We need to trigger a purchase on A's **right** side too. Let's do that.

### 8.1 Fund Member B's e-wallet

1. **Login as admin**
2. Go to **Members**
3. Find **rp_b** and add at least 500 to their e-wallet

### 8.2 Member B buys a product (on the Left leg)

1. **Login as rp_b**
2. Go to **Repeat Purchases**
3. Add the **same product** to cart
4. **Important:** On the checkout page, set binary position to **Left** (or accept the default "Left")
5. Complete the purchase with e-wallet

### 8.3 Verify Binary PV after B's purchase

After B buys, the binary PV flow looks like this:

```
B (buyer) → B.left_pv increases
    ↓
A (parent) → A.left_pv increases AGAIN (now A has even more left PV)

A now has: Left PV > 0  (from D + B), Right PV > 0 (from D via C)
```

### 8.4 Check for Pairing Bonus

A pairing bonus is triggered when an ancestor has BOTH left_pv AND right_pv > 0.

1. **Login as Member A**
2. Go to **Earnings**
3. Click the **Pairing** filter tab
4. You should see a commission row with type "Pairing Bonus"
5. The amount depends on how many pairs were created:
   - 1 pair = the lesser of (left PV / pair_value) vs (right PV / pair_value)
   - Pair value is configured in the package settings

> **Example:** If A's left PV = 4 and right PV = 2, and pair_value = 2, then 1 pair is created (min(4/2, 2/2) = min(2, 1) = 1). The pairing bonus would be 1 × pair_bonus_rate × pv_per_peso_rate.

### 8.5 Check admin's Pairing Bonus too

1. **Login as admin**
2. Go to **Earnings** or check the binary diagram
3. Admin should also have binary PV (right PV from D's purchase)
4. If admin also has both left and right PV, they may have a pairing bonus too

---

## 9. UI: Binary PV and Pairing Display

### 9.1 Member Dashboard

1. Login as **Member A**
2. Go to **Dashboard**
3. Look at the stat cards:
   - **Binary Left PV** should show a positive number
   - **Binary Right PV** should show a positive number
   - **My Pairs** stat card should show the number of pairs created

### 9.2 My Pairs Page

1. Go to **My Pairs** in the sidebar
2. Verify the table shows:
   - Date columns for left/right PV accumulation
   - Pair count
   - Pairing bonus earned

### 9.3 Binary Tree View

1. Go to **Binary Tree** tab in Genealogy
2. Verify:
   - Each member node shows their Left PV and Right PV
   - The D3 tree visualization renders correctly
   - Hovering over a node shows PV details

### 9.4 Earnings — Pairing Tab

1. Go to **Earnings**
2. Click the **Pairing** filter tab
3. Verify only pairing commissions are shown
4. Each row should show:
   - Date
   - Type: Pairing
   - Description (text explaining the pair)
   - Amount
   - Status: Credited

---

## 10. UI: Product Unilevel on Earnings Page

### 10.1 Stat Card

1. Login as **Member A**
2. Go to **Earnings**
3. Verify the **Product Unilevel** stat card exists and shows a positive amount

### 10.2 Filter Tab

1. Click **Prod Unilevel** filter tab
2. Table should show ONLY unilevel_product commission rows
3. Click **All** to show all types again

### 10.3 History Rows

1. With filter set to **All**, find rows with type "Prod Unilevel Lvl X"
2. Each row should show:
   - Date
   - Type (Prod Unilevel Lvl X)
   - Description
   - From (@rp_d — the buyer)
   - Amount (positive)
   - Cap Impact (— if not capped)
   - Status (Credited)

---

## 11. UI: Product Unilevel on Genealogy Page

### 11.1 Navigate to Genealogy

1. Login as **Member A**
2. Go to **Unilevel Network** in the sidebar

### 11.2 Verify the Tab

1. You should see tabs for:
   - 🌳 Binary Tree
   - 👥 Referral Network
   - 📦 Product Unilevel
2. Click **📦 Product Unilevel**

### 11.3 Verify the Tree Content

1. The card title should say: **Product Unilevel Tree (10 Levels)**
2. Collapsible level sections:
   - **Level 1** should show rp_b
   - **Level 2** should show rp_c
   - **Level 3** should show rp_d
   - **Level 4** should show rp_e (if created)
3. Each member row displays:
   - Username
   - Package name
   - Prod PV (numerical value)
   - Peso equivalent
   - Status badge

---

## 12. UI: Dashboard — Stat Cards and Recent Activity

### 12.1 Stat Cards

1. Login as **Member A**
2. Go to **Dashboard**
3. Verify stat cards exist for:
   - **Product Unilevel** — shows total unilevel commissions
   - **Pairing Bonus** — shows total pairing commissions (if any)
   - **Binary Left PV** / **Binary Right PV** — shows PV values

### 12.2 Recent Activity

1. Scroll down to **Recent Activity**
2. Look for entries showing:
   - 📦 icon with "Product Unilevel — Lvl X"
   - Pairing bonus entries
3. Verify amounts are green (+ positive)

---

## 13. Edge Cases and Regression

### 13.1 Disable Binary Repeat Feature

1. **Login as admin**
2. Go to **Settings**
3. Turn **OFF** Binary Repeat Purchase
4. Save
5. Login as a member and make a repeat purchase
6. Verify:
   - Binary PV from repeat purchases does NOT flow up the tree
   - No pairing bonuses from repeat purchases
7. Turn it **ON** again

### 13.2 Disable Unilevel Product Feature

1. **Login as admin**
2. Go to **Settings**
3. Turn **OFF** Enable Unilevel Product Bonus
4. Save
5. Login as a member:
   - Product Unilevel stat card is **gone** from dashboard
   - Prod Unilevel filter tab is **gone** from earnings
   - Unilevel Network link is **gone** from sidebar
6. Turn it **ON** again

### 13.3 Product with No Unilevel Levels (Binary only)

1. **Login as admin**
2. Edit a product
3. Turn **OFF** Unilevel Bonuses (or set all levels to 0)
4. Save
5. Login as a member and purchase this product
6. Verify:
   - ✅ Binary PV still flows up the tree (left/right PV increases normally)
   - ❌ No unilevel commissions are created
   - ✅ Pairing bonus may still trigger from binary PV

> This confirms Binary Repeat and Unilevel Product are independent features.

### 13.4 No Downline

1. Login as a brand-new member with no downline
2. Go to **Unilevel Network**
3. You should see: "No members in your product unilevel tree yet."
4. Check **Binary Tree** — it should show only you

### 13.5 PV Gate Enforcement (Unilevel)

1. **Login as admin**
2. Go to **Packages**
3. Edit the active package
4. Set **Personal PV Requirement** to a value higher than Member B's current personal_pv (e.g. 100)
5. Save
6. Login as Member D and make another purchase
7. Login as Member B:
   - B should NOT receive a unilevel commission (fails PV gate)
   - The system skips B and the next eligible upline (A) gets the commission at the same level
8. Restore Personal PV Requirement to 0

### 13.6 Zero Percent Levels

1. **Login as admin**
2. Edit a product
3. Set Level 1 = 0, Level 2 = 10, all others = 0
4. Save
5. Make a purchase from a downline member:
   - Level 1 should get nothing (0%)
   - Level 2 should get the commission

### 13.7 Binary Tree Still Works

1. Login as Member A
2. Go to **Binary Tree** tab
3. The D3 tree visualization should still load and render correctly
4. It should show all members with correct binary placement

### 13.8 Referral Network Still Works

1. Login as Member A
2. Go to **Referral Network**
3. Verify it still shows all downline members
4. This confirms no regression to the referral system

---

## 14. Side-by-Side Comparison Table

Here's a quick reference showing which commission types are triggered by a repeat purchase:

| Commission Type | Trigger | Tree Used | Enabled By | Verified in Test |
|---|---|---|---|---|
| **Pairing Bonus** | Binary PV creates left+right pair on an ancestor | Binary Tree | `binary_repeat_enabled` | Section 8 |
| **Unilevel Product** | Sponsor chain walk (10 levels) | Sponsor Chain | `unilevel_product_enabled` | Section 7 |
| **Direct Referral** | Initial registration only | Sponsor Chain | N/A (registration) | Not tested here |
| **Indirect Referral** | Initial registration only | Sponsor Chain | N/A (registration) | Not tested here |

### What happens when a member buys a product?

```
┌───────────────────────────────────────────────────┐
│              Member D Buys Product                 │
├───────────────────────────────────────────────────┤
│                                                     │
│  1. D gets Personal PV                              │
│     └─ PV = product_pv × (pv_value/100) × qty       │
│                                                     │
│  2. Binary PV flows up binary tree                  │
│     └─ D.left_pv += PV                              │
│     └─ C.right_pv += PV (opposite side)            │
│     └─ A.left_pv += PV                              │
│     └─ Admin.right_pv += PV                         │
│                                                     │
│  3. If ancestor has both L and R PV ≥ pair_value:   │
│     └─ Pairing Bonus credited to ancestor           │
│                                                     │
│  4. Unilevel sponsor chain walk:                    │
│     └─ L1: C gets 15% × PV × rate                  │
│     └─ L2: B gets 5% × PV × rate                   │
│     └─ L3: A gets 3% × PV × rate                   │
│     └─ L4: admin gets 2.5% × PV × rate             │
└───────────────────────────────────────────────────┘
```

---

## 15. Test Checklist Summary

Copy this checklist and check off items as you test:

### Admin Setup
- [ ] Binary Repeat Purchase is ENABLED in Settings
- [ ] Unilevel Product Bonus is ENABLED in Settings
- [ ] At least one product has Unilevel Bonuses turned ON
- [ ] Level percentages are set (15, 5, 3, 2.5, 0...)
- [ ] Saving a product preserves the unilevel settings

### Member Accounts (5 members)
- [ ] rp_a (sponsored by admin, binary: admin's right)
- [ ] rp_b (sponsored by A, binary: A's left)
- [ ] rp_c (sponsored by B, binary: A's right)
- [ ] rp_d (sponsored by C, binary: C's left)
- [ ] rp_e (optional, sponsored by D, binary: D's left)

### Binary PV Flow (Member D buys)
- [ ] D's left_pv increased
- [ ] C's right_pv increased
- [ ] A's left_pv increased
- [ ] Admin's right_pv increased
- [ ] B's PV unchanged

### Unilevel Commissions (Member D buys)
- [ ] C (L1, 15%) receives unilevel commission
- [ ] B (L2, 5%) receives unilevel commission
- [ ] A (L3, 3%) receives unilevel commission
- [ ] admin (L4, 2.5%) receives unilevel commission
- [ ] D (buyer) receives no unilevel commission
- [ ] Amounts match the configured percentages
- [ ] E-wallet balances reflect the commissions

### Binary Pairing Bonus (Member B buys on left)
- [ ] A's left_pv increased further
- [ ] A now has both left_pv AND right_pv > 0
- [ ] A receives Pairing Bonus
- [ ] Pairing Bonus amount is correct

### UI — Binary
- [ ] Binary Tree shows correct PV values per node
- [ ] My Pairs page shows pair count and bonus
- [ ] Pairing filter on Earnings works

### UI — Product Unilevel
- [ ] Product Unilevel stat card exists on Dashboard
- [ ] Product Unilevel stat card exists on Earnings
- [ ] Prod Unilevel filter tab exists and filters correctly
- [ ] History rows show correct type, from, amount
- [ ] Unilevel Network tab in genealogy shows correct tree
- [ ] Level sections collapse/expand

### Sidebar
- [ ] "Unilevel Network" link appears after "Referral Network"

### Edge Cases
- [ ] Disabling Binary Repeat stops binary PV flow
- [ ] Disabling Unilevel Product hides all UI elements
- [ ] Product with no unilevel levels → binary works, unilevel skipped
- [ ] New member with no downline sees empty state
- [ ] PV Gate correctly skips ineligible uplines
- [ ] Zero percent levels correctly skip that level
- [ ] Binary Tree still works (no regression)
- [ ] Referral Network still works (no regression)
