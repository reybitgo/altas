# Phase 3 QA Testing Guide
## Daily Fixed Income (DFI) Engine

---

## Prerequisites

- Phase 1 schema deployed (packages + users v2 columns, `daily_fixed_income_log` table)
- Phase 2 deployed (`CapEngine.php`, `Commission.php` v2)
- At least one member with an active package that has `daily_fixed_income > 0`

---

## Test 1: Global DFI Toggle

### Steps
1. Log in as admin → Settings
2. Uncheck "Enable DFI payouts" → Save
3. Run midnight cron manually: `php cron/midnight_reset.php`

### Expected
- Log shows: `DFI payout: Globally disabled via settings.`
- No entries added to `daily_fixed_income_log`

### Cleanup
- Re-enable DFI in settings

---

## Test 2: Full DFI Payout (Active Member)

### Setup
- Ensure member has:
  - `cap_status = 'active'`
  - `dfi_active = 1`
  - `dfi_days_used = 0`
  - Package `daily_fixed_income = 100.00`, `daily_fixed_income_days = 90`
  - Plenty of lifetime cap remaining

### Steps
1. Run cron: `php cron/midnight_reset.php`

### Expected
- Log shows: `DFI payout: ₱100.00 paid to 1 member(s), 0 skipped.`
- `commissions` table has new row: `type='daily_fixed_income', amount=100.00, status='credited'`
- `ewallet_ledger` has credit entry
- `users.dfi_days_used` = 1
- `daily_fixed_income_log` has entry: `amount=100.00, day_number=1, cap_status_at_payout='active'`

---

## Test 3: Partial DFI Near Cap

### Setup
- Member has `lifetime_earned` just below cap
- Example: cap = ₱30,000, earned = ₱29,970, DFI = ₱100/day

### Steps
1. Run cron

### Expected
- `commissions` amount = 30.00, `cap_deduction` = 70.00
- E-wallet credited ₱30.00
- `users.cap_status` becomes `'capped'`
- `users.dfi_active` becomes `0`
- `daily_fixed_income_log`: `amount=30.00, day_number=...`

---

## Test 4: Capped Member Skipped

### Setup
- Member has `cap_status = 'capped'`
- `dfi_days_used = 5`

### Steps
1. Run cron

### Expected
- Member is skipped (not in eligible query because `cap_status != 'active'`)
- `dfi_days_used` remains 5 (PAUSED)
- No log entry in `daily_fixed_income_log`

---

## Test 5: Admin Manual Reset with DFI Trigger

### Steps
1. Admin → Settings
2. Check "Also trigger DFI payout now"
3. Click "Run Daily Reset Now"

### Expected
- Flash success message includes DFI results:
  - `Daily pair counter reset for N member(s). DFI: ₱... paid to N member(s), N skipped.`

---

## Test 6: Member DFI History Page

### Steps
1. Log in as member who has received DFI
2. Navigate to "DFI History" in sidebar

### Expected
- Page shows DFI status summary (Daily Rate, Days Used, Total Earned, Status)
- Table lists each payout with Date, Day #, Amount, Cap Status
- Pagination works if > 20 records

---

## Test 7: Member Cap Status Page

### Steps
1. Log in as member
2. Navigate to "Lifetime Cap" in sidebar

### Expected
- Progress bar shows `lifetime_earned / lifetime_cap`
- Percentage and remaining amount displayed
- Breakdown table: Pairing, Direct, Indirect, Blocked by Cap
- Correct cap status badge (Active / Capped / Permanent)

---

## Test 8: Dashboard Widgets

### Steps
1. Member dashboard

### Expected
- **Lifetime Cap Widget**: shows earned, remaining, percentage bar, status
- **DFI Widget**: shows days used/remaining, daily rate, total earned, status badge
- Both widgets have "View Details →" / "View History →" links

---

## Test 9: API Endpoints

### Steps
```bash
curl -H "X-Requested-With: XMLHttpRequest" \
  -b "PHPSESSID=..." \
  "http://localhost/altas/?page=api_dfi_status"
```

### Expected JSON
```json
{
  "total_dfi_earned": 100.00,
  "days_used": 1,
  "days_remaining": 89,
  "daily_rate": 100.00,
  "next_payout_date": "2026-05-29 00:00:00",
  "status": "active"
}
```

---

## Test 10: DFI in Recent Activity

### Steps
1. Member dashboard → Recent Activity section

### Expected
- DFI commissions show with 📅 icon
- Type label reads "Daily Fixed Income"
- Amount shown in green with + sign

---

## Rollback (if needed)

Replace modified files with `_current.php` backups saved in `temp/lifetime_capping/phase3/`.
