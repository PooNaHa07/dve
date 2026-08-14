<?php
date_default_timezone_set('Asia/Bangkok');
require_once __DIR__ . '/includes/configdb.php';
require_once __DIR__ . '/includes/functions.php';
$page_title = 'ข่าวประชาสัมพันธ์';
include __DIR__ . '/includes/header_public.php'; 

// ตรวจสอบว่ามีการส่งค่า ID มาใน URL หรือไม่
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: news_list.php');
    exit;
}

$news_id = (int)$_GET['id'];

// ดึงรายละเอียดข่าว
$stmt = $conn->prepare("SELECT title, content, created_at, image, video, video_url FROM news WHERE id = ?");
if ($stmt === false) {
    die('🚨 SQL Prepare Error: ตรวจสอบชื่อตาราง (news) และคอลัมน์ในฐานข้อมูล (title, content, created_at, image, video, video_url) <br> MySQL Error: ' . $conn->error);
}

$stmt->bind_param("i", $news_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $news_data = null;
} else {
    $news_data = $result->fetch_assoc();
    
    // Fetch attachments
    $attachments = ['image' => [], 'video' => [], 'document' => []];
    $att_stmt = $conn->prepare("SELECT file_name, original_name, file_type FROM news_attachments WHERE news_id = ? ORDER BY id ASC");
    $att_stmt->bind_param("i", $news_id);
    $att_stmt->execute();
    $att_res = $att_stmt->get_result();
    while ($att_row = $att_res->fetch_assoc()) {
        $attachments[$att_row['file_type']][] = $att_row;
    }
    $att_stmt->close();

    // Collect all images for the slideable lightbox gallery
    $all_images = [];
    if (!empty($news_data['image'])) {
        $all_images[] = 'uploads/news/' . $news_data['image'];
    }
    if (!empty($attachments['image'])) {
        foreach ($attachments['image'] as $img) {
            $all_images[] = 'uploads/news/' . $img['file_name'];
        }
    }
    $all_images = array_values(array_unique($all_images));
}
$stmt->close();
?>

