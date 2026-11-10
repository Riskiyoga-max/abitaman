<?php
declare(strict_types=1);

/**
 * Membuat satu dokumen penjelasan Bab 3 pada setiap folder diagram.
 *
 * Format mengikuti pola dokumen referensi:
 * - judul subbab;
 * - paragraf pengantar;
 * - gambar dan caption;
 * - paragraf "Berdasarkan diagram...";
 * - tabel rincian untuk memperjelas alur/struktur.
 *
 * DOCX dibentuk secara lokal melalui LibreOffice dari HTML berformat A4.
 */

require_once __DIR__ . '/generate_bab3_diagrams.php';

const DOC_ROOT = __DIR__ . '/../dataabitaman/diagram-bab3';

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function imageDataUri(string $path): string
{
    $absolute = realpath($path);
    if ($absolute === false) {
        throw new RuntimeException("Gambar tidak ditemukan: {$path}");
    }
    $mime = mime_content_type($absolute) ?: 'image/png';
    return 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($absolute));
}

function paragraph(string $text): string
{
    return '<p>' . e($text) . '</p>';
}

function figureBlock(string $imagePath, string $caption, bool $wide = false): string
{
    $size = getimagesize($imagePath);
    if ($size === false) {
        throw new RuntimeException("Ukuran gambar tidak dapat dibaca: {$imagePath}");
    }
    [$pixelWidth, $pixelHeight] = $size;
    $maxWidthCm = $wide ? 23.0 : 15.5;
    $maxHeightCm = $wide ? 15.5 : 20.5;
    $scale = min($maxWidthCm / $pixelWidth, $maxHeightCm / $pixelHeight);
    $widthCm = round($pixelWidth * $scale, 2);
    $heightCm = round($pixelHeight * $scale, 2);
    return '<figure class="' . ($wide ? 'wide-figure' : '') . '">'
        . '<img src="' . imageDataUri($imagePath) . '" alt="' . e($caption)
        . '" style="width:' . $widthCm . 'cm;height:' . $heightCm . 'cm;">'
        . '<figcaption>' . e($caption) . '</figcaption>'
        . '</figure>';
}

function tableBlock(array $headers, array $rows, array $widths = []): string
{
    $html = '<table><thead><tr>';
    foreach ($headers as $index => $header) {
        $style = isset($widths[$index]) ? ' style="width:' . (float) $widths[$index] . '%"' : '';
        $html .= '<th' . $style . '>' . e((string) $header) . '</th>';
    }
    $html .= '</tr></thead><tbody>';
    foreach ($rows as $row) {
        $html .= '<tr>';
        foreach ($row as $cell) {
            $html .= '<td>' . e((string) $cell) . '</td>';
        }
        $html .= '</tr>';
    }
    return $html . '</tbody></table>';
}

function documentHtml(string $documentTitle, string $body, bool $landscape = false): string
{
    $pageSize = $landscape ? 'A4 landscape' : 'A4 portrait';
    $imageWidth = $landscape ? '23.5cm' : '15.5cm';
    $tableWidth = $landscape ? '22.5cm' : '14.8cm';
    return '<!doctype html><html lang="id"><head><meta charset="utf-8">'
        . '<title>' . e($documentTitle) . '</title>'
        . '<style>'
        . '@page { size:' . $pageSize . '; margin:3cm 3cm 3cm 4cm; }'
        . 'body { font-family:"Times New Roman",serif; font-size:12pt; line-height:1.5; color:#000; }'
        . 'h1 { font-size:14pt; font-weight:bold; margin:0 0 12pt; text-align:left; }'
        . 'h2 { font-size:12pt; font-weight:bold; margin:18pt 0 6pt; text-align:left; page-break-after:avoid; }'
        . 'h3 { font-size:12pt; font-weight:bold; margin:12pt 0 6pt; text-align:left; page-break-after:avoid; }'
        . 'p { margin:0 0 10pt; text-align:justify; text-indent:1.25cm; }'
        . '.no-indent { text-indent:0; }'
        . '.section-page { page-break-before:always; }'
        . '.section-page.first { page-break-before:auto; }'
        . 'figure { margin:12pt 0 10pt; text-align:center; page-break-inside:avoid; }'
        . 'figure img { display:block; margin:0 auto; max-width:' . $imageWidth . '; max-height:21cm; width:auto; height:auto; }'
        . '.wide-figure img { max-width:' . $imageWidth . '; max-height:18cm; }'
        . 'figcaption { margin-top:6pt; font-size:11pt; text-align:center; }'
        . 'table { width:' . $tableWidth . '; table-layout:fixed; border-collapse:collapse; margin:8pt 0 14pt; font-size:9pt; page-break-inside:auto; }'
        . 'thead { display:table-header-group; }'
        . 'tr { page-break-inside:avoid; }'
        . 'th,td { border:1px solid #000; padding:4pt 5pt; vertical-align:top; overflow-wrap:break-word; word-wrap:break-word; word-break:normal; }'
        . 'th { font-weight:bold; text-align:center; background:#e7e6e6; }'
        . 'td { text-align:left; }'
        . 'td:first-child { text-align:center; }'
        . '.table-caption { text-align:center; text-indent:0; margin:5pt 0 4pt; font-size:11pt; }'
        . '.source { text-align:center; text-indent:0; font-size:10pt; margin-top:-6pt; }'
        . '</style></head><body>' . $body . '</body></html>';
}

