# Shopping-Cart Repeat Purchase Redesign — Implementation Plan

## Status
Plan updated after codebase review feedback. **No code changes yet.**

## 1. Background & Goal

### Current state (`/?page=repeat_purchases`)
- Products are displayed as cards with a quantity input and a **Buy** button.
- Each click creates a single `repeat_purchases` row immediately.
- There is no cart, no checkout summary, no payment-method choice, and no stock control.
- Admins approve/reject each purchase individually.

### Target state
Convert repeat purchases into a **shopping-cart + checkout flow** similar to `/?page=reactivate`:
- Members can add multiple products, adjust quantities, and remove items.
- A checkout step lets them choose a payment method and a **binary placement side** (Left / Right, default Left).
- Products have **stock limits**; unavailable items cannot be checked out.
- **E-Wallet:** instant debit from total e-wallet balance + instant PV distribution + order `approved`.
- **External payments (GCash / Maya / USDT TRC20 / USDT BEP20):** upload proof → order `pending` → admin **Mark Paid** → order `paid` → admin **Approve** → PV distributed → order `approved`.
- Admins review pending/paid orders (not individual line items) in one place.
- The old `repeat_purchases` table is dropped immediately after migration.

## 2. High-Level User Flow

### Member flow
```
Repeat Purchases page
        │
        ▼
Browse product cards ──► Add to cart (toast feedback)
        │
        ▼
Off-canvas cart sidebar
   • Update quantities
   • Remove items
   • See running totals (PV & price)
        │
        ▼
Checkout
   • Review cart summary
   • Select binary side (Left / Right)
   • Choose payment method (E-Wallet / GCash / Maya / USDT)
        │
        ├─ E-Wallet + sufficient balance ──► debit ──► PV distributed ──► order approved ──► success
        │
        └─ External payment ──► upload proof ──► order pending ──► admin Mark Paid ──► admin Approve ──► PV distributed
```

### Admin flow
```
Admin Repeat Purchase Orders
        │
        ▼
List pending/paid orders with proof thumbnails
        │
        ▼
Pending + proof uploaded ──► Mark Paid ──► status = paid
        │
        ▼
Paid ──► Approve ──► PV distributed ──► status = approved
   or
Pending/Paid ──► Reject ──► status = rejected (stock reservation released)
```

## 3. Data Model Changes

### Products table changes

Add stock tracking to `products`:

```sql
ALTER TABLE products
  ADD COLUMN stock INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Total physical inventory';
```

`stock` is the absolute inventory set by the admin. It is **never** modified by order lifecycle events. Available stock is computed as `stock - reserved` where reserved = sum of quantities in pending/paid/approved order items.

### Packages table changes

The `personal_pv_requirement` is package-level, not a global admin setting. Add it to `packages`:

```sql
ALTER TABLE packages
  ADD COLUMN personal_pv_requirement DECIMAL(14,2) NOT NULL DEFAULT 0.00
  COMMENT 'Minimum Personal PV an upline must have to receive Group/Binary PV from product purchases';
```

When checking if an upline qualifies for Group/Binary PV from a repeat purchase, the system uses the **upline's own package's** `personal_pv_requirement`.

### New tables

#### `carts`
One active cart per member, stored in the DB.

```sql
CREATE TABLE carts (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  member_id  INT UNSIGNED NOT NULL,
  status     ENUM('active','abandoned','converted') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_member_active_cart (member_id, status),
  FOREIGN KEY (member_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
```

> **Note:** `uq_member_active_cart` is a partial-ish guard. In MySQL 8.0 we can use a filtered unique index; otherwise enforce in code (close old active carts before opening a new one).

#### `cart_items`

```sql
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
```

> `unit_price` and `unit_pv` are snapshotted at add-to-cart time so later product edits don't change an active cart. They are rechecked at checkout.

#### `repeat_purchase_orders`
Replaces the current `repeat_purchases` table as the order header.

```sql
CREATE TABLE repeat_purchase_orders (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  member_id        INT UNSIGNED NOT NULL,
  total_pv         DECIMAL(14,2) NOT NULL,
  total_price      DECIMAL(12,2) NOT NULL,
  binary_position  ENUM('left','right') NOT NULL DEFAULT 'left' COMMENT 'Side used for binary PV placement',
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
```

#### `repeat_purchase_order_items`
Line items for each order.

```sql
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
```

