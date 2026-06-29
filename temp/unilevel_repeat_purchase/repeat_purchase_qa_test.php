<?php

/**
 * @file   temp/unilevel_repeat_purchase/repeat_purchase_qa_test.php
 * @brief  Automated QA tests for Binary Repeat PV + Unilevel Product commissions.
 *
 * Usage:  php temp/unilevel_repeat_purchase/repeat_purchase_qa_test.php
 *
 * Tests the data layer (commissions, PV, e-wallet, pairing) end-to-end.
 * Does NOT test browser UI — verify those manually per repeat_purchase_qa_test.md.
 *
 * Binary Tree:
 *         admin(root)
 *            │
 *            A (right)
 *           / \
 *          B   C
 *              │
 *              D
 *              │
 *              E
 *
 * Sponsor Chain:  admin → A → B → C → D → E
 *
 * When D buys on 'left':
 *   Binary PV: D.left → C.left → A.right → admin.right
 *   Unilevel:  C=L1(15%), B=L2(5%), A=L3(3%), admin=L4(2.5%)
 *
 * When B buys on 'left':
 *   Binary PV: B.left → A.left  (now A has left+right → PAIRING!)
 */

// ── Bootstrap ──────────────────────────────────────────────────────────
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../core/helpers.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Commission.php';
require_once __DIR__ . '/../../core/CapEngine.php';
require_once __DIR__ . '/../../core/DailyFixedIncome.php';
require_once __DIR__ . '/../../core/Reactivation.php';
spl_autoload_register(function (string $class): void {
    foreach ([__DIR__ . '/../../models/', __DIR__ . '/../../controllers/'] as $dir) {
        $file = $dir . $class . '.php';
        if (file_exists($file)) { require_once $file; return; }
    }
});

// ── Test Helpers ──────────────────────────────────────────────────────
$passed  = 0; $failed  = 0; $section = '';
function test_section(string $name): void {
    global $section; $section = $name;
    echo "\n" . str_repeat('=', 72) . "\n  {$name}\n" . str_repeat('=', 72) . "\n";
}
function test_step(string $label): void { echo "  • {$label} ... "; flush(); }
function pass(string $msg = ''): void { global $passed; $passed++; echo "\033[32mPASS\033[0m" . ($msg ? " — {$msg}" : '') . "\n"; }
function fail(string $msg): void { global $failed; $failed++; echo "\033[31mFAIL\033[0m — {$msg}\n"; }
function eq(mixed $expected, mixed $actual, string $label): void {
    if ($expected === $actual) { pass($label); }
    else { fail("{$label}: expected " . var_export($expected, true) . ", got " . var_export($actual, true)); }
}
function eqf(float $expected, float $actual, string $label, float $eps = 0.0001): void {
    if (abs($expected - $actual) < $eps) { pass($label); }
    else { fail("{$label}: expected {$expected}, got {$actual} (diff " . abs($expected - $actual) . ")"); }
}
function assert_true(mixed $val, string $label): void {
    if ($val) { pass($label); }
    else { fail("{$label}: expected truthy, got " . var_export($val, true)); }
}
function assert_gt(float $min, float $actual, string $label): void {
    if ($actual > $min) { pass($label); }
    else { fail("{$label}: expected > {$min}, got {$actual}"); }
}

// ── DB Cleanup ─────────────────────────────────────────────────────────
function delete_users_by_username(array $usernames): void {
    $pdo = db();
    foreach ($usernames as $u) {
        $st = $pdo->prepare("SELECT id FROM users WHERE username = ?"); $st->execute([$u]);
        $id = $st->fetchColumn();
        if ($id) {
            foreach (['pv_transactions','commissions'] as $t) {
                $pdo->prepare("DELETE FROM {$t} WHERE user_id = ? OR source_user_id = ?")->execute([$id, $id]);
            }
            $pdo->prepare("DELETE FROM repeat_purchase_order_items WHERE order_id IN (SELECT id FROM repeat_purchase_orders WHERE member_id = ?)")->execute([$id]);
            $pdo->prepare("DELETE FROM repeat_purchase_orders WHERE member_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM ewallet_ledger WHERE user_id = ?")->execute([$id]);
            foreach (['cd_status_ledger','cap_earnings','cd_ledger'] as $t) {
                try { $pdo->prepare("DELETE FROM {$t} WHERE user_id = ?")->execute([$id]); } catch (PDOException $e) {}
            }
            try { $pdo->prepare("DELETE FROM cd_status WHERE user_id = ?")->execute([$id]); } catch (PDOException $e) {}
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
        }
    }
}
function delete_test_products(): void {
    $pdo = db();
    foreach ($pdo->query("SELECT id, name FROM products WHERE name LIKE 'QA RP%'") as $r) {
        foreach (['product_unilevel_levels','cart_items','repeat_purchase_order_items','register_bonus_products'] as $t) {
            try { $pdo->prepare("DELETE FROM {$t} WHERE product_id = ?")->execute([$r['id']]); } catch (PDOException $e) {}
        }
        try { $pdo->prepare("DELETE FROM products WHERE id = ?")->execute([$r['id']]); } catch (PDOException $e) {}
    }
}

