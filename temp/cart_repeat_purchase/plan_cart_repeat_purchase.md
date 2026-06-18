# Shopping-Cart Repeat Purchase — Phased Development Plan

## Overview

5 phases, each self-contained. Every phase ends with a QA test that verifies the phase is fully working before proceeding. Phases build on previous ones — never skip phases.

| Phase | What it delivers | Depends on |
|-------|-----------------|------------|
| 1 | DB schema + models (no UI visible) | Nothing |
| 2 | Cart API endpoints + topbar badge | Phase 1 |
| 3 | Checkout flow + admin order review + commission rewrite | Phase 1, 2 |
| 4 | Member-facing UI (catalog, cart sidebar, checkout page) | Phase 1, 2, 3 |
| 5 | Admin order UI + settings cleanup | Phase 1, 3 |

---

## Phase 1 — Database Schema & Models

**Goal:** All new tables exist, old data is migrated, old table is dropped, model classes are ready. Nothing changes in the browser yet.

### 1.1 — Migration file

Create `migrations/027_add_cart_and_order_tables.sql`:

```sql
-- ============================================================
--  MIGRATION 027: Cart + order tables for repeat-purchase
--  redesign. Replaces the single-row repeat_purchases table
--  with a full cart + checkout + order model.
--  Run: mysql -u USER -p DATABASE < migrations/027_add_cart_and_order_tables.sql
-- ============================================================

-- 1. Add stock to products (absolute inventory, never mutated by orders)
ALTER TABLE products
  ADD COLUMN stock INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Total physical inventory';

-- 2. Add package-level personal PV requirement
ALTER TABLE packages
  ADD COLUMN personal_pv_requirement DECIMAL(14,2) NOT NULL DEFAULT 0.00
  COMMENT 'Minimum Personal PV an upline must have to receive Group/Binary PV from product purchases';

-- Seed existing packages from the old global setting (one-time only)
UPDATE packages SET personal_pv_requirement = COALESCE(
  (SELECT value FROM settings WHERE key_name = 'personal_pv_requirement'),
  '0.0000'
);

-- 3. Create carts table
CREATE TABLE carts (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  member_id  INT UNSIGNED NOT NULL,
  status     ENUM('active','abandoned','converted') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_member_active_cart (member_id, status),
  FOREIGN KEY (member_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 4. Create cart_items table
CREATE TABLE cart_items (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cart_id     INT UNSIGNED NOT NULL,
  product_id  INT UNSIGNED NOT NULL,
  quantity    INT UNSIGNED NOT NULL DEFAULT 1,
  unit_price  DECIMAL(12,2) NOT NULL,
  unit_pv     DECIMAL(14,2) NOT NULL,
  added_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_cart_product (cart_id, product_id),
  FOREIGN KEY (cart_id)    REFERENCES carts(id)    ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- 5. Create repeat_purchase_orders table (replaces repeat_purchases)
CREATE TABLE repeat_purchase_orders (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  member_id        INT UNSIGNED NOT NULL,
  total_pv         DECIMAL(14,2) NOT NULL,
  total_price      DECIMAL(12,2) NOT NULL,
  binary_position  ENUM('left','right') NOT NULL DEFAULT 'left' COMMENT 'Side used for buyer''s own leg PV placement',
  payment_method   ENUM('ewallet','gcash','maya','usdt_trc20','usdt_bep20') NOT NULL,
  proof_image      VARCHAR(255) NULL,
  status           ENUM('pending','paid','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
  approved_by      INT UNSIGNED NULL,
  approved_at      TIMESTAMP NULL,
  paid_at          TIMESTAMP NULL,
  created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (member_id)   REFERENCES users(id)    ON DELETE CASCADE,
  FOREIGN KEY (approved_by) REFERENCES users(id)    ON DELETE SET NULL
) ENGINE=InnoDB;

-- 6. Create repeat_purchase_order_items table
CREATE TABLE repeat_purchase_order_items (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id     INT UNSIGNED NOT NULL,
  product_id   INT UNSIGNED NOT NULL,
  quantity     INT UNSIGNED NOT NULL,
  unit_price   DECIMAL(12,2) NOT NULL,
  unit_pv      DECIMAL(14,2) NOT NULL,
  total_price  DECIMAL(12,2) NOT NULL,
  total_pv     DECIMAL(14,2) NOT NULL,
  FOREIGN KEY (order_id)   REFERENCES repeat_purchase_orders(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id)              ON DELETE RESTRICT
) ENGINE=InnoDB;

-- 7. Migrate existing repeat_purchases rows into new order tables
INSERT INTO repeat_purchase_orders
  (id, member_id, total_pv, total_price, binary_position, payment_method, proof_image, status, approved_by, approved_at, created_at)
SELECT
  id, member_id, total_pv, total_price, COALESCE(u.binary_position, 'left'), 'gcash',
  NULLIF(proof_image, ''),
  status,
  approved_by,
  approved_at,
  created_at
FROM repeat_purchases rp
JOIN users u ON u.id = rp.member_id;

INSERT INTO repeat_purchase_order_items
  (order_id, product_id, quantity, unit_price, unit_pv, total_price, total_pv)
SELECT
  rp.id, rp.product_id, rp.quantity,
  CASE WHEN rp.quantity > 0 THEN rp.total_price / rp.quantity ELSE COALESCE(p.price, 0) END,
  CASE WHEN rp.quantity > 0 THEN rp.total_pv / rp.quantity ELSE COALESCE(p.pv_value, 0) END,
  rp.total_price, rp.total_pv
FROM repeat_purchases rp
LEFT JOIN products p ON p.id = rp.product_id;

-- 8. Drop old table immediately
DROP TABLE repeat_purchases;

-- 9. Remove the global setting (moved to packages.personal_pv_requirement)
DELETE FROM settings WHERE key_name = 'personal_pv_requirement';
```

Apply the migration. Create `uploads/repeat_purchase_proofs/` directory (chmod 755).

### 1.2 — Update `install.sql`

Replace the old `repeat_purchases` table definition with the four new tables (same DDL as above). Add `stock` column to `products`. Add `personal_pv_requirement` column to `packages`. Remove the `personal_pv_requirement` row from settings seed data.

### 1.3 — `models/Product.php`: add stock helper

Add one public static method:

```php
/**
 * Returns the currently available stock for a product.
 * Available = stock - SUM(qty in pending/paid/approved order items).
 */
public static function availableStock(int $productId): int
{
    $pdo = db();
    $st = $pdo->prepare("
        SELECT p.stock - COALESCE(SUM(oi.quantity), 0) AS available
        FROM products p
        LEFT JOIN repeat_purchase_order_items oi ON oi.product_id = p.id
        LEFT JOIN repeat_purchase_orders o ON o.id = oi.order_id
            AND o.status IN ('pending','paid','approved')
        WHERE p.id = ?
        GROUP BY p.id
    ");
    $st->execute([$productId]);
    $row = $st->fetch();
    return $row ? (int)$row['available'] : 0;
}
```

No `decrementStock()` or `incrementStock()` — the reservation model does not mutate `products.stock`.

### 1.4 — `models/Cart.php`: new model

