<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Not permitted — Nithi Docket</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Source+Serif+4:opsz,wght@8..60,400;8..60,600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?= \App\Core\Response::url('assets/css/app.css') ?>" rel="stylesheet">
</head>
<body class="d-flex align-items-center justify-content-center" style="min-height:100vh;">
<div class="text-center">
  <div class="nd-brand-mark mx-auto mb-4" style="width:52px;height:52px;font-size:24px;"><i class="bi bi-shield-lock"></i></div>
  <h1 class="font-head fs-1 mb-2">Not permitted</h1>
  <p class="text-muted mb-4">Your account doesn't have access to this action.</p>
  <a href="<?= \App\Core\Response::url('/dashboard') ?>" class="btn btn-nd-primary">Back to dashboard</a>
</div>
</body>
</html>
