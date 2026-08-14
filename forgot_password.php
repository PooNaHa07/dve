<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/configdb.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$err = '';
$success = '';
$step = isset($_SESSION['reset_user_id']) ? 2 : 1;

// Step 1: Verify Identity
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify'])) {
    $username_email = trim($_POST['username_email']);
    $phone = trim($_POST['phone']);

    $stmt = $conn->prepare('SELECT id FROM users WHERE (username=? OR email=?) AND phone=? LIMIT 1');
    $stmt->bind_param('sss', $username_email, $username_email, $phone);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();

    if ($res) {
        $_SESSION['reset_user_id'] = $res['id'];
        $step = 2;
    } else {
        $err = 'ไม่พบข้อมูลผู้ใช้งานที่ตรงกับชื่อผู้ใช้/อีเมล และเบอร์โทรศัพท์นี้';
    }
}

// Step 2: Reset Password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset'])) {
    if (!isset($_SESSION['reset_user_id'])) {
        header('Location: forgot_password.php');
        exit;
    }

    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];

    if (strlen($new_pass) < 6) {
        $err = 'รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร';
    } elseif ($new_pass !== $confirm_pass) {
        $err = 'รหัสผ่านใหม่และการยืนยันรหัสผ่านไม่ตรงกัน';
    } else {
        $user_id = $_SESSION['reset_user_id'];
        $hashed_pass = password_hash($new_pass, PASSWORD_DEFAULT);

        $stmt = $conn->prepare('UPDATE users SET password=? WHERE id=?');
        $stmt->bind_param('si', $hashed_pass, $user_id);
        
        if ($stmt->execute()) {
            unset($_SESSION['reset_user_id']);
            $success = 'เปลี่ยนรหัสผ่านสำเร็จแล้ว คุณสามารถเข้าสู่ระบบได้ทันที';
            // Optional: Redirect after a few seconds or show a link
        } else {
            $err = 'เกิดข้อผิดพลาดในการบันทึกรหัสผ่านใหม่: ' . $conn->error;
        }
    }
}

