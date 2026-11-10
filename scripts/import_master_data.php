<?php
declare(strict_types=1);

require_once __DIR__ . '/../data/session_helper.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Importer hanya dapat dijalankan melalui CLI.\n");
    exit(1);
}

final class MasterWorkbookImporter
{
    private array $warnings = [];
    private array $normalizations = [];
    private array $summary = [];
    private bool $apply;
    private string $workbookPath;
    private string $sourceRoot;

    public function __construct(string $workbookPath, bool $apply)
    {
        $realPath = realpath($workbookPath);
        if ($realPath === false || !is_file($realPath)) {
            throw new RuntimeException("Workbook tidak ditemukan: {$workbookPath}");
        }
        $this->workbookPath = $realPath;
        $this->sourceRoot = dirname($realPath);
        $this->apply = $apply;
    }

    public function run(): void
    {
        $sheets = $this->readWorkbook();
        $required = [
            '01_Pengaturan',
            '02_Kategori_Layanan',
            '03_Layanan',
            '04_Material',
            '05_Biaya_Tambahan',
            '06_Harga_Tukang_Opsional',
        ];
        foreach ($required as $sheetName) {
            if (!isset($sheets[$sheetName])) {
                throw new RuntimeException("Sheet wajib tidak ditemukan: {$sheetName}");
            }
        }

        $data = $this->normalizeData($sheets);
        $this->validate($data);

        if ($this->apply) {
            db()->beginTransaction();
            try {
                $this->persist($data);
                db()->commit();
            } catch (Throwable $exception) {
                if (db()->inTransaction()) {
                    db()->rollBack();
                }
                throw $exception;
            }
        } else {
            $this->summary = [
                'pengaturan' => count($data['settings']),
                'kategori' => count($data['categories']),
                'layanan' => count($data['services']),
                'material' => count($data['materials']),
                'biaya_tambahan' => count($data['additional_costs']),
                'tarif_tenaga_kerja' => count($data['labor_rates']),
            ];
        }

        $this->printReport();
    }

    private function readWorkbook(): array
    {
        $zip = new ZipArchive();
        if ($zip->open($this->workbookPath) !== true) {
            throw new RuntimeException('Workbook tidak dapat dibuka sebagai XLSX.');
        }

        $sharedStrings = [];
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXml !== false) {
            $document = new DOMDocument();
            $document->loadXML($sharedXml);
            $xpath = new DOMXPath($document);
            $xpath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            foreach ($xpath->query('//x:si') as $stringNode) {
                $parts = [];
                foreach ($xpath->query('.//x:t', $stringNode) as $textNode) {
                    $parts[] = $textNode->textContent;
                }
                $sharedStrings[] = implode('', $parts);
            }
        }

        $workbookXml = $zip->getFromName('xl/workbook.xml');
        $relationsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($workbookXml === false || $relationsXml === false) {
            throw new RuntimeException('Struktur workbook tidak lengkap.');
        }

        $relationDocument = new DOMDocument();
        $relationDocument->loadXML($relationsXml);
        $relationXpath = new DOMXPath($relationDocument);
        $relationXpath->registerNamespace('r', 'http://schemas.openxmlformats.org/package/2006/relationships');
        $relations = [];
        foreach ($relationXpath->query('//r:Relationship') as $relation) {
            $relations[$relation->getAttribute('Id')] = 'xl/' . ltrim($relation->getAttribute('Target'), '/');
        }

        $workbookDocument = new DOMDocument();
        $workbookDocument->loadXML($workbookXml);
        $workbookXpath = new DOMXPath($workbookDocument);
        $workbookXpath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $workbookXpath->registerNamespace('rel', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');

        $sheets = [];
        foreach ($workbookXpath->query('//x:sheet') as $sheetNode) {
            $name = $sheetNode->getAttribute('name');
            $relationId = $sheetNode->getAttributeNS(
                'http://schemas.openxmlformats.org/officeDocument/2006/relationships',
                'id'
            );
            $target = $relations[$relationId] ?? '';
            $sheetXml = $target !== '' ? $zip->getFromName($target) : false;
            if ($sheetXml === false) {
                throw new RuntimeException("XML sheet tidak ditemukan: {$name}");
            }
            $sheets[$name] = $this->readSheet($sheetXml, $sharedStrings);
        }
        $zip->close();

        return $sheets;
    }

