<?php
require_once __DIR__ . '/data/session_helper.php';

if (current_user()) {
    header('Location: dashboard.php');
    exit;
}

$mode = ($_GET['mode'] ?? '') === 'register' ? 'register' : 'login';
$return = safe_redirect_path($_GET['return'] ?? ($_POST['return'] ?? ''), 'dashboard.php');
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $error = 'Sesi form tidak valid. Muat ulang halaman lalu coba lagi.';
    } elseif (($_POST['mode'] ?? 'login') === 'register') {
        $name = trim($_POST['name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['password_confirm'] ?? '';

        if ($name === '' || $email === '' || $phone === '' || $address === '' || $password === '') {
            $error = 'Nama, email, nomor HP, alamat, dan password wajib diisi.';
            $mode = 'register';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Format email tidak valid.';
            $mode = 'register';
        } elseif (strlen($password) < 6) {
            $error = 'Password minimal 6 karakter.';
            $mode = 'register';
        } elseif ($password !== $confirm) {
            $error = 'Konfirmasi password tidak cocok.';
            $mode = 'register';
        } elseif (db_first('SELECT id FROM users WHERE email = ?', [$email])) {
            $error = 'Email sudah terdaftar.';
            $mode = 'register';
        } else {
            $id = db_insert(
                'INSERT INTO users (name, email, password, role, phone, address, status) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$name, $email, password_hash($password, PASSWORD_BCRYPT), 'customer', $phone, $address, 'active']
            );
            session_regenerate_id(true);
            $_SESSION['abi_user'] = normalize_user([
                'id' => (int) $id, 'name' => $name, 'email' => $email,
                'role' => 'customer', 'phone' => $phone, 'address' => $address, 'status' => 'active',
            ]);
            log_activity('Register', "{$name} mendaftar sebagai customer.", 'user', (int) $id);
            header('Location: ' . $return);
            exit;
        }
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        if ($email === '' || $password === '') {
            $error = 'Email dan password wajib diisi.';
        } else {
            $user = find_user_by_credentials($email, $password);
            if ($user) {
                session_regenerate_id(true);
                $_SESSION['abi_user'] = $user;
                log_activity('Login', $user['name'] . ' berhasil login ke sistem.', 'user', (int) $user['id']);
                header('Location: ' . $return);
                exit;
            }
            $error = 'Email atau password salah, atau akun tidak aktif.';
        }
    }
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $mode === 'register' ? 'Daftar Akun' : 'Masuk' ?> | ABI Taman</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/app.css?v=<?= filemtime(__DIR__ . '/assets/css/app.css') ?>" rel="stylesheet">
    <link rel="icon" type="image/png" href="assets/img/abi-taman.png">
</head>
<body class="bg-body-tertiary min-vh-100">
    <div class="container d-flex align-items-center justify-content-center min-vh-100 py-5">
        <div class="col-12 col-md-8 col-lg-5">
            <!-- Brand -->
            <div class="text-center mb-4">
                <img src="assets/img/abi-taman.png" alt="ABI Taman" width="52" height="52" class="rounded mb-3">
                <h1 class="h4 fw-bold mb-1">ABI TAMAN</h1>
                <p class="small text-secondary mb-0">Jasa Pembuatan &amp; Perawatan Taman Profesional</p>
            </div>

            <!-- Card -->
            <div class="card border-0 shadow-sm overflow-hidden">
                <!-- Tabs -->
                <div class="card-header bg-white border-0 px-4 pt-4 pb-0">
                    <ul class="nav nav-tabs">
                        <li class="nav-item">
                            <a class="nav-link <?= $mode === 'login' ? 'active' : '' ?>"
                               href="login.php?return=<?= h(urlencode($return)) ?>">
                                <i class="bi bi-box-arrow-in-right me-1"></i> Masuk
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $mode === 'register' ? 'active' : '' ?>"
                               href="login.php?mode=register&return=<?= h(urlencode($return)) ?>">
                                <i class="bi bi-person-plus me-1"></i> Daftar
                            </a>
                        </li>
                    </ul>
                </div>

                <div class="card-body px-4 py-4">
                    <?php if ($error): ?>
                        <div class="alert alert-danger d-flex align-items-center gap-2 py-2 mb-3 small">
                            <i class="bi bi-exclamation-circle-fill flex-shrink-0"></i>
                            <span><?= h($error) ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($mode === 'register'): ?>
                        <form method="post" class="d-flex flex-column gap-3">
                            <?= csrf_field() ?>
                            <input type="hidden" name="mode" value="register">
                            <input type="hidden" name="return" value="<?= h($return) ?>">
                            <div>
                                <label class="form-label">Nama Lengkap</label>
                                <input class="form-control" type="text" name="name" value="<?= h($_POST['name'] ?? '') ?>" placeholder="Nama lengkap Anda" required>
                            </div>
                            <div>
                                <label class="form-label">Email</label>
                                <input class="form-control" type="email" name="email" value="<?= h($_POST['email'] ?? '') ?>" placeholder="email@contoh.com" autocomplete="email" required>
                            </div>
                            <div class="row g-3">
                                <div class="col-6">
                                    <label class="form-label">Nomor HP</label>
                                    <input class="form-control" type="text" name="phone" value="<?= h($_POST['phone'] ?? '') ?>" placeholder="08xxxxxxxxxx" required>
                                </div>
                                <div class="col-6">
                                    <label class="form-label">Alamat</label>
                                    <input class="form-control" type="text" name="address" value="<?= h($_POST['address'] ?? '') ?>" placeholder="Kota / Alamat" required>
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-6">
                                    <label class="form-label">Password</label>
                                    <input class="form-control" type="password" name="password" placeholder="Min. 6 karakter" autocomplete="new-password" required>
                                </div>
                                <div class="col-6">
                                    <label class="form-label">Konfirmasi</label>
                                    <input class="form-control" type="password" name="password_confirm" placeholder="Ulangi password" autocomplete="new-password" required>
                                </div>
                            </div>
                            <button class="btn btn-success fw-bold w-100 mt-1" type="submit">
                                <i class="bi bi-person-check me-1"></i> Buat Akun
                            </button>
                        </form>
                    <?php else: ?>
                        <form method="post" class="d-flex flex-column gap-3">
                            <?= csrf_field() ?>
                            <input type="hidden" name="mode" value="login">
                            <input type="hidden" name="return" value="<?= h($return) ?>">
                            <div>
                                <label class="form-label">Email</label>
                                <input class="form-control" type="email" name="email" placeholder="email@contoh.com" value="<?= h($_POST['email'] ?? '') ?>" autocomplete="email" required autofocus>
                            </div>
                            <div>
                                <label class="form-label">Password</label>
                                <input class="form-control" type="password" name="password" placeholder="••••••••" autocomplete="current-password" required>
                            </div>
                            <button class="btn btn-success fw-bold w-100 mt-1" type="submit">
                                <i class="bi bi-box-arrow-in-right me-1"></i> Masuk ke Dashboard
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <div class="text-center small mt-3">
                <a class="link-success fw-semibold text-decoration-none" href="index.php"><i class="bi bi-arrow-left me-1"></i>Kembali ke beranda</a>
            </div>
        </div>
    </div>

    <script src="assets/js/app.js?v=<?= filemtime(__DIR__ . '/assets/js/app.js') ?>"></script>
</body>
</html>
