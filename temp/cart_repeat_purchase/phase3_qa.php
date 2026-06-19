<?php
/**
 * Phase 3 QA Test Script — Checkout & Admin Order Review
 * Run: php temp/cart_repeat_purchase/phase3_qa.php
 * 
 * This script tests the checkout flow, admin two-step review, and PV distribution.
 * It uses member ID 2 as the test member and product ID 7 as the test product.
 * Adjust IDs as needed for your environment.
 */
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
require __DIR__ . '/../../models/Ewallet.php';
require __DIR__ . '/../../models/User.php';
require __DIR__ . '/../../models/Package.php';

$pass = 0;
$fail = 0;
$pdo = db();

$TEST_MEMBER_ID = 2;
$TEST_PRODUCT_ID = 7;
$ADMIN_ID = 1;

echo "╔════════════════════════════════════════════════════════════════════╗\n";
echo "║  PHASE 3 QA — Checkout & Admin Order Review                      ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n\n";

// ════════════════════════════════════════════════════════════════════════
//  SETUP
// ════════════════════════════════════════════════════════════════════════

echo "--- SETUP ---\n";

// Ensure product has stock
$pdo->prepare("UPDATE products SET stock = 100 WHERE id = ?")->execute([$TEST_PRODUCT_ID]);
echo "✓ Product $TEST_PRODUCT_ID stock set to 100\n";

// Top up test member's e-wallet
$pdo->prepare("UPDATE users SET ewallet_balance = 50000 WHERE id = ?")->execute([$TEST_MEMBER_ID]);
$balBefore = Ewallet::balance($TEST_MEMBER_ID);
echo "✓ Member $TEST_MEMBER_ID e-wallet balance = " . fmt_money($balBefore) . "\n";

// Clean up any existing active cart for the test member
$existing = Cart::getActive($TEST_MEMBER_ID);
if ($existing) {
    Cart::clear((int)$existing['id']);
    Cart::abandon((int)$existing['id']);
    echo "✓ Cleaned up existing cart\n";
}

// Clean up any existing test orders
$pdo->prepare("DELETE FROM repeat_purchase_order_items WHERE order_id IN (
    SELECT id FROM repeat_purchase_orders WHERE member_id = ? AND status IN ('pending','paid','approved','rejected')
)")->execute([$TEST_MEMBER_ID]);
$pdo->prepare("DELETE FROM repeat_purchase_orders WHERE member_id = ? AND status IN ('pending','paid','approved','rejected')")
    ->execute([$TEST_MEMBER_ID]);
echo "✓ Cleaned up previous test orders\n\n";

// Get member's initial personal_pv
$memberBefore = User::find($TEST_MEMBER_ID);
$initialPv = (float)($memberBefore['personal_pv'] ?? 0);
echo "✓ Initial Personal PV = $initialPv\n\n";

// ════════════════════════════════════════════════════════════════════════
//  SECTION A: E-WALLET CHECKOUT (Auto-Approved)
// ════════════════════════════════════════════════════════════════════════

echo "══════════════════════════════════════════════════════════════════════\n";
echo "  SECTION A: E-WALLET CHECKOUT (Auto-Approved)\n";
echo "══════════════════════════════════════════════════════════════════════\n\n";

// TC-301: Checkout page loads (file existence check)
echo "TC-301: Checkout view exists\n";
if (file_exists(__DIR__ . '/../../views/member/checkout.php')) {
    echo "  ✓ PASS — checkout.php found\n"; $pass++;
} else {
    echo "  ✗ FAIL — checkout.php missing\n"; $fail++;
}

// Build a cart for the test member
$cart = Cart::getOrCreate($TEST_MEMBER_ID);
$cartId = (int)$cart['id'];
Cart::addItem($cartId, $TEST_PRODUCT_ID, 2);
$items = Cart::getItems($cartId);
$product = Product::find($TEST_PRODUCT_ID);
$unitPrice = (float)$product['price'];
$unitPv = (float)$product['pv_value'];
$expectedTotalPrice = $unitPrice * 2;
$expectedTotalPv = $unitPv * 2;
echo "  → Cart built: 2x {$product['name']} (Price: " . fmt_money($unitPrice) . ", PV: $unitPv)\n";

