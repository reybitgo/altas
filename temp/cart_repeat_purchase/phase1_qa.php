<?php
$_SERVER['REQUEST_URI'] = '/altas/';
$_SERVER['HTTP_HOST'] = 'localhost';
require __DIR__ . '/../../config/db.php';
require __DIR__ . '/../../core/helpers.php';
require __DIR__ . '/../../models/Product.php';
require __DIR__ . '/../../models/Cart.php';

$pass = 0;
$fail = 0;

// TC-107: Product::availableStock()
// stock=100, 2 pending orders reserving qty=1 each, so available = 100 - 2 = 98
$available = Product::availableStock(7);
echo "TC-107: Product::availableStock(7) = $available (expected 98)\n";
if ($available === 98) { echo "  PASS\n"; $pass++; } else { echo "  FAIL\n"; $fail++; }
echo "  (stock=100, reserved=" . Product::reservedStock(7) . ")\n";

// TC-108: Cart model
$cart = Cart::getOrCreate(1);
echo "TC-108: Cart::getOrCreate(1)\n";
if (isset($cart['id']) && $cart['status'] === 'active') {
    echo "  Cart created: id={$cart['id']}, status={$cart['status']} PASS\n"; $pass++;
} else { echo "  FAIL\n"; $fail++; }

Cart::addItem($cart['id'], 7, 2);
$items = Cart::getItems($cart['id']);
if (count($items) === 1 && (int)$items[0]['quantity'] === 2) {
    echo "  addItem qty=2: PASS (qty={$items[0]['quantity']})\n"; $pass++;
} else { echo "  addItem qty=2: FAIL (count=" . count($items) . ", qty={$items[0]['quantity']})\n"; $fail++; }

Cart::addItem($cart['id'], 7, 3);
$items = Cart::getItems($cart['id']);
if ((int)$items[0]['quantity'] === 5) {
    echo "  addItem upsert qty=5: PASS (qty={$items[0]['quantity']})\n"; $pass++;
} else { echo "  addItem upsert qty=5: FAIL (qty={$items[0]['quantity']})\n"; $fail++; }

Cart::updateQuantity($cart['id'], 7, 1);
$items = Cart::getItems($cart['id']);
if ((int)$items[0]['quantity'] === 1) {
    echo "  updateQuantity to 1: PASS (qty={$items[0]['quantity']})\n"; $pass++;
} else { echo "  updateQuantity to 1: FAIL (qty={$items[0]['quantity']})\n"; $fail++; }

$totals = Cart::getTotals($cart['id']);
if ((int)$totals['total_items'] === 1) {
    echo "  getTotals items=1: PASS\n"; $pass++;
} else { echo "  getTotals items=1: FAIL ({$totals['total_items']})\n"; $fail++; }

Cart::removeItem($cart['id'], 7);
$items = Cart::getItems($cart['id']);
if (count($items) === 0) {
    echo "  removeItem: PASS\n"; $pass++;
} else { echo "  removeItem: FAIL (count=" . count($items) . ")\n"; $fail++; }

$empty = Cart::isEmpty($cart['id']);
if ($empty) {
    echo "  isEmpty: PASS\n"; $pass++;
} else { echo "  isEmpty: FAIL\n"; $fail++; }

// Cleanup: abandon cart
Cart::abandon($cart['id']);
echo "  abandon: done\n";

echo "\n--- RESULTS ---\n";
echo "PASS: $pass, FAIL: $fail\n";
exit($fail > 0 ? 1 : 0);
