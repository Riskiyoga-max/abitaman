<?php
// Helper untuk render card list booking yang konsisten di semua halaman
function render_booking_card_list(array $rows, string $role, string $page, callable $ctaFn): void
{
    if (!$rows) {
        echo '<div class="card-body"><div class="card card-body text-center text-secondary py-5"><i class="bi bi-inbox"></i><h3>Tidak ada data</h3><p>Belum ada booking yang sesuai filter.</p></div></div>';
        return;
    }
    echo '<div class="card-body p-3"><div class="row g-3">';
    foreach ($rows as $item) {
        $stage = booking_workflow_stage_from_status($item['status']);
        [$ctaLabel, $ctaUrl, $ctaClass] = $ctaFn($item);
        ?>
        <div class="col-12 col-md-6 col-xl-4">
            <div class="card h-100 border shadow-sm">
                <div class="card-body d-flex flex-column gap-2">
                    <div class="d-flex align-items-start justify-content-between gap-2">
                        <div class="overflow-hidden">
                            <p class="mb-0 fw-bold text-success small text-uppercase"><?= h($item['booking_code']) ?></p>
                            <p class="mb-0 fw-bold text-truncate small"><?= h($item['customer_name'] ?? '-') ?></p>
                            <p class="mb-0 text-muted small"><?= h($item['service_name'] ?? '-') ?></p>
                        </div>
                        <span class="<?= status_badge_class($item['status']) ?> flex-shrink-0">
                            <?= h(booking_status_label_for_role($item['status'], $role)) ?>
                        </span>
                    </div>
                    <div class="d-flex flex-column gap-1 text-muted small">
                        <?php if ($item['location_address'] ?? ''): ?>
                            <span><i class="bi bi-geo-alt me-1"></i><?= h(mb_strimwidth($item['location_address'], 0, 40, '…')) ?></span>
                        <?php endif; ?>
                        <span><i class="bi bi-calendar3 me-1"></i><?= h(format_date_id($item['actual_survey_date'] ?: $item['requested_survey_date'])) ?></span>
                        <?php if ($item['pekerja_lapangan_name'] ?? ''): ?>
                            <span><i class="bi bi-person-check me-1"></i><?= h($item['pekerja_lapangan_name']) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="d-flex gap-2 mt-auto pt-1 flex-wrap">
                        <a class="btn <?= h($ctaClass) ?> btn-sm w-100" href="<?= h($ctaUrl) ?>"><?= h($ctaLabel) ?></a>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    echo '</div></div>';
}

function render_booking_page_header(string $eyebrow, string $title, string $subtitle): void
{
    echo '<div class="mb-4"><p class="small fw-bold text-uppercase text-secondary mb-1">' . h($eyebrow) . '</p>'
       . '<h2 class="h4 fw-bold mb-1">' . h($title) . '</h2>'
       . '<p class="text-muted small mb-0">' . h($subtitle) . '</p></div>';
}

function render_booking_filter(string $page, array $filters, bool $showStatus = false): void
{
    ?>
    <div class="card-header bg-light border-bottom py-3">
        <form class="d-flex flex-wrap gap-2 align-items-center" method="get">
            <input type="hidden" name="page" value="<?= h($page) ?>">
            <input class="form-control form-control-sm flex-grow-1" name="q" value="<?= h($filters['q']) ?>"
                   placeholder="Cari kode, customer…">
            <?php if ($showStatus): ?>
                <select class="form-select form-select-sm w-auto" name="status">
                    <option value="">Semua status</option>
                    <?php foreach (ORDER_STATUSES as $s): ?>
                        <option value="<?= h($s) ?>" <?= $filters['status'] === $s ? 'selected' : '' ?>><?= h($s) ?></option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
            <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-search me-1"></i>Cari</button>
            <a class="btn btn-outline-secondary btn-sm" href="dashboard.php?page=<?= h($page) ?>">Reset</a>
        </form>
    </div>
    <?php
}

function render_booking_detail_header(array $booking, string $role, string $eyebrow, string $backPage): void
{
    ?>
    <a class="btn btn-outline-secondary btn-sm mb-3" href="dashboard.php?page=<?= h($backPage) ?>">
        <i class="bi bi-arrow-left"></i> Kembali
    </a>
    <div class="d-flex align-items-start justify-content-between gap-3 mb-4 mt-2">
        <div>
            <p class="small fw-bold text-uppercase text-secondary mb-1"><?= h($eyebrow) ?></p>
            <h2 class="h4 fw-bold mb-1"><?= h($booking['booking_code']) ?></h2>
            <p class="text-muted small mb-0"><?= h($booking['customer_name']) ?> · <?= h($booking['service_name'] ?? 'Custom Layanan') ?></p>
            <p class="text-muted small mb-0"><i class="bi bi-geo-alt me-1"></i><?= h($booking['location_address']) ?></p>
        </div>
        <span class="<?= status_badge_class($booking['status']) ?> flex-shrink-0 mt-1">
            <?= h(booking_status_label_for_role($booking['status'], $role)) ?>
        </span>
    </div>
    <?php
}
