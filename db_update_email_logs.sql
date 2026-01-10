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
