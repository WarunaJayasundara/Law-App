<?php
use App\Core\View;
?>
<div class="nd-page-head">
  <span class="nd-page-head-icon"><i class="bi bi-shield-check"></i></span>
  <div class="nd-page-head-text">
    <h1 class="font-head">Audit log</h1>
    <p>Every sensitive action recorded across the system, most recent first (latest 200 shown).</p>
  </div>
</div>

<div class="card p-3">
  <?php if (empty($logs)): ?>
    <div class="nd-empty"><i class="bi bi-shield-check nd-empty-icon"></i><p class="mb-0">No audit entries yet.</p></div>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table nd-table nd-table-stack mb-0 small">
      <thead><tr><th>When</th><th>User</th><th>Action</th><th>Entity</th><th>IP</th></tr></thead>
      <tbody>
      <?php foreach ($logs as $log): ?>
        <tr>
          <td data-label="When" class="text-muted"><?= View::e(date('d M Y, H:i:s', strtotime($log['created_at']))) ?></td>
          <td data-label="User"><?= View::e($log['user_name'] ?? 'system') ?></td>
          <td data-label="Action"><code><?= View::e($log['action']) ?></code></td>
          <td data-label="Entity"><?= View::e($log['entity_type']) ?><?= $log['entity_id'] ? ' #' . (int) $log['entity_id'] : '' ?></td>
          <td data-label="IP" class="text-muted"><?= View::e($log['ip_address'] ?? '—') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
