<?php
require_once __DIR__ . '/../includes/functions.php';
require_role(['staff']);

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: news.php');
    exit;
}

$id = (int)$_GET['id'];

// Fetch current to check for image and video
$stmt = $conn->prepare("SELECT image, video FROM news WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
$news = $res->fetch_assoc();
$stmt->close();

if ($news) {
    // Delete disk image if exists
    if (!empty($news['image'])) {
        $img_path = __DIR__ . "/../uploads/news/" . $news['image'];
        if (file_exists($img_path)) {
            @unlink($img_path);
        }
    }

    // Delete disk video if exists
    if (!empty($news['video'])) {
        $video_path = __DIR__ . "/../uploads/news/" . $news['video'];
        if (file_exists($video_path)) {
            @unlink($video_path);
        }
    }

    // Fetch and delete attachments from disk
    $att_q = $conn->query("SELECT file_name FROM news_attachments WHERE news_id = $id");
    while ($att = $att_q->fetch_assoc()) {
        $att_path = __DIR__ . "/../uploads/news/" . $att['file_name'];
        if (file_exists($att_path)) {
            @unlink($att_path);
        }
    }

    // Delete from DB
    $del = $conn->prepare("DELETE FROM news WHERE id = ?");
    $del->bind_param("i", $id);
    $del->execute();
    $del->close();
}

header('Location: news.php?deleted=success');
exit;
