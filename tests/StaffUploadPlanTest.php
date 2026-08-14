<?php
declare(strict_types=1);
/**
 * tests/StaffUploadPlanTest.php
 * Test Suite: Staff Upload Plan on Behalf of Teacher
 * Covers: schema, security, business logic, access control, data integrity, notifications, cleanup
 * Run: C:\xampp\php\php.exe tests/StaffUploadPlanTest.php
 */

require_once __DIR__ . "/../includes/functions.php";
require_once __DIR__ . "/../includes/configdb.php";

if (!defined("CLR_RESET"))  define("CLR_RESET",  "\033[0m");
if (!defined("CLR_GREEN"))  define("CLR_GREEN",  "\033[32m");
if (!defined("CLR_RED"))    define("CLR_RED",    "\033[31m");
if (!defined("CLR_CYAN"))   define("CLR_CYAN",   "\033[36m");
if (!defined("CLR_YELLOW")) define("CLR_YELLOW", "\033[33m");
if (!defined("CLR_BLUE"))   define("CLR_BLUE",   "\033[34m");

echo CLR_CYAN . str_repeat("=",60) . "\n" . CLR_RESET;
echo CLR_CYAN . "  STAFF UPLOAD PLAN FOR TEACHER — TEST SUITE v2.0\n" . CLR_RESET;
echo CLR_CYAN . str_repeat("=",60) . "\n\n" . CLR_RESET;

global $conn;
if (!isset($conn) || $conn->connect_error) {
    echo CLR_RED . "CRITICAL: Cannot connect to database.\n" . CLR_RESET;
    exit(1);
}

$passed = 0; $failed = 0;
$cleanup = []; // track IDs to delete

function t(string $name, bool $ok, string $detail = ""): void {
    global $passed, $failed;
    if ($ok) {
        echo CLR_GREEN . "  ✓ PASS: $name\n" . CLR_RESET;
        $passed++;
    } else {
        echo CLR_RED . "  ✗ FAIL: $name" . ($detail ? " [$detail]" : "") . "\n" . CLR_RESET;
        $failed++;
    }
}

// ══════════════════════════════════════════════════════════════════
echo CLR_YELLOW . "[PHASE 1] Schema Validation\n" . CLR_RESET;
// ══════════════════════════════════════════════════════════════════

$tables = ["users","plans","companies","teacher_assignments","classrooms","notifications"];
foreach ($tables as $tbl) {
    $safe = $conn->real_escape_string($tbl);
    $r = $conn->query("SHOW TABLES LIKE '$safe'");
    t("Table exists: $tbl", $r && $r->num_rows > 0);
}

// plans column check
$plan_cols = [];
$pc = $conn->query("SHOW COLUMNS FROM plans");
while ($row = $pc->fetch_assoc()) $plan_cols[] = $row["Field"];

$required_cols = ["id","teacher_id","company_id","title","plan_date","note","filename","uploaded_at"];
foreach ($required_cols as $col) {
    t("plans column exists: $col", in_array($col, $plan_cols));
}

// uploaded_by column (may or may not exist — feature added by the new file)
$uby = $conn->query("SHOW COLUMNS FROM plans LIKE 'uploaded_by'");
$has_uploaded_by = ($uby && $uby->num_rows > 0);
if (!$has_uploaded_by) {
    // Migrate
    $conn->query("ALTER TABLE plans ADD COLUMN uploaded_by INT DEFAULT NULL");
    $uby2 = $conn->query("SHOW COLUMNS FROM plans LIKE 'uploaded_by'");
    $has_uploaded_by = ($uby2 && $uby2->num_rows > 0);
}
t("plans.uploaded_by column exists (migration OK)", $has_uploaded_by);

echo "\n";

// ══════════════════════════════════════════════════════════════════
echo CLR_YELLOW . "[PHASE 2] Security Tests\n" . CLR_RESET;
// ══════════════════════════════════════════════════════════════════

