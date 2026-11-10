<?php
require_once __DIR__ . '/booking/_helpers.php';

$newBookings = (int) (db_first("SELECT COUNT(*) c FROM bookings WHERE status='Menunggu Konfirmasi'")['c'] ?? 0);
$draftReviews = (int) (db_first(
    "SELECT
        (SELECT COUNT(*) FROM estimates WHERE status='Draft') +
        (SELECT COUNT(*) FROM estimate_revisions WHERE status='Draft') +
        (SELECT COUNT(*) FROM final_invoices WHERE status='Draft') c"
)['c'] ?? 0);
$pendingProofs = (int) (db_first("SELECT COUNT(DISTINCT booking_id) c FROM payments WHERE payment_status='Belum Dibayar' AND payment_proof IS NOT NULL")['c'] ?? 0);
$overdueSurveys = (int) (db_first("SELECT COUNT(*) c FROM schedules WHERE schedule_type='Survey' AND schedule_date<CURDATE() AND status NOT IN ('Selesai','Dibatalkan')")['c'] ?? 0);

$queue = db_query(
    "SELECT b.*, c.name customer_name, s.name service_name, w.name worker_name,
            CASE
                WHEN EXISTS (SELECT 1 FROM payments p WHERE p.booking_id=b.id AND p.payment_status='Belum Dibayar' AND p.payment_proof IS NOT NULL) THEN 'Verifikasi pembayaran'
                WHEN b.status='Menunggu Konfirmasi' THEN 'Jadwalkan survei'
                WHEN e.status='Draft' THEN 'Review estimasi'
                WHEN r.status='Draft' THEN 'Review revisi'
                WHEN i.status='Draft' THEN 'Review invoice'
                WHEN b.status='Lunas' THEN 'Selesaikan order'
                ELSE 'Pantau order'
            END next_action
     FROM bookings b
     JOIN users c ON c.id=b.customer_id
     LEFT JOIN users w ON w.id=b.assigned_pekerja_lapangan_id
     LEFT JOIN services s ON s.id=b.service_id
     LEFT JOIN estimates e ON e.id=(SELECT id FROM estimates WHERE booking_id=b.id ORDER BY id DESC LIMIT 1)
     LEFT JOIN estimate_revisions r ON r.id=(SELECT id FROM estimate_revisions WHERE booking_id=b.id ORDER BY id DESC LIMIT 1)
     LEFT JOIN final_invoices i ON i.id=(SELECT id FROM final_invoices WHERE booking_id=b.id ORDER BY id DESC LIMIT 1)
     WHERE b.status IN ('Menunggu Konfirmasi','Lunas') OR e.status='Draft' OR r.status='Draft' OR i.status='Draft'
        OR EXISTS (SELECT 1 FROM payments p WHERE p.booking_id=b.id AND p.payment_status='Belum Dibayar' AND p.payment_proof IS NOT NULL)
     ORDER BY b.updated_at DESC LIMIT 8"
);
$todaySchedules = db_query(
    "SELECT sc.schedule_time, sc.schedule_type, sc.status, b.id, b.booking_code,
            c.name customer_name, w.name worker_name
     FROM schedules sc
     JOIN bookings b ON b.id=sc.booking_id
     JOIN users c ON c.id=b.customer_id
     LEFT JOIN users w ON w.id=sc.pekerja_lapangan_id
     WHERE sc.schedule_date=CURDATE() AND sc.status NOT IN ('Selesai','Dibatalkan')
     ORDER BY sc.schedule_time LIMIT 6"
);
?>

<header class="dashboard-hero">
    <div class="page-intro__copy">
        <p class="page-kicker">Operasional hari ini</p>
        <h2>Selamat bekerja, <?= h($user['name']) ?></h2>
        <p>Mulai dari antrean yang paling membutuhkan keputusan.</p>
    </div>
    <a class="btn btn-primary" href="dashboard.php?page=order&lane=action">Buka antrean <i class="bi bi-arrow-right ms-1"></i></a>
</header>

<section class="kpi-grid" aria-label="Ringkasan operasional">
    <?php foreach ([
        ['Booking baru', $newBookings, 'bi-inbox', 'order&lane=action'],
        ['Perlu review', $draftReviews, 'bi-clipboard-check', 'order&lane=action'],
        ['Bukti bayar', $pendingProofs, 'bi-cash-coin', 'order&lane=action'],
        ['Survei terlambat', $overdueSurveys, 'bi-exclamation-triangle', 'order&stage=survey&lane=active'],
    ] as [$label, $value, $icon, $target]): ?>
        <a class="kpi-card" href="dashboard.php?page=<?= h($target) ?>">
            <div class="kpi-card__content">
                <div class="kpi-card__top"><span><?= h($label) ?></span><i class="bi <?= h($icon) ?>" aria-hidden="true"></i></div>
                <strong><?= (int) $value ?></strong>
            </div>
        </a>
    <?php endforeach; ?>
</section>

<section class="dashboard-grid">
    <section class="data-panel">
        <header class="data-panel__header"><div><p class="page-kicker">Prioritas</p><h3>Perlu tindakan saya</h3></div><a class="btn btn-link btn-sm text-success text-decoration-none" href="dashboard.php?page=order&lane=action">Semua antrean <i class="bi bi-arrow-right"></i></a></header>
        <?php if ($queue): ?>
        <div class="data-list">
            <?php foreach ($queue as $item): ?>
                <a class="data-list__row" href="<?= h(booking_detail_url((int) $item['id'], 'actions')) ?>">
                    <div><span class="data-list__title"><?= h($item['booking_code']) ?> · <?= h($item['customer_name']) ?></span><span class="data-list__meta"><?= h($item['service_name'] ?? 'Custom Layanan') ?></span></div>
                    <span class="data-list__meta"><?= h($item['worker_name'] ?? 'Belum ada pekerja') ?></span>
                    <span class="status-label"><?= h($item['next_action']) ?></span>
                    <span class="data-list__action">Kerjakan <i class="bi bi-arrow-right ms-1"></i></span>
                </a>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
            <div class="empty-state"><i class="bi bi-check2"></i><h3>Antrean prioritas selesai</h3><p class="mb-0">Tidak ada keputusan operasional yang tertunda.</p></div>
        <?php endif; ?>
    </section>

    <div class="dashboard-stack">
        <section class="dashboard-card">
            <header class="panel-heading"><div><p class="page-kicker">Jadwal</p><h3>Hari ini</h3></div><span class="status-label"><?= count($todaySchedules) ?> agenda</span></header>
            <?php if ($todaySchedules): ?>
                <div class="quick-action-list">
                    <?php foreach ($todaySchedules as $schedule): ?>
                        <a class="quick-action" href="<?= h(booking_detail_url((int) $schedule['id'], 'actions')) ?>">
                            <div><strong><?= h(substr((string) $schedule['schedule_time'], 0, 5)) ?> · <?= h($schedule['customer_name']) ?></strong><span><?= h($schedule['schedule_type']) ?> · <?= h($schedule['worker_name'] ?? 'Belum ada pekerja') ?></span></div>
                            <i class="bi bi-arrow-right"></i>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="small text-muted mb-0">Tidak ada survei atau pekerjaan yang dijadwalkan hari ini.</p>
            <?php endif; ?>
        </section>
        <section class="dashboard-card">
            <header class="panel-heading"><div><p class="page-kicker">Fokus kerja</p><h3>Urutan prioritas</h3></div></header>
            <p class="small text-muted mb-0">Tangani booking baru, review dokumen, verifikasi pembayaran, lalu pantau jadwal yang melewati batas.</p>
        </section>
    </div>
</section>
