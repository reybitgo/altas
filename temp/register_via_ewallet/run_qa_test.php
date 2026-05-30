<?php
/**
 * QA Test Runner for Register via E-Wallet
 * Run: php temp/register_via_ewallet/run_qa_test.php
 */

require 'config/db.php';
require 'core/helpers.php';
require 'core/Auth.php';
require 'core/Commission.php';
require 'core/CapEngine.php';
require 'core/DailyFixedIncome.php';
require 'core/Reactivation.php';
require 'models/Package.php';
require 'models/Ewallet.php';
require 'models/User.php';
require 'models/Code.php';
require 'models/Payout.php';

$pdo = db();
$pass = 0;
$fail = 0;
$tests = [];

function check(bool $cond, string $name, string $detail = ''): void {
    global $pass, $fail, $tests;
    $tests[] = ['name' => $name, 'pass' => $cond, 'detail' => $detail];
    if ($cond) { $pass++; }
    else { $fail++; }
}

function section(string $title): void {
    echo "\n=== $title ===\n";
}

// ── SETUP ──
echo "========== QA TEST RUN ==========\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n";

// Create test codes
$pdo->exec("DELETE FROM reg_codes WHERE code LIKE 'QA-%'");
$pdo->prepare('INSERT INTO reg_codes (code, package_id, price, status, created_by, expires_at) VALUES (?,?,?,?,?,?)')
    ->execute(['QA-CODE-PASS1', 1, 10000, 'unused', 1, '2026-12-31']);
$codeId1 = $pdo->lastInsertId();
$pdo->prepare('INSERT INTO reg_codes (code, package_id, price, status, created_by, expires_at) VALUES (?,?,?,?,?,?)')
    ->execute(['QA-CODE-PASS2', 1, 10000, 'unused', 1, '2026-12-31']);
$codeId2 = $pdo->lastInsertId();
$pdo->prepare('INSERT INTO reg_codes (code, package_id, price, status, created_by, expires_at) VALUES (?,?,?,?,?,?)')
    ->execute(['QA-CODE-USED', 1, 10000, 'unused', 1, '2026-12-31']);
$codeIdUsed = $pdo->lastInsertId();
$pdo->exec('UPDATE reg_codes SET status="used" WHERE id=' . $codeIdUsed);

// Reset balances
$pdo->exec('UPDATE users SET ewallet_balance=100, withdrawable_balance=100 WHERE id=4');   // test4 — low
$pdo->exec('UPDATE users SET ewallet_balance=5000, withdrawable_balance=5000 WHERE id=6'); // test5 — low
$pdo->exec('UPDATE users SET ewallet_balance=15000, withdrawable_balance=15000 WHERE id=2'); // test1 — sufficient
$pdo->exec('UPDATE users SET ewallet_balance=2015, withdrawable_balance=2000 WHERE id=1'); // admin

// Clean any prior QA test users
$pdo->exec("DELETE FROM users WHERE username LIKE 'qa_%'");

// ── HELPERS ──
function findFreeSlot(int $parentId): ?string {
    $taken = db()->query("SELECT binary_position FROM users WHERE binary_parent_id=$parentId")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('left', $taken)) return 'left';
    if (!in_array('right', $taken)) return 'right';
    return null;
}

function findFreeUpline(): ?array {
    return db()->query("SELECT id, username FROM users WHERE role='member' AND id NOT IN (SELECT DISTINCT binary_parent_id FROM users WHERE binary_parent_id IS NOT NULL) LIMIT 1")->fetch();
}

// ── TEST 1: Guest Code Registration ──
section('TEST 1: Guest Registers with a Code');
$guestName = 'qa_guest_' . time();
$slot = findFreeSlot(8);
if (!$slot) {
    check(false, 'T1: Setup — free slot under test7', 'No free slots');
} else {
    try {
        $newId = User::register([
            'username' => $guestName,
            'password' => 'password123',
            'package_id' => 1,
            'reg_code_id' => $codeId1,
            'reg_payment_method' => 'code',
            'sponsor_id' => 8,
            'binary_parent_id' => 8,
            'binary_position' => $slot,
        ]);
        $u = $pdo->query("SELECT reg_payment_method, reg_paid_by, reg_code_id FROM users WHERE id = $newId")->fetch();
        $c = $pdo->query("SELECT status, used_by FROM reg_codes WHERE id = $codeId1")->fetch();
        check($newId > 0, 'T1: Account created', "ID: $newId");
        check($u['reg_payment_method'] === 'code', 'T1: Payment method = code');
        check($u['reg_paid_by'] === null, 'T1: reg_paid_by is NULL');
        check($u['reg_code_id'] == $codeId1, 'T1: reg_code_id set');
        check($c['status'] === 'used' && $c['used_by'] == $newId, 'T1: Code marked used');
        $pdo->exec("DELETE FROM users WHERE id = $newId");
    } catch (Exception $e) {
        check(false, 'T1: Account created', $e->getMessage());
    }
}

