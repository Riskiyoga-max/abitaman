<?php
/**
 * Database singleton using PDO.
 * Reads credentials from config/app.php (which loads .env).
 */

function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $host   = $_ENV['DB_HOST'];
        $port   = $_ENV['DB_PORT'];
        $name   = $_ENV['DB_DATABASE'];
        $user   = $_ENV['DB_USERNAME'];
        $pass   = $_ENV['DB_PASSWORD'];
        $socket = trim($_ENV['DB_SOCKET'] ?? '');

        if ($socket !== '') {
            $dsn = "mysql:unix_socket={$socket};dbname={$name};charset=utf8mb4";
        } else {
            $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
        }

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        $pdo = new PDO($dsn, $user, $pass, $options);
    }

    return $pdo;
}

/**
 * Run a query and return all rows.
 */
function db_query(string $sql, array $params = []): array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Run a query and return the first row.
 */
function db_first(string $sql, array $params = []): ?array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Run an INSERT/UPDATE/DELETE and return affected rows.
 */
function db_execute(string $sql, array $params = []): int
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}

/**
 * Run an INSERT and return the last inserted ID.
 */
function db_insert(string $sql, array $params = []): string|false
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return db()->lastInsertId();
}

/**
 * Get a system setting value by key.
 */
function setting(string $key, string $default = ''): string
{
    $row = db_first('SELECT setting_value FROM settings WHERE setting_key = ?', [$key]);
    return $row ? $row['setting_value'] : $default;
}

function setting_set(string $key, string $value): void
{
    db_execute(
        'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
        [$key, $value]
    );
}

/**
 * Generate the next auto-code for a table.
 * e.g. RSV-2026-001, PLG-001, PRJ-2026-001
 *
 * Identifiers (table, column) are validated against a strict whitelist
 * to prevent any possibility of identifier injection.
 */
function next_code(string $table, string $column, string $prefix, bool $withYear = true): string
{
    static $allowed = [
        'bookings'           => 'booking_code',
        'final_invoices'     => 'invoice_code',
        'schedules'          => 'schedule_code',
        'estimates'          => 'estimate_code',
        'estimate_revisions' => 'revision_code',
        'services'           => 'service_code',
        'materials'          => 'material_code',
    ];

    if (!isset($allowed[$table]) || $allowed[$table] !== $column) {
        throw new InvalidArgumentException("next_code: kombinasi table/column tidak diizinkan ({$table}.{$column}).");
    }

    $year = date('Y');
    if ($withYear) {
        $like = $prefix . $year . '-%';
        $row = db_first(
            "SELECT {$column} FROM {$table} WHERE {$column} LIKE ? ORDER BY id DESC LIMIT 1",
            [$like]
        );
        if ($row) {
            $num = (int) substr($row[$column], strrpos($row[$column], '-') + 1);
            return $prefix . $year . '-' . str_pad($num + 1, 3, '0', STR_PAD_LEFT);
        }
        return $prefix . $year . '-001';
    }

    $like = $prefix . '%';
    $row = db_first(
        "SELECT {$column} FROM {$table} WHERE {$column} LIKE ? ORDER BY id DESC LIMIT 1",
        [$like]
    );
    if ($row) {
        $num = (int) substr($row[$column], strlen($prefix));
        return $prefix . str_pad($num + 1, 3, '0', STR_PAD_LEFT);
    }
    return $prefix . '001';
}
