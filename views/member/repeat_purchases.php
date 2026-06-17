<?php

/**
 * @file   views/member/repeat_purchases.php
 * @brief  Member repeat-purchase catalog and history (Phase 5)
 */
?>
<?php $pageTitle = 'Repeat Purchases'; ?>
<?php require 'views/partials/head.php'; ?>
<style>
  .product-card-img {
    height: 160px;
    object-fit: cover;
    border-top-left-radius: var(--bs-card-border-radius, .375rem);
    border-top-right-radius: var(--bs-card-border-radius, .375rem);
    border-bottom: 1px solid #f1f5f9;
    background: #f8fafc;
    cursor: pointer;
    transition: opacity .2s ease;
  }
  .product-card-img:hover {
    opacity: .92;
  }
  .product-card-img-placeholder {
    height: 160px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f8fafc;
    border-top-left-radius: var(--bs-card-border-radius, .375rem);
    border-top-right-radius: var(--bs-card-border-radius, .375rem);
    border-bottom: 1px solid #f1f5f9;
    font-size: 3rem;
    cursor: pointer;
  }
  .product-thumb-xs {
    width: 40px;
    height: 40px;
    object-fit: cover;
    border-radius: 6px;
    border: 1px solid #e5e7eb;
  }
  .product-thumb-placeholder {
    width: 40px;
    height: 40px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #f8fafc;
    border-radius: 6px;
    border: 1px solid #e5e7eb;
    font-size: 1.1rem;
  }
</style>
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
              <?php
                $imgFull = !empty($p['image_url']) ? APP_URL . '/uploads/' . e($p['image_url']) : '';
                $priceFmt = fmt_money((float)$p['price']);
                $pvFmt = number_format((float)$p['pv_value'], 2);
              ?>
              <?php if (!empty($p['image_url'])): ?>
                <img src="<?= $imgFull ?>" alt="<?= e($p['name']) ?>"
                     class="product-card-img product-image-trigger" loading="lazy"
                     data-name="<?= e($p['name']) ?>"
                     data-image="<?= $imgFull ?>"
                     data-short="<?= e($p['short_description'] ?? '') ?>"
                     data-desc="<?= e($p['description'] ?? '') ?>"
                     data-price="<?= e($priceFmt) ?>"
                     data-pv="<?= e($pvFmt) ?>">
              <?php else: ?>
                <div class="product-card-img-placeholder product-image-trigger"
                     data-name="<?= e($p['name']) ?>"
                     data-image=""
                     data-short="<?= e($p['short_description'] ?? '') ?>"
                     data-desc="<?= e($p['description'] ?? '') ?>"
                     data-price="<?= e($priceFmt) ?>"
                     data-pv="<?= e($pvFmt) ?>">🛍️</div>
              <?php endif; ?>
              <div class="card-body">
                <h5 class="card-title"><?= e($p['name']) ?></h5>
                <?php if (!empty($p['short_description'])): ?>
                  <p class="text-muted mb-2" style="font-size:.8rem; line-height:1.35;"><?= e($p['short_description']) ?></p>
                <?php endif; ?>
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
      <div class="card-header d-flex justify-content-between align-items-center">
        <span class="card-title">🛒 Purchase History</span>
        <?php require 'views/partials/rows_per_page.php'; ?>
      </div>
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
                  <td style="padding-left:1.25rem;">
                    <div class="d-flex align-items-center gap-2">
                      <?php if (!empty($h['product_image'])): ?>
                        <a href="<?= APP_URL ?>/uploads/<?= e($h['product_image']) ?>" target="_blank" rel="noopener">
                          <img src="<?= APP_URL ?>/uploads/<?= e($h['product_image']) ?>" alt="<?= e($h['product_name']) ?>" class="product-thumb-xs" loading="lazy">
                        </a>
                      <?php else: ?>
                        <div class="product-thumb-placeholder">🛍️</div>
                      <?php endif; ?>
                      <span><?= e($h['product_name']) ?></span>
                    </div>
                  </td>
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

    <?php if (!empty($history['total_pages']) && $history['total_pages'] > 1): ?>
      <div class="mt-3">
        <?= pagination_links($history, APP_URL . '/?page=repeat_purchases&per_page=' . per_page()) ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Product Detail Modal -->
<div class="modal fade" id="productDetailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="productModalName">Product</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <img id="productModalImg" src="" alt="" class="img-fluid rounded mb-3 d-none" style="max-height:60vh; width:auto; display:block; margin:0 auto; border:1px solid #f1f5f9;">
        <div id="productModalNoImage" class="text-center py-5 mb-3 rounded bg-light d-none" style="font-size:4rem;">🛍️</div>
        <div class="d-flex justify-content-between mb-2">
          <span class="text-muted">Price</span>
          <strong class="font-mono" id="productModalPrice"></strong>
        </div>
        <div class="d-flex justify-content-between mb-3">
          <span class="text-muted">PV Value</span>
          <strong class="font-mono text-success" id="productModalPv"></strong>
        </div>
        <h6 class="mb-2">Description</h6>
        <p class="text-muted mb-0" id="productModalDesc" style="white-space:pre-wrap; line-height:1.5;"></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script>
window.addEventListener('load', function() {
  const modalEl = document.getElementById('productDetailModal');
  if (!modalEl || typeof bootstrap === 'undefined') return;

  document.addEventListener('click', function(e) {
    const trigger = e.target.closest('.product-image-trigger');
    if (!trigger) return;

    document.getElementById('productModalName').textContent = trigger.dataset.name;
    document.getElementById('productModalPrice').textContent = trigger.dataset.price;
    document.getElementById('productModalPv').textContent = trigger.dataset.pv + ' PV';
    document.getElementById('productModalDesc').textContent = trigger.dataset.desc || 'No description available.';

    const img = document.getElementById('productModalImg');
    const noImg = document.getElementById('productModalNoImage');
    if (trigger.dataset.image) {
      img.src = trigger.dataset.image;
      img.alt = trigger.dataset.name;
      img.classList.remove('d-none');
      noImg.classList.add('d-none');
    } else {
      img.classList.add('d-none');
      noImg.classList.remove('d-none');
    }

    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
  });
});
</script>

<?php require 'views/partials/footer.php'; ?>
