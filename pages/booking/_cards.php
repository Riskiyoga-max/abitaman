<?php
declare(strict_types=1);

function render_booking_empty_state(string $title, string $message): void
{
    ?>
    <div class="card border-0 shadow-sm">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
            <div>
                <p class="small fw-bold text-uppercase text-secondary">Kosong</p>
                <h2><?= h($title) ?></h2>
                <p class="text-secondary mb-0"><?= h($message) ?></p>
            </div>
        </div>
    </div>
    <?php
}

function render_booking_filter_bar(string $page, array $filters, bool $showStatus = true): void
{
    ?>
    <form class="d-flex flex-wrap align-items-center gap-2 mb-3" method="get">
        <input type="hidden" name="page" value="<?= h($page) ?>">
        <input class="form-control form-control-sm" name="q" value="<?= h($filters['q']) ?>" placeholder="Cari kode, customer, lokasi…">
        <?php if ($showStatus): ?>
            <select class="form-select form-select-sm" name="status">
                <option value="">Semua status</option>
                <?php foreach (ORDER_STATUSES as $s): ?>
                    <option value="<?= h($s) ?>" <?= $filters['status'] === $s ? 'selected' : '' ?>><?= h($s) ?></option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>
        <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-search"></i> Filter</button>
        <a class="btn btn-outline-secondary btn-sm" href="dashboard.php?page=<?= h($page) ?>">Reset</a>
    </form>
    <?php
}