function processNarratives(): array
{
    return [
        '01-registrasi-customer' => [
            'purpose' => 'proses pembuatan akun baru oleh calon customer, mulai dari pengisian identitas sampai akun aktif dan pengguna diarahkan ke dashboard',
            'result' => 'Sistem memvalidasi token CSRF, kelengkapan data, format email, panjang password, konfirmasi password, dan duplikasi email. Data yang valid disimpan menggunakan password yang telah di-hash, sedangkan data tidak valid dikembalikan kepada pengguna untuk diperbaiki.',
        ],
        '02-login' => [
            'purpose' => 'proses autentikasi seluruh pengguna menggunakan email dan password yang telah terdaftar',
            'result' => 'Sistem hanya membentuk session ketika akun berstatus aktif dan password sesuai. Pengguna yang berhasil login diarahkan ke dashboard berdasarkan role, sedangkan kegagalan autentikasi menghasilkan pesan kesalahan tanpa membuka akses aplikasi.',
        ],
        '03-profil-dan-ubah-password' => [
            'purpose' => 'proses perubahan identitas profil dan penggantian password oleh pengguna yang sedang login',
            'result' => 'Perubahan profil disimpan setelah nama dan email dinyatakan valid serta tidak digunakan akun lain. Perubahan password mensyaratkan password lama yang benar, password baru minimal enam karakter, dan konfirmasi yang sama; session kemudian dihentikan agar pengguna melakukan login ulang.',
        ],
        '04-pembuatan-booking' => [
            'purpose' => 'proses pengajuan layanan taman oleh customer melalui katalog maupun kebutuhan custom',
            'result' => 'Booking yang lolos validasi memperoleh kode unik dan status Menunggu Konfirmasi. Foto referensi disimpan bila tersedia, riwayat status serta activity log dicatat, dan Staff menerima notifikasi booking baru.',
        ],
        '05-konfirmasi-dan-penjadwalan-survei' => [
            'purpose' => 'proses keputusan Staff terhadap booking baru serta penetapan pekerja, tanggal, dan waktu survei',
            'result' => 'Booking dapat ditolak dengan status Dibatalkan atau diterima melalui penjadwalan survei. Sistem memastikan pekerja aktif dan tidak memiliki konflik jadwal, lalu memperbarui penugasan, status Survey Dijadwalkan, riwayat, serta notifikasi kepada Customer dan Pekerja Lapangan.',
        ],
        '06-pelaksanaan-survei' => [
            'purpose' => 'proses dimulainya survei lapangan, pencatatan kondisi lokasi, dan penyimpanan hasil survei',
            'result' => 'Pekerja Lapangan hanya dapat memproses booking yang ditugaskan kepadanya. Luas aktual, kondisi lokasi, akses, kendala, estimasi hari dan tenaga kerja, catatan, serta foto before disimpan sebelum status berubah menjadi Survey Selesai.',
        ],
        '07-penyusunan-estimasi' => [
            'purpose' => 'proses penyusunan estimasi biaya berdasarkan hasil survei dan master biaya yang aktif',
            'result' => 'Sistem menghitung biaya material, tenaga kerja, dan biaya tambahan secara terpisah sebelum membentuk grand total. Estimasi beserta seluruh item disimpan sebagai Draft, status booking menjadi Estimasi Dibuat, dan Staff memperoleh notifikasi untuk melakukan review.',
        ],
        '08-review-dan-pengiriman-estimasi' => [
            'purpose' => 'proses pemeriksaan estimasi oleh Staff sebelum dikirim kepada Customer',
            'result' => 'Staff menilai kesesuaian rincian item, kuantitas, harga, durasi, jumlah pekerja, dan total. Estimasi yang layak dikirim berubah menjadi Dikirim, booking menjadi Menunggu Persetujuan Customer, dan Customer menerima notifikasi untuk memberikan keputusan.',
        ],
        '09-keputusan-estimasi' => [
            'purpose' => 'proses Customer menyetujui atau menolak estimasi yang telah dikirim Staff',
            'result' => 'Persetujuan mengubah status estimasi menjadi Disetujui dan menyiapkan booking untuk pengerjaan. Penolakan mengubah status estimasi menjadi Ditolak, membuat kewajiban Survey Fee, membentuk invoice Survey Fee bila belum tersedia, serta mengubah booking menjadi Menunggu Pembayaran.',
        ],
        '10-pengerjaan-dan-update-progres' => [
            'purpose' => 'proses dimulainya pekerjaan taman dan pembaruan progres lapangan secara berkala',
            'result' => 'Pekerjaan hanya dapat dimulai setelah estimasi disetujui. Pekerja Lapangan mengirim persentase progres, judul, catatan, kendala, dan foto; data tersebut disimpan sebagai riwayat progres agar dapat dipantau Customer.',
        ],
        '11-revisi-estimasi' => [
            'purpose' => 'proses pencatatan perubahan biaya yang muncul ketika pekerjaan sedang berlangsung',
            'result' => 'Pekerja Lapangan membuat draft revisi berisi alasan dan item tambahan. Setelah Staff mengirim revisi, Customer dapat menyetujui atau menolak; kedua keputusan dicatat dan booking kembali ke status Dalam Pengerjaan agar pekerjaan dapat dilanjutkan.',
        ],
        '12-penyelesaian-pekerjaan-dan-invoice-draft' => [
            'purpose' => 'proses penutupan pekerjaan lapangan dan pembentukan invoice final berstatus Draft',
            'result' => 'Setelah pekerjaan ditandai selesai, Pekerja Lapangan mengirim data hari dan jumlah pekerja aktual, catatan, serta foto after. Sistem menjumlahkan estimasi dasar dan revisi yang disetujui, membentuk invoice beserta itemnya, lalu memberi tahu Staff untuk melakukan review.',
        ],
        '13-review-dan-pengiriman-invoice' => [
            'purpose' => 'proses pemeriksaan, koreksi, perhitungan ulang, dan pengiriman invoice final oleh Staff',
            'result' => 'Staff dapat mengubah kuantitas, harga satuan, hari aktual, jumlah pekerja, dan catatan selama invoice masih Draft. Setelah dikirim, invoice berubah menjadi Dikirim, booking menjadi Menunggu Pembayaran, dan Customer menerima informasi nominal yang harus dibayar.',
        ],
        '14-pembayaran-dan-verifikasi' => [
            'purpose' => 'proses pengiriman bukti pembayaran oleh Customer dan verifikasi oleh Staff',
            'result' => 'Bukti pembayaran disimpan pada transaksi berstatus Belum Dibayar dan memicu notifikasi Staff. Setelah bukti dinyatakan valid, invoice serta payment berubah menjadi Lunas, penanda Survey Fee diperbarui bila relevan, dan status booking menjadi Lunas.',
        ],
        '15-penyelesaian-order-dan-ulasan' => [
            'purpose' => 'proses penetapan order selesai dan pemberian rating serta komentar oleh Customer',
            'result' => 'Staff hanya dapat menyelesaikan order yang telah Lunas. Customer kemudian menerima permintaan ulasan dan dapat menyimpan rating satu sampai lima serta komentar; ulasan existing diperbarui sehingga setiap booking hanya memiliki satu ulasan.',
        ],
        '16-kelola-master-data' => [
            'purpose' => 'proses pengelolaan kategori, layanan, material, tarif tenaga kerja, dan jenis biaya tambahan oleh Staff',
            'result' => 'Sistem mendukung tambah, ubah, serta hapus master data. Data yang masih digunakan pada histori tidak dihapus secara fisik, melainkan dinonaktifkan agar integritas transaksi lama tetap terjaga.',
        ],
        '17-kelola-user-dan-pengaturan' => [
            'purpose' => 'proses pengelolaan akun pengguna serta konfigurasi identitas perusahaan dan Survey Fee',
            'result' => 'Email dan role divalidasi sebelum user disimpan, password di-hash, dan akun yang memiliki histori dinonaktifkan ketika dihapus. Pengaturan perusahaan disimpan sebagai pasangan setting_key dan setting_value setelah email serta nilai Survey Fee dinyatakan valid.',
        ],
        '18-monitoring-dan-laporan-boss' => [
            'purpose' => 'proses pemantauan kinerja operasional dan penyusunan laporan manajemen oleh Boss',
            'result' => 'Boss memperoleh akses read-only terhadap booking, pekerjaan, pekerja, estimasi, invoice, dan pembayaran. Laporan dapat difilter menurut periode, layanan, dan pekerja, kemudian diekspor sebagai CSV atau dicetak menjadi PDF.',
        ],
        '19-logout' => [
            'purpose' => 'proses pengguna mengakhiri sesi aplikasi secara aman',
            'result' => 'Sistem hanya menerima logout melalui request POST dengan token CSRF yang valid. Data session dan cookie dihapus, session dihancurkan, lalu pengguna diarahkan kembali ke halaman login.',
        ],
    ];
}

