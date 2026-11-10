<?php
declare(strict_types=1);

require_once __DIR__ . '/../../data/session_helper.php';
require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/_queries.php';
require_once __DIR__ . '/_forms.php';

$bookingId = max(0, (int) ($_GET['id'] ?? 0));
$booking = booking_for_access($bookingId, $user);
if (!$booking) {
    echo '<div class="alert alert-warning">Order tidak ditemukan atau tidak dapat diakses.</div>';
    return;
}

$rel = booking_related_records($bookingId);
$review = db_first('SELECT * FROM reviews WHERE booking_id=?', [$bookingId]);
$histories = db_query(
    'SELECT h.*, u.name AS user_name
     FROM booking_status_histories h
     LEFT JOIN users u ON u.id=h.changed_by
     WHERE h.booking_id=? ORDER BY h.created_at DESC',
    [$bookingId]
);
$paymentProof = null;
foreach ($rel['payments'] as $paymentRow) {
    if (!empty($paymentRow['payment_proof'])) {
        $paymentProof = $paymentRow['payment_proof'];
        break;
    }
}
$nextAction = booking_next_action_label(
    $booking,
    $role,
    $rel['estimate'],
    $rel['revision'],
    $rel['invoice'],
    $paymentProof !== null,
    $review !== false && $review !== null
);
$activeSection = trim((string) ($_GET['section'] ?? ($nextAction ? 'actions' : 'overview')));
$sections = [
    'overview' => ['Ringkasan', 'bi-grid'],
    'survey' => ['Survei', 'bi-clipboard2-check'],
    'estimate' => ['Estimasi', 'bi-calculator'],
    'progress' => ['Progres', 'bi-graph-up-arrow'],
    'billing' => ['Invoice & Bayar', 'bi-receipt'],
    'timeline' => ['Aktivitas', 'bi-clock-history'],
    'actions' => ['Tindakan', 'bi-lightning-charge'],
];
if ($role === 'pekerja_lapangan') {
    unset($sections['billing']);
}
if (!isset($sections[$activeSection])) {
    $activeSection = 'overview';
}
$stage = booking_workflow_stage_from_status($booking['status']);
$stageLabels = ['Booking', 'Survei', 'Persetujuan', 'Pengerjaan', 'Penyelesaian'];
$sectionUrl = static fn (string $section): string => booking_detail_url($bookingId, $section);
$progressPercent = (int) ($rel['progress'][0]['progress_percent'] ?? 0);
?>

<a class="btn btn-outline-secondary btn-sm mb-3" href="dashboard.php?page=<?= h(booking_list_page_for_role($role)) ?>">
    <i class="bi bi-arrow-left me-1"></i>Kembali ke daftar
</a>

<header class="page-intro">
    <div class="page-intro__copy">
        <p class="page-kicker"><?= h($booking['booking_code']) ?> · <?= h(booking_workflow_stage_label($booking['status'])) ?></p>
        <h2><?= h($booking['customer_name']) ?></h2>
        <p><?= h($booking['service_name'] ?? 'Custom Layanan') ?> · <?= h($booking['location_address']) ?></p>
    </div>
    <div class="d-flex flex-column align-items-start align-items-lg-end gap-2">
        <span class="status-label"><?= h(booking_status_label_for_role($booking['status'], $role)) ?></span>
        <?php if ($nextAction): ?><a class="btn btn-primary" href="<?= h($sectionUrl('actions')) ?>"><?= h($nextAction) ?> <i class="bi bi-arrow-right ms-1"></i></a><?php endif; ?>
    </div>
</header>

<section class="order-stage-track" aria-label="Tahap order">
    <?php foreach ($stageLabels as $index => $label): ?>
        <div class="order-stage rounded-3 p-3 <?= $index < $stage ? 'is-done' : ($index === $stage ? 'is-current' : '') ?>">
            <small class="d-block opacity-75"><?= $index < $stage ? 'Selesai' : 'Tahap ' . ($index + 1) ?></small>
            <strong><?= h($label) ?></strong>
        </div>
    <?php endforeach; ?>
</section>

