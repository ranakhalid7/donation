-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3307
-- Generation Time: 27 نوفمبر 2025 الساعة 19:14
-- إصدار الخادم: 8.0.42
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `donation_system`
--

-- --------------------------------------------------------

--
-- بنية الجدول `charities`
--

CREATE TABLE `charities` (
  `id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `charity_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `license_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `phone` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `website` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `logo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `verified` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `charities`
--

INSERT INTO `charities` (`id`, `user_id`, `charity_name`, `license_number`, `description`, `address`, `phone`, `email`, `website`, `logo`, `verified`, `created_at`) VALUES
(1, 4, 'جمعية الاحسان', '595656565656', 'جمعية خيرية غير ربحية', NULL, NULL, NULL, 'https://www.donation.com', NULL, 1, '2025-10-26 18:25:13');

-- --------------------------------------------------------

--
-- بنية الجدول `charity_pickup_requests`
--

CREATE TABLE `charity_pickup_requests` (
  `id` int NOT NULL,
  `donation_id` int NOT NULL,
  `charity_id` int NOT NULL,
  `pickup_date` date DEFAULT NULL,
  `pickup_time` time DEFAULT NULL,
  `status` enum('pending','scheduled','completed','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `donations`
--

CREATE TABLE `donations` (
  `id` int NOT NULL,
  `donor_id` int NOT NULL,
  `beneficiary_id` int DEFAULT NULL,
  `charity_id` int DEFAULT NULL,
  `title` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `category` enum('clothing','furniture','electronics','other') COLLATE utf8mb4_unicode_ci NOT NULL,
  `condition_item` enum('new','excellent','good','fair') COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` int DEFAULT '1',
  `images` text COLLATE utf8mb4_unicode_ci,
  `pickup_location` text COLLATE utf8mb4_unicode_ci,
  `delivery_method` enum('pickup','delivery','both') COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('pending_charity_approval','available','reserved','with_charity','delivered','completed','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pending_charity_approval',
  `received_by_charity_at` timestamp NULL DEFAULT NULL,
  `charity_approved_at` timestamp NULL DEFAULT NULL,
  `delivered_to_beneficiary_at` timestamp NULL DEFAULT NULL,
  `charity_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `charity_approval_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `donations`
--

INSERT INTO `donations` (`id`, `donor_id`, `beneficiary_id`, `charity_id`, `title`, `description`, `category`, `condition_item`, `quantity`, `images`, `pickup_location`, `delivery_method`, `status`, `received_by_charity_at`, `charity_approved_at`, `delivered_to_beneficiary_at`, `charity_notes`, `charity_approval_notes`, `created_at`, `updated_at`) VALUES
(1, 2, NULL, NULL, 'ملابس شتوية', 'ملابس شتوية شبة جديدة', 'clothing', 'excellent', 1, '[\"uploads\\/donations\\/donation_68fe661c437d3.png\"]', 'جدة', 'pickup', 'available', NULL, NULL, NULL, NULL, NULL, '2025-10-26 18:19:08', '2025-10-26 18:19:08'),
(2, 2, NULL, NULL, 'غسالة كهربائي اوتوماتيكية', 'غسالة كهربائية عشرة كيلو بحالة جيدة', 'electronics', 'excellent', 1, '[\"uploads\\/donations\\/donation_6902313ce2a08.jpg\"]', 'الرياض', 'pickup', 'available', NULL, NULL, NULL, NULL, NULL, '2025-10-29 15:22:36', '2025-10-29 15:22:36'),
(3, 2, NULL, NULL, 'test', 'test', 'other', 'good', 1, '[]', 'test', 'both', 'available', NULL, NULL, NULL, NULL, NULL, '2025-10-29 15:54:00', '2025-10-29 15:54:00'),
(4, 2, NULL, 1, 'فحص', 'فحص 01', 'clothing', 'new', 1, '[\"uploads\\/donations\\/donation_69025968090dc.png\"]', 'الدمام', 'delivery', 'with_charity', '2025-10-29 18:14:43', NULL, NULL, 'تم التسليم من العميل', NULL, '2025-10-29 18:14:00', '2025-10-29 18:14:43'),
(5, 2, NULL, NULL, 'فحص فحص', 'فحص فحص', 'furniture', 'good', 1, '[\"uploads\\/donations\\/donation_6902607527efb.png\"]', 'جدة', 'delivery', 'available', NULL, NULL, NULL, NULL, NULL, '2025-10-29 18:44:05', '2025-10-29 18:44:05'),
(6, 2, 3, 1, 'فحص فحص فحص', 'فحص فحص فحص', 'clothing', 'new', 1, '[\"uploads\\/donations\\/donation_69026094ecc30.png\"]', 'جدة', 'both', 'delivered', '2025-10-29 18:47:07', NULL, '2025-10-29 18:48:09', 'تم الاستلام من الشخص\n\nتوزيع: تم التوزيع لشخص المستفيد من قبل جمعيتنا', NULL, '2025-10-29 18:44:36', '2025-10-29 18:48:09'),
(7, 2, NULL, NULL, 'فحص توزيع مباشر', 'فحص توزيع مباشر', 'clothing', 'new', 1, '[\"uploads\\/donations\\/donation_6902626546fc9.png\"]', 'جدة', 'both', 'available', NULL, NULL, NULL, NULL, NULL, '2025-10-29 18:52:21', '2025-10-29 18:52:21'),
(8, 2, 3, 1, 'ملابش شتوية', 'ملابس شتوية', 'clothing', 'excellent', 1, '[\"uploads\\/donations\\/donation_6903d132c0685.png\"]', 'جدة', 'both', 'delivered', '2025-10-30 20:59:44', NULL, '2025-10-30 21:00:25', 'تم استلام البرع من المتبرع بحالة جيدخ\n\nتوزيع: تم التسليم الى المستفيد', NULL, '2025-10-30 20:57:22', '2025-10-30 21:00:25'),
(9, 2, NULL, 1, 'ملابس شتوية لكبار السن', 'ملابس شتوية لكبار السن', 'clothing', 'excellent', 1, '[\"uploads\\/donations\\/donation_692891a2db0ca.png\"]', 'جدة', 'pickup', 'with_charity', '2025-11-27 18:01:10', '2025-11-27 18:00:48', NULL, 'ملابس بحالة جيدة', '', '2025-11-27 18:00:02', '2025-11-27 18:01:10'),
(10, 2, NULL, 1, 'cloths for kids', 'cloths for kids', 'clothing', 'new', 1, '[\"uploads\\/donations\\/donation_692892fac6cb0.png\"]', 'جدة', 'both', 'with_charity', '2025-11-27 18:09:13', '2025-11-27 18:06:43', NULL, 'ملابس جديدة من فاعل خير', '', '2025-11-27 18:05:46', '2025-11-27 18:09:13');

-- --------------------------------------------------------

--
-- بنية الجدول `donation_movements`
--

CREATE TABLE `donation_movements` (
  `id` int NOT NULL,
  `donation_id` int NOT NULL,
  `from_status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `to_status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `moved_by` int NOT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `moved_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `donation_movements`
--

INSERT INTO `donation_movements` (`id`, `donation_id`, `from_status`, `to_status`, `moved_by`, `notes`, `moved_at`) VALUES
(1, 4, 'available', 'with_charity', 4, 'تم التسليم من العميل', '2025-10-29 18:14:43'),
(2, 6, 'available', 'with_charity', 4, 'تم الاستلام من الشخص', '2025-10-29 18:47:07'),
(3, 6, 'with_charity', 'delivered', 4, 'تم التوزيع على: user 01. تم التوزيع لشخص المستفيد من قبل جمعيتنا', '2025-10-29 18:48:09'),
(4, 8, 'available', 'with_charity', 4, 'تم استلام البرع من المتبرع بحالة جيدخ', '2025-10-30 20:59:44'),
(5, 8, 'with_charity', 'delivered', 4, 'تم التوزيع على: user 01. تم التسليم الى المستفيد', '2025-10-30 21:00:25'),
(6, 9, 'pending_charity_approval', 'available', 4, 'تمت الموافقة على التبرع من قبل الجمعية: ', '2025-11-27 18:00:48'),
(7, 9, 'available', 'with_charity', 4, 'ملابس بحالة جيدة', '2025-11-27 18:01:10'),
(8, 10, 'pending_charity_approval', 'available', 4, 'تمت الموافقة على التبرع من قبل الجمعية: ', '2025-11-27 18:06:43'),
(9, 10, 'available', 'with_charity', 4, 'ملابس جديدة من فاعل خير', '2025-11-27 18:09:13');

-- --------------------------------------------------------

--
-- بنية الجدول `donation_requests`
--

CREATE TABLE `donation_requests` (
  `id` int NOT NULL,
  `donation_id` int NOT NULL,
  `requester_id` int NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci,
  `status` enum('pending','approved','rejected') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `donation_requests`
--

INSERT INTO `donation_requests` (`id`, `donation_id`, `requester_id`, `message`, `status`, `created_at`, `updated_at`) VALUES
(2, 1, 3, 'fdfdfdfdfd fdf fdfd fdf', 'pending', '2025-10-26 21:24:01', '2025-10-26 21:24:01'),
(3, 2, 3, 'محتاج تلك الغسالة الكهربائية', 'pending', '2025-10-29 15:24:45', '2025-10-29 15:24:45'),
(4, 5, 3, 'فحص فحص فحص فحص فحص فحف', 'pending', '2025-10-29 18:45:27', '2025-10-29 18:45:27'),
(5, 6, 3, 'فحص فحص فحص فحص فحص فحص فحص', 'approved', '2025-10-29 18:46:04', '2025-10-29 18:48:09'),
(6, 7, 3, 'فحص توزيع مباشر فحص توزيع مباشرفحص توزيع مباشرفحص توزيع مباشرفحص توزيع مباشرفحص توزيع مباشرفحص توزيع مباشر', 'pending', '2025-10-29 18:52:57', '2025-10-29 18:52:57'),
(7, 8, 3, 'احتاج هذا التبرع احتاج هذا التبرع', 'approved', '2025-10-30 20:58:43', '2025-10-30 21:00:25');

-- --------------------------------------------------------

--
-- بنية الجدول `notifications`
--

CREATE TABLE `notifications` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `title` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('info','success','warning','error') COLLATE utf8mb4_unicode_ci DEFAULT 'info',
  `is_read` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `title`, `message`, `type`, `is_read`, `created_at`) VALUES
(1, 2, 'طلب تبرع جديد', 'تم استلام طلب جديد على تبرعك: ملابس شتوية', 'info', 0, '2025-10-26 19:27:04'),
(2, 2, 'طلب تبرع جديد', 'تم استلام طلب جديد على تبرعك: ملابس شتوية', 'info', 0, '2025-10-26 21:24:01'),
(3, 2, 'طلب تبرع جديد', 'تم استلام طلب جديد على تبرعك: غسالة كهربائي اوتوماتيكية', 'info', 0, '2025-10-29 15:24:45'),
(4, 2, 'تم استلام التبرع', 'تم استلام تبرعك \"فحص\" من قبل جمعية جمعية الاحسان', 'success', 0, '2025-10-29 18:14:43'),
(5, 2, 'طلب تبرع جديد', 'تم استلام طلب جديد على تبرعك: فحص فحص', 'info', 0, '2025-10-29 18:45:27'),
(6, 2, 'طلب تبرع جديد', 'تم استلام طلب جديد على تبرعك: فحص فحص فحص', 'info', 0, '2025-10-29 18:46:04'),
(7, 2, 'تم استلام التبرع', 'تم استلام تبرعك \"فحص فحص فحص\" من قبل جمعية جمعية الاحسان', 'success', 0, '2025-10-29 18:47:07'),
(8, 3, 'تم استلام تبرع', 'تم تسليمك تبرع \"فحص فحص فحص\" من جمعية جمعية الاحسان', 'success', 0, '2025-10-29 18:48:09'),
(9, 2, 'تم توزيع تبرعك', 'تم توزيع تبرعك \"فحص فحص فحص\" على مستفيد من خلال جمعية جمعية الاحسان', 'success', 0, '2025-10-29 18:48:09'),
(10, 2, 'طلب تبرع جديد', 'تم استلام طلب جديد على تبرعك: فحص توزيع مباشر', 'info', 0, '2025-10-29 18:52:57'),
(11, 2, 'طلب تبرع جديد', 'تم استلام طلب جديد على تبرعك: ملابش شتوية', 'info', 0, '2025-10-30 20:58:43'),
(12, 2, 'تم استلام التبرع', 'تم استلام تبرعك \"ملابش شتوية\" من قبل جمعية جمعية الاحسان', 'success', 0, '2025-10-30 20:59:44'),
(13, 3, 'تم استلام تبرع', 'تم تسليمك تبرع \"ملابش شتوية\" من جمعية جمعية الاحسان', 'success', 0, '2025-10-30 21:00:25'),
(14, 2, 'تم توزيع تبرعك', 'تم توزيع تبرعك \"ملابش شتوية\" على مستفيد من خلال جمعية جمعية الاحسان', 'success', 0, '2025-10-30 21:00:25'),
(15, 4, 'تبرع جديد في انتظار الموافقة', 'تبرع جديد \"ملابس شتوية لكبار السن\" يحتاج إلى موافقتكم للنشر', 'info', 0, '2025-11-27 18:00:02'),
(16, 2, 'تمت الموافقة على تبرعك', 'تمت الموافقة على تبرعك \"ملابس شتوية لكبار السن\" من قبل جمعية جمعية الاحسان وتم نشره للمستفيدين', 'success', 0, '2025-11-27 18:00:48'),
(17, 2, 'تم استلام التبرع', 'تم استلام تبرعك \"ملابس شتوية لكبار السن\" من قبل جمعية جمعية الاحسان', 'success', 0, '2025-11-27 18:01:10'),
(18, 4, 'تبرع جديد في انتظار الموافقة', 'تبرع جديد \"cloths for kids\" يحتاج إلى موافقتكم للنشر', 'info', 0, '2025-11-27 18:05:46'),
(19, 2, 'تمت الموافقة على تبرعك', 'تمت الموافقة على تبرعك \"cloths for kids\" من قبل جمعية جمعية الاحسان وتم نشره للمستفيدين', 'success', 0, '2025-11-27 18:06:43'),
(20, 2, 'تم استلام التبرع', 'تم استلام تبرعك \"cloths for kids\" من قبل جمعية جمعية الاحسان', 'success', 0, '2025-11-27 18:09:13');

-- --------------------------------------------------------

--
-- بنية الجدول `settings`
--

CREATE TABLE `settings` (
  `id` int NOT NULL,
  `setting_key` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` text COLLATE utf8mb4_unicode_ci,
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `description`, `created_at`, `updated_at`) VALUES
(1, 'site_name', 'منصة التبرع بالملابس والأثاث', 'اسم الموقع', '2025-10-25 19:14:32', '2025-10-25 19:14:32'),
(2, 'site_description', 'منصة لتسهيل عملية التبرع والاستفادة من الملابس والأثاث', 'وصف الموقع', '2025-10-25 19:14:32', '2025-10-25 19:14:32'),
(3, 'max_images_per_donation', '5', 'عدد الصور المسموح لكل تبرع', '2025-10-25 19:14:32', '2025-10-25 19:14:32'),
(4, 'admin_email', 'admin@donation.com', 'بريد المدير الإلكتروني', '2025-10-25 19:14:32', '2025-10-25 19:14:32');

-- --------------------------------------------------------

--
-- بنية الجدول `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `full_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(15) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `user_type` enum('donor','beneficiary','charity','admin') COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('active','inactive','pending') COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- إرجاع أو استيراد بيانات الجدول `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `full_name`, `phone`, `address`, `user_type`, `status`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'admin@donation.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'مدير النظام', '0123456789', NULL, 'admin', 'active', '2025-10-25 19:14:32', '2025-10-25 19:24:45'),
(2, 'donation', 'donation@donation.com', '$2y$10$UmAZBoYGzq4NmJDp0rsQWeRR/Q2z6NxLkLQjVrbjlfrPqsM.MntM6', 'donation 01', '0555555555', 'سراقة بن مالك', 'donor', 'active', '2025-10-25 19:27:43', '2025-10-25 19:27:43'),
(3, 'user', 'user@donation.com', '$2y$10$3GsBxoBwyU2cYbvCIGcNkewFYj1Rzgj5lZ16dXyUoZqqdrqD5OFTC', 'user 01', '01234567890', 'ksa', 'beneficiary', 'active', '2025-10-25 19:30:05', '2025-10-26 19:17:35'),
(4, 'association', 'association@donation.com', '$2y$10$JtVuHXw3hiQyk5kgkCtkLOXSjfMWeH9nHw/K7wrfQ9xvS8VIrFrn.', 'جمعية الاحسان', '01234567890', 'سراقة بن مالك', 'charity', 'active', '2025-10-26 18:25:13', '2025-10-26 18:26:31');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `charities`
--
ALTER TABLE `charities`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `license_number` (`license_number`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `charity_pickup_requests`
--
ALTER TABLE `charity_pickup_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `donation_id` (`donation_id`),
  ADD KEY `charity_id` (`charity_id`);

--
-- Indexes for table `donations`
--
ALTER TABLE `donations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `donor_id` (`donor_id`),
  ADD KEY `beneficiary_id` (`beneficiary_id`),
  ADD KEY `charity_id` (`charity_id`);

--
-- Indexes for table `donation_movements`
--
ALTER TABLE `donation_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `donation_id` (`donation_id`),
  ADD KEY `moved_by` (`moved_by`);

--
-- Indexes for table `donation_requests`
--
ALTER TABLE `donation_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `donation_id` (`donation_id`),
  ADD KEY `requester_id` (`requester_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `charities`
--
ALTER TABLE `charities`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `charity_pickup_requests`
--
ALTER TABLE `charity_pickup_requests`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `donations`
--
ALTER TABLE `donations`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `donation_movements`
--
ALTER TABLE `donation_movements`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `donation_requests`
--
ALTER TABLE `donation_requests`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- قيود الجداول المُلقاة.
--

--
-- قيود الجداول `charities`
--
ALTER TABLE `charities`
  ADD CONSTRAINT `charities_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- قيود الجداول `charity_pickup_requests`
--
ALTER TABLE `charity_pickup_requests`
  ADD CONSTRAINT `charity_pickup_requests_ibfk_1` FOREIGN KEY (`donation_id`) REFERENCES `donations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `charity_pickup_requests_ibfk_2` FOREIGN KEY (`charity_id`) REFERENCES `charities` (`id`) ON DELETE CASCADE;

--
-- قيود الجداول `donations`
--
ALTER TABLE `donations`
  ADD CONSTRAINT `donations_ibfk_1` FOREIGN KEY (`donor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `donations_ibfk_2` FOREIGN KEY (`beneficiary_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `donations_ibfk_3` FOREIGN KEY (`charity_id`) REFERENCES `charities` (`id`) ON DELETE SET NULL;

--
-- قيود الجداول `donation_movements`
--
ALTER TABLE `donation_movements`
  ADD CONSTRAINT `donation_movements_ibfk_1` FOREIGN KEY (`donation_id`) REFERENCES `donations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `donation_movements_ibfk_2` FOREIGN KEY (`moved_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- قيود الجداول `donation_requests`
--
ALTER TABLE `donation_requests`
  ADD CONSTRAINT `donation_requests_ibfk_1` FOREIGN KEY (`donation_id`) REFERENCES `donations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `donation_requests_ibfk_2` FOREIGN KEY (`requester_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- قيود الجداول `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
