# Phase 4 QA Testing Guide
## Reactivation System, Proof-of-Payment Upload & Admin Payment Display

**Version:** 1.2 · **Environment:** `http://localhost/altas/` · **Database:** `u938213108_altas_db`

> **v1.1 Changes:** Added proof-of-payment image upload tests, admin payment account display tests, and admin reactivations view proof column verification.  
> **v1.2 Changes:** Admin payment accounts (GCash/Maya/USDT) now configured via **Admin → Settings** (saved to `settings` table). Both member reactivation page and admin reactivations view read from the same source. Confirm modal shows admin account. `saveSettings()` uses `INSERT ... ON DUPLICATE KEY UPDATE` so new keys auto-create.

---

## Prerequisites

- Phase 1–3 schema deployed (reactivations table, CapEngine, DFI engine)
- **Run migrations** if tables already exist:
  ```bash
  mysql -u USER -p DATABASE < migrations/002_add_reactivation_admin_tracking.sql
  mysql -u USER -p DATABASE < migrations/003_add_reactivation_proof_image.sql
  mysql -u USER -p DATABASE < migrations/004_fix_reactivation_status_enum.sql
  ```
- At least one member with `cap_status = 'capped'`
- **Admin payment accounts configured** (see Test 0 below)

> **Known issue:** Older deployments may have `status ENUM('pending','completed','failed')` instead of the correct `ENUM('pending','completed','rejected')`. If rejecting a reactivation throws *"Data truncated for column 'status'"*, run migration 004 above.

---

## Test 0: Configure Admin Payment Accounts in Settings

> **Run this first** — all downstream reactivation tests depend on these values being set.

### Steps
1. Log in as `admin`
2. Go to `/?page=admin_settings`
3. Scroll to **"🏦 Admin Payment Accounts (for Reactivation)"** section
4. Fill **GCash Number**: `09171234567`
5. Fill **Maya Number**: `09281234567`
6. Fill **USDT TRC20 Address**: `TN8dqFnGBcP8sYcKEkMvHrwJqZ6kLmX9pQ`
7. Click **💾 Save Settings**

### Expected
- Flash: **"Settings saved."**
- phpMyAdmin → `settings` table → rows `gcash_number`, `maya_number`, `usdt_address` exist with values
- If rows did not exist before, they are **auto-created** (not updated-to-nowhere)

### Quick Verification SQL
```sql
SELECT key_name, value FROM settings
WHERE key_name IN ('gcash_number','maya_number','usdt_address');
```

---

## Test 1: Dashboard Capped Banner

### Steps
1. Log in as a capped member
2. View dashboard

### Expected
- ⚠️ Banner appears at top: "Lifetime Income Cap Reached"
- Shows earned / cap amount
- **[Reactivate Account]** button visible

---

## Test 2: Reactivation Page — Payment Methods & Admin Accounts

### Steps
1. Click "Reactivate Account" on dashboard

### Expected
- Page shows reactivation fee (e.g. ₱10,000.00)
- Shows window countdown (e.g. "Window closes in 12 day(s)")
- Payment options:
  - 💳 E-Wallet (if balance >= fee, checked by default)
  - 📱 GCash
  - 💙 Maya
  - ₮ USDT
- **Admin account info displayed dynamically from settings:**
  - Selecting GCash shows admin GCash number (`09171234567`)
  - Selecting Maya shows admin Maya number (`09281234567`)
  - Selecting USDT shows admin USDT address (`TN8dqFnGBcP8sYcKEkMvHrwJqZ6kLmX9pQ`)
- Terms checkbox required
- Submit button **disabled** until terms checked
- Selecting external payment shows info notice with admin account:
  > "After submitting, please send ₱10,000.00 via GCASH to 09171234567 and wait for admin confirmation."

---

## Test 3: Reactivation via E-Wallet (Immediate)

### Setup
- Member is capped
- E-wallet balance >= reactivation fee

### Steps
1. Select "E-Wallet" payment
2. Check terms checkbox
3. Submit

### Expected
- Success flash (green): "Account reactivated successfully..."
- `users.cap_status` = `'active'`
- `users.lifetime_earned` = `0`
- `users.dfi_days_used` = `0`
- `users.dfi_active` = `1`
- `ewallet_ledger` has debit entry for fee
- `reactivations` table has new record with `status='completed'`, `proof_image` = `NULL`

