<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php';
require_login();
require_role(['supervisor']);

$u             = current_user();
$supervisor_id = (int)$u['id'];
$curr          = $u;

// บันทึกข้อมูลโปรไฟล์
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $pass_err = 'คำขอไม่ถูกต้องหรือเซสชันหมดอายุ กรุณารีเฟรชหน้าแล้วลองใหม่อีกครั้ง';
    } else {
        $fullname        = trim($_POST['fullname']        ?? '');
        $phone           = trim($_POST['phone']           ?? '');
        $email           = trim($_POST['email']           ?? '');
        $affiliation     = trim($_POST['affiliation']     ?? '');
        $company_name    = trim($_POST['company_name']    ?? '');
        $company_address = trim($_POST['company_address'] ?? '');
        $company_phone   = trim($_POST['company_phone']   ?? '');
        $company_manager = trim($_POST['company_manager'] ?? '');
        $company_id_fk   = isset($_POST['company_id']) ? (int)$_POST['company_id'] : 0;
        if ($company_id_fk <= 0) $company_id_fk = null;
        $branch_id_fk    = isset($_POST['branch_id']) ? (int)$_POST['branch_id'] : 0;
        if ($branch_id_fk <= 0) $branch_id_fk = null;
        $profile_image   = $curr['profile_image'];

        // Validation
        if (empty($fullname)) {
            $pass_err = 'กรุณากรอกชื่อ-นามสกุล';
        } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $pass_err = 'รูปแบบอีเมลไม่ถูกต้อง';
        } else {
            // Check email uniqueness if changed
            if (!empty($email) && $email !== $curr['email']) {
                $chk_email = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
                $chk_email->bind_param("si", $email, $supervisor_id);
                $chk_email->execute();
                if ($chk_email->get_result()->num_rows > 0) {
                    $pass_err = 'อีเมลนี้ถูกใช้งานแล้วโดยผู้ใช้อื่นในระบบ';
                }
                $chk_email->close();
            }

            if (!isset($pass_err)) {
                if (!empty($_POST['captured_image_base64'])) {
                    $img_str = $_POST['captured_image_base64'];
                    if (preg_match('/^data:image\/(\w+);base64,/', $img_str, $type)) {
                        $data = base64_decode(substr($img_str, strpos($img_str, ',') + 1));
                        if ($data !== false && strlen($data) <= 10 * 1024 * 1024) {
                            // Security check: Ensure decoded string is a valid image
                            $img_info = @getimagesizefromstring($data);
                            if ($img_info !== false) {
                                $file_name  = 'avatar_supervisor_' . uniqid() . '.jpg';
                                $upload_dir = __DIR__ . '/../uploads/avatars/';
                                if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                                if (file_put_contents($upload_dir . $file_name, $data)) {
                                    replace_old_avatar($curr['profile_image']);
                                    $profile_image = $file_name;
                                }
                            } else {
                                $pass_err = 'รูปภาพที่ถ่ายไม่ถูกต้อง กรุณาลองถ่ายใหม่อีกครั้ง';
                            }
                        }
                    }
                } elseif (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
                    $uploaded = upload_file($_FILES['profile_image'], ['jpg','jpeg','png','webp','gif'], __DIR__ . '/../uploads/avatars', 10*1024*1024);
                    if ($uploaded !== null) {
                        replace_old_avatar($curr['profile_image']);
                        $profile_image = $uploaded;
                    } else {
                        $pass_err = 'ไม่สามารถอัปโหลดไฟล์รูปภาพได้ กรุณาตรวจสอบชนิดไฟล์ (JPG, PNG, WEBP) และขนาดต้องไม่เกิน 10MB';
                    }
                } elseif (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $pass_err = 'เกิดข้อผิดพลาดในการอัปโหลดรูปภาพ (โค้ดข้อผิดพลาด: ' . $_FILES['profile_image']['error'] . ')';
                }

                if (!isset($pass_err)) {
                    $stmt = $conn->prepare("UPDATE users SET fullname=?, phone=?, email=?, affiliation=?, company_id=?, branch_id=?, company_name=?, company_address=?, company_phone=?, company_manager=?, profile_image=? WHERE id=?");
                    $stmt->bind_param("ssssiisssssi", $fullname, $phone, $email, $affiliation, $company_id_fk, $branch_id_fk, $company_name, $company_address, $company_phone, $company_manager, $profile_image, $supervisor_id);
                    if ($stmt->execute()) {
                        $curr = current_user(true);
                        $show_success_alert = true;
                        log_audit_action('supervisor_update_profile', "Supervisor #{$supervisor_id} updated profile info");
                    }
                    $stmt->close();
                }
            }
        }
    }
}

