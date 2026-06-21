# Phase 5 QA Test Guide — Admin UI & Settings Cleanup

> **Target:** Beginner QA Tester
> **Environment:** http://localhost/altas/
> **Prerequisites:** Phase 1 through Phase 4 QA tests passed. Logged in as admin AND as a test member in separate browsers. At least one active package exists and one active product with stock > 0.

---

## 🎯 What This Phase Tests

This QA validates three admin-facing changes from Phase 5:

1. **Admin Settings** — The global `personal_pv_requirement` setting is removed from the Settings page
2. **Package Management** — Each package now has its own `personal_pv_requirement` field that can be edited in the Package modal
3. **Product Management** — The admin Products page shows stock (available / total) and allows editing stock via the product modal

It also re-verifies the admin Repeat Purchases page styling (button actions, Payment column, tab order, hidden Actions on Approved tab).

---

## 📋 Prerequisites (Do These First)

### Step 1: Log in as admin

- Open `http://localhost/altas/` in your main browser
- Log in as **admin** (username: `admin`, password: `Admin@1234`)
- Verify: You see the admin dashboard with the sidebar

### Step 2: Ensure at least one test package exists

- Go to `http://localhost/altas/?page=admin_packages`
- Verify: There is at least one package (e.g., "Starter") with an **Entry Fee** and **Status** = Active
- Note the package ID if you need to edit it later

### Step 3: Ensure at least one test product exists with stock

- Go to `http://localhost/altas/?page=admin_products`
- Verify: There is at least one product with:
  - **Price:** ₱1,000.00 or more
  - **PV Value:** 100.00 or more
  - **Stock:** 10 or more
  - **Status:** Active
- If needed, click **+ New Product** to create one

### Step 4: Log in as a test member (separate browser)

- Open an incognito/private window
- Go to `http://localhost/altas/`
- Log in as a test member (e.g., `testqa`)
- Verify: You see the member dashboard with e-wallet balance

---

## 📂 Test Case Breakdown

---

## Section A: PERSONAL PV REQUIREMENT MOVED TO PACKAGES

### TC-501 — Admin Settings: No More "Personal PV Gate" Section

**Steps:**

1. As admin, go to `http://localhost/altas/?page=admin_settings`
2. Scroll down to the **Compensation Plan** section (the card with the 📋 icon)
3. Look for the **Personal PV Gate** section (purple/🚪 card with the `personal_pv_requirement` input)

**Expected Result:**

- The **Personal PV Gate** section is **NOT present** anymore
- The **Compensation Plan** card ends with the **PV Conversion Rate** section (💎 / blue card with `pv_per_peso_rate`)
- There is **no input field named `personal_pv_requirement`** anywhere on the page
- The **PV per Peso Rate** input still exists and works

**Pass / Fail:**

- [✅] PASS — The Personal PV Gate section is completely gone from Settings
- [] FAIL — The Personal PV Gate section still appears on the Settings page

**Screenshot to verify:**
Take a screenshot of the Compensation Plan section in Settings. It should show only:

- Binary toggle
- Indirect Referral toggle
- Default Lifetime Cap Multiplier
- PV per Peso Rate (blue card)

No purple/🚪 Personal PV Gate card below it.

---

### TC-502 — Package Edit Modal: Personal PV Requirement Field Exists

**Steps:**

1. As admin, go to `http://localhost/altas/?page=admin_packages`
2. Find the test package and click **Edit**
3. The package modal opens
4. Scroll down past the **Binary PV Rate** and **Direct Referral** rows

**Expected Result:**

- A row shows **Daily Pair Cap (PV)** on the left (if binary is enabled)
- On the right side of that same row (or directly below if binary is disabled), there is a field labeled:
  - **Personal PV Requirement (PV)**
- It has a numeric input box with the current value (default is `0.00` if never set)
- Below the input is helper text: _"Minimum Personal PV an upline must have to earn repeat-purchase indirect/PV bonuses. 0 = no gate."_

**Pass / Fail:**

- [✅] PASS — The Personal PV Requirement field is visible in the package modal
- [ ] FAIL — The field is missing from the package edit form

---

### TC-503 — Package Edit: Save Personal PV Requirement Value

**Steps:**