### Quick Verification SQL
```sql
SET @user_id := 6;
SELECT u.cap_status, u.lifetime_earned, u.dfi_days_used, u.dfi_active,
       r.status, r.payment_method, r.amount_paid, r.proof_image
FROM users u
LEFT JOIN reactivations r ON r.user_id = u.id
WHERE u.id = @user_id
ORDER BY r.created_at DESC LIMIT 1;
```

---

## Test 4: Reactivation via External Payment with Proof Image (GCash/Maya/USDT)

### Setup
- Member is capped
- Any e-wallet balance (external doesn't require it)
- Have a test image file ready (JPG/PNG/GIF/WebP, under 5MB)
- Admin payment accounts configured (Test 0)

### Steps
1. Select "GCash" (or Maya/USDT) payment
2. Check terms checkbox
3. Click **Choose File** and select a test image
4. Submit

### Expected
- Info flash (blue): "Reactivation request submitted. Admin will review your payment proof and confirm shortly."
- `users.cap_status` remains `'capped'` (not reset yet!)
- `reactivations` table has new record with `status='pending'`
- `proof_image` column contains path like `reactivation_proofs/reactivation_6_1234567890.jpg`
- File exists in `uploads/reactivation_proofs/`
- No e-wallet debit entry

### Steps (member dashboard)
5. Return to dashboard

### Expected
- ⏳ **Pending Reactivation** banner replaces the capped banner:
  > "Your reactivation request has been submitted and is awaiting admin confirmation."

### Steps (admin confirmation)
6. Log in as admin
7. Visit `/?page=admin_reactivations`
8. Filter by **Pending** tab
9. Find the member's request

### Expected (admin reactivations list)
- **Proof** column shows a 48×48 thumbnail of the uploaded image
- Clicking thumbnail opens full image in new tab
- **Method** column shows:
  - GCash badge (blue)
  - Below badge: `→ 09171234567` (admin account from settings)
- **Fee** column shows ₱10,000.00
- **Actions** column has **✓ Confirm** and **✕ Reject** buttons

10. Click **✓ Confirm** (after verifying external payment received)

### Expected (admin confirm modal)
- Modal title: **"✓ Confirm Reactivation"**
- Modal description shows:
  > "Confirm you've received ₱10,000.00 from **@member** via **GCash**.  
  > Admin account: **09171234567**  
  > This will reset the member's cap state to active."
- After confirm: success flash

### Expected (member, after admin confirm)
- `users.cap_status` = `'active'`
- `users.lifetime_earned` = `0`
- `users.dfi_days_used` = `0`
- `reactivations.status` = `'completed'`
- Dashboard banner removed, normal dashboard shown

---

## Test 5: External Payment — Reject with Proof Image

### Setup
- Member has pending external reactivation with proof image (from Test 4)

### Steps
1. Admin visits `/?page=admin_reactivations&status=pending`
2. Find pending request
3. Click **✕ Reject**
4. Enter rejection reason: "Payment not received"
5. Submit

### Expected (admin)
- Success flash: "Reactivation rejected."
- Record status changes to `'rejected'`
- Proof image still visible in **Rejected** tab for audit

### Expected (member)
- Member remains `'capped'`
- Dashboard shows ⚠️ "Lifetime Income Cap Reached" banner again
- Member can submit a new reactivation request (with new proof image)

---

## Test 6: External Payment — Missing Proof Image Blocked

### Setup
- Member is capped

### Steps
1. Select "GCash" payment
2. Check terms checkbox
3. **Do NOT upload a proof image**
4. Submit

### Expected
- Error flash (red): "Please upload proof of payment."
- Request NOT created
- Member remains capped

---

## Test 7: Proof Image Validation

### Steps
1. Select GCash payment
2. Try uploading a non-image file (e.g. `.txt` or `.pdf`)

### Expected
- Error flash: "Proof must be an image (JPEG, PNG, GIF, WebP)."
- Request blocked

### Steps
3. Try uploading an image larger than 5MB

### Expected
- Error flash: "Image must be under 5MB."
- Request blocked

---

## Test 8: Reactivation with Insufficient E-Wallet Balance

### Setup
- Member is capped
- E-wallet balance < fee

### Steps
1. Visit reactivation page

### Expected
- E-wallet option hidden
- Info message: "E-Wallet balance too low... Choose an external payment method."
- External methods shown as default

---

## Test 9: Non-Capped Member Access

### Steps
1. Log in as active member
2. Visit `/?page=reactivate` directly

### Expected
- Redirected to dashboard
- Info flash: "Your account is not currently capped."

---

## Test 10: Admin Reactivation Log

### Steps
1. Log in as admin
2. Visit `/?page=admin_reactivations`

### Expected
- Status filter tabs: ⏳ Pending / ✅ Completed / ❌ Rejected / 📋 All
- **Pending Fees** stat card shows total pending amount
- **Total Revenue** stat card shows total completed amount
- Table columns: #, Member, Fee, Previous Earned, Method, **Proof**, Requested, Status, Actions
- Pending rows have **✓ Confirm** and **✕ Reject** buttons
- Completed/Rejected rows show processed timestamp
- For external methods: Method badge shows admin account below it (→ account number/address)
- For e-wallet methods: No admin account shown under method badge
- Proof column shows image thumbnail or `—` for e-wallet / missing proof
- Pagination works

---

## Test 11: Cap Status Page Reactivation History

### Steps
1. Member visits `/?page=cap_status`

### Expected
- If capped with no pending: reactivation CTA banner with fee and button
- If has pending request: CTA banner still visible (but blocked from new request)
- If reactivated before: "Reactivation History" table appears
- Table shows date, previous earned, fee paid, method, status

---

## Test 12: Midnight Cron Window Expiration

### Setup
- Create/update a member to be capped with `capped_at` = 20 days ago
- Package `reactivation_window_days` = 15

### Steps
1. Run `php cron/midnight_reset.php`

### Expected
- Log shows: `Cap expiration: N member(s) moved from 'capped' to 'perminact'.`
- Member's `cap_status` = `'perminact'`

---

## Test 13: DFI Resets After Reactivation

### Steps
1. Reactivate a capped member (Test 3 or Test 4 after admin confirm)
2. Visit dashboard

### Expected
- DFI widget shows "0 days used / 90 remaining"
- DFI status = "Active"
- Next midnight cron will process DFI for this member

---

## Test 14: Window Expired = Permanent

### Setup
- Member capped 20 days ago, window = 15 days
- Run cron to expire them

### Steps
1. Visit `/?page=reactivate` as this member

### Expected
- Error flash: "Your reactivation window has expired."
- Or: "Your account is permanently inactive."

---

## Test 15: Admin Payment Settings — First-Time Save Creates Rows

### Setup
- Fresh install or manually delete rows:
  ```sql
  DELETE FROM settings WHERE key_name IN ('gcash_number','maya_number','usdt_address');
  ```

### Steps
1. Admin visits `/?page=admin_settings`
2. Fill GCash Number, Maya Number, USDT Address
3. Click **💾 Save Settings**

### Expected
- Flash: **"Settings saved."**
- phpMyAdmin → `settings` table → the three rows **exist** with correct values
- No SQL errors — `INSERT ... ON DUPLICATE KEY UPDATE` created them automatically

---

## Test 16: Admin Payment Settings Persist Across Reset

### Steps
1. Admin visits `/?page=admin_settings`
2. Set GCash Number: `09171234567`
3. Set Maya Number: `09281234567`
4. Set USDT Address: `TN8dqFnGBcP8sYcKEkMvHrwJqZ6kLmX9pQ`
5. Save settings
6. Run `reset.php` (database reset)

### Expected
- `settings` table **retains** `gcash_number`, `maya_number`, `usdt_address`
- After reset, admin reactivation page still shows correct admin accounts
- Member reactivation page shows correct admin accounts for payment methods

---

## Test 17: Reset.php Cleans Proof Images

### Setup
- Have pending reactivation(s) with proof images uploaded

### Steps
1. Run `reset.php`
2. Check `uploads/reactivation_proofs/` directory

### Expected
- Directory exists but is empty (all proof images deleted)
- Log shows: "Cleared N reactivation proof image(s)"

---

## Reactivation Flow Summary

```
┌─────────────────────────────────────────────────────────────────────────────┐
│  E-Wallet Reactivation          External Reactivation (with proof image)    │
├─────────────────────────────────────────────────────────────────────────────┤
│  1. Member submits               1. Member uploads proof image               │
│  2. Fee debited immediately      2. Member submits                           │
│  3. Cap state reset              3. status='pending'                         │
│  4. status='completed'           4. Member sends payment                     │
│                                  5. Admin reviews proof image + account      │
│                                  6. Admin confirms/rejects                   │
│                                  7. If confirmed: cap reset                  │
│                                  8. status='completed'                       │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## Rollback (if needed)

Replace modified files with `_current.php` backups in `temp/lifetime_capping/phase4/`.