function activityDocument(): string
{
    $body = '<h1>3.6.2 Activity Diagram</h1>'
        . paragraph('Activity diagram digunakan untuk menggambarkan alur aktivitas yang terjadi di dalam Sistem Informasi Manajemen Jasa Taman ABI Taman. Diagram disusun berdasarkan fungsi aktual aplikasi, pembagian hak akses pengguna, validasi data, aturan status booking, serta proses penyimpanan data pada database.')
        . paragraph('Activity diagram pada penelitian ini terdiri dari sembilan belas proses. Setiap diagram menggunakan swimlane untuk memperlihatkan pihak atau komponen yang bertanggung jawab terhadap aktivitas tertentu, sehingga perpindahan proses antara pengguna, sistem, database, dan komponen pendukung dapat dipahami secara jelas.');
    $narratives = processNarratives();
    foreach (detailedProcessSpecs() as $index => $spec) {
        $number = $index + 1;
        $figureNumber = 5 + $number;
        $narrative = $narratives[$spec['slug']];
        $body .= '<section class="section-page' . ($index === 0 ? ' first' : '') . '">'
            . '<h2>3.6.2.' . $number . ' Activity Diagram ' . e($spec['title']) . '</h2>'
            . paragraph('Activity diagram ' . strtolower($spec['title']) . ' menggambarkan ' . $narrative['purpose'] . '.')
            . figureBlock(
                DOC_ROOT . '/activity-diagram/' . $spec['slug'] . '.png',
                'Gambar 3.' . $figureNumber . ' Activity Diagram ' . $spec['title']
            )
            . '<p class="source">Sumber: Hasil Perancangan (2026)</p>'
            . paragraph('Berdasarkan activity diagram pada gambar di atas, ' . $narrative['result'])
            . '<p class="table-caption">Tabel 3.' . $figureNumber . ' Rincian Activity Diagram ' . e($spec['title']) . '</p>';
        $rows = [];
        foreach ($spec['steps'] as [$id, $laneIndex, $type, $text]) {
            if (in_array($type, ['start', 'end'], true)) {
                continue;
            }
            $typeLabel = match ($type) {
                'decision' => 'Keputusan',
                'error' => 'Penanganan Kesalahan',
                default => 'Aktivitas',
            };
            $rows[] = [$spec['lanes'][$laneIndex], $typeLabel, $text];
        }
        $body .= tableBlock(['Swimlane', 'Jenis', 'Aktivitas'], $rows, [25, 20, 55])
            . '</section>';
    }
    return documentHtml('Penjelasan Activity Diagram ABI Taman', $body);
}

