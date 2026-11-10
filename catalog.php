<?php
require_once __DIR__ . '/data/session_helper.php';

$categorySlug = trim($_GET['category'] ?? '');
$search = trim($_GET['q'] ?? '');
$categories = db_query("SELECT * FROM service_categories WHERE status='active' ORDER BY name");
$where = ["s.status='active'"];
$params = [];
if ($categorySlug !== '') {
    $where[] = 'c.slug = ?';
    $params[] = $categorySlug;
}
if ($search !== '') {
    $where[] = '(s.name LIKE ? OR s.short_description LIKE ? OR s.description LIKE ?)';
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}
$services = db_query(
    'SELECT s.*, c.name AS category_name
     FROM services s
     LEFT JOIN service_categories c ON c.id=s.category_id
     WHERE ' . implode(' AND ', $where) . '
     ORDER BY s.name',
    $params
);
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Katalog Layanan | ABI Taman</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/app.css?v=<?= filemtime(__DIR__ . '/assets/css/app.css') ?>" rel="stylesheet">
    <link rel="icon" type="image/png" href="assets/img/abi-taman.png">
</head>
<body class="bg-body-tertiary">
    <nav class="navbar bg-body border-bottom px-3 px-lg-5 py-3">
        <a href="index.php" class="navbar-brand d-flex align-items-center gap-2 text-decoration-none"><img src="assets/img/abi-taman.png" alt="ABI Taman" width="32" height="32" class="rounded"><span><strong class="d-block">ABI TAMAN</strong><small class="d-block text-secondary">Katalog Layanan</small></span></a>
        <div class="d-flex align-items-center gap-2 ms-auto"><a class="btn btn-link text-decoration-none" href="index.php">Home</a><a class="btn btn-success" href="booking_create.php?custom=1">Custom Layanan</a></div>
    </nav>
    <main class="container py-5">
        <section class="mb-4 d-flex flex-column gap-3">
            <p class="small fw-bold text-uppercase text-secondary">Katalog</p>
            <h1>Layanan taman ABI Taman</h1>
            <form class="row g-2" method="get">
                <div class="col-md"><input class="form-control" name="q" value="<?= h($search) ?>" placeholder="Cari layanan"></div>
                <div class="col-md"><select class="form-select" name="category">
                    <option value="">Semua kategori</option>
                    <?php foreach ($categories as $cat): ?><option value="<?= h($cat['slug']) ?>" <?= $categorySlug === $cat['slug'] ? 'selected' : '' ?>><?= h($cat['name']) ?></option><?php endforeach; ?>
                </select></div>
                <div class="col-md-auto"><button class="btn btn-success w-100" type="submit">Filter</button></div>
            </form>
        </section>
        <section class="catalog-list">
            <?php foreach ($services as $service): ?>
                <?php
                $serviceImage = trim((string) ($service['image'] ?? ''));
                $serviceImagePath = $serviceImage !== '' ? __DIR__ . '/' . ltrim($serviceImage, '/') : '';
                $isServicePlaceholder = false;
                if ($serviceImagePath === '' || !is_file($serviceImagePath) || @getimagesize($serviceImagePath) === false) {
                    $isServicePlaceholder = true;
                    $serviceImage = $service['slug'] === 'taman-minimalis'
                        ? 'assets/img/services/taman-minimalis.svg'
                        : 'assets/img/services/custom-landscape.svg';
                }
                ?>
                <article class="catalog-item">
                    <img class="catalog-item__image <?= $isServicePlaceholder ? 'catalog-item__image--placeholder' : '' ?>" src="<?= h($serviceImage) ?>" alt="<?= h($service['name']) ?>">
                    <div class="catalog-item__body">
                        <span class="landing-service__meta"><?= h($service['category_name']) ?></span>
                        <h2><?= h($service['name']) ?></h2>
                        <p><?= h($service['short_description']) ?></p>
                        <small class="fw-semibold mb-3">Estimasi disusun setelah survey lokasi.</small>
                        <div class="catalog-item__actions">
                            <a class="btn btn-outline-secondary btn-sm" href="service_detail.php?slug=<?= h($service['slug']) ?>">Detail</a>
                            <a class="btn btn-success btn-sm" href="booking_create.php?service_id=<?= (int) $service['id'] ?>">Buat booking</a>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>
    </main>
</body>
</html>
