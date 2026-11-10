<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';

function seed_slug(string $value): string
{
    $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $value), '-'));
    return $slug ?: 'item';
}

function seed_insert(string $sql, array $params = []): int
{
    return (int) db_insert($sql, $params);
}

db()->exec('SET FOREIGN_KEY_CHECKS=0');
foreach ([
    'reviews', 'final_invoice_items', 'final_invoices', 'payments', 'work_progress',
    'estimate_revision_items', 'estimate_revisions', 'estimate_items', 'estimates',
    'survey_photos', 'surveys', 'schedules', 'booking_status_histories',
    'notifications', 'activity_logs', 'bookings', 'additional_cost_types',
    'labor_rates', 'materials', 'services',
    'service_categories', 'settings', 'users',
] as $table) {
    db()->exec("TRUNCATE TABLE {$table}");
}
db()->exec('SET FOREIGN_KEY_CHECKS=1');

$users = [
    ['Udin', 'udin@abitaman.id', 'staff123', 'staff', '0812-9000-2211', 'Jl. Mawar Raya, Tangerang Selatan'],
    ['Riski', 'riski@abitaman.id', 'pekerja123', 'pekerja_lapangan', '0813-2200-0011', 'Pamulang, Tangerang Selatan'],
    ['Abi', 'abi@abitaman.id', 'boss123', 'boss', '0821-4400-5566', 'BSD, Tangerang Selatan'],
    ['Yoga', 'yoga@email.com', 'user123', 'customer', '0812-3311-7806', 'Ciputat, Tangerang Selatan'],
];

$userIds = [];
foreach ($users as [$name, $email, $password, $role, $phone, $address]) {
    $userIds[$email] = seed_insert(
        'INSERT INTO users (name, email, password, role, phone, address, status) VALUES (?, ?, ?, ?, ?, ?, ?)',
        [$name, $email, password_hash($password, PASSWORD_BCRYPT), $role, $phone, $address, 'active']
    );
}

$categories = [
    ['Pembuatan Taman', 'Layanan pembuatan taman rumah, kantor, dan area komersial.', 'assets/img/services/taman-minimalis.svg'],
    ['Perawatan Taman', 'Perawatan berkala untuk taman tetap sehat dan rapi.', 'assets/img/services/perawatan-taman.svg'],
    ['Renovasi Taman', 'Peremajaan area hijau yang sudah tidak optimal.', 'assets/img/services/renovasi-taman.svg'],
    ['Vertical Garden', 'Instalasi taman vertikal untuk area terbatas.', 'assets/img/services/vertical-garden.svg'],
    ['Kolam Minimalis', 'Kolam dekoratif dan elemen air minimalis.', 'assets/img/services/kolam-minimalis.svg'],
    ['Custom Landscape', 'Desain landscape custom sesuai kebutuhan lokasi.', 'assets/img/services/custom-landscape.svg'],
];

$categoryIds = [];
foreach ($categories as [$name, $description, $image]) {
    $categoryIds[$name] = seed_insert(
        'INSERT INTO service_categories (name, slug, description, image, status) VALUES (?, ?, ?, ?, ?)',
        [$name, seed_slug($name), $description, $image, 'active']
    );
}

$services = [
    ['Pembuatan Taman Minimalis', 'Pembuatan Taman', 'Layanan pembuatan taman rumah dengan komposisi tanaman, rumput, batu, dan jalur pijak.', 'assets/img/services/taman-minimalis.svg'],
    ['Renovasi Taman Rumah', 'Renovasi Taman', 'Perbaikan layout, penggantian tanaman, dan penguatan media tanam.', 'assets/img/services/renovasi-taman.svg'],
    ['Vertical Garden', 'Vertical Garden', 'Taman dinding modular untuk hunian, cafe, dan kantor.', 'assets/img/services/vertical-garden.svg'],
    ['Perawatan Taman Bulanan', 'Perawatan Taman', 'Perawatan rutin, pemangkasan, pemupukan, dan pembersihan area taman.', 'assets/img/services/perawatan-taman.svg'],
    ['Kolam Minimalis', 'Kolam Minimalis', 'Pembuatan kolam minimalis dengan sirkulasi air dan tanaman pendamping.', 'assets/img/services/kolam-minimalis.svg'],
    ['Custom Landscape', 'Custom Landscape', 'Layanan landscape custom untuk area yang membutuhkan rancangan khusus.', 'assets/img/services/custom-landscape.svg'],
];

