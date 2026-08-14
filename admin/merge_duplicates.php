<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php';
require_login();
require_role(['admin']);

// 🚀 AJAX Handler for getting student stats
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_stats') {
    header('Content-Type: application/json; charset=utf-8');
    $student_id = isset($_POST['student_id']) ? (int)$_POST['student_id'] : 0;
    $stats = getStudentStats($conn, $student_id);
    echo json_encode($stats);
    exit;
}

// 🚀 AJAX Handler for merging duplicates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'merge') {
    header('Content-Type: application/json; charset=utf-8');
    
    $keep_id = isset($_POST['keep_id']) ? (int)$_POST['keep_id'] : 0;
    $merge_ids_str = isset($_POST['merge_ids']) ? $_POST['merge_ids'] : '';
    if (empty($merge_ids_str) && isset($_POST['merge_id'])) {
        $merge_ids_str = $_POST['merge_id'];
    }
    
    $merge_ids = array_filter(array_map('intval', explode(',', $merge_ids_str)));
    
    if ($keep_id <= 0 || empty($merge_ids) || in_array($keep_id, $merge_ids)) {
        echo json_encode(['success' => false, 'message' => 'ข้อมูลบัญชีไม่ถูกต้องหรือเป็นบัญชีเดียวกัน']);
        exit;
    }
    
    $conn->begin_transaction();
    try {
        // Fetch primary user details
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->bind_param("i", $keep_id);
        $stmt->execute();
        $keep_user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$keep_user) {
            throw new Exception("ไม่พบข้อมูลผู้ใช้หลักที่ระบุในฐานข้อมูล");
        }

        $merged_count = 0;
        $merged_details = [];

        foreach ($merge_ids as $merge_id) {
            if ($merge_id <= 0 || $merge_id === $keep_id) continue;

            $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->bind_param("i", $merge_id);
            $stmt->execute();
            $merge_user = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$merge_user) {
                continue;
            }

            // 1. Copy missing attributes from merge_user to keep_user (non-destructive)
            $update_fields = [];
            $params = [];
            $types = "";
            
            $fields_to_check = [
                'student_code', 'classroom_id', 'company_id', 'branch_id', 'affiliation', 
                'student_level', 'phone', 'email', 'company_name', 'company_address', 
                'company_manager', 'company_phone', 'trainer_name', 'trainer_phone', 
                'trainer_position', 'mentor_id', 'mentor_id_2', 'mentor_id_3', 
                'mentor_id_4', 'mentor_id_5', 'profile_image'
            ];

            foreach ($fields_to_check as $field) {
                // If primary field is empty/0/null and secondary field is NOT empty/0/null, copy it
                if ((empty($keep_user[$field]) || $keep_user[$field] == 0) && !empty($merge_user[$field]) && $merge_user[$field] != 0) {
                    $update_fields[] = "`$field` = ?";
                    $params[] = $merge_user[$field];
                    if (in_array($field, ['classroom_id', 'company_id', 'branch_id', 'mentor_id', 'mentor_id_2', 'mentor_id_3', 'mentor_id_4', 'mentor_id_5'])) {
                        $types .= "i";
                    } else {
                        $types .= "s";
                    }
                    $keep_user[$field] = $merge_user[$field]; // Update in-memory keep_user
                }
            }

            if (!empty($update_fields)) {
                $sql = "UPDATE users SET " . implode(", ", $update_fields) . " WHERE id = ?";
                $params[] = $keep_id;
                $types .= "i";
                
                $up_stmt = $conn->prepare($sql);
                $up_stmt->bind_param($types, ...$params);
                $up_stmt->execute();
                $up_stmt->close();
            }

            // 2. Transfer related logs & records to the primary user
            // 2.1 daily_reports
            $stmt = $conn->prepare("UPDATE daily_reports SET student_id = ? WHERE student_id = ?");
            $stmt->bind_param("ii", $keep_id, $merge_id);
            $stmt->execute();
            $stmt->close();

            // 2.2 evaluations
            $stmt = $conn->prepare("UPDATE evaluations SET student_id = ? WHERE student_id = ?");
            $stmt->bind_param("ii", $keep_id, $merge_id);
            $stmt->execute();
            $stmt->close();

            // 2.3 staff_evaluations (Handle UNIQUE KEY on student_id)
            $stmt = $conn->prepare("SELECT id FROM staff_evaluations WHERE student_id = ?");
            $stmt->bind_param("i", $keep_id);
            $stmt->execute();
            $has_keep_eval = $stmt->get_result()->num_rows > 0;
            $stmt->close();
          
            if ($has_keep_eval) {
                // Delete the secondary one to prevent unique constraint violation
                $stmt = $conn->prepare("DELETE FROM staff_evaluations WHERE student_id = ?");
                $stmt->bind_param("i", $merge_id);
                $stmt->execute();
                $stmt->close();
            } else {
                $stmt = $conn->prepare("UPDATE staff_evaluations SET student_id = ? WHERE student_id = ?");
                $stmt->bind_param("ii", $keep_id, $merge_id);
                $stmt->execute();
                $stmt->close();
            }

            // 2.4 supervision_files
            $stmt = $conn->prepare("UPDATE supervision_files SET student_id = ? WHERE student_id = ?");
            $stmt->bind_param("ii", $keep_id, $merge_id);
            $stmt->execute();
            $stmt->close();

            // 2.5 certificates
            $stmt = $conn->prepare("UPDATE certificates SET student_id = ? WHERE student_id = ?");
            $stmt->bind_param("ii", $keep_id, $merge_id);
            $stmt->execute();
            $stmt->close();

            // 2.6 audit_logs (System-wide Audits)
            $stmt = $conn->prepare("UPDATE audit_logs SET user_id = ? WHERE user_id = ?");
            $stmt->bind_param("ii", $keep_id, $merge_id);
            $stmt->execute();
            $stmt->close();

            // 2.7 contact_messages
            $stmt = $conn->prepare("UPDATE contact_messages SET user_id = ? WHERE user_id = ?");
            $stmt->bind_param("ii", $keep_id, $merge_id);
            $stmt->execute();
            $stmt->close();

            // 2.8 notifications
            $stmt = $conn->prepare("UPDATE notifications SET user_id = ? WHERE user_id = ?");
            $stmt->bind_param("ii", $keep_id, $merge_id);
            $stmt->execute();
            $stmt->close();

            // 3. Delete the secondary user
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $merge_id);
            $stmt->execute();
            $stmt->close();

            $merged_count++;
            $merged_details[] = "{$merge_user['fullname']} (ID {$merge_id}, รหัส: " . ($merge_user['student_code'] ?: 'ไม่มี') . ")";
        }

        if ($merged_count === 0) {
            throw new Exception("ไม่มีข้อมูลผู้ใช้รองที่ถูกยุบรวมสำเร็จ");
        }

        // Commit transaction!
        $conn->commit();

        // Log the administrative action to the audit logs
        $audit_details = "ยุบรวมรายชื่อซ้ำซ้อนสำเร็จ: บัญชีหลัก ID {$keep_id} ({$keep_user['fullname']}, รหัส: " . ($keep_user['student_code'] ?: 'ไม่มี') . ") <- ยุบรวมและลบบัญชีรอง {$merged_count} บัญชี (" . implode(", ", $merged_details) . ")";
        log_audit('MERGE_STUDENT', $audit_details);

        echo json_encode([
            'success' => true, 
            'message' => "ยุบรวมบัญชีเรียบร้อย! ข้อมูลทั้งหมดของบัญชีรองจำนวน {$merged_count} บัญชี ถูกโอนย้ายไปยังบัญชีหลัก {$keep_user['fullname']} เรียบร้อยแล้ว"
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()]);
    }
    exit;
}

// 👨‍🎓 Helper to normalize student names (strips prefix and whitespace)
function normalizeName($name) {
    $name = preg_replace('/^(นาย|นางสาว|นาง|เด็กชาย|เด็กหญิง)\s*/u', '', trim($name));
    $name = preg_replace('/\s+/u', '', $name);
    return $name;
}

// UTF-8 safe Levenshtein distance
function levenshtein_utf8($s1, $s2) {
    $char1 = preg_split('//u', $s1, -1, PREG_SPLIT_NO_EMPTY);
    $char2 = preg_split('//u', $s2, -1, PREG_SPLIT_NO_EMPTY);
    $l1 = count($char1);
    $l2 = count($char2);
    if ($l1 == 0) return $l2;
    if ($l2 == 0) return $l1;
    $dp = range(0, $l2);
    for ($i = 0; $i < $l1; $i++) {
        $prev = $i + 1;
        for ($j = 0; $j < $l2; $j++) {
            $cost = ($char1[$i] === $char2[$j]) ? 0 : 1;
            $val = min($dp[$j + 1] + 1, $prev + 1, $dp[$j] + $cost);
            $dp[$j] = $prev;
            $prev = $val;
        }
        $dp[$l2] = $prev;
    }
    return $dp[$l2];
}

// Calculate similarity score (0 to 100) between two names
function utf8_similarity($s1, $s2) {
    $s1 = normalizeName($s1);
    $s2 = normalizeName($s2);
    if (empty($s1) || empty($s2)) return 0;
    if ($s1 === $s2) return 100;
    
    $dist = levenshtein_utf8($s1, $s2);
    $max_len = max(mb_strlen($s1, 'utf-8'), mb_strlen($s2, 'utf-8'));
    if ($max_len === 0) return 0;
    
    $percent = (1 - ($dist / $max_len)) * 100;
    
    // Check substring match (e.g. first name matches exactly but missing/different last name)
    if (mb_strpos($s1, $s2, 0, 'utf-8') !== false || mb_strpos($s2, $s1, 0, 'utf-8') !== false) {
        $min_len = min(mb_strlen($s1, 'utf-8'), mb_strlen($s2, 'utf-8'));
        if ($min_len >= 3) {
            $ratio = $min_len / $max_len;
            $substring_percent = 70 + ($ratio * 25);
            return max($percent, $substring_percent);
        }
    }
    
    return $percent;
}

// Helper to get stats of records for a student
function getStudentStats($conn, $student_id) {
    $stats = [
        'reports' => 0,
        'evaluations' => 0,
        'staff_evals' => 0,
        'supervision' => 0,
        'certificates' => 0
    ];
    
    // daily_reports
    $q = $conn->prepare("SELECT COUNT(*) FROM daily_reports WHERE student_id = ?");
    $q->bind_param("i", $student_id);
    $q->execute();
    $q->bind_result($stats['reports']);
    $q->fetch();
    $q->close();
    
    // evaluations
    $q = $conn->prepare("SELECT COUNT(*) FROM evaluations WHERE student_id = ?");
    $q->bind_param("i", $student_id);
    $q->execute();
    $q->bind_result($stats['evaluations']);
    $q->fetch();
    $q->close();
    
    // staff_evaluations
    $q = $conn->prepare("SELECT COUNT(*) FROM staff_evaluations WHERE student_id = ?");
    $q->bind_param("i", $student_id);
    $q->execute();
    $q->bind_result($stats['staff_evals']);
    $q->fetch();
    $q->close();
    
    // supervision_files
    $q = $conn->prepare("SELECT COUNT(*) FROM supervision_files WHERE student_id = ?");
    $q->bind_param("i", $student_id);
    $q->execute();
    $q->bind_result($stats['supervision']);
    $q->fetch();
    $q->close();
    
    // certificates
    $q = $conn->prepare("SELECT COUNT(*) FROM certificates WHERE student_id = ?");
    $q->bind_param("i", $student_id);
    $q->execute();
    $q->bind_result($stats['certificates']);
    $q->fetch();
    $q->close();
    
    return $stats;
}

// Fetch classrooms and companies maps for displaying human-readable labels
$classroom_map = [];
$res = $conn->query("SELECT id, class_name FROM classrooms");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $classroom_map[$row['id']] = $row['class_name'];
    }
}

$company_map = [];
$res = $conn->query("SELECT id, name FROM companies");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $company_map[$row['id']] = $row['name'];
    }
}

// Fetch all students to search for duplicates (including phone and profile_image)
$res = $conn->query("SELECT id, username, fullname, email, phone, student_code, classroom_id, company_id, student_level, affiliation, profile_image, created_at FROM users WHERE role = 'student' ORDER BY fullname ASC");
$all_students = [];
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $all_students[] = $row;
    }
}

// Helper to enrich candidates and sort them based on record history and completeness
function enrichAndSortCandidates($conn, $students) {
    $candidates = [];
    foreach ($students as $idx => $s) {
        $stats = getStudentStats($conn, $s['id']);
        
        $reasons = [];
        if ($stats['reports'] > 0) {
            $reasons[] = "มีบันทึกรายงานประจำวัน (" . $stats['reports'] . " รายการ)";
        }
        if ($stats['evaluations'] > 0 || $stats['staff_evals'] > 0) {
            $reasons[] = "มีบันทึกการประเมินผลสัมฤทธิ์ (" . ($stats['evaluations'] + $stats['staff_evals']) . " รายการ)";
        }
        if ($stats['supervision'] > 0) {
            $reasons[] = "มีประวัตินิเทศงาน (" . $stats['supervision'] . " รายการ)";
        }
        if ($stats['certificates'] > 0) {
            $reasons[] = "มีใบรับรอง/เกียรติบัตรออกแล้ว";
        }
        if (!empty($s['profile_image'])) {
            $reasons[] = "มีรูปโปรไฟล์ในระบบ";
        }
        if (!empty($s['student_code'])) {
            $reasons[] = "มีรหัสประจำตัวครบถ้วน";
        }
        if (empty($reasons)) {
            $reasons[] = "บัญชีนักศึกษาพื้นฐาน";
        }
        
        $score = ($stats['reports'] * 5) + ($stats['evaluations'] * 10) + ($stats['staff_evals'] * 10) + ($stats['supervision'] * 8) + ($stats['certificates'] * 15);
        if (!empty($s['profile_image'])) $score += 12;
        if (!empty($s['student_code'])) $score += 10;
        
        $candidates[$idx] = [
            'student' => $s,
            'stats' => $stats,
            'score' => $score,
            'recommendation_reasons' => $reasons
        ];
    }
    
    // Sort candidates by score descending so the most complete account is first
    usort($candidates, function($a, $b) {
        return $b['score'] - $a['score'];
    });
    
    return $candidates;
}

