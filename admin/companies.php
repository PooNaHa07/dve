<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php';
require_login();
require_role(array('admin'));

$err = '';
$msg = isset($_GET['msg']) ? $_GET['msg'] : '';

// --- 1. จัดการการลบสถานประกอบการ ---
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    // Fetch info for Audit Log
    $c_q = $conn->query("SELECT name FROM companies WHERE id = $id");
    $target_comp = $c_q ? $c_q->fetch_assoc() : null;
    
    $stmt = $conn->prepare('DELETE FROM companies WHERE id=?');
    $stmt->bind_param('i', $id);
    if ($stmt->execute()) {
        if ($target_comp) {
            log_audit('DELETE_COMPANY', 'ลบสถานประกอบการ: ' . $target_comp['name']);
        }
        header('Location: companies.php?msg=deleted');
        exit;
    }
}

// --- 2. จัดการการเพิ่ม/แก้ไขสถานประกอบการ ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_company'])) {
    $id = intval($_POST['company_id']);
    $name = trim($_POST['name']); 
    $addr = trim($_POST['address']); 
    $contact = trim($_POST['contact_name']); 
    $phone = trim($_POST['contact_phone']); 
    $email = trim($_POST['contact_email']);

    if ($id == 0) { // เพิ่มใหม่
        $stmt = $conn->prepare('INSERT INTO companies (name, address, contact_name, contact_phone, contact_email) VALUES (?,?,?,?,?)');
        $stmt->bind_param('sssss', $name, $addr, $contact, $phone, $email);
    } else { // แก้ไข
        $stmt = $conn->prepare('UPDATE companies SET name=?, address=?, contact_name=?, contact_phone=?, contact_email=? WHERE id=?');
        $stmt->bind_param('sssssi', $name, $addr, $contact, $phone, $email, $id);
    }
    try {
        if ($stmt->execute()) {
            header('Location: companies.php?msg=success');
            exit;
        }
        $err = "เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . $conn->error;
    } catch (mysqli_sql_exception $e) {
        if ($e->getCode() == 1062) {
            $err = "ข้อมูลซ้ำกับในระบบ (เช่น ชื่อสถานประกอบการหรืออีเมลติดต่อมีแล้ว) กรุณาตรวจสอบและแก้ไข";
        } else {
            $err = "เกิดข้อผิดพลาดจากระบบ: " . $e->getMessage();
        }
    }
}

// --- 2b. AJAX: บันทึกสาขา ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_branch'])) {
    header('Content-Type: application/json');
    $bid       = (int)($_POST['branch_id'] ?? 0);
    $cid       = (int)($_POST['branch_company_id'] ?? 0);
    $label     = trim($_POST['branch_label'] ?? '');
    $cl_id     = (int)($_POST['branch_classroom_id'] ?? 0) ?: null;
    $b_addr    = trim($_POST['branch_address'] ?? '');
    $b_contact = trim($_POST['branch_contact_name'] ?? '');
    $b_phone   = trim($_POST['branch_contact_phone'] ?? '');
    $b_mgr     = trim($_POST['branch_manager_name'] ?? '');
    if (!$cid) { echo json_encode(['ok'=>false,'msg'=>'ไม่พบข้อมูลบริษัท']); exit; }
    if ($bid > 0) {
        $st = $conn->prepare('UPDATE company_branches SET branch_label=?,classroom_id=?,address=?,contact_name=?,contact_phone=?,manager_name=? WHERE id=? AND company_id=?');
        $st->bind_param('sissssii',$label,$cl_id,$b_addr,$b_contact,$b_phone,$b_mgr,$bid,$cid);
    } else {
        $st = $conn->prepare('INSERT INTO company_branches (company_id,branch_label,classroom_id,address,contact_name,contact_phone,manager_name) VALUES (?,?,?,?,?,?,?)');
        $st->bind_param('isissss',$cid,$label,$cl_id,$b_addr,$b_contact,$b_phone,$b_mgr);
    }
    $ok = $st->execute(); $st->close();
    echo json_encode(['ok'=>$ok,'msg'=>$ok?'บันทึกสาเร็จ':$conn->error]);
    exit;
}

// --- 2c. AJAX: ลบสาขา ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_branch'])) {
    header('Content-Type: application/json');
    $bid = (int)($_POST['branch_id'] ?? 0);
    $st  = $conn->prepare('DELETE FROM company_branches WHERE id=?');
    $st->bind_param('i',$bid); $ok = $st->execute(); $st->close();
    echo json_encode(['ok'=>$ok]);
    exit;
}

