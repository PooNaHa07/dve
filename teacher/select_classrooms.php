<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php';
require_login();
require_role(['teacher']);

$u = current_user();
$teacher_id = $u['id'];

// --- ส่วนบันทึกการเลือกห้อง ---
if (isset($_POST['save_assignments'])) {
    $del = $conn->prepare("DELETE FROM teacher_assignments WHERE teacher_id = ?");
    if ($del) {
        $del->bind_param("i", $teacher_id);
        $del->execute();
        $del->close();
    }
    $saved_ok = true;
    if (!empty($_POST['rooms'])) {
        $ins = $conn->prepare("INSERT INTO teacher_assignments (teacher_id, classroom_id) VALUES (?, ?)");
        if ($ins) {
            foreach ($_POST['rooms'] as $room_id) {
                $room_id = (int)$room_id;
                $ins->bind_param("ii", $teacher_id, $room_id);
                if (!$ins->execute()) {
                    $saved_ok = false;
                    break;
                }
            }
            $ins->close();
        } else {
            $saved_ok = false;
        }
    }
    header("Location: select_classrooms.php?msg=" . ($saved_ok ? "saved" : "error"));
    exit;
}

$swal_show = isset($_GET['msg']) && ($_GET['msg'] === 'saved' || $_GET['msg'] === 'error');
$swal_type = isset($_GET['msg']) && $_GET['msg'] === 'error' ? 'error' : 'success';
$swal_title = isset($_GET['msg']) && $_GET['msg'] === 'error' ? 'เกิดข้อผิดพลาด' : 'บันทึกสำเร็จ';
$swal_text = isset($_GET['msg']) && $_GET['msg'] === 'error' ? 'ไม่สามารถบันทึกการเลือกห้องได้ กรุณาลองใหม่อีกครั้ง' : 'บันทึกการเลือกห้องเรียนเรียบร้อย';

include __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="../includes/teacher_style.css">
<style>
    .classroom-check-card {
        cursor: pointer;
        transition: all 0.25s ease;
        border: 2px solid #e9ecef;
        position: relative;
        padding-right: 45px !important; /* Add safety space for tick mark */
    }
    .classroom-check-card:hover {
        border-color: var(--primary-color);
        background-color: rgba(78, 115, 223, 0.02);
        transform: translateY(-2px);
    }
    .classroom-check-input {
        display: none;
    }
    .classroom-check-input:checked + .classroom-check-card {
        border-color: var(--primary-color);
        background-color: var(--primary-soft);
        box-shadow: 0 4px 12px rgba(78, 115, 223, 0.15);
    }
    .classroom-check-input:checked + .classroom-check-card::after {
        content: '\F26E'; /* bi-check-circle-fill */
        font-family: 'bootstrap-icons';
        position: absolute;
        top: 10px;
        right: 12px;
        color: var(--primary-color);
        font-size: 1.2rem;
    }
    .classroom-check-input:checked + .classroom-check-card .icon-box-sm {
        background: #ffffff !important;
        color: var(--primary-color) !important;
        box-shadow: inset 0 0 0 1px rgba(78, 115, 223, 0.1);
    }
    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.15); }
        100% { transform: scale(1); }
    }
    .pulse-anim {
        animation: pulse 0.3s ease-out;
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<?php if ($swal_show): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    Swal.fire({
        title: <?= json_encode($swal_title) ?>,
        text: <?= json_encode($swal_text) ?>,
        icon: <?= json_encode($swal_type) ?>,
        confirmButtonText: 'ตกลง',
        confirmButtonColor: '#4e73df',
        customClass: {
            confirmButton: 'btn btn-primary px-4 rounded-pill'
        }
    }).then(function() {
        var url = new URL(window.location.href);
        url.searchParams.delete('msg');
        if (window.history && window.history.replaceState) window.history.replaceState({}, '', url.pathname + (url.search ? url.search : ''));
    });
});
</script>
<?php endif; ?>

