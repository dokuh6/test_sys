-- 既存のbookingsテーブルに新しいカラムを追加し、仕様変更に対応するSQL

-- 到着・出発予定時刻の追加
ALTER TABLE `bookings` ADD COLUMN `check_in_time` VARCHAR(20) DEFAULT NULL COMMENT '到着予定時刻' AFTER `check_out_date`;
ALTER TABLE `bookings` ADD COLUMN `check_out_time` VARCHAR(20) DEFAULT NULL COMMENT '出発予定時刻' AFTER `check_in_time`;

-- 既存のnum_guestsカラムのコメント更新（大人人数であることを明確化）
ALTER TABLE `bookings` MODIFY COLUMN `num_guests` int(11) NOT NULL COMMENT '宿泊人数 (大人)';

-- 子供人数の追加
ALTER TABLE `bookings` ADD COLUMN `num_children` INT DEFAULT 0 COMMENT '宿泊人数 (子供)' AFTER `num_guests`;

-- 備考欄の追加
ALTER TABLE `bookings` ADD COLUMN `notes` TEXT DEFAULT NULL COMMENT '備考欄' AFTER `status`;
