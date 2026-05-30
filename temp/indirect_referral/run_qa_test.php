<?php
/**
 * QA Test Runner: Disable Indirect Referral Toggle
 * Run: php temp/indirect_referral/run_qa_test.php
 */

require 'config/db.php';
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
echo "========== QA TEST: Indirect Referral Toggle ==========\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n";

$originalToggle = $pdo->query("SELECT value FROM settings WHERE key_name = 'indirect_referral_enabled'")->fetchColumn();
echo "Original toggle state: " . var_export($originalToggle, true) . "\n";

// Clean prior QA users
$pdo->exec("DELETE FROM users WHERE username LIKE 'qa_indirect_%'");

// Get sponsor info
$sponsor = $pdo->query("SELECT id, username, sponsor_id FROM users WHERE username = 'test1' LIMIT 1")->fetch();
if (!$sponsor) {
    echo "ERROR: test1 not found\n";
    exit(1);
}

// ── TEST A: Toggle ON (subprocess) ──
section('TEST A: Toggle ON — Indirect commissions ARE paid');
$outA = shell_exec("php " . __DIR__ . "/sub_test_register.php test1 1 qa_indirect_on_" . time());
echo $outA;
$linesA = array_filter(explode("\n", $outA));
$lastA = end($linesA);
check(strpos($lastA, 'INDIRECT_OK') !== false, 'T1: Indirect commissions created with toggle ON', $lastA);

// ── TEST B/C/D: Toggle OFF (subprocess) ──
section('TEST B/C/D: Toggle OFF — No indirect commissions, no ledger');
$outB = shell_exec("php " . __DIR__ . "/sub_test_register.php test1 0 qa_indirect_off_" . time());
echo $outB;
$linesB = array_filter(explode("\n", $outB));
$lastB = end($linesB);
check(strpos($lastB, 'INDIRECT_ZERO') !== false, 'T2: ZERO indirect commissions with toggle OFF', $lastB);

// ── TEST E: Re-enable (subprocess) ──
section('TEST E: Re-enable — Indirect commissions resume');
$outE = shell_exec("php " . __DIR__ . "/sub_test_register.php test1 1 qa_indirect_re_" . time());
echo $outE;
$linesE = array_filter(explode("\n", $outE));
$lastE = end($linesE);
check(strpos($lastE, 'INDIRECT_OK') !== false, 'T3: Indirect commissions resume after re-enable', $lastE);

// ── TEST F: Code Review Checks ──
section('TEST F: UI/Code Verification');

check(strpos(file_get_contents('views/member/dashboard.php'), "indirect_referral_enabled") !== false, 'F1: Dashboard has conditional');
check(strpos(file_get_contents('views/member/earnings.php'), "indirect_referral_enabled") !== false, 'F2: Earnings has conditional');
check(strpos(file_get_contents('views/member/genealogy.php'), "indirect_referral_enabled") !== false, 'F3: Genealogy has conditional');
check(strpos(file_get_contents('views/member/cap_status.php'), "indirect_referral_enabled") !== false, 'F4: Cap Status has conditional');
check(strpos(file_get_contents('views/admin/packages.php'), "indirect_referral_enabled") !== false, 'F5: Admin Packages has conditional');
check(strpos(file_get_contents('views/admin/user_view.php'), "indirect_referral_enabled") !== false, 'F6: Admin User View has conditional');
check(strpos(file_get_contents('views/admin/settings.php'), "indirect_referral_enabled") !== false, 'F7: Admin Settings has toggle');

$commContent = file_get_contents('core/Commission.php');
check(strpos($commContent, "setting('indirect_referral_enabled', '1') !== '1'") !== false, 'F8: Commission.php has early return');

$userContent = file_get_contents('models/User.php');
check(strpos($userContent, "if (setting('indirect_referral_enabled', '1') === '1')") !== false, 'F9: User::register gates indirect call');

$adminContent = file_get_contents('controllers/AdminController.php');
check(strpos($adminContent, "'indirect_referral_enabled'") !== false, 'F10: AdminController whitelist includes toggle');

// Check checkbox handling array
$chkPos = strpos($adminContent, "['gcash_enabled', 'maya_enabled'");
check($chkPos !== false && strpos(substr($adminContent, $chkPos, 200), "'indirect_referral_enabled'") !== false, 'F11: AdminController checkbox handling');

// Check Direct Referrals table exists in genealogy when indirect is OFF
$genealogyContent = file_get_contents('views/member/genealogy.php');
check(strpos($genealogyContent, 'Direct Referrals') !== false, 'F12: Genealogy shows Direct Referrals table when OFF');

// ── RESTORE ORIGINAL TOGGLE ──
$pdo->prepare("INSERT INTO settings (key_name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = ?")
    ->execute(['indirect_referral_enabled', $originalToggle, $originalToggle]);

// ── REPORT ──
echo "\n========== FINAL REPORT ==========\n";
echo str_pad('Test', 60) . str_pad('Result', 10) . "Detail\n";
echo str_repeat('-', 110) . "\n";
foreach ($tests as $t) {
    $status = $t['pass'] ? 'PASS' : 'FAIL';
    echo str_pad($t['name'], 60) . str_pad($status, 10) . ($t['detail'] ?? '') . "\n";
}
echo str_repeat('-', 110) . "\n";
echo "TOTAL: $pass passed, $fail failed\n";
echo "==================================\n";

exit($fail > 0 ? 1 : 0);
