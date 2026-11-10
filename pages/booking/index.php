<?php
declare(strict_types=1);

require_once __DIR__ . '/../../data/session_helper.php';
require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/_queries.php';

require_roles(['customer', 'staff', 'pekerja_lapangan', 'boss']);

$filters = booking_filters_from_request();
$paged = booking_list_for_role($user, $role, $filters);
$rows = $paged['rows'];
$canonicalPage = booking_list_page_for_role($role);
$services = db_query("SELECT id, name FROM services WHERE status='active' ORDER BY name");
$workers = in_array($role, ['staff', 'boss'], true)
    ? db_query("SELECT id, name FROM users WHERE role='pekerja_lapangan' AND status='active' ORDER BY name")
    : [];
$title = match ($role) {
    'customer' => 'Order Saya',
    'pekerja_lapangan' => 'Tugas Saya',
    'boss' => 'Monitoring',
    default => 'Order',
};
$subtitle = match ($role) {
    'customer' => 'Lihat posisi order, tindakan, progres, dan tagihan dalam satu tempat.',
    'pekerja_lapangan' => 'Kerjakan survei, estimasi, dan progres dari antrean yang sama.',
    'boss' => 'Pantau seluruh perjalanan order tanpa berpindah modul.',
    default => 'Selesaikan pekerjaan berdasarkan prioritas tindakan berikutnya.',
};
$laneLabels = [
    'action' => $role === 'boss' ? 'Perlu Perhatian' : 'Perlu Tindakan Saya',
    'active' => 'Semua Aktif',
    'history' => 'Riwayat',
];
$stageOptions = [
    'booking' => 'Booking',
    'survey' => 'Survei',
    'approval' => 'Persetujuan',
    'work' => 'Pengerjaan',
    'completion' => 'Penyelesaian',
];

$scopeWhere = match ($role) {
    'customer' => ['customer_id=?', [$user['id']]],
    'pekerja_lapangan' => ['assigned_pekerja_lapangan_id=?', [$user['id']]],
    default => ['1=1', []],
};
[$scopeSql, $scopeParams] = $scopeWhere;
$activeCount = (int) (db_first("SELECT COUNT(*) c FROM bookings WHERE {$scopeSql} AND status NOT IN ('Dibatalkan','Selesai')", $scopeParams)['c'] ?? 0);
$doneCount = (int) (db_first("SELECT COUNT(*) c FROM bookings WHERE {$scopeSql} AND status='Selesai'", $scopeParams)['c'] ?? 0);
?>

<header class="page-intro">
    <div class="page-intro__copy">
        <p class="page-kicker">ABI Taman · Alur terpadu</p>
        <h2><?= h($title) ?></h2>
        <p><?= h($subtitle) ?></p>
    </div>
    <?php if ($role === 'customer'): ?>
        <a class="btn btn-primary" href="catalog.php">Buat booking <i class="bi bi-arrow-right ms-1"></i></a>
    <?php endif; ?>
</header>

<section class="summary-rail" style="--metric-columns: 2" aria-label="Ringkasan order">
    <div class="summary-rail__item"><i class="bi bi-bag" aria-hidden="true"></i><div><strong><?= $activeCount ?></strong><span>Order aktif</span></div></div>
    <div class="summary-rail__item"><i class="bi bi-check2" aria-hidden="true"></i><div><strong><?= $doneCount ?></strong><span>Order selesai</span></div></div>
</section>

<nav class="nav nav-pills order-tabs mb-3" aria-label="Antrean order">
    <?php foreach ($laneLabels as $lane => $label): ?>
        <?php $laneQuery = array_merge($_GET, ['page' => $canonicalPage, 'lane' => $lane, 'p' => 1]); ?>
        <a class="nav-link <?= $filters['lane'] === $lane ? 'active' : '' ?>" href="dashboard.php?<?= h(http_build_query($laneQuery)) ?>">
            <?= h($label) ?>
        </a>
    <?php endforeach; ?>
</nav>

<?php if ($role === 'boss'): ?>
    <nav class="nav order-tabs mb-3" aria-label="Jenis monitoring">
        <?php foreach ([
            ['Semua Order', '', 'bi-bag'],
            ['Pekerjaan', 'work', 'bi-tools'],
            ['Estimasi', 'approval', 'bi-calculator'],
            ['Invoice & Bayar', 'completion', 'bi-receipt'],
        ] as [$label, $monitorStage, $icon]): ?>
            <?php $monitorQuery = ['page' => 'monitoring', 'lane' => 'active']; if ($monitorStage !== '') $monitorQuery['stage'] = $monitorStage; ?>
            <a class="nav-link <?= $filters['stage'] === $monitorStage ? 'active' : '' ?>" href="dashboard.php?<?= h(http_build_query($monitorQuery)) ?>"><i class="bi <?= h($icon) ?> me-1"></i><?= h($label) ?></a>
        <?php endforeach; ?>
        <a class="nav-link" href="dashboard.php?page=monitoring_pekerja_lapangan"><i class="bi bi-people me-1"></i>Pekerja</a>
    </nav>
