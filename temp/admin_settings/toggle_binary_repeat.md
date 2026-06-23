# `Enable Binary Repeat Purchase` — Implementation Plan

## 1. Problem / Context

Currently the system has a single toggle `binary_enabled` in **Admin → Settings → Compensation Plan**. When disabled, it:

- Suppresses all binary pairing bonuses
- Hides the binary placement UI during **registration**
- Causes `processBinaryPV()` and `processBinaryPlacement()` to early-return

**But it does NOT** gate the repeat-purchase binary position selector or the buyer's self-leg PV application in `processProductPV()`.

What the user wants is a **separate toggle** `binary_repeat_enabled` that controls whether repeat purchases participate in binary pairing. This allows scenarios like:

| binary_enabled | binary_repeat_enabled | Effect |
|---------------|----------------------|--------|
| ON | ON | **(Default)** Full binary: registration + repeat purchases both earn pairing |
| ON | OFF | Registration binary works, but repeat purchases skip binary PV entirely |
| OFF | OFF/Either | All binary disabled (registration placement hidden, no pairing anywhere) |

---

## 2. Architecture Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                    ADMIN SETTINGS (comp_plan)                     │
│                                                                   │
│  ☑ Enable Binary Pairing Bonuses        [binary_enabled]          │
│  ☑ Enable Binary Repeat Purchase   ◄──── [binary_repeat_enabled]  │
│  ☑ Enable Indirect Referral Bonuses      [indirect_referral_enabled]
│  ☑ Enable Daily Fixed Income             [dfi_enabled]            │
│  ...                                                            │
└───────────────────────────────────────┬─────────────────────────┘
                                        │ _POST['group'] = 'comp_plan'
                                        ▼
┌─────────────────────────────────────────────────────────────────┐
│                AdminController::saveSettings()                    │
│                                                                   │
│  - Adds 'binary_repeat_enabled' to $groupKeys['comp_plan']       │
│  - Adds 'binary_repeat_enabled' to $checkboxKeys                  │
│  - No "block if members exist" guard (safe to toggle anytime)     │
│  - Uses INSERT … ON DUPLICATE KEY UPDATE (same pattern)           │
└──────────┬──────────────────────────────────────────────┬────────┘
           │                                              │
           ▼                                              ▼
┌──────────────────────────┐            ┌──────────────────────────────┐
│   CHECKOUT VIEW          │            │   COMMISSION ENGINE           │
│                          │            │                              │
│  ┌──────────────────┐   │            │  processProductPV():         │
│  │ binary_repeat     │   │            │   ① Check setting before     │
│  │ enabled?          │───┼─────────►  │      buyer self-leg PV       │
│  │  ├─ YES: show     │   │            │   ② processBinaryPV()        │
│  │  │     Binary Pos │   │            │      already checks          │
│  │  │     section    │   │            │      binary_enabled —         │
│  │  │                │   │            │      add binary_repeat_enabled│
│  │  └─ NO: hide      │   │            │      check too               │
│  │       section     │   │            │                              │
│  └──────────────────┘   │            │  processBinaryPlacement():   │
│                          │            │    unchanged (registrations  │
│                          │            │    only, already gates       │
│                          │            │    on binary_enabled)         │
│                          │            │                              │
└──────────────────────────┘            └──────────────────────────────┘
```

---

## 3. Data Flow Diagrams

### 3A — Checkout Page Rendering (conditional)

```
Member visits /?page=checkout
          │
          ▼
   MemberController::checkout()
          │
          ▼
   Load cart, items, payment methods
          │
          ▼
   Check setting('binary_repeat_enabled', '1')
          │
          ├── '1' → $showBinaryPosition = true
          └── '0' → $showBinaryPosition = false
          │
          ▼
   Pass $showBinaryPosition to view
          │
          ▼
   views/member/checkout.php
          │
          ├── $showBinaryPosition === true:
          │     Render "Binary Position" card (Left/Right radios)
          │     + wire up JS event listeners
          │
          └── $showBinaryPosition === false:
                Omit the "Binary Position" card entirely
                + skip JS wiring for binary radios
