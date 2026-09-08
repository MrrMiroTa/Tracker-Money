-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 06, 2026 at 08:07 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `payment_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_approvals`
--

CREATE TABLE `admin_approvals` (
  `id` int(11) NOT NULL,
  `requested_by` int(11) NOT NULL,
  `target_user_id` int(11) NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `actioned_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `target_user_id` int(11) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `target_user_id`, `details`, `ip_address`, `created_at`) VALUES
(1, 2, 'LOGIN_SUCCESS', NULL, 'Successfully signed in.', '::1', '2026-08-31 05:04:18'),
(2, 2, 'ENABLE_MFA', 2, 'User activated Google Authenticator MFA', '::1', '2026-08-31 06:40:57'),
(3, 2, 'LOGIN_SUCCESS', NULL, 'Successfully signed in.', '::1', '2026-09-01 06:47:38'),
(4, 2, 'DISABLE_MFA', 2, 'User deactivated Google Authenticator MFA', '::1', '2026-09-01 06:48:03'),
(5, 2, 'ENABLE_MFA', 2, 'User activated Google Authenticator MFA', '::1', '2026-09-01 06:48:39'),
(6, 2, 'LOGIN_SUCCESS', NULL, 'Successfully signed in.', '::1', '2026-09-02 04:33:05'),
(7, 2, 'LOGIN_SUCCESS', NULL, 'Successfully signed in.', '::1', '2026-09-02 06:06:49'),
(8, 2, 'LOGIN_SUCCESS', NULL, 'Successfully signed in.', '::1', '2026-09-02 06:19:58'),
(9, 2, 'EXPORT_CSV_REPORT', NULL, 'Exported financial transactions to CSV. Scope: ALL_RECORDS', '::1', '2026-09-02 06:20:29'),
(10, 2, 'LOGIN_SUCCESS', NULL, 'Successfully signed in.', '::1', '2026-09-02 06:30:50'),
(11, 2, 'ADD_TRANSACTION', NULL, 'Added transaction ID: 4 (Yesss) of amount 50 USD [Type: expense].', '::1', '2026-09-03 06:28:51'),
(12, 2, 'EXPORT_PDF_REPORT', NULL, 'Exported financial transaction PDF report. Scope: ALL_RECORDS', '::1', '2026-09-03 06:54:42'),
(13, 2, 'EXPORT_PDF_REPORT', NULL, 'Exported financial transaction PDF report. Scope: ALL_RECORDS', '::1', '2026-09-03 06:54:43'),
(14, 2, 'EXPORT_PDF_REPORT', NULL, 'Exported financial transaction PDF report. Scope: ALL_RECORDS', '::1', '2026-09-03 06:54:43'),
(15, 2, 'EXPORT_PDF_REPORT', NULL, 'Exported financial transaction PDF report. Scope: ALL_RECORDS', '::1', '2026-09-03 06:55:03'),
(16, 2, 'LOGIN_SUCCESS', NULL, 'Successfully signed in.', '::1', '2026-09-03 06:55:50'),
(17, 2, 'EXPORT_PDF_REPORT', NULL, 'Exported financial transaction PDF report. Scope: ALL_RECORDS', '::1', '2026-09-03 07:51:39'),
(18, 2, 'LOGIN_SUCCESS', NULL, 'Successfully signed in.', '::1', '2026-09-04 06:28:18'),
(19, 2, 'EXPORT_PDF_REPORT', NULL, 'Exported financial transaction PDF report. Scope: ALL_RECORDS', '::1', '2026-09-04 06:29:29'),
(20, 2, 'EXPORT_PDF_REPORT', NULL, 'Exported financial transaction PDF report. Scope: ALL_RECORDS', '::1', '2026-09-04 06:29:33'),
(21, 2, 'EXPORT_PDF_REPORT', NULL, 'Exported financial transaction PDF report. Scope: ALL_RECORDS', '::1', '2026-09-04 06:29:34'),
(22, 2, 'EXPORT_PDF_REPORT', NULL, 'Exported financial transaction PDF report. Scope: ALL_RECORDS', '::1', '2026-09-04 06:29:34'),
(23, 2, 'ADD_TRANSACTION', NULL, 'Added transaction ID: 5 (Laurel Lucas) of amount 31 USD [Type: expense].', '::1', '2026-09-04 06:44:09'),
(24, 2, 'EXPORT_PDF_REPORT', NULL, 'Exported financial transaction PDF report. Scope: ALL_RECORDS', '::1', '2026-09-06 02:55:11'),
(25, 2, 'LOGIN_SUCCESS', NULL, 'Successfully signed in.', '::1', '2026-09-06 02:56:09'),
(26, 1, 'LOGIN_SUCCESS', NULL, 'Successfully signed in.', '::1', '2026-09-06 04:08:17'),
(27, 1, 'LOGIN_SUCCESS', NULL, 'Successfully signed in.', '::1', '2026-09-06 04:18:47'),
(28, 2, 'LOGIN_SUCCESS', NULL, 'Successfully signed in.', '::1', '2026-09-06 04:19:41'),
(29, 2, 'LOGIN_SUCCESS', NULL, 'Successfully signed in.', '::1', '2026-09-06 04:27:05'),
(30, 2, 'CREATE_USER', 4, 'Created new user account: \'user1\' with role: \'user\'', '::1', '2026-09-06 04:27:29'),
(31, 4, 'LOGIN_SUCCESS', NULL, 'Successfully signed in.', '::1', '2026-09-06 04:27:51'),
(32, 2, 'LOGIN_SUCCESS', NULL, 'Successfully signed in.', '::1', '2026-09-06 05:21:48'),
(33, 2, 'EXPORT_PDF_REPORT', NULL, 'Exported financial transaction report. Scope: ALL_RECORDS', '::1', '2026-09-06 06:06:27'),
(34, 2, 'EXPORT_CSV_REPORT', NULL, 'Exported financial transactions to CSV. Scope: ALL_RECORDS', '::1', '2026-09-06 06:06:31');

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `description` varchar(255) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `currency` enum('KHR','USD') NOT NULL,
  `type` enum('income','expense') NOT NULL,
  `category` varchar(100) NOT NULL,
  `is_deleted` tinyint(1) DEFAULT 0,
  `date` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`id`, `user_id`, `description`, `amount`, `currency`, `type`, `category`, `is_deleted`, `date`, `created_at`) VALUES
(1, 2, 'Meat loaf', 3000.00, 'KHR', 'expense', 'ម្ហូបអាហារ', 0, '2026-08-20 09:12:00', '2026-09-01 07:17:59'),
(2, 2, 'Opening Salary', 1500.00, 'USD', 'income', 'ប្រាក់ខែ', 0, '2026-08-28 08:00:00', '2026-09-01 07:17:59'),
(3, 2, 'Office Supplies', 45000.00, 'KHR', 'expense', 'សម្ភារៈការិយាល័យ', 0, '2026-08-29 14:30:00', '2026-09-01 07:17:59'),
(4, 2, 'Yesss', 50.00, 'USD', 'expense', 'Food', 0, '2026-09-03 13:23:00', '2026-09-03 06:28:51'),
(5, 2, 'Laurel Lucas', 31.00, 'USD', 'expense', 'Quod dolor sint temp', 0, '2026-09-04 02:02:00', '2026-09-04 06:44:09');

-- --------------------------------------------------------

--
-- Table structure for table `transaction_history`
--

CREATE TABLE `transaction_history` (
  `id` int(11) NOT NULL,
  `transaction_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `original_description` varchar(255) NOT NULL,
  `original_amount` decimal(15,2) NOT NULL,
  `original_currency` enum('KHR','USD') NOT NULL,
  `original_type` enum('income','expense') NOT NULL,
  `original_category` varchar(100) NOT NULL,
  `new_description` varchar(255) DEFAULT NULL,
  `new_amount` decimal(15,2) DEFAULT NULL,
  `new_currency` enum('KHR','USD') DEFAULT NULL,
  `new_type` enum('income','expense') DEFAULT NULL,
  `new_category` varchar(100) DEFAULT NULL,
  `action_type` enum('UPDATE','DELETE','RESTORE') NOT NULL,
  `actioned_by` int(11) NOT NULL,
  `actioned_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('super_admin','admin','user') NOT NULL DEFAULT 'user',
  `status` enum('active','suspended','pending') NOT NULL DEFAULT 'active',
  `mfa_secret` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password_hash`, `role`, `status`, `mfa_secret`, `created_at`) VALUES