<nav class="nav order-tabs mb-4" aria-label="Bagian detail order">
    <?php foreach ($sections as $key => [$label, $icon]): ?>
        <a class="nav-link <?= $activeSection === $key ? 'active' : '' ?>" href="<?= h($sectionUrl($key)) ?>">
            <i class="bi <?= h($icon) ?> me-1"></i><?= h($label) ?>
            <?php if ($key === 'actions' && $nextAction): ?><span class="badge text-bg-warning ms-1">1</span><?php endif; ?>
        </a>
    <?php endforeach; ?>
</nav>

<?php if ($activeSection === 'overview'): ?>
    <section class="row g-3">
        <div class="col-lg-8">
            <article class="card border-0 shadow-sm h-100">
                <div class="card-body p-4">
                    <p class="eyebrow mb-1">Informasi utama</p>
                    <h3 class="h5 mb-4">Ringkasan Order</h3>
                    <div class="detail-facts">
                        <?php
                        $facts = [
                            ['Customer', $booking['customer_name'], 'bi-person'],
                            ['Kontak', $booking['customer_phone'] ?: $booking['customer_email'], 'bi-telephone'],
                            ['Layanan', $booking['service_name'] ?? 'Custom Layanan', 'bi-flower1'],
                            ['Pekerja', $booking['pekerja_lapangan_name'] ?? 'Belum ditentukan', 'bi-person-check'],
                            ['Survei diminta', format_date_id($booking['requested_survey_date']), 'bi-calendar3'],
                            ['Jadwal survei', format_date_id($booking['actual_survey_date']) . ($booking['actual_survey_time'] ? ' · ' . substr($booking['actual_survey_time'], 0, 5) : ''), 'bi-calendar2-check'],
                            ['Luas', ($booking['actual_area'] ?: $booking['estimated_area'] ?: '-') . ' m²', 'bi-rulers'],
                            ['Progres', $progressPercent . '%', 'bi-graph-up'],
                        ];
                        foreach ($facts as [$label, $value, $icon]):
                        ?>
                            <div class="detail-fact">
                                    <i class="bi <?= h($icon) ?>"></i>
                                    <div><small class="text-muted d-block"><?= h($label) ?></small><strong><?= h($value) ?></strong></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($booking['customer_notes'] || $booking['custom_service_description']): ?>
                        <div class="bg-light border rounded-3 p-3 mt-4">
                            <small class="text-muted d-block mb-1">Catatan kebutuhan</small>
                            <p class="mb-0"><?= h($booking['custom_service_description'] ?: $booking['customer_notes']) ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </article>
        </div>
        <div class="col-lg-4 detail-rail">
            <article class="card border-0 shadow-sm h-100">
                <div class="card-body p-4">
                    <p class="eyebrow mb-1">Posisi sekarang</p>
                    <h3 class="h5">Tahap <?= h(booking_workflow_stage_label($booking['status'])) ?></h3>
                    <p class="text-muted small"><?= h(booking_status_label_for_role($booking['status'], $role)) ?></p>
                    <hr>
                    <?php if ($nextAction): ?>
                        <div class="rounded-3 bg-warning-subtle border border-warning-subtle p-3">
                            <small class="text-warning-emphasis d-block mb-1">Perlu tindakan Anda</small>
                            <strong><?= h($nextAction) ?></strong>
                            <a class="btn btn-primary btn-sm w-100 mt-3" href="<?= h($sectionUrl('actions')) ?>">Kerjakan sekarang</a>
                        </div>
                    <?php else: ?>
                        <div class="rounded-3 bg-success-subtle p-3 text-success-emphasis">
                            <i class="bi bi-check2-circle me-1"></i>Tidak ada tindakan tertunda.
                        </div>
                    <?php endif; ?>
                </div>
            </article>
        </div>
    </section>
<?php endif; ?>

