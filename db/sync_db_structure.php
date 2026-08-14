<?php
/**
 * AUTOMATIC NON-DESTRUCTIVE SCHEMA SYNCHRONIZER
 * TVET - DVE System Database Synchronizer & Optimizer (Smarter & Premium Glassmorphism Edition)
 *
 * Premium Features:
 * 1. 🛡️ Zip-Compressed Safety Backups (Uses ZipArchive for on-the-fly SQL compression)
 * 2. ⚡ AJAX Step-by-Step Execution Engine (Prevents browser execution timeouts)
 * 3. 🔍 Smart Index & Constraints Sync (Automatically parses, verifies, and adds indexes)
 * 4. 🔗 Integrated Backup History Manager (Secure download and deletion system)
 * 5. 📊 Classroom Student Count Sync (Recalculates and updates total_students dynamically)
 * 6. 🧹 Thai Character Encoding Cleaner (Clears corrupted '?' marks in active profiles)
 * 7. 🎨 Stunning Premium Glassmorphic UI (Custom tabs, status controls, JetBrains terminal, smooth transitions)
 */

require_once __DIR__ . '/../includes/configdb.php';
require_once __DIR__ . '/../includes/functions.php';

// Define DB_NAME constant from configdb variables for backup compatibility
if (!defined('DB_NAME')) {
    define('DB_NAME', $db ?? 'tvet_system');
}

