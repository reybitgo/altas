# Unilevel Product Bonus — Full UI Mirror Plan

## Overview

Mirror every Indirect Referral UI element for Unilevel Product across member dashboard, earnings, genealogy, cap status, admin user_view, and admin reports.

---

## Legend

| Symbol | Meaning |
|--------|---------|
| ✅ | Done (backend only — Commission, Product, Admin products/settings) |
| 🏗️ | Needs implementation |
| 🔁 | Mirrors existing Indirect Referral pattern |

---

## 1. MemberController.php — Data Methods

### 1.1 `dashboard()`

**Indirect Referral (existing):**
- Query: `SELECT COALESCE(SUM(amount),0) FROM commissions WHERE user_id=? AND type='indirect_referral' AND DATE(created_at)=CURDATE()`
- `$today['total_indirect_referral']` passed to view

**Unilevel Product (to add):**
- Query: `SELECT COALESCE(SUM(amount),0) FROM commissions WHERE user_id=? AND type='unilevel_product' AND DATE(created_at)=CURDATE()`
- Add `$today['total_unilevel_product']` to the `$today` array
- Pass to view

**Files:** `controllers/MemberController.php`

---

### 1.2 `earnings()`

**Indirect Referral (existing):**
- `$indirect_today` = today's indirect_referral sum
- `$total_indirect` = all-time indirect_referral sum
- `$indirectReferralTree` = array built from `User::indirectReferralTree()` (10-level sponsor chain with counts)

**Unilevel Product (to add):**
- `$unilevel_product_today` = today's unilevel_product sum
- `$total_unilevel_product` = all-time unilevel_product sum
- `$unilevelProductTree` = array built from new `User::productUnilevelTree()` (10-level sponsor chain with product counts)

**Files:** `controllers/MemberController.php`

---

### 1.3 `genealogy()`

**Indirect Referral (existing):**
- Tab "Referral Tree" rendered via `User::indirectReferralTree($userId, 10)` — returns array of `[username, level, status, avatar, placement, total_personal_pv]`
- Rendered in table with avatar, name, level badge, status badge

**Unilevel Product (to add):**
- Tab "Unilevel Product" rendered via new `User::productUnilevelTree($userId, 10)` — returns array of `[username, level, status, avatar, placement, total_personal_pv, total_product_pv]` (same structure + product PV)
- Note: This is the same sponsor chain as indirect referral but shows product-related info
- Could be a new tab or integrated into existing referral tree with a toggle

**Decision:** New tab "Product Unilevel" in genealogy, showing same sponsor chain but highlighting product PV and product-level info.

**Files:** `controllers/MemberController.php`, `views/member/genealogy.php`

---

### 1.4 `capStatus()` / `apiCapStatus()`

**Indirect Referral (existing):**
- Timeline entry: `indirect_referral` — shows when cap was reached, breakdown of paired PV vs commissions earned
- `apiCapStatus()` returns JSON with timeline entries and breakdown per commission type

**Unilevel Product (to add):**
- Timeline entry: `unilevel_product` — show when cap was reached
- Breakdown in the `commission_type_breakdown` table

**Files:** `controllers/MemberController.php`

---

## 2. Member Dashboard View

**File:** `views/member/dashboard.php`

### 2.1 Stat Cards (top row)

**Indirect Referral (existing):**
```php
<div class="stat-card">
    <div class="stat-icon bg-info">
        <i class="fas fa-sitemap"></i>
    </div>
    <div class="stat-content">
        <h3><?= fmt_money($today['total_indirect_referral'] ?? 0) ?></h3>
        <p>Indirect Referral Today</p>
    </div>
</div>
```

**Unilevel Product (to add):**
```php
<div class="stat-card">
    <div class="stat-icon bg-warning">
        <i class="fas fa-box"></i>
    </div>
    <div class="stat-content">
        <h3><?= fmt_money($today['total_unilevel_product'] ?? 0) ?></h3>
        <p>Product Unilevel Today</p>
    </div>
</div>
```