function sequenceDocument(): string
{
    $body = '<h1>3.6.3 Sequence Diagram</h1>'
        . paragraph('Sequence diagram digunakan untuk menggambarkan urutan komunikasi antaraktor dan objek sistem berdasarkan waktu. Diagram ini menunjukkan request yang dikirim oleh pengguna, pemrosesan pada halaman dan backend, operasi terhadap database maupun penyimpanan file, serta response yang diterima kembali oleh pengguna.')
        . paragraph('Sequence diagram pada penelitian ini dibuat untuk sembilan belas proses utama aplikasi. Pesan bergaris penuh menunjukkan request atau perintah, sedangkan pesan kembali menggambarkan response, data hasil query, notifikasi, atau hasil pemrosesan sistem.');
    $narratives = processNarratives();
    foreach (detailedProcessSpecs() as $index => $spec) {
        $number = $index + 1;
        $figureNumber = 24 + $number;
        $narrative = $narratives[$spec['slug']];
        $body .= '<section class="section-page' . ($index === 0 ? ' first' : '') . '">'
            . '<h2>3.6.3.' . $number . ' Sequence Diagram ' . e($spec['title']) . '</h2>'
            . paragraph('Sequence diagram ' . strtolower($spec['title']) . ' menggambarkan urutan interaksi pada ' . $narrative['purpose'] . '.')
            . figureBlock(
                DOC_ROOT . '/sequence-diagram/' . $spec['slug'] . '.png',
                'Gambar 3.' . $figureNumber . ' Sequence Diagram ' . $spec['title']
            )
            . '<p class="source">Sumber: Hasil Perancangan (2026)</p>'
            . paragraph('Berdasarkan sequence diagram pada gambar di atas, ' . $narrative['result'] . ' Urutan pesan pada diagram memastikan bahwa setiap validasi dan perubahan data terjadi melalui komponen yang sesuai.')
            . '<p class="table-caption">Tabel 3.' . $figureNumber . ' Rincian Pesan Sequence Diagram ' . e($spec['title']) . '</p>';
        $rows = [];
        foreach ($spec['messages'] as $messageIndex => $message) {
            [$from, $to, $label] = $message;
            $response = (bool) ($message[3] ?? false);
            $rows[] = [
                $spec['participants'][$from],
                $spec['participants'][$to],
                $response ? 'Response' : 'Request/Proses',
                $label,
            ];
        }
        $body .= tableBlock(['Pengirim', 'Penerima', 'Jenis Pesan', 'Pesan/Proses'], $rows, [20, 20, 18, 42])
            . '</section>';
    }
    return documentHtml('Penjelasan Sequence Diagram ABI Taman', $body);
}

function proposedSystemDocument(): string
{
    $body = '<h1>3.1.2 Analisis Sistem Usulan</h1>'
        . paragraph('Sistem usulan merupakan pengembangan proses pelayanan ABI Taman yang sebelumnya bergantung pada komunikasi WhatsApp dan pencatatan manual menjadi sistem informasi berbasis web. Sistem mengintegrasikan proses pemesanan, survei, estimasi, pengerjaan, invoice, pembayaran, ulasan, dan laporan ke dalam satu alur data yang dapat ditelusuri.')
        . paragraph('Pada sistem usulan terdapat empat aktor utama, yaitu Customer, Staff, Pekerja Lapangan, dan Boss. Setiap aktor memperoleh dashboard dan hak akses sesuai tanggung jawabnya, sedangkan sistem menangani validasi, perubahan status, notifikasi, penyimpanan bukti, dan pencatatan riwayat aktivitas.')
        . figureBlock(DOC_ROOT . '/analisis-sistem-usulan/01-analisis-sistem-usulan.png', 'Gambar 3.2 Analisis Sistem Usulan')
        . '<p class="source">Sumber: Hasil Perancangan (2026)</p>'
        . paragraph('Berdasarkan analisis sistem usulan pada gambar di atas, Customer memulai proses dengan memilih layanan dan mengirim booking. Staff mengonfirmasi permintaan serta menetapkan jadwal survei. Pekerja Lapangan mengisi hasil survei, menyusun estimasi, dan memperbarui progres. Setelah Customer menyetujui estimasi, pekerjaan dilaksanakan sampai invoice dan pembayaran selesai. Data transaksi selanjutnya digunakan Boss untuk memantau kinerja dan laporan perusahaan.')
        . '<p class="table-caption">Tabel 3.2 Rincian Analisis Sistem Usulan</p>'
        . tableBlock(
            ['No.', 'Aktor/Komponen', 'Proses Utama', 'Output'],
            [
                [1, 'Customer', 'Registrasi, booking, persetujuan estimasi, pembayaran, dan ulasan', 'Data pesanan dan keputusan Customer'],
                [2, 'Staff', 'Konfirmasi booking, jadwal survei, review estimasi/invoice, dan verifikasi pembayaran', 'Status operasional yang tervalidasi'],
                [3, 'Pekerja Lapangan', 'Survei, estimasi, progres, revisi, dan data final pekerjaan', 'Dokumentasi serta nilai pekerjaan aktual'],
                [4, 'Boss', 'Monitoring KPI dan laporan', 'Informasi kinerja dan pendapatan'],
                [5, 'Sistem', 'Validasi, penyimpanan, notifikasi, history, dan activity log', 'Data terintegrasi serta dapat ditelusuri'],
            ],
            [7, 20, 45, 28]
        );
    return documentHtml('Penjelasan Analisis Sistem Usulan ABI Taman', $body);
}

function architectureDocument(): string
{
    $body = '<h1>3.4 Perancangan Arsitektur Sistem</h1>'
        . paragraph('Perancangan arsitektur sistem menggambarkan susunan komponen yang digunakan untuk menjalankan Sistem Informasi Manajemen Jasa Taman ABI Taman. Arsitektur dirancang menggunakan pendekatan tiga lapis yang memisahkan antarmuka pengguna, logika aplikasi, dan pengelolaan data.')
        . figureBlock(DOC_ROOT . '/arsitektur/02-arsitektur-sistem.png', 'Gambar 3.3 Perancangan Arsitektur Sistem', true)
        . '<p class="source">Sumber: Hasil Perancangan (2026)</p>'
        . paragraph('Berdasarkan arsitektur pada gambar di atas, Customer, Staff, Pekerja Lapangan, dan Boss mengakses aplikasi menggunakan browser melalui jaringan internet/HTTPS. Presentation layer menyajikan halaman PHP, Bootstrap, dan JavaScript. Application layer menjalankan controller, API, workflow booking, estimator biaya, validasi bisnis, session, CSRF, dan pembatasan role. Data layer menyimpan data terstruktur pada MySQL/MariaDB serta berkas foto dan bukti pembayaran pada penyimpanan lokal.')
        . '<p class="table-caption">Tabel 3.3 Komponen Arsitektur Sistem</p>'
        . tableBlock(
            ['No.', 'Lapisan', 'Komponen', 'Tanggung Jawab'],
            [
                [1, 'Client Layer', 'Browser desktop/mobile', 'Menyediakan akses bagi Customer, Staff, Pekerja Lapangan, dan Boss'],
                [2, 'Presentation Layer', 'PHP Views, Bootstrap 5, Vanilla JavaScript', 'Menampilkan form, dashboard, tabel, notifikasi, dan response antarmuka'],
                [3, 'Application Layer', 'PHP Native Controllers/API', 'Menjalankan validasi, RBAC, workflow booking, estimator, dan aktivitas bisnis'],
                [4, 'Data Layer', 'MySQL/MariaDB', 'Menyimpan 23 tabel relasional dan menjaga hubungan foreign key'],
                [5, 'File Storage', 'Direktori assets/uploads', 'Menyimpan foto referensi, before, progress, after, dan bukti pembayaran'],
            ],
            [7, 21, 31, 41]
        );
    return documentHtml('Penjelasan Arsitektur Sistem ABI Taman', $body, true);
}

