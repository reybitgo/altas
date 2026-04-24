<?php

/**
 * @file   cron/midnight_reset.php
 * @brief  Midnight reset cron job
 */

/**
 * MIDNIGHT RESET CRON
 * Crontab: 0 0 * * * /usr/bin/php /var/www/html/altasfarm/cron/midnight_reset.php
 *
 * The ONLY job of this script:
 *   Reset pairs_paid_today = 0 for all active members.
 *   This clears the daily pairing cap so members can earn again tomorrow.
 *
 * All commission calculations are real-time and happen during registration.
 * This script has zero business logic — it is purely a counter reset.
 */

// ── Timezone ──────────────────────────────────────────────────────────────────
date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/../config/db.php';

// ── Log helpers ───────────────────────────────────────────────────────────────
$logDir  = __DIR__ . '/logs';
$logFile = $logDir . '/reset_' . date('Y-m') . '.log';  // one file per month

if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

function log_line(string $level, string $message, string $logFile): void
{
    $ts   = date('Y-m-d H:i:s T');
    $line = "[{$ts}] [{$level}] {$message}" . PHP_EOL;
    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    echo $line;
}

function log_info(string $m, string $f): void
{
    log_line('INFO ', $m, $f);
}
function log_ok(string $m,   string $f): void
{
    log_line('OK   ', $m, $f);
}
function log_warn(string $m, string $f): void
{
    log_line('WARN ', $m, $f);
}
function log_error(string $m, string $f): void
{
    log_line('ERROR', $m, $f);
}

// ── Run ───────────────────────────────────────────────────────────────────────
$startTime = microtime(true);

log_info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━', $logFile);
log_info('Midnight reset started — ' . APP_NAME, $logFile);
log_info('Environment : ' . APP_ENV,  $logFile);
log_info('Database    : ' . DB_NAME,  $logFile);
log_info('Server time : ' . date('D, d M Y h:i:s A T'), $logFile);

try {
    $pdo = db();

    // ── 1. Verify DB connection ───────────────────────────────────────────────
    $pdo->query('SELECT 1');
    log_ok('Database connection established.', $logFile);

    // ── 2. Snapshot before reset ──────────────────────────────────────────────
    $totalMembers    = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'member'")->fetchColumn();
    $activeMembers   = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'member' AND status = 'active'")->fetchColumn();
    $nonZeroMembers  = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'member' AND pairs_paid_today > 0")->fetchColumn();
    $totalPairsToday = (int)$pdo->query("SELECT COALESCE(SUM(pairs_paid_today), 0) FROM users WHERE role = 'member'")->fetchColumn();

    log_info("Members (total)         : {$totalMembers}", $logFile);
    log_info("Members (active)        : {$activeMembers}", $logFile);
    log_info("Members with pairs today: {$nonZeroMembers}", $logFile);
    log_info("Total pairs today       : {$totalPairsToday}", $logFile);

    if ($nonZeroMembers === 0) {
        log_warn('No members had pairs_paid_today > 0. Reset affects 0 rows (normal if no activity today).', $logFile);
    }

    // ── 3. Perform reset ──────────────────────────────────────────────────────
    $affected = $pdo->exec("UPDATE users SET pairs_paid_today = 0 WHERE role = 'member'");
    log_ok("pairs_paid_today reset to 0. Rows updated: {$affected}", $logFile);

    // ── 4. Verify reset applied ───────────────────────────────────────────────
    $remaining = (int)$pdo->query("SELECT COALESCE(SUM(pairs_paid_today), 0) FROM users WHERE role = 'member'")->fetchColumn();
    if ($remaining === 0) {
        log_ok('Verification passed — all pairs_paid_today confirmed at 0.', $logFile);
    } else {
        log_warn("Verification warning — {$remaining} total pairs_paid_today remain non-zero after reset.", $logFile);
    }

    // ── 5. Update last_reset timestamp ────────────────────────────────────────
    $resetTs = date('Y-m-d H:i:s');
    $pdo->prepare("UPDATE settings SET value = ? WHERE key_name = 'last_reset'")
        ->execute([$resetTs]);
    log_ok("last_reset timestamp updated: {$resetTs}", $logFile);

    // ── 6. Summary ────────────────────────────────────────────────────────────
    $elapsed = round((microtime(true) - $startTime) * 1000, 2);
    log_info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━", $logFile);
    log_ok("Reset complete. Duration: {$elapsed}ms", $logFile);
    log_info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━", $logFile);

    exit(0);
} catch (\Exception $e) {
    $elapsed = round((microtime(true) - $startTime) * 1000, 2);
    log_error("Reset FAILED after {$elapsed}ms", $logFile);
    log_error('Exception : ' . $e->getMessage(), $logFile);
    log_error('File      : ' . $e->getFile() . ':' . $e->getLine(), $logFile);
    log_info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━', $logFile);
    exit(1);
}
