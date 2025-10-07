<?php
if (!defined('BASE_PATH')) {
    require_once dirname(__DIR__) . '/config/config.php';
}
require_once BASE_PATH . '/src/Utils/db.php';

class Migrations {
    private static $migrationsTable = 'migrations';
    
    public static function run() {
        try {
            // إنشاء جدول Migrations إذا لم يكن موجوداً
            self::createMigrationsTable();
            
            // جلب قائمة الملفات المنجزة
            $completed = self::getCompletedMigrations();
            
            // جلب جميع ملفات Migration
            $migrationFiles = glob(__DIR__ . '/migrations/*.sql');
            sort($migrationFiles);
            
            $newMigrations = [];
            
            foreach ($migrationFiles as $file) {
                $filename = basename($file);
                
                if (!in_array($filename, $completed)) {
                    echo "تشغيل Migration: {$filename}\n";
                    
                    // قراءة وتنفيذ الملف
                    $sql = file_get_contents($file);
                    $queries = explode(';', $sql);
                    
                    foreach ($queries as $query) {
                        $query = trim($query);
                        if (!empty($query)) {
                            Database::query($query);
                        }
                    }
                    
                    // تسجيل Migration كمنجز
                    Database::query(
                        "INSERT INTO " . self::$migrationsTable . " (filename, run_at) VALUES (?, NOW())",
                        [$filename]
                    );
                    
                    $newMigrations[] = $filename;
                }
            }
            
            if (empty($newMigrations)) {
                echo "جميع Migrations محدثة.\n";
            } else {
                echo "تم تشغيل " . count($newMigrations) . " Migration جديد.\n";
            }
            
            return true;
            
        } catch (Exception $e) {
            echo "خطأ في تشغيل Migrations: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    private static function createMigrationsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS " . self::$migrationsTable . " (
            id INT AUTO_INCREMENT PRIMARY KEY,
            filename VARCHAR(255) UNIQUE NOT NULL,
            run_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        Database::query($sql);
    }
    
    private static function getCompletedMigrations() {
        try {
            $result = Database::fetchAll("SELECT filename FROM " . self::$migrationsTable);
            return array_column($result, 'filename');
        } catch (Exception $e) {
            return [];
        }
    }
}

// تشغيل Migrations إذا تم استدعاء الملف مباشرة
if (basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
    Migrations::run();
}
?>