<?php endif; ?>

<section class="filter-toolbar">
        <form class="row g-2 align-items-end" method="get">
            <input type="hidden" name="page" value="<?= h($canonicalPage) ?>">
            <input type="hidden" name="lane" value="<?= h($filters['lane']) ?>">
            <label class="col-lg-4">
                <span class="form-label small fw-semibold">Cari order</span>
                <input class="form-control form-control-sm" name="q" value="<?= h($filters['q']) ?>" placeholder="Kode, customer, atau lokasi">
            </label>
            <label class="col-6 col-lg-2">
                <span class="form-label small fw-semibold">Tahap</span>
                <select class="form-select form-select-sm" name="stage">
                    <option value="">Semua tahap</option>
                    <?php foreach ($stageOptions as $value => $label): ?>
                        <option value="<?= h($value) ?>" <?= $filters['stage'] === $value ? 'selected' : '' ?>><?= h($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="col-6 col-lg-2">
                <span class="form-label small fw-semibold">Layanan</span>
                <select class="form-select form-select-sm" name="service">
                    <option value="0">Semua layanan</option>
                    <?php foreach ($services as $service): ?>
                        <option value="<?= (int) $service['id'] ?>" <?= (int) $filters['service'] === (int) $service['id'] ? 'selected' : '' ?>><?= h($service['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <?php if ($workers): ?>
                <label class="col-6 col-lg-2">
                    <span class="form-label small fw-semibold">Pekerja</span>
                    <select class="form-select form-select-sm" name="worker">
                        <option value="0">Semua pekerja</option>
                        <?php foreach ($workers as $worker): ?>
                            <option value="<?= (int) $worker['id'] ?>" <?= (int) $filters['worker'] === (int) $worker['id'] ? 'selected' : '' ?>><?= h($worker['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            <?php endif; ?>
            <div class="col-6 col-lg d-flex gap-2">
                <button class="btn btn-primary btn-sm flex-fill" type="submit">Terapkan filter</button>
                <a class="btn btn-outline-secondary btn-sm" href="dashboard.php?page=<?= h($canonicalPage) ?>&lane=<?= h($filters['lane']) ?>">Reset</a>
            </div>
        </form>
</section>

<?php if (!$rows): ?>
    <section class="empty-state"><i class="bi bi-check2"></i><h3>Antrean ini sudah bersih</h3><p class="mb-0">Tidak ada order yang sesuai dengan tab dan filter saat ini.</p></section>
<?php else: ?>
    <section class="data-list" aria-label="Daftar order">
        <?php foreach ($rows as $item): ?>
            <?php
            $estimate = $item['estimate_id'] ? ['id' => $item['estimate_id'], 'status' => $item['estimate_status']] : null;
            $revision = $item['revision_id'] ? ['id' => $item['revision_id'], 'status' => $item['revision_status']] : null;
            $invoice = $item['invoice_id'] ? ['id' => $item['invoice_id'], 'status' => $item['invoice_status'], 'payment_status' => $item['invoice_payment_status']] : null;
            $nextAction = booking_next_action_label($item, $role, $estimate, $revision, $invoice, (bool) $item['has_payment_proof'], (bool) $item['review_id']);
            ?>
            <a class="data-list__row" href="<?= h(booking_detail_url((int) $item['id'], $nextAction ? 'actions' : 'overview')) ?>">
                <div>
                    <span class="data-list__title"><?= h($item['booking_code']) ?> · <?= h($item['customer_name']) ?></span>
                    <span class="data-list__meta"><?= h($item['service_name'] ?? 'Custom Layanan') ?> · <?= h(mb_strimwidth($item['location_address'], 0, 48, '…')) ?></span>
                </div>
                <div><span class="status-label"><?= h(booking_workflow_stage_label($item['status'])) ?></span><span class="data-list__meta"><?= h(booking_status_label_for_role($item['status'], $role)) ?></span></div>
                <div><span class="data-list__meta"><?= h(format_date_id($item['actual_survey_date'] ?: $item['requested_survey_date'])) ?></span><?php if ($item['pekerja_lapangan_name']): ?><span class="data-list__meta"><?= h($item['pekerja_lapangan_name']) ?></span><?php endif; ?></div>
                <span class="data-list__action"><?= h($nextAction ?: 'Buka order') ?> <i class="bi bi-arrow-right ms-1"></i></span>
            </a>
        <?php endforeach; ?>
    </section>
    <?php if ($paged['totalPages'] > 1): ?>
        <div class="mt-4"><?php render_pagination($paged['page'], $paged['totalPages'], $paged['total'], $paged['perPage']); ?></div>
    <?php endif; ?>
<?php endif; ?>
