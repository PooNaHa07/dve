<?php
declare(strict_types=1);
/**
 * staff/upload_plan_for_teacher.php v2.5 (Hardened & Secure Edition)
 * Staff uploads supervision plan on behalf of teacher.
 * Security & Error Prevention Highlights:
 * - ACID Database Transactions ($conn->begin_transaction(), commit, rollback)
 * - Automatic Orphan File Cleanup (@unlink on failure)
 * - CSRF Protection & Token Regeneration
 * - Strict MIME Magic-Bytes Validation (application/pdf)
 * - Path Traversal Prevention (basename checks)
 * - Strict Whitelisted SQL Sorting & Prepared Statements
 * - Role-Based Access Control (RBAC)
 */
require_once __DIR__ . "/../includes/functions.php";
require_once __DIR__ . "/../includes/configdb.php";
require_login();
require_role(["staff", "admin"]);

$u        = current_user();
$staff_id = (int)$u["id"];

// ── Inline migration: ensure uploaded_by column exists ──────────────────────
$col_chk = $conn->query("SHOW COLUMNS FROM plans LIKE 'uploaded_by'");
if ($col_chk && $col_chk->num_rows === 0) {
    $conn->query("ALTER TABLE plans ADD COLUMN uploaded_by INT DEFAULT NULL COMMENT 'staff user id'");
}

