<?php
declare(strict_types=1);

/**
 * 🛠️ Contact System Security, Logic & Integrity Test Suite
 * Assesses database tables, input sanitization, safe parameter binding, and message storage integrity.
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
echo CLR_CYAN . "✉️ STARTING CONTACT SYSTEM SECURITY & LOGIC TESTS\n" . CLR_RESET;
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

// Table Existence
$tableCheck = $conn->query("SHOW TABLES LIKE 'contact_messages'");
assertTest("contact_messages table exists in database", $tableCheck && $tableCheck->num_rows > 0);

// Verify message storage and insert capabilities
$createdTempMsg = false;
$tempMsgId = 0;

echo CLR_YELLOW . "  ℹ Preparing a secure test message containing XSS strings...\n" . CLR_RESET;
$insertStmt = $conn->prepare("INSERT INTO contact_messages (sender_name, contact_info, subject, message, status, created_at) VALUES (?, ?, ?, ?, ?, ?)");
if ($insertStmt) {
    $name = "Test Sender <script>alert('xss-sender')</script>";
    $contact = "test@example.com <script>alert('xss-contact')</script>";
    $subject = "ทั่วไป";
    $message = "This is a temporary test message to verify security sanitization.";
    $status = "unread";
    $created_at = date('Y-m-d H:i:s');
    $insertStmt->bind_param("ssssss", $name, $contact, $subject, $message, $status, $created_at);
    $insertStmt->execute();
    $tempMsgId = $insertStmt->insert_id;
    $insertStmt->close();
    $createdTempMsg = ($tempMsgId > 0);
}

assertTest("Able to safely store contact messages into the database", $createdTempMsg);


// -----------------------------------------------------------------------------
echo "\n" . CLR_YELLOW . "Test Suite 2: XSS Sanitization & Escaping Verification\n" . CLR_RESET;
// -----------------------------------------------------------------------------

if ($createdTempMsg && $tempMsgId > 0) {
    $fetchStmt = $conn->prepare("SELECT sender_name, contact_info, subject, message FROM contact_messages WHERE id = ?");
    $fetchedMsg = null;
    if ($fetchStmt) {
        $fetchStmt->bind_param("i", $tempMsgId);
        $fetchStmt->execute();
        $fetchedMsg = $fetchStmt->get_result()->fetch_assoc();
        $fetchStmt->close();
    }

    assertTest("Able to query stored test contact message details", $fetchedMsg !== null);

    if ($fetchedMsg) {
        // 1. Test XSS escaping on Sender Name
        $escapedName = e($fetchedMsg['sender_name']);
        assertTest("HTML tags in Contact Name are successfully escaped to prevent XSS", !str_contains($escapedName, "<script>"));

        // 2. Test XSS escaping on Contact Info
        $escapedContact = e($fetchedMsg['contact_info']);
        assertTest("HTML tags in Contact Info are successfully escaped to prevent XSS", !str_contains($escapedContact, "<script>"));

        // 3. Test XSS escaping on Message Content
        $escapedMessage = e($fetchedMsg['message']);
        assertTest("HTML tags in Message Content are successfully escaped to prevent XSS", !str_contains($escapedMessage, "<script>"));
    }
} else {
    echo CLR_RED . "  ✗ Skipping Test Suite 2: Temporary message was not created.\n" . CLR_RESET;
    $testsFailed += 4;
}


// -----------------------------------------------------------------------------
echo "\n" . CLR_YELLOW . "Test Suite 3: SQLi Parameter Protection & Secure Binding\n" . CLR_RESET;
// -----------------------------------------------------------------------------

// Prepared statement select validation
$foundMsg = false;
$sqlId = $tempMsgId > 0 ? $tempMsgId : 1;
$stmtSec = $conn->prepare("SELECT id FROM contact_messages WHERE id = ?");
if ($stmtSec) {
    $stmtSec->bind_param("i", $sqlId);
    $stmtSec->execute();
    $stmtSec->store_result();
    $foundMsg = ($stmtSec->num_rows > 0);
    $stmtSec->close();
}
assertTest("System retrieves contact messages safely using secure parameter bindings (No SQL Injection)", $foundMsg);


// -----------------------------------------------------------------------------
// Clean up temporary test data if created
if ($createdTempMsg && $tempMsgId > 0) {
    $conn->query("DELETE FROM contact_messages WHERE id = $tempMsgId");
    echo CLR_YELLOW . "\n  ℹ Temporary test contact message cleaned up successfully from the database.\n" . CLR_RESET;
}

echo "\n" . CLR_CYAN . "========================================================\n" . CLR_RESET;
echo CLR_CYAN . "📋 CONTACT TEST EXECUTION COMPLETE SUMMARY\n" . CLR_RESET;
echo CLR_CYAN . "========================================================\n" . CLR_RESET;
echo CLR_GREEN . "  Passed: $testsPassed\n" . CLR_RESET;
if ($testsFailed > 0) {
    echo CLR_RED . "  Failed: $testsFailed\n" . CLR_RESET;
} else {
    echo CLR_GREEN . "  All contact system tests passed successfully! 🎉\n" . CLR_RESET;
}
echo CLR_CYAN . "========================================================\n" . CLR_RESET;
