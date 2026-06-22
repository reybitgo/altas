# QA Test Plan: Absolute PV System (Compact Convention)

> **What changed:** `package_pv_rate` now stores the **absolute PV value** directly (not a percentage of entry fee). Combined with `pv_per_peso_rate = 1000`, the numbers stay small and clean — ideal for genealogy tree display where space is tight.
>
> **Example with defaults (Starter package, entry fee = P10,000):**
> - Package PV = **10** (stored directly in `package_pv_rate` column)
> - Binary PV = 10 x 20% = **2 PV**
> - Direct PV = 10 x 5% = **0.5 PV**
> - DFI (1%) = 10 x 1% = **0.1 PV**
> - Peso value = PV x 1000 (e.g., 0.5 PV x 1000 = P500 direct bonus)
>
> **Key formulas:**
> - Package PV = `package_pv_rate` (direct value, small number like 10)
> - Binary PV = Package PV x `binary_pv_pct / 100`
> - Direct Ref PV = Package PV x `direct_ref_pv_pct / 100`
> - Indirect Ref PV = Package PV x `level_pv_pct / 100`
> - DFI (PV mode) = Package PV x `dfi_pv_pct / 100`
> - Peso bonus = PV x `pv_per_peso_rate` (1000 by default)

---

## Prerequisites

- Fresh install (run `install.sql`) OR run **Migration 028** on existing DB
- Set **PV Conversion Rate** = `1000.0000` in **Admin > Settings**
- Logged in as **admin** (`admin` / `Admin@1234`)
- Second browser/incognito for member testing
- Unused registration codes (create in **Admin > Codes**)

---

## Test 1: Package Data & Form

**Goal:** Verify packages store small absolute PV values correctly.

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1.1 | Go to **Admin > Packages** (`?page=admin_packages`) | Package list loads |
| 1.2 | Click **Edit** on Starter package | Form opens |
| 1.3 | Check **Package PV** field | Shows **10.00 PV** (small number, not 10000) |
| 1.4 | Check **Binary PV** preview | `Binary PV = Package PV x 20.00% = P2,000.00 in binary tree` |
| 1.5 | Check **Direct Ref** preview | Shows `~P500.00 per recruit` (0.5 PV x 1000) |
| 1.6 | Go to **Settings** (`?page=admin_settings`) | **PV Conversion Rate** = `1000.0000` |

**Pass:** Package PV is a small integer. Previews use `pv_per_peso_rate=1000` for correct peso values.

---

## Test 2: Package CRUD

**Goal:** Create/edit packages with compact PV numbers.

| Step | Action | Expected Result |
|------|--------|-----------------|
| 2.1 | Click **+ New Package** | Modal opens |
| 2.2 | Name = `Compact`, Entry Fee = `5000` | |
| 2.3 | Set **Package PV** = `5.00` | Preview shows `5.00 PV` |
| 2.4 | Set **Binary PV Rate** = `10.00` | Preview: `Binary PV = Package PV x 10.00% = P500.00` |
| 2.5 | Set **Direct Ref** = `5.00` | Preview: `~P250.00 per recruit` (0.25 PV x 1000) |
| 2.6 | Set **DFI PV %** = `2.00` | Preview: daily rate = P100 (0.1 PV x 1000) |
| 2.7 | Click **Create Package** | Success |
| 2.8 | Click **Edit** on the new package | Package PV reloads as `5.00` |
| 2.9 | Change to `8.00`, update | Re-edit shows `8.00` |

**Pass:** PV values are small, self-explanatory numbers. All previews correct.

---

## Test 3: Binary PV — Registration & Tree

**Goal:** Compact PV flows correctly into binary tree (10 PV becomes 2 PV per leg).

| Step | Action | Expected Result |
|------|--------|-----------------|
| 3.1 | Register member A, place **Left** of admin | Created |
| 3.2 | As admin, check **Admin > User View** for admin | **Left PV = 2.00** (10 x 20%) |
| 3.3 | Register member B, place **Right** of admin | **Right PV = 2.00** |
| 3.4 | Check admin's Dashboard | If both legs >= 2 PV, pairing fires |
| 3.5 | Check **Genealogy Tree** | Node labels show `L:2 PV R:2 PV` (small, readable) |

**Pass:** Genealogy tree displays compact PV values (2 instead of 2000).

---

## Test 4: Direct Referral Bonus

| Step | Action | Expected Result |
|------|--------|-----------------|
| 4.1 | Register member C sponsored by member A | |
| 4.2 | Check A's **Earnings** | Direct bonus credited |
| 4.3 | Starter: PV=10, direct=5% => 0.5 PV => 0.5x1000 = P500 | Amount = **P500.00** |

**Pass:** Direct bonus = Package PV x (direct_ref_pv_pct / 100) x 1000.

---

## Test 5: Indirect Referral Bonus

