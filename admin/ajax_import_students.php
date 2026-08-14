<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php';
require_login();
require_role(['admin', 'staff']);

/**
 * Resolves classroom ID by matching level prefix and tokens of the affiliation name.
 */
function resolveClassroom($conn, $student_level, $affiliation) {
    // Normalize level
    $lvl = preg_replace('/([ปวชส]+)\.\s+/', '$1.', trim($student_level));
    $affName = trim($affiliation);
    
    if (empty($affName)) {
        return null;
    }

    // Replace punctuation and parentheses with spaces, then split by whitespace
    $cleanAff = preg_replace('/[\(\)\+\-\[\]\{\}\.,\/_]/u', ' ', $affName);
    $rawTokens = preg_split('/\s+/u', $cleanAff);
    $tokens = [];
    
    // Stop words to exclude from matching tokens
    $stopWords = ['ห้อง', 'สาขาวิชา', 'สาขา'];
    
    foreach ($rawTokens as $t) {
        $t = trim($t);
        if ($t !== '' && !in_array($t, $stopWords)) {
            $tokens[] = $t;
        }
    }

    // Cache classrooms to avoid multiple SQL queries in the loop
    static $allClassrooms = null;
    if ($allClassrooms === null) {
        $allClassrooms = [];
        $res = $conn->query("SELECT id, class_name FROM classrooms");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $allClassrooms[] = $row;
            }
        }
    }

    // Strategy 1: Match level AND all affiliation tokens
    foreach ($allClassrooms as $class) {
        $className = $class['class_name'];
        
        // Level check is mandatory if level is specified
        if (!empty($lvl)) {
            if (mb_strpos($className, $lvl) === false) {
                continue;
            }
        }
        
        // Token check: all tokens must match
        $allMatched = true;
        foreach ($tokens as $token) {
            if (mb_strpos($className, $token) === false) {
                $allMatched = false;
                break;
            }
        }
        
        if ($allMatched) {
            return $class;
        }
    }

    // Strategy 2: Match all affiliation tokens only (only if level is not specified)
    if (empty($lvl)) {
        foreach ($allClassrooms as $class) {
            $className = $class['class_name'];
            
            $allMatched = true;
            foreach ($tokens as $token) {
                if (mb_strpos($className, $token) === false) {
                    $allMatched = false;
                    break;
                }
            }
            
            if ($allMatched) {
                return $class;
            }
        }
    }

    return null;
}

/**
 * Normalizes full name by removing title prefixes (นาย, นางสาว, นาง, เด็กชาย, เด็กหญิง) and whitespaces.
 */
function normalizeName($name) {
    $name = preg_replace('/^(นาย|นางสาว|นาง|เด็กชาย|เด็กหญิง)\s*/u', '', trim($name));
    $name = preg_replace('/\s+/u', '', $name);
    return $name;
}


header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$action = $_POST['action'] ?? '';