```

### 3B — Order Placement (binary_repeat_enabled OFF)

```
Form POST /?page=place_order
          │
          ▼
   MemberController::placeOrder()
          │
          ▼
   $binaryPosition = ($_POST['binary_position'] ?? 'left') === 'right' ? 'right' : 'left';
          │
          ▼
   When binary_repeat_enabled is OFF:
     $binaryPosition = 'left'   (always defaults, is ignored)
          │
          ▼
   RepeatPurchaseOrder::createFromCart($memberId, $cartId, $binaryPosition, ...)
          │
          ▼
   Order saved with binary_position = 'left' (irrelevant — not used)
          │
          ▼
   If ewallet: Commission::processProductPV($orderId)
          │
          ▼
   processProductPV() checks binary_repeat_enabled:
     ├── steps 1-2 (personal/group PV) → ALWAYS run
     ├── step 3 (buyer self-leg PV) → SKIP if binary_repeat_enabled is OFF
     └── step 4 (binary tree flow) → SKIP if binary_repeat_enabled is OFF
```

### 3C — Admin Order Approval (binary_repeat_enabled OFF)

```
Admin approves repeat purchase
          │
          ▼
   RepeatPurchaseOrder::approve()   or   RepeatPurchase::approve()
          │
          ▼
   Commission::processProductPV($orderId)
          │
          ▼
   Same gating as 3B above
```

### 3D — Full Decision Logic in processProductPV()

```
processProductPV(orderId):
          │
          ▼
   Load order, member, validate
          │
          ▼
   Step 1: personal_pv += totalPv          ← ALWAYS
   Step 2: group_pv up sponsor chain       ← ALWAYS
   Step 3: buyer self-leg binary PV         ← ONLY IF binary_repeat_enabled
   Step 4: processBinaryPV() (upline flow)  ← ONLY IF binary_repeat_enabled
                                              AND binary_enabled (already exists)
```

---

## 4. Setting Dependency Matrix

```
                       binary_enabled
                       ┌────────┬─────────┐
                       │  ON     │  OFF    │
┌──────────────────────┼────────┼─────────┤
│ binary_repeat_enabled│        │         │
│        ON            │  Full  │  No     │
│                      │binary  │ binary  │
│                      │ (both) │ (both)  │
├──────────────────────┼────────┼─────────┤
│        OFF           │  Reg   │  No     │
│                      │binary  │ binary  │
│                      │ only   │ (both)  │
└──────────────────────┴────────┴─────────┘
```

When `binary_enabled` is OFF, `binary_repeat_enabled` is irrelevant — there is no binary to participate in. But the toggle is still useful: if an admin re-enables binary later, `binary_repeat_enabled` determines the repeat-purchase behavior without extra config.

---

## 5. File-by-File Changes

### 5.1 — Database Migration (`migrations/027_binary_repeat_enabled.sql`)

```sql
INSERT INTO settings (key_name, value) VALUES ('binary_repeat_enabled', '1')
ON DUPLICATE KEY UPDATE value = VALUES(value);
```

| Column | Value |
|--------|-------|
| key_name | `binary_repeat_enabled` |
| value | `'1'` (default enabled) |

No DDL changes needed — existing `settings` key-value table suffices. No column changes in `users` or `repeat_purchase_orders`.

---

### 5.2 — Admin Settings View  (`views/admin/settings.php`)

**Location**: Insert after the existing `binary_enabled` toggle (after line 149), before the Indirect Referral toggle (line 150).

**New HTML**:

```php
<!-- Binary Repeat Purchase -->
<div class="rounded p-3 mb-3" style="background:#f8fafc;border:1px solid #e2e8f0;">
  <div class="form-check form-switch mb-2">
    <input class="form-check-input" type="checkbox"
           name="binary_repeat_enabled"
           id="binaryRepeatEnabled"
           value="1"
           <?= setting('binary_repeat_enabled', '1') === '1' ? 'checked' : '' ?>>
    <label class="form-check-label" for="binaryRepeatEnabled"
           style="font-weight:700;font-size:.85rem;">
      Enable Binary Repeat Purchase
    </label>
  </div>
  <div style="font-size:.78rem;color:var(--muted);line-height:1.6;padding-left:2.4rem;">
    When enabled, members can choose Left/Right leg during checkout and
    product PV earns binary pairing bonuses. When disabled, the Binary
    Position selector is hidden on the checkout page and product PV
    does not trigger binary pairing. Toggleable anytime — no member
    reset required.
  </div>
