# Phase 2 QA — PV Infrastructure (No Package PV Flow)

## Concept

- **Package purchases do NOT generate any PV flow.**
- Members do NOT gain Personal PV from their own package.
- Uplines do NOT gain Group PV from a downline's package purchase.
- PV movement begins in **Phase 5** with product purchases.

## Database Verification

```sql
-- PV transactions table exists
SHOW TABLES LIKE 'pv_transactions';

-- User PV columns exist
SHOW COLUMNS FROM users LIKE '%_pv';
-- Expected: left_pv, right_pv, flushed_pv, personal_pv, group_pv

-- No package PV rate on packages
SHOW COLUMNS FROM packages LIKE 'package_pv_rate';
-- Expected: empty result
```

## Test 1: Active Registration Does NOT Change Any PV

1. As admin, go to **Register Member**
2. Select any active package
3. Set Sponsor = an existing member (e.g., `altas01`)
4. Complete registration
5. Run verification queries:

```sql
-- New member has no PV
SELECT id, username, personal_pv, group_pv FROM users WHERE username = 'NEW_USERNAME';
-- Expected: personal_pv = 0.00, group_pv = 0.00

-- Sponsor's PV unchanged
SELECT id, username, personal_pv, group_pv FROM users WHERE username = 'altas01';
-- Expected: personal_pv unchanged, group_pv unchanged

-- No package-related PV transactions
SELECT COUNT(*) FROM pv_transactions WHERE source_user_id = (SELECT id FROM users WHERE username = 'NEW_USERNAME');
-- Expected: 0
```

## Test 2: Pending Activation Does NOT Change Any PV

1. Register a member in **referral mode** (pending activation)
2. Verify `personal_pv` = 0 and `group_pv` = 0 while pending
3. Activate the account with a registration code
4. Verify `personal_pv` and `group_pv` are still 0 after activation
5. Verify no `pv_transactions` rows were created for this member

## Test 3: Member Dashboard Shows Empty PV Stats

1. Log in as a member
2. Go to **Dashboard**
3. Verify cards show:
   - **Personal PV** = 0.00
   - **Group PV** = 0.00
4. Verify **Package PV** card is NOT shown

## Test 4: Admin User View Shows Empty PV Stats

1. As admin, go to **Members → View**
2. Verify PV stats row shows Personal PV and Group PV only
3. Verify both values are 0.00 for members with no product purchases
4. Verify Package PV stat is NOT shown

## Test 5: Existing Commissions Unchanged

1. Register a test member
2. Verify direct referral bonus amount is identical to pre-Phase 2
3. Verify binary pairing (if enabled) still uses count-based logic

## Regression Checks

- [ ] No PHP errors during registration
- [ ] No PHP errors during activation
- [ ] No PHP errors on member dashboard
- [ ] No PHP errors on admin user view
- [ ] `package_pv_rate` column no longer exists on `packages`
- [ ] `total_package_pv` column no longer exists on `users`
