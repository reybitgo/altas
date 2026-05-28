-- ============================================================
-- Find members who are capped or active
-- ============================================================
-- Useful for Phase 4 QA: quickly identify test candidates for
-- reactivation (capped) or verify unaffected members (active).
-- ============================================================

-- All capped or active members
SELECT
    u.id,
    u.username,
    u.full_name,
    u.cap_status,
    u.lifetime_earned,
    (p.entry_fee * p.lifetime_cap_multiplier) AS lifetime_cap,
    u.dfi_days_used,
    u.dfi_active,
    p.daily_fixed_income,
    p.daily_fixed_income_days,
    p.reactivation_fee,
    p.reactivation_window_days,
    u.capped_at,
    u.last_reactivation_at,
    u.ewallet_balance,
    u.joined_at
FROM users u
LEFT JOIN packages p ON p.id = u.package_id
WHERE u.role = 'member'
  AND u.cap_status IN ('active', 'capped')
ORDER BY u.cap_status DESC, u.lifetime_earned DESC;

-- ============================================================

-- Only capped members (reactivation candidates)
SELECT
    u.id,
    u.username,
    u.full_name,
    u.lifetime_earned,
    (p.entry_fee * p.lifetime_cap_multiplier) AS lifetime_cap,
    p.reactivation_fee,
    p.reactivation_window_days,
    u.capped_at,
    u.ewallet_balance
FROM users u
LEFT JOIN packages p ON p.id = u.package_id
WHERE u.role = 'member'
  AND u.cap_status = 'capped'
ORDER BY u.capped_at DESC;

-- ============================================================

-- Only active members (should NOT be affected by reactivation)
SELECT
    u.id,
    u.username,
    u.full_name,
    u.lifetime_earned,
    (p.entry_fee * p.lifetime_cap_multiplier) AS lifetime_cap,
    u.dfi_days_used,
    u.dfi_active
FROM users u
LEFT JOIN packages p ON p.id = u.package_id
WHERE u.role = 'member'
  AND u.cap_status = 'active'
ORDER BY u.lifetime_earned DESC;
