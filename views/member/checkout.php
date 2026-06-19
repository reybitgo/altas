<div class="container-fluid px-4 py-3">
  <h4 class="mb-3">Checkout</h4>

  <?php if (!empty($stockErrors)): ?>
    <div class="alert alert-danger">
      <strong>Some items are no longer available in the requested quantity:</strong>
      <ul class="mb-0 mt-1">
        <?php foreach ($stockErrors as $e): ?>
        <li><?= e($e) ?></li>
        <?php endforeach; ?>
      </ul>
      <a href="?page=repeat_purchases" class="alert-link mt-2 d-inline-block">Back to catalog</a>
    </div>
  <?php endif; ?>

  <form method="post" action="?page=place_order" enctype="multipart/form-data" id="checkoutForm">
    <?= csrf_field() ?>

    <!-- Order Summary -->
    <div class="card mb-3">
      <div class="card-header fw-bold">Order Summary</div>
      <div class="card-body p-0">
        <table class="table table-sm mb-0">
          <thead class="table-light">
            <tr><th>Product</th><th>Qty</th><th>Unit Price</th><th>Unit PV</th><th>Subtotal</th></tr>
          </thead>
          <tbody>
            <?php foreach ($items as $item): ?>
            <tr>
              <td>
                <div class="d-flex align-items-center gap-2">
                  <?php if ($item['image_url']): ?>
                  <img src="<?= APP_URL ?>/uploads/<?= e($item['image_url']) ?>" style="width:36px;height:36px;object-fit:cover;border-radius:4px;">
                  <?php endif; ?>
                  <span><?= e($item['product_name']) ?></span>
                </div>
              </td>
              <td><?= (int)$item['quantity'] ?></td>
              <td>₱<?= fmt_money((float)$item['unit_price']) ?></td>
              <td><?= fmt_money((float)$item['unit_pv']) ?></td>
              <td>₱<?= fmt_money((float)$item['quantity'] * (float)$item['unit_price']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot class="table-light fw-bold">
            <tr>
              <td colspan="3"></td>
              <td>Total PV: <?= fmt_money((float)$totals['total_pv']) ?></td>
              <td>₱<?= fmt_money((float)$totals['total_price']) ?></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>

    <!-- Binary Position -->
    <div class="card mb-3">
      <div class="card-header fw-bold">Binary Side</div>
      <div class="card-body">
        <p class="small text-muted mb-2">Choose which of your own legs receives the product PV to help balance your binary tree.</p>
        <div class="d-flex gap-3">
          <div class="form-check">
            <input class="form-check-input" type="radio" name="binary_position" id="pos_left" value="left" checked>
            <label class="form-check-label fw-medium" for="pos_left">Left</label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="binary_position" id="pos_right" value="right">
            <label class="form-check-label fw-medium" for="pos_right">Right</label>
          </div>
        </div>
      </div>
    </div>

    <!-- Payment Method -->
    <div class="card mb-3">
      <div class="card-header fw-bold">Payment Method</div>
      <div class="card-body">
        <div class="row g-2">
          <?php if ($canUseEwallet): ?>
          <div class="col-6 col-md-3">
            <label class="method-option d-block border rounded p-3 text-center" style="cursor:pointer;">
              <input type="radio" name="payment_method" value="ewallet" class="d-none" checked>
              <div class="fw-bold">E-Wallet</div>
              <div class="small text-muted">₱<?= fmt_money($ewalletBalance) ?></div>
              <?php if ((float)$totals['total_price'] > $ewalletBalance): ?>
              <div class="small text-danger mt-1">Insufficient balance</div>
              <?php endif; ?>
            </label>
          </div>
          <?php endif; ?>
          <?php foreach ($methods as $key => $label): ?>
          <div class="col-6 col-md-3">
            <label class="method-option d-block border rounded p-3 text-center" style="cursor:pointer;">
              <input type="radio" name="payment_method" value="<?= $key ?>" class="d-none"
                <?= (!$canUseEwallet && $key === array_key_first($methods)) ? 'checked' : '' ?>>
              <div class="fw-bold"><?= e($label) ?></div>
              <div class="small text-muted">Upload proof</div>
            </label>
          </div>
          <?php endforeach; ?>
        </div>

        <div id="proofUploadSection" class="mt-3" style="display:none;">
          <label class="form-label fw-medium">Upload Proof of Payment</label>
          <input type="file" name="proof_image" class="form-control" accept="image/jpeg,image/png,image/gif,image/webp">
          <div class="form-text">JPEG, PNG, GIF, or WebP. Max 5 MB.</div>
        </div>
      </div>
    </div>

    <!-- Terms & Submit -->
    <div class="form-check mb-3">
      <input class="form-check-input" type="checkbox" id="termsCheck" required>
      <label class="form-check-label" for="termsCheck">
        I confirm that the order details are correct and agree to the terms.
      </label>
    </div>

    <button type="submit" class="btn btn-primary btn-lg" id="placeOrderBtn" disabled>Place Order</button>
    <a href="?page=repeat_purchases" class="btn btn-outline-secondary btn-lg ms-2">Back to Catalog</a>
  </form>
</div>

<style>
.method-option { transition: all .15s ease; }
.method-option:hover { border-color: #0d6efd !important; background: #f0f7ff; }
.method-option:has(input:checked) { border-color: #0d6efd !important; background: #e6f0ff; box-shadow: 0 0 0 2px rgba(13,110,253,.25); }
</style>

<script>
(function() {
    var radios = document.querySelectorAll('[name=payment_method]');
    var proofSection = document.getElementById('proofUploadSection');
    var termsCheck = document.getElementById('termsCheck');
    var placeBtn = document.getElementById('placeOrderBtn');

    function toggleProof() {
        var checked = document.querySelector('[name=payment_method]:checked');
        proofSection.style.display = checked && checked.value !== 'ewallet' ? 'block' : 'none';
    }

    radios.forEach(function(r) { r.addEventListener('change', toggleProof); });
    toggleProof();

    termsCheck.addEventListener('change', function() {
        placeBtn.disabled = !this.checked;
    });
})();
</script>
