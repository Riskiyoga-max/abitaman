<?php
declare(strict_types=1);
require_once __DIR__ . '/../../data/session_helper.php';
require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/_queries.php';
require_once __DIR__ . '/_forms.php';
require_once __DIR__ . '/_list_helpers.php';
require_roles(['staff', 'pekerja_lapangan', 'boss']);
$bookingId = (int) ($_GET['id'] ?? 0);
if ($bookingId > 0) {
    $booking  = booking_for_access($bookingId, $user);
    if (!$booking) { echo '<div class="alert alert-warning">Booking tidak ditemukan.</div>'; return; }
    $survey   = db_first('SELECT sv.*, u.name AS pekerja_lapangan_name FROM surveys sv LEFT JOIN users u ON u.id = sv.pekerja_lapangan_id WHERE sv.booking_id = ? ORDER BY sv.id DESC LIMIT 1', [$bookingId]);
    $photos   = db_query('SELECT * FROM survey_photos WHERE booking_id = ? AND photo_type = ? ORDER BY created_at', [$bookingId, 'before']);
    
    render_booking_detail_header($booking, $role, 'Hasil Survey', 'booking_survey');
    if ($role === 'pekerja_lapangan' && $booking['status'] === 'Survey Berlangsung') {
        echo '<div class="card border-0 shadow-sm mb-3"><div class="card-header bg-white fw-semibold">Input Hasil Survey</div><div class="card-body">';
        render_complete_survey_form($booking);
        echo '</div></div>';
    } elseif ($survey) {
        echo '<div class="card border-0 shadow-sm mb-3"><div class="card-header bg-white d-flex align-items-center justify-content-between"><span class="fw-semibold">Data Lapangan</span><span class="badge text-bg-success">Selesai</span></div><div class="card-body"><div class="row g-3">'
           . '<div class="col-md-6 border rounded p-3"><span>Luas aktual</span><strong>' . h($survey['actual_area']) . ' m²</strong></div>'
           . '<div class="col-md-6 border rounded p-3"><span>Pekerja lapangan</span><strong>' . h($survey['pekerja_lapangan_name'] ?? '-') . '</strong></div>'
           . '<div class="col-md-6 border rounded p-3"><span>Durasi / tukang</span><strong>' . (int) $survey['estimated_work_days'] . ' hari · ' . (int) $survey['estimated_worker_count'] . ' tukang</strong></div>'
           . '<div class="col-md-6 border rounded p-3"><span>Kondisi lokasi</span><p>' . h($survey['location_condition']) . '</p></div>'
           . '<div class="col-md-6 border rounded p-3"><span>Akses lokasi</span><p>' . h($survey['access_condition']) . '</p></div>'
           . ($survey['obstacle_notes'] ? '<div class="col-md-6 border rounded p-3"><span>Kendala</span><p>' . h($survey['obstacle_notes']) . '</p></div>' : '')
           . '</div></div></div>';
    } else {
        echo '<div class="card card-body text-center text-secondary py-5 mb-3"><i class="bi bi-clipboard2"></i><h3>Belum ada hasil survey</h3><p>' . ($booking['status'] === 'Survey Dijadwalkan' ? 'Survey sudah dijadwalkan. Pekerja lapangan belum memulai.' : 'Survey belum dilakukan.') . '</p></div>';
    }
    if ($photos) {
        echo '<div class="card border-0 shadow-sm"><div class="card-header bg-white d-flex align-items-center justify-content-between"><span class="fw-semibold">Foto Kondisi Lokasi</span><span class="text-muted small">' . count($photos) . ' foto</span></div><div class="card-body"><div class="row g-3">';
        foreach ($photos as $photo) { echo '<figure><a href="' . h($photo['photo_path']) . '" target="_blank"><img src="' . h($photo['photo_path']) . '" alt="Foto before"></a></figure>'; }
        echo '</div></div></div>';
    }
    return;
}
$filters = booking_filters_from_request();
if ($role === 'pekerja_lapangan') { $ew = ["b.status IN ('Survey Berlangsung','Survey Selesai')"]; $ep = []; if ($filters['status'] !== '') { $ew = ['b.status = ?']; $ep = [$filters['status']]; } $paged = booking_list_for_role($user, $role, ['q' => $filters['q'], 'status' => ''], $ew, $ep);
} else { $ew = ["b.status IN ('Survey Berlangsung','Survey Selesai')"]; $ep = []; if ($filters['status'] !== '') { $ew = ['b.status = ?']; $ep = [$filters['status']]; } $paged = booking_list_for_role($user, $role, ['q' => $filters['q'], 'status' => ''], $ew, $ep); }
$rows = $paged['rows'];
render_booking_page_header('Survey', match ($role) { 'pekerja_lapangan' => 'Input Survey', 'boss' => 'Monitoring Survey', default => 'Hasil Survey' }, match ($role) { 'pekerja_lapangan' => 'Booking yang sedang atau sudah Anda survey.', default => 'Booking yang sedang atau sudah selesai disurvey.' });
echo '<div class="card border-0 shadow-sm">';
render_booking_filter('booking_survey', $filters);
render_booking_card_list($rows, $role, 'booking_survey', function ($item) {
    $cta = $item['status'] === 'Survey Berlangsung' ? 'Input Survey' : 'Lihat Hasil';
    return [$cta, 'dashboard.php?page=booking_survey&id=' . (int) $item['id'], 'btn-primary'];
});
if ($paged['totalPages'] > 1) { echo '<div class="card-footer bg-white border-top py-3">'; render_pagination($paged['page'], $paged['totalPages'], $paged['total'], $paged['perPage']); echo '</div>'; }
echo '</div>';
