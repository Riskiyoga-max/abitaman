<?php
declare(strict_types=1);

function booking_filters_from_request(): array
{
    $role = current_role() ?? 'customer';
    $defaultLane = $role === 'boss' ? 'active' : 'action';
    $lane = trim((string) ($_GET['lane'] ?? $defaultLane));
    if (!in_array($lane, ['action', 'active', 'history'], true)) {
        $lane = $defaultLane;
    }

    return [
        'q'       => trim((string) ($_GET['q'] ?? '')),
        'status'  => trim((string) ($_GET['status'] ?? '')),
        'stage'   => trim((string) ($_GET['stage'] ?? '')),
        'service' => max(0, (int) ($_GET['service'] ?? 0)),
        'worker'  => max(0, (int) ($_GET['worker'] ?? 0)),
        'lane'    => $lane,
    ];
}

function booking_list_for_role(array $user, string $role, array $filters = [], array $extraWhere = [], array $extraParams = []): array
{
    $where  = [];
    $params = [];

    if ($role === 'customer') {
        $where[]  = 'b.customer_id = ?';
        $params[] = $user['id'];
    } elseif ($role === 'pekerja_lapangan') {
        $where[]  = 'b.assigned_pekerja_lapangan_id = ?';
        $params[] = $user['id'];
    }

    if ($filters['status'] !== '') {
        $where[]  = 'b.status = ?';
        $params[] = $filters['status'];
    }

    $stageStatuses = booking_stage_statuses((string) ($filters['stage'] ?? ''));
    if ($stageStatuses) {
        $where[] = 'b.status IN (' . implode(',', array_fill(0, count($stageStatuses), '?')) . ')';
        array_push($params, ...$stageStatuses);
    }

    if (!empty($filters['service'])) {
        $where[] = 'b.service_id = ?';
        $params[] = (int) $filters['service'];
    }

    if (!empty($filters['worker']) && in_array($role, ['staff', 'boss'], true)) {
        $where[] = 'b.assigned_pekerja_lapangan_id = ?';
        $params[] = (int) $filters['worker'];
    }

    $lane = (string) ($filters['lane'] ?? 'active');
    if ($lane === 'active') {
        $where[] = "b.status NOT IN ('Dibatalkan','Selesai')";
    } elseif ($lane === 'history') {
        $where[] = "b.status IN ('Dibatalkan','Selesai')";
    } elseif ($lane === 'action') {
        $where[] = match ($role) {
            'staff' => "(
                b.status IN ('Menunggu Konfirmasi','Lunas')
                OR e.status='Draft'
                OR er.status='Draft'
                OR fi.status='Draft'
                OR EXISTS (
                    SELECT 1 FROM payments pp
                    WHERE pp.booking_id=b.id
                      AND pp.payment_status='Belum Dibayar'
                      AND pp.payment_proof IS NOT NULL
                )
            )",
            'customer' => "(
                e.status='Dikirim'
                OR er.status='Dikirim'
                OR (fi.status='Dikirim' AND fi.payment_status='Belum Dibayar')
                OR (b.status='Selesai' AND rv.id IS NULL)
            )",
            'pekerja_lapangan' => "b.status IN (
                'Survey Dijadwalkan','Survey Berlangsung','Survey Selesai',
                'Estimasi Disetujui','Dalam Pengerjaan','Revisi Estimasi','Pekerjaan Selesai'
            )",
            default => "b.status NOT IN ('Dibatalkan','Selesai')",
        };
    }

    if ($filters['q'] !== '') {
        $where[]  = '(b.booking_code LIKE ? OR u.name LIKE ? OR b.location_address LIKE ?)';
        $params[] = '%' . $filters['q'] . '%';
        $params[] = '%' . $filters['q'] . '%';
        $params[] = '%' . $filters['q'] . '%';
    }

    foreach ($extraWhere as $clause) {
        $where[] = $clause;
    }
    foreach ($extraParams as $p) {
        $params[] = $p;
    }

    $sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $prioritySql = match ($role) {
        'staff' => "CASE
            WHEN EXISTS (SELECT 1 FROM payments px WHERE px.booking_id=b.id AND px.payment_status='Belum Dibayar' AND px.payment_proof IS NOT NULL) THEN 1
            WHEN b.status='Menunggu Konfirmasi' THEN 2
            WHEN e.status='Draft' OR er.status='Draft' OR fi.status='Draft' THEN 3
            WHEN b.status='Lunas' THEN 4
            ELSE 9 END",
        'customer' => "CASE
            WHEN e.status='Dikirim' OR er.status='Dikirim' THEN 1
            WHEN fi.status='Dikirim' AND fi.payment_status='Belum Dibayar' THEN 2
            WHEN b.status='Selesai' AND rv.id IS NULL THEN 3
            ELSE 9 END",
        'pekerja_lapangan' => "CASE
            WHEN b.status IN ('Survey Dijadwalkan','Survey Berlangsung') THEN 1
            WHEN b.status IN ('Survey Selesai','Estimasi Disetujui') THEN 2
            WHEN b.status IN ('Dalam Pengerjaan','Revisi Estimasi') THEN 3
            WHEN b.status='Pekerjaan Selesai' THEN 4
            ELSE 9 END",
        default => '9',
    };

    return paginate(
        "SELECT b.*, u.name AS customer_name, u.phone AS customer_phone,
                fw.name AS pekerja_lapangan_name,
                s.name AS service_name,
                e.id AS estimate_id, e.status AS estimate_status,
                er.id AS revision_id, er.status AS revision_status,
                fi.id AS invoice_id, fi.status AS invoice_status,
                fi.payment_status AS invoice_payment_status,
                rv.id AS review_id,
                EXISTS (
                    SELECT 1 FROM payments pproof
                    WHERE pproof.booking_id=b.id
                      AND pproof.payment_status='Belum Dibayar'
                      AND pproof.payment_proof IS NOT NULL
                ) AS has_payment_proof,
                {$prioritySql} AS action_priority
         FROM bookings b
         JOIN users u ON u.id = b.customer_id
         LEFT JOIN users fw ON fw.id = b.assigned_pekerja_lapangan_id
         LEFT JOIN services s ON s.id = b.service_id
         LEFT JOIN estimates e ON e.id=(SELECT ex.id FROM estimates ex WHERE ex.booking_id=b.id ORDER BY ex.id DESC LIMIT 1)
         LEFT JOIN estimate_revisions er ON er.id=(SELECT rx.id FROM estimate_revisions rx WHERE rx.booking_id=b.id ORDER BY rx.id DESC LIMIT 1)
         LEFT JOIN final_invoices fi ON fi.id=(SELECT ix.id FROM final_invoices ix WHERE ix.booking_id=b.id ORDER BY ix.id DESC LIMIT 1)
         LEFT JOIN reviews rv ON rv.booking_id=b.id
         {$sqlWhere}
         ORDER BY action_priority, b.updated_at DESC",
        $params
    );
}

