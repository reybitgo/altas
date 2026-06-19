<?php
$_SERVER['REQUEST_URI'] = '/altas/';
$_SERVER['HTTP_HOST'] = 'localhost';
require __DIR__ . '/../../config/db.php';
require __DIR__ . '/../../core/helpers.php';
require __DIR__ . '/../../models/Product.php';
require __DIR__ . '/../../models/RepeatPurchase.php';

$pass = 0;
$fail = 0;

// TC-109: RepeatPurchase model works with new schema
echo "TC-109: RepeatPurchase model\n";

// find existing order
$order = RepeatPurchase::find(9);
if ($order && $order['member_id'] == 14 && $order['product_id'] == 7) {
    echo "  find(9): PASS (member={$order['member_id']}, product={$order['product_id']})\n"; $pass++;
} else {
    echo "  find(9): FAIL\n"; $fail++;
}

$order = RepeatPurchase::find(10);
if ($order && $order['member_id'] == 2 && $order['product_id'] == 7) {
    echo "  find(10): PASS (member={$order['member_id']}, product={$order['product_id']})\n"; $pass++;
} else {
    echo "  find(10): FAIL\n"; $fail++;
}

// forMember
$list = RepeatPurchase::forMember(14, 1, 20);
if (!empty($list['data']) && count($list['data']) > 0) {
    echo "  forMember(14): PASS (count=" . count($list['data']) . ")\n"; $pass++;
} else {
    echo "  forMember(14): FAIL\n"; $fail++;
}

// pending
$pending = RepeatPurchase::pending(1, 25);
if (!empty($pending['data'])) {
    echo "  pending(): PASS (count=" . count($pending['data']) . ")\n"; $pass++;
} else {
    echo "  pending(): FAIL\n"; $fail++;
}

// all
$all = RepeatPurchase::all(1, 25);
if (!empty($all['data'])) {
    echo "  all(): PASS (count=" . count($all['data']) . ")\n"; $pass++;
} else {
    echo "  all(): FAIL\n"; $fail++;
}

// create new order
$orderId = RepeatPurchase::create(2, 7, 3);
if ($orderId > 0) {
    echo "  create(2, 7, 3): PASS (id=$orderId)\n"; $pass++;
} else {
    echo "  create(2, 7, 3): FAIL\n"; $fail++;
}

echo "\n--- RESULTS ---\n";
echo "PASS: $pass, FAIL: $fail\n";
exit($fail > 0 ? 1 : 0);
