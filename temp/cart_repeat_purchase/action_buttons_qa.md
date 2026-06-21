# Action Buttons Mobile Responsiveness Plan

> **Date:** June 21, 2026  
> **Context:** All admin tables with an `Actions` column show cramped, side-by-side buttons on mobile (iPhone SE 375px). The buttons need to stack vertically with proper padding and centering for a polished mobile experience. Desktop view remains unchanged.  
> **Screenshot reference:** `image.png` — admin_products page on iPhone SE showing "Edit" and "Del" buttons crammed side-by-side in a narrow Actions column.

---

## 🎯 Problem Statement

On mobile viewports (≤ 576px), admin table rows are extremely narrow. The **Actions** column contains two (or more) buttons that render horizontally side-by-side, causing:

- Buttons become unreadably small or overflow their cell
- Text is cramped (e.g., "Edit" + "Del" squeezed together)
- Touch targets are too small for comfortable tapping
- Visual alignment is broken — buttons sit at the top of the cell instead of vertically centered

**Affected pages:** `admin_products`, `admin_users`, `admin_payouts`, `admin_reactivations`, `admin_repeat_purchases`  
**Not affected:** `admin_packages` (only one action button — no stacking needed)

---

## 🏗 Proposed Solution

### 1. CSS Utility Class (Single Source of Truth)

Add a reusable `.action-buttons` utility class to `assets/css/layout.css`. This class is applied to the wrapper `<div>` around every set of action buttons in admin tables.

```css
/* Desktop: horizontal row, right-aligned, normal gap */
.action-buttons {
  display: flex;
  align-items: center;
  gap: 0.35rem;           /* ~5px between buttons */
  flex-wrap: wrap;
}

/* Mobile: stack vertically, centered, full-width buttons */
@media (max-width: 576px) {
  .action-buttons {
    flex-direction: column;
    align-items: center;    /* center buttons horizontally */
    justify-content: center; /* center vertically within the cell */
    gap: 0.5rem;            /* more breathing room when stacked */
  }
  .action-buttons > .btn,
  .action-buttons > form .btn,
  .action-buttons > a.btn {
    width: 100%;            /* buttons stretch to fill the column */
    min-width: 72px;        /* but never narrower than 72px */
    padding: 0.35rem 0.5rem; /* slightly taller for easier tapping */
    font-size: 0.75rem;
    text-align: center;
    justify-content: center; /* center icon + text inside button */
  }
  /* Forms inside action-buttons should also be full-width so buttons stretch */
  .action-buttons > form {
    display: block;
    width: 100%;
    margin: 0;
  }
}
```

**Why this approach:**
- One CSS rule, applied everywhere — no duplication
- Desktop is completely untouched (horizontal `gap: 0.35rem`)
- Mobile gets clean vertical stacking with full-width buttons
- `align-items: center` ensures buttons are horizontally centered in the narrow cell
- `justify-content: center` (on the flex container) ensures the whole button group is vertically centered in the `<td>`
- `min-width: 72px` prevents buttons from becoming too tiny on very narrow screens

---

### 2. HTML Pattern Change (Per File)

Every Actions `<td>` that currently wraps buttons in a `<div class="d-flex gap-1">` or similar must be replaced with:

```html
<td class="action-cell">
  <div class="action-buttons">
    <!-- button 1 -->
    <!-- button 2 -->
    <!-- etc. -->
  </div>
</td>
```

Additionally, add a small helper class `.action-cell` to ensure the table cell itself has proper vertical alignment and padding on mobile:

```css
.action-cell {
  vertical-align: middle;
  padding-right: 1rem;
  min-width: 80px;  /* prevents column from collapsing too small */
}

@media (max-width: 576px) {
  .action-cell {
    padding: 0.75rem 0.5rem;  /* more vertical padding on mobile */
    min-width: 64px;
  }
}
```

---

## 📁 File-by-File Implementation Plan

