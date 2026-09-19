<?php
use App\Core\Csrf;
use App\Core\Response;
use App\Core\View;
use App\Services\SmsTemplate;

$sample = SmsTemplate::render($template, '14367');
$estimate = SmsTemplate::estimate($sample);
?>
<div class="nd-page-head">
  <span class="nd-page-head-icon"><i class="bi bi-gear"></i></span>
  <div class="nd-page-head-text">
    <h1 class="font-head">Settings</h1>
    <p>Choose the wording of the SMS sent to buyers and sellers.</p>
  </div>
</div>

<?php if (!empty($success)): ?><div class="alert alert-success py-2 small"><i class="bi bi-check-circle me-1"></i><?= View::e($success) ?></div><?php endif; ?>
<?php if (!empty($error)): ?><div class="alert alert-danger py-2 small"><i class="bi bi-exclamation-circle me-1"></i><?= View::e($error) ?></div><?php endif; ?>

<div class="row g-3">
  <div class="col-lg-8">
    <form method="post" action="<?= Response::url('settings') ?>" class="card nd-filter-card p-3 mb-3">
      <?= Csrf::field() ?>
      <div class="nd-section-title fs-6 mb-1"><i class="bi bi-chat-square-text"></i>Deed received message</div>
      <p class="text-muted small mb-3">
        Sent automatically to the buyer's and the seller's mobile numbers when a deed is marked <strong>Received</strong>.
        <code>{deed_number}</code> is replaced with that deed's number; everything else is sent exactly as written.
        <?= $isCustom ? 'This is your customised message.' : 'This is the default message.' ?>
      </p>

      <label class="form-label small text-muted" for="smsTemplate">Message</label>
      <textarea name="template" id="smsTemplate" class="form-control" rows="12" maxlength="<?= SmsTemplate::MAX_LENGTH ?>" required><?= View::e($template) ?></textarea>

      <div class="nd-sms-meta" id="smsMeta">
        <span><strong id="smsChars"><?= (int) $estimate['length'] ?></strong> characters</span>
        <span id="smsEncoding"><?= View::e($estimate['encoding']) ?></span>
        <span><strong id="smsParts"><?= (int) $estimate['parts'] ?></strong> SMS per recipient</span>
      </div>
      <p class="text-muted small mb-3">
        Sinhala is sent as Unicode: one SMS holds 70 characters, or 67 per part once the text is longer. A deed sends up to two messages (buyer and seller), so a shorter message costs less. The count above assumes a 5-character deed number; a longer number can add one part when the message is close to a boundary.
      </p>

      <div class="d-flex flex-wrap gap-2">
        <button type="submit" class="btn btn-nd-primary"><i class="bi bi-check2 me-1"></i>Save message</button>
      </div>
    </form>

    <?php if ($isCustom): ?>
    <form method="post" action="<?= Response::url('settings') ?>" class="mb-3" onsubmit="return confirm('Reset the message to the default wording? Your custom text will be replaced.');">
      <?= Csrf::field() ?>
      <input type="hidden" name="reset" value="1">
      <button type="submit" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset to default</button>
    </form>
    <?php endif; ?>
  </div>

  <div class="col-lg-4">
    <div class="card p-3 mb-3">
      <div class="nd-section-title fs-6 mb-2"><i class="bi bi-eye"></i>Preview</div>
      <p class="text-muted small mb-2">As it will read for deed no. 14367.</p>
      <div class="nd-sms-preview" id="smsPreview"><?= View::e($sample) ?></div>
    </div>

    <div class="card p-3">
      <div class="nd-section-title fs-6 mb-2"><i class="bi bi-broadcast"></i>SMS sender</div>
      <dl class="small mb-2">
        <dt class="text-muted fw-semibold">Sender name</dt>
        <dd><?= $senderId !== '' ? View::e($senderId) : '<span class="text-muted">not set</span>' ?></dd>
        <dt class="text-muted fw-semibold">Gateway</dt>
        <dd class="mb-0"><?= $smsConfigured ? 'text.lk (connected)' : '<span class="text-danger">text.lk is not configured</span>' ?></dd>
      </dl>
      <p class="text-muted small mb-0">The sender name and API key live in the server's <code>.env</code> file, not here.</p>
    </div>
  </div>
</div>
<script src="<?= Response::url('assets/js/sms-template.js') ?>"></script>
