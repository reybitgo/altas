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

            <form method="POST" action="<?= APP_URL ?>/?page=do_reactivate" id="reactivateForm">
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

            <!-- External payment instructions -->
            <div id="externalNotice" class="alert alert-info py-2 mt-3 mb-0 d-none" style="font-size:.8rem;">
              <strong>📌 External Payment:</strong> After submitting, please send <?= fmt_money($request['fee']) ?> via your chosen method and wait for admin confirmation. Your account will remain capped until confirmed.
            </div>

            <script>
              (function () {
                const terms   = document.getElementById('termsCheck');
                const btn     = document.getElementById('reactivateBtn');
                const notice  = document.getElementById('externalNotice');
                const methods = document.querySelectorAll('input[name="payment_method"]');
                if (!terms || !btn) return;

                terms.addEventListener('change', function () {
                  btn.disabled = !this.checked;
                });

                function updateNotice() {
                  const selected = document.querySelector('input[name="payment_method"]:checked');
                  if (selected && selected.value !== 'ewallet' && notice) {
                    notice.classList.remove('d-none');
                  } else if (notice) {
                    notice.classList.add('d-none');
                  }
                }

                methods.forEach(function (radio) {
                  radio.addEventListener('change', updateNotice);
                });
                updateNotice();
              })();
            </script>

            <script>
              (function () {
                const terms = document.getElementById('termsCheck');
                const btn   = document.getElementById('reactivateBtn');
                if (!terms || !btn) return;
                terms.addEventListener('change', function () {
                  btn.disabled = !this.checked;
                });
              })();
            </script>

            <a href="<?= APP_URL ?>/?page=dashboard" class="btn btn-link btn-sm w-100 mt-2">← Back to Dashboard</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require 'views/partials/footer.php'; ?>
