<?php
// calendar/list.php - หน้าจัดการรายการกิจกรรมในปฏิทิน
date_default_timezone_set('Asia/Bangkok');
// ต้องเรียกไฟล์ functions.php และ configdb.php เพื่อให้ใช้งานการเชื่อมต่อฐานข้อมูลได้
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php'; 
// กำหนดสิทธิ์การเข้าถึง (สมมติว่าเป็น staff)
require_role(['staff']); 
include __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<?php
// **ตรวจสอบว่า Path นี้ถูกต้องตามโครงสร้าง URL ของคุณ**
$web_upload_path = '../uploads/event_images/';
?>

<link rel="stylesheet" href="../includes/staff_style.css">

<div class="staff-dashboard-page pt-4 pb-5">
    <div class="container">
        <!-- Page Header Container -->
        <div class="staff-page-header d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
            <div>
                <h4 class="mb-1 fw-bold text-dark"><i class="bi bi-calendar-event text-primary me-2"></i>จัดการกิจกรรมปฏิทิน</h4>
                <p class="text-muted mb-0 small">เพิ่ม ลบ แก้ไข ข้อมูลกิจกรรมในปฏิทินของระบบ</p>
            </div>
            <div class="d-flex gap-2">
                <a href="../roles/staff.php" class="btn btn-outline-secondary border-opacity-25 bg-white rounded-pill shadow-sm px-4">
                    <i class="bi bi-house-door me-1"></i> กลับหน้าหลัก
                </a>
                <button type="button" class="btn btn-staff rounded-pill shadow-sm px-4" onclick="openAddModal()">
                    <i class="bi bi-plus-circle me-1"></i> เพิ่มกิจกรรม
                </button>
            </div>
        </div>

        <div class="glass-card border-0 shadow-sm p-4 p-md-5" style="border-radius: 20px;">
            <div class="table-responsive">
                <table id="calendarListTable" class="table table-hover align-middle w-100">
                    <thead class="table-light text-secondary">
                        <tr>
                            <th class="fw-semibold">ID</th>
                            <th class="fw-semibold">ชื่อกิจกรรม</th>
                            <th class="fw-semibold text-center">รูปภาพ</th> 
                            <th class="fw-semibold">รายละเอียด</th>
                            <th class="fw-semibold">วันที่จัดกิจกรรม</th>
                            <th class="fw-semibold text-center">จัดการ</th>
                        </tr>
                    </thead>
            <tbody>
                <?php
                // ดึงข้อมูลจากตาราง calendar_events (แก้ไข Syntax Error และลบ U+00A0)
                $sql = "SELECT id, title, description, event_date, image_filename FROM calendar_events ORDER BY event_date DESC";
                $result = $conn->query($sql);
                
                if ($result === false):
                    // หาก SQL Error (Colspan 6)
                    echo "<tr><td colspan='6' class='text-center text-danger'>SQL Error: " . $conn->error . "</td></tr>";
                elseif ($result->num_rows > 0): 
                    while ($event = $result->fetch_assoc()):
                        
                        // สร้าง HTML สำหรับรูปภาพ
                        $image_html = '';
                        if (!empty($event['image_filename'])) {
                            $image_url = $web_upload_path . e($event['image_filename']);
                            // Make image clickable for preview
                            $image_html = "<img src='{$image_url}' alt='รูปภาพกิจกรรม' class='img-preview-trigger' style='width: 50px; height: 50px; object-fit: cover; border-radius: 8px; cursor: pointer;' data-bs-toggle='tooltip' title='คลิกเพื่อดูรูปใหญ่'>";
                        } else {
                            $image_html = '<span class="text-muted"><i class="bi bi-image-fill"></i> N/A</span>';
                        }

                ?>
                <tr>
                    <td class="text-secondary">#<?= e($event['id']) ?></td>
                    <td class="fw-bold text-dark"><?= e($event['title']) ?></td> 
                    
                    <td class="text-center"><?= $image_html ?></td> 
                    <td class="text-muted small"><?= e(mb_substr($event['description'], 0, 50, 'UTF-8')) ?><?php if (mb_strlen($event['description'], 'UTF-8') > 50) echo '...'; ?></td> 
                    
                    <td><span class="badge bg-info bg-opacity-10 text-info px-3 py-2 rounded-pill"><i class="bi bi-calendar-check me-1"></i><?= date('d/m/Y', strtotime($event['event_date'])) ?></span></td>
                    
                    <td class="text-center">
                        <div class="btn-group shadow-sm">
                            <button type="button" class="btn btn-sm btn-light border btn-edit-event" data-id="<?= $event['id'] ?>" data-bs-toggle="tooltip" title="แก้ไข"><i class="bi bi-pencil text-warning"></i></button>
                            <a href="delete_event.php?id=<?= $event['id'] ?>" class="btn btn-sm btn-light border btn-delete-event" data-bs-toggle="tooltip" title="ลบ"><i class="bi bi-trash text-danger"></i></a>
                        </div>
                    </td>
                </tr>
                <?php 
                    endwhile;
                else: 
                ?>
                <tr>
                    <td colspan="6" class="text-center">ยังไม่มีกิจกรรมในปฏิทิน</td> </tr>
                <?php 
                endif; 
                ?>
            </tbody>
        </table>
            </div>
    </div>
    </div>
</div>

