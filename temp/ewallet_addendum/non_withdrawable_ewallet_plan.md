# Non-Withdrawable E-Wallet Plan

> **Status:** Pending review  
> **Goal:** Split e-wallet into two buckets — **Withdrawable** (commissions, DFI) and **Non-Withdrawable** (transfers, top-ups). Non-withdrawable funds can be used for internal transactions (reactivation, future features) but **cannot** be cashed out as payout.

---

## 1. Core Concept

Every member's e-wallet now has two visible numbers:

| Bucket | Source | Can Withdraw? | Can Spend Internally? |
|--------|--------|--------------|----------------------|
| **Withdrawable** | Commissions, Pairing, Direct Ref, Indirect Ref, DFI | ✅ Yes | ✅ Yes |
| **Non-Withdrawable** | Member-to-member Transfers, Admin Top-Ups | ❌ No | ✅ Yes |

**Total Balance = Withdrawable + Non-Withdrawable**

> **Mental model for members:** "I have ₱1,000 total. ₱700 is my earned money (can cash out). ₱300 was sent to me by others / topped up by admin (can only spend inside the platform)."

---

## 2. Database Change

### 2.1 Add `withdrawable_balance` to `users` table

```sql
ALTER TABLE users
  ADD COLUMN withdrawable_balance DECIMAL(14,2) NOT NULL DEFAULT 0.00
  AFTER ewallet_balance;
```

| Column | Meaning |
|--------|---------|
| `ewallet_balance` | **Total** balance (withdrawable + non-withdrawable) |
| `withdrawable_balance` | Portion that **can** be requested as payout |

**Non-withdrawable = `ewallet_balance - withdrawable_balance`**

### 2.2 Migration File

**`migrations/007_add_withdrawable_balance.sql`**
```sql
ALTER TABLE users
  ADD COLUMN withdrawable_balance DECIMAL(14,2) NOT NULL DEFAULT 0.00
  AFTER ewallet_balance;
```

### 2.3 Fresh Install (`install.sql`)

Add `withdrawable_balance` to the `CREATE TABLE users` definition right after `ewallet_balance`.

---

## 3. Credit Rules (When Money Comes In)

### 3.1 Withdrawable Credits (Both columns increase)

These credit types increment **both** `ewallet_balance` AND `withdrawable_balance`:

| Source | Model/Method | Change |
|--------|-------------|--------|
| Pairing Bonus | `Commission::creditPairing()` | `ewallet_balance += bonus`, `withdrawable_balance += bonus` |
| Direct Referral | `Commission::creditDirect()` | `ewallet_balance += bonus`, `withdrawable_balance += bonus` |
| Indirect Referral | `Commission::creditIndirect()` | `ewallet_balance += bonus`, `withdrawable_balance += bonus` |
| Daily Fixed Income | `DailyFixedIncome::processDailyPayout()` | `ewallet_balance += dfi`, `withdrawable_balance += dfi` |

### 3.2 Non-Withdrawable Credits (Only total increases)

These credit types increment **only** `ewallet_balance`:

| Source | Model/Method | Change |
|--------|-------------|--------|
| Member Transfer (recipient) | `Ewallet::transfer()` | `ewallet_balance += amount` only |
| Admin Top-Up (recipient) | `Ewallet::adminTopUp()` | `ewallet_balance += amount` only |

---

## 4. Debit Rules (When Money Goes Out)

### 4.1 Payout Request (Withdrawable Only)

**File:** `controllers/MemberController::requestPayout()` or `models/Payout.php`

A member can only request payout up to their `withdrawable_balance`.

```php
$maxPayout = (float) $user['withdrawable_balance'];
if ($amount > $maxPayout) {
    return ['ok' => false, 'error' => 'Withdrawable balance insufficient.'];
}

// Debit both columns
UPDATE users 
SET ewallet_balance = ewallet_balance - :amount,
    withdrawable_balance = withdrawable_balance - :amount
WHERE id = :id;
```

**Why both?** Payout is a true cash-out. The money leaves the platform entirely.

