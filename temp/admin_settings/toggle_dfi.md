# `Enable Daily Fixed Income` — Clean-Sweep Implementation Plan

## 1. Problem / Context

Currently `dfi_enabled` in **Admin → Settings → Compensation Plan** only gates the payout engine (`DailyFixedIncome::processDailyPayout()` returns `['reason'=>'disabled']`). All DFI-related UI — member dashboard widget, DFI History page, earnings stat card, cap-status timeline, admin dashboard stat, admin DFI page, sidebar nav links — remains visible even when DFI is disabled. This creates confusion: members see "DFI" everywhere but receive nothing.

The ask is a **clean sweep**: when `dfi_enabled` is OFF, every DFI touchpoint across the system is hidden or redirected. When toggled back ON, everything reappears without data loss.

---

## 2. Design Principles

| Principle | Application |
|-----------|-------------|
| **Admin always retains access to configure DFI** | Package DFI fields, the settings toggle itself remain visible (admin needs to set these up before enabling) |
| **Historical data is preserved** | Past `daily_fixed_income_log` records remain queryable; earnings totals in summary views include DFI line items for full transparency |
| **Members see no dangling UI** | No nav links, no dashboard widgets, no history pages — if they visit a DFI URL directly, they get redirected with an info flash |
| **Toggleable anytime, no reset** | No member-count guard; re-enabling picks up at next midnight cron |
| **Graceful degradation** | API endpoints return `{'status': 'disabled'}` rather than erroring |

---

## 3. Architecture Overview

```
┌──────────────────────────────────────────────────────────────────────┐
│              ADMIN SETTINGS (comp_plan tab)                           │
│                                                                       │
│  ☑ Enable Binary Pairing Bonuses          [binary_enabled]            │
│  ☑ Enable Binary Repeat Purchase          [binary_repeat_enabled]     │
│  ☑ Enable Indirect Referral Bonuses        [indirect_referral_enabled]│
│  ☑ Enable Daily Fixed Income       ◄──   [dfi_enabled]  (master sw)  │
│  ...                                                                  │
└────────────────────────┬─────────────────────────────────────────────┘
                         │ setting('dfi_enabled', '1')
                         ▼
              ┌──────────────────────┐
              │  dfi_enabled === '1'? │
              └──────┬──────┬───────┘
                     │      │
                   YES      NO
                     │      │
                     ▼      ▼
        ┌──────────────────────┐
        │ Render all DFI UI    │    ┌─────────────────────────────────┐
        │ Process midnight     │    │ Hide/redirect all DFI:          │
        │ payouts              │    │ • Member sidebar nav: HIDE      │
        │                     │    │ • Dashboard DFI widget: HIDE     │
        │ Frontend already     │    │ • Earnings stat + tab: HIDE     │
        │ gated by $dfiEnabled │    │ • Cap Status timeline: HIDE     │
        │ (no changes needed)  │    │ • DFI History page: REDIRECT    │
        └──────────────────────┘    │ • Admin sidebar nav: HIDE       │
                                    │ • Admin dashboard card: HIDE    │
                                    │ • Admin DFI page: REDIRECT      │
                                    │ • UserView DFI tab: HIDE        │
                                    │ • Packages table col: HIDE      │
                                    │ • API returns disabled status   │
                                    │                                 │
                                    │ KEEP VISIBLE:                   │
                                    │ • Settings toggle (to re-enable)│
                                    │ • Package DFI form fields       │
                                    │   (admin needs to configure)    │
                                    │ • Historical data in earnings   │
                                    │   and cap_status tables         │
                                    └─────────────────────────────────┘
```

---

## 4. Clean-Sweep Matrix — All 31 Touchpoints

### 4A — Member Area (need changes — 9 files)

| # | File | Line(s) | Element | `dfi_enabled=OFF` Action |
|---|------|---------|---------|--------------------------|
| 1 | `views/partials/sidebar_member.php` | 32 | "DFI History" nav link | HIDE the list item entirely |
| 2 | `views/member/dashboard.php` | 262–306 | DFI widget card | HIDE the entire `.col-12.col-md-6` div |
| 3 | `views/member/earnings.php` | 23 | DFI stat card | HIDE the stat card row |
| 4 | `views/member/earnings.php` | 96 | DFI filter tab | DROP from `$filterTabs` array |
| 5 | `views/member/earnings.php` | 129 | DFI type label in table | KEEP (historical records still show type) |
| 6 | `views/member/cap_status.php` | 107–114 | DFI timeline item | HIDE the `timeline-item` |
| 7 | `views/member/cap_status.php` | 242 | DFI breakdown row | DROP from `$breakdown` array |
| 8 | `controllers/MemberController.php` | 536–539 | `apiDfiStatus()` | RETURN `{'status':'disabled'}` early |
| 9 | `controllers/MemberController.php` | 545–573 | `dfiHistory()` | FLASH + REDIRECT to dashboard |
| 10 | `index.php` | 313 | `dfi_history` route | Unchanged — route exists, controller handles gating |

