<?php

use App\Core\Auth;
use App\Core\Response;
use App\Core\View;

$appName = App\Core\Env::get('APP_NAME', 'Nithi Docket');
$navItems = [
    ['path' => '/dashboard', 'label' => 'Dashboard', 'icon' => 'bi-speedometer2', 'perm' => null],
    ['path' => '/deeds', 'label' => 'Deed registry', 'icon' => 'bi-journal-text', 'perm' => 'deeds.view'],
    ['path' => '/notifications', 'label' => 'Notifications', 'icon' => 'bi-bell', 'perm' => 'notifications.view'],
    ['path' => '/users', 'label' => 'Users', 'icon' => 'bi-people', 'perm' => 'users.manage'],
    ['path' => '/audit-logs', 'label' => 'Audit log', 'icon' => 'bi-shield-check', 'perm' => 'audit.view'],
];
$currentPath = App\Core\Request::path();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= View::e($appName) ?> — Deed Registry Tracker</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Source+Serif+4:opsz,wght@8..60,400;8..60,600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?= Response::url('assets/css/app.css') ?>" rel="stylesheet">
</head>
<body>
<?= \App\Core\Csrf::field() ?>
<div class="nd-shell">
  <aside class="nd-sidebar d-flex flex-column" id="ndSidebar">
    <div class="nd-brand">
      <span class="nd-brand-mark"><i class="bi bi-bank2"></i></span>
      <span><?= View::e($appName) ?><small>Deed registry tracker</small></span>
      <button type="button" class="nd-nav-toggle" id="ndNavToggle" aria-label="Toggle menu" aria-expanded="false" aria-controls="ndSidebar">
        <i class="bi bi-list"></i>
      </button>
    </div>
    <nav class="nd-nav nav flex-column">
      <?php foreach ($navItems as $item): ?>
        <?php if ($item['perm'] && !Auth::can($item['perm'])) continue; ?>
        <a class="nav-link <?= str_starts_with($currentPath, $item['path']) ? 'active' : '' ?>" href="<?= Response::url($item['path']) ?>">
          <i class="bi <?= $item['icon'] ?>"></i><?= View::e($item['label']) ?>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="nd-sidebar-foot">
      <?php if ($user = Auth::user()): ?>
        <div class="who">
          <span class="nd-avatar"><?= View::e(mb_strtoupper(mb_substr($user['name'], 0, 1))) ?></span>
          <span><?= View::e($user['name']) ?><small class="d-block text-capitalize"><?= View::e($user['role_name']) ?></small></span>
        </div>
        <form method="post" action="<?= Response::url('logout') ?>">
          <?= \App\Core\Csrf::field() ?>
          <button type="submit" class="nd-logout-btn"><i class="bi bi-box-arrow-right"></i> Log out</button>
        </form>
      <?php endif; ?>
    </div>
  </aside>
  <main class="nd-main">
    <?= $content ?? '' ?>
  </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= Response::url('assets/js/nav.js') ?>"></script>
<script src="<?= Response::url('assets/js/ui.js') ?>"></script>
<?php if (is_file(__DIR__ . '/../../public/assets/js/firebase-config.js')): ?>
<script src="<?= Response::url('assets/js/firebase-config.js') ?>"></script>
<?php endif; ?>
<script src="<?= Response::url('assets/js/notifications.js') ?>"></script>
</body>
</html>