### 4.2 Internal Spending (Both Buckets, Non-Withdrawable Spent First)

Internal transactions include:
- Reactivation fee
- Transfer out (sending money to another member)
- Any future internal feature

**Rule:** Spend from the **non-withdrawable bucket first**. Only dip into withdrawable if non-withdrawable is insufficient.

```php
function debitInternal(int $userId, float $amount): bool
{
    $pdo = db();
    $st = $pdo->prepare('SELECT ewallet_balance, withdrawable_balance FROM users WHERE id = ? FOR UPDATE');
    $st->execute([$userId]);
    $row = $st->fetch();

    $total = (float) $row['ewallet_balance'];
    $withdrawable = (float) $row['withdrawable_balance'];
    $nonWithdrawable = $total - $withdrawable;

    if ($total < $amount) return false; // not enough total

    // Spend non-withdrawable first, then withdrawable
    $fromNonWithdrawable = min($amount, $nonWithdrawable);
    $fromWithdrawable = $amount - $fromNonWithdrawable;

    $pdo->prepare("
        UPDATE users
        SET ewallet_balance = ewallet_balance - :amt,
            withdrawable_balance = withdrawable_balance - :wamt
        WHERE id = :id
    ")->execute([':amt' => $amount, ':wamt' => $fromWithdrawable, ':id' => $userId]);

    return true;
}
```

**Example:**
- User has ₱500 total: ₱300 withdrawable + ₱200 non-withdrawable
- Spends ₱250 on reactivation
- ₱200 comes from non-withdrawable, ₱50 comes from withdrawable
- New balances: total ₱250, withdrawable ₱250, non-withdrawable ₱0

**This preserves earned money as long as possible.**

### 4.3 Transfer Out (Sender)

When a member sends money to another member:
- Debit sender using internal rule (non-withdrawable first)
- Credit recipient as **non-withdrawable** (since transfers are non-withdrawable)

---

## 5. Member UI — Clear Distinction

### 5.1 Dashboard Balance Card

Replace the single balance display with a split view:

```
┌─────────────────────────────────────────────┐
│  AVAILABLE BALANCE                           │
│  ₱1,000.00                                   │
│                                              │
│  ┌────────────────┐  ┌────────────────────┐ │
│  │ ₱700 Withdraw  │  │ ₱300 Non-Withdraw  │ │
│  │    ✅ Can cash │  │    🔒 Platform use │ │
│  │       out      │  │          only      │ │
│  └────────────────┘  └────────────────────┘ │
└─────────────────────────────────────────────┘
```

### 5.2 Payout Page

The payout form must explicitly show the **withdrawable limit**:

```
┌─────────────────────────────────────────────┐
│  REQUEST PAYOUT                              │
│                                              │
│  Withdrawable:  ₱700.00  ← max you can cash │
│  Non-Withdraw:  ₱300.00  ← internal use     │
│                                              │
│  Amount: [________]                         │
│  Method: [GCash ▼]                          │
│                                              │
│  [ Request Payout ]                         │
└─────────────────────────────────────────────┘
```

**Validation:** If amount > `withdrawable_balance`, block with:  
*"You can only withdraw ₱700.00. The remaining ₱300.00 is non-withdrawable platform credit."*

### 5.3 Send Money Page

Show total balance (both buckets combined) since transfers are internal:

```
┌─────────────────────────────────────────────┐
│  AVAILABLE BALANCE                           │
│  ₱1,000.00  (₱700 withdrawable + ₱300      │
│             non-withdrawable)                │
└─────────────────────────────────────────────┘
```

### 5.4 Reactivate Page

Show total balance + a small note:

```
Your Balance: ₱1,000.00
(₱700 withdrawable + ₱300 non-withdrawable)

Reactivation Fee: ₱500.00
```

---

## 6. Admin UI — Monitor & User View

### 6.1 Admin User View

In the **Profile** card or a new row in the info table:

```
E-Wallet Balance     ₱1,000.00
├─ Withdrawable      ₱700.00 ✅
└─ Non-Withdrawable  ₱300.00 🔒
```

