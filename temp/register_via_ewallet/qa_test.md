# QA Test Guide: Register via E-Wallet

> **What is this feature?**
> There are now **two ways** to register a new member:
> 1. **🎫 Registration Code** — Anyone can use this (guest or logged-in). You enter a code given by your sponsor.
> 2. **💳 E-Wallet** — Only logged-in users can use this. You pay the entry fee using your own e-wallet balance.

---

## Table of Contents
1. [Before You Start](#before-you-start)
2. [Test 1: Guest Registers with a Code](#test-1-guest-registers-with-a-code)
3. [Test 2: Logged-in Member Registers with a Code](#test-2-logged-in-member-registers-with-a-code)
4. [Test 3: Logged-in Member Registers via E-Wallet](#test-3-logged-in-member-registers-via-e-wallet)
5. [Test 4: Error Cases](#test-4-error-cases)
6. [How to Check Results in the Database](#how-to-check-results-in-the-database)
7. [Quick Reference: Test Accounts](#quick-reference-test-accounts)

---

## Before You Start

### What You Need
- Access to the AltasFarm website (local or staging)
- A **test sponsor/upline** account (so you have someone to place the new member under)
- An **unused registration code** (for code tests)
- A **logged-in member** with enough e-wallet balance (for e-wallet success tests)
- A **logged-in member** with low e-wallet balance (for e-wallet error tests — e.g., `test4` with only ₱100)

### How to Get a Test Registration Code
1. Log in as **admin** (`admin` / `admin123`)
2. Go to **Codes** in the sidebar
3. Click **Generate Codes**
4. Select a package (e.g., "Starter"), enter quantity `1`, click **Generate**
5. Copy the new code — it will look like `ABCD-EFGH-IJKL`

### Quick Setup Check
Open your browser and visit:
- `http://localhost/altas/?page=register` — You should see the registration page even **without logging in**.
- If you are redirected to the login page, something is wrong. Report it.

---

## Test 1: Guest Registers with a Code

> **Goal:** Make sure a guest (someone not logged in) can still register using a code.

### Steps
1. **Log out** if you are currently logged in. (Click your name → Logout)
2. Go to `/?page=register`
3. **Look at Step 1.** You should see:
   - A text box labeled **"Registration Code"**
   - **NO** payment method toggle (no "Code" vs "E-Wallet" buttons)
   - A **Validate** button
4. Enter your unused registration code (format: `XXXX-XXXX-XXXX`)
5. Click **Validate**
6. You should see:
   - ✅ A green checkmark and the package name
   - "✓ Code is valid!" in green text
   - The **Continue →** button becomes enabled (no longer greyed out)
7. Click **Continue →**
8. Fill in Step 2:
   - **Username:** Pick something unique, e.g., `qa_test_guest_01`
   - **Password:** `password123`
   - **Confirm Password:** `password123`
   - **Sponsor Username:** Enter the username of your test sponsor (e.g., `test1`)
   - **Binary Upline Username:** Enter the same sponsor username (e.g., `test1`)
   - **Binary Position:** Choose **Left** or **Right** (whichever is free)
9. Click **Review →**
10. On Step 3, check the summary:
    - Payment should show **🎫 Registration Code**
    - Code should show the code you entered
    - Package should show the package name
    - All other details should match what you typed
11. Click **✓ Complete Registration**

### Expected Result
- A green success message: *"Welcome! Your account has been created successfully."*
- You are **automatically logged in** as the new user
- You are taken to the **Dashboard**
- The registration code is now marked as **used** (it cannot be used again)

### Pass / Fail
- [ ] **PASS** — Guest can register with a code and is logged in automatically
- [ ] **FAIL** — Guest sees e-wallet option, or gets "Please log in" error, or code validation fails

---

## Test 2: Logged-in Member Registers with a Code

> **Goal:** Make sure a logged-in member can register a downline using a code.

### Steps
1. **Log in** as a member (e.g., `test1` / `password123`)
2. Click **Register Member** from the sidebar or dashboard
3. **Look at Step 1.** You should see:
   - A **payment method toggle**: 🎫 Code and 💳 E-Wallet
   - "Code" should be selected by default
   - The Registration Code input box
4. Select **🎫 Code** if not already selected
5. Enter an unused registration code
6. Click **Validate**
7. You should see "✓ Code is valid!" and the Continue button becomes active
8. Click **Continue →**
9. Fill in Step 2:
   - **Username:** `qa_test_member_01`
   - **Password / Confirm:** `password123`
   - **Sponsor:** Should be pre-filled with `test1` and **locked** (you cannot change it)
   - **Upline:** Enter `test2` (or any existing member)
   - **Position:** Choose a free slot (Left or Right)
10. Click **Review →**
11. Check the summary, then click **✓ Complete Registration**

### Expected Result
- Green success message: *"Account @qa_test_member_01 registered successfully."*
- You **stay logged in** as `test1` (you are NOT switched to the new account)
- You are taken back to your **Dashboard**

### Pass / Fail
- [ ] **PASS** — Member stays logged in, new account is created, code is marked used
- [ ] **FAIL** — Member gets logged out, or sees wrong payment options, or code validation fails

---

## Test 3: Logged-in Member Registers via E-Wallet

> **Goal:** Make sure a logged-in member can pay the entry fee using their e-wallet to register a downline.

### Before This Test
Make sure the logged-in member has **enough e-wallet balance** to cover the entry fee.

**How to check balance:**
- Go to the **Dashboard**
- Look at the **E-Wallet** card — it shows your total balance
- Check the package entry fee (e.g., Starter = ₱10,000.00)
- If balance is too low, ask the developer to top up the test account, or log in as admin and use **E-Wallet → Top Up**

### Steps
1. **Log in** as a member with enough balance (e.g., `test5` with ₱15,000+)
2. Note down your current e-wallet balance
3. Go to **Register Member**
4. In Step 1, click the **💳 E-Wallet** payment option
5. **Look for:**
   - The code input box should **disappear**
   - A **Package** selector should appear (dropdown or auto-selected card)
   - Your current e-wallet balance is displayed below (e.g., "💳 Your balance: ₱15,000.00")
6. Select a package (if needed), then click **Continue →**
7. Fill in Step 2 (same as Test 2)
8. Click **Review →**
9. On Step 3, check:
   - Payment should show **💳 E-Wallet**
   - Code row should be **hidden**
   - Package should show the package you selected
10. Click **✓ Complete Registration**

> **Note:** If your balance is **too low** to afford even the cheapest package, see Test 4C below. When you click **💳 E-Wallet**, you will see a **yellow warning** instead of the package selector, and the **Continue →** button will stay disabled.

### Expected Result
- Green success message: *"Account @qa_test_member_02 registered successfully."*
- You stay logged in as the original member
- Your e-wallet balance is **reduced by the entry fee**
- The admin/system e-wallet balance is **increased by the entry fee** (revenue)
- A new ledger entry appears in your e-wallet history: "Entry fee for new member @..." (debit)
- A new ledger entry appears in admin's e-wallet history: "Entry fee from @..." (credit)

### How to Verify the Money Moved
1. Go to **Dashboard** → check your e-wallet balance is lower
2. Go to **E-Wallet** page → look for a new **debit** entry with `ref_type = registration`
3. (If you have admin access) Go to **Admin → E-Wallet Monitor** → check the admin ledger for a matching **credit**

### Pass / Fail
- [ ] **PASS** — Balance deducted correctly, admin credited, member stays logged in
- [ ] **FAIL** — Balance not deducted, admin not credited, or member gets logged out

---

## Test 4: Error Cases

These tests check that the system says "no" when something is wrong.

### 4A: Guest tries to use E-Wallet
1. **Log out**
2. Go to `/?page=register`
3. **Check:** There is NO e-wallet option visible. Only the code input.
4. (Optional advanced test) Try to submit the form with `payment_method=ewallet` using browser dev tools

**Expected:** Guest sees only code option. If forced, server rejects with "Please log in to use e-wallet registration."

- [ ] **PASS**
- [ ] **FAIL**

### 4B: Invalid or used registration code
1. Go to `/?page=register` (as guest or logged-in)
2. Enter a fake code: `FAKE-CODE-1234`
3. Click **Validate**

**Expected:** Red error message: "Invalid or already-used registration code." Continue button stays disabled.

- [ ] **PASS**
- [ ] **FAIL**

### 4C: Insufficient e-wallet balance — UI Behavior
1. **Log in** as a member with **low balance** (e.g., `test4` with ₱100, or `test5` with ₱5,000 when the cheapest package costs ₱10,000)
2. Go to **Register Member**
3. In Step 1, click **💳 E-Wallet**
4. **Look carefully at what appears:**

**Expected UI:**
- ❌ The **package dropdown** should **NOT** appear
- ❌ The **balance info card** ("💳 Your balance: ...") should **NOT** appear
- ✅ Instead, a **yellow warning alert** appears with:
  - ⚠️ **"Insufficient E-Wallet Balance"**
  - Your current balance shown (e.g., ₱5,000.00)
  - The minimum entry fee required (e.g., ₱10,000.00)
  - A suggestion: *"Please top up or switch to registration code."*
- The **Continue →** button stays **disabled** (greyed out) no matter what

**What to check:**
- [ ] Package selector is hidden when balance is too low
- [ ] Warning alert is clearly visible
- [ ] Continue button cannot be clicked
- [ ] Switching back to **🎫 Code** re-enables the code input and allows normal registration

- [ ] **PASS**
- [ ] **FAIL**

---

### 4C-2: Insufficient e-wallet balance — Server-side block
1. (Advanced) Try to submit the form with `payment_method=ewallet` and a `package_id` even though the UI shows the warning

**Expected:** Server still rejects with "Insufficient e-wallet balance. Required: ₱XX,XXX.XX"

- [ ] **PASS**
- [ ] **FAIL**

### 4D: Binary position already taken
1. Go to Register Member
2. Enter a valid code (or select e-wallet + package)
3. In Step 2, for **Binary Upline**, enter a user who already has both left and right children
4. Try to select a position

**Expected:** The position radio button is **disabled** (greyed out) with "✗ Taken" shown. If you somehow submit, error: "The [left/right] position under @username is already occupied."

- [ ] **PASS**
- [ ] **FAIL**

### 4E: Username already taken
1. Go to Register Member
2. Enter a valid code
3. In Step 2, type a username that **already exists** (e.g., `test1`)
4. Wait 1 second (it checks automatically)

**Expected:** Red text: "Username is taken." Continue button stays disabled.

- [ ] **PASS**
- [ ] **FAIL**

### 4F: Passwords do not match
1. Go to Register Member
2. Enter a valid code, fill in Step 2
3. Type password: `password123`
4. Type confirm password: `password456`
5. Click **Review →**

**Expected:** Red text: "Passwords do not match." You cannot proceed to Step 3.

- [ ] **PASS**
- [ ] **FAIL**

---

## How to Check Results in the Database

If you have database access (phpMyAdmin or MySQL CLI), run these queries to verify:

### Check the new user
```sql
SELECT username, reg_payment_method, reg_paid_by, reg_code_id, package_id
FROM users
WHERE username = 'qa_test_guest_01';
```

**Expected for code registration:**
- `reg_payment_method` = `code`
- `reg_paid_by` = `NULL`
- `reg_code_id` = a number (the code ID)

**Expected for e-wallet registration:**
- `reg_payment_method` = `ewallet`
- `reg_paid_by` = a number (the payer's user ID)
- `reg_code_id` = `NULL`

### Check the e-wallet ledger (e-wallet registrations only)
```sql
SELECT user_id, type, amount, ref_type, note
FROM ewallet_ledger
WHERE ref_type = 'registration'
ORDER BY created_at DESC;
```

**Expected:**
- One **debit** row for the payer (the member who paid)
- One **credit** row for the admin (system revenue)

### Check the registration code status
```sql
SELECT code, status, used_by
FROM reg_codes
WHERE code = 'YOUR-CODE-HERE';
```

**Expected after use:**
- `status` = `used`
- `used_by` = the new user's ID

---

## Quick Reference: Test Accounts

| Account | Username | Password | Role | Notes |
|---------|----------|----------|------|-------|
| Admin | `admin` | `admin123` | admin | Use to generate codes and check ledger |
| Member A | `test1` | `password123` | member | Good sponsor/upline for tests |
| Member B (low balance) | `test4` | `password123` | member | Use for "insufficient balance" tests |
| Member C | `test5` | `password123` | member | May need balance top-up for e-wallet success tests |

### How to Top Up a Member (Admin)
1. Log in as `admin`
2. Go to **E-Wallet Monitor**
3. Click the **Top-Ups** tab
4. Select recipient (e.g., `test5`)
5. Enter amount (e.g., `10000`)
6. Click **Top Up**

---

## Reporting Bugs

If any test fails, please include:
1. **Which test** failed (e.g., "Test 3: E-Wallet Registration")
2. **What you did** (step by step)
3. **What you expected** to happen
4. **What actually happened** (exact error message or wrong behavior)
5. **Screenshots** if possible
6. **Browser console errors** (press F12 → Console tab)

---

*Last updated: 2026-05-28*
*Updated: Added Test 4C — insufficient e-wallet balance UI behavior (warning replaces package selector)*
