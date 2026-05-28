-- ============================================================
-- Phase 4 QA Helper: Verify Reactivation State for a User
-- ============================================================
-- 
-- Run this after reactivation (Test 3) to confirm ALL expected
-- state changes in one query.
-- 
-- Usage: Replace @user_id := 6 with the target user ID
-- ============================================================

SET @user_id := 6;

SELECT 
    u.id,
    u.username,
    u.cap_status,
    u.lifetime_earned,
    u.dfi_days_used,
    u.dfi_active,
    u.last_reactivation_at,
    u.ewallet_balance,
    -- Reactivation record
    r.id AS reactivation_id,
    r.amount_paid AS reactivation_fee,
    r.payment_method,
    r.previous_earned,
    r.status AS reactivation_status,
    r.created_at AS reactivated_at,
    p.name AS package_name,
    -- E-wallet ledger verification
    (
        SELECT COUNT(*) 
        FROM ewallet_ledger el 
        WHERE el.user_id = u.id 
          AND el.type = 'debit' 
          AND el.note LIKE '%reactivation%'
    ) AS reactivation_ledger_entries,
    (
        SELECT COALESCE(SUM(el.amount), 0)
        FROM ewallet_ledger el 
        WHERE el.user_id = u.id 
          AND el.type = 'debit' 
          AND el.note LIKE '%reactivation%'
    ) AS total_debited_for_reactivation
FROM users u
LEFT JOIN reactivations r ON r.user_id = u.id
LEFT JOIN packages p ON p.id = r.package_id
WHERE u.id = @user_id
ORDER BY r.created_at DESC
LIMIT 1;

-- ============================================================
-- Expected Results After Successful Reactivation
-- ============================================================
-- 
-- | cap_status          | 'active'                      |
-- | lifetime_earned     | 0.00                          |
-- | dfi_days_used       | 0                             |
-- | dfi_active          | 1                             |
-- | last_reactivation_at| NOT NULL (recent timestamp)   |
-- | reactivation_id     | NOT NULL (new record)         |
-- | reactivation_fee    | > 0 (e.g. 10000.00)           |
-- | reactivation_status | 'completed'                   |
-- | reactivation_ledger_entries | >= 1              |
-- | total_debited_for_reactivation | >= fee amount  |
--
-- If any of these are wrong, the reactivation did not complete.
-- ============================================================
