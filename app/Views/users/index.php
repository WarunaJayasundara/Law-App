<?php
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Response;
use App\Core\View;
?>
<h1 class="font-head fs-2 mb-1">Users</h1>
<p class="text-muted mb-3">Manage staff and admin accounts and their roles.</p>

<?php if (!empty($success)): ?><div class="alert alert-success py-2 small"><?= View::e($success) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="alert alert-danger py-2 small"><?= View::e($error) ?></div><?php endif; ?>

<div class="card p-3 mb-3">
  <div class="nd-section-title fs-6 mb-3">Add user</div>
  <form method="post" action="<?= Response::url('users') ?>" class="row g-2 align-items-end">
    <?= Csrf::field() ?>
    <div class="col-md-2"><label class="form-label small text-muted">Name</label><input type="text" name="name" class="form-control form-control-sm" required></div>
    <div class="col-md-2"><label class="form-label small text-muted">Email</label><input type="email" name="email" class="form-control form-control-sm" required></div>
    <div class="col-md-2"><label class="form-label small text-muted">Username</label><input type="text" name="username" class="form-control form-control-sm" required></div>
    <div class="col-md-2"><label class="form-label small text-muted">Password</label><input type="password" name="password" class="form-control form-control-sm" minlength="10" required></div>
    <div class="col-md-2">
      <label class="form-label small text-muted">Role</label>
      <select name="role_id" class="form-select form-select-sm">
        <?php foreach ($roles as $r): ?><option value="<?= (int) $r['id'] ?>"><?= View::e($r['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2"><button type="submit" class="btn btn-nd-primary btn-sm w-100">Create</button></div>
  </form>
</div>

<div class="card p-3">
  <div class="table-responsive">
    <table class="table nd-table nd-table-stack mb-0">
      <thead><tr><th>Name</th><th>Email</th><th>Username</th><th>Role</th><th>Status</th><th>Last login</th></tr></thead>
      <tbody>
      <?php foreach ($users as $u): ?>
        <tr>
          <td data-label="Name"><?= View::e($u['name']) ?></td>
          <td data-label="Email"><?= View::e($u['email']) ?></td>
          <td data-label="Username"><?= View::e($u['username']) ?></td>
          <td data-label="Role"><?php if ($u['id'] === Auth::id()): ?><?= View::e($u['role_name']) ?><?php else: ?>
            <form method="post" action="<?= Response::url('users/' . $u['id']) ?>" class="d-flex gap-1">
              <?= Csrf::field() ?>
              <input type="hidden" name="status" value="<?= View::e($u['status']) ?>">
              <select name="role_id" class="form-select form-select-sm" onchange="this.form.submit()">
                <?php foreach ($roles as $r): ?><option value="<?= (int) $r['id'] ?>" <?= (int) $r['id'] === (int) $u['role_id'] ? 'selected' : '' ?>><?= View::e($r['name']) ?></option><?php endforeach; ?>
              </select>
            </form>
          <?php endif; ?></td>
          <td data-label="Status">
            <?php if ($u['id'] === Auth::id()): ?>
              <span class="badge text-bg-secondary"><?= View::e($u['status']) ?></span>
            <?php else: ?>
              <form method="post" action="<?= Response::url('users/' . $u['id']) ?>">
                <?= Csrf::field() ?>
                <input type="hidden" name="role_id" value="<?= (int) $u['role_id'] ?>">
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                  <option value="active" <?= $u['status'] === 'active' ? 'selected' : '' ?>>active</option>
                  <option value="disabled" <?= $u['status'] === 'disabled' ? 'selected' : '' ?>>disabled</option>
                </select>
              </form>
            <?php endif; ?>
          </td>
          <td data-label="Last login" class="text-muted small"><?= $u['last_login_at'] ? View::e(date('d M Y, H:i', strtotime($u['last_login_at']))) : 'Never' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
