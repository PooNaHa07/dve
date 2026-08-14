<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/configdb.php';
require_login();

$templates = [
    'pgfa.pdf' => 'แบบฟอร์ม PGFA',
    'pgga.pdf' => 'แบบฟอร์ม PGGA',
    'twifa.pdf' => 'แบบฟอร์ม TWIFA',
    'twipa.pdf' => 'แบบฟอร์ม TWIPA',
];
$allowed = array_keys($templates);
$current = isset($_GET['t']) && in_array($_GET['t'], $allowed) ? $_GET['t'] : null;
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . dirname($_SERVER['SCRIPT_NAME'] ?? '') . '/';

include __DIR__ . '/../includes/header.php';
?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<style>
.sign-pad { border: 2px solid #2563eb; border-radius: 0.5rem; background: #fff; cursor: crosshair; touch-action: none; }
.pdf-preview { border: 1px solid #e2e8f0; border-radius: 0.75rem; min-height: 400px; }
.sign-section { border: 1px solid #e2e8f0; border-radius: 0.75rem; padding: 1rem; margin-bottom: 1rem; background: #fff; }
#previewWrap { position: relative; display: inline-block; }
#pdfCanvasPreview { max-width: 100%; height: auto; border-radius: 0.5rem; display: block; margin: 0 auto; }
#drawCanvas { position: absolute; top: 0; left: 0; cursor: crosshair; touch-action: none; pointer-events: auto; }
</style>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold text-primary mb-0"><i class="bi bi-file-earmark-pdf me-2"></i>แบบฟอร์ม PDF — ลงนามแบบกำหนดจุดเอง</h4>
        <a href="<?= isset($_SESSION['role']) && $_SESSION['role'] === 'staff' ? '../roles/staff.php' : '../index.php' ?>" class="btn btn-outline-secondary rounded-pill">← กลับ</a>
    </div>

    <div class="card border-0 shadow-sm mb-4" style="border-radius: 1rem;">
        <div class="card-body">
            <p class="text-muted small mb-3">เลือกแบบฟอร์ม แล้ว<strong>เซ็นตรงบนเอกสารได้เลย</strong> — ลากเมาส์/นิ้ววาดลงนามบนพื้นที่ด้านล่าง (real-time) หลายหน้าได้</p>
            <div class="row g-2 mb-3">
                <?php foreach ($templates as $file => $label): ?>
                <div class="col-6 col-md-3">
                    <a href="?t=<?= urlencode($file) ?>" class="btn w-100 <?= $current === $file ? 'btn-primary' : 'btn-outline-primary' ?> rounded-pill">
                        <?= htmlspecialchars($label) ?>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <?php if ($current && file_exists(__DIR__ . '/' . $current)): ?>
    <input type="hidden" id="currentTemplate" value="<?= htmlspecialchars($current) ?>">
    <input type="hidden" id="pdfBaseUrl" value="<?= htmlspecialchars($baseUrl) ?>">

    <div class="row">
        <div class="col-lg-8 mb-4">
            <div class="card border-0 shadow-sm" style="border-radius: 1rem;">
                <div class="card-header bg-light border-0 py-2 d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <strong>ลงนามบนเอกสาร (real-time)</strong>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="small text-muted" id="pageInfo">—</span>
                        <div class="btn-group btn-group-sm">
                            <button type="button" id="btnPrevPage" class="btn btn-outline-secondary" title="หน้าก่อน">←</button>
                            <button type="button" id="btnNextPage" class="btn btn-outline-secondary" title="หน้าถัดไป">→</button>
                        </div>
                    </div>
                </div>
                <div class="card-body p-2 text-center">
                    <p class="small text-muted mb-2">ลากเมาส์หรือนิ้ววาดลงนามตรงบนเอกสารด้านล่างได้เลย</p>
                    <div id="previewWrap">
                        <canvas id="pdfCanvasPreview"></canvas>
                        <canvas id="drawCanvas"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm" style="border-radius: 1rem;">
                <div class="card-header bg-light border-0 py-2">
                    <strong>ลงนามบนเอกสาร</strong>
                </div>
                <div class="card-body">
                    <form method="POST" action="fill_pdf.php" id="formPdf" target="_blank">
                        <input type="hidden" name="template" value="<?= htmlspecialchars($current) ?>">
                        <input type="hidden" name="mode" value="draw">
                        <div class="d-flex gap-2 justify-content-center flex-wrap mb-2 align-items-center">
                            <span class="small text-muted me-1">เครื่องมือ:</span>
                            <button type="button" id="btnPen" class="btn btn-secondary btn-sm rounded-pill" title="ปากกา — วาดลายเซ็น">ปากกา</button>
                            <button type="button" id="btnEraser" class="btn btn-outline-secondary btn-sm rounded-pill" title="ยางลบ — ลากเพื่อลบบางส่วน">ยางลบ</button>
                            <button type="button" id="btnClearPage" class="btn btn-outline-secondary btn-sm rounded-pill">
                                <i class="bi bi-eraser me-1"></i>ล้างลายเซ็นหน้าปัจจุบัน
                            </button>
                        </div>
                        <button type="submit" id="btnSubmitPdf" class="btn btn-success rounded-pill px-4 mt-2" disabled>
                            <i class="bi bi-file-earmark-pdf me-1"></i> สร้าง PDF พร้อมลายเซ็น
                        </button>
                    </form>
                    <p class="text-muted small mt-3 mb-0">หลังกดสร้าง PDF หน้าต่างใหม่จะเปิด — กด Ctrl+P เพื่อพิมพ์หรือบันทึกเป็น PDF</p>
                </div>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="alert alert-info">กรุณาเลือกแบบฟอร์มด้านบน</div>
    <?php endif; ?>
</div>

<?php if ($current): ?>
<script>
(function() {
    var template = document.getElementById('currentTemplate').value;
    var baseUrl = document.getElementById('pdfBaseUrl').value;
    var pdfUrl = baseUrl.replace(/\/$/, '') + '/' + template;
    var pdfDoc = null;
    var currentPage = 1;
    var numPages = 1;
    var viewport = null;
    var pageWidthPt = 595;
    var pageHeightPt = 842;
    var pageDrawings = {}; // หน้า -> base64 PNG
    var drawLayerInited = false;
    var eraserMode = false;

    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

    function initDrawLayer(drawCanvas) {
        var ctx = drawCanvas.getContext('2d');
        var drawing = false, lastX = 0, lastY = 0;
        function getPos(e) {
            var r = drawCanvas.getBoundingClientRect();
            var scaleX = drawCanvas.width / r.width, scaleY = drawCanvas.height / r.height;
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
        function end(e) { e.preventDefault(); drawing = false; savePageDrawing(); updateSubmitButton(); }
        drawCanvas.addEventListener('mousedown', start);
        drawCanvas.addEventListener('mousemove', move);
        drawCanvas.addEventListener('mouseup', end);
        drawCanvas.addEventListener('mouseleave', end);
        drawCanvas.addEventListener('touchstart', start, { passive: false });
        drawCanvas.addEventListener('touchmove', move, { passive: false });
        drawCanvas.addEventListener('touchend', end, { passive: false });
    }

    function savePageDrawing() {
        var dc = document.getElementById('drawCanvas');
        if (!dc || !dc.width) return;
        var data = dc.toDataURL('image/png');
        if (data.length > 100) pageDrawings[currentPage] = data;
    }

    function loadPageDrawing() {
        var dc = document.getElementById('drawCanvas');
        if (!dc) return;
        var ctx = dc.getContext('2d');
        ctx.clearRect(0, 0, dc.width, dc.height);
        if (pageDrawings[currentPage]) {
            var img = new Image();
            img.onload = function() { ctx.drawImage(img, 0, 0); };
            img.src = pageDrawings[currentPage];
        }
    }

    function updateSubmitButton() {
        var hasAny = Object.keys(pageDrawings).length > 0;
        document.getElementById('btnSubmitPdf').disabled = !hasAny;
    }

    function renderPage() {
        if (!pdfDoc) return;
        pdfDoc.getPage(currentPage).then(function(page) {
            var scale = 1.2;
            viewport = page.getViewport({ scale: scale });
            pageWidthPt = viewport.width;
            pageHeightPt = viewport.height;
            var canvas = document.getElementById('pdfCanvasPreview');
            var drawCanvas = document.getElementById('drawCanvas');
            if (!canvas) return;
            var ctx = canvas.getContext('2d');
            canvas.height = viewport.height;
            canvas.width = viewport.width;
            page.render({ canvasContext: ctx, viewport: viewport });
            if (drawCanvas) {
                drawCanvas.width = canvas.width;
                drawCanvas.height = canvas.height;
                drawCanvas.style.width = canvas.width + 'px';
                drawCanvas.style.height = canvas.height + 'px';
                loadPageDrawing();
                if (!drawLayerInited) {
                    initDrawLayer(drawCanvas);
                    drawLayerInited = true;
                }
            }
        });
    }

    function showPageInfo() {
        var el = document.getElementById('pageInfo');
        if (el) el.textContent = 'หน้า ' + currentPage + ' / ' + numPages;
    }

    pdfjsLib.getDocument(pdfUrl).promise.then(function(pdf) {
        pdfDoc = pdf;
        numPages = pdf.numPages;
        showPageInfo();
        renderPage();

        document.getElementById('btnPrevPage').onclick = function() {
            if (currentPage <= 1) return;
            savePageDrawing();
            currentPage--;
            showPageInfo();
            renderPage();
        };
        document.getElementById('btnNextPage').onclick = function() {
            if (currentPage >= numPages) return;
            savePageDrawing();
            currentPage++;
            showPageInfo();
            renderPage();
        };

        document.getElementById('btnClearPage').onclick = function() {
            var dc = document.getElementById('drawCanvas');
            if (!dc) return;
            var ctx = dc.getContext('2d');
            ctx.clearRect(0, 0, dc.width, dc.height);
            delete pageDrawings[currentPage];
            updateSubmitButton();
        };
        var btnPen = document.getElementById('btnPen');
        var btnEraser = document.getElementById('btnEraser');
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

        document.getElementById('formPdf').addEventListener('submit', function(e) {
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
    }).catch(function() {
        document.getElementById('pageInfo').textContent = 'โหลด PDF ไม่ได้';
    });
})();
</script>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
