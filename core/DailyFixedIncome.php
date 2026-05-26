<?php
/**
 * @file   core/DailyFixedIncome.php
 * @brief  Daily Fixed Income Engine — STUB for Phase 1 QA
 * 
 * Phase 3 will implement full DFI processing logic.
 * This stub prevents index.php from crashing during Phase 1 testing.
 */
class DailyFixedIncome
{
    /**
     * Process daily DFI payout for all eligible members.
     * STUB: Returns empty stats (no DFI paid in Phase 1).
     */
    public static function processDailyPayout(): array
    {
        return [
            'processed' => 0,
            'paid'      => 0.00,
            'skipped'   => 0,
        ];
    }

    /**
     * Get DFI status for a member.
     * STUB: Returns disabled state.
     */
    public static function getMemberDFIStatus(int $userId): array
    {
        return [
            'total_dfi_earned' => 0.00,
            'days_used'        => 0,
            'days_remaining'   => 0,
            'daily_rate'       => 0.00,
            'next_payout_date' => null,
            'status'           => 'disabled',
        ];
    }

    /**
     * Get paginated DFI history for a member.
     * STUB: Returns empty result.
     */
    public static function getDFIHistory(int $userId, int $page = 1): array
    {
        return [
            'data'        => [],
            'total'       => 0,
            'page'        => 1,
            'per_page'    => 20,
            'total_pages' => 1,
        ];
    }
}
