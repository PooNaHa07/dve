<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/configdb.php';

$err = '';
$return_url = isset($_GET['return_url']) ? trim($_GET['return_url']) : (isset($_POST['return_url']) ? trim($_POST['return_url']) : '');

// อนุญาต return_url เฉพาะ path ในโปรเจกต์เรา (กัน open redirect)
$safe_return = false;
if ($return_url !== '') {
    $decoded = urldecode($return_url);
    if (strpos($decoded, '/') === 0 && strpos($decoded, '//') !== 0 && !preg_match('~^https?://~i', $decoded)) {
        $safe_return = $decoded;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    if (isset($_POST['return_url'])) $return_url = trim($_POST['return_url']);
    if ($return_url !== '') {
        $decoded = urldecode($return_url);
        if (strpos($decoded, '/') === 0 && strpos($decoded, '//') !== 0 && !preg_match('~^https?://~i', $decoded)) {
            $safe_return = $decoded;
        }
    }

    $stmt = $conn->prepare('SELECT id, password, role, fullname FROM users WHERE username=? OR email=? LIMIT 1');
    if (!$stmt) die("Prepare failed: " . $conn->error);

    $stmt->bind_param('ss', $username, $username);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();

    if ($res && password_verify($password, $res['password'])) {
        if (session_status() === PHP_SESSION_NONE) session_start();

        $_SESSION['user_id']  = $res['id'];
        $_SESSION['fullname'] = $res['fullname'];

        $raw_role = strtolower(trim($res['role']));
        $_SESSION['role'] = $raw_role;

        // ถ้ามี return_url ที่ปลอดภัย ให้ส่งกลับไปหน้านั้น (เช่น เปิดเอกสาร view.php?id=5)
        if ($safe_return !== false) {
            header('Location: ' . $safe_return);
            exit;
        }
        if ($raw_role === 'admin') {
            header('Location: ' . BASE_URL . '/roles/admin.php');
            exit;
        } elseif ($raw_role === 'director') {
            header('Location: ' . BASE_URL . '/roles/director.php');
            exit;
        } elseif ($raw_role === 'teacher') {
            header('Location: ' . BASE_URL . '/roles/teacher.php');
            exit;
        } elseif ($raw_role === 'supervisor') {
            header('Location: ' . BASE_URL . '/roles/supervisor.php');
            exit;
        } elseif ($raw_role === 'staff' || $raw_role === 'officer') {
            header('Location: ' . BASE_URL . '/roles/staff.php');
            exit;
        } elseif ($raw_role === 'student') {
            header('Location: ' . BASE_URL . '/roles/student.php');
            exit;
        } else {
            $err = "บทบาทผู้ใช้งานไม่ถูกต้อง (Role: $raw_role)";
        }
    } else {
        $err = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ - DVE | PBPVC</title>
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
        .login-card-wrapper {
            width: 100%;
            max-width: 440px;
            position: relative;
            z-index: 2;
            margin: auto;
        }

        .login-card {
            background: #ffffff;
            border: 1px solid var(--slate-200);
            border-radius: 1.75rem;
            box-shadow: 0 20px 45px rgba(15, 23, 42, 0.05), 0 1px 3px rgba(15, 23, 42, 0.02);
            padding: 2.75rem 2.25rem;
            position: relative;
            overflow: hidden; /* Clips the top accent line perfectly inside the card's border-radius */
        }

        .login-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; width: 100%; height: 5px;
            background: linear-gradient(90deg, var(--primary) 0%, var(--primary-light) 100%);
        }

        /* Branding Section Inside Card */
        .brand-section {
            text-align: center;
            margin-bottom: 2.25rem;
        }

        .brand-logo-wrap {
            width: 84px;
            height: 84px;
            background: #ffffff;
            border: 1px solid var(--slate-200);
            border-radius: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.25rem;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.03);
        }

        .brand-logo-wrap img {
            width: 58px;
            height: 58px;
            object-fit: contain;
        }

        .brand-title {
            font-size: 1.85rem;
            font-weight: 800;
            margin-bottom: 0.35rem;
            letter-spacing: -0.5px;
            color: var(--slate-900);
        }

        .brand-subtitle {
            color: var(--slate-500);
            font-size: 0.85rem;
            font-weight: 500;
            line-height: 1.4;
            margin: 0 auto;
            max-width: 320px;
        }

        /* Inputs Styling */
        .form-group-custom {
            position: relative;
            margin-bottom: 1.25rem;
        }

        .form-label-custom {
            font-weight: 700;
            color: var(--slate-700);
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.4rem;
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
            left: 1.15rem;
            color: var(--slate-400);
            font-size: 1.1rem;
            pointer-events: none;
            transition: color 0.2s ease;
            z-index: 5;
        }

        .form-control-custom {
            width: 100%;
            border-radius: 0.85rem;
            padding: 0.85rem 1.15rem 0.85rem 2.85rem;
            border: 1.5px solid var(--slate-200);
            background: var(--slate-50);
            font-size: 0.95rem;
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

        /* Toggle Password Visibility Button */
        .btn-toggle-password {
            position: absolute;
            right: 1.15rem;
            background: none;
            border: none;
            color: var(--slate-400);
            font-size: 1.1rem;
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
            padding: 0.85rem;
            font-weight: 700;
            font-size: 1rem;
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
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
        }

        /* Card Footer */
        .login-footer {
            text-align: center;
            margin-top: 1.75rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--slate-200);
            color: var(--slate-500);
            font-size: 0.875rem;
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
            .login-card {
                padding: 2rem 1.5rem;
            }
            .back-home {
                position: static;
                margin-bottom: 1.5rem;
                display: inline-flex;
            }
            .login-card-wrapper {
                display: flex;
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <!-- Main Floating Card Wrapper -->
    <div class="login-card-wrapper">
        <!-- Back to Home -->
        <a href="<?= BASE_URL ?>/index.php" class="back-home">
            <i class="bi bi-arrow-left"></i> กลับหน้าหลัก
        </a>

        <div class="login-card">
            <!-- Unified Brand & Title Section inside Card -->
            <div class="brand-section">
                <div class="brand-logo-wrap">
                    <img src="<?= BASE_URL ?>/images/logo.png" alt="DVE Logo">
                </div>
                <h1 class="brand-title">DVE WORK HUB</h1>
                <p class="brand-subtitle">ระบบการนิเทศรายวิชาฝึกประสบการณ์สมรรถนะวิชาชีพ<br>วิทยาลัยอาชีวศึกษาเพชรบุรี</p>
            </div>

            <!-- Error Alerts -->
            <?php if ($err): ?>
                <div class="alert-premium">
                    <i class="bi bi-exclamation-circle-fill me-2 fs-5"></i>
                    <div><?= htmlspecialchars($err) ?></div>
                </div>
            <?php endif; ?>

            <!-- Login Form -->
            <form method="post" action="">
                <!-- Hidden input for return URL -->
                <input type="hidden" name="return_url" value="<?= htmlspecialchars($return_url) ?>">

                <div class="form-group-custom">
                    <label for="username" class="form-label-custom">ชื่อผู้ใช้ หรือ อีเมล</label>
                    <div class="input-icon-wrapper">
                        <input type="text" id="username" name="username" class="form-control-custom" required autofocus
                               placeholder="ชื่อผู้ใช้ หรือ อีเมลของคุณ" value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>">
                        <i class="bi bi-person input-icon-left"></i>
                    </div>
                </div>

                <div class="form-group-custom">
                    <label for="password" class="form-label-custom">รหัสผ่าน</label>
                    <div class="input-icon-wrapper">
                        <input type="password" id="password" name="password" class="form-control-custom" required placeholder="ป้อนรหัสผ่านของคุณ">
                        <i class="bi bi-shield-lock input-icon-left"></i>
                        <button type="button" class="btn-toggle-password" aria-label="Toggle password visibility">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                    <div class="text-end mt-2">
                        <a href="<?= BASE_URL ?>/forgot_password.php" class="text-decoration-none small fw-bold" style="color: var(--slate-500); transition: color 0.2s;">ลืมรหัสผ่าน?</a>
                    </div>
                </div>

                
                <div class="d-grid mt-4">
                    <button type="submit" class="btn-login-gradient">
                        <i class="bi bi-box-arrow-in-right"></i> เข้าสู่ระบบ
                    </button>
                </div>
            </form>

            <div class="login-footer">
                ยังไม่มีบัญชีสมาชิก? <a href="<?= BASE_URL ?>/register.php">สมัครสมาชิกใหม่</a>
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