// ── Utility ────────────────────────────────────────────────────────────
function user_pv(int $uid): array {
    $u = User::find($uid);
    return [
        'personal_pv'=>(float)$u['personal_pv'], 'group_pv'=>(float)$u['group_pv'],
        'left_pv'=>(float)$u['left_pv'], 'right_pv'=>(float)$u['right_pv'],
        'paired_pv'=>(float)$u['paired_pv'], 'ewallet'=>(float)$u['ewallet_balance'],
    ];
}
function pv_snapshot(array $ids): array {
    $s = [];
    foreach ($ids as $label => $uid) $s[$label] = user_pv($uid);
    return $s;
}
function pv_delta(array $after, array $before, string $field): float {
    return (float)($after[$field] ?? 0) - (float)($before[$field] ?? 0);
}

// ── Start ──────────────────────────────────────────────────────────────
echo "\n" . str_repeat('█', 72) . "\n";
echo "  BINARY REPEAT + UNILEVEL PRODUCT — Automated QA Tests\n";
echo "  " . date('Y-m-d H:i:s') . "\n" . str_repeat('█', 72) . "\n\n";
$pdo = db();

// ══════════════════════════════════════════════════════════════════════
//  1. SETUP
// ══════════════════════════════════════════════════════════════════════
test_section('1. Setup');

test_step('Enable binary_repeat_enabled + unilevel_product_enabled');
$pdo->prepare("INSERT INTO settings (key_name, value) VALUES ('binary_repeat_enabled', '1') ON DUPLICATE KEY UPDATE value = '1'")->execute();
$pdo->prepare("INSERT INTO settings (key_name, value) VALUES ('unilevel_product_enabled', '1') ON DUPLICATE KEY UPDATE value = '1'")->execute();
pass('Done');

test_step('Create test product with unilevel levels (L1=15%, L2=5%, L3=3%, L4=2.5%)');
delete_test_products();
$prodId = Product::save([
    'name'=>'QA RP Product','price'=>100.00,'product_pv'=>5.00,'pv_value'=>100.00,'stock'=>999,
    'image_url'=>null,'short_description'=>'QA repeat purchase test product','description'=>'','status'=>'active',
    'unilevel_levels'=>[1=>15.00,2=>5.00,3=>3.00,4=>2.50,5=>0,6=>0,7=>0,8=>0,9=>0,10=>0],
]);
assert_true($prodId > 0, "Product created (ID {$prodId})");
$effPv = 5.00; // product_pv * (pv_value/100) = 5.00

test_step('Verify unilevel levels persisted');
$lv = Product::getUnilevelLevels($prodId);
eqf(15.00, $lv[1]??0, 'L1=15%'); eqf(5.00, $lv[2]??0, 'L2=5%');
eqf(3.00, $lv[3]??0, 'L3=3%'); eqf(2.50, $lv[4]??0, 'L4=2.5%');

test_step('Find active package');
$pkg = $pdo->query("SELECT * FROM packages WHERE status='active' ORDER BY entry_fee ASC LIMIT 1")->fetch();
assert_true(!empty($pkg), 'Package available');
$pkgId = (int)$pkg['id'];
$pdo->prepare("UPDATE packages SET personal_pv_requirement=0, lifetime_cap_multiplier=999 WHERE id=?")->execute([$pkgId]);
$adminId = (int)$pdo->query("SELECT id FROM users WHERE role='admin' ORDER BY id ASC LIMIT 1")->fetchColumn();
$pdo->prepare("UPDATE users SET ewallet_balance=10000000, withdrawable_balance=10000000 WHERE id=?")->execute([$adminId]);
delete_users_by_username(['qa_rp_A','qa_rp_B','qa_rp_C','qa_rp_D','qa_rp_E']);
$rate = (float)$pdo->query("SELECT value FROM settings WHERE key_name='pv_per_peso_rate' LIMIT 1")->fetchColumn();
pass("pkg={$pkg['name']}(id={$pkgId}) admin={$adminId} rate={$rate} effPV={$effPv}");

