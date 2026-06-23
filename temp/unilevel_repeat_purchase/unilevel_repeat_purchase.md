# Unilevel Bonuses (10 Levels) for Repeat Purchase Products

> **Plan v1.0** — 23 June 2026
> **Scope:** Add per-product 10-level Unilevel Bonus to the repeat-purchase commission engine, gated by the same PV Gate (`personal_pv_requirement`) used in Binary Repeat Purchase.

---

## 1. Overview

### What It Does

When an active member buys a product (via the repeat-purchase checkout flow), the **entire sponsor chain up to 10 levels** receives a cash bonus based on a percentage of the **product's effective PV**, converted to pesos via the global `pv_per_peso_rate`. This mirrors exactly how the existing **Indirect Referral Bonus** works for package registrations, but scoped to **products**.

### What It Reuses

| Component | Source |
|-----------|--------|
| Sponsor chain walk (10 levels) | `Commission::processIndirectReferral()` |
| PV-to-peso formula | `Package::indirectReferralBonus()` |
| CD split + Lifetime cap check | `Commission::processIndirectReferral()` |
| PV Gate (`personal_pv_requirement`) | `Commission::meetsPersonalPvRequirement()` |
| Commission audit table (`commissions`) | Existing schema |
| Per-product level configuration UI (10 inputs) | `views/admin/packages.php` lines 309–327 |
| Global toggle setting | `indirect_referral_enabled` pattern |

### What Is New

1. **DB table**: `product_unilevel_levels` — stores 10 pv_pct values per product
2. **Product model**: `getUnilevelLevels()`, `withUnilevelLevels()`, updated `save()`
3. **AdminController**: `saveProduct()` collects `unilevel_1`..`unilevel_10` POST fields
4. **View**: Collapsible `Unilevel Bonuses (10 Levels)` section in product edit modal
5. **Commission::processProductUnilevel()** — new 10-level sponsor chain processor
6. **Commission::processProductPV()** — integration point to call the new processor
7. **Migration**: `030_add_product_unilevel_levels.sql`
8. **Settings entry**: `unilevel_product_enabled` (default `1`)

---

## 2. Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────────┐
│  ADMIN: Products Edit Modal                                         │
│  ┌──────────────────────────────────────────────────────────────┐   │
│  │  Product Name | Price | PV | PV % | Stock | Status | Image   │   │
│  ├──────────────────────────────────────────────────────────────┤   │
│  │  ▼ Unilevel Bonuses (10 Levels)          [enabled globally]  │   │
│  │  ┌────┬────┬────┬────┬────┬────┬────┬────┬────┬────┐        │   │
│  │  │ L1 │ L2 │ L3 │ L4 │ L5 │ L6 │ L7 │ L8 │ L9 │ L10│  %     │   │
│  │  └────┴────┴────┴────┴────┴────┴────┴────┴────┴────┘        │   │
│  │  PV Gate: uses each upline's package personal_pv_requirement │   │
│  └──────────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────────┘

                          │  POST saveProduct()
                          ▼
┌─────────────────────────────────────────────────────────────────────┐
│  AdminController::saveProduct()                                      │
│  1. Collect unilevel_1 .. unilevel_10 from $_POST                   │
│  2. Pass to Product::save($data, $id)                              │
└─────────────────────────────────────────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────────────────┐
│  Product::save()                                                     │
│  → DELETE FROM product_unilevel_levels WHERE product_id = ?         │
│  → INSERT INTO product_unilevel_levels (product_id, level, pv_pct)  │
│    (loop 1..10)                                                      │
└─────────────────────────────────────────────────────────────────────┘

                          │
                          ▼
