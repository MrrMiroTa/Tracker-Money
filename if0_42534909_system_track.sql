-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Host: sql306.infinityfree.com
-- Generation Time: Sep 08, 2026 at 08:03 AM
-- Server version: 11.4.13-MariaDB
-- PHP Version: 7.2.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `if0_42534909_system_track`
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
(34, 2, 'EXPORT_CSV_REPORT', NULL, 'Exported financial transactions to CSV. Scope: ALL_RECORDS', '::1', '2026-09-06 06:06:31'),
(35, 2, 'LOGIN_SUCCESS', NULL, 'Successfully signed in.', '110.74.193.44', '2026-09-06 08:11:06'),
(36, 4, 'LOGIN_SUCCESS', NULL, 'Successfully signed in.', '110.74.193.44', '2026-09-06 08:21:06'),
(37, 4, 'LOGIN_SUCCESS', NULL, 'Successfully signed in.', '110.74.193.44', '2026-09-06 09:39:20'),
(38, 2, 'CREATE_USER', 5, 'Created new user account: \'Mrr Phors\' with role: \'user\'', '110.74.193.44', '2026-09-06 10:02:41'),
(39, 5, 'LOGIN_SUCCESS', NULL, 'Successfully signed in.', '110.74.193.44', '2026-09-06 10:03:04'),
(40, 5, 'ADD_TRANSACTION', NULL, 'Added transaction ID: 6 (Salary) of amount 300 USD [Type: income].', '110.74.193.44', '2026-09-06 10:04:15'),
(41, 5, 'ADD_TRANSACTION', NULL, 'Added transaction ID: 7 (Room rent and motor) of amount 63 USD [Type: expense].', '110.74.193.44', '2026-09-06 10:04:53'),
(42, 5, 'LOGIN_SUCCESS', NULL, 'Successfully signed in.', '110.74.193.44', '2026-09-06 10:06:52'),
(43, 5, 'ADD_TRANSACTION', NULL, 'Added transaction ID: 8 (ទិញទាអាំង) of amount 4.95 USD [Type: expense].', '110.74.193.44', '2026-09-06 10:08:03'),
(44, 5, 'ADD_TRANSACTION', NULL, 'Added transaction ID: 9 (បាយថ្ងៃនិងទឹកអំពៅ) of amount 1.72 USD [Type: expense].', '110.74.193.44', '2026-09-06 10:08:54'),
(45, 5, 'ADD_TRANSACTION', NULL, 'Added transaction ID: 10 (Party team work) of amount 16.5 USD [Type: expense].', '110.74.193.44', '2026-09-06 10:09:37'),
(46, 5, 'ADD_TRANSACTION', NULL, 'Added transaction ID: 11 (Back to Vipu) of amount 2.48 USD [Type: expense].', '110.74.193.44', '2026-09-06 10:10:27'),
(47, 5, 'ADD_TRANSACTION', NULL, 'Added transaction ID: 12 (Mom, Bro Phat and Bro Phou) of amount 80 USD [Type: expense].', '110.74.193.44', '2026-09-06 10:11:13'),
(48, 5, 'ADD_TRANSACTION', NULL, 'Added transaction ID: 13 (Lunch) of amount 0.87 USD [Type: expense].', '110.74.193.44', '2026-09-06 10:11:53'),
(49, 5, 'ADD_TRANSACTION', NULL, 'Added transaction ID: 14 (ប្ដូរប្រេងម៉ាស៊ីនម៉ូតូ) of amount 6.19 USD [Type: expense].', '110.74.193.44', '2026-09-06 10:13:28'),
(50, 5, 'ADD_TRANSACTION', NULL, 'Added transaction ID: 15 (Card Phone) of amount 1.5 USD [Type: expense].', '110.74.193.44', '2026-09-06 10:15:53'),
(51, 5, 'ADD_TRANSACTION', NULL, 'Added transaction ID: 16 (Coffee) of amount 1.24 USD [Type: expense].', '110.74.193.44', '2026-09-06 10:16:16'),
(52, 5, 'ADD_TRANSACTION', NULL, 'Added transaction ID: 17 (Old Spice and soab) of amount 7.67 USD [Type: expense].', '110.74.193.44', '2026-09-06 10:17:02'),
(53, 5, 'ADD_TRANSACTION', NULL, 'Added transaction ID: 18 (Gasoline) of amount 2.18 USD [Type: expense].', '110.74.193.44', '2026-09-06 10:17:47'),
(54, 5, 'ADD_TRANSACTION', NULL, 'Added transaction ID: 19 (Bong rith (Food)) of amount 2.48 USD [Type: expense].', '110.74.193.44', '2026-09-06 10:18:43'),
(55, 5, 'ADD_TRANSACTION', NULL, 'Added transaction ID: 20 (Lunch) of amount 1.24 USD [Type: expense].', '110.74.193.44', '2026-09-06 10:19:10'),
(56, 5, 'ADD_TRANSACTION', NULL, 'Added transaction ID: 21 (នំបញ្ចុក) of amount 0.75 USD [Type: expense].', '110.74.193.44', '2026-09-06 10:19:43'),
(57, 5, 'ADD_TRANSACTION', NULL, 'Added transaction ID: 22 (មី​ កាវ ពងមាន់) of amount 1.61 USD [Type: expense].', '110.74.193.44', '2026-09-06 10:20:49'),
(58, 5, 'ADD_TRANSACTION', NULL, 'Added transaction ID: 23 (Free Fire) of amount 1.69 USD [Type: expense].', '110.74.193.44', '2026-09-06 10:22:26'),
(59, 2, 'LOGIN_SUCCESS', NULL, 'Successfully signed in.', '110.74.193.44', '2026-09-06 10:22:55'),
(60, 2, 'DELETE_TRANSACTION', NULL, 'Soft-deleted transaction ID: 5 (Laurel Lucas) of amount 31.00 USD.', '110.74.193.44', '2026-09-06 10:23:12'),
(61, 2, 'DELETE_TRANSACTION', NULL, 'Soft-deleted transaction ID: 4 (Yesss) of amount 50.00 USD.', '110.74.193.44', '2026-09-06 10:23:27'),
(62, 2, 'DELETE_TRANSACTION', NULL, 'Soft-deleted transaction ID: 3 (Office Supplies) of amount 45000.00 KHR.', '110.74.193.44', '2026-09-06 10:23:35'),
(63, 2, 'DELETE_TRANSACTION', NULL, 'Soft-deleted transaction ID: 1 (Meat loaf) of amount 3000.00 KHR.', '110.74.193.44', '2026-09-06 10:23:42'),
(64, 2, 'DELETE_TRANSACTION', NULL, 'Soft-deleted transaction ID: 2 (Opening Salary) of amount 1500.00 USD.', '110.74.193.44', '2026-09-06 10:23:46'),
(65, 2, 'CREATE_USER', 6, 'Created new user account: \'Seyha\' with role: \'user\'', '110.74.193.44', '2026-09-06 10:24:17'),
(66, 5, 'ADD_TRANSACTION', NULL, 'Added transaction ID: 24 (មីឆា) of amount 2 USD [Type: expense].', '203.144.80.245', '2026-09-06 11:09:03'),
(67, 5, 'ADD_TRANSACTION', NULL, 'Added transaction ID: 25 (នំប៉ាវ) of amount 1 USD [Type: expense].', '203.18.217.152', '2026-09-07 03:38:49'),
(68, 5, 'LOGIN_SUCCESS', NULL, 'Successfully signed in.', '110.74.193.44', '2026-09-07 06:40:11'),
(69, 2, 'LOGIN_SUCCESS', NULL, 'Successfully signed in.', '110.74.193.44', '2026-09-07 06:41:43'),
(70, 2, 'LOGIN_SUCCESS', NULL, 'Successfully signed in.', '110.74.193.44', '2026-09-07 07:48:51'),
(71, 2, 'CREATE_USER', 7, 'Created new user account: \'Chhannak\' with role: \'user\'', '110.74.193.44', '2026-09-07 07:49:15'),
(72, 4, 'LOGIN_SUCCESS', NULL, 'Successfully signed in.', '110.74.193.44', '2026-09-07 07:49:59'),
(73, 4, 'ENABLE_MFA', 4, 'User activated Google Authenticator MFA', '110.74.193.44', '2026-09-07 07:50:40'),
(74, 4, 'LOGIN_SUCCESS', NULL, 'Successfully signed in.', '110.74.193.44', '2026-09-07 07:51:06'),
(75, 4, 'DISABLE_MFA', 4, 'User deactivated Google Authenticator MFA', '110.74.193.44', '2026-09-07 07:52:07'),
(76, 5, 'LOGIN_SUCCESS', NULL, 'Successfully signed in.', '110.74.193.44', '2026-09-07 07:57:06'),
(77, 5, 'ADD_TRANSACTION', NULL, 'Added transaction ID: 26 (Black Shark T23 Airpod) of amount 10.14 USD [Type: expense].', '110.74.193.44', '2026-09-07 07:57:53'),
(78, 7, 'LOGIN_SUCCESS', NULL, 'Successfully signed in.', '110.74.193.44', '2026-09-07 08:02:09'),
(79, 7, 'EXPORT_PDF_REPORT', NULL, 'Exported financial transaction report. Scope: OWN_RECORDS_ONLY', '110.74.193.44', '2026-09-07 08:02:41'),
(80, 7, 'EXPORT_PDF_REPORT', NULL, 'Exported financial transaction report. Scope: OWN_RECORDS_ONLY', '110.74.193.44', '2026-09-07 08:05:25'),
(81, 5, 'LOGIN_SUCCESS', NULL, 'Successfully signed in.', '203.144.80.245', '2026-09-07 11:09:11'),
(82, 5, 'ADD_TRANSACTION', NULL, 'Added transaction ID: 27 (Eggs) of amount 1.61 USD [Type: expense].', '203.144.80.245', '2026-09-07 11:40:38'),
(83, 5, 'ADD_TRANSACTION', NULL, 'Added transaction ID: 28 (អគ្គិសនី) of amount 6.18 USD [Type: expense].', '203.144.80.245', '2026-09-07 12:53:07');

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
(1, 2, 'Meat loaf', '3000.00', 'KHR', 'expense', 'ម្ហូបអាហារ', 1, '2026-08-20 09:12:00', '2026-09-01 07:17:59'),
(2, 2, 'Opening Salary', '1500.00', 'USD', 'income', 'ប្រាក់ខែ', 1, '2026-08-28 08:00:00', '2026-09-01 07:17:59'),
(3, 2, 'Office Supplies', '45000.00', 'KHR', 'expense', 'សម្ភារៈការិយាល័យ', 1, '2026-08-29 14:30:00', '2026-09-01 07:17:59'),
(4, 2, 'Yesss', '50.00', 'USD', 'expense', 'Food', 1, '2026-09-03 13:23:00', '2026-09-03 06:28:51'),
(5, 2, 'Laurel Lucas', '31.00', 'USD', 'expense', 'Quod dolor sint temp', 1, '2026-09-04 02:02:00', '2026-09-04 06:44:09'),
(6, 5, 'Salary', '300.00', 'USD', 'income', 'Salary', 0, '2026-09-01 17:03:00', '2026-09-06 10:04:15'),
(7, 5, 'Room rent and motor', '63.00', 'USD', 'expense', 'Room', 0, '2026-09-02 17:04:00', '2026-09-06 10:04:53'),
(8, 5, 'ទិញទាអាំង', '4.95', 'USD', 'expense', 'Food', 0, '2026-09-05 17:06:00', '2026-09-06 10:08:03'),
(9, 5, 'បាយថ្ងៃនិងទឹកអំពៅ', '1.72', 'USD', 'expense', 'Food', 0, '2026-09-06 17:08:00', '2026-09-06 10:08:54'),
(10, 5, 'Party team work', '16.50', 'USD', 'expense', 'Party', 0, '2026-09-02 17:09:00', '2026-09-06 10:09:37'),
(11, 5, 'Back to Vipu', '2.48', 'USD', 'expense', 'Viphu', 0, '2026-09-01 17:10:00', '2026-09-06 10:10:27'),
(12, 5, 'Mom, Bro Phat and Bro Phou', '80.00', 'USD', 'expense', 'Home', 0, '2026-09-01 17:11:00', '2026-09-06 10:11:13'),
(13, 5, 'Lunch', '0.87', 'USD', 'expense', 'Lunch', 0, '2026-09-02 17:11:00', '2026-09-06 10:11:53'),
(14, 5, 'ប្ដូរប្រេងម៉ាស៊ីនម៉ូតូ', '6.19', 'USD', 'expense', 'Motor', 0, '2026-09-02 17:13:00', '2026-09-06 10:13:28'),
(15, 5, 'Card Phone', '1.50', 'USD', 'expense', 'Phone', 0, '2026-09-05 17:15:00', '2026-09-06 10:15:53'),
(16, 5, 'Coffee', '1.24', 'USD', 'expense', 'Coffee', 0, '2026-09-04 17:16:00', '2026-09-06 10:16:16'),
(17, 5, 'Old Spice and soab', '7.67', 'USD', 'expense', 'Personal', 0, '2026-09-03 17:16:00', '2026-09-06 10:17:02'),
(18, 5, 'Gasoline', '2.18', 'USD', 'expense', 'Motor', 0, '2026-09-03 17:17:00', '2026-09-06 10:17:47'),
(19, 5, 'Bong rith (Food)', '2.48', 'USD', 'expense', 'Bong rith', 0, '2026-09-03 17:18:00', '2026-09-06 10:18:43'),
(20, 5, 'Lunch', '1.24', 'USD', 'expense', 'Food', 0, '2026-09-03 17:19:00', '2026-09-06 10:19:10'),
(21, 5, 'នំបញ្ចុក', '0.75', 'USD', 'expense', 'Breakfast', 0, '2026-09-03 17:19:00', '2026-09-06 10:19:43'),
(22, 5, 'មី​ កាវ ពងមាន់', '1.61', 'USD', 'expense', 'Food', 0, '2026-09-03 17:20:00', '2026-09-06 10:20:49'),
(23, 5, 'Free Fire', '1.69', 'USD', 'expense', 'Game', 0, '2026-09-05 17:22:00', '2026-09-06 10:22:26'),
(24, 5, 'មីឆា', '2.00', 'USD', 'expense', 'Dinner', 0, '2026-09-06 18:08:00', '2026-09-06 11:09:03'),
(25, 5, 'នំប៉ាវ', '1.00', 'USD', 'expense', 'Breakfast', 0, '2026-09-07 10:38:00', '2026-09-07 03:38:49'),
(26, 5, 'Black Shark T23 Airpod', '10.14', 'USD', 'expense', 'Phone', 0, '2026-09-07 14:57:00', '2026-09-07 07:57:53'),
(27, 5, 'Eggs', '1.61', 'USD', 'expense', 'Dinner', 0, '2026-09-07 18:09:00', '2026-09-07 11:40:38'),
(28, 5, 'អគ្គិសនី', '6.18', 'USD', 'expense', 'Room', 0, '2026-09-07 19:52:00', '2026-09-07 12:53:07');

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

