-- إضافة عمود notes إلى جدول الطلبات
-- Migration: 009_add_notes_to_orders.sql

-- إضافة عمود notes إذا لم يكن موجوداً
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists 
FROM information_schema.columns 
WHERE table_schema = DATABASE() 
AND table_name = 'orders' 
AND column_name = 'notes';

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE orders ADD COLUMN notes TEXT NULL', 
    'SELECT "Column notes already exists" as message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- إضافة عمود provider إذا لم يكن موجوداً
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists 
FROM information_schema.columns 
WHERE table_schema = DATABASE() 
AND table_name = 'orders' 
AND column_name = 'provider';

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE orders ADD COLUMN provider VARCHAR(50) DEFAULT \'peakerr\'', 
    'SELECT "Column provider already exists" as message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- إضافة عمود external_id إذا لم يكن موجوداً
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists 
FROM information_schema.columns 
WHERE table_schema = DATABASE() 
AND table_name = 'orders' 
AND column_name = 'external_id';

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE orders ADD COLUMN external_id VARCHAR(255) NULL', 
    'SELECT "Column external_id already exists" as message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
