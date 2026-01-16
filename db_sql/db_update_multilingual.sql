-- データベース多言語対応更新SQL

-- room_typesテーブルに英語用のカラムを追加
ALTER TABLE `room_types`
ADD `name_en` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL AFTER `name`,
ADD `description_en` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL AFTER `description`;

-- roomsテーブルに英語用のカラムを追加
ALTER TABLE `rooms`
ADD `name_en` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL AFTER `name`;

-- 既存の部屋タイプにサンプル英語データを追加
UPDATE `room_types` SET `name_en` = 'Single Room', `description_en` = 'A compact and functional room for one person.' WHERE `id` = 1;
UPDATE `room_types` SET `name_en` = 'Twin Room', `description_en` = 'An ideal room for friends or couples.' WHERE `id` = 2;
UPDATE `room_types` SET `name_en` = 'Deluxe Twin', `description_en` = 'A spacious room where you can relax comfortably.' WHERE `id` = 3;
UPDATE `room_types` SET `name_en` = 'Japanese-style Room', `description_en` = 'Spend a quiet time in a traditional Japanese room.' WHERE `id` = 4;

-- 既存の部屋にサンプル英語データを追加
UPDATE `rooms` SET `name_en` = 'Room 101' WHERE `id` = 1;
UPDATE `rooms` SET `name_en` = 'Room 102' WHERE `id` = 2;
UPDATE `rooms` SET `name_en` = 'Room 201' WHERE `id` = 3;
UPDATE `rooms` SET `name_en` = 'Room 202' WHERE `id` = 4;
UPDATE `rooms` SET `name_en` = 'Room 301' WHERE `id` = 5;
UPDATE `rooms` SET `name_en` = 'Room 302 (Japanese)' WHERE `id` = 6;