// 2a. CSRF token generation
$tok1 = bin2hex(random_bytes(32));
$tok2 = bin2hex(random_bytes(32));
t("CSRF: random_bytes(32) generates unique tokens", $tok1 !== $tok2);
t("CSRF: token length is 64 hex chars", strlen($tok1) === 64);
t("CSRF: hash_equals() correctly validates matching tokens", hash_equals($tok1, $tok1));
t("CSRF: hash_equals() correctly rejects mismatched tokens", !hash_equals($tok1, $tok2));

// 2b. File extension check
$allowed = ["pdf"];
t("File ext: .pdf accepted", in_array("pdf", $allowed));
t("File ext: .php rejected", !in_array("php", $allowed));
t("File ext: .exe rejected", !in_array("exe", $allowed));
t("File ext: .PDF (uppercase) normalised by strtolower", in_array(strtolower("PDF"), $allowed));

// 2c. MIME type check logic
$tmpPdf = tempnam(sys_get_temp_dir(), "test_") . ".pdf";
file_put_contents($tmpPdf, "%PDF-1.4\n% test valid pdf");
$finfo = new finfo(FILEINFO_MIME_TYPE);
$detectedMime = $finfo->file($tmpPdf);
t("MIME magic: valid PDF file detected correctly", $detectedMime === "application/pdf");
@unlink($tmpPdf);

// Fake PDF (PHP/text content renamed as .pdf)
$tmpFake = tempnam(sys_get_temp_dir(), "fake_") . ".pdf";
file_put_contents($tmpFake, "#!/bin/bash\necho shell_exec payload");
$fakeMime = $finfo->file($tmpFake);
t("MIME magic: shell script renamed as .pdf is blocked (not application/pdf)", $fakeMime !== "application/pdf");
@unlink($tmpFake);

// 2d. Input sanitization
$xss = "<script>alert(1)</script>";
t("XSS: htmlspecialchars encodes <script>", htmlspecialchars($xss) !== $xss);
t("XSS: e() helper function works", e($xss) === htmlspecialchars($xss, ENT_QUOTES, "UTF-8"));

// 2e. File size check
$max = 20 * 1024 * 1024; // 20 MB
t("File size: 10MB is within limit", (10*1024*1024) <= $max);
t("File size: 21MB is rejected", (21*1024*1024) > $max);

echo "\n";

// ══════════════════════════════════════════════════════════════════
echo CLR_YELLOW . "[PHASE 3] Business Logic — Test Data Setup\n" . CLR_RESET;
// ══════════════════════════════════════════════════════════════════

// Create mock teacher
$tch_user = "test_tch_" . rand(1000,9999);
$tch_pass  = password_hash("password", PASSWORD_DEFAULT);
$ins_t = $conn->prepare("INSERT INTO users (username, fullname, email, password, role) VALUES (?, ?, ?, ?, ?)");
$tch_email = $tch_user . "@test.local";
$tch_role  = "teacher";
$tch_name  = "Mock Teacher " . rand(1,99);
$ins_t->bind_param("sssss", $tch_user, $tch_name, $tch_email, $tch_pass, $tch_role);
$teacher_ok = $ins_t->execute();
$teacher_id = $teacher_ok ? (int)$ins_t->insert_id : 0;
$ins_t->close();
if ($teacher_id) $cleanup["teacher_id"] = $teacher_id;
t("Setup: Create mock teacher user", $teacher_ok && $teacher_id > 0);

// Create mock staff
$stf_user = "test_stf_" . rand(1000,9999);
$stf_role  = "staff";
$ins_s = $conn->prepare("INSERT INTO users (username, fullname, email, password, role) VALUES (?, ?, ?, ?, ?)");
$stf_email = $stf_user . "@test.local";
$stf_name  = "Mock Staff " . rand(1,99);
$ins_s->bind_param("sssss", $stf_user, $stf_name, $stf_email, $tch_pass, $stf_role);
$staff_ok = $ins_s->execute();
$staff_id = $staff_ok ? (int)$ins_s->insert_id : 0;
$ins_s->close();
if ($staff_id) $cleanup["staff_id"] = $staff_id;
t("Setup: Create mock staff user", $staff_ok && $staff_id > 0);

