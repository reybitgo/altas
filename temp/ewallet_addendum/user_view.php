<?php

/**
 * @file   views/admin/user_view.php
 * @brief  User view UI
 */
?>
<?php $pageTitle = 'Member: @' . $user['username']; ?>
<?php require 'views/partials/head.php'; ?>
<?php require 'views/partials/sidebar_admin.php'; ?>
<div class="main-content">
  <?php require 'views/partials/topbar.php'; ?>
  <div class="page-content">
    <?= render_flash() ?>

    <!-- Header -->
    <div class="d-flex align-items-center gap-3 mb-4 flex-wrap">
      <a href="<?= APP_URL ?>/?page=admin_users" class="btn btn-outline-secondary btn-sm">← Back</a>
      <div class="flex-grow-1">
        <h4 class="mb-0 fw-800">@<?= e($user['username']) ?></h4>
        <p class="text-muted mb-0" style="font-size:.78rem;">Member since <?= fmt_datetime($user['joined_at']) ?></p>
      </div>
      <?php $b = $user['status'] === 'active' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'; ?>
      <span class="badge <?= $b ?>" style="font-size:.8rem;padding:.4em .9em;"><?= ucfirst($user['status']) ?></span>

      <!-- VIP Toggle -->
      <form method="POST" action="<?= APP_URL ?>/?page=admin_toggle_vip" class="m-0">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= $user['id'] ?>">
        <input type="hidden" name="bypass" value="<?= $user['capping_bypass'] ? '0' : '1' ?>">
        <?php if ($user['capping_bypass']): ?>
          <button type="button" class="btn btn-sm btn-warning"
            onclick="showConfirm({title:'Remove VIP Privilege',message:'Remove VIP status from <strong>@<?= e($user['username']) ?></strong>? Capping will apply normally.',confirmText:'Remove VIP',confirmClass:'btn-warning',onConfirm:()=>this.closest('form').submit()})">
            🏆 VIP
          </button>
        <?php elseif ($user['cap_status'] === 'active'): ?>
          <button type="button" class="btn btn-sm btn-outline-warning"
            onclick="showConfirm({title:'Grant VIP Privilege',message:'Grant <strong>@<?= e($user['username']) ?></strong> unlimited lifetime earnings (bypass cap)?',confirmText:'Grant VIP',confirmClass:'btn-warning',onConfirm:()=>this.closest('form').submit()})">
            ⭐ Grant VIP
          </button>
        <?php else: ?>
          <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="Only active members can receive VIP">
            ⭐ Grant VIP
          </button>
        <?php endif; ?>
      </form>

      <!-- Daily Cap Bypass Toggle (only when VIP is active) -->
      <?php if ($user['capping_bypass']): ?>
      <form method="POST" action="<?= APP_URL ?>/?page=admin_toggle_daily_cap" class="m-0">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= $user['id'] ?>">
        <input type="hidden" name="bypass" value="<?= $user['daily_cap_bypass'] ? '0' : '1' ?>">
        <?php if ($user['daily_cap_bypass']): ?>
          <button type="button" class="btn btn-sm btn-info"
            onclick="showConfirm({title:'Disable Daily Cap Bypass',message:'Re-enable daily pair cap for <strong>@<?= e($user['username']) ?></strong>?',confirmText:'Disable',confirmClass:'btn-info',onConfirm:()=>this.closest('form').submit()})">
            ⚡ Daily Cap Off
          </button>
        <?php else: ?>
          <button type="button" class="btn btn-sm btn-outline-info"
            onclick="showConfirm({title:'Enable Daily Cap Bypass',message:'Allow <strong>@<?= e($user['username']) ?></strong> unlimited daily pairs?',confirmText:'Enable',confirmClass:'btn-info',onConfirm:()=>this.closest('form').submit()})">
            ⚡ Daily Cap On
          </button>
        <?php endif; ?>
      </form>
      <?php endif; ?>

      <form method="POST" action="<?= APP_URL ?>/?page=admin_toggle_user" class="m-0">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= $user['id'] ?>">
        <?php $isSuspend = $user['status'] === 'active'; ?>
        <button type="button" class="btn btn-sm <?= $isSuspend ? 'btn-outline-danger' : 'btn-outline-success' ?>"
          onclick="showConfirm({title:'<?= $isSuspend ? 'Suspend' : 'Activate' ?> Member',message:'<?= $isSuspend ? 'Suspend' : 'Activate' ?> <strong>@<?= e($user['username']) ?></strong>?',confirmText:'<?= $isSuspend ? 'Suspend' : 'Activate' ?>',confirmClass:'<?= $isSuspend ? 'btn-danger' : 'btn-success' ?>',onConfirm:()=>this.closest(\'form\').submit()})">
          <?= $isSuspend ? '🔒 Suspend' : '✅ Activate' ?>
        </button>
      </form>
    </div>

    <!-- KPI row -->
    <div class="row g-3 mb-3">
      <?php foreach (
        [
          ['E-Wallet Balance', fmt_money($user['ewallet_balance']),              'primary', 'primary',
           fmt_money((float)($user['withdrawable_balance'] ?? 0)) . ' withdrawable · ' . fmt_money(max(0, (float)$user['ewallet_balance'] - (float)($user['withdrawable_balance'] ?? 0))) . ' internal'],
          ['Total Earned',     fmt_money($summary['total_earned']),              'success', 'success'],
          ['Pairs Paid / Today', $pairingStatus['pairs_paid'] . ' / ' . $pairingStatus['pairs_paid_today'], 'orange', 'warning'],
          ['Pairs Flushed',    number_format($pairingStatus['pairs_flushed']),   'danger', 'danger'],
        ] as $card
      ): ?>
        <?php [$label, $val, $accent, $color] = $card; $subText = $card[4] ?? null; ?>
        <div class="col-6 col-xl-3">
          <div class="card stat-card">
            <div class="stat-accent stat-accent-<?= $accent ?>"></div>
            <div class="card-body pt-4">
              <div class="stat-label"><?= $label ?></div>
              <div class="stat-value text-<?= $color ?>" style="font-size:1.25rem;"><?= $val ?></div>
              <?php if ($subText): ?><div class="stat-sub"><?= $subText ?></div><?php endif; ?>
              <?php if ($label === 'Pairs Paid / Today'): ?><div class="stat-sub">Cap: <?= $pairingStatus['daily_cap'] ?> / day</div><?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="row g-3 mb-3">
      <!-- Profile -->
      <div class="col-12 col-md-6">
        <div class="card h-100">
          <div class="card-header"><span class="card-title">👤 Profile</span></div>
          <div class="card-body">
            <table class="info-table">
              <tr>
                <td>Full Name</td>
                <td><?= e($user['full_name'] ?? '—') ?></td>
              </tr>
              <tr>
                <td>Email</td>
                <td><?= e($user['email'] ?? '—') ?></td>
              </tr>
              <tr>
                <td>Mobile</td>
                <td><?= e($user['mobile'] ?? '—') ?></td>
              </tr>
              <tr>
                <td>GCash</td>
                <td><strong><?= e($user['gcash_number'] ?? '—') ?></strong></td>
              </tr>
              <tr>
                <td>Address</td>
                <td><?= e($user['address'] ?? '—') ?></td>
              </tr>
              <tr>
                <td>Last Login</td>
                <td><?= $user['last_login'] ? fmt_datetime($user['last_login']) : 'Never' ?></td>
              </tr>
            </table>
          </div>
        </div>
      </div>
      <!-- Binary -->
      <div class="col-12 col-md-6">
        <div class="card h-100">
          <div class="card-header"><span class="card-title">🌳 Binary Placement</span></div>
          <div class="card-body">
            <table class="info-table">
              <tr>
                <td>Package</td>
                <td><span class="badge bg-primary-subtle text-primary"><?= e($user['package_name'] ?? '—') ?></span></td>
              </tr>
              <tr>
                <td>Sponsor</td>
                <td><?= ($user['sponsor_username'] ?? null) ? '<a href="' . APP_URL . '/?page=admin_user_view&id=' . $user['sponsor_id'] . '">@' . e($user['sponsor_username']) . '</a>' : '—' ?></td>
              </tr>
              <tr>
                <td>Upline</td>
                <td><?= ($user['binary_parent_username'] ?? null) ? '@' . e($user['binary_parent_username']) . ' (' . $user['binary_position'] . ')' : '—' ?></td>
              </tr>
              <tr>
                <td>Pairing Bonus</td>
                <td><?= fmt_money($user['pairing_bonus'] ?? 0) ?> / pair</td>
              </tr>
              <tr>
                <td>Daily Cap</td>
                <td><?= $user['daily_pair_cap'] ?? 0 ?> pairs / day</td>
              </tr>
            </table>
            <div class="row g-2 mt-2">
              <div class="col-6">
                <div class="leg-box text-center">
                  <div class="leg-label">↙ Left</div>
                  <div class="leg-count"><?= number_format($user['left_count']) ?></div>
                </div>
              </div>
              <div class="col-6">
                <div class="leg-box text-center">
                  <div class="leg-label">↘ Right</div>
                  <div class="leg-count"><?= number_format($user['right_count']) ?></div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Tabs -->
    <?php $tab = $_GET['tab'] ?? 'commissions'; ?>
    <div class="card">
      <div class="card-header">
        <ul class="nav nav-pills card-header-pills gap-1">
          <?php foreach (['commissions' => '💰 Commissions', 'ledger' => '📒 E-Wallet Ledger', 'payouts' => '💳 Payouts', 'cap_dfi' => '🛡️ Cap & DFI', 'ewallet' => '💱 Transfers'] as $t => $label): ?>
            <li class="nav-item"><a class="nav-link <?= $tab === $t ? 'active' : '' ?>" href="<?= APP_URL ?>/?page=admin_user_view&id=<?= $user['id'] ?>&tab=<?= $t ?>"><?= $label ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>

      <?php if ($tab === 'commissions'): ?>
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead>
              <tr>
                <th>Date</th>
                <th>Type</th>
                <th>Description</th>
                <th>From</th>
                <th>Amount</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($commHist['data'])): ?><tr>
                  <td colspan="6" class="text-center py-4 text-muted">No commissions.</td>
                </tr>
                <?php else: foreach ($commHist['data'] as $c):
                  $tn = match ($c['type']) {
                    'pairing' => '🤝 Pairing',
                    'direct_referral' => '👥 Direct',
                    'indirect_referral' => '🔗 Indirect Lvl ' . $c['level'],
                    default => $c['type']
                  };
                ?>
                  <tr>
                    <td class="td-muted" style="font-size:.72rem;"><?= fmt_datetime($c['created_at']) ?></td>
                    <td><?= $tn ?></td>
                    <td class="text-truncate" style="max-width:180px;font-size:.8rem;"><?= e($c['description']) ?></td>
                    <td class="td-muted"><?= $c['source_username'] ? '@' . e($c['source_username']) : '—' ?></td>
                    <td class="<?= $c['status'] === 'credited' ? 'td-green' : ' td-muted' ?> font-mono"><?= $c['status'] === 'credited' ? '+' . fmt_money($c['amount']) : '—' ?></td>
                    <td><span class="badge <?= $c['status'] === 'credited' ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning' ?>"><?= ucfirst($c['status']) ?></span></td>
                  </tr>
              <?php endforeach;
              endif; ?>
            </tbody>
          </table>
        </div>

      <?php elseif ($tab === 'ledger'): ?>
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead>
              <tr>
                <th>Date</th>
                <th>Type</th>
                <th>Amount</th>
                <th>Balance After</th>
                <th>Note</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($ledger['data'])): ?><tr>
                  <td colspan="5" class="text-center py-4 text-muted">No ledger entries.</td>
                </tr>
                <?php else: foreach ($ledger['data'] as $l): ?>
                  <tr>
                    <td class="td-muted" style="font-size:.72rem;"><?= fmt_datetime($l['created_at']) ?></td>
                    <td><span class="badge <?= $l['type'] === 'credit' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' ?>"><?= ucfirst($l['type']) ?></span></td>
                    <td class="font-mono <?= $l['type'] === 'credit' ? 'td-green' : 'td-red' ?>"><?= ($l['type'] === 'credit' ? '+' : '-') . fmt_money($l['amount']) ?></td>
                    <td class="font-mono fw-bold"><?= fmt_money($l['balance_after']) ?></td>
                    <td class="td-muted" style="font-size:.78rem;"><?= e($l['note'] ?? '—') ?></td>
                  </tr>
              <?php endforeach;
              endif; ?>
            </tbody>
          </table>
        </div>

      <?php elseif ($tab === 'cap_dfi'): ?>
        <div class="card-body">
          <!-- Cap Progress -->
          <div class="row g-3 mb-4">
            <div class="col-12 col-md-6">
              <div class="card h-100">
                <div class="card-header"><span class="card-title">🛡️ Lifetime Cap</span></div>
                <div class="card-body">
                  <?php
                  $lEarned = $capStatus['lifetime_earned'] ?? 0;
                  $lCap    = $capStatus['lifetime_cap'] ?? 0;
                  $lPct    = $lCap > 0 ? min(100, ($lEarned / $lCap) * 100) : 0;
                  ?>
                  <div class="d-flex justify-content-between mb-2">
                    <span class="fw-bold"><?= fmt_money($lEarned) ?></span>
                    <span class="text-muted">/ <?= fmt_money($lCap) ?></span>
                  </div>
                  <div class="cap-bar-track mb-2">
                    <div class="cap-bar-fill <?= $lPct >= 100 ? 'full' : '' ?>" style="width:<?= $lPct ?>%"></div>
                  </div>
                  <div class="d-flex justify-content-between" style="font-size:.78rem;">
                    <span><?= number_format($lPct, 1) ?>%</span>
                    <span><?= fmt_money(max(0, $lCap - $lEarned)) ?> remaining</span>
                  </div>
                  <div class="mt-3">
                    <?php $cs = $capStatus['cap_status'] ?? 'active'; ?>
                    <span class="badge <?= match($cs) { 'active' => 'bg-success-subtle text-success', 'capped' => 'bg-warning-subtle text-warning', default => 'bg-danger-subtle text-danger' } ?>">
                      <?= match($cs) { 'active' => '✅ Active', 'capped' => '⚠️ Capped', default => '⛔ Permanent' } ?>
                    </span>
                    <?php if ($capStatus['capped_at']): ?>
                      <span class="text-muted ms-2" style="font-size:.75rem;">Capped at: <?= fmt_datetime($capStatus['capped_at']) ?></span>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-12 col-md-6">
              <div class="card h-100">
                <div class="card-header"><span class="card-title">📅 DFI Status</span></div>
                <div class="card-body">
                  <div class="row g-2">
                    <div class="col-6">
                      <div class="text-muted" style="font-size:.72rem;font-weight:700;text-transform:uppercase;">Daily Rate</div>
                      <div class="fw-bold"><?= fmt_money($dfiStatus['daily_rate'] ?? 0) ?></div>
                    </div>
                    <div class="col-6">
                      <div class="text-muted" style="font-size:.72rem;font-weight:700;text-transform:uppercase;">Days Used</div>
                      <div class="fw-bold"><?= $dfiStatus['days_used'] ?? 0 ?> / <?= ($dfiStatus['days_used'] ?? 0) + ($dfiStatus['days_remaining'] ?? 0) ?></div>
                    </div>
                    <div class="col-6">
                      <div class="text-muted" style="font-size:.72rem;font-weight:700;text-transform:uppercase;">Total Earned</div>
                      <div class="fw-bold text-success"><?= fmt_money($dfiStatus['total_dfi_earned'] ?? 0) ?></div>
                    </div>
                    <div class="col-6">
                      <div class="text-muted" style="font-size:.72rem;font-weight:700;text-transform:uppercase;">Status</div>
                      <div class="fw-bold"><?= ucfirst($dfiStatus['status'] ?? 'unknown') ?></div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Reactivation History -->
          <?php if (!empty($reactivationHistory)): ?>
            <div class="card mb-4">
              <div class="card-header"><span class="card-title">🔄 Reactivation History</span></div>
              <div class="table-responsive">
                <table class="table table-hover mb-0" style="font-size:.85rem;">
                  <thead class="table-light">
                    <tr><th>Date</th><th>Previous Earned</th><th>Fee Paid</th><th>Method</th><th>Status</th></tr>
                  </thead>
                  <tbody>
                    <?php foreach ($reactivationHistory as $r): ?>
                      <tr>
                        <td><?= fmt_datetime($r['created_at']) ?></td>
                        <td><?= fmt_money((float)$r['previous_earned']) ?></td>
                        <td class="fw-semibold text-success"><?= fmt_money((float)$r['amount_paid']) ?></td>
                        <td><span class="badge bg-secondary-subtle text-secondary" style="text-transform:uppercase;font-size:.65rem;"><?= e($r['payment_method']) ?></span></td>
                        <td><span class="badge <?= $r['status'] === 'completed' ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning' ?>"><?= ucfirst($r['status']) ?></span></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          <?php endif; ?>

          <!-- Cap-Blocked Commissions -->
          <div class="card">
            <div class="card-header"><span class="card-title">⛔ Cap-Triggered Blocks</span></div>
            <div class="table-responsive">
              <table class="table table-hover mb-0" style="font-size:.85rem;">
                <thead class="table-light">
                  <tr><th>Date</th><th>Type</th><th>Description</th><th>Credited</th><th>Blocked</th></tr>
                </thead>
                <tbody>
                  <?php $blockedRows = $capBlocked->fetchAll(); if (empty($blockedRows)): ?>
                    <tr><td colspan="5" class="text-center py-4 text-muted">No cap-blocked commissions.</td></tr>
                  <?php else: foreach ($blockedRows as $c):
                    $tn = match ($c['type']) {
                      'pairing' => '🤝 Pairing',
                      'direct_referral' => '👥 Direct',
                      'indirect_referral' => '🔗 Indirect Lvl ' . $c['level'],
                      'daily_fixed_income' => '📅 DFI',
                      default => $c['type']
                    };
                  ?>
                    <tr>
                      <td class="td-muted" style="font-size:.72rem;"><?= fmt_datetime($c['created_at']) ?></td>
                      <td><?= $tn ?></td>
                      <td class="text-truncate" style="max-width:200px;font-size:.78rem;"><?= e($c['description']) ?></td>
                      <td class="td-green font-mono">+<?= fmt_money($c['amount']) ?></td>
                      <td class="text-warning font-mono">−<?= fmt_money($c['cap_deduction']) ?></td>
                    </tr>
                  <?php endforeach; endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

      <?php elseif ($tab === 'ewallet'): ?>
        <div class="card-body">
          <!-- Transfer History -->
          <div class="card mb-0">
            <div class="card-header"><span class="card-title">💱 Transfer History</span></div>
            <div class="table-responsive">
              <table class="table table-hover mb-0" style="font-size:.85rem;">
                <thead class="table-light">
                  <tr><th>Date</th><th>Direction</th><th>Counterparty</th><th>Amount</th><th>Fee</th><th>Note</th></tr>
                </thead>
                <tbody>
                  <?php
                  $transferRows = [];
                  if (!empty($transferHistory)) {
                      $transferRows = $transferHistory;
                  }
                  if (empty($transferRows)): ?>
                    <tr><td colspan="6" class="text-center py-4 text-muted">No transfer history.</td></tr>
                  <?php else: foreach ($transferRows as $t): ?>
                    <tr>
                      <td style="font-size:.75rem;"><?= fmt_datetime($t['created_at']) ?></td>
                      <td>
                        <?php if ($t['sender_id'] == $user['id']): ?>
                          <span class="badge bg-danger-subtle text-danger" style="font-size:.65rem;">SENT</span>
                        <?php else: ?>
                          <span class="badge bg-success-subtle text-success" style="font-size:.65rem;">RECEIVED</span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <?php if ($t['sender_id'] == $user['id']): ?>
                          <a href="<?= APP_URL ?>/?page=admin_user_view&id=<?= $t['recipient_id'] ?>" class="text-decoration-none fw-semibold">@<?= e($t['recipient_username'] ?? '') ?></a>
                        <?php else: ?>
                          <a href="<?= APP_URL ?>/?page=admin_user_view&id=<?= $t['sender_id'] ?>" class="text-decoration-none fw-semibold">@<?= e($t['sender_username'] ?? '') ?></a>
                        <?php endif; ?>
                      </td>
                      <td class="font-mono fw-semibold"><?= fmt_money($t['amount']) ?></td>
                      <td class="font-mono text-muted"><?= ($t['fee'] ?? 0) > 0 ? fmt_money($t['fee']) : '—' ?></td>
                      <td class="text-muted" style="font-size:.75rem;"><?= e($t['note'] ?? '') ?></td>
                    </tr>
                  <?php endforeach; endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead>
              <tr>
                <th>Requested</th>
                <th>Amount</th>
                <th>GCash</th>
                <th>Status</th>
                <th>Processed</th>
                <th>Note</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($payouts['data'])): ?><tr>
                  <td colspan="6" class="text-center py-4 text-muted">No payout history.</td>
                </tr>
                <?php else: foreach ($payouts['data'] as $pr): ?>
                  <tr>
                    <td class="td-muted" style="font-size:.72rem;"><?= fmt_datetime($pr['requested_at']) ?></td>
                    <td class="font-mono fw-bold"><?= fmt_money($pr['amount']) ?></td>
                    <td class="td-muted font-mono"><?= e($pr['gcash_number']) ?></td>
                    <td><?php $b = match ($pr['status']) {
                          'pending' => 'bg-warning-subtle text-warning',
                          'approved' => 'bg-info-subtle text-info',
                          'completed' => 'bg-success-subtle text-success',
                          'rejected' => 'bg-danger-subtle text-danger',
                          default => 'bg-secondary-subtle'
                        }; ?><span class="badge <?= $b ?>"><?= ucfirst($pr['status']) ?></span></td>
                    <td class="td-muted" style="font-size:.72rem;"><?= $pr['processed_at'] ? fmt_datetime($pr['processed_at']) : '—' ?></td>
                    <td class="td-muted" style="font-size:.78rem;"><?= e($pr['admin_note'] ?? '—') ?></td>
                  </tr>
              <?php endforeach;
              endif; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require 'views/partials/footer.php'; ?>