// 🛡️ PREVENTATIVE ERROR HANDLING BOOTSTRAP
error_reporting(E_ALL);
ini_set('display_errors', '1');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// 1. Security Check (CLI or Admin Role Required)
if (php_sapi_name() !== 'cli') {
    if (!is_logged_in() || !in_array(get_current_role(), ['admin'])) {
        die("<div style='padding:45px; font-family:sans-serif; text-align:center; background:#fff1f2; color:#be123c; border-radius:12px; margin:40px auto; max-width:600px; border:1px solid #fecdd3;'>
            <h2 style='margin-top:0;'>⛔ ปฏิเสธการเข้าถึง</h2>
            <p style='font-size:16px;'>เซสชันผู้ใช้ไม่ถูกต้อง หรือคุณไม่มีสิทธิ์ระดับผู้ดูแลระบบในการเข้าใช้งานส่วนนี้</p>
            <a href='../login.php' style='display:inline-block; background:#e11d48; color:white; padding:10px 24px; border-radius:30px; text-decoration:none; font-weight:bold; margin-top:15px;'>กลับไปหน้าเข้าสู่ระบบ</a>
        </div>");
    }
}

// SECURE BACKUPS MANAGER: Download or delete backup files
if (isset($_GET['download_backup'])) {
    $file = basename($_GET['download_backup']);
    $path = __DIR__ . '/backups/' . $file;
    if (file_exists($path) && php_sapi_name() !== 'cli' && is_logged_in() && get_current_role() === 'admin') {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    } else {
        die("พารามิเตอร์ไฟล์ไม่ถูกต้อง หรือไม่ได้รับสิทธิ์ในการเข้าถึง");
    }
}

if (isset($_GET['delete_backup'])) {
    $file = basename($_GET['delete_backup']);
    $path = __DIR__ . '/backups/' . $file;
    if (file_exists($path) && php_sapi_name() !== 'cli' && is_logged_in() && get_current_role() === 'admin') {
        unlink($path);
        header('Location: sync_db_structure.php?msg=backup_deleted');
        exit;
    }
}

// Define complete target schema map (Aligned exactly with database.sql)
$schema = [
    'audit_logs' => [
        'create' => "CREATE TABLE `audit_logs` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) DEFAULT NULL,
            `action_type` varchar(50) NOT NULL,
            `details` text DEFAULT NULL,
            `ip_address` varchar(45) DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
        'columns' => [
            'id' => 'int(11) NOT NULL AUTO_INCREMENT',
            'user_id' => 'int(11) DEFAULT NULL',
            'action_type' => 'varchar(50) NOT NULL',
            'details' => 'text DEFAULT NULL',
            'ip_address' => 'varchar(45) DEFAULT NULL',
            'created_at' => 'timestamp NOT NULL DEFAULT current_timestamp()'
        ]
    ],
    'calendar_events' => [
        'create' => "CREATE TABLE `calendar_events` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `title` varchar(255) DEFAULT NULL,
            `description` text DEFAULT NULL,
            `event_date` date DEFAULT NULL,
            `created_at` datetime DEFAULT current_timestamp(),
            `image_filename` varchar(255) DEFAULT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
        'columns' => [
            'id' => 'int(11) NOT NULL AUTO_INCREMENT',
            'title' => 'varchar(255) DEFAULT NULL',
            'description' => 'text DEFAULT NULL',
            'event_date' => 'date DEFAULT NULL',
            'created_at' => 'datetime DEFAULT current_timestamp()',
            'image_filename' => 'varchar(255) DEFAULT NULL'
        ]
    ],
    'certificates' => [
        'create' => "CREATE TABLE `certificates` (
            `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
            `student_id` int(11) NOT NULL,
            `certificate_no` varchar(100) DEFAULT NULL,
            `issued_date` date DEFAULT NULL,
            `issued_by` int(11) DEFAULT NULL,
            `file` varchar(255) DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `certificate_no` (`certificate_no`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
        'columns' => [
            'id' => 'int(10) unsigned NOT NULL AUTO_INCREMENT',
            'student_id' => 'int(11) NOT NULL',
            'certificate_no' => 'varchar(100) DEFAULT NULL',
            'issued_date' => 'date DEFAULT NULL',
            'issued_by' => 'int(11) DEFAULT NULL',
            'file' => 'varchar(255) DEFAULT NULL'
        ],
        'indexes' => [
            'certificate_no' => 'UNIQUE KEY `certificate_no` (`certificate_no`)'
        ]
    ],
    'classrooms' => [
        'create' => "CREATE TABLE `classrooms` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `class_name` varchar(100) NOT NULL,
            `total_students` int(11) DEFAULT 0,
            `teacher_id` int(11) DEFAULT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
        'columns' => [
            'id' => 'int(11) NOT NULL AUTO_INCREMENT',
            'class_name' => 'varchar(100) NOT NULL',
            'total_students' => 'int(11) DEFAULT 0',
            'teacher_id' => 'int(11) DEFAULT NULL'
        ]
    ],
    'companies' => [
        'create' => "CREATE TABLE `companies` (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
        'columns' => [
            'id' => 'int(11) NOT NULL AUTO_INCREMENT',
            'name' => 'varchar(255) NOT NULL',
            'branch' => 'varchar(100) DEFAULT NULL',
            'address' => 'text DEFAULT NULL',
            'province' => 'varchar(100) DEFAULT NULL',
            'district' => 'varchar(100) DEFAULT NULL',
            'contact_name' => 'varchar(150) DEFAULT NULL',
            'contact_phone' => 'varchar(50) DEFAULT NULL',
            'contact_email' => 'varchar(150) DEFAULT NULL',
            'company_type' => 'varchar(100) DEFAULT NULL',
            'created_at' => 'datetime DEFAULT current_timestamp()',
            'status' => 'tinyint(1) NOT NULL DEFAULT 1',
            'contact_phone_2' => 'varchar(50) DEFAULT NULL'
        ]
    ],
    'company_branches' => [
        'create' => "CREATE TABLE `company_branches` (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
        'columns' => [
            'id' => 'int(11) NOT NULL AUTO_INCREMENT',
            'company_id' => 'int(11) NOT NULL',
            'classroom_id' => 'int(11) DEFAULT NULL',
            'branch_label' => 'varchar(255) DEFAULT NULL',
            'address' => 'text DEFAULT NULL',
            'contact_name' => 'varchar(150) DEFAULT NULL',
            'contact_phone' => 'varchar(50) DEFAULT NULL',
            'contact_email' => 'varchar(150) DEFAULT NULL',
            'manager_name' => 'varchar(255) DEFAULT NULL',
            'created_at' => 'datetime NOT NULL DEFAULT current_timestamp()'
        ],
        'indexes' => [
            'idx_company_id' => 'KEY `idx_company_id` (`company_id`)',
            'idx_classroom_id' => 'KEY `idx_classroom_id` (`classroom_id`)'
        ]
    ],
    'contact_messages' => [
        'create' => "CREATE TABLE `contact_messages` (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
        'columns' => [
            'id' => 'int(11) NOT NULL AUTO_INCREMENT',
            'user_id' => 'int(11) DEFAULT NULL',
            'sender_name' => 'varchar(255) NOT NULL',
            'contact_info' => 'varchar(255) NOT NULL',
            'subject' => 'varchar(100) DEFAULT NULL',
            'message' => 'text NOT NULL',
            'staff_note' => 'text DEFAULT NULL',
            'status' => "enum('unread','read') DEFAULT 'unread'",
            'created_at' => 'datetime DEFAULT current_timestamp()'
        ]
    ],
    'daily_reports' => [
        'create' => "CREATE TABLE `daily_reports` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `student_id` int(11) NOT NULL,
            `date_work` date NOT NULL,
            `details` text DEFAULT NULL,
            `problems` text DEFAULT NULL,
            `teacher_comment` text DEFAULT NULL,
            `supervisor_comment` text DEFAULT NULL,
            `supervisor_commented_by` int(11) DEFAULT NULL,
            `supervisor_commented_at` datetime DEFAULT NULL,
            `solutions` text DEFAULT NULL,
            `image1` varchar(255) DEFAULT NULL,
            `image2` varchar(255) DEFAULT NULL,
            `status` enum('pending','approved','rejected') DEFAULT 'pending',
            `approved_by` int(11) DEFAULT NULL,
            `approved_at` datetime DEFAULT NULL,
            `created_at` datetime DEFAULT current_timestamp(),
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
        'columns' => [
            'id' => 'int(11) NOT NULL AUTO_INCREMENT',
            'student_id' => 'int(11) NOT NULL',
            'date_work' => 'date NOT NULL',
            'details' => 'text DEFAULT NULL',
            'problems' => 'text DEFAULT NULL',
            'teacher_comment' => 'text DEFAULT NULL',
            'supervisor_comment' => 'text DEFAULT NULL',
            'supervisor_commented_by' => 'int(11) DEFAULT NULL',
            'supervisor_commented_at' => 'datetime DEFAULT NULL',
            'solutions' => 'text DEFAULT NULL',
            'image1' => 'varchar(255) DEFAULT NULL',
            'image2' => 'varchar(255) DEFAULT NULL',
            'status' => "enum('pending','approved','rejected') DEFAULT 'pending'",
            'approved_by' => 'int(11) DEFAULT NULL',
            'approved_at' => 'datetime DEFAULT NULL',
            'created_at' => 'datetime DEFAULT current_timestamp()'
        ]
    ],
    'documents' => [
        'create' => "CREATE TABLE `documents` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `title` varchar(255) NOT NULL,
            `filename` varchar(255) NOT NULL,
            `created_at` datetime DEFAULT current_timestamp(),
            `uploaded_by` int(11) DEFAULT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
        'columns' => [
            'id' => 'int(11) NOT NULL AUTO_INCREMENT',
            'title' => 'varchar(255) NOT NULL',
            'filename' => 'varchar(255) NOT NULL',
            'created_at' => 'datetime DEFAULT current_timestamp()',
            'uploaded_by' => 'int(11) DEFAULT NULL'
        ]
    ],
    'evaluations' => [
        'create' => "CREATE TABLE `evaluations` (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
        'columns' => [
            'id' => 'int(11) NOT NULL AUTO_INCREMENT',
            'student_id' => 'int(11) NOT NULL',
            'teacher_id' => 'int(11) NOT NULL',
            'term' => 'varchar(10) DEFAULT NULL',
            'score_work' => 'int(11) DEFAULT 0',
            'score_report' => 'int(11) DEFAULT 0',
            'score_behavior' => 'int(11) DEFAULT 0',
            'total_score' => 'int(11) DEFAULT 0',
            'grade' => 'varchar(10) DEFAULT NULL',
            'remarks' => 'text DEFAULT NULL',
            'created_at' => 'datetime DEFAULT current_timestamp()'
        ]
    ],
    'internship_settings' => [
        'create' => "CREATE TABLE `internship_settings` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `level_name` varchar(20) NOT NULL,
            `start_date` date DEFAULT NULL,
            `end_date` date DEFAULT NULL,
            `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
        'columns' => [
            'id' => 'int(11) NOT NULL AUTO_INCREMENT',
            'level_name' => 'varchar(20) NOT NULL',
            'start_date' => 'date DEFAULT NULL',
            'end_date' => 'date DEFAULT NULL',
            'updated_at' => 'timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()'
        ]
    ],
    'mentors' => [
        'create' => "CREATE TABLE `mentors` (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
        'columns' => [
            'id' => 'int(10) unsigned NOT NULL AUTO_INCREMENT',
            'user_id' => 'int(10) unsigned NOT NULL',
            'fullname' => 'varchar(200) NOT NULL',
            'phone' => 'varchar(15) DEFAULT NULL',
            'online_schedule' => 'text DEFAULT NULL',
            'supervision_schedule' => 'text DEFAULT NULL',
            'email' => 'varchar(200) DEFAULT NULL',
            'department' => 'varchar(200) DEFAULT NULL',
            'status' => 'tinyint(1) NOT NULL DEFAULT 1',
            'created_at' => 'datetime NOT NULL DEFAULT current_timestamp()',
            'schedule_json' => 'text DEFAULT NULL'
        ]
    ],
    'news' => [
        'create' => "CREATE TABLE `news` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `title` varchar(255) DEFAULT NULL,
            `content` text DEFAULT NULL,
            `image` varchar(255) DEFAULT NULL,
            `video` varchar(255) DEFAULT NULL,
            `video_url` varchar(255) DEFAULT NULL,
            `file` varchar(255) DEFAULT NULL,
            `created_at` datetime DEFAULT current_timestamp(),
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
        'columns' => [
            'id' => 'int(11) NOT NULL AUTO_INCREMENT',
            'title' => 'varchar(255) DEFAULT NULL',
            'content' => 'text DEFAULT NULL',
            'image' => 'varchar(255) DEFAULT NULL',
            'video' => 'varchar(255) DEFAULT NULL',
            'video_url' => 'varchar(255) DEFAULT NULL',
            'file' => 'varchar(255) DEFAULT NULL',
            'created_at' => 'datetime DEFAULT current_timestamp()'
        ]
    ],
    'news_attachments' => [
        'create' => "CREATE TABLE `news_attachments` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `news_id` int(11) NOT NULL,
            `file_name` varchar(255) NOT NULL,
            `original_name` varchar(255) NOT NULL,
            `file_type` varchar(50) NOT NULL,
            `created_at` datetime DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `idx_news_id` (`news_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
        'columns' => [
            'id' => 'int(11) NOT NULL AUTO_INCREMENT',
            'news_id' => 'int(11) NOT NULL',
            'file_name' => 'varchar(255) NOT NULL',
            'original_name' => 'varchar(255) NOT NULL',
            'file_type' => 'varchar(50) NOT NULL',
            'created_at' => 'datetime DEFAULT current_timestamp()'
        ],
        'indexes' => [
            'idx_news_id' => 'KEY `idx_news_id` (`news_id`)'
        ]
    ],
    'notifications' => [
        'create' => "CREATE TABLE `notifications` (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
        'columns' => [
            'id' => 'int(11) NOT NULL AUTO_INCREMENT',
            'user_id' => 'int(11) NOT NULL',
            'title' => 'varchar(255) NOT NULL',
            'message' => 'text NOT NULL',
            'link' => 'varchar(255) DEFAULT NULL',
            'is_read' => 'tinyint(1) DEFAULT 0',
            'created_at' => 'timestamp NOT NULL DEFAULT current_timestamp()',
            'type' => "varchar(50) DEFAULT 'info'",
            'action_url' => 'varchar(255) DEFAULT NULL',
            'expires_at' => 'datetime DEFAULT NULL'
        ],
        'indexes' => [
            'idx_notif_user_read' => 'KEY `idx_notif_user_read` (`user_id`, `is_read`)',
            'idx_notif_user_id_created' => 'KEY `idx_notif_user_id_created` (`user_id`, `created_at`)'
        ]
    ],
    'user_notification_settings' => [
        'create' => "CREATE TABLE `user_notification_settings` (
            `user_id` int(11) NOT NULL,
            `enable_web` tinyint(1) DEFAULT 1,
            `enable_line` tinyint(1) DEFAULT 0,
            `enable_email` tinyint(1) DEFAULT 0,
            `line_token` varchar(255) DEFAULT NULL,
            PRIMARY KEY (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
        'columns' => [
            'user_id' => 'int(11) NOT NULL',
            'enable_web' => 'tinyint(1) DEFAULT 1',
            'enable_line' => 'tinyint(1) DEFAULT 0',
            'enable_email' => 'tinyint(1) DEFAULT 0',
            'line_token' => 'varchar(255) DEFAULT NULL'
        ]
    ],
    'plans' => [
        'create' => "CREATE TABLE `plans` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `teacher_id` int(11) NOT NULL,
            `company_id` int(11) DEFAULT NULL,
            `title` varchar(255) NOT NULL,
            `plan_date` date NOT NULL,
            `note` text DEFAULT NULL,
            `filename` varchar(255) DEFAULT NULL,
            `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
        'columns' => [
            'id' => 'int(11) NOT NULL AUTO_INCREMENT',
            'teacher_id' => 'int(11) NOT NULL',
            'company_id' => 'int(11) DEFAULT NULL',
            'title' => 'varchar(255) NOT NULL',
            'plan_date' => 'date NOT NULL',
            'note' => 'text DEFAULT NULL',
            'filename' => 'varchar(255) DEFAULT NULL',
            'uploaded_at' => 'timestamp NOT NULL DEFAULT current_timestamp()'
        ]
    ],
    'site_settings' => [
        'create' => "CREATE TABLE `site_settings` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `setting_key` varchar(100) NOT NULL,
            `setting_value` text DEFAULT NULL,
            `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`id`),
            UNIQUE KEY `setting_key` (`setting_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
        'columns' => [
            'id' => 'int(11) NOT NULL AUTO_INCREMENT',
            'setting_key' => 'varchar(100) NOT NULL',
            'setting_value' => 'text DEFAULT NULL',
            'updated_at' => 'timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()'
        ],
        'indexes' => [
            'setting_key' => 'UNIQUE KEY `setting_key` (`setting_key`)'
        ]
    ],
    'staff_evaluations' => [
        'create' => "CREATE TABLE `staff_evaluations` (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
        'columns' => [
            'id' => 'int(11) NOT NULL AUTO_INCREMENT',
            'student_id' => 'int(11) NOT NULL',
            'staff_id' => 'int(11) NOT NULL',
            'term' => 'varchar(50) DEFAULT NULL',
            'score_work' => 'int(11) DEFAULT 0',
            'score_report' => 'int(11) DEFAULT 0',
            'score_behavior' => 'int(11) DEFAULT 0',
            'total_score' => 'int(11) DEFAULT 0',
            'grade' => 'varchar(20) DEFAULT NULL',
            'remarks' => 'text DEFAULT NULL',
            'created_at' => 'datetime DEFAULT current_timestamp()'
        ],
        'indexes' => [
            'uk_student' => 'UNIQUE KEY `uk_student` (`student_id`)'
        ]
    ],
    'subject_plans' => [
        'create' => "CREATE TABLE `subject_plans` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `teacher_id` int(11) NOT NULL,
            `subject_code` varchar(20) NOT NULL,
            `subject_name` varchar(255) NOT NULL,
            `company_id` int(11) DEFAULT NULL,
            `filename` varchar(255) NOT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
        'columns' => [
            'id' => 'int(11) NOT NULL AUTO_INCREMENT',
            'teacher_id' => 'int(11) NOT NULL',
            'subject_code' => 'varchar(20) NOT NULL',
            'subject_name' => 'varchar(255) NOT NULL',
            'company_id' => 'int(11) DEFAULT NULL',
            'filename' => 'varchar(255) NOT NULL',
            'created_at' => 'timestamp NOT NULL DEFAULT current_timestamp()'
        ]
    ],
    'supervision_files' => [
        'create' => "CREATE TABLE `supervision_files` (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
        'columns' => [
            'id' => 'int(11) NOT NULL AUTO_INCREMENT',
            'teacher_id' => 'int(11) NOT NULL',
            'student_id' => 'int(11) NOT NULL',
            'company_id' => 'int(11) DEFAULT 0',
            'file_path' => 'varchar(255) DEFAULT NULL',
            'staff_signed_file' => 'varchar(255) DEFAULT NULL',
            'staff_signed_at' => 'datetime DEFAULT NULL',
            'staff_signed_by' => 'int(11) DEFAULT NULL',
            'status' => 'int(11) DEFAULT 0',
            'uploaded_at' => 'timestamp NOT NULL DEFAULT current_timestamp()',
            'director_signed_at' => 'datetime DEFAULT NULL',
            'signed_by' => 'int(11) DEFAULT NULL',
            'signed_file' => 'varchar(255) DEFAULT NULL'
        ]
    ],
    'teacher_assignments' => [
        'create' => "CREATE TABLE `teacher_assignments` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `teacher_id` int(11) NOT NULL,
            `classroom_id` int(11) NOT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
        'columns' => [
            'id' => 'int(11) NOT NULL AUTO_INCREMENT',
            'teacher_id' => 'int(11) NOT NULL',
            'classroom_id' => 'int(11) NOT NULL',
            'created_at' => 'timestamp NOT NULL DEFAULT current_timestamp()'
        ]
    ],
    'supervisor_assignments' => [
        'create' => "CREATE TABLE `supervisor_assignments` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `supervisor_id` int(11) NOT NULL COMMENT 'users.id ที่มี role=supervisor',
            `student_id` int(11) NOT NULL COMMENT 'users.id ที่มี role=student',
            `assigned_at` datetime NOT NULL DEFAULT current_timestamp(),
            `assigned_by` int(11) DEFAULT NULL COMMENT 'user_id ของผู้กำหนด',
            `assigned_by_role` varchar(20) DEFAULT NULL COMMENT 'staff หรือ student',
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_supervisor_student` (`supervisor_id`, `student_id`),
            KEY `idx_supervisor_id` (`supervisor_id`),
            KEY `idx_student_id` (`student_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
        'columns' => [
            'id' => 'int(11) NOT NULL AUTO_INCREMENT',
            'supervisor_id' => 'int(11) NOT NULL',
            'student_id' => 'int(11) NOT NULL',
            'assigned_at' => 'datetime NOT NULL DEFAULT current_timestamp()',
            'assigned_by' => 'int(11) DEFAULT NULL',
            'assigned_by_role' => 'varchar(20) DEFAULT NULL'
        ],
        'indexes' => [
            'uk_supervisor_student' => 'UNIQUE KEY `uk_supervisor_student` (`supervisor_id`, `student_id`)',
            'idx_supervisor_id' => 'KEY `idx_supervisor_id` (`supervisor_id`)',
            'idx_student_id' => 'KEY `idx_student_id` (`student_id`)'
        ]
    ],
    'users' => [
        'create' => "CREATE TABLE `users` (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
        'columns' => [
            'id' => 'int(11) NOT NULL AUTO_INCREMENT',
            'username' => 'varchar(50) NOT NULL',
            'fullname' => 'varchar(150) NOT NULL',
            'email' => 'varchar(150) NOT NULL',
            'password' => 'varchar(255) NOT NULL',
            'role' => 'varchar(50) NOT NULL',
            'phone' => 'varchar(50) DEFAULT NULL',
            'affiliation' => 'varchar(255) DEFAULT NULL',
            'company_id' => 'int(11) DEFAULT NULL',
            'branch_id' => 'int(11) DEFAULT NULL',
            'created_at' => 'datetime DEFAULT current_timestamp()',
            'classroom_id' => 'int(11) DEFAULT NULL',
            'company_name' => 'varchar(255) DEFAULT NULL',
            'company_address' => 'text DEFAULT NULL',
            'company_manager' => 'varchar(255) DEFAULT NULL',
            'company_phone' => 'varchar(20) DEFAULT NULL',
            'trainer_name' => 'varchar(255) DEFAULT NULL',
            'trainer_phone' => 'varchar(20) DEFAULT NULL',
            'trainer_position' => 'varchar(255) DEFAULT NULL',
            'mentor_id' => 'int(11) DEFAULT NULL',
            'student_level' => "varchar(50) DEFAULT 'ปวช.'",
            'profile_image' => 'varchar(255) DEFAULT NULL',
            'mentor_id_2' => 'int(11) DEFAULT NULL',
            'mentor_id_3' => 'int(11) DEFAULT NULL',
            'mentor_id_4' => 'int(11) DEFAULT NULL',
            'mentor_id_5' => 'int(11) DEFAULT NULL',
            'student_code' => 'varchar(50) DEFAULT NULL'
        ],
        'indexes' => [
            'username' => 'UNIQUE KEY `username` (`username`)',
            'email' => 'UNIQUE KEY `email` (`email`)'
        ]
    ]
];

// Global arrays for logging during AJAX or sequential processes
$ajax_logs = [];
$errors_count = 0;

// Stat counters initialization
$tables_checked = 0;
$tables_created = 0;
$columns_added = 0;
$columns_modified = 0;
$indexes_added = 0;

// AJAX ENDPOINTS CONTROLLER
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    // Check administrative authority
    if (php_sapi_name() !== 'cli') {
        if (!is_logged_in() || !in_array(get_current_role(), ['admin'])) {
            echo json_encode(['success' => false, 'message' => 'ไม่ได้รับอนุญาตให้เข้าถึง หรือเซสชันหมดอายุ']);
            exit;
        }
    }
    
    $action = $_GET['action'] ?? '';
    $is_dry_run = isset($_GET['dry_run']) && $_GET['dry_run'] === '1';
    $sync_counts = isset($_GET['sync_counts']) && $_GET['sync_counts'] === '1';
    $repair_corrupted = isset($_GET['repair_corrupted']) && $_GET['repair_corrupted'] === '1';
    
    switch ($action) {
        case 'backup':
            $backup_dir = __DIR__ . '/backups';
            if (!file_exists($backup_dir)) {
                mkdir($backup_dir, 0777, true);
            }
            $backup_file = $backup_dir . '/db_backup_before_sync_' . date('Ymd_His') . '.sql';
            try {
                $compressed = false;
                $final_file = backup_database_pure_php($conn, $backup_file, $compressed);
                $rel_path = 'db/backups/' . basename($final_file);
                $size = filesize($final_file);
                
                output_message('success', "สร้างไฟล์สำรองฐานข้อมูลสำเร็จ", "บันทึกข้อมูลไปที่ <b>$rel_path</b> (" . format_size($size) . ") โดยระบบ" . ($compressed ? 'บีบอัด ZIP' : 'SQL ดิบ') . ".", $is_dry_run);
                
                echo json_encode([
                    'success' => true,
                    'logs' => $ajax_logs,
                    'file' => basename($final_file),
                    'size' => format_size($size),
                    'compressed' => $compressed
                ]);
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'message' => $e->getMessage()
                ]);
            }
            break;
            
        case 'schema_sync':
            foreach ($schema as $tableName => $def) {
                $tables_checked++;
                $exists_q = $conn->query("SHOW TABLES LIKE '$tableName'");
                
                if ($exists_q->num_rows === 0) {
                    if ($is_dry_run) {
                        output_message('warning', "ไม่พบตาราง: $tableName", "การทำงาน: จะทำการสร้างโครงสร้างตารางใหม่ตามเป้าหมาย", $is_dry_run);
                        $tables_created++;
                    } else {
                        try {
                            if ($conn->query($def['create'])) {
                                output_message('success', "สร้างตารางสำเร็จ: $tableName", "สร้างโครงสร้างตารางใหม่ในฐานข้อมูลเรียบร้อยแล้ว", $is_dry_run);
                                $tables_created++;
                            }
                        } catch (Exception $e) {
                            output_message('danger', "สร้างตารางล้มเหลว: $tableName", htmlspecialchars($e->getMessage()), $is_dry_run);
                            $errors_count++;
                        }
                    }
                } else {
                    // Check Columns
                    $cols_res = $conn->query("SHOW COLUMNS FROM `$tableName`");
                    $existing_cols = [];
                    while ($c = $cols_res->fetch_assoc()) {
                        $existing_cols[strtolower($c['Field'])] = [
                            'type' => strtolower($c['Type']),
                            'null' => strtoupper($c['Null']),
                            'default' => $c['Default'],
                            'raw' => $c
                        ];
                    }
                    
                    foreach ($def['columns'] as $colName => $colDefString) {
                        $colNameLower = strtolower($colName);
                        if (!isset($existing_cols[$colNameLower])) {
                            $alter_query = "ALTER TABLE `$tableName` ADD `$colName` $colDefString";
                            if ($is_dry_run) {
                                output_message('warning', "ไม่พบคอลัมน์: $tableName.$colName", "การทำงาน: จะเพิ่มนิยามคอลัมน์ด้วยคำสั่ง: <span class='query-code'>$alter_query</span>", $is_dry_run);
                                $columns_added++;
                            } else {
                                try {
                                    if ($conn->query($alter_query)) {
                                        output_message('success', "เพิ่มคอลัมน์สำเร็จ: $tableName.$colName", "เพิ่มคอลัมน์ใหม่เรียบร้อยแล้ว", $is_dry_run);
                                        $columns_added++;
                                    }
                                } catch (Exception $e) {
                                    output_message('danger', "เพิ่มคอลัมน์ล้มเหลว: $tableName.$colName", htmlspecialchars($e->getMessage()) . "<br>คำสั่ง: <code>$alter_query</code>", $is_dry_run);
                                    $errors_count++;
                                }
                            }
                        } else {
                            $existing = $existing_cols[$colNameLower];
                            if (column_needs_modification($colName, $colDefString, $existing)) {
                                $alter_query = "ALTER TABLE `$tableName` MODIFY `$colName` $colDefString";
                                if ($is_dry_run) {
                                    output_message('info', "ชนิดข้อมูลคอลัมน์ไม่ตรงกัน: $tableName.$colName", "การทำงาน: จะปรับปรุงข้อกำหนดจาก <b>{$existing['type']}</b> ให้ตรงกับโครงสร้างเป้าหมาย", $is_dry_run);
                                    $columns_modified++;
                                } else {
                                    try {
                                        if ($conn->query($alter_query)) {
                                            output_message('success', "แก้ไขคอลัมน์สำเร็จ: $tableName.$colName", "ปรับปรุงชนิดข้อมูลจาก <b>{$existing['type']}</b> เป็นค่าที่ต้องการเรียบร้อยแล้ว", $is_dry_run);
                                            $columns_modified++;
                                        }
                                    } catch (Exception $e) {
                                        output_message('danger', "แก้ไขคอลัมน์ล้มเหลว: $tableName.$colName", htmlspecialchars($e->getMessage()) . "<br>คำสั่ง: <code>$alter_query</code>", $is_dry_run);
                                        $errors_count++;
                                    }
                                }
                            }
                        }
                    }
                    
                    // Smart Index & Constraints Checking
                    if (isset($def['indexes'])) {
                        $index_res = $conn->query("SHOW INDEX FROM `$tableName`");
                        $existing_indexes = [];
                        while ($idx = $index_res->fetch_assoc()) {
                            $existing_indexes[strtolower($idx['Key_name'])] = $idx;
                        }
                        
                        foreach ($def['indexes'] as $indexName => $indexDefString) {
                            $indexNameLower = strtolower($indexName);
                            if (!isset($existing_indexes[$indexNameLower])) {
                                $alter_query = "ALTER TABLE `$tableName` ADD $indexDefString";
                                if ($is_dry_run) {
                                    output_message('warning', "ไม่พบดัชนี (Index Key): $tableName.$indexName", "การทำงาน: จะสร้างดัชนีคีย์ด้วยคำสั่ง: <span class='query-code'>$alter_query</span>", $is_dry_run);
                                    $indexes_added++;
                                } else {
                                    try {
                                        if ($conn->query($alter_query)) {
                                            output_message('success', "เพิ่มดัชนีฐานข้อมูลสำเร็จ: $tableName.$indexName", "ซิงค์ข้อจำกัดและดัชนีเสร็จสิ้นอย่างสมบูรณ์", $is_dry_run);
                                            $indexes_added++;
                                        }
                                    } catch (Exception $e) {
                                        output_message('danger', "ซิงค์ดัชนีล้มเหลว: $tableName.$indexName", htmlspecialchars($e->getMessage()) . "<br>คำสั่ง: <code>$alter_query</code>", $is_dry_run);
                                        $errors_count++;
                                    }
                                }
                            }
                        }
                    }
                }
            }
            
            echo json_encode([
                'success' => $errors_count === 0,
                'logs' => $ajax_logs,
                'stats' => [
                    'checked' => $tables_checked,
                    'created' => $tables_created,
                    'columns_added' => $columns_added,
                    'columns_modified' => $columns_modified,
                    'indexes_added' => $indexes_added,
                    'errors' => $errors_count
                ]
            ]);
            break;
            
        case 'optimization_sync':
            // 3.1 Drop legacy unique constraint on companies.name
            try {
                $check_index = $conn->query("SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'companies' AND index_name = 'name' AND non_unique = 0");
                if ($check_index && $check_index->num_rows > 0) {
                    if ($is_dry_run) {
                        output_message('info', "ตรวจพบข้อจำกัดเดิมที่ล้าสมัย", "การทำงาน: จะทำการลบข้อจำกัดดัชนีประเภท UNIQUE เดิมใน <b>companies.name</b> เพื่อรองรับการแยกสาขาของสถานประกอบการ", $is_dry_run);
                    } else {
                        $conn->query("ALTER TABLE `companies` DROP INDEX `name`");
                        output_message('success', "ลบข้อจำกัดดัชนีเดิมสำเร็จ", "ลบข้อจำกัด UNIQUE เดิมใน <b>companies.name</b> เรียบร้อยแล้ว", $is_dry_run);
                    }
                }
            } catch (Exception $e) {
                output_message('warning', "ข้ามขั้นตอนตรวจสอบดัชนีเดิม", htmlspecialchars($e->getMessage()), $is_dry_run);
            }
            
            // 3.2 Dynamic Character Set Conversion to UTF8MB4 (perfect Thai characters support)
            $tables_to_unicode = array_keys($schema);
            foreach ($tables_to_unicode as $tbl) {
                try {
                    $tbl_check = $conn->query("SHOW TABLES LIKE '$tbl'");
                    if ($tbl_check->num_rows > 0) {
                        $status_res = $conn->query("SHOW TABLE STATUS LIKE '$tbl'")->fetch_assoc();
                        $current_collation = $status_res['Collation'] ?? '';
                        
                        if (strtolower($current_collation) !== 'utf8mb4_unicode_ci') {
                            if ($is_dry_run) {
                                output_message('info', "รหัสการจัดเก็บตารางไม่ตรงตามมาตรฐาน: $tbl", "การทำงาน: จะทำการแปลงรหัสตัวอักษรและชุดอักขระเป็น utf8mb4_unicode_ci", $is_dry_run);
                            } else {
                                $conn->query("ALTER TABLE `$tbl` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                                output_message('success', "ปรับแต่งรหัสภาษาสำเร็จ: $tbl", "แปลงรหัสตารางเป็น CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci เรียบร้อยแล้ว", $is_dry_run);
                            }
                        }
                    }
                } catch (Exception $e) {
                    output_message('warning', "ข้ามขั้นตอนการแก้ไขรหัสภาษาของตาราง $tbl", htmlspecialchars($e->getMessage()), $is_dry_run);
                }
            }
            
            echo json_encode([
                'success' => true,
                'logs' => $ajax_logs
            ]);
            break;
            
        case 'seeding_sync':
            // 4.1 Seed Admin safely
            try {
                $check_admin = $conn->query("SELECT id FROM users WHERE username = 'admin' LIMIT 1");
                if ($check_admin->num_rows === 0) {
                    if ($is_dry_run) {
                        output_message('warning', "ไม่พบบัญชีผู้ดูแลระบบ (Admin User)", "การทำงาน: จะทำการสร้างบัญชีผู้ดูแลระบบเริ่มต้น (ชื่อผู้ใช้: <b>admin</b>)", $is_dry_run);
                    } else {
                        $stmt = $conn->prepare("INSERT INTO `users` (`id`, `username`, `fullname`, `email`, `password`, `role`, `phone`, `student_level`) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                        $admin_id = 1;
                        $admin_user = 'admin';
                        $admin_name = 'ผู้ดูแลระบบสูงสุด';
                        $admin_email = 'admin@dve-system.local';
                        $admin_pw = '$2y$10$qR.7d1vH80vO04zP2H6jX.4cK2yv.j19JzR5yVdK3RjC6oG6r1vG6'; // admin123
                        $admin_role = 'admin';
                        $admin_phone = '0800000000';
                        $admin_lvl = 'ปวช.';
                        $stmt->bind_param("isssssss", $admin_id, $admin_user, $admin_name, $admin_email, $admin_pw, $admin_role, $admin_phone, $admin_lvl);
                        if ($stmt->execute()) {
                            output_message('success', "สร้างบัญชีผู้ดูแลระบบสูงสุดสำเร็จ", "สร้างข้อมูลเข้าสู่ระบบเริ่มต้นเรียบร้อยแล้ว: admin / admin123", $is_dry_run);
                        }
                        $stmt->close();
                    }
                } else {
                    output_message('success', "ตรวจสอบความถูกต้องบัญชีผู้ดูแลระบบสูงสุดเรียบร้อย", "พบบัญชีผู้ดูแลระบบในฐานข้อมูลแล้ว ข้อมูลการเข้าสู่ระบบเดิมยังคงปลอดภัย", $is_dry_run);
                }
            } catch (Exception $e) {
                output_message('danger', "การเพิ่มข้อมูลบัญชีผู้ดูแลระบบล้มเหลว", htmlspecialchars($e->getMessage()), $is_dry_run);
            }
            
            // 4.2 Seed default site settings safely
            $default_settings = [
                'college_director_name' => 'นายสมชาย ดีงาม',
                'college_director_title' => 'ผู้อำนวยการวิทยาลัยเทคนิคการอาชีพ',
                'college_name_th' => 'วิทยาลัยเทคนิคการอาชีพ',
                'college_name_en' => 'Industrial Education College',
                'system_title' => 'ระบบบริหารจัดการฝึกงานวิชาชีพ (DVE System)',
                'sys_default_term' => '1/2569'
            ];
            
            foreach ($default_settings as $key => $val) {
                try {
                    $check_set = $conn->prepare("SELECT id FROM site_settings WHERE setting_key = ? LIMIT 1");
                    $check_set->bind_param("s", $key);
                    $check_set->execute();
                    $res = $check_set->get_result();
                    $check_set->close();
                    
                    if ($res->num_rows === 0) {
                        if ($is_dry_run) {
                            output_message('warning', "ไม่พบการตั้งค่าระบบเริ่มต้น: $key", "การทำงาน: จะทำการบันทึกค่าระบบเริ่มต้นเป็น '<b>" . htmlspecialchars($val) . "</b>'", $is_dry_run);
                        } else {
                            $stmt = $conn->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)");
                            $stmt->bind_param("ss", $key, $val);
                            if ($stmt->execute()) {
                                output_message('success', "สร้างค่าระบบเริ่มต้นสำเร็จ: $key", "บันทึกการตั้งค่าระบบเรียบร้อยแล้ว", $is_dry_run);
                            }
                            $stmt->close();
                        }
                    } else {
                        output_message('success', "การตั้งค่าระบบถูกต้องและปลอดภัย: $key", "ค่าคอนฟิกเกอเรชันปัจจุบันถูกเก็บรักษาไว้อย่างถูกต้อง", $is_dry_run);
                    }
                } catch (Exception $e) {
                    output_message('danger', "การสร้างค่าระบบ $key ล้มเหลว", htmlspecialchars($e->getMessage()), $is_dry_run);
                }
            }
            
            echo json_encode([
                'success' => true,
                'logs' => $ajax_logs
            ]);
            break;
            
        case 'post_sync':
            // Recalculate student counts if requested
            if ($sync_counts) {
                try {
                    $cls_q = $conn->query("SELECT id, class_name FROM classrooms");
                    $classes = [];
                    while ($r = $cls_q->fetch_assoc()) {
                        $classes[] = $r;
                    }
                    
                    $updated_counts = 0;
                    foreach ($classes as $c) {
                        $cid = (int)$c['id'];
                        $name = $c['class_name'];
                        
                        $count_stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM users WHERE role = 'student' AND classroom_id = ?");
                        $count_stmt->bind_param("i", $cid);
                        $count_stmt->execute();
                        $cnt = $count_stmt->get_result()->fetch_assoc()['cnt'] ?? 0;
                        $count_stmt->close();
                        
                        if (!$is_dry_run) {
                            $up_stmt = $conn->prepare("UPDATE classrooms SET total_students = ? WHERE id = ?");
                            $up_stmt->bind_param("ii", $cnt, $cid);
                            $up_stmt->execute();
                            $up_stmt->close();
                            output_message('success', "ปรับปรุงข้อมูลสถิติชั้นเรียนสำเร็จ: $name", "อัปเดตจำนวนนักเรียนทั้งหมดเป็น <b>$cnt</b> คน", $is_dry_run);
                        } else {
                            output_message('info', "ข้อมูลสถิติชั้นเรียนพร้อมจำลอง: $name", "จะปรับปรุงจำนวนนักเรียนเป็น <b>$cnt</b> คน", $is_dry_run);
                        }
                        $updated_counts++;
                    }
                } catch (Exception $e) {
                    output_message('danger', "คำนวณจำนวนนักเรียนสะสมในแต่ละชั้นเรียนล้มเหลว", htmlspecialchars($e->getMessage()), $is_dry_run);
                }
            }
            
            // Clean corrupted Thai symbols
            if ($repair_corrupted) {
                $fields_to_clean = ['affiliation', 'fullname', 'company_name', 'company_address', 'trainer_name', 'trainer_position'];
                $total_cleaned = 0;
                foreach ($fields_to_clean as $field) {
                    if (!$is_dry_run) {
                        try {
                            $q = "UPDATE users SET `$field` = '' WHERE `$field` LIKE '%?%'";
                            if ($conn->query($q)) {
                                $affected = $conn->affected_rows;
                                if ($affected > 0) {
                                    output_message('success', "ล้างข้อมูลคอลัมน์ $field สำเร็จ", "ล้างข้อมูลอักขระไทยที่เสียหายได้ <b>$affected</b> แถวข้อมูล", $is_dry_run);
                                    $total_cleaned += $affected;
                                }
                            }
                        } catch (Exception $e) {
                            output_message('danger', "เกิดข้อผิดพลาดในการล้างข้อมูลคอลัมน์ $field", htmlspecialchars($e->getMessage()), $is_dry_run);
                        }
                    } else {
                        try {
                            $q_count = $conn->query("SELECT COUNT(*) as count FROM users WHERE `$field` LIKE '%?%'")->fetch_assoc();
                            $cnt = $q_count['count'] ?? 0;
                            if ($cnt > 0) {
                                output_message('warning', "พบข้อมูลตัวอักษรเสียหายในคอลัมน์ $field", "การทำงาน: ตรวจพบข้อมูลอักขระเสียหายจำนวน <b>$cnt</b> แถวข้อมูลที่จะถูกล้าง", $is_dry_run);
                            }
                        } catch (Exception $e) { }
                    }
                }
                if ($total_cleaned > 0) {
                    output_message('success', "ตรวจสอบการล้างอักขระที่เสียหายเสร็จสิ้น", "ล้างข้อมูลฟิลด์อักขระไทยที่เสียหายรวมทั้งสิ้น <b>$total_cleaned</b> รายการ", $is_dry_run);
                } else {
                    output_message('success', "ตรวจสอบการล้างอักขระที่เสียหายเสร็จสิ้น", "ไม่พบอักขระภาษาไทยที่เสียหาย ('?') ในตารางข้อมูลปัจจุบัน", $is_dry_run);
                }
            }
            
            // Orphan checks
            $orphans = [];
            try {
                $orphan_studs = $conn->query("SELECT u.id, u.fullname, u.student_code, u.classroom_id FROM users u LEFT JOIN classrooms c ON u.classroom_id = c.id WHERE u.role = 'student' AND u.classroom_id IS NOT NULL AND c.id IS NULL");
                if ($orphan_studs && $orphan_studs->num_rows > 0) {
                    $list = [];
                    while ($st = $orphan_studs->fetch_assoc()) {
                        $list[] = [
                            'name' => $st['fullname'],
                            'code' => $st['student_code'],
                            'missing_id' => $st['classroom_id']
                        ];
                    }
                    $orphans['students'] = $list;
                    output_message('warning', "ข้อมูลเชื่อมโยงห้องเรียนขาดความสัมพันธ์ (Orphaned)", $orphan_studs->num_rows . " นักเรียนอ้างอิงรหัสห้องเรียนที่ไม่มีอยู่จริง", $is_dry_run);
                } else {
                    output_message('success', "สถานะความสัมพันธ์ชั้นเรียนของนักเรียนถูกต้อง", "บัญชีนักเรียนทั้งหมดอ้างอิงไปยังห้องเรียนที่มีอยู่จริงอย่างถูกต้อง", $is_dry_run);
                }
                
                $orphan_br = $conn->query("SELECT b.id, b.branch_label, b.classroom_id FROM company_branches b LEFT JOIN classrooms c ON b.classroom_id = c.id WHERE b.classroom_id IS NOT NULL AND c.id IS NULL");
                if ($orphan_br && $orphan_br->num_rows > 0) {
                    $list = [];
                    while ($br = $orphan_br->fetch_assoc()) {
                        $list[] = [
                            'branch' => $br['branch_label'],
                            'missing_id' => $br['classroom_id']
                        ];
                    }
                    $orphans['branches'] = $list;
                    output_message('warning', "ข้อมูลความสัมพันธ์ห้องเรียนของสาขาขาดการเชื่อมโยง", $orphan_br->num_rows . " สาขาสถานประกอบการอ้างอิงรหัสห้องเรียนที่ไม่พบในระบบ", $is_dry_run);
                } else {
                    output_message('success', "สถานะความสัมพันธ์สาขาสถานประกอบการถูกต้อง", "สาขาสถานประกอบการทั้งหมดอ้างอิงห้องเรียนที่ถูกต้อง", $is_dry_run);
                }
            } catch (Exception $e) {
                output_message('danger', "เกิดข้อผิดพลาดในการวิเคราะห์ความถูกต้องเชิงโครงสร้าง", htmlspecialchars($e->getMessage()), $is_dry_run);
            }
            
            echo json_encode([
                'success' => true,
                'logs' => $ajax_logs,
                'orphans' => $orphans
            ]);
            break;

        case 'health_check':
            $scores = [];

            // 1. Schema Alignment (max 25pt)
            $missing_tbls = 0;
            $missing_cols = 0;
            $total_cols = 0;
            foreach ($schema as $tbl => $def) {
                try {
                    $chk = $conn->query("SHOW TABLES LIKE '$tbl'");
                    if (!$chk || $chk->num_rows === 0) {
                        $missing_tbls++;
                    } else {
                        $c_res = $conn->query("SHOW COLUMNS FROM `$tbl`");
                        $ex_cols = [];
                        if ($c_res) {
                            while ($r = $c_res->fetch_assoc()) $ex_cols[strtolower($r['Field'])] = true;
                        }
                        foreach ($def['columns'] as $cname => $cdef) {
                            $total_cols++;
                            if (!isset($ex_cols[strtolower($cname)])) $missing_cols++;
                        }
                    }
                } catch (Exception $e) {
                    $missing_tbls++;
                }
            }
            $schema_score = 25;
            if ($missing_tbls > 0) $schema_score -= ($missing_tbls * 8);
            if ($missing_cols > 0) $schema_score -= ($missing_cols * 2);
            $scores['schema'] = max(0, $schema_score);

            // 2. Character Set & Collation (max 15pt)
            $non_utf8 = 0;
            foreach (array_keys($schema) as $tbl) {
                try {
                    $st = $conn->query("SHOW TABLE STATUS LIKE '$tbl'");
                    if ($st && $r = $st->fetch_assoc()) {
                        if (strtolower($r['Collation'] ?? '') !== 'utf8mb4_unicode_ci') $non_utf8++;
                    }
                } catch (Exception $e) {}
            }
            $scores['charset'] = max(0, 15 - ($non_utf8 * 5));

            // 3. Data Integrity & Orphans (max 20pt)
            $orphan_cnt = 0;
            try {
                $q1 = $conn->query("SELECT COUNT(*) as cnt FROM daily_reports r LEFT JOIN users u ON r.student_id = u.id WHERE u.id IS NULL");
                if ($q1) $orphan_cnt += (int)($q1->fetch_assoc()['cnt'] ?? 0);
            } catch (Exception $e) {}

            try {
                $q2 = $conn->query("SELECT COUNT(*) as cnt FROM users u LEFT JOIN classrooms c ON u.classroom_id = c.id WHERE u.role = 'student' AND u.classroom_id IS NOT NULL AND c.id IS NULL");
                if ($q2) $orphan_cnt += (int)($q2->fetch_assoc()['cnt'] ?? 0);
            } catch (Exception $e) {}

            $scores['orphans'] = max(0, 20 - ($orphan_cnt * 2));

            // 4. Index Coverage (max 20pt)
            $unindexed_fk = 0;
            $fk_checks = [
                'users' => 'classroom_id',
                'daily_reports' => 'student_id',
                'certificates' => 'student_id',
                'supervision_schedules' => 'teacher_id'
            ];
            foreach ($fk_checks as $tbl => $col) {
                try {
                    $tbl_chk = $conn->query("SHOW TABLES LIKE '$tbl'");
                    if ($tbl_chk && $tbl_chk->num_rows > 0) {
                        $idx_q = $conn->query("SHOW INDEX FROM `$tbl` WHERE Column_name = '$col'");
                        if (!$idx_q || $idx_q->num_rows === 0) $unindexed_fk++;
                    }
                } catch (Exception $e) {}
            }
            $scores['indexes'] = max(0, 20 - ($unindexed_fk * 5));

            // 5. Storage Overhead / Defrag (max 10pt)
            $total_overhead = 0;
            try {
                $st_all = $conn->query("SHOW TABLE STATUS");
                if ($st_all) {
                    while ($r = $st_all->fetch_assoc()) {
                        $total_overhead += (int)($r['Data_free'] ?? 0);
                    }
                }
            } catch (Exception $e) {}
            $overhead_mb = round($total_overhead / (1024 * 1024), 2);
            $scores['defrag'] = $overhead_mb > 5 ? 5 : 10;

            // 6. Backup Freshness (max 10pt)
            $has_recent_backup = false;
            $b_dir = __DIR__ . '/backups';
            if (is_dir($b_dir)) {
                foreach (scandir($b_dir) as $f) {
                    if ($f === '.' || $f === '..') continue;
                    if (filemtime($b_dir . '/' . $f) > (time() - 7 * 86400)) {
                        $has_recent_backup = true;
                        break;
                    }
                }
            }
            $scores['backup'] = $has_recent_backup ? 10 : 0;

            $total_health_score = array_sum($scores);
            $health_status = 'EXCELLENT';
            $status_color = '#10b981';

            if ($total_health_score < 60) {
                $health_status = 'CRITICAL';
                $status_color = '#ef4444';
            } elseif ($total_health_score < 80) {
                $health_status = 'NEEDS_ATTENTION';
                $status_color = '#f59e0b';
            } elseif ($total_health_score < 95) {
                $health_status = 'GOOD';
                $status_color = '#3b82f6';
            }

            echo json_encode([
                'success' => true,
                'total_score' => $total_health_score,
                'status' => $health_status,
                'color' => $status_color,
                'scores' => $scores,
                'overhead_mb' => $overhead_mb,
                'orphan_count' => $orphan_cnt,
                'missing_tables' => $missing_tbls,
                'missing_cols' => $missing_cols
            ]);
            break;

        case 'visual_diff':
            $diffs = [];
            foreach ($schema as $tbl => $def) {
                $chk = $conn->query("SHOW TABLES LIKE '$tbl'");
                if (!$chk || $chk->num_rows === 0) {
                    $diffs[$tbl] = [
                        'status' => 'missing',
                        'label' => 'ไม่พบตาราง',
                        'columns' => []
                    ];
                } else {
                    $c_res = $conn->query("SHOW COLUMNS FROM `$tbl`");
                    $ex_cols = [];
                    while ($r = $c_res->fetch_assoc()) {
                        $ex_cols[strtolower($r['Field'])] = [
                            'type' => strtolower($r['Type']),
                            'null' => strtoupper($r['Null'])
                        ];
                    }

                    $col_diffs = [];
                    $has_mismatch = false;
                    foreach ($def['columns'] as $cname => $cdef) {
                        $cn_lower = strtolower($cname);
                        if (!isset($ex_cols[$cn_lower])) {
                            $col_diffs[$cname] = [
                                'status' => 'missing',
                                'expected' => $cdef,
                                'actual' => 'ไม่มีคอลัมน์นี้'
                            ];
                            $has_mismatch = true;
                        } else {
                            $col_diffs[$cname] = [
                                'status' => 'matched',
                                'expected' => $cdef,
                                'actual' => $ex_cols[$cn_lower]['type']
                            ];
                        }
                    }

                    $diffs[$tbl] = [
                        'status' => $has_mismatch ? 'alter_required' : 'matched',
                        'label' => $has_mismatch ? 'ต้องปรับปรุง' : 'ตรงตามมาตรฐาน',
                        'columns' => $col_diffs
                    ];
                }
            }

            echo json_encode([
                'success' => true,
                'diffs' => $diffs
            ]);
            break;

        case 'optimize_tables':
            $optimized = 0;
            $bytes_freed = 0;
            foreach (array_keys($schema) as $tbl) {
                $chk = $conn->query("SHOW TABLES LIKE '$tbl'");
                if ($chk && $chk->num_rows > 0) {
                    $st_before = $conn->query("SHOW TABLE STATUS LIKE '$tbl'")->fetch_assoc();
                    $free_before = (int)($st_before['Data_free'] ?? 0);
                    $conn->query("OPTIMIZE TABLE `$tbl`");
                    $bytes_freed += $free_before;
                    $optimized++;
                    output_message('success', "Optimize ตารางสำเร็จ: $tbl", "จัดระเบียบ Index และคืนพื้นที่ " . format_size($free_before), $is_dry_run);
                }
            }
            $mb_freed = round($bytes_freed / (1024 * 1024), 2);
            output_message('success', "ทำการ Optimize ทุกตารางเสร็จสิ้น", "Optimize ทั้งหมด <b>$optimized</b> ตาราง (คืนพื้นที่รวม <b>{$mb_freed} MB</b>)", $is_dry_run);

            echo json_encode([
                'success' => true,
                'logs' => $ajax_logs,
                'freed_mb' => $mb_freed,
                'optimized_count' => $optimized
            ]);
            break;

        case 'clean_db_orphans':
            $cleaned = 0;
            // 1. Clean orphan daily_reports
            $conn->query("DELETE r FROM daily_reports r LEFT JOIN users u ON r.student_id = u.id WHERE u.id IS NULL");
            $c1 = $conn->affected_rows;
            if ($c1 > 0) {
                output_message('success', "ล้างรายงานประจำวันลอยสำเร็จ", "ลบรายงานที่ไม่มีนักเรียนในระบบไป <b>$c1</b> รายการ", $is_dry_run);
                $cleaned += $c1;
            }

            // 2. Reset invalid classroom_ids in users
            $conn->query("UPDATE users u LEFT JOIN classrooms c ON u.classroom_id = c.id SET u.classroom_id = NULL WHERE u.role = 'student' AND u.classroom_id IS NOT NULL AND c.id IS NULL");
            $c2 = $conn->affected_rows;
            if ($c2 > 0) {
                output_message('success', "ซ่อมแซมรหัสห้องเรียนนักเรียนสำเร็จ", "ล้างรหัสห้องเรียนที่ไม่มีจริงไป <b>$c2</b> รายการ", $is_dry_run);
                $cleaned += $c2;
            }

            if ($cleaned === 0) {
                output_message('success', "ตรวจสอบข้อมูลความสัมพันธ์เสร็จสิ้น", "ไม่พบข้อมูลหลุดลอย (Orphaned Records) ในฐานข้อมูล", $is_dry_run);
            }

            echo json_encode([
                'success' => true,
                'logs' => $ajax_logs,
                'cleaned_count' => $cleaned
            ]);
            break;
    }
    exit;
}

