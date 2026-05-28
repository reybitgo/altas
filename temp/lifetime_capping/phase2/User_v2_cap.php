<?php

/**
 * @file   models/User.php
 * @brief  User model with v2 cap status helpers
 */
class User
{
    // ── Existing methods preserved ───────────────────────────────────────
    // find(), findByUsername(), usernameExists(), register(), updateProfile(),
    // updatePassword(), verifyPassword(), isSlotFree(), counts(), allMembers(),
    // todayPairingStatus(), indirectReferralTree(), etc.

    // ... existing methods remain unchanged ...

    // ── v2 Cap Status Helpers ───────────────────────────────────────────

    /**
     * Get cap status for a user.
     * Delegates to CapEngine for consistency.
     */
    public static function getCapStatus(int $userId): array
    {
        return CapEngine::getCapStatus($userId);
    }

    /**
     * Check if user is active for binary pair counting.
     */
    public static function isCapActive(int $userId): bool
    {
        return CapEngine::isActiveForPairs($userId);
    }

    /**
     * Get cap-aware pairing status for dashboard.
     * Extends todayPairingStatus() with cap info.
     */
    public static function todayPairingStatus(int $userId): array
    {
        $pdo = db();
        $st = $pdo->prepare("
            SELECT
                u.pairs_paid,
                u.pairs_paid_today,
                u.pairs_flushed,
                u.left_count,
                u.right_count,
                p.pairing_bonus,
                p.daily_pair_cap,
                u.lifetime_earned,
                u.cap_status,
                (p.entry_fee * p.lifetime_cap_multiplier) AS lifetime_cap
            FROM users u
            LEFT JOIN packages p ON p.id = u.package_id
            WHERE u.id = ?
        ");
        $st->execute([$userId]);
        $row = $st->fetch();

        if (!$row) {
            return [
                'pairs_paid'       => 0,
                'pairs_paid_today' => 0,
                'pairs_flushed'    => 0,
                'left_count'       => 0,
                'right_count'      => 0,
                'pairing_bonus'    => 0,
                'daily_cap'        => 0,
                'cap_percent'      => 0,
                'cap_remaining'    => 0,
                'earned_today'     => fmt_money(0),
                'lifetime_earned'  => 0,
                'lifetime_cap'     => 0,
                'cap_status'       => 'perminact',
            ];
        }

        $paidToday = (int)$row['pairs_paid_today'];
        $dailyCap  = (int)$row['daily_pair_cap'];
        $bonus     = (float)$row['pairing_bonus'];
        $capPct    = $dailyCap > 0 ? min(100, ($paidToday / $dailyCap) * 100) : 0;
        $capRem    = max(0, $dailyCap - $paidToday);
        $earnedToday = $paidToday * $bonus;

        return [
            'pairs_paid'       => (int)$row['pairs_paid'],
            'pairs_paid_today' => $paidToday,
            'pairs_flushed'    => (int)$row['pairs_flushed'],
            'left_count'       => (int)$row['left_count'],
            'right_count'      => (int)$row['right_count'],
            'pairing_bonus'    => $bonus,
            'daily_cap'        => $dailyCap,
            'cap_percent'      => round($capPct, 1),
            'cap_remaining'    => $capRem,
            'earned_today'     => fmt_money($earnedToday),
            'lifetime_earned'  => (float)$row['lifetime_earned'],
            'lifetime_cap'     => (float)$row['lifetime_cap'],
            'cap_status'       => $row['cap_status'],
        ];
    }
}
