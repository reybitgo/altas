<?php

/**
 * @file   views/admin/repeat_purchases.php
 * @brief  Admin review/approval of member repeat purchases (Phase 5)
 */
?>
<?php $pageTitle = 'Repeat Purchases'; ?>
<?php require 'views/partials/head.php'; ?>
<style>
  .product-thumb-sm {
    width: 40px;
    height: 40px;
    object-fit: cover;
    border-radius: 6px;
    border: 1px solid #e5e7eb;
  }
  .product-thumb-placeholder {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #f8fafc;
    font-size: 1.1rem;
  }
</style>
<?php require 'views/partials/sidebar_admin.php'; ?>
<div class="main-content">
  <?php require 'views/partials/topbar.php'; ?>
  <div class="page-content">
    <?= render_flash() ?>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
      <div>
        <h4 class="mb-0">Repeat Purchases</h4>
        <p class="text-muted mb-0" style="font-size:.8rem;">Review and approve member product purchases to distribute PV</p>
      </div>
      <div class="btn-group">
        <a href="<?= APP_URL ?>/?page=admin_repeat_purchases&status=pending" class="btn btn-sm <?= $status === 'pending' ? 'btn-primary' : 'btn-outline-primary' ?>">Pending</a>
        <a href="<?= APP_URL ?>/?page=admin_repeat_purchases&status=all" class="btn btn-sm <?= $status === 'all' ? 'btn-primary' : 'btn-outline-primary' ?>">All</a>
      </div>
    </div>

    <div class="card">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead style="background:#f8fafc;">
            <tr>
              <th style="padding-left:1.25rem;">Member</th>
              <th>Product</th>
              <th class="text-end">Qty</th>
              <th class="text-end">Total PV</th>
              <th class="text-end">Total Price</th>
              <th class="text-center">Status</th>
              <th class="text-center">Date</th>
              <th class="text-end" style="padding-right:1.25rem;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($result['data'])): ?>
              <tr>
                <td colspan="8" class="text-center py-5 text-muted">
                  <div style="font-size:2rem;opacity:.3;margin-bottom:.5rem;">🛒</div>
                  <div>No repeat purchases found.</div>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($result['data'] as $rp): ?>
                <tr>
                  <td style="padding-left:1.25rem;">
                    <div class="fw-semibold">@<?= e($rp['member_username']) ?></div>
                    <div class="text-muted" style="font-size:.7rem;"><?= e($rp['member_full_name'] ?? '') ?></div>
                  </td>
                  <td>
                    <div class="d-flex align-items-center gap-2">
                      <?php if (!empty($rp['product_image'])): ?>
                        <a href="<?= APP_URL ?>/uploads/<?= e($rp['product_image']) ?>" target="_blank" rel="noopener">
                          <img src="<?= APP_URL ?>/uploads/<?= e($rp['product_image']) ?>" alt="<?= e($rp['product_name']) ?>" class="product-thumb-sm" loading="lazy">
                        </a>
                      <?php else: ?>
                        <div class="product-thumb-sm product-thumb-placeholder">🛍️</div>
                      <?php endif; ?>
                      <span><?= e($rp['product_name']) ?></span>
                    </div>
                  </td>
                  <td class="text-end font-mono"><?= (int)$rp['quantity'] ?></td>
                  <td class="text-end font-mono"><?= number_format((float)$rp['total_pv'], 2) ?></td>
                  <td class="text-end font-mono"><?= fmt_money($rp['total_price']) ?></td>
                  <td class="text-center">
                    <?php if ($rp['status'] === 'pending'): ?>
                      <span class="badge bg-warning-subtle text-warning" style="font-size:.72rem;">⏳ Pending</span>
                    <?php elseif ($rp['status'] === 'approved'): ?>
                      <span class="badge bg-success-subtle text-success" style="font-size:.72rem;">✓ Approved</span>
                    <?php else: ?>
                      <span class="badge bg-secondary-subtle text-secondary" style="font-size:.72rem;">✕ Rejected</span>
                    <?php endif; ?>
                  </td>
                  <td class="text-center" style="font-size:.78rem;"><?= fmt_date($rp['created_at']) ?></td>
                  <td class="text-end" style="padding-right:1.25rem;">
                    <?php if ($rp['status'] === 'pending'): ?>
                      <form method="POST" action="<?= APP_URL ?>/?page=admin_approve_repeat_purchase" class="d-inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int)$rp['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-success me-1">Approve</button>
                      </form>
                      <form method="POST" action="<?= APP_URL ?>/?page=admin_reject_repeat_purchase" class="d-inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int)$rp['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger">Reject</button>
                      </form>
                    <?php else: ?>
                      <span class="text-muted" style="font-size:.78rem;">—</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <?php if (!empty($result['pages']) && $result['pages'] > 1): ?>
      <nav class="mt-3">
        <ul class="pagination justify-content-center">
          <?php for ($i = 1; $i <= $result['pages']; $i++): ?>
            <li class="page-item <?= $i === $result['page'] ? 'active' : '' ?>">
              <a class="page-link" href="<?= APP_URL ?>/?page=admin_repeat_purchases&status=<?= e($status) ?>&pg=<?= $i ?>"><?= $i ?></a>
            </li>
          <?php endfor; ?>
        </ul>
      </nav>
    <?php endif; ?>
  </div>
</div>

<?php require 'views/partials/footer.php'; ?>