<?php if ($activeSection === 'survey'): ?>
    <section class="row g-3">
        <div class="col-lg-5">
            <article class="card border-0 shadow-sm h-100">
                <div class="card-body p-4">
                    <p class="eyebrow mb-1">Penjadwalan</p><h3 class="h5 mb-3">Jadwal Survei</h3>
                    <?php foreach ($rel['schedules'] as $schedule): ?>
                        <div class="border rounded-3 p-3 mb-2">
                            <strong><?= h(format_date_id($schedule['schedule_date'])) ?> · <?= h(substr($schedule['schedule_time'], 0, 5)) ?></strong>
                            <small class="d-block text-muted"><?= h($schedule['pekerja_lapangan_name']) ?> · <?= h($schedule['status']) ?></small>
                            <?php if ($schedule['notes']): ?><p class="small mb-0 mt-2"><?= h($schedule['notes']) ?></p><?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$rel['schedules']): ?><p class="text-muted">Survei belum dijadwalkan.</p><?php endif; ?>
                </div>
            </article>
        </div>
        <div class="col-lg-7">
            <article class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <p class="eyebrow mb-1">Temuan lapangan</p><h3 class="h5 mb-3">Hasil Survei</h3>
                    <?php if ($rel['survey']): ?>
                        <dl class="row mb-0">
                            <dt class="col-sm-4">Luas aktual</dt><dd class="col-sm-8"><?= h($rel['survey']['actual_area']) ?> m²</dd>
                            <dt class="col-sm-4">Kondisi</dt><dd class="col-sm-8"><?= h($rel['survey']['location_condition']) ?></dd>
                            <dt class="col-sm-4">Akses</dt><dd class="col-sm-8"><?= h($rel['survey']['access_condition']) ?></dd>
                            <dt class="col-sm-4">Perkiraan tim</dt><dd class="col-sm-8"><?= (int) $rel['survey']['estimated_worker_count'] ?> pekerja · <?= (int) $rel['survey']['estimated_work_days'] ?> hari</dd>
                            <dt class="col-sm-4">Kendala</dt><dd class="col-sm-8"><?= h($rel['survey']['obstacle_notes'] ?: '-') ?></dd>
                        </dl>
                    <?php else: ?><p class="text-muted">Hasil survei belum tersedia.</p><?php endif; ?>
                </div>
            </article>
            <?php $surveyPhotos = array_filter($rel['photos'], static fn ($photo) => $photo['photo_type'] === 'before'); ?>
            <?php if ($surveyPhotos): ?>
                <div class="row g-2 mt-1">
                    <?php foreach ($surveyPhotos as $photo): ?>
                        <a class="col-6 col-md-4" href="<?= h($photo['photo_path']) ?>" target="_blank"><img class="img-fluid rounded-3 border" src="<?= h($photo['photo_path']) ?>" alt="Dokumentasi survei"></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>

<?php if ($activeSection === 'estimate'): ?>
    <section class="card border-0 shadow-sm mb-3">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                <div><p class="eyebrow mb-1">Versi dasar</p><h3 class="h5 mb-0"><?= h($rel['estimate']['estimate_code'] ?? 'Estimasi belum dibuat') ?></h3></div>
                <?php if ($rel['estimate']): ?><span class="<?= status_badge_class($rel['estimate']['status']) ?>"><?= h($rel['estimate']['status']) ?></span><?php endif; ?>
            </div>
            <?php if ($rel['estimate']): ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th>Item</th><th>Jenis</th><th>Qty</th><th>Harga</th><th class="text-end">Subtotal</th></tr></thead>
                        <tbody>
                        <?php foreach ($rel['estimateItems'] as $item): ?>
                            <tr><td><strong><?= h($item['item_name']) ?></strong></td><td><?= h($item['item_type']) ?></td><td><?= h($item['quantity'] . ' ' . $item['unit']) ?></td><td><?= format_rupiah($item['unit_price']) ?></td><td class="text-end fw-bold"><?= format_rupiah($item['subtotal']) ?></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                        <tfoot><tr><th colspan="4">Total estimasi</th><th class="text-end fs-5"><?= format_rupiah($rel['estimate']['grand_total']) ?></th></tr></tfoot>
                    </table>
                </div>
            <?php else: ?><p class="text-muted mb-0">Estimasi dibuat setelah hasil survei tersimpan.</p><?php endif; ?>
        </div>
    </section>
    <section class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <p class="eyebrow mb-1">Riwayat versi</p><h3 class="h5 mb-3">Revisi Estimasi</h3>
            <?php foreach ($rel['revisions'] as $revision): ?>
                <div class="d-flex flex-wrap justify-content-between gap-3 border rounded-3 p-3 mb-2">
                    <div><strong><?= h($revision['revision_code']) ?></strong><small class="d-block text-muted"><?= h($revision['reason']) ?> · <?= h(format_datetime($revision['created_at'])) ?></small></div>
                    <div class="text-end"><span class="<?= status_badge_class($revision['status']) ?>"><?= h($revision['status']) ?></span><strong class="d-block"><?= format_rupiah($revision['old_total']) ?> → <?= format_rupiah($revision['new_total']) ?></strong></div>
                </div>
            <?php endforeach; ?>
            <?php if (!$rel['revisions']): ?><p class="text-muted mb-0">Belum ada revisi estimasi.</p><?php endif; ?>
        </div>
    </section>
