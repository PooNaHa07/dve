# DVE_DATA_FULL
Full sample project for TVET internship management (PHP + MySQL)
Includes:
- Bootstrap UI (CDN)
- Login/Register (password_hash)
- Role-based dashboard (admin/teacher/staff/student)
- CRUD examples (admin users, companies)
- Upload: plans (PDF), student daily report (images)
- DOMPDF certificate generator (composer)
- PHPMailer sample (composer)
- init_db.php to create tables and admin user (run once)

## Setup
1. Place project in your web root (e.g., `C:\AppServ\www\DVE_DATA_FULL` or `/var/www/html/DVE_DATA_FULL`)
2. Create MySQL database (e.g., `dve_data`) and update `includes/configdb.php` DB credentials.
3. Run `php init_db.php` once from browser or CLI to create tables and an admin user (admin / admin123).
4. Install composer dependencies:
   ```
   composer install
   ```
   This installs `dompdf/dompdf` and `phpmailer/phpmailer`.
5. Ensure `uploads/pdfs` and `uploads/images` are writable by web server.
6. Open `http://localhost/DVE_DATA_FULL/login.php`

## Composer
Provided `composer.json`. Run `composer install` to get vendor.

## Notes
- DOMPDF/PHPMailer require composer. If vendor not installed, certificate/email pages will show instructions.
- This is a sample scaffold; review security and production hardening before deploying.
