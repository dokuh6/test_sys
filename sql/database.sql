-- ゲストハウス丸正 データベース構築SQL

-- データベースが存在しない場合に作成
CREATE DATABASE IF NOT EXISTS `guesthouse_marusho` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `guesthouse_marusho`;

--
-- テーブル構造: `users`
-- 顧客および管理者の情報
--
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL COMMENT '氏名',
  `email` varchar(255) NOT NULL COMMENT 'メールアドレス',
  `phone` varchar(20) DEFAULT NULL COMMENT '電話番号',
  `password` varchar(255) NOT NULL COMMENT 'ハッシュ化されたパスワード',
  `role` int(1) NOT NULL DEFAULT 0 COMMENT '権限 (0: 一般ユーザー, 1: 管理者)',
  `notes` text DEFAULT NULL COMMENT '特記事項',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- テーブル構造: `room_types`
-- 部屋のカテゴリ情報（シングル、ツインなど）
--
CREATE TABLE `room_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL COMMENT '部屋タイプ名',
  `name_en` varchar(255) DEFAULT NULL COMMENT '部屋タイプ名 (英語)',
  `description` text DEFAULT NULL COMMENT '説明',
  `description_en` text DEFAULT NULL COMMENT '説明 (英語)',
  `capacity` int(11) NOT NULL COMMENT '収容人数',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- テーブル構造: `rooms`
-- 個別の部屋情報
--
CREATE TABLE `rooms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `room_type_id` int(11) NOT NULL COMMENT '部屋タイプID',
  `name` varchar(255) NOT NULL COMMENT '部屋名・番号 (例: 101号室)',
  `name_en` varchar(255) DEFAULT NULL COMMENT '部屋名 (英語)',
  `price` decimal(10,2) NOT NULL COMMENT '一泊あたりの料金',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `room_type_id` (`room_type_id`),
  CONSTRAINT `rooms_ibfk_1` FOREIGN KEY (`room_type_id`) REFERENCES `room_types` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- テーブル構造: `room_images`
-- 部屋の画像
--
CREATE TABLE `room_images` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `room_id` int(11) NOT NULL COMMENT '部屋ID',
  `image_path` varchar(255) NOT NULL COMMENT '画像パス',
  `is_main` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'メイン画像フラグ',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `room_id` (`room_id`),
  CONSTRAINT `room_images_ibfk_1` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- テーブル構造: `bookings`
-- 予約情報
--
CREATE TABLE `bookings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `booking_number` varchar(64) DEFAULT NULL COMMENT '予約番号',
  `user_id` int(11) DEFAULT NULL COMMENT '顧客ID (ゲスト予約の場合はNULL)',
  `guest_name` varchar(255) DEFAULT NULL COMMENT 'ゲストの氏名',
  `guest_email` varchar(255) DEFAULT NULL COMMENT 'ゲストのメールアドレス',
  `guest_phone` varchar(20) DEFAULT NULL COMMENT 'ゲスト電話番号',
  `check_in_date` date NOT NULL COMMENT 'チェックイン日',
  `check_out_date` date NOT NULL COMMENT 'チェックアウト日',
  `num_guests` int(11) NOT NULL COMMENT '宿泊人数',
  `total_price` decimal(10,2) NOT NULL COMMENT '合計金額',
  `status` varchar(20) NOT NULL DEFAULT 'confirmed' COMMENT '予約状況 (confirmed, cancelled)',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `idx_booking_number` (`booking_number`),
  CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- テーブル構造: `booking_rooms`
-- 予約と部屋の関連付け
--
CREATE TABLE `booking_rooms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `booking_id` int(11) NOT NULL COMMENT '予約ID',
  `room_id` int(11) NOT NULL COMMENT '部屋ID',
  PRIMARY KEY (`id`),
  UNIQUE KEY `booking_id_room_id` (`booking_id`,`room_id`),
  KEY `room_id` (`room_id`),
  CONSTRAINT `booking_rooms_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `booking_rooms_ibfk_2` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- テーブル構造: `email_logs`
-- 送信メール履歴
--
CREATE TABLE `email_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `to_email` varchar(255) NOT NULL COMMENT '送信先メールアドレス',
  `subject` varchar(255) NOT NULL COMMENT '件名',
  `body` text DEFAULT NULL COMMENT '本文',
  `headers` text DEFAULT NULL COMMENT 'ヘッダー情報',
  `status` varchar(20) NOT NULL DEFAULT 'success' COMMENT '送信結果 (success, failure)',
  `error_message` text DEFAULT NULL COMMENT 'エラーメッセージ',
  `sent_at` datetime NOT NULL COMMENT '送信日時',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- 初期データの挿入（サンプル）
--
-- 部屋タイプ
INSERT INTO `room_types` (`id`, `name`, `name_en`, `description`, `description_en`, `capacity`) VALUES
(1, 'シングルルーム', 'Single Room', 'お一人様向けのコンパクトで機能的なお部屋です。', 'Compact and functional room for one person.', 1),
(2, 'ツインルーム', 'Twin Room', 'ご友人やカップルでのご利用に最適なお部屋です。', 'Ideal for friends or couples.', 2),
(3, 'デラックスツイン', 'Deluxe Twin', '広々とした空間で、ゆったりとおくつろぎいただけます。', 'Spacious room for a relaxing stay.', 2),
(4, '和室', 'Japanese Style Room', '日本の伝統的なお部屋で、落ち着いた時間をお過ごしください。', 'Traditional Japanese room for a calm experience.', 4);

-- 部屋
INSERT INTO `rooms` (`id`, `room_type_id`, `name`, `name_en`, `price`) VALUES
(1, 1, '101号室', 'Room 101', '8000.00'),
(2, 1, '102号室', 'Room 102', '8000.00'),
(3, 2, '201号室', 'Room 201', '12000.00'),
(4, 2, '202号室', 'Room 202', '12000.00'),
(5, 3, '301号室', 'Room 301', '15000.00'),
(6, 4, '302号室 (和室)', 'Room 302 (Japanese)', '16000.00');

-- 管理者アカウント
INSERT INTO `users` (`name`, `email`, `password`, `role`, `notes`) VALUES
('管理者', 'admin@example.com', '$2y$10$xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx', 1, '初期管理者');
-- 注意: 上記パスワードはダミーです。実際の開発時には必ず強力なパスワードに置き換えてください。