// -----------------------------------------------------------------------------
// BACKWARD-COMPATIBLE SEQUENTIAL EXECUTION: CLI OR CLASSIC MODE (?classic=1)
// -----------------------------------------------------------------------------
if (php_sapi_name() === 'cli' || isset($_GET['classic'])) {
    $is_dry_run = isset($_GET['dry_run']) || (php_sapi_name() === 'cli' && in_array('--dry-run', $argv));
    $do_backup = !isset($_GET['skip_backup']) && !(php_sapi_name() === 'cli' && in_array('--skip-backup', $argv));
    $repair_corrupted = isset($_GET['repair_corrupted']);
    $sync_counts = isset($_GET['sync_counts']);
    
    if (php_sapi_name() !== 'cli') {
        echo '<!DOCTYPE html><html lang="th"><head><meta charset="UTF-8"><title>ผลลัพธ์การปรับโครงสร้างระบบฐานข้อมูล (Classic)</title></head><body style="font-family:sans-serif; background:#f3f4f6; padding:20px;">';
        echo '<h2>🔄 รายงานผลการปรับโครงสร้างฐานข้อมูล (Classic)</h2><pre style="background:#1e293b; color:#10b981; padding:20px; border-radius:10px; overflow-x:auto;">';
    } else {
        echo "====================================================\n";
        echo "🔧 โปรแกรมปรับโครงสร้างฐานข้อมูลอัตโนมัติ (CLI)\n";
        echo "====================================================\n";
    }
    
    // Step 1: Backup
    if ($do_backup && !$is_dry_run) {
        $backup_dir = __DIR__ . '/backups';
        if (!file_exists($backup_dir)) mkdir($backup_dir, 0777, true);
        $backup_file = $backup_dir . '/db_backup_before_sync_' . date('Ymd_His') . '.sql';
        try {
            $compressed = false;
            $final_file = backup_database_pure_php($conn, $backup_file, $compressed);
            output_message('success', "สร้างไฟล์สำรองข้อมูลสำเร็จ", "บันทึกในชื่อ " . basename($final_file) . " (" . ($compressed ? 'ZIP' : 'SQL') . ")", $is_dry_run);
        } catch (Exception $e) {
            output_message('danger', "การสำรองข้อมูลล้มเหลว", $e->getMessage(), $is_dry_run);
            exit;
        }
    }
    
    // Step 2: Schema Loop
    foreach ($schema as $tableName => $def) {
        $tables_checked++;
        $exists_q = $conn->query("SHOW TABLES LIKE '$tableName'");
        if ($exists_q->num_rows === 0) {
            if ($is_dry_run) {
                output_message('warning', "ไม่พบตาราง: $tableName", "การทำงาน: จะสร้างโครงสร้างตารางใหม่", $is_dry_run);
            } else {
                $conn->query($def['create']);
                output_message('success', "สร้างตารางสำเร็จ: $tableName", "สร้างตารางเรียบร้อย", $is_dry_run);
            }
        } else {
            // Columns
            $cols_res = $conn->query("SHOW COLUMNS FROM `$tableName`");
            $existing_cols = [];
            while ($c = $cols_res->fetch_assoc()) {
                $existing_cols[strtolower($c['Field'])] = [
                    'type' => strtolower($c['Type']),
                    'null' => strtoupper($c['Null']),
                    'default' => $c['Default'],
                    'raw' => $c
                ];
            }
            foreach ($def['columns'] as $colName => $colDefString) {
                $colNameLower = strtolower($colName);
                if (!isset($existing_cols[$colNameLower])) {
                    $alter_query = "ALTER TABLE `$tableName` ADD `$colName` $colDefString";
                    if ($is_dry_run) {
                        output_message('warning', "ไม่พบคอลัมน์: $tableName.$colName", "คำสั่ง SQL: $alter_query", $is_dry_run);
                    } else {
                        $conn->query($alter_query);
                        output_message('success', "เพิ่มคอลัมน์สำเร็จ: $tableName.$colName", "เสร็จสิ้น", $is_dry_run);
                    }
                } else {
                    $existing = $existing_cols[$colNameLower];
                    if (column_needs_modification($colName, $colDefString, $existing)) {
                        $alter_query = "ALTER TABLE `$tableName` MODIFY `$colName` $colDefString";
                        if ($is_dry_run) {
                            output_message('info', "ชนิดข้อมูลคอลัมน์ไม่ตรงกัน: $tableName.$colName", "จะแก้ไขโครงสร้างด้วยคำสั่ง: $alter_query", $is_dry_run);
                        } else {
                            $conn->query($alter_query);
                            output_message('success', "แก้ไขคอลัมน์สำเร็จ: $tableName.$colName", "ประสานชนิดข้อมูลเสร็จสิ้น", $is_dry_run);
                        }
                    }
                }
            }
            // Indexes
            if (isset($def['indexes'])) {
                $index_res = $conn->query("SHOW INDEX FROM `$tableName`");
                $existing_indexes = [];
                while ($idx = $index_res->fetch_assoc()) {
                    $existing_indexes[strtolower($idx['Key_name'])] = $idx;
                }
                foreach ($def['indexes'] as $indexName => $indexDefString) {
                    $indexNameLower = strtolower($indexName);
                    if (!isset($existing_indexes[$indexNameLower])) {
                        $alter_query = "ALTER TABLE `$tableName` ADD $indexDefString";
                        if ($is_dry_run) {
                            output_message('warning', "ไม่พบดัชนี (Index): $tableName.$indexName", "คำสั่ง SQL: $alter_query", $is_dry_run);
                        } else {
                            $conn->query($alter_query);
                            output_message('success', "เพิ่มดัชนีสำเร็จ: $tableName.$indexName", "เสร็จสิ้น", $is_dry_run);
                        }
                    }
                }
            }
        }
    }
    
    // Step 3: Collations & constraints
    try {
        $conn->query("ALTER TABLE `companies` DROP INDEX `name`");
    } catch (Exception $e) {}
    
    // Step 4: Seeding
    try {
        $check_admin = $conn->query("SELECT id FROM users WHERE username = 'admin' LIMIT 1");
        if ($check_admin->num_rows === 0 && !$is_dry_run) {
            $conn->query("INSERT INTO `users` (`id`, `username`, `fullname`, `email`, `password`, `role`, `phone`, `student_level`) VALUES (1, 'admin', 'ผู้ดูแลระบบสูงสุด', 'admin@dve-system.local', '$2y$10$qR.7d1vH80vO04zP2H6jX.4cK2yv.j19JzR5yVdK3RjC6oG6r1vG6', 'admin', '0800000000', 'ปวช.')");
            output_message('success', "สร้างบัญชีผู้ดูแลระบบ (Admin) เริ่มต้น", "เสร็จสิ้น", $is_dry_run);
        }
    } catch (Exception $e) {}
    
    if (php_sapi_name() !== 'cli') {
        echo '</pre></body></html>';
    } else {
        echo "====================================================\n";
        echo "🎉 ปรับปรุงโครงสร้างฐานข้อมูลเสร็จสมบูรณ์เรียบร้อยแล้ว!\n";
        echo "====================================================\n";
    }
    exit;
}

