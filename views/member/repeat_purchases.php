<?php $pageTitle = 'Repeat Purchases'; ?>
<?php require 'views/partials/sidebar_member.php'; ?>
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
<div class="main-content">
  <?php require 'views/partials/topbar.php'; ?>
  <div class="page-content">
    <?= render_flash() ?>

    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
      <div>
        <h4 class="mb-0">Repeat Purchases</h4>
        <p class="text-muted mb-0" style="font-size:.8rem;">Buy products to earn Personal PV and contribute Group/Binary PV</p>
      </div>
    </div>

    <?php if (empty($products)): ?>
      <div class="alert alert-info">No products available at this time.</div>
    <?php else: ?>
      <div class="row g-3 mb-4">
        <?php foreach ($products as $p):
          $imgFull = !empty($p['image_url']) ? APP_URL . '/uploads/' . e($p['image_url']) : '';
          $priceFmt = fmt_money((float)$p['price']);
          $pvFmt = number_format((float)$p['pv_value'], 2);
          $available = Product::availableStock((int)$p['id']);
        ?>
          <div class="col-12 col-md-6 col-lg-4">
            <div class="card h-100">
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
              <div class="card-body d-flex flex-column">
                <h5 class="card-title"><?= e($p['name']) ?></h5>
                <?php if (!empty($p['short_description'])): ?>
                  <p class="text-muted mb-2 flex-grow-1" style="font-size:.8rem; line-height:1.35;"><?= e($p['short_description']) ?></p>
                <?php endif; ?>
                <div class="d-flex justify-content-between mb-2">
                  <span class="text-muted" style="font-size:.8rem;">Price</span>
                  <strong class="font-mono"><?= fmt_money($p['price']) ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                  <span class="text-muted" style="font-size:.8rem;">PV Value</span>
                  <strong class="font-mono text-success"><?= number_format((float)$p['pv_value'], 2) ?> PV</strong>
                </div>
                <div class="small text-muted mb-2">
                  <?php if ($available > 0): ?>
                    In stock: <?= $available ?>
                  <?php else: ?>
                    <span class="text-danger">Out of stock</span>
                  <?php endif; ?>
                </div>
                <form method="post" action="?page=add_to_cart" class="d-flex gap-2">
                  <?= csrf_field() ?>
                  <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
                  <input type="number" name="quantity" class="form-control" min="1" value="1" style="max-width:80px;" max="<?= $available > 0 ? $available : 1 ?>">
                  <button type="submit" class="btn btn-primary flex-grow-1" <?= $available < 1 ? 'disabled' : '' ?>><?= $available < 1 ? 'Out of Stock' : 'Add to Cart' ?></button>
                </form>
  </div>
</div>
          </div>
        <?php endforeach; ?>
      </div>
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
              <th style="padding-left:1.25rem;">Items</th>
              <th class="text-end">Total PV</th>
              <th class="text-end">Total Price</th>
              <th class="text-center">Payment</th>
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
              <?php foreach ($history['data'] as $h):
                $full = RepeatPurchaseOrder::findWithItems((int)$h['id']);
                $itemNames = [];
                $firstImg = '';
                foreach (($full['items'] ?? []) as $i) {
                    $itemNames[] = (int)$i['quantity'] . 'x ' . e($i['name']);
                    if (!$firstImg && !empty($i['image_url'])) {
                        $firstImg = $i['image_url'];
                    }
                }
              ?>
                <tr>
                  <td style="padding-left:1.25rem;">
                    <div class="d-flex align-items-center gap-2">
                      <?php if (!empty($firstImg)): ?>
                        <a href="<?= APP_URL ?>/uploads/<?= e($firstImg) ?>" target="_blank" rel="noopener">
                          <img src="<?= APP_URL ?>/uploads/<?= e($firstImg) ?>" alt="" class="product-thumb-xs" loading="lazy">
                        </a>
                      <?php else: ?>
                        <div class="product-thumb-placeholder">🛍️</div>
                      <?php endif; ?>
                      <span><?= e(implode(', ', $itemNames)) ?></span>
                    </div>
                  </td>
                  <td class="text-end font-mono"><?= number_format((float)$h['total_pv'], 2) ?></td>
                  <td class="text-end font-mono"><?= fmt_money($h['total_price']) ?></td>
                  <td class="text-center"><?= e($h['payment_method'] ?? '—') ?></td>
                  <td class="text-center">
                    <?php if ($h['status'] === 'pending'): ?>
                      <span class="badge bg-warning-subtle text-warning" style="font-size:.72rem;">⏳ Pending</span>
                    <?php elseif ($h['status'] === 'paid'): ?>
                      <span class="badge bg-info-subtle text-info" style="font-size:.72rem;">💳 Paid</span>
                    <?php elseif ($h['status'] === 'approved'): ?>
                      <span class="badge bg-success-subtle text-success" style="font-size:.72rem;">✓ Approved</span>
                    <?php elseif ($h['status'] === 'rejected'): ?>
                      <span class="badge bg-secondary-subtle text-secondary" style="font-size:.72rem;">✕ Rejected</span>
                    <?php else: ?>
                      <span class="badge bg-light-subtle text-muted" style="font-size:.72rem;"><?= e(ucfirst($h['status'])) ?></span>
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