// เปลี่ยนรหัสผ่าน
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    if (!verify_csrf($_POST['csrf_token'] ?? null)) {
        $pass_err = 'คำขอไม่ถูกต้องหรือเซสชันหมดอายุ กรุณารีเฟรชหน้าแล้วลองใหม่อีกครั้ง';
    } else {
        $current_pass = $_POST['current_password'] ?? '';
        $new_pass     = $_POST['new_password']     ?? '';
        $confirm_pass = $_POST['confirm_password'] ?? '';

        if (password_verify($current_pass, $curr['password'])) {
            if (strlen($new_pass) < 6) {
                $pass_err = 'รหัสผ่านใหม่ต้องมีความยาวอย่างน้อย 6 ตัวอักษร';
            } elseif ($new_pass !== $confirm_pass) {
                $pass_err = 'รหัสผ่านใหม่และการยืนยันไม่ตรงกัน';
            } else {
                $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
                $stmt   = $conn->prepare("UPDATE users SET password=? WHERE id=?");
                $stmt->bind_param("si", $hashed, $supervisor_id);
                if ($stmt->execute()) {
                    $pass_success = 'เปลี่ยนรหัสผ่านเรียบร้อยแล้ว';
                    log_audit_action('supervisor_change_password', "Supervisor #{$supervisor_id} changed password");
                } else {
                    $pass_err = 'เกิดข้อผิดพลาดในการบันทึกรหัสผ่าน';
                }
                $stmt->close();
            }
        } else {
            $pass_err = 'รหัสผ่านปัจจุบันไม่ถูกต้อง';
        }
    }
}