// --- 2d. AJAX: ดึง branches ของบริษัท ---
if (isset($_GET['get_branches'])) {
    header('Content-Type: application/json');
    $cid = (int)$_GET['get_branches'];
    $rows = $conn->query("SELECT b.*, cl.class_name FROM company_branches b LEFT JOIN classrooms cl ON cl.id=b.classroom_id WHERE b.company_id=$cid ORDER BY b.id ASC");
    $out = [];
    while ($r = $rows->fetch_assoc()) $out[] = $r;
    echo json_encode($out);
    exit;
}

// Pre-fetch classrooms for branch modal
$classrooms_for_branch = [];
$cl_res = $conn->query('SELECT id, class_name FROM classrooms ORDER BY class_name ASC');
while ($cl = $cl_res->fetch_assoc()) $classrooms_for_branch[] = $cl;

// --- 3. ดึงข้อมูลสถานประกอบการ ---
$search = isset($_GET['search']) ? $_GET['search'] : '';
$sql = "SELECT * FROM companies WHERE 1=1";
if (!empty($search)) {
    $s = "%$search%";
    $sql .= " AND (name LIKE '$s' OR contact_name LIKE '$s' OR contact_email LIKE '$s')";
}
$sql .= " ORDER BY id ASC";
$res = $conn->query($sql);
$hide_welcome = true;
include __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="../assets/css/admin-premium.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

