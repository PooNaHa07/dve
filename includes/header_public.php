<?php
if (!isset($page_title)) $page_title = 'Work Hub';
require_once __DIR__ . '/functions.php';
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> - งานอาชีวศึกษาระบบทวิภาคี</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="manifest" href="/DVE_DATA_FULL/manifest.json">
    <style>
        * { box-sizing: border-box; }
        html { overflow-x: hidden; }
        body {
            font-family: 'Sarabun', sans-serif;
            background: #f5f7fa;
            color: #2c3e50;
            margin: 0;
            min-height: 100vh;
            overflow-x: hidden;
        }
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --secondary: #0ea5e9;
            --accent: #d946ef;
            --glass: rgba(255, 255, 255, 0.8);
            --glass-border: rgba(255, 255, 255, 0.4);
        }
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
        .landing-nav .navbar-brand {
            font-weight: 800;
            font-size: 1.4rem;
            color: var(--primary-dark) !important;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            letter-spacing: -0.5px;
            text-decoration: none;
        }
        .landing-nav .navbar-brand img { height: 40px; }
        .landing-nav .nav-link {
            color: #334155 !important;
            font-weight: 600;
            font-size: 0.95rem;
            padding: 0.5rem 1.25rem !important;
            border-radius: 8px;
            transition: all 0.2s;
        }
        .landing-nav .nav-link:hover {
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
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            white-space: nowrap;
        }
        .btn-nav-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 25px rgba(99, 102, 241, 0.3);
            color: white !important;
        }
        .landing-nav .btn-outline-danger { border-radius: 8px; font-weight: 500; }
        /* มือถือ: ปรับปุ่มแฮมเบอร์เกอร์และเมนู collapse ให้สวยงามพรีเมียม */
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

            /* ปรับปุ่มเข้าสู่ระบบ / PWA ในเมนูมือถือ */
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

            .landing-nav .btn-outline-danger {
                width: 100%;
                justify-content: center;
                padding: 12px !important;
                border-radius: 12px;
                font-size: 0.95rem;
                display: inline-flex;
                align-items: center;
                gap: 8px;
            }

            body { padding-bottom: 72px !important; }
        }
        /* Bottom navigation แบบแอป (มือถือเท่านั้น) */
        .bottom-nav {
            display: none;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: 64px;
            background: #fff;
            border-top: 1px solid #e8ecf1;
            box-shadow: 0 -2px 12px rgba(0,0,0,0.08);
            z-index: 1030;
            padding-bottom: env(safe-area-inset-bottom, 0);
        }
        @media (max-width: 991.98px) {
            .bottom-nav { display: flex; align-items: stretch; justify-content: space-around; }
        }
        .bottom-nav a {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 6px 4px;
            color: #64748b;
            text-decoration: none;
            font-size: 0.7rem;
            font-weight: 500;
            transition: color 0.2s;
        }
        .bottom-nav a i, .bottom-nav a .bi { font-size: 1.25rem; margin-bottom: 2px; }
        .bottom-nav a:hover, .bottom-nav a:active { color: #4a148c; }
        .bottom-nav a.active { color: #4a148c; }
        /* Modern Image Preview Modal */
        .event-img, .detail-img-container img, .news-img, .news-img-container img, .previewable-img {
            cursor: zoom-in !important;
            transition: opacity 0.2s;
        }
        .event-img:hover, .detail-img-container img:hover, .news-img:hover, .news-img-container img:hover, .previewable-img:hover {
            opacity: 0.95;
        }
        .image-preview-modal {
            display: none;
            position: fixed;
            z-index: 99999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(15, 23, 42, 0.9);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            opacity: 0;
            transition: opacity 0.3s cubic-bezier(0.165, 0.84, 0.44, 1);
            align-items: center;
            justify-content: center;
            flex-direction: column;
            padding: 2rem;
        }
        .image-preview-modal.show {
            display: flex;
            opacity: 1;
        }
        .image-preview-content {
            max-width: 95%;
            max-height: 82vh;
            object-fit: contain;
            border-radius: 20px;
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.6);
            transform: scale(0.92);
            transition: transform 0.3s cubic-bezier(0.165, 0.84, 0.44, 1);
            border: 2px solid rgba(255, 255, 255, 0.15);
        }
        .image-preview-modal.show .image-preview-content {
            transform: scale(1);
        }
        .image-preview-close {
            position: absolute;
            top: 2rem;
            right: 2rem;
            color: #f8fafc;
            font-size: 1.75rem;
            transition: all 0.2s;
            cursor: pointer;
            background: rgba(255,255,255,0.1);
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .image-preview-close:hover {
            color: white;
            background: rgba(255,255,255,0.25);
            transform: rotate(90deg);
        }
        .image-preview-caption {
            color: #f8fafc;
            padding-top: 1.5rem;
            font-weight: 600;
            font-size: 1.15rem;
            text-align: center;
        }
        @media (max-width: 768px) {
            .image-preview-modal {
                padding: 1rem;
            }
            .image-preview-content {
                max-width: 98%;
                max-height: 75vh;
            }
            .image-preview-close {
                top: 1rem;
                right: 1rem;
                width: 44px;
                height: 44px;
                font-size: 1.35rem;
            }
            .image-preview-caption {
                font-size: 1rem;
                padding-top: 1rem;
            }
        }
    </style>
</head>
<body>

<!-- Modern Image Preview Modal Markup -->
<div id="imgPreviewModal" class="image-preview-modal" onclick="closeImagePreview()">
    <span class="image-preview-close" onclick="closeImagePreview()"><i class="bi bi-x-lg"></i></span>
    <img class="image-preview-content" id="imgPreviewTarget" alt="Preview" onclick="event.stopPropagation()">
    <div class="image-preview-caption" id="imgPreviewCaption"></div>
</div>

<script>
function openImagePreview(src, alt = '') {
    const modal = document.getElementById('imgPreviewModal');
    const target = document.getElementById('imgPreviewTarget');
    const caption = document.getElementById('imgPreviewCaption');
    if (modal && target) {
        target.src = src;
        if (caption) caption.textContent = alt;
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
    }
}

function closeImagePreview() {
    const modal = document.getElementById('imgPreviewModal');
    if (modal) {
        modal.classList.remove('show');
        document.body.style.overflow = '';
    }
}

// Attach listeners automatically to previewable images
document.addEventListener('DOMContentLoaded', function() {
    document.addEventListener('click', function(e) {
        const img = e.target.closest('.event-img, .detail-img-container img, .news-img, .news-img-container img, .previewable-img');
        if (img) {
            openImagePreview(img.src, img.alt || 'ดูตัวอย่างรูปภาพ');
        }
    });
});
</script>

<nav class="navbar navbar-expand-lg landing-nav sticky-top">
    <div class="container">
        <a class="navbar-brand" href="<?= BASE_URL ?>/Landing-Page.php">
            <img src="<?= BASE_URL ?>/images/logo.png" alt="DVE | PBPVC">
            <span class="d-none d-sm-inline-block">DVE | PBPVC</span>
            <span class="d-inline-block d-sm-none">DVE</span>
        </a>
        
        <!-- Mobile Actions (visible only on screens < 992px) -->
        <div class="d-flex d-lg-none align-items-center gap-2 ms-auto me-2">
            <?php if (is_logged_in()): $u = current_user(); ?>
                <a href="<?= BASE_URL ?>/roles/<?= $u['role'] ?>.php" class="btn btn-sm btn-outline-primary px-3 rounded-pill fw-bold" style="font-size: 0.8rem;">
                    <i class="bi bi-speedometer2"></i> แดชบอร์ด
                </a>
            <?php else: ?>
                <a href="<?= BASE_URL ?>/login.php" class="btn btn-sm btn-nav-login py-1 px-3" style="font-size: 0.8rem; border-radius: 6px; box-shadow: none;">
                    <i class="bi bi-box-arrow-in-right"></i> เข้าสู่ระบบ
                </a>
            <?php endif; ?>
        </div>

        <button class="navbar-toggler" type="button" data-bs-toggle="custom-drawer" data-bs-target="#navbarPublic">
            <i class="bi bi-list fs-2 text-dark"></i>
        </button>
        <div class="collapse navbar-collapse" id="navbarPublic">
            <!-- Mobile Menu Header -->
            <div class="d-flex justify-content-between align-items-center w-100 mb-4 d-lg-none">
                <span class="fw-bold fs-5 text-dark" style="letter-spacing: -0.5px;">เมนูหลัก</span>
                <button type="button" class="btn btn-light rounded-circle p-2 d-flex align-items-center justify-content-center mobile-menu-close shadow-sm" style="width: 40px; height: 40px; border: 1px solid #e2e8f0;">
                    <i class="bi bi-x-lg text-secondary"></i>
                </button>
            </div>
            <ul class="navbar-nav mx-auto mb-2 mb-lg-0">
                <li class="nav-item"><a href="https://202.29.236.132/" target="_blank" rel="noopener noreferrer" class="nav-link d-flex align-items-center"><i class="bi bi-calendar-event me-2"></i>ปฏิทินกิจกรรม</a></li>
                <li class="nav-item"><a href="<?= BASE_URL ?>/news_list.php" class="nav-link d-flex align-items-center"><i class="bi bi-newspaper me-2"></i>ข่าวประชาสัมพันธ์</a></li>
                <li class="nav-item"><a href="<?= BASE_URL ?>/companies/list.php" class="nav-link d-flex align-items-center"><i class="bi bi-building me-2"></i>สถานประกอบการ</a></li>
                <li class="nav-item"><a href="<?= BASE_URL ?>/documents_list.php" class="nav-link d-flex align-items-center"><i class="bi bi-download me-2"></i>ดาวน์โหลด</a></li>
                <li class="nav-item"><a href="<?= BASE_URL ?>/contact.php" class="nav-link d-flex align-items-center"><i class="bi bi-chat-dots me-2"></i>ติดต่อสอบถาม</a></li>
            </ul>
            <div class="d-flex gap-3 align-items-center">
                <button id="pwaInstallBtnPublic" class="btn btn-outline-primary btn-sm px-3 rounded-pill fw-bold d-none align-items-center gap-1">
                    <i class="bi bi-download"></i> ติดตั้งแอป
                </button>
                <?php if (is_logged_in()): $u = current_user(); ?>
                    <span class="fw-bold text-dark d-none d-md-inline" style="font-size: 0.95rem;"><i class="bi bi-person-circle me-1"></i> <?= e($u['fullname']) ?></span>
                    <a href="<?= BASE_URL ?>/logout.php" class="btn btn-outline-danger btn-sm px-3 rounded-pill">ออกจากระบบ</a>
                <?php else: ?>
                    <a href="<?= BASE_URL ?>/login.php" class="btn btn-nav-login d-inline-flex align-items-center"><i class="bi bi-box-arrow-in-right me-2"></i>เข้าสู่ระบบ</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<nav class="bottom-nav d-lg-none" aria-label="เมนูหลัก">
    <a href="<?= BASE_URL ?>/Landing-Page.php" class="bottom-nav-item" title="หน้าหลัก"><i class="fas fa-home"></i><span>หน้าหลัก</span></a>
    <a href="https://202.29.236.132/" target="_blank" rel="noopener noreferrer" class="bottom-nav-item" title="ปฏิทินกิจกรรม"><i class="fas fa-calendar-alt"></i><span>ปฏิทิน</span></a>
    <a href="<?= BASE_URL ?>/news_list.php" class="bottom-nav-item" title="ข่าว"><i class="fas fa-newspaper"></i><span>ข่าว</span></a>
    <a href="<?= BASE_URL ?>/companies/list.php" class="bottom-nav-item" title="สถานประกอบการ"><i class="fas fa-building"></i><span>บริษัท</span></a>
    <a href="<?= BASE_URL ?>/documents_list.php" class="bottom-nav-item" title="ดาวน์โหลด"><i class="fas fa-download"></i><span>ดาวน์โหลด</span></a>
    <a href="<?= BASE_URL ?>/contact.php" class="bottom-nav-item" title="ติดต่อ"><i class="fas fa-envelope"></i><span>ติดต่อ</span></a>
</nav>
<script>
(function(){
    var path = window.location.pathname;
    document.querySelectorAll('.bottom-nav a').forEach(function(a){
        var href = a.getAttribute('href');
        if (href && (path === href || (path.indexOf(href) === 0 && path.length > href.length && path.charAt(href.length) === '/'))) a.classList.add('active');
    });

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
})();
</script>
