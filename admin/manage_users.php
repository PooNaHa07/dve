<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php';
require_login();
require_role(['admin']);

// Fetch data for dropdowns
$classrooms_res = $conn->query("SELECT id, class_name FROM classrooms ORDER BY class_name ASC");
$classrooms = [];
while ($row = $classrooms_res->fetch_assoc()) $classrooms[] = $row;

$mentors_res = $conn->query("SELECT id, fullname FROM users WHERE role = 'mentor' ORDER BY fullname ASC");
$mentors = [];
while ($row = $mentors_res->fetch_assoc()) $mentors[] = $row;

// Fetch GET role parameter
$target_role = isset($_GET['role']) ? trim($_GET['role']) : '';
$allowed_roles = ['admin', 'staff', 'teacher', 'mentor', 'student', 'director', 'supervisor'];

$page_title = "จัดการผู้ใช้งาน";
$page_desc = "ระบบบริหารจัดการสมาชิกและกำหนดสิทธิ์การเข้าถึง";
$where_clause = "";

if (in_array($target_role, $allowed_roles)) {
    $where_clause = "WHERE u.role = '" . $conn->real_escape_string($target_role) . "' ";
    
    $role_titles = [
        'student' => ['title' => 'จัดการข้อมูลนักเรียน', 'desc' => 'ตรวจสอบรายชื่อและข้อมูลการฝึกงานรายบุคคล'],
        'teacher' => ['title' => 'จัดการหัวหน้าแผนก / ครู', 'desc' => 'บริหารจัดการข้อมูลบุคลากรทางการศึกษา'],
        'staff' => ['title' => 'จัดการเจ้าหน้าที่', 'desc' => 'ควบคุมสิทธิ์การทำงานของเจ้าหน้าที่งานทวิภาคี'],
        'supervisor' => ['title' => 'จัดการผู้ดูแลการฝึกงาน', 'desc' => 'บริหารจัดการบัญชีผู้ดูแลการฝึกงานของนักเรียน (Supervisor)'],
        'director' => ['title' => 'จัดการผู้บริหาร', 'desc' => 'จัดการบัญชีสำหรับผู้บริหารเพื่อดูภาพรวม'],
        'admin' => ['title' => 'จัดการผู้ดูแลระบบ', 'desc' => 'ควบคุมสิทธิ์สูงสุดในการเข้าถึงระบบ'],
        'mentor' => ['title' => 'จัดการครูนิเทศก์', 'desc' => 'ควบคุมรายชื่อครูที่ปรึกษาประจำหน่วยงาน']
    ];
    
    if(isset($role_titles[$target_role])) {
        $page_title = $role_titles[$target_role]['title'];
        $page_desc = $role_titles[$target_role]['desc'];
    }
}

// Initial data load for the table
$users_res = $conn->query("SELECT u.*, c.class_name, m.fullname as mentor_name 
                         FROM users u 
                         LEFT JOIN classrooms c ON u.classroom_id = c.id 
                         LEFT JOIN users m ON u.mentor_id = m.id 
                         $where_clause
                         ORDER BY u.id DESC");

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
    background: #f8fafc;
    min-height: 100vh;
    overflow-x: hidden;
}

/* Background Blobs */
.bg-blob {
    position: fixed;
    width: 500px;
    height: 500px;
    background: radial-gradient(circle, rgba(99, 102, 241, 0.15) 0%, rgba(168, 85, 247, 0.05) 100%);
    border-radius: 50%;
    filter: blur(80px);
    z-index: -1;
    animation: blobFloat 20s infinite alternate;
}
.blob-1 { top: -100px; right: -100px; }
.blob-2 { bottom: -100px; left: -100px; animation-delay: -5s; }

@keyframes blobFloat {
    0% { transform: translate(0, 0) scale(1); }
    100% { transform: translate(50px, 50px) scale(1.1); }
}

.glass-table-card {
    background: var(--glass-bg);
    backdrop-filter: blur(10px);
    border: 1px solid var(--glass-border);
    border-radius: 1.5rem;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.03);
    overflow: hidden;
}

.admin-header-section {
    background: var(--glass-bg);
    backdrop-filter: blur(10px);
    border: 1px solid var(--glass-border);
    padding: 1.5rem 2rem;
    border-radius: 1.25rem;
    margin-bottom: 2rem;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
}

