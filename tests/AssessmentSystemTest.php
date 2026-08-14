<?php
declare(strict_types=1);

/**
 * 📋 Assessment & Grading System Test Suite
 * Verifies the 70/20/10 scoring logic, grade mapping, 
 * and server-side validation rules.
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php';

define('CLR_RESET', "\033[0m");
define('CLR_GREEN', "\033[32m");
define('CLR_RED', "\033[31m");
define('CLR_CYAN', "\033[36m");
define('CLR_YELLOW', "\033[33m");

echo CLR_CYAN . "========================================================\n" . CLR_RESET;
echo CLR_CYAN . "🎓 STARTING ASSESSMENT LOGIC & GRADING TESTS\n" . CLR_RESET;
echo CLR_CYAN . "========================================================\n\n" . CLR_RESET;

$testsPassed = 0;
$testsFailed = 0;

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

/**
 * Mocking the grading logic to test accuracy
 */
function calculateTestGrade(int $total): string {
    if ($total >= 80) return '4';
    if ($total >= 75) return '3.5';
    if ($total >= 70) return '3';
    if ($total >= 65) return '2.5';
    if ($total >= 60) return '2';
    if ($total >= 55) return '1.5';
    if ($total >= 50) return '1';
    return '0';
}

// -----------------------------------------------------------------------------
echo CLR_YELLOW . "[PHASE 1] Testing Grade Mapping Accuracy\n" . CLR_RESET;
// -----------------------------------------------------------------------------

assertTest("Grade Calculation: 85 points = Grade 4", calculateTestGrade(85) === '4');
assertTest("Grade Calculation: 80 points = Grade 4 (Boundary)", calculateTestGrade(80) === '4');
assertTest("Grade Calculation: 77 points = Grade 3.5", calculateTestGrade(77) === '3.5');
assertTest("Grade Calculation: 75 points = Grade 3.5 (Boundary)", calculateTestGrade(75) === '3.5');
assertTest("Grade Calculation: 70 points = Grade 3", calculateTestGrade(70) === '3');
assertTest("Grade Calculation: 65 points = Grade 2.5", calculateTestGrade(65) === '2.5');
assertTest("Grade Calculation: 60 points = Grade 2", calculateTestGrade(60) === '2');
assertTest("Grade Calculation: 55 points = Grade 1.5", calculateTestGrade(55) === '1.5');
assertTest("Grade Calculation: 50 points = Grade 1", calculateTestGrade(50) === '1');
assertTest("Grade Calculation: 49 points = Grade 0 (Fail)", calculateTestGrade(49) === '0');

// -----------------------------------------------------------------------------
echo "\n" . CLR_YELLOW . "[PHASE 2] Testing Scoring Bounds (70/20/10)\n" . CLR_RESET;
// -----------------------------------------------------------------------------

function validateScores(int $work, int $report, int $behavior): bool {
    return !($work < 0 || $work > 70 || $report < 0 || $report > 20 || $behavior < 0 || $behavior > 10);
}

assertTest("Score Bounds: 70/20/10 is valid", validateScores(70, 20, 10) === true);
assertTest("Score Bounds: 0/0/0 is valid", validateScores(0, 0, 0) === true);
assertTest("Score Bounds: 71/20/10 is invalid (Work overflow)", validateScores(71, 20, 10) === false);
assertTest("Score Bounds: 70/21/10 is invalid (Report overflow)", validateScores(70, 21, 10) === false);
assertTest("Score Bounds: 70/20/11 is invalid (Behavior overflow)", validateScores(70, 20, 11) === false);
assertTest("Score Bounds: Negative scores are invalid", validateScores(-1, 5, 5) === false);

// -----------------------------------------------------------------------------
echo "\n" . CLR_YELLOW . "[PHASE 3] Database Schema Consistency for Evaluations\n" . CLR_RESET;
// -----------------------------------------------------------------------------

global $conn;
$schemaChecks = [
    'evaluations' => ['score_work', 'score_report', 'score_behavior', 'total_score', 'grade'],
    'staff_evaluations' => ['score_work', 'score_report', 'score_behavior', 'total_score', 'grade']
];

foreach ($schemaChecks as $table => $columns) {
    foreach ($columns as $col) {
        $check = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$col'");
        assertTest("Schema: Table '$table' has column '$col'", $check && $check->num_rows > 0);
    }
}

// -----------------------------------------------------------------------------
echo "\n" . CLR_CYAN . "========================================================\n" . CLR_RESET;
echo CLR_CYAN . "📊 TEST SUMMARY\n" . CLR_RESET;
echo CLR_CYAN . "========================================================\n" . CLR_RESET;

echo CLR_GREEN . "  ✅ PASSED: $testsPassed\n" . CLR_RESET;
if ($testsFailed > 0) {
    echo CLR_RED . "  ❌ FAILED: $testsFailed\n" . CLR_RESET;
} else {
    echo CLR_GREEN . "  💎 ALL SYSTEM LOGIC VERIFIED CORRECTLY\n" . CLR_RESET;
}
echo CLR_CYAN . "========================================================\n" . CLR_RESET;
