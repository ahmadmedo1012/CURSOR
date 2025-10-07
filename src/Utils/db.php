<?php
if (!defined('BASE_PATH')) {
    require_once dirname(dirname(__DIR__)) . '/config/config.php';
}
require_once __DIR__ . '/logger.php';

class Database {
    private static $connection = null;
    
    public static function connect() {
        if (self::$connection === null) {
            try {
                self::$connection = new mysqli(
                    DB_HOST, 
                    DB_USER, 
                    DB_PASS, 
                    DB_NAME, 
                    DB_PORT
                );
                
                if (self::$connection->connect_error) {
                    Logger::error('فشل الاتصال بقاعدة البيانات', ['error' => self::$connection->connect_error]);
                    throw new Exception('فشل الاتصال بقاعدة البيانات: ' . self::$connection->connect_error);
                }
                
                self::$connection->set_charset('utf8mb4');
                
            } catch (Exception $e) {
                die('خطأ في الاتصال: ' . $e->getMessage());
            }
        }
        
        return self::$connection;
    }
    
    public static function query($sql, $params = []) {
        $conn = self::connect();
        
        if (empty($params)) {
            $result = $conn->query($sql);
            if (!$result) {
                throw new Exception('خطأ في الاستعلام: ' . $conn->error);
            }
            return $result;
        }
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('خطأ في تحضير الاستعلام: ' . $conn->error);
        }
        
        if (!empty($params)) {
            $types = '';
            $values = [];
            
            foreach ($params as $param) {
                if (is_int($param)) {
                    $types .= 'i';
                } elseif (is_double($param)) {
                    $types .= 'd';
                } else {
                    $types .= 's';
                }
                $values[] = $param;
            }
            
            $stmt->bind_param($types, ...$values);
        }
        
        if (!$stmt->execute()) {
            throw new Exception('خطأ في تنفيذ الاستعلام: ' . $stmt->error);
        }
        
        $result = $stmt->get_result();
        $stmt->close();
        
        return $result;
    }
    
    public static function fetchAll($sql, $params = []) {
        $result = self::query($sql, $params);
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    public static function fetchOne($sql, $params = []) {
        $result = self::query($sql, $params);
        return $result->fetch_assoc();
    }
    
    public static function fetchColumn($sql, $params = []) {
        $result = self::query($sql, $params);
        $row = $result->fetch_array(MYSQLI_NUM);
        return $row ? $row[0] : null;
    }
    
    public static function lastInsertId() {
        return self::connect()->insert_id;
    }
    
    public static function escape($string) {
        return self::connect()->real_escape_string($string);
    }
}
?>
