<?php
// news_list.php (ไฟล์สำหรับผู้ใช้ทั่วไป)
date_default_timezone_set('Asia/Bangkok');
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/configdb.php';
$page_title = 'ข่าวประชาสัมพันธ์';

// Smart Header
if (is_logged_in() && in_array(get_current_role(), ['admin', 'staff', 'officer', 'teacher', 'director'])) {
    include __DIR__ . '/includes/header.php';
} else {
    include __DIR__ . '/includes/header_public.php';
}
?>

<style>
    /* Animated Blobs Background matching Landing Page & Calendar */
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

    /* Premium Header block */
    .news-header {
        text-align: center;
        padding: 3rem 1rem 1.5rem;
        position: relative;
    }

    .news-icon-box {
        width: 80px;
        height: 80px;
        background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%);
        border-radius: 22px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 2.25rem;
        margin-bottom: 1.25rem;
        box-shadow: 0 15px 30px rgba(99, 102, 241, 0.25);
        animation: pulseIcon 3s infinite alternate ease-in-out;
        position: relative;
    }

    @keyframes pulseIcon {
        0% { transform: scale(1); box-shadow: 0 15px 30px rgba(99, 102, 241, 0.25); }
        100% { transform: scale(1.05); box-shadow: 0 20px 40px rgba(168, 85, 247, 0.35); }
    }

    .news-title {
        font-size: 2.25rem;
        font-weight: 850;
        letter-spacing: -0.5px;
        margin-bottom: 0.5rem;
        color: #0f172a;
    }

    .news-subtitle {
        font-size: 1.05rem;
        font-weight: 500;
        color: #64748b;
        max-width: 600px;
        margin: 0 auto;
    }

    /* Interactive Live Search Filter */
    .search-container {
        max-width: 600px;
        margin: -1rem auto 3.5rem;
        position: relative;
        z-index: 10;
    }

    .search-input-box {
        background: white;
        border: 1px solid rgba(226, 232, 240, 0.8);
        border-radius: 50rem;
        padding: 0.6rem 0.6rem 0.6rem 1.75rem;
        box-shadow: 0 15px 35px rgba(15, 23, 42, 0.08);
        display: flex;
        align-items: center;
        gap: 0.75rem;
        transition: all 0.3s ease;
    }

    .search-input-box:focus-within {
        border-color: #6366f1;
        box-shadow: 0 15px 35px rgba(99, 102, 241, 0.15);
        transform: translateY(-2px);
    }

    .search-input-box input {
        border: none;
        outline: none;
        width: 100%;
        font-size: 1rem;
        color: #1e293b;
        font-weight: 500;
    }

    .search-input-box i.bi-search {
        color: #94a3b8;
        font-size: 1.1rem;
    }

    /* Premium News Grid & Cards */
    .news-card {
        background: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(15px);
        -webkit-backdrop-filter: blur(15px);
        border: 1px solid rgba(226, 232, 240, 0.8);
        border-radius: 24px;
        overflow: hidden;
        transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
        height: 100%;
        display: flex;
        flex-direction: column;
        position: relative;
    }

    .news-card:hover {
        transform: translateY(-10px);
        box-shadow: 0 30px 60px rgba(99, 102, 241, 0.12);
        border-color: rgba(99, 102, 241, 0.3);
    }

    .news-img-container {
        position: relative;
        overflow: hidden;
        height: 220px;
        background: #f1f5f9;
    }

    .news-img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.6s cubic-bezier(0.165, 0.84, 0.44, 1);
    }

    .news-card:hover .news-img {
        transform: scale(1.06);
    }

    .news-date-badge {
        position: absolute;
        bottom: 1.25rem;
        left: 1.25rem;
        background: rgba(15, 23, 42, 0.8);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        color: white;
        padding: 0.5rem 1.25rem;
        border-radius: 50rem;
        font-size: 0.85rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        box-shadow: 0 8px 16px rgba(0,0,0,0.15);
        border: 1px solid rgba(255, 255, 255, 0.1);
    }

    .news-body {
        padding: 2rem;
        display: flex;
        flex-direction: column;
        flex-grow: 1;
    }

    .news-card-title {
        font-size: 1.3rem;
        font-weight: 800;
        color: #0f172a;
        line-height: 1.45;
        margin-bottom: 0.75rem;
        transition: color 0.25s;
    }

    .news-card:hover .news-card-title {
        color: #6366f1;
    }

    .news-desc {
        font-size: 0.95rem;
        color: #64748b;
        line-height: 1.6;
        margin-bottom: 1.75rem;
        flex-grow: 1;
    }

    .btn-view-details {
        background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
        color: white;
        border: none;
        border-radius: 14px;
        padding: 0.75rem 1.5rem;
        font-weight: 700;
        font-size: 0.9rem;
        text-align: center;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.6rem;
        transition: all 0.3s;
        width: 100%;
        text-decoration: none;
        box-shadow: 0 4px 12px rgba(99, 102, 241, 0.15);
    }

    .btn-view-details:hover {
        background: linear-gradient(135deg, #4f46e5 0%, #3730a3 100%);
        transform: translateY(-2px);
        box-shadow: 0 10px 20px rgba(79, 70, 229, 0.25);
        color: white;
    }

    /* Premium Empty State */
    .empty-state-card {
        background: rgba(255, 255, 255, 0.75);
        backdrop-filter: blur(15px);
        -webkit-backdrop-filter: blur(15px);
        border: 1px solid rgba(226, 232, 240, 0.8);
        border-radius: 32px;
        padding: 5rem 2rem;
        text-align: center;
        box-shadow: 0 15px 35px rgba(0,0,0,0.03);
        margin: 2rem 0;
    }

    .empty-icon-box {
        width: 110px;
        height: 110px;
        background: #eef2ff;
        color: #6366f1;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 3.5rem;
        margin-bottom: 2rem;
        box-shadow: 0 10px 25px rgba(99, 102, 241, 0.1);
    }

    .btn-back-home {
        background: #ffffff;
        color: #475569;
        border: 1px solid #e2e8f0;
        padding: 0.75rem 2rem;
        font-weight: 700;
        border-radius: 14px;
        transition: all 0.25s;
        text-decoration: none;
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.04);
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }

    .btn-back-home:hover {
        background: #f8fafc;
        border-color: #cbd5e1;
        color: #1e293b;
        transform: translateX(-3px);
    }

    /* Glowing play button overlay for video cards */
    .video-play-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(15, 23, 42, 0.45);
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0;
        transition: all 0.3s ease;
        z-index: 1;
    }

    .play-icon-btn {
        width: 60px;
        height: 60px;
        background: rgba(255, 255, 255, 0.9);
        color: #ef4444; /* Red play button */
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.85rem;
        box-shadow: 0 10px 25px rgba(239, 68, 68, 0.4);
        transform: scale(0.8);
        transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }

    .news-card:hover .video-play-overlay {
        opacity: 1;
    }

    .news-card:hover .play-icon-btn {
        transform: scale(1);
        box-shadow: 0 15px 30px rgba(239, 68, 68, 0.6);
    }

    /* Animation for pulsing video badge */
    @keyframes pulseBadge {
        0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4); }
        70% { transform: scale(1.03); box-shadow: 0 0 0 8px rgba(239, 68, 68, 0); }
        100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
    }
    .animate-pulse {
        animation: pulseBadge 2s infinite;
    }

    /* Filter buttons premium styling */
    .btn-filter {
        background: rgba(255, 255, 255, 0.85);
        border: 1px solid rgba(226, 232, 240, 0.8);
        color: #475569;
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
    }
    .btn-filter:hover {
        background: #f1f5f9;
        color: #0f172a;
        border-color: #cbd5e1;
        transform: translateY(-1px);
    }
    .btn-filter.active {
        background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
        color: white !important;
        border-color: transparent;
        box-shadow: 0 8px 20px rgba(99, 102, 241, 0.3);
    }