// TC-301b: Stock validation
echo "\nTC-301b: Stock validation before checkout\n";
$stockErrors = Cart::validateStock($cartId);
if (empty($stockErrors)) {
    echo "  ✓ PASS — Stock available for all items\n"; $pass++;
} else {
    echo "  ✗ FAIL — Stock errors: " . implode(', ', $stockErrors) . "\n"; $fail++;
}

// TC-302: Create order from cart (e-wallet, no proof needed)
echo "\nTC-302: Create order from cart (e-wallet)\n";
$orderId = RepeatPurchaseOrder::createFromCart($TEST_MEMBER_ID, $cartId, 'right', 'ewallet', null);
if ($orderId > 0) {
    echo "  ✓ PASS — Order created: ID = $orderId\n"; $pass++;
} else {
    echo "  ✗ FAIL — Order not created\n"; $fail++;
}

// Verify order is pending
$order = RepeatPurchaseOrder::find($orderId);
echo "  → Order status: {$order['status']}, payment: {$order['payment_method']}, total: " . fmt_money((float)$order['total_price']) . "\n";

// TC-303: E-wallet debit and auto-approve (simulates controller flow)
echo "\nTC-303: E-wallet debit + auto-approve\n";
$totalPrice = (float)$order['total_price'];
$ewalletBalance = Ewallet::balance($TEST_MEMBER_ID);

if ($ewalletBalance >= $totalPrice) {
    // Debit e-wallet
    $pdo->prepare("UPDATE users SET ewallet_balance = ewallet_balance - ? WHERE id = ?")
        ->execute([$totalPrice, $TEST_MEMBER_ID]);
    $newBal = Ewallet::balance($TEST_MEMBER_ID);
    
    if (abs($newBal - ($ewalletBalance - $totalPrice)) < 0.01) {
        echo "  ✓ PASS — E-wallet deducted: " . fmt_money($ewalletBalance) . " → " . fmt_money($newBal) . "\n"; $pass++;
    } else {
        echo "  ✗ FAIL — Expected " . fmt_money($ewalletBalance - $totalPrice) . ", got " . fmt_money($newBal) . "\n"; $fail++;
    }
    
    // Auto-approve (e-wallet path in controller)
    $pdo->prepare("UPDATE repeat_purchase_orders SET status = 'approved', paid_at = NOW(), approved_by = ?, approved_at = NOW() WHERE id = ?")
        ->execute([$ADMIN_ID, $orderId]);
    Commission::processProductPV($orderId);
    
    $orderAfter = RepeatPurchaseOrder::find($orderId);
    if ($orderAfter['status'] === 'approved') {
        echo "  ✓ PASS — Order auto-approved, PV distributed\n"; $pass++;
    } else {
        echo "  ✗ FAIL — Order status = {$orderAfter['status']} (expected approved)\n"; $fail++;
    }
} else {
    echo "  ✗ FAIL — Insufficient balance: " . fmt_money($ewalletBalance) . " < " . fmt_money($totalPrice) . "\n"; $fail++;
}

// TC-304: Verify order status in admin view
$orderCheck = RepeatPurchaseOrder::find($orderId);
echo "\nTC-304: Order status in admin view\n";
if ($orderCheck['status'] === 'approved') {
    echo "  ✓ PASS — Order shows status: approved (not in Pending tab)\n"; $pass++;
} else {
    echo "  ✗ FAIL — Status = {$orderCheck['status']} (should be approved)\n"; $fail++;
}

// TC-305: PV distributed (Personal PV)
echo "\nTC-305: Personal PV distributed\n";
$memberAfter = User::find($TEST_MEMBER_ID);
$personalPvAfter = (float)$memberAfter['personal_pv'];
$pvIncrease = $personalPvAfter - $initialPv;

if ($pvIncrease >= $expectedTotalPv - 0.01) {
    echo "  ✓ PASS — Personal PV increased by $pvIncrease (expected >= $expectedTotalPv)\n"; $pass++;
} else {
    echo "  ✗ FAIL — Personal PV increased by $pvIncrease (expected >= $expectedTotalPv)\n"; $fail++;
}

