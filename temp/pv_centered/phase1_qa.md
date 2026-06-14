# Phase 1 QA — PV Foundation

## Database Verification

Run these checks after migration:

```sql
-- User PV columns exist
SHOW COLUMNS FROM users LIKE '%_pv';
-- Expected: left_pv, right_pv, flushed_pv, personal_pv, group_pv

-- System setting exists
SELECT key_name, value FROM settings WHERE key_name = 'pv_per_peso_rate';
-- Expected: value = 1.0000
```

## Admin UI Tests

### Test 1: PV per Peso Rate Setting
1. Go to **Admin → System Settings**
2. Scroll to **Compensation Plan** → **PV Conversion Rate**
3. Verify "PV per Peso Rate" field shows 1.0000
4. Change to 0.5000
5. Save Settings
6. Verify in DB: `pv_per_peso_rate = 0.5000`
7. Refresh page and confirm value persists

### Test 2: Existing Commissions Unchanged
1. Register a new test member under an existing sponsor
2. Verify direct referral bonus amount is unchanged from before Phase 1
3. Verify binary pairing (if enabled) still works with count-based logic

## Regression Checks

- [ ] No PHP errors on Packages page
- [ ] No PHP errors on Settings page
- [ ] Package create/update still works
- [ ] Settings save still works
- [ ] Binary/Indirect toggle guards still function correctly
