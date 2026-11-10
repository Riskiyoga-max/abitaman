<?php
$menus = match ($role) {
    'customer' => [
        ['page' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'bi-grid-1x2'],
        ['page' => 'catalog', 'label' => 'Katalog', 'icon' => 'bi-flower1'],
        ['page' => 'order', 'label' => 'Order Saya', 'icon' => 'bi-bag-check'],
        ['page' => 'profil', 'label' => 'Profil', 'icon' => 'bi-person-circle'],
    ],
    'pekerja_lapangan' => [
        ['page' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'bi-grid-1x2'],
        ['page' => 'tasks', 'label' => 'Tugas Saya', 'icon' => 'bi-clipboard2-check'],
        ['page' => 'profil', 'label' => 'Profil', 'icon' => 'bi-person-circle'],
    ],
    'boss' => [
        ['page' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'bi-grid-1x2'],
        ['page' => 'monitoring', 'label' => 'Monitoring', 'icon' => 'bi-binoculars'],
        ['page' => 'reports', 'label' => 'Laporan', 'icon' => 'bi-file-earmark-bar-graph'],
        ['page' => 'profil', 'label' => 'Profil', 'icon' => 'bi-person-circle'],
    ],
    default => [
        ['page' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'bi-grid-1x2'],
        ['page' => 'order', 'label' => 'Order', 'icon' => 'bi-inbox'],
        ['page' => 'settings', 'label' => 'Pengaturan', 'icon' => 'bi-sliders'],
        ['page' => 'reports', 'label' => 'Laporan', 'icon' => 'bi-file-earmark-bar-graph'],
        ['page' => 'profil', 'label' => 'Profil', 'icon' => 'bi-person-circle'],
    ],
};

$activePage = $page;

// Map detail/sub pages ke parent menu yang aktif
$activePageMap = [
    'booking_detail'  => match ($role) {
        'customer'          => 'order',
        'pekerja_lapangan'  => 'tasks',
        'boss'              => 'monitoring',
        default             => 'order',
    },
    'estimate_detail' => match ($role) {
        'boss'              => 'booking_estimate',
        'customer'          => 'booking_estimate',
        'pekerja_lapangan'  => 'booking_estimate',
        default             => 'booking_estimate',
    },
    'revision_detail' => match ($role) {
        'pekerja_lapangan'  => 'revisions',
        default             => 'booking_work',
    },
    'invoice_detail'  => match ($role) {
        'boss'              => 'booking_invoice',
        default             => 'booking_invoice',
    },
    'payment_detail'  => match ($role) {
        'boss'              => 'booking_payment',
        'customer'          => 'booking_payment',
        default             => 'booking_payment',
    },
];

foreach (['estimate_detail', 'revision_detail', 'invoice_detail', 'payment_detail',
          'booking_schedule', 'booking_survey', 'booking_estimate', 'booking_work',
          'booking_invoice', 'booking_payment', 'monitoring_booking', 'monitoring_works',
          'monitoring_pekerja_lapangan', 'monitoring_estimates', 'monitoring_invoices',
          'monitoring_payments'] as $workflowPage) {
    $activePageMap[$workflowPage] = match ($role) {
        'customer' => 'order',
        'pekerja_lapangan' => 'tasks',
        'boss' => 'monitoring',
        default => 'order',
    };
}

if (isset($activePageMap[$page])) {
    $activePage = $activePageMap[$page];
}
?>
<aside class="offcanvas-lg offcanvas-start col-lg-3 col-xl-2 text-white p-0" tabindex="-1" id="appSidebar" aria-label="Navigasi utama">
    <div class="offcanvas-header app-sidebar-brand">
        <a href="dashboard.php" class="d-flex align-items-center gap-2 text-white text-decoration-none">
            <img src="assets/img/abi-taman.png" alt="ABI Taman" width="30" height="30">
            <span class="lh-sm">
                <strong class="d-block">ABI TAMAN</strong>
                <small><?= h(role_label($role)) ?></small>
            </span>
        </a>
        <button class="btn-close btn-close-white d-lg-none" type="button" data-bs-dismiss="offcanvas" data-bs-target="#appSidebar" aria-label="Tutup menu"></button>
    </div>

    <div class="offcanvas-body d-flex flex-column p-3">
      <nav class="nav flex-column flex-grow-1 app-sidebar-nav">
        <p class="app-sidebar-label">Ruang kerja</p>
        <?php foreach ($menus as $item): ?>
            <?php
            $isActive = $activePage === $item['page'];
            $href = 'dashboard.php?page=' . urlencode($item['page']);
            ?>
            <a class="nav-link d-flex align-items-center gap-3 <?= $isActive ? 'active' : '' ?>" href="<?= h($href) ?>" data-sidebar-link>
                <i class="bi <?= h($item['icon']) ?>"></i>
                <span><?= h($item['label']) ?></span>
            </a>
        <?php endforeach; ?>
      </nav>

      <div class="app-sidebar-footer">
        <form method="post" action="logout.php">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-link text-white-50 w-100 d-flex align-items-center gap-3 px-2">
                <i class="bi bi-box-arrow-right"></i><span>Logout</span>
            </button>
        </form>
      </div>
    </div>
</aside>
