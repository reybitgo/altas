# System Reset vs. Fresh Install — Alignment & Behavior Document

> **Date:** June 21, 2026
> **Files:** `install.sql` | `reset.php`
> **Purpose:** Document what happens during a database reset, what is intentionally preserved, and the change history of past gaps that have since been fixed.
>
> **Status note:** Earlier revisions of this document cataloged three gaps as open bugs (uncleared `pv_transactions`, uncleared cart/order tables, and non-reset admin PV columns). **All three are already implemented in the current `reset.php`.** Sections 3–6 below have been rewritten as a change log rather than an open-issues list, plus one newly-identified defect (orphaned repeat-purchase proof images) that *is* still open.

---

## 1. Philosophy: What Is Intentionally Preserved

`reset.php` is designed to wipe **member data and transaction history** while keeping **system configuration** intact. This is by design, not by accident.

The following are **intentionally preserved** during a reset:

| Category | Preserved? | Rationale |
|----------|-----------|-----------|
| **System Settings** | ✅ Yes | Admins configure branding, rates, fees, toggles. Resetting these would force re-configuration every test cycle. |
| **Packages** | ✅ Yes (optional) | Package structures, pricing, PV rates, and commission configs are core business logic. The `keep_packages` checkbox allows wiping them, but the default is to preserve. |
| **Products** | ✅ Yes | Product catalog is catalog data, not member data. No reset logic touches `products`. |
| **Admin Account** | ✅ Yes | The admin account (username `admin`, password `Admin@1234`) is always preserved. Counters and balances are reset to zero, but the login credentials remain. |

**Conclusion:** If you want a truly "clean slate" including settings/packages/products, you must run `install.sql` fresh (drop and recreate the database). `reset.php` is for **member data cleanup only**.

---

## 2. What Gets Cleared During Reset

### 2.1 Member Accounts & Identity

| Action | SQL | Status |
|--------|-----|--------|
| Delete all members | `DELETE FROM users WHERE role = 'member'` | ✅ Implemented |
| Reset admin counters/balances | `UPDATE users SET ... WHERE role = 'admin'` | ✅ Implemented |

### 2.2 Financial History

| Table | Cleared? | Status |
|-------|----------|--------|
| `commissions` | ✅ `DELETE FROM commissions` | ✅ Implemented |
| `ewallet_ledger` | ✅ `DELETE FROM ewallet_ledger` | ✅ Implemented |
| `payout_requests` | ✅ `DELETE FROM payout_requests` | ✅ Implemented |
| `ewallet_transfers` | ✅ `DELETE FROM ewallet_transfers` | ✅ Implemented |
| `ewallet_admin_topups` | ✅ `DELETE FROM ewallet_admin_topups` | ✅ Implemented |

### 2.3 Capping & Reactivation

| Table / Path | Cleared? | Status |
|-------|----------|--------|
| `reactivations` | ✅ `DELETE FROM reactivations` | ✅ Implemented |
| `daily_fixed_income_log` | ✅ `DELETE FROM daily_fixed_income_log` | ✅ Implemented |
| `cd_ledger` | ✅ `DELETE FROM cd_ledger` | ✅ Implemented |
| `user_cd_status` | ✅ `DELETE FROM user_cd_status` | ✅ Implemented |
| Reactivation proof images | ✅ `unlink()` from `uploads/reactivation_proofs/` | ✅ Implemented |
| **Repeat-purchase proof images** | ✅ `unlink()` from `uploads/repeat_purchase_proofs/` | ✅ **Implemented (newly added)** |

### 2.4 Registration & Codes

| Table | Cleared? | Status |
|-------|----------|--------|
| `reg_codes` | ✅ `DELETE FROM reg_codes` | ✅ Implemented |
| Fresh codes generated | ❌ **None re-seeded** | ⚠️ See note below |

> ⚠️ **Clarification:** Earlier drafts of this document claimed reset re-seeds 5 fresh reg codes via an `INSERT INTO reg_codes` loop. This is **incorrect**. `reset.php` only runs `DELETE FROM reg_codes` — after a reset there are **zero** reg codes in the DB; only `install.sql` seeds one demo code (`DEMO-STAR-TKIT`). A dead private `generate_code()` helper had previously been copy-pasted into `reset.php` but was never called; it has since been **removed** (the live helper lives in `core/helpers.php` and is used by `models/Code.php` for admin code generation, which is unaffected). If re-seeding demo codes on reset is ever desired, that behavior does not currently exist and would need to be added deliberately.

### 2.5 PV & Repeat-Purchase Data

