# Admin Settings — Vertical Tab Menu Redesign

## Overview

Replace the current 2-column card layout with a **vertical tab menu**. Each current setting card becomes a tab on the left sidebar. Clicking a tab displays its full-page form. Each tab has its own **Save Settings** button so sections can be saved independently.

---

## Layout Diagram

```
┌─────────────────────────────────────────────────────────────┐
│  ┌────────┐  ┌──────────────────────────────────────────────┐│
│  │        │  │  ┌──────────────────────────────────────────┐ ││
│  │  🌐    │  │  │  Site Basics                    (tab 1) │ ││
│  │  Site  │  │  │                                            │ ││
│  │ Basics │  │  │  ┌────────────────────────────────────┐   │ ││
│  │        │  │  │  │ Site Name:  [___________________]  │   │ ││
│  ├────────┤  │  │  │ Tagline:    [___________________]  │   │ ││
│  │  🛡️    │  │  │  │ Email:      [___________________]  │   │ ││
│  │  Maint │  │  │  │ Min Payout: [___________________]  │   │ ││
│  │  & Sec │  │  │  └────────────────────────────────────┘   │ ││
│  │        │  │  │                                            │ ││
│  ├────────┤  │  │  │        [ 💾 Save Settings ]            │ ││
│  │  📋    │  │  │  └──────────────────────────────────────────┘ ││
│  │  Comp  │  │  └──────────────────────────────────────────────┘│
│  │  Plan  │  │                                                  │
│  │        │  │  ┌───────────────┐  ┌─────────────────────────┐  │
│  ├────────┤  │  │ Tab Nav       │  │ Content Area            │  │
│  │  ...   │  │  │ (vertical)    │  │ (active tab-pane)       │  │
│  │        │  │  │               │  │                         │  │
│  └────────┘  │  │ 🌐 Site       │  │ [Site Basics form...]   │  │
│  Left tab    │  │    Basics     │  │                         │  │
│  column      │  │ 🛡️ Maint     │  │ [Save Settings btn]     │  │
│  (270px)     │  │    & Sec      │  │                         │  │
│              │  │ 📋 Comp Plan  │  └─────────────────────────┘  │
│              │  │ 🔄 Reactivate │                               │
│              │  │ 💱 E-Wallet   │                               │
│              │  │ 💸 Payouts    │                               │
│              │  │ 📅 DFI        │                               │
│              │  │ 🔒 Password   │                               │
│              │  │ ⏱️ Reset      │                               │
│              │  │ ℹ️ Overview   │                               │
│              │  └───────────────┘                               │
│              │                                                  │
│              └──────────────────────────────────────────────────┘
│                                                              │
└──────────────────────────────────────────────────────────────┘
```

---

## Tab-to-Card Mapping

| # | Tab Name | Icon | Contents (from current cards) | Form action |
|---|----------|------|------------------------------|-------------|
| 1 | Site Basics | 🌐 | site_name, site_tagline, contact_email, min_payout | `admin_save_settings?group=basics` |
| 2 | Maintenance & Security | 🛡️ | maintenance_mode, bypass_token, seat_limit | `admin_save_settings?group=maint_security` |
| 3 | Compensation Plan | 📋 | binary_enabled, indirect_ref_enabled, default_cap_multiplier, pv_per_peso_rate | `admin_save_settings?group=comp_plan` |
| 4 | Reactivation | 🔄 | reactivation_ewallet_enabled, reactivation_external_enabled, gcash_number, maya_number, usdt_trc20_address, usdt_bep20_address | `admin_save_settings?group=reactivation` |
| 5 | E-Wallet Transfers | 💱 | transfer_fee, min_transfer, daily_limit, weekly_limit | `admin_save_settings?group=ewallet_transfer` |
| 6 | Payout Methods | 💸 | gcash_enabled, maya_enabled, all service fees, gas fees | `admin_save_settings?group=payouts` |
| 7 | Daily Fixed Income | 📅 | dfi_enabled | `admin_save_settings?group=dfi` |
| 8 | Change Password | 🔒 | (separate form → `save_profile`) | `save_profile` (external form attr) |
| 9 | Daily Pair Cap Reset | ⏱️ | (separate form → `admin_manual_reset`) | `admin_manual_reset` (external form attr) |
| 10 | System Overview | ℹ️ | Read-only table (PHP/MySQL versions, counts, etc.) | none |

---

## Wireframe Detail

### Tab 1 — Site Basics (active state)

