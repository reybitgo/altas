-- ============================================================
-- Phase 4 QA Helper: Force E-Wallet Credit for Reactivation Testing
-- ============================================================
-- 
-- Use this to add e-wallet balance to a member so they can
-- test the reactivation flow (Test 3 in qa_test_phase4.md).
--
-- Typical reactivation fee for Starter package = ₱10,000.00
-- Adding ₱15,000 gives enough headroom for testing.

-- Option A: Quick balance update (sufficient for testing)
UPDATE users 
SET ewallet_balance = ewallet_balance + 15000.00 
WHERE id = 6;

-- Option B: Full credit with ledger audit trail (cleaner)
-- UPDATE users 
-- SET ewallet_balance = ewallet_balance + 15000.00 
-- WHERE id = 6;
-- 
-- INSERT INTO ewallet_ledger 
--   (user_id, type, amount, reference_id, ref_type, balance_after, note, created_at)
-- VALUES 
--   (6, 'credit', 15000.00, 0, 'commission', 
--    (SELECT ewallet_balance FROM users WHERE id = 6), 
--    'Test credit for reactivation QA', NOW());

-- ============================================================
-- Verification queries
-- ============================================================

-- Check member balance and cap status
-- SELECT username, ewallet_balance, cap_status, lifetime_earned 
-- FROM users WHERE id = 6;

-- Check reactivation fee for this member's package
-- SELECT u.username, p.reactivation_fee 
-- FROM users u 
-- JOIN packages p ON p.id = u.package_id 
-- WHERE u.id = 6;