1. In the package edit modal, find the **Personal PV Requirement (PV)** field
2. Change the value from `0.00` to `500.00`
3. Click **Update Package** (or **Create Package** if it's a new one)
4. Wait for the page to reload
5. Click **Edit** on the same package again

**Expected Result:**

- The modal opens again
- The **Personal PV Requirement (PV)** field now shows `500.00` (the saved value)
- No error flash appears

**Pass / Fail:**

- [✅] PASS — Value is saved and persisted on re-open
- [] FAIL — Value reverts to 0, or error flash appears

**Cleanup:** Set the value back to `0.00` after testing if you want no gate for other tests.

---

### TC-504 — Commission Engine Reads Package-Level Value (Functional Test)

**Steps:**

1. As admin, set the test member's package `personal_pv_requirement` to `500.00` (from TC-503)
2. Ensure the test member's **Personal PV** is currently **less than 500** (e.g., 0 or 100)
3. As the test member, go to `http://localhost/altas/?page=repeat_purchases`
4. Add a product to the cart and **proceed to checkout**
5. Place an order using **E-Wallet** (instant approval, so PV is distributed immediately)
6. As admin, go to `http://localhost/altas/?page=admin_user_view&id=2` (the test member's sponsor/upline)
7. Check the upline's **Personal PV** and **Group PV**

**Expected Result:**

- The upline (sponsor) did **NOT** receive Group PV from this order because their Personal PV is below the package's `personal_pv_requirement` of 500
- The test member (buyer) **did** receive Personal PV (the buyer always gets Personal PV regardless of the gate)
- If the upline's Personal PV is above 500, then they **did** receive Group PV

**Pass / Fail:**

- [✅] PASS — The gate works correctly (upline below 500 gets nothing, above 500 gets Group PV)
- [] FAIL — The upline gets Group PV even though their Personal PV is below the requirement

**Note:** This is a functional test of the underlying commission engine. If the upline's Personal PV is already above 500, this test won't prove the gate is working. You may need to temporarily reduce the upline's `personal_pv` via SQL for a true negative test.

**Cleanup:** Reset `personal_pv_requirement` to `0.00` after testing.

```sql
UPDATE packages SET personal_pv_requirement = 0.00 WHERE id = 1;
```

---

## Section B: STOCK MANAGEMENT ON ADMIN PRODUCTS PAGE

### TC-505 — Products Table Shows Stock Column

**Steps:**

1. As admin, go to `http://localhost/altas/?page=admin_products`
2. Look at the table headers

**Expected Result:**

- Columns are: **Image**, **Product**, **Price**, **PV Value**, **Stock**, **Status**, **Actions**
- The **Stock** column is between **PV Value** and **Status**
- Every product row has a badge in the Stock column showing two numbers, e.g.:
  - `10 / 10` (green badge) — all stock is available
  - `5 / 10` (orange/yellow badge) — 5 reserved by pending orders, 5 available
  - `0 / 10` (gray badge) — all stock reserved, none available
- Below the badge is small text: _"available / total"_

**Pass / Fail:**

- [✅] PASS — Stock column is visible with correct available/total badges
- [] FAIL — Stock column missing, or values are wrong

---

### TC-506 — Stock Badge Colors Are Correct

**Steps:**

1. Look at the Stock column for different products

**Expected Result:**

- If **available > 0** → badge is **green** (`bg-success-subtle text-success`)
- If **available = 0** but **total stock > 0** → badge is **orange/yellow** (`bg-warning-subtle text-warning`) — this means stock exists but is all reserved by pending/paid/approved orders
- If **total stock = 0** → badge is **gray** (`bg-secondary-subtle text-secondary`) — product has no inventory at all

**Pass / Fail:**

- [✅] PASS — Badge colors match the stock state correctly
- [] FAIL — Wrong colors, e.g., green when available is 0

---

### TC-507 — Product Modal: Stock Input with Stepper Buttons

**Steps:**

1. On the Products page, click **+ New Product**
2. The product modal opens
3. Look below the **Price (₱)** and **PV Value** row

**Expected Result:**

- A field labeled **Stock** with a red asterisk (\*) is visible
- It has an **input group** with three elements:
  - Left button: **−** (minus)
  - Middle input: a number field showing `0` (or the current value for edits)
  - Right button: **+** (plus)
- Below the input is helper text: _"Total physical inventory. Orders reserve from this count but never modify it. Set to 0 to disallow purchases until restocked."_

**Pass / Fail:**

- [✅] PASS — Stock input with ± buttons is visible in the modal
- [] FAIL — Stock field missing, or not using stepper buttons

---

### TC-508 — Stock Stepper Buttons Work

**Steps:**

1. In the New Product modal, find the Stock field (currently showing `0`)
2. Click the **+** button 3 times
3. Click the **−** button 1 time

**Expected Result:**

- After clicking **+** 3 times: the input shows `3`
- After clicking **−** 1 time: the input shows `2`
- The value never goes below `0` (clicking **−** when at `0` keeps it at `0`)

**Pass / Fail:**

- [✅] PASS — Buttons increment/decrement correctly, minimum is 0
- [] FAIL — Buttons don't work, or value goes negative

---

### TC-509 — Create Product with Stock Saves Correctly

**Steps:**

1. In the New Product modal, fill in:
   - **Product Name:** `QA Test Product`
   - **Price (₱):** `1500.00`
   - **PV Value:** `150.00`
   - **Stock:** `25` (use the stepper buttons or type directly)
   - **Status:** Active
2. Click **Create Product**
3. After the page reloads, find the new product in the table

**Expected Result:**

- The new product appears in the table
- **Stock** column shows: `25 / 25` (green badge)
- **Price** shows: `₱1,500.00`
- **PV Value** shows: `150.00`
- **Status** shows: Active (green badge)

**Pass / Fail:**

- [✅] PASS — Product created with correct stock value
- [] FAIL — Stock shows 0 or wrong value, or product not created

**Cleanup:** Delete the test product after this test.

```
Click the "Del" button on the QA Test Product row, confirm deletion.
```

---

### TC-510 — Edit Product Stock Updates Correctly

**Steps:**

1. Find an existing product in the table (e.g., the test product with stock 10)
2. Click **Edit**
3. In the modal, change the **Stock** value from `10` to `50`
4. Click **Update Product**
5. After the page reloads, check the same product's Stock column

**Expected Result:**

- The Stock column now shows: `50 / 50` (or `50 / total` if some is reserved)
- The badge color reflects the new available amount
- No error flash appears

**Pass / Fail:**

- [✅] PASS — Stock updated and persisted
- [] FAIL — Stock reverts to old value, or error occurs

**Cleanup:** Restore the original stock value after testing.

---

### TC-511 — Available Stock Updates When Orders Are Placed

**Steps:**

1. As admin, note the current available stock of a test product (e.g., `10 / 10`)
2. As the test member, go to `http://localhost/altas/?page=repeat_purchases`
3. Add that product to cart (quantity = 3) and place an order using **GCash** (external payment, so it stays in Pending status)
4. As admin, go back to `http://localhost/altas/?page=admin_products`
5. Find the same product

**Expected Result:**

- The Stock column now shows: `7 / 10` (yellow/orange badge)
- The **available** dropped from 10 to 7 because 3 units are reserved by the pending order
- The **total** remains 10 because orders never modify the total physical stock

**Pass / Fail:**

- [✅] PASS — Available stock decreased by the order quantity, total unchanged
- [] FAIL — Available stock didn't change, or total stock changed

**Cleanup:** Reject the pending order to release the reservation:

1. Go to `http://localhost/altas/?page=admin_repeat_purchases`
2. In the Pending tab, find the order
3. Click **Reject**
4. Go back to Products — the stock should show `10 / 10` again

---

## Section C: ADMIN REPEAT PURCHASES UI POLISH

### TC-512 — Tab Order is Pending → Paid → Approved → All

**Steps:**

1. As admin, go to `http://localhost/altas/?page=admin_repeat_purchases`
2. Look at the stat cards (the row of 5 cards at the top)
3. Look at the filter buttons below them

**Expected Result:**

- **Stat cards** order (left to right): `Pending → Paid → Approved → Total → PV This Page`
- **Filter buttons** order (left to right): `Pending → Paid → Approved → All`
- The **Paid** tab/card comes immediately after **Pending**
- The **Approved** tab/card comes after **Paid**
- This matches the workflow: Pending → Paid → Approved

**Pass / Fail:**

- [✅] PASS — Tabs are in the correct order: Pending, Paid, Approved, All
- [] FAIL — Approved comes before Paid, or wrong order

---

### TC-513 — Approved Tab Has No Actions Column

**Steps:**

1. As admin, go to `http://localhost/altas/?page=admin_repeat_purchases&status=approved`
2. Look at the table headers
3. Look at any rows in the table

**Expected Result:**

- The table headers are: **Order**, **Product**, **Amount**, **Payment**, **Proof**, **Status**, **Date**
- There is **NO "Actions" column** in the Approved tab
- There are **NO buttons** (Approve, Reject, Mark Paid) on any row
- The last column is **Date**
- If there are no approved orders, the empty state shows correctly with proper colspan

**Pass / Fail:**

- [✅] PASS — Actions column is completely hidden in the Approved tab
- [] FAIL — Actions column still shows, or buttons are visible

---

### TC-514 — Payment Column Exists in All Tabs

**Steps:**

1. As admin, go to `http://localhost/altas/?page=admin_repeat_purchases&status=pending`
2. Look at the table headers
3. Switch to **Paid** tab and check headers
4. Switch to **All** tab and check headers

**Expected Result:**

- In every tab, the table headers include: **Payment** (between **Amount** and **Proof**)
- The Payment column shows values like: `ewallet`, `gcash`, `maya`, `usdt_trc20`, `usdt_bep20`
- If an order has no payment method stored, it shows `—` (dash)

**Pass / Fail:**

- [✅] PASS — Payment column is visible in all tabs with correct values
- [] FAIL — Payment column missing, or shows wrong data

---

### TC-515 — Action Buttons Are Inline (Not Dropdown)

**Steps:**

1. As admin, go to `http://localhost/altas/?page=admin_repeat_purchases&status=pending`
2. Find a pending order
3. Look at the rightmost column

**Expected Result:**

- The Actions column shows **two separate buttons side by side** (not a dropdown menu):
  - **✓ Mark Paid** (green button)
  - **✕ Reject** (red button)
- There is no "Actions" dropdown toggle or hamburger menu
- The buttons are in a flex row with small gap between them

4. Switch to the **Paid** tab
5. Find a paid order

**Expected Result:**

- The Actions column shows:
  - **✓ Approve** (green button)
  - **✕ Reject** (red button)
- Again, no dropdown — just two inline buttons

**Pass / Fail:**

- [✅] PASS — Inline buttons visible, no dropdown menu
- [] FAIL — Dropdown menu still used, or buttons missing

---

### TC-516 — Mark Paid Button Disabled Without Proof

**Steps:**

1. As the test member, place an external payment order (GCash/Maya) but **do NOT upload a proof image**
2. As admin, go to `http://localhost/altas/?page=admin_repeat_purchases&status=pending`
3. Find the order with no proof

**Expected Result:**

- The **✓ Mark Paid** button is **disabled** (grayed out, cannot be clicked)
- Hovering over the button shows a tooltip: _"No proof uploaded"_
- The **✕ Reject** button is still enabled (you can reject an order without proof)

**Pass / Fail:**

- [✅] PASS — Mark Paid is disabled without proof, tooltip shown
- [] FAIL — Mark Paid is clickable without proof, or no tooltip

---

## Section D: END-TO-END WORKFLOW VERIFICATION

### TC-517 — Full Two-Step Admin Workflow with Buttons

**Steps:**

1. As the test member, place an external payment order (GCash) **with a proof image uploaded**
2. As admin, go to `http://localhost/altas/?page=admin_repeat_purchases&status=pending`
3. Find the order and click **✓ Mark Paid**
4. Confirm the modal dialog

**Expected Result:**

- Green flash: _"Order marked as paid."_
- Order disappears from Pending tab
- Order appears in **Paid** tab with status 💳 Paid

5. In the **Paid** tab, find the order and click **✓ Approve**
6. Confirm the modal dialog

**Expected Result:**

- Green flash: _"Order approved and PV distributed."_
- Order disappears from Paid tab
- Order appears in **All** tab with status ✓ Approved
- In the **Approved** tab, the order shows **NO action buttons** (no Actions column)

**Pass / Fail:**

- [✅] PASS — Two-step workflow completes successfully with inline buttons
- [] FAIL — Button clicks don't work, or modal doesn't appear, or workflow broken

---

### TC-518 — Reject from Pending Tab Works

**Steps:**

1. As the test member, place another external payment order with proof
2. As admin, go to `http://localhost/altas/?page=admin_repeat_purchases&status=pending`
3. Find the order and click **✕ Reject**
4. Confirm the modal dialog

**Expected Result:**

- Green flash: _"Order rejected."_
- Order disappears from Pending tab
- Order appears in **All** tab with status ✕ Rejected
- In the **Approved** tab, this order does NOT appear (because it's rejected, not approved)

**Pass / Fail:**

- [✅] PASS — Reject works correctly from Pending tab
- [] FAIL — Reject fails, or order shows wrong status

---

## Section E: SETTINGS CLEANUP VERIFICATION

### TC-519 — Saving Settings Does NOT Break Without personal_pv_requirement

**Steps:**

1. As admin, go to `http://localhost/altas/?page=admin_settings`
2. Change any harmless setting (e.g., change **Site Name** to something else, then change it back)
3. Click **💾 Save All Settings**

**Expected Result:**

- Green flash: _"Settings saved."_
- No error flash about missing `personal_pv_requirement`
- The page reloads and all settings are preserved

**Pass / Fail:**

- [✅] PASS — Settings save successfully without the old field
- [] FAIL — Error flash about missing field, or settings not saved

---

## 📊 QA Summary Sheet

Copy this to a notepad and check off each test as you go:

| Test ID | Description                                            | Status          |
| ------- | ------------------------------------------------------ | --------------- |
| TC-501  | Personal PV Gate removed from Settings                 | ☐ PASS / ☐ FAIL |
| TC-502  | Personal PV Requirement field exists in Package modal  | ☐ PASS / ☐ FAIL |
| TC-503  | Package-level Personal PV Requirement saves correctly  | ☐ PASS / ☐ FAIL |
| TC-504  | Commission gate reads package-level value (functional) | ☐ PASS / ☐ FAIL |
| TC-505  | Products table shows Stock column                      | ☐ PASS / ☐ FAIL |
| TC-506  | Stock badge colors match available state               | ☐ PASS / ☐ FAIL |
| TC-507  | Product modal has Stock input with stepper             | ☐ PASS / ☐ FAIL |
| TC-508  | Stock stepper ± buttons work correctly                 | ☐ PASS / ☐ FAIL |
| TC-509  | Create product with stock saves correctly              | ☐ PASS / ☐ FAIL |
| TC-510  | Edit product stock updates correctly                   | ☐ PASS / ☐ FAIL |
| TC-511  | Available stock updates when orders reserve stock      | ☐ PASS / ☐ FAIL |
| TC-512  | Tab order: Pending → Paid → Approved → All             | ☐ PASS / ☐ FAIL |
| TC-513  | Approved tab has no Actions column                     | ☐ PASS / ☐ FAIL |
| TC-514  | Payment column visible in all tabs                     | ☐ PASS / ☐ FAIL |
| TC-515  | Action buttons are inline (not dropdown)               | ☐ PASS / ☐ FAIL |
| TC-516  | Mark Paid disabled without proof                       | ☐ PASS / ☐ FAIL |
| TC-517  | Full two-step admin workflow with buttons              | ☐ PASS / ☐ FAIL |
| TC-518  | Reject from Pending tab works                          | ☐ PASS / ☐ FAIL |
| TC-519  | Settings save without old personal_pv_requirement      | ☐ PASS / ☐ FAIL |

---

## 🧹 Cleanup After QA

After all tests are complete, clean up any test data:

```sql
-- Delete test orders (for test member, adjust member_id as needed)
DELETE FROM repeat_purchase_order_items WHERE order_id IN (
    SELECT id FROM repeat_purchase_orders WHERE member_id = 2
);
DELETE FROM repeat_purchase_orders WHERE member_id = 2;

-- Clear test member's cart
DELETE FROM cart_items WHERE cart_id IN (
    SELECT id FROM carts WHERE member_id = 2
);
DELETE FROM carts WHERE member_id = 2;

-- Restore balance (if needed)
UPDATE users SET ewallet_balance = 0 WHERE id = 2;

-- Reset package-level personal_pv_requirement to 0 (no gate)
UPDATE packages SET personal_pv_requirement = 0.00;

-- Delete QA test product (if you created one)
-- DELETE FROM products WHERE name = 'QA Test Product';
```

---

## ✅ Pass Criteria

- **Minimum 17 out of 19 tests must pass** for Phase 5 to be considered complete.
- **Critical tests (TC-501, TC-503, TC-509, TC-512, TC-515, TC-517) must ALL pass.** If any of these fail, the phase is not complete.
- Document any failures with screenshots and the exact error message.

---

## 🔍 What Changed in Phase 5 (Reference)

| Area         | Before                                                      | After                                                    |
| ------------ | ----------------------------------------------------------- | -------------------------------------------------------- |
| **Settings** | Global `personal_pv_requirement` field in Compensation Plan | Removed from Settings entirely                           |
| **Packages** | No per-package PV gate                                      | Each package has `personal_pv_requirement` in edit modal |
| **Products** | No stock column, no stock input                             | Stock column (available/total) + stepper input in modal  |
| **Admin RP** | Dropdown "Actions" menu                                     | Inline **✓/✕** buttons for Mark Paid/Approve/Reject      |
| **Admin RP** | No Payment column                                           | Payment column added between Amount and Proof            |
| **Admin RP** | Tab order: Pending → Approved → Paid → All                  | Tab order: Pending → Paid → Approved → All               |
| **Admin RP** | Actions column shown in all tabs                            | Actions column hidden in **Approved** tab only           |