// ─── PREVIEW: parse CSV and return rows ───────────────────────────────────────
if ($action === 'preview') {
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'ไม่พบไฟล์ที่อัปโหลด']);
        exit;
    }

    $tmpPath = $_FILES['csv_file']['tmp_name'];
    $content = file_get_contents($tmpPath);

    // Detect and convert encoding
    // In PHP 8.1+, mb_detect_encoding() throws a ValueError if the encodings array contains TIS-620 or Windows-874
    // since Windows PHP standard mbstring does not support them natively.
    // Instead, we check if the content is valid UTF-8. If not, we convert it from TIS-620/Windows-874 using iconv.
    if (!mb_check_encoding($content, 'UTF-8')) {
        $converted = @iconv('TIS-620', 'UTF-8//IGNORE', $content);
        if ($converted !== false) {
            $content = $converted;
        }
    }

    // Remove BOM if present
    $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

    $lines = explode("\n", str_replace("\r\n", "\n", str_replace("\r", "\n", $content)));
    $rows = [];
    $header = null;

    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;

        // Parse CSV line (handles quoted fields)
        $cols = str_getcsv($line, ',', '"');
        $cols = array_map('trim', $cols);

        if ($header === null) {
            $header = $cols;
            continue;
        }

        // Skip empty rows
        if (empty($cols[0]) && empty($cols[1])) continue;

        // Map columns
        $row = [
            'student_code'    => $cols[0] ?? '',
            'fullname'        => $cols[1] ?? '',
            'affiliation'     => $cols[2] ?? '',
            'student_level'   => $cols[3] ?? '',
            'company_name'    => $cols[4] ?? '',
            'company_address' => $cols[5] ?? '',
        ];

        if (empty($row['student_code']) || empty($row['fullname'])) continue;

        // Check if student_code already exists
        $stmt = $conn->prepare("SELECT id, fullname FROM users WHERE student_code = ?");
        $stmt->bind_param("s", $row['student_code']);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $matched_by_name = false;
        if (!$existing) {
            // Fallback: Check if there is an existing student in the database with the same normalized name
            static $dbStudents = null;
            if ($dbStudents === null) {
                $dbStudents = [];
                $res = $conn->query("SELECT id, fullname, student_code FROM users WHERE role = 'student'");
                if ($res) {
                    while ($rowDb = $res->fetch_assoc()) {
                        $dbStudents[] = $rowDb;
                    }
                }
            }
            
            $normCsvName = normalizeName($row['fullname']);
            foreach ($dbStudents as $dbStu) {
                if (normalizeName($dbStu['fullname']) === $normCsvName) {
                    $existing = $dbStu;
                    $matched_by_name = true;
                    break;
                }
            }
        }

        $row['status'] = $existing ? 'update' : 'new';
        $row['existing_name'] = $existing['fullname'] ?? '';
        $row['existing_id'] = $existing['id'] ?? 0;
        $row['matched_by_name'] = $matched_by_name;

        // Auto-generate username from student_code
        $row['username'] = $row['student_code'];

        // ── Strict classroom lookup: match by level prefix + affiliation name ──
        $cls = resolveClassroom($conn, $row['student_level'], $row['affiliation']);

        $row['classroom_id']        = $cls['id'] ?? null;
        $row['matched_class_name']  = $cls['class_name'] ?? null;
        $row['student_level_norm']  = preg_replace('/([ปวชส]+)\.\s+/', '$1.', trim($row['student_level']));  // Normalized level for import action

        $rows[] = $row;
    }

    echo json_encode(['success' => true, 'rows' => $rows, 'total' => count($rows)]);
    exit;
}