<!-- Event Modal -->
<div class="modal fade" id="eventModal" tabindex="-1" aria-labelledby="eventModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="eventModalLabel">เพิ่มกิจกรรมใหม่</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="eventForm" enctype="multipart/form-data">
                <input type="hidden" name="id" id="event_id">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label for="title" class="form-label fw-semibold">ชื่อกิจกรรม <span class="text-danger">*</span></label>
                            <input type="text" class="form-control bg-light border-0 shadow-sm rounded-3" id="title" name="title" required>
                        </div>
                        <div class="col-md-4">
                            <label for="event_date" class="form-label fw-semibold">วันที่จัดกิจกรรม <span class="text-danger">*</span></label>
                            <input type="date" class="form-control bg-light border-0 shadow-sm rounded-3" id="event_date" name="event_date" required>
                        </div>
                        <div class="col-12">
                            <label for="description" class="form-label fw-semibold">รายละเอียด</label>
                            <textarea class="form-control bg-light border-0 shadow-sm rounded-3" id="description" name="description" rows="4"></textarea>
                        </div>
                        <div class="col-12">
                            <label for="event_image" class="form-label fw-semibold">รูปภาพประชาสัมพันธ์</label>
                            <div class="d-flex align-items-center gap-3">
                                <div id="current_image_preview" class="border rounded bg-light d-flex align-items-center justify-content-center" style="width: 100px; height: 100px; overflow: hidden;">
                                    <i class="bi bi-image text-muted fs-2"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <input type="file" class="form-control bg-light border-0 shadow-sm rounded-3" id="event_image" name="event_image" accept="image/*">
                                    <small class="text-muted">ไฟล์ JPG, PNG, GIF (ไม่เกิน 5MB)</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0 p-4">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-staff rounded-pill px-4" id="btnSubmit">
                        <i class="bi bi-check-circle-fill me-1"></i> บันทึกข้อมูล
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Image Viewer Modal -->
<div class="modal fade" id="imageViewerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 bg-transparent">
            <div class="modal-body p-0 text-center position-relative">
                <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3 shadow-none" data-bs-dismiss="modal" aria-label="Close" style="z-index: 1060; filter: drop-shadow(0 0 2px rgba(0,0,0,0.5));"></button>
                <img id="fullSizeImage" src="" class="img-fluid rounded-4 shadow-lg" style="max-height: 85vh;">
            </div>
        </div>
    </div>
</div>

<script>
let eventModal, imageViewerModal;

function openAddModal() {
    document.getElementById('eventForm').reset();
    document.getElementById('event_id').value = '';
    document.getElementById('eventModalLabel').innerText = 'เพิ่มกิจกรรมใหม่';
    document.getElementById('current_image_preview').innerHTML = '<i class="bi bi-image text-muted fs-2"></i>';
    eventModal.show();
}

document.addEventListener('DOMContentLoaded', function() {
    eventModal = new bootstrap.Modal(document.getElementById('eventModal'));
    imageViewerModal = new bootstrap.Modal(document.getElementById('imageViewerModal'));

    // Image Preview Click in Table
    $('#calendarListTable').on('click', '.img-preview-trigger', function() {
        const src = $(this).attr('src');
        document.getElementById('fullSizeImage').src = src;
        imageViewerModal.show();
    });

    // Initialize DataTable
    const table = $('#calendarListTable').DataTable({
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.13.7/i18n/th.json"
        },
        "pageLength": 10,
        "ordering": true,
        "responsive": true
    });

    // Initialize tooltips
    const initTooltips = () => {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });
    };
    initTooltips();
    table.on('draw', initTooltips);

    // Edit Event Click
    $('#calendarListTable').on('click', '.btn-edit-event', function() {
        const id = $(this).data('id');
        
        // Show loading state or similar if needed
        $.get('get_event.php', { id: id }, function(res) {
            if (res.success) {
                const data = res.data;
                document.getElementById('event_id').value = data.id;
                document.getElementById('title').value = data.title;
                document.getElementById('event_date').value = data.event_date_formatted;
                document.getElementById('description').value = data.description;
                document.getElementById('eventModalLabel').innerText = 'แก้ไขกิจกรรม';
                
                if (data.image_filename) {
                    const imgUrl = '<?= $web_upload_path ?>' + data.image_filename;
                    document.getElementById('current_image_preview').innerHTML = `<img src="${imgUrl}" style="width:100%; height:100%; object-fit:cover;">`;
                } else {
                    document.getElementById('current_image_preview').innerHTML = '<i class="bi bi-image text-muted fs-2"></i>';
                }
                
                eventModal.show();
            } else {
                Swal.fire('ข้อผิดพลาด', res.message, 'error');
            }
        });
    });

    // Handle Form Submit
    document.getElementById('eventForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const btn = document.getElementById('btnSubmit');
        
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> กำลังบันทึก...';

        $.ajax({
            url: 'save_event.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(res) {
                if (res.success) {
                    Swal.fire({
                        title: 'สำเร็จ!',
                        text: res.message,
                        icon: 'success'
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire('ข้อผิดพลาด', res.message, 'error');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> บันทึกข้อมูล';
                }
            },
            error: function() {
                Swal.fire('ข้อผิดพลาด', 'ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้', 'error');
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> บันทึกข้อมูล';
            }
        });
    });

    // Image Preview for File Input
    document.getElementById('event_image').addEventListener('change', function() {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('current_image_preview').innerHTML = `<img src="${e.target.result}" style="width:100%; height:100%; object-fit:cover;">`;
            }
            reader.readAsDataURL(file);
        }
    });

    // Delete Event
    $('#calendarListTable').on('click', '.btn-delete-event', function(e) {
        e.preventDefault();
        const url = this.getAttribute('href');
        Swal.fire({
            title: 'ยืนยันการลบ',
            text: 'ยืนยันการลบกิจกรรมนี้?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'ลบ',
            cancelButtonText: 'ยกเลิก'
        }).then(function(r) {
            if (r.isConfirmed) window.location.href = url;
        });
    });
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>