// ══════════════════════════════════════════════════════════════════════
//  2. CREATE TEST USERS
// ══════════════════════════════════════════════════════════════════════
test_section('2. Create Test Users');
echo "  Tree: admin(root) → A(right) → [B(left), C(right)] → D(left of C) → E(left of D)\n";
echo "  Sponsor: admin→A→B→C→D→E\n\n";

$mA = User::register(['username'=>'qa_rp_A','password'=>'Test@1234','package_id'=>$pkgId,
    'sponsor_id'=>$adminId,'binary_parent_id'=>$adminId,'binary_position'=>'right',
    'status'=>'active','reg_payment_method'=>'ewallet','reg_paid_by'=>$adminId,'reg_code_id'=>null]);
$mB = User::register(['username'=>'qa_rp_B','password'=>'Test@1234','package_id'=>$pkgId,
    'sponsor_id'=>$mA,'binary_parent_id'=>$mA,'binary_position'=>'left',
    'status'=>'active','reg_payment_method'=>'ewallet','reg_paid_by'=>$adminId,'reg_code_id'=>null]);
$mC = User::register(['username'=>'qa_rp_C','password'=>'Test@1234','package_id'=>$pkgId,
    'sponsor_id'=>$mB,'binary_parent_id'=>$mA,'binary_position'=>'right',
    'status'=>'active','reg_payment_method'=>'ewallet','reg_paid_by'=>$adminId,'reg_code_id'=>null]);
$mD = User::register(['username'=>'qa_rp_D','password'=>'Test@1234','package_id'=>$pkgId,
    'sponsor_id'=>$mC,'binary_parent_id'=>$mC,'binary_position'=>'left',
    'status'=>'active','reg_payment_method'=>'ewallet','reg_paid_by'=>$adminId,'reg_code_id'=>null]);
$mE = User::register(['username'=>'qa_rp_E','password'=>'Test@1234','package_id'=>$pkgId,
    'sponsor_id'=>$mD,'binary_parent_id'=>$mD,'binary_position'=>'left',
    'status'=>'active','reg_payment_method'=>'ewallet','reg_paid_by'=>$adminId,'reg_code_id'=>null]);

test_step('Verify');
eq($adminId,(int)User::find($mA)['sponsor_id'],'A sponsor=admin');
eq($mA,(int)User::find($mB)['sponsor_id'],'B sponsor=A');
eq($mB,(int)User::find($mC)['sponsor_id'],'C sponsor=B');
eq($mC,(int)User::find($mD)['sponsor_id'],'D sponsor=C');
eq($mD,(int)User::find($mE)['sponsor_id'],'E sponsor=D');
foreach ([$mA,$mB,$mC,$mD,$mE] as $id) eq('active', User::find($id)['status'], "User {$id} active");

$pvLabels = ['A'=>$mA,'B'=>$mB,'C'=>$mC,'D'=>$mD,'E'=>$mE,'admin'=>$adminId];
$pvs_before = pv_snapshot($pvLabels);

// ══════════════════════════════════════════════════════════════════════
//  3. BINARY PV FLOW — Member D Buys on Left
// ══════════════════════════════════════════════════════════════════════
test_section('3. Binary PV Flow — D Buys on Left');
echo "  D.left+={$effPv} → C.left+={$effPv} → A.right+={$effPv} → admin.right+={$effPv}\n";
echo "  B.left=0, B.right=0 (not in path)\n\n";

Ewallet::credit($mD, 5000, 0, 'topup', 'fund D');
$cart = Cart::getOrCreate($mD); Cart::addItem((int)$cart['id'], $prodId, 1);
$totals = Cart::getTotals((int)$cart['id']);
$oid = RepeatPurchaseOrder::createFromCart($mD, (int)$cart['id'], 'left', 'ewallet', null);
Ewallet::debit($mD, (float)$totals['total_price'], $oid, 'registration', "Order #{$oid}");
$pdo->prepare("UPDATE repeat_purchase_orders SET status='approved', paid_at=NOW(), approved_by=?, approved_at=NOW() WHERE id=?")->execute([$mD, $oid]);
Commission::processProductPV($oid);
$pvs_mid = pv_snapshot($pvLabels);

