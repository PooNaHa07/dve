<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php';
require_login();
require_role(['admin', 'staff']);

include __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/admin-premium.css">

<style>
:root { --primary: #6366f1; --success: #10b981; --warning: #f59e0b; --danger: #ef4444; }
body { background: #f1f5f9; }

.import-hero {
    background: linear-gradient(135deg, #6366f1, #a855f7);
    border-radius: 1.5rem;
    padding: 2rem;
    color: white;
    margin-bottom: 2rem;
    box-shadow: 0 10px 30px rgba(99,102,241,.3);
}

.upload-zone {
    border: 2.5px dashed #a5b4fc;
    border-radius: 1.25rem;
    background: #faf5ff;
    padding: 3rem 2rem;
    text-align: center;
    transition: all .3s;
    cursor: pointer;
}
.upload-zone:hover, .upload-zone.drag-over {
    border-color: #6366f1;
    background: #ede9fe;
    transform: translateY(-3px);
}
.upload-zone .icon { font-size: 3.5rem; color: #a855f7; margin-bottom: 1rem; }

.config-card {
    background: white;
    border-radius: 1.25rem;
    padding: 1.5rem;
    box-shadow: 0 4px 15px rgba(0,0,0,.05);
    margin-bottom: 1.5rem;
}

.preview-table-wrap {
    background: white;
    border-radius: 1.25rem;
    box-shadow: 0 4px 15px rgba(0,0,0,.06);
    overflow: hidden;
    margin-top: 1.5rem;
}
.preview-table-wrap thead th {
    background: #1e1b4b;
    color: white;
    font-size:.78rem;
    text-transform: uppercase;
    letter-spacing:.05em;
    padding:.85rem 1rem;
    border:none;
}
.preview-table-wrap tbody td { padding:.75rem 1rem; vertical-align:middle; font-size:.88rem; }
.preview-table-wrap tbody tr:hover { background:#f8fafc; }

.badge-new    { background:#d1fae5; color:#065f46; }
.badge-update { background:#fef3c7; color:#92400e; }
.badge-skip   { background:#fee2e2; color:#991b1b; }

.stat-card {
    background: white;
    border-radius: 1rem;
    padding: 1.25rem 1.5rem;
    text-align: center;
    box-shadow: 0 4px 15px rgba(0,0,0,.05);
}
.stat-card .num { font-size:2.2rem; font-weight:800; }
.stat-card.new    { border-top: 4px solid #10b981; }
.stat-card.update { border-top: 4px solid #f59e0b; }
.stat-card.skip   { border-top: 4px solid #ef4444; }

.btn-import-main {
    background: linear-gradient(135deg,#6366f1,#a855f7);
    color:white; border:none;
    padding:.9rem 2.5rem;
    border-radius:1rem;
    font-weight:700; font-size:1rem;
    box-shadow:0 6px 20px rgba(99,102,241,.35);
    transition:all .3s;
}
.btn-import-main:hover { transform:translateY(-2px); box-shadow:0 10px 25px rgba(99,102,241,.45); color:white; }

.progress-bar { transition: width .5s ease; }

#resultBox { display:none; }
#previewSection { display:none; }
</style>

<div class="container admin-content-wrapper py-5">

    <!-- Hero Header -->
    <div class="import-hero d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div>
            <h2 class="mb-1 fw-bold"><i class="fas fa-file-import me-2"></i>นำเข้าข้อมูลนักศึกษา</h2>
            <p class="mb-0 opacity-75">นำเข้าจากไฟล์ CSV รหัสนักศึกษา, ชื่อ-นามสกุล, สาขาวิชา, ระดับ, สถานประกอบการ</p>
        </div>
        <a href="manage_users.php?role=student" class="btn btn-outline-light px-4 rounded-pill">
            <i class="fas fa-arrow-left me-2"></i>กลับหน้านักศึกษา
        </a>
    </div>

    <div class="row g-4">
        <!-- Left: Upload + Config -->
        <div class="col-md-4">

            <div class="config-card">
                <h6 class="fw-bold mb-3"><i class="fas fa-upload me-2 text-primary"></i>อัปโหลดไฟล์ CSV</h6>
                <div class="upload-zone" id="uploadZone" onclick="document.getElementById('csvFile').click()">
                    <div class="icon"><i class="fas fa-file-csv"></i></div>
                    <div class="fw-bold text-dark mb-1">คลิกหรือลากไฟล์มาวางที่นี่</div>
                    <div class="text-muted small">รองรับ .csv (UTF-8 / TIS-620)</div>
                    <div id="fileName" class="mt-2 fw-bold text-primary" style="display:none;"></div>
                </div>
                <input type="file" id="csvFile" accept=".csv" class="d-none">

                <div class="mt-3">
                    <small class="text-muted d-block mb-1"><strong>รูปแบบคอลัมน์ที่ต้องการ:</strong></small>
                    <div class="bg-light rounded p-2" style="font-size:.76rem; font-family:monospace;">
                        รหัสนักศึกษา, ชื่อ-นามสกุล, สาขาวิชา, ระดับ, ชื่อสถานประกอบการ, ที่อยู่
                    </div>
                </div>

                <button class="btn btn-outline-primary w-100 mt-3 rounded-pill" id="btnPreview" disabled>
                    <i class="fas fa-search me-2"></i>ตรวจสอบข้อมูล (Preview)
                </button>
            </div>

            <div class="config-card">
                <h6 class="fw-bold mb-3"><i class="fas fa-cog me-2 text-secondary"></i>ตั้งค่าการนำเข้า</h6>

                <label class="form-label small fw-bold">รหัสผ่านเริ่มต้น (สำหรับนักศึกษาใหม่)</label>
                <div class="input-group mb-3">
                    <span class="input-group-text"><i class="fas fa-key text-muted"></i></span>
                    <input type="text" id="defaultPassword" class="form-control" value="student1234">
                </div>

                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" id="updateExisting" checked>
                    <label class="form-check-label" for="updateExisting">
                        อัปเดตข้อมูลนักศึกษาที่มีอยู่แล้ว
                    </label>
                </div>

                <hr>
                <div class="d-grid">
                    <button class="btn-import-main" id="btnImport" disabled>
                        <i class="fas fa-database me-2"></i>นำเข้าข้อมูลทั้งหมด
                    </button>
                </div>
            </div>

            <!-- Result Box -->
            <div id="resultBox" class="config-card">
                <h6 class="fw-bold mb-3"><i class="fas fa-chart-bar me-2 text-success"></i>ผลการนำเข้า</h6>
                <div class="row g-2">
                    <div class="col-4">
                        <div class="stat-card new">
                            <div class="num text-success" id="resInserted">0</div>
                            <div class="small text-muted">เพิ่มใหม่</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="stat-card update">
                            <div class="num text-warning" id="resUpdated">0</div>
                            <div class="small text-muted">อัปเดต</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="stat-card skip">
                            <div class="num text-danger" id="resSkipped">0</div>
                            <div class="small text-muted">ข้าม</div>
                        </div>
                    </div>
                </div>
                <div id="resErrors" class="mt-3" style="display:none;">
                    <div class="alert alert-danger small mb-0"><ul id="errList" class="mb-0 ps-3"></ul></div>
                </div>
                <a href="manage_users.php?role=student" class="btn btn-success w-100 mt-3 rounded-pill">
                    <i class="fas fa-users me-2"></i>ดูรายชื่อนักศึกษาทั้งหมด
                </a>
            </div>
        </div>

        <!-- Right: Preview Table -->
        <div class="col-md-8">
            <div id="previewSection">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="fw-bold mb-0"><i class="fas fa-table me-2 text-primary"></i>ตัวอย่างข้อมูล <span id="previewCount" class="badge bg-primary rounded-pill ms-1"></span></h6>
                    <div class="d-flex gap-2 small">
                        <span class="badge badge-new px-3 py-2 rounded-pill">ใหม่</span>
                        <span class="badge badge-update px-3 py-2 rounded-pill">อัปเดต</span>
                        <span class="badge badge-skip px-3 py-2 rounded-pill">ข้าม/ซ้ำ</span>
                    </div>
                </div>

                <!-- Progress -->
                <div id="progressWrap" class="mb-2" style="display:none;">
                    <div class="progress" style="height:8px; border-radius:1rem;">
                        <div class="progress-bar bg-primary" id="progressBar" style="width:0%"></div>
                    </div>
                    <small class="text-muted" id="progressText">กำลังโหลด...</small>
                </div>

                <div class="preview-table-wrap">
                    <div class="table-responsive" style="max-height:520px; overflow-y:auto;">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>รหัสนักศึกษา</th>
                                    <th>ชื่อ-นามสกุล</th>
                                    <th>สาขาวิชา</th>
                                    <th>ระดับ</th>
                                    <th>ห้องเรียนที่จับคู่</th>
                                    <th>สถานประกอบการ</th>
                                    <th>สถานะ</th>
                                </tr>
                            </thead>
                            <tbody id="previewTbody">
                                <tr><td colspan="7" class="text-center text-muted py-5">
                                    <i class="fas fa-arrow-left fa-2x d-block mb-2 opacity-25"></i>
                                    อัปโหลดไฟล์ CSV แล้วกด "ตรวจสอบข้อมูล"
                                </td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Placeholder when no file -->
            <div id="placeholderBox" class="text-center py-5 text-muted">
                <i class="fas fa-file-csv fa-5x mb-3 opacity-25"></i>
                <h5>ยังไม่ได้เลือกไฟล์</h5>
                <p>เลือกไฟล์ CSV ทางด้านซ้าย แล้วกด <strong>"ตรวจสอบข้อมูล"</strong></p>
                <hr class="my-4">
                <h6 class="text-start fw-bold">รูปแบบไฟล์ CSV ที่รองรับ</h6>
                <table class="table table-sm table-bordered text-start" style="font-size:.82rem;">
                    <thead class="table-dark">
                        <tr>
                            <th>คอลัมน์ที่ 1</th><th>คอลัมน์ที่ 2</th><th>คอลัมน์ที่ 3</th>
                            <th>คอลัมน์ที่ 4</th><th>คอลัมน์ที่ 5</th><th>คอลัมน์ที่ 6</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>รหัสนักศึกษา</td><td>ชื่อ - นามสกุล</td><td>สาขาวิชา</td>
                            <td>ระดับการศึกษา</td><td>ชื่อสถานประกอบการ</td><td>ที่อยู่สถานประกอบการ</td>
                        </tr>
                        <tr class="table-secondary">
                            <td>67202010001</td><td>นางสาวกชกร ชูเนียม</td><td>การบัญชี ห้อง 1</td>
                            <td>ปวช.3</td><td>สหกรณ์เครดิตยูเนี่ยน...</td><td>149 หมู่ 5 ต.หนองจอก...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
let previewRows = [];

// ── Drag & Drop ───────────────────────────────────────────────────────────────
const zone = document.getElementById('uploadZone');
zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('drag-over'); });
zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));
zone.addEventListener('drop', e => {
    e.preventDefault();
    zone.classList.remove('drag-over');
    const f = e.dataTransfer.files[0];
    if (f && f.name.endsWith('.csv')) handleFileSelected(f);
});

document.getElementById('csvFile').addEventListener('change', function() {
    if (this.files[0]) handleFileSelected(this.files[0]);
});

function handleFileSelected(file) {
    document.getElementById('fileName').textContent = '📄 ' + file.name;
    document.getElementById('fileName').style.display = 'block';
    document.getElementById('btnPreview').disabled = false;
    document.getElementById('csvFile')._file = file;
}

// ── Preview ───────────────────────────────────────────────────────────────────
document.getElementById('btnPreview').addEventListener('click', function() {
    const file = document.getElementById('csvFile').files[0] || document.getElementById('csvFile')._file;
    if (!file) return;

    const fd = new FormData();
    fd.append('action', 'preview');
    fd.append('csv_file', file);

    document.getElementById('progressWrap').style.display = 'block';
    document.getElementById('progressBar').style.width = '30%';
    document.getElementById('progressText').textContent = 'กำลังวิเคราะห์ข้อมูล...';
    document.getElementById('placeholderBox').style.display = 'none';
    document.getElementById('previewSection').style.display = 'block';

    fetch('ajax_import_students.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            document.getElementById('progressBar').style.width = '100%';
            if (!res.success) { alert('เกิดข้อผิดพลาด: ' + res.message); return; }

            previewRows = res.rows;
            renderPreview(previewRows);

            document.getElementById('previewCount').textContent = res.total + ' รายการ';
            document.getElementById('btnImport').disabled = false;
            setTimeout(() => document.getElementById('progressWrap').style.display = 'none', 800);
        })
        .catch(err => { alert('เกิดข้อผิดพลาด: ' + err); });
});

function renderPreview(rows) {
    const tbody = document.getElementById('previewTbody');
    let html = '';
    rows.forEach((r, i) => {
        const badge = r.status === 'new'
            ? '<span class="badge badge-new rounded-pill px-3">เพิ่มใหม่</span>'
            : (r.matched_by_name 
                ? '<span class="badge badge-update rounded-pill px-3" style="background:#fffbeb; color:#d97706; border: 1px solid #fde68a;" title="จับคู่ด้วยการวิเคราะห์ชื่อ"><i class="fas fa-user-tag me-1"></i>อัปเดต (จับคู่ชื่อ)</span>'
                : '<span class="badge badge-update rounded-pill px-3">อัปเดต</span>');
        const code = r.student_code || '-';
        const name = r.fullname || '-';
        const aff  = r.affiliation || '-';
        const lvl  = r.student_level_norm || r.student_level || '-';
        const co   = r.company_name || '-';
        const clsBadge = r.matched_class_name
            ? `<span class="badge bg-success-subtle text-success border border-success-subtle small py-1 px-2" title="ID: ${r.classroom_id}">${r.matched_class_name}</span>`
            : `<span class="badge bg-danger-subtle text-danger border border-danger-subtle small py-1 px-2"><i class="fas fa-exclamation-triangle me-1"></i>ไม่พบห้องเรียน</span>`;
        html += `<tr class="${!r.matched_class_name ? 'table-warning' : ''}">
            <td class="text-muted">${i+1}</td>
            <td><code>${code}</code></td>
            <td class="fw-semibold">${name}</td>
            <td class="small">${aff}</td>
            <td><span class="badge bg-secondary rounded-pill">${lvl}</span></td>
            <td>${clsBadge}</td>
            <td class="small text-truncate" style="max-width:180px;" title="${co}">${co}</td>
            <td>${badge}</td>
        </tr>`;
    });
    tbody.innerHTML = html || '<tr><td colspan="8" class="text-center text-muted">ไม่มีข้อมูลที่ถูกต้อง</td></tr>';
}

// ── Import ────────────────────────────────────────────────────────────────────
document.getElementById('btnImport').addEventListener('click', function() {
    if (!previewRows.length) return;

    const newCount  = previewRows.filter(r => r.status === 'new').length;
    const updCount  = previewRows.filter(r => r.status === 'update').length;
    const updateExisting = document.getElementById('updateExisting').checked;

    Swal.fire({
        title: 'ยืนยันการนำเข้าข้อมูล',
        html: `<div class="text-start">
            <p>กำลังจะนำเข้า <strong>${previewRows.length}</strong> รายการ</p>
            <ul class="small">
                <li>เพิ่มใหม่: <strong class="text-success">${newCount}</strong> คน</li>
                <li>อัปเดต: <strong class="text-warning">${updCount}</strong> คน ${updateExisting ? '' : '(ถูกข้ามเนื่องจากปิดตัวเลือก)'}</li>
                ${(() => { const nc = previewRows.filter(r => !r.matched_class_name).length; return nc > 0 ? `<li class="text-danger"><i class="fas fa-exclamation-triangle me-1"></i>ไม่พบห้องเรียนในระบบ: <strong>${nc}</strong> คน (นักเรียนยังถูกนำเข้า แต่ไม่ถูก assign ห้อง)</li>` : ''; })()}
            </ul>
            <p class="text-muted small mb-0">รหัสผ่านเริ่มต้น: <code>${document.getElementById('defaultPassword').value}</code></p>
        </div>`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: '<i class="fas fa-check me-2"></i>ยืนยันนำเข้า',
        cancelButtonText: 'ยกเลิก',
        confirmButtonColor: '#6366f1',
    }).then(result => {
        if (!result.isConfirmed) return;

        document.getElementById('btnImport').disabled = true;
        document.getElementById('progressWrap').style.display = 'block';
        document.getElementById('progressBar').style.width = '50%';
        document.getElementById('progressText').textContent = 'กำลังนำเข้าข้อมูล...';

        const fd = new FormData();
        fd.append('action', 'import');
        fd.append('rows', JSON.stringify(previewRows));
        fd.append('default_password', document.getElementById('defaultPassword').value);
        fd.append('update_existing', updateExisting ? '1' : '0');

        fetch('ajax_import_students.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                document.getElementById('progressBar').style.width = '100%';
                setTimeout(() => document.getElementById('progressWrap').style.display = 'none', 600);

                if (!res.success) {
                    Swal.fire('เกิดข้อผิดพลาด', res.message, 'error');
                    return;
                }

                // Show result
                document.getElementById('resInserted').textContent = res.inserted;
                document.getElementById('resUpdated').textContent  = res.updated;
                document.getElementById('resSkipped').textContent  = res.skipped;

                if (res.errors && res.errors.length > 0) {
                    document.getElementById('resErrors').style.display = 'block';
                    const ul = document.getElementById('errList');
                    ul.innerHTML = res.errors.map(e => `<li>${e}</li>`).join('');
                }

                document.getElementById('resultBox').style.display = 'block';
                document.getElementById('resultBox').scrollIntoView({ behavior: 'smooth' });

                Swal.fire({
                    icon: 'success',
                    title: 'นำเข้าข้อมูลสำเร็จ!',
                    html: `เพิ่มใหม่ <b>${res.inserted}</b> | อัปเดต <b>${res.updated}</b> | ข้าม <b>${res.skipped}</b>`,
                    confirmButtonColor: '#6366f1',
                    timer: 4000,
                });
            })
            .catch(err => Swal.fire('Error', err.toString(), 'error'));
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
