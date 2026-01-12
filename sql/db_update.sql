-- 既存のbookingsテーブルにカラムを追加
ALTER TABLE `bookings` ADD COLUMN `guest_phone` VARCHAR(20) DEFAULT NULL COMMENT 'ゲスト電話番号' AFTER `guest_email`;
ALTER TABLE `bookings` ADD COLUMN `booking_number` VARCHAR(20) DEFAULT NULL COMMENT '予約番号 (YYYYMMDD-XXXXXXXX)' AFTER `id`;
ALTER TABLE `bookings` ADD INDEX `idx_booking_number` (`booking_number`);

-- room_imagesテーブルの作成
CREATE TABLE `room_images` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `room_id` int(11) NOT NULL COMMENT '部屋ID',
  `image_path` varchar(255) NOT NULL COMMENT '画像パス',
  `is_main` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'メイン画像フラグ (1: メイン, 0: その他)',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `room_id` (`room_id`),
  CONSTRAINT `room_images_ibfk_1` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
