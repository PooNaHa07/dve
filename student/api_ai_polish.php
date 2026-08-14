<?php
/**
 * Super-Smart Context-Aware AI Daily Report Polisher Engine (Upgraded)
 * วิทยาลัยอาชีวศึกษาเพชรบุรี (Phetchaburi Vocational College)
 */
header('Content-Type: application/json; charset=utf-8');

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$raw_text = isset($input['text']) ? trim($input['text']) : '';

if (empty($raw_text)) {
    echo json_encode(['success' => false, 'polished' => '', 'tones' => [], 'detected' => []]);
    exit;
}

/**
 * Advanced Sliding Window Fuzzy Match helper for Thai text
 * Calculates percentage of similarity between target keyword and text subsections
 */
function find_fuzzy_match_in_text($text, $kw, $threshold = 75.0) {
    $text_len = mb_strlen($text, 'UTF-8');
    $kw_len = mb_strlen($kw, 'UTF-8');
    if ($kw_len === 0) return false;
    
    // 1. Direct match (case-insensitive)
    if (mb_stripos($text, $kw, 0, 'UTF-8') !== false) {
        return [
            'matched' => true,
            'exact' => true,
            'found_word' => $kw,
            'similarity' => 100.0
        ];
    }
    
    // 2. Fuzzy match (only if keyword is long enough to avoid false positives)
    if ($kw_len < 3) return false;
    
    $best_similarity = 0;
    $best_sub = '';
    
    // Check windows of size: $kw_len - 1, $kw_len, $kw_len + 1
    for ($w = $kw_len - 1; $w <= $kw_len + 1; $w++) {
        if ($w <= 1) continue;
        for ($i = 0; $i <= $text_len - $w; $i++) {
            $sub = mb_substr($text, $i, $w, 'UTF-8');
            similar_text($sub, $kw, $percent);
            if ($percent > $best_similarity) {
                $best_similarity = $percent;
                $best_sub = $sub;
            }
        }
    }
    
    if ($best_similarity >= $threshold) {
        return [
            'matched' => true,
            'exact' => false,
            'found_word' => $best_sub,
            'similarity' => round($best_similarity, 1)
        ];
    }
    
    return false;
}