(1, 'superadmin_cambodia', '$2y$10$90idCMGub0jadwISIxxD5eIzM0d3lcYg7/q5KvmcrAqO3IaOWX56K', 'super_admin', 'active', NULL, '2026-08-31 05:03:45'),
(2, 'admin_sophors', '$2y$10$90idCMGub0jadwISIxxD5eIzM0d3lcYg7/q5KvmcrAqO3IaOWX56K', 'admin', 'active', 'FS4UEAOXNKOT4C44', '2026-08-31 05:03:45'),
(3, 'khmer_user1', '$2y$10$90idCMGub0jadwISIxxD5eIzM0d3lcYg7/q5KvmcrAqO3IaOWX56K', 'user', 'active', NULL, '2026-08-31 05:03:45'),
(4, 'user1', '$2y$10$KrLWtHu/s/ZuSQsX.ZmQNeH1icqx07R2lZQpBm4J8toE5O5nGR6nq', 'user', 'active', NULL, '2026-09-06 04:27:29');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_approvals`
--
ALTER TABLE `admin_approvals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `requested_by` (`requested_by`),
  ADD KEY `target_user_id` (`target_user_id`),
  ADD KEY `approved_by` (`approved_by`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `target_user_id` (`target_user_id`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `transaction_history`
--
ALTER TABLE `transaction_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `transaction_id` (`transaction_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `actioned_by` (`actioned_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_approvals`
--
ALTER TABLE `admin_approvals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `transaction_history`
--
ALTER TABLE `transaction_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin_approvals`
--
ALTER TABLE `admin_approvals`
  ADD CONSTRAINT `admin_approvals_ibfk_1` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `admin_approvals_ibfk_2` FOREIGN KEY (`target_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `admin_approvals_ibfk_3` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `audit_logs_ibfk_2` FOREIGN KEY (`target_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `transaction_history`
--
ALTER TABLE `transaction_history`
  ADD CONSTRAINT `transaction_history_ibfk_1` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `transaction_history_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `transaction_history_ibfk_3` FOREIGN KEY (`actioned_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