<!-- Off-canvas Cart Sidebar -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="cartOffcanvas" aria-labelledby="cartOffcanvasLabel">
  <div class="offcanvas-header border-bottom">
    <h5 class="offcanvas-title" id="cartOffcanvasLabel">
      <span class="d-flex align-items-center gap-2">
        🛒 Cart
        <?php if (!empty($cartItems)): ?>
        <span class="badge bg-primary rounded-pill fs-6 fw-medium"><?= array_sum(array_column($cartItems, 'quantity')) ?></span>
        <?php endif; ?>
      </span>
    </h5>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>
  <div class="offcanvas-body d-flex flex-column p-0">
    <?php if (empty($cartItems)): ?>
    <div class="d-flex flex-column align-items-center justify-content-center flex-grow-1 text-muted gap-2 p-4">
      <span style="font-size:3rem;">🛒</span>
      <p class="mb-0 fw-medium">Your cart is empty</p>
      <small>Browse products and add items to get started.</small>
      <a href="?page=repeat_purchases" class="btn btn-outline-primary btn-sm mt-2" data-bs-dismiss="offcanvas">Browse Products</a>
    </div>
    <?php else: ?>
    <div class="flex-grow-1 overflow-auto" id="cartItemsContainer">
      <?php
        $itemTotal = 0;
        foreach ($cartItems as $item):
          $available = Product::availableStock((int)$item['product_id']);
          $subtotal = (float)$item['unit_price'] * (int)$item['quantity'];
          $itemTotal += $subtotal;
      ?>
      <div class="cart-item px-3 py-3 border-bottom" data-item-id="<?= (int)$item['id'] ?>" data-unit-price="<?= (float)$item['unit_price'] ?>" data-unit-pv="<?= (float)$item['unit_pv'] ?>">
        <div class="d-flex gap-3">
          <?php if ($item['image_url']): ?>
          <div class="flex-shrink-0">
            <img src="<?= APP_URL ?>/uploads/<?= e($item['image_url']) ?>" alt="<?= e($item['product_name']) ?>" class="rounded-2" style="width:64px;height:64px;object-fit:cover;border:1px solid #eef1f8;">
          </div>
          <?php else: ?>
          <div class="flex-shrink-0 bg-light rounded-2 d-flex align-items-center justify-content-center" style="width:64px;height:64px;border:1px solid #eef1f8;">
            <span style="font-size:1.5rem;">🛍️</span>
          </div>
          <?php endif; ?>
          <div class="flex-grow-1 min-w-0">
            <div class="d-flex justify-content-between align-items-start gap-2">
              <h6 class="mb-0 text-truncate"><?= e($item['product_name']) ?></h6>
              <button class="btn btn-sm p-0 border-0 text-muted cart-remove-btn" title="Remove" style="line-height:1;" data-item-id="<?= (int)$item['id'] ?>">✕</button>
            </div>
            <div class="small text-muted mt-1"><?= fmt_money((float)$item['unit_price']) ?> each</div>
            <div class="d-flex justify-content-between align-items-center mt-2">
              <div class="input-group input-group-sm border rounded-2" style="width:110px;">
                <button class="btn btn-sm border-0 px-2 fw-bold cart-qty-btn" type="button" data-dir="down">−</button>
                <input type="number" class="form-control border-0 text-center bg-transparent fw-semibold cart-qty-input" value="<?= (int)$item['quantity'] ?>" min="1" max="<?= $available ?>" style="font-size:.8rem;box-shadow:none;" data-item-id="<?= (int)$item['id'] ?>">
                <button class="btn btn-sm border-0 px-2 fw-bold cart-qty-btn" type="button" data-dir="up">+</button>
              </div>
              <span class="fw-semibold font-mono small cart-item-subtotal"><?= fmt_money($subtotal) ?></span>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="border-top bg-white p-3 shadow-sm" id="cartFooter">
      <div class="d-flex justify-content-between align-items-center mb-2 small text-muted">
        <span id="cartItemsLabel">Subtotal (<span id="cartItemsCount"><?= array_sum(array_column($cartItems, 'quantity')) ?></span> items)</span>
        <span class="fw-semibold font-mono" id="cartSubtotal"><?= fmt_money($itemTotal) ?></span>
      </div>
      <div class="d-flex justify-content-between align-items-center mb-3 small text-muted">
        <span>Total PV</span>
        <span class="fw-semibold font-mono text-success" id="cartTotalPv"><?= fmt_money((float)$cartTotals['total_pv']) ?></span>
      </div>
      <a href="?page=checkout" class="btn btn-primary w-100 d-flex align-items-center justify-content-between">
        <span>Proceed to Checkout</span>
        <span class="fw-bold" id="cartTotalPrice"><?= fmt_money((float)$cartTotals['total_price']) ?></span>
      </a>
      <button class="btn btn-link btn-sm text-muted w-100 mt-1 text-decoration-none" data-bs-dismiss="offcanvas">Continue Shopping</button>
    </div>
    <?php endif; ?>
  </div>