### Migration strategy
1. Add `stock` column to `products`.
2. Add `personal_pv_requirement` column to `packages` and seed from the old global setting.
3. Create `carts`, `cart_items`, `repeat_purchase_orders`, `repeat_purchase_order_items`.
4. Migrate existing `repeat_purchases` rows into the new order tables.
5. **Drop `repeat_purchases` immediately** after migration.
6. Remove the global `personal_pv_requirement` from `settings` table.
7. Update `install.sql` to create the new schema directly.

## 4. Proposed File Changes

| File | Change |
|---|---|
| `migrations/027_add_cart_and_order_tables.sql` | New tables, stock column, package column, data migration, drop old `repeat_purchases`, remove old setting. |
| `install.sql` | Replace old `repeat_purchases` schema with new cart/order schema; add `products.stock`; add `packages.personal_pv_requirement`. |
| `models/Product.php` | Add `availableStock(int $productId): int` helper (computes `stock - SUM(reserved qty)` via DB query). No `decrementStock()`/`incrementStock()` — stock is never mutated by orders. |
| `models/Cart.php` | New model: `getOrCreate()`, `addItem()`, `updateQty()`, `removeItem()`, `getItems()`, `getTotals()`, `clear()`, `validateStock()`. |
| `models/RepeatPurchaseOrder.php` | New model: `createFromCart()`, `find()`, `forMember()`, `pending()`, `paid()`, `all()`, `markPaid()`, `approve()`, `reject()`, `cancel()`. |
| `controllers/MemberController.php` | New actions: `cart()`, `addToCart()`, `updateCartItem()`, `removeCartItem()`, `checkout()`, `placeOrder()`. Remove `doRepeatPurchase()`. |
| `controllers/AdminController.php` | Replace `repeatPurchases()`, `approveRepeatPurchase()`, `rejectRepeatPurchase()` with order-based `repeatPurchaseOrders()`, `markRepeatOrderPaid()`, `approveRepeatOrder()`, `rejectRepeatOrder()`. |
| `views/member/repeat_purchases.php` | Convert product grid to catalog + off-canvas cart + checkout UI. |
| `views/admin/repeat_purchases.php` | Update to show order-level list with proof thumbnails and Mark Paid / Approve / Reject actions. |
| `views/partials/topbar.php` | Add cart badge (item count) for member users, fetched from `Cart::getItemCount(Auth::id())`. |
| `views/admin/settings.php` | Remove the `personal_pv_requirement` input field (moved to package-level in Packages edit UI). |
| `core/Commission.php` | Update `processProductPV(int $orderId)` to load from `repeat_purchase_orders`, iterate line items. Add "pay yourself first" at buyer level using the order's `binary_position`. Ancestor tree walk remains unchanged (reads each user's fixed `binary_position` from `users` table). Rewrite `meetsPersonalPvRequirement()` to read from the user's own package. Remove global setting lookup. |

## 5. Backend Logic Details

### Cart rules
- Each member has **one active cart** at a time.
- Adding the same product increments quantity or updates it (upsert).
- Quantity must be ≥ 1.
- If a product becomes inactive or its stock drops while in the cart, the item is flagged at checkout.
- Cart totals are recalculated from `cart_items` on every read (no stored total).
- Cart lives in the DB so it survives logout/login and device switches.

### Stock rules
- `products.stock` is the **total physical inventory** set by the admin. Orders **never** mutate this column.
- **Reserved** stock = `COALESCE(SUM(oi.quantity), 0)` across `repeat_purchase_order_items oi JOIN repeat_purchase_orders o ON oi.order_id = o.id` where `o.status IN ('pending','paid','approved')`.
- **Available** stock = `products.stock - reserved`.
- At checkout, every cart line must satisfy `quantity <= available stock`.
- When an order is created, the reservation exists automatically because the order's line items are in `repeat_purchase_order_items` with a pending/paid/approved status.
- On **reject** or **cancel**: the order status changes to `rejected`/`cancelled`, which removes those line items from the reserved calculation. No stock column is modified.
- On **approve**: no stock change — the items were already reserved at order creation.

### Available stock helper (Product model)

```sql
SELECT p.stock - COALESCE(SUM(oi.quantity), 0) AS available
FROM products p
LEFT JOIN repeat_purchase_order_items oi ON oi.product_id = p.id
LEFT JOIN repeat_purchase_orders o ON o.id = oi.order_id AND o.status IN ('pending','paid','approved')
WHERE p.id = ?
GROUP BY p.id;
```

