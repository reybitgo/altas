# Referral Link Registration Plan

> **Goal:** Allow users to register via a referral link (e.g., `?page=register&sponsor=test1`) without requiring a registration code or e-wallet payment. Their position is secured immediately, but they start as **inactive/pending** and must activate later via code or e-wallet.
>
> **Current Bug (noted):** `SQLSTATE[01000]: Warning: 1265 Data truncated for column 'ref_type'` — this occurs when `Ewallet::credit()` or `Ewallet::debit()` is called with a `ref_type` value not in the `enum('commission','payout','reactivation','transfer','topup','registration')`. The fix is included in this plan.

---

## Current State Analysis

| Finding | Detail |
|---------|--------|
| `users.status` enum | `('active','suspended','pending')` — `pending` already exists but is **never used** |
| `processBinaryPlacement` | Already checks `u.status = 'active'` before paying pairing bonuses ✅ |
| `processDirectReferral` | Does **NOT** check `status` — pays even if sponsor is `pending` ❌ |
| `processIndirectReferral` | Does **NOT** check `status` — pays even if upline is `pending` ❌ |
| `DailyFixedIncome::processDailyPayout` | Checks `cap_status` and `dfi_active`, but **NOT** `status` ❌ |
| `User::register()` | Always inserts `status = 'active'` — hardcoded |
| `AuthController::doRegister()` | Requires either a valid code **or** e-wallet balance — no "free" path |

---

## Architecture Overview

```
Referral Link: ?page=register&sponsor=test1&ref=1
        │
        ▼
┌─────────────────────────────────────┐
│  AuthController::showRegister()     │
│  Detects ?ref=1 or ?sponsor=        │
│  → Enables "position-only" mode     │
│  → Auto-fills sponsor (readonly)    │
│  → Auto-finds upline + position     │
│  → Hides code/ewallet/payment UI    │
└─────────────────────────────────────┘
        │
        ▼
┌─────────────────────────────────────┐
│  AuthController::doRegister()       │
│  Detects position-only mode         │
│  → Skips code validation            │
│  → Skips e-wallet debit             │
│  → Sets status = 'pending'          │
│  → Sets package_id = NULL (or 0)   │
│  → Still places in binary tree      │
└─────────────────────────────────────┘
        │
        ▼
┌─────────────────────────────────────┐
│  Commission Engine                  │
│  → Pairing: blocked by status check │
│  → Direct: blocked by status check  │
│  → Indirect: blocked by status check│
│  → DFI: blocked by status check     │
└─────────────────────────────────────┘
        │
        ▼
┌─────────────────────────────────────┐
│  Activation Page (?page=activate)   │
│  Code path: enter code → activate   │
│  E-wallet path: select pkg → debit  │
│  → Sets status = 'active'           │
│  → Sets package_id                  │
│  → No retroactive earnings          │
└─────────────────────────────────────┘
```

---

## 1. Commission Engine — Block Pending Users from Earning

### 1.1 `processDirectReferral()` — Add status check

**File:** `core/Commission.php` (line 122)

Add before the `Package::find()` call:
```php
// Skip if sponsor is not active (e.g., pending activation)
$sponsorStatus = db()->query("SELECT status FROM users WHERE id = {$sponsorId}")->fetchColumn();
if ($sponsorStatus !== 'active') {
    return;
}
```

> **Rationale:** Pending users should not earn direct referral bonuses. Their downlines are placed in the tree, but the sponsor earns nothing until they activate.

### 1.2 `processIndirectReferral()` — Add status check

**File:** `core/Commission.php` (line 163)

Inside the `for` loop, before processing each level:
```php
// Skip if this upline is not active
$uplineStatus = $pdo->query("SELECT status FROM users WHERE id = {$cur}")->fetchColumn();
if ($uplineStatus !== 'active') {
    // Move up but do NOT pay this level
    $row = $pdo->prepare('SELECT sponsor_id FROM users WHERE id = ?');
    // ... rest of climb logic
    continue;
}
```

> **Rationale:** Pending users in the sponsor chain should not earn unilevel bonuses either.

### 1.3 `DailyFixedIncome::processDailyPayout()` — Add status check