// Group students by normalized name
$grouped = [];
foreach ($all_students as $s) {
    $normalized = normalizeName($s['fullname']);
    $grouped[$normalized][] = $s;
}

// Isolate duplicate groups (count > 1) - EXACT NAME MATCHES
$exact_groups = [];
$exact_grouped_ids = [];
foreach ($grouped as $norm => $group) {
    if (count($group) > 1) {
        $exact_groups[] = [
            'normalized_name' => $norm,
            'students' => $group
        ];
        foreach ($group as $s) {
            $exact_grouped_ids[$s['id']] = true;
        }
    }
}

// Isolate FUZZY NAME MATCHES (similarity between 55% and 99%)
$fuzzy_groups = [];
$already_fuzzy_paired = [];

for ($i = 0; $i < count($all_students); $i++) {
    $s1 = $all_students[$i];
    $norm1 = normalizeName($s1['fullname']);
    
    for ($j = $i + 1; $j < count($all_students); $j++) {
        $s2 = $all_students[$j];
        $norm2 = normalizeName($s2['fullname']);
        
        // If they are exact duplicates, skip (they are in the Exact tab)
        if ($norm1 === $norm2) {
            continue;
        }
        
        $sim = utf8_similarity($s1['fullname'], $s2['fullname']);
        
        if ($sim >= 55) {
            $pair_key = min($s1['id'], $s2['id']) . '_' . max($s1['id'], $s2['id']);
            if (!isset($already_fuzzy_paired[$pair_key])) {
                $already_fuzzy_paired[$pair_key] = true;
                
                $fuzzy_groups[] = [
                    'similarity' => round($sim, 1),
                    'students' => [$s1, $s2]
                ];
            }
        }
    }
}

// Isolate DUPLICATE STUDENT CODES (รหัสนักเรียนซ้ำกัน)
$code_groups = [];
$code_grouped = [];
foreach ($all_students as $s) {
    $code = trim($s['student_code']);
    if (!empty($code)) {
        $code_grouped[$code][] = $s;
    }
}
foreach ($code_grouped as $code => $group) {
    if (count($group) > 1) {
        $code_groups[] = [
            'student_code' => $code,
            'students' => $group
        ];
    }
}

// Isolate DUPLICATE CONTACT INFO (ข้อมูลติดต่อซ้ำ - อีเมล หรือ เบอร์โทร)
$contact_groups = [];
$email_grouped = [];
$phone_grouped = [];
foreach ($all_students as $s) {
    $email = trim($s['email']);
    if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $email_grouped[$email][] = $s;
    }
    $phone = trim($s['phone']);
    $phone_clean = preg_replace('/[^0-9]/', '', $phone);
    if (!empty($phone_clean) && strlen($phone_clean) >= 9) {
        $phone_grouped[$phone_clean][] = $s;
    }
}

foreach ($email_grouped as $email => $group) {
    if (count($group) > 1) {
        $contact_groups[] = [
            'type' => 'อีเมลตรงกัน',
            'value' => $email,
            'students' => $group
        ];
    }
}

foreach ($phone_grouped as $phone => $group) {
    if (count($group) > 1) {
        $contact_groups[] = [
            'type' => 'เบอร์โทรตรงกัน',
            'value' => $phone,
            'students' => $group
        ];
    }
}

// 🤖 Compile AI Smart Duplicate Matching Suggestions
$ai_suggestions = [];

// 1. Exact matches:
foreach ($exact_groups as $group) {
    $candidates = enrichAndSortCandidates($conn, $group['students']);
    $keep = $candidates[0]['student'];
    $merges = array_slice($candidates, 1);
    $merge_ids = array_map(function($c) { return $c['student']['id']; }, $merges);
    $ai_suggestions[] = [
        'type' => 'Exact Name Match',
        'type_th' => 'ชื่อสะกดตรงเป๊ะ',
        'badge_class' => 'bg-danger text-white',
        'keep_id' => $keep['id'],
        'keep_name' => $keep['fullname'],
        'merge_ids' => implode(',', $merge_ids),
        'reason' => 'ตรวจพบประวัติผู้ใช้ที่สะกดตรงกันเป๊ะ ' . count($group['students']) . ' รายชื่อ เลือกบัญชีที่มีประวัติมากที่สุดเป็นหลัก'
    ];
}

// 2. Fuzzy matches:
foreach ($fuzzy_groups as $group) {
    $candidates = enrichAndSortCandidates($conn, $group['students']);
    $keep = $candidates[0]['student'];
    $merges = array_slice($candidates, 1);
    $merge_ids = array_map(function($c) { return $c['student']['id']; }, $merges);
    $ai_suggestions[] = [
        'type' => 'Fuzzy Name Match',
        'type_th' => 'ชื่อสะกดใกล้เคียง (' . $group['similarity'] . '%)',
        'badge_class' => 'bg-warning text-dark',
        'keep_id' => $keep['id'],
        'keep_name' => $keep['fullname'],
        'merge_ids' => implode(',', $merge_ids),
        'reason' => 'ชื่อสะกดใกล้เคียงกัน ความเหมือน ' . $group['similarity'] . '% แนะนำให้รวมบัญชีเพื่อความเป็นระเบียบ'
    ];
}

// 3. Duplicate student codes:
foreach ($code_groups as $group) {
    $candidates = enrichAndSortCandidates($conn, $group['students']);
    $keep = $candidates[0]['student'];
    $merges = array_slice($candidates, 1);
    $merge_ids = array_map(function($c) { return $c['student']['id']; }, $merges);
    $ai_suggestions[] = [
        'type' => 'Duplicate Code Match',
        'type_th' => 'รหัสนักศึกษาซ้ำ (' . $group['student_code'] . ')',
        'badge_class' => 'bg-info text-white',
        'keep_id' => $keep['id'],
        'keep_name' => $keep['fullname'],
        'merge_ids' => implode(',', $merge_ids),
        'reason' => 'รหัสนักศึกษาตรงกันแต่อาจลงทะเบียนไว้หลายบัญชีในระบบ'
    ];
}

// 4. Duplicate contacts:
foreach ($contact_groups as $group) {
    $candidates = enrichAndSortCandidates($conn, $group['students']);
    $keep = $candidates[0]['student'];
    $merges = array_slice($candidates, 1);
    $merge_ids = array_map(function($c) { return $c['student']['id']; }, $merges);
    $ai_suggestions[] = [
        'type' => 'Duplicate Contact Match',
        'type_th' => $group['type'] . ' (' . htmlspecialchars($group['value']) . ')',
        'badge_class' => 'bg-primary text-white',
        'keep_id' => $keep['id'],
        'keep_name' => $keep['fullname'],
        'merge_ids' => implode(',', $merge_ids),
        'reason' => 'ข้อมูลติดต่อตรงกัน (' . $group['type'] . ') แสดงว่าเป็นบุคคลเดียวกันอย่างแน่นอน'
    ];
}

include __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/admin-premium.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
:root {
    --glass-bg: rgba(255, 255, 255, 0.9);
    --glass-border: rgba(255, 255, 255, 0.2);
    --primary-gradient: linear-gradient(135deg, #6366f1 0%, #a855f7 100%);
    --keep-color: #10b981;
    --merge-color: #ef4444;
}

body {
    background: #f8fafc;
    min-height: 100vh;
    overflow-x: hidden;
}

.bg-blob {
    position: fixed;
    width: 500px;
    height: 500px;
    background: radial-gradient(circle, rgba(99, 102, 241, 0.15) 0%, rgba(168, 85, 247, 0.05) 100%);
    border-radius: 50%;
    filter: blur(80px);
    z-index: -1;
    animation: blobFloat 20s infinite alternate;
}
.blob-1 { top: -100px; right: -100px; }
.blob-2 { bottom: -100px; left: -100px; animation-delay: -5s; }

@keyframes blobFloat {
    0% { transform: translate(0, 0) scale(1); }
    100% { transform: translate(50px, 50px) scale(1.1); }
}

.glass-card {
    background: var(--glass-bg);
    backdrop-filter: blur(10px);
    border: 1px solid var(--glass-border);
    border-radius: 1.5rem;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.03);
}

.admin-header-section {
    background: var(--glass-bg);
    backdrop-filter: blur(10px);
    border: 1px solid var(--glass-border);
    padding: 1.5rem 2rem;
    border-radius: 1.25rem;
    margin-bottom: 2rem;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
}

.student-avatar {
    width: 50px;
    height: 50px;
    background: var(--primary-gradient);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 14px;
    font-weight: 700;
    font-size: 1.2rem;
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
}

.comparison-card {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border: 2px solid transparent;
    border-radius: 1.25rem;
    background: rgba(255, 255, 255, 0.65);
    cursor: pointer;
    position: relative;
    overflow: hidden;
}

.comparison-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 20px -8px rgba(0,0,0,0.15);
}

/* Card Selection Status styling */
.comparison-card.keep-active {
    border-color: var(--keep-color) !important;
    background: rgba(16, 185, 129, 0.05) !important;
    box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.1), 0 4px 6px -4px rgba(16, 185, 129, 0.1) !important;
}

.comparison-card.merge-active {
    border-color: var(--merge-color) !important;
    background: rgba(239, 68, 68, 0.05) !important;
    box-shadow: 0 10px 15px -3px rgba(239, 68, 68, 0.1), 0 4px 6px -4px rgba(239, 68, 68, 0.1) !important;
}

.stat-badge-row {
    font-size: 0.82rem;
    background: #f8fafc;
    border-radius: 0.75rem;
    padding: 0.5rem 0.75rem;
    border: 1px solid #e2e8f0;
}

.btn-merge-action {
    background: linear-gradient(135deg, #ef4444 0%, #f43f5e 100%);
    border: none;
    color: white;
    padding: 0.6rem 1.5rem;
    border-radius: 2rem;
    font-weight: 700;
    transition: all 0.3s;
    box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
}

.btn-merge-action:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(239, 68, 68, 0.4);
    color: white;
}

.btn-back-nav {
    border: 1px solid #cbd5e1;
    border-radius: 2rem;
    padding: 0.5rem 1.25rem;
    font-weight: 600;
    color: #475569;
    background: white;
    transition: all 0.2s;
}

.btn-back-nav:hover {
    background: #f1f5f9;
    color: #1e293b;
    border-color: #94a3b8;
}

.system-alert-banner {
    background: linear-gradient(135deg, #fef3c7 0%, #fffbeb 100%);
    border-left: 5px solid #d97706;
    color: #92400e;
}
.nav-pills .nav-link {
    color: #475569;
    background: rgba(255, 255, 255, 0.8);
    border: 1px solid rgba(226, 232, 240, 0.8);
    transition: all 0.2s ease-in-out;
}
.nav-pills .nav-link:hover:not(.active) {
    background: #f1f5f9;
    color: #1e293b;
}
.nav-pills .nav-link.active {
    background: var(--primary-gradient) !important;
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3) !important;
    color: white !important;
    border-color: transparent !important;
}
.animate-pulse-warning {
    animation: pulseWarning 2s infinite;
}
@keyframes pulseWarning {
    0% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.4); }
    70% { box-shadow: 0 0 0 10px rgba(245, 158, 11, 0); }
    100% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0); }
}
</style>

<div class="bg-blob blob-1"></div>
<div class="bg-blob blob-2"></div>