    private function readSheet(string $xml, array $sharedStrings): array
    {
        $document = new DOMDocument();
        $document->loadXML($xml);
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $rows = [];
        foreach ($xpath->query('//x:sheetData/x:row') as $rowNode) {
            $row = [];
            foreach ($xpath->query('./x:c', $rowNode) as $cell) {
                $reference = $cell->getAttribute('r');
                preg_match('/^[A-Z]+/', $reference, $match);
                $columnIndex = $this->columnIndex($match[0] ?? 'A');
                $type = $cell->getAttribute('t');
                $valueNode = $xpath->query('./x:v', $cell)->item(0);
                $value = $valueNode?->textContent ?? '';
                if ($type === 's') {
                    $value = $sharedStrings[(int) $value] ?? '';
                } elseif ($type === 'inlineStr') {
                    $textParts = [];
                    foreach ($xpath->query('.//x:t', $cell) as $textNode) {
                        $textParts[] = $textNode->textContent;
                    }
                    $value = implode('', $textParts);
                } elseif ($type === 'b') {
                    $value = $value === '1';
                } elseif ($value !== '' && is_numeric($value)) {
                    $value = str_contains($value, '.') ? (float) $value : (int) $value;
                }
                $row[$columnIndex] = $value;
            }
            if ($row) {
                ksort($row);
                $maxIndex = max(array_keys($row));
                $rows[] = array_replace(array_fill(0, $maxIndex + 1, null), $row);
            }
        }
        return $rows;
    }

    private function columnIndex(string $letters): int
    {
        $index = 0;
        foreach (str_split($letters) as $letter) {
            $index = ($index * 26) + (ord($letter) - 64);
        }
        return max(0, $index - 1);
    }

    private function normalizeData(array $sheets): array
    {
        $data = [
            'settings' => $this->rowsAsRecords($sheets['01_Pengaturan']),
            'categories' => $this->rowsAsRecords($sheets['02_Kategori_Layanan']),
            'services' => $this->rowsAsRecords($sheets['03_Layanan'], ['tipe' => 'nama_layanan']),
            'materials' => $this->rowsAsRecords($sheets['04_Material']),
            'additional_costs' => $this->rowsAsRecords($sheets['05_Biaya_Tambahan']),
            'labor_rates' => $this->rowsAsRecords($sheets['06_Harga_Tukang_Opsional']),
        ];

        foreach ($data as &$records) {
            foreach ($records as &$record) {
                foreach ($record as $key => $value) {
                    if (is_string($value)) {
                        $record[$key] = $this->cleanText($value);
                    }
                }
            }
            unset($record);
        }
        unset($records);

        foreach ($data['services'] as &$service) {
            $serviceName = mb_strtolower((string) ($service['nama_layanan'] ?? ''));
            $categoryName = mb_strtolower((string) ($service['kategori'] ?? ''));

            if (
                $categoryName === 'pemasangan dekorasi taman'
                && str_starts_with($serviceName, 'menambah nilai estetika taman')
            ) {
                $service['nama_layanan'] = 'Pemasangan Dekorasi Taman';
                $this->normalizations[] = 'Nama layanan dekorasi dinormalisasi menjadi Pemasangan Dekorasi Taman.';
            }

            if ($serviceName === 'perapihan, pembersihan dan pengolahan tanah') {
                $service['deskripsi_singkat'] = 'Persiapan lahan agar bersih, rapi, dan siap ditanami.';
                $this->normalizations[] = 'Deskripsi singkat layanan persiapan lahan diperbaiki.';
            }
        }
        unset($service);

        $existingCategories = [];
        foreach ($data['categories'] as $category) {
            $existingCategories[mb_strtolower($category['nama_kategori'])] = true;
        }
        foreach ($data['services'] as $service) {
            $categoryName = (string) ($service['kategori'] ?? '');
            $categoryKey = mb_strtolower($categoryName);
            if ($categoryName !== '' && !isset($existingCategories[$categoryKey])) {
                $data['categories'][] = [
                    'nama_kategori' => $categoryName,
                    'deskripsi' => "Layanan {$categoryName} ABI Taman.",
                    'nama_file_gambar' => '',
                    'status' => 'active',
                    'catatan' => 'Kategori dibuat dari data layanan final.',
                    '_row' => 'otomatis',
                ];
                $existingCategories[$categoryKey] = true;
                $this->normalizations[] = "Kategori '{$categoryName}' ditambahkan dari sheet Layanan.";
            }
        }

        foreach ($data['materials'] as &$material) {
            $name = mb_strtolower((string) ($material['nama_material'] ?? ''));
            if ($name === 'pinus') {
                $material['harga_per_satuan'] = 600000;
                $material['qty_default_per_10m2'] = 9;
                $this->normalizations[] = 'Pinus: harga dinormalisasi menjadi 600000 dan qty menjadi 9.';
            }
            $status = (string) ($material['status'] ?? '');
            if (preg_match('/^active\+F\d+$/i', $status)) {
                $material['status'] = 'active';
                $this->normalizations[] = "{$material['nama_material']}: status '{$status}' dinormalisasi menjadi active.";
            }
        }
        unset($material);

        foreach ($data['additional_costs'] as &$cost) {
            if (mb_strtolower((string) ($cost['nama_biaya'] ?? '')) === 'pembersihan lahan dan pembongkaran lahan') {
                $cost['deskripsi'] = $cost['deskripsi'] ?: 'Pembersihan dan pembongkaran area sebelum pekerjaan taman.';
                $cost['harga_default'] = 0;
                $cost['satuan'] = 'item';
                $cost['status'] = 'active';
                $this->normalizations[] = 'Biaya pembersihan lahan dilengkapi dengan harga 0, satuan item, dan status active.';
            }
        }
        unset($cost);

        return $data;
    }

