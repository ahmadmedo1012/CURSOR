-- إضافة عمود rate_per_1k_usd إذا لم يكن موجوداً
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists 
FROM information_schema.columns 
WHERE table_schema = DATABASE() 
AND table_name = 'services_cache' 
AND column_name = 'rate_per_1k_usd';

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE services_cache ADD COLUMN rate_per_1k_usd DECIMAL(12,4) NULL AFTER rate_per_1k', 
    'SELECT "Column rate_per_1k_usd already exists" as message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- إضافة عمود rate_per_1k_lyd إذا لم يكن موجوداً
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists 
FROM information_schema.columns 
WHERE table_schema = DATABASE() 
AND table_name = 'services_cache' 
AND column_name = 'rate_per_1k_lyd';

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE services_cache ADD COLUMN rate_per_1k_lyd DECIMAL(12,4) NULL AFTER rate_per_1k_usd', 
    'SELECT "Column rate_per_1k_lyd already exists" as message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
