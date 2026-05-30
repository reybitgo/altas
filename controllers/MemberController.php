<?php

/**
 * @file   controllers/MemberController.php
 * @brief  Member controller for handling member-specific actions
 */
class MemberController
{
    public function dashboard(): void
    {
        Auth::guard('member');
        $user    = Auth::user();
        $summary = Commission::summary($user['id']);
        $status  = User::todayPairingStatus($user['id']);
        $recent  = Commission::recent($user['id'], 8);
        require 'views/member/dashboard.php';
    }

    public function profile(): void
    {
        Auth::guard('member');
        $user = Auth::user();
        require 'views/member/profile.php';
    }

    public function saveProfile(): void
    {
        Auth::guard('member');
        csrf_verify();
        $id   = Auth::id();
        $user = Auth::user();

        $data = [
            'full_name'    => trim($_POST['full_name']    ?? ''),
            'email'        => trim($_POST['email']        ?? ''),
            'mobile'       => trim($_POST['mobile']       ?? ''),
            'gcash_number' => trim($_POST['gcash_number'] ?? ''),
            'maya_number'  => trim($_POST['maya_number']  ?? ''),
            'usdt_address' => trim($_POST['usdt_address'] ?? ''),
            'address'      => trim($_POST['address']      ?? ''),
        ];

        // Handle photo upload
        if (!empty($_FILES['photo']['tmp_name'])) {
            $file = $_FILES['photo'];

            // Verify MIME type
            $mime    = mime_content_type($file['tmp_name']);
            $allowed = ['image/jpeg', 'image/png', 'image/webp'];
            if (!in_array($mime, $allowed)) {
                flash('error', 'Photo must be JPEG, PNG, or WebP.');
                redirect('/?page=profile');
            }
            if ($file['size'] > 5 * 1024 * 1024) { // 5 MB — phone photos can be large
                flash('error', 'Photo must be under 5MB.');
                redirect('/?page=profile');
            }

            // Use absolute path so it works regardless of PHP's working directory.
            // dirname(__DIR__) = the project root (parent of /controllers/)
            $uploadDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;

            // Create directory if missing
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $ext  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime];
            $name = 'photo_' . $id . '_' . time() . '.' . $ext;
            $dest = $uploadDir . $name;

            if (!move_uploaded_file($file['tmp_name'], $dest)) {
                flash('error', 'Failed to save photo. Check that the uploads/ folder exists and is writable.');
                redirect('/?page=profile');
            }

            // Delete old photo file if it exists
            if (!empty($user['photo'])) {
                $old = $uploadDir . $user['photo'];
                if (file_exists($old)) @unlink($old);
            }

            $data['photo'] = $name;
        }

        // Password change (optional)
        $newPw = $_POST['new_password'] ?? '';
        if ($newPw) {
            if (strlen($newPw) < 8) {
                flash('error', 'New password must be at least 8 characters.');
                redirect('/?page=profile');
            }
            if (!User::verifyPassword($id, $_POST['current_password'] ?? '')) {
                flash('error', 'Current password is incorrect.');
                redirect('/?page=profile');
            }
            if ($newPw !== ($_POST['new_password_confirm'] ?? '')) {
                flash('error', 'New passwords do not match.');
                redirect('/?page=profile');
            }
            User::updatePassword($id, $newPw);
        }