function render_booking_list_table(array $rows, string $role, string $actionColCallback = ''): void
{
    ?>
    <div class="table-responsive">
        <table class="table align-middle table table-hover align-middle">
            <thead>
                <tr>
                    <th>Kode</th>
                    <th>Customer</th>
                    <th>Layanan</th>
                    <th>Survey</th>
                    <th>Pekerja Lapangan</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $item): ?>
                <tr>
                    <td><strong><?= h($item['booking_code']) ?></strong></td>
                    <td>
                        <?= h($item['customer_name'] ?? '-') ?>
                        <?php if ($item['customer_phone'] ?? ''): ?>
                            <br><small><?= h($item['customer_phone']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= h($item['service_name'] ?? ($item['is_custom_service'] ? 'Custom Layanan' : '-')) ?>
                    </td>
                    <td><?= h(format_date_id($item['actual_survey_date'] ?: $item['requested_survey_date'])) ?></td>
                    <td><?= h($item['pekerja_lapangan_name'] ?? '-') ?></td>
                    <td>
                        <span class="<?= status_badge_class($item['status']) ?>">
                            <?= h(booking_status_label_for_role($item['status'], $role)) ?>
                        </span>
                    </td>
                    <td>
                        <div class="d-flex flex-wrap gap-2">
                            <a class="btn btn-outline-secondary btn-sm"
                               href="dashboard.php?page=booking_detail&id=<?= (int) $item['id'] ?>">
                                Detail
                            </a>
                            <?php if ($role === 'staff'): ?>
                                <?php if ($item['status'] === 'Menunggu Konfirmasi'): ?>
                                    <a class="btn btn-primary btn-sm"
                                       href="dashboard.php?page=booking_schedule&id=<?= (int) $item['id'] ?>">
                                        Atur Survey
                                    </a>
                                <?php elseif (in_array($item['status'], ['Survey Dijadwalkan', 'Survey Berlangsung'], true)): ?>
                                    <a class="btn btn-outline-secondary btn-sm"
                                       href="dashboard.php?page=booking_schedule&id=<?= (int) $item['id'] ?>">
                                        Reschedule
                                    </a>
                                <?php elseif ($item['status'] === 'Estimasi Disetujui'): ?>
                                    <a class="btn btn-primary btn-sm"
                                       href="dashboard.php?page=booking_work&id=<?= (int) $item['id'] ?>">
                                        Mulai Kerja
                                    </a>
                                <?php elseif ($item['status'] === 'Lunas'): ?>
                                    <a class="btn btn-success btn-sm"
                                       href="dashboard.php?page=booking_detail&id=<?= (int) $item['id'] ?>">
                                        Selesaikan
                                    </a>
                                <?php endif; ?>
                                <?php
                                // inline delete form
                                echo '<form class="d-flex flex-wrap align-items-center gap-2" data-api-form method="post"'
                                    . ' data-confirm="Hapus booking ' . h($item['booking_code']) . '? Data order, jadwal, survey, estimasi, invoice, dan pembayaran terkait akan ikut dihapus.">';
                                echo csrf_field();
                                echo '<input type="hidden" name="action" value="delete_booking">';
                                echo '<input type="hidden" name="confirm_action" value="1">';
                                echo '<input type="hidden" name="booking_id" value="' . (int) $item['id'] . '">';
                                echo '<button class="btn btn-outline-danger btn-sm" type="submit"><i class="bi bi-trash"></i></button>';
                                echo '</form>';
                                ?>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">Belum ada data.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

function render_booking_context_card(array $booking, string $role): void
{
    ?>
    <aside class="card card-body shadow-sm card card-body shadow-sm">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
            <div>
                <p class="small fw-bold text-uppercase text-secondary">Konteks Order</p>
                <h2><?= h($booking['booking_code']) ?></h2>
            </div>
            <span class="<?= status_badge_class($booking['status']) ?>">
                <?= h(booking_status_label_for_role($booking['status'], $role)) ?>
            </span>
        </div>
        <div class="row g-3">
            <div class="col-md-6 border rounded p-3"><span>Customer</span><strong><?= h($booking['customer_name']) ?></strong></div>
            <div class="col-md-6 border rounded p-3"><span>Layanan</span><strong><?= h($booking['service_name'] ?? 'Custom Layanan') ?></strong></div>
            <div class="col-md-6 border rounded p-3"><span>Pekerja Lapangan</span><strong><?= h($booking['pekerja_lapangan_name'] ?? '-') ?></strong></div>
            <div class="col-md-6 border rounded p-3"><span>Lokasi</span><p><?= h($booking['location_address']) ?></p></div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="dashboard.php?page=booking_detail&id=<?= (int) $booking['id'] ?>">
                <i class="bi bi-clipboard-check"></i><span>Ringkasan booking</span>
            </a>
            <a href="dashboard.php?page=booking_schedule&id=<?= (int) $booking['id'] ?>">
                <i class="bi bi-calendar2-week"></i><span>Jadwal survey</span>
            </a>
            <a href="dashboard.php?page=booking_survey&id=<?= (int) $booking['id'] ?>">
                <i class="bi bi-clipboard2-check"></i><span>Hasil survey</span>
            </a>
            <a href="dashboard.php?page=booking_estimate&id=<?= (int) $booking['id'] ?>">
                <i class="bi bi-calculator"></i><span>Estimasi</span>
            </a>
            <a href="dashboard.php?page=booking_work&id=<?= (int) $booking['id'] ?>">
                <i class="bi bi-tools"></i><span>Pekerjaan</span>
            </a>
            <?php if ($role !== 'pekerja_lapangan'): ?>
                <a href="dashboard.php?page=booking_invoice&id=<?= (int) $booking['id'] ?>">
                    <i class="bi bi-receipt"></i><span>Invoice</span>
                </a>
                <a href="dashboard.php?page=booking_payment&id=<?= (int) $booking['id'] ?>">
                    <i class="bi bi-cash-coin"></i><span>Pembayaran</span>
                </a>
            <?php endif; ?>
        </div>
    </aside>
    <?php
}

function render_booking_timeline(int $bookingId, string $role): void
{
    $rows = db_query(
        'SELECT h.*, u.name AS user_name FROM booking_status_histories h
         LEFT JOIN users u ON u.id = h.changed_by
         WHERE h.booking_id = ? ORDER BY h.created_at ASC',
        [$bookingId]
    );
    echo '<div class="list-group list-group-flush">';
    foreach ($rows as $row) {
        echo '<div class="list-group-item list-group-item-success">'
            . '<strong>' . h(booking_status_label_for_role($row['new_status'], $role)) . '</strong>'
            . '<span>' . h(format_datetime($row['created_at'])) . ' · ' . h($row['user_name'] ?? 'System') . '</span>'
            . '<p>' . h(booking_status_note_for_role($row['note'] ?? '', $role)) . '</p>'
            . '</div>';
    }
    if (!$rows) {
        echo '<p class="text-secondary">Belum ada riwayat status.</p>';
    }
    echo '</div>';
}

function render_booking_workflow_stepper(array $booking, string $role): void
{
    $steps = [
        ['key' => 'overview',  'label' => 'Order',     'hint' => 'Data masuk',        'icon' => 'bi-inbox'],
        ['key' => 'schedule',  'label' => 'Survey',    'hint' => 'Jadwal & hasil',    'icon' => 'bi-calendar2-check'],
        ['key' => 'estimate',  'label' => 'Estimasi',  'hint' => 'Review harga',      'icon' => 'bi-calculator'],
        ['key' => 'work',      'label' => 'Pekerjaan', 'hint' => 'Eksekusi lapangan', 'icon' => 'bi-tools'],
        ['key' => 'invoice',   'label' => 'Invoice',   'hint' => 'Tagihan final',     'icon' => 'bi-receipt-cutoff'],
        ['key' => 'done',      'label' => 'Selesai',   'hint' => 'Riwayat final',     'icon' => 'bi-check2-circle'],
    ];

    if ($role === 'pekerja_lapangan') {
        $steps = array_values(array_filter($steps, fn ($s) => $s['key'] !== 'invoice'));
    }

    $pageMap = [
        'overview' => 'booking_detail',
        'schedule' => 'booking_schedule',
        'estimate' => 'booking_estimate',
        'work'     => 'booking_work',
        'invoice'  => 'booking_invoice',
        'done'     => 'booking_detail',
    ];

    $currentStage = booking_workflow_stage_from_status($booking['status']);
    ?>
    <ol class="list-group list-group-horizontal-lg" aria-label="Alur booking">
        <?php foreach ($steps as $index => $step): ?>
            <?php
            $state = $index < $currentStage ? 'list-group-item-success' : ($index === $currentStage ? 'list-group-item-primary' : '');
            $href  = 'dashboard.php?page=' . ($pageMap[$step['key']] ?? 'booking_detail') . '&id=' . (int) $booking['id'];
            ?>
            <li class="list-group-item flex-fill <?= h($state) ?>">
                <a class="d-flex flex-column text-decoration-none" href="<?= h($href) ?>">
                    <i class="bi <?= h($step['icon']) ?>"></i>
                    <span><?= h($step['label']) ?></span>
                    <small><?= h($step['hint']) ?></small>
                </a>
            </li>
        <?php endforeach; ?>
    </ol>
    <?php
}