### File 1: `assets/css/layout.css`

**Add at the bottom of the file (after existing rules):**

```css
/* ============================================================
   ACTION BUTTONS — Mobile stacking for admin table actions
   ============================================================ */

.action-cell {
  vertical-align: middle;
  padding-right: 1rem;
  min-width: 80px;
}

.action-buttons {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  flex-wrap: wrap;
}

/* Mobile: stack vertically, centered, full-width buttons */
@media (max-width: 576px) {
  .action-cell {
    padding: 0.75rem 0.5rem;
    min-width: 64px;
  }
  .action-buttons {
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
  }
  .action-buttons > .btn,
  .action-buttons > form .btn,
  .action-buttons > a.btn {
    width: 100%;
    min-width: 72px;
    padding: 0.35rem 0.5rem;
    font-size: 0.75rem;
    text-align: center;
    justify-content: center;
  }
  .action-buttons > form {
    display: block;
    width: 100%;
    margin: 0;
  }
}
```

---

### File 2: `views/admin/products.php`

**Current code (lines ~107–113):**
```html
<td class="text-end" style="padding-right:1.25rem;">
  <a href="..." class="btn btn-sm btn-outline-primary me-1">Edit</a>
  <form method="POST" action="..." class="d-inline" onsubmit="...">
    <button type="submit" class="btn btn-sm btn-outline-danger">Del</button>
  </form>
</td>
```

**Change to:**
```html
<td class="action-cell">
  <div class="action-buttons">
    <a href="..." class="btn btn-sm btn-outline-primary">Edit</a>
    <form method="POST" action="..." onsubmit="...">
      <button type="submit" class="btn btn-sm btn-outline-danger">Del</button>
    </form>
  </div>
</td>
```

**Note:** Remove the inline `style="padding-right:1.25rem;"` from the `<td>` and the `me-1` (margin-end) from the Edit button — the `.action-buttons` gap handles spacing consistently.

---

### File 3: `views/admin/users.php`

**Current code (lines ~91–110):**
```html
<td>
  <div class="d-flex gap-1">
    <a href="..." class="btn btn-sm btn-outline-primary">View</a>
    <form method="POST" action="..." class="m-0" id="...">
      <button type="button" class="btn btn-sm ...">Suspend</button>
    </form>
  </div>
</td>
```

**Change to:**
```html
<td class="action-cell">
  <div class="action-buttons">
    <a href="..." class="btn btn-sm btn-outline-primary">View</a>
    <form method="POST" action="..." id="...">
      <button type="button" class="btn btn-sm ...">Suspend</button>
    </form>
  </div>
</td>
```

**Note:** Remove `class="m-0"` from the form — the `.action-buttons` wrapper handles margins. Remove `class="d-flex gap-1"` from the div — replaced by `.action-buttons`.

---

### File 4: `views/admin/payouts.php`

**Current code (lines ~163–173):**
```html
<td>
  <?php if ($pr['status'] === 'pending'): ?>
    <div class="d-flex gap-1 flex-wrap">
      <button class="btn btn-sm btn-success" onclick="...">✓ Approve</button>
      <button class="btn btn-sm btn-danger" onclick="...">✕ Reject</button>
    </div>
  <?php elseif ... ?>
</td>
```

**Change to:**
```html
<td class="action-cell">
  <?php if ($pr['status'] === 'pending'): ?>
    <div class="action-buttons">
      <button class="btn btn-sm btn-success" onclick="...">✓ Approve</button>
      <button class="btn btn-sm btn-danger" onclick="...">✕ Reject</button>
    </div>
  <?php elseif ... ?>
</td>
```

**Note:** Replace `class="d-flex gap-1 flex-wrap"` with `class="action-buttons"` inside the pending branch. The Approved/Processed branches already show non-button content (payment info) — those don't need the wrapper.

---

### File 5: `views/admin/reactivations.php`

