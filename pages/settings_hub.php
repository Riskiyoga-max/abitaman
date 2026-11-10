<?php
declare(strict_types=1);

require_roles(['staff']);

$settingsTabs = [
    'general' => ['Umum', 'bi-sliders', 'settings', 'Dasar'],
    'catalog' => ['Katalog', 'bi-flower1', 'catalog', 'Data master'],
    'materials' => ['Material', 'bi-basket', 'materials', 'Data master'],
    'labor' => ['Harga Tukang', 'bi-people', 'labor_rates', 'Data master'],
    'costs' => ['Biaya Tambahan', 'bi-plus-square-dotted', 'additional_costs', 'Data master'],
    'users' => ['Pengguna', 'bi-person-gear', 'users', 'Sistem'],
    'activity' => ['Activity Log', 'bi-activity', 'activity_logs', 'Sistem'],
];
$settingsTab = trim((string) ($_GET['tab'] ?? 'general'));
if (!isset($settingsTabs[$settingsTab])) {
    $settingsTab = 'general';
}
?>

<header class="settings-hero">
    <div>
        <p class="eyebrow">Administrasi</p>
        <h1>Pengaturan</h1>
        <p>Kelola identitas bisnis, referensi harga, katalog, pengguna, dan jejak aktivitas dari satu workspace.</p>
    </div>
    <span class="status-label">Tersimpan terpusat</span>
</header>

<div class="settings-shell">
    <nav class="settings-nav" aria-label="Bagian pengaturan">
        <?php foreach (['Dasar', 'Data master', 'Sistem'] as $group): ?>
            <div class="settings-nav__group">
                <p class="settings-nav__label"><?= h($group) ?></p>
                <?php foreach ($settingsTabs as $key => [$label, $icon, $targetPage, $itemGroup]): ?>
                    <?php if ($itemGroup !== $group) continue; ?>
                    <a class="settings-nav__link <?= $settingsTab === $key ? 'active' : '' ?>" href="dashboard.php?page=settings&tab=<?= h($key) ?>">
                        <i class="bi <?= h($icon) ?>" aria-hidden="true"></i>
                        <span><?= h($label) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </nav>

    <section class="settings-content">
        <?php
        $settingsHubContext = true;
        $page = $settingsTabs[$settingsTab][2];
        require __DIR__ . '/commerce_page.php';
        ?>
    </section>
</div>
