-- Feature Update Migration

-- 1. Split Room Prices
ALTER TABLE `rooms` ADD COLUMN `price_adult` DECIMAL(10,2) NOT NULL DEFAULT 4500.00 COMMENT '大人料金' AFTER `price`;
ALTER TABLE `rooms` ADD COLUMN `price_child` DECIMAL(10,2) NOT NULL DEFAULT 2500.00 COMMENT '子供料金' AFTER `price_adult`;

-- Initialize with existing price if meaningful, otherwise defaults are set above.
-- Assuming existing 'price' was the adult price:
UPDATE `rooms` SET `price_adult` = `price`;

-- Note: We are keeping the old `price` column for now to prevent immediate breakage,
-- but it should be considered deprecated.
-- ALTER TABLE `rooms` DROP COLUMN `price`;

-- 2. Booking Statuses
ALTER TABLE `bookings` ADD COLUMN `payment_status` VARCHAR(20) NOT NULL DEFAULT 'unpaid' COMMENT '支払状況 (unpaid, paid)' AFTER `status`;
ALTER TABLE `bookings` ADD COLUMN `checkin_status` VARCHAR(20) NOT NULL DEFAULT 'waiting' COMMENT 'チェックイン状況 (waiting, checked_in)' AFTER `payment_status`;
