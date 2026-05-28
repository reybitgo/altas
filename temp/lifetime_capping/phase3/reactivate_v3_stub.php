<?php
/**
 * @file   views/member/reactivate.php
 * @brief  Member reactivation page (Phase 4 stub)
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
        <div class="card">
          <div class="card-header"><span class="card-title">🔄 Account Reactivation</span></div>
          <div class="card-body text-center py-5">
            <div style="font-size:3rem;">⚠️</div>
            <h5 class="fw-700 mt-3">Lifetime Income Cap Reached</h5>
            <p class="text-muted" style="font-size:.85rem;">
              Your account has reached its lifetime earnings limit.<br>
              Reactivation will reset your earnings counter to zero and start a new cycle.
            </p>
            <div class="alert alert-info py-2 my-3" style="font-size:.8rem;">
              <strong>Reactivation Fee:</strong> <?= fmt_money($capStatus['reactivation_fee'] ?? 0) ?><br>
              <strong>Window:</strong> <?= $capStatus['reactivation_window'] ?? 15 ?> days from cap date
            </div>
            <form method="POST" action="<?= APP_URL ?>/?page=do_reactivate">
              <?= csrf_field() ?>
              <button type="submit" class="btn btn-primary w-100">
                🔄 Request Reactivation
              </button>
            </form>
            <a href="<?= APP_URL ?>/?page=dashboard" class="btn btn-link btn-sm mt-2">← Back to Dashboard</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require 'views/partials/footer.php'; ?>