    private function rowsAsRecords(array $rows, array $headerAliases = []): array
    {
        if (!$rows) {
            return [];
        }
        $headers = array_map(
            static fn ($header): string => trim((string) ($headerAliases[trim((string) $header)] ?? $header)),
            array_shift($rows)
        );
        $records = [];
        foreach ($rows as $rowIndex => $row) {
            if (!array_filter($row, static fn ($value): bool => $value !== null && $value !== '')) {
                continue;
            }
            $record = [];
            foreach ($headers as $columnIndex => $header) {
                if ($header === '') {
                    continue;
                }
                $record[$header] = $row[$columnIndex] ?? null;
            }
            $record['_row'] = $rowIndex + 2;
            $records[] = $record;
        }
        return $records;
    }

    private function cleanText(string $value): string
    {
        $value = trim((string) preg_replace('/\s+/u', ' ', $value));
        $replacements = [
            '/^angat disarankan/u' => 'Sangat disarankan',
            '/^aman dengan elemen air/u' => 'Taman dengan elemen air',
            '/^ohon berbunga/u' => 'Pohon berbunga',
            '/^upuk organik/u' => 'Pupuk organik',
            '/^anaman semak/u' => 'Tanaman semak',
        ];
        foreach ($replacements as $pattern => $replacement) {
            if (preg_match($pattern, $value)) {
                $cleaned = preg_replace($pattern, $replacement, $value);
                $this->normalizations[] = "Teks '{$value}' dinormalisasi menjadi '{$cleaned}'.";
                return (string) $cleaned;
            }
        }
        return $value;
    }

