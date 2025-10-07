-- إضافة عمود user_id إذا لم يكن موجوداً
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists 
FROM information_schema.columns 
WHERE table_schema = DATABASE() 
AND table_name = 'orders' 
AND column_name = 'user_id';

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE orders ADD COLUMN user_id INT NULL', 
    'SELECT "Column user_id already exists" as message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- إضافة الفهرس إذا لم يكن موجوداً
SET @index_exists = 0;
SELECT COUNT(*) INTO @index_exists 
FROM information_schema.statistics 
WHERE table_schema = DATABASE() 
AND table_name = 'orders' 
AND index_name = 'idx_orders_user';

SET @sql = IF(@index_exists = 0, 
    'ALTER TABLE orders ADD INDEX idx_orders_user (user_id)', 
    'SELECT "Index idx_orders_user already exists" as message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- إضافة المفتاح الخارجي إذا لم يكن موجوداً
SET @fk_exists = 0;
SELECT COUNT(*) INTO @fk_exists 
FROM information_schema.key_column_usage 
WHERE table_schema = DATABASE() 
AND table_name = 'orders' 
AND constraint_name = 'fk_orders_user';

SET @sql = IF(@fk_exists = 0, 
    'ALTER TABLE orders ADD CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL', 
    'SELECT "Foreign key fk_orders_user already exists" as message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
