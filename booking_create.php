<?php
require_once __DIR__ . '/data/session_helper.php';

if (!current_user()) {
    $return = 'booking_create.php?' . http_build_query($_GET);
    header('Location: login.php?return=' . urlencode($return));
    exit;
}
require_roles(['customer']);

$user     = current_user();
$serviceId = (int) ($_GET['service_id'] ?? 0);
$isCustom  = isset($_GET['custom']);
$service   = $serviceId ? db_first('SELECT * FROM services WHERE id=? AND status="active"', [$serviceId]) : null;
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Booking Layanan | ABI Taman</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/app.css?v=<?= filemtime(__DIR__ . '/assets/css/app.css') ?>" rel="stylesheet">
    <link rel="icon" type="image/png" href="assets/img/abi-taman.png">
</head>
<body class="bg-body-tertiary" data-role="customer">

    <!-- Navbar -->
    <nav class="navbar bg-body border-bottom px-3 px-lg-5 py-3">
        <a href="index.php" class="navbar-brand d-flex align-items-center gap-2 text-decoration-none">
            <img src="assets/img/abi-taman.png" alt="ABI Taman" width="32" height="32" class="rounded">
            <span><strong class="d-block">ABI TAMAN</strong><small class="d-block text-secondary">Booking Layanan</small></span>
        </a>
        <div class="d-flex align-items-center gap-2 ms-auto">
            <a class="btn btn-link text-decoration-none" href="catalog.php"><i class="bi bi-arrow-left me-1"></i>Katalog</a>
            <a class="btn btn-outline-secondary btn-sm" href="dashboard.php">Dashboard</a>
        </div>
    </nav>

    <main class="container py-5">
      <div class="col-lg-7 mx-auto">

        <!-- Header -->
        <div class="mb-4">
            <p class="small fw-bold text-uppercase text-secondary mb-1"><?= $isCustom ? 'Custom Layanan' : 'Booking Layanan' ?></p>
            <h1 class="h3 fw-bold mb-1">
                <?= $isCustom ? 'Ajukan Custom Layanan' : h($service['name'] ?? 'Booking Layanan') ?>
            </h1>
            <?php if ($service && !$isCustom): ?>
                <p class="text-muted small mb-0">
                    <?= h($service['short_description'] ?? $service['description'] ?? '') ?>
                </p>
            <?php elseif ($isCustom): ?>
                <p class="text-muted small mb-0">
                    Ceritakan kebutuhan taman Anda. Tim kami akan menghubungi dan melakukan survey untuk menentukan estimasi harga.
                </p>
            <?php endif; ?>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <form data-api-form class="d-flex flex-column gap-3" method="post" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="create_booking">
                    <input type="hidden" name="service_id" value="<?= (int) ($service['id'] ?? 0) ?>">
                    <?php if ($isCustom): ?>
                        <input type="hidden" name="is_custom_service" value="1">
                    <?php endif; ?>

                    <?php if ($isCustom): ?>
                        <!-- Custom: deskripsi kebutuhan -->
                        <div>
                            <label class="form-label fw-semibold">Deskripsi Kebutuhan <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="custom_service_description" rows="4"
                                      placeholder="Jelaskan kebutuhan taman Anda secara detail — jenis tanaman, konsep, ukuran, dll." required></textarea>
                        </div>
                    <?php else: ?>
                        <!-- Info layanan yang dipilih -->
                        <?php if ($service): ?>
                            <div class="alert alert-success mb-0">
                                <p class="mb-0 fw-semibold small text-success">
                                    <i class="bi bi-flower1 me-1"></i><?= h($service['name']) ?>
                                </p>
                                <p class="mb-0 text-muted small mt-1">
                                    Harga akan ditentukan setelah survey lapangan berdasarkan luas dan kondisi lokasi Anda.
                                </p>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <div class="rounded-3 bg-light border p-3">
                        <small class="text-muted d-block">Booking sebagai</small>
                        <strong><?= h($user['name']) ?></strong>
                        <span class="text-muted small"> · <?= h($user['phone'] ?: $user['email']) ?></span>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Tanggal Survey yang Diinginkan <span class="text-danger">*</span></label>
                            <input class="form-control" type="date" name="requested_survey_date"
                                   min="<?= h(date('Y-m-d', strtotime('+1 day'))) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Foto Referensi <span class="text-muted fw-normal">(opsional)</span></label>
                            <input class="form-control" type="file" name="reference_photo" accept=".jpg,.jpeg,.png,.webp">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Alamat Lokasi <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="location_address" rows="3" required><?= h($user['address'] ?? '') ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Catatan Tambahan <span class="text-muted fw-normal">(opsional)</span></label>
                            <textarea class="form-control" name="customer_notes" rows="3"
                                      placeholder="Ceritakan kebutuhan atau preferensi khusus Anda"></textarea>
                        </div>
                    </div>

                    <details class="border rounded-3 p-3">
                        <summary class="fw-semibold">Informasi tambahan <span class="text-muted fw-normal">(opsional)</span></summary>
                        <div class="pt-3">
                            <label class="form-label fw-semibold">Perkiraan Luas Area (m²)</label>
                            <input class="form-control" type="number" min="1" step="0.1" name="estimated_area" placeholder="Contoh: 50">
                            <small class="text-muted">Tidak masalah jika belum tahu; luas aktual dicatat saat survei.</small>
                        </div>
                    </details>

                    <!-- Agreement -->
                    <div class="alert alert-warning d-flex align-items-start gap-2 mb-0">
                        <input class="form-check-input flex-shrink-0 mt-1" type="checkbox" name="survey_agreement" value="1" id="surveyAgreement" required>
                        <label class="form-check-label small" for="surveyAgreement">
                            Saya memahami bahwa <strong>estimasi harga final</strong> akan ditentukan setelah pekerja lapangan melakukan survey ke lokasi saya.
                        </label>
                    </div>

                    <button class="btn btn-success btn-lg fw-bold" type="submit">
                        <i class="bi bi-bag-check me-2"></i>Kirim Booking
                    </button>
                </form>
            </div>
        </div>

        <p class="text-center text-muted small mt-3">
            Ingin layanan lain? <a href="catalog.php" class="text-success fw-semibold">Lihat katalog</a>
        </p>
      </div>
    </main>

    <div class="toast-container position-fixed top-0 end-0 p-3" id="toastContainer"></div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/app.js?v=<?= filemtime(__DIR__ . '/assets/js/app.js') ?>"></script>
</body>
</html>