    private function validate(array $data): void
    {
        $errors = [];
        $statusValues = ['active', 'inactive'];
        $requiredFields = [
            'categories' => ['nama_kategori', 'status'],
            'services' => ['kategori', 'nama_layanan', 'durasi_per_10m2_hari', 'status'],
            'materials' => ['nama_material', 'satuan', 'harga_per_satuan', 'qty_default_per_10m2', 'status'],
            'additional_costs' => ['nama_biaya', 'harga_default', 'satuan', 'status'],
            'labor_rates' => ['nama_role_tukang', 'upah_per_hari', 'status'],
        ];
        foreach ($requiredFields as $group => $fields) {
            foreach ($data[$group] as $record) {
                foreach ($fields as $field) {
                    if (!isset($record[$field]) || $record[$field] === '') {
                        $errors[] = "{$group} baris {$record['_row']}: {$field} wajib diisi.";
                    }
                }
                if (isset($record['status']) && !in_array($record['status'], $statusValues, true)) {
                    $errors[] = "{$group} baris {$record['_row']}: status harus active atau inactive.";
                }
            }
        }
        foreach ($data['settings'] as $settingRow) {
            if (($settingRow['wajib'] ?? '') === 'YA' && (string) ($settingRow['contoh_input'] ?? '') === '') {
                $errors[] = "Pengaturan baris {$settingRow['_row']}: nilai wajib kosong.";
            }
        }

        $categoryNames = array_map(
            static fn (array $row): string => mb_strtolower((string) $row['nama_kategori']),
            $data['categories']
        );
        foreach ($data['services'] as $service) {
            if (!in_array(mb_strtolower((string) $service['kategori']), $categoryNames, true)) {
                $errors[] = "Layanan baris {$service['_row']}: kategori tidak ditemukan.";
            }
        }
        $this->assertUnique($data['categories'], 'nama_kategori', 'kategori', $errors);
        $this->assertUnique($data['services'], 'nama_layanan', 'layanan', $errors);
        $this->assertUnique($data['materials'], 'nama_material', 'material', $errors);

        foreach ([
            ['categories', 'nama_file_gambar', 'Gambar Taman'],
            ['services', 'nama_file_gambar', 'Gambar Taman'],
            ['materials', 'nama_file_gambar', 'Gambar material'],
        ] as [$group, $field, $folder]) {
            foreach ($data[$group] as $record) {
                $filename = (string) ($record[$field] ?? '');
                $sourceImage = $filename !== '' ? $this->findSourceImage($folder, $filename) : null;
                if ($filename !== '' && $sourceImage === null) {
                    $this->warnings[] = "{$group} baris {$record['_row']}: gambar '{$filename}' tidak ditemukan; placeholder akan digunakan.";
                } elseif ($sourceImage !== null && @getimagesize($sourceImage) === false) {
                    $this->warnings[] = "{$group} baris {$record['_row']}: file '{$filename}' bukan gambar valid; placeholder akan digunakan.";
                }
            }
        }

        if ($errors) {
            throw new RuntimeException("Validasi gagal:\n- " . implode("\n- ", $errors));
        }
    }

    private function assertUnique(array $rows, string $field, string $label, array &$errors): void
    {
        $seen = [];
        foreach ($rows as $record) {
            $key = mb_strtolower((string) ($record[$field] ?? ''));
            if ($key !== '' && isset($seen[$key])) {
                $errors[] = ucfirst($label) . " duplikat pada baris {$seen[$key]} dan {$record['_row']}: {$record[$field]}.";
            }
            $seen[$key] = $record['_row'];
        }
    }