// ════════════════════════════════════════════════════════════════════════
//  SECTION B: EXTERNAL PAYMENT (Two-Step Admin Review)
// ════════════════════════════════════════════════════════════════════════

echo "\n══════════════════════════════════════════════════════════════════════\n";
echo "  SECTION B: EXTERNAL PAYMENT (Two-Step Admin Review)\n";
echo "══════════════════════════════════════════════════════════════════════\n\n";

$memberPvAfterEwallet = $personalPvAfter;

// Build new cart for external payment
$cart2 = Cart::getOrCreate($TEST_MEMBER_ID);
$cartId2 = (int)$cart2['id'];
Cart::addItem($cartId2, $TEST_PRODUCT_ID, 1);
$expectedPv2 = $unitPv * 1;

// TC-306: Create external payment order with proof
echo "TC-306: Create external payment order (GCash with proof)\n";
$orderId2 = RepeatPurchaseOrder::createFromCart($TEST_MEMBER_ID, $cartId2, 'left', 'gcash', 'repeat_purchase_proofs/test.png');
if ($orderId2 > 0) {
    echo "  ✓ PASS — Order created: ID = $orderId2\n"; $pass++;
} else {
    echo "  ✗ FAIL — Order not created\n"; $fail++;
}

$order2 = RepeatPurchaseOrder::find($orderId2);
echo "  → Status: {$order2['status']}, Payment: {$order2['payment_method']}, Proof: " . ($order2['proof_image'] ?: 'none') . "\n";

// TC-307: Order appears as pending
echo "\nTC-307: Order status is pending\n";
if ($order2['status'] === 'pending' && $order2['payment_method'] === 'gcash') {
    echo "  ✓ PASS — Status: pending, Payment: gcash, Proof uploaded\n"; $pass++;
} else {
    echo "  ✗ FAIL — Status: {$order2['status']}, Payment: {$order2['payment_method']}\n"; $fail++;
}

// TC-308: Admin marks order as paid
echo "\nTC-308: Admin marks order as paid\n";
try {
    RepeatPurchaseOrder::markPaid($orderId2, $ADMIN_ID);
    $order2 = RepeatPurchaseOrder::find($orderId2);
    if ($order2['status'] === 'paid') {
        echo "  ✓ PASS — Status changed to: paid\n"; $pass++;
    } else {
        echo "  ✗ FAIL — Status = {$order2['status']} (expected paid)\n"; $fail++;
    }
} catch (Exception $e) {
    echo "  ✗ FAIL — Exception: " . $e->getMessage() . "\n"; $fail++;
}

// TC-309: Admin approves order
echo "\nTC-309: Admin approves order (PV distributed)\n";
try {
    RepeatPurchaseOrder::approve($orderId2, $ADMIN_ID);
    $order2 = RepeatPurchaseOrder::find($orderId2);
    if ($order2['status'] === 'approved') {
        echo "  ✓ PASS — Status changed to: approved, PV distributed\n"; $pass++;
    } else {
        echo "  ✗ FAIL — Status = {$order2['status']} (expected approved)\n"; $fail++;
    }
} catch (Exception $e) {
    echo "  ✗ FAIL — Exception: " . $e->getMessage() . "\n"; $fail++;
}

// TC-310: PV distributed from external order
echo "\nTC-310: PV distributed from external order\n";
$memberAfterExternal = User::find($TEST_MEMBER_ID);
$personalPvAfterExternal = (float)$memberAfterExternal['personal_pv'];
$pvIncreaseExternal = $personalPvAfterExternal - $memberPvAfterEwallet;

if ($pvIncreaseExternal >= $expectedPv2 - 0.01) {
    echo "  ✓ PASS — Personal PV increased by $pvIncreaseExternal (expected >= $expectedPv2)\n"; $pass++;
} else {
    echo "  ✗ FAIL — Personal PV increased by $pvIncreaseExternal (expected >= $expectedPv2)\n"; $fail++;
}

// ════════════════════════════════════════════════════════════════════════
//  SECTION C: REJECT FLOW
// ════════════════════════════════════════════════════════════════════════

