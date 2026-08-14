<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php'; 
require_login();
require_role(['director']);

$u = current_user();
$director_id = $u['id'];

// 1. ดึงข้อมูลปัจจุบันของผู้บริหาร
$curr = current_user(); 
if (!$curr) {
    die("ไม่พบข้อมูลผู้ใช้");
}

// 2. บันทึกข้อมูลเมื่อมีการกดปุ่ม Submit
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $fullname = $_POST['fullname'];
    $phone = $_POST['phone'];
    $email = $_POST['email'];
    $affiliation = $_POST['affiliation'];

    $profile_image = $curr['profile_image']; 
    
    // ตรวจสอบภาพจากระบบ Camera Direct Base64
    if (!empty($_POST['captured_image_base64'])) {
        $img_str = $_POST['captured_image_base64'];
        if (preg_match('/^data:image\/(\w+);base64,/', $img_str, $type)) {
            $data = substr($img_str, strpos($img_str, ',') + 1);
            $data = base64_decode($data);
            if ($data !== false) {
                $file_name = 'avatar_dir_' . uniqid() . '.jpg';
                $upload_dir = __DIR__ . '/../uploads/avatars/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                
                if (file_put_contents($upload_dir . $file_name, $data)) {
                    $profile_image = $file_name;
                }
            }
        }
    }
    // หากไม่มี Base64 จึงค่อยประมวลผลจากการอัปโหลดไฟล์มาตรฐาน
    else if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $uploaded = upload_file($_FILES['profile_image'], ['jpg', 'jpeg', 'png', 'webp'], __DIR__ . '/../uploads/avatars', 2*1024*1024);
        if ($uploaded !== null) {
            $profile_image = $uploaded;
        }
    }

    $sql = "UPDATE users SET 
            fullname = ?, 
            phone = ?, 
            email = ?, 
            affiliation = ?,
            profile_image = ? 
            WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssi", $fullname, $phone, $email, $affiliation, $profile_image, $director_id);
    if ($stmt->execute()) {
        $curr = current_user(true); 
        $show_success_alert = true;
    }
}

// 3. เปลี่ยนรหัสผ่าน
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $current_pass = $_POST['current_password'];
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];

    if (password_verify($current_pass, $curr['password'])) {
        if (strlen($new_pass) < 6) {
            $pass_err = 'รหัสผ่านใหม่ต้องมีความยาวอย่างน้อย 6 ตัวอักษร';
        } elseif ($new_pass !== $confirm_pass) {
            $pass_err = 'รหัสผ่านใหม่และการยืนยันรหัสผ่านไม่ตรงกัน';
        } else {
            $hashed_pass = password_hash($new_pass, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hashed_pass, $director_id);
            if ($stmt->execute()) {
                $pass_success = 'เปลี่ยนรหัสผ่านเรียบร้อยแล้ว';
            } else {
                $pass_err = 'เกิดข้อผิดพลาดในการบันทึกรหัสผ่านใหม่';
            }
        }
    } else {
        $pass_err = 'รหัสผ่านปัจจุบันไม่ถูกต้อง';
    }
}