**File:** `core/DailyFixedIncome.php` (line 51)

Add `AND u.status = 'active'` to the SQL query:
```sql
WHERE u.role = 'member'
  AND u.status = 'active'
  AND u.cap_status = 'active'
  AND u.dfi_active = 1
```

> **Rationale:** Pending users should not receive DFI payouts.

### 1.4 `processBinaryPlacement()` — Already blocked ✅

Line 56 already has `WHERE u.id = ? AND u.status = 'active'`. Pending users won't earn pairing bonuses. No change needed.

---

## 2. Registration Flow — Position-Only Mode

### 2.1 URL Detection Logic

**File:** `controllers/AuthController.php` — `showRegister()`

```php
public function showRegister(): void
{
    // ── Seat limit check ──
    if (isSeatLimitReached()) {
        http_response_code(403);
        require 'views/auth/register_closed.php';
        return;
    }

    $prefillSponsor = trim($_GET['sponsor'] ?? '');
    $isReferralMode = isset($_GET['ref']) && $_GET['ref'] === '1';
    $packages       = Package::all(true);

    // Can the logged-in user afford e-wallet registration?
    $canUseEwallet = false;
    if (Auth::check() && !empty($packages)) {
        $minFee = min(array_map(fn($p) => (float)$p['entry_fee'], $packages));
        $canUseEwallet = Ewallet::balance(Auth::id()) >= $minFee;
    }

    // Auto-find upline + position for referral mode
    $prefillUpline = '';
    $prefillPosition = 'left';
    if ($isReferralMode && $prefillSponsor) {
        $sponsorUser = User::findByUsername($prefillSponsor);
        if ($sponsorUser) {
            $auto = self::findNextBinarySlot((int)$sponsorUser['id']);
            if ($auto) {
                $prefillUpline = $auto['upline_username'];
                $prefillPosition = $auto['position'];
            }
        }
    }

    require 'views/auth/register.php';
}
```

### 2.2 Auto-Slot Finder Helper

**File:** `controllers/AuthController.php`

```php
/**
 * Find the next available binary slot starting from a given user's tree.
 * Tries left first, then right, breadth-first.
 */
private static function findNextBinarySlot(int $sponsorId): ?array
{
    $pdo = db();
    $queue = [$sponsorId];
    $visited = [];

    while (!empty($queue)) {
        $cur = array_shift($queue);
        if (isset($visited[$cur])) continue;
        $visited[$cur] = true;

        // Check left slot
        $left = $pdo->query("SELECT id FROM users WHERE binary_parent_id = {$cur} AND binary_position = 'left' LIMIT 1")
            ->fetchColumn();
        if (!$left) {
            $upline = $pdo->query("SELECT username FROM users WHERE id = {$cur}")->fetchColumn();
            return ['upline_id' => $cur, 'upline_username' => $upline, 'position' => 'left'];
        }
        $queue[] = $left;

        // Check right slot
        $right = $pdo->query("SELECT id FROM users WHERE binary_parent_id = {$cur} AND binary_position = 'right' LIMIT 1")
            ->fetchColumn();
        if (!$right) {
            $upline = $pdo->query("SELECT username FROM users WHERE id = {$cur}")->fetchColumn();
            return ['upline_id' => $cur, 'upline_username' => $upline, 'position' => 'right'];
        }
        $queue[] = $right;
    }

    return null; // Tree is completely full
}
```

### 2.3 `doRegister()` — Handle Position-Only Mode

**File:** `controllers/AuthController.php` — `doRegister()`

Add after the existing seat limit check:

```php
$isReferralMode = isset($_POST['referral_mode']) && $_POST['referral_mode'] === '1';

if ($isReferralMode) {
    // ── Referral link registration (no code, no e-wallet) ──
    $packageId   = 0; // No package yet
    $regCodeId   = null;
    $paymentMethod = 'referral_link';
    $regPaidBy   = null;
} else {
    // ── Existing code/ewallet logic ──
    $paymentMethod = $_POST['payment_method'] ?? 'code';
    // ... existing code validation or e-wallet debit logic ...
}
```

