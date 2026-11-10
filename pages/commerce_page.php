<?php
/**
 * Render a CSRF-protected API form button.
 *
 * The $button parameter supports ONLY a controlled subset of HTML:
 * an optional leading Bootstrap icon tag (<i class="bi bi-XXX"></i>)
 * followed by plain text. Any other HTML is stripped.
 *
 * @param string $action      API action name (hidden field).
 * @param string $button      Button content: plain text or '<i class="bi bi-XXX"></i> Text'.
 * @param array  $fields      Hidden field key-value pairs.
 * @param string $class       Form CSS class.
 * @param string $buttonClass Button CSS class.
 * @param string $confirm     Optional confirmation message shown before submit.
 */
function render_api_form(string $action, string $button, array $fields = [], string $class = 'd-flex flex-wrap align-items-center gap-2', string $buttonClass = 'btn btn-primary btn-sm', string $confirm = ''): void
{
    // Safely parse button: extract optional leading Bootstrap icon, then escape the rest as plain text.
    $iconHtml = '';
    $labelText = $button;
    if (preg_match('/^<i class="bi (bi-[a-z0-9\-]+)"><\/i>\s*/i', $button, $m)) {
        $iconHtml = '<i class="bi' . h($m[1]) . '"></i> ';
        $labelText = substr($button, strlen($m[0]));
    }
    $safeButton = $iconHtml . h($labelText);

    echo '<form class="' . h($class) . '" data-api-form method="post" enctype="multipart/form-data"' . ($confirm !== '' ? ' data-confirm="' . h($confirm) . '"' : '') . '>';
    echo csrf_field();
    echo '<input type="hidden" name="action" value="' . h($action) . '">';
    if ($confirm !== '') {
        echo '<input type="hidden" name="confirm_action" value="1">';
    }
    foreach ($fields as $key => $value) {
        echo '<input type="hidden" name="' . h((string) $key) . '" value="' . h((string) $value) . '">';
    }
    echo '<button class="' . h($buttonClass) . '" type="submit">' . $safeButton . '</button>';
    echo '</form>';
}