┌─────────────────────────────────────────────────────────────────────┐
│  Order Approval Flow                                                 │
│  (admin approve, or ewallet auto-approve)                            │
└─────────────────────────────────────────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────────────────┐
│  Commission::processProductPV($orderId)                              │
│                                                                      │
│  Step 1: Buyer receives Personal PV  ───────────────────────────────┐│
│  Step 2: Group PV flows up sponsor chain (gated)                    ││
│  Step 3a: Binary PV to buyer's leg                                  ││
│  Step 3b: Binary PV flows up binary tree (gated)                    ││
│  ★ NEW Step 4: Unilevel Product Bonus (10 levels)                   ││
│               ┌────────────────────────────────────────────────────┐ ││
│               │  Commission::processProductUnilevel($orderId)      │ ││
│               │                                                   │ ││
│               │  For each product line item in the order:         │ ││
│               │    $effPv = item.total_pv                         │ ││
│               │    $levels = Product::getUnilevelLevels(productId)│ ││
│               │    Walk sponsor chain 1..10:                      │ ││
│               │      ├─ Skip if inactive                          │ ││
│               │      ├─ Skip if fails PV Gate                     │ ││
│               │      ├─ Bonus = effPv × (pct/100) × rate         │ ││
│               │      ├─ CD split                                  │ ││
│               │      ├─ Lifetime cap check                        │ ││
│               │      ├─ INSERT INTO commissions                   │ ││
│               │      └─ Credit e-wallet                           │ ││
│               └────────────────────────────────────────────────────┘ ││
└───────────────────────────────────────────────────────────────────────┘

                          │
                          ▼
┌─────────────────────────────────────────────────────────────────────┐
│  commissions table: type='unilevel_product', level=N,               │
│                     amount=GROSS, cap_deduction=blocked             │
│  ewallet_ledger: credited net amount                                │
│  cd_ledger: CD split trail                                          │
└─────────────────────────────────────────────────────────────────────┘
```

---

## 3. Sponsor Chain Walk Flow (per product line item)

```
Order Item: Product X, qty=1, effPV=50.00
  │
  ├─ Config: Product X has levels [L1=3%, L2=2%, L3=1%, L4-L10=0%]
  │
  ├─ Step: Walk sponsor chain from buyer.sponsor_id
  │
  ├─ Level 1 → Upline A (sponsor of buyer)
  │   ├─ active?                → yes
  │   ├─ meets PV Gate?         → A.personal_pv >= A.package.personal_pv_requirement?
  │   │                            if YES → pay 50.00 × (3/100) × 1000 = ₱1,500.00
  │   │                            if NO  → skip (no bonus, continue to next upline)
  │   └─ move up to A.sponsor_id
  │
  ├─ Level 2 → Upline B (sponsor of A)
  │   ├─ active?                → yes
  │   ├─ meets PV Gate?         → if YES → pay 50.00 × (2/100) × 1000 = ₱1,000.00
  │   └─ move up
  │
  ├─ Level 3 → Upline C (sponsor of B)
  │   ├─ active?                → no (suspended)
  │   └─ skip, move up (no bonus for level 3, continue to level 4)
  │
  ├─ Level 4 → Upline D (sponsor of C)
  │   ├─ active?                → yes
  │   ├─ meets PV Gate?         → yes
  │   ├─ pct configured?        → 0% → no bonus (skip)
  │   └─ move up
  │
  ├─ Level 5-10 → no more uplines or 0% → stop
  │
  └─ Done
```

---

## 4. Database Schema Changes

### New Table: `product_unilevel_levels`

```sql
CREATE TABLE product_unilevel_levels (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED     NOT NULL,
  level      TINYINT UNSIGNED NOT NULL,
  pv_pct     DECIMAL(5,2)     NOT NULL DEFAULT 0.00
             COMMENT 'Unilevel product bonus = product_eff_pv * (pv_pct/100) * pv_per_peso_rate',
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  UNIQUE KEY uq_product_level (product_id, level)
) ENGINE=InnoDB;
```

**Design notes:**
- `ON DELETE CASCADE` — deleting a product removes its levels automatically
- `DECIMAL(5,2)` — allows up to 999.99%, step 0.01
- `DEFAULT 0.00` — disabled per level unless explicitly set
- Uses the same schema pattern as `package_indirect_levels` for consistency

### ALTER `commissions.type` ENUM

```sql
ALTER TABLE commissions
  MODIFY COLUMN type
    ENUM('pairing','direct_referral','indirect_referral','daily_fixed_income','unilevel_product')
    NOT NULL;
```

### ALTER `cd_ledger.type` ENUM

```sql
ALTER TABLE cd_ledger
  MODIFY COLUMN type
    ENUM('pairing','direct_referral','indirect_referral','unilevel_product')
    NOT NULL;
