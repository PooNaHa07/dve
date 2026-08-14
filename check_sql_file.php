<?php
$sqlFile = __DIR__ . '/db/tvet_system (2).sql';
if (!file_exists($sqlFile)) {
    die("SQL file not found\n");
}

$content = file_get_contents($sqlFile);

if (substr($content, 0, 2) === "\xFF\xFE") {
    $content = mb_convert_encoding($content, 'UTF-8', 'UTF-16LE');
} elseif (substr($content, 0, 2) === "\xFE\xFF") {
    $content = mb_convert_encoding($content, 'UTF-8', 'UTF-16BE');
}

$lines = explode("\n", str_replace("\r\n", "\n", $content));

$users = [];
$in_users_insert = false;

function normalizeName($name) {
    $name = preg_replace('/^(นาย|นางสาว|นาง|เด็กชาย|เด็กหญิง)\s*/u', '', trim($name));
    $name = preg_replace('/\s+/u', '', $name);
    return $name;
}

foreach ($lines as $line_num => $line) {
    $line = trim($line);
    if (stripos($line, 'INSERT INTO `users`') !== false) {
        $in_users_insert = true;
        continue;
    }
    
    if ($in_users_insert) {
        if ($line === '' || substr($line, 0, 2) === '--' || substr($line, 0, 2) === '/*') {
            continue;
        }
        
        // Parse a tuple like (193, 'Setthaphong', ...)
        // The line might end with a comma ',' or semicolon ';'
        $is_end = (substr($line, -1) === ';');
        $tuple = rtrim($line, ',;');
        $tuple = trim($tuple);
        
        if (substr($tuple, 0, 1) === '(' && substr($tuple, -1) === ')') {
            $rec = substr($tuple, 1, -1);
            $cols = str_getcsv($rec, ',', "'");
            
            $id = $cols[0] ?? '';
            $username = $cols[1] ?? '';
            $fullname = $cols[2] ?? '';
            $email = $cols[3] ?? '';
            $role = $cols[5] ?? '';
            $student_code = $cols[25] ?? ''; // Wait, in line sample: student_code was 25th column (0-indexed). Let's search columns.
            // columns defined:
            // 0: id, 1: username, 2: fullname, 3: email, 4: password, 5: role, 6: phone, 7: affiliation, 8: company_id, 9: created_at, 10: classroom_id, 11: company_name, 12: company_address, 13: company_phone, 14: trainer_name, 15: trainer_phone, 16: trainer_position, 17: mentor_id, 18: student_level, 19: profile_image, 20: company_manager, 21: mentor_id_2, 22: mentor_id_3, 23: mentor_id_4, 24: mentor_id_5, 25: student_code, 26: branch_id
            
            $norm_name = normalizeName($fullname);
            $user_data = [
                'id' => $id,
                'username' => $username,
                'fullname' => $fullname,
                'role' => $role,
                'student_code' => $student_code
            ];
            
            if ($role === 'student') {
                $users[] = $user_data;
            }
        }
        
        if ($is_end) {
            $in_users_insert = false;
        }
    }
}

echo "Total Students parsed from SQL: " . count($users) . "\n";

$dup_by_username = [];
$dup_by_name = [];
$dup_by_code = [];

foreach ($users as $u) {
    $un = $u['username'];
    $name = normalizeName($u['fullname']);
    $code = trim($u['student_code']);
    
    if (isset($dup_by_username[$un])) {
        $dup_by_username[$un][] = $u;
    } else {
        $dup_by_username[$un] = [$u];
    }
    
    if ($name !== '') {
        if (isset($dup_by_name[$name])) {
            $dup_by_name[$name][] = $u;
        } else {
            $dup_by_name[$name] = [$u];
        }
    }
    
    if ($code !== '' && $code !== 'NULL') {
        if (isset($dup_by_code[$code])) {
            $dup_by_code[$code][] = $u;
        } else {
            $dup_by_code[$code] = [$u];
        }
    }
}

// Count duplicates
$dup_username_count = 0;
foreach ($dup_by_username as $un => $group) {
    if (count($group) > 1) {
        $dup_username_count++;
        echo "Duplicate Username: $un\n";
        foreach ($group as $u) {
            echo "  - ID: {$u['id']}, Name: {$u['fullname']}, Code: {$u['student_code']}\n";
        }
    }
}

$dup_name_count = 0;
foreach ($dup_by_name as $name => $group) {
    if (count($group) > 1) {
        $dup_name_count++;
        echo "Duplicate Name (Normalized): $name\n";
        foreach ($group as $u) {
            echo "  - ID: {$u['id']}, Username: {$u['username']}, Code: {$u['student_code']}, Fullname: {$u['fullname']}\n";
        }
    }
}

$dup_code_count = 0;
foreach ($dup_by_code as $code => $group) {
    if (count($group) > 1) {
        $dup_code_count++;
        echo "Duplicate Student Code: $code\n";
        foreach ($group as $u) {
            echo "  - ID: {$u['id']}, Username: {$u['username']}, Name: {$u['fullname']}, Code: {$u['student_code']}\n";
        }
    }
}

echo "Summary:\n";
echo "  Duplicate Usernames count: $dup_username_count\n";
echo "  Duplicate Names count: $dup_name_count\n";
echo "  Duplicate Student Codes count: $dup_code_count\n";