// Retrieve backup file histories for display under tab
$backup_dir = __DIR__ . '/backups';
if (!file_exists($backup_dir)) {
    mkdir($backup_dir, 0777, true);
}
$backup_files = glob($backup_dir . '/*.{sql,zip}', GLOB_BRACE);
usort($backup_files, function($a, $b) {
    return filemtime($b) - filemtime($a);
});
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบปรับปรุงโครงสร้างฐานข้อมูลอัจฉริยะ | DVE System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Sarabun:wght@300;400;500;600;700&family=Fira+Code:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #6366f1;
            --primary-glow: rgba(99, 102, 241, 0.4);
            --primary-dark: #4f46e5;
            --success: #10b981;
            --success-glow: rgba(16, 185, 129, 0.4);
            --warning: #f59e0b;
            --danger: #ef4444;
            --glass-bg: rgba(255, 255, 255, 0.75);
            --glass-border: rgba(255, 255, 255, 0.5);
            --glass-shadow: rgba(31, 38, 135, 0.08);
            --text-dark: #0f172a;
            --text-muted: #475569;
            --slate-100: #f1f5f9;
            --slate-200: #e2e8f0;
            --slate-800: #1e293b;
            --slate-900: #0f172a;
        }

        body {
            font-family: "Inter", "Sarabun", sans-serif;
            background: linear-gradient(135deg, #eef2f6 0%, #dbeafe 100%);
            background-attachment: fixed;
            color: var(--text-dark);
            margin: 0;
            padding: 0;
            line-height: 1.6;
        }

        .bg-pattern {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background-image: radial-gradient(rgba(99, 102, 241, 0.07) 1.5px, transparent 1.5px);
            background-size: 24px 24px;
            pointer-events: none;
            z-index: -1;
        }

        .container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }

        /* Glassmorphism Header */
        .header-panel {
            background: var(--glass-bg);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 8px 32px 0 var(--glass-shadow);
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 20px;
        }

        .header-title h1 {
            margin: 0;
            font-size: 26px;
            font-weight: 700;
            color: var(--slate-900);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .header-title p {
            margin: 6px 0 0 0;
            color: var(--text-muted);
            font-size: 14px;
        }

        /* Layout Grid */
        .main-grid {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 30px;
            align-items: start;
        }

        @media (max-width: 992px) {
            .main-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Navigation Sidebar */
        .sidebar {
            background: var(--glass-bg);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 20px;
            box-shadow: 0 8px 32px 0 var(--glass-shadow);
        }

        .nav-list {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .nav-item button {
            width: 100%;
            text-align: left;
            padding: 12px 16px;
            border-radius: 12px;
            border: none;
            background: transparent;
            color: var(--text-muted);
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .nav-item button:hover {
            background: rgba(99, 102, 241, 0.08);
            color: var(--primary);
            padding-left: 20px;
        }

        .nav-item.active button {
            background: var(--primary);
            color: white;
            box-shadow: 0 4px 14px 0 var(--primary-glow);
        }

        /* Content Card */
        .content-area {
            background: var(--glass-bg);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 8px 32px 0 var(--glass-shadow);
            min-height: 480px;
        }

        .tab-panel {
            display: none;
            animation: fadeIn 0.3s ease-in-out;
        }

        .tab-panel.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Controls and Cards styling */
        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--slate-900);
            margin-top: 0;
            margin-bottom: 20px;
            border-bottom: 1px solid var(--slate-200);
            padding-bottom: 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        /* Grid Controls */
        .control-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .option-card {
            background: rgba(255, 255, 255, 0.4);
            border: 1px solid var(--slate-200);
            border-radius: 14px;
            padding: 16px 20px;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .option-card:hover {
            border-color: var(--primary-light);
            background: rgba(255, 255, 255, 0.8);
        }

        .option-info {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .option-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--slate-900);
        }

        .option-desc {
            font-size: 12px;
            color: var(--text-muted);
        }

        /* Beautiful Switch Button */
        .switch {
            position: relative;
            display: inline-block;
            width: 46px;
            height: 24px;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0; left: 0; right: 0; bottom: 0;
            background-color: var(--slate-200);
            transition: .3s;
            border-radius: 34px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .3s;
            border-radius: 50%;
        }

        input:checked + .slider {
            background-color: var(--primary);
        }

        input:focus + .slider {
            box-shadow: 0 0 1px var(--primary);
        }

        input:checked + .slider:before {
            transform: translateX(22px);
        }

        /* Stat Panel */
        .stats-summary {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }

        @media (max-width: 768px) {
            .stats-summary {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        .stat-box {
            background: rgba(255, 255, 255, 0.5);
            border: 1px solid var(--slate-200);
            border-radius: 12px;
            padding: 16px;
            text-align: center;
            transition: all 0.2s ease;
        }

        .stat-box:hover {
            transform: translateY(-2px);
            border-color: var(--primary);
            box-shadow: 0 4px 20px 0 rgba(99, 102, 241, 0.05);
        }

        .stat-num {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary);
        }

        .stat-label {
            font-size: 11px;
            font-weight: 500;
            color: var(--text-muted);
            margin-top: 4px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        /* JetBrains Dark Console */
        .terminal-container {
            background: #1e1e2e;
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
            overflow: hidden;
            border: 1px solid #313244;
            margin-top: 20px;
        }

        .terminal-header {
            background: #11111b;
            padding: 12px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #313244;
        }

        .terminal-dots {
            display: flex;
            gap: 8px;
        }

        .terminal-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }

        .dot-red { background-color: #f38ba8; }
        .dot-yellow { background-color: #f9e2af; }
        .dot-green { background-color: #a6e3a1; }

        .terminal-title {
            color: #cdd6f4;
            font-family: "Fira Code", monospace;
            font-size: 12px;
            font-weight: 500;
        }

        .terminal-body {
            height: 320px;
            overflow-y: auto;
            padding: 20px;
            font-family: "Fira Code", "Sarabun", monospace;
            font-size: 13px;
            color: #cdd6f4;
            line-height: 1.6;
        }

        /* Scrollbar customizing */
        .terminal-body::-webkit-scrollbar, .tab-panel::-webkit-scrollbar {
            width: 8px;
        }
        .terminal-body::-webkit-scrollbar-track {
            background: #11111b;
        }
        .terminal-body::-webkit-scrollbar-thumb {
            background: #313244;
            border-radius: 4px;
        }

        .term-log {
            margin-bottom: 8px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            animation: slideIn 0.2s ease-out;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateX(-4px); }
            to { opacity: 1; transform: translateX(0); }
        }

        .term-success { color: #a6e3a1; }
        .term-warning { color: #f9e2af; }
        .term-danger { color: #f38ba8; }
        .term-info { color: #89b4fa; }

        /* Progress Bar */
        .progress-wrapper {
            margin: 25px 0;
            display: none;
        }

        .progress-text {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 6px;
            color: var(--slate-800);
        }

        .progress-track {
            background: var(--slate-200);
            height: 12px;
            border-radius: 100px;
            overflow: hidden;
            padding: 2px;
        }

        .progress-fill {
            background: linear-gradient(90deg, var(--success), var(--primary));
            height: 100%;
            border-radius: 100px;
            width: 0%;
            box-shadow: 0 0 12px var(--primary-glow);
            transition: width 0.4s ease;
        }

        /* Large Sync Action Button */
        .btn-sync-trigger {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            padding: 16px;
            border-radius: 14px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            font-weight: 700;
            font-size: 16px;
            border: none;
            cursor: pointer;
            box-shadow: 0 6px 20px 0 var(--primary-glow);
            transition: all 0.3s ease;
            gap: 12px;
        }

        .btn-sync-trigger:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px 0 var(--primary-glow);
            filter: brightness(1.1);
        }

        .btn-sync-trigger:disabled {
            background: var(--slate-200);
            color: var(--text-muted);
            cursor: not-allowed;
            box-shadow: none;
            transform: none;
        }

        /* Backup History list */
        .backup-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-top: 15px;
        }

        .backup-item {
            background: rgba(255,255,255,0.4);
            border: 1px solid var(--slate-200);
            border-radius: 12px;
            padding: 12px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.2s ease;
        }

        .backup-item:hover {
            background: rgba(255,255,255,0.8);
            border-color: var(--primary);
        }

        .backup-meta {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .backup-icon {
            font-size: 24px;
            color: var(--primary);
        }

        .backup-details {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .backup-name {
            font-size: 14px;
            font-weight: 600;
            color: var(--slate-900);
        }

        .backup-sub {
            font-size: 12px;
            color: var(--text-muted);
        }

        .backup-actions {
            display: flex;
            gap: 8px;
        }

        .btn-circle {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: 1px solid var(--slate-200);
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            text-decoration: none;
            color: var(--text-dark);
            transition: all 0.2s ease;
        }

        .btn-circle:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .btn-delete:hover {
            background: var(--danger);
            border-color: var(--danger);
            color: white;
        }

        /* Custom alert notification box */
        .toast-msg {
            background: var(--success);
            color: white;
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 20px;
            animation: fadeIn 0.3s ease;
            box-shadow: 0 4px 15px 0 var(--success-glow);
        }
    </style>
</head>
<body>
    <div class="bg-pattern"></div>
    <div class="container">
        
        <!-- Header Panel -->
        <div class="header-panel">
            <div class="header-title">
                <h1>🔧 ระบบซิงโครไนซ์ฐานข้อมูล <span style="font-size: 14px; background: rgba(99,102,241,0.1); color: var(--primary); padding: 4px 10px; border-radius: 30px; font-weight: 600; border: 1px solid rgba(99,102,241,0.2);">เครื่องมืออัจฉริยะ</span></h1>
                <p>ตรรกะการซิงค์ข้อมูลองค์กร การสร้างดัชนีอัตโนมัติ และการสำรองข้อมูลบีบอัด ZIP แบบเรียลไทม์</p>
            </div>
            <div>
                <a href="../index.php" style="background: white; border: 1px solid var(--slate-200); color: var(--text-dark); padding: 10px 20px; border-radius: 30px; font-weight: 600; text-decoration: none; font-size: 14px; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.03); transition: all 0.2s;">
                    🏠 แดชบอร์ดหลัก
                </a>
            </div>
        </div>

        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'backup_deleted'): ?>
            <div class="toast-msg">🗑️ ลบไฟล์สำรองฐานข้อมูลเรียบร้อยแล้ว</div>
        <?php endif; ?>

        <!-- Main Dashboard Navigation Grid -->
        <div class="main-grid">
            
            <!-- Tab Links Sidebar -->
            <div class="sidebar">
                <ul class="nav-list">
                    <li class="nav-item active" data-tab="dashboard">
                        <button><span style="font-size:18px;">🔄</span> แผงควบคุมหลัก</button>
                    </li>
                    <li class="nav-item" data-tab="ai-health">
                        <button><span style="font-size:18px;">🧠</span> สุขภาพ DB (AI Health Score)</button>
                    </li>
                    <li class="nav-item" data-tab="visual-diff">
                        <button><span style="font-size:18px;">📋</span> Visual Schema Diff</button>
                    </li>
                    <li class="nav-item" data-tab="backup">
                        <button><span style="font-size:18px;">🛡️</span> จัดการไฟล์สำรอง</button>
                    </li>
                    <li class="nav-item" data-tab="diagnostics">
                        <button><span style="font-size:18px;">🔍</span> ตรวจสอบสถานะระบบ</button>
                    </li>
                </ul>
            </div>

            <!-- Content Area Cards -->
            <div class="content-area">
                
                <!-- Tab Panel 1: Main Control Panel -->
                <div class="tab-panel active" id="tab-dashboard">
                    <div class="section-title">
                        <span>🚀 ศูนย์ควบคุมการซิงโครไนซ์ฐานข้อมูล (Schema)</span>
                        <span id="badge-mode" style="font-size: 11px; background: var(--success-glow); color: var(--success); padding: 4px 12px; border-radius: 99px; font-weight: bold; border: 1px solid var(--success); text-transform: uppercase;">โหมดใช้งานจริง (Live Mode)</span>
                    </div>

                    <!-- Sync Action Variables Controls -->
                    <div class="control-grid">
                        <div class="option-card">
                            <div class="option-info">
                                <span class="option-title">ทดลองรันเท่านั้น (Dry Run)</span>
                                <span class="option-desc">ตรวจสอบความคลาดเคลื่อนของโครงสร้างฐานข้อมูลโดยไม่บันทึกจริง</span>
                            </div>
                            <label class="switch">
                                <input type="checkbox" id="chk-dry-run">
                                <span class="slider"></span>
                            </label>
                        </div>
                        <div class="option-card">
                            <div class="option-info">
                                <span class="option-title">สำรองข้อมูลเพื่อความปลอดภัย</span>
                                <span class="option-desc">ส่งออกโครงสร้างและข้อมูลทั้งหมดเป็นไฟล์สำรองก่อนเริ่มการซิงค์</span>
                            </div>
                            <label class="switch">
                                <input type="checkbox" id="chk-backup" checked>
                                <span class="slider"></span>
                            </label>
                        </div>
                        <div class="option-card">
                            <div class="option-info">
                                <span class="option-title">ซิงค์จำนวนนักศึกษา</span>
                                <span class="option-desc">คำนวณและปรับปรุงจำนวนนักศึกษารวมในแต่ละห้องเรียนใหม่</span>
                            </div>
                            <label class="switch">
                                <input type="checkbox" id="chk-sync-counts" checked>
                                <span class="slider"></span>
                            </label>
                        </div>
                        <div class="option-card">
                            <div class="option-info">
                                <span class="option-title">ล้างข้อมูลรหัสอักษรที่เสียหาย</span>
                                <span class="option-desc">แก้ไขตัวอักษรภาษาไทยที่แสดงผลผิดพลาดหรืออ่านไม่ออก</span>
                            </div>
                            <label class="switch">
                                <input type="checkbox" id="chk-clean-thai">
                                <span class="slider"></span>
                            </label>
                        </div>
                    </div>

                    <!-- Execution Stats panel -->
                    <div class="stats-summary" id="stats-container">
                        <div class="stat-box">
                            <div class="stat-num" id="stat-checked">0</div>
                            <div class="stat-label">ตารางที่ตรวจสอบ</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-num" id="stat-created">0</div>
                            <div class="stat-label">ตารางที่สร้างใหม่</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-num" id="stat-columns">0</div>
                            <div class="stat-label">คอลัมน์/ดัชนีที่ซิงค์</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-num" id="stat-errors" style="color:var(--success);">0</div>
                            <div class="stat-label">ข้อผิดพลาด</div>
                        </div>
                    </div>

                    <!-- Progress Bar Component -->
                    <div class="progress-wrapper" id="progress-wrapper">
                        <div class="progress-text">
                            <span id="progress-status">กำลังเตรียมการตั้งค่า...</span>
                            <span id="progress-percent">0%</span>
                        </div>
                        <div class="progress-track">
                            <div class="progress-fill" id="progress-fill"></div>
                        </div>
                    </div>

                    <!-- Main Action buttons -->
                    <button class="btn-sync-trigger" id="btn-sync-start">
                        <span>⚡</span> เริ่มกระบวนการซิงโครไนซ์ฐานข้อมูลอัตโนมัติ
                    </button>

                    <!-- Realtime Obsidian Terminal -->
                    <div class="terminal-container">
                        <div class="terminal-header">
                            <div class="terminal-dots">
                                <span class="terminal-dot dot-red"></span>
                                <span class="terminal-dot dot-yellow"></span>
                                <span class="terminal-dot dot-green"></span>
                            </div>
                            <div class="terminal-title">tvet-dve-system: db_migrations.log</div>
                            <div style="font-size: 11px; font-family: monospace; color:#6272a4;">PHP 8.3+ Active</div>
                        </div>
                        <div class="terminal-body" id="terminal-body">
                            <div class="term-log term-info"><span>$</span> เปิดใช้เชลล์ระบบแล้ว กรุณาปรับแต่งตัวเลือกด้านบน และกดปุ่ม "เริ่มกระบวนการซิงโครไนซ์ฐานข้อมูลอัตโนมัติ" เพื่อดำเนินการโยกย้ายข้อมูล (Migration)</div>
                        </div>
                    </div>

                </div>

                <!-- Tab Panel: AI Health Score -->
                <div class="tab-panel" id="tab-ai-health">
                    <div class="section-title">
                        <span>🧠 ตรวจวัดและประเมินดัชนีสุขภาพฐานข้อมูล (AI Health Score)</span>
                        <button class="btn-sync-trigger" id="btn-run-health" style="width: auto; padding: 8px 20px; font-size: 13px;">
                            <span style="font-size:16px;">⚡</span> เริ่มตรวจวัดสุขภาพ DB
                        </button>
                    </div>

                    <div style="background: linear-gradient(135deg, #1e1e2e 0%, #2b2b40 100%); border-radius: 20px; padding: 25px; color: white; margin-bottom: 25px; box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
                        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 20px;">
                            <div>
                                <span style="font-size: 12px; text-transform: uppercase; letter-spacing: 1px; color: #a6adc8;">Database Health Rating</span>
                                <h2 id="health-score-val" style="font-size: 48px; margin: 5px 0 0 0; font-weight: 800; color: #a6e3a1;">-- <span style="font-size: 20px; font-weight: 400; color: #cdd6f4;">/ 100</span></h2>
                                <span id="health-status-badge" style="display: inline-block; margin-top: 8px; padding: 4px 14px; border-radius: 30px; font-size: 12px; font-weight: 700; background: rgba(166,227,161,0.2); color: #a6e3a1; border: 1px solid #a6e3a1;">รอการประเมิน</span>
                            </div>
                            <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                                <button onclick="runOptimizeTables()" style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: white; padding: 12px 18px; border-radius: 12px; font-weight: 600; cursor: pointer; font-size: 13px; display: flex; align-items: center; gap: 8px;">
                                    <span>⚡</span> Optimize & Defrag
                                </button>
                                <button onclick="runCleanDbOrphans()" style="background: rgba(239,68,68,0.2); border: 1px solid rgba(239,68,68,0.4); color: #f87171; padding: 12px 18px; border-radius: 12px; font-weight: 600; cursor: pointer; font-size: 13px; display: flex; align-items: center; gap: 8px;">
                                    <span>🧹</span> ล้างข้อมูลลอย (Orphan Records)
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Health Sub-Scores Breakdown -->
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 25px;" id="health-breakdown-grid">
                        <div class="stat-box">
                            <div class="stat-num" id="hs-schema">-- / 25</div>
                            <div class="stat-label">Schema Alignment</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-num" id="hs-charset">-- / 15</div>
                            <div class="stat-label">Unicode Encoding</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-num" id="hs-orphans">-- / 20</div>
                            <div class="stat-label">Data Integrity</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-num" id="hs-indexes">-- / 20</div>
                            <div class="stat-label">Index Coverage</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-num" id="hs-defrag">-- / 10</div>
                            <div class="stat-label">Storage Efficiency</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-num" id="hs-backup">-- / 10</div>
                            <div class="stat-label">Backup Freshness</div>
                        </div>
                    </div>
                </div>

                <!-- Tab Panel: Visual Schema Diff -->
                <div class="tab-panel" id="tab-visual-diff">
                    <div class="section-title">
                        <span>📋 ตารางเปรียบเทียบโครงสร้างเป้าหมาย VS ฐานข้อมูลจริง (Visual Diff)</span>
                        <button class="btn-circle" style="width:auto; border-radius:30px; padding:0 15px; font-size:12px; font-weight:bold;" onclick="fetchVisualDiff()">🔄 โหลด Visual Diff ใหม่</button>
                    </div>
                    <p style="font-size:14px; color:var(--text-muted); margin-top:0; margin-bottom:20px;">เปรียบเทียบโครงสร้างตารางและคอลัมน์ในเป้าหมาย (Standard Schema map) กับฐานข้อมูลจริงในขณะนี้</p>
                    <div id="visual-diff-container">
                        <div style="text-align:center; padding:40px; color:var(--text-muted);">คลิก "โหลด Visual Diff ใหม่" เพื่อตรวจสอบความสอดคล้องของตาราง</div>
                    </div>
                </div>

                <!-- Tab Panel 2: Backup Manager -->
                <div class="tab-panel" id="tab-backup">
                    <div class="section-title">
                        <span>🛡️ จัดการไฟล์สำรองฐานข้อมูลเพื่อความปลอดภัย</span>
                        <span style="font-size:12px; color:var(--text-muted); font-weight:normal;">จำนวนไฟล์ทั้งหมด: <?php echo count($backup_files); ?> ไฟล์</span>
                    </div>
                    <p style="font-size:14px; color:var(--text-muted); margin-top:0; margin-bottom:20px;">ไฟล์สำรองข้อมูล (Safety database dump) จะถูกสร้างขึ้นโดยอัตโนมัติก่อนที่จะมีการเปลี่ยนแปลงโครงสร้างใดๆ โดยไฟล์ทั้งหมดจะถูกบันทึกไว้ในระบบเครื่องแม่ข่ายโฟลเดอร์ของโครงการ (`db/backups/`) และเปิดใช้งานการบีบอัดไฟล์แบบ ZIP โดยอัตโนมัติเพื่อประหยัดพื้นที่จัดเก็บข้อมูล</p>
                    
                    <div class="backup-list">
                        <?php if (empty($backup_files)): ?>
                            <div style="text-align:center; padding:40px; color:var(--text-muted); border:1px dashed var(--slate-200); border-radius:12px;">
                                <div style="font-size:32px; margin-bottom:8px;">📦</div>
                                ไม่พบไฟล์สำรองข้อมูลในระบบ กรุณาเริ่มกระบวนการซิงค์ข้อมูลโดยเปิดตัวเลือกการสำรองข้อมูลเพื่อสร้างไฟล์สำรองแรก
                            </div>
                        <?php else: ?>
                            <?php foreach ($backup_files as $f): 
                                $name = basename($f);
                                $ext = pathinfo($f, PATHINFO_EXTENSION);
                                $time = filemtime($f);
                                $size = filesize($f);
                            ?>
                                <div class="backup-item">
                                    <div class="backup-meta">
                                        <span class="backup-icon"><?php echo $ext === 'zip' ? '🤐' : '🗄️'; ?></span>
                                        <div class="backup-details">
                                            <span class="backup-name"><?php echo htmlspecialchars($name); ?></span>
                                            <span class="backup-sub">สร้างเมื่อ: <?php echo date('Y-m-d H:i:s', $time); ?> | ขนาดไฟล์: <?php echo format_size($size); ?></span>
                                        </div>
                                    </div>
                                    <div class="backup-actions">
                                        <a href="sync_db_structure.php?download_backup=<?php echo urlencode($name); ?>" class="btn-circle" title="ดาวน์โหลดไฟล์สำรองข้อมูล">⬇️</a>
                                        <a href="sync_db_structure.php?delete_backup=<?php echo urlencode($name); ?>" class="btn-circle btn-delete" title="ลบไฟล์สำรองข้อมูล" onclick="return confirm('คุณแน่ใจหรือไม่ว่าต้องการลบไฟล์สำรองข้อมูลนี้อย่างถาวร?')">🗑️</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Tab Panel 3: Health Diagnostics -->
                <div class="tab-panel" id="tab-diagnostics">
                    <div class="section-title">
                        <span>🔍 วิเคราะห์ข้อมูลที่ขาดความเชื่อมโยงและสุขภาพโครงสร้าง</span>
                        <button class="btn-circle" style="width:auto; border-radius:30px; padding:0 15px; font-size:12px; font-weight:bold;" id="btn-refresh-diagnostics">🔄 เริ่มวิเคราะห์สถานะระบบ</button>
                    </div>
                    <p style="font-size:14px; color:var(--text-muted); margin-top:0; margin-bottom:20px;">สแกนหาความสัมพันธ์โครงสร้างข้อมูลที่เสียหายในระบบ (Orphaned relational data models) เช่น นักศึกษาที่เชื่อมโยงกับห้องเรียนที่ไม่มีอยู่จริง หรือโครงสร้างแผนกที่อ้างอิงถึงชั้นเรียนที่ถูกลบออกจากระบบไปแล้ว</p>

                    <div style="background:rgba(255,255,255,0.4); border:1px solid var(--slate-200); border-radius:14px; padding:20px; margin-bottom:20px;">
                        <h4 style="margin-top:0; margin-bottom:10px; font-size:15px; color:var(--slate-800);">🔗 ความถูกต้องของการจับคู่ห้องเรียนของนักศึกษา (Student Classroom Associations)</h4>
                        <div id="diagnostics-student-box" style="font-size:14px; color:var(--text-muted);">
                            คลิก "เริ่มวิเคราะห์สถานะระบบ" หรือเริ่มต้นการซิงโครไนซ์ฐานข้อมูลเพื่อสแกนความสัมพันธ์ของห้องเรียนและนักศึกษา
                        </div>
                    </div>

                    <div style="background:rgba(255,255,255,0.4); border:1px solid var(--slate-200); border-radius:14px; padding:20px;">
                        <h4 style="margin-top:0; margin-bottom:10px; font-size:15px; color:var(--slate-800);">🏬 ความถูกต้องของการเชื่อมโยงสาขากับสถานประกอบการ (Company Branch Association Health)</h4>
                        <div id="diagnostics-branch-box" style="font-size:14px; color:var(--text-muted);">
                            คลิก "เริ่มวิเคราะห์สถานะระบบ" หรือเริ่มต้นการซิงโครไนซ์ฐานข้อมูลเพื่อสแกนความสัมพันธ์ของการเชื่อมโยงสาขาห้องเรียน
                        </div>
                    </div>
                </div>

            </div>
        </div>

    </div>

    <!-- Frontend Interactive Dashboard JS Engine -->
    <script>
        // Forcefully unregister any active service worker to prevent fetch interception on dynamic endpoints
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.getRegistrations().then(function(registrations) {
                for (let registration of registrations) {
                    registration.unregister().then(function(success) {
                        if (success) {
                            console.log('[SW] Unregistered active service worker successfully.');
                        }
                    });
                }
            }).catch(err => console.log('[SW] Error unregistering service worker:', err));
        }

        document.addEventListener('DOMContentLoaded', () => {
            // Tab Switcher
            const tabLinks = document.querySelectorAll('.nav-item');
            const tabPanels = document.querySelectorAll('.tab-panel');

            tabLinks.forEach(link => {
                link.addEventListener('click', () => {
                    tabLinks.forEach(l => l.classList.remove('active'));
                    tabPanels.forEach(p => p.classList.remove('active'));

                    link.classList.add('active');
                    const tabId = link.getAttribute('data-tab');
                    const targetPanel = document.getElementById(`tab-${tabId}`);
                    if (targetPanel) targetPanel.classList.add('active');

                    if (tabId === 'ai-health') {
                        fetchHealthCheck();
                    } else if (tabId === 'visual-diff') {
                        fetchVisualDiff();
                    }
                });
            });

            // Live / Dry Run Badge Indicator
            const chkDryRun = document.getElementById('chk-dry-run');
            const badgeMode = document.getElementById('badge-mode');

            chkDryRun.addEventListener('change', () => {
                if (chkDryRun.checked) {
                    badgeMode.textContent = 'โหมดทดลองรัน (Dry Run)';
                    badgeMode.style.color = 'var(--warning)';
                    badgeMode.style.borderColor = 'var(--warning)';
                    badgeMode.style.background = 'rgba(245, 158, 11, 0.15)';
                } else {
                    badgeMode.textContent = 'โหมดใช้งานจริง (Live Mode)';
                    badgeMode.style.color = 'var(--success)';
                    badgeMode.style.borderColor = 'var(--success)';
                    badgeMode.style.background = 'var(--success-glow)';
                }
            });

            // Sync Process AJAX Coordinator
            const btnStart = document.getElementById('btn-sync-start');
            const term = document.getElementById('terminal-body');
            const progressWrap = document.getElementById('progress-wrapper');
            const progressFill = document.getElementById('progress-fill');
            const progressPercent = document.getElementById('progress-percent');
            const progressStatus = document.getElementById('progress-status');

            // Stats counters DOMs
            const domChecked = document.getElementById('stat-checked');
            const domCreated = document.getElementById('stat-created');
            const domColumns = document.getElementById('stat-columns');
            const domErrors = document.getElementById('stat-errors');

            const writeLog = (type, title, desc) => {
                let colorClass = 'term-info';
                let icon = 'ℹ️';
                if (type === 'success') { colorClass = 'term-success'; icon = '✅'; }
                if (type === 'warning') { colorClass = 'term-warning'; icon = '⚠️'; }
                if (type === 'danger') { colorClass = 'term-danger'; icon = '❌'; }

                const el = document.createElement('div');
                el.className = `term-log ${colorClass}`;
                el.innerHTML = `<span>${icon}</span> <div><strong>${title}</strong>: ${desc}</div>`;
                term.appendChild(el);
                term.scrollTop = term.scrollHeight;
            };

            btnStart.addEventListener('click', async () => {
                // Confirm dialog for safety
                if (!chkDryRun.checked && !confirm("⚠️ คำเตือน: คุณกำลังจะปรับเปลี่ยนโครงสร้างตารางข้อมูลในระบบที่ใช้งานจริง คุณต้องการดำเนินการต่อหรือไม่?")) {
                    return;
                }

                // Reset dashboard environment
                term.innerHTML = '';
                writeLog('info', 'เริ่มกระบวนการปรับปรุงข้อมูล (Migration)', 'กำลังตรวจสอบการตั้งค่าและวิเคราะห์ความพร้อมของเซิร์ฟเวอร์...');
                progressWrap.style.display = 'block';
                btnStart.disabled = true;

                // Disable options toggles during sync
                const toggles = document.querySelectorAll('.slider');
                toggles.forEach(t => t.style.pointerEvents = 'none');

                const dryRun = chkDryRun.checked ? 1 : 0;
                const backup = document.getElementById('chk-backup').checked ? 1 : 0;
                const syncCounts = document.getElementById('chk-sync-counts').checked ? 1 : 0;
                const cleanThai = document.getElementById('chk-clean-thai').checked ? 1 : 0;

                try {
                    let step = 0;
                    const totalSteps = 5;

                    // STEP 1: SAFETY BACKUP
                    if (backup && !dryRun) {
                        progressStatus.textContent = 'กำลังสำรองข้อมูลความปลอดภัยฐานข้อมูล (ZIP/SQL)...';
                        progressPercent.textContent = '20%';
                        progressFill.style.width = '20%';

                        writeLog('info', 'ขั้นตอนการสำรองข้อมูล', 'กำลังคัดลอกและสร้างไฟล์สำรองโครงสร้างรวมถึงแถวข้อมูลตารางทั้งหมด...');
                        const res = await fetch(`sync_db_structure.php?ajax=1&action=backup&dry_run=${dryRun}`);
                        const data = await res.json();
                        
                        if (data.success) {
                            data.logs.forEach(log => writeLog(log.type, log.title, log.desc));
                        } else {
                            throw new Error(data.message || 'การเขียนและจัดทำไฟล์สำรองฐานข้อมูลล้มเหลว');
                        }
                    } else {
                        writeLog('warning', 'ข้ามขั้นตอนการสำรองข้อมูล', 'ข้ามระบบสำรองข้อมูล (เนื่องจากการสั่งงานแบบ Dry-run หรือผู้ใช้ระบุให้ข้าม)');
                    }

                    // STEP 2: SCHEMA SYNC (Tables, Columns, Indexes)
                    progressStatus.textContent = 'กำลังซิงโครไนซ์ตาราง คอลัมน์ และคีย์ดัชนี (Indexes)...';
                    progressPercent.textContent = '40%';
                    progressFill.style.width = '40%';

                    writeLog('info', 'ขั้นตอนการซิงค์ Schema', 'กำลังประมวลผลตารางที่หายไปและจัดลำดับโครงสร้างคอลัมน์...');
                    const schemaRes = await fetch(`sync_db_structure.php?ajax=1&action=schema_sync&dry_run=${dryRun}`);
                    const schemaData = await schemaRes.json();
                    
                    if (schemaData.success || schemaData.stats) {
                        schemaData.logs.forEach(log => writeLog(log.type, log.title, log.desc));
                        
                        // Update Stat Counters
                        domChecked.textContent = schemaData.stats.checked;
                        domCreated.textContent = schemaData.stats.created;
                        domColumns.textContent = schemaData.stats.columns_added + schemaData.stats.columns_modified + schemaData.stats.indexes_added;
                        domErrors.textContent = schemaData.stats.errors;
                        
                        if (schemaData.stats.errors > 0) {
                            domErrors.style.color = 'var(--danger)';
                        } else {
                            domErrors.style.color = 'var(--success)';
                        }
                    }

                    // STEP 3: OPTIMIZATIONS (Collations, Legacy Constraints)
                    progressStatus.textContent = 'กำลังปรับปรุงประสิทธิภาพข้อจำกัดตารางและปรับปรุง Collation...';
                    progressPercent.textContent = '65%';
                    progressFill.style.width = '65%';

                    writeLog('info', 'ขั้นตอนการจัดระเบียบ Charset', 'กำลังแปลงชุดภาษาของโครงสร้างตารางข้อมูลทั้งหมดให้เป็น utf8mb4_unicode_ci...');
                    const optRes = await fetch(`sync_db_structure.php?ajax=1&action=optimization_sync&dry_run=${dryRun}`);
                    const optData = await optRes.json();
                    if (optData.logs) {
                        optData.logs.forEach(log => writeLog(log.type, log.title, log.desc));
                    }

                    // STEP 4: SEED INITIAL VALUES
                    progressStatus.textContent = 'กำลังนำเข้าข้อมูลสถานบันพื้นฐานและสร้างโปรไฟล์ Super Admin เริ่มต้น...';
                    progressPercent.textContent = '80%';
                    progressFill.style.width = '80%';

                    writeLog('info', 'ขั้นตอนการนำเข้าข้อมูลเริ่มต้น (Seeding)', 'กำลังบันทึกบัญชีเข้าใช้งานระดับสูงของสถาบันและตั้งค่าตัวแปรระบบ...');
                    const seedRes = await fetch(`sync_db_structure.php?ajax=1&action=seeding_sync&dry_run=${dryRun}`);
                    const seedData = await seedRes.json();
                    if (seedData.logs) {
                        seedData.logs.forEach(log => writeLog(log.type, log.title, log.desc));
                    }

                    // STEP 5: POST SYNC HEALTH & INTEGRITY
                    progressStatus.textContent = 'กำลังตรวจสอบสุขภาพความสัมพันธ์ของโมเดลข้อมูลและคำนวณจำนวนห้องเรียน...';
                    progressPercent.textContent = '100%';
                    progressFill.style.width = '100%';

                    writeLog('info', 'ขั้นตอนหลังการซิงค์ข้อมูล (Post Sync)', 'กำลังประมวลผลการนับจำนวนนักศึกษาและล้างข้อมูลอักษรไทยที่เสียหาย...');
                    const postRes = await fetch(`sync_db_structure.php?ajax=1&action=post_sync&dry_run=${dryRun}&sync_counts=${syncCounts}&repair_corrupted=${cleanThai}`);
                    const postData = await postRes.json();
                    if (postData.logs) {
                        postData.logs.forEach(log => writeLog(log.type, log.title, log.desc));
                    }

                    // Render Orphan Diagnostics Results
                    renderDiagnostics(postData.orphans);

                    writeLog('success', 'ดำเนินการสำเร็จ', 'เสร็จสิ้นขั้นตอนการซิงโครไนซ์และจัดลำดับโครงสร้างฐานข้อมูลเรียบร้อยแล้ว');
                    progressStatus.textContent = 'กระบวนการโยกย้ายและตรวจสอบโครงสร้างเสร็จสมบูรณ์เรียบร้อย!';

                } catch (err) {
                    writeLog('danger', 'เกิดข้อผิดพลาดในการทำงาน', err.message || 'เกิดข้อผิดพลาดที่ไม่รู้จักจากเซิร์ฟเวอร์');
                    progressStatus.textContent = 'กระบวนการซิงค์ฐานข้อมูลหยุดชะงักเนื่องจากพบข้อผิดพลาด';
                } finally {
                    btnStart.disabled = false;
                    toggles.forEach(t => t.style.pointerEvents = 'auto');
                }
            });

            // Diagnostic refreshing button
            const btnDiagnostics = document.getElementById('btn-refresh-diagnostics');
            btnDiagnostics.addEventListener('click', async () => {
                btnDiagnostics.disabled = true;
                writeLog('info', 'วิเคราะห์สุขภาพระบบ', 'กำลังประมวลผลและสแกนโครงสร้างความเชื่อมโยงข้อมูลในตาราง...');
                
                try {
                    const res = await fetch(`sync_db_structure.php?ajax=1&action=post_sync&dry_run=1`);
                    const data = await res.json();
                    renderDiagnostics(data.orphans);
                    writeLog('success', 'วิเคราะห์สุขภาพระบบ', 'เสร็จสิ้นการสแกนความผิดปกติ ผลลัพธ์อัปเดตลงในแท็บตรวจวิเคราะห์เรียบร้อยแล้ว');
                } catch(e) {
                    writeLog('danger', 'วิเคราะห์สุขภาพระบบล้มเหลว', e.message);
                } finally {
                    btnDiagnostics.disabled = false;
                }
            });

            // Diagnostics DOM Renderer
            const renderDiagnostics = (orphans) => {
                const studBox = document.getElementById('diagnostics-student-box');
                const branchBox = document.getElementById('diagnostics-branch-box');

                if (orphans && orphans.students && orphans.students.length > 0) {
                    let html = `<ul style="list-style:none; padding:0; margin:0; display:flex; flex-direction:column; gap:8px;">`;
                    orphans.students.forEach(st => {
                        html += `<li style="background:#fee2e2; border-left:4px solid var(--danger); padding:8px 15px; border-radius:6px; color:#991b1b;">
                            • นักศึกษา <strong>${st.name}</strong> (${st.code}) เชื่อมโยงกับห้องเรียนที่ไม่มีอยู่จริง รหัสห้องเรียนขาดหาย: <strong>${st.missing_id}</strong>
                        </li>`;
                    });
                    html += '</ul>';
                    studBox.innerHTML = html;
                } else {
                    studBox.innerHTML = `<div style="background:#d1fae5; border-left:4px solid var(--success); padding:8px 15px; border-radius:6px; color:#065f46;">
                        ✅ ความเชื่อมโยงของชั้นเรียนนักศึกษาทุกคนสมบูรณ์ ไม่พบข้อผิดพลาด
                    </div>`;
                }

                if (orphans && orphans.branches && orphans.branches.length > 0) {
                    let html = `<ul style="list-style:none; padding:0; margin:0; display:flex; flex-direction:column; gap:8px;">`;
                    orphans.branches.forEach(br => {
                        html += `<li style="background:#fee2e2; border-left:4px solid var(--danger); padding:8px 15px; border-radius:6px; color:#991b1b;">
                            • สาขา <strong>${br.branch}</strong> เชื่อมโยงกับห้องเรียนสาขาที่ขาดหาย รหัสห้องเรียนขาดหาย: <strong>${br.missing_id}</strong>
                        </li>`;
                    });
                    html += '</ul>';
                    branchBox.innerHTML = html;
                } else {
                    branchBox.innerHTML = `<div style="background:#d1fae5; border-left:4px solid var(--success); padding:8px 15px; border-radius:6px; color:#065f46;">
                        ✅ ความเชื่อมโยงของชั้นเรียนระดับสาขาสถานประกอบการถูกต้องครบถ้วน
                    </div>`;
                }
            };

            // 🧠 AI Health Check & Visual Diff Functions
            function fetchHealthCheck() {
                const scoreVal = document.getElementById('health-score-val');
                const badge = document.getElementById('health-status-badge');
                if (!scoreVal || !badge) return;
                scoreVal.innerHTML = '... <span style="font-size:20px; font-weight:400; color:#cdd6f4;">/ 100</span>';
                badge.innerHTML = 'กำลังประเมิน...';

                fetch('sync_db_structure.php?ajax=1&action=health_check')
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            scoreVal.innerHTML = `${data.total_score} <span style="font-size:20px; font-weight:400; color:#cdd6f4;">/ 100</span>`;
                            scoreVal.style.color = data.color;
                            badge.innerHTML = data.status;
                            badge.style.borderColor = data.color;
                            badge.style.color = data.color;
                            badge.style.background = data.color + '22';

                            document.getElementById('hs-schema').innerText = `${data.scores.schema} / 25`;
                            document.getElementById('hs-charset').innerText = `${data.scores.charset} / 15`;
                            document.getElementById('hs-orphans').innerText = `${data.scores.orphans} / 20`;
                            document.getElementById('hs-indexes').innerText = `${data.scores.indexes} / 20`;
                            document.getElementById('hs-defrag').innerText = `${data.scores.defrag} / 10`;
                            document.getElementById('hs-backup').innerText = `${data.scores.backup} / 10`;
                        }
                    });
            }
            window.fetchHealthCheck = fetchHealthCheck;

            function fetchVisualDiff() {
                const container = document.getElementById('visual-diff-container');
                if (!container) return;
                container.innerHTML = '<div style="text-align:center; padding:30px; color:var(--text-muted);">กำลังโหลดข้อมูล Visual Schema Diff...</div>';

                fetch('sync_db_structure.php?ajax=1&action=visual_diff')
                    .then(res => res.json())
                    .then(data => {
                        if (data.success && data.diffs) {
                            let html = '<div style="display:flex; flex-direction:column; gap:15px;">';
                            for (const [tbl, info] of Object.entries(data.diffs)) {
                                const badgeColor = info.status === 'matched' ? 'var(--success)' : (info.status === 'missing' ? 'var(--danger)' : 'var(--warning)');
                                const badgeBg = info.status === 'matched' ? 'rgba(16,185,129,0.1)' : (info.status === 'missing' ? 'rgba(239,68,68,0.1)' : 'rgba(245,158,11,0.1)');
                                
                                html += `<div style="background:rgba(255,255,255,0.5); border:1px solid var(--slate-200); border-radius:14px; padding:16px;">
                                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                                        <strong style="font-size:15px; color:var(--slate-900);">🗄️ ${tbl}</strong>
                                        <span style="font-size:12px; font-weight:bold; padding:4px 12px; border-radius:20px; color:${badgeColor}; background:${badgeBg}; border:1px solid ${badgeColor};">${info.label}</span>
                                    </div>`;

                                if (Object.keys(info.columns).length > 0) {
                                    html += `<div style="font-size:12px; color:var(--text-muted); display:flex; flex-wrap:wrap; gap:8px; margin-top:8px;">`;
                                    for (const [col, colInfo] of Object.entries(info.columns)) {
                                        const cColor = colInfo.status === 'matched' ? '#059669' : '#dc2626';
                                        html += `<span style="background:white; border:1px solid var(--slate-200); padding:3px 8px; border-radius:6px; font-family:monospace; color:${cColor};">
                                            ${colInfo.status === 'matched' ? '✓' : '✗'} ${col} (${colInfo.actual})
                                        </span>`;
                                    }
                                    html += `</div>`;
                                }
                                html += `</div>`;
                            }
                            html += '</div>';
                            container.innerHTML = html;
                        }
                    });
            }
            window.fetchVisualDiff = fetchVisualDiff;

            const btnRunHealth = document.getElementById('btn-run-health');
            if (btnRunHealth) {
                btnRunHealth.addEventListener('click', fetchHealthCheck);
            }

            // ⚡ Optimize Tables Trigger
            function runOptimizeTables() {
                if (!confirm('ยืนยัน Optimize และ Defrag ทุกตารางในฐานข้อมูล?')) return;
                fetch('sync_db_structure.php?ajax=1&action=optimize_tables')
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            alert(`Optimize ตารางเรียบร้อยแล้ว! คืนพื้นที่ดิสก์รวม ${data.freed_mb} MB`);
                            fetchHealthCheck();
                        }
                    });
            }
            window.runOptimizeTables = runOptimizeTables;

            // 🧹 Clean DB Orphans Trigger
            function runCleanDbOrphans() {
                if (!confirm('ยืนยันทำความสะอาดข้อมูลหลุดลอย (Orphan Records) ใน DB?')) return;
                fetch('sync_db_structure.php?ajax=1&action=clean_db_orphans')
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            alert(`ทำความสะอาด DB สำเร็จ! ลบ/ซ่อมแซมข้อมูลหลุดลอยไป ${data.cleaned_count} รายการ`);
                            fetchHealthCheck();
                        }
                    });
            }
            window.runCleanDbOrphans = runCleanDbOrphans;
        });
    </script>
