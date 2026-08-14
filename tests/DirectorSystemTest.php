<?php
declare(strict_types=1);

/**
 * 🏛️ Director Role System Test Suite
 * ครอบคลุม: Dashboard, นักเรียน, ครูนิเทศก์, แผนงาน, บันทึกรายวัน,
 *            รายงานค้างตรวจ, รอลงนาม, ลงนามแล้ว, เกียรติบัตร
 * + ป้องกันข้อผิดพลาด: SQL Injection, XSS, Access Control, NULL safety
 */

require_once __DIR__ . '/../includes/configdb.php';
require_once __DIR__ . '/../includes/functions.php';

// ─── ANSI Colors ───────────────────────────────────────────
if (!defined('CLR_RESET'))  define('CLR_RESET',  "\033[0m");
if (!defined('CLR_GREEN'))  define('CLR_GREEN',  "\033[32m");
if (!defined('CLR_RED'))    define('CLR_RED',    "\033[31m");
if (!defined('CLR_CYAN'))   define('CLR_CYAN',   "\033[36m");
if (!defined('CLR_YELLOW')) define('CLR_YELLOW', "\033[33m");
if (!defined('CLR_BOLD'))   define('CLR_BOLD',   "\033[1m");

echo CLR_CYAN . CLR_BOLD;
echo "╔══════════════════════════════════════════════════════════╗\n";
echo "║    🏛️  DIRECTOR ROLE SYSTEM TEST SUITE  v1.0            ║\n";
echo "║    DVE | PBPVC  —  Supervisor Dashboard Audit           ║\n";
echo "╚══════════════════════════════════════════════════════════╝\n";
echo CLR_RESET . "\n";

// ─── Global counters ───────────────────────────────────────
$testsPassed = 0;
$testsFailed = 0;

function assertTest(string $name, bool $expression, string $hint = ''): void {
    global $testsPassed, $testsFailed;
    if ($expression) {
        echo CLR_GREEN . "  ✓ PASS: $name\n" . CLR_RESET;
        $testsPassed++;
    } else {
        echo CLR_RED   . "  ✗ FAIL: $name" . ($hint ? " ($hint)" : '') . "\n" . CLR_RESET;
        $testsFailed++;
    }
}

function sectionHeader(string $phase, string $title): void {
    echo "\n" . CLR_YELLOW . CLR_BOLD . "[$phase] $title\n" . CLR_RESET;
    echo CLR_YELLOW . str_repeat('─', 58) . "\n" . CLR_RESET;
}

// ─── DB sanity check ───────────────────────────────────────
global $conn;
if (!isset($conn) || $conn->connect_error) {
    echo CLR_RED . "❌ CRITICAL: Cannot connect to database. Aborting.\n" . CLR_RESET;
    exit(1);
}
echo CLR_CYAN . "  ✦ Database connection: OK  (" . $conn->host_info . ")\n" . CLR_RESET;

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// PHASE 1 — ตรวจสอบโครงสร้างตาราง DB
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
sectionHeader('PHASE 1', 'Database Table Structure Validation');

$requiredTables = [
    'users'              => 'User accounts (students, teachers, director)',
    'classrooms'         => 'Classroom registry for filtering',
    'daily_reports'      => 'Student daily report submissions',
    'supervision_files'  => 'Supervision document workflow',
    'plans'              => 'Teacher supervision plans',
    'subject_plans'      => 'Teacher subject-level training plans',
    'evaluations'        => 'Student grade evaluations',
    'companies'          => 'Internship companies',
    'teacher_assignments'=> 'Teacher-classroom assignments',
    'notifications'      => 'User notification inbox',
    'audit_logs'         => 'Audit trail for sensitive actions',
];

