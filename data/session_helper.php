<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../app/helpers/estimator.php';

const ORDER_STATUSES = [
    'Menunggu Konfirmasi',
    'Survey Dijadwalkan',
    'Survey Berlangsung',
    'Survey Selesai',
    'Estimasi Dibuat',
    'Menunggu Persetujuan Customer',
    'Estimasi Disetujui',
    'Estimasi Ditolak',
    'Dibatalkan',
    'Dalam Pengerjaan',
    'Revisi Estimasi',
    'Pekerjaan Selesai',
    'Invoice Final Dibuat',
    'Menunggu Pembayaran',
    'Lunas',
    'Selesai',
];

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function json_attr(array $value): string
{
    return h(json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function format_rupiah($amount): string
{
    return 'Rp ' . number_format((float) $amount, 0, ',', '.');
}

function format_rupiah_number($amount): string
{
    return number_format((float) $amount, 0, ',', '.');
}

function format_datetime(?string $value): string
{
    if (!$value) {
        return '-';
    }

    return date('d M Y H:i', strtotime($value));
}

function format_date_id(?string $value): string
{
    if (!$value) {
        return '-';
    }

    return date('d M Y', strtotime($value));
}

function chart_percent($value, $max, int $minimum = 4): int
{
    $value = (float) $value;
    $max = (float) $max;
    if ($value <= 0 || $max <= 0) {
        return 0;
    }

    return min(100, max($minimum, (int) round(($value / $max) * 100)));
}

function slugify(string $value): string
{
    $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $value), '-'));
    return $slug ?: 'item';
}

function base_url(string $path = ''): string
{
    $base = rtrim($_ENV['APP_URL'] ?? 'http://localhost/riski', '/');
    return $base . ($path ? '/' . ltrim($path, '/') : '');
}

/**
 * Validate and return a safe internal relative redirect path.
 * Rejects absolute URLs, protocol-relative URLs, backslash tricks,
 * null bytes, control characters, and directory traversal.
 *
 * @param  mixed  $input    Raw user-supplied redirect path.
 * @param  string $fallback Default path to use when input is rejected.
 * @return string           A safe relative path.
 */