<div class="container admin-content-wrapper">
    <?php if($msg || $err): ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var err = <?php echo $err ? json_encode($err) : 'null'; ?>;
            var msg = <?php echo $msg ? json_encode($msg) : 'null'; ?>;
            if (err) Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: err, confirmButtonText: 'ตกลง', confirmButtonColor: '#dc3545' });
            else if (msg === 'success') Swal.fire({ icon: 'success', title: 'สำเร็จ', text: 'บันทึกข้อมูลสำเร็จแล้ว', confirmButtonText: 'ตกลง', confirmButtonColor: '#4f46e5' });
            else if (msg === 'deleted') Swal.fire({ icon: 'success', title: 'ลบแล้ว', text: 'ลบข้อมูลเรียบร้อยแล้ว', confirmButtonText: 'ตกลง', confirmButtonColor: '#4f46e5' });
        });
        </script>
    <?php endif; ?>

    <div class="admin-header-section">
        <div>
            <h2 class="admin-header-title">
                <i class="fas fa-building"></i>
                จัดการสถานประกอบการ
            </h2>
        </div>
        <div class="d-flex gap-2">
            <a href="../roles/admin.php" class="btn-admin-outline text-decoration-none">
                <i class="fas fa-home me-1"></i> หน้าหลัก
            </a>
            <button class="btn-admin-primary" onclick="openCompanyModal()">
                <i class="fas fa-plus-circle me-1"></i> เพิ่มสถานประกอบการ
            </button>
        </div>
    </div>

    <div class="filter-strip">
        <form method="GET" class="row g-2 w-100 align-items-center">
            <div class="col-md-9 col-sm-8">
                <div class="input-group bg-light rounded-3 overflow-hidden border">
                    <span class="input-group-text bg-light border-0"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" name="search" class="form-control admin-form-control border-0 bg-light py-2" style="box-shadow:none;" placeholder="ค้นหาชื่อบริษัท, ที่อยู่ หรือผู้ติดต่อ..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
            </div>
            <div class="col-md-3 col-sm-4">
                <button type="submit" class="btn btn-dark w-100 fw-bold" style="border-radius: 0.6rem; padding: 0.6rem;">ค้นหาข้อมูล</button>
            </div>
        </form>
    </div>

    <div class="admin-table-container shadow-sm">
        <div class="table-responsive bg-white">
            <table id="companiesTable" class="table admin-table table-hover align-middle" style="width:100%">
                <thead>
                    <tr>
                        <th style="width: 80px;" class="text-center">ลำดับ</th>
                        <th style="width: 80px;">ID</th>
                        <th>ข้อมูลสถานประกอบการ</th>
                        <th>ผู้ติดต่อ</th>
                        <th>ช่องทางติดต่อ</th>
                        <th class="text-center" style="width: 120px;">การจัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($res && $res->num_rows > 0): 
                    $i = 1;
                    while($r = $res->fetch_assoc()): ?>
                    <tr>
                        <td class="text-center text-secondary fw-bold"><?php echo $i++; ?></td>
                        <td class="text-muted font-monospace small fw-bold" data-order="<?php echo $r['id']; ?>">#<?php echo $r['id']; ?></td>
                        <td>
                            <div class="fw-bold text-dark fs-6"><?php echo htmlspecialchars($r['name']); ?></div>
                            <div class="text-muted small mt-1"><i class="fas fa-map-marker-alt me-1 opacity-50"></i> <?php echo htmlspecialchars($r['address']); ?></div>
                        </td>
                        <td><span class="admin-badge bg-light text-dark border-secondary border-opacity-25"><?php echo htmlspecialchars($r['contact_name']); ?></span></td>
                        <td>
                            <div class="small mb-1"><i class="fas fa-phone me-2 text-primary opacity-75"></i><span class="fw-medium"><?php echo htmlspecialchars($r['contact_phone']); ?></span></div>
                            <div class="small text-muted"><i class="fas fa-envelope me-2 opacity-75"></i><?php echo htmlspecialchars($r['contact_email']); ?></div>
                        </td>
                        <td class="text-center">
                            <?php
                            $branch_cnt_q = $conn->query("SELECT COUNT(*) as c FROM company_branches WHERE company_id={$r['id']}");
                            $branch_cnt = $branch_cnt_q ? (int)$branch_cnt_q->fetch_assoc()['c'] : 0;
                            ?>
                            <div class="d-flex flex-column gap-1 align-items-center">
                                <div class="btn-group action-btn-group">
                                    <button class="btn btn-warning text-dark" onclick="editCompany(<?php echo htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8'); ?>)" title="แก้ไข">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-info text-white" onclick="manageBranches(<?php echo $r['id']; ?>, <?php echo htmlspecialchars(json_encode($r['name']), ENT_QUOTES, 'UTF-8'); ?>)" title="จัดการสาขา">
                                        <i class="fas fa-code-branch"></i>
                                    </button>
                                    <button class="btn btn-danger" onclick="confirmDelete(<?php echo $r['id']; ?>)" title="ลบ">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </div>
                                <?php if ($branch_cnt > 0): ?>
                                <span class="badge bg-purple text-white" style="background:#7c3aed;font-size:0.7rem;"><?php echo $branch_cnt; ?> สาขา</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="6" class="text-center py-5 text-muted"><i class="fas fa-folder-open d-block fs-3 mb-2 opacity-50"></i>ไม่พบข้อมูลสถานประกอบการ</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade admin-modal" id="companyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="" method="POST" class="modal-content border-0 shadow-lg">
            <div class="modal-header">
                <h5 class="modal-title d-flex align-items-center text-white">
                    <i class="fas fa-building me-2 opacity-75"></i>
                    <span id="modalTitle">ข้อมูลสถานประกอบการ</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-white">
                <input type="hidden" name="company_id" id="company_id" value="0">
                <div class="mb-4">
                    <label class="admin-form-label fw-bold mb-2 text-dark"><i class="fas fa-industry me-2 text-muted"></i>ชื่อสถานประกอบการ</label>
                    <input type="text" name="name" id="name" class="form-control admin-form-control" required placeholder="ระบุชื่อบริษัท/ห้างร้าน">
                </div>
                <div class="mb-4">
                    <label class="admin-form-label fw-bold mb-2 text-dark"><i class="fas fa-map-marked-alt me-2 text-muted"></i>ที่อยู่</label>
                    <textarea name="address" id="address" class="form-control admin-form-control" rows="3" placeholder="ระบุข้อมูลที่ตั้ง"></textarea>
                </div>
                <div class="row g-3 mb-4">
                    <div class="col-12">
                        <label class="admin-form-label fw-bold mb-2 text-dark"><i class="fas fa-user-tie me-2 text-muted"></i>ชื่อผู้ติดต่อ</label>
                        <input type="text" name="contact_name" id="contact_name" class="form-control admin-form-control" placeholder="ชื่อผู้ประสานงาน">
                    </div>
                    <div class="col-md-6">
                        <label class="admin-form-label fw-bold mb-2 text-dark"><i class="fas fa-phone-alt me-2 text-muted"></i>เบอร์โทรศัพท์</label>
                        <input type="text" name="contact_phone" id="contact_phone" class="form-control admin-form-control" placeholder="08x-xxxxxxx">
                    </div>
                    <div class="col-md-6">
                        <label class="admin-form-label fw-bold mb-2 text-dark"><i class="fas fa-envelope me-2 text-muted"></i>อีเมล</label>
                        <input type="email" name="contact_email" id="contact_email" class="form-control admin-form-control" placeholder="example@domain.com">
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top-0 bg-light px-4 py-3 d-flex justify-content-between">
                <button type="button" class="btn-admin-outline bg-white" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="submit" name="save_company" class="btn btn-admin-primary px-4">
                    <i class="fas fa-save me-2"></i>บันทึกข้อมูล
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Branch Management Modal -->
<div class="modal fade admin-modal" id="branchModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header" style="background:linear-gradient(135deg,#7c3aed,#4f46e5);">
                <h5 class="modal-title text-white d-flex align-items-center gap-2">
                    <i class="fas fa-code-branch"></i>
                    <span id="branchModalTitle">จัดการสาขา</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <!-- Add/Edit branch form -->
                <div class="card border-0 shadow-sm mb-4" style="border-radius:1rem;">
                    <div class="card-body p-3">
                        <h6 class="fw-bold mb-3" id="branch-form-title"><i class="fas fa-plus-circle text-success me-2"></i>เพิ่มสาขาใหม่</h6>
                        <input type="hidden" id="edit_branch_id" value="0">
                        <input type="hidden" id="edit_branch_company_id" value="0">
                        <div class="row g-2 mb-2">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">ชื่อสาขา / แผนก</label>
                                <input type="text" id="branch_label" class="form-control" placeholder="เช่น สาขาเพชรบุรี, ฝ่ายการตลาด">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">ห้องเรียน/สาขาวิชา (เชื่อมนักเรียน)</label>
                                <select id="branch_classroom_id" class="form-select">
                                    <option value="">-- ไม่ระบุ --</option>
                                    <?php foreach ($classrooms_for_branch as $cl): ?>
                                    <option value="<?= $cl['id'] ?>"><?= htmlspecialchars($cl['class_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small fw-bold">ที่อยู่สาขา (ถ้าต่างจากบริษัทหลัก)</label>
                            <textarea id="branch_address" class="form-control" rows="2" placeholder="ที่อยู่เฉพาะสาขานี้..."></textarea>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-md-4">
                                <label class="form-label small fw-bold">ชื่อผู้ติดต่อสาขา</label>
                                <input type="text" id="branch_contact_name" class="form-control" placeholder="ชื่อผู้ประสานงาน">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold">เบอร์โทรสาขา</label>
                                <input type="text" id="branch_contact_phone" class="form-control" placeholder="08x-xxxxxxx">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold">ผู้มีอำนาจลงนาม</label>
                                <input type="text" id="branch_manager_name" class="form-control" placeholder="ชื่อผู้จัดการ/ผู้อำนวยการ">
                            </div>
                        </div>
                        <div class="d-flex gap-2 mt-3">
                            <button class="btn btn-success fw-bold px-4" onclick="saveBranch()"><i class="fas fa-save me-2"></i>บันทึกสาขา</button>
                            <button class="btn btn-outline-secondary" onclick="clearBranchForm()">ล้างฟอร์ม</button>
                        </div>
                    </div>
                </div>
                <!-- Branch list -->
                <h6 class="fw-bold mb-2"><i class="fas fa-list me-2 text-primary"></i>รายการสาขา</h6>
                <div id="branch-list-container">
                    <div class="text-center text-muted py-3"><i class="fas fa-spinner fa-spin me-2"></i>กำลังโหลด...</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
var companyModal;
var branchModal;
var _currentBranchCompanyId = 0;
var _branchesMap = {};

function manageBranches(cid, cname) {
    _currentBranchCompanyId = cid;
    document.getElementById('branchModalTitle').textContent = 'จัดการสาขา: ' + cname;
    document.getElementById('edit_branch_company_id').value = cid;
    clearBranchForm();
    loadBranches(cid);
    branchModal.show();
}

function loadBranches(cid) {
    var container = document.getElementById('branch-list-container');
    container.innerHTML = '<div class="text-center text-muted py-3"><i class="fas fa-spinner fa-spin me-2"></i>กำลังโหลด...</div>';
    fetch('companies.php?get_branches=' + cid, {credentials:'same-origin'})
        .then(function(r){return r.json();})
        .then(function(list){
            _branchesMap = {};
            if (!list || list.length === 0) {
                container.innerHTML = '<div class="text-center text-muted py-4 border rounded-3"><i class="fas fa-folder-open d-block fs-3 mb-2 opacity-40"></i>ยังไม่มีสาขา<br><small class="text-muted">เพิ่มสาขาแรกด้านบน</small></div>';
                return;
            }
            var html = '<div class="list-group">';
            list.forEach(function(b) {
                _branchesMap[b.id] = b;
                html += '<div class="list-group-item d-flex justify-content-between align-items-start gap-3 mb-2" style="border-radius:0.75rem;border:1.5px solid #e9ecef;">';
                html += '<div class="flex-grow-1">';
                html += '<div class="fw-bold text-dark">' + escHtml(b.branch_label || '(ไม่มีชื่อสาขา)') + '</div>';
                if (b.class_name) html += '<span class="badge me-1" style="background:#ede9fe;color:#7c3aed;">' + escHtml(b.class_name) + '</span>';
                if (b.address)    html += '<div class="small text-muted mt-1"><i class="fas fa-map-marker-alt me-1"></i>' + escHtml(b.address.substring(0,80)) + '</div>';
                if (b.contact_name) html += '<div class="small text-muted"><i class="fas fa-user me-1"></i>' + escHtml(b.contact_name) + (b.contact_phone ? ' &nbsp;' + escHtml(b.contact_phone) : '') + '</div>';
                if (b.manager_name) html += '<div class="small text-muted"><i class="fas fa-signature me-1"></i>ผู้ลงนาม: ' + escHtml(b.manager_name) + '</div>';
                html += '</div>';
                html += '<div class="d-flex gap-1 flex-shrink-0">';
                html += '<button class="btn btn-sm btn-warning text-dark" onclick="editBranch(' + b.id + ')" title="แก้ไข"><i class="fas fa-edit"></i></button>';
                html += '<button class="btn btn-sm btn-danger" onclick="deleteBranch(' + b.id + ')" title="ลบ"><i class="fas fa-trash"></i></button>';
                html += '</div>';
                html += '</div>';
            });
            html += '</div>';
            container.innerHTML = html;
        })
        .catch(function(){container.innerHTML='<div class="text-danger p-3">โหลดข้อมูลไม่สำเร็จ</div>';});
}

function clearBranchForm() {
    document.getElementById('edit_branch_id').value = '0';
    document.getElementById('branch-form-title').innerHTML = '<i class="fas fa-plus-circle text-success me-2"></i>เพิ่มสาขาใหม่';
    ['branch_label','branch_address','branch_contact_name','branch_contact_phone','branch_manager_name'].forEach(function(id){
        var el = document.getElementById(id); if(el) el.value='';
    });
    var sel = document.getElementById('branch_classroom_id');
    if (sel) sel.value = '';
}

function editBranch(bid) {
    var b = _branchesMap[bid];
    if (!b) return;
    document.getElementById('edit_branch_id').value = b.id;
    document.getElementById('branch_label').value = b.branch_label || '';
    document.getElementById('branch_classroom_id').value = b.classroom_id || '';
    document.getElementById('branch_address').value = b.address || '';
    document.getElementById('branch_contact_name').value = b.contact_name || '';
    document.getElementById('branch_contact_phone').value = b.contact_phone || '';
    document.getElementById('branch_manager_name').value = b.manager_name || '';
    document.getElementById('branch-form-title').innerHTML = '<i class="fas fa-edit text-warning me-2"></i>แก้ไขสาขา: ' + escHtml(b.branch_label || '');
    document.getElementById('branch_label').focus();
}

function saveBranch() {
    var fd = new FormData();
    fd.append('save_branch','1');
    fd.append('branch_id', document.getElementById('edit_branch_id').value);
    fd.append('branch_company_id', document.getElementById('edit_branch_company_id').value);
    fd.append('branch_label', document.getElementById('branch_label').value);
    fd.append('branch_classroom_id', document.getElementById('branch_classroom_id').value);
    fd.append('branch_address', document.getElementById('branch_address').value);
    fd.append('branch_contact_name', document.getElementById('branch_contact_name').value);
    fd.append('branch_contact_phone', document.getElementById('branch_contact_phone').value);
    fd.append('branch_manager_name', document.getElementById('branch_manager_name').value);
    fetch('companies.php', {method:'POST', body:fd, credentials:'same-origin'})
        .then(function(r){return r.json();})
        .then(function(res){
            if (res.ok) {
                clearBranchForm();
                loadBranches(_currentBranchCompanyId);
                Swal.fire({icon:'success',title:'บันทึกแล้ว',timer:1200,showConfirmButton:false});
            } else {
                Swal.fire({icon:'error',title:'ผิดพลาด',text:res.msg || 'ไม่สามารถบันทึกได้'});
            }
        })
        .catch(function(){ Swal.fire({icon:'error',title:'เกิดข้อผิดพลาด'}); });
}

function deleteBranch(bid) {
    Swal.fire({title:'ลบสาขา?',text:'ยืนยันการลบสาขานี้?',icon:'warning',showCancelButton:true,confirmButtonColor:'#dc3545',cancelButtonText:'ยกเลิก',confirmButtonText:'ลบเลย'})
        .then(function(r){
            if (!r.isConfirmed) return;
            var fd = new FormData(); fd.append('delete_branch','1'); fd.append('branch_id',bid);
            fetch('companies.php',{method:'POST',body:fd,credentials:'same-origin'})
                .then(function(r){return r.json();})
                .then(function(res){ if(res.ok) loadBranches(_currentBranchCompanyId); });
        });
}

function escHtml(str) {
    return String(str||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function confirmDelete(id) {
    Swal.fire({ 
        title: 'ยืนยันการลบ?', 
        text: 'ยืนยันการลบข้อมูลสถานประกอบการนี้? การกระทำนี้ไม่สามารถเรียกคืนได้', 
        icon: 'warning', 
        showCancelButton: true, 
        confirmButtonColor: '#dc3545', 
        cancelButtonColor: '#6c757d', 
        confirmButtonText: 'ลบเลย!', 
        cancelButtonText: 'ยกเลิก',
        borderRadius: '1rem'
    }).then(function(r) { 
        if (r.isConfirmed) window.location.href = '?delete=' + id; 
    });
}

function openCompanyModal() {
    document.getElementById('modalTitle').innerText = "เพิ่มสถานประกอบการใหม่";
    document.getElementById('company_id').value = 0;
    document.getElementById('name').value = "";
    document.getElementById('address').value = "";
    document.getElementById('contact_name').value = "";
    document.getElementById('contact_phone').value = "";
    document.getElementById('contact_email').value = "";
    companyModal.show();
}

function editCompany(data) {
    document.getElementById('modalTitle').innerText = "แก้ไขข้อมูลสถานประกอบการ";
    document.getElementById('company_id').value = data.id;
    document.getElementById('name').value = data.name;
    document.getElementById('address').value = data.address;
    document.getElementById('contact_name').value = data.contact_name;
    document.getElementById('contact_phone').value = data.contact_phone;
    document.getElementById('contact_email').value = data.contact_email;
    companyModal.show();
}

document.addEventListener('DOMContentLoaded', function() {
    companyModal = new bootstrap.Modal(document.getElementById('companyModal'));
    branchModal  = new bootstrap.Modal(document.getElementById('branchModal'));
    var dtLang = { 
        search: 'ค้นหา:', 
        lengthMenu: 'แสดง _MENU_ รายการ', 
        info: 'แสดง _START_ ถึง _END_ จาก _TOTAL_ รายการ', 
        infoEmpty: 'ไม่มีข้อมูล', 
        infoFiltered: '(กรองจาก _MAX_ รายการ)', 
        paginate: { first: 'แรก', last: 'ท้าย', next: 'ถัดไป', previous: 'ก่อนหน้า' }, 
        zeroRecords: 'ไม่พบข้อมูล' 
    };
    var tbl = document.getElementById('companiesTable');
    if (tbl && tbl.querySelector('tbody tr') && !tbl.querySelector('tbody tr td[colspan]')) {
        $(tbl).DataTable({ 
            order: [[1, 'asc']], 
            language: dtLang, 
            pageLength: 15,
            dom: '<"d-flex flex-column flex-md-row justify-content-between mb-3"lf>rt<"d-flex flex-column flex-md-row justify-content-between mt-3"ip>',
            columnDefs: [
                { orderable: false, targets: 5 }
            ]
        });
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>