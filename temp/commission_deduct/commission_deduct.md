# Plan: Commission-Deduct (CD) Status

**Status:** Draft — Pending Review  
**Date:** 2026-06-03  
**Scope:** Backend, Admin UI, Member UI, Commission Flow, DFI Flow  

---

## 1. Overview

**Commission-Deduct (CD)** is a designation assigned by admin to active members. While in CD mode:

- The member **earns commissions normally** (direct referral, pairing, indirect).
- But **commissions are first routed to a "CD bucket"** until the bucket is filled.
- The bucket target is typically equal to the member's **package entry fee**.
- **DFI is suspended** until the CD bucket is fully filled.
- Once the bucket is filled, CD mode auto-completes, and all **future commissions become fully withdrawable**.
- DFI resumes on the next eligible cycle after CD completion.

> **Analogy:** It's like a "membership fee recovery lock" — the system holds commissions in escrow until the member has "paid back" their entry fee through earnings, then unlocks full withdrawal.

---

## 2. Business Rules

| # | Rule |
|---|------|
| 1 | Only **active** members can be assigned CD status. |
| 2 | CD target amount defaults to the member's **current package entry fee**. Admin can override. |
| 3 | CD bucket receives commissions **before** the withdrawable e-wallet balance. |
| 4 | If a commission exceeds the remaining CD bucket space, the **overflow goes to withdrawable balance**. |
| 5 | DFI is **not credited** while CD is active. It does not accrue or backlog. |
| 6 | CD auto-completes when `filled_amount >= target_amount`. |
| 7 | Upon completion, member's future commissions are **100 % withdrawable** (subject to normal cap rules). |
| 8 | CD is **not retroactive** — commissions earned *before* CD assignment remain in the e-wallet as-is. |
| 9 | A member can only have **one active CD** at a time. |
| 10 | Admin can **manually complete or cancel** an active CD (edge case / support tool). |

---

## 3. Database Schema

### 3.1 New Table: `user_cd_status`

