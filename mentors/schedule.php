<?php
require_once __DIR__ . '/../includes/configdb.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();
require_role(['mentor', 'teacher', 'admin']);

$u = current_user();
$user_id = (int)$u['id'];

$conn->query("ALTER TABLE mentors 
    ADD COLUMN IF NOT EXISTS schedule_json TEXT NULL");

$stmt = $conn->prepare("SELECT id, schedule_json FROM mentors WHERE user_id=? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
$mentor = $res->fetch_assoc();
$stmt->close();

if (!$mentor) {
    $u_stmt = $conn->prepare("SELECT email, phone FROM users WHERE id=?");
    $u_stmt->bind_param("i", $user_id);
    $u_stmt->execute();
    $u_data = $u_stmt->get_result()->fetch_assoc();
    $u_stmt->close();

    $email = $u_data['email'] ?? '';
    $phone = $u_data['phone'] ?? '';
    $department = 'วิทยาลัยอาชีวศึกษาเพชรบุรี'; // Default for UI

    $ins_stmt = $conn->prepare("INSERT INTO mentors (user_id, fullname, email, phone, department, status) VALUES (?, ?, ?, ?, ?, 1)");
    $ins_stmt->bind_param("issss", $user_id, $u['fullname'], $email, $phone, $department);
    $ins_stmt->execute();

    $mentor = [
        'id' => $conn->insert_id,
        'schedule_json' => null
    ];
    $ins_stmt->close();
}

$day_names = [
    1 => 'จันทร์', 2 => 'อังคาร', 3 => 'พุธ',
    4 => 'พฤหัสบดี', 5 => 'ศุกร์', 6 => 'เสาร์', 7 => 'อาทิตย์'
];

$schedule = [];
if (!empty($mentor['schedule_json'])) {
    $schedule = json_decode($mentor['schedule_json'], true) ?? [];
}

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [];
    foreach ($day_names as $d => $name) {
        $rows = $_POST['schedule'][$d] ?? [];
        foreach ($rows as $r) {
            if ($r['start'] && $r['end']) {
                $data[$d][] = [
                    'type' => $r['type'],
                    'start' => $r['start'],
                    'end' => $r['end']
                ];
            }
        }
    }

    $json = json_encode($data, JSON_UNESCAPED_UNICODE);
    $st = $conn->prepare("UPDATE mentors SET schedule_json=? WHERE id=?");
    $st->bind_param("si", $json, $mentor['id']);
    if ($st->execute()) {
        $success = 'บันทึกตารางเรียบร้อยแล้ว';
        $schedule = $data;
        
        // Trigger notification to students
        $stud_res = $conn->query("SELECT id FROM users WHERE role='student' AND mentor_id = $user_id");
        if ($stud_res) {
            while ($student = $stud_res->fetch_assoc()) {
                add_notification((int)$student['id'], 'ตารางครูนิเทศก์ได้รับการอัปเดต', "อาจารย์ {$u['fullname']} ได้ทำการอัปเดตตารางครูนิเทศก์ประจำสัปดาห์แล้ว กรุณาเข้าตรวจสอบ");
            }
        }
    } else {
        $error = 'บันทึกไม่สำเร็จ';
    }
    $st->close();
}

include __DIR__ . '/../includes/header.php';
$role = get_current_role();
$home_url = ($role === 'admin') ? '../roles/admin.php' : '../roles/teacher.php';
?>
<link rel="stylesheet" href="../includes/teacher_style.css">
<style>
.day-badge {
    width: 110px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    border-radius: 8px;
    padding: 6px;
    letter-spacing: 0.5px;
}
.time-row {
    animation: slideIn 0.25s ease-out;
    background-color: #f8f9fa;
    border-radius: 10px;
    padding: 8px 12px;
    border: 1px solid #e9ecef;
    transition: all 0.2s ease;
}
.time-row:hover {
    background-color: #f1f3f5;
    border-color: #dee2e6;
}
.select-type {
    min-width: 140px;
    font-weight: 500;
}
@keyframes slideIn {
    from { opacity: 0; transform: translateY(-5px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>

<div class="container teacher-page-container py-4">
    <div class="page-header-wrapper d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-4">
        <h4 class="page-header-title mb-3 mb-md-0">
            <a href="<?= htmlspecialchars($home_url); ?>" class="btn-back-circle me-2"><i class="bi bi-arrow-left"></i></a>
            <div class="icon-box me-1"><i class="bi bi-calendar-week icon-gradient"></i></div>
            กำหนดตารางสอนออนไลน์ / ออกนิเทศ
        </h4>
    </div>

    <?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i> <?= htmlspecialchars($success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <div class="premium-card border-top border-primary border-4">
        <div class="premium-card-header bg-white py-3">
            <h6 class="mb-0 fw-bold text-dark d-flex align-items-center">
                <i class="bi bi-clock-history text-primary me-2 fs-5"></i> จัดการตารางเวลาประจำสัปดาห์
            </h6>
        </div>
        <div class="card-body p-0">
            <form method="POST">
                <div class="table-responsive">
                    <table class="table table-tch align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th style="width: 160px;" class="ps-4">วัน</th>
                                <th>ช่วงเวลาและการปฏิบัติงาน</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $colors = [
                                1 => 'bg-warning-soft text-dark',   // จันทร์
                                2 => 'bg-danger-soft text-danger',  // อังคาร
                                3 => 'bg-success-soft text-success',// พุธ
                                4 => 'bg-warning-soft text-warning-dark', // พฤหัส (ส้ม)
                                5 => 'bg-info-soft text-info',      // ศุกร์
                                6 => 'bg-purple-soft text-purple',  // เสาร์
                                7 => 'bg-danger-soft text-danger'   // อาทิตย์
                            ];
                            // Override text for clarity
                            $day_styles = [
                                1 => 'background: #FFF9C4; color: #827717;', 
                                2 => 'background: #FCE4EC; color: #C2185B;', 
                                3 => 'background: #E8F5E9; color: #2E7D32;',
                                4 => 'background: #FFF3E0; color: #EF6C00;', 
                                5 => 'background: #E1F5FE; color: #0277BD;', 
                                6 => 'background: #F3E5F5; color: #7B1FA2;',
                                7 => 'background: #FFEBEE; color: #D32F2F;'
                            ];
                            
                            foreach ($day_names as $d => $label): 
                                $style = $day_styles[$d] ?? '';
                            ?>
                            <tr>
                                <td class="ps-4 py-4">
                                    <span class="day-badge" style="<?= $style ?>">วัน<?= htmlspecialchars($label) ?></span>
                                </td>
                                <td class="pe-4 py-3">
                                    <div class="schedule-day d-flex flex-column gap-2" data-day="<?= $d ?>">
                                        <?php if (!empty($schedule[$d])): ?>
                                            <?php foreach ($schedule[$d] as $i => $row): ?>
                                            <div class="time-row d-flex flex-wrap align-items-center gap-2 mb-1">
                                                <select name="schedule[<?= $d ?>][<?= $i ?>][type]" class="form-select form-select-sm select-type border-primary-light">
                                                    <option value="online" <?= $row['type']=='online'?'selected':'' ?>>📡 สอนออนไลน์</option>
                                                    <option value="supervision" <?= $row['type']=='supervision'?'selected':'' ?>>🚗 ออกนิเทศ</option>
                                                </select>
                                                <div class="input-group input-group-sm w-auto">
                                                    <span class="input-group-text bg-white"><i class="bi bi-clock"></i></span>
                                                    <input type="time" name="schedule[<?= $d ?>][<?= $i ?>][start]" value="<?= htmlspecialchars($row['start']) ?>" class="form-control">
                                                </div>
                                                <span class="text-muted small">ถึง</span>
                                                <div class="input-group input-group-sm w-auto">
                                                    <input type="time" name="schedule[<?= $d ?>][<?= $i ?>][end]" value="<?= htmlspecialchars($row['end']) ?>" class="form-control">
                                                </div>
                                                <button type="button" class="btn btn-link text-danger btn-sm p-0 ms-auto remove-row" title="ลบช่วงเวลา">
                                                    <i class="bi bi-trash-fill fs-6"></i>
                                                </button>
                                            </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <button type="button" class="btn btn-sm btn-outline-primary add-row border-dashed mt-2 rounded-pill px-3 py-1" data-day="<?= $d ?>" style="border-style: dashed;">
                                        <i class="bi bi-plus-circle-fill me-1"></i> เพิ่มช่วงเวลา
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="p-4 bg-light text-center border-top">
                    <button class="btn btn-tch-gradient px-5 py-3 fw-bold shadow-sm rounded-pill">
                        <i class="bi bi-save-fill me-2"></i> บันทึกข้อมูลตารางเวลาทั้งหมด
                    </button>
                    <div class="text-muted small mt-2"><i class="bi bi-info-circle me-1"></i> การบันทึกจะส่งการแจ้งเตือนไปยังนักเรียนโดยอัตโนมัติ</div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.add-row').forEach(btn => {
    btn.onclick = () => {
        const day = btn.dataset.day;
        const box = document.querySelector(`.schedule-day[data-day="${day}"]`);
        // We need a dynamic unique identifier that won't overwrite other indexes if they add many. Date.now() works perfectly.
        const i = Date.now(); 

        const html = `
        <div class="time-row d-flex flex-wrap align-items-center gap-2 mb-1">
            <select name="schedule[${day}][${i}][type]" class="form-select form-select-sm select-type border-primary-light">
                <option value="online">📡 สอนออนไลน์</option>
                <option value="supervision">🚗 ออกนิเทศ</option>
            </select>
            <div class="input-group input-group-sm w-auto">
                <span class="input-group-text bg-white"><i class="bi bi-clock"></i></span>
                <input type="time" name="schedule[${day}][${i}][start]" class="form-control" required>
            </div>
            <span class="text-muted small">ถึง</span>
            <div class="input-group input-group-sm w-auto">
                <input type="time" name="schedule[${day}][${i}][end]" class="form-control" required>
            </div>
            <button type="button" class="btn btn-link text-danger btn-sm p-0 ms-auto remove-row" title="ลบช่วงเวลา">
                <i class="bi bi-trash-fill fs-6"></i>
            </button>
        </div>`;
        box.insertAdjacentHTML('beforeend', html);
    };
});

document.addEventListener('click', e => {
    // Check if target is either the icon inside the button or the button itself
    const btn = e.target.closest('.remove-row');
    if (btn) {
        const row = btn.closest('.time-row');
        row.style.opacity = '0';
        row.style.transform = 'translateX(20px)';
        setTimeout(() => row.remove(), 250);
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