function useCaseDocument(): string
{
    $body = '<h1>3.6.1 Use Case Diagram</h1>'
        . paragraph('Use case diagram digunakan untuk menggambarkan hubungan antara aktor dengan fungsi yang disediakan Sistem Informasi Manajemen Jasa Taman ABI Taman. Diagram memperlihatkan batas sistem dan pembagian hak akses berdasarkan role pengguna.')
        . figureBlock(DOC_ROOT . '/use-case/03-use-case-diagram.png', 'Gambar 3.5 Use Case Diagram', true)
        . '<p class="source">Sumber: Hasil Perancangan (2026)</p>'
        . paragraph('Berdasarkan use case diagram pada gambar di atas, terdapat empat aktor utama. Customer mengelola proses pemesanan dan keputusan transaksi miliknya. Staff menjalankan administrasi operasional dan master data. Pekerja Lapangan menangani kegiatan lapangan dari survei sampai data final. Boss memperoleh fungsi monitoring dan laporan tanpa melakukan perubahan terhadap transaksi operasional. Seluruh aktor dapat melakukan login, mengelola profil, dan logout.')
        . '<p class="table-caption">Tabel 3.5 Deskripsi Hak Akses Use Case</p>'
        . tableBlock(
            ['No.', 'Aktor', 'Use Case', 'Deskripsi'],
            [
                [1, 'Seluruh Pengguna', 'Login, Kelola Profil, Logout', 'Mengakses sistem, memperbarui akun, dan mengakhiri session'],
                [2, 'Customer', 'Katalog dan Booking', 'Melihat layanan serta mengajukan layanan biasa atau custom'],
                [3, 'Customer', 'Keputusan Estimasi', 'Menyetujui atau menolak estimasi dan revisi'],
                [4, 'Customer', 'Pembayaran, Invoice, dan Ulasan', 'Mengunggah bukti bayar, melihat invoice, dan memberikan rating'],
                [5, 'Staff', 'Operasional Booking', 'Konfirmasi booking, jadwal survei, review estimasi, pekerjaan, dan invoice'],
                [6, 'Staff', 'Administrasi', 'Kelola master data, pengguna, pengaturan, laporan, dan activity log'],
                [7, 'Pekerja Lapangan', 'Survei dan Estimasi', 'Mencatat kondisi lokasi serta menyusun estimasi biaya'],
                [8, 'Pekerja Lapangan', 'Pengerjaan', 'Memulai pekerjaan, memperbarui progres, revisi, dan data final'],
                [9, 'Boss', 'Monitoring dan Laporan', 'Melihat KPI, transaksi, kinerja pekerja, pendapatan, CSV, dan cetak laporan'],
            ],
            [7, 20, 28, 45]
        );
    return documentHtml('Penjelasan Use Case Diagram ABI Taman', $body, true);
}

function classDocument(): string
{
    $rows = [
        [1, 'User', 'Data akun, role, status', 'Login, profil, password; berelasi dengan transaksi dan notifikasi'],
        [2, 'ServiceCategory', 'Kategori layanan', 'Mengelompokkan banyak Service'],
        [3, 'Service', 'Kode, nama, harga dasar, status', 'Dipilih pada banyak Booking'],
        [4, 'Booking', 'Customer, layanan, lokasi, jadwal, status', 'Kelas pusat workflow jasa taman'],
        [5, 'Schedule', 'Tanggal, waktu, pekerja, status', 'Menjadwalkan survei dan aktivitas lapangan'],
        [6, 'Survey', 'Luas aktual dan kondisi lokasi', 'Menjadi dasar Estimate'],
        [7, 'Estimate', 'Total material, tenaga kerja, tambahan', 'Memiliki banyak EstimateItem dan revision'],
        [8, 'EstimateItem', 'Jenis, quantity, harga, subtotal', 'Rincian biaya Estimate'],
        [9, 'EstimateRevision', 'Alasan, tambahan, total baru', 'Perubahan biaya saat pekerjaan berlangsung'],
        [10, 'WorkProgress', 'Persentase, judul, catatan', 'Riwayat progres Booking'],
        [11, 'FinalInvoice', 'Total akhir dan status pembayaran', 'Tagihan akhir Booking'],
        [12, 'Payment', 'Jenis, jumlah, bukti, status', 'Pembayaran invoice final atau Survey Fee'],
        [13, 'Review', 'Rating dan komentar', 'Satu ulasan untuk satu Booking selesai'],
        [14, 'Notification', 'Target, judul, pesan, status baca', 'Menyampaikan informasi workflow kepada pengguna'],
    ];
    $body = '<h1>3.6.4 Class Diagram</h1>'
        . paragraph('Class diagram digunakan untuk menggambarkan struktur kelas domain pada Sistem Informasi Manajemen Jasa Taman ABI Taman. Setiap kelas memuat atribut utama, operasi yang mewakili perilaku sistem, serta hubungan dengan kelas lain.')
        . figureBlock(DOC_ROOT . '/class-diagram/08-class-diagram.png', 'Gambar 3.44 Class Diagram', true)
        . '<p class="source">Sumber: Hasil Perancangan (2026)</p>'
        . paragraph('Berdasarkan class diagram pada gambar di atas, Booking menjadi kelas pusat yang menghubungkan Customer, layanan, jadwal, survei, estimasi, progres pekerjaan, invoice, pembayaran, dan ulasan. Hubungan antarkelas mencerminkan alur bisnis aktual dari booking sampai pekerjaan selesai, sedangkan Notification digunakan untuk menyampaikan perubahan penting kepada pengguna.')
        . '<p class="table-caption">Tabel 3.44 Penjelasan Class Diagram</p>'
        . tableBlock(['No.', 'Class', 'Atribut/Fokus Data', 'Method dan Relasi Utama'], $rows, [7, 20, 31, 42]);
    return documentHtml('Penjelasan Class Diagram ABI Taman', $body, true);
}