```php
<?php

/**
 * Cart model — one active cart per member, DB-backed.
 */
class Cart
{
    /**
     * Get or create the active cart for a member.
     */
    public static function getOrCreate(int $memberId): array
    {
        $pdo = db();
        $st = $pdo->prepare("SELECT * FROM carts WHERE member_id = ? AND status = 'active'");
        $st->execute([$memberId]);
        $cart = $st->fetch();
        if ($cart) return $cart;

        $pdo->prepare("INSERT INTO carts (member_id, status) VALUES (?, 'active')")
            ->execute([$memberId]);
        return self::getOrCreate($memberId);
    }

    /**
     * Add a product to the cart (upsert by product_id).
     */
    public static function addItem(int $cartId, int $productId, int $quantity): void
    {
        $pdo = db();
        $product = Product::find($productId);
        if (!$product || $product['status'] !== 'active') {
            throw new \RuntimeException('Product not available.');
        }
        if ($quantity < 1) {
            throw new \InvalidArgumentException('Quantity must be at least 1.');
        }

        $st = $pdo->prepare("SELECT id, quantity FROM cart_items WHERE cart_id = ? AND product_id = ?");
        $st->execute([$cartId, $productId]);
        $existing = $st->fetch();

        if ($existing) {
            $pdo->prepare("UPDATE cart_items SET quantity = quantity + ?, updated_at = NOW() WHERE id = ?")
                ->execute([$quantity, $existing['id']]);
        } else {
            $pdo->prepare("
                INSERT INTO cart_items (cart_id, product_id, quantity, unit_price, unit_pv)
                VALUES (?, ?, ?, ?, ?)
            ")->execute([$cartId, $productId, $quantity, $product['price'], $product['pv_value']]);
        }
    }

    /**
     * Update quantity for a cart item (absolute set, not increment).
     */
    public static function updateQty(int $itemId, int $quantity): void
    {
        if ($quantity < 1) {
            throw new \InvalidArgumentException('Quantity must be at least 1.');
        }
        db()->prepare("UPDATE cart_items SET quantity = ?, updated_at = NOW() WHERE id = ?")
            ->execute([$quantity, $itemId]);
    }

    /**
     * Remove an item from the cart.
     */
    public static function removeItem(int $itemId): void
    {
        db()->prepare("DELETE FROM cart_items WHERE id = ?")->execute([$itemId]);
    }

    /**
     * Get all items in a cart with product details.
     */
    public static function getItems(int $cartId): array
    {
        $st = db()->prepare("
            SELECT ci.*, p.name, p.image_url, p.short_description, p.status AS product_status, p.stock
            FROM cart_items ci
            JOIN products p ON p.id = ci.product_id
            WHERE ci.cart_id = ?
            ORDER BY ci.added_at
        ");
        $st->execute([$cartId]);
        return $st->fetchAll();
    }

    /**
     * Compute totals for a cart (PV and price).
     */
    public static function getTotals(int $cartId): array
    {
        $st = db()->prepare("
            SELECT SUM(quantity * unit_pv) AS total_pv,
                   SUM(quantity * unit_price) AS total_price
            FROM cart_items WHERE cart_id = ?
        ");
        $st->execute([$cartId]);
        return $st->fetch() ?: ['total_pv' => 0, 'total_price' => 0];
    }

    /**
     * Get item count for a member's active cart (for topbar badge).
     */
    public static function getItemCount(int $memberId): int
    {
        $pdo = db();
        $st = $pdo->prepare("
            SELECT COALESCE(SUM(ci.quantity), 0) AS cnt
            FROM carts c
            JOIN cart_items ci ON ci.cart_id = c.id
            WHERE c.member_id = ? AND c.status = 'active'
        ");
        $st->execute([$memberId]);
        return (int)$st->fetchColumn();
    }

    /**
     * Validate stock availability for all items in the cart.
     * Returns an array of error messages; empty array = all good.
     */
    public static function validateStock(int $cartId): array
    {
        $items = self::getItems($cartId);
        $errors = [];
        foreach ($items as $item) {
            $available = Product::availableStock((int)$item['product_id']);
            if ($item['quantity'] > $available) {
                $errors[] = sprintf(
                    '"%s" — requested %d, only %d available.',
                    $item['name'],
                    $item['quantity'],
                    $available
                );
            }
        }
        return $errors;
    }

    /**
     * Clear all items from the cart and mark it converted.
     */
    public static function clear(int $cartId): void
    {
        $pdo = db();
        $pdo->prepare("DELETE FROM cart_items WHERE cart_id = ?")->execute([$cartId]);
        $pdo->prepare("UPDATE carts SET status = 'converted' WHERE id = ?")->execute([$cartId]);
    }
}
```

### 1.5 — `models/RepeatPurchaseOrder.php`: new model

```php
<?php

/**
 * Repeat purchase order model — replaces the old single-row RepeatPurchase.
 */
class RepeatPurchaseOrder
{
    /**
     * Find an order by ID.
     */
    public static function find(int $id): ?array
    {
        $st = db()->prepare("SELECT * FROM repeat_purchase_orders WHERE id = ?");
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    /**
     * Find an order with its line items.
     */
    public static function findWithItems(int $id): ?array
    {
        $order = self::find($id);
        if (!$order) return null;
        $st = db()->prepare("
            SELECT oi.*, p.name, p.image_url
            FROM repeat_purchase_order_items oi
            JOIN products p ON p.id = oi.product_id
            WHERE oi.order_id = ?
        ");
        $st->execute([$id]);
        $order['items'] = $st->fetchAll();
        return $order;
    }

    /**
     * Paginated list of orders for a specific member.
     */
    public static function forMember(int $memberId, int $page = 1, int $perPage = 10): array
    {
        $pdo = db();
        $offset = ($page - 1) * $perPage;

        $countSt = $pdo->prepare("SELECT COUNT(*) FROM repeat_purchase_orders WHERE member_id = ?");
        $countSt->execute([$memberId]);
        $total = (int)$countSt->fetchColumn();

        $st = $pdo->prepare("
            SELECT o.* FROM repeat_purchase_orders o
            WHERE o.member_id = ?
            ORDER BY o.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $st->execute([$memberId, $perPage, $offset]);

        return [
            'data'  => $st->fetchAll(),
            'total' => $total,
            'pages' => max(1, (int)ceil($total / $perPage)),
        ];
    }

    /**
     * Paginated list of pending orders (for admin Mark Paid step).
     */
    public static function pending(int $page = 1, int $perPage = 10): array
    {
        return self::_paginated("WHERE o.status = 'pending'", $page, $perPage);
    }

    /**
     * Paginated list of paid orders awaiting approval.
     */
    public static function paid(int $page = 1, int $perPage = 10): array
    {
        return self::_paginated("WHERE o.status = 'paid'", $page, $perPage);
    }

    /**
     * Paginated list of all orders.
     */
    public static function all(int $page = 1, int $perPage = 10): array
    {
        return self::_paginated('', $page, $perPage);
    }

    private static function _paginated(string $where, int $page, int $perPage): array
    {
        $pdo = db();
        $offset = ($page - 1) * $perPage;

        $countSt = $pdo->prepare("SELECT COUNT(*) FROM repeat_purchase_orders o $where");
        $countSt->execute();
        $total = (int)$countSt->fetchColumn();

        $st = $pdo->prepare("
            SELECT o.*, u.username, u.full_name
            FROM repeat_purchase_orders o
            JOIN users u ON u.id = o.member_id
            $where
            ORDER BY o.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $st->execute([$perPage, $offset]);

        return [
            'data'  => $st->fetchAll(),
            'total' => $total,
            'pages' => max(1, (int)ceil($total / $perPage)),
        ];
    }

    /**
     * Create an order from a cart (called inside a DB transaction).
     * Returns the new order ID.
     */
    public static function createFromCart(
        int $memberId,
        int $cartId,
        string $binaryPosition,
        string $paymentMethod,
        ?string $proofImage = null
    ): int {
        $pdo = db();
        $totals = Cart::getTotals($cartId);

        $pdo->prepare("
            INSERT INTO repeat_purchase_orders
              (member_id, total_pv, total_price, binary_position, payment_method, proof_image, status)
            VALUES (?, ?, ?, ?, ?, ?, 'pending')
        ")->execute([
            $memberId,
            $totals['total_pv'],
            $totals['total_price'],
            $binaryPosition,
            $paymentMethod,
            $proofImage,
        ]);
        $orderId = (int)$pdo->lastInsertId();

        $items = Cart::getItems($cartId);
        $itemSt = $pdo->prepare("
            INSERT INTO repeat_purchase_order_items
              (order_id, product_id, quantity, unit_price, unit_pv, total_price, total_pv)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        foreach ($items as $item) {
            $itemSt->execute([
                $orderId,
                $item['product_id'],
                $item['quantity'],
                $item['unit_price'],
                $item['unit_pv'],
                $item['quantity'] * $item['unit_price'],
                $item['quantity'] * $item['unit_pv'],
            ]);
        }

        return $orderId;
    }

    /**
     * Mark a pending order as paid (admin step 1).
     */
    public static function markPaid(int $orderId, int $adminId): void
    {
        $pdo = db();
        $order = self::find($orderId);
        if (!$order || $order['status'] !== 'pending') {
            throw new \RuntimeException('Order cannot be marked paid in its current state.');
        }
        if (empty($order['proof_image'])) {
            throw new \RuntimeException('Cannot mark paid — no proof uploaded.');
        }
        $pdo->prepare("
            UPDATE repeat_purchase_orders
            SET status = 'paid', paid_at = NOW()
            WHERE id = ?
        ")->execute([$orderId]);
    }

    /**
     * Approve a paid order (admin step 2). Distributes PV.
     */
    public static function approve(int $orderId, int $adminId): void
    {
        $pdo = db();
        $order = self::find($orderId);
        if (!$order || $order['status'] !== 'paid') {
            throw new \RuntimeException('Order cannot be approved in its current state.');
        }

        $pdo->beginTransaction();
        try {
            $pdo->prepare("
                UPDATE repeat_purchase_orders
                SET status = 'approved', approved_by = ?, approved_at = NOW()
                WHERE id = ?
            ")->execute([$adminId, $orderId]);

            Commission::processProductPV($orderId);

            $pdo->commit();
        } catch (\Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Reject a pending or paid order. No PV distribution. Stock reservation
     * is released automatically (the order status filters out from availableStock).
     */
    public static function reject(int $orderId, int $adminId): void
    {
        $order = self::find($orderId);
        if (!$order || !in_array($order['status'], ['pending', 'paid'], true)) {
            throw new \RuntimeException('Order cannot be rejected in its current state.');
        }
        db()->prepare("UPDATE repeat_purchase_orders SET status = 'rejected' WHERE id = ?")
            ->execute([$orderId]);
    }

    /**
     * Cancel an order by the member (only if still pending).
     */
    public static function cancel(int $orderId, int $memberId): void
    {
        $order = self::find($orderId);
        if (!$order || $order['member_id'] !== $memberId || $order['status'] !== 'pending') {
            throw new \RuntimeException('Order cannot be cancelled.');
        }
        db()->prepare("UPDATE repeat_purchase_orders SET status = 'cancelled' WHERE id = ?")
            ->execute([$orderId]);
    }
}
```

### 1.6 — Update `index.php` autoloader

