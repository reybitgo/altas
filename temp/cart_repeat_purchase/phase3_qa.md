# Phase 3 QA Test Guide — Checkout & Admin Order Review

> **Target:** Beginner QA Tester
> **Environment:** http://localhost/altas/
> **Prerequisites:** Phase 1 and Phase 2 QA tests passed. Logged in as admin AND as a test member in separate browsers.

---

## 🎯 What This Phase Tests

This QA validates the checkout flow (e-wallet instant + external payment with proof) and the admin two-step review process (Mark Paid → Approve). It also tests commission/PV distribution on approval.

---

## 📋 Prerequisites (Do These First)

### Step 1: Create a test member (if not already done)

- Log in as **admin**
- Go to `http://localhost/altas/?page=register`
- Register a member with username `testqa` (any package)
- Note the password and the member ID (check the Users page)

### Step 2: Top up the test member's e-wallet

- Go to `http://localhost/altas/?page=admin_user_view&id=2` (or whatever the test member's ID is)
- Top up the e-wallet with **₱50,000** or more
- Verify: Balance shows **₱50,000.00** in the member's top bar

### Step 3: Ensure a test product exists with stock

- Go to `http://localhost/altas/?page=admin_products`
- Check that a product exists (e.g., ID 7) with:
  - **Price:** ₱1,000.00 or more
  - **PV Value:** 100.00 or more
  - **Stock:** 100 (set via SQL if the stock UI isn't ready yet)
- **Verify:** The product shows as **In Stock** on the member's Repeat Purchases page

### Step 4: Log in as the test member

- Open a second browser or incognito window
- Log in as `testqa`
- Verify: You see the member dashboard with the e-wallet balance

---

## 📂 Test Case Breakdown

---

## Section A: E-WALLET CHECKOUT (Member → Auto-Approved)

### TC-301 — Checkout Page Loads

**Steps:**

1. As the test member, go to `http://localhost/altas/?page=repeat_purchases`
2. Find a product with stock > 0
3. Click **Add to Cart** (quantity = 1)
4. The cart sidebar should open automatically (or click the 🛒 icon in the topbar)
5. Click **Proceed to Checkout**

**Expected Result:**

- The page loads at `http://localhost/altas/?page=checkout`
- You see the order summary with the product name, quantity, unit price, and subtotal
- You see a **Binary Position** section (Left / Right)
- You see **Payment Method** cards (E-Wallet, GCash, Maya, etc.)
- The **E-Wallet** card shows your current balance (₱50,000+)

**Pass / Fail:**

- ✅ PASS — Page loads with correct data
- [ ] FAIL — Page error, missing data, or layout broken

---

### TC-302 — E-Wallet Checkout Completes Successfully

**Steps:**

1. On the checkout page, select **Left** under Binary Position (or any side)
2. Select **E-Wallet** as the payment method (should be pre-selected if balance is sufficient)
3. Check the **"I confirm..."** terms checkbox
4. Click **Place Order**

**Expected Result:**

- Redirects to `http://localhost/altas/?page=repeat_purchases`
- A **green success flash** appears: "Order placed and approved. PV has been distributed."
- The cart is now empty (cart badge shows 0)

**Pass / Fail:**

- [ ] PASS — Success flash shown, redirected
- [ ] FAIL — Error flash, page not redirected, or cart still has items

---

### TC-303 — E-Wallet Balance Deducted

**Steps:**

1. After TC-302, look at the topbar balance

**Expected Result:**

- Balance is reduced by the order total (e.g., if product was ₱1,000, balance drops from ₱50,000 to ₱49,000)

**Pass / Fail:**

- [ ] PASS — Balance reduced correctly
- [ ] FAIL — Balance unchanged or wrong amount deducted

---

### TC-304 — Order Appears as Approved in Admin

**Steps:**

1. Switch to the **admin** browser
2. Go to `http://localhost/altas/?page=admin_repeat_purchases`
3. Click the **Pending** tab — the order should NOT be here (because it was auto-approved)
4. Click the **All** tab

**Expected Result:**

- The order appears in the "All" tab with status: **✓ Approved**
- Payment method shows: **ewallet**
- Total Price and Total PV match the product values
- The member name shows as `@testqa`

**Pass / Fail:**

- [ ] PASS — Order shows with status "approved" in the All tab
- [ ] FAIL — Order is in Pending, or missing from All tab

---

### TC-305 — PV Distributed (Personal PV)

**Steps:**

1. In the admin browser, go to `http://localhost/altas/?page=admin_user_view&id=2` (the test member's ID)
2. Scroll to the "Personal PV" section or look at the user's stats

**Expected Result:**

- The member's **Personal PV** has increased by the product's total PV (e.g., if the product was 100 PV, Personal PV should increase by 100)
- Check: `pv_transactions` table should have a new row with `type = product_personal` for this user

**Pass / Fail:**

- [ ] PASS — Personal PV increased by the order's total PV
- [ ] FAIL — Personal PV unchanged

---

## Section B: EXTERNAL PAYMENT (Member → Admin Review)

### TC-306 — External Checkout with Proof Upload

**Steps:**

1. Switch back to the **test member** browser
2. Go to `http://localhost/altas/?page=repeat_purchases`
3. Add a product to the cart again (quantity = 1)
4. Click the 🛒 cart icon, then **Proceed to Checkout**
5. Select **Left** or **Right** for Binary Position
6. Select **GCash** (or **Maya**) as payment method
7. A **file upload zone** appears — drag & drop a test image (JPG or PNG) or click to upload
8. Verify the file name appears (e.g., "Selected: test.jpg")
9. Check the terms checkbox
10. Click **Place Order**

**Expected Result:**

- Redirects to `http://localhost/altas/?page=repeat_purchases`
- A **green success flash** appears: "Order placed. Admin will review your payment proof."
- The cart is empty

**Pass / Fail:**

- [ ] PASS — Success flash, file uploaded, order created
- [ ] FAIL — Error about proof upload, or order not created

---

### TC-307 — Order Appears as Pending in Admin

**Steps:**

1. Switch to the **admin** browser
2. Go to `http://localhost/altas/?page=admin_repeat_purchases`
3. You should be on the **Pending** tab by default

**Expected Result:**

- The new order appears in the Pending tab
- Status badge shows: **⏳ Pending**
- Payment method shows: **gcash** (or **maya**)
- The **Proof** column shows a thumbnail image of the uploaded proof
- Clicking the thumbnail opens the full image in a new tab

**Pass / Fail:**

- [ ] PASS — Order visible in Pending with proof thumbnail
- [ ] FAIL — Order missing, or proof not shown

---

### TC-308 — Admin Marks Order as Paid

**Steps:**

1. In the Pending tab, find the GCash/Maya order
2. Click the **Mark Paid** button

**Expected Result:**

- Green success flash: "Order marked as paid."
- The order disappears from the **Pending** tab
- Click the **Paid** tab — the order now appears here with status: **💳 Paid**

**Pass / Fail:**

- [ ] PASS — Status changed to "paid", moved to Paid tab
- [ ] FAIL — Error flash, or status not changed

---

### TC-309 — Admin Approves Order

**Steps:**

1. In the **Paid** tab, find the order
2. Click the **Approve** button

**Expected Result:**

- Green success flash: "Order approved and PV distributed."
- The order disappears from the **Paid** tab
- Click the **All** tab — the order now shows status: **✓ Approved**

**Pass / Fail:**

- [ ] PASS — Status changed to "approved", PV distributed
- [ ] FAIL — Error flash, or status not changed

---

### TC-310 — PV Distributed from External Order

**Steps:**

1. Go to `http://localhost/altas/?page=admin_user_view&id=2`
2. Check the member's Personal PV

**Expected Result:**

- Personal PV has increased by the second order's total PV (e.g., from 100 to 200)
- The PV increase is from the approved order, not the pending one

**Pass / Fail:**

- [ ] PASS — Personal PV increased by the second order's PV
- [ ] FAIL — Personal PV unchanged

---

## Section C: REJECT FLOW

### TC-311 — Admin Rejects a Pending Order

**Steps:**

1. As the test member, place a **third** order with GCash/Maya and upload proof
2. Switch to admin, go to `http://localhost/altas/?page=admin_repeat_purchases`
3. In the **Pending** tab, find the new order
4. Click the **Reject** button
5. Confirm the browser dialog: "Reject this order? No PV will be distributed."

**Expected Result:**

- Green success flash: "Order rejected."
- The order disappears from the **Pending** tab
- Click the **All** tab — the order shows status: **✕ Rejected**

**Pass / Fail:**

- [ ] PASS — Status changed to "rejected", confirm dialog appeared
- [ ] FAIL — No confirm dialog, or status not changed

---

### TC-312 — No PV for Rejected Order

**Steps:**

1. Go to `http://localhost/altas/?page=admin_user_view&id=2`
2. Check the member's Personal PV

**Expected Result:**

- Personal PV is **unchanged** from the value after TC-310 (the rejected order did NOT add PV)
- Example: If PV was 200 after TC-310, it should still be 200

**Pass / Fail:**

- [ ] PASS — Personal PV unchanged by the rejected order
- [ ] FAIL — Personal PV increased (PV leaked to rejected order)

---

## Section D: VALIDATION & EDGE CASES

### TC-313 — Stock Check at Checkout

**Steps:**

1. As admin, go to `http://localhost/altas/?page=admin_products`
2. Set the test product's stock to **0** (via SQL: `UPDATE products SET stock = 0 WHERE id = 7;`)
3. As the test member, try to add that product to the cart

**Expected Result:**

- The "Add to Cart" button is **disabled** or shows "Out of Stock"
- OR if the member already has it in the cart, going to checkout shows a stock error

**Pass / Fail:**

- [ ] PASS — Product cannot be purchased, or stock error shown at checkout
- [ ] FAIL — Member can still add/purchase the out-of-stock product

**Cleanup:** Restore stock: `UPDATE products SET stock = 100 WHERE id = 7;`

---

### TC-314 — Empty Cart Redirect

**Steps:**

1. As the test member, ensure the cart is empty (remove all items or place all orders)
2. Directly navigate to `http://localhost/altas/?page=checkout`

**Expected Result:**

- Redirects to `http://localhost/altas/?page=repeat_purchases`
- An orange/blue flash appears: "Your cart is empty."

**Pass / Fail:**

- [ ] PASS — Redirected to Repeat Purchases with flash message
- [ ] FAIL — Checkout page loads with empty order, or error page

---

### TC-315 — Insufficient E-Wallet Balance

**Steps:**

1. As admin, go to `http://localhost/altas/?page=admin_user_view&id=2`
2. Reduce the test member's e-wallet balance to **₱0** (or less than the product price)
3. As the test member, add a product to cart and go to checkout

**Expected Result:**

- The **E-Wallet** payment option shows "Insufficient" in red text
- OR the E-Wallet option is not selectable as the default
- The member can still select an external payment method (GCash/Maya) instead

**Pass / Fail:**

- [ ] PASS — E-Wallet option warns about insufficient balance, member cannot use it
- [ ] FAIL — E-Wallet option is selectable and allows checkout, causing an error later

**Cleanup:** Restore the balance to ₱50,000+

---

## Section E: ADMIN VIEW VERIFICATION

### TC-316 — Admin View Shows All Status Tabs

**Steps:**

1. As admin, go to `http://localhost/altas/?page=admin_repeat_purchases`
2. Verify the three tabs at the top: **Pending**, **Paid**, **All**

**Expected Result:**

- All three tabs are clickable and show the correct orders
- **Pending** shows only pending orders
- **Paid** shows only paid orders
- **All** shows all orders regardless of status

**Pass / Fail:**

- [ ] PASS — All three tabs work and filter correctly
- [ ] FAIL — Tabs missing, or filter shows wrong orders

---

### TC-317 — Order Table Columns

**Steps:**

1. Look at the orders table in the admin view

**Expected Result:**

- Columns are: **Order #**, **Member**, **Product**, **Qty**, **Total PV**, **Total Price**, **Proof**, **Status**, **Date**, **Actions**
- The Product column shows the product name and image
- The Proof column shows the uploaded image thumbnail
- The Actions column shows the correct buttons based on status

**Pass / Fail:**

- [ ] PASS — All columns visible and correct
- [ ] FAIL — Missing columns, broken layout, or wrong data

---

### TC-318 — "Mark Paid" Disabled Without Proof

**Steps:**

1. As a test member, place an external payment order but intentionally **do not upload a proof image**
2. As admin, go to the Pending tab

**Expected Result:**

- The **Mark Paid** button is **disabled** (grayed out)
- Hovering over the button shows a tooltip: "No proof uploaded"
- The admin can only click **Reject** for this order

**Pass / Fail:**

- [ ] PASS — Mark Paid disabled, tooltip shown
- [ ] FAIL — Mark Paid is clickable, or order goes through without proof

---

## 📊 QA Summary Sheet

Copy this to a notepad and check off each test as you go:

| Test ID | Description                                 | Status          |
| ------- | ------------------------------------------- | --------------- |
| TC-301  | Checkout page loads with correct data       | ☐ PASS / ☐ FAIL |
| TC-302  | E-wallet checkout completes successfully    | ☐ PASS / ☐ FAIL |
| TC-303  | E-wallet balance deducted correctly         | ☐ PASS / ☐ FAIL |
| TC-304  | E-wallet order appears as approved in admin | ☐ PASS / ☐ FAIL |
| TC-305  | PV distributed for e-wallet order           | ☐ PASS / ☐ FAIL |
| TC-306  | External checkout with proof upload         | ☐ PASS / ☐ FAIL |
| TC-307  | External order appears as pending in admin  | ☐ PASS / ☐ FAIL |
| TC-308  | Admin marks order as paid                   | ☐ PASS / ☐ FAIL |
| TC-309  | Admin approves order and PV distributed     | ☐ PASS / ☐ FAIL |
| TC-310  | PV distributed from external order          | ☐ PASS / ☐ FAIL |
| TC-311  | Admin rejects pending order                 | ☐ PASS / ☐ FAIL |
| TC-312  | No PV for rejected order                    | ☐ PASS / ☐ FAIL |
| TC-313  | Stock check blocks out-of-stock purchase    | ☐ PASS / ☐ FAIL |
| TC-314  | Empty cart redirects to catalog             | ☐ PASS / ☐ FAIL |
| TC-315  | Insufficient e-wallet balance handled       | ☐ PASS / ☐ FAIL |
| TC-316  | Admin status tabs (Pending/Paid/All) work   | ☐ PASS / ☐ FAIL |
| TC-317  | Order table columns are correct             | ☐ PASS / ☐ FAIL |
| TC-318  | Mark Paid disabled without proof            | ☐ PASS / ☐ FAIL |

---

## 🧹 Cleanup After QA

After all tests are complete, run this SQL to clean up test data:

```sql
-- Delete test orders (for member_id = 2, adjust as needed)
DELETE FROM repeat_purchase_order_items WHERE order_id IN (
    SELECT id FROM repeat_purchase_orders WHERE member_id = 2
);
DELETE FROM repeat_purchase_orders WHERE member_id = 2;

-- Clear test member's cart
DELETE FROM cart_items WHERE cart_id IN (
    SELECT id FROM carts WHERE member_id = 2
);
DELETE FROM carts WHERE member_id = 2;

-- Restore balance and stock (if needed)
UPDATE users SET ewallet_balance = 0 WHERE id = 2;
UPDATE products SET stock = 100 WHERE id = 7;
```

---

## ✅ Pass Criteria

- **Minimum 16 out of 18 tests must pass** for Phase 3 to be considered complete.
- **Critical tests (TC-302, TC-308, TC-309, TC-313) must ALL pass.** If any of these fail, DO NOT proceed to Phase 4.
- Document any failures with screenshots and the exact error message.