function erdDocument(): string
{
    $purposes = entityPurposes();
    $rows = [];
    foreach (schemaTables() as $name => $fields) {
        $rows[] = [
            count($rows) + 1,
            $name,
            implode(', ', array_slice($fields, 0, 4)),
            $purposes[$name] ?? 'Menyimpan data pendukung sistem.',
        ];
    }
    $body = '<h1>3.7.2 Entity Relationship Diagram (ERD)</h1>'
        . paragraph('Entity Relationship Diagram digunakan untuk menggambarkan entitas, atribut kunci, dan hubungan data yang membentuk database ABI Taman. ERD disusun berdasarkan skema implementasi yang terdiri dari 23 tabel dan 35 foreign key.')
        . figureBlock(DOC_ROOT . '/erd/09-erd.png', 'Gambar 3.45 Entity Relationship Diagram', true)
        . '<p class="source">Sumber: Hasil Perancangan (2026)</p>'
        . paragraph('Berdasarkan ERD pada gambar di atas, tabel bookings menjadi pusat transaksi. Tabel tersebut berelasi dengan users, services, schedules, surveys, estimates, work_progress, final_invoices, payments, reviews, dan tabel riwayat. Relasi detail digunakan untuk menyimpan item estimasi, revisi, serta invoice tanpa menggabungkan data berulang ke dalam satu record.')
        . '<p class="table-caption">Tabel 3.45 Penjelasan Entitas pada ERD</p>'
        . tableBlock(['No.', 'Entitas', 'Kunci/Atribut Utama', 'Fungsi'], $rows, [6, 22, 34, 38]);
    return documentHtml('Penjelasan ERD ABI Taman', $body, true);
}

function transformationDocument(): string
{
    $body = '<h1>3.7.3 Transformasi ERD ke LRS</h1>'
        . paragraph('Transformasi ERD ke LRS merupakan tahap perubahan model konseptual menjadi struktur record logis yang mendekati bentuk tabel database. Setiap entitas, relasi, primary key, dan kardinalitas pada ERD dipetakan menjadi tabel serta foreign key pada LRS.')
        . figureBlock(DOC_ROOT . '/transformasi-erd-lrs/10-transformasi-erd-ke-lrs.png', 'Gambar 3.46 Transformasi ERD ke LRS', true)
        . '<p class="source">Sumber: Hasil Perancangan (2026)</p>'
        . paragraph('Berdasarkan transformasi pada gambar di atas, setiap entitas kuat membentuk satu tabel. Relasi satu-ke-banyak dipindahkan sebagai foreign key pada tabel di sisi banyak. Data item yang berulang, seperti item estimasi, revisi, dan invoice, dipisahkan menjadi tabel detail. Relasi satu-ke-satu menggunakan foreign key unik, seperti hubungan bookings dengan reviews.')
        . '<p class="table-caption">Tabel 3.46 Aturan Transformasi ERD ke LRS</p>'
        . tableBlock(
            ['No.', 'Aturan Transformasi', 'Penerapan pada ABI Taman'],
            [
                [1, 'Entitas kuat menjadi tabel', 'users, services, bookings, surveys, estimates, final_invoices, dan entitas lainnya'],
                [2, 'Relasi 1:N menjadi foreign key pada sisi N', 'customer_id pada bookings, booking_id pada surveys, estimate_id pada estimate_items'],
                [3, 'Atribut multivalue/item berulang menjadi tabel detail', 'survey_photos, estimate_items, estimate_revision_items, final_invoice_items'],
                [4, 'Relasi 1:1 menggunakan foreign key unik', 'booking_id pada reviews bersifat UNIQUE'],
                [5, 'Relasi dengan histori dipisahkan', 'booking_status_histories dan activity_logs menjaga jejak perubahan'],
                [6, 'Primary key teknis menggunakan id', 'Seluruh tabel memakai id auto increment; kode bisnis tetap diberi UNIQUE'],
            ],
            [7, 39, 54]
        );
    return documentHtml('Penjelasan Transformasi ERD ke LRS ABI Taman', $body, true);
}

function lrsDocument(): string
{
    $purposes = entityPurposes();
    $rows = [];
    foreach (schemaTables() as $name => $fields) {
        $primary = $fields[0] ?? 'PK id';
        $foreign = array_values(array_filter($fields, static fn(string $field): bool => str_starts_with($field, 'FK ')));
        $rows[] = [
            count($rows) + 1,
            strtoupper($name),
            $primary,
            $foreign ? implode(', ', $foreign) : '-',
            $purposes[$name] ?? 'Data pendukung sistem.',
        ];
    }
    $body = '<h1>3.7.4 Logical Record Structure (LRS)</h1>'
        . paragraph('Logical Record Structure menggambarkan susunan record logis yang akan diterapkan pada database Sistem Informasi Manajemen Jasa Taman ABI Taman. LRS menunjukkan nama tabel, primary key, foreign key, serta hubungan antarrecord setelah proses transformasi ERD.')
        . figureBlock(DOC_ROOT . '/lrs/11-lrs.png', 'Gambar 3.47 Logical Record Structure', true)
        . '<p class="source">Sumber: Hasil Perancangan (2026)</p>'
        . paragraph('Berdasarkan LRS pada gambar di atas, database terdiri dari 23 tabel. Tabel master menyimpan layanan dan komponen biaya, tabel transaksi menangani booking sampai pembayaran, sedangkan tabel pendukung menyimpan notifikasi, pengaturan, riwayat status, aktivitas, foto, dan ulasan. Penggunaan foreign key menjaga konsistensi hubungan data sepanjang workflow.')
        . '<p class="table-caption">Tabel 3.47 Penjelasan Logical Record Structure</p>'
        . tableBlock(['No.', 'Record/Tabel', 'Primary Key', 'Foreign Key Utama', 'Fungsi'], $rows, [5, 19, 14, 28, 34]);
    return documentHtml('Penjelasan LRS ABI Taman', $body, true);
}

