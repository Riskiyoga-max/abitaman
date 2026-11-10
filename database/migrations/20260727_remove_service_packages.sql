-- Fitur Paket Layanan dipensiunkan permanen.
-- Jalankan setelah backup database. Migrasi ini aman untuk schema lama ABI Taman.

USE abi_taman;

DELIMITER //
DROP PROCEDURE IF EXISTS drop_fk_for_column//
CREATE PROCEDURE drop_fk_for_column(IN table_name_arg VARCHAR(64), IN column_name_arg VARCHAR(64))
BEGIN
    DECLARE constraint_name_value VARCHAR(64);
    SELECT k.CONSTRAINT_NAME INTO constraint_name_value
    FROM information_schema.KEY_COLUMN_USAGE k
    WHERE k.CONSTRAINT_SCHEMA = DATABASE()
      AND k.TABLE_NAME = table_name_arg
      AND k.COLUMN_NAME = column_name_arg
      AND k.REFERENCED_TABLE_NAME IS NOT NULL
    LIMIT 1;

    IF constraint_name_value IS NOT NULL THEN
        SET @drop_fk_sql = CONCAT(
            'ALTER TABLE `', table_name_arg,
            '` DROP FOREIGN KEY `', constraint_name_value, '`'
        );
        PREPARE drop_fk_statement FROM @drop_fk_sql;
        EXECUTE drop_fk_statement;
        DEALLOCATE PREPARE drop_fk_statement;
    END IF;
END//
DELIMITER ;

CALL drop_fk_for_column('bookings', 'package_id');
CALL drop_fk_for_column('surveys', 'recommended_package_id');
CALL drop_fk_for_column('estimates', 'package_id');
DROP PROCEDURE drop_fk_for_column;

ALTER TABLE bookings DROP COLUMN IF EXISTS package_id;
ALTER TABLE surveys DROP COLUMN IF EXISTS recommended_package_id;
ALTER TABLE estimates
    DROP COLUMN IF EXISTS package_id,
    DROP COLUMN IF EXISTS package_price_total;

ALTER TABLE estimate_items
    MODIFY item_type ENUM('material','labor','additional') NOT NULL;
ALTER TABLE final_invoice_items
    MODIFY item_type ENUM('material','labor','additional','revision') NOT NULL;

DROP TABLE IF EXISTS service_packages;
