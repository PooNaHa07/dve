<?php if (isset($is_admin_context) && $is_admin_context): ?>
</div><!-- #adminContent -->

<!-- 📱 Mobile Sidebar Toggle -->
<button class="sb-mobile-toggle" id="sbMobileToggle" title="เปิด/ปิด Sidebar">
    <i class="bi bi-layout-sidebar-inset"></i>
</button>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const sidebar   = document.getElementById('adminSidebar');
    const toggle    = document.getElementById('sbToggle');
    const overlay   = document.getElementById('sbOverlay');
    const mobileBtn = document.getElementById('sbMobileToggle');

    if (localStorage.getItem('sb_collapsed') === '1' && sidebar) {
        sidebar.classList.add('collapsed');
    }

    if (toggle && sidebar) {
        toggle.addEventListener('click', function () {
            sidebar.classList.toggle('collapsed');
            localStorage.setItem('sb_collapsed', sidebar.classList.contains('collapsed') ? '1' : '0');
        });
    }

    function openMobileSidebar() {
        if (sidebar) sidebar.classList.add('mobile-open');
        if (overlay) overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    function closeMobileSidebar() {
        if (sidebar) sidebar.classList.remove('mobile-open');
        if (overlay) overlay.classList.remove('active');
        document.body.style.overflow = '';
    }
    if (mobileBtn) mobileBtn.addEventListener('click', openMobileSidebar);
    if (overlay)   overlay.addEventListener('click', closeMobileSidebar);
});
</script>
<?php endif; ?>

