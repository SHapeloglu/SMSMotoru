-- Panelde oluşturduğunuz veritabanını phpMyAdmin'de seçip bu dosyayı içe aktarın.
-- (Paylaşımlı hostingde CREATE DATABASE yetkisi olmadığı için burada veritabanı oluşturulmaz.)

CREATE TABLE IF NOT EXISTS users (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 username VARCHAR(100) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS providers (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(50) NOT NULL UNIQUE,
 enabled TINYINT(1) NOT NULL DEFAULT 1,
 credentials_encrypted TEXT NULL,
 sender VARCHAR(30) NULL,
 price_per_sms DECIMAL(12,6) NULL,
 priority INT NOT NULL DEFAULT 100,
 supports_iys TINYINT(1) NOT NULL DEFAULT 0,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS contacts (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 first_name VARCHAR(100) NULL,
 last_name VARCHAR(100) NULL,
 phone VARCHAR(30) NOT NULL,
 group_name VARCHAR(100) NULL,
 company VARCHAR(255) NULL,
 source VARCHAR(500) NULL,
 consent_status ENUM('unknown','granted','denied') NOT NULL DEFAULT 'unknown',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_phone_group(phone,group_name),
 INDEX idx_phone(phone), INDEX idx_group(group_name)
);

-- Ret listesi: buradaki numaralara hiçbir türde (bilgilendirme dahil) SMS gönderilmez
CREATE TABLE IF NOT EXISTS optouts (
 phone VARCHAR(30) NOT NULL PRIMARY KEY,
 note VARCHAR(255) NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS messages (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 provider_id INT UNSIGNED NULL,
 sender VARCHAR(30) NULL,
 message_template TEXT NOT NULL,
 message_type VARCHAR(30) NOT NULL DEFAULT 'informational',
 total_recipients INT UNSIGNED NOT NULL DEFAULT 0,
 total_segments INT UNSIGNED NOT NULL DEFAULT 0,
 estimated_cost DECIMAL(12,4) NULL,
 status VARCHAR(30) NOT NULL DEFAULT 'queued',
 scheduled_at DATETIME NULL,
 created_by INT UNSIGNED NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE SET NULL,
 FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_queue(status,scheduled_at)
);

CREATE TABLE IF NOT EXISTS message_recipients (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 message_id BIGINT UNSIGNED NOT NULL,
 contact_id BIGINT UNSIGNED NULL,
 phone VARCHAR(30) NOT NULL,
 rendered_message TEXT NOT NULL,
 provider_message_id VARCHAR(255) NULL,
 status VARCHAR(30) NOT NULL DEFAULT 'queued',
 error_message TEXT NULL,
 sent_at DATETIME NULL,
 delivered_at DATETIME NULL,
 FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE,
 FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE SET NULL,
 INDEX idx_message_status(message_id,status)
);

CREATE TABLE IF NOT EXISTS iys_checks (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 provider_id INT UNSIGNED NULL,
 phone VARCHAR(30) NOT NULL,
 consent_status VARCHAR(30) NOT NULL,
 raw_response TEXT NULL,
 checked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_iys_phone(phone)
);

CREATE TABLE IF NOT EXISTS provider_logs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 provider_id INT UNSIGNED NULL,
 action VARCHAR(50) NOT NULL,
 http_code INT NULL,
 success TINYINT(1) NOT NULL DEFAULT 0,
 response_body MEDIUMTEXT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
