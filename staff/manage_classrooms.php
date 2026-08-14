<?php
// staff/manage_classrooms.php - Classroom CRUD panel for Staff
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php';
require_login();
require_role(['staff']);

// Fetch initial data
$res_total = $conn->query("SELECT COUNT(id) as total FROM users WHERE role = 'student'");
$total_students_all = ($res_total) ? $res_total->fetch_assoc()['total'] : 0;

$sql_classrooms = "SELECT c.*, 
                  (SELECT COUNT(u.id) FROM users u WHERE u.classroom_id = c.id AND u.role = 'student') as student_registered 
                  FROM classrooms c 
                  ORDER BY c.id ASC"; 
$classrooms = $conn->query($sql_classrooms);

include __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="../assets/css/admin-premium.css">

<style>
:root {
    --glass-bg: rgba(255, 255, 255, 0.9);
    --glass-border: rgba(255, 255, 255, 0.2);
    --primary-gradient: linear-gradient(135deg, #6366f1 0%, #a855f7 100%);
}

body {
    background: #f1f5f9;
    min-height: 100vh;
}

.bg-blob {
    position: fixed;
    width: 600px;
    height: 600px;
    background: radial-gradient(circle, rgba(99, 102, 241, 0.1) 0%, rgba(168, 85, 247, 0.05) 100%);
    border-radius: 50%;
    filter: blur(100px);
    z-index: -1;
}
.blob-1 { top: -200px; right: -200px; }
.blob-2 { bottom: -200px; left: -200px; }

.glass-card {
    background: var(--glass-bg);
    backdrop-filter: blur(10px);
    border: 1px solid var(--glass-border);
    border-radius: 1.5rem;
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.04);
}

.stat-mini-card {
    background: white;
    border-radius: 1.25rem;
    padding: 1.5rem;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    display: flex;
    justify-content: space-between;
    align-items: center;
    border: 1px solid rgba(0,0,0,0.05);
}

.progress-tiny { 
    height: 8px; 
    background: #e2e8f0; 
    border-radius: 10px; 
    overflow: hidden; 
    width: 100px; 
}

.btn-add-room {
    background: var(--primary-gradient);
    color: white;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 1rem;
    font-weight: 600;
    transition: all 0.3s;
}
.btn-add-room:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 15px -3px rgba(99, 102, 241, 0.3);
    color: white;
}
</style>

<div class="bg-blob blob-1"></div>
<div class="bg-blob blob-2"></div>

<div class="container admin-content-wrapper py-5">
    <div class="admin-header-section d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div>
            <h2 class="admin-header-title">
                <i class="fas fa-door-open" style="color: #6366f1;"></i>
                จัดการห้องเรียนและสาขา (เจ้าหน้าที่)
            </h2>
            <p class="text-muted mb-0">บริหารจัดการข้อมูลห้องเรียนและเป้าหมายจำนวนนักเรียน</p>
        </div>
        <div class="d-flex gap-2">
            <a href="../roles/staff.php" class="btn btn-admin-outline px-4">
                <i class="fas fa-home me-2"></i> หน้าหลัก
            </a>
            <button class="btn btn-add-room" onclick="openRoomModal()">
                <i class="fas fa-plus-circle me-2"></i> เพิ่มห้องเรียนใหม่
            </button>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-4">
            <div class="stat-mini-card">
                <div>
                    <div class="fs-4 fw-bold text-dark"><?php echo $total_students_all; ?></div>
                    <div class="text-muted small text-uppercase fw-bold letter-spacing-1">นักเรียนลงทะเบียนรวม</div>
                </div>
                <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                    <i class="fas fa-users fs-5"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="glass-card overflow-hidden">
        <div class="p-4">
            <div class="table-responsive">
                <table id="roomsTable" class="table align-middle table-hover" style="width:100%">
                    <thead>
                        <tr>
                            <th style="width: 80px;">ID</th>
                            <th>ชื่อห้องเรียน / แผนก</th>
                            <th class="text-center">ลงทะเบียน (คน)</th>
                            <th class="text-center">ความสำเร็จ</th>
                            <th class="text-end" style="width: 150px;">การจัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $classrooms->fetch_assoc()): 
                            $pct = ($row['total_students'] > 0) ? round(($row['student_registered'] / $row['total_students']) * 100) : 0;
                            $pct = min(100, $pct);
                            $barColor = $pct >= 90 ? 'bg-success' : ($pct > 50 ? 'bg-warning' : 'bg-danger');
                        ?>
                        <tr id="row-<?php echo $row['id']; ?>">
                            <td><span class="text-muted fw-bold">#<?php echo $row['id']; ?></span></td>
                            <td>
                                <div class="fw-bold text-dark fs-6"><?php echo htmlspecialchars($row['class_name']); ?></div>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-pill fw-bold">
                                    <?php echo $row['student_registered']; ?> / <?php echo $row['total_students']; ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <div class="d-inline-flex align-items-center gap-3">
                                    <div class="progress-tiny">
                                        <div class="progress-bar <?php echo $barColor; ?>" style="width: <?php echo $pct; ?>%"></div>
                                    </div>
                                    <span class="small fw-bold text-dark"><?php echo $pct; ?>%</span>
                                </div>
                            </td>
                            <td class="text-end">
                                <div class="d-flex justify-content-end gap-2">
                                    <a href="view_classroom_students.php?id=<?php echo $row['id']; ?>" class="btn btn-light btn-sm rounded-3 px-3" title="รายชื่อนักเรียน">
                                        <i class="fas fa-list text-primary"></i>
                                    </a>
                                    <button class="btn btn-light btn-sm rounded-3 px-3" onclick="editRoom(<?php echo $row['id']; ?>)" title="แก้ไข">
                                        <i class="fas fa-edit text-warning"></i>
                                    </button>
                                    <button class="btn btn-light btn-sm rounded-3 px-3" onclick="deleteRoom(<?php echo $row['id']; ?>, '<?php echo addslashes($row['class_name']); ?>')" title="ลบ">
                                        <i class="fas fa-trash text-danger"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Room Modal -->