| Table | Cleared? | Status |
|-------|----------|--------|
| `pv_transactions` | ✅ `DELETE FROM pv_transactions` | ✅ Implemented |
| `repeat_purchase_order_items` | ✅ `DELETE FROM ...` | ✅ Implemented |
| `repeat_purchase_orders` | ✅ `DELETE FROM ...` | ✅ Implemented |
| `cart_items` | ✅ `DELETE FROM ...` | ✅ Implemented |
| `carts` | ✅ `DELETE FROM ...` | ✅ Implemented |

---

## 3. Change Log: Previously-Identified Gaps (All Fixed)

These three items were flagged as open bugs in earlier revisions of this document. They have **all been implemented** in the current `reset.php`. Kept here as a historical record so the rationale isn't lost.

### 3.1 ✅ `pv_transactions` — now cleared

**Was:** All PV movement history survived reset, leaving orphaned rows referencing deleted members. The table is the source of truth for Personal/Group PV calculations.
**Fix applied:** `DELETE FROM pv_transactions` (now at ~line 88) + added to the auto-increment reset loop.

### 3.2 ✅ Repeat-purchase cart/order tables — now cleared

**Was:** `carts`, `cart_items`, `repeat_purchase_orders`, `repeat_purchase_order_items` were never touched, leaving phantom carts and orders pointing at deleted members.
**Fix applied:** Four `DELETE` statements in child-before-parent order (~lines 92–101) + all four added to the auto-increment reset loop.

### 3.3 ✅ Admin PV columns — now reset

**Was:** The admin `UPDATE` reset many counters but missed all PV-related columns on `users`, so any admin-accumulated PV persisted across resets.
**Fix applied:** The admin `UPDATE` now also sets `left_pv`, `right_pv`, `paired_pv`, `paired_pv_today`, `flushed_pv`, `personal_pv`, `group_pv` to `0.00` (~lines 149–155).

### 3.4 ✅ Auto-increment reset list — now complete

The auto-increment reset loop now includes all cleared tables: `pv_transactions`, `carts`, `cart_items`, `repeat_purchase_orders`, `repeat_purchase_order_items` (line 161).

---

## 4. Open Issue: Orphaned Repeat-Purchase Proof Images

> 🔴 **Still open at the time this revision was written — verify before closing.**

The `repeat_purchase_orders` table carries a `proof_image` column (install.sql line 99, pointing at `uploads/repeat_purchase_proofs/`). `reset.php` deleted the order rows but **did not unlink the proof files on disk**, leaving the directory full of orphaned PNGs/JPGs referencing deleted orders.

**Expected fix** (mirrors the existing reactivation-proof cleanup at ~lines 105–115):

```php
// Phase 7: Clear uploaded repeat-purchase proof images
$rpProofDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'repeat_purchase_proofs';
if (is_dir($rpProofDir)) {
  $cleared = 0;
  foreach (glob($rpProofDir . '/*') as $f) {
    if (is_file($f)) {
      unlink($f);
      $cleared++;
    }
  }
  $logs[] = ['ok', "Cleared {$cleared} repeat-purchase proof image(s)"];
}
```

**Status:** Fix implemented in this revision — update this row when confirmed by testing.

---

## 5. Legacy Schema Note: `reactivation_payments` table