    private function persist(array $data): void
    {
        // Workbook final bersifat authoritative. Data lama tetap disimpan, tetapi
        // master yang tidak ada di workbook dinonaktifkan agar tidak muncul di UI.
        db_execute("UPDATE service_categories SET status='inactive'");
        db_execute("UPDATE services SET status='inactive'");
        db_execute("UPDATE materials SET status='inactive'");
        db_execute("UPDATE additional_cost_types SET status='inactive'");
        db_execute("UPDATE labor_rates SET status='inactive'");

        foreach ($data['settings'] as $row) {
            db_execute(
                'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)',
                [$row['setting_key'], (string) $row['contoh_input']]
            );
            $this->increment('pengaturan');
        }

        $categoryIds = [];
        foreach ($data['categories'] as $row) {
            $slug = slugify($row['nama_kategori']);
            $image = $this->importImage('Gambar Taman', (string) ($row['nama_file_gambar'] ?? ''), 'services');
            $existing = db_first('SELECT id, image FROM service_categories WHERE slug=?', [$slug]);
            if ($existing) {
                db_execute(
                    'UPDATE service_categories SET name=?, description=?, image=?, status=? WHERE id=?',
                    [$row['nama_kategori'], $row['deskripsi'] ?: null, $image ?: $existing['image'], $row['status'], $existing['id']]
                );
                $categoryIds[mb_strtolower($row['nama_kategori'])] = (int) $existing['id'];
            } else {
                $categoryIds[mb_strtolower($row['nama_kategori'])] = (int) db_insert(
                    'INSERT INTO service_categories (name, slug, description, image, status) VALUES (?, ?, ?, ?, ?)',
                    [$row['nama_kategori'], $slug, $row['deskripsi'] ?: null, $image, $row['status']]
                );
            }
            $this->increment('kategori');
        }

        foreach ($data['services'] as $row) {
            $slug = slugify($row['nama_layanan']);
            $image = $this->importImage('Gambar Taman', (string) ($row['nama_file_gambar'] ?? ''), 'services');
            $existing = db_first('SELECT id, image FROM services WHERE slug=?', [$slug]);
            if (!$existing && $slug === 'pemasangan-dekorasi-taman') {
                $existing = db_first(
                    'SELECT id, image FROM services
                     WHERE slug=? LIMIT 1',
                    ['menambah-nilai-estetika-taman-dengan-berbagai-aksesori']
                );
            }
            $categoryId = $categoryIds[mb_strtolower($row['kategori'])];
            if ($existing) {
                db_execute(
                    'UPDATE services SET category_id=?, name=?, slug=?, short_description=?, description=?,
                     base_duration_days_per_10m=?, image=?, status=? WHERE id=?',
                    [$categoryId, $row['nama_layanan'], $slug, $row['deskripsi_singkat'] ?: null, $row['deskripsi_lengkap'] ?: null,
                     max(1, (int) $row['durasi_per_10m2_hari']), $image ?: $existing['image'], $row['status'], $existing['id']]
                );
            } else {
                $code = next_code('services', 'service_code', 'SRV-', true);
                db_insert(
                    'INSERT INTO services (category_id, service_code, name, slug, short_description, description,
                     base_duration_days_per_10m, image, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [$categoryId, $code, $row['nama_layanan'], $slug, $row['deskripsi_singkat'] ?: null,
                     $row['deskripsi_lengkap'] ?: null, max(1, (int) $row['durasi_per_10m2_hari']), $image, $row['status']]
                );
            }
            $this->increment('layanan');
        }

        foreach ($data['materials'] as $row) {
            $image = $this->importImage('Gambar material', (string) ($row['nama_file_gambar'] ?? ''), 'materials');
            $existing = db_first('SELECT id, image FROM materials WHERE LOWER(name)=LOWER(?) LIMIT 1', [$row['nama_material']]);
            if ($existing) {
                db_execute(
                    'UPDATE materials SET unit=?, price_per_unit=?, default_qty_per_10m=?, image=?, status=? WHERE id=?',
                    [$row['satuan'], max(0, (int) $row['harga_per_satuan']), max(0, (float) $row['qty_default_per_10m2']),
                     $image ?: $existing['image'], $row['status'], $existing['id']]
                );
            } else {
                $code = next_code('materials', 'material_code', 'MAT-', true);
                db_insert(
                    'INSERT INTO materials (material_code, name, unit, price_per_unit, default_qty_per_10m, image, status)
                     VALUES (?, ?, ?, ?, ?, ?, ?)',
                    [$code, $row['nama_material'], $row['satuan'], max(0, (int) $row['harga_per_satuan']),
                     max(0, (float) $row['qty_default_per_10m2']), $image, $row['status']]
                );
            }
            $this->increment('material');
        }

        foreach ($data['additional_costs'] as $row) {
            $existing = db_first('SELECT id FROM additional_cost_types WHERE LOWER(name)=LOWER(?) LIMIT 1', [$row['nama_biaya']]);
            if ($existing) {
                db_execute(
                    'UPDATE additional_cost_types SET description=?, default_price=?, unit=?, status=? WHERE id=?',
                    [$row['deskripsi'] ?: null, max(0, (int) $row['harga_default']), $row['satuan'], $row['status'], $existing['id']]
                );
            } else {
                db_insert(
                    'INSERT INTO additional_cost_types (name, description, default_price, unit, status) VALUES (?, ?, ?, ?, ?)',
                    [$row['nama_biaya'], $row['deskripsi'] ?: null, max(0, (int) $row['harga_default']), $row['satuan'], $row['status']]
                );
            }
            $this->increment('biaya_tambahan');
        }

        foreach ($data['labor_rates'] as $row) {
            $existing = db_first('SELECT id FROM labor_rates WHERE LOWER(name)=LOWER(?) LIMIT 1', [$row['nama_role_tukang']]);
            if ($existing) {
                db_execute(
                    'UPDATE labor_rates SET description=?, daily_rate=?, status=? WHERE id=?',
                    [$row['deskripsi'] ?: null, max(0, (int) $row['upah_per_hari']), $row['status'], $existing['id']]
                );
            } else {
                db_insert(
                    'INSERT INTO labor_rates (name, description, daily_rate, status) VALUES (?, ?, ?, ?)',
                    [$row['nama_role_tukang'], $row['deskripsi'] ?: null, max(0, (int) $row['upah_per_hari']), $row['status']]
                );
            }
            $this->increment('tarif_tenaga_kerja');
        }
    }