Then in the `User::register()` call, pass `status`:
```php
$newId = User::register([
    'username'         => $username,
    'password'         => $password,
    'package_id'       => $packageId > 0 ? $packageId : null,
    'reg_code_id'      => $regCodeId,
    'reg_payment_method' => $paymentMethod,
    'reg_paid_by'      => $regPaidBy,
    'sponsor_id'       => $sponsor['id'],
    'binary_parent_id' => $upline['id'],
    'binary_position'  => $position,
    'status'           => $isReferralMode ? 'pending' : 'active',
]);
```

### 2.4 `User::register()` — Accept Optional Status

**File:** `models/User.php` — `register()`

Change the INSERT to use the passed status (default to 'active'):
```php
$status = $data['status'] ?? 'active';

$pdo->prepare("
    INSERT INTO users
      (username, password_hash, package_id, reg_code_id,
       reg_payment_method, reg_paid_by,
       sponsor_id, binary_parent_id, binary_position, status)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
")->execute([
    $data['username'],
    $hash,
    $data['package_id'] ?? null,
    $data['reg_code_id'] ?? null,
    $paymentMethod,
    $data['reg_paid_by'] ?? null,
    $data['sponsor_id'],
    $data['binary_parent_id'],
    $data['binary_position'],
    $status,
]);
```

> **Important:** When `status = 'pending'`, `package_id` is NULL. The binary placement still happens (they get a seat), but no commissions fire because of the status checks in the Commission engine.

---

## 3. Registration View — Referral Mode UI

### 3.1 Auto-Fill Sponsor (Readonly)

Already implemented from previous work. When `$isReferralMode` is true, the sponsor field should be readonly and display:
```html
<input type="text" id="sponsor_username" name="sponsor_username"
  class="form-control" value="<?= e($prefillSponsor) ?>" readonly>
<div class="form-text text-info">📌 Sponsor locked via referral link.</div>
```

### 3.2 Auto-Fill Upline + Position (Editable)

When `$isReferralMode` is true:
```html
<div class="mb-3">
  <label class="form-label">Binary Upline Username <span class="text-danger">*</span></label>
  <input type="text" id="upline_username" name="upline_username"
    class="form-control" value="<?= e($prefillUpline) ?>" autocomplete="off" required>
  <div class="form-text" id="uplineHint">Auto-filled — you may change this.</div>
</div>

<div class="mb-3">
  <label class="form-label">Binary Position <span class="text-danger">*</span></label>
  <select name="binary_position" class="form-select">
    <option value="left" <?= $prefillPosition === 'left' ? 'selected' : '' ?>>↙ Left</option>
    <option value="right" <?= $prefillPosition === 'right' ? 'selected' : '' ?>>↘ Right</option>
  </select>
  <div class="form-text">Auto-filled — you may change this.</div>
</div>
```

### 3.3 Hide Payment UI in Referral Mode

When `$isReferralMode` is true:
- Hide the entire payment method toggle
- Hide code input section
- Hide e-wallet section
- Hide package selector (no package selected yet)
- Show an info banner instead:
```html
<div class="alert alert-info">
  <strong>🎟️ Position Secured — Activation Required</strong><br>
  You are registering to secure your position in the network. Your account will be <strong>inactive</strong> until you activate it with a registration code or e-wallet payment. You can activate anytime from your dashboard.
</div>
<input type="hidden" name="referral_mode" value="1">
```

### 3.4 Review Step (Step 3) — Referral Mode

Adjust the review table to show:
- Payment: "🔗 Referral Link — Position Only"
- Package: "— (to be selected on activation)"
- Sponsor: @username
- Upline: @username
- Position: Left/Right

---

## 4. Activation Page

### 4.1 New Controller Method

**File:** `controllers/MemberController.php` or `controllers/AuthController.php`

