-- 📦 DVE SYSTEM COMPLETE DATABASE SCHEMA & BOOTSTRAP DATA
-- Generated for production server deployment
-- Compatible with MySQL 5.7+ / MariaDB 10.3+
-- Encoding: UTF8MB4 (Support for Thai characters)
-- -------------------------------------------------------------

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+07:00";
SET FOREIGN_KEY_CHECKS = 0;

-- -------------------------------------------------------------
-- 1. Table structure for table `audit_logs`
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action_type` varchar(50) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 2. Table structure for table `calendar_events`
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `calendar_events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `event_date` date DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `image_filename` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 3. Table structure for table `certificates`
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `certificates` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `certificate_no` varchar(100) DEFAULT NULL,
  `issued_date` date DEFAULT NULL,
  `issued_by` int(11) DEFAULT NULL,
  `file` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `certificate_no` (`certificate_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 4. Table structure for table `classrooms`
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `classrooms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `class_name` varchar(100) NOT NULL,
  `total_students` int(11) DEFAULT 0,
  `teacher_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 5. Table structure for table `companies`
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `companies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `branch` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `province` varchar(100) DEFAULT NULL,
  `district` varchar(100) DEFAULT NULL,
  `contact_name` varchar(150) DEFAULT NULL,
  `contact_phone` varchar(50) DEFAULT NULL,
  `contact_email` varchar(150) DEFAULT NULL,
  `company_type` varchar(100) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `contact_phone_2` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 6. Table structure for table `company_branches`
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `company_branches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `classroom_id` int(11) DEFAULT NULL,
  `branch_label` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `contact_name` varchar(150) DEFAULT NULL,
  `contact_phone` varchar(50) DEFAULT NULL,
  `contact_email` varchar(150) DEFAULT NULL,
  `manager_name` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_company_id` (`company_id`),
  KEY `idx_classroom_id` (`classroom_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 7. Table structure for table `contact_messages`
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `contact_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `sender_name` varchar(255) NOT NULL,
  `contact_info` varchar(255) NOT NULL,
  `subject` varchar(100) DEFAULT NULL,
  `message` text NOT NULL,
  `staff_note` text DEFAULT NULL,
  `status` enum('unread','read') DEFAULT 'unread',
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 8. Table structure for table `daily_reports`
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `daily_reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `date_work` date NOT NULL,
  `details` text DEFAULT NULL,
  `problems` text DEFAULT NULL,
  `teacher_comment` text DEFAULT NULL,
  `supervisor_comment` text DEFAULT NULL COMMENT 'ความคิดเห็นจาก supervisor (ผู้ดูแลการฝึกงาน)',
  `supervisor_commented_by` int(11) DEFAULT NULL COMMENT 'supervisor user_id ที่เขียน comment',
  `supervisor_commented_at` datetime DEFAULT NULL COMMENT 'เวลาที่ supervisor เขียน comment',
  `solutions` text DEFAULT NULL,
  `image1` varchar(255) DEFAULT NULL,
  `image2` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 9. Table structure for table `documents`
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `uploaded_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 10. Table structure for table `evaluations`
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `evaluations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `term` varchar(10) DEFAULT NULL,
  `score_work` int(11) DEFAULT 0,
  `score_report` int(11) DEFAULT 0,
  `score_behavior` int(11) DEFAULT 0,
  `total_score` int(11) DEFAULT 0,
  `grade` varchar(10) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 11. Table structure for table `internship_settings`
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `internship_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `level_name` varchar(20) NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 12. Table structure for table `mentors`
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `mentors` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `fullname` varchar(200) NOT NULL,
  `phone` varchar(15) DEFAULT NULL,
  `online_schedule` text DEFAULT NULL,
  `supervision_schedule` text DEFAULT NULL,
  `email` varchar(200) DEFAULT NULL,
  `department` varchar(200) DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `schedule_json` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 13. Table structure for table `news`
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `news` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) DEFAULT NULL,
  `content` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `video` varchar(255) DEFAULT NULL,
  `video_url` varchar(255) DEFAULT NULL,
  `file` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 13b. Table structure for table `news_attachments`
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `news_attachments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `news_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `file_type` varchar(50) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_news_id` (`news_id`),
  CONSTRAINT `fk_news_id` FOREIGN KEY (`news_id`) REFERENCES `news` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 14. Table structure for table `notifications`
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `type` varchar(50) DEFAULT 'info',
  `action_url` varchar(255) DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_notif_user_read` (`user_id`,`is_read`),
  KEY `idx_notif_user_id_created` (`user_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 14b. Table structure for table `user_notification_settings`
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `user_notification_settings` (
  `user_id` int(11) NOT NULL,
  `enable_web` tinyint(1) DEFAULT 1,
  `enable_line` tinyint(1) DEFAULT 0,
  `enable_email` tinyint(1) DEFAULT 0,
  `line_token` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



-- -------------------------------------------------------------
-- 15. Table structure for table `plans`
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `plans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) NOT NULL,
  `company_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `plan_date` date NOT NULL,
  `note` text DEFAULT NULL,
  `filename` varchar(255) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 16. Table structure for table `site_settings`
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `site_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 17. Table structure for table `staff_evaluations`
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `staff_evaluations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `term` varchar(50) DEFAULT NULL,
  `score_work` int(11) DEFAULT 0,
  `score_report` int(11) DEFAULT 0,
  `score_behavior` int(11) DEFAULT 0,
  `total_score` int(11) DEFAULT 0,
  `grade` varchar(20) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_student` (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 18. Table structure for table `subject_plans`
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `subject_plans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) NOT NULL,
  `subject_code` varchar(20) NOT NULL,
  `subject_name` varchar(255) NOT NULL,
  `company_id` int(11) DEFAULT NULL,
  `filename` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 19. Table structure for table `supervision_files`
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `supervision_files` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `company_id` int(11) DEFAULT 0,
  `file_path` varchar(255) DEFAULT NULL,
  `staff_signed_file` varchar(255) DEFAULT NULL,
  `staff_signed_at` datetime DEFAULT NULL,
  `staff_signed_by` int(11) DEFAULT NULL,
  `status` int(11) DEFAULT 0,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `director_signed_at` datetime DEFAULT NULL,
  `signed_by` int(11) DEFAULT NULL,
  `signed_file` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 20. Table structure for table `teacher_assignments`
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `teacher_assignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) NOT NULL,
  `classroom_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 20b. Table structure for table `supervisor_assignments`
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `supervisor_assignments` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `supervisor_id` INT(11) NOT NULL COMMENT 'users.id ที่มี role=supervisor',
  `student_id`    INT(11) NOT NULL COMMENT 'users.id ที่มี role=student',
  `assigned_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `assigned_by`   INT(11) DEFAULT NULL COMMENT 'user_id ของผู้กำหนด',
  `assigned_by_role` VARCHAR(20) DEFAULT NULL COMMENT 'staff หรือ student',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_supervisor_student` (`supervisor_id`, `student_id`),
  KEY `idx_supervisor_id` (`supervisor_id`),
  KEY `idx_student_id` (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='ผูกความสัมพันธ์ผู้ดูแลการฝึกงาน (supervisor) กับนักเรียน';

-- -------------------------------------------------------------
-- 21. Table structure for table `users`
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `fullname` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(50) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `affiliation` varchar(255) DEFAULT NULL,
  `company_id` int(11) DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `classroom_id` int(11) DEFAULT NULL,
  `company_name` varchar(255) DEFAULT NULL,
  `company_address` text DEFAULT NULL,
  `company_manager` varchar(255) DEFAULT NULL,
  `company_phone` varchar(20) DEFAULT NULL,
  `trainer_name` varchar(255) DEFAULT NULL,
  `trainer_phone` varchar(20) DEFAULT NULL,
  `trainer_position` varchar(255) DEFAULT NULL,
  `mentor_id` int(11) DEFAULT NULL,
  `student_level` varchar(50) DEFAULT 'ปวช.',
  `profile_image` varchar(255) DEFAULT NULL,
  `mentor_id_2` int(11) DEFAULT NULL,
  `mentor_id_3` int(11) DEFAULT NULL,
  `mentor_id_4` int(11) DEFAULT NULL,
  `mentor_id_5` int(11) DEFAULT NULL,
  `student_code` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 🔒 SEED INITIAL DATA
-- -------------------------------------------------------------

-- Pre-seed default Admin User: admin / admin123
INSERT INTO `users` (`id`, `username`, `fullname`, `email`, `password`, `role`, `phone`, `student_level`) 
VALUES (1, 'admin', 'ผู้ดูแลระบบสูงสุด', 'admin@dve-system.local', '$2y$10$qR.7d1vH80vO04zP2H6jX.4cK2yv.j19JzR5yVdK3RjC6oG6r1vG6', 'admin', '0800000000', 'ปวช.')
ON DUPLICATE KEY UPDATE `username` = VALUES(`username`);

-- Pre-seed core system settings in site_settings
INSERT INTO `site_settings` (`setting_key`, `setting_value`) VALUES
('college_director_name', 'นายสมชาย ดีงาม'),
('college_director_title', 'ผู้อำนวยการวิทยาลัยเทคนิคการอาชีพ'),
('college_name_th', 'วิทยาลัยเทคนิคการอาชีพ'),
('college_name_en', 'Industrial Education College'),
('system_title', 'ระบบบริหารจัดการฝึกงานวิชาชีพ (DVE System)'),
('sys_default_term', '1/2569')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);

SET FOREIGN_KEY_CHECKS = 1;