**Current code (lines ~155–172):**
```html
<td>
  <?php if ($row['status'] === 'pending'): ?>
    <div class="d-flex gap-1 flex-wrap">
      <button class="btn btn-sm btn-success" onclick="...">✓ Confirm</button>
      <button class="btn btn-sm btn-danger" onclick="...">✕ Reject</button>
    </div>
  <?php elseif ... ?>
</td>
```

**Change to:**
```html
<td class="action-cell">
  <?php if ($row['status'] === 'pending'): ?>
    <div class="action-buttons">
      <button class="btn btn-sm btn-success" onclick="...">✓ Confirm</button>
      <button class="btn btn-sm btn-danger" onclick="...">✕ Reject</button>
    </div>
  <?php elseif ... ?>
</td>
```

Same pattern as payouts.

---

### File 6: `views/admin/repeat_purchases.php`

**Current code (lines ~306+, inside the loop):**
```html
<?php if ($status !== 'approved'): ?>
<td style="padding-right:1rem; min-width:140px;">
  <?php if ($rp['status'] === 'pending' || $rp['status'] === 'paid'): ?>
    <div class="d-flex gap-1 flex-wrap justify-content-end">
      <!-- forms + buttons -->
    </div>
  <?php else: ?>
    <span class="text-muted" style="font-size:.75rem;">—</span>
  <?php endif; ?>
</td>
<?php endif; ?>
```

**Change to:**
```html
<?php if ($status !== 'approved'): ?>
<td class="action-cell" style="min-width:140px;">
  <?php if ($rp['status'] === 'pending' || $rp['status'] === 'paid'): ?>
    <div class="action-buttons">
      <!-- forms + buttons (same as before) -->
    </div>
  <?php else: ?>
    <span class="text-muted" style="font-size:.75rem;">—</span>
  <?php endif; ?>
</td>
<?php endif; ?>
```

**Note:** Remove `style="padding-right:1rem;"` from the `<td>` — handled by `.action-cell`. Remove `class="d-flex gap-1 flex-wrap justify-content-end"` from the inner div — replaced by `.action-buttons`. On mobile, the `justify-content: end` doesn't matter because `flex-direction: column` with `align-items: center` takes over. On desktop, buttons are naturally left-aligned within the cell, which is fine for this table (the cell is already right-aligned via the table structure).

---

## 🎨 Visual Result (Mobile)

**Before (iPhone SE 375px):**
```
┌─────────────────────┐
│ ACTIONS             │
│ [Edit] [Del]        │  ← cramped, side by side, text barely readable
└─────────────────────┘
```

**After (iPhone SE 375px):**
```
┌─────────────────────┐
│                     │
│    [  Edit  ]       │  ← stacked, centered, full-width, comfortable tap
│    [  Del   ]       │  ← proper gap between buttons
│                     │  ← vertically centered in cell
└─────────────────────┘
```

**Desktop (unchanged):**
```
┌─────────────────────┐
│ [Edit] [Del]        │  ← horizontal, same as before
└─────────────────────┘
```

---

## 📊 QA Test Plan

### Test Environment
- Browser: Chrome or Edge with DevTools Device Toolbar
- Viewport: iPhone SE (375 × 667) and iPhone 14 Pro Max (430 × 932)
- Also test on actual mobile device if available

---

### TC-AB-01 — Products Page Actions Stack on Mobile

**Steps:**
1. Go to `http://localhost/altas/?page=admin_products`
2. Open DevTools → Toggle Device Toolbar → select **iPhone SE**
3. Look at any product row in the Actions column

**Expected Result:**
- The **Edit** and **Del** buttons are stacked vertically (one above the other)
- Both buttons are horizontally centered within the cell
- There is visible spacing (~8px) between the two buttons
- Both buttons are the same width (full width of the column, minimum 72px)
- The buttons are vertically centered within the table row (not hugging the top edge)