```
┌──────────────┬──────────────────────────────────────────────────────┐
│ 🌐 Site      │  ┌── Site Basics ──────────────────────────────────┐ │
│    Basics*   │  │                                                  │ │
├──────────────┤  │  🌐 Site Name                                   │ │
│ 🛡️ Maint    │  │  [ Altas Farm                            ]      │ │
│    & Sec     │  │                                                  │ │
├──────────────┤  │  🏷️ Site Tagline                                │ │
│ 📋 Comp Plan │  │  [ Build Your Network. Grow Your Income.  ]      │ │
├──────────────┤  │                                                  │ │
│ 🔄 Reactivat │  │  📧 Contact Email                               │ │
├──────────────┤  │  [ support@altasfarm.com                 ]      │ │
│ 💱 E-Wallet  │  │                                                  │ │
├──────────────┤  │  💰 Minimum Payout (₱)                           │ │
│ 💸 Payouts   │  │  [ 500.00                                 ]     │ │
├──────────────┤  │       Members cannot request below this amount   │ │
│ 📅 DFI       │  │                                                  │ │
├──────────────┤  │  ─────────────────────────────────────────────── │ │
│ 🔒 Password  │  │          [ 💾 Save Settings ]                    │ │
├──────────────┤  │  └──────────────────────────────────────────────┘ │
│ ⏱️ Reset     │                                                    │
├──────────────┤                                                    │
│ ℹ️ Overview  │                                                    │
└──────────────┴──────────────────────────────────────────────────────┘
```

### Tab 8 — Change Password (external form via `form=""` attr)

```
┌──────────────┬──────────────────────────────────────────────────────┐
│ 🌐 Site      │  ┌── Change Password ─────────────────────────────┐ │
│    Basics    │  │                                                  │ │
├──────────────┤  │  🔒 Current Password                            │ │
│ 🛡️ Maint    │  │  [ **************************            ]      │ │
│    & Sec     │  │                                                  │ │
├──────────────┤  │  🔑 New Password                                │ │
│ 📋 Comp Plan │  │  [ **************************            ]      │ │
├──────────────┤  │                                                  │ │
│ 🔄 Reactivat │  │  ✅ Confirm New Password                         │ │
├──────────────┤  │  [ **************************            ]      │ │
│ 💱 E-Wallet  │  │                                                  │ │
├──────────────┤  │  ─────────────────────────────────────────────── │ │
│ 💸 Payouts   │  │           [ 🔒 Update Password ]                 │ │
├──────────────┤  │  └──────────────────────────────────────────────┘ │
│ 📅 DFI       │                                                    │
├──────────────┤                                                    │
│ 🔒 Password* │                                                    │
├──────────────┤                                                    │
│ ⏱️ Reset     │                                                    │
├──────────────┤                                                    │
│ ℹ️ Overview  │                                                    │
└──────────────┴──────────────────────────────────────────────────────┘
```

### Tab 10 — System Overview (read-only)

```
┌──────────────┬──────────────────────────────────────────────────────┐
│ 🌐 Site      │  ┌── System Overview ─────────────────────────────┐ │
│    Basics    │  │                                                  │ │
├──────────────┤  │  PHP Version        │  8.1.25                    │ │
│ 🛡️ Maint    │  │─────────────────────│────────────────────────── │ │
│    & Sec     │  │  MySQL Version      │  8.0.35                    │ │
├──────────────┤  │─────────────────────│────────────────────────── │ │
│ 📋 Comp Plan │  │  Server Time        │  2026-06-23 10:58:00       │ │
├──────────────┤  │─────────────────────│────────────────────────── │ │
│ 🔄 Reactivat │  │  App URL            │  http://localhost/altas/   │ │
├──────────────┤  │─────────────────────│────────────────────────── │ │
│ 💱 E-Wallet  │  │  Environment        │  🟡 development            │ │
├──────────────┤  │─────────────────────│────────────────────────── │ │
│ 💸 Payouts   │  │  Members            │  42 / 1000                 │ │
├──────────────┤  │─────────────────────│────────────────────────── │ │
│ 📅 DFI       │  │  Binary Status      │  🟢 Enabled                │ │
├──────────────┤  │─────────────────────│────────────────────────── │ │
│ 🔒 Password  │  │  Maintenance        │  🟢 Off                    │ │
├──────────────┤  └──────────────────────────────────────────────────┘ │
│ ⏱️ Reset     │                                                    │
├──────────────┤                                                    │
│ ℹ️ Overview* │                                                    │
└──────────────┴──────────────────────────────────────────────────────┘
```

