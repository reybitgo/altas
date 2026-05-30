# Frontend Sync Plan: `frontend/index.php`

> **Goal:** Make the public landing page reflect the **actual** system settings and packages — not hardcoded copy. Remove anything that's disabled. Show everything that's enabled. Use real package data.

**Current DB State (as of 2026-05-30):**

| Setting | Value | Status |
|---------|-------|--------|
| `indirect_referral_enabled` | `0` | **OFF** |
| `dfi_enabled` | `1` | ON |
| `gcash_enabled` | `1` | ON |
| `maya_enabled` | `1` | ON |
| `min_payout` | `500` | — |
| `site_name` | `Altas Farm` | — |
| `site_tagline` | `Build Your Network. Grow Your Income.` | — |
| `usdt_gas_fee` | `0.7801` | — |
| `service_fee_usdt` | `5` | — |
| `service_fee_gcash` | `0` | — |
| `service_fee_maya` | `0` | — |

**Active Packages:**

| Package | Entry Fee | Pairing | Daily Cap | Direct Ref | DFI/day | DFI Days |
|---------|-----------|---------|-----------|------------|---------|----------|
| Starter | ₱10,000 | ₱2,000 | 3 | ₱500 | ₱100 | 90 |
| Test Pro Package | ₱20,000 | ₱3,000 | 5 | ₱1,000 | ₱250 | 120 |

**Indirect Levels (Package 1 only — currently disabled globally):**
L1=₱300, L2=₱200, L3=₱150, L4–L5=₱100, L6–L10=₱50

---

## Summary of Discrepancies

1. **Indirect Referral (Unilevel) is OFF** — but it appears in 10+ places on the page.
2. **Two active packages exist** — but the page claims "one package" and hardcodes Starter only.
3. **GCash & Maya payouts are ON** — but the page claims "USDT TRC20 — Sole Payout Method."
4. **DFI is ON** — but never mentioned on the landing page.
5. **All monetary amounts are hardcoded** — they should pull from the active package(s).
6. **Site name/tagline are hardcoded** — should use `site_name` / `site_tagline` settings.
7. **Missing trust signals** — lifetime cap, reactivation, e-wallet transfers are live features not mentioned.

---

## Proposed Changes (Section by Section)

### 0. Top-Level PHP Bootstrap

**Current:**
```php
<?php
require_once __DIR__ . '/../config/db.php';
$base     = rtrim(APP_URL, '/');
$frontend = $base . '/frontend';
?>
```

**Proposed:**
```php
<?php
require_once __DIR__ . '/../config/db.php';
$base     = rtrim(APP_URL, '/');
$frontend = $base . '/frontend';

// ── Load live settings ──
$siteName        = setting('site_name', 'AltasFarm');
$siteTagline     = setting('site_tagline', 'Build Your Network. Grow Your Income.');
$indirectEnabled = setting('indirect_referral_enabled', '1') === '1';
$dfiEnabled      = setting('dfi_enabled', '1') === '1';
$gcashEnabled    = setting('gcash_enabled', '1') === '1';
$mayaEnabled     = setting('maya_enabled', '1') === '1';
$minPayout       = (float) setting('min_payout', '500');
$usdtFee         = (float) setting('service_fee_usdt', '5');
$gcashFee        = (float) setting('service_fee_gcash', '0');
$mayaFee         = (float) setting('service_fee_maya', '0');

// ── Load active packages ──
$packages = Package::all(true); // active only
$pkgCount = count($packages);

// Pick "featured" package (lowest entry fee) for hero highlights
$featuredPkg = $packages[0] ?? null;
?>
```

> **Rationale:** All downstream conditionals depend on these variables. `Package::all(true)` already exists.

---

### 1. `<head>` — SEO / Meta

**Changes:**
- Replace hardcoded `"AltasFarm"` with `<?= e($siteName) ?>` in `<title>`, `og:title`, `twitter:title`, Schema.org `name`, etc.
- Replace description with one that matches the **actual** commission count (2 or 3 streams depending on `$indirectEnabled`).
- **If `$indirectEnabled === false`:**
  - Remove "unilevel" / "10-level" from meta keywords.
  - Adjust `og:description` to say "two commission streams" instead of "three."

---

### 2. Schema.org FAQPage JSON-LD

**Current:**
```json
"text": "AltasFarm is a closed binary referral network ... three commission streams ..."
```

