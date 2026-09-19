<?php
use App\Core\Csrf;
use App\Core\Response;
use App\Core\View;
?>
<a href="<?= Response::url('deeds') ?>" class="back-link">&larr; Back to deed registry</a>
<h1 class="font-head fs-2 mb-3">Enter deed</h1>

<?php if (!empty($errors)): ?>
  <div class="alert alert-danger py-2 small">
    <?php foreach ($errors as $e): ?><div><?= View::e($e) ?></div><?php endforeach; ?>
  </div>
<?php endif; ?>

<form method="post" action="<?= Response::url('deeds') ?>" class="card p-4" style="max-width:640px;">
  <?= Csrf::field() ?>

  <div class="nd-section-title fs-6 mb-2"><i class="bi bi-person"></i>Buyer</div>
  <div class="mb-3"><label class="form-label small text-muted">Full name</label><input type="text" name="buyer_name" class="form-control" required></div>
  <div class="row g-2 mb-3">
    <div class="col-6"><label class="form-label small text-muted">NIC / ID no.</label><input type="text" name="buyer_nic" class="form-control"></div>
    <div class="col-6"><label class="form-label small text-muted">Mobile no.</label><input type="text" name="buyer_phone" class="form-control" inputmode="tel" placeholder="07XXXXXXXX"></div>
  </div>

  <div class="nd-section-title fs-6 mb-2 mt-3"><i class="bi bi-person-badge"></i>Seller</div>
  <div class="mb-3"><label class="form-label small text-muted">Full name</label><input type="text" name="seller_name" class="form-control" required></div>
  <div class="row g-2 mb-3">
    <div class="col-6"><label class="form-label small text-muted">NIC / ID no.</label><input type="text" name="seller_nic" class="form-control"></div>
    <div class="col-6"><label class="form-label small text-muted">Mobile no.</label><input type="text" name="seller_phone" class="form-control" inputmode="tel" placeholder="07XXXXXXXX"></div>
  </div>

  <hr class="my-3">

  <div class="row g-2 mb-3">
    <div class="col-6"><label class="form-label small text-muted">Deed no.</label><input type="text" name="deed_no" class="form-control" required></div>
    <div class="col-6">
      <label class="form-label small text-muted">Deed category</label>
      <select name="deed_category" class="form-select" required>
        <?php foreach ($categories as $c): ?><option value="<?= View::e($c) ?>"><?= View::e($c) ?></option><?php endforeach; ?>
      </select>
    </div>
  </div>
  <div class="mb-3"><label class="form-label small text-muted">Folio no.</label><input type="text" name="folio_number" class="form-control"></div>
  <div class="row g-2 mb-3">
    <div class="col-6"><label class="form-label small text-muted">Amount</label><input type="text" name="amount" class="form-control" inputmode="decimal"></div>
    <div class="col-6"><label class="form-label small text-muted">Value</label><input type="text" name="value" class="form-control" inputmode="decimal"></div>
  </div>
  <div class="mb-3"><label class="form-label small text-muted">Other document numbers</label><textarea name="other_document_numbers" class="form-control" rows="2"></textarea></div>

  <div class="d-flex justify-content-end gap-2 mt-2">
    <a href="<?= Response::url('deeds') ?>" class="btn btn-outline-secondary">Cancel</a>
    <button type="submit" class="btn btn-nd-primary">Enter</button>
  </div>
</form>
