# Phase 1 QA Test — Royalty Bonus Pool (Schema + Settings)

## What We're Testing

Phase 1 added:
1. A new database table called `royalty_pool` that tracks monthly repeat purchase sales
2. New settings that control how the royalty bonus works (pool rate, rank rates, qualification gates)
3. An admin settings page where you can configure all of these
4. Validation that ensures rank rates always add up to 100%

**Important:** Phase 1 does NOT change how commissions are paid yet. The old royalty bonus still works exactly as before. We're just adding the storage and configuration for the new pool model.

---

## Before You Start

- You should be logged in as **admin**
- The site should be running normally
- If you just ran migration 032, everything is already set up

---

## Test 1: Verify the Database Table Exists

**What to do:**

Open phpMyAdmin (or your database tool) and look for a table named `royalty_pool`. Or run this SQL:

```sql
SHOW TABLES LIKE 'royalty_pool';
SELECT * FROM royalty_pool;
```

**Expected result:**

You should see the table exists with one row:
| id | period_date | total_sales | pool_amount | pool_rate | status | created_at | updated_at |
|----|-------------|-------------|-------------|-----------|--------|------------|------------|
| 1 | 2026-07-01 | 0.00 | 0.00 | 10.00 | open | ... | ... |

**If it fails:** Migration 032 was not applied. Run:
```sql
SOURCE migrations/032_royalty_bonus_pool.sql;
```
or paste the SQL content into phpMyAdmin's SQL tab.

---

## Test 2: Verify New Settings Exist in the Database

**What to do:**

In phpMyAdmin, browse the `settings` table and look for keys starting with `royalty_`. Or run:

```sql
SELECT key_name, value FROM settings WHERE key_name LIKE 'royalty_%' ORDER BY key_name;
```

**Expected result:**

You should see all of these settings:

| key_name | value |
|----------|-------|
| royalty_chairman_group_pct | 12 |
| royalty_chairman_rate | 25 |
| royalty_chairman_repeat_pct | 20 |
| royalty_chm_dir_legs | 3 |
| royalty_dir_mgr_legs | 3 |
| royalty_director_group_pct | 10 |
| royalty_director_rate | 25 |
| royalty_director_repeat_pct | 15 |
| royalty_enabled | 0 |
| royalty_manager_group_pct | 5 |
| royalty_manager_rate | 25 |
| royalty_manager_repeat_pct | 10 |
| royalty_mgr_sup_legs | 3 |
| royalty_min_pool | 500.00 |
| royalty_pool_rate | 10.00 |
| royalty_qa_directs | 3 |
| royalty_qa_group_pv | 1000 |
| royalty_qa_personal_pv | 200 |
| royalty_spv_directs | 10 |
| royalty_spv_qa_legs | 5 |
| royalty_supervisor_group_pct | 3 |
| royalty_supervisor_rate | 25 |
| royalty_supervisor_repeat_pct | 5 |

**Note:** The settings ending in `_group_pct` and `_repeat_pct` are from the OLD waterfall model (migration 031). They are still there and still work. The NEW settings are the ones we added in Phase 1: `royalty_pool_rate`, `royalty_min_pool`, `royalty_*_rate`, `royalty_spv_*`, `royalty_mgr_*`, `royalty_dir_*`, `royalty_chm_*`.

**If it fails:** Run migration 032 again or manually INSERT the missing settings.

---

## Test 3: Open the Admin Settings Page

**What to do:**

1. Log in as admin
2. Click the ⚙️ gear icon in the top bar to open the Settings offcanvas
3. Click **"Royalty Bonus"** in the sidebar

**Expected result:**

You should see the Royalty Bonus settings page with these sections:

**A. Enable/Disable toggle** — a switch labeled "Enable Royalty Bonus"

**B. Pool Configuration** — a blue section with:
- "Pool Rate (% of monthly repeat sales)" — should show **10.00**
- "Minimum Pool Threshold (₱)" — should show **500.00**

**C. Rank Rate Allocation** — a green section with 4 inputs:
- 🥉 Supervisor — **25**
- 🥈 Manager — **25**
- 🥇 Director — **25**
- 👑 Chairman — **25**
- Below the inputs: **Sum: 100.00%** in green text

**D. Rank Qualification Gates** — a yellow section with a table:
- QA: Min Directs = 3
- QA: Personal PV Gate = 200
- QA: Group PV Gate = 1000
- Supervisor: Min Directs = 10
- Supervisor: Min QA Legs = 5
- Manager: Min Supervisor Legs = 3
- Director: Min Manager Legs = 3
- Chairman: Min Director Legs = 3

**E. Save button** — labeled "💾 Save Settings"

**If it fails:** The settings page may not load properly. Check for PHP errors in the browser or the PHP error log.

---

## Test 4: Rank Rate Sum Validation (Live Display)

**What to do:**

