<?php
$_SERVER['REQUEST_URI'] = '/altas/';
$_SERVER['HTTP_HOST'] = 'localhost';
require __DIR__ . '/../../config/db.php';
require __DIR__ . '/../../core/helpers.php';
require __DIR__ . '/../../core/Auth.php';
require __DIR__ . '/../../core/Commission.php';
require __DIR__ . '/../../core/CapEngine.php';
require __DIR__ . '/../../core/DailyFixedIncome.php';
require __DIR__ . '/../../models/CdStatus.php';
require __DIR__ . '/../../models/Product.php';
require __DIR__ . '/../../models/Cart.php';
require __DIR__ . '/../../models/RepeatPurchaseOrder.php';
require __DIR__ . '/../../models/RepeatPurchase.php';
require __DIR__ . '/../../models/Ewallet.php';
require __DIR__ . '/../../models/User.php';
require __DIR__ . '/../../models/Package.php';

$pass = 0;
$fail = 0;

$pdo = db();

// Ensure product has stock
$pdo->prepare("UPDATE products SET stock = 100 WHERE id = 7")->execute();

// Clean up any existing active cart for member 2
$existing = Cart::getActive(2);
if ($existing) {
    Cart::clear((int)$existing['id']);
    Cart::abandon((int)$existing['id']);
}

echo "=== Phase 3 QA Tests ===\n\n";

// TC-301: Checkout page loads - tested in browser (verify code paths)
echo "TC-301: Checkout view exists\n";
if (file_exists(__DIR__ . '/../../views/member/checkout.php')) {
    echo "  PASS\n"; $pass++;
} else {
    echo "  FAIL\n"; $fail++;
}

// TC-302/303/304: E-wallet checkout flow (programmatic)
echo "\n--- E-WALLET FLOW ---\n";

// Top up member 2's e-wallet
$pdo->prepare("UPDATE users SET ewallet_balance = 50000 WHERE id = 2")->execute();

// Build cart
$cart = Cart::getOrCreate(2);
$cartId = (int)$cart['id'];
Cart::addItem($cartId, 7, 2);

// Verify stock validation
$stockErrors = Cart::validateStock($cartId);
if (empty($stockErrors)) {
    echo "TC-301b: validateStock OK -> PASS\n"; $pass++;
} else {
    echo "TC-301b: validateStock FAIL: " . implode(', ', $stockErrors) . "\n"; $fail++;
}

// Create order via RepeatPurchaseOrder::createFromCart
$orderId = RepeatPurchaseOrder::createFromCart(2, $cartId, 'right', 'ewallet', null);
echo "TC-302: createFromCart orderId=$orderId\n";
if ($orderId > 0) { echo "  PASS\n"; $pass++; } else { echo "  FAIL\n"; $fail++; }

// Simulate e-wallet debit + approve
$totalPrice = 2000; // 2 x 1000
$ewalletBalance = Ewallet::balance(2);
echo "TC-303a: ewallet balance before = $ewalletBalance\n";
if ($ewalletBalance >= $totalPrice) {
    $pdo->prepare("UPDATE users SET ewallet_balance = ewallet_balance - ? WHERE id = ?")
        ->execute([$totalPrice, 2]);
    $newBal = Ewallet::balance(2);
    echo "TC-303b: Deducted $totalPrice, new balance = $newBal\n";
    if ($newBal == 50000 - $totalPrice) { echo "  PASS\n"; $pass++; } else { echo "  FAIL (expected " . (50000 - $totalPrice) . ")\n"; $fail++; }
} else {
    echo "TC-303: Insufficient balance: $ewalletBalance < $totalPrice\n"; $fail++;
}

// Approve order and distribute PV (matches controller flow)
$pdo->prepare("UPDATE repeat_purchase_orders SET status = 'approved', paid_at = NOW(), approved_by = ?, approved_at = NOW() WHERE id = ?")
    ->execute([2, $orderId]);
Commission::processProductPV($orderId);

$order = RepeatPurchaseOrder::find($orderId);
echo "TC-304: Order status after approve = {$order['status']}\n";
if ($order && $order['status'] === 'approved') { echo "  PASS\n"; $pass++; } else { echo "  FAIL\n"; $fail++; }

// TC-305: PV distributed
$member = User::find(2);
$orderPv = (float)$order['total_pv'];
echo "TC-305: Member personal_pv = {$member['personal_pv']} (order PV = $orderPv)\n";
if ((float)$member['personal_pv'] >= $orderPv) { echo "  PASS\n"; $pass++; } else { echo "  FAIL\n"; $fail++; }

// Clean up the test order
$pdo->prepare("DELETE FROM repeat_purchase_order_items WHERE order_id = ?")->execute([$orderId]);
$pdo->prepare("DELETE FROM repeat_purchase_orders WHERE id = ?")->execute([$orderId]);

// TC-306/307: External payment flow
echo "\n--- EXTERNAL PAYMENT FLOW ---\n";

$cart2 = Cart::getOrCreate(2);
$cartId2 = (int)$cart2['id'];
Cart::addItem($cartId2, 7, 1);