.role-badge {
    padding: 0.4rem 0.8rem;
    font-weight: 600;
    font-size: 0.75rem;
    border-radius: 50rem;
    text-transform: uppercase;
}
.role-admin { background: #fee2e2; color: #991b1b; }
.role-staff { background: #fef3c7; color: #92400e; }
.role-teacher { background: #dcfce7; color: #166534; }
.role-mentor { background: #dbeafe; color: #1e40af; }
.role-director { background: #ffedd5; color: #c2410c; }
.role-student { background: #f3f4f6; color: #374151; }

.user-avatar {
    width: 40px;
    height: 40px;
    background: var(--primary-gradient);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
    font-weight: 700;
    font-size: 1.1rem;
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
}

.btn-add-user {
    background: var(--primary-gradient);
    border: none;
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 1rem;
    font-weight: 600;
    transition: all 0.3s;
    box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
}
.btn-add-user:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(99, 102, 241, 0.4);
    color: white;
}

.action-btn {
    width: 36px;
    height: 36px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    transition: all 0.2s;
    border: none;
}
.btn-edit { background: #fef3c7; color: #92400e; }
.btn-edit:hover { background: #fde68a; }
.btn-delete { background: #fee2e2; color: #991b1b; }
.btn-delete:hover { background: #fecaca; }
</style>

<div class="bg-blob blob-1"></div>
<div class="bg-blob blob-2"></div>

<div class="container admin-content-wrapper py-5">
    <div class="admin-header-section d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div>
            <h2 class="admin-header-title mb-1">
                <i class="fas fa-users-cog"></i>
                <?php echo htmlspecialchars($page_title); ?>
            </h2>
            <p class="text-muted mb-0"><?php echo htmlspecialchars($page_desc); ?></p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="../roles/admin.php" class="btn btn-admin-outline px-4">
                <i class="fas fa-arrow-left me-2"></i> กลับหน้าหลัก
            </a>
            <?php if ($target_role === 'student' || $target_role === ''): ?>
            <a href="import_students.php" class="btn btn-success px-4" style="border-radius:1rem;">
                <i class="fas fa-file-import me-2"></i> นำเข้า CSV
            </a>
            <?php endif; ?>
            <button class="btn btn-add-user" onclick="openUserModal()">
                <i class="fas fa-user-plus me-2"></i> เพิ่มผู้ใช้งานใหม่
            </button>
        </div>
    </div>

    <div class="glass-table-card">
        <div class="p-4">
            <div class="table-responsive">
                <table id="usersTable" class="table align-middle table-hover" style="width:100%">
                    <thead>
                        <tr>
                            <th style="width: 60px;">#</th>
                            <th>บัญชีผู้ใช้</th>
                            <th>ชื่อ-นามสกุล</th>
                            <th>เบอร์โทรศัพท์</th>
                            <th>สาขาวิชา/สังกัด</th>
                            <th>บทบาท</th>
                            <th>รายละเอียดเพิ่มเติม</th>
                            <th class="text-end" style="width: 120px;">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $users_res->fetch_assoc()): ?>
                        <tr id="row-<?php echo $row['id']; ?>">
                            <td><span class="text-muted fw-bold">#<?php echo $row['id']; ?></span></td>
                            <td><?php echo htmlspecialchars($row['username']); ?></td>
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <div class="user-avatar bg-primary text-white">
                                        <?php echo mb_substr($row['fullname'], 0, 1); ?>
                                    </div>
                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($row['fullname']); ?></div>
                                </div>
                            </td>
                            <td><i class="fas fa-phone-alt me-1 opacity-50 small"></i> <?php echo htmlspecialchars($row['phone'] ?: '-'); ?></td>
                            <td><i class="fas fa-building me-1 opacity-50 small"></i> <?php echo htmlspecialchars($row['affiliation'] ?: '-'); ?></td>
                            <td>
                                <span class="role-badge role-<?php echo $row['role']; ?>">
                                    <i class="fas fa-user-shield me-1"></i>
                                    <?php echo strtoupper($row['role']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="small">
                                    <?php if($row['role'] == 'student'): ?>
                                        <div class="text-dark"><i class="fas fa-graduation-cap me-1 opacity-50"></i> <?php echo htmlspecialchars($row['class_name'] ?? 'ไม่มีห้องเรียน'); ?> (<?php echo htmlspecialchars($row['student_level'] ?? '-'); ?>)</div>
                                        <div class="text-muted"><i class="fas fa-user-tie me-1 opacity-50"></i> ครูนิเทศ: <?php echo htmlspecialchars($row['mentor_name'] ?? '-'); ?></div>
                                    <?php elseif($row['email']): ?>
                                        <div class="text-dark"><i class="fas fa-envelope me-1 opacity-50"></i> <?php echo htmlspecialchars($row['email']); ?></div>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="text-end">
                                <div class="d-flex justify-content-end gap-2">
                                    <button class="action-btn btn-edit" onclick="editUser(<?php echo $row['id']; ?>)" title="แก้ไข">
                                        <i class="fas fa-pen"></i>
                                    </button>
                                    <button class="action-btn btn-delete" onclick="deleteUser(<?php echo $row['id']; ?>, '<?php echo addslashes($row['fullname']); ?>')" title="ลบ">
                                        <i class="fas fa-trash"></i>
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

<!-- User Modal -->
<div class="modal fade" id="userModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark text-white border-0 py-3">
                <h5 class="modal-title d-flex align-items-center">
                    <i class="fas fa-user-circle me-2"></i>
                    <span id="modalTitle">ข้อมูลผู้ใช้งาน</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="userForm">
                <div class="modal-body p-4 bg-white">
                    <input type="hidden" name="id" id="user_id" value="0">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted text-uppercase">ชื่อผู้ใช้ (Username)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="fas fa-at text-muted"></i></span>
                                <input type="text" name="username" id="username" class="form-control border-start-0" placeholder="Username" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted text-uppercase">ชื่อ-นามสกุล (Full Name)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="fas fa-user text-muted"></i></span>
                                <input type="text" name="fullname" id="fullname" class="form-control border-start-0" placeholder="ชื่อ นามสกุล" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted text-uppercase">อีเมล (Email)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="fas fa-envelope text-muted"></i></span>
                                <input type="email" name="email" id="email" class="form-control border-start-0" placeholder="email@example.com">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted text-uppercase">เบอร์โทรศัพท์</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="fas fa-phone text-muted"></i></span>
                                <input type="text" name="phone" id="phone" class="form-control border-start-0" placeholder="08x-xxx-xxxx">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted text-uppercase">สังกัด/สาขาวิชา</label>
                            <input type="text" name="affiliation" id="affiliation" class="form-control" placeholder="เช่น สาขาวิชาคอมพิวเตอร์ธุรกิจ">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted text-uppercase">รหัสผ่าน (Password)</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="fas fa-key text-muted"></i></span>
                                <input type="password" name="password" id="password" class="form-control border-start-0" placeholder="เว้นว่างไว้หากไม่ต้องการเปลี่ยน">
                            </div>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-bold small text-muted text-uppercase">ระดับสิทธิ์การใช้งาน (Role)</label>
                            <select name="role" id="role" class="form-select" required onchange="toggleRoleFields()">
                                <option value="admin">Administrator (ผู้ดูแลระบบ)</option>
                                <option value="staff">Staff (เจ้าหน้าที่)</option>
                                <option value="supervisor">Supervisor (ผู้ดูแลการฝึกงาน)</option>
                                <option value="teacher">Teacher (หัวหน้าแผนก/ครู)</option>
                                <option value="mentor">Mentor (ครูนิเทศก์)</option>
                                <option value="director">Director (ผู้บริหาร)</option>
                                <option value="student">Student (นักเรียน/นักศึกษา)</option>
                            </select>
                        </div>

                        <!-- Fields for Students Only -->
                        <div id="student_fields" class="row g-4 mt-1" style="display:none;">
                            <div class="col-md-4">
                                <label class="form-label fw-bold small text-muted text-uppercase">รหัสนักศึกษา</label>
                                <input type="text" name="student_code" id="student_code" class="form-control" placeholder="6XXXXXXX">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold small text-muted text-uppercase">ห้องเรียน / แผนก</label>
                                <select name="classroom_id" id="classroom_id" class="form-select">
                                    <option value="">เลือกห้องเรียน</option>
                                    <?php foreach($classrooms as $c): ?>
                                        <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['class_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold small text-muted text-uppercase">ระดับ (เช่น ปวส.2/1)</label>
                                <input type="text" name="student_level" id="student_level" class="form-control" placeholder="ปวส. 2/1">
                            </div>

                            <div class="col-md-12">
                                <label class="form-label fw-bold small text-muted text-uppercase mb-2">ครูนิเทศก์ที่รับผิดชอบ (สูงสุด 5 คน)</label>
                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <select name="mentor_id" id="mentor_id" class="form-select mb-2">
                                            <option value="">เลือกครูนิเทศก์คนที่ 1</option>
                                            <?php foreach($mentors as $m): ?>
                                                <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['fullname']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <select name="mentor_id_2" id="mentor_id_2" class="form-select mb-2">
                                            <option value="">เลือกครูนิเทศก์คนที่ 2</option>
                                            <?php foreach($mentors as $m): ?>
                                                <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['fullname']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <select name="mentor_id_3" id="mentor_id_3" class="form-select mb-2">
                                            <option value="">คนที่ 3</option>
                                            <?php foreach($mentors as $m): ?>
                                                <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['fullname']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <select name="mentor_id_4" id="mentor_id_4" class="form-select mb-2">
                                            <option value="">คนที่ 4</option>
                                            <?php foreach($mentors as $m): ?>
                                                <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['fullname']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <select name="mentor_id_5" id="mentor_id_5" class="form-select mb-2">
                                            <option value="">คนที่ 5</option>
                                            <?php foreach($mentors as $m): ?>
                                                <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['fullname']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label fw-bold small text-muted text-uppercase">ผู้จัดการสถานประกอบการ (ผู้ลงนามเกียรติบัตร)</label>
                                <input type="text" name="company_manager" id="company_manager" class="form-control" placeholder="ชื่อ-นามสกุล ผู้จัดการ">
                            </div>
                        </div>
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
let usersTable;
let userModal;

$(document).ready(function() {
    usersTable = $('#usersTable').DataTable({
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/th.json'
        },
        pageLength: 25,
        order: [[1, 'asc']],
        columnDefs: [
            { orderable: false, targets: 7 }
        ]
    });
    
    // Initialize Bootstrap Modal after DOM & scripts loaded
    userModal = new bootstrap.Modal(document.getElementById('userModal'));
});


function toggleRoleFields() {
    const role = document.getElementById('role').value;
    const studentFields = document.getElementById('student_fields');
    if (role === 'student') {
        $(studentFields).slideDown();
    } else {
        $(studentFields).slideUp();
    }
}

function openUserModal() {
    document.getElementById('modalTitle').innerText = "เพิ่มผู้ใช้งานใหม่";
    document.getElementById('userForm').reset();
    document.getElementById('user_id').value = "0";
    document.getElementById('password').required = true;
    
    // Pre-select the role from GET parameter if available
    const currentTargetRole = "<?php echo htmlspecialchars($target_role); ?>";
    if(currentTargetRole !== "") {
        document.getElementById('role').value = currentTargetRole;
    }
    
    toggleRoleFields();
    userModal.show();
}

function editUser(id) {
    $.getJSON('get_user.php', { id: id }, function(data) {
        if (data.error) {
            Swal.fire('Error', data.error, 'error');
            return;
        }
        document.getElementById('modalTitle').innerText = "แก้ไขข้อมูลผู้ใช้งาน";
        document.getElementById('user_id').value = data.id;
        document.getElementById('username').value = data.username;
        document.getElementById('fullname').value = data.fullname;
        document.getElementById('email').value = data.email || '';
        document.getElementById('phone').value = data.phone || '';
        document.getElementById('affiliation').value = data.affiliation || '';
        document.getElementById('role').value = data.role;
        document.getElementById('student_code').value = data.student_code || '';
        document.getElementById('classroom_id').value = data.classroom_id || '';
        document.getElementById('student_level').value = data.student_level || '';
        document.getElementById('mentor_id').value = data.mentor_id || '';
        document.getElementById('mentor_id_2').value = data.mentor_id_2 || '';
        document.getElementById('mentor_id_3').value = data.mentor_id_3 || '';
        document.getElementById('mentor_id_4').value = data.mentor_id_4 || '';
        document.getElementById('mentor_id_5').value = data.mentor_id_5 || '';
        document.getElementById('company_manager').value = data.company_manager || '';
        document.getElementById('password').required = false;
        
        toggleRoleFields();
        userModal.show();
    });
}

$('#userForm').on('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    $.ajax({
        url: 'save_user.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            if (response.success) {
                userModal.hide();
                Swal.fire({
                    icon: 'success',
                    title: 'สำเร็จ',
                    text: response.message,
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => {
                    location.reload(); // Simplest way to refresh table with all joins
                });
            } else {
                Swal.fire('Error', response.message, 'error');
            }
        },
        error: function() {
            Swal.fire('Error', 'เกิดข้อผิดพลาดในการเชื่อมต่อ', 'error');
        }
    });
});

function deleteUser(id, name) {
    Swal.fire({
        title: 'ยืนยันการลบ?',
        text: `คุณกำลังจะลบผู้ใช้ "${name}" การกระทำนี้ไม่สามารถย้อนกลับได้`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'ยืนยันการลบ',
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        if (result.isConfirmed) {
            $.post('delete_user.php', { id: id }, function(response) {
                if (response.success) {
                    $(`#row-${id}`).fadeOut(400, function() {
                        usersTable.row($(this)).remove().draw(false);
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
