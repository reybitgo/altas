# Phase 5 QA Testing Guide
## Member Dashboard & Monitoring UI

**Version:** v1.0  
**Date:** 2026-05-28  
**System:** Altas Farm MLM Binary System  
**Phase:** 5 of 6 (Member Dashboard & Monitoring UI)

---

## Table of Contents

1. [What is Phase 5 Testing?](#1-what-is-phase-5-testing)
2. [Prerequisites](#2-prerequisites)
3. [Test Environment Setup](#3-test-environment-setup)
4. [Test Cases](#4-test-cases)
   - 4.1 [Earnings Page Enhancements](#41-earnings-page-enhancements)
   - 4.2 [Cap Status Visual Timeline](#42-cap-status-visual-timeline)
   - 4.3 [DFI Calendar View](#43-dfi-calendar-view)
   - 4.4 [Dashboard Pairing Cap Widget](#44-dashboard-pairing-cap-widget)
   - 4.5 [Regression Testing](#45-regression-testing)
5. [Bug Reporting Template](#5-bug-reporting-template)
6. [Pass/Fail Criteria](#6-passfail-criteria)

---

## 1. What is Phase 5 Testing?

Phase 5 adds **visual monitoring and reporting** to the member-facing side of the system. While Phases 1–4 built the engines (capping, DFI, reactivation), Phase 5 makes them **visible and understandable** to members.

### What's New in Phase 5

| Component | What It Does | Where to Test |
|-----------|-------------|-------------|
| **Earnings Page v2** | Now shows DFI earnings + cap impact per row | `/?page=earnings` |
| **Cap Status Timeline** | Visual timeline of member's earnings journey | `/?page=cap_status` |
| **DFI Calendar** | Month-by-month calendar showing DFI paid/blocked days | `/?page=dfi_history` |
| **Dashboard Enhancement** | Pairing cap widget now shows lifetime cap context | `/?page=dashboard` |

### What's NOT in Phase 5 (Already Tested in Earlier Phases)

- ❌ Cap engine logic — Phase 2
- ❌ DFI payout calculation — Phase 3
- ❌ Reactivation payment flow — Phase 4
- ❌ Admin monitoring pages — Phase 6

> **Rule of Thumb:** If it involves clicking "Confirm" on a reactivation or seeing DFI amounts change, the underlying engine was tested in Phases 2–4. Phase 5 only tests **how that data is displayed**.

---

## 2. Prerequisites

Before you start testing, ensure you have:

### Required Access
- [ ] Admin login credentials (username: `admin`, password: `Admin@1234`)
- [ ] At least one test member account with earnings history
- [ ] At least one test member who has received DFI payouts
- [ ] Browser access to `http://localhost/altas/`

### Phases 1–4 Must Be Complete
- [ ] `CapEngine.php` is deployed and working
- [ ] `DailyFixedIncome.php` is deployed and working
- [ ] `Reactivation.php` is deployed and working
- [ ] Member dashboard loads without errors
- [ ] Earnings page loads without errors
- [ ] DFI history page loads without errors
- [ ] Cap status page loads without errors

### Test Accounts Needed

| Account | Purpose | How to Prepare |
|---------|---------|----------------|
| `member1` | Active member with earnings | Register and trigger some pairings/direct refs |
| `member2` | Member who has received DFI | Run `php cron/midnight_reset.php` at least once |
| `member3` | Capped member | Manually cap via SQL or earn up to limit |
| `member4` | Reactivated member | Cap then reactivate via `/?page=reactivate` |

---

## 3. Test Environment Setup

### Step 1: Verify Phase 5 Files Are Deployed

Run these checks:

```bash
# Check earnings page has DFI tab
grep -n "daily_fixed_income" /path/to/site/views/member/earnings.php

# Check cap status has timeline
grep -n "timeline" /path/to/site/views/member/cap_status.php

# Check DFI history has calendar
grep -n "calendar" /path/to/site/views/member/dfi_history.php

# Check Commission history supports DFI filter
grep -n "daily_fixed_income" /path/to/site/core/Commission.php
```

**Expected Result:**
- All four grep commands return at least one match
- No "No such file or directory" errors

### Step 2: Prepare Test Data

**For DFI testing:**
```bash
# Run DFI cron manually (requires at least one active member)
php /path/to/site/cron/midnight_reset.php
```

**For capped member testing:**
```sql
-- Cap a member manually (test environment only)
UPDATE users u
JOIN packages p ON p.id = u.package_id
SET u.lifetime_earned = (p.entry_fee * p.lifetime_cap_multiplier),
    u.cap_status = 'capped',
    u.capped_at = NOW()
WHERE u.id = [member_id];
```

**For reactivated member testing:**
```sql
-- Create a reactivation record
INSERT INTO reactivations (user_id, amount_paid, previous_earned, package_id, payment_method, status, created_at)
VALUES ([member_id], 10000.00, 30000.00, 1, 'ewallet', 'completed', NOW());
```

---

## 4. Test Cases

### 4.1 Earnings Page Enhancements

**Purpose:** Verify the earnings page now includes DFI and shows cap impact.

#### Test 1.1: DFI Filter Tab Exists

**Steps:**
1. Log in as `member1` (member who has DFI earnings)
2. Click **💰 Earnings** in the sidebar
3. Look at the filter tabs below the summary cards

**Expected Result:**
- Tabs shown: **All** | **🤝 Pairing** | **👥 Direct** | **🔗 Indirect** | **📅 DFI**
- DFI tab is clickable

**Verify:**
```sql
-- Ensure member has DFI commissions
SELECT COUNT(*) FROM commissions
WHERE user_id = [member_id] AND type = 'daily_fixed_income';
-- Should return > 0
```

---

#### Test 1.2: DFI Tab Filters Correctly

**Steps:**
1. On Earnings page, click the **📅 DFI** tab

**Expected Result:**
- URL changes to `/?page=earnings&type=daily_fixed_income`
- Table shows **only** `daily_fixed_income` rows
- Each row shows type: **📅 Daily Fixed Income**
- Amount column shows green `+₱X.XX` for credited rows

**Verify:**
```sql
SELECT type, amount, status FROM commissions
WHERE user_id = [member_id] AND type = 'daily_fixed_income'
ORDER BY created_at DESC;
-- Compare with what's shown on screen
```

---

#### Test 1.3: Cap Impact Column Shows Blocked Amount

**Setup:**
Find or create a commission row with `cap_deduction > 0`:
```sql
SELECT id, type, amount, cap_deduction, status
FROM commissions
WHERE user_id = [member_id] AND cap_deduction > 0
LIMIT 1;
```

If none exist, manually create one:
```sql
INSERT INTO commissions (user_id, type, amount, cap_deduction, status, description, created_at)
VALUES ([member_id], 'direct_referral', 200.00, 300.00, 'credited', 'Test capped referral', NOW());
```

**Steps:**
1. Go to `/?page=earnings`
2. Find the row with cap deduction

**Expected Result:**
- **Cap Impact** column shows: `−₱300.00` in orange/yellow
- Hovering over the cap impact shows tooltip: "₱300.00 blocked by lifetime cap"
- **Amount** column still shows the credited amount: `+₱200.00`
- **Status** column shows: **Credited**

---

#### Test 1.4: Cap Impact Column Shows Dash for Normal Earnings

**Steps:**
1. Find a normal commission row (one where cap was not involved)

**Expected Result:**
- **Cap Impact** column shows: `—` (grey dash)
- No tooltip on hover

---

#### Test 1.5: Summary Cards Include DFI

**Steps:**
1. Go to `/?page=earnings`
2. Look at the 5 summary cards at the top

**Expected Result:**
- 5 cards are visible:
  1. **Total Earned**
  2. **Pairing Bonuses**
  3. **Direct Referral**
  4. **Indirect Referral**
  5. **DFI** (teal accent)
- DFI card shows total DFI earned

**Verify:**
```sql
SELECT COALESCE(SUM(amount), 0) AS total_dfi
FROM commissions
WHERE user_id = [member_id] AND type = 'daily_fixed_income' AND status = 'credited';
-- Should match the DFI card value
```

---

#### Test 1.6: All Tab Shows Mixed Types Including DFI

**Steps:**
1. Click the **All** tab
2. Scroll through the table

**Expected Result:**
- Table shows all commission types mixed together
- DFI rows appear with 📅 icon
- Pairing rows appear with 🤝 icon
- Direct rows appear with 👥 icon
- Indirect rows appear with 🔗 icon + level number
- All rows sorted by date (newest first)

---

### 4.2 Cap Status Visual Timeline

**Purpose:** Verify the cap status page shows a visual earnings timeline.

#### Test 2.1: Timeline Shows All Events

**Setup:** Use `member4` (reactivated member with multiple earnings)

**Steps:**
1. Log in as `member4`
2. Click **🛡️ Lifetime Cap** in the sidebar (or go to `/?page=cap_status`)
3. Scroll down to the **📈 Earnings Timeline** card

**Expected Result:**
- Timeline is visible with vertical line and dots
- Events shown in chronological order:
  - 🔵 **Account Created** — shows join date
  - 🟢 **🤝 Pairing Bonuses** — shows amount (if any)
  - 🟡 **👥 Direct Referrals** — shows amount (if any)
  - 🟣 **🔗 Indirect Referrals** — shows amount (if any)
  - 🔵 **📅 Daily Fixed Income** — shows amount (if any)
  - 🔴 **⚠️ Lifetime Cap Reached** — shows cap date + amount (if capped)
  - 🔵 **🔄 Reactivated** — shows reactivation date + fee (if reactivated)
  - **Current State** — shows active/capped/perminact

---

#### Test 2.2: Timeline Hides Zero-Earning Types

**Steps:**
1. Find any member and check what they actually have. Run this query:
```sql
SELECT 
    u.id,
    u.username,
    COALESCE(SUM(CASE WHEN c.type='pairing' AND c.status='credited' THEN c.amount END),0) AS pairing,
    COALESCE(SUM(CASE WHEN c.type='direct_referral' AND c.status='credited' THEN c.amount END),0) AS direct,
    COALESCE(SUM(CASE WHEN c.type='indirect_referral' AND c.status='credited' THEN c.amount END),0) AS indirect,
    COALESCE(SUM(CASE WHEN c.type='daily_fixed_income' AND c.status='credited' THEN c.amount END),0) AS dfi
FROM users u
LEFT JOIN commissions c ON c.user_id = u.id AND c.status = 'credited'
WHERE u.role = 'member'
GROUP BY u.id, u.username
ORDER BY pairing + direct + indirect + dfi DESC
LIMIT 5;
```
> ⚠️ **Reality check:** In a binary system, registering even one leg triggers **both direct referral (to sponsor) AND indirect referral (to upline)**. The minimum realistic profile is `direct > 0` and `indirect > 0`. Pairing and DFI depend on the tree state and cron runs.
>
> So instead of hunting for a unicorn account, **pick any member**, note which types are zero, and confirm those are hidden in the timeline.

2. Go to `/?page=cap_status` as that member
3. Compare the timeline against the SQL output

**Expected Result:**
- Timeline shows **Account Created** and **Current State** for every member
- For each commission type:
  - If amount > 0 in SQL → the type **IS shown** in the timeline
  - If amount = 0 in SQL → the type **IS NOT shown** in the timeline
- Example: if SQL shows `pairing=2000, direct=500, indirect=300, dfi=0`, the timeline shows:
  - Account Created → 🤝 Pairing Bonuses → 👥 Direct Referrals → 🔗 Indirect Referrals → Current State
  - 📅 DFI is **absent** (because `dfi=0`)

---

#### Test 2.3: Timeline Shows Capped Event for Capped Members

**Setup:** Use `member3` (capped member)

**Steps:**
1. Go to `/?page=cap_status` as capped member

**Expected Result:**
- Timeline shows red dot: **⚠️ Lifetime Cap Reached**
- Shows date and amount: e.g. "Feb 20, 2026 — ₱30,000.00 / ₱30,000.00"
- Current state shows: **⚠️ Capped — Reactivation Required**

---

#### Test 2.4: Timeline Shows Reactivation for Reactivated Members

**Setup:** Use `member4` (reactivated member)

**Steps:**
1. Go to `/?page=cap_status` as reactivated member

**Expected Result:**
- Timeline shows blue dot: **🔄 Reactivated**
- Shows date and fee: e.g. "Feb 21, 2026 — Fee: ₱10,000.00 via ewallet"
- Current state shows: **✅ Currently Active**

---

#### Test 2.5: Timeline Shows Correct Current State for Permanently Inactive

**Setup:**
```sql
UPDATE users SET cap_status = 'perminact' WHERE id = [member_id];
```

**Steps:**
1. Go to `/?page=cap_status` as perminact member

**Expected Result:**
- Timeline shows red capped event
- Current state shows: **⛔ Permanently Inactive**
- Current state dot is grey

---

### 4.3 DFI Calendar View

**Purpose:** Verify the DFI history page shows a month calendar with day icons.

#### Test 3.1: Calendar Shows Two Months

**Steps:**
1. Log in as `member2` (member with DFI history)
2. Click **📅 DFI History** in the sidebar
3. Scroll down to the **🗓️ DFI Calendar** card

**Expected Result:**
- Two month calendars are visible side by side (current month + previous month)
- Each calendar has a title: e.g. **May 2026**, **April 2026**
- Days of week headers: Su Mo Tu We Th Fr Sa
- Days are in a 7-column grid

---

#### Test 3.2: Paid Days Show Green with Checkmark

**Setup:** Ensure member has DFI payouts on specific days.

**Steps:**
1. Look at the calendar for days when DFI was paid

**Expected Result:**
- Paid days have **green background**
- Day number is visible
- Small ✅ icon appears below the day number
- Hover shows tooltip: "DFI paid: ₱100.00"

**Verify:**
```sql
SELECT DATE(created_at) AS day, amount
FROM daily_fixed_income_log
WHERE user_id = [member_id]
ORDER BY created_at DESC;
-- Cross-check with calendar green days
```

---

#### Test 3.3: Blocked Days Show Red with Cross

**Setup:** Manually create a blocked DFI record:
```sql
INSERT INTO daily_fixed_income_log (user_id, amount, day_number, cap_status_at_payout, created_at)
VALUES ([member_id], 0.00, 1, 'capped', '2026-05-15 00:00:00');
```

**Steps:**
1. Look at May 15 on the calendar

**Expected Result:**
- Day has **red background**
- Small ⛔ icon appears below the day number
- Hover shows tooltip: "Blocked by cap"

---

#### Test 3.4: Future Days Are Faded

**Steps:**
1. Look at days in the current month that are in the future (after today)

**Expected Result:**
- Future days are **faded/greyed out**
- No background color
- No icon

---

#### Test 3.5: Legend is Visible

**Steps:**
1. Look below the calendars

**Expected Result:**
- Legend shows:
  - ✅ DFI Paid
  - ⛔ Blocked by Cap
  - ⏸️ Paused (capped)

---

#### Test 3.6: Calendar Handles Empty Months

**Setup:** Use a brand new member with no DFI history

**Steps:**
1. Log in as new member
2. Go to `/?page=dfi_history`

**Expected Result:**
- Calendar still renders for current month + previous month
- All days are plain (no green/red backgrounds)
- Payout log shows "No DFI payouts yet."

---

### 4.4 Dashboard Pairing Cap Widget

**Purpose:** Verify the dashboard pairing cap widget now shows lifetime cap context.

#### Test 4.1: Lifetime Cap Context Visible

**Steps:**
1. Log in as any member
2. Look at the **🎯 Today's Pairing Cap** widget
3. Scroll to the bottom of the widget

**Expected Result:**
- Below "Earned today" row, there is a new line:
  > "Lifetime cap: ₱X / ₱Y  View →"
- Amounts match the member's actual lifetime earned / lifetime cap
- "View →" link goes to `/?page=cap_status`

---

#### Test 4.2: Daily Cap Still Works Normally

**Steps:**
1. Register members to trigger pairs
2. Watch the daily pairing cap widget

**Expected Result:**
- Daily cap bar still fills correctly
- "X earned today" still updates
- "Y remaining" still counts down
- "Earned today" amount still shows correctly
- Daily cap reached warning still appears when cap is hit

---

### 4.5 Regression Testing

**Purpose:** Ensure existing features still work after Phase 5 deployment.

#### Test 5.1: Earnings Page Loads Without Errors

**Steps:**
1. Log in as any member
2. Go to `/?page=earnings`
3. Click each tab: All, Pairing, Direct, Indirect, DFI

**Expected Result:**
- All tabs load without PHP errors
- No JavaScript errors in browser console (F12 → Console)
- Table renders correctly for each filter

---

#### Test 5.2: Cap Status Page Loads Without Errors

**Steps:**
1. Go to `/?page=cap_status`
2. Check for active, capped, and perminact members

**Expected Result:**
- Page loads for all cap statuses
- Progress bar renders correctly
- Timeline renders correctly
- No PHP fatal errors

---

#### Test 5.3: DFI History Page Loads Without Errors

**Steps:**
1. Go to `/?page=dfi_history`
2. Check members with and without DFI history

**Expected Result:**
- Page loads for all members
- Calendar renders correctly
- Payout log table renders correctly
- No PHP fatal errors

---

#### Test 5.4: Dashboard Loads Without Errors

**Steps:**
1. Go to `/?page=dashboard`

**Expected Result:**
- All widgets render: KPI cards, Cap widget, DFI widget, Pairing cap, Binary legs, Recent activity
- No PHP errors
- No JavaScript console errors

---

#### Test 5.5: Admin Pages Still Work

**Steps:**
1. Log in as admin
2. View admin dashboard
3. View members list
4. View reactivations page
5. View DFI admin page

**Expected Result:**
- All admin pages load without errors
- No broken UI elements

---

## 5. Bug Reporting Template

When you find an issue, use this format:

```markdown
### Bug Report — Phase 5

**Tester:** [Your Name]
**Date:** [YYYY-MM-DD HH:MM]
**Test Case:** [e.g., 1.3 Cap Impact Column]
**Severity:** [Critical / High / Medium / Low]

**Steps to Reproduce:**
1. [Step 1]
2. [Step 2]
3. [Step 3]

**Expected Result:**
[What should happen]

**Actual Result:**
[What actually happened]

**Screenshots:**
[Attach if applicable]

**Browser Console Errors:**
```
[Paste any JS errors from F12 → Console]
```

**Environment:**
- Browser: [Chrome/Firefox/Safari] v[X.X]
- OS: [Windows/Mac/Linux]
```

---

## 6. Pass/Fail Criteria

### Phase 5 PASSES if ALL of these are true:

| # | Criteria | How to Verify |
|---|----------|---------------|
| 1 | DFI filter tab exists and works | Test 1.1, 1.2 |
| 2 | Cap impact column shows blocked amounts | Test 1.3 |
| 3 | Summary cards include DFI | Test 1.5 |
| 4 | Timeline shows on cap status page | Test 2.1 |
| 5 | Timeline hides zero-earning types | Test 2.2 |
| 6 | Timeline shows capped/reactivated events | Test 2.3, 2.4 |
| 7 | DFI calendar shows two months | Test 3.1 |
| 8 | Paid days show green with ✅ | Test 3.2 |
| 9 | Blocked days show red with ⛔ | Test 3.3 |
| 10 | Dashboard pairing cap shows lifetime context | Test 4.1 |
| 11 | All existing pages still load | Test 5.1–5.5 |
| 12 | No PHP fatal errors | Check error logs |

### Phase 5 FAILS if ANY of these are true:

- ❌ DFI tab missing or doesn't filter
- ❌ Cap impact column missing or shows wrong data
- ❌ Timeline doesn't render or shows wrong events
- ❌ Calendar doesn't render or shows wrong day states
- ❌ Dashboard pairing cap missing lifetime context
- ❌ Any existing page broken (won't load, throws error)
- ❌ PHP fatal errors in server logs

---

**End of Phase 5 QA Guide**