--
-- Dumping data for table `transaction_history`
--

INSERT INTO `transaction_history` (`id`, `transaction_id`, `user_id`, `original_description`, `original_amount`, `original_currency`, `original_type`, `original_category`, `new_description`, `new_amount`, `new_currency`, `new_type`, `new_category`, `action_type`, `actioned_by`, `actioned_at`) VALUES
(1, 5, 2, 'Laurel Lucas', '31.00', 'USD', 'expense', 'Quod dolor sint temp', NULL, NULL, NULL, NULL, NULL, 'DELETE', 2, '2026-09-06 10:23:12'),
(2, 4, 2, 'Yesss', '50.00', 'USD', 'expense', 'Food', NULL, NULL, NULL, NULL, NULL, 'DELETE', 2, '2026-09-06 10:23:27'),
(3, 3, 2, 'Office Supplies', '45000.00', 'KHR', 'expense', 'សម្ភារៈការិយាល័យ', NULL, NULL, NULL, NULL, NULL, 'DELETE', 2, '2026-09-06 10:23:35'),
(4, 1, 2, 'Meat loaf', '3000.00', 'KHR', 'expense', 'ម្ហូបអាហារ', NULL, NULL, NULL, NULL, NULL, 'DELETE', 2, '2026-09-06 10:23:42'),
(5, 2, 2, 'Opening Salary', '1500.00', 'USD', 'income', 'ប្រាក់ខែ', NULL, NULL, NULL, NULL, NULL, 'DELETE', 2, '2026-09-06 10:23:46');

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
(4, 'user1', '$2y$10$KrLWtHu/s/ZuSQsX.ZmQNeH1icqx07R2lZQpBm4J8toE5O5nGR6nq', 'user', 'active', NULL, '2026-09-06 04:27:29'),
(5, 'Mrr Phors', '$2y$10$57O3a7GzQxSMCgllXwezm.hinUEexov4iSWrQ2Wk5CzSBbody3Wbi', 'user', 'active', NULL, '2026-09-06 10:02:41'),
(6, 'Seyha', '$2y$10$Zy7IVr9IUD9THskGhkUyWuvVyagHrbKYRpiMsAHwhZ74g4QcD73Fa', 'user', 'active', NULL, '2026-09-06 10:24:17'),
(7, 'Chhannak', '$2y$10$Oq019yzXdvGu/t6JGJBPhuiZNdnGrsxmVP6qnszdBcLLYKmmVAcwq', 'user', 'active', NULL, '2026-09-07 07:49:15');

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=84;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `transaction_history`
--
ALTER TABLE `transaction_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

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
