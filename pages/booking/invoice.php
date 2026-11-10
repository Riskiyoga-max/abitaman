<?php
declare(strict_types=1);
require_once __DIR__ . '/../../data/session_helper.php';
require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/_queries.php';
require_once __DIR__ . '/_forms.php';
require_once __DIR__ . '/_list_helpers.php';
require_roles(['customer', 'staff', 'boss']);
$bookingId = (int) ($_GET['id'] ?? 0);
if ($bookingId > 0) {
    $booking      = booking_for_access($bookingId, $user);
    if (!$booking) { echo '<div class="alert alert-warning">Booking tidak ditemukan.</div>'; return; }
    $invoice      = latest_booking_invoice($bookingId);
    $invoiceItems = $invoice ? db_query('SELECT * FROM final_invoice_items WHERE final_invoice_id = ? ORDER BY id', [$invoice['id']]) : [];
    ?>
    <a class="btn btn-outline-secondary btn-sm mb-3" href="dashboard.php?page=booking_invoice">
        <i class="bi bi-arrow-left"></i> Kembali ke daftar invoice
    </a>

    <div class="d-flex align-items-start justify-content-between gap-3 mb-4 mt-2">
        <div>
            <p class="small fw-bold text-uppercase text-secondary mb-1">Invoice Final</p>
            <h2 class="h4 fw-bold mb-1"><?= h($booking['booking_code']) ?></h2>
            <p class="text-muted small mb-0"><?= h($booking['customer_name']) ?> · <?= h($booking['service_name'] ?? 'Custom Layanan') ?></p>
        </div>
        <span class="<?= status_badge_class($booking['status']) ?> flex-shrink-0 mt-1">
            <?= h(booking_status_label_for_role($booking['status'], $role)) ?>
        </span>
    </div>

    <?php if ($invoice): ?>
        <!-- Header invoice -->
        <div class="card text-bg-success border-0 mb-4">
            <div class="card-body p-4">
                <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
                    <div>
                        <p class="small text-uppercase text-white-50 mb-1 fw-bold">Invoice</p>
                        <p class="h5 mb-0 fw-bold"><?= h($invoice['invoice_code']) ?></p>
                    </div>
                    <div class="text-end">
                        <p class="small text-uppercase text-white-50 mb-1">Total Tagihan</p>
                        <p class="h3 mb-0 fw-bold"><?= format_rupiah($invoice['final_total']) ?></p>
                    </div>
                </div>
                <div class="d-flex gap-2 mt-3 flex-wrap align-items-center">
                    <span class="badge bg-white text-dark"><?= h($invoice['status']) ?></span>
                    <span class="badge bg-white text-dark"><?= h($invoice['payment_status']) ?></span>
                    <a class="btn btn-sm btn-light" href="invoice_print.php?id=<?= (int) $invoice['id'] ?>" target="_blank">
                        <i class="bi bi-printer me-1"></i>Cetak
                    </a>
                    <?php if ($role === 'staff' && $invoice['status'] === 'Draft'): ?>
                        <form class="d-inline" data-api-form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="send_invoice">
                            <input type="hidden" name="invoice_id" value="<?= (int) $invoice['id'] ?>">
                            <button class="btn btn-sm btn-warning fw-bold" type="submit">
                                <i class="bi bi-send-check me-1"></i>Kirim ke Customer
                            </button>
                        </form>
                    <?php endif; ?>
                    <?php if ($role === 'staff' && $invoice['payment_status'] === 'Belum Dibayar' && $invoice['status'] === 'Dikirim'): ?>
                        <?php render_mark_invoice_paid_form($invoice); ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($role === 'staff' && $invoice['status'] === 'Draft'): ?>
        <!-- Form edit invoice untuk staff -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom py-3 px-4 d-flex align-items-center justify-content-between">
                <div><p class="small fw-bold text-uppercase text-secondary mb-0">Edit Invoice</p><h3 class="h6 fw-bold mb-0">Sesuaikan sebelum dikirim ke customer</h3></div>
                <span class="badge bg-warning text-dark">Draft</span>
            </div>
            <div class="card-body">
                <form data-api-form method="post" id="editInvoiceForm">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="save_invoice">
                    <input type="hidden" name="invoice_id" value="<?= (int) $invoice['id'] ?>">

                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Hari Kerja Aktual</label>
                            <input class="form-control" type="number" min="1" name="work_days_actual" value="<?= (int) $invoice['work_days_actual'] ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Tukang Aktual</label>
                            <input class="form-control" type="number" min="1" name="worker_count_actual" value="<?= (int) $invoice['worker_count_actual'] ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Catatan Invoice</label>
                            <input class="form-control" type="text" name="notes" value="<?= h($invoice['notes'] ?? '') ?>" placeholder="Catatan opsional">
                        </div>
                    </div>

                    <!-- Edit item -->
                    <p class="fw-semibold small mb-2">Rincian Item</p>
                    <div class="table-responsive mb-3">
                        <table class="table table-sm align-middle">
                            <thead class="table-light">
                                <tr><th>Item</th><th>Tipe</th><th>Qty</th><th>Harga Satuan</th><th>Subtotal</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($invoiceItems as $item): ?>
                                    <tr>
                                        <td>
                                            <strong><?= h($item['item_name']) ?></strong>
                                            <?php if ($item['description']): ?><br><small class="text-muted"><?= h($item['description']) ?></small><?php endif; ?>
                                            <input type="hidden" name="item_id[]" value="<?= (int) $item['id'] ?>">
                                        </td>
                                        <td><span class="badge text-bg-secondary"><?= h($item['item_type']) ?></span></td>
                                        <td>
                                            <input class="form-control form-control-sm"
                                                   data-invoice-quantity
                                                   type="number" min="0" step="0.01"
                                                   name="quantity[]"
                                                   value="<?= h($item['quantity']) ?>"
                                                   data-price="<?= (float) $item['unit_price'] ?>">
                                        </td>
                                        <td>
                                            <input class="form-control form-control-sm"
                                                   data-invoice-price
                                                   type="number" min="0"
                                                   name="unit_price[]"
                                                   value="<?= (float) $item['unit_price'] ?>"
                                                   data-qty="<?= (float) $item['quantity'] ?>">
                                        </td>
                                        <td class="fw-bold" data-invoice-subtotal><?= format_rupiah($item['subtotal']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <button class="btn btn-primary btn-sm" type="submit">
                        <i class="bi bi-floppy me-1"></i>Simpan Perubahan
                    </button>
                </form>
            </div>
        </div>
        <?php else: ?>
        <!-- View-only invoice items -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom py-3 px-4">
                <p class="small fw-bold text-uppercase text-secondary mb-0">Rincian Invoice</p>
            </div>
            <div class="card-body">
                <div class="row g-3 mb-3">
                    <div class="col-6 col-md-3"><p class="small fw-bold text-uppercase text-secondary mb-1">Hari Aktual</p><p class="fw-bold mb-0"><?= (int) $invoice['work_days_actual'] ?> hari</p></div>
                    <div class="col-6 col-md-3"><p class="small fw-bold text-uppercase text-secondary mb-1">Tukang Aktual</p><p class="fw-bold mb-0"><?= (int) $invoice['worker_count_actual'] ?> orang</p></div>
                    <div class="col-6 col-md-3"><p class="small fw-bold text-uppercase text-secondary mb-1">Subtotal Estimasi</p><p class="fw-bold mb-0"><?= format_rupiah($invoice['subtotal_estimate']) ?></p></div>
                    <div class="col-6 col-md-3"><p class="small fw-bold text-uppercase text-secondary mb-1">Total Revisi</p><p class="fw-bold mb-0"><?= format_rupiah($invoice['revision_total']) ?></p></div>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light"><tr><th>Item</th><th>Tipe</th><th>Qty</th><th>Harga</th><th>Subtotal</th></tr></thead>
                        <tbody>
                            <?php foreach ($invoiceItems as $item): ?>
                                <tr>
                                    <td><strong><?= h($item['item_name']) ?></strong><?php if ($item['description']): ?><br><small class="text-muted"><?= h($item['description']) ?></small><?php endif; ?></td>
                                    <td><span class="badge text-bg-secondary"><?= h($item['item_type']) ?></span></td>
                                    <td><?= h($item['quantity'] . ' ' . $item['unit']) ?></td>
                                    <td><?= format_rupiah($item['unit_price']) ?></td>
                                    <td><strong><?= format_rupiah($item['subtotal']) ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$invoiceItems): ?><tr><td colspan="5" class="text-center text-muted py-3">Belum ada item.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Upload bukti bayar untuk customer (hanya jika invoice sudah dikirim) -->
        <?php if ($role === 'customer' && $invoice['status'] === 'Dikirim' && $invoice['payment_status'] === 'Belum Dibayar'): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom py-3 px-4">
                    <p class="small fw-bold text-uppercase text-secondary mb-0">Pembayaran</p>
                    <h3 class="h6 fw-bold mb-0">Upload Bukti Transfer</h3>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">Transfer ke rekening yang tertera di invoice, lalu upload bukti transfer di sini.</p>
                    <?php render_upload_payment_proof_form((int) $invoice['id']); ?>
                </div>
            </div>
        <?php elseif ($role === 'customer' && $invoice['payment_status'] === 'Lunas'): ?>
            <div class="alert alert-success d-flex align-items-center gap-2 mb-4">
                <i class="bi bi-check-circle-fill fs-5"></i>
                <div><strong>Pembayaran Lunas</strong><p class="mb-0 small">Terima kasih, pembayaran Anda sudah dikonfirmasi.</p></div>
            </div>
        <?php elseif ($role === 'customer' && $invoice['status'] !== 'Dikirim'): ?>
            <div class="alert alert-info small mb-4">
                <i class="bi bi-info-circle me-1"></i>Invoice sedang direview oleh staff sebelum dikirim ke Anda.
            </div>
        <?php endif; ?>

    <?php else: ?>
        <div class="card card-body text-center text-secondary py-5">
            <i class="bi bi-receipt"></i>
            <h3>Invoice belum dibuat</h3>
            <p>Pekerja lapangan belum mengirim data final untuk booking ini.</p>
        </div>
    <?php endif; ?>

    <script>
    // Hitung ulang subtotal saat qty/harga berubah
    document.querySelectorAll('[data-invoice-quantity], [data-invoice-price]').forEach(function(input) {
        input.addEventListener('input', function() {
            var row = this.closest('tr');
            var qty   = parseFloat(row.querySelector('[data-invoice-quantity]').value) || 0;
            var price = parseFloat(row.querySelector('[data-invoice-price]').value) || 0;
            var sub   = qty * price;
            row.querySelector('[data-invoice-subtotal]').textContent = 'Rp ' + sub.toLocaleString('id-ID');
        });
    });
    </script>
    <?php
    return;
}

