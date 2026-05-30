# Plan: Admin Toggle to Disable Indirect Referrals

## Overview
Add a system-wide admin setting toggle (`indirect_referral_enabled`) that disables indirect (unilevel) referral bonuses globally. When disabled:
- No indirect referral commissions are calculated or paid
- The indirect referral configuration is hidden from package settings
- All indirect referral UI elements are hidden from member-facing pages
- Existing historical data (past commissions, ledger entries) is preserved

## Approach
Single toggle in **System Settings** → gates the `Commission::processIndirectReferral()` engine → hides all related UI. Default is **enabled** (`1`) for backward compatibility.

---

## Files to Modify

### 1. Database Migration
**`migrations/011_add_indirect_referral_toggle.sql`**
```sql
INSERT INTO settings (`key`, `value`) VALUES ('indirect_referral_enabled', '1')
ON DUPLICATE KEY UPDATE `value` = `value`;
```
- No schema changes needed. Uses existing `settings` table.

---

### 2. Admin Settings — Persistence
**`controllers/AdminController.php`** — `saveSettings()` whitelist
- Add `'indirect_referral_enabled'` to the `$allowed` array so the toggle value is persisted.

---

### 3. Admin Settings — UI
**`views/admin/settings.php`**
- Add a new toggle switch in the **Compensation Plan Defaults** section (around line 102–120):
  ```html
  <div class="form-check form-switch mb-3">
    <input class="form-check-input" type="checkbox" name="indirect_referral_enabled"
           id="indirectRefEnabled" value="1"
           <?= setting('indirect_referral_enabled', '1') === '1' ? 'checked' : '' ?>>
    <label class="form-check-label" for="indirectRefEnabled" style="font-weight:600;">
      Enable Indirect Referral (Unilevel) Bonuses
    </label>
  </div>
  <div class="form-text mb-3">
    When disabled, no unilevel bonuses are paid and all indirect referral UI is hidden from members.
  </div>
  ```

---

### 4. Business Logic Gate
**`core/Commission.php`** — `processIndirectReferral()` (line 163)
- Add early return at the top of the method:
  ```php
  if (setting('indirect_referral_enabled', '1') !== '1') {
      return;
  }
  ```

**`models/User.php`** — `register()` (line 60–67)
- Wrap the `processIndirectReferral()` call in a conditional:
  ```php
  if (setting('indirect_referral_enabled', '1') === '1') {
      Commission::processIndirectReferral($data['sponsor_id'], $newId, $data['package_id']);
  }
  ```
  > *Rationale:* Double-gate. The engine already returns early, but skipping the call entirely avoids unnecessary DB queries and makes the code path explicit.

---

### 5. Admin Package Settings — Hide Configuration
**`views/admin/packages.php`** (lines 188–204)
- Wrap the entire **"🔗 Indirect Referral Bonuses (10 Levels)"** section:
  ```php
  <?php if (setting('indirect_referral_enabled', '1') === '1'): ?>
    <!-- existing L1–L10 inputs -->
  <?php endif; ?>
  ```

---

### 6. Member Dashboard — Hide Stats & Activity
**`views/member/dashboard.php`**
- **Line 107** — Stat card: wrap the "Indirect Referral" card in `<?php if (setting('indirect_referral_enabled', '1') === '1'): ?>`
- **Line 295** — Activity `$typeMap`: conditionally include `indirect_referral` mapping
- **Line 300** — Activity label: the type filter in the query naturally won't match if the map is excluded, but guard the label display just in case

---

### 7. Member Earnings — Hide Filter, Stat & History
**`views/member/earnings.php`**
- **Line 21** — Summary stat card: wrap "Indirect Referral" card
- **Line 40** — Filter tab: conditionally render the `🔗 Indirect` tab link
- **Line 69** — History table row label: guard with conditional (or rely on the fact that no rows will exist when disabled)

---

### 8. Member Genealogy — Hide Referral Tree
**`views/member/genealogy.php`** (lines 288–298)
- Wrap the entire **"Referral Network"** section (the level-grouped collapsible list) in the conditional.
- Keep the binary tree section unchanged — that is unaffected.

---

### 9. Member Cap Status — Hide Indirect Rows
**`views/member/cap_status.php`**
- **Lines 98–106** — Lifetime cap timeline: conditionally show the "🔗 Indirect Referrals" row
- **Line 239** — Cap breakdown table: conditionally show the "Indirect Referral" row

---

### 10. Admin User View — Hide Indirect Labels
**`views/admin/user_view.php`**
- **Lines 212–215** — Commission history table: guard the `indirect_referral` → "🔗 Indirect Lvl" label
- **Lines 363–366** — Cap-blocked commissions table: same guard

---

## Historical Data Policy
**No deletion.** Past indirect commissions in `commissions`, `ewallet_ledger`, and `users.lifetime_earned` remain untouched. The toggle only affects:
1. Future registrations (no new indirect payouts)
2. UI visibility (members won't see indirect-related stats)

If the toggle is re-enabled later, new registrations will resume paying indirect bonuses normally.

---

## Testing Checklist
1. Toggle ON (default): indirect bonuses pay normally, all UI visible
2. Toggle OFF: register a new member → verify no indirect commissions created, no ledger entries, payer balance unchanged for indirect amounts
3. Toggle OFF: member dashboard/earnings/genealogy/cap-status show no indirect elements
4. Toggle OFF: admin package settings page hides L1–L10 inputs
5. Toggle OFF → ON: re-enable, register another member → indirect bonuses resume