**Proposed:**
Generate the FAQ array in PHP. If `$indirectEnabled` is false, the answer says:
> "... two commission streams: binary pairing and direct referral. All payouts are in USDT TRC20."

If `$indirectEnabled` is true, keep the three-stream wording.

---

### 3. FAQ Modal (`#modal-faq`)

**Affected items:**

| FAQ | Issue |
|-----|-------|
| "What is AltasFarm?" | Says "three commission streams (binary pairing, direct referral, and unilevel)" |
| "How are commissions earned?" | Lists three streams with hardcoded unilevel amounts (₱300, ₱200, ₱150, ₱100, ₱50) |

**Proposed:**
- Wrap the unilevel `<li>` in `<?php if ($indirectEnabled): ?>`.
- If false, the list shows only **Binary Pairing** and **Direct Referral**.
- Pull the actual pairing/direct amounts from `$featuredPkg['pairing_bonus']` and `$featuredPkg['direct_ref_bonus']` instead of hardcoding ₱2,000 / ₱500.
- If `$indirectEnabled` is true, pull unilevel levels from `Package::getIndirectLevels($featuredPkg['id'])` instead of hardcoding.

---

### 4. Terms of Service Modal (`#modal-tos`)

**Affected: §5 Commissions and Earning Structure**

Current:
> "Members earn through three streams: (a) Binary Pairing Bonus of ₱2,000 ... (b) Direct Referral Bonus of ₱500 ... and (c) Unilevel Bonus ..."

**Proposed:**
- Replace "three streams" with `<?= $indirectEnabled ? 'three' : 'two' ?>`.
- Remove clause (c) entirely when `$indirectEnabled === false`.
- Replace hardcoded ₱2,000 and ₱500 with `<?= fmt_money($featuredPkg['pairing_bonus']) ?>` and `<?= fmt_money($featuredPkg['direct_ref_bonus']) ?>`.
- Add DFI clause if `$dfiEnabled === true` (currently missing entirely).

---

### 5. Hero Section (`#hero`)

**Current hardcoded stats:**
```html
<div class="hero-stat-val">₱2,000</div>
<div class="hero-stat-label">Per Pair Bonus</div>
<div class="hero-stat-val">10</div>
<div class="hero-stat-label">Unilevel Levels</div>
```

**Proposed:**
```html
<div class="hero-stat-val"><?= fmt_money($featuredPkg['pairing_bonus']) ?></div>
<div class="hero-stat-label">Per Pair Bonus</div>
<?php if ($indirectEnabled): ?>
<div class="hero-stat-val">10</div>
<div class="hero-stat-label">Unilevel Levels</div>
<?php endif; ?>
```

**Hero badge (right side):**
- "Starter Entry ₱10,000" → `<?= e($featuredPkg['name']) ?> Entry <?= fmt_money($featuredPkg['entry_fee']) ?>`
- "Pair Earned ₱2,000" → `Pair Earned <?= fmt_money($featuredPkg['pairing_bonus']) ?>`
- "Daily Cap 3×" → `Daily Cap <?= $featuredPkg['daily_pair_cap'] ?>×`

---

### 6. Marquee

**Current items:**
```
10-Level Unilevel   ← REMOVE if indirect disabled
Daily Pair Bonuses  ← keep
Binary Structure    ← keep
```

**Proposed:**
```php
<?php
$marqueeItems = [
    '1,000 Members Only',
    'Real Poultry Products',
    'Instant Commissions',
    'USDT TRC20 Payouts',
    'Philippine Farms',
    'Daily Pair Bonuses',
    'Bayanihan Network',
    'Closed Community',
    'Binary Structure',
];
if ($indirectEnabled) {
    $marqueeItems[] = '10-Level Unilevel';
}
if ($dfiEnabled) {
    $marqueeItems[] = 'Daily Fixed Income';
}
?>
```

---

### 7. How It Works (`#how`)

**Step 5 — "Unilevel Royalties"**

**Proposed:**
```php
<?php if ($indirectEnabled): ?>
<div class="step-card fade-up">
  <div class="step-num">05</div>
  <div class="step-icon">🔗</div>
  <div class="step-title">Unilevel Royalties</div>
  <div class="step-desc">Generational bonuses paid 10 levels deep through your sponsor chain...</div>
</div>
<div class="step-card fade-up">
  <div class="step-num">06</div>
  ...
</div>
<?php else: ?>
<!-- Step 5 becomes Withdraw -->
<div class="step-card fade-up">
  <div class="step-num">05</div>
  <div class="step-icon">₮</div>
  <div class="step-title">Withdraw in USDT</div>
  ...
</div>
<?php endif; ?>
```