function booking_related_records(int $bookingId): array
{
    $schedules = db_query(
        'SELECT sc.*, u.name AS pekerja_lapangan_name
         FROM schedules sc JOIN users u ON u.id = sc.pekerja_lapangan_id
         WHERE sc.booking_id = ? ORDER BY sc.schedule_date, sc.schedule_time',
        [$bookingId]
    );
    $survey = db_first(
        'SELECT sv.*, u.name AS pekerja_lapangan_name
         FROM surveys sv
         LEFT JOIN users u ON u.id = sv.pekerja_lapangan_id
         WHERE sv.booking_id = ? ORDER BY sv.id DESC LIMIT 1',
        [$bookingId]
    );
    $photos   = db_query('SELECT * FROM survey_photos WHERE booking_id = ? ORDER BY photo_type, created_at', [$bookingId]);
    $estimate = db_first(
        'SELECT e.* FROM estimates e WHERE e.booking_id = ? ORDER BY e.id DESC LIMIT 1',
        [$bookingId]
    );
    $estimateItems = $estimate
        ? db_query('SELECT * FROM estimate_items WHERE estimate_id = ? ORDER BY id', [$estimate['id']])
        : [];
    $revision = db_first(
        'SELECT * FROM estimate_revisions WHERE booking_id = ? ORDER BY id DESC LIMIT 1',
        [$bookingId]
    );
    $revisions = db_query(
        'SELECT r.*, u.name AS created_by_name
         FROM estimate_revisions r
         LEFT JOIN users u ON u.id=r.created_by
         WHERE r.booking_id=? ORDER BY r.id DESC',
        [$bookingId]
    );
    $revisionItems = $revision
        ? db_query('SELECT * FROM estimate_revision_items WHERE revision_id = ? ORDER BY id', [$revision['id']])
        : [];
    $progress = db_query(
        'SELECT wp.*, u.name AS worker_name FROM work_progress wp
         JOIN users u ON u.id = wp.pekerja_lapangan_id
         WHERE wp.booking_id = ? ORDER BY wp.created_at DESC',
        [$bookingId]
    );
    $invoice = db_first(
        'SELECT * FROM final_invoices WHERE booking_id = ? ORDER BY id DESC LIMIT 1',
        [$bookingId]
    );
    $invoiceItems = $invoice
        ? db_query('SELECT * FROM final_invoice_items WHERE final_invoice_id = ? ORDER BY id', [$invoice['id']])
        : [];
    $payments = db_query(
        'SELECT p.*, u.name AS marked_by_name FROM payments p
         LEFT JOIN users u ON u.id = p.marked_by
         WHERE p.booking_id = ? ORDER BY p.created_at DESC',
        [$bookingId]
    );

    return compact('schedules', 'survey', 'photos', 'estimate', 'estimateItems', 'revision', 'revisions', 'revisionItems', 'progress', 'invoice', 'invoiceItems', 'payments');
}

function latest_booking_estimate(int $bookingId): ?array
{
    return db_first(
        'SELECT e.* FROM estimates e WHERE e.booking_id = ? ORDER BY e.id DESC LIMIT 1',
        [$bookingId]
    ) ?: null;
}

function latest_booking_invoice(int $bookingId): ?array
{
    return db_first(
        'SELECT * FROM final_invoices WHERE booking_id = ? ORDER BY id DESC LIMIT 1',
        [$bookingId]
    ) ?: null;
}