$serviceIds = [];
$serviceIndex = 1;
foreach ($services as [$name, $category, $description, $image]) {
    $serviceIds[$name] = seed_insert(
        'INSERT INTO services (category_id, service_code, name, slug, short_description, description, image, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        [$categoryIds[$category], 'SRV-' . str_pad((string) $serviceIndex, 3, '0', STR_PAD_LEFT), $name, seed_slug($name), mb_substr($description, 0, 180), $description, $image, 'active']
    );
    $serviceIndex++;
}

$materials = [
    ['MAT-001', 'Batu dekoratif', 'kg', 100000, 1, 'assets/img/materials/batu-dekoratif.svg'],
    ['MAT-002', 'Tanah taman', 'karung', 100000, 2, 'assets/img/materials/tanah-taman.svg'],
    ['MAT-003', 'Rumput gajah mini', 'm2', 50000, 8, 'assets/img/materials/rumput.svg'],
    ['MAT-004', 'Tanaman hias', 'pot', 45000, 6, 'assets/img/materials/tanaman-hias.svg'],
    ['MAT-005', 'Pupuk', 'kg', 25000, 2, 'assets/img/materials/pupuk.svg'],
    ['MAT-006', 'Pasir', 'karung', 40000, 2, 'assets/img/materials/pasir.svg'],
    ['MAT-007', 'Kerikil', 'kg', 30000, 4, 'assets/img/materials/kerikil.svg'],
];
foreach ($materials as $row) {
    db_execute('INSERT INTO materials (material_code, name, unit, price_per_unit, default_qty_per_10m, image, status) VALUES (?, ?, ?, ?, ?, ?, ?)', [...$row, 'active']);
}

$laborIds = [];
$laborIds['Tukang taman umum'] = seed_insert(
    'INSERT INTO labor_rates (name, description, daily_rate, status) VALUES (?, ?, ?, ?)',
    ['Tukang taman umum', 'Tenaga kerja lapangan untuk pemasangan dan perapian taman.', 130000, 'active']
);
$laborIds['Mandor lapangan'] = seed_insert(
    'INSERT INTO labor_rates (name, description, daily_rate, status) VALUES (?, ?, ?, ?)',
    ['Mandor lapangan', 'Koordinator pekerjaan lapangan dan quality check.', 180000, 'active']
);

$additionalCosts = [
    ['Angkut puing', 'Pengangkutan sisa bongkaran dan sampah taman.', 250000, 'rit'],
    ['Transportasi', 'Biaya mobilisasi tim dan material.', 200000, 'trip'],
    ['Sewa alat', 'Kebutuhan alat kerja tambahan.', 150000, 'hari'],
    ['Akses lokasi sulit', 'Tambahan biaya untuk lokasi dengan akses sempit atau bertingkat.', 300000, 'lokasi'],
    ['Tambahan tanaman', 'Permintaan tanaman tambahan di luar estimasi awal.', 100000, 'item'],
];
foreach ($additionalCosts as $row) {
    db_execute('INSERT INTO additional_cost_types (name, description, default_price, unit, status) VALUES (?, ?, ?, ?, ?)', [...$row, 'active']);
}

foreach ([
    ['app_name', 'ABI Taman Service Commerce System'],
    ['company_name', 'ABI Taman'],
    ['company_email', 'hello@abitaman.id'],
    ['company_phone', '0812-9000-2211'],
    ['company_address', 'Tangerang Selatan, Banten'],
    ['survey_fee_amount', '100000'],
] as [$key, $value]) {
    db_execute('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)', [$key, $value]);
}

echo "Seed master data selesai. Data transaksi dikosongkan.\n";