<?php endif; ?>

<?php if ($activeSection === 'progress'): ?>
    <section class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between mb-2"><div><p class="eyebrow mb-1">Pengerjaan</p><h3 class="h5">Progres Lapangan</h3></div><strong class="text-success fs-3"><?= $progressPercent ?>%</strong></div>
            <div class="progress mb-4" style="height:.7rem"><div class="progress-bar bg-success" style="width:<?= $progressPercent ?>%"></div></div>
            <?php foreach ($rel['progress'] as $progress): ?>
                <article class="d-flex gap-3 border-start border-3 border-success ps-3 py-2 mb-3">
                    <span class="badge rounded-pill text-bg-success align-self-start"><?= (int) $progress['progress_percent'] ?>%</span>
                    <div><strong><?= h($progress['title']) ?></strong><small class="d-block text-muted"><?= h($progress['worker_name']) ?> · <?= h(format_datetime($progress['created_at'])) ?></small><?php if ($progress['note']): ?><p class="mb-0 mt-1"><?= h($progress['note']) ?></p><?php endif; ?><?php if ($progress['obstacle_note']): ?><p class="mb-0 mt-1 text-warning-emphasis"><i class="bi bi-exclamation-triangle me-1"></i><?= h($progress['obstacle_note']) ?></p><?php endif; ?></div>
                </article>
            <?php endforeach; ?>
            <?php if (!$rel['progress']): ?><p class="text-muted mb-0">Belum ada pembaruan progres.</p><?php endif; ?>
        </div>
    </section>
<?php endif; ?>

<?php if ($activeSection === 'billing' && $role !== 'pekerja_lapangan'): ?>
    <section class="row g-3">
        <div class="col-lg-7">
            <article class="card border-0 shadow-sm h-100">
                <div class="card-body p-4">
                    <p class="eyebrow mb-1">Tagihan final</p><h3 class="h5 mb-3"><?= h($rel['invoice']['invoice_code'] ?? 'Invoice belum tersedia') ?></h3>
                    <?php if ($rel['invoice']): ?>
                        <strong class="display-6 d-block mb-2"><?= format_rupiah($rel['invoice']['final_total']) ?></strong>
                        <span class="<?= status_badge_class($rel['invoice']['payment_status']) ?>"><?= h($rel['invoice']['payment_status']) ?></span>
                        <a class="btn btn-outline-secondary btn-sm ms-2" href="invoice_print.php?id=<?= (int) $rel['invoice']['id'] ?>" target="_blank"><i class="bi bi-printer me-1"></i>Cetak</a>
                    <?php else: ?><p class="text-muted">Invoice dibuat setelah pekerjaan selesai.</p><?php endif; ?>
                    <?php if ($paymentProof): ?>
                        <hr><p class="small fw-semibold">Bukti pembayaran terakhir</p><a href="<?= h($paymentProof) ?>" target="_blank"><img class="img-fluid rounded-3 border" src="<?= h($paymentProof) ?>" alt="Bukti pembayaran"></a>
                    <?php endif; ?>
                </div>
            </article>
        </div>
        <div class="col-lg-5">
            <article class="card border-0 shadow-sm h-100">
                <div class="card-body p-4">
                    <p class="eyebrow mb-1">Pencatatan</p><h3 class="h5 mb-3">Riwayat Pembayaran</h3>
                    <?php foreach ($rel['payments'] as $payment): ?>
                        <div class="border rounded-3 p-3 mb-2"><strong><?= format_rupiah($payment['amount']) ?></strong><span class="<?= status_badge_class($payment['payment_status']) ?> float-end"><?= h($payment['payment_status']) ?></span><small class="d-block text-muted"><?= h($payment['payment_type']) ?> · <?= h(format_datetime($payment['created_at'])) ?></small></div>
                    <?php endforeach; ?>
                    <?php if (!$rel['payments']): ?><p class="text-muted">Belum ada pembayaran.</p><?php endif; ?>
                </div>
            </article>
        </div>
    </section>