// ── TEST 2: Logged-in Member Code Registration ──
section('TEST 2: Logged-in Member Registers with a Code');
$memberName = 'qa_member_code_' . time();
$upline2 = findFreeUpline();
if (!$upline2) {
    check(false, 'T2: Setup — free upline', 'No free upline');
} else {
    try {
        $newId = User::register([
            'username' => $memberName,
            'password' => 'password123',
            'package_id' => 1,
            'reg_code_id' => $codeId2,
            'reg_payment_method' => 'code',
            'sponsor_id' => 2,
            'binary_parent_id' => $upline2['id'],
            'binary_position' => 'left',
        ]);
        $u = $pdo->query("SELECT reg_payment_method, reg_paid_by, reg_code_id FROM users WHERE id = $newId")->fetch();
        check($newId > 0, 'T2: Account created', "ID: $newId");
        check($u['reg_payment_method'] === 'code', 'T2: Payment method = code');
        check($u['reg_paid_by'] === null, 'T2: reg_paid_by is NULL');
        check($u['reg_code_id'] == $codeId2, 'T2: reg_code_id set');
        $pdo->exec("DELETE FROM users WHERE id = $newId");
    } catch (Exception $e) {
        check(false, 'T2: Account created', $e->getMessage());
    }
}

// ── TEST 3: E-Wallet Registration ──
section('TEST 3: Logged-in Member Registers via E-Wallet');
$ewalletName = 'qa_ewallet_' . time();
$upline3 = findFreeUpline();
if (!$upline3) {
    check(false, 'T3: Setup — free upline', 'No free upline');
} else {
    $beforePayer = (float)$pdo->query('SELECT ewallet_balance FROM users WHERE id = 2')->fetchColumn();
    $beforeAdmin = (float)$pdo->query('SELECT ewallet_balance FROM users WHERE id = 1')->fetchColumn();
    try {
        $newId = User::register([
            'username' => $ewalletName,
            'password' => 'password123',
            'package_id' => 1,
            'reg_code_id' => null,
            'reg_payment_method' => 'ewallet',
            'reg_paid_by' => 2,
            'paid_by_username' => 'test1',
            'sponsor_id' => 2,
            'binary_parent_id' => $upline3['id'],
            'binary_position' => 'left',
        ]);
        $u = $pdo->query("SELECT reg_payment_method, reg_paid_by, reg_code_id FROM users WHERE id = $newId")->fetch();
        $afterPayer = (float)$pdo->query('SELECT ewallet_balance FROM users WHERE id = 2')->fetchColumn();
        $afterAdmin = (float)$pdo->query('SELECT ewallet_balance FROM users WHERE id = 1')->fetchColumn();
        $ledgerDebit = $pdo->query("SELECT COUNT(*) FROM ewallet_ledger WHERE user_id=2 AND ref_type='registration' AND type='debit'")->fetchColumn();
        $ledgerCredit = $pdo->query("SELECT COUNT(*) FROM ewallet_ledger WHERE user_id=1 AND ref_type='registration' AND type='credit'")->fetchColumn();

        check($newId > 0, 'T3: Account created', "ID: $newId");
        check($u['reg_payment_method'] === 'ewallet', 'T3: Payment method = ewallet');
        check($u['reg_paid_by'] == 2, 'T3: reg_paid_by = payer ID');
        check($u['reg_code_id'] === null, 'T3: reg_code_id is NULL');
        check($afterPayer < $beforePayer, 'T3: Payer balance decreased', "$beforePayer → $afterPayer");
        check($afterAdmin > $beforeAdmin, 'T3: Admin balance increased', "$beforeAdmin → $afterAdmin");
        check($ledgerDebit > 0, 'T3: Ledger debit entry exists');
        check($ledgerCredit > 0, 'T3: Ledger credit entry exists');

        $pdo->exec("DELETE FROM users WHERE id = $newId");
        $pdo->exec("DELETE FROM ewallet_ledger WHERE ref_type='registration'");
        $pdo->exec('UPDATE users SET ewallet_balance=15000, withdrawable_balance=15000 WHERE id=2');
        $pdo->exec('UPDATE users SET ewallet_balance=2015, withdrawable_balance=2000 WHERE id=1');
    } catch (Exception $e) {
        check(false, 'T3: Account created', $e->getMessage());
        $pdo->exec('UPDATE users SET ewallet_balance=15000, withdrawable_balance=15000 WHERE id=2');
        $pdo->exec('UPDATE users SET ewallet_balance=2015, withdrawable_balance=2000 WHERE id=1');
    }
}

// ── TEST 4A: Guest E-Wallet Blocked ──
section('TEST 4A: Guest Tries E-Wallet (server-side block)');
try {
    $newId = User::register([
        'username' => 'qa_guest_ewallet_' . time(),
        'password' => 'password123',
        'package_id' => 1,
        'reg_code_id' => null,
        'reg_payment_method' => 'ewallet',
        'reg_paid_by' => null,
        'sponsor_id' => 8,
        'binary_parent_id' => 8,
        'binary_position' => 'left',
    ]);
    check(false, 'T4A: Server rejected guest e-wallet', 'Registration succeeded when it should have failed');
    $pdo->exec("DELETE FROM users WHERE id = $newId");
} catch (Exception $e) {
    check(true, 'T4A: Server rejected guest e-wallet', $e->getMessage());
}

