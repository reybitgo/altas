# Seat Limit Plan — Hard Member Cap Enforcement

> **Problem:** The frontend talks about a "1,000-member cap" but there is **no actual enforcement** in the backend. Registration never checks how many members exist. An admin cannot even configure the limit.
>
> **Goal:** Add a configurable `seat_limit` setting. When the total number of member accounts reaches this limit, registration **ceases completely** — no guest registration, no logged-in member registration, no admin bypass.

---

## Current State

| Area | Status |
|------|--------|
| `settings` table | No `seat_limit` key exists |
| `User::counts()` | Returns `total` member count (currently 16) |
| `AuthController::showRegister()` | No seat check before rendering form |
| `AuthController::doRegister()` | No seat check before `User::register()` |
| `User::register()` | No seat check inside the transaction |
| `frontend/index.php` | Hardcodes "1,000" in ~20 places |
| Member Dashboard | "➕ Register Member" button always visible |
| Admin Settings | No seat limit input field |

---

## Architecture

```
┌─────────────────┐     ┌─────────────────────┐     ┌─────────────────┐
│  Admin Settings │────▶│  settings.seat_limit │────▶│  All enforcement │
│  (configurable) │     │  (default: 1000)     │     │  layers          │
└─────────────────┘     └─────────────────────┘     └─────────────────┘
                                                              │
        ┌─────────────────────────────────────────────────────┼─────┐
        │                                                     │     │
        ▼                                                     ▼     ▼
┌───────────────┐   ┌───────────────┐   ┌───────────────┐   ┌───────────────┐
│  Frontend     │   │  showRegister │   │  doRegister   │   │  User::register│
│  (marketing)  │   │  (UI block)   │   │  (POST block) │   │  (hard guard)  │
└───────────────┘   └───────────────┘   └───────────────┘   └───────────────┘
```

**Rule:** The seat limit counts **all member accounts** (`role = 'member'`), regardless of status (`active`, `suspended`, `capped`, `perminact`). Admins do not count toward the limit.

---

## Implementation Steps

### 1. Core Helper — `seatsRemaining()`

Add to `core/helpers.php` (or `core/Auth.php`):

```php
function seatsRemaining(): int
{
    $limit = (int) setting('seat_limit', '1000');
    $count = (int) db()->query("SELECT COUNT(*) FROM users WHERE role = 'member'")->fetchColumn();
    return max(0, $limit - $count);
}

function isSeatLimitReached(): bool
{
    return seatsRemaining() <= 0;
}
```

> **Why a helper?** Centralizes the logic. Every enforcement point calls the same function. Easy to test, easy to change the counting rule later.

---

### 2. Admin Settings — Add `seat_limit` Input

**File:** `views/admin/settings.php`

Add after the **Maintenance Mode** dropdown (line ~100):

```php
<div class="mb-3">
  <label class="form-label">🪑 Seat Limit (Hard Member Cap)</label>
  <input type="number" name="seat_limit" class="form-control" min="1" step="1"
    value="<?= e(setting('seat_limit', '1000')) ?>">
  <div class="form-text">
    Maximum number of member accounts allowed. When reached, registration closes permanently.
    Current members: <strong><?= User::counts()['total'] ?? 0 ?></strong> ·
    Remaining seats: <strong><?= seatsRemaining() ?></strong>
  </div>
</div>
```

> **Visual cue:** Show live "Current / Remaining" counters so the admin always knows the status.

---

### 3. AdminController — Whitelist the New Setting

**File:** `controllers/AdminController.php`

Add `'seat_limit'` to the `$allowed` array (around line 343):

```php
$allowed = [
    // ... existing keys ...
    'seat_limit',
    // ...
];
```

