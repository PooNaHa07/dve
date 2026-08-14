<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json');

// Security Check
if (!is_logged_in() || !in_array(get_current_role(), ['staff'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id > 0) {
    $stmt = $conn->prepare("SELECT id, title, content, image, video, video_url FROM news WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($news = $result->fetch_assoc()) {
        $news['image_url'] = !empty($news['image']) ? '../uploads/news/' . $news['image'] : null;
        $news['video_url_path'] = !empty($news['video']) ? '../uploads/news/' . $news['video'] : null;
        
        // Fetch attachments
        $attachments = [];
        $att_stmt = $conn->prepare("SELECT id, file_name, original_name, file_type FROM news_attachments WHERE news_id = ?");
        $att_stmt->bind_param("i", $id);
        $att_stmt->execute();
        $att_res = $att_stmt->get_result();
        while ($att_row = $att_res->fetch_assoc()) {
            $attachments[] = $att_row;
        }
        $att_stmt->close();
        
        $news['attachments'] = $attachments;
        echo json_encode($news);
    } else {
        echo json_encode(['error' => 'News not found']);
    }
    $stmt->close();
} else {
    echo json_encode(['error' => 'Invalid ID']);
}
