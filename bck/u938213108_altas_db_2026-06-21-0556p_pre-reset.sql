-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Jun 21, 2026 at 09:55 AM
-- Server version: 8.4.3
-- PHP Version: 8.3.16

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `u938213108_altas_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `carts`
--

CREATE TABLE `carts` (
  `id` int UNSIGNED NOT NULL,
  `member_id` int UNSIGNED NOT NULL,
  `status` enum('active','abandoned','converted') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `carts`
--

INSERT INTO `carts` (`id`, `member_id`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 'abandoned', '2026-06-19 00:37:21', '2026-06-19 00:37:21'),
(2, 1, 'abandoned', '2026-06-19 00:53:13', '2026-06-19 00:53:13'),
(3, 2, 'converted', '2026-06-19 01:03:19', '2026-06-19 01:03:19'),
(4, 2, 'converted', '2026-06-19 01:03:19', '2026-06-19 01:03:19'),
(5, 2, 'converted', '2026-06-19 01:03:56', '2026-06-19 01:03:56'),
(6, 2, 'converted', '2026-06-19 01:03:56', '2026-06-19 01:03:56'),
(7, 2, 'converted', '2026-06-19 01:03:56', '2026-06-19 01:03:56'),
(8, 2, 'abandoned', '2026-06-19 01:03:56', '2026-06-19 01:04:20'),
(9, 2, 'converted', '2026-06-19 01:04:20', '2026-06-19 01:04:20'),
(10, 2, 'converted', '2026-06-19 01:04:20', '2026-06-19 01:04:20'),
(11, 2, 'converted', '2026-06-19 01:04:20', '2026-06-19 01:04:20'),
(12, 2, 'active', '2026-06-19 01:04:20', '2026-06-19 01:04:20'),
(13, 3, 'converted', '2026-06-19 01:53:01', '2026-06-20 11:35:33'),
(14, 1, 'active', '2026-06-20 02:57:39', '2026-06-20 02:57:39'),
(15, 5, 'converted', '2026-06-20 04:36:23', '2026-06-20 04:41:05'),
(16, 5, 'converted', '2026-06-20 04:41:05', '2026-06-20 09:08:39'),
(17, 5, 'active', '2026-06-20 09:08:39', '2026-06-20 09:08:39'),
(18, 3, 'active', '2026-06-20 11:35:33', '2026-06-20 11:35:33'),
(19, 22, 'converted', '2026-06-20 16:27:45', '2026-06-21 00:08:31'),
(20, 22, 'active', '2026-06-21 00:08:31', '2026-06-21 00:08:31');

-- --------------------------------------------------------

--
-- Table structure for table `cart_items`
--

CREATE TABLE `cart_items` (
  `id` int UNSIGNED NOT NULL,
  `cart_id` int UNSIGNED NOT NULL,
  `product_id` int UNSIGNED NOT NULL,
  `quantity` int UNSIGNED NOT NULL DEFAULT '1',
  `unit_price` decimal(12,2) NOT NULL,
  `unit_pv` decimal(14,2) NOT NULL,
  `added_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `cart_items`
--

INSERT INTO `cart_items` (`id`, `cart_id`, `product_id`, `quantity`, `unit_price`, `unit_pv`, `added_at`, `updated_at`) VALUES
(3, 3, 7, 2, 1000.00, 100.00, '2026-06-19 01:03:19', '2026-06-19 01:03:19'),
(4, 4, 7, 1, 1000.00, 100.00, '2026-06-19 01:03:19', '2026-06-19 01:03:19'),
(5, 5, 7, 2, 1000.00, 100.00, '2026-06-19 01:03:56', '2026-06-19 01:03:56'),
(6, 6, 7, 1, 1000.00, 100.00, '2026-06-19 01:03:56', '2026-06-19 01:03:56'),
(7, 7, 7, 1, 1000.00, 100.00, '2026-06-19 01:03:56', '2026-06-19 01:03:56'),
(10, 11, 7, 1, 1000.00, 100.00, '2026-06-19 01:04:20', '2026-06-19 01:04:20'),
(11, 12, 7, 1, 1000.00, 100.00, '2026-06-19 01:04:20', '2026-06-19 01:04:20'),
(17, 18, 7, 1, 1000.00, 100.00, '2026-06-20 16:26:35', '2026-06-20 16:26:35');

-- --------------------------------------------------------

--
-- Table structure for table `cd_ledger`
--