<div class="modal fade" id="roomModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark text-white border-0 py-3">
                <h5 class="modal-title d-flex align-items-center">
                    <i class="fas fa-door-open me-2"></i>
                    <span id="roomModalTitle">ข้อมูลห้องเรียน</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="roomForm">
                <div class="modal-body p-4 bg-white">
                    <input type="hidden" name="room_id" id="room_id" value="0">
                    <div class="mb-4">
                        <label class="form-label fw-bold small text-muted text-uppercase">ชื่อห้องเรียน / แผนก / กลุ่มงาน</label>
                        <input type="text" name="class_name" id="class_name" class="form-control form-control-lg border-2" placeholder="เช่น ชฟ. 3/1" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted text-uppercase">เป้าหมายจำนวนนักเรียน (คน)</label>
                        <input type="number" name="total_students" id="total_students" class="form-control form-control-lg border-2" min="0" value="0">
                        <div class="form-text mt-2">ใช้สำหรับคำนวณความคืบหน้าในการลงทะเบียน</div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 px-4">
                    <button type="button" class="btn btn-outline-secondary px-4 border-0" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary px-4 rounded-pill">
                        <i class="fas fa-save me-2"></i>บันทึกข้อมูล
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

<script>
let roomsTable;
let roomModal;
$(document).ready(function() {
    roomsTable = $('#roomsTable').DataTable({
        language: { url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/th.json' },
        pageLength: 25,
        columnDefs: [{ orderable: false, targets: 4 }]
    });
    roomModal = new bootstrap.Modal(document.getElementById('roomModal'));
});

function openRoomModal() {
    document.getElementById('roomModalTitle').innerText = "เพิ่มห้องเรียนใหม่";
    document.getElementById('roomForm').reset();
    document.getElementById('room_id').value = "0";
    roomModal.show();
}

function editRoom(id) {
    $.getJSON('get_classroom.php', { id: id }, function(data) {
        if (data.error) {
            Swal.fire('Error', data.error, 'error');
            return;
        }
        document.getElementById('roomModalTitle').innerText = "แก้ไขข้อมูลห้องเรียน";
        document.getElementById('room_id').value = data.id;
        document.getElementById('class_name').value = data.class_name;
        document.getElementById('total_students').value = data.total_students;
        roomModal.show();
    });
}

$('#roomForm').on('submit', function(e) {
    e.preventDefault();
    $.post('save_classroom.php', $(this).serialize(), function(response) {
        if (response.success) {
            roomModal.hide();
            Swal.fire({
                icon: 'success',
                title: 'สำเร็จ',
                text: response.message,
                timer: 1500,
                showConfirmButton: false
            }).then(() => {
                location.reload();
            });
        } else {
            Swal.fire('Error', response.message, 'error');
        }
    });
});

function deleteRoom(id, name) {
    Swal.fire({
        title: 'ยืนยันการลบ?',
        text: `คุณกำลังจะลบห้องเรียน "${name}"`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'ยืนยันการลบ',
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        if (result.isConfirmed) {
            $.post('delete_classroom.php', { id: id }, function(response) {
                if (response.success) {
                    $(`#row-${id}`).fadeOut(400, function() {
                        roomsTable.row($(this)).remove().draw(false);
                    });
                    Swal.fire('ลบแล้ว!', response.message, 'success');
                } else {
                    Swal.fire('Error', response.message, 'error');
                }
            });
        }
    });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