function entityPurposes(): array
{
    return [
        'users' => 'Menyimpan akun Customer, Pekerja Lapangan, Staff, dan Boss.',
        'service_categories' => 'Mengelompokkan jenis layanan.',
        'services' => 'Menyimpan katalog layanan taman.',
        'materials' => 'Menyimpan material dan harga satuan.',
        'labor_rates' => 'Menyimpan tarif tenaga kerja.',
        'additional_cost_types' => 'Menyimpan master biaya tambahan.',
        'bookings' => 'Menyimpan transaksi utama pesanan jasa.',
        'booking_status_histories' => 'Menyimpan riwayat setiap perubahan status booking.',
        'schedules' => 'Menyimpan jadwal survei dan aktivitas lapangan.',
        'surveys' => 'Menyimpan hasil pemeriksaan kondisi lokasi.',
        'survey_photos' => 'Menyimpan foto before, progress, dan after.',
        'estimates' => 'Menyimpan ringkasan estimasi biaya.',
        'estimate_items' => 'Menyimpan rincian material, tenaga kerja, dan biaya tambahan.',
        'estimate_revisions' => 'Menyimpan ringkasan perubahan biaya.',
        'estimate_revision_items' => 'Menyimpan item tambahan pada revisi.',
        'work_progress' => 'Menyimpan pembaruan progres pekerjaan.',
        'final_invoices' => 'Menyimpan tagihan final atau Survey Fee.',
        'final_invoice_items' => 'Menyimpan rincian item invoice.',
        'payments' => 'Menyimpan bukti dan status pembayaran.',
        'notifications' => 'Menyimpan pemberitahuan untuk pengguna atau role.',
        'activity_logs' => 'Menyimpan catatan aktivitas penting.',
        'settings' => 'Menyimpan konfigurasi aplikasi dan perusahaan.',
        'reviews' => 'Menyimpan rating serta komentar Customer.',
    ];
}

function convertHtmlToDocx(string $folder, string $filename, string $html): string
{
    $tempDir = sys_get_temp_dir() . '/abi-taman-bab3-docs';
    if (!is_dir($tempDir) && !mkdir($tempDir, 0775, true) && !is_dir($tempDir)) {
        throw new RuntimeException("Tidak dapat membuat direktori sementara: {$tempDir}");
    }
    $htmlPath = $tempDir . '/' . $filename . '.html';
    file_put_contents($htmlPath, $html);

    $profileDir = $tempDir . '/lo-profile-' . bin2hex(random_bytes(6));
    if (!mkdir($profileDir, 0775, true) && !is_dir($profileDir)) {
        throw new RuntimeException("Tidak dapat membuat profil LibreOffice: {$profileDir}");
    }
    $profileUri = 'file://' . $profileDir;
    $command = 'libreoffice -env:UserInstallation=' . escapeshellarg($profileUri)
        . ' --headless --convert-to ' . escapeshellarg('docx:Office Open XML Text')
        . ' --outdir ' . escapeshellarg($folder) . ' ' . escapeshellarg($htmlPath) . ' 2>&1';
    exec($command, $output, $exitCode);
    $docxPath = $folder . '/' . $filename . '.docx';
    if ($exitCode !== 0 || !is_file($docxPath)) {
        throw new RuntimeException(
            "Konversi DOCX gagal untuk {$filename}: " . implode("\n", $output)
        );
    }
    normalizeDocxTableWidths($docxPath);
    return $docxPath;
}