// 1. Dictionary mapping categories to rich vocabulary styles
$dictionary = [
    // --- TECHNICAL & VOCATIONAL CORE TASKS ---
    [
        'category' => 'technical',
        'keywords' => ['ซ่อมคอม', 'ซอมคอม', 'เช็กคอม', 'เช็คคอม', 'คอมเสีย', 'คอมพิวเตอร์เสีย', 'คอมชำรุด'],
        'formal' => 'ตรวจสอบวิเคราะห์ความขัดข้อง ซ่อมบำรุงรักษา และแก้ไขปัญหาฮาร์ดแวร์คอมพิวเตอร์สำนักงานที่ชำรุดเสียหาย',
        'concise' => 'ซ่อมบำรุงและแก้ไขเครื่องคอมพิวเตอร์สำนักงาน',
        'reflective' => 'ฝึกทักษะการตรวจสอบ ซ่อมบำรุง และแก้ไขปัญหาฮาร์ดแวร์คอมพิวเตอร์ร่วมกับพี่เลี้ยง'
    ],
    [
        'category' => 'technical',
        'keywords' => ['ลงวินโดว์', 'ลงวินโดวส์', 'ลง window', 'ลง windows', 'วินโดว์', 'วินโดวส์', 'ฟอร์แมต', 'format'],
        'formal' => 'ดำเนินการติดตั้งระบบปฏิบัติการ Windows ตั้งค่าระบบรักษาความปลอดภัยระบบ และลงโปรแกรมควบคุมอุปกรณ์ (Drivers) ต่างๆ',
        'concise' => 'ติดตั้งระบบปฏิบัติการ Windows และตั้งค่าไดรเวอร์',
        'reflective' => 'เรียนรู้ขั้นตอนการติดตั้งระบบปฏิบัติการ Windows และการกำหนดค่าความปลอดภัยคอมพิวเตอร์'
    ],
    [
        'category' => 'technical',
        'keywords' => ['ลงโปรแกรม', 'ลงแอป', 'ติดตั้งโปรแกรม', 'ลงโปรแกรมออฟฟิศ', 'ลง office', 'ลงโปรแกรมคอม'],
        'formal' => 'ติดตั้งชุดโปรแกรมสำนักงาน ซอฟต์แวร์ประยุกต์ และโปรแกรมอรรถประโยชน์เสริมที่จำเป็นต่อการปฏิบัติงานหลัก',
        'concise' => 'ติดตั้งซอฟต์แวร์สำนักงานและโปรแกรมที่จำเป็น',
        'reflective' => 'ฝึกฝนทักษะการจัดเตรียมซอฟต์แวร์สำนักงานและลงโปรแกรมประยุกต์ใช้งานต่างๆ'
    ],
    [
        'category' => 'technical',
        'keywords' => ['ประกอบคอม', 'ประกอบเครื่อง', 'ประกอบคอมพิวเตอร์'],
        'formal' => 'จัดระเบียบชุดชิ้นส่วนประกอบฮาร์ดแวร์คอมพิวเตอร์ ดำเนินการติดตั้งระบบระบายความร้อน และทดสอบการทำงานเบื้องต้น',
        'concise' => 'ประกอบและทดสอบเครื่องคอมพิวเตอร์ใหม่',
        'reflective' => 'เรียนรู้โครงสร้างภายในฮาร์ดแวร์ และฝึกประกอบชุดเครื่องคอมพิวเตอร์ตามข้อกำหนดวิชาชีพ'
    ],
    [
        'category' => 'technical',
        'keywords' => ['เดินสายแลน', 'เข้าหัวแลน', 'เข้าหัว lan', 'เดินสาย LAN', 'สายแลนขาด', 'เดินสายแลนด์'],
        'formal' => 'วางระบบสายสัญญาณเครือข่าย LAN ดำเนินการเข้าหัวเชื่อมต่อ RJ-45 และทดสอบความเสถียรของการเชื่อมต่อโครงข่าย',
        'concise' => 'ติดตั้งและเข้าหัวสายแลน (LAN) เครือข่าย',
        'reflective' => 'พัฒนาทักษะการเดินสาย LAN และเทคนิคการเข้าหัว RJ-45 พร้อมตรวจเช็กสัญญาณเครือข่าย'
    ],
    [
        'category' => 'technical',
        'keywords' => ['ต่อเน็ต', 'ต่อเนต', 'ต่อเน็ท', 'ต่ออินเทอร์เน็ต', 'ต่ออินเตอร์เน็ต', 'เซ็ตเร้าเตอร์', 'เซ็ตเตอร์', 'เน็ตหลุด', 'เน็ตใช้งานไม่ได้'],
        'formal' => 'ตรวจสอบการทำงานของอุปกรณ์กระจายสัญญาณ ดูแลเสถียรภาพ และแก้ไขปัญหาเชื่อมต่อโครงข่ายอินเทอร์เน็ต',
        'concise' => 'ตั้งค่าและแก้ไขปัญหาระบบอินเทอร์เน็ตขัดข้อง',
        'reflective' => 'ฝึกทักษะการคอนฟิกอุปกรณ์เครือข่ายและเรียนรู้วิธีการวิเคราะห์ปัญหาระบบอินเทอร์เน็ตขัดข้อง'
    ],
    [
        'category' => 'technical',
        'keywords' => ['ซ่อมเครื่องพิมพ์', 'ซ่อมปริ้นเตอร์', 'ซ่อมปริ๊นเตอร์', 'ซ่อมพรินเตอร์', 'เครื่องพิมพ์เสีย', 'เครื่องพิมพ์เปิดไม่ติด', 'ปริ้นเตอร์เสีย'],
        'formal' => 'ตรวจเช็กความชำรุด เปลี่ยนชิ้นส่วนกลไกฟีดกระดาษ และทำความสะอาดหัวพิมพ์และเคลียร์ตลับหมึกเครื่องพิมพ์สำนักงาน',
        'concise' => 'ตรวจเช็กและซ่อมบำรุงเครื่องพิมพ์สำนักงาน',
        'reflective' => 'เรียนรู้ระบบกลไกภายในเครื่องพิมพ์ ฝึกบำรุงรักษาและแก้ไขปัญหาตลับหมึกและกระดาษติด'
    ],
    [
        'category' => 'technical',
        'keywords' => ['ทำเว็บ', 'ทำเวป', 'ทำเว็บไซต์', 'เขียนโค้ด', 'เขียนเว็บบอร์ด', 'แก้ไขข้อผิดพลาดทางเทคนิค', 'แก้บั๊ก', 'แก้บัค', 'แก้ bug', 'debug', 'เขียนโปรแกรม'],
        'formal' => 'วิเคราะห์ความต้องการ พัฒนาระบบโครงสร้างเว็บไซต์ ทำการตรวจสอบและดำเนินการแก้ไขข้อผิดพลาดของระบบโค้ดดิ้ง',
        'concise' => 'เขียนโค้ดและพัฒนาปรับปรุงหน้าเว็บไซต์',
        'reflective' => 'พัฒนาทักษะการโค้ดดิ้ง ค้นหาจุดบกพร่องและแก้ไขข้อผิดพลาดในการทำงานของระบบเว็บไซต์'
    ],
    [
        'category' => 'technical',
        'keywords' => ['ออกแบบ', 'กราฟิก', 'กราฟฟิก', 'ตัดต่อ', 'แต่งรูป', 'photoshop', 'canva', 'ตัดต่อวิดีโอ'],
        'formal' => 'ออกแบบองค์ประกอบสื่อสร้างสรรค์ ทำงานกราฟิกประชาสัมพันธ์ ปรับแต่งภาพลักษณ์ และตัดต่อไฟล์วิดีโอนำเสนอแผนก',
        'concise' => 'ออกแบบงานกราฟิกและตัดต่อสื่อประชาสัมพันธ์',
        'reflective' => 'เสริมสร้างความคิดสร้างสรรค์ด้วยการฝึกออกแบบภาพกราฟิกและเรียนรู้เทคนิคการตัดต่อวิดีโอ'
    ],
    [
        'category' => 'technical',
        'keywords' => ['โพสต์เพจ', 'โพสเพจ', 'โปรโมท', 'ทำตลาด', 'หาลูกค้า', 'ยิงแอด', 'โพสเฟส', 'โพสต์เฟส'],
        'formal' => 'จัดทำข้อมูลสารสนเทศคอนเทนต์ วางแผนสื่อสารการตลาดดิจิทัล และดูแลปฏิสัมพันธ์บนหน้าสื่อสังคมออนไลน์ช่องทางหลัก',
        'concise' => 'ดูแลเพจและจัดทำคอนเทนต์การตลาดออนไลน์',
        'reflective' => 'ศึกษาเรียนรู้วิธีการตอบคำถามลูกค้าออนไลน์ และประมวลผลการตอบรับบนช่องทางหลัก'
    ],
    [
        'category' => 'technical',
        'keywords' => ['ลงบัญชี', 'ทำบัญชี', 'เช็กยอด', 'เช็คยอด', 'เชคยอด', 'คีย์บิล', 'คีย์ยอดบิล', 'ยอดเงิน', 'เช็คยอดบิล', 'คีย์ยอด'],
        'formal' => 'ตรวจสอบความถูกต้องของใบสำคัญรับ-จ่าย ลงบันทึกรายการสมุดรายวันขั้นต้น และจัดทำสรุปยอดการเงินประจำแผนก',
        'concise' => 'ลงบันทึกรายการบัญชีและสรุปยอดการเงินประจำวัน',
        'reflective' => 'ฝึกความละเอียดรอบคอบในการตรวจสอบตัวเลขทางบัญชีและจัดทำรายงานสรุปทางการเงิน'
    ],

    // --- ADMINISTRATIVE & DOCUMENTATION SUPPORT ---
    [
        'category' => 'admin',
        'keywords' => ['พิมพ์เอกสาร', 'ทำเอกสาร', 'พิมพ์งาน'],
        'formal' => 'จัดทำข้อมูล พิมพ์เอกสารธุรการประจำวัน และจัดหมวดหมู่ไฟล์ข้อมูลจัดพิมพ์เพื่อความสะดวกรวดเร็ว',
        'concise' => 'จัดทำและพิมพ์เอกสารธุรการสำนักงาน',
        'reflective' => 'ฝึกทักษะการพิมพ์และการใช้โปรแกรมสำนักงานเพื่อจัดเตรียมเอกสารราชการอย่างเป็นระบบ'
    ],
    [
        'category' => 'admin',
        'keywords' => ['ถ่ายเอกสาร', 'ถ่ายชีท', 'สแกนเอกสาร'],
        'formal' => 'ดำเนินการคัดลอก ถ่ายสำเนาสแกนเอกสารสำนักงาน จัดทำเล่มรายงาน และดูแลรักษาความปลอดภัยข้อมูลความลับ',
        'concise' => 'ถ่ายสำเนาและสแกนจัดเก็บเอกสารเข้าเครื่องคอมพิวเตอร์',
        'reflective' => 'เรียนรู้การทำงานของอุปกรณ์สำนักงานอเนกประสงค์และการจัดหมวดหมู่แฟ้มดิจิทัล'
    ],
    [
        'category' => 'admin',
        'keywords' => ['คีย์ข้อมูล', 'ลงข้อมูล', 'กรอกข้อมูล'],
        'formal' => 'บันทึกประมวลผลข้อมูลการทำสถิติประจำสัปดาห์ ลงข้อมูลในฐานข้อมูลอิเล็กทรอนิกส์ และคัดกรองความถูกต้องข้อมูล',
        'concise' => 'กรอกบันทึกข้อมูลเข้าระบบฐานข้อมูล',
        'reflective' => 'เรียนรู้วิธีการคัดกรองและนำข้อมูลดิบเข้าสู่ฐานข้อมูลด้วยความถูกต้องและแม่นยำ'
    ],
    [
        'category' => 'admin',
        'keywords' => ['จัดแฟ้ม', 'คัดแยกเอกสาร', 'จัดเรียงเอกสาร'],
        'formal' => 'จัดแยกประเภท คัดกรองรหัสหมวดหมู่ และจัดเก็บเอกสารเข้าแฟ้มสารบบระบบงานจัดเก็บเอกสารสำนักงาน',
        'concise' => 'จัดระเบียบสารบบและคัดแยกแฟ้มเอกสารเข้าชั้นวาง',
        'reflective' => 'เรียนรู้วิธีจัดระบบแฟ้มสารบรรณ ซึ่งช่วยให้เกิดความคล่องตัวในการค้นหาและจัดเก็บในสำนักงาน'
    ],
    [
        'category' => 'admin',
        'keywords' => ['ยกลัง', 'ขนย้าย', 'ยกของ', 'จัดของ'],
        'formal' => 'จัดระเบียบอุปกรณ์ จัดสรรสถานที่ ขนย้ายพัสดุสำนักงาน และขนย้ายอุปกรณ์สิ่งของนำเข้าสู่พื้นที่เก็บรักษาที่กำหนด',
        'concise' => 'จัดเรียงพัสดุและขนย้ายสิ่งของสำนักงาน',
        'reflective' => 'ฝึกฝนความพร้อมในการสนับสนุนกิจกรรมขนย้าย และการมีน้ำใจช่วยเหลือจัดพื้นที่ปฏิบัติงานให้เหมาะสม'
    ],
    [
        'category' => 'admin',
        'keywords' => ['แพ็กของ', 'แพ็คของ', 'ส่งของ', 'ส่งพัสดุ'],
        'formal' => 'ตรวจเช็กรายการวัสดุนำส่ง บรรจุหีบห่อวัสดุตามมาตรฐานแผนก และลงทะเบียนส่งพัสดุปลายทางอย่างเป็นระบบ',
        'concise' => 'แพ็กกล่องพัสดุและนำส่งไปรษณีย์บริการ',
        'reflective' => 'เรียนรู้ทักษะการตรวจสอบความสมบูรณ์ของพัสดุและการบรรจุหีบห่อเพื่อความปลอดภัยในการขนส่ง'
    ],

    // --- RELATIONSHIPS, SOFTSKILLS & LEARNING ---
    [
        'category' => 'softskills',
        'keywords' => ['ช่วยพี่', 'ช่วยงาน', 'ช่างแนะ', 'ทำตามพี่'],
        'formal' => 'ให้การสนับสนุน ประสานงานสนับสนุนภารกิจ และเรียนรู้เทคนิคการทำงานร่วมกับเจ้าหน้าที่พี่เลี้ยงอย่างเคร่งครัด',
        'concise' => 'ประสานงานและเรียนรู้งานเคียงข้างทีมงานพี่เลี้ยง',
        'reflective' => 'เสริมสร้างทักษะความรับผิดชอบและการปฏิบัติตามคำสั่งของพี่เลี้ยงอย่างมีประสิทธิภาพ'
    ],
    [
        'category' => 'softskills',
        'keywords' => ['รับโทรศัพท์', 'ต้อนรับ', 'รับลูกค้า', 'คุยลูกค้า'],
        'formal' => 'ทำหน้าที่ต้อนรับผู้มาติดต่อ ให้คำแนะนำ ประสานต้อนรับผู้มาเยือน และรับข้อความตอบกลับเบื้องต้นอย่างเป็นมิตร',
        'concise' => 'ต้อนรับผู้มาติดต่อและรับสายโทรศัพท์บริการประสานงาน',
        'reflective' => 'ฝึกฝนทักษะการสื่อสาร การแก้ปัญหาเฉพาะหน้า และการต้อนรับลูกค้าด้วยอัธยาศัยอันดี'
    ],
    [
        'category' => 'softskills',
        'keywords' => ['เรียนงาน', 'รับบรีฟ', 'ประชุม'],
        'formal' => 'เข้าร่วมการปฐมนิเทศแผน รับมอบหมายเป้าหมายการทำงานประจำวัน และมีส่วนร่วมรับฟังทิศทางภารกิจองค์กร',
        'concise' => 'เข้าร่วมประชุมฟังแผนงานและรับมอบหมายภารกิจจากหัวหน้างาน',
        'reflective' => 'ทำความเข้าใจทิศทางการดำเนินงานขององค์กร และฝึกการทำงานร่วมกับผู้อื่นอย่างเป็นระบบ'
    ],

    // --- ENVIRONMENT & HYGIENE ---
    [
        'category' => 'general',
        'keywords' => ['ทำความสะอาด', 'ปัดกวาด', 'เช็ดถู', 'กวาดออฟฟิศ', 'ถูพื้น'],
        'formal' => 'ทำความสะอาดปัดกวาดเช็ดถู ดูแลระบบสุขอนามัย และบำรุงรักษาสภาพแวดล้อมสำนักงานให้อยู่ในสภาวะพร้อมใช้งานและปลอดภัย',
        'concise' => 'ทำความสะอาดปัดกวาดดูแลความสะอาดสำนักงาน',
        'reflective' => 'ส่งเสริมจิตสาธารณะในการบำรุงรักษาสุขอนามัยและความสะอาดเรียบร้อยของส่วนรวม'
    ],
    [
        'category' => 'general',
        'keywords' => ['จัดโต๊ะ', 'จัดตู้'],
        'formal' => 'จัดระเบียบพื้นที่เครื่องใช้ จัดวางสิ่งของสำนักงานตามโครงการ 5ส เพื่อความสวยงามเป็นระเบียบและปลอดภัย',
        'concise' => 'จัดโต๊ะจัดตู้เก็บของตามแนวทาง 5ส',
        'reflective' => 'พัฒนาทักษะการวางระบบหมวดหมู่อุปกรณ์ตามหลัก 5ส เพื่อความปลอดภัยและเป็นระเบียบเรียบร้อย'
    ]
];