</style>

<div class="bg-blob blob-1"></div>
<div class="bg-blob blob-2"></div>

<div class="container py-4">
    <!-- Premium Header Block -->
    <div class="news-header">
        <div class="news-icon-box">
            <i class="bi bi-newspaper text-white"></i>
        </div>
        <h1 class="news-title">ข่าวประชาสัมพันธ์</h1>
        <p class="news-subtitle">ติดตามประกาศ ข่าวสาร และความเคลื่อนไหวกิจกรรมทวิภาคีล่าสุดจากงานอาชีวศึกษาระบบทวิภาคี</p>
    </div>

    <!-- Live Interactive Search Box -->
    <div class="search-container">
        <div class="search-input-box">
            <i class="bi bi-search"></i>
            <input type="text" id="newsSearch" placeholder="ค้นหาประกาศ ข่าวสารประชาสัมพันธ์...">
        </div>
    </div>

    <!-- Category Filters Row -->
    <div class="d-flex justify-content-center flex-wrap gap-2 mb-5 animate-fade-in" style="margin-top: -2rem; position: relative; z-index: 10;">
        <button type="button" class="btn btn-filter active px-4 py-2 rounded-pill shadow-xs" data-filter="all" style="font-weight: 600; font-size: 0.9rem; transition: all 0.25s;">
            <i class="bi bi-grid-fill me-1"></i> ทั้งหมด
        </button>
        <button type="button" class="btn btn-filter px-4 py-2 rounded-pill shadow-xs" data-filter="video" style="font-weight: 600; font-size: 0.9rem; transition: all 0.25s;">
            <i class="bi bi-play-circle-fill text-danger me-1"></i> เฉพาะข่าวที่มีวิดีโอ
        </button>
        <button type="button" class="btn btn-filter px-4 py-2 rounded-pill shadow-xs" data-filter="general" style="font-weight: 600; font-size: 0.9rem; transition: all 0.25s;">
            <i class="bi bi-newspaper text-primary me-1"></i> ข่าวสารทั่วไป
        </button>
    </div>

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
    ?>
        <div class="row g-4" id="newsGrid">
            <?php while ($news = $result->fetch_assoc()): 
                $hasVideoFile = (!empty($news['video']) || !empty($news['cover_video']));
                $videoFileSrc = !empty($news['video']) ? 'uploads/news/' . e($news['video']) : (!empty($news['cover_video']) ? 'uploads/news/' . e($news['cover_video']) : '');
                $hasVideo = ($hasVideoFile || !empty($news['video_url']));
                
                $imageUrl = 'images/no-image.png';
                if (!empty($news['cover_image'])) {
                    $imageUrl = 'uploads/news/' . e($news['cover_image']);
                } elseif (!empty($news['image'])) {
                    $imageUrl = 'uploads/news/' . e($news['image']);
                }

                // Parse video details
                $hasVideoUrl = !empty($news['video_url']);
                $youtube_id = '';
                $vimeo_id = '';
                $isDirectVideoUrl = false;
                $isExternalVideoUrl = false;

                if ($hasVideoUrl) {
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
                $isEmbeddable = ($hasVideoFile || !empty($youtube_id) || !empty($vimeo_id) || $isDirectVideoUrl);
            ?>
                <div class="col-md-6 col-lg-4 news-item-card" data-has-video="<?= $hasVideo ? '1' : '0' ?>">
                    <div class="news-card">
                        <?php if ($hasVideo && $isEmbeddable): ?>
                            <!-- Live Video Preview (Controls are interactive, not wrapped in anchor) -->
                            <div class="news-img-container">
                                <span class="badge bg-danger bg-opacity-95 position-absolute top-0 end-0 m-3 small fw-bold px-3 py-2 shadow-sm rounded-pill animate-pulse" style="z-index: 2; border: 1px solid rgba(255, 255, 255, 0.25);">
                                    <i class="bi bi-play-circle-fill me-1"></i> VIDEO
                                </span>
                                
                                <?php 
                                $posterAttr = !empty($imageUrl) && $imageUrl !== 'images/no-image.png' ? ' poster="' . $imageUrl . '"' : '';
                                ?>
                                <?php if ($hasVideoFile): ?>
                                    <video src="<?= $videoFileSrc ?>"<?= $posterAttr ?> controls class="w-100 h-100" style="object-fit: cover;"></video>
                                <?php elseif (!empty($youtube_id)): ?>
                                    <iframe src="https://www.youtube.com/embed/<?= $youtube_id ?>" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen class="w-100 h-100"></iframe>
                                <?php elseif (!empty($vimeo_id)): ?>
                                    <iframe src="https://player.vimeo.com/video/<?= $vimeo_id ?>" title="Vimeo video player" frameborder="0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen class="w-100 h-100"></iframe>
                                <?php elseif ($isDirectVideoUrl): ?>
                                    <video src="<?= e($news['video_url']) ?>"<?= $posterAttr ?> controls class="w-100 h-100" style="object-fit: cover;"></video>
                                <?php endif; ?>

                                <div class="news-date-badge">
                                    <i class="bi bi-clock"></i>
                                    <?= date('d/m/Y', strtotime($news['created_at'])) ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <!-- Standard Image or External Link Cover (Clickable) -->
                            <a href="view_news.php?id=<?= $news['id'] ?>" class="text-decoration-none d-block">
                                <div class="news-img-container">
                                    <?php if ($hasVideo && $isExternalVideoUrl): ?>
                                        <span class="badge bg-primary bg-opacity-95 position-absolute top-0 end-0 m-3 small fw-bold px-3 py-2 shadow-sm rounded-pill" style="z-index: 2; border: 1px solid rgba(255, 255, 255, 0.25);">
                                            <i class="bi bi-box-arrow-up-right me-1"></i> LINK
                                        </span>
                                    <?php endif; ?>
                                    <img src="<?= $imageUrl ?>" class="news-img" alt="<?= e($news['title']) ?>">
                                    <?php if ($news['image_count'] > 1): ?>
                                        <span class="badge bg-dark bg-opacity-75 position-absolute bottom-0 end-0 m-3 small fw-bold px-3 py-2 shadow-sm rounded-pill" style="z-index: 2;">
                                            <i class="bi bi-images me-1"></i> +<?= $news['image_count'] - 1 ?> ภาพ
                                        </span>
                                    <?php endif; ?>
                                    <div class="news-date-badge">
                                        <i class="bi bi-clock"></i>
                                        <?= date('d/m/Y', strtotime($news['created_at'])) ?>
                                    </div>
                                </div>
                            </a>
                        <?php endif; ?>

                        <div class="news-body">
                            <h3 class="news-card-title">
                                <a href="view_news.php?id=<?= $news['id'] ?>" class="text-decoration-none text-reset">
                                    <?= e($news['title']) ?>
                                </a>
                            </h3>
                            <p class="news-desc">
                                <?= e(mb_substr($news['content'], 0, 110, 'UTF-8')) ?>
                                <?= (mb_strlen($news['content'], 'UTF-8') > 110) ? '...' : '' ?>
                            </p>
                            
                            <a href="view_news.php?id=<?= $news['id'] ?>" class="btn-view-details">
                                <span>ดูรายละเอียด</span>
                                <i class="bi bi-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>

        <div class="text-center mt-5 pt-3 mb-5">
            <a href="index.php" class="btn-back-home">
                <i class="bi bi-arrow-left"></i> กลับหน้าหลัก
            </a>
        </div>
    <?php else: ?>
        <div class="empty-state-card">
            <div class="empty-icon-box">
                <i class="bi bi-mailbox"></i>
            </div>
            <h3 class="fw-bold text-dark mb-3">ยังไม่มีข่าวประชาสัมพันธ์ในขณะนี้</h3>
            <p class="text-muted max-width-500 mx-auto mb-4">ขณะนี้ยังไม่มีรายการประกาศข่าวประชาสัมพันธ์อัปโหลดเข้ามาในระบบหลัก ท่านสามารถเข้ามาติดตามอัปเดตได้ใหม่ในภายหลัง</p>
            <a href="index.php" class="btn-back-home">
                <i class="bi bi-arrow-left"></i> กลับหน้าหลัก
            </a>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('newsSearch');
    const filterButtons = document.querySelectorAll('.btn-filter');
    let currentFilter = 'all'; // all, video, general

    function filterNews() {
        const query = searchInput ? searchInput.value.toLowerCase().trim() : '';
        const cards = document.querySelectorAll('.news-item-card');

        cards.forEach(card => {
            const title = card.querySelector('.news-card-title').textContent.toLowerCase();
            const desc = card.querySelector('.news-desc').textContent.toLowerCase();
            const hasVideo = card.getAttribute('data-has-video') === '1';

            // Check Search Query match
            const matchesSearch = title.includes(query) || desc.includes(query);

            // Check Category Filter match
            let matchesFilter = true;
            if (currentFilter === 'video') {
                matchesFilter = hasVideo;
            } else if (currentFilter === 'general') {
                matchesFilter = !hasVideo;
            }

            if (matchesSearch && matchesFilter) {
                card.style.display = '';
                card.style.opacity = '0';
                setTimeout(() => {
                    card.style.transition = 'opacity 0.25s ease';
                    card.style.opacity = '1';
                }, 10);
            } else {
                card.style.display = 'none';
            }
        });
    }

    if (searchInput) {
        searchInput.addEventListener('input', filterNews);
    }

    filterButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            filterButtons.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            currentFilter = this.getAttribute('data-filter');
            filterNews();
        });
    });
});
</script>

<?php
if ($result) $result->free();
include __DIR__ . '/includes/footer.php';
include __DIR__ . '/includes/footer_close.php';
?>