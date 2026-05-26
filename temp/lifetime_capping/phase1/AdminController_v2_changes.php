<?php

/**
 * @file   controllers/AdminController.php
 * @brief  Admin controller with v2 capping + DFI support
 */
class AdminController
{
    // ── Existing methods unchanged ─────────────────────────────────────────
    // dashboard(), users(), viewUser(), toggleUser(), updateUsdtGas(),
    // codes(), generateCodes(), exportCodes(), payouts(), payoutAction(),
    // settings(), saveSettings(), manualReset() — all remain as-is

    // ... (existing methods preserved) ...

    // ── Packages (v2 Updated) ────────────────────────────────────────────

    public function packages(): void
    {
        Auth::guard('admin');
        $packages = Package::all();
        $editPkg  = null;
        if (isset($_GET['edit'])) {
            $editPkg = Package::withLevels((int)$_GET['edit']);
        }
        require 'views/admin/packages.php';
    }

    public function savePackage(): void
    {
        Auth::guard('admin');
        csrf_verify();

        $id   = (int)($_POST['package_id'] ?? 0);
        $data = [
            'name'             => trim($_POST['name']             ?? ''),
            'entry_fee'        => (float)($_POST['entry_fee']      ?? 0),
            'pairing_bonus'    => (float)($_POST['pairing_bonus']  ?? 0),
            'daily_pair_cap'   => (int)($_POST['daily_pair_cap']   ?? 3),
            'direct_ref_bonus' => (float)($_POST['direct_ref_bonus'] ?? 0),
            'status'           => $_POST['status'] ?? 'active',
            'indirect_levels'  => [],
            // NEW v2 fields
            'lifetime_cap_multiplier'  => (float)($_POST['lifetime_cap_multiplier']  ?? 3.00),
            'reactivation_fee'         => (float)($_POST['reactivation_fee']         ?? 0),
            'reactivation_window_days' => (int)($_POST['reactivation_window_days']    ?? 15),
            'daily_fixed_income'       => (float)($_POST['daily_fixed_income']       ?? 0),
            'daily_fixed_income_days'  => (int)($_POST['daily_fixed_income_days']    ?? 90),
        ];

        for ($lvl = 1; $lvl <= 10; $lvl++) {
            $data['indirect_levels'][$lvl] = (float)($_POST["indirect_{$lvl}"] ?? 0);
        }

        if (!$data['name'] || $data['entry_fee'] <= 0) {
            flash('error', 'Package name and entry fee are required.');
            redirect('/?page=admin_packages');
        }

        // ── NEW v2 Validation ──────────────────────────────────────────────
        // Robust float comparison: cast to string with 2 decimals, then compare as strings
        $capMultStr = number_format($data['lifetime_cap_multiplier'], 2, '.', '');
        if ($capMultStr < '1.00') {
            flash('error', 'Lifetime cap multiplier must be at least 1.0.');
            redirect('/?page=admin_packages');
        }
        if ($data['reactivation_window_days'] < 1) {
            flash('error', 'Reactivation window must be at least 1 day.');
            redirect('/?page=admin_packages');
        }
        $dfiStr = number_format($data['daily_fixed_income'], 2, '.', '');
        if ($dfiStr < '0.00') {
            flash('error', 'Daily fixed income cannot be negative.');
            redirect('/?page=admin_packages');
        }
        if ($data['daily_fixed_income_days'] < 1) {
            flash('error', 'Max DFI days must be at least 1.');
            redirect('/?page=admin_packages');
        }
        // ── End v2 Validation ─────────────────────────────────────────────

        Package::save($data, $id ?: null);
        flash('success', $id ? 'Package updated with v2 settings.' : 'Package created with v2 settings.');
        redirect('/?page=admin_packages');
    }

    // ── v2 Admin Monitoring Pages (Phase 6 placeholders) ───────────────────

    public function capMonitor(): void
    {
        Auth::guard('admin');
        $pdo = db();

        $stats = [
            'active'    => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='member' AND cap_status='active'")->fetchColumn(),
            'capped'    => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='member' AND cap_status='capped'")->fetchColumn(),
            'perminact' => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='member' AND cap_status='perminact'")->fetchColumn(),
        ];

        $page   = max(1, (int)($_GET['pg'] ?? 1));
        $status = $_GET['status'] ?? '';

        $where = "u.role='member'";
        $params = [];
        if ($status && in_array($status, ['active', 'capped', 'perminact'])) {
            $where .= " AND u.cap_status = ?";
            $params[] = $status;
        }

        $result = paginate(
            "SELECT u.*, p.name AS package_name, p.entry_fee, p.lifetime_cap_multiplier,
                    (p.entry_fee * p.lifetime_cap_multiplier) AS lifetime_cap
             FROM users u
             LEFT JOIN packages p ON p.id = u.package_id
             WHERE {$where}
             ORDER BY u.cap_status DESC, u.lifetime_earned DESC",
            $params,
            $page,
            25
        );

        require 'views/admin/cap_monitor.php';
    }

    public function dfiAdmin(): void
    {
        Auth::guard('admin');
        $pdo = db();

        $todayDfi = (float)$pdo->query("
            SELECT COALESCE(SUM(amount), 0) 
            FROM daily_fixed_income_log 
            WHERE DATE(created_at) = CURDATE()
        ")->fetchColumn();

        $totalDfi = (float)$pdo->query("
            SELECT COALESCE(SUM(amount), 0) 
            FROM daily_fixed_income_log
        ")->fetchColumn();

        $totalMembers = (int)$pdo->query("
            SELECT COUNT(DISTINCT user_id) 
            FROM daily_fixed_income_log
        ")->fetchColumn();

        require 'views/admin/dfi_admin.php';
    }

    public function reactivations(): void
    {
        Auth::guard('admin');
        $page = max(1, (int)($_GET['pg'] ?? 1));

        $result = paginate(
            "SELECT r.*, u.username, u.full_name, p.name AS package_name
             FROM reactivations r
             JOIN users u ON u.id = r.user_id
             JOIN packages p ON p.id = r.package_id
             ORDER BY r.created_at DESC",
            [],
            $page,
            25
        );

        $totalRevenue = (float)db()->query("SELECT COALESCE(SUM(amount_paid), 0) FROM reactivations WHERE status='completed'")->fetchColumn();

        require 'views/admin/reactivations.php';
    }
}