> If indirect is OFF, the step count should compress from 6 → 5 steps.

---

### 8. Compensation Plan (`#plan`)

**Current:** Always shows 3 cards (Pairing, Direct, Unilevel).

**Proposed:**
```php
<?php
$planCards = [
    ['🤝', 'Binary Pairing Bonus', $featuredPkg['pairing_bonus'],
     'Earned every time a left-right pair forms... Capped at ' . $featuredPkg['daily_pair_cap'] . ' pairs per day...'],
    ['👥', 'Direct Referral Bonus', $featuredPkg['direct_ref_bonus'],
     'Credited instantly every time someone you referred registers...'],
];
if ($indirectEnabled) {
    $maxIndirect = max(Package::getIndirectLevels($featuredPkg['id']) ?: [0]);
    $planCards[] = ['🔗', 'Unilevel Bonus', 'Up to ' . fmt_money($maxIndirect),
        'Generational bonuses 10 levels deep through your sponsor chain...'];
}
if ($dfiEnabled) {
    $planCards[] = ['📅', 'Daily Fixed Income', fmt_money($featuredPkg['daily_fixed_income']) . '/day',
        'Fixed daily payout for ' . $featuredPkg['daily_fixed_income_days'] . ' days after registration.'];
}
?>
```

**Unilevel Breakdown sub-line:**
```php
<?php if ($indirectEnabled): ?>
<div class="plan-note">
  <strong>Unilevel Breakdown:</strong>
  <?php
  $lvls = Package::getIndirectLevels($featuredPkg['id']);
  $parts = [];
  foreach ($lvls as $lvl => $amt) {
      if ($amt > 0) $parts[] = "Level $lvl " . fmt_money($amt);
  }
  echo implode(' · ', $parts);
  ?> per member registration.
</div>
<?php endif; ?>
```

---

### 9. Packages Section (`#packages`)

**Current problems:**
- Says "One Entry. No Tiers." but there are **2 active packages**.
- Hardcodes "Broiler Starter", ₱10,000, ₱2,000, ₱500, 10-level unilevel.
- Claims "USDT TRC20 — Sole Payout Method" but GCash & Maya are enabled.

**Proposed architecture:**

```php
<?php if ($pkgCount === 1): ?>
  <!-- Keep the current single-card layout but populate from $packages[0] -->
<?php else: ?>
  <!-- Multi-package grid -->
  <div class="pkg-grid">
    <?php foreach ($packages as $pkg): ?>
      <div class="pkg-card">
        <div class="pkg-badge"><?= e($pkg['name']) ?></div>
        <div class="pkg-price"><?= fmt_money($pkg['entry_fee']) ?> <small>one-time</small></div>
        <ul class="pkg-features">
          <li>₱<?= fmt_money($pkg['pairing_bonus']) ?> per binary pair · cap <?= $pkg['daily_pair_cap'] ?>/day</li>
          <li>₱<?= fmt_money($pkg['direct_ref_bonus']) ?> direct referral bonus</li>
          <?php if ($indirectEnabled): ?>
            <li>10-level unilevel generational bonuses</li>
          <?php endif; ?>
          <?php if ($dfiEnabled): ?>
            <li>₱<?= fmt_money($pkg['daily_fixed_income']) ?> daily fixed income for <?= $pkg['daily_fixed_income_days'] ?> days</li>
          <?php endif; ?>
          <li>Lifetime income cap: <?= $pkg['lifetime_cap_multiplier'] ?>× entry fee</li>
        </ul>
        <!-- Payout methods badge -->
        <div class="payout-methods">
          <?php if ($gcashEnabled): ?><span class="badge-gcash">GCash</span><?php endif; ?>
          <?php if ($mayaEnabled): ?><span class="badge-maya">Maya</span><?php endif; ?>
          <span class="badge-usdt">USDT TRC20</span>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
```

**Payout badge copy:**
Replace:
```html
<div class="usdt-badge">₮ USDT TRC20 — Sole Payout Method</div>
```

With:
```html
<div class="payout-methods">
  <span>Payout methods:</span>
  <?php if ($gcashEnabled): ?><span>GCash</span><?php endif; ?>
  <?php if ($mayaEnabled): ?><span>Maya</span><?php endif; ?>
  <span>USDT TRC20</span>
</div>
```

---

### 10. Why AltasFarm (`#why`)

