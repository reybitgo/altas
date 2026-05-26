<?php
/**
 * @file   core/CapEngine.php
 * @brief  Lifetime Income Capping Engine — STUB for Phase 1 QA
 * 
 * Phase 2 will implement full cap checking logic.
 * This stub prevents index.php from crashing during Phase 1 testing.
 */
class CapEngine
{
    /**
     * Check if a user can earn a given amount under their lifetime cap.
     * STUB: Always allows full amount (no cap enforcement in Phase 1).
     */
    public static function canEarn(int $userId, float $amount): array
    {
        return [
            'allowed' => $amount,
            'blocked' => 0.00,
            'status'  => 'active',
        ];
    }

    /**
     * Record an earning against the lifetime cap.
     * STUB: No-op in Phase 1.
     */
    public static function recordEarning(int $userId, float $amount, string $type): void
    {
        // Phase 2: Update lifetime_earned, check cap, trigger cap_status change
    }

    /**
     * Get full cap status for a user.
     * STUB: Returns zeroed/defaults.
     */
    public static function getCapStatus(int $userId): array
    {
        return [
            'earned'      => 0.00,
            'cap'         => 0.00,
            'remaining'   => 0.00,
            'status'      => 'active',
            'dfi_days_used' => 0,
            'dfi_active'    => 1,
        ];
    }

    /**
     * Check if user is active for binary pair counting.
     * STUB: Always returns true (no skipping in Phase 1).
     */
    public static function isActiveForPairs(int $userId): bool
    {
        return true;
    }

    /**
     * Apply cap when limit is reached.
     * STUB: No-op in Phase 1.
     */
    public static function applyCap(int $userId): void
    {
        // Phase 2: Set cap_status='capped', capped_at=NOW()
    }
}
