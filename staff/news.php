<?php
// staff/news.php - หน้าจัดการข่าวสารประชาสัมพันธ์ (Modal Version)
date_default_timezone_set('Asia/Bangkok');
require_once __DIR__ . '/../includes/functions.php';
require_role(['staff']);

$error_msg = '';
$success_msg = '';

// Helper function to process multiple uploads
function process_news_attachments($news_id, $conn, $target_dir) {
    $upload_types = [
        'images' => ['allowed' => ['jpg', 'jpeg', 'png', 'gif', 'webp'], 'type' => 'image'],
        'videos' => ['allowed' => ['mp4', 'webm', 'ogg', 'avi', 'mov'], 'type' => 'video'],
        'documents' => ['allowed' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'rar'], 'type' => 'document']
    ];

    foreach ($upload_types as $input_name => $config) {
        if (!empty($_FILES[$input_name]['name'][0])) {
            foreach ($_FILES[$input_name]['name'] as $key => $name) {
                if ($_FILES[$input_name]['error'][$key] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                    if (in_array($ext, $config['allowed'])) {
                        $new_name = time() . '_' . rand(1000, 9999) . '_' . $config['type'] . '.' . $ext;
                        if (move_uploaded_file($_FILES[$input_name]['tmp_name'][$key], $target_dir . $new_name)) {
                            $stmt = $conn->prepare("INSERT INTO news_attachments (news_id, file_name, original_name, file_type) VALUES (?, ?, ?, ?)");
                            $stmt->bind_param("isss", $news_id, $new_name, $name, $config['type']);
                            $stmt->execute();
                        }
                    }
                }
            }
        }
    }
}