$dDl = pv_delta($pvs_mid['D'],$pvs_before['D'],'left_pv');
$dCl = pv_delta($pvs_mid['C'],$pvs_before['C'],'left_pv');
$dAr = pv_delta($pvs_mid['A'],$pvs_before['A'],'right_pv');
$dAdr = pv_delta($pvs_mid['admin'],$pvs_before['admin'],'right_pv');
$dBl = pv_delta($pvs_mid['B'],$pvs_before['B'],'left_pv');
$dBr = pv_delta($pvs_mid['B'],$pvs_before['B'],'right_pv');
eqf($effPv,$dDl,"D.left Δ={$effPv}"); eqf($effPv,$dCl,"C.left Δ={$effPv}");
eqf($effPv,$dAr,"A.right Δ={$effPv}"); eqf($effPv,$dAdr,"admin.right Δ={$effPv}");
eqf(0,$dBl,'B.left Δ=0'); eqf(0,$dBr,'B.right Δ=0');
echo "  Δ: D.left={$dDl} C.left={$dCl} A.right={$dAr} admin.right={$dAdr} B.left={$dBl} B.right={$dBr}\n";

// ══════════════════════════════════════════════════════════════════════
//  4. UNILEVEL COMMISSIONS — Member D Buys
// ══════════════════════════════════════════════════════════════════════
test_section('4. Unilevel Commissions — D Buys');
$lC = $effPv*(15/100)*$rate; $lB = $effPv*(5/100)*$rate;
$lA = $effPv*(3/100)*$rate; $lAd = $effPv*(2.5/100)*$rate;
echo "  C(L1=15%):{$lC} B(L2=5%):{$lB} A(L3=3%):{$lA} admin(L4=2.5%):{$lAd}\n\n";

$cnt_uni = function($uid,$t='unilevel_product')use($pdo){
    $st=$pdo->prepare("SELECT COUNT(*) FROM commissions WHERE user_id=? AND type=? AND status='credited'"); $st->execute([$uid,$t]); return(int)$st->fetchColumn();
};
$get_comm = function($uid,$src,$t='unilevel_product')use($pdo){
    $st=$pdo->prepare("SELECT level,amount,cap_deduction FROM commissions WHERE user_id=? AND source_user_id=? AND type=? ORDER BY level ASC LIMIT 1");
    $st->execute([$uid,$src,$t]); return $st->fetch();
};
$get_pair = function($uid)use($pdo){
    $st=$pdo->prepare("SELECT amount,pairs_count,cap_deduction FROM commissions WHERE type='pairing' AND user_id=? ORDER BY id DESC LIMIT 1");
    $st->execute([$uid]); return $st->fetch();
};

$rC=$get_comm($mC,$mD); eq(1,(int)$rC['level'],'C L1'); eqf($lC,(float)$rC['amount'],"C {$lC}");
$rB=$get_comm($mB,$mD); eq(2,(int)$rB['level'],'B L2'); eqf($lB,(float)$rB['amount'],"B {$lB}");
$rA=$get_comm($mA,$mD); eq(3,(int)$rA['level'],'A L3'); eqf($lA,(float)$rA['amount'],"A {$lA}");
$rAd=$get_comm($adminId,$mD); eq(4,(int)$rAd['level'],'admin L4'); eqf($lAd,(float)$rAd['amount'],"admin {$lAd}");
eq(0,$cnt_uni($mD),'D (buyer) 0 unilevel');
foreach ([$mC=>'C',$mB=>'B',$mA=>'A',$adminId=>'admin'] as $uid=>$label) {
    $bal = Ewallet::balance($uid); assert_gt(0,$bal,"{$label} ewallet={$bal}");
}

// ══════════════════════════════════════════════════════════════════════
//  5. BINARY PAIRING — Member B Buys on Left
// ══════════════════════════════════════════════════════════════════════
test_section('5. Binary Pairing — B Buys on Left');
$pvA_mid = user_pv($mA);
echo "  After D: A right={$pvA_mid['right_pv']} left={$pvA_mid['left_pv']}\n";
echo "  B buys left → A.left+={$effPv} → both legs>0 → PAIRING!\n\n";