```

### New Settings Entry (in migration + seed)

```sql
INSERT INTO settings (key_name, value) VALUES ('unilevel_product_enabled', '1');
```

---

## 5. Product Model — New Methods (`models/Product.php`)

### `getUnilevelLevels(int $productId): array`

Same pattern as `Package::getIndirectLevels()`:

```php
public static function getUnilevelLevels(int $productId): array
{
    $st = db()->prepare(
        'SELECT level, pv_pct FROM product_unilevel_levels WHERE product_id = ? ORDER BY level'
    );
    $st->execute([$productId]);
    $rows   = $st->fetchAll();
    $result = [];
    foreach ($rows as $r) {
        $result[(int)$r['level']] = (float)$r['pv_pct'];
    }
    return $result;
}
```

### `withUnilevelLevels(int $id): ?array`

Attaches unilevel levels to a product array for the edit view:

```php
public static function withUnilevelLevels(int $id): ?array
{
    $product = self::find($id);
    if (!$product) return null;
    $product['unilevel_levels'] = self::getUnilevelLevels($id);
    return $product;
}
```

### Updated `save()` — Add unilevel levels persistence

Inside `Product::save()`, after the INSERT/UPDATE block, add:

```php
// Save unilevel levels
$pdo->prepare("DELETE FROM product_unilevel_levels WHERE product_id = ?")
    ->execute([$id]);
$st = $pdo->prepare("INSERT INTO product_unilevel_levels (product_id, level, pv_pct) VALUES (?, ?, ?)");
for ($lvl = 1; $lvl <= 10; $lvl++) {
    $pvPct = (float)($data['unilevel_levels'][$lvl] ?? 0);
    $st->execute([$id, $lvl, $pvPct]);
}
```

### Bonus Helper: `unilevelProductBonus(float $effPv, float $pvPct): float`

Same formula as indirect referral, extracted for clarity:

```php
public static function unilevelProductBonus(float $effPv, float $pvPct): float
{
    if ($pvPct <= 0) return 0.00;
    $rate = (float)setting('pv_per_peso_rate', '1.0000');
    return $effPv * ($pvPct / 100) * $rate;
}
```

---

## 6. Admin Controller — `saveProduct()` Changes (`controllers/AdminController.php`)

### Collect Unilevel Levels from POST

After the existing form-data collection, add:

```php
$data['unilevel_levels'] = [];
for ($lvl = 1; $lvl <= 10; $lvl++) {
    $data['unilevel_levels'][$lvl] = (float)($_POST["unilevel_{$lvl}"] ?? 0);
}

// Preserve existing levels when the section was not rendered (toggle disabled)
if ($id && !isset($_POST['unilevel_1'])) {
    $existingLevels = Product::getUnilevelLevels($id);
    if (!empty($existingLevels)) {
        $data['unilevel_levels'] = $existingLevels;
    }
}
```

### `products()` method — Load unilevel levels for edit

Update the edit-product fetch to include levels:

```php
if (isset($_GET['edit'])) {
    $editProduct = Product::withUnilevelLevels((int)$_GET['edit']);
}
```

(This requires `withUnilevelLevels()` to be implemented in `Product`.)

### Validation (optional but recommended)

```php
foreach ($data['unilevel_levels'] as $lvl => $pct) {
    if ($pct < 0 || $pct > 100) {
        flash('error', "Unilevel Level {$lvl} must be between 0 and 100.");
        redirect($backUrl);
    }
}
```

---

## 7. Form UI — `products.php` View

### New HTML Section (inside the modal body, after the Status field)

```html
<!-- ── Unilevel Bonuses (10 Levels) ── -->
<?php if (setting('unilevel_product_enabled', '1') === '1'): ?>
<div class="mb-0">
  <label class="form-label fw-bold">📊 Unilevel Bonuses (10 Levels)</label>
  <div class="row g-2">
    <?php $ulvls = $editProduct['unilevel_levels'] ?? [];
    for ($lvl = 1; $lvl <= 10; $lvl++): ?>
      <div class="col-6 col-md-4">
        <label class="form-label" style="font-size:.72rem;">Level <?= $lvl ?></label>
        <div class="input-group input-group-sm">
          <input type="number" name="unilevel_<?= $lvl ?>" id="unilevel_<?= $lvl ?>"
                 class="form-control" inputmode="decimal" min="0" max="100" step="0.01"
                 value="<?= e($ulvls[$lvl] ?? 0) ?>" placeholder="0.00">
          <span class="input-group-text">%</span>
        </div>
      </div>
    <?php endfor; ?>
  </div>
  <div class="form-text mt-1">
    Set 0 to disable a level. Percentages are applied to the product's effective PV.
    Each upline must also meet their package's Personal PV Requirement (PV Gate).
    Total unilevel ≈ <span id="unilevelPreview" class="font-mono">₱0.00</span>
  </div>
