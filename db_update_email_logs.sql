-- メール送信ログ機能のためのテーブル作成

CREATE TABLE IF NOT EXISTS `email_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `to_email` varchar(255) NOT NULL COMMENT '送信先メールアドレス',
  `subject` varchar(255) NOT NULL COMMENT '件名',
  `body` text NOT NULL COMMENT '本文',
  `status` varchar(20) NOT NULL COMMENT '送信ステータス (success/failure)',
  `error_message` text DEFAULT NULL COMMENT 'エラーメッセージ',
  `created_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT '送信日時',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
