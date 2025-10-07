-- إضافة أعمدة التعريب لجدول الخدمات
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists 
FROM information_schema.columns 
WHERE table_schema = DATABASE() 
AND table_name = 'services_cache' 
AND column_name = 'name_ar';

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE services_cache ADD COLUMN name_ar VARCHAR(255) NULL AFTER name', 
    'SELECT "Column name_ar already exists" as message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- إضافة عمود category_ar
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists 
FROM information_schema.columns 
WHERE table_schema = DATABASE() 
AND table_name = 'services_cache' 
AND column_name = 'category_ar';

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE services_cache ADD COLUMN category_ar VARCHAR(160) NULL AFTER category', 
    'SELECT "Column category_ar already exists" as message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- إضافة عمود description_ar
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists 
FROM information_schema.columns 
WHERE table_schema = DATABASE() 
AND table_name = 'services_cache' 
AND column_name = 'description_ar';

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE services_cache ADD COLUMN description_ar TEXT NULL AFTER type', 
    'SELECT "Column description_ar already exists" as message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- إنشاء جدول الترجمات
CREATE TABLE IF NOT EXISTS service_translations (
  service_id INT PRIMARY KEY,
  name_ar VARCHAR(255),
  category_ar VARCHAR(160),
  description_ar TEXT,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (service_id) REFERENCES services_cache(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
