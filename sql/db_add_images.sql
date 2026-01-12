--
-- テーブル構造: `room_images`
-- 部屋の画像情報
--
CREATE TABLE `room_images` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `room_id` int(11) NOT NULL COMMENT '部屋ID',
  `image_path` varchar(255) NOT NULL COMMENT '画像ファイルへのパス',
  `is_main` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'メイン画像かどうかのフラグ (1: メイン, 0: サブ)',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `room_id` (`room_id`),
  CONSTRAINT `room_images_ibfk_1` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- roomsテーブルに多言語対応の列を追加 (もし存在しなければ)
-- db_update_multilingual.sqlが既に適用されているかもしれないが、念のため
ALTER TABLE `rooms`
  ADD COLUMN `name_en` VARCHAR(255) DEFAULT NULL COMMENT '部屋名・番号 (English)' AFTER `name`,
  ADD COLUMN `description` TEXT DEFAULT NULL COMMENT '部屋の説明 (日本語)',
  ADD COLUMN `description_en` TEXT DEFAULT NULL COMMENT '部屋の説明 (English)';

ALTER TABLE `room_types`
  ADD COLUMN `name_en` VARCHAR(255) DEFAULT NULL COMMENT '部屋タイプ名 (English)' AFTER `name`,
  ADD COLUMN `description_en` TEXT DEFAULT NULL COMMENT '説明 (English)' AFTER `description`;

-- Note:
-- The ALTER TABLE statements for multilingual support might already be applied
-- by `db_update_multilingual.sql`. If so, these will produce an error,
-- but it can be safely ignored as the columns would already exist.
-- It's included here for completeness in case the other script hasn't been run.