</div>
```

**Rationale**: 
- Follows the identical pattern as `binary_enabled` / `indirect_referral_enabled` 
- No "block if members exist" guard because this is safe to toggle anytime (existing binary data is not corrupted on disable; on re-enable, PV flows normally going forward)
- Visual hint text explains the behavioral difference

---

### 5.3 — Admin Controller (`controllers/AdminController.php`)

**A) `$groupKeys['comp_plan']`** (line 577):

```php
'comp_plan' => [
    'binary_enabled',
    'binary_repeat_enabled',     // ← ADD
    'indirect_referral_enabled',
    'default_cap_multiplier',
    'pv_per_peso_rate',
    'dfi_enabled'
],
```

**B) `$checkboxKeys`** (line 593):

```php
$checkboxKeys = [
    'gcash_enabled',
    'maya_enabled',
    'dfi_enabled',
    'reactivation_ewallet_enabled',
    'reactivation_external_enabled',
    'indirect_referral_enabled',
    'binary_enabled',
    'binary_repeat_enabled',     // ← ADD
];
```

**C) No additional guard needed** — unlike `binary_enabled` and `indirect_referral_enabled`, `binary_repeat_enabled` does NOT affect historical commissions or tree structure. It only gates future behavior at checkout and in `processProductPV()`. Safe to toggle with existing members.

**If you want a softer guard**: display a confirmation flash message ("This will affect future repeat purchases only") but do not block.

---

### 5.4 — Checkout Controller (`controllers/MemberController.php`)

**A) `checkout()` method** (around line 256):

After loading the cart data and before rendering the view, add:

```php
$showBinaryPosition = setting('binary_repeat_enabled', '1') === '1';
```

Pass to view (existing view variables — no need to modify the `require` call).

**B) `placeOrder()` method** (line 307):

```php
// Old:
$binaryPosition = ($_POST['binary_position'] ?? 'left') === 'right' ? 'right' : 'left';

// New:
$binaryPosition = 'left'; // default (irrelevant when repeat binary is off)
if (setting('binary_repeat_enabled', '1') === '1') {
    $binaryPosition = ($_POST['binary_position'] ?? 'left') === 'right' ? 'right' : 'left';
}
```

This ensures:
- When toggle is **off**, `binary_position` is always `'left'` (a safe default — it's stored but never used)
- When toggle is **on**, user choice is respected
- Prevents POST manipulation (setting choice even when hidden)

---

### 5.5 — Checkout View (`views/member/checkout.php`)

**A) Wrap the "Binary Position" card** (lines 83–108) in a conditional:

```php
<?php if (!empty($showBinaryPosition)): ?>
<!-- Binary Position -->
<div class="card mb-3">
  ...
</div>
<?php endif; ?>
```

**B) JavaScript** (lines 250, 273–277, 281):

The JS code references `binaryRadios` and calls `updateSelected('binary_position', 'binary-option')`. These need guards:

```js
// At line 250 — only query if the section exists:
var binaryRadios = document.querySelectorAll('[name=binary_position]');

// At line 273 — guard event listener setup:
if (binaryRadios.length) {
  binaryRadios.forEach(function(r) {
    r.addEventListener('change', function() {
      updateSelected('binary_position', 'binary-option');
    });
  });
}

