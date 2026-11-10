<?php
declare(strict_types=1);
require_once __DIR__ . '/../../data/session_helper.php';
require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/_queries.php';
require_once __DIR__ . '/_forms.php';
require_once __DIR__ . '/_list_helpers.php';
require_roles(['customer', 'staff', 'pekerja_lapangan', 'boss']);
$bookingId = (int) ($_GET['id'] ?? 0);
if ($bookingId > 0) {
    $booking        = booking_for_access($bookingId, $user);
    if (!$booking) { echo '<div class="alert alert-warning">Booking tidak ditemukan.</div>'; return; }
    $progress       = db_query('SELECT wp.*, u.name AS worker_name FROM work_progress wp JOIN users u ON u.id = wp.pekerja_lapangan_id WHERE wp.booking_id = ? ORDER BY wp.created_at DESC', [$bookingId]);
    $progressPhotos = db_query('SELECT * FROM survey_photos WHERE booking_id = ? AND photo_type = ? ORDER BY created_at DESC', [$bookingId, 'progress']);
    $afterPhotos    = db_query('SELECT * FROM survey_photos WHERE booking_id = ? AND photo_type = ? ORDER BY created_at DESC', [$bookingId, 'after']);
    $revision       = db_first('SELECT * FROM estimate_revisions WHERE booking_id = ? ORDER BY id DESC LIMIT 1', [$bookingId]);
    $invoice        = latest_booking_invoice($bookingId);
    $latestProgress = $progress[0] ?? null;
    $progressPct    = $latestProgress ? (int) $latestProgress['progress_percent'] : 0;
    render_booking_detail_header($booking, $role, 'Pekerjaan', 'booking_work');
    if ($progress) {
        echo '<div class="alert alert-success shadow-sm mb-3">'
           . '<div class="d-flex justify-content-between align-items-center mb-2"><span class="fw-bold small text-success">Progress Pekerjaan</span><span class="fw-bold h5 mb-0 text-success">' . $progressPct . '%</span></div>'
           . '<div class="progress"><div class="progress-bar bg-success" style="width:' . $progressPct . '%"></div></div>'
           . ($latestProgress ? '<p class="mb-0 mt-2 small text-success">Update terakhir: ' . h($latestProgress['title']) . ' · ' . h(format_datetime($latestProgress['created_at'])) . '</p>' : '')
           . '</div>';
    }
    if ($role === 'pekerja_lapangan') {
        if ($booking['status'] === 'Estimasi Disetujui') {
            echo '<div class="card border-0 shadow-sm mb-3"><div class="card-header bg-white fw-semibold">Mulai Pekerjaan</div><div class="card-body"><p class="text-muted small mb-3">Estimasi sudah disetujui customer.</p>';
            render_start_work_form($booking); echo '</div></div>';
        } elseif (in_array($booking['status'], ['Dalam Pengerjaan','Revisi Estimasi'], true)) {
            echo '<div class="card border-0 shadow-sm mb-3"><div class="card-header bg-white fw-semibold">Update Progress</div><div class="card-body">';
            render_progress_update_form($booking); echo '</div></div>';
            echo '<div class="card border-0 shadow-sm mb-3"><div class="card-header bg-white fw-semibold">Revisi Estimasi</div><div class="card-body"><p class="text-muted small mb-3">Jika ada perubahan biaya di lapangan.</p>';
            render_revision_form($booking); echo '</div></div>';
            echo '<div class="card border-0 shadow-sm mb-3"><div class="card-header bg-white fw-semibold">Tandai Selesai</div><div class="card-body"><p class="text-muted small mb-3">Gunakan setelah semua pekerjaan lapangan selesai.</p>';
            render_mark_work_done_form($booking); echo '</div></div>';
        } elseif ($booking['status'] === 'Pekerjaan Selesai' && !$invoice) {
            echo '<div class="card border-0 shadow-sm mb-3"><div class="card-header bg-white fw-semibold">Kirim Data Final</div><div class="card-body"><p class="text-muted small mb-3">Upload foto after dan kirim data akhir ke staff.</p>';
            render_create_invoice_form($booking); echo '</div></div>';
        } elseif ($booking['status'] === 'Pekerjaan Selesai' && $invoice) {
            echo '<div class="alert alert-info small">Data final sudah dikirim. Invoice sedang diproses oleh staff.</div>';
        }
    } elseif ($role === 'staff' && $booking['status'] === 'Estimasi Disetujui') {
        echo '<div class="card border-0 shadow-sm mb-3"><div class="card-header bg-white fw-semibold">Mulai Pekerjaan</div><div class="card-body">';
        render_start_work_form($booking); echo '</div></div>';
    } elseif ($role === 'customer') {
        echo '<div class="alert alert-info small"><i class="bi bi-info-circle me-1"></i>Pekerjaan sedang berlangsung. Anda akan mendapat notifikasi saat ada update.</div>';
    }
    echo '<div class="card border-0 shadow-sm mb-3"><div class="card-header bg-white d-flex align-items-center justify-content-between"><span class="fw-semibold">Catatan Lapangan</span><span class="text-muted small">' . count($progress) . ' update</span></div>';
    if ($progress) {
        echo '<div class="card-body p-0"><div class="list-group list-group-flush">';
        foreach ($progress as $row) {
            echo '<article class="list-group-item px-0"><div><span class="small text-secondary">' . h(format_datetime($row['created_at'])) . ' · ' . h($row['worker_name']) . '</span><strong>' . h($row['title']) . ' · ' . (int) $row['progress_percent'] . '%</strong>' . ($row['note'] ? '<p class="mb-0 small">' . h($row['note']) . '</p>' : '') . ($row['obstacle_note'] ? '<p class="mb-0 small text-warning"><i class="bi bi-exclamation-triangle me-1"></i>' . h($row['obstacle_note']) . '</p>' : '') . '</div></article>';
        }
        echo '</div></div>';
    } else { echo '<div class="card-body"><p class="text-muted small mb-0">Belum ada progress pekerjaan.</p></div>'; }
    echo '</div>';
    if ($progressPhotos) {
        echo '<div class="card border-0 shadow-sm mb-3"><div class="card-header bg-white d-flex align-items-center justify-content-between"><span class="fw-semibold">Foto Progress</span><span class="text-muted small">' . count($progressPhotos) . ' foto</span></div><div class="card-body"><div class="row g-3">';
        foreach ($progressPhotos as $p) { echo '<figure><a href="' . h($p['photo_path']) . '" target="_blank"><img src="' . h($p['photo_path']) . '" alt="progress"></a></figure>'; }
        echo '</div></div></div>';
    }
    if ($afterPhotos) {
        echo '<div class="card border-0 shadow-sm mb-3"><div class="card-header bg-white d-flex align-items-center justify-content-between"><span class="fw-semibold">Foto After</span><span class="text-muted small">' . count($afterPhotos) . ' foto</span></div><div class="card-body"><div class="row g-3">';
        foreach ($afterPhotos as $p) { echo '<figure><a href="' . h($p['photo_path']) . '" target="_blank"><img src="' . h($p['photo_path']) . '" alt="after"></a></figure>'; }
        echo '</div></div></div>';
    }
    if ($revision) {
        echo '<div class="card border-0 shadow-sm"><div class="card-header bg-white d-flex align-items-center justify-content-between"><span class="fw-semibold">Revisi Terakhir: ' . h($revision['revision_code']) . '</span><div class="d-flex gap-2"><span class="' . status_badge_class($revision['status']) . '">' . h($revision['status']) . '</span><a class="btn btn-outline-secondary btn-sm" href="dashboard.php?page=revision_detail&id=' . (int) $revision['id'] . '">Detail</a></div></div><div class="card-body"><p class="small fw-bold text-uppercase text-secondary mb-1">Total baru</p><p class="fw-bold text-success mb-2">' . format_rupiah($revision['new_total']) . '</p><p class="small fw-bold text-uppercase text-secondary mb-1">Alasan</p><p class="small mb-0">' . h($revision['reason']) . '</p></div></div>';
    }
    return;
}
$filters = booking_filters_from_request();
$activeSt = ['Estimasi Disetujui','Dalam Pengerjaan','Revisi Estimasi','Pekerjaan Selesai'];
$ew = ['b.status IN (' . implode(',', array_fill(0, count($activeSt), '?')) . ')']; $ep = $activeSt;
if ($filters['status'] !== '') { $ew = ['b.status = ?']; $ep = [$filters['status']]; }
$paged = booking_list_for_role($user, $role, ['q' => $filters['q'], 'status' => ''], $ew, $ep);
$rows = $paged['rows'];
render_booking_page_header('Pekerjaan', match ($role) { 'customer' => 'Progress Pekerjaan', 'pekerja_lapangan' => 'Pekerjaan Saya', 'boss' => 'Monitoring Pekerjaan', default => 'Pekerjaan' }, match ($role) { 'customer' => 'Pantau progress pekerjaan dari booking Anda.', 'pekerja_lapangan' => 'Booking yang sedang atau perlu dikerjakan.', default => 'Booking yang sedang dalam proses pengerjaan.' });
echo '<div class="card border-0 shadow-sm">';
render_booking_filter('booking_work', $filters);
render_booking_card_list($rows, $role, 'booking_work', function ($item) use ($role) {
    $cta = match ($item['status']) { 'Estimasi Disetujui' => 'Mulai Kerja', 'Dalam Pengerjaan','Revisi Estimasi' => $role === 'pekerja_lapangan' ? 'Update' : 'Monitor', 'Pekerjaan Selesai' => $role === 'pekerja_lapangan' ? 'Kirim Data' : 'Lihat', default => 'Lihat' };
    return [$cta, 'dashboard.php?page=booking_work&id=' . (int) $item['id'], 'btn-primary'];
});
if ($paged['totalPages'] > 1) { echo '<div class="card-footer bg-white border-top py-3">'; render_pagination($paged['page'], $paged['totalPages'], $paged['total'], $paged['perPage']); echo '</div>'; }
echo '</div>';
