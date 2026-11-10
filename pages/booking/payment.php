<?php
declare(strict_types=1);
require_once __DIR__ . '/../../data/session_helper.php';
require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/_queries.php';
require_once __DIR__ . '/_forms.php';
require_once __DIR__ . '/_list_helpers.php';

require_roles(['customer', 'staff', 'boss']);

function payment_scope_for_role(array $user, string $role, string $alias = 'b'): array
{
    if ($role === 'customer') {
        return ["{$alias}.customer_id = ?", [$user['id']]];
    }
    return ['', []];
}

function payment_filter_query(array $user, string $role, string $lane, string $search): array
{
    [$scopeSql, $params] = payment_scope_for_role($user, $role);
    $where = [];
    if ($scopeSql !== '') {
        $where[] = $scopeSql;
    }

    $where[] = "(i.id IS NOT NULL OR b.survey_fee_payment_status <> 'Tidak Ada' OR b.status IN ('Menunggu Pembayaran','Lunas','Selesai','Estimasi Ditolak'))";

    if ($search !== '') {
        $where[] = '(b.booking_code LIKE ? OR u.name LIKE ? OR s.name LIKE ? OR i.invoice_code LIKE ?)';
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }

    if ($lane === 'needs_confirmation') {
        $where[] = "EXISTS (SELECT 1 FROM payments p WHERE p.booking_id=b.id AND p.payment_status='Belum Dibayar' AND p.payment_proof IS NOT NULL)";
    } elseif ($lane === 'invoice') {
        $where[] = 'i.id IS NOT NULL';
    } elseif ($lane === 'survey_fee') {
        $where[] = "i.id IS NULL AND b.survey_fee_payment_status <> 'Tidak Ada'";
    } elseif ($lane === 'paid') {
        $where[] = "((i.id IS NOT NULL AND i.payment_status='Lunas') OR (i.id IS NULL AND b.survey_fee_payment_status='Sudah Dibayar'))";
    } elseif ($lane === 'unpaid') {
        $where[] = "((i.id IS NOT NULL AND i.payment_status='Belum Dibayar') OR (i.id IS NULL AND b.survey_fee_payment_status='Belum Dibayar'))";
    }

    return [$where ? 'WHERE ' . implode(' AND ', $where) : '', $params];
}

function payment_list_sql(string $where): string
{
    return "SELECT b.*, u.name AS customer_name, u.phone AS customer_phone, s.name AS service_name,
            i.id AS invoice_id, i.invoice_code, i.final_total, i.status AS invoice_status, i.payment_status AS invoice_payment_status,
            (SELECT COUNT(*) FROM payments p WHERE p.booking_id=b.id AND p.payment_status='Belum Dibayar' AND p.payment_proof IS NOT NULL) AS proof_count,
            (SELECT MAX(COALESCE(p.paid_at, p.created_at)) FROM payments p WHERE p.booking_id=b.id) AS last_payment_at
        FROM bookings b
        JOIN users u ON u.id=b.customer_id
        LEFT JOIN services s ON s.id=b.service_id
        LEFT JOIN final_invoices i ON i.id=(SELECT fi.id FROM final_invoices fi WHERE fi.booking_id=b.id ORDER BY fi.id DESC LIMIT 1)
        {$where}
        ORDER BY proof_count DESC, COALESCE(last_payment_at, b.updated_at) DESC, b.updated_at DESC";
}

function payment_row_meta(array $row): array
{
    $hasInvoice = !empty($row['invoice_id']);
    $type = $hasInvoice ? 'Invoice Final' : 'Survey Fee';
    $code = $hasInvoice ? (string) $row['invoice_code'] : (string) $row['booking_code'];
    $amount = $hasInvoice ? (int) $row['final_total'] : (int) $row['survey_fee_amount'];
    $paymentStatus = $hasInvoice ? (string) $row['invoice_payment_status'] : (string) $row['survey_fee_payment_status'];
    $invoiceStatus = $hasInvoice ? (string) $row['invoice_status'] : '-';

    return compact('hasInvoice', 'type', 'code', 'amount', 'paymentStatus', 'invoiceStatus');
}

