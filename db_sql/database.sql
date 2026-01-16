-- ゲストハウスマル正 データベース構築SQL

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
  `name_en` varchar(255) DEFAULT NULL COMMENT '部屋タイプ名 (English)',
  `description` text DEFAULT NULL COMMENT '説明',
  `description_en` text DEFAULT NULL COMMENT '説明 (English)',
  `capacity` int(11) NOT NULL COMMENT '収容人数',
  `main_image` varchar(255) DEFAULT NULL COMMENT '部屋タイプ代表画像パス',
  `is_visible` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'トップページ表示フラグ (1:表示, 0:非表示)',
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
  `price` decimal(10,2) NOT NULL COMMENT '一泊あたりの料金',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `room_type_id` (`room_type_id`),
  CONSTRAINT `rooms_ibfk_1` FOREIGN KEY (`room_type_id`) REFERENCES `room_types` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- テーブル構造: `bookings`
-- 予約情報
--
CREATE TABLE `bookings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `booking_token` varchar(64) DEFAULT NULL COMMENT '予約トークン',
  `user_id` int(11) DEFAULT NULL COMMENT '顧客ID (ゲスト予約の場合はNULL)',
  `guest_name` varchar(255) DEFAULT NULL COMMENT 'ゲストの氏名',
  `guest_email` varchar(255) DEFAULT NULL COMMENT 'ゲストのメールアドレス',
  `guest_phone` varchar(20) DEFAULT NULL COMMENT 'ゲスト電話番号',
  `check_in_date` date NOT NULL COMMENT 'チェックイン日',
  `check_out_date` date NOT NULL COMMENT 'チェックアウト日',
  `check_in_time` varchar(20) DEFAULT NULL COMMENT '到着予定時刻',
  `check_out_time` varchar(20) DEFAULT NULL COMMENT '出発予定時刻',
  `num_guests` int(11) NOT NULL COMMENT '宿泊人数 (大人)',
  `num_children` int(11) DEFAULT 0 COMMENT '宿泊人数 (子供)',
  `total_price` decimal(10,2) NOT NULL COMMENT '合計金額',
  `status` varchar(20) NOT NULL DEFAULT 'confirmed' COMMENT '予約状況 (confirmed, cancelled)',
  `notes` text DEFAULT NULL COMMENT '備考欄',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `idx_booking_token` (`booking_token`),
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
-- 初期データの挿入（サンプル）
--
-- 部屋タイプ
INSERT INTO `room_types` (`id`, `name`, `description`, `capacity`) VALUES
(1, 'シングルルーム', 'お一人様向けのコンパクトで機能的なお部屋です。', 1),
(2, 'ツインルーム', 'ご友人やカップルでのご利用に最適なお部屋です。', 2),
(3, 'デラックスツイン', '広々とした空間で、ゆったりとおくつろぎいただけます。', 2),
(4, '和室', '日本の伝統的なお部屋で、落ち着いた時間をお過ごしください。', 4);

-- 部屋
INSERT INTO `rooms` (`id`, `room_type_id`, `name`, `price`) VALUES
(1, 1, '101号室', '8000.00'),
(2, 1, '102号室', '8000.00'),
(3, 2, '201号室', '12000.00'),
(4, 2, '202号室', '12000.00'),
(5, 3, '301号室', '15000.00'),
(6, 4, '302号室 (和室)', '16000.00');

-- 管理者アカウント
INSERT INTO `users` (`name`, `email`, `password`, `role`, `notes`) VALUES
('管理者', 'admin@example.com', '$2y$10$xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx', 1, '初期管理者');
-- 注意: 上記パスワードはダミーです。実際の開発時には必ず強力なパスワードに置き換えてください。
-- 'password'をハッシュ化したものを設定してください。例: password_hash('password', PASSWORD_DEFAULT)

-- 既存のシステムへのカラム追加（必要に応じて実行）
-- ALTER TABLE `users` ADD COLUMN `phone` VARCHAR(20) DEFAULT NULL COMMENT '電話番号' AFTER `email`;
-- ALTER TABLE `users` ADD COLUMN `notes` TEXT DEFAULT NULL COMMENT '特記事項' AFTER `role`;
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