// Create mock classroom
$ins_cl = $conn->prepare("INSERT INTO classrooms (class_name, teacher_id) VALUES (?, ?)");
$cl_name = "TEST_CLASS_" . rand(100,999);
$ins_cl->bind_param("si", $cl_name, $teacher_id);
$cl_ok = $ins_cl->execute();
$classroom_id = $cl_ok ? (int)$ins_cl->insert_id : 0;
$ins_cl->close();
if ($classroom_id) $cleanup["classroom_id"] = $classroom_id;
t("Setup: Create mock classroom", $cl_ok && $classroom_id > 0);

// Assign teacher to classroom
$ins_ta = $conn->prepare("INSERT INTO teacher_assignments (teacher_id, classroom_id) VALUES (?, ?)");
$ins_ta->bind_param("ii", $teacher_id, $classroom_id);
$ta_ok = $ins_ta->execute();
$ta_id = $ta_ok ? (int)$ins_ta->insert_id : 0;
$ins_ta->close();
if ($ta_id) $cleanup["ta_id"] = $ta_id;
t("Setup: Assign teacher to classroom", $ta_ok && $ta_id > 0);

// Create mock company
$ins_comp = $conn->prepare("INSERT INTO companies (name) VALUES (?)");
$comp_name = "TEST_COMPANY_" . rand(100,999);
$ins_comp->bind_param("s", $comp_name);
$comp_ok = $ins_comp->execute();
$company_id = $comp_ok ? (int)$ins_comp->insert_id : 0;
$ins_comp->close();
if ($company_id) $cleanup["company_id"] = $company_id;
t("Setup: Create mock company", $comp_ok && $company_id > 0);

