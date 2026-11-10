<?php
require_once __DIR__ . '/data/session_helper.php';
require_login();
refresh_current_user();

$user = current_user();
$role = $user['role'];
$page = preg_replace('/[^a-z_]/', '', $_GET['page'] ?? 'dashboard') ?: 'dashboard';

$canonicalListPage = match ($role) {
    'customer' => 'order',
    'pekerja_lapangan' => 'tasks',
    'boss' => 'monitoring',
    default => 'order',
};
$legacyListStages = [
    'booking' => 'booking',
    'monitoring_booking' => '',
    'booking_schedule' => 'survey',
    'booking_survey' => 'survey',
    'booking_estimate' => 'approval',
    'booking_work' => 'work',
    'booking_invoice' => 'completion',
    'booking_payment' => 'completion',
];
if (array_key_exists($page, $legacyListStages)) {
    $legacyId = max(0, (int) ($_GET['id'] ?? 0));
    if ($legacyId > 0 && $page !== 'booking' && $page !== 'monitoring_booking') {
        $section = match ($page) {
            'booking_schedule', 'booking_survey' => 'survey',
            'booking_estimate' => 'estimate',
            'booking_work' => 'progress',
            default => 'billing',
        };
        header('Location: dashboard.php?' . http_build_query(['page' => 'booking_detail', 'id' => $legacyId, 'section' => $section]));
        exit;
    }

    $query = $_GET;
    $query['page'] = $canonicalListPage;
    if ($legacyListStages[$page] !== '') {
        $query['stage'] = $legacyListStages[$page];
    }
    unset($query['id']);
    header('Location: dashboard.php?' . http_build_query($query));
    exit;
}

if (!can_access_page($page, $role)) {
    flash_message('Anda tidak memiliki akses ke halaman tersebut.', 'warning');
    $page = 'dashboard';
}

$pageTitle = page_label($page, $role);
$flash = flash_message();
$notifications = db_query(
    'SELECT * FROM notifications
     WHERE (user_id = ? OR role_target = ?) AND is_read = 0
     ORDER BY created_at DESC LIMIT 8',
    [$user['id'], $role]
);

require __DIR__ . '/partials/header.php';
?>
<div class="container-fluid app-layout">
    <div class="row min-vh-100 flex-nowrap g-0">
        <?php require __DIR__ . '/partials/sidebar.php'; ?>
        <main class="col min-vh-100 p-0 overflow-x-hidden app-main">
            <?php require __DIR__ . '/partials/topbar.php'; ?>
            <div class="container-fluid app-content">
            <?php if ($flash): ?>
                <div class="alert alert-<?= h($flash['type'] === 'warning' ? 'warning' : 'info') ?> alert-dismissible fade show" id="serverFlash">
                    <?= h($flash['message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
                </div>
            <?php endif; ?>
            <?php
            $bookingPages = [
                'order'            => 'pages/booking/index.php',
                'tasks'            => 'pages/booking/index.php',
                'monitoring'       => 'pages/booking/index.php',
                'booking'          => 'pages/booking/index.php',
                'booking_detail'   => 'pages/booking/detail.php',
                'booking_schedule' => 'pages/booking/schedule.php',
                'booking_survey'   => 'pages/booking/survey.php',
                'booking_estimate' => 'pages/booking/estimate.php',
                'booking_work'     => 'pages/booking/work.php',
                'booking_invoice'  => 'pages/booking/invoice.php',
                'booking_payment'  => 'pages/booking/payment.php',
            ];
            if ($page === 'dashboard') {
                require __DIR__ . '/pages/' . dashboard_file_for_role($role);
            } elseif ($page === 'settings') {
                require __DIR__ . '/pages/settings_hub.php';
            } elseif ($page === 'profil') {
                require __DIR__ . '/pages/profil.php';
            } elseif (isset($bookingPages[$page])) {
                require __DIR__ . '/' . $bookingPages[$page];
            } else {
                require __DIR__ . '/pages/commerce_page.php';
            }
            ?>
            </div>
        </main>
    </div>
</div>
<?php
require __DIR__ . '/partials/modals.php';
require __DIR__ . '/partials/toast.php';
require __DIR__ . '/partials/footer.php';
