-- CRM機能追加のためのデータベース更新

-- usersテーブルに電話番号と特記事項カラムを追加
ALTER TABLE `users` ADD COLUMN `phone` VARCHAR(20) DEFAULT NULL COMMENT '電話番号' AFTER `email`;
ALTER TABLE `users` ADD COLUMN `notes` TEXT DEFAULT NULL COMMENT '特記事項' AFTER `role`;
