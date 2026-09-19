<?php
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Response;
use App\Core\View;
use App\Models\Deed;

$canUpdate = Auth::can('deeds.update');
$canNotify = Auth::can('notifications.send');
$canArchive = Auth::can('deeds.archive');

$isReviewed = !empty($registration['reviewed']);
$isReceived = !empty($registration['received']);
$steps = [
    ['key' => 'Submitted', 'label' => 'Submitted', 'done' => true],
    ['key' => 'Reviewed', 'label' => 'Reviewed', 'done' => $isReviewed],
    ['key' => 'Received', 'label' => 'Received', 'done' => $isReceived],
];
?>
<a href="<?= Response::url('deeds') ?>" class="back-link">&larr; Back to deed registry</a>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div class="d-flex align-items-center gap-3">
    <span class="nd-page-head-icon"><i class="bi bi-file-earmark-richtext"></i></span>
    <div>
      <h1 class="font-head fs-2 mb-1">Deed no. <?= View::e($deed['deed_number']) ?></h1>
      <p class="text-muted mb-0"><?= View::e($deed['category']) ?></p>
    </div>
  </div>
  <span class="badge badge-status status-<?= $deed['status'] ?>"><?= View::e(Deed::statusLabel($deed['status'])) ?></span>
</div>

<?php if (!empty($success)): ?><div class="alert alert-success py-2 small"><i class="bi bi-check-circle me-1"></i><?= View::e($success) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="alert alert-danger py-2 small"><i class="bi bi-exclamation-circle me-1"></i><?= View::e($error) ?></div><?php endif; ?>

