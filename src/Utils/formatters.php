<?php
/**
 * Formatting Helpers
 * Standardize monetary and date/time display across the application
 */

class Formatters {
    
    /**
     * Format amount to LYD with 2 decimal places
     * 
     * @param float|int|string $amount
     * @param bool $includeCurrency Include "LYD" suffix
     * @return string
     */
    public static function formatMoney($amount, $includeCurrency = true) {
        $formatted = number_format(floatval($amount), 2, '.', ',');
        return $includeCurrency ? $formatted . ' LYD' : $formatted;
    }
    
    /**
     * Format amount for display in tables (compact)
     * 
     * @param float|int|string $amount
     * @return string
     */
    public static function formatMoneyCompact($amount) {
        return number_format(floatval($amount), 2, '.', ',');
    }
    
    /**
     * Format date to Arabic-friendly format
     * 
     * @param string $datetime MySQL datetime or timestamp
     * @param bool $includeTime Include time component
     * @return string
     */
    public static function formatDate($datetime, $includeTime = false) {
        if (empty($datetime)) return '-';
        
        $timestamp = is_numeric($datetime) ? $datetime : strtotime($datetime);
        if (!$timestamp) return '-';
        
        if ($includeTime) {
            return date('Y-m-d H:i', $timestamp);
        }
        return date('Y-m-d', $timestamp);
    }
    
    /**
     * Format datetime to full format with time
     * 
     * @param string $datetime MySQL datetime or timestamp
     * @return string
     */
    public static function formatDateTime($datetime) {
        return self::formatDate($datetime, true);
    }
    
    /**
     * Format date to human-readable Arabic format
     * 
     * @param string $datetime MySQL datetime or timestamp
     * @return string
     */
    public static function formatDateHuman($datetime) {
        if (empty($datetime)) return '-';
        
        $timestamp = is_numeric($datetime) ? $datetime : strtotime($datetime);
        if (!$timestamp) return '-';
        
        $now = time();
        $diff = $now - $timestamp;
        
        // Less than 1 minute
        if ($diff < 60) {
            return 'الآن';
        }
        
        // Less than 1 hour
        if ($diff < 3600) {
            $minutes = floor($diff / 60);
            return "منذ {$minutes} دقيقة" . ($minutes > 2 ? '' : '');
        }
        
        // Less than 24 hours
        if ($diff < 86400) {
            $hours = floor($diff / 3600);
            return "منذ {$hours} ساعة" . ($hours > 2 ? '' : '');
        }
        
        // Less than 7 days
        if ($diff < 604800) {
            $days = floor($diff / 86400);
            return "منذ {$days} يوم" . ($days > 2 ? '' : '');
        }
        
        // Default to standard format
        return date('Y-m-d', $timestamp);
    }
    
    /**
     * Format time only (HH:MM)
     * 
     * @param string $datetime MySQL datetime or timestamp
     * @return string
     */
    public static function formatTime($datetime) {
        if (empty($datetime)) return '-';
        
        $timestamp = is_numeric($datetime) ? $datetime : strtotime($datetime);
        if (!$timestamp) return '-';
        
        return date('H:i', $timestamp);
    }
    
    /**
     * Format quantity with thousands separator
     * 
     * @param int|string $quantity
     * @return string
     */
    public static function formatQuantity($quantity) {
        return number_format(intval($quantity), 0, '.', ',');
    }
    
    /**
     * Get current timezone from config
     * 
     * @return string
     */
    public static function getTimezone() {
        return defined('TIMEZONE') ? TIMEZONE : 'Africa/Tripoli';
    }
    
    /**
     * Set default timezone for the application
     */
    public static function setDefaultTimezone() {
        date_default_timezone_set(self::getTimezone());
    }
}

// Set timezone on load
Formatters::setDefaultTimezone();
?>