CREATE TABLE `cd_ledger` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `cd_status_id` int NOT NULL,
  `commission_id` int DEFAULT NULL,
  `type` enum('pairing','direct_referral','indirect_referral') COLLATE utf8mb4_unicode_ci NOT NULL,
  `gross_amount` decimal(12,2) NOT NULL,
  `cd_amount` decimal(12,2) NOT NULL,
  `withdrawable_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `source_user_id` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `commissions`
--

CREATE TABLE `commissions` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `type` enum('pairing','direct_referral','indirect_referral','daily_fixed_income') COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `cap_deduction` decimal(12,2) NOT NULL DEFAULT '0.00',
  `source_user_id` int UNSIGNED DEFAULT NULL,
  `level` tinyint UNSIGNED DEFAULT NULL,
  `pairs_count` tinyint UNSIGNED DEFAULT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('credited','flushed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'credited',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `commissions`
--

INSERT INTO `commissions` (`id`, `user_id`, `type`, `amount`, `cap_deduction`, `source_user_id`, `level`, `pairs_count`, `description`, `status`, `created_at`) VALUES
(1, 1, 'direct_referral', 0.00, 1000.00, 2, NULL, NULL, 'Direct referral blocked — lifetime cap reached', 'flushed', '2026-06-13 05:40:32'),
(2, 1, 'direct_referral', 1000.00, 1000.00, 2, NULL, NULL, 'Direct referral bonus', 'credited', '2026-06-13 05:40:32'),
(3, 1, 'direct_referral', 0.00, 1000.00, 2, NULL, NULL, 'Direct referral blocked — lifetime cap reached', 'flushed', '2026-06-13 05:40:32'),
(4, 2, 'direct_referral', 1000.00, 0.00, 3, NULL, NULL, 'Direct referral bonus', 'credited', '2026-06-13 06:22:57'),
(5, 2, 'pairing', 1500.00, 0.00, 5, NULL, 1, '1 pair(s) × ₱1,500.00', 'credited', '2026-06-13 09:38:25'),
(6, 3, 'direct_referral', 1000.00, 0.00, 5, NULL, NULL, 'Direct referral bonus', 'credited', '2026-06-13 09:38:25'),
(8, 2, 'daily_fixed_income', 33.00, 0.00, NULL, NULL, NULL, 'Daily Fixed Income — Day 1', 'credited', '2026-06-14 02:23:56'),
(9, 3, 'daily_fixed_income', 33.00, 0.00, NULL, NULL, NULL, 'Daily Fixed Income — Day 1', 'credited', '2026-06-14 02:23:56'),
(10, 5, 'daily_fixed_income', 33.00, 0.00, NULL, NULL, NULL, 'Daily Fixed Income — Day 1', 'credited', '2026-06-14 02:23:56'),
(15, 3, 'direct_referral', 500.00, 0.00, 14, NULL, NULL, 'Direct referral bonus', 'credited', '2026-06-15 07:51:03'),
(16, 3, 'pairing', 600.00, 0.00, 16, NULL, 1, '2,000.00 PV paired → ₱600.00', 'credited', '2026-06-15 12:20:10'),
(17, 3, 'direct_referral', 2000.00, 0.00, 16, NULL, NULL, 'Direct referral bonus', 'credited', '2026-06-15 12:20:10'),
(18, 3, 'pairing', 600.00, 0.00, 17, NULL, 1, '2,000.00 PV paired → ₱600.00', 'credited', '2026-06-15 23:19:35'),
(19, 16, 'direct_referral', 2000.00, 0.00, 17, NULL, NULL, 'Direct referral bonus', 'credited', '2026-06-15 23:19:35'),
(20, 16, 'pairing', 600.00, 0.00, 18, NULL, 1, '2,000.00 PV paired → ₱600.00', 'credited', '2026-06-15 23:23:20'),
(21, 3, 'pairing', 600.00, 0.00, 18, NULL, 1, '2,000.00 PV paired → ₱600.00', 'credited', '2026-06-15 23:23:20'),
(22, 16, 'direct_referral', 2000.00, 0.00, 18, NULL, NULL, 'Direct referral bonus', 'credited', '2026-06-15 23:23:21'),
(23, 14, 'direct_referral', 2000.00, 0.00, 19, NULL, NULL, 'Direct referral bonus', 'credited', '2026-06-16 03:19:23'),
(24, 14, 'pairing', 2000.00, 0.00, 20, NULL, 1, '2,000.00 PV paired → ₱2,000.00', 'credited', '2026-06-16 03:21:17'),
(25, 14, 'direct_referral', 2000.00, 0.00, 20, NULL, NULL, 'Direct referral bonus', 'credited', '2026-06-16 03:21:18'),
(26, 2, 'daily_fixed_income', 33.00, 0.00, NULL, NULL, NULL, 'Daily Fixed Income — Day 2', 'credited', '2026-06-16 03:22:19'),
(27, 3, 'daily_fixed_income', 33.00, 0.00, NULL, NULL, NULL, 'Daily Fixed Income — Day 2', 'credited', '2026-06-16 03:22:19'),
(28, 5, 'daily_fixed_income', 33.00, 0.00, NULL, NULL, NULL, 'Daily Fixed Income — Day 2', 'credited', '2026-06-16 03:22:19'),
(29, 14, 'daily_fixed_income', 500.00, 0.00, NULL, NULL, NULL, 'Daily Fixed Income — Day 1', 'credited', '2026-06-16 03:22:19'),
(30, 16, 'daily_fixed_income', 33.00, 0.00, NULL, NULL, NULL, 'Daily Fixed Income — Day 1', 'credited', '2026-06-16 03:22:19'),
(31, 17, 'daily_fixed_income', 33.00, 0.00, NULL, NULL, NULL, 'Daily Fixed Income — Day 1', 'credited', '2026-06-16 03:22:19'),
(32, 18, 'daily_fixed_income', 33.00, 0.00, NULL, NULL, NULL, 'Daily Fixed Income — Day 1', 'credited', '2026-06-16 03:22:19'),
(33, 19, 'daily_fixed_income', 33.00, 0.00, NULL, NULL, NULL, 'Daily Fixed Income — Day 1', 'credited', '2026-06-16 03:22:19'),
(34, 20, 'daily_fixed_income', 33.00, 0.00, NULL, NULL, NULL, 'Daily Fixed Income — Day 1', 'credited', '2026-06-16 03:22:19'),
(35, 19, 'direct_referral', 2000.00, 0.00, 21, NULL, NULL, 'Direct referral bonus', 'credited', '2026-06-16 03:25:51'),
(36, 19, 'pairing', 2000.00, 0.00, 22, NULL, 1, '2,000.00 PV paired → ₱2,000.00', 'credited', '2026-06-16 03:30:52'),
(37, 19, 'direct_referral', 1000.00, 0.00, 22, NULL, NULL, 'Direct referral bonus', 'credited', '2026-06-16 03:30:53'),
(38, 19, 'indirect_referral', 300.00, 0.00, 22, 1, NULL, 'Unilevel Level 1 Bonus', 'credited', '2026-06-16 03:30:53'),
(39, 14, 'indirect_referral', 200.00, 0.00, 22, 2, NULL, 'Unilevel Level 2 Bonus', 'credited', '2026-06-16 03:30:53'),
(40, 3, 'indirect_referral', 150.00, 0.00, 22, 3, NULL, 'Unilevel Level 3 Bonus', 'credited', '2026-06-16 03:30:53'),
(41, 2, 'indirect_referral', 100.00, 0.00, 22, 4, NULL, 'Unilevel Level 4 Bonus', 'credited', '2026-06-16 03:30:53'),
(42, 1, 'indirect_referral', 0.00, 100.00, 22, 5, NULL, 'Unilevel L5 blocked — lifetime cap reached', 'flushed', '2026-06-16 03:30:53'),
(43, 1, 'indirect_referral', 100.00, 100.00, 22, 5, NULL, 'Unilevel Level 5 Bonus', 'credited', '2026-06-16 03:30:53'),
(44, 1, 'indirect_referral', 0.00, 100.00, 22, 5, NULL, 'Unilevel L5 blocked — lifetime cap reached', 'flushed', '2026-06-16 03:30:53');

-- --------------------------------------------------------

--
-- Table structure for table `daily_fixed_income_log`
--

CREATE TABLE `daily_fixed_income_log` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `day_number` int UNSIGNED NOT NULL,
  `cap_status_at_payout` enum('active','capped','perminact') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `cap_remaining` decimal(14,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `daily_fixed_income_log`
--

INSERT INTO `daily_fixed_income_log` (`id`, `user_id`, `amount`, `day_number`, `cap_status_at_payout`, `cap_remaining`, `created_at`) VALUES
(1, 2, 33.00, 1, 'active', 27467.00, '2026-06-14 02:23:56'),
(2, 3, 33.00, 1, 'active', 28967.00, '2026-06-14 02:23:56'),
(3, 5, 33.00, 1, 'active', 29967.00, '2026-06-14 02:23:56'),
(4, 2, 33.00, 2, 'active', 27434.00, '2026-06-16 03:22:19'),
(5, 3, 33.00, 2, 'active', 24634.00, '2026-06-16 03:22:19'),
(6, 5, 33.00, 2, 'active', 29934.00, '2026-06-16 03:22:19'),
(7, 14, 500.00, 1, 'active', 23500.00, '2026-06-16 03:22:19'),
(8, 16, 33.00, 1, 'active', 25367.00, '2026-06-16 03:22:19'),
(9, 17, 33.00, 1, 'active', 29967.00, '2026-06-16 03:22:19'),
(10, 18, 33.00, 1, 'active', 29967.00, '2026-06-16 03:22:19'),
(11, 19, 33.00, 1, 'active', 29967.00, '2026-06-16 03:22:19'),
(12, 20, 33.00, 1, 'active', 29967.00, '2026-06-16 03:22:19');

-- --------------------------------------------------------

--
-- Table structure for table `ewallet_admin_topups`
--

CREATE TABLE `ewallet_admin_topups` (
  `id` int UNSIGNED NOT NULL,
  `admin_id` int UNSIGNED NOT NULL,
  `recipient_id` int UNSIGNED NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ewallet_admin_topups`
--

INSERT INTO `ewallet_admin_topups` (`id`, `admin_id`, `recipient_id`, `amount`, `note`, `created_at`) VALUES
(1, 1, 1, 100000.00, '', '2026-06-13 05:37:50'),
(2, 1, 2, 10000.00, '', '2026-06-17 11:14:39'),
(3, 1, 5, 10000.00, '', '2026-06-20 09:01:49');

-- --------------------------------------------------------

--
-- Table structure for table `ewallet_ledger`
--