### 2.2 Recent Activity — Type Map

**Indirect Referral (existing):**
```php
$typeMap = [
    'indirect_referral' => 'Indirect Referral',
    // ...
];
$typeName = $typeMap[$comm['type']] ?? ucwords(str_replace('_', ' ', $comm['type']));
```

**Unilevel Product (to add):**
- Add `'unilevel_product' => 'Product Unilevel'` to `$typeMap`

### 2.3 Recent Activity — Icons

**Indirect Referral (existing):**
```php
$iconMap = [
    'indirect_referral' => 'fa-sitemap',
    // ...
];
```

**Unilevel Product (to add):**
- Add `'unilevel_product' => 'fa-box'` to `$iconMap`

---

## 3. Member Earnings View

**File:** `views/member/earnings.php`

### 3.1 Stat Cards

**Indirect Referral (existing):**
```php
<div class="stat-card">
    <h3><?= fmt_money($total_indirect) ?></h3>
    <p>Total Indirect Referral Bonus</p>
    <small>Today: <?= fmt_money($indirect_today) ?></small>
</div>
```

**Unilevel Product (to add):**
```php
<div class="stat-card">
    <h3><?= fmt_money($total_unilevel_product) ?></h3>
    <p>Total Product Unilevel Bonus</p>
    <small>Today: <?= fmt_money($unilevel_product_today) ?></small>
</div>
```

### 3.2 Filter Tabs

**Indirect Referral (existing):**
```php
$filter = $_GET['filter'] ?? 'all';
// tab: <a href="?page=member_earnings&filter=indirect_referral">Indirect Referral</a>
```

**Unilevel Product (to add):**
- Add tab: `<a href="?page=member_earnings&filter=unilevel_product">Product Unilevel</a>`

### 3.3 Commission History Rows

**Indirect Referral (existing):**
```php
$history = Commission::history($userId, $filter, $limit, $offset);
// row shows: amount, description, date
```

**Unilevel Product (handled):**
- Already supported by `Commission::history()` since it accepts the commission type string directly

### 3.4 CD Ledger Section

**Indirect Referral (existing):**
- CD ledger entries filtered by `source = 'indirect_referral'`

**Unilevel Product (to add):**
- Filter by `source = 'unilevel_product'`

### 3.5 Referral Tree (Indirect)

**Indirect Referral (existing):**
```php
<div class="tree-section">
    <h4>Indirect Referral Tree</h4>
    <?php $tree = User::indirectReferralTree($userId, 10); ?>
    <?php foreach ($tree as $node): ?>
        <div class="tree-node level-<?= $node['level'] ?>">
            <span class="badge badge-level">Level <?= $node['level'] ?></span>
            <div class="member-info">
                <img src="<?= $node['avatar'] ?>" class="avatar-sm">
                <span><?= e($node['username']) ?></span>
                <span class="badge badge-<?= $node['status'] ?>"><?= $node['status'] ?></span>
                <span>PV: <?= fmt_money($node['total_personal_pv']) ?></span>
            </div>
        </div>
    <?php endforeach; ?>
</div>
```

**Unilevel Product (to add):**
```php
<div class="tree-section">
    <h4>Product Unilevel Tree</h4>
    <?php $tree = User::productUnilevelTree($userId, 10); ?>
    <?php foreach ($tree as $node): ?>
        <div class="tree-node level-<?= $node['level'] ?>">
            <span class="badge badge-level">Level <?= $node['level'] ?></span>
            <div class="member-info">
                <img src="<?= $node['avatar'] ?>" class="avatar-sm">
                <span><?= e($node['username']) ?></span>
                <span class="badge badge-<?= $node['status'] ?>"><?= $node['status'] ?></span>
                <span>Prod PV: <?= fmt_money($node['total_product_pv'] ?? 0) ?></span>
            </div>
        </div>
    <?php endforeach; ?>
</div>
```

