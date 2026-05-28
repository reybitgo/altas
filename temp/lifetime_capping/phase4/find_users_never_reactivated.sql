-- ============================================================
-- Find users that are NOT capped and have NEVER reactivated
-- ============================================================
-- 
-- Returns members who:
--   1. Are not currently capped (cap_status != 'capped')
--   2. Have never reactivated (no last_reactivation_at AND no reactivation records)
-- ============================================================

-- Option A: Simple check using last_reactivation_at only
SELECT
    u.id,
    u.username,
    u.full_name,
    u.cap_status,
    u.lifetime_earned,
    (p.entry_fee * p.lifetime_cap_multiplier) AS lifetime_cap,
    u.last_reactivation_at,
    u.joined_at
FROM users u
LEFT JOIN packages p ON p.id = u.package_id
WHERE u.role = 'member'
  AND u.cap_status != 'capped'
  AND u.last_reactivation_at IS NULL
ORDER BY u.joined_at DESC;

-- ============================================================

-- Option B: Strict check — also verify NO records in reactivations table
SELECT
    u.id,
    u.username,
    u.full_name,
    u.cap_status,
    u.lifetime_earned,
    (p.entry_fee * p.lifetime_cap_multiplier) AS lifetime_cap,
    u.last_reactivation_at,
    u.joined_at
FROM users u
LEFT JOIN packages p ON p.id = u.package_id
WHERE u.role = 'member'
  AND u.cap_status != 'capped'
  AND u.last_reactivation_at IS NULL
  AND NOT EXISTS (
      SELECT 1 FROM reactivations r WHERE r.user_id = u.id
  )
ORDER BY u.joined_at DESC;

-- ============================================================

-- Option C: Count how many have never reactivated (summary)
SELECT
    u.cap_status,
    COUNT(*) AS user_count,
    COALESCE(SUM(u.lifetime_earned), 0) AS total_earned,
    COALESCE(AVG(u.lifetime_earned), 0) AS avg_earned
FROM users u
WHERE u.role = 'member'
  AND u.cap_status != 'capped'
  AND u.last_reactivation_at IS NULL
GROUP BY u.cap_status;