// ── MODE LIST ────────────────────────────────────────────────────────────────
$filters = booking_filters_from_request();
$invSt = ['Invoice Final Dibuat','Menunggu Pembayaran','Lunas','Selesai'];
$ew = ['b.status IN (' . implode(',', array_fill(0, count($invSt), '?')) . ')']; $ep = $invSt;
if ($filters['status'] !== '') { $ew = ['b.status = ?']; $ep = [$filters['status']]; }
$paged = booking_list_for_role($user, $role, ['q' => $filters['q'], 'status' => ''], $ew, $ep);
$rows  = $paged['rows'];
render_booking_page_header('Invoice', match ($role) { 'customer' => 'Invoice Saya', 'boss' => 'Monitoring Invoice', default => 'Invoice Final' }, match ($role) { 'customer' => 'Invoice final dari booking Anda.', 'boss' => 'Monitoring semua invoice final.', default => 'Booking yang memiliki invoice untuk direview atau dikirim.' });
echo '<div class="card border-0 shadow-sm">';
render_booking_filter('booking_invoice', $filters);
render_booking_card_list($rows, $role, 'booking_invoice', function ($item) use ($role) {
    $cta = match ($item['status']) { 'Invoice Final Dibuat' => 'Review Invoice', 'Menunggu Pembayaran' => $role === 'customer' ? 'Bayar' : 'Verifikasi', default => 'Lihat Invoice' };
    return [$cta, 'dashboard.php?page=booking_invoice&id=' . (int) $item['id'], 'btn-primary'];
});
if ($paged['totalPages'] > 1) { echo '<div class="card-footer bg-white border-top py-3">'; render_pagination($paged['page'], $paged['totalPages'], $paged['total'], $paged['perPage']); echo '</div>'; }
echo '</div>';
