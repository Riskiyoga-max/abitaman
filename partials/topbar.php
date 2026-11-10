<header class="navbar sticky-top app-topbar">
    <div class="d-flex align-items-center gap-3">
        <button class="btn btn-link text-dark d-lg-none p-0" type="button" data-bs-toggle="offcanvas" data-bs-target="#appSidebar" aria-controls="appSidebar" aria-label="Buka menu">
            <i class="bi bi-list fs-4"></i>
        </button>
        <div class="app-topbar-title">
            <span><?= h(role_label($role)) ?></span>
            <h1><?= h($pageTitle) ?></h1>
        </div>
    </div>

    <div class="d-flex align-items-center gap-2">
        <?php $roleNotifications = $notifications ?? []; ?>
        <div class="dropdown">
            <button class="btn btn-link p-2 position-relative text-secondary" type="button"
                    data-bs-toggle="dropdown" aria-expanded="false" aria-label="Notifikasi">
                <i class="bi bi-bell fs-5"></i>
                <?php if ($roleNotifications): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" data-notification-count>
                        <?= count($roleNotifications) ?>
                    </span>
                <?php else: ?>
                    <span class="d-none" data-notification-count>0</span>
                <?php endif; ?>
            </button>
            <div class="dropdown-menu dropdown-menu-end shadow overflow-y-auto">
                <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom">
                    <span class="small fw-bold text-uppercase mb-0">Notifikasi</span>
                    <button class="btn btn-outline-secondary btn-sm" type="button"
                            data-action-button data-action="mark_notifications_read">
                        Tandai dibaca
                    </button>
                </div>
                <?php foreach ($roleNotifications as $notice): ?>
                    <a class="dropdown-item py-2 px-3 border-bottom"
                       href="<?= h(notification_target_url($notice)) ?>"
                       >
                        <strong class="d-block small"><?= h($notice['title']) ?></strong>
                        <span class="text-muted small text-wrap"><?= h($notice['message'] ?? 'Buka halaman terkait') ?></span>
                    </a>
                <?php endforeach; ?>
                <?php if (!$roleNotifications): ?>
                    <div class="px-3 py-3 text-center text-muted small">
                        <i class="bi bi-bell-slash d-block mb-1 fs-5"></i>
                        Tidak ada notifikasi baru
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="dropdown">
            <button class="btn btn-link text-dark text-decoration-none d-flex align-items-center gap-2 px-2"
                    type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Menu akun">
                <span class="fw-semibold"><?= h($user['name'] ?? $user['nama']) ?></span>
                <i class="bi bi-chevron-down"></i>
            </button>
            <div class="dropdown-menu dropdown-menu-end shadow p-0">
                <div class="p-3 border-bottom">
                    <p class="small fw-bold text-uppercase text-secondary mb-1">Akun</p>
                    <strong><?= h($user['name'] ?? $user['nama'] ?? 'User') ?></strong>
                </div>
                <div class="p-3 small">
                    <div class="mb-2"><span class="d-block text-secondary">Email</span><strong><?= h($user['email'] ?? '-') ?></strong></div>
                    <div class="mb-2"><span class="d-block text-secondary">Role</span><strong><?= h(role_label($role)) ?></strong></div>
                    <div><span class="d-block text-secondary">Nomor HP</span><strong><?= h($user['phone'] ?? '-') ?></strong></div>
                </div>
                <div class="d-flex gap-2 p-3 border-top">
                    <a class="btn btn-outline-secondary btn-sm" href="dashboard.php?page=profil">
                        <i class="bi bi-person-circle me-1"></i>Edit Profil
                    </a>
                    <form method="post" action="logout.php">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-success btn-sm">
                            <i class="bi bi-box-arrow-right me-1"></i>Logout
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</header>
