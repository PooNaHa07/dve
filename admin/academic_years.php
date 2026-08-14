<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php';
require_login();
require_role(['admin']);

// Ensure table exists via helper
$active_ay = get_active_academic_year();

$msg = '';
$error = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $year = trim($_POST['year'] ?? '');
        $term = trim($_POST['term'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
        $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if (empty($year) || empty($term)) {
            $error = 'กรุณากรอกปีการศึกษาและภาคเรียน';
        } else {
            if (empty($title)) {
                $title = "ปีการศึกษา {$year} ภาคเรียนที่ {$term}";
            }

            if ($action === 'add') {
                if ($is_active == 1) {
                    $conn->query("UPDATE academic_years SET is_active = 0");
                }
                $stmt = $conn->prepare("INSERT INTO academic_years (year, term, title, start_date, end_date, is_active) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssssi", $year, $term, $title, $start_date, $end_date, $is_active);
                if ($stmt->execute()) {
                    log_audit('ADD_ACADEMIC_YEAR', "เพิ่มปีการศึกษาใหม่: {$title}");
                    $msg = 'เพิ่มปีการศึกษาใหม่เรียบร้อยแล้ว';
                } else {
                    $error = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล';
                }
                $stmt->close();
            } else {
                $id = (int)($_POST['id'] ?? 0);
                if ($is_active == 1) {
                    $conn->query("UPDATE academic_years SET is_active = 0");
                }
                $stmt = $conn->prepare("UPDATE academic_years SET year = ?, term = ?, title = ?, start_date = ?, end_date = ?, is_active = ? WHERE id = ?");
                $stmt->bind_param("sssssii", $year, $term, $title, $start_date, $end_date, $is_active, $id);
                if ($stmt->execute()) {
                    log_audit('EDIT_ACADEMIC_YEAR', "แก้ไขปีการศึกษา ID #{$id}: {$title}");
                    $msg = 'แก้ไขข้อมูลปีการศึกษาเรียบร้อยแล้ว';
                } else {
                    $error = 'เกิดข้อผิดพลาดในการอัปเดตข้อมูล';
                }
                $stmt->close();
            }
        }
    } elseif ($action === 'activate') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $conn->query("UPDATE academic_years SET is_active = 0");
            $stmt = $conn->prepare("UPDATE academic_years SET is_active = 1 WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                log_audit('ACTIVATE_ACADEMIC_YEAR', "เปิดใช้งานปีการศึกษา ID #{$id}");
                $msg = 'ตั้งค่าปีการศึกษาปัจจุบันเรียบร้อยแล้ว';
            }
            $stmt->close();
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $conn->prepare("DELETE FROM academic_years WHERE id = ? AND is_active = 0");
            $stmt->bind_param("i", $id);
            if ($stmt->execute() && $conn->affected_rows > 0) {
                log_audit('DELETE_ACADEMIC_YEAR', "ลบปีการศึกษา ID #{$id}");
                $msg = 'ลบข้อมูลปีการศึกษาเรียบร้อยแล้ว';
            } else {
                $error = 'ไม่สามารถลบปีการศึกษาที่กำลังใช้งานอยู่ได้';
            }
            $stmt->close();
        }
    }
}

// Fetch all academic years
$years_list = [];
$res = $conn->query("SELECT * FROM academic_years ORDER BY year DESC, term DESC");
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $years_list[] = $r;
    }
}

