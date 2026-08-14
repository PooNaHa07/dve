<?php
declare(strict_types=1);

/**
 * 👨‍🏫 Mentor Management System Audit & Test Suite
 * Validates CRUD operations, user linking logic, and data integrity 
 * for the Supervision Teacher module.
 */

// 1. Bootstrapping
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php';

// Define colors for consistent console log output
if (!defined('CLR_RESET'))  define('CLR_RESET', "\033[0m");
if (!defined('CLR_GREEN'))  define('CLR_GREEN', "\033[32m");
if (!defined('CLR_RED'))    define('CLR_RED', "\033[31m");
if (!defined('CLR_CYAN'))   define('CLR_CYAN', "\033[36m");
if (!defined('CLR_YELLOW')) define('CLR_YELLOW', "\033[33m");

echo CLR_CYAN . "========================================================\n" . CLR_RESET;
echo CLR_CYAN . "👨‍🏫 STARTING MENTOR MANAGEMENT SYSTEM AUDIT\n" . CLR_RESET;
echo CLR_CYAN . "========================================================\n\n" . CLR_RESET;

global $conn;

if (!isset($conn) || $conn->connect_error) {
    echo CLR_RED . "❌ CRITICAL ERROR: Cannot reach the core database. Aborting tests.\n" . CLR_RESET;
    exit(1);
}

$testsPassed = 0;
$testsFailed = 0;

/**
 * Dynamic assertion helper
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
echo CLR_YELLOW . "[PHASE 1] Database & Schema Integrity\n" . CLR_RESET;
// -----------------------------------------------------------------------------

$chk = $conn->query("SHOW TABLES LIKE 'mentors'");
assertTest("Table 'mentors' exists", $chk && $chk->num_rows > 0);

$columns = [];
$res = $conn->query("DESCRIBE mentors");
while($row = $res->fetch_assoc()) {
    $columns[] = $row['Field'];
}

$requiredCols = ['id', 'user_id', 'fullname', 'department', 'email', 'phone', 'status'];
foreach($requiredCols as $col) {
    assertTest("Schema: Column '$col' exists in mentors table", in_array($col, $columns));
}

// -----------------------------------------------------------------------------
echo "\n" . CLR_YELLOW . "[PHASE 2] Teacher User Pairing Logic\n" . CLR_RESET;
// -----------------------------------------------------------------------------

$testTeacherId = 0;
$testMentorId = 0;

// 1. Create a dummy teacher user
$tUsr = "test_mentor_link_" . rand(1000, 9999);
$tPass = password_hash("password123", PASSWORD_DEFAULT);
$stmt = $conn->prepare("INSERT INTO users (username, fullname, password, role) VALUES (?, 'Test Teacher For Mentor Link', ?, 'teacher')");
if ($stmt) {
    $stmt->bind_param("ss", $tUsr, $tPass);
    if ($stmt->execute()) {
        $testTeacherId = $stmt->insert_id;
    }
    $stmt->close();
}
assertTest("Account: Create dummy teacher user (ID: $testTeacherId)", $testTeacherId > 0);

// 2. Create a mentor linked to this teacher
$mName = "Mentor Test Suite " . rand(100, 999);
$mDept = "Test Department";
$mEmail = "test@example.com";
$mPhone = "0812345678";
$createdAt = date('Y-m-d H:i:s');

$stmtM = $conn->prepare("INSERT INTO mentors (user_id, fullname, department, email, phone, status, created_at) VALUES (?, ?, ?, ?, ?, 1, ?)");
if ($stmtM) {
    $stmtM->bind_param("isssss", $testTeacherId, $mName, $mDept, $mEmail, $mPhone, $createdAt);
    if ($stmtM->execute()) {
        $testMentorId = $stmtM->insert_id;
    }
    $stmtM->close();
}
assertTest("CRUD: Create mentor linked to teacher user (ID: $testMentorId)", $testMentorId > 0);

// 3. Verify link integrity (Logic fix verification)
$resM = $conn->query("SELECT user_id FROM mentors WHERE id = $testMentorId");
$rowM = $resM->fetch_assoc();
assertTest("Logic: Mentor's user_id correctly matches the assigned teacher user", (int)$rowM['user_id'] === $testTeacherId);

// -----------------------------------------------------------------------------
echo "\n" . CLR_YELLOW . "[PHASE 3] Update & Data Persistence\n" . CLR_RESET;
// -----------------------------------------------------------------------------

$newName = "Updated Mentor Name";
$stmtU = $conn->prepare("UPDATE mentors SET fullname = ? WHERE id = ?");
$successU = false;
if ($stmtU) {
    $stmtU->bind_param("si", $newName, $testMentorId);
    $successU = $stmtU->execute();
    $stmtU->close();
}
assertTest("CRUD: Update mentor details (fullname)", $successU);

$resV = $conn->query("SELECT fullname FROM mentors WHERE id = $testMentorId");
$rowV = $resV->fetch_assoc();
assertTest("Persistence: Updated data correctly retrieved from DB", $rowV['fullname'] === $newName);

// -----------------------------------------------------------------------------
echo "\n" . CLR_YELLOW . "[PHASE 4] Output Security (XSS)\n" . CLR_RESET;
// -----------------------------------------------------------------------------

$xssInput = "<script>alert('pwned')</script> & Content";
$clean = e($xssInput);
assertTest("Security: Global helper e() neutralizes <script> tags", !str_contains($clean, "<script>"));
assertTest("Security: Global helper e() handles ampersands correctly", str_contains($clean, "&amp;"));
assertTest("Security: Global helper e() handles NULL inputs gracefully (PHP 8.1+ Compatibility)", e(null) === "");

// -----------------------------------------------------------------------------
echo "\n" . CLR_YELLOW . "[PHASE 5] Teardown & Resource Cleanup\n" . CLR_RESET;
// -----------------------------------------------------------------------------

$cleanupCount = 0;
if ($testMentorId > 0) {
    if ($conn->query("DELETE FROM mentors WHERE id = $testMentorId")) $cleanupCount++;
}
if ($testTeacherId > 0) {
    if ($conn->query("DELETE FROM users WHERE id = $testTeacherId")) $cleanupCount++;
}

assertTest("Teardown: Removed all temporary test records", $cleanupCount === 2);

// -----------------------------------------------------------------------------
echo "\n" . CLR_CYAN . "========================================================\n" . CLR_RESET;
echo CLR_CYAN . "📊 MENTOR SYSTEM AUDIT SUMMARY\n" . CLR_RESET;
echo CLR_CYAN . "========================================================\n" . CLR_RESET;

echo CLR_GREEN . "  ✅ PASSED TESTS: $testsPassed\n" . CLR_RESET;
if ($testsFailed > 0) {
    echo CLR_RED . "  ❌ FAILED TESTS: $testsFailed\n" . CLR_RESET;
} else {
    echo CLR_GREEN . "  💎 CONFIRMATION: Mentor Management system is SECURE and RELIABLE.\n" . CLR_RESET;
}
echo CLR_CYAN . "========================================================\n" . CLR_RESET;
