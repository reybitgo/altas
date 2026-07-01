# Royalty Bonus — Pool/Share Model Analysis

## Current Problem

The current implementation (in `core/Royalty.php`) pays royalty bonuses as a **waterfall per-transaction** basis:

```
group_bonus  = rank%_group  × totalPv × pv_per_peso_rate
repeat_bonus = rank%_repeat × purchaseAmount
```

Drawbacks:
1. **No pool ceiling** — total payout is unbounded; every qualifying upline gets a cut of every purchase
2. **Compounds with more ranks** — as the network grows, each purchase pays more people, not less
3. **Hard to budget** — no fixed % of revenue is set aside
4. **Inconsistent with industry practice** — leadership bonuses in MLM are almost always a **fixed pool** shared among qualifiers

---

## Proposed Model: % Pool + Rank-Rate Split (per your formula)

### Core Formula (your specification)

```
Pool Value = Total Repeat Purchase Sales (monthly) × Pool Rate
```

```
Rank Share = (Pool Value / Qualifiers at Rank) × (Rank Rate / 100)
```

**Where:**
- `Pool Rate` = fixed % of monthly repeat sales set aside for royalty (e.g. 10%)
- `Rank Rate` = each rank's fixed % of the pool (all rank rates **must sum to 100**)
- Each rank's allocation is divided **equally** among all qualifiers at that rank

### Your Example Worked

**Given:**
- Monthly repeat purchase sales: **₱100,000**
- Pool rate: **10%**
- Pool value: **₱10,000**
- Qualifying supervisors this month: **10**
- Supervisor rank rate: **X%** (to be decided)

```
supervisor_share = (₱10,000 / 10) × (X / 100) = ₱1,000 × (X / 100)
```

If X = 40: `₱1,000 × 0.40 = ₱400 per supervisor`

### Full Multi-Rank Example

**Assumptions:**
- Pool: ₱10,000
- Rank rates must sum to 100%

| Rank | Rank Rate | Tier Slice | Qualifiers | Per Member |
|------|-----------|------------|------------|------------|
| Supervisor | 40% | ₱4,000 | 10 | ₱400 |
| Manager | 30% | ₱3,000 | 5 | ₱600 |
| Director | 20% | ₱2,000 | 2 | ₱1,000 |
| Chairman | 10% | ₱1,000 | 1 | ₱1,000 |
| **Total** | **100%** | **₱10,000** | | |

**Check:** 10×400 + 5×600 + 2×1000 + 1×1000 = 4,000 + 3,000 + 2,000 + 1,000 = ₱10,000 ✓

### Verification That Pool Is Fully Distributed

```
Total Paid Out = Σ [ (Pool / Count_R) × (RankRate_R / 100) × Count_R ]
               = Σ [ Pool × RankRate_R / 100 ]
               = Pool × ( Σ RankRate_R / 100 )
               = Pool × (100 / 100)
               = Pool  ✓
```

The math guarantees the entire pool is distributed as long as rank rates sum to 100.

---

## Key Design Parameter: Pool Rate

Your example uses **10%**, which is on the high side vs industry norms (1–5%). However, this depends on:

| Factor | Impact |
|--------|--------|
| Total commission budget target | Industry standard: 40–45% of wholesale revenue |
| Existing bonuses (binary, direct, indirect, unilevel, DFI) | These already consume a portion of the budget |
| Product margins | Higher margins allow higher pool rate |
| Business stage | Early stage may want lower rate, increase later |

**Recommendation:** Model the total payout % first, then set the royalty pool rate within that. If existing bonuses consume ~35%, a 10% royalty pool would push total to 45% — at the high end but acceptable.

---

## Interaction with Monthly Rank Maintenance

Your design aligns naturally:

- **Rank qualification** is checked monthly (personal PV + group PV gates)
- **Pool distribution** happens monthly based on month-end rank
- If a member fails rank maintenance at month-end, they don't qualify for that month's pool — no retroactive adjustment needed
- Each month stands alone: qualification, pool accumulation, and distribution are all scoped to the same period

---

## Implementation Outline

### Schema

```sql
CREATE TABLE IF NOT EXISTS royalty_pool (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  period_date DATE NOT NULL COMMENT 'First day of the month (YYYY-MM-01)',
  total_sales DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  pool_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  pool_rate   DECIMAL(5,2) NOT NULL COMMENT 'e.g. 10.00 for 10%',
  status      ENUM('open','closed','distributed') NOT NULL DEFAULT 'open',
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY idx_period (period_date)
) ENGINE=InnoDB;
```

### Per-Transaction Tracking

On each completed repeat purchase:
```sql
UPDATE royalty_pool
SET total_sales = total_sales + :order_total
WHERE period_date = DATE_FORMAT(NOW(), '%Y-%m-01') AND status = 'open';
```

### Monthly Cron (1st of month)

```
1. Close prior month pool → status = 'closed'
2. Compute pool_amount = total_sales × pool_rate / 100
3. If pool_amount > 0 AND qualifiers exist:
   a. For each rank (Supervisor→Chairman):
      - Count qualifiers at that rank at month-end
      - Per-member payout = (pool_amount / count) × (rank_rate / 100)
      - Insert commission + credit e-wallet for each
   b. Mark pool → status = 'distributed'
4. Open new month pool row → status = 'open'
```

### Settings Keys

| Key | Purpose |
|-----|---------|
| `royalty_enabled` | Master toggle |
| `royalty_pool_rate` | % of monthly repeat sales (e.g. `10.00`) |
| `royalty_supervisor_rate` | % of pool to supervisor tier (e.g. `40`) |
| `royalty_manager_rate` | % of pool to manager tier |
| `royalty_director_rate` | % of pool to director tier |
| `royalty_chairman_rate` | % of pool to chairman tier |
| (existing rank qualification settings remain) | |

**Constraint:** Rates must sum to 100. Validation on save in admin.

---

## Open Questions for Review

1. **Pool rate**: What % of monthly repeat purchase sales? (You suggested 10% — confirm or adjust?)
2. **Rank rates**: What % split across the 4 ranks? (Must sum to 100. Example above used 40/30/20/10.)
3. **Qualification gates**: Keep existing (3 directs + PV gate for QA, 10 directs + 5 QA legs for Supervisor, etc.)?
4. **Minimum pool threshold**: Skip distribution if pool is below a minimum (e.g. ₱500)?
5. **Capping**: Apply lifetime cap to pool distributions? (Consistent with other commission types.)
6. **CD deduction**: Apply commission-deduct to pool payouts? (Industry: yes, if CD is active.)
7. **Pro-ration**: Member qualifies mid-month — full share or prorated?