// ─── IMPORT: write to DB ──────────────────────────────────────────────────────
if ($action === 'import') {
    $rows_json = $_POST['rows'] ?? '';
    $default_password = $_POST['default_password'] ?? 'student1234';
    $update_existing = ($_POST['update_existing'] ?? '0') === '1';

    $rows = json_decode($rows_json, true);
    if (!$rows || !is_array($rows)) {
        echo json_encode(['success' => false, 'message' => 'ข้อมูลไม่ถูกต้อง']);
        exit;
    }

    $inserted = 0;
    $updated  = 0;
    $skipped  = 0;
    $errors   = [];
    $hashed_pw = password_hash($default_password, PASSWORD_DEFAULT);

    foreach ($rows as $row) {
        $student_code    = trim($row['student_code'] ?? '');
        $fullname        = trim($row['fullname'] ?? '');
        $affiliation     = trim($row['affiliation'] ?? '');
        // Use pre-normalized level from preview; fallback to raw value
        $student_level   = trim($row['student_level_norm'] ?? $row['student_level'] ?? '');
        // Normalize again in case rows come from re-submitted form data
        $student_level   = preg_replace('/([ปวชส]+)\.\s+/', '$1.', $student_level);
        $company_name    = trim($row['company_name'] ?? '');
        $company_address = trim($row['company_address'] ?? '');
        $username        = $student_code;

        // ── Strict classroom resolution — NO auto-create ──
        $classroom_id = !empty($row['classroom_id']) ? (int)$row['classroom_id'] : null;
        if (empty($classroom_id) && !empty($affiliation)) {
            $cls = resolveClassroom($conn, $student_level, $affiliation);
            if ($cls) {
                $classroom_id = (int)$cls['id'];
            } else {
                // ⛔ Do NOT auto-create — log a warning instead
                $errors[] = "⚠️ [{$student_code}] ไม่พบห้องเรียนที่ตรงกับ '{$affiliation}' (ระดับ: {$student_level}) — ข้ามการ assign ห้องเรียน";
                // classroom_id remains null; student is still imported
            }
        }

        // Sync company & branch first
        $company_id_fk = null;
        $branch_id_fk = null;

        if (!empty($company_name)) {
            // Find or create company
            $stmtComp = $conn->prepare("SELECT id FROM companies WHERE name = ? LIMIT 1");
            $stmtComp->bind_param("s", $company_name);
            $stmtComp->execute();
            $comp = $stmtComp->get_result()->fetch_assoc();
            $stmtComp->close();

            if ($comp) {
                $company_id_fk = (int)$comp['id'];
            } else {
                $stmtComp = $conn->prepare("INSERT INTO companies (name, address) VALUES (?, ?)");
                $stmtComp->bind_param("ss", $company_name, $company_address);
                if ($stmtComp->execute()) {
                    $company_id_fk = $conn->insert_id;
                }
                $stmtComp->close();
            }

            if ($company_id_fk && !empty($classroom_id)) {
                // Find or create branch for this classroom/affiliation
                $branch_label = $affiliation ?: 'สาขาทั่วไป';
                $stmtBr = $conn->prepare("SELECT id FROM company_branches WHERE company_id = ? AND classroom_id = ? LIMIT 1");
                $stmtBr->bind_param("ii", $company_id_fk, $classroom_id);
                $stmtBr->execute();
                $br = $stmtBr->get_result()->fetch_assoc();
                $stmtBr->close();

                if ($br) {
                    $branch_id_fk = (int)$br['id'];
                } else {
                    $stmtBr = $conn->prepare("INSERT INTO company_branches (company_id, branch_label, classroom_id, address) VALUES (?, ?, ?, ?)");
                    $stmtBr->bind_param("isis", $company_id_fk, $branch_label, $classroom_id, $company_address);
                    if ($stmtBr->execute()) {
                        $branch_id_fk = $conn->insert_id;
                    }
                    $stmtBr->close();
                }
            }
        }

        if (empty($student_code) || empty($fullname)) {
            $skipped++;
            continue;
        }

        $existing_id = !empty($row['existing_id']) ? (int)$row['existing_id'] : 0;
        $existing = null;
        if ($existing_id > 0) {
            $stmt = $conn->prepare("SELECT id FROM users WHERE id = ?");
            $stmt->bind_param("i", $existing_id);
            $stmt->execute();
            $existing = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        } else {
            // Check existing by student_code
            $stmt = $conn->prepare("SELECT id FROM users WHERE student_code = ?");
            $stmt->bind_param("s", $student_code);
            $stmt->execute();
            $existing = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }

        if ($existing) {
            if (!$update_existing) {
                $skipped++;
                continue;
            }
            // Update existing student with company_id, branch_id, and also assign/update student_code
            $stmt = $conn->prepare("UPDATE users SET student_code=?, fullname=?, affiliation=?, student_level=?, company_name=?, company_address=?, classroom_id=?, company_id=?, branch_id=? WHERE id=?");
            $stmt->bind_param("ssssssiiii", $student_code, $fullname, $affiliation, $student_level, $company_name, $company_address, $classroom_id, $company_id_fk, $branch_id_fk, $existing['id']);
            if ($stmt->execute()) {
                $updated++;
            } else {
                $errors[] = "อัปเดต $student_code ล้มเหลว: " . $conn->error;
            }
            $stmt->close();
        } else {
            // Check if username taken
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $un_exists = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($un_exists) {
                $username = $student_code . '_' . time();
            }

            $email = $student_code . '@student.local';
            $role  = 'student';

            $stmt = $conn->prepare("INSERT INTO users (username, password, fullname, email, role, affiliation, student_level, company_name, company_address, student_code, classroom_id, company_id, branch_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->bind_param("ssssssssssiii", $username, $hashed_pw, $fullname, $email, $role, $affiliation, $student_level, $company_name, $company_address, $student_code, $classroom_id, $company_id_fk, $branch_id_fk);
            if ($stmt->execute()) {
                $inserted++;
            } else {
                $errors[] = "เพิ่ม $student_code ล้มเหลว: " . $conn->error;
            }
            $stmt->close();
        }
    }

    // Also sync companies from import
    syncCompaniesFromImport($conn, $rows);

    echo json_encode([
        'success'  => true,
        'inserted' => $inserted,
        'updated'  => $updated,
        'skipped'  => $skipped,
        'errors'   => $errors,
        'message'  => "นำเข้าสำเร็จ: เพิ่ม {$inserted} | อัปเดต {$updated} | ข้าม {$skipped}",
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action']);

// ─── Helper: sync unique companies to companies table ─────────────────────────
function syncCompaniesFromImport(mysqli $conn, array $rows): void {
    $seen = [];
    foreach ($rows as $row) {
        $name    = trim($row['company_name'] ?? '');
        $address = trim($row['company_address'] ?? '');
        if (empty($name) || isset($seen[$name])) continue;
        $seen[$name] = true;

        $stmt = $conn->prepare("SELECT id FROM companies WHERE name = ? LIMIT 1");
        $stmt->bind_param("s", $name);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$exists) {
            $stmt = $conn->prepare("INSERT INTO companies (name, address) VALUES (?, ?)");
            $stmt->bind_param("ss", $name, $address);
            $stmt->execute();
            $stmt->close();
        }
    }
}
