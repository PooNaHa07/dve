-- Database Optimization Script for DVE System

ALTER TABLE notifications 
ADD INDEX IF NOT EXISTS idx_user_read (user_id, is_read),
ADD INDEX IF NOT EXISTS idx_created (created_at);

ALTER TABLE supervision_files 
ADD INDEX IF NOT EXISTS idx_status (status),
ADD INDEX IF NOT EXISTS idx_teacher_id (teacher_id),
ADD INDEX IF NOT EXISTS idx_company_id (company_id),
ADD INDEX IF NOT EXISTS idx_uploaded_at (uploaded_at);

ALTER TABLE users
ADD INDEX IF NOT EXISTS idx_role (role),
ADD INDEX IF NOT EXISTS idx_classroom (classroom_id);
