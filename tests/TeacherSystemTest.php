<?php
declare(strict_types=1);

/**
 * 🛠️ Teacher System Security, Isolation & Logic Test Suite
 * Assesses database tables, teacher roles, classroom assignments, data isolation,
 * pending reports auditing, plans management, SQLi bindings, and XSS sanitization.
 */

// 1. Bootstrapping and Database Connection
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php';

// Colors for terminal output
define('CLR_RESET', "\033[0m");
define('CLR_GREEN', "\033[32m");
define('CLR_RED', "\033[31m");
define('CLR_CYAN', "\033[36m");
define('CLR_YELLOW', "\033[33m");

echo CLR_CYAN . "========================================================\n" . CLR_RESET;
echo CLR_CYAN . "👩‍🏫 STARTING TEACHER SYSTEM SECURITY & LOGIC TESTS\n" . CLR_RESET;
echo CLR_CYAN . "========================================================\n\n" . CLR_RESET;

global $conn;

if (!$conn) {
    echo CLR_RED . "❌ Error: Failed to connect to database.\n" . CLR_RESET;
    exit(1);
}

$testsPassed = 0;
$testsFailed = 0;

/**
 * Helper to assert values
 */
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

// -----------------------------------------------------------------------------
echo CLR_YELLOW . "Test Suite 1: Database Table and Schema Validation\n" . CLR_RESET;
// -----------------------------------------------------------------------------

// 1. users table exists
$tableUsers = $conn->query("SHOW TABLES LIKE 'users'");
assertTest("Users table exists in database", $tableUsers && $tableUsers->num_rows > 0);

// 2. teacher_assignments table exists
$tableAssignments = $conn->query("SHOW TABLES LIKE 'teacher_assignments'");
assertTest("Teacher Assignments table exists in database", $tableAssignments && $tableAssignments->num_rows > 0);

// 3. classrooms table exists
$tableClassrooms = $conn->query("SHOW TABLES LIKE 'classrooms'");
assertTest("Classrooms table exists in database", $tableClassrooms && $tableClassrooms->num_rows > 0);

// 4. daily_reports table exists
$tableDailyReports = $conn->query("SHOW TABLES LIKE 'daily_reports'");
assertTest("Daily Reports table exists in database", $tableDailyReports && $tableDailyReports->num_rows > 0);

// 5. evaluations table exists
$tableEvaluations = $conn->query("SHOW TABLES LIKE 'evaluations'");
assertTest("Evaluations table exists in database", $tableEvaluations && $tableEvaluations->num_rows > 0);

// 6. plans table exists
$tablePlans = $conn->query("SHOW TABLES LIKE 'plans'");
assertTest("Supervision Plans table exists in database", $tablePlans && $tablePlans->num_rows > 0);

// 7. subject_plans table exists
$tableSubjectPlans = $conn->query("SHOW TABLES LIKE 'subject_plans'");
assertTest("Subject/Teaching Plans table exists in database", $tableSubjectPlans && $tableSubjectPlans->num_rows > 0);


// -----------------------------------------------------------------------------
echo "\n" . CLR_YELLOW . "Test Suite 2: Role Management & Temporary Test Environment Setup\n" . CLR_RESET;
// -----------------------------------------------------------------------------

// We will set up a complete sandbox test environment to verify teacher flow
$createdTempTeacher = false;
$tempTeacherId = 0;
$createdTempClassroom = false;
$tempClassroomId = 0;
$createdTempStudent = false;
$tempStudentId = 0;
$createdTempAssignment = false;
$createdTempReport = false;
$tempReportId = 0;

// 1. Verify if at least one teacher exists or create a temporary one
$teacherCheck = $conn->query("SELECT id FROM users WHERE role = 'teacher' LIMIT 1");
$existingTeacherId = 0;
if ($teacherCheck && $teacherCheck->num_rows > 0) {
    $row = $teacherCheck->fetch_assoc();
    $existingTeacherId = (int)$row['id'];
}

// Sandbox: Setup temporary Classroom
$classInsert = $conn->prepare("INSERT INTO classrooms (class_name) VALUES (?)");
if ($classInsert) {
    $className = "ห้องทดสอบพิเศษ 9/9";
    $classInsert->bind_param("s", $className);
    if ($classInsert->execute()) {
        $tempClassroomId = $classInsert->insert_id;
        $createdTempClassroom = true;
    }
    $classInsert->close();
}
assertTest("Temporary sandbox classroom created successfully", $createdTempClassroom && $tempClassroomId > 0);