$pvs_beforeB = pv_snapshot($pvLabels);
Ewallet::credit($mB, 5000, 0, 'topup', 'fund B');
$cartB = Cart::getOrCreate($mB); Cart::addItem((int)$cartB['id'], $prodId, 1);
$totalsB = Cart::getTotals((int)$cartB['id']);
$oidB = RepeatPurchaseOrder::createFromCart($mB, (int)$cartB['id'], 'left', 'ewallet', null);
Ewallet::debit($mB, (float)$totalsB['total_price'], $oidB, 'registration', "Order #{$oidB}");
$pdo->prepare("UPDATE repeat_purchase_orders SET status='approved', paid_at=NOW(), approved_by=?, approved_at=NOW() WHERE id=?")->execute([$mB, $oidB]);
Commission::processProductPV($oidB);
$pvs_afterB = pv_snapshot($pvLabels);

$dB_l = pv_delta($pvs_afterB['B'],$pvs_beforeB['B'],'left_pv');
eqf($effPv, $dB_l, "B.left Δ={$effPv}");
$dA_l = pv_delta($pvs_afterB['A'],$pvs_beforeB['A'],'left_pv');
$dA_r = pv_delta($pvs_afterB['A'],$pvs_beforeB['A'],'right_pv');
eqf($effPv, $dA_l, "A.left Δ={$effPv}");
eqf(0, $dA_r, 'A.right Δ=0');

$pvA_afterB = user_pv($mA);
$pairedBefore = $pvs_beforeB['A']['paired_pv'];
$pairedAfter  = $pvA_afterB['paired_pv'];
$newPairedPv  = $pairedAfter - $pairedBefore;
$expectedBonus = $newPairedPv * $rate;

$pairA = $get_pair($mA);
assert_true(!empty($pairA), 'A pairing commission');
echo "  Paired PV: before={$pairedBefore} after={$pairedAfter} new={$newPairedPv}\n";
echo "  Bonus: {$newPairedPv} PV × {$rate} = {$expectedBonus}\n";
eqf($expectedBonus, (float)$pairA['amount'], "Pairing bonus={$expectedBonus}");
echo "  Actual: amount={$pairA['amount']} pairs={$pairA['pairs_count']}\n";

// ══════════════════════════════════════════════════════════════════════
//  6. SIDE-BY-SIDE COMPARISON (before any cleanup)
// ══════════════════════════════════════════════════════════════════════
test_section('6. Side-by-Side — Binary vs Unilevel');

echo "\n  ┌─────────────────────────────────────────────────────────────────────────┐\n";
echo "  │              Member D Buys Product (effPV={$effPv})                          │\n";
echo "  ├──────────────────────────────┬──────────────────────────────────────────┤\n";
echo "  │  BINARY PV FLOW               │  UNILEVEL COMMISSION                     │\n";
echo "  ├──────────────────────────────┼──────────────────────────────────────────┤\n";
echo "  │  D.left_pv   += {$effPv}       │  C (L1, 15%):  " . str_pad(number_format($lC,2),10) . "             │\n";
echo "  │  C.left_pv   += {$effPv}       │  B (L2, 5%):   " . str_pad(number_format($lB,2),10) . "             │\n";
echo "  │  A.right_pv  += {$effPv}       │  A (L3, 3%):   " . str_pad(number_format($lA,2),10) . "             │\n";
echo "  │  admin.right += {$effPv}       │  admin (L4, 2.5%): " . str_pad(number_format($lAd,2),10) . "            │\n";
echo "  ├──────────────────────────────┼──────────────────────────────────────────┤\n";
echo "  │  Member B Buys (left):        │                                          │\n";
echo "  │  B.left_pv  += {$effPv}       │  (normal unilevel also fires)            │\n";
echo "  │  A.left_pv  += {$effPv}       │                                          │\n";
echo "  │  → A pairs left+right         │                                          │\n";
echo "  │  → Pairing Bonus ≈ " . sprintf('₱%.2f',$expectedBonus) . "      │                                          │\n";
echo "  └──────────────────────────────┴──────────────────────────────────────────┘\n";

echo "  Key insight:\n";
echo "  • Binary PV flows up the BINARY TREE (parent-child placement)\n";
echo "  • Unilevel flows up the SPONSOR CHAIN (who recruited whom)\n";
echo "  • These are DIFFERENT trees — a member's binary_up ≠ sponsor chain\n";
echo "  • Both fire independently on the same repeat purchase\n";

