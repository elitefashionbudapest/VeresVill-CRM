-- Google Calendar szinkron támogatás
-- Futtasd phpMyAdmin-ban!

-- Felhasználónkénti Google OAuth tokenek
CREATE TABLE IF NOT EXISTS vv_google_tokens (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL UNIQUE,
    access_token TEXT NOT NULL,
    refresh_token TEXT NOT NULL,
    token_expires_at DATETIME NOT NULL,
    calendar_id VARCHAR(255) DEFAULT 'primary',
    sync_enabled TINYINT(1) NOT NULL DEFAULT 1,
    last_sync_at DATETIME NULL,
    sync_token VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES vv_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_hungarian_ci;

-- Google event ID hozzárendelés a naptár eseményekhez
ALTER TABLE vv_calendar_events
    ADD COLUMN google_event_id VARCHAR(255) NULL AFTER notes,
    ADD COLUMN google_synced_at DATETIME NULL AFTER google_event_id,
    ADD INDEX idx_google_event (google_event_id);
