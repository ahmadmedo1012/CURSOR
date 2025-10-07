CREATE TABLE IF NOT EXISTS service_groups (
  id INT AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(60) UNIQUE,
  title VARCHAR(120) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- إضافة عمود group_slug إذا لم يكن موجوداً
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists 
FROM information_schema.columns 
WHERE table_schema = DATABASE() 
AND table_name = 'services_cache' 
AND column_name = 'group_slug';

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE services_cache ADD COLUMN group_slug VARCHAR(60) NULL', 
    'SELECT "Column group_slug already exists" as message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- إضافة الفهرس إذا لم يكن موجوداً
SET @index_exists = 0;
SELECT COUNT(*) INTO @index_exists 
FROM information_schema.statistics 
WHERE table_schema = DATABASE() 
AND table_name = 'services_cache' 
AND index_name = 'idx_group_slug';

SET @sql = IF(@index_exists = 0, 
    'ALTER TABLE services_cache ADD INDEX idx_group_slug (group_slug)', 
    'SELECT "Index idx_group_slug already exists" as message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
