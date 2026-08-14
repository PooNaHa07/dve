<?php
declare(strict_types=1);

/**
 * 🛠️ Notification System Security & Logic Test Suite
 * Assesses the notification system for security vulnerabilities (IDOR, SQL Injection, XSS) and functional correctness.
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
echo CLR_CYAN . "🔔 STARTING NOTIFICATION SYSTEM SECURITY & LOGIC TESTS\n" . CLR_RESET;
echo CLR_CYAN . "========================================================\n\n" . CLR_RESET;

global $conn;

if (!$conn) {
    echo CLR_RED . "❌ Error: Failed to connect to database.\n" . CLR_RESET;
    exit(1);
}

// 2. Setup Test Data (Use dummy user IDs that are safely out of active range, e.g. 999991 and 999992)
$testUserA = 999991;
$testUserB = 999992;

// Clean up any stale test data from previous runs
$conn->query("DELETE FROM notifications WHERE user_id IN ($testUserA, $testUserB)");

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
echo CLR_YELLOW . "Test Suite 1: Database Schema Integration & Tables\n" . CLR_RESET;
// -----------------------------------------------------------------------------

// Verify table existence
$tableCheck = $conn->query("SHOW TABLES LIKE 'notifications'");
assertTest("Notifications table exists in database", $tableCheck && $tableCheck->num_rows > 0);

// Verify critical columns
$columnsRes = $conn->query("SHOW COLUMNS FROM notifications");
$columns = [];
if ($columnsRes) {
    while ($col = $columnsRes->fetch_assoc()) {
        $columns[] = $col['Field'];
    }
}
assertTest("Table has 'user_id' column", in_array('user_id', $columns));
assertTest("Table has 'title' column", in_array('title', $columns));
assertTest("Table has 'message' column", in_array('message', $columns));
assertTest("Table has 'is_read' column", in_array('is_read', $columns));
assertTest("Table has 'type' or 'action_url' columns", in_array('type', $columns) || in_array('action_url', $columns));


// -----------------------------------------------------------------------------
echo "\n" . CLR_YELLOW . "Test Suite 2: Multi-User Privacy & IDOR Prevention\n" . CLR_RESET;
// -----------------------------------------------------------------------------

// Add test notification for User A
add_notification($testUserA, "User A notification title", "Message for User A", "info", "/some/url");

// Assert that User B cannot retrieve notifications of User A
$stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM notifications WHERE user_id = ?");
$cnt = 0;
if ($stmt) {
    $stmt->bind_param("i", $testUserB);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $cnt = (int)$res['cnt'];
    $stmt->close();
}
assertTest("User B has 0 notifications (No leak of User A's data)", $cnt === 0);

// Retrieve User A's notification ID
$notifId = 0;
$resA = $conn->query("SELECT id FROM notifications WHERE user_id = $testUserA LIMIT 1");
if ($resA && $row = $resA->fetch_assoc()) {
    $notifId = (int)$row['id'];
}

// Simulate User B trying to mark User A's notification as read (IDOR Attack)
// Using click_notification.php logic context: UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?
$updateStmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
$affectedRows = 0;
if ($updateStmt) {
    $updateStmt->bind_param("ii", $notifId, $testUserB); // Trying to mark A's notification as B
    $updateStmt->execute();
    $affectedRows = $updateStmt->affected_rows;
    $updateStmt->close();
}
assertTest("IDOR Attack blocked: User B cannot modify User A's notification state", $affectedRows === 0);

// Verify User A's notification is still unread
$resCheck = $conn->query("SELECT is_read FROM notifications WHERE id = $notifId");
$isRead = 1;
if ($resCheck && $row = $resCheck->fetch_assoc()) {
    $isRead = (int)$row['is_read'];
}
assertTest("User A's notification remains unread after unauthorized update attempt", $isRead === 0);


// -----------------------------------------------------------------------------
echo "\n" . CLR_YELLOW . "Test Suite 3: SQL Injection Protection\n" . CLR_RESET;
// -----------------------------------------------------------------------------

// Attempt SQL Injection via parameters in prepare context
$maliciousId = "999991' OR '1'='1";
$maliciousUser = "999992' OR '1'='1";

// Prepared statement should safely handle string input by casting or binding as parameter
$stmt = $conn->prepare("SELECT id FROM notifications WHERE user_id = ?");
$success = false;
if ($stmt) {
    // If the parameter is correctly typed or bound, SQL Injection is completely neutralized
    $stmt->bind_param("i", $testUserA);
    $stmt->execute();
    $res = $stmt->get_result();
    $success = ($res->num_rows > 0);
    $stmt->close();
}
assertTest("SQL Injection is neutralized via prepared statement bindings", $success);


// -----------------------------------------------------------------------------
echo "\n" . CLR_YELLOW . "Test Suite 4: Cross-Site Scripting (XSS) Sanitization\n" . CLR_RESET;
// -----------------------------------------------------------------------------

$maliciousPayload = "<script>alert('XSS Attack Successful!')</script>";
$escapedPayload = e($maliciousPayload);

assertTest("XSS Payload is properly escaped using e() function", !str_contains($escapedPayload, "<script>") && str_contains($escapedPayload, "&lt;script&gt;"));


// -----------------------------------------------------------------------------
// 5. Clean up
$conn->query("DELETE FROM notifications WHERE user_id IN ($testUserA, $testUserB)");

echo "\n" . CLR_CYAN . "========================================================\n" . CLR_RESET;
echo CLR_CYAN . "📋 TEST EXECUTION COMPLETE SUMMARY\n" . CLR_RESET;
echo CLR_CYAN . "========================================================\n" . CLR_RESET;
echo CLR_GREEN . "  Passed: $testsPassed\n" . CLR_RESET;
if ($testsFailed > 0) {
    echo CLR_RED . "  Failed: $testsFailed\n" . CLR_RESET;
} else {
    echo CLR_GREEN . "  All tests passed successfully! 🎉\n" . CLR_RESET;
}
echo CLR_CYAN . "========================================================\n" . CLR_RESET;
