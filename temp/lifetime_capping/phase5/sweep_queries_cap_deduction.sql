-- ============================================================
-- Phase 5: Cap Deduction Sweep Queries
-- ============================================================
-- Use these queries to find commission records where the lifetime
-- cap blocked part or all of a payout. Helpful for QA testing the
-- "Cap Impact" column on the Earnings page.

-- ------------------------------------------------------------
-- 1. ALL RECORDS WITH CAP DEDUCTIONS
-- ------------------------------------------------------------
-- Every commission row where cap_deduction > 0, newest first.

SELECT 
    c.id,
    u.username,
    c.type,
    c.amount,
    c.cap_deduction,
    c.status,
    c.created_at
FROM commissions c
JOIN users u ON u.id = c.user_id
WHERE c.cap_deduction > 0
ORDER BY c.created_at DESC;


-- ------------------------------------------------------------
-- 2. MEMBERS WHO HAVE BEEN CAPPED (Distinct Users)
-- ------------------------------------------------------------
-- One row per member with blocked totals.

SELECT 
    u.id,
    u.username,
    u.cap_status,
    COUNT(c.id) AS blocked_commissions,
    SUM(c.cap_deduction) AS total_blocked,
    MAX(c.created_at) AS last_blocked_at
FROM commissions c
JOIN users u ON u.id = c.user_id
WHERE c.cap_deduction > 0
GROUP BY u.id, u.username, u.cap_status
ORDER BY total_blocked DESC;


-- ------------------------------------------------------------
-- 3. SUMMARY BY COMMISSION TYPE
-- ------------------------------------------------------------
-- Aggregates across all users grouped by commission type.

SELECT 
    c.type,
    COUNT(*) AS records,
    SUM(c.amount) AS total_credited,
    SUM(c.cap_deduction) AS total_blocked,
    ROUND(AVG(c.cap_deduction), 2) AS avg_blocked
FROM commissions c
WHERE c.cap_deduction > 0
GROUP BY c.type
ORDER BY total_blocked DESC;


-- ------------------------------------------------------------
-- 4. QUICK HEALTH CHECK — Any Cap Deductions Exist?
-- ------------------------------------------------------------
-- Returns a single-row summary.

SELECT 
    COUNT(*) AS records_with_cap_deduction,
    SUM(cap_deduction) AS total_blocked_amount,
    COUNT(DISTINCT user_id) AS members_affected
FROM commissions
WHERE cap_deduction > 0;


-- ------------------------------------------------------------
-- 5. RECENTLY CAPPED MEMBERS (Good for QA Testing)
-- ------------------------------------------------------------
-- Members who had a cap block in the last 7 days.

SELECT 
    u.id,
    u.username,
    u.cap_status,
    u.lifetime_earned,
    (p.entry_fee * p.lifetime_cap_multiplier) AS lifetime_cap,
    c.amount AS last_credited_amount,
    c.cap_deduction AS last_blocked_amount,
    c.created_at
FROM users u
JOIN packages p ON p.id = u.package_id
JOIN commissions c ON c.user_id = u.id
WHERE c.cap_deduction > 0
  AND c.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
ORDER BY c.created_at DESC;


-- ------------------------------------------------------------
-- 6. FIND A SPECIFIC MEMBER'S LATEST BLOCKED COMMISSION
-- ------------------------------------------------------------
-- Replace [member_id] with the actual user ID.

SELECT id, type, amount, cap_deduction, status, created_at
FROM commissions
WHERE user_id = [member_id] AND cap_deduction > 0
ORDER BY created_at DESC
LIMIT 1;
