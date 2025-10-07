-- إضافة دعم المزودين المتعددين (نهائي وآمن)
-- Migration: 007_multi_providers_final.sql

-- إنشاء جدول المزودين
CREATE TABLE IF NOT EXISTS providers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) UNIQUE NOT NULL,
    display_name VARCHAR(100) NOT NULL,
    api_url VARCHAR(255) NOT NULL,
    api_key VARCHAR(255) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    priority INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- إدراج المزودين الافتراضيين
INSERT INTO providers (name, display_name, api_url, api_key, priority) VALUES
('peakerr', 'Peakerr', 'https://peakerr.com/api/', 'YOUR_PEAKERR_KEY', 1),
('newprovider', 'New Provider', 'https://newprovider.com/api/', 'YOUR_NEW_KEY', 2)
ON DUPLICATE KEY UPDATE 
display_name = VALUES(display_name),
api_url = VALUES(api_url),
api_key = VALUES(api_key),
priority = VALUES(priority);

-- إنشاء جدول إحصائيات المزودين
CREATE TABLE IF NOT EXISTS provider_stats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    provider VARCHAR(50) NOT NULL,
    total_orders INT DEFAULT 0,
    successful_orders INT DEFAULT 0,
    failed_orders INT DEFAULT 0,
    total_revenue DECIMAL(10,2) DEFAULT 0.00,
    last_sync TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_provider (provider)
);

-- إدراج إحصائيات افتراضية للمزودين
INSERT INTO provider_stats (provider, total_orders, successful_orders, failed_orders, total_revenue) VALUES
('peakerr', 0, 0, 0, 0.00),
('newprovider', 0, 0, 0, 0.00)
ON DUPLICATE KEY UPDATE 
total_orders = VALUES(total_orders),
successful_orders = VALUES(successful_orders),
failed_orders = VALUES(failed_orders),
total_revenue = VALUES(total_revenue);

