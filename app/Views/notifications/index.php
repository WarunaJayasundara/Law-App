<?php
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\View;
?>
<h1 class="font-head fs-2 mb-1">Notifications</h1>
<p class="text-muted mb-3">Send push notifications via Firebase Cloud Messaging and review notification history.</p>

<?php if (!empty($success)): ?><div class="alert alert-success py-2 small"><?= View::e($success) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="alert alert-danger py-2 small"><?= View::e($error) ?></div><?php endif; ?>

<?php if (Auth::can('notifications.send')): ?>
<div class="card p-3 mb-3">
  <div class="nd-section-title fs-6 mb-3">Send a notification</div>
  <form method="post" action="<?= \App\Core\Response::url('notifications') ?>" style="max-width:560px;">
    <?= Csrf::field() ?>
    <div class="mb-2">
      <label class="form-label small text-muted">Title</label>
      <input type="text" name="title" class="form-control" placeholder="Important Notice" required>
    </div>
    <div class="mb-2">
      <label class="form-label small text-muted">Message</label>
      <textarea name="message" class="form-control" rows="3" placeholder="The Land Registry office will be closed on Friday." required></textarea>
    </div>
    <div class="mb-3">
      <label class="form-label small text-muted">Target</label>
      <select name="target" class="form-select">
        <?php foreach ($topics as $t): ?><option value="<?= View::e($t) ?>"><?= View::e($t) ?></option><?php endforeach; ?>
      </select>
    </div>
    <button type="submit" class="btn btn-nd-primary">Send notification</button>
  </form>
  <p class="text-muted small mt-2 mb-0">Delivery requires Firebase to be configured (see /firebase/SETUP.md). Every attempt is recorded below whether or not it succeeds.</p>
</div>
<?php endif; ?>

<div class="card p-3">
  <div class="nd-section-title fs-6 mb-3">History</div>
  <?php if (empty($history)): ?>
    <p class="text-muted small mb-0">No notifications sent yet.</p>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table nd-table nd-table-stack mb-0">
        <thead><tr><th>Title</th><th>Target</th><th>Sent by</th><th>Status</th><th>Recipients</th><th>When</th></tr></thead>
        <tbody>
        <?php foreach ($history as $n): ?>
          <tr>
            <td data-label="Title"><?= View::e($n['title']) ?></td>
            <td data-label="Target"><code><?= View::e($n['target_value']) ?></code></td>
            <td data-label="Sent by"><?= View::e($n['sent_by_name']) ?></td>
            <td data-label="Status">
              <?php $badgeClass = ['sent' => 'success', 'failed' => 'danger', 'partial' => 'warning', 'pending' => 'secondary'][$n['status']] ?? 'secondary'; ?>
              <span class="badge text-bg-<?= $badgeClass ?>"><?= View::e($n['status']) ?></span>
              <?php if (!empty($n['error_message'])): ?><div class="text-muted small"><?= View::e($n['error_message']) ?></div><?php endif; ?>
            </td>
            <td data-label="Recipients"><?= $n['recipient_count'] !== null ? (int) $n['recipient_count'] : '—' ?></td>
            <td data-label="When" class="text-muted small"><?= View::e(date('d M Y, H:i', strtotime($n['created_at']))) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
