<?php
use App\Core\Env;
use App\Core\Response;
use App\Core\View;
$appName = Env::get('APP_NAME', 'Nithi Docket');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= View::e($appName) ?> — Sign in</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Source+Serif+4:opsz,wght@8..60,400;8..60,600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?= Response::url('assets/css/app.css') ?>" rel="stylesheet">
<style>
  html, body { height: 100%; }
  body { background: var(--paper); }
  .nd-auth-shell { min-height: 100vh; display: flex; }
  .nd-auth-hero {
    flex: 1.15;
    position: relative;
    background: linear-gradient(160deg, #16232f 0%, #1c2e3d 55%, #24384a 100%);
    color: #f2efe4;
    display: flex; flex-direction: column; justify-content: space-between;
    padding: 56px 60px;
    overflow: hidden;
  }
  .nd-auth-hero svg { position: absolute; inset: 0; width: 100%; height: 100%; opacity: .5; }
  .nd-auth-hero-content { position: relative; z-index: 1; }
  .nd-auth-hero-mark { display: flex; align-items: center; gap: 14px; margin-bottom: 64px; }
  .nd-auth-hero-mark .nd-brand-mark { width: 44px; height: 44px; font-size: 21px; }
  .nd-auth-hero-mark .name { font-family: var(--font-head); font-size: 22px; color: #fff; }
  .nd-auth-hero-mark .name small { display: block; font-family: var(--font-ui); font-size: 11px; color: #9fb0bd; letter-spacing: .4px; text-transform: uppercase; font-weight: 500; }
  .nd-auth-hero h2 { font-family: var(--font-head); font-size: 34px; line-height: 1.25; max-width: 460px; color: #fff; font-weight: 400; }
  .nd-auth-hero p.lead-copy { color: #b7c3cc; font-size: 15px; max-width: 420px; margin-top: 14px; }
  .nd-auth-hero-features { position: relative; z-index: 1; display: flex; flex-direction: column; gap: 14px; }
  .nd-auth-hero-features .feat { display: flex; align-items: center; gap: 12px; color: #d7dee3; font-size: 13.5px; }
  .nd-auth-hero-features .feat i { color: var(--gold); font-size: 16px; }
  .nd-auth-panel { flex: 1; display: flex; align-items: center; justify-content: center; padding: 40px 24px; background: var(--paper); }
  .auth-card { width: 100%; max-width: 380px; background: var(--paper-raised); border: 1px solid var(--line); border-radius: 14px; padding: 40px 34px; box-shadow: var(--shadow-md); }
  @media (max-width: 860px) { .nd-auth-hero { display: none; } }
</style>
</head>
<body>
<div class="nd-auth-shell">
  <div class="nd-auth-hero">
    <svg viewBox="0 0 600 800" preserveAspectRatio="xMidYMid slice" xmlns="http://www.w3.org/2000/svg">
      <defs>
        <linearGradient id="gold-line" x1="0" y1="0" x2="1" y2="1">
          <stop offset="0" stop-color="#c9a04e" stop-opacity="0.55"/>
          <stop offset="1" stop-color="#c9a04e" stop-opacity="0.05"/>
        </linearGradient>
      </defs>
      <g fill="none" stroke="url(#gold-line)" stroke-width="1.5">
        <rect x="60" y="120" width="280" height="380" rx="6" transform="rotate(-8 200 310)"/>
        <rect x="160" y="180" width="280" height="380" rx="6" transform="rotate(6 300 370)"/>
        <line x1="200" y1="240" x2="380" y2="240" transform="rotate(6 300 370)"/>
        <line x1="200" y1="280" x2="380" y2="280" transform="rotate(6 300 370)"/>
        <line x1="200" y1="320" x2="340" y2="320" transform="rotate(6 300 370)"/>
      </g>
      <circle cx="470" cy="620" r="70" fill="none" stroke="#c9a04e" stroke-opacity="0.35" stroke-width="1.5"/>
      <circle cx="470" cy="620" r="52" fill="none" stroke="#c9a04e" stroke-opacity="0.5" stroke-width="1.5"/>
      <path d="M470 588 L478 610 L502 612 L484 628 L490 652 L470 638 L450 652 L456 628 L438 612 L462 610 Z" fill="#c9a04e" fill-opacity="0.4"/>
    </svg>
    <div class="nd-auth-hero-content">
      <div class="nd-auth-hero-mark">
        <span class="nd-brand-mark"><i class="bi bi-bank2"></i></span>
        <span class="name"><?= View::e($appName) ?><small>Deed registry tracker</small></span>
      </div>
      <h2>A single, secure record of every deed from submission to registration.</h2>
      <p class="lead-copy">Track buyer and seller details, Land Registry progress, and notifications in one place — built for legal and notarial offices.</p>
    </div>
    <div class="nd-auth-hero-features">
      <div class="feat"><i class="bi bi-shield-check"></i> Role-based access with a full audit trail</div>
      <div class="feat"><i class="bi bi-diagram-3"></i> Clear Submitted &rarr; Reviewed &rarr; Received workflow</div>
      <div class="feat"><i class="bi bi-bell"></i> Firebase-powered notifications</div>
    </div>
  </div>
  <div class="nd-auth-panel">
    <div class="auth-card">
      <div class="font-head text-center fs-3 mb-1"><?= View::e($appName) ?></div>
      <p class="text-center text-muted small mb-4">Sign in to your workspace</p>
      <?php if (!empty($error)): ?>
        <div class="alert alert-danger py-2 small"><?= View::e($error) ?></div>
      <?php endif; ?>
      <form method="post" action="<?= Response::url('login') ?>">
        <?= $csrf ?>
        <div class="mb-3">
          <label class="form-label small text-muted">Username or email</label>
          <input type="text" name="login" class="form-control" required autofocus>
        </div>
        <div class="mb-3">
          <label class="form-label small text-muted">Password</label>
          <input type="password" name="password" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-nd-primary w-100">Log in</button>
      </form>
      <p class="text-center text-muted small mt-4 mb-0">Accounts are created by an administrator under Users.</p>
    </div>
  </div>
</div>
<script src="<?= Response::url('assets/js/ui.js') ?>"></script>
</body>
</html>
