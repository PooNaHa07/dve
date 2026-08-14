<?php
require_once __DIR__ . '/../includes/functions.php';
require_role(['staff']);
include __DIR__ . '/../includes/header.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: news.php');
    exit;
}

$id = (int)$_GET['id'];
$err = '';
$success = '';

// Fetch current state
$stmt = $conn->prepare("SELECT * FROM news WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
$news = $res->fetch_assoc();
$stmt->close();

if (!$news) {
    header('Location: news.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $video_url = trim($_POST['video_url'] ?? '');
    $image_name = $news['image']; // Default to keeping current image
    $video_name = $news['video']; // Default to keeping current video

    // Check if deletion of image requested
    if (isset($_POST['delete_image']) && $_POST['delete_image'] === '1') {
         if (!empty($news['image'])) {
            $old_path = __DIR__ . "/../uploads/news/" . $news['image'];
            if (file_exists($old_path)) @unlink($old_path);
         }
         $image_name = null;
    }

    // Check if deletion of video requested
    if (isset($_POST['delete_video']) && $_POST['delete_video'] === '1') {
         if (!empty($news['video'])) {
            $old_path = __DIR__ . "/../uploads/news/" . $news['video'];
            if (file_exists($old_path)) @unlink($old_path);
         }
         $video_name = null;
    }

    // Check new upload (Image)
    if (!empty($_FILES['image']['name'])) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed)) {
            $err = "อนุญาตเฉพาะไฟล์ JPG, PNG, GIF เท่านั้น";
        } else {
            $target_dir = __DIR__ . "/../uploads/news/";
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0777, true);
            }

            $new_image = time() . '_' . rand(1000,9999) . "." . $ext;
            $target_file = $target_dir . $new_image;

            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                // Delete old image if replacing
                if (!empty($news['image'])) {
                    $old_path = $target_dir . $news['image'];
                    if (file_exists($old_path)) @unlink($old_path);
                }
                $image_name = $new_image;
            } else {
                $err = "อัปโหลดรูปไม่สำเร็จ";
            }
        }
    }

    // Check new upload (Video)
    if (!$err && !empty($_FILES['video']['name'])) {
        $allowed_video = ['mp4', 'webm', 'ogg', 'avi', 'mov'];
        $ext_video = strtolower(pathinfo($_FILES['video']['name'], PATHINFO_EXTENSION));

        if (!in_array($ext_video, $allowed_video)) {
            $err = "อนุญาตเฉพาะไฟล์วิดีโอ MP4, WEBM, OGG, AVI, MOV เท่านั้น";
        } else {
            $target_dir = __DIR__ . "/../uploads/news/";
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0777, true);
            }

            $new_video = time() . '_video_' . rand(1000,9999) . "." . $ext_video;
            $target_file = $target_dir . $new_video;

            if (move_uploaded_file($_FILES['video']['tmp_name'], $target_file)) {
                // Delete old video if replacing
                if (!empty($news['video'])) {
                    $old_path = $target_dir . $news['video'];
                    if (file_exists($old_path)) @unlink($old_path);
                }
                $video_name = $new_video;
            } else {
                $err = "อัปโหลดวิดีโอไม่สำเร็จ";
            }
        }
    }

    if (!$err && $title && $content) {
        $update = $conn->prepare("UPDATE news SET title = ?, content = ?, image = ?, video = ?, video_url = ? WHERE id = ?");
        $update->bind_param("sssssi", $title, $content, $image_name, $video_name, $video_url, $id);

        if ($update->execute()) {
            $success = "อัปเดตข้อมูลข่าวสารเรียบร้อยแล้ว";
            // Refresh current object for render
            $news['title'] = $title;
            $news['content'] = $content;
            $news['image'] = $image_name;
            $news['video'] = $video_name;
            $news['video_url'] = $video_url;
        } else {
            $err = "เกิดข้อผิดพลาดในการบันทึก กรุณาลองใหม่อีกครั้ง";
        }
        $update->close();
    }
}
?>
<link rel="stylesheet" href="../includes/staff_style.css">