// ══════════════════════════════════════════════════════════════════════
//  7. COMMISSION SUMMARY REPORT (before any cleanup)
// ══════════════════════════════════════════════════════════════════════
test_section('7. Commission Summary');

echo "  Unilevel from D's purchase (#{$oid}):\n";
echo "  ┌──────────┬───────────┬────────┬──────────────┬────────┐\n";
echo "  │ Member   │ Type      │ Level  │ Amount       │ Status │\n";
echo "  ├──────────┼───────────┼────────┼──────────────┼────────┤\n";
foreach ([$mC=>'C',$mB=>'B',$mA=>'A',$adminId=>'admin'] as $uid=>$label) {
    $st=$pdo->prepare("SELECT type,level,amount,status FROM commissions WHERE user_id=? AND source_user_id=? AND type='unilevel_product'");
    $st->execute([$uid,$mD]);
    foreach($st as $r) echo "  │ " . str_pad($label,8) . " │ " . str_pad('Unilevel',9) . " │ " . str_pad("L{$r['level']}",6) . " │ " . str_pad(number_format((float)$r['amount'],2),12) . " │ {$r['status']} │\n";
}
echo "  └──────────┴───────────┴────────┴──────────────┴────────┘\n";

echo "\n  Commissions from B's purchase (#{$oidB}):\n";
echo "  ┌──────────┬───────────┬────────┬──────────────┬────────┐\n";
echo "  │ Member   │ Type      │ Level  │ Amount       │ Status │\n";
echo "  ├──────────┼───────────┼────────┼──────────────┼────────┤\n";
foreach ([$mA=>'A',$adminId=>'admin'] as $uid=>$label) {
    $st=$pdo->prepare("SELECT type,level,amount,status FROM commissions WHERE user_id=? AND source_user_id=? AND type='unilevel_product'");
    $st->execute([$uid,$mB]);
    foreach($st as $r) echo "  │ " . str_pad($label,8) . " │ " . str_pad('Unilevel',9) . " │ " . str_pad("L{$r['level']}",6) . " │ " . str_pad(number_format((float)$r['amount'],2),12) . " │ {$r['status']} │\n";
}
if (!empty($pairA)) echo "  │ " . str_pad('A',8) . " │ " . str_pad('Pairing',9) . " │ " . str_pad('--',6) . " │ " . str_pad(number_format((float)$pairA['amount'],2),12) . " │ credited │\n";
echo "  └──────────┴───────────┴────────┴──────────────┴────────┘\n";

echo "\n  Binary PV (cumulative):\n";
echo "  ┌──────────┬──────────┬───────────┬───────────┬───────────┐\n";
echo "  │ Member   │ Left PV  │ Right PV  │ Paired PV │ E-Wallet  │\n";
echo "  ├──────────┼──────────┼───────────┼───────────┼───────────┤\n";
foreach ([$mA=>'A',$mB=>'B',$mC=>'C',$mD=>'D',$mE=>'E',$adminId=>'admin'] as $uid=>$label) {
    $pv=user_pv($uid);
    echo "  │ " . str_pad($label,8) . " │ " . str_pad(number_format($pv['left_pv'],2),8) . " │ " . str_pad(number_format($pv['right_pv'],2),9) . " │ " . str_pad(number_format($pv['paired_pv'],2),9) . " │ " . str_pad(number_format($pv['ewallet'],2),9) . " │\n";
}
echo "  └──────────┴──────────┴───────────┴───────────┴───────────┘\n";

// ══════════════════════════════════════════════════════════════════════
//  8. PRODUCT UPDATE TEST
// ══════════════════════════════════════════════════════════════════════
test_section('8. Product Update — Change Unilevel Levels');

$updateId = Product::save([
    'name'=>'QA RP Product','price'=>100.00,'product_pv'=>5.00,'pv_value'=>100.00,'stock'=>999,
    'image_url'=>null,'short_description'=>'','description'=>'','status'=>'active',
    'unilevel_levels'=>[1=>20,2=>10,3=>5,4=>2,5=>1,6=>0.5,7=>0,8=>0,9=>0,10=>0],
], $prodId);
eq($prodId, $updateId, 'Update same ID');
$ul = Product::getUnilevelLevels($prodId);
eqf(20,$ul[1]??0,'L1=20%'); eqf(10,$ul[2]??0,'L2=10%'); eqf(5,$ul[3]??0,'L3=5%');
eqf(2,$ul[4]??0,'L4=2%'); eqf(1,$ul[5]??0,'L5=1%'); eqf(0.5,$ul[6]??0,'L6=0.5%');

