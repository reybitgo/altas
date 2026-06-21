# Plan: Add "Keep existing products" checkbox to reset.php

## Overview

The `reset.php` tool currently has a **"Keep existing packages"** checkbox (`keep_packages`) that preserves `packages` and `package_indirect_levels` tables. We need a parallel **"Keep existing products"** checkbox (`keep_products`) to preserve the `products` table.

## Changes needed

### 1. PHP — POST handling (line ~33)

Add after `$keepPackages`:
```php
$keepProducts = isset($_POST['keep_products']);
```

### 2. PHP — Conditional products clear (after existing package block, line ~203)

After the `if (!$keepPackages) { ... }` block (after line 203), add a new block:

```php
if (!$keepProducts) {
    // Delete uploaded product images first
    $productImages = $pdo->query("SELECT image_url FROM products WHERE image_url IS NOT NULL")->fetchAll();
    foreach ($productImages as $row) {
        $path = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $row['image_url'];
        if ($row['image_url'] && is_file($path)) {
            unlink($path);
        }
    }
    $pdo->exec("DELETE FROM products");
    $pdo->exec("ALTER TABLE products AUTO_INCREMENT = 1");
    $logs[] = ['ok', 'Cleared all products'];
}
```

No re-seed is needed (unlike packages which re-seed "Starter") — the `install.sql` has no default product seed data, and products are created via the admin panel (`AdminController::saveProduct`). An empty products table is the clean state.

### 3. HTML — Checkbox (around line ~793, after the `keep_packages` checkbox)

Add a new checkbox row after the `keep_packages` check-row:

```html
<div class="form-group">
  <label>Products</label>
  <label class="check-row">
    <input type="checkbox" name="keep_products" checked id="keepProds">
    <div>
      <div class="check-label">Keep existing products</div>
      <div class="check-hint">Uncheck to delete all products and their uploaded images</div>
    </div>
  </label>
</div>
```

### 4. HTML — "What Will Be Cleared" list (lines ~754-767)

The existing static list always shows what will be cleared. Since the `keep_products` checkbox gives the user control, we have two options:

**Option A (dynamic):** Add a JS snippet that toggles a list item's visibility/text when the checkbox changes — similar to how the packages checkbox would ideally behave.

**Option B (static, simpler):** Add a line:
```html
<li id="productsClearItem"><span class="dot dot-red"></span><span class="text-red">All products</span></li>
```
And use JS to:
- Grey it out / strike-through when `keep_products` is checked
- Keep it red when unchecked
- Initially reflect the checkbox's default checked state

Given the existing code style, **Option B with a small JS toggle** is recommended.

### 5. HTML — UPDATE: confirm button formatting

Add `#keepProds` to the scope. No other changes needed.

### 6. Database integrity note

The `repeat_purchase_order_items` table has `FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT`. The reset already deletes all `repeat_purchase_order_items` and `repeat_purchase_orders` rows *before* reaching the products-clear block, so the FK constraint will not block the `DELETE FROM products`.

## Summary of user-facing behavior

| keep_products checked | keep_products unchecked |
|-----------------------|-------------------------|
| All products kept intact | Products table truncated, auto-increment reset, uploaded product images deleted |
| Repeat purchase orders/items still cleared (reset always does this) | Same |
| Carts still cleared | Same |

## File to modify

`C:\laragon\www\altas\reset.php`

## Total estimated changes

- 1 line: PHP variable capture (`$keepProducts`)
- ~10 lines: PHP conditional clear block
- ~8 lines: HTML checkbox row in the form
- ~1 line: HTML list item in "What Will Be Cleared"
- ~15 lines: JS to toggle the list item visual state
