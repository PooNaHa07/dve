<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/configdb.php';

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'];

    $role = (isset($_POST['role']) && !empty($_POST['role'])) ? $_POST['role'] : 'student';

    if (!$username || !$fullname || !$email || !$phone || !$password) {
        $err = 'กรุณากรอกข้อมูลให้ครบ';
    } else {
        $s = $conn->prepare('SELECT id FROM users WHERE username=? OR email=? LIMIT 1');
        $s->bind_param('ss', $username, $email);
        $s->execute();
        $s->store_result();

        if ($s->num_rows > 0) {
            $err = 'ชื่อผู้ใช้หรืออีเมลนี้ถูกใช้แล้ว';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $ins = $conn->prepare('INSERT INTO users (username, fullname, email, password, role, phone) VALUES (?, ?, ?, ?, ?, ?)');
            $ins->bind_param('ssssss', $username, $fullname, $email, $hash, $role, $phone);
            try {
                if ($ins->execute()) {
                    header('Location: ' . BASE_URL . '/login.php?registered=1');
                    exit;
                }
                $err = 'เกิดข้อผิดพลาด: ' . $conn->error;
            } catch (mysqli_sql_exception $e) {
                if ($e->getCode() == 1062) {
                    $err = 'ชื่อผู้ใช้หรืออีเมลนี้ถูกใช้แล้ว กรุณาใช้อีเมลหรือชื่อผู้ใช้อื่น';
                } else {
                    $err = 'เกิดข้อผิดพลาดจากระบบ กรุณาลองใหม่ภายหลัง';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สมัครสมาชิก - DVE | PBPVC</title>
    <link rel="icon" href="<?= BASE_URL ?>/images/logo.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700;800&family=Outfit:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4f46e5;
            --primary-light: #6366f1;
            --primary-dark: #3730a3;
            --slate-50: #f8fafc;
            --slate-100: #f1f5f9;
            --slate-200: #e2e8f0;
            --slate-300: #cbd5e1;
            --slate-400: #94a3b8;
            --slate-500: #64748b;
            --slate-700: #334155;
            --slate-800: #1e293b;
            --slate-900: #0f172a;
        }

        * { box-sizing: border-box; }
        
        body {
            font-family: 'Sarabun', 'Outfit', sans-serif;
            min-height: 100vh;
            margin: 0;
            background: linear-gradient(135deg, #e0f2fe 0%, #e0e7ff 100%);
            color: var(--slate-800);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            padding: 1.5rem 1rem;
            overflow-x: hidden;
        }

        /* Elegant Back Button */
        .back-home {
            position: absolute;
            top: 1.5rem;
            left: 1.5rem;
            z-index: 10;
            background: #ffffff;
            padding: 0.5rem 1.15rem;
            border-radius: 0.75rem;
            color: var(--slate-700);
            text-decoration: none;
            font-weight: 700;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.04);
            border: 1px solid var(--slate-200);
            transition: all 0.25s ease;
        }

        .back-home i {
            transition: transform 0.2s ease;
        }

        .back-home:hover {
            color: var(--primary);
            border-color: var(--primary-light);
            transform: translateY(-1px);
            box-shadow: 0 6px 15px rgba(79, 70, 229, 0.08);
        }

        .back-home:hover i {
            transform: translateX(-3px);
        }

        /* Centered High-Performance White Card */
        .register-card-wrapper {
            width: 100%;
            max-width: 480px;
            position: relative;
            z-index: 2;
            margin: auto;
        }

        .register-card {
            background: #ffffff;
            border: 1px solid var(--slate-200);
            border-radius: 1.75rem;
            box-shadow: 0 20px 45px rgba(15, 23, 42, 0.05), 0 1px 3px rgba(15, 23, 42, 0.02);
            padding: 2.5rem 2rem;
            position: relative;
            overflow: hidden; /* Clips the top accent line perfectly inside the card's border-radius */
        }

        .register-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; width: 100%; height: 5px;
            background: linear-gradient(90deg, var(--primary) 0%, var(--primary-light) 100%);
        }

        /* Branding Section Inside Card */
        .brand-section {
            text-align: center;
            margin-bottom: 1.75rem;
        }

        .brand-logo-wrap {
            width: 72px;
            height: 72px;
            background: #ffffff;
            border: 1px solid var(--slate-200);
            border-radius: 1.25rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.03);
        }

        .brand-logo-wrap img {
            width: 48px;
            height: 48px;
            object-fit: contain;
        }

        .brand-title {
            font-size: 1.65rem;
            font-weight: 800;
            margin-bottom: 0.25rem;
            letter-spacing: -0.5px;
            color: var(--slate-900);
        }

        .brand-subtitle {
            color: var(--slate-500);
            font-size: 0.825rem;
            font-weight: 500;
            line-height: 1.4;
            margin: 0 auto;
        }

        /* Inputs Styling */
        .form-group-custom {
            position: relative;
            margin-bottom: 1rem;
        }

        .form-label-custom {
            font-weight: 700;
            color: var(--slate-700);
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.35rem;
            margin-left: 0.15rem;
            display: block;
        }

        .input-icon-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-icon-left {
            position: absolute;
            left: 1.1rem;
            color: var(--slate-400);
            font-size: 1.05rem;
            pointer-events: none;
            transition: color 0.2s ease;
            z-index: 5;
        }

        .form-control-custom {
            width: 100%;
            border-radius: 0.85rem;
            padding: 0.75rem 1.1rem 0.75rem 2.75rem;
            border: 1.5px solid var(--slate-200);
            background: var(--slate-50);
            font-size: 0.925rem;
            font-weight: 500;
            color: var(--slate-900);
            transition: all 0.2s ease;
        }

        .form-control-custom::placeholder {
            color: var(--slate-400);
            font-weight: 400;
        }

        .form-control-custom:focus {
            border-color: var(--primary-light);
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
            outline: none;
        }

        .form-control-custom:focus ~ .input-icon-left {
            color: var(--primary);
        }

        /* Dropdown custom layout */
        select.form-control-custom {
            appearance: none;
            padding-right: 2.75rem;
            cursor: pointer;
        }

        .input-icon-right {
            position: absolute;
            right: 1.1rem;
            color: var(--slate-400);
            font-size: 0.95rem;
            pointer-events: none;
            transition: color 0.2s ease;
            z-index: 5;
        }

        /* Toggle Password Visibility Button */
        .btn-toggle-password {
            position: absolute;
            right: 1.1rem;
            background: none;
            border: none;
            color: var(--slate-400);
            font-size: 1.05rem;
            padding: 0;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: color 0.2s ease;
            z-index: 5;
        }

        .btn-toggle-password:hover {
            color: var(--primary);
        }

        /* High-Performance Elegant Button */
        .btn-login-gradient {
            width: 100%;
            background: var(--primary);
            border: none;
            border-radius: 0.85rem;
            padding: 0.8rem;
            font-weight: 700;
            font-size: 0.95rem;
            color: white;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.15);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: all 0.2s ease;
        }

        .btn-login-gradient:hover {
            background: var(--primary-light);
            transform: translateY(-1px);
            box-shadow: 0 6px 15px rgba(79, 70, 229, 0.25);
        }

        .btn-login-gradient:active {
            transform: translateY(0);
            box-shadow: 0 2px 8px rgba(79, 70, 229, 0.15);
        }

        /* High-Performance Clean Alert */
        .alert-premium {
            background: #fef2f2;
            border: 1px solid #fee2e2;
            border-left: 4px solid #ef4444;
            color: #991b1b;
            border-radius: 0.85rem;
            padding: 0.95rem;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
        }

        /* Card Footer */
        .login-footer {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1.25rem;
            border-top: 1px solid var(--slate-200);
            color: var(--slate-500);
            font-size: 0.85rem;
            font-weight: 600;
        }

        .login-footer a {
            color: var(--primary);
            font-weight: 700;
            text-decoration: none;
            transition: color 0.15s ease;
        }

        .login-footer a:hover {
            color: var(--primary-light);
            text-decoration: underline;
        }

        @media (max-width: 480px) {
            body {
                padding: 1rem;
            }
            .register-card {
                padding: 2rem 1.25rem;
            }
            .back-home {
                position: static;
                margin-bottom: 1.25rem;
                display: inline-flex;
            }
            .register-card-wrapper {
                display: flex;
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <!-- Main Floating Card Wrapper -->
    <div class="register-card-wrapper">
        <!-- Back to Home -->
        <a href="<?= BASE_URL ?>/index.php" class="back-home">
            <i class="bi bi-arrow-left"></i> กลับหน้าหลัก
        </a>

        <div class="register-card">
            <!-- Unified Brand & Title Section inside Card -->
            <div class="brand-section">
                <div class="brand-logo-wrap">
                    <img src="<?= BASE_URL ?>/images/logo.png" alt="DVE Logo">
                </div>
                <h1 class="brand-title">สมัครสมาชิกใหม่</h1>
                <p class="brand-subtitle">ร่วมเป็นส่วนหนึ่งของระบบนิเทศและฝึกงาน DVE Work Hub</p>
            </div>

            <!-- Error Alerts -->
            <?php if ($err): ?>
                <div class="alert-premium">
                    <i class="bi bi-exclamation-circle-fill me-2 fs-5"></i>
                    <div><?= htmlspecialchars($err) ?></div>
                </div>
            <?php endif; ?>

            <!-- Registration Form -->
            <form method="post" action="">
                <div class="form-group-custom">
                    <label for="username" class="form-label-custom">ชื่อผู้ใช้งาน (Username)</label>
                    <div class="input-icon-wrapper">
                        <input type="text" id="username" name="username" class="form-control-custom" required autofocus
                               placeholder="ตัวอย่าง: somchai (ชื่อภาษาอังกฤษสำหรับเข้าสู่ระบบ)" value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>">
                        <i class="bi bi-person-badge input-icon-left"></i>
                    </div>
                </div>

                <div class="form-group-custom">
                    <label for="fullname" class="form-label-custom">ชื่อ-นามสกุลจริง</label>
                    <div class="input-icon-wrapper">
                        <input type="text" id="fullname" name="fullname" class="form-control-custom" required
                               placeholder="ตัวอย่าง: นายสมชาย ใจดี" value="<?= isset($_POST['fullname']) ? htmlspecialchars($_POST['fullname']) : '' ?>">
                        <i class="bi bi-person input-icon-left"></i>
                    </div>
                </div>

                <div class="form-group-custom">
                    <label for="email" class="form-label-custom">ที่อยู่อีเมล</label>
                    <div class="input-icon-wrapper">
                        <input type="email" id="email" name="email" class="form-control-custom" required
                               placeholder="ตัวอย่าง: somchai.j@pbpvc.ac.th" value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
                        <i class="bi bi-envelope input-icon-left"></i>
                    </div>
                </div>

                <div class="form-group-custom">
                    <label for="phone" class="form-label-custom">เบอร์โทรศัพท์</label>
                    <div class="input-icon-wrapper">
                        <input type="text" id="phone" name="phone" class="form-control-custom" required
                               placeholder="ตัวอย่าง: 0812345678" value="<?= isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : '' ?>">
                        <i class="bi bi-telephone input-icon-left"></i>
                    </div>
                </div>

                <div class="form-group-custom">
                    <label for="password" class="form-label-custom">รหัสผ่าน</label>
                    <div class="input-icon-wrapper">
                        <input type="password" id="password" name="password" class="form-control-custom" required placeholder="ตัวอย่าง: 123456 (ความยาว 6 ตัวขึ้นไป)">
                        <i class="bi bi-shield-lock input-icon-left"></i>
                        <button type="button" class="btn-toggle-password" aria-label="Toggle password visibility">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="form-group-custom">
                    <label for="role" class="form-label-custom">สถานะ / บทบาทผู้ใช้งาน</label>
                    <div class="input-icon-wrapper">
                        <select id="role" name="role" class="form-control-custom" required>
                            <option value="student" <?= (isset($_POST['role']) && $_POST['role'] === 'student') ? 'selected' : '' ?>>นักเรียน/นักศึกษาฝึกงาน (Student)</option>
                            <option value="teacher" <?= (isset($_POST['role']) && $_POST['role'] === 'teacher') ? 'selected' : '' ?>>อาจารย์นิเทศก์/ครูที่ปรึกษา (Teacher)</option>
                            <option value="supervisor" <?= (isset($_POST['role']) && $_POST['role'] === 'supervisor') ? 'selected' : '' ?>>ผู้ดูแลการฝึกงาน / เจ้าหน้าที่ดูแลนักเรียน (Supervisor)</option>
                            <option value="director" <?= (isset($_POST['role']) && $_POST['role'] === 'director') ? 'selected' : '' ?>>ผู้บริหารสถานศึกษา (Director)</option>
                            <option value="staff" <?= (isset($_POST['role']) && $_POST['role'] === 'staff') ? 'selected' : '' ?>>เจ้าหน้าที่งานทวิภาคี (Staff)</option>
                        </select>
                        <i class="bi bi-people input-icon-left"></i>
                        <i class="bi bi-chevron-down input-icon-right"></i>
                    </div>
                </div>
                
                <div class="d-grid mt-4">
                    <button type="submit" class="btn-login-gradient">
                        <i class="bi bi-person-plus"></i> ลงทะเบียนใช้งาน
                    </button>
                </div>
            </form>

            <div class="login-footer">
                มีบัญชีสมาชิกอยู่แล้ว? <a href="<?= BASE_URL ?>/login.php">เข้าสู่ระบบ</a>
            </div>
        </div>
        
        <p class="text-center mt-3 text-muted small" style="font-weight: 500; opacity: 0.8;">
            &copy; <?= date('Y') ?> DVE Work Hub. All rights reserved.
        </p>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            // Toggle Password Visibility
            const toggleBtn = document.querySelector(".btn-toggle-password");
            const passwordInput = document.getElementById("password");
            if (toggleBtn && passwordInput) {
                toggleBtn.addEventListener("click", (e) => {
                    e.preventDefault();
                    const type = passwordInput.getAttribute("type") === "password" ? "text" : "password";
                    passwordInput.setAttribute("type", type);
                    
                    const icon = toggleBtn.querySelector("i");
                    if (type === "text") {
                        icon.classList.remove("bi-eye");
                        icon.classList.add("bi-eye-slash");
                    } else {
                        icon.classList.remove("bi-eye-slash");
                        icon.classList.add("bi-eye");
                    }
                });
            }
        });
    </script>
</body>
</html>