</body>
</html>
<?php
// -----------------------------------------------------------------------------
// HELPER COMPRESSION AND QUERY UTILITIES
// -----------------------------------------------------------------------------

/**
 * Checks if a column definition needs modifying based on target vs. existing structure.
 */
function column_needs_modification(string $colName, string $desiredDef, array $existing): bool {
    $desiredDef = strtolower(trim($desiredDef));
    $existingType = strtolower(trim($existing['type']));
    $existingNull = strtoupper(trim($existing['null'])); // YES or NO
    $existingDefault = $existing['default'];
    
    // Parse desired type (e.g. "varchar(255)")
    preg_match('/^([a-z]+(\([0-9,]+\)?|))/', $desiredDef, $matches);
    $desiredType = isset($matches[1]) ? trim($matches[1]) : '';
    
    // 1. Direct type comparison (if they don't match, we definitely modify)
    if (!empty($desiredType) && $desiredType !== $existingType) {
        if (strpos($desiredType, 'int') !== false && strpos($existingType, 'int') !== false) {
            $clean_des = preg_replace('/\(.*\)/', '', $desiredType);
            $clean_ext = preg_replace('/\(.*\)/', '', $existingType);
            if ($clean_des !== $clean_ext) {
                return true;
            }
        } else {
            return true;
        }
    }
    
    // 2. Nullability check
    $desiredNull = 'YES'; // default to null allowed
    if (strpos($desiredDef, 'not null') !== false) {
        $desiredNull = 'NO';
    }
    if ($desiredNull !== $existingNull) {
        return true;
    }
    
    return false;
}

