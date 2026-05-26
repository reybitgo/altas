<?php
/**
 * @file   core/Reactivation.php
 * @brief  Reactivation Service — STUB for Phase 1 QA
 * 
 * Phase 4 will implement full reactivation logic.
 * This stub prevents index.php from crashing during Phase 1 testing.
 */
class Reactivation
{
    /**
     * Request reactivation (member-initiated).
     * STUB: Returns error (not available in Phase 1).
     */
    public static function requestReactivation(int $userId): array
    {
        return ['ok' => false, 'error' => 'Reactivation not yet available.'];
    }

    /**
     * Process a reactivation payment.
     * STUB: No-op in Phase 1.
     */
    public static function processReactivation(int $userId, string $paymentMethod): array
    {
        return ['ok' => false, 'error' => 'Reactivation not yet available.'];
    }

    /**
     * Expire capped users who missed the reactivation window.
     * STUB: Returns 0 (no expiration in Phase 1).
     */
    public static function expireOldCappedUsers(): int
    {
        return 0;
    }

    /**
     * Get reactivation history for a member.
     * STUB: Returns empty array.
     */
    public static function getReactivationHistory(int $userId): array
    {
        return [];
    }
}
