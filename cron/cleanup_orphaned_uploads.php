<?php
/**
 * Cron maintenance script to clean up orphaned supervision documents.
 * Deletes physical files in original/ and signed/ folders that are not registered in the database.
 * To avoid deleting currently uploading files, only files older than 24 hours are removed.
 */

// Only run via CLI or with a secure token (optional)
if (php_sapi_name() !== 'cli' && (!isset($_GET['token']) || $_GET['token'] !== 'dve_secure_cleanup_2026')) {
    http_response_code(403);
    die("Access Denied: CLI or valid token required.");
}

require_once __DIR__ . '/../includes/configdb.php';

$original_dir = __DIR__ . '/../uploads/supervision_docs/original/';
$signed_dir = __DIR__ . '/../uploads/supervision_docs/signed/';

// Ensure directories exist
if (!is_dir($original_dir)) {
    die("Error: Original directory does not exist: $original_dir\n");
}
if (!is_dir($signed_dir)) {
    die("Error: Signed directory does not exist: $signed_dir\n");
}

echo "=== Supervision Docs Orphaned Cleanup System ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

// 1. Fetch all registered files from database
$registered_originals = [];
$registered_signed = [];

$sql = "SELECT file_path, signed_file FROM supervision_files";
$res = $conn->query($sql);

if ($res) {
    while ($row = $res->fetch_assoc()) {
        if (!empty($row['file_path'])) {
            $registered_originals[basename($row['file_path'])] = true;
        }
        if (!empty($row['signed_file'])) {
            $registered_signed[basename($row['signed_file'])] = true;
        }
    }
} else {
    die("Database Query Error: " . $conn->error . "\n");
}

echo "Registered originals in DB: " . count($registered_originals) . "\n";
echo "Registered signed files in DB: " . count($registered_signed) . "\n\n";

$now = time();
$threshold_seconds = 86400; // 24 hours to prevent race conditions during upload/merge processes

// 2. Clean original directory
echo "Scanning original directory: $original_dir\n";
$original_files = scandir($original_dir);
$deleted_originals_count = 0;
$skipped_originals_count = 0;

foreach ($original_files as $file) {
    if ($file === '.' || $file === '..' || $file === '.gitkeep') {
        continue;
    }
    
    $full_path = $original_dir . $file;
    if (is_file($full_path)) {
        $mtime = filemtime($full_path);
        $age = $now - $mtime;
        
        if (!isset($registered_originals[$file])) {
            if ($age > $threshold_seconds) {
                if (unlink($full_path)) {
                    echo "[-] Deleted orphaned original file: $file (Age: " . round($age / 3600, 1) . " hours)\n";
                    $deleted_originals_count++;
                } else {
                    echo "[!] Failed to delete: $file\n";
                }
            } else {
                echo "[~] Skipped young orphaned original file: $file (Age: " . round($age / 3600, 1) . " hours)\n";
                $skipped_originals_count++;
            }
        }
    }
}

// 3. Clean signed directory
echo "\nScanning signed directory: $signed_dir\n";
$signed_files = scandir($signed_dir);
$deleted_signed_count = 0;
$skipped_signed_count = 0;

foreach ($signed_files as $file) {
    if ($file === '.' || $file === '..' || $file === '.gitkeep') {
        continue;
    }
    
    $full_path = $signed_dir . $file;
    if (is_file($full_path)) {
        $mtime = filemtime($full_path);
        $age = $now - $mtime;
        
        if (!isset($registered_signed[$file])) {
            if ($age > $threshold_seconds) {
                if (unlink($full_path)) {
                    echo "[-] Deleted orphaned signed file: $file (Age: " . round($age / 3600, 1) . " hours)\n";
                    $deleted_signed_count++;
                } else {
                    echo "[!] Failed to delete: $file\n";
                }
            } else {
                echo "[~] Skipped young orphaned signed file: $file (Age: " . round($age / 3600, 1) . " hours)\n";
                $skipped_signed_count++;
            }
        }
    }
}

echo "\n=== Cleanup Summary ===\n";
echo "Deleted original files: $deleted_originals_count\n";
echo "Skipped original files (young): $skipped_originals_count\n";
echo "Deleted signed files: $deleted_signed_count\n";
echo "Skipped signed files (young): $skipped_signed_count\n";
echo "Cleanup completed successfully.\n";