/**
 * Appends diagnostic message log (Adapts seamlessly to CLI, AJAX and sequential browser contexts)
 */
function output_message(string $type, string $title, string $desc, bool $is_dry_run): void {
    global $ajax_logs;
    if (isset($_GET['ajax'])) {
        $ajax_logs[] = [
            'type' => $type,
            'title' => $title,
            'desc' => $desc
        ];
        return;
    }
    
    if (php_sapi_name() === 'cli') {
        $prefix = "[INFO]";
        if ($type === 'success') $prefix = "[SUCCESS]";
        if ($type === 'warning') $prefix = "[WARNING]";
        if ($type === 'danger')  $prefix = "[ERROR]";
        echo "$prefix $title - " . strip_tags(str_replace('<br>', ' | ', $desc)) . "\n";
        return;
    }
    
    $class = "log-info";
    $icon = "ℹ️";
    if ($type === 'success') { $class = "log-success"; $icon = "✅"; }
    if ($type === 'warning') { $class = "log-warning"; $icon = "⚠️"; }
    if ($type === 'danger')  { $class = "log-danger"; $icon = "❌"; }
    
    echo '<li class="log-item ' . $class . '">
            <span class="log-icon">' . $icon . '</span>
            <div>
                <strong style="font-size:15px; display:block; margin-bottom:2px;">' . $title . '</strong>
                <span style="opacity:0.9;">' . $desc . '</span>
            </div>
          </li>';
}

