<?php
use App\Core\Response;
use App\Core\View;
use App\Models\Deed;
?>
<div class="nd-page-head">
  <span class="nd-page-head-icon"><i class="bi bi-speedometer2"></i></span>
  <div class="nd-page-head-text">
    <h1 class="font-head">Dashboard</h1>
    <p>An overview of buyer and seller deeds tracked for registration.</p>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-3 col-6">
    <div class="card nd-stat nd-stat--total p-3"><span class="nd-stat-icon-chip"><i class="bi bi-journal-text"></i></span><div class="num"><?= (int) $counts['total'] ?></div><div class="label">Total deeds</div></div>
  </div>
  <div class="col-md-3 col-6">
    <div class="card nd-stat nd-stat--submitted p-3"><span class="nd-stat-icon-chip"><i class="bi bi-inbox"></i></span><div class="num"><?= (int) $counts['submitted'] ?></div><div class="label">Submitted</div></div>
  </div>
  <div class="col-md-3 col-6">
    <div class="card nd-stat nd-stat--reviewed p-3"><span class="nd-stat-icon-chip"><i class="bi bi-clipboard-check"></i></span><div class="num"><?= (int) $counts['reviewed'] ?></div><div class="label">Reviewed</div></div>
  </div>
  <div class="col-md-3 col-6">
    <div class="card nd-stat nd-stat--received p-3"><span class="nd-stat-icon-chip"><i class="bi bi-box-seam"></i></span><div class="num"><?= (int) $counts['received'] ?></div><div class="label">Received</div></div>
  </div>
</div>

<div class="card p-3">
  <div class="nd-section-title fs-5 mb-3"><i class="bi bi-clock-history"></i>Recent deeds</div>
  <?php if (empty($recent)): ?>
    <div class="nd-empty">
      <i class="bi bi-journal-text nd-empty-icon"></i>
      <p>No deeds entered yet.</p>
      <a href="<?= Response::url('deeds/create') ?>" class="btn btn-nd-primary btn-sm">Enter a deed</a>
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table nd-table nd-table-stack mb-0">
        <thead><tr><th>Deed no</th><th>Buyer</th><th>Seller</th><th>Registered date</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($recent as $r): ?>
          <tr class="nd-clickable" onclick="location.href='<?= Response::url('deeds/' . $r['id']) ?>'">
            <td data-label="Deed no"><?= View::e($r['deed_number']) ?></td>
            <td data-label="Buyer"><?= View::e($r['buyer_name']) ?></td>
            <td data-label="Seller"><?= View::e($r['seller_name']) ?></td>
            <td data-label="Registered"><?= $r['register_date'] ? View::e(date('d M Y', strtotime($r['register_date']))) : '—' ?></td>
            <td data-label="Status"><span class="badge badge-status status-<?= $r['status'] ?>"><?= View::e(Deed::statusLabel($r['status'])) ?></span></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
