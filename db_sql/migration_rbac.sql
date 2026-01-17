-- 権限管理(RBAC)およびログ機能追加用マイグレーション

-- admin_logsテーブルの作成
CREATE TABLE IF NOT EXISTS `admin_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL COMMENT '操作者ID',
  `action` varchar(50) NOT NULL COMMENT '操作内容 (login, create, update, deleteなど)',
  `details` text DEFAULT NULL COMMENT '詳細情報 (JSON形式推奨)',
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'IPアドレス',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `admin_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- usersテーブルのroleカラムのコメント更新
ALTER TABLE `users` MODIFY COLUMN `role` int(1) NOT NULL DEFAULT 0 COMMENT '権限 (0:一般, 1:管理者, 2:フロント, 3:清掃)';