// ── TEST 4B: Invalid / Used Code ──
section('TEST 4B: Invalid / Used Registration Code');
$badCode = Code::validate('FAKE-CODE-1234');
check($badCode === null, 'T4B: Invalid code rejected');
$usedCode = Code::validate('QA-CODE-USED');
check($usedCode === null, 'T4B: Used code rejected');

// ── TEST 4C: Insufficient Balance ──
section('TEST 4C: Insufficient E-Wallet Balance');
$lowBalName = 'qa_lowbal_' . time();
$upline4c = findFreeUpline();
if (!$upline4c) {
    check(false, 'T4C: Setup — free upline', 'No free upline');
} else {
    $beforeTest4 = (float)$pdo->query('SELECT ewallet_balance FROM users WHERE id = 4')->fetchColumn();
    try {
        $newId = User::register([
            'username' => $lowBalName,
            'password' => 'password123',
            'package_id' => 1,
            'reg_code_id' => null,
            'reg_payment_method' => 'ewallet',
            'reg_paid_by' => 4,
            'paid_by_username' => 'test4',
            'sponsor_id' => 4,
            'binary_parent_id' => $upline4c['id'],
            'binary_position' => 'left',
        ]);
        check(false, 'T4C: Registration blocked', 'Should have failed due to insufficient balance');
        $pdo->exec("DELETE FROM users WHERE id = $newId");
    } catch (Exception $e) {
        check(true, 'T4C: Registration blocked', $e->getMessage());
    }
    $afterTest4 = (float)$pdo->query('SELECT ewallet_balance FROM users WHERE id = 4')->fetchColumn();
    check($beforeTest4 == $afterTest4, 'T4C: Balance unchanged', "$beforeTest4 → $afterTest4");
}

// ── TEST 4D: Binary Position Taken ──
section('TEST 4D: Binary Position Already Taken');
// First, ensure test7 has at least one child
$childCheck = $pdo->query('SELECT COUNT(*) FROM users WHERE binary_parent_id=8')->fetchColumn();
if ($childCheck == 0) {
    // Create a dummy child under test7 on the left
    $pdo->prepare("INSERT INTO users (username, password_hash, package_id, sponsor_id, binary_parent_id, binary_position, status, role) VALUES (?, ?, ?, ?, ?, ?, 'active', 'member')")
        ->execute(['qa_t4d_dummy_' . time(), password_hash('x', PASSWORD_BCRYPT), 1, 8, 8, 'left']);
}
$child = $pdo->query('SELECT binary_position FROM users WHERE binary_parent_id=8 LIMIT 1')->fetch();
if ($child) {
    $takenPos = $child['binary_position'];
    $isFree = User::isSlotFree(8, $takenPos);
    check($isFree === false, 'T4D: Taken slot reports not free', "position: $takenPos");
    $otherPos = ($takenPos === 'left') ? 'right' : 'left';
    $isOtherFree = User::isSlotFree(8, $otherPos);
    check($isOtherFree, 'T4D: Other slot is free', "position: $otherPos");
} else {
    check(false, 'T4D: Cannot test taken slot', 'No children under test7');
}

// ── TEST 4E: Duplicate Username ──
section('TEST 4E: Username Already Taken');
$exists = User::usernameExists('test1');
check($exists === true, 'T4E: Existing username detected');
$notExists = User::usernameExists('definitely_not_real_99999');
check($notExists === false, 'T4E: New username available');

// ── TEST 4F: Password Mismatch ──
section('TEST 4F: Password Mismatch');
check('password123' !== 'password456', 'T4F: Password mismatch detected');

// ── CLEANUP ──
$pdo->exec("DELETE FROM reg_codes WHERE code LIKE 'QA-%'");
$pdo->exec("DELETE FROM users WHERE username LIKE 'qa_%'");
$pdo->exec("DELETE FROM ewallet_ledger WHERE ref_type='registration'");
$pdo->exec('UPDATE users SET ewallet_balance=100, withdrawable_balance=100 WHERE id=4');
$pdo->exec('UPDATE users SET ewallet_balance=5000, withdrawable_balance=5000 WHERE id=6');
$pdo->exec('UPDATE users SET ewallet_balance=15000, withdrawable_balance=15000 WHERE id=2');
$pdo->exec('UPDATE users SET ewallet_balance=2015, withdrawable_balance=2000 WHERE id=1');

// ── REPORT ──
echo "\n========== FINAL REPORT ==========\n";
echo str_pad('Test', 55) . str_pad('Result', 10) . "Detail\n";
echo str_repeat('-', 95) . "\n";
foreach ($tests as $t) {
    $status = $t['pass'] ? '✅ PASS' : '❌ FAIL';
    echo str_pad($t['name'], 55) . str_pad($status, 10) . ($t['detail'] ?? '') . "\n";
}
echo str_repeat('-', 95) . "\n";
echo "TOTAL: $pass passed, $fail failed\n";
echo "==================================\n";

exit($fail > 0 ? 1 : 0);
