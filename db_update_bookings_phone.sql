ALTER TABLE `bookings` ADD COLUMN `guest_phone` VARCHAR(20) DEFAULT NULL COMMENT 'ゲスト電話番号' AFTER `guest_email`;
