-- ====================================================
-- DVE System: Migration — Role Supervisor
-- วันที่: 2026-08-02
-- ====================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+07:00";

-- --------------------------------------------------
-- ตาราง supervisor_assignments
-- เชื่อมโยง supervisor_id → student_id
-- --------------------------------------------------
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

-- --------------------------------------------------
-- เพิ่ม column ใน daily_reports สำหรับ supervisor comment
-- --------------------------------------------------
ALTER TABLE `daily_reports`
  ADD COLUMN IF NOT EXISTS `supervisor_comment` TEXT DEFAULT NULL
    COMMENT 'ความคิดเห็นจาก supervisor (ผู้ดูแลการฝึกงาน)' AFTER `teacher_comment`,
  ADD COLUMN IF NOT EXISTS `supervisor_commented_by` INT(11) DEFAULT NULL
    COMMENT 'supervisor user_id ที่เขียน comment' AFTER `supervisor_comment`,
  ADD COLUMN IF NOT EXISTS `supervisor_commented_at` DATETIME DEFAULT NULL
    COMMENT 'เวลาที่ supervisor เขียน comment' AFTER `supervisor_commented_by`;

-- Fallback สำหรับ MySQL < 8.0 ที่ไม่รองรับ ADD COLUMN IF NOT EXISTS
-- (รัน queries ด้านล่างนี้แทนหากคำสั่งด้านบนไม่ทำงาน)
-- ALTER TABLE `daily_reports` ADD COLUMN `supervisor_comment` TEXT DEFAULT NULL AFTER `teacher_comment`;
-- ALTER TABLE `daily_reports` ADD COLUMN `supervisor_commented_by` INT(11) DEFAULT NULL AFTER `supervisor_comment`;
-- ALTER TABLE `daily_reports` ADD COLUMN `supervisor_commented_at` DATETIME DEFAULT NULL AFTER `supervisor_commented_by`;