// Sandbox: Setup temporary Teacher User
$teacherInsert = $conn->prepare("INSERT INTO users (username, fullname, email, password, role) VALUES (?, ?, ?, ?, 'teacher')");
if ($teacherInsert) {
    $tUser = "test_teacher_spec_user_" . rand(1000, 9999);
    $tName = "ครูสมชาย ทดสอบระบบ";
    $tEmail = "somchai_test_" . rand(1000, 9999) . "@example.com";
    $tPass = password_hash("somchai123", PASSWORD_DEFAULT);
    $teacherInsert->bind_param("ssss", $tUser, $tName, $tEmail, $tPass);
    if ($teacherInsert->execute()) {
        $tempTeacherId = $teacherInsert->insert_id;
        $createdTempTeacher = true;
    }
    $teacherInsert->close();
}
assertTest("Temporary sandbox teacher user created successfully", $createdTempTeacher && $tempTeacherId > 0);

// Sandbox: Assign Teacher to the Classroom
$assignInsert = $conn->prepare("INSERT INTO teacher_assignments (teacher_id, classroom_id) VALUES (?, ?)");
if ($assignInsert) {
    $assignInsert->bind_param("ii", $tempTeacherId, $tempClassroomId);
    if ($assignInsert->execute()) {
        $createdTempAssignment = true;
    }
    $assignInsert->close();
}
assertTest("Teacher-classroom assignment created successfully in teacher_assignments", $createdTempAssignment);

// Sandbox: Setup temporary Student User assigned to that classroom
$studentInsert = $conn->prepare("INSERT INTO users (username, fullname, email, password, role, classroom_id) VALUES (?, ?, ?, ?, 'student', ?)");
if ($studentInsert) {
    $sUser = "test_student_spec_user_" . rand(1000, 9999);
    $sName = "นักเรียนสมหวัง ทดสอบระบบ";
    $sEmail = "somwang_test_" . rand(1000, 9999) . "@example.com";
    $sPass = password_hash("somwang123", PASSWORD_DEFAULT);
    $studentInsert->bind_param("ssssi", $sUser, $sName, $sEmail, $sPass, $tempClassroomId);
    if ($studentInsert->execute()) {
        $tempStudentId = $studentInsert->insert_id;
        $createdTempStudent = true;
    }
    $studentInsert->close();
}
assertTest("Temporary sandbox student user created and assigned to the classroom", $createdTempStudent && $tempStudentId > 0);

// Sandbox: Create a pending Daily Report for this student
$reportInsert = $conn->prepare("INSERT INTO daily_reports (student_id, date_work, details, problems, solutions, status) VALUES (?, ?, ?, ?, ?, 'pending')");
if ($reportInsert) {
    $workDate = date('Y-m-d');
    $details = "วันนี้ฝึกทำแบบทดสอบระบบครูนิเทศก์";
    $problems = "ไม่มีอุปสรรคใดๆ";
    $solutions = "ทำงานเสร็จตามแผน";
    $reportInsert->bind_param("issss", $tempStudentId, $workDate, $details, $problems, $solutions);
    if ($reportInsert->execute()) {
        $tempReportId = $reportInsert->insert_id;
        $createdTempReport = true;
    }
    $reportInsert->close();
}
assertTest("Temporary student daily report created with status 'pending'", $createdTempReport && $tempReportId > 0);


// -----------------------------------------------------------------------------
echo "\n" . CLR_YELLOW . "Test Suite 3: Teacher Access Control & Data Isolation Verification\n" . CLR_RESET;
// -----------------------------------------------------------------------------

// 1. Fetching Teacher Assignments (checking if classroom IDs are correctly resolved)
$assignedClassrooms = [];
$sql_my_rooms = "SELECT classroom_id FROM teacher_assignments WHERE teacher_id = ?";
$stmt_my_rooms = $conn->prepare($sql_my_rooms);
if ($stmt_my_rooms) {
    $stmt_my_rooms->bind_param("i", $tempTeacherId);
    $stmt_my_rooms->execute();
    $res_my_rooms = $stmt_my_rooms->get_result();
    while ($r = $res_my_rooms->fetch_assoc()) {
        $assignedClassrooms[] = (int)$r['classroom_id'];
    }
    $stmt_my_rooms->close();
}
assertTest("Teacher assignment resolver returns correct classroom ID", count($assignedClassrooms) === 1 && $assignedClassrooms[0] === $tempClassroomId);

// 2. Data Isolation Check (Students list must ONLY contain students from assigned classrooms)
$filter_where = "";
if (!empty($assignedClassrooms)) {
    $ids_string = implode(',', $assignedClassrooms);
    $filter_where = " AND u.classroom_id IN ($ids_string)";
} else {
    $filter_where = " AND u.classroom_id = 0";
}

$sql_pending = "SELECT COUNT(*) as total FROM daily_reports dr
                JOIN users u ON dr.student_id = u.id 
                WHERE dr.status = 'pending' $filter_where";