</div>
<hr class="my-3">
<?php endif; ?>
```

### Live Preview JavaScript

```javascript
// ── Unilevel Bonus preview ──
function updateUnilevelPreview() {
  const pv = parseFloat(document.getElementById('prodProductPv')?.value) || 0;
  const pct = parseFloat(document.getElementById('prodPv')?.value) || 0;
  const effPv = pv * (pct / 100);
  const rate = <?= (float)setting('pv_per_peso_rate', '1000.0000') ?>;
  let totalPeso = 0;
  for (let lvl = 1; lvl <= 10; lvl++) {
    const input = document.getElementById('unilevel_' + lvl);
    const lvlPct = parseFloat(input?.value) || 0;
    totalPeso += effPv * (lvlPct / 100) * rate;
  }
  document.getElementById('unilevelPreview').textContent =
    '₱' + totalPeso.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// Attach listeners to all unilevel inputs
for (let lvl = 1; lvl <= 10; lvl++) {
  const el = document.getElementById('unilevel_' + lvl);
  if (el) el.addEventListener('input', updateUnilevelPreview);
}
// Also update when product_pv or pv_value changes
if (prodPvInput) prodPvInput.addEventListener('input', updateUnilevelPreview);
if (prodPctInput) prodPctInput.addEventListener('input', updateUnilevelPreview);
```

### Modal Form Reset

In `resetProductForm()`, add:

```javascript
for (let lvl = 1; lvl <= 10; lvl++) {
  const el = document.getElementById('unilevel_' + lvl);
  if (el) el.value = '0';
}
```

---

## 8. Commission Engine — New Processor (`core/Commission.php`)

### `processProductUnilevel(int $orderId): void`

```php
public static function processProductUnilevel(int $orderId): void
{
    // Guard: global toggle
    if (setting('unilevel_product_enabled', '1') !== '1') {
        return;
    }

    $order = RepeatPurchaseOrder::find($orderId);
    if (!$order || $order['status'] !== 'approved') return;

    $buyerId = (int)$order['member_id'];
    $buyer   = User::find($buyerId);
    if (!$buyer || $buyer['status'] !== 'active') return;

    // Load order items to process per-product
    $items = RepeatPurchaseOrderItem::findByOrderId($orderId);
    if (empty($items)) return;

    $pdo = db();
    $rate = (float)setting('pv_per_peso_rate', '1.0000');

    foreach ($items as $item) {
        $productId = (int)$item['product_id'];
        $effPv     = (float)$item['total_pv'];   // already effective PV × qty
        if ($effPv <= 0) continue;

        // Load per-product unilevel percentages
        $levels = Product::getUnilevelLevels($productId);
        if (empty($levels)) continue;

        // Walk sponsor chain from the buyer's sponsor
        $cur = (int)$buyer['sponsor_id'];
        $visited = [$buyerId => true];

        for ($lvl = 1; $lvl <= 10; $lvl++) {
            if ($cur <= 0 || isset($visited[$cur])) break;
            $visited[$cur] = true;

            $upline = User::find($cur);
            if (!$upline) break;

            // Skip inactive uplines (still move up)
            if ($upline['status'] !== 'active') {
                $cur = (int)$upline['sponsor_id'];
                continue;
            }

            // PV Gate: skip uplines who don't meet their personal PV requirement
            if (!self::meetsPersonalPvRequirement($cur)) {
                $cur = (int)$upline['sponsor_id'];
                continue;
            }

            $pvPct = (float)($levels[$lvl] ?? 0);
            if ($pvPct <= 0) {
                $cur = (int)$upline['sponsor_id'];
                continue;
            }

            // Calculate bonus
            $bonus = $effPv * ($pvPct / 100) * $rate;

            // ── Same as indirect referral processing ──
            // 1. CD split
            $cdSplit    = CdStatus::fillBucket($cur, $bonus);
            $cdPortion  = $cdSplit['cd'];
            $walletPortion = $cdSplit['wallet'];
            $cdStatusId = $cdSplit['cd_status_id'] ?? null;

            // 2. Lifetime cap check
            $capBlocked   = 0.00;
            $actualWallet = 0.00;
            if ($walletPortion > 0) {
                $capCheck     = CapEngine::canEarn($cur, $walletPortion);
                $actualWallet = $capCheck['allowed'];
                $capBlocked   = $capCheck['blocked'];
            }

            // 3. Build description
            $desc = "Unilevel Product L{$lvl} Bonus";
            if ($cdPortion > 0) {
                $desc .= sprintf(' — %s to CD', fmt_money($cdPortion));
                if ($actualWallet > 0) {
                    $desc .= sprintf(', %s to wallet', fmt_money($actualWallet));
                }
            }

            // 4. INSERT commission (GROSS)
            $pdo->prepare("
                INSERT INTO commissions
                  (user_id, type, amount, cap_deduction, source_user_id, level, description, status)
                VALUES (?, 'unilevel_product', ?, ?, ?, ?, ?, 'credited')
            ")->execute([$cur, $bonus, $capBlocked, $buyerId, $lvl, $desc]);

            $commId = (int)$pdo->lastInsertId();

            // 5. Credit e-wallet (net of cap)
            if ($actualWallet > 0) {
                Ewallet::credit($cur, $actualWallet, $commId, 'commission', "Unilevel Product L{$lvl} Bonus");
            }
            if ($capBlocked > 0) {
                self::recordCapBlocked($cur, $capBlocked, 'unilevel_product', $buyerId, $lvl);
            }

            // 6. CD ledger
            if ($cdPortion > 0 && $cdStatusId) {
                CdStatus::recordLedger(
                    $cur, $cdStatusId, $commId, 'unilevel_product',
                    $bonus, $cdPortion, $actualWallet, $buyerId
                );
            }

            // 7. Record cap earning
            if ($actualWallet > 0) {
                CapEngine::recordEarning($cur, $actualWallet, 'unilevel_product');
            }

            // Move up sponsor chain
            $cur = (int)$upline['sponsor_id'];
        }
    }
}
```

### Integration in `processProductPV()`

Add as Step 4, after binary processing:

```php
public static function processProductPV(int $orderId): void
{
    // ... existing steps 1-3 ...

    // 3b. Product PV also flows up the binary tree
    self::processBinaryPV($memberId, $totalPv);

    // ★ NEW Step 4: Unilevel Product Bonus (10 levels via sponsor chain)
    self::processProductUnilevel($orderId);
}
```

### Update `recordCapBlocked()` Description Switch

Add a new match arm:

```php
'unilevel_product' => "Unilevel Product L{$level} blocked — lifetime cap reached",
```

---

## 9. Integration Points Summary

| Trigger Point | File | Line | Action |
|---|---|---|---|
| Admin saves product | `AdminController::saveProduct()` | ~322 | Collect `unilevel_1..10` from POST |
| Product model persists | `Product::save()` | ~100 | DELETE+INSERT into `product_unilevel_levels` |
| Load product for edit | `AdminController::products()` | ~315 | Call `Product::withUnilevelLevels()` |
| Order approved (admin) | `RepeatPurchaseOrder::approve()` | ~213 | Already calls `processProductPV()` — no change needed |
| Order auto-approved (ewallet) | `MemberController::placeOrder()` | ~378 | Already calls `processProductPV()` — no change needed |
| Commission engine | `Commission::processProductPV()` | ~477 | Add call to `processProductUnilevel()` |
| Lifetime cap check | `Commission::recordCapBlocked()` | ~610 | Add `'unilevel_product'` match arm |
| Commission summary | `Commission::summary()` | ~636 | SQL already includes COALESCE; returns all types. Add `unilevel_product` to selective sums if needed |
| Admin settings page | `views/admin/settings.php` | ~150 | Add toggle for `unilevel_product_enabled` |

---

## 10. Settings Toggle UI

In `views/admin/settings.php`, add alongside the `binary_repeat_enabled` section:

```html
<!-- Unilevel Product Bonus -->
<div class="rounded p-3 mb-3" style="background:#f8fafc;border:1px solid #e2e8f0;">
  <div class="form-check form-switch mb-2">
    <input class="form-check-input" type="checkbox" name="unilevel_product_enabled"
           id="unilevelProductEnabled" value="1"
           <?= setting('unilevel_product_enabled', '1') === '1' ? 'checked' : '' ?>>
    <label class="form-check-label" for="unilevelProductEnabled" style="font-weight:700;font-size:.85rem;">
      Enable Unilevel Product Bonus
    </label>
  </div>
  <div style="font-size:.78rem;color:var(--muted);line-height:1.6;padding-left:2.4rem;">
    When enabled, upline sponsors earn a 10-level unilever cash bonus on each product purchase
    (gated by each upline's Personal PV Requirement). When disabled, the Unilevel Bonus section
    is hidden from the product edit form and no unilevel commissions are processed.
  </div>
</div>
```

In `AdminController::saveSettings()`, add `'unilevel_product_enabled'` to both the `$compPlanSettings` array and the `$checkboxKeys` array:

```php
'comp_plan' => ['binary_enabled', 'binary_repeat_enabled', 'unilevel_product_enabled', ...],
$checkboxKeys = [..., 'binary_repeat_enabled', 'unilevel_product_enabled', ...];
```

Add a toggle guard similar to `indirect_referral_enabled`:

```php
if ($key === 'unilevel_product_enabled' && $value === '0') {
    $memberCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'member'")->fetchColumn();
    if ($memberCount > 0) {
        flash('warning', "Cannot disable Unilevel Product Bonus — members already exist.");
        continue;
    }
}
```

---

## 11. Migration Script

**File:** `migrations/030_add_product_unilevel_levels.sql`

```sql
-- ════════════════════════════════════════════════════════════
--  Migration 030: Add product-level unilevel bonus support
-- ════════════════════════════════════════════════════════════

-- New table: stores 10 unilevel percentages per product
CREATE TABLE IF NOT EXISTS product_unilevel_levels (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED     NOT NULL,
  level      TINYINT UNSIGNED NOT NULL,
  pv_pct     DECIMAL(5,2)     NOT NULL DEFAULT 0.00
             COMMENT 'Unilevel product bonus = product_eff_pv * (pv_pct/100) * pv_per_peso_rate',
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  UNIQUE KEY uq_product_level (product_id, level)
) ENGINE=InnoDB;

-- Extend commissions.type ENUM to include unilevel_product
ALTER TABLE commissions
  MODIFY COLUMN type
    ENUM('pairing','direct_referral','indirect_referral','daily_fixed_income','unilevel_product')
    NOT NULL;

-- Extend cd_ledger.type ENUM to include unilevel_product
ALTER TABLE cd_ledger
  MODIFY COLUMN type
    ENUM('pairing','direct_referral','indirect_referral','unilevel_product')
    NOT NULL;

-- Global toggle setting (default enabled)
INSERT INTO settings (key_name, value) VALUES ('unilevel_product_enabled', '1')
  ON DUPLICATE KEY UPDATE value = '1';

-- Index for quick lookup by product
ALTER TABLE product_unilevel_levels
  ADD INDEX idx_product_level (product_id, level);
```

---

## 12. Bonus Formula Reference

```
Effective PV of product (per order item) = item.quantity × item.unit_pv

Unilevel Bonus (pesos) = eff_PV × (level_pv_pct / 100) × pv_per_peso_rate

Example:
  Product A: product_pv=50.00, pv_value=100% → eff_pv=50.00
  Unilevel L1: 3% → 50 × (3/100) × 1000 = ₱1,500.00
  Unilevel L2: 2% → 50 × (2/100) × 1000 = ₱1,000.00

Order with 3× Product A + 2× Product B:
  Item 1: Product A, qty=3, effPV=50×3=150
  Item 2: Product B, qty=2, effPV=30×2=60
  → Process each item separately through the sponsor chain
  → Total potential unilevel = sum of all item-level bonuses
```

---

## 13. Commission Table ENUM Changes

| Current `commissions.type` | After Migration |
|---------------------------|-----------------|
| `pairing` | `pairing` |
| `direct_referral` | `direct_referral` |
| `indirect_referral` | `indirect_referral` |
| `daily_fixed_income` | `daily_fixed_income` |
| | `unilevel_product` ← **NEW** |

| Current `cd_ledger.type` | After Migration |
|-------------------------|-----------------|
| `pairing` | `pairing` |
| `direct_referral` | `direct_referral` |
| `indirect_referral` | `indirect_referral` |
| | `unilevel_product` ← **NEW** |

---

## 14. PV Gate Integration Details

The same `meetsPersonalPvRequirement()` check used in `processBinaryPV()` is applied:

```php
private static function meetsPersonalPvRequirement(int $userId): bool
{
    $user = User::find($userId);
    if (!$user) return false;
    $pkg = Package::find((int)$user['package_id']);
    $req = (float)($pkg['personal_pv_requirement'] ?? 0);
    if ($req <= 0) return true;            // gate disabled
    return (float)$user['personal_pv'] >= $req;
}
```

**Behavior for Unilevel Product Bonus:**

| Upline's `personal_pv` >= `personal_pv_requirement` | Action |
|---|---|
| `requirement = 0.00` (disabled) | Always passes → pays bonus |
| `requirement > 0` AND `personal_pv >= requirement` | Passes → pays bonus |
| `requirement > 0` AND `personal_pv < requirement` | Fails → skip upline, continue to next |
| Upline status not `active` | Skip upline (same as before) |

If an upline fails the PV gate, the **level does not advance** — the same level is attempted on the next upline in the sponsor chain. This matches exactly how `processIndirectReferral()` handles inactive uplines (level advances to the next active upline).

---

## 15. Edge Cases

| Scenario | Handling |
|----------|----------|
| Product has all levels at 0% | No `unilevel_product` commissions recorded; walk continues up chain but finds all 0% so nothing is paid |
| `unilevel_product_enabled` is OFF | Entire processor skipped; form hidden in admin |
| Buyer is inactive | Processor exits early — no bonuses paid |
| No active uplines in 10 levels | Loop exits via `$cur <= 0` break |
| Upline at level N is suspended | Skipped (no bonus), level counter increments, next upline is checked |
| Same upline encountered twice (cycle) | `$visited` array prevents infinite loop |
| Product deleted while referenced by order item | `product_unilevel_levels` cascade-deleted; `Product::getUnilevelLevels()` returns empty array; no bonus for that item |
| `pv_per_peso_rate` is 0 | Calculator returns 0 for all bonuses; no commissions recorded |
| Multiple items in one order | Each item processed independently through sponsor chain; totals accumulate on same commission types |
| Per-product levels differ in the same order | Each line item uses its own product's level percentages |
| Order has mixed active/inactive products | Only active products are purchasable; all are active at time of order |
| Upline meets PV gate for one product but not another | Gate check is per-upline, not per-product; consistent across all items in the same order |
| Lifetime cap reached mid-chain | Bonus capped for that upline only; remaining levels continue normally for higher uplines |
| CD bucket active for upline | Bonus split into CD portion + wallet portion before cap check |
| Bulk migration of existing products | Existing products get no `product_unilevel_levels` rows (all 0%); no behavioral change until admin configures them |

---

## 16. Files Changed Summary

| File | Change Type | Notes |
|------|-------------|-------|
| `models/Product.php` | **Edit** | Add `getUnilevelLevels()`, `withUnilevelLevels()`, `unilevelProductBonus()`, update `save()` |
| `controllers/AdminController.php` | **Edit** | `products()` → load with levels; `saveProduct()` → collect/preserve levels |
| `views/admin/products.php` | **Edit** | Add Unilevel form section, live preview JS, form reset |
| `views/admin/settings.php` | **Edit** | Add `unilevel_product_enabled` toggle |
| `core/Commission.php` | **Edit** | Add `processProductUnilevel()`, integrate in `processProductPV()`, update `recordCapBlocked()` |
| `migrations/030_add_product_unilevel_levels.sql` | **New** | Table + ENUM ALTERs + setting + index |

---

## 17. Testing Checklist (Manual)

1. **Admin > Products > Edit Product**: Verify Unilevel Bonuses section appears with 10 level inputs
2. **Create new product**: Set L1=3%, L2=2%, save, reopen → values persist
3. **Disable toggle**: Set `unilevel_product_enabled=0` in settings → section disappears from form
4. **Enable toggle + create order**: Member buys product with unilevel levels configured
5. **Approve order**: Verify `commissions` table has rows with `type='unilevel_product'`, correct `level` and `amount`
6. **PV Gate test**: Set upline's package `personal_pv_requirement=500`; give upline 0 personal_pv → upline gets no unilevel bonus; next upline up gets it
7. **PV Gate disabled**: Set `personal_pv_requirement=0` → bonus flows to all active uplines
8. **Inactive upline**: Suspend an upline → bonus skips past them
9. **Lifetime cap**: Set low cap → verify `cap_deduction` in commissions row
10. **CD split**: Assign CD to upline → verify CD + wallet split in commission description
11. **Multiple line items**: Order with 2+ products with different unilevel configs → verify each product triggers its own bonuses
12. **Commission summary**: Verify `type='unilevel_product'` appears in member commission history
