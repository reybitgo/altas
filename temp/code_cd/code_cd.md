# Plan: CD Registration Codes

**Feature:** Admins can generate registration codes that automatically assign a Commission-Deduct (CD) bucket when used.  
**Date:** 2026-05-28  
**Status:** Draft — awaiting review

---

## 1. Overview

A **CD code** is exactly the same as a regular registration code, with one extra behavior:

> When a member registers using a CD code, after the account is created, the system also assigns them an active CD bucket (target = package `entry_fee`) and sets `users.cd_active = 1`.

Visually, CD codes are prefixed with `CD-` (e.g. `CD-3JW9-KBHK-AXPJ`) so admins can identify them instantly.

**No changes to referral links.** CD codes are just regular codes with a CD side effect.

---

## 2. Database Changes

### 2.1 `reg_codes` table

```sql
ALTER TABLE reg_codes
  ADD COLUMN is_cd TINYINT(1) NOT NULL DEFAULT 0;
```

Also update `install.sql` and `reset.php`.

---

## 3. Backend Changes

### 3.1 `models/Code.php`

#### `generate()` — add `$isCd` parameter

```php
public static function generate(
    int $packageId,
    int $qty,
    float $price,
    ?string $expiresAt,
    int $adminId,
    bool $isCd = false
): array {
    // ... existing loop ...
    do {
        $code = generate_code();
        if ($isCd) {
            $code = 'CD-' . $code;
        }
        $exists = $pdo->query("SELECT COUNT(*) FROM reg_codes WHERE code = '{$code}'")->fetchColumn();
    } while ($exists);

    $st->execute([$code, $packageId, $price, $expiresAt ?: null, $adminId, $isCd ? 1 : 0]);
    // ...
}
```

Update the INSERT statement to include `is_cd`:

```sql
INSERT INTO reg_codes (code, package_id, price, expires_at, created_by, is_cd)
VALUES (?, ?, ?, ?, ?, ?)
```

#### `validate()`, `all()`, `exportCSV()`

SELECT `is_cd` alongside existing columns so callers can read it.

### 3.2 `models/User.php` — `create()`

After the user is inserted and the registration code is marked used, assign CD if the code was a CD code:

```php
// Auto-assign CD if registration code was a CD code
if (!empty($data['reg_code_id'])) {
    $isCd = (int)$pdo->query(
        "SELECT is_cd FROM reg_codes WHERE id = {$data['reg_code_id']}"
    )->fetchColumn();

    if ($isCd) {
        $pkg = Package::find((int)$data['package_id']);
        try {
            CdStatus::assign($newId, (float)($pkg['entry_fee'] ?? 0), 1);
        } catch (RuntimeException $e) {
            error_log("Auto-CD assignment failed for new user {$newId}: " . $e->getMessage());
        }
    }
}
```

Place this **inside the transaction**, after the `reg_codes` UPDATE and before `$pdo->commit()`.

**Why this works:**
- Regular code registration already creates the user as `active`.
- `CdStatus::assign()` requires `status = 'active'` — satisfied.
- CD is assigned before commissions fire (which happen right after `commit()` in `create()`), so the first pairing/direct bonus is split correctly.
- If registration rolls back, the CD row rolls back with it.

### 3.3 `controllers/AdminController.php` — `generateCodes()`

```php
$isCd = !empty($_POST['is_cd']);
$generated = Code::generate($pkgId, $qty, $price, $expires ?: null, Auth::id(), $isCd);
flash('success', count($generated) . ' code(s) generated' . ($isCd ? ' (CD)' : '') . ' successfully.');
```

---

## 4. Frontend Changes

### 4.1 `views/admin/codes.php` — Generate Form

Add a CD toggle above the submit button:

```html
<div class="mb-3">
  <div class="form-check form-switch">
    <input class="form-check-input" type="checkbox" name="is_cd" id="isCdToggle" value="1">
    <label class="form-check-label" for="isCdToggle">
      ⏳ CD Code — auto-assigns Commission-Deduct on registration
    </label>
  </div>
  <div class="form-text">CD codes are prefixed with <code>CD-</code>.</div>
</div>
```

### 4.2 `views/admin/codes.php` — Code List

Add an amber CD badge next to CD codes:

```html
<span class="badge bg-warning-subtle text-warning" style="font-size:.65rem;">⏳ CD</span>
```

### 4.3 `views/admin/codes.php` — CSV Export

Add `is_cd` column to CSV header and rows.

---

## 5. Files to Touch

| File | Change |
|------|--------|
| `migrations/004_add_cd_codes.sql` | New migration |
| `install.sql` | Add `is_cd` to `reg_codes` schema |
| `reset.php` | Include `is_cd` in reset |
| `models/Code.php` | `$isCd` param, `CD-` prefix, include `is_cd` in SELECTs |
| `models/User.php` | Auto-CD assignment in `create()` |
| `controllers/AdminController.php` | Read `is_cd` from POST |
| `views/admin/codes.php` | CD toggle, badge, CSV column |

---

## 6. QA Checklist

- [ ] Generate regular codes → no `CD-` prefix
- [ ] Generate CD codes → prefixed `CD-`, badge shown
- [ ] Register with regular code → `cd_active = 0`
- [ ] Register with CD code → `cd_active = 1`, CD bucket created
- [ ] Register with CD code → first pairing/direct bonus is split to CD
- [ ] Export CSV → includes `is_cd` column

---

**Ready for review. Estimated implementation: ~10 minutes.**
