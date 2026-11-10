<?php
require_once __DIR__ . '/data/session_helper.php';
require_login();
refresh_current_user();

$user = current_user();
$invoiceId = (int) ($_GET['id'] ?? 0);

$invoice = db_first(
    "SELECT i.*, b.booking_code, b.status AS booking_status, b.location_address, b.actual_area, b.estimated_area,
            b.customer_notes, b.actual_survey_date, b.actual_survey_time,
            b.customer_id, b.assigned_pekerja_lapangan_id,
            c.name AS customer_name, c.email AS customer_email, c.phone AS customer_phone, c.address AS customer_address,
            fw.name AS pekerja_lapangan_name, fw.phone AS pekerja_lapangan_phone,
            s.name AS service_name
     FROM final_invoices i
     JOIN bookings b ON b.id = i.booking_id
     JOIN users c ON c.id = b.customer_id
     LEFT JOIN users fw ON fw.id = b.assigned_pekerja_lapangan_id
     LEFT JOIN services s ON s.id = b.service_id
     WHERE i.id = ?",
    [$invoiceId]
);

if (!$invoice) {
    http_response_code(404);
    exit('Invoice tidak ditemukan.');
}

$canAccess = match ($user['role']) {
    'customer' => (int) $invoice['customer_id'] === (int) $user['id'],
    'staff', 'boss' => true,
    default => false,
};

if (!$canAccess) {
    http_response_code(403);
    exit('Akses invoice ditolak.');
}

$items = db_query('SELECT * FROM final_invoice_items WHERE final_invoice_id = ? ORDER BY id', [$invoiceId]);
$payments = db_query('SELECT p.*, u.name AS marked_by_name FROM payments p LEFT JOIN users u ON u.id=p.marked_by WHERE p.invoice_id = ? ORDER BY p.created_at DESC', [$invoiceId]);
$beforePhotos = db_query("SELECT * FROM survey_photos WHERE booking_id=? AND photo_type='before' ORDER BY id LIMIT 5", [$invoice['booking_id']]);
$afterPhotos = db_query("SELECT * FROM survey_photos WHERE booking_id=? AND photo_type='after' ORDER BY id LIMIT 5", [$invoice['booking_id']]);

