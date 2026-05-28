<?php
/**
 * @file   views/admin/reactivations.php
 * @brief  Admin reactivation management (Phase 4)
 */
?>
<?php $pageTitle = 'Reactivation Log'; ?>
<?php require 'views/partials/head.php'; ?>
<?php require 'views/partials/sidebar_admin.php'; ?>
<div class="main-content">
  <?php require 'views/partials/topbar.php'; ?>
  <div class="page-content">
    <?= render_flash() ?>

    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
      <div>
        <h4 class="fw-800 mb-1">🔄 Reactivation Log</h4>
        <p class="text-muted mb-0" style="font-size:.8rem;">All reactivation requests and revenue</p>
      </div>
      <div class="badge bg-success-subtle text-success" style="font-size:.9rem;padding:.5em 1em;">
        💰 Total Revenue: <?= fmt_money($totalRevenue) ?>
      </div>
    </div>

    <div class="card">
      <div class="card-body p-0">
        <?php if (empty($result['data'])): ?>
          <div class="text-center py-5 text-muted">
            <div style="font-size:2rem;">📭</div>
            <p class="mt-2 mb-0" style="font-size:.85rem;">No reactivations yet.</p>
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover mb-0" style="font-size:.85rem;">
              <thead class="table-light">
                <tr>
                  <th>Date</th>
                  <th>Member</th>
                  <th>Package</th>
                  <th>Previous Earned</th>
                  <th>Fee Paid</th>
                  <th>Method</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($result['data'] as $row): ?>
                  <tr>
                    <td><?= fmt_datetime($row['created_at']) ?></td>
                    <td>
                      <strong>@<?= e($row['username']) ?></strong><br>
                      <span class="text-muted" style="font-size:.75rem;"><?= e($row['full_name'] ?? '') ?></span>
                    </td>
                    <td><?= e($row['package_name']) ?></td>
                    <td><?= fmt_money((float)$row['previous_earned']) ?></td>
                    <td class="fw-semibold text-success"><?= fmt_money((float)$row['amount_paid']) ?></td>
                    <td>
                      <span class="badge bg-secondary-subtle text-secondary" style="text-transform:uppercase;font-size:.65rem;">
                        <?= e($row['payment_method']) ?>
                      </span>
                    </td>
                    <td>
                      <?php if ($row['status'] === 'completed'): ?>
                        <span class="badge bg-success-subtle text-success">✅ Completed</span>
                      <?php elseif ($row['status'] === 'pending'): ?>
                        <span class="badge bg-warning-subtle text-warning">⏳ Pending</span>
                      <?php else: ?>
                        <span class="badge bg-danger-subtle text-danger">❌ Failed</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?= pagination_links($result, APP_URL . '/?page=admin_reactivations') ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php require 'views/partials/footer.php'; ?>