<div class="container teacher-page-container">
    <div class="page-header-wrapper">
        <h4 class="page-header-title">
            <a href="../roles/teacher.php" class="btn-back-circle me-2"><i class="bi bi-arrow-left"></i></a>
            <div class="icon-box me-1"><i class="bi bi-building icon-gradient"></i></div>
            ตั้งค่าห้องเรียนรับผิดชอบ
        </h4>
    </div>

    <div class="premium-card">
        <div class="premium-card-header bg-white border-bottom-0 d-flex flex-wrap justify-content-between align-items-center pb-2 pt-3 px-4">
            <div>
                <h5 class="fw-bold text-dark mb-1">เลือกห้องเรียนที่ดูแลการนิเทศ</h5>
                <p class="text-muted small mb-0">คลิกเพื่อเลือก (ปัจจุบันเลือกไว้ <span id="selectedCount" class="badge bg-primary rounded-pill">0</span> ห้อง)</p>
            </div>
            <div class="d-flex gap-2 mt-2 mt-md-0">
                <div class="input-group input-group-sm shadow-sm" style="max-width: 200px; border-radius: 20px; overflow: hidden;">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" id="searchInput" class="form-control border-start-0 ps-0" placeholder="ค้นหา...">
                </div>
                <button type="button" class="btn btn-sm btn-outline-secondary shadow-sm rounded-pill" id="toggleAllBtn">
                    <i class="bi bi-check2-all me-1"></i> ทั้งหมด
                </button>
            </div>
        </div>
        <div class="card-body p-4 pt-2">
            <form method="POST">
                <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-3" id="classroomGrid">
                    <?php
                    $sql_rooms = "SELECT * FROM classrooms ORDER BY class_name ASC";
                    $res_rooms = $conn->query($sql_rooms);
                    
                    $my_rooms = [];
                    $sql_my = "SELECT classroom_id FROM teacher_assignments WHERE teacher_id = '$teacher_id'";
                    $res_my = $conn->query($sql_my);
                    if ($res_my) {
                        while($row_my = $res_my->fetch_assoc()) {
                            $my_rooms[] = $row_my['classroom_id'];
                        }
                    }

                    if ($res_rooms && $res_rooms->num_rows > 0):
                        while($room = $res_rooms->fetch_assoc()):
                            $checked = in_array($room['id'], $my_rooms) ? 'checked' : '';
                            $st_count = (int)($room['total_students'] ?? 0);
                    ?>
                    <div class="col class-item">
                        <input type="checkbox" name="rooms[]" value="<?php echo $room['id']; ?>" class="classroom-check-input" id="room_<?php echo $room['id']; ?>" <?php echo $checked; ?>>
                        <label for="room_<?php echo $room['id']; ?>" class="classroom-check-card w-100 p-3 rounded-3 d-flex align-items-center">
                            <div class="icon-box-sm bg-light text-secondary me-3 rounded-circle d-flex align-items-center justify-content-center" style="width:42px;height:42px">
                                <i class="bi bi-door-closed-fill fs-5"></i>
                            </div>
                            <div>
                                <span class="fw-bold text-dark d-block class-name"><?php echo htmlspecialchars($room['class_name']); ?></span>
                            </div>
                        </label>
                    </div>
                    <?php endwhile; else: ?>
                        <div class="col-12 text-center py-5 text-muted">
                            <i class="bi bi-inbox d-block fs-2 mb-2 opacity-50"></i> ไม่พบห้องเรียนในฐานข้อมูล
                        </div>
                    <?php endif; ?>
                </div>
                
                <div id="noResult" class="text-center py-5 text-muted d-none">
                    <i class="bi bi-search d-block fs-2 mb-2 opacity-50"></i> ไม่พบข้อมูลที่คุณค้นหา
                </div>

                <hr class="my-4 border-light">
                <div class="d-flex justify-content-between align-items-center">
                    <a href="../roles/teacher.php" class="btn btn-light text-muted px-4 rounded-pill"><i class="bi bi-x-circle me-1"></i> ยกเลิก</a>
                    <button type="submit" name="save_assignments" class="btn btn-tch-gradient px-5 py-3 fw-bold rounded-pill shadow-sm">
                        <i class="bi bi-save-fill me-2"></i> บันทึกการตั้งค่า
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const inputs = document.querySelectorAll('.classroom-check-input');
    const countBadge = document.getElementById('selectedCount');
    const searchInput = document.getElementById('searchInput');
    const classItems = document.querySelectorAll('.class-item');
    const toggleAllBtn = document.getElementById('toggleAllBtn');
    const noResult = document.getElementById('noResult');

    function updateSelectedCount() {
        const count = document.querySelectorAll('.classroom-check-input:checked').length;
        countBadge.textContent = count;
        countBadge.classList.remove('pulse-anim');
        void countBadge.offsetWidth; // reflow
        countBadge.classList.add('pulse-anim');
        countBadge.className = count > 0 ? 'badge bg-primary rounded-pill pulse-anim' : 'badge bg-secondary rounded-pill';
    }

    updateSelectedCount();

    inputs.forEach(inp => inp.addEventListener('change', updateSelectedCount));

    searchInput.addEventListener('input', function() {
        const val = this.value.trim().toLowerCase();
        let match = 0;
        classItems.forEach(el => {
            const txt = el.querySelector('.class-name').textContent.toLowerCase();
            if (txt.includes(val)) {
                el.style.display = '';
                match++;
            } else {
                el.style.display = 'none';
            }
        });
        if (match === 0 && val !== '') {
            noResult.classList.remove('d-none');
        } else {
            noResult.classList.add('d-none');
        }
    });

    toggleAllBtn.addEventListener('click', function() {
        const visible = Array.from(classItems).filter(e => e.style.display !== 'none');
        if (!visible.length) return;
        const boxes = visible.map(e => e.querySelector('input'));
        const allChecked = boxes.every(i => i.checked);
        boxes.forEach(i => i.checked = !allChecked);
        updateSelectedCount();
    });
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>