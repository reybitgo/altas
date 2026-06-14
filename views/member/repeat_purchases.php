<?php

/**
 * @file   views/member/repeat_purchases.php
 * @brief  Member repeat-purchase catalog and history (Phase 5)
 */
?>
<?php $pageTitle = 'Repeat Purchases'; ?>
<?php require 'views/partials/head.php'; ?>
<?php require 'views/partials/sidebar_member.php'; ?>
<div class="main-content">
  <?php require 'views/partials/topbar.php'; ?>
  <div class="page-content">
    <?= render_flash() ?>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
      <div>
        <h4 class="mb-0">Repeat Purchases</h4>
        <p class="text-muted mb-0" style="font-size:.8rem;">Buy products to earn Personal PV and contribute Group/Binary PV</p>
      </div>
    </div>

    <?php if (!empty($products)): ?>
      <div class="row g-3 mb-4">
        <?php foreach ($products as $p): ?>
          <div class="col-12 col-md-6 col-lg-4">
            <div class="card h-100">
              <div class="card-body">
                <h5 class="card-title"><?= e($p['name']) ?></h5>
                <div class="d-flex justify-content-between mb-2">
                  <span class="text-muted" style="font-size:.8rem;">Price</span>
                  <strong class="font-mono"><?= fmt_money($p['price']) ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-3">
                  <span class="text-muted" style="font-size:.8rem;">PV Value</span>
                  <strong class="font-mono text-success"><?= number_format((float)$p['pv_value'], 2) ?> PV</strong>
                </div>
                <form method="POST" action="<?= APP_URL ?>/?page=do_repeat_purchase" class="d-flex gap-2">
                  <?= csrf_field() ?>
                  <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                  <input type="number" name="quantity" class="form-control" min="1" value="1" style="max-width:80px;">
                  <button type="submit" class="btn btn-primary flex-grow-1">Buy</button>
                </form>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="alert alert-info">No products are available for repeat purchase yet.</div>
    <?php endif; ?>

    <h5 class="mb-3">Purchase History</h5>
    <div class="card">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead style="background:#f8fafc;">
            <tr>
              <th style="padding-left:1.25rem;">Product</th>
              <th class="text-end">Qty</th>
              <th class="text-end">Total PV</th>
              <th class="text-end">Total Price</th>
              <th class="text-center">Status</th>
              <th class="text-center">Date</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($history['data'])): ?>
              <tr>
                <td colspan="6" class="text-center py-4 text-muted">No purchases yet.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($history['data'] as $h): ?>
                <tr>
                  <td style="padding-left:1.25rem;"><?= e($h['product_name']) ?></td>
                  <td class="text-end font-mono"><?= (int)$h['quantity'] ?></td>
                  <td class="text-end font-mono"><?= number_format((float)$h['total_pv'], 2) ?></td>
                  <td class="text-end font-mono"><?= fmt_money($h['total_price']) ?></td>
                  <td class="text-center">
                    <?php if ($h['status'] === 'pending'): ?>
                      <span class="badge bg-warning-subtle text-warning" style="font-size:.72rem;">⏳ Pending</span>
                    <?php elseif ($h['status'] === 'approved'): ?>
                      <span class="badge bg-success-subtle text-success" style="font-size:.72rem;">✓ Approved</span>
                    <?php else: ?>
                      <span class="badge bg-secondary-subtle text-secondary" style="font-size:.72rem;">✕ Rejected</span>
                    <?php endif; ?>
                  </td>
                  <td class="text-center" style="font-size:.78rem;"><?= fmt_date($h['created_at']) ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <?php if (!empty($history['pages']) && $history['pages'] > 1): ?>
      <nav class="mt-3">
        <ul class="pagination justify-content-center">
          <?php for ($i = 1; $i <= $history['pages']; $i++): ?>
            <li class="page-item <?= $i === $history['page'] ? 'active' : '' ?>">
              <a class="page-link" href="<?= APP_URL ?>/?page=repeat_purchases&pg=<?= $i ?>"><?= $i ?></a>
            </li>
          <?php endfor; ?>
        </ul>
      </nav>
    <?php endif; ?>
  </div>
</div>

<?php require 'views/partials/footer.php'; ?>