---

## 4. Member Genealogy View

**File:** `views/member/genealogy.php`

### 4.1 Tabs

**Indirect Referral (existing):**
```php
<div class="tab-nav">
    <a href="?page=member_genealogy&tab=referral" class="tab-link <?= $tab === 'referral' ? 'active' : '' ?>">Referral Tree</a>
    <a href="?page=member_genealogy&tab=binary" class="tab-link <?= $tab === 'binary' ? 'active' : '' ?>">Binary Tree</a>
</div>
```

**Unilevel Product (to add):**
- Add tab: `<a href="?page=member_genealogy&tab=product_unilevel" class="tab-link <?= $tab === 'product_unilevel' ? 'active' : '' ?>">Product Unilevel</a>`

### 4.2 Tab Content

**Indirect Referral (existing) — `tab=referral`:**
```php
<?php if ($tab === 'referral'): ?>
    <div class="tree-container">
    <?php foreach ($referralTree as $node): ?>
        <!-- same tree-node pattern as earnings -->
    <?php endforeach; ?>
    </div>
<?php endif; ?>
```

**Unilevel Product (to add) — `tab=product_unilevel`:**
```php
<?php if ($tab === 'product_unilevel'): ?>
    <div class="tree-container">
    <?php foreach ($productUnilevelTree as $node): ?>
        <div class="tree-node level-<?= $node['level'] ?>">
            <span class="badge badge-level">Level <?= $node['level'] ?></span>
            <div class="member-info">
                <img src="<?= $node['avatar'] ?>" class="avatar-sm">
                <span><?= e($node['username']) ?></span>
                <span class="badge badge-<?= $node['status'] ?>"><?= $node['status'] ?></span>
                <span>Prod PV: <?= fmt_money($node['total_product_pv'] ?? 0) ?></span>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
<?php endif; ?>
```

---

## 5. Member Cap Status View

**File:** `views/member/cap_status.php`

### 5.1 Timeline Entries

**Indirect Referral (existing):**
```php
foreach ($timeline as $entry):
    if ($entry['commission_type'] === 'indirect_referral'):
        // show timeline card with cap info
    endif;
endforeach;
```

**Unilevel Product (to add):**
- Add handling for `$entry['commission_type'] === 'unilevel_product'`
- Display same timeline card format with different icon/color
- Icon: `fa-box`, Color: `warning`

### 5.2 Breakdown Table

**Indirect Referral (existing):**
```php
<?php if ($entry['commission_type'] === 'indirect_referral'): ?>
    <tr>
        <td>Indirect Referral</td>
        <td>✔️</td>
        <td><?= fmt_money($entry['total_paired_pv'] ?? 0) ?></td>
        <td><?= fmt_money($entry['total_earned'] ?? 0) ?></td>
    </tr>
<?php endif; ?>
```

**Unilevel Product (to add):**
```php
<?php if ($entry['commission_type'] === 'unilevel_product'): ?>
    <tr>
        <td>Product Unilevel</td>
        <td>✔️</td>
        <td><?= fmt_money($entry['total_paired_pv'] ?? 0) ?></td>
        <td><?= fmt_money($entry['total_earned'] ?? 0) ?></td>
    </tr>
<?php endif; ?>
```

---

## 6. Admin User View

**File:** `views/admin/user_view.php`

### 6.1 Commission Type Map

**Indirect Referral (existing):**
```php
$typeMap = [
    'indirect_referral' => 'Indirect Referral',
    // ...
];
```

**Unilevel Product (to add):**
- Add `'unilevel_product' => 'Product Unilevel'` to `$typeMap`

---

## 7. New Method: `User::productUnilevelTree()`

**File:** `models/User.php`

**Purpose:** Walk sponsor chain up to 10 levels (same as `indirectReferralTree()`), but also include total product PV per member.