// 1. Handle POST Actions (Add/Edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = isset($_POST['news_id']) ? (int)$_POST['news_id'] : 0;
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $video_url = trim($_POST['video_url'] ?? '');
    
    if (empty($title) || empty($content)) {
        $error_msg = "กรุณากรอกหัวข้อและเนื้อหาข่าว";
    } else {
        $target_dir = __DIR__ . "/../uploads/news/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        
        if ($action === 'add') {
            $stmt = $conn->prepare("INSERT INTO news (title, content, video_url, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->bind_param("sss", $title, $content, $video_url);
            if ($stmt->execute()) {
                $news_id = $stmt->insert_id;
                process_news_attachments($news_id, $conn, $target_dir);
                $_SESSION['success_message'] = "เพิ่มข่าวสารสำเร็จแล้ว!";
                header("Location: news.php");
                exit;
            } else {
                $error_msg = "เกิดข้อผิดพลาด: " . $conn->error;
            }
        } elseif ($action === 'edit' && $id > 0) {
            $stmt = $conn->prepare("UPDATE news SET title = ?, content = ?, video_url = ? WHERE id = ?");
            $stmt->bind_param("sssi", $title, $content, $video_url, $id);
            if ($stmt->execute()) {
                if (!empty($_POST['delete_attachments'])) {
                    foreach ($_POST['delete_attachments'] as $attach_id) {
                        $attach_id = (int)$attach_id;
                        $q = $conn->query("SELECT file_name FROM news_attachments WHERE id = $attach_id AND news_id = $id");
                        if ($row = $q->fetch_assoc()) {
                            @unlink($target_dir . $row['file_name']);
                            $conn->query("DELETE FROM news_attachments WHERE id = $attach_id");
                        }
                    }
                }
                
                $q = $conn->query("SELECT image, video FROM news WHERE id = $id");
                $old_data = $q->fetch_assoc();
                if (isset($_POST['delete_old_image']) && $_POST['delete_old_image'] === '1') {
                    if (!empty($old_data['image'])) @unlink($target_dir . $old_data['image']);
                    $conn->query("UPDATE news SET image = NULL WHERE id = $id");
                }
                if (isset($_POST['delete_old_video']) && $_POST['delete_old_video'] === '1') {
                    if (!empty($old_data['video'])) @unlink($target_dir . $old_data['video']);
                    $conn->query("UPDATE news SET video = NULL WHERE id = $id");
                }

                process_news_attachments($id, $conn, $target_dir);
                
                $_SESSION['success_message'] = "อัปเดตข่าวสารสำเร็จแล้ว!";
                header("Location: news.php");
                exit;
            } else {
                $error_msg = "เกิดข้อผิดพลาด: " . $conn->error;
            }
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="../includes/staff_style.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="staff-dashboard-page pt-4">
    <div class="container">
        
        <!-- Page Header Container -->
        <div class="staff-page-header d-flex justify-content-between align-items-center mb-4 animate-fade-in">
            <div>
                <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-megaphone text-primary me-2"></i>ข่าวสารประชาสัมพันธ์</h4>
                <p class="text-muted mb-0 small">จัดการข่าวสารและกิจกรรมเพื่อประกาศไปยังสมาชิกทุกคน</p>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-staff d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#addNewsModal">
                    <i class="bi bi-plus-circle fs-6"></i> เพิ่มข่าว
                </button>
                <a href="../roles/staff.php" class="btn btn-outline-secondary border-opacity-25 bg-white rounded-pill shadow-sm">
                    กลับแดชบอร์ด
                </a>
            </div>
        </div>

        <!-- Flash Messages -->
        <?php 
        if(isset($_SESSION['success_message'])) {
            echo '<div class="alert alert-success border-0 shadow-sm rounded-3 py-3 mb-4"><i class="bi bi-check-circle-fill me-2"></i>'.e($_SESSION['success_message']).'</div>';
            unset($_SESSION['success_message']);
        }
        if($error_msg) {
            echo '<div class="alert alert-danger border-0 shadow-sm rounded-3 py-3 mb-4"><i class="bi bi-exclamation-circle-fill me-2"></i>'.e($error_msg).'</div>';
        }
        ?>

        <!-- News List Grid -->
        <div class="row g-4">
            <?php
            $query = "
                SELECT 
                    n.id, n.title, n.created_at, n.image, n.video, n.video_url, n.content,
                    (SELECT file_name FROM news_attachments WHERE news_id = n.id AND file_type = 'image' ORDER BY id ASC LIMIT 1) as cover_image,
                    (SELECT file_name FROM news_attachments WHERE news_id = n.id AND file_type = 'video' ORDER BY id ASC LIMIT 1) as cover_video,
                    (SELECT COUNT(id) FROM news_attachments WHERE news_id = n.id AND file_type = 'image') as image_count
                FROM news n 
                ORDER BY n.created_at DESC
            ";
            $result = $conn->query($query);
            if ($result && $result->num_rows > 0):
                while ($news = $result->fetch_assoc()):
                    $hasImg = !empty($news['image']) || !empty($news['cover_image']);
                    $imgSrc = $hasImg ? (!empty($news['image']) ? '../uploads/news/'.e($news['image']) : '../uploads/news/'.e($news['cover_image'])) : '../images/no-image.png';
                    $readableDate = date('d M Y', strtotime($news['created_at']));
                    
                    $hasVideo = !empty($news['video']) || !empty($news['cover_video']);
                    $videoSrc = !empty($news['video']) ? '../uploads/news/'.e($news['video']) : (!empty($news['cover_video']) ? '../uploads/news/'.e($news['cover_video']) : '');
                    $videoUrl = !empty($news['video_url']) ? e($news['video_url']) : '';
                    
                    // Parse video details
                    $youtube_id = '';
                    $vimeo_id = '';
                    $isDirectVideoUrl = false;
                    $isExternalVideoUrl = false;
                    
                    if (!empty($news['video_url'])) {
                        $url = $news['video_url'];
                        if (preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $url, $match)) {
                            $youtube_id = $match[1];
                        } elseif (preg_match('%(?:vimeo\.com/|player\.vimeo\.com/video/)([0-9]+)%i', $url, $match)) {
                            $vimeo_id = $match[1];
                        } else {
                            $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
                            if (in_array($ext, ['mp4', 'webm', 'ogg'])) {
                                $isDirectVideoUrl = true;
                            } else {
                                $isExternalVideoUrl = true;
                            }
                        }
                    }
                    $isEmbeddable = ($hasVideo || !empty($youtube_id) || !empty($vimeo_id) || $isDirectVideoUrl);
                    
                    // Compile all media objects for preview (images + videos + links)
                    $media_list = [];
                    $news_id = $news['id'];
                    // 1. Cover Image (legacy field)
                    if ($hasImg) {
                        $media_list[] = ['type' => 'image', 'src' => $imgSrc];
                    }
                    // 2. Attachment Images
                    $img_q = $conn->query("SELECT file_name FROM news_attachments WHERE news_id = $news_id AND file_type = 'image' ORDER BY id ASC");
                    if ($img_q) while ($img_row = $img_q->fetch_assoc()) {
                        $path = '../uploads/news/' . $img_row['file_name'];
                        // Avoid duplicates with cover_image
                        $dup = false;
                        foreach ($media_list as $m) {
                            if ($m['type'] === 'image' && $m['src'] === $path) { $dup = true; break; }
                        }
                        if (!$dup) {
                            $media_list[] = ['type' => 'image', 'src' => $path];
                        }
                    }
                    // 3. Local Video File (legacy field)
                    if ($hasVideo && !empty($videoSrc)) {
                        $dup = false;
                        foreach ($media_list as $m) {
                            if (($m['type'] === 'video') && $m['src'] === $videoSrc) { $dup = true; break; }
                        }
                        if (!$dup) $media_list[] = ['type' => 'video', 'src' => $videoSrc];
                    }
                    // 4. Attachment Videos
                    $vid_q = $conn->query("SELECT file_name FROM news_attachments WHERE news_id = $news_id AND file_type = 'video' ORDER BY id ASC");
                    if ($vid_q) while ($vid_row = $vid_q->fetch_assoc()) {
                        $vpath = '../uploads/news/' . $vid_row['file_name'];
                        $dup = false;
                        foreach ($media_list as $m) {
                            if ($m['type'] === 'video' && $m['src'] === $vpath) { $dup = true; break; }
                        }
                        if (!$dup) $media_list[] = ['type' => 'video', 'src' => $vpath];
                    }
                    // 5. Video URL (YouTube / Vimeo / Direct)
                    if (!empty($youtube_id)) {
                        $media_list[] = ['type' => 'youtube', 'src' => $youtube_id];
                    } elseif (!empty($vimeo_id)) {
                        $media_list[] = ['type' => 'vimeo', 'src' => $vimeo_id];
                    } elseif ($isDirectVideoUrl && !empty($videoUrl)) {
                        $media_list[] = ['type' => 'direct_video', 'src' => $videoUrl];
                    } elseif ($isExternalVideoUrl && !empty($videoUrl)) {
                        $media_list[] = ['type' => 'external_link', 'src' => $videoUrl];
                    }
                    
                    // Modal Trigger Attrs
                    $previewAttrs = 'data-bs-toggle="modal" data-bs-target="#newsPreviewModal" '
                                  . 'data-full-title="'.e($news['title']).'" '
                                  . 'data-full-content="'.e($news['content']).'" '
                                  . 'data-full-date="'.$readableDate.'" '
                                  . 'data-media="'.e(json_encode($media_list)).'"';
                    
                    // Determine thumbnail image for this card
                    $thumbImg = $hasImg ? $imgSrc : '';
                    // Fallback: use first attachment image if no cover image
                    if (empty($thumbImg)) {
                        foreach ($media_list as $m) {
                            if ($m['type'] === 'image') { $thumbImg = $m['src']; break; }
                        }
                    }
                    // Fallback: YouTube thumbnail
                    if (empty($thumbImg) && !empty($youtube_id)) {
                        $thumbImg = 'https://img.youtube.com/vi/' . $youtube_id . '/hqdefault.jpg';
                    }
                    $totalMedia = count($media_list);
                    ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="glass-card h-100 d-flex flex-column overflow-hidden" style="border-radius: 16px;">
                            <div class="news-img-wrapper position-relative" style="height: 160px; overflow: hidden; background: #1a1a2e; cursor:pointer;" <?= $previewAttrs ?>>
                        <?php if (!empty($thumbImg)): ?>
                            <img src="<?= $thumbImg ?>" alt="<?= e($news['title']) ?>" class="w-100 h-100" style="object-fit: cover; transition: transform 0.35s ease;" onmouseover="this.style.transform='scale(1.06)'" onmouseout="this.style.transform='scale(1)'">
                        <?php elseif ($hasVideo && !empty($videoSrc)): ?>
                            <video src="<?= $videoSrc ?>" class="w-100 h-100" style="object-fit: cover; transition: transform 0.35s ease;" onmouseover="this.style.transform='scale(1.06)'" onmouseout="this.style.transform='scale(1)'" muted playsinline preload="metadata"></video>
                        <?php elseif ($isDirectVideoUrl && !empty($videoUrl)): ?>
                            <video src="<?= $videoUrl ?>" class="w-100 h-100" style="object-fit: cover; transition: transform 0.35s ease;" onmouseover="this.style.transform='scale(1.06)'" onmouseout="this.style.transform='scale(1)'" muted playsinline preload="metadata"></video>
                        <?php else: ?>
                            <div class="w-100 h-100 d-flex align-items-center justify-content-center" style="background: linear-gradient(135deg,#1a1a2e,#16213e);">
                                <i class="bi bi-image text-white-50" style="font-size:2.5rem;"></i>
                            </div>
                        <?php endif; ?>

                        <?php if ($isEmbeddable || !empty($videoUrl)): ?>
                            <!-- Play overlay -->
                            <div class="position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center" style="background:rgba(0,0,0,0.28); pointer-events:none;">
                                <div style="width:52px;height:52px;background:rgba(255,255,255,0.92);border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 18px rgba(0,0,0,0.35);">
                                    <i class="bi bi-play-fill text-danger" style="font-size:1.6rem; margin-left:3px;"></i>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Date badge -->
                        <span class="badge bg-dark bg-opacity-75 position-absolute top-0 start-0 m-2 small fw-normal" style="z-index:3;">
                            <i class="bi bi-calendar-event me-1"></i> <?= $readableDate ?>
                        </span>

                        <?php if ($totalMedia > 1): ?>
                            <!-- Media count badge -->
                            <span class="badge bg-dark bg-opacity-75 position-absolute bottom-0 end-0 m-2 small fw-normal" style="z-index:3;">
                                <i class="bi bi-images me-1"></i> <?= $totalMedia ?>
                            </span>
                        <?php endif; ?>

                        <?php if ($isEmbeddable && !$hasVideo): ?>
                            <?php if (!empty($youtube_id)): ?>
                                <span class="badge bg-danger position-absolute top-0 end-0 m-2 fw-bold" style="z-index:3;"><i class="bi bi-youtube me-1"></i>YouTube</span>
                            <?php elseif (!empty($vimeo_id)): ?>
                                <span class="badge bg-info position-absolute top-0 end-0 m-2 fw-bold" style="z-index:3;"><i class="bi bi-vimeo me-1"></i>Vimeo</span>
                            <?php elseif ($isDirectVideoUrl): ?>
                                <span class="badge bg-secondary position-absolute top-0 end-0 m-2 fw-bold" style="z-index:3;"><i class="bi bi-play-circle me-1"></i>Video</span>
                            <?php endif; ?>
                        <?php elseif ($hasVideo): ?>
                            <span class="badge bg-danger position-absolute top-0 end-0 m-2 fw-bold" style="z-index:3;"><i class="bi bi-camera-video-fill me-1"></i>Video</span>
                        <?php elseif (!empty($videoUrl) && $isExternalVideoUrl): ?>
                            <span class="badge bg-primary position-absolute top-0 end-0 m-2 fw-bold" style="z-index:3;"><i class="bi bi-link-45deg me-1"></i>Link</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="p-3 d-flex flex-column flex-grow-1">
                        <h6 class="fw-bold text-dark mb-2 news-hover-title" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; cursor:pointer;" <?= $previewAttrs ?>>
                            <?= e($news['title']) ?>
                        </h6>
                        <p class="text-muted mb-3 small" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                            <?= e(mb_substr(strip_tags($news['content']), 0, 100)) ?>...
                        </p>
                        
                        <div class="mt-auto pt-3 border-top d-flex justify-content-between gap-1">
                            <button type="button" class="btn btn-sm btn-light text-info fw-bold flex-fill border rounded-pill" <?= $previewAttrs ?>>
                                <i class="bi bi-eye"></i> ดู
                            </button>
                            <button type="button" class="btn btn-sm btn-light text-primary fw-bold flex-fill border rounded-pill edit-news-btn" data-id="<?= $news['id'] ?>">
                                <i class="bi bi-pencil-square"></i> แก้
                            </button>
                            <a href="delete_news.php?id=<?= $news['id'] ?>" class="btn btn-sm btn-light text-danger fw-bold flex-fill border rounded-pill btn-delete-news" data-title="<?= e($news['title']) ?>">
                                <i class="bi bi-trash"></i> ลบ
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endwhile; else: ?>
                <div class="col-12 text-center py-5">
                    <h5 class="text-muted">ยังไม่มีประกาศข่าวสาร</h5>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal: Add News -->
<div class="modal fade" id="addNewsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 p-4 pb-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-megaphone text-primary me-2"></i>เพิ่มข่าวสารใหม่</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold text-secondary">หัวข้อข่าว <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control border-0 bg-light rounded-3" placeholder="ระบุหัวข้อข่าว..." required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-secondary">รายละเอียดข่าว <span class="text-danger">*</span></label>
                        <textarea name="content" class="form-control border-0 bg-light rounded-3" rows="6" placeholder="ระบุเนื้อหา..." required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-secondary">รูปภาพประกอบ (เลือกได้หลายไฟล์)</label>
                        <input type="file" name="images[]" class="form-control border-0 bg-light rounded-3" accept="image/*" multiple>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-secondary">วิดีโอประกอบ (เลือกได้หลายไฟล์)</label>
                        <div class="p-3 bg-light rounded-3 text-center" style="border: 2px dashed rgba(0,0,0,0.08) !important;">
                            <input type="file" name="videos[]" id="add_video_file" class="form-control border-0 bg-white shadow-sm news-video-input" accept="video/*" multiple>
                            <small class="text-muted d-block mt-2">รองรับไฟล์วิดีโอ MP4, WEBM, OGG, AVI, MOV (สูงสุด 40MB ต่อไฟล์)</small>
                        </div>
                    </div>
                    <div class="mb-3" id="add_video_local_preview_area" style="display:none; background: #f8fafc; padding: 10px; border-radius: 8px;">
                        <label class="form-label fw-bold text-secondary d-block text-start small mb-2"><i class="bi bi-play-circle-fill text-primary"></i> ตัวอย่างวิดีโอที่เลือก</label>
                        <video id="add_video_local_preview" src="" controls class="rounded border shadow-xs w-100" style="max-height: 180px;"></video>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-secondary">ไฟล์เอกสารประกอบ (PDF, DOCX, ฯลฯ เลือกได้หลายไฟล์)</label>
                        <input type="file" name="documents[]" class="form-control border-0 bg-light rounded-3" accept=".pdf,.doc,.docx,.xls,.xlsx,.zip,.rar" multiple>
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-bold text-secondary">ลิงก์วิดีโอประชาสัมพันธ์ (ถ้ามี เช่น YouTube)</label>
                        <input type="url" name="video_url" id="add_video_url" class="form-control border-0 bg-light rounded-3 news-video-url-input" placeholder="ระบุ URL วิดีโอ เช่น https://www.youtube.com/watch?v=...">
                        <div class="mt-2 text-center" id="add_video_url_preview_area" style="display:none; background: #f8fafc; padding: 10px; border-radius: 8px;"></div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-staff rounded-pill px-5 shadow">บันทึกข่าวสาร</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Edit News -->
<div class="modal fade" id="editNewsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 p-4 pb-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square text-primary me-2"></i>แก้ไขข่าวสาร</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="news_id" id="edit_news_id">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold text-secondary">หัวข้อข่าว <span class="text-danger">*</span></label>
                        <input type="text" name="title" id="edit_title" class="form-control border-0 bg-light rounded-3" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-secondary">รายละเอียดข่าว <span class="text-danger">*</span></label>
                        <textarea name="content" id="edit_content" class="form-control border-0 bg-light rounded-3" rows="6" required></textarea>
                    </div>
                    
                    <div class="mb-3" id="edit_img_preview_area" style="display:none;">
                        <label class="form-label fw-bold text-secondary">รูปภาพเดิม (เก่า)</label>
                        <div class="position-relative d-inline-block">
                            <img id="edit_img_preview" src="" style="max-height: 120px; border-radius: 10px;" class="border">
                            <div class="mt-2">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="delete_old_image" value="1" id="edit_del_img">
                                    <label class="form-check-label text-danger small" for="edit_del_img">ลบรูปภาพนี้</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3" id="edit_video_preview_area" style="display:none;">
                        <label class="form-label fw-bold text-secondary">วิดีโอเดิม (เก่า)</label>
                        <div class="position-relative d-block">
                            <video id="edit_video_preview" src="" controls style="max-height: 120px; border-radius: 10px;" class="border"></video>
                            <div class="mt-2">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="delete_old_video" value="1" id="edit_del_video">
                                    <label class="form-check-label text-danger small" for="edit_del_video">ลบวิดีโอนี้</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold text-secondary">ไฟล์แนบที่มีอยู่</label>
                        <div id="edit_attachments_list" class="d-flex flex-column gap-2 p-3 bg-white border rounded-3">
                            <!-- Populated via JS -->
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-secondary">เพิ่มรูปภาพ (เลือกได้หลายไฟล์)</label>
                        <input type="file" name="images[]" class="form-control border-0 bg-light rounded-3" accept="image/*" multiple>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-secondary">เพิ่มวิดีโอ (เลือกได้หลายไฟล์)</label>
                        <div class="p-3 bg-light rounded-3 text-center" style="border: 2px dashed rgba(0,0,0,0.08) !important;">
                            <input type="file" name="videos[]" id="edit_video_file" class="form-control border-0 bg-white shadow-sm news-video-input" accept="video/*" multiple>
                            <small class="text-muted d-block mt-2">รองรับไฟล์วิดีโอ MP4, WEBM, OGG, AVI, MOV (สูงสุด 40MB ต่อไฟล์)</small>
                        </div>
                    </div>
                    <div class="mb-3" id="edit_video_local_preview_area" style="display:none; background: #f8fafc; padding: 10px; border-radius: 8px;">
                        <label class="form-label fw-bold text-secondary d-block text-start small mb-2"><i class="bi bi-play-circle-fill text-primary"></i> ตัวอย่างวิดีโอที่เลือกใหม่</label>
                        <video id="edit_video_local_preview" src="" controls class="rounded border shadow-xs w-100" style="max-height: 180px;"></video>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold text-secondary">เพิ่มไฟล์เอกสาร (PDF, DOCX, ฯลฯ เลือกได้หลายไฟล์)</label>
                        <input type="file" name="documents[]" class="form-control border-0 bg-light rounded-3" accept=".pdf,.doc,.docx,.xls,.xlsx,.zip,.rar" multiple>
                    </div>

                    <div class="mb-0">
                        <label class="form-label fw-bold text-secondary">ลิงก์วิดีโอประชาสัมพันธ์ (เช่น YouTube)</label>
                        <input type="url" name="video_url" id="edit_video_url" class="form-control border-0 bg-light rounded-3 news-video-url-input" placeholder="ระบุ URL วิดีโอ เช่น https://www.youtube.com/watch?v=...">
                        <div class="mt-2 text-center" id="edit_video_url_preview_area" style="display:none; background: #f8fafc; padding: 10px; border-radius: 8px;"></div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-staff rounded-pill px-5 shadow">บันทึกการแก้ไข</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Preview Modal -->
<div class="modal fade" id="newsPreviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div id="modalImageContainer" style="display: none; background: #111;">
                <div id="previewCarousel" class="carousel slide" data-bs-ride="false" data-bs-touch="true">
                    <!-- Indicator dots -->
                    <div class="carousel-indicators" id="previewCarouselIndicators" style="bottom:0; margin-bottom:4px;">
                        <!-- Populated via JS -->
                    </div>
                    <div class="carousel-inner" id="previewCarouselInner">
                        <!-- Populated via JS -->
                    </div>
                    <button class="carousel-control-prev" type="button" data-bs-target="#previewCarousel" data-bs-slide="prev" style="width:48px;">
                        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                        <span class="visually-hidden">Previous</span>
                    </button>
                    <button class="carousel-control-next" type="button" data-bs-target="#previewCarousel" data-bs-slide="next" style="width:48px;">
                        <span class="carousel-control-next-icon" aria-hidden="true"></span>
                        <span class="visually-hidden">Next</span>
                    </button>
                </div>
            </div>
            <div id="modalVideoContainer" style="display: none; background: #000;" class="p-3 text-center">
                <!-- Video will be loaded dynamically -->
            </div>
            <div class="modal-header border-0 pb-0">
                <h5 id="modalTitle" class="modal-title fw-bold"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body py-4">
                <span id="modalDate" class="badge bg-light text-primary mb-3"></span>
                <div id="modalContent" class="text-muted lh-lg" style="white-space: pre-wrap;"></div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Helper to get YouTube Embed
    function getYoutubeEmbed(url) {
        var youtubeId = '';
        var regExp = /^.*(youtu.be\/|v\/|u\/\w\/|embed\/|watch\?v=|\&v=)([^#\&\?]*).*/;
        var match = url.match(regExp);
        if (match && match[2].length == 11) {
            youtubeId = match[2];
        }
        if (youtubeId) {
            return '<div class="ratio ratio-16x9 mx-auto" style="max-width: 600px;"><iframe src="https://www.youtube.com/embed/' + youtubeId + '" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe></div>';
        }
        return null;
    }

    // Helper to generate dynamic preview for URLs (YouTube or direct file)
    function showUrlPreview(url, previewAreaId) {
        var $area = $(previewAreaId);
        if (!url) {
            $area.hide().html('');
            return;
        }

        var ytEmbed = getYoutubeEmbed(url);
        if (ytEmbed) {
            $area.html('<div class="small fw-bold text-muted mb-2 text-start"><i class="bi bi-youtube text-danger"></i> ตัวอย่างวิดีโอ YouTube</div>' + ytEmbed).show();
            return;
        }

        var lowerUrl = url.toLowerCase();
        if (lowerUrl.endsWith('.mp4') || lowerUrl.endsWith('.webm') || lowerUrl.endsWith('.ogg')) {
            $area.html('<div class="small fw-bold text-muted mb-2 text-start"><i class="bi bi-play-circle-fill text-primary"></i> ตัวอย่างวิดีโอจากลิงก์</div>' +
                       '<video src="' + url + '" controls class="rounded border shadow-xs" style="max-height: 150px; max-width: 100%;"></video>').show();
            return;
        }

        // Default: display direct link info card
        $area.html('<div class="alert alert-info border-0 shadow-xs text-start mb-0 p-2 small d-flex align-items-center gap-2">' +
                   '<i class="bi bi-info-circle-fill text-info"></i>' +
                   '<div class="text-truncate">ลิงก์วิดีโอภายนอก: <a href="' + url + '" target="_blank" class="text-decoration-underline">' + url + '</a></div>' +
                   '</div>').show();
    }

    // Size limit configuration: 40MB
    var MAX_VIDEO_SIZE = 40 * 1024 * 1024; // 40MB

    function handleLocalVideoSelect(inputElement, previewAreaId, previewElementId) {
        var file = inputElement.files[0];
        var $area = $(previewAreaId);
        var $preview = $(previewElementId);

        if (!file) {
            $area.hide();
            $preview.attr('src', '');
            return;
        }

        // Guard: File Size limit
        if (file.size > MAX_VIDEO_SIZE) {
            Swal.fire({
                title: 'ไฟล์ใหญ่เกินกำหนด!',
                text: 'ไฟล์วิดีโอนี้มีขนาด ' + (file.size / (1024 * 1024)).toFixed(1) + 'MB ซึ่งเกินขีดจำกัดสูงสุด 40MB แนะนำให้อัปโหลดลง YouTube แล้วนำลิงก์มาวางในช่อง URL แทน',
                icon: 'warning',
                confirmButtonColor: '#3085d6',
                confirmButtonText: 'รับทราบ'
            });
            inputElement.value = ''; // Reset input
            $area.hide();
            $preview.attr('src', '');
            return;
        }

        // Set Preview
        try {
            var fileUrl = URL.createObjectURL(file);
            $preview.attr('src', fileUrl);
            $area.show();
        } catch (e) {
            console.error('Error creating video object URL:', e);
            $area.hide();
        }
    }

    // Add Modal Handlers
    $('#add_video_file').on('change', function() {
        handleLocalVideoSelect(this, '#add_video_local_preview_area', '#add_video_local_preview');
    });

    $('#add_video_url').on('input change', function() {
        showUrlPreview($(this).val().trim(), '#add_video_url_preview_area');
    });

    // Reset previews when Add Modal is closed
    $('#addNewsModal').on('hidden.bs.modal', function () {
        $('#add_video_file').val('');
        $('#add_video_local_preview_area').hide();
        $('#add_video_local_preview').attr('src', '');
        $('#add_video_url').val('');
        $('#add_video_url_preview_area').hide().html('');
    });

    // Edit Modal Handlers
    $('#edit_video_file').on('change', function() {
        handleLocalVideoSelect(this, '#edit_video_local_preview_area', '#edit_video_local_preview');
    });

    $('#edit_video_url').on('input change', function() {
        showUrlPreview($(this).val().trim(), '#edit_video_url_preview_area');
    });

    // Clear previews when Edit Modal is closed
    $('#editNewsModal').on('hidden.bs.modal', function () {
        $('#edit_video_file').val('');
        $('#edit_video_local_preview_area').hide();
        $('#edit_video_local_preview').attr('src', '');
        $('#edit_video_url_preview_area').hide().html('');
    });

    // ======= Preview Modal Logic =======
    $('#newsPreviewModal').on('show.bs.modal', function (e) {
        var b = $(e.relatedTarget);
        $('#modalTitle').text(b.data('full-title'));
        $('#modalContent').text(b.data('full-content'));
        $('#modalDate').html('<i class="bi bi-calendar-event me-1"></i> ' + b.data('full-date'));
        
        // Build media carousel
        var media = b.data('media') || [];
        var $carouselInner = $('#previewCarouselInner');
        var $indicators = $('#previewCarouselIndicators');
        $carouselInner.empty();
        $indicators.empty();

        function buildMediaHtml(item) {
            if (item.type === 'image') {
                return '<div class="d-flex align-items-center justify-content-center" style="min-height:220px;background:#111;">' +
                       '<img src="' + item.src + '" class="img-fluid" style="max-height:460px;object-fit:contain;"></div>';
            } else if (item.type === 'video') {
                return '<div class="ratio ratio-16x9"><video src="' + item.src + '" controls class="w-100 h-100"></video></div>';
            } else if (item.type === 'youtube') {
                return '<div class="ratio ratio-16x9"><iframe src="https://www.youtube.com/embed/' + item.src +
                       '?rel=0" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen class="w-100 h-100"></iframe></div>';
            } else if (item.type === 'vimeo') {
                return '<div class="ratio ratio-16x9"><iframe src="https://player.vimeo.com/video/' + item.src +
                       '" frameborder="0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen class="w-100 h-100"></iframe></div>';
            } else if (item.type === 'direct_video') {
                return '<div class="ratio ratio-16x9"><video src="' + item.src + '" controls class="w-100 h-100"></video></div>';
            } else if (item.type === 'external_link') {
                return '<div class="d-flex align-items-center justify-content-center p-4" style="min-height:220px;background:#111;">' +
                       '<div class="text-center">' +
                       '<i class="bi bi-play-btn-fill text-danger" style="font-size:3rem;"></i>' +
                       '<div class="mt-3"><a href="' + item.src + '" target="_blank" class="btn btn-light btn-sm">' +
                       '<i class="bi bi-box-arrow-up-right me-1"></i>เปิดลิงก์วิดีโอ</a></div>' +
                       '<div class="text-white-50 small mt-2 text-break px-3" style="max-width:400px;">' + item.src + '</div>' +
                       '</div></div>';
            }
            return '';
        }

        if (media.length > 0) {
            $.each(media, function(i, item) {
                var activeClass = (i === 0) ? 'active' : '';
                // Indicator dot
                var iconClass = 'bi-circle-fill';
                if (item.type === 'image') iconClass = 'bi-image';
                else if (item.type === 'video' || item.type === 'direct_video' || item.type === 'youtube' || item.type === 'vimeo') iconClass = 'bi-play-circle-fill';
                else if (item.type === 'external_link') iconClass = 'bi-link-45deg';

                $indicators.append(
                    '<button type="button" data-bs-target="#previewCarousel" data-bs-slide-to="' + i + '"' +
                    (i === 0 ? ' class="active" aria-current="true"' : '') +
                    ' aria-label="Slide ' + (i+1) + '"></button>'
                );

                $carouselInner.append(
                    '<div class="carousel-item ' + activeClass + '" style="background:#111;">' +
                    buildMediaHtml(item) +
                    '</div>'
                );
            });

            $('#modalImageContainer').show();
            // Show/hide nav arrows
            if (media.length > 1) {
                $('#previewCarousel .carousel-control-prev, #previewCarousel .carousel-control-next').show();
                $indicators.show();
            } else {
                $('#previewCarousel .carousel-control-prev, #previewCarousel .carousel-control-next').hide();
                $indicators.hide();
            }
        } else {
            $('#modalImageContainer').hide();
        }

        // Pause videos/iframes on slide change
        var carouselEl = document.getElementById('previewCarousel');
        if (carouselEl && !carouselEl.dataset.hasListener) {
            carouselEl.addEventListener('slide.bs.carousel', function() {
                carouselEl.querySelectorAll('video').forEach(function(v) { v.pause(); });
                carouselEl.querySelectorAll('iframe').forEach(function(f) { f.src = f.src; });
            });
            carouselEl.dataset.hasListener = 'true';
        }

        $('#modalVideoContainer').hide().html('');
    });

    // Clear video on hide modal to stop playback
    $('#newsPreviewModal').on('hide.bs.modal', function () {
        $('#modalVideoContainer').html('').hide();
    });

    // Edit Modal Fetch
    $('.edit-news-btn').on('click', function() {
        var id = $(this).data('id');
        $.get('get_news.php', { id: id }, function(data) {
            if(data.error) return Swal.fire('Error', data.error, 'error');
            $('#edit_news_id').val(data.id);
            $('#edit_title').val(data.title);
            $('#edit_content').val(data.content);
            $('#edit_del_img').prop('checked', false);
            
            // Image Preview
            if(data.image) {
                $('#edit_img_preview').attr('src', data.image_url);
                $('#edit_img_preview_area').show();
            } else {
                $('#edit_img_preview_area').hide();
            }
            
            // Video Preview
            $('#edit_del_video').prop('checked', false);
            if(data.video) {
                $('#edit_video_preview').attr('src', data.video_url_path);
                $('#edit_video_preview_area').show();
            } else {
                $('#edit_video_preview_area').hide();
            }
            $('#edit_video_url').val(data.video_url || '');
            
            // Reset and trigger remote URL preview if any
            $('#edit_video_local_preview_area').hide();
            $('#edit_video_local_preview').attr('src', '');
            showUrlPreview(data.video_url || '', '#edit_video_url_preview_area');
            
            // Populate attachments
            var $attList = $('#edit_attachments_list');
            $attList.empty();
            if (data.attachments && data.attachments.length > 0) {
                $.each(data.attachments, function(i, att) {
                    var icon = 'bi-file-earmark';
                    if (att.file_type === 'image') icon = 'bi-file-image text-success';
                    else if (att.file_type === 'video') icon = 'bi-film text-danger';
                    else if (att.file_type === 'document') icon = 'bi-file-earmark-text text-primary';
                    
                    var html = '<div class="d-flex justify-content-between align-items-center p-2 border rounded bg-light">' +
                               '<div class="text-truncate" style="max-width: 80%;"><i class="bi ' + icon + ' me-2"></i>' + att.original_name + '</div>' +
                               '<div class="form-check form-switch m-0">' +
                               '<input class="form-check-input" type="checkbox" name="delete_attachments[]" value="' + att.id + '" id="del_att_' + att.id + '">' +
                               '<label class="form-check-label text-danger small" for="del_att_' + att.id + '">ลบ</label>' +
                               '</div></div>';
                    $attList.append(html);
                });
                $attList.parent().show();
            } else {
                $attList.append('<div class="text-muted small text-center">ไม่มีไฟล์แนบเพิ่มเติม</div>');
            }

            $('#editNewsModal').modal('show');
        });
    });

    // Delete Logic
    $('.btn-delete-news').on('click', function(e) {
        e.preventDefault();
        var h = $(this).attr('href');
        Swal.fire({
            title: 'ลบข่าวสาร?',
            text: "ต้องการลบข่าวนี้ใช่หรือไม่?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'ลบเลย'
        }).then((result) => { if (result.isConfirmed) window.location.href = h; });
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>