On the Royalty Bonus settings page, look at the Rank Rate Allocation section.

1. Notice the **Sum: 100.00%** text below the inputs — it should be **green**
2. Change the **Supervisor** value from **25** to **40**
3. Watch the Sum text change

**Expected result:**

- Sum should now show **115.00%** (40 + 25 + 25 + 25)
- The Sum text should turn **red**
- The Save button should change text to **"⚠️ Rates must sum to 100%"**

**What to do next:**

4. Try clicking the **Save button** while the sum is 115%

**Expected result:**

- A popup alert should appear saying: **"Rank rates must sum to 100%. Current sum: 115.00"**
- The page should NOT reload (form submission is blocked)

**What to do next:**

5. Change the values back to **25, 25, 25, 25**
6. Verify Sum shows **100.00%** in green
7. Verify Save button shows **"💾 Save Settings"**

**If it fails:** Check the browser console for JavaScript errors. Make sure the `royalty-rank-rate` class is on the input elements.

---

## Test 5: Rank Rate Validation on Save (Backend)

**What to do:**

1. Set the rank rates to: **Supervisor=30, Manager=30, Director=30, Chairman=30** (sum = 120)
2. Bypass the JavaScript validation by disabling JavaScript in your browser (or use curl)
3. Click Save

**Expected result:**

- The page should reload and show a red error message: **"Rank rates must sum to 100 (current: 120). No settings saved."**
- The values in the database should remain unchanged (still 25, 25, 25, 25)

**How to verify the database was NOT changed:**

Run in phpMyAdmin:
```sql
SELECT key_name, value FROM settings WHERE key_name IN ('royalty_supervisor_rate', 'royalty_manager_rate', 'royalty_director_rate', 'royalty_chairman_rate');
```

All values should still be **25**.

**What to do next:**

4. Set the rates back to **25, 25, 25, 25** (sum = 100)
5. Click Save
6. Verify the green success message: **"Settings saved."**
7. Verify the database now has the correct values:

```sql
SELECT key_name, value FROM settings WHERE key_name IN ('royalty_supervisor_rate', 'royalty_manager_rate', 'royalty_director_rate', 'royalty_chairman_rate');
```

All should still be **25**.

**If it fails:** Check `AdminController.php` around line 600 for the validation logic:
```php
if ($group === 'royalty') {
    // ... sum check ...
    if (abs($sum - 100) > 0.01) {
        flash('error', ...);
        redirect(...);
    }
}
```

---

## Test 6: Save Each Setting Individually

**What to do:**

For each setting below, change the value, click Save, then verify the change stuck:

| Setting | Change to | Save? | Verify via phpMyAdmin |
|---------|-----------|-------|----------------------|
| Pool Rate | 5.00 | ✅ | `SELECT value FROM settings WHERE key_name = 'royalty_pool_rate'` → **5.00** |
| Min Pool Threshold | 1000.00 | ✅ | `SELECT value FROM settings WHERE key_name = 'royalty_min_pool'` → **1000.00** |
| QA Min Directs | 5 | ✅ | `SELECT value FROM settings WHERE key_name = 'royalty_qa_directs'` → **5** |
| QA Personal PV | 500 | ✅ | `SELECT value FROM settings WHERE key_name = 'royalty_qa_personal_pv'` → **500** |
| QA Group PV | 2000 | ✅ | `SELECT value FROM settings WHERE key_name = 'royalty_qa_group_pv'` → **2000** |
| Supervisor Min Directs | 15 | ✅ | `SELECT value FROM settings WHERE key_name = 'royalty_spv_directs'` → **15** |
| Supervisor Min QA Legs | 8 | ✅ | `SELECT value FROM settings WHERE key_name = 'royalty_spv_qa_legs'` → **8** |
| Manager Min Sup Legs | 5 | ✅ | `SELECT value FROM settings WHERE key_name = 'royalty_mgr_sup_legs'` → **5** |
| Director Min Mgr Legs | 5 | ✅ | `SELECT value FROM settings WHERE key_name = 'royalty_dir_mgr_legs'` → **5** |
| Chairman Min Dir Legs | 5 | ✅ | `SELECT value FROM settings WHERE key_name = 'royalty_chm_dir_legs'` → **5** |

**After testing everything**, restore the defaults:
| Setting | Restore to |
|---------|-----------|
| Pool Rate | 10.00 |
| Min Pool | 500.00 |
| QA Min Directs | 3 |
| QA Personal PV | 200 |
| QA Group PV | 1000 |
| Supervisor Min Directs | 10 |
| Supervisor Min QA Legs | 5 |
| Manager Min Sup Legs | 3 |
| Director Min Mgr Legs | 3 |
| Chairman Min Dir Legs | 3 |
| All Rank Rates | 25 |