```php
public function showActivate(): void
{
    Auth::guard('member');
    $user = Auth::user();

    if ($user['status'] !== 'pending') {
        flash('info', 'Your account is already active.');
        redirect('/?page=dashboard');
    }

    $packages = Package::all(true);
    require 'views/member/activate.php';
}

public function doActivate(): void
{
    Auth::guard('member');
    csrf_verify();
    $user = Auth::user();

    if ($user['status'] !== 'pending') {
        flash('error', 'Your account is already active.');
        redirect('/?page=dashboard');
    }

    $method = $_POST['activation_method'] ?? 'code'; // 'code' | 'ewallet'

    if ($method === 'code') {
        $code = strtoupper(trim($_POST['activation_code'] ?? ''));
        $codeRow = Code::validate($code);
        if (!$codeRow) {
            flash('error', 'Invalid or already-used registration code.');
            redirect('/?page=activate');
        }
        $packageId = (int)$codeRow['package_id'];
        $regCodeId = (int)$codeRow['id'];

        // Activate
        User::activate($user['id'], $packageId, $regCodeId, 'code');
        Code::markUsed($regCodeId, $user['id']);
        flash('success', 'Account activated! Welcome to the network.');
        redirect('/?page=dashboard');
    }

    if ($method === 'ewallet') {
        $packageId = (int)($_POST['package_id'] ?? 0);
        $pkg = Package::find($packageId);
        if (!$pkg) {
            flash('error', 'Please select a package.');
            redirect('/?page=activate');
        }

        $entryFee = (float)$pkg['entry_fee'];
        $bal = Ewallet::balance($user['id']);
        if ($bal < $entryFee) {
            flash('error', 'Insufficient e-wallet balance. Required: ' . fmt_money($entryFee));
            redirect('/?page=activate');
        }

        // Debit self
        Ewallet::debitInternal($user['id'], $entryFee, 0, 'activation', "Activation fee for package {$pkg['name']}");

        // Credit admin
        $adminId = (int)db()->query("SELECT id FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1")->fetchColumn();
        if ($adminId) {
            Ewallet::credit($adminId, $entryFee, 0, 'activation', "Activation fee from @{$user['username']}");
        }

        // Activate
        User::activate($user['id'], $packageId, null, 'ewallet');
        flash('success', 'Account activated using e-wallet! Welcome to the network.');
        redirect('/?page=dashboard');
    }
}
```

### 4.2 `User::activate()` Model Method

**File:** `models/User.php`

```php
public static function activate(int $userId, int $packageId, ?int $regCodeId, string $paymentMethod): void
{
    $pdo = db();
    $pdo->prepare("
        UPDATE users
        SET status = 'active',
            package_id = ?,
            reg_code_id = ?,
            reg_payment_method = ?,
            joined_at = NOW()
        WHERE id = ? AND status = 'pending'
    ")->execute([$packageId, $regCodeId, $paymentMethod, $userId]);
}
```

> **Note:** `joined_at` is set to NOW() on activation. DFI and commission eligibility start from activation date, not position-secured date.

### 4.3 Activation View

**New file:** `views/member/activate.php`

```php
<?php $pageTitle = 'Activate Account'; ?>
<?php require 'views/partials/head.php'; ?>
<?php require 'views/partials/sidebar_member.php'; ?>
<div class="main-content">
  <?php require 'views/partials/topbar.php'; ?>
  <div class="page-content">
    <?= render_flash() ?>
    <div class="row justify-content-center">
      <div class="col-md-8 col-lg-6">
        <div class="card">
          <div class="card-header"><span class="card-title">🎟️ Activate Your Account</span></div>
          <div class="card-body">
            <div class="alert alert-warning">
              Your account is currently <strong>inactive</strong>. You have secured your position in the network, but you cannot earn commissions or DFI until you activate. Choose a method below.
            </div>

            <form method="POST" action="<?= APP_URL ?>/?page=do_activate">
              <?= csrf_field() ?>

              <div class="mb-3">
                <label class="form-label">Activation Method</label>
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="activation_method" id="actCode" value="code" checked onchange="toggleActMethod()">
                  <label class="form-check-label" for="actCode">🎫 Registration Code</label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="activation_method" id="actEwallet" value="ewallet" onchange="toggleActMethod()">
                  <label class="form-check-label" for="actEwallet">💳 E-Wallet Payment</label>
                </div>
              </div>

              <!-- Code Section -->
              <div id="actCodeSection">
                <div class="mb-3">
                  <label class="form-label">Enter Registration Code</label>
                  <input type="text" name="activation_code" class="form-control" placeholder="XXXX-XXXX-XXXX">
                </div>
              </div>

              <!-- E-Wallet Section -->
              <div id="actEwalletSection" style="display:none;">
                <div class="mb-3">
                  <label class="form-label">Select Package</label>
                  <select name="package_id" class="form-select">
                    <option value="">Choose a package…</option>
                    <?php foreach ($packages as $pkg): ?>
                      <option value="<?= $pkg['id'] ?>"><?= e($pkg['name']) ?> — <?= fmt_money($pkg['entry_fee']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="alert alert-info">
                  Your e-wallet balance: <strong><?= fmt_money($user['ewallet_balance']) ?></strong>
                </div>
              </div>

              <button type="submit" class="btn btn-primary w-100">Activate Account →</button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require 'views/partials/footer.php'; ?>
```