No special checkbox handling needed (it's a number input, not a checkbox).

---

### 4. AuthController — Block Registration Form When Full

**File:** `controllers/AuthController.php` — `showRegister()`

```php
public function showRegister(): void
{
    // ── Seat limit check ──
    if (isSeatLimitReached()) {
        http_response_code(403);
        require 'views/auth/register_closed.php';  // new view (see Step 8)
        return;
    }

    $prefillSponsor = trim($_GET['sponsor'] ?? '');
    $packages       = Package::all(true);
    // ... rest unchanged
}
```

> **Why block here?** Prevents the browser from even receiving the registration form HTML. Cleanest UX.

---

### 5. AuthController — Block Registration Submission When Full

**File:** `controllers/AuthController.php` — `doRegister()`

Add immediately after `csrf_verify()` (line 67):

```php
public function doRegister(): void
{
    csrf_verify();

    // ── Seat limit check ──
    if (isSeatLimitReached()) {
        flash('error', 'Registration is closed. The member seat limit has been reached.');
        redirect('/?page=register');
    }

    $wasLoggedIn = Auth::check();
    // ... rest unchanged
}
```

> **Why also here?** `showRegister()` blocks the form, but a malicious actor could still POST directly. Defense in depth.

---

### 6. User::register() — Hard Guard

**File:** `models/User.php` — `register()`

Add as the very first line inside the method:

```php
public static function register(array $data): int
{
    if (isSeatLimitReached()) {
        throw new RuntimeException('Registration is closed. The member seat limit has been reached.');
    }

    $pdo = db();
    $pdo->beginTransaction();
    // ... rest unchanged
}
```

> **Why also here?** This is the model layer. Even if someone bypasses `AuthController` (e.g., direct DB script, API, future registration path), the model itself refuses to insert.

---

### 7. Member Dashboard — Hide "Register Member" Button When Full

**File:** `views/member/dashboard.php`

Current button (line 90):
```php
<a href="<?= APP_URL ?>/?page=register&sponsor=<?= urlencode($user['username']) ?>"
   class="btn btn-success btn-sm">➕ Register Member</a>
```

Replace with:
```php
<?php if (!isSeatLimitReached()): ?>
  <a href="<?= APP_URL ?>/?page=register&sponsor=<?= urlencode($user['username']) ?>"
     class="btn btn-success btn-sm">➕ Register Member</a>
<?php else: ?>
  <span class="btn btn-secondary btn-sm" style="cursor:not-allowed;opacity:.6;"
        title="Seat limit reached — registration is closed.">
    🔒 Registration Closed
  </span>
<?php endif; ?>
```

> **Also check:** Admin dashboard (`views/admin/dashboard.php`) — if it has a "Create Member" or "Register Member" shortcut, apply the same logic.

---

### 8. New View — `views/auth/register_closed.php`

Create a dedicated "Registration Closed" page:

```php
<?php $pageTitle = 'Registration Closed — ' . setting('site_name', APP_NAME); ?>
<?php require 'views/partials/head.php'; ?>
<div class="d-flex align-items-center justify-content-center" style="min-height:100vh;">
  <div class="text-center" style="max-width:400px;padding:2rem;">
    <div style="font-size:4rem;">🔒</div>
    <h2 class="fw-800 mt-3">Registration Closed</h2>
    <p class="text-muted">
      The <?= e(setting('seat_limit', '1000')) ?>-member seat limit has been reached.
      No new accounts can be created at this time.
    </p>
    <a href="<?= APP_URL ?>/?page=login" class="btn btn-primary">Sign In →</a>
    <a href="<?= APP_URL ?>/" class="btn btn-outline-secondary ms-2">Home</a>
  </div>
</div>
<?php require 'views/partials/footer.php'; ?>
```

> **Reuse for guests too:** Guests who hit `?page=register` directly should see this same closed page (not the member sidebar layout). The `showRegister()` method should render it without requiring `Auth::guard()`.

**Guest variant:** Since `showRegister()` doesn't require auth, the closed view should work for both guests and logged-in users. Use a minimal standalone layout:

```php
<?php
$name = setting('site_name', APP_NAME);
$limit = (int) setting('seat_limit', '1000');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Registration Closed — <?= e($name) ?></title>
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/auth.css">
  <style>
    body { display:flex; align-items:center; justify-content:center; min-height:100vh;
           background:#f4f6fb; font-family:system-ui; margin:0; }
    .box { text-align:center; padding:40px; max-width:420px; background:#fff;
           border-radius:12px; box-shadow:0 4px 20px rgba(0,0,0,.06); }
    .emoji { font-size:3.5rem; margin-bottom:.5rem; }
    h1 { font-size:1.5rem; margin-bottom:.5rem; color:#1a1a2e; }
    p { color:#6b7a99; line-height:1.6; margin-bottom:1.5rem; }
    .btn { display:inline-block; padding:.6rem 1.4rem; border-radius:8px;
           text-decoration:none; font-weight:600; font-size:.9rem; }
    .btn-primary { background:var(--primary); color:#fff; }
    .btn-outline { background:transparent; color:var(--primary); border:1px solid var(--primary); }
  </style>
</head>
<body>
  <div class="box">
    <div class="emoji">🔒</div>
    <h1>Registration Closed</h1>
    <p>The member seat limit of <strong><?= number_format($limit) ?></strong> has been reached. No new accounts can be created at this time.</p>
    <a href="<?= APP_URL ?>/?page=login" class="btn btn-primary">Sign In →</a>
    <a href="<?= APP_URL ?>/" class="btn btn-outline" style="margin-left:.5rem;">Home</a>
  </div>
</body>
</html>
```

---

### 9. Frontend Landing Page — Dynamic Seat Limit

**File:** `frontend/index.php`

Add to the bootstrap block:
```php
$seatLimit = (int) setting('seat_limit', '1000');
$membersNow = (int) db()->query("SELECT COUNT(*) FROM users WHERE role = 'member'")->fetchColumn();
$seatsLeft = max(0, $seatLimit - $membersNow);
$isFull = $seatsLeft <= 0;
```

**Replace all hardcoded "1,000" references** with `<?= number_format($seatLimit) ?>`.

**Key sections to update:**

| Section | Current Hardcoded | Proposed |
|---------|-------------------|----------|
| `<title>` | "Closed 1,000-Member" | "Closed <?= number_format($seatLimit) ?>-Member" |
| Meta description | "1,000-member" | "<?= number_format($seatLimit) ?>-member" |
| OG description | "1,000 farmers" | "<?= number_format($seatLimit) ?> farmers" |
| Schema.org | "limited to exactly 1,000" | "limited to exactly <?= number_format($seatLimit) ?>" |
| Hero eyebrow | (no number) | keep as-is |
| About chip | "1,000 Seats Total" | "<?= number_format($seatLimit) ?> Seats Total" |
| About features | "Hard cap of 1,000" | "Hard cap of <?= number_format($seatLimit) ?>" |
| Marquee | "1,000 Members Only" | "<?= number_format($seatLimit) ?> Members Only" |
| How It Works | "Your seat among the 1,000" | "Your seat among the <?= number_format($seatLimit) ?>" |
| Compensation Plan | "within the 1,000-member ceiling" | "within the <?= number_format($seatLimit) ?>-member ceiling" |
| Package section | "closed network of 1,000" | "closed network of <?= number_format($seatLimit) ?>" |
| Package features | "capped at 1,000" | "capped at <?= number_format($seatLimit) ?>" |
| CTA section | "1,000 Seats. Not One More." | "<?= number_format($seatLimit) ?> Seats. Not One More." |
| FAQ | "limited to exactly 1,000" | "limited to exactly <?= number_format($seatLimit) ?>" |
| FAQ | "capped at 1,000 members" | "capped at <?= number_format($seatLimit) ?> members" |
| FAQ | "When the 1,000th" | "When the <?= number_format($seatLimit) ?>th" |
| TOS §3 | "cap of exactly 1,000" | "cap of exactly <?= number_format($seatLimit) ?>" |
| TOS §5 | "within the 1,000-seat limit" | "within the <?= number_format($seatLimit) ?>-seat limit" |
| TOS §9 | "within the 1,000-seat limit" | "within the <?= number_format($seatLimit) ?>-seat limit" |
| TOS Income Disclaimer | "within the 1,000-seat limit" | "within the <?= number_format($seatLimit) ?>-seat limit" |
| Contact modal | (no number) | keep as-is |

**When `$isFull === true`:** Replace the CTA buttons and package section with a "Registration Closed" banner:

```php
<?php if ($isFull): ?>
  <div class="closed-banner" style="background:#fef3c7;border:2px solid #f59e0b;">
    <div class="closed-banner-icon">🔒</div>
    <div class="closed-banner-text">
      <strong>Registration is closed.</strong>
      <span>All <?= number_format($seatLimit) ?> seats have been filled. The network is now complete.</span>
    </div>
  </div>
<?php else: ?>
  <!-- existing package grid / CTA -->
<?php endif; ?>
```

Also add a live counter widget (optional but nice):
```php
<div style="text-align:center;margin-top:1rem;font-size:.85rem;color:var(--muted);">
  <?= number_format($membersNow) ?> of <?= number_format($seatLimit) ?> seats filled
  <?php if ($seatsLeft > 0): ?>· <?= number_format($seatsLeft) ?> remaining<?php endif; ?>
</div>
```

---

### 10. Router / Index.php — Optional Global Block

**File:** `index.php`

The router already has a `maintenance_mode` check. We could add a seat-limit block right after it for `?page=register`:

```php
// Maintenance mode
if (setting('maintenance_mode') === '1' && !Auth::isAdmin()) {
    // ... existing code ...
}

// Seat limit — block registration routes entirely when full
if (in_array($_GET['page'] ?? '', ['register', 'do_register', 'validate_code'], true) && isSeatLimitReached()) {
    http_response_code(403);
    $name = setting('site_name', APP_NAME);
    die("<!doctype html>...Registration Closed...");
}
```

> **Debate:** This is redundant with `AuthController::showRegister()`, but it catches direct URL access even if the controller is somehow bypassed. **Recommendation:** Include it for defense in depth, but keep the dedicated `register_closed.php` view for the controller path.

---

### 11. Admin Dashboard — Seat Counter Widget

**File:** `views/admin/dashboard.php`

Add a small widget showing:
- Total members: `User::counts()['total']`
- Seat limit: `setting('seat_limit', '1000')`
- Remaining: `seatsRemaining()`
- Visual progress bar

This gives the admin an at-a-glance view of network capacity.

---

## Files to Touch (Summary)

| File | Action |
|------|--------|
| `core/helpers.php` | Add `seatsRemaining()` and `isSeatLimitReached()` |
| `views/admin/settings.php` | Add `seat_limit` number input + live counters |
| `controllers/AdminController.php` | Add `'seat_limit'` to `$allowed` whitelist |
| `controllers/AuthController.php` | Add `isSeatLimitReached()` guard in `showRegister()` and `doRegister()` |
| `models/User.php` | Add hard guard at top of `register()` |
| `views/auth/register_closed.php` | **New file** — standalone "Registration Closed" page |
| `views/member/dashboard.php` | Conditionally hide/disable "➕ Register Member" button |
| `views/admin/dashboard.php` | Add seat counter widget (optional) |
| `frontend/index.php` | Replace all hardcoded "1,000" with `<?= number_format($seatLimit) ?>`; show closed banner when full |
| `index.php` | Optional: global route block for registration pages |

---

## QA Test Plan

| # | Test | Steps | Expected |
|---|------|-------|----------|
| 1 | Admin can set limit | Go to System Settings, change Seat Limit to 20, Save | Green success flash; value persists |
| 2 | Counter updates | Add member; refresh Settings | "Current members" counter increments |
| 3 | Form blocked at limit | Set limit = current count; visit `?page=register` | Sees "Registration Closed" page, not form |
| 4 | POST blocked at limit | Set limit = current count; POST directly to `do_register` | Redirects with error flash; no account created |
| 5 | Model guard | Set limit = current count; call `User::register()` directly | Throws `RuntimeException` |
| 6 | Dashboard button hidden | Set limit = current count; log in as member | "➕ Register Member" replaced with "🔒 Registration Closed" |
| 7 | Frontend shows closed | Set limit = current count; visit `/frontend/` | CTA shows "Registration is closed" banner |
| 8 | Frontend dynamic number | Set limit to 500; visit `/frontend/` | All copy says "500" instead of "1,000" |
| 9 | Re-open after increase | Set limit = current + 5; visit `?page=register` | Form appears normally |
| 10 | Admin still sees settings | Set limit = current; log in as admin | Admin can still access System Settings and change limit |

---

*Prepared for review. Do not implement until approved.*