### Checkout / place order
1. Re-read the cart and verify every product is still active and prices match.
2. Verify stock availability via `Product::availableStock()`; block the order if any item is out of stock.
3. Compute `total_price` and `total_pv`.
4. Read `binary_position` from checkout form (default `left`).
5. Determine `can_use_ewallet` = `Ewallet::balance(member_id) >= total_price`.
6. Start DB transaction.
7. Create `repeat_purchase_orders` row in status `pending`.
8. Create `repeat_purchase_order_items` rows. (No `products.stock` decrement — the order line items ARE the reservation.)
9. Clear the cart.
10. **E-Wallet path:**
    - Debit e-wallet via `Ewallet::debitInternal()` from **total e-wallet balance**.
    - Mark order `paid`, set `paid_at`.
    - Mark order `approved`, set `approved_by` = member/system, `approved_at`.
    - Call `Commission::processProductPV($orderId)` (reads `binary_position` from the order row for the buyer's own leg).
    - Commit.
11. **External payment path:**
    - Save uploaded proof image (validate MIME, size ≤ 5 MB) to `uploads/repeat_purchase_proofs/`.
    - Order remains `pending`.
    - Commit.
    - Flash: "Order placed. Admin will review your payment proof."

### Admin two-step review
- `markPaid($orderId, $adminId)`:
  - Verify order is `pending` and proof exists.
  - Set status = `paid`, `paid_at` = NOW().
  - No PV distribution yet.
- `approve($orderId, $adminId)`:
  - Verify order is `paid`.
  - DB transaction.
  - Mark `approved`, set `approved_by`, `approved_at`.
  - Call `Commission::processProductPV($orderId)` (reads `binary_position` from the order row).
  - Commit.
- `reject($orderId, $adminId)`:
  - Verify order is `pending` or `paid`.
  - Mark `rejected`.
  - No stock column change — the rejected status removes these items from the reserved calculation automatically.
  - No PV distribution.

### PV distribution update (behavior change from current)
`Commission::processProductPV(int $orderId)` replaces the existing method that read from `repeat_purchases`. Changes:

1. Load order header and all order items from `repeat_purchase_orders` and `repeat_purchase_order_items`.

2. **Pay yourself first (new):** Add the order's total PV to the buyer's own selected leg (`left_pv` or `right_pv` on the `users` row). Trigger pairing at the buyer level via the existing `applyBinaryPv()` logic. The buyer is not gated from receiving PV on their own leg. Currently the buyer never receives binary leg PV from repeat purchases — this is new.

3. **Binary tree walk (unchanged from current):** Add the same total PV flowing up from the buyer's binary parent, on the leg determined by the buyer's fixed `binary_position` in the `users` table (the side where the buyer sits under that parent), then continue up the tree using each ancestor's own fixed `binary_position`. The checkout `left`/`right` choice does **not** affect ancestors. This matches the existing `processBinaryPV()` traversal logic exactly — no changes needed to the tree walk.

4. **Package-level Personal PV gate (changed):** For every ancestor above the buyer, apply the gate using the ancestor's own package's `personal_pv_requirement` (not the global setting). `meetsPersonalPvRequirement(int $userId)` must be rewritten to:
   ```php
   $user = User::find($userId);
   $pkg = Package::find($user['package_id']);
   $req = (float)($pkg['personal_pv_requirement'] ?? 0);
   return $req <= 0 || (float)$user['personal_pv'] >= $req;
   ```
   This function is only called from `processProductPV()` → `processBinaryPV()` → `applyBinaryPv()`, so the package-level change affects only repeat-purchase flows, not registration.

5. Pairing bonuses fire through the existing `applyBinaryPv()` logic (unchanged).

6. Record `pv_transactions` for every movement (unchanged).

> **Why let the buyer choose `left`/`right`?** The buyer has two binary legs (`left_pv` / `right_pv`). Allowing them to choose which leg receives the product PV at checkout lets them balance their own legs to trigger a pairing bonus at their own level. For example, if the buyer has 50 PV on the left and 100 PV on the right, buying a 50-PV product and choosing `left` gives them 50+50=100 on the left, matching the right to earn a pairing bonus. This choice **only** affects the buyer's own legs — ancestor legs are determined by each ancestor's fixed `binary_position` in the `users` table, as always.

## 6. UI/UX Design Notes

### Product catalog (`/?page=repeat_purchases`)
- Keep product cards with image, name, short description, price, PV, and stock indicator (e.g., "In stock: 12").
- Replace the inline quantity + Buy form with:
  - A quantity stepper (`-` / `+`), constrained by available stock.
  - An **Add to Cart** button.
  - Toast notification on add.
- Add a persistent **Cart badge** in the top bar and/or sidebar showing item count and total.

### Topbar cart badge
Add to `views/partials/topbar.php`:
- After rendering the e-wallet balance block (for member users), insert a cart badge:
  ```php
  <?php if (Auth::role() === 'member'): ?>
  <?php $cartCount = (new Cart)->getItemCount(Auth::id()); ?>
  <a href="?page=repeat_purchases" class="btn btn-sm position-relative me-2">
    🛒 Cart
    <?php if ($cartCount > 0): ?>
    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
      <?= $cartCount ?>
    </span>
    <?php endif; ?>
  </a>
  <?php endif; ?>
  ```

### Off-canvas cart sidebar
- Slide-in panel from the right (Bootstrap 5 offcanvas, confirmed available — `head.php` loads Bootstrap 5.3.3).
- Lists items with thumbnail, name, unit price, quantity stepper, remove button.
- Shows cart totals: total PV, total price.
- **Proceed to Checkout** button.
- **Continue Shopping** button to close the panel.

### Checkout UI
- Full order summary (items, quantities, totals).
- **Binary Side selector:** Left / Right toggle, default Left, styled like the registration position selector.
- Payment method selector styled like `reactivate.php` (`method-option` cards).
- E-Wallet preview (balance, total, remaining) when sufficient.
- Insufficient-balance warning when E-Wallet is selected but balance is too low.
- Proof upload area for external methods.
- Terms checkbox + **Place Order** button.
- Stock-validation errors displayed inline if any item became unavailable.

### Admin orders UI
- Table columns: Order #, Member, Items (thumbnail + count), Total PV, Total Price, Binary Side, Payment Method, Proof, Status, Date, Actions.
- Action buttons per status:
  - `pending` → **Mark Paid** / **Reject**
  - `paid` → **Approve** / **Reject**
  - `approved` / `rejected` / `cancelled` → no actions (read-only)
- Proof opens in a modal or new tab.
- Filters by status (Pending / Paid / Approved / Rejected / All).
- Bulk actions could be added later.

## 7. Security & Best Practices

- **CSRF** on all cart/order mutations.
- **Server-side recalculation** of totals at checkout; never trust client-sent totals.
- **Stock atomicity:** check stock availability inside the checkout transaction; the SELECT ... FOR UPDATE or REPEATABLE READ ensures concurrent orders see consistent reservations.
- **DB transactions** for e-wallet debits and PV distribution to avoid partial state.
- **File upload validation:** MIME check, size limit ≤ 5 MB, unique filename, store under `uploads/repeat_purchase_proofs/`. Create the directory in the migration if it does not exist, or check-and-create on first admin action.
- **Race-condition guard:** lock or transaction around e-wallet balance check + debit.
- **Idempotency:** if a member double-clicks Place Order, only one order is created (clear cart immediately inside transaction, and validate cart is active before creating order).
- **Package-level Personal PV gate** — `Commission::meetsPersonalPvRequirement()` rewritten to use the user's own package's `personal_pv_requirement`. Remove the global setting from admin settings UI.
- **Authorization:** ensure admin actions verify the order status transition is valid.

## 8. Migration Script Outline

Migration file: `migrations/027_add_cart_and_order_tables.sql`

```sql
-- 1. Add stock to products
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

-- 3. Create new tables
CREATE TABLE carts (...);
CREATE TABLE cart_items (...);
CREATE TABLE repeat_purchase_orders (...);
CREATE TABLE repeat_purchase_order_items (...);

-- 4. Migrate existing repeat_purchases into the new order model
INSERT INTO repeat_purchase_orders
  (id, member_id, total_pv, total_price, binary_position, payment_method, proof_image, status, approved_by, approved_at, created_at)
SELECT
  id, member_id, total_pv, total_price, COALESCE(u.binary_position, 'left'), 'gcash',
  proof_image,
  status,
  approved_by,
  approved_at,
  created_at
FROM repeat_purchases rp
JOIN users u ON u.id = rp.member_id;

-- Migrate each old row as one line item
-- Guard against quantity=0: fall back to the product's original pv_value and price
INSERT INTO repeat_purchase_order_items
  (order_id, product_id, quantity, unit_price, unit_pv, total_price, total_pv)
SELECT
  rp.id, rp.product_id, rp.quantity,
  CASE WHEN rp.quantity > 0 THEN rp.total_price / rp.quantity ELSE COALESCE(p.price, 0) END,
  CASE WHEN rp.quantity > 0 THEN rp.total_pv / rp.quantity ELSE COALESCE(p.pv_value, 0) END,
  rp.total_price, rp.total_pv
FROM repeat_purchases rp
LEFT JOIN products p ON p.id = rp.product_id;

-- 5. Drop old table immediately
DROP TABLE repeat_purchases;

-- 6. Remove the global setting
DELETE FROM settings WHERE key_name = 'personal_pv_requirement';

-- 7. Create proof upload directory
-- (handled outside SQL: mkdir -p uploads/repeat_purchase_proofs/ && chmod 755)
```

## 9. Suggested Routing

| Page | Controller::method | Guard |
|---|---|---|
| `repeat_purchases` | `MemberController::repeatPurchases` | member |
| `add_to_cart` | `MemberController::addToCart` | member |
| `update_cart_item` | `MemberController::updateCartItem` | member |
| `remove_cart_item` | `MemberController::removeCartItem` | member |
| `checkout` | `MemberController::checkout` | member |
| `place_order` | `MemberController::placeOrder` | member |
| `admin_repeat_purchase_orders` | `AdminController::repeatPurchaseOrders` | admin |
| `admin_mark_repeat_order_paid` | `AdminController::markRepeatOrderPaid` | admin |
| `admin_approve_repeat_order` | `AdminController::approveRepeatOrder` | admin |
| `admin_reject_repeat_order` | `AdminController::rejectRepeatOrder` | admin |

## 10. Decisions Made

| # | Question | Decision |
|---|---|---|
| 1 | Cart persistence | **DB-backed cart** |
| 2 | Cart UI | **Off-canvas sidebar** |
| 3 | Inventory model | **Reservation model:** `products.stock` = absolute inventory; available = `stock - reserved`; stock column **never** mutated by orders |
| 4 | Order statuses | `pending / paid / approved / rejected / cancelled` |
| 5 | Admin review | **Two-step:** Mark Paid → Approve |
| 6 | Old table | **Drop `repeat_purchases` immediately** |
| 7 | E-Wallet source | **Debit from total e-wallet balance** |
| 8 | Binary placement | Buyer selects **Left/Right** at checkout; default **Left**; used only for the buyer's own leg ("pay yourself first"). Ancestor tree walk is unchanged — each ancestor's fixed `binary_position` from the `users` table determines which leg receives PV. |
| 9 | Personal PV gate | Per-**upline** package: each upline must meet their own package's `personal_pv_requirement`. Global setting removed from admin settings UI. |
| 10 | Pay yourself first | Buyer now receives binary leg PV on their chosen side (new behavior — current system never gives the buyer binary PV from repeat purchases). `processProductPV()` credits buyer's left/right leg before walking up the tree. |
| 11 | Migration number | **027** (latest existing is 026) |
| 12 | Stock helpers | `Product::availableStock(int $productId): int` only. No `decrementStock()`/`incrementStock()` — the reservation model does not mutate `products.stock`. |

## 11. Next Steps After Approval

1. Create migration `027_add_cart_and_order_tables.sql` and update `install.sql`.
2. Create `uploads/repeat_purchase_proofs/` directory.
3. Implement `models/Product.php` — add `availableStock()` helper only.
4. Implement `models/Cart.php` and `models/RepeatPurchaseOrder.php`.
5. Refactor `controllers/MemberController.php` and `controllers/AdminController.php`.
6. Add cart badge to `views/partials/topbar.php`.
7. Remove `personal_pv_requirement` input from `views/admin/settings.php`.
8. Rebuild `views/member/repeat_purchases.php` (catalog + off-canvas cart + checkout).
9. Rebuild `views/admin/repeat_purchases.php` (order-level two-step review).
10. Update `core/Commission.php::processProductPV(int $orderId)` — load from `repeat_purchase_orders`, add "pay yourself first" using the order's `binary_position`, rewrite `meetsPersonalPvRequirement()` to per-package. Ancestor tree walk (`processBinaryPV`) unchanged.
11. Run full syntax checks, local smoke tests, and end-to-end purchase tests.