<div class="card p-4 mb-3">
  <div class="nd-stepper">
    <?php foreach ($steps as $i => $step): ?>
      <div class="nd-step <?= $step['done'] ? 'is-done' : '' ?> <?= $deed['status'] === $step['key'] ? 'is-current' : '' ?>">
        <div class="nd-step-dot"><?= $step['done'] ? '<i class="bi bi-check-lg"></i>' : ($i + 1) ?></div>
        <div class="nd-step-label"><?= View::e($step['label']) ?></div>
      </div>
      <?php if ($i < count($steps) - 1): ?><div class="nd-step-line <?= $steps[$i + 1]['done'] ? 'is-done' : '' ?>"></div><?php endif; ?>
    <?php endforeach; ?>
  </div>

  <p class="text-muted small mt-3 mb-3"><i class="bi bi-chat-dots me-1"></i>When this deed is marked <strong>Received</strong>, the buyer and seller are sent the office's confirmation SMS automatically. Nothing else to click.</p>

  <?php if ($canUpdate): ?>
    <div class="d-flex gap-2 mt-1 flex-wrap">
      <form method="post" action="<?= Response::url('deeds/' . $deed['id'] . '/review') ?>">
        <?= Csrf::field() ?>
        <button type="submit" class="btn btn-nd-primary" <?= $isReviewed ? 'disabled' : '' ?>>
          <i class="bi bi-clipboard-check me-1"></i><?= $isReviewed ? 'Reviewed' : 'Mark reviewed' ?>
        </button>
      </form>
      <form method="post" action="<?= Response::url('deeds/' . $deed['id'] . '/receive') ?>">
        <?= Csrf::field() ?>
        <button type="submit" class="btn btn-nd-primary" <?= (!$isReviewed || $isReceived) ? 'disabled' : '' ?>>
          <i class="bi bi-box-seam me-1"></i><?= $isReceived ? 'Received' : 'Mark received' ?>
        </button>
      </form>
      <?php if ($canNotify): ?>
        <form method="post" action="<?= Response::url('deeds/' . $deed['id'] . '/notify') ?>">
          <?= Csrf::field() ?>
          <button type="submit" class="btn btn-outline-secondary" <?= $isReceived ? '' : 'disabled' ?> title="Send the confirmation SMS again, for example if the automatic one did not reach someone.">
            <i class="bi bi-arrow-repeat me-1"></i>Resend SMS
          </button>
        </form>
      <?php endif; ?>
    </div>
    <?php if (!empty($registration['notification_sent'])): ?>
      <div class="mt-2 p-2 small nd-note">
        <i class="bi bi-chat-dots me-1"></i>Last SMS attempt: <?= View::e(date('d M Y, H:i', strtotime($registration['notification_sent_at']))) ?>. The result of each send is shown at the top of this page and recorded in the audit log.
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="card p-3">
      <div class="nd-section-title fs-6 mb-3"><i class="bi bi-file-earmark-text"></i>Buyer, seller &amp; deed details</div>
      <form method="post" action="<?= Response::url('deeds/' . $deed['id'] . '/details') ?>">
        <?= Csrf::field() ?>
        <fieldset <?= $canUpdate ? '' : 'disabled' ?>>
          <div class="row g-2 mb-2">
            <div class="col-6"><label class="form-label small text-muted">Buyer name</label><input class="form-control form-control-sm" value="<?= View::e($deed['buyer_name']) ?>" disabled></div>
            <div class="col-6"><label class="form-label small text-muted">Buyer NIC / ID no.</label><input class="form-control form-control-sm" value="<?= View::e(str_starts_with((string) $deed['buyer_nic'], 'nic_') ? '' : $deed['buyer_nic']) ?>" placeholder="Not provided" disabled></div>
          </div>
          <div class="row g-2 mb-2">
            <div class="col-6"><label class="form-label small text-muted">Seller name</label><input class="form-control form-control-sm" value="<?= View::e($deed['seller_name']) ?>" disabled></div>
            <div class="col-6"><label class="form-label small text-muted">Seller NIC / ID no.</label><input class="form-control form-control-sm" value="<?= View::e(str_starts_with((string) $deed['seller_nic'], 'nic_') ? '' : $deed['seller_nic']) ?>" placeholder="Not provided" disabled></div>
          </div>
          <div class="row g-2 mb-2">
            <div class="col-6"><label class="form-label small text-muted" for="buyer_phone">Buyer mobile no.</label><input type="text" id="buyer_phone" name="buyer_phone" class="form-control form-control-sm" inputmode="tel" placeholder="07XXXXXXXX" value="<?= View::e($deed['buyer_mobile'] ?? '') ?>"></div>
            <div class="col-6"><label class="form-label small text-muted" for="seller_phone">Seller mobile no.</label><input type="text" id="seller_phone" name="seller_phone" class="form-control form-control-sm" inputmode="tel" placeholder="07XXXXXXXX" value="<?= View::e($deed['seller_mobile'] ?? '') ?>"></div>
          </div>
          <div class="form-text mb-2">The confirmation SMS is sent to these numbers when the deed is marked Received.</div>
          <hr>
          <div class="row g-2 mb-2">
            <div class="col-6"><label class="form-label small text-muted">Deed no.</label><input type="text" name="deed_no" class="form-control form-control-sm" value="<?= View::e($deed['deed_number']) ?>" required></div>
            <div class="col-6">
              <label class="form-label small text-muted">Deed category</label>
              <select name="deed_category" class="form-select form-select-sm">
                <?php foreach ($categories as $c): ?><option value="<?= View::e($c) ?>" <?= $deed['category'] === $c ? 'selected' : '' ?>><?= View::e($c) ?></option><?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="mb-2"><label class="form-label small text-muted">Folio no.</label><input type="text" name="folio_number" class="form-control form-control-sm" value="<?= View::e($deed['folio_number'] ?? '') ?>"></div>
          <div class="row g-2 mb-2">
            <div class="col-6"><label class="form-label small text-muted">Amount</label><input type="text" name="amount" class="form-control form-control-sm" value="<?= View::e($deed['amount'] !== null ? (string) $deed['amount'] : '') ?>"></div>
            <div class="col-6"><label class="form-label small text-muted">Value</label><input type="text" name="value" class="form-control form-control-sm" value="<?= View::e($deed['value'] !== null ? (string) $deed['value'] : '') ?>"></div>
          </div>
          <div class="mb-3"><label class="form-label small text-muted">Other document numbers</label><textarea name="other_document_numbers" class="form-control form-control-sm" rows="2"><?= View::e($deed['other_document_numbers'] ?? '') ?></textarea></div>
          <?php if ($canUpdate): ?><button type="submit" class="btn btn-outline-secondary btn-sm">Save details</button><?php endif; ?>
        </fieldset>
      </form>

      <?php if ($canArchive): ?>
        <form method="post" action="<?= Response::url('deeds/' . $deed['id']) ?>" class="text-end mt-3" onsubmit="return confirm('Archive this deed? It will be hidden from the registry but not permanently deleted.');">
          <?= Csrf::field() ?>
          <input type="hidden" name="_method" value="DELETE">
          <button type="submit" class="btn btn-outline-danger btn-sm">Archive deed</button>
        </form>
      <?php endif; ?>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card p-3">
      <div class="nd-section-title fs-6 mb-3"><i class="bi bi-building"></i>Registration details</div>
      <form method="post" action="<?= Response::url('deeds/' . $deed['id'] . '/registration') ?>">
        <?= Csrf::field() ?>
        <fieldset <?= $canUpdate ? '' : 'disabled' ?>>
          <div class="row g-2 mb-2">
            <div class="col-6"><label class="form-label small text-muted">Register date</label><input type="date" name="register_date" class="form-control form-control-sm" value="<?= View::e($registration['register_date'] ?? '') ?>"></div>
            <div class="col-6"><label class="form-label small text-muted">Day book no.</label><input type="text" name="day_book_number" class="form-control form-control-sm" value="<?= View::e($registration['day_book_number'] ?? '') ?>"></div>
          </div>
          <div class="mb-2"><label class="form-label small text-muted">Folio no. after registration</label><input type="text" name="new_folio_number" class="form-control form-control-sm" value="<?= View::e($registration['new_folio_number'] ?? '') ?>"></div>
          <div class="mb-3"><label class="form-label small text-muted">Notes</label><textarea name="notes" class="form-control form-control-sm" rows="2"><?= View::e($registration['notes'] ?? '') ?></textarea></div>
          <?php if ($canUpdate): ?><button type="submit" class="btn btn-outline-secondary btn-sm">Save registration details</button><?php endif; ?>
        </fieldset>
      </form>
      <p class="text-muted small mt-3 mb-0">These fields are reference data only — use the buttons above to move the deed through Reviewed / Received.</p>
    </div>
  </div>
</div>
