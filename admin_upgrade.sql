-- Select your existing paibaekpea_db in phpMyAdmin first. No DROP, DELETE, or account changes.
-- Compatible with MariaDB 10.4 / PHP 8.0. Safe to import again.
CREATE TABLE IF NOT EXISTS admin_settings (
 setting_key VARCHAR(80) PRIMARY KEY,
 setting_value VARCHAR(255) NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO admin_settings(setting_key,setting_value) VALUES ('analytics_started','1');
CREATE TABLE IF NOT EXISTS analytics_visits (
 visit_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 visitor_hash CHAR(64) NOT NULL,
 started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 last_seen TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX visit_started(started_at), INDEX visitor_seen(visitor_hash,last_seen)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS analytics_events (
 event_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 visit_id BIGINT UNSIGNED NOT NULL,
 visitor_hash CHAR(64) NOT NULL,
 user_id INT NULL,
 page_name VARCHAR(50) NOT NULL,
 trip_id INT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX event_time(created_at), INDEX visitor_time(visitor_hash,created_at),
 INDEX member_time(user_id,created_at), INDEX trip_time(trip_id,created_at),
 FOREIGN KEY(visit_id) REFERENCES analytics_visits(visit_id) ON DELETE CASCADE,
 FOREIGN KEY(user_id) REFERENCES users(user_id) ON DELETE SET NULL,
 FOREIGN KEY(trip_id) REFERENCES trips(trip_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS reports (
 report_id INT AUTO_INCREMENT PRIMARY KEY,
 reporter_id INT NOT NULL,
 target_type ENUM('trip','user') NOT NULL,
 target_id INT NOT NULL,
 reason VARCHAR(1000) NOT NULL,
 status ENUM('pending','resolved','dismissed') NOT NULL DEFAULT 'pending',
 resolution VARCHAR(500) NULL,
 reviewed_by INT NULL,
 reviewed_at TIMESTAMP NULL DEFAULT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX report_status(status,created_at), INDEX report_target(target_type,target_id),
 FOREIGN KEY(reporter_id) REFERENCES users(user_id),
 FOREIGN KEY(reviewed_by) REFERENCES users(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
