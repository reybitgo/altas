# Shopping-Cart Repeat Purchase Redesign — Implementation Plan

## Status
Plan updated after review. **No code changes yet.**

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
Pending/Paid ──► Reject ──► status = rejected (no PV, stock returned)
```

## 3. Data Model Changes

### Products table changes

Add stock tracking to `products`:

```sql
ALTER TABLE products
  ADD COLUMN stock INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Available stock';
```

Stock is reduced when an order is created (reserved). It is returned only on reject/cancel. Approved orders keep the stock committed.

### Packages table changes

The `personal_pv_requirement` is package-level, not a global admin setting. Add it to `packages`:

```sql
ALTER TABLE packages
  ADD COLUMN personal_pv_requirement DECIMAL(14,2) NOT NULL DEFAULT 0.00
  COMMENT 'Minimum Personal PV an upline must have to receive Group/Binary PV from this package';
```

When checking if an upline qualifies for Group/Binary PV from a repeat purchase, the system uses the **upline’s own package’s** `personal_pv_requirement`.

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
2. Create `carts`, `cart_items`, `repeat_purchase_orders`, `repeat_purchase_order_items`.
3. Migrate existing `repeat_purchases` rows into the new order tables.
4. **Drop `repeat_purchases` immediately** after migration.
5. Update `install.sql` to create the new schema directly.

## 4. Proposed File Changes

| File | Change |
|---|---|
| `migrations/026_add_cart_and_order_tables.sql` | New tables, stock column, data migration, drop old `repeat_purchases`. |
| `install.sql` | Replace old `repeat_purchases` schema with new cart/order schema; add `products.stock`. |
| `models/Product.php` | Add stock helpers: `availableStock()`, `decrementStock()`, `incrementStock()`. |
| `models/Cart.php` | New model: `getOrCreate()`, `addItem()`, `updateQty()`, `removeItem()`, `getItems()`, `getTotals()`, `clear()`, `validateStock()`. |
| `models/RepeatPurchaseOrder.php` | New model: `createFromCart()`, `find()`, `forMember()`, `pending()`, `paid()`, `all()`, `markPaid()`, `approve()`, `reject()`, `cancel()`. |
| `controllers/MemberController.php` | New actions: `cart()`, `addToCart()`, `updateCartItem()`, `removeCartItem()`, `checkout()`, `placeOrder()`. Remove `doRepeatPurchase()`. |
| `controllers/AdminController.php` | Replace `repeatPurchases()`, `approveRepeatPurchase()`, `rejectRepeatPurchase()` with order-based `repeatPurchaseOrders()`, `markRepeatOrderPaid()`, `approveRepeatOrder()`, `rejectRepeatOrder()`. |
| `views/member/repeat_purchases.php` | Convert product grid to catalog + off-canvas cart + checkout UI. |
| `views/admin/repeat_purchases.php` | Update to show order-level list with proof thumbnails and Mark Paid / Approve / Reject actions. |
| `core/Commission.php` | Update `processProductPV()` to accept an `order_id`, iterate line items, and accept/use the order's `binary_position`. |

## 5. Backend Logic Details

### Cart rules
- Each member has **one active cart** at a time.
- Adding the same product increments quantity or updates it (upsert).
- Quantity must be ≥ 1.
- If a product becomes inactive or its stock drops while in the cart, the item is flagged at checkout.
- Cart totals are recalculated from `cart_items` on every read (no stored total).
- Cart lives in the DB so it survives logout/login and device switches.

### Stock rules
- `products.stock` is the absolute inventory.
- **Reserved** stock = sum of quantities in `repeat_purchase_order_items` whose order status is `pending`, `paid`, or `approved`.
- **Available** stock = `stock - reserved`.
- At checkout, every cart line must satisfy `quantity <= available stock`.
- When an order is created, the stock is effectively reserved because the order starts as `pending` (external) or `approved` (e-wallet).
- On **reject** or **cancel**, return the quantity to `products.stock`.
- On **approve**, no stock change (already reserved at creation).

### Checkout / place order
1. Re-read the cart and verify every product is still active and prices match.
2. Verify stock availability; block the order if any item is out of stock.
3. Compute `total_price` and `total_pv`.
4. Read `binary_position` from checkout form (default `left`).
5. Determine `can_use_ewallet` = `Ewallet::balance(member_id) >= total_price`.
6. Create `repeat_purchase_orders` row in status `pending`.
7. Create `repeat_purchase_order_items` rows.
8. Decrement `products.stock` for each item (reserve inventory).
9. Clear the cart.
10. **E-Wallet path:**
    - Start DB transaction.
    - Debit e-wallet via `Ewallet::debitInternal()` from **total e-wallet balance**.
    - Mark order `paid`, set `paid_at`.
    - Mark order `approved`, set `approved_by` = member/system (or the same transaction), `approved_at`.
    - Call `Commission::processProductPV($orderId)` using the order's selected `binary_position`.
    - Commit.
11. **External payment path:**
    - Save uploaded proof image (validate MIME, size ≤ 5 MB).
    - Order remains `pending`.
    - Flash: “Order placed. Admin will review your payment proof.”

### Admin two-step review
- `markPaid($orderId, $adminId)`:
  - Verify order is `pending` and proof exists.
  - Set status = `paid`, `paid_at` = NOW().
  - No PV distribution yet.
- `approve($orderId, $adminId)`:
  - Verify order is `paid`.
  - DB transaction.
  - Mark `approved`, set `approved_by`, `approved_at`.
  - Call `Commission::processProductPV($orderId)` using the order's selected `binary_position`.
  - Commit.
- `reject($orderId, $adminId)`:
  - Verify order is `pending` or `paid`.
  - Mark `rejected`.
  - Return stock to `products.stock`.
  - No PV distribution.

### PV distribution update
`Commission::processProductPV(int $orderId)` should:
- Load order header and all order items.
- **Pay yourself first:** add the order's total PV to the buyer's own selected leg (`left_pv` or `right_pv`) and trigger pairing at the buyer level. The buyer is not gated from receiving PV on their own leg.
- Add the same total PV to the buyer's binary parent on the leg determined by the buyer's selected `binary_position` (the side where the buyer sits under that parent), then continue up the tree using each ancestor's own `binary_position`.
- For every ancestor above the buyer, apply the **package-level Personal PV gate**: the ancestor receives the Binary/Group PV only if their own `personal_pv` is at least their own package's `personal_pv_requirement`. If they do not qualify, the PV still flows past them to the next ancestor.
- Pairing bonuses fire through the existing `applyBinaryPv()` logic.
- Record `pv_transactions` for every movement.

> **Why override the starting side?** The buyer's own `binary_position` under their binary parent is fixed. Allowing the buyer to choose `left`/`right` at checkout lets them decide which of their upline's legs the purchase volume counts toward for this order, which helps members balance their binary tree.

## 6. UI/UX Design Notes

### Product catalog (`/?page=repeat_purchases`)
- Keep product cards with image, name, short description, price, PV, and stock indicator (e.g., “In stock: 12”).
- Replace the inline quantity + Buy form with:
  - A quantity stepper (`-` / `+`), constrained by available stock.
  - An **Add to Cart** button.
  - Toast notification on add.
- Add a persistent **Cart badge** in the top bar and/or sidebar showing item count and total.

### Off-canvas cart sidebar
- Slide-in panel from the right (Bootstrap 5 offcanvas).
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
- **Stock atomicity:** check and decrement stock inside a transaction to prevent overselling.
- **DB transactions** for e-wallet debits and PV distribution to avoid partial state.
- **File upload validation:** MIME check, size limit ≤ 5 MB, unique filename, store under `uploads/repeat_purchase_proofs/`.
- **Race-condition guard:** lock or transaction around e-wallet balance check + debit.
- **Idempotency:** if a member double-clicks Place Order, only one order is created (clear cart immediately inside transaction).
- **Package-level Personal PV gate** remains enforced in `Commission::processProductPV()`: each upline is checked against their own package's `personal_pv_requirement`.
- **Authorization:** ensure admin actions verify the order status transition is valid.

## 8. Migration Script Outline

```sql
-- 1. Add stock to products
ALTER TABLE products
  ADD COLUMN stock INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Available stock';

