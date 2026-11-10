<?php
require_once __DIR__ . '/data/session_helper.php';

$slug    = trim($_GET['slug'] ?? '');
$service = db_first('SELECT s.*, c.name AS category_name FROM services s LEFT JOIN service_categories c ON c.id=s.category_id WHERE s.slug=? AND s.status="active"', [$slug]);
if (!$service) { http_response_code(404); exit('Layanan tidak ditemukan.'); }

$materials = db_query("SELECT * FROM materials WHERE status='active' ORDER BY name LIMIT 8");
$current   = current_user();
$serviceImage = trim((string) ($service['image'] ?? ''));
$serviceImagePath = $serviceImage !== '' ? __DIR__ . '/' . ltrim($serviceImage, '/') : '';
if ($serviceImagePath === '' || !is_file($serviceImagePath) || @getimagesize($serviceImagePath) === false) {
    $serviceImage = $service['slug'] === 'taman-minimalis'
        ? 'assets/img/services/taman-minimalis.svg'
        : 'assets/img/services/custom-landscape.svg';
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($service['name']) ?> | ABI Taman</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/app.css?v=<?= filemtime(__DIR__ . '/assets/css/app.css') ?>" rel="stylesheet">
    <link rel="icon" type="image/png" href="assets/img/abi-taman.png">
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar bg-body border-bottom sticky-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <img src="assets/img/abi-taman.png" alt="ABI Taman" width="30" height="30" class="rounded"> ABI TAMAN
            </a>
            <div class="d-flex gap-2">
                <a class="btn btn-outline-secondary btn-sm" href="catalog.php">
                    <i class="bi bi-arrow-left me-1"></i>Katalog
                </a>
                <a class="btn btn-success btn-sm" href="booking_create.php?service_id=<?= (int) $service['id'] ?>">
                    <i class="bi bi-bag-check me-1"></i>Booking Sekarang
                </a>
            </div>
        </div>
    </nav>

    <!-- Hero -->
    <section class="bg-success text-white pt-5">
        <div class="container">
            <div class="row align-items-end g-4">
                <div class="col-lg-7 pb-4">
                    <p class="small fw-bold text-uppercase text-white-50 mb-2">
                        <?= h($service['category_name'] ?? 'Layanan') ?>
                    </p>
                    <h1 class="display-5 fw-bold mb-3">
                        <?= h($service['name']) ?>
                    </h1>
                    <p class="lead text-white-50">
                        <?= h($service['description'] ?: $service['short_description'] ?? '') ?>
                    </p>
                    <div class="alert alert-light d-inline-flex align-items-center gap-2 mt-3 mb-4">
                        <i class="bi bi-info-circle flex-shrink-0"></i>
                        Harga ditentukan setelah survey lapangan berdasarkan luas dan kondisi lokasi Anda.
                    </div>
                    <div class="d-flex gap-3 flex-wrap">
                        <a class="btn btn-light btn-lg fw-bold" href="booking_create.php?service_id=<?= (int) $service['id'] ?>">
                            <i class="bi bi-bag-check me-2"></i>Booking Sekarang
                        </a>
                        <a class="btn btn-outline-light btn-lg" href="booking_create.php?custom=1">
                            <i class="bi bi-pencil-square me-2"></i>Custom Layanan
                        </a>
                    </div>
                </div>
                <div class="col-lg-5">
                    <img src="<?= h($serviceImage) ?>" alt="<?= h($service['name']) ?>" class="img-fluid rounded-top">
                </div>
            </div>
        </div>
    </section>

    <main class="py-5">
        <div class="container">
            <div class="row g-5">
                <!-- Konten utama -->
                <div class="col-lg-8">

                    <!-- Cara kerja -->
                    <section class="mb-5">
                        <p class="small fw-bold text-uppercase text-secondary mb-1">Proses</p>
                        <h2 class="h4 fw-bold mb-4">Bagaimana Cara Kerjanya?</h2>
                        <div class="d-flex flex-column gap-3">
                            <?php
                            $steps = [
                                ['bi-bag-check',       'Booking Online',    'Pilih layanan dan isi form booking dengan lokasi dan jadwal survey yang Anda inginkan.'],
                                ['bi-calendar2-check', 'Survey Lokasi',     'Tim kami datang ke lokasi untuk mengukur area, menilai kondisi, dan mendiskusikan detail pekerjaan.'],
                                ['bi-calculator',      'Terima Estimasi',   'Estimasi biaya transparan dikirim ke Anda berdasarkan hasil survey. Setujui atau diskusikan.'],
                                ['bi-tools',           'Pekerjaan Dimulai', 'Tim lapangan mengerjakan sesuai jadwal. Pantau progress kapan saja via dashboard.'],
                                ['bi-receipt',         'Invoice & Bayar',   'Terima invoice final setelah pekerjaan selesai dan lakukan pembayaran.'],
                            ];
                            foreach ($steps as $i => [$icon, $title, $desc]):
                            ?>
                                <div class="d-flex align-items-start gap-3">
                                    <div class="badge rounded-pill text-bg-success fs-6 flex-shrink-0">
                                        <?= str_pad((string)($i+1), 2, '0', STR_PAD_LEFT) ?>
                                    </div>
                                    <div>
                                        <p class="fw-bold mb-0 small"><?= h($title) ?></p>
                                        <p class="text-muted small mb-0"><?= h($desc) ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>

                    <!-- Material -->
                    <?php if ($materials): ?>
                    <section>
                        <p class="small fw-bold text-uppercase text-secondary mb-1">Bahan Baku</p>
                        <h2 class="h4 fw-bold mb-1">Material yang Digunakan</h2>
                        <p class="text-muted small mb-4">Estimasi bahan baku default — dapat berubah sesuai hasil survey lapangan.</p>
                        <div class="material-list">
                            <?php foreach ($materials as $mat): ?>
                                <div class="material-item">
                                    <?php if ($mat['image']): ?>
                                        <img src="<?= h($mat['image']) ?>" alt="<?= h($mat['name']) ?>">
                                    <?php else: ?>
                                        <i class="bi bi-box-seam" aria-hidden="true"></i>
                                    <?php endif; ?>
                                    <div>
                                        <strong class="d-block small"><?= h($mat['name']) ?></strong>
                                        <span class="small text-secondary"><?= h($mat['default_qty_per_10m']) ?> <?= h($mat['unit']) ?> / 10 m²
                                            · <?= format_rupiah($mat['price_per_unit']) ?>/<?= h($mat['unit']) ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                    <?php endif; ?>
                </div>

                <!-- Sidebar CTA -->
                <div class="col-lg-4">
                    <div class="sticky-lg-top">
                        <div class="card border-0 shadow-sm overflow-hidden">
                            <div class="card-body p-4">
                                <p class="small fw-bold text-uppercase text-secondary mb-1">Tertarik?</p>
                                <h3 class="h5 fw-bold mb-2"><?= h($service['name']) ?></h3>
                                <p class="text-muted small mb-3">
                                    Harga ditentukan setelah survey lapangan — transparan dan sesuai kondisi lokasi Anda.
                                </p>
                                <a class="btn btn-success w-100 mb-2" href="booking_create.php?service_id=<?= (int) $service['id'] ?>">
                                    <i class="bi bi-bag-check me-2"></i>Booking Sekarang
                                </a>
                                <a class="btn btn-outline-secondary w-100" href="booking_create.php?custom=1">
                                    <i class="bi bi-pencil-square me-2"></i>Custom Layanan
                                </a>
                            </div>
                            <div class="card-footer bg-light border-top py-3 px-4">
                                <div class="d-flex flex-column gap-2 small text-secondary">
                                    <span><i class="bi bi-check-circle-fill text-success me-2"></i>Estimasi transparan setelah survey</span>
                                    <span><i class="bi bi-check-circle-fill text-success me-2"></i>Pantau progress real-time</span>
                                    <span><i class="bi bi-check-circle-fill text-success me-2"></i>Riwayat estimasi dan revisi tercatat</span>
                                    <span><i class="bi bi-check-circle-fill text-success me-2"></i>Invoice dan pembayaran terhubung ke order</span>
                                </div>
                            </div>
                        </div>

                        <div class="mt-3 p-3 border rounded bg-white">
                            <p class="small fw-bold text-uppercase text-secondary mb-2">Layanan Lain</p>
                            <a class="btn btn-outline-secondary btn-sm w-100 mb-2" href="catalog.php">
                                <i class="bi bi-grid me-1"></i>Lihat Semua Layanan
                            </a>
                            <?php if ($current): ?>
                                <a class="btn btn-outline-secondary btn-sm w-100" href="dashboard.php">
                                    <i class="bi bi-grid-1x2 me-1"></i>Ke Dashboard
                                </a>
                            <?php else: ?>
                                <a class="btn btn-outline-secondary btn-sm w-100" href="login.php">
                                    <i class="bi bi-person me-1"></i>Login / Daftar
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