$companyName = setting('company_name', 'ABI Taman');
$companyEmail = setting('company_email', 'hello@abitaman.id');
$companyPhone = setting('company_phone', '0812-9000-2211');
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($invoice['invoice_code']) ?> | Invoice ABI Taman</title>
    <link rel="icon" type="image/png" href="assets/img/abi-taman.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-body-tertiary py-4">
    <main class="container bg-body shadow-sm rounded p-4 p-lg-5">
        <header class="d-flex flex-wrap align-items-start justify-content-between gap-3 border-bottom border-success border-3 pb-4 mb-4">
            <div class="d-flex align-items-center gap-3">
                <img src="assets/img/abi-taman.png" alt="ABI Taman" width="48" height="48" class="rounded">
                <div>
                    <strong class="d-block fs-5 text-success"><?= h($companyName) ?></strong>
                    <span class="small text-secondary"><?= h($companyEmail) ?> · <?= h($companyPhone) ?></span>
                </div>
            </div>
            <div class="text-end">
                <p class="small text-uppercase text-secondary mb-1">Invoice Final</p>
                <h1 class="h3 mb-0"><?= h($invoice['invoice_code']) ?></h1>
            </div>
        </header>

        <section class="row g-3 mb-4">
            <div class="col-md"><div class="border rounded p-3 d-flex justify-content-between gap-2"><span class="small text-secondary">Status Invoice</span><strong class="<?= h(status_badge_class($invoice['status'])) ?>"><?= h($invoice['status']) ?></strong></div></div>
            <div class="col-md"><div class="border rounded p-3 d-flex justify-content-between gap-2"><span class="small text-secondary">Status Pembayaran</span><strong class="<?= h(status_badge_class($invoice['payment_status'])) ?>"><?= h($invoice['payment_status']) ?></strong></div></div>
            <div class="col-md"><div class="border rounded p-3 d-flex justify-content-between gap-2"><span class="small text-secondary">Status Order</span><strong class="<?= h(status_badge_class($invoice['booking_status'])) ?>"><?= h($invoice['booking_status']) ?></strong></div></div>
        </section>

        <section class="row g-3 mb-4">
            <div class="col-lg-4"><div class="border rounded p-3 h-100">
                <p class="small fw-bold text-uppercase text-secondary">Ditagihkan kepada</p>
                <h2 class="h5"><?= h($invoice['customer_name']) ?></h2>
                <p><?= h($invoice['customer_email']) ?><br><?= h($invoice['customer_phone'] ?: '-') ?><br><?= h($invoice['customer_address'] ?: '-') ?></p>
            </div></div>
            <div class="col-lg-4"><div class="border rounded p-3 h-100">
                <p class="small fw-bold text-uppercase text-secondary">Detail order</p>
                <dl class="row small mb-0">
                    <dt>Booking</dt><dd><?= h($invoice['booking_code']) ?></dd>
                    <dt>Layanan</dt><dd><?= h($invoice['service_name'] ?? 'Custom Layanan') ?></dd>
                    <dt>Pekerja Lapangan</dt><dd><?= h($invoice['pekerja_lapangan_name'] ?? '-') ?></dd>
                    <dt>Lokasi</dt><dd><?= h($invoice['location_address']) ?></dd>
                    <dt>Luas Area</dt><dd><?= h($invoice['actual_area'] ?: $invoice['estimated_area']) ?> m2</dd>
                </dl>
            </div></div>
            <div class="col-lg-4"><div class="border rounded p-3 h-100">
                <p class="small fw-bold text-uppercase text-secondary">Tanggal</p>
                <dl class="row small mb-0">
                    <dt>Dibuat</dt><dd><?= h(format_datetime($invoice['created_at'])) ?></dd>
                    <dt>Diperbarui</dt><dd><?= h(format_datetime($invoice['updated_at'])) ?></dd>
                    <dt>Hari Kerja Aktual</dt><dd><?= (int) $invoice['work_days_actual'] ?> hari</dd>
                    <dt>Jumlah Tukang Aktual</dt><dd><?= (int) $invoice['worker_count_actual'] ?> orang</dd>
                </dl>
            </div></div>
        </section>

        <section class="mb-4">
            <h2 class="h5">Rincian Tagihan</h2>
            <div class="table-responsive"><table class="table table-bordered align-middle">
                <thead>
                    <tr><th>Item</th><th>Tipe</th><th>Qty</th><th>Harga</th><th>Subtotal</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><strong class="d-block"><?= h($item['item_name']) ?></strong><span class="small text-secondary"><?= h($item['description'] ?: '-') ?></span></td>
                            <td><?= h($item['item_type']) ?></td>
                            <td><?= h($item['quantity'] . ' ' . $item['unit']) ?></td>
                            <td><?= format_rupiah($item['unit_price']) ?></td>
                            <td><?= format_rupiah($item['subtotal']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table></div>
            <div class="col-md-6 ms-auto list-group">
                <div class="list-group-item d-flex justify-content-between"><span>Subtotal Estimasi</span><strong><?= format_rupiah($invoice['subtotal_estimate']) ?></strong></div>
                <div class="list-group-item d-flex justify-content-between"><span>Total Revisi</span><strong><?= format_rupiah($invoice['revision_total']) ?></strong></div>
                <div class="list-group-item list-group-item-success d-flex justify-content-between"><span>Total Final</span><strong><?= format_rupiah($invoice['final_total']) ?></strong></div>
            </div>
        </section>

        <section class="mb-4">
            <h2 class="h5">Status Pembayaran</h2>
            <?php if ($payments): ?>
                <div class="table-responsive"><table class="table table-sm table-bordered align-middle">
                    <thead><tr><th>Tipe</th><th>Jumlah</th><th>Status</th><th>Ditandai Oleh</th><th>Tanggal</th></tr></thead>
                    <tbody>
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td><?= h($payment['payment_type']) ?></td>
                                <td><?= format_rupiah($payment['amount']) ?></td>
                                <td><?= h($payment['payment_status']) ?></td>
                                <td><?= h($payment['marked_by_name'] ?? '-') ?></td>
                                <td><?= h(format_datetime($payment['paid_at'] ?: $payment['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table></div>
            <?php else: ?>
                <p class="alert alert-secondary">Belum ada pembayaran yang ditandai untuk invoice ini.</p>
            <?php endif; ?>
        </section>

        <section class="row g-4 mb-4">
            <div class="col-md-6">
                <h2 class="h5">Foto Before</h2>
                <div class="row g-2">
                    <?php foreach ($beforePhotos as $photo): ?><div class="col-6"><img class="img-fluid img-thumbnail" src="<?= h($photo['photo_path']) ?>" alt="Foto before"></div><?php endforeach; ?>
                    <?php if (!$beforePhotos): ?><div class="col-6"><img class="img-fluid img-thumbnail" src="assets/img/placeholders/upload-before.svg" alt="Placeholder before"></div><?php endif; ?>
                </div>
            </div>
            <div class="col-md-6">
                <h2 class="h5">Foto After</h2>
                <div class="row g-2">
                    <?php foreach ($afterPhotos as $photo): ?><div class="col-6"><img class="img-fluid img-thumbnail" src="<?= h($photo['photo_path']) ?>" alt="Foto after"></div><?php endforeach; ?>
                    <?php if (!$afterPhotos): ?><div class="col-6"><img class="img-fluid img-thumbnail" src="assets/img/placeholders/upload-after.svg" alt="Placeholder after"></div><?php endif; ?>
                </div>
            </div>
        </section>

        <section class="mb-4">
            <h2 class="h5">Catatan</h2>
            <p class="alert alert-light border"><?= h($invoice['notes'] ?: 'Pembayaran dilakukan di luar sistem dan ditandai manual oleh staff ABI Taman.') ?></p>
        </section>

        <footer class="d-flex justify-content-between gap-3 border-top pt-4 small">
            <div>
                <span class="d-block text-secondary">Dicetak pada</span>
                <strong class="d-block"><?= h(date('d M Y H:i')) ?></strong>
            </div>
            <div class="text-end">
                <span class="text-secondary">Staff ABI Taman</span>
            </div>
        </footer>
    </main>

    <div class="container d-flex justify-content-end gap-2 mt-3 d-print-none">
        <button class="btn btn-success" type="button" onclick="window.print()">Cetak Invoice</button>
        <button class="btn btn-outline-secondary" type="button" onclick="window.close()">Tutup</button>
    </div>
</body>
</html>