<div class="container admin-content-wrapper py-4 position-relative">
    
    <!-- 🏢 HEADER SECTION -->
    <div class="admin-header-section d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h2 class="admin-header-title mb-1">
                <i class="fas fa-user-friends text-danger me-2"></i>
                จัดการรายชื่อซ้ำซ้อน
            </h2>
            <p class="text-muted mb-0 small">เปรียบเทียบข้อมูลเชิงลึกและยุบรวมบัญชีนักเรียนซ้ำซ้อนให้ปลอดภัยอย่างเป็นระบบ</p>
        </div>
        <div>
            <a href="system_tools.php" class="btn btn-back-nav d-inline-flex align-items-center">
                <i class="fas fa-arrow-left me-2"></i> ย้อนกลับไปเครื่องมือระบบ
            </a>
        </div>
    </div>

    <!-- 💡 SYSTEM BANNER -->
    <div class="card border-0 shadow-sm glass-card mb-4 overflow-hidden">
        <div class="card-body p-4 system-alert-banner">
            <div class="d-flex align-items-start">
                <i class="fas fa-info-circle fs-3 me-3 mt-1"></i>
                <div>
                    <h5 class="fw-bold mb-1">💡 คำแนะนำในการรวมบัญชีนักศึกษาซ้ำซ้อน</h5>
                    <p class="mb-0 small leading-relaxed">
                        ปัญหารายชื่อซ้ำซ้อนมักเกิดขึ้นเมื่อ<strong>นักเรียนลงทะเบียนเรียนเองในระบบไว้ล่วงหน้า</strong> และจากนั้น<strong>แอดมินทำการนำเข้าไฟล์รายชื่อรวม (CSV)</strong> เข้ามาภายหลัง ระบบใหม่จะใช้อัลกอริทึมจับคู่ชื่อภาษาไทยอัตโนมัติเพื่อป้องกันแล้ว แต่สำหรับข้อมูลที่ซ้ำซ้อนเดิมที่เคยเกิดขึ้น ท่านสามารถเลือกยุบรวมได้ที่นี่ โดยระบบจะย้ายรายงานประจำวัน แฟ้มสะสมงาน และการประเมินต่างๆ ไปยัง<strong>บัญชีหลัก (Keep)</strong> แล้วทำการลบ<strong>บัญชีรอง (Merge)</strong> ออกอย่างถาวรโดยอัตโนมัติ
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- 🔍 MANUAL SEARCH & MERGE SECTION -->
    <div class="card border-0 shadow-sm glass-card p-4 mb-4" style="background: rgba(255, 255, 255, 0.85); border: 1px solid rgba(99, 102, 241, 0.2);">
        <h5 class="fw-bold text-dark mb-2">
            <i class="fas fa-compress-arrows-alt me-2 text-indigo"></i>
            เครื่องมือจับคู่และยุบรวมรายชื่อแบบกำหนดเอง (Manual Match & Merge Tool)
        </h5>
        <p class="text-muted small mb-4">
            กรณีพบรายชื่อซ้ำแต่สะกดชื่อไม่เหมือนกันเป๊ะๆ (เช่น ไม่มีนามสกุล, พิมพ์นามสกุลผิด หรือข้อมูลสะกดต่างกัน) ทำให้ระบบสแกนไม่พบโดยอัตโนมัติ ท่านสามารถค้นหาชื่อและเลือกบัญชีหลัก/รองด้วยตนเองเพื่อยุบรวมข้อมูลได้ทันทีที่นี่:
        </p>
        
        <div class="row g-4">
            <!-- KEEP COLUMN -->
            <div class="col-md-6">
                <div class="p-3 rounded-4 h-100" style="background: rgba(16, 185, 129, 0.03); border: 1px solid rgba(16, 185, 129, 0.12);">
                    <label class="form-label fw-bold text-success mb-2">
                        <i class="fas fa-check-circle me-1"></i> 1. เลือกบัญชีหลักที่จะเก็บรักษาไว้ (Keep)
                    </label>
                    <input type="text" class="form-control mb-2" id="search-keep" placeholder="🔍 พิมพ์ชื่อ, รหัส หรือ username เพื่อค้นหา..." oninput="onSearchStudent('keep')">
                    <select class="form-select text-dark" id="select-keep" size="5" onchange="onSelectStudent('keep')" style="max-height: 160px; font-size: 0.88rem; border-radius: 10px;">
                        <!-- Options loaded by JS -->
                    </select>
                    
                    <!-- PREVIEW BOX -->
                    <div id="preview-keep" class="mt-3 p-3 bg-white bg-opacity-75 rounded-3 border border-success-subtle d-none">
                        <h6 class="fw-bold text-dark mb-1" id="preview-keep-name">-</h6>
                        <span class="text-secondary small d-block mb-2" id="preview-keep-details">-</span>
                        <div class="row g-2 text-center" id="preview-keep-stats">
                            <!-- Stats loaded by AJAX -->
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- MERGE COLUMN -->
            <div class="col-md-6">
                <div class="p-3 rounded-4 h-100" style="background: rgba(239, 68, 68, 0.03); border: 1px solid rgba(239, 68, 68, 0.12);">
                    <label class="form-label fw-bold text-danger mb-2">
                        <i class="fas fa-user-slash me-1"></i> 2. เลือกบัญชีรองที่จะนำมารวมและลบออก (Merge)
                    </label>
                    <input type="text" class="form-control mb-2" id="search-merge" placeholder="🔍 พิมพ์ชื่อ, รหัส หรือ username เพื่อค้นหา..." oninput="onSearchStudent('merge')">
                    <select class="form-select text-dark" id="select-merge" size="5" onchange="onSelectStudent('merge')" style="max-height: 160px; font-size: 0.88rem; border-radius: 10px;">
                        <!-- Options loaded by JS -->
                    </select>
                    
                    <!-- PREVIEW BOX -->
                    <div id="preview-merge" class="mt-3 p-3 bg-white bg-opacity-75 rounded-3 border border-danger-subtle d-none">
                        <h6 class="fw-bold text-dark mb-1" id="preview-merge-name">-</h6>
                        <span class="text-secondary small d-block mb-2" id="preview-merge-details">-</span>
                        <div class="row g-2 text-center" id="preview-merge-stats">
                            <!-- Stats loaded by AJAX -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- AI ASSISTED SUGGESTIONS CONTAINER -->
        <div id="ai-suggestion-container" class="mt-4 p-3 rounded-4 border border-warning-subtle d-none animate__animated animate__fadeIn" style="background: rgba(245, 158, 11, 0.05);">
            <h6 class="fw-bold text-warning mb-2">
                <i class="fas fa-magic me-1"></i> ✨ AI ค้นหาคู่ซ้ำแนะนำ (Smart Duplicate Matching Suggestion)
            </h6>
            <div id="ai-suggestion-list" class="d-flex flex-wrap gap-2">
                <!-- Rendered dynamically by JS -->
            </div>
        </div>
        
        <div class="text-center mt-4">
            <button type="button" class="btn px-5 py-2 fw-bold btn-indigo" id="btn-manual-merge" onclick="submitManualMerge()" disabled style="border-radius: 2rem; background: var(--primary-gradient); color: white; border: none; box-shadow: 0 4px 15px rgba(99, 102, 241, 0.35);">
                <i class="fas fa-compress-arrows-alt me-1"></i> ยืนยันยุบรวมบัญชีแบบเลือกเอง
            </button>
        </div>
    </div>

    <!-- 📊 SCANNER RESULTS -->
    <div class="glass-card p-4 mb-4">
        <h5 class="fw-bold text-dark mb-3">
            <i class="fas fa-search me-2 text-indigo"></i>
            ผลการสแกนระบบอัจฉริยะ (Smart Scanner Results)
        </h5>
        <p class="text-muted small mb-4">
            ระบบทำการวิเคราะห์หาบัญชีซ้ำซ้อนโดยเปรียบเทียบทั้งแบบ<strong>สะกดตรงกันเป๊ะ</strong> และใช้ระบบช่วยค้นหาแบบ<strong>สะกดคล้ายคลึง (Levenshtein Similarity >= 55%)</strong> เพื่อความครอบคอบและแม่นยำสูงสุด:
        </p>

        <!-- Premium Tab Pills Navigation -->
        <ul class="nav nav-pills mb-4" id="scanner-tabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active px-4 py-2.5 fw-bold" id="exact-tab" data-bs-toggle="pill" data-bs-target="#exact-pane" type="button" role="tab" aria-controls="exact-pane" aria-selected="true" style="border-radius: 20px;">
                    <i class="fas fa-equals me-2"></i> สะกดตรงเป๊ะ (Exact Matches) 
                    <span class="badge bg-danger ms-2"><?php echo count($exact_groups); ?></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link px-4 py-2.5 fw-bold ms-2" id="fuzzy-tab" data-bs-toggle="pill" data-bs-target="#fuzzy-pane" type="button" role="tab" aria-controls="fuzzy-pane" aria-selected="false" style="border-radius: 20px;">
                    <i class="fas fa-magic me-2"></i> สะกดคล้ายคลึง (Fuzzy Matches) 
                    <span class="badge bg-warning text-dark ms-2"><?php echo count($fuzzy_groups); ?></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link px-4 py-2.5 fw-bold ms-2" id="code-tab" data-bs-toggle="pill" data-bs-target="#code-pane" type="button" role="tab" aria-controls="code-pane" aria-selected="false" style="border-radius: 20px;">
                    <i class="fas fa-id-card me-2"></i> รหัสนักศึกษาซ้ำ (Duplicate Codes) 
                    <span class="badge bg-info ms-2"><?php echo count($code_groups); ?></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link px-4 py-2.5 fw-bold ms-2" id="contact-tab" data-bs-toggle="pill" data-bs-target="#contact-pane" type="button" role="tab" aria-controls="contact-pane" aria-selected="false" style="border-radius: 20px;">
                    <i class="fas fa-phone me-2"></i> ข้อมูลติดต่อซ้ำ (Duplicate Contacts) 
                    <span class="badge bg-primary ms-2"><?php echo count($contact_groups); ?></span>
                </button>
            </li>
        </ul>

        <!-- Tab Panes Content -->
        <div class="tab-content" id="scanner-tabs-content">
            
            <!-- 1. EXACT MATCHES TAB PANE -->
            <div class="tab-pane fade show active" id="exact-pane" role="tabpanel" aria-labelledby="exact-tab">
                <?php if (empty($exact_groups)): ?>
                    <div class="text-center py-5 bg-white bg-opacity-50 rounded-4 border border-dashed border-2">
                        <div class="mb-3 text-success">
                            <i class="fas fa-check-circle" style="font-size: 4rem;"></i>
                        </div>
                        <h4 class="fw-bold text-success">ยอดเยี่ยม! ไม่พบรายชื่อนักเรียนซ้ำซ้อนแบบตรงตัว</h4>
                        <p class="text-muted mb-0">ไม่พบบัญชีที่สะกดชื่อตรงกันในระบบขณะนี้</p>
                    </div>
                <?php else: ?>
                    <!-- BATCH MERGE CONTROL BAR -->
                    <div class="card border-0 shadow-sm mb-4 p-3" style="border-radius: 1.25rem; background: rgba(255, 255, 255, 0.7); backdrop-filter: blur(10px); border: 1px solid rgba(226, 232, 240, 0.8);">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                            <div class="d-flex align-items-center">
                                <div class="form-check me-3">
                                    <input class="form-check-input" type="checkbox" id="selectAllExact" style="transform: scale(1.25); cursor: pointer;" onchange="toggleSelectAllExact(this)">
                                    <label class="form-check-label fw-bold text-dark ms-1" for="selectAllExact" style="cursor: pointer;">เลือกกลุ่มสะกดตรงเป๊ะทั้งหมด</label>
                                </div>
                                <span class="text-secondary small border-start ps-3"><i class="fas fa-info-circle text-primary me-1"></i> ระบบจะยุบรวมตามบัญชีหลัก (Keep) ที่ได้รับการคัดเลือกในแต่ละกลุ่มด้านล่าง</span>
                            </div>
                            <button type="button" class="btn btn-warning fw-bold text-dark px-4 py-2" id="btnBatchMerge" disabled onclick="executeBatchMerge()" style="border-radius: 0.75rem; box-shadow: 0 4px 12px rgba(245, 158, 11, 0.2); transition: all 0.2s ease;">
                                <i class="fas fa-compress-arrows-alt me-2"></i> ยุบรวมกลุ่มที่เลือกพร้อมกัน (<span id="selectedExactCount">0</span> กลุ่ม)
                            </button>
                        </div>
                    </div>

                    <?php foreach ($exact_groups as $group_idx => $group): 
                        $group_name = $group['students'][0]['fullname'];
                        $group_key = 'exact_' . $group_idx;
                        
                        $candidates = enrichAndSortCandidates($conn, $group['students']);
                        $recommended_keep_id = $candidates[0]['student']['id'];
                    ?>
                        <!-- EXACT MATCH GROUP CONTAINER -->
                        <div class="card border-0 shadow-sm mb-4" style="border-radius: 1.25rem; background: rgba(255, 255, 255, 0.45); border: 1px solid rgba(226, 232, 240, 0.8);">
                            <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center" style="border-top-left-radius: 1.25rem; border-top-right-radius: 1.25rem;">
                                <div class="d-flex align-items-center">
                                    <div class="form-check me-3">
                                        <input class="form-check-input batch-exact-checkbox" type="checkbox" 
                                               id="chk_group_<?php echo $group_key; ?>" 
                                               data-group-key="<?php echo $group_key; ?>" 
                                               data-group-name="<?php echo htmlspecialchars($group_name); ?>"
                                               style="transform: scale(1.25); cursor: pointer;" 
                                               onchange="updateBatchButtonState()">
                                    </div>
                                    <div>
                                        <span class="badge bg-secondary mb-1">กลุ่มสะกดตรงเป๊ะที่ <?php echo $group_idx + 1; ?></span>
                                        <h5 class="fw-bold text-dark mb-0">
                                            <i class="fas fa-user-circle me-2 text-primary"></i>
                                            <?php echo htmlspecialchars($group_name); ?>
                                        </h5>
                                    </div>
                                </div>
                                <div>
                                    <span class="badge bg-danger rounded-pill px-3 py-2 fw-semibold">
                                        <i class="fas fa-copy me-1"></i> พบซ้ำ <?php echo count($group['students']); ?> บัญชี
                                    </span>
                                </div>
                            </div>
                            <div class="card-body p-4">
                                <div class="row g-4">
                                    <?php foreach ($candidates as $cand_idx => $cand): 
                                        $s = $cand['student'];
                                        $stats = $cand['stats'];
                                        $is_default_keep = ($s['id'] === $recommended_keep_id);
                                        $card_class = $is_default_keep ? 'keep-active border-success' : 'merge-active border-danger';
                                        $badge_html = $is_default_keep 
                                            ? '<span class="badge bg-success"><i class="fas fa-check-circle me-1"></i> บัญชีที่จะเก็บไว้</span>'
                                            : '<span class="badge bg-danger"><i class="fas fa-user-slash me-1"></i> บัญชีที่จะยุบรวมและลบ</span>';
                                    ?>
                                        <div class="col-lg-6">
                                            <div class="card comparison-card group-<?php echo $group_key; ?>-card h-100 p-3 shadow-sm <?php echo $card_class; ?>" 
                                                 data-id="<?php echo $s['id']; ?>"
                                                 onclick="selectCard('<?php echo $group_key; ?>', <?php echo $s['id']; ?>)">
                                                
                                                <div class="d-flex justify-content-between align-items-center mb-3">
                                                    <div class="status-badge">
                                                        <?php echo $badge_html; ?>
                                                    </div>
                                                    <div class="form-check m-0">
                                                        <input class="form-check-input" type="radio" 
                                                               name="primary_group_<?php echo $group_key; ?>" 
                                                               id="radio_<?php echo $s['id']; ?>" 
                                                               value="<?php echo $s['id']; ?>" 
                                                               <?php echo $is_default_keep ? 'checked' : ''; ?>
                                                               style="transform: scale(1.3); cursor: pointer;"
                                                               onchange="updateSelection('<?php echo $group_key; ?>')">
                                                        <label class="form-check-label ms-1 small fw-bold text-dark" for="radio_<?php echo $s['id']; ?>" style="cursor: pointer;">
                                                            เลือกเป็นบัญชีหลัก
                                                        </label>
                                                    </div>
                                                </div>

                                                <div class="d-flex align-items-center mb-3">
                                                    <div class="student-avatar me-3">
                                                        <?php echo mb_substr(trim($s['fullname']), 0, 1, 'utf-8'); ?>
                                                    </div>
                                                    <div>
                                                        <h6 class="fw-bold text-dark mb-0"><?php echo htmlspecialchars($s['fullname']); ?></h6>
                                                        <span class="text-secondary small d-block">
                                                            <i class="fas fa-user me-1"></i> Username: <strong><?php echo htmlspecialchars($s['username']); ?></strong>
                                                        </span>
                                                        <span class="text-secondary small d-block">
                                                            <i class="fas fa-envelope me-1"></i> Email: <?php echo htmlspecialchars($s['email']); ?>
                                                        </span>
                                                    </div>
                                                </div>

                                                <!-- AI Reason / Recommendation Badge -->
                                                <div class="mb-3">
                                                    <div class="p-2 rounded bg-light border border-light-subtle">
                                                        <span class="text-muted d-block mb-1" style="font-size: 0.7rem;"><i class="fas fa-lightbulb text-warning me-1"></i> จุดเด่นของบัญชีนี้:</span>
                                                        <div class="d-flex flex-wrap gap-1">
                                                            <?php foreach ($cand['recommendation_reasons'] as $reason): ?>
                                                                <span class="badge bg-secondary-subtle text-secondary-emphasis small px-2 py-1" style="font-size: 0.68rem; border: 1px solid rgba(0,0,0,0.05);">
                                                                    <?php echo htmlspecialchars($reason); ?>
                                                                </span>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="row g-2 mb-3">
                                                    <div class="col-6">
                                                        <div class="px-2 py-1 bg-white bg-opacity-75 rounded" style="border: 1px dashed #cbd5e1;">
                                                            <span class="text-muted d-block" style="font-size: 0.72rem;">รหัสนักศึกษา:</span>
                                                            <strong class="text-dark small">
                                                                <?php echo !empty($s['student_code']) ? htmlspecialchars($s['student_code']) : '<span class="text-danger">ไม่มีข้อมูล</span>'; ?>
                                                            </strong>
                                                        </div>
                                                    </div>
                                                    <div class="col-6">
                                                        <div class="px-2 py-1 bg-white bg-opacity-75 rounded" style="border: 1px dashed #cbd5e1;">
                                                            <span class="text-muted d-block" style="font-size: 0.72rem;">ระดับการศึกษา:</span>
                                                            <strong class="text-dark small">
                                                                <?php echo !empty($s['student_level']) ? htmlspecialchars($s['student_level']) : '<span class="text-muted">-</span>'; ?>
                                                            </strong>
                                                        </div>
                                                    </div>
                                                    <div class="col-12">
                                                        <div class="px-2 py-1 bg-white bg-opacity-75 rounded" style="border: 1px dashed #cbd5e1;">
                                                            <span class="text-muted d-block" style="font-size: 0.72rem;">แผนกวิชา / ห้องเรียน:</span>
                                                            <strong class="text-dark small">
                                                                <?php 
                                                                    $class_label = isset($classroom_map[$s['classroom_id']]) ? $classroom_map[$s['classroom_id']] : '';
                                                                    $aff_label = !empty($s['affiliation']) ? $s['affiliation'] : '';
                                                                    if ($class_label && $aff_label) {
                                                                        echo htmlspecialchars($class_label . ' (' . $aff_label . ')');
                                                                    } elseif ($class_label) {
                                                                        echo htmlspecialchars($class_label);
                                                                    } elseif ($aff_label) {
                                                                        echo htmlspecialchars($aff_label);
                                                                    } else {
                                                                        echo '<span class="text-secondary small">ไม่ได้ระบุ</span>';
                                                                    }
                                                                ?>
                                                            </strong>
                                                        </div>
                                                    </div>
                                                    <div class="col-12">
                                                        <div class="px-2 py-1 bg-white bg-opacity-75 rounded" style="border: 1px dashed #cbd5e1;">
                                                            <span class="text-muted d-block" style="font-size: 0.72rem;">สถานประกอบการ:</span>
                                                            <strong class="text-dark small">
                                                                <?php 
                                                                    echo isset($company_map[$s['company_id']]) 
                                                                        ? htmlspecialchars($company_map[$s['company_id']]) 
                                                                        : '<span class="text-secondary small">ยังไม่ได้จับคู่สถานประกอบการ</span>'; 
                                                                ?>
                                                            </strong>
                                                        </div>
                                                    </div>
                                                </div>

                                                <h6 class="fw-bold text-dark small mb-2"><i class="fas fa-database me-1 text-secondary"></i> ข้อมูลบันทึกประวัติการใช้งาน:</h6>
                                                <div class="row g-2 mb-3">
                                                    <div class="col-4">
                                                        <div class="stat-badge-row text-center">
                                                            <span class="text-secondary d-block" style="font-size: 0.7rem;">📝 รายงาน</span>
                                                            <strong class="fs-6 <?php echo $stats['reports'] > 0 ? 'text-primary' : 'text-secondary'; ?>">
                                                                <?php echo $stats['reports']; ?>
                                                            </strong>
                                                        </div>
                                                    </div>
                                                    <div class="col-4">
                                                        <div class="stat-badge-row text-center">
                                                            <span class="text-secondary d-block" style="font-size: 0.7rem;">👨‍🏫 ครูประเมิน</span>
                                                            <strong class="fs-6 <?php echo $stats['evaluations'] > 0 ? 'text-indigo' : 'text-secondary'; ?>">
                                                                <?php echo $stats['evaluations']; ?>
                                                            </strong>
                                                        </div>
                                                    </div>
                                                    <div class="col-4">
                                                        <div class="stat-badge-row text-center">
                                                            <span class="text-secondary d-block" style="font-size: 0.7rem;">🏢 บ.ประเมิน</span>
                                                            <strong class="fs-6 <?php echo $stats['staff_evals'] > 0 ? 'text-success' : 'text-secondary'; ?>">
                                                                <?php echo $stats['staff_evals']; ?>
                                                            </strong>
                                                        </div>
                                                    </div>
                                                    <div class="col-6">
                                                        <div class="stat-badge-row text-center">
                                                            <span class="text-secondary d-block" style="font-size: 0.7rem;">📂 แฟ้มนิเทศ</span>
                                                            <strong class="fs-6 <?php echo $stats['supervision'] > 0 ? 'text-warning' : 'text-secondary'; ?>">
                                                                <?php echo $stats['supervision']; ?>
                                                            </strong>
                                                        </div>
                                                    </div>
                                                    <div class="col-6">
                                                        <div class="stat-badge-row text-center">
                                                            <span class="text-secondary d-block" style="font-size: 0.7rem;">🎓 เกียรติบัตร</span>
                                                            <strong class="fs-6 <?php echo $stats['certificates'] > 0 ? 'text-danger' : 'text-secondary'; ?>">
                                                                <?php echo $stats['certificates']; ?>
                                                            </strong>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="text-muted text-end mt-auto" style="font-size: 0.7rem;">
                                                    <i class="far fa-clock me-1"></i> ลงทะเบียน: <?php echo date('d/m/Y H:i', strtotime($s['created_at'])); ?> น.
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="card-footer bg-white border-0 py-3 d-flex justify-content-between align-items-center" style="border-bottom-left-radius: 1.25rem; border-bottom-right-radius: 1.25rem; border-top: 1px solid #edf2f7 !important;">
                                <span class="text-muted small">
                                    <i class="fas fa-shield-alt me-1"></i> ข้อมูลรายงานประจำวัน แฟ้มสะสมงาน และการประเมินต่างๆ จะย้ายไปบัญชีหลักอัตโนมัติ
                                </span>
                                <button type="button" class="btn btn-merge-action animate-pulse-warning" onclick="executeMerge('<?php echo $group_key; ?>', '<?php echo htmlspecialchars($group_name); ?>')">
                                    <i class="fas fa-compress-arrows-alt me-1"></i> ยุบรวมคู่นี้สำเร็จ
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- 2. FUZZY MATCHES TAB PANE -->
            <div class="tab-pane fade" id="fuzzy-pane" role="tabpanel" aria-labelledby="fuzzy-tab">
                <?php if (empty($fuzzy_groups)): ?>
                    <div class="text-center py-5 bg-white bg-opacity-50 rounded-4 border border-dashed border-2">
                        <div class="mb-3 text-warning">
                            <i class="fas fa-search" style="font-size: 4rem;"></i>
                        </div>
                        <h4 class="fw-bold text-dark">ไม่พบบัญชีสะกดคล้ายคลึงกัน (Fuzzy Duplicate Pairs)</h4>
                        <p class="text-muted mb-0">ระบบวิเคราะห์อย่างละเอียดแล้ว ไม่พบบัญชีนักเรียนที่สะกดชื่อคล้ายกันผิดปกติในขณะนี้</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($fuzzy_groups as $group_idx => $group): 
                        $group_name = $group['students'][0]['fullname'];
                        $group_key = 'fuzzy_' . $group_idx;
                        
                        $candidates = enrichAndSortCandidates($conn, $group['students']);
                        $recommended_keep_id = $candidates[0]['student']['id'];
                    ?>
                        <!-- FUZZY MATCH GROUP CONTAINER -->
                        <div class="card border-0 shadow-sm mb-4" style="border-radius: 1.25rem; background: rgba(255, 255, 255, 0.45); border: 1px solid rgba(226, 232, 240, 0.8);">
                            <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center" style="border-top-left-radius: 1.25rem; border-top-right-radius: 1.25rem;">
                                <div>
                                    <span class="badge bg-secondary mb-1">กลุ่มสะกดคล้ายที่ <?php echo $group_idx + 1; ?></span>
                                    <h5 class="fw-bold text-dark mb-0">
                                        <i class="fas fa-magic me-2 text-warning"></i>
                                        <?php echo htmlspecialchars($group['students'][0]['fullname']); ?> กับ <?php echo htmlspecialchars($group['students'][1]['fullname']); ?>
                                    </h5>
                                </div>
                                <div>
                                    <?php 
                                    $sim = $group['similarity'];
                                    $sim_badge = 'bg-warning text-dark';
                                    $sim_text = 'โปรดตรวจสอบรอบคอบ';
                                    if ($sim >= 85) {
                                        $sim_badge = 'bg-success text-white';
                                        $sim_text = 'ใกล้เคียงกันสูงมาก';
                                    } elseif ($sim >= 70) {
                                        $sim_badge = 'bg-info text-white';
                                        $sim_text = 'สะกดคล้ายคลึง';
                                    }
                                    ?>
                                    <span class="badge <?php echo $sim_badge; ?> rounded-pill px-3 py-2 fw-semibold">
                                        <i class="fas fa-fingerprint me-1"></i> ความคล้ายคลึง <?php echo $sim; ?>% (<?php echo $sim_text; ?>)
                                    </span>
                                </div>
                            </div>
                            <div class="card-body p-4">
                                <div class="row g-4">
                                    <?php foreach ($candidates as $cand_idx => $cand): 
                                        $s = $cand['student'];
                                        $stats = $cand['stats'];
                                        $is_default_keep = ($s['id'] === $recommended_keep_id);
                                        $card_class = $is_default_keep ? 'keep-active border-success' : 'merge-active border-danger';
                                        $badge_html = $is_default_keep 
                                            ? '<span class="badge bg-success"><i class="fas fa-check-circle me-1"></i> บัญชีที่จะเก็บไว้ (แนะนำ)</span>'
                                            : '<span class="badge bg-danger"><i class="fas fa-user-slash me-1"></i> บัญชีที่จะยุบรวมและลบ</span>';
                                    ?>
                                        <div class="col-lg-6">
                                            <div class="card comparison-card group-<?php echo $group_key; ?>-card h-100 p-3 shadow-sm <?php echo $card_class; ?>" 
                                                 data-id="<?php echo $s['id']; ?>"
                                                 onclick="selectCard('<?php echo $group_key; ?>', <?php echo $s['id']; ?>)">
                                                
                                                <div class="d-flex justify-content-between align-items-center mb-3">
                                                    <div class="status-badge">
                                                        <?php echo $badge_html; ?>
                                                    </div>
                                                    <div class="form-check m-0">
                                                        <input class="form-check-input" type="radio" 
                                                               name="primary_group_<?php echo $group_key; ?>" 
                                                               id="radio_<?php echo $s['id']; ?>" 
                                                               value="<?php echo $s['id']; ?>" 
                                                               <?php echo $is_default_keep ? 'checked' : ''; ?>
                                                               style="transform: scale(1.3); cursor: pointer;"
                                                               onchange="updateSelection('<?php echo $group_key; ?>')">
                                                        <label class="form-check-label ms-1 small fw-bold text-dark" for="radio_<?php echo $s['id']; ?>" style="cursor: pointer;">
                                                            เลือกเป็นบัญชีหลัก
                                                        </label>
                                                    </div>
                                                </div>

                                                <div class="d-flex align-items-center mb-3">
                                                    <div class="student-avatar me-3">
                                                        <?php echo mb_substr(trim($s['fullname']), 0, 1, 'utf-8'); ?>
                                                    </div>
                                                    <div>
                                                        <h6 class="fw-bold text-dark mb-0"><?php echo htmlspecialchars($s['fullname']); ?></h6>
                                                        <span class="text-secondary small d-block">
                                                            <i class="fas fa-user me-1"></i> Username: <strong><?php echo htmlspecialchars($s['username']); ?></strong>
                                                        </span>
                                                        <span class="text-secondary small d-block">
                                                            <i class="fas fa-envelope me-1"></i> Email: <?php echo htmlspecialchars($s['email']); ?>
                                                        </span>
                                                    </div>
                                                </div>

                                                <!-- AI Reason / Recommendation Badge -->
                                                <div class="mb-3">
                                                    <div class="p-2 rounded bg-light border border-light-subtle">
                                                        <span class="text-muted d-block mb-1" style="font-size: 0.7rem;"><i class="fas fa-lightbulb text-warning me-1"></i> จุดเด่นของบัญชีนี้:</span>
                                                        <div class="d-flex flex-wrap gap-1">
                                                            <?php foreach ($cand['recommendation_reasons'] as $reason): ?>
                                                                <span class="badge bg-secondary-subtle text-secondary-emphasis small px-2 py-1" style="font-size: 0.68rem; border: 1px solid rgba(0,0,0,0.05);">
                                                                    <?php echo htmlspecialchars($reason); ?>
                                                                </span>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="row g-2 mb-3">
                                                    <div class="col-6">
                                                        <div class="px-2 py-1 bg-white bg-opacity-75 rounded" style="border: 1px dashed #cbd5e1;">
                                                            <span class="text-muted d-block" style="font-size: 0.72rem;">รหัสนักศึกษา:</span>
                                                            <strong class="text-dark small">
                                                                <?php echo !empty($s['student_code']) ? htmlspecialchars($s['student_code']) : '<span class="text-danger">ไม่มีข้อมูล</span>'; ?>
                                                            </strong>
                                                        </div>
                                                    </div>
                                                    <div class="col-6">
                                                        <div class="px-2 py-1 bg-white bg-opacity-75 rounded" style="border: 1px dashed #cbd5e1;">
                                                            <span class="text-muted d-block" style="font-size: 0.72rem;">ระดับการศึกษา:</span>
                                                            <strong class="text-dark small">
                                                                <?php echo !empty($s['student_level']) ? htmlspecialchars($s['student_level']) : '<span class="text-muted">-</span>'; ?>
                                                            </strong>
                                                        </div>
                                                    </div>
                                                    <div class="col-12">
                                                        <div class="px-2 py-1 bg-white bg-opacity-75 rounded" style="border: 1px dashed #cbd5e1;">
                                                            <span class="text-muted d-block" style="font-size: 0.72rem;">แผนกวิชา / ห้องเรียน:</span>
                                                            <strong class="text-dark small">
                                                                <?php 
                                                                    $class_label = isset($classroom_map[$s['classroom_id']]) ? $classroom_map[$s['classroom_id']] : '';
                                                                    $aff_label = !empty($s['affiliation']) ? $s['affiliation'] : '';
                                                                    if ($class_label && $aff_label) {
                                                                        echo htmlspecialchars($class_label . ' (' . $aff_label . ')');
                                                                    } elseif ($class_label) {
                                                                        echo htmlspecialchars($class_label);
                                                                    } elseif ($aff_label) {
                                                                        echo htmlspecialchars($aff_label);
                                                                    } else {
                                                                        echo '<span class="text-secondary small">ไม่ได้ระบุ</span>';
                                                                    }
                                                                ?>
                                                            </strong>
                                                        </div>
                                                    </div>
                                                    <div class="col-12">
                                                        <div class="px-2 py-1 bg-white bg-opacity-75 rounded" style="border: 1px dashed #cbd5e1;">
                                                            <span class="text-muted d-block" style="font-size: 0.72rem;">สถานประกอบการ:</span>
                                                            <strong class="text-dark small">
                                                                <?php 
                                                                    echo isset($company_map[$s['company_id']]) 
                                                                        ? htmlspecialchars($company_map[$s['company_id']]) 
                                                                        : '<span class="text-secondary small">ยังไม่ได้จับคู่สถานประกอบการ</span>'; 
                                                                ?>
                                                            </strong>
                                                        </div>
                                                    </div>
                                                </div>

                                                <h6 class="fw-bold text-dark small mb-2"><i class="fas fa-database me-1 text-secondary"></i> ข้อมูลบันทึกประวัติการใช้งาน:</h6>
                                                <div class="row g-2 mb-3">
                                                    <div class="col-4">
                                                        <div class="stat-badge-row text-center">
                                                            <span class="text-secondary d-block" style="font-size: 0.7rem;">📝 รายงาน</span>
                                                            <strong class="fs-6 <?php echo $stats['reports'] > 0 ? 'text-primary' : 'text-secondary'; ?>">
                                                                <?php echo $stats['reports']; ?>
                                                            </strong>
                                                        </div>
                                                    </div>
                                                    <div class="col-4">
                                                        <div class="stat-badge-row text-center">
                                                            <span class="text-secondary d-block" style="font-size: 0.7rem;">👨‍🏫 ครูประเมิน</span>
                                                            <strong class="fs-6 <?php echo $stats['evaluations'] > 0 ? 'text-indigo' : 'text-secondary'; ?>">
                                                                <?php echo $stats['evaluations']; ?>
                                                            </strong>
                                                        </div>
                                                    </div>
                                                    <div class="col-4">
                                                        <div class="stat-badge-row text-center">
                                                            <span class="text-secondary d-block" style="font-size: 0.7rem;">🏢 บ.ประเมิน</span>
                                                            <strong class="fs-6 <?php echo $stats['staff_evals'] > 0 ? 'text-success' : 'text-secondary'; ?>">
                                                                <?php echo $stats['staff_evals']; ?>
                                                            </strong>
                                                        </div>
                                                    </div>
                                                    <div class="col-6">
                                                        <div class="stat-badge-row text-center">
                                                            <span class="text-secondary d-block" style="font-size: 0.7rem;">📂 แฟ้มนิเทศ</span>
                                                            <strong class="fs-6 <?php echo $stats['supervision'] > 0 ? 'text-warning' : 'text-secondary'; ?>">
                                                                <?php echo $stats['supervision']; ?>
                                                            </strong>
                                                        </div>
                                                    </div>
                                                    <div class="col-6">
                                                        <div class="stat-badge-row text-center">
                                                            <span class="text-secondary d-block" style="font-size: 0.7rem;">🎓 เกียรติบัตร</span>
                                                            <strong class="fs-6 <?php echo $stats['certificates'] > 0 ? 'text-danger' : 'text-secondary'; ?>">
                                                                <?php echo $stats['certificates']; ?>
                                                            </strong>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="text-muted text-end mt-auto" style="font-size: 0.7rem;">
                                                    <i class="far fa-clock me-1"></i> ลงทะเบียน: <?php echo date('d/m/Y H:i', strtotime($s['created_at'])); ?> น.
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="card-footer bg-white border-0 py-3 d-flex justify-content-between align-items-center" style="border-bottom-left-radius: 1.25rem; border-bottom-right-radius: 1.25rem; border-top: 1px solid #edf2f7 !important;">
                                <span class="text-muted small">
                                    <i class="fas fa-shield-alt me-1"></i> โปรดพิจารณาจากประวัติการใช้งาน บัญชีใดมีรายงานหรือประวัติครูประเมิน ควรเก็บรักษาไว้
                                </span>
                                <button type="button" class="btn btn-merge-action" onclick="executeMerge('<?php echo $group_key; ?>', '<?php echo htmlspecialchars($group_name); ?>')">
                                    <i class="fas fa-compress-arrows-alt me-1"></i> ยืนยันยุบรวมสะกดคล้ายคู่นี้
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- 3. DUPLICATE CODES TAB PANE -->
            <div class="tab-pane fade" id="code-pane" role="tabpanel" aria-labelledby="code-tab">
                <?php if (empty($code_groups)): ?>
                    <div class="text-center py-5 bg-white bg-opacity-50 rounded-4 border border-dashed border-2">
                        <div class="mb-3 text-info">
                            <i class="fas fa-id-card" style="font-size: 4rem;"></i>
                        </div>
                        <h4 class="fw-bold text-dark">ไม่พบรหัสนักศึกษาซ้ำซ้อน</h4>
                        <p class="text-muted mb-0">ไม่พบบัญชีผู้เรียนที่ลงทะเบียนด้วยรหัสประจำตัวตรงกันในระบบ</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($code_groups as $group_idx => $group): 
                        $group_name = $group['students'][0]['fullname'];
                        $group_key = 'code_' . $group_idx;
                        $code = $group['student_code'];
                        
                        $candidates = enrichAndSortCandidates($conn, $group['students']);
                        $recommended_keep_id = $candidates[0]['student']['id'];
                    ?>
                        <!-- DUPLICATE CODE GROUP CONTAINER -->
                        <div class="card border-0 shadow-sm mb-4" style="border-radius: 1.25rem; background: rgba(255, 255, 255, 0.45); border: 1px solid rgba(226, 232, 240, 0.8);">
                            <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center" style="border-top-left-radius: 1.25rem; border-top-right-radius: 1.25rem;">
                                <div>
                                    <span class="badge bg-info text-white mb-1">กลุ่มรหัสซ้ำที่ <?php echo $group_idx + 1; ?></span>
                                    <h5 class="fw-bold text-dark mb-0">
                                        <i class="fas fa-id-card me-2 text-info"></i>
                                        รหัสนักศึกษา: <?php echo htmlspecialchars($code); ?> (<?php echo htmlspecialchars($group_name); ?>)
                                    </h5>
                                </div>
                                <div>
                                    <span class="badge bg-danger rounded-pill px-3 py-2 fw-semibold">
                                        <i class="fas fa-copy me-1"></i> พบซ้ำ <?php echo count($group['students']); ?> บัญชี
                                    </span>
                                </div>
                            </div>
                            <div class="card-body p-4">
                                <div class="row g-4">
                                    <?php foreach ($candidates as $cand_idx => $cand): 
                                        $s = $cand['student'];
                                        $stats = $cand['stats'];
                                        $is_default_keep = ($s['id'] === $recommended_keep_id);
                                        $card_class = $is_default_keep ? 'keep-active border-success' : 'merge-active border-danger';
                                        $badge_html = $is_default_keep 
                                            ? '<span class="badge bg-success"><i class="fas fa-check-circle me-1"></i> บัญชีที่จะเก็บไว้ (แนะนำ)</span>'
                                            : '<span class="badge bg-danger"><i class="fas fa-user-slash me-1"></i> บัญชีที่จะยุบรวมและลบ</span>';
                                    ?>
                                        <div class="col-lg-6">
                                            <div class="card comparison-card group-<?php echo $group_key; ?>-card h-100 p-3 shadow-sm <?php echo $card_class; ?>" 
                                                 data-id="<?php echo $s['id']; ?>"
                                                 onclick="selectCard('<?php echo $group_key; ?>', <?php echo $s['id']; ?>)">
                                                
                                                <div class="d-flex justify-content-between align-items-center mb-3">
                                                    <div class="status-badge">
                                                        <?php echo $badge_html; ?>
                                                    </div>
                                                    <div class="form-check m-0">
                                                        <input class="form-check-input" type="radio" 
                                                               name="primary_group_<?php echo $group_key; ?>" 
                                                               id="radio_<?php echo $s['id']; ?>" 
                                                               value="<?php echo $s['id']; ?>" 
                                                               <?php echo $is_default_keep ? 'checked' : ''; ?>
                                                               style="transform: scale(1.3); cursor: pointer;"
                                                               onchange="updateSelection('<?php echo $group_key; ?>')">
                                                        <label class="form-check-label ms-1 small fw-bold text-dark" for="radio_<?php echo $s['id']; ?>" style="cursor: pointer;">
                                                            เลือกเป็นบัญชีหลัก
                                                        </label>
                                                    </div>
                                                </div>

                                                <div class="d-flex align-items-center mb-3">
                                                    <div class="student-avatar me-3">
                                                        <?php echo mb_substr(trim($s['fullname']), 0, 1, 'utf-8'); ?>
                                                    </div>
                                                    <div>
                                                        <h6 class="fw-bold text-dark mb-0"><?php echo htmlspecialchars($s['fullname']); ?></h6>
                                                        <span class="text-secondary small d-block">
                                                            <i class="fas fa-user me-1"></i> Username: <strong><?php echo htmlspecialchars($s['username']); ?></strong>
                                                        </span>
                                                        <span class="text-secondary small d-block">
                                                            <i class="fas fa-envelope me-1"></i> Email: <?php echo htmlspecialchars($s['email']); ?>
                                                        </span>
                                                    </div>
                                                </div>

                                                <!-- AI Reason / Recommendation Badge -->
                                                <div class="mb-3">
                                                    <div class="p-2 rounded bg-light border border-light-subtle">
                                                        <span class="text-muted d-block mb-1" style="font-size: 0.7rem;"><i class="fas fa-lightbulb text-warning me-1"></i> จุดเด่นของบัญชีนี้:</span>
                                                        <div class="d-flex flex-wrap gap-1">
                                                            <?php foreach ($cand['recommendation_reasons'] as $reason): ?>
                                                                <span class="badge bg-secondary-subtle text-secondary-emphasis small px-2 py-1" style="font-size: 0.68rem; border: 1px solid rgba(0,0,0,0.05);">
                                                                    <?php echo htmlspecialchars($reason); ?>
                                                                </span>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="row g-2 mb-3">
                                                    <div class="col-6">
                                                        <div class="px-2 py-1 bg-white bg-opacity-75 rounded" style="border: 1px solid #e2e8f0;">
                                                            <span class="text-muted d-block" style="font-size: 0.72rem;">รหัสนักศึกษา:</span>
                                                            <strong class="text-dark small">
                                                                <?php echo !empty($s['student_code']) ? htmlspecialchars($s['student_code']) : '<span class="text-danger">ไม่มีข้อมูล</span>'; ?>
                                                            </strong>
                                                        </div>
                                                    </div>
                                                    <div class="col-6">
                                                        <div class="px-2 py-1 bg-white bg-opacity-75 rounded" style="border: 1px solid #e2e8f0;">
                                                            <span class="text-muted d-block" style="font-size: 0.72rem;">ระดับการศึกษา:</span>
                                                            <strong class="text-dark small">
                                                                <?php echo !empty($s['student_level']) ? htmlspecialchars($s['student_level']) : '<span class="text-muted">-</span>'; ?>
                                                            </strong>
                                                        </div>
                                                    </div>
                                                    <div class="col-12">
                                                        <div class="px-2 py-1 bg-white bg-opacity-75 rounded" style="border: 1px solid #e2e8f0;">
                                                            <span class="text-muted d-block" style="font-size: 0.72rem;">แผนกวิชา / ห้องเรียน:</span>
                                                            <strong class="text-dark small">
                                                                <?php 
                                                                    $class_label = isset($classroom_map[$s['classroom_id']]) ? $classroom_map[$s['classroom_id']] : '';
                                                                    $aff_label = !empty($s['affiliation']) ? $s['affiliation'] : '';
                                                                    if ($class_label && $aff_label) {
                                                                        echo htmlspecialchars($class_label . ' (' . $aff_label . ')');
                                                                    } elseif ($class_label) {
                                                                        echo htmlspecialchars($class_label);
                                                                    } elseif ($aff_label) {
                                                                        echo htmlspecialchars($aff_label);
                                                                    } else {
                                                                        echo '<span class="text-secondary small">ไม่ได้ระบุ</span>';
                                                                    }
                                                                ?>
                                                            </strong>
                                                        </div>
                                                    </div>
                                                    <div class="col-12">
                                                        <div class="px-2 py-1 bg-white bg-opacity-75 rounded" style="border: 1px solid #e2e8f0;">
                                                            <span class="text-muted d-block" style="font-size: 0.72rem;">สถานประกอบการ:</span>
                                                            <strong class="text-dark small">
                                                                <?php 
                                                                    echo isset($company_map[$s['company_id']]) 
                                                                        ? htmlspecialchars($company_map[$s['company_id']]) 
                                                                        : '<span class="text-secondary small">ยังไม่ได้จับคู่สถานประกอบการ</span>'; 
                                                                ?>
                                                            </strong>
                                                        </div>
                                                    </div>
                                                </div>

                                                <h6 class="fw-bold text-dark small mb-2"><i class="fas fa-database me-1 text-secondary"></i> ข้อมูลบันทึกประวัติการใช้งาน:</h6>
                                                <div class="row g-2 mb-3">
                                                    <div class="col-4">
                                                        <div class="stat-badge-row text-center">
                                                            <span class="text-secondary d-block" style="font-size: 0.7rem;">📝 รายงาน</span>
                                                            <strong class="fs-6 <?php echo $stats['reports'] > 0 ? 'text-primary' : 'text-secondary'; ?>">
                                                                <?php echo $stats['reports']; ?>
                                                            </strong>
                                                        </div>
                                                    </div>
                                                    <div class="col-4">
                                                        <div class="stat-badge-row text-center">
                                                            <span class="text-secondary d-block" style="font-size: 0.7rem;">👨‍🏫 ครูประเมิน</span>
                                                            <strong class="fs-6 <?php echo $stats['evaluations'] > 0 ? 'text-indigo' : 'text-secondary'; ?>">
                                                                <?php echo $stats['evaluations']; ?>
                                                            </strong>
                                                        </div>
                                                    </div>
                                                    <div class="col-4">
                                                        <div class="stat-badge-row text-center">
                                                            <span class="text-secondary d-block" style="font-size: 0.7rem;">🏢 บ.ประเมิน</span>
                                                            <strong class="fs-6 <?php echo $stats['staff_evals'] > 0 ? 'text-success' : 'text-secondary'; ?>">
                                                                <?php echo $stats['staff_evals']; ?>
                                                            </strong>
                                                        </div>
                                                    </div>
                                                    <div class="col-6">
                                                        <div class="stat-badge-row text-center">
                                                            <span class="text-secondary d-block" style="font-size: 0.7rem;">📂 แฟ้มนิเทศ</span>
                                                            <strong class="fs-6 <?php echo $stats['supervision'] > 0 ? 'text-warning' : 'text-secondary'; ?>">
                                                                <?php echo $stats['supervision']; ?>
                                                            </strong>
                                                        </div>
                                                    </div>
                                                    <div class="col-6">
                                                        <div class="stat-badge-row text-center">
                                                            <span class="text-secondary d-block" style="font-size: 0.7rem;">🎓 เกียรติบัตร</span>
                                                            <strong class="fs-6 <?php echo $stats['certificates'] > 0 ? 'text-danger' : 'text-secondary'; ?>">
                                                                <?php echo $stats['certificates']; ?>
                                                            </strong>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="text-muted text-end mt-auto" style="font-size: 0.7rem;">
                                                    <i class="far fa-clock me-1"></i> ลงทะเบียน: <?php echo date('d/m/Y H:i', strtotime($s['created_at'])); ?> น.
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="card-footer bg-white border-0 py-3 d-flex justify-content-between align-items-center" style="border-bottom-left-radius: 1.25rem; border-bottom-right-radius: 1.25rem; border-top: 1px solid #edf2f7 !important;">
                                <span class="text-muted small">
                                    <i class="fas fa-shield-alt me-1"></i> ข้อมูลประวัติการใช้งานทั้งหมดจะถูกโอนไปรวมที่บัญชีหลักที่เลือก
                                </span>
                                <button type="button" class="btn btn-merge-action" onclick="executeMerge('<?php echo $group_key; ?>', '<?php echo htmlspecialchars($group_name); ?>')">
                                    <i class="fas fa-compress-arrows-alt me-1"></i> ยืนยันยุบรวมกลุ่มรหัสซ้ำนี้
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- 4. DUPLICATE CONTACTS TAB PANE -->
            <div class="tab-pane fade" id="contact-pane" role="tabpanel" aria-labelledby="contact-tab">
                <?php if (empty($contact_groups)): ?>
                    <div class="text-center py-5 bg-white bg-opacity-50 rounded-4 border border-dashed border-2">
                        <div class="mb-3 text-primary">
                            <i class="fas fa-phone" style="font-size: 4rem;"></i>
                        </div>
                        <h4 class="fw-bold text-dark">ไม่พบข้อมูลติดต่อซ้ำซ้อน</h4>
                        <p class="text-muted mb-0">ไม่พบบัญชีผู้เรียนที่ใช้เบอร์โทรศัพท์หรืออีเมลตรงกันในระบบ</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($contact_groups as $group_idx => $group): 
                        $group_name = $group['students'][0]['fullname'];
                        $group_key = 'contact_' . $group_idx;
                        $type_label = $group['type'];
                        $value_label = $group['value'];
                        
                        $candidates = enrichAndSortCandidates($conn, $group['students']);
                        $recommended_keep_id = $candidates[0]['student']['id'];
                    ?>
                        <!-- DUPLICATE CONTACT GROUP CONTAINER -->
                        <div class="card border-0 shadow-sm mb-4" style="border-radius: 1.25rem; background: rgba(255, 255, 255, 0.45); border: 1px solid rgba(226, 232, 240, 0.8);">
                            <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center" style="border-top-left-radius: 1.25rem; border-top-right-radius: 1.25rem;">
                                <div>
                                    <span class="badge bg-primary text-white mb-1">กลุ่มข้อมูลติดต่อซ้ำที่ <?php echo $group_idx + 1; ?></span>
                                    <h5 class="fw-bold text-dark mb-0">
                                        <i class="fas fa-phone me-2 text-primary"></i>
                                        <?php echo htmlspecialchars($type_label); ?>: <?php echo htmlspecialchars($value_label); ?> (<?php echo htmlspecialchars($group_name); ?>)
                                    </h5>
                                </div>
                                <div>
                                    <span class="badge bg-danger rounded-pill px-3 py-2 fw-semibold">
                                        <i class="fas fa-copy me-1"></i> พบซ้ำ <?php echo count($group['students']); ?> บัญชี
                                    </span>
                                </div>
                            </div>
                            <div class="card-body p-4">
                                <div class="row g-4">
                                    <?php foreach ($candidates as $cand_idx => $cand): 
                                        $s = $cand['student'];
                                        $stats = $cand['stats'];
                                        $is_default_keep = ($s['id'] === $recommended_keep_id);
                                        $card_class = $is_default_keep ? 'keep-active border-success' : 'merge-active border-danger';
                                        $badge_html = $is_default_keep 
                                            ? '<span class="badge bg-success"><i class="fas fa-check-circle me-1"></i> บัญชีที่จะเก็บไว้ (แนะนำ)</span>'
                                            : '<span class="badge bg-danger"><i class="fas fa-user-slash me-1"></i> บัญชีที่จะยุบรวมและลบ</span>';
                                    ?>
                                        <div class="col-lg-6">
                                            <div class="card comparison-card group-<?php echo $group_key; ?>-card h-100 p-3 shadow-sm <?php echo $card_class; ?>" 
                                                 data-id="<?php echo $s['id']; ?>"
                                                 onclick="selectCard('<?php echo $group_key; ?>', <?php echo $s['id']; ?>)">
                                                
                                                <div class="d-flex justify-content-between align-items-center mb-3">
                                                    <div class="status-badge">
                                                        <?php echo $badge_html; ?>
                                                    </div>
                                                    <div class="form-check m-0">
                                                        <input class="form-check-input" type="radio" 
                                                               name="primary_group_<?php echo $group_key; ?>" 
                                                               id="radio_<?php echo $s['id']; ?>" 
                                                               value="<?php echo $s['id']; ?>" 
                                                               <?php echo $is_default_keep ? 'checked' : ''; ?>
                                                               style="transform: scale(1.3); cursor: pointer;"
                                                               onchange="updateSelection('<?php echo $group_key; ?>')">
                                                        <label class="form-check-label ms-1 small fw-bold text-dark" for="radio_<?php echo $s['id']; ?>" style="cursor: pointer;">
                                                            เลือกเป็นบัญชีหลัก
                                                        </label>
                                                    </div>
                                                </div>

                                                <div class="d-flex align-items-center mb-3">
                                                    <div class="student-avatar me-3">
                                                        <?php echo mb_substr(trim($s['fullname']), 0, 1, 'utf-8'); ?>
                                                    </div>
                                                    <div>
                                                        <h6 class="fw-bold text-dark mb-0"><?php echo htmlspecialchars($s['fullname']); ?></h6>
                                                        <span class="text-secondary small d-block">
                                                            <i class="fas fa-user me-1"></i> Username: <strong><?php echo htmlspecialchars($s['username']); ?></strong>
                                                        </span>
                                                        <span class="text-secondary small d-block">
                                                            <i class="fas fa-envelope me-1"></i> Email: <?php echo htmlspecialchars($s['email']); ?>
                                                        </span>
                                                    </div>
                                                </div>

                                                <!-- AI Reason / Recommendation Badge -->
                                                <div class="mb-3">
                                                    <div class="p-2 rounded bg-light border border-light-subtle">
                                                        <span class="text-muted d-block mb-1" style="font-size: 0.7rem;"><i class="fas fa-lightbulb text-warning me-1"></i> จุดเด่นของบัญชีนี้:</span>
                                                        <div class="d-flex flex-wrap gap-1">
                                                            <?php foreach ($cand['recommendation_reasons'] as $reason): ?>
                                                                <span class="badge bg-secondary-subtle text-secondary-emphasis small px-2 py-1" style="font-size: 0.68rem; border: 1px solid rgba(0,0,0,0.05);">
                                                                    <?php echo htmlspecialchars($reason); ?>
                                                                </span>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="row g-2 mb-3">
                                                    <div class="col-6">
                                                        <div class="px-2 py-1 bg-white bg-opacity-75 rounded" style="border: 1px solid #e2e8f0;">
                                                            <span class="text-muted d-block" style="font-size: 0.72rem;">รหัสนักศึกษา:</span>
                                                            <strong class="text-dark small">
                                                                <?php echo !empty($s['student_code']) ? htmlspecialchars($s['student_code']) : '<span class="text-danger">ไม่มีข้อมูล</span>'; ?>
                                                            </strong>
                                                        </div>
                                                    </div>
                                                    <div class="col-6">
                                                        <div class="px-2 py-1 bg-white bg-opacity-75 rounded" style="border: 1px solid #e2e8f0;">
                                                            <span class="text-muted d-block" style="font-size: 0.72rem;">ระดับการศึกษา:</span>
                                                            <strong class="text-dark small">
                                                                <?php echo !empty($s['student_level']) ? htmlspecialchars($s['student_level']) : '<span class="text-muted">-</span>'; ?>
                                                            </strong>
                                                        </div>
                                                    </div>
                                                    <div class="col-12">
                                                        <div class="px-2 py-1 bg-white bg-opacity-75 rounded" style="border: 1px solid #e2e8f0;">
                                                            <span class="text-muted d-block" style="font-size: 0.72rem;">แผนกวิชา / ห้องเรียน:</span>
                                                            <strong class="text-dark small">
                                                                <?php 
                                                                    $class_label = isset($classroom_map[$s['classroom_id']]) ? $classroom_map[$s['classroom_id']] : '';
                                                                    $aff_label = !empty($s['affiliation']) ? $s['affiliation'] : '';
                                                                    if ($class_label && $aff_label) {
                                                                        echo htmlspecialchars($class_label . ' (' . $aff_label . ')');
                                                                    } elseif ($class_label) {
                                                                        echo htmlspecialchars($class_label);
                                                                    } elseif ($aff_label) {
                                                                        echo htmlspecialchars($aff_label);
                                                                    } else {
                                                                        echo '<span class="text-secondary small">ไม่ได้ระบุ</span>';
                                                                    }
                                                                ?>
                                                            </strong>
                                                        </div>
                                                    </div>
                                                    <div class="col-12">
                                                        <div class="px-2 py-1 bg-white bg-opacity-75 rounded" style="border: 1px solid #e2e8f0;">
                                                            <span class="text-muted d-block" style="font-size: 0.72rem;">สถานประกอบการ:</span>
                                                            <strong class="text-dark small">
                                                                <?php 
                                                                    echo isset($company_map[$s['company_id']]) 
                                                                        ? htmlspecialchars($company_map[$s['company_id']]) 
                                                                        : '<span class="text-secondary small">ยังไม่ได้จับคู่สถานประกอบการ</span>'; 
                                                                ?>
                                                            </strong>
                                                        </div>
                                                    </div>
                                                </div>

                                                <h6 class="fw-bold text-dark small mb-2"><i class="fas fa-database me-1 text-secondary"></i> ข้อมูลบันทึกประวัติการใช้งาน:</h6>
                                                <div class="row g-2 mb-3">
                                                    <div class="col-4">
                                                        <div class="stat-badge-row text-center">
                                                            <span class="text-secondary d-block" style="font-size: 0.7rem;">📝 รายงาน</span>
                                                            <strong class="fs-6 <?php echo $stats['reports'] > 0 ? 'text-primary' : 'text-secondary'; ?>">
                                                                <?php echo $stats['reports']; ?>
                                                            </strong>
                                                        </div>
                                                    </div>
                                                    <div class="col-4">
                                                        <div class="stat-badge-row text-center">
                                                            <span class="text-secondary d-block" style="font-size: 0.7rem;">👨‍🏫 ครูประเมิน</span>
                                                            <strong class="fs-6 <?php echo $stats['evaluations'] > 0 ? 'text-indigo' : 'text-secondary'; ?>">
                                                                <?php echo $stats['evaluations']; ?>
                                                            </strong>
                                                        </div>
                                                    </div>
                                                    <div class="col-4">
                                                        <div class="stat-badge-row text-center">
                                                            <span class="text-secondary d-block" style="font-size: 0.7rem;">🏢 บ.ประเมิน</span>
                                                            <strong class="fs-6 <?php echo $stats['staff_evals'] > 0 ? 'text-success' : 'text-secondary'; ?>">
                                                                <?php echo $stats['staff_evals']; ?>
                                                            </strong>
                                                        </div>
                                                    </div>
                                                    <div class="col-6">
                                                        <div class="stat-badge-row text-center">
                                                            <span class="text-secondary d-block" style="font-size: 0.7rem;">📂 แฟ้มนิเทศ</span>
                                                            <strong class="fs-6 <?php echo $stats['supervision'] > 0 ? 'text-warning' : 'text-secondary'; ?>">
                                                                <?php echo $stats['supervision']; ?>
                                                            </strong>
                                                        </div>
                                                    </div>
                                                    <div class="col-6">
                                                        <div class="stat-badge-row text-center">
                                                            <span class="text-secondary d-block" style="font-size: 0.7rem;">🎓 เกียรติบัตร</span>
                                                            <strong class="fs-6 <?php echo $stats['certificates'] > 0 ? 'text-danger' : 'text-secondary'; ?>">
                                                                <?php echo $stats['certificates']; ?>
                                                            </strong>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="text-muted text-end mt-auto" style="font-size: 0.7rem;">
                                                    <i class="far fa-clock me-1"></i> ลงทะเบียน: <?php echo date('d/m/Y H:i', strtotime($s['created_at'])); ?> น.
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="card-footer bg-white border-0 py-3 d-flex justify-content-between align-items-center" style="border-bottom-left-radius: 1.25rem; border-bottom-right-radius: 1.25rem; border-top: 1px solid #edf2f7 !important;">
                                <span class="text-muted small">
                                    <i class="fas fa-shield-alt me-1"></i> ข้อมูลประวัติการใช้งานทั้งหมดจะถูกโอนไปรวมที่บัญชีหลักที่เลือก
                                </span>
                                <button type="button" class="btn btn-merge-action" onclick="executeMerge('<?php echo $group_key; ?>', '<?php echo htmlspecialchars($group_name); ?>')">
                                    <i class="fas fa-compress-arrows-alt me-1"></i> ยืนยันยุบรวมกลุ่มข้อมูลติดต่อซ้ำนี้
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<script>
// Toggle card selection visually when clicking anywhere on the card
function selectCard(groupKey, userId) {
    const radioBtn = document.getElementById('radio_' + userId);
    if (radioBtn) {
        radioBtn.checked = true;
        updateSelection(groupKey);
    }
}