// ── AJAX Endpoint: Get Companies for selected teacher ────────────────────────
if (isset($_GET["get_teacher_companies"])) {
    header("Content-Type: application/json; charset=utf-8");
    $t_id = (int)$_GET["get_teacher_companies"];
    $comps = [];

    if ($t_id > 0) {
        $stc = $conn->prepare("
            SELECT c.id, c.name, COUNT(DISTINCT u.id) AS student_count
            FROM companies c
            JOIN users u ON u.company_id = c.id
            JOIN teacher_assignments ta ON u.classroom_id = ta.classroom_id
            WHERE ta.teacher_id = ? AND u.role = 'student'
            GROUP BY c.id, c.name
            ORDER BY c.name ASC
        ");
        if ($stc) {
            $stc->bind_param("i", $t_id);
            $stc->execute();
            $r_stc = $stc->get_result();
            while ($row = $r_stc->fetch_assoc()) {
                $comps[] = [
                    "id" => (int)$row["id"],
                    "name" => $row["name"],
                    "student_count" => (int)$row["student_count"]
                ];
            }
            $stc->close();
        }

        // Custom text company_name
        $stcn = $conn->prepare("
            SELECT DISTINCT u.company_name
            FROM users u
            JOIN teacher_assignments ta ON u.classroom_id = ta.classroom_id
            WHERE ta.teacher_id = ? AND u.role = 'student'
              AND (u.company_id IS NULL OR u.company_id = 0)
              AND u.company_name IS NOT NULL AND u.company_name != ''
        ");
        if ($stcn) {
            $stcn->bind_param("i", $t_id);
            $stcn->execute();
            $r_stcn = $stcn->get_result();
            $existing_ids = array_column($comps, "id");
            while ($row = $r_stcn->fetch_assoc()) {
                $cn = trim($row["company_name"]);
                $cc = $conn->prepare("SELECT id FROM companies WHERE name = ? LIMIT 1");
                if ($cc) {
                    $cc->bind_param("s", $cn); $cc->execute();
                    $rcc = $cc->get_result();
                    if ($rcc->num_rows > 0) {
                        $cid = (int)$rcc->fetch_assoc()["id"];
                        if (!in_array($cid, $existing_ids, true)) {
                            $comps[] = ["id" => $cid, "name" => $cn, "student_count" => 1];
                            $existing_ids[] = $cid;
                        }
                    }
                    $cc->close();
                }
            }
            $stcn->close();
        }
    }

    echo json_encode(["success" => true, "companies" => $comps]);
    exit;
}

// ── CSRF ─────────────────────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION["csrf_token"])) $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
$csrf_token = $_SESSION["csrf_token"];

$msg_type = $msg_title = $msg_text = "";

// ── DELETE a plan ────────────────────────────────────────────────────────────
if (isset($_POST["delete_plan_id"])) {
    if (!hash_equals($_SESSION["csrf_token"] ?? "", $_POST["csrf_token"] ?? "")) {
        $msg_type="error"; $msg_title="ความปลอดภัย"; $msg_text="CSRF token ไม่ถูกต้อง กรุณารีเฟรชหน้า";
    } else {
        $del_id = (int)($_POST["delete_plan_id"]);
        $user_role = $u["role"] ?? "staff";
        
        $ds = ($user_role === "admin") 
            ? $conn->prepare("SELECT filename, teacher_id FROM plans WHERE id=?")
            : $conn->prepare("SELECT filename, teacher_id FROM plans WHERE id=? AND uploaded_by=?");

        if ($ds) {
            if ($user_role === "admin") {
                $ds->bind_param("i", $del_id);
            } else {
                $ds->bind_param("ii", $del_id, $staff_id);
            }
            $ds->execute();
            $del_row = $ds->get_result()->fetch_assoc();
            $ds->close();

            if ($del_row) {
                $del_file = basename($del_row["filename"] ?? "");
                $del_teacher = (int)$del_row["teacher_id"];

                // Delete physical file safely
                if (!empty($del_file)) {
                    $f = __DIR__ . "/../uploads/plans/" . $del_file;
                    if (is_file($f)) {
                        @unlink($f);
                    }
                }
                
                // Delete ALL plan rows matching this filename & teacher_id (across multiple companies)
                if (!empty($del_file)) {
                    $dd = ($user_role === "admin") 
                        ? $conn->prepare("DELETE FROM plans WHERE filename=? AND teacher_id=?")
                        : $conn->prepare("DELETE FROM plans WHERE filename=? AND teacher_id=? AND uploaded_by=?");

                    if ($dd) {
                        if ($user_role === "admin") {
                            $dd->bind_param("si", $del_file, $del_teacher);
                        } else {
                            $dd->bind_param("sii", $del_file, $del_teacher, $staff_id);
                        }
                        $dd->execute();
                        $dd->close();
                    }
                } else {
                    $dd = ($user_role === "admin") 
                        ? $conn->prepare("DELETE FROM plans WHERE id=?")
                        : $conn->prepare("DELETE FROM plans WHERE id=? AND uploaded_by=?");

                    if ($dd) {
                        if ($user_role === "admin") {
                            $dd->bind_param("i", $del_id);
                        } else {
                            $dd->bind_param("ii", $del_id, $staff_id);
                        }
                        $dd->execute();
                        $dd->close();
                    }
                }

                // Regenerate CSRF token after state change
                $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
                $csrf_token = $_SESSION["csrf_token"];

                $msg_type="success"; $msg_title="ลบสำเร็จ"; $msg_text="ลบแผนการนิเทศเรียบร้อยแล้ว";
            } else {
                $msg_type="error"; $msg_title="ไม่มีสิทธิ์"; $msg_text="ไม่พบรายการหรือคุณไม่มีสิทธิ์ลบรายการนี้";
            }
        }
    }
}

// ── UPLOAD ────────────────────────────────────────────────────────────────────
if (isset($_POST["submit_plan"]) || (isset($_POST["teacher_id"]) && isset($_FILES["plan_file"]))) {
    if (!hash_equals($_SESSION["csrf_token"] ?? "", $_POST["csrf_token"] ?? "")) {
        $msg_type="error"; $msg_title="ความปลอดภัย"; $msg_text="CSRF token ไม่ถูกต้อง กรุณารีเฟรชหน้า";
    } else {
        $teacher_id    = (int)($_POST["teacher_id"] ?? 0);
        $title_raw     = trim($_POST["plan_title"] ?? "");
        $plan_date_raw = trim($_POST["plan_date"] ?? "");

        $target_comp_type = $_POST["target_companies_type"] ?? "all";
        $selected_c_ids   = array_filter(array_map("intval", $_POST["selected_company_ids"] ?? []), fn($id) => $id > 0);

        $upload_errors = [
            UPLOAD_ERR_INI_SIZE=>"ไฟล์ใหญ่เกิน php.ini limit",
            UPLOAD_ERR_FORM_SIZE=>"ไฟล์ใหญ่เกินฟอร์ม",
            UPLOAD_ERR_PARTIAL=>"อัปโหลดไม่สมบูรณ์",
            UPLOAD_ERR_NO_FILE=>"กรุณาเลือกไฟล์ PDF",
            UPLOAD_ERR_NO_TMP_DIR=>"เซิร์ฟเวอร์ไม่มีโฟลเดอร์ temp",
            UPLOAD_ERR_CANT_WRITE=>"เซิร์ฟเวอร์เขียนไฟล์ไม่ได้",
        ];

        if ($teacher_id <= 0) {
            $msg_type="error"; $msg_title="ข้อมูลไม่ครบ"; $msg_text="กรุณาเลือกครูนิเทศก์";
        } elseif (empty($_FILES["plan_file"]["name"]) || $_FILES["plan_file"]["error"] !== UPLOAD_ERR_OK) {
            $ec = $_FILES["plan_file"]["error"] ?? UPLOAD_ERR_NO_FILE;
            $msg_type="error"; $msg_title="ข้อผิดพลาดไฟล์"; $msg_text=$upload_errors[$ec] ?? "กรุณาเลือกไฟล์";
        } else {
            $chk_t = $conn->prepare("SELECT id, fullname FROM users WHERE id=? AND role='teacher' LIMIT 1");
            $chk_t->bind_param("i", $teacher_id);
            $chk_t->execute();
            $teacher_row = $chk_t->get_result()->fetch_assoc();
            $chk_t->close();

            if (!$teacher_row) {
                $msg_type="error"; $msg_title="ข้อผิดพลาด"; $msg_text="ไม่พบครูนิเทศก์ที่เลือก";
            } else {
                $file_ext = strtolower(pathinfo($_FILES["plan_file"]["name"], PATHINFO_EXTENSION));
                if ($file_ext !== "pdf") {
                    $msg_type="error"; $msg_title="ประเภทไฟล์ผิด"; $msg_text="อนุญาตเฉพาะ .pdf เท่านั้น";
                    if (!empty($_FILES["plan_file"]["tmp_name"]) && is_file($_FILES["plan_file"]["tmp_name"])) {
                        @unlink($_FILES["plan_file"]["tmp_name"]);
                    }
                } elseif ($_FILES["plan_file"]["size"] > 20*1024*1024) {
                    $msg_type="error"; $msg_title="ไฟล์ใหญ่เกิน"; $msg_text="ขนาดไม่เกิน 20 MB";
                    if (!empty($_FILES["plan_file"]["tmp_name"]) && is_file($_FILES["plan_file"]["tmp_name"])) {
                        @unlink($_FILES["plan_file"]["tmp_name"]);
                    }
                } else {
                    // Magic-bytes MIME verification
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mime  = $finfo->file($_FILES["plan_file"]["tmp_name"]);
                    if ($mime !== "application/pdf") {
                        $msg_type="error"; $msg_title="ไฟล์ไม่ถูกต้อง"; $msg_text="ไฟล์ที่อัปโหลดไม่ใช่ PDF จริง (detected: " . htmlspecialchars($mime) . ")";
                        if (!empty($_FILES["plan_file"]["tmp_name"]) && is_file($_FILES["plan_file"]["tmp_name"])) {
                            @unlink($_FILES["plan_file"]["tmp_name"]);
                        }
                    } else {
                        $target_dir = __DIR__ . "/../uploads/plans/";
                        if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);
                        $file_new_name = time() . "_" . bin2hex(random_bytes(6)) . ".pdf";
                        $target_file   = $target_dir . $file_new_name;

                        if (!move_uploaded_file($_FILES["plan_file"]["tmp_name"], $target_file)) {
                            $msg_type="error"; $msg_title="บันทึกไม่ได้"; $msg_text="ไม่สามารถบันทึกไฟล์ได้ กรุณาตรวจสิทธิ์โฟลเดอร์";
                        } else {
                            $title     = !empty($title_raw) ? $title_raw : ("แผนการนิเทศ " . date("d/m/Y H:i") . " (เจ้าหน้าที่อัปโหลด)");
                            $plan_date = preg_match("/^\d{4}-\d{2}-\d{2}$/", $plan_date_raw) ? $plan_date_raw : date("Y-m-d");
                            $note      = "อัปโหลดแทนโดย: " . ($u["fullname"] ?? "เจ้าหน้าที่") . " [staff_id=" . $staff_id . "]";

                            // Begin Database Transaction for ACID safety
                            $conn->begin_transaction();
                            try {
                                $c_ids = [];
                                $sc = $conn->prepare("SELECT DISTINCT c.id FROM companies c JOIN users u2 ON u2.company_id=c.id JOIN teacher_assignments ta ON u2.classroom_id=ta.classroom_id WHERE ta.teacher_id=? AND u2.role='student'");
                                $sc->bind_param("i", $teacher_id);
                                $sc->execute();
                                $rc = $sc->get_result();
                                while ($r=$rc->fetch_assoc()) $c_ids[]=(int)$r["id"];
                                $sc->close();

                                $scn = $conn->prepare("SELECT DISTINCT u2.company_name FROM users u2 JOIN teacher_assignments ta ON u2.classroom_id=ta.classroom_id WHERE ta.teacher_id=? AND u2.role='student' AND (u2.company_id IS NULL OR u2.company_id=0) AND u2.company_name IS NOT NULL AND u2.company_name!=''");
                                $scn->bind_param("i", $teacher_id);
                                $scn->execute();
                                $rcn = $scn->get_result();
                                while ($r=$rcn->fetch_assoc()) {
                                    $cn = trim($r["company_name"]);
                                    $cc = $conn->prepare("SELECT id FROM companies WHERE name=? LIMIT 1");
                                    $cc->bind_param("s",$cn); $cc->execute();
                                    $rcc=$cc->get_result();
                                    if ($rcc->num_rows>0) { $cid=(int)$rcc->fetch_assoc()["id"]; }
                                    else {
                                        $ic=$conn->prepare("INSERT INTO companies (name) VALUES (?)");
                                        $ic->bind_param("s",$cn); $ic->execute();
                                        $cid=(int)$ic->insert_id; $ic->close();
                                    }
                                    $cc->close();
                                    if (!in_array($cid,$c_ids, true)) $c_ids[]=$cid;
                                }
                                $scn->close();

                                // Filter companies if specific companies selected
                                if ($target_comp_type === "selected" && !empty($selected_c_ids)) {
                                    $c_ids = array_values(array_intersect($c_ids, $selected_c_ids));
                                }

                                $sql_ins  = "INSERT INTO plans (teacher_id,company_id,title,plan_date,note,filename,uploaded_by) VALUES (?,?,?,?,?,?,?)";
                                $stmt_ins = $conn->prepare($sql_ins);
                                $count = 0;
                                if (!empty($c_ids)) {
                                    foreach ($c_ids as $c_id) {
                                        $stmt_ins->bind_param("iissssi",$teacher_id,$c_id,$title,$plan_date,$note,$file_new_name,$staff_id);
                                        if ($stmt_ins->execute()) $count++;
                                    }
                                } else {
                                    $czero=0;
                                    $stmt_ins->bind_param("iissssi",$teacher_id,$czero,$title,$plan_date,$note,$file_new_name,$staff_id);
                                    if ($stmt_ins->execute()) $count++;
                                }
                                $stmt_ins->close();

                                // Commit Transaction
                                $conn->commit();

                                add_notification(
                                    $teacher_id,
                                    "\xF0\x9F\x93\x8B เจ้าหน้าที่อัปโหลดแผนการนิเทศให้คุณแล้ว",
                                    sprintf("%s ได้อัปโหลดแผนการนิเทศ \"%s\" ในนามของคุณ (%d สถานประกอบการ)",
                                        $u["fullname"] ?? "เจ้าหน้าที่", $title, $count),
                                    "plan_uploaded",
                                    BASE_URL . "/teacher/plans.php"
                                );

                                // Regenerate CSRF Token
                                $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
                                $csrf_token = $_SESSION["csrf_token"];

                                $msg_type="success"; $msg_title="อัปโหลดสำเร็จ!";
                                $msg_text=sprintf("บันทึกแผน \"%s\" — ครู: %s — %d สถานประกอบการ",
                                    $title, htmlspecialchars($teacher_row["fullname"]), $count);

                            } catch (Exception $e) {
                                // Rollback DB on failure & delete target file to prevent orphan files
                                $conn->rollback();
                                if (is_file($target_file)) @unlink($target_file);
                                error_log("Upload plan error: " . $e->getMessage());
                                $msg_type="error"; $msg_title="เกิดข้อผิดพลาด"; $msg_text="ไม่สามารถบันทึกข้อมูลได้ กรุณาลองใหม่อีกครั้ง";
                            }
                        }
                    }
                }
            }
        }
    }
}

// ── Teachers list with student count ─────────────────────────────────────────
$teachers = [];
$tr = $conn->query("SELECT u.id, u.fullname, u.email, COUNT(DISTINCT s.id) AS student_count FROM users u LEFT JOIN teacher_assignments ta ON u.id=ta.teacher_id LEFT JOIN users s ON s.classroom_id=ta.classroom_id AND s.role='student' WHERE u.role='teacher' GROUP BY u.id ORDER BY u.fullname ASC");
if ($tr) {
    while ($row = $tr->fetch_assoc()) $teachers[] = $row;
}

// ── Latest System Upload Tracker ─────────────────────────────────────────────
$latest_upload = null;
$lu_sql = "SELECT p.id, p.title, p.plan_date, p.uploaded_at, p.filename, p.note, p.teacher_id, p.uploaded_by,
                  u_tch.fullname AS teacher_name, u_tch.email AS teacher_email,
                  COALESCE(u_up.fullname, 'ครูนิเทศก์') AS uploader_name,
                  COALESCE(u_up.role, 'teacher') AS uploader_role,
                  COUNT(DISTINCT p.company_id) AS company_count,
                  GROUP_CONCAT(DISTINCT COALESCE(c.name, 'ทั้งหมด (ส่วนกลาง)') ORDER BY c.name SEPARATOR ', ') AS company_names
           FROM plans p
           JOIN users u_tch ON p.teacher_id = u_tch.id
           LEFT JOIN users u_up ON p.uploaded_by = u_up.id
           LEFT JOIN companies c ON p.company_id = c.id
           GROUP BY p.filename, p.teacher_id, p.title
           ORDER BY p.uploaded_at DESC
           LIMIT 1";
$lu_res = $conn->query($lu_sql);
if ($lu_res && $lu_res->num_rows > 0) {
    $latest_upload = $lu_res->fetch_assoc();
}

// ── Sorting Logic Parameters ──────────────────────────────────────────────────
$allowed_sorts = [
    'uploaded_at' => 'p.uploaded_at',
    'plan_date'   => 'p.plan_date',
    'teacher'     => 'u_tch.fullname',
    'uploader'    => 'uploader_name',
    'title'       => 'p.title'
];
$sort_by  = $_GET["sort_by"] ?? "uploaded_at";
$sort_dir = strtoupper($_GET["sort_dir"] ?? "DESC");
if (!array_key_exists($sort_by, $allowed_sorts)) $sort_by = "uploaded_at";
if (!in_array($sort_dir, ["ASC", "DESC"], true)) $sort_dir = "DESC";

$order_column = $allowed_sorts[$sort_by];
$order_clause = "ORDER BY {$order_column} {$sort_dir}, p.id DESC";

// ── History Scope (my vs all) ────────────────────────────────────────────────
$scope    = in_array($_GET["scope"] ?? "", ["my", "all"], true) ? $_GET["scope"] : "my";
$page     = max(1, (int)($_GET["page"] ?? 1));
$per_page = 15;
$offset   = ($page - 1) * $per_page;

if ($scope === "all") {
    $hc = $conn->query("SELECT COUNT(DISTINCT filename, teacher_id) AS c FROM plans");
    $total_history = (int)($hc ? ($hc->fetch_assoc()["c"] ?? 0) : 0);

    $sql_hist = "
        SELECT p.id, p.title, p.plan_date, p.uploaded_at, p.filename, p.note, p.teacher_id, p.uploaded_by,
               u_tch.fullname AS teacher_name, u_tch.email AS teacher_email,
               COALESCE(u_up.fullname, 'ครูนิเทศก์') AS uploader_name,
               COALESCE(u_up.role, 'teacher') AS uploader_role,
               COUNT(DISTINCT p.company_id) AS company_count,
               GROUP_CONCAT(DISTINCT COALESCE(c.name, 'ทั้งหมด (ส่วนกลาง)') ORDER BY c.name SEPARATOR ', ') AS company_names
        FROM plans p
        JOIN users u_tch ON p.teacher_id = u_tch.id
        LEFT JOIN users u_up ON p.uploaded_by = u_up.id
        LEFT JOIN companies c ON p.company_id = c.id
        GROUP BY p.filename, p.teacher_id, p.title
        {$order_clause}
        LIMIT ? OFFSET ?
    ";
    $hs = $conn->prepare($sql_hist);
    $hs->bind_param("ii", $per_page, $offset);
} else {
    // scope = my
    $hc = $conn->prepare("SELECT COUNT(DISTINCT filename, teacher_id) AS c FROM plans WHERE uploaded_by=?");
    $hc->bind_param("i", $staff_id);
    $hc->execute();
    $total_history = (int)($hc->get_result()->fetch_assoc()["c"] ?? 0);
    $hc->close();

    $sql_hist = "
        SELECT p.id, p.title, p.plan_date, p.uploaded_at, p.filename, p.note, p.teacher_id, p.uploaded_by,
               u_tch.fullname AS teacher_name, u_tch.email AS teacher_email,
               COALESCE(u_up.fullname, 'ครูนิเทศก์') AS uploader_name,
               COALESCE(u_up.role, 'teacher') AS uploader_role,
               COUNT(DISTINCT p.company_id) AS company_count,
               GROUP_CONCAT(DISTINCT COALESCE(c.name, 'ทั้งหมด (ส่วนกลาง)') ORDER BY c.name SEPARATOR ', ') AS company_names
        FROM plans p
        JOIN users u_tch ON p.teacher_id = u_tch.id
        LEFT JOIN users u_up ON p.uploaded_by = u_up.id
        LEFT JOIN companies c ON p.company_id = c.id
        WHERE p.uploaded_by = ?
        GROUP BY p.filename, p.teacher_id, p.title
        {$order_clause}
        LIMIT ? OFFSET ?
    ";
    $hs = $conn->prepare($sql_hist);
    $hs->bind_param("iii", $staff_id, $per_page, $offset);
}
$hs->execute();
$hr = $hs->get_result();
$history = [];
while ($row = $hr->fetch_assoc()) $history[] = $row;
$hs->close();

$total_pages = max(1, (int)ceil($total_history / $per_page));

// Helper function to build column header sorting JS call
function get_sort_js_call(string $colKey, string $currentSortBy, string $currentSortDir): string {
    $nextDir = ($colKey === $currentSortBy && $currentSortDir === 'ASC') ? 'DESC' : 'ASC';
    return "reloadHistoryTable(undefined, '{$colKey}', '{$nextDir}', 1); return false;";
}

// Helper function to get sort icon
function get_sort_icon(string $colKey, string $currentSortBy, string $currentSortDir): string {
    if ($colKey !== $currentSortBy) return '<i class="bi bi-arrow-down-up text-muted ms-1 opacity-40" style="font-size:11px"></i>';
    return $currentSortDir === 'ASC' 
        ? '<i class="bi bi-sort-up-alt text-primary ms-1 fw-bold"></i>' 
        : '<i class="bi bi-sort-down text-primary ms-1 fw-bold"></i>';
}

// ── Function to render history content partial (used for both Initial Render and AJAX) ──
function render_history_partial($history, $total_history, $total_pages, $page, $scope, $sort_by, $sort_dir, $csrf_token, $staff_id, $user_role) {
    ob_start();
    ?>
    <div id="historyTablePartial">
        <div class="upt-card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
          <div class="d-flex align-items-center gap-2">
            <i class="bi bi-clock-history me-1"></i>
            <span>ประวัติการอัปโหลด</span>
            <span class="history-count-badge"><?= $total_history ?> รายการ</span>
          </div>

          <!-- Scope Filter Tabs (AJAX) -->
          <ul class="nav nav-pills nav-pills-upt mb-0">
            <li class="nav-item">
              <a class="nav-link <?= $scope==='my'?'active':'' ?>" href="#" onclick="reloadHistoryTable('my', undefined, undefined, 1); return false;">
                <i class="bi bi-person-fill me-1"></i>คุณอัปโหลด
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link <?= $scope==='all'?'active':'' ?>" href="#" onclick="reloadHistoryTable('all', undefined, undefined, 1); return false;">
                <i class="bi bi-globe me-1"></i>ทั้งหมดในระบบ
              </a>
            </li>
          </ul>
        </div>

        <!-- Sorting Control Bar (AJAX) -->
        <div class="bg-light px-3 py-2 border-bottom d-flex flex-wrap align-items-center justify-content-between gap-2" style="font-size:12.5px">
          <div class="text-muted">
            <i class="bi bi-funnel me-1"></i>เรียงลำดับตาม: <strong class="text-dark"><?= htmlspecialchars([
              'uploaded_at' => 'วันที่อัปโหลด',
              'plan_date'   => 'วันที่แผน',
              'teacher'     => 'ครูนิเทศก์',
              'uploader'    => 'ผู้อัปโหลด',
              'title'       => 'หัวข้อแผน'
            ][$sort_by] ?? 'วันที่อัปโหลด') ?></strong>
            (<?= $sort_dir === 'DESC' ? 'ล่าสุด/มากไปน้อย' : 'เก่าสุด/น้อยไปมาก' ?>)
          </div>
          <div class="d-flex align-items-center gap-2">
            <select class="form-select form-select-sm rounded-pill" style="font-size:12px;width:auto"
                    onchange="var v=this.value.split(':'); reloadHistoryTable(undefined, v[0], v[1], 1);">
              <option value="uploaded_at:DESC" <?= $sort_by==='uploaded_at'&&$sort_dir==='DESC'?'selected':'' ?>>🕒 วันที่อัปโหลด (ล่าสุดก่อน)</option>
              <option value="uploaded_at:ASC" <?= $sort_by==='uploaded_at'&&$sort_dir==='ASC'?'selected':'' ?>>🕒 วันที่อัปโหลด (เก่าสุดก่อน)</option>
              <option value="plan_date:DESC" <?= $sort_by==='plan_date'&&$sort_dir==='DESC'?'selected':'' ?>>📅 วันที่แผน (ใหม่ไปเก่า)</option>
              <option value="plan_date:ASC" <?= $sort_by==='plan_date'&&$sort_dir==='ASC'?'selected':'' ?>>📅 วันที่แผน (เก่าไปใหม่)</option>
              <option value="teacher:ASC" <?= $sort_by==='teacher'&&$sort_dir==='ASC'?'selected':'' ?>>👨‍🏫 ชื่อครู (ก-ฮ)</option>
              <option value="uploader:ASC" <?= $sort_by==='uploader'&&$sort_dir==='ASC'?'selected':'' ?>>👤 ผู้อัปโหลด (ก-ฮ)</option>
              <option value="title:ASC" <?= $sort_by==='title'&&$sort_dir==='ASC'?'selected':'' ?>>📌 หัวข้อแผน (ก-ฮ)</option>
            </select>
          </div>
        </div>

        <div class="p-3">
          <?php if (empty($history)): ?>
          <div class="text-center text-muted py-5">
            <i class="bi bi-inbox display-4 d-block mb-2 opacity-30"></i>
            <p class="mb-0">ยังไม่มีประวัติการอัปโหลดในหมวดนี้</p>
            <p class="small opacity-60">เมื่อมีการอัปโหลดสำเร็จ รายการจะปรากฏที่นี่</p>
          </div>
          <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-2" style="font-size:13px">
              <thead>
                <tr class="table-light">
                  <th class="sortable-th">
                    <a href="#" onclick="<?= get_sort_js_call('teacher', $sort_by, $sort_dir) ?>">
                      ครูนิเทศก์ <?= get_sort_icon('teacher', $sort_by, $sort_dir) ?>
                    </a>
                  </th>
                  <th class="sortable-th">
                    <a href="#" onclick="<?= get_sort_js_call('uploader', $sort_by, $sort_dir) ?>">
                      ผู้อัปโหลด <?= get_sort_icon('uploader', $sort_by, $sort_dir) ?>
                    </a>
                  </th>
                  <th class="sortable-th">
                    <a href="#" onclick="<?= get_sort_js_call('title', $sort_by, $sort_dir) ?>">
                      หัวข้อ <?= get_sort_icon('title', $sort_by, $sort_dir) ?>
                    </a>
                  </th>
                  <th class="text-center sortable-th">
                    <a href="#" onclick="<?= get_sort_js_call('uploaded_at', $sort_by, $sort_dir) ?>">
                      วันที่อัปโหลด <?= get_sort_icon('uploaded_at', $sort_by, $sort_dir) ?>
                    </a>
                  </th>
                  <th class="text-center" style="width:85px">รายละเอียด</th>
                  <th class="text-center" style="width:48px">ลบ</th>
                </tr>
              </thead>
              <tbody>
              <?php foreach ($history as $h): ?>
              <tr>
                <td>
                  <span class="fw-semibold text-dark d-block" style="line-height:1.2"><?= htmlspecialchars($h['teacher_name']) ?></span>
                </td>
                <td>
                  <span class="badge bg-light text-dark border px-2 py-1" style="font-size:11.5px">
                    <i class="bi bi-person me-1 text-primary"></i><?= htmlspecialchars($h['uploader_name']) ?>
                  </span>
                </td>
                <td>
                  <span class="text-truncate d-block" style="max-width:145px"
                        title="<?= htmlspecialchars($h['title']) ?>">
                    <?= htmlspecialchars(mb_strimwidth($h['title'],0,30,'…')) ?>
                  </span>
                </td>
                <td class="text-center text-muted small">
                  <?= date('d/m/Y', strtotime($h['plan_date'])) ?><br>
                  <span style="font-size:10.5px;opacity:.7"><?= date('H:i', strtotime($h['uploaded_at'])) ?></span>
                </td>
                <td class="text-center">
                  <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-2 py-0" style="font-size:12px"
                          onclick='openDetailsModal(<?= json_encode($h, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>)'>
                    <i class="bi bi-eye"></i> ดู
                  </button>
                </td>
                <td class="text-center">
                  <?php if ($user_role === 'admin' || (int)$h['uploaded_by'] === $staff_id): ?>
                  <form method="POST" class="d-inline">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <input type="hidden" name="delete_plan_id" value="<?= (int)$h['id'] ?>">
                    <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-2 py-0"
                            style="font-size:12px"
                            onclick="confirmDelete(this,'<?= htmlspecialchars(addslashes(mb_strimwidth($h['title'],0,40,'…'))) ?>')">
                      <i class="bi bi-trash3"></i>
                    </button>
                  </form>
                  <?php else: ?>
                  <span class="text-muted small" title="ไม่มีสิทธิ์ลบ">—</span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <?php if ($total_pages>1): ?>
          <div class="d-flex justify-content-center mt-3">
            <nav><ul class="pagination pagination-sm mb-0">
              <?php for ($p=1;$p<=$total_pages;$p++): ?>
              <li class="page-item <?= $p===$page?'active':'' ?>">
                <a class="page-link rounded-pill mx-1" href="#"
                   onclick="reloadHistoryTable(undefined, undefined, undefined, <?= $p ?>); return false;"
                   style="min-width:32px;text-align:center"><?= $p ?></a>
              </li>
              <?php endfor; ?>
            </ul></nav>
          </div>
          <?php endif; endif; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// ── Handle AJAX request if requested ─────────────────────────────────────────
if (isset($_GET["ajax"])) {
    header("Content-Type: application/json; charset=utf-8");
    $html_out = render_history_partial($history, $total_history, $total_pages, $page, $scope, $sort_by, $sort_dir, $csrf_token, $staff_id, $u["role"] ?? "staff");
    echo json_encode([
        "success"       => true,
        "html"          => $html_out,
        "total_history" => $total_history,
        "total_pages"   => $total_pages,
        "page"          => $page,
        "scope"         => $scope,
        "sort_by"       => $sort_by,
        "sort_dir"      => $sort_dir
    ]);
    exit;
}

$page_title  = "อัปโหลดงานแทนครู";
$hide_welcome = true;
include __DIR__ . "/../includes/header.php";
?><link rel="stylesheet" href="../includes/teacher_style.css">
<link rel="stylesheet" href="../includes/staff_style.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
.upt-hero{background:linear-gradient(135deg,#4f46e5,#7c3aed);border-radius:20px;padding:28px 32px;color:#fff;margin-bottom:24px;box-shadow:0 8px 32px rgba(79,70,229,.25)}
.upt-card{background:#fff;border-radius:16px;box-shadow:0 4px 24px rgba(79,70,229,.08);border:1px solid rgba(79,70,229,.1);overflow:hidden}
.upt-card-header{background:linear-gradient(90deg,#f8f7ff,#ede9fe);padding:14px 22px;border-bottom:1px solid rgba(79,70,229,.1);font-weight:700;color:#4f46e5;font-size:.95rem}

.latest-upload-card{background:linear-gradient(135deg,#eef2ff 0%,#e0e7ff 100%);border:1.5px solid #c7d2fe;border-radius:14px;padding:16px 20px;margin-bottom:20px;box-shadow:0 4px 16px rgba(79,70,229,.06)}
.latest-badge{background:#4f46e5;color:#fff;font-size:11px;padding:3px 10px;border-radius:999px;font-weight:700;letter-spacing:.3px}
.uploader-chip{display:inline-flex;align-items:center;gap:5px;background:#fff;border:1px solid #c7d2fe;color:#4338ca;padding:2px 10px;border-radius:999px;font-size:12px;font-weight:600}

.teacher-list-wrap{max-height:280px;overflow-y:auto;scroll-behavior:smooth}
.teacher-list-wrap::-webkit-scrollbar{width:4px}
.teacher-list-wrap::-webkit-scrollbar-thumb{background:#c7d2fe;border-radius:4px}
.teacher-item{display:flex;align-items:center;gap:12px;padding:10px 14px;border-radius:12px;cursor:pointer;border:2px solid transparent;transition:all .18s;background:#f8f9fa;margin-bottom:6px;user-select:none}
.teacher-item:hover{border-color:#818cf8;background:#ede9fe}
.teacher-item.active{border-color:#4f46e5;background:#eef2ff;box-shadow:0 2px 10px rgba(79,70,229,.18)}
.teacher-avatar{width:42px;height:42px;border-radius:50%;flex-shrink:0;background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:17px}
.upload-zone{border:2.5px dashed #c7d2fe;border-radius:16px;padding:36px 20px;text-align:center;background:#f8f7ff;cursor:pointer;transition:all .22s}
.upload-zone:hover,.upload-zone.dragover{border-color:#4f46e5;background:#eef2ff}
.upload-zone input[type=file]{display:none}
.upload-icon{font-size:2.8rem;color:#a5b4fc;transition:transform .28s,color .28s}
.upload-zone:hover .upload-icon{transform:translateY(-5px);color:#4f46e5}
.teacher-search{border:1.5px solid #c7d2fe;border-radius:10px;padding:9px 14px;width:100%;font-size:14px;outline:none;margin-bottom:10px;transition:border .18s}
.teacher-search:focus{border-color:#4f46e5;box-shadow:0 0 0 3px rgba(79,70,229,.1)}
.stu-badge{font-size:11px;padding:2px 8px;background:#e0e7ff;color:#4338ca;border-radius:999px;font-weight:600}
.history-count-badge{background:#eef2ff;color:#4f46e5;font-size:12px;padding:3px 10px;border-radius:999px;font-weight:600}
.ffl{font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#64748b;margin-bottom:6px}
.form-control-upt{border:1.5px solid #e2e8f0;border-radius:10px;padding:10px 14px;font-size:14px;transition:border .18s;width:100%}
.form-control-upt:focus{border-color:#4f46e5;box-shadow:0 0 0 3px rgba(79,70,229,.1);outline:none}

.nav-pills-upt .nav-link{border-radius:999px;padding:5px 14px;font-size:12.5px;font-weight:600;color:#64748b;transition:all .2s}
.nav-pills-upt .nav-link.active{background:#4f46e5;color:#fff;box-shadow:0 2px 8px rgba(79,70,229,.25)}

/* Clickable table headers */
th.sortable-th a { color:#334155; text-decoration:none; display:inline-flex; align-items:center; transition:color .18s; }
th.sortable-th a:hover { color:#4f46e5; }

/* Table Reload Smooth Overlay */
#historyContainer { transition: opacity 0.22s ease; }

@keyframes fadeIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
.upt-hero,.upt-card{animation:fadeIn .4s ease both}
</style>

<div class="container py-4" style="max-width:1140px">

  <!-- ── Top Hero ── -->
  <div class="upt-hero d-flex flex-wrap align-items-center justify-content-between gap-3">
    <div>
      <h1 class="fw-bold mb-1" style="font-size:1.55rem;letter-spacing:-.4px">
        <i class="bi bi-cloud-arrow-up-fill me-2"></i>อัปโหลดแผนการนิเทศแทนครู
      </h1>
      <p class="mb-0 small" style="opacity:.85">เจ้าหน้าที่อัปโหลดเอกสารแผนการนิเทศในนามของครูนิเทศก์ — ไฟล์จะปรากฏในหน้าแผนของครูทันที</p>
    </div>
    <a href="../roles/staff.php" class="btn btn-light rounded-pill px-4 fw-semibold shadow-sm">
      <i class="bi bi-arrow-left me-1"></i> กลับแดชบอร์ด
    </a>
  </div>

  <!-- ── Latest Upload Highlight Widget ── -->
  <?php if ($latest_upload): ?>
  <div class="latest-upload-card">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
      <div class="d-flex align-items-center gap-2">
        <span class="latest-badge"><i class="bi bi-lightning-charge-fill me-1"></i>อัปโหลดล่าสุดในระบบ</span>
        <span class="text-muted small">
          <i class="bi bi-clock me-1"></i><?= date("d/m/Y H:i", strtotime($latest_upload["uploaded_at"])) ?> น.
        </span>
      </div>
      <div class="uploader-chip">
        <i class="bi bi-person-circle"></i>
        <span>อัปโดย: <?= htmlspecialchars($latest_upload["uploader_name"]) ?></span>
        <span class="badge" style="font-size:10px;background:#eef2ff !important;color:#3730a3 !important;border:1px solid #c7d2fe !important"><?= htmlspecialchars(strtoupper($latest_upload["uploader_role"])) ?></span>
      </div>
    </div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
      <div>
        <h6 class="fw-bold text-dark mb-1">
          <i class="bi bi-file-earmark-text text-primary me-1"></i><?= htmlspecialchars($latest_upload["title"]) ?>
        </h6>
        <div class="small text-secondary">
          <span><i class="bi bi-person-badge me-1"></i>ครูนิเทศก์: <strong><?= htmlspecialchars($latest_upload["teacher_name"]) ?></strong></span>
          <span class="ms-3"><i class="bi bi-building me-1"></i>สถานประกอบการ: <strong><?= (int)$latest_upload["company_count"] ?> แห่ง</strong></span>
        </div>
      </div>
      <div class="d-flex gap-2">
        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3"
                onclick='openDetailsModal(<?= json_encode($latest_upload, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>)'>
          <i class="bi bi-eye me-1"></i>ดูรายละเอียด
        </button>
        <?php if (!empty($latest_upload["filename"]) && is_file(__DIR__ . "/../uploads/plans/" . basename($latest_upload["filename"]))): ?>
        <a href="../uploads/plans/<?= htmlspecialchars(basename($latest_upload["filename"])) ?>" target="_blank"
           class="btn btn-sm btn-primary rounded-pill px-3 shadow-sm" style="background:#4f46e5;border:none">
          <i class="bi bi-filetype-pdf me-1"></i>เปิดไฟล์ PDF
        </a>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <div class="row g-4">
    <!-- ── Left Column: Upload Form ── -->
    <div class="col-lg-5">
      <div class="upt-card">
        <div class="upt-card-header"><i class="bi bi-upload me-2"></i>ฟอร์มอัปโหลด</div>
        <div class="p-4">
          <form method="POST" enctype="multipart/form-data" id="uploadForm" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="hidden" name="submit_plan" value="1">
            <input type="hidden" name="teacher_id" id="selected_teacher_id" value="">

            <!-- 1. Teacher Selection -->
            <div class="mb-3">
              <div class="ffl"><i class="bi bi-person-badge me-1"></i>เลือกครูนิเทศก์ <span class="text-danger">*</span></div>
              <input type="text" class="teacher-search" id="teacherSearch" placeholder="🔍 พิมพ์ชื่อครูเพื่อค้นหา..." autocomplete="off">
              <div class="teacher-list-wrap" id="teacherList">
                <?php if (empty($teachers)): ?>
                <div class="text-center text-muted py-4 small">
                  <i class="bi bi-person-x display-6 d-block mb-1 opacity-40"></i>ไม่พบครูในระบบ
                </div>
                <?php else: foreach ($teachers as $t): ?>
                <div class="teacher-item"
                     data-id="<?= (int)$t['id'] ?>"
                     data-name="<?= htmlspecialchars($t['fullname'], ENT_QUOTES) ?>"
                     onclick="selectTeacher(this)">
                  <div class="teacher-avatar"><?= mb_substr($t['fullname'],0,1) ?></div>
                  <div class="flex-grow-1 overflow-hidden">
                    <div class="fw-semibold text-dark" style="font-size:13.5px;line-height:1.3"><?= htmlspecialchars($t['fullname']) ?></div>
                    <div class="text-muted text-truncate" style="font-size:11.5px"><?= htmlspecialchars($t['email']??'') ?></div>
                  </div>
                  <div class="d-flex flex-column align-items-end gap-1 flex-shrink-0">
                    <span class="stu-badge"><?= (int)$t['student_count'] ?> นร.</span>
                    <i class="bi bi-check-circle-fill check-icon d-none" style="color:#4f46e5;font-size:18px"></i>
                  </div>
                </div>
                <?php endforeach; endif; ?>
              </div>
              <div id="selectedBadge" class="mt-2 d-none">
                <span class="badge rounded-pill px-3 py-2" style="background:#4f46e5;font-size:12.5px">
                  <i class="bi bi-person-check me-1"></i><span id="selectedTeacherName"></span>
                </span>
              </div>
            </div>

            <!-- 2. Multi-Company Selection Section (Dynamic per Teacher) -->
            <div class="mb-3 d-none" id="companySelectionSection">
              <div class="ffl"><i class="bi bi-buildings me-1"></i>สถานประกอบการที่จะรับแผน</div>
              
              <div class="p-3 border rounded-3 bg-light">
                <div class="form-check mb-2">
                  <input class="form-check-input" type="radio" name="target_companies_type" id="targetAll" value="all" checked onchange="toggleCompanyCheckboxes()">
                  <label class="form-check-label fw-semibold text-dark small" for="targetAll">
                    🔘 ทั้งหมดในความดูแลของครู (<span id="allCompaniesCount">0</span> แห่ง)
                  </label>
                </div>
                
                <div class="form-check mb-2">
                  <input class="form-check-input" type="radio" name="target_companies_type" id="targetSelected" value="selected" onchange="toggleCompanyCheckboxes()">
                  <label class="form-check-label fw-semibold text-dark small" for="targetSelected">
                    🏢 เลือกเฉพาะสถานประกอบการที่ต้องการ (เลือกได้หลายตัวเลือกพร้อมกัน)
                  </label>
                </div>

                <div id="companyCheckboxesWrap" class="mt-2 pt-2 border-top d-none">
                  <div class="d-flex justify-content-between align-items-center mb-2 pb-1">
                    <small class="fw-bold text-secondary" style="font-size:11.5px">รายการสถานประกอบการ:</small>
                    <div>
                      <button type="button" class="btn btn-sm btn-link p-0 text-decoration-none me-2" onclick="selectAllCompanies(true)" style="font-size:11.5px;color:#4f46e5">เลือกทั้งหมด</button>
                      <button type="button" class="btn btn-sm btn-link p-0 text-decoration-none text-muted" onclick="selectAllCompanies(false)" style="font-size:11.5px">ยกเลิกทั้งหมด</button>
                    </div>
                  </div>
                  <div id="companyCheckboxesList" style="max-height:170px;overflow-y:auto;padding-right:4px">
                    <!-- Dynamic Checkboxes loaded via AJAX -->
                  </div>
                </div>
              </div>
            </div>

            <hr class="my-3">
            <div class="mb-3">
              <div class="ffl"><i class="bi bi-pencil me-1"></i>หัวข้อแผนการนิเทศ</div>
              <input type="text" name="plan_title" class="form-control-upt"
                     placeholder="ปล่อยว่างเพื่อใช้ชื่ออัตโนมัติ" maxlength="255"
                     value="<?= htmlspecialchars($_POST['plan_title']??'') ?>">
            </div>
            <div class="mb-3">
              <div class="ffl"><i class="bi bi-calendar-event me-1"></i>วันที่แผน</div>
              <input type="date" name="plan_date" class="form-control-upt"
                     value="<?= htmlspecialchars($_POST['plan_date']??date('Y-m-d')) ?>">
            </div>
            <hr class="my-3">
            <div class="mb-4">
              <div class="ffl"><i class="bi bi-file-earmark-pdf me-1 text-danger"></i>ไฟล์แผนนิเทศ (PDF) <span class="text-danger">*</span></div>
              <div class="upload-zone" id="uploadZone" onclick="document.getElementById('plan_file').click()">
                <div class="upload-icon mb-2"><i class="bi bi-cloud-arrow-up" id="uploadIconI"></i></div>
                <div class="fw-semibold text-dark small mb-1">คลิกหรือลากไฟล์มาวางที่นี่</div>
                <div class="text-muted" style="font-size:12px">รองรับเฉพาะ .pdf ขนาดไม่เกิน 20 MB</div>
                <div id="fileInfo" class="mt-2 d-none">
                  <span class="badge bg-success px-3 py-2 rounded-pill" style="font-size:12px">
                    <i class="bi bi-file-earmark-check me-1"></i><span id="fileName"></span>
                    <span class="ms-1 opacity-75" id="fileSize"></span>
                  </span>
                </div>
                <input type="file" name="plan_file" id="plan_file" accept=".pdf,application/pdf">
              </div>
            </div>
            <button type="button" id="submitBtn" onclick="confirmUpload()"
              class="btn btn-lg fw-bold w-100 rounded-pill py-3 shadow-sm"
              style="background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff;border:none">
              <i class="bi bi-cloud-arrow-up-fill me-2"></i>บันทึกและอัปโหลดแผน
            </button>
            <div class="text-center mt-2">
              <small class="text-muted"><i class="bi bi-shield-check me-1 text-success"></i>ไฟล์จะปรากฏในหน้าแผนของครูทันที พร้อมแจ้งเตือน</small>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- ── Right Column: Upload History Container (AJAX Target) ── -->
    <div class="col-lg-7">
      <div class="upt-card" id="historyContainer" style="min-height:450px">
        <?= render_history_partial($history, $total_history, $total_pages, $page, $scope, $sort_by, $sort_dir, $csrf_token, $staff_id, $u["role"] ?? "staff") ?>
      </div>
    </div>
  </div>
</div>

<?php if ($msg_type!==''): ?>
<script>
document.addEventListener('DOMContentLoaded',function(){
  Swal.fire({title:<?= json_encode($msg_title) ?>,html:<?= json_encode(htmlspecialchars_decode($msg_text)) ?>,icon:<?= json_encode($msg_type) ?>,confirmButtonText:'ตกลง',confirmButtonColor:'#4f46e5'});
});
</script>
<?php endif; ?>

<script>
/* Current History Filter State */
var currentScope   = <?= json_encode($scope) ?>;
var currentSortBy  = <?= json_encode($sort_by) ?>;
var currentSortDir = <?= json_encode($sort_dir) ?>;
var currentPage    = <?= (int)$page ?>;
var loadedTeacherCompanies = [];

/**
 * Select Teacher and Load associated Companies via AJAX
 */
function selectTeacher(el){
  var id = el.getAttribute('data-id'), name = el.getAttribute('data-name');
  document.getElementById('selected_teacher_id').value = id;
  document.getElementById('selectedTeacherName').textContent = name;
  document.getElementById('selectedBadge').classList.remove('d-none');

  document.querySelectorAll('.teacher-item').forEach(function(i){
    i.classList.remove('active');
    i.querySelector('.check-icon').classList.add('d-none');
  });
  el.classList.add('active');
  el.querySelector('.check-icon').classList.remove('d-none');
  el.scrollIntoView({behavior:'smooth',block:'nearest'});

  // Load Companies for this teacher via AJAX
  loadTeacherCompanies(id);
}

function loadTeacherCompanies(teacherId) {
  var section = document.getElementById('companySelectionSection');
  var countSpan = document.getElementById('allCompaniesCount');
  var listWrap = document.getElementById('companyCheckboxesList');

  if (!section) return;

  fetch('?get_teacher_companies=' + encodeURIComponent(teacherId))
    .then(function(res) { return res.json(); })
    .then(function(data) {
      if (data.success) {
        loadedTeacherCompanies = data.companies || [];
        countSpan.textContent = loadedTeacherCompanies.length;

        var html = '';
        if (loadedTeacherCompanies.length === 0) {
          html = '<div class="text-muted small py-2"><i class="bi bi-info-circle me-1"></i>ไม่พบสถานประกอบการเฉพาะ (จะลงทะเบียนแบบส่วนกลาง)</div>';
        } else {
          loadedTeacherCompanies.forEach(function(c) {
            html += '<div class="form-check mb-1">' +
                      '<input class="form-check-input comp-chk" type="checkbox" name="selected_company_ids[]" value="' + c.id + '" id="chk_comp_' + c.id + '" checked>' +
                      '<label class="form-check-label small text-dark d-flex align-items-center justify-content-between" for="chk_comp_' + c.id + '">' +
                        '<span class="text-truncate me-1" style="max-width:220px">' + escapeHtml(c.name) + '</span>' +
                        '<span class="badge bg-indigo-subtle text-primary border" style="font-size:10px">' + c.student_count + ' นร.</span>' +
                      '</label>' +
                    '</div>';
          });
        }
        listWrap.innerHTML = html;
        section.classList.remove('d-none');
        document.getElementById('targetAll').checked = true;
        toggleCompanyCheckboxes();
      }
    })
    .catch(function(err) { console.error('Load companies error:', err); });
}

function toggleCompanyCheckboxes() {
  var isSelected = document.getElementById('targetSelected').checked;
  var wrap = document.getElementById('companyCheckboxesWrap');
  if (wrap) {
    if (isSelected) {
      wrap.classList.remove('d-none');
    } else {
      wrap.classList.add('d-none');
    }
  }
}

function selectAllCompanies(select) {
  document.querySelectorAll('.comp-chk').forEach(function(chk) {
    chk.checked = select;
  });
}

/**
 * Reload History Table smoothly via AJAX (No Full Page Reload!)
 */
function reloadHistoryTable(scope, sort_by, sort_dir, page) {
  if (scope !== undefined) currentScope = scope;
  if (sort_by !== undefined) currentSortBy = sort_by;
  if (sort_dir !== undefined) currentSortDir = sort_dir;
  if (page !== undefined) currentPage = page;

  var container = document.getElementById('historyContainer');
  if (!container) return;

  container.style.opacity = '0.45';
  container.style.pointerEvents = 'none';

  var url = '?ajax=1' +
            '&scope=' + encodeURIComponent(currentScope) +
            '&sort_by=' + encodeURIComponent(currentSortBy) +
            '&sort_dir=' + encodeURIComponent(currentSortDir) +
            '&page=' + encodeURIComponent(currentPage);

  fetch(url)
    .then(function(res) { return res.json(); })
    .then(function(data) {
      if (data.success && data.html) {
        container.innerHTML = data.html;
        
        var cleanUrl = '?scope=' + encodeURIComponent(currentScope) +
                       '&sort_by=' + encodeURIComponent(currentSortBy) +
                       '&sort_dir=' + encodeURIComponent(currentSortDir) +
                       '&page=' + encodeURIComponent(currentPage);
        if (window.history && window.history.replaceState) {
          window.history.replaceState({}, '', cleanUrl);
        }
      }
    })
    .catch(function(err) { console.error('History AJAX error:', err); })
    .finally(function() {
      container.style.opacity = '1';
      container.style.pointerEvents = 'auto';
    });
}

document.getElementById('teacherSearch').addEventListener('input',function(){
  var q=this.value.toLowerCase().trim();
  document.querySelectorAll('.teacher-item').forEach(function(el){
    el.style.display=(!q||el.getAttribute('data-name').toLowerCase().includes(q))?'':'none';
  });
});

function handleFile(file){
  if(!file)return;
  if(file.size>20*1024*1024){Swal.fire({title:'ไฟล์ใหญ่เกิน 20 MB',icon:'error',confirmButtonColor:'#4f46e5'});document.getElementById('plan_file').value='';return;}
  if(file.name.split('.').pop().toLowerCase()!=='pdf'){Swal.fire({title:'อนุญาตเฉพาะ .pdf',icon:'error',confirmButtonColor:'#4f46e5'});document.getElementById('plan_file').value='';return;}
  document.getElementById('fileName').textContent=file.name;
  document.getElementById('fileSize').textContent='('+(file.size/1024/1024).toFixed(2)+' MB)';
  document.getElementById('fileInfo').classList.remove('d-none');
  document.getElementById('uploadIconI').className='bi bi-file-earmark-check-fill';
  document.getElementById('uploadZone').style.cssText='border-color:#22c55e;background:#f0fdf4';
}

document.getElementById('plan_file').addEventListener('change',function(){if(this.files.length>0)handleFile(this.files[0]);});

(function(){
  var z=document.getElementById('uploadZone');
  ['dragenter','dragover'].forEach(function(e){z.addEventListener(e,function(ev){ev.preventDefault();z.classList.add('dragover');});});
  z.addEventListener('dragleave',function(){z.classList.remove('dragover');});
  z.addEventListener('drop',function(ev){
    ev.preventDefault();z.classList.remove('dragover');
    var f=ev.dataTransfer.files[0];
    if(f){try{var dt=new DataTransfer();dt.items.add(f);document.getElementById('plan_file').files=dt.files;}catch(e){}handleFile(f);}
  });
})();

function confirmUpload(){
  var tId=document.getElementById('selected_teacher_id').value;
  var fInp=document.getElementById('plan_file');
  var tName=document.getElementById('selectedTeacherName').textContent;
  var title=document.querySelector('[name=plan_title]').value.trim()||'(ชื่ออัตโนมัติ)';
  
  if(!tId){Swal.fire({title:'กรุณาเลือกครูนิเทศก์',icon:'warning',confirmButtonColor:'#4f46e5'});return;}
  if(!fInp.files.length){Swal.fire({title:'กรุณาเลือกไฟล์ PDF',icon:'warning',confirmButtonColor:'#4f46e5'});return;}

  var isSelected = document.getElementById('targetSelected').checked;
  var checkedBoxes = document.querySelectorAll('.comp-chk:checked');
  if (isSelected && checkedBoxes.length === 0) {
    Swal.fire({title:'กรุณาเลือกอย่างน้อย 1 สถานประกอบการ',icon:'warning',confirmButtonColor:'#4f46e5'});
    return;
  }

  var targetText = isSelected ? ('เลือกเฉพาะ ' + checkedBoxes.length + ' สถานประกอบการ') : 'กระจายทุกสถานประกอบการในความดูแล';

  Swal.fire({
    title:'ยืนยันการอัปโหลด',
    html:'<table class="table table-sm table-borderless text-start mb-0" style="font-size:13px"><tr><th class="text-muted pe-3">ครูนิเทศก์</th><td><strong>'+tName+'</strong></td></tr><tr><th class="text-muted">หัวข้อ</th><td>'+title+'</td></tr><tr><th class="text-muted">การกระจาย</th><td>'+targetText+'</td></tr><tr><th class="text-muted">ไฟล์</th><td>'+fInp.files[0].name+'</td></tr></table>',
    icon:'question',showCancelButton:true,
    confirmButtonText:'<i class="bi bi-cloud-arrow-up-fill me-1"></i>ยืนยันอัปโหลด',
    cancelButtonText:'ยกเลิก',confirmButtonColor:'#4f46e5',cancelButtonColor:'#6c757d'
  }).then(function(r){
    if(r.isConfirmed){
      var btn=document.getElementById('submitBtn');
      btn.disabled=true;btn.innerHTML='<span class="spinner-border spinner-border-sm me-2"></span>กำลังอัปโหลด...';
      document.getElementById('uploadForm').submit();
    }
  });
}

function confirmDelete(btn,title){
  Swal.fire({
    title:'ยืนยันการลบ?',
    html:'ต้องการลบแผน <strong>"'+title+'"</strong>?<br><small class="text-danger">ไฟล์จะถูกลบออกจากเซิร์ฟเวอร์ด้วย</small>',
    icon:'warning',showCancelButton:true,
    confirmButtonColor:'#dc3545',cancelButtonColor:'#6c757d',
    confirmButtonText:'<i class="bi bi-trash3 me-1"></i>ลบ',cancelButtonText:'ยกเลิก'
  }).then(function(r){if(r.isConfirmed)btn.closest('form').submit();});
}

/* ── Interactive View Details Modal ── */
function openDetailsModal(item) {
  if (!item) return;

  var comps = item.company_names ? item.company_names.split(', ') : [];
  var compBadges = comps.map(function(c){
    return '<span class="badge px-2 py-1 me-1 mb-1" style="font-size:11.5px;background:#eef2ff !important;color:#3730a3 !important;border:1px solid #c7d2fe !important;font-weight:600"><i class="bi bi-building text-primary me-1"></i>' + escapeHtml(c) + '</span>';
  }).join('');

  var pdfButton = item.filename ?
    '<a href="../uploads/plans/' + encodeURIComponent(item.filename) + '" target="_blank" class="btn btn-danger btn-sm rounded-pill px-4 fw-bold shadow-sm">' +
      '<i class="bi bi-filetype-pdf me-1"></i>เปิดไฟล์ PDF' +
    '</a>' :
    '<span class="text-muted small"><i class="bi bi-file-x me-1"></i>ไม่มีไฟล์</span>';

  var html = [
    '<div class="text-start" style="font-size:13.5px">',
      '<div class="p-3 mb-3 rounded-3" style="background:#f8f7ff;border:1px solid #e0e7ff">',
        '<div class="fw-bold text-primary mb-1" style="font-size:15px"><i class="bi bi-file-earmark-text me-1"></i>' + escapeHtml(item.title) + '</div>',
        '<div class="text-muted small"><i class="bi bi-clock me-1"></i>อัปโหลดเมื่อ: ' + escapeHtml(item.uploaded_at || '') + '</div>',
      '</div>',

      '<table class="table table-sm table-borderless mb-2 align-middle">',
        '<tr>',
          '<th class="text-secondary" style="width:38%"><i class="bi bi-person-badge me-1"></i>ครูผู้รับเอกสาร:</th>',
          '<td><strong class="text-dark">' + escapeHtml(item.teacher_name || '') + '</strong> <br><small class="text-muted">' + escapeHtml(item.teacher_email || '') + '</small></td>',
        '</tr>',
        '<tr>',
          '<th class="text-secondary"><i class="bi bi-person-circle me-1"></i>ผู้อัปโหลดเอกสาร:</th>',
          '<td><span class="badge bg-primary-subtle text-primary border px-2 py-1"><i class="bi bi-shield-check me-1"></i>' + escapeHtml(item.uploader_name || 'ครูนิเทศก์') + ' (' + escapeHtml((item.uploader_role||'').toUpperCase()) + ')</span></td>',
        '</tr>',
        '<tr>',
          '<th class="text-secondary"><i class="bi bi-calendar-event me-1"></i>วันที่ของแผน:</th>',
          '<td>' + escapeHtml(item.plan_date || '') + '</td>',
        '</tr>',
        '<tr>',
          '<th class="text-secondary"><i class="bi bi-info-circle me-1"></i>หมายเหตุ:</th>',
          '<td><small class="text-muted">' + escapeHtml(item.note || '—') + '</small></td>',
        '</tr>',
      '</table>',

      '<div class="mb-3">',
        '<div class="fw-semibold text-secondary mb-1" style="font-size:12px;text-transform:uppercase"><i class="bi bi-buildings me-1"></i>สถานประกอบการที่ได้รับแผน (' + (item.company_count || 0) + ' แห่ง):</div>',
        '<div>' + (compBadges || '<span class="text-muted small">ส่วนกลาง</span>') + '</div>',
      '</div>',

      '<div class="text-center pt-2 border-top">',
        pdfButton,
      '</div>',
    '</div>'
  ].join('');

  Swal.fire({
    title: '<i class="bi bi-journal-check text-primary me-2"></i>รายละเอียดแผนการนิเทศ',
    html: html,
    showCloseButton: true,
    showConfirmButton: false,
    width: '560px'
  });
}

function escapeHtml(str) {
  if (!str) return '';
  return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>