<div class="staff-dashboard-page pt-4 pb-5">
    <div class="container">
        
        <!-- Header area -->
        <div class="staff-page-header d-flex justify-content-between align-items-center">
            <div>
                <h4 class="fw-bold text-dark mb-1"><i class="bi bi-pencil-square text-primary me-2"></i>แก้ไขข่าวสาร</h4>
                <p class="text-muted small mb-0">แก้ไขรายละเอียดข่าวสาร หรือเปลี่ยนแปลงภาพประกอบหลัก</p>
            </div>
            <a href="news.php" class="btn btn-outline-secondary bg-white shadow-sm border-opacity-25 rounded-pill px-4">← ยกเลิก</a>
        </div>

        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm overflow-hidden" style="border-radius: 20px;">
                    <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
                        <h6 class="mb-0 fw-bold text-secondary d-flex align-items-center gap-2"><i class="bi bi-pencil text-primary"></i> ปรับเปลี่ยนข้อมูล</h6>
                        <hr class="border-light mt-3 mb-0">
                    </div>
                    <div class="card-body p-4">
                        
                        <?php if ($err): ?>
                            <div class="alert alert-danger border-0 rounded-3 shadow-xs d-flex align-items-center" role="alert">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i> 
                                <div><?= e($err) ?></div>
                            </div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-success border-0 rounded-3 shadow-xs d-flex align-items-center" role="alert">
                                <i class="bi bi-check-circle-fill me-2"></i>
                                <div><?= e($success) ?></div>
                            </div>
                        <?php endif; ?>

                        <form method="post" enctype="multipart/form-data">
                            <div class="mb-4">
                                <label class="form-label fw-bold small text-secondary text-uppercase">หัวข้อข่าว / ชื่อกิจกรรม</label>
                                <input type="text" name="title" class="form-control form-control-lg bg-light border-0 rounded-3" 
                                       value="<?= e($news['title']) ?>" required>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold small text-secondary text-uppercase">รายละเอียด</label>
                                <textarea name="content" class="form-control bg-light border-0 rounded-3" rows="8" required><?= e($news['content']) ?></textarea>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold small text-secondary text-uppercase">ภาพประกอบเดิม (ถ้ามี)</label>
                                <?php if (!empty($news['image'])): ?>
                                    <div class="mb-3 p-2 bg-light border rounded d-inline-block position-relative">
                                        <img src="../uploads/news/<?= e($news['image']) ?>" style="max-height: 150px; border-radius: 8px;" alt="current">
                                        <div class="mt-2">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="delImg" name="delete_image" value="1">
                                                <label class="form-check-label text-danger small fw-bold" for="delImg">ต้องการลบรูปภาพนี้</label>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted small">ยังไม่มีรูปภาพประกอบสำหรับข่าวนี้</p>
                                <?php endif; ?>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold small text-secondary text-uppercase">อัปโหลดภาพใหม่ (กรณีเปลี่ยน)</label>
                                <div class="custom-file-input-wrapper p-3 bg-light rounded-3 text-center border-dashed">
                                    <input type="file" name="image" id="fileInput" class="form-control border-0 bg-white shadow-sm" accept="image/*">
                                    <small class="text-muted mt-2 d-block">รองรับ: .jpg, .png, .gif (การอัปโหลดใหม่จะแทนที่รูปเดิมทันที)</small>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold small text-secondary text-uppercase">วิดีโอประกอบเดิม (ถ้ามี)</label>
                                <?php if (!empty($news['video'])): ?>
                                    <div class="mb-3 p-2 bg-light border rounded d-inline-block position-relative w-100" style="max-width: 400px;">
                                        <video src="../uploads/news/<?= e($news['video']) ?>" controls class="w-100 rounded" style="max-height: 150px;"></video>
                                        <div class="mt-2">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="delVideo" name="delete_video" value="1">
                                                <label class="form-check-label text-danger small fw-bold" for="delVideo">ต้องการลบวิดีโอนี้</label>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted small">ยังไม่มีไฟล์วิดีโอประกอบสำหรับข่าวนี้</p>
                                <?php endif; ?>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold small text-secondary text-uppercase">อัปโหลดวิดีโอใหม่ / เพิ่มวิดีโอ</label>
                                <div class="custom-file-input-wrapper p-3 bg-light rounded-3 text-center border-dashed">
                                    <input type="file" name="video" class="form-control border-0 bg-white shadow-sm" accept="video/*">
                                    <small class="text-muted mt-2 d-block">รองรับ: .mp4, .webm, .ogg, .avi, .mov (การอัปโหลดใหม่จะแทนที่วิดีโอเดิมทันที)</small>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold small text-secondary text-uppercase">ลิงก์วิดีโอประชาสัมพันธ์ (เช่น YouTube)</label>
                                <input type="url" name="video_url" class="form-control form-control-lg bg-light border-0 rounded-3" 
                                       value="<?= e($news['video_url'] ?? '') ?>" placeholder="วางลิงก์วิดีโอประชาสัมพันธ์ที่นี่...">
                            </div>

                            <div class="d-grid mt-4">
                                <button type="submit" class="btn btn-staff btn-lg rounded-pill py-3 shadow-sm fw-bold">
                                    <i class="bi bi-check-circle-fill me-2"></i> บันทึกการแก้ไข
                                </button>
                            </div>
                        </form>
                        
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<style>
    .border-dashed {
        border: 2px dashed rgba(0,0,0,0.08) !important;
    }
    .form-control:focus {
        background-color: #fff !important;
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
    }
    .shadow-xs { box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>
