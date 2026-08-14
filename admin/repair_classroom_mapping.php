<?php
/**
 * ================================================================
 * Data Repair Tool: Fix Classroom Mismatch & Orphaned Students
 * ================================================================
 * Features:
 *  - CLI execution support (runs silently & auto-corrects).
 *  - Premium Web Dashboard (glassmorphism UI with elegant animations).
 *  - Real-time client-side interactive scanning & filtering.
 *  - Mass automatic correction & manual override selectors.
 * ================================================================
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php';

// Allow CLI run without session
$isCli = php_sapi_name() === 'cli';
if (!$isCli) {
    require_login();
    require_role(['admin']);
}

// ─────────────────────────────────────────────────────────────────────────────
// 1. RE-USE SYSTEM TOKEN RESOLUTION LOGIC
// ─────────────────────────────────────────────────────────────────────────────
function resolveClassroomLocal($conn, $student_level, $affiliation) {
    // Normalize level
    $lvl = preg_replace('/([ปวชส]+)\.\s+/', '$1.', trim($student_level));
    $affName = trim($affiliation);
    
    if (empty($affName)) {
        return null;
    }

    // Replace punctuation and parentheses with spaces, then split by whitespace
    $cleanAff = preg_replace('/[\(\)\+\-\[\]\{\}\.,\/_]/u', ' ', $affName);
    $rawTokens = preg_split('/\s+/u', $cleanAff);
    $tokens = [];
    
    // Stop words to exclude from matching tokens
    $stopWords = ['ห้อง', 'สาขาวิชา', 'สาขา'];
    
    foreach ($rawTokens as $t) {
        $t = trim($t);
        if ($t !== '' && !in_array($t, $stopWords)) {
            $tokens[] = $t;
        }
    }

    // Cache classrooms to avoid multiple SQL queries in the loop
    static $allClassrooms = null;
    if ($allClassrooms === null) {
        $allClassrooms = [];
        $res = $conn->query("SELECT id, class_name FROM classrooms ORDER BY class_name ASC");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $allClassrooms[] = $row;
            }
        }
    }

    // Strategy 1: Match level AND all affiliation tokens
    foreach ($allClassrooms as $class) {
        $className = $class['class_name'];
        
        // Level check is mandatory if level is specified
        if (!empty($lvl)) {
            if (mb_strpos($className, $lvl) === false) {
                continue;
            }
        }
        
        // Token check: all tokens must match
        $allMatched = true;
        foreach ($tokens as $token) {
            if (mb_strpos($className, $token) === false) {
                $allMatched = false;
                break;
            }
        }
        
        if ($allMatched) {
            return $class;
        }
    }

    // Strategy 2: Match all affiliation tokens only (only if level is not specified)
    if (empty($lvl)) {
        foreach ($allClassrooms as $class) {
            $className = $class['class_name'];
            
            $allMatched = true;
            foreach ($tokens as $token) {
                if (mb_strpos($className, $token) === false) {
                    $allMatched = false;
                    break;
                }
            }
            
            if ($allMatched) {
                return $class;
            }
        }
    }

    return null;
}

// Fetch all classrooms for UI dropdowns
function getAllClassrooms($conn) {
    $res = $conn->query("SELECT id, class_name FROM classrooms ORDER BY class_name ASC");
    $list = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $list[] = $row;
        }
    }
    return $list;
}

// ─────────────────────────────────────────────────────────────────────────────
// 2. CLI BACKGROUND EXECUTION MODE
// ─────────────────────────────────────────────────────────────────────────────
if ($isCli) {
    echo "=== DVE Automated Classroom Repair CLI ===\n";
    echo "Started: " . date('Y-m-d H:i:s') . "\n";
    
    // Fetch all student users
    $res = $conn->query("SELECT id, student_code, fullname, student_level, affiliation, classroom_id FROM users WHERE role = 'student'");
    if (!$res) {
        echo "❌ Error reading database: " . $conn->error . "\n";
        exit(1);
    }
    
    $students = $res->fetch_all(MYSQLI_ASSOC);
    echo "Scanning " . count($students) . " students...\n";
    
    $totalFixed = 0;
    $totalSkipped = 0;
    
    foreach ($students as $stu) {
        $resolved = resolveClassroomLocal($conn, $stu['student_level'], $stu['affiliation']);
        $proposedId = $resolved ? $resolved['id'] : null;
        $currentId = $stu['classroom_id'] ? (int)$stu['classroom_id'] : null;
        
        // If mismatched or empty
        if ($proposedId !== null && $proposedId !== $currentId) {
            $update = $conn->prepare("UPDATE users SET classroom_id = ? WHERE id = ?");
            $update->bind_param("ii", $proposedId, $stu['id']);
            if ($update->execute()) {
                echo "  ✅ Fixed [{$stu['student_code']}] {$stu['fullname']} \n";
                echo "     Current Room ID: " . ($currentId ?? 'NULL') . " ➔ Proposed Room: '{$resolved['class_name']}' (ID: {$proposedId})\n";
                $totalFixed++;
            } else {
                echo "  ❌ Failed updating ID {$stu['id']}: " . $conn->error . "\n";
            }
            $update->close();
        } else {
            $totalSkipped++;
        }
    }
    
    echo "\n=== CLI SUMMARY ===\n";
    echo "Total Scanned: " . count($students) . " students\n";
    echo "Auto-Repaired: {$totalFixed} students\n";
    echo "Unchanged    : {$totalSkipped} students\n";
    exit(0);
}

// ─────────────────────────────────────────────────────────────────────────────
// 3. AJAX REQUEST HANDLERS (WEB)
// ─────────────────────────────────────────────────────────────────────────────
if (isset($_GET['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    // ACTION: SCAN DATA
    if ($_GET['action'] === 'scan') {
        $res = $conn->query("
            SELECT u.id, u.student_code, u.fullname, u.student_level, u.affiliation, u.classroom_id, c.class_name as current_class_name
            FROM users u
            LEFT JOIN classrooms c ON u.classroom_id = c.id
            WHERE u.role = 'student'
            ORDER BY u.student_code ASC
        ");
        
        $students = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        $mismatches = [];
        $total_scanned = count($students);
        
        foreach ($students as $stu) {
            $resolved = resolveClassroomLocal($conn, $stu['student_level'], $stu['affiliation']);
            $proposedId = $resolved ? $resolved['id'] : null;
            $proposedName = $resolved ? $resolved['class_name'] : null;
            $currentId = $stu['classroom_id'] ? (int)$stu['classroom_id'] : null;
            
            // Check mismatch condition: 
            // 1. Current classroom is empty/null
            // 2. Or, Current classroom ID does not match proposed ID resolved by the algorithm
            $isMismatch = ($currentId === null || $currentId === 0 || ($proposedId !== null && $proposedId !== $currentId));
            
            if ($isMismatch) {
                $mismatches[] = [
                    'id' => $stu['id'],
                    'student_code' => $stu['student_code'],
                    'fullname' => $stu['fullname'],
                    'student_level' => $stu['student_level'] ?: '(ไม่ได้ระบุ)',
                    'affiliation' => $stu['affiliation'] ?: '(ไม่ได้ระบุ)',
                    'current_classroom_id' => $currentId,
                    'current_classroom_name' => $stu['current_class_name'] ?: '❌ ไม่มีห้องเรียน',
                    'proposed_classroom_id' => $proposedId,
                    'proposed_classroom_name' => $proposedName ?: '⚠️ ไม่พบห้องเรียนที่เหมาะสม',
                    'type' => ($currentId === null || $currentId === 0) ? 'orphaned' : 'mismatch'
                ];
            }
        }
        
        echo json_encode([
            'success' => true,
            'total_scanned' => $total_scanned,
            'mismatches_count' => count($mismatches),
            'data' => $mismatches
        ]);
        exit;
    }
    
    // ACTION: BATCH REPAIR
    if ($_GET['action'] === 'repair' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $repairs = $_POST['repairs'] ?? []; // Array of arrays: [['student_id' => X, 'classroom_id' => Y]]
        
        if (empty($repairs)) {
            echo json_encode(['success' => false, 'message' => 'ไม่มีรายชื่อที่เลือกส่งมาเพื่อปรับปรุงข้อมูล']);
            exit;
        }
        
        $successCount = 0;
        $failedCount = 0;
        
        $stmt = $conn->prepare("UPDATE users SET classroom_id = ? WHERE id = ? AND role = 'student'");
        
        foreach ($repairs as $item) {
            $studentId = (int)($item['student_id'] ?? 0);
            $classroomId = $item['classroom_id'] !== '' ? (int)$item['classroom_id'] : null;
            
            if ($studentId <= 0) continue;
            
            $stmt->bind_param("ii", $classroomId, $studentId);
            if ($stmt->execute()) {
                $successCount++;
            } else {
                $failedCount++;
            }
        }
        $stmt->close();
        
        echo json_encode([
            'success' => true,
            'message' => "ปรับปรุงสำเร็จ {$successCount} รายการ" . ($failedCount > 0 ? ", ล้มเหลว {$failedCount} รายการ" : ""),
            'success_count' => $successCount,
            'failed_count' => $failedCount
        ]);
        exit;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 4. MAIN WEB UI RENDER
// ─────────────────────────────────────────────────────────────────────────────
$allClassrooms = getAllClassrooms($conn);
include __DIR__ . '/../includes/header.php';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/admin-premium.css">

<style>
:root {
    --primary: #4f46e5;
    --primary-light: #818cf8;
    --success: #10b981;
    --warning: #f59e0b;
    --danger: #ef4444;
    --glass-bg: rgba(255, 255, 255, 0.95);
    --glass-border: rgba(255, 255, 255, 0.2);
}

body {
    background: #f1f5f9;
}

.bg-blob {
    position: fixed;
    width: 600px;
    height: 600px;
    background: radial-gradient(circle, rgba(79, 70, 229, 0.08) 0%, rgba(124, 58, 237, 0.04) 100%);
    border-radius: 50%;
    filter: blur(100px);
    z-index: -1;
}
.blob-1 { top: -200px; right: -200px; }
.blob-2 { bottom: -200px; left: -200px; }

.glass-card {
    background: var(--glass-bg);
    backdrop-filter: blur(10px);
    border: 1px solid var(--glass-border);
    border-radius: 1.5rem;
    box-shadow: 0 10px 25px -5px rgba(79, 70, 229, 0.05);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.repair-hero {
    background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
    border-radius: 1.5rem;
    padding: 2.5rem 2rem;
    color: white;
    margin-bottom: 2rem;
    box-shadow: 0 10px 30px rgba(79, 70, 229, 0.25);
    position: relative;
    overflow: hidden;
}
.repair-hero::after {
    content: '';
    position: absolute;
    top: -50%;
    right: -20%;
    width: 300px;
    height: 300px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 50%;
    filter: blur(50px);
}

.stat-counter {
    background: white;
    border-radius: 1.25rem;
    padding: 1.5rem;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    border-left: 5px solid #ccc;
    transition: transform 0.2s ease;
}
.stat-counter:hover {
    transform: translateY(-3px);
}
.stat-counter.total { border-left-color: var(--primary); }
.stat-counter.mismatch { border-left-color: var(--danger); }
.stat-counter.resolved { border-left-color: var(--success); }

.stat-counter .num {
    font-size: 2.5rem;
    font-weight: 800;
    line-height: 1;
}

.table-wrap {
    background: white;
    border-radius: 1rem;
    box-shadow: 0 4px 15px rgba(0,0,0,.04);
    overflow: hidden;
}
.table-wrap thead th {
    background: #1e1b4b;
    color: white;
    font-size: .8rem;
    text-transform: uppercase;
    letter-spacing: .05em;
    padding: 1rem;
    border: none;
    vertical-align: middle;
}
.table-wrap tbody td {
    padding: 0.9rem 1rem;
    vertical-align: middle;
    font-size: .9rem;
}
.table-wrap tbody tr {
    transition: background 0.2s;
}
.table-wrap tbody tr:hover {
    background: #f8fafc;
}

.badge-tag {
    font-size: 0.8rem;
    font-weight: 600;
    padding: 0.3rem 0.6rem;
    border-radius: 0.5rem;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}
.badge-danger { background: #fee2e2; color: #991b1b; }
.badge-success { background: #d1fae5; color: #065f46; }
.badge-warning { background: #fef3c7; color: #92400e; }
.badge-secondary { background: #e2e8f0; color: #475569; }

.select-classroom-picker {
    font-size: 0.85rem;
    padding: 0.35rem 0.50rem;
    border-radius: 0.5rem;
    border: 1px solid #cbd5e1;
    background-color: #f8fafc;
    width: 100%;
    max-width: 250px;
    font-weight: 500;
    transition: border-color 0.2s, background-color 0.2s;
}
.select-classroom-picker:focus {
    border-color: var(--primary);
    background-color: #fff;
    outline: none;
}

.floating-action-bar {
    position: fixed;
    bottom: 2rem;
    left: 50%;
    transform: translateX(-50%) translateY(100px);
    background: rgba(15, 23, 42, 0.9);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 1.25rem;
    padding: 1rem 2rem;
    display: flex;
    align-items: center;
    gap: 1.5rem;
    box-shadow: 0 10px 25px rgba(0,0,0,0.3);
    z-index: 1000;
    transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1);
    color: white;
}
.floating-action-bar.show {
    transform: translateX(-50%) translateY(0);
}

.btn-premium {
    background: linear-gradient(135deg, var(--primary) 0%, #7c3aed 100%);
    color: white !important;
    border: none;
    border-radius: 0.75rem;
    padding: 0.6rem 1.5rem;
    font-weight: 600;
    transition: all 0.3s;
    box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
}
.btn-premium:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 18px rgba(79, 70, 229, 0.4);
}
.btn-premium:active {
    transform: translateY(0);
}

.btn-secondary-premium {
    background: white;
    color: #4f46e5 !important;
    border: 2px solid #e0e7ff;
    border-radius: 0.75rem;
    padding: 0.5rem 1.25rem;
    font-weight: 600;
    transition: all 0.3s;
}
.btn-secondary-premium:hover {
    background: #f5f3ff;
    border-color: #c7d2fe;
    transform: translateY(-1px);
}

.filter-tab {
    cursor: pointer;
    font-weight: 600;
    font-size: 0.9rem;
    color: #64748b;
    padding: 0.5rem 1.25rem;
    border-radius: 0.75rem;
    transition: all 0.2s;
}
.filter-tab.active {
    background: var(--primary);
    color: white;
}
</style>

<div class="bg-blob blob-1"></div>
<div class="bg-blob blob-2"></div>

<div class="container-fluid py-4">
    <!-- Hero Block -->
    <div class="repair-hero">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="fw-bold mb-2">
                    <i class="fa-solid fa-wand-magic-sparkles me-2"></i> 
                    เครื่องมือซ่อมแซมการจับคู่ห้องเรียนอัจฉริยะ
                </h1>
                <p class="lead mb-0 text-white-50">
                    วิเคราะห์ ตรวจสอบ และแก้ไขความคลาดเคลื่อนของห้องเรียนนักศึกษาที่นำเข้าจาก CSV โดยเปรียบเทียบจาก ระดับชั้น และ แผนกวิชา/ห้องจริง
                </p>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                <button id="btnStartScan" class="btn btn-light btn-lg fw-bold text-primary shadow-sm" style="border-radius: 1rem;">
                    <i class="fa-solid fa-arrows-rotate fa-spin-hover me-2"></i> สแกนตรวจสอบทันที
                </button>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="stat-counter total">
                <div class="text-muted small fw-bold text-uppercase">นักเรียนทั้งหมดในระบบ</div>
                <div class="d-flex justify-content-between align-items-center mt-2">
                    <div class="num text-primary" id="statScannedCount">-</div>
                    <div class="icon fs-1 text-primary-light opacity-50"><i class="fa-solid fa-users"></i></div>
                </div>
                <div class="small text-muted mt-2">นักศึกษาทุกคนที่มีบทบาทเป็น Student</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-counter mismatch">
                <div class="text-muted small fw-bold text-uppercase">พบห้องเรียนไม่ตรง/ไม่มีห้อง</div>
                <div class="d-flex justify-content-between align-items-center mt-2">
                    <div class="num text-danger" id="statMismatchCount">-</div>
                    <div class="icon fs-1 text-danger opacity-50"><i class="fa-solid fa-triangle-exclamation"></i></div>
                </div>
                <div class="small text-muted mt-2">จำเป็นต้องกดปรับปรุงข้อมูลห้องเรียนให้ตรง</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-counter resolved">
                <div class="text-muted small fw-bold text-uppercase">สถานะความพร้อมข้อมูล</div>
                <div class="d-flex justify-content-between align-items-center mt-2">
                    <div class="num text-success" id="statHealthPct">-</div>
                    <div class="icon fs-1 text-success opacity-50"><i class="fa-solid fa-heart-pulse"></i></div>
                </div>
                <div class="small text-muted mt-2">เปอร์เซ็นต์นักเรียนที่ข้อมูลห้องเรียนถูกต้องดีแล้ว</div>
            </div>
        </div>
    </div>

    <!-- Workspace Glass Card -->
    <div class="glass-card p-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div class="d-flex gap-2">
                <div class="filter-tab active" data-filter="all">ทั้งหมด (<span class="cnt-all">0</span>)</div>
                <div class="filter-tab" data-filter="mismatch">ห้องเรียนไม่ถูกต้อง (<span class="cnt-mismatch">0</span>)</div>
                <div class="filter-tab" data-filter="orphaned">ไม่มีห้องเรียน (<span class="cnt-orphaned">0</span>)</div>
            </div>
            
            <div class="d-flex gap-2 align-items-center">
                <div class="input-group" style="width: 280px;">
                    <span class="input-group-text bg-white border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                    <input type="text" id="searchBox" class="form-control border-start-0" placeholder="ค้นหารหัส หรือ ชื่อนศ...">
                </div>
            </div>
        </div>

        <!-- Scan State placeholder -->
        <div id="scanPlaceholder" class="text-center py-5">
            <div class="opacity-75 mb-3">
                <i class="fa-solid fa-radar fa-beat-fade text-primary" style="font-size: 4rem;"></i>
            </div>
            <h4 class="fw-bold">พร้อมสำหรับการสแกนโครงสร้างข้อมูลห้องเรียน</h4>
            <p class="text-muted">ระบบจะวิ่งตรวจสอบนักศึกษาทีละคน เพื่อค้นหาว่าห้องเรียนใดคลาดเคลื่อนหรือครูมองไม่เห็น</p>
            <button id="btnStartScanPlaceholder" class="btn btn-premium mt-3">
                <i class="fa-solid fa-magnifying-glass-chart me-1"></i> เริ่มตรวจสอบข้อมูล
            </button>
        </div>

        <!-- Loading State -->
        <div id="loadingState" class="text-center py-5 d-none">
            <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <h4 class="fw-bold mt-4">กำลังดึงข้อมูลและจำลองการจับคู่ห้องเรียน...</h4>
            <p class="text-muted">กรุณารอสักครู่ ขั้นตอนนี้อาจใช้เวลาประมาณ 5-10 วินาที</p>
        </div>

        <!-- Results Table -->
        <div id="resultsContent" class="d-none">
            <div class="table-responsive table-wrap mb-3">
                <table class="table mb-0" id="mismatchTable">
                    <thead>
                        <tr>
                            <th width="40" class="text-center">
                                <div class="form-check d-flex justify-content-center align-items-center">
                                    <input class="form-check-input" type="checkbox" id="checkAll">
                                </div>
                            </th>
                            <th width="60" class="text-center">ลำดับ</th>
                            <th width="130">รหัสนักศึกษา</th>
                            <th width="180">ชื่อ-นามสกุล</th>
                            <th width="200">ระดับ & สังกัด/ห้องฝึกเดิม</th>
                            <th width="200">ห้องเรียนปัจจุบัน</th>
                            <th width="250">ข้อเสนอแนะ / ปรับเปลี่ยนเป็น</th>
                            <th width="120" class="text-center">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody id="mismatchList">
                        <!-- Filled by JavaScript -->
                    </tbody>
                </table>
            </div>

            <!-- If Clean State -->
            <div id="cleanState" class="text-center py-5 d-none">
                <div class="text-success mb-3">
                    <i class="fa-solid fa-circle-check" style="font-size: 5rem;"></i>
                </div>
                <h3 class="fw-bold text-success">ยอดเยี่ยม! ข้อมูลเป็นระเบียบเรียบร้อย</h3>
                <p class="text-muted fs-5">
                    ไม่พบข้อมูลนักเรียนที่มีข้อผิดพลาดในการจับคู่ระดับการศึกษาและห้องเรียนเลย คุณครูทุกคนจะสามารถเห็นรายชื่อนักเรียนของตนเองได้อย่างครบถ้วน
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Floating Action Bar for Mass Fixes -->
<div class="floating-action-bar" id="floatingBar">
    <div class="fw-bold">
        <i class="fa-solid fa-list-check text-primary me-2"></i>
        เลือกอยู่ <span id="selectedCount" class="text-primary-light">0</span> รายการ
    </div>
    <div class="d-flex gap-2">
        <button id="btnRepairSelected" class="btn btn-premium">
            <i class="fa-solid fa-hammer me-1"></i> ซ่อมแซมห้องเรียนรายการที่เลือก
        </button>
        <button id="btnCancelSelected" class="btn btn-outline-light btn-sm text-white-50 border-0">ยกเลิก</button>
    </div>
</div>

<!-- Pre-rendered JSON classroom mapping map for manual selector overrides -->
<script>
const CLASSROOMS_MAP = <?php echo json_encode($allClassrooms); ?>;
</script>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
let scanData = [];

$(document).ready(function() {
    $('#btnStartScan, #btnStartScanPlaceholder, #btnStartScan').on('click', runScan);
    
    // Select all checkboxes
    $('#checkAll').on('change', function() {
        const isChecked = $(this).is(':checked');
        $('.student-checkbox:visible').prop('checked', isChecked);
        updateFloatingBar();
    });

    // Checkbox toggling inside the table
    $(document).on('change', '.student-checkbox', function() {
        updateFloatingBar();
    });

    // Tab Filter switches
    $('.filter-tab').on('click', function() {
        $('.filter-tab').removeClass('active');
        $(this).addClass('active');
        applyFilter();
    });

    // Live search keyup
    $('#searchBox').on('keyup', function() {
        applyFilter();
    });

    // Cancel selection
    $('#btnCancelSelected').on('click', function() {
        $('.student-checkbox').prop('checked', false);
        $('#checkAll').prop('checked', false);
        updateFloatingBar();
    });

    // Repair selected
    $('#btnRepairSelected').on('click', function() {
        repairSelected();
    });

    // Individual repair action
    $(document).on('click', '.btn-fix-single', function() {
        const sId = $(this).data('id');
        const row = $(this).closest('tr');
        const cPicker = row.find('.select-classroom-picker');
        const selectVal = cPicker.val();
        
        repairItems([ { student_id: sId, classroom_id: selectVal } ]);
    });
});

function runScan() {
    $('#scanPlaceholder').addClass('d-none');
    $('#loadingState').removeClass('d-none');
    $('#resultsContent').addClass('d-none');
    $('#cleanState').addClass('d-none');

    $.ajax({
        url: 'repair_classroom_mapping.php?action=scan',
        method: 'GET',
        dataType: 'json',
        success: function(res) {
            $('#loadingState').addClass('d-none');
            
            if (res.success) {
                scanData = res.data;
                
                // Calculate Stats
                const totalScanned = res.total_scanned;
                const mismatchCount = res.mismatches_count;
                const healthyCount = totalScanned - mismatchCount;
                const healthPct = totalScanned > 0 ? Math.round((healthyCount / totalScanned) * 100) : 100;
                
                $('#statScannedCount').text(totalScanned.toLocaleString());
                $('#statMismatchCount').text(mismatchCount.toLocaleString());
                $('#statHealthPct').text(healthPct + '%');
                
                // Set tab counter totals
                $('.cnt-all').text(mismatchCount);
                $('.cnt-mismatch').text(scanData.filter(d => d.type === 'mismatch').length);
                $('.cnt-orphaned').text(scanData.filter(d => d.type === 'orphaned').length);

                $('#resultsContent').removeClass('d-none');
                
                if (mismatchCount === 0) {
                    $('#cleanState').removeClass('d-none');
                    $('#mismatchTable').parent().addClass('d-none');
                } else {
                    $('#mismatchTable').parent().removeClass('d-none');
                    renderTable(scanData);
                }
                
                updateFloatingBar();
            } else {
                Swal.fire('ข้อผิดพลาด', 'ไม่สามารถสแกนข้อมูลระบบได้: ' + res.message, 'error');
                $('#scanPlaceholder').removeClass('d-none');
            }
        },
        error: function() {
            $('#loadingState').addClass('d-none');
            Swal.fire('ข้อผิดพลาด', 'เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์', 'error');
            $('#scanPlaceholder').removeClass('d-none');
        }
    });
}

function renderTable(data) {
    const list = $('#mismatchList');
    list.empty();
    
    data.forEach((item, index) => {
        // Construct options for manual matching dropdown
        let pickerOptions = `<option value="">❌ ยกเลิกจับห้องเรียน (ไม่จับคู่)</option>`;
        
        CLASSROOMS_MAP.forEach(cls => {
            const isSelected = (item.proposed_classroom_id == cls.id) ? 'selected' : '';
            pickerOptions += `<option value="${cls.id}" ${isSelected}>${cls.class_name}</option>`;
        });

        const levelBadge = (item.student_level.includes('ปวส')) 
            ? `<span class="badge-tag badge-warning"><i class="fa-solid fa-graduation-cap"></i> ${item.student_level}</span>`
            : `<span class="badge-tag badge-secondary"><i class="fa-solid fa-graduation-cap"></i> ${item.student_level}</span>`;

        const curRoomBadge = (item.current_classroom_id)
            ? `<span class="badge-tag badge-danger"><i class="fa-solid fa-rectangle-xmark"></i> ${item.current_classroom_name}</span>`
            : `<span class="badge-tag badge-secondary"><i class="fa-solid fa-triangle-exclamation"></i> ${item.current_classroom_name}</span>`;

        const propRoomBadge = (item.proposed_classroom_id)
            ? `<span class="badge-tag badge-success"><i class="fa-solid fa-circle-check"></i> ${item.proposed_classroom_name}</span>`
            : `<span class="badge-tag badge-danger"><i class="fa-solid fa-circle-xmark"></i> ${item.proposed_classroom_name}</span>`;

        const row = `
            <tr id="student-row-${item.id}" class="filter-row" data-type="${item.type}" data-code="${item.student_code}" data-name="${item.fullname}">
                <td class="text-center">
                    <div class="form-check d-flex justify-content-center align-items-center">
                        <input class="form-check-input student-checkbox" type="checkbox" value="${item.id}" data-proposed="${item.proposed_classroom_id || ''}">
                    </div>
                </td>
                <td class="text-center text-secondary fw-bold">${index + 1}</td>
                <td class="fw-bold text-primary">${item.student_code}</td>
                <td>
                    <div class="fw-bold">${item.fullname}</div>
                </td>
                <td>
                    <div class="mb-1">${levelBadge}</div>
                    <div class="small text-muted" title="สังกัดใน CSV">${item.affiliation}</div>
                </td>
                <td>${curRoomBadge}</td>
                <td>
                    <div class="mb-1">${propRoomBadge}</div>
                    <div class="mt-1 d-flex align-items-center gap-1">
                        <label class="small text-muted me-1" style="white-space:nowrap;">ปรับห้องเรียน:</label>
                        <select class="select-classroom-picker" id="picker-${item.id}">
                            ${pickerOptions}
                        </select>
                    </div>
                </td>
                <td class="text-center">
                    <button class="btn btn-sm btn-premium btn-fix-single" data-id="${item.id}">
                        <i class="fa-solid fa-wrench"></i> แก้ไข
                    </button>
                </td>
            </tr>
        `;
        list.append(row);
    });
}

function applyFilter() {
    const filter = $('.filter-tab.active').data('filter');
    const searchVal = $('#searchBox').val().toLowerCase();
    
    $('.filter-row').each(function() {
        const row = $(this);
        const type = row.data('type');
        const code = String(row.data('code')).toLowerCase();
        const name = String(row.data('name')).toLowerCase();
        
        let showByFilter = (filter === 'all') || (type === filter);
        let showBySearch = !searchVal || code.includes(searchVal) || name.includes(searchVal);
        
        if (showByFilter && showBySearch) {
            row.show();
        } else {
            row.hide();
            row.find('.student-checkbox').prop('checked', false);
        }
    });

    updateFloatingBar();
}

function updateFloatingBar() {
    const checkedBoxes = $('.student-checkbox:checked:visible');
    const count = checkedBoxes.length;
    
    $('#selectedCount').text(count);
    
    if (count > 0) {
        $('#floatingBar').addClass('show');
    } else {
        $('#floatingBar').removeClass('show');
    }
}

function repairSelected() {
    const checkedBoxes = $('.student-checkbox:checked:visible');
    const items = [];
    
    checkedBoxes.each(function() {
        const sId = $(this).val();
        const pickerVal = $(`#picker-${sId}`).val();
        items.push({
            student_id: sId,
            classroom_id: pickerVal
        });
    });
    
    if (items.length === 0) return;
    
    Swal.fire({
        title: 'ยืนยันการซ่อมแซม?',
        text: `คุณกำลังทำการย้ายนักศึกษาจำนวน ${items.length} คน เข้าสู่ห้องเรียนตามที่เลือกไว้`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#4f46e5',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'ยืนยันซ่อมแซม',
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        if (result.isConfirmed) {
            repairItems(items);
        }
    });
}

function repairItems(items) {
    Swal.fire({
        title: 'กำลังแก้ไขข้อมูลห้องเรียน...',
        html: 'โปรดรอสักครู่ ห้ามปิดหน้าต่างนี้',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    $.ajax({
        url: 'repair_classroom_mapping.php?action=repair',
        method: 'POST',
        data: { repairs: items },
        dataType: 'json',
        success: function(res) {
            if (res.success) {
                Swal.fire({
                    title: 'สำเร็จ!',
                    text: res.message,
                    icon: 'success',
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    // Refresh scanner to show updated list
                    runScan();
                });
            } else {
                Swal.fire('ล้มเหลว', 'เกิดข้อผิดพลาดในการแก้ไข: ' + res.message, 'error');
            }
        },
        error: function() {
            Swal.fire('ข้อผิดพลาด', 'เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์', 'error');
        }
    });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
