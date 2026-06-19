<?php require 'views/partials/head.php'; ?>
<?php require 'views/partials/sidebar_admin.php'; ?>
<div class="main-content">
<style>
  .admin-order-card {
    transition: box-shadow .15s ease, transform .05s ease;
  }
  .admin-order-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,.06);
  }
  .admin-order-card:active {
    transform: translateY(1px);
  }
  .status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    display: inline-block;
    margin-right: .35rem;
  }
  .status-pending .status-dot { background: #f59e0b; }
  .status-paid .status-dot { background: #3b82f6; }
  .status-approved .status-dot { background: #10b981; }
  .status-rejected .status-dot { background: #ef4444; }
  .status-cancelled .status-dot { background: #9ca3af; }

  .stat-card {
    border: 1px solid #e5e7eb;
    border-radius: .75rem;
    padding: 1rem 1.25rem;
    background: #fff;
    transition: all .15s ease;
  }
  .stat-card:hover { border-color: #d1d5db; }
  .stat-card.active { border-color: var(--primary); background: #f8faff; }
  .stat-card .stat-value { font-size: 1.5rem; font-weight: 700; line-height: 1; }
  .stat-card .stat-label { font-size: .75rem; color: #6b7280; text-transform: uppercase; letter-spacing: .025em; }

  .proof-thumb {
    width: 40px; height: 40px; object-fit: cover; border-radius: 6px;
    border: 1px solid #e5e7eb; cursor: pointer; transition: transform .1s ease;
  }
  .proof-thumb:hover { transform: scale(1.05); }
  .product-thumb-xs {
    width: 36px; height: 36px; object-fit: cover; border-radius: 6px;
    border: 1px solid #e5e7eb; flex-shrink: 0;
  }
  .product-thumb-placeholder-xs {
    width: 36px; height: 36px; border-radius: 6px; border: 1px solid #e5e7eb;
    display: flex; align-items: center; justify-content: center;
    background: #f8fafc; font-size: .9rem; flex-shrink: 0;
  }

  .action-btn {
    padding: .25rem .5rem; font-size: .75rem; font-weight: 500;
    border-radius: .375rem; border: 1px solid; transition: all .1s ease;
    display: inline-flex; align-items: center; gap: .25rem;
  }
  .action-btn:hover { transform: translateY(-1px); }
  .action-btn:active { transform: translateY(0); }

  .order-id { font-family: var(--font-mono, monospace); font-weight: 600; font-size: .875rem; }
  .order-amount { font-family: var(--font-mono, monospace); font-weight: 600; font-size: .9rem; }
  .order-amount .pv { font-size: .75rem; color: #10b981; font-weight: 500; }

  .table-orders > thead > tr > th {
    font-size: .7rem; text-transform: uppercase; letter-spacing: .05em;
    font-weight: 600; color: #6b7280; padding: .6rem .5rem;
    background: #f9fafb; border-bottom: 1px solid #e5e7eb;
  }
  .table-orders > tbody > tr > td {
    padding: .65rem .5rem; vertical-align: middle; font-size: .82rem;
    border-bottom: 1px solid #f3f4f6;
  }
  .table-orders > tbody > tr:last-child > td { border-bottom: none; }
  .table-orders > tbody > tr:hover { background: #f9fafb; }

  .empty-state { text-align: center; padding: 3rem 1rem; }
  .empty-state-icon { font-size: 3rem; opacity: .15; margin-bottom: .75rem; }
  .empty-state-text { color: #9ca3af; font-size: .875rem; }

  @media (max-width: 1200px) {
    .table-orders .hide-sm { display: none; }
  }
  @media (max-width: 992px) {
    .table-orders .hide-md { display: none; }
  }
  @media (max-width: 768px) {
    .table-orders .hide-xs { display: none; }
  }
</style>

  <?php require 'views/partials/topbar.php'; ?>
  <div class="page-content">
  <?= render_flash() ?>

  <!-- Header -->
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
      <h4 class="mb-1 fw-bold">Repeat Purchases</h4>
      <p class="text-muted mb-0" style="font-size:.82rem;">Review payment proofs, mark paid, and approve to distribute PV</p>
    </div>
  </div>

  <!-- Stats Cards -->
  <?php
    $totalCount = $result['total'] ?? 0;
    $pendingCount = 0;
    $paidCount = 0;
    $approvedCount = 0;
    // If we have all data, count statuses; otherwise we'd need separate queries
    // For now, we estimate based on current view
  ?>
  <div class="row g-2 mb-4">
    <div class="col-6 col-md-3">
      <a href="<?= APP_URL ?>/?page=admin_repeat_purchases&status=pending" class="text-decoration-none">
        <div class="stat-card <?= $status === 'pending' ? 'active' : '' ?>">
          <div class="stat-value text-warning"><?= $status === 'pending' ? $totalCount : '—' ?></div>
          <div class="stat-label">Pending</div>
        </div>
      </a>
    </div>
    <div class="col-6 col-md-3">
      <a href="<?= APP_URL ?>/?page=admin_repeat_purchases&status=paid" class="text-decoration-none">
        <div class="stat-card <?= $status === 'paid' ? 'active' : '' ?>">
          <div class="stat-value text-info"><?= $status === 'paid' ? $totalCount : '—' ?></div>
          <div class="stat-label">Paid</div>
        </div>
      </a>
    </div>
    <div class="col-6 col-md-3">
      <a href="<?= APP_URL ?>/?page=admin_repeat_purchases&status=all" class="text-decoration-none">
        <div class="stat-card <?= $status === 'all' ? 'active' : '' ?>">
          <div class="stat-value text-dark"><?= $totalCount ?></div>
          <div class="stat-label">Total</div>
        </div>
      </a>
    </div>
    <div class="col-6 col-md-3">
      <div class="stat-card" style="border-color: #e5e7eb;">
        <div class="stat-value text-success"><?= number_format((float)($result['data'][0]['total_pv'] ?? 0), 0) ?>+</div>
        <div class="stat-label">PV This Page</div>
      </div>
    </div>
  </div>

  <!-- Filter Tabs (compact) -->
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div class="btn-group btn-group-sm">
      <a href="<?= APP_URL ?>/?page=admin_repeat_purchases&status=pending" class="btn <?= $status === 'pending' ? 'btn-primary' : 'btn-outline-secondary' ?>">
        <span class="status-dot" style="background:#f59e0b;"></span>Pending
      </a>
      <a href="<?= APP_URL ?>/?page=admin_repeat_purchases&status=paid" class="btn <?= $status === 'paid' ? 'btn-primary' : 'btn-outline-secondary' ?>">
        <span class="status-dot" style="background:#3b82f6;"></span>Paid
      </a>
      <a href="<?= APP_URL ?>/?page=admin_repeat_purchases&status=all" class="btn <?= $status === 'all' ? 'btn-primary' : 'btn-outline-secondary' ?>">
        <span class="status-dot" style="background:#9ca3af;"></span>All
      </a>
    </div>
    <?php require 'views/partials/rows_per_page.php'; ?>
  </div>

  <!-- Orders Table -->
  <div class="card">
    <div class="table-responsive">
      <table class="table table-orders mb-0">
        <thead>
          <tr>
            <th style="padding-left:1rem;">Order</th>
            <th>Product</th>
            <th class="text-end">Amount</th>
            <th class="text-center hide-xs">Proof</th>
            <th class="text-center">Status</th>
            <th class="text-center hide-sm">Date</th>
            <th class="text-end" style="padding-right:1rem;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($result['data'])): ?>
            <tr>
              <td colspan="7">
                <div class="empty-state">
                  <div class="empty-state-icon">🛒</div>
                  <div class="empty-state-text">No <?= e($status) ?> orders found.</div>
                  <a href="<?= APP_URL ?>/?page=admin_repeat_purchases&status=all" class="btn btn-sm btn-outline-primary mt-2">View All Orders</a>
                </div>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($result['data'] as $rp): ?>
              <tr>
                <!-- Order + Member -->
                <td style="padding-left:1rem; min-width:160px;">
                  <div class="order-id">#<?= (int)$rp['id'] ?></div>
                  <a href="<?= APP_URL ?>/?page=admin_user_view&id=<?= (int)$rp['member_id'] ?>" class="text-decoration-none" style="font-size:.75rem;color:#6b7280;">
                    @<?= e($rp['member_username'] ?? '—') ?>
                  </a>
                  <?php if (!empty($rp['member_full_name'])): ?>
                    <div class="text-muted" style="font-size:.7rem;"><?= e($rp['member_full_name']) ?></div>
                  <?php endif; ?>
                </td>

                <!-- Product -->
                <td style="min-width:180px;">
                  <div class="d-flex align-items-center gap-2">
                    <?php if (!empty($rp['product_image'])): ?>
                      <a href="<?= APP_URL ?>/uploads/<?= e($rp['product_image']) ?>" target="_blank" rel="noopener">
                        <img src="<?= APP_URL ?>/uploads/<?= e($rp['product_image']) ?>" alt="" class="product-thumb-xs" loading="lazy">
                      </a>
                    <?php else: ?>
                      <div class="product-thumb-placeholder-xs">🛍️</div>
                    <?php endif; ?>
                    <div class="min-w-0">
                      <div class="text-truncate" style="font-size:.82rem;max-width:200px;"><?= e($rp['product_name'] ?? '—') ?></div>
                      <div class="text-muted" style="font-size:.7rem;">
                        Qty: <?= (int)($rp['quantity'] ?? 0) ?>
                        <?php if ((int)($rp['item_count'] ?? 1) > 1): ?>
                          <span class="text-primary">&middot; +<?= (int)$rp['item_count'] - 1 ?> more</span>
                        <?php endif; ?>
                      </div>
                    </div>
                  </div>
                </td>

                <!-- Amount (Price + PV) -->
                <td class="text-end" style="min-width:120px;">
                  <div class="order-amount"><?= fmt_money($rp['total_price']) ?></div>
                  <div class="pv"><?= number_format((float)$rp['total_pv'], 2) ?> PV</div>
                </td>

                <!-- Proof -->
                <td class="text-center hide-xs">
                  <?php if (!empty($rp['proof_image'])): ?>
                    <a href="<?= APP_URL ?>/uploads/<?= e($rp['proof_image']) ?>" target="_blank" rel="noopener" title="View proof">
                      <img src="<?= APP_URL ?>/uploads/<?= e($rp['proof_image']) ?>" alt="Proof" class="proof-thumb" loading="lazy">
                    </a>
                  <?php else: ?>
                    <span class="text-muted" style="font-size:.75rem;">—</span>
                  <?php endif; ?>
                </td>

                <!-- Status -->
                <td class="text-center" style="min-width:100px;">
                  <?php
                    $statusClass = match($rp['status']) {
                      'pending' => 'status-pending',
                      'paid' => 'status-paid',
                      'approved' => 'status-approved',
                      'rejected' => 'status-rejected',
                      'cancelled' => 'status-cancelled',
                      default => ''
                    };
                    $statusLabel = match($rp['status']) {
                      'pending' => 'Pending',
                      'paid' => 'Paid',
                      'approved' => 'Approved',
                      'rejected' => 'Rejected',
                      'cancelled' => 'Cancelled',
                      default => ucfirst($rp['status'])
                    };
                    $statusBg = match($rp['status']) {
                      'pending' => 'bg-warning-subtle text-warning',
                      'paid' => 'bg-info-subtle text-info',
                      'approved' => 'bg-success-subtle text-success',
                      'rejected' => 'bg-danger-subtle text-danger',
                      'cancelled' => 'bg-secondary-subtle text-secondary',
                      default => 'bg-light text-muted'
                    };
                  ?>
                  <span class="badge <?= $statusBg ?> <?= $statusClass ?>" style="font-size:.7rem;padding:.35rem .6rem;">
                    <span class="status-dot"></span><?= $statusLabel ?>
                  </span>
                </td>

                <!-- Date -->
                <td class="text-center hide-sm" style="font-size:.75rem; color:#6b7280; min-width:90px;">
                  <?= fmt_date($rp['created_at']) ?>
                </td>

                <!-- Actions -->
                <td class="text-end" style="padding-right:1rem; min-width:160px;">
                  <?php if ($rp['status'] === 'pending'): ?>
                    <form method="POST" action="<?= APP_URL ?>/?page=admin_mark_repeat_purchases" class="d-inline">
                      <?= csrf_field() ?>
                      <input type="hidden" name="id" value="<?= (int)$rp['id'] ?>">
                      <button type="submit"
                        class="action-btn btn-info text-white border-0"
                        <?= empty($rp['proof_image']) ? 'disabled title="No proof uploaded"' : '' ?>
                        style="background:#0ea5e9;">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        Mark Paid
                      </button>
                    </form>
                    <form method="POST" action="<?= APP_URL ?>/?page=admin_reject_repeat_purchase" class="d-inline" onsubmit="return confirm('Reject this order? No PV will be distributed.')">
                      <?= csrf_field() ?>
                      <input type="hidden" name="id" value="<?= (int)$rp['id'] ?>">
                      <button type="submit" class="action-btn btn-outline-danger" style="border-color:#ef4444;color:#ef4444;background:#fff;">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                        Reject
                      </button>
                    </form>
                  <?php elseif ($rp['status'] === 'paid'): ?>
                    <form method="POST" action="<?= APP_URL ?>/?page=admin_approve_repeat_purchase" class="d-inline">
                      <?= csrf_field() ?>
                      <input type="hidden" name="id" value="<?= (int)$rp['id'] ?>">
                      <button type="submit" class="action-btn btn-success text-white border-0" style="background:#10b981;">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        Approve
                      </button>
                    </form>
                    <form method="POST" action="<?= APP_URL ?>/?page=admin_reject_repeat_purchase" class="d-inline" onsubmit="return confirm('Reject this order? No PV will be distributed.')">
                      <?= csrf_field() ?>
                      <input type="hidden" name="id" value="<?= (int)$rp['id'] ?>">
                      <button type="submit" class="action-btn btn-outline-danger" style="border-color:#ef4444;color:#ef4444;background:#fff;">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                        Reject
                      </button>
                    </form>
                  <?php else: ?>
                    <span class="text-muted" style="font-size:.75rem;">No action</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Pagination -->
  <?php if (!empty($result['total_pages']) && $result['total_pages'] > 1): ?>
    <div class="mt-3">
      <?= pagination_links($result, APP_URL . '/?page=admin_repeat_purchases&status=' . e($status) . '&per_page=' . per_page()) ?>
    </div>
  <?php endif; ?>
</div>
  </div>
</div>

<?php require 'views/partials/footer.php'; ?>