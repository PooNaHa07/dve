<?php
header('Content-Type: application/json; charset=utf-8');

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

require_once __DIR__ . '/../includes/configdb.php';

$input = json_decode(file_get_contents('php://input'), true);
$reports = isset($input['reports']) ? $input['reports'] : [];
$student_id = $_SESSION['user_id'];

if (empty($reports)) {
    echo json_encode(['success' => true, 'message' => 'No reports to sync', 'synced_count' => 0]);
    exit;
}

$synced_count = 0;
$errors = [];

foreach ($reports as $index => $rep) {
    $date_work = isset($rep['date_work']) ? $rep['date_work'] : date('Y-m-d');
    $details = isset($rep['details']) ? $rep['details'] : '';
    $problems = isset($rep['problems']) ? $rep['problems'] : '';
    $solutions = isset($rep['solutions']) ? $rep['solutions'] : '';
    
    $img1 = null;
    $img2 = null;

    // Save base64 image 1
    if (!empty($rep['image1_base64'])) {
        $img1 = save_base64_image($rep['image1_base64'], $rep['image1_name']);
    }
    // Save base64 image 2
    if (!empty($rep['image2_base64'])) {
        $img2 = save_base64_image($rep['image2_base64'], $rep['image2_name']);
    }

    // Check duplicate
    $chk = $conn->prepare('SELECT id FROM daily_reports WHERE student_id=? AND date_work=?');
    $chk->bind_param('is', $student_id, $date_work);
    $chk->execute();
    $chk_res = $chk->get_result();
    $dup = $chk_res && $chk_res->num_rows > 0;
    $chk->close();

    if ($dup) {
        // Skip duplicate or update
        continue;
    }

    // Insert
    $stmt = $conn->prepare('INSERT INTO daily_reports (student_id, date_work, details, problems, solutions, image1, image2) VALUES (?,?,?,?,?,?,?)');
    $stmt->bind_param('issssss', $student_id, $date_work, $details, $problems, $solutions, $img1, $img2);
    if ($stmt->execute()) {
        $synced_count++;
    } else {
        $errors[] = "Report index $index: " . $conn->error;
    }
    $stmt->close();
}

echo json_encode([
    'success' => true,
    'synced_count' => $synced_count,
    'errors' => $errors
]);

// Helper to save base64 string as file
function save_base64_image($base64_str, $orig_name) {
    if (empty($base64_str)) return null;
    
    // Clean string
    $parts = explode(',', $base64_str);
    $data = isset($parts[1]) ? base64_decode($parts[1]) : base64_decode($parts[0]);
    if (!$data) return null;

    // Get extension
    $ext = 'png';
    if (!empty($orig_name)) {
        $path_info = pathinfo($orig_name);
        $ext = isset($path_info['extension']) ? $path_info['extension'] : 'png';
    }

    $filename = 'img_' . uniqid() . '_' . time() . '.' . $ext;
    $target_dir = __DIR__ . '/../uploads/images/';
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    if (file_put_contents($target_dir . $filename, $data)) {
        return $filename;
    }
    return null;
}
