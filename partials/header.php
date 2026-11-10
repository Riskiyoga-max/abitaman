<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= h(csrf_token()) ?>">
    <title><?= h($pageTitle ?? 'Dashboard') ?> | ABI Taman Service Commerce</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/app.css?v=<?= filemtime(__DIR__ . '/../assets/css/app.css') ?>" rel="stylesheet">
    <link rel="icon" type="image/png" href="assets/img/abi-taman.png">
</head>
<body class="app-shell" data-open-modal="<?= h($_GET['open'] ?? '') ?>" data-role="<?= h($role ?? '') ?>" data-user-name="<?= h($user['name'] ?? $user['nama'] ?? 'Guest') ?>" data-user-email="<?= h($user['email'] ?? '') ?>">
