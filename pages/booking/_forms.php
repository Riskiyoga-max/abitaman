<?php
declare(strict_types=1);

function render_schedule_survey_form(array $booking, array $pekerjaLapanganList): void
{
    $isNew = $booking['status'] === 'Menunggu Konfirmasi';
    ?>
    <form data-api-form class="d-flex flex-column gap-3" method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="schedule_survey">
        <input type="hidden" name="booking_id" value="<?= (int) $booking['id'] ?>">
        <label class="form-label">
            <?= $isNew ? 'Terima booking, assign pekerja lapangan, dan jadwalkan survey' : 'Reschedule survey atau ganti pekerja lapangan' ?>
        </label>
        <select class="form-select" name="pekerja_lapangan_id" required>
            <option value="">Pilih pekerja lapangan</option>
            <?php foreach ($pekerjaLapanganList as $worker): ?>
                <option value="<?= (int) $worker['id'] ?>"
                    <?= (int) ($booking['assigned_pekerja_lapangan_id'] ?? 0) === (int) $worker['id'] ? 'selected' : '' ?>>
                    <?= h($worker['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <div class="row g-2">
            <div class="col-7">
                <input class="form-control" type="date" name="schedule_date"
                       value="<?= h($booking['actual_survey_date'] ?: date('Y-m-d')) ?>" required>
            </div>
            <div class="col-5">
                <input class="form-control" type="time" name="schedule_time"
                       value="<?= h($booking['actual_survey_time'] ? substr($booking['actual_survey_time'], 0, 5) : '09:00') ?>" required>
            </div>
        </div>
        <textarea class="form-control" name="notes" rows="2"
                  placeholder="Catatan survey atau alasan reschedule"></textarea>
        <button class="btn btn-primary btn-sm" type="submit">
            <?= $isNew ? 'Terima &amp; Jadwalkan Survey' : 'Simpan Jadwal Survey' ?>
        </button>
    </form>
    <?php
}

function render_reject_booking_form(array $booking): void
{
    ?>
    <form data-api-form class="d-flex flex-column gap-3" method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="reject_booking">
        <input type="hidden" name="booking_id" value="<?= (int) $booking['id'] ?>">
        <input class="form-control" name="reason" placeholder="Alasan penolakan">
        <button class="btn btn-outline-danger btn-sm" type="submit">Tolak Booking</button>
    </form>
    <?php
}

function render_start_survey_form(array $booking): void
{
    ?>
    <form class="d-flex flex-wrap align-items-center gap-2" data-api-form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="start_survey">
        <input type="hidden" name="booking_id" value="<?= (int) $booking['id'] ?>">
        <button class="btn btn-primary btn-sm" type="submit">
            <i class="bi bi-play-fill"></i> Mulai Survey
        </button>
    </form>
    <?php
}

function render_complete_survey_form(array $booking): void
{
    ?>
    <form data-api-form class="d-flex flex-column gap-3" method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="complete_survey">
        <input type="hidden" name="booking_id" value="<?= (int) $booking['id'] ?>">
        <label class="form-label">Hasil survey</label>
        <input class="form-control" type="number" min="1" step="0.1" name="actual_area"
               placeholder="Luas aktual m²" required>
        <select class="form-select" name="location_condition" required>
            <option value="">Pilih kondisi lokasi</option>
            <option>Lahan kosong siap dikerjakan</option>
            <option>Sudah ada taman, perlu renovasi</option>
            <option>Perlu pembersihan dan pembongkaran</option>
            <option>Permukaan miring atau tidak rata</option>
            <option>Area sempit / taman vertikal</option>
            <option>Kondisi lain — jelaskan di catatan</option>
        </select>
        <select class="form-select" name="access_condition" required>
            <option value="">Pilih kondisi akses</option>
            <option>Mudah, kendaraan dapat mendekat</option>
            <option>Sedang, perlu angkut manual</option>
            <option>Sulit, gang sempit atau bertingkat</option>
            <option>Akses lain — jelaskan di catatan</option>
        </select>
        <textarea class="form-control" name="obstacle_notes" rows="2"
                  placeholder="Catatan kendala"></textarea>
        <div class="row g-2">
            <div class="col-6">
                <input class="form-control" type="number" min="1" name="estimated_work_days"
                       placeholder="Hari kerja" required>
            </div>
            <div class="col-6">
                <input class="form-control" type="number" min="1" name="estimated_worker_count"
                       placeholder="Jumlah tukang" required>
            </div>
        </div>
        <textarea class="form-control" name="survey_notes" rows="2"
                  placeholder="Catatan survey"></textarea>
        <label class="form-label">Upload foto before (1–5 foto)</label>
        <input class="form-control" type="file" name="before_photos[]"
               accept=".jpg,.jpeg,.png,.webp" multiple required data-max-files="5">
        <button class="btn btn-primary btn-sm" type="submit">Selesaikan Survey</button>
    </form>
    <?php
}

function render_create_estimate_form(array $booking, ?array $service = null): void
{
    $baseDurationPer10m = (int) ($service['base_duration_days_per_10m'] ?? 1);
    $materials = db_query("SELECT * FROM materials WHERE status='active' ORDER BY name");
    $laborRates = db_query("SELECT * FROM labor_rates WHERE status='active' ORDER BY daily_rate, name");
    $additionalCosts = db_query("SELECT * FROM additional_cost_types WHERE status='active' ORDER BY name");
    $area = (float) ($booking['actual_area'] ?? $booking['estimated_area'] ?? 0);
    $blockCount = $area > 0 ? calculate_block_count($area) : 1;
    ?>
    <form data-api-form data-estimate-form class="d-flex flex-column gap-4" method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create_estimate">
        <input type="hidden" name="booking_id" value="<?= (int) $booking['id'] ?>">

        <?php if ($service): ?>
            <div class="alert alert-success mb-1">
                <p class="mb-0 fw-semibold small text-success">
                    <i class="bi bi-flower1 me-1"></i><?= h($service['name']) ?>
                </p>
                <p class="mb-0 text-muted small mt-1">
                    Pilih komponen yang benar-benar dipakai. Estimasi akan disimpan sebagai draft untuk direview Staff.
                </p>
            </div>
        <?php endif; ?>

        <div class="row g-2">
            <div class="col-4">
                <label class="form-label small fw-semibold">Luas (m²)</label>
                <input class="form-control" type="number" min="1" step="0.1" name="area_size"
                       value="<?= h($area) ?>" required data-estimate-area>
            </div>
            <div class="col-4">
                <label class="form-label small fw-semibold">Tukang</label>
                <input class="form-control" type="number" min="1" name="worker_count" value="2" required>
            </div>
            <div class="col-4">
                <label class="form-label small fw-semibold">Hari kerja</label>
                <input class="form-control" type="number" min="1" name="estimated_days"
                       value="<?= $baseDurationPer10m ?>" required>
            </div>
        </div>

        <div>
            <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                <div><label class="form-label small fw-semibold mb-0">Material</label><small class="d-block text-muted">Qty awal sudah disesuaikan untuk <?= $blockCount ?> blok area.</small></div>
                <button class="btn btn-outline-secondary btn-sm" type="button" data-estimate-select-all>Pilih semua</button>
            </div>
            <div class="border rounded-3 overflow-hidden">
                <?php foreach ($materials as $material): ?>
                    <?php $materialQty = (float) $material['default_qty_per_10m'] * $blockCount; ?>
                    <div class="row g-2 align-items-center p-2 border-bottom" data-estimate-row>
                        <div class="col-12 col-md">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="material_id[]" value="<?= (int) $material['id'] ?>" id="material-<?= (int) $material['id'] ?>" data-estimate-toggle>
                                <label class="form-check-label fw-semibold" for="material-<?= (int) $material['id'] ?>"><?= h($material['name']) ?></label>
                                <small class="d-block text-muted"><?= h($material['unit']) ?> · default <?= h($material['default_qty_per_10m']) ?>/10 m²</small>
                            </div>
                        </div>
                        <div class="col-5 col-md-2"><input class="form-control form-control-sm" type="number" min="0" step="0.01" name="material_qty[<?= (int) $material['id'] ?>]" value="<?= h($materialQty) ?>" aria-label="Qty <?= h($material['name']) ?>" data-estimate-qty disabled></div>
                        <div class="col-7 col-md-3"><input class="form-control form-control-sm" type="number" min="0" name="material_price[<?= (int) $material['id'] ?>]" value="<?= (int) $material['price_per_unit'] ?>" aria-label="Harga <?= h($material['name']) ?>" data-estimate-price disabled></div>
                    </div>
                <?php endforeach; ?>
                <?php if (!$materials): ?><p class="text-danger small p-3 mb-0">Master material aktif belum tersedia.</p><?php endif; ?>
            </div>
        </div>

        <div>
            <label class="form-label small fw-semibold">Tarif Tenaga Kerja</label>
            <select class="form-select" name="labor_rate_id" required data-labor-rate>
                <option value="">Pilih tarif pekerja</option>
                <?php foreach ($laborRates as $labor): ?>
                    <option value="<?= (int) $labor['id'] ?>" data-rate="<?= (int) $labor['daily_rate'] ?>"><?= h($labor['name']) ?> · <?= format_rupiah($labor['daily_rate']) ?>/hari</option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="form-label small fw-semibold">Biaya Tambahan <span class="text-muted fw-normal">(opsional)</span></label>
            <div class="border rounded-3 overflow-hidden">
                <?php foreach ($additionalCosts as $cost): ?>
                    <div class="row g-2 align-items-center p-2 border-bottom" data-estimate-row>
                        <div class="col-12 col-md">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="additional_cost_id[]" value="<?= (int) $cost['id'] ?>" id="cost-<?= (int) $cost['id'] ?>" data-estimate-toggle>
                                <label class="form-check-label fw-semibold" for="cost-<?= (int) $cost['id'] ?>"><?= h($cost['name']) ?></label>
                                <small class="d-block text-muted"><?= h($cost['unit']) ?></small>
                            </div>
                        </div>
                        <div class="col-5 col-md-2"><input class="form-control form-control-sm" type="number" min="0" step="0.01" name="additional_qty[<?= (int) $cost['id'] ?>]" value="1" data-estimate-qty disabled></div>
                        <div class="col-7 col-md-3"><input class="form-control form-control-sm" type="number" min="0" name="additional_price[<?= (int) $cost['id'] ?>]" value="<?= (int) $cost['default_price'] ?>" data-estimate-price disabled></div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="row g-2 mt-2">
                <div class="col-md-5"><input class="form-control" name="custom_additional_name" placeholder="Biaya lain (opsional)"></div>
                <div class="col-3 col-md-2"><input class="form-control" type="number" min="0" step="0.01" name="custom_additional_qty" value="1" aria-label="Qty biaya lain"></div>
                <div class="col-4 col-md-2"><input class="form-control" name="custom_additional_unit" value="item" aria-label="Satuan biaya lain"></div>
                <div class="col-5 col-md-3"><input class="form-control" type="number" min="0" name="custom_additional_price" value="0" aria-label="Harga biaya lain"></div>
            </div>
        </div>

        <div class="rounded-3 bg-success-subtle p-3 d-flex justify-content-between align-items-center">
            <div><small class="d-block text-success-emphasis">Perkiraan total</small><span class="text-muted small">Dihitung ulang dan divalidasi oleh server.</span></div>
            <strong class="fs-5 text-success-emphasis" data-estimate-total>Rp 0</strong>
        </div>

        <?php if (!$materials || !$laborRates): ?>
            <div class="alert alert-warning mb-0">Material dan tarif tenaga kerja aktif wajib tersedia sebelum estimasi dibuat.</div>
        <?php else: ?>
            <button class="btn btn-primary btn-sm" type="submit">
                <i class="bi bi-calculator me-1"></i>Simpan Draft Estimasi
            </button>
        <?php endif; ?>
    </form>
    <?php
}

function render_start_work_form(array $booking): void
{
    ?>
    <form class="d-flex flex-wrap align-items-center gap-2" data-api-form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="start_work">
        <input type="hidden" name="booking_id" value="<?= (int) $booking['id'] ?>">
        <button class="btn btn-primary btn-sm" type="submit">
            <i class="bi bi-play-fill"></i> Mulai Pekerjaan
        </button>
    </form>
    <?php
}

function render_progress_update_form(array $booking): void
{
    ?>
    <form data-api-form class="d-flex flex-column gap-3" method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update_progress">
        <input type="hidden" name="booking_id" value="<?= (int) $booking['id'] ?>">
        <label class="form-label">Update progress</label>
        <input class="form-control" type="number" min="0" max="100" name="progress_percent"
               placeholder="Persen progress" required>
        <input class="form-control" name="title" placeholder="Judul update" required>
        <textarea class="form-control" name="note" rows="2"
                  placeholder="Catatan pekerjaan"></textarea>
        <textarea class="form-control" name="obstacle_note" rows="2"
                  placeholder="Kendala lapangan"></textarea>
        <label class="form-label">Foto progress opsional (maks. 5 foto)</label>
        <input class="form-control" type="file" name="progress_photos[]"
               accept=".jpg,.jpeg,.png,.webp" multiple data-max-files="5">
        <button class="btn btn-primary btn-sm" type="submit">Simpan Progress</button>
    </form>
    <?php
}

function render_revision_form(array $booking): void
{
    ?>
    <form data-api-form class="d-flex flex-column gap-3" method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create_revision">
        <input type="hidden" name="booking_id" value="<?= (int) $booking['id'] ?>">
        <label class="form-label">Revisi estimasi</label>
        <textarea class="form-control" name="reason" rows="2"
                  placeholder="Alasan revisi"></textarea>
        <div class="row g-2">
            <input class="form-control" name="item_name[]" placeholder="Item revisi">
            <input class="form-control" name="description[]" placeholder="Deskripsi">
            <input class="form-control" type="number" name="quantity[]" value="1">
            <input class="form-control" name="unit[]" value="item">
            <input class="form-control" type="number" name="unit_price[]" value="0">
        </div>
        <button class="btn btn-outline-secondary btn-sm" type="submit">Buat Revisi</button>
    </form>
    <?php
}

function render_mark_work_done_form(array $booking): void
{
    ?>
    <form class="d-flex flex-wrap align-items-center gap-2" data-api-form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="mark_work_done">
        <input type="hidden" name="booking_id" value="<?= (int) $booking['id'] ?>">
        <button class="btn btn-outline-secondary btn-sm" type="submit">
            <i class="bi bi-check2-square"></i> Tandai Pekerjaan Selesai
        </button>
    </form>
    <?php
}

function render_create_invoice_form(array $booking): void
{
    ?>
    <form data-api-form class="d-flex flex-column gap-3" method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create_invoice">
        <input type="hidden" name="booking_id" value="<?= (int) $booking['id'] ?>">
        <label class="form-label">Data final pekerjaan</label>
        <div class="row g-2">
            <div class="col-6">
                <input class="form-control" type="number" min="1" name="work_days_actual"
                       placeholder="Hari aktual" required>
            </div>
            <div class="col-6">
                <input class="form-control" type="number" min="1" name="worker_count_actual"
                       placeholder="Tukang aktual" required>
            </div>
        </div>
        <textarea class="form-control" name="notes" rows="2"
                  placeholder="Catatan data final"></textarea>
        <label class="form-label">Upload foto after (1–5 foto)</label>
        <input class="form-control" type="file" name="after_photos[]"
               accept=".jpg,.jpeg,.png,.webp" multiple required data-max-files="5">
        <button class="btn btn-primary btn-sm" type="submit">Kirim Data Final</button>
    </form>
    <?php
}

function render_upload_payment_proof_form(int $invoiceId): void
{
    ?>
    <form data-api-form class="d-flex flex-column gap-3" method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="upload_payment_proof">
        <input type="hidden" name="invoice_id" value="<?= $invoiceId ?>">
        <div>
            <label class="form-label">Foto / screenshot bukti transfer <span class="text-danger">*</span></label>
            <input class="form-control" type="file" name="payment_proof"
                   accept=".jpg,.jpeg,.png,.webp" required>
        </div>
        <div>
            <label class="form-label">Catatan (opsional)</label>
            <input class="form-control" type="text" name="note"
                   placeholder="Misal: transfer via BCA a.n. Budi">
        </div>
        <button class="btn btn-primary" type="submit">
            <i class="bi bi-upload me-1"></i>Kirim Bukti Bayar
        </button>
    </form>
    <?php
}

function render_mark_invoice_paid_form(array $invoice): void
{
    ?>
    <form class="d-flex flex-wrap align-items-center gap-2" data-api-form method="post"
          data-confirm="Tandai invoice <?= h($invoice['invoice_code']) ?> sebagai lunas?">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="mark_invoice_paid">
        <input type="hidden" name="confirm_action" value="1">
        <input type="hidden" name="invoice_id" value="<?= (int) $invoice['id'] ?>">
        <button class="btn btn-success btn-sm" type="submit">
            <i class="bi bi-cash-coin"></i> Tandai Lunas
        </button>
    </form>
    <?php
}

function render_mark_survey_fee_paid_form(array $booking): void
{
    ?>
    <form class="d-flex flex-wrap align-items-center gap-2" data-api-form method="post"
          data-confirm="Tandai survey fee <?= h($booking['booking_code']) ?> sebagai lunas?">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="mark_survey_fee_paid">
        <input type="hidden" name="confirm_action" value="1">
        <input type="hidden" name="booking_id" value="<?= (int) $booking['id'] ?>">
        <button class="btn btn-success btn-sm" type="submit">
            <i class="bi bi-cash"></i> Tandai Survey Fee Lunas
        </button>
    </form>
    <?php
}