CREATE TABLE `ewallet_ledger` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `type` enum('credit','debit') COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `reference_id` int UNSIGNED DEFAULT NULL,
  `ref_type` enum('commission','payout','reactivation','transfer','topup','registration') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `balance_after` decimal(14,2) NOT NULL,
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ewallet_ledger`
--

INSERT INTO `ewallet_ledger` (`id`, `user_id`, `type`, `amount`, `reference_id`, `ref_type`, `balance_after`, `note`, `created_at`) VALUES
(1, 1, 'credit', 100000.00, 1, 'topup', 100000.00, 'Admin top-up by @admin', '2026-06-13 05:37:50'),
(2, 1, 'debit', 10000.00, 0, 'registration', 90000.00, 'Entry fee for new member @altas01', '2026-06-13 05:40:32'),
(3, 1, 'credit', 10000.00, 0, 'registration', 100000.00, 'Entry fee from @altas01 (registered by @admin)', '2026-06-13 05:40:32'),
(4, 1, 'debit', 10000.00, 0, 'registration', 90000.00, 'Entry fee for new member @altas02', '2026-06-13 06:22:57'),
(5, 1, 'credit', 10000.00, 0, 'registration', 100000.00, 'Entry fee from @altas02 (registered by @admin)', '2026-06-13 06:22:57'),
(6, 2, 'credit', 1000.00, 4, 'commission', 1000.00, 'Direct referral bonus', '2026-06-13 06:22:57'),
(7, 1, 'debit', 10000.00, 0, 'registration', 90000.00, 'Entry fee for new member @altas03', '2026-06-13 09:38:25'),
(8, 1, 'credit', 10000.00, 0, 'registration', 100000.00, 'Entry fee from @altas03 (registered by @admin)', '2026-06-13 09:38:25'),
(9, 2, 'credit', 1500.00, 5, 'commission', 2500.00, 'Pairing bonus — 1 pair(s)', '2026-06-13 09:38:25'),
(10, 3, 'credit', 1000.00, 6, 'commission', 1000.00, 'Direct referral bonus', '2026-06-13 09:38:25'),
(12, 2, 'credit', 33.00, 8, 'commission', 2533.00, 'Daily Fixed Income — Day 1', '2026-06-14 02:23:56'),
(13, 3, 'credit', 33.00, 9, 'commission', 1033.00, 'Daily Fixed Income — Day 1', '2026-06-14 02:23:56'),
(14, 5, 'credit', 33.00, 10, 'commission', 33.00, 'Daily Fixed Income — Day 1', '2026-06-14 02:23:56'),
(18, 1, 'debit', 10000.00, 0, 'registration', 90000.00, 'Entry fee for new member @altas05', '2026-06-15 07:51:02'),
(19, 1, 'credit', 10000.00, 0, 'registration', 100000.00, 'Entry fee from @altas05 (registered by @admin)', '2026-06-15 07:51:02'),
(20, 3, 'credit', 500.00, 15, 'commission', 1533.00, 'Direct referral bonus', '2026-06-15 07:51:03'),
(21, 1, 'debit', 10000.00, 0, 'registration', 90000.00, 'Entry fee for new member @altas06', '2026-06-15 12:20:09'),
(22, 1, 'credit', 10000.00, 0, 'registration', 100000.00, 'Entry fee from @altas06 (registered by @admin)', '2026-06-15 12:20:09'),
(23, 3, 'credit', 600.00, 16, 'commission', 2133.00, '2,000.00 PV pairing bonus', '2026-06-15 12:20:10'),
(24, 3, 'credit', 2000.00, 17, 'commission', 4133.00, 'Direct referral bonus', '2026-06-15 12:20:10'),
(25, 1, 'debit', 10000.00, 0, 'registration', 90000.00, 'Entry fee for new member @altas07', '2026-06-15 23:19:35'),
(26, 1, 'credit', 10000.00, 0, 'registration', 100000.00, 'Entry fee from @altas07 (registered by @admin)', '2026-06-15 23:19:35'),
(27, 3, 'credit', 600.00, 18, 'commission', 4733.00, '2,000.00 PV pairing bonus', '2026-06-15 23:19:35'),
(28, 16, 'credit', 2000.00, 19, 'commission', 2000.00, 'Direct referral bonus', '2026-06-15 23:19:35'),
(29, 1, 'debit', 10000.00, 0, 'registration', 90000.00, 'Entry fee for new member @altas08', '2026-06-15 23:23:20'),
(30, 1, 'credit', 10000.00, 0, 'registration', 100000.00, 'Entry fee from @altas08 (registered by @admin)', '2026-06-15 23:23:20'),
(31, 16, 'credit', 600.00, 20, 'commission', 2600.00, '2,000.00 PV pairing bonus', '2026-06-15 23:23:20'),
(32, 3, 'credit', 600.00, 21, 'commission', 5333.00, '2,000.00 PV pairing bonus', '2026-06-15 23:23:20'),
(33, 16, 'credit', 2000.00, 22, 'commission', 4600.00, 'Direct referral bonus', '2026-06-15 23:23:21'),
(34, 1, 'debit', 10000.00, 0, 'registration', 90000.00, 'Entry fee for new member @altas09', '2026-06-16 03:19:22'),
(35, 1, 'credit', 10000.00, 0, 'registration', 100000.00, 'Entry fee from @altas09 (registered by @admin)', '2026-06-16 03:19:22'),
(36, 14, 'credit', 2000.00, 23, 'commission', 2000.00, 'Direct referral bonus', '2026-06-16 03:19:23'),
(37, 1, 'debit', 10000.00, 0, 'registration', 90000.00, 'Entry fee for new member @altas10', '2026-06-16 03:21:17'),
(38, 1, 'credit', 10000.00, 0, 'registration', 100000.00, 'Entry fee from @altas10 (registered by @admin)', '2026-06-16 03:21:17'),
(39, 14, 'credit', 2000.00, 24, 'commission', 4000.00, '2,000.00 PV pairing bonus', '2026-06-16 03:21:17'),
(40, 14, 'credit', 2000.00, 25, 'commission', 6000.00, 'Direct referral bonus', '2026-06-16 03:21:18'),
(41, 2, 'credit', 33.00, 26, 'commission', 2566.00, 'Daily Fixed Income — Day 2', '2026-06-16 03:22:19'),
(42, 3, 'credit', 33.00, 27, 'commission', 5366.00, 'Daily Fixed Income — Day 2', '2026-06-16 03:22:19'),
(43, 5, 'credit', 33.00, 28, 'commission', 66.00, 'Daily Fixed Income — Day 2', '2026-06-16 03:22:19'),
(44, 14, 'credit', 500.00, 29, 'commission', 6500.00, 'Daily Fixed Income — Day 1', '2026-06-16 03:22:19'),
(45, 16, 'credit', 33.00, 30, 'commission', 4633.00, 'Daily Fixed Income — Day 1', '2026-06-16 03:22:19'),
(46, 17, 'credit', 33.00, 31, 'commission', 33.00, 'Daily Fixed Income — Day 1', '2026-06-16 03:22:19'),
(47, 18, 'credit', 33.00, 32, 'commission', 33.00, 'Daily Fixed Income — Day 1', '2026-06-16 03:22:19'),
(48, 19, 'credit', 33.00, 33, 'commission', 33.00, 'Daily Fixed Income — Day 1', '2026-06-16 03:22:19'),
(49, 20, 'credit', 33.00, 34, 'commission', 33.00, 'Daily Fixed Income — Day 1', '2026-06-16 03:22:19'),
(50, 1, 'debit', 10000.00, 0, 'registration', 90000.00, 'Entry fee for new member @altas11', '2026-06-16 03:25:51'),
(51, 1, 'credit', 10000.00, 0, 'registration', 100000.00, 'Entry fee from @altas11 (registered by @admin)', '2026-06-16 03:25:51'),
(52, 19, 'credit', 2000.00, 35, 'commission', 2033.00, 'Direct referral bonus', '2026-06-16 03:25:51'),
(53, 1, 'debit', 10000.00, 0, 'registration', 90000.00, 'Entry fee for new member @altas12', '2026-06-16 03:30:52'),
(54, 1, 'credit', 10000.00, 0, 'registration', 100000.00, 'Entry fee from @altas12 (registered by @admin)', '2026-06-16 03:30:52'),
(55, 19, 'credit', 2000.00, 36, 'commission', 4033.00, '2,000.00 PV pairing bonus', '2026-06-16 03:30:53'),
(56, 19, 'credit', 1000.00, 37, 'commission', 5033.00, 'Direct referral bonus', '2026-06-16 03:30:53'),
(57, 19, 'credit', 300.00, 38, 'commission', 5333.00, 'Unilevel Level 1 Bonus', '2026-06-16 03:30:53'),
(58, 14, 'credit', 200.00, 39, 'commission', 6700.00, 'Unilevel Level 2 Bonus', '2026-06-16 03:30:53'),
(59, 3, 'credit', 150.00, 40, 'commission', 5516.00, 'Unilevel Level 3 Bonus', '2026-06-16 03:30:53'),
(60, 2, 'credit', 100.00, 41, 'commission', 2666.00, 'Unilevel Level 4 Bonus', '2026-06-16 03:30:53'),
(61, 2, 'debit', 500.00, 3, 'payout', 2166.00, 'Payout via USDT_BEP20 0x1234567890abcdef1234567890abcdef12345678', '2026-06-17 08:52:34'),
(62, 2, 'credit', 10000.00, 2, 'topup', 12166.00, 'Admin top-up by @admin', '2026-06-17 11:14:39'),
(63, 2, 'debit', 10000.00, 2, 'reactivation', 2166.00, 'Account reactivation fee', '2026-06-17 11:15:00'),
(64, 5, 'credit', 10000.00, 3, 'topup', 10066.00, 'Admin top-up by @admin', '2026-06-20 09:01:49'),
(65, 5, 'debit', 1000.00, 21, 'registration', 9066.00, 'Payment for repeat purchase order #21', '2026-06-20 09:08:39');

-- --------------------------------------------------------

--
-- Table structure for table `ewallet_transfers`
--

CREATE TABLE `ewallet_transfers` (
  `id` int UNSIGNED NOT NULL,
  `sender_id` int UNSIGNED NOT NULL,
  `recipient_id` int UNSIGNED NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `fee` decimal(12,2) NOT NULL DEFAULT '0.00',
  `net_amount` decimal(12,2) NOT NULL,
  `status` enum('completed','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'completed',
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `packages`
--

CREATE TABLE `packages` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entry_fee` decimal(12,2) NOT NULL,
  `package_pv_rate` decimal(5,2) NOT NULL DEFAULT '100.00' COMMENT 'Percentage of entry fee that becomes package PV for binary/direct/indirect basis',
  `binary_pv_pct` decimal(5,2) NOT NULL DEFAULT '20.00' COMMENT 'Percentage of entry fee that becomes binary PV',
  `pairing_bonus` decimal(12,2) NOT NULL,
  `pairing_pv_pct` decimal(5,2) NOT NULL DEFAULT '0.00' COMMENT 'Pairing bonus = paired_pv * (pairing_pv_pct/100) * pv_per_peso_rate',
  `daily_pair_cap` tinyint UNSIGNED NOT NULL DEFAULT '3',
  `daily_pair_pv_cap` decimal(14,2) NOT NULL DEFAULT '0.00' COMMENT 'Max paired PV per member per day',
  `direct_ref_bonus` decimal(12,2) NOT NULL DEFAULT '0.00',
  `direct_ref_pv_pct` decimal(5,2) NOT NULL DEFAULT '0.00' COMMENT 'Direct referral bonus = package_pv * (direct_ref_pv_pct/100) * pv_per_peso_rate',
  `lifetime_cap_multiplier` decimal(5,2) NOT NULL DEFAULT '3.00',
  `reactivation_fee` decimal(12,2) NOT NULL DEFAULT '0.00',
  `reactivation_window_days` int NOT NULL DEFAULT '15',
  `daily_fixed_income` decimal(12,2) NOT NULL DEFAULT '0.00',
  `daily_fixed_income_days` int NOT NULL DEFAULT '90',
  `dfi_pv_pct` decimal(5,2) NOT NULL DEFAULT '0.00' COMMENT 'Optional DFI as % of package PV; 0 falls back to daily_fixed_income',
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `personal_pv_requirement` decimal(14,2) NOT NULL DEFAULT '0.00' COMMENT 'Minimum Personal PV an upline must have to receive Group/Binary PV from product purchases'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `packages`
--