**Pass / Fail:**
- [ ] PASS — Buttons stack vertically, centered, with proper gap
- [ ] FAIL — Buttons still side-by-side, or misaligned, or no gap

---

### TC-AB-02 — Users Page Actions Stack on Mobile

**Steps:**
1. Go to `http://localhost/altas/?page=admin_users`
2. Open DevTools → iPhone SE
3. Look at any member row in the Actions column

**Expected Result:**
- **View** and **Suspend** (or **Unsuspend**) buttons are stacked vertically
- Buttons are centered and have proper gap between them
- Button text is fully readable (not truncated)

**Pass / Fail:**
- [ ] PASS — Buttons stack vertically with good spacing
- [ ] FAIL — Buttons side-by-side or text cut off

---

### TC-AB-03 — Payouts Page Actions Stack on Mobile

**Steps:**
1. Go to `http://localhost/altas/?page=admin_payouts`
2. Ensure there is at least one **Pending** payout
3. Open DevTools → iPhone SE
4. Look at a pending payout row in the Actions column

**Expected Result:**
- **✓ Approve** and **✕ Reject** buttons are stacked vertically
- Green Approve button is on top, red Reject button is below it
- Both buttons are centered and full-width

**Pass / Fail:**
- [ ] PASS — Approve/Reject stack vertically, centered
- [ ] FAIL — Buttons side-by-side or misaligned

---

### TC-AB-04 — Reactivations Page Actions Stack on Mobile

**Steps:**
1. Go to `http://localhost/altas/?page=admin_reactivations`
2. Ensure there is at least one **Pending** reactivation
3. Open DevTools → iPhone SE
4. Look at a pending row in the Actions column

**Expected Result:**
- **✓ Confirm** and **✕ Reject** buttons are stacked vertically, centered, with gap

**Pass / Fail:**
- [ ] PASS — Buttons stack vertically
- [ ] FAIL — Buttons side-by-side

---

### TC-AB-05 — Repeat Purchases Pending Actions Stack on Mobile

**Steps:**
1. Go to `http://localhost/altas/?page=admin_repeat_purchases&status=pending`
2. Ensure there is at least one pending order
3. Open DevTools → iPhone SE
4. Look at a pending row in the Actions column

**Expected Result:**
- **✓ Mark Paid** and **✕ Reject** buttons are stacked vertically
- Both buttons are centered and have proper gap
- The **Mark Paid** button (if disabled without proof) is still full-width and grayed out

**Pass / Fail:**
- [ ] PASS — Mark Paid + Reject stack vertically, disabled state visible
- [ ] FAIL — Buttons side-by-side or disabled state not visible

---

### TC-AB-06 — Repeat Purchases Paid Actions Stack on Mobile

**Steps:**
1. Go to `http://localhost/altas/?page=admin_repeat_purchases&status=paid`
2. Ensure there is at least one paid order
3. Open DevTools → iPhone SE

**Expected Result:**
- **✓ Approve** and **✕ Reject** buttons are stacked vertically, centered

**Pass / Fail:**
- [ ] PASS — Approve + Reject stack vertically
- [ ] FAIL — Buttons side-by-side

---

### TC-AB-07 — Desktop View Is Unchanged

**Steps:**
1. For each of the 5 pages above, switch DevTools back to **Desktop** (Responsive → 1366px or just close device toolbar)
2. Look at the Actions column

**Expected Result:**
- Buttons are side-by-side horizontally (same as before this change)
- Gap between buttons is small (~5px) and natural
- No vertical stacking occurs on desktop
- Layout is identical to the pre-change desktop view

**Pass / Fail:**
- [ ] PASS — Desktop horizontal layout unchanged
- [ ] FAIL — Desktop shows vertical stacking, or gap changed significantly

---

### TC-AB-08 — Approved Tab Has No Actions (Still Works)

**Steps:**
1. Go to `http://localhost/altas/?page=admin_repeat_purchases&status=approved`
2. Open DevTools → iPhone SE

