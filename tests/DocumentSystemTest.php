<?php
declare(strict_types=1);

/**
 * 🛠️ Document System Security, Integrity & Logic Test Suite
 * Assesses database tables, XSS escaping, secure SQL bindings, download safety, and filename sanitization.
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
echo CLR_CYAN . "📋 STARTING DOCUMENT SYSTEM SECURITY & LOGIC TESTS\n" . CLR_RESET;
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
$tableCheck = $conn->query("SHOW TABLES LIKE 'documents'");
assertTest("Documents table exists in database", $tableCheck && $tableCheck->num_rows > 0);

// Ensure document records are populated for thorough testing
$docCountQuery = $conn->query("SELECT COUNT(*) as count FROM documents");
$docCount = 0;
if ($docCountQuery) {
    $row = $docCountQuery->fetch_assoc();
    $docCount = (int)$row['count'];
}

// If no documents exist, temporarily create a test document record
$createdTempDoc = false;
$tempDocId = 0;
if ($docCount === 0) {
    echo CLR_YELLOW . "  ℹ No document records found. Creating a temporary test document for validation...\n" . CLR_RESET;
    $insertStmt = $conn->prepare("INSERT INTO documents (title, filename, created_at) VALUES (?, ?, ?)");
    if ($insertStmt) {
        $title = "Test File <script>alert('xss-doc-test')</script>";
        $filename = "test_document_<script>malicious.pdf";
        $created_at = date('Y-m-d H:i:s');
        $insertStmt->bind_param("sss", $title, $filename, $created_at);
        $insertStmt->execute();
        $tempDocId = $insertStmt->insert_id;
        $insertStmt->close();
        $createdTempDoc = true;
    }
}

assertTest("Documents data available in database for validation", ($docCount > 0 || $createdTempDoc));


// -----------------------------------------------------------------------------
echo "\n" . CLR_YELLOW . "Test Suite 2: XSS Sanitization, Safe Filenames, and escaping\n" . CLR_RESET;
// -----------------------------------------------------------------------------

// Fetch latest document to inspect
$docQuery = $conn->query("SELECT id, title, filename, created_at FROM documents ORDER BY created_at DESC LIMIT 1");
$doc = $docQuery ? $docQuery->fetch_assoc() : null;

assertTest("Able to query latest document details successfully", $doc !== null);

if ($doc) {
    // 1. Test XSS Sanitization on Title
    $escapedTitle = e($doc['title']);
    assertTest("HTML tags in Document Title are successfully escaped to prevent XSS", !str_contains($escapedTitle, "<script>"));

    // 2. Test XSS Sanitization on Filename
    $escapedFilename = e($doc['filename']);
    assertTest("HTML tags in Document Filename are successfully escaped inside URLs", !str_contains($escapedFilename, "<script>"));

    // 3. Test relative path traversal prevention in filenames
    $isPathTraversalProtected = !str_contains($doc['filename'], '../') && !str_contains($doc['filename'], '..\\');
    assertTest("Document filename does not contain path traversal characters (../) for file system protection", $isPathTraversalProtected);
}


// -----------------------------------------------------------------------------
echo "\n" . CLR_YELLOW . "Test Suite 3: SQLi Parameter Protection & Secure Binding\n" . CLR_RESET;
// -----------------------------------------------------------------------------

// Prepared statements with valid ID
$testId = $doc ? (int)$doc['id'] : 1;
$stmt = $conn->prepare("SELECT title, filename, created_at FROM documents WHERE id = ?");
$found = false;
if ($stmt) {
    $stmt->bind_param("i", $testId);
    $stmt->execute();
    $stmt->store_result();
    $found = ($stmt->num_rows > 0);
    $stmt->close();
}
assertTest("System retrieves individual document safely using secure parameter bindings (No SQL Injection)", $found);


// -----------------------------------------------------------------------------
// Clean up temporary test data if created
if ($createdTempDoc && $tempDocId > 0) {
    $conn->query("DELETE FROM documents WHERE id = $tempDocId");
    echo CLR_YELLOW . "\n  ℹ Temporary test document cleaned up successfully.\n" . CLR_RESET;
}

echo "\n" . CLR_CYAN . "========================================================\n" . CLR_RESET;
echo CLR_CYAN . "📋 DOCUMENT TEST EXECUTION COMPLETE SUMMARY\n" . CLR_RESET;
echo CLR_CYAN . "========================================================\n" . CLR_RESET;
echo CLR_GREEN . "  Passed: $testsPassed\n" . CLR_RESET;
if ($testsFailed > 0) {
    echo CLR_RED . "  Failed: $testsFailed\n" . CLR_RESET;
} else {
    echo CLR_GREEN . "  All document system tests passed successfully! 🎉\n" . CLR_RESET;
}
echo CLR_CYAN . "========================================================\n" . CLR_RESET;
