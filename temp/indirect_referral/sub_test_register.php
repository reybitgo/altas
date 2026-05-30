<?php
/**
 * Sub-test: register a user with a specific toggle state
 * Args: sponsor_username toggle_state(0|1) username_suffix
 */
if ($argc < 4) {
    echo "Usage: php sub_test_register.php <sponsor_username> <toggle_state> <username>\n";
    exit(1);
}

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
$sponsorUsername = $argv[1];
$toggleState = $argv[2];
$username = $argv[3];

// Set toggle
$pdo->prepare("INSERT INTO settings (key_name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = ?")
    ->execute(['indirect_referral_enabled', $toggleState, $toggleState]);

$sponsor = $pdo->query("SELECT id, sponsor_id FROM users WHERE username = " . $pdo->quote($sponsorUsername))->fetch();
if (!$sponsor) {
    echo "ERROR: Sponsor not found\n";
    exit(1);
}

// Find a free upline with free slots
$upline = $pdo->query("SELECT id FROM users WHERE role='member' AND id NOT IN (SELECT DISTINCT binary_parent_id FROM users WHERE binary_parent_id IS NOT NULL) LIMIT 1")->fetch();
if (!$upline) {
    echo "ERROR: No free upline\n";
    exit(1);
}
$uplineId = $upline['id'];
$position = 'left';

// Create a temporary registration code
$tmpCode = 'QA-INDIRECT-' . strtoupper(substr(md5(uniqid()), 0, 8));
$pdo->prepare('INSERT INTO reg_codes (code, package_id, price, status, created_by, expires_at) VALUES (?,?,?,?,?,?)')
    ->execute([$tmpCode, 1, 10000, 'unused', 1, '2026-12-31']);
$codeId = $pdo->lastInsertId();

// Snapshot sponsor chain ledger counts before registration
$sponsorChain = [];
$cur = $sponsor['id'];
while ($cur) {
    $sponsorChain[] = $cur;
    $row = $pdo->query("SELECT sponsor_id FROM users WHERE id = $cur")->fetch();
    $cur = $row['sponsor_id'] ?? null;
    if (in_array($cur, $sponsorChain)) break;
}
$beforeLedger = [];
foreach ($sponsorChain as $uid) {
    $beforeLedger[$uid] = (int)$pdo->query("SELECT COUNT(*) FROM ewallet_ledger WHERE user_id = $uid AND note LIKE '%Unilevel%'")->fetchColumn();
}

try {
    $newId = User::register([
        'username' => $username,
        'password' => 'password123',
        'package_id' => 1,
        'reg_code_id' => $codeId,
        'reg_payment_method' => 'code',
        'sponsor_id' => $sponsor['id'],
        'binary_parent_id' => $uplineId,
        'binary_position' => $position,
    ]);

    $indirectCount = (int)$pdo->query("SELECT COUNT(*) FROM commissions WHERE source_user_id = $newId AND type = 'indirect_referral'")->fetchColumn();
    $directCount = (int)$pdo->query("SELECT COUNT(*) FROM commissions WHERE source_user_id = $newId AND type = 'direct_referral'")->fetchColumn();

    // Check for new unilevel ledger entries
    $newLedgerFound = false;
    foreach ($sponsorChain as $uid) {
        $after = (int)$pdo->query("SELECT COUNT(*) FROM ewallet_ledger WHERE user_id = $uid AND note LIKE '%Unilevel%'")->fetchColumn();
        if ($after > $beforeLedger[$uid]) {
            $newLedgerFound = true;
            break;
        }
    }

    // Cleanup
    $pdo->exec("DELETE FROM users WHERE id = $newId");
    $pdo->exec("DELETE FROM commissions WHERE source_user_id = $newId");
    $pdo->exec("DELETE FROM ewallet_ledger WHERE note LIKE '%Unilevel%' AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)");
    $pdo->exec("DELETE FROM reg_codes WHERE id = $codeId");

    if ($indirectCount > 0 && $toggleState === '1') {
        echo "RESULT: INDIRECT_OK indirect=$indirectCount direct=$directCount ledger_new=$newLedgerFound\n";
    } elseif ($indirectCount === 0 && $toggleState === '0' && !$newLedgerFound) {
        echo "RESULT: INDIRECT_ZERO indirect=$indirectCount direct=$directCount ledger_new=$newLedgerFound\n";
    } else {
        echo "RESULT: UNEXPECTED indirect=$indirectCount direct=$directCount ledger_new=$newLedgerFound toggle=$toggleState\n";
    }
} catch (Exception $e) {
    echo "RESULT: ERROR " . $e->getMessage() . "\n";
}
