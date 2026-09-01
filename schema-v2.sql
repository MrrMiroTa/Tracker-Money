-- schema-v2.sql
-- Database structure update for Secure User Authorization, RBAC, and Dual Control Approval
-- Designed for the Khmer Payment Tracker and Financial Management System

-- 1. Create or alter the Users table with Status and Roles
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('super_admin', 'admin', 'user') NOT NULL DEFAULT 'user',
  `status` ENUM('active', 'suspended', 'pending') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Audit Logs Table to record critical security and administrative actions
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `action` VARCHAR(100) NOT NULL,
  `target_user_id` INT DEFAULT NULL,
  `details` TEXT,
  `ip_address` VARCHAR(45) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`target_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Dual Control Approval Table for Maker-Checker Promotion Workflow
CREATE TABLE IF NOT EXISTS `admin_approvals` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `requested_by` INT NOT NULL,
  `target_user_id` INT NOT NULL,
  `status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
  `approved_by` INT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `actioned_at` TIMESTAMP NULL,
  FOREIGN KEY (`requested_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`target_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Sample Seed Data for testing (Ensure to change default credentials in production)
-- Default passwords are 'admin123' and 'user123' (hashed using BCRYPT)
INSERT INTO `users` (`id`, `username`, `password_hash`, `role`, `status`) VALUES
(1, 'superadmin_cambodia', '$2y$10$U.p19DqCIs7XlyI.Y60GDeO/W2h2Z7mJ2RWh4Z.vA9Oa7Q5D3n.8i', 'super_admin', 'active'),
(2, 'admin_sophors', '$2y$10$U.p19DqCIs7XlyI.Y60GDeO/W2h2Z7mJ2RWh4Z.vA9Oa7Q5D3n.8i', 'admin', 'active'),
(3, 'khmer_user1', '$2y$10$fV2t8b77yG6OAtW96E2m9eEwhT1rOqB3zI0mGzN8iU67F6vGg2Cbe', 'user', 'active')
ON DUPLICATE KEY UPDATE `username`=`username`;
