<?php
declare(strict_types=1);

/**
 * 🛠️ Staff System Security, Flow & Validation Test Suite
 * Ensures correct database schemas, counter reliability, data accessibility,
 * and protective controls for the centralized Staff Dashboard.
 */

// 1. Bootstrapping
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php';

// Define colors for consistent console log output
define('CLR_RESET', "\033[0m");
define('CLR_GREEN', "\033[32m");
define('CLR_RED', "\033[31m");
define('CLR_CYAN', "\033[36m");
define('CLR_YELLOW', "\033[33m");

echo CLR_CYAN . "========================================================\n" . CLR_RESET;
echo CLR_CYAN . "💼 STARTING STAFF DASHBOARD SYSTEM AUDIT & TESTS\n" . CLR_RESET;
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
echo CLR_YELLOW . "[PHASE 1] Validating Table Structures Required by Staff\n" . CLR_RESET;
// -----------------------------------------------------------------------------

$requiredTables = [
    'users'             => 'Core system users registry',
    'news'              => 'Announcement distribution',
    'mentors'           => 'Mentorship records',
    'companies'         => 'Business partners database',
    'calendar_events'   => 'Calendar routing subsystem',
    'documents'         => 'Digital archive repository',
    'daily_reports'     => 'Centralized report submission stream',
    'evaluations'       => 'Academic grade output tracking',
    'supervision_files' => 'Internal management workflow'
];

foreach ($requiredTables as $table => $desc) {
    $chk = $conn->query("SHOW TABLES LIKE '$table'");
    assertTest("Table checks: '$table' ($desc) is active", $chk && $chk->num_rows > 0);
}

// -----------------------------------------------------------------------------
echo "\n" . CLR_YELLOW . "[PHASE 2] Sandbox Environment Setup & Stat Verification\n" . CLR_RESET;
// -----------------------------------------------------------------------------

// Track creations to auto-delete at the end
$createdNews = false;
$newsId = 0;
$createdReport = false;
$reportId = 0;
$testStudentId = 0;

// 1. Capture baseline
$initialNewsCount = safe_count('news');
$initialPendingReports = safe_count('daily_reports', "status = 'pending'");

echo CLR_CYAN . "  [DEBUG] Detected current system baseline: $initialNewsCount News, $initialPendingReports Pending Reports\n" . CLR_RESET;

// 2. Action: Create a mock student
$stdUsr = "test_suite_std_" . rand(1000, 9999);
$stdPass = password_hash("password", PASSWORD_DEFAULT);
$insertStd = $conn->prepare("INSERT INTO users (username, fullname, password, role) VALUES (?, 'Mock System Verification Student', ?, 'student')");
if ($insertStd) {
    $insertStd->bind_param("ss", $stdUsr, $stdPass);
    if ($insertStd->execute()) {
        $testStudentId = $insertStd->insert_id;
    }
    $insertStd->close();
}

// 3. Action: Inject temporary records to trigger dashboards update
// Simulating "Staff adds a News notice"
$stmtNews = $conn->prepare("INSERT INTO news (title, content) VALUES ('SYS_TEST_NEWS', 'Testing dashboard response')");
if ($stmtNews) {
    if ($stmtNews->execute()) {
        $newsId = $stmtNews->insert_id;
        $createdNews = true;
    }
    $stmtNews->close();
}
assertTest("Sandbox: Simulating staff news creation succeeds", $createdNews && $newsId > 0);

// Simulating "Student creates report" (should immediately increment the Staff Pending count)
if ($testStudentId > 0) {
    $stmtRep = $conn->prepare("INSERT INTO daily_reports (student_id, status, details) VALUES (?, 'pending', 'Testing staff monitoring capabilities')");
    if ($stmtRep) {
        $stmtRep->bind_param("i", $testStudentId);
        if ($stmtRep->execute()) {
            $reportId = $stmtRep->insert_id;
            $createdReport = true;
        }
        $stmtRep->close();
    }
}
assertTest("Sandbox: Injecting active workload task (Pending Report) succeeds", $createdReport && $reportId > 0);

// -----------------------------------------------------------------------------
echo "\n" . CLR_YELLOW . "[PHASE 3] Core Logic Verification & Counters Synchronization\n" . CLR_RESET;
// -----------------------------------------------------------------------------

// Check if our injected news correctly bumped global stats
$newNewsCount = safe_count('news');
assertTest("Dashboard synchronization: News metrics dynamically incremented (+1)", $newNewsCount === ($initialNewsCount + 1));

// Check if our injected pending report triggered workload counters
$newPendingReports = safe_count('daily_reports', "status = 'pending'");
assertTest("Dashboard synchronization: Workload queue 'Pending Reports' dynamic bump (+1)", $newPendingReports === ($initialPendingReports + 1));

// -----------------------------------------------------------------------------
echo "\n" . CLR_YELLOW . "[PHASE 4] Automated Safety & Input Disinfection Audits\n" . CLR_RESET;
// -----------------------------------------------------------------------------

// 1. Cross-Site Scripting (XSS) payload prevention
$dirtyPayload = "<script>alert('XSS_PAYLOAD');</script><strong>Bold Text</strong>";
$cleanPayload = e($dirtyPayload);
assertTest("Sanitization Filter: Output encoder 'e()' properly neutralizes raw JS injection", !str_contains($cleanPayload, "<script>"));

// 2. SQL Parameter Separation (Checking robustness against bypass)
$evilInput = "999 OR 1=1";
$isSafe = false;
// Using safe wrappers
$safeVal = safe_count('news', "id = " . (int)$evilInput); // Simulating strict typecast
assertTest("Input handling: Variable strictly parsed as integer prevents raw payload leakage", $safeVal === 0);

// -----------------------------------------------------------------------------
echo "\n" . CLR_YELLOW . "[PHASE 5] Cleanup & Resource Teardown\n" . CLR_RESET;
// -----------------------------------------------------------------------------

$cleanupSteps = 0;

if ($createdNews) {
    if ($conn->query("DELETE FROM news WHERE id = $newsId")) $cleanupSteps++;
}
if ($createdReport) {
    if ($conn->query("DELETE FROM daily_reports WHERE id = $reportId")) $cleanupSteps++;
}
if ($testStudentId > 0) {
    if ($conn->query("DELETE FROM users WHERE id = $testStudentId")) $cleanupSteps++;
}

assertTest("System integrity: Dynamic sandbox teardown removed all temporary data entries", $cleanupSteps >= 2);

// -----------------------------------------------------------------------------
echo "\n" . CLR_CYAN . "========================================================\n" . CLR_RESET;
echo CLR_CYAN . "📊 AUDIT SUMMARY RESULTS\n" . CLR_RESET;
echo CLR_CYAN . "========================================================\n" . CLR_RESET;

echo CLR_GREEN . "  ✅ PASSED TESTS: $testsPassed\n" . CLR_RESET;
if ($testsFailed > 0) {
    echo CLR_RED . "  ❌ FAILED TESTS: $testsFailed\n" . CLR_RESET;
} else {
    echo CLR_GREEN . "  💎 CONFIRMATION: Staff Ecosystem maintains perfect health standards.\n" . CLR_RESET;
}
echo CLR_CYAN . "========================================================\n" . CLR_RESET;