// Update card states (borders and badges) in real-time
function updateSelection(groupKey) {
    const selectedVal = document.querySelector(`input[name="primary_group_${groupKey}"]:checked`).value;
    const cards = document.querySelectorAll(`.group-${groupKey}-card`);
    
    cards.forEach(card => {
        const cardId = card.getAttribute('data-id');
        if (cardId === selectedVal) {
            card.classList.add('keep-active', 'border-success');
            card.classList.remove('merge-active', 'border-danger');
            card.querySelector('.status-badge').innerHTML = '<span class="badge bg-success"><i class="fas fa-check-circle me-1"></i> บัญชีที่จะเก็บไว้</span>';
        } else {
            card.classList.add('merge-active', 'border-danger');
            card.classList.remove('keep-active', 'border-success');
            card.querySelector('.status-badge').innerHTML = '<span class="badge bg-danger"><i class="fas fa-user-slash me-1"></i> บัญชีที่จะยุบรวมและลบ</span>';
        }
    });
}

// Perform the merge operation via AJAX
function executeMerge(groupKey, fullname) {
    const keepId = document.querySelector(`input[name="primary_group_${groupKey}"]:checked`).value;
    const cards = document.querySelectorAll(`.group-${groupKey}-card`);
    let mergeIds = [];
    
    cards.forEach(card => {
        const id = parseInt(card.getAttribute('data-id'));
        if (id !== parseInt(keepId)) {
            mergeIds.push(id);
        }
    });

    if (keepId <= 0 || mergeIds.length === 0) {
        Swal.fire('เกิดข้อผิดพลาด', 'ไม่สามารถค้นหารายชื่อบัญชีรองที่จะนำมารวมได้', 'error');
        return;
    }

    Swal.fire({
        title: 'ยืนยันการยุบรวมข้อมูลซ้ำซ้อน?',
        html: `
            <div class="text-start">
                <p>ระบบกำลังดำเนินการยุบรวมข้อมูลของนักเรียน <strong>${fullname}</strong>:</p>
                <ul class="small text-secondary ps-3">
                    <li><strong class="text-success">บัญชีหลัก (Keep ID: ${keepId})</strong> จะเก็บรักษาข้อมูลประวัติและรหัสผ่านเข้าใช้งานไว้</li>
                    <li><strong class="text-danger">บัญชีรอง (Merge ID: ${mergeIds.join(', ')})</strong> จะถูกโอนถ่ายข้อมูลคุณลักษณะและถูกลบออก</li>
                    <li>ประวัติรายงานประจำวัน แฟ้มสะสมงาน การประเมินผลสัมฤทธิ์ทั้งหมดจะถูกเชื่อมเข้าด้วยกันโดยสมบูรณ์</li>
                </ul>
                <p class="mb-0 text-center fw-bold text-danger">⚠️ ยืนยันการทำรายการหรือไม่? การยุบรวมจะไม่สามารถยกเลิกภายหลังได้</p>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'ยืนยัน ยุบรวมเข้าด้วยกัน',
        cancelButtonText: 'ยกเลิก',
        showLoaderOnConfirm: true,
        preConfirm: () => {
            const formData = new FormData();
            formData.append('action', 'merge');
            formData.append('keep_id', keepId);
            formData.append('merge_ids', mergeIds.join(','));

            return fetch('merge_duplicates.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('ระบบขัดข้องกรุณาลองใหม่อีกครั้ง');
                }
                return response.json();
            })
            .then(data => {
                if (!data.success) {
                    throw new Error(data.message);
                }
                return data;
            })
            .catch(error => {
                Swal.showValidationMessage(`เกิดข้อผิดพลาด: ${error.message}`);
            });
        },
        allowOutsideClick: () => !Swal.isLoading()
    }).then((result) => {
        if (result.isConfirmed && result.value.success) {
            Swal.fire({
                title: 'สำเร็จ!',
                text: result.value.message,
                icon: 'success',
                confirmButtonColor: '#10b981'
            }).then(() => {
                // Reload the page to refresh scanner and results
                window.location.reload();
            });
        }
    });
}

// Select all exact match checkboxes
function toggleSelectAllExact(masterCheckbox) {
    const checkboxes = document.querySelectorAll('.batch-exact-checkbox');
    checkboxes.forEach(cb => {
        cb.checked = masterCheckbox.checked;
    });
    updateBatchButtonState();
}

// Update the batch merge button status and counts
function updateBatchButtonState() {
    const checkboxes = document.querySelectorAll('.batch-exact-checkbox:checked');
    const btn = document.getElementById('btnBatchMerge');
    const countSpan = document.getElementById('selectedExactCount');
    
    countSpan.textContent = checkboxes.length;
    
    if (checkboxes.length > 0) {
        btn.disabled = false;
        btn.classList.remove('btn-warning');
        btn.classList.add('btn-success');
    } else {
        btn.disabled = true;
        btn.classList.remove('btn-success');
        btn.classList.add('btn-warning');
    }
}

// Execute batch merge operation sequentially with a gorgeous progress bar
function executeBatchMerge() {
    const checkboxes = document.querySelectorAll('.batch-exact-checkbox:checked');
    if (checkboxes.length === 0) return;
    
    let mergeTasks = [];
    checkboxes.forEach(cb => {
        const groupKey = cb.getAttribute('data-group-key');
        const fullname = cb.getAttribute('data-group-name');
        
        // Find keep ID for this group
        const radio = document.querySelector(`input[name="primary_group_${groupKey}"]:checked`);
        if (!radio) return;
        
        const keepId = parseInt(radio.value);
        
        // Find merge IDs (all other cards in this group)
        const cards = document.querySelectorAll(`.group-${groupKey}-card`);
        let mergeIds = [];
        cards.forEach(card => {
            const id = parseInt(card.getAttribute('data-id'));
            if (id !== keepId) {
                mergeIds.push(id);
            }
        });
        
        if (keepId > 0 && mergeIds.length > 0) {
            mergeTasks.push({
                groupKey: groupKey,
                fullname: fullname,
                keepId: keepId,
                mergeIds: mergeIds
            });
        }
    });
    
    if (mergeTasks.length === 0) {
        Swal.fire('เกิดข้อผิดพลาด', 'ไม่พบรายชื่อบัญชีรองสำหรับยุบรวมในกลุ่มที่เลือก', 'error');
        return;
    }
    
    Swal.fire({
        title: 'ยืนยันการยุบรวมแบบกลุ่ม?',
        html: `
            <div class="text-start">
                <p class="mb-2 text-dark font-semibold">คุณกำลังจะยุบรวมกลุ่มรายชื่อสะกดตรงกันจำนวน <strong>${mergeTasks.length} กลุ่ม</strong> ที่เลือก</p>
                <div class="alert alert-warning p-2 rounded small mb-3">
                    <i class="fas fa-exclamation-triangle me-1"></i> <strong>โปรดทราบ:</strong> ระบบจะใช้บัญชีหลัก (Keep ID) ที่มีคะแนนความเคลื่อนไหวมากที่สุดของแต่ละกลุ่ม และถ่ายโอนบันทึกรายงาน/การประเมินจากบัญชีซ้ำซ้อนอื่นๆ ทั้งหมดเข้าไว้ด้วยกันอย่างถาวร
                </div>
                <p class="mb-0 text-center fw-bold text-danger small">⚠️ การทำงานนี้ไม่สามารถย้อนกลับได้ โปรดตรวจทานอีกครั้ง</p>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'ยืนยัน ยุบรวมทั้งหมดที่เลือก',
        cancelButtonText: 'ยกเลิก',
    }).then(async (result) => {
        if (result.isConfirmed) {
            // Show beautiful modern progress modal
            Swal.fire({
                title: 'กำลังยุบรวมข้อมูล...',
                html: `
                    <div class="text-center my-3">
                        <div class="progress mb-3" style="height: 10px; border-radius: 5px; overflow: hidden; background: #e2e8f0;">
                            <div id="batchProgressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-success" role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        <div id="batchProgressText" class="fw-semibold text-dark">เริ่มต้นทำรายการ... (0/${mergeTasks.length})</div>
                        <div id="batchCurrentItem" class="text-secondary small mt-1">กำลังเริ่ม...</div>
                    </div>
                `,
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            let successCount = 0;
            let failureCount = 0;
            let errors = [];
            
            for (let i = 0; i < mergeTasks.length; i++) {
                const task = mergeTasks[i];
                
                // Update UI progress in modal
                const progressPercent = Math.round((i / mergeTasks.length) * 100);
                const progressBar = document.getElementById('batchProgressBar');
                const progressText = document.getElementById('batchProgressText');
                const currentItem = document.getElementById('batchCurrentItem');
                
                if (progressBar) progressBar.style.width = `${progressPercent}%`;
                if (progressText) progressText.textContent = `กำลังดำเนินงาน... (${i}/${mergeTasks.length})`;
                if (currentItem) currentItem.textContent = `กำลังรวมชื่อ: ${task.fullname}`;
                
                // Perform single merge AJAX call
                const formData = new FormData();
                formData.append('action', 'merge');
                formData.append('keep_id', task.keepId);
                formData.append('merge_ids', task.mergeIds.join(','));
                
                try {
                    const response = await fetch('merge_duplicates.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    if (!response.ok) {
                        throw new Error('การเชื่อมต่อเครือข่ายขัดข้อง');
                    }
                    
                    const resJson = await response.json();
                    if (!resJson.success) {
                        throw new Error(resJson.message);
                    }
                    
                    successCount++;
                } catch (err) {
                    failureCount++;
                    errors.push(`${task.fullname}: ${err.message}`);
                }
            }
            
            // Finish Progress Bar
            const progressBar = document.getElementById('batchProgressBar');
            if (progressBar) progressBar.style.width = '100%';
            
            // Show summary popup
            if (failureCount === 0) {
                Swal.fire({
                    title: 'ยุบรวมสำเร็จ!',
                    text: `ดำเนินการยุบรวมข้อมูลเรียบร้อยจำนวน ${successCount} กลุ่มประวัติอย่างปลอดภัย`,
                    icon: 'success',
                    confirmButtonColor: '#10b981'
                }).then(() => {
                    window.location.reload();
                });
            } else {
                Swal.fire({
                    title: 'เสร็จสิ้นโดยมีข้อผิดพลาดบางส่วน',
                    html: `
                        <div class="text-start">
                            <p class="text-success fw-bold"><i class="fas fa-check-circle me-1"></i> ยุบรวมสำเร็จ: ${successCount} กลุ่ม</p>
                            <p class="text-danger fw-bold"><i class="fas fa-times-circle me-1"></i> ขัดข้อง: ${failureCount} กลุ่ม</p>
                            <div class="p-2 border rounded bg-light text-danger small" style="max-height: 150px; overflow-y: auto;">
                                <strong>รายละเอียดข้อผิดพลาด:</strong><br>
                                ${errors.map(e => `• ${e}`).join('<br>')}
                            </div>
                        </div>
                    `,
                    icon: 'warning',
                    confirmButtonColor: '#64748b',
                    confirmButtonText: 'รับทราบและโหลดหน้าเว็บใหม่'
                }).then(() => {
                    window.location.reload();
                });
            }
        }
    });
}
</script>

<script>
// ==========================================
// 🛠️ CUSTOM MANUAL MATCH & MERGE ENGINE
// ==========================================
const allStudents = <?php echo json_encode($all_students); ?>;
const classroomMap = <?php echo json_encode($classroom_map); ?>;
const companyMap = <?php echo json_encode($company_map); ?>;

function initManualMerge() {
    renderList('keep', '');
    renderList('merge', '');
}

function renderList(type, filterText) {
    const select = document.getElementById(`select-${type}`);
    select.innerHTML = '';
    
    const filter = filterText.toLowerCase().trim();
    
    allStudents.forEach(s => {
        const classLabel = classroomMap[s.classroom_id] || 'ไม่มีห้องเรียน';
        const codeLabel = s.student_code ? `รหัส: ${s.student_code}` : 'ไม่มีรหัส';
        const displayLabel = `${s.fullname} (${codeLabel} | ห้อง: ${classLabel} | user: ${s.username})`;
        
        if (filter === '' || s.fullname.toLowerCase().includes(filter) || (s.student_code && s.student_code.includes(filter)) || s.username.toLowerCase().includes(filter)) {
            const opt = document.createElement('option');
            opt.value = s.id;
            opt.textContent = displayLabel;
            select.appendChild(opt);
        }
    });
}

function onSearchStudent(type) {
    const searchVal = document.getElementById(`search-${type}`).value;
    renderList(type, searchVal);
}

function onSelectStudent(type) {
    const select = document.getElementById(`select-${type}`);
    const selectedId = select.value;
    if (!selectedId) return;
    
    const student = allStudents.find(s => s.id == selectedId);
    if (!student) return;
    
    // Show preview card
    const previewDiv = document.getElementById(`preview-${type}`);
    previewDiv.classList.remove('d-none');
    
    document.getElementById(`preview-${type}-name`).textContent = student.fullname;
    const classLabel = classroomMap[student.classroom_id] || 'ยังไม่ได้ระบุห้องเรียน';
    const compLabel = companyMap[student.company_id] || 'ยังไม่ได้จับคู่สถานประกอบการ';
    document.getElementById(`preview-${type}-details`).innerHTML = `
        <i class="fas fa-id-card me-1"></i> รหัสนักศึกษา: <strong>${student.student_code || 'ไม่มีข้อมูล'}</strong><br>
        <i class="fas fa-user me-1"></i> Username: <strong>${student.username}</strong><br>
        <i class="fas fa-school me-1"></i> ห้องเรียน: <strong>${classLabel}</strong><br>
        <i class="fas fa-building me-1"></i> สถานประกอบการ: <strong>${compLabel}</strong>
    `;
    
    // Fetch stats via AJAX
    const statsContainer = document.getElementById(`preview-${type}-stats`);
    statsContainer.innerHTML = '<div class="col-12 py-2 small text-muted"><i class="fas fa-spinner fa-spin me-1"></i> โหลดประวัติ...</div>';
    
    const formData = new FormData();
    formData.append('action', 'get_stats');
    formData.append('student_id', selectedId);
    
    fetch('merge_duplicates.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(stats => {
        statsContainer.innerHTML = `
            <div class="col-4">
                <div class="stat-badge-row text-center p-1" style="font-size:0.75rem;">
                    <span class="text-secondary d-block" style="font-size: 0.65rem;">📝 รายงาน</span>
                    <strong class="${stats.reports > 0 ? 'text-primary' : 'text-secondary'}">${stats.reports}</strong>
                </div>
            </div>
            <div class="col-4">
                <div class="stat-badge-row text-center p-1" style="font-size:0.75rem;">
                    <span class="text-secondary d-block" style="font-size: 0.65rem;">👨‍🏫 ครูประเมิน</span>
                    <strong class="${stats.evaluations > 0 ? 'text-indigo' : 'text-secondary'}">${stats.evaluations}</strong>
                </div>
            </div>
            <div class="col-4">
                <div class="stat-badge-row text-center p-1" style="font-size:0.75rem;">
                    <span class="text-secondary d-block" style="font-size: 0.65rem;">🏢 บ.ประเมิน</span>
                    <strong class="${stats.staff_evals > 0 ? 'text-success' : 'text-secondary'}">${stats.staff_evals}</strong>
                </div>
            </div>
        `;
        
        checkButtonState();
    });
}

function checkButtonState() {
    const keepId = document.getElementById('select-keep').value;
    const mergeId = document.getElementById('select-merge').value;
    const btn = document.getElementById('btn-manual-merge');
    
    if (keepId && mergeId && keepId !== mergeId) {
        btn.disabled = false;
    } else {
        btn.disabled = true;
    }
}

function submitManualMerge() {
    const keepId = document.getElementById('select-keep').value;
    const mergeId = document.getElementById('select-merge').value;
    
    if (!keepId || !mergeId || keepId === mergeId) {
        Swal.fire('ข้อผิดพลาด', 'กรุณาเลือกบัญชีหลักและบัญชีรองที่ต่างกัน', 'error');
        return;
    }
    
    const keepName = allStudents.find(s => s.id == keepId).fullname;
    const mergeName = allStudents.find(s => s.id == mergeId).fullname;
    
    Swal.fire({
        title: 'ยืนยันการรวมบัญชีด้วยตนเอง?',
        html: `
            <div class="text-start">
                <p>ท่านกำลังดำเนินการรวมบัญชีของนักเรียนแบบกำหนดเอง:</p>
                <div class="p-3 mb-3 rounded border" style="background: rgba(16, 185, 129, 0.05); border-color: rgba(16, 185, 129, 0.2) !important;">
                    <strong class="text-success"><i class="fas fa-check-circle me-1"></i> บัญชีหลักที่จะเก็บไว้ (Keep):</strong><br>
                    ชื่อ: <strong>${keepName}</strong> (ID: ${keepId})
                </div>
                <div class="p-3 mb-3 rounded border" style="background: rgba(239, 68, 68, 0.05); border-color: rgba(239, 68, 68, 0.2) !important;">
                    <strong class="text-danger"><i class="fas fa-user-slash me-1"></i> บัญชีรองที่จะยุบรวมและลบออก (Merge):</strong><br>
                    ชื่อ: <strong>${mergeName}</strong> (ID: ${mergeId})
                </div>
                <p class="small text-muted">⚠️ ประวัติกิจกรรม รายงานประจำวัน ประเมินสถานประกอบการ และเกียรติบัตรทั้งหมดของบัญชีรองจะถูกโอนไปให้บัญชีหลักทั้งหมด และบัญชีรองจะถูกลบออกจากระบบอย่างถาวร</p>
                <p class="mb-0 text-center fw-bold text-danger">ยืนยันการทำรายการหรือไม่? ยุบรวมแล้วไม่สามารถยกเลิกได้</p>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'ยืนยันรวมบัญชีคู่นี้',
        cancelButtonText: 'ยกเลิก',
        showLoaderOnConfirm: true,
        preConfirm: () => {
            const formData = new FormData();
            formData.append('action', 'merge');
            formData.append('keep_id', keepId);
            formData.append('merge_id', mergeId);
            
            return fetch('merge_duplicates.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('ระบบขัดข้องกรุณาลองใหม่อีกครั้ง');
                }
                return response.json();
            })
            .then(data => {
                if (!data.success) {
                    throw new Error(data.message);
                }
                return data;
            })
            .catch(error => {
                Swal.showValidationMessage(`เกิดข้อผิดพลาด: ${error.message}`);
            });
        },
        allowOutsideClick: () => !Swal.isLoading()
    }).then((result) => {
        if (result.isConfirmed && result.value.success) {
            Swal.fire({
                title: 'สำเร็จ!',
                text: result.value.message,
                icon: 'success',
                confirmButtonColor: '#10b981'
            }).then(() => {
                window.location.reload();
            });
        }
    });
}

// Initialize manual merge components on load
document.addEventListener('DOMContentLoaded', function() {
    initManualMerge();
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