**Current:** No mention of DFI, lifetime cap, or reactivation.

**Proposed additions (conditional):**

```php
<?php if ($dfiEnabled): ?>
<div class="why-item">
  <div class="why-icon">📅</div>
  <div>
    <div class="why-item-title">Daily Fixed Income</div>
    <div class="why-item-desc">Earn a fixed daily amount for up to <?= $featuredPkg['daily_fixed_income_days'] ?> days after joining — a predictable baseline on top of your network earnings.</div>
  </div>
</div>
<?php endif; ?>
```

Also add a "Lifetime Cap Protection" item:
> "Your total lifetime earnings are capped at <?= $featuredPkg['lifetime_cap_multiplier'] ?>× your entry fee. This protects the network from overextension and guarantees sustainability."

---

### 11. CTA Section

**Current:**
> "1,000 Seats. Not One More."

**Proposed:** Keep the 1,000 cap (that's a business constant), but pull the `site_name` dynamically:
> "<?= e($siteName) ?> — 1,000 Seats. Not One More."

---

### 12. Footer

**Changes:**
- Replace hardcoded "AltasFarm" with `<?= e($siteName) ?>` in footer brand, copyright, etc.
- If `$indirectEnabled === false`, remove any mention of "unilevel" or "10-level" in footer description.

---

## Settings → Frontend Mapping Reference

| Setting | Where It Should Appear |
|---------|------------------------|
| `site_name` | `<title>`, meta tags, Schema.org, nav logo text, footer brand, CTA |
| `site_tagline` | Hero subtitle or meta description |
| `indirect_referral_enabled` | Hero stats, Marquee, How It Works step 5, Plan cards, Package features, FAQ, TOS |
| `dfi_enabled` | Plan cards, Package features, Why AltasFarm section, FAQ |
| `gcash_enabled` | Payout methods badge, Trust bar, FAQ |
| `maya_enabled` | Payout methods badge, Trust bar, FAQ |
| `min_payout` | FAQ "Is there a withdrawal minimum?" |
| `service_fee_usdt` / `gcash` / `maya` | FAQ / TOS payout section |
| Package `entry_fee` | Hero badge, Package price |
| Package `pairing_bonus` | Hero stats, Plan card, Package features |
| Package `daily_pair_cap` | Hero badge, Plan card description |
| Package `direct_ref_bonus` | Plan card, Package features |
| Package `daily_fixed_income` + `daily_fixed_income_days` | Plan card, Package features, Why section |
| Package `lifetime_cap_multiplier` | Why section, Package features |

---

## Recommended PHP Helpers (add to `frontend/index.php`)

```php
<?php
// Already available via helpers.php:
// setting(), fmt_money(), e(), etc.

// Load packages
$packages = Package::all(true);
$featuredPkg = $packages[0] ?? null;

// Commission stream count for copywriting
$streamCount = 2 + ($indirectEnabled ? 1 : 0) + ($dfiEnabled ? 1 : 0);

// Build payout methods list
$payoutMethods = ['USDT TRC20'];
if ($gcashEnabled) $payoutMethods[] = 'GCash';
if ($mayaEnabled)  $payoutMethods[] = 'Maya';
$payoutMethodsText = implode(', ', $payoutMethods);
?>
```

---

## Files to Touch

| File | Action |
|------|--------|
| `frontend/index.php` | Major rewrite of all sections listed above |
| `frontend/style.css` | Minor: add `.badge-gcash`, `.badge-maya`, `.pkg-grid` styles if multi-package |

> **Scope:** This plan covers **only** `frontend/index.php`. The inner member dashboard (`views/member/dashboard.php`, etc.) already respects `indirect_referral_enabled` correctly from prior work.

---

## QA Checklist (for after implementation)

- [ ] Toggle `indirect_referral_enabled = 0` → refresh frontend → zero mentions of "unilevel", "10-level", "indirect"
- [ ] Toggle `indirect_referral_enabled = 1` → refresh frontend → unilevel appears in hero, plan, FAQ, marquee
- [ ] Toggle `dfi_enabled = 0` → DFI badge/feature disappears
- [ ] Toggle `gcash_enabled = 0` → GCash removed from payout badges
- [ ] Add a 3rd package in admin → frontend shows 3 packages
- [ ] Change `site_name` in settings → navbar and footer update
- [ ] Change `pairing_bonus` on Starter → hero stat updates

---

*Prepared for review. Do not implement until approved.*