function normalizeDocxTableWidths(string $docxPath): void
{
    $zip = new ZipArchive();
    if ($zip->open($docxPath) !== true) {
        throw new RuntimeException("DOCX tidak dapat dibuka untuk normalisasi tabel: {$docxPath}");
    }
    $xml = $zip->getFromName('word/document.xml');
    if ($xml === false) {
        $zip->close();
        throw new RuntimeException("word/document.xml tidak ditemukan: {$docxPath}");
    }

    $dom = new DOMDocument();
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = false;
    if (!$dom->loadXML($xml)) {
        $zip->close();
        throw new RuntimeException("XML DOCX tidak valid: {$docxPath}");
    }
    $xpath = new DOMXPath($dom);
    $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
    $wordNs = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    $pageSize = $xpath->query('(//w:sectPr/w:pgSz)[last()]')->item(0);
    $pageMargin = $xpath->query('(//w:sectPr/w:pgMar)[last()]')->item(0);
    if (!$pageSize instanceof DOMElement || !$pageMargin instanceof DOMElement) {
        $zip->close();
        return;
    }
    $pageWidth = (int) $pageSize->getAttributeNS($wordNs, 'w');
    $leftMargin = (int) $pageMargin->getAttributeNS($wordNs, 'left');
    $rightMargin = (int) $pageMargin->getAttributeNS($wordNs, 'right');
    $targetWidth = max(1000, $pageWidth - $leftMargin - $rightMargin - 160);

    foreach ($xpath->query('//w:tbl') as $table) {
        if (!$table instanceof DOMElement) {
            continue;
        }
        $tableWidthNode = $xpath->query('./w:tblPr/w:tblW', $table)->item(0);
        if (!$tableWidthNode instanceof DOMElement) {
            continue;
        }
        $currentWidth = (int) $tableWidthNode->getAttributeNS($wordNs, 'w');
        if ($currentWidth <= 0 || $currentWidth <= $targetWidth) {
            continue;
        }
        $scale = $targetWidth / $currentWidth;
        $tableWidthNode->setAttributeNS($wordNs, 'w:w', (string) $targetWidth);
        foreach ($xpath->query('./w:tblGrid/w:gridCol | .//w:tcPr/w:tcW', $table) as $widthNode) {
            if (!$widthNode instanceof DOMElement) {
                continue;
            }
            $oldWidth = (int) $widthNode->getAttributeNS($wordNs, 'w');
            if ($oldWidth > 0) {
                $widthNode->setAttributeNS($wordNs, 'w:w', (string) max(120, (int) round($oldWidth * $scale)));
            }
        }
        $gridColumns = $xpath->query('./w:tblGrid/w:gridCol', $table);
        $firstHeader = trim((string) $xpath->evaluate('string(./w:tr[1]/w:tc[1])', $table));
        $hasNumberColumn = $firstHeader === 'No.';
        $ratios = match (true) {
            !$hasNumberColumn && $gridColumns->length === 3 => [0.25, 0.20, 0.55],
            !$hasNumberColumn && $gridColumns->length === 4 => [0.20, 0.20, 0.18, 0.42],
            $hasNumberColumn && $gridColumns->length === 4 => [0.12, 0.22, 0.20, 0.46],
            $hasNumberColumn && $gridColumns->length === 5 => [0.12, 0.18, 0.18, 0.18, 0.34],
            default => [],
        };
        if ($ratios !== []) {
            $columnWidths = [];
            foreach ($ratios as $ratioIndex => $ratio) {
                $columnWidths[$ratioIndex] = (int) round($targetWidth * $ratio);
                $gridColumn = $gridColumns->item($ratioIndex);
                if ($gridColumn instanceof DOMElement) {
                    $gridColumn->setAttributeNS($wordNs, 'w:w', (string) $columnWidths[$ratioIndex]);
                }
            }
            foreach ($xpath->query('./w:tr', $table) as $row) {
                foreach ($xpath->query('./w:tc', $row) as $columnIndex => $cell) {
                    if (!isset($columnWidths[$columnIndex]) || !$cell instanceof DOMElement) {
                        continue;
                    }
                    $cellWidth = $xpath->query('./w:tcPr/w:tcW', $cell)->item(0);
                    if ($cellWidth instanceof DOMElement) {
                        $cellWidth->setAttributeNS($wordNs, 'w:w', (string) $columnWidths[$columnIndex]);
                    }
                    if ($columnIndex === 0 && $hasNumberColumn) {
                        $cellProperties = $xpath->query('./w:tcPr', $cell)->item(0);
                        $noWrap = $cellProperties instanceof DOMElement
                            ? $xpath->query('./w:noWrap', $cellProperties)->item(0)
                            : null;
                        if ($cellProperties instanceof DOMElement && !($noWrap instanceof DOMElement)) {
                            $cellProperties->appendChild($dom->createElementNS($wordNs, 'w:noWrap'));
                        }
                        foreach ($xpath->query('./w:p/w:pPr/w:jc', $cell) as $alignment) {
                            if ($alignment instanceof DOMElement) {
                                $alignment->setAttributeNS($wordNs, 'w:val', 'center');
                            }
                        }
                    }
                }
            }
        }
        foreach ($xpath->query('.//w:p/w:pPr/w:spacing', $table) as $spacing) {
            if ($spacing instanceof DOMElement) {
                $spacing->setAttributeNS($wordNs, 'w:before', '0');
                $spacing->setAttributeNS($wordNs, 'w:after', '0');
            }
        }
        foreach ($xpath->query('.//w:r', $table) as $run) {
            if (!$run instanceof DOMElement) {
                continue;
            }
            $runProperties = $xpath->query('./w:rPr', $run)->item(0);
            if (!$runProperties instanceof DOMElement) {
                $runProperties = $dom->createElementNS($wordNs, 'w:rPr');
                $run->insertBefore($runProperties, $run->firstChild);
            }
            foreach (['sz', 'szCs'] as $sizeTag) {
                $sizeNode = $xpath->query('./w:' . $sizeTag, $runProperties)->item(0);
                if (!$sizeNode instanceof DOMElement) {
                    $sizeNode = $dom->createElementNS($wordNs, 'w:' . $sizeTag);
                    $runProperties->appendChild($sizeNode);
                }
                $sizeNode->setAttributeNS($wordNs, 'w:val', '18');
            }
        }
    }

    $updated = $dom->saveXML();
    if ($updated === false || !$zip->addFromString('word/document.xml', $updated)) {
        $zip->close();
        throw new RuntimeException("Gagal menyimpan normalisasi tabel: {$docxPath}");
    }
    $zip->close();
}

$documents = [
    ['analisis-sistem-usulan', 'Penjelasan Analisis Sistem Usulan', proposedSystemDocument()],
    ['arsitektur', 'Penjelasan Arsitektur Sistem', architectureDocument()],
    ['use-case', 'Penjelasan Use Case Diagram', useCaseDocument()],
    ['activity-diagram', 'Penjelasan Activity Diagram', activityDocument()],
    ['sequence-diagram', 'Penjelasan Sequence Diagram', sequenceDocument()],
    ['class-diagram', 'Penjelasan Class Diagram', classDocument()],
    ['erd', 'Penjelasan Entity Relationship Diagram', erdDocument()],
    ['transformasi-erd-lrs', 'Penjelasan Transformasi ERD ke LRS', transformationDocument()],
    ['lrs', 'Penjelasan Logical Record Structure', lrsDocument()],
];

foreach ($documents as [$folderName, $filename, $html]) {
    $folder = DOC_ROOT . '/' . $folderName;
    if (!is_dir($folder)) {
        throw new RuntimeException("Folder diagram tidak ditemukan: {$folder}");
    }
    $docx = convertHtmlToDocx($folder, $filename, $html);
    echo str_replace(realpath(DOC_ROOT) . '/', '', realpath($docx)) . "\n";
}