$bookingId = (int) ($_GET['id'] ?? 0);

if ($bookingId > 0) {
    $booking = booking_for_access($bookingId, $user);
    if (!$booking) {
        echo '<div class="alert alert-warning">Data pembayaran tidak ditemukan atau tidak dapat diakses.</div>';
        return;
    }

    $invoice = latest_booking_invoice($bookingId);
    $payments = db_query(
        'SELECT p.*, u.name AS marked_by_name FROM payments p LEFT JOIN users u ON u.id=p.marked_by WHERE p.booking_id=? ORDER BY p.created_at DESC',
        [$bookingId]
    );
    $latestProof = null;
    foreach ($payments as $pay) {
        if (!empty($pay['payment_proof'])) {
            $latestProof = $pay['payment_proof'];
            break;
        }
    }

    $paymentType = $invoice ? 'Invoice Final' : 'Survey Fee';
    $paymentCode = $invoice ? (string) $invoice['invoice_code'] : (string) $booking['booking_code'];
    $amount = $invoice ? (int) $invoice['final_total'] : (int) $booking['survey_fee_amount'];
    $paymentStatus = $invoice ? (string) $invoice['payment_status'] : (string) $booking['survey_fee_payment_status'];
    $canCustomerUpload = $role === 'customer' && $paymentStatus === 'Belum Dibayar' && (!$invoice || $invoice['status'] === 'Dikirim');
    $canStaffConfirm = $role === 'staff' && $paymentStatus === 'Belum Dibayar';
    $proofPending = (bool) db_first("SELECT id FROM payments WHERE booking_id=? AND payment_status='Belum Dibayar' AND payment_proof IS NOT NULL LIMIT 1", [$bookingId]);

    render_booking_detail_header($booking, $role, 'Pembayaran', 'booking_payment');
    ?>
    <section class="row g-3">
        <div class="col-lg-8 d-flex flex-column gap-3">
            <section class="card card-body shadow-sm">
                <div>
                    <p class="small fw-bold text-uppercase text-secondary mb-1"><?= h($paymentType) ?></p>
                    <h2><?= format_rupiah($amount) ?></h2>
                    <p><?= h($paymentCode) ?> · <?= h($booking['customer_name']) ?></p>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <span class="<?= status_badge_class($paymentStatus) ?>"><?= h($paymentStatus) ?></span>
                    <?php if ($invoice): ?>
                        <a class="btn btn-outline-secondary btn-sm" href="invoice_print.php?id=<?= (int) $invoice['id'] ?>" target="_blank"><i class="bi bi-printer me-1"></i>Cetak Invoice</a>
                    <?php endif; ?>
                </div>
            </section>

            <section class="row g-3">
                <div class="col-md border rounded p-3 <?= $paymentStatus !== 'Tidak Ada' ? 'border-success bg-success-subtle' : '' ?>">
                    <span>1</span><div><strong>Tagihan dibuat</strong><small><?= h($paymentType) ?> tersedia untuk customer.</small></div>
                </div>
                <div class="col-md border rounded p-3 <?= $latestProof ? 'border-success bg-success-subtle' : ($paymentStatus === 'Belum Dibayar' ? 'border-primary bg-primary-subtle' : '') ?>">
                    <span>2</span><div><strong>Bukti pembayaran</strong><small><?= $latestProof ? 'Bukti transfer sudah dikirim.' : 'Customer mengirim bukti transfer.' ?></small></div>
                </div>
                <div class="col-md border rounded p-3 <?= $paymentStatus === 'Lunas' || $paymentStatus === 'Sudah Dibayar' ? 'border-success bg-success-subtle' : ($proofPending ? 'border-primary bg-primary-subtle' : '') ?>">
                    <span>3</span><div><strong>Konfirmasi staff</strong><small>Staff mengecek bukti dan menandai lunas.</small></div>
                </div>
            </section>

            <?php if ($canCustomerUpload): ?>
                <section class="card border-0 shadow-sm card shadow-sm">
                    <div class="card-header bg-body d-flex flex-wrap align-items-center justify-content-between gap-3"><div><p class="small fw-bold text-uppercase text-secondary mb-1">Customer</p><h3>Upload Bukti Pembayaran</h3></div></div>
                    <div class="card-body">
                        <p class="text-muted small mb-3">Upload foto atau screenshot bukti transfer. Staff akan mengonfirmasi setelah bukti dicek.</p>
                        <?php if ($invoice): ?>
                            <?php render_upload_payment_proof_form((int) $invoice['id']); ?>
                        <?php else: ?>
                            <form data-api-form class="d-flex flex-column gap-3" method="post" enctype="multipart/form-data">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="upload_payment_proof">
                                <input type="hidden" name="booking_id" value="<?= (int) $bookingId ?>">
                                <div><label class="form-label">Foto / screenshot bukti transfer <span class="text-danger">*</span></label><input class="form-control" type="file" name="payment_proof" accept=".jpg,.jpeg,.png,.webp" required></div>
                                <div><label class="form-label">Catatan (opsional)</label><input class="form-control" type="text" name="note" placeholder="Misal: transfer via BCA a.n. Budi"></div>
                                <button class="btn btn-primary" type="submit"><i class="bi bi-upload me-1"></i>Kirim Bukti Bayar</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </section>
            <?php elseif ($role === 'customer' && $invoice && $invoice['status'] !== 'Dikirim' && $paymentStatus === 'Belum Dibayar'): ?>
                <div class="alert alert-info">Invoice sedang disiapkan staff. Tombol upload bukti akan muncul setelah invoice dikirim.</div>
            <?php endif; ?>

            <?php if ($canStaffConfirm): ?>
                <section class="card border-0 shadow-sm card shadow-sm">
                    <div class="card-header bg-body d-flex flex-wrap align-items-center justify-content-between gap-3"><div><p class="small fw-bold text-uppercase text-secondary mb-1">Staff</p><h3>Konfirmasi Pembayaran</h3></div></div>
                    <div class="card-body d-flex flex-wrap gap-2 align-items-center justify-content-between">
                        <p class="text-muted small mb-0"><?= $proofPending ? 'Bukti bayar sudah masuk. Cocokkan nominal sebelum menandai lunas.' : 'Belum ada bukti upload. Tetap bisa ditandai lunas jika pembayaran sudah terverifikasi manual.' ?></p>
                        <?php $invoice ? render_mark_invoice_paid_form($invoice) : render_mark_survey_fee_paid_form($booking); ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($latestProof && in_array($role, ['staff', 'boss', 'customer'], true)): ?>
                <section class="card border-0 shadow-sm card shadow-sm">
                    <div class="card-header bg-body d-flex flex-wrap align-items-center justify-content-between gap-3"><div><p class="small fw-bold text-uppercase text-secondary mb-1">Bukti Transfer</p><h3>Bukti Terakhir</h3></div><a href="<?= h($latestProof) ?>" target="_blank" class="btn btn-outline-secondary btn-sm">Buka File</a></div>
                    <div class="card-body"><a href="<?= h($latestProof) ?>" target="_blank"><img src="<?= h($latestProof) ?>" alt="Bukti pembayaran" class="img-fluid rounded border"></a></div>
                </section>
            <?php endif; ?>

            <section class="card border-0 shadow-sm card shadow-sm">
                <div class="card-header bg-body d-flex flex-wrap align-items-center justify-content-between gap-3"><div><p class="small fw-bold text-uppercase text-secondary mb-1">Pencatatan</p><h3>Riwayat Pembayaran</h3></div></div>
                <?php if ($payments): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($payments as $pay): ?>
                            <article>
                                <div><strong><?= h($pay['payment_type']) ?> · <?= format_rupiah($pay['amount']) ?></strong><span><?= h($pay['marked_by_name'] ?? 'Customer') ?> · <?= h(format_datetime($pay['paid_at'] ?: $pay['created_at'])) ?></span><?php if (!empty($pay['note'])): ?><small><?= h($pay['note']) ?></small><?php endif; ?></div>
                                <div class="d-flex flex-wrap gap-2"><span class="<?= status_badge_class($pay['payment_status']) ?>"><?= h($pay['payment_status']) ?></span><?php if (!empty($pay['payment_proof'])): ?><a href="<?= h($pay['payment_proof']) ?>" target="_blank">Bukti</a><?php endif; ?></div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="card-body"><p class="text-muted small mb-0">Belum ada pembayaran yang dicatat untuk booking ini.</p></div>
                <?php endif; ?>
            </section>
        </div>

        <aside class="col-lg-4 card card-body shadow-sm">
            <p class="small fw-bold text-uppercase text-secondary mb-1">Konteks</p>
            <h3><?= h($booking['booking_code']) ?></h3>
            <dl>
                <div><dt>Customer</dt><dd><?= h($booking['customer_name']) ?></dd></div>
                <div><dt>Layanan</dt><dd><?= h($booking['service_name'] ?? 'Custom Layanan') ?></dd></div>
                <div><dt>Status order</dt><dd><span class="<?= status_badge_class($booking['status']) ?>"><?= h(booking_status_label_for_role($booking['status'], $role)) ?></span></dd></div>
                <div><dt>Jenis tagihan</dt><dd><?= h($paymentType) ?></dd></div>
            </dl>
            <div class="list-group list-group-flush">
                <a href="dashboard.php?page=booking_detail&id=<?= (int) $bookingId ?>">Ringkasan Booking</a>
                <?php if ($invoice): ?><a href="dashboard.php?page=booking_invoice&id=<?= (int) $bookingId ?>">Lihat Invoice</a><?php endif; ?>
            </div>
        </aside>
    </section>
    <?php
    return;
}