function safe_redirect_path($input, string $fallback = 'dashboard.php'): string
{
    if (!is_string($input) || $input === '') {
        return $fallback;
    }

    // Strip null bytes and control characters
    $path = preg_replace('/[\x00-\x1f\x7f]/', '', $input);

    // Normalize backslashes to forward slashes (bypass attempt)
    $path = str_replace('\\', '/', $path);

    // Strip leading slashes/dots to get a relative path
    $path = ltrim($path, '/');

    // Reject if still empty after stripping
    if ($path === '') {
        return $fallback;
    }

    // Reject absolute URLs: anything with a scheme (://), protocol-relative (//)
    if (
        str_contains($path, '://') ||
        str_starts_with($path, '//') ||
        preg_match('/^[a-zA-Z][a-zA-Z0-9+\-.]*:/', $path)
    ) {
        return $fallback;
    }

    // Reject directory traversal
    if (str_contains($path, '../') || str_contains($path, './')) {
        return $fallback;
    }

    // Only allow safe filename characters: letters, digits, hyphen, underscore, dot, slash, equals, ampersand, question mark
    if (!preg_match('/^[a-zA-Z0-9\-_.\/=&?%]+$/', $path)) {
        return $fallback;
    }

    return $path;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function verify_csrf(?string $token): bool
{
    return is_string($token) && hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

function require_csrf(): void
{
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        json_response(false, 'Sesi form tidak valid. Muat ulang halaman lalu coba lagi.', [], 419);
    }
}

function user_avatar_initial(string $name): string
{
    $parts = preg_split('/\s+/', trim($name));
    $initials = strtoupper(mb_substr($parts[0] ?? 'U', 0, 1));
    if (count($parts) > 1) {
        $initials .= strtoupper(mb_substr(end($parts), 0, 1));
    }

    return $initials;
}

function normalize_user(array $user): array
{
    $user['avatar_initial'] = user_avatar_initial($user['name'] ?? $user['nama'] ?? 'User');
    $user['nama'] = $user['name'] ?? $user['nama'] ?? 'User';
    $user['no_hp'] = $user['phone'] ?? $user['no_hp'] ?? '';
    return $user;
}

function find_user_by_credentials(string $email, string $password): ?array
{
    $user = db_first(
        'SELECT id, name, email, password, role, phone, address, avatar, status FROM users WHERE email = ? AND status = ?',
        [strtolower(trim($email)), 'active']
    );

    if (!$user || !password_verify($password, $user['password'])) {
        return null;
    }

    db_execute('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$user['id']]);
    unset($user['password']);

    return normalize_user($user);
}

function current_user(): ?array
{
    return isset($_SESSION['abi_user']) ? normalize_user($_SESSION['abi_user']) : null;
}

function refresh_current_user(): void
{
    $user = current_user();
    if (!$user) {
        return;
    }

    $fresh = db_first('SELECT id, name, email, role, phone, address, avatar, status FROM users WHERE id = ?', [$user['id']]);
    if ($fresh && $fresh['status'] === 'active') {
        $_SESSION['abi_user'] = normalize_user($fresh);
    }
}

function current_role(): ?string
{
    return current_user()['role'] ?? null;
}

function role_label(string $role): string
{
    return match ($role) {
        'customer' => 'Customer',
        'pekerja_lapangan' => 'Pekerja Lapangan',
        'staff' => 'Staff Operasional',
        'boss' => 'Boss',
        default => 'User',
    };
}

function require_login(): void
{
    if (!current_user()) {
        $return = $_SERVER['REQUEST_URI'] ?? 'dashboard.php';
        header('Location: login.php?return=' . urlencode($return));
        exit;
    }
}

function allow_roles(array $roles): bool
{
    $role = current_role();
    return $role !== null && in_array($role, $roles, true);
}

function require_roles(array $roles): void
{
    if (!allow_roles($roles)) {
        if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch') {
            json_response(false, 'Akses ditolak.', [], 403);
        }
        flash_message('Anda tidak memiliki akses ke halaman tersebut.', 'warning');
        header('Location: ' . base_url('dashboard.php'));
        exit;
    }
}

function flash_message(?string $message = null, string $type = 'info'): ?array
{
    if ($message !== null) {
        $_SESSION['abi_flash'] = ['message' => $message, 'type' => $type];
        return null;
    }

    $flash = $_SESSION['abi_flash'] ?? null;
    unset($_SESSION['abi_flash']);

    return $flash;
}

function json_response(bool $success, string $message, array $data = [], int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

function allowed_pages_for_role(string $role): array
{
    return match ($role) {
        'customer' => ['dashboard', 'catalog', 'order', 'booking', 'booking_detail', 'booking_estimate', 'booking_work', 'booking_invoice', 'booking_payment', 'estimate_detail', 'revision_detail', 'invoice_detail', 'payment_detail', 'estimates', 'invoices', 'progress', 'profil'],
        'pekerja_lapangan' => ['dashboard', 'tasks', 'booking_schedule', 'booking_survey', 'booking_estimate', 'booking_work', 'surveys', 'booking_detail', 'estimate_detail', 'revision_detail', 'estimates', 'works', 'revisions', 'profil'],
        'staff' => ['dashboard', 'order', 'booking', 'booking_detail', 'booking_schedule', 'booking_survey', 'booking_estimate', 'booking_work', 'booking_invoice', 'booking_payment', 'estimate_detail', 'revision_detail', 'invoice_detail', 'payment_detail', 'customers', 'schedules', 'pekerja_lapangan', 'catalog', 'materials', 'labor_rates', 'additional_costs', 'estimates', 'revisions', 'invoices', 'payments', 'users', 'settings', 'reports', 'activity_logs', 'profil'],
        'boss' => ['dashboard', 'monitoring', 'booking_detail', 'booking_schedule', 'booking_survey', 'booking_estimate', 'booking_work', 'booking_invoice', 'booking_payment', 'estimate_detail', 'revision_detail', 'invoice_detail', 'payment_detail', 'monitoring_booking', 'monitoring_works', 'monitoring_pekerja_lapangan', 'monitoring_estimates', 'monitoring_invoices', 'monitoring_payments', 'reports', 'profil'],
        default => ['dashboard', 'profil'],
    };
}

function can_access_page(string $page, string $role): bool
{
    return in_array($page, allowed_pages_for_role($role), true);
}

function page_label(string $page, string $role): string
{
    if ($page === 'dashboard') {
        return 'Dashboard ' . role_label($role);
    }

    $bookingWorkflowLabels = match ($page) {
        'booking_schedule' => match ($role) {
            'pekerja_lapangan' => 'Tugas Survey',
            'boss' => 'Monitoring Jadwal',
            default => 'Jadwal Survey',
        },
        'booking_survey' => match ($role) {
            'pekerja_lapangan' => 'Input Survey',
            'boss' => 'Monitoring Survey',
            default => 'Hasil Survey',
        },
        'booking_estimate' => match ($role) {
            'customer' => 'Estimasi Saya',
            'pekerja_lapangan' => 'Buat Estimasi',
            'boss' => 'Monitoring Estimasi',
            default => 'Estimasi Booking',
        },
        'booking_work' => match ($role) {
            'customer' => 'Progress Pekerjaan',
            'pekerja_lapangan' => 'Pekerjaan Saya',
            'boss' => 'Monitoring Pekerjaan',
            default => 'Pekerjaan',
        },
        'booking_invoice' => match ($role) {
            'customer' => 'Invoice Saya',
            'boss' => 'Monitoring Invoice',
            default => 'Invoice Final',
        },
        'booking_payment' => match ($role) {
            'customer' => 'Pembayaran Saya',
            'boss' => 'Monitoring Pembayaran',
            default => 'Pembayaran',
        },
        default => null,
    };
    if ($bookingWorkflowLabels !== null) {
        return $bookingWorkflowLabels;
    }

    return [
        'order' => $role === 'customer' ? 'Order Saya' : 'Order',
        'tasks' => 'Tugas Saya',
        'monitoring' => 'Monitoring',
        'catalog' => 'Katalog Layanan',
        'booking' => $role === 'customer' ? 'Booking Saya' : 'Booking Masuk',
        'booking_detail' => 'Detail Order',
        'estimate_detail' => 'Detail Estimasi',
        'revision_detail' => 'Detail Revisi',
        'invoice_detail' => 'Detail Invoice',
        'payment_detail' => 'Detail Pembayaran',
        'customers' => 'Customer',
        'schedules' => 'Jadwal Survey',
        'pekerja_lapangan' => 'Pekerja Lapangan',
        'materials' => 'Bahan Baku',
        'labor_rates' => 'Harga Tukang',
        'additional_costs' => 'Biaya Tambahan',
        'estimates' => 'Estimasi',
        'revisions' => 'Revisi Estimasi',
        'works' => 'Pekerjaan Saya',
        'invoices' => 'Invoice Final',
        'payments' => 'Pembayaran',
        'users' => 'User Management',
        'settings' => 'Pengaturan',
        'reports' => 'Laporan',
        'activity_logs' => 'Activity Log',
        'progress' => 'Progress Pekerjaan',
        'profil' => 'Profil',
        'monitoring_booking' => 'Monitoring Booking',
        'monitoring_works' => 'Monitoring Pekerjaan',
        'monitoring_pekerja_lapangan' => 'Monitoring Pekerja Lapangan',
        'monitoring_estimates' => 'Monitoring Estimasi',
        'monitoring_invoices' => 'Monitoring Invoice',
        'monitoring_payments' => 'Monitoring Pembayaran',
        'surveys' => 'Tugas Survey',
    ][$page] ?? 'Dashboard';
}

function notification_target_url(array $notification): string
{
    $type = (string) ($notification['reference_type'] ?? '');
    $referenceId = (int) ($notification['reference_id'] ?? 0);
    if ($referenceId <= 0) {
        return '#';
    }

    $bookingId = 0;
    $section = 'overview';
    if ($type === 'booking') {
        $bookingId = $referenceId;
    } elseif ($type === 'estimate') {
        $row = db_first('SELECT booking_id FROM estimates WHERE id=?', [$referenceId]);
        $bookingId = (int) ($row['booking_id'] ?? 0);
        $section = 'estimate';
    } elseif ($type === 'revision') {
        $row = db_first('SELECT booking_id FROM estimate_revisions WHERE id=?', [$referenceId]);
        $bookingId = (int) ($row['booking_id'] ?? 0);
        $section = 'estimate';
    } elseif (in_array($type, ['invoice', 'payment'], true)) {
        $row = db_first('SELECT booking_id FROM final_invoices WHERE id=?', [$referenceId]);
        $bookingId = (int) ($row['booking_id'] ?? 0);
        if ($bookingId === 0) {
            $booking = db_first('SELECT id FROM bookings WHERE id=?', [$referenceId]);
            $bookingId = (int) ($booking['id'] ?? 0);
        }
        $section = 'billing';
    }

    return $bookingId > 0
        ? 'dashboard.php?page=booking_detail&id=' . $bookingId . '&section=' . urlencode($section)
        : '#';
}

function dashboard_file_for_role(string $role): string
{
    return match ($role) {
        'customer' => 'dashboard_customer.php',
        'pekerja_lapangan' => 'dashboard_pekerja_lapangan.php',
        'boss' => 'dashboard_boss.php',
        default => 'dashboard_staff.php',
    };
}

function status_badge_class(string $status): string
{
    return match ($status) {
        'Survey Selesai', 'Estimasi Disetujui', 'Pekerjaan Selesai', 'Lunas', 'Sudah Dibayar', 'Selesai' => 'badge text-bg-success',
        'Menunggu Konfirmasi', 'Menunggu Persetujuan Customer', 'Revisi Estimasi', 'Menunggu Pembayaran',
        'inactive', 'Ditolak', 'Dijadwalkan Ulang', 'Belum Dibayar' => 'badge text-bg-warning',
        'Estimasi Ditolak', 'Dibatalkan' => 'badge text-bg-danger',
        'Survey Dijadwalkan', 'Survey Berlangsung', 'Estimasi Dibuat', 'Dalam Pengerjaan', 'Invoice Final Dibuat',
        'active', 'Draft', 'Dikirim', 'Terjadwal', 'Berlangsung' => 'badge text-bg-info',
        default => 'badge text-bg-secondary',
    };
}

function get_pekerja_lapangan(): array
{
    return db_query("SELECT id, name, email, phone FROM users WHERE role = 'pekerja_lapangan' AND status = 'active' ORDER BY name");
}

function get_services_with_min_price(bool $activeOnly = true): array
{
    $where = $activeOnly ? "WHERE s.status = 'active'" : '';
    return db_query("
        SELECT s.*, c.name AS category_name
        FROM services s
        LEFT JOIN service_categories c ON c.id = s.category_id
        {$where}
        ORDER BY c.name, s.name
    ");
}

function booking_for_access(int $bookingId, array $user): ?array
{
    $booking = db_first(
        "SELECT b.*, u.name AS customer_name, u.email AS customer_email, u.phone AS customer_phone,
                fw.name AS pekerja_lapangan_name, s.name AS service_name
         FROM bookings b
         JOIN users u ON u.id = b.customer_id
         LEFT JOIN users fw ON fw.id = b.assigned_pekerja_lapangan_id
         LEFT JOIN services s ON s.id = b.service_id
         WHERE b.id = ?",
        [$bookingId]
    );

    if (!$booking) {
        return null;
    }

    return match ($user['role']) {
        'customer' => (int) $booking['customer_id'] === (int) $user['id'] ? $booking : null,
        'pekerja_lapangan' => (int) ($booking['assigned_pekerja_lapangan_id'] ?? 0) === (int) $user['id'] ? $booking : null,
        'staff', 'boss' => $booking,
        default => null,
    };
}

function update_booking_status(int $bookingId, string $newStatus, ?string $note = null, ?int $changedBy = null): void
{
    if (!in_array($newStatus, ORDER_STATUSES, true)) {
        throw new InvalidArgumentException('Status booking tidak valid.');
    }

    $booking = db_first('SELECT status FROM bookings WHERE id = ?', [$bookingId]);
    if (!$booking) {
        throw new InvalidArgumentException('Booking tidak ditemukan.');
    }

    $oldStatus = $booking['status'];
    if ($oldStatus === $newStatus) {
        return;
    }

    db_execute('UPDATE bookings SET status = ? WHERE id = ?', [$newStatus, $bookingId]);
    record_booking_status_history($bookingId, $oldStatus, $newStatus, $note, $changedBy);
}

function record_booking_status_history(int $bookingId, ?string $oldStatus, string $newStatus, ?string $note = null, ?int $changedBy = null): void
{
    db_execute(
        'INSERT INTO booking_status_histories (booking_id, old_status, new_status, changed_by, note) VALUES (?, ?, ?, ?, ?)',
        [$bookingId, $oldStatus, $newStatus, $changedBy ?? current_user()['id'] ?? null, $note]
    );
}

function log_activity(string $type, string $description, ?string $referenceType = null, ?int $referenceId = null): void
{
    $user = current_user();
    db_insert(
        'INSERT INTO activity_logs (user_id, activity_type, description, reference_type, reference_id) VALUES (?, ?, ?, ?, ?)',
        [$user['id'] ?? null, $type, $description, $referenceType, $referenceId]
    );
}

function create_notification(?int $userId, ?string $roleTarget, string $title, string $message, ?string $referenceType = null, ?int $referenceId = null): void
{
    db_insert(
        'INSERT INTO notifications (user_id, role_target, title, message, reference_type, reference_id) VALUES (?, ?, ?, ?, ?, ?)',
        [$userId, $roleTarget, $title, $message, $referenceType, $referenceId]
    );
}

function notify_role(string $role, string $title, string $message, ?string $referenceType = null, ?int $referenceId = null): void
{
    $users = db_query('SELECT id FROM users WHERE role = ? AND status = ?', [$role, 'active']);
    foreach ($users as $user) {
        create_notification((int) $user['id'], $role, $title, $message, $referenceType, $referenceId);
    }
}

function upload_booking_files(string $inputName, string $bookingCode, string $type, ?int $surveyId, int $bookingId): int
{
    if (empty($_FILES[$inputName]) || !is_array($_FILES[$inputName]['name'])) {
        return 0;
    }

    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
    $baseDir = dirname(__DIR__) . "/assets/uploads/bookings/{$bookingCode}/{$type}";
    if (!is_dir($baseDir) && !@mkdir($baseDir, 0775, true)) {
        throw new RuntimeException('Folder upload foto tidak bisa dibuat. Periksa permission folder assets/uploads.');
    }
    if (!is_writable($baseDir)) {
        throw new RuntimeException('Folder upload foto tidak bisa ditulis oleh server. Periksa permission folder assets/uploads.');
    }

    $saved = 0;
    foreach ($_FILES[$inputName]['name'] as $idx => $name) {
        if (($_FILES[$inputName]['error'][$idx] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        if ($_FILES[$inputName]['error'][$idx] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Upload foto gagal diproses.');
        }

        if ((int) $_FILES[$inputName]['size'][$idx] > 2 * 1024 * 1024) {
            throw new RuntimeException('Ukuran foto maksimal 2MB per file.');
        }

        $ext = strtolower(pathinfo((string) $name, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed, true)) {
            throw new RuntimeException('Format foto harus jpg, jpeg, png, atau webp.');
        }

        $safeName = date('YmdHis') . '-' . bin2hex(random_bytes(6)) . '.' . $ext;
        $target = "{$baseDir}/{$safeName}";
        if (!move_uploaded_file($_FILES[$inputName]['tmp_name'][$idx], $target)) {
            throw new RuntimeException('Foto gagal disimpan.');
        }

        $relative = "assets/uploads/bookings/{$bookingCode}/{$type}/{$safeName}";
        db_insert(
            'INSERT INTO survey_photos (survey_id, booking_id, photo_path, photo_type, uploaded_by) VALUES (?, ?, ?, ?, ?)',
            [$surveyId, $bookingId, $relative, $type, current_user()['id'] ?? null]
        );
        $saved++;
    }

    return $saved;
}

function existing_photo_count(int $bookingId, string $type): int
{
    $row = db_first('SELECT COUNT(*) AS c FROM survey_photos WHERE booking_id = ? AND photo_type = ?', [$bookingId, $type]);
    return (int) ($row['c'] ?? 0);
}

function paginate(string $sql, array $params, int $perPage = 15): array
{
    $page = max(1, (int) ($_GET['p'] ?? 1));
    $countSql = 'SELECT COUNT(*) AS c FROM (' . $sql . ') AS _count_query';
    $total = (int) (db_first($countSql, $params)['c'] ?? 0);
    $totalPages = max(1, (int) ceil($total / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;
    $rows = db_query($sql . " LIMIT {$perPage} OFFSET {$offset}", $params);
    return [
        'rows'        => $rows,
        'page'        => $page,
        'total'       => $total,
        'totalPages'  => $totalPages,
        'perPage'     => $perPage,
    ];
}

function render_pagination(int $page, int $totalPages, int $total, int $perPage): void
{
    if ($total <= 0) return;
    $params = $_GET;
    $start = max(1, $page - 2);
    $end   = min($totalPages, $page + 2);
    ?>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mt-3">
        <span class="small text-secondary">
            <?= number_format(($page - 1) * $perPage + 1) ?>–<?= number_format(min($page * $perPage, $total)) ?> dari <?= number_format($total) ?> data
        </span>
        <?php if ($totalPages <= 1): ?>
            <span class="small text-secondary">1 halaman</span>
        <?php else: ?>
        <nav>
            <ul class="pagination pagination-sm mb-0">
                <?php $params['p'] = max(1, $page - 1); ?>
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="?<?= h(http_build_query($params)) ?>"><i class="bi bi-chevron-left"></i></a>
                </li>
                <?php if ($start > 1): ?>
                    <?php $params['p'] = 1; ?>
                    <li class="page-item"><a class="page-link" href="?<?= h(http_build_query($params)) ?>">1</a></li>
                    <?php if ($start > 2): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
                <?php endif; ?>
                <?php for ($i = $start; $i <= $end; $i++): ?>
                    <?php $params['p'] = $i; ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link" href="?<?= h(http_build_query($params)) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                <?php if ($end < $totalPages): ?>
                    <?php if ($end < $totalPages - 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
                    <?php $params['p'] = $totalPages; ?>
                    <li class="page-item"><a class="page-link" href="?<?= h(http_build_query($params)) ?>"><?= $totalPages ?></a></li>
                <?php endif; ?>
                <?php $params['p'] = min($totalPages, $page + 1); ?>
                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="?<?= h(http_build_query($params)) ?>"><i class="bi bi-chevron-right"></i></a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
    <?php
}
