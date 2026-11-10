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
    $booking       = booking_for_access($bookingId, $user);
    if (!$booking) { echo '<div class="alert alert-warning">Booking tidak ditemukan.</div>'; return; }
    $estimate      = latest_booking_estimate($bookingId);
    $estimateItems = $estimate ? db_query('SELECT * FROM estimate_items WHERE estimate_id = ? ORDER BY id', [$estimate['id']]) : [];
    $revision      = db_first('SELECT * FROM estimate_revisions WHERE booking_id = ? ORDER BY id DESC LIMIT 1', [$bookingId]);
    $revisionItems = $revision ? db_query('SELECT * FROM estimate_revision_items WHERE revision_id = ? ORDER BY id', [$revision['id']]) : [];
    $service = $booking['service_id'] ? db_first('SELECT * FROM services WHERE id = ?', [$booking['service_id']]) : null;
    render_booking_detail_header($booking, $role, 'Estimasi Biaya', 'booking_estimate');
    if ($role === 'pekerja_lapangan' && $booking['status'] === 'Survey Selesai' && !$estimate) {
        echo '<div class="card border-0 shadow-sm mb-3"><div class="card-header bg-white fw-semibold">Buat Estimasi Biaya</div><div class="card-body">';
        render_create_estimate_form($booking, $service);
        echo '</div></div>';
    } elseif ($estimate) {
        // Summary harga
        echo '<div class="row g-3 mb-3">'
           . '<div class="col-12 col-md-4"><div class="card border text-center p-3 h-100"><p class="small fw-bold text-uppercase text-secondary mb-1">Total Estimasi</p><p class="h4 fw-bold text-success mb-0">' . format_rupiah($estimate['grand_total']) . '</p></div></div>'
           . '<div class="col-6 col-md-4"><div class="card border text-center p-3 h-100"><p class="small fw-bold text-uppercase text-secondary mb-1">Area / Blok</p><p class="fw-bold mb-0">' . h($estimate['area_size']) . ' m² · ' . h($estimate['block_count']) . ' blok</p></div></div>'
           . '<div class="col-6 col-md-4"><div class="card border text-center p-3 h-100"><p class="small fw-bold text-uppercase text-secondary mb-1">Durasi / Tukang</p><p class="fw-bold mb-0">' . (int) $estimate['estimated_days'] . ' hari · ' . (int) $estimate['worker_count'] . ' orang</p></div></div>'
           . '</div>';
        echo '<div class="card border-0 shadow-sm mb-3"><div class="card-header bg-white d-flex align-items-center justify-content-between flex-wrap gap-2">'
           . '<div><p class="small fw-bold text-uppercase text-secondary mb-0">Estimasi</p><p class="fw-bold mb-0">' . h($estimate['estimate_code']) . '</p></div>'
           . '<div class="d-flex align-items-center gap-2 flex-wrap"><span class="' . status_badge_class($estimate['status']) . '">' . h($estimate['status']) . '</span>';
        if ($role === 'staff' && $estimate['status'] === 'Draft') {
            echo '<form class="d-inline" data-api-form method="post">' . csrf_field() . '<input type="hidden" name="action" value="send_estimate"><input type="hidden" name="estimate_id" value="' . (int) $estimate['id'] . '"><button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-send me-1"></i>Review &amp; Kirim</button></form>';
        }
        echo '</div></div><div class="card-body p-0"><div class="table-responsive"><table class="table table-sm table table-hover align-middle mb-0"><thead><tr><th>Item</th><th>Tipe</th><th>Qty</th><th>Harga</th><th>Subtotal</th></tr></thead><tbody>';
        foreach ($estimateItems as $item) {
            echo '<tr><td><strong>' . h($item['item_name']) . '</strong></td><td><span class="badge text-bg-secondary">' . h($item['item_type']) . '</span></td><td>' . h($item['quantity'] . ' ' . $item['unit']) . '</td><td>' . format_rupiah($item['unit_price']) . '</td><td><strong>' . format_rupiah($item['subtotal']) . '</strong></td></tr>';
        }
        if (!$estimateItems) echo '<tr><td colspan="5" class="text-center text-muted py-3">Belum ada item.</td></tr>';
        echo '</tbody></table></div>';
        if ($role === 'customer' && $estimate['status'] === 'Dikirim') {
            echo '<div class="p-3 border-top"><div class="card border-warning mb-3"><div class="card-header bg-warning-subtle"><div><h4>Keputusan Anda</h4><p>Setujui estimasi untuk lanjut ke tahap pekerjaan, atau tolak jika tidak sesuai.</p></div></div>'
               . '<form data-api-form class="d-flex flex-wrap align-items-center gap-2" method="post">' . csrf_field() . '<input type="hidden" name="estimate_id" value="' . (int) $estimate['id'] . '"><textarea class="form-control" name="customer_response_note" rows="2" placeholder="Catatan keputusan (opsional)"></textarea><button class="btn btn-primary btn-sm" name="action" value="approve_estimate" type="submit"><i class="bi bi-check-lg me-1"></i>Setujui</button><button class="btn btn-outline-danger btn-sm" name="action" value="reject_estimate" type="submit"><i class="bi bi-x-lg me-1"></i>Tolak</button></form></div></div>';
        } elseif ($estimate['customer_response_note']) {
            echo '<div class="p-3 border-top"><p class="small fw-bold text-uppercase text-secondary mb-1">Catatan Customer</p><p class="mb-0 small">' . h($estimate['customer_response_note']) . '</p></div>';
        }
        echo '</div></div>';
    } else {
        echo '<div class="card card-body text-center text-secondary py-5 mb-3"><i class="bi bi-calculator"></i><h3>Estimasi belum dibuat</h3><p>Pekerja lapangan belum membuat estimasi untuk booking ini.</p></div>';
    }
    if ($revision) {
        echo '<div class="card border-0 shadow-sm"><div class="card-header bg-white d-flex align-items-center justify-content-between flex-wrap gap-2">'
           . '<div><p class="small fw-bold text-uppercase text-secondary mb-0">Revisi</p><p class="fw-bold mb-0">' . h($revision['revision_code']) . '</p></div>'
           . '<div class="d-flex align-items-center gap-2"><span class="' . status_badge_class($revision['status']) . '">' . h($revision['status']) . '</span>';
        if ($role === 'staff' && $revision['status'] === 'Draft') {
            echo '<form class="d-inline" data-api-form method="post">' . csrf_field() . '<input type="hidden" name="action" value="send_revision"><input type="hidden" name="revision_id" value="' . (int) $revision['id'] . '"><button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-send me-1"></i>Kirim</button></form>';
        }
        echo '</div></div><div class="card-body">'
           . '<div class="row g-3 mb-3"><div class="col-6"><p class="small fw-bold text-uppercase text-secondary mb-1">Total lama</p><p class="fw-bold mb-0">' . format_rupiah($revision['old_total']) . '</p></div><div class="col-6"><p class="small fw-bold text-uppercase text-secondary mb-1">Total baru</p><p class="fw-bold text-success mb-0">' . format_rupiah($revision['new_total']) . '</p></div></div>'
           . '<p class="small fw-bold text-uppercase text-secondary mb-1">Alasan revisi</p><p class="small mb-0">' . h($revision['reason']) . '</p>';
        if ($revisionItems) {
            echo '<div class="table-responsive mt-3"><table class="table table-sm table table-hover align-middle mb-0"><thead><tr><th>Item</th><th>Qty</th><th>Harga</th><th>Subtotal</th></tr></thead><tbody>';
            foreach ($revisionItems as $item) { echo '<tr><td><strong>' . h($item['item_name']) . '</strong><br><small>' . h($item['description'] ?: '-') . '</small></td><td>' . h($item['quantity'] . ' ' . $item['unit']) . '</td><td>' . format_rupiah($item['unit_price']) . '</td><td><strong>' . format_rupiah($item['subtotal']) . '</strong></td></tr>'; }
            echo '</tbody></table></div>';
        }
        if ($role === 'customer' && $revision['status'] === 'Dikirim') {
            echo '<div class="card border-warning mb-3 mt-3"><div class="card-header bg-warning-subtle"><div><h4>Keputusan Revisi</h4><p>Setujui atau tolak perubahan biaya ini.</p></div></div><form data-api-form class="d-flex flex-wrap align-items-center gap-2" method="post">' . csrf_field() . '<input type="hidden" name="revision_id" value="' . (int) $revision['id'] . '"><textarea class="form-control" name="customer_response_note" rows="2" placeholder="Catatan keputusan (opsional)"></textarea><button class="btn btn-primary btn-sm" name="action" value="approve_revision" type="submit"><i class="bi bi-check-lg me-1"></i>Setujui</button><button class="btn btn-outline-danger btn-sm" name="action" value="reject_revision" type="submit"><i class="bi bi-x-lg me-1"></i>Tolak</button></form></div>';
        }
        echo '</div></div>';
    }
    return;
}
$filters = booking_filters_from_request();
if ($role === 'pekerja_lapangan') { $ew = ["b.status IN ('Survey Selesai','Menunggu Persetujuan Customer','Estimasi Disetujui','Estimasi Ditolak')"]; $ep = []; if ($filters['status'] !== '') { $ew = ['b.status = ?']; $ep = [$filters['status']]; } $paged = booking_list_for_role($user, $role, ['q' => $filters['q'], 'status' => ''], $ew, $ep);
} elseif ($role === 'staff') { $ew = ["b.status IN ('Survey Selesai','Estimasi Dibuat','Menunggu Persetujuan Customer','Estimasi Disetujui','Estimasi Ditolak','Revisi Estimasi')"]; $ep = []; if ($filters['status'] !== '') { $ew = ['b.status = ?']; $ep = [$filters['status']]; } $paged = booking_list_for_role($user, $role, ['q' => $filters['q'], 'status' => ''], $ew, $ep);
} elseif ($role === 'customer') { $ew = ["b.status IN ('Menunggu Persetujuan Customer','Estimasi Disetujui','Estimasi Ditolak','Revisi Estimasi')"]; $ep = []; if ($filters['status'] !== '') { $ew = ['b.status = ?']; $ep = [$filters['status']]; } $paged = booking_list_for_role($user, $role, ['q' => $filters['q'], 'status' => ''], $ew, $ep);
} else { $paged = booking_list_for_role($user, $role, $filters); }
$rows = $paged['rows'];
render_booking_page_header('Estimasi', match ($role) { 'customer' => 'Estimasi Saya', 'pekerja_lapangan' => 'Buat Estimasi', 'boss' => 'Monitoring Estimasi', default => 'Estimasi Booking' }, match ($role) { 'customer' => 'Estimasi biaya dari booking Anda yang perlu diputuskan.', 'pekerja_lapangan' => 'Booking yang perlu dibuatkan estimasi biaya.', default => 'Booking yang memerlukan pembuatan atau review estimasi.' });
echo '<div class="card border-0 shadow-sm">';
render_booking_filter('booking_estimate', $filters);
render_booking_card_list($rows, $role, 'booking_estimate', function ($item) use ($role) {
    $cta = match ($role) { 'customer' => 'Putuskan', 'pekerja_lapangan' => $item['status'] === 'Survey Selesai' ? 'Buat Estimasi' : 'Lihat', default => 'Review' };
    return [$cta, 'dashboard.php?page=booking_estimate&id=' . (int) $item['id'], 'btn-primary'];
});
if ($paged['totalPages'] > 1) { echo '<div class="card-footer bg-white border-top py-3">'; render_pagination($paged['page'], $paged['totalPages'], $paged['total'], $paged['perPage']); echo '</div>'; }
echo '</div>';