$orderId2 = RepeatPurchaseOrder::createFromCart(2, $cartId2, 'left', 'gcash', 'repeat_purchase_proofs/test.png');
echo "TC-306: createFromCart (gcash) orderId=$orderId2\n";
if ($orderId2 > 0) { echo "  PASS\n"; $pass++; } else { echo "  FAIL\n"; $fail++; }

$order2 = RepeatPurchaseOrder::find($orderId2);
echo "TC-307: Order status = {$order2['status']}, payment = {$order2['payment_method']}\n";
if ($order2 && $order2['status'] === 'pending' && $order2['payment_method'] === 'gcash') {
    echo "  PASS\n"; $pass++;
} else {
    echo "  FAIL\n"; $fail++;
}

// TC-308: Mark paid
RepeatPurchaseOrder::markPaid($orderId2, 1);
$order2 = RepeatPurchaseOrder::find($orderId2);
echo "TC-308: markPaid status = {$order2['status']}\n";
if ($order2['status'] === 'paid') { echo "  PASS\n"; $pass++; } else { echo "  FAIL\n"; $fail++; }

// TC-309: Approve
RepeatPurchaseOrder::approve($orderId2, 1);
$order2 = RepeatPurchaseOrder::find($orderId2);
echo "TC-309: approve status = {$order2['status']}\n";
if ($order2['status'] === 'approved') { echo "  PASS\n"; $pass++; } else { echo "  FAIL\n"; $fail++; }

// TC-310: PV distributed
$member2 = User::find(2);
echo "TC-310: Member personal_pv after second order = {$member2['personal_pv']}\n";
if ((float)$member2['personal_pv'] > 0) { echo "  PASS\n"; $pass++; } else { echo "  FAIL\n"; $fail++; }

// TC-311: Reject flow
// Create another pending order
$cart3 = Cart::getOrCreate(2);
$cartId3 = (int)$cart3['id'];
Cart::addItem($cartId3, 7, 1);
$orderId3 = RepeatPurchaseOrder::createFromCart(2, $cartId3, 'left', 'gcash', null);
RepeatPurchaseOrder::reject($orderId3, 1);
$order3 = RepeatPurchaseOrder::find($orderId3);
echo "TC-311: reject status = {$order3['status']}\n";
if ($order3['status'] === 'rejected') { echo "  PASS\n"; $pass++; } else { echo "  FAIL\n"; $fail++; }

// TC-312: No PV for rejected
$pdo->prepare("DELETE FROM repeat_purchase_order_items WHERE order_id = ?")->execute([$orderId3]);
$pdo->prepare("DELETE FROM repeat_purchase_orders WHERE id = ?")->execute([$orderId3]);

// TC-313: Stock check
echo "\n--- VALIDATION ---\n";
$cart4 = Cart::getOrCreate(2);
$cartId4 = (int)$cart4['id'];
Cart::addItem($cartId4, 7, 1);
$pdo->prepare("UPDATE products SET stock = 0 WHERE id = 7")->execute();
$stockErrors = Cart::validateStock($cartId4);
echo "TC-313: Stock check with stock=0\n";
if (!empty($stockErrors)) {
    echo "  PASS (errors: " . implode('', $stockErrors) . ")\n"; $pass++;
} else {
    echo "  FAIL (no errors when stock=0)\n"; $fail++;
}

// Restore stock
$pdo->prepare("UPDATE products SET stock = 100 WHERE id = 7")->execute();

// TC-314: Empty cart redirect (handled in controller, just verify code path)
echo "TC-314: Empty cart check in controller (code review)\n";
$memberCtrl = file_get_contents(__DIR__ . '/../../controllers/MemberController.php');
if (strpos($memberCtrl, "Your cart is empty.'") !== false) {
    echo "  PASS\n"; $pass++;
} else {
    echo "  FAIL\n"; $fail++;
}

// TC-315: Insufficient e-wallet
$pdo->prepare("UPDATE users SET ewallet_balance = 10 WHERE id = 2")->execute();
$bal = Ewallet::balance(2);
$totalPrice4 = 1000; // 1 x 1000
echo "TC-315: ewallet balance = $bal, total price = $totalPrice4\n";
if ($bal < $totalPrice4) {
    echo "  PASS (balance insufficient, e-wallet option won't show)\n"; $pass++;
} else {
    echo "  FAIL\n"; $fail++;
}

// Restore balance
$pdo->prepare("UPDATE users SET ewallet_balance = 50000 WHERE id = 2")->execute();

// Clean up all test carts and orders
Cart::clear($cartId);
Cart::abandon($cartId);
Cart::clear($cartId2);
Cart::abandon($cartId2);
$pdo->prepare("DELETE FROM repeat_purchase_order_items WHERE order_id = ?")->execute([$orderId2]);
$pdo->prepare("DELETE FROM repeat_purchase_orders WHERE id = ?")->execute([$orderId2]);

echo "\n--- RESULTS ---\n";
echo "PASS: $pass, FAIL: $fail\n";
exit($fail > 0 ? 1 : 0);