INSERT INTO `packages` (`id`, `name`, `entry_fee`, `package_pv_rate`, `binary_pv_pct`, `pairing_bonus`, `pairing_pv_pct`, `daily_pair_cap`, `daily_pair_pv_cap`, `direct_ref_bonus`, `direct_ref_pv_pct`, `lifetime_cap_multiplier`, `reactivation_fee`, `reactivation_window_days`, `daily_fixed_income`, `daily_fixed_income_days`, `dfi_pv_pct`, `status`, `created_at`, `personal_pv_requirement`) VALUES
(1, 'Starter', 10000.00, 100.00, 20.00, 1500.00, 100.00, 5, 50000.00, 1000.00, 10.00, 3.00, 10000.00, 15, 33.00, 909, 0.00, 'active', '2026-05-30 13:32:05', 500.00),
(2, 'Test Package', 10000.00, 100.00, 20.00, 2000.00, 20.00, 3, 30000.00, 1000.00, 5.00, 3.00, 0.00, 15, 0.00, 90, 5.00, 'active', '2026-06-13 05:26:29', 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `package_indirect_levels`
--

CREATE TABLE `package_indirect_levels` (
  `id` int UNSIGNED NOT NULL,
  `package_id` int UNSIGNED NOT NULL,
  `level` tinyint UNSIGNED NOT NULL,
  `bonus` decimal(12,2) NOT NULL DEFAULT '0.00',
  `pv_pct` decimal(5,2) NOT NULL DEFAULT '0.00' COMMENT 'Indirect level bonus = package_pv * (pv_pct/100) * pv_per_peso_rate'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `package_indirect_levels`
--

INSERT INTO `package_indirect_levels` (`id`, `package_id`, `level`, `bonus`, `pv_pct`) VALUES
(41, 2, 1, 0.00, 0.00),
(42, 2, 2, 0.00, 0.00),
(43, 2, 3, 0.00, 0.00),
(44, 2, 4, 0.00, 0.00),
(45, 2, 5, 0.00, 0.00),
(46, 2, 6, 0.00, 0.00),
(47, 2, 7, 0.00, 0.00),
(48, 2, 8, 0.00, 0.00),
(49, 2, 9, 0.00, 0.00),
(50, 2, 10, 0.00, 0.00),
(71, 1, 1, 0.00, 3.00),
(72, 1, 2, 0.00, 2.00),
(73, 1, 3, 0.00, 1.50),
(74, 1, 4, 0.00, 1.00),
(75, 1, 5, 0.00, 1.00),
(76, 1, 6, 0.00, 0.50),
(77, 1, 7, 0.00, 0.50),
(78, 1, 8, 0.00, 0.50),
(79, 1, 9, 0.00, 0.50),
(80, 1, 10, 0.00, 0.50);

-- --------------------------------------------------------

--
-- Table structure for table `payout_requests`
--

CREATE TABLE `payout_requests` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `payout_method` enum('gcash','maya','usdt_trc20','usdt_bep20') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'gcash',
  `payout_account` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `service_fee_pct` decimal(5,2) NOT NULL DEFAULT '0.00',
  `service_fee_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `usdt_trc20_rate` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `usdt_trc20_gas_fee` decimal(10,4) NOT NULL DEFAULT '0.0000',
  `usdt_trc20_amount` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `usdt_bep20_rate` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `usdt_bep20_gas_fee` decimal(10,4) NOT NULL DEFAULT '0.0000',
  `usdt_bep20_amount` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `status` enum('pending','approved','rejected','completed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `admin_note` text COLLATE utf8mb4_unicode_ci,
  `processed_by` int UNSIGNED DEFAULT NULL,
  `requested_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `processed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payout_requests`
--

INSERT INTO `payout_requests` (`id`, `user_id`, `amount`, `payout_method`, `payout_account`, `service_fee_pct`, `service_fee_amount`, `usdt_trc20_rate`, `usdt_trc20_gas_fee`, `usdt_trc20_amount`, `usdt_bep20_rate`, `usdt_bep20_gas_fee`, `usdt_bep20_amount`, `status`, `admin_note`, `processed_by`, `requested_at`, `processed_at`) VALUES
(1, 1, 1000.00, 'usdt_bep20', '0x1234567890123456789012345678901234567890', 5.00, 50.00, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 0.0000, 'pending', NULL, NULL, '2026-06-17 08:16:09', NULL),
(2, 2, 500.00, 'usdt_trc20', 'TXYZ123456789ABCDEFGHJKLMNPQRSTUVWXYZ12', 5.00, 25.00, 60.3200, 0.7801, 7.0946, 0.0000, 0.0000, 0.0000, 'rejected', '', 1, '2026-06-17 08:50:54', '2026-06-17 08:51:19'),
(3, 2, 500.00, 'usdt_bep20', '0x1234567890abcdef1234567890abcdef12345678', 5.00, 25.00, 0.0000, 0.0000, 0.0000, 60.3200, 0.0500, 7.8247, 'completed', '', 1, '2026-06-17 08:51:56', '2026-06-17 08:52:34');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `price` decimal(12,2) NOT NULL,
  `pv_value` decimal(14,2) NOT NULL,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `image_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Product image path relative to uploads/',
  `short_description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Short description shown on product cards',
  `description` text COLLATE utf8mb4_unicode_ci COMMENT 'Full description shown in product detail modal',
  `stock` int UNSIGNED NOT NULL DEFAULT '0' COMMENT 'Total physical inventory'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `name`, `price`, `pv_value`, `status`, `created_at`, `updated_at`, `image_url`, `short_description`, `description`, `stock`) VALUES
(7, 'Test Product', 1000.00, 100.00, 'active', '2026-06-16 03:37:36', '2026-06-20 16:24:54', 'products/product_7_1781594966.png', 'Short description etc', 'This is a full description of the product, etc', 100);

-- --------------------------------------------------------

--
-- Table structure for table `pv_transactions`
--

CREATE TABLE `pv_transactions` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL COMMENT 'Member whose PV balance is affected',
  `type` enum('package_personal','package_group','product_personal','product_group','binary_left','binary_right','binary_paired','binary_flushed') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Type of PV movement',
  `amount` decimal(14,2) NOT NULL DEFAULT '0.00' COMMENT 'PV amount added (always positive)',
  `source_user_id` int UNSIGNED DEFAULT NULL COMMENT 'The member whose action generated this PV',
  `source_type` enum('registration','activation','repeat_purchase') COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `pv_transactions`
--

INSERT INTO `pv_transactions` (`id`, `user_id`, `type`, `amount`, `source_user_id`, `source_type`, `created_at`) VALUES
(4, 3, 'package_personal', 10000.00, 5, 'registration', '2026-06-13 09:38:25'),
(27, 3, 'binary_left', 10000.00, 14, 'registration', '2026-06-15 07:51:03'),
(28, 2, 'binary_left', 10000.00, 14, 'registration', '2026-06-15 07:51:03'),
(29, 1, 'binary_left', 10000.00, 14, 'registration', '2026-06-15 07:51:03'),
(32, 3, 'binary_right', 2000.00, 16, 'registration', '2026-06-15 12:20:10'),
(33, 3, 'binary_paired', 2000.00, 16, 'registration', '2026-06-15 12:20:10'),
(34, 2, 'binary_left', 2000.00, 16, 'registration', '2026-06-15 12:20:10'),
(35, 1, 'binary_left', 2000.00, 16, 'registration', '2026-06-15 12:20:10'),
(36, 16, 'binary_left', 2000.00, 17, 'registration', '2026-06-15 23:19:35'),
(37, 3, 'binary_right', 2000.00, 17, 'registration', '2026-06-15 23:19:35'),
(38, 3, 'binary_paired', 2000.00, 17, 'registration', '2026-06-15 23:19:35'),
(39, 2, 'binary_left', 2000.00, 17, 'registration', '2026-06-15 23:19:35'),
(40, 1, 'binary_left', 2000.00, 17, 'registration', '2026-06-15 23:19:35'),
(41, 16, 'binary_right', 2000.00, 18, 'registration', '2026-06-15 23:23:20'),
(42, 16, 'binary_paired', 2000.00, 18, 'registration', '2026-06-15 23:23:20'),
(43, 3, 'binary_right', 2000.00, 18, 'registration', '2026-06-15 23:23:20'),
(44, 3, 'binary_paired', 2000.00, 18, 'registration', '2026-06-15 23:23:20'),
(45, 2, 'binary_left', 2000.00, 18, 'registration', '2026-06-15 23:23:20'),
(46, 1, 'binary_left', 2000.00, 18, 'registration', '2026-06-15 23:23:21'),
(47, 14, 'binary_left', 2000.00, 19, 'registration', '2026-06-16 03:19:23'),
(48, 3, 'binary_left', 2000.00, 19, 'registration', '2026-06-16 03:19:23'),
(49, 2, 'binary_left', 2000.00, 19, 'registration', '2026-06-16 03:19:23'),
(50, 1, 'binary_left', 2000.00, 19, 'registration', '2026-06-16 03:19:23'),
(51, 14, 'binary_right', 2000.00, 20, 'registration', '2026-06-16 03:21:17'),
(52, 14, 'binary_paired', 2000.00, 20, 'registration', '2026-06-16 03:21:17'),
(53, 3, 'binary_left', 2000.00, 20, 'registration', '2026-06-16 03:21:18'),
(54, 2, 'binary_left', 2000.00, 20, 'registration', '2026-06-16 03:21:18'),
(55, 1, 'binary_left', 2000.00, 20, 'registration', '2026-06-16 03:21:18'),
(56, 19, 'binary_left', 2000.00, 21, 'registration', '2026-06-16 03:25:51'),
(57, 14, 'binary_left', 2000.00, 21, 'registration', '2026-06-16 03:25:51'),
(58, 3, 'binary_left', 2000.00, 21, 'registration', '2026-06-16 03:25:51'),
(59, 2, 'binary_left', 2000.00, 21, 'registration', '2026-06-16 03:25:51'),
(60, 1, 'binary_left', 2000.00, 21, 'registration', '2026-06-16 03:25:51'),
(61, 19, 'binary_right', 2000.00, 22, 'registration', '2026-06-16 03:30:52'),
(62, 19, 'binary_paired', 2000.00, 22, 'registration', '2026-06-16 03:30:53'),
(63, 14, 'binary_left', 2000.00, 22, 'registration', '2026-06-16 03:30:53'),
(64, 3, 'binary_left', 2000.00, 22, 'registration', '2026-06-16 03:30:53'),
(65, 2, 'binary_left', 2000.00, 22, 'registration', '2026-06-16 03:30:53'),
(66, 1, 'binary_left', 2000.00, 22, 'registration', '2026-06-16 03:30:53'),
(67, 2, 'product_personal', 100.00, 2, 'repeat_purchase', '2026-06-19 01:03:19'),
(68, 1, 'product_group', 100.00, 2, 'repeat_purchase', '2026-06-19 01:03:19'),
(69, 2, 'binary_left', 100.00, 2, 'repeat_purchase', '2026-06-19 01:03:19'),
(70, 2, 'product_personal', 200.00, 2, 'repeat_purchase', '2026-06-19 01:03:56'),
(71, 1, 'product_group', 200.00, 2, 'repeat_purchase', '2026-06-19 01:03:56'),
(72, 2, 'binary_right', 200.00, 2, 'repeat_purchase', '2026-06-19 01:03:56'),
(73, 1, 'binary_left', 200.00, 2, 'repeat_purchase', '2026-06-19 01:03:56'),
(74, 2, 'product_personal', 100.00, 2, 'repeat_purchase', '2026-06-19 01:03:56'),
(75, 1, 'product_group', 100.00, 2, 'repeat_purchase', '2026-06-19 01:03:56'),
(76, 2, 'binary_left', 100.00, 2, 'repeat_purchase', '2026-06-19 01:03:56'),
(77, 1, 'binary_left', 100.00, 2, 'repeat_purchase', '2026-06-19 01:03:56'),
(78, 2, 'product_personal', 200.00, 2, 'repeat_purchase', '2026-06-19 01:04:20'),
(79, 1, 'product_group', 200.00, 2, 'repeat_purchase', '2026-06-19 01:04:20'),
(80, 2, 'binary_right', 200.00, 2, 'repeat_purchase', '2026-06-19 01:04:20'),
(81, 1, 'binary_left', 200.00, 2, 'repeat_purchase', '2026-06-19 01:04:20'),
(82, 2, 'product_personal', 100.00, 2, 'repeat_purchase', '2026-06-19 01:04:20'),
(83, 1, 'product_group', 100.00, 2, 'repeat_purchase', '2026-06-19 01:04:20'),
(84, 2, 'binary_left', 100.00, 2, 'repeat_purchase', '2026-06-19 01:04:20'),
(85, 1, 'binary_left', 100.00, 2, 'repeat_purchase', '2026-06-19 01:04:20'),
(86, 5, 'product_personal', 100.00, 5, 'repeat_purchase', '2026-06-20 09:08:39'),
(87, 3, 'product_group', 100.00, 5, 'repeat_purchase', '2026-06-20 09:08:39'),
(88, 2, 'product_group', 100.00, 5, 'repeat_purchase', '2026-06-20 09:08:39'),
(89, 1, 'product_group', 100.00, 5, 'repeat_purchase', '2026-06-20 09:08:39'),
(90, 5, 'binary_left', 100.00, 5, 'repeat_purchase', '2026-06-20 09:08:39'),
(91, 2, 'binary_right', 100.00, 5, 'repeat_purchase', '2026-06-20 09:08:39'),
(92, 1, 'binary_left', 100.00, 5, 'repeat_purchase', '2026-06-20 09:08:39'),
(93, 22, 'product_personal', 100.00, 22, 'repeat_purchase', '2026-06-21 06:19:21'),
(94, 14, 'product_group', 100.00, 22, 'repeat_purchase', '2026-06-21 06:19:21'),
(95, 2, 'product_group', 100.00, 22, 'repeat_purchase', '2026-06-21 06:19:21'),
(96, 1, 'product_group', 100.00, 22, 'repeat_purchase', '2026-06-21 06:19:21'),
(97, 22, 'binary_left', 100.00, 22, 'repeat_purchase', '2026-06-21 06:19:21'),
(98, 14, 'binary_left', 100.00, 22, 'repeat_purchase', '2026-06-21 06:19:21'),
(99, 2, 'binary_left', 100.00, 22, 'repeat_purchase', '2026-06-21 06:19:21'),
(100, 1, 'binary_left', 100.00, 22, 'repeat_purchase', '2026-06-21 06:19:21');

-- --------------------------------------------------------

--
-- Table structure for table `reactivations`
--

CREATE TABLE `reactivations` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `amount_paid` decimal(12,2) NOT NULL,
  `previous_earned` decimal(14,2) NOT NULL DEFAULT '0.00',
  `package_id` int UNSIGNED NOT NULL,
  `payment_method` enum('ewallet','gcash','maya','usdt_trc20','usdt_bep20','admin') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ewallet',
  `status` enum('pending','completed','rejected') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'completed',
  `admin_note` text COLLATE utf8mb4_unicode_ci,
  `proof_image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `processed_by` int UNSIGNED DEFAULT NULL,
  `processed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `reactivations`
--

INSERT INTO `reactivations` (`id`, `user_id`, `amount_paid`, `previous_earned`, `package_id`, `payment_method`, `status`, `admin_note`, `proof_image`, `processed_by`, `processed_at`, `created_at`) VALUES
(1, 2, 10000.00, 2666.00, 1, 'usdt_bep20', 'completed', '', 'reactivation_proofs/reactivation_2_1781686736.jpg', 1, '2026-06-17 08:59:25', '2026-06-17 08:58:56'),
(2, 2, 10000.00, 0.00, 1, 'ewallet', 'completed', NULL, '', NULL, NULL, '2026-06-17 11:15:00');

-- --------------------------------------------------------

--
-- Table structure for table `reg_codes`
--

CREATE TABLE `reg_codes` (
  `id` int UNSIGNED NOT NULL,
  `code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `package_id` int UNSIGNED NOT NULL,
  `price` decimal(12,2) NOT NULL,
  `status` enum('unused','used','expired') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unused',
  `used_by` int UNSIGNED DEFAULT NULL,
  `created_by` int UNSIGNED NOT NULL,
  `used_at` timestamp NULL DEFAULT NULL,
  `expires_at` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `is_cd` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `reg_codes`
--

INSERT INTO `reg_codes` (`id`, `code`, `package_id`, `price`, `status`, `used_by`, `created_by`, `used_at`, `expires_at`, `created_at`, `is_cd`) VALUES
(1, 'L3B6-KSF8-MG3S', 1, 10000.00, 'unused', NULL, 1, NULL, NULL, '2026-06-09 04:59:08', 0),
(2, 'Q3EW-PT5W-TYTC', 1, 10000.00, 'unused', NULL, 1, NULL, NULL, '2026-06-09 04:59:08', 0),
(3, 'RF9L-29LT-TC3A', 1, 10000.00, 'unused', NULL, 1, NULL, NULL, '2026-06-09 04:59:08', 0),
(4, '7VTQ-55KZ-YAMW', 1, 10000.00, 'unused', NULL, 1, NULL, NULL, '2026-06-09 04:59:08', 0),
(5, 'UWXJ-XZAA-3WZW', 1, 10000.00, 'unused', NULL, 1, NULL, NULL, '2026-06-09 04:59:08', 0);

-- --------------------------------------------------------

--
-- Table structure for table `repeat_purchase_orders`
--

CREATE TABLE `repeat_purchase_orders` (
  `id` int UNSIGNED NOT NULL,
  `member_id` int UNSIGNED NOT NULL,
  `total_pv` decimal(14,2) NOT NULL,
  `total_price` decimal(12,2) NOT NULL,
  `binary_position` enum('left','right') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'left' COMMENT 'Side used for buyer''s own leg PV placement',
  `payment_method` enum('ewallet','gcash','maya','usdt_trc20','usdt_bep20') COLLATE utf8mb4_unicode_ci NOT NULL,
  `proof_image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('pending','paid','approved','rejected','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `approved_by` int UNSIGNED DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `repeat_purchase_orders`
--

INSERT INTO `repeat_purchase_orders` (`id`, `member_id`, `total_pv`, `total_price`, `binary_position`, `payment_method`, `proof_image`, `status`, `approved_by`, `approved_at`, `paid_at`, `created_at`) VALUES
(9, 14, 100.00, 1000.00, 'left', 'gcash', NULL, 'rejected', 1, '2026-06-20 12:39:41', NULL, '2026-06-16 07:33:46'),
(10, 2, 100.00, 1000.00, 'left', 'gcash', NULL, 'rejected', 1, '2026-06-20 12:39:29', NULL, '2026-06-17 10:27:10'),
(13, 2, 100.00, 1000.00, 'left', 'gcash', 'repeat_purchase_proofs/test.png', 'approved', 1, '2026-06-19 01:03:19', NULL, '2026-06-19 01:03:19'),
(15, 2, 100.00, 1000.00, 'left', 'gcash', 'repeat_purchase_proofs/test.png', 'approved', 1, '2026-06-19 01:03:56', NULL, '2026-06-19 01:03:56'),
(20, 5, 100.00, 1000.00, 'left', 'gcash', 'repeat_purchase_proofs/proof_5_1781930465.jpg', 'pending', NULL, NULL, NULL, '2026-06-20 04:41:05'),
(21, 5, 100.00, 1000.00, 'left', 'ewallet', NULL, 'approved', 5, '2026-06-20 09:08:39', '2026-06-20 09:08:39', '2026-06-20 09:08:39'),
(22, 3, 100.00, 1000.00, 'left', 'gcash', 'repeat_purchase_proofs/proof_3_1781955333.jpg', 'paid', 1, '2026-06-20 13:24:15', NULL, '2026-06-20 11:35:33'),
(23, 22, 100.00, 1000.00, 'left', 'gcash', 'repeat_purchase_proofs/proof_22_1782000511.png', 'approved', 1, '2026-06-21 06:19:21', NULL, '2026-06-21 00:08:31');

-- --------------------------------------------------------

--
-- Table structure for table `repeat_purchase_order_items`
--

CREATE TABLE `repeat_purchase_order_items` (
  `id` int UNSIGNED NOT NULL,
  `order_id` int UNSIGNED NOT NULL,
  `product_id` int UNSIGNED NOT NULL,
  `quantity` int UNSIGNED NOT NULL,
  `unit_price` decimal(12,2) NOT NULL,
  `unit_pv` decimal(14,2) NOT NULL,
  `total_price` decimal(12,2) NOT NULL,
  `total_pv` decimal(14,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `repeat_purchase_order_items`
--

INSERT INTO `repeat_purchase_order_items` (`id`, `order_id`, `product_id`, `quantity`, `unit_price`, `unit_pv`, `total_price`, `total_pv`) VALUES
(1, 9, 7, 1, 1000.00, 100.00, 1000.00, 100.00),
(2, 10, 7, 1, 1000.00, 100.00, 1000.00, 100.00),
(6, 13, 7, 1, 1000.00, 100.00, 1000.00, 100.00),
(8, 15, 7, 1, 1000.00, 100.00, 1000.00, 100.00),
(13, 20, 7, 1, 1000.00, 100.00, 1000.00, 100.00),
(14, 21, 7, 1, 1000.00, 100.00, 1000.00, 100.00),
(15, 22, 7, 1, 1000.00, 100.00, 1000.00, 100.00),
(16, 23, 7, 1, 1000.00, 100.00, 1000.00, 100.00);

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `key_name` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`key_name`, `value`, `updated_at`) VALUES
('binary_enabled', '1', '2026-06-09 04:59:56'),
('contact_email', 'support@altasfarm.com', '2026-05-30 13:32:05'),
('default_cap_multiplier', '3.00', '2026-05-30 13:32:05'),
('dfi_enabled', '1', '2026-05-30 13:32:05'),
('ewallet_min_transfer', '50.00', '2026-05-30 13:32:05'),
('ewallet_transfer_daily_limit', '5000', '2026-06-05 15:26:25'),
('ewallet_transfer_fee', '5.00', '2026-06-02 10:46:18'),
('ewallet_transfer_weekly_limit', '20000', '2026-06-05 15:26:25'),
('gcash_enabled', '1', '2026-05-30 13:32:05'),
('gcash_number', '09171234567', '2026-05-30 13:33:17'),
('indirect_referral_enabled', '1', '2026-06-09 05:00:02'),
('last_reset', '2026-06-16 11:22:19', '2026-06-16 03:22:19'),
('maintenance_bypass_token', '', '2026-06-05 15:26:25'),
('maintenance_mode', '0', '2026-06-05 06:54:41'),
('maya_enabled', '1', '2026-05-30 13:32:05'),
('maya_number', '09281234567', '2026-05-30 13:33:17'),
('min_payout', '500', '2026-05-30 13:32:05'),
('pv_per_peso_rate', '1.0000', '2026-06-15 07:22:09'),
('reactivation_ewallet_enabled', '1', '2026-05-30 13:32:05'),
('reactivation_external_enabled', '1', '2026-05-30 13:32:05'),
('seat_limit', '2000', '2026-05-30 13:33:17'),
('service_fee_gcash', '0', '2026-05-30 13:32:05'),
('service_fee_maya', '0', '2026-05-30 13:32:05'),
('service_fee_usdt_bep20', '5', '2026-06-17 08:14:45'),
('service_fee_usdt_trc20', '5', '2026-06-17 08:14:45'),
('site_name', 'Altas Farm', '2026-05-30 13:32:05'),
('site_tagline', 'Build Your Network. Grow Your Income.', '2026-05-30 13:32:05'),
('usdt_bep20_address', '0xExampleBep20Address123456789012345678901', '2026-06-17 08:47:16'),
('usdt_bep20_gas_fee', '0.05', '2026-06-17 08:47:16'),
('usdt_trc20_address', 'TExampleTrc20Address123456789012345678', '2026-06-17 08:47:16'),
('usdt_trc20_gas_fee', '0.7801', '2026-06-17 08:14:45');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int UNSIGNED NOT NULL,
  `username` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('member','admin') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'member',
  `package_id` int UNSIGNED DEFAULT NULL,
  `reg_code_id` int UNSIGNED DEFAULT NULL,
  `reg_payment_method` enum('code','ewallet','pending') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'code',
  `reg_paid_by` int UNSIGNED DEFAULT NULL,
  `sponsor_id` int UNSIGNED DEFAULT NULL,
  `binary_parent_id` int UNSIGNED DEFAULT NULL,
  `binary_position` enum('left','right') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `left_count` int UNSIGNED NOT NULL DEFAULT '0',
  `right_count` int UNSIGNED NOT NULL DEFAULT '0',
  `left_pv` decimal(14,2) NOT NULL DEFAULT '0.00' COMMENT 'Accumulated PV on left binary leg',
  `right_pv` decimal(14,2) NOT NULL DEFAULT '0.00' COMMENT 'Accumulated PV on right binary leg',
  `paired_pv` decimal(14,2) NOT NULL DEFAULT '0.00' COMMENT 'Total paired PV lifetime',
  `flushed_pv` decimal(14,2) NOT NULL DEFAULT '0.00' COMMENT 'PV that has been flushed in pairing',
  `personal_pv` decimal(14,2) NOT NULL DEFAULT '0.00' COMMENT 'Personal sales PV (package + product)',
  `group_pv` decimal(14,2) NOT NULL DEFAULT '0.00' COMMENT 'Group sales PV from downline',
  `pairs_paid` int UNSIGNED NOT NULL DEFAULT '0',
  `pairs_flushed` int UNSIGNED NOT NULL DEFAULT '0',
  `pairs_paid_today` int UNSIGNED NOT NULL DEFAULT '0',
  `paired_pv_today` decimal(14,2) NOT NULL DEFAULT '0.00' COMMENT 'Paired PV today (midnight reset)',
  `lifetime_earned` decimal(14,2) NOT NULL DEFAULT '0.00',
  `cap_status` enum('active','capped','perminact') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `capping_bypass` tinyint(1) NOT NULL DEFAULT '0',
  `daily_cap_bypass` tinyint(1) NOT NULL DEFAULT '0',
  `capped_at` timestamp NULL DEFAULT NULL,
  `last_reactivation_at` timestamp NULL DEFAULT NULL,
  `dfi_days_used` int UNSIGNED NOT NULL DEFAULT '0',
  `dfi_active` tinyint(1) NOT NULL DEFAULT '1',
  `full_name` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mobile` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gcash_number` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `maya_number` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `usdt_trc20_address` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `usdt_bep20_address` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `photo` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ewallet_balance` decimal(14,2) NOT NULL DEFAULT '0.00',
  `withdrawable_balance` decimal(14,2) NOT NULL DEFAULT '0.00',
  `status` enum('active','suspended','pending') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `joined_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `last_login` timestamp NULL DEFAULT NULL,
  `cd_active` tinyint(1) NOT NULL DEFAULT '0',
  `ewallet_sent_today` decimal(12,2) NOT NULL DEFAULT '0.00',
  `ewallet_sent_this_week` decimal(12,2) NOT NULL DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password_hash`, `role`, `package_id`, `reg_code_id`, `reg_payment_method`, `reg_paid_by`, `sponsor_id`, `binary_parent_id`, `binary_position`, `left_count`, `right_count`, `left_pv`, `right_pv`, `paired_pv`, `flushed_pv`, `personal_pv`, `group_pv`, `pairs_paid`, `pairs_flushed`, `pairs_paid_today`, `paired_pv_today`, `lifetime_earned`, `cap_status`, `capping_bypass`, `daily_cap_bypass`, `capped_at`, `last_reactivation_at`, `dfi_days_used`, `dfi_active`, `full_name`, `email`, `mobile`, `gcash_number`, `maya_number`, `usdt_trc20_address`, `usdt_bep20_address`, `address`, `photo`, `ewallet_balance`, `withdrawable_balance`, `status`, `joined_at`, `last_login`, `cd_active`, `ewallet_sent_today`, `ewallet_sent_this_week`) VALUES
(1, 'admin', '$2y$12$h3j0mO9NbtMyLg6EsC4M6eGy6buk0zanOgPmFBIgaI8V5/CUbaYqq', 'admin', NULL, NULL, 'code', NULL, NULL, NULL, NULL, 11, 0, 24800.00, 0.00, 0.00, 0.00, 0.00, 10900.00, 0, 0, 0, 0.00, 0.00, 'capped', 0, 0, '2026-06-13 05:40:32', NULL, 0, 0, 'System Administrator', 'admin@mlm.local', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 100000.00, 100000.00, 'active', '2026-05-30 13:32:05', '2026-06-17 08:19:00', 0, 0.00, 0.00),
(2, 'altas01', '$2y$12$x1.SQk2mi4NfVaRnZYStme0XwxuD4otfN.Hg5DSodl3v/911Zb7Py', 'member', 1, NULL, 'ewallet', 1, 1, 1, 'left', 9, 1, 34400.00, 10500.00, 10000.00, 0.00, 700.00, 10200.00, 1, 0, 0, 0.00, 0.00, 'capped', 0, 0, '2026-06-17 11:21:45', '2026-06-17 11:15:00', 0, 1, '', '', '', '09171234567', '09281234566', 'TXYZ123456789ABCDEFGHJKLMNPQRSTUVWXYZ12', '0x1234567890abcdef1234567890abcdef12345678', '', NULL, 50000.00, 2166.00, 'active', '2026-06-13 05:40:32', '2026-06-20 11:33:02', 0, 0.00, 0.00),
(3, 'altas02', '$2y$12$U1m53EQ.ar7xxk.XmlUZ1efgeUViylsMggd3nB8BTUggGuYSzNIpy', 'member', 1, NULL, 'ewallet', 1, 2, 2, 'left', 5, 3, 18000.00, 6000.00, 6000.00, 0.00, 0.00, 10100.00, 0, 0, 0, 0.00, 5516.00, 'active', 0, 0, NULL, NULL, 2, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 5516.00, 5516.00, 'active', '2026-06-13 06:22:57', '2026-06-20 11:33:19', 0, 0.00, 0.00),
(5, 'altas03', '$2y$12$LNW8SOE8n0AQLIBPKMk7eOgG9UajuK9t7/wF15GrRehjl6UrVRkbW', 'member', 1, NULL, 'ewallet', 1, 3, 2, 'right', 0, 0, 100.00, 0.00, 0.00, 0.00, 100.00, 0.00, 0, 0, 0, 0.00, 66.00, 'active', 0, 0, NULL, NULL, 2, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 9066.00, 66.00, 'active', '2026-06-13 09:38:25', '2026-06-20 04:36:18', 0, 0.00, 0.00),
(6, 'altas04', '$2y$12$IlC2KqEPVY9uNlPgyEKwy.klgmb9icMWuE4L7pmaaGswhKrBPqfw.', 'member', NULL, NULL, 'pending', NULL, 5, 5, 'left', 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 0, 0, 0.00, 0.00, 'active', 0, 0, NULL, NULL, 0, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 'pending', '2026-06-13 09:45:16', '2026-06-16 07:37:14', 0, 0.00, 0.00),
(14, 'altas05', '$2y$12$Nm5rlRjmvNwdN78Oz.NuQeG4YtyCy11DPesG1BSmGDTZkgbKE0jZi', 'member', 2, NULL, 'ewallet', 1, 3, 3, 'left', 3, 1, 6100.00, 2000.00, 2000.00, 0.00, 0.00, 100.00, 0, 0, 0, 0.00, 6700.00, 'active', 0, 0, NULL, NULL, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 6700.00, 6700.00, 'active', '2026-06-15 07:51:03', '2026-06-16 03:16:04', 0, 0.00, 0.00),
(16, 'altas06', '$2y$12$exnGqW78wFAR7fCTNGfzKO7i.njd6N1BiQl3rARd9uXofqazWVASq', 'member', 1, NULL, 'ewallet', 1, 3, 3, 'right', 1, 1, 2000.00, 2000.00, 2000.00, 0.00, 0.00, 0.00, 0, 0, 0, 0.00, 4633.00, 'active', 0, 0, NULL, NULL, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 4633.00, 4633.00, 'active', '2026-06-15 12:20:10', '2026-06-15 23:18:34', 0, 0.00, 0.00),
(17, 'altas07', '$2y$12$U2FghVuljqmNRWTZevBNDOE9bQQqzysLitAakjLSZ0qqjvFb4R.Tu', 'member', 1, NULL, 'ewallet', 1, 16, 16, 'left', 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 0, 0, 0.00, 33.00, 'active', 0, 0, NULL, NULL, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 33.00, 33.00, 'active', '2026-06-15 23:19:35', NULL, 0, 0.00, 0.00),
(18, 'altas08', '$2y$12$NEBj5WuqmFCAooWVXFFow.MouTl/Cre4V7oBvGqr.AqFf8Q1aBoRi', 'member', 1, NULL, 'ewallet', 1, 16, 16, 'right', 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 0, 0, 0.00, 33.00, 'active', 0, 0, NULL, NULL, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 33.00, 33.00, 'active', '2026-06-15 23:23:20', NULL, 0, 0.00, 0.00),
(19, 'altas09', '$2y$12$s4BkBM28nyoQInIYb6qnl.z3E3JKTgopsd6Hh8OgkSQ9a02s0EWWO', 'member', 1, NULL, 'ewallet', 1, 14, 14, 'left', 1, 1, 2000.00, 2000.00, 2000.00, 0.00, 0.00, 0.00, 0, 0, 0, 2000.00, 5333.00, 'active', 0, 0, NULL, NULL, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 5333.00, 5333.00, 'active', '2026-06-16 03:19:23', NULL, 0, 0.00, 0.00),
(20, 'altas10', '$2y$12$h9K3vt5PKfYvpTwrEyTje.oS3qjbnY870wZ5oZqk9soutRWQ8QNve', 'member', 1, NULL, 'ewallet', 1, 14, 14, 'right', 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 0, 0, 0.00, 33.00, 'active', 0, 0, NULL, NULL, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 33.00, 33.00, 'active', '2026-06-16 03:21:17', NULL, 0, 0.00, 0.00),
(21, 'altas11', '$2y$12$u96xFqPQroSIs7CrXYbKE.vHgWq1XlMkbPpT8DFLZPC/A.EkRNW9y', 'member', 1, NULL, 'ewallet', 1, 19, 19, 'left', 0, 0, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 0, 0, 0.00, 0.00, 'active', 0, 0, NULL, NULL, 0, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 'active', '2026-06-16 03:25:51', NULL, 0, 0.00, 0.00),
(22, 'altas12', '$2y$12$Nd67D2FJG82IfEWrv9ROVeDk1BhcQ3b/o.Ka6gkQL2Rk6iPtvvicy', 'member', 1, NULL, 'ewallet', 1, 19, 19, 'right', 0, 0, 100.00, 0.00, 0.00, 0.00, 100.00, 0.00, 0, 0, 0, 0.00, 0.00, 'active', 0, 0, NULL, NULL, 0, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 'active', '2026-06-16 03:30:52', '2026-06-20 16:27:45', 0, 0.00, 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `user_cd_status`
--

CREATE TABLE `user_cd_status` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `target_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `filled_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `status` enum('active','completed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `assigned_by` int NOT NULL,
  `assigned_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` datetime DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `carts`
--
ALTER TABLE `carts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `member_id` (`member_id`);

--
-- Indexes for table `cart_items`
--
ALTER TABLE `cart_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_cart_product` (`cart_id`,`product_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `cd_ledger`
--
ALTER TABLE `cd_ledger`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_cd` (`user_id`,`cd_status_id`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `commissions`
--
ALTER TABLE `commissions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_type` (`user_id`,`type`,`created_at`),
  ADD KEY `idx_source` (`source_user_id`),
  ADD KEY `idx_status` (`status`,`created_at`);

--
-- Indexes for table `daily_fixed_income_log`
--
ALTER TABLE `daily_fixed_income_log`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_user_day` (`user_id`,`day_number`),
  ADD KEY `idx_dfi_user_date` (`user_id`,`created_at`);

--
-- Indexes for table `ewallet_admin_topups`
--
ALTER TABLE `ewallet_admin_topups`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admin_id` (`admin_id`),
  ADD KEY `recipient_id` (`recipient_id`);

--
-- Indexes for table `ewallet_ledger`
--
ALTER TABLE `ewallet_ledger`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`,`created_at`);

--
-- Indexes for table `ewallet_transfers`
--
ALTER TABLE `ewallet_transfers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `recipient_id` (`recipient_id`);

--
-- Indexes for table `packages`
--
ALTER TABLE `packages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `package_indirect_levels`
--
ALTER TABLE `package_indirect_levels`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_pkg_level` (`package_id`,`level`);

--
-- Indexes for table `payout_requests`
--
ALTER TABLE `payout_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `processed_by` (`processed_by`),
  ADD KEY `idx_user_status` (`user_id`,`status`),
  ADD KEY `idx_status` (`status`,`requested_at`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `pv_transactions`
--
ALTER TABLE `pv_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_type` (`user_id`,`type`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `source_user_id` (`source_user_id`);

--
-- Indexes for table `reactivations`
--
ALTER TABLE `reactivations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `package_id` (`package_id`),
  ADD KEY `idx_react_user` (`user_id`,`created_at`),
  ADD KEY `idx_react_status` (`status`,`created_at`),
  ADD KEY `fk_reactivations_processed_by` (`processed_by`);

--
-- Indexes for table `reg_codes`
--
ALTER TABLE `reg_codes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `package_id` (`package_id`),
  ADD KEY `used_by` (`used_by`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `repeat_purchase_orders`
--
ALTER TABLE `repeat_purchase_orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `member_id` (`member_id`),
  ADD KEY `approved_by` (`approved_by`);

--
-- Indexes for table `repeat_purchase_order_items`
--
ALTER TABLE `repeat_purchase_order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`key_name`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `package_id` (`package_id`),
  ADD KEY `idx_sponsor` (`sponsor_id`),
  ADD KEY `idx_binary_parent` (`binary_parent_id`,`binary_position`),
  ADD KEY `idx_role_status` (`role`,`status`),
  ADD KEY `idx_cap_status` (`cap_status`,`capped_at`),
  ADD KEY `idx_dfi_active` (`dfi_active`,`dfi_days_used`),
  ADD KEY `idx_reg_code` (`reg_code_id`);

--
-- Indexes for table `user_cd_status`
--
ALTER TABLE `user_cd_status`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_active` (`user_id`,`status`),
  ADD KEY `idx_assigned_at` (`assigned_at`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `carts`
--
ALTER TABLE `carts`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `cart_items`
--
ALTER TABLE `cart_items`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `cd_ledger`
--
ALTER TABLE `cd_ledger`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `commissions`
--
ALTER TABLE `commissions`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT for table `daily_fixed_income_log`
--
ALTER TABLE `daily_fixed_income_log`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `ewallet_admin_topups`
--
ALTER TABLE `ewallet_admin_topups`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `ewallet_ledger`
--
ALTER TABLE `ewallet_ledger`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=66;

--
-- AUTO_INCREMENT for table `ewallet_transfers`
--
ALTER TABLE `ewallet_transfers`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `packages`
--
ALTER TABLE `packages`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `package_indirect_levels`
--
ALTER TABLE `package_indirect_levels`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=81;

--
-- AUTO_INCREMENT for table `payout_requests`
--
ALTER TABLE `payout_requests`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `pv_transactions`
--
ALTER TABLE `pv_transactions`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=101;

--
-- AUTO_INCREMENT for table `reactivations`
--
ALTER TABLE `reactivations`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `reg_codes`
--
ALTER TABLE `reg_codes`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `repeat_purchase_orders`
--
ALTER TABLE `repeat_purchase_orders`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `repeat_purchase_order_items`
--
ALTER TABLE `repeat_purchase_order_items`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `user_cd_status`
--
ALTER TABLE `user_cd_status`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `carts`
--
ALTER TABLE `carts`
  ADD CONSTRAINT `carts_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `cart_items`
--
ALTER TABLE `cart_items`
  ADD CONSTRAINT `cart_items_ibfk_1` FOREIGN KEY (`cart_id`) REFERENCES `carts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cart_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT;

--
-- Constraints for table `commissions`
--
ALTER TABLE `commissions`
  ADD CONSTRAINT `commissions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `commissions_ibfk_2` FOREIGN KEY (`source_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `daily_fixed_income_log`
--
ALTER TABLE `daily_fixed_income_log`
  ADD CONSTRAINT `daily_fixed_income_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `ewallet_admin_topups`
--
ALTER TABLE `ewallet_admin_topups`
  ADD CONSTRAINT `ewallet_admin_topups_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `ewallet_admin_topups_ibfk_2` FOREIGN KEY (`recipient_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `ewallet_ledger`
--
ALTER TABLE `ewallet_ledger`
  ADD CONSTRAINT `ewallet_ledger_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `ewallet_transfers`
--
ALTER TABLE `ewallet_transfers`
  ADD CONSTRAINT `ewallet_transfers_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `ewallet_transfers_ibfk_2` FOREIGN KEY (`recipient_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `package_indirect_levels`
--
ALTER TABLE `package_indirect_levels`
  ADD CONSTRAINT `package_indirect_levels_ibfk_1` FOREIGN KEY (`package_id`) REFERENCES `packages` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payout_requests`
--
ALTER TABLE `payout_requests`
  ADD CONSTRAINT `payout_requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `payout_requests_ibfk_2` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `pv_transactions`
--
ALTER TABLE `pv_transactions`
  ADD CONSTRAINT `pv_transactions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `pv_transactions_ibfk_2` FOREIGN KEY (`source_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `reactivations`
--
ALTER TABLE `reactivations`
  ADD CONSTRAINT `reactivations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `reactivations_ibfk_2` FOREIGN KEY (`package_id`) REFERENCES `packages` (`id`),
  ADD CONSTRAINT `reactivations_ibfk_3` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `reg_codes`
--
ALTER TABLE `reg_codes`
  ADD CONSTRAINT `reg_codes_ibfk_1` FOREIGN KEY (`package_id`) REFERENCES `packages` (`id`),
  ADD CONSTRAINT `reg_codes_ibfk_2` FOREIGN KEY (`used_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `reg_codes_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `repeat_purchase_orders`
--
ALTER TABLE `repeat_purchase_orders`
  ADD CONSTRAINT `repeat_purchase_orders_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `repeat_purchase_orders_ibfk_2` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `repeat_purchase_order_items`
--
ALTER TABLE `repeat_purchase_order_items`
  ADD CONSTRAINT `repeat_purchase_order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `repeat_purchase_orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `repeat_purchase_order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`sponsor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `users_ibfk_2` FOREIGN KEY (`binary_parent_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `users_ibfk_3` FOREIGN KEY (`package_id`) REFERENCES `packages` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `users_ibfk_4` FOREIGN KEY (`reg_code_id`) REFERENCES `reg_codes` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
