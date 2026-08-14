<?php
require_once __DIR__ . '/../includes/configdb.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();
require_role(['director', 'admin']);

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: view_students.php?error=1');
    exit;
}
$id = (int)$_GET['id'];
$class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$return_to = isset($_GET['return_to']) && $_GET['return_to'] === 'view_wait_sign' ? 'view_wait_sign' : '';
$back_url = $return_to ? 'view_wait_sign.php' : ('view_students.php?class_id=' . $class_id);

/* สร้างคอลัมน์ลายเซ็นเจ้าหน้าที่ถ้ายังไม่มี (ให้ director เห็นเอกสารที่เจ้าหน้าที่เซ็นแล้ว) */
$chk = $conn->query("SHOW COLUMNS FROM supervision_files LIKE 'staff_signed_file'");
if ($chk && $chk->num_rows === 0) {
    $conn->query("ALTER TABLE supervision_files
        ADD COLUMN staff_signed_file VARCHAR(255) DEFAULT NULL AFTER file_path,
        ADD COLUMN staff_signed_at DATETIME DEFAULT NULL AFTER staff_signed_file,
        ADD COLUMN staff_signed_by INT(11) DEFAULT NULL AFTER staff_signed_at");
}

$stmt = $conn->prepare("SELECT file_path, staff_signed_file FROM supervision_files WHERE id = ? AND status = 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
if (!$res || $res->num_rows === 0) {
    header('Location: view_students.php?class_id=' . $class_id . '&error=2');
    exit;
}
$row = $res->fetch_assoc();
$file_path = $row['file_path'];
$staff_signed = isset($row['staff_signed_file']) ? trim((string)$row['staff_signed_file']) : '';
$signed_path = __DIR__ . '/../uploads/supervision_docs/signed/';
if ($staff_signed !== '' && is_file($signed_path . $staff_signed)) {
    $file_url = '../uploads/supervision_docs/signed/' . htmlspecialchars($staff_signed);
    $ext = 'pdf';
} else {
    $file_url = $file_path ? ('../uploads/supervision_docs/original/' . htmlspecialchars($file_path)) : '';
    $ext = $file_path ? strtolower(pathinfo($file_path, PATHINFO_EXTENSION)) : '';
}
$has_file = ($file_url !== '');
$is_pdf = ($ext === 'pdf');

include __DIR__ . '/../includes/header.php';
?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<style>
.file-sign-wrap { position: relative; display: inline-block; width: 100%; max-width: 100%; margin: 0 auto; }
.file-sign-wrap > #dirDocCanvas { width: 100%; height: auto; max-width: 100%; border-radius: 0.5rem; display: block; vertical-align: top; }
.file-sign-wrap > #dirDrawCanvas { position: absolute; top: 0; left: 0; cursor: crosshair; touch-action: none; pointer-events: auto; }
.sign-pad { border: 2px solid #dee2e6; border-radius: 0.5rem; background: #fff; cursor: crosshair; touch-action: none; }
@media (max-width: 576px) {
    .file-sign-wrap { width: 100%; }
    .file-sign-wrap > #dirDocCanvas { max-width: 100%; }
}
</style>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold text-primary mb-0"><i class="bi bi-pencil-square me-2"></i>ลงนามออนไลน์ - ใบนิเทศ (ผู้บริหาร)</h4>
        <a href="<?= htmlspecialchars($back_url) ?>" class="btn btn-outline-secondary rounded-pill">← กลับ</a>
    </div>

    <?php if (!$has_file): ?>
    <div class="card border-0 shadow-sm mb-4" style="border-radius: 1rem;">
        <div class="card-body">
            <p class="text-muted mb-3">เอกสารนี้ครูส่งเป็นลายเซ็นอย่างเดียว ไม่มีไฟล์แนบ — ลงนามด้านล่างแล้วกดส่งกลับมาหาครูนิเทศก์</p>
            <form method="POST" action="sign_pdf.php" id="signForm">
                <input type="hidden" name="id" value="<?= $id ?>">
                <input type="hidden" name="class_id" value="<?= $class_id ?>">
                <?php if ($return_to): ?><input type="hidden" name="return_to" value="view_wait_sign"><?php endif; ?>
                <input type="hidden" name="signature_data" id="signatureData">
                <div class="border rounded p-2 bg-light mb-3">
                    <canvas id="signPad" class="sign-pad w-100" width="400" height="120"></canvas>
                    <div class="mt-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="clearSign()">ล้างลายเซ็น</button>
                    </div>
                </div>
                <button type="submit" class="btn btn-success rounded-pill px-4"><i class="bi bi-check-lg me-1"></i> ลงนามและส่งกลับมาหาครูนิเทศก์</button>
            </form>
        </div>
    </div>
    <?php else: ?>
    <div class="card border-0 shadow-sm mb-4" style="border-radius: 1rem;">
        <div class="card-header bg-light border-0 py-2 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <strong class="small"><i class="bi bi-pencil-square me-1"></i>เปิดเอกสารและลงนามบนเอกสาร</strong>
            <div class="d-flex align-items-center gap-2" id="dirPageNav" style="display: none;">
                <span class="small text-muted" id="dirPageInfo">—</span>
                <div class="btn-group btn-group-sm">
                    <button type="button" id="dirBtnPrev" class="btn btn-outline-secondary">←</button>
                    <button type="button" id="dirBtnNext" class="btn btn-outline-secondary">→</button>
                </div>
            </div>
        </div>
        <div class="card-body p-2 text-center">
            <div class="file-sign-wrap" id="dirPreviewWrap">
                <canvas id="dirDocCanvas"></canvas>
                <canvas id="dirDrawCanvas"></canvas>
            </div>
            <p class="small text-muted mt-2 mb-2">ลากเมาส์หรือนิ้ววาดลงนามตรงบนเอกสารด้านบน</p>
            <div class="d-flex gap-2 justify-content-center flex-wrap mb-2 align-items-center">
                <span class="small text-muted me-1">เครื่องมือ:</span>
                <button type="button" id="dirBtnPen" class="btn btn-secondary btn-sm rounded-pill" title="ปากกา">ปากกา</button>
                <button type="button" id="dirBtnEraser" class="btn btn-outline-secondary btn-sm rounded-pill" title="ยางลบ">ยางลบ</button>
                <button type="button" id="dirBtnClear" class="btn btn-outline-secondary btn-sm rounded-pill">ล้างลายเซ็นหน้าปัจจุบัน</button>
            </div>
            <form method="POST" action="sign_pdf.php" id="signForm">
                <input type="hidden" name="id" value="<?= $id ?>">
                <input type="hidden" name="class_id" value="<?= $class_id ?>">
                <?php if ($return_to): ?><input type="hidden" name="return_to" value="view_wait_sign"><?php endif; ?>
                <input type="hidden" name="sign_on_document" value="1">
                <button type="submit" id="dirBtnSubmit" class="btn btn-success rounded-pill px-4" disabled><i class="bi bi-check-lg me-1"></i> ลงนามและส่งกลับมาหาครูนิเทศก์</button>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php if (!$has_file): ?>
<script>
(function() {
    var canvas = document.getElementById('signPad');
    if (!canvas) return;
    var ctx = canvas.getContext('2d');
    var drawing = false, lastX = 0, lastY = 0;
    function getPos(e) {
        var r = canvas.getBoundingClientRect();
        var scaleX = canvas.width / r.width, scaleY = canvas.height / r.height;
        var clientX = (e.touches ? e.touches[0].clientX : e.clientX) - r.left;
        var clientY = (e.touches ? e.touches[0].clientY : e.clientY) - r.top;
        return { x: clientX * scaleX, y: clientY * scaleY };
    }
    function start(e) { e.preventDefault(); drawing = true; var p = getPos(e); lastX = p.x; lastY = p.y; }
    function move(e) {
        e.preventDefault();
        if (!drawing) return;
        var p = getPos(e);
        ctx.strokeStyle = '#000';
        ctx.lineWidth = 2;
        ctx.lineCap = 'round';
        ctx.beginPath();
        ctx.moveTo(lastX, lastY);
        ctx.lineTo(p.x, p.y);
        ctx.stroke();
        lastX = p.x; lastY = p.y;
    }
    function end(e) { e.preventDefault(); drawing = false; }
    canvas.addEventListener('mousedown', start);
    canvas.addEventListener('mousemove', move);
    canvas.addEventListener('mouseup', end);
    canvas.addEventListener('mouseleave', end);
    canvas.addEventListener('touchstart', start, { passive: false });
    canvas.addEventListener('touchmove', move, { passive: false });
    canvas.addEventListener('touchend', end, { passive: false });
    window.clearSign = function() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        document.getElementById('signatureData').value = '';
    };
    document.getElementById('signForm').addEventListener('submit', function() {
        document.getElementById('signatureData').value = canvas.toDataURL('image/png');
    });
})();
</script>
<?php else: ?>
<script>
(function() {
    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
    var fileUrl = <?= json_encode($file_url) ?>;
    var isPdf = <?= json_encode($is_pdf) ?>;
    var dirDocCanvas = document.getElementById('dirDocCanvas');
    var dirDrawCanvas = document.getElementById('dirDrawCanvas');
    var dirPageNav = document.getElementById('dirPageNav');
    var dirPageInfo = document.getElementById('dirPageInfo');
    var pdfDoc = null;
    var numPages = 1;
    var currentPage = 1;
    var pageDrawings = {};
    var drawInited = false;
    var eraserMode = false;

    function initDrawLayer() {
        if (!dirDrawCanvas || drawInited) return;
        drawInited = true;
        var ctx = dirDrawCanvas.getContext('2d');
        var drawing = false, lastX = 0, lastY = 0;
        function getPos(e) {
            var r = dirDrawCanvas.getBoundingClientRect();
            var scaleX = dirDrawCanvas.width / r.width, scaleY = dirDrawCanvas.height / r.height;
            var clientX = (e.touches ? e.touches[0].clientX : e.clientX) - r.left;
            var clientY = (e.touches ? e.touches[0].clientY : e.clientY) - r.top;
            return { x: clientX * scaleX, y: clientY * scaleY };
        }
        function start(e) { e.preventDefault(); drawing = true; var p = getPos(e); lastX = p.x; lastY = p.y; }
        function move(e) {
            e.preventDefault();
            if (!drawing) return;
            var p = getPos(e);
            ctx.lineCap = 'round';
            if (eraserMode) {
                ctx.globalCompositeOperation = 'destination-out';
                ctx.strokeStyle = 'rgba(0,0,0,1)';
                ctx.lineWidth = 14;
            } else {
                ctx.globalCompositeOperation = 'source-over';
                ctx.strokeStyle = '#000';
                ctx.lineWidth = 2.5;
            }
            ctx.beginPath();
            ctx.moveTo(lastX, lastY);
            ctx.lineTo(p.x, p.y);
            ctx.stroke();
            ctx.globalCompositeOperation = 'source-over';
            lastX = p.x; lastY = p.y;
        }
        function end(e) { e.preventDefault(); drawing = false; savePageDrawing(); updateSubmitBtn(); }
        dirDrawCanvas.addEventListener('mousedown', start);
        dirDrawCanvas.addEventListener('mousemove', move);
        dirDrawCanvas.addEventListener('mouseup', end);
        dirDrawCanvas.addEventListener('mouseleave', end);
        dirDrawCanvas.addEventListener('touchstart', start, { passive: false });
        dirDrawCanvas.addEventListener('touchmove', move, { passive: false });
        dirDrawCanvas.addEventListener('touchend', end, { passive: false });
    }

    function savePageDrawing() {
        if (!dirDrawCanvas || !dirDrawCanvas.width) return;
        var data = dirDrawCanvas.toDataURL('image/png');
        if (data.length > 100) pageDrawings[currentPage] = data;
    }

    function loadPageDrawing() {
        if (!dirDrawCanvas) return;
        var ctx = dirDrawCanvas.getContext('2d');
        ctx.clearRect(0, 0, dirDrawCanvas.width, dirDrawCanvas.height);
        if (pageDrawings[currentPage]) {
            var img = new Image();
            img.onload = function() { ctx.drawImage(img, 0, 0); };
            img.src = pageDrawings[currentPage];
        }
    }

    function updateSubmitBtn() {
        document.getElementById('dirBtnSubmit').disabled = Object.keys(pageDrawings).length === 0;
    }

    function syncDrawCanvasSize() {
        if (!dirDocCanvas || !dirDrawCanvas) return;
        var docRect = dirDocCanvas.getBoundingClientRect();
        dirDrawCanvas.style.width = docRect.width + 'px';
        dirDrawCanvas.style.height = docRect.height + 'px';
        dirDrawCanvas.style.left = '0';
        dirDrawCanvas.style.top = '0';
        loadPageDrawing();
    }

    function showPage() {
        if (isPdf && pdfDoc) {
            pdfDoc.getPage(currentPage).then(function(page) {
                var scale = 1.2;
                var viewport = page.getViewport({ scale: scale });
                dirDocCanvas.height = viewport.height;
                dirDocCanvas.width = viewport.width;
                page.render({ canvasContext: dirDocCanvas.getContext('2d'), viewport: viewport });
                dirDrawCanvas.width = dirDocCanvas.width;
                dirDrawCanvas.height = dirDocCanvas.height;
                dirDocCanvas.style.width = '100%';
                dirDocCanvas.style.height = 'auto';
                requestAnimationFrame(function() {
                    syncDrawCanvasSize();
                    initDrawLayer();
                });
            });
            dirPageInfo.textContent = 'หน้า ' + currentPage + ' / ' + numPages;
            dirPageNav.style.display = numPages > 1 ? 'flex' : 'none';
        }
    }

    if (isPdf) {
        pdfjsLib.getDocument(fileUrl).promise.then(function(pdf) {
            pdfDoc = pdf;
            numPages = pdf.numPages;
            currentPage = 1;
            showPage();
            document.getElementById('dirBtnPrev').onclick = function() {
                if (currentPage <= 1) return;
                savePageDrawing();
                currentPage--;
                showPage();
            };
            document.getElementById('dirBtnNext').onclick = function() {
                if (currentPage >= numPages) return;
                savePageDrawing();
                currentPage++;
                showPage();
            };
        }).catch(function() {
            dirPageInfo.textContent = 'โหลด PDF ไม่ได้';
        });
    } else {
        var img = new Image();
        img.crossOrigin = '';
        img.onload = function() {
            var maxW = 600;
            var scale = maxW / img.width;
            if (img.height * scale > 800) scale = 800 / img.height;
            var w = Math.floor(img.width * scale);
            var h = Math.floor(img.height * scale);
            dirDocCanvas.width = w;
            dirDocCanvas.height = h;
            dirDocCanvas.getContext('2d').drawImage(img, 0, 0, w, h);
            dirDrawCanvas.width = w;
            dirDrawCanvas.height = h;
            dirDocCanvas.style.width = '100%';
            dirDocCanvas.style.height = 'auto';
            numPages = 1;
            currentPage = 1;
            requestAnimationFrame(function() {
                syncDrawCanvasSize();
                initDrawLayer();
                updateSubmitBtn();
            });
        };
        img.src = fileUrl;
    }

    var resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            if (dirDocCanvas && dirDrawCanvas && (dirDocCanvas.width > 0 && dirDocCanvas.height > 0)) {
                syncDrawCanvasSize();
            }
        }, 150);
    });

    document.getElementById('dirBtnClear').onclick = function() {
        var ctx = dirDrawCanvas.getContext('2d');
        ctx.clearRect(0, 0, dirDrawCanvas.width, dirDrawCanvas.height);
        delete pageDrawings[currentPage];
        updateSubmitBtn();
    };

    var btnPen = document.getElementById('dirBtnPen');
    var btnEraser = document.getElementById('dirBtnEraser');
    function setTool(mode) {
        eraserMode = (mode === 'eraser');
        if (btnPen) {
            btnPen.classList.toggle('btn-secondary', !eraserMode);
            btnPen.classList.toggle('btn-outline-secondary', eraserMode);
        }
        if (btnEraser) {
            btnEraser.classList.toggle('btn-secondary', eraserMode);
            btnEraser.classList.toggle('btn-outline-secondary', !eraserMode);
        }
    }
    if (btnPen) btnPen.onclick = function() { setTool('pen'); };
    if (btnEraser) btnEraser.onclick = function() { setTool('eraser'); };

    document.getElementById('signForm').addEventListener('submit', function(e) {
        e.preventDefault();
        savePageDrawing();
        var form = this;
        form.querySelectorAll('input[name^="page_"]').forEach(function(inp) { inp.remove(); });
        for (var p in pageDrawings) {
            if (!pageDrawings.hasOwnProperty(p)) continue;
            var inp = document.createElement('input');
            inp.type = 'hidden';
            inp.name = 'page_' + p;
            inp.value = pageDrawings[p];
            form.appendChild(inp);
        }
        form.submit();
    });
})();
</script>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