$hide_welcome = true;
$page_title = "จัดการโปรไฟล์ผู้บริหาร";
include __DIR__ . '/../includes/header.php';
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/student-premium.css">
<style>
    .profile-banner-premium {
        background: linear-gradient(135deg, #4361ee 0%, #3a0ca3 50%, #7209b7 100%);
        border-radius: 24px;
        position: relative;
        overflow: hidden;
        margin-bottom: -30px;
        box-shadow: 0 15px 35px rgba(67, 97, 238, 0.25);
    }
    
    .avatar-wrapper-premium {
        width: 120px;
        height: 120px;
        background: rgba(255, 255, 255, 0.15);
        backdrop-filter: blur(10px);
        border: 4px solid rgba(255, 255, 255, 0.8);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }

    .input-group-premium {
        position: relative;
        display: flex;
        align-items: center;
    }

    .input-group-premium .form-control {
        padding-left: 3rem !important;
        border-radius: 1.25rem;
        background: rgba(255, 255, 255, 0.65);
        backdrop-filter: blur(5px);
        border: 1px solid rgba(0, 0, 0, 0.08);
        transition: all 0.3s ease;
    }

    .input-icon {
        position: absolute;
        left: 1.25rem;
        color: #4361ee;
        font-size: 1.2rem;
        z-index: 5;
    }

    .btn-premium-submit {
        background: linear-gradient(135deg, #4361ee 0%, #7209b7 100%);
        border: none;
        border-radius: 1.5rem;
        padding: 1rem 3rem;
        font-weight: 800;
        color: white;
        box-shadow: 0 10px 20px rgba(67, 97, 238, 0.3);
        transition: all 0.3s ease;
    }

    .btn-premium-submit:hover {
        transform: translateY(-2px);
        box-shadow: 0 15px 30px rgba(114, 9, 183, 0.4);
        color: white;
    }

    /* Webcam Modal Styles */
    .webcam-overlay {
        display: none;
        position: fixed;
        top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0,0,0,0.85);
        backdrop-filter: blur(15px);
        z-index: 10000;
        align-items: center;
        justify-content: center;
    }
    .webcam-overlay.show { display: flex; }
    .webcam-window {
        background: white;
        border-radius: 2rem;
        width: 90%;
        max-width: 480px;
        padding: 1.5rem;
        text-align: center;
    }
    .webcam-preview-container {
        width: 100%;
        aspect-ratio: 3/4;
        border-radius: 1.5rem;
        overflow: hidden;
        background: #000;
        margin-bottom: 1.5rem;
    }
    #webcam-video, #webcam-canvas { width: 100%; height: 100%; object-fit: cover; transform: scaleX(-1); }
</style>

<div class="container py-5">
    <div class="mb-4 d-flex justify-content-between align-items-center">
        <a href="../roles/director.php" class="btn btn-link text-decoration-none p-0 fw-bold text-secondary">
            <i class="bi bi-arrow-left-circle-fill fs-4 me-2"></i> กลับหน้าหลัก
        </a>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-10 col-xl-9">
            <div class="profile-banner-premium shadow-lg p-4 p-md-5 text-white mb-4">
                <div class="d-flex flex-column flex-md-row align-items-center gap-4">
                    <div class="avatar-wrapper-premium" style="position: relative; overflow: hidden;">
                        <?php if (!empty($curr['profile_image'])): ?>
                            <img src="<?= BASE_URL ?>/uploads/avatars/<?= htmlspecialchars($curr['profile_image']) ?>?v=<?= time() ?>" style="width: 100%; height: 100%; object-fit: cover;">
                        <?php else: ?>
                            <i class="bi bi-person-workspace fs-1"></i>
                        <?php endif; ?>
                    </div>
                    <div class="text-center text-md-start">
                        <span class="badge bg-white text-primary px-3 py-1 rounded-pill mb-2 fw-bold small">Director Profile</span>
                        <h2 class="fw-extrabold mb-1 text-white"><?= e($curr['fullname']) ?></h2>
                        <div class="mb-2">
                            <span class="badge rounded-pill px-3 py-2 shadow-sm" style="background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.3); color: white; backdrop-filter: blur(10px); display: inline-flex; align-items: center;">
                                <i class="bi bi-diagram-3 me-2"></i><?= e($curr['affiliation'] ?? 'ผู้บริหารวิทยาลัย') ?>
                            </span>
                        </div>
                        <p class="mb-0 text-white-50"><i class="bi bi-person-badge me-1"></i> ผู้อำนวยการ/รองผู้อำนวยการ | วิทยาลัยอาชีวศึกษาเพชรบุรี</p>
                    </div>
                </div>
            </div>

            <div class="glass-card p-4 p-md-5 mt-3 shadow-sm" style="background: white; border-radius: 2rem;">
                <h4 class="fw-bold mb-4 text-center">ข้อมูลส่วนตัวผู้บริหาร</h4>
                
                <form id="profileForm" method="POST" enctype="multipart/form-data">
                    <div class="row align-items-center mb-5 p-4 rounded-4" style="background: rgba(67, 97, 238, 0.03); border: 1px dashed rgba(67, 97, 238, 0.2);">
                        <div class="col-md-3 text-center mb-3 mb-md-0">
                            <div style="width: 100px; height: 100px; border-radius: 50%; overflow: hidden; border: 3px solid white; box-shadow: 0 5px 15px rgba(0,0,0,0.1); margin: 0 auto;">
                                <img id="avatar-preview" src="<?= !empty($curr['profile_image']) ? BASE_URL.'/uploads/avatars/'.$curr['profile_image'].'?v='.time() : '' ?>" style="width: 100%; height: 100%; object-fit: cover; <?= empty($curr['profile_image']) ? 'display:none;' : '' ?>">
                                <div id="avatar-placeholder" class="h-100 d-flex align-items-center justify-content-center bg-light" style="<?= !empty($curr['profile_image']) ? 'display:none;' : '' ?>">
                                    <i class="bi bi-person-fill fs-1 text-secondary"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-9 text-center text-md-start">
                            <h6 class="fw-bold mb-1"><i class="bi bi-camera-fill me-1"></i> เปลี่ยนรูปโปรไฟล์</h6>
                            <p class="text-muted small mb-3">แนะนำไฟล์สี่เหลี่ยมจัตุรัส ขนาดไม่เกิน 2MB</p>
                            <div class="d-flex flex-wrap gap-2 align-items-center justify-content-center justify-content-md-start">
                                <input type="file" name="profile_image" id="profile-image-input" class="form-control form-control-sm w-auto rounded-pill" accept="image/*" onchange="previewImage(this)">
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
                                <input type="text" name="fullname" class="form-control form-control-lg" value="<?= htmlspecialchars($curr['fullname']) ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="form-label small fw-bold text-primary">อีเมล (Email)</label>
                            <div class="input-group-premium">
                                <i class="bi bi-envelope input-icon"></i>
                                <input type="email" name="email" class="form-control form-control-lg" value="<?= htmlspecialchars($curr['email']) ?>">
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="form-label small fw-bold text-primary">เบอร์โทรศัพท์</label>
                            <div class="input-group-premium">
                                <i class="bi bi-telephone input-icon"></i>
                                <input type="text" name="phone" class="form-control form-control-lg" value="<?= e($curr['phone'] ?? '') ?>" placeholder="เช่น 081-234-5678">
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="form-label small fw-bold text-primary">สังกัด / ตำแหน่ง</label>
                            <div class="input-group-premium">
                                <i class="bi bi-building input-icon"></i>
                                <input type="text" name="affiliation" class="form-control form-control-lg" value="<?= e($curr['affiliation'] ?? '') ?>" placeholder="เช่น ฝ่ายวิชาการ">
                            </div>
                        </div>
                    </div>

                    <div class="text-center mt-4">
                        <button type="submit" name="update_profile" class="btn btn-premium-submit">
                            <i class="bi bi-check-circle-fill me-2"></i> บันทึกข้อมูล
                        </button>
                    </div>
                </form>
            </div>

            <!-- Change Password Card -->
            <div class="glass-card p-4 p-md-5 mt-4 shadow-sm mb-5" style="background: white; border-radius: 2rem;">
                <h4 class="fw-bold mb-4"><i class="bi bi-shield-lock-fill me-2 text-warning"></i>เปลี่ยนรหัสผ่าน</h4>
                <form method="POST">
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label small fw-bold">รหัสผ่านปัจจุบัน</label>
                            <div class="input-group-premium">
                                <i class="bi bi-key input-icon"></i>
                                <input type="password" name="current_password" class="form-control" required placeholder="ป้อนรหัสผ่านเดิมเพื่อยืนยัน">
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
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('avatar-preview').src = e.target.result;
                document.getElementById('avatar-preview').style.display = 'block';
                document.getElementById('avatar-placeholder').style.display = 'none';
                document.getElementById('captured-image-base64').value = "";
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    // Webcam Logic
    let stream = null;
    const video = document.getElementById('webcam-video');
    const canvas = document.getElementById('webcam-canvas');

    async function openWebcam() {
        try {
            stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: "user" }, audio: false });
            video.srcObject = stream;
            document.getElementById('webcamModal').classList.add('show');
        } catch (err) {
            Swal.fire("ผิดพลาด", "ไม่สามารถเข้าถึงกล้องได้", "error");
        }
    }

    function closeWebcam() {
        if (stream) stream.getTracks().forEach(t => t.stop());
        document.getElementById('webcamModal').classList.remove('show');
    }

    function takeSnapshot() {
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        canvas.getContext('2d').drawImage(video, 0, 0);
        video.style.display = 'none';
        canvas.style.display = 'block';
        document.getElementById('camera-controls').classList.add('d-none');
        document.getElementById('result-controls').classList.remove('d-none');
    }

    function retakePhoto() {
        video.style.display = 'block';
        canvas.style.display = 'none';
        document.getElementById('camera-controls').classList.remove('d-none');
        document.getElementById('result-controls').classList.add('d-none');
    }

    function savePhoto() {
        const dataUrl = canvas.toDataURL('image/jpeg');
        document.getElementById('avatar-preview').src = dataUrl;
        document.getElementById('avatar-preview').style.display = 'block';
        document.getElementById('avatar-placeholder').style.display = 'none';
        document.getElementById('captured-image-base64').value = dataUrl;
        document.getElementById('profile-image-input').value = "";
        closeWebcam();
    }

    <?php if(isset($show_success_alert)): ?>
    Swal.fire({
        title: "สำเร็จ!",
        text: "อัปเดตโปรไฟล์เรียบร้อยแล้ว",
        icon: "success",
        confirmButtonText: "ตกลง"
    });
    <?php endif; ?>

    <?php if(isset($pass_success)): ?>
    Swal.fire({
        title: "สำเร็จ!",
        text: "<?= $pass_success ?>",
        icon: "success",
        confirmButtonText: "ตกลง"
    });
    <?php endif; ?>

    <?php if(isset($pass_err)): ?>
    Swal.fire({
        title: "ผิดพลาด!",
        text: "<?= $pass_err ?>",
        icon: "error",
        confirmButtonText: "ตกลง"
    });
    <?php endif; ?>
</script>


<?php include __DIR__ . '/../includes/footer.php'; ?>
