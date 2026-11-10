<?php
require_once __DIR__ . '/booking/_helpers.php';
$customerId = (int) $user['id'];
$activeCount = (int) (db_first("SELECT COUNT(*) c FROM bookings WHERE customer_id=? AND status NOT IN ('Dibatalkan','Selesai')", [$customerId])['c'] ?? 0);
$decisionCount = (int) (db_first("SELECT COUNT(*) c FROM estimates e JOIN bookings b ON b.id=e.booking_id WHERE b.customer_id=? AND e.status='Dikirim'", [$customerId])['c'] ?? 0);
$unpaidCount = (int) (db_first("SELECT COUNT(*) c FROM final_invoices i JOIN bookings b ON b.id=i.booking_id WHERE b.customer_id=? AND i.status='Dikirim' AND i.payment_status='Belum Dibayar'", [$customerId])['c'] ?? 0);
$completedCount = (int) (db_first("SELECT COUNT(*) c FROM bookings WHERE customer_id=? AND status='Selesai'", [$customerId])['c'] ?? 0);
$latest = db_query(
    "SELECT b.*, s.name service_name, COALESCE(MAX(wp.progress_percent),0) progress_percent
     FROM bookings b LEFT JOIN services s ON s.id=b.service_id LEFT JOIN work_progress wp ON wp.booking_id=b.id
     WHERE b.customer_id=? GROUP BY b.id ORDER BY b.updated_at DESC LIMIT 5",
    [$customerId]
);
?>
<header class="dashboard-hero">
    <div class="page-intro__copy">
        <p class="page-kicker">Ruang Customer</p>
        <h2>Halo, <?= h($user['name']) ?></h2>
        <p>Pantau kebutuhan taman, keputusan, progres, dan tagihan dari satu tempat.</p>
    </div>
    <a class="btn btn-primary" href="catalog.php">Buat booking <i class="bi bi-arrow-right ms-1"></i></a>
</header>

<section class="kpi-grid" aria-label="Ringkasan order">
    <?php foreach ([['Order aktif',$activeCount,'active','bi-bag'],['Menunggu keputusan',$decisionCount,'action','bi-check2-square'],['Tagihan',$unpaidCount,'action','bi-receipt'],['Order selesai',$completedCount,'history','bi-check2-circle']] as [$label,$value,$lane,$icon]): ?>
        <a class="kpi-card" href="dashboard.php?page=order&lane=<?= h($lane) ?>">
            <div class="kpi-card__content">
                <div class="kpi-card__top"><span><?= h($label) ?></span><i class="bi <?= h($icon) ?>" aria-hidden="true"></i></div>
                <strong><?= (int) $value ?></strong>
            </div>
        </a>
    <?php endforeach; ?>
</section>

<section class="dashboard-grid">
    <section class="data-panel">
        <header class="data-panel__header">
            <div><p class="page-kicker">Perjalanan Anda</p><h3>Order terbaru</h3></div>
            <a class="btn btn-link btn-sm text-success text-decoration-none" href="dashboard.php?page=order">Lihat semua <i class="bi bi-arrow-right ms-1"></i></a>
        </header>
        <?php if ($latest): ?>
            <div class="data-list">
                <?php foreach ($latest as $item): ?>
                    <a class="data-list__row" href="<?= h(booking_detail_url((int) $item['id'])) ?>">
                        <div><span class="data-list__title"><?= h($item['booking_code']) ?></span><span class="data-list__meta"><?= h($item['service_name'] ?? 'Custom Layanan') ?></span></div>
                        <span class="data-list__meta"><?= h(booking_workflow_stage_label($item['status'])) ?></span>
                        <span class="data-list__meta"><?= (int) $item['progress_percent'] ?>% progres</span>
                        <span class="data-list__action">Buka <i class="bi bi-arrow-right ms-1"></i></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state"><i class="bi bi-bag"></i><h3>Belum ada order</h3><p class="mb-0">Mulai dari katalog layanan untuk membuat booking pertama.</p></div>
        <?php endif; ?>
    </section>

    <div class="dashboard-stack">
        <section class="dashboard-card">
            <header class="panel-heading"><div><p class="page-kicker">Perlu Anda cek</p><h3>Tindakan berikutnya</h3></div></header>
            <div class="quick-action-list">
                <a class="quick-action" href="dashboard.php?page=order&lane=action"><div><strong>Persetujuan estimasi</strong><span><?= (int) $decisionCount ?> menunggu keputusan</span></div><i class="bi bi-arrow-right"></i></a>
                <a class="quick-action" href="dashboard.php?page=order&lane=action"><div><strong>Pembayaran</strong><span><?= (int) $unpaidCount ?> tagihan perlu ditindaklanjuti</span></div><i class="bi bi-arrow-right"></i></a>
                <a class="quick-action" href="catalog.php"><div><strong>Layanan baru</strong><span>Lihat katalog ABI Taman</span></div><i class="bi bi-arrow-right"></i></a>
            </div>
        </section>
        <section class="dashboard-card">
            <header class="panel-heading"><div><p class="page-kicker">Alur sederhana</p><h3>Posisi order</h3></div></header>
            <p class="small text-muted mb-0">Booking → Survei → Persetujuan → Pengerjaan → Selesai. Detail teknis tetap tersedia saat order dibuka.</p>
        </section>
    </div>
</section>