---

## Technical Implementation

### A. Frontend — Vertical Tab HTML Structure

```php
<div class="settings-layout">
  <!-- Left: Vertical Tab Nav -->
  <div class="settings-tabs">
    <nav class="nav flex-column nav-pills" role="tablist">
      <button class="nav-link active" data-bs-toggle="pill"
              data-bs-target="#tab-basics" type="button"
              role="tab">🌐 Site Basics</button>
      <button class="nav-link" data-bs-toggle="pill"
              data-bs-target="#tab-maint" type="button"
              role="tab">🛡️ Maintenance & Security</button>
      <!-- ... more tabs ... -->
    </nav>
  </div>

  <!-- Right: Tab Content -->
  <div class="tab-content settings-content">
    <div class="tab-pane fade show active" id="tab-basics" role="tabpanel">
      <form method="POST" action="...?page=admin_save_settings">
        <?= csrf_field() ?>
        <input type="hidden" name="group" value="basics">
        <div class="card">
          <div class="card-header">🌐 Site Basics</div>
          <div class="card-body">
            <!-- form fields -->
            <button type="submit" class="btn btn-primary w-100">
              💾 Save Settings
            </button>
          </div>
        </div>
      </form>
    </div>
    <!-- ... more panes ... -->
  </div>
</div>
```

### B. CSS — Flex Layout for Side-by-Side

```css
.settings-layout {
  display: flex;
  gap: 1.5rem;
  align-items: flex-start;
}
.settings-tabs {
  flex: 0 0 260px;
  position: sticky;
  top: 1rem;
}
.settings-content {
  flex: 1;
  min-width: 0;
}
.settings-tabs .nav-link {
  text-align: left;
  padding: 0.65rem 1rem;
  border-radius: 0.5rem;
  margin-bottom: 0.25rem;
  font-size: 0.85rem;
  color: #475569;
}
.settings-tabs .nav-link.active {
  background: #eef2ff;
  color: #4f46e5;
  font-weight: 600;
}
```

### C. Backend — Grouped Save

Modify `saveSettings()` to accept a `group` POST param:

```php
$groups = [
    'basics'         => ['site_name','site_tagline','contact_email','min_payout'],
    'maint_security' => ['maintenance_mode','maintenance_bypass_token','seat_limit'],
    'comp_plan'      => ['binary_enabled','indirect_referral_enabled','default_cap_multiplier','pv_per_peso_rate'],
    'reactivation'   => ['reactivation_ewallet_enabled','reactivation_external_enabled','gcash_number','maya_number','usdt_trc20_address','usdt_bep20_address'],
    'ewallet_transfer'=> ['ewallet_transfer_fee','ewallet_min_transfer','ewallet_transfer_daily_limit','ewallet_transfer_weekly_limit'],
    'payouts'        => ['gcash_enabled','maya_enabled','service_fee_gcash','service_fee_maya','service_fee_usdt_trc20','service_fee_usdt_bep20','usdt_trc20_gas_fee','usdt_bep20_gas_fee'],
    'dfi'            => ['dfi_enabled'],
];

$group = $_POST['group'] ?? null;
$allowed = $group && isset($groups[$group]) ? $groups[$group] : [];
```

Checkbox guards (binary_enabled, indirect_referral_enabled) stay in place. Only settings in the current group are saved.

### D. URL Hash Routing

Store active tab in the URL hash so it survives page reload:

```js
// On tab show, update hash
document.querySelectorAll('[data-bs-toggle="pill"]').forEach(tab => {
  tab.addEventListener('shown.bs.tab', function (e) {
    history.replaceState(null, '', '#tab-' + e.target.getAttribute('data-bs-target').replace('#tab-', ''));
  });
});

// On page load, activate tab from hash
const hash = window.location.hash || '#tab-basics';
const tab = document.querySelector(`[data-bs-target="${hash}"]`);
if (tab) bootstrap.Tab.getOrCreateInstance(tab).show();
```

---

## Save Flow

```
User clicks "Save Settings" in Tab 1
  │
  ├─ POST /?page=admin_save_settings
  │     group=basics
  │     site_name=...
  │     site_tagline=...
  │     contact_email=...
  │     min_payout=...
  │
  ├─ AdminController::saveSettings()
  │     reads $_POST['group'] → 'basics'
  │     $allowed = $groups['basics']
  │     only saves those 4 keys
  │     flash('success', 'Site Basics saved.')
  │
  └─ redirect('/?page=admin_settings#tab-basics')
```

