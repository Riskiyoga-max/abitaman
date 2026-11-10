<?php
declare(strict_types=1);

function booking_list_page_for_role(string $role): string
{
    return match ($role) {
        'pekerja_lapangan' => 'tasks',
        'boss'             => 'monitoring',
        default            => 'order',
    };
}

function booking_workflow_stage_from_status(string $status): int
{
    return match ($status) {
        'Menunggu Konfirmasi', 'Dibatalkan'                                              => 0,
        'Survey Dijadwalkan', 'Survey Berlangsung', 'Survey Selesai'                     => 1,
        'Estimasi Dibuat', 'Menunggu Persetujuan Customer', 'Estimasi Disetujui',
        'Estimasi Ditolak'                                                               => 2,
        'Dalam Pengerjaan', 'Revisi Estimasi', 'Pekerjaan Selesai'                       => 3,
        'Invoice Final Dibuat', 'Menunggu Pembayaran', 'Lunas', 'Selesai'                 => 4,
        default                                                                          => 0,
    };
}

function booking_workflow_stage_key(string $status): string
{
    return ['booking', 'survey', 'approval', 'work', 'completion'][booking_workflow_stage_from_status($status)];
}

function booking_workflow_stage_label(string $status): string
{
    return ['Booking', 'Survei', 'Persetujuan', 'Pengerjaan', 'Penyelesaian'][booking_workflow_stage_from_status($status)];
}

function booking_stage_statuses(string $stage): array
{
    return match ($stage) {
        'booking' => ['Menunggu Konfirmasi', 'Dibatalkan'],
        'survey' => ['Survey Dijadwalkan', 'Survey Berlangsung', 'Survey Selesai'],
        'approval' => ['Estimasi Dibuat', 'Menunggu Persetujuan Customer', 'Estimasi Disetujui', 'Estimasi Ditolak'],
        'work' => ['Dalam Pengerjaan', 'Revisi Estimasi', 'Pekerjaan Selesai'],
        'completion' => ['Invoice Final Dibuat', 'Menunggu Pembayaran', 'Lunas', 'Selesai'],
        default => [],
    };
}

function booking_detail_url(int $bookingId, string $section = 'overview'): string
{
    return 'dashboard.php?page=booking_detail&id=' . $bookingId . '&section=' . urlencode($section);
}

function booking_workflow_target_page(array $booking, string $role, ?array $estimate, ?array $revision, ?array $invoice): string
{
    $bookingId = (int) $booking['id'];

    return match (true) {
        $role === 'staff' && $booking['status'] === 'Menunggu Konfirmasi'
            => booking_detail_url($bookingId, 'actions'),
        $role === 'staff' && $estimate && $estimate['status'] === 'Draft'
            => booking_detail_url($bookingId, 'estimate'),
        $role === 'staff' && $revision && $revision['status'] === 'Draft'
            => booking_detail_url($bookingId, 'estimate'),
        $role === 'staff' && $invoice && $invoice['status'] === 'Draft'
            => booking_detail_url($bookingId, 'billing'),
        $role === 'staff' && $invoice && $invoice['payment_status'] === 'Belum Dibayar'
            => booking_detail_url($bookingId, 'billing'),
        $role === 'pekerja_lapangan' && in_array($booking['status'], ['Survey Dijadwalkan', 'Survey Berlangsung'], true)
            => booking_detail_url($bookingId, 'actions'),
        $role === 'pekerja_lapangan' && $booking['status'] === 'Survey Selesai'
            => booking_detail_url($bookingId, 'actions'),
        $role === 'pekerja_lapangan' && in_array($booking['status'], ['Dalam Pengerjaan', 'Revisi Estimasi', 'Pekerjaan Selesai'], true)
            => booking_detail_url($bookingId, 'actions'),
        $role === 'customer' && $estimate && $estimate['status'] === 'Dikirim'
            => booking_detail_url($bookingId, 'actions'),
        $role === 'customer' && $revision && $revision['status'] === 'Dikirim'
            => booking_detail_url($bookingId, 'actions'),
        $role === 'customer' && $invoice
            => booking_detail_url($bookingId, 'billing'),
        default
            => booking_detail_url($bookingId),
    };
}

function booking_next_action_label(array $booking, string $role, ?array $estimate, ?array $revision, ?array $invoice, bool $hasPaymentProof = false, bool $hasReview = false): ?string
{
    return match (true) {
        $role === 'staff' && $hasPaymentProof => 'Verifikasi pembayaran',
        $role === 'staff' && $booking['status'] === 'Menunggu Konfirmasi' => 'Jadwalkan survei',
        $role === 'staff' && $estimate && $estimate['status'] === 'Draft' => 'Review estimasi',
        $role === 'staff' && $revision && $revision['status'] === 'Draft' => 'Review revisi',
        $role === 'staff' && $invoice && $invoice['status'] === 'Draft' => 'Review invoice',
        $role === 'staff' && $booking['status'] === 'Lunas' => 'Selesaikan order',
        $role === 'customer' && $estimate && $estimate['status'] === 'Dikirim' => 'Putuskan estimasi',
        $role === 'customer' && $revision && $revision['status'] === 'Dikirim' => 'Putuskan revisi',
        $role === 'customer' && $invoice && $invoice['status'] === 'Dikirim' && $invoice['payment_status'] === 'Belum Dibayar' => 'Kirim bukti bayar',
        $role === 'customer' && $booking['status'] === 'Selesai' && !$hasReview => 'Beri ulasan',
        $role === 'pekerja_lapangan' && $booking['status'] === 'Survey Dijadwalkan' => 'Mulai survei',
        $role === 'pekerja_lapangan' && $booking['status'] === 'Survey Berlangsung' => 'Isi hasil survei',
        $role === 'pekerja_lapangan' && $booking['status'] === 'Survey Selesai' => 'Buat estimasi',
        $role === 'pekerja_lapangan' && $booking['status'] === 'Estimasi Disetujui' => 'Mulai pekerjaan',
        $role === 'pekerja_lapangan' && in_array($booking['status'], ['Dalam Pengerjaan', 'Revisi Estimasi'], true) => 'Update progres',
        $role === 'pekerja_lapangan' && $booking['status'] === 'Pekerjaan Selesai' => 'Kirim data final',
        default => null,
    };
}

function booking_status_label_for_role(string $status, string $role): string
{
    if ($role !== 'pekerja_lapangan') {
        return $status;
    }

    return match ($status) {
        'Invoice Final Dibuat' => 'Data Final Dikirim',
        'Menunggu Pembayaran'  => 'Menunggu Administrasi',
        'Lunas'                => 'Administrasi Selesai',
        default                => $status,
    };
}

function booking_status_note_for_role(?string $note, string $role): string
{
    $note = (string) ($note ?? '');
    if ($role !== 'pekerja_lapangan' || $note === '') {
        return $note;
    }

    return str_replace(
        ['Invoice Final', 'Invoice final', 'invoice final', 'Invoice', 'invoice', 'Pembayaran', 'pembayaran', 'Lunas', 'lunas'],
        ['Data Final', 'Data final', 'data final', 'Data final', 'data final', 'Administrasi', 'administrasi', 'Selesai', 'selesai'],
        $note
    );
}