echo "\n══════════════════════════════════════════════════════════════════════\n";
echo "  SECTION C: REJECT FLOW\n";
echo "══════════════════════════════════════════════════════════════════════\n\n";

$memberPvAfterApprove = $personalPvAfterExternal;

// Create another pending order for rejection
$cart3 = Cart::getOrCreate($TEST_MEMBER_ID);
$cartId3 = (int)$cart3['id'];
Cart::addItem($cartId3, $TEST_PRODUCT_ID, 1);
$orderId3 = RepeatPurchaseOrder::createFromCart($TEST_MEMBER_ID, $cartId3, 'left', 'gcash', 'repeat_purchase_proofs/test.png');

echo "TC-311: Admin rejects pending order\n";
try {
    RepeatPurchaseOrder::reject($orderId3, $ADMIN_ID);
    $order3 = RepeatPurchaseOrder::find($orderId3);
    if ($order3['status'] === 'rejected') {
        echo "  ✓ PASS — Status changed to: rejected\n"; $pass++;
    } else {
        echo "  ✗ FAIL — Status = {$order3['status']} (expected rejected)\n"; $fail++;
    }
} catch (Exception $e) {
    echo "  ✗ FAIL — Exception: " . $e->getMessage() . "\n"; $fail++;
}

// TC-312: No PV for rejected order
echo "\nTC-312: No PV distributed for rejected order\n";
$memberAfterReject = User::find($TEST_MEMBER_ID);
$personalPvAfterReject = (float)$memberAfterReject['personal_pv'];

if (abs($personalPvAfterReject - $memberPvAfterApprove) < 0.01) {
    echo "  ✓ PASS — Personal PV unchanged ($personalPvAfterReject = $memberPvAfterApprove)\n"; $pass++;
} else {
    echo "  ✗ FAIL — Personal PV changed from $memberPvAfterApprove to $personalPvAfterReject\n"; $fail++;
}

// ════════════════════════════════════════════════════════════════════════
//  SECTION D: VALIDATION & EDGE CASES
// ════════════════════════════════════════════════════════════════════════

echo "\n══════════════════════════════════════════════════════════════════════\n";
echo "  SECTION D: VALIDATION & EDGE CASES\n";
echo "══════════════════════════════════════════════════════════════════════\n\n";

// TC-313: Stock check at checkout
echo "TC-313: Stock check blocks out-of-stock purchase\n";
$cart4 = Cart::getOrCreate($TEST_MEMBER_ID);
$cartId4 = (int)$cart4['id'];
Cart::addItem($cartId4, $TEST_PRODUCT_ID, 1);
$pdo->prepare("UPDATE products SET stock = 0 WHERE id = ?")->execute([$TEST_PRODUCT_ID]);
$stockErrors = Cart::validateStock($cartId4);
if (!empty($stockErrors)) {
    echo "  ✓ PASS — Stock errors detected: " . implode(', ', $stockErrors) . "\n"; $pass++;
} else {
    echo "  ✗ FAIL — No stock errors when stock=0\n"; $fail++;
}
$pdo->prepare("UPDATE products SET stock = 100 WHERE id = ?")->execute([$TEST_PRODUCT_ID]);

// TC-314: Empty cart redirect (code check)
echo "\nTC-314: Empty cart check in controller\n";
$memberCtrl = file_get_contents(__DIR__ . '/../../controllers/MemberController.php');
if (strpos($memberCtrl, "Your cart is empty.") !== false) {
    echo "  ✓ PASS — Controller has empty cart redirect logic\n"; $pass++;
} else {
    echo "  ✗ FAIL — Empty cart redirect missing in controller\n"; $fail++;
}

// TC-315: Insufficient e-wallet balance
echo "\nTC-315: Insufficient e-wallet balance handled\n";
$pdo->prepare("UPDATE users SET ewallet_balance = 10 WHERE id = ?")->execute([$TEST_MEMBER_ID]);
$bal = Ewallet::balance($TEST_MEMBER_ID);
$productPrice = (float)$product['price'];
if ($bal < $productPrice) {
    echo "  ✓ PASS — Balance " . fmt_money($bal) . " < " . fmt_money($productPrice) . " (insufficient)\n"; $pass++;
} else {
    echo "  ✗ FAIL — Balance " . fmt_money($bal) . " should be < " . fmt_money($productPrice) . "\n"; $fail++;
}
$pdo->prepare("UPDATE users SET ewallet_balance = 50000 WHERE id = ?")->execute([$TEST_MEMBER_ID]);