<?php endif; ?>

<?php if ($activeSection === 'timeline'): ?>
    <section class="card border-0 shadow-sm timeline-list">
        <div class="card-body p-4">
            <p class="eyebrow mb-1">Jejak proses</p><h3 class="h5 mb-4">Aktivitas Order</h3>
            <?php foreach ($histories as $history): ?>
                <article class="timeline-entry">
                    <span class="timeline-dot" aria-hidden="true"></span>
                    <div><strong><?= h(booking_status_label_for_role($history['new_status'], $role)) ?></strong><small class="d-block text-muted"><?= h(format_datetime($history['created_at'])) ?> · <?= h($history['user_name'] ?? 'Sistem') ?></small><?php if ($history['note']): ?><p class="mb-0 mt-1"><?= h(booking_status_note_for_role($history['note'], $role)) ?></p><?php endif; ?></div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?php if ($activeSection === 'actions'): ?>
    <section class="row g-3">
        <div class="col-lg-8">
            <article class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <p class="eyebrow mb-1">Langkah berikutnya</p><h3 class="h5 mb-3"><?= h($nextAction ?? 'Tidak ada tindakan tertunda') ?></h3>
                    <?php if ($role === 'staff'): ?>
                        <?php if ($booking['status'] === 'Menunggu Konfirmasi'): ?>
                            <?php render_schedule_survey_form($booking, get_pekerja_lapangan()); ?>
                            <hr><?php render_reject_booking_form($booking); ?>
                        <?php elseif ($rel['estimate'] && $rel['estimate']['status'] === 'Draft'): ?>
                            <p class="text-muted">Periksa rincian pada tab Estimasi sebelum dikirim.</p>
                            <form data-api-form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="send_estimate"><input type="hidden" name="estimate_id" value="<?= (int) $rel['estimate']['id'] ?>"><button class="btn btn-primary" type="submit"><i class="bi bi-send me-1"></i>Kirim Estimasi ke Customer</button></form>
                        <?php elseif ($rel['revision'] && $rel['revision']['status'] === 'Draft'): ?>
                            <form data-api-form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="send_revision"><input type="hidden" name="revision_id" value="<?= (int) $rel['revision']['id'] ?>"><button class="btn btn-primary" type="submit"><i class="bi bi-send me-1"></i>Kirim Revisi ke Customer</button></form>
                        <?php elseif ($rel['invoice'] && $rel['invoice']['status'] === 'Draft'): ?>
                            <form data-api-form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="send_invoice"><input type="hidden" name="invoice_id" value="<?= (int) $rel['invoice']['id'] ?>"><button class="btn btn-primary" type="submit"><i class="bi bi-send me-1"></i>Kirim Invoice</button></form>
                        <?php elseif ($rel['invoice'] && $rel['invoice']['payment_status'] === 'Belum Dibayar' && $paymentProof): ?>
                            <?php render_mark_invoice_paid_form($rel['invoice']); ?>
                        <?php elseif ($booking['status'] === 'Lunas'): ?>
                            <form data-api-form method="post" data-confirm="Selesaikan order ini?"><?= csrf_field() ?><input type="hidden" name="action" value="complete_order"><input type="hidden" name="booking_id" value="<?= $bookingId ?>"><button class="btn btn-success" type="submit"><i class="bi bi-check2-circle me-1"></i>Selesaikan Order</button></form>
                        <?php else: ?><p class="text-muted mb-0">Belum ada tindakan Staff untuk status ini.</p><?php endif; ?>
                    <?php elseif ($role === 'pekerja_lapangan'): ?>
                        <?php if ($booking['status'] === 'Survey Dijadwalkan'): ?><?php render_start_survey_form($booking); ?>
                        <?php elseif ($booking['status'] === 'Survey Berlangsung'): ?><?php render_complete_survey_form($booking); ?>
                        <?php elseif ($booking['status'] === 'Survey Selesai'): ?><?php render_create_estimate_form($booking, $booking['service_id'] ? db_first('SELECT * FROM services WHERE id=?', [$booking['service_id']]) : null); ?>
                        <?php elseif ($booking['status'] === 'Estimasi Disetujui'): ?><?php render_start_work_form($booking); ?>
                        <?php elseif (in_array($booking['status'], ['Dalam Pengerjaan', 'Revisi Estimasi'], true)): ?>
                            <?php render_progress_update_form($booking); ?><hr><?php render_revision_form($booking); ?><hr><?php render_mark_work_done_form($booking); ?>
                        <?php elseif ($booking['status'] === 'Pekerjaan Selesai' && !$rel['invoice']): ?><?php render_create_invoice_form($booking); ?>
                        <?php else: ?><p class="text-muted mb-0">Belum ada tindakan lapangan untuk status ini.</p><?php endif; ?>
                    <?php elseif ($role === 'customer'): ?>
                        <?php if ($rel['estimate'] && $rel['estimate']['status'] === 'Dikirim'): ?>
                            <form data-api-form class="d-flex flex-column gap-3" method="post"><?= csrf_field() ?><input type="hidden" name="estimate_id" value="<?= (int) $rel['estimate']['id'] ?>"><textarea class="form-control" name="customer_response_note" placeholder="Catatan keputusan (opsional)"></textarea><button class="btn btn-primary" name="action" value="approve_estimate">Setujui Estimasi</button><button class="btn btn-outline-danger" name="action" value="reject_estimate">Tolak Estimasi</button></form>
                        <?php elseif ($rel['revision'] && $rel['revision']['status'] === 'Dikirim'): ?>
                            <form data-api-form class="d-flex flex-column gap-3" method="post"><?= csrf_field() ?><input type="hidden" name="revision_id" value="<?= (int) $rel['revision']['id'] ?>"><textarea class="form-control" name="customer_response_note" placeholder="Catatan keputusan (opsional)"></textarea><button class="btn btn-primary" name="action" value="approve_revision">Setujui Revisi</button><button class="btn btn-outline-danger" name="action" value="reject_revision">Tolak Revisi</button></form>
                        <?php elseif ($rel['invoice'] && $rel['invoice']['status'] === 'Dikirim' && $rel['invoice']['payment_status'] === 'Belum Dibayar'): ?><?php render_upload_payment_proof_form((int) $rel['invoice']['id']); ?>
                        <?php elseif ($booking['status'] === 'Selesai' && !$review): ?>
                            <form data-api-form class="d-flex flex-column gap-3" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="submit_review"><input type="hidden" name="booking_id" value="<?= $bookingId ?>"><label>Rating<select class="form-select" name="rating" required><option value="">Pilih rating</option><?php for ($rating = 5; $rating >= 1; $rating--): ?><option value="<?= $rating ?>"><?= $rating ?> bintang</option><?php endfor; ?></select></label><textarea class="form-control" name="comment" placeholder="Ceritakan pengalaman Anda"></textarea><button class="btn btn-primary" type="submit">Kirim Ulasan</button></form>
                        <?php else: ?><p class="text-muted mb-0">Tidak ada tindakan yang perlu Anda lakukan sekarang.</p><?php endif; ?>
                    <?php else: ?><p class="text-muted mb-0">Boss memiliki akses monitoring tanpa tindakan operasional.</p><?php endif; ?>
                </div>
            </article>
        </div>
    <aside class="col-lg-4 detail-rail">
            <div class="card border-0 shadow-sm"><div class="card-body p-4"><p class="eyebrow mb-1">Panduan</p><h3 class="h6">Semua tetap di Order ini</h3><p class="small text-muted mb-0">Setelah aksi berhasil, status, timeline, dan tindakan berikutnya akan diperbarui otomatis.</p></div></div>
        </aside>
    </section>
<?php endif; ?>
