<?php
/**
 * @file   views/member/cap_status.php
 * @brief  Member cap status detail page (Phase 3/5)
 */
?>
<?php $pageTitle = 'Lifetime Cap Status'; ?>
<?php require 'views/partials/head.php'; ?>
<?php require 'views/partials/sidebar_member.php'; ?>
<div class="main-content">
  <?php require 'views/partials/topbar.php'; ?>
  <div class="page-content">
    <?= render_flash() ?>

    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
      <div>
        <h4 class="fw-800 mb-1">🛡️ Lifetime Income Cap</h4>
        <p class="text-muted mb-0" style="font-size:.8rem;">Monitor your cap progress and earnings breakdown</p>
      </div>
      <a href="<?= APP_URL ?>/?page=dashboard" class="btn btn-outline-primary btn-sm">← Dashboard</a>
    </div>

    <!-- Cap Progress Card -->
    <div class="card mb-4">
      <div class="card-body">
        <?php
        $earned = $capStatus['lifetime_earned'];
        $cap    = $capStatus['lifetime_cap'];
        $pct    = $cap > 0 ? min(100, ($earned / $cap) * 100) : 0;
        $remaining = max(0, $cap - $earned);
        ?>
        <div class="d-flex justify-content-between align-items-end mb-2">
          <div>
            <div class="text-muted" style="font-size:.72rem;font-weight:700;text-transform:uppercase;">Progress</div>
            <div class="fw-800" style="font-size:1.5rem;"><?= fmt_money($earned) ?> <span style="font-size:.9rem;color:var(--muted);">/ <?= fmt_money($cap) ?></span></div>
          </div>
          <div class="text-end">
            <div class="fw-700" style="font-size:1.25rem;"><?= number_format($pct, 1) ?>%</div>
            <div class="text-muted" style="font-size:.75rem;"><?= fmt_money($remaining) ?> remaining</div>
          </div>
        </div>
        <div class="cap-bar-track mb-3">
          <div class="cap-bar-fill <?= $pct >= 100 ? 'full' : '' ?>" style="width:<?= $pct ?>%"></div>
        </div>
        <div class="d-flex gap-2">
          <?php if ($capStatus['cap_status'] === 'active'): ?>
            <span class="badge bg-success-subtle text-success">✅ Active — You can still earn</span>
          <?php elseif ($capStatus['cap_status'] === 'capped'): ?>
            <span class="badge bg-warning-subtle text-warning">⚠️ Capped — Reactivation required</span>
          <?php else: ?>
            <span class="badge bg-danger-subtle text-danger">⛔ Permanently Inactive</span>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Breakdown Table -->
    <div class="card">
      <div class="card-header"><span class="card-title">Earnings Breakdown</span></div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0" style="font-size:.85rem;">
            <thead class="table-light">
              <tr>
                <th>Type</th>
                <th>Amount</th>
                <th>% of Cap</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $breakdown = [
                  ['Pairing', (float)$summary['total_pairing']],
                  ['Direct Referral', (float)$summary['total_direct']],
                  ['Indirect Referral', (float)$summary['total_indirect']],
                  ['Daily Fixed Income', (float)($summary['total_dfi'] ?? 0)],
              ];
              $hasRows = false;
              foreach ($breakdown as [$label, $amount]):
                  if ($amount <= 0) continue;
                  $hasRows = true;
                  $typePct = $cap > 0 ? ($amount / $cap) * 100 : 0;
              ?>
                <tr>
                  <td><?= $label ?></td>
                  <td class="fw-semibold"><?= fmt_money($amount) ?></td>
                  <td><?= number_format($typePct, 1) ?>%</td>
                  <td><span class="badge bg-success-subtle text-success">✅ Credited</span></td>
                </tr>
              <?php endforeach; ?>
              <?php if ((float)$summary['total_cap_blocked'] > 0):
                  $hasRows = true;
              ?>
                <tr class="table-warning">
                  <td>Blocked by Cap</td>
                  <td class="fw-semibold"><?= fmt_money((float)$summary['total_cap_blocked']) ?></td>
                  <td>—</td>
                  <td><span class="badge bg-warning-subtle text-warning">⛔ Not Paid</span></td>
                </tr>
              <?php endif; ?>
              <?php if (!$hasRows): ?>
                <tr>
                  <td colspan="4" class="text-center text-muted py-4">No earnings recorded yet.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require 'views/partials/footer.php'; ?>
