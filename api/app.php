<?php
declare(strict_types=1);

require_once __DIR__ . '/../data/session_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Method tidak valid.', [], 405);
}

require_login();
require_csrf();

$user = current_user();
$role = $user['role'];
$action = $_POST['action'] ?? '';

function post_int(string $key, int $default = 0): int
{
    return (int) ($_POST[$key] ?? $default);
}

function post_float(string $key, float $default = 0): float
{
    return (float) ($_POST[$key] ?? $default);
}

function post_trim(string $key, string $default = ''): string
{
    return trim((string) ($_POST[$key] ?? $default));
}

function require_value(string $value, string $message): void
{
    if ($value === '') {
        json_response(false, $message, [], 422);
    }
}

function save_single_upload(string $inputName, string $relativeDir, bool $allowSvg = false): ?string
{
    if (empty($_FILES[$inputName]) || ($_FILES[$inputName]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($_FILES[$inputName]['error'] !== UPLOAD_ERR_OK) {
        json_response(false, 'Upload file gagal diproses.', [], 422);
    }

    if ((int) $_FILES[$inputName]['size'] > 2 * 1024 * 1024) {
        json_response(false, 'Ukuran file maksimal 2MB.', [], 422);
    }

    // SVG is no longer allowed for runtime uploads; allowSvg parameter is kept for BC but ignored
    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
    $allowedMime = ['image/jpeg', 'image/png', 'image/webp'];
    $ext = strtolower(pathinfo((string) $_FILES[$inputName]['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) {
        json_response(false, 'Format file harus jpg, jpeg, png, atau webp.', [], 422);
    }

    // Validate actual MIME type from file contents (not browser-supplied Content-Type)
    $tmpPath = $_FILES[$inputName]['tmp_name'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmpPath);
    if (!in_array($mime, $allowedMime, true)) {
        json_response(false, 'Konten file tidak sesuai dengan format yang diizinkan (jpg, png, webp).', [], 422);
    }

    $targetDir = dirname(__DIR__) . '/' . trim($relativeDir, '/');
    if (!is_dir($targetDir) && !@mkdir($targetDir, 0775, true)) {
        json_response(false, 'Folder upload tidak bisa dibuat. Periksa permission folder assets.', [], 500);
    }
    if (!is_writable($targetDir)) {
        json_response(false, 'Folder upload tidak bisa ditulis oleh server. Periksa permission folder assets.', [], 500);
    }

    $name = date('YmdHis') . '-' . bin2hex(random_bytes(6)) . '.' . $ext;
    $target = $targetDir . '/' . $name;
    if (!move_uploaded_file($_FILES[$inputName]['tmp_name'], $target)) {
        json_response(false, 'File gagal disimpan.', [], 422);
    }

    return trim($relativeDir, '/') . '/' . $name;
}

function count_submitted_uploads(string $inputName): int
{
    if (empty($_FILES[$inputName])) {
        return 0;
    }

    $error = $_FILES[$inputName]['error'] ?? UPLOAD_ERR_NO_FILE;
    if (is_array($error)) {
        $count = 0;
        foreach ($error as $itemError) {
            if ($itemError !== UPLOAD_ERR_NO_FILE) {
                $count++;
            }
        }
        return $count;
    }

    return $error === UPLOAD_ERR_NO_FILE ? 0 : 1;
}

function require_booking_photo_total_range(int $bookingId, string $inputName, string $photoType, int $min = 1, int $max = 5): void
{
    $existing = existing_photo_count($bookingId, $photoType);
    $submitted = count_submitted_uploads($inputName);
    $total = $existing + $submitted;

    if ($total < $min || $total > $max) {
        json_response(false, "Foto {$photoType} wajib minimal {$min} dan maksimal {$max} file. Saat ini tersimpan {$existing} dan dipilih {$submitted}.", [], 422);
    }
}

function require_booking_access_for_action(int $bookingId, array $user): array
{
    $booking = booking_for_access($bookingId, $user);
    if (!$booking) {
        json_response(false, 'Booking tidak ditemukan atau tidak dapat diakses.', [], 404);
    }
    return $booking;
}

/**
 * Fetch estimate row joined with booking context. If $customerId is set,
 * also enforce customer ownership.
 *
 * @param int      $estimateId
 * @param int|null $customerId Optional customer-id to scope access.
 * @param string   $select     Extra columns to select from bookings (defaults to common).
 */
function require_estimate_access(int $estimateId, ?int $customerId = null, string $select = 'b.booking_code, b.customer_id'): array
{
    $estimate = db_first("SELECT e.*, {$select} FROM estimates e JOIN bookings b ON b.id = e.booking_id WHERE e.id = ?", [$estimateId]);
    if (!$estimate) {
        json_response(false, 'Estimasi tidak ditemukan.', [], 404);
    }
    if ($customerId !== null && (int) $estimate['customer_id'] !== $customerId) {
        json_response(false, 'Estimasi tidak ditemukan atau tidak dapat diakses.', [], 404);
    }
    return $estimate;
}

/**
 * Fetch final invoice row joined with booking context.
 *
 * @param int      $invoiceId
 * @param int|null $customerId Optional customer-id to scope access.
 */
function require_invoice_access(int $invoiceId, ?int $customerId = null): array
{
    $invoice = db_first('SELECT i.*, b.customer_id, b.booking_code FROM final_invoices i JOIN bookings b ON b.id = i.booking_id WHERE i.id = ?', [$invoiceId]);
    if (!$invoice) {
        json_response(false, 'Invoice tidak ditemukan.', [], 404);
    }
    if ($customerId !== null && (int) $invoice['customer_id'] !== $customerId) {
        json_response(false, 'Invoice tidak ditemukan atau tidak dapat diakses.', [], 404);
    }
    return $invoice;
}

/**
 * Fetch estimate revision row joined with booking context.
 *
 * @param int      $revisionId
 * @param int|null $customerId Optional customer-id to scope access.
 */
function require_revision_access(int $revisionId, ?int $customerId = null): array
{
    $revision = db_first('SELECT r.*, b.customer_id, b.booking_code FROM estimate_revisions r JOIN bookings b ON b.id = r.booking_id WHERE r.id = ?', [$revisionId]);
    if (!$revision) {
        json_response(false, 'Revisi tidak ditemukan.', [], 404);
    }
    if ($customerId !== null && (int) $revision['customer_id'] !== $customerId) {
        json_response(false, 'Revisi tidak ditemukan atau tidak dapat diakses.', [], 404);
    }
    return $revision;
}

function require_confirmed_action(): void
{
    if (($_POST['confirm_action'] ?? '') !== '1') {
        json_response(false, 'Konfirmasi aksi diperlukan.', [], 422);
    }
}

function require_status_value(string $actual, array $allowed, string $message): void
{
    if (!in_array($actual, $allowed, true)) {
        json_response(false, $message, [], 422);
    }
}

/**
 * Read and whitelist a master-data status field from POST.
 * Only 'active' and 'inactive' are accepted; defaults to 'active'.
 */
function post_master_status(string $default = 'active'): string
{
    $value = trim((string) ($_POST['status'] ?? $default));
    return in_array($value, ['active', 'inactive'], true) ? $value : 'active';
}

function unlink_safe_local_files(array $paths): void
{
    $projectRoot = realpath(dirname(__DIR__));
    if (!$projectRoot) {
        return;
    }
    $uploadRoot = realpath($projectRoot . '/assets/uploads/bookings');
    if (!$uploadRoot) {
        return;
    }
    foreach ($paths as $path) {
        $target = realpath($projectRoot . '/' . ltrim((string) $path, '/'));
        if ($target && str_starts_with($target, $uploadRoot) && is_file($target)) {
            @unlink($target);
        }
    }
}

function count_user_operational_links(int $userId): int
{
    $row = db_first(
        "SELECT
            (SELECT COUNT(*) FROM bookings WHERE customer_id=? OR assigned_pekerja_lapangan_id=? OR created_by=?) +
            (SELECT COUNT(*) FROM schedules WHERE pekerja_lapangan_id=? OR created_by=?) +
            (SELECT COUNT(*) FROM surveys WHERE pekerja_lapangan_id=?) +
            (SELECT COUNT(*) FROM estimates WHERE created_by=?) +
            (SELECT COUNT(*) FROM estimate_revisions WHERE created_by=?) +
            (SELECT COUNT(*) FROM work_progress WHERE pekerja_lapangan_id=?) +
            (SELECT COUNT(*) FROM final_invoices WHERE created_by=?) +
            (SELECT COUNT(*) FROM payments WHERE marked_by=?) AS total",
        [$userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId]
    );

    return (int) ($row['total'] ?? 0);
}

function invoice_has_survey_fee_item(int $invoiceId): bool
{
    return (bool) db_first(
        "SELECT id FROM final_invoice_items WHERE final_invoice_id = ? AND item_name = 'Survey Fee' LIMIT 1",
        [$invoiceId]
    );
}

function create_survey_fee_invoice(array $estimate, int $createdBy): int
{
    $existing = db_first(
        "SELECT i.id
         FROM final_invoices i
         JOIN final_invoice_items item ON item.final_invoice_id = i.id
         WHERE i.booking_id = ? AND item.item_name = 'Survey Fee'
         LIMIT 1",
        [(int) $estimate['booking_id']]
    );
    if ($existing) {
        return (int) $existing['id'];
    }

    $amount = (int) ($estimate['survey_fee_amount'] ?? 100000);
    $code = next_code('final_invoices', 'invoice_code', 'INV-', true);
    $invoiceId = (int) db_insert(
        'INSERT INTO final_invoices (invoice_code, booking_id, estimate_id, created_by, subtotal_estimate, revision_total, final_total, work_days_actual, worker_count_actual, notes, payment_status, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$code, (int) $estimate['booking_id'], (int) $estimate['id'], $createdBy, 0, 0, $amount, 1, 1, 'Invoice survey fee wajib karena estimasi ditolak customer.', 'Belum Dibayar', 'Dikirim']
    );
    db_insert(
        'INSERT INTO final_invoice_items (final_invoice_id, item_type, item_name, description, quantity, unit, unit_price, subtotal) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        [$invoiceId, 'additional', 'Survey Fee', 'Biaya survey wajib setelah estimasi ditolak customer.', 1, 'tagihan', $amount, $amount]
    );

    return $invoiceId;
}

try {
    if ($action === 'mark_notifications_read') {
        db_execute('UPDATE notifications SET is_read = 1 WHERE user_id = ?', [$user['id']]);
        json_response(true, 'Semua notifikasi ditandai sudah dibaca.');
    }

    if ($action === 'create_booking') {
        require_roles(['customer']);
        $serviceId = post_int('service_id');
        $isCustom = isset($_POST['is_custom_service']) ? 1 : 0;
        $custom = post_trim('custom_service_description');
        $surveyDate = post_trim('requested_survey_date');
        $address = post_trim('location_address');
        $area = post_float('estimated_area');
        $notes = post_trim('customer_notes');

        if (!$serviceId && !$isCustom) {
            json_response(false, 'Pilih layanan atau gunakan custom layanan.', [], 422);
        }
        if ($isCustom && $custom === '') {
            json_response(false, 'Deskripsi kebutuhan wajib diisi untuk layanan custom.', [], 422);
        }

        // Validate service integrity (server-side, do not trust hidden fields).
        if (!$isCustom) {
            $service = db_first("SELECT id FROM services WHERE id = ? AND status = 'active'", [$serviceId]);
            if (!$service) {
                json_response(false, 'Layanan tidak ditemukan atau tidak aktif.', [], 422);
            }
        }
        require_value($surveyDate, 'Tanggal survey yang diinginkan wajib diisi.');
        require_value($address, 'Alamat lokasi wajib diisi.');
        if (empty($_POST['survey_agreement'])) {
            json_response(false, 'Persetujuan estimasi final setelah survey wajib dicentang.', [], 422);
        }

        $code = next_code('bookings', 'booking_code', 'BK-', true);
        $bookingId = (int) db_insert(
            'INSERT INTO bookings (booking_code, customer_id, service_id, is_custom_service, custom_service_description, requested_survey_date, location_address, estimated_area, customer_notes, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$code, $user['id'], $serviceId ?: null, $isCustom, $custom ?: null, $surveyDate, $address, $area > 0 ? $area : null, $notes, 'Menunggu Konfirmasi', $user['id']]
        );

        $reference = save_single_upload('reference_photo', "assets/uploads/bookings/{$code}/reference");
        if ($reference) {
            db_execute('UPDATE bookings SET reference_photo = ? WHERE id = ?', [$reference, $bookingId]);
        }

        record_booking_status_history($bookingId, null, 'Menunggu Konfirmasi', 'Booking dibuat customer.', (int) $user['id']);
        log_activity('Booking dibuat', "{$user['name']} membuat booking {$code}.", 'booking', $bookingId);
        notify_role('staff', 'Booking baru masuk', "{$code} menunggu konfirmasi staff.", 'booking', $bookingId);

        json_response(true, 'Booking berhasil dibuat dan menunggu konfirmasi staff.', ['redirect' => 'dashboard.php?page=booking_detail&id=' . $bookingId]);
    }

    if ($action === 'accept_booking') {
        require_roles(['staff']);
        json_response(false, 'Penerimaan booking dilakukan lewat form Terima & Jadwalkan Survey agar pekerja lapangan langsung ditugaskan.', [], 422);
    }

    if ($action === 'reject_booking') {
        require_roles(['staff']);
        $bookingId = post_int('booking_id');
        $booking = db_first('SELECT status FROM bookings WHERE id = ?', [$bookingId]);
        if (!$booking) {
            json_response(false, 'Booking tidak ditemukan.', [], 404);
        }
        require_status_value($booking['status'], ['Menunggu Konfirmasi'], 'Booking hanya bisa ditolak saat masih menunggu konfirmasi.');
        $reason = post_trim('reason', 'Booking ditolak staff.');
        update_booking_status($bookingId, 'Dibatalkan', $reason, (int) $user['id']);
        log_activity('Booking ditolak', $reason, 'booking', $bookingId);
        json_response(true, 'Booking ditolak dan dibatalkan.');
    }

    if ($action === 'schedule_survey') {
        require_roles(['staff']);
        $bookingId = post_int('booking_id');
        $pekerjaLapanganId = post_int('pekerja_lapangan_id');
        $date = post_trim('schedule_date');
        $time = post_trim('schedule_time');
        $notes = post_trim('notes');
        if (!$bookingId || !$pekerjaLapanganId || !$date || !$time) {
            json_response(false, 'Booking, pekerja lapangan, tanggal, dan jam survey wajib diisi.', [], 422);
        }

        $booking = db_first('SELECT * FROM bookings WHERE id = ?', [$bookingId]);
        if (!$booking) {
            json_response(false, 'Booking tidak ditemukan.', [], 404);
        }
        require_status_value($booking['status'], ['Menunggu Konfirmasi', 'Survey Dijadwalkan', 'Survey Berlangsung'], 'Survey hanya bisa dijadwalkan dari booking baru atau status survey.');
        $worker = db_first("SELECT id FROM users WHERE id = ? AND role = 'pekerja_lapangan' AND status = 'active'", [$pekerjaLapanganId]);
        if (!$worker) {
            json_response(false, 'Pekerja Lapangan tidak valid atau tidak aktif.', [], 422);
        }

        $conflict = db_first(
            "SELECT id FROM schedules WHERE pekerja_lapangan_id = ? AND schedule_date = ? AND schedule_time = ? AND booking_id <> ? AND status NOT IN ('Selesai','Dibatalkan')",
            [$pekerjaLapanganId, $date, $time, $bookingId]
        );
        if ($conflict) {
            json_response(false, 'Pekerja Lapangan sudah memiliki jadwal pada waktu tersebut. Pilih jadwal lain.', [], 422);
        }

        $existingSchedule = db_first("SELECT * FROM schedules WHERE booking_id = ? AND schedule_type = 'Survey' ORDER BY id DESC LIMIT 1", [$bookingId]);
        if ($existingSchedule) {
            $rescheduled = (int) $existingSchedule['pekerja_lapangan_id'] !== $pekerjaLapanganId
                || $existingSchedule['schedule_date'] !== $date
                || substr((string) $existingSchedule['schedule_time'], 0, 5) !== substr($time, 0, 5);
            db_execute(
                'UPDATE schedules SET pekerja_lapangan_id=?, schedule_date=?, schedule_time=?, location=?, status=?, notes=?, reschedule_reason=? WHERE id=?',
                [$pekerjaLapanganId, $date, $time, $booking['location_address'], $rescheduled ? 'Dijadwalkan Ulang' : 'Terjadwal', $notes, $rescheduled ? ($notes ?: 'Reschedule survey oleh staff.') : null, $existingSchedule['id']]
            );
        } else {
            $code = next_code('schedules', 'schedule_code', 'SCH-', true);
            db_insert(
                'INSERT INTO schedules (schedule_code, booking_id, pekerja_lapangan_id, schedule_type, schedule_date, schedule_time, location, status, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$code, $bookingId, $pekerjaLapanganId, 'Survey', $date, $time, $booking['location_address'], 'Terjadwal', $notes, $user['id']]
            );
        }
        db_execute(
            'UPDATE bookings SET assigned_pekerja_lapangan_id = ?, actual_survey_date = ?, actual_survey_time = ? WHERE id = ?',
            [$pekerjaLapanganId, $date, $time, $bookingId]
        );
        update_booking_status($bookingId, 'Survey Dijadwalkan', 'Survey dijadwalkan dan pekerja lapangan ditugaskan.', (int) $user['id']);

        create_notification((int) $booking['customer_id'], 'customer', 'Survey dijadwalkan', "Survey booking {$booking['booking_code']} dijadwalkan pada {$date} {$time}.", 'booking', $bookingId);
        create_notification($pekerjaLapanganId, 'pekerja_lapangan', 'Survey baru ditugaskan', "Anda ditugaskan survey {$booking['booking_code']}.", 'booking', $bookingId);
        log_activity('Survey dijadwalkan', "Survey {$booking['booking_code']} dijadwalkan ke pekerja lapangan.", 'booking', $bookingId);
        json_response(true, 'Survey berhasil dijadwalkan dan pekerja lapangan ditugaskan.');
    }

    if ($action === 'start_survey') {
        require_roles(['pekerja_lapangan']);
        $bookingId = post_int('booking_id');
        $booking = require_booking_access_for_action($bookingId, $user);
        require_status_value($booking['status'], ['Survey Dijadwalkan'], 'Survey hanya bisa dimulai dari status Survey Dijadwalkan.');
        update_booking_status($bookingId, 'Survey Berlangsung', 'Pekerja Lapangan memulai survey.', (int) $user['id']);
        db_execute("UPDATE schedules SET status = 'Berlangsung' WHERE booking_id = ? AND pekerja_lapangan_id = ? AND schedule_type = 'Survey'", [$bookingId, $user['id']]);
        log_activity('Survey dimulai', "Survey {$booking['booking_code']} dimulai pekerja lapangan.", 'booking', $bookingId);
        json_response(true, 'Survey dimulai.');
    }

    if ($action === 'complete_survey') {
        require_roles(['pekerja_lapangan']);
        $bookingId = post_int('booking_id');
        $booking = require_booking_access_for_action($bookingId, $user);
        require_status_value($booking['status'], ['Survey Berlangsung'], 'Survey hanya bisa diselesaikan setelah dimulai.');
        $area = post_float('actual_area');
        $condition = post_trim('location_condition');
        $access = post_trim('access_condition');
        $obstacle = post_trim('obstacle_notes');
        $days = max(1, post_int('estimated_work_days', 1));
        $workers = max(1, post_int('estimated_worker_count', 1));
        $notes = post_trim('survey_notes');

        if ($area <= 0 || $condition === '' || $access === '') {
            json_response(false, 'Luas aktual, kondisi lokasi, dan akses lokasi wajib diisi.', [], 422);
        }

        require_booking_photo_total_range($bookingId, 'before_photos', 'before');

        $surveyId = (int) db_insert(
            'INSERT INTO surveys (booking_id, pekerja_lapangan_id, actual_area, location_condition, access_condition, obstacle_notes, estimated_work_days, estimated_worker_count, survey_notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$bookingId, $user['id'], $area, $condition, $access, $obstacle, $days, $workers, $notes]
        );

        $saved = upload_booking_files('before_photos', $booking['booking_code'], 'before', $surveyId, $bookingId);

        db_execute('UPDATE bookings SET actual_area = ? WHERE id = ?', [$area, $bookingId]);
        db_execute("UPDATE schedules SET status = 'Selesai' WHERE booking_id = ? AND pekerja_lapangan_id = ? AND schedule_type = 'Survey'", [$bookingId, $user['id']]);
        update_booking_status($bookingId, 'Survey Selesai', 'Survey selesai dan hasil survey tersimpan.', (int) $user['id']);
        log_activity('Survey selesai', "Pekerja Lapangan menyelesaikan survey {$booking['booking_code']} dengan {$saved} foto before.", 'booking', $bookingId);
        json_response(true, 'Survey selesai dan foto before tersimpan.');
    }

    if ($action === 'create_estimate') {
        require_roles(['pekerja_lapangan']);
        $bookingId = post_int('booking_id');
        $booking   = require_booking_access_for_action($bookingId, $user);
        require_status_value($booking['status'], ['Survey Selesai'], 'Estimasi hanya bisa dibuat setelah survey selesai.');
        $survey = db_first('SELECT * FROM surveys WHERE booking_id = ? ORDER BY id DESC LIMIT 1', [$bookingId]);
        if (!$survey) {
            json_response(false, 'Survey harus diselesaikan sebelum membuat estimasi.', [], 422);
        }

        $area         = post_float('area_size') ?: (float) $survey['actual_area'];
        $workerCount  = max(1, post_int('worker_count', (int) $survey['estimated_worker_count']));
        $blockCount   = calculate_block_count($area);
        $estimatedDays= max(1, post_int('estimated_days', (int) $survey['estimated_work_days']));

        $materialIds = array_values(array_unique(array_filter(
            array_map('intval', (array) ($_POST['material_id'] ?? [])),
            static fn (int $id): bool => $id > 0
        )));
        if (!$materialIds) {
            json_response(false, 'Pilih minimal satu material yang digunakan.', [], 422);
        }
        $materialPlaceholders = implode(',', array_fill(0, count($materialIds), '?'));
        $materials = db_query(
            "SELECT * FROM materials WHERE status='active' AND id IN ({$materialPlaceholders}) ORDER BY name",
            $materialIds
        );
        if (count($materials) !== count($materialIds)) {
            json_response(false, 'Pilihan material tidak valid atau sudah dinonaktifkan.', [], 422);
        }
        $materialItems = [];
        foreach ($materials as $material) {
            $materialId = (int) $material['id'];
            $qty = (float) ($_POST['material_qty'][$materialId] ?? 0);
            $price = (int) ($_POST['material_price'][$materialId] ?? $material['price_per_unit']);
            if ($qty <= 0 || $price < 0) {
                json_response(false, 'Quantity material wajib lebih dari 0 dan harga tidak boleh negatif.', [], 422);
            }
            $materialItems[] = [
                'name' => $material['name'],
                'quantity' => $qty,
                'unit' => $material['unit'],
                'unit_price' => $price,
            ];
        }
        $materialTotal = calculate_additional_total($materialItems);

        $laborRateId = post_int('labor_rate_id');
        $laborRate = db_first("SELECT * FROM labor_rates WHERE id=? AND status='active'", [$laborRateId]);
        if (!$laborRate) {
            json_response(false, 'Pilih tarif tenaga kerja yang aktif.', [], 422);
        }
        $laborTotal = calculate_labor_total($workerCount, $estimatedDays, (int) $laborRate['daily_rate']);

        $additionalItems = [];
        $additionalIds = array_values(array_unique(array_filter(
            array_map('intval', (array) ($_POST['additional_cost_id'] ?? [])),
            static fn (int $id): bool => $id > 0
        )));
        if ($additionalIds) {
            $additionalPlaceholders = implode(',', array_fill(0, count($additionalIds), '?'));
            $masterAdditional = db_query(
                "SELECT * FROM additional_cost_types WHERE status='active' AND id IN ({$additionalPlaceholders}) ORDER BY name",
                $additionalIds
            );
            if (count($masterAdditional) !== count($additionalIds)) {
                json_response(false, 'Pilihan biaya tambahan tidak valid atau sudah dinonaktifkan.', [], 422);
            }
            foreach ($masterAdditional as $additional) {
                $additionalId = (int) $additional['id'];
                $qty = (float) ($_POST['additional_qty'][$additionalId] ?? 1);
                $price = (int) ($_POST['additional_price'][$additionalId] ?? $additional['default_price']);
                if ($qty <= 0 || $price < 0) {
                    json_response(false, 'Quantity biaya tambahan wajib lebih dari 0 dan harga tidak boleh negatif.', [], 422);
                }
                $additionalItems[] = [
                    'name' => $additional['name'],
                    'quantity' => $qty,
                    'unit' => $additional['unit'],
                    'unit_price' => $price,
                ];
            }
        }
        $customAdditionalName = post_trim('custom_additional_name');
        if ($customAdditionalName !== '') {
            $additionalItems[] = [
                'name' => $customAdditionalName,
                'quantity' => max(0.01, post_float('custom_additional_qty', 1)),
                'unit' => post_trim('custom_additional_unit', 'item') ?: 'item',
                'unit_price' => max(0, post_int('custom_additional_price')),
            ];
        }
        $additionalTotal = calculate_additional_total($additionalItems);
        $grandTotal      = calculate_grand_total($materialTotal, $laborTotal, $additionalTotal);
        $code            = next_code('estimates', 'estimate_code', 'EST-', true);

        $estimateId = (int) db_insert(
            'INSERT INTO estimates (estimate_code, booking_id, survey_id, created_by, area_size, block_count, material_total, labor_total, additional_total, grand_total, estimated_days, worker_count, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$code, $bookingId, $survey['id'], $user['id'], $area, $blockCount, $materialTotal, $laborTotal, $additionalTotal, $grandTotal, $estimatedDays, $workerCount, 'Draft']
        );

        foreach ($materialItems as $material) {
            $subtotal = (int) round($material['quantity'] * $material['unit_price']);
            db_insert(
                'INSERT INTO estimate_items (estimate_id, item_type, item_name, description, quantity, unit, unit_price, subtotal) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$estimateId, 'material', $material['name'], 'Material terpilih dari hasil survei.', $material['quantity'], $material['unit'], $material['unit_price'], $subtotal]
            );
        }
        db_insert(
            'INSERT INTO estimate_items (estimate_id, item_type, item_name, description, quantity, unit, unit_price, subtotal) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$estimateId, 'labor', $laborRate['name'], "{$workerCount} pekerja × {$estimatedDays} hari.", $workerCount * $estimatedDays, 'orang-hari', $laborRate['daily_rate'], $laborTotal]
        );
        foreach ($additionalItems as $item) {
            db_insert(
                'INSERT INTO estimate_items (estimate_id, item_type, item_name, description, quantity, unit, unit_price, subtotal) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$estimateId, 'additional', $item['name'], 'Biaya tambahan lapangan.', $item['quantity'], $item['unit'], $item['unit_price'], (int) round($item['quantity'] * $item['unit_price'])]
            );
        }

        update_booking_status($bookingId, 'Estimasi Dibuat', 'Pekerja Lapangan menyimpan draft estimasi untuk direview Staff.', (int) $user['id']);
        notify_role('staff', 'Estimasi perlu direview', "Draft estimasi {$code} untuk {$booking['booking_code']} menunggu review.", 'estimate', $estimateId);
        log_activity('Draft estimasi dibuat', "Pekerja Lapangan membuat draft estimasi {$code}.", 'estimate', $estimateId);
        json_response(true, 'Draft estimasi disimpan dan dikirim ke antrean review Staff.', [
            'redirect' => 'dashboard.php?page=booking_detail&id=' . $bookingId . '&section=estimate',
        ]);
    }

    if ($action === 'send_estimate') {
        require_roles(['staff']);
        $estimateId = post_int('estimate_id');
        $estimate = require_estimate_access($estimateId);
        require_status_value($estimate['status'], ['Draft'], 'Hanya estimasi draft yang bisa dikirim ke customer.');
        db_execute("UPDATE estimates SET status='Dikirim', sent_at=NOW() WHERE id=?", [$estimateId]);
        update_booking_status((int) $estimate['booking_id'], 'Menunggu Persetujuan Customer', 'Staff mengirim estimasi ke customer.', (int) $user['id']);
        create_notification((int) $estimate['customer_id'], 'customer', 'Estimasi menunggu keputusan', "Estimasi {$estimate['estimate_code']} untuk {$estimate['booking_code']} sudah dikirim.", 'estimate', $estimateId);
        log_activity('Estimasi dikirim', "Staff mengirim estimasi {$estimate['estimate_code']} ke customer.", 'estimate', $estimateId);
        json_response(true, 'Estimasi dikirim ke customer.');
    }

    if ($action === 'approve_estimate' || $action === 'reject_estimate') {
        require_roles(['customer']);
        $estimateId = post_int('estimate_id');
        $note = post_trim('customer_response_note');
        $estimate = require_estimate_access($estimateId, (int) $user['id'], 'b.customer_id, b.booking_code, b.assigned_pekerja_lapangan_id, b.survey_fee_amount');
        require_status_value($estimate['status'], ['Dikirim'], 'Estimasi hanya bisa diputuskan setelah dikirim staff.');
        if ($action === 'approve_estimate') {
            db_execute("UPDATE estimates SET status='Disetujui', customer_response_note=?, approved_at=NOW() WHERE id=?", [$note, $estimateId]);
            update_booking_status((int) $estimate['booking_id'], 'Estimasi Disetujui', 'Customer menyetujui estimasi.', (int) $user['id']);
            if ($estimate['assigned_pekerja_lapangan_id']) {
                create_notification((int) $estimate['assigned_pekerja_lapangan_id'], 'pekerja_lapangan', 'Estimasi disetujui', "{$estimate['booking_code']} siap masuk pengerjaan.", 'booking', (int) $estimate['booking_id']);
            }
            log_activity('Estimasi disetujui', "Customer menyetujui estimasi {$estimate['estimate_code']}.", 'estimate', $estimateId);
            json_response(true, 'Estimasi disetujui. Staff atau pekerja lapangan dapat memulai pekerjaan.');
        }

        db_execute("UPDATE estimates SET status='Ditolak', customer_response_note=?, rejected_at=NOW() WHERE id=?", [$note, $estimateId]);
        db_execute("UPDATE bookings SET survey_fee_payment_status='Belum Dibayar' WHERE id=?", [$estimate['booking_id']]);
        update_booking_status((int) $estimate['booking_id'], 'Estimasi Ditolak', 'Customer menolak estimasi dan survey fee perlu dibayar.', (int) $user['id']);
        $invoiceId = create_survey_fee_invoice($estimate, (int) $user['id']);
        update_booking_status((int) $estimate['booking_id'], 'Menunggu Pembayaran', 'Invoice survey fee wajib dibuat setelah estimasi ditolak customer.', (int) $user['id']);
        notify_role('staff', 'Estimasi ditolak', "{$estimate['booking_code']} ditolak customer. Invoice survey fee wajib menunggu pembayaran.", 'invoice', $invoiceId);
        create_notification((int) $estimate['customer_id'], 'customer', 'Invoice survey fee wajib dibayar', "Invoice survey fee {$estimate['booking_code']} sebesar " . format_rupiah((int) $estimate['survey_fee_amount']) . ' sudah tersedia.', 'invoice', $invoiceId);
        log_activity('Estimasi ditolak', "Customer menolak estimasi {$estimate['estimate_code']}.", 'estimate', $estimateId);
        json_response(true, 'Estimasi ditolak. Invoice survey fee ' . format_rupiah((int) $estimate['survey_fee_amount']) . ' wajib dibayar.', ['redirect' => 'dashboard.php?page=invoice_detail&id=' . $invoiceId]);
    }

    if ($action === 'start_work') {
        require_roles(['staff', 'pekerja_lapangan']);
        $bookingId = post_int('booking_id');
        $booking = require_booking_access_for_action($bookingId, $user);
        require_status_value($booking['status'], ['Estimasi Disetujui'], 'Pekerjaan hanya bisa dimulai setelah estimasi disetujui customer.');
        update_booking_status($bookingId, 'Dalam Pengerjaan', 'Pekerjaan dimulai.', (int) $user['id']);
        $booking = db_first('SELECT booking_code, customer_id FROM bookings WHERE id=?', [$bookingId]);
        log_activity('Pekerjaan dimulai', "Pekerjaan {$booking['booking_code']} dimulai.", 'booking', $bookingId);
        json_response(true, 'Pekerjaan dimulai.');
    }

    if ($action === 'update_progress') {
        require_roles(['pekerja_lapangan']);
        $bookingId = post_int('booking_id');
        $booking = require_booking_access_for_action($bookingId, $user);
        require_status_value($booking['status'], ['Dalam Pengerjaan', 'Revisi Estimasi'], 'Progress hanya bisa diupdate saat pekerjaan berjalan.');
        $progress = post_int('progress_percent');
        $title = post_trim('title');
        $note = post_trim('note');
        $obstacle = post_trim('obstacle_note');
        if ($progress < 0 || $progress > 100 || $title === '') {
            json_response(false, 'Progress harus 0-100 dan judul wajib diisi.', [], 422);
        }
        require_booking_photo_total_range($bookingId, 'progress_photos', 'progress', 0, 5);
        $savedPhotos = upload_booking_files('progress_photos', $booking['booking_code'], 'progress', null, $bookingId);
        db_insert(
            'INSERT INTO work_progress (booking_id, pekerja_lapangan_id, progress_percent, title, note, obstacle_note) VALUES (?, ?, ?, ?, ?, ?)',
            [$bookingId, $user['id'], $progress, $title, $note, $obstacle]
        );
        log_activity('Progress diupdate', "Progress pekerjaan diperbarui menjadi {$progress}% dengan {$savedPhotos} foto progress.", 'booking', $bookingId);
        json_response(true, $savedPhotos > 0 ? 'Progress pekerjaan dan foto progress berhasil disimpan.' : 'Progress pekerjaan berhasil disimpan.');
    }

    if ($action === 'create_revision') {
        require_roles(['pekerja_lapangan']);
        $bookingId = post_int('booking_id');
        $booking = require_booking_access_for_action($bookingId, $user);
        require_status_value($booking['status'], ['Dalam Pengerjaan', 'Revisi Estimasi'], 'Revisi estimasi hanya bisa dibuat saat pekerjaan berjalan.');
        $estimate = db_first("SELECT * FROM estimates WHERE booking_id=? AND status IN ('Disetujui','Direvisi') ORDER BY id DESC LIMIT 1", [$bookingId]);
        if (!$estimate) {
            json_response(false, 'Estimasi utama yang disetujui belum tersedia.', [], 422);
        }
        $reason = post_trim('reason');
        require_value($reason, 'Alasan revisi wajib diisi.');
        $items = [];
        foreach ($_POST['item_name'] ?? [] as $idx => $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }
            $qty = (float) (($_POST['quantity'][$idx] ?? 1) ?: 1);
            $price = (int) (($_POST['unit_price'][$idx] ?? 0) ?: 0);
            if ($qty < 0 || $price < 0) {
                json_response(false, 'Quantity dan harga revisi tidak boleh negatif.', [], 422);
            }
            $items[] = [
                'name' => $name,
                'description' => trim((string) ($_POST['description'][$idx] ?? '')),
                'qty' => $qty,
                'unit' => trim((string) ($_POST['unit'][$idx] ?? 'item')),
                'price' => $price,
                'subtotal' => (int) round($qty * $price),
            ];
        }
        if (!$items) {
            json_response(false, 'Minimal satu item revisi diperlukan.', [], 422);
        }
        $additional = array_sum(array_column($items, 'subtotal'));
        $newTotal = (int) $estimate['grand_total'] + $additional;
        $code = next_code('estimate_revisions', 'revision_code', 'REV-', true);
        $revisionId = (int) db_insert(
            'INSERT INTO estimate_revisions (revision_code, estimate_id, booking_id, created_by, reason, old_total, additional_total, new_total, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$code, $estimate['id'], $bookingId, $user['id'], $reason, $estimate['grand_total'], $additional, $newTotal, 'Draft']
        );
        foreach ($items as $item) {
            db_insert(
                'INSERT INTO estimate_revision_items (revision_id, item_name, description, quantity, unit, unit_price, subtotal) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$revisionId, $item['name'], $item['description'], $item['qty'], $item['unit'], $item['price'], $item['subtotal']]
            );
        }
        update_booking_status($bookingId, 'Revisi Estimasi', 'Pekerja Lapangan membuat revisi estimasi.', (int) $user['id']);
        $booking = db_first('SELECT booking_code, customer_id FROM bookings WHERE id=?', [$bookingId]);
        notify_role('staff', 'Revisi estimasi dibuat', "Revisi {$code} menunggu review.", 'revision', $revisionId);
        log_activity('Revisi estimasi dibuat', "Pekerja Lapangan membuat revisi {$code}.", 'revision', $revisionId);
        json_response(true, 'Revisi estimasi berhasil dibuat dan menunggu review staff.');
    }

    if ($action === 'send_revision') {
        require_roles(['staff']);
        $revisionId = post_int('revision_id');
        $revision = require_revision_access($revisionId);
        require_status_value($revision['status'], ['Draft'], 'Hanya revisi draft yang bisa dikirim ke customer.');
        db_execute("UPDATE estimate_revisions SET status='Dikirim' WHERE id=?", [$revisionId]);
        create_notification((int) $revision['customer_id'], 'customer', 'Revisi estimasi perlu keputusan', "Revisi {$revision['revision_code']} untuk {$revision['booking_code']} sudah dikirim.", 'revision', $revisionId);
        log_activity('Revisi estimasi dikirim', "Staff mengirim revisi {$revision['revision_code']} ke customer.", 'revision', $revisionId);
        json_response(true, 'Revisi estimasi dikirim ke customer.');
    }

    if ($action === 'approve_revision' || $action === 'reject_revision') {
        require_roles(['customer']);
        $revisionId = post_int('revision_id');
        $note = post_trim('customer_response_note');
        $revision = require_revision_access($revisionId, (int) $user['id']);
        require_status_value($revision['status'], ['Dikirim'], 'Revisi hanya bisa diputuskan setelah dikirim staff.');
        $status = $action === 'approve_revision' ? 'Disetujui' : 'Ditolak';
        db_execute('UPDATE estimate_revisions SET status=?, customer_response_note=? WHERE id=?', [$status, $note, $revisionId]);
        if ($status === 'Disetujui') {
            db_execute("UPDATE estimates SET status='Direvisi', grand_total=? WHERE id=?", [$revision['new_total'], $revision['estimate_id']]);
            update_booking_status((int) $revision['booking_id'], 'Dalam Pengerjaan', 'Customer menyetujui revisi estimasi.', (int) $user['id']);
        } else {
            update_booking_status((int) $revision['booking_id'], 'Dalam Pengerjaan', 'Customer menolak revisi estimasi.', (int) $user['id']);
        }
        notify_role('staff', "Revisi {$status}", "Customer memberi keputusan revisi {$revision['revision_code']}.", 'revision', $revisionId);
        log_activity("Revisi {$status}", "Customer memberi keputusan revisi {$revision['revision_code']}.", 'revision', $revisionId);
        json_response(true, "Revisi estimasi {$status}.");
    }

    if ($action === 'mark_work_done') {
        require_roles(['pekerja_lapangan']);
        $bookingId = post_int('booking_id');
        $booking = require_booking_access_for_action($bookingId, $user);
        require_status_value($booking['status'], ['Dalam Pengerjaan', 'Revisi Estimasi'], 'Pekerjaan hanya bisa ditandai selesai saat sedang berjalan.');
        update_booking_status($bookingId, 'Pekerjaan Selesai', 'Pekerja Lapangan menandai pekerjaan selesai.', (int) $user['id']);
        log_activity('Pekerjaan selesai', 'Pekerja Lapangan menandai pekerjaan selesai.', 'booking', $bookingId);
        json_response(true, 'Pekerjaan ditandai selesai.');
    }

    if ($action === 'create_invoice') {
        require_roles(['pekerja_lapangan']);
        $bookingId = post_int('booking_id');
        $booking = require_booking_access_for_action($bookingId, $user);
        require_status_value($booking['status'], ['Pekerjaan Selesai'], 'Data final hanya bisa dikirim setelah pekerjaan selesai.');
        if (db_first('SELECT id FROM final_invoices WHERE booking_id=? LIMIT 1', [$bookingId])) {
            json_response(false, 'Data final pekerjaan untuk booking ini sudah dikirim.', [], 422);
        }
        $estimate = db_first("SELECT * FROM estimates WHERE booking_id=? AND status IN ('Disetujui','Direvisi') ORDER BY id DESC LIMIT 1", [$bookingId]);
        if (!$estimate) {
            json_response(false, 'Estimasi disetujui belum tersedia.', [], 422);
        }
        require_booking_photo_total_range($bookingId, 'after_photos', 'after');
        upload_booking_files('after_photos', $booking['booking_code'], 'after', null, $bookingId);
        $baseEstimateTotal = (int) (db_first('SELECT COALESCE(SUM(subtotal),0) AS total FROM estimate_items WHERE estimate_id=?', [$estimate['id']])['total'] ?? 0);
        if ($baseEstimateTotal <= 0) {
            $baseEstimateTotal = (int) $estimate['grand_total'];
        }
        $revisionTotal = (int) (db_first("SELECT COALESCE(SUM(additional_total),0) AS total FROM estimate_revisions WHERE booking_id=? AND status='Disetujui'", [$bookingId])['total'] ?? 0);
        $finalTotal = $baseEstimateTotal + $revisionTotal;
        $days = max(1, post_int('work_days_actual', (int) $estimate['estimated_days']));
        $workers = max(1, post_int('worker_count_actual', (int) $estimate['worker_count']));
        $notes = post_trim('notes');
        $code = next_code('final_invoices', 'invoice_code', 'INV-', true);
        $invoiceId = (int) db_insert(
            'INSERT INTO final_invoices (invoice_code, booking_id, estimate_id, created_by, subtotal_estimate, revision_total, final_total, work_days_actual, worker_count_actual, notes, payment_status, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$code, $bookingId, $estimate['id'], $user['id'], $baseEstimateTotal, $revisionTotal, $finalTotal, $days, $workers, $notes, 'Belum Dibayar', 'Draft']
        );
        $items = db_query('SELECT * FROM estimate_items WHERE estimate_id=? ORDER BY id', [$estimate['id']]);
        foreach ($items as $item) {
            db_insert(
                'INSERT INTO final_invoice_items (final_invoice_id, item_type, item_name, description, quantity, unit, unit_price, subtotal) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$invoiceId, $item['item_type'], $item['item_name'], $item['description'], $item['quantity'], $item['unit'], $item['unit_price'], $item['subtotal']]
            );
        }
        $revisionItems = db_query(
            "SELECT ri.*, r.revision_code
             FROM estimate_revision_items ri
             JOIN estimate_revisions r ON r.id=ri.revision_id
             WHERE r.booking_id=? AND r.status='Disetujui'
             ORDER BY r.id, ri.id",
            [$bookingId]
        );
        foreach ($revisionItems as $item) {
            db_insert(
                'INSERT INTO final_invoice_items (final_invoice_id, item_type, item_name, description, quantity, unit, unit_price, subtotal) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [$invoiceId, 'revision', $item['item_name'], trim(($item['revision_code'] ?? 'Revisi') . ' - ' . ($item['description'] ?: 'Item revisi estimasi')), $item['quantity'], $item['unit'], $item['unit_price'], $item['subtotal']]
            );
        }
        update_booking_status($bookingId, 'Invoice Final Dibuat', 'Pekerja Lapangan mengirim data final pekerjaan.', (int) $user['id']);
        notify_role('staff', 'Invoice final dibuat', "Invoice {$code} menunggu review staff.", 'invoice', $invoiceId);
        log_activity('Invoice final dibuat', "Pekerja Lapangan membuat invoice {$code}.", 'invoice', $invoiceId);
        json_response(true, 'Data final pekerjaan berhasil dikirim ke staff.');
    }

    if ($action === 'save_invoice') {
        require_roles(['staff']);
        $invoiceId = post_int('invoice_id');
        $invoice   = require_invoice_access($invoiceId);
        require_status_value($invoice['status'], ['Draft'], 'Hanya invoice draft yang bisa diedit.');

        $workDaysActual    = max(1, (int) ($_POST['work_days_actual'] ?? $invoice['work_days_actual']));
        $workerCountActual = max(1, (int) ($_POST['worker_count_actual'] ?? $invoice['worker_count_actual']));
        $notes             = trim($_POST['notes'] ?? '');

        // Update item prices jika dikirim
        $itemIds    = $_POST['item_id']    ?? [];
        $unitPrices = $_POST['unit_price'] ?? [];
        $quantities = $_POST['quantity']   ?? [];

        $subtotalEstimate = 0;
        foreach ($itemIds as $idx => $itemId) {
            $itemId    = (int) $itemId;
            $unitPrice = max(0, (float) ($unitPrices[$idx] ?? 0));
            $qty       = max(0, (float) ($quantities[$idx] ?? 1));
            $subtotal  = $unitPrice * $qty;
            db_execute(
                'UPDATE final_invoice_items SET unit_price=?, quantity=?, subtotal=? WHERE id=? AND final_invoice_id=?',
                [$unitPrice, $qty, $subtotal, $itemId, $invoiceId]
            );
        }

        // Hitung ulang total
        $items           = db_query('SELECT * FROM final_invoice_items WHERE final_invoice_id=?', [$invoiceId]);
        $subtotalEstimate= 0; $revisionTotal = 0;
        foreach ($items as $item) {
            if (in_array($item['item_type'], ['material', 'labor', 'additional'], true)) {
                $subtotalEstimate += (float) $item['subtotal'];
            } else {
                $revisionTotal += (float) $item['subtotal'];
            }
        }
        $finalTotal = $subtotalEstimate + $revisionTotal;

        db_execute(
            'UPDATE final_invoices SET work_days_actual=?, worker_count_actual=?, notes=?, subtotal_estimate=?, revision_total=?, final_total=? WHERE id=?',
            [$workDaysActual, $workerCountActual, $notes, $subtotalEstimate, $revisionTotal, $finalTotal, $invoiceId]
        );

        log_activity('Invoice diedit', "Staff mengedit invoice {$invoice['invoice_code']}.", 'invoice', $invoiceId);
        json_response(true, 'Invoice berhasil disimpan.');
    }

    if ($action === 'send_invoice') {
        require_roles(['staff']);
        $invoiceId = post_int('invoice_id');
        $invoice = require_invoice_access($invoiceId);
        require_status_value($invoice['status'], ['Draft'], 'Hanya invoice draft yang bisa dikirim ke customer.');
        db_execute("UPDATE final_invoices SET status='Dikirim' WHERE id=?", [$invoiceId]);
        update_booking_status((int) $invoice['booking_id'], 'Menunggu Pembayaran', 'Staff mengirim invoice final dan menunggu pembayaran.', (int) $user['id']);
        create_notification((int) $invoice['customer_id'], 'customer', 'Invoice menunggu pembayaran', "Invoice {$invoice['invoice_code']} siap dibayar di luar sistem.", 'invoice', $invoiceId);
        log_activity('Invoice dikirim', "Staff mengirim invoice {$invoice['invoice_code']}.", 'invoice', $invoiceId);
        json_response(true, 'Invoice dikirim dan status menunggu pembayaran.');
    }

    if ($action === 'mark_invoice_paid') {
        require_roles(['staff']);
        $invoiceId = post_int('invoice_id');
        $invoice = require_invoice_access($invoiceId);
        if ($invoice['payment_status'] === 'Lunas') {
            json_response(true, 'Invoice final sudah berstatus lunas.');
        }
        require_status_value($invoice['status'], ['Dikirim'], 'Invoice hanya bisa ditandai lunas setelah dikirim ke customer.');
        $isSurveyFeeInvoice = invoice_has_survey_fee_item($invoiceId);
        db_execute("UPDATE final_invoices SET status='Lunas', payment_status='Lunas' WHERE id=?", [$invoiceId]);
        if ($isSurveyFeeInvoice) {
            db_execute("UPDATE bookings SET survey_fee_payment_status='Sudah Dibayar' WHERE id=?", [$invoice['booking_id']]);
        }
        $paymentType = $isSurveyFeeInvoice ? 'Survey Fee' : 'Invoice Final';
        $existingPayment = db_first("SELECT id FROM payments WHERE invoice_id=? AND payment_status='Lunas' LIMIT 1", [$invoiceId]);
        if (!$existingPayment) {
            db_insert(
                'INSERT INTO payments (booking_id, invoice_id, payment_type, amount, payment_status, marked_by, paid_at, note) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)',
                [$invoice['booking_id'], $invoiceId, $paymentType, $invoice['final_total'], 'Lunas', $user['id'], post_trim('note')]
            );
        }
        update_booking_status((int) $invoice['booking_id'], 'Lunas', 'Staff menandai invoice final lunas.', (int) $user['id']);
        log_activity('Pembayaran ditandai', "Staff menandai invoice {$invoice['invoice_code']} lunas.", 'invoice', $invoiceId);
        json_response(true, 'Invoice final ditandai lunas.');
    }

    if ($action === 'mark_survey_fee_paid') {
        require_roles(['staff']);
        $bookingId = post_int('booking_id');
        $booking = db_first('SELECT * FROM bookings WHERE id=?', [$bookingId]);
        if (!$booking) {
            json_response(false, 'Booking tidak ditemukan.', [], 404);
        }
        $feeInvoice = db_first(
            "SELECT i.*
             FROM final_invoices i
             JOIN final_invoice_items item ON item.final_invoice_id=i.id
             WHERE i.booking_id=? AND item.item_name='Survey Fee'
             LIMIT 1",
            [$bookingId]
        );
        if ($feeInvoice) {
            if ($feeInvoice['payment_status'] === 'Lunas') {
                json_response(true, 'Invoice survey fee sudah berstatus lunas.');
            }
            db_execute("UPDATE final_invoices SET status='Lunas', payment_status='Lunas' WHERE id=?", [$feeInvoice['id']]);
            db_execute("UPDATE bookings SET survey_fee_payment_status='Sudah Dibayar' WHERE id=?", [$bookingId]);
            $existingPayment = db_first("SELECT id FROM payments WHERE invoice_id=? AND payment_status='Lunas' LIMIT 1", [$feeInvoice['id']]);
            if (!$existingPayment) {
                db_insert(
                    'INSERT INTO payments (booking_id, invoice_id, payment_type, amount, payment_status, marked_by, paid_at, note) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)',
                    [$bookingId, $feeInvoice['id'], 'Survey Fee', $feeInvoice['final_total'], 'Lunas', $user['id'], post_trim('note')]
                );
            }
            update_booking_status($bookingId, 'Lunas', 'Staff menandai invoice survey fee lunas.', (int) $user['id']);
            log_activity('Survey fee ditandai', "Staff menandai invoice survey fee {$booking['booking_code']} lunas.", 'invoice', (int) $feeInvoice['id']);
            json_response(true, 'Invoice survey fee ditandai sudah dibayar.');
        }
        require_status_value($booking['status'], ['Estimasi Ditolak', 'Menunggu Pembayaran'], 'Survey fee hanya ditandai lunas untuk estimasi yang ditolak customer.');
        if ($booking['survey_fee_payment_status'] === 'Sudah Dibayar') {
            json_response(true, 'Survey fee sudah berstatus lunas.');
        }
        db_execute("UPDATE bookings SET survey_fee_payment_status='Sudah Dibayar' WHERE id=?", [$bookingId]);
        $existingPayment = db_first("SELECT id FROM payments WHERE booking_id=? AND invoice_id IS NULL AND payment_type='Survey Fee' AND payment_status='Lunas' LIMIT 1", [$bookingId]);
        if (!$existingPayment) {
            db_insert(
                'INSERT INTO payments (booking_id, invoice_id, payment_type, amount, payment_status, marked_by, paid_at, note) VALUES (?, NULL, ?, ?, ?, ?, NOW(), ?)',
                [$bookingId, 'Survey Fee', $booking['survey_fee_amount'], 'Lunas', $user['id'], post_trim('note')]
            );
        }
        update_booking_status($bookingId, 'Lunas', 'Staff menandai survey fee lunas.', (int) $user['id']);
        log_activity('Survey fee ditandai', "Survey fee {$booking['booking_code']} ditandai sudah dibayar.", 'booking', $bookingId);
        json_response(true, 'Survey fee ditandai sudah dibayar.');
    }

    if ($action === 'upload_payment_proof') {
        require_roles(['customer']);
        $invoiceId = post_int('invoice_id');
        $bookingId = post_int('booking_id');
        $proofPath = save_single_upload('payment_proof', 'assets/uploads/payment_proofs');
        if (!$proofPath) {
            json_response(false, 'File bukti pembayaran wajib diupload.', [], 422);
        }
        if ($invoiceId) {
            $invoice = require_invoice_access($invoiceId, (int) $user['id']);
            if ($invoice['payment_status'] === 'Lunas') {
                json_response(false, 'Invoice sudah lunas, tidak perlu upload bukti.', [], 422);
            }
            $existing = db_first("SELECT id FROM payments WHERE invoice_id=? AND payment_status='Belum Dibayar' ORDER BY created_at DESC LIMIT 1", [$invoiceId]);
            if ($existing) {
                db_execute('UPDATE payments SET payment_proof=? WHERE id=?', [$proofPath, $existing['id']]);
            } else {
                db_insert(
                    'INSERT INTO payments (booking_id, invoice_id, payment_type, amount, payment_status, marked_by, note, payment_proof) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                    [$invoice['booking_id'], $invoiceId, 'Invoice Final', $invoice['final_total'], 'Belum Dibayar', $user['id'], post_trim('note'), $proofPath]
                );
            }
            notify_role('staff', 'Bukti bayar dikirim', "Customer mengirim bukti pembayaran invoice {$invoice['invoice_code']}.", 'payment', $invoiceId);
            log_activity('Upload bukti bayar', "Customer {$user['name']} upload bukti pembayaran invoice ID {$invoiceId}.", 'invoice', $invoiceId);
            json_response(true, 'Bukti pembayaran berhasil dikirim. Menunggu konfirmasi staff.');
        }
        if ($bookingId) {
            $booking = db_first('SELECT * FROM bookings WHERE id=? AND customer_id=?', [$bookingId, $user['id']]);
            if (!$booking) {
                json_response(false, 'Booking tidak ditemukan.', [], 404);
            }
            $existing = db_first("SELECT id FROM payments WHERE booking_id=? AND invoice_id IS NULL AND payment_type='Survey Fee' AND payment_status='Belum Dibayar' ORDER BY created_at DESC LIMIT 1", [$bookingId]);
            if ($existing) {
                db_execute('UPDATE payments SET payment_proof=? WHERE id=?', [$proofPath, $existing['id']]);
            } else {
                db_insert(
                    'INSERT INTO payments (booking_id, invoice_id, payment_type, amount, payment_status, marked_by, note, payment_proof) VALUES (?, NULL, ?, ?, ?, ?, ?, ?)',
                    [$bookingId, 'Survey Fee', $booking['survey_fee_amount'], 'Belum Dibayar', $user['id'], post_trim('note'), $proofPath]
                );
            }
            notify_role('staff', 'Bukti survey fee dikirim', "Customer mengirim bukti pembayaran survey fee {$booking['booking_code']}.", 'payment', $bookingId);
            log_activity('Upload bukti survey fee', "Customer {$user['name']} upload bukti survey fee booking {$booking['booking_code']}.", 'booking', $bookingId);
            json_response(true, 'Bukti survey fee berhasil dikirim. Menunggu konfirmasi staff.');
        }
        json_response(false, 'Invoice atau booking ID wajib diisi.', [], 422);
    }

    if ($action === 'complete_order') {
        require_roles(['staff']);
        $bookingId = post_int('booking_id');
        $booking = db_first('SELECT * FROM bookings WHERE id=?', [$bookingId]);
        if (!$booking) {
            json_response(false, 'Booking tidak ditemukan.', [], 404);
        }
        require_status_value($booking['status'], ['Lunas'], 'Order hanya bisa diselesaikan setelah invoice lunas.');
        update_booking_status($bookingId, 'Selesai', 'Staff menyelesaikan order.', (int) $user['id']);
        create_notification((int) $booking['customer_id'], 'customer', 'Berikan ulasan', "Order {$booking['booking_code']} selesai. Bagikan rating dan pengalaman Anda.", 'booking', $bookingId);
        log_activity('Order selesai', "Staff menyelesaikan order {$booking['booking_code']}.", 'booking', $bookingId);
        json_response(true, 'Order ditandai selesai.');
    }

    if ($action === 'submit_review') {
        require_roles(['customer']);
        $bookingId = post_int('booking_id');
        $rating = post_int('rating');
        $comment = post_trim('comment');
        if ($rating < 1 || $rating > 5) {
            json_response(false, 'Rating harus antara 1 sampai 5.', [], 422);
        }
        $booking = db_first('SELECT * FROM bookings WHERE id=? AND customer_id=?', [$bookingId, $user['id']]);
        if (!$booking) {
            json_response(false, 'Booking tidak ditemukan.', [], 404);
        }
        if ($booking['status'] !== 'Selesai') {
            json_response(false, 'Ulasan hanya bisa diberikan setelah order selesai.', [], 422);
        }
        $existing = db_first('SELECT id FROM reviews WHERE booking_id=?', [$bookingId]);
        if ($existing) {
            db_execute('UPDATE reviews SET rating=?, comment=? WHERE booking_id=?', [$rating, $comment, $bookingId]);
        } else {
            db_insert('INSERT INTO reviews (booking_id, customer_id, rating, comment) VALUES (?, ?, ?, ?)', [$bookingId, $user['id'], $rating, $comment]);
        }
        log_activity('Ulasan diberikan', "Customer {$user['name']} memberi rating {$rating}/5 untuk {$booking['booking_code']}.", 'booking', $bookingId);
        json_response(true, 'Ulasan berhasil disimpan. Terima kasih!');
    }

    if ($action === 'delete_booking') {
        require_roles(['staff']);
        require_confirmed_action();
        $bookingId = post_int('booking_id');
        $booking = db_first('SELECT * FROM bookings WHERE id=?', [$bookingId]);
        if (!$booking) {
            json_response(false, 'Booking tidak ditemukan.', [], 404);
        }
        $paths = [];
        if (!empty($booking['reference_photo'])) {
            $paths[] = $booking['reference_photo'];
        }
        foreach (db_query('SELECT photo_path FROM survey_photos WHERE booking_id=?', [$bookingId]) as $photo) {
            $paths[] = $photo['photo_path'];
        }

        $pdo = db();
        $pdo->beginTransaction();
        try {
            db_execute('DELETE FROM bookings WHERE id=?', [$bookingId]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        unlink_safe_local_files($paths);
        log_activity('Booking dihapus', "Staff menghapus booking {$booking['booking_code']}.", 'booking', $bookingId);
        json_response(true, "Booking {$booking['booking_code']} berhasil dihapus.");
    }

    if ($action === 'delete_master_record') {
        require_roles(['staff']);
        require_confirmed_action();
        $target = post_trim('target');
        $id = post_int('id');
        $map = [
            'category' => ['table' => 'service_categories', 'label' => 'Kategori layanan', 'name' => 'name'],
            'service' => ['table' => 'services', 'label' => 'Layanan', 'name' => 'name'],
            'material' => ['table' => 'materials', 'label' => 'Bahan baku', 'name' => 'name'],
            'labor_rate' => ['table' => 'labor_rates', 'label' => 'Harga tukang', 'name' => 'name'],
            'additional_cost' => ['table' => 'additional_cost_types', 'label' => 'Biaya tambahan', 'name' => 'name'],
        ];
        if (!$id || !isset($map[$target])) {
            json_response(false, 'Target hapus tidak valid.', [], 422);
        }
        $meta = $map[$target];
        $record = db_first("SELECT * FROM {$meta['table']} WHERE id=?", [$id]);
        if (!$record) {
            json_response(false, "{$meta['label']} tidak ditemukan.", [], 404);
        }

        $used = 0;
        if ($target === 'category') {
            $used = (int) (db_first('SELECT COUNT(*) AS c FROM services WHERE category_id=?', [$id])['c'] ?? 0);
        } elseif ($target === 'service') {
            $used = (int) (db_first(
                'SELECT COUNT(*) AS c FROM bookings WHERE service_id=?',
                [$id]
            )['c'] ?? 0);
        }

        $name = (string) ($record[$meta['name']] ?? ('#' . $id));
        if ($used > 0 && array_key_exists('status', $record)) {
            db_execute("UPDATE {$meta['table']} SET status='inactive' WHERE id=?", [$id]);
            log_activity('Master data dihapus', "Staff menonaktifkan {$meta['label']} {$name} karena masih terkait histori.", $target, $id);
            json_response(true, "{$meta['label']} {$name} sudah dipakai histori, jadi dinonaktifkan agar data lama tetap aman.");
        }

        db_execute("DELETE FROM {$meta['table']} WHERE id=?", [$id]);
        log_activity('Master data dihapus', "Staff menghapus {$meta['label']} {$name}.", $target, $id);
        json_response(true, "{$meta['label']} {$name} berhasil dihapus.");
    }

    if ($action === 'delete_user') {
        require_roles(['staff']);
        require_confirmed_action();
        $userId = post_int('user_id');
        if (!$userId) {
            json_response(false, 'User tidak valid.', [], 422);
        }
        if ($userId === (int) $user['id']) {
            json_response(false, 'Staff tidak bisa menghapus akun yang sedang dipakai login.', [], 422);
        }
        $targetUser = db_first('SELECT * FROM users WHERE id=?', [$userId]);
        if (!$targetUser) {
            json_response(false, 'User tidak ditemukan.', [], 404);
        }
        $linked = count_user_operational_links($userId);
        $targetLabel = role_label($targetUser['role']);
        if ($linked > 0 || in_array($targetUser['role'], ['staff', 'boss'], true)) {
            db_execute("UPDATE users SET status='inactive' WHERE id=?", [$userId]);
            log_activity($targetLabel . ' dihapus', "Staff menonaktifkan {$targetLabel} {$targetUser['name']} karena masih terkait histori.", 'user', $userId);
            json_response(true, "{$targetLabel} {$targetUser['name']} masih punya histori transaksi, jadi dinonaktifkan.");
        }

        db_execute('DELETE FROM users WHERE id=?', [$userId]);
        log_activity($targetLabel . ' dihapus', "Staff menghapus {$targetLabel} {$targetUser['name']}.", 'user', $userId);
        json_response(true, "{$targetLabel} {$targetUser['name']} berhasil dihapus.");
    }

    if (in_array($action, ['save_category', 'save_service', 'save_material', 'save_labor_rate', 'save_additional_cost', 'save_user', 'save_settings'], true)) {
        require_roles(['staff']);

        if ($action === 'save_category') {
            $id = post_int('id');
            $name = post_trim('name');
            require_value($name, 'Nama kategori wajib diisi.');
            $image = save_single_upload('image', 'assets/img/services') ?: post_trim('existing_image', 'assets/img/services/custom-landscape.svg');
            if ($id) {
                db_execute('UPDATE service_categories SET name=?, slug=?, description=?, image=?, status=? WHERE id=?', [$name, slugify($name), post_trim('description'), $image, post_master_status(), $id]);
            } else {
                db_insert('INSERT INTO service_categories (name, slug, description, image, status) VALUES (?, ?, ?, ?, ?)', [$name, slugify($name), post_trim('description'), $image, post_master_status()]);
            }
            log_activity('Master data diubah', "Kategori layanan {$name} disimpan.", 'category', $id ?: null);
            json_response(true, 'Kategori layanan berhasil disimpan.');
        }

        if ($action === 'save_service') {
            $id       = post_int('id');
            $name     = post_trim('name');
            require_value($name, 'Nama layanan wajib diisi.');
            $basePricePer10m    = 0;
            $baseDurationPer10m = max(1, post_int('base_duration_days_per_10m', 1));
            $image = save_single_upload('image', 'assets/img/services') ?: post_trim('existing_image', 'assets/img/services/taman-minimalis.svg');
            if ($id) {
                db_execute('UPDATE services SET category_id=?, name=?, slug=?, short_description=?, description=?, base_price_per_10m=?, base_duration_days_per_10m=?, image=?, status=? WHERE id=?',
                    [post_int('category_id') ?: null, $name, slugify($name), post_trim('short_description'), post_trim('description'), $basePricePer10m, $baseDurationPer10m, $image, post_master_status(), $id]);
            } else {
                db_insert('INSERT INTO services (category_id, service_code, name, slug, short_description, description, base_price_per_10m, base_duration_days_per_10m, image, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [post_int('category_id') ?: null, next_code('services', 'service_code', 'SRV-', false), $name, slugify($name), post_trim('short_description'), post_trim('description'), $basePricePer10m, $baseDurationPer10m, $image, post_master_status()]);
            }
            log_activity('Master data diubah', "Layanan {$name} disimpan.", 'service', $id ?: null);
            json_response(true, 'Layanan berhasil disimpan.');
        }

        if ($action === 'save_material') {
            $id = post_int('id');
            $name = post_trim('name');
            require_value($name, 'Nama bahan wajib diisi.');
            $image = save_single_upload('image', 'assets/img/materials') ?: post_trim('existing_image', 'assets/img/materials/rumput.svg');
            if ($id) {
                db_execute('UPDATE materials SET name=?, unit=?, price_per_unit=?, default_qty_per_10m=?, image=?, status=? WHERE id=?', [$name, post_trim('unit'), post_int('price_per_unit'), post_float('default_qty_per_10m'), $image, post_master_status(), $id]);
            } else {
                db_insert('INSERT INTO materials (material_code, name, unit, price_per_unit, default_qty_per_10m, image, status) VALUES (?, ?, ?, ?, ?, ?, ?)', [next_code('materials', 'material_code', 'MAT-', false), $name, post_trim('unit'), post_int('price_per_unit'), post_float('default_qty_per_10m'), $image, post_master_status()]);
            }
            log_activity('Master data diubah', "Bahan {$name} disimpan.", 'material', $id ?: null);
            json_response(true, 'Bahan baku berhasil disimpan.');
        }

        if ($action === 'save_labor_rate') {
            $id = post_int('id');
            $name = post_trim('name');
            require_value($name, 'Nama harga tukang wajib diisi.');
            if ($id) {
                db_execute('UPDATE labor_rates SET name=?, description=?, daily_rate=?, status=? WHERE id=?', [$name, post_trim('description'), post_int('daily_rate'), post_master_status(), $id]);
            } else {
                db_insert('INSERT INTO labor_rates (name, description, daily_rate, status) VALUES (?, ?, ?, ?)', [$name, post_trim('description'), post_int('daily_rate'), post_master_status()]);
            }
            log_activity('Master data diubah', "Harga tukang {$name} disimpan.", 'labor_rate', $id ?: null);
            json_response(true, 'Harga tukang berhasil disimpan.');
        }

        if ($action === 'save_additional_cost') {
            $id = post_int('id');
            $name = post_trim('name');
            require_value($name, 'Nama biaya tambahan wajib diisi.');
            if ($id) {
                db_execute('UPDATE additional_cost_types SET name=?, description=?, default_price=?, unit=?, status=? WHERE id=?', [$name, post_trim('description'), post_int('default_price'), post_trim('unit'), post_master_status(), $id]);
            } else {
                db_insert('INSERT INTO additional_cost_types (name, description, default_price, unit, status) VALUES (?, ?, ?, ?, ?)', [$name, post_trim('description'), post_int('default_price'), post_trim('unit'), post_master_status()]);
            }
            log_activity('Master data diubah', "Biaya tambahan {$name} disimpan.", 'additional_cost', $id ?: null);
            json_response(true, 'Biaya tambahan berhasil disimpan.');
        }

        if ($action === 'save_user') {
            $id = post_int('id');
            $name = post_trim('name');
            $email = strtolower(post_trim('email'));
            $userRole = post_trim('role');
            require_value($name, 'Nama user wajib diisi.');
            require_value($email, 'Email user wajib diisi.');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !in_array($userRole, ['customer', 'pekerja_lapangan', 'staff', 'boss'], true)) {
                json_response(false, 'Email atau role tidak valid.', [], 422);
            }
            if ($id) {
                $targetUser = db_first('SELECT id, role FROM users WHERE id=?', [$id]);
                if (!$targetUser) {
                    json_response(false, 'Data user tidak ditemukan.', [], 404);
                }
                if ((int) $targetUser['id'] === (int) $user['id'] && ($userRole !== 'staff' || post_master_status() !== 'active')) {
                    json_response(false, 'Staff tidak bisa mengubah role atau menonaktifkan akun yang sedang dipakai.', [], 422);
                }
            }
            $dup = db_first('SELECT id FROM users WHERE email=? AND id<>?', [$email, $id ?: 0]);
            if ($dup) {
                json_response(false, 'Email sudah digunakan user lain.', [], 422);
            }
            if ($id) {
                $params = [$name, $email, $userRole, post_trim('phone'), post_trim('address'), post_master_status(), $id];
                db_execute('UPDATE users SET name=?, email=?, role=?, phone=?, address=?, status=? WHERE id=?', $params);
                if (post_trim('password') !== '') {
                    if (strlen(post_trim('password')) < 6) {
                        json_response(false, 'Password minimal 6 karakter.', [], 422);
                    }
                    db_execute('UPDATE users SET password=? WHERE id=?', [password_hash(post_trim('password'), PASSWORD_BCRYPT), $id]);
                }
            } else {
                $password = post_trim('password');
                if (strlen($password) < 6) {
                    json_response(false, 'Password minimal 6 karakter.', [], 422);
                }
                db_insert('INSERT INTO users (name, email, password, role, phone, address, status) VALUES (?, ?, ?, ?, ?, ?, ?)', [$name, $email, password_hash($password, PASSWORD_BCRYPT), $userRole, post_trim('phone'), post_trim('address'), post_master_status()]);
            }
            log_activity('Master data diubah', "User {$name} disimpan.", 'user', $id ?: null);
            json_response(true, 'User berhasil disimpan.');
        }

        if ($action === 'save_settings') {
            $companyEmail = strtolower(post_trim('company_email'));
            $surveyFeeAmount = post_int('survey_fee_amount');
            if ($companyEmail !== '' && !filter_var($companyEmail, FILTER_VALIDATE_EMAIL)) {
                json_response(false, 'Format email perusahaan tidak valid.', [], 422);
            }
            if ($surveyFeeAmount < 0) {
                json_response(false, 'Survey fee tidak boleh bernilai negatif.', [], 422);
            }
            foreach (['app_name', 'company_name', 'company_email', 'company_phone', 'company_address', 'survey_fee_amount'] as $key) {
                $value = $key === 'company_email' ? $companyEmail : post_trim($key);
                $value = $key === 'survey_fee_amount' ? (string) $surveyFeeAmount : $value;
                setting_set($key, $value);
            }
            log_activity('Pengaturan diubah', 'Staff menyimpan pengaturan sistem.', 'settings', null);
            json_response(true, 'Pengaturan sistem berhasil disimpan.');
        }
    }

    if ($action === 'update_profile') {
        $name = post_trim('name');
        $email = strtolower(post_trim('email'));
        require_value($name, 'Nama wajib diisi.');
        require_value($email, 'Email wajib diisi.');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            json_response(false, 'Format email tidak valid.', [], 422);
        }
        $dup = db_first('SELECT id FROM users WHERE email=? AND id<>?', [$email, $user['id']]);
        if ($dup) {
            json_response(false, 'Email sudah digunakan user lain.', [], 422);
        }
        db_execute('UPDATE users SET name=?, email=?, phone=?, address=? WHERE id=?', [$name, $email, post_trim('phone'), post_trim('address'), $user['id']]);
        refresh_current_user();
        log_activity('Profil diubah', "{$name} memperbarui profil.", 'user', (int) $user['id']);
        json_response(true, 'Profil berhasil diperbarui.');
    }

    if ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['password_confirm'] ?? '';
        if ($new === '' || strlen($new) < 6 || $new !== $confirm) {
            json_response(false, 'Password baru minimal 6 karakter dan konfirmasi harus cocok.', [], 422);
        }
        $row = db_first('SELECT password FROM users WHERE id=?', [$user['id']]);
        if (!$row || !password_verify($current, $row['password'])) {
            json_response(false, 'Password lama tidak sesuai.', [], 422);
        }
        db_execute('UPDATE users SET password=? WHERE id=?', [password_hash($new, PASSWORD_BCRYPT), $user['id']]);
        session_destroy();
        json_response(true, 'Password berhasil diubah. Silakan login ulang.', ['redirect' => 'login.php']);
    }
} catch (InvalidArgumentException | RuntimeException $e) {
    json_response(false, $e->getMessage(), [], 422);
} catch (Throwable $e) {
    error_log($e->getMessage());
    json_response(false, 'Terjadi kendala server. Silakan coba lagi atau hubungi staff.', [], 500);
}

json_response(false, 'Action tidak dikenali.', [], 404);