// Cancel reset
if (isset($_GET['cancel'])) {
    unset($_SESSION['reset_user_id']);
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ลืมรหัสผ่าน - DVE | PBPVC</title>
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

        body {
            font-family: 'Sarabun', 'Outfit', sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, #e0f2fe 0%, #e0e7ff 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem 1rem;
        }

        .reset-card {
            background: #ffffff;
            border: 1px solid var(--slate-200);
            border-radius: 1.75rem;
            box-shadow: 0 20px 45px rgba(15, 23, 42, 0.05);
            width: 100%;
            max-width: 440px;
            padding: 2.75rem 2.25rem;
            position: relative;
            overflow: hidden;
        }

        .reset-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; width: 100%; height: 5px;
            background: linear-gradient(90deg, #f59e0b 0%, #fbbf24 100%);
        }

        .brand-section {
            text-align: center;
            margin-bottom: 2rem;
        }

        .brand-logo-wrap {
            width: 70px;
            height: 70px;
            background: #ffffff;
            border: 1px solid var(--slate-200);
            border-radius: 1.25rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
        }

        .brand-logo-wrap img { width: 48px; height: 48px; object-fit: contain; }

        .brand-title { font-size: 1.5rem; font-weight: 800; color: var(--slate-900); }
        .brand-subtitle { color: var(--slate-500); font-size: 0.85rem; font-weight: 500; }

        .form-label-custom {
            font-weight: 700;
            color: var(--slate-700);
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.4rem;
            display: block;
        }

        .form-control-custom {
            width: 100%;
            border-radius: 0.85rem;
            padding: 0.85rem 1.15rem;
            border: 1.5px solid var(--slate-200);
            background: var(--slate-50);
            font-size: 0.95rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .form-control-custom:focus {
            border-color: #f59e0b;
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(245, 158, 11, 0.1);
            outline: none;
        }

        .btn-reset-gradient {
            width: 100%;
            background: #f59e0b;
            border: none;
            border-radius: 0.85rem;
            padding: 0.85rem;
            font-weight: 700;
            color: white;
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.15);
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .btn-reset-gradient:hover {
            background: #d97706;
            transform: translateY(-1px);
        }

        .alert-premium {
            border-radius: 0.85rem;
            padding: 0.95rem;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
        }

        .alert-error {
            background: #fef2f2;
            border: 1px solid #fee2e2;
            border-left: 4px solid #ef4444;
            color: #991b1b;
        }

        .alert-success {
            background: #f0fdf4;
            border: 1px solid #dcfce7;
            border-left: 4px solid #22c55e;
            color: #166534;
        }

        .step-indicator {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-bottom: 2rem;
        }

        .step-dot {
            width: 30px;
            height: 6px;
            border-radius: 3px;
            background: var(--slate-200);
        }

        .step-dot.active {
            background: #f59e0b;
        }
    </style>
</head>
<body>
    <div class="reset-card">
        <div class="brand-section">
            <div class="brand-logo-wrap">
                <img src="<?= BASE_URL ?>/images/logo.png" alt="Logo">
            </div>
            <h1 class="brand-title">กู้คืนรหัสผ่าน</h1>
            <p class="brand-subtitle">DVE WORK HUB | PBPVC</p>
        </div>

        <div class="step-indicator">
            <div class="step-dot <?= $step >= 1 ? 'active' : '' ?>"></div>
            <div class="step-dot <?= $step >= 2 ? 'active' : '' ?>"></div>
        </div>

        <?php if ($err): ?>
            <div class="alert-premium alert-error">
                <i class="bi bi-exclamation-circle-fill me-2 fs-5"></i>
                <div><?= htmlspecialchars($err) ?></div>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert-premium alert-success">
                <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                <div><?= htmlspecialchars($success) ?></div>
            </div>
            <div class="d-grid mt-4">
                <a href="<?= BASE_URL ?>/login.php" class="btn btn-primary rounded-pill py-2">
                    <i class="bi bi-box-arrow-in-right"></i> เข้าสู่ระบบตอนนี้
                </a>
            </div>
        <?php elseif ($step === 1): ?>
            <!-- Step 1 Form -->
            <form method="post">
                <div class="mb-3">
                    <label class="form-label-custom">ชื่อผู้ใช้ หรือ อีเมล</label>
                    <input type="text" name="username_email" class="form-control-custom" required 
                           placeholder="ชื่อผู้ใช้ที่ต้องการกู้คืน" value="<?= isset($_POST['username_email']) ? htmlspecialchars($_POST['username_email']) : '' ?>">
                </div>
                <div class="mb-4">
                    <label class="form-label-custom">เบอร์โทรศัพท์ (ที่ลงทะเบียนไว้)</label>
                    <input type="text" name="phone" class="form-control-custom" required 
                           placeholder="08X-XXX-XXXX" value="<?= isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : '' ?>">
                    <div class="form-text small mt-2">โปรดป้อนเบอร์โทรศัพท์ที่ถูกต้องเพื่อยืนยันตัวตน</div>
                </div>
                <div class="d-grid gap-2">
                    <button type="submit" name="verify" class="btn-reset-gradient">
                        ตรวจสอบข้อมูล <i class="bi bi-arrow-right ms-1"></i>
                    </button>
                    <a href="<?= BASE_URL ?>/login.php" class="btn btn-link text-muted text-decoration-none small">
                        กลับหน้าเข้าสู่ระบบ
                    </a>
                </div>
            </form>
        <?php elseif ($step === 2): ?>
            <!-- Step 2 Form -->
            <form method="post">
                <div class="mb-3">
                    <label class="form-label-custom">รหัสผ่านใหม่</label>
                    <input type="password" name="new_password" class="form-control-custom" required placeholder="อย่างน้อย 6 ตัวอักษร">
                </div>
                <div class="mb-4">
                    <label class="form-label-custom">ยืนยันรหัสผ่านใหม่</label>
                    <input type="password" name="confirm_password" class="form-control-custom" required placeholder="ป้อนรหัสผ่านอีกครั้ง">
                </div>
                <div class="d-grid gap-2">
                    <button type="submit" name="reset" class="btn-reset-gradient">
                        <i class="bi bi-shield-check me-1"></i> ยืนยันการเปลี่ยนรหัสผ่าน
                    </button>
                    <a href="?cancel=1" class="btn btn-link text-muted text-decoration-none small">
                        ยกเลิกและกลับไปเริ่มต้นใหม่
                    </a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
