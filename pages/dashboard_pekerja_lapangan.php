<?php
require_once __DIR__ . '/booking/_helpers.php';
$workerId = (int) $user['id'];
$todayTasks = db_query(
    "SELECT b.*, c.name customer_name, s.name service_name, sc.schedule_time
     FROM schedules sc JOIN bookings b ON b.id=sc.booking_id JOIN users c ON c.id=b.customer_id
     LEFT JOIN services s ON s.id=b.service_id
     WHERE sc.pekerja_lapangan_id=? AND sc.schedule_date=CURDATE() AND sc.status NOT IN ('Selesai','Dibatalkan')
     ORDER BY sc.schedule_time",
    [$workerId]
);
$surveyCount = (int)(db_first("SELECT COUNT(*) c FROM bookings WHERE assigned_pekerja_lapangan_id=? AND status IN ('Survey Dijadwalkan','Survey Berlangsung','Survey Selesai')",[$workerId])['c']??0);
$workCount = (int)(db_first("SELECT COUNT(*) c FROM bookings WHERE assigned_pekerja_lapangan_id=? AND status IN ('Estimasi Disetujui','Dalam Pengerjaan','Revisi Estimasi','Pekerjaan Selesai')",[$workerId])['c']??0);
$completedThisMonth = (int)(db_first("SELECT COUNT(*) c FROM bookings WHERE assigned_pekerja_lapangan_id=? AND status='Selesai' AND DATE_FORMAT(updated_at,'%Y-%m')=DATE_FORMAT(CURDATE(),'%Y-%m')",[$workerId])['c']??0);
$activeTasks = db_query(
    "SELECT b.*, c.name customer_name, s.name service_name FROM bookings b JOIN users c ON c.id=b.customer_id
     LEFT JOIN services s ON s.id=b.service_id
     WHERE b.assigned_pekerja_lapangan_id=? AND b.status IN ('Survey Dijadwalkan','Survey Berlangsung','Survey Selesai','Estimasi Disetujui','Dalam Pengerjaan','Revisi Estimasi','Pekerjaan Selesai')
     ORDER BY b.updated_at DESC LIMIT 8",[$workerId]
);
?>
<header class="dashboard-hero">
    <div class="page-intro__copy">
        <p class="page-kicker">Lapangan hari ini</p>
        <h2>Halo, <?= h($user['name']) ?></h2>
        <p>Tugas diurutkan berdasarkan jadwal dan tindakan berikutnya.</p>
    </div>
    <a class="btn btn-primary" href="dashboard.php?page=tasks&lane=action">Lihat semua tugas <i class="bi bi-arrow-right ms-1"></i></a>
</header>

<section class="kpi-grid" aria-label="Ringkasan tugas">
    <?php foreach ([['Tugas hari ini', count($todayTasks), 'bi-calendar2-day', 'tasks&lane=action'], ['Antrean survei', $surveyCount, 'bi-clipboard2-check', 'tasks&stage=survey&lane=action'], ['Pekerjaan aktif', $workCount, 'bi-tools', 'tasks&stage=work&lane=action'], ['Selesai bulan ini', $completedThisMonth, 'bi-check2-circle', 'tasks&lane=history']] as [$label, $value, $icon, $target]): ?>
        <a class="kpi-card" href="dashboard.php?page=<?= h($target) ?>">
            <div class="kpi-card__content">
                <div class="kpi-card__top"><span><?= h($label) ?></span><i class="bi <?= h($icon) ?>" aria-hidden="true"></i></div>
                <strong><?= (int) $value ?></strong>
            </div>
        </a>
    <?php endforeach; ?>
</section>

<section class="dashboard-grid dashboard-grid--equal">
    <section class="data-panel">
        <header class="data-panel__header"><div><p class="page-kicker">Jadwal</p><h3>Hari ini</h3></div><span class="status-label"><?= count($todayTasks) ?> tugas</span></header>
        <?php if ($todayTasks): ?>
            <div class="data-list">
                <?php foreach ($todayTasks as $task): ?>
                    <a class="data-list__row data-list__row--compact" href="<?= h(booking_detail_url((int) $task['id'], 'actions')) ?>">
                        <div><span class="data-list__title"><?= h(substr($task['schedule_time'], 0, 5)) ?> · <?= h($task['customer_name']) ?></span><span class="data-list__meta"><?= h($task['service_name'] ?? 'Custom Layanan') ?></span></div>
                        <span class="data-list__action">Buka <i class="bi bi-arrow-right ms-1"></i></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state"><i class="bi bi-calendar2-check"></i><h3>Jadwal hari ini kosong</h3><p class="mb-0">Tugas berikutnya akan muncul setelah dijadwalkan Staff.</p></div>
        <?php endif; ?>
    </section>

    <section class="data-panel">
        <header class="data-panel__header"><div><p class="page-kicker">Antrean aktif</p><h3>Tindakan berikutnya</h3></div><a class="btn btn-link btn-sm text-success text-decoration-none" href="dashboard.php?page=tasks&lane=action">Semua tugas</a></header>
        <?php if ($activeTasks): ?>
            <div class="data-list">
            <?php foreach ($activeTasks as $task): $label = booking_next_action_label($task, $role, null, null, null); ?>
                <a class="data-list__row" href="<?= h(booking_detail_url((int) $task['id'], 'actions')) ?>">
                    <div><span class="data-list__title"><?= h($task['booking_code']) ?> · <?= h($task['customer_name']) ?></span><span class="data-list__meta"><?= h($task['service_name'] ?? 'Custom Layanan') ?></span></div>
                    <span class="status-label"><?= h($label ?? 'Pantau') ?></span><span></span>
                    <span class="data-list__action">Kerjakan <i class="bi bi-arrow-right ms-1"></i></span>
                </a>
            <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state"><i class="bi bi-check2"></i><h3>Tidak ada tugas aktif</h3><p class="mb-0">Semua pekerjaan lapangan sudah tertangani.</p></div>
        <?php endif; ?>
    </section>
</section>
