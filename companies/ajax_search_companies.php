<?php
/**
 * ajax_search_companies.php
 * AJAX endpoint: ค้นหาสถานประกอบการและดึง branches แยกตามห้องเรียน
 *
 * GET  ?q=ชื่อ[&classroom_id=X]   → JSON array of matching companies/branches
 * GET  ?company_id=X[&branch_id=Y] → JSON single company detail for auto-fill
 */
declare(strict_types=1);
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php';

header('Content-Type: application/json; charset=utf-8');

// Security: must be logged in
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// ── Mode 1: auto-fill detail for a selected company / branch ──────────────────
if (isset($_GET['company_id'])) {
    $company_id = (int)$_GET['company_id'];
    $branch_id  = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : 0;

    if ($branch_id > 0) {
        // Return branch-specific data (overrides parent)
        $stmt = $conn->prepare("
            SELECT
                c.name        AS company_name,
                c.address     AS company_address_parent,
                COALESCE(b.address, c.address)           AS address,
                COALESCE(b.contact_name, c.contact_name) AS contact_name,
                COALESCE(b.contact_phone, c.contact_phone) AS contact_phone,
                COALESCE(b.manager_name, '')             AS manager_name,
                b.branch_label,
                b.id          AS branch_id,
                b.classroom_id
            FROM company_branches b
            JOIN companies c ON c.id = b.company_id
            WHERE b.id = ? AND b.company_id = ?
            LIMIT 1
        ");
        $stmt->bind_param("ii", $branch_id, $company_id);
    } else {
        // No branch selected, return company master data
        $stmt = $conn->prepare("
            SELECT
                name          AS company_name,
                address,
                contact_name,
                contact_phone,
                ''            AS manager_name,
                NULL          AS branch_label,
                NULL          AS branch_id,
                NULL          AS classroom_id
            FROM companies
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->bind_param("i", $company_id);
    }

    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    echo json_encode($row ?: ['error' => 'Not found']);
    exit;
}

// ── Mode 2: Search companies by name ─────────────────────────────────────────
$q            = trim($_GET['q'] ?? '');
$classroom_id = isset($_GET['classroom_id']) ? (int)$_GET['classroom_id'] : 0;

if (strlen($q) < 1) {
    echo json_encode([]);
    exit;
}

$like = '%' . $conn->real_escape_string($q) . '%';

/*
 * Strategy:
 *  – Find distinct company names matching the query.
 *  – For each company, also return any branches that match the student's classroom
 *    (or all branches if no classroom filter).
 *  – Return flat list: each item is either a company (no branch) or a branch entry.
 */
$sql = "
    SELECT
        c.id           AS company_id,
        c.name         AS company_name,
        c.address      AS company_address,
        c.contact_name,
        c.contact_phone,
        NULL           AS branch_id,
        NULL           AS branch_label,
        NULL           AS classroom_id,
        NULL           AS class_name
    FROM companies c
    WHERE c.name LIKE ?
      AND c.status = 1

    UNION ALL

    SELECT
        c.id           AS company_id,
        c.name         AS company_name,
        COALESCE(b.address, c.address) AS company_address,
        COALESCE(b.contact_name, c.contact_name) AS contact_name,
        COALESCE(b.contact_phone, c.contact_phone) AS contact_phone,
        b.id           AS branch_id,
        b.branch_label,
        b.classroom_id,
        cl.class_name
    FROM company_branches b
    JOIN companies c ON c.id = b.company_id
    LEFT JOIN classrooms cl ON cl.id = b.classroom_id
    WHERE c.name LIKE ?
      AND c.status = 1
";

$params = [$like, $like];
$types  = 'ss';

// If classroom filter requested, prioritise branches for that classroom
if ($classroom_id > 0) {
    // We still return all results but sort classroom-matching branches first
    $sql .= " ORDER BY (classroom_id = $classroom_id) DESC, company_name ASC, branch_id ASC";
} else {
    $sql .= " ORDER BY company_name ASC, branch_id ASC";
}

$sql .= " LIMIT 30";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/*
 * De-duplicate: if a company has no branches in our DB, show just one "base" row.
 * If it has branches, we only show the branches (since the base row would be redundant).
 * This avoids showing the same company twice.
 */
$company_has_branch = [];
foreach ($rows as $r) {
    if ($r['branch_id'] !== null) {
        $company_has_branch[(int)$r['company_id']] = true;
    }
}

$output = [];
$seen_company_no_branch = [];
foreach ($rows as $r) {
    $cid = (int)$r['company_id'];
    if ($r['branch_id'] === null) {
        // Show base record only when company has no branches at all
        if (!isset($company_has_branch[$cid])) {
            if (!isset($seen_company_no_branch[$cid])) {
                $seen_company_no_branch[$cid] = true;
                $output[] = $r;
            }
        }
    } else {
        $output[] = $r;
    }
}

echo json_encode($output);