// At line 281 — guard initialization call:
if (binaryRadios.length) {
  updateSelected('binary_position', 'binary-option');
}
```

This is safe: `querySelectorAll` returns an empty NodeList when no radios exist, and we skip the calls when `length === 0`.

---

### 5.6 — Commission Engine (`core/Commission.php`)

**A) `processProductPV()`** (lines 430–473) — **THE KEY CHANGE**:

Check `binary_repeat_enabled` before steps 3 and 4:

```php
public static function processProductPV(int $orderId): void
{
    $order = RepeatPurchaseOrder::find($orderId);
    if (!$order || $order['status'] !== 'approved') return;

    $memberId = (int)$order['member_id'];
    $totalPv  = (float)$order['total_pv'];
    if ($totalPv <= 0.00) return;

    $buyer = User::find($memberId);
    if (!$buyer || $buyer['status'] !== 'active') return;

    $pdo = db();

    // 1. Buyer receives Personal PV (always)
    $pdo->prepare('UPDATE users SET personal_pv = personal_pv + ? WHERE id = ?')
        ->execute([$totalPv, $memberId]);
    self::recordPvTransaction($memberId, 'product_personal', $totalPv, $memberId, 'repeat_purchase');

    // 2. Group PV flows up the sponsor chain (always)
    $cur = (int)$buyer['sponsor_id'];
    $visited = [$memberId => true];
    while ($cur > 0 && !isset($visited[$cur])) {
        $upline = User::find($cur);
        if (!$upline) break;
        if ($upline['status'] === 'active' && self::meetsPersonalPvRequirement($cur)) {
            $pdo->prepare('UPDATE users SET group_pv = group_pv + ? WHERE id = ?')
                ->execute([$totalPv, $cur]);
            self::recordPvTransaction($cur, 'product_group', $totalPv, $memberId, 'repeat_purchase');
        }
        $visited[$cur] = true;
        $cur = (int)$upline['sponsor_id'];
    }

    // 3. Binary PV — only if binary repeat purchase is enabled
    if (setting('binary_repeat_enabled', '1') !== '1') {
        return; // no binary PV from repeat purchases
    }

    // 3a. Pay yourself first: buyer receives binary PV on their chosen leg
    $buyerSide = $order['binary_position'];
    self::applyBinaryPv($memberId, $buyerSide, $totalPv, $memberId, 'repeat_purchase');

    // 3b. Product PV also flows up the binary tree
    self::processBinaryPV($memberId, $totalPv);
}
```

> **Design rationale**: Steps 1 and 2 (personal/group PV) are always processed because they are **non-binary** features — group PV feeds unilevel/indirect referral bonuses, which is controlled by `indirect_referral_enabled`, not `binary_repeat_enabled`. Only steps 3a/3b (binary leg PV + binary tree flow) are gated.

**B) No change needed in `processBinaryPV()`** — it already checks `binary_enabled`. Adding a `binary_repeat_enabled` check here would create a double gate, but since `processBinaryPV()` is also called internally by `processBinaryPlacement()` (registration), we cannot add the check here. The gate must be at the caller level (`processProductPV()`), which is what the above change does.

**C) No change needed in `processBinaryPlacement()`** — registration binary is governed by `binary_enabled` only.

---

### 5.7 — Install SQL seed Data (`install.sql`)

Add to the settings INSERT block (around line 455–485):

```sql
('binary_repeat_enabled',            '1'),
```

---

## 6. Summary of All Changes

| # | File | Change Type | Lines |
|---|------|------------|-------|
| 1 | `migrations/027_binary_repeat_enabled.sql` | **New file** | 3 |
| 2 | `install.sql` | **Edit** (seed data) | +1 |
| 3 | `views/admin/settings.php` | **Edit** (new toggle HTML) | +16 |
| 4 | `controllers/AdminController.php` | **Edit** (2 array additions) | +2 |
| 5 | `controllers/MemberController.php` | **Edit** (checkout + placeOrder) | +6 |
| 6 | `views/member/checkout.php` | **Edit** (wrap card + guard JS) | +12 |
| 7 | `core/Commission.php` | **Edit** (early return in processProductPV) | +2 |

**Total: ~42 lines of meaningful changes** across 7 files.

---

## 7. Migration Sequence

1. Apply `migrations/027_binary_repeat_enabled.sql` to insert the default `'1'` setting
2. Deploy all code changes
3. Verify toggle appears in Admin → Settings → Compensation Plan
4. Verify checkout hides/shows binary position per toggle
5. Place test orders in both states, verify `pv_transactions` table for binary leg entries

---

## 8. Edge Cases & Considerations

| Edge Case | Behavior |
|-----------|----------|
| **Toggle OFF → existing approved orders** | No effect. `processProductPV()` already ran for those orders. Historical data unchanged. |
| **Toggle OFF → pending orders approved later** | Gated at `processProductPV()` call — order gets personal/group PV only, no binary leg or tree flow. |
| **Toggle ON during a pending order's lifetime** | Toggle is checked at approval time. If ON when admin approves, binary PV flows. |
| **binary_enabled OFF, binary_repeat_enabled ON** | Registration binary is still disabled (placement hidden). Repeat purchase `processProductPV()` attempts binary flow, but `applyBinaryPv()` and `processBinaryPV()` both check `binary_enabled` internally and will no-op. So toggle ON has no effect when `binary_enabled` is OFF. This is correct — the toggles are additive. |
| **binary_enabled ON, binary_repeat_enabled OFF** | **(Primary use case)** Registration binary works, but repeat purchases skip binary PV entirely. The binary position selector is hidden at checkout. |
| **Legacy systems with existing data** | The old `binary_position` column in `repeat_purchase_orders` may have stale data — this is fine; the column is simply not used when the toggle is off. |
| **POST injection** | Even if a malicious user sends `binary_position=right` via devtools when the UI is hidden, `processProductPV()` checks the setting and ignores the value. |
| **Admin non-ewallet approval** | `RepeatPurchaseOrder::approve()` and `RepeatPurchase::approve()` both call `processProductPV()` — gated correctly. |

---

## 9. Testing Scenarios (Manual QA)

| # | Scenario | Toggle State | Expected Result |
|---|----------|-------------|-----------------|
| T1 | Checkout page with toggle ON | ON | Binary Position card visible, Left/Right radios functional |
| T2 | Checkout page with toggle OFF | OFF | Binary Position card hidden |
| T3 | Place ewallet order (toggle ON) | ON | Personal PV + group PV + binary leg PV + binary tree PV all applied. Check `pv_transactions` for `product_personal`, `product_group`, and binary leg types. |
| T4 | Place ewallet order (toggle OFF) | OFF | Only personal PV + group PV applied. No binary leg or binary tree entries in `pv_transactions`. |
| T5 | Place external payment order, then admin approve (toggle OFF) | OFF | Approval calls `processProductPV()` → personal + group PV only. No binary. |
| T6 | Toggle ON → place order → toggle OFF → admin approves different order | OFF → ON → OFF | First order gets binary, second order does not. |
| T7 | binary_enabled OFF, binary_repeat_enabled ON | See matrix | Checkout shows binary position, but `processProductPV()` step 3/4 calls `applyBinaryPv` which also checks `binary_enabled` and no-ops. No binary PV flows. |

---

## 10. Database State After Change

```
settings table (relevant keys):
┌────────────────────────┬───────┐
│ key_name               │ value │
├────────────────────────┼───────┤
│ binary_enabled         │ 1     │
│ binary_repeat_enabled  │ 1     │   ← NEW
│ indirect_referral_enabled │ 1   │
│ dfi_enabled            │ 1     │
│ pv_per_peso_rate       │ 1000  │
│ default_cap_multiplier │ 3.00  │
└────────────────────────┴───────┘
```

No DDL changes. No new columns. No new tables.

---

## 11. Commit Checklist

- [ ] Migration SQL file created
- [ ] `install.sql` seed data updated
- [ ] Admin settings view toggle rendered
- [ ] `$groupKeys['comp_plan']` includes key
- [ ] `$checkboxKeys` includes key
- [ ] `MemberController::checkout()` passes flag to view
- [ ] `MemberController::placeOrder()` gates $_POST binary_position
- [ ] Checkout view conditionally wraps Binary Position card
- [ ] Checkout JS guards against missing binary radios
- [ ] `Commission::processProductPV()` early-returns before steps 3/4
- [ ] Migration applied to dev database
- [ ] T1–T7 manual tests pass
