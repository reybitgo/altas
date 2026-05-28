<?php

/**
 * @file   views/member/dashboard.php
 * @brief  Member dashboard UI
 */
?>
<?php $pageTitle = 'Dashboard'; ?>
<?php require 'views/partials/head.php'; ?>

<?php require 'views/partials/sidebar_member.php'; ?>

<div class="main-content">
  <?php require 'views/partials/topbar.php'; ?>
  <div class="page-content">
    <?= render_flash() ?>

    <?php if (($status['cap_status'] ?? 'active') === 'capped'): ?>
      <!-- Capped Banner -->
      <div class="card mb-4 border-warning" style="border-width:2px;">
        <div class="card-body">
          <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="d-flex align-items-center gap-3">
              <div style="font-size:2rem;">⚠️</div>
              <div>
                <h5 class="fw-700 mb-1">Lifetime Income Cap Reached</h5>
                <p class="text-muted mb-0" style="font-size:.8rem;">
                  You've earned <?= fmt_money($status['lifetime_earned'] ?? 0) ?> of <?= fmt_money($status['lifetime_cap'] ?? 0) ?>
                </p>
              </div>
            </div>
            <a href="<?= APP_URL ?>/?page=reactivate" class="btn btn-warning">
              🔄 Reactivate Account
            </a>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <!-- Welcome row -->
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
      <div>
        <h4 class="fw-800 mb-1">Welcome back, <?= e($user['full_name'] ? explode(' ', $user['full_name'])[0] : '@' . $user['username']) ?>! 👋</h4>
        <p class="text-muted mb-0" style="font-size:.8rem;"><?= e($user['package_name'] ?? 'Member') ?> · Joined <?= fmt_date($user['joined_at']) ?></p>
      </div>
      <a href="<?= APP_URL ?>/?page=payout" class="btn btn-primary btn-sm">💳 Request Payout</a>
      <a href="<?= APP_URL ?>/?page=register&sponsor=<?= urlencode($user['username']) ?>"
        class="btn btn-success btn-sm">➕ Register Member</a>
    </div>

    <!-- KPI Cards -->
    <div class="row g-3 mb-3">
      <?php $cards = [
        [$user['ewallet_balance'], 'E-Wallet Balance',   '💰', 'primary', 'primary', 'Withdraw →', '/?page=payout'],
        [$summary['total_pairing'],  'Pairing Earnings', '🤝', 'success', 'success', number_format($user['pairs_paid']) . ' pairs lifetime', null],
        [$summary['total_direct'],   'Direct Referral',  '👥', 'orange',  'warning', null, '/?page=genealogy&view=referral'],
        [$summary['total_indirect'], 'Indirect Referral', '🔗', 'purple',  'purple',  'Up to 10 levels', null],
      ];
      foreach ($cards as [$val, $label, $icon, $accent, $color, $sub, $link]): ?>
        <div class="col-6 col-xl-3">
          <div class="card stat-card h-100">
            <div class="stat-accent stat-accent-<?= $accent ?>"></div>
            <div class="card-body pt-4">
              <div class="stat-icon bg-<?= $color === 'purple' ? 'purple' : ($color === 'orange' ? 'warning' : $color) ?>-subtle"><?= $icon ?></div>
              <div class="stat-label"><?= $label ?></div>
              <div class="stat-value text-<?= $color === 'orange' ? 'warning' : ($color === 'purple' ? 'primary' : $color) ?>"><?= fmt_money((float)$val) ?></div>
              <?php if ($sub): ?>
                <div class="stat-sub">
                  <?php if ($link): ?><a href="<?= APP_URL . $link ?>" class="text-decoration-none fw-semibold" style="font-size:.72rem;"><?= $sub ?></a>
                    <?php else: ?><?= $sub ?><?php endif; ?>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="row g-3 mb-3">
      <!-- Lifetime Cap Widget -->
      <div class="col-12 col-md-6">
        <div class="card h-100">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <div class="card-title">🛡️ Lifetime Income Cap</div>
              <a href="<?= APP_URL ?>/?page=cap_status" class="btn btn-outline-primary btn-sm" style="font-size:.65rem;">View Details →</a>
            </div>
            <?php
            $lEarned = $status['lifetime_earned'] ?? 0;
            $lCap    = $status['lifetime_cap'] ?? 0;
            $lPct    = $lCap > 0 ? min(100, ($lEarned / $lCap) * 100) : 0;
            ?>
            <div class="cap-bar-track mb-2">
              <div class="cap-bar-fill <?= $lPct >= 100 ? 'full' : '' ?>" style="width:<?= $lPct ?>%"></div>
            </div>
            <div class="d-flex justify-content-between" style="font-size:.78rem;color:var(--muted);">
              <span><strong><?= fmt_money($lEarned) ?></strong> earned</span>
              <span><strong><?= fmt_money(max(0, $lCap - $lEarned)) ?></strong> remaining</span>
            </div>
            <div class="cap-earned mt-2">
              <span>Cap Status</span>
              <strong><?= match($status['cap_status'] ?? 'active') { 'active' => 'Active ✅', 'capped' => 'Capped ⚠️', default => 'Inactive ⛔' } ?></strong>
            </div>
          </div>
        </div>
      </div>

      <!-- DFI Widget -->
      <div class="col-12 col-md-6">
        <div class="card h-100">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <div class="card-title">📅 Daily Fixed Income</div>
              <a href="<?= APP_URL ?>/?page=dfi_history" class="btn btn-outline-primary btn-sm" style="font-size:.65rem;">View History →</a>
            </div>
            <?php
            $dfiStatus = DailyFixedIncome::getMemberDFIStatus($user['id']);
            $dfiDays   = $dfiStatus['days_used'] + $dfiStatus['days_remaining'];
            $dfiPct    = $dfiDays > 0 ? ($dfiStatus['days_used'] / $dfiDays) * 100 : 0;
            ?>
            <div class="cap-bar-track mb-2">
              <div class="cap-bar-fill" style="width:<?= $dfiPct ?>%;background:linear-gradient(90deg,#3b6ff0,#60a5fa)"></div>
            </div>
            <div class="d-flex justify-content-between" style="font-size:.78rem;color:var(--muted);">
              <span><strong><?= $dfiStatus['days_used'] ?></strong> days used</span>
              <span><strong><?= $dfiStatus['days_remaining'] ?></strong> remaining</span>
            </div>
            <div class="cap-earned mt-2">
              <span><?= fmt_money($dfiStatus['daily_rate']) ?> / day</span>
              <strong><?= fmt_money($dfiStatus['total_dfi_earned']) ?> total</strong>
            </div>
            <?php if ($dfiStatus['status'] !== 'active'): ?>
              <div class="alert alert-warning py-2 mb-0 mt-2" style="font-size:.78rem;">
                <?= match($dfiStatus['status']) {
                    'capped'    => '⚠️ DFI paused — cap reached',
                    'perminact' => '⛔ DFI frozen — permanently inactive',
                    'completed' => '✅ DFI cycle completed',
                    'disabled'  => 'ℹ DFI not enabled for your package',
                    default     => '⏸️ DFI paused'
                } ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <div class="row g-3 mb-3">
      <!-- Pairing cap widget -->
      <div class="col-12 col-md-6">
        <div class="card h-100">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <div class="card-title">🎯 Today's Pairing Cap</div>
              <span class="badge bg-secondary-subtle text-secondary" style="font-size:.65rem;">Resets at midnight</span>
            </div>
            <?php $pct = $status['cap_percent']; ?>
            <div class="cap-bar-track mb-2">
              <div class="cap-bar-fill <?= $pct >= 100 ? 'full' : '' ?>" style="width:<?= $pct ?>%"></div>
            </div>
            <div class="d-flex justify-content-between" style="font-size:.78rem;color:var(--muted);">
              <span><strong><?= $status['pairs_paid_today'] ?></strong> earned today</span>
              <span><strong><?= $status['cap_remaining'] ?></strong> / <?= $status['daily_cap'] ?> remaining</span>
            </div>
            <div class="cap-earned mt-2">
              <span>Earned today</span>
              <strong><?= fmt_money($status['earned_today']) ?></strong>
            </div>
            <?php if ($status['cap_remaining'] === 0): ?>
              <div class="alert alert-warning py-2 mb-0 mt-2" style="font-size:.78rem;">⚡ Daily cap reached — resets at midnight</div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Binary legs -->
      <div class="col-12 col-md-6">
        <div class="card h-100">
          <div class="card-header d-flex justify-content-between align-items-center">
            <span class="card-title">🌳 Binary Legs</span>
            <a href="<?= APP_URL ?>/?page=genealogy&view=binary" class="btn btn-outline-primary btn-sm" style="font-size:.72rem;">View Tree</a>
          </div>
          <div class="card-body">
            <div class="row g-2 mb-3">
              <div class="col-6">
                <div class="leg-box text-center">
                  <div class="leg-label">↙ Left Leg</div>
                  <div class="leg-count"><?= number_format($status['left_count']) ?></div>
                  <div style="font-size:.72rem;color:var(--muted);">members</div>
                </div>
              </div>
              <div class="col-6">
                <div class="leg-box text-center">
                  <div class="leg-label">↘ Right Leg</div>
                  <div class="leg-count"><?= number_format($status['right_count']) ?></div>
                  <div style="font-size:.72rem;color:var(--muted);">members</div>
                </div>
              </div>
            </div>
            <?php foreach (
              [
                ['Lifetime pairs paid', number_format($status['pairs_paid']), ''],
                ['Pairs flushed',       number_format($status['pairs_flushed']), 'color:var(--warning)'],
                ['Bonus / pair',        fmt_money($status['pairing_bonus']),  'color:var(--success)'],
              ] as [$k, $v, $s]
            ): ?>
              <div class="d-flex justify-content-between py-1 border-bottom" style="font-size:.8rem;">
                <span class="text-muted"><?= $k ?></span><strong style="<?= $s ?>"><?= $v ?></strong>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Recent Activity -->
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span class="card-title">📋 Recent Activity</span>
        <a href="<?= APP_URL ?>/?page=earnings" class="btn btn-outline-primary btn-sm" style="font-size:.72rem;">View all</a>
      </div>
      <div class="card-body py-0 px-3">
        <?php if (empty($recent)): ?>
          <div class="text-center py-5 text-muted">
            <div style="font-size:2rem;">📭</div>
            <p class="mt-2 mb-0" style="font-size:.85rem;">No activity yet.</p>
          </div>
          <?php else: foreach ($recent as $item):
            $isCredit = $item['status'] === 'credited';
            $typeMap  = ['pairing' => ['🤝', '#ecfdf5', 'var(--success)'], 'direct_referral' => ['👥', '#fff7ed', 'var(--orange)'], 'indirect_referral' => ['🔗', '#f5f3ff', 'var(--purple)'], 'daily_fixed_income' => ['📅', '#eff6ff', 'var(--primary)']];
            [$icon, $bg, $col] = $typeMap[$item['type']] ?? ['💬', '#f4f6fb', 'var(--muted)'];
            $typeName = match ($item['type']) {
              'pairing' => 'Pairing Bonus',
              'direct_referral' => 'Direct Referral',
              'indirect_referral' => 'Indirect — Lvl ' . $item['level'],
              'daily_fixed_income' => 'Daily Fixed Income',
              default => $item['type']
            };
          ?>
            <div class="activity-item">
              <div class="activity-dot" style="background:<?= $bg ?>;color:<?= $col ?>"><?= $icon ?></div>
              <div class="flex-grow-1 min-w-0">
                <div class="activity-desc"><?= e($typeName) ?><?php if ($item['source_username']): ?> <span class="text-muted">via @<?= e($item['source_username']) ?></span><?php endif; ?></div>
                <div class="activity-meta"><?= fmt_datetime($item['created_at']) ?><?php if ($item['pairs_count']): ?> · <?= $item['pairs_count'] ?> pair(s)<?php endif; ?></div>
              </div>
              <div class="activity-amount" style="color:<?= $isCredit ? 'var(--success)' : 'var(--muted)' ?>">
                <?= $isCredit ? '+'  . fmt_money($item['amount']) : '<span class="badge bg-warning-subtle text-warning">Flushed</span>' ?>
              </div>
            </div>
        <?php endforeach;
        endif; ?>
      </div>
    </div>
  </div>
</div>

<?php require 'views/partials/footer.php'; ?>