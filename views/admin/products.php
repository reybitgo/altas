<?php

/**
 * @file   views/admin/products.php
 * @brief  Product catalog management (Phase 5)
 */
?>
<?php $pageTitle = 'Products'; ?>
<?php require 'views/partials/head.php'; ?>
<?php require 'views/partials/sidebar_admin.php'; ?>
<div class="main-content">
  <?php require 'views/partials/topbar.php'; ?>
  <div class="page-content">
    <?= render_flash() ?>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
      <div>
        <h4 class="mb-0">Products</h4>
        <p class="text-muted mb-0" style="font-size:.8rem;">Manage repeat-purchase products and their PV values</p>
      </div>
      <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#productModal" onclick="resetProductForm()">
        + New Product
      </button>
    </div>

    <div class="card">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead style="background:#f8fafc;">
            <tr>
              <th style="padding-left:1.25rem;">Product</th>
              <th class="text-end">Price</th>
              <th class="text-end">PV Value</th>
              <th class="text-center">Status</th>
              <th class="text-end" style="padding-right:1.25rem;width:120px;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($products)): ?>
              <tr>
                <td colspan="5" class="text-center py-5 text-muted">
                  <div style="font-size:2rem;opacity:.3;margin-bottom:.5rem;">🛍️</div>
                  <div>No products yet.</div>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($products as $p): ?>
                <tr>
                  <td style="padding-left:1.25rem;">
                    <div class="fw-semibold"><?= e($p['name']) ?></div>
                    <div class="text-muted" style="font-size:.7rem;">ID: <?= (int)$p['id'] ?></div>
                  </td>
                  <td class="text-end font-mono"><?= fmt_money($p['price']) ?></td>
                  <td class="text-end font-mono"><?= number_format((float)$p['pv_value'], 2) ?></td>
                  <td class="text-center">
                    <?php if ($p['status'] === 'active'): ?>
                      <span class="badge bg-success-subtle text-success" style="font-size:.72rem;">● Active</span>
                    <?php else: ?>
                      <span class="badge bg-secondary-subtle text-secondary" style="font-size:.72rem;">○ Inactive</span>
                    <?php endif; ?>
                  </td>
                  <td class="text-end" style="padding-right:1.25rem;">
                    <a href="<?= APP_URL ?>/?page=admin_products&edit=<?= (int)$p['id'] ?>" class="btn btn-sm btn-outline-primary me-1">Edit</a>
                    <form method="POST" action="<?= APP_URL ?>/?page=admin_delete_product" class="d-inline" onsubmit="return confirm('Delete this product?');">
                      <?= csrf_field() ?>
                      <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                      <button type="submit" class="btn btn-sm btn-outline-danger">Del</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Product Modal -->
<div class="modal fade" id="productModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="productModalTitle">➕ New Product</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form method="POST" action="<?= APP_URL ?>/?page=admin_save_product" id="productForm">
          <?= csrf_field() ?>
          <input type="hidden" name="product_id" id="productId" value="<?= e($editProduct['id'] ?? '') ?>">

          <div class="mb-3">
            <label class="form-label">Product Name <span class="text-danger">*</span></label>
            <input type="text" name="name" id="prodName" class="form-control" value="<?= e($editProduct['name'] ?? '') ?>" required>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label">Price (₱) <span class="text-danger">*</span></label>
              <input type="number" name="price" id="prodPrice" class="form-control" inputmode="decimal" min="0" step="0.01" value="<?= e($editProduct['price'] ?? '') ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">PV Value <span class="text-danger">*</span></label>
              <input type="number" name="pv_value" id="prodPv" class="form-control" inputmode="decimal" min="0" step="0.01" value="<?= e($editProduct['pv_value'] ?? '') ?>" required>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">Status</label>
            <select name="status" id="prodStatus" class="form-select">
              <option value="active" <?= (($editProduct['status'] ?? 'active') === 'active') ? 'selected' : '' ?>>🟢 Active</option>
              <option value="inactive" <?= (($editProduct['status'] ?? '') === 'inactive') ? 'selected' : '' ?>>⚪ Inactive</option>
            </select>
          </div>
        </form>
      </div>
      <div class="modal-footer" style="border-top:1px solid #f1f5f9;">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary" form="productForm" id="prodSubmitBtn">
          <?= ($editProduct ?? null) ? '💾 Update Product' : '➕ Create Product' ?>
        </button>
      </div>
    </div>
  </div>
</div>

<script>
  function resetProductForm() {
    const form = document.getElementById('productForm');
    form.reset();
    document.getElementById('productModalTitle').textContent = '➕ New Product';
    document.getElementById('productId').value = '';
    document.getElementById('prodSubmitBtn').textContent = '➕ Create Product';
  }

  <?php if ($editProduct): ?>
  document.addEventListener('DOMContentLoaded', function() {
    const modalEl = document.getElementById('productModal');
    const modal = new bootstrap.Modal(modalEl);
    document.getElementById('productModalTitle').textContent = '✏️ Edit Product';
    document.getElementById('prodSubmitBtn').textContent = '💾 Update Product';
    modal.show();
  });
  <?php endif; ?>

  document.getElementById('productForm').addEventListener('submit', function() {
    const btn = document.getElementById('prodSubmitBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving…';
  });
</script>

<?php require 'views/partials/footer.php'; ?>
