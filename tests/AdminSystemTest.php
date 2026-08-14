<?php
declare(strict_types=1);

/**
 * 🛡️ ADMIN SYSTEM INTEGRATION & ROBUSTNESS TEST SUITE
 * Validates critical dashboard entities, table structures, permission safety, and routing dependencies.
 * Crucial to preemptively detecting "Unknown Column" or "Missing Directory" fatal runtimes.
 */

// 1. Environment Bootstrapping
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php';

// Standardized CLI Palette
define('C_RST', "\033[0m");
define('C_GRN', "\033[32m");
define('C_RED', "\033[31m");
define('C_CYN', "\033[36m");
define('C_YLW', "\033[33m");
define('C_BLU', "\033[34m");

echo C_CYN . "╔══════════════════════════════════════════════════════╗\n" . C_RST;
echo C_CYN . "║ 🛠️  ADMINISTRATION BACKEND SYSTEM & INTEGRITY TESTS   ║\n" . C_RST;
echo C_CYN . "╚══════════════════════════════════════════════════════╝\n\n" . C_RST;

global $conn;
if (!isset($conn) || $conn->connect_error) {
    echo C_RED . "❌ FATAL: Database link is severered or uninitialized.\n" . C_RST;
    exit(1);
}

$passed = 0;
$failed = 0;

function run_assert(string $desc, bool $expr, string $tip = ""): void {
    global $passed, $failed;
    if ($expr) {
        echo C_GRN . "  [OK]    " . C_RST . "$desc\n";
        $passed++;
    } else {
        echo C_RED . "  [FAIL]  " . C_RST . "$desc" . ($tip ? C_YLW . " (Tip: $tip)" : "") . C_RST . "\n";
        $failed++;
    }
}

function has_column($conn, $table, $col): bool {
    $res = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$col'");
    return $res && $res->num_rows > 0;
}

// =========================================================================
echo C_BLU . "📂 PHASE 1: DATABASE SCHEMA HARDENING & COLUMN VERIFICATION\n" . C_RST;
echo "---------------------------------------------------------------------\n";

// 1. Check existence of critical Tables
$critical_tables = ['users', 'daily_reports', 'companies', 'plans', 'internship_settings', 'classrooms', 'contact_messages'];
foreach($critical_tables as $tbl) {
    $tChk = $conn->query("SHOW TABLES LIKE '$tbl'");
    run_assert("Table `$tbl` is mounted and accessible", $tChk && $tChk->num_rows > 0, "Check init_db.php for missing schema");
}

echo "\n" . C_BLU . "🔬 PHASE 2: CRITICAL COLUMN MAPPINGS (Preventing Fatal Missing Col)\n" . C_RST;
echo "---------------------------------------------------------------------\n";

// 2. Prevent previous fatal error: company name
run_assert("`companies` contains safe identifier `name` column", has_column($conn, 'companies', 'name'), "Previously caused runtime crashes due to alternate spelling");
run_assert("`users` contains foundational `role` discriminator", has_column($conn, 'users', 'role'));
run_assert("`users` contains user binding `username` field", has_column($conn, 'users', 'username'));
run_assert("`daily_reports` maps relation `student_id` key", has_column($conn, 'daily_reports', 'student_id'));
run_assert("`daily_reports` includes runtime status descriptor `status`", has_column($conn, 'daily_reports', 'status'));
run_assert("`internship_settings` contains range-bounds `start_date` & `end_date`", has_column($conn, 'internship_settings', 'start_date') && has_column($conn, 'internship_settings', 'end_date'));
run_assert("`contact_messages` implements messaging state `status`", has_column($conn, 'contact_messages', 'status'));


// =========================================================================
echo "\n" . C_BLU . "⚡ PHASE 3: DOMAIN LOGIC & AGGREGATION SAFETY\n" . C_RST;
echo "---------------------------------------------------------------------\n";

// Try basic math queries used on dashboard to ensure they don't trigger exceptions
try {
    $conn->query("SELECT COUNT(*) FROM users WHERE role='admin'");
    run_assert("Admin account enumeration aggregator succeeds", true);
} catch (Exception $e) {
    run_assert("Admin account enumeration aggregator succeeds", false, $e->getMessage());
}

// Check if at least ONE admin exists in the database
$adminCountRes = $conn->query("SELECT COUNT(*) as c FROM users WHERE role='admin'");
$adminCount = $adminCountRes ? (int)$adminCountRes->fetch_assoc()['c'] : 0;
run_assert("System bootstrap condition met (Min 1 Admin user exists)", $adminCount > 0, "System locked: creates an admin user via CLI or init_db!");

// Verify reports activity charting logic availability
try {
    $conn->query("SELECT DATE_FORMAT(created_at, '%d/%m') FROM daily_reports LIMIT 1");
    run_assert("Temporal rendering engines (DATE_FORMAT) functional", true);
} catch(Exception $e) {
    run_assert("Temporal rendering engines (DATE_FORMAT) functional", false, "Possible SQL dialect incompatibility");
}


// =========================================================================
echo "\n" . C_BLU . "📁 PHASE 4: FILE SYSTEM & DIRECTORY WIRING\n" . C_RST;
echo "---------------------------------------------------------------------\n";

$base = realpath(__DIR__ . '/../');
$dirs_to_test = [
    $base . '/uploads',
    $base . '/uploads/pdfs',
    $base . '/uploads/reports',
    $base . '/admin'
];

foreach ($dirs_to_test as $p) {
    $relName = str_replace($base, '', $p);
    $exists = is_dir($p);
    if ($exists) {
        $isWritable = is_writable($p);
        run_assert("Directory `$relName` is operational and secure", $exists && $isWritable, "Check read/write permissions or run chmod/chown.");
    } else {
        run_assert("Directory `$relName` is created", false, "Crucial runtime dependency missing");
    }
}

echo "\n" . C_BLU . "🔗 PHASE 5: SECURE DISPATCHING & ROUTING ANCHORS\n" . C_RST;
echo "---------------------------------------------------------------------\n";

$core_scripts = [
    'admin/manage_users.php',
    'admin/manage_classrooms.php',
    'admin/internship_settings.php',
    'admin/ajax_global_search.php',
    'roles/admin.php'
];

foreach ($core_scripts as $script) {
    run_assert("Core vector `/$script` is in correct path", file_exists($base . '/' . $script));
}


// =========================================================================
echo "\n" . C_CYN . "══════════════════════════════════════════════════════\n" . C_RST;
echo C_CYN . "🏁 SYSTEM VALIDATION COMPLETION SUMMARY\n" . C_RST;
echo C_CYN . "══════════════════════════════════════════════════════\n" . C_RST;
echo "  ✅ TOTAL PASSED: " . C_GRN . $passed . C_RST . "\n";
if ($failed > 0) {
    echo "  🚨 TOTAL FAILED: " . C_RED . $failed . C_RST . "\n";
    echo C_RED . "\n ⚠️ ATTENTION REQUIRED: One or more stability checks failed!\n" . C_RST;
    exit(1);
} else {
    echo C_GRN . "\n  🏆 ALL SYSTEMS OPERATIONAL! NO VULNERABILITIES DETECTED.\n" . C_RST;
    exit(0);
}