**Key benefit:** Each tab's form is completely independent. No other tabs' fields are sent or saved. If validation fails for a binary_enabled toggle switch, only that tab's form is rejected — other tabs are not affected.

---

## Files to Modify

| File | Changes |
|------|---------|
| `views/admin/settings.php` | Complete rewrite — replace 2-column row with vertical tabs + tab-panes |
| `controllers/AdminController.php` | Modify `saveSettings()` — accept `group` param, restrict to group-specific keys |
| `views/partials/head.php` or inline | Add CSS for `.settings-layout`, `.settings-tabs`, `.settings-content` |

---

## Tab Implementation Details

### Tab 1 — Site Basics
- **group=basics**
- Same fields as current "Site Basics" card
- Single Save button at bottom of card body

### Tab 2 — Maintenance & Security
- **group=maint_security**
- Same fields as current "Maintenance & Security" card
- Keep the "Locked out?" warning box as-is

### Tab 3 — Compensation Plan
- **group=comp_plan**
- Keep binary_enabled and indirect_referral_enabled toggles with their guard messages
- Keep PV Conversion Rate box with gradient background

### Tab 4 — Reactivation
- **group=reactivation**
- E-Wallet / External checkboxes
- GCash/Maya/USDT address fields
- No Save button for admin payment accounts? Actually yes — all in one form

### Tab 5 — E-Wallet Transfers
- **group=ewallet_transfer**
- Transfer fee, min, daily limit, weekly limit — 2x2 grid

### Tab 6 — Payout Methods
- **group=payouts**
- GCash/Maya enable toggles
- All 4 service fee % fields
- TRC20/BEP20 gas fees (2x2)
- The GCash/Maya enable switches need special treatment: they're checkboxes so unchecked = absent from POST

### Tab 7 — Daily Fixed Income
- **group=dfi**
- Just the dfi_enabled toggle switch (single field)

### Tab 8 — Change Password
- **No group needed** (separate endpoint `save_profile`)
- Uses `form=""` attribute pointing to external form `#changePasswordForm`
- Fields and button inside the tab-pane but linked via `form=""` attribute to the external hidden form
- Same approach as current implementation (hidden external form at top of page)

### Tab 9 — Daily Pair Cap Reset
- **No group needed** (separate endpoint `admin_manual_reset`)
- Uses `form=""` attribute pointing to `#manualResetForm`
- Shows last reset time, crontab instruction, checkbox for DFI trigger

### Tab 10 — System Overview
- **Read-only** (no save button)
- Table with PHP version, MySQL version, Server Time, App URL, Env, Members, Binary, Maintenance
- Pure static display

---

## What Stays the Same

- All setting keys (no DB schema changes)
- Flash messages (`render_flash()`)
- CSRF tokens
- Checkbox handling (explicit '0' when unchecked)
- Binary/indirect-referral toggle guards
- External forms for password change and daily reset

---

## Edge Cases

1. **Hash mismatch on redirect**: After saving, redirect to `#tab-{group}` so user lands on the tab they just saved.
2. **Tab order preservation on validation error**: If save fails (e.g., guard blocks), the flash error is shown and user stays on the same tab.
3. **Sticky tab nav**: `.settings-tabs` uses `position: sticky` so the tab list follows the user as they scroll tall forms.
4. **Responsive**: On screens < 768px, tabs stack horizontally at the top (like the repeat purchases btn-group filter style) to save vertical space.
5. **Password/Reset tabs**: These tabs don't save via the main settings handler. When clicked, their content is self-contained with their own submit buttons pointing to separate endpoints via the `form=""` attribute.

---

## Responsive Behavior

```
Desktop (> 992px):    [  Tab Nav (270px)  |  Content (flex)         ]
Tablet (768-992px):   [  Tab Nav (200px)  |  Content (flex)         ]
Mobile (< 768px):     [  Tab Nav (horizontal btn-group, scrollable)  ]
                      [  Content (full width below tabs)             ]
```

On mobile, the vertical tab list transforms into a horizontal scrolling `btn-group` at the top, similar to the repeat purchases page filter. The active tab content appears below.

---

## Migration Path

1. Rewrite `views/admin/settings.php` with the new tab layout
2. Update `AdminController::saveSettings()` to accept `group` param
3. No DB migration needed (no schema changes)
4. Test each tab individually: load tab, modify a field, save, verify in DB
5. Test responsive collapse on mobile
6. Clean up any unused CSS from old layout
