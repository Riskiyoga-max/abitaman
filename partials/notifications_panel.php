<?php $roleNotifications = $notifications ?? []; ?>
<section class="workspace-section">
    <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
        <div>
            <p class="small fw-bold text-uppercase text-secondary mb-1">Notifikasi <?= h($role) ?></p>
            <h3 class="h5 mb-0">Prioritas Hari Ini</h3>
        </div>
        <span class="status-label"><?= count($roleNotifications) ?> baru</span>
    </div>
    <div class="data-list">
        <?php foreach ($roleNotifications as $notice): ?>
            <a class="data-list__row data-list__row--compact" href="<?= h(notification_target_url($notice)) ?>">
                <div>
                    <strong><?= h($notice['title']) ?></strong>
                    <span><?= h($notice['message'] ?? 'Klik untuk membuka halaman terkait.') ?></span>
                </div>
                <i class="bi bi-arrow-right"></i>
            </a>
        <?php endforeach; ?>
        <?php if (!$roleNotifications): ?>
            <div class="empty-state"><p>Tidak ada notifikasi yang membutuhkan tindakan.</p></div>
        <?php endif; ?>
    </div>
</section>
