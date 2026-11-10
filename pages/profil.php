<?php
$profileData = db_first('SELECT id, name, email, role, phone, address, status, last_login_at FROM users WHERE id=?', [$user['id']]);
?>
<section class="page-intro">
    <div>
        <p class="eyebrow">Akun</p>
        <h1>Profil Saya</h1>
        <p>Perbarui informasi kontak dan keamanan akun Anda.</p>
    </div>
</section>

<section class="profile-layout">
    <aside class="profile-summary">
        <div class="profile-identity">
            <div class="profile-initial" aria-hidden="true"><?= h(strtoupper(substr($profileData['name'] ?: 'U', 0, 1))) ?></div>
            <div>
                <p class="eyebrow">Profil</p>
                <h2><?= h($profileData['name']) ?></h2>
                <span class="<?= status_badge_class($profileData['status']) ?>"><?= h($profileData['status']) ?></span>
            </div>
        </div>

        <dl class="profile-meta">
            <div><dt>Role</dt><dd><?= h(role_label($profileData['role'])) ?></dd></div>
            <div><dt>Email</dt><dd><?= h($profileData['email']) ?></dd></div>
            <div><dt>Nomor HP</dt><dd><?= h($profileData['phone'] ?: '-') ?></dd></div>
            <div><dt>Alamat</dt><dd><?= h($profileData['address'] ?: '-') ?></dd></div>
            <div><dt>Login terakhir</dt><dd><?= h(format_datetime($profileData['last_login_at'])) ?></dd></div>
        </dl>
    </aside>

    <div class="profile-forms">
        <section class="profile-form-section">
            <div class="section-heading">
                <div><p class="eyebrow">Informasi akun</p><h2>Kelola profil</h2></div>
            </div>
            <form data-api-form class="row g-3" method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_profile">
                <label class="col-md-6">Nama<input class="form-control" name="name" value="<?= h($profileData['name']) ?>" required></label>
                <label class="col-md-6">Email<input class="form-control" type="email" name="email" value="<?= h($profileData['email']) ?>" required></label>
                <label class="col-md-6">Nomor HP<input class="form-control" name="phone" value="<?= h($profileData['phone']) ?>" placeholder="Nomor HP"></label>
                <label class="col-12">Alamat<textarea class="form-control" name="address" rows="3" placeholder="Alamat"><?= h($profileData['address']) ?></textarea></label>
                <div class="col-12 d-flex flex-wrap gap-2"><button class="btn btn-primary" type="submit"><i class="bi bi-check2-circle me-1"></i>Simpan Profil</button></div>
            </form>
        </section>

        <section class="profile-form-section">
            <div class="section-heading">
                <div><p class="eyebrow">Keamanan</p><h2>Ubah password</h2></div>
            </div>
            <form data-api-form class="row g-3" method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="change_password">
                <label class="col-md-6">Password Lama<input class="form-control" type="password" name="current_password" placeholder="Password lama" autocomplete="current-password" required></label>
                <label class="col-md-6">Password Baru<input class="form-control" type="password" name="new_password" placeholder="Password baru" autocomplete="new-password" required></label>
                <label class="col-md-6">Konfirmasi Password<input class="form-control" type="password" name="password_confirm" placeholder="Konfirmasi password baru" autocomplete="new-password" required></label>
                <div class="col-12 d-flex flex-wrap gap-2"><button class="btn btn-outline-secondary" type="submit"><i class="bi bi-shield-lock me-1"></i>Ubah Password</button></div>
            </form>
        </section>
    </div>
</section>
