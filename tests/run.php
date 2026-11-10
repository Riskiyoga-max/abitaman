<?php
declare(strict_types=1);

require_once __DIR__ . '/../data/session_helper.php';
require_once __DIR__ . '/../pages/booking/_helpers.php';

$failures = [];
$checks = 0;

$assertSame = static function (mixed $expected, mixed $actual, string $label) use (&$failures, &$checks): void {
    $checks++;
    if ($expected !== $actual) {
        $failures[] = "{$label}: expected " . var_export($expected, true) . ', got ' . var_export($actual, true);
    }
};

$stageCases = [
    'Menunggu Konfirmasi' => ['booking', 'Booking'],
    'Dibatalkan' => ['booking', 'Booking'],
    'Survey Dijadwalkan' => ['survey', 'Survei'],
    'Survey Berlangsung' => ['survey', 'Survei'],
    'Survey Selesai' => ['survey', 'Survei'],
    'Estimasi Dibuat' => ['approval', 'Persetujuan'],
    'Menunggu Persetujuan Customer' => ['approval', 'Persetujuan'],
    'Estimasi Disetujui' => ['approval', 'Persetujuan'],
    'Estimasi Ditolak' => ['approval', 'Persetujuan'],
    'Dalam Pengerjaan' => ['work', 'Pengerjaan'],
    'Revisi Estimasi' => ['work', 'Pengerjaan'],
    'Pekerjaan Selesai' => ['work', 'Pengerjaan'],
    'Invoice Final Dibuat' => ['completion', 'Penyelesaian'],
    'Menunggu Pembayaran' => ['completion', 'Penyelesaian'],
    'Lunas' => ['completion', 'Penyelesaian'],
    'Selesai' => ['completion', 'Penyelesaian'],
];
foreach ($stageCases as $status => [$key, $label]) {
    $assertSame($key, booking_workflow_stage_key($status), "stage key {$status}");
    $assertSame($label, booking_workflow_stage_label($status), "stage label {$status}");
}

$assertSame(1, calculate_block_count(1), 'block 1m²');
$assertSame(1, calculate_block_count(10), 'block 10m²');
$assertSame(2, calculate_block_count(10.1), 'block 10.1m²');
$assertSame(1560000, calculate_labor_total(2, 6, 130000), 'labor total');
$assertSame(650000, calculate_additional_total([
    ['quantity' => 2, 'unit_price' => 200000],
    ['quantity' => 1, 'unit_price' => 250000],
]), 'additional total');
$assertSame(3210000, calculate_grand_total(1000000, 1560000, 650000), 'grand total');

$staffBooking = ['id' => 1, 'status' => 'Menunggu Konfirmasi'];
$assertSame(
    'Jadwalkan survei',
    booking_next_action_label($staffBooking, 'staff', null, null, null),
    'staff new booking action'
);
$workerSurvey = ['id' => 2, 'status' => 'Survey Selesai'];
$assertSame(
    'Buat estimasi',
    booking_next_action_label($workerSurvey, 'pekerja_lapangan', null, null, null),
    'worker survey action'
);
$customerEstimate = ['id' => 3, 'status' => 'Menunggu Persetujuan Customer'];
$assertSame(
    'Putuskan estimasi',
    booking_next_action_label($customerEstimate, 'customer', ['status' => 'Dikirim'], null, null),
    'customer estimate action'
);

if ($failures) {
    fwrite(STDERR, "TEST GAGAL (" . count($failures) . "/{$checks})\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "TEST BERHASIL ({$checks} pemeriksaan)\n";