function paginate_with_param(string $sql, array $params, int $perPage = 10, string $pageParam = 'p'): array
{
    $page = max(1, (int) ($_GET[$pageParam] ?? 1));
    $total = (int) (db_first('SELECT COUNT(*) AS c FROM (' . $sql . ') AS _count_query', $params)['c'] ?? 0);
    $totalPages = max(1, (int) ceil($total / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;
    $rows = db_query($sql . " LIMIT {$perPage} OFFSET {$offset}", $params);

    return [
        'rows' => $rows,
        'page' => $page,
        'total' => $total,
        'totalPages' => $totalPages,
        'perPage' => $perPage,
        'pageParam' => $pageParam,
    ];
}

function render_pagination_param(array $paged): void
{
    if ((int) ($paged['total'] ?? 0) <= 0) {
        return;
    }

    $page = (int) $paged['page'];
    $totalPages = (int) $paged['totalPages'];
    $total = (int) $paged['total'];
    $perPage = (int) $paged['perPage'];
    $pageParam = (string) ($paged['pageParam'] ?? 'p');
    $params = $_GET;
    $start = max(1, $page - 2);
    $end = min($totalPages, $page + 2);
    ?>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mt-3">
        <span class="small text-secondary">
            <?= number_format(($page - 1) * $perPage + 1) ?>-<?= number_format(min($page * $perPage, $total)) ?> dari <?= number_format($total) ?> data
        </span>
        <?php if ($totalPages <= 1): ?>
            <span class="small text-secondary">1 halaman</span>
        <?php else: ?>
        <nav>
            <ul class="pagination pagination-sm mb-0">
                <?php $params[$pageParam] = max(1, $page - 1); ?>
                <li class="page-item <?= $page <= 1 ?'disabled' : '' ?>"><a class="page-link" href="?<?= h(http_build_query($params)) ?>"><i class="bi bi-chevron-left"></i></a></li>
                <?php for ($i = $start; $i <= $end; $i++): ?>
                    <?php $params[$pageParam] = $i; ?>
                    <li class="page-item <?= $i === $page ?'active' : '' ?>"><a class="page-link" href="?<?= h(http_build_query($params)) ?>"><?= (int) $i ?></a></li>
                <?php endfor; ?>
                <?php $params[$pageParam] = min($totalPages, $page + 1); ?>
                <li class="page-item <?= $page >= $totalPages ?'disabled' : '' ?>"><a class="page-link" href="?<?= h(http_build_query($params)) ?>"><i class="bi bi-chevron-right"></i></a></li>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
    <?php
}

function booking_status_label_for_role(string $status, string $role): string
{
    if ($role !== 'pekerja_lapangan') {
        return $status;
    }

    return match ($status) {
        'Invoice Final Dibuat' => 'Data Final Dikirim',
        'Menunggu Pembayaran' => 'Menunggu Administrasi',
        'Lunas' => 'Administrasi Selesai',
        default => $status,
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

function render_booking_table(array $rows, string $role): void
{
    ?>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead><tr><th>Kode</th><th>Customer</th><th>Layanan</th><th>Survey</th><th>Pekerja Lapangan</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $item): ?>
                <tr>
                    <td><strong><?= h($item['booking_code']) ?></strong></td>
                    <td><?= h($item['customer_name'] ?? '-') ?><br><small><?= h($item['customer_phone'] ?? '') ?></small></td>
                    <td><?= h($item['service_name'] ?? ($item['is_custom_service'] ? 'Custom Layanan' : '-')) ?></td>
                    <td><?= h(format_date_id($item['actual_survey_date'] ?: $item['requested_survey_date'])) ?></td>
                    <td><?= h($item['pekerja_lapangan_name'] ?? '-') ?></td>
                    <td><span class="<?= status_badge_class($item['status']) ?>"><?= h(booking_status_label_for_role($item['status'], $role)) ?></span></td>
                    <td>
                        <div class="d-flex flex-wrap gap-2">
                            <a class="btn btn-outline-secondary btn-sm" href="dashboard.php?page=booking_detail&id=<?= (int) $item['id'] ?>">Detail</a>
                            <?php if ($role === 'staff'): ?>
                                <?php if ($item['status'] === 'Menunggu Konfirmasi'): ?>
                                    <a class="btn btn-primary btn-sm" href="dashboard.php?page=booking_detail&id=<?= (int) $item['id'] ?>&section=actions">Atur Survey</a>
                                <?php elseif (in_array($item['status'], ['Survey Dijadwalkan','Survey Berlangsung'], true)): ?>
                                    <a class="btn btn-outline-secondary btn-sm" href="dashboard.php?page=booking_detail&id=<?= (int) $item['id'] ?>&section=actions">Reschedule</a>
                                <?php elseif ($item['status'] === 'Estimasi Disetujui'): ?>
                                    <a class="btn btn-primary btn-sm" href="dashboard.php?page=booking_detail&id=<?= (int) $item['id'] ?>&section=actions">Mulai Kerja</a>
                                <?php elseif ($item['status'] === 'Lunas'): ?>
                                    <a class="btn btn-success btn-sm" href="dashboard.php?page=booking_detail&id=<?= (int) $item['id'] ?>&section=actions">Selesaikan</a>
                                <?php endif; ?>
                                <?php render_api_form('delete_booking', '<i class="bi bi-trash"></i> Hapus', ['booking_id' => $item['id']], 'd-flex flex-wrap align-items-center gap-2', 'btn btn-outline-danger btn-sm', 'Hapus booking ' . $item['booking_code'] . '? Data order, jadwal, survey, estimasi, invoice, dan pembayaran terkait akan ikut dihapus.'); ?>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">Belum ada data.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

function render_master_delete_form(string $target, int $id, string $label): void
{
    render_api_form(
        'delete_master_record',
        '<i class="bi bi-trash"></i> Hapus',
        ['target' => $target, 'id' => $id],
        'd-flex flex-wrap align-items-center gap-2',
        'btn btn-outline-danger btn-sm',
        "Hapus {$label}? Jika data sudah dipakai transaksi, sistem akan menonaktifkannya agar histori tetap aman."
    );
}

function modal_id(string $prefix, int|string $id): string
{
    return preg_replace('/[^A-Za-z0-9_-]/', '', $prefix . '-' . $id);
}

function settings_tab_for_page(string $page): string
{
    return match ($page) {
        'catalog' => 'catalog',
        'materials' => 'materials',
        'labor_rates' => 'labor',
        'additional_costs' => 'costs',
        'users' => 'users',
        'activity_logs' => 'activity',
        default => 'general',
    };
}

function settings_context_url(string $page, array $params = []): string
{
    if (!empty($GLOBALS['settingsHubContext'])) {
        $params = ['page' => 'settings', 'tab' => settings_tab_for_page($page)] + $params;
    } else {
        $params = ['page' => $page] + $params;
    }

    return 'dashboard.php?' . http_build_query($params);
}

function render_settings_context_fields(string $page): void
{
    if (!empty($GLOBALS['settingsHubContext'])) {
        echo '<input type="hidden" name="page" value="settings">';
        echo '<input type="hidden" name="tab" value="' . h(settings_tab_for_page($page)) . '">';
        return;
    }

    echo '<input type="hidden" name="page" value="' . h($page) . '">';
}

function render_modal_button(string $targetId, string $label, string $class = 'btn btn-primary', string $icon = ''): void
{
    echo '<button class="' . h($class) . '" type="button" data-bs-toggle="modal" data-bs-target="#' . h($targetId) . '">';
    if ($icon !== '') {
        echo '<i class="bi ' . h($icon) . '"></i> ';
    }
    echo h($label) . '</button>';
}

function render_user_form_fields(string $role = 'customer'): void
{
    ?>
    <input class="form-control" name="name" placeholder="Nama lengkap" required>
    <input class="form-control" type="email" name="email" placeholder="Email" required>
    <select class="form-select" name="role" required>
        <option value="customer" <?= $role === 'customer' ? 'selected' : '' ?>>Customer</option>
        <option value="pekerja_lapangan" <?= $role === 'pekerja_lapangan' ? 'selected' : '' ?>>Pekerja Lapangan</option>
        <option value="staff" <?= $role === 'staff' ? 'selected' : '' ?>>Staff Operasional</option>
        <option value="boss" <?= $role === 'boss' ? 'selected' : '' ?>>Boss</option>
    </select>
    <input class="form-control" name="phone" placeholder="Nomor HP">
    <textarea class="form-control" name="address" rows="2" placeholder="Alamat"></textarea>
    <select class="form-select" name="status"><option value="active">Aktif</option><option value="inactive">Nonaktif</option></select>
    <input class="form-control" type="password" name="password" placeholder="Password awal minimal 6 karakter" autocomplete="new-password" required>
    <?php
}

function render_user_edit_fields(array $row): void
{
    ?>
    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
    <input class="form-control" name="name" value="<?= h($row['name']) ?>" placeholder="Nama lengkap" required>
    <input class="form-control" type="email" name="email" value="<?= h($row['email']) ?>" placeholder="Email" required>
    <select class="form-select" name="role" required>
        <option value="customer" <?= $row['role'] === 'customer' ? 'selected' : '' ?>>Customer</option>
        <option value="pekerja_lapangan" <?= $row['role'] === 'pekerja_lapangan' ? 'selected' : '' ?>>Pekerja Lapangan</option>
        <option value="staff" <?= $row['role'] === 'staff' ? 'selected' : '' ?>>Staff Operasional</option>
        <option value="boss" <?= $row['role'] === 'boss' ? 'selected' : '' ?>>Boss</option>
    </select>
    <input class="form-control" name="phone" value="<?= h($row['phone'] ?? '') ?>" placeholder="Nomor HP">
    <textarea class="form-control" name="address" rows="2" placeholder="Alamat"><?= h($row['address'] ?? '') ?></textarea>
    <select class="form-select" name="status">
        <option value="active" <?= $row['status'] === 'active' ? 'selected' : '' ?>>Aktif</option>
        <option value="inactive" <?= $row['status'] === 'inactive' ? 'selected' : '' ?>>Nonaktif</option>
    </select>
    <input class="form-control" type="password" name="password" placeholder="Kosongkan jika password tidak diganti" autocomplete="new-password">
    <?php
}

function render_form_modal(string $id, string $title, string $subtitle, string $action, callable $fields, string $button = 'Simpan'): void
{
    ?>
    <div class="modal fade" id="<?= h($id) ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
            <div class="modal-content">
                <form data-api-form method="post" enctype="multipart/form-data">
                    <div class="modal-header">
                        <div>
                            <p class="eyebrow"><?= h($subtitle) ?></p>
                            <h5 class="modal-title"><?= h($title) ?></h5>
                        </div>
                        <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body d-flex flex-column gap-3">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="<?= h($action) ?>">
                        <?php $fields(); ?>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Batal</button>
                        <button class="btn btn-primary" type="submit"><?= h($button) ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php
}

function render_delete_modal(string $id, string $title, string $message, string $action, array $fields): void
{
    ?>
    <div class="modal fade" id="<?= h($id) ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form data-api-form method="post">
                    <div class="modal-header">
                        <div>
                            <p class="eyebrow">Konfirmasi</p>
                            <h5 class="modal-title"><?= h($title) ?></h5>
                        </div>
                        <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="<?= h($action) ?>">
                        <input type="hidden" name="confirm_action" value="1">
                        <?php foreach ($fields as $key => $value): ?>
                            <input type="hidden" name="<?= h((string) $key) ?>" value="<?= h((string) $value) ?>">
                        <?php endforeach; ?>
                        <p class="text-secondary mb-0"><?= h($message) ?></p>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Batal</button>
                        <button class="btn btn-danger" type="submit">Hapus</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php
}

function render_user_management_page(array $currentUser): void
{
    require_roles(['staff']);
    $type = $_GET['type'] ?? 'customer';
    $allowedTypes = ['customer', 'pekerja_lapangan', 'staff', 'boss', 'all'];
    if (!in_array($type, $allowedTypes, true)) {
        $type = 'customer';
    }

    $tabs = [
        'customer' => 'Customer',
        'pekerja_lapangan' => 'Pekerja Lapangan',
        'staff' => 'Staff',
        'boss' => 'Boss',
        'all' => 'Semua User',
    ];

    $addModal = modal_id('add-user-management', $type);
    $title = $tabs[$type];
    $search = trim((string) ($_GET['q'] ?? ''));
    $where = [];
    $params = [];

    if ($type !== 'all') {
        $where[] = 'role = ?';
        $params[] = $type;
    }

    if ($search !== '') {
        $where[] = '(name LIKE ? OR email LIKE ? OR phone LIKE ?)';
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }

    $sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    if ($type === 'all') {
        $paged = paginate("SELECT * FROM users {$sqlWhere} ORDER BY FIELD(role, 'staff','boss','pekerja_lapangan','customer'), status, name", $params, 15);
    } else {
        $paged = paginate("SELECT * FROM users {$sqlWhere} ORDER BY status, name", $params, 15);
    }
    $rows = $paged['rows'];
    $counts = [
        'customer' => (int) (db_first("SELECT COUNT(*) AS c FROM users WHERE role='customer'")['c'] ?? 0),
        'pekerja_lapangan' => (int) (db_first("SELECT COUNT(*) AS c FROM users WHERE role='pekerja_lapangan'")['c'] ?? 0),
        'staff' => (int) (db_first("SELECT COUNT(*) AS c FROM users WHERE role='staff'")['c'] ?? 0),
        'boss' => (int) (db_first("SELECT COUNT(*) AS c FROM users WHERE role='boss'")['c'] ?? 0),
        'all' => (int) (db_first('SELECT COUNT(*) AS c FROM users')['c'] ?? 0),
    ];
    $activeCount = (int) (db_first('SELECT COUNT(*) AS c FROM users WHERE status = ?', ['active'])['c'] ?? 0);
    $inactiveCount = (int) (db_first('SELECT COUNT(*) AS c FROM users WHERE status <> ?', ['active'])['c'] ?? 0);
    ?>
    <section>
        <header class="settings-section-head">
            <div>
                <p class="eyebrow">Manajemen akses</p>
                <h2>Pengguna</h2>
                <p>Kelola akun dan hak akses sesuai role tanpa mencampurnya dengan data operasional order.</p>
            </div>
            <?php render_modal_button($addModal, 'Tambah User', 'btn btn-primary', 'bi-plus-lg'); ?>
        </header>

        <div class="settings-kpis" style="--settings-kpi-columns: 3">
            <div class="settings-kpi"><span>Total user</span><strong><?= (int) $counts['all'] ?></strong></div>
            <div class="settings-kpi"><span>Akun aktif</span><strong><?= (int) $activeCount ?></strong></div>
            <div class="settings-kpi"><span>Akun nonaktif</span><strong><?= (int) $inactiveCount ?></strong></div>
        </div>

        <nav class="nav nav-pills settings-role-tabs" aria-label="Kategori user management">
            <?php foreach ($tabs as $key => $label): ?>
                <a class="nav-link <?= $type === $key ?'active' : '' ?>" href="<?= h(settings_context_url('users', ['type' => $key])) ?>">
                    <span><?= h($label) ?></span>
                    <b><?= (int) $counts[$key] ?></b>
                </a>
            <?php endforeach; ?>
        </nav>

        <section class="settings-panel">
            <div class="settings-toolbar">
                <div><p class="eyebrow">Daftar akun</p><h3 class="h6 mb-0 mt-1"><?= h($title) ?></h3></div>
                <form method="get">
                    <?php render_settings_context_fields('users'); ?>
                    <input type="hidden" name="type" value="<?= h($type) ?>">
                    <input class="form-control form-control-sm" type="search" name="q" value="<?= h($search) ?>" placeholder="Cari nama, email, atau HP">
                    <button class="btn btn-primary btn-sm" type="submit">Cari</button>
                    <?php if ($search !== ''): ?><a class="btn btn-outline-secondary btn-sm" href="<?= h(settings_context_url('users', ['type' => $type])) ?>">Reset</a><?php endif; ?>
                </form>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 settings-table settings-table--users">
                    <thead><tr><th>Nama</th><th>Kontak</th><th>Role</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $row): ?>
                        <?php $deleteModal = modal_id('delete-user', (int) $row['id']); ?>
                        <?php $editModal = modal_id('edit-user', (int) $row['id']); ?>
                        <tr>
                            <td><strong><?= h($row['name']) ?></strong></td>
                            <td><strong class="d-block fw-semibold"><?= h($row['email']) ?></strong><small class="text-muted"><?= h($row['phone'] ?: 'Nomor HP belum diisi') ?></small></td>
                            <td><?= h(role_label($row['role'])) ?></td>
                            <td><span class="<?= status_badge_class($row['status']) ?>"><?= h($row['status']) ?></span></td>
                            <td class="text-end">
                                <div class="d-flex flex-wrap gap-2 justify-content-end">
                                    <?php render_modal_button($editModal, 'Edit', 'btn btn-outline-secondary btn-sm', 'bi-pencil-square'); ?>
                                    <?php if ((int) $row['id'] !== (int) $currentUser['id']): ?>
                                        <?php render_modal_button($deleteModal, 'Hapus', 'btn btn-outline-danger btn-sm', 'bi-trash'); ?>
                                    <?php else: ?>
                                        <span class="text-muted small">Akun aktif</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php render_form_modal($editModal, 'Edit User', 'User Management', 'save_user', function () use ($row): void {
                            render_user_edit_fields($row);
                        }, 'Simpan Perubahan'); ?>
                        <?php if ((int) $row['id'] !== (int) $currentUser['id']): ?>
                            <?php render_delete_modal($deleteModal, 'Hapus User', 'Hapus/nonaktifkan user ' . $row['name'] . '? Jika punya histori transaksi, sistem akan menonaktifkannya.', 'delete_user', ['user_id' => $row['id']]); ?>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <?php if (!$rows): ?><tr><td colspan="5" class="text-center text-muted py-4">Belum ada user yang cocok.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php render_pagination($paged['page'], $paged['totalPages'], $paged['total'], $paged['perPage']); ?>
        </section>
    </section>
    <?php render_form_modal($addModal, 'Tambah User', 'User Management', 'save_user', function () use ($type): void {
        render_user_form_fields($type === 'all' ? 'customer' : $type);
    }); ?>
    <?php
}

function booking_query_for_role(string $role, array $user, ?string $status = null): array
{
    $where = [];
    $params = [];

    if ($role === 'customer') {
        $where[] = 'b.customer_id = ?';
        $params[] = $user['id'];
    } elseif ($role === 'pekerja_lapangan') {
        $where[] = 'b.assigned_pekerja_lapangan_id = ?';
        $params[] = $user['id'];
    }

    if ($status) {
        $where[] = 'b.status = ?';
        $params[] = $status;
    }

    $sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    return db_query(
        "SELECT b.*, u.name AS customer_name, u.phone AS customer_phone, fw.name AS pekerja_lapangan_name,
                s.name AS service_name
         FROM bookings b
         JOIN users u ON u.id=b.customer_id
         LEFT JOIN users fw ON fw.id=b.assigned_pekerja_lapangan_id
         LEFT JOIN services s ON s.id=b.service_id
         {$sqlWhere}
         ORDER BY b.updated_at DESC",
        $params
    );
}

function render_status_history(int $bookingId, string $role): void
{
    $rows = db_query('SELECT h.*, u.name AS user_name FROM booking_status_histories h LEFT JOIN users u ON u.id=h.changed_by WHERE h.booking_id=? ORDER BY h.created_at ASC', [$bookingId]);
    echo '<div class="list-group list-group-flush">';
    foreach ($rows as $row) {
        echo '<div class="list-group-item list-group-item-success"><strong>' . h(booking_status_label_for_role($row['new_status'], $role)) . '</strong><span>' . h(format_datetime($row['created_at'])) . ' · ' . h($row['user_name'] ?? 'System') . '</span><p>' . h(booking_status_note_for_role($row['note'] ?? '', $role)) . '</p></div>';
    }
    echo '</div>';
}

function booking_list_page_for_role(string $role): string
{
    return match ($role) {
        'pekerja_lapangan' => 'surveys',
        'boss' => 'monitoring_booking',
        default => 'booking',
    };
}

function estimate_list_page_for_role(string $role): string
{
    return $role === 'boss' ? 'monitoring_estimates' : 'estimates';
}

function invoice_list_page_for_role(string $role): string
{
    return match ($role) {
        'boss' => 'monitoring_invoices',
        'pekerja_lapangan' => 'surveys',
        default => 'invoices',
    };
}

function payment_list_page_for_role(string $role): string
{
    return match ($role) {
        'boss' => 'monitoring_payments',
        'staff' => 'payments',
        'pekerja_lapangan' => 'surveys',
        default => 'invoices',
    };
}

function render_detail_context_panel(array $booking, string $role, string $activeModule = ''): void
{
    $bookingUrl = 'dashboard.php?page=booking_detail&id=' . (int) $booking['id'];
    ?>
    <aside class="card card-body shadow-sm card card-body shadow-sm">
        <div class="card-header bg-white d-flex align-items-start justify-content-between gap-3 py-3 px-4">
            <div><p class="small fw-bold text-uppercase text-secondary">Konteks Order</p><h2><?= h($booking['booking_code']) ?></h2></div>
            <span class="<?= status_badge_class($booking['status']) ?>"><?= h(booking_status_label_for_role($booking['status'], $role)) ?></span>
        </div>
        <div class="row g-3">
            <div class="col-md-6 border rounded p-3"><span>Customer</span><strong><?= h($booking['customer_name']) ?></strong></div>
            <div class="col-md-6 border rounded p-3"><span>Layanan</span><strong><?= h($booking['service_name'] ?? 'Custom Layanan') ?></strong></div>
            <div class="col-md-6 border rounded p-3"><span>Pekerja Lapangan</span><strong><?= h($booking['pekerja_lapangan_name'] ?? '-') ?></strong></div>
            <div class="col-md-6 border rounded p-3"><span>Lokasi</span><p><?= h($booking['location_address']) ?></p></div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a class="<?= $activeModule ==='booking' ? 'active' : '' ?>" href="<?= h($bookingUrl) ?>"><i class="bi bi-clipboard-check"></i><span>Ringkasan booking</span></a>
            <a class="<?= $activeModule ==='estimate' ? 'active' : '' ?>" href="dashboard.php?page=<?= h(estimate_list_page_for_role($role)) ?>"><i class="bi bi-calculator"></i><span>Daftar estimasi</span></a>
            <?php if ($role !== 'pekerja_lapangan'): ?>
                <a class="<?= $activeModule ==='invoice' ? 'active' : '' ?>" href="dashboard.php?page=<?= h(invoice_list_page_for_role($role)) ?>"><i class="bi bi-receipt"></i><span>Daftar invoice</span></a>
                <a class="<?= $activeModule ==='payment' ? 'active' : '' ?>" href="dashboard.php?page=<?= h(payment_list_page_for_role($role)) ?>"><i class="bi bi-cash-coin"></i><span>Daftar pembayaran</span></a>
            <?php endif; ?>
        </div>
    </aside>
    <?php
}

function render_estimate_detail(array $user, string $role): void
{
    $estimateId = (int) ($_GET['id'] ?? 0);
    $estimate = db_first(
        "SELECT e.*, b.id AS booking_id, b.booking_code, b.customer_id, b.assigned_pekerja_lapangan_id, b.status AS booking_status,
                b.location_address, u.name AS customer_name, fw.name AS pekerja_lapangan_name,
                s.name AS service_name
         FROM estimates e
         JOIN bookings b ON b.id=e.booking_id
         JOIN users u ON u.id=b.customer_id
         LEFT JOIN users fw ON fw.id=b.assigned_pekerja_lapangan_id
         LEFT JOIN services s ON s.id=b.service_id
         WHERE e.id=?",
        [$estimateId]
    );
    if (!$estimate) {
        echo '<div class="alert alert-warning">Estimasi tidak ditemukan.</div>';
        return;
    }
    $booking = booking_for_access((int) $estimate['booking_id'], $user);
    if (!$booking) {
        echo '<div class="alert alert-warning">Estimasi tidak dapat diakses.</div>';
        return;
    }

    $items = db_query('SELECT * FROM estimate_items WHERE estimate_id=? ORDER BY id', [$estimateId]);
    ?>
    <section class="d-flex align-items-start justify-content-between gap-3 p-4 mb-3 rounded border bg-white">
        <div>
            <a class="btn btn-outline-secondary btn-sm mb-3" href="dashboard.php?page=<?= h(estimate_list_page_for_role($role)) ?>"><i class="bi bi-arrow-left"></i> Kembali ke estimasi</a>
            <p class="small fw-bold text-uppercase text-secondary">Detail Estimasi</p>
            <h2><?= h($estimate['estimate_code']) ?></h2>
            <p><?= h($estimate['booking_code']) ?> · <?= h($estimate['customer_name']) ?> · <?= h($estimate['service_name'] ?? 'Custom Layanan') ?></p>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <span class="<?= status_badge_class($estimate['status']) ?>"><?= h($estimate['status']) ?></span>
            <?php if ($role === 'staff' && $estimate['status'] === 'Draft'): ?>
                <?php render_api_form('send_estimate', '<i class="bi bi-send"></i> Review & Kirim', ['estimate_id' => $estimateId], 'd-flex flex-wrap align-items-center gap-2', 'btn btn-primary btn-sm'); ?>
            <?php endif; ?>
        </div>
    </section>

    <section class="row g-3">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex align-items-start justify-content-between gap-3 py-3 px-4"><div><p class="small fw-bold text-uppercase text-secondary">Rincian Harga</p><h2><?= format_rupiah($estimate['grand_total']) ?></h2></div></div>
            <div class="row g-3 mb-3">
                <div class="col-md-6 border rounded p-3"><span>Area / blok</span><strong><?= h($estimate['area_size']) ?> m2 · <?= h($estimate['block_count']) ?> blok</strong></div>
                <div class="col-md-6 border rounded p-3"><span>Durasi</span><strong><?= (int) $estimate['estimated_days'] ?> hari</strong></div>
                <div class="col-md-6 border rounded p-3"><span>Jumlah tukang</span><strong><?= (int) $estimate['worker_count'] ?> orang</strong></div>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0"><thead><tr><th>Item</th><th>Qty</th><th>Harga</th><th>Subtotal</th></tr></thead><tbody>
                    <?php foreach ($items as $item): ?>
                        <tr><td><?= h($item['item_name']) ?><br><small><?= h($item['item_type']) ?></small></td><td><?= h($item['quantity'] . ' ' . $item['unit']) ?></td><td><?= format_rupiah($item['unit_price']) ?></td><td><?= format_rupiah($item['subtotal']) ?></td></tr>
                    <?php endforeach; ?>
                    <?php if (!$items): ?><tr><td colspan="4" class="text-center text-muted py-4">Belum ada item estimasi.</td></tr><?php endif; ?>
                </tbody></table>
            </div>
            <?php if ($role === 'customer' && $estimate['status'] === 'Dikirim'): ?>
                <form data-api-form class="d-flex flex-wrap gap-2 align-items-start p-3 border rounded bg-light mt-3" method="post">
                    <?= csrf_field() ?><input type="hidden" name="estimate_id" value="<?= $estimateId ?>">
                    <textarea class="form-control" name="customer_response_note" rows="2" placeholder="Catatan keputusan"></textarea>
                    <button class="btn btn-primary btn-sm" name="action" value="approve_estimate" type="submit">Setujui Estimasi</button>
                    <button class="btn btn-outline-danger btn-sm" name="action" value="reject_estimate" type="submit">Tolak Estimasi</button>
                </form>
            <?php elseif ($estimate['customer_response_note']): ?>
                <div class="mt-3 p-3 bg-light rounded border mt-3"><strong>Catatan customer</strong><p><?= h($estimate['customer_response_note']) ?></p></div>
            <?php endif; ?>
        </div>
        <?php render_detail_context_panel($booking, $role, 'estimate'); ?>
    </section>
    <?php
}

function render_revision_detail(array $user, string $role): void
{
    $revisionId = (int) ($_GET['id'] ?? 0);
    $revision = db_first(
        "SELECT r.*, b.booking_code, b.customer_id, b.assigned_pekerja_lapangan_id, b.status AS booking_status,
                u.name AS customer_name, s.name AS service_name
         FROM estimate_revisions r
         JOIN bookings b ON b.id=r.booking_id
         JOIN users u ON u.id=b.customer_id
         LEFT JOIN services s ON s.id=b.service_id
         WHERE r.id=?",
        [$revisionId]
    );
    if (!$revision) {
        echo '<div class="alert alert-warning">Revisi estimasi tidak ditemukan.</div>';
        return;
    }
    $booking = booking_for_access((int) $revision['booking_id'], $user);
    if (!$booking) {
        echo '<div class="alert alert-warning">Revisi estimasi tidak dapat diakses.</div>';
        return;
    }

    $items = db_query('SELECT * FROM estimate_revision_items WHERE revision_id=? ORDER BY id', [$revisionId]);
    $backUrl = in_array($role, ['staff', 'pekerja_lapangan'], true)
        ? 'dashboard.php?page=revisions'
        : 'dashboard.php?page=booking_detail&id=' . (int) $booking['id'] . '&section=estimate';
    $backLabel = in_array($role, ['staff', 'pekerja_lapangan'], true) ? 'Kembali ke revisi' : 'Kembali ke booking';
    ?>
    <section class="d-flex align-items-start justify-content-between gap-3 p-4 mb-3 rounded border bg-white">
        <div>
            <a class="btn btn-outline-secondary btn-sm mb-3" href="<?= h($backUrl) ?>"><i class="bi bi-arrow-left"></i> <?= h($backLabel) ?></a>
            <p class="small fw-bold text-uppercase text-secondary">Detail Revisi</p>
            <h2><?= h($revision['revision_code']) ?></h2>
            <p><?= h($revision['booking_code']) ?> · <?= h($revision['customer_name']) ?> · <?= h($revision['service_name'] ?? 'Custom Layanan') ?></p>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <span class="<?= status_badge_class($revision['status']) ?>"><?= h($revision['status']) ?></span>
            <?php if ($role === 'staff' && $revision['status'] === 'Draft'): ?>
                <?php render_api_form('send_revision', '<i class="bi bi-send"></i> Review & Kirim', ['revision_id' => $revisionId], 'd-flex flex-wrap align-items-center gap-2', 'btn btn-primary btn-sm'); ?>
            <?php endif; ?>
        </div>
    </section>

    <section class="row g-3">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex align-items-start justify-content-between gap-3 py-3 px-4"><div><p class="small fw-bold text-uppercase text-secondary">Rincian Revisi</p><h2><?= format_rupiah($revision['new_total']) ?></h2></div></div>
            <div class="row g-3 mb-3">
                <div class="col-md-6 border rounded p-3"><span>Total lama</span><strong><?= format_rupiah($revision['old_total']) ?></strong></div>
                <div class="col-md-6 border rounded p-3"><span>Tambahan</span><strong><?= format_rupiah($revision['additional_total']) ?></strong></div>
                <div class="col-md-6 border rounded p-3"><span>Alasan revisi</span><p><?= h($revision['reason']) ?></p></div>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0"><thead><tr><th>Item</th><th>Qty</th><th>Harga</th><th>Subtotal</th></tr></thead><tbody>
                    <?php foreach ($items as $item): ?>
                        <tr><td><?= h($item['item_name']) ?><br><small><?= h($item['description'] ?: '-') ?></small></td><td><?= h($item['quantity'] . ' ' . $item['unit']) ?></td><td><?= format_rupiah($item['unit_price']) ?></td><td><?= format_rupiah($item['subtotal']) ?></td></tr>
                    <?php endforeach; ?>
                    <?php if (!$items): ?><tr><td colspan="4" class="text-center text-muted py-4">Belum ada item revisi.</td></tr><?php endif; ?>
                </tbody></table>
            </div>
            <?php if ($role === 'customer' && $revision['status'] === 'Dikirim'): ?>
                <form data-api-form class="d-flex flex-wrap gap-2 align-items-start p-3 border rounded bg-light mt-3" method="post">
                    <?= csrf_field() ?><input type="hidden" name="revision_id" value="<?= $revisionId ?>">
                    <textarea class="form-control" name="customer_response_note" rows="2" placeholder="Catatan keputusan revisi"></textarea>
                    <button class="btn btn-primary btn-sm" name="action" value="approve_revision" type="submit">Setujui Revisi</button>
                    <button class="btn btn-outline-danger btn-sm" name="action" value="reject_revision" type="submit">Tolak Revisi</button>
                </form>
            <?php elseif ($revision['customer_response_note']): ?>
                <div class="mt-3 p-3 bg-light rounded border mt-3"><strong>Catatan customer</strong><p><?= h($revision['customer_response_note']) ?></p></div>
            <?php endif; ?>
        </div>
        <?php render_detail_context_panel($booking, $role, 'estimate'); ?>
    </section>
    <?php
}

function render_invoice_detail(array $user, string $role): void
{
    if ($role === 'pekerja_lapangan') {
        echo '<div class="alert alert-warning">Invoice hanya dapat diakses oleh customer, staff, dan boss.</div>';
        return;
    }

    $invoiceId = (int) ($_GET['id'] ?? 0);
    $invoice = db_first(
        "SELECT i.*, b.booking_code, b.customer_id, b.assigned_pekerja_lapangan_id, b.status AS booking_status,
                u.name AS customer_name, s.name AS service_name
         FROM final_invoices i
         JOIN bookings b ON b.id=i.booking_id
         JOIN users u ON u.id=b.customer_id
         LEFT JOIN services s ON s.id=b.service_id
         WHERE i.id=?",
        [$invoiceId]
    );
    if (!$invoice) {
        echo '<div class="alert alert-warning">Invoice tidak ditemukan.</div>';
        return;
    }
    $booking = booking_for_access((int) $invoice['booking_id'], $user);
    if (!$booking) {
        echo '<div class="alert alert-warning">Invoice tidak dapat diakses.</div>';
        return;
    }

    $items = db_query('SELECT * FROM final_invoice_items WHERE final_invoice_id=? ORDER BY id', [$invoiceId]);
    $isSurveyFeeInvoice = (bool) array_filter($items, fn ($item) => ($item['item_name'] ?? '') === 'Survey Fee');
    $payments = db_query('SELECT p.*, u.name AS marked_by_name FROM payments p LEFT JOIN users u ON u.id=p.marked_by WHERE p.invoice_id=? ORDER BY p.created_at DESC', [$invoiceId]);
    ?>
    <section class="d-flex align-items-start justify-content-between gap-3 p-4 mb-3 rounded border bg-white">
        <div>
            <a class="btn btn-outline-secondary btn-sm mb-3" href="dashboard.php?page=<?= h(invoice_list_page_for_role($role)) ?>"><i class="bi bi-arrow-left"></i> Kembali ke invoice</a>
            <p class="small fw-bold text-uppercase text-secondary">Detail Invoice</p>
            <h2><?= h($invoice['invoice_code']) ?></h2>
            <p><?= h($invoice['booking_code']) ?> · <?= h($invoice['customer_name']) ?> · <?= h($invoice['service_name'] ?? 'Custom Layanan') ?></p>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <span class="<?= status_badge_class($invoice['status']) ?>"><?= h($invoice['status']) ?></span>
            <span class="<?= status_badge_class($invoice['payment_status']) ?>"><?= h($invoice['payment_status']) ?></span>
            <a class="btn btn-primary btn-sm" href="invoice_print.php?id=<?= $invoiceId ?>" target="_blank"><i class="bi bi-printer"></i>Cetak</a>
            <?php if ($role === 'staff' && $invoice['status'] === 'Draft'): ?>
                <?php render_api_form('send_invoice', '<i class="bi bi-send-check"></i> Review & Kirim', ['invoice_id' => $invoiceId], 'd-flex flex-wrap align-items-center gap-2', 'btn btn-outline-secondary btn-sm'); ?>
            <?php endif; ?>
            <?php if ($role === 'staff' && $invoice['payment_status'] === 'Belum Dibayar'): ?>
                <?php render_api_form('mark_invoice_paid', '<i class="bi bi-cash-coin"></i> Tandai Lunas', ['invoice_id' => $invoiceId], 'd-flex flex-wrap align-items-center gap-2', 'btn btn-success btn-sm', 'Tandai invoice ' . $invoice['invoice_code'] . ' sebagai lunas?'); ?>
            <?php endif; ?>
        </div>
    </section>

    <section class="row g-3">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex align-items-start justify-content-between gap-3 py-3 px-4"><div><p class="small fw-bold text-uppercase text-secondary"><?= $isSurveyFeeInvoice ? 'Invoice Survey Fee' : 'Rincian Invoice' ?></p><h2><?= format_rupiah($invoice['final_total']) ?></h2></div></div>
            <div class="row g-3 mb-3">
                <?php if ($isSurveyFeeInvoice): ?>
                    <div class="col-md-6 border rounded p-3"><span>Jenis tagihan</span><strong>Survey Fee</strong></div>
                    <div class="col-md-6 border rounded p-3"><span>Nominal wajib</span><strong><?= format_rupiah($invoice['final_total']) ?></strong></div>
                <?php else: ?>
                    <div class="col-md-6 border rounded p-3"><span>Subtotal estimasi</span><strong><?= format_rupiah($invoice['subtotal_estimate']) ?></strong></div>
                    <div class="col-md-6 border rounded p-3"><span>Total revisi</span><strong><?= format_rupiah($invoice['revision_total']) ?></strong></div>
                    <div class="col-md-6 border rounded p-3"><span>Hari aktual</span><strong><?= (int) $invoice['work_days_actual'] ?> hari</strong></div>
                    <div class="col-md-6 border rounded p-3"><span>Tukang aktual</span><strong><?= (int) $invoice['worker_count_actual'] ?> orang</strong></div>
                <?php endif; ?>
                <div class="col-md-6 border rounded p-3"><span>Catatan invoice</span><p><?= h($invoice['notes'] ?: '-') ?></p></div>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0"><thead><tr><th>Item</th><th>Tipe</th><th>Qty</th><th>Harga</th><th>Subtotal</th></tr></thead><tbody>
                    <?php foreach ($items as $item): ?>
                        <tr><td><?= h($item['item_name']) ?><br><small><?= h($item['description'] ?: '-') ?></small></td><td><?= h($item['item_type']) ?></td><td><?= h($item['quantity'] . ' ' . $item['unit']) ?></td><td><?= format_rupiah($item['unit_price']) ?></td><td><?= format_rupiah($item['subtotal']) ?></td></tr>
                    <?php endforeach; ?>
                    <?php if (!$items): ?><tr><td colspan="5" class="text-center text-muted py-4">Belum ada item invoice.</td></tr><?php endif; ?>
                </tbody></table>
            </div>
            <div class="mt-3 p-3 bg-light rounded border mt-3">
                <strong>Riwayat pembayaran</strong>
                <?php if ($payments): ?>
                    <div class="list-group list-group-flush mt-2">
                        <?php foreach ($payments as $pay): ?>
                            <div class="list-group-item d-flex align-items-start justify-content-between gap-3 px-0"><div><strong><?= h($pay['payment_type']) ?> · <?= format_rupiah($pay['amount']) ?></strong><span><?= h($pay['marked_by_name'] ?? 'Staff') ?> · <?= h(format_datetime($pay['paid_at'] ?: $pay['created_at'])) ?></span></div><span class="<?= status_badge_class($pay['payment_status']) ?>"><?= h($pay['payment_status']) ?></span></div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p>Belum ada pembayaran yang ditandai.</p>
                <?php endif; ?>
            </div>
        </div>
        <?php render_detail_context_panel($booking, $role, 'invoice'); ?>
    </section>
    <?php
}

function render_payment_detail(array $user, string $role): void
{
    if ($role === 'pekerja_lapangan') {
        echo '<div class="alert alert-warning">Pembayaran hanya dapat diakses oleh customer, staff, dan boss.</div>';
        return;
    }

    $type = ($_GET['type'] ?? 'invoice') === 'survey_fee' ? 'survey_fee' : 'invoice';
    $id = (int) ($_GET['id'] ?? 0);

    if ($type === 'survey_fee') {
        $booking = booking_for_access($id, $user);
        if (!$booking) {
            echo '<div class="alert alert-warning">Pembayaran survey fee tidak dapat diakses.</div>';
            return;
        }
        $feeInvoice = db_first(
            "SELECT i.id, i.invoice_code
             FROM final_invoices i
             JOIN final_invoice_items item ON item.final_invoice_id=i.id
             WHERE i.booking_id=? AND item.item_name='Survey Fee'
             LIMIT 1",
            [$id]
        );
        if ($feeInvoice) {
            echo '<div class="alert alert-info">Survey fee sudah masuk ke invoice final wajib <a href="dashboard.php?page=payment_detail&type=invoice&id=' . (int) $feeInvoice['id'] . '">' . h($feeInvoice['invoice_code']) . '</a>.</div>';
            return;
        }
        $payments = db_query("SELECT p.*, u.name AS marked_by_name FROM payments p LEFT JOIN users u ON u.id=p.marked_by WHERE p.booking_id=? AND p.payment_type='Survey Fee' ORDER BY p.created_at DESC", [$id]);
        $latestProof = null;
        foreach ($payments as $pay) {
            if (!empty($pay['payment_proof'])) { $latestProof = $pay['payment_proof']; break; }
        }
        ?>
        <section class="d-flex align-items-start justify-content-between gap-3 p-4 mb-3 rounded border bg-white">
            <div>
                <a class="btn btn-outline-secondary btn-sm mb-3" href="dashboard.php?page=<?= h(payment_list_page_for_role($role)) ?>"><i class="bi bi-arrow-left"></i> Kembali ke pembayaran</a>
                <p class="small fw-bold text-uppercase text-secondary">Detail Pembayaran</p>
                <h2>Survey Fee <?= h($booking['booking_code']) ?></h2>
                <p><?= h($booking['customer_name']) ?> · <?= h($booking['service_name'] ?? 'Custom Layanan') ?></p>
            </div>
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <span class="<?= status_badge_class($booking['survey_fee_payment_status']) ?>"><?= h($booking['survey_fee_payment_status']) ?></span>
                <?php if ($role === 'staff' && $booking['survey_fee_payment_status'] === 'Belum Dibayar'): ?>
                    <?php render_api_form('mark_survey_fee_paid', '<i class="bi bi-cash"></i> Tandai Lunas', ['booking_id' => $id], 'd-flex flex-wrap align-items-center gap-2', 'btn btn-success btn-sm', 'Tandai survey fee ' . $booking['booking_code'] . ' sebagai lunas?'); ?>
                <?php endif; ?>
            </div>
        </section>
        <section class="row g-3">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex align-items-start justify-content-between gap-3 py-3 px-4"><div><p class="small fw-bold text-uppercase text-secondary">Survey Fee</p><h2><?= format_rupiah($booking['survey_fee_amount']) ?></h2></div></div>
                <div class="row g-3 mb-3">
                    <div class="col-md-6 border rounded p-3"><span>Status pembayaran</span><strong><?= h($booking['survey_fee_payment_status']) ?></strong></div>
                    <div class="col-md-6 border rounded p-3"><span>Status order</span><strong><?= h($booking['status']) ?></strong></div>
                </div>
                <?php if ($role === 'customer' && $booking['survey_fee_payment_status'] === 'Belum Dibayar'): ?>
                <div class="card card-body shadow-sm mt-3">
                    <div class="card-header bg-white d-flex align-items-start justify-content-between gap-3 py-3 px-4"><div><p class="small fw-bold text-uppercase text-secondary">Upload Bukti Bayar</p><h3>Kirim bukti transfer survey fee</h3></div></div>
                    <form data-api-form method="post" enctype="multipart/form-data" class="d-flex flex-column gap-3">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="upload_payment_proof">
                        <input type="hidden" name="booking_id" value="<?= $id ?>">
                        <div><label class="form-label">Foto / screenshot bukti transfer <span class="text-danger">*</span></label>
                        <input class="form-control" type="file" name="payment_proof" accept=".jpg,.jpeg,.png,.webp" required></div>
                        <div><label class="form-label">Catatan (opsional)</label>
                        <input class="form-control" type="text" name="note" placeholder="Misal: transfer via BCA a.n. Budi"></div>
                        <button class="btn btn-primary" type="submit"><i class="bi bi-upload me-1"></i>Kirim Bukti Bayar</button>
                    </form>
                </div>
                <?php endif; ?>
                <?php if ($latestProof && in_array($role, ['staff', 'boss', 'customer'], true)): ?>
                <div class="card card-body shadow-sm mt-3">
                    <div class="card-header bg-white d-flex align-items-start justify-content-between gap-3 py-3 px-4"><div><p class="small fw-bold text-uppercase text-secondary">Bukti Pembayaran</p><h3>Foto bukti transfer</h3></div></div>
                    <a href="<?= h($latestProof) ?>" target="_blank">
                        <img src="<?= h($latestProof) ?>" alt="Bukti pembayaran" class="img-fluid rounded border">
                    </a>
                </div>
                <?php endif; ?>
                <?php if ($payments): ?>
                    <div class="list-group list-group-flush mt-3">
                        <?php foreach ($payments as $pay): ?>
                            <div class="list-group-item d-flex align-items-start justify-content-between gap-3 px-0">
                                <div>
                                    <strong><?= h($pay['payment_type']) ?> · <?= format_rupiah($pay['amount']) ?></strong>
                                    <span><?= h($pay['marked_by_name'] ?? 'Customer') ?> · <?= h(format_datetime($pay['paid_at'] ?: $pay['created_at'])) ?></span>
                                    <?php if (!empty($pay['note'])): ?><span><?= h($pay['note']) ?></span><?php endif; ?>
                                </div>
                                <span class="<?= status_badge_class($pay['payment_status']) ?>"><?= h($pay['payment_status']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted small">Belum ada pembayaran survey fee yang ditandai.</p>
                <?php endif; ?>
            </div>
            <?php render_detail_context_panel($booking, $role, 'payment'); ?>
        </section>
        <?php
        return;
    }

    $invoice = db_first(
        "SELECT i.*, b.booking_code, b.customer_id, b.assigned_pekerja_lapangan_id, b.status AS booking_status,
                u.name AS customer_name, s.name AS service_name
         FROM final_invoices i
         JOIN bookings b ON b.id=i.booking_id
         JOIN users u ON u.id=b.customer_id
         LEFT JOIN services s ON s.id=b.service_id
         WHERE i.id=?",
        [$id]
    );
    if (!$invoice) {
        echo '<div class="alert alert-warning">Pembayaran invoice tidak ditemukan.</div>';
        return;
    }
    $booking = booking_for_access((int) $invoice['booking_id'], $user);
    if (!$booking) {
        echo '<div class="alert alert-warning">Pembayaran invoice tidak dapat diakses.</div>';
        return;
    }
    $payments = db_query('SELECT p.*, u.name AS marked_by_name FROM payments p LEFT JOIN users u ON u.id=p.marked_by WHERE p.invoice_id=? ORDER BY p.created_at DESC', [$id]);
    $latestProof = null;
    foreach ($payments as $pay) {
        if (!empty($pay['payment_proof'])) { $latestProof = $pay['payment_proof']; break; }
    }
    ?>
    <section class="d-flex align-items-start justify-content-between gap-3 p-4 mb-3 rounded border bg-white">
        <div>
            <a class="btn btn-outline-secondary btn-sm mb-3" href="dashboard.php?page=<?= h(payment_list_page_for_role($role)) ?>"><i class="bi bi-arrow-left"></i> Kembali ke pembayaran</a>
            <p class="small fw-bold text-uppercase text-secondary">Detail Pembayaran</p>
            <h2><?= h($invoice['invoice_code']) ?></h2>
            <p><?= h($invoice['booking_code']) ?> · <?= h($invoice['customer_name']) ?> · <?= h($invoice['service_name'] ?? 'Custom Layanan') ?></p>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <span class="<?= status_badge_class($invoice['payment_status']) ?>"><?= h($invoice['payment_status']) ?></span>
            <a class="btn btn-outline-secondary btn-sm" href="dashboard.php?page=invoice_detail&id=<?= $id ?>">Lihat Invoice</a>
            <?php if ($role === 'staff' && $invoice['payment_status'] === 'Belum Dibayar'): ?>
                <?php render_api_form('mark_invoice_paid', '<i class="bi bi-cash-coin"></i> Tandai Lunas', ['invoice_id' => $id], 'd-flex flex-wrap align-items-center gap-2', 'btn btn-success btn-sm', 'Tandai invoice ' . $invoice['invoice_code'] . ' sebagai lunas?'); ?>
            <?php endif; ?>
        </div>
    </section>
    <section class="row g-3">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex align-items-start justify-content-between gap-3 py-3 px-4"><div><p class="small fw-bold text-uppercase text-secondary">Invoice Final</p><h2><?= format_rupiah($invoice['final_total']) ?></h2></div></div>
            <div class="row g-3 mb-3">
                <div class="col-md-6 border rounded p-3"><span>Status invoice</span><strong><?= h($invoice['status']) ?></strong></div>
                <div class="col-md-6 border rounded p-3"><span>Status pembayaran</span><strong><?= h($invoice['payment_status']) ?></strong></div>
            </div>
            <?php if ($role === 'customer' && $invoice['payment_status'] === 'Belum Dibayar' && $invoice['status'] === 'Dikirim'): ?>
            <div class="card card-body shadow-sm mt-3">
                <div class="card-header bg-white d-flex align-items-start justify-content-between gap-3 py-3 px-4"><div><p class="small fw-bold text-uppercase text-secondary">Upload Bukti Bayar</p><h3>Kirim bukti transfer invoice</h3></div></div>
                <form data-api-form method="post" enctype="multipart/form-data" class="d-flex flex-column gap-3">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="upload_payment_proof">
                    <input type="hidden" name="invoice_id" value="<?= $id ?>">
                    <div><label class="form-label">Foto / screenshot bukti transfer <span class="text-danger">*</span></label>
                    <input class="form-control" type="file" name="payment_proof" accept=".jpg,.jpeg,.png,.webp" required></div>
                    <div><label class="form-label">Catatan (opsional)</label>
                    <input class="form-control" type="text" name="note" placeholder="Misal: transfer via BCA a.n. Budi"></div>
                    <button class="btn btn-primary" type="submit"><i class="bi bi-upload me-1"></i>Kirim Bukti Bayar</button>
                </form>
            </div>
            <?php endif; ?>
            <?php if ($latestProof && in_array($role, ['staff', 'boss', 'customer'], true)): ?>
            <div class="card card-body shadow-sm mt-3">
                <div class="card-header bg-white d-flex align-items-start justify-content-between gap-3 py-3 px-4"><div><p class="small fw-bold text-uppercase text-secondary">Bukti Pembayaran</p><h3>Foto bukti transfer</h3></div></div>
                <a href="<?= h($latestProof) ?>" target="_blank">
                    <img src="<?= h($latestProof) ?>" alt="Bukti pembayaran" class="img-fluid rounded border">
                </a>
            </div>
            <?php endif; ?>
            <?php if ($payments): ?>
                <div class="list-group list-group-flush mt-3">
                    <?php foreach ($payments as $pay): ?>
                        <div class="list-group-item d-flex align-items-start justify-content-between gap-3 px-0">
                            <div>
                                <strong><?= h($pay['payment_type']) ?> · <?= format_rupiah($pay['amount']) ?></strong>
                                <span><?= h($pay['marked_by_name'] ?? 'Customer') ?> · <?= h(format_datetime($pay['paid_at'] ?: $pay['created_at'])) ?></span>
                                <?php if (!empty($pay['note'])): ?><span><?= h($pay['note']) ?></span><?php endif; ?>
                                <?php if (!empty($pay['payment_proof'])): ?>
                                    <span><a href="<?= h($pay['payment_proof']) ?>" target="_blank"><i class="bi bi-image me-1"></i>Lihat bukti</a></span>
                                <?php endif; ?>
                            </div>
                            <span class="<?= status_badge_class($pay['payment_status']) ?>"><?= h($pay['payment_status']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-muted small">Belum ada pembayaran invoice yang ditandai.</p>
            <?php endif; ?>
        </div>
        <?php render_detail_context_panel($booking, $role, 'payment'); ?>
    </section>
    <?php
}

function render_booking_detail(array $user, string $role): void
{
    $bookingId = (int) ($_GET['id'] ?? 0);
    $booking = booking_for_access($bookingId, $user);
    if (!$booking) {
        echo '<div class="alert alert-warning">Booking tidak ditemukan atau tidak dapat diakses.</div>';
        return;
    }

    $schedules = db_query('SELECT sc.*, u.name AS pekerja_lapangan_name FROM schedules sc JOIN users u ON u.id=sc.pekerja_lapangan_id WHERE sc.booking_id=? ORDER BY sc.schedule_date, sc.schedule_time', [$bookingId]);
    $survey = db_first('SELECT sv.*, u.name AS pekerja_lapangan_name FROM surveys sv LEFT JOIN users u ON u.id=sv.pekerja_lapangan_id WHERE sv.booking_id=? ORDER BY sv.id DESC LIMIT 1', [$bookingId]);
    $photos = db_query('SELECT * FROM survey_photos WHERE booking_id=? ORDER BY photo_type, created_at', [$bookingId]);
    $estimate = db_first('SELECT e.* FROM estimates e WHERE e.booking_id=? ORDER BY e.id DESC LIMIT 1', [$bookingId]);
    $estimateItems = $estimate ? db_query('SELECT * FROM estimate_items WHERE estimate_id=? ORDER BY id', [$estimate['id']]) : [];
    $revision = db_first('SELECT * FROM estimate_revisions WHERE booking_id=? ORDER BY id DESC LIMIT 1', [$bookingId]);
    $revisionItems = $revision ? db_query('SELECT * FROM estimate_revision_items WHERE revision_id=? ORDER BY id', [$revision['id']]) : [];
    $progress = db_query('SELECT wp.*, u.name AS worker_name FROM work_progress wp JOIN users u ON u.id=wp.pekerja_lapangan_id WHERE wp.booking_id=? ORDER BY wp.created_at DESC', [$bookingId]);
    $invoice = db_first('SELECT * FROM final_invoices WHERE booking_id=? ORDER BY id DESC LIMIT 1', [$bookingId]);
    $invoiceItems = $invoice ? db_query('SELECT * FROM final_invoice_items WHERE final_invoice_id=? ORDER BY id', [$invoice['id']]) : [];
    $payments = db_query('SELECT p.*, u.name AS marked_by_name FROM payments p LEFT JOIN users u ON u.id=p.marked_by WHERE p.booking_id=? ORDER BY p.created_at DESC', [$bookingId]);
    $pekerjaLapanganList = get_pekerja_lapangan();
    $sectionParam = $_GET['section'] ?? 'overview';
    $activeSection = is_string($sectionParam) ? strtolower(trim($sectionParam)) : 'overview';
    $sections = [
        'overview' => ['Ringkasan', 'bi-clipboard-data'],
        'list-group list-group-flush' => ['Timeline', 'bi-clock-history'],
        'schedule' => ['Jadwal', 'bi-calendar-week'],
        'survey' => ['Survey', 'bi-camera'],
        'estimate' => ['Estimasi', 'bi-calculator'],
        'progress' => ['Progress', 'bi-graph-up-arrow'],
        'invoice' => ['Invoice', 'bi-receipt'],
        'actions' => ['Aksi', 'bi-lightning-charge'],
    ];
    if ($role === 'pekerja_lapangan') {
        unset($sections['invoice']);
    }
    if (!array_key_exists($activeSection, $sections)) {
        $activeSection = 'overview';
    }
    $sectionUrl = fn (string $key): string => 'dashboard.php?page=booking_detail&id=' . $bookingId . '&section=' . urlencode($key);
    $workflowSteps = [
        ['key' => 'overview', 'label' => 'Order', 'hint' => 'Data masuk', 'icon' => 'bi-inbox'],
        ['key' => 'schedule', 'label' => 'Survey', 'hint' => 'Jadwal & hasil', 'icon' => 'bi-calendar2-check'],
        ['key' => 'estimate', 'label' => 'Estimasi', 'hint' => 'Review harga', 'icon' => 'bi-calculator'],
        ['key' => 'progress', 'label' => 'Pekerjaan', 'hint' => 'Eksekusi lapangan', 'icon' => 'bi-tools'],
        ['key' => 'invoice', 'label' => 'Invoice', 'hint' => 'Tagihan final', 'icon' => 'bi-receipt-cutoff'],
        ['key' => 'list-group list-group-flush', 'label' => 'Selesai', 'hint' => 'Riwayat final', 'icon' => 'bi-check2-circle'],
    ];
    if ($role === 'pekerja_lapangan') {
        unset($workflowSteps[4]);
        $workflowSteps = array_values($workflowSteps);
    }
    $currentStage = match ($booking['status']) {
        'Menunggu Konfirmasi', 'Dibatalkan' => 0,
        'Survey Dijadwalkan', 'Survey Berlangsung', 'Survey Selesai' => 1,
        'Estimasi Dibuat', 'Menunggu Persetujuan Customer', 'Estimasi Ditolak' => 2,
        'Estimasi Disetujui', 'Dalam Pengerjaan', 'Revisi Estimasi', 'Pekerjaan Selesai' => 3,
        'Invoice Final Dibuat', 'Menunggu Pembayaran', 'Lunas' => 4,
        'Selesai' => 5,
        default => 0,
    };
    $guide = match (true) {
        $role === 'staff' && $booking['status'] === 'Menunggu Konfirmasi' => ['Terima & jadwalkan survey', 'Pilih pekerja lapangan dan jadwal. Ini sekaligus menjadi approval booking.', $sectionUrl('actions'), 'Atur sekarang', 'btn btn-primary btn-sm'],
        $role === 'staff' && in_array($booking['status'], ['Survey Dijadwalkan','Survey Berlangsung'], true) => ['Pantau survey pekerja lapangan', 'Survey sudah ada di jadwal. Gunakan halaman aksi hanya kalau perlu reschedule atau ganti pekerja lapangan.', $sectionUrl('schedule'), 'Lihat jadwal', 'btn btn-outline-secondary btn-sm'],
        $role === 'staff' && $estimate && $estimate['status'] === 'Draft' => ['Review estimasi', 'Estimasi pekerja lapangan masih draft. Buka detail estimasi untuk cek item harga sebelum dikirim ke customer.', 'dashboard.php?page=estimate_detail&id=' . (int) $estimate['id'], 'Review estimasi', 'btn btn-primary btn-sm'],
        $role === 'staff' && $revision && $revision['status'] === 'Draft' => ['Review revisi', 'Ada revisi estimasi yang perlu dicek sebelum customer menerima keputusan.', 'dashboard.php?page=revision_detail&id=' . (int) $revision['id'], 'Review revisi', 'btn btn-primary btn-sm'],
        $role === 'staff' && $invoice && $invoice['status'] === 'Draft' => ['Review invoice final', 'Invoice final sudah dibuat pekerja lapangan dan perlu direview sebelum dikirim.', 'dashboard.php?page=invoice_detail&id=' . (int) $invoice['id'], 'Review invoice', 'btn btn-primary btn-sm'],
        $role === 'staff' && $invoice && $invoice['payment_status'] === 'Belum Dibayar' => ['Tandai pembayaran', 'Invoice sudah dikirim. Setelah customer bayar, tandai lunas dari halaman pembayaran.', 'dashboard.php?page=payment_detail&type=invoice&id=' . (int) $invoice['id'], 'Buka pembayaran', 'btn btn-success btn-sm'],
        $role === 'staff' && $booking['status'] === 'Lunas' => ['Selesaikan order', 'Pembayaran sudah lunas. Tutup order setelah pekerjaan dan dokumen final aman.', $sectionUrl('actions'), 'Selesaikan', 'btn btn-success btn-sm'],
        $role === 'pekerja_lapangan' && $booking['status'] === 'Survey Dijadwalkan' => ['Mulai survey', 'Survey sudah ditugaskan ke Anda. Mulai survey dari halaman aksi lalu upload hasil lapangan.', $sectionUrl('actions'), 'Mulai survey', 'btn btn-primary btn-sm'],
        $role === 'pekerja_lapangan' && $booking['status'] === 'Survey Selesai' => ['Buat estimasi', 'Hasil survey sudah ada. Susun estimasi sementara untuk direview staff.', $sectionUrl('actions'), 'Buat estimasi', 'btn btn-primary btn-sm'],
        $role === 'pekerja_lapangan' && in_array($booking['status'], ['Dalam Pengerjaan','Revisi Estimasi'], true) => ['Update progress', 'Catat pekerjaan lapangan, foto progress, atau buat revisi kalau ada perubahan biaya.', $sectionUrl('actions'), 'Update pekerjaan', 'btn btn-primary btn-sm'],
        $role === 'pekerja_lapangan' && $booking['status'] === 'Pekerjaan Selesai' => ['Kirim data final', 'Pekerjaan sudah selesai. Upload foto after dan kirim data akhir ke staff.', $sectionUrl('actions'), 'Kirim data', 'btn btn-primary btn-sm'],
        $role === 'customer' && $estimate && $estimate['status'] === 'Dikirim' => ['Putuskan estimasi', 'Estimasi sudah dikirim. Buka detail estimasi untuk menyetujui atau menolak.', 'dashboard.php?page=estimate_detail&id=' . (int) $estimate['id'], 'Buka estimasi', 'btn btn-primary btn-sm'],
        $role === 'customer' && $revision && $revision['status'] === 'Dikirim' => ['Putuskan revisi', 'Ada revisi estimasi yang menunggu keputusan Anda.', 'dashboard.php?page=revision_detail&id=' . (int) $revision['id'], 'Buka revisi', 'btn btn-primary btn-sm'],
        $role === 'customer' && $invoice => ['Lihat invoice', 'Invoice final tersedia. Detail invoice dan status pembayaran ada di halaman invoice.', 'dashboard.php?page=invoice_detail&id=' . (int) $invoice['id'], 'Buka invoice', 'btn btn-primary btn-sm'],
        default => ['Pantau status order', 'Gunakan alur di bawah untuk membuka bagian yang relevan tanpa mencampur semua detail dalam satu layar.', $sectionUrl($activeSection), 'Bagian aktif', 'btn btn-outline-secondary btn-sm'],
    };
    ?>
    <section class="row g-3">
        <div class="col-lg-8">
            <a class="btn btn-outline-secondary btn-sm mb-3" href="dashboard.php?page=<?= h(booking_list_page_for_role($role)) ?>"><i class="bi bi-arrow-left"></i> Kembali ke daftar booking</a>
            <p class="small fw-bold text-uppercase text-secondary">Detail Booking</p>
            <h2><?= h($booking['booking_code']) ?></h2>
            <p><?= h($booking['customer_name']) ?> · <?= h($booking['service_name'] ?? 'Custom Layanan') ?> · <?= h($booking['location_address']) ?></p>
            <ol class="list-group list-group-horizontal-lg" aria-label="Alur booking">
                <?php foreach ($workflowSteps as $index => $step): ?>
                    <?php $state = $index < $currentStage ? 'list-group-item-success' : ($index === $currentStage ? 'list-group-item-primary' : ''); ?>
                    <li class="list-group-item flex-fill <?= h($state) ?>">
                        <a class="d-flex flex-column text-decoration-none" href="<?= h($sectionUrl($step['key'])) ?>">
                            <i class="bi <?= h($step['icon']) ?>"></i>
                            <span><?= h($step['label']) ?></span>
                            <small><?= h($step['hint']) ?></small>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ol>
        </div>
        <aside class="col-lg-4 card card-body shadow-sm">
            <span class="<?= status_badge_class($booking['status']) ?>"><?= h(booking_status_label_for_role($booking['status'], $role)) ?></span>
            <h3><?= h($guide[0]) ?></h3>
            <p><?= h($guide[1]) ?></p>
            <a class="<?= h($guide[4]) ?>" href="<?= h($guide[2]) ?>"><?= h($guide[3]) ?></a>
            <small>Survey fee: <?= format_rupiah($booking['survey_fee_amount']) ?> · <?= h($booking['survey_fee_payment_status']) ?></small>
        </aside>
    </section>

    <nav class="nav nav-pills flex-wrap gap-2 mb-3" aria-label="Navigasi detail booking">
        <?php foreach ($sections as $key => [$label, $icon]): ?>
            <a class="<?= $activeSection === $key ?'active' : '' ?>" href="<?= h($sectionUrl($key)) ?>" <?= $activeSection === $key ? 'aria-current="page"' : '' ?>>
                <i class="bi <?= h($icon) ?>"></i>
                <span><?= h($label) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <?php if ($activeSection === 'overview'): ?>
        <section class="row g-3 d-flex flex-column gap-3">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex align-items-start justify-content-between gap-3 py-3 px-4">
                    <div><p class="small fw-bold text-uppercase text-secondary">Order</p><h2>Identitas booking</h2></div>
                    <span class="<?= status_badge_class($booking['status']) ?>"><?= h(booking_status_label_for_role($booking['status'], $role)) ?></span>
                </div>
                <div class="row g-3">
                    <div class="col-md-6 border rounded p-3"><span>Customer</span><strong><?= h($booking['customer_name']) ?></strong></div>
                    <div class="col-md-6 border rounded p-3"><span>Email</span><strong><?= h($booking['customer_email']) ?></strong></div>
                    <div class="col-md-6 border rounded p-3"><span>No HP</span><strong><?= h($booking['customer_phone'] ?? '-') ?></strong></div>
                    <div class="col-md-6 border rounded p-3"><span>Layanan</span><strong><?= h($booking['service_name'] ?? 'Custom Layanan') ?></strong></div>
                    <div class="col-md-6 border rounded p-3"><span>Pekerja Lapangan</span><strong><?= h($booking['pekerja_lapangan_name'] ?? '-') ?></strong></div>
                    <div class="col-md-6 border rounded p-3"><span>Alamat</span><strong><?= h($booking['location_address']) ?></strong></div>
                    <div class="col-md-6 border rounded p-3"><span>Luas estimasi / aktual</span><strong><?= h(($booking['estimated_area'] ?? '-') . ' / ' . ($booking['actual_area'] ?? '-')) ?> m2</strong></div>
                    <div class="col-md-6 border rounded p-3"><span>Survey diminta</span><strong><?= h(format_date_id($booking['requested_survey_date'])) ?></strong></div>
                    <div class="col-md-6 border rounded p-3"><span>Survey fee</span><strong><?= format_rupiah($booking['survey_fee_amount']) ?> · <?= h($booking['survey_fee_payment_status']) ?></strong></div>
                    <div class="col-md-6 border rounded p-3"><span>Catatan customer</span><p><?= h($booking['customer_notes'] ?: '-') ?></p></div>
                    <?php if ($booking['custom_service_description']): ?><div class="col-md-6 border rounded p-3"><span>Custom layanan</span><p><?= h($booking['custom_service_description']) ?></p></div><?php endif; ?>
                </div>
            </div>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex align-items-start justify-content-between gap-3 py-3 px-4"><div><p class="small fw-bold text-uppercase text-secondary">Ringkas</p><h2>Status utama</h2></div></div>
                <div class="list-group list-group-flush">
                    <div><span>Survey</span><strong><?= $survey ? 'Selesai' : 'Belum lengkap' ?></strong></div>
                    <div><span>Estimasi</span><strong><?= h($estimate['status'] ?? 'Belum dibuat') ?></strong></div>
                    <div><span>Pekerjaan</span><strong><?= count($progress) ?> update</strong></div>
                    <?php if ($role !== 'pekerja_lapangan'): ?>
                        <div><span>Invoice</span><strong><?= h($invoice ? ($invoice['payment_status'] ?? $invoice['status']) : 'Belum dibuat') ?></strong></div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

    <?php elseif ($activeSection === 'list-group list-group-flush'): ?>
        <section class="card border-0 shadow-sm d-flex flex-column gap-3">
            <div class="card-header bg-white d-flex align-items-start justify-content-between gap-3 py-3 px-4"><div><p class="small fw-bold text-uppercase text-secondary">Timeline</p><h2>Status order</h2></div></div>
            <?php render_status_history($bookingId, $role); ?>
        </section>

    <?php elseif ($activeSection === 'schedule'): ?>
        <section class="card border-0 shadow-sm d-flex flex-column gap-3">
            <div class="card-header bg-white d-flex align-items-start justify-content-between gap-3 py-3 px-4"><div><p class="small fw-bold text-uppercase text-secondary">Jadwal</p><h2>Survey dan pekerjaan</h2></div></div>
            <div class="list-group list-group-flush">
                <?php foreach ($schedules as $row): ?>
                    <div class="list-group-item d-flex align-items-start justify-content-between gap-3 px-0"><div><strong><?= h($row['schedule_code']) ?> · <?= h($row['schedule_type']) ?></strong><span><?= h(format_date_id($row['schedule_date']) . ' ' . substr($row['schedule_time'], 0, 5)) ?> · <?= h($row['pekerja_lapangan_name']) ?></span></div><span class="<?= status_badge_class($row['status']) ?>"><?= h($row['status']) ?></span></div>
                <?php endforeach; ?>
                <?php if (!$schedules): ?><div class="list-group-item d-flex align-items-start justify-content-between gap-3 px-0"><div><strong>Belum ada jadwal</strong><span>Staff belum menjadwalkan survey.</span></div></div><?php endif; ?>
            </div>
        </section>

    <?php elseif ($activeSection === 'survey'): ?>
        <section class="row g-3 d-flex flex-column gap-3">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex align-items-start justify-content-between gap-3 py-3 px-4"><div><p class="small fw-bold text-uppercase text-secondary">Survey</p><h2>Hasil survey pekerja lapangan</h2></div></div>
                <?php if ($survey): ?>
                    <div class="row g-3">
                        <div class="col-md-6 border rounded p-3"><span>Luas aktual</span><strong><?= h($survey['actual_area']) ?> m2</strong></div>
                        <div class="col-md-6 border rounded p-3"><span>Kondisi lokasi</span><p><?= h($survey['location_condition']) ?></p></div>
                        <div class="col-md-6 border rounded p-3"><span>Akses lokasi</span><p><?= h($survey['access_condition']) ?></p></div>
                        <div class="col-md-6 border rounded p-3"><span>Kendala</span><p><?= h($survey['obstacle_notes'] ?: '-') ?></p></div>
                        <div class="col-md-6 border rounded p-3"><span>Durasi/tukang</span><strong><?= (int) $survey['estimated_work_days'] ?> hari · <?= (int) $survey['estimated_worker_count'] ?> tukang</strong></div>
                    </div>
                <?php else: ?><p class="text-muted small">Belum ada hasil survey.</p><?php endif; ?>
            </div>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex align-items-start justify-content-between gap-3 py-3 px-4"><div><p class="small fw-bold text-uppercase text-secondary">Foto Lokal</p><h2>Before / Progress / After</h2></div></div>
                <div class="row g-3">
                    <?php foreach ($photos as $photo): ?>
                        <figure><img src="<?= h($photo['photo_path']) ?>" alt="<?= h($photo['photo_type']) ?>"><figcaption><?= h($photo['photo_type']) ?></figcaption></figure>
                    <?php endforeach; ?>
                    <?php if (!$photos): ?><figure><img src="assets/img/placeholders/upload-before.svg" alt="Placeholder before"><figcaption>Belum ada foto</figcaption></figure><?php endif; ?>
                </div>
            </div>
        </section>

    <?php elseif ($activeSection === 'estimate'): ?>
        <section class="row g-3 d-flex flex-column gap-3">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex align-items-start justify-content-between gap-3 py-3 px-4"><div><p class="small fw-bold text-uppercase text-secondary">Estimasi</p><h2><?= h($estimate['estimate_code'] ?? 'Belum ada estimasi') ?></h2></div><?= $estimate ? '<span class="' . status_badge_class($estimate['status']) . '">' . h($estimate['status']) . '</span>' : '' ?></div>
                <?php if ($estimate): ?>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6 border rounded p-3"><span>Area / blok</span><strong><?= h($estimate['area_size']) ?> m2 · <?= h($estimate['block_count']) ?> blok</strong></div>
                        <div class="col-md-6 border rounded p-3"><span>Total estimasi</span><strong><?= format_rupiah($estimate['grand_total']) ?></strong></div>
                    </div>
                    <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Item</th><th>Qty</th><th>Harga</th><th>Subtotal</th></tr></thead><tbody>
                        <?php foreach ($estimateItems as $item): ?><tr><td><?= h($item['item_name']) ?><br><small><?= h($item['item_type']) ?></small></td><td><?= h($item['quantity'] . ' ' . $item['unit']) ?></td><td><?= format_rupiah($item['unit_price']) ?></td><td><?= format_rupiah($item['subtotal']) ?></td></tr><?php endforeach; ?>
                    </tbody></table></div>
                <?php else: ?><p class="text-muted small">Estimasi belum dibuat.</p><?php endif; ?>
            </div>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex align-items-start justify-content-between gap-3 py-3 px-4"><div><p class="small fw-bold text-uppercase text-secondary">Revisi</p><h2>Revisi estimasi</h2></div></div>
                <?php if ($revision): ?>
                    <div class="list-group-item d-flex align-items-start justify-content-between gap-3 px-0 mb-3"><div><strong><?= h($revision['revision_code']) ?> · <?= format_rupiah($revision['new_total']) ?></strong><span><?= h($revision['reason']) ?></span></div><span class="<?= status_badge_class($revision['status']) ?>"><?= h($revision['status']) ?></span></div>
                    <?php if ($revisionItems): ?>
                        <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Item</th><th>Qty</th><th>Harga</th><th>Subtotal</th></tr></thead><tbody>
                            <?php foreach ($revisionItems as $item): ?><tr><td><?= h($item['item_name']) ?><br><small><?= h($item['description'] ?: '-') ?></small></td><td><?= h($item['quantity'] . ' ' . $item['unit']) ?></td><td><?= format_rupiah($item['unit_price']) ?></td><td><?= format_rupiah($item['subtotal']) ?></td></tr><?php endforeach; ?>
                        </tbody></table></div>
                    <?php endif; ?>
                <?php else: ?><p class="text-muted small">Belum ada revisi estimasi.</p><?php endif; ?>
            </div>
        </section>

    <?php elseif ($activeSection === 'progress'): ?>
        <section class="card border-0 shadow-sm d-flex flex-column gap-3">
            <div class="card-header bg-white d-flex align-items-start justify-content-between gap-3 py-3 px-4"><div><p class="small fw-bold text-uppercase text-secondary">Progress Pekerjaan</p><h2>Catatan lapangan</h2></div></div>
            <div class="list-group list-group-flush">
                <?php foreach ($progress as $row): ?><article class="list-group-item px-0"><div><span class="small text-secondary"><?= h(format_datetime($row['created_at'])) ?> · <?= h($row['worker_name']) ?></span><strong><?= h($row['title']) ?> · <?= (int) $row['progress_percent'] ?>%</strong><p><?= h($row['note']) ?></p><p><?= h($row['obstacle_note']) ?></p></div></article><?php endforeach; ?>
                <?php if (!$progress): ?><p class="text-muted small">Belum ada progress pekerjaan.</p><?php endif; ?>
            </div>
        </section>

    <?php elseif ($activeSection === 'invoice'): ?>
        <section class="row g-3 d-flex flex-column gap-3">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex align-items-start justify-content-between gap-3 py-3 px-4">
                    <div><p class="small fw-bold text-uppercase text-secondary">Invoice</p><h2><?= h($invoice['invoice_code'] ?? 'Invoice final belum dibuat') ?></h2></div>
                    <?php if ($invoice): ?>
	                        <div class="d-flex flex-wrap gap-2">
	                            <span class="<?= status_badge_class($invoice['status']) ?>"><?= h($invoice['status']) ?></span>
	                            <a class="btn btn-primary btn-sm" href="invoice_print.php?id=<?= (int) $invoice['id'] ?>" target="_blank"><i class="bi bi-printer"></i>Cetak</a>
                                <?php if ($role === 'staff' && $invoice['status'] === 'Draft'): ?>
                                    <?php render_api_form('send_invoice', '<i class="bi bi-send-check"></i> Review & Kirim', ['invoice_id' => $invoice['id']], 'd-flex flex-wrap align-items-center gap-2', 'btn btn-outline-secondary btn-sm'); ?>
                                <?php endif; ?>
                                <?php if ($role === 'staff' && $invoice['payment_status'] === 'Belum Dibayar'): ?>
                                    <?php render_api_form('mark_invoice_paid', '<i class="bi bi-cash-coin"></i> Tandai Lunas', ['invoice_id' => $invoice['id']], 'd-flex flex-wrap align-items-center gap-2', 'btn btn-success btn-sm', 'Tandai invoice ' . $invoice['invoice_code'] . ' sebagai lunas?'); ?>
                                <?php endif; ?>
	                        </div>
	                    <?php endif; ?>
                </div>
                <?php if ($invoice): ?>
                    <div class="row g-3">
                        <div class="col-md-6 border rounded p-3"><span>Total final</span><strong><?= format_rupiah($invoice['final_total']) ?></strong></div>
                        <div class="col-md-6 border rounded p-3"><span>Status invoice</span><strong><span class="<?= status_badge_class($invoice['status']) ?>"><?= h($invoice['status']) ?></span></strong></div>
                        <div class="col-md-6 border rounded p-3"><span>Status pembayaran</span><strong><span class="<?= status_badge_class($invoice['payment_status']) ?>"><?= h($invoice['payment_status']) ?></span></strong></div>
                    </div>
                    <div class="table-responsive mt-3"><table class="table table-sm align-middle mb-0"><thead><tr><th>Item</th><th>Tipe</th><th>Qty</th><th>Harga</th><th>Subtotal</th></tr></thead><tbody>
                        <?php foreach ($invoiceItems as $item): ?><tr><td><?= h($item['item_name']) ?><br><small><?= h($item['description'] ?: '-') ?></small></td><td><?= h($item['item_type']) ?></td><td><?= h($item['quantity'] . ' ' . $item['unit']) ?></td><td><?= format_rupiah($item['unit_price']) ?></td><td><?= format_rupiah($item['subtotal']) ?></td></tr><?php endforeach; ?>
                    </tbody></table></div>
                <?php else: ?><p class="text-muted small">Invoice final belum dibuat.</p><?php endif; ?>
            </div>
            <div class="card border-0 shadow-sm">
	                <div class="card-header bg-white d-flex align-items-start justify-content-between gap-3 py-3 px-4"><div><p class="small fw-bold text-uppercase text-secondary">Pembayaran</p><h2>Riwayat pembayaran</h2></div></div>
                    <?php if ($role === 'staff' && $booking['survey_fee_payment_status'] === 'Belum Dibayar' && !$invoice): ?>
                        <div class="mb-3"><?php render_api_form('mark_survey_fee_paid', '<i class="bi bi-cash"></i> Tandai Survey Fee Lunas', ['booking_id' => $bookingId], 'd-flex flex-wrap align-items-center gap-2', 'btn btn-success btn-sm', 'Tandai survey fee ' . $booking['booking_code'] . ' sebagai lunas?'); ?></div>
                    <?php endif; ?>
                <?php if ($payments): ?>
                    <div class="list-group list-group-flush"><?php foreach ($payments as $pay): ?><div class="list-group-item d-flex align-items-start justify-content-between gap-3 px-0"><div><strong><?= h($pay['payment_type']) ?> · <?= format_rupiah($pay['amount']) ?></strong><span><?= h($pay['marked_by_name'] ?? 'Staff') ?> · <?= h(format_datetime($pay['paid_at'] ?: $pay['created_at'])) ?></span></div><span class="<?= status_badge_class($pay['payment_status']) ?>"><?= h($pay['payment_status']) ?></span></div><?php endforeach; ?></div>
                <?php else: ?><p class="text-muted small">Belum ada pembayaran yang ditandai.</p><?php endif; ?>
            </div>
        </section>

    <?php elseif ($activeSection === 'actions'): ?>
        <section class="card border-0 shadow-sm d-flex flex-column gap-3">
            <div class="card-header bg-white d-flex align-items-start justify-content-between gap-3 py-3 px-4"><div><p class="small fw-bold text-uppercase text-secondary">Aksi Role</p><h2><?= h(role_label($role)) ?></h2></div></div>
            <?php ob_start(); ?>
            <?php if ($role === 'staff'): ?>
                <?php if ($booking['status'] === 'Menunggu Konfirmasi'): ?>
                    <div class="alert alert-info">Untuk menerima booking, pilih pekerja lapangan dan jadwal survey. Setelah disimpan, status langsung menjadi <strong>Survey Dijadwalkan</strong> dan pekerja lapangan mendapat notifikasi.</div>
                    <form data-api-form class="d-flex flex-column gap-3 mt-2" method="post">
                        <?= csrf_field() ?><input type="hidden" name="action" value="reject_booking"><input type="hidden" name="booking_id" value="<?= $bookingId ?>">
                        <input class="form-control" name="reason" placeholder="Alasan penolakan">
                        <button class="btn btn-outline-danger btn-sm" type="submit">Tolak Booking</button>
                    </form>
                <?php endif; ?>

                <?php if (in_array($booking['status'], ['Menunggu Konfirmasi','Survey Dijadwalkan','Survey Berlangsung'], true)): ?>
                    <form data-api-form class="d-flex flex-column gap-3 mt-3" method="post">
                        <?= csrf_field() ?><input type="hidden" name="action" value="schedule_survey"><input type="hidden" name="booking_id" value="<?= $bookingId ?>">
                        <label class="form-label"><?= $booking['status'] === 'Menunggu Konfirmasi' ? 'Terima booking, assign pekerja lapangan, dan jadwalkan survey' : 'Reschedule survey atau ganti pekerja lapangan' ?></label>
                        <select class="form-select" name="pekerja_lapangan_id" required>
                            <option value="">Pilih pekerja lapangan</option>
                            <?php foreach ($pekerjaLapanganList as $worker): ?><option value="<?= (int) $worker['id'] ?>" <?= (int) ($booking['assigned_pekerja_lapangan_id'] ?? 0) === (int) $worker['id'] ? 'selected' : '' ?>><?= h($worker['name']) ?></option><?php endforeach; ?>
                        </select>
                        <div class="row g-2"><div class="col-7"><input class="form-control" type="date" name="schedule_date" value="<?= h($booking['actual_survey_date'] ?: date('Y-m-d')) ?>" required></div><div class="col-5"><input class="form-control" type="time" name="schedule_time" value="<?= h($booking['actual_survey_time'] ? substr($booking['actual_survey_time'], 0, 5) : '09:00') ?>" required></div></div>
                        <textarea class="form-control" name="notes" rows="2" placeholder="Catatan survey atau alasan reschedule"></textarea>
                        <button class="btn btn-primary btn-sm" type="submit"><?= $booking['status'] === 'Menunggu Konfirmasi' ? 'Terima & Jadwalkan Survey' : 'Simpan Jadwal Survey' ?></button>
                    </form>
                <?php endif; ?>

                <?php if ($estimate && $estimate['status'] === 'Draft'): ?>
                    <div class="mt-3"><?php render_api_form('send_estimate', '<i class="bi bi-send"></i> Review & Kirim Estimasi ke Customer', ['estimate_id' => $estimate['id']]); ?></div>
                <?php endif; ?>
                <?php if ($booking['status'] === 'Estimasi Disetujui'): ?>
                    <div class="mt-3"><?php render_api_form('start_work', '<i class="bi bi-play-fill"></i> Mulai Pekerjaan', ['booking_id' => $bookingId]); ?></div>
                <?php endif; ?>
                <?php if ($invoice && $invoice['status'] === 'Draft'): ?>
                    <div class="mt-3"><?php render_api_form('send_invoice', '<i class="bi bi-send-check"></i> Review & Kirim Invoice Final', ['invoice_id' => $invoice['id']]); ?></div>
                <?php endif; ?>
                <?php if ($revision && $revision['status'] === 'Draft'): ?>
                    <div class="mt-3"><?php render_api_form('send_revision', '<i class="bi bi-send"></i> Review & Kirim Revisi ke Customer', ['revision_id' => $revision['id']]); ?></div>
                <?php endif; ?>
                <?php if ($invoice && $invoice['payment_status'] === 'Belum Dibayar'): ?>
                    <div class="mt-3"><?php render_api_form('mark_invoice_paid', '<i class="bi bi-cash-coin"></i> Tandai Invoice Lunas', ['invoice_id' => $invoice['id']]); ?></div>
                <?php endif; ?>
                <?php if ($booking['survey_fee_payment_status'] === 'Belum Dibayar' && !$invoice): ?>
                    <div class="mt-3"><?php render_api_form('mark_survey_fee_paid', '<i class="bi bi-cash"></i> Tandai Survey Fee Lunas', ['booking_id' => $bookingId], 'd-flex flex-wrap align-items-center gap-2', 'btn btn-outline-secondary btn-sm'); ?></div>
                <?php endif; ?>
                <?php if ($booking['status'] === 'Lunas'): ?>
                    <div class="mt-3"><?php render_api_form('complete_order', '<i class="bi bi-check2-circle"></i> Selesaikan Order', ['booking_id' => $bookingId]); ?></div>
                <?php endif; ?>
            <?php elseif ($role === 'pekerja_lapangan'): ?>
                <?php if ($booking['status'] === 'Survey Dijadwalkan'): ?>
                    <?php render_api_form('start_survey', '<i class="bi bi-play-fill"></i> Mulai Survey', ['booking_id' => $bookingId]); ?>
                <?php endif; ?>
                <?php if ($booking['status'] === 'Survey Berlangsung'): ?>
                    <form data-api-form class="d-flex flex-column gap-3 mt-3" method="post" enctype="multipart/form-data">
                        <?= csrf_field() ?><input type="hidden" name="action" value="complete_survey"><input type="hidden" name="booking_id" value="<?= $bookingId ?>">
                        <label class="form-label">Hasil survey</label>
                        <input class="form-control" type="number" min="1" step="0.1" name="actual_area" placeholder="Luas aktual m2" required>
                        <textarea class="form-control" name="location_condition" rows="2" placeholder="Kondisi lokasi" required></textarea>
                        <textarea class="form-control" name="access_condition" rows="2" placeholder="Akses lokasi" required></textarea>
                        <textarea class="form-control" name="obstacle_notes" rows="2" placeholder="Catatan kendala"></textarea>
                        <div class="row g-2"><div class="col-6"><input class="form-control" type="number" min="1" name="estimated_work_days" placeholder="Hari kerja" required></div><div class="col-6"><input class="form-control" type="number" min="1" name="estimated_worker_count" placeholder="Jumlah tukang" required></div></div>
                        <textarea class="form-control" name="survey_notes" rows="2" placeholder="Catatan survey"></textarea>
                        <label class="form-label">Upload foto before (1–5 foto)</label>
                        <input class="form-control" type="file" name="before_photos[]" accept=".jpg,.jpeg,.png,.webp" multiple required data-max-files="5">
                        <button class="btn btn-primary btn-sm" type="submit">Selesaikan Survey</button>
                    </form>
                <?php endif; ?>
                <?php if ($booking['status'] === 'Survey Selesai'): ?>
                    <form data-api-form class="d-flex flex-column gap-3 mt-3" method="post">
                        <?= csrf_field() ?><input type="hidden" name="action" value="create_estimate"><input type="hidden" name="booking_id" value="<?= $bookingId ?>">
                        <label class="form-label">Buat estimasi sementara</label>
                        <div class="row g-2"><div class="col-4"><input class="form-control" type="number" min="1" step="0.1" name="area_size" value="<?= h($booking['actual_area'] ?? $booking['estimated_area']) ?>" required></div><div class="col-4"><input class="form-control" type="number" min="1" name="worker_count" value="2" required></div><div class="col-4"><input class="form-control" type="number" min="1" name="estimated_days" value="1" required></div></div>
                        <div class="d-flex flex-column gap-2" data-repeatable>
                            <div class="row g-2"><input class="form-control" name="additional_name[]" placeholder="Biaya tambahan"><input class="form-control" type="number" name="additional_qty[]" value="1"><input class="form-control" name="additional_unit[]" value="item"><input class="form-control" type="number" name="additional_price[]" value="0"></div>
                        </div>
                        <button class="btn btn-primary btn-sm" type="submit">Simpan Estimasi</button>
                    </form>
                <?php endif; ?>
                <?php if ($booking['status'] === 'Estimasi Disetujui'): ?>
                    <div class="mt-3"><?php render_api_form('start_work', '<i class="bi bi-play-fill"></i> Mulai Pekerjaan', ['booking_id' => $bookingId]); ?></div>
                <?php endif; ?>
                <?php if (in_array($booking['status'], ['Dalam Pengerjaan','Revisi Estimasi'], true)): ?>
                    <form data-api-form class="d-flex flex-column gap-3 mt-3" method="post" enctype="multipart/form-data">
                        <?= csrf_field() ?><input type="hidden" name="action" value="update_progress"><input type="hidden" name="booking_id" value="<?= $bookingId ?>">
                        <label class="form-label">Update progress</label>
                        <input class="form-control" type="number" min="0" max="100" name="progress_percent" placeholder="Persen progress" required>
                        <input class="form-control" name="title" placeholder="Judul update" required>
                        <textarea class="form-control" name="note" rows="2" placeholder="Catatan pekerjaan"></textarea>
                        <textarea class="form-control" name="obstacle_note" rows="2" placeholder="Kendala lapangan"></textarea>
                        <label class="form-label">Foto progress opsional (maksimal 5 foto)</label>
                        <input class="form-control" type="file" name="progress_photos[]" accept=".jpg,.jpeg,.png,.webp" multiple data-max-files="5">
                        <button class="btn btn-primary btn-sm" type="submit">Simpan Progress</button>
                    </form>
                    <form data-api-form class="d-flex flex-column gap-3 mt-3" method="post">
                        <?= csrf_field() ?><input type="hidden" name="action" value="create_revision"><input type="hidden" name="booking_id" value="<?= $bookingId ?>">
                        <label class="form-label">Revisi estimasi</label>
                        <textarea class="form-control" name="reason" rows="2" placeholder="Alasan revisi"></textarea>
                        <div class="row g-2"><input class="form-control" name="item_name[]" placeholder="Item revisi"><input class="form-control" name="description[]" placeholder="Deskripsi"><input class="form-control" type="number" name="quantity[]" value="1"><input class="form-control" name="unit[]" value="item"><input class="form-control" type="number" name="unit_price[]" value="0"></div>
                        <button class="btn btn-outline-secondary btn-sm" type="submit">Buat Revisi</button>
                    </form>
                    <div class="mt-3"><?php render_api_form('mark_work_done', '<i class="bi bi-check2-square"></i> Tandai Pekerjaan Selesai', ['booking_id' => $bookingId], 'd-flex flex-wrap align-items-center gap-2', 'btn btn-outline-secondary btn-sm'); ?></div>
                <?php endif; ?>
                <?php if ($booking['status'] === 'Pekerjaan Selesai' && !$invoice): ?>
                    <form data-api-form class="d-flex flex-column gap-3 mt-3" method="post" enctype="multipart/form-data">
                        <?= csrf_field() ?><input type="hidden" name="action" value="create_invoice"><input type="hidden" name="booking_id" value="<?= $bookingId ?>">
                        <label class="form-label">Data final pekerjaan</label>
                        <div class="row g-2"><div class="col-6"><input class="form-control" type="number" min="1" name="work_days_actual" placeholder="Hari aktual" required></div><div class="col-6"><input class="form-control" type="number" min="1" name="worker_count_actual" placeholder="Tukang aktual" required></div></div>
                        <textarea class="form-control" name="notes" rows="2" placeholder="Catatan data final"></textarea>
                        <label class="form-label">Upload foto after (1–5 foto)</label>
                        <input class="form-control" type="file" name="after_photos[]" accept=".jpg,.jpeg,.png,.webp" multiple required data-max-files="5">
                        <button class="btn btn-primary btn-sm" type="submit">Kirim Data Final</button>
                    </form>
                <?php endif; ?>
            <?php elseif ($role === 'customer'): ?>
                <?php if ($estimate && $estimate['status'] === 'Dikirim'): ?>
                    <form data-api-form class="d-flex flex-column gap-3" method="post">
                        <?= csrf_field() ?><input type="hidden" name="estimate_id" value="<?= (int) $estimate['id'] ?>">
                        <textarea class="form-control" name="customer_response_note" rows="2" placeholder="Catatan keputusan"></textarea>
                        <button class="btn btn-primary btn-sm" name="action" value="approve_estimate" type="submit">Setujui Estimasi</button>
                        <button class="btn btn-outline-danger btn-sm" name="action" value="reject_estimate" type="submit">Tolak Estimasi</button>
                    </form>
                <?php endif; ?>
                <?php if ($revision && $revision['status'] === 'Dikirim'): ?>
                    <form data-api-form class="d-flex flex-column gap-3 mt-3" method="post">
                        <?= csrf_field() ?><input type="hidden" name="revision_id" value="<?= (int) $revision['id'] ?>">
                        <textarea class="form-control" name="customer_response_note" rows="2" placeholder="Catatan keputusan revisi"></textarea>
                        <button class="btn btn-primary btn-sm" name="action" value="approve_revision" type="submit">Setujui Revisi</button>
                        <button class="btn btn-outline-danger btn-sm" name="action" value="reject_revision" type="submit">Tolak Revisi</button>
                    </form>
                <?php endif; ?>
                <a class="btn btn-outline-secondary btn-sm mt-2" href="dashboard.php?page=catalog">Buat booking lain</a>
            <?php else: ?>
                <p class="text-muted small">Boss hanya memiliki akses monitoring.</p>
            <?php endif; ?>
            <?php $actions = trim(ob_get_clean()); ?>
            <?= $actions !== '' ? $actions : '<p class="text-muted small">Belum ada aksi aktif untuk status booking ini.</p>' ?>
        </section>
    <?php endif; ?>

    <?php
    // Section ulasan — tampil di semua section jika booking sudah Selesai
    $review = db_first('SELECT * FROM reviews WHERE booking_id=?', [$bookingId]);
    if ($booking['status'] === 'Selesai'):
    ?>
    <section class="card border-0 shadow-sm mt-4">
        <div class="card-header bg-white d-flex align-items-start justify-content-between gap-3 py-3 px-4">
            <div><p class="small fw-bold text-uppercase text-secondary">Ulasan</p><h2>Rating & Komentar</h2></div>
            <?php if ($review): ?>
                <div class="d-flex gap-1">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <i class="bi bi-star<?= $i <= (int) $review['rating'] ? '-fill' : '' ?> text-warning fs-5"></i>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php if ($review): ?>
            <div class="row g-3">
                <div class="col-md-6 border rounded p-3"><span>Rating</span><strong><?= (int) $review['rating'] ?>/5</strong></div>
                <div class="col-md-6 border rounded p-3"><span>Tanggal</span><strong><?= h(format_date_id($review['created_at'])) ?></strong></div>
                <?php if ($review['comment']): ?>
                    <div class="col-12 border rounded p-3"><span>Komentar</span><p><?= h($review['comment']) ?></p></div>
                <?php endif; ?>
            </div>
            <?php if ($role === 'customer'): ?>
                <p class="text-secondary mt-2">Ulasan sudah diberikan. Terima kasih atas masukan Anda!</p>
            <?php endif; ?>
        <?php elseif ($role === 'customer'): ?>
            <form data-api-form class="d-flex flex-column gap-3" method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="submit_review">
                <input type="hidden" name="booking_id" value="<?= $bookingId ?>">
                <div>
                    <label class="form-label">Rating <span class="text-danger">*</span></label>
                    <div class="d-flex gap-3 align-items-center mt-1" data-star-rating>
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <div class="form-check form-check-inline m-0">
                                <input class="form-check-input visually-hidden" type="radio" name="rating" id="rating<?= $i ?>" value="<?= $i ?>" required>
                                <label class="form-check-label btn btn-link p-0" for="rating<?= $i ?>">
                                    <i class="bi bi-star text-secondary fs-4"></i>
                                </label>
                            </div>
                        <?php endfor; ?>
                        <span class="text-muted small">Pilih bintang</span>
                    </div>
                </div>
                <div>
                    <label class="form-label">Komentar (opsional)</label>
                    <textarea class="form-control" name="comment" rows="3" placeholder="Ceritakan pengalaman Anda menggunakan layanan ABI Taman..."></textarea>
                </div>
                <button class="btn btn-primary" type="submit"><i class="bi bi-star me-1"></i>Kirim Ulasan</button>
            </form>
        <?php else: ?>
            <p class="text-muted small">Customer belum memberikan ulasan untuk order ini.</p>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php
}

function render_customer_catalog_page(): void
{
    $categorySlug = trim((string) ($_GET['category'] ?? ''));
    $search = trim((string) ($_GET['q'] ?? ''));
    $categories = db_query("SELECT * FROM service_categories WHERE status='active' ORDER BY name");

    $where = ["s.status='active'"];
    $params = [];
    if ($categorySlug !== '') {
        $where[] = 'c.slug = ?';
        $params[] = $categorySlug;
    }
    if ($search !== '') {
        $where[] = '(s.name LIKE ? OR s.short_description LIKE ? OR s.description LIKE ?)';
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }

    $catalogSql =
        'SELECT s.*, c.name AS category_name,
                s.base_price_per_10m AS min_price,
                0 AS package_count
         FROM services s
         LEFT JOIN service_categories c ON c.id=s.category_id
         WHERE ' . implode(' AND ', $where) . '
         ORDER BY c.name, s.name';
    $paged = paginate($catalogSql, $params, 12);
    $services = $paged['rows'];

    $activeServiceCount = (int) $paged['total'];
    ?>
    <!-- Header -->
    <div class="d-flex align-items-start justify-content-between gap-3 mb-4 flex-wrap">
        <div>
            <p class="small fw-bold text-uppercase text-secondary mb-1">Katalog</p>
            <h2 class="h4 fw-bold mb-1">Layanan ABI Taman</h2>
            <p class="text-muted small mb-0">
                <i class="bi bi-flower1 me-1"></i><?= (int) $activeServiceCount ?> layanan tersedia
            </p>
        </div>
        <a class="btn btn-primary btn-sm" href="booking_create.php?custom=1">
            <i class="bi bi-plus-lg me-1"></i> Custom Layanan
        </a>
    </div>

    <!-- Filter -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body py-3">
            <form class="d-flex flex-wrap gap-2 align-items-center" method="get">
                <input type="hidden" name="page" value="catalog">
                <input class="form-control form-control-sm flex-grow-1" name="q" value="<?= h($search) ?>"
                       placeholder="Cari layanan…">
                <select class="form-select form-select-sm w-auto" name="category">
                    <option value="">Semua kategori</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= h($cat['slug']) ?>" <?= $categorySlug === $cat['slug'] ? 'selected' : '' ?>>
                            <?= h($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-primary btn-sm" type="submit">
                    <i class="bi bi-search me-1"></i>Filter
                </button>
                <?php if ($search !== '' || $categorySlug !== ''): ?>
                    <a class="btn btn-outline-secondary btn-sm" href="dashboard.php?page=catalog">Reset</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Service cards -->
    <?php if ($services): ?>
        <div class="row g-4">
            <?php foreach ($services as $service): ?>
                <div class="col-12 col-md-6 col-xl-4">
                    <div class="card border h-100 shadow-sm overflow-hidden">
                        <div class="position-relative">
                            <img src="<?= h($service['image'] ?: 'assets/img/abi-taman.png') ?>"
                                 alt="<?= h($service['name']) ?>"
                                 class="card-img-top object-fit-cover">
                            <span class="position-absolute top-0 start-0 m-2 badge text-bg-success">
                                <?= h($service['category_name'] ?? 'Layanan') ?>
                            </span>
                        </div>
                        <div class="card-body d-flex flex-column gap-2">
                            <h3 class="h6 fw-bold mb-0"><?= h($service['name']) ?></h3>
                            <p class="text-muted small mb-0 flex-grow-1">
                                <?= h(mb_strimwidth($service['short_description'] ?? $service['description'] ?? '', 0, 100, '…')) ?>
                            </p>
                            <p class="text-muted small mb-0">
                                <i class="bi bi-info-circle me-1"></i>Harga ditentukan setelah survey lapangan.
                            </p>
                        </div>
                        <div class="card-footer bg-white border-top d-flex gap-2 py-3">
                            <a class="btn btn-primary btn-sm flex-fill"
                               href="booking_create.php?service_id=<?= (int) $service['id'] ?>">
                                <i class="bi bi-bag-check me-1"></i>Booking
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php render_pagination($paged['page'], $paged['totalPages'], $paged['total'], $paged['perPage']); ?>
    <?php else: ?>
        <div class="card card-body text-center text-secondary py-5">
            <i class="bi bi-flower1"></i>
            <h3>Layanan tidak ditemukan</h3>
            <p>Coba ubah filter atau kata kunci pencarian.</p>
        </div>
    <?php endif; ?>
    <?php
}

function render_master_page(string $page): void
{
    $categories = db_query('SELECT * FROM service_categories ORDER BY name');
    $services = db_query('SELECT s.*, c.name AS category_name FROM services s LEFT JOIN service_categories c ON c.id=s.category_id ORDER BY c.name, s.name');
    if ($page === 'catalog') {
        $categoryModal = modal_id('add-master', 'category');
        $serviceModal = modal_id('add-master', 'service');
        $serviceSearch = trim((string) ($_GET['q'] ?? ''));
        $serviceStatus = trim((string) ($_GET['status'] ?? ''));
        $serviceWhere = [];
        $serviceParams = [];
        if ($serviceSearch !== '') {
            $serviceWhere[] = '(s.name LIKE ? OR s.short_description LIKE ? OR c.name LIKE ?)';
            $serviceParams[] = "%{$serviceSearch}%";
            $serviceParams[] = "%{$serviceSearch}%";
            $serviceParams[] = "%{$serviceSearch}%";
        }
        if ($serviceStatus !== '') {
            $serviceWhere[] = 's.status = ?';
            $serviceParams[] = $serviceStatus;
        }
        $serviceSqlWhere = $serviceWhere ? 'WHERE ' . implode(' AND ', $serviceWhere) : '';
        $categoryPaged = paginate_with_param(
            'SELECT c.*, COUNT(s.id) AS service_count
             FROM service_categories c
             LEFT JOIN services s ON s.category_id = c.id
             GROUP BY c.id
             ORDER BY c.status, c.name',
            [],
            10,
            'category_page'
        );
        $categoryRows = $categoryPaged['rows'];
        $servicePaged = paginate_with_param(
            "SELECT s.*, c.name AS category_name
             FROM services s
             LEFT JOIN service_categories c ON c.id=s.category_id
             {$serviceSqlWhere}
             ORDER BY s.status, c.name, s.name",
            $serviceParams,
            10,
            'service_page'
        );
        $services = $servicePaged['rows'];
        $totalCategories = count($categoryRows);
        $activeCategories = count(array_filter($categoryRows, static fn(array $row): bool => ($row['status'] ?? '') === 'active'));
        $totalServices = (int) (db_first('SELECT COUNT(*) AS c FROM services')['c'] ?? 0);
        $activeServices = (int) (db_first("SELECT COUNT(*) AS c FROM services WHERE status='active'")['c'] ?? 0);
        ?>
        <section>
            <header class="settings-section-head">
                <div>
                    <p class="eyebrow">Data master</p>
                    <h2>Katalog layanan</h2>
                    <p>Atur kategori dan layanan yang tampil ke customer. Harga final tetap mengikuti hasil survei dan estimasi.</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <?php render_modal_button($categoryModal, 'Tambah Kategori', 'btn btn-outline-primary', 'bi-plus-lg'); ?>
                    <?php render_modal_button($serviceModal, 'Tambah Layanan', 'btn btn-primary', 'bi-plus-lg'); ?>
                </div>
            </header>

            <div class="settings-kpis">
                <div class="settings-kpi"><span>Total kategori</span><strong><?= (int) $totalCategories ?></strong></div>
                <div class="settings-kpi"><span>Kategori aktif</span><strong><?= (int) $activeCategories ?></strong></div>
                <div class="settings-kpi"><span>Total layanan</span><strong><?= (int) $totalServices ?></strong></div>
                <div class="settings-kpi"><span>Layanan aktif</span><strong><?= (int) $activeServices ?></strong></div>
            </div>

            <section class="settings-panel">
                <div class="settings-panel__header">
                    <div><p class="eyebrow">Kategori</p><h3>Kategori layanan</h3></div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 settings-table settings-table--categories">
                        <thead><tr><th>Kategori</th><th>Layanan</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($categoryRows as $cat): ?>
                            <?php $deleteModal = modal_id('delete-category', (int) $cat['id']); ?>
                            <?php $editModal = modal_id('edit-category', (int) $cat['id']); ?>
                            <tr>
                                <td><strong><?= h($cat['name']) ?></strong><small class="small text-secondary"><?= h($cat['description'] ?: 'Tanpa deskripsi') ?></small></td>
                                <td><?= (int) $cat['service_count'] ?> layanan</td>
                                <td><span class="<?= status_badge_class($cat['status']) ?>"><?= h($cat['status']) ?></span></td>
                                <td class="text-end">
                                    <div class="d-flex flex-wrap gap-2 justify-content-end">
                                        <?php render_modal_button($editModal, 'Edit', 'btn btn-outline-secondary btn-sm', 'bi-pencil-square'); ?>
                                        <?php render_modal_button($deleteModal, 'Hapus', 'btn btn-outline-danger btn-sm', 'bi-trash'); ?>
                                    </div>
                                </td>
                            </tr>
                            <?php render_form_modal($editModal, 'Edit Kategori', 'Katalog', 'save_category', function () use ($cat): void { ?>
                                <input type="hidden" name="id" value="<?= (int) $cat['id'] ?>">
                                <input type="hidden" name="existing_image" value="<?= h($cat['image'] ?? 'assets/img/services/custom-landscape.svg') ?>">
                                <input class="form-control" name="name" value="<?= h($cat['name']) ?>" placeholder="Nama kategori" required>
                                <textarea class="form-control" name="description" rows="2" placeholder="Deskripsi"><?= h($cat['description'] ?? '') ?></textarea>
                                <input class="form-control" type="file" name="image" accept=".jpg,.jpeg,.png,.webp,.svg">
                                <select class="form-select" name="status"><option value="active" <?= $cat['status'] === 'active' ? 'selected' : '' ?>>Aktif</option><option value="inactive" <?= $cat['status'] === 'inactive' ? 'selected' : '' ?>>Nonaktif</option></select>
                            <?php }, 'Simpan Perubahan'); ?>
                            <?php render_delete_modal($deleteModal, 'Hapus Kategori', 'Hapus/nonaktifkan kategori ' . $cat['name'] . '?', 'delete_master_record', ['target' => 'category', 'id' => $cat['id']]); ?>
                        <?php endforeach; ?>
                        <?php if (!$categoryRows): ?><tr><td colspan="4" class="text-center text-muted py-4">Belum ada kategori.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php render_pagination_param($categoryPaged); ?>
            </section>

            <section class="settings-panel">
                <div class="settings-toolbar">
                    <div><p class="eyebrow">Layanan</p><h3 class="h6 mb-0 mt-1">Daftar layanan</h3></div>
                    <form method="get">
                        <?php render_settings_context_fields('catalog'); ?>
                        <input class="form-control form-control-sm" type="search" name="q" value="<?= h($serviceSearch) ?>" placeholder="Cari layanan atau kategori">
                        <select class="form-select form-select-sm" name="status">
                            <option value="">Semua status</option>
                            <option value="active" <?= $serviceStatus === 'active' ? 'selected' : '' ?>>Aktif</option>
                            <option value="inactive" <?= $serviceStatus === 'inactive' ? 'selected' : '' ?>>Nonaktif</option>
                        </select>
                        <button class="btn btn-primary btn-sm" type="submit">Filter</button>
                        <?php if ($serviceSearch !== '' || $serviceStatus !== ''): ?><a class="btn btn-outline-secondary btn-sm" href="<?= h(settings_context_url('catalog')) ?>">Reset</a><?php endif; ?>
                    </form>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 settings-table settings-table--services">
                        <thead><tr><th>Layanan</th><th>Kategori</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($services as $srv): ?>
                            <?php $deleteModal = modal_id('delete-service', (int) $srv['id']); ?>
                            <?php $editModal = modal_id('edit-service', (int) $srv['id']); ?>
                            <tr>
                                <td><strong><?= h($srv['name']) ?></strong><small class="small text-secondary"><?= h($srv['short_description'] ?: 'Tanpa deskripsi singkat') ?></small></td>
                                <td><?= h($srv['category_name'] ?? '-') ?></td>
                                <td><span class="<?= status_badge_class($srv['status']) ?>"><?= h($srv['status']) ?></span></td>
                                <td class="text-end">
                                    <div class="d-flex flex-wrap gap-2 justify-content-end">
                                        <?php render_modal_button($editModal, 'Edit', 'btn btn-outline-secondary btn-sm', 'bi-pencil-square'); ?>
                                        <?php render_modal_button($deleteModal, 'Hapus', 'btn btn-outline-danger btn-sm', 'bi-trash'); ?>
                                    </div>
                                </td>
                            </tr>
                            <?php render_form_modal($editModal, 'Edit Layanan', 'Katalog', 'save_service', function () use ($srv, $categories): void { ?>
                                <input type="hidden" name="id" value="<?= (int) $srv['id'] ?>">
                                <input type="hidden" name="existing_image" value="<?= h($srv['image'] ?? 'assets/img/services/taman-minimalis.svg') ?>">
                                <select class="form-select" name="category_id"><?php foreach ($categories as $cat): ?><option value="<?= (int) $cat['id'] ?>" <?= (int) ($srv['category_id'] ?? 0) === (int) $cat['id'] ? 'selected' : '' ?>><?= h($cat['name']) ?></option><?php endforeach; ?></select>
                                <input class="form-control" name="name" value="<?= h($srv['name']) ?>" placeholder="Nama layanan" required>
                                <input class="form-control" name="short_description" value="<?= h($srv['short_description'] ?? '') ?>" placeholder="Deskripsi singkat">
                                <textarea class="form-control" name="description" rows="3" placeholder="Deskripsi lengkap"><?= h($srv['description'] ?? '') ?></textarea>
                                <div class="row g-2">
                                    <div class="col-12">
                                        <label class="form-label small fw-semibold">Durasi / 10 m² (hari)</label>
                                        <input class="form-control" type="number" min="1" name="base_duration_days_per_10m" value="<?= (int) ($srv['base_duration_days_per_10m'] ?? 1) ?>" placeholder="Hari per 10 m²">
                                    </div>
                                </div>
                                <input class="form-control" type="file" name="image" accept=".jpg,.jpeg,.png,.webp,.svg">
                                <select class="form-select" name="status"><option value="active" <?= $srv['status'] === 'active' ? 'selected' : '' ?>>Tampil/Aktif</option><option value="inactive" <?= $srv['status'] === 'inactive' ? 'selected' : '' ?>>Nonaktif</option></select>
                            <?php }, 'Simpan Perubahan'); ?>
                            <?php render_delete_modal($deleteModal, 'Hapus Layanan', 'Hapus/nonaktifkan layanan ' . $srv['name'] . '?', 'delete_master_record', ['target' => 'service', 'id' => $srv['id']]); ?>
                        <?php endforeach; ?>
                        <?php if (!$services): ?><tr><td colspan="4" class="text-center text-muted py-4">Belum ada layanan yang cocok.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php render_pagination_param($servicePaged); ?>
            </section>
        </section>
        <?php render_form_modal($categoryModal, 'Tambah Kategori', 'Katalog', 'save_category', function (): void { ?>
            <input class="form-control" name="name" placeholder="Nama kategori" required>
            <textarea class="form-control" name="description" rows="2" placeholder="Deskripsi"></textarea>
            <input class="form-control" type="file" name="image" accept=".jpg,.jpeg,.png,.webp,.svg">
            <select class="form-select" name="status"><option value="active">Aktif</option><option value="inactive">Nonaktif</option></select>
        <?php }); ?>
        <?php render_form_modal($serviceModal, 'Tambah Layanan', 'Katalog', 'save_service', function () use ($categories): void { ?>
            <select class="form-select" name="category_id"><?php foreach ($categories as $cat): ?><option value="<?= (int) $cat['id'] ?>"><?= h($cat['name']) ?></option><?php endforeach; ?></select>
            <input class="form-control" name="name" placeholder="Nama layanan" required>
            <input class="form-control" name="short_description" placeholder="Deskripsi singkat">
            <textarea class="form-control" name="description" rows="3" placeholder="Deskripsi lengkap"></textarea>
            <div class="row g-2">
                <div class="col-12">
                    <label class="form-label small fw-semibold">Durasi / 10 m² (hari)</label>
                    <input class="form-control" type="number" min="1" name="base_duration_days_per_10m" value="1" placeholder="Hari per 10 m²">
                </div>
            </div>
            <input class="form-control" type="file" name="image" accept=".jpg,.jpeg,.png,.webp,.svg">
            <select class="form-select" name="status"><option value="active">Tampil/Aktif</option><option value="inactive">Nonaktif</option></select>
        <?php }); ?>
        <?php
        return;
    }

    $map = [
        'materials' => ['materials', 'save_material', 'Bahan Baku', ['name','unit','price_per_unit','default_qty_per_10m','image','status']],
        'labor_rates' => ['labor_rates', 'save_labor_rate', 'Harga Tukang', ['name','description','daily_rate','status']],
        'additional_costs' => ['additional_cost_types', 'save_additional_cost', 'Biaya Tambahan', ['name','description','default_price','unit','status']],
    ];
    [$table, $action, $title, $fields] = $map[$page];

    $search = trim((string) ($_GET['q'] ?? ''));
    $statusFilter = trim((string) ($_GET['status'] ?? ''));
    $where = [];
    $params = [];
    if ($search !== '') {
        $where[] = $page === 'materials' ? 'name LIKE ?' : '(name LIKE ? OR description LIKE ?)';
        $params[] = "%{$search}%";
        if ($page !== 'materials') {
            $params[] = "%{$search}%";
        }
    }
    if ($statusFilter !== '') {
        $where[] = 'status = ?';
        $params[] = $statusFilter;
    }
    $sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $paged = paginate("SELECT * FROM {$table} {$sqlWhere} ORDER BY status, id DESC", $params, 15);
    $rows = $paged['rows'];
    $totalCount = (int) (db_first("SELECT COUNT(*) AS c FROM {$table}")['c'] ?? 0);
    $activeCount = (int) (db_first("SELECT COUNT(*) AS c FROM {$table} WHERE status = ?", ['active'])['c'] ?? 0);
    $inactiveCount = max(0, $totalCount - $activeCount);
    $valueSummary = 0;
    if ($page === 'materials') {
        $valueSummary = (int) (db_first("SELECT COALESCE(SUM(price_per_unit * default_qty_per_10m),0) AS total FROM {$table} WHERE status = 'active'")['total'] ?? 0);
    } elseif ($page === 'additional_costs') {
        $valueSummary = (int) (db_first("SELECT COALESCE(AVG(default_price),0) AS total FROM {$table} WHERE status = 'active'")['total'] ?? 0);
    } elseif ($page === 'labor_rates') {
        $valueSummary = (int) (db_first("SELECT COALESCE(AVG(daily_rate),0) AS total FROM {$table} WHERE status = 'active'")['total'] ?? 0);
    }
    $summaryLabel = $page === 'materials' ? 'Estimasi bahan / 10m²' : ($page === 'additional_costs' ? 'Rata-rata biaya' : 'Rata-rata upah');
    $pageDescription = $page === 'materials'
        ? 'Kelola bahan yang dipakai untuk penyusunan estimasi. Detail transaksi tetap berada di halaman estimasi.'
        : ($page === 'additional_costs'
            ? 'Kelola template biaya tambahan yang bisa dipilih saat menyusun estimasi.'
            : 'Kelola referensi upah harian untuk perhitungan tenaga kerja.');

    $addModal = modal_id('add-master', $page);
    $targetMap = [
        'materials' => 'material',
        'labor_rates' => 'labor_rate',
        'additional_costs' => 'additional_cost',
    ];
    ?>
    <section>
        <header class="settings-section-head">
            <div>
                <p class="eyebrow">Data master</p>
                <h2><?= h($title) ?></h2>
                <p><?= h($pageDescription) ?></p>
            </div>
            <?php render_modal_button($addModal, 'Tambah ' . $title, 'btn btn-primary', 'bi-plus-lg'); ?>
        </header>

        <div class="settings-kpis">
            <div class="settings-kpi"><span>Total data</span><strong><?= (int) $totalCount ?></strong></div>
            <div class="settings-kpi"><span>Aktif</span><strong><?= (int) $activeCount ?></strong></div>
            <div class="settings-kpi"><span>Nonaktif</span><strong><?= (int) $inactiveCount ?></strong></div>
            <div class="settings-kpi"><span><?= h($summaryLabel) ?></span><strong><?= format_rupiah($valueSummary) ?></strong></div>
        </div>

        <section class="settings-panel">
            <div class="settings-toolbar">
                <div><p class="eyebrow">Daftar</p><h3 class="h6 mb-0 mt-1"><?= h($title) ?></h3></div>
                <form method="get">
                    <?php render_settings_context_fields($page); ?>
                    <input class="form-control form-control-sm" type="search" name="q" value="<?= h($search) ?>" placeholder="Cari <?= h(strtolower($title)) ?>">
                    <select class="form-select form-select-sm" name="status">
                        <option value="">Semua status</option>
                        <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Aktif</option>
                        <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Nonaktif</option>
                    </select>
                    <button class="btn btn-primary btn-sm" type="submit">Filter</button>
                    <?php if ($search !== '' || $statusFilter !== ''): ?><a class="btn btn-outline-secondary btn-sm" href="<?= h(settings_context_url($page)) ?>">Reset</a><?php endif; ?>
                </form>
            </div>
        <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 settings-table settings-table--<?= h($page) ?>">
        <thead><tr>
            <?php if ($page === 'materials'): ?>
                <th>Nama</th><th>Satuan</th><th>Harga / Satuan</th><th>Qty Default / 10m²</th><th>Status</th><th></th>
            <?php elseif ($page === 'labor_rates'): ?>
                <th>Nama</th><th>Deskripsi</th><th>Upah / Hari</th><th>Status</th><th></th>
            <?php elseif ($page === 'additional_costs'): ?>
                <th>Biaya</th><th>Harga Default</th><th>Satuan</th><th>Status</th><th></th>
            <?php endif; ?>
        </tr></thead>
        <tbody>
            <?php foreach ($rows as $row): ?>
                <?php $deleteModal = modal_id('delete-master-' . $page, (int) $row['id']); ?>
                <?php $editModal = modal_id('edit-master-' . $page, (int) $row['id']); ?>
                <tr>
                    <?php if ($page === 'materials'): ?>
                        <td><strong><?= h($row['name']) ?></strong></td>
                        <td><?= h($row['unit']) ?></td>
                        <td><?= format_rupiah($row['price_per_unit']) ?></td>
                        <td><?= h($row['default_qty_per_10m']) ?></td>
                    <?php elseif ($page === 'labor_rates'): ?>
                        <td><strong><?= h($row['name']) ?></strong></td>
                        <td><?= h($row['description'] ?? '-') ?></td>
                        <td><?= format_rupiah($row['daily_rate']) ?></td>
                    <?php elseif ($page === 'additional_costs'): ?>
                        <td><strong><?= h($row['name']) ?></strong><small class="small text-secondary"><?= h($row['description'] ?: 'Tanpa deskripsi') ?></small></td>
                        <td><?= format_rupiah($row['default_price']) ?></td>
                        <td><?= h($row['unit']) ?></td>
                    <?php endif; ?>
                    <td><span class="<?= status_badge_class($row['status'] ?? 'active') ?>"><?= h($row['status'] ?? 'active') ?></span></td>
                    <td>
                        <div class="d-flex flex-wrap gap-2 justify-content-end">
                            <?php render_modal_button($editModal, 'Edit', 'btn btn-outline-secondary btn-sm', 'bi-pencil-square'); ?>
                            <?php render_modal_button($deleteModal, 'Hapus', 'btn btn-outline-danger btn-sm', 'bi-trash'); ?>
                        </div>
                    </td>
                </tr>
                <?php render_form_modal($editModal, 'Edit ' . $title, 'Master Data', $action, function () use ($fields, $services, $row): void { ?>
                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                    <?php foreach ($fields as $field): ?>
                        <?php if ($field === 'service_id'): ?>
                            <select class="form-select" name="service_id" required><?php foreach ($services as $srv): ?><option value="<?= (int) $srv['id'] ?>" <?= (int) ($row['service_id'] ?? 0) === (int) $srv['id'] ? 'selected' : '' ?>><?= h($srv['name']) ?></option><?php endforeach; ?></select>
                        <?php elseif ($field === 'description'): ?>
                            <textarea class="form-control" name="description" rows="2" placeholder="Deskripsi"><?= h($row['description'] ?? '') ?></textarea>
                        <?php elseif ($field === 'image'): ?>
                            <input type="hidden" name="existing_image" value="<?= h($row['image'] ?? '') ?>">
                            <input class="form-control" type="file" name="image" accept=".jpg,.jpeg,.png,.webp,.svg">
                        <?php elseif ($field === 'status'): ?>
                            <select class="form-select" name="status"><option value="active" <?= ($row['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Aktif</option><option value="inactive" <?= ($row['status'] ?? 'active') === 'inactive' ? 'selected' : '' ?>>Nonaktif</option></select>
                        <?php else: ?>
                            <input class="form-control" name="<?= h($field) ?>" value="<?= h($row[$field] ?? '') ?>" type="<?= str_contains($field, 'price') || str_contains($field, 'rate') || str_contains($field, 'duration') || str_contains($field, 'qty') ? 'number' : 'text' ?>" placeholder="<?= h(ucwords(str_replace('_', ' ', $field))) ?>" required>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php }, 'Simpan Perubahan'); ?>
                <?php render_delete_modal($deleteModal, 'Hapus ' . $title, 'Hapus/nonaktifkan ' . $title . ' ' . (string) ($row['name'] ?? '') . '? Jika sudah dipakai transaksi, sistem akan menjaga histori.', 'delete_master_record', ['target' => $targetMap[$page], 'id' => $row['id']]); ?>
            <?php endforeach; ?>
            <?php $emptyColspan = $page === 'additional_costs' ? 5 : count(array_slice($fields, 0, 5)) + 2; ?>
            <?php if (!$rows): ?><tr><td colspan="<?= (int) $emptyColspan ?>" class="text-center text-muted py-4">Belum ada data yang cocok.</td></tr><?php endif; ?>
        </tbody></table></div>
        <?php render_pagination($paged['page'], $paged['totalPages'], $paged['total'], $paged['perPage']); ?>
        </section>
    </section>
    <?php render_form_modal($addModal, 'Tambah ' . $title, 'Master Data', $action, function () use ($fields, $services): void { ?>
        <?php foreach ($fields as $field): ?>
            <?php if ($field === 'service_id'): ?>
                <select class="form-select" name="service_id" required><?php foreach ($services as $srv): ?><option value="<?= (int) $srv['id'] ?>"><?= h($srv['name']) ?></option><?php endforeach; ?></select>
            <?php elseif ($field === 'description'): ?>
                <textarea class="form-control" name="description" rows="2" placeholder="Deskripsi"></textarea>
            <?php elseif ($field === 'image'): ?>
                <input class="form-control" type="file" name="image" accept=".jpg,.jpeg,.png,.webp,.svg">
            <?php elseif ($field === 'status'): ?>
                <select class="form-select" name="status"><option value="active">Aktif</option><option value="inactive">Nonaktif</option></select>
            <?php else: ?>
                <input class="form-control" name="<?= h($field) ?>" type="<?= str_contains($field, 'price') || str_contains($field, 'rate') || str_contains($field, 'duration') || str_contains($field, 'qty') ? 'number' : 'text' ?>" placeholder="<?= h(ucwords(str_replace('_', ' ', $field))) ?>" required>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php }); ?>
    <?php
}

if ($page === 'booking_detail') {
    render_booking_detail($user, $role);
    return;
}

if ($page === 'estimate_detail') {
    render_estimate_detail($user, $role);
    return;
}

if ($page === 'revision_detail') {
    render_revision_detail($user, $role);
    return;
}

if ($page === 'invoice_detail') {
    render_invoice_detail($user, $role);
    return;
}

if ($page === 'payment_detail') {
    render_payment_detail($user, $role);
    return;
}

if (in_array($page, ['booking','surveys','works','progress','monitoring_booking','monitoring_works'], true)) {
    $filterStatus = trim((string) ($_GET['status'] ?? ''));
    $filterSearch = trim((string) ($_GET['q'] ?? ''));

    $where = [];
    $params = [];
    if ($role === 'customer') { $where[] = 'b.customer_id = ?'; $params[] = $user['id']; }
    elseif ($role === 'pekerja_lapangan') { $where[] = 'b.assigned_pekerja_lapangan_id = ?'; $params[] = $user['id']; }
    if ($filterStatus !== '') { $where[] = 'b.status = ?'; $params[] = $filterStatus; }
    if ($filterSearch !== '') {
        $where[] = '(b.booking_code LIKE ? OR u.name LIKE ? OR b.location_address LIKE ?)';
        $params[] = "%{$filterSearch}%"; $params[] = "%{$filterSearch}%"; $params[] = "%{$filterSearch}%";
    }
    $sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $baseSql = "SELECT b.*, u.name AS customer_name, u.phone AS customer_phone, fw.name AS pekerja_lapangan_name,
                s.name AS service_name
         FROM bookings b
         JOIN users u ON u.id=b.customer_id
         LEFT JOIN users fw ON fw.id=b.assigned_pekerja_lapangan_id
         LEFT JOIN services s ON s.id=b.service_id
         {$sqlWhere}
         ORDER BY b.updated_at DESC";
    $paged = paginate($baseSql, $params, 15);
    $rows = $paged['rows'];
    ?>
    <section class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex align-items-start justify-content-between gap-3 py-3 px-4">
            <div><p class="small fw-bold text-uppercase text-secondary">Order</p><h2 class="h5 fw-bold mb-0"><?= h($pageTitle) ?></h2></div>
            <?php if ($role === 'customer'): ?><a class="btn btn-primary" href="dashboard.php?page=catalog">Booking Baru</a><?php endif; ?>
        </div>
        <form class="d-flex flex-wrap gap-2 align-items-center p-3 bg-light border-bottom" method="get">
            <input type="hidden" name="page" value="<?= h($page) ?>">
            <input class="form-control form-control-sm" type="search" name="q" value="<?= h($filterSearch) ?>" placeholder="Cari kode, customer, alamat...">
            <select class="form-select form-select-sm" name="status">
                <option value="">Semua status</option>
                <?php foreach (ORDER_STATUSES as $st): ?>
                    <option value="<?= h($st) ?>" <?= $filterStatus === $st ? 'selected' : '' ?>><?= h($st) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-search"></i> Filter</button>
            <?php if ($filterStatus !== '' || $filterSearch !== ''): ?>
                <a class="btn btn-outline-secondary btn-sm" href="dashboard.php?page=<?= h($page) ?>">Reset</a>
            <?php endif; ?>
        </form>
        <?php render_booking_table($rows, $role); ?>
        <?php render_pagination($paged['page'], $paged['totalPages'], $paged['total'], $paged['perPage']); ?>
    </section>
    <?php
    return;
}

if ($role === 'staff' && in_array($page, ['catalog','materials','additional_costs','labor_rates'], true)) {
    render_master_page($page);
    return;
}

if ($page === 'catalog' && $role === 'customer') {
    render_customer_catalog_page();
    return;
}

if ($page === 'customers') {
    header('Location: dashboard.php?page=users&type=customer');
    return;
}

if ($page === 'users') {
    render_user_management_page($user);
    return;
}

if (in_array($page, ['pekerja_lapangan', 'monitoring_pekerja_lapangan'], true)) {
    require_roles(['staff', 'boss']);
    if ($page === 'pekerja_lapangan' && $role === 'staff') {
        header('Location: dashboard.php?page=users&type=pekerja_lapangan');
        return;
    }
    $rows = db_query(
        "SELECT u.*, COUNT(DISTINCT b.id) AS task_count,
                SUM(CASE WHEN b.status IN ('Survey Dijadwalkan','Survey Berlangsung','Estimasi Disetujui','Dalam Pengerjaan','Revisi Estimasi') THEN 1 ELSE 0 END) AS active_count,
                SUM(CASE WHEN b.status IN ('Pekerjaan Selesai','Selesai') THEN 1 ELSE 0 END) AS done_count,
                COALESCE(AVG(lp.progress_percent),0) AS avg_progress
         FROM users u
         LEFT JOIN bookings b ON b.assigned_pekerja_lapangan_id=u.id
         LEFT JOIN (SELECT booking_id, MAX(progress_percent) AS progress_percent FROM work_progress GROUP BY booking_id) lp ON lp.booking_id=b.id
         WHERE u.role='pekerja_lapangan'
         GROUP BY u.id
         ORDER BY u.name"
    );
    $title = $page === 'monitoring_pekerja_lapangan' ? 'Monitoring Pekerja Lapangan' : 'Kelola Pekerja Lapangan';
    ?>
    <section class="<?= $role === 'staff' ? 'row g-3' : '' ?>">
        <?php if ($role === 'staff'): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex align-items-start justify-content-between gap-3 py-3 px-4"><div><p class="small fw-bold text-uppercase text-secondary">Pekerja Lapangan</p><h2>Tambah Pekerja Lapangan</h2></div></div>
                <form data-api-form class="d-flex flex-column gap-3" method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="save_user">
                    <input type="hidden" name="role" value="pekerja_lapangan">
                    <input type="hidden" name="status" value="active">
                    <input class="form-control" name="name" placeholder="Nama pekerja lapangan" required>
                    <input class="form-control" type="email" name="email" placeholder="Email pekerja lapangan" required>
                    <input class="form-control" name="phone" placeholder="Nomor HP">
                    <textarea class="form-control" name="address" rows="2" placeholder="Area/alamat pekerja lapangan"></textarea>
                    <input class="form-control" type="password" name="password" placeholder="Password awal minimal 6 karakter" required>
                    <button class="btn btn-primary" type="submit">Simpan Pekerja Lapangan</button>
                </form>
            </div>
        <?php endif; ?>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex align-items-start justify-content-between gap-3 py-3 px-4"><div><p class="small fw-bold text-uppercase text-secondary">Pekerja Lapangan</p><h2><?= h($title) ?></h2></div></div>
            <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Nama</th><th>Email</th><th>HP</th><th>Total Tugas</th><th>Aktif</th><th>Selesai</th><th>Rata-rata Progress</th><th>Status</th><th></th></tr></thead><tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= h($row['name']) ?></td>
                        <td><?= h($row['email']) ?></td>
                        <td><?= h($row['phone']) ?></td>
                        <td><?= (int) $row['task_count'] ?></td>
                        <td><?= (int) $row['active_count'] ?></td>
                        <td><?= (int) $row['done_count'] ?></td>
                        <td><?= (int) round((float) $row['avg_progress']) ?>%</td>
                        <td><span class="<?= status_badge_class($row['status']) ?>"><?= h($row['status']) ?></span></td>
                        <td>
                            <?php if ($role === 'staff'): ?>
                                <?php render_api_form('delete_user', '<i class="bi bi-trash"></i> Hapus', ['user_id' => $row['id']], 'd-flex flex-wrap align-items-center gap-2', 'btn btn-outline-danger btn-sm', 'Hapus/nonaktifkan pekerja lapangan ' . $row['name'] . '?'); ?>
                            <?php else: ?>
                                <span class="text-muted small">Monitoring</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?><tr><td colspan="9" class="text-center text-muted py-4">Belum ada pekerja lapangan.</td></tr><?php endif; ?>
            </tbody></table></div>
        </div>
    </section>
    <?php
    return;
}

if ($page === 'schedules') {
    require_roles(['staff']);
    $filterType = trim((string) ($_GET['type'] ?? ''));
    $filterStatus = trim((string) ($_GET['status'] ?? ''));
    $filterSearch = trim((string) ($_GET['q'] ?? ''));
    $where = [];
    $params = [];
    if ($filterType !== '') { $where[] = 'sc.schedule_type = ?'; $params[] = $filterType; }
    if ($filterStatus !== '') { $where[] = 'sc.status = ?'; $params[] = $filterStatus; }
    if ($filterSearch !== '') { $where[] = '(b.booking_code LIKE ? OR u.name LIKE ?)'; $params[] = "%{$filterSearch}%"; $params[] = "%{$filterSearch}%"; }
    $sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $baseSql = "SELECT sc.*, b.booking_code, u.name AS worker_name FROM schedules sc JOIN bookings b ON b.id=sc.booking_id JOIN users u ON u.id=sc.pekerja_lapangan_id {$sqlWhere} ORDER BY sc.schedule_date DESC, sc.schedule_time DESC";
    $paged = paginate($baseSql, $params, 15);
    $rows = $paged['rows'];
    ?>
    <section class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex align-items-start justify-content-between gap-3 py-3 px-4"><div><p class="small fw-bold text-uppercase text-secondary">Jadwal</p><h2>Jadwal Survey dan Pekerjaan</h2></div></div>
        <form class="d-flex flex-wrap gap-2 align-items-center p-3 bg-light border-bottom" method="get">
            <input type="hidden" name="page" value="schedules">
            <input class="form-control form-control-sm" type="search" name="q" value="<?= h($filterSearch) ?>" placeholder="Cari kode booking, pekerja...">
            <select class="form-select form-select-sm" name="type">
                <option value="">Semua tipe</option>
                <?php foreach (['Survey','Pengerjaan','Follow Up','Revisi'] as $t): ?>
                    <option value="<?= h($t) ?>" <?= $filterType === $t ? 'selected' : '' ?>><?= h($t) ?></option>
                <?php endforeach; ?>
            </select>
            <select class="form-select form-select-sm" name="status">
                <option value="">Semua status</option>
                <?php foreach (['Terjadwal','Berlangsung','Selesai','Dijadwalkan Ulang','Dibatalkan'] as $st): ?>
                    <option value="<?= h($st) ?>" <?= $filterStatus === $st ? 'selected' : '' ?>><?= h($st) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-search"></i> Filter</button>
            <?php if ($filterType !== '' || $filterStatus !== '' || $filterSearch !== ''): ?>
                <a class="btn btn-outline-secondary btn-sm" href="dashboard.php?page=schedules">Reset</a>
            <?php endif; ?>
        </form>
        <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Kode</th><th>Booking</th><th>Pekerja Lapangan</th><th>Tipe</th><th>Tanggal</th><th>Status</th></tr></thead><tbody>
        <?php foreach ($rows as $row): ?>
            <tr><td><?= h($row['schedule_code']) ?></td><td><a href="dashboard.php?page=booking_detail&id=<?= (int) $row['booking_id'] ?>"><?= h($row['booking_code']) ?></a></td><td><?= h($row['worker_name']) ?></td><td><?= h($row['schedule_type']) ?></td><td><?= h(format_date_id($row['schedule_date']) . ' ' . substr($row['schedule_time'], 0, 5)) ?></td><td><span class="<?= status_badge_class($row['status']) ?>"><?= h($row['status']) ?></span></td></tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="6" class="text-center text-muted py-3">Tidak ada jadwal ditemukan.</td></tr><?php endif; ?>
        </tbody></table></div>
        <?php render_pagination($paged['page'], $paged['totalPages'], $paged['total'], $paged['perPage']); ?>
    </section>
    <?php
    return;
}

if (in_array($page, ['estimates','monitoring_estimates'], true)) {
    $whereConditions = [];
    $whereParams = [];
    if ($role === 'customer') { $whereConditions[] = 'b.customer_id = ?'; $whereParams[] = $user['id']; }
    elseif ($role === 'pekerja_lapangan') { $whereConditions[] = 'b.assigned_pekerja_lapangan_id = ?'; $whereParams[] = $user['id']; }
    $sqlWhere = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    $paged = paginate("SELECT e.*, b.booking_code, u.name AS customer_name FROM estimates e JOIN bookings b ON b.id=e.booking_id JOIN users u ON u.id=b.customer_id {$sqlWhere} ORDER BY e.updated_at DESC", $whereParams, 15);
    $rows = $paged['rows'];
    ?>
    <section class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex align-items-start justify-content-between gap-3 py-3 px-4"><div><p class="small fw-bold text-uppercase text-secondary">Estimasi</p><h2 class="h5 fw-bold mb-0"><?= h($pageTitle) ?></h2></div></div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>Kode</th><th>Booking</th><th>Customer</th><th>Total</th><th>Status</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><strong><?= h($row['estimate_code']) ?></strong></td>
                        <td><?= h($row['booking_code']) ?></td>
                        <td><?= h($row['customer_name']) ?></td>
                        <td><?= format_rupiah($row['grand_total']) ?></td>
                        <td><span class="<?= status_badge_class($row['status']) ?>"><?= h($row['status']) ?></span></td>
                        <td>
                            <div class="d-flex flex-wrap gap-2">
                                <a class="btn btn-outline-secondary btn-sm" href="dashboard.php?page=estimate_detail&id=<?= (int) $row['id'] ?>">Detail</a>
                                <?php if ($role === 'staff' && $row['status'] === 'Draft'): ?>
                                    <?php render_api_form('send_estimate', '<i class="bi bi-send"></i> Kirim', ['estimate_id' => $row['id']], 'd-flex flex-wrap align-items-center gap-2', 'btn btn-primary btn-sm'); ?>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?><tr><td colspan="6" class="text-center text-muted py-4">Belum ada estimasi.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php render_pagination($paged['page'], $paged['totalPages'], $paged['total'], $paged['perPage']); ?>
    </section>
    <?php
    return;
}

if (in_array($page, ['revisions'], true)) {
    $whereConditions = [];
    $whereParams = [];
    if ($role === 'customer') { $whereConditions[] = 'b.customer_id = ?'; $whereParams[] = $user['id']; }
    elseif ($role === 'pekerja_lapangan') { $whereConditions[] = 'b.assigned_pekerja_lapangan_id = ?'; $whereParams[] = $user['id']; }
    $sqlWhere = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    $paged = paginate("SELECT r.*, b.booking_code, u.name AS customer_name FROM estimate_revisions r JOIN bookings b ON b.id=r.booking_id JOIN users u ON u.id=b.customer_id {$sqlWhere} ORDER BY r.updated_at DESC", $whereParams, 15);
    $rows = $paged['rows'];
    ?>
    <section class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex align-items-start justify-content-between gap-3 py-3 px-4"><div><p class="small fw-bold text-uppercase text-secondary">Revisi</p><h2>Revisi Estimasi</h2></div></div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>Kode</th><th>Booking</th><th>Customer</th><th>Biaya Tambahan</th><th>Total Baru</th><th>Status</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><strong><?= h($row['revision_code']) ?></strong></td>
                        <td><?= h($row['booking_code']) ?></td>
                        <td><?= h($row['customer_name']) ?></td>
                        <td><?= format_rupiah($row['additional_total']) ?></td>
                        <td><?= format_rupiah($row['new_total']) ?></td>
                        <td><span class="<?= status_badge_class($row['status']) ?>"><?= h($row['status']) ?></span></td>
                        <td>
                            <div class="d-flex flex-wrap gap-2">
                                <a class="btn btn-outline-secondary btn-sm" href="dashboard.php?page=revision_detail&id=<?= (int) $row['id'] ?>">Detail</a>
                                <?php if ($role === 'staff' && $row['status'] === 'Draft'): ?>
                                    <?php render_api_form('send_revision', '<i class="bi bi-send"></i> Kirim', ['revision_id' => $row['id']], 'd-flex flex-wrap align-items-center gap-2', 'btn btn-primary btn-sm'); ?>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?><tr><td colspan="7" class="text-center text-muted py-4">Belum ada revisi estimasi.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php render_pagination($paged['page'], $paged['totalPages'], $paged['total'], $paged['perPage']); ?>
    </section>
    <?php
    return;
}

if (in_array($page, ['invoices','monitoring_invoices','payments','monitoring_payments'], true)) {
    $filterPayStatus = trim((string) ($_GET['pay_status'] ?? ''));
    $filterSearch = trim((string) ($_GET['q'] ?? ''));
    $conditions = [];
    $params = [];
    if ($role === 'customer') {
        $conditions[] = 'b.customer_id = ?';
        $params[] = $user['id'];
    } elseif ($role === 'pekerja_lapangan') {
        $conditions[] = 'b.assigned_pekerja_lapangan_id = ?';
        $params[] = $user['id'];
    }
    if ($filterPayStatus !== '') { $conditions[] = 'i.payment_status = ?'; $params[] = $filterPayStatus; }
    if ($filterSearch !== '') {
        $conditions[] = '(i.invoice_code LIKE ? OR b.booking_code LIKE ? OR u.name LIKE ?)';
        $params[] = "%{$filterSearch}%"; $params[] = "%{$filterSearch}%"; $params[] = "%{$filterSearch}%";
    }
    $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
    $baseSql = "SELECT i.*, b.booking_code, u.name AS customer_name FROM final_invoices i JOIN bookings b ON b.id=i.booking_id JOIN users u ON u.id=b.customer_id {$where} ORDER BY i.updated_at DESC";
    $paged = paginate($baseSql, $params, 15);
    $rows = $paged['rows'];
    $surveyFeeRows = [];
    if (in_array($page, ['payments','monitoring_payments'], true)) {
        $feeConditions = $conditions;
        $feeParams = $params;
        $feeConditions[] = "b.survey_fee_payment_status <> 'Tidak Ada'";
        $feeConditions[] = "NOT EXISTS (
            SELECT 1
            FROM final_invoices fee_i
            JOIN final_invoice_items fee_item ON fee_item.final_invoice_id=fee_i.id
            WHERE fee_i.booking_id=b.id AND fee_item.item_name='Survey Fee'
        )";
        $feeWhere = 'WHERE ' . implode(' AND ', $feeConditions);
        $surveyFeeRows = db_query(
            "SELECT b.id, b.booking_code, b.survey_fee_amount, b.survey_fee_payment_status, b.status, u.name AS customer_name
             FROM bookings b
             JOIN users u ON u.id=b.customer_id
             {$feeWhere}
             ORDER BY b.updated_at DESC",
            $feeParams
        );
    }
    ?>
    <section class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex align-items-start justify-content-between gap-3 py-3 px-4"><div><p class="small fw-bold text-uppercase text-secondary">Invoice</p><h2 class="h5 fw-bold mb-0"><?= h($pageTitle) ?></h2></div></div>
        <form class="d-flex flex-wrap gap-2 align-items-center p-3 bg-light border-bottom" method="get">
            <input type="hidden" name="page" value="<?= h($page) ?>">
            <input class="form-control form-control-sm" type="search" name="q" value="<?= h($filterSearch) ?>" placeholder="Cari invoice, booking, customer...">
            <select class="form-select form-select-sm" name="pay_status">
                <option value="">Semua status bayar</option>
                <option value="Belum Dibayar" <?= $filterPayStatus === 'Belum Dibayar' ? 'selected' : '' ?>>Belum Dibayar</option>
                <option value="Lunas" <?= $filterPayStatus === 'Lunas' ? 'selected' : '' ?>>Lunas</option>
            </select>
            <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-search"></i> Filter</button>
            <?php if ($filterPayStatus !== '' || $filterSearch !== ''): ?>
                <a class="btn btn-outline-secondary btn-sm" href="dashboard.php?page=<?= h($page) ?>">Reset</a>
            <?php endif; ?>
        </form>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>Invoice</th><th>Booking</th><th>Customer</th><th>Total</th><th>Pembayaran</th><th>Status</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><strong><?= h($row['invoice_code']) ?></strong></td>
                        <td><?= h($row['booking_code']) ?></td>
                        <td><?= h($row['customer_name']) ?></td>
                        <td><?= format_rupiah($row['final_total']) ?></td>
                        <td><span class="<?= status_badge_class($row['payment_status']) ?>"><?= h($row['payment_status']) ?></span></td>
                        <td><span class="<?= status_badge_class($row['status']) ?>"><?= h($row['status']) ?></span></td>
                        <td>
                            <div class="d-flex flex-wrap gap-2">
                                <a class="btn btn-outline-secondary btn-sm" href="<?= in_array($page, ['payments','monitoring_payments'], true) ? 'dashboard.php?page=payment_detail&type=invoice&id=' . (int) $row['id'] : 'dashboard.php?page=invoice_detail&id=' . (int) $row['id'] ?>">Detail</a>
                                <a class="btn btn-primary btn-sm" target="_blank" href="invoice_print.php?id=<?= (int) $row['id'] ?>"><i class="bi bi-printer"></i>Cetak</a>
                                <?php if ($role === 'staff' && $row['status'] === 'Draft'): ?>
                                    <?php render_api_form('send_invoice', '<i class="bi bi-send-check"></i> Review & Kirim', ['invoice_id' => $row['id']], 'd-flex flex-wrap align-items-center gap-2', 'btn btn-outline-secondary btn-sm'); ?>
                                <?php endif; ?>
                                <?php if ($role === 'staff' && $row['payment_status'] === 'Belum Dibayar'): ?>
                                    <?php render_api_form('mark_invoice_paid', '<i class="bi bi-cash-coin"></i> Tandai Lunas', ['invoice_id' => $row['id']], 'd-flex flex-wrap align-items-center gap-2', 'btn btn-success btn-sm', 'Tandai invoice ' . $row['invoice_code'] . ' sebagai lunas?'); ?>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?><tr><td colspan="7" class="text-center text-muted py-4">Belum ada invoice final.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php render_pagination($paged['page'], $paged['totalPages'], $paged['total'], $paged['perPage']); ?>
    </section>

    <?php if (in_array($page, ['payments','monitoring_payments'], true)): ?>
        <section class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-white d-flex align-items-start justify-content-between gap-3 py-3 px-4"><div><p class="small fw-bold text-uppercase text-secondary">Survey Fee</p><h2>Pembayaran survey ditolak</h2></div></div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Booking</th><th>Customer</th><th>Nominal</th><th>Status Survey Fee</th><th>Status Order</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($surveyFeeRows as $row): ?>
                        <tr>
                            <td><strong><?= h($row['booking_code']) ?></strong></td>
                            <td><?= h($row['customer_name']) ?></td>
                            <td><?= format_rupiah($row['survey_fee_amount']) ?></td>
                            <td><span class="<?= status_badge_class($row['survey_fee_payment_status']) ?>"><?= h($row['survey_fee_payment_status']) ?></span></td>
                            <td><span class="<?= status_badge_class($row['status']) ?>"><?= h($row['status']) ?></span></td>
                            <td>
                                <div class="d-flex flex-wrap gap-2">
                                    <a class="btn btn-outline-secondary btn-sm" href="dashboard.php?page=payment_detail&type=survey_fee&id=<?= (int) $row['id'] ?>">Detail</a>
                                    <?php if ($role === 'staff' && $row['survey_fee_payment_status'] === 'Belum Dibayar'): ?>
                                        <?php render_api_form('mark_survey_fee_paid', '<i class="bi bi-cash"></i> Tandai Lunas', ['booking_id' => $row['id']], 'd-flex flex-wrap align-items-center gap-2', 'btn btn-success btn-sm', 'Tandai survey fee ' . $row['booking_code'] . ' sebagai lunas?'); ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$surveyFeeRows): ?><tr><td colspan="6" class="text-center text-muted py-4">Belum ada survey fee yang perlu ditandai.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>
    <?php
    return;
}

if ($page === 'settings') {
    require_roles(['staff']);
    $appName = setting('app_name', 'ABI Taman');
    $companyName = setting('company_name', 'ABI Taman');
    $companyEmail = setting('company_email', '');
    $companyPhone = setting('company_phone', '');
    $companyAddress = setting('company_address', '');
    $surveyFeeAmount = setting('survey_fee_amount', '0');
    $settingsFields = [
        'app_name' => [
            'label' => 'Nama Aplikasi',
            'type' => 'text',
            'value' => $appName,
            'hint' => 'Nama yang tampil pada title dan identitas sistem.',
        ],
        'company_name' => [
            'label' => 'Nama Perusahaan',
            'type' => 'text',
            'value' => $companyName,
            'hint' => 'Dipakai pada invoice, laporan, dan tampilan publik.',
        ],
        'company_email' => [
            'label' => 'Email Perusahaan',
            'type' => 'email',
            'value' => $companyEmail,
            'hint' => 'Kontak resmi untuk komunikasi pelanggan.',
        ],
        'company_phone' => [
            'label' => 'Nomor HP Perusahaan',
            'type' => 'tel',
            'value' => $companyPhone,
            'hint' => 'Nomor kontak utama yang dapat dihubungi pelanggan.',
        ],
        'company_address' => [
            'label' => 'Alamat Perusahaan',
            'type' => 'textarea',
            'value' => $companyAddress,
            'hint' => 'Opsional, berguna untuk dokumen cetak dan identitas usaha.',
        ],
        'survey_fee_amount' => [
            'label' => 'Survey Fee',
            'type' => 'number',
            'value' => $surveyFeeAmount,
            'hint' => 'Nominal biaya survey default dalam rupiah.',
        ],
    ];
    ?>
    <header class="settings-section-head">
        <div>
            <p class="eyebrow">Konfigurasi dasar</p>
            <h2>Umum</h2>
            <p>Informasi yang menjadi identitas aplikasi, perusahaan, dan nilai default pada alur layanan.</p>
        </div>
    </header>

    <section class="settings-general-grid">
        <aside class="settings-card settings-overview">
            <p class="eyebrow">Pengaturan umum</p>
            <h2 class="h4 mb-2">Sistem &amp; Perusahaan</h2>
            <p class="text-muted">Konfigurasi inti yang dipakai pada aplikasi, invoice, laporan, dan alur booking.</p>
            <span class="status-label">Konfigurasi aktif</span>
            <dl class="profile-meta mt-3">
                <div><dt>Aplikasi</dt><dd><?= h($appName) ?></dd></div>
                <div><dt>Perusahaan</dt><dd><?= h($companyName) ?></dd></div>
                <div><dt>Kontak</dt><dd><?= h($companyPhone ?: $companyEmail ?: 'Belum diisi') ?></dd></div>
                <div><dt>Survey Fee</dt><dd><?= format_rupiah((int) $surveyFeeAmount) ?></dd></div>
            </dl>
        </aside>

        <section class="settings-card settings-form-card">
            <div class="section-heading">
                <div>
                    <p class="eyebrow">Konfigurasi</p>
                    <h2>Informasi utama</h2>
                </div>
            </div>
            <form data-api-form class="row g-3" method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_settings">

                <div class="col-12 form-section-label">
                    <div><strong>Identitas Aplikasi</strong><span>Nama sistem dan identitas usaha yang tampil ke pengguna.</span></div>
                </div>
                <?php foreach (['app_name', 'company_name'] as $key): $field = $settingsFields[$key]; ?>
                    <label class="col-md-6"><?= h($field['label']) ?><input class="form-control" type="<?= h($field['type']) ?>" name="<?= h($key) ?>" value="<?= h($field['value']) ?>"><small><?= h($field['hint']) ?></small></label>
                <?php endforeach; ?>

                <div class="col-12 form-section-label">
                    <div><strong>Kontak Perusahaan</strong><span>Informasi kontak dasar untuk pelanggan dan dokumen.</span></div>
                </div>
                <?php foreach (['company_email', 'company_phone'] as $key): $field = $settingsFields[$key]; ?>
                    <label class="col-md-6"><?= h($field['label']) ?><input class="form-control" type="<?= h($field['type']) ?>" name="<?= h($key) ?>" value="<?= h($field['value']) ?>"><small><?= h($field['hint']) ?></small></label>
                <?php endforeach; ?>
                <?php $addressField = $settingsFields['company_address']; ?>
                <label class="col-12"><?= h($addressField['label']) ?><textarea class="form-control" name="company_address" rows="3"><?= h($addressField['value']) ?></textarea><small><?= h($addressField['hint']) ?></small></label>

                <div class="col-12 form-section-label">
                    <div><strong>Biaya Survey</strong><span>Nominal default untuk invoice survey fee.</span></div>
                </div>
                <?php $feeField = $settingsFields['survey_fee_amount']; ?>
                <label class="col-12"><?= h($feeField['label']) ?><input class="form-control" type="number" min="0" step="1000" name="survey_fee_amount" value="<?= h($feeField['value']) ?>"><small><?= h($feeField['hint']) ?></small></label>

                <div class="col-12 d-flex flex-wrap gap-2">
                    <button class="btn btn-primary" type="submit"><i class="bi bi-check2-circle me-1"></i>Simpan Pengaturan</button>
                </div>
            </form>
        </section>
    </section>
    <?php
    return;
}

if ($page === 'activity_logs') {
    require_roles(['staff']);
    $perPage = 20;
    $activityPage = max(1, (int) ($_GET['log_page'] ?? 1));
    $totalRows = (int) (db_first('SELECT COUNT(*) AS c FROM activity_logs')['c'] ?? 0);
    $totalPages = max(1, (int) ceil($totalRows / $perPage));
    $activityPage = min($activityPage, $totalPages);
    $offset = ($activityPage - 1) * $perPage;
    $rows = db_query(
        "SELECT l.*, u.name AS user_name
         FROM activity_logs l
         LEFT JOIN users u ON u.id=l.user_id
         ORDER BY l.created_at DESC
         LIMIT {$perPage} OFFSET {$offset}"
    );
    $paginationUrl = static function (int $targetPage): string {
        $params = $_GET;
        if (!empty($GLOBALS['settingsHubContext'])) {
            $params['page'] = 'settings';
            $params['tab'] = 'activity';
        } else {
            $params['page'] = 'activity_logs';
        }
        $params['log_page'] = $targetPage;
        return 'dashboard.php?' . http_build_query($params);
    };
    $fromRow = $totalRows > 0 ? $offset + 1 : 0;
    $toRow = min($offset + $perPage, $totalRows);
    ?>
    <header class="settings-section-head">
        <div>
            <p class="eyebrow">Audit sistem</p>
            <h2>Activity Log</h2>
            <p>Telusuri perubahan penting dan aktivitas pengguna untuk menjaga proses operasional tetap transparan.</p>
        </div>
        <button class="btn btn-outline-secondary btn-sm" type="button" data-table-download data-target="activity-log-table" data-filename="activity-log-halaman-<?= (int) $activityPage ?>"><i class="bi bi-download me-1"></i>Download CSV</button>
    </header>

    <div class="settings-kpis" style="--settings-kpi-columns: 2">
        <div class="settings-kpi"><span>Total aktivitas</span><strong><?= (int) $totalRows ?></strong></div>
        <div class="settings-kpi"><span>Ditampilkan</span><strong><?= (int) $fromRow ?>–<?= (int) $toRow ?></strong></div>
    </div>

    <section class="settings-panel">
        <div class="settings-panel__header">
            <div>
                <p class="eyebrow">Riwayat</p>
                <h2>Aktivitas sistem</h2>
            </div>
            <span class="small text-muted">Halaman <?= (int) $activityPage ?> dari <?= (int) $totalPages ?></span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 settings-table settings-table--activity" id="activity-log-table">
                <thead><tr><th>Waktu</th><th>User</th><th>Aktivitas</th><th>Deskripsi</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><span class="small text-secondary"><?= h(format_datetime($row['created_at'])) ?></span></td>
                        <td><strong class="fw-semibold"><?= h($row['user_name'] ?? 'System') ?></strong></td>
                        <td><span class="badge text-bg-info"><?= h($row['activity_type']) ?></span></td>
                        <td><p class="mb-0"><?= h($row['description']) ?></p></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?><tr><td colspan="4" class="text-center text-muted py-4">Belum ada activity log.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="d-flex justify-content-center mt-3">
            <span>Halaman <?= (int) $activityPage ?> dari <?= (int) $totalPages ?></span>
            <nav aria-label="Pagination activity log">
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?= $activityPage <= 1 ?'disabled' : '' ?>"><a class="page-link" href="<?= h($paginationUrl(max(1, $activityPage - 1))) ?>">Sebelumnya</a></li>
                    <?php
                    $startPage = max(1, $activityPage - 2);
                    $endPage = min($totalPages, $activityPage + 2);
                    for ($number = $startPage; $number <= $endPage; $number++):
                    ?>
                        <li class="page-item <?= $number === $activityPage ?'active' : '' ?>"><a class="page-link" href="<?= h($paginationUrl($number)) ?>"><?= (int) $number ?></a></li>
                    <?php endfor; ?>
                    <li class="page-item <?= $activityPage >= $totalPages ?'disabled' : '' ?>"><a class="page-link" href="<?= h($paginationUrl(min($totalPages, $activityPage + 1))) ?>">Berikutnya</a></li>
                </ul>
            </nav>
        </div>
    </section>
    <?php
    return;
}

if ($page === 'reports') {
    require_roles(['staff', 'boss']);
    $start = $_GET['start'] ?? date('Y-m-01');
    $end = $_GET['end'] ?? date('Y-m-d');
    $status = $_GET['status'] ?? '';
    $service = 0;
    $worker = 0;
    $where = ['DATE(b.created_at) BETWEEN ? AND ?'];
    $params = [$start, $end];
    if ($status) {$where[] = 'b.status=?'; $params[] = $status;}
    if ($service) {$where[] = 'b.service_id=?'; $params[] = $service;}
    if ($worker) {$where[] = 'b.assigned_pekerja_lapangan_id=?'; $params[] = $worker;}
    $sqlWhere = 'WHERE ' . implode(' AND ', $where);
    $avgDuration = (float) (db_first("SELECT COALESCE(AVG(i.work_days_actual),0) AS avg_days FROM final_invoices i JOIN bookings b ON b.id=i.booking_id {$sqlWhere}", $params)['avg_days'] ?? 0);
    $summary = [
        'Total booking' => db_first("SELECT COUNT(*) AS c FROM bookings b {$sqlWhere}", $params)['c'] ?? 0,
        'Estimasi disetujui' => db_first("SELECT COUNT(*) AS c FROM estimates e JOIN bookings b ON b.id=e.booking_id {$sqlWhere} AND e.status='Disetujui'", $params)['c'] ?? 0,
        'Estimasi ditolak' => db_first("SELECT COUNT(*) AS c FROM estimates e JOIN bookings b ON b.id=e.booking_id {$sqlWhere} AND e.status='Ditolak'", $params)['c'] ?? 0,
        'Order dibatalkan' => db_first("SELECT COUNT(*) AS c FROM bookings b {$sqlWhere} AND b.status='Dibatalkan'", $params)['c'] ?? 0,
        'Pekerjaan aktif' => db_first("SELECT COUNT(*) AS c FROM bookings b {$sqlWhere} AND b.status IN ('Dalam Pengerjaan','Revisi Estimasi')", $params)['c'] ?? 0,
        'Pekerjaan selesai' => db_first("SELECT COUNT(*) AS c FROM bookings b {$sqlWhere} AND b.status IN ('Pekerjaan Selesai','Selesai')", $params)['c'] ?? 0,
        'Invoice final dibuat' => db_first("SELECT COUNT(*) AS c FROM final_invoices i JOIN bookings b ON b.id=i.booking_id {$sqlWhere}", $params)['c'] ?? 0,
        'Invoice lunas' => db_first("SELECT COUNT(*) AS c FROM final_invoices i JOIN bookings b ON b.id=i.booking_id {$sqlWhere} AND i.payment_status='Lunas'", $params)['c'] ?? 0,
        'Invoice belum lunas' => db_first("SELECT COUNT(*) AS c FROM final_invoices i JOIN bookings b ON b.id=i.booking_id {$sqlWhere} AND i.payment_status='Belum Dibayar'", $params)['c'] ?? 0,
        'Rata-rata durasi pekerjaan' => number_format($avgDuration, 1, ',', '.') . ' hari',
    ];
    $income = db_first("SELECT COALESCE(SUM(p.amount),0) AS total FROM payments p JOIN bookings b ON b.id=p.booking_id {$sqlWhere} AND p.payment_status='Lunas' AND p.payment_type='Invoice Final'", $params)['total'] ?? 0;
    $surveyFee = db_first("SELECT COALESCE(SUM(p.amount),0) AS total FROM payments p JOIN bookings b ON b.id=p.booking_id {$sqlWhere} AND p.payment_status='Lunas' AND p.payment_type='Survey Fee'", $params)['total'] ?? 0;
    $incomeThisMonth = db_first("SELECT COALESCE(SUM(amount),0) AS total FROM payments WHERE payment_status='Lunas' AND payment_type='Invoice Final' AND DATE_FORMAT(COALESCE(paid_at, created_at),'%Y-%m')=?", [date('Y-m')])['total'] ?? 0;
    $statusRows = db_query("SELECT b.status, COUNT(*) AS total FROM bookings b {$sqlWhere} GROUP BY b.status ORDER BY total DESC", $params);
    $serviceRows = db_query(
        "SELECT COALESCE(s.name, 'Custom Layanan') AS name, COUNT(DISTINCT b.id) AS booking_count,
                COALESCE(SUM(i.final_total),0) AS invoice_total, 0 AS min_price
         FROM bookings b
         LEFT JOIN services s ON s.id=b.service_id
         LEFT JOIN final_invoices i ON i.booking_id=b.id
         {$sqlWhere}
         GROUP BY COALESCE(s.name, 'Custom Layanan')
         ORDER BY booking_count DESC, invoice_total DESC
         LIMIT 8",
        $params
    );
    $revenueWhere = ['DATE(COALESCE(p.paid_at, p.created_at)) BETWEEN ? AND ?'];
    $revenueParams = [$start, $end];
    if ($status) {$revenueWhere[] = 'b.status=?'; $revenueParams[] = $status;}
    if ($service) {$revenueWhere[] = 'b.service_id=?'; $revenueParams[] = $service;}
    if ($worker) {$revenueWhere[] = 'b.assigned_pekerja_lapangan_id=?'; $revenueParams[] = $worker;}
    $revenueSqlWhere = 'WHERE ' . implode(' AND ', $revenueWhere);
    $revenueRows = db_query(
        "SELECT DATE_FORMAT(COALESCE(p.paid_at, p.created_at),'%d %b') AS label, COALESCE(SUM(p.amount),0) AS total
         FROM payments p
         JOIN bookings b ON b.id=p.booking_id
         {$revenueSqlWhere}
         AND p.payment_status='Lunas' AND p.payment_type='Invoice Final'
         GROUP BY DATE(COALESCE(p.paid_at, p.created_at)), label
         ORDER BY DATE(COALESCE(p.paid_at, p.created_at))",
        $revenueParams
    );
    $maxStatus = max(array_column($statusRows, 'total') ?: [0]);
    $maxService = max(array_column($serviceRows, 'booking_count') ?: [0]);
    $maxRevenue = max(array_column($revenueRows, 'total') ?: [0]);
    $overviewCards = [
        'Total booking' => $summary['Total booking'],
        'Pekerjaan aktif' => $summary['Pekerjaan aktif'],
        'Pekerjaan selesai' => $summary['Pekerjaan selesai'],
        'Invoice lunas' => $summary['Invoice lunas'],
        'Invoice belum lunas' => $summary['Invoice belum lunas'],
        'Rata-rata durasi' => $summary['Rata-rata durasi pekerjaan'],
    ];
    $moneyCards = [
        ['label' => 'Pendapatan bulan ini', 'value' => format_rupiah($incomeThisMonth), 'icon' => 'bi-cash-stack'],
        ['label' => 'Pendapatan lunas', 'value' => format_rupiah($income), 'icon' => 'bi-graph-up-arrow'],
        ['label' => 'Survey fee', 'value' => format_rupiah($surveyFee), 'icon' => 'bi-receipt'],
    ];
    ?>
    <header class="page-intro">
        <div class="page-intro__copy">
            <p class="page-kicker">Kinerja bisnis</p>
            <h1>Laporan</h1>
            <p>Analisis pendapatan, status order, dan layanan dalam satu tampilan.</p>
        </div>
    </header>

    <section class="filter-toolbar">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div><p class="eyebrow">Filter laporan</p><h2 class="h5 mb-0">Periode &amp; status</h2></div>
            <span class="small text-muted"><?= h(format_date_id($start)) ?> sampai <?= h(format_date_id($end)) ?> · <?= h($status ?: 'Semua status') ?></span>
        </div>
        <form class="row g-3" method="get">
            <input type="hidden" name="page" value="reports">
            <label class="col-md-4">Mulai<input class="form-control" type="date" name="start" value="<?= h($start) ?>"></label>
            <label class="col-md-4">Sampai<input class="form-control" type="date" name="end" value="<?= h($end) ?>"></label>
            <label class="col-md-4">Status<select class="form-select" name="status"><option value="">Semua status</option><?php foreach (ORDER_STATUSES as $st): ?><option value="<?= h($st) ?>" <?= $status === $st ? 'selected' : '' ?>><?= h($st) ?></option><?php endforeach; ?></select></label>
            <div class="col-12 d-flex flex-wrap gap-2">
                <button class="btn btn-primary" type="submit"><i class="bi bi-funnel me-1"></i>Terapkan</button>
                <a class="btn btn-outline-secondary" href="dashboard.php?page=reports">Reset</a>
                <button class="btn btn-outline-secondary" type="button" onclick="window.print()"><i class="bi bi-printer me-1"></i>Cetak</button>
            </div>
        </form>
    </section>

    <section class="d-flex flex-column gap-3">
        <section class="d-none d-print-block">
            <h1>Laporan ABI Taman</h1>
            <p>Periode <?= h(format_date_id($start)) ?> sampai <?= h(format_date_id($end)) ?> · Status: <?= h($status ?: 'Semua status') ?></p>
        </section>

        <section class="summary-rail" style="--metric-columns: 3">
            <?php foreach ($moneyCards as $card): ?>
                <article class="summary-rail__item">
                    <strong><?= h($card['value']) ?></strong>
                    <span><?= h($card['label']) ?></span>
                </article>
            <?php endforeach; ?>
        </section>

        <section class="summary-rail summary-rail--compact">
            <?php foreach ($overviewCards as $label => $value): ?>
                <article class="summary-rail__item"><strong><?= h($value) ?></strong><span><?= h($label) ?></span></article>
            <?php endforeach; ?>
        </section>

        <section class="report-grid">
            <article class="workspace-section">
                <div class="workspace-section__header"><div><p class="eyebrow">Pendapatan</p><h2>Invoice lunas</h2></div></div>
                <div class="d-flex flex-column gap-3" id="report-revenue-chart">
                    <?php foreach ($revenueRows as $row): $width = chart_percent($row['total'], $maxRevenue, 0); ?>
                        <div><div class="d-flex justify-content-between gap-3 small mb-1"><span><?= h($row['label']) ?></span><strong><?= format_rupiah($row['total']) ?></strong></div><div class="progress"><div class="progress-bar bg-success" style="width:<?= $width ?>%"></div></div></div>
                    <?php endforeach; ?>
                    <?php if (!$revenueRows): ?><p class="text-muted small mb-0">Belum ada pendapatan lunas dalam filter ini.</p><?php endif; ?>
                </div>
            </article>
            <article class="workspace-section">
                <div class="workspace-section__header"><div><p class="eyebrow">Booking</p><h2>Status order</h2></div></div>
                <div class="d-flex flex-column gap-3" id="report-status-chart">
                    <?php foreach ($statusRows as $row): $width = chart_percent($row['total'], $maxStatus, 0); ?>
                        <div><div class="d-flex justify-content-between gap-3 small mb-1"><span><?= h($row['status']) ?></span><strong><?= (int) $row['total'] ?></strong></div><div class="progress"><div class="progress-bar bg-info" style="width:<?= $width ?>%"></div></div></div>
                    <?php endforeach; ?>
                    <?php if (!$statusRows): ?><p class="text-muted small mb-0">Belum ada status booking.</p><?php endif; ?>
                </div>
            </article>
        </section>

        <section class="workspace-section">
            <div class="workspace-section__header"><div><p class="eyebrow">Layanan</p><h2>Performa layanan</h2></div></div>
            <div class="list-group list-group-flush" id="report-service-chart">
                <?php foreach ($serviceRows as $row): $width = chart_percent($row['booking_count'], $maxService, 0); ?>
                    <article class="list-group-item px-0"><div class="d-flex justify-content-between gap-3 mb-2"><div><strong class="d-block"><?= h($row['name']) ?></strong><span class="small text-secondary"><?= (int) $row['booking_count'] ?> booking</span></div><b><?= format_rupiah($row['invoice_total']) ?></b></div><div class="progress"><div class="progress-bar bg-success" style="width:<?= $width ?>%"></div></div></article>
                <?php endforeach; ?>
                <?php if (!$serviceRows): ?><p class="text-muted small mb-0">Belum ada data layanan.</p><?php endif; ?>
            </div>
        </section>

        <section class="report-grid">
            <article class="workspace-section">
                <div class="workspace-section__header"><div><p class="eyebrow">Data pendapatan</p><h2>Tanggal dan nominal</h2></div><button class="btn btn-outline-secondary btn-sm" type="button" data-table-download data-target="report-revenue-table" data-filename="data-pendapatan"><i class="bi bi-download me-1"></i>CSV</button></div>
                <div class="table-responsive"><table class="table align-middle table table-hover align-middle mb-0" id="report-revenue-table"><thead><tr><th>Tanggal</th><th>Pendapatan</th></tr></thead><tbody><?php foreach ($revenueRows as $row): ?><tr><td><?= h($row['label']) ?></td><td><?= format_rupiah($row['total']) ?></td></tr><?php endforeach; ?><?php if (!$revenueRows): ?><tr><td colspan="2">Belum ada data</td></tr><?php endif; ?></tbody></table></div>
            </article>
        </section>
    </section>
    <?php
    return;
}

echo '<section class="card border-0 shadow-sm"><div class="card-header bg-white d-flex align-items-start justify-content-between gap-3 py-3 px-4"><div><p class="small fw-bold text-uppercase text-secondary">Modul</p><h2>' . h($pageTitle) . '</h2></div></div><p class="text-muted small">Modul ini tersedia dalam konteks role final dan akan menampilkan data sesuai akses Anda.</p></section>';
