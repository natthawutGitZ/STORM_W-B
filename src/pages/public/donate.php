<?php
require_once ROOT_PATH . '/includes/db.php';
require_once ROOT_PATH . '/includes/functions.php';

// Auto-create tables
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `donate_settings` (
        `id` INT(1) NOT NULL DEFAULT 1,
        `page_title` VARCHAR(255) DEFAULT 'Donate',
        `page_description` TEXT,
        `promptpay_number` VARCHAR(20) DEFAULT '',
        `bank_account` VARCHAR(50) DEFAULT '',
        `bank_name` VARCHAR(100) DEFAULT '',
        `account_name` VARCHAR(100) DEFAULT '',
        `qr_image` VARCHAR(255) DEFAULT '',
        `discord_webhook` VARCHAR(500) DEFAULT '',
        `goal_amount` DECIMAL(12,2) DEFAULT 0,
        `goal_label` VARCHAR(100) DEFAULT '',
        `preset_amounts` VARCHAR(255) DEFAULT '20,50,100,200,500,1000',
        `is_active` TINYINT(1) DEFAULT 1,
        `min_amount` DECIMAL(10,2) DEFAULT 1,
        `thank_you_message` TEXT,
        `thunder_api_key` VARCHAR(255) DEFAULT '',
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Add Thunder API column if missing
    try { $pdo->exec("ALTER TABLE `donate_settings` ADD COLUMN `thunder_api_key` VARCHAR(255) DEFAULT '' AFTER `thank_you_message`"); } catch (PDOException $e) {}

    $pdo->exec("CREATE TABLE IF NOT EXISTS `donations` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `donor_name` VARCHAR(100) DEFAULT 'Anonymous',
        `amount` DECIMAL(12,2) NOT NULL,
        `message` TEXT,
        `slip_image` VARCHAR(255) DEFAULT NULL,
        `status` ENUM('pending','approved','rejected') DEFAULT 'pending',
        `admin_note` VARCHAR(255) DEFAULT NULL,
        `ip_address` VARCHAR(45) DEFAULT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `reviewed_at` DATETIME DEFAULT NULL,
        `reviewed_by` VARCHAR(100) DEFAULT NULL,
        `transaction_ref` VARCHAR(100) DEFAULT NULL,
        `verify_status` VARCHAR(20) DEFAULT NULL,
        `verify_data` TEXT DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_status` (`status`),
        KEY `idx_created` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Add verify columns if missing
    try { $pdo->exec("ALTER TABLE `donations` ADD COLUMN `transaction_ref` VARCHAR(100) DEFAULT NULL AFTER `reviewed_by`"); } catch (PDOException $e) {}
    try { $pdo->exec("ALTER TABLE `donations` ADD COLUMN `verify_status` VARCHAR(20) DEFAULT NULL AFTER `transaction_ref`"); } catch (PDOException $e) {}
    try { $pdo->exec("ALTER TABLE `donations` ADD COLUMN `verify_data` TEXT DEFAULT NULL AFTER `verify_status`"); } catch (PDOException $e) {}

    // Insert default settings if not exists
    $pdo->exec("INSERT IGNORE INTO donate_settings (id) VALUES (1)");
} catch (PDOException $e) {
    // Tables may already exist
}

// Fetch settings
$settings = $pdo->query("SELECT * FROM donate_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
if (!$settings) {
    die('Donate system not configured.');
}

// Calculate total & goal progress (current month only)
$currentMonth = date('Y-m-01 00:00:00');
$totalStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM donations WHERE status = 'approved' AND created_at >= ?");
$totalStmt->execute([$currentMonth]);
$totalDonated = $totalStmt->fetch()['total'];

$presets = array_filter(array_map('trim', explode(',', $settings['preset_amounts'] ?? '20,50,100,200,500,1000')));
$goalAmount = floatval($settings['goal_amount']);
$goalPercent = $goalAmount > 0 ? min(100, ($totalDonated / $goalAmount) * 100) : 0;

$pageTitle = htmlspecialchars($settings['page_title'] ?: 'Donate');
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> | S.T.O.R.M.</title>
    <link rel="icon" href="assets/images/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        :root {
            --bg-primary: #0f0f0f;
            --bg-secondary: #1e1e1e;
            --bg-card: rgba(20,20,20,0.85);
            --bg-card-hover: #252525;
            --accent: #c5a059;
            --accent-glow: rgba(197,160,89,0.3);
            --success: #43b581;
            --warning: #faa61a;
            --danger: #f04747;
            --text: #f0f0f0;
            --text-muted: #a0a0a0;
            --text-dim: #72767d;
            --border: rgba(255,255,255,0.08);
            --radius: 16px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: var(--bg-primary);
            color: var(--text);
            min-height: 100vh;
            line-height: 1.6;
            position: relative;
            zoom: 0.8;
        }

        /* Background like index.php */
        .bg-layer {
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            z-index: 0;
            background: linear-gradient(to bottom, rgba(15,15,15,0.3), rgba(15,15,15,1)),
                        url('assets/images/hero-bg.jpg') no-repeat center center;
            background-size: cover;
            background-attachment: fixed;
        }
        .bg-layer::before {
            content: '';
            position: absolute;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: radial-gradient(circle, transparent 20%, #0f0f0f 150%);
            pointer-events: none;
        }

        .container {
            max-width: 520px;
            margin: 0 auto;
            padding: 20px 16px 40px;
            position: relative;
            z-index: 1;
        }

        /* Header */
        .donate-header {
            text-align: center;
            padding: 40px 0 30px;
        }
        .donate-header .icon {
            width: 80px; height: 80px;
            border-radius: 50%;
            display: inline-block;
            margin-bottom: 16px;
            box-shadow: 0 8px 32px var(--accent-glow);
            overflow: hidden;
            border: 3px solid var(--accent);
        }
        .donate-header .icon img {
            width: 100%; height: 100%; object-fit: cover;
        }
        .donate-header h1 {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 6px;
        }
        .donate-header p {
            color: var(--text-muted);
            font-size: 0.95rem;
        }

        /* Goal Bar */
        .goal-bar {
            background: var(--bg-card);
            backdrop-filter: blur(10px);
            border-radius: var(--radius);
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid var(--border);
        }
        .goal-label {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 10px; font-size: 0.9rem;
        }
        .goal-label .target { color: var(--text-muted); }
        .goal-label .current { color: var(--success); font-weight: 700; }
        .goal-track {
            background: rgba(255,255,255,0.06);
            border-radius: 10px;
            height: 12px;
            overflow: hidden;
        }
        .goal-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--accent), var(--success));
            border-radius: 10px;
            transition: width 1s ease;
            min-width: 2px;
        }

        /* Card */
        .card {
            background: var(--bg-card);
            backdrop-filter: blur(10px);
            border-radius: var(--radius);
            padding: 24px;
            margin-bottom: 16px;
            border: 1px solid var(--border);
        }
        .card-title {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 16px;
            display: flex; align-items: center; gap: 8px;
        }
        .card-title i { color: var(--accent); }

        /* Amount Presets */
        .preset-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 14px;
        }
        .preset-btn {
            background: rgba(197,160,89,0.08);
            border: 2px solid rgba(197,160,89,0.15);
            border-radius: 12px;
            padding: 12px 8px;
            color: var(--text);
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-align: center;
        }
        .preset-btn:hover { border-color: var(--accent); background: rgba(197,160,89,0.15); }
        .preset-btn.active {
            border-color: var(--accent);
            background: rgba(197,160,89,0.2);
            box-shadow: 0 0 20px var(--accent-glow);
        }

        /* Inputs */
        .input-group { margin-bottom: 14px; }
        .input-group label {
            display: block; font-size: 0.85rem;
            color: var(--text-muted); margin-bottom: 6px; font-weight: 500;
        }
        .modern-input {
            width: 100%;
            background: rgba(0,0,0,0.3);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 12px 14px;
            color: var(--text);
            font-size: 1rem;
            outline: none;
            transition: border-color 0.2s;
            font-family: inherit;
        }
        .modern-input:focus { border-color: var(--accent); }
        textarea.modern-input { resize: vertical; min-height: 60px; }

        /* QR Section */
        .qr-section {
            text-align: center;
            padding: 20px;
        }
        .qr-section img {
            max-width: 240px;
            border-radius: 12px;
            margin-bottom: 12px;
            background: #fff;
            padding: 8px;
        }
        .promptpay-number {
            background: rgba(0,0,0,0.3);
            border-radius: 10px;
            padding: 10px 16px;
            display: inline-flex; align-items: center; gap: 8px;
            font-family: monospace;
            font-size: 1.1rem;
            letter-spacing: 1px;
            cursor: pointer;
            transition: background 0.2s;
        }
        .promptpay-number:hover { background: rgba(197,160,89,0.15); }

        /* Upload */
        .upload-area {
            border: 2px dashed rgba(255,255,255,0.1);
            border-radius: 12px;
            padding: 30px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
            position: relative;
        }
        .upload-area:hover, .upload-area.dragover {
            border-color: var(--accent);
            background: rgba(197,160,89,0.05);
        }
        .upload-area i { font-size: 2rem; color: var(--text-dim); margin-bottom: 8px; }
        .upload-area p { color: var(--text-muted); font-size: 0.85rem; }
        .upload-area input { display: none; }
        .upload-preview {
            display: none;
            margin-top: 12px;
            text-align: center;
        }
        .upload-preview img {
            max-width: 200px;
            max-height: 200px;
            border-radius: 8px;
            object-fit: contain;
        }

        /* Submit Button */
        .btn-submit {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, var(--accent), #8a6d2f);
            border: none;
            border-radius: 12px;
            color: #fff;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 8px 30px var(--accent-glow); }
        .btn-submit:disabled { opacity: 0.5; cursor: not-allowed; transform: none; box-shadow: none; }

        /* Recent Donations */
        .donation-item {
            display: flex; align-items: center; justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid var(--border);
        }
        .donation-item:last-child { border-bottom: none; }
        .donation-info .name { font-weight: 600; font-size: 0.95rem; }
        .donation-info .msg { color: var(--text-dim); font-size: 0.8rem; margin-top: 2px; }
        .donation-info .time { color: var(--text-dim); font-size: 0.75rem; }
        .donation-amount {
            font-weight: 700; color: var(--success);
            font-size: 1.05rem; white-space: nowrap;
        }

        /* Status Messages */
        .alert {
            padding: 14px 18px;
            border-radius: 12px;
            margin-bottom: 16px;
            font-size: 0.9rem;
            display: flex; align-items: center; gap: 10px;
        }
        .alert-success { background: rgba(67,181,129,0.12); color: var(--success); border: 1px solid rgba(67,181,129,0.2); }
        .alert-error { background: rgba(240,71,71,0.12); color: var(--danger); border: 1px solid rgba(240,71,71,0.2); }

        /* Steps */
        .steps { display: flex; gap: 4px; margin-bottom: 20px; }
        .step {
            flex: 1; height: 4px;
            background: rgba(255,255,255,0.06);
            border-radius: 4px;
            transition: background 0.3s;
        }
        .step.active { background: var(--accent); }
        .step.done { background: var(--success); }

        /* Responsive */
        @media (max-width: 400px) {
            .preset-grid { grid-template-columns: repeat(2, 1fr); }
            .container { padding: 12px 12px 30px; }
        }

        /* Animations */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate { animation: fadeInUp 0.4s ease forwards; }

        .empty-state {
            text-align: center; color: var(--text-dim); padding: 30px;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>

<div class="bg-layer"></div>

<div class="container">
    <!-- Header -->
    <div class="donate-header animate">
        <div class="icon"><img src="assets/images/logo.png" alt="Logo"></div>
        <h1><?= $pageTitle ?></h1>
        <?php if (!empty($settings['page_description'])): ?>
            <p><?= htmlspecialchars($settings['page_description']) ?></p>
        <?php endif; ?>
    </div>

    <!-- Goal Bar -->
    <?php if ($goalAmount > 0): ?>
    <div class="goal-bar animate" style="animation-delay: 0.1s;">
        <div class="goal-label">
            <span class="current"><i class="fas fa-coins"></i> ฿<?= number_format($totalDonated, 0) ?></span>
            <span class="target">เป้าหมาย: ฿<?= number_format($goalAmount, 0) ?>/เดือน <?= htmlspecialchars($settings['goal_label'] ?? '') ?></span>
        </div>
        <div class="goal-track">
            <div class="goal-fill" style="width: <?= $goalPercent ?>%;"></div>
        </div>
    </div>
    <?php endif; ?>

    <div id="alertContainer"></div>

    <!-- Step 1: Amount -->
    <div class="card animate" style="animation-delay: 0.15s;" id="stepAmount">
        <div class="card-title"><i class="fas fa-coins"></i> จำนวนเงิน</div>
        <div class="steps">
            <div class="step active" id="s1"></div>
            <div class="step" id="s2"></div>
            <div class="step" id="s3"></div>
        </div>

        <div class="preset-grid">
            <?php foreach ($presets as $amt): ?>
                <button type="button" class="preset-btn" data-amount="<?= intval($amt) ?>"
                    onclick="selectPreset(this)"><?= number_format(intval($amt)) ?> ฿</button>
            <?php endforeach; ?>
        </div>

        <div class="input-group">
            <label>หรือกรอกจำนวนเอง</label>
            <input type="number" id="customAmount" class="modern-input" placeholder="0.00"
                min="<?= $settings['min_amount'] ?>" step="1" oninput="clearPresets()">
        </div>

        <div class="input-group">
            <label>ชื่อผู้บริจาค (ไม่จำเป็น)</label>
            <input type="text" id="donorName" class="modern-input" placeholder="Anonymous" maxlength="100">
        </div>

        <div class="input-group">
            <label>ข้อความ (ไม่จำเป็น)</label>
            <textarea id="donorMessage" class="modern-input" placeholder="ฝากข้อความถึงทีมงาน..." maxlength="500"></textarea>
        </div>

        <button class="btn-submit" onclick="goToPayment()">
            <i class="fas fa-arrow-right"></i> ดำเนินการชำระเงิน
        </button>
    </div>

    <!-- Step 2: Payment QR -->
    <div class="card animate" style="display:none;" id="stepPayment">
        <div class="card-title"><i class="fas fa-qrcode"></i> ชำระเงิน</div>
        <div class="steps">
            <div class="step done" id="s1b"></div>
            <div class="step active" id="s2b"></div>
            <div class="step" id="s3b"></div>
        </div>

        <div class="qr-section">
            <?php if (!empty($settings['promptpay_number'])): ?>
                <div id="ppQrCode" style="display:inline-block; background:#fff; padding:10px; border-radius:12px; margin-bottom:14px;"></div>
                <div style="margin-bottom: 10px; color: var(--text-muted); font-size: 0.85rem;">PromptPay</div>
                <div class="promptpay-number" onclick="copyPromptPay()" title="คลิกเพื่อคัดลอก">
                    <i class="fas fa-copy"></i> <?= htmlspecialchars($settings['promptpay_number']) ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($settings['account_name'])): ?>
                <div style="margin-top: 10px; color: var(--text-muted); font-size: 0.85rem;"><?= htmlspecialchars($settings['account_name']) ?></div>
            <?php endif; ?>
            <?php if (!empty($settings['bank_name'])): ?>
                <div style="margin-top: 14px; color: var(--text-muted); font-size: 0.85rem;">
                    <?= htmlspecialchars($settings['bank_name']) ?>
                    <?php if (!empty($settings['bank_account'])): ?>
                        <br><span style="font-family: monospace; color: var(--text);"><?= htmlspecialchars($settings['bank_account']) ?></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <div style="margin-top: 16px; padding: 12px; background: rgba(197,160,89,0.1); border-radius: 10px;">
                <div style="font-weight: 700; font-size: 1.3rem; color: var(--accent);" id="payAmountDisplay">฿0</div>
                <div style="color: var(--text-muted); font-size: 0.8rem;">โอนตามจำนวนเงินนี้</div>
            </div>
        </div>

        <button class="btn-submit" onclick="saveQrImage()" style="margin-top: 10px; background: rgba(197,160,89,0.15); border: 1px solid rgba(197,160,89,0.3); color: var(--accent);">
            <i class="fas fa-download"></i> บันทึก QR Code
        </button>
        <button class="btn-submit" onclick="goToUpload()" style="margin-top: 10px;">
            <i class="fas fa-camera"></i> อัปโหลดสลิป
        </button>
        <button class="btn-submit" onclick="goToAmount()" style="margin-top: 8px; background: rgba(255,255,255,0.05); font-size: 0.9rem;">
            <i class="fas fa-arrow-left"></i> กลับ
        </button>
    </div>

    <!-- Step 3: Upload Slip -->
    <div class="card animate" style="display:none;" id="stepUpload">
        <div class="card-title"><i class="fas fa-receipt"></i> อัปโหลดสลิป</div>
        <div class="steps">
            <div class="step done"></div>
            <div class="step done"></div>
            <div class="step active"></div>
        </div>

        <div class="upload-area" id="uploadArea" onclick="document.getElementById('slipFile').click()">
            <i class="fas fa-cloud-upload-alt"></i>
            <p>คลิกหรือลากไฟล์มาวางที่นี่</p>
            <p style="font-size: 0.75rem; color: var(--text-dim);">รองรับ JPG, PNG ขนาดไม่เกิน 5MB</p>
            <input type="file" id="slipFile" accept="image/jpeg,image/png,image/webp" onchange="previewSlip(this)">
        </div>
        <div class="upload-preview" id="slipPreview">
            <img id="slipPreviewImg" src="" alt="Slip Preview">
        </div>

        <button class="btn-submit" onclick="submitDonation()" id="btnSubmit" style="margin-top: 16px;" disabled>
            <i class="fas fa-paper-plane"></i> ส่งรายการโดเนท
        </button>
        <button class="btn-submit" onclick="goToPayment()" style="margin-top: 8px; background: rgba(255,255,255,0.05); font-size: 0.9rem;">
            <i class="fas fa-arrow-left"></i> กลับ
        </button>
    </div>

    <!-- Success State -->
    <div class="card animate" style="display:none;" id="stepSuccess">
        <div style="text-align:center; padding: 20px;">
            <div style="width:70px; height:70px; background:rgba(67,181,129,0.15); border-radius:50%; display:inline-flex; align-items:center; justify-content:center; margin-bottom:16px;">
                <i class="fas fa-check" style="font-size:2rem; color:var(--success);"></i>
            </div>
            <h3 style="margin-bottom:8px;">ส่งรายการสำเร็จ!</h3>
            <div id="successMessage" style="margin-bottom:8px;"></div>
            <p style="color:var(--text-muted); font-size:0.9rem;">
                <?= htmlspecialchars($settings['thank_you_message'] ?? 'ขอบคุณสำหรับการสนับสนุน! รายการของคุณจะได้รับการตรวจสอบโดยเร็ว') ?>
            </p>
            <button class="btn-submit" onclick="resetForm()" style="margin-top:20px;">
                <i class="fas fa-redo"></i> โดเนทอีกครั้ง
            </button>
        </div>
    </div>

</div>

<script>
let selectedAmount = 0;
let selectedFile = null;

function selectPreset(btn) {
    document.querySelectorAll('.preset-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    selectedAmount = parseFloat(btn.dataset.amount);
    document.getElementById('customAmount').value = '';
}

function clearPresets() {
    document.querySelectorAll('.preset-btn').forEach(b => b.classList.remove('active'));
    selectedAmount = 0;
}

function getAmount() {
    const custom = parseFloat(document.getElementById('customAmount').value);
    return custom > 0 ? custom : selectedAmount;
}

function goToAmount() {
    document.getElementById('stepPayment').style.display = 'none';
    document.getElementById('stepAmount').style.display = 'block';
}

// PromptPay QR Code Generator (EMVCo standard)
const PP_AID = 'A000000677010111';
const PP_NUMBER = '<?= addslashes($settings['promptpay_number'] ?? '') ?>';

function ppTag(id, val) { return id + ('0' + val.length).slice(-2) + val; }

function ppCrc16(str) {
    let crc = 0xFFFF;
    for (let i = 0; i < str.length; i++) {
        crc ^= str.charCodeAt(i) << 8;
        for (let j = 0; j < 8; j++) crc = crc & 0x8000 ? (crc << 1) ^ 0x1021 : crc << 1;
        crc &= 0xFFFF;
    }
    return ('0000' + crc.toString(16).toUpperCase()).slice(-4);
}

function ppFormatTarget(id) {
    const sanitized = id.replace(/[^0-9]/g, '');
    if (sanitized.length >= 13) return ppTag('02', sanitized);
    // Phone: add 66 prefix
    const phone = '0066' + sanitized.replace(/^0/, '');
    return ppTag('01', phone);
}

function generatePromptPayPayload(amount) {
    let payload = '';
    payload += ppTag('00', '01'); // format indicator
    payload += ppTag('01', amount > 0 ? '12' : '11'); // 12=dynamic, 11=static
    // Tag 29: PromptPay merchant
    const merchantData = ppTag('00', PP_AID) + ppFormatTarget(PP_NUMBER);
    payload += ppTag('29', merchantData);
    payload += ppTag('53', '764'); // THB
    if (amount > 0) payload += ppTag('54', amount.toFixed(2));
    payload += ppTag('58', 'TH');
    payload += ppTag('63', '0000'); // placeholder CRC
    // Replace CRC placeholder
    const crc = ppCrc16(payload);
    payload = payload.slice(0, -4) + crc;
    return payload;
}

let ppQrInstance = null;

function renderPromptPayQR(amount) {
    const container = document.getElementById('ppQrCode');
    if (!container || !PP_NUMBER) return;
    const payload = generatePromptPayPayload(amount);
    container.innerHTML = '';
    ppQrInstance = new QRCode(container, {
        text: payload,
        width: 220,
        height: 220,
        colorDark: '#000000',
        colorLight: '#ffffff',
        correctLevel: QRCode.CorrectLevel.M
    });
}

function saveQrImage() {
    const container = document.getElementById('ppQrCode');
    if (!container) return;
    const canvas = container.querySelector('canvas');
    if (!canvas) { showAlert('ไม่พบ QR Code', 'error'); return; }
    const amt = getAmount();
    // Create a padded canvas with white background and label
    const pad = 30;
    const labelH = 36;
    const w = canvas.width + pad * 2;
    const h = canvas.height + pad * 2 + labelH;
    const c2 = document.createElement('canvas');
    c2.width = w; c2.height = h;
    const ctx = c2.getContext('2d');
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, w, h);
    ctx.drawImage(canvas, pad, pad);
    ctx.fillStyle = '#333333';
    ctx.font = 'bold 16px sans-serif';
    ctx.textAlign = 'center';
    ctx.fillText('PromptPay ฿' + amt.toLocaleString(), w / 2, canvas.height + pad + labelH - 8);
    const link = document.createElement('a');
    link.download = 'promptpay_' + amt + '.png';
    link.href = c2.toDataURL('image/png');
    link.click();
}

function goToPayment() {
    const amt = getAmount();
    if (!amt || amt < <?= $settings['min_amount'] ?>) {
        showAlert('กรุณาเลือกหรือกรอกจำนวนเงิน', 'error');
        return;
    }
    document.getElementById('payAmountDisplay').textContent = '฿' + amt.toLocaleString();
    document.getElementById('stepAmount').style.display = 'none';
    document.getElementById('stepPayment').style.display = 'block';
    renderPromptPayQR(amt);
}

function goToUpload() {
    document.getElementById('stepPayment').style.display = 'none';
    document.getElementById('stepUpload').style.display = 'block';
}

function previewSlip(input) {
    if (input.files && input.files[0]) {
        selectedFile = input.files[0];
        if (selectedFile.size > 5 * 1024 * 1024) {
            showAlert('ไฟล์มีขนาดเกิน 5MB', 'error');
            selectedFile = null;
            return;
        }
        const reader = new FileReader();
        reader.onload = e => {
            document.getElementById('slipPreviewImg').src = e.target.result;
            document.getElementById('slipPreview').style.display = 'block';
            document.getElementById('btnSubmit').disabled = false;
        };
        reader.readAsDataURL(selectedFile);
    }
}

// Drag & drop
const uploadArea = document.getElementById('uploadArea');
if (uploadArea) {
    ['dragenter','dragover'].forEach(evt => {
        uploadArea.addEventListener(evt, e => { e.preventDefault(); uploadArea.classList.add('dragover'); });
    });
    ['dragleave','drop'].forEach(evt => {
        uploadArea.addEventListener(evt, e => { e.preventDefault(); uploadArea.classList.remove('dragover'); });
    });
    uploadArea.addEventListener('drop', e => {
        const dt = e.dataTransfer;
        if (dt.files.length) {
            document.getElementById('slipFile').files = dt.files;
            previewSlip(document.getElementById('slipFile'));
        }
    });
}

async function submitDonation() {
    const amt = getAmount();
    const btn = document.getElementById('btnSubmit');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> กำลังตรวจสอบสลิป...';

    const fd = new FormData();
    fd.append('amount', amt);
    fd.append('donor_name', document.getElementById('donorName').value || 'Anonymous');
    fd.append('message', document.getElementById('donorMessage').value || '');
    if (selectedFile) fd.append('slip', selectedFile);

    try {
        const resp = await fetch('upload_slip.php', { method: 'POST', body: fd });
        const data = await resp.json();
        if (data.success) {
            const successMsg = document.getElementById('successMessage');
            if (data.auto_approved) {
                successMsg.innerHTML = '<span style="color:var(--success);"><i class="fas fa-shield-alt"></i> สลิปถูกตรวจสอบแล้ว อนุมัติอัตโนมัติ!</span>';
            } else if (data.message) {
                successMsg.innerHTML = '<span style="color:var(--warning);"><i class="fas fa-info-circle"></i> ' + data.message + '</span>';
            }
            document.getElementById('stepUpload').style.display = 'none';
            document.getElementById('stepSuccess').style.display = 'block';
        } else {
            showAlert(data.error || 'เกิดข้อผิดพลาด', 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane"></i> ส่งรายการโดเนท';
        }
    } catch (e) {
        showAlert('Network error', 'error');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane"></i> ส่งรายการโดเนท';
    }
}

function resetForm() {
    selectedAmount = 0;
    selectedFile = null;
    document.querySelectorAll('.preset-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('customAmount').value = '';
    document.getElementById('donorName').value = '';
    document.getElementById('donorMessage').value = '';
    document.getElementById('slipPreview').style.display = 'none';
    document.getElementById('slipFile').value = '';
    document.getElementById('btnSubmit').disabled = true;
    document.getElementById('btnSubmit').innerHTML = '<i class="fas fa-paper-plane"></i> ส่งรายการโดเนท';
    document.getElementById('stepSuccess').style.display = 'none';
    document.getElementById('stepUpload').style.display = 'none';
    document.getElementById('stepPayment').style.display = 'none';
    document.getElementById('stepAmount').style.display = 'block';
}

function copyPromptPay() {
    const num = '<?= addslashes($settings['promptpay_number'] ?? '') ?>';
    navigator.clipboard.writeText(num).then(() => showAlert('คัดลอกเลข PromptPay แล้ว', 'success'));
}

function showAlert(msg, type) {
    const c = document.getElementById('alertContainer');
    c.innerHTML = `<div class="alert alert-${type}"><i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i> ${msg}</div>`;
    setTimeout(() => c.innerHTML = '', 4000);
}
</script>
</body>
</html>