    private function importImage(string $sourceFolder, string $filename, string $targetFolder): ?string
    {
        if ($filename === '') {
            return null;
        }
        $source = $this->findSourceImage($sourceFolder, $filename);
        if ($source === null) {
            return null;
        }
        if (@getimagesize($source) === false) {
            $this->normalizations[] = "File '{$filename}' dilewati karena bukan gambar yang valid.";
            return null;
        }
        $safeFilename = basename($filename);
        $targetDirectory = dirname(__DIR__) . "/assets/img/{$targetFolder}";
        if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0775, true) && !is_dir($targetDirectory)) {
            throw new RuntimeException("Folder aset tidak dapat dibuat: {$targetDirectory}");
        }
        $target = $targetDirectory . '/' . $safeFilename;
        if (!copy($source, $target)) {
            throw new RuntimeException("Gambar gagal disalin: {$filename}");
        }
        return "assets/img/{$targetFolder}/{$safeFilename}";
    }

    private function findSourceImage(string $folder, string $filename): ?string
    {
        if ($filename === '') {
            return null;
        }
        $candidate = $this->sourceRoot . '/' . $folder . '/' . basename($filename);
        if (is_file($candidate)) {
            return $candidate;
        }
        foreach (glob($candidate . '*') ?: [] as $matchingFile) {
            if (is_file($matchingFile)) {
                return $matchingFile;
            }
        }
        return null;
    }

    private function increment(string $key): void
    {
        $this->summary[$key] = ($this->summary[$key] ?? 0) + 1;
    }

    private function printReport(): void
    {
        echo $this->apply ? "IMPORT BERHASIL\n" : "DRY RUN BERHASIL — tidak ada data yang diubah\n";
        foreach ($this->summary as $label => $count) {
            echo str_pad(str_replace('_', ' ', ucfirst($label)), 24) . ": {$count}\n";
        }
        echo "Normalisasi             : " . count($this->normalizations) . "\n";
        foreach ($this->normalizations as $message) {
            echo "  ~ {$message}\n";
        }
        echo "Peringatan              : " . count($this->warnings) . "\n";
        foreach ($this->warnings as $warning) {
            echo "  ! {$warning}\n";
        }
    }
}

$arguments = array_slice($argv, 1);
$apply = in_array('--apply', $arguments, true);
$pathArguments = array_values(array_filter($arguments, static fn (string $argument): bool => !str_starts_with($argument, '--')));
$workbookPath = $pathArguments[0] ?? (__DIR__ . '/../dataabitaman/data abi taman exel.xlsx');

try {
    (new MasterWorkbookImporter($workbookPath, $apply))->run();
} catch (Throwable $exception) {
    fwrite(STDERR, "IMPORT GAGAL\n{$exception->getMessage()}\n");
    exit(1);
}
