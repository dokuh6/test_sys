-- Migration for Password Reset and Automated Emails

-- Users table updates for Password Reset
ALTER TABLE users ADD COLUMN reset_token VARCHAR(64) DEFAULT NULL COMMENT 'パスワードリセット用トークン';
ALTER TABLE users ADD COLUMN reset_expires_at DATETIME DEFAULT NULL COMMENT 'パスワードリセットトークン有効期限';

-- Bookings table updates for Automated Emails
ALTER TABLE bookings ADD COLUMN reminder_sent TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'リマインダーメール送信済みフラグ';
ALTER TABLE bookings ADD COLUMN thankyou_sent TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'サンキューメール送信済みフラグ';
