<?php
require_once __DIR__ . '/booking/_helpers.php';
$month = date('Y-m');
$revenue = (int)(db_first("SELECT COALESCE(SUM(amount),0) total FROM payments WHERE payment_status='Lunas' AND payment_type='Invoice Final' AND DATE_FORMAT(COALESCE(paid_at,created_at),'%Y-%m')=?",[$month])['total']??0);
$active = (int)(db_first("SELECT COUNT(*) c FROM bookings WHERE status NOT IN ('Dibatalkan','Selesai')")['c']??0);
$pendingPayments = (int)(db_first("SELECT COUNT(*) c FROM final_invoices WHERE status='Dikirim' AND payment_status='Belum Dibayar'")['c']??0);
$late = (int)(db_first(
    "SELECT COUNT(DISTINCT b.id) c FROM bookings b
     LEFT JOIN schedules sc ON sc.booking_id=b.id AND sc.schedule_type='Survey'
     LEFT JOIN estimates e ON e.id=(SELECT id FROM estimates WHERE booking_id=b.id ORDER BY id DESC LIMIT 1)
     LEFT JOIN booking_status_histories h ON h.id=(SELECT id FROM booking_status_histories WHERE booking_id=b.id AND new_status='Dalam Pengerjaan' ORDER BY id DESC LIMIT 1)
     WHERE (sc.schedule_date<CURDATE() AND sc.status NOT IN ('Selesai','Dibatalkan'))
        OR (b.status IN ('Dalam Pengerjaan','Revisi Estimasi') AND h.created_at IS NOT NULL AND DATE_ADD(DATE(h.created_at),INTERVAL COALESCE(e.estimated_days,1) DAY)<CURDATE())"
)['c']??0);
$stageRows=db_query("SELECT status,COUNT(*) total FROM bookings WHERE status NOT IN ('Dibatalkan','Selesai') GROUP BY status ORDER BY total DESC LIMIT 7");
$recentPayments=db_query("SELECT p.*,b.booking_code,c.name customer_name FROM payments p JOIN bookings b ON b.id=p.booking_id JOIN users c ON c.id=b.customer_id WHERE p.payment_status='Lunas' ORDER BY COALESCE(p.paid_at,p.created_at) DESC LIMIT 6");
$attentionOrders=db_query(
    "SELECT DISTINCT b.id,b.booking_code,b.status,c.name customer_name
     FROM bookings b
     JOIN users c ON c.id=b.customer_id
     LEFT JOIN schedules sc ON sc.booking_id=b.id AND sc.schedule_type='Survey'
     LEFT JOIN estimates e ON e.id=(SELECT id FROM estimates WHERE booking_id=b.id ORDER BY id DESC LIMIT 1)
     LEFT JOIN booking_status_histories h ON h.id=(SELECT id FROM booking_status_histories WHERE booking_id=b.id AND new_status='Dalam Pengerjaan' ORDER BY id DESC LIMIT 1)
     WHERE (sc.schedule_date<CURDATE() AND sc.status NOT IN ('Selesai','Dibatalkan'))
        OR (b.status IN ('Dalam Pengerjaan','Revisi Estimasi') AND h.created_at IS NOT NULL AND DATE_ADD(DATE(h.created_at),INTERVAL COALESCE(e.estimated_days,1) DAY)<CURDATE())
     ORDER BY b.updated_at ASC LIMIT 5"
);
?>
<header class="dashboard-hero">
    <div class="page-intro__copy">
        <p class="page-kicker">Ringkasan bisnis</p>
        <h2>Dashboard Boss</h2>
        <p>Sinyal utama untuk melihat kesehatan operasional dan arus pembayaran ABI Taman.</p>
    </div>
    <a class="btn btn-primary" href="dashboard.php?page=reports">Buka laporan <i class="bi bi-arrow-right ms-1"></i></a>
</header>

<section class="kpi-grid" aria-label="Ringkasan bisnis">
<?php foreach([['Pendapatan bulan ini',format_rupiah($revenue),'bi-cash-stack','reports'],['Order aktif',$active,'bi-bag','monitoring'],['Pekerjaan terlambat',$late,'bi-exclamation-triangle','monitoring'],['Pembayaran tertunda',$pendingPayments,'bi-receipt','monitoring&stage=completion']] as [$label,$value,$icon,$target]): ?><a class="kpi-card" href="dashboard.php?page=<?= h($target) ?>"><div class="kpi-card__content"><div class="kpi-card__top"><span><?= h($label) ?></span><i class="bi <?= h($icon) ?>" aria-hidden="true"></i></div><strong><?= h($value) ?></strong></div></a><?php endforeach; ?>
</section>

<section class="dashboard-grid">
    <section class="dashboard-card">
        <header class="panel-heading"><div><p class="page-kicker">Operasional</p><h3>Komposisi order aktif</h3></div><a class="btn btn-link btn-sm text-success text-decoration-none" href="dashboard.php?page=monitoring">Monitoring</a></header>
        <?php $max=max(array_column($stageRows,'total')?:[1]); foreach($stageRows as $row): ?>
            <div class="mb-3"><div class="d-flex justify-content-between small mb-2"><span><?= h(booking_workflow_stage_label($row['status'])) ?> · <?= h($row['status']) ?></span><strong><?= (int)$row['total'] ?></strong></div><div class="progress"><div class="progress-bar" style="width:<?= chart_percent($row['total'],$max,3) ?>%"></div></div></div>
        <?php endforeach; ?>
        <?php if(!$stageRows): ?><div class="empty-state"><i class="bi bi-bar-chart"></i><h3>Belum ada order aktif</h3></div><?php endif; ?>
    </section>

    <div class="dashboard-stack">
        <section class="dashboard-card">
            <header class="panel-heading"><div><p class="page-kicker">Perlu perhatian</p><h3>Order terlambat</h3></div><span class="status-label"><?= (int)$late ?> order</span></header>
            <?php if ($attentionOrders): ?><div class="quick-action-list"><?php foreach ($attentionOrders as $item): ?><a class="quick-action" href="<?= h(booking_detail_url((int)$item['id'])) ?>"><div><strong><?= h($item['booking_code']) ?> · <?= h($item['customer_name']) ?></strong><span><?= h($item['status']) ?></span></div><i class="bi bi-arrow-right"></i></a><?php endforeach; ?></div><?php else: ?><p class="small text-muted mb-0">Tidak ada pekerjaan yang melewati jadwal.</p><?php endif; ?>
        </section>
        <section class="dashboard-card">
            <header class="panel-heading"><div><p class="page-kicker">Kas masuk</p><h3>Pembayaran terbaru</h3></div></header>
            <?php if ($recentPayments): ?><div class="quick-action-list"><?php foreach($recentPayments as $payment): ?><div class="quick-action"><div><strong><?= h($payment['booking_code']) ?></strong><span><?= h($payment['customer_name']) ?></span></div><strong class="text-success"><?= format_rupiah($payment['amount']) ?></strong></div><?php endforeach; ?></div><?php else: ?><p class="small text-muted mb-0">Belum ada pembayaran lunas.</p><?php endif; ?>
        </section>
    </div>
</section>