</div>

<script>
(function() {
  var cartEl = document.getElementById('cartOffcanvas');
  if (!cartEl) return;

  var csrfToken = '<?= csrf_token() ?>';

  function cartFetch(url, data, cb) {
    var fd = new FormData();
    for (var k in data) fd.append(k, data[k]);
    fetch(url, {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': csrfToken },
      body: fd
    }).then(function(r) {
      if (!r.ok) return r.json().then(function(j) { throw new Error(j.error || 'Request failed'); });
      return r.json();
    }).then(function(json) {
      cb(json);
    }).catch(function(err) {
      console.error(err);
    });
  }

  function updateFooter(totals) {
    var c = document.getElementById('cartItemsCount');
    var s = document.getElementById('cartSubtotal');
    var p = document.getElementById('cartTotalPv');
    var t = document.getElementById('cartTotalPrice');
    if (totals) {
      if (c) c.textContent = totals.total_items || 0;
      if (s) s.textContent = totals.total_price ? '₱' + parseFloat(totals.total_price).toFixed(2) : '₱0.00';
      if (p) p.textContent = totals.total_pv ? '₱' + parseFloat(totals.total_pv).toFixed(2) : '₱0.00';
      if (t) t.textContent = totals.total_price ? '₱' + parseFloat(totals.total_price).toFixed(2) : '₱0.00';
    }
  }

  function updateTopbarBadge(count) {
    var badges = document.querySelectorAll('.topbar-wrapper .badge');
    badges.forEach(function(b) {
      b.textContent = count;
      if (count < 1) b.style.display = 'none';
      else b.style.display = '';
    });
  }

  document.addEventListener('change', function(e) {
    var input = e.target.closest('.cart-qty-input');
    if (!input) return;
    var itemId = input.getAttribute('data-item-id');
    var qty = parseInt(input.value) || 1;
    var max = parseInt(input.getAttribute('max')) || 999;
    if (qty < 1) qty = 1;
    if (qty > max) qty = max;
    input.value = qty;
    cartFetch('?page=update_cart_item', { item_id: itemId, quantity: qty }, function(json) {
      if (json.totals) {
        updateFooter(json.totals);
        updateTopbarBadge(parseInt(json.totals.total_items) || 0);
      }
      var row = input.closest('.cart-item');
      if (row) {
        var price = parseFloat(row.getAttribute('data-unit-price')) || 0;
        var sub = row.querySelector('.cart-item-subtotal');
        if (sub) sub.textContent = '₱' + (price * qty).toFixed(2);
      }
    });
  });

  document.addEventListener('click', function(e) {
    var btn = e.target.closest('.cart-qty-btn');
    if (!btn) return;
    var input = btn.parentNode.querySelector('.cart-qty-input');
    if (!input) return;
    var v = parseInt(input.value) || 1;
    var max = parseInt(input.getAttribute('max')) || 999;
    if (btn.getAttribute('data-dir') === 'down') { if (v > 1) input.value = v - 1; }
    else { if (v < max) input.value = v + 1; }
    input.dispatchEvent(new Event('change', { bubbles: true }));
  });

  document.addEventListener('click', function(e) {
    var btn = e.target.closest('.cart-remove-btn');
    if (!btn) return;
    var itemId = btn.getAttribute('data-item-id');
    var row = btn.closest('.cart-item');
    cartFetch('?page=remove_cart_item', { item_id: itemId }, function(json) {
      if (row) row.remove();
      if (json.totals) updateFooter(json.totals);
      var remaining = document.querySelectorAll('.cart-item');
      if (remaining.length < 1) {
        var container = document.getElementById('cartItemsContainer');
        if (container) {
          container.innerHTML = '<div class="d-flex flex-column align-items-center justify-content-center flex-grow-1 text-muted gap-2 p-4"><span style="font-size:3rem;">🛒</span><p class="mb-0 fw-medium">Your cart is empty</p><small>Browse products and add items to get started.</small><a href="?page=repeat_purchases" class="btn btn-outline-primary btn-sm mt-2" data-bs-dismiss="offcanvas">Browse Products</a></div>';
        }
        var footer = document.getElementById('cartFooter');
        if (footer) footer.remove();
        var headerBadge = document.querySelector('#cartOffcanvas .offcanvas-title .badge');
        if (headerBadge) headerBadge.remove();
      }
      updateTopbarBadge(json.totals ? (parseInt(json.totals.total_items) || 0) : 0);
    });
  });

  if (typeof bootstrap !== 'undefined') {
    var params = new URLSearchParams(location.search);
    if (params.has('cart') && params.get('cart') === '1') {
      var offcanvas = bootstrap.Offcanvas.getOrCreateInstance(cartEl);
      offcanvas.show();
      params.delete('cart');
      var newUrl = location.pathname + '?' + params.toString() + location.hash;
      history.replaceState(null, '', newUrl.replace(/\?$/, ''));
    }
  }
})();
</script>