        User::updateProfile($id, $data);
        flash('success', 'Profile updated successfully.');
        redirect('/?page=profile');
    }

    public function earnings(): void
    {
        Auth::guard('member');
        $userId  = Auth::id();
        $type    = $_GET['type'] ?? '';
        $page    = max(1, (int)($_GET['pg'] ?? 1));
        $summary = Commission::summary($userId);
        $history = Commission::history($userId, $page, 20, $type);
        require 'views/member/earnings.php';
    }

    public function genealogy(): void
    {
        Auth::guard('member');
        $user     = Auth::user();
        $view     = $_GET['view'] ?? 'binary'; // 'binary' | 'referral'
        $indirect = [];
        $direct   = [];
        if ($view === 'referral') {
            if (setting('indirect_referral_enabled', '1') === '1') {
                $indirect = User::indirectReferralTree($user['id']);
            } else {
                $page   = max(1, (int)($_GET['pg'] ?? 1));
                $direct = User::directReferrals($user['id'], $page);
            }
        }
        require 'views/member/genealogy.php';
    }

    public function apiBinaryTree(): void
    {
        Auth::guard('member');
        $rootId = isset($_GET['root']) ? (int)$_GET['root'] : Auth::id();
        $depth  = min(4, max(1, (int)($_GET['depth'] ?? 3)));
        json_response(self::buildTreeNode($rootId, $depth));
    }

    private static function buildTreeNode(int $id, int $depth): array
    {
        $u = User::find($id);
        if (!$u) return [];

        $node = [
            'id'          => (int)$u['id'],
            'username'    => $u['username'],
            'full_name'   => $u['full_name'] ?: $u['username'],
            'status'      => $u['status'],
            'package'     => $u['package_name'] ?? '—',
            'joined'      => fmt_date($u['joined_at']),
            'left_count'  => (int)$u['left_count'],
            'right_count' => (int)$u['right_count'],
            'left'        => null,
            'right'       => null,
        ];

        if ($depth > 0) {
            $pdo = db();
            $st  = $pdo->prepare(
                "SELECT id FROM users WHERE binary_parent_id = ? AND binary_position = ?"
            );
            $st->execute([$id, 'left']);
            $lc = $st->fetchColumn();
            if ($lc) $node['left'] = self::buildTreeNode((int)$lc, $depth - 1);

            $st->execute([$id, 'right']);
            $rc = $st->fetchColumn();
            if ($rc) $node['right'] = self::buildTreeNode((int)$rc, $depth - 1);
        }

        return $node;
    }

    public function payout(): void
    {
        Auth::guard('member');
        $userId  = Auth::id();
        $user    = Auth::user();
        $history = Payout::forUser($userId);
        require 'views/member/payout.php';
    }

    public function requestPayout(): void
    {
        Auth::guard('member');
        csrf_verify();

        $amount   = (float)($_POST['amount']         ?? 0);
        $method   = trim($_POST['payout_method']     ?? 'gcash');
        $account  = trim($_POST['payout_account']    ?? '');
        $usdtRate = (float)($_POST['usdt_rate']      ?? 0);

        $allowed = ['gcash', 'maya', 'usdt'];
        if (!in_array($method, $allowed)) {
            flash('error', 'Invalid payout method.');
            redirect('/?page=payout');
        }

        if (!$account) {
            flash('error', 'Please enter your payout account details.');
            redirect('/?page=payout');
        }

        $result = Payout::request(Auth::id(), $amount, $method, $account, $usdtRate);
        if ($result['ok']) {
            flash('success', 'Payout request submitted. Admin will process it shortly.');
        } else {
            flash('error', $result['error']);
        }
        redirect('/?page=payout');
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  PHASE 3: DAILY FIXED INCOME (DFI)
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * JSON endpoint for DFI widget data.
     */
    public function apiDfiStatus(): void
    {
        Auth::guard('member');
        json_response(DailyFixedIncome::getMemberDFIStatus(Auth::id()));
    }

    /**
     * DFI payout history page.
     */
    public function dfiHistory(): void
    {
        Auth::guard('member');
        $userId  = Auth::id();
        $page    = max(1, (int)($_GET['pg'] ?? 1));
        $history = DailyFixedIncome::getDFIHistory($userId, $page);
        $status  = DailyFixedIncome::getMemberDFIStatus($userId);

        // Fetch all DFI records for calendar view (grouped by date)
        $calendarRaw = db()->prepare("
            SELECT DATE(created_at) AS day, amount, cap_status_at_payout
            FROM daily_fixed_income_log
            WHERE user_id = ?
            ORDER BY created_at ASC
        ");
        $calendarRaw->execute([$userId]);
        $calendarData = [];
        foreach ($calendarRaw->fetchAll() as $row) {
            $ym = date('Y-m', strtotime($row['day']));
            $d  = (int)date('j', strtotime($row['day']));
            $calendarData[$ym][$d] = [
                'amount' => (float)$row['amount'],
                'cap_status' => $row['cap_status_at_payout'],
            ];
        }

        require 'views/member/dfi_history.php';
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  PHASE 3/5: CAP STATUS MONITORING
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Cap status detail page.
     */
    public function capStatus(): void
    {
        Auth::guard('member');
        $userId    = Auth::id();
        $capStatus = User::getCapStatus($userId);
        $summary   = Commission::summary($userId);
        $user      = User::find($userId);
        require 'views/member/cap_status.php';
    }

    /**
     * JSON endpoint for cap widget data.
     */
    public function apiCapStatus(): void
    {
        Auth::guard('member');
        $userId    = Auth::id();
        $capStatus = User::getCapStatus($userId);
        $summary   = Commission::summary($userId);

        json_response([
            'lifetime_earned'  => $capStatus['lifetime_earned'],
            'lifetime_cap'     => $capStatus['lifetime_cap'],
            'remaining'        => $capStatus['remaining'],
            'cap_status'       => $capStatus['cap_status'],
            'capped_at'        => $capStatus['capped_at'],
            'total_pairing'    => (float)$summary['total_pairing'],
            'total_direct'     => (float)$summary['total_direct'],
            'total_indirect'   => (float)$summary['total_indirect'],
            'total_cap_blocked'=> (float)$summary['total_cap_blocked'],
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  PHASE 4: REACTIVATION (stubs — full UI in Phase 4)
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Reactivation page.
     */
    public function reactivate(): void
    {
        Auth::guard('member');
        $userId  = Auth::id();
        $request = Reactivation::requestReactivation($userId);

        if (!$request['ok']) {
            flash('error', $request['error']);
            redirect('/?page=dashboard');
        }

        $capStatus = User::getCapStatus($userId);

        // Fetch admin payment details for external payment display (from settings)
        $admin = [];
        foreach (['gcash_number','maya_number','usdt_address'] as $k) {
            $admin[$k] = db()->query("SELECT value FROM settings WHERE key_name='{$k}'")->fetchColumn() ?: '';
        }

        require 'views/member/reactivate.php';
    }

    /**
     * Process reactivation request.
     */
    public function doReactivate(): void
    {
        Auth::guard('member');
        csrf_verify();

        $userId        = Auth::id();
        $paymentMethod = trim($_POST['payment_method'] ?? 'ewallet');
        $proofPath     = '';

        // Handle proof image upload for external payments
        if ($paymentMethod !== 'ewallet' && !empty($_FILES['proof_image']['tmp_name'])) {
            $file = $_FILES['proof_image'];
            $mime = mime_content_type($file['tmp_name']);
            $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

            if (!in_array($mime, $allowed)) {
                flash('error', 'Proof must be an image (JPEG, PNG, GIF, WebP).');
                redirect('/?page=reactivate');
            }
            if ($file['size'] > 5 * 1024 * 1024) {
                flash('error', 'Image must be under 5MB.');
                redirect('/?page=reactivate');
            }

            $uploadDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'reactivation_proofs' . DIRECTORY_SEPARATOR;
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $ext  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'][$mime];
            $name = 'reactivation_' . $userId . '_' . time() . '.' . $ext;
            $dest = $uploadDir . $name;

            if (!move_uploaded_file($file['tmp_name'], $dest)) {
                flash('error', 'Failed to save proof image. Check uploads folder permissions.');
                redirect('/?page=reactivate');
            }

            $proofPath = 'reactivation_proofs/' . $name;
        }

        $result = Reactivation::processReactivation($userId, $paymentMethod, $proofPath);

        if ($result['ok']) {
            if (!empty($result['pending'])) {
                flash('info', $result['message']);
            } else {
                flash('success', $result['message']);
            }
        } else {
            flash('error', $result['error'] ?? 'Reactivation failed.');
        }

        redirect('/?page=dashboard');
    }

    // ── E-Wallet Transfer ──────────────────────────────────────────────────

    public function ewalletTransfer(): void
    {
        Auth::check() or redirect('/?page=login');
        $user = Auth::user();
        $fee = Auth::isAdmin() ? 0.00 : (float) setting('ewallet_transfer_fee', '0.00');
        $min = (float) setting('ewallet_min_transfer', '50.00');
        $dailyLimit  = (float) setting('ewallet_transfer_daily_limit', '5000.00');
        $weeklyLimit = (float) setting('ewallet_transfer_weekly_limit', '20000.00');

        $pdo = db();
        $recent = $pdo->prepare("
            SELECT t.*, su.username AS sender_username, ru.username AS recipient_username
            FROM ewallet_transfers t
            JOIN users su ON su.id = t.sender_id
            JOIN users ru ON ru.id = t.recipient_id
            WHERE t.sender_id = ? OR t.recipient_id = ?
            ORDER BY t.created_at DESC
            LIMIT 20
        ");
        $recent->execute([$user['id'], $user['id']]);

        require 'views/member/ewallet_transfer.php';
    }

    public function doEwalletTransfer(): void
    {
        Auth::check() or redirect('/?page=login');
        csrf_verify();

        $senderId = Auth::id();
        $recipientUsername = trim($_POST['recipient'] ?? '');
        $amount = (float) ($_POST['amount'] ?? 0);
        $note = trim($_POST['note'] ?? '');
        $password = $_POST['password'] ?? '';

        // Password confirmation
        if (!User::verifyPassword($senderId, $password)) {
            flash('error', 'Password confirmation is incorrect.');
            redirect('/?page=ewallet_transfer');
            return;
        }

        $recipient = User::findByUsername($recipientUsername);
        if (!$recipient) {
            flash('error', 'Recipient not found.');
            redirect('/?page=ewallet_transfer');
            return;
        }

        if ($recipient['id'] === $senderId) {
            flash('error', 'You cannot transfer to yourself.');
            redirect('/?page=ewallet_transfer');
            return;
        }

        $result = Ewallet::transfer($senderId, $recipient['id'], $amount, $note);

        if ($result['ok']) {
            flash('success', 'Transfer completed successfully.');
        } else {
            flash('error', $result['error']);
        }
        redirect('/?page=ewallet_transfer');
    }
}