### 6.2 Admin E-Wallet Monitor

Add a stat card:
- **Total Withdrawable in System** — SUM(withdrawable_balance) across all members
- **Total Non-Withdrawable in System** — SUM(ewallet_balance - withdrawable_balance)

---

## 7. Files to Modify

| File | Change |
|------|--------|
| `migrations/007_add_withdrawable_balance.sql` | New migration |
| `install.sql` | Add `withdrawable_balance` to `CREATE TABLE users` |
| `models/Ewallet.php` | Update `credit()` to accept `bool $withdrawable = true`. Update `debit()` to reduce `withdrawable_balance` for payout debits. Add `debitInternal()`. |
| `models/Commission.php` | Ensure commission credits use `Ewallet::credit(..., withdrawable: true)` |
| `core/DailyFixedIncome.php` | DFI payout credits use `Ewallet::credit(..., withdrawable: true)` |
| `core/Reactivation.php` | Reactivation debits use `Ewallet::debitInternal()` (non-withdrawable first) |
| `models/Ewallet.php` `transfer()` | Sender debit uses `debitInternal()`, recipient credit uses `credit(..., withdrawable: false)` |
| `models/Ewallet.php` `adminTopUp()` | Recipient credit uses `credit(..., withdrawable: false)` |
| `models/Payout.php` or `MemberController::requestPayout()` | Validate against `withdrawable_balance`, debit both columns |
| `views/member/dashboard.php` | Show split balance card |
| `views/member/payout.php` | Show withdrawable limit, validate against it |
| `views/member/ewallet_transfer.php` | Show total balance with sub-line |
| `views/member/cap_status.php` or `views/member/reactivate.php` | Show total balance note |
| `views/admin/user_view.php` | Show split balance in profile table |
| `views/admin/ewallet_monitor.php` | Add withdrawable/non-withdrawable stat cards |
| `reset.php` | Reset `withdrawable_balance = 0` alongside `ewallet_balance = 0` |

---

## 8. Data Integrity Guardrails

### 8.1 Invariant Check

A cron or background check ensures:
```sql
SELECT id, username 
FROM users 
WHERE withdrawable_balance > ewallet_balance 
   OR withdrawable_balance < 0;
```
**Should always return 0 rows.** If not, data is corrupted.

### 8.2 Atomic Updates

All credit/debit operations must happen in a single `UPDATE` statement or within a transaction. Never read balance, compute in PHP, then write — that's a race condition.

---

## 9. Edge Cases & Decisions

| Situation | Decision |
|-----------|----------|
| Member has ₱0 non-withdrawable, spends ₱100 internally | All ₱100 comes from withdrawable. Both columns reduce by ₱100. |
| Member has ₱200 non-withdrawable, ₱0 withdrawable, tries to request ₱50 payout | Blocked. `withdrawable_balance = 0`. |
| Admin transfers to member | Recipient gets non-withdrawable credit. Admin sender: if admin has no non-withdrawable, the debit comes from their withdrawable. |
| Refund / reversal of a transfer | Complex. Not in scope for v1. Admin can top-up to correct if needed. |
| Member receives transfer, then earns commission | `ewallet_balance` = transfer + commission. `withdrawable_balance` = commission only. |

---

## 10. QA Test Plan (Brief)

1. Member with ₱0 balance earns ₱100 pairing → `withdrawable_balance = ₱100`, `ewallet_balance = ₱100`
2. Admin tops up ₱50 → `ewallet_balance = ₱150`, `withdrawable_balance = ₱100`
3. Member requests ₱120 payout → **Blocked** (max ₱100 withdrawable)
4. Member requests ₱80 payout → **OK** → `ewallet_balance = ₱70`, `withdrawable_balance = ₱20`
5. Member reactivates for ₱60 → **OK** → `ewallet_balance = ₱10`, `withdrawable_balance = ₱10` (spent ₱50 non-withdrawable + ₱10 withdrawable)
6. Dashboard shows correct split: Total ₱10, Withdrawable ₱10, Non-Withdrawable ₱0