### 4B — Admin Area (need changes — 7 files)

| # | File | Line(s) | Element | `dfi_enabled=OFF` Action |
|---|------|---------|---------|--------------------------|
| 11 | `views/partials/sidebar_admin.php` | 77–79 | "DFI Admin" nav link | HIDE the `<a>` tag |
| 12 | `views/admin/dashboard.php` | 39 | "DFI Paid Today" stat card | HIDE the stat-card entry |
| 13 | `controllers/AdminController.php` | 696–718 | `dfiAdmin()` method | FLASH + REDIRECT to settings |
| 14 | `views/admin/user_view.php` | 322 | Tab label `'cap_dfi'` | RENAME to `'🛡️ Cap'` |
| 15 | `views/admin/user_view.php` | 444–468 | DFI Status card | HIDE the entire card |
| 16 | `views/admin/user_view.php` | 517 | DFI type in cap-blocked | KEEP (historical formatting) |
| 17 | `views/admin/packages.php` | 75, 119–129 | DFI column (header + data) | HIDE both `<th>` and `<td>` |
| 18 | `views/admin/packages.php` | 268–296 | DFI form fields | KEEP (admin configures before enabling) |
| 19 | `controllers/AdminController.php` | 644–652 | Manual reset "trigger DFI" | HIDE checkbox + label |

### 4C — Settings (always visible)

| # | File | Line(s) | Element | Action |
|---|------|---------|---------|--------|
| 20 | `views/admin/settings.php` | 175–184 | DFI enable toggle | ALWAYS VISIBLE (master control) |
| 21 | `views/admin/settings.php` | Reset section | "Also trigger DFI" checkbox | GATE on `dfi_enabled` |

### 4D — Frontend Marketing (already gated)

| # | File | Line(s) | Element | Status |
|---|------|---------|---------|--------|
| 22 | `frontend/index.php:13,61,77` | `$dfiEnabled` flag, stream count, stream words | Already reads setting — no change |
| 23 | `frontend/index.php:264,341` | Terms DFI bullets | Already `<?php if ($dfiEnabled): ?>` |
| 24 | `frontend/index.php:707,855,922,962,1027` | Marquee, plan card, features, "Why" | Already gated on `$dfiEnabled` |

### 4E — Backend/Engine (already gated or intentionally preserved)

| # | File | Line(s) | Element | Status |
|---|------|---------|---------|--------|
| 25 | `core/DailyFixedIncome.php:29` | `processDailyPayout()` | ✅ Returns `['reason'=>'disabled']` |
| 26 | `core/DailyFixedIncome.php:253` | `getMemberDFIStatus()` | ✅ Returns `status='disabled'` when off |
| 27 | `cron/midnight_reset.php:168-178` | DFI cron call | ✅ Logs disabled state |
| 28 | `core/Reactivation.php:187,268` | dfi_days_used reset | ✅ Keep (needed if re-enabled) |
| 29 | `core/CapEngine.php:198` | dfi_active = 0 on cap | ✅ Keep (state management) |
| 30 | `core/Commission.php:643` | `total_dfi` in summary | ✅ Keep (historical reporting) |

---

## 5. Data Flow: Disabled → Enabling

```
  DFI DISABLED                  ADMIN TOGGLES ON             NEXT MIDNIGHT
  ┌──────────────┐              ┌──────────────┐             ┌──────────────┐
  │ • All UI      │   ─────►   │ • UI instantly │   ─────►   │ • Cron runs   │
  │   hidden      │   toggle   │   reappears    │   cron     │   processDaily│
  │ • Payouts     │  dfi_enabled              │   midnight  │   Payout()    │
  │   stopped     │   = '1'    │ • Any packages │             │ • DFI paid    │
  │ • Admin can   │            │   already have │             │   to eligible │
  │   configure   │            │   DFI config   │             │   members     │
  │   packages    │            │   set up       │             │               │
  └──────────────┘            └──────────────┘             └──────────────┘
```

---

## 6. File-by-File Changes

### 6.1 — Member Sidebar  (`views/partials/sidebar_member.php:32`)