**If any save fails:** The error message at the top of the page will tell you what went wrong. Common issues:
- Value out of allowed range (e.g., negative number)
- The rank rates don't sum to 100 (you changed a rank rate without fixing the others)

---

## Test 7: Verify Old Settings Are NOT Affected

**What to do:**

The old waterfall royalty settings should still be in the database with their original values:

```sql
SELECT key_name, value FROM settings WHERE key_name IN (
  'royalty_supervisor_group_pct', 'royalty_supervisor_repeat_pct',
  'royalty_manager_group_pct', 'royalty_manager_repeat_pct',
  'royalty_director_group_pct', 'royalty_director_repeat_pct',
  'royalty_chairman_group_pct', 'royalty_chairman_repeat_pct',
  'royalty_enabled'
);
```

**Expected result:**

| key_name | value |
|----------|-------|
| royalty_supervisor_group_pct | 3 |
| royalty_supervisor_repeat_pct | 5 |
| royalty_manager_group_pct | 5 |
| royalty_manager_repeat_pct | 10 |
| royalty_director_group_pct | 10 |
| royalty_director_repeat_pct | 15 |
| royalty_chairman_group_pct | 12 |
| royalty_chairman_repeat_pct | 20 |
| royalty_enabled | (whatever you set it to) |

These settings are still used by the old waterfall royalty code and should NOT be removed.

**If it fails:** Someone may have deleted these settings. They need to be re-inserted.

---

## Test 8: Verify Existing Features Still Work

**What to do:**

Phase 1 should NOT break anything. Verify these still work:

1. **Login as admin** — should work normally
2. **Login as a member** — should work normally
3. **Member registration** — use a demo code to register a new member
4. **Dashboard** — both admin and member dashboards should load
5. **Other settings tabs** — click each tab in the settings sidebar:
   - Site Basics
   - Maintenance & Security
   - Compensation Plan
   - Payments
   - E-Wallet Transfers
   - Payout Methods
   - Change Password
   - Daily Cap Reset
   - System Overview

**Expected result:**

Everything should work exactly as before Phase 1. No errors, no broken pages, no missing data.

**If something is broken:** The most likely cause is a PHP syntax error in the files we modified. Check the files:
- `controllers/AdminController.php`
- `views/admin/settings.php`
- `install.sql` (only matters for fresh installs)
- `reset.php` (only matters when running reset)

---

## Test 9: Run reset.php (Optional — Only If You Want To)

**⚠️ Warning:** This deletes ALL member data, commissions, and resets the database. Only do this if you have a test environment with no real members.

**What to do:**

1. Navigate to `http://localhost/altas/reset.php`
2. Type `RESET` in the confirmation field
3. Click the reset button

**Expected result:**

- The page should show a success message with green checkmarks
- One of the log lines should say: **"Cleared royalty pool"**
- Another should say: **"Inserted open royalty pool row for current month"**

**Verify the pool was re-created:**

```sql
SELECT * FROM royalty_pool;
```

You should see a fresh row with `total_sales = 0.00` and `status = 'open'` for the current month.

**If it fails:** Check `reset.php` around lines 90–95 and 165–170 for the royalty pool handling code.

---

## Test 10: Verify Migration is Idempotent (Rerun-Safe)

**What to do:**

Run migration 032 a second time:

```sql
SOURCE migrations/032_royalty_bonus_pool.sql;
```
(or paste and execute in phpMyAdmin)

**Expected result:**

- No errors (errors are suppressed by `IF NOT EXISTS` and `INSERT IGNORE`)
- The `royalty_pool` table still has exactly 1 row (or 2 if a new month started)
- All settings still have their current values (not overwritten)

**Why this matters:** Migrations should be safe to run multiple times. This is critical for production deployments where migrations may be applied automatically.

**If it fails:** Check that the migration uses `CREATE TABLE IF NOT EXISTS` and `INSERT IGNORE` instead of plain `CREATE TABLE` and `INSERT`.

---

## Summary Checklist

| Test | Description | Pass/Fail |
|------|-------------|-----------|
| 1 | royalty_pool table exists with open row | ⬜ |
| 2 | All new settings exist in DB with correct defaults | ⬜ |
| 3 | Admin settings page loads with Royalty Bonus tab | ⬜ |
| 4 | Rank rate live sum display turns red + blocks submit when ≠ 100 | ⬜ |
| 5 | Backend validation rejects save when rates ≠ 100 | ⬜ |
| 6 | Each setting saves and persists correctly | ⬜ |
| 7 | Old waterfall royalty settings are untouched | ⬜ |
| 8 | No regressions — login, register, dashboards, other tabs all work | ⬜ |
| 9 | reset.php clears + re-creates royalty pool | ⬜ |
| 10 | Migration is safe to re-run (idempotent) | ⬜ |

**Tester Name:** \_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_
**Date:** \_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_\_
**Result:** ⬜ All Passed &nbsp; ⬜ Some Failed (see notes)
