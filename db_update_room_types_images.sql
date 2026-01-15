ALTER TABLE `room_types`
ADD COLUMN `main_image` VARCHAR(255) DEFAULT NULL COMMENT '部屋タイプ代表画像パス',
ADD COLUMN `is_visible` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'トップページ表示フラグ (1:表示, 0:非表示)';
