-- =============================================
-- VeresVill CRM - Adatbázis séma
-- MySQL 5.7+ / MariaDB 10.3+
-- =============================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- =============================================
-- FELHASZNÁLÓK
-- =============================================
CREATE TABLE IF NOT EXISTS vv_users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'worker') NOT NULL DEFAULT 'worker',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

-- =============================================
-- MEGRENDELÉSEK
-- =============================================
CREATE TABLE IF NOT EXISTS vv_orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(100) NOT NULL,
    customer_email VARCHAR(255) NOT NULL,
    customer_phone VARCHAR(30) NOT NULL,
    customer_address TEXT NOT NULL,
    property_type VARCHAR(50) NOT NULL,
    property_type_label VARCHAR(100) NOT NULL,
    size INT UNSIGNED NOT NULL,
    urgency VARCHAR(20) NOT NULL DEFAULT 'normal',
    urgency_label VARCHAR(50) NOT NULL,
    message TEXT NULL,
    status ENUM('uj','ajanlat_kuldve','elfogadva','idopont_kivalasztva','elvegezve') NOT NULL DEFAULT 'uj',
    assigned_to INT UNSIGNED NULL,
    quote_amount INT UNSIGNED NULL,
    quote_token VARCHAR(64) NULL UNIQUE,
    quote_token_expires DATETIME NULL,
    quote_sent_at DATETIME NULL,
    quote_accepted_at DATETIME NULL,
    selected_slot_id INT UNSIGNED NULL,
    slots_rejected_at DATETIME NULL,
    completed_at DATETIME NULL,
    admin_notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_assigned (assigned_to),
    INDEX idx_quote_token (quote_token),
    INDEX idx_created (created_at DESC),
    FOREIGN KEY (assigned_to) REFERENCES vv_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

-- =============================================
-- IDŐPONT SLOTOK (árajánlathoz)
-- =============================================
CREATE TABLE IF NOT EXISTS vv_time_slots (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    worker_id INT UNSIGNED NOT NULL,
    slot_date DATE NOT NULL,
    slot_start TIME NOT NULL,
    slot_end TIME NOT NULL,
    is_selected TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_order (order_id),
    INDEX idx_worker_date (worker_id, slot_date),
    FOREIGN KEY (order_id) REFERENCES vv_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (worker_id) REFERENCES vv_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

-- =============================================
-- NAPTÁR ESEMÉNYEK
-- =============================================
CREATE TABLE IF NOT EXISTS vv_calendar_events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    order_id INT UNSIGNED NULL,
    title VARCHAR(255) NOT NULL,
    event_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    event_type ENUM('appointment','block','travel') NOT NULL DEFAULT 'appointment',
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_date (user_id, event_date),
    INDEX idx_order (order_id),
    FOREIGN KEY (user_id) REFERENCES vv_users(id) ON DELETE CASCADE,
    FOREIGN KEY (order_id) REFERENCES vv_orders(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

-- =============================================
-- STÁTUSZ NAPLÓ (audit trail)
-- =============================================
CREATE TABLE IF NOT EXISTS vv_order_status_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    old_status VARCHAR(30) NULL,
    new_status VARCHAR(30) NOT NULL,
    changed_by INT UNSIGNED NULL,
    note TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_order (order_id),
    FOREIGN KEY (order_id) REFERENCES vv_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES vv_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

-- =============================================
-- PUSH FELIRATKOZÁSOK
-- =============================================
CREATE TABLE IF NOT EXISTS vv_push_subscriptions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    platform ENUM('web','ios') NOT NULL DEFAULT 'web',
    endpoint TEXT NULL,
    p256dh VARCHAR(255) NULL,
    auth VARCHAR(255) NULL,
    device_token VARCHAR(255) NULL,
    user_agent VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    FOREIGN KEY (user_id) REFERENCES vv_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

-- =============================================
-- AUTH TOKENEK
-- =============================================
CREATE TABLE IF NOT EXISTS vv_auth_tokens (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token (token),
    INDEX idx_expires (expires_at),
    FOREIGN KEY (user_id) REFERENCES vv_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

-- =============================================
-- BEJELENTKEZÉSI KÍSÉRLETEK (rate limiting)
-- =============================================
CREATE TABLE IF NOT EXISTS vv_login_attempts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    email VARCHAR(255) NULL,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_time (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

-- =============================================
-- BEÁLLÍTÁSOK (kulcs-érték)
-- =============================================
CREATE TABLE IF NOT EXISTS vv_settings (
    setting_key VARCHAR(50) PRIMARY KEY,
    setting_value TEXT NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================
-- ALAPÉRTELMEZETT BEÁLLÍTÁSOK
-- =============================================
INSERT INTO vv_settings (setting_key, setting_value) VALUES
('working_hours', '{"mon":{"start":"08:00","end":"17:00"},"tue":{"start":"08:00","end":"17:00"},"wed":{"start":"08:00","end":"17:00"},"thu":{"start":"08:00","end":"17:00"},"fri":{"start":"08:00","end":"17:00"},"sat":{"start":"09:00","end":"13:00"},"sun":null}'),
('default_slot_duration', '60'),
('company_name', 'Veresvill'),
('company_phone', '+36 70 368 6638')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