---

## 5. Dashboard — Pending User Experience

### 5.1 Show Activation Banner

**File:** `views/member/dashboard.php`

Add at the top of the page (before KPI cards):
```php
<?php if ($user['status'] === 'pending'): ?>
  <div class="card mb-4 border-warning" style="border-width:2px;">
    <div class="card-body">
      <div class="d-flex align-items-center gap-3">
        <div style="font-size:2rem;">⏳</div>
        <div class="flex-grow-1">
          <h5 class="fw-700 mb-1">Account Activation Required</h5>
          <p class="text-muted mb-0" style="font-size:.8rem;">
            Your position is secured, but your account is inactive. Activate now to start earning commissions and DFI.
          </p>
        </div>
        <a href="<?= APP_URL ?>/?page=activate" class="btn btn-warning">Activate →</a>
      </div>
    </div>
  </div>
<?php endif; ?>
```

### 5.2 Hide Earnings for Pending Users

Pending users should see:
- E-Wallet Balance: ₱0.00 (or their actual balance if they have e-wallet from transfers)
- Pairing Earnings: ₱0.00
- Direct Referral: ₱0.00
- All commission history: empty

The existing KPI cards should still render, but values will naturally be 0 since no commissions were credited.

---

## 6. Referral Link UI on Genealogy Page

### 6.1 Header Redesign

**File:** `views/member/genealogy.php`

Replace the current nav pills (line 237-240):
```php
<ul class="nav nav-pills mb-3">
  <li class="nav-item"><a class="nav-link <?= $view !== 'referral' ? 'active' : '' ?>" href="<?= APP_URL ?>/?page=genealogy&view=binary">🌳 Binary Tree</a></li>
  <li class="nav-item"><a class="nav-link <?= $view === 'referral' ? 'active' : '' ?>" href="<?= APP_URL ?>/?page=genealogy&view=referral">👥 Referral Network</a></li>
</ul>
```

With:
```php
<?php
$refLink = APP_URL . '/?page=register&sponsor=' . urlencode($user['username']) . '&ref=1';
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
  <ul class="nav nav-pills">
    <li class="nav-item"><a class="nav-link <?= $view !== 'referral' ? 'active' : '' ?>" href="<?= APP_URL ?>/?page=genealogy&view=binary">🌳 Binary Tree</a></li>
    <li class="nav-item"><a class="nav-link <?= $view === 'referral' ? 'active' : '' ?>" href="<?= APP_URL ?>/?page=genealogy&view=referral">👥 Referral Network</a></li>
  </ul>
  <div class="referral-link-box">
    <div class="input-group input-group-sm" style="max-width:360px;">
      <span class="input-group-text">🔗</span>
      <input type="text" id="refLinkInput" class="form-control font-mono" value="<?= e($refLink) ?>" readonly style="font-size:.78rem;">
      <button class="btn btn-outline-primary" type="button" onclick="copyRefLink()" title="Copy">
        <span id="refCopyIcon">📋</span>
      </button>
    </div>
  </div>
</div>

<script>
function copyRefLink() {
  const el = document.getElementById('refLinkInput');
  el.select();
  navigator.clipboard.writeText(el.value).then(() => {
    const icon = document.getElementById('refCopyIcon');
    icon.textContent = '✅';
    setTimeout(() => icon.textContent = '📋', 1500);
  });
}
</script>

<style>
@media (max-width: 576px) {
  .referral-link-box { width: 100%; margin-top: .5rem; }
  .referral-link-box .input-group { max-width: 100% !important; }
}
</style>
```