```sql
CREATE TABLE user_cd_status (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    target_amount   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    filled_amount   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    status          ENUM('active','completed','cancelled') NOT NULL DEFAULT 'active',
    assigned_by     INT NOT NULL,          -- admin user ID
    assigned_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at    DATETIME NULL,
    cancelled_at    DATETIME NULL,
    notes           TEXT NULL,
    INDEX idx_user_active (user_id, status),
    INDEX idx_assigned_at (assigned_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 3.2 New Table: `cd_ledger`

Records every commission slice that went into the CD bucket.

```sql
CREATE TABLE cd_ledger (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    cd_status_id    INT NOT NULL,          -- FK to user_cd_status.id
    commission_id   INT NULL,              -- FK to commissions.id (optional but useful)
    type            ENUM('pairing','direct_referral','indirect_referral') NOT NULL,
    gross_amount    DECIMAL(12,2) NOT NULL, -- total commission before CD split
    cd_amount       DECIMAL(12,2) NOT NULL, -- portion that went to CD bucket
    withdrawable_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00, -- overflow to e-wallet
    source_user_id  INT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_cd (user_id, cd_status_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 3.3 Migration File

`migrations/003_add_cd_schema.sql`

```sql
-- CD status and ledger tables
CREATE TABLE user_cd_status (...);       -- as above
CREATE TABLE cd_ledger (...);            -- as above

-- Optional: add a cached flag to users for fast lookup
ALTER TABLE users ADD COLUMN cd_active TINYINT(1) NOT NULL DEFAULT 0;
```

> **Rationale for `users.cd_active`:** Commission firing is hot-path. A cached boolean avoids a JOIN on `user_cd_status` for the ~95 % of members who don't have CD.

---

## 4. Commission Flow Changes

### 4.1 High-Level Flow

```
Commission Earned
    │
    ▼
Is user.cd_active = 1 ?
    │
    ├── NO  → Credit full amount to e-wallet (existing flow)
    │
    └── YES → Read user's active CD record
              │
              ▼
        remaining = target_amount - filled_amount
              │
              ▼
        Is remaining > 0 ?
              │
              ├── NO  → CD already full (shouldn't happen, but safety)
              │         Credit full amount to e-wallet
              │
              └── YES → cd_portion = min(gross_commission, remaining)
                        wallet_portion = gross_commission - cd_portion
                        │
                        ▼
                Update user_cd_status.filled_amount += cd_portion
                Insert cd_ledger row
                Credit wallet_portion to e-wallet (if > 0)
                │
                ▼
        Is filled_amount >= target_amount now ?
              │
              ├── YES → Mark CD as 'completed', cd_active = 0
              │         (Optionally flash a notification)
              │
              └── NO  → Keep CD active
```

### 4.2 Modified Methods in `core/Commission.php`

#### A. `creditPairing()` — wrap with CD split

```php
public static function creditPairing(int $userId, float $amount, int $pairs, int $sourceId): void
{
    $cdSplit = self::applyCdSplit($userId, $amount, 'pairing', $sourceId);
    // $cdSplit = ['cd' => X, 'wallet' => Y, 'cd_status_id' => Z]

    if ($cdSplit['wallet'] > 0) {
        self::creditEwalletAndLedger($userId, $cdSplit['wallet'], 'pairing', $sourceId, $pairs);
    }
    if ($cdSplit['cd'] > 0) {
        self::recordCdLedger($userId, $cdSplit['cd_status_id'], $amount, $cdSplit['cd'], $cdSplit['wallet'], 'pairing', $sourceId);
    }
}
```

#### B. `processDirectReferral()` — wrap with CD split

Same pattern: gross amount → `applyCdSplit()` → route to CD bucket or wallet.

#### C. `processIndirectReferral()` — wrap with CD split

Same pattern.

#### D. New Helper: `applyCdSplit()`

```php
private static function applyCdSplit(int $userId, float $grossAmount, string $type, ?int $sourceId = null): array
{
    $pdo = db();

    // Fast-path: 95 % of users have no CD
    $user = $pdo->prepare('SELECT cd_active FROM users WHERE id = ?');
    $user->execute([$userId]);
    if (!$user->fetchColumn()) {
        return ['cd' => 0.00, 'wallet' => $grossAmount, 'cd_status_id' => null];
    }

    // Lock CD row for update
    $st = $pdo->prepare('
        SELECT id, target_amount, filled_amount
        FROM user_cd_status
        WHERE user_id = ? AND status = "active"
        FOR UPDATE
    ');
    $st->execute([$userId]);
    $cd = $st->fetch();

    if (!$cd) {
        // Stale cd_active flag — fix and bail out
        $pdo->prepare('UPDATE users SET cd_active = 0 WHERE id = ?')->execute([$userId]);
        return ['cd' => 0.00, 'wallet' => $grossAmount, 'cd_status_id' => null];
    }

    $remaining = (float)$cd['target_amount'] - (float)$cd['filled_amount'];
    if ($remaining <= 0) {
        // Already full — complete it
        self::completeCd((int)$cd['id'], $userId);
        return ['cd' => 0.00, 'wallet' => $grossAmount, 'cd_status_id' => null];
    }

    $cdPortion     = min($grossAmount, $remaining);
    $walletPortion = $grossAmount - $cdPortion;

    // Update CD bucket
    $pdo->prepare('
        UPDATE user_cd_status
        SET filled_amount = filled_amount + :amt
        WHERE id = :id
    ')->execute([':amt' => $cdPortion, ':id' => $cd['id']]);

    // Check completion
    $newFilled = (float)$cd['filled_amount'] + $cdPortion;
    if ($newFilled >= (float)$cd['target_amount']) {
        self::completeCd((int)$cd['id'], $userId);
    }

    return [
        'cd'            => $cdPortion,
        'wallet'        => $walletPortion,
        'cd_status_id'  => (int)$cd['id'],
    ];
}
```

#### E. New Helper: `completeCd()`

```php
private static function completeCd(int $cdStatusId, int $userId): void
{
    $pdo = db();
    $pdo->prepare('
        UPDATE user_cd_status
        SET status = "completed", completed_at = NOW()
        WHERE id = ?
    ')->execute([$cdStatusId]);

    $pdo->prepare('UPDATE users SET cd_active = 0 WHERE id = ?')->execute([$userId]);

    // Optional: create a notification or flash for the user
    // (Can be queued or deferred to avoid blocking commission flow)
}
```

---

## 5. DFI Flow Changes

### 5.1 `DailyFixedIncome::processDailyPayout()`

Add a CD check at the top of the eligible-user query:

```sql
-- Before (existing)
SELECT u.id, u.ewallet_balance, u.dfi_active, ...
FROM users u
WHERE u.status = 'active'
  AND u.dfi_active = 1
  AND u.package_id IS NOT NULL
  ...

-- After (with CD guard)
SELECT u.id, u.ewallet_balance, u.dfi_active, ...
FROM users u
WHERE u.status = 'active'
  AND u.dfi_active = 1
  AND u.package_id IS NOT NULL
  AND u.cd_active = 0           -- ← NEW: skip members in CD mode
  ...
```

> **Rule:** DFI is simply skipped for CD-active members. It does **not** backlog or accrue. The member resumes DFI on the next cron cycle after CD completes.

---

## 6. Admin UI

### 6.1 Admin Users List (`/?page=admin_users`)

- Add a new column or badge: **"CD"** (amber/orange badge).
- Only show badge if `cd_active = 1`.
- Clicking the user row or "View" button goes to the detail page.

### 6.2 Admin User Detail (`/?page=admin_user_view&id=X`)

Add a new card: **"Commission-Deduct Status"**

```
┌─ Commission-Deduct Status ──────────────────────────┐
│                                                      │
│  Status:    [Active / Completed / None]              │
│  Target:    ₱3,500.00                                │
│  Filled:    ₱1,200.00 (34 %)                         │
│  Progress:  [████████░░░░░░░░░░░░]                   │
│  Assigned:  2026-06-01 by @admin                     │
│                                                      │
│  [Assign CD]  [Mark Complete]  [Cancel CD]           │
│                                                      │
└──────────────────────────────────────────────────────┘
```

#### Actions:

| Button | When Visible | What It Does |
|--------|-------------|--------------|
| **Assign CD** | User has no active CD | Opens modal to set target amount (default = package entry fee). On confirm: inserts `user_cd_status` row, sets `users.cd_active = 1`. |
| **Mark Complete** | User has active CD | Manually completes CD. Sets `status = 'completed'`, `cd_active = 0`. |
| **Cancel CD** | User has active CD | Cancels CD. Sets `status = 'cancelled'`, `cd_active = 0`. Already-filled amount is **forfeited** (or optionally credited to wallet — TBD). |

### 6.3 Assign CD Modal

```
┌─ Assign Commission-Deduct ───────────┐
│                                       │
│  Member:     @altas05                 │
│  Package:    Starter                  │
│  Entry Fee:  ₱3,500.00                │
│                                       │
│  CD Target Amount:                    │
│  [₱3,500.00                    ]      │
│  (Default = package entry fee)        │
│                                       │
│  [Cancel]        [Confirm Assign]     │
│                                       │
└───────────────────────────────────────┘
```

---

## 7. Member UI

### 7.1 Dashboard Card

If `cd_active = 1`, show a prominent card below the pending banner (if any):

```
┌─ ⏳ Commission-Deduct Bucket ─────────────────────────┐
│                                                        │
│  Target:  ₱3,500.00                                    │
│  Filled:  ₱1,200.00  (34 %)                            │
│                                                        │
│  [████████░░░░░░░░░░░░░░░░░░░░░░░░░░░░]                │
│                                                        │
│  DFI is paused until bucket is full.                   │
│  Keep earning — commissions are filling your bucket!   │
│                                                        │
└────────────────────────────────────────────────────────┘
```

### 7.2 Earnings Page (`/?page=earnings`)

Add a **"CD Bucket"** tab or section:

| Date | Type | Gross | To Bucket | To Wallet | From |
|------|------|-------|-----------|-----------|------|
| Jun 03 | Pairing | +₱1,500.00 | ₱1,500.00 | ₱0.00 | @altas15 |
| Jun 02 | Direct | +₱1,000.00 | ₱1,000.00 | ₱0.00 | @altas14 |
| Jun 01 | Pairing | +₱1,500.00 | ₱1,200.00 | ₱300.00 | @altas13 |

> The last row shows the **overflow** case: only ₱1,200 was needed to fill the bucket, so ₱300 went to the wallet.

### 7.3 Topbar / Profile Badge

- If `cd_active = 1`, show a small **"CD"** badge next to the username pill.
- Style: amber/orange pill, similar to pending badge.

---

## 8. API / Backend Endpoints

### 8.1 New Admin Endpoints

| Endpoint | Method | Auth | Description |
|----------|--------|------|-------------|
| `/?page=admin_assign_cd` | POST | Admin | Assign CD to user. Body: `user_id`, `target_amount` |
| `/?page=admin_complete_cd` | POST | Admin | Manually complete a user's active CD. Body: `user_id` |
| `/?page=admin_cancel_cd` | POST | Admin | Cancel a user's active CD. Body: `user_id`, `reason` |

### 8.2 New Member Endpoint

| Endpoint | Method | Auth | Description |
|----------|--------|------|-------------|
| `/?page=api_cd_status` | GET | Member | Returns current CD status + progress for logged-in user. JSON: `{active, target, filled, percent, completed_at}` |

---

## 9. Settings Whitelist

If any new admin-configurable settings are added (e.g., `cd_enabled_globally`, `cd_default_multiplier`), they must be added to:

- `AdminController::$allowed` array
- Checkbox-handling array (if toggle)

---

## 10. Files to Touch (Estimated)

| File | Change |
|------|--------|
| `migrations/003_add_cd_schema.sql` | New tables + column |
| `core/Commission.php` | Wrap `creditPairing`, `processDirectReferral`, `processIndirectReferral` with CD split logic |
| `core/DailyFixedIncome.php` | Add `AND u.cd_active = 0` to DFI query |
| `models/User.php` | Optional: helper `hasActiveCd()`, `getCdProgress()` |
| `controllers/AdminController.php` | New methods: `assignCd()`, `completeCd()`, `cancelCd()` |
| `controllers/MemberController.php` | New method: `apiCdStatus()` |
| `views/admin/user_view.php` | Add CD status card |
| `views/admin/users.php` | Add CD badge in table |
| `views/member/dashboard.php` | Add CD progress card |
| `views/member/earnings.php` | Add CD Ledger tab/section |
| `views/partials/topbar.php` | Add CD badge next to username |

---

## 11. Open Questions / TBD

1. **Overflow on final commission:** When a commission partially fills the bucket and the rest overflows to wallet, should the `commissions` table record the **gross** amount or the **wallet** amount as `amount`?
   - *Suggestion:* Record gross in `commissions.amount`, and use `cd_ledger` to audit the split. This keeps commission totals honest.

2. **Cancellation policy:** If admin cancels an active CD, what happens to already-filled amount?
   - *Option A:* Forfeited (simplest).
   - *Option B:* Credited to e-wallet (more user-friendly).
   - *Option C:* Refunded as non-withdrawable credit.

3. **Multiple CD assignments:** Can a member be assigned CD again after completing one?
   - *Suggestion:* Yes, but each is a separate `user_cd_status` row. Only one can be `active` at a time.

4. **Package change during CD:** If a member upgrades/downgrades their package while CD is active, does the target change?
   - *Suggestion:* Target is locked at assignment time. Changing package does not retroactively adjust the CD target.

5. **CapEngine interaction:** Does CD deduction happen **before** or **after** lifetime cap checks?
   - *Suggestion:* Cap check happens **first** (on gross commission). Then CD split happens on the `allowed` amount. This prevents CD from bypassing caps.

6. **Pairing description:** When a pairing commission is split, what description appears in the Earnings table?
   - *Suggestion:* "1 pair(s) × ₱1,500.00 — ₱800 to CD bucket, ₱700 to wallet"

---

## 12. Edge Cases

| Scenario | Expected Behavior |
|----------|-------------------|
| Member with CD earns ₱0 commission | No CD ledger row inserted. Bucket unchanged. |
| CD target = ₱0 (admin sets to 0) | CD completes immediately on assignment. |
| Member suspended while CD active | CD remains active but commissions stop (existing suspension logic). DFI remains paused. |
| Admin assigns CD to pending user | **Reject.** CD is only for active users. |
| Commission exactly fills bucket | CD completes. Entire commission goes to bucket, ₱0 to wallet. |
| Member already has active CD, admin assigns again | **Reject.** One active CD at a time. |
| CD completed, member earns next commission | 100 % goes to wallet. DFI resumes next cron. |

---

**End of Plan — Awaiting Review**
