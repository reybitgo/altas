I'll identify all affected code from the implementation plan and update them to use `NOW()` for `capped_at` and properly set `reactivation_window_expires`. Let me trace through each location.

---

## 1. Migration SQL (`migrations/001_add_capping_schema.sql`)

**Original (lines 68-78):**

```sql
-- 6. Update existing members: set cap_status based on current earnings
UPDATE users u
  JOIN packages p ON p.id = u.package_id
  SET u.cap_status = 'capped',
      u.capped_at = u.joined_at
  WHERE u.binary_earned_this_cycle >= p.income_cap
    AND u.cap_status = 'active'
    AND u.role = 'member';
```

**Updated:**

```sql
-- 6. Update existing members: set cap_status based on current earnings
UPDATE users u
  JOIN packages p ON p.id = u.package_id
  SET u.cap_status = 'capped',
      u.capped_at = NOW(),
      u.reactivation_window_expires = DATE_ADD(NOW(), INTERVAL p.reactivation_window_days DAY)
  WHERE u.binary_earned_this_cycle >= p.income_cap
    AND u.cap_status = 'active'
    AND u.role = 'member';
```

---

## 2. `core/Commission.php` — `triggerCap()` method

**Original (Phase 4.1):**

```php
private static function triggerCap(int $userId, array $userData): void
{
    $pdo = db();
    $windowDays = (int)$userData['reactivation_window_days'] > 0
        ? (int)$userData['reactivation_window_days']
        : (int)setting('reactivation_window_default', '15');

    $windowExpires = date('Y-m-d H:i:s', strtotime("+{$windowDays} days"));

    $pdo->prepare("
        UPDATE users
        SET cap_status = 'capped',
            capped_at = NOW(),
            reactivation_window_expires = ?
        WHERE id = ?
    ")->execute([$windowExpires, $userId]);
    // ...
}
```

**Updated** (uses `DATE_ADD` consistent with SQL approach, or keep PHP `strtotime` — both work; I'll use PHP for single-row updates, SQL-style for batch):

```php
private static function triggerCap(int $userId, array $userData): void
{
    $pdo = db();
    $windowDays = (int)$userData['reactivation_window_days'] > 0
        ? (int)$userData['reactivation_window_days']
        : (int)setting('reactivation_window_default', '15');

    $pdo->prepare("
        UPDATE users
        SET cap_status = 'capped',
            capped_at = NOW(),
            reactivation_window_expires = DATE_ADD(NOW(), INTERVAL ? DAY)
        WHERE id = ?
    ")->execute([$windowDays, $userId]);
    // ...
}
```

---

## 3. `models/User.php` — `reactivate()` method

No change needed here — reactivation **clears** these fields, not sets them. Already correct:

```php
$pdo->prepare("
    UPDATE users
    SET cap_status = 'active',
        binary_earned_this_cycle = 0.00,
        pairs_paid = 0,
        pairs_flushed = 0,
        pairs_paid_today = 0,
        reactivation_count = reactivation_count + 1,
        last_reactivated_at = NOW(),
        capped_at = NULL,
        reactivation_window_expires = NULL
    WHERE id = ?
")->execute([$userId]);
```

---

## 4. `controllers/AdminController.php` — `forceReactivate()`

No change needed — already uses `NOW()` for `last_reactivated_at` and clears `capped_at`/`reactivation_window_expires`:

```php
db()->prepare("
    UPDATE users
    SET cap_status = 'active',
        binary_earned_this_cycle = 0.00,
        pairs_paid = 0,
        pairs_flushed = 0,
        pairs_paid_today = 0,
        reactivation_count = reactivation_count + 1,
        last_reactivated_at = NOW(),
        capped_at = NULL,
        reactivation_window_expires = NULL
    WHERE id = ?
")->execute([$id]);
```

---

## 5. `controllers/AdminController.php` — `extendWindow()`

**Original (Phase 8.2):**

```php
public function extendWindow(): void
{
    Auth::guard('admin');
    csrf_verify();

    $id = (int)($_POST['id'] ?? 0);
    $newExpiry = date('Y-m-d H:i:s', strtotime('+7 days'));

    db()->prepare("
        UPDATE users
        SET reactivation_window_expires = ?
        WHERE id = ? AND cap_status = 'capped'
    ")->execute([$newExpiry, $id]);
    // ...
}
```

**Updated** (uses `DATE_ADD` for consistency):

```php
public function extendWindow(): void
{
    Auth::guard('admin');
    csrf_verify();

    $id = (int)($_POST['id'] ?? 0);

    db()->prepare("
        UPDATE users
        SET reactivation_window_expires = DATE_ADD(reactivation_window_expires, INTERVAL 7 DAY)
        WHERE id = ? AND cap_status = 'capped'
    ")->execute([$id]);

    flash('success', 'Reactivation window extended by 7 days.');
    redirect("/?page=admin_user_view&id={$id}");
}
```

---

## 6. `cron/process_capping.php`

**Original (Phase 9.1):**

```php
// Process expired windows
$expired = User::processExpiredWindows();
```

The `User::processExpiredWindows()` method (in `models/User.php`) is correct as-is — it only updates `cap_status = 'perminact'` and doesn't touch `capped_at`. No change needed.

---

## Summary of Changes

| File                                    | Location         | Change                                                                                  |
| --------------------------------------- | ---------------- | --------------------------------------------------------------------------------------- |
| `migrations/001_add_capping_schema.sql` | Backfill query   | `capped_at = NOW()`, added `reactivation_window_expires = DATE_ADD(...)`                |
| `core/Commission.php`                   | `triggerCap()`   | `capped_at = NOW()` + `DATE_ADD(NOW(), INTERVAL ? DAY)`                                 |
| `controllers/AdminController.php`       | `extendWindow()` | Uses `DATE_ADD(reactivation_window_expires, INTERVAL 7 DAY)` instead of PHP `strtotime` |

All other locations either already used `NOW()` correctly or clear the fields entirely during reactivation.
