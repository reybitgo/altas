<?php
/**
 * @file   views/member/reactivate.php
 * @brief  Member reactivation page (Phase 4)
 */
?>
<?php $pageTitle = 'Reactivate Account'; ?>
<?php require 'views/partials/head.php'; ?>
<?php require 'views/partials/sidebar_member.php'; ?>
<div class="main-content">
  <?php require 'views/partials/topbar.php'; ?>
  <div class="page-content">
    <?= render_flash() ?>

    <div class="row justify-content-center">
      <div class="col-12 col-md-8 col-lg-6">
        <!-- Cap Status Banner -->
        <div class="card mb-3 border-warning" style="border-width:2px;">
          <div class="card-body">
            <div class="d-flex align-items-center gap-3 mb-2">
              <div style="font-size:2rem;">⚠️</div>
              <div>
                <h5 class="fw-700 mb-0">Lifetime Income Cap Reached</h5>
                <p class="text-muted mb-0" style="font-size:.8rem;">
                  You've earned <?= fmt_money($capStatus['lifetime_earned']) ?> of <?= fmt_money($capStatus['lifetime_cap']) ?> cap
                </p>
              </div>
            </div>
            <div class="alert alert-warning py-2 mb-0" style="font-size:.8rem;">
              <strong>Window closes in <?= (int)$request['days_remaining'] ?> day(s)</strong><br>
              After that, your account becomes permanently inactive.
            </div>
          </div>
        </div>

        <!-- Reactivation Form -->
        <div class="card">
          <div class="card-header"><span class="card-title">🔄 Account Reactivation</span></div>
          <div class="card-body">
            <div class="mb-4">
              <div class="text-muted" style="font-size:.72rem;font-weight:700;text-transform:uppercase;">Reactivation Fee</div>
              <div class="fw-800" style="font-size:1.75rem;"><?= fmt_money($request['fee']) ?></div>
            </div>

            <hr class="my-3">

            <form method="POST" action="<?= APP_URL ?>/?page=do_reactivate" id="reactivateForm" enctype="multipart/form-data">
              <?= csrf_field() ?>

              <!-- Payment Method -->
              <div class="mb-3">
                <label class="form-label fw-semibold" style="font-size:.85rem;">Payment Method</label>

                <?php if ($request['can_use_ewallet']): ?>
                  <div class="form-check mb-2">
                    <input class="form-check-input" type="radio" name="payment_method" id="payEwallet" value="ewallet" checked>
                    <label class="form-check-label" for="payEwallet">
                      💳 Deduct from E-Wallet
                      <span class="badge bg-success-subtle text-success ms-1">Balance: <?= fmt_money($request['ewallet_balance']) ?></span>
                    </label>
                  </div>
                <?php else: ?>
                  <div class="alert alert-info py-2" style="font-size:.8rem;">
                    💳 E-Wallet balance too low (<?= fmt_money($request['ewallet_balance']) ?>). Choose an external payment method.
                  </div>
                <?php endif; ?>

                <div class="form-check mb-2">
                  <input class="form-check-input" type="radio" name="payment_method" id="payGcash" value="gcash" <?= !$request['can_use_ewallet'] ? 'checked' : '' ?>>
                  <label class="form-check-label" for="payGcash">
                    📱 Pay via GCash
                  </label>
                </div>
                <div class="form-check mb-2">
                  <input class="form-check-input" type="radio" name="payment_method" id="payMaya" value="maya">
                  <label class="form-check-label" for="payMaya">
                    💙 Pay via Maya
                  </label>
                </div>
                <div class="form-check mb-2">
                  <input class="form-check-input" type="radio" name="payment_method" id="payUsdt" value="usdt">
                  <label class="form-check-label" for="payUsdt">
                    ₮ Pay via USDT
                  </label>
                </div>
              </div>

              <!-- Admin Payment Details (shown for external methods) -->
              <div id="adminPaymentDetails" class="d-none">
                <div class="alert alert-primary py-3" style="font-size:.85rem;">
                  <div class="fw-bold mb-2" style="font-size:.8rem;text-transform:uppercase;letter-spacing:.5px;">Send Payment To</div>

                  <div id="detailGcash" class="d-none">
                    <div class="d-flex align-items-center gap-2 mb-1">
                      <span style="font-size:1.2rem;">📱</span>
                      <span class="fw-bold">GCash</span>
                    </div>
                    <div class="font-mono fw-bold" style="font-size:1.1rem;color:#0070d8;"><?= e($admin['gcash_number'] ?? '—') ?></div>
                  </div>

                  <div id="detailMaya" class="d-none">
                    <div class="d-flex align-items-center gap-2 mb-1">
                      <span style="font-size:1.2rem;">💙</span>
                      <span class="fw-bold">Maya</span>
                    </div>
                    <div class="font-mono fw-bold" style="font-size:1.1rem;color:#48b0db;"><?= e($admin['maya_number'] ?? '—') ?></div>
                  </div>

                  <div id="detailUsdt" class="d-none">
                    <div class="d-flex align-items-center gap-2 mb-1">
                      <span style="font-size:1.2rem;">₮</span>
                      <span class="fw-bold">USDT (TRC20)</span>
                    </div>
                    <div class="font-mono fw-bold" style="font-size:.9rem;color:#26a17b;word-break:break-all;"><?= e($admin['usdt_address'] ?? '—') ?></div>
                  </div>

                  <div class="mt-2 pt-2 border-top" style="border-color:rgba(59,111,240,.2);">
                    <div class="text-muted" style="font-size:.75rem;">Amount to send:</div>
                    <div class="fw-bold" style="font-size:1.1rem;"><?= fmt_money($request['fee']) ?></div>
                  </div>
                </div>

                <!-- Proof Upload -->
                <div class="mb-3">
                  <label class="form-label fw-semibold" style="font-size:.85rem;">📎 Proof of Payment <span class="text-danger">*</span></label>
                  <div class="form-text mb-2" style="font-size:.75rem;">Upload a screenshot or photo showing your successful payment.</div>
                  <input type="file" name="proof_image" id="proofImage" class="form-control" accept="image/jpeg,image/png,image/gif,image/webp">
                  <div class="form-text" style="font-size:.7rem;">Max 5MB. JPEG, PNG, GIF, or WebP.</div>
                </div>
              </div>

              <hr class="my-3">

              <!-- Terms -->
              <div class="mb-3">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" id="termsCheck" required>
                  <label class="form-check-label" for="termsCheck" style="font-size:.8rem;">
                    I understand that reactivation resets my lifetime earnings counter to zero and starts a new cycle. Previous earnings are retained but do not count toward the new cap.
                  </label>
                </div>
              </div>

              <button type="submit" class="btn btn-primary w-100" id="reactivateBtn" disabled>
                🔄 Request Reactivation — <?= fmt_money($request['fee']) ?>
              </button>
            </form>

            <a href="<?= APP_URL ?>/?page=dashboard" class="btn btn-link btn-sm w-100 mt-2">← Back to Dashboard</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  (function () {
    const terms      = document.getElementById('termsCheck');
    const btn        = document.getElementById('reactivateBtn');
    const detailsBox = document.getElementById('adminPaymentDetails');
    const proofInput = document.getElementById('proofImage');
    const methods    = document.querySelectorAll('input[name="payment_method"]');

    if (!terms || !btn) return;

    terms.addEventListener('change', function () {
      btn.disabled = !this.checked;
    });

    function updatePaymentDetails() {
      const selected = document.querySelector('input[name="payment_method"]:checked');
      if (!selected) return;

      const method = selected.value;

      // Show/hide admin details box
      if (method === 'ewallet') {
        detailsBox.classList.add('d-none');
        if (proofInput) proofInput.removeAttribute('required');
      } else {
        detailsBox.classList.remove('d-none');
        if (proofInput) proofInput.setAttribute('required', 'required');
      }

      // Show specific detail
      ['Gcash', 'Maya', 'Usdt'].forEach(function (m) {
        const el = document.getElementById('detail' + m);
        if (el) el.classList.add('d-none');
      });
      const activeDetail = document.getElementById('detail' + method.charAt(0).toUpperCase() + method.slice(1));
      if (activeDetail) activeDetail.classList.remove('d-none');
    }

    methods.forEach(function (radio) {
      radio.addEventListener('change', updatePaymentDetails);
    });
    updatePaymentDetails();
  })();
</script>
<?php require 'views/partials/footer.php'; ?>