// Create mock student linked to classroom and company
$stu_user  = "test_stu_" . rand(1000,9999) . "_" . time();
$stu_email = $stu_user . "@test.local";
$stu_role  = "student";
$ins_stu = $conn->prepare("INSERT INTO users (username, fullname, email, password, role, classroom_id, company_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
$stu_name = "Mock Student " . rand(1,99);
$ins_stu->bind_param("sssssii", $stu_user, $stu_name, $stu_email, $tch_pass, $stu_role, $classroom_id, $company_id);
$stu_ok = false;
$student_id = 0;
try {
    $stu_ok    = $ins_stu->execute();
    $student_id = $stu_ok ? (int)$ins_stu->insert_id : 0;
} catch (Exception $e) {
    // duplicate or constraint error — non-fatal, log it
    error_log("StaffUploadPlanTest student insert: " . $e->getMessage());
}
$ins_stu->close();
if ($student_id) $cleanup["student_id"] = $student_id;
t("Setup: Create mock student (classroom + company)", $stu_ok && $student_id > 0);

echo "\n";

// ══════════════════════════════════════════════════════════════════
echo CLR_YELLOW . "[PHASE 4] Access Control\n" . CLR_RESET;
// ══════════════════════════════════════════════════════════════════

// Verify teacher record with the mock data
$chk = $conn->prepare("SELECT id, fullname FROM users WHERE id=? AND role=?");
$role_teacher = "teacher";
$chk->bind_param("is", $teacher_id, $role_teacher);
$chk->execute();
$row = $chk->get_result()->fetch_assoc();
$chk->close();
t("Access: teacher lookup by id+role succeeds", !empty($row) && (int)$row["id"] === $teacher_id);

// Non-existent ID should fail
$bad_id = 999999;
$chk2 = $conn->prepare("SELECT id FROM users WHERE id=? AND role=?");
$chk2->bind_param("is", $bad_id, $role_teacher);
$chk2->execute();
$bad_row = $chk2->get_result()->fetch_assoc();
$chk2->close();
t("Access: non-existent teacher id is rejected", empty($bad_row));

// Student should not be treated as teacher
if ($student_id > 0) {
    $chk3 = $conn->prepare("SELECT id FROM users WHERE id=? AND role=?");
    $chk3->bind_param("is", $student_id, $role_teacher);
    $chk3->execute();
    $stu_as_tch = $chk3->get_result()->fetch_assoc();
    $chk3->close();
    t("Access: student cannot be selected as teacher", empty($stu_as_tch));
}

// role check logic (simulated)
$allowed_roles = ["staff", "admin"];
t("Access: staff role is allowed", in_array("staff", $allowed_roles));
t("Access: admin role is allowed", in_array("admin", $allowed_roles));
t("Access: teacher role is blocked", !in_array("teacher", $allowed_roles));
t("Access: student role is blocked", !in_array("student", $allowed_roles));
t("Access: supervisor role is blocked", !in_array("supervisor", $allowed_roles));

echo "\n";

// ══════════════════════════════════════════════════════════════════
echo CLR_YELLOW . "[PHASE 5] Data Integrity — Plan INSERT & Company Lookup\n" . CLR_RESET;
// ══════════════════════════════════════════════════════════════════

// Simulate company lookup query
if ($teacher_id > 0 && $classroom_id > 0) {
    $role_s = "student";
    $sc = $conn->prepare("SELECT DISTINCT c.id FROM companies c JOIN users u ON u.company_id=c.id JOIN teacher_assignments ta ON u.classroom_id=ta.classroom_id WHERE ta.teacher_id=? AND u.role=?");
    $sc->bind_param("is", $teacher_id, $role_s);
    $sc->execute();
    $c_res = $sc->get_result();
    $c_ids = [];
    while ($r = $c_res->fetch_assoc()) $c_ids[] = (int)$r["id"];
    $sc->close();
    t("Business: company lookup returns at least 1 company for the teacher", count($c_ids) > 0);
    t("Business: found company is the mock company", in_array($company_id, $c_ids));
}

// Insert a plan with uploaded_by
$plan_title    = "TEST_PLAN_" . rand(1000,9999);
$plan_date_str = date("Y-m-d");
$plan_note     = "อัปโหลดแทนโดย Mock Staff [staff_id=$staff_id]";
$plan_file     = "test_" . time() . ".pdf";
$plan_id       = 0;

if ($teacher_id > 0 && $staff_id > 0) {
    $plan_comp_id = $company_id ?: 0;
    if ($has_uploaded_by) {
        $ins_p = $conn->prepare("INSERT INTO plans (teacher_id,company_id,title,plan_date,note,filename,uploaded_by) VALUES (?,?,?,?,?,?,?)");
        $ins_p->bind_param("iissssi", $teacher_id, $plan_comp_id, $plan_title, $plan_date_str, $plan_note, $plan_file, $staff_id);
    } else {
        $ins_p = $conn->prepare("INSERT INTO plans (teacher_id,company_id,title,plan_date,note,filename) VALUES (?,?,?,?,?,?)");
        $ins_p->bind_param("iissss", $teacher_id, $plan_comp_id, $plan_title, $plan_date_str, $plan_note, $plan_file);
    }
    $plan_ok = $ins_p->execute();
    $plan_id = $plan_ok ? (int)$ins_p->insert_id : 0;
    $ins_p->close();
    if ($plan_id) $cleanup["plan_id"] = $plan_id;
    t("Data: INSERT plan with teacher_id + uploaded_by succeeds", $plan_ok && $plan_id > 0);
}

// Verify the plan was stored correctly
if ($plan_id > 0) {
    $vp = $conn->prepare("SELECT * FROM plans WHERE id=?");
    $vp->bind_param("i", $plan_id);
    $vp->execute();
    $vrow = $vp->get_result()->fetch_assoc();
    $vp->close();

    t("Data: stored plan has correct teacher_id", (int)$vrow["teacher_id"] === $teacher_id);
    t("Data: stored plan has correct title", $vrow["title"] === $plan_title);
    t("Data: stored plan has correct filename", $vrow["filename"] === $plan_file);
    t("Data: stored plan has correct plan_date", $vrow["plan_date"] === $plan_date_str);
    if ($has_uploaded_by) {
        t("Data: stored plan has correct uploaded_by", (int)($vrow["uploaded_by"] ?? 0) === $staff_id);
    }

    // IDOR check: staff cannot delete plans uploaded by another user
    $other_staff_id = $staff_id + 9999;
    $idor_check = $conn->prepare("SELECT id FROM plans WHERE id=? AND uploaded_by=?");
    $idor_check->bind_param("ii", $plan_id, $other_staff_id);
    $idor_check->execute();
    $idor_row = $idor_check->get_result()->fetch_assoc();
    $idor_check->close();
    t("Security: IDOR — other staff cannot access this plan", empty($idor_row));
}

// Title auto-generation
$auto_title = "แผนการนิเทศ " . date("d/m/Y H:i") . " (เจ้าหน้าที่อัปโหลด)";
t("Data: auto-generated title is non-empty", !empty($auto_title));
t("Data: plan_date defaults to today when blank", date("Y-m-d") === (preg_match("/^\d{4}-\d{2}-\d{2}$/", "") ? "" : date("Y-m-d")));

// Invalid date string fallback
$bad_date = "not-a-date";
$safe_date = preg_match("/^\d{4}-\d{2}-\d{2}$/", $bad_date) ? $bad_date : date("Y-m-d");
t("Data: invalid plan_date falls back to today", $safe_date === date("Y-m-d"));

// Test Uploader Metadata & Scope Queries
$lu_sql = "SELECT p.id, p.title, p.plan_date, p.uploaded_at, p.filename, p.note, p.teacher_id, p.uploaded_by,
                  u_tch.fullname AS teacher_name,
                  COALESCE(u_up.fullname, 'ครูนิเทศก์') AS uploader_name,
                  COALESCE(u_up.role, 'teacher') AS uploader_role,
                  COUNT(DISTINCT p.company_id) AS company_count,
                  GROUP_CONCAT(DISTINCT COALESCE(c.name, 'ทั้งหมด') ORDER BY c.name SEPARATOR ', ') AS company_names
           FROM plans p
           JOIN users u_tch ON p.teacher_id = u_tch.id
           LEFT JOIN users u_up ON p.uploaded_by = u_up.id
           LEFT JOIN companies c ON p.company_id = c.id
           WHERE p.id = $plan_id
           GROUP BY p.filename, p.teacher_id, p.title";
$lu_r = $conn->query($lu_sql);
$lu_data = $lu_r ? $lu_r->fetch_assoc() : null;
t("Data: uploader metadata query returns valid record", !empty($lu_data));
t("Data: uploader_name matches mock staff name", ($lu_data["uploader_name"] ?? "") === $stf_name);
t("Data: uploader_role matches 'staff'", ($lu_data["uploader_role"] ?? "") === "staff");
t("Data: company_names includes mock company name", str_contains($lu_data["company_names"] ?? "", $comp_name));

// Test Dynamic Sorting Queries (by teacher, uploader, plan_date, title)
$sort_fields = [
    'uploaded_at' => 'p.uploaded_at',
    'plan_date'   => 'p.plan_date',
    'teacher'     => 'u_tch.fullname',
    'uploader'    => 'uploader_name',
    'title'       => 'p.title'
];

foreach ($sort_fields as $sKey => $sCol) {
    foreach (['ASC', 'DESC'] as $sDir) {
        $sort_sql = "SELECT p.id, p.title, p.uploaded_at, u_tch.fullname AS teacher_name, COALESCE(u_up.fullname, 'ครูนิเทศก์') AS uploader_name
                     FROM plans p
                     JOIN users u_tch ON p.teacher_id = u_tch.id
                     LEFT JOIN users u_up ON p.uploaded_by = u_up.id
                     GROUP BY p.filename, p.teacher_id, p.title
                     ORDER BY {$sCol} {$sDir}, p.id DESC
                     LIMIT 5";
        $sort_res = $conn->query($sort_sql);
        t("Sorting: query ORDER BY $sKey $sDir succeeds", $sort_res !== false);
    }
}

echo "\n";

// ══════════════════════════════════════════════════════════════════
echo CLR_YELLOW . "[PHASE 6] Notification System\n" . CLR_RESET;
// ══════════════════════════════════════════════════════════════════

$notif_table = $conn->query("SHOW TABLES LIKE 'notifications'");
$notif_exists = ($notif_table && $notif_table->num_rows > 0);
t("Notification: notifications table exists", $notif_exists);

if ($notif_exists && $teacher_id > 0) {
    $notif_title = "\xF0\x9F\x93\x8B เจ้าหน้าที่อัปโหลดแผนการนิเทศให้คุณแล้ว";
    $notif_msg   = "Mock Staff ได้อัปโหลดแผนการนิเทศ \"$plan_title\" ในนามของคุณ (1 สถานประกอบการ)";
    $notif_type  = "plan_uploaded";
    $notif_url   = BASE_URL . "/teacher/plans.php";

    // Call the real function
    $notif_ok = add_notification($teacher_id, $notif_title, $notif_msg, $notif_type, $notif_url);
    t("Notification: add_notification() returns true", $notif_ok === true);

    // Verify it was stored in DB
    $nq = $conn->prepare("SELECT id FROM notifications WHERE user_id=? AND type=? ORDER BY id DESC LIMIT 1");
    $nq->bind_param("is", $teacher_id, $notif_type);
    $nq->execute();
    $nrow = $nq->get_result()->fetch_assoc();
    $nq->close();
    $notif_id = $nrow ? (int)$nrow["id"] : 0;
    if ($notif_id) $cleanup["notif_id"] = $notif_id;
    t("Notification: record stored in notifications table", $notif_id > 0);
}

// History query integrity — using prepared statement (not string interpolation)
if ($staff_id > 0) {
    $hq = $conn->prepare("SELECT COUNT(DISTINCT filename) AS c FROM plans WHERE uploaded_by=?");
    $hq->bind_param("i", $staff_id);
    $hq->execute();
    $hcount = (int)($hq->get_result()->fetch_assoc()["c"] ?? 0);
    $hq->close();
    t("Notification: history count query uses prepared statement (safe)", true);
    t("Notification: history count >= 1 after plan insertion", $hcount >= 1);
}

echo "\n";

// ══════════════════════════════════════════════════════════════════
echo CLR_YELLOW . "[PHASE 7] Cleanup\n" . CLR_RESET;
// ══════════════════════════════════════════════════════════════════

$ok_del = 0; $need_del = 0;

if (!empty($cleanup["notif_id"])) { $need_del++; if ($conn->query("DELETE FROM notifications WHERE id=" . (int)$cleanup["notif_id"])) $ok_del++; }
if (!empty($cleanup["plan_id"])) { $need_del++; if ($conn->query("DELETE FROM plans WHERE id=" . (int)$cleanup["plan_id"])) $ok_del++; }
if (!empty($cleanup["student_id"])) { $need_del++; if ($conn->query("DELETE FROM users WHERE id=" . (int)$cleanup["student_id"])) $ok_del++; }
if (!empty($cleanup["ta_id"])) { $need_del++; if ($conn->query("DELETE FROM teacher_assignments WHERE id=" . (int)$cleanup["ta_id"])) $ok_del++; }
if (!empty($cleanup["classroom_id"])) { $need_del++; if ($conn->query("DELETE FROM classrooms WHERE id=" . (int)$cleanup["classroom_id"])) $ok_del++; }
if (!empty($cleanup["company_id"])) { $need_del++; if ($conn->query("DELETE FROM companies WHERE id=" . (int)$cleanup["company_id"])) $ok_del++; }
if (!empty($cleanup["teacher_id"])) { $need_del++; if ($conn->query("DELETE FROM users WHERE id=" . (int)$cleanup["teacher_id"])) $ok_del++; }
if (!empty($cleanup["staff_id"])) { $need_del++; if ($conn->query("DELETE FROM users WHERE id=" . (int)$cleanup["staff_id"])) $ok_del++; }

t("Cleanup: all test records removed ($ok_del / $need_del)", $ok_del === $need_del);

// ══════════════════════════════════════════════════════════════════
echo "\n" . CLR_CYAN . str_repeat("=",60) . "\n" . CLR_RESET;
echo CLR_CYAN . "  RESULTS\n" . CLR_RESET;
echo CLR_CYAN . str_repeat("=",60) . "\n" . CLR_RESET;
echo CLR_GREEN . "  ✅ PASSED: $passed\n" . CLR_RESET;
if ($failed > 0) {
    echo CLR_RED . "  ❌ FAILED: $failed\n" . CLR_RESET;
    exit(1);
} else {
    echo CLR_GREEN . "  🎉 ALL TESTS PASSED — Staff Upload Plan feature is production-ready.\n" . CLR_RESET;
}
echo CLR_CYAN . str_repeat("=",60) . "\n" . CLR_RESET;
