<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/configdb.php';

$page_title = 'ดาวน์โหลดเอกสาร';

// Smart Header: If Staff/Admin is logged in, show the App Header. Otherwise show Public Header.
if (is_logged_in() && in_array(get_current_role(), ['admin', 'staff', 'officer', 'teacher', 'director'])) {
    // For logged-in management roles, use the premium app header
    $hide_welcome = true; // Don't show the big welcome banner from header.php
    include __DIR__ . '/includes/header.php';
} else {
    // For guests or students, use the landing page header
    include __DIR__ . '/includes/header_public.php';
}
?>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

<style>
    /* Animated Floating Blobs Background matching other pages */
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

    /* Premium Header Customisation */
    .doc-list-container {
        margin-top: 0;
        margin-bottom: 6rem;
    }

    .doc-header-box {
        text-align: center;
        padding: 3rem 1rem 1.5rem;
        position: relative;
    }

    .doc-icon-circle {
        width: 80px;
        height: 80px;
        background: linear-gradient(135deg, #6366f1 0%, #3b82f6 100%);
        border-radius: 22px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 2.25rem;
        color: white;
        margin-bottom: 1.25rem;
        box-shadow: 0 15px 30px rgba(99, 102, 241, 0.25);
    }

    .doc-title {
        font-size: 2.25rem;
        font-weight: 850;
        color: #0f172a;
        letter-spacing: -0.5px;
        margin-bottom: 0.5rem;
    }

    .doc-subtitle {
        font-size: 1.05rem;
        color: #64748b;
        font-weight: 500;
        max-width: 600px;
        margin: 0 auto;
    }

    /* Elegant Glassmorphic Container for Table */
    .glass-table-card {
        background: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid rgba(226, 232, 240, 0.8);
        border-radius: 28px;
        padding: 2.5rem;
        box-shadow: 0 25px 60px rgba(0,0,0,0.04);
    }

    /* Actions Header Buttons Styling */
    .btn-add-doc {
        background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
        color: white !important;
        border: none;
        border-radius: 12px;
        padding: 0.65rem 1.5rem;
        font-weight: 700;
        font-size: 0.9rem;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.25s;
        box-shadow: 0 4px 12px rgba(99, 102, 241, 0.2);
    }

    .btn-add-doc:hover {
        background: linear-gradient(135deg, #4f46e5 0%, #3730a3 100%);
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(79, 70, 229, 0.3);
    }

    .btn-back-home {
        background: #f8fafc;
        color: #475569 !important;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 0.65rem 1.5rem;
        font-weight: 700;
        font-size: 0.9rem;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.25s;
    }

    .btn-back-home:hover {
        background: #f1f5f9;
        border-color: #cbd5e1;
        color: #1e293b !important;
    }

    /* Table visual refinements */
    .premium-doc-table thead th {
        background-color: #1e293b !important;
        color: white !important;
        font-weight: 700;
        text-transform: uppercase;
        font-size: 0.85rem;
        letter-spacing: 0.5px;
        padding: 1.25rem 1rem !important;
        border-bottom: none !important;
    }

    .premium-doc-table tbody tr {
        transition: background-color 0.2s;
    }

    .premium-doc-table tbody tr:hover {
        background-color: rgba(99, 102, 241, 0.03) !important;
    }

    .premium-doc-table td {
        padding: 1.1rem 1rem !important;
        vertical-align: middle;
        font-size: 0.95rem;
        color: #334155;
        border-bottom: 1px solid #f1f5f9 !important;
    }

    /* Doc specific styling badges */
    .doc-badge-number {
        background: #f1f5f9;
        color: #64748b;
        font-weight: 700;
        font-size: 0.85rem;
        padding: 0.35rem 0.65rem;
        border-radius: 8px;
    }

    .doc-file-icon {
        color: #3b82f6;
        font-size: 1.2rem;
        margin-right: 0.5rem;
    }

    .doc-date-text {
        color: #64748b;
        font-weight: 500;
        font-size: 0.9rem;
    }

    /* Action table button designs */
    .btn-download-doc {
        background: rgba(16, 185, 129, 0.1);
        color: #10b981 !important;
        border: 1px solid rgba(16, 185, 129, 0.2);
        font-weight: 700;
        padding: 0.45rem 1.15rem;
        border-radius: 10px;
        font-size: 0.85rem;
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        transition: all 0.2s;
    }

    .btn-download-doc:hover {
        background: #10b981;
        color: white !important;
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
    }

    .btn-edit-inline {
        background: rgba(245, 158, 11, 0.1);
        color: #f59e0b !important;
        border: 1px solid rgba(245, 158, 11, 0.2);
        font-weight: 700;
        padding: 0.45rem 0.95rem;
        border-radius: 10px;
        font-size: 0.85rem;
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        transition: all 0.2s;
    }

    .btn-edit-inline:hover {
        background: #f59e0b;
        color: white !important;
        box-shadow: 0 4px 12px rgba(245, 158, 11, 0.2);
    }

    .btn-delete-inline {
        background: rgba(239, 68, 68, 0.1);
        color: #ef4444 !important;
        border: 1px solid rgba(239, 68, 68, 0.2);
        font-weight: 700;
        padding: 0.45rem 0.95rem;
        border-radius: 10px;
        font-size: 0.85rem;
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        transition: all 0.2s;
    }

    .btn-delete-inline:hover {
        background: #ef4444;
        color: white !important;
        box-shadow: 0 4px 12px rgba(239, 68, 68, 0.2);
    }

    /* Customise DataTable elements to be rounded & sleek */
    .dataTables_wrapper .dataTables_filter input {
        border: 1px solid #cbd5e1;
        border-radius: 10px;
        padding: 0.4rem 0.75rem;
        outline: none;
    }

    .dataTables_wrapper .dataTables_filter input:focus {
        border-color: #6366f1;
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
    }

    .dataTables_wrapper .dataTables_length select {
        border: 1px solid #cbd5e1;
        border-radius: 10px;
        padding: 0.4rem 1.5rem 0.4rem 0.75rem;
    }

    .page-link {
        border-radius: 8px !important;
        margin: 0 2px;
        border-color: #e2e8f0;
        color: #4f46e5;
    }

    .active>.page-link {
        background-color: #4f46e5 !important;
        border-color: #4f46e5 !important;
    }
</style>

<div class="bg-blob blob-1"></div>
<div class="bg-blob blob-2"></div>

<?php
// ตรวจสอบสิทธิ์เจ้าหน้าที่ (Admin หรือ Staff)
$isAdmin = isset($_SESSION['role']) && ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'staff');

// ฟังก์ชันสำหรับแปลงขนาดไฟล์
function formatSize($bytes) {
    if ($bytes >= 1073741824) { $bytes = number_format($bytes / 1073741824, 2) . ' GB'; }
    elseif ($bytes >= 1048576) { $bytes = number_format($bytes / 1048576, 2) . ' MB'; }
    elseif ($bytes >= 1024) { $bytes = number_format($bytes / 1024, 2) . ' KB'; }
    elseif ($bytes > 1) { $bytes = $bytes . ' bytes'; }
    elseif ($bytes == 1) { $bytes = $bytes . ' byte'; }
    else { $bytes = '0 bytes'; }
    return $bytes;
}

// ฟังก์ชันสำหรับดึง Icon ตามนามสกุลไฟล์
function getFileIcon($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    switch($ext) {
        case 'pdf': return 'bi-file-earmark-pdf-fill text-danger';
        case 'doc':
        case 'docx': return 'bi-file-earmark-word-fill text-primary';
        case 'xls':
        case 'xlsx': return 'bi-file-earmark-excel-fill text-success';
        case 'ppt':
        case 'pptx': return 'bi-file-earmark-slides-fill text-warning';
        case 'zip':
        case 'rar': return 'bi-file-earmark-zip-fill text-secondary';
        case 'jpg':
        case 'jpeg':
        case 'png': return 'bi-file-earmark-image-fill text-info';
        default: return 'bi-file-earmark-fill text-secondary';
    }
}

// ดึงข้อมูลเอกสารพร้อมชื่อผู้อัปโหลด
$documents = [];
try {
    $sql = "SELECT d.*, u.fullname as uploader_name 
            FROM documents d 
            LEFT JOIN users u ON d.uploaded_by = u.id 
            ORDER BY d.created_at DESC";
    $result = $conn->query($sql);
    $documents = ($result && $result->num_rows > 0) ? $result->fetch_all(MYSQLI_ASSOC) : [];
} catch (Exception $e) {
    // ตาราง documents อาจมีปัญหา InnoDB Tablespace — แสดงข้อความแทน crash
    $documents = [];
    $db_error = "⚠️ ไม่สามารถโหลดรายการเอกสารได้ในขณะนี้ กรุณาติดต่อผู้ดูแลระบบ";
}
?>

<div class="container doc-list-container">
    <!-- Header visual block -->
    <div class="doc-header-box">
        <div class="doc-icon-circle">
            <i class="bi bi-file-earmark-arrow-down-fill"></i>
        </div>
        <h1 class="doc-title">คลังเอกสารดาวน์โหลด</h1>
        <p class="doc-subtitle">ดาวน์โหลดเอกสาร คู่มือ และแบบฟอร์มการฝึกงานที่จำเป็นครบครันในที่เดียว</p>
    </div>

    <!-- Glassmorphic Table Card -->
    <div class="glass-table-card">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4 pb-2">
            <div>
                <h5 class="fw-bold text-slate-800 mb-0">รายการเอกสารดาวน์โหลดทั้งหมด</h5>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <?php if ($isAdmin): ?>
                    <button type="button" class="btn-add-doc btn-add-doc-modal">
                        <i class="bi bi-file-earmark-plus-fill"></i> เพิ่มเอกสารใหม่
                    </button>
                <?php endif; ?>

                <a href="/DVE_DATA_FULL/roles/staff.php" class="btn-back-home">
                    <i class="bi bi-house-door-fill"></i> กลับหน้าหลัก
                </a>
            </div>
        </div>

        <div class="table-responsive">
<?php if (!empty($db_error)): ?>
        <div class="alert alert-warning d-flex align-items-center gap-3 rounded-3 mb-3">
            <i class="bi bi-exclamation-triangle-fill fs-4"></i>
            <div><?= htmlspecialchars($db_error) ?></div>
        </div>
<?php else: ?>
            <table id="documentsListTable" class="table premium-doc-table" style="width:100%">
                <thead>
                    <tr>
                        <th width="5%">#</th>
                        <th width="35%">ชื่อเอกสาร / ประเภท</th>
                        <th width="15%">ขนาดไฟล์</th>
                        <th width="15%">ผู้อัปโหลด</th>
                        <th width="15%">วันที่อัปโหลด</th>
                        <th width="15%" class="text-center">ดำเนินการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($documents)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">
                                <i class="bi bi-file-earmark-x fs-2 d-block mb-2"></i> ยังไม่มีเอกสารในระบบขณะนี้
                            </td>
                        </tr>
                    <?php else: $i = 1; foreach ($documents as $doc): 
                        $filePath = __DIR__ . '/uploads/documents/' . $doc['filename'];
                        $fileSize = file_exists($filePath) ? formatSize(filesize($filePath)) : 'N/A';
                        $iconClass = getFileIcon($doc['filename']);
                    ?>
                        <tr id="doc-row-<?= $doc['id'] ?>">
                            <td>
                                <span class="doc-badge-number"><?= str_pad((string)$i++, 2, '0', STR_PAD_LEFT) ?></span>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <i class="bi <?= $iconClass ?> fs-4 me-3"></i>
                                    <div>
                                        <strong class="text-slate-800 d-block title-cell"><?= e($doc['title']) ?></strong>
                                        <div class="d-flex align-items-center gap-2 mt-1">
                                            <small class="text-muted text-uppercase"><?= pathinfo($doc['filename'], PATHINFO_EXTENSION) ?></small>
                                            <?php if (!file_exists($filePath)): ?>
                                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle" style="font-size: 0.7rem;">
                                                    <i class="bi bi-exclamation-triangle-fill"></i> ไม่พบไฟล์ในเซิร์ฟเวอร์
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border fw-medium"><?= $fileSize ?></span>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 24px; height: 24px; font-size: 0.7rem;">
                                        <i class="bi bi-person-fill"></i>
                                    </div>
                                    <span class="text-slate-700"><?= e($doc['uploader_name'] ?? 'ไม่ระบุ') ?></span>
                                </div>
                            </td>
                            <td>
                                <span class="doc-date-text">
                                    <i class="bi bi-calendar3 me-1"></i> <?= date('d/m/Y H:i', strtotime($doc['created_at'])) ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <div class="d-inline-flex gap-1">
                                    <a href="uploads/documents/<?= e($doc['filename']) ?>" 
                                       class="btn-download-doc" target="_blank" download>
                                       <i class="bi bi-download"></i>
                                    </a>

                                    <?php if ($isAdmin): ?>
                                        <button type="button" class="btn-edit-inline btn-edit-doc-modal" data-id="<?= e($doc['id']) ?>">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <a href="documents/documents_delete.php?id=<?= e($doc['id']) ?>" 
                                           class="btn-delete-inline btn-delete-doc" 
                                           data-title="<?= e($doc['title']) ?>">
                                            <i class="bi bi-trash-fill"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
<?php endif; // end else (no db_error) ?>
    </div>
</div>

<!-- Modal สำหรับ เพิ่ม/แก้ไข เอกสาร -->
<div class="modal fade" id="docModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 24px; overflow: hidden;">
            <div class="modal-header border-0 bg-dark text-white py-3">
                <h5 class="modal-title fw-bold" id="docModalTitle">เพิ่มเอกสารใหม่</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="docForm" enctype="multipart/form-data">
                <input type="hidden" name="id" id="docId">
                <div class="modal-body p-4">
                    <div class="mb-4">
                        <label class="form-label fw-bold text-dark">ชื่อเอกสาร</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="bi bi-type text-primary"></i></span>
                            <input type="text" name="title" id="docTitle" class="form-control border-start-0" placeholder="ระบุชื่อเอกสาร..." required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark">ไฟล์เอกสาร</label>
                        <div id="currentFileInfo" class="alert alert-info py-2 px-3 mb-3 d-none" style="border-radius: 12px; font-size: 0.9rem;">
                            <i class="bi bi-file-earmark-check-fill me-1"></i> ไฟล์เดิม: <span id="currentFileName" class="fw-bold"></span>
                        </div>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="bi bi-cloud-arrow-up text-primary"></i></span>
                            <input type="file" name="doc_file" id="docFile" class="form-control border-start-0">
                        </div>
                        <small class="text-muted d-block mt-2" id="fileHelpText">
                            * รองรับไฟล์ PDF, DOC, DOCX, XLS, XLSX (สูงสุด 20MB)
                        </small>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light fw-bold px-4 py-2" data-bs-dismiss="modal" style="border-radius: 12px;">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary fw-bold px-4 py-2" id="btnSaveDoc" style="border-radius: 12px; background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); border: none;">
                        <i class="bi bi-save-fill me-1"></i> บันทึกข้อมูล
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var tbl = document.getElementById('documentsListTable');
    var dataTable = null;
    
    if (tbl && tbl.querySelector('tbody tr') && !tbl.querySelector('tbody tr td[colspan]')) {
        var dtLang = { 
            search: 'ค้นหา:', 
            lengthMenu: 'แสดง _MENU_ รายการ', 
            info: 'แสดง _START_ ถึง _END_ จาก _TOTAL_ รายการ', 
            infoEmpty: 'ไม่มีข้อมูล', 
            infoFiltered: '(กรองจาก _MAX_ รายการ)', 
            paginate: { first: 'แรก', last: 'ท้าย', next: 'ถัดไป', previous: 'ก่อนหน้า' }, 
            zeroRecords: 'ไม่พบข้อมูลที่ตรงกับคำค้นหา' 
        };
        dataTable = $(tbl).DataTable({ 
            order: [[2, 'desc']], 
            language: dtLang, 
            pageLength: 25,
            dom: "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
                 "<'row'<'col-sm-12'tr>>" +
                 "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>"
        });
    }

    const docModal = new bootstrap.Modal(document.getElementById('docModal'));
    const docForm = document.getElementById('docForm');

    // คลิกเพิ่มเอกสารใหม่
    document.querySelectorAll('.btn-add-doc-modal').forEach(btn => {
        btn.addEventListener('click', () => {
            docForm.reset();
            document.getElementById('docId').value = '';
            document.getElementById('docModalTitle').textContent = 'เพิ่มเอกสารใหม่';
            document.getElementById('currentFileInfo').classList.add('d-none');
            document.getElementById('fileHelpText').innerHTML = '* รองรับไฟล์ PDF, DOC, DOCX, XLS, XLSX (สูงสุด 20MB)';
            document.getElementById('docFile').required = true;
            docModal.show();
        });
    });

    // คลิกแก้ไขเอกสาร
    $(document).on('click', '.btn-edit-doc-modal', function() {
        const id = $(this).data('id');
        
        Swal.fire({
            title: 'กำลังดึงข้อมูล...',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });

        $.get('documents/get_document.php', { id: id }, function(res) {
            Swal.close();
            if (res.success) {
                document.getElementById('docId').value = res.data.id;
                document.getElementById('docTitle').value = res.data.title;
                document.getElementById('docModalTitle').textContent = 'แก้ไขเอกสาร';
                
                document.getElementById('currentFileInfo').classList.remove('d-none');
                document.getElementById('currentFileName').textContent = res.data.filename;
                document.getElementById('fileHelpText').innerHTML = '* ปล่อยว่างไว้ถ้าไม่ต้องการเปลี่ยนไฟล์';
                document.getElementById('docFile').required = false;
                
                docModal.show();
            } else {
                Swal.fire('ข้อผิดพลาด', res.message, 'error');
            }
        });
    });

    // บันทึกฟอร์ม (Add/Edit)
    docForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        
        Swal.fire({
            title: 'กำลังบันทึกข้อมูล...',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });

        $.ajax({
            url: 'documents/save_document.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(res) {
                if (res.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'สำเร็จ',
                        text: res.message,
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        location.reload(); // โหลดหน้าใหม่เพื่ออัปเดตตาราง
                    });
                    docModal.hide();
                } else {
                    Swal.fire('ข้อผิดพลาด', res.message, 'error');
                }
            },
            error: function() {
                Swal.fire('ข้อผิดพลาด', 'ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้', 'error');
            }
        });
    });

    $(document).on('click', '.btn-delete-doc', function(e) {
        e.preventDefault();
        var href = $(this).attr('href');
        var title = $(this).attr('data-title') || 'เอกสารนี้';
        
        Swal.fire({ 
            title: 'ยืนยันการลบเอกสาร', 
            text: 'คุณแน่ใจหรือไม่ว่าต้องการลบเอกสาร "' + title + '" นี้ออกจากคลังระบบ?', 
            icon: 'warning', 
            showCancelButton: true, 
            confirmButtonColor: '#ef4444', 
            cancelButtonColor: '#64748b', 
            confirmButtonText: '<i class="bi bi-trash"></i> ใช่, ต้องการลบ', 
            cancelButtonText: 'ยกเลิก' 
        }).then(function(r) { 
            if (r.isConfirmed) {
                window.location.href = href; 
            }
        });
    });
});
</script>

<?php
include __DIR__ . '/includes/footer.php';
include __DIR__ . '/includes/footer_close.php';
?>