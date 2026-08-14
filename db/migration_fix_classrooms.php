<?php
require_once 'includes/configdb.php';

// Fetch all classrooms
$stmt = $conn->prepare("SELECT id, class_name FROM classrooms");
$stmt->execute();
$classrooms = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch all students in the problem classrooms
$stmt = $conn->prepare("SELECT id, username, student_level, classroom_id FROM users WHERE role = 'student' AND classroom_id >= 40");
$stmt->execute();
$students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

echo "Problematic Students: " . count($students) . "\n";

foreach ($students as $student) {
    // Determine target class name based on student_level and the old classroom name
    $oldClass = array_filter($classrooms, function($c) use ($student) { return $c['id'] == $student['classroom_id']; });
    $oldClass = reset($oldClass);
    
    $level = $student['student_level'];
    $affName = $oldClass['class_name'];
    
    // Attempt matching
    $target_id = null;
    
    // Normalize and match
    $lvlPrefix = trim($level);
    
    // 1. Exact match with level
    $fullClassname = trim("$lvlPrefix $affName");
    
    foreach ($classrooms as $c) {
        if ($c['class_name'] == $fullClassname) {
            $target_id = $c['id'];
            break;
        }
    }
    
    // 2. Partial match (with or without 'สาขาวิชา')
    if (!$target_id) {
        foreach ($classrooms as $c) {
            if (strpos($c['class_name'], $lvlPrefix) !== false && strpos($c['class_name'], $affName) !== false) {
                $target_id = $c['id'];
                break;
            }
        }
    }
    
    if (!$target_id) {
        // Handle cases where 'สาขาวิชา' is missing from affName but present in classroom
        $cleanAff = str_replace('สาขาวิชา', '', $affName);
        foreach ($classrooms as $c) {
            $cClean = str_replace('สาขาวิชา', '', $c['class_name']);
            if (strpos($cClean, $lvlPrefix) !== false && strpos($cClean, $cleanAff) !== false) {
                $target_id = $c['id'];
                break;
            }
        }
    }
    
    if ($target_id) {
        echo "Mapping Student ID {$student['id']} ({$student['username']}): Classroom ID {$student['classroom_id']} -> {$target_id}\n";
        // Perform update
        $upd = $conn->prepare("UPDATE users SET classroom_id = ? WHERE id = ?");
        $upd->bind_param("ii", $target_id, $student['id']);
        $upd->execute();
    } else {
        echo "Failed to map Student ID {$student['id']} ({$student['username']}) - Level: $level, Affiliation: $affName\n";
    }
}
?>
