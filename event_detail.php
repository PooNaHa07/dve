<?php
// กำหนด Timezone เป็น Bangkok/Asia 
date_default_timezone_set('Asia/Bangkok');

// === การเรียกไฟล์ที่จำเป็น ===
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/configdb.php';
$page_title = 'ปฏิทินกิจกรรม';

// Smart Header
if (is_logged_in() && in_array(get_current_role(), ['admin', 'staff', 'officer', 'teacher', 'director'])) {
    include __DIR__ . '/includes/header.php';
} else {
    include __DIR__ . '/includes/header_public.php';
}

// === 1. การดึงข้อมูลกิจกรรมทั้งหมด ===
$sql = "SELECT id, title, description, event_date, image_filename 
        FROM calendar_events 
        ORDER BY event_date DESC"; // เรียงจากกิจกรรมล่าสุดก่อน
        
$result = $conn->query($sql);

if (!$result || $result->num_rows === 0) {
    $events = [];
} else {
    $events = $result->fetch_all(MYSQLI_ASSOC);
}
?>

<style>
    /* Animated Blobs Background matching Landing Page */
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
    .blob-2 { background: #a855f7; bottom: -150px; left: -150px; animation-delay: -5s; }

    @keyframes floatBlob {
        0% { transform: translate(0, 0) scale(1); }
        100% { transform: translate(80px, 50px) scale(1.1); }
    }

    .calendar-header {
        background: transparent;
        color: #0f172a;
        padding: 3rem 1rem 1.5rem;
        text-align: center;
        position: relative;
    }

    .calendar-icon-box {
        width: 80px;
        height: 80px;
        background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
        border-radius: 22px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 2.25rem;
        margin-bottom: 1.25rem;
        animation: pulseIcon 3s infinite;
        box-shadow: 0 15px 30px rgba(99, 102, 241, 0.25);
    }

    @keyframes pulseIcon {
        0%, 100% { transform: scale(1); box-shadow: 0 15px 30px rgba(99, 102, 241, 0.25); }
        50% { transform: scale(1.05); box-shadow: 0 20px 40px rgba(99, 102, 241, 0.35); }
    }

    .calendar-title {
        font-size: 2.25rem;
        font-weight: 850;
        color: #0f172a;
        letter-spacing: -0.5px;
        margin-bottom: 0.5rem;
    }

    .calendar-subtitle {
        font-size: 1.05rem;
        color: #64748b;
        font-weight: 500;
        max-width: 600px;
        margin: 0 auto;
    }

    /* Interactive Live Search Filter */
    .search-container {
        max-width: 600px;
        margin: -1.25rem auto 2.5rem;
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
        border-color: #6f42c1;
        box-shadow: 0 15px 35px rgba(111, 66, 193, 0.15);
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

    /* Premium Event Grid & Cards */
    .event-card {
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

    .event-card:hover {
        transform: translateY(-10px);
        box-shadow: 0 30px 60px rgba(74, 20, 140, 0.12);
        border-color: rgba(111, 66, 193, 0.3);
    }

    .event-img-container {
        position: relative;
        overflow: hidden;
        height: 220px;
    }

    .event-img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.6s cubic-bezier(0.165, 0.84, 0.44, 1);
    }

    .event-card:hover .event-img {
        transform: scale(1.06);
    }

    .event-date-badge {
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

    .event-body {
        padding: 2rem;
        display: flex;
        flex-direction: column;
        flex-grow: 1;
    }

    .event-title {
        font-size: 1.3rem;
        font-weight: 800;
        color: #0f172a;
        line-height: 1.45;
        margin-bottom: 0.75rem;
        transition: color 0.25s;
    }

    .event-card:hover .event-title {
        color: #6f42c1;
    }

    .event-desc {
        font-size: 0.95rem;
        color: #64748b;
        line-height: 1.6;
        margin-bottom: 1.75rem;
        flex-grow: 1;
    }

    .btn-read-more {
        background: linear-gradient(135deg, #6f42c1 0%, #4a148c 100%);
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
        box-shadow: 0 4px 12px rgba(111, 66, 193, 0.15);
    }

    .btn-read-more:hover {
        background: linear-gradient(135deg, #4a148c 0%, #300c5c 100%);
        transform: translateY(-2px);
        box-shadow: 0 10px 20px rgba(74, 20, 140, 0.25);
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

    /* Back Button Container */
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
</style>

<div class="bg-blob blob-1"></div>
<div class="bg-blob blob-2"></div>

<div class="container py-4">
    <!-- Premium Header Block -->
    <div class="calendar-header">
        <div class="calendar-icon-box">
            <i class="bi bi-calendar-event text-white"></i>
        </div>
        <h1 class="calendar-title">ปฏิทินกิจกรรมทั้งหมด</h1>
        <p class="calendar-subtitle">ติดตามความเคลื่อนไหว กิจกรรมการฝึกงาน และประกาศข่าวสารแบบทันท่วงทีสำหรับทุกระดับชั้น</p>
    </div>

    <!-- Live Interactive Search Box -->
    <div class="search-container">
        <div class="search-input-box">
            <i class="bi bi-search"></i>
            <input type="text" id="eventSearch" placeholder="ค้นหากิจกรรมด้วยชื่อ หรือรายละเอียดกิจกรรม...">
        </div>
    </div>

    <?php if (empty($events)): ?>
        <div class="empty-state-card">
            <div class="empty-icon-box">
                <i class="bi bi-calendar-x"></i>
            </div>
            <h3 class="fw-bold text-dark mb-3">ยังไม่มีกิจกรรมในขณะนี้</h3>
            <p class="text-muted max-width-500 mx-auto mb-4">ขณะนี้ยังไม่มีรายละเอียดกิจกรรมฝึกงานถูกอัปโหลดเข้ามาในระบบปฏิทินหลัก ท่านสามารถเข้ามาติดตามอัปเดตได้ที่นี่ในภายหลัง</p>
            <a href="index.php" class="btn-back-home">
                <i class="bi bi-arrow-left"></i> กลับหน้าหลัก
            </a>
        </div>
    <?php else: ?>
        <div class="row g-4" id="eventGrid">
            <?php foreach ($events as $event): 
                $image = !empty($event['image_filename'])
                    ? 'uploads/event_images/' . e($event['image_filename'])
                    : 'images/no-image.png';
            ?>
                <div class="col-md-6 col-lg-4 event-item-card">
                    <div class="event-card">
                        <div class="event-img-container">
                            <img src="<?= $image ?>" class="event-img" alt="<?= e($event['title']) ?>">
                            <div class="event-date-badge">
                                <i class="bi bi-calendar3"></i>
                                <?= date('d/m/Y', strtotime($event['event_date'])) ?>
                            </div>
                        </div>

                        <div class="event-body">
                            <h3 class="event-title"><?= e($event['title']) ?></h3>
                            <p class="event-desc">
                                <?= e(mb_substr($event['description'], 0, 110, 'UTF-8')) ?>
                                <?= (mb_strlen($event['description'], 'UTF-8') > 110) ? '...' : '' ?>
                            </p>
                            
                            <a href="view_detail.php?id=<?= e($event['id']) ?>" class="btn-read-more">
                                <span>อ่านรายละเอียดเพิ่มเติม</span>
                                <i class="bi bi-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="text-center mt-5 pt-3 mb-5">
            <a href="index.php" class="btn-back-home">
                <i class="bi bi-arrow-left"></i> กลับหน้าหลัก
            </a>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('eventSearch');
    if (searchInput) {
        searchInput.addEventListener('input', function(e) {
            const query = e.target.value.toLowerCase().trim();
            const cards = document.querySelectorAll('.event-item-card');
            
            cards.forEach(card => {
                const title = card.querySelector('.event-title').textContent.toLowerCase();
                const desc = card.querySelector('.event-desc').textContent.toLowerCase();
                
                if (title.includes(query) || desc.includes(query)) {
                    card.style.display = '';
                    card.style.opacity = '0';
                    setTimeout(() => {
                        card.style.transition = 'opacity 0.3s ease';
                        card.style.opacity = '1';
                    }, 10);
                } else {
                    card.style.display = 'none';
                }
            });
        });
    }
});
</script>

<?php
if (isset($result)) $result->free();
include __DIR__ . '/includes/footer.php';
include __DIR__ . '/includes/footer_close.php';
?>