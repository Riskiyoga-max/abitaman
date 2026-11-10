<?php
/**
 * partials/activity_log_panel.php
 * Panel aktivitas sistem.
 */
$activityPanelTitle = $activityPanelTitle ?? 'Aktivitas Terbaru';
$activityPanelLimit = $activityPanelLimit ?? 6;
$activityLogs = db_query(
    'SELECT l.*, u.name AS user_name FROM activity_logs l LEFT JOIN users u ON u.id=l.user_id ORDER BY l.created_at DESC LIMIT ' . (int) $activityPanelLimit
);
?>
<section class="workspace-section">
    <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
        <div>
            <p class="small fw-bold text-uppercase text-secondary mb-1">Activity log</p>
            <h3 class="h5 mb-0"><?= h($activityPanelTitle) ?></h3>
        </div>
        <span class="status-label">Aktif</span>
    </div>
    <div class="data-list" id="activityLogList">
        <?php foreach ($activityLogs as $log): ?>
            <article class="data-list__row data-list__row--compact">
                <div>
                    <span class="small text-secondary"><?= h(date('d M Y H:i', strtotime($log['created_at']))) ?></span>
                    <strong><?= h($log['user_name'] ?? 'System') ?></strong>
                    <p><?= h($log['description']) ?></p>
                </div>
                <span class="<?= status_badge_class($log['activity_type']) ?>"><?= h($log['activity_type']) ?></span>
            </article>
        <?php endforeach; ?>
        <?php if (empty($activityLogs)): ?>
            <div class="empty-state"><p>Belum ada aktivitas tercatat.</p></div>
        <?php endif; ?>
    </div>
</section>
