-- ============================================================
-- ABI Taman Service Commerce System
-- Refactor schema: semi-ecommerce jasa taman
-- Database: abi_taman
-- ============================================================

CREATE DATABASE IF NOT EXISTS abi_taman
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE abi_taman;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS reviews;
DROP TABLE IF EXISTS final_invoice_items;
DROP TABLE IF EXISTS final_invoices;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS work_progress;
DROP TABLE IF EXISTS estimate_revision_items;
DROP TABLE IF EXISTS estimate_revisions;
DROP TABLE IF EXISTS estimate_items;
DROP TABLE IF EXISTS estimates;
DROP TABLE IF EXISTS survey_photos;
DROP TABLE IF EXISTS surveys;
DROP TABLE IF EXISTS schedules;
DROP TABLE IF EXISTS booking_status_histories;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS activity_logs;
DROP TABLE IF EXISTS bookings;
DROP TABLE IF EXISTS additional_cost_types;
DROP TABLE IF EXISTS labor_rates;
DROP TABLE IF EXISTS materials;
DROP TABLE IF EXISTS services;
DROP TABLE IF EXISTS service_categories;
DROP TABLE IF EXISTS settings;
DROP TABLE IF EXISTS users;

DROP TABLE IF EXISTS project_notes;
DROP TABLE IF EXISTS system_settings;
DROP TABLE IF EXISTS cost_estimates;
DROP TABLE IF EXISTS projects;
DROP TABLE IF EXISTS reservations;
DROP TABLE IF EXISTS customers;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(150) NOT NULL,
    email           VARCHAR(150) NOT NULL UNIQUE,
    password        VARCHAR(255) NOT NULL,
    role            ENUM('customer','pekerja_lapangan','staff','boss') NOT NULL,
    phone           VARCHAR(30),
    address         TEXT,
    avatar          VARCHAR(255),
    status          ENUM('active','inactive') NOT NULL DEFAULT 'active',
    last_login_at   DATETIME NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE service_categories (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(150) NOT NULL,
    slug          VARCHAR(170) NOT NULL UNIQUE,
    description   TEXT,
    image         VARCHAR(255),
    status        ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE services (
    id                          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id                 INT UNSIGNED,
    service_code                VARCHAR(30) NOT NULL UNIQUE,
    name                        VARCHAR(180) NOT NULL,
    slug                        VARCHAR(200) NOT NULL UNIQUE,
    short_description           VARCHAR(255),
    description                 TEXT,
    base_price_per_10m          BIGINT UNSIGNED NOT NULL DEFAULT 0,
    base_duration_days_per_10m  INT UNSIGNED NOT NULL DEFAULT 1,
    image                       VARCHAR(255),
    status                      ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at                  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES service_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE materials (
    id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    material_code          VARCHAR(30) NOT NULL UNIQUE,
    name                  VARCHAR(150) NOT NULL,
    unit                  VARCHAR(30) NOT NULL,
    price_per_unit         BIGINT UNSIGNED NOT NULL DEFAULT 0,
    default_qty_per_10m    DECIMAL(12,2) NOT NULL DEFAULT 0,
    image                 VARCHAR(255),
    status                ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE labor_rates (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(150) NOT NULL,
    description   TEXT,
    daily_rate    BIGINT UNSIGNED NOT NULL DEFAULT 0,
    status        ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE additional_cost_types (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(150) NOT NULL,
    description     TEXT,
    default_price   BIGINT UNSIGNED NOT NULL DEFAULT 0,
    unit            VARCHAR(50) NOT NULL DEFAULT 'item',
    status          ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE bookings (
    id                          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_code                VARCHAR(30) NOT NULL UNIQUE,
    customer_id                 INT UNSIGNED NOT NULL,
    service_id                  INT UNSIGNED,
    is_custom_service           TINYINT(1) NOT NULL DEFAULT 0,
    custom_service_description  TEXT,
    requested_survey_date       DATE NOT NULL,
    actual_survey_date          DATE NULL,
    actual_survey_time          TIME NULL,
    location_address            TEXT NOT NULL,
    estimated_area              DECIMAL(12,2),
    actual_area                 DECIMAL(12,2),
    customer_notes              TEXT,
    reference_photo             VARCHAR(255),
    status                      ENUM(
        'Menunggu Konfirmasi',
        'Survey Dijadwalkan',
        'Survey Berlangsung',
        'Survey Selesai',
        'Estimasi Dibuat',
        'Menunggu Persetujuan Customer',
        'Estimasi Disetujui',
        'Estimasi Ditolak',
        'Dibatalkan',
        'Dalam Pengerjaan',
        'Revisi Estimasi',
        'Pekerjaan Selesai',
        'Invoice Final Dibuat',
        'Menunggu Pembayaran',
        'Lunas',
        'Selesai'
    ) NOT NULL DEFAULT 'Menunggu Konfirmasi',
    survey_fee_amount           BIGINT UNSIGNED NOT NULL DEFAULT 100000,
    survey_fee_payment_status   ENUM('Tidak Ada','Belum Dibayar','Sudah Dibayar') NOT NULL DEFAULT 'Tidak Ada',
    assigned_pekerja_lapangan_id    INT UNSIGNED,
    created_by                  INT UNSIGNED,
    created_at                  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_pekerja_lapangan_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE booking_status_histories (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id   INT UNSIGNED NOT NULL,
    old_status   VARCHAR(80),
    new_status   VARCHAR(80) NOT NULL,
    changed_by   INT UNSIGNED,
    note         TEXT,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE schedules (
    id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    schedule_code         VARCHAR(30) NOT NULL UNIQUE,
    booking_id            INT UNSIGNED NOT NULL,
    pekerja_lapangan_id       INT UNSIGNED NOT NULL,
    schedule_type         ENUM('Survey','Pengerjaan','Follow Up','Revisi') NOT NULL DEFAULT 'Survey',
    schedule_date         DATE NOT NULL,
    schedule_time         TIME NOT NULL,
    location              TEXT,
    status                ENUM('Terjadwal','Berlangsung','Selesai','Dijadwalkan Ulang','Dibatalkan') NOT NULL DEFAULT 'Terjadwal',
    notes                 TEXT,
    reschedule_reason     TEXT,
    created_by            INT UNSIGNED,
    created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (pekerja_lapangan_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE surveys (
    id                         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id                 INT UNSIGNED NOT NULL,
    pekerja_lapangan_id            INT UNSIGNED NOT NULL,
    actual_area                DECIMAL(12,2) NOT NULL,
    location_condition         TEXT NOT NULL,
    access_condition           TEXT NOT NULL,
    obstacle_notes             TEXT,
    estimated_work_days        INT UNSIGNED NOT NULL DEFAULT 1,
    estimated_worker_count     INT UNSIGNED NOT NULL DEFAULT 1,
    survey_notes               TEXT,
    created_at                 DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                 DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (pekerja_lapangan_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE survey_photos (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    survey_id      INT UNSIGNED,
    booking_id     INT UNSIGNED NOT NULL,
    photo_path     VARCHAR(255) NOT NULL,
    photo_type     ENUM('before','progress','after') NOT NULL,
    caption        VARCHAR(255),
    uploaded_by    INT UNSIGNED,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (survey_id) REFERENCES surveys(id) ON DELETE CASCADE,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE estimates (
    id                       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    estimate_code             VARCHAR(30) NOT NULL UNIQUE,
    booking_id                INT UNSIGNED NOT NULL,
    survey_id                 INT UNSIGNED,
    created_by                INT UNSIGNED,
    area_size                 DECIMAL(12,2) NOT NULL,
    block_count               INT UNSIGNED NOT NULL,
    material_total            BIGINT UNSIGNED NOT NULL DEFAULT 0,
    labor_total               BIGINT UNSIGNED NOT NULL DEFAULT 0,
    additional_total          BIGINT UNSIGNED NOT NULL DEFAULT 0,
    grand_total               BIGINT UNSIGNED NOT NULL DEFAULT 0,
    estimated_days            INT UNSIGNED NOT NULL DEFAULT 1,
    worker_count              INT UNSIGNED NOT NULL DEFAULT 1,
    status                    ENUM('Draft','Dikirim','Disetujui','Ditolak','Direvisi') NOT NULL DEFAULT 'Draft',
    customer_response_note    TEXT,
    sent_at                   DATETIME NULL,
    approved_at               DATETIME NULL,
    rejected_at               DATETIME NULL,
    created_at                DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (survey_id) REFERENCES surveys(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE estimate_items (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    estimate_id    INT UNSIGNED NOT NULL,
    item_type      ENUM('material','labor','additional') NOT NULL,
    item_name      VARCHAR(180) NOT NULL,
    description    TEXT,
    quantity       DECIMAL(12,2) NOT NULL DEFAULT 1,
    unit           VARCHAR(50),
    unit_price     BIGINT UNSIGNED NOT NULL DEFAULT 0,
    subtotal       BIGINT UNSIGNED NOT NULL DEFAULT 0,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (estimate_id) REFERENCES estimates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE estimate_revisions (
    id                       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    revision_code             VARCHAR(30) NOT NULL UNIQUE,
    estimate_id               INT UNSIGNED NOT NULL,
    booking_id                INT UNSIGNED NOT NULL,
    created_by                INT UNSIGNED,
    reason                    TEXT NOT NULL,
    old_total                 BIGINT UNSIGNED NOT NULL DEFAULT 0,
    additional_total          BIGINT UNSIGNED NOT NULL DEFAULT 0,
    new_total                 BIGINT UNSIGNED NOT NULL DEFAULT 0,
    status                    ENUM('Draft','Dikirim','Disetujui','Ditolak') NOT NULL DEFAULT 'Draft',
    customer_response_note    TEXT,
    created_at                DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (estimate_id) REFERENCES estimates(id) ON DELETE CASCADE,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE estimate_revision_items (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    revision_id     INT UNSIGNED NOT NULL,
    item_name       VARCHAR(180) NOT NULL,
    description     TEXT,
    quantity        DECIMAL(12,2) NOT NULL DEFAULT 1,
    unit            VARCHAR(50),
    unit_price      BIGINT UNSIGNED NOT NULL DEFAULT 0,
    subtotal        BIGINT UNSIGNED NOT NULL DEFAULT 0,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (revision_id) REFERENCES estimate_revisions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE work_progress (
    id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id          INT UNSIGNED NOT NULL,
    pekerja_lapangan_id     INT UNSIGNED NOT NULL,
    progress_percent    TINYINT UNSIGNED NOT NULL DEFAULT 0,
    title              VARCHAR(180) NOT NULL,
    note               TEXT,
    obstacle_note      TEXT,
    created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (pekerja_lapangan_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE final_invoices (
    id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_code           VARCHAR(30) NOT NULL UNIQUE,
    booking_id             INT UNSIGNED NOT NULL,
    estimate_id            INT UNSIGNED,
    created_by             INT UNSIGNED,
    subtotal_estimate      BIGINT UNSIGNED NOT NULL DEFAULT 0,
    revision_total         BIGINT UNSIGNED NOT NULL DEFAULT 0,
    final_total            BIGINT UNSIGNED NOT NULL DEFAULT 0,
    work_days_actual       INT UNSIGNED NOT NULL DEFAULT 1,
    worker_count_actual    INT UNSIGNED NOT NULL DEFAULT 1,
    notes                  TEXT,
    payment_status         ENUM('Belum Dibayar','Lunas') NOT NULL DEFAULT 'Belum Dibayar',
    status                 ENUM('Draft','Dikirim','Lunas') NOT NULL DEFAULT 'Draft',
    created_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (estimate_id) REFERENCES estimates(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE final_invoice_items (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    final_invoice_id    INT UNSIGNED NOT NULL,
    item_type           ENUM('material','labor','additional','revision') NOT NULL,
    item_name           VARCHAR(180) NOT NULL,
    description         TEXT,
    quantity            DECIMAL(12,2) NOT NULL DEFAULT 1,
    unit                VARCHAR(50),
    unit_price          BIGINT UNSIGNED NOT NULL DEFAULT 0,
    subtotal            BIGINT UNSIGNED NOT NULL DEFAULT 0,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (final_invoice_id) REFERENCES final_invoices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE payments (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id         INT UNSIGNED NOT NULL,
    invoice_id         INT UNSIGNED,
    payment_type       ENUM('Survey Fee','Invoice Final') NOT NULL,
    amount             BIGINT UNSIGNED NOT NULL DEFAULT 0,
    payment_status     ENUM('Belum Dibayar','Lunas') NOT NULL DEFAULT 'Belum Dibayar',
    marked_by          INT UNSIGNED,
    paid_at            DATETIME NULL,
    note               TEXT,
    payment_proof      VARCHAR(255) NULL,
    created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (invoice_id) REFERENCES final_invoices(id) ON DELETE SET NULL,
    FOREIGN KEY (marked_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE notifications (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id            INT UNSIGNED,
    role_target        ENUM('customer','pekerja_lapangan','staff','boss'),
    title              VARCHAR(180) NOT NULL,
    message            TEXT,
    reference_type     VARCHAR(80),
    reference_id       INT UNSIGNED,
    is_read            TINYINT(1) NOT NULL DEFAULT 0,
    created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE activity_logs (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id            INT UNSIGNED,
    activity_type      VARCHAR(80) NOT NULL,
    description        TEXT NOT NULL,
    reference_type     VARCHAR(80),
    reference_id       INT UNSIGNED,
    created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE settings (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key     VARCHAR(100) NOT NULL UNIQUE,
    setting_value   TEXT,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE reviews (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id  INT UNSIGNED NOT NULL UNIQUE,
    customer_id INT UNSIGNED NOT NULL,
    rating      TINYINT UNSIGNED NOT NULL CHECK (rating BETWEEN 1 AND 5),
    comment     TEXT,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
