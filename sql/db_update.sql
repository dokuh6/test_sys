-- Fix Schema for Code Compatibility

-- 1. Create room_images table
CREATE TABLE IF NOT EXISTS `room_images` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `room_id` int(11) NOT NULL COMMENT '部屋ID',
  `image_path` varchar(255) NOT NULL COMMENT '画像パス',
  `is_main` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'メイン画像フラグ',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `room_id` (`room_id`),
  CONSTRAINT `room_images_ibfk_1` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Add columns to rooms
ALTER TABLE `rooms` ADD COLUMN `name_en` varchar(255) DEFAULT NULL COMMENT '部屋名 (英語)' AFTER `name`;

-- 3. Add columns to room_types
ALTER TABLE `room_types` ADD COLUMN `name_en` varchar(255) DEFAULT NULL COMMENT '部屋タイプ名 (英語)' AFTER `name`;
ALTER TABLE `room_types` ADD COLUMN `description_en` text DEFAULT NULL COMMENT '説明 (英語)' AFTER `description`;

-- 4. Alter bookings table
-- Rename booking_token to booking_number if possible, or add booking_number
-- MySQL 8.0+ supports RENAME COLUMN, but for compatibility with older MariaDB/MySQL 5.7, we might need CHANGE.
-- Since we are defining a migration file, we will try to adjust `booking_token` to `booking_number`.
-- Assuming the table is empty or we can just change it.
ALTER TABLE `bookings` CHANGE COLUMN `booking_token` `booking_number` varchar(64) DEFAULT NULL COMMENT '予約番号';
ALTER TABLE `bookings` ADD COLUMN `guest_phone` varchar(20) DEFAULT NULL COMMENT 'ゲスト電話番号' AFTER `guest_email`;
-- Add index for booking_number if the old index name persists it might be confusing, but checking schema...
-- KEY `idx_booking_token` (`booking_token`) -> this index will now rely on `booking_number` but the index name stays `idx_booking_token` in MySQL usually.
-- We can rename the index for clarity.
ALTER TABLE `bookings` RENAME INDEX `idx_booking_token` TO `idx_booking_number`;

-- 5. Alter users table
-- Add phone if it doesn't exist (using procedure or just ignoring error if strict mode is off, but here just the SQL)
ALTER TABLE `users` ADD COLUMN `phone` varchar(20) DEFAULT NULL COMMENT '電話番号' AFTER `email`;
-- notes is already in CREATE TABLE in sql/database.sql but user said fix "Database errors", so maybe it was missing in live.
-- ALTER TABLE `users` ADD COLUMN `notes` text DEFAULT NULL COMMENT '特記事項' AFTER `role`;

-- Update sample data for English
UPDATE `room_types` SET `name_en` = 'Single Room', `description_en` = 'Compact and functional room for one person.' WHERE `name` = 'シングルルーム';
UPDATE `room_types` SET `name_en` = 'Twin Room', `description_en` = 'Ideal for friends or couples.' WHERE `name` = 'ツインルーム';
UPDATE `room_types` SET `name_en` = 'Deluxe Twin', `description_en` = 'Spacious room for a relaxing stay.' WHERE `name` = 'デラックスツイン';
UPDATE `room_types` SET `name_en` = 'Japanese Style Room', `description_en` = 'Traditional Japanese room for a calm experience.' WHERE `name` = '和室';

UPDATE `rooms` SET `name_en` = 'Room 101' WHERE `name` = '101号室';
UPDATE `rooms` SET `name_en` = 'Room 102' WHERE `name` = '102号室';
UPDATE `rooms` SET `name_en` = 'Room 201' WHERE `name` = '201号室';
UPDATE `rooms` SET `name_en` = 'Room 202' WHERE `name` = '202号室';
UPDATE `rooms` SET `name_en` = 'Room 301' WHERE `name` = '301号室';
UPDATE `rooms` SET `name_en` = 'Room 302 (Japanese)' WHERE `name` = '302号室 (和室)';