The `spl_autoload_register` in `index.php` only loads from `models/` and `controllers/`. No change needed — `Cart` and `RepeatPurchaseOrder` follow the same convention.

### 1.7 — Update cron scripts

Cron scripts (`cron/midnight_reset.php`, etc.) have their own `require_once` list. They don't need the new models unless they touch orders. No change needed for Phase 1.

### Phase 1 QA Test

```
┌─────────────────────────────────────────────────────────────┐
│  PHASE 1 QA — Database & Models                             │
│  Verify from the command line or phpMyAdmin.                │
│  No browser interaction required.                           │
└─────────────────────────────────────────────────────────────┘

Precondition: Migration 027 has been applied.

TC-101 — Products table has stock column
  → DESCRIBE products;
  → stock column exists (INT UNSIGNED, NOT NULL, DEFAULT 0).

TC-102 — Packages table has personal_pv_requirement column
  → DESCRIBE packages;
  → personal_pv_requirement column exists.
  → SELECT id, name, personal_pv_requirement FROM packages;
  → Every row has a value (should be seeded from old setting or 0.00).

TC-103 — New tables exist
  → SHOW TABLES LIKE 'carts';
  → SHOW TABLES LIKE 'cart_items';
  → SHOW TABLES LIKE 'repeat_purchase_orders';
  → SHOW TABLES LIKE 'repeat_purchase_order_items';
  → All four exist.

TC-104 — Old table is gone
  → SHOW TABLES LIKE 'repeat_purchases';
  → Empty set.

TC-105 — Data migration preserved old orders
  → SELECT COUNT(*) FROM repeat_purchase_orders;
  → Same count as was in repeat_purchases before migration.
  → SELECT o.*, oi.* FROM repeat_purchase_orders o
      JOIN repeat_purchase_order_items oi ON oi.order_id = o.id;
  → Every order has at least one line item with correct totals.

TC-106 — Global setting removed
  → SELECT * FROM settings WHERE key_name = 'personal_pv_requirement';
  → Empty set.

TC-107 — Product::availableStock() returns correctly
  → Run from a test script or tinker:
    $available = Product::availableStock(1);
    → Returns 0 (stock is 0 by default for existing products).

TC-108 — Cart model create + add + remove
  → Run from a test script:
    $cart = Cart::getOrCreate(1); // admin or any test member
    → Returns array with id, member_id, status='active'.
    Cart::addItem($cart['id'], 1, 2);
    $items = Cart::getItems($cart['id']);
    → count($items) === 1, $items[0]['quantity'] === 2.
    Cart::addItem($cart['id'], 1, 3); // upsert
    $items = Cart::getItems($cart['id']);
    → $items[0]['quantity'] === 5 (2+3).
    Cart::updateQty($items[0]['id'], 1);
    $items = Cart::getItems($cart['id']);
    → $items[0]['quantity'] === 1.
    $count = Cart::getItemCount(1);
    → $count === 1.
    Cart::removeItem($items[0]['id']);
    $items = Cart::getItems($cart['id']);
    → count($items) === 0.
    Cart::clear($cart['id']);
    → Cart marked converted, items deleted.

TC-109 — RepeatPurchaseOrder model works
  → Run from a test script:
    $order = RepeatPurchaseOrder::find(1);
    → Returns the migrated order or null if no orders exist.
    $list = RepeatPurchaseOrder::forMember(1);
    → Returns paginated array with 'data', 'total', 'pages'.

Pass criteria: All 9 test cases pass without errors.
```

---

## Phase 2 — Cart API & Topbar Badge

**Goal:** Members can add/update/remove cart items via POST endpoints. Topbar shows cart item count. No checkout yet — the existing `/` path still shows the old `repeat_purchases` page (unchanged in this phase).

### 2.1 — Add routes to `index.php`

Insert into the `$routes` array (alphabetically near existing repeat_purchase routes):

```php
'add_to_cart'        => ['MemberController', 'addToCart',        'member'],
'update_cart_item'   => ['MemberController', 'updateCartItem',    'member'],
'remove_cart_item'   => ['MemberController', 'removeCartItem',    'member'],
```

### 2.2 — Add controller methods to `MemberController.php`

```php
public function addToCart(): void
{
    Auth::guard('member');
    Auth::checkCsrfOrFail();

    $productId = (int)($_POST['product_id'] ?? 0);
    $quantity  = max(1, (int)($_POST['quantity'] ?? 1));

    try {
        $cart = Cart::getOrCreate(Auth::id());
        Cart::addItem((int)$cart['id'], $productId, $quantity);
        flash('Product added to cart.', 'success');
    } catch (\Exception $e) {
        flash($e->getMessage(), 'danger');
    }

    redirect('?page=repeat_purchases');
}

public function updateCartItem(): void
{
    Auth::guard('member');
    Auth::checkCsrfOrFail();

    $itemId   = (int)($_POST['item_id'] ?? 0);
    $quantity = max(1, (int)($_POST['quantity'] ?? 1));

    try {
        Cart::updateQty($itemId, $quantity);
        flash('Cart updated.', 'success');
    } catch (\Exception $e) {
        flash($e->getMessage(), 'danger');
    }

    redirect('?page=repeat_purchases');
}

public function removeCartItem(): void
{
    Auth::guard('member');
    Auth::checkCsrfOrFail();

    $itemId = (int)($_POST['item_id'] ?? 0);

    Cart::removeItem($itemId);
    flash('Item removed from cart.', 'success');

    redirect('?page=repeat_purchases');
}
```

### 2.3 — Add cart badge to `views/partials/topbar.php`

After the e-wallet balance block (for member users), insert:

```php
<?php if (Auth::isLoggedIn() && Auth::user()['role'] === 'member'): ?>
<?php
    $cartCount = 0;
    try {
        require_once __DIR__ . '/../../models/Cart.php';
        $cartCount = Cart::getItemCount(Auth::id());
    } catch (\Throwable $e) {
        // Silently ignore if table doesn't exist yet
    }
?>
<a href="?page=repeat_purchases" class="btn btn-sm btn-outline-light position-relative me-2">
    🛒
    <?php if ($cartCount > 0): ?>
    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:.65rem;">
        <?= $cartCount ?>
    </span>
    <?php endif; ?>
</a>
<?php endif; ?>
```

> Note: The `require_once` with `try/catch` guards against the rare case where the page is loaded before Phase 1 migration is run. Once Phase 1 is confirmed, this can be simplified to just `Cart::getItemCount()`.

### Phase 2 QA Test

```
┌─────────────────────────────────────────────────────────────┐
│  PHASE 2 QA — Cart API & Topbar Badge                       │
│  Browser-based. Log in as a member.                         │
└─────────────────────────────────────────────────────────────┘

Precondition: Phase 1 deployed and QA passed.
              1+ active products exist in the products table.
              Logged in as a test member (not admin).

TC-201 — Topbar shows cart badge
  → Navigate to any member page (e.g., Dashboard).
  → Topbar shows a cart icon/button with "0" (or no count if empty).
  → [PASS] if visible.

TC-202 — Add to cart via direct POST
  → Using browser dev tools or a REST client, POST to:
      ?page=add_to_cart
    with body:
      product_id=1&quantity=2&csrf_token=...
    (Get csrf_token from any member page's CSRF meta tag or hidden input.)
  → Redirect to ?page=repeat_purchases with success flash.
  → [PASS] if no error.

TC-203 — Cart item count increments
  → Refresh any member page.
  → Topbar badge shows count > 0.
  → [PASS] if badge reflects the added quantity.

TC-204 — Add same product again (upsert)
  → POST to ?page=add_to_cart with same product_id=1, quantity=3.
  → Topbar badge now shows 5 (2+3).
  → [PASS] if quantity accumulated.

TC-205 — Update cart item quantity
  → POST to ?page=update_cart_item with:
      item_id=<id from cart>&quantity=1&csrf_token=...
  → Flash "Cart updated."
  → [PASS] if no error.

TC-206 — Remove cart item
  → POST to ?page=remove_cart_item with:
      item_id=<id>&csrf_token=...
  → Flash "Item removed from cart."
  → Topbar badge drops to 0.
  → [PASS] if item removed.

TC-207 — CSRF protection works
  → POST to ?page=add_to_cart without csrf_token.
  → Error or redirect without success flash.
  → [PASS] if the action is rejected.

TC-208 — Validation: quantity < 1
  → POST to ?page=add_to_cart with quantity=0.
  → Flash error.
  → [PASS] if rejected.

Pass criteria: All 8 test cases pass.
```

---

## Phase 3 — Checkout & Admin Order Review

