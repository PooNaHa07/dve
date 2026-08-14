<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/configdb.php';
date_default_timezone_set('Asia/Bangkok');

$settings_query = $conn->query("SELECT * FROM internship_settings");
$intern_data = [];
while ($row = $settings_query->fetch_assoc()) {
    $intern_data[$row['level_name']] = $row;
}
$levels = array('ปวช.', 'ปวส.', 'ทวิภาคี (ปวช.)', 'ทวิภาคี (ปวส.)');
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <title>DVE | PBPVC - วิทยาลัยอาชีวศึกษาเพชรบุรี</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="/DVE_DATA_FULL/images/logo.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --secondary: #0ea5e9;
            --accent: #d946ef;
            --glass: rgba(255, 255, 255, 0.8);
            --glass-border: rgba(255, 255, 255, 0.4);
        }

        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        
        body {
            font-family: 'Sarabun', sans-serif;
            background: #f8fafc;
            color: #1e293b;
            min-height: 100vh;
            margin: 0;
            line-height: 1.6;
            overflow-x: hidden;
        }

        /* Animated Background Blobs */
        .bg-blob {
            position: fixed;
            width: 800px;
            height: 800px;
            filter: blur(120px);
            z-index: -1;
            opacity: 0.15;
            pointer-events: none;
            border-radius: 50%;
            animation: float 20s infinite alternate;
        }
        .blob-1 { background: var(--primary); top: -300px; right: -200px; animation-delay: 0s; }
        .blob-2 { background: var(--secondary); bottom: -300px; left: -200px; animation-delay: -5s; }
        .blob-3 { background: var(--accent); top: 40%; left: 30%; width: 500px; height: 500px; animation-delay: -10s; }

        @keyframes float {
            0% { transform: translate(0, 0) scale(1); }
            100% { transform: translate(100px, 50px) scale(1.1); }
        }

        /* Navbar Modernization - Integrated Formal Style */
        .landing-nav {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border-bottom: 1px solid rgba(99, 102, 241, 0.1);
            padding: 0.75rem 0;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0,0,0,0.02);
            z-index: 1045;
        }

        .navbar-brand {
            font-weight: 800;
            font-size: 1.4rem;
            color: var(--primary-dark) !important;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            letter-spacing: -0.5px;
        }

        .navbar-brand img {
            height: 40px;
        }

        .nav-link {
            color: #334155 !important;
            font-weight: 600;
            font-size: 0.95rem;
            padding: 0.5rem 1.25rem !important;
            border-radius: 8px;
            transition: all 0.2s;
        }

        .nav-link:hover {
            color: var(--primary) !important;
            background: rgba(99, 102, 241, 0.05);
        }

        .btn-nav-login {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white !important;
            border: none;
            border-radius: 8px;
            padding: 0.6rem 1.5rem;
            font-weight: 700;
            box-shadow: 0 4px 10px rgba(99, 102, 241, 0.2);
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            white-space: nowrap;
            text-decoration: none;
        }

        .btn-nav-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 25px rgba(99, 102, 241, 0.3);
        }

        /* Hero Section */
        .hero-section {
            padding: 8rem 0 6rem;
            text-align: center;
            position: relative;
        }

        .hero-badge {
            background: rgba(99, 102, 241, 0.1);
            color: var(--primary);
            padding: 0.5rem 1.5rem;
            border-radius: 2rem;
            font-weight: 800;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 2px;
            display: inline-block;
            margin-bottom: 2rem;
        }

        .hero-title {
            font-size: clamp(2.5rem, 8vw, 4.5rem);
            font-weight: 900;
            color: #0f172a;
            line-height: 1.35;
            margin-bottom: 1.5rem;
            letter-spacing: normal;
        }

        .hero-gradient {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero-description {
            font-size: 1.25rem;
            color: #334155;
            max-width: 700px;
            margin: 0 auto 3rem;
            line-height: 1.6;
        }
        
        .level-card p {
            color: #475569;
            font-size: 0.95rem;
            margin-bottom: 2rem;
            line-height: 1.6;
        }

        .feature-card p {
            color: #475569;
            font-size: 0.95rem;
            margin-bottom: 0;
            line-height: 1.6;
        }

        /* Level Cards */
        .section-title {
            font-size: 2.25rem;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 1rem;
            letter-spacing: -1px;
        }

        .level-card {
            background: var(--glass);
            backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
            border-radius: 2rem;
            padding: 2.5rem 2rem;
            height: 100%;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            text-align: center;
        }

        .level-card:hover {
            transform: translateY(-10px);
            background: white;
            box-shadow: 0 30px 60px rgba(0,0,0,0.08);
            border-color: var(--primary);
        }

        .level-icon {
            width: 64px;
            height: 64px;
            background: rgba(99, 102, 241, 0.1);
            color: var(--primary);
            border-radius: 1.25rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            margin: 0 auto 1.5rem;
        }

        .status-badge {
            border-radius: 2rem;
            padding: 0.4rem 1.25rem;
            font-weight: 700;
            font-size: 0.75rem;
            text-transform: uppercase;
            margin-bottom: 1.5rem;
            display: inline-block;
        }

        /* Roles Grid */
        .role-card {
            background: white;
            border-radius: 2rem;
            padding: 3rem 2rem;
            text-align: center;
            transition: all 0.3s ease;
            border: 1px solid #e2e8f0;
            height: 100%;
        }

        .role-card:hover {
            background: var(--primary);
            color: white;
            transform: scale(1.05);
            box-shadow: 0 20px 40px rgba(99, 102, 241, 0.2);
        }

        .role-icon-box {
            width: 80px;
            height: 80px;
            border-radius: 2rem;
            background: #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin: 0 auto 2rem;
            transition: all 0.3s ease;
        }

        .role-card:hover .role-icon-box {
            background: rgba(255,255,255,0.2);
            color: white;
        }

        /* Mobile Optimization & Premium Hamburger Dropdown Menu */
        @media (max-width: 991.98px) {
            .landing-nav .navbar-toggler {
                display: flex !important;
                align-items: center;
                justify-content: center;
                border: none;
                padding: 6px 10px;
                border-radius: 12px;
                background: rgba(99, 102, 241, 0.08);
                color: var(--primary-dark) !important;
                transition: all 0.25s ease;
                outline: none !important;
                box-shadow: none !important;
            }
            .landing-nav .navbar-toggler:hover {
                background: rgba(99, 102, 241, 0.15);
                transform: scale(1.05);
            }
            .landing-nav .navbar-toggler:active {
                transform: scale(0.95);
            }
            .landing-nav .navbar-toggler i {
                color: var(--primary-dark) !important;
            }

            /* ออกแบบเมนูดร็อปดาวน์สไตล์ Side Drawer (Offcanvas) ให้ใช้งานง่ายและเหมือนแอป Native */
            .landing-nav .navbar-collapse {
                position: fixed;
                top: 0;
                right: -320px;
                width: 300px;
                max-width: 85vw;
                height: 100vh;
                background: rgba(255, 255, 255, 0.98);
                backdrop-filter: blur(25px);
                -webkit-backdrop-filter: blur(25px);
                z-index: 1050;
                padding: 1.5rem;
                display: flex !important;
                flex-direction: column;
                transition: right 0.4s cubic-bezier(0.16, 1, 0.3, 1), visibility 0.4s;
                box-shadow: -15px 0 40px rgba(0, 0, 0, 0.08);
                overflow-y: auto;
                visibility: hidden;
                border-radius: 20px 0 0 20px;
            }

            .landing-nav .navbar-collapse.show {
                right: 0;
                visibility: visible;
            }

            /* Backdrop Overlay */
            .mobile-menu-backdrop {
                position: fixed;
                inset: 0;
                background: rgba(15, 23, 42, 0.4);
                backdrop-filter: blur(3px);
                -webkit-backdrop-filter: blur(3px);
                z-index: 1040;
                opacity: 0;
                visibility: hidden;
                transition: all 0.3s ease;
            }
            .mobile-menu-backdrop.show {
                opacity: 1;
                visibility: visible;
            }

            .landing-nav .navbar-nav {
                gap: 6px;
                margin-bottom: 20px !important;
            }

            .landing-nav .nav-link {
                padding: 12px 18px !important;
                font-size: 0.95rem;
                display: flex;
                align-items: center;
                border-radius: 12px;
                background: transparent;
                transition: all 0.2s ease;
            }

            .landing-nav .nav-link:hover {
                background: rgba(99, 102, 241, 0.06) !important;
                color: var(--primary) !important;
                padding-left: 24px !important;
            }

            .landing-nav .nav-link i,
            .landing-nav .nav-link .bi {
                font-size: 1.1rem;
                color: var(--primary);
                transition: transform 0.2s ease;
            }

            .landing-nav .nav-link:hover i,
            .landing-nav .nav-link:hover .bi {
                transform: scale(1.15);
            }

            /* ปรับปุ่มเข้าสู่ระบบ / การจัดการปุ่มกลุ่มบนมือถือ */
            .landing-nav .navbar-collapse .d-flex {
                flex-direction: column;
                width: 100%;
                gap: 12px !important;
                align-items: stretch !important;
            }

            .landing-nav .btn-nav-login {
                width: 100%;
                justify-content: center;
                padding: 12px !important;
                border-radius: 12px;
                font-size: 0.95rem;
            }

            /* Hero Section & Spacing Mobile Optimizations */
            .hero-section { 
                padding: 4rem 1rem 3rem !important; 
            }
            .hero-title {
                font-size: clamp(1.8rem, 8vw, 2.5rem);
                margin-bottom: 1rem;
            }
            .hero-description {
                font-size: 1.05rem;
                margin-bottom: 2rem;
                padding: 0 0.5rem;
            }
            .section-title {
                font-size: 1.75rem;
            }
            .level-card {
                padding: 1.5rem 1.25rem;
            }
            .role-card {
                padding: 2rem 1.5rem;
            }
            .cta-box {
                padding: 3rem 1.5rem;
                margin-top: 4rem;
                border-radius: 2rem;
            }
            /* Stack buttons full width on small mobile screens */
            @media (max-width: 767.98px) {
                .hero-section .d-flex.flex-wrap,
                .cta-box .d-flex.flex-wrap {
                    flex-direction: column;
                    width: 100%;
                    gap: 12px !important;
                }
                .hero-section .btn,
                .cta-box .btn {
                    width: 100%;
                    padding: 0.85rem !important;
                }
            }
            body { padding-bottom: 80px; }
        }

        .bottom-nav {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(20px);
            border-top: 1px solid #e2e8f0;
            padding: 0.75rem 0.5rem;
            box-shadow: 0 -10px 30px rgba(0,0,0,0.05);
        }

        .bottom-nav-item {
            color: #64748b;
            text-decoration: none;
            font-size: 0.7rem;
            font-weight: 700;
            text-align: center;
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .bottom-nav-item i { font-size: 1.4rem; }
        .bottom-nav-item.active { color: var(--primary); }

        .cta-box {
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            border-radius: 3rem;
            padding: 5rem 3rem;
            color: white;
            text-align: center;
            margin-top: 6rem;
            position: relative;
            overflow: hidden;
        }

        .cta-box::after {
            content: '';
            position: absolute;
            top: 0; right: 0;
            width: 300px; height: 300px;
            background: var(--primary);
            filter: blur(150px);
            opacity: 0.3;
        }

        /* Custom Premium Footer Card matching the reference image */
        .custom-footer {
            margin-top: 6rem;
            margin-bottom: 3rem;
            position: relative;
            z-index: 5;
        }

        .footer-card {
            background: #ffffff;
            border-radius: 2rem;
            padding: 3rem;
            box-shadow: 0 10px 40px rgba(15, 23, 42, 0.03);
            border: 1px solid rgba(226, 232, 240, 0.8);
        }

        .footer-icon-box {
            width: 72px;
            height: 72px;
            background: #eef2ff;
            border-radius: 1.25rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.25rem;
            color: var(--primary);
            flex-shrink: 0;
            box-shadow: 0 8px 24px rgba(99, 102, 241, 0.08);
        }

        .footer-brand-text {
            display: flex;
            flex-direction: column;
            gap: 0.15rem;
        }

        .footer-title {
            font-size: 1.5rem;
            font-weight: 800;
            color: #0f172a;
            margin: 0;
            letter-spacing: -0.025em;
        }

        .footer-subtitle {
            font-size: 1.15rem;
            font-weight: 600;
            color: #475569;
            margin: 0;
        }

        .footer-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            background: #eef2ff;
            color: #6366f1;
            padding: 0.35rem 0.85rem;
            border-radius: 50rem;
            font-size: 0.75rem;
            font-weight: 800;
            width: fit-content;
            margin-top: 0.5rem;
            border: 1px solid #e2e8f0;
            letter-spacing: 0.05em;
        }

        .footer-tagline {
            font-size: 0.95rem;
            color: #64748b;
            line-height: 1.6;
            margin: 0;
            font-weight: 500;
        }

        /* Buddhist Year Card in the Center */
        .year-card {
            background: #ffffff;
            border-radius: 1.25rem;
            padding: 1.2rem 2rem;
            display: inline-flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            border: 1px solid #e0f2fe;
            box-shadow: 0 10px 25px rgba(14, 165, 233, 0.04);
            position: relative;
        }

        .year-card::before {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: 1.25rem;
            padding: 2px;
            background: linear-gradient(135deg, #e0f2fe, #e0e7ff);
            -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
            pointer-events: none;
        }

        .year-label {
            font-size: 0.8rem;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: 0.05em;
            margin-bottom: 0.25rem;
        }

        .year-value {
            font-size: 2rem;
            font-weight: 900;
            color: #0f172a;
            line-height: 1;
            letter-spacing: -0.03em;
        }

        /* Social buttons and credits on the right */
        .social-btn {
            width: 48px;
            height: 48px;
            background: #ffffff;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #4f46e5;
            font-size: 1.35rem;
            border: 1px solid #f1f5f9;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.04);
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .social-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(79, 70, 229, 0.12);
            color: #4f46e5;
            border-color: #e0e7ff;
        }

        .social-btn i.bi-line {
            color: #22c55e;
        }

        .copyright-text {
            font-size: 0.85rem;
            color: #64748b;
            font-weight: 500;
        }

        .credit-text {
            font-size: 0.8rem;
            color: #94a3b8;
            font-weight: 500;
        }

        .credit-text .fw-bold {
            color: #475569;
        }
    </style>
</head>
<body>
    <div class="bg-blob blob-1"></div>
    <div class="bg-blob blob-2"></div>
    <div class="bg-blob blob-3"></div>

    <nav class="navbar navbar-expand-lg landing-nav sticky-top">
        <div class="container">
            <a class="navbar-brand" href="Landing-Page.php">
                <img src="images/logo.png" alt="DVE | PBPVC">
                <span>DVE | PBPVC</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="custom-drawer" data-bs-target="#navbarContent">
                <i class="bi bi-list fs-2 text-dark"></i>
            </button>
            <div class="collapse navbar-collapse" id="navbarContent">
                <!-- Mobile Menu Header -->
                <div class="d-flex justify-content-between align-items-center w-100 mb-4 d-lg-none">
                    <span class="fw-bold fs-5 text-dark" style="letter-spacing: -0.5px;">เมนูหลัก</span>
                    <button type="button" class="btn btn-light rounded-circle p-2 d-flex align-items-center justify-content-center mobile-menu-close shadow-sm" style="width: 40px; height: 40px; border: 1px solid #e2e8f0;">
                        <i class="bi bi-x-lg text-secondary"></i>
                    </button>
                </div>
                <ul class="navbar-nav mx-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a href="https://202.29.236.132/" target="_blank" rel="noopener noreferrer" class="nav-link d-flex align-items-center"><i class="bi bi-calendar-event me-2"></i>ปฏิทินกิจกรรม</a></li>
                    <li class="nav-item"><a href="news_list.php" class="nav-link d-flex align-items-center"><i class="bi bi-newspaper me-2"></i>ข่าวประชาสัมพันธ์</a></li>
                    <li class="nav-item"><a href="companies/list.php" class="nav-link d-flex align-items-center"><i class="bi bi-building me-2"></i>สถานประกอบการ</a></li>
                    <li class="nav-item"><a href="documents_list.php" class="nav-link d-flex align-items-center"><i class="bi bi-download me-2"></i>ดาวน์โหลด</a></li>
                    <li class="nav-item"><a href="contact.php" class="nav-link d-flex align-items-center"><i class="bi bi-chat-dots me-2"></i>ติดต่อสอบถาม</a></li>
                </ul>
                <div class="d-flex gap-3">
                    <a href="login.php" class="btn btn-nav-login d-inline-flex align-items-center"><i class="bi bi-box-arrow-in-right me-2"></i>เข้าสู่ระบบ</a>
                </div>
            </div>
        </div>
    </nav>

    <header class="hero-section">
        <div class="container">
            <div class="hero-badge animate-fade-in">
                วิทยาลัยอาชีวศึกษาเพชรบุรี
            </div>
            <h1 class="hero-title animate-slide-up">
                ระบบการนิเทศ<br>
                <span class="hero-gradient">รายวิชาฝึกประสบการณ์สมรรถนะวิชาชีพ</span>
            </h1>
            <p class="hero-description animate-slide-up" style="animation-delay: 0.1s;">
                ยกระดับการฝึกอาชีพด้วยเทคโนโลยีดิจิทัล
            </p>
            <div class="d-flex flex-wrap justify-content-center gap-3" data-aos="fade-up" data-aos-delay="300">
                <a href="login.php" class="btn btn-nav-login btn-lg px-5 py-3">เริ่มใช้งานเลย</a>
                <a href="#schedule" class="btn btn-outline-dark btn-lg px-5 py-3 rounded-4 fw-bold">ดูตารางฝึกงาน</a>
            </div>
        </div>
    </header>

    <main class="container py-5" id="schedule">
        <div class="text-center mb-5">
            <h2 class="section-title">กำหนดการฝึกอาชีพ</h2>
            <p class="text-muted fw-bold">ข้อมูลอัปเดตเรียลไทม์ตามประกาศล่าสุด</p>
        </div>

        <div class="row g-4">
            <?php
            $icons = ['bi-mortarboard', 'bi-book', 'bi-award', 'bi-stars'];
            foreach ($levels as $index => $lv):
                $s = isset($intern_data[$lv]) ? $intern_data[$lv] : null;
                $start_txt = ($s && !empty($s['start_date'])) ? date('d/m/Y', strtotime($s['start_date'])) : 'N/A';
                $end_txt = ($s && !empty($s['end_date'])) ? date('d/m/Y', strtotime($s['end_date'])) : 'N/A';

                $badge_class = "bg-light text-secondary";
                $status_label = "ยังไม่เปิด";

                if ($s && !empty($s['start_date']) && !empty($s['end_date'])) {
                    $today = new DateTime(date('Y-m-d'));
                    $start = new DateTime($s['start_date']);
                    $end = new DateTime($s['end_date']);
                    if ($today < $start) {
                        $badge_class = "bg-warning-subtle text-warning-emphasis";
                        $status_label = "เตรียมตัวฝึกงาน";
                    } elseif ($today > $end) {
                        $badge_class = "bg-danger-subtle text-danger-emphasis";
                        $status_label = "สิ้นสุดรอบการฝึก";
                    } else {
                        $badge_class = "bg-success-subtle text-success-emphasis";
                        $status_label = "กำลังดำเนินการฝึก";
                    }
                }
            ?>
            <div class="col-md-6 col-lg-3">
                <div class="level-card">
                    <div class="level-icon">
                        <i class="bi <?= $icons[$index] ?>"></i>
                    </div>
                    <h3 class="h5 fw-bold mb-3"><?= htmlspecialchars($lv) ?></h3>
                    <span class="status-badge <?= $badge_class ?>"><?= $status_label ?></span>
                    <div class="d-flex flex-column gap-2 text-start bg-light p-3 rounded-4">
                        <div class="small text-muted fw-bold"><i class="bi bi-calendar-check me-2 text-success"></i>เริ่ม: <?= $start_txt ?></div>
                        <div class="small text-muted fw-bold"><i class="bi bi-calendar-x me-2 text-danger"></i>สิ้นสุด: <?= $end_txt ?></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="mt-5 pt-5">
            <div class="text-center mb-5">
                <h2 class="section-title">บทบาทในระบบ</h2>
                <p class="text-muted fw-bold">เข้าถึงข้อมูลที่เหมาะสมตามสิทธิ์ของคุณ</p>
            </div>
            <div class="row g-4">
                <div class="col-md-6 col-lg-3">
                    <div class="role-card">
                        <div class="role-icon-box text-purple" style="color: #6b21a8;"><i class="bi bi-shield-lock"></i></div>
                        <h4 class="fw-bold">ผู้ดูแลระบบ</h4>
                        <p class="text-muted small fw-bold">จัดการโครงสร้างพื้นฐานและข้อมูลหลักของระบบ</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="role-card">
                        <div class="role-icon-box text-blue" style="color: #1d4ed8;"><i class="bi bi-person-gear"></i></div>
                        <h4 class="fw-bold">เจ้าหน้าที่</h4>
                        <p class="text-muted small fw-bold">ประสานงานทวิภาคีและจัดการงานเอกสารดิจิทัล</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="role-card">
                        <div class="role-icon-box text-green" style="color: #15803d;"><i class="bi bi-easel"></i></div>
                        <h4 class="fw-bold">ครูนิเทศก์</h4>
                        <p class="text-muted small fw-bold">ติดตามและประเมินผลการฝึกงานของนักเรียน</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="role-card">
                        <div class="role-icon-box text-orange" style="color: #c2410c;"><i class="bi bi-person-badge"></i></div>
                        <h4 class="fw-bold">นักเรียน</h4>
                        <p class="text-muted small fw-bold">บันทึกรายงานประจำวันและติดตามผลการฝึกอาชีพ</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="cta-box">
            <p class="lead mb-5 opacity-75">เข้าสู่ระบบเพื่อจัดการข้อมูลการฝึกงานของคุณได้ทันที</p>
            <div class="d-flex flex-wrap justify-content-center gap-3">
                <a href="login.php" class="btn btn-light btn-lg px-5 py-3 rounded-4 fw-bold">เข้าสู่ระบบ</a>
                <a href="register.php" class="btn btn-outline-light btn-lg px-5 py-3 rounded-4 fw-bold">สมัครสมาชิกใหม่</a>
            </div>
        </div>
    </main>

    <?php include __DIR__ . '/includes/footer.php'; ?>

    <nav class="bottom-nav fixed-bottom d-lg-none d-flex justify-content-around">
        <a href="Landing-Page.php" class="bottom-nav-item active"><i class="bi bi-house-door"></i><span>หน้าหลัก</span></a>
        <a href="https://202.29.236.132/" target="_blank" rel="noopener noreferrer" class="bottom-nav-item"><i class="bi bi-calendar-event"></i><span>กิจกรรม</span></a>
        <a href="news_list.php" class="bottom-nav-item"><i class="bi bi-newspaper"></i><span>ข่าวสาร</span></a>
        <a href="companies/list.php" class="bottom-nav-item"><i class="bi bi-building"></i><span>บริษัท</span></a>
        <a href="contact.php" class="bottom-nav-item"><i class="bi bi-chat-dots"></i><span>ติดต่อ</span></a>
    </nav>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Robust Native JS fallback for Mobile Navbar Hamburger Toggle (using event delegation)
    var mobileBackdrop = null;
    document.addEventListener('click', function(e) {
        if (!mobileBackdrop && document.body) {
            mobileBackdrop = document.createElement('div');
            mobileBackdrop.className = 'mobile-menu-backdrop d-lg-none';
            document.body.appendChild(mobileBackdrop);
        }

        var toggler = e.target.closest('.navbar-toggler');
        var closeBtn = e.target.closest('.mobile-menu-close');
        var isBackdrop = e.target.classList.contains('mobile-menu-backdrop');
        
        if (toggler) {
            var targetId = toggler.getAttribute('data-bs-target');
            if (targetId) {
                var target = document.querySelector(targetId);
                if (target) {
                    e.preventDefault();
                    var isShown = target.classList.toggle('show');
                    toggler.setAttribute('aria-expanded', isShown ? 'true' : 'false');
                    if (mobileBackdrop) mobileBackdrop.classList.toggle('show', isShown);
                    document.body.style.overflow = isShown ? 'hidden' : '';
                }
            }
        } else if (closeBtn || isBackdrop) {
            var openMenu = document.querySelector('.navbar-collapse.show');
            if (openMenu) {
                openMenu.classList.remove('show');
                if (mobileBackdrop) mobileBackdrop.classList.remove('show');
                document.body.style.overflow = '';
                var activeToggler = document.querySelector('.navbar-toggler[aria-expanded="true"]');
                if (activeToggler) activeToggler.setAttribute('aria-expanded', 'false');
            }
        }
    });
    </script>
</body>
</html>