| Step | Action | Expected Result |
|------|--------|-----------------|
| 5.1 | Build a chain of 3+ levels | |
| 5.2 | Check top sponsor's **Earnings** | Indirect bonuses at each level |
| 5.3 | Level 1: PV=10 x 3% = 0.3 PV => 0.3x1000 = P300 | Level 1 = **P300.00** |
| 5.4 | Level 2: PV=10 x 2% = 0.2 PV => 0.2x1000 = P200 | Level 2 = **P200.00** |

**Pass:** Small PV values scale correctly through levels.

---

## Test 6: DFI (Daily Fixed Income)

| Step | Action | Expected Result |
|------|--------|-----------------|
| 6.1 | Edit a package, set **DFI PV %** = `1.00` | Daily rate = 10x1%x1000 = **P100.00/day** |
| 6.2 | Run DFI cron | Members paid correctly |
| 6.3 | Check E-wallet Ledger | DFI credit = P100.00 |

**Pass:** PV-based DFI uses compact numbers correctly.

---

## Test 7: Product PV (Independent)

**Product PV values are independent of the 1000 rate — they can be any number.**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 7.1 | Create product with PV Value = `5` | Saved |
| 7.2 | Member buys it, admin approves | Personal PV +5, Group PV +5 |
| 7.3 | Binary leg increases by 5 | Tree shows readable numbers |

**Pass:** Product PV is flexible — not tied to the package PV convention.

---

## Test 8: PV Transactions

| Step | Action | Expected Result |
|------|--------|-----------------|
| 8.1 | `SELECT * FROM pv_transactions LIMIT 20` | All amounts are small numbers |
| 8.2 | Check `amount` column | Values like 2.00, 0.5, 0.3 (not 2000, 500, 300) |

**Pass:** Transaction log is compact and readable.

---

## Test 9: Lifetime Cap (Unchanged)

| Step | Action | Expected Result |
|------|--------|-----------------|
| 9.1 | lifetime_cap_multiplier = 3x | Cap = entry_fee x 3 (peso-based) |
| 9.2 | Cap reached | Earnings stop |

**Pass:** Lifetime cap unaffected by PV changes.

---

## Test 10: Daily Pair PV Cap

| Step | Action | Expected Result |
|------|--------|-----------------|
| 10.1 | Default daily_pair_pv_cap = 30000 PV | |
| 10.2 | Generate enough pairing | Cap limits pairing, excess flushed |

**Pass:** Pair cap works with any PV scale.

---

## Test 11: Cron & Resets

| Step | Action | Expected Result |
|------|--------|-----------------|
| 11.1 | Run `cron/midnight_reset.php` | paired_pv_today = 0 |
| 11.2 | Run `cron/monthly_pv_reset.php` | personal_pv = 0 |
| 11.3 | Run `/reset.php` (keep packages) | Package PV = **10.00** |
| 11.4 | Reset with "Keep packages" OFF | Starter re-seeded with **10.00** |

**Pass:** All resets use the compact convention.

---

## Test 12: All Displays

| Area | What to check |
|------|---------------|
| Admin user view | Personal PV, Group PV, Left/Right PV — small numbers |
| Member dashboard | Same — compact, readable |
| Genealogy tree | `L:2 PV R:2 PV` — fits in node labels |
| Admin settings | PV Conversion Rate = 1000.0000 |
| Admin packages table | Pairing payout shows peso values |

**Pass:** Every PV display is compact and user-friendly.

---

## Test 13: Frontend Marketing Pages

| Step | Action | Expected Result |
|------|--------|-----------------|
| 13.1 | Visit frontend | Package cards show peso bonus amounts |
| 13.2 | Verify amounts | Binary pair = P2,000, Direct = P500, DFI = P100/day |
| 13.3 | Raw PV never shown to public | Only peso values displayed |

**Pass:** Frontend only shows peso-converted amounts.

---

## Test 14: Edge Cases

| Step | Action | Expected Result |
|------|--------|-----------------|
| 14.1 | Package PV = **0** | No bonuses from this package |
| 14.2 | Package PV = **999999.99** | Scales up, bonuses match |
| 14.3 | binary_pv_pct = 0% | No tree PV |
| 14.4 | Change PV Conversion Rate in Settings | All peso values update immediately |

**Pass:** System handles any PV value gracefully.

---

## Summary

The compact convention (`pv_per_peso_rate = 1000`, `Package PV = 10` for a P10k entry) keeps PV numbers small throughout the system — especially useful in the genealogy tree where `L:2 PV R:2 PV` fits nicely instead of `L:2,000 PV R:2,000 PV`.

**Quick reference — Starter package (entry P10,000):**

| Concept | Old (% of fee) | Intermediate (direct PV) | Compact (PV x 1000) |
|---------|---------------|------------------------|---------------------|
| Package PV Rate | 100% | 10000 PV | **10 PV** |
| Binary PV (20%) | 2000 PV | 2000 PV | **2 PV** |
| Direct Ref (5%) | 500 PV | 500 PV | **0.5 PV** |
| DFI (1%) | 100 PV | 100 PV | **0.1 PV** |
| Binary pair peso | P2,000 | P2,000 | **P2,000** |
| Direct peso | P500 | P500 | **P500** |

Raw PV values are now small; peso values (displayed to users) remain the same.