-- 2. Add package-level personal PV requirement
ALTER TABLE packages
  ADD COLUMN personal_pv_requirement DECIMAL(14,2) NOT NULL DEFAULT 0.00
  COMMENT 'Minimum Personal PV an upline must have to receive Group/Binary PV from this package';

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

-- 3. Migrate existing repeat_purchases into the new order model
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
INSERT INTO repeat_purchase_order_items
  (order_id, product_id, quantity, unit_price, unit_pv, total_price, total_pv)
SELECT
  id, product_id, quantity,
  CASE WHEN quantity > 0 THEN total_price / quantity ELSE 0 END,
  CASE WHEN quantity > 0 THEN total_pv / quantity ELSE 0 END,
  total_price, total_pv
FROM repeat_purchases;

-- 4. Drop old table immediately
DROP TABLE repeat_purchases;

-- 5. Remove the global setting (optional; keep until all code is migrated if safer)
DELETE FROM settings WHERE key_name = 'personal_pv_requirement';
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
| 3 | Inventory | Products have `stock`; reserved by pending/paid/approved orders |
| 4 | Order statuses | `pending / paid / approved / rejected / cancelled` |
| 5 | Admin review | **Two-step:** Mark Paid → Approve |
| 6 | Old table | **Drop `repeat_purchases` immediately** |
| 7 | E-Wallet source | **Debit from total e-wallet balance** |
| 8 | Binary placement | Buyer selects **Left/Right** at checkout; default **Left**; used as the starting side for binary PV distribution |
| 9 | Personal PV gate | Per-**upline** package: each upline must meet their own package's `personal_pv_requirement` to receive Group/Binary PV from a repeat purchase |

## 11. Next Steps After Approval

1. Create migration `026_add_cart_and_order_tables.sql` and update `install.sql`.
2. Implement `models/Product.php` stock helpers.
3. Implement `models/Cart.php` and `models/RepeatPurchaseOrder.php`.
4. Refactor `controllers/MemberController.php` and `controllers/AdminController.php`.
5. Rebuild `views/member/repeat_purchases.php` (catalog + off-canvas cart + checkout).
6. Rebuild `views/admin/repeat_purchases.php` (order-level two-step review).
7. Update `core/Commission.php::processProductPV()` to support order IDs and the selected binary position.
8. Run full syntax checks, local smoke tests, and end-to-end purchase tests.