**Goal:** Members can place orders (e-wallet instant or external with proof). Admins can review, mark paid, approve, reject. PV distribution fires on approval. The member UI still uses the old `repeat_purchases` view (no cart UI yet — members use Phase 2's POST endpoints to build a cart, then the new checkout page).

### 3.1 — Add routes to `index.php`

```php
'checkout'                          => ['MemberController', 'checkout',              'member'],
'place_order'                       => ['MemberController', 'placeOrder',            'member'],
'admin_repeat_purchase_orders'      => ['AdminController',  'repeatPurchaseOrders',  'admin'],
'admin_mark_repeat_order_paid'      => ['AdminController',  'markRepeatOrderPaid',   'admin'],
'admin_approve_repeat_order'        => ['AdminController',  'approveRepeatOrder',    'admin'],
'admin_reject_repeat_order'         => ['AdminController',  'rejectRepeatOrder',     'admin'],
```

### 3.2 — Add member controller methods

#### `checkout()` — show checkout form

```php
public function checkout(): void
{
    Auth::guard('member');

    $cart = Cart::getOrCreate(Auth::id());
    $items = Cart::getItems((int)$cart['id']);

    if (empty($items)) {
        flash('Your cart is empty.', 'warning');
        redirect('?page=repeat_purchases');
    }

    $totals = Cart::getTotals((int)$cart['id']);
    $ewalletBalance = Ewallet::balance(Auth::id());
    $canUseEwallet = $ewalletBalance >= (float)$totals['total_price'];

    // Payment methods (mirrors the reactivate page)
    $methods = [];
    if (setting('gcash_enabled', '1') === '1') $methods['gcash'] = 'GCash';
    if (setting('maya_enabled', '1') === '1')  $methods['maya']  = 'Maya';
    if (trim(setting('usdt_trc20_address', ''))) $methods['usdt_trc20'] = 'USDT TRC20';
    if (trim(setting('usdt_bep20_address', ''))) $methods['usdt_bep20'] = 'USDT BEP20';

    $stockErrors = Cart::validateStock((int)$cart['id']);

    $pageTitle = 'Checkout';
    include 'views/partials/head.php';
    include 'views/partials/topbar.php';
    include 'views/partials/sidebar_member.php';
    // The view will be built in Phase 4; for now use a minimal form
    include 'views/member/checkout.php';
    include 'views/partials/footer.php';
}
```

#### `placeOrder()` — process the order

```php
public function placeOrder(): void
{
    Auth::guard('member');
    Auth::checkCsrfOrFail();

    $memberId = Auth::id();
    $cart = Cart::getOrCreate($memberId);
    $cartId = (int)$cart['id'];

    // Validate cart items
    $items = Cart::getItems($cartId);
    if (empty($items)) {
        flash('Your cart is empty.', 'warning');
        redirect('?page=repeat_purchases');
    }

    // Validate stock
    $stockErrors = Cart::validateStock($cartId);
    if (!empty($stockErrors)) {
        flash('Some items are out of stock: ' . implode(' | ', $stockErrors), 'danger');
        redirect('?page=checkout');
    }

    // Read form fields
    $binaryPosition = ($_POST['binary_position'] ?? 'left') === 'right' ? 'right' : 'left';
    $paymentMethod = $_POST['payment_method'] ?? '';
    $validMethods = ['ewallet', 'gcash', 'maya', 'usdt_trc20', 'usdt_bep20'];
    if (!in_array($paymentMethod, $validMethods, true)) {
        flash('Invalid payment method.', 'danger');
        redirect('?page=checkout');
    }

    $totals = Cart::getTotals($cartId);
    $totalPrice = (float)$totals['total_price'];

    $pdo = db();
    $pdo->beginTransaction();

    try {
        // Handle proof upload for external payments
        $proofImage = null;
        if ($paymentMethod !== 'ewallet') {
            if (!isset($_FILES['proof_image']) || $_FILES['proof_image']['error'] !== UPLOAD_ERR_OK) {
                throw new \RuntimeException('Please upload a proof of payment.');
            }
            $file = $_FILES['proof_image'];
            // Validate MIME
            $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            if (!in_array($mime, $allowedMimes, true)) {
                throw new \RuntimeException('Proof must be an image (JPEG, PNG, GIF, WebP).');
            }
            // Validate size (5 MB)
            if ($file['size'] > 5 * 1024 * 1024) {
                throw new \RuntimeException('Proof image must be 5 MB or smaller.');
            }
            // Generate unique filename
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'proof_' . $memberId . '_' . time() . '.' . $ext;
            $destDir = __DIR__ . '/../uploads/repeat_purchase_proofs/';
            if (!is_dir($destDir)) {
                mkdir($destDir, 0755, true);
            }
            if (!move_uploaded_file($file['tmp_name'], $destDir . $filename)) {
                throw new \RuntimeException('Failed to save proof image.');
            }
            $proofImage = 'repeat_purchase_proofs/' . $filename;
        }

        // Create the order
        $orderId = RepeatPurchaseOrder::createFromCart(
            $memberId,
            $cartId,
            $binaryPosition,
            $paymentMethod,
            $proofImage
        );

        // E-wallet path: instant debit + approve
        if ($paymentMethod === 'ewallet') {
            $ewalletBalance = Ewallet::balance($memberId);
            if ($ewalletBalance < $totalPrice) {
                throw new \RuntimeException('Insufficient e-wallet balance.');
            }

            // Debit from total e-wallet balance
            $pdo->prepare("
                UPDATE users SET ewallet_balance = ewallet_balance - ? WHERE id = ?
            ")->execute([$totalPrice, $memberId]);

            // Record e-wallet transaction
            Ewallet::record([
                'user_id'       => $memberId,
                'type'          => 'debit',
                'amount'        => $totalPrice,
                'description'   => "Payment for repeat purchase order #{$orderId}",
                'created_at'    => date('Y-m-d H:i:s'),
            ]);

            // Mark paid and approved
            $pdo->prepare("
                UPDATE repeat_purchase_orders
                SET status = 'approved', paid_at = NOW(), approved_by = ?, approved_at = NOW()
                WHERE id = ?
            ")->execute([$memberId, $orderId]);

            // Distribute PV
            Commission::processProductPV($orderId);
        }

        // Clear the cart
        Cart::clear($cartId);

        $pdo->commit();

        if ($paymentMethod === 'ewallet') {
            flash('Order placed and approved. PV has been distributed.', 'success');
        } else {
            flash('Order placed. Admin will review your payment proof.', 'success');
        }
    } catch (\Exception $e) {
        $pdo->rollBack();
        flash($e->getMessage(), 'danger');
    }

    redirect('?page=repeat_purchases');
}
```

### 3.3 — Update `core/Commission.php`

Replace the existing `processProductPV()` and `meetsPersonalPvRequirement()`:

```php
/**
 * Check whether a member satisfies the Personal PV gate.
 * Now reads from the member's own package (not global setting).
 */
private static function meetsPersonalPvRequirement(int $userId): bool
{
    $user = User::find($userId);
    if (!$user) return false;
    $pkg = Package::find((int)$user['package_id']);
    $req = (float)($pkg['personal_pv_requirement'] ?? 0);
    if ($req <= 0) {
        return true;
    }
    return (float)$user['personal_pv'] >= $req;
}

/**
 * Distribute product PV from an approved repeat purchase order.
 * Called after order is approved (immediately for e-wallet,
 * after admin approval for external payments).
 */
public static function processProductPV(int $orderId): void
{
    $pdo = db();

    // Load order header
    $order = RepeatPurchaseOrder::find($orderId);
    if (!$order || $order['status'] !== 'approved') return;

    $memberId = (int)$order['member_id'];
    $totalPv  = (float)$order['total_pv'];
    if ($totalPv <= 0.00) return;

    $buyer = User::find($memberId);
    if (!$buyer || $buyer['status'] !== 'active') return;

    // 1. Buyer receives Personal PV (unchanged from current)
    $pdo->prepare('UPDATE users SET personal_pv = personal_pv + ? WHERE id = ?')
        ->execute([$totalPv, $memberId]);
    self::recordPvTransaction($memberId, 'product_personal', $totalPv, $memberId, 'repeat_purchase');

    // 2. Group PV flows up the sponsor chain (unchanged from current)
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

    // 3. Pay yourself first (NEW): buyer receives binary PV on their chosen leg
    $buyerSide = $order['binary_position']; // 'left' or 'right' from checkout form
    self::applyBinaryPv($memberId, $buyerSide, $totalPv, $memberId, 'repeat_purchase');

    // 4. Binary tree walk (unchanged from current — reads each user's fixed binary_position)
    //    processBinaryPV starts from the buyer's binary parent.
    self::processBinaryPV($memberId, $totalPv);
}
```

> **Important:** `processBinaryPV()` is unchanged. It reads `binary_position` from each user's `users` table row as it walks up. The buyer's checkout choice only affects step 3 (pay yourself first).

### 3.4 — Add admin controller methods to `AdminController.php`

```php
public function repeatPurchaseOrders(): void
{
    Auth::guard('admin');

    $page = max(1, (int)($_GET['page'] ?? 1));
    $status = $_GET['status'] ?? 'pending';

    $result = match ($status) {
        'pending' => RepeatPurchaseOrder::pending($page),
        'paid'    => RepeatPurchaseOrder::paid($page),
        'all'     => RepeatPurchaseOrder::all($page),
        default   => RepeatPurchaseOrder::pending($page),
    };

    $pageTitle = 'Repeat Purchase Orders';
    include 'views/partials/head.php';
    include 'views/partials/topbar.php';
    include 'views/partials/sidebar_admin.php';
    include 'views/admin/repeat_purchases.php';
    include 'views/partials/footer.php';
}

public function markRepeatOrderPaid(): void
{
    Auth::guard('admin');
    Auth::checkCsrfOrFail();

    $orderId = (int)($_POST['id'] ?? 0);
    try {
        RepeatPurchaseOrder::markPaid($orderId, Auth::id());
        flash('Order marked as paid.', 'success');
    } catch (\RuntimeException $e) {
        flash($e->getMessage(), 'danger');
    }
    redirect('?page=admin_repeat_purchase_orders');
}

public function approveRepeatOrder(): void
{
    Auth::guard('admin');
    Auth::checkCsrfOrFail();

    $orderId = (int)($_POST['id'] ?? 0);
    try {
        RepeatPurchaseOrder::approve($orderId, Auth::id());
        flash('Order approved and PV distributed.', 'success');
    } catch (\RuntimeException $e) {
        flash($e->getMessage(), 'danger');
    }
    redirect('?page=admin_repeat_purchase_orders');
}

public function rejectRepeatOrder(): void
{
    Auth::guard('admin');
    Auth::checkCsrfOrFail();

    $orderId = (int)($_POST['id'] ?? 0);
    try {
        RepeatPurchaseOrder::reject($orderId, Auth::id());
        flash('Order rejected.', 'success');
    } catch (\RuntimeException $e) {
        flash($e->getMessage(), 'danger');
    }
    redirect('?page=admin_repeat_purchase_orders');
}
```

### 3.5 — Create minimal checkout view

Create `views/member/checkout.php` (temporary — will be replaced by the full UI in Phase 4):

```php
<div class="container-fluid px-4 py-3">
  <h4 class="mb-3">Checkout</h4>

  <?php if (!empty($stockErrors)): ?>
    <div class="alert alert-danger">
      <strong>Stock issues:</strong>
      <ul class="mb-0"><?php foreach ($stockErrors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul>
    </div>
  <?php endif; ?>

  <form method="post" action="?page=place_order" enctype="multipart/form-data">
    <?= csrf_field() ?>

    <div class="card mb-3">
      <div class="card-header">Order Summary</div>
      <div class="card-body">
        <table class="table table-sm">
          <thead><tr><th>Product</th><th>Qty</th><th>Price</th><th>PV</th></tr></thead>
          <tbody>
            <?php foreach ($items as $item): ?>
            <tr>
              <td><?= e($item['name']) ?></td>
              <td><?= (int)$item['quantity'] ?></td>
              <td>₱<?= fmt_money((float)$item['unit_price']) ?></td>
              <td><?= fmt_money((float)$item['unit_pv']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr class="fw-bold">
              <td colspan="2">Total</td>
              <td>₱<?= fmt_money((float)$totals['total_price']) ?></td>
              <td><?= fmt_money((float)$totals['total_pv']) ?></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header">Binary Position</div>
      <div class="card-body">
        <div class="form-check form-check-inline">
          <input class="form-check-input" type="radio" name="binary_position" id="pos_left" value="left" checked>
          <label class="form-check-label" for="pos_left">Left</label>
        </div>
        <div class="form-check form-check-inline">
          <input class="form-check-input" type="radio" name="binary_position" id="pos_right" value="right">
          <label class="form-check-label" for="pos_right">Right</label>
        </div>
        <div class="form-text">Which of your own legs receives the product PV.</div>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header">Payment Method</div>
      <div class="card-body">
        <?php if ($canUseEwallet): ?>
        <div class="form-check mb-2">
          <input class="form-check-input" type="radio" name="payment_method" id="pm_ewallet" value="ewallet" checked>
          <label class="form-check-label" for="pm_ewallet">
            <strong>E-Wallet</strong>
            <span class="text-muted"> (Balance: ₱<?= fmt_money($ewalletBalance) ?>)</span>
          </label>
        </div>
        <?php endif; ?>
        <?php foreach ($methods as $key => $label): ?>
        <div class="form-check mb-2">
          <input class="form-check-input" type="radio" name="payment_method" id="pm_<?= $key ?>" value="<?= $key ?>"
            <?= (!$canUseEwallet && $loop->first ?? true) ? 'checked' : '' ?>>
          <label class="form-check-label" for="pm_<?= $key ?>"><?= e($label) ?></label>
        </div>
        <?php endforeach; ?>
        <div id="proof_upload_container" style="display:none;" class="mt-3">
          <label class="form-label">Upload Proof of Payment</label>
          <input type="file" name="proof_image" class="form-control" accept="image/*">
          <div class="form-text">JPEG, PNG, GIF, or WebP. Max 5 MB.</div>
        </div>
      </div>
    </div>

    <div class="form-check mb-3">
      <input class="form-check-input" type="checkbox" id="terms" required>
      <label class="form-check-label" for="terms">I confirm that the order details are correct.</label>
    </div>

    <button type="submit" class="btn btn-primary" id="place_order_btn">Place Order</button>
    <a href="?page=repeat_purchases" class="btn btn-outline-secondary ms-2">Cancel</a>
  </form>
</div>

<script>
// Toggle proof upload visibility based on payment method
document.querySelectorAll('[name=payment_method]').forEach(el => {
    el.addEventListener('change', function() {
        document.getElementById('proof_upload_container').style.display =
            this.value === 'ewallet' ? 'none' : 'block';
    });
});
// Trigger on load
document.querySelector('[name=payment_method]:checked')?.dispatchEvent(new Event('change'));
</script>
```

### Phase 3 QA Test

```
┌─────────────────────────────────────────────────────────────┐
│  PHASE 3 QA — Checkout & Admin Order Review                 │
│  Log in as a member + admin in separate browsers.           │
└─────────────────────────────────────────────────────────────┘

Precondition: Phase 1 + 2 deployed and QA passed.
              Member has e-wallet balance ≥ product price
              (admin can top up via ?page=admin_ewallet_topup).
              At least 1 active product exists with stock > 0.
              Member has an active cart with items (use Phase 2 POST).

── E-WALLET FLOW ──────────────────────────────────────────────

TC-301 — Checkout page loads
  → Navigate to ?page=checkout.
  → Shows order summary with items, totals, binary position,
    payment methods.
  → [PASS] if page loads without errors.

TC-302 — E-wallet checkout succeeds
  → Select E-Wallet payment method.
  → Select binary position (Left or Right).
  → Check terms, click Place Order.
  → Redirects to ?page=repeat_purchases with success flash
    "Order placed and approved. PV has been distributed."
  → [PASS] if flash shows.

TC-303 — E-wallet balance deducted
  → Check e-wallet balance in topbar.
  → Balance reduced by total_price.
  → [PASS] if deduction matches.

TC-304 — Order appears as approved
  → Admin logs in, goes to ?page=admin_repeat_purchase_orders.
  → Filter shows "pending" — the e-wallet order should NOT be here.
  → Switch to "All" filter — order appears with status "approved".
  → [PASS] if status is approved.

TC-305 — PV distributed (personal PV)
  → Admin checks member's user view (?page=admin_user_view&id=...).
  → personal_pv increased by order's total_pv.
  → [PASS] if personal PV reflects the addition.

── EXTERNAL PAYMENT FLOW ──────────────────────────────────────

TC-306 — External checkout with proof upload
  → As member, add items to cart again.
  → Go to ?page=checkout.
  → Select GCash (or any external method).
  → Proof upload area appears.
  → Upload a valid image file.
  → Select binary position, check terms, click Place Order.
  → Redirects with flash "Order placed. Admin will review..."
  → [PASS] if flash shows.

TC-307 — Order appears as pending
  → Admin goes to ?page=admin_repeat_purchase_orders.
  → New order shows in "Pending" filter with status "pending".
  → [PASS] if visible with proof thumbnail.

TC-308 — Admin marks paid
  → Admin clicks "Mark Paid" on the pending order.
  → Flash "Order marked as paid."
  → Order moves to "Paid" filter with status "paid".
  → [PASS] if status changed.

TC-309 — Admin approves
  → Admin clicks "Approve" on the paid order.
  → Flash "Order approved and PV distributed."
  → Order moves to "All" filter with status "approved".
  → [PASS] if status is approved.

TC-310 — PV distributed from external order
  → Check member's personal_pv again.
  → Increased by the external order's total_pv.
  → [PASS] if PV is updated.

── REJECT FLOW ────────────────────────────────────────────────

TC-311 — Admin rejects a pending order
  → As member, place another order with external payment.
  → Admin rejects it (pending or paid).
  → Flash "Order rejected."
  → Status shows "rejected".
  → [PASS] if rejected.

TC-312 — No PV for rejected order
  → Check member's personal_pv — unchanged by the rejected order.
  → [PASS] if no change.

── VALIDATION ─────────────────────────────────────────────────

TC-313 — Stock check at checkout
  → Admin sets a product's stock to 0.
  → Member has that product in cart.
  → Go to ?page=checkout — stock errors shown.
  → Place Order — blocked with flash error.
  → [PASS] if blocked.

TC-314 — Empty cart redirect
  → With empty cart, navigate to ?page=checkout.
  → Redirects to ?page=repeat_purchases with flash "Your cart is empty."
  → [PASS] if redirected.

TC-315 — Insufficient e-wallet balance
  → Drain member's e-wallet (or test with balance < total).
  → E-Wallet option should show warning or be unavailable.
  → [PASS] if user is warned and cannot use e-wallet.

Pass criteria: All 15 test cases pass.
```

---

## Phase 4 — Member UI

**Goal:** The member-facing `/?page=repeat_purchases` is rebuilt with a full product catalog, off-canvas cart sidebar, and polished checkout page. The old UI is replaced.

### 4.1 — Update `repeatPurchases()` in `MemberController.php`

```php
public function repeatPurchases(): void
{
    Auth::guard('member');

    $memberId = Auth::id();
    $products = Product::active();
    $cart = Cart::getOrCreate($memberId);
    $cartItems = Cart::getItems((int)$cart['id']);
    $cartTotals = Cart::getTotals((int)$cart['id']);
    $history = RepeatPurchaseOrder::forMember($memberId, (int)($_GET['page'] ?? 1));

    $pageTitle = 'Repeat Purchases';
    include 'views/partials/head.php';
    include 'views/partials/topbar.php';
    include 'views/partials/sidebar_member.php';
    include 'views/member/repeat_purchases.php';
    include 'views/partials/footer.php';
}
```

### 4.2 — Rebuild `views/member/repeat_purchases.php`

Full catalog + off-canvas cart + link to checkout:

```php
<div class="container-fluid px-4 py-3">

  <!-- Product Catalog -->
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Product Catalog</h4>
    <button class="btn btn-outline-primary position-relative" type="button" data-bs-toggle="offcanvas" data-bs-target="#cartOffcanvas">
      🛒 Cart
      <?php if (count($cartItems) > 0): ?>
      <span class="badge bg-danger ms-1"><?= array_sum(array_column($cartItems, 'quantity')) ?></span>
      <?php endif; ?>
    </button>
  </div>

  <?php if (empty($products)): ?>
  <div class="alert alert-info">No products available at this time.</div>
  <?php else: ?>
  <div class="row g-3">
    <?php foreach ($products as $product):
      $available = Product::availableStock((int)$product['id']);
    ?>
    <div class="col-12 col-sm-6 col-lg-4 col-xl-3">
      <div class="card h-100">
        <?php if ($product['image_url']): ?>
        <img src="<?= APP_URL ?>/uploads/<?= e($product['image_url']) ?>" class="card-img-top" alt="<?= e($product['name']) ?>" style="height:180px;object-fit:cover;">
        <?php else: ?>
        <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height:180px;">
          <span class="text-muted">No Image</span>
        </div>
        <?php endif; ?>
        <div class="card-body d-flex flex-column">
          <h6 class="card-title"><?= e($product['name']) ?></h6>
          <?php if ($product['short_description']): ?>
          <p class="card-text small text-muted flex-grow-1"><?= e($product['short_description']) ?></p>
          <?php endif; ?>
          <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="fw-bold text-primary">₱<?= fmt_money((float)$product['price']) ?></span>
            <span class="badge bg-info"><?= fmt_money((float)$product['pv_value']) ?> PV</span>
          </div>
          <div class="small text-muted mb-2">
            <?php if ($available > 0): ?>
            In stock: <?= $available ?>
            <?php else: ?>
            <span class="text-danger">Out of stock</span>
            <?php endif; ?>
          </div>
          <form method="post" action="?page=add_to_cart" class="d-flex gap-2">
            <?= csrf_field() ?>
            <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
            <input type="number" name="quantity" class="form-control form-control-sm" style="width:70px;" value="1" min="1" max="<?= $available > 0 ? $available : 1 ?>">
            <button type="submit" class="btn btn-sm btn-success" <?= $available < 1 ? 'disabled' : '' ?>>
              Add to Cart
            </button>
          </form>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Purchase History -->
  <h5 class="mt-4 mb-3">Purchase History</h5>
  <?php if (empty($history['data'])): ?>
  <div class="alert alert-light">No purchases yet.</div>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table table-hover table-sm">
      <thead>
        <tr>
          <th>#</th>
          <th>Items</th>
          <th>Total PV</th>
          <th>Total Price</th>
          <th>Payment</th>
          <th>Status</th>
          <th>Date</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($history['data'] as $order): ?>
        <tr>
          <td><?= (int)$order['id'] ?></td>
          <td>
            <?php
              // Load items for this order
              $full = RepeatPurchaseOrder::findWithItems((int)$order['id']);
              $itemNames = array_map(fn($i) => $i['quantity'] . '× ' . $i['name'], $full['items'] ?? []);
              echo e(implode(', ', $itemNames));
            ?>
          </td>
          <td><?= fmt_money((float)$order['total_pv']) ?></td>
          <td>₱<?= fmt_money((float)$order['total_price']) ?></td>
          <td><?= e($order['payment_method']) ?></td>
          <td>
            <span class="badge bg-<?= match($order['status']) {
              'pending' => 'warning',
              'paid' => 'info',
              'approved' => 'success',
              'rejected' => 'danger',
              'cancelled' => 'secondary',
              default => 'light'
            } ?>">
              <?= e(ucfirst($order['status'])) ?>
            </span>
          </td>
          <td class="small"><?= e($order['created_at']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ($history['pages'] > 1): ?>
  <nav><?= render_pagination($history['pages'], (int)($_GET['page'] ?? 1), '?page=repeat_purchases') ?></nav>
  <?php endif; ?>
  <?php endif; ?>
</div>

<!-- Off-canvas Cart Sidebar -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="cartOffcanvas" aria-labelledby="cartOffcanvasLabel">
  <div class="offcanvas-header">
    <h5 class="offcanvas-title" id="cartOffcanvasLabel">🛒 Your Cart</h5>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
  </div>
  <div class="offcanvas-body d-flex flex-column">
    <?php if (empty($cartItems)): ?>
    <p class="text-muted">Your cart is empty.</p>
    <?php else: ?>
    <div class="flex-grow-1">
      <?php foreach ($cartItems as $item):
        $available = Product::availableStock((int)$item['product_id']);
      ?>
      <div class="d-flex gap-2 align-items-start mb-3 pb-2 border-bottom">
        <?php if ($item['image_url']): ?>
        <img src="<?= APP_URL ?>/uploads/<?= e($item['image_url']) ?>" style="width:50px;height:50px;object-fit:cover;border-radius:4px;">
        <?php else: ?>
        <div class="bg-light" style="width:50px;height:50px;border-radius:4px;"></div>
        <?php endif; ?>
        <div class="flex-grow-1">
          <div class="fw-small"><?= e($item['name']) ?></div>
          <div class="small text-muted">₱<?= fmt_money((float)$item['unit_price']) ?> × <?= (int)$item['quantity'] ?></div>
          <form method="post" action="?page=update_cart_item" class="d-inline-block mt-1">
            <?= csrf_field() ?>
            <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">
            <div class="input-group input-group-sm" style="width:120px;">
              <button class="btn btn-outline-secondary" type="button" onclick="this.parentNode.querySelector('input').stepDown(); this.parentNode.querySelector('input').dispatchEvent(new Event('change'));">−</button>
              <input type="number" name="quantity" class="form-control text-center" value="<?= (int)$item['quantity'] ?>" min="1" max="<?= $available ?>" onchange="this.form.submit()">
              <button class="btn btn-outline-secondary" type="button" onclick="this.parentNode.querySelector('input').stepUp(); this.parentNode.querySelector('input').dispatchEvent(new Event('change'));">+</button>
            </div>
          </form>
          <form method="post" action="?page=remove_cart_item" class="d-inline-block ms-1">
            <?= csrf_field() ?>
            <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">
            <button type="submit" class="btn btn-sm btn-outline-danger">✕</button>
          </form>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="border-top pt-2 mt-2">
      <div class="d-flex justify-content-between fw-bold">
        <span>Total PV</span>
        <span><?= fmt_money((float)$cartTotals['total_pv']) ?></span>
      </div>
      <div class="d-flex justify-content-between fw-bold">
        <span>Total Price</span>
        <span>₱<?= fmt_money((float)$cartTotals['total_price']) ?></span>
      </div>
      <a href="?page=checkout" class="btn btn-primary w-100 mt-3">Proceed to Checkout</a>
      <button class="btn btn-outline-secondary w-100 mt-1" data-bs-dismiss="offcanvas">Continue Shopping</button>
    </div>
    <?php endif; ?>
  </div>
</div>
```

### 4.3 — Replace the checkout view

Replace the minimal `views/member/checkout.php` with the full polished UI:

```php
<div class="container-fluid px-4 py-3">
  <h4 class="mb-3">Checkout</h4>

  <?php if (!empty($stockErrors)): ?>
    <div class="alert alert-danger">
      <strong>Some items are no longer available in the requested quantity:</strong>
      <ul class="mb-0 mt-1">
        <?php foreach ($stockErrors as $e): ?>
        <li><?= e($e) ?></li>
        <?php endforeach; ?>
      </ul>
      <a href="?page=repeat_purchases" class="alert-link mt-2 d-inline-block">← Back to catalog</a>
    </div>
  <?php endif; ?>

  <form method="post" action="?page=place_order" enctype="multipart/form-data" id="checkoutForm">
    <?= csrf_field() ?>

    <!-- Order Summary -->
    <div class="card mb-3">
      <div class="card-header fw-bold">📋 Order Summary</div>
      <div class="card-body p-0">
        <table class="table table-sm mb-0">
          <thead class="table-light">
            <tr><th>Product</th><th>Qty</th><th>Unit Price</th><th>Unit PV</th><th>Subtotal</th></tr>
          </thead>
          <tbody>
            <?php foreach ($items as $item): ?>
            <tr>
              <td>
                <div class="d-flex align-items-center gap-2">
                  <?php if ($item['image_url']): ?>
                  <img src="<?= APP_URL ?>/uploads/<?= e($item['image_url']) ?>" style="width:36px;height:36px;object-fit:cover;border-radius:4px;">
                  <?php endif; ?>
                  <span><?= e($item['name']) ?></span>
                </div>
              </td>
              <td><?= (int)$item['quantity'] ?></td>
              <td>₱<?= fmt_money((float)$item['unit_price']) ?></td>
              <td><?= fmt_money((float)$item['unit_pv']) ?></td>
              <td>₱<?= fmt_money((float)$item['quantity'] * (float)$item['unit_price']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot class="table-light fw-bold">
            <tr>
              <td colspan="3"></td>
              <td>Total PV: <?= fmt_money((float)$totals['total_pv']) ?></td>
              <td>₱<?= fmt_money((float)$totals['total_price']) ?></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>

    <!-- Binary Position -->
    <div class="card mb-3">
      <div class="card-header fw-bold">🎯 Binary Side</div>
      <div class="card-body">
        <p class="small text-muted mb-2">Choose which of your own legs receives the product PV to help balance your binary tree.</p>
        <div class="d-flex gap-3">
          <div class="form-check">
            <input class="form-check-input" type="radio" name="binary_position" id="pos_left" value="left" checked>
            <label class="form-check-label fw-medium" for="pos_left">⬅️ Left</label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="binary_position" id="pos_right" value="right">
            <label class="form-check-label fw-medium" for="pos_right">➡️ Right</label>
          </div>
        </div>
      </div>
    </div>

    <!-- Payment Method -->
    <div class="card mb-3">
      <div class="card-header fw-bold">💳 Payment Method</div>
      <div class="card-body">
        <div class="row g-2">
          <?php if ($canUseEwallet): ?>
          <div class="col-6 col-md-3">
            <label class="method-option d-block border rounded p-3 text-center cursor-pointer <?= $canUseEwallet ? 'selected' : '' ?>" style="cursor:pointer;">
              <input type="radio" name="payment_method" value="ewallet" class="d-none" <?= $canUseEwallet ? 'checked' : '' ?>>
              <div class="fw-bold">E-Wallet</div>
              <div class="small text-muted">₱<?= fmt_money($ewalletBalance) ?></div>
              <?php if ((float)$totals['total_price'] > $ewalletBalance): ?>
              <div class="small text-danger mt-1">Insufficient balance</div>
              <?php endif; ?>
            </label>
          </div>
          <?php endif; ?>
          <?php foreach ($methods as $key => $label): ?>
          <div class="col-6 col-md-3">
            <label class="method-option d-block border rounded p-3 text-center" style="cursor:pointer;">
              <input type="radio" name="payment_method" value="<?= $key ?>" class="d-none"
                <?= (!$canUseEwallet && $loop->first ?? true) ? 'checked' : '' ?>>
              <div class="fw-bold"><?= e($label) ?></div>
              <div class="small text-muted">Upload proof</div>
            </label>
          </div>
          <?php endforeach; ?>
        </div>

        <!-- Proof upload (hidden for e-wallet) -->
        <div id="proofUploadSection" class="mt-3" style="display:none;">
          <label class="form-label fw-medium">📎 Upload Proof of Payment</label>
          <input type="file" name="proof_image" class="form-control" accept="image/jpeg,image/png,image/gif,image/webp">
          <div class="form-text">JPEG, PNG, GIF, or WebP. Max 5 MB.</div>
        </div>
      </div>
    </div>

    <!-- Terms & Submit -->
    <div class="form-check mb-3">
      <input class="form-check-input" type="checkbox" id="termsCheck" required>
      <label class="form-check-label" for="termsCheck">
        I confirm that the order details are correct and agree to the terms.
      </label>
    </div>

    <button type="submit" class="btn btn-primary btn-lg" id="placeOrderBtn" disabled>🛒 Place Order</button>
    <a href="?page=repeat_purchases" class="btn btn-outline-secondary btn-lg ms-2">← Back to Catalog</a>
  </form>
</div>

<!-- style for method-option cards (mirrors reactivate.php) -->
<style>
.method-option { transition: all .15s ease; }
.method-option:hover { border-color: #0d6efd !important; background: #f0f7ff; }
.method-option:has(input:checked) { border-color: #0d6efd !important; background: #e6f0ff; box-shadow: 0 0 0 2px rgba(13,110,253,.25); }
</style>

<script>
(function() {
    // Toggle proof upload visibility
    const radios = document.querySelectorAll('[name=payment_method]');
    const proofSection = document.getElementById('proofUploadSection');
    const termsCheck = document.getElementById('termsCheck');
    const placeBtn = document.getElementById('placeOrderBtn');

    function toggleProof() {
        const checked = document.querySelector('[name=payment_method]:checked');
        proofSection.style.display = checked && checked.value !== 'ewallet' ? 'block' : 'none';
    }

    radios.forEach(r => r.addEventListener('change', toggleProof));
    toggleProof();

    // Enable place order only when terms checked
    termsCheck.addEventListener('change', function() {
        placeBtn.disabled = !this.checked;
    });
})();
</script>
```

### Phase 4 QA Test

```
┌─────────────────────────────────────────────────────────────┐
│  PHASE 4 QA — Member UI                                     │
│  Log in as a member. Verify visual elements.                │
└─────────────────────────────────────────────────────────────┘

Precondition: Phases 1-3 deployed and QA passed.
              Multiple active products exist with stock.
              Some products should have stock=0 for testing.

TC-401 — Product catalog displays correctly
  → Navigate to ?page=repeat_purchases.
  → Product cards show: image (or placeholder), name,
    short_description, price, PV badge, stock indicator,
    quantity input, "Add to Cart" button.
  → Out-of-stock products show "Out of stock" and button is disabled.
  → [PASS] if layout matches expectations.

TC-402 — Add to cart from catalog
  → Set quantity to 2 on a product, click "Add to Cart".
  → Toast/success flash appears.
  → Cart button in topbar updates count.
  → [PASS] if cart count reflects addition.

TC-403 — Off-canvas cart opens
  → Click the "Cart" button (or the topbar cart badge).
  → Off-canvas panel slides in from the right.
  → Shows cart items with: thumbnail, name, unit price,
    quantity stepper (−/value/+), remove (✕) button.
  → Shows totals (PV and price) at bottom.
  → [PASS] if all cart data renders correctly.

TC-404 — Quantity stepper works
  → In the off-canvas cart, click the − or + buttons.
  → Quantity updates and the form submits automatically.
  → Cart totals update.
  → [PASS] if quantity changes without errors.

TC-405 — Remove item from cart
  → Click ✕ button on a cart item.
  → Item removed, totals update.
  → [PASS] if item disappears from cart.

TC-406 — "Proceed to Checkout" button works
  → With items in cart, click "Proceed to Checkout".
  → Navigates to ?page=checkout.
  → Shows full order summary, binary side selector,
    payment methods, and Place Order button.
  → [PASS] if checkout page loads with correct data.

TC-407 — Binary side selector visible
  → On checkout page, Left/Right radio buttons visible.
  → Help text explains the purpose.
  → [PASS] if selector renders.

TC-408 — Payment method cards styled
  → Payment methods shown as styled cards (like reactivate.php).
  → E-Wallet shows balance.
  → External methods show "Upload proof".
  → [PASS] if styled correctly.

TC-409 — Proof upload shown for external methods
  → Select an external payment method.
  → File upload input appears.
  → Switch back to E-Wallet — upload hidden.
  → [PASS] if proof section toggles.

TC-410 — Terms checkbox required
  → Place Order button is disabled initially.
  → Check the terms checkbox — button enables.
  → Uncheck — button disables again.
  → [PASS] if terms gate works.

TC-411 — Purchase history table visible
  → Below the catalog, purchase history table shows.
  → Columns: #, Items, Total PV, Total Price, Payment, Status, Date.
  → Status badges color-coded correctly.
  → [PASS] if history renders with previous orders.

TC-412 — Responsive layout
  → Resize browser to mobile width.
  → Products stack in single column.
  → Off-canvas cart still works.
  → [PASS] if layout adapts.

Pass criteria: All 12 test cases pass.
```

---

## Phase 5 — Admin UI & Cleanup

**Goal:** The admin repeat purchases page shows order-level rows with proof thumbnails and two-step actions. The old `personal_pv_requirement` setting is removed from the admin settings form.

### 5.1 — Rebuild `views/admin/repeat_purchases.php`

```php
<?php
/**
 * @var array $result Paginated result from RepeatPurchaseOrder
 */
$orders = $result['data'] ?? [];
$currentPage = max(1, (int)($_GET['page'] ?? 1));
$totalPages = $result['pages'] ?? 1;
$currentStatus = $_GET['status'] ?? 'pending';
?>

<div class="container-fluid px-4 py-3">
  <h4 class="mb-3">Repeat Purchase Orders</h4>

  <!-- Status filter tabs -->
  <ul class="nav nav-tabs mb-3">
    <li class="nav-item">
      <a class="nav-link <?= $currentStatus === 'pending' ? 'active' : '' ?>" href="?page=admin_repeat_purchase_orders&status=pending">
        Pending <?php $p = RepeatPurchaseOrder::pending(1, 1); if ($p['total'] > 0): ?><span class="badge bg-warning ms-1"><?= $p['total'] ?></span><?php endif; ?>
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?= $currentStatus === 'paid' ? 'active' : '' ?>" href="?page=admin_repeat_purchase_orders&status=paid">
        Paid Awaiting Approval <?php $p2 = RepeatPurchaseOrder::paid(1, 1); if ($p2['total'] > 0): ?><span class="badge bg-info ms-1"><?= $p2['total'] ?></span><?php endif; ?>
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?= $currentStatus === 'all' ? 'active' : '' ?>" href="?page=admin_repeat_purchase_orders&status=all">All Orders</a>
    </li>
  </ul>

  <?php if (empty($orders)): ?>
  <div class="alert alert-light">No orders found.</div>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead class="table-light">
        <tr>
          <th>#</th>
          <th>Member</th>
          <th>Items</th>
          <th>Total PV</th>
          <th>Total Price</th>
          <th>Side</th>
          <th>Payment</th>
          <th>Proof</th>
          <th>Status</th>
          <th>Date</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($orders as $order):
          $full = RepeatPurchaseOrder::findWithItems((int)$order['id']);
          $itemSummaries = array_map(fn($i) => $i['quantity'] . '× ' . $i['name'], $full['items'] ?? []);
        ?>
        <tr>
          <td><?= (int)$order['id'] ?></td>
          <td>
            <a href="?page=admin_user_view&id=<?= (int)$order['member_id'] ?>" class="text-decoration-none">
              <?= e($order['username'] ?? '—') ?>
            </a>
            <div class="small text-muted"><?= e($order['full_name'] ?? '') ?></div>
          </td>
          <td>
            <div class="d-flex gap-1 flex-wrap">
              <?php foreach (($full['items'] ?? []) as $item): ?>
              <span class="badge bg-light text-dark border"><?= (int)$item['quantity'] ?>× <?= e($item['name']) ?></span>
              <?php endforeach; ?>
            </div>
          </td>
          <td><?= fmt_money((float)$order['total_pv']) ?></td>
          <td>₱<?= fmt_money((float)$order['total_price']) ?></td>
          <td><span class="badge bg-secondary"><?= e($order['binary_position']) ?></span></td>
          <td><?= e($order['payment_method']) ?></td>
          <td>
            <?php if ($order['proof_image']): ?>
            <a href="<?= APP_URL ?>/uploads/<?= e($order['proof_image']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
              📎 View
            </a>
            <?php else: ?>
            <span class="text-muted">—</span>
            <?php endif; ?>
          </td>
          <td>
            <span class="badge bg-<?= match($order['status']) {
              'pending' => 'warning',
              'paid' => 'info',
              'approved' => 'success',
              'rejected' => 'danger',
              'cancelled' => 'secondary',
              default => 'light'
            } ?>">
              <?= e(ucfirst($order['status'])) ?>
            </span>
          </td>
          <td class="small"><?= e($order['created_at']) ?></td>
          <td>
            <?php if ($order['status'] === 'pending'): ?>
            <form method="post" action="?page=admin_mark_repeat_order_paid" class="d-inline">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int)$order['id'] ?>">
              <button type="submit" class="btn btn-sm btn-success" <?= empty($order['proof_image']) ? 'disabled title="No proof uploaded"' : '' ?>>Mark Paid</button>
            </form>
            <form method="post" action="?page=admin_reject_repeat_order" class="d-inline" onsubmit="return confirm('Reject this order? No PV will be distributed.')">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int)$order['id'] ?>">
              <button type="submit" class="btn btn-sm btn-outline-danger">Reject</button>
            </form>
            <?php elseif ($order['status'] === 'paid'): ?>
            <form method="post" action="?page=admin_approve_repeat_order" class="d-inline">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int)$order['id'] ?>">
              <button type="submit" class="btn btn-sm btn-primary">Approve</button>
            </form>
            <form method="post" action="?page=admin_reject_repeat_order" class="d-inline" onsubmit="return confirm('Reject this order? No PV will be distributed.')">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int)$order['id'] ?>">
              <button type="submit" class="btn btn-sm btn-outline-danger">Reject</button>
            </form>
            <?php else: ?>
            <span class="text-muted small">—</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($totalPages > 1): ?>
  <nav><?= render_pagination($totalPages, $currentPage, '?page=admin_repeat_purchase_orders&status=' . urlencode($currentStatus)) ?></nav>
  <?php endif; ?>
  <?php endif; ?>
</div>
```

### 5.2 — Remove `personal_pv_requirement` from admin settings

In `views/admin/settings.php`, delete the entire "Personal PV Gate" section (around lines 168-179). The field is no longer needed — `personal_pv_requirement` is now set per-package in the Packages admin page (`?page=admin_packages`). Add the field to the package edit form if not already present.

### 5.3 — Add `personal_pv_requirement` to package edit UI

In `views/admin/packages.php`, add an input field for `personal_pv_requirement` in the package form, and update `AdminController::savePackage()` to read and save it.

### Phase 5 QA Test

```
┌─────────────────────────────────────────────────────────────┐
│  PHASE 5 QA — Admin UI & Cleanup                            │
│  Log in as admin.                                           │
└─────────────────────────────────────────────────────────────┘

Precondition: Phases 1-4 deployed and QA passed.

TC-501 — Admin orders page loads
  → Navigate to ?page=admin_repeat_purchase_orders.
  → Filter tabs: Pending, Paid Awaiting Approval, All Orders.
  → Pending tab shows count badge if any pending orders exist.
  → [PASS] if page renders.

TC-502 — Order table shows correct columns
  → Table columns: #, Member, Items, Total PV, Total Price,
    Side, Payment, Proof, Status, Date, Actions.
  → [PASS] if all columns present.

TC-503 — Items column shows product badges
  → Items column shows badges like "2× Product Name".
  → [PASS] if readable.

TC-504 — Proof column shows View link
  → Orders with proof_image show "📎 View" button.
  → Clicking opens the image in a new tab.
  → [PASS] if proof opens.

TC-505 — Mark Paid action works
  → On a pending order with proof, click "Mark Paid".
  → Flash success, order moves to Paid tab.
  → [PASS] if status changes.

TC-506 — Mark Paid disabled if no proof
  → On a pending order without proof, "Mark Paid" button shows as disabled with tooltip.
  → [PASS] if disabled.

TC-507 — Approve action works
  → On a paid order, click "Approve".
  → Flash success, order status becomes "approved".
  → PV distributed (verify as in Phase 3 QA).
  → [PASS] if status changes and PV updates.

TC-508 — Reject action works
  → On a pending or paid order, click "Reject".
  → Confirm dialog appears.
  → Confirm — flash success, order status becomes "rejected".
  → No PV distributed.
  → [PASS] if rejected and no PV.

TC-509 — Settings page no longer has personal_pv_requirement
  → Navigate to ?page=admin_settings.
  → Search for "Personal PV Requirement" or "Personal PV Gate".
  → Not found.
  → [PASS] if removed.

TC-510 — Package edit has personal_pv_requirement field
  → Navigate to ?page=admin_packages.
  → Edit a package — form includes "Personal PV Requirement" input.
  → Save a new value — value persists in DB.
  → [PASS] if field exists and saves.

TC-511 — Filter tabs work
  → Click "Pending" — shows only pending orders.
  → Click "Paid Awaiting Approval" — shows only paid orders.
  → Click "All Orders" — shows all orders.
  → [PASS] if filters work correctly.

TC-512 — Pagination works (if many orders)
  → With more than 1 page of orders, pagination links appear.
  → Click page 2 — loads next page.
  → [PASS] if pagination works.

── FULL END-TO-END SMOKE TEST ────────────────────────────────

TC-513 — Complete member flow
  → As member: add products to cart → checkout → e-wallet
    → order approved → PV distributed → cart cleared.
  → [PASS] if flow completes without errors.

TC-514 — Complete admin flow
  → As member: place external payment order.
  → As admin: view pending → mark paid → approve → PV distributed.
  → [PASS] if two-step approval completes.

Pass criteria: All 14 test cases pass.
```

---

## Rollback Plan

If any phase causes issues, rollback per phase:

| Phase | Rollback |
|-------|----------|
| 1 | Run the inverse migration (drop new tables, re-create `repeat_purchases` from a backup, remove new columns) |
| 2 | Remove routes from `index.php`, revert `topbar.php` |
| 3 | Remove routes, revert `Commission.php`, revert `MemberController.php`, revert `AdminController.php`, delete `views/member/checkout.php` |
| 4 | Replace `views/member/repeat_purchases.php` with the original, revert `MemberController::repeatPurchases()` |
| 5 | Replace `views/admin/repeat_purchases.php` with the original, restore `personal_pv_requirement` in settings form |

## Order of Implementation

1. Phase 1 (DB + models) → QA → if fail, rollback
2. Phase 2 (cart API + badge) → QA → if fail, rollback
3. Phase 3 (checkout + admin review + commissions) → QA → if fail, rollback
4. Phase 4 (member UI) → QA → if fail, rollback
5. Phase 5 (admin UI + cleanup) → QA → if fail, rollback

Each phase is self-contained and independently testable. Never merge a phase without passing its QA test.