Product::save(['unilevel_levels'=>[1=>15,2=>5,3=>3,4=>2.5,5=>0,6=>0,7=>0,8=>0,9=>0,10=>0]], $prodId);
eqf(15, Product::getUnilevelLevels($prodId)[1]??0, 'restored L1=15%');
pass('Done');

// ══════════════════════════════════════════════════════════════════════
//  9. PRODUCT WITH NO UNILEVEL LEVELS (binary only)
// ══════════════════════════════════════════════════════════════════════
test_section('9. Edge — No Unilevel Levels (Binary Only)');

$pNoUni = Product::save(['name'=>'QA RP NoUni','price'=>50,'product_pv'=>3,'pv_value'=>100,'stock'=>999,
    'image_url'=>null,'short_description'=>'','description'=>'','status'=>'active','unilevel_levels'=>[]]);
assert_true($pNoUni>0, "Prod ID {$pNoUni}");

$ePv3 = 3.00;
$pvE_b4 = user_pv($mE); $pvD_b4 = user_pv($mD);
$pdo->prepare("INSERT INTO repeat_purchase_orders(member_id,total_pv,total_price,binary_position,payment_method,status,created_at) VALUES(?,?,?,'left','ewallet','approved',NOW())")->execute([$mE,$ePv3,50]);
$oNU = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO repeat_purchase_order_items(order_id,product_id,quantity,unit_price,unit_pv,total_price,total_pv) VALUES(?,?,1,50,3,50,?)")->execute([$oNU,$pNoUni,$ePv3]);
$uniB4 = (int)$pdo->query("SELECT COUNT(*) FROM commissions WHERE type='unilevel_product'")->fetchColumn();
Commission::processProductPV($oNU);
$uniAf = (int)$pdo->query("SELECT COUNT(*) FROM commissions WHERE type='unilevel_product'")->fetchColumn();
eq($uniB4, $uniAf, 'No new unilevel commissions');

$pvE_af = user_pv($mE); $pvD_af = user_pv($mD);
$dEl = $pvE_af['left_pv'] - $pvE_b4['left_pv'];
$dDl = $pvD_af['left_pv'] - $pvD_b4['left_pv'];
eqf($ePv3, $dEl, "E.left Δ={$ePv3}");
eqf($ePv3, $dDl, "D.left Δ={$ePv3} (E on D's left)");

$pdo->prepare("DELETE FROM repeat_purchase_order_items WHERE order_id=?")->execute([$oNU]);
$pdo->prepare("DELETE FROM repeat_purchase_orders WHERE id=?")->execute([$oNU]);
$pdo->prepare("DELETE FROM pv_transactions WHERE source_user_id=? AND created_at>DATE_SUB(NOW(), INTERVAL 2 MINUTE)")->execute([$mE]);

// ══════════════════════════════════════════════════════════════════════
//  10. PV GATE ENFORCEMENT
// ══════════════════════════════════════════════════════════════════════
test_section('10. Edge — PV Gate Enforcement');

$pdo->prepare("UPDATE packages SET personal_pv_requirement=100 WHERE id=?")->execute([$pkgId]);
$pdo->prepare("UPDATE users SET personal_pv=200 WHERE id IN(?,?)")->execute([$mC,$mA]);
$pdo->prepare("UPDATE users SET personal_pv=0 WHERE id=?")->execute([$mB]);
$pdo->prepare("DELETE FROM commissions WHERE source_user_id=? AND type='unilevel_product'")->execute([$mD]);

$pdo->prepare("INSERT INTO repeat_purchase_orders(member_id,total_pv,total_price,binary_position,payment_method,status,created_at) VALUES(?,5,100,'left','ewallet','approved',NOW())")->execute([$mD]);
$oPv = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO repeat_purchase_order_items(order_id,product_id,quantity,unit_price,unit_pv,total_price,total_pv) VALUES(?,?,1,100,5,100,5)")->execute([$oPv,$prodId]);
Commission::processProductPV($oPv);

echo "  Trace: C(PV=200 passes) B(PV=0 fails) A(PV=200 passes)\n";
echo "  L1:C→L2:B→skip→L3:A gets bonus (level advances past skipped B)\n\n";

