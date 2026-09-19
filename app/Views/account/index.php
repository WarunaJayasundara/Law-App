<?php
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Response;
use App\Core\View;
use App\Services\PasswordPolicy;

$me = Auth::user();
?>
<div class="nd-page-head">
  <span class="nd-page-head-icon"><i class="bi bi-person-lock"></i></span>
  <div class="nd-page-head-text">
    <h1 class="font-head">My account</h1>
    <p>Signed in as <?= View::e($me['name']) ?> (<?= View::e($me['role_name']) ?>).</p>
  </div>
</div>

<?php if (!empty($forced)): ?>
  <div class="alert alert-danger py-2 small"><i class="bi bi-shield-exclamation me-1"></i>You are still using the default password. Choose a new one to continue.</div>
<?php endif; ?>
<?php if (!empty($success)): ?><div class="alert alert-success py-2 small"><i class="bi bi-check-circle me-1"></i><?= View::e($success) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="alert alert-danger py-2 small"><i class="bi bi-exclamation-circle me-1"></i><?= View::e($error) ?></div><?php endif; ?>

<form method="post" action="<?= Response::url('account/password') ?>" class="card nd-filter-card p-3" style="max-width:520px;">
  <?= Csrf::field() ?>
  <div class="nd-section-title fs-6 mb-3"><i class="bi bi-key"></i>Change password</div>

  <div class="mb-3">
    <label class="form-label small text-muted" for="current_password">Current password</label>
    <input type="password" id="current_password" name="current_password" class="form-control" autocomplete="current-password" required>
  </div>
  <div class="mb-3">
    <label class="form-label small text-muted" for="new_password">New password</label>
    <input type="password" id="new_password" name="new_password" class="form-control" autocomplete="new-password" minlength="<?= PasswordPolicy::MIN_LENGTH ?>" maxlength="<?= PasswordPolicy::MAX_BYTES ?>" required>
    <div class="form-text">At least <?= PasswordPolicy::MIN_LENGTH ?> characters. A few random words make a strong, easy-to-remember password.</div>
  </div>
  <div class="mb-3">
    <label class="form-label small text-muted" for="confirm_password">Confirm new password</label>
    <input type="password" id="confirm_password" name="confirm_password" class="form-control" autocomplete="new-password" required>
  </div>
  <button type="submit" class="btn btn-nd-primary"><i class="bi bi-check2 me-1"></i>Change password</button>
</form>