include __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/admin-premium.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
.ay-card {
    background: #fff;
    border-radius: 1.25rem;
    border: 1px solid #f1f5f9;
    box-shadow: 0 4px 16px rgba(0,0,0,0.04);
    padding: 1.5rem;
    transition: all 0.3s ease;
}
.ay-card.active-ay {
    border-color: #6366f1;
    background: linear-gradient(135deg, #ffffff 0%, #f5f3ff 100%);
    box-shadow: 0 8px 24px rgba(99, 102, 241, 0.15);
}
.badge-active-year {
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
    color: #fff;
    font-weight: 700;
    padding: 0.4rem 0.8rem;
    border-radius: 50rem;
    font-size: 0.78rem;
}
</style>

<div class="container admin-content-wrapper pb-5">
    <div class="admin-header-section mb-4">
        <div>
            <h2 class="admin-header-title">
                <i class="fas fa-calendar-alt me-2"></i>
                จัดการปีการศึกษา & ภาคเรียน
            </h2>
            <p class="text-white text-opacity-75 mb-0 mt-1 small">กำหนดปีการศึกษาปัจจุบัน วันเริ่ม-สิ้นสุดฝึกงานของนักศึกษา</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-light fw-bold rounded-pill shadow-sm px-4" data-bs-toggle="modal" data-bs-target="#addAyModal">
                <i class="fas fa-plus-circle me-1 text-primary"></i> เพิ่มปีการศึกษา
            </button>
            <a href="../roles/admin.php" class="btn-admin-outline text-decoration-none">
                <i class="fas fa-home me-1"></i> หน้าหลัก
            </a>
        </div>
    </div>

    <?php if ($msg): ?>
    <div class="alert alert-success border-0 shadow-sm d-flex align-items-center mb-4" style="border-radius: 0.85rem;">
        <i class="fas fa-check-circle fs-4 me-3"></i>
        <div><?= e($msg) ?></div>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert alert-danger border-0 shadow-sm d-flex align-items-center mb-4" style="border-radius: 0.85rem;">
        <i class="fas fa-exclamation-triangle fs-4 me-3"></i>
        <div><?= e($error) ?></div>
    </div>
    <?php endif; ?>

    <!-- Active Year Display -->
    <div class="ay-card active-ay mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="rounded-circle d-flex align-items-center justify-content-center text-white shadow-sm"
                     style="width:56px; height:56px; background: linear-gradient(135deg, #4f46e5, #06b6d4); font-size:1.5rem;">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <div>
                    <span class="badge-active-year mb-1 d-inline-block"><i class="fas fa-star me-1"></i> กำลังใช้งานอยู่</span>
                    <h4 class="fw-bold text-dark mb-0"><?= e($active_ay['title'] ?? 'ยังไม่ได้กำหนด') ?></h4>
                    <p class="text-muted small mb-0 mt-1">
                        <i class="far fa-calendar-check me-1 text-primary"></i>
                        ช่วงเวลาฝึกงาน: <?= !empty($active_ay['start_date']) ? date('d/m/Y', strtotime($active_ay['start_date'])) : '-' ?> ถึง <?= !empty($active_ay['end_date']) ? date('d/m/Y', strtotime($active_ay['end_date'])) : '-' ?>
                    </p>
                </div>
            </div>
            <div>
                <span class="fs-5 fw-bold text-indigo" style="color:#4f46e5;">ปีการศึกษา <?= e($active_ay['year'] ?? '-') ?> (เทอม <?= e($active_ay['term'] ?? '-') ?>)</span>
            </div>
        </div>
    </div>

    <!-- Years List Table -->
    <div class="card border-0 shadow-sm admin-card">
        <div class="card-header bg-white border-0 pt-4 pb-2">
            <h5 class="fw-bold text-dark mb-0"><i class="fas fa-list text-primary me-2"></i>รายการปีการศึกษาทั้งหมด</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-muted small">
                        <tr>
                            <th class="ps-4">ปีการศึกษา / ภาคเรียน</th>
                            <th>ชื่อภาคเรียน</th>
                            <th>วันเริ่มต้น</th>
                            <th>วันสิ้นสุด</th>
                            <th>สถานะ</th>
                            <th class="text-end pe-4">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($years_list)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">ยังไม่มีข้อมูลปีการศึกษา</td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($years_list as $row): ?>
                        <tr>
                            <td class="ps-4 fw-bold text-dark">
                                ปี <?= e($row['year']) ?> / เทอม <?= e($row['term']) ?>
                            </td>
                            <td class="fw-medium text-dark"><?= e($row['title']) ?></td>
                            <td><?= !empty($row['start_date']) ? date('d/m/Y', strtotime($row['start_date'])) : '-' ?></td>
                            <td><?= !empty($row['end_date']) ? date('d/m/Y', strtotime($row['end_date'])) : '-' ?></td>
                            <td>
                                <?php if ($row['is_active'] == 1): ?>
                                    <span class="badge bg-success bg-opacity-10 text-success fw-bold px-3 py-2 rounded-pill">
                                        <i class="fas fa-check-circle me-1"></i> ปัจจุบัน
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary fw-bold px-3 py-2 rounded-pill">
                                        ปิดใช้งาน
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-4">
                                <div class="d-flex justify-content-end gap-1">
                                    <?php if ($row['is_active'] != 1): ?>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('ยืนยันตั้งค่าเป็นปีการศึกษาปัจจุบันหรือไม่?')">
                                        <input type="hidden" name="action" value="activate">
                                        <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-success rounded-pill px-3" title="เปิดใช้งาน">
                                            <i class="fas fa-power-off me-1"></i> ใช้ปีนี้
                                        </button>
                                    </form>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('ยืนยันลบข้อมูลปีการศึกษานี้?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-2" title="ลบ">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Add Academic Year -->
<div class="modal fade" id="addAyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 bg-light rounded-top-4">
                <h5 class="modal-title fw-bold text-dark"><i class="fas fa-calendar-plus text-primary me-2"></i>เพิ่มปีการศึกษาใหม่</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label fw-bold small">ปีการศึกษา (พ.ศ.)</label>
                            <input type="text" name="year" class="form-control rounded-3" placeholder="เช่น 2567" value="<?= (date('Y')+543) ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold small">ภาคเรียน</label>
                            <select name="term" class="form-select rounded-3" required>
                                <option value="1">ภาคเรียนที่ 1</option>
                                <option value="2">ภาคเรียนที่ 2</option>
                                <option value="Summer">ภาคเรียนฤดูร้อน</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold small">ชื่อหัวข้อ (เว้นว่างไว้เพื่อสร้างอัตโนมัติ)</label>
                            <input type="text" name="title" class="form-control rounded-3" placeholder="เช่น ปีการศึกษา 2567 ภาคเรียนที่ 1">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold small">วันเริ่มต้นฝึกงาน</label>
                            <input type="date" name="start_date" class="form-control rounded-3">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold small">วันสิ้นสุดฝึกงาน</label>
                            <input type="date" name="end_date" class="form-control rounded-3">
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" name="is_active" id="isActiveCheck" value="1" checked>
                                <label class="form-check-label fw-bold small" for="isActiveCheck">ตั้งค่าเป็นปีการศึกษาปัจจุบันทันที</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold">บันทึกข้อมูล</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
