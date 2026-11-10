<?php
require_once __DIR__ . '/data/session_helper.php';

$current = current_user();
$services = get_services_with_min_price(true);
$categories = db_query("SELECT * FROM service_categories WHERE status='active' ORDER BY name");
$totalBooking = (int) (db_first("SELECT COUNT(*) AS c FROM bookings WHERE status='Selesai'")['c'] ?? 0);
$totalCustomer = (int) (db_first("SELECT COUNT(*) AS c FROM users WHERE role='customer'")['c'] ?? 0);
$totalLayanan = (int) (db_first("SELECT COUNT(*) AS c FROM services WHERE status='active'")['c'] ?? 0);
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ABI Taman | Jasa Pembuatan &amp; Perawatan Taman</title>
    <meta name="description" content="ABI Taman membantu pembuatan, renovasi, dan perawatan taman melalui proses booking, survey, estimasi, pengerjaan, dan pembayaran yang mudah dipantau.">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/app.css?v=<?= filemtime(__DIR__ . '/assets/css/app.css') ?>" rel="stylesheet">
    <link rel="icon" type="image/png" href="assets/img/abi-taman.png">
</head>
<body class="landing">
    <nav class="navbar navbar-expand-lg bg-white border-bottom sticky-top">
        <div class="container">
            <a class="navbar-brand landing-brand" href="index.php">
                <img src="assets/img/abi-taman.png" alt="Logo ABI Taman">
                <span>ABI TAMAN</span>
            </a>
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#landingNav" aria-controls="landingNav" aria-expanded="false" aria-label="Buka navigasi">
                <i class="bi bi-list fs-4"></i>
            </button>
            <div class="collapse navbar-collapse" id="landingNav">
                <ul class="navbar-nav mx-auto">
                    <li class="nav-item"><a class="nav-link" href="#layanan">Layanan</a></li>
                    <li class="nav-item"><a class="nav-link" href="#proses">Cara kerja</a></li>
                    <li class="nav-item"><a class="nav-link" href="#informasi">Informasi</a></li>
                    <li class="nav-item"><a class="nav-link" href="#faq">Pertanyaan umum</a></li>
                </ul>
                <div class="d-flex gap-2 mt-3 mt-lg-0">
                    <a class="btn btn-outline-secondary btn-sm" href="<?= $current ? 'dashboard.php' : 'login.php' ?>">
                        <?= $current ? 'Dashboard' : 'Masuk' ?>
                    </a>
                    <a class="btn btn-success btn-sm" href="catalog.php">Lihat layanan</a>
                </div>
            </div>
        </div>
    </nav>

    <main>
        <section class="landing-hero">
            <div class="container">
                <div class="row align-items-center g-5">
                    <div class="col-lg-6">
                        <p class="landing-kicker">Jasa taman untuk hunian dan area usaha</p>
                        <h1>Taman yang direncanakan dengan jelas.</h1>
                        <p class="lead">Pilih layanan, tentukan jadwal survey, tinjau estimasi, dan pantau pekerjaan dari satu tempat. Setiap biaya disusun dari kebutuhan aktual di lokasi.</p>
                        <div class="d-flex gap-2 flex-wrap mt-4">
                            <a class="btn btn-success btn-lg" href="catalog.php">Jelajahi layanan</a>
                            <a class="btn btn-outline-secondary btn-lg" href="booking_create.php?custom=1">Ajukan kebutuhan custom</a>
                        </div>
                        <div class="landing-proof" aria-label="Ringkasan ABI Taman">
                            <div>
                                <strong><?= $totalLayanan ?></strong>
                                <span>Layanan aktif</span>
                            </div>
                            <div>
                                <strong><?= $totalBooking ?></strong>
                                <span>Pekerjaan selesai</span>
                            </div>
                            <div>
                                <strong><?= $totalCustomer ?></strong>
                                <span>Customer terdaftar</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <img class="landing-hero__image" src="assets/img/hero.png" alt="Taman hijau dengan jalur batu dan pepohonan yang tertata">
                    </div>
                </div>
            </div>
        </section>

        <section class="landing-section" id="layanan">
            <div class="container">
                <div class="d-lg-flex align-items-end justify-content-between gap-4">
                    <div class="landing-section__intro mb-lg-5">
                        <p class="landing-kicker">Layanan ABI Taman</p>
                        <h2>Dari persiapan lahan sampai perawatan.</h2>
                        <p>Pilih layanan yang paling dekat dengan kebutuhan Anda. Jika ruang atau pekerjaan bersifat khusus, gunakan booking custom dan jelaskan kondisi lokasi melalui catatan serta foto.</p>
                    </div>
                    <?php if ($categories): ?>
                        <div class="d-flex flex-wrap gap-2 mb-5">
                            <?php foreach ($categories as $category): ?>
                                <a class="btn btn-outline-secondary btn-sm" href="catalog.php?category=<?= h($category['slug']) ?>"><?= h($category['name']) ?></a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($services): ?>
                    <div class="landing-services">
                        <?php foreach (array_slice($services, 0, 6) as $service): ?>
                            <?php
                            $serviceImage = trim((string) ($service['image'] ?? ''));
                            $serviceImagePath = $serviceImage !== '' ? __DIR__ . '/' . ltrim($serviceImage, '/') : '';
                            $isServicePlaceholder = false;
                            if ($serviceImagePath === '' || !is_file($serviceImagePath) || @getimagesize($serviceImagePath) === false) {
                                $isServicePlaceholder = true;
                                $serviceImage = $service['slug'] === 'taman-minimalis'
                                    ? 'assets/img/services/taman-minimalis.svg'
                                    : 'assets/img/services/custom-landscape.svg';
                            }
                            ?>
                            <article class="landing-service">
                                <img class="<?= $isServicePlaceholder ? 'landing-service__image--placeholder' : '' ?>" src="<?= h($serviceImage) ?>" alt="<?= h($service['name']) ?>">
                                <div class="landing-service__body">
                                    <span class="landing-service__meta"><?= h($service['category_name'] ?? 'Layanan taman') ?></span>
                                    <h3><?= h($service['name']) ?></h3>
                                    <p><?= h($service['short_description'] ?: 'Layanan dikerjakan berdasarkan hasil pemeriksaan lokasi dan kebutuhan aktual Anda.') ?></p>
                                    <div class="landing-service__actions">
                                        <a href="service_detail.php?slug=<?= h($service['slug']) ?>">Lihat detail</a>
                                        <a href="booking_create.php?service_id=<?= (int) $service['id'] ?>">Buat booking <i class="bi bi-arrow-right ms-1"></i></a>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-secondary">Layanan sedang diperbarui. Anda tetap dapat mengajukan kebutuhan custom.</p>
                <?php endif; ?>

                <?php if (count($services) > 6): ?>
                    <div class="mt-4">
                        <a class="btn btn-outline-secondary" href="catalog.php">Lihat seluruh layanan <i class="bi bi-arrow-right ms-1"></i></a>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="landing-section landing-section--soft">
            <div class="container">
                <div class="landing-section__intro">
                    <p class="landing-kicker">Yang Anda dapatkan</p>
                    <h2>Proses lapangan yang tetap mudah dipahami.</h2>
                    <p>Sistem membantu customer dan tim ABI Taman melihat informasi yang sama, sehingga keputusan tidak tersebar di chat dan perkembangan pekerjaan lebih mudah ditelusuri.</p>
                </div>
                <div class="landing-feature-grid">
                    <?php
                    $features = [
                        ['bi-calendar2-check', 'Jadwal survey terkonfirmasi', 'Staff memeriksa permintaan, memilih pekerja lapangan, lalu menetapkan jadwal yang dapat dilihat dari dashboard.'],
                        ['bi-clipboard-data', 'Hasil survey terdokumentasi', 'Kondisi lokasi, ukuran, akses, catatan, dan foto survey tersimpan sebagai dasar penyusunan pekerjaan.'],
                        ['bi-receipt', 'Estimasi terperinci', 'Material, tenaga kerja, serta biaya tambahan ditampilkan sebagai rincian. Revisi tetap tercatat dalam riwayat versi.'],
                        ['bi-bar-chart-line', 'Progres dapat dipantau', 'Status utama, catatan pekerjaan, dan foto progres membantu Anda memahami posisi order tanpa perlu menanyakan ulang.'],
                        ['bi-shield-check', 'Pembayaran terverifikasi', 'Invoice dan bukti pembayaran berada pada order yang sama, lalu diverifikasi oleh staff sebelum dinyatakan lunas.'],
                        ['bi-chat-left-text', 'Ulasan setelah selesai', 'Customer dapat memberi rating dan ulasan setelah pekerjaan serta pembayaran selesai.'],
                    ];
                    foreach ($features as [$icon, $title, $description]):
                    ?>
                        <article class="landing-feature">
                            <i class="bi <?= h($icon) ?>" aria-hidden="true"></i>
                            <div>
                                <h3><?= h($title) ?></h3>
                                <p><?= h($description) ?></p>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <section class="landing-section" id="proses">
            <div class="container">
                <div class="landing-section__intro">
                    <p class="landing-kicker">Cara kerja</p>
                    <h2>Lima tahap dari booking hingga selesai.</h2>
                    <p>Anda cukup mengikuti tindakan berikutnya yang muncul di dashboard. Status teknis tetap tercatat, namun perjalanan utama dibuat ringkas.</p>
                </div>
                <div class="landing-process">
                    <?php
                    $steps = [
                        ['01', 'Booking', 'Pilih layanan, isi lokasi, tanggal yang diinginkan, catatan, dan foto bila diperlukan.'],
                        ['02', 'Survey', 'Tim mengonfirmasi jadwal lalu memeriksa kondisi serta kebutuhan aktual di lokasi.'],
                        ['03', 'Persetujuan', 'Estimasi dikirim untuk Anda tinjau, setujui, atau minta penyesuaian.'],
                        ['04', 'Pengerjaan', 'Tim bekerja sesuai jadwal dan memperbarui catatan serta progres lapangan.'],
                        ['05', 'Selesai', 'Invoice diterbitkan, pembayaran diverifikasi, lalu Anda dapat memberikan ulasan.'],
                    ];
                    foreach ($steps as [$number, $title, $description]):
                    ?>
                        <article class="landing-process__step">
                            <span><?= h($number) ?></span>
                            <h3><?= h($title) ?></h3>
                            <p><?= h($description) ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <section class="landing-section landing-section--soft" id="informasi">
            <div class="container">
                <div class="landing-info-grid">
                    <div>
                        <p class="landing-kicker">Sebelum membuat booking</p>
                        <h2>Siapkan informasi dasar lokasi.</h2>
                        <p class="text-secondary">Data awal tidak perlu terlalu teknis. Tim lapangan akan memvalidasi detailnya saat survey.</p>
                        <ul class="landing-checklist">
                            <li><i class="bi bi-check2" aria-hidden="true"></i><span>Jenis layanan atau gambaran hasil yang diinginkan.</span></li>
                            <li><i class="bi bi-check2" aria-hidden="true"></i><span>Alamat lengkap dan patokan lokasi pekerjaan.</span></li>
                            <li><i class="bi bi-check2" aria-hidden="true"></i><span>Tanggal survey yang diharapkan.</span></li>
                            <li><i class="bi bi-check2" aria-hidden="true"></i><span>Catatan akses, kondisi khusus, dan foto lokasi jika tersedia.</span></li>
                        </ul>
                    </div>
                    <div>
                        <p class="landing-kicker">Tentang estimasi</p>
                        <h2>Biaya mengikuti kondisi aktual.</h2>
                        <p class="text-secondary">ABI Taman tidak memakai paket tetap. Estimasi disusun setelah survey agar material, tenaga kerja, dan biaya tambahan sesuai kebutuhan pekerjaan.</p>
                        <ul class="landing-checklist">
                            <li><i class="bi bi-check2" aria-hidden="true"></i><span>Material dihitung dari jenis, jumlah, dan harga satuan.</span></li>
                            <li><i class="bi bi-check2" aria-hidden="true"></i><span>Tenaga kerja dihitung dari tarif, jumlah pekerja, dan durasi.</span></li>
                            <li><i class="bi bi-check2" aria-hidden="true"></i><span>Biaya tambahan dicantumkan terpisah agar mudah ditinjau.</span></li>
                            <li><i class="bi bi-check2" aria-hidden="true"></i><span>Perubahan biaya disimpan sebagai revisi, bukan menimpa estimasi sebelumnya.</span></li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        <section class="landing-section" id="faq">
            <div class="container">
                <div class="row g-5">
                    <div class="col-lg-5">
                        <p class="landing-kicker">Pertanyaan umum</p>
                        <h2>Hal yang sering ditanyakan sebelum memulai.</h2>
                        <p class="text-secondary">Informasi detail setiap layanan tetap dapat dilihat di katalog. Untuk kebutuhan yang tidak tersedia, gunakan layanan custom.</p>
                    </div>
                    <div class="col-lg-7 landing-faq">
                        <details>
                            <summary>Apakah harga langsung terlihat saat memilih layanan?</summary>
                            <p>Harga final tidak ditentukan dari paket. Tim menyusun estimasi setelah survey berdasarkan kebutuhan material, tenaga kerja, dan biaya tambahan di lokasi.</p>
                        </details>
                        <details>
                            <summary>Apakah estimasi dapat direvisi?</summary>
                            <p>Dapat. Revisi tersimpan pada order yang sama sehingga nilai sebelumnya, alasan perubahan, dan keputusan customer tetap dapat ditelusuri.</p>
                        </details>
                        <details>
                            <summary>Bagaimana saya mengetahui perkembangan pekerjaan?</summary>
                            <p>Dashboard customer menampilkan tahap order, tindakan yang perlu dilakukan, progres, foto lapangan, estimasi, invoice, dan status pembayaran.</p>
                        </details>
                        <details>
                            <summary>Bagaimana jika layanan yang saya butuhkan tidak ada di katalog?</summary>
                            <p>Pilih kebutuhan custom, kemudian jelaskan tujuan pekerjaan, lokasi, jadwal survey yang diinginkan, dan lampirkan foto bila tersedia.</p>
                        </details>
                    </div>
                </div>
            </div>
        </section>

        <section class="landing-cta">
            <div class="container">
                <div class="row align-items-end g-4">
                    <div class="col-lg-8">
                        <p class="landing-kicker text-white-50">Mulai dari kebutuhan Anda</p>
                        <h2>Jelaskan lokasinya. Kami bantu susun langkah berikutnya.</h2>
                    </div>
                    <div class="col-lg-4 d-flex gap-2 flex-wrap justify-content-lg-end">
                        <a class="btn btn-light btn-lg" href="catalog.php">Pilih layanan</a>
                        <a class="btn btn-outline-light btn-lg" href="<?= $current ? 'dashboard.php' : 'login.php?mode=register' ?>">
                            <?= $current ? 'Buka dashboard' : 'Buat akun' ?>
                        </a>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="landing-footer">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-5">
                    <a class="landing-brand text-white text-decoration-none" href="index.php">
                        <img src="assets/img/abi-taman.png" alt="">
                        <span>ABI TAMAN</span>
                    </a>
                    <p class="small mt-3 mb-0">Pembuatan, renovasi, dan perawatan taman dengan proses yang terdokumentasi dari survey sampai pembayaran.</p>
                </div>
                <div class="col-6 col-lg-2 offset-lg-1">
                    <strong class="d-block text-white small mb-3">Layanan</strong>
                    <ul class="list-unstyled d-grid gap-2 small">
                        <li><a href="catalog.php">Katalog</a></li>
                        <li><a href="booking_create.php?custom=1">Kebutuhan custom</a></li>
                    </ul>
                </div>
                <div class="col-6 col-lg-2">
                    <strong class="d-block text-white small mb-3">Informasi</strong>
                    <ul class="list-unstyled d-grid gap-2 small">
                        <li><a href="#proses">Cara kerja</a></li>
                        <li><a href="#informasi">Tentang estimasi</a></li>
                        <li><a href="#faq">Pertanyaan umum</a></li>
                    </ul>
                </div>
                <div class="col-6 col-lg-2">
                    <strong class="d-block text-white small mb-3">Akun</strong>
                    <ul class="list-unstyled d-grid gap-2 small">
                        <li><a href="login.php">Masuk</a></li>
                        <li><a href="login.php?mode=register">Daftar</a></li>
                        <li><a href="dashboard.php">Dashboard</a></li>
                    </ul>
                </div>
            </div>
            <div class="border-top border-secondary mt-4 pt-4 small">
                &copy; <?= date('Y') ?> ABI Taman. Seluruh hak dilindungi.
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
