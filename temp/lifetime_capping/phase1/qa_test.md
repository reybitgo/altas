# QA Tester Guide — Phase 1: Lifetime Income Capping Foundation

**Version:** v1.0  
**Date:** 2026-05-26  
**System:** Altas Farm MLM Binary System  
**Phase:** 1 of 6 (Database Schema & Package Settings Foundation)

---

## Table of Contents

1. [What is Phase 1 Testing?](#1-what-is-phase-1-testing)
2. [Prerequisites](#2-prerequisites)
3. [Test Environment Setup](#3-test-environment-setup)
4. [Test Cases](#4-test-cases)
   - 4.1 [Database Schema Verification](#41-database-schema-verification)
   - 4.2 [Package CRUD Operations](#42-package-crud-operations)
   - 4.3 [UI/UX Validation](#43-uiux-validation)
   - 4.4 [Migration Script Testing](#44-migration-script-testing)
   - 4.5 [Regression Testing](#45-regression-testing)
5. [Bug Reporting Template](#5-bug-reporting-template)
6. [Pass/Fail Criteria](#6-passfail-criteria)
7. [FAQ](#7-faq)

---

## 1. What is Phase 1 Testing?

Phase 1 lays the **foundation** for Lifetime Income Capping and Daily Fixed Income. Think of it like building the basement of a house — you can't see the finished rooms yet, but the structural supports must be perfect.

### What's Being Tested
| Component | What It Does |
|-----------|-------------|
| **Database Schema** | New tables and columns to store cap/DFI/reactivation data |
| **Package Settings** | Admin can configure cap multiplier, reactivation fee, DFI rates |
| **Migration Script** | Upgrades existing databases without breaking anything |
| **Stub Services** | Placeholder files that prevent crashes (will be filled in Phase 2+) |

### What's NOT in Phase 1 (Don't Test These Yet)
- ❌ Cap enforcement (stops earnings at limit) — Phase 2
- ❌ Daily Fixed Income payouts — Phase 3
- ❌ Reactivation workflow — Phase 4
- ❌ Member dashboard widgets — Phase 5
- ❌ Admin monitoring pages — Phase 6

> **Rule of Thumb:** If it involves money actually moving or caps being enforced, it's NOT in Phase 1.

---

## 2. Prerequisites

Before you start testing, ensure you have:

### Required Access
- [ ] Admin login credentials (username: `admin`, password: `Admin@1234`)
- [ ] Database access (phpMyAdmin, MySQL CLI, or similar)
- [ ] File system access (FTP, SFTP, or direct server access)

### Required Knowledge
- [ ] Basic SQL (SELECT, DESCRIBE, SHOW)
- [ ] How to use browser DevTools (F12 → Console, Network tabs)
- [ ] How to clear browser cache (Ctrl+Shift+R or Cmd+Shift+R)

### Required Tools
| Tool | Purpose | Free? |
|------|---------|-------|
| Web Browser (Chrome/Firefox) | UI testing | Yes |
| Browser DevTools (F12) | Check for JS errors, network requests | Yes |
| phpMyAdmin or MySQL CLI | Verify database schema | Yes |
| Text Editor (VS Code, Notepad++) | Check file contents | Yes |

---

## 3. Test Environment Setup

### Step 1: Backup (CRITICAL)

**Before touching anything, backup your database:**

```bash
# Via command line
mysqldump -u your_username -p u938213108_altas_db > backup_before_phase1.sql

# Or use phpMyAdmin → Export → Quick → Go
```

> ⚠️ **Never skip this step.** If something breaks, you need a way back.

### Step 2: Fresh Install Test

**Test A: Clean Installation**

1. Create a **new empty database** (e.g., `altas_test_phase1`)
2. Run the new schema:
   ```bash
   mysql -u your_username -p altas_test_phase1 < install_v2.sql
   ```
3. Verify the database was created successfully

### Step 3: Migration Test

**Test B: Existing Database Upgrade**

1. Use your **existing** database (the one with real data)
2. Run the migration:
   ```
   Visit: https://yoursite.com/migrate_v2.php
   ```
3. Check the visual output for green checkmarks

---

## 4. Test Cases

### 4.1 Database Schema Verification

**Purpose:** Ensure all new tables, columns, and indexes exist exactly as specified.

#### Test 1.1: Verify New Tables Exist

**Steps:**
1. Open phpMyAdmin or MySQL CLI
2. Run: `SHOW TABLES;`

**Expected Result:**
```
+---------------------------+
| Tables_in_u938213108_altas_db |
+---------------------------+
| commissions               |
| daily_fixed_income_log    |  ← NEW
| ewallet_ledger            |
| package_indirect_levels   |
| packages                  |
| payout_requests           |
| reactivations             |  ← NEW
| reg_codes                 |
| settings                  |
| users                     |
+---------------------------+
```

**Pass Criteria:** Both `daily_fixed_income_log` and `reactivations` appear.

---

#### Test 1.2: Verify New Columns in `packages` Table

**Steps:**
1. Run: `DESCRIBE packages;`

**Expected Result:**
```
+--------------------------+------------------+------+-----+---------+----------------+
| Field                    | Type             | Null | Key | Default | Extra          |
+--------------------------+------------------+------+-----+---------+----------------+
| id                       | int unsigned     | NO   | PRI | NULL    | auto_increment |
| name                     | varchar(80)      | NO   |     | NULL    |                |
| entry_fee                | decimal(12,2)    | NO   |     | NULL    |                |
| pairing_bonus            | decimal(12,2)    | NO   |     | NULL    |                |
| daily_pair_cap           | tinyint unsigned | NO   |     | 3       |                |
| direct_ref_bonus         | decimal(12,2)    | NO   |     | 0.00    |                |
| lifetime_cap_multiplier  | decimal(5,2)     | NO   |     | 3.00    |                | ← NEW
| reactivation_fee         | decimal(12,2)    | NO   |     | 0.00    |                | ← NEW
| reactivation_window_days | int              | NO   |     | 15      |                | ← NEW
| daily_fixed_income       | decimal(12,2)    | NO   |     | 0.00    |                | ← NEW
| daily_fixed_income_days  | int              | NO   |     | 90      |                | ← NEW
| status                   | enum(...)        | NO   |     | active  |                |
| created_at               | timestamp        | YES  |     | CURRENT_TIMESTAMP |      |
+--------------------------+------------------+------+-----+---------+----------------+
```

**Pass Criteria:** All 5 new columns appear with correct types and defaults.

---

#### Test 1.3: Verify New Columns in `users` Table

**Steps:**
1. Run: `DESCRIBE users;`

**Expected Result — New Columns:**
```
+--------------------------+---------------+------+-----+---------+-------+
| Field                    | Type          | Null | Key | Default | Extra |
+--------------------------+---------------+------+-----+---------+-------+
| lifetime_earned          | decimal(14,2) | NO   |     | 0.00    |       | ← NEW
| cap_status               | enum(...)     | NO   |     | active  |       | ← NEW
| capped_at                | timestamp     | YES  |     | NULL    |       | ← NEW
| last_reactivation_at     | timestamp     | YES  |     | NULL    |       | ← NEW
| dfi_days_used            | int unsigned  | NO   |     | 0       |       | ← NEW
| dfi_active               | tinyint(1)    | NO   |     | 1       |       | ← NEW
+--------------------------+---------------+------+-----+---------+-------+
```

**Pass Criteria:** All 6 new columns appear.

---

#### Test 1.4: Verify New Column in `commissions` Table

**Steps:**
1. Run: `DESCRIBE commissions;`

**Expected Result:**
```
| cap_deduction  | decimal(12,2) | NO   |     | 0.00    |       | ← NEW
| type           | enum(...)      | NO   |     | NULL    |       | (expanded: 'daily_fixed_income' added)
```

**Pass Criteria:** `cap_deduction` exists AND `type` enum includes `'daily_fixed_income'`.

---

#### Test 1.5: Verify Indexes

**Steps:**
1. Run: `SHOW INDEX FROM users;`
2. Run: `SHOW INDEX FROM daily_fixed_income_log;`
3. Run: `SHOW INDEX FROM reactivations;`

**Expected Results:**

**users table:**
```
| idx_cap_status | cap_status, capped_at           | ← NEW
| idx_dfi_active | dfi_active, dfi_days_used       | ← NEW
```

**daily_fixed_income_log table:**
```
| uq_user_day    | user_id, day_number (UNIQUE)    | ← NEW
| idx_user_date  | user_id, created_at             | ← NEW
```

**reactivations table:**
```
| idx_user       | user_id, created_at               | ← NEW
| idx_status     | status, created_at                | ← NEW
```

**Pass Criteria:** All indexes exist with correct columns.

---

#### Test 1.6: Verify Seed Data

**Steps:**
1. Run: `SELECT * FROM packages WHERE id = 1;`

**Expected Result:**
```
| id | name    | entry_fee | pairing_bonus | daily_pair_cap | direct_ref_bonus | lifetime_cap_multiplier | reactivation_fee | reactivation_window_days | daily_fixed_income | daily_fixed_income_days | status | created_at          |
|----|---------|-----------|---------------|----------------|------------------|-----------------------|------------------|--------------------------|--------------------|-------------------------|--------|---------------------|
| 1  | Starter | 10000.00  | 2000.00       | 3              | 500.00           | 3.00                  | 10000.00         | 15                       | 100.00             | 90                      | active | 2026-05-26 ...      |
```

**Pass Criteria:**
- `lifetime_cap_multiplier` = 3.00
- `reactivation_fee` = 10000.00
- `reactivation_window_days` = 15
- `daily_fixed_income` = 100.00
- `daily_fixed_income_days` = 90

---

#### Test 1.7: Verify Settings

**Steps:**
1. Run: `SELECT * FROM settings WHERE key_name = 'dfi_enabled';`

**Expected Result:**
```
| key_name    | value | updated_at          |
|-------------|-------|---------------------|
| dfi_enabled | 1     | 2026-05-26 ...      |
```

**Pass Criteria:** `dfi_enabled` exists with value `1`.

---

### 4.2 Package CRUD Operations

**Purpose:** Ensure admin can create, read, update packages with all v2 fields.

#### Test 2.1: Create New Package with v2 Fields

**Steps:**
1. Log in as admin
2. Navigate to: **Packages** (sidebar menu)
3. Fill in the form:
   - Name: `Test Pro Package`
   - Entry Fee: `20000`
   - Pairing Bonus: `3000`
   - Daily Pair Cap: `5`
   - Direct Referral: `1000`
   - **Cap Multiplier:** `4` (new field)
   - **Reactivation Fee:** `15000` (new field)
   - **Reactivation Window:** `30` (new field)
   - **Daily Fixed Income:** `200` (new field)
   - **Max DFI Days:** `120` (new field)
   - Status: `Active`
   - Indirect Levels: Set L1=500, L2=300, rest=0
4. Click **"➕ Create Package"**

**Expected Result:**
- Success toast: "Package created with v2 settings."
- Package appears in list with all values correct
- Auto-cap preview shows: `₱80,000.00` (20000 × 4)

**Verify in Database:**
```sql
SELECT * FROM packages WHERE name = 'Test Pro Package';
```

---

#### Test 2.2: Edit Existing Package

**Steps:**
1. In package list, click **Edit** on "Test Pro Package"
2. Change:
   - Cap Multiplier: `4` → `5`
   - Daily Fixed Income: `200` → `250`
3. Click **"💾 Update Package"**

**Expected Result:**
- Success toast: "Package updated with v2 settings."
- Cap preview updates to `₱100,000.00` (20000 × 5)
- DFI shows `₱250/d`

**Verify in Database:**
```sql
SELECT lifetime_cap_multiplier, daily_fixed_income 
FROM packages WHERE name = 'Test Pro Package';
-- Should return: 5.00, 250.00
```

---

#### Test 2.3: Validation Errors

**Steps:**
1. Create new package
2. Set **Cap Multiplier** to `0.5` (below minimum of 1.0)
3. Submit

**Expected Result:**
- Error flash: "Lifetime cap multiplier must be at least 1.0."
- Package NOT created

**Repeat for:**
- Reactivation Window = `0` → Error: "must be at least 1 day"
- Daily Fixed Income = `-50` → Error: "cannot be negative"
- Max DFI Days = `0` → Error: "must be at least 1"

---

#### Test 2.4: Cap Preview Auto-Calculation

**Steps:**
1. Start creating a new package
2. Type `15000` in **Entry Fee**
3. Type `3.5` in **Cap Multiplier**

**Expected Result:**
- Cap preview instantly updates to `₱52,500.00`
- No page reload required

**Test Edge Cases:**
- Entry Fee = `0` → Preview shows `₱0.00`
- Clear Entry Fee field → Preview shows `₱0.00` (no crash)
- Cap Multiplier = empty → Preview shows `₱0.00` (no crash)

---

#### Test 2.5: Package List Display

**Steps:**
1. View package list with multiple packages

**Expected Result:**
- Each row shows: Name, Entry, Pair, **Cap** (calculated), **DFI** (if enabled)
- Cap column shows: `₱30,000.00` with `3× entry` subtext
- DFI column shows: `₱100/d` with `90 days` subtext (or `—` if disabled)

---

### 4.3 UI/UX Validation

**Purpose:** Ensure the interface is intuitive and visually correct.

#### Test 3.1: Visual Section Separation

**Steps:**
1. Open package create/edit form

**Expected Result:**
- **Basic Info** section: Standard white/light background
- **Lifetime Income Capping** section: Purple-tinted border (`var(--purple-border)`)
- **Daily Fixed Income** section: Pink-tinted border (`var(--pink-border)`)

**Visual Checklist:**
- [ ] Purple section has shield icon (🛡️)
- [ ] Pink section has calendar icon (📅)
- [ ] Sections are visually distinct — no confusion between them

---

#### Test 3.2: Responsive Layout

**Steps:**
1. Open package form on **desktop** (1920px wide)
2. Resize to **tablet** (768px wide)
3. Resize to **mobile** (375px wide)

**Expected Result:**
- Desktop: Two-column layout for Cap and DFI fields
- Tablet: Two-column layout maintained
- Mobile: Single column, fields stack vertically
- No horizontal scrolling on any size

---

#### Test 3.3: Form Field Behavior

**Steps:**
1. Click into **Cap Multiplier** field
2. Type `2.5`
3. Press Tab to move to next field

**Expected Result:**
- Value accepted: `2.5`
- Cap preview updates immediately
- No JavaScript errors in DevTools Console (F12)

**Test with DevTools open:**
- Open F12 → Console
- Interact with all form fields
- **Pass Criteria:** Zero red error messages in Console

---

### 4.4 Migration Script Testing

**Purpose:** Ensure existing databases upgrade safely.

#### Test 4.1: First-Time Migration

**Steps:**
1. Start with existing database (pre-v2)
2. Visit: `https://yoursite.com/migrate_v2.php`
3. Read the warning banner
4. Type `RESET` in confirmation field (wait — that's the reset.php tool!)

**Correction:** The migration runs automatically on POST. Simply visit the page and review output.

**Expected Result:**
- Visual output shows green checkmarks for each step
- Steps include:
  - ✓ Altering packages table
  - ✓ Backfilled N package(s) with default v2 values
  - ✓ Altering users table
  - ✓ Added cap_status and dfi_active indexes
  - ✓ Creating reactivations table
  - ✓ Creating daily_fixed_income_log table
  - ✓ Backfilled lifetime_earned for N member(s)

---

#### Test 4.2: Idempotency (Run Twice)

**Steps:**
1. Run migration once (should succeed)
2. Immediately run migration again

**Expected Result:**
- Second run also succeeds
- No "column already exists" errors
- No duplicate data

**Verify:**
```sql
SELECT COUNT(*) FROM daily_fixed_income_log;
-- Should be 0 (no duplicate entries created)
```

---

#### Test 4.3: Backfill Verification

**Steps:**
1. Before migration, note a member's total earnings:
   ```sql
   SELECT user_id, SUM(amount) 
   FROM commissions 
   WHERE status='credited' 
   GROUP BY user_id LIMIT 1;
   ```
2. Run migration
3. Check same member:
   ```sql
   SELECT lifetime_earned FROM users WHERE id = [user_id];
   ```

**Expected Result:**
- `lifetime_earned` equals the sum from step 1

---

#### Test 4.4: Capped Member Detection

**Steps:**
1. Before migration, find a member whose earnings exceed 3× entry fee:
   ```sql
   SELECT u.id, u.username, SUM(c.amount) as earned, p.entry_fee
   FROM users u
   JOIN commissions c ON c.user_id = u.id
   JOIN packages p ON p.id = u.package_id
   WHERE u.role = 'member' AND c.status = 'credited'
   GROUP BY u.id
   HAVING earned >= (p.entry_fee * 3);
   ```
2. Run migration
3. Check their cap_status:
   ```sql
   SELECT cap_status, capped_at FROM users WHERE id = [user_id];
   ```

**Expected Result:**
- `cap_status` = `capped`
- `capped_at` = current timestamp (not NULL)

---

### 4.5 Regression Testing

**Purpose:** Ensure existing features still work after v2 changes.

#### Test 5.1: Existing Package Operations

**Steps:**
1. Edit the "Starter" package
2. Change only the name: `Starter` → `Starter Basic`
3. Save

**Expected Result:**
- Success message
- All v1 fields (entry_fee, pairing_bonus, etc.) unchanged
- All v2 fields retain their default values

---

#### Test 5.2: Member Registration

**Steps:**
1. Log out
2. Register a new member using the demo code `DEMO-STAR-TKIT`
3. Complete registration

**Expected Result:**
- Registration succeeds
- New user row has:
  - `lifetime_earned` = 0.00
  - `cap_status` = `active`
  - `dfi_days_used` = 0
  - `dfi_active` = 1

**Verify:**
```sql
SELECT lifetime_earned, cap_status, dfi_days_used, dfi_active 
FROM users WHERE username = '[new_username]';
```

---

#### Test 5.3: Existing Member Dashboard

**Steps:**
1. Log in as an existing member
2. View dashboard

**Expected Result:**
- Page loads without errors
- No new widgets visible yet (Phase 5 adds those)
- Existing stats (balance, pairs, earnings) display correctly

**Check DevTools Console:**
- Zero red JavaScript errors

---

#### Test 5.4: Binary Pairing Still Works

**Steps:**
1. Register a new member under an existing member
2. Check that the sponsor's `left_count` or `right_count` incremented

**Expected Result:**
- Binary placement works normally
- Pair counters update
- No cap enforcement yet (stubs allow everything)

---

#### Test 5.5: Payout Request Still Works

**Steps:**
1. As a member, request a payout
2. As admin, approve and complete the payout

**Expected Result:**
- Full payout workflow completes
- E-wallet balance updates correctly
- No interference from v2 stubs

---

## 5. Bug Reporting Template

When you find an issue, use this format:

```markdown
### Bug Report

**Tester:** [Your Name]
**Date:** [YYYY-MM-DD]
**Test Case:** [e.g., 2.1 Create New Package]
**Severity:** [Critical / High / Medium / Low]

**Steps to Reproduce:**
1. [Step 1]
2. [Step 2]
3. [Step 3]

**Expected Result:**
[What should happen]

**Actual Result:**
[What actually happened]

**Screenshots:**
[Attach if applicable]

**Browser Console Errors:**
```
[Paste any red error messages from F12 → Console]
```

**Database State:**
```sql
[Run any relevant queries and paste results]
```

**Environment:**
- Browser: [Chrome/Firefox/Safari] v[X.X]
- OS: [Windows/Mac/Linux]
- Server: [PHP version, MySQL version]
```

---

## 6. Pass/Fail Criteria

### Phase 1 PASSES if ALL of these are true:

| # | Criteria | How to Verify |
|---|----------|---------------|
| 1 | Database has 2 new tables | `SHOW TABLES` → `daily_fixed_income_log` and `reactivations` exist |
| 2 | `packages` has 5 new columns | `DESCRIBE packages` → all v2 fields present |
| 3 | `users` has 6 new columns | `DESCRIBE users` → all v2 fields present |
| 4 | Indexes created | `SHOW INDEX` → new indexes exist |
| 5 | Seed data correct | `SELECT * FROM packages WHERE id=1` → v2 values match spec |
| 6 | Can create package with v2 fields | UI test → package saves, appears in list |
| 7 | Can edit package v2 fields | UI test → edits persist after reload |
| 8 | Validation works | Enter invalid values → appropriate error messages |
| 9 | Cap preview auto-calculates | Change entry fee → preview updates instantly |
| 10 | Migration runs clean | `migrate_v2.php` → all green checkmarks |
| 11 | Migration is idempotent | Run twice → no errors, no duplicates |
| 12 | Existing features work | Register member, request payout, view dashboard → all work |
| 13 | No PHP fatal errors | Check server error logs → zero fatal errors |
| 14 | No JS console errors | F12 → Console → zero red messages on all pages |

### Phase 1 FAILS if ANY of these are true:

- ❌ Database schema missing any required table/column/index
- ❌ Cannot create or edit packages with v2 fields
- ❌ Migration crashes or leaves database in inconsistent state
- ❌ Existing features (registration, payout, dashboard) broken
- ❌ PHP fatal errors in server logs
- ❌ JavaScript errors preventing form interaction

---

## 7. FAQ

### Q: I see "Class CapEngine not found" error. What do I do?
**A:** Ensure the stub files are copied to `core/`:
```bash
cp CapEngine.php DailyFixedIncome.php Reactivation.php /path/to/site/core/
```
Or check that `index.php` uses the conditional require logic.

### Q: The migration says "column already exists" on second run. Is that bad?
**A:** No — the migration is idempotent. But if you see this, it means the migration isn't properly catching the error. Report as a Medium-severity bug.

### Q: Cap preview shows "₱0.00" even with values entered. Bug?
**A:** Check browser DevTools Console for JavaScript errors. If no errors, check that the `entry_fee` field has `name="entry_fee"` and the JS selector matches.

### Q: Can I test DFI payouts in Phase 1?
**A:** No — DFI payout logic is a stub in Phase 1. It will always return "disabled." Test the configuration fields only.

### Q: Can I test reactivation in Phase 1?
**A:** No — reactivation workflow is a stub in Phase 1. The UI for it doesn't exist yet (Phase 4).

### Q: What if I find a bug that's not in the test cases?
**A:** Report it anyway! Use the bug template. Even "cosmetic" issues (layout misalignment, typos) are valid reports.

### Q: Do I need to test on mobile?
**A:** Yes — at minimum, check that the package form is usable on mobile (375px width). Full responsive testing happens in Phase 5.

### Q: The package list shows weird colors. Is that right?
**A:** The v2 package list uses color coding: purple for cap info, pink for DFI info. If colors are missing or wrong, report as a UI bug.

---

## Quick Reference Card

| Test | Page/URL | What to Check |
|------|----------|---------------|
| Schema | phpMyAdmin | Tables, columns, indexes |
| Create Package | /?page=admin_packages | All v2 fields save correctly |
| Edit Package | /?page=admin_packages&edit=1 | Changes persist |
| Validation | /?page=admin_packages | Error messages for bad input |
| Migration | /migrate_v2.php | Green checkmarks, no red errors |
| Regression | /?page=dashboard | Existing features still work |
| Console | F12 anywhere | Zero red JS errors |

---

**End of Guide**

*Ready for Phase 2 when all 14 pass criteria are met.*