$lane = trim((string) ($_GET['lane'] ?? 'needs_confirmation'));
$allowedLanes = ['needs_confirmation', 'unpaid', 'invoice', 'survey_fee', 'paid', 'all'];
if (!in_array($lane, $allowedLanes, true)) {
    $lane = 'needs_confirmation';
}
$search = trim((string) ($_GET['q'] ?? ''));
[$where, $params] = payment_filter_query($user, $role, $lane, $search);
$paged = paginate(payment_list_sql($where), $params, 15);
$rows = $paged['rows'];

[$summaryWhere, $summaryParams] = payment_filter_query($user, $role, 'all', '');
$summaryRows = db_query(payment_list_sql($summaryWhere), $summaryParams);
$summary = ['needs_confirmation' => 0, 'unpaid' => 0, 'invoice' => 0, 'survey_fee' => 0, 'paid' => 0, 'all' => count($summaryRows)];
foreach ($summaryRows as $row) {
    $meta = payment_row_meta($row);
    if ((int) ($row['proof_count'] ?? 0) > 0 && !in_array($meta['paymentStatus'], ['Lunas', 'Sudah Dibayar'], true)) {
        $summary['needs_confirmation']++;
    }
    if ($meta['paymentStatus'] === 'Belum Dibayar') {
        $summary['unpaid']++;
    }
    if ($meta['hasInvoice']) {
        $summary['invoice']++;
    } else {
        $summary['survey_fee']++;
    }
    if (in_array($meta['paymentStatus'], ['Lunas', 'Sudah Dibayar'], true)) {
        $summary['paid']++;
    }
}