$hide_welcome = true;
$page_title   = 'โปรไฟล์ผู้ดูแลการฝึกงาน';
include __DIR__ . '/../includes/header.php';
?>
<style>
.sv-profile-banner {
    background: linear-gradient(135deg, #0c4a6e 0%, #0ea5e9 50%, #14b8a6 100%);
    border-radius: 24px;
    position: relative;
    overflow: hidden;
    margin-bottom: -30px;
    box-shadow: 0 15px 35px rgba(14,165,233,.3);
}
.avatar-wrapper-premium {
    width: 120px; height: 120px;
    background: rgba(255,255,255,.15);
    backdrop-filter: blur(10px);
    border: 4px solid rgba(255,255,255,.8);
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 10px 25px rgba(0,0,0,.15);
    overflow: hidden;
    transition: all .4s cubic-bezier(.175,.885,.32,1.275);
}
.input-group-premium { position: relative; display: flex; align-items: center; }
.input-group-premium .form-control {
    padding-left: 3rem !important;
    border-radius: 1.25rem;
    background: rgba(255,255,255,.65);
    backdrop-filter: blur(5px);
    border: 1px solid rgba(0,0,0,.08);
    transition: all .3s ease;
}
.input-icon { position: absolute; left: 1.25rem; color: #0ea5e9; font-size: 1.2rem; z-index: 5; }
.btn-sv-submit {
    background: linear-gradient(135deg,#0ea5e9 0%,#14b8a6 100%);
    border: none; border-radius: 1.5rem; padding: 1rem 3rem;
    font-weight: 800; color: white;
    box-shadow: 0 10px 20px rgba(14,165,233,.3);
    transition: all .3s;
}
.btn-sv-submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 15px 30px rgba(20,184,166,.4);
    color: white;
}
.webcam-overlay { display:none; position:fixed; top:0;left:0;width:100%;height:100%; background:rgba(0,0,0,.85); backdrop-filter:blur(15px); z-index:10000; align-items:center; justify-content:center; }
.webcam-overlay.show { display:flex; }
.webcam-window { background:#fff; border-radius:2rem; width:90%; max-width:480px; padding:1.5rem; text-align:center; }
.webcam-preview-container { width:100%; aspect-ratio:3/4; border-radius:1.5rem; overflow:hidden; background:#000; margin-bottom:1.5rem; }
#webcam-video,#webcam-canvas { width:100%; height:100%; object-fit:cover; transform:scaleX(-1); }
</style>

<?php 
  $avatar_filename = $curr['profile_image'] ?? '';
  $has_avatar_file = !empty($avatar_filename) && file_exists(__DIR__ . '/../uploads/avatars/' . $avatar_filename);
?>
<div class="container py-5">
    <div class="mb-4">
        <a href="/DVE_DATA_FULL/roles/supervisor.php" class="btn btn-link text-decoration-none p-0 fw-bold text-secondary">
            <i class="bi bi-arrow-left-circle-fill fs-4 me-2"></i> กลับหน้าหลัก
        </a>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-10 col-xl-9">
            <!-- Banner -->
            <div class="sv-profile-banner shadow-lg p-4 p-md-5 text-white mb-4 animate-fade-up animated-gradient-bg">
                <div class="d-flex flex-column flex-md-row align-items-center gap-4">
                    <div class="avatar-wrapper-premium hover-lift">
                        <?php if ($has_avatar_file): ?>
                            <img src="<?= BASE_URL ?>/uploads/avatars/<?= htmlspecialchars($avatar_filename) ?>?v=<?= time() ?>"
                                 style="width:100%;height:100%;object-fit:cover;"
                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <div style="display:none;" class="w-100 h-100 align-items-center justify-content-center">
                                <i class="bi bi-person-workspace fs-1 text-white"></i>
                            </div>
                        <?php else: ?>
                            <i class="bi bi-person-workspace fs-1 text-white"></i>
                        <?php endif; ?>
                    </div>
                    <div class="text-center text-md-start">
                        <span class="badge bg-white shadow-sm px-3 py-1.5 rounded-pill mb-2 fw-bold small animate-fade-in" style="color:#000000 !important; background:#ffffff !important;">Supervisor Profile</span>
                        <h2 class="fw-extrabold mb-1 text-white"><?= e($curr['fullname']) ?></h2>
                        <p class="mb-1 text-white-50"><i class="bi bi-person-workspace me-1"></i> ผู้ดูแลการฝึกงาน</p>
                        <?php if (!empty($curr['company_name'])): ?>
                            <div class="mt-2">
                                <span class="badge bg-white shadow-sm px-3.5 py-2 rounded-pill fw-bold animate-fade-in delay-1" style="color:#000000 !important; background:#ffffff !important;">
                                    <i class="bi bi-building text-primary me-1"></i> <?= e($curr['company_name']) ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Profile Form -->
            <div class="p-4 p-md-5 mt-3 shadow-sm animate-fade-up delay-1" style="background:white;border-radius:2rem;">
                <h4 class="fw-bold mb-4 text-center">ข้อมูลส่วนตัว</h4>
                <form id="profileForm" method="POST" enctype="multipart/form-data">
                    <?php csrf_field(); ?>
                    <!-- Avatar Upload -->
                    <div class="row align-items-center mb-5 p-4 rounded-4" style="background:rgba(14,165,233,.03);border:1px dashed rgba(14,165,233,.2);">
                        <div class="col-md-3 text-center mb-3 mb-md-0">
                            <div style="width:100px;height:100px;border-radius:50%;overflow:hidden;border:3px solid white;box-shadow:0 5px 15px rgba(0,0,0,.1);margin:0 auto;position:relative;">
                                <img id="avatar-preview"
                                     src="<?= $has_avatar_file ? BASE_URL.'/uploads/avatars/'.$avatar_filename.'?v='.time() : '' ?>"
                                     style="width:100%;height:100%;object-fit:cover;<?= $has_avatar_file ? '' : 'display:none;' ?>"
                                     onerror="this.style.display='none'; document.getElementById('avatar-placeholder').style.display='flex';">
                                <div id="avatar-placeholder" class="h-100 d-flex align-items-center justify-content-center bg-light"
                                     style="<?= $has_avatar_file ? 'display:none;' : '' ?>">
                                    <i class="bi bi-person-fill fs-1 text-secondary"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-9 text-center text-md-start">
                            <h6 class="fw-bold mb-1"><i class="bi bi-camera-fill me-1"></i> เปลี่ยนรูปโปรไฟล์</h6>
                            <p class="text-muted small mb-3">แนะนำไฟล์สี่เหลี่ยมจัตุรัส ขนาดไม่เกิน 10MB</p>
                            <div class="d-flex flex-wrap gap-2 align-items-center justify-content-center justify-content-md-start">
                                <input type="file" name="profile_image" id="profile-image-input"
                                       class="form-control form-control-sm w-auto rounded-pill" accept="image/*"
                                       onchange="previewImage(this)">
                                <button type="button" class="btn btn-sm btn-primary rounded-pill px-3" onclick="openWebcam()">ถ่ายภาพ</button>
                                <input type="hidden" name="captured_image_base64" id="captured-image-base64">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <label class="form-label small fw-bold text-primary">ชื่อ-นามสกุล</label>
                            <div class="input-group-premium">
                                <i class="bi bi-person input-icon"></i>
                                <input type="text" name="fullname" class="form-control form-control-lg"
                                       value="<?= e($curr['fullname']) ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="form-label small fw-bold text-primary">อีเมล</label>
                            <div class="input-group-premium">
                                <i class="bi bi-envelope input-icon"></i>
                                <input type="email" name="email" class="form-control form-control-lg"
                                       value="<?= e($curr['email']) ?>">
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="form-label small fw-bold text-primary">เบอร์โทรศัพท์</label>
                            <div class="input-group-premium">
                                <i class="bi bi-telephone input-icon"></i>
                                <input type="text" name="phone" class="form-control form-control-lg"
                                       value="<?= e($curr['phone'] ?? '') ?>" placeholder="เช่น 081-234-5678">
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="form-label small fw-bold text-primary">แผนก / หน่วยงาน</label>
                            <div class="input-group-premium">
                                <i class="bi bi-building input-icon"></i>
                                <input type="text" name="affiliation" class="form-control form-control-lg"
                                       value="<?= e($curr['affiliation'] ?? '') ?>" placeholder="เช่น งานทวิภาคี">
                            </div>
                        </div>
                    </div>

                    <!-- Workplace / Establishment Section -->
                    <div class="p-4 rounded-4 mb-4" style="background:rgba(14,165,233,.04);border:1px solid rgba(14,165,233,.18);">
                        <h6 class="fw-bold mb-3 text-primary d-flex align-items-center gap-2">
                            <i class="bi bi-building-gear fs-5 text-info"></i> สถานที่ฝึกงาน / สถานประกอบการที่ท่านสังกัด
                        </h6>
                        <input type="hidden" name="company_id" id="supervisor_company_id" value="<?= e($curr['company_id'] ?? 0) ?>">
                        <input type="hidden" name="branch_id" id="supervisor_branch_id" value="<?= e($curr['branch_id'] ?? 0) ?>">

                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label small fw-bold text-dark">ชื่อสถานที่ฝึกงาน / สถานประกอบการ</label>
                                <div class="position-relative">
                                    <div class="input-group-premium">
                                        <i class="bi bi-search input-icon"></i>
                                        <input type="text" name="company_name" id="supervisor_company_name" class="form-control form-control-lg"
                                               value="<?= e($curr['company_name'] ?? '') ?>" placeholder="พิมพ์เพื่อค้นหาหรือเลือกสถานประกอบการ..." autocomplete="off">
                                    </div>
                                    <div id="company_dropdown" class="dropdown-menu shadow-lg w-100 border-0 rounded-4 mt-1 p-2" style="display:none; max-height:260px; overflow-y:auto; z-index:1050;"></div>
                                </div>
                                <span class="form-text text-muted small ms-1"><i class="bi bi-info-circle me-1"></i> ท่านสามารถพิมพ์ค้นหาจากสถานประกอบการที่มีในระบบ หรือพิมพ์ระบุเองได้</span>
                            </div>

                            <div class="col-md-12 mb-3">
                                <label class="form-label small fw-bold text-dark">ที่อยู่สถานประกอบการ</label>
                                <div class="input-group-premium">
                                    <i class="bi bi-geo-alt input-icon"></i>
                                    <input type="text" name="company_address" id="supervisor_company_address" class="form-control form-control-lg"
                                           value="<?= e($curr['company_address'] ?? '') ?>" placeholder="เลขที่ ถนน ตำบล อำเภอ จังหวัด">
                                </div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label small fw-bold text-dark">เบอร์โทรศัพท์สถานประกอบการ</label>
                                <div class="input-group-premium">
                                    <i class="bi bi-telephone-outbound input-icon"></i>
                                    <input type="text" name="company_phone" id="supervisor_company_phone" class="form-control form-control-lg"
                                           value="<?= e($curr['company_phone'] ?? '') ?>" placeholder="เช่น 02-123-4567">
                                </div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label small fw-bold text-dark">ผู้จัดการ / ผู้รับผิดชอบสถานประกอบการ</label>
                                <div class="input-group-premium">
                                    <i class="bi bi-person-badge input-icon"></i>
                                    <input type="text" name="company_manager" id="supervisor_company_manager" class="form-control form-control-lg"
                                           value="<?= e($curr['company_manager'] ?? '') ?>" placeholder="ชื่อผู้จัดการหรือผู้ติดต่อหลัก">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="text-center mt-4">
                        <button type="submit" name="update_profile" class="btn btn-sv-submit btn-shine hover-lift">
                            <i class="bi bi-check-circle-fill me-2"></i> บันทึกข้อมูล
                        </button>
                    </div>
                </form>
            </div>

            <!-- Change Password -->
            <div class="p-4 p-md-5 mt-4 shadow-sm mb-5" style="background:white;border-radius:2rem;">
                <h4 class="fw-bold mb-4"><i class="bi bi-shield-lock-fill me-2 text-warning"></i>เปลี่ยนรหัสผ่าน</h4>
                <form method="POST">
                    <?php csrf_field(); ?>
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label small fw-bold">รหัสผ่านปัจจุบัน</label>
                            <div class="input-group-premium">
                                <i class="bi bi-key input-icon"></i>
                                <input type="password" name="current_password" class="form-control" required placeholder="ป้อนรหัสผ่านเดิม">
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold">รหัสผ่านใหม่</label>
                            <div class="input-group-premium">
                                <i class="bi bi-lock input-icon"></i>
                                <input type="password" name="new_password" class="form-control" required placeholder="อย่างน้อย 6 ตัวอักษร">
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label small fw-bold">ยืนยันรหัสผ่านใหม่</label>
                            <div class="input-group-premium">
                                <i class="bi bi-lock-fill input-icon"></i>
                                <input type="password" name="confirm_password" class="form-control" required placeholder="ป้อนรหัสผ่านใหม่อีกครั้ง">
                            </div>
                        </div>
                    </div>
                    <div class="text-end mt-3">
                        <button type="submit" name="change_password" class="btn btn-warning rounded-pill px-4 fw-bold">
                            ยืนยันการเปลี่ยนรหัสผ่าน
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Webcam Modal -->
<div id="webcamModal" class="webcam-overlay">
    <div class="webcam-window">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold mb-0">ถ่ายรูปโปรไฟล์</h5>
            <button type="button" class="btn-close" onclick="closeWebcam()"></button>
        </div>
        <div class="webcam-preview-container">
            <video id="webcam-video" autoplay playsinline muted></video>
            <canvas id="webcam-canvas" style="display:none;"></canvas>
        </div>
        <div id="camera-controls">
            <button type="button" class="btn btn-danger rounded-pill px-4" onclick="takeSnapshot()">ถ่ายภาพ</button>
        </div>
        <div id="result-controls" class="d-none">
            <button type="button" class="btn btn-light border rounded-pill px-3 me-2" onclick="retakePhoto()">ถ่ายใหม่</button>
            <button type="button" class="btn btn-success rounded-pill px-4" onclick="savePhoto()">ตกลง</button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function previewImage(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const maxBytes = 10 * 1024 * 1024; // 10MB
        if (file.size > maxBytes) {
            Swal.fire('ไฟล์มีขนาดใหญ่เกินไป', 'ขนาดรูปภาพต้องไม่เกิน 10MB (ขนาดรูปของคุณคือ ' + (file.size / (1024*1024)).toFixed(2) + ' MB)', 'warning');
            input.value = '';
            return;
        }
        const validTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/jpg'];
        if (!validTypes.includes(file.type.toLowerCase())) {
            Swal.fire('ชนิดไฟล์ไม่ถูกต้อง', 'กรุณาเลือกไฟล์รูปภาพประเภท JPG, PNG, WEBP หรือ GIF เท่านั้น', 'warning');
            input.value = '';
            return;
        }
        const reader = new FileReader();
        reader.onload = e => {
            document.getElementById('avatar-preview').src = e.target.result;
            document.getElementById('avatar-preview').style.display = 'block';
            document.getElementById('avatar-placeholder').style.display = 'none';
            document.getElementById('captured-image-base64').value = '';
        };
        reader.readAsDataURL(file);
    }
}

let stream = null;
const video  = document.getElementById('webcam-video');
const canvas = document.getElementById('webcam-canvas');

async function openWebcam() {
    try {
        stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false });
        video.srcObject = stream;
        document.getElementById('webcamModal').classList.add('show');
    } catch(e) {
        Swal.fire('ผิดพลาด', 'ไม่สามารถเข้าถึงกล้องได้', 'error');
    }
}
function closeWebcam() {
    if (stream) stream.getTracks().forEach(t => t.stop());
    document.getElementById('webcamModal').classList.remove('show');
}
function takeSnapshot() {
    canvas.width = video.videoWidth; canvas.height = video.videoHeight;
    canvas.getContext('2d').drawImage(video, 0, 0);
    video.style.display = 'none'; canvas.style.display = 'block';
    document.getElementById('camera-controls').classList.add('d-none');
    document.getElementById('result-controls').classList.remove('d-none');
}
function retakePhoto() {
    video.style.display = 'block'; canvas.style.display = 'none';
    document.getElementById('camera-controls').classList.remove('d-none');
    document.getElementById('result-controls').classList.add('d-none');
}
function savePhoto() {
    const dataUrl = canvas.toDataURL('image/jpeg');
    document.getElementById('avatar-preview').src = dataUrl;
    document.getElementById('avatar-preview').style.display = 'block';
    document.getElementById('avatar-placeholder').style.display = 'none';
    document.getElementById('captured-image-base64').value = dataUrl;
    document.getElementById('profile-image-input').value = '';
    closeWebcam();
}

(function() {
    const companyInput = document.getElementById('supervisor_company_name');
    const companyDropdown = document.getElementById('company_dropdown');
    const companyIdField = document.getElementById('supervisor_company_id');
    const branchIdField = document.getElementById('supervisor_branch_id');
    const addressInput = document.getElementById('supervisor_company_address');
    const phoneInput = document.getElementById('supervisor_company_phone');
    const managerInput = document.getElementById('supervisor_company_manager');
    let debounceTimer = null;

    if (!companyInput || !companyDropdown) return;

    companyInput.addEventListener('input', function() {
        clearTimeout(debounceTimer);
        const q = companyInput.value.trim();
        if (q.length < 1) {
            companyDropdown.style.display = 'none';
            return;
        }

        debounceTimer = setTimeout(function() {
            const url = '<?= BASE_URL ?>/companies/ajax_search_companies.php?q=' + encodeURIComponent(q);
            fetch(url, { credentials: 'same-origin' })
                .then(res => res.json())
                .then(data => {
                    if (!Array.isArray(data) || data.length === 0) {
                        companyDropdown.innerHTML = '<div class="p-2 text-muted small text-center"><i class="bi bi-info-circle me-1"></i>ไม่พบในฐานข้อมูล (ระบบจะใช้ชื่อที่คุณพิมพ์)</div>';
                        companyDropdown.style.display = 'block';
                        return;
                    }

                    let html = '';
                    data.forEach(item => {
                        const name = item.company_name + (item.branch_label ? ' (' + item.branch_label + ')' : '');
                        const address = item.company_address || '';
                        const phone = item.contact_phone || '';
                        const contact = item.contact_name || '';

                        html += `<a class="dropdown-item rounded-3 py-2 px-3 mb-1" href="javascript:void(0)" 
                                    onclick="selectSupervisorCompany('${item.company_id}', '${item.branch_id || ''}', '${escAttr(name)}', '${escAttr(address)}', '${escAttr(phone)}', '${escAttr(contact)}')">
                                    <div class="fw-bold text-dark"><i class="bi bi-building me-1 text-primary"></i>${escHtml(name)}</div>
                                    ${address ? '<div class="small text-muted text-truncate"><i class="bi bi-geo-alt me-1"></i>' + escHtml(address) + '</div>' : ''}
                                 </a>`;
                    });
                    companyDropdown.innerHTML = html;
                    companyDropdown.style.display = 'block';
                })
                .catch(() => { companyDropdown.style.display = 'none'; });
        }, 300);
    });

    window.selectSupervisorCompany = function(cid, bid, name, address, phone, contact) {
        companyInput.value = name;
        companyIdField.value = cid || '0';
        branchIdField.value = bid || '';
        if (addressInput && address) addressInput.value = address;
        if (phoneInput && phone) phoneInput.value = phone;
        if (managerInput && contact) managerInput.value = contact;
        companyDropdown.style.display = 'none';
    };

    document.addEventListener('click', function(e) {
        if (!companyInput.contains(e.target) && !companyDropdown.contains(e.target)) {
            companyDropdown.style.display = 'none';
        }
    });

    function escHtml(str) {
        return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
    function escAttr(str) {
        return String(str || '').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }
})();

<?php if (isset($show_success_alert)): ?>
Swal.fire({ title:'สำเร็จ!', text:'อัปเดตโปรไฟล์เรียบร้อยแล้ว', icon:'success', confirmButtonText:'ตกลง' });
<?php endif; ?>
<?php if (isset($pass_success)): ?>
Swal.fire({ title:'สำเร็จ!', text:'<?= $pass_success ?>', icon:'success', confirmButtonText:'ตกลง' });
<?php endif; ?>
<?php if (isset($pass_err)): ?>
Swal.fire({ title:'ผิดพลาด!', text:'<?= $pass_err ?>', icon:'error', confirmButtonText:'ตกลง' });
<?php endif; ?>
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
