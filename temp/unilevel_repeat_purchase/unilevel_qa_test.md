# Product Unilevel — QA Test Guide for Beginners

> **Target audience:** A QA tester who knows how to open a browser and log in, but has no knowledge of the codebase.
> **Test environment:** `http://localhost/altas/`

---

## Table of Contents

1. [Pre-test Setup](#1-pre-test-setup)
2. [Admin: Enable the Feature](#2-admin-enable-the-feature)
3. [Admin: Configure Unilevel Levels per Product](#3-admin-configure-unilevel-levels-per-product)
4. [Member: Register and Prepare Test Accounts](#4-member-register-and-prepare-test-accounts)
5. [Member: Make a Repeat Purchase (ewallet)](#5-member-make-a-repeat-purchase-ewallet)
6. [Member: Verify Commissions Were Paid](#6-member-verify-commissions-were-paid)
7. [UI: Product Unilevel on Earnings Page](#7-ui-product-unilevel-on-earnings-page)
8. [UI: Product Unilevel on Genealogy Page](#8-ui-product-unilevel-on-genealogy-page)
9. [UI: Dashboard — Stat Card and Recent Activity](#9-ui-dashboard--stat-card-and-recent-activity)
10. [UI: Lifetime Cap Status Page](#10-ui-lifetime-cap-status-page)
11. [UI: Admin Member View](#11-ui-admin-member-view)
12. [UI: Sidebar Navigation](#12-ui-sidebar-navigation)
13. [Edge Cases and Regression](#13-edge-cases-and-regression)
14. [Test Checklist Summary](#14-test-checklist-summary)

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

## 2. Admin: Enable the Feature

### 2.1 Go to Settings

1. In the sidebar, click **Settings** (or visit `http://localhost/altas/?page=admin_settings`)
2. Look for the **Enable Unilevel Product Bonus** toggle
3. Make sure it is **ON** (checked/enabled)
4. Click **Save Settings** at the bottom of the page

### 2.2 Verify

After saving, you should see a green success message: "Settings saved."

---

## 3. Admin: Configure Unilevel Levels per Product

### 3.1 Go to Products

1. In the sidebar, click **Products** (or visit `http://localhost/altas/?page=admin_products`)

### 3.2 Edit a Product

1. Find an existing product (e.g. a product with price > 0)
2. Click the **Edit** button (pencil icon) on that product
3. The product edit modal will open

### 3.3 Turn on Unilevel for the Product

1. In the modal, scroll down to **Unilevel Bonuses** section
2. You should see a checkbox/toggle: **Enable Unilevel Bonuses for this product**
3. **Check it** to enable

### 3.4 Set Level Percentages

Once enabled, 10 level percentage fields appear (Level 1 through Level 10):
   - Level 1: Enter `10`
   - Level 2: Enter `5`
   - Level 3: Enter `3`
   - Level 4: Enter `2`
   - Level 5: Enter `1`
   - Level 6–10: Enter `0.5`

> **Test data tip:** These percentages mean: Level 1 gets 10% of the product PV, Level 2 gets 5%, etc.

### 3.5 Save the Product

1. Click **Save Product** (or **Update Product**) at the bottom of the modal
2. You should see a success message

### 3.6 Verify the saved percentages

1. Click **Edit** on the same product again
2. The modal should still show:
   - Unilevel Bonuses toggle still **checked**
   - All 10 level fields still showing the values you entered
3. Close the modal without saving

### 3.7 Enable Unilevel for another product (optional)

Repeat steps 3.2–3.5 for a second product, using different percentages (e.g. Level 1 = 5, Level 2 = 2.5, levels 3–10 = 0).

---

## 4. Member: Register and Prepare Test Accounts

### 4.1 What we need

To test Product Unilevel, we need **at least 4 members** in a sponsor chain:

```
You (Member A)   ← logged in as the test user
  └─ Member B    ← sponsored by A
       └─ Member C   ← sponsored by B
            └─ Member D  ← sponsored by C
```

### 4.2 Get your referral link

1. **Log out** of admin if you're still logged in
2. Login as an **existing regular member** (or register a new one at the frontend)
   - If registering: Go to the front page, click Register, fill in details
3. Once logged in as Member A, go to **Referral Network** in the sidebar
4. Copy your **referral link** from the input field at the top

### 4.3 Register Member B

1. Open a **private/incognito browser window**
2. Paste the referral link
3. Fill in the registration form:
   - Username: `test_b`
   - Password: `Test@1234`
   - Confirm password: `Test@1234`
   - Other fields: fill as needed
4. Submit the registration
5. **Activate** test_b's account if needed:
   - If prompted to activate, enter a valid registration code or use e-wallet
   - If you don't have a registration code, use the admin panel to approve the activation

### 4.4 Register Member C

1. From Member B's account, get their referral link (go to their Referral Network page)
2. Open a **third private browser window**
3. Register using Member B's referral link:
   - Username: `test_c`
   - Password: `Test@1234`
4. Activate the account

### 4.5 Register Member D

1. From Member C's account, get their referral link
2. Open a **fourth private browser window**
3. Register using Member C's referral link:
   - Username: `test_d`
   - Password: `Test@1234`
4. Activate the account

### 4.6 Verify the sponsor chain

1. Login as **Member A** in your main browser
2. Go to **Referral Network** in the sidebar
3. You should see:
   - Level 1: test_b
   - Level 2: test_c
   - Level 3: test_d

---

## 5. Member: Make a Repeat Purchase (ewallet)

### 5.1 Fund Member D's e-wallet

Since Member D needs e-wallet balance to make a purchase (and trigger unilevel bonuses up the chain):

1. **Login as admin**
2. Go to **Members** page
3. Find **test_d** and click to view their profile
4. Look for the e-wallet section or use the "Credit" button to add funds
5. Add at least 500 to test_d's e-wallet

**OR** have Member D make a purchase with proof of payment (admin must approve it later).

### 5.2 Member D buys a product

1. **Login as test_d** (Member D)
2. Go to **Repeat Purchases** in the sidebar
3. Add a product (one that has Unilevel Bonuses enabled) to the cart
   - Click **Add to Cart** on the product
4. Click **View Cart** or go to the cart page
5. Set quantity to **1** (or any quantity)
6. Click **Checkout**
7. Select **E-Wallet** as payment method
8. Click **Place Order**

### 5.3 Verify order was approved

If using e-wallet, the order is approved immediately. You should see a success message.

---

## 6. Member: Verify Commissions Were Paid

### 6.1 Check Member A's commissions (the top member)

1. **Login as Member A**
2. Go to **Earnings** in the sidebar
3. Look at the stat cards at the top:
   - You should see a **Product Unilevel** stat card with an amount > 0
4. Click the **Prod Unilevel** filter tab
5. You should see commission rows with type "Prod Unilevel Lvl 1", "Prod Unilevel Lvl 2", "Prod Unilevel Lvl 3"
   - **Level 1** = you sponsored test_b, who sponsored test_c, who sponsored test_d (Member D)
   - Member D's purchase should trigger:
     - test_c gets Level 1 (10% of product PV)
     - test_b gets Level 2 (5% of product PV)
     - Member A gets Level 3 (3% of product PV)

### 6.2 Check the commission amounts

Hover over or look at the **Amount** column:
   - Level 1 amount should be larger than Level 2, which should be larger than Level 3
   - This matches the percentages you set (10%, 5%, 3%)

### 6.3 Check Member A's E-Wallet

1. While logged in as Member A, go to the dashboard
2. Check the **E-Wallet Balance** — it should have increased by the commission amounts

---

## 7. UI: Product Unilevel on Earnings Page

### 7.1 Stat Card

1. Login as **Member A**
2. Go to **Earnings**
3. Look at the top row of stat cards
4. Verify:
   - **Product Unilevel** card exists
   - Shows a positive amount (matching the total unilevel commissions)
   - The amount matches the commissions you saw in step 6.1

### 7.2 Filter Tab

1. Below the stat cards, there are filter tabs: All, Pairing, Direct, Indirect, **Prod Unilevel**, DFI
2. Click **Prod Unilevel**
3. The table should now show ONLY unilevel_product commission rows
4. Click **All** to show all types again

### 7.3 History Rows

1. Make sure the filter is set to **All**
2. Look at the commission history table
3. Verify rows with type "📦 Prod Unilevel Lvl X" exist
4. Each row should show:
   - Date
   - Type (Prod Unilevel Lvl X)
   - Description (text explaining the commission)
   - From (@test_d — the buyer)
   - Amount (positive number)
   - Cap Impact (— if not capped)
   - Status (Credited)

### 7.4 CD Ledger

1. If the buyer (test_d) has a CD (Commission Deduct) bucket assigned:
   - The CD Ledger section should appear above the filter tabs
   - It should show a row with type "📦 Product Unilevel"
   - The "To CD Bucket" and "To Wallet" columns show how much was deducted

---

## 8. UI: Product Unilevel on Genealogy Page

### 8.1 Navigate to Genealogy

1. Login as **Member A**
2. Go to **Unilevel Network** in the sidebar (or click Genealogy tab then Product Unilevel)

### 8.2 Verify the Tab

1. You should see three tabs (if Binary Tree is enabled):
   - 🌳 Binary Tree
   - 👥 Referral Network
   - 📦 Product Unilevel
2. Click **📦 Product Unilevel**

### 8.3 Verify the Tree Content

1. The card title should say: **Product Unilevel Tree (10 Levels)**
2. Header badges should show:
   - Member count (should say "3 members" or however many members are in your chain)
   - Total PV and Peso value (sum of all members' product PV)
3. Collapsible level sections:
   - **Level 1** should show test_b
   - **Level 2** should show test_c
   - **Level 3** should show test_d
4. Each member row displays:
   - Avatar (first letter of username)
   - Username
   - Package name
   - Prod PV (numerical value)
   - Peso equivalent
   - Joined date
   - Status badge (Active / Suspended)

### 8.4 Toggle Level Sections

1. Click on a **Level 1** header row
2. The members under it should collapse (hide)
3. Click again — they should expand (show) again
4. The arrow icon should toggle between ▼ and ▶

### 8.5 Verify PV/Peso Values

1. The level header shows a badge like "X.XX PV ₱X.XX"
2. This should be the sum of all members' product PV in that level
3. The grand total at the top should be the sum of all levels

---

## 9. UI: Dashboard — Stat Card and Recent Activity

### 9.1 Stat Card

1. Login as **Member A**
2. Go to **Dashboard** (or click the house icon)
3. Look at the KPI stat cards row
4. Verify there is a **Product Unilevel** card
5. It should show the total unilevel commissions earned (in pesos)

### 9.2 Recent Activity

1. Scroll down to the **Recent Activity** section
2. Look for an entry that shows 📦 icon with "Product Unilevel — Lvl X"
3. Verify:
   - The icon is a 📦 (package box)
   - The description includes the level number
   - It shows "via @test_d" or whoever triggered the commission
   - The amount shows as +₱X.XX and is green

---

## 10. UI: Lifetime Cap Status Page

### 10.1 Navigate

1. Login as **Member A**
2. Go to **Lifetime Cap** in the sidebar

### 10.2 Timeline

1. Scroll down to the **Earnings Timeline** section
2. Look for a green dot entry: **📦 Product Unilevel**
3. It should show the amount earned from product unilevel commissions

### 10.3 Earnings Breakdown

1. Scroll down to the **Earnings Breakdown** table
2. Look for a row: **Product Unilevel**
3. It should show:
   - The amount earned
   - The percentage of the lifetime cap
   - Status: ✅ Credited

---

## 11. UI: Admin Member View

### 11.1 View Member A from Admin

1. **Login as admin**
2. Go to **Members**
3. Find **test_a** (or whatever Member A's username is)
4. Click to view their profile

### 11.2 Commissions Tab

1. The **Commissions** tab should be active by default
2. Look at the Type column
3. Find a row with type: **📦 Prod Unilevel Lvl X**
4. Verify it shows the correct level and amount

### 11.3 Cap & DFI Tab

1. Click the **🛡️ Cap & DFI** tab
2. Scroll down to **Cap-Triggered Blocks**
3. If the member hit their lifetime cap, you should see blocked unilevel_product entries there too, labeled as "📦 Prod Unilevel Lvl X"

---

## 12. UI: Sidebar Navigation

### 12.1 Verify Sidebar Link

1. Login as **Member A**
2. Look at the sidebar under the **Network** section
3. You should see a link: **📦 Unilevel Network**
4. It should appear right after **👥 Referral Network**
5. Click it — it should take you to `?page=genealogy&view=product_unilevel`

### 12.2 Verify Sidebar Active State

1. While on the Product Unilevel page, the sidebar link **📦 Unilevel Network** should be highlighted/active

---

## 13. Edge Cases and Regression

### 13.1 Disable the Feature

1. **Login as admin**
2. Go to **Settings**
3. Turn **OFF** the Enable Unilevel Product Bonus toggle
4. Save
5. Login as Member A
6. Verify:
   - Product Unilevel stat card is **gone** from dashboard
   - Prod Unilevel filter tab is **gone** from earnings
   - Unilevel Network tab is **gone** from genealogy
   - Unilevel Network link is **gone** from sidebar
7. Turn it **ON** again

### 13.2 No Referrals

1. Login as a brand-new member who has no downline yet
2. Go to **Unilevel Network**
3. You should see: "No members in your product unilevel tree yet."

### 13.3 No Unilevel Enabled on Product

1. Login as admin
2. Go to **Products**
3. Edit a product
4. Turn **OFF** Unilevel Bonuses for that product
5. Save
6. Login as a member and purchase that product
7. No unilevel commission should be created

### 13.4 Zero Percent Levels

1. Login as admin
2. Go to Products
3. Edit a product with Unilevel ON
4. Set Level 1 = 0, Level 2 = 10, all others = 0
5. Save
6. Make a purchase from a downline:
   - Only Level 2 should get a commission
   - Level 1 should NOT get a commission (0%)

### 13.5 Indirect Referral Still Works

1. Login as Member A
2. Go to **Referral Network**
3. Verify it still shows all your downline members (test_b, test_c, test_d)
4. Go to **Earnings**, filter by **Indirect**
5. Verify indirect referral commissions from the original registration still appear

### 13.6 Binary Tree Still Works

1. Login as Member A
2. Go to **Binary Tree** tab
3. The D3 tree visualization should still load and render
4. It should show members placed in the binary structure

### 13.7 Product Unilevel with PV Gate

**Testing the Personal PV Requirement:**
1. Login as admin
2. Go to Packages
3. Find a package and set a **Personal PV Requirement** (e.g. 100)
4. Login as test_b and check their Personal PV — if it's below 100
5. Make a purchase from test_d
6. Login as test_b and check commissions:
   - test_b should NOT receive a unilevel bonus because they don't meet the PV gate
   - The system should skip test_b and try the same level on the next upline (test_a)

---

## 14. Test Checklist Summary

Copy this checklist and check off items as you test:

### Admin Setup
- [ ] Product Unilevel bonus is ENABLED in Settings
- [ ] At least one product has Unilevel Bonuses turned ON
- [ ] Level percentages are set (e.g. 10, 5, 3, 2, 1, 0.5...)
- [ ] Saving a product preserves the unilevel settings

### Sponsor Chain (4+ members)
- [ ] Member A
- [ ] Member B (sponsored by A)
- [ ] Member C (sponsored by B)
- [ ] Member D (sponsored by C)

### Purchase + Commission Processing
- [ ] Member D makes a repeat purchase (e-wallet)
- [ ] Order is approved immediately
- [ ] Members A, B, C each receive unilevel product commissions
- [ ] Commission amounts match the configured percentages

### Earnings Page
- [ ] Product Unilevel stat card shows the total
- [ ] Prod Unilevel filter tab exists and filters correctly
- [ ] History rows show correct type, description, from, amount, status
- [ ] CD Ledger section shows Product Unilevel type if applicable

### Genealogy Page
- [ ] Product Unilevel tab exists in the tab nav
- [ ] Tree shows all downline members with correct levels
- [ ] Level totals and grand total PV/peso values are accurate
- [ ] Toggle/collapse works on level sections
- [ ] Each member shows Prod PV and peso equivalent
- [ ] Status badges are correct

### Dashboard
- [ ] Product Unilevel stat card exists and shows correct amount
- [ ] Recent Activity shows Product Unilevel entries with 📦 icon

### Cap Status Page
- [ ] Timeline includes Product Unilevel entry
- [ ] Earnings Breakdown includes Product Unilevel row

### Admin Member View
- [ ] Commissions tab shows "Prod Unilevel Lvl X" for unilevel entries
- [ ] Cap & DFI tab shows "Prod Unilevel Lvl X" for blocked entries

### Sidebar
- [ ] "Unilevel Network" link appears after "Referral Network"
- [ ] Link is active when on the Product Unilevel page

### Edge Cases
- [ ] Disabling the feature hides all UI elements
- [ ] New member with no downline sees empty state
- [ ] Product with Unilevel OFF does not create commissions
- [ ] Zero percent levels correctly skip that level
- [ ] Indirect Referral still works (no regression)
- [ ] Binary Tree still works (no regression)
- [ ] PV Gate enforcement correctly skips ineligible uplines