$laneLabels = [
    'needs_confirmation' => 'Perlu Konfirmasi',
    'unpaid' => 'Belum Dibayar',
    'invoice' => 'Invoice Final',
    'survey_fee' => 'Survey Fee',
    'paid' => 'Lunas',
    'all' => 'Semua',
];
?>
<section class="d-flex flex-column gap-3">
    <div class="card card-body border-0 shadow-sm d-flex flex-md-row align-items-md-center justify-content-between gap-3">
        <div>
            <p class="small fw-bold text-uppercase text-secondary mb-1">Pembayaran</p>
            <h2><?= h(match ($role) { 'customer' => 'Pembayaran Saya', 'boss' => 'Monitoring Pembayaran', default => 'Pusat Pembayaran' }) ?></h2>
            <p>Kelola tagihan, bukti transfer, dan konfirmasi lunas dari satu alur yang jelas.</p>
        </div>
    </div>

    <div class="row g-3">
        <div><span>Perlu Konfirmasi</span><strong><?= (int) $summary['needs_confirmation'] ?></strong></div>
        <div><span>Belum Dibayar</span><strong><?= (int) $summary['unpaid'] ?></strong></div>
        <div><span>Invoice Final</span><strong><?= (int) $summary['invoice'] ?></strong></div>
        <div><span>Lunas</span><strong><?= (int) $summary['paid'] ?></strong></div>
    </div>

    <nav class="nav nav-pills flex-wrap gap-2" aria-label="Filter pembayaran">
        <?php foreach ($laneLabels as $key => $label): ?>
            <a class="<?= $lane === $key ?'active' : '' ?>" href="dashboard.php?page=booking_payment&lane=<?= h($key) ?><?= $search !== '' ? '&q=' . h(urlencode($search)) : '' ?>"><span><?= h($label) ?></span><b><?= (int) ($summary[$key] ?? $summary['all']) ?></b></a>
        <?php endforeach; ?>
    </nav>

    <section class="card border-0 shadow-sm card shadow-sm">
        <div class="card-header bg-body d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div><p class="small fw-bold text-uppercase text-secondary mb-1">Daftar</p><h3><?= h($laneLabels[$lane]) ?></h3></div>
            <form class="d-flex flex-wrap gap-2" method="get">
                <input type="hidden" name="page" value="booking_payment">
                <input type="hidden" name="lane" value="<?= h($lane) ?>">
                <input class="form-control form-control-sm" type="search" name="q" value="<?= h($search) ?>" placeholder="Cari booking, customer, layanan, invoice">
                <button class="btn btn-primary btn-sm" type="submit">Cari</button>
                <?php if ($search !== ''): ?><a class="btn btn-outline-secondary btn-sm" href="dashboard.php?page=booking_payment&lane=<?= h($lane) ?>">Reset</a><?php endif; ?>
            </form>
        </div>

        <?php if ($rows): ?>
            <div class="list-group list-group-flush">
                <?php foreach ($rows as $row): ?>
                    <?php $meta = payment_row_meta($row); $needsConfirmation = (int) ($row['proof_count'] ?? 0) > 0 && !in_array($meta['paymentStatus'], ['Lunas', 'Sudah Dibayar'], true); ?>
                    <article class="list-group-item d-flex flex-wrap align-items-center justify-content-between gap-3 <?= $needsConfirmation ?'needs-confirmation' : '' ?>">
                        <div class="flex-grow-1">
                            <strong><?= h($row['booking_code']) ?></strong>
                            <span><?= h($row['customer_name']) ?> · <?= h($row['service_name'] ?? 'Custom Layanan') ?></span>
                            <small><?= h($meta['type']) ?><?= $meta['hasInvoice'] ? ' · ' . h($meta['code']) : '' ?></small>
                        </div>
                        <div class="text-md-end"><strong><?= format_rupiah($meta['amount']) ?></strong><span><?= $needsConfirmation ? 'Bukti masuk' : 'Nominal tagihan' ?></span></div>
                        <div class="d-flex align-items-center"><span class="<?= status_badge_class($meta['paymentStatus']) ?>"><?= h($meta['paymentStatus']) ?></span></div>
                        <div class="d-flex flex-wrap gap-2">
                            <a class="btn btn-primary btn-sm" href="dashboard.php?page=booking_payment&id=<?= (int) $row['id'] ?>"><?= $needsConfirmation && $role === 'staff' ? 'Verifikasi' : 'Detail' ?></a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="card-body"><div class="card card-body text-center text-secondary py-5"><h3>Tidak ada pembayaran</h3><p>Tidak ada data yang cocok dengan filter ini.</p></div></div>
        <?php endif; ?>

        <?php if ($paged['totalPages'] > 1): ?>
            <div class="card-footer bg-white border-top py-3"><?php render_pagination($paged['page'], $paged['totalPages'], $paged['total'], $paged['perPage']); ?></div>
        <?php endif; ?>
    </section>
</section>