<style>
    /* Animated Blobs Background matching Landing Page & Calendar & News List */
    .bg-blob {
        position: fixed;
        width: 600px;
        height: 600px;
        filter: blur(140px);
        z-index: -1;
        opacity: 0.12;
        pointer-events: none;
        border-radius: 50%;
        animation: floatBlob 18s infinite alternate ease-in-out;
    }
    .blob-1 { background: #6366f1; top: -150px; right: -150px; }
    .blob-2 { background: #d946ef; bottom: -150px; left: -150px; animation-delay: -5s; }

    @keyframes floatBlob {
        0% { transform: translate(0, 0) scale(1); }
        100% { transform: translate(80px, 50px) scale(1.1); }
    }

    /* Premium News Detail Card Container */
    .premium-news-container {
        max-width: 900px;
        margin: 3rem auto;
    }

    .pastel-news-card {
        background: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid rgba(226, 232, 240, 0.8);
        border-radius: 32px;
        overflow: hidden;
        box-shadow: 0 25px 55px rgba(99, 102, 241, 0.08);
        transition: all 0.3s ease;
    }

    .pastel-news-card:hover {
        box-shadow: 0 35px 70px rgba(99, 102, 241, 0.12);
    }

    .news-img-container {
        position: relative;
        max-height: 480px;
        overflow: hidden;
    }

    .news-img-container img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.5s ease;
    }

    .pastel-news-card:hover .news-img-container img {
        transform: scale(1.02);
    }

    .news-date-badge {
        position: absolute;
        bottom: 1.5rem;
        left: 1.5rem;
        background: rgba(15, 23, 42, 0.85);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        color: white;
        padding: 0.6rem 1.5rem;
        border-radius: 50rem;
        font-size: 0.9rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 0.6rem;
        box-shadow: 0 8px 20px rgba(0,0,0,0.2);
        border: 1px solid rgba(255, 255, 255, 0.15);
    }

    .news-body-content {
        padding: 3rem;
    }

    .news-detail-title {
        font-size: 2.25rem;
        font-weight: 850;
        color: #0f172a;
        line-height: 1.35;
        letter-spacing: -0.5px;
        position: relative;
        padding-bottom: 1rem;
        margin-bottom: 2rem;
    }

    .news-detail-title::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        width: 80px;
        height: 4px;
        background: linear-gradient(90deg, #6366f1, #d946ef);
        border-radius: 2px;
    }

    .news-content {
        font-size: 1.1rem;
        color: #475569;
        line-height: 1.85;
    }

    /* Back Button Style */
    .btn-back-list {
        background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
        color: white;
        border: none;
        padding: 0.85rem 2rem;
        font-weight: 700;
        border-radius: 16px;
        transition: all 0.3s;
        text-decoration: none;
        box-shadow: 0 4px 15px rgba(99, 102, 241, 0.2);
        display: inline-flex;
        align-items: center;
        gap: 0.6rem;
    }

    .btn-back-list:hover {
        background: linear-gradient(135deg, #4f46e5 0%, #3730a3 100%);
        transform: translateY(-2px);
        box-shadow: 0 10px 25px rgba(79, 70, 229, 0.3);
        color: white;
    }
</style>

<div class="bg-blob blob-1"></div>
<div class="bg-blob blob-2"></div>

<div class="container premium-news-container">
    <?php if ($news_data): ?>
        <div class="pastel-news-card">
            <?php
            $hasImg = !empty($news_data['image']) || !empty($attachments['image']);
            $imgSrc = !empty($news_data['image']) ? 'uploads/news/' . e($news_data['image']) : (!empty($attachments['image']) ? 'uploads/news/' . e($attachments['image'][0]['file_name']) : '');
            
            $hasVideoFile = !empty($news_data['video']) || !empty($attachments['video']);
            $videoFileSrc = !empty($news_data['video']) ? 'uploads/news/' . e($news_data['video']) : (!empty($attachments['video']) ? 'uploads/news/' . e($attachments['video'][0]['file_name']) : '');
            $hasVideoUrl = !empty($news_data['video_url']);
            
            $youtube_id = '';
            $vimeo_id = '';
            $isDirectVideoUrl = false;
            $isExternalVideoUrl = false;
            
            if ($hasVideoUrl) {
                $url = $news_data['video_url'];
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
            
            $isEmbeddable = ($hasVideoFile || !empty($youtube_id) || !empty($vimeo_id) || $isDirectVideoUrl);
            
            // Compile unified media list for lightbox gallery carousel (images + videos)
            $all_media = [];
            // 1. Legacy cover image
            if (!empty($news_data['image'])) {
                $all_media[] = ['type' => 'image', 'src' => 'uploads/news/' . e($news_data['image'])];
            }
            // 2. Attachment images (avoid duplicate cover)
            if (!empty($attachments['image'])) {
                foreach ($attachments['image'] as $img) {
                    $path = 'uploads/news/' . $img['file_name'];
                    $dup = false;
                    foreach ($all_media as $m) {
                        if ($m['type'] === 'image' && $m['src'] === $path) { $dup = true; break; }
                    }
                    if (!$dup) $all_media[] = ['type' => 'image', 'src' => $path];
                }
            }
            // 3. Legacy video file
            if ($hasVideoFile && !empty($videoFileSrc)) {
                $all_media[] = ['type' => 'video', 'src' => $videoFileSrc];
            }
            // 4. Attachment videos (avoid duplicate)
            if (!empty($attachments['video'])) {
                foreach ($attachments['video'] as $vid) {
                    $path = 'uploads/news/' . $vid['file_name'];
                    $dup = false;
                    foreach ($all_media as $m) {
                        if ($m['type'] === 'video' && $m['src'] === $path) { $dup = true; break; }
                    }
                    if (!$dup) $all_media[] = ['type' => 'video', 'src' => $path];
                }
            }
            // 5. Video URL (YouTube / Vimeo / Direct / External)
            if (!empty($youtube_id)) {
                $all_media[] = ['type' => 'youtube', 'src' => $youtube_id];
            } elseif (!empty($vimeo_id)) {
                $all_media[] = ['type' => 'vimeo', 'src' => $vimeo_id];
            } elseif ($isDirectVideoUrl && !empty($news_data['video_url'])) {
                $all_media[] = ['type' => 'direct_video', 'src' => $news_data['video_url']];
            } elseif ($isExternalVideoUrl && !empty($news_data['video_url'])) {
                $all_media[] = ['type' => 'external_link', 'src' => $news_data['video_url']];
            }
            
            $headerMedia = 'none';
            
            if ($hasImg):
                $headerMedia = 'image';
            ?>
                <!-- Standard Image Header -->
                <div class="news-img-container" style="cursor: pointer;" onclick="openLightbox(0)">
                    <img src="<?= $imgSrc ?>" alt="<?= e($news_data['title']) ?>">
                    <div class="news-date-badge">
                        <i class="bi bi-clock"></i>
                        เผยแพร่เมื่อ: <?= date('d/m/Y H:i', strtotime($news_data['created_at'])) ?>
                    </div>
                </div>
            <?php elseif ($isEmbeddable): ?>
                <!-- Video Header Cover if no image -->
                <div class="news-img-container border-bottom bg-black position-relative" style="max-height: 480px;">
                    <?php if ($hasVideoFile): 
                        $headerMedia = 'local_video';
                    ?>
                        <div class="ratio ratio-16x9 w-100 mx-auto">
                            <video src="<?= $videoFileSrc ?>" controls class="w-100 h-100" style="object-fit: contain; background: #000;"></video>
                        </div>
                    <?php elseif (!empty($youtube_id)): 
                        $headerMedia = 'url_video';
                    ?>
                        <div class="ratio ratio-16x9 w-100 mx-auto">
                            <iframe src="https://www.youtube.com/embed/<?= $youtube_id ?>" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen class="w-100 h-100"></iframe>
                        </div>
                    <?php elseif (!empty($vimeo_id)): 
                        $headerMedia = 'url_video';
                    ?>
                        <div class="ratio ratio-16x9 w-100 mx-auto">
                            <iframe src="https://player.vimeo.com/video/<?= $vimeo_id ?>" title="Vimeo video player" frameborder="0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen class="w-100 h-100"></iframe>
                        </div>
                    <?php elseif ($isDirectVideoUrl): 
                        $headerMedia = 'url_video';
                    ?>
                        <div class="ratio ratio-16x9 w-100 mx-auto">
                            <video src="<?= e($news_data['video_url']) ?>" controls class="w-100 h-100" style="object-fit: contain; background: #000;"></video>
                        </div>
                    <?php endif; ?>
                    
                    <div class="news-date-badge shadow" style="bottom: 1rem; left: 1rem; z-index: 2;">
                        <i class="bi bi-clock-fill text-danger me-1"></i>
                        เผยแพร่เมื่อ: <?= date('d/m/Y H:i', strtotime($news_data['created_at'])) ?>
                    </div>
                </div>
            <?php else: ?>
                <!-- Standard Placeholder Header -->
                <div class="news-img-container" style="height: 120px; background: linear-gradient(135deg, rgba(99, 102, 241, 0.05) 0%, rgba(217, 70, 239, 0.05) 100%);">
                    <div class="news-date-badge">
                        <i class="bi bi-clock"></i>
                        เผยแพร่เมื่อ: <?= date('d/m/Y H:i', strtotime($news_data['created_at'])) ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="news-body-content">
                <h1 class="news-detail-title"><?= e($news_data['title']) ?></h1>

                <!-- In-Body Media (Only if not already displayed in header cover) -->
                <?php if ($hasVideoFile && $headerMedia !== 'local_video'): ?>
                    <div class="mb-4 text-center">
                        <h6 class="fw-bold text-secondary text-start mb-2"><i class="bi bi-play-circle-fill text-danger me-2"></i>วิดีโอแนบประชาสัมพันธ์</h6>
                        <div class="ratio ratio-16x9 border rounded shadow-sm overflow-hidden bg-black mx-auto" style="max-width: 100%;">
                            <video src="<?= $videoFileSrc ?>" controls class="w-100 h-100" style="object-fit: contain;"></video>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($hasVideoUrl && $isEmbeddable && $headerMedia !== 'url_video'): ?>
                    <div class="mb-4 text-center">
                        <h6 class="fw-bold text-secondary text-start mb-2"><i class="bi bi-youtube text-danger me-2"></i>วิดีโอแนะนำ/YouTube</h6>
                        <?php if (!empty($youtube_id)): ?>
                            <div class="ratio ratio-16x9 border rounded shadow-sm overflow-hidden bg-black mx-auto">
                                <iframe src="https://www.youtube.com/embed/<?= $youtube_id ?>" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen class="w-100 h-100"></iframe>
                            </div>
                        <?php elseif (!empty($vimeo_id)): ?>
                            <div class="ratio ratio-16x9 border rounded shadow-sm overflow-hidden bg-black mx-auto">
                                <iframe src="https://player.vimeo.com/video/<?= $vimeo_id ?>" title="Vimeo video player" frameborder="0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen class="w-100 h-100"></iframe>
                            </div>
                        <?php elseif ($isDirectVideoUrl): ?>
                            <div class="ratio ratio-16x9 border rounded shadow-sm overflow-hidden bg-black mx-auto">
                                <video src="<?= e($news_data['video_url']) ?>" controls class="w-100 h-100" style="object-fit: contain;"></video>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- External/Non-embeddable Video Link Card (Only if applicable) -->
                <?php if ($isExternalVideoUrl): ?>
                    <div class="alert alert-light border d-flex align-items-center gap-3 p-3 rounded-3 mb-4 shadow-sm">
                        <i class="bi bi-play-btn-fill text-danger fs-1"></i>
                        <div>
                            <div class="fw-bold text-dark">ลิงก์วิดีโอประชาสัมพันธ์ภายนอก</div>
                            <a href="<?= e($news_data['video_url']) ?>" target="_blank" class="text-primary small text-break fw-semibold d-inline-flex align-items-center gap-1">
                                <?= e($news_data['video_url']) ?> 
                                <i class="bi bi-box-arrow-up-right small"></i>
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="news-content">
                    <?= nl2br(e($news_data['content'])) ?>
                </div>

                <!-- Attachments Section -->
                <?php if (!empty($attachments['image'])): ?>
                    <div class="mt-5">
                        <h5 class="fw-bold mb-3"><i class="bi bi-images text-success me-2"></i>แกลเลอรีรูปภาพ</h5>
                        <div class="row g-3">
                            <?php foreach ($attachments['image'] as $img): 
                                $img_path = 'uploads/news/' . $img['file_name'];
                                $idx = 0;
                                foreach ($all_media as $m_idx => $m_item) {
                                    if ($m_item['type'] === 'image' && $m_item['src'] === $img_path) {
                                        $idx = $m_idx;
                                        break;
                                    }
                                }
                            ?>
                                <div class="col-6 col-md-4">
                                    <div onclick="openLightbox(<?= $idx ?>)" class="d-block border rounded overflow-hidden shadow-sm" style="height: 150px; cursor: pointer;">
                                        <img src="<?= $img_path ?>" alt="Image" class="w-100 h-100 object-fit-cover transition-transform hover-scale">
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($attachments['video'])): ?>
                    <div class="mt-5">
                        <h5 class="fw-bold mb-3"><i class="bi bi-film text-danger me-2"></i>วิดีโอเพิ่มเติม</h5>
                        <div class="row g-4">
                            <?php foreach ($attachments['video'] as $idx => $vid): 
                                $vid_path = 'uploads/news/' . $vid['file_name'];
                                // Skip if already shown as header video
                                if (!empty($news_data['video']) && $vid['file_name'] === $news_data['video'] && $idx === 0) continue;
                                $idx_vid = 0;
                                foreach ($all_media as $m_idx => $m_item) {
                                    if ($m_item['type'] === 'video' && $m_item['src'] === $vid_path) {
                                        $idx_vid = $m_idx;
                                        break;
                                    }
                                }
                            ?>
                                <div class="col-md-6">
                                    <div class="ratio ratio-16x9 border rounded shadow-sm overflow-hidden bg-black position-relative" style="cursor: pointer;" onclick="openLightbox(<?= $idx_vid ?>)">
                                        <video src="<?= $vid_path ?>" class="w-100 h-100" style="object-fit: cover;"></video>
                                        <div class="position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center bg-dark bg-opacity-40">
                                            <i class="bi bi-play-circle-fill text-white fs-1 opacity-75"></i>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($attachments['document'])): ?>
                    <div class="mt-5">
                        <h5 class="fw-bold mb-3"><i class="bi bi-file-earmark-arrow-down text-primary me-2"></i>เอกสารแนบ</h5>
                        <div class="list-group shadow-sm">
                            <?php foreach ($attachments['document'] as $doc): ?>
                                <a href="uploads/news/<?= e($doc['file_name']) ?>" target="_blank" class="list-group-item list-group-item-action d-flex align-items-center gap-3 py-3">
                                    <i class="bi bi-file-earmark-text fs-3 text-secondary"></i>
                                    <div class="flex-grow-1 text-truncate">
                                        <h6 class="mb-0 fw-bold text-dark"><?= e($doc['original_name']) ?></h6>
                                        <small class="text-muted">คลิกเพื่อดาวน์โหลดหรือเปิดดูเอกสาร</small>
                                    </div>
                                    <i class="bi bi-download text-primary"></i>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="mt-5 pt-4 border-top text-center">
                    <a href="news_list.php" class="btn-back-list">
                        <i class="bi bi-arrow-left"></i>
                        <span>กลับหน้ารายการข่าวสาร</span>
                    </a>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-warning text-center rounded-3">
            ไม่พบข่าวสารที่คุณต้องการ
        </div>
        <div class="text-center mt-3">
            <a href="news_list.php" class="btn-back-list">
                <i class="bi bi-arrow-left"></i> กลับหน้ารายการข่าวสาร
            </a>
        </div>
    <?php endif; ?>
</div>

<!-- Lightbox Modal -->
<div class="modal fade" id="lightboxModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 bg-transparent">
            <div class="modal-header border-0 p-0 position-absolute top-0 end-0 m-3" style="z-index: 1055;">
                <button type="button" class="btn-close btn-close-white fs-4 bg-dark bg-opacity-50 p-2 rounded-circle shadow" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div id="lightboxCarousel" class="carousel slide" data-bs-ride="false">
                    <div class="carousel-inner" id="lightboxCarouselInner">
                        <!-- Dynamically populated by JS -->
                    </div>
                    <button class="carousel-control-prev" type="button" data-bs-target="#lightboxCarousel" data-bs-slide="prev">
                        <span class="carousel-control-prev-icon bg-dark bg-opacity-50 p-3 rounded-circle shadow" aria-hidden="true"></span>
                        <span class="visually-hidden">Previous</span>
                    </button>
                    <button class="carousel-control-next" type="button" data-bs-target="#lightboxCarousel" data-bs-slide="next">
                        <span class="carousel-control-next-icon bg-dark bg-opacity-50 p-3 rounded-circle shadow" aria-hidden="true"></span>
                        <span class="visually-hidden">Next</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
var lightboxMedia = <?= json_encode($all_media ?? []) ?>;
function openLightbox(index) {
    var inner = document.getElementById('lightboxCarouselInner');
    if (!inner) return;
    inner.innerHTML = '';
    
    lightboxMedia.forEach(function(media, i) {
        var activeClass = (i === index) ? 'active' : '';
        var item = document.createElement('div');
        item.className = 'carousel-item ' + activeClass;
        
        var html = '';
        if (media.type === 'image') {
            html = '<img src="' + media.src + '" class="d-block w-100 object-fit-contain" style="max-height: 80vh; background: rgba(0,0,0,0.95); border-radius: 12px;">';
        } else if (media.type === 'video') {
            html = '<div class="ratio ratio-16x9 mx-auto" style="max-height: 80vh; background: #000; border-radius: 12px; overflow: hidden;">' +
                   '<video src="' + media.src + '" controls class="w-100 h-100"></video>' +
                   '</div>';
        } else if (media.type === 'youtube') {
            html = '<div class="ratio ratio-16x9 mx-auto" style="max-height: 80vh; background: #000; border-radius: 12px; overflow: hidden;">' +
                   '<iframe src="https://www.youtube.com/embed/' + media.src + '?rel=0" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen class="w-100 h-100"></iframe>' +
                   '</div>';
        } else if (media.type === 'vimeo') {
            html = '<div class="ratio ratio-16x9 mx-auto" style="max-height: 80vh; background: #000; border-radius: 12px; overflow: hidden;">' +
                   '<iframe src="https://player.vimeo.com/video/' + media.src + '" title="Vimeo video player" frameborder="0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen class="w-100 h-100"></iframe>' +
                   '</div>';
        } else if (media.type === 'direct_video') {
            html = '<div class="ratio ratio-16x9 mx-auto" style="max-height: 80vh; background: #000; border-radius: 12px; overflow: hidden;">' +
                   '<video src="' + media.src + '" controls class="w-100 h-100"></video>' +
                   '</div>';
        } else if (media.type === 'external_link') {
            html = '<div class="d-flex align-items-center justify-content-center p-4" style="min-height:300px; background:#111; border-radius:12px;">' +
                   '<div class="text-center">' +
                   '<i class="bi bi-play-btn-fill text-danger" style="font-size:3rem;"></i>' +
                   '<div class="mt-3"><a href="' + media.src + '" target="_blank" class="btn btn-light btn-sm">' +
                   '<i class="bi bi-box-arrow-up-right me-1"></i>เปิดลิงก์วิดีโอ</a></div>' +
                   '<div class="text-white-50 small mt-2 text-break px-3" style="max-width:400px;">' + media.src + '</div>' +
                   '</div></div>';
        }
        
        item.innerHTML = html;
        inner.appendChild(item);
    });
    
    // Stop any playing video when sliding to a different slide
    var carouselEl = document.getElementById('lightboxCarousel');
    if (carouselEl && !carouselEl.dataset.hasListener) {
        carouselEl.addEventListener('slide.bs.carousel', function() {
            var videos = inner.querySelectorAll('video');
            videos.forEach(function(video) {
                video.pause();
            });
            var iframes = inner.querySelectorAll('iframe');
            iframes.forEach(function(iframe) {
                var src = iframe.src;
                iframe.src = src;
            });
        });
        carouselEl.dataset.hasListener = "true";
    }
    
    // Toggle next/prev controls based on media count
    var prevBtn = document.querySelector('#lightboxCarousel .carousel-control-prev');
    var nextBtn = document.querySelector('#lightboxCarousel .carousel-control-next');
    if (prevBtn && nextBtn) {
        if (lightboxMedia.length > 1) {
            prevBtn.style.display = '';
            nextBtn.style.display = '';
        } else {
            prevBtn.style.display = 'none';
            nextBtn.style.display = 'none';
        }
    }
    
    var modalEl = document.getElementById('lightboxModal');
    var myModal = new bootstrap.Modal(modalEl);
    myModal.show();
}
</script>

<?php 
include __DIR__ . '/includes/footer.php';
include __DIR__ . '/includes/footer_close.php';
?>