$rCpv=$get_comm($mC,$mD); assert_true(!empty($rCpv),'C has L1'); eq(1,(int)$rCpv['level'],'C L1');
eqf($effPv*(15/100)*$rate,(float)$rCpv['amount'],'C amount');
eq(0,$cnt_uni($mB),'B 0 unilevel (skipped)');
$rApv=$get_comm($mA,$mD); assert_true(!empty($rApv),'A has L3'); eq(3,(int)$rApv['level'],'A L3 (not L2)');
eqf($effPv*(3/100)*$rate,(float)$rApv['amount'],'A amount');

$pdo->prepare("UPDATE packages SET personal_pv_requirement=0 WHERE id=?")->execute([$pkgId]);
$pdo->prepare("UPDATE users SET personal_pv=0 WHERE id IN(?,?,?)")->execute([$mC,$mA,$mB]);
$pdo->prepare("DELETE FROM commissions WHERE source_user_id=? AND created_at>DATE_SUB(NOW(),INTERVAL 2 MINUTE)")->execute([$mD]);
$pdo->prepare("DELETE FROM pv_transactions WHERE source_user_id=? AND created_at>DATE_SUB(NOW(),INTERVAL 2 MINUTE)")->execute([$mD]);
$pdo->prepare("DELETE FROM repeat_purchase_order_items WHERE order_id=?")->execute([$oPv]);
$pdo->prepare("DELETE FROM repeat_purchase_orders WHERE id=?")->execute([$oPv]);

// ══════════════════════════════════════════════════════════════════════
//  11. ZERO PERCENT LEVELS
// ══════════════════════════════════════════════════════════════════════
test_section('11. Edge — Zero Percent Levels');

$pZero = Product::save(['name'=>'QA RP ZeroPct','price'=>75,'product_pv'=>2,'pv_value'=>100,'stock'=>999,
    'image_url'=>null,'short_description'=>'','description'=>'','status'=>'active',
    'unilevel_levels'=>[1=>0,2=>10,3=>0,4=>0,5=>0,6=>0,7=>0,8=>0,9=>0,10=>0]]);
assert_true($pZero>0, "Prod ID {$pZero}");
$ePv2 = 2.00;

$pdo->prepare("INSERT INTO repeat_purchase_orders(member_id,total_pv,total_price,binary_position,payment_method,status,created_at) VALUES(?,?,?,'left','ewallet','approved',NOW())")->execute([$mE,$ePv2,75]);
$oZ = (int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO repeat_purchase_order_items(order_id,product_id,quantity,unit_price,unit_pv,total_price,total_pv) VALUES(?,?,1,75,2,75,?)")->execute([$oZ,$pZero,$ePv2]);
Commission::processProductPV($oZ);

echo "  L1=0(D→skip) L2=10(C→paid) L3-10=0\n";
$stE=$pdo->prepare("SELECT COUNT(*) FROM commissions WHERE user_id=? AND type='unilevel_product'"); $stE->execute([$mE]);
eq(0,(int)$stE->fetchColumn(),'E (buyer) 0');
$stCz=$pdo->prepare("SELECT level,amount FROM commissions WHERE user_id=? AND type='unilevel_product' AND source_user_id=?"); $stCz->execute([$mC,$mE]);
$rCz=$stCz->fetch(); assert_true(!empty($rCz),'C has L2');
eq(2,(int)$rCz['level'],'C L2'); eqf($ePv2*(10/100)*$rate,(float)$rCz['amount'],'C amount');

$pdo->prepare("DELETE FROM commissions WHERE source_user_id=?")->execute([$mE]);
$pdo->prepare("DELETE FROM pv_transactions WHERE (user_id=? OR source_user_id=?) AND created_at>DATE_SUB(NOW(),INTERVAL 2 MINUTE)")->execute([$mE,$mE]);
$pdo->prepare("DELETE FROM repeat_purchase_order_items WHERE order_id=?")->execute([$oZ]);
$pdo->prepare("DELETE FROM repeat_purchase_orders WHERE id=?")->execute([$oZ]);

// ══════════════════════════════════════════════════════════════════════
//  SUMMARY
// ══════════════════════════════════════════════════════════════════════
test_section('Test Summary');
$total = $passed + $failed;
echo "\n" . str_repeat('─', 72) . "\n";
echo "  Total: {$total}  |  \033[32mPassed: {$passed}\033[0m  |  \033[31mFailed: {$failed}\033[0m\n";
echo str_repeat('─', 72) . "\n\n";
exit($failed > 0 ? 1 : 0);