$matches_by_cat = [
    'technical' => [],
    'admin' => [],
    'softskills' => [],
    'general' => []
];

$detected = [];

foreach ($dictionary as $item) {
    foreach ($item['keywords'] as $kw) {
        $match = find_fuzzy_match_in_text($raw_text, $kw);
        if ($match) {
            $matches_by_cat[$item['category']][] = $item;
            
            // Log for the frontend highlighting
            $detected[] = [
                'raw' => $match['found_word'],
                'corrected' => $item['concise'], // Show the concise form as summary
                'category' => $item['category'],
                'exact' => $match['exact'],
                'similarity' => $match['similarity']
            ];
            break; // Stop testing other keywords in the same dictionary item once matched
        }
    }
}

// Remove duplicates in category matches
foreach ($matches_by_cat as $cat => $arr) {
    $temp = [];
    foreach ($arr as $item) {
        $temp[$item['formal']] = $item; // Key by formal string to unique
    }
    $matches_by_cat[$cat] = array_values($temp);
}

$sentences_formal = [];
$sentences_concise = [];
$sentences_reflective = [];

// 2. Generate sentence chains for the 3 distinct tones
if (!empty($matches_by_cat['technical'])) {
    $sentences_formal[] = "ได้ปฏิบัติงานหลักโดยการ" . implode(" และการ", array_column($matches_by_cat['technical'], 'formal'));
    $sentences_concise[] = "ปฏิบัติงานหลักด้าน" . implode(" และ", array_column($matches_by_cat['technical'], 'concise'));
    $sentences_reflective[] = "ได้ลงมือปฏิบัติและ" . implode(" อีกทั้งยังได้", array_column($matches_by_cat['technical'], 'reflective'));
}
if (!empty($matches_by_cat['admin'])) {
    $sentences_formal[] = "พร้อมทั้งได้ช่วยงานธุรการและบริหารจัดการระบบงาน ได้แก่ " . implode(" อีกทั้งยังได้ทำหน้าที่", array_column($matches_by_cat['admin'], 'formal'));
    $sentences_concise[] = "ช่วยงานเอกสารและการ" . implode(" รวมถึง", array_column($matches_by_cat['admin'], 'concise'));
    $sentences_reflective[] = "นอกจากนี้ยังได้ฝึกทักษะการ" . implode(" และพัฒนาทักษะด้าน", array_column($matches_by_cat['admin'], 'reflective'));
}
if (!empty($matches_by_cat['softskills'])) {
    $sentences_formal[] = "นอกจากนี้ยังได้ประสานการทำงาน ได้แก่ " . implode(" ร่วมด้วย", array_column($matches_by_cat['softskills'], 'formal'));
    $sentences_concise[] = "ประสานงานกับส่วนต่าง ๆ โดย" . implode(" และ", array_column($matches_by_cat['softskills'], 'concise'));
    $sentences_reflective[] = "ขณะเดียวกันก็ได้ศึกษาและ" . implode(" ตลอดจนเรียนรู้ผ่านการ", array_column($matches_by_cat['softskills'], 'reflective'));
}
if (!empty($matches_by_cat['general'])) {
    $sentences_formal[] = "รวมถึงมีส่วนร่วมในการ" . implode(" และ", array_column($matches_by_cat['general'], 'formal'));
    $sentences_concise[] = "และดูแลพื้นที่ทำงานโดย" . implode(" และ", array_column($matches_by_cat['general'], 'concise'));
    $sentences_reflective[] = "และช่วยปลูกฝังวินัยในตนเองจากการ" . implode(" และการ", array_column($matches_by_cat['general'], 'reflective'));
}

