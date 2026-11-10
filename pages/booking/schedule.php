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
    $booking = booking_for_access($bookingId, $user);
    if (!$booking) { echo '<div class="alert alert-warning">Booking tidak ditemukan.</div>'; return; }
    $pekerjaLapanganList = get_pekerja_lapangan();
    $schedules = db_query('SELECT sc.*, u.name AS pekerja_lapangan_name FROM schedules sc JOIN users u ON u.id = sc.pekerja_lapangan_id WHERE sc.booking_id = ? ORDER BY sc.schedule_date DESC', [$bookingId]);
    render_booking_detail_header($booking, $role, 'Jadwal Survey', 'booking_schedule');
    if ($role === 'staff') {
        if ($booking['status'] === 'Menunggu Konfirmasi') {
            echo '<div class="alert alert-info small mb-3">Untuk menerima booking, pilih pekerja lapangan dan jadwal survey. Status langsung menjadi <strong>Survey Dijadwalkan</strong>.</div>';
            echo '<div class="card border-0 shadow-sm mb-3"><div class="card-header bg-white fw-semibold">Terima &amp; Jadwalkan Survey</div><div class="card-body">';
            render_schedule_survey_form($booking, $pekerjaLapanganList);
            echo '</div></div>';
            echo '<div class="card border-0 shadow-sm mb-3"><div class="card-header bg-white fw-semibold text-danger">Tolak Booking</div><div class="card-body">';
            render_reject_booking_form($booking);
            echo '</div></div>';
        } elseif (in_array($booking['status'], ['Survey Dijadwalkan','Survey Berlangsung'], true)) {
            echo '<div class="card border-0 shadow-sm mb-3"><div class="card-header bg-white fw-semibold">Reschedule Survey</div><div class="card-body">';
            render_schedule_survey_form($booking, $pekerjaLapanganList);
            echo '</div></div>';
        } else {
            echo '<div class="card card-body text-center text-secondary py-5 mb-3"><i class="bi bi-calendar2-check"></i><h3>Tidak ada aksi jadwal</h3><p>Status booking saat ini tidak memerlukan penjadwalan.</p></div>';
        }
    } elseif ($role === 'pekerja_lapangan') {
        if ($booking['status'] === 'Survey Dijadwalkan') {
            echo '<div class="card border-0 shadow-sm mb-3"><div class="card-header bg-white fw-semibold">Mulai Survey</div><div class="card-body"><p class="text-muted small mb-3">Klik tombol ini saat Anda sudah tiba di lokasi.</p>';
            render_start_survey_form($booking);
            echo '</div></div>';
        } elseif ($booking['status'] === 'Survey Berlangsung') {
            echo '<div class="card border-0 shadow-sm mb-3"><div class="card-header bg-white fw-semibold">Survey Berlangsung</div><div class="card-body"><p class="text-muted small mb-3">Setelah selesai mengukur lokasi, isi hasil survey.</p><a class="btn btn-primary btn-sm" href="dashboard.php?page=booking_survey&id=' . $bookingId . '"><i class="bi bi-clipboard2-check me-1"></i>Input Hasil Survey</a></div></div>';
        } else {
            echo '<div class="card card-body text-center text-secondary py-5 mb-3"><i class="bi bi-calendar2-check"></i><h3>Tidak ada tugas survey aktif</h3><p>Tidak ada aksi survey yang perlu dilakukan saat ini.</p></div>';
        }
    } else {
        echo '<div class="card card-body text-center text-secondary py-5 mb-3"><i class="bi bi-eye"></i><h3>Mode monitoring</h3><p>Boss hanya memiliki akses monitoring.</p></div>';
    }
    if ($schedules) {
        echo '<div class="card border-0 shadow-sm"><div class="card-header bg-white fw-semibold">Riwayat Jadwal</div><div class="card-body p-0"><ul class="list-group list-group-flush">';
        foreach ($schedules as $row) {
            echo '<li class="list-group-item d-flex align-items-start justify-content-between gap-3 py-3">'
               . '<div><p class="mb-0 fw-semibold small">' . h($row['schedule_code']) . '</p>'
               . '<p class="mb-0 text-muted small">' . h(format_date_id($row['schedule_date'])) . ' pukul ' . h(substr($row['schedule_time'], 0, 5)) . ' · ' . h($row['pekerja_lapangan_name']) . '</p>'
               . ($row['notes'] ? '<p class="mb-0 text-muted small">' . h($row['notes']) . '</p>' : '')
               . '</div><span class="' . status_badge_class($row['status']) . '">' . h($row['status']) . '</span></li>';
        }
        echo '</ul></div></div>';
    }
    return;
}
$filters = booking_filters_from_request();
if ($role === 'staff') { $ew = ["b.status IN ('Menunggu Konfirmasi','Survey Dijadwalkan','Survey Berlangsung')"]; $ep = []; if ($filters['status'] !== '') { $ew = ['b.status = ?']; $ep = [$filters['status']]; } $paged = booking_list_for_role($user, $role, ['q' => $filters['q'], 'status' => ''], $ew, $ep);
} elseif ($role === 'pekerja_lapangan') { $ew = ["b.status IN ('Survey Dijadwalkan','Survey Berlangsung')"]; $ep = []; if ($filters['status'] !== '') { $ew = ['b.status = ?']; $ep = [$filters['status']]; } $paged = booking_list_for_role($user, $role, ['q' => $filters['q'], 'status' => ''], $ew, $ep);
} else { $paged = booking_list_for_role($user, $role, $filters); }
$rows = $paged['rows'];
render_booking_page_header('Survey', match ($role) { 'pekerja_lapangan' => 'Tugas Survey', 'boss' => 'Monitoring Jadwal', default => 'Jadwal Survey' }, match ($role) { 'pekerja_lapangan' => 'Booking yang ditugaskan ke Anda untuk disurvey.', 'boss' => 'Monitoring semua jadwal survey.', default => 'Booking yang perlu dijadwalkan atau sedang dalam proses survey.' });
echo '<div class="card border-0 shadow-sm">';
render_booking_filter('booking_schedule', $filters);
render_booking_card_list($rows, $role, 'booking_schedule', function ($item) {
    $cta = $item['status'] === 'Menunggu Konfirmasi' ? 'Atur Survey' : 'Lihat Jadwal';
    return [$cta, 'dashboard.php?page=booking_schedule&id=' . (int) $item['id'], 'btn-primary'];
});
if ($paged['totalPages'] > 1) { echo '<div class="card-footer bg-white border-top py-3">'; render_pagination($paged['page'], $paged['totalPages'], $paged['total'], $paged['perPage']); echo '</div>'; }
echo '</div>';
