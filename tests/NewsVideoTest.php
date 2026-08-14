<?php
declare(strict_types=1);

/**
 * 🛠️ News Video and URL Capability Test Suite
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php';

// Colors for terminal output
define('CLR_RESET', "\033[0m");
define('CLR_GREEN', "\033[32m");
define('CLR_RED', "\033[31m");
define('CLR_CYAN', "\033[36m");
define('CLR_YELLOW', "\033[33m");

echo CLR_CYAN . "========================================================\n" . CLR_RESET;
echo CLR_CYAN . "🎥 STARTING NEWS VIDEO FUNCTIONALITY TESTS\n" . CLR_RESET;
echo CLR_CYAN . "========================================================\n\n" . CLR_RESET;

global $conn;

if (!$conn) {
    echo CLR_RED . "❌ Error: Failed to connect to database.\n" . CLR_RESET;
    exit(1);
}

$testsPassed = 0;
$testsFailed = 0;

function assertTest(string $name, bool $expression): void {
    global $testsPassed, $testsFailed;
    if ($expression) {
        echo CLR_GREEN . "  ✓ PASS: $name\n" . CLR_RESET;
        $testsPassed++;
    } else {
        echo CLR_RED . "  ✗ FAIL: $name\n" . CLR_RESET;
        $testsFailed++;
    }
}

// 1. Insert news with video file and url
$title = "PR Video Test News";
$content = "Testing news video features";
$videoFile = "test_pr_video.mp4";
$videoUrl = "https://www.youtube.com/watch?v=dQw4w9WgXcQ";

$stmt = $conn->prepare("INSERT INTO news (title, content, video, video_url, created_at) VALUES (?, ?, ?, ?, NOW())");
$inserted = false;
$tempId = 0;
if ($stmt) {
    $stmt->bind_param("ssss", $title, $content, $videoFile, $videoUrl);
    if ($stmt->execute()) {
        $tempId = $stmt->insert_id;
        $inserted = true;
    }
    $stmt->close();
}

assertTest("Can insert news article with video file and video URL", $inserted && $tempId > 0);

// 2. Fetch and verify fields
if ($inserted && $tempId > 0) {
    $stmt = $conn->prepare("SELECT title, content, video, video_url FROM news WHERE id = ?");
    $stmt->bind_param("i", $tempId);
    $stmt->execute();
    $news = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    assertTest("Title retrieved matches: " . $title, $news['title'] === $title);
    assertTest("Content retrieved matches: " . $content, $news['content'] === $content);
    assertTest("Video file retrieved matches: " . $videoFile, $news['video'] === $videoFile);
    assertTest("Video URL retrieved matches: " . $videoUrl, $news['video_url'] === $videoUrl);

    // 3. Test get_news.php response format logic
    $video_url_path = !empty($news['video']) ? '../uploads/news/' . $news['video'] : null;
    assertTest("Resolved uploaded video path is correct", $video_url_path === '../uploads/news/' . $videoFile);

    // 4. Clean up / Delete the test article
    $deleted = false;
    $del = $conn->prepare("DELETE FROM news WHERE id = ?");
    if ($del) {
        $del->bind_param("i", $tempId);
        if ($del->execute()) {
            $deleted = true;
        }
        $del->close();
    }
    assertTest("Can delete the test news article from database", $deleted);
}

echo "\n" . CLR_CYAN . "========================================================\n" . CLR_RESET;
echo CLR_CYAN . "📋 VIDEO NEWS TEST EXECUTION SUMMARY\n" . CLR_RESET;
echo CLR_CYAN . "========================================================\n" . CLR_RESET;
echo CLR_GREEN . "  Passed: $testsPassed\n" . CLR_RESET;
if ($testsFailed > 0) {
    echo CLR_RED . "  Failed: $testsFailed\n" . CLR_RESET;
} else {
    echo CLR_GREEN . "  All video functionality tests passed! 🎉\n" . CLR_RESET;
}
echo CLR_CYAN . "========================================================\n" . CLR_RESET;
