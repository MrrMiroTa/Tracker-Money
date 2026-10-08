-- Brute-force protection table (auth-guard.php also creates it automatically on first login).
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(100) NOT NULL,
  `ip_address` VARCHAR(45) NOT NULL,
  `success` TINYINT(1) NOT NULL DEFAULT 0,
  `attempted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_user_ip_time` (`username`, `ip_address`, `attempted_at`),
  KEY `idx_ip_time` (`ip_address`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Speeds up the transaction list / CSV / PDF queries (filter by user, not deleted, sorted by date).
ALTER TABLE `transactions` ADD INDEX `idx_user_deleted_date` (`user_id`, `is_deleted`, `date`);

-- Admin/Super Admin reports have no user_id filter, so they need their own index.
ALTER TABLE `transactions` ADD INDEX `idx_deleted_date` (`is_deleted`, `date`);