Migration `001_add_capping_schema.sql` creates a `reactivation_payments` table. This table was **superseded** by the `reactivations` table in the v2 schema (the migration's own comments at lines 38–40 note reactivation revenue is tracked via `payout_requests`/`reactivations` instead). The consolidated `install.sql` ships only `reactivations`, not `reactivation_payments`.

- On databases built fresh from `install.sql`: table does not exist. No action.
- On databases migrated up from migration 001: `reactivation_payments` may still exist as a dead table. `reset.php` correctly does not touch it (it's neither populated nor read by current code). Consider a one-off `DROP TABLE IF EXISTS reactivation_payments;` cleanup migration if found.

---

## 6. Schema Alignment: `install.sql` vs `reset.php` INSERT Statements

### 6.1 Starter Package Insert

| Field | install.sql Default | reset.php Re-seed | Aligned? |
|-------|---------------------|-------------------|----------|
| `name` | `'Starter'` | `'Starter'` | ✅ Yes |
| `entry_fee` | `10000.00` | `10000.00` | ✅ Yes |
| `package_pv_rate` | `100.00` | `100.00` | ✅ Yes |
| `binary_pv_pct` | `20.00` | `20.00` | ✅ Yes |
| `pairing_pv_pct` | `20.00` | `20.00` | ✅ Yes |
| `daily_pair_pv_cap` | `30000.00` | `30000.00` | ✅ Yes |
| `direct_ref_pv_pct` | `5.00` | `5.00` | ✅ Yes |
| `lifetime_cap_multiplier` | `3.00` | `3.00` | ✅ Yes |
| `reactivation_fee` | `10000.00` | `10000.00` | ✅ Yes |
| `reactivation_window_days` | `15` | `15` | ✅ Yes |
| `daily_fixed_income` | `100.00` | `100.00` | ✅ Yes |
| `daily_fixed_income_days` | `90` | `90` | ✅ Yes |
| `dfi_pv_pct` | `0.00` | `0.00` | ✅ Yes |
| `personal_pv_requirement` | `0.00` (DEFAULT) | `0.00` (DEFAULT) | ✅ Yes (implicit) |
| `status` | `'active'` | `'active'` | ✅ Yes |

**Note:** Both omit `personal_pv_requirement` explicitly, relying on the DEFAULT 0.00. If you want the seeded Starter package to have a non-zero gate, you must update both files.

### 6.2 Indirect Level Inserts

| Level | install.sql `bonus` | install.sql `pv_pct` | reset.php `bonus` | reset.php `pv_pct` | Aligned? |
|-------|---------------------|----------------------|-------------------|--------------------|----------|
| 1 | 300.00 | 3.00 | 300.00 | 3.00 | ✅ Yes |
| 2 | 200.00 | 2.00 | 200.00 | 2.00 | ✅ Yes |
| 3 | 150.00 | 1.50 | 150.00 | 1.50 | ✅ Yes |
| 4 | 100.00 | 1.00 | 100.00 | 1.00 | ✅ Yes |
| 5 | 100.00 | 1.00 | 100.00 | 1.00 | ✅ Yes |
| 6 | 50.00 | 0.50 | 50.00 | 0.50 | ✅ Yes |
| 7 | 50.00 | 0.50 | 50.00 | 0.50 | ✅ Yes |
| 8 | 50.00 | 0.50 | 50.00 | 0.50 | ✅ Yes |
| 9 | 50.00 | 0.50 | 50.00 | 0.50 | ✅ Yes |
| 10 | 50.00 | 0.50 | 50.00 | 0.50 | ✅ Yes |

### 6.3 Admin Account Insert

| Field | install.sql | reset.php | Aligned? |
|-------|-------------|-----------|----------|
| `username` | `admin` | preserved (never deleted) | ✅ Yes |
| `password_hash` | `$2y$12$...` | preserved | ✅ Yes |
| `role` | `admin` | preserved | ✅ Yes |
| `status` | `active` | preserved (UPDATE does **not** touch `status`) | ✅ Yes |
| `full_name` | `System Administrator` | preserved | ✅ Yes |
| `email` | `admin@mlm.local` | preserved | ✅ Yes |

> **Correction from earlier revisions:** The admin `UPDATE` does **not** set `status` — the admin row's `status` simply remains whatever it was before reset (normally `'active'` from the original install). Functionally fine today, but worth knowing: if the admin were ever suspended, a reset would not restore `active`.

### 6.4 Settings Seed

| Key | install.sql Value | reset.php Behavior | Aligned? |
|-----|-------------------|--------------------|----------|
| `site_name` | `'Altas Farm'` | Preserved (not reset) | ✅ Intentional |
| `site_tagline` | `'Build Your Network...'` | Preserved | ✅ Intentional |
| `min_payout` | `'500'` | Preserved | ✅ Intentional |
| `last_reset` | `''` | Reset to `''` | ✅ Yes |
| `maintenance_mode` | `'0'` | Preserved | ✅ Intentional |
| `contact_email` | `'support@altasfarm.com'` | Preserved | ✅ Intentional |
| `service_fee_gcash` | `'0'` | Preserved | ✅ Intentional |
| `service_fee_maya` | `'0'` | Preserved | ✅ Intentional |
| `service_fee_usdt_trc20` | `'5'` | Preserved | ✅ Intentional |
| `usdt_trc20_gas_fee` | `'2.50'` | Preserved | ✅ Intentional |
| `service_fee_usdt_bep20` | `'5'` | Preserved | ✅ Intentional |
| `usdt_bep20_gas_fee` | `'0.05'` | Preserved | ✅ Intentional |
| `gcash_enabled` | `'1'` | Preserved | ✅ Intentional |
| `maya_enabled` | `'1'` | Preserved | ✅ Intentional |
| `dfi_enabled` | `'1'` | Preserved | ✅ Intentional |
| `gcash_number` | `''` | Preserved | ✅ Intentional |
| `maya_number` | `''` | Preserved | ✅ Intentional |
| `usdt_trc20_address` | `''` | Preserved | ✅ Intentional |
| `usdt_bep20_address` | `''` | Preserved | ✅ Intentional |
| `default_cap_multiplier` | `'3.00'` | Preserved | ✅ Intentional |
| `reactivation_ewallet_enabled` | `'1'` | Preserved | ✅ Intentional |
| `reactivation_external_enabled` | `'1'` | Preserved | ✅ Intentional |
| `ewallet_transfer_fee` | `'0.00'` | Preserved | ✅ Intentional |
| `ewallet_min_transfer` | `'50.00'` | Preserved | ✅ Intentional |
| `ewallet_transfer_daily_limit` | `'5000.00'` | Preserved | ✅ Intentional |
| `ewallet_transfer_weekly_limit` | `'20000.00'` | Preserved | ✅ Intentional |
| `indirect_referral_enabled` | `'1'` | Preserved | ✅ Intentional |
| `binary_enabled` | `'1'` | Preserved | ✅ Intentional |
| `seat_limit` | `'0'` | Preserved | ✅ Intentional |
| `pv_per_peso_rate` | `'1.0000'` | Preserved | ✅ Intentional |

**Note:** The `personal_pv_requirement` global setting was removed in Phase 5.2 (moved to per-package `packages.personal_pv_requirement`). It does not appear in either file. This is correct.

---

## 7. Decision Log

| Decision | Rationale | Status |
|----------|-----------|--------|
| **Settings preserved** | Admins configure once; resetting them every test cycle is annoying. | ✅ Intentional — no change needed |
| **Packages preserved (default)** | Package structure is business logic, not test data. `keep_packages` checkbox gives option to wipe. | ✅ Intentional — no change needed |
| **Products preserved** | Product catalog is catalog data, not member data. | ✅ Intentional — no change needed |
| **Admin account preserved** | Admin login must survive reset. | ✅ Intentional — no change needed |
| **pv_transactions cleared** | PV movement history is member transaction data. | ✅ Implemented |
| **Cart/order tables cleared** | Cart and order data are member transaction data. | ✅ Implemented |
| **Admin PV columns reset** | PV is member-state data; admin should start at zero. | ✅ Implemented |
| **Repeat-purchase proof images cleared** | Order rows are deleted; on-disk proof files must follow to avoid orphans. | ✅ Implemented (this revision) |
| **Reg codes NOT re-seeded** | Reset only deletes; no demo codes are generated. (Dead `generate_code()` copy removed from `reset.php`.) | ✅ Documented — by design |
| **Settings NOT re-seeded to defaults** | Intentional — preserves admin config. Documented here. | ✅ Intentional — no change needed |

---

## 8. Files Modified

| File | Changes |
|------|---------|
| `reset.php` | (Already done) `DELETE FROM pv_transactions`, `DELETE FROM carts/cart_items/repeat_purchase_orders/repeat_purchase_order_items`; admin PV columns in UPDATE; all five tables in auto-increment loop. |
| `reset.php` | **(This revision)** Added `unlink()` cleanup for `uploads/repeat_purchase_proofs/` mirroring the reactivation-proof block. |
| `temp/system_reset_install.md` | This file — rewritten to reflect implemented state + document the orphaned-proof-image fix. |

---

## 9. Testing Checklist After Fixes

After applying fixes, verify:

- [ ] Run `reset.php` with "RESET" confirmation
- [ ] Check `pv_transactions` — should be empty (`SELECT COUNT(*) FROM pv_transactions` → 0)
- [ ] Check `carts` — should be empty
- [ ] Check `cart_items` — should be empty
- [ ] Check `repeat_purchase_orders` — should be empty
- [ ] Check `repeat_purchase_order_items` — should be empty
- [ ] Check `uploads/repeat_purchase_proofs/` — directory should be empty after reset
- [ ] Check `uploads/reactivation_proofs/` — directory should be empty after reset
- [ ] Check admin `users` row — all PV columns should be 0.00
- [ ] Check `settings` — should still have your custom values (preserved)
- [ ] Check `products` — should still have your products (preserved)
- [ ] Check `packages` — should still have your packages (preserved, unless unchecked)
- [ ] Check `reg_codes` — should be empty (no demo codes after reset; see §2.4)
- [ ] Create a new member, place a repeat purchase order with a proof image, then reset again — verify all new data AND the proof image are wiped
