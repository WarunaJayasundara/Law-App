<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Session expired — Nithi Docket</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Source+Serif+4:opsz,wght@8..60,400;8..60,600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?= \App\Core\Response::url('assets/css/app.css') ?>" rel="stylesheet">
</head>
<body class="d-flex align-items-center justify-content-center" style="min-height:100vh;">
<div class="text-center" style="max-width:420px;">
  <div class="nd-brand-mark mx-auto mb-4" style="width:52px;height:52px;font-size:24px;"><i class="bi bi-hourglass-split"></i></div>
  <h1 class="font-head fs-1 mb-2">Session expired</h1>
  <p class="text-muted mb-4">Your form session expired, or the request looked unsafe. Please go back and try again.</p>
  <a href="javascript:history.back()" class="btn btn-nd-primary">Go back</a>
</div>
</body>
</html>
