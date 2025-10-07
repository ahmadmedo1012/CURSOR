-- تحسين نظام الخدمات مع الوصف والترجمة
-- Migration: 010_improve_services.sql

-- إضافة عمود الوصف إذا لم يكن موجوداً
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists 
FROM information_schema.columns 
WHERE table_schema = DATABASE() 
AND table_name = 'services_cache' 
AND column_name = 'description';

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE services_cache ADD COLUMN description TEXT NULL', 
    'SELECT "Column description already exists" as message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- إضافة عمود الوصف العربي إذا لم يكن موجوداً
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists 
FROM information_schema.columns 
WHERE table_schema = DATABASE() 
AND table_name = 'services_cache' 
AND column_name = 'description_ar';

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE services_cache ADD COLUMN description_ar TEXT NULL', 
    'SELECT "Column description_ar already exists" as message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- إضافة عمود التصنيف المفصل إذا لم يكن موجوداً
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists 
FROM information_schema.columns 
WHERE table_schema = DATABASE() 
AND table_name = 'services_cache' 
AND column_name = 'subcategory';

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE services_cache ADD COLUMN subcategory VARCHAR(100) NULL', 
    'SELECT "Column subcategory already exists" as message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- إضافة عمود الترتيب إذا لم يكن موجوداً
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists 
FROM information_schema.columns 
WHERE table_schema = DATABASE() 
AND table_name = 'services_cache' 
AND column_name = 'sort_order';

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE services_cache ADD COLUMN sort_order INT DEFAULT 0', 
    'SELECT "Column sort_order already exists" as message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- إضافة عمود عدد الطلبات إذا لم يكن موجوداً
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists 
FROM information_schema.columns 
WHERE table_schema = DATABASE() 
AND table_name = 'services_cache' 
AND column_name = 'orders_count';

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE services_cache ADD COLUMN orders_count INT DEFAULT 0', 
    'SELECT "Column orders_count already exists" as message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- إضافة فهارس للبحث السريع
SET @index_exists = 0;
SELECT COUNT(*) INTO @index_exists 
FROM information_schema.statistics 
WHERE table_schema = DATABASE() 
AND table_name = 'services_cache' 
AND index_name = 'idx_services_subcategory';

SET @sql = IF(@index_exists = 0, 
    'ALTER TABLE services_cache ADD INDEX idx_services_subcategory (subcategory)', 
    'SELECT "Index idx_services_subcategory already exists" as message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @index_exists = 0;
SELECT COUNT(*) INTO @index_exists 
FROM information_schema.statistics 
WHERE table_schema = DATABASE() 
AND table_name = 'services_cache' 
AND index_name = 'idx_services_sort_order';

SET @sql = IF(@index_exists = 0, 
    'ALTER TABLE services_cache ADD INDEX idx_services_sort_order (sort_order)', 
    'SELECT "Index idx_services_sort_order already exists" as message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @index_exists = 0;
SELECT COUNT(*) INTO @index_exists 
FROM information_schema.statistics 
WHERE table_schema = DATABASE() 
AND table_name = 'services_cache' 
AND index_name = 'idx_services_orders_count';

SET @sql = IF(@index_exists = 0, 
    'ALTER TABLE services_cache ADD INDEX idx_services_orders_count (orders_count)', 
    'SELECT "Index idx_services_orders_count already exists" as message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