if (count($sentences_formal) > 0) {
    $polished_formal = implode(" จากนั้น", $sentences_formal) . " เพื่อให้การดำเนินภารกิจต่างๆ ของสถานประกอบการเสร็จสิ้นลุล่วงด้วยประสิทธิภาพสูงสุดอย่างเป็นระเบียบเรียบร้อย";
    $polished_concise = implode(" ", $sentences_concise) . " เพื่อให้เกิดความสะดวกรวดเร็วและเสร็จสิ้นตามหน้าที่ที่ได้รับมอบหมายประจำวัน";
    $polished_reflective = implode(" ", $sentences_reflective) . " ซึ่งทำให้มีความเข้าใจในกระบวนการทำงานจริง เพิ่มพูนประสบการณ์ และพร้อมสำหรับการพัฒนาต่อยอดในการปฏิบัติงานในอนาคต";
} else {
    // Fallback if no matching keywords found
    $polished_formal = "ได้ดำเนินการปฏิบัติงานในหน้าที่ที่ได้รับมอบหมายประจำวันอย่างเต็มความสามารถ โดยปฏิบัติหน้าที่ในส่วนของ " . $raw_text . " ด้วยความละเอียดรอบคอบ มุ่งมั่นพากเพียร และระมัดระวังในความปลอดภัยอย่างดีเยี่ยม เพื่อประสิทธิภาพการทำงานสูงสุดของแผนก";
    $polished_concise = "ปฏิบัติงานตามที่ได้รับมอบหมายประจำวันในส่วนของ " . $raw_text . " ได้อย่างรวดเร็ว ถูกต้อง และสำเร็จเสร็จสิ้นตามเป้าหมายของทีมงานเป็นอย่างดี";
    $polished_reflective = "ได้ตั้งใจศึกษาเรียนรู้และลองปฏิบัติงานในส่วนของ " . $raw_text . " ร่วมกับพี่เลี้ยงและเพื่อนร่วมงาน ได้เรียนรู้ขั้นตอนหน้างานจริงที่เป็นประโยชน์และช่วยเสริมสร้างประสบการณ์วิชาชีพเป็นอย่างมาก";
}

echo json_encode([
    'success' => true,
    'detected' => $detected,
    'polished' => $polished_formal, // Default fallback
    'tones' => [
        'formal' => $polished_formal,
        'concise' => $polished_concise,
        'reflective' => $polished_reflective
    ]
]);
