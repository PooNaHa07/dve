<?php
// กำหนด Timezone เพื่อหลีกเลี่ยง Warning
date_default_timezone_set('Asia/Bangkok');

// === 1. การเรียกไฟล์ที่จำเป็น ===
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/configdb.php';
$page_title = 'รายละเอียดกิจกรรม';
include __DIR__ . '/includes/header_public.php';

// === 2. รับค่า ID และการตรวจสอบ ===
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id === 0) {
    echo "<div class='container mt-5 alert alert-danger text-center'>
        **ข้อผิดพลาด:** ไม่พบ ID กิจกรรมที่ต้องการแสดงรายละเอียด
    </div>";
    include __DIR__ . '/includes/footer.php';
    include __DIR__ . '/includes/footer_close.php';
    exit;
}

// === 3. ดึงข้อมูลกิจกรรมเดี่ยวจากฐานข้อมูล ===
$sql = "SELECT title, description, event_date, image_filename
        FROM calendar_events
        WHERE id = ?";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo "<div class='container mt-5 alert alert-danger'>
        **ข้อผิดพลาดร้ายแรง:** เตรียมคำสั่ง SQL ล้มเหลว! (" . $conn->error . ")
    </div>";
    include __DIR__ . '/includes/footer.php';
    include __DIR__ . '/includes/footer_close.php';
    exit;
}

$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    echo "<div class='container mt-5 alert alert-warning text-center'>
        ไม่พบกิจกรรมสำหรับ ID: **" . e($id) . "** กรุณาตรวจสอบ ID
    </div>";
    $stmt->close();
    include __DIR__ . '/includes/footer.php';
    include __DIR__ . '/includes/footer_close.php';
    exit;
}

$stmt->bind_result($title, $description, $event_date, $image_filename);
$stmt->fetch();

$image = !empty($image_filename)
    ? 'uploads/event_images/' . e($image_filename)
    : 'images/no-image.png';
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

    /* Premium Detail Card Container */
    .premium-detail-container {
        max-width: 900px;
        margin: 3rem auto;
    }

    .pastel-detail-card {
        background: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid rgba(226, 232, 240, 0.8);
        border-radius: 32px;
        overflow: hidden;
        box-shadow: 0 25px 55px rgba(74, 20, 140, 0.08);
        transition: all 0.3s ease;
    }

    .pastel-detail-card:hover {
        box-shadow: 0 35px 70px rgba(74, 20, 140, 0.12);
    }

    .detail-img-container {
        position: relative;
        max-height: 480px;
        overflow: hidden;
    }

    .detail-img-container img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.5s ease;
    }

    .pastel-detail-card:hover .detail-img-container img {
        transform: scale(1.02);
    }

    .detail-date-badge {
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

    .detail-body {
        padding: 3rem;
    }

    .detail-title {
        font-size: 2.25rem;
        font-weight: 850;
        color: #0f172a;
        line-height: 1.35;
        letter-spacing: -0.5px;
        position: relative;
        padding-bottom: 1rem;
        margin-bottom: 2rem;
    }

    .detail-title::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        width: 80px;
        height: 4px;
        background: linear-gradient(90deg, #6f42c1, #0ea5e9);
        border-radius: 2px;
    }

    .activity-description {
        font-size: 1.1rem;
        color: #475569;
        line-height: 1.85;
    }

    /* Back Button Style */
    .btn-back-list {
        background: linear-gradient(135deg, #6f42c1 0%, #4a148c 100%);
        color: white;
        border: none;
        padding: 0.85rem 2rem;
        font-weight: 700;
        border-radius: 16px;
        transition: all 0.3s;
        text-decoration: none;
        box-shadow: 0 4px 15px rgba(111, 66, 193, 0.2);
        display: inline-flex;
        align-items: center;
        gap: 0.6rem;
    }

    .btn-back-list:hover {
        background: linear-gradient(135deg, #4a148c 0%, #300c5c 100%);
        transform: translateY(-2px);
        box-shadow: 0 10px 25px rgba(74, 20, 140, 0.3);
        color: white;
    }
</style>

<div class="bg-blob blob-1"></div>
<div class="bg-blob blob-2"></div>

<div class="container premium-detail-container">
    <div class="pastel-detail-card">
        <div class="detail-img-container">
            <img src="<?= $image ?>" alt="<?= e($title) ?>">
            <div class="detail-date-badge">
                <i class="bi bi-calendar3"></i>
                วันที่จัดกิจกรรม: <?= date('d/m/Y', strtotime($event_date)) ?>
            </div>
        </div>

        <div class="detail-body">
            <h1 class="detail-title"><?= e($title) ?></h1>

            <div class="activity-description">
                <?= nl2br(e($description)) ?>
            </div>

            <div class="mt-5 pt-4 border-top text-center">
                <a href="https://202.29.236.132/" target="_blank" rel="noopener noreferrer" class="btn-back-list">
                    <i class="bi bi-arrow-left"></i>
                    <span>กลับหน้าปฏิทินกิจกรรมทั้งหมด</span>
                </a>
            </div>
        </div>
    </div>
</div>

<?php 
$stmt->close();
include __DIR__ . '/includes/footer.php';
include __DIR__ . '/includes/footer_close.php';
?>