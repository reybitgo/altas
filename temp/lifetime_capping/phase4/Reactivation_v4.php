<?php

/**
 * @file   core/Reactivation.php
 * @brief  Reactivation Service — Phase 4 Implementation
 *
 * Handles member-initiated account reactivation after hitting the lifetime cap.
 * Resets lifetime_earned, dfi_days_used, and starts a new earning cycle.
 */
class Reactivation
{
    /**
     * Validate reactivation eligibility and return available payment options.
     *
     * @param int $userId User ID
     * @return array ['ok' => bool, 'fee' => float, 'days_remaining' => int, ...]
     */
    public static function requestReactivation(int $userId): array
    {
        $capStatus = CapEngine::getCapStatus($userId);

        // 1. Must be capped
        if ($capStatus['cap_status'] !== 'capped') {
            return ['ok' => false, 'error' => 'Your account is not currently capped.'];
        }

        // 2. Check if permanently inactive
        $pdo = db();
        $st = $pdo->prepare("SELECT cap_status FROM users WHERE id = ?");
        $st->execute([$userId]);
        $currentStatus = $st->fetchColumn();
        if ($currentStatus === 'perminact') {
            return ['ok' => false, 'error' => 'Your account is permanently inactive. Reactivation is no longer possible.'];
        }

        // 3. Check reactivation window
        $cappedAt   = $capStatus['capped_at'];
        $windowDays = $capStatus['reactivation_window'];
        $fee        = $capStatus['reactivation_fee'];

        $deadline = null;
        $daysRemaining = $windowDays;

        if ($cappedAt) {
            $deadline = date('Y-m-d H:i:s', strtotime($cappedAt . " +{$windowDays} days"));
            if (time() > strtotime($deadline)) {
                return ['ok' => false, 'error' => 'Your reactivation window has expired.'];
            }
            $daysRemaining = max(0, ceil((strtotime($deadline) - time()) / 86400));
        }

        // 4. Check e-wallet balance
        $ewalletBalance = Ewallet::balance($userId);
        $canUseEwallet  = $fee > 0 && $ewalletBalance >= $fee;

        return [
            'ok'              => true,
            'fee'             => $fee,
            'window_days'     => $windowDays,
            'days_remaining'  => $daysRemaining,
            'capped_at'       => $cappedAt,
            'deadline'        => $deadline,
            'ewallet_balance' => $ewalletBalance,
            'can_use_ewallet' => $canUseEwallet,
            'payment_methods' => array_values(array_filter([
                $canUseEwallet ? 'ewallet' : null,
                'gcash',
                'maya',
                'usdt',
            ])),
        ];
    }

    /**
     * Process a reactivation payment and reset cap state.
     *
     * @param int    $userId        User ID
     * @param string $paymentMethod 'ewallet' | 'gcash' | 'maya' | 'usdt' | 'admin'
     * @return array ['ok' => bool, 'message' => string, ...]
     */
    public static function processReactivation(int $userId, string $paymentMethod): array
    {
        $capStatus = CapEngine::getCapStatus($userId);

        // 1. Must be capped
        if ($capStatus['cap_status'] !== 'capped') {
            return ['ok' => false, 'error' => 'Your account is not currently capped.'];
        }

        // 2. Check window
        $cappedAt   = $capStatus['capped_at'];
        $windowDays = $capStatus['reactivation_window'];
        $fee        = $capStatus['reactivation_fee'];

        if ($cappedAt) {
            $deadline = date('Y-m-d H:i:s', strtotime($cappedAt . " +{$windowDays} days"));
            if (time() > strtotime($deadline)) {
                return ['ok' => false, 'error' => 'Your reactivation window has expired.'];
            }
        }

        // 3. Validate payment method
        $allowed = ['ewallet', 'gcash', 'maya', 'usdt', 'admin'];
        if (!in_array($paymentMethod, $allowed, true)) {
            return ['ok' => false, 'error' => 'Invalid payment method.'];
        }

        // 4. E-wallet balance check
        if ($paymentMethod === 'ewallet') {
            $balance = Ewallet::balance($userId);
            if ($balance < $fee) {
                return ['ok' => false, 'error' => 'Insufficient e-wallet balance.'];
            }
        }

        $pdo = db();
        $pdo->beginTransaction();

        try {
            // 5. Get current state
            $user = User::find($userId);
            if (!$user) {
                throw new RuntimeException('User not found.');
            }
            $packageId      = (int)$user['package_id'];
            $previousEarned = (float)$user['lifetime_earned'];

            // 6. Record reactivation
            $pdo->prepare("
                INSERT INTO reactivations
                  (user_id, amount_paid, previous_earned, package_id, payment_method, status)
                VALUES (?, ?, ?, ?, ?, 'completed')
            ")->execute([
                $userId,
                $fee,
                $previousEarned,
                $packageId,
                $paymentMethod,
            ]);
            $reactivationId = (int)$pdo->lastInsertId();

            // 7. Debit e-wallet if applicable
            if ($paymentMethod === 'ewallet' && $fee > 0) {
                $debitOk = Ewallet::debit(
                    $userId,
                    $fee,
                    $reactivationId,
                    'reactivation',
                    'Account reactivation fee'
                );
                if (!$debitOk) {
                    throw new RuntimeException('E-wallet debit failed during reactivation.');
                }
            }

            // 8. Reset cap state — fresh cycle
            $pdo->prepare("
                UPDATE users
                SET cap_status = 'active',
                    lifetime_earned = 0,
                    capped_at = NULL,
                    dfi_days_used = 0,
                    dfi_active = 1,
                    last_reactivation_at = NOW()
                WHERE id = ?
            ")->execute([$userId]);

            $pdo->commit();

            return [
                'ok'       => true,
                'message'  => 'Account reactivated successfully. Your new earning cycle has started.',
                'fee'      => $fee,
                'previous' => $previousEarned,
            ];

        } catch (\Exception $e) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'Reactivation failed: ' . $e->getMessage()];
        }
    }

    /**
     * Expire capped users who missed the reactivation window.
     * Delegates to CapEngine for the actual query.
     *
     * @return int Number of users expired
     */
    public static function expireOldCappedUsers(): int
    {
        return CapEngine::expireOldCappedUsers();
    }

    /**
     * Get reactivation history for a member.
     *
     * @param int $userId User ID
     * @return array List of reactivation records
     */
    public static function getReactivationHistory(int $userId): array
    {
        $st = db()->prepare("
            SELECT r.*, p.name AS package_name
            FROM reactivations r
            JOIN packages p ON p.id = r.package_id
            WHERE r.user_id = ?
            ORDER BY r.created_at DESC
        ");
        $st->execute([$userId]);
        return $st->fetchAll();
    }
}
