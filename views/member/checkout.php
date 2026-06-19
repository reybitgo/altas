<div class="container-fluid px-4 py-3">
  <h4 class="mb-3">Checkout</h4>

  <?php if (!empty($stockErrors)): ?>
    <div class="alert alert-danger">
      <strong>Stock issues:</strong>
      <ul class="mb-0"><?php foreach ($stockErrors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul>
    </div>
  <?php endif; ?>

  <form method="post" action="?page=place_order" enctype="multipart/form-data">
    <?= csrf_field() ?>

    <div class="card mb-3">
      <div class="card-header">Order Summary</div>
      <div class="card-body">
        <table class="table table-sm">
          <thead><tr><th>Product</th><th>Qty</th><th>Price</th><th>PV</th></tr></thead>
          <tbody>
            <?php foreach ($items as $item): ?>
            <tr>
              <td><?= e($item['product_name']) ?></td>
              <td><?= (int)$item['quantity'] ?></td>
              <td>₱<?= fmt_money((float)$item['unit_price']) ?></td>
              <td><?= fmt_money((float)$item['unit_pv']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr class="fw-bold">
              <td colspan="2">Total</td>
              <td>₱<?= fmt_money((float)$totals['total_price']) ?></td>
              <td><?= fmt_money((float)$totals['total_pv']) ?></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header">Binary Position</div>
      <div class="card-body">
        <div class="form-check form-check-inline">
          <input class="form-check-input" type="radio" name="binary_position" id="pos_left" value="left" checked>
          <label class="form-check-label" for="pos_left">Left</label>
        </div>
        <div class="form-check form-check-inline">
          <input class="form-check-input" type="radio" name="binary_position" id="pos_right" value="right">
          <label class="form-check-label" for="pos_right">Right</label>
        </div>
        <div class="form-text">Which of your own legs receives the product PV.</div>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header">Payment Method</div>
      <div class="card-body">
        <?php if ($canUseEwallet): ?>
        <div class="form-check mb-2">
          <input class="form-check-input" type="radio" name="payment_method" id="pm_ewallet" value="ewallet" checked>
          <label class="form-check-label" for="pm_ewallet">
            <strong>E-Wallet</strong>
            <span class="text-muted"> (Balance: ₱<?= fmt_money($ewalletBalance) ?>)</span>
          </label>
        </div>
        <?php endif; ?>
        <?php $first = true; foreach ($methods as $key => $label): ?>
        <div class="form-check mb-2">
          <input class="form-check-input" type="radio" name="payment_method" id="pm_<?= $key ?>" value="<?= $key ?>"
            <?= (!$canUseEwallet && $first) ? 'checked' : '' ?>>
          <label class="form-check-label" for="pm_<?= $key ?>"><?= e($label) ?></label>
        </div>
        <?php $first = false; endforeach; ?>
        <div id="proof_upload_container" style="display:none;" class="mt-3">
          <label class="form-label">Upload Proof of Payment</label>
          <input type="file" name="proof_image" class="form-control" accept="image/*">
          <div class="form-text">JPEG, PNG, GIF, or WebP. Max 5 MB.</div>
        </div>
      </div>
    </div>

    <div class="form-check mb-3">
      <input class="form-check-input" type="checkbox" id="terms" required>
      <label class="form-check-label" for="terms">I confirm that the order details are correct.</label>
    </div>

    <button type="submit" class="btn btn-primary" id="place_order_btn">Place Order</button>
    <a href="?page=repeat_purchases" class="btn btn-outline-secondary ms-2">Cancel</a>
  </form>
</div>

<script>
document.querySelectorAll('[name=payment_method]').forEach(function(el) {
    el.addEventListener('change', function() {
        document.getElementById('proof_upload_container').style.display =
            this.value === 'ewallet' ? 'none' : 'block';
    });
});
document.querySelector('[name=payment_method]:checked')?.dispatchEvent(new Event('change'));
</script>