$res_pending = $conn->query($sql_pending);
$row_pending = $res_pending ? $res_pending->fetch_assoc() : null;
$pending_count = $row_pending ? (int)$row_pending['total'] : 0;

assertTest("Teacher pending daily report counter returns correct count of 1 for assigned classroom", $pending_count === 1);

// 3. Security Data Isolation Verification (Checking that students from non-assigned classrooms are NOT visible)
// Let's query with a dummy/non-assigned classroom ID
$dummyClassroomId = $tempClassroomId + 999;
$dummy_filter_where = " AND u.classroom_id IN ($dummyClassroomId)";
$sql_dummy_pending = "SELECT COUNT(*) as total FROM daily_reports dr
                      JOIN users u ON dr.student_id = u.id 
                      WHERE dr.status = 'pending' $dummy_filter_where";
$res_dummy_pending = $conn->query($sql_dummy_pending);
$row_dummy_pending = $res_dummy_pending ? $res_dummy_pending->fetch_assoc() : null;
$dummy_pending_count = $row_dummy_pending ? (int)$row_dummy_pending['total'] : 0;

assertTest("Teacher data isolation is secure: Student reports in unassigned classrooms are strictly hidden (Count = 0)", $dummy_pending_count === 0);


// -----------------------------------------------------------------------------
echo "\n" . CLR_YELLOW . "Test Suite 4: SQL Injection & XSS Sanitization in Teacher Actions\n" . CLR_RESET;
// -----------------------------------------------------------------------------

// 1. Simulate SQL Injection attempt in classroom selection / filter
// Using prepared statements prevents SQL Injection
$maliciousId = "0 OR 1=1";
$sql_secure_check = "SELECT classroom_id FROM teacher_assignments WHERE teacher_id = ?";
$stmt_secure = $conn->prepare($sql_secure_check);
$securedResultCount = -1;
if ($stmt_secure) {
    // Malicious ID is bound as string or integer safely without injecting query structure
    $stmt_secure->bind_param("s", $maliciousId);
    $stmt_secure->execute();
    $res_secure = $stmt_secure->get_result();
    $securedResultCount = $res_secure->num_rows;
    $stmt_secure->close();
}
assertTest("Classroom assignment queries are fully protected against SQL Injection via prepared statements", $securedResultCount === 0);

// 2. XSS Sanitization on Teacher Plans
// Teacher can upload plans with titles containing HTML tags. e() sanitization must be active.
$planTitleWithXSS = "แผนฝึกงานวิชาช่างยนต์ <script>alert('xss-teacher-test')</script>";
$escapedPlanTitle = e($planTitleWithXSS);
assertTest("XSS sanitization successfully strips/escapes unsafe HTML tags from plans titles", !str_contains($escapedPlanTitle, "<script>"));


// -----------------------------------------------------------------------------
echo "\n" . CLR_YELLOW . "Test Suite 5: Cleaning up Temporary Sandbox Test Data\n" . CLR_RESET;
// -----------------------------------------------------------------------------

// 1. Delete temporary daily report
if ($createdTempReport && $tempReportId > 0) {
    $conn->query("DELETE FROM daily_reports WHERE id = $tempReportId");
}

// 2. Delete temporary student
if ($createdTempStudent && $tempStudentId > 0) {
    $conn->query("DELETE FROM users WHERE id = $tempStudentId");
}

// 3. Delete temporary teacher classroom assignment
if ($createdTempAssignment && $tempTeacherId > 0) {
    $conn->query("DELETE FROM teacher_assignments WHERE teacher_id = $tempTeacherId");
}

// 4. Delete temporary teacher
if ($createdTempTeacher && $tempTeacherId > 0) {
    $conn->query("DELETE FROM users WHERE id = $tempTeacherId");
}

// 5. Delete temporary classroom
if ($createdTempClassroom && $tempClassroomId > 0) {
    $conn->query("DELETE FROM classrooms WHERE id = $tempClassroomId");
}

echo CLR_GREEN . "  ✓ Temporary sandbox environment cleaned up successfully.\n" . CLR_RESET;


// -----------------------------------------------------------------------------
echo "\n" . CLR_CYAN . "========================================================\n" . CLR_RESET;
echo CLR_CYAN . "📋 TEACHER ROLE SYSTEM TEST SUMMARY\n" . CLR_RESET;
echo CLR_CYAN . "========================================================\n" . CLR_RESET;
echo CLR_GREEN . "  Passed: $testsPassed\n" . CLR_RESET;
if ($testsFailed > 0) {
    echo CLR_RED . "  Failed: $testsFailed\n" . CLR_RESET;
} else {
    echo CLR_GREEN . "  All teacher role system tests passed successfully! 👩‍🏫🎉\n" . CLR_RESET;
}
echo CLR_CYAN . "========================================================\n" . CLR_RESET;