```php
// Before (one line, unconditional):
['page' => 'dfi_history', 'icon' => '📅', 'label' => 'DFI History', 'pages' => ['dfi_history']],

// After (wrapped in spread):
...(setting('dfi_enabled', '1') === '1' ? [
  ['page' => 'dfi_history', 'icon' => '📅', 'label' => 'DFI History', 'pages' => ['dfi_history']],
] : []),
```

Pattern: identical to `binary_enabled` gate at line 34–36 of the same file.

---

### 6.2 — Member Dashboard  (`views/member/dashboard.php:262–306`)

```php
<?php if (setting('dfi_enabled', '1') === '1'): ?>
<!-- DFI Widget -->
<div class="col-12 col-md-6">
  ...
</div>
<?php endif; ?>
```

Wraps lines 263–306. Ensures the entire widget + its DB call (`getMemberDFIStatus`) is skipped.

---

### 6.3 — Member Earnings  (`views/member/earnings.php`)

**Line 23** — stat card:

```php
// Before:
['DFI', $summary['total_dfi'] ?? 0,  'teal', 'info'],

// After:
...(setting('dfi_enabled', '1') === '1' ? [['DFI', $summary['total_dfi'] ?? 0, 'teal', 'info']] : []),
```

**Line 96** — filter tab:

```php
// Before:
$filterTabs = ['' => 'All', ...($binaryEnabled ? ['pairing' => '🤝 Pairing'] : []), 'direct_referral' => '👥 Direct', ...(setting('indirect_referral_enabled', '1') === '1' ? ['indirect_referral' => '🔗 Indirect'] : []), 'daily_fixed_income' => '📅 DFI'];

// After:
$filterTabs = ['' => 'All', ...($binaryEnabled ? ['pairing' => '🤝 Pairing'] : []), 'direct_referral' => '👥 Direct', ...(setting('indirect_referral_enabled', '1') === '1' ? ['indirect_referral' => '🔗 Indirect'] : []), ...(setting('dfi_enabled', '1') === '1' ? ['daily_fixed_income' => '📅 DFI'] : [])];
```

**Line 129** — no change (type label for historical records).

---

### 6.4 — Member Cap Status  (`views/member/cap_status.php`)

**Lines 107–114** — timeline item:

```php
<?php if (setting('dfi_enabled', '1') === '1' && (float)($summary['total_dfi'] ?? 0) > 0): ?>
<div class="timeline-item">
  <div class="timeline-dot" style="background:#06b6d4;"></div>
  <div class="timeline-content">
    <div class="fw-bold" style="font-size:.85rem;">📅 Daily Fixed Income</div>
    <div class="text-muted" style="font-size:.75rem;"><?= fmt_money((float)$summary['total_dfi']) ?> earned</div>
  </div>
</div>
<?php endif; ?>
```

**Line 242** — breakdown table row:

```php
// Before:
['Daily Fixed Income', (float)($summary['total_dfi'] ?? 0)],

// After:
...(setting('dfi_enabled', '1') === '1' ? [['Daily Fixed Income', (float)($summary['total_dfi'] ?? 0)]] : []),
```

---

### 6.5 — Member Controller  (`controllers/MemberController.php`)

**`apiDfiStatus()`** — lines 536–539:

```php
public function apiDfiStatus(): void
{
    Auth::guard('member');
    if (setting('dfi_enabled', '1') !== '1') {
        json_response([
            'status'           => 'disabled',
            'daily_rate'       => 0,
            'daily_rate_pv'    => 0,
            'dfi_pv_pct'       => 0,
            'days_used'        => 0,
            'days_remaining'   => 0,
            'total_dfi_earned' => 0,
        ]);
        return;
    }
    json_response(DailyFixedIncome::getMemberDFIStatus(Auth::id()));
}
```

**`dfiHistory()`** — lines 545–573:

```php
public function dfiHistory(): void
{
    Auth::guard('member');
    if (setting('dfi_enabled', '1') !== '1') {
        flash('info', 'Daily Fixed Income is currently disabled.');
        redirect('/?page=dashboard');
    }
    // … existing code unchanged …
}
```

---

### 6.6 — Admin Sidebar  (`views/partials/sidebar_admin.php:77–79`)

```php
<?php if (setting('dfi_enabled', '1') === '1'): ?>
<a href="<?= APP_URL ?>/?page=admin_dfi" class="nav-item-link <?= $cp === 'admin_dfi' ? 'active' : '' ?>">
  <span class="nav-icon">📅</span> DFI Admin
</a>
<?php endif; ?>
```

---

### 6.7 — Admin Dashboard  (`views/admin/dashboard.php:39`)

The stat cards are generated from an inline array. Gate the DFI entry:

```php
// Before (line 39):
['DFI Paid Today',     fmt_money($v2Stats['dfi_today']),              'primary', '📅', '<a href="' . APP_URL . '/?page=admin_dfi" class="text-decoration-none fw-semibold" style="font-size:.72rem;">View →</a>'],

// After:
...(setting('dfi_enabled', '1') === '1' ? [['DFI Paid Today', fmt_money($v2Stats['dfi_today']), 'primary', '📅', '<a href="' . APP_URL . '/?page=admin_dfi" class="text-decoration-none fw-semibold" style="font-size:.72rem;">View →</a>']] : []),
```

> **Note**: The `$v2Stats['dfi_today']` query in `AdminController::dashboard()` (line 24) still runs. The overhead of a `COALESCE(SUM(...))` on an indexed column is negligible. Gating it would require refactoring the inline array — not worth the complexity.

---

### 6.8 — Admin Controller  (`controllers/AdminController.php`)

**`dfiAdmin()`** — around line 696:

```php
public function dfiAdmin(): void
{
    Auth::guard('admin');
    if (setting('dfi_enabled', '1') !== '1') {
        flash('info', 'Daily Fixed Income is disabled. Enable it in Compensation Plan settings.');
        redirect('/?page=admin_settings#tabPane-comp_plan');
    }
    // … existing DFI admin code …
}
```

**Manual reset trigger** — around line 644:

The "Also trigger DFI payout now" checkbox in the `manualReset()` action / view should be gated. This is in the admin settings view (the manual reset form). Let me locate it:

```
(AdminController::manualReset() line 644):
if (isset($_POST['trigger_dfi']) && $_POST['trigger_dfi'] === '1') {
    $dfiResult = DailyFixedIncome::processDailyPayout();
```

In the settings view, the checkbox for this is rendered. Gate it with:

```php
<?php if (setting('dfi_enabled', '1') === '1'): ?>
  <div class="form-check mb-2">
    <input class="form-check-input" type="checkbox" name="trigger_dfi" id="triggerDfi" value="1">
    <label class="form-check-label" for="triggerDfi">Also trigger DFI payout now</label>
  </div>
<?php endif; ?>
```

---

### 6.9 — Admin User View  (`views/admin/user_view.php`)

**Line 322** — tab label:

```php
// Before (inline foreach):
<?php foreach (['commissions' => '💰 Commissions', 'ledger' => '📒 E-Wallet Ledger', 'payouts' => '💳 Payouts', 'cap_dfi' => '🛡️ Cap & DFI', 'ewallet' => '💱 Transfers'] as $t => $label): ?>

// After:
<?php
$userTabs = [
    'commissions' => '💰 Commissions',
    'ledger'      => '📒 E-Wallet Ledger',
    'payouts'     => '💳 Payouts',
    'cap_dfi'     => setting('dfi_enabled', '1') === '1' ? '🛡️ Cap & DFI' : '🛡️ Cap',
    'ewallet'     => '💱 Transfers',
];
?>
<?php foreach ($userTabs as $t => $label): ?>
```

**Lines 444–468** — DFI Status card:

```php
<?php if (setting('dfi_enabled', '1') === '1'): ?>
<div class="col-12 col-md-6">
  <div class="card h-100">
    <div class="card-header"><span class="card-title">📅 DFI Status</span></div>
    <div class="card-body">
      ...
    </div>
  </div>
</div>
<?php endif; ?>
```

**Line 517** — no change (historical cap-blocked records still need DFI type label).

---

### 6.10 — Admin Packages  (`views/admin/packages.php`)

**Line 75** — column header:

```php
<?php if (setting('dfi_enabled', '1') === '1'): ?>
<th class="text-center">DFI</th>
<?php endif; ?>
```

**Lines 119–129** — column data:

```php
<?php if (setting('dfi_enabled', '1') === '1'): ?>
<td class="text-center">
  <?php if ($hasDfi): ?>
    <div class="font-mono" style="font-size:.8rem;color:#db2777;"><?= fmt_money($dfiAmount) ?>/d</div>
    <?php if ($dfiPvPct > 0): ?>
      <div class="text-muted" style="font-size:.65rem;"><?= $dfiPvPct ?>% of PV · <?= (int)$pkg['daily_fixed_income_days'] ?> days</div>
    <?php else: ?>
      <div class="text-muted" style="font-size:.65rem;"><?= (int)$pkg['daily_fixed_income_days'] ?> days</div>
    <?php endif; ?>
  <?php else: ?>
    <span class="text-muted" style="font-size:.8rem;">—</span>
  <?php endif; ?>
</td>
<?php endif; ?>
```

**Lines 268–296** — no change (DFI form fields stay for configuration).