<div class="landing-waves">
        <svg class="waves" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"
        viewBox="0 24 150 28" preserveAspectRatio="none" shape-rendering="auto">
            <defs>
                <path id="gentle-wave" d="M-160 44c30 0 58-18 88-18s 58 18 88 18 58-18 88-18 58 18 88 18 v44h-352z" />
            </defs>
            <g class="parallax">
                <use xlink:href="#gentle-wave" x="48" y="0" fill="rgba(101, 66, 153, 0.6)" />
                <use xlink:href="#gentle-wave" x="48" y="3" fill="rgba(58, 100, 133, 0.5)" />
                <use xlink:href="#gentle-wave" x="48" y="7" fill="#ffffff" />
            </g>
        </svg>
    </div>

    <footer class="landing-footer">
        <div class="container">
            <div class="glass-card shadow-lg">
                <div class="row g-4 align-items-center">
                    
                    <div class="col-lg-5 text-center text-lg-start">
                        <div class="d-flex align-items-center justify-content-center justify-content-lg-start mb-3">
                            <div class="gradient-icon-box main-pulse">
                                <div class="icon-inner-glow">
                                    <i class="bi bi-mortarboard-fill"></i> 
                                </div>
                            </div>
                            <div class="ms-3 text-start">
                                <h4 class="fw-bold mb-1 text-dark"><?php echo e(get_setting('footer_college_name_1', 'งานอาชีวศึกษาระบบทวิภาคี')); ?></h4>
                                <h4 class="fw-bold mb-1 text-dark"><?php echo e(get_setting('footer_college_name_2', 'วิทยาลัยอาชีวศึกษาเพชรบุรี')); ?></h4>
                                <span class="badge rounded-pill status-badge">
                                    <i class="bi bi-book-half me-1"></i> WORK HUB
                                </span>
                            </div>
                        </div>
                        <p class="text-secondary slogan-text pe-lg-4 mb-0">
                            <?php echo get_setting('footer_slogan', "มุ่งเน้นความเป็นเลิศทางการศึกษาและทักษะวิชาชีพ <br>\nเพื่อพัฒนากำลังคนอาชีวศึกษาสู่ตลาดแรงงาน"); ?>
                        </p>
                    </div>

                    <div class="col-lg-2 text-center">
                        <div class="year-floating-card">
                            <div class="year-label">พุทธศักราช</div>
                            <div class="year-value"><?php echo date('Y') + 543; ?></div>
                        </div>
                    </div>

                    <div class="col-lg-5 text-center text-lg-end">
                        <div class="social-wrapper mb-3">
                            <a href="<?php echo e(get_setting('footer_fb_url', '#')); ?>" class="s-btn btn-fb" title="ติดตามข่าวสาร" target="_blank">
                                <i class="bi bi-facebook"></i>
                            </a>
                            <a href="<?php echo e(get_setting('footer_line_url', '#')); ?>" class="s-btn btn-line" title="สอบถามข้อมูล" target="_blank">
                                <i class="bi bi-line"></i>
                            </a>
                            <a href="<?php echo e(get_setting('footer_web_url', '#')); ?>" class="s-btn btn-web" title="คลังความรู้/ผลงาน" target="_blank">
                                <i class="bi bi-journal-bookmark-fill"></i>
                            </a>
                        </div>
                        <div class="credit-box">
                            <p class="mb-0 text-muted credit-text">
                                &copy; <?php echo date('Y'); ?> All Rights Reserved. 
                                <br>Success with <i class="bi bi-stars text-warning animate-beat"></i> by <b>DVE Tech Team</b>
                            </p>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </footer>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap');
        
        body { background-color: #ffffff; font-family: 'Sarabun', sans-serif; min-height: 100vh; display: flex; flex-direction: column; }

        /* Waves */
        .landing-waves { position: relative; width: 100%; height: 80px; margin-top: auto; }
        .waves { width: 100%; height: 100%; margin-bottom: -7px; }
        .parallax > use { animation: move-forever 25s cubic-bezier(.55,.5,.45,.5) infinite; }
        @keyframes move-forever { 0% { transform: translate3d(-90px,0,0); } 100% { transform: translate3d(85px,0,0); } }

        /* Footer Card */
        .landing-footer { background-color: #ffffff; padding-bottom: 3.5rem; }
        .glass-card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(15px);
            border-radius: 40px;
            padding: 50px 45px;
            border: 1px solid rgba(214, 199, 237, 0.5);
            box-shadow: 0 25px 60px rgba(214, 199, 237, 0.3) !important;
        }

        /* ปรับขนาดชื่อวิทยาลัย */
        h4.fw-bold { font-size: 1.6rem; letter-spacing: -0.5px; }
        
        /* ปรับขนาดคำขวัญ */
        .slogan-text { font-size: 1.05rem; line-height: 1.6; font-weight: 400; }

        /* ไอคอนหลัก */
        .gradient-icon-box {
            width: 70px; height: 70px; 
            background: linear-gradient(135deg, #D6C7ED 0%, #D8E9F6 100%);
            border-radius: 22px; display: flex; align-items: center;
            justify-content: center; color: #553c9a; font-size: 2.2rem;
            box-shadow: 0 10px 25px rgba(214, 199, 237, 0.6);
        }
        .icon-inner-glow { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; border-radius: inherit; background: rgba(255, 255, 255, 0.2); border: 1px solid rgba(255, 255, 255, 0.5); }

        /* ปี พ.ศ. (ขนาดใหญ่ขึ้นเป็นพิเศษ) */
        .year-floating-card { 
            background: #ffffff; 
            border: 2.5px solid #D8E9F6; 
            padding: 10px 20px; 
            border-radius: 20px; 
            display: inline-block;
            box-shadow: 0 10px 20px rgba(216, 233, 246, 0.4);
        }
        .year-label { font-size: 0.85rem; color: #000000; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 4px; }
        .year-value { font-size: 2.4rem; font-weight: 800; color: #000000; line-height: 1; }

        /* ปุ่มโซเชียล */
        .s-btn {
            width: 55px; height: 55px; display: inline-flex;
            align-items: center; justify-content: center;
            background: #ffffff; border-radius: 18px;
            margin: 0 10px; transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            text-decoration: none; border: 1px solid #f1f5f9;
            box-shadow: 0 6px 15px rgba(0,0,0,0.05);
            font-size: 1.5rem;
        }
        .s-btn:hover { transform: translateY(-10px) scale(1.1); color: #fff; border-color: transparent; }
        .btn-fb { color: #553c9a; } .btn-fb:hover { background: #6b46c1; }
        .btn-line { color: #28a745; } .btn-line:hover { background: #28a745; }
        .btn-web { color: #0d6efd; } .btn-web:hover { background: #0d6efd; }

        /* ป้ายสถานะ */
        .status-badge { background-color: #f3f0ff; color: #6b46c1; font-size: 0.85rem; font-weight: 700; padding: 8px 20px; border: 1px solid #e9d8fd; }
        
        /* เครดิตด้านล่าง */
        .credit-text { font-size: 0.95rem; line-height: 1.5; }

        /* Animations */
        .main-pulse { animation: soft-pulse 3s infinite; }
        @keyframes soft-pulse { 0% { box-shadow: 0 0 0 0 rgba(214, 199, 237, 0.7); } 70% { box-shadow: 0 0 0 20px rgba(214, 199, 237, 0); } 100% { box-shadow: 0 0 0 0 rgba(214, 199, 237, 0); } }
        .animate-beat { animation: heartbeat 1.5s infinite; display: inline-block; }
        @keyframes heartbeat {
            0% { transform: scale(1); }
            50% { transform: scale(1.15); }
            100% { transform: scale(1); }
        }
    </style>
    <!-- Floating PWA Install Banner (Sleek Glassmorphic Design) -->
    <div id="pwaInstallBanner" class="pwa-install-banner shadow-lg d-none animate-slide-up" style="position: fixed; bottom: 85px; right: 24px; z-index: 1045; background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(15px); -webkit-backdrop-filter: blur(15px); border: 1px solid rgba(99, 102, 241, 0.2); border-radius: 16px; padding: 16px; width: 340px; max-width: calc(100vw - 32px); transition: all 0.3s ease;">
        <div class="d-flex align-items-start gap-3">
            <div class="bg-primary bg-opacity-10 p-2 rounded-3 text-primary d-flex align-items-center justify-content-center" style="width: 42px; height: 42px; min-width: 42px; background-color: rgba(99, 102, 241, 0.1) !important;">
                <i class="bi bi-phone-vibrate fs-3 text-primary" style="color: #6366f1 !important;"></i>
            </div>
            <div class="flex-grow-1" style="min-width: 0;">
                <h6 class="mb-1 fw-bold text-dark" style="font-size: 0.92rem; font-family: 'Sarabun', sans-serif;">ติดตั้งแอป DVE WORK HUB</h6>
                <p class="text-secondary mb-2" style="font-size: 0.78rem; line-height: 1.35; font-family: 'Sarabun', sans-serif;">เพิ่มระบบเข้าหน้าจอโฮมของคุณเพื่อเข้าใช้งานด่วนในคลิกเดียว สะดวก รวดเร็ว ไม่ต้องเปิดกูเกิล!</p>
                <div class="d-flex gap-2">
                    <button id="pwaInstallBtnBanner" class="btn btn-sm btn-primary fw-bold px-3 py-1.5" style="font-size: 0.78rem; border-radius: 8px; background-color: #6366f1 !important; border-color: #6366f1 !important;">
                        ติดตั้งแอป
                    </button>
                    <button id="pwaCloseBannerBtn" class="btn btn-sm btn-light border fw-bold text-secondary px-3 py-1.5" style="font-size: 0.78rem; border-radius: 8px;">
                        ไว้ทีหลัง
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal คู่มือการติดตั้งแอป DVE WORK HUB (Premium Instruction Modal) -->
    <div class="modal fade" id="pwaInstructionModal" tabindex="-1" aria-labelledby="pwaInstructionModalLabel" aria-hidden="true" style="z-index: 1060;">
        <div class="modal-dialog modal-dialog-centered" style="max-width: 380px;">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 24px; background: rgba(255, 255, 255, 0.98); backdrop-filter: blur(15px); -webkit-backdrop-filter: blur(15px); border: 1px solid rgba(255,255,255,0.8) !important;">
                <div class="modal-header border-0 pb-0 position-relative">
                    <h5 class="modal-title fw-bold text-dark w-100 text-center pt-3" id="pwaInstructionModalLabel" style="font-family: 'Sarabun', sans-serif; font-size: 1.2rem;">
                        <i class="bi bi-download text-primary me-2" style="color: #6366f1 !important;"></i>ติดตั้งแอป DVE WORK HUB
                    </h5>
                    <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close" style="position: absolute; right: 20px; top: 20px; font-size: 0.8rem;"></button>
                </div>
                <div class="modal-body p-4 text-center">
                    <!-- OS Icon & Heading -->
                    <div class="d-flex justify-content-center mb-3">
                        <div id="pwaOsIconWrapper" class="p-3 rounded-circle d-flex align-items-center justify-content-center" style="width: 72px; height: 72px; transition: all 0.3s ease;">
                            <i id="pwaOsIcon" class="bi bi-phone fs-1"></i>
                        </div>
                    </div>
                    
                    <h6 id="pwaOsTitle" class="fw-bold mb-3 text-dark" style="font-size: 1.02rem; font-family: 'Sarabun', sans-serif;">วิธีกดติดตั้งบนโทรศัพท์ของคุณ</h6>
                    
                    <!-- Steps List -->
                    <div class="text-start p-3 border" style="border-radius: 16px; background-color: #f8fafc; border-color: rgba(99, 102, 241, 0.15) !important;">
                        <div id="pwaInstructionsContent" class="small text-secondary" style="font-family: 'Sarabun', sans-serif; line-height: 1.6; font-size: 0.85rem;">
                            <!-- Dynamic Content -->
                        </div>
                    </div>
                    
                    <p class="text-muted mt-3 mb-0" style="font-size: 0.72rem; line-height: 1.45; font-family: 'Sarabun', sans-serif;">
                        * เมื่อเพิ่มลงในหน้าจอหลักแล้ว คุณจะสามารถกดเปิดใช้งานระบบได้เสมือนแอปพลิเคชันมือถือทั่วไป โดยไม่ต้องพิมพ์ชื่อเว็บในกูเกิลอีกต่อไป
                    </p>
                </div>
                <div class="modal-footer border-0 pt-0 pb-4 justify-content-center">
                    <button type="button" class="btn btn-primary fw-bold px-4 py-2 w-100 mx-3 shadow-sm" data-bs-dismiss="modal" style="border-radius: 12px; background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%) !important; border: none !important; font-size: 0.88rem;">
                        รับทราบ
                    </button>
                </div>
            </div>
        </div>
    </div>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script>
    if ('serviceWorker' in navigator) {
      window.addEventListener('load', () => {
        navigator.serviceWorker.register('/DVE_DATA_FULL/sw.js')
          .then(reg => console.log('PWA Service Worker registered!', reg.scope))
          .catch(err => console.error('PWA Service Worker registration failed:', err));
      });
    }

    // PWA Custom Installation Script with Fallback Instructions for Mobile
    let deferredPrompt;
    const pwaBanner = document.getElementById('pwaInstallBanner');
    const pwaBtnBanner = document.getElementById('pwaInstallBtnBanner');
    const pwaCloseBtn = document.getElementById('pwaCloseBannerBtn');
    const pwaBtnPublic = document.getElementById('pwaInstallBtnPublic');
    const pwaInstallItem = document.getElementById('pwaInstallItem');
    const pwaBtnDropdown = document.getElementById('pwaInstallBtn');

    // Check if the app is already installed or running in Standalone (App) Mode
    const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;

    // Initialize installation UI triggers if not already installed
    if (!isStandalone) {
        // Show public header install button
        if (pwaBtnPublic) {
            pwaBtnPublic.classList.remove('d-none');
            pwaBtnPublic.classList.add('d-inline-flex');
        }
        
        // Show private header dropdown item
        if (pwaInstallItem) {
            pwaInstallItem.style.setProperty('display', 'block', 'important');
        }

        // Show banner after a short delay if not dismissed in this session
        if (!sessionStorage.getItem('pwaPromptDismissed')) {
            setTimeout(() => {
                if (pwaBanner) {
                    pwaBanner.classList.remove('d-none');
                    pwaBanner.classList.add('d-block');
                }
            }, 1800);
        }
    }

    window.addEventListener('beforeinstallprompt', (e) => {
        // Prevent Chrome 67 and earlier from automatically showing the prompt
        e.preventDefault();
        // Stash the event so it can be triggered later.
        deferredPrompt = e;
        
        // If not running standalone and not dismissed, ensure banner is displayed
        if (!isStandalone && !sessionStorage.getItem('pwaPromptDismissed')) {
            if (pwaBanner) {
                pwaBanner.classList.remove('d-none');
                pwaBanner.classList.add('d-block');
            }
        }
    });

    function triggerPwaInstall() {
        if (deferredPrompt) {
            deferredPrompt.prompt();
            deferredPrompt.userChoice.then((choiceResult) => {
                if (choiceResult.outcome === 'accepted') {
                    console.log('User accepted the PWA install prompt');
                    hidePwaUi();
                } else {
                    console.log('User dismissed the PWA install prompt');
                }
                deferredPrompt = null;
            });
        } else {
            // Fallback: Show premium instruction modal for devices/environments without native prompts (iOS Safari, HTTP mobile)
            showPwaInstructions();
        }
    }

    function showPwaInstructions() {
        const userAgent = navigator.userAgent || navigator.vendor || window.opera;
        const isIOS = /iPad|iPhone|iPod/.test(userAgent) && !window.MSStream;
        const isAndroid = /Android/i.test(userAgent);
        
        const osIcon = document.getElementById('pwaOsIcon');
        const osTitle = document.getElementById('pwaOsTitle');
        const instructionsContent = document.getElementById('pwaInstructionsContent');
        const iconWrapper = document.getElementById('pwaOsIconWrapper');
        
        if (isIOS) {
            // iOS Safari instructions
            if (osIcon) {
                osIcon.className = 'bi bi-apple fs-1';
                osIcon.style.color = '#000000';
            }
            if (iconWrapper) {
                iconWrapper.style.backgroundColor = 'rgba(0, 0, 0, 0.07)';
            }
            if (osTitle) osTitle.textContent = 'วิธีกดติดตั้งบน iPhone / iPad';
            if (instructionsContent) {
                instructionsContent.innerHTML = `
                    <div class="mb-3 d-flex align-items-start gap-2">
                        <span class="badge rounded-circle px-2 py-1" style="background-color:#6366f1; min-width: 22px; text-align: center;">1</span>
                        <div>เปิดหน้าเว็บนี้โดยใช้งานเบราว์เซอร์ <strong>Safari</strong></div>
                    </div>
                    <div class="mb-3 d-flex align-items-start gap-2">
                        <span class="badge rounded-circle px-2 py-1" style="background-color:#6366f1; min-width: 22px; text-align: center;">2</span>
                        <div>กดปุ่ม <strong>แชร์ (Share)</strong> <i class="bi bi-share text-primary fw-bold" style="color: #6366f1 !important;"></i> ด้านล่างของจอภาพ</div>
                    </div>
                    <div class="mb-0 d-flex align-items-start gap-2">
                        <span class="badge rounded-circle px-2 py-1" style="background-color:#6366f1; min-width: 22px; text-align: center;">3</span>
                        <div>เลื่อนลงด้านล่างแล้วกดเลือก <strong>"เพิ่มไปยังหน้าจอโฮม" (Add to Home Screen)</strong> <i class="bi bi-plus-square text-primary fw-bold" style="color: #6366f1 !important;"></i> แล้วเลือก <strong>"เพิ่ม" (Add)</strong> ที่มุมบนขวา</div>
                    </div>
                `;
            }
        } else if (isAndroid) {
            // Android Chrome instructions
            if (osIcon) {
                osIcon.className = 'bi bi-android2 fs-1';
                osIcon.style.color = '#3ddc84';
            }
            if (iconWrapper) {
                iconWrapper.style.backgroundColor = 'rgba(61, 220, 132, 0.12)';
            }
            if (osTitle) osTitle.textContent = 'วิธีกดติดตั้งบนสมาร์ทโฟน Android';
            if (instructionsContent) {
                instructionsContent.innerHTML = `
                    <div class="mb-3 d-flex align-items-start gap-2">
                        <span class="badge rounded-circle px-2 py-1" style="background-color:#6366f1; min-width: 22px; text-align: center;">1</span>
                        <div>เปิดหน้าเว็บนี้ด้วยแอป <strong>Google Chrome</strong></div>
                    </div>
                    <div class="mb-3 d-flex align-items-start gap-2">
                        <span class="badge rounded-circle px-2 py-1" style="background-color:#6366f1; min-width: 22px; text-align: center;">2</span>
                        <div>กดปุ่ม <strong>เมนูตัวเลือกสามจุด (More)</strong> <i class="bi bi-three-dots-vertical text-primary fw-bold" style="color: #6366f1 !important;"></i> บริเวณขวาบนสุด</div>
                    </div>
                    <div class="mb-0 d-flex align-items-start gap-2">
                        <span class="badge rounded-circle px-2 py-1" style="background-color:#6366f1; min-width: 22px; text-align: center;">3</span>
                        <div>เลือกที่รายการ <strong>"ติดตั้งแอป" (Install app)</strong> หรือ <strong>"เพิ่มลงในหน้าจอหลัก" (Add to Home screen)</strong> <i class="bi bi-plus-square text-primary fw-bold" style="color: #6366f1 !important;"></i></div>
                    </div>
                `;
            }
        } else {
            // Desktop/General instructions
            if (osIcon) {
                osIcon.className = 'bi bi-laptop fs-1';
                osIcon.style.color = '#6366f1';
            }
            if (iconWrapper) {
                iconWrapper.style.backgroundColor = 'rgba(99, 102, 241, 0.08)';
            }
            if (osTitle) osTitle.textContent = 'วิธีกดติดตั้งบนคอมพิวเตอร์';
            if (instructionsContent) {
                instructionsContent.innerHTML = `
                    <div class="mb-3 d-flex align-items-start gap-2">
                        <span class="badge rounded-circle px-2 py-1" style="background-color:#6366f1; min-width: 22px; text-align: center;">1</span>
                        <div>กรุณาใช้งานเบราว์เซอร์ <strong>Google Chrome</strong> หรือ <strong>Microsoft Edge</strong></div>
                    </div>
                    <div class="mb-3 d-flex align-items-start gap-2">
                        <span class="badge rounded-circle px-2 py-1" style="background-color:#6366f1; min-width: 22px; text-align: center;">2</span>
                        <div>สังเกตปุ่ม **ติดตั้งแอป DVE** <i class="bi bi-download text-primary fw-bold" style="color: #6366f1 !important;"></i> ที่แถบพิมพ์ลิงก์ (Address Bar) ด้านบนสุด</div>
                    </div>
                    <div class="mb-0 d-flex align-items-start gap-2">
                        <span class="badge rounded-circle px-2 py-1" style="background-color:#6366f1; min-width: 22px; text-align: center;">3</span>
                        <div>หรือกดปุ่มเมนูสามจุดขวาบนของบราวเซอร์ เลือก <strong>"บันทึกและแชร์" (Save and share)</strong> จากนั้นเลือก <strong>"ติดตั้งแอป DVE WORK HUB"</strong></div>
                    </div>
                `;
            }
        }
        
        // Show bootstrap modal
        const myModal = new bootstrap.Modal(document.getElementById('pwaInstructionModal'));
        myModal.show();
    }

    function hidePwaUi() {
        if (pwaBanner) {
            pwaBanner.classList.remove('d-block');
            pwaBanner.classList.add('d-none');
        }
        if (pwaBtnPublic) {
            pwaBtnPublic.classList.remove('d-inline-flex');
            pwaBtnPublic.classList.add('d-none');
        }
        if (pwaInstallItem) {
            pwaInstallItem.style.setProperty('display', 'none', 'important');
        }
    }

    if (pwaBtnBanner) pwaBtnBanner.addEventListener('click', triggerPwaInstall);
    if (pwaBtnPublic) pwaBtnPublic.addEventListener('click', triggerPwaInstall);
    if (pwaBtnDropdown) {
        pwaBtnDropdown.addEventListener('click', (e) => {
            e.preventDefault();
            triggerPwaInstall();
        });
    }

    if (pwaCloseBtn) {
        pwaCloseBtn.addEventListener('click', () => {
            hidePwaUi();
            sessionStorage.setItem('pwaPromptDismissed', 'true');
        });
    }

    window.addEventListener('appinstalled', (evt) => {
        console.log('PWA Installed successfully');
        hidePwaUi();
    });
    </script>
    <script src="/DVE_DATA_FULL/assets/js/scroll-to-top.js"></script>
</body>
</html>