/**
 * Pure PHP Database Backup engine.
 * Generates an SQL structures & rows export file without depending on `mysqldump` executable path.
 * Compresses automatically to ZIP if ZipArchive exists.
 */
function backup_database_pure_php(mysqli $conn, string $filepath, bool &$compressed = false): string {
    $handle = fopen($filepath, 'w');
    if (!$handle) {
        throw new Exception("Unable to create backup file at path: $filepath");
    }
    
    fwrite($handle, "-- DVE System Pure PHP Automatic Backup\n");
    fwrite($handle, "-- Generated: " . date('Y-m-d H:i:s') . "\n");
    fwrite($handle, "-- Database: " . DB_NAME . "\n");
    fwrite($handle, "-- -----------------------------------------------------\n\n");
    fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n\n");
    
    // Get all tables
    $tables = [];
    $res = $conn->query("SHOW TABLES");
    while ($row = $res->fetch_row()) {
        $tables[] = $row[0];
    }
    
    foreach ($tables as $table) {
        // Structure
        $create_res = $conn->query("SHOW CREATE TABLE `$table`")->fetch_assoc();
        $create_sql = $create_res['Create Table'] ?? '';
        
        fwrite($handle, "-- -----------------------------------------------------\n");
        fwrite($handle, "-- Table structure for `$table`\n");
        fwrite($handle, "-- -----------------------------------------------------\n");
        fwrite($handle, "DROP TABLE IF EXISTS `$table`;\n");
        fwrite($handle, $create_sql . ";\n\n");
        
        // Data rows
        $rows_res = $conn->query("SELECT * FROM `$table`");
        $fields_count = $rows_res->field_count;
        
        if ($rows_res->num_rows > 0) {
            fwrite($handle, "-- Dump rows data for `$table`\n");
            while ($row = $rows_res->fetch_row()) {
                $vals = [];
                for ($i = 0; $i < $fields_count; $i++) {
                    if ($row[$i] === null) {
                        $vals[] = 'NULL';
                    } else {
                        $escaped = $conn->real_escape_string($row[$i]);
                        $vals[] = "'" . $escaped . "'";
                    }
                }
                fwrite($handle, "INSERT INTO `$table` VALUES (" . implode(',', $vals) . ");\n");
            }
            fwrite($handle, "\n");
        }
    }
    
    fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
    fclose($handle);
    
    // Compress SQL to ZIP if possible to optimize server storage
    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        $zip_file = str_replace('.sql', '.zip', $filepath);
        if ($zip->open($zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
            $zip->addFile($filepath, basename($filepath));
            $zip->close();
            unlink($filepath); // Remove raw uncompressed SQL file
            $compressed = true;
            return $zip_file;
        }
    }
    
    $compressed = false;
    return $filepath;
}

/**
 * Formats size of file into readable string bytes unit.
 */
function format_size($bytes): string {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    for ($i = 0; $bytes >= 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}