**Signature:** `public static function productUnilevelTree(int $userId, int $maxLevels = 10): array`

**Logic (mirrors `indirectReferralTree()`):**
1. Start with `$userId` as the root (level 0, skipped in output)
2. Walk sponsor chain: `SELECT referred_by FROM users WHERE id = :current_id`
3. Collect each upline member at levels 1–10
4. For each member, query total product PV (sum of product PV from entries that generated commissions)
5. Return array with keys: `username`, `level`, `status`, `avatar`, `placement`, `total_personal_pv`, `total_product_pv`

**SQL for product PV:**
```sql
SELECT COALESCE(SUM(ci.pv), 0) AS total_product_pv
FROM commission_items ci
JOIN commissions c ON c.id = ci.commission_id
WHERE c.user_id = :user_id
  AND c.type IN ('repeat_purchase', 'unilevel_product')
```

**Note:** If `indirectReferralTree()` uses `User::getUserWithSponsor()`, follow same pattern for consistency.

---

## 8. Product PV in Existing Queries

### 8.1 `Commission::summary()`

**Indirect Referral (existing):**
```php
$sql = "SELECT
    COALESCE(SUM(CASE WHEN type = 'indirect_referral' THEN amount ELSE 0 END), 0) AS total_indirect_referral,
    ...
FROM commissions WHERE user_id = ?";
```

**Unilevel Product (already added in previous session ✅):**
```php
COALESCE(SUM(CASE WHEN type = 'unilevel_product' THEN amount ELSE 0 END), 0) AS total_unilevel_product,
```

### 8.2 `Commission::history()`

**Indirect Referral (existing):**
- Accepts `$type` filter string
- When `$type` is not `'all'`, adds `AND c.type = :type` to WHERE clause

**Unilevel Product (already handled ✅):**
- Works automatically since it's just a string filter value

---

## 9. Files Changed Summary

| File | Change |
|------|--------|
| `controllers/MemberController.php` | Add unilevel_product today/all-time queries in `dashboard()`, `earnings()`; add productUnilevelTree to `genealogy()`; add cap status for unilevel_product in `capStatus()` |
| `views/member/dashboard.php` | Add stat card; add typeMap/iconMap entries |
| `views/member/earnings.php` | Add stat card; add filter tab; add tree section; add CD ledger filter |
| `views/member/genealogy.php` | Add "Product Unilevel" tab + tree content |
| `views/member/cap_status.php` | Add timeline + breakdown for unilevel_product |
| `views/admin/user_view.php` | Add typeMap entry |
| `models/User.php` | Add `productUnilevelTree()` static method |

---

## 10. Implementation Order

1. **`controllers/MemberController.php`** — Add data queries
2. **`models/User.php`** — Add `productUnilevelTree()` method
3. **`views/member/earnings.php`** — Full mirror (most complex view)
4. **`views/member/dashboard.php`** — Stat card + activity type
5. **`views/member/genealogy.php`** — New tab + tree
6. **`views/member/cap_status.php`** — Timeline + breakdown
7. **`views/admin/user_view.php`** — Type label

---

## 11. Verification Checklist

- [ ] Dashboard stat card shows today's unilevel product bonus
- [ ] Dashboard recent activity shows unilevel_product entries with proper icon/name
- [ ] Earnings page stat cards show total + today for unilevel_product
- [ ] Earnings page filter tab filters by unilevel_product
- [ ] Earnings page shows Product Unilevel Tree (10-level sponsor chain)
- [ ] Earnings page CD ledger shows unilevel_product entries
- [ ] Genealogy page has "Product Unilevel" tab showing tree
- [ ] Cap status page shows unilevel_product timeline + breakdown
- [ ] Admin user_view shows "Product Unilevel" label for unilevel_product commissions
- [ ] Commission history correctly filters by unilevel_product
- [ ] Product PV column appears in tree nodes
- [ ] No regression on existing indirect_referral functionality