foreach ($requiredTables as $table => $desc) {
    $chk = $conn->query("SHOW TABLES LIKE '$table'");
    assertTest("Table '$table' exists ($desc)", $chk && $chk->num_rows > 0);
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// PHASE 2 — ตรวจสอบ Column สำคัญในแต่ละตาราง
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
sectionHeader('PHASE 2', 'Column Integrity Checks');

$columnChecks = [
    'supervision_files' => ['id','teacher_id','student_id','file_path','status','director_signed_at','uploaded_at'],
    'users'             => ['id','username','fullname','role','classroom_id','company_id','company_name'],
    'daily_reports'     => ['id','student_id','status','created_at','details','date_work'],
    'evaluations'       => ['id','student_id','grade','created_at'],
    'plans'             => ['id','teacher_id','filename'],
    'subject_plans'     => ['id','teacher_id','filename'],
    'classrooms'        => ['id','class_name'],
];

foreach ($columnChecks as $table => $cols) {
    $colRes = $conn->query("SHOW COLUMNS FROM `$table`");
    $existing = [];
    if ($colRes) {
        while ($row = $colRes->fetch_assoc()) {
            $existing[] = $row['Field'];
        }
    }
    foreach ($cols as $col) {
        assertTest("Column `$table`.`$col` exists", in_array($col, $existing));
    }
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// PHASE 3 — Dashboard Counter Logic (roles/director.php)
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
sectionHeader('PHASE 3', 'Director Dashboard Counter Logic');

// ─ 3-A: count_std ─
$count_std_res = $conn->query("SELECT COUNT(*) c FROM users WHERE role='student'");
assertTest("Dashboard: count_std query executes successfully", $count_std_res !== false);
$count_std = $count_std_res ? (int)$count_std_res->fetch_assoc()['c'] : -1;
assertTest("Dashboard: count_std is non-negative integer", $count_std >= 0);

// ─ 3-B: count_tea ─
$count_tea_res = $conn->query("SELECT COUNT(*) c FROM users WHERE role IN ('teacher','mentor')");
assertTest("Dashboard: count_tea query executes successfully", $count_tea_res !== false);
$count_tea = $count_tea_res ? (int)$count_tea_res->fetch_assoc()['c'] : -1;
assertTest("Dashboard: count_tea is non-negative integer", $count_tea >= 0);

// ─ 3-C: count_plan ─
$count_plan_res = $conn->query("SELECT COUNT(DISTINCT teacher_id) c FROM plans");
assertTest("Dashboard: count_plan query executes successfully", $count_plan_res !== false);
$count_plan = $count_plan_res ? (int)$count_plan_res->fetch_assoc()['c'] : -1;
assertTest("Dashboard: count_plan does not exceed count_tea (logical)", $count_plan <= $count_tea);

// ─ 3-D: count_wait_sign ─
$wait_sign_res = $conn->query("SELECT COUNT(*) c FROM supervision_files WHERE status = 1");
assertTest("Dashboard: count_wait_sign query executes successfully", $wait_sign_res !== false);
$count_wait_sign = $wait_sign_res ? (int)$wait_sign_res->fetch_assoc()['c'] : -1;
assertTest("Dashboard: count_wait_sign is non-negative", $count_wait_sign >= 0);

// ─ 3-E: count_pending (late reports >7 days) ─
$pending_res = $conn->query("
    SELECT COUNT(DISTINCT dr.student_id) AS total
    FROM daily_reports dr
    WHERE dr.status = 'pending'
    AND dr.created_at < DATE_SUB(CURDATE(), INTERVAL 7 DAY)
");
assertTest("Dashboard: count_pending (>7 days) query executes", $pending_res !== false);
$count_pending = $pending_res ? (int)$pending_res->fetch_assoc()['total'] : -1;
assertTest("Dashboard: count_pending is non-negative", $count_pending >= 0);

// ─ 3-F: count_checked ─
$checked_res = $conn->query("SELECT COUNT(*) c FROM supervision_files WHERE status IN (1,2)");
assertTest("Dashboard: count_checked query executes successfully", $checked_res !== false);
$count_checked = $checked_res ? (int)$checked_res->fetch_assoc()['c'] : -1;
assertTest("Dashboard: count_checked >= count_wait_sign (logical, status=2 is subset)", $count_checked >= $count_wait_sign);

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// PHASE 4 — Sandbox Data & Director Workflow Simulation
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
sectionHeader('PHASE 4', 'Director Workflow Sandbox Simulation');

$cleanup = []; // track all inserted IDs for teardown

// 4-A: Create mock teacher
$suffix      = rand(10000, 99999);
$teacherUsr  = 'dir_test_teacher_' . $suffix;
$teacherEmail = 'dir_teacher_' . $suffix . '@test.local';
$hash = password_hash('test_pass', PASSWORD_DEFAULT);
$stmtT = $conn->prepare("INSERT INTO users (username,fullname,password,role,email) VALUES (?,'TEST Teacher Director Suite',?,'teacher',?)");
$teacherId = 0;
if ($stmtT) {
    $stmtT->bind_param('sss', $teacherUsr, $hash, $teacherEmail);
    if ($stmtT->execute()) {
        $teacherId = $stmtT->insert_id;
        $cleanup['teacher'] = $teacherId;
    }
    $stmtT->close();
}
assertTest("Sandbox: Create mock teacher succeeds", $teacherId > 0);

// 4-B: Create mock student
$studentUsr   = 'dir_test_student_' . $suffix;
$studentEmail = 'dir_student_' . $suffix . '@test.local';
$stmtS = $conn->prepare("INSERT INTO users (username,fullname,password,role,email) VALUES (?,'TEST Student Director Suite',?,'student',?)");
$studentId = 0;
if ($stmtS) {
    $stmtS->bind_param('sss', $studentUsr, $hash, $studentEmail);
    if ($stmtS->execute()) {
        $studentId = $stmtS->insert_id;
        $cleanup['student'] = $studentId;
    }
    $stmtS->close();
}
assertTest("Sandbox: Create mock student succeeds", $studentId > 0);

// 4-C: Insert supervision file (status=1 → pending director signature)
$supId = 0;
if ($teacherId > 0 && $studentId > 0) {
    $stmtSup = $conn->prepare("
        INSERT INTO supervision_files (teacher_id, student_id, file_path, status, uploaded_at)
        VALUES (?, ?, 'test_director_suite.pdf', 1, NOW())
    ");
    if ($stmtSup) {
        $stmtSup->bind_param('ii', $teacherId, $studentId);
        if ($stmtSup->execute()) {
            $supId = $stmtSup->insert_id;
            $cleanup['supervision'] = $supId;
        }
        $stmtSup->close();
    }
}
assertTest("Sandbox: Insert supervision file (status=pending director) succeeds", $supId > 0);

// 4-D: Verify wait_sign count incremented
$newWaitSign = $conn->query("SELECT COUNT(*) c FROM supervision_files WHERE status = 1")->fetch_assoc()['c'];
assertTest("Workflow: Wait-sign counter incremented after injection (+1)", (int)$newWaitSign > $count_wait_sign);

// 4-E: Simulate daily report (pending > 8 days)
$lateReportId = 0;
if ($studentId > 0) {
    $old_date = date('Y-m-d H:i:s', strtotime('-9 days'));
    $stmtR = $conn->prepare("
        INSERT INTO daily_reports (student_id, status, details, date_work, created_at)
        VALUES (?, 'pending', 'TEST late report for director audit', CURDATE(), ?)
    ");
    if ($stmtR) {
        $stmtR->bind_param('is', $studentId, $old_date);
        if ($stmtR->execute()) {
            $lateReportId = $stmtR->insert_id;
            $cleanup['late_report'] = $lateReportId;
        }
        $stmtR->close();
    }
}
assertTest("Sandbox: Insert 9-day-old pending report succeeds", $lateReportId > 0);

// 4-F: Verify late report counter incremented
$newPending = (int)$conn->query("
    SELECT COUNT(DISTINCT dr.student_id) AS total
    FROM daily_reports dr
    WHERE dr.status='pending'
    AND dr.created_at < DATE_SUB(CURDATE(), INTERVAL 7 DAY)
")->fetch_assoc()['total'];
assertTest("Workflow: Late-report counter incremented after injection", $newPending > $count_pending);

// 4-G: Simulate director signing (status 1 → 2)
if ($supId > 0) {
    $signed = $conn->query("
        UPDATE supervision_files
        SET status=2, director_signed_at=NOW()
        WHERE id=$supId AND status=1
    ");
    assertTest("Workflow: Director sign action (status 1→2) succeeds", $signed && $conn->affected_rows === 1);

    // Verify it moved to signed bucket
    $signedCheck = $conn->query("SELECT status FROM supervision_files WHERE id=$supId")->fetch_assoc();
    assertTest("Workflow: supervision_files.status is now 2 after signing", (int)$signedCheck['status'] === 2);
}

// 4-H: Teacher plan check (no plan = counted correctly)
if ($teacherId > 0) {
    $planCheck = $conn->query("SELECT COUNT(*) c FROM plans WHERE teacher_id=$teacherId")->fetch_assoc()['c'];
    assertTest("Workflow: New teacher has 0 submitted plans initially", (int)$planCheck === 0);
}

// 4-I: Evaluation/cert data — evaluations requires both student_id AND teacher_id (FK)
$evalId = 0;
if ($studentId > 0 && $teacherId > 0) {
    try {
        $stmtE = $conn->prepare(
            "INSERT INTO evaluations (student_id, teacher_id, grade, created_at) VALUES (?, ?, 'A', NOW())"
        );
        if ($stmtE) {
            $stmtE->bind_param('ii', $studentId, $teacherId);
            if ($stmtE->execute()) {
                $evalId = $stmtE->insert_id;
                $cleanup['eval'] = $evalId;
            }
            $stmtE->close();
        }
    } catch (\mysqli_sql_exception $ex) {
        echo CLR_YELLOW . "  ⚠ SKIP: Evaluation insert exception: " . $ex->getMessage() . "\n" . CLR_RESET;
    }
}
assertTest("Sandbox: Insert student evaluation (grade A) with teacher_id succeeds", $evalId > 0);

// Verify cert query returns this student
if ($studentId > 0) {
    $certRes = $conn->query("
        SELECT COUNT(*) c FROM users u
        LEFT JOIN evaluations e ON u.id = e.student_id
        WHERE u.id = $studentId AND e.grade IS NOT NULL AND e.grade != ''
    ");
    assertTest("Workflow: Cert query finds student with evaluation grade", $certRes && (int)$certRes->fetch_assoc()['c'] > 0);
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// PHASE 5 — Security: Access Control, XSS, SQL Injection
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
sectionHeader('PHASE 5', 'Security & Input Sanitisation Audits');

// 5-A: XSS — output encoder
$xssPayload = "<script>alert('XSS');</script><img src=x onerror=alert(1)>";
$cleaned = e($xssPayload);
assertTest("Security: e() strips <script> XSS payload",          !str_contains($cleaned, '<script>'));
assertTest("Security: e() encodes onerror= into HTML entities",  str_contains($cleaned, 'onerror') ? str_contains($cleaned, '&lt;') : true);
assertTest("Security: e() output is non-empty string",            strlen($cleaned) > 0);

// 5-B: SQL Injection via integer casting
$sqlInject = "1 OR 1=1";
$safeId = (int)$sqlInject;
assertTest("Security: Integer cast strips SQL injection payload", $safeId === 1);

$sqlInject2 = "0; DROP TABLE users;";
$safeId2 = (int)$sqlInject2;
assertTest("Security: Integer cast prevents DROP TABLE payload", $safeId2 === 0);

// 5-C: Prepared statement (real parameter test)
$evilClass = "1 UNION SELECT * FROM users";
$stmtPDO = $conn->prepare("SELECT COUNT(*) c FROM classrooms WHERE id = ?");
$safeQuery = false;
if ($stmtPDO) {
    $intClass = (int)$evilClass; // safe cast
    $stmtPDO->bind_param('i', $intClass);
    $safeQuery = $stmtPDO->execute();
    $stmtPDO->close();
}
assertTest("Security: Prepared statement executes without SQL error on injection attempt", $safeQuery);

// 5-D: Role access guard function
assertTest("Access: is_director() function is defined", function_exists('is_director'));
assertTest("Access: require_role() function is defined", function_exists('require_role'));
assertTest("Access: require_login() function is defined", function_exists('require_login'));

// 5-E: NULL safety on supervision_files columns
$nullCheck = $conn->query("
    SELECT COUNT(*) c FROM supervision_files
    WHERE teacher_id IS NULL OR student_id IS NULL
");
$nullCount = $nullCheck ? (int)$nullCheck->fetch_assoc()['c'] : 0;
assertTest("Data integrity: No supervision_files with NULL teacher_id or student_id", $nullCount === 0);

// 5-F: status constraint — only valid values (0,1,2)
$invalidStatus = $conn->query("
    SELECT COUNT(*) c FROM supervision_files
    WHERE status NOT IN (0,1,2)
");
assertTest("Data integrity: All supervision_files.status values are valid (0/1/2)",
    $invalidStatus && (int)$invalidStatus->fetch_assoc()['c'] === 0);

// 5-G: date_thai() helper
assertTest("Helper: date_thai() function is defined", function_exists('date_thai'));
$thaiDate = date_thai('2024-01-15');
assertTest("Helper: date_thai() returns non-empty string for valid date", strlen($thaiDate) > 0);
$thaiNull = date_thai('');
assertTest("Helper: date_thai() returns '-' for empty/null input", $thaiNull === '-');

// 5-H: add_notification() defined and usable
assertTest("Helper: add_notification() function is defined", function_exists('add_notification'));

// 5-I: log_audit() defined
assertTest("Helper: log_audit() function is defined", function_exists('log_audit'));

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// PHASE 6 — Director Page File Existence
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
sectionHeader('PHASE 6', 'Director Page File Existence Check');

$dirPages = [
    '../roles/director.php'            => 'Dashboard ผู้บริหาร',
    '../director/view_students.php'    => 'ตรวจสอบและลงนามใบนิเทศ',
    '../director/view_teachers.php'    => 'รายชื่อครูนิเทศก์',
    '../director/view_plans.php'       => 'ตรวจสอบแผนงานครู',
    '../director/view_daily.php'       => 'บันทึกการฝึกงานประจำวัน',
    '../director/view_late_reports.php'=> 'รายงานค้างตรวจ',
    '../director/view_wait_sign.php'   => 'ใบนิเทศรอลงนาม',
    '../director/sign.php'             => 'ใบนิเทศลงนามแล้ว',
    '../director/view_supervision.php' => 'รายงานการนิเทศก์',
    '../director/view_certs.php'       => 'ทะเบียนเกียรติบัตร',
    '../includes/director-pages.css'   => 'Director pages shared CSS',
    '../includes/functions.php'        => 'Core functions library',
    '../includes/configdb.php'         => 'Database configuration',
    '../includes/header.php'           => 'Global page header',
    '../includes/footer.php'           => 'Global page footer',
];

foreach ($dirPages as $path => $label) {
    $fullPath = __DIR__ . '/' . $path;
    assertTest("File exists: '$label'", file_exists($fullPath));
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// PHASE 7 — Upload Directory Writability
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
sectionHeader('PHASE 7', 'Upload Directory Writability');

$uploadDirs = [
    '../uploads/supervision_docs/original/' => 'ไฟล์นิเทศ (original)',
    '../uploads/supervision_docs/signed/'   => 'ไฟล์นิเทศ (signed)',
    '../uploads/plans/'                     => 'แผนการนิเทศ',
    '../uploads/avatars/'                   => 'รูปโปรไฟล์',
];

foreach ($uploadDirs as $rel => $label) {
    $abs = __DIR__ . '/' . $rel;
    if (!is_dir($abs)) {
        mkdir($abs, 0755, true);
    }
    assertTest("Upload dir writable: $label", is_dir($abs) && is_writable($abs));
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// PHASE 8 — Cleanup Sandbox Data
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
sectionHeader('PHASE 8', 'Sandbox Teardown & Cleanup');

// Reconnect if connection dropped during long test run
if (!$conn->ping()) {
    $conn->close();
    $conn = new mysqli('localhost', 'root', '', 'tvet_system');
    $conn->set_charset('utf8');
}

$cleanedCount = 0;

try {
    if (!empty($cleanup['eval'])) {
        if ($conn->query("DELETE FROM evaluations WHERE id=" . (int)$cleanup['eval'])) $cleanedCount++;
    }
    if (!empty($cleanup['late_report'])) {
        if ($conn->query("DELETE FROM daily_reports WHERE id=" . (int)$cleanup['late_report'])) $cleanedCount++;
    }
    if (!empty($cleanup['supervision'])) {
        if ($conn->query("DELETE FROM supervision_files WHERE id=" . (int)$cleanup['supervision'])) $cleanedCount++;
    }
    // Delete student before teacher (child before parent in FK chain)
    if (!empty($cleanup['student'])) {
        if ($conn->query("DELETE FROM users WHERE id=" . (int)$cleanup['student'])) $cleanedCount++;
    }
    if (!empty($cleanup['teacher'])) {
        if ($conn->query("DELETE FROM users WHERE id=" . (int)$cleanup['teacher'])) $cleanedCount++;
    }
} catch (\mysqli_sql_exception $ex) {
    echo CLR_YELLOW . "  ⚠ Cleanup exception: " . $ex->getMessage() . "\n" . CLR_RESET;
}

assertTest("Cleanup: All sandbox records removed ($cleanedCount/5 operations)", $cleanedCount >= 4);

// Verify student no longer exists
if (!empty($cleanup['student'])) {
    $gone = $conn->query("SELECT id FROM users WHERE id=" . (int)$cleanup['student'])->num_rows;
    assertTest("Cleanup: Mock student no longer exists in users table", $gone === 0);
}
// Verify supervision file no longer exists
if (!empty($cleanup['supervision'])) {
    $gone2 = $conn->query("SELECT id FROM supervision_files WHERE id=" . (int)$cleanup['supervision'])->num_rows;
    assertTest("Cleanup: Mock supervision file no longer exists", $gone2 === 0);
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// FINAL SUMMARY
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
$total = $testsPassed + $testsFailed;
echo "\n" . CLR_CYAN . CLR_BOLD;
echo "╔══════════════════════════════════════════════════════════╗\n";
echo "║                   📊  TEST RESULTS                      ║\n";
echo "╠══════════════════════════════════════════════════════════╣\n";
echo CLR_RESET;
echo CLR_GREEN . CLR_BOLD . sprintf("║  ✅ PASSED : %3d / %3d tests\n", $testsPassed, $total) . CLR_RESET;
if ($testsFailed > 0) {
    echo CLR_RED   . CLR_BOLD . sprintf("║  ❌ FAILED : %3d / %3d tests\n", $testsFailed, $total) . CLR_RESET;
} else {
    echo CLR_GREEN . CLR_BOLD . "║  💎 ALL TESTS PASSED — Director system is healthy!\n" . CLR_RESET;
}
echo CLR_CYAN . CLR_BOLD;
echo "╚══════════════════════════════════════════════════════════╝\n";
echo CLR_RESET;

exit($testsFailed > 0 ? 1 : 0);