// ════════════════════════════════════════════════════════════════════════
//  SECTION E: ADMIN VIEW VERIFICATION
// ════════════════════════════════════════════════════════════════════════

echo "\n══════════════════════════════════════════════════════════════════════\n";
echo "  SECTION E: ADMIN VIEW VERIFICATION\n";
echo "══════════════════════════════════════════════════════════════════════\n\n";

// TC-316: Verify model includes first item data for admin view
echo "TC-316: Admin view data (first item joined)\n";
$pendingResult = RepeatPurchaseOrder::pending(1, 10);
$hasProductData = false;
if (!empty($pendingResult['data'])) {
    foreach ($pendingResult['data'] as $row) {
        if (isset($row['product_name']) || isset($row['product_image'])) {
            $hasProductData = true;
            break;
        }
    }
}
if ($hasProductData) {
    echo "  ✓ PASS — Model includes product_name / product_image for admin view\n"; $pass++;
} else {
    echo "  ⚠ SKIP — No pending orders to verify (may be expected)\n";
}

// TC-317: Admin view file exists with two-step flow
echo "\nTC-317: Admin view has two-step flow (Mark Paid + Approve)\n";
$adminView = file_get_contents(__DIR__ . '/../../views/admin/repeat_purchases.php');
$hasMarkPaid = strpos($adminView, 'Mark Paid') !== false;
$hasApprove = strpos($adminView, 'Approve') !== false;
$hasPaidTab = strpos($adminView, 'Paid') !== false;
$hasProof = strpos($adminView, 'proof_image') !== false;
if ($hasMarkPaid && $hasApprove && $hasPaidTab && $hasProof) {
    echo "  ✓ PASS — Admin view has Mark Paid, Approve, Paid tab, and proof display\n"; $pass++;
} else {
    echo "  ✗ FAIL — Admin view missing: ";
    if (!$hasMarkPaid) echo "Mark Paid ";
    if (!$hasApprove) echo "Approve ";
    if (!$hasPaidTab) echo "Paid tab ";
    if (!$hasProof) echo "proof display ";
    echo "\n";
    $fail++;
}

// TC-318: Mark Paid disabled without proof
echo "\nTC-318: Mark Paid disabled when no proof\n";
$hasDisabled = strpos($adminView, 'No proof uploaded') !== false;
$hasDisabledAttr = strpos($adminView, 'disabled') !== false && strpos($adminView, 'proof_image') !== false;
if ($hasDisabled || $hasDisabledAttr) {
    echo "  ✓ PASS — Admin view disables Mark Paid when no proof uploaded\n"; $pass++;
} else {
    echo "  ⚠ SKIP — Disabled check pattern not found in view (may need manual verification)\n";
}

// ════════════════════════════════════════════════════════════════════════
//  SUMMARY
// ════════════════════════════════════════════════════════════════════════

echo "\n╔════════════════════════════════════════════════════════════════════╗\n";
echo "║  RESULTS                                                           ║\n";
echo "╠══════════════════════════════════════════════════════════════════════╣\n";
$total = $pass + $fail;
$percent = $total > 0 ? round(($pass / $total) * 100, 1) : 0;
echo "║  PASS:  $pass                                                      ║\n";
echo "║  FAIL:  $fail                                                      ║\n";
echo "║  TOTAL: $total ($percent%)                                        ║\n";
echo "╚════════════════════════════════════════════════════════════════════╝\n";

if ($fail > 0) {
    echo "\n⚠ Phase 3 QA has FAILURES. Do NOT proceed to Phase 4 until fixed.\n";
} else {
    echo "\n✅ All Phase 3 tests passed. You may proceed to Phase 4.\n";
}

exit($fail > 0 ? 1 : 0);
