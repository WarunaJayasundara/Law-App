<?php
use App\Core\Auth;
use App\Core\Response;
use App\Core\View;
use App\Models\Deed;
$rows = $result['rows'];
?>
<div class="d-flex justify-content-between align-items-end flex-wrap gap-2 mb-3">
  <div>
    <h1 class="font-head fs-2 mb-1">Deed registry</h1>
    <p class="text-muted mb-0">Buyer and seller records tracked through to registration.</p>
  </div>
  <?php if (Auth::can('deeds.create')): ?>
    <a href="<?= Response::url('deeds/create') ?>" class="btn btn-nd-primary">+ Enter deed</a>
  <?php endif; ?>
</div>

<form method="get" action="<?= Response::url('deeds') ?>" class="card p-3 mb-3">
  <div class="row g-2">
    <div class="col-md-4">
      <input type="text" name="q" value="<?= View::e($filters['q']) ?>" class="form-control" placeholder="Search deed no., NIC / ID, or name">
    </div>
    <div class="col-md-2">
      <select name="category" class="form-select">
        <option value="">All categories</option>
        <?php foreach ($categories as $c): ?>
          <option value="<?= View::e($c) ?>" <?= $filters['category'] === $c ? 'selected' : '' ?>><?= View::e($c) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <select name="status" class="form-select">
        <option value="">All statuses</option>
        <?php foreach ($statuses as $s): ?>
          <option value="<?= View::e($s) ?>" <?= $filters['status'] === $s ? 'selected' : '' ?>><?= View::e(Deed::statusLabel($s)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <input type="date" name="from" value="<?= View::e($filters['from']) ?>" class="form-control" title="Registered from">
    </div>
    <div class="col-md-2">
      <input type="date" name="to" value="<?= View::e($filters['to']) ?>" class="form-control" title="Registered to">
    </div>
  </div>
  <div class="mt-2">
    <button type="submit" class="btn btn-outline-secondary btn-sm">Filter</button>
    <a href="<?= Response::url('deeds') ?>" class="btn btn-link btn-sm">Clear</a>
  </div>
</form>

<div class="card p-3">
  <?php if (empty($rows)): ?>
    <div class="nd-empty">
      <i class="bi <?= $result['total'] === 0 && $filters['q'] === '' ? 'bi-journal-text' : 'bi-search' ?> nd-empty-icon"></i>
      <p><?= $result['total'] === 0 && $filters['q'] === '' ? 'No deeds entered yet.' : 'No matching deeds found.' ?></p>
      <?php if (Auth::can('deeds.create')): ?>
        <a href="<?= Response::url('deeds/create') ?>" class="btn btn-nd-primary btn-sm">Enter a deed</a>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table nd-table nd-table-stack mb-0">
        <thead><tr><th>Deed no</th><th>Category</th><th>Buyer</th><th>Seller</th><th>Folio no</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr class="nd-clickable" onclick="location.href='<?= Response::url('deeds/' . $r['id']) ?>'">
            <td data-label="Deed no"><?= View::e($r['deed_number']) ?></td>
            <td data-label="Category"><?= View::e($r['category']) ?></td>
            <td data-label="Buyer"><?= View::e($r['buyer_name']) ?></td>
            <td data-label="Seller"><?= View::e($r['seller_name']) ?></td>
            <td data-label="Folio no"><?= View::e($r['folio_number'] ?? '—') ?></td>
            <td data-label="Status"><span class="badge badge-status status-<?= $r['status'] ?>"><?= View::e(Deed::statusLabel($r['status'])) ?></span></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if ($result['lastPage'] > 1): ?>
      <nav class="mt-3">
        <ul class="pagination pagination-sm mb-0">
          <?php for ($p = 1; $p <= $result['lastPage']; $p++): ?>
            <?php $query = array_merge($filters, ['page' => $p]); ?>
            <li class="page-item <?= $p === $result['page'] ? 'active' : '' ?>">
              <a class="page-link" href="<?= Response::url('deeds') ?>?<?= http_build_query($query) ?>"><?= $p ?></a>
            </li>
          <?php endfor; ?>
        </ul>
      </nav>
    <?php endif; ?>
  <?php endif; ?>
</div>
