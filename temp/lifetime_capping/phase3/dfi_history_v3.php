<?php
/**
 * @file   views/member/dfi_history.php
 * @brief  Member DFI payout history (Phase 3)
 */
?>
<?php $pageTitle = 'DFI History'; ?>
<?php require 'views/partials/head.php'; ?>
<?php require 'views/partials/sidebar_member.php'; ?>
<div class="main-content">
  <?php require 'views/partials/topbar.php'; ?>
  <div class="page-content">
    <?= render_flash() ?>

    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
      <div>
        <h4 class="fw-800 mb-1">📅 Daily Fixed Income History</h4>
        <p class="text-muted mb-0" style="font-size:.8rem;">Day-by-day record of your DFI payouts</p>
      </div>
      <a href="<?= APP_URL ?>/?page=dashboard" class="btn btn-outline-primary btn-sm">← Dashboard</a>
    </div>

    <!-- DFI Status Summary -->
    <div class="card mb-4">
      <div class="card-body">
        <div class="row g-3">
          <div class="col-6 col-md-3">
            <div class="text-muted" style="font-size:.72rem;font-weight:700;text-transform:uppercase;">Daily Rate</div>
            <div class="fw-700" style="font-size:1.1rem;"><?= fmt_money($status['daily_rate']) ?></div>
          </div>
          <div class="col-6 col-md-3">
            <div class="text-muted" style="font-size:.72rem;font-weight:700;text-transform:uppercase;">Days Used</div>
            <div class="fw-700" style="font-size:1.1rem;"><?= $status['days_used'] ?> / <?= $status['days_used'] + $status['days_remaining'] ?></div>
          </div>
          <div class="col-6 col-md-3">
            <div class="text-muted" style="font-size:.72rem;font-weight:700;text-transform:uppercase;">Total Earned</div>
            <div class="fw-700 text-success" style="font-size:1.1rem;"><?= fmt_money($status['total_dfi_earned']) ?></div>
          </div>
          <div class="col-6 col-md-3">
            <div class="text-muted" style="font-size:.72rem;font-weight:700;text-transform:uppercase;">Status</div>
            <div class="fw-700" style="font-size:1.1rem;">
              <?php
              $statusBadge = match ($status['status']) {
                  'active'     => '<span class="badge bg-success-subtle text-success">Active</span>',
                  'capped'     => '<span class="badge bg-warning-subtle text-warning">Capped</span>',
                  'perminact'  => '<span class="badge bg-danger-subtle text-danger">Permanent</span>',
                  'completed'  => '<span class="badge bg-info-subtle text-info">Completed</span>',
                  'paused'     => '<span class="badge bg-secondary-subtle text-secondary">Paused</span>',
                  default      => '<span class="badge bg-secondary-subtle text-secondary">Disabled</span>',
              };
              echo $statusBadge;
              ?>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- History Table -->
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span class="card-title">Payout Log</span>
        <span class="text-muted" style="font-size:.75rem;"><?= $history['total'] ?> record(s)</span>
      </div>
      <div class="card-body p-0">
        <?php if (empty($history['data'])): ?>
          <div class="text-center py-5 text-muted">
            <div style="font-size:2rem;">📭</div>
            <p class="mt-2 mb-0" style="font-size:.85rem;">No DFI payouts yet.</p>
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover mb-0" style="font-size:.85rem;">
              <thead class="table-light">
                <tr>
                  <th>Date</th>
                  <th>Day #</th>
                  <th>Amount</th>
                  <th>Cap Status</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($history['data'] as $row): ?>
                  <tr>
                    <td><?= fmt_datetime($row['created_at']) ?></td>
                    <td>Day <?= (int)$row['day_number'] ?></td>
                    <td class="fw-semibold text-success"><?= fmt_money((float)$row['amount']) ?></td>
                    <td>
                      <?php if ($row['cap_status_at_payout'] === 'active'): ?>
                        <span class="badge bg-success-subtle text-success">Active</span>
                      <?php else: ?>
                        <span class="badge bg-warning-subtle text-warning"><?= e($row['cap_status_at_payout']) ?></span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?= pagination_links($history, APP_URL . '/?page=dfi_history') ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php require 'views/partials/footer.php'; ?>