**Expected Result:**
- The **Actions column is completely absent** (not just hidden — the `<th>` and `<td>` are not rendered)
- The table renders correctly without a broken layout
- No empty Actions column taking up space

**Pass / Fail:**
- [ ] PASS — No Actions column on Approved tab, mobile layout intact
- [ ] FAIL — Empty Actions column visible, or layout broken

---

### TC-AB-09 — Touch Target Size on Mobile

**Steps:**
1. On any of the affected pages, open DevTools → iPhone SE
2. Right-click a stacked button → Inspect
3. Check the computed height of the button element

**Expected Result:**
- Button height is at least **32px** (taller than the default `btn-sm` height of ~28px due to increased padding)
- Button width is at least **72px** (the `min-width` rule)

**Pass / Fail:**
- [ ] PASS — Buttons are ≥ 32px tall and ≥ 72px wide on mobile
- [ ] FAIL — Buttons too small for comfortable tapping

---

### TC-AB-10 — Wide Mobile Viewports (e.g., iPhone 14 Pro Max, 430px)

**Steps:**
1. On any affected page, switch DevTools to **iPhone 14 Pro Max** (430px width)
2. Check the Actions column

**Expected Result:**
- At **430px**, buttons still stack vertically (since 430px > 576px? No — 430px < 576px, so they still stack)
- Wait, 430px is LESS than 576px, so yes, they stack
- At **768px** (iPad Mini), buttons should be horizontal again (since 768px > 576px)

**Pass / Fail:**
- [ ] PASS — iPhone 14 Pro Max shows vertical stacking; iPad Mini shows horizontal
- [ ] FAIL — Wrong breakpoint behavior

---

## 📁 Summary of Files to Change

| File | Lines | Change Type |
|------|-------|-------------|
| `assets/css/layout.css` | Add at EOF | New `.action-cell` + `.action-buttons` CSS rules |
| `views/admin/products.php` | ~107–113 | Replace `<td>` + button wrapper with `.action-cell` + `.action-buttons` |
| `views/admin/users.php` | ~91–110 | Replace `<td>` + `.d-flex.gap-1` with `.action-cell` + `.action-buttons` |
| `views/admin/payouts.php` | ~163–173 | Replace inner `<div>` with `.action-buttons` |
| `views/admin/reactivations.php` | ~155–172 | Replace inner `<div>` with `.action-buttons` |
| `views/admin/repeat_purchases.php` | ~306+ | Replace `<td>` style + inner `<div>` with `.action-cell` + `.action-buttons` |

**Not changed:** `views/admin/packages.php` — only one button (Edit), no stacking needed.  
**Not changed:** `views/admin/settings.php` — no table with Actions.  
**Not changed:** Any member-facing views — no "Actions" columns with multiple buttons.

---

## ✅ Acceptance Criteria

- [ ] All 5 admin tables with multiple action buttons show **vertically stacked, centered buttons** on mobile (≤ 576px)
- [ ] Desktop view (≥ 577px) is **completely unchanged** from before
- [ ] Buttons on mobile are **≥ 32px tall** and **≥ 72px wide** for comfortable touch
- [ ] Buttons have **visible gap** between them when stacked (no touching edges)
- [ ] Buttons are **horizontally centered** within their table cell on mobile
- [ ] The entire button group is **vertically centered** within the table row on mobile
- [ ] No regression: approved tab still hides Actions column, all other existing functionality works
- [ ] All 10 QA tests (TC-AB-01 through TC-AB-10) pass

---

## 🧹 Optional Cleanup (Out of Scope for this Plan)

- Consider wrapping admin tables in a horizontal-scroll container (`overflow-x: auto`) for even narrower screens, so the entire table can be scrolled rather than squishing all columns. This is a separate enhancement and not required for this action-buttons fix.
- Consider adding `table-layout: auto` to the `<table>` on mobile so columns can shrink more gracefully. Also out of scope.
