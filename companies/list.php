<?php
// companies/list.php - หน้าจัดการรายการบริษัท (Modal Version)
date_default_timezone_set('Asia/Bangkok');
require_once __DIR__ . '/../includes/functions.php'; 
require_once __DIR__ . '/../includes/configdb.php'; 

global $conn;

// 1. ตรวจสอบสิทธิ์การจัดการ (Staff/Admin เท่านั้นที่เพิ่ม/แก้ไข/ลบได้)
$can_manage = is_logged_in() && in_array(get_current_role(), ['staff', 'admin']);

$error_msg = '';
$success_msg = '';

// 2. Handle POST Actions (Add/Edit)
if ($can_manage && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = isset($_POST['company_id']) ? (int)$_POST['company_id'] : 0;
    
    $name = trim($_POST['name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $contact_name = trim($_POST['contact_name'] ?? '');
    $contact_phone = trim($_POST['contact_phone'] ?? '');
    $contact_email = trim($_POST['contact_email'] ?? '');

    if (empty($name) || empty($contact_name) || empty($contact_phone) || empty($contact_email)) {
        $error_msg = "กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วน (*)";
    } else {
        if ($action === 'add') {
            $stmt = $conn->prepare("INSERT INTO companies (name, address, contact_name, contact_phone, contact_email, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("sssss", $name, $address, $contact_name, $contact_phone, $contact_email);
            if ($stmt->execute()) {
                $_SESSION['success_message'] = "เพิ่มสถานประกอบการสำเร็จ!";
                header("Location: list.php");
                exit;
            } else {
                $error_msg = "เกิดข้อผิดพลาด: " . ($conn->errno == 1062 ? "ข้อมูลซ้ำในระบบ" : $conn->error);
            }
        } elseif ($action === 'edit' && $id > 0) {
            $stmt = $conn->prepare("UPDATE companies SET name = ?, address = ?, contact_name = ?, contact_phone = ?, contact_email = ? WHERE id = ?");
            $stmt->bind_param("sssssi", $name, $address, $contact_name, $contact_phone, $contact_email, $id);
            if ($stmt->execute()) {
                $_SESSION['success_message'] = "อัปเดตข้อมูลสถานประกอบการสำเร็จ!";
                header("Location: list.php");
                exit;
            } else {
                $error_msg = "เกิดข้อผิดพลาด: " . $conn->error;
            }
        }
    }
}

// 3. รับค่าการค้นหาและสร้าง WHERE clause
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$where_clause = '';
if (!empty($search)) {
    $search_term = "%" . $conn->real_escape_string($search) . "%";
    $where_clause = " WHERE name LIKE '$search_term' OR address LIKE '$search_term' OR contact_name LIKE '$search_term'";
}

$page_title = 'สถานประกอบการ';

// Smart Header
if (is_logged_in() && in_array(get_current_role(), ['admin', 'staff', 'officer', 'teacher', 'director'])) {
    $hide_welcome = true;
    include __DIR__ . '/../includes/header.php';
} else {
    include __DIR__ . '/../includes/header_public.php';
}
?>

<!-- Datatable and Premium Styling dependencies -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

<style>
    .bg-blob {
        position: fixed;
        width: 600px;
        height: 600px;
        filter: blur(140px);
        z-index: -1;
        opacity: 0.12;
        pointer-events: none;
        border-radius: 50%;
        animation: floatBlob 18s infinite alternate ease-in-out;
    }
    .blob-1 { background: #6366f1; top: -150px; right: -150px; }
    .blob-2 { background: #3b82f6; bottom: -150px; left: -150px; animation-delay: -5s; }

    @keyframes floatBlob {
        0% { transform: translate(0, 0) scale(1); }
        100% { transform: translate(80px, 50px) scale(1.1); }
    }

    .company-header-section {
        padding: 3rem 1rem 1.5rem;
        text-align: center;
        position: relative;
    }

    .company-icon-box {
        width: 80px;
        height: 80px;
        background: linear-gradient(135deg, #6366f1 0%, #3b82f6 100%);
        border-radius: 22px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 2.25rem;
        margin-bottom: 1.25rem;
        box-shadow: 0 15px 30px rgba(99, 102, 241, 0.25);
        color: white;
    }

    .company-title {
        font-size: 2.25rem;
        font-weight: 850;
        letter-spacing: -0.5px;
        color: #0f172a;
        margin-bottom: 0.5rem;
    }

    .company-subtitle {
        font-size: 1.05rem;
        color: #64748b;
        max-width: 600px;
        margin: 0 auto;
        font-weight: 500;
    }

    .glass-table-card {
        background: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(15px);
        -webkit-backdrop-filter: blur(15px);
        border: 1px solid rgba(226, 232, 240, 0.8);
        border-radius: 24px;
        box-shadow: 0 20px 45px rgba(0,0,0,0.04);
        padding: 1.75rem;
        overflow: hidden;
        margin-bottom: 4rem;
    }

    .table th {
        background-color: rgba(248, 250, 252, 0.9) !important;
        color: #475569 !important;
        font-weight: 700;
        font-size: 0.95rem;
        padding: 1.1rem 1rem !important;
        border-bottom: 2px solid #f1f5f9 !important;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .table td {
        padding: 1.25rem 1rem !important;
        vertical-align: middle;
        font-size: 0.95rem;
        color: #334155;
        border-bottom: 1px solid #f1f5f9;
        transition: background-color 0.2s ease;
    }

    .btn-action-view {
        background: rgba(14, 165, 233, 0.08);
        color: #0284c7;
        border: none;
        border-radius: 10px;
        padding: 0.45rem 1rem;
        font-weight: 700;
        font-size: 0.85rem;
        transition: all 0.25s;
        text-decoration: none;
    }

    .btn-action-view:hover {
        background: #0284c7;
        color: white;
    }

    .btn-action-edit {
        background: rgba(99, 102, 241, 0.08);
        color: #4f46e5;
        border: none;
        border-radius: 10px;
        padding: 0.45rem 1rem;
        font-weight: 700;
        font-size: 0.85rem;
        transition: all 0.25s;
        cursor: pointer;
    }

    .btn-action-edit:hover {
        background: #4f46e5;
        color: white;
    }

    .btn-action-delete {
        background: rgba(239, 68, 68, 0.08);
        color: #dc2626;
        border: none;
        border-radius: 10px;
        padding: 0.45rem 1rem;
        font-weight: 700;
        font-size: 0.85rem;
        transition: all 0.25s;
        text-decoration: none;
    }

    .btn-action-delete:hover {
        background: #dc2626;
        color: white;
    }

    .custom-search-input {
        background: white;
        border: 1px solid rgba(226, 232, 240, 0.8);
        border-radius: 14px;
        padding: 0.5rem 1.25rem;
        font-size: 0.95rem;
        width: 100%;
        max-width: 400px;
        font-weight: 500;
        outline: none;
        transition: all 0.3s;
    }

    .custom-search-input:focus {
        border-color: #6366f1;
        box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
    }
</style>

<div class="bg-blob blob-1"></div>
<div class="bg-blob blob-2"></div>

<div class="container py-4">

    <!-- Premium Page Title Header -->
    <div class="company-header-section animate-fade-in">
        <div class="company-icon-box">
            <i class="bi bi-building"></i>
        </div>
        <h1 class="company-title">จัดการสถานประกอบการ</h1>
        <p class="company-subtitle">ทำเนียบรายชื่อบริษัทคู่ค้าและสถานประกอบการที่ร่วมจัดการอาชีวศึกษาระบบทวิภาคี</p>
    </div>

    <!-- Actions & Filter Row -->
    <div class="action-row d-flex flex-column flex-md-row justify-content-between align-items-center gap-3 mb-4">
        <form method="GET" action="list.php" class="w-100 max-width-400 d-flex gap-2">
            <input type="text" name="search" class="custom-search-input" placeholder="ค้นชื่อบริษัท, ที่อยู่, ผู้ติดต่อ..." 
                   value="<?= htmlspecialchars($search) ?>">
            <button class="btn btn-primary rounded-3 px-3" type="submit">
                <i class="bi bi-search"></i>
            </button>
            <?php if (!empty($search)): ?>
                <a href="list.php" class="btn btn-outline-secondary rounded-3">ล้าง</a>
            <?php endif; ?>
        </form>

        <div class="d-flex align-items-center gap-2 w-100 justify-content-md-end justify-content-center flex-wrap">
            <a href="export.php?search=<?= urlencode($search) ?>" class="btn btn-success rounded-3 px-3 py-2 fw-bold d-flex align-items-center gap-2">
                <i class="bi bi-file-earmark-excel"></i> ส่งออก CSV
            </a>
            
            <?php if ($can_manage): ?>
                <button type="button" class="btn btn-primary rounded-3 px-3 py-2 fw-bold d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#addCompanyModal">
                    <i class="bi bi-plus-circle"></i> เพิ่มบริษัท
                </button>
            <?php endif; ?>

            <a href="<?= is_logged_in() ? '../roles/' . get_current_role() . '.php' : '../Landing-Page.php' ?>" class="btn btn-outline-secondary rounded-3 px-3 py-2 fw-bold d-flex align-items-center gap-2 bg-white">
                <i class="bi bi-arrow-left"></i> กลับหน้ารวม
            </a>
        </div>
    </div>

    <!-- Flash Messages -->
    <?php 
    if(isset($_SESSION['success_message'])) {
        echo '<div class="alert alert-success border-0 shadow-sm rounded-3 py-3 mb-4"><i class="bi bi-check-circle-fill me-2"></i>'.e($_SESSION['success_message']).'</div>';
        unset($_SESSION['success_message']);
    }
    if($error_msg) {
        echo '<div class="alert alert-danger border-0 shadow-sm rounded-3 py-3 mb-4"><i class="bi bi-exclamation-circle-fill me-2"></i>'.e($error_msg).'</div>';
    }
    ?>

    <!-- Glassmorphic Data Table Card -->
    <div class="glass-table-card">
        <div class="table-responsive">
            <table id="companiesListTable" class="table table-hover mb-0" style="width:100%; min-width: 700px;">
                <thead>
                    <tr>
                        <th style="width: 80px;" class="text-center">ลำดับ</th>
                        <th style="width: 80px;">ID</th>
                        <th>ข้อมูลบริษัทคู่ค้า</th>
                        <th>ผู้ติดต่อประสานงาน</th>
                        <th class="d-none d-lg-table-cell">ข้อมูลการติดต่อ</th>
                        <th class="text-center" style="width: 260px;">การจัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sql = "SELECT id, name, address, contact_name, contact_phone, contact_email
                            FROM companies {$where_clause}
                            ORDER BY id ASC";

                    $result = $conn->query($sql);
                    if ($result && $result->num_rows > 0): 
                        $i = 1;
                        while ($company = $result->fetch_assoc()): ?>
                            <tr>
                                <td class="text-center text-secondary fw-bold"><?= $i++ ?></td>
                                <td data-order="<?= $company['id'] ?>"><span class="badge-id">#<?= e($company['id']) ?></span></td>
                                <td>
                                    <span class="company-name-highlight"><?= e($company['name']) ?></span>
                                    <span class="company-address-sub" title="<?= e($company['address']) ?>">
                                        <i class="bi bi-geo-alt"></i> <?= !empty($company['address']) ? e($company['address']) : 'ไม่ระบุที่อยู่' ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark"><?= e($company['contact_name']) ?></div>
                                    <div class="small text-muted"><i class="bi bi-telephone"></i> <?= e($company['contact_phone']) ?></div>
                                </td>
                                <td class="d-none d-lg-table-cell">
                                    <span class="d-block small text-dark"><i class="bi bi-envelope"></i> <?= !empty($company['contact_email']) ? e($company['contact_email']) : '-' ?></span>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex align-items-center justify-content-center gap-1 flex-wrap">
                                        <a href="../detail_company.php?id=<?= $company['id'] ?>" class="btn-action-view" title="ดูรายละเอียด">
                                            <i class="bi bi-eye"></i> ดู
                                        </a>
                                        <a href="../director/view_supervision.php?company_id=<?= $company['id'] ?>" class="btn-action-view" style="background:rgba(147,51,234,0.08);color:#9333ea;" title="รายงานการนิเทศก์">
                                            <i class="bi bi-file-earmark-bar-graph"></i> รายงานนิเทศ
                                        </a>
                                        <?php if ($can_manage): ?>
                                            <button type="button" class="btn-action-edit edit-company-btn" data-id="<?= $company['id'] ?>" title="แก้ไข">
                                                <i class="bi bi-pencil-square"></i>
                                            </button>
                                            <a href="delete_company.php?id=<?= $company['id'] ?>" class="btn-action-delete btn-delete-company" data-name="<?= e($company['name']) ?>" title="ลบ">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Add Company -->
<div class="modal fade" id="addCompanyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 p-4 pb-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-building-plus text-primary me-2"></i>เพิ่มสถานประกอบการใหม่</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold text-secondary">ชื่อบริษัท <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control border-0 bg-light rounded-3" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-secondary">ที่อยู่</label>
                        <textarea name="address" class="form-control border-0 bg-light rounded-3" rows="3"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold text-secondary">ชื่อผู้ติดต่อ <span class="text-danger">*</span></label>
                            <input type="text" name="contact_name" class="form-control border-0 bg-light rounded-3" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold text-secondary">เบอร์โทรศัพท์ <span class="text-danger">*</span></label>
                            <input type="text" name="contact_phone" class="form-control border-0 bg-light rounded-3" required>
                        </div>
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-bold text-secondary">อีเมลติดต่อ <span class="text-danger">*</span></label>
                        <input type="email" name="contact_email" class="form-control border-0 bg-light rounded-3" required>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-5 shadow">บันทึกข้อมูล</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Edit Company -->
<div class="modal fade" id="editCompanyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header border-0 p-4 pb-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square text-primary me-2"></i>แก้ไขข้อมูลสถานประกอบการ</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="company_id" id="edit_company_id">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold text-secondary">ชื่อบริษัท <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="edit_name" class="form-control border-0 bg-light rounded-3" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-secondary">ที่อยู่</label>
                        <textarea name="address" id="edit_address" class="form-control border-0 bg-light rounded-3" rows="3"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold text-secondary">ชื่อผู้ติดต่อ <span class="text-danger">*</span></label>
                            <input type="text" name="contact_name" id="edit_contact_name" class="form-control border-0 bg-light rounded-3" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold text-secondary">เบอร์โทรศัพท์ <span class="text-danger">*</span></label>
                            <input type="text" name="contact_phone" id="edit_contact_phone" class="form-control border-0 bg-light rounded-3" required>
                        </div>
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-bold text-secondary">อีเมลติดต่อ <span class="text-danger">*</span></label>
                        <input type="email" name="contact_email" id="edit_contact_email" class="form-control border-0 bg-light rounded-3" required>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-5 shadow">บันทึกการแก้ไข</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    var tbl = $('#companiesListTable').DataTable({ 
        order: [[1, 'asc']], 
        language: { 
            search: 'ค้นหา:', 
            lengthMenu: 'แสดง _MENU_ รายการ', 
            info: 'แสดง _START_ ถึง _END_ จาก _TOTAL_', 
            paginate: { next: 'ถัดไป', previous: 'ก่อนหน้า' }
        },
        columnDefs: [
            { orderable: false, targets: 5 }
        ]
    });

    // Edit Modal Fetch
    $('.edit-company-btn').on('click', function() {
        var id = $(this).data('id');
        $.get('get_company.php', { id: id }, function(data) {
            if(data.error) return Swal.fire('Error', data.error, 'error');
            $('#edit_company_id').val(data.id);
            $('#edit_name').val(data.name);
            $('#edit_address').val(data.address);
            $('#edit_contact_name').val(data.contact_name);
            $('#edit_contact_phone').val(data.contact_phone);
            $('#edit_contact_email').val(data.contact_email);
            $('#editCompanyModal').modal('show');
        });
    });

    // Delete Logic
    $('.btn-delete-company').on('click', function(e) {
        e.preventDefault();
        var h = $(this).attr('href');
        var n = $(this).data('name');
        Swal.fire({
            title: 'ลบบริษัท?',
            text: 'ต้องการลบบริษัท "' + n + '" ใช่หรือไม่?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'ลบเลย'
        }).then((result) => { if (result.isConfirmed) window.location.href = h; });
    });
});
</script>

<?php
if (isset($result) && $result) {
    $result->free();
}
include __DIR__ . '/../includes/footer.php';
include __DIR__ . '/../includes/footer_close.php';
?>