> **Mobile behavior:** On screens < 576px, the referral link drops below the tabs and takes full width.

---

## 7. Routes

**File:** `index.php`

Add to the route table:
```php
'activate'     => ['MemberController', 'showActivate',  'member'],
'do_activate'  => ['MemberController', 'doActivate',    'member'],
```

---

## 8. SQL `ref_type` Warning Fix

The warning `SQLSTATE[01000]: Warning: 1265 Data truncated for column 'ref_type'` occurs when `Ewallet::credit()` or `Ewallet::debit()` is called with a `ref_type` not in the enum.

**Root cause:** Some code path passes an unexpected string. Search all callers:
```bash
grep -rn "Ewallet::credit\|Ewallet::debit" core/ models/ controllers/ --include="*.php"
```

**Likely culprit:** `Ewallet::credit($adminId, $entryFee, 0, 'registration', ...)` — but `'registration'` IS in the enum. Another possibility: a NULL or empty string is being passed.

**Fix:** Add validation at the start of `Ewallet::credit()` and `Ewallet::debit()`:
```php
$validRefTypes = ['commission', 'payout', 'reactivation', 'transfer', 'topup', 'registration'];
if (!in_array($refType, $validRefTypes, true)) {
    throw new InvalidArgumentException("Invalid ref_type: {$refType}");
}
```

This turns a silent MySQL warning into a loud PHP exception, making the actual caller immediately obvious.

---

## Files to Touch

| # | File | Action |
|---|------|--------|
| 1 | `core/Commission.php` | Add `status = 'active'` checks in `processDirectReferral()` and `processIndirectReferral()` |
| 2 | `core/DailyFixedIncome.php` | Add `u.status = 'active'` to DFI cron query |
| 3 | `models/User.php` | Accept optional `status` in `register()`; add `activate()` method |
| 4 | `models/Ewallet.php` | Add `ref_type` validation to prevent SQL warning |
| 5 | `controllers/AuthController.php` | Add `findNextBinarySlot()`; handle `referral_mode` in `showRegister()` and `doRegister()` |
| 6 | `controllers/MemberController.php` | Add `showActivate()` and `doActivate()` |
| 7 | `views/auth/register.php` | Hide payment UI in referral mode; show info banner; pass `referral_mode` |
| 8 | `views/member/activate.php` | **New** — activation page with code + e-wallet tabs |
| 9 | `views/member/dashboard.php` | Add pending activation banner |
| 10 | `views/member/genealogy.php` | Add referral link input with copy button |
| 11 | `index.php` | Add `activate` and `do_activate` routes |

---

## QA Test Plan

| # | Test | Steps | Expected |
|---|------|-------|----------|
| 1 | Referral link opens form | Visit `/?page=register&sponsor=test1&ref=1` | Form loads with sponsor locked, upline+position auto-filled, no payment UI |
| 2 | Register without payment | Submit referral form | Account created with `status = 'pending'`, `package_id = NULL` |
| 3 | Pending user no pairing | Register a downline under a pending user | Pending user earns ₱0 pairing (tree grows but no bonus) |
| 4 | Pending user no direct | Register a direct downline under a pending sponsor | Pending sponsor earns ₱0 direct referral |
| 5 | Pending user no DFI | Run DFI cron with pending user in DB | Pending user receives ₱0 DFI |
| 6 | Activation with code | Pending user enters valid code on `?page=activate` | Status → `active`, package set, code marked used |
| 7 | Activation with e-wallet | Pending user selects package, has enough balance | Balance debited, status → `active`, admin credited |
| 8 | Post-activation earnings | Register a downline under now-active user | User earns direct + pairing normally |
| 9 | Retroactive flush | User had 3 downlines while pending, then activates | No retroactive pay for those 3 downlines |
| 10 | Referral link copy | Click copy icon on genealogy page | Link copied to clipboard, icon changes to ✅ |
| 11 | Mobile layout | Resize browser to < 576px on genealogy | Referral link drops below tabs, full width |
| 12 | Already active visit | Active user visits `?page=activate` | Redirected to dashboard with "already active" flash |

---

*Prepared for review. Do not implement until approved.*