---

## 7. Summary of All Changes

| # | File | Change | Lines |
|---|------|--------|-------|
| 1 | `views/partials/sidebar_member.php` | Wrap nav item in spread conditional | ±2 |
| 2 | `views/member/dashboard.php` | Wrap DFI widget in `<?php if ?>` | ±2 |
| 3 | `views/member/earnings.php` | Gate stat card + filter tab | ±2 |
| 4 | `views/member/cap_status.php` | Gate timeline item + breakdown row | ±2 |
| 5 | `controllers/MemberController.php` | Early return in apiDfiStatus + dfiHistory redirect | ±8 |
| 6 | `views/partials/sidebar_admin.php` | Wrap DFI Admin nav in `<?php if ?>` | ±4 |
| 7 | `views/admin/dashboard.php` | Gate DFI stat card entry | ±2 |
| 8 | `controllers/AdminController.php` | Gate dfiAdmin() + gate trigger_dfi logic | ±8 |
| 9 | `views/admin/user_view.php` | Dynamic tab label + hide DFI card | ±12 |
| 10 | `views/admin/packages.php` | Gate DFI column header + data | ±6 |
| 11 | `views/admin/settings.php` | Gate "trigger DFI" checkbox (no change to toggle) | ±4 |

**Total: ~50 lines of meaningful changes** across 11 files.

---

## 8. Notable Non-Changes (Intentionally Preserved)

| File | Element | Reason |
|------|---------|--------|
| `views/admin/packages.php:268-296` | DFI form fields | Admin needs to configure before enabling toggle |
| `views/admin/settings.php:175-184` | DFI toggle itself | Master control switch |
| `views/member/earnings.php:129` | DFI type label in table | Historical records still show "📅 Daily Fixed Income" |
| `views/admin/user_view.php:517` | DFI in cap-blocked type map | Historical cap-blocked entries still need labeling |
| `core/DailyFixedIncome.php` | Engine methods | Already gates on setting; getMemberDFIStatus returns disabled status |
| `core/Reactivation.php` | dfi_days_used = 0 reset | Must reset even when disabled, so re-enabling works cleanly |
| `core/CapEngine.php` | dfi_active = 0 | State management independent of global toggle |
| `core/Commission.php` | total_dfi in summary query | Needed for historical reporting even when disabled |
| `frontend/index.php` | All DFI marketing content | Already gated on `$dfiEnabled` flag |
| `cron/midnight_reset.php` | DFI payout call | Already logs disabled gracefully |
| `index.php` | Route definitions | Routes persist; controllers handle gating |

---

## 9. QA Testing Scenarios

| # | Scenario | `dfi_enabled` | Expected Result |
|---|----------|--------------|-----------------|
| T1 | Member views dashboard | OFF | No DFI widget visible |
| T2 | Member views sidebar | OFF | No "DFI History" link |
| T3 | Member visits `/?page=dfi_history` directly | OFF | Flash "DFI is disabled" + redirect to dashboard |
| T4 | Member views Earnings page | OFF | No DFI stat card, no DFI filter tab |
| T5 | Member views Cap Status page | OFF | No DFI timeline item, no DFI breakdown row |
| T6 | Member views Earnings page with historical DFI | OFF | Table rows with `daily_fixed_income` type still show correct label |
| T7 | AJAX calls `api_dfi_status` | OFF | Returns `{"status":"disabled",...}` |
| T8 | Admin views sidebar | OFF | No "DFI Admin" link |
| T9 | Admin dashboard | OFF | No "DFI Paid Today" card |
| T10 | Admin visits `/?page=admin_dfi` directly | OFF | Flash "DFI is disabled" + redirect to settings |
| T11 | Admin views user in UserView | OFF | Tab shows "🛡️ Cap" (not "Cap & DFI"), DFI Status card hidden |
| T12 | Admin views Packages page | OFF | No "DFI" column in table; DFI form fields still visible in edit/create |
| T13 | Admin views Settings page → Reset section | OFF | "Also trigger DFI payout" checkbox hidden |
| T14 | Admin toggles DFI ON | ON → OFF → ON | UI reappears instantly; packages already configured work at next midnight |
| T15 | Cron runs at midnight | OFF | Logs "Globally disabled via settings" |
| T16 | Frontend marketing page | OFF | No DFI references anywhere in the HTML |

---

## 10. Migration Sequence

No database migration needed — `dfi_enabled` already exists in the `settings` table. This is purely a code-change plan.

1. Apply all 11 file changes
2. Verify settings toggle still works (ON → OFF → ON)
3. Run T1–T16 manual tests
4. Deploy
