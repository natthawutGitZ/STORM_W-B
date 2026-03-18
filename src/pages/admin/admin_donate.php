<?php
require_once ROOT_PATH . '/includes/db.php';
require_once ROOT_PATH . '/includes/functions.php';

if (!isAdmin()) {
    redirect('../login');
}

// Ensure tables exist (donate.php creates them, but just in case)
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
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("INSERT IGNORE INTO donate_settings (id) VALUES (1)");

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
        PRIMARY KEY (`id`),
        KEY `idx_status` (`status`),
        KEY `idx_created` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (PDOException $e) {
}

// Stats
$stats = $pdo->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status='approved' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN status='rejected' THEN 1 ELSE 0 END) as rejected,
    COALESCE(SUM(CASE WHEN status='approved' THEN amount ELSE 0 END), 0) as total_amount
FROM donations")->fetch(PDO::FETCH_ASSOC);

$settings = $pdo->query("SELECT * FROM donate_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC);

// Fetch Discord channels for webhook channel selector
require_once ROOT_PATH . '/includes/bot_api.php';
$api = new BotAPI();
$channels = $api->getChannels();

include ROOT_PATH . '/admin/includes/admin_header.php';
?>

<style>
    :root {
        --gold: #c5a059;
        --gold-dim: rgba(197, 160, 89, 0.15);
        --gold-border: rgba(197, 160, 89, 0.25);
        --dark-bg: #0f0f0f;
        --card-bg: rgba(18, 18, 24, 0.85);
        --card-border: rgba(255, 255, 255, 0.06);
        --text-primary: #f2f3f5;
        --text-muted: #8b8d93;
        --success: #43b581;
        --warning: #faa61a;
        --danger: #f04747;
        --info: #5865F2;
    }

    .donate-admin {
        max-width: 1200px;
        margin: 0 auto;
    }

    /* Page Header */
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 28px;
        flex-wrap: wrap;
        gap: 12px;
    }

    .page-header h2 {
        margin: 0;
        font-size: 1.6rem;
        font-weight: 700;
        background: linear-gradient(135deg, var(--gold), #e8d5a3);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .page-header .btn-view {
        padding: 10px 20px;
        border-radius: 12px;
        border: 1px solid var(--gold-border);
        background: var(--gold-dim);
        color: var(--gold);
        text-decoration: none;
        font-weight: 600;
        font-size: 0.9rem;
        transition: all 0.2s;
    }

    .page-header .btn-view:hover {
        background: rgba(197, 160, 89, 0.25);
    }

    /* Stat Cards */
    .stat-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 16px;
        margin-bottom: 28px;
    }

    .stat-card {
        background: var(--card-bg);
        border-radius: 16px;
        padding: 22px;
        border: 1px solid var(--card-border);
        position: relative;
        overflow: hidden;
        transition: transform 0.2s, border-color 0.2s;
    }

    .stat-card:hover {
        transform: translateY(-2px);
        border-color: rgba(255, 255, 255, 0.1);
    }

    .stat-card .stat-icon {
        width: 42px;
        height: 42px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        margin-bottom: 12px;
    }

    .stat-card .stat-icon.pending {
        background: rgba(250, 166, 26, 0.12);
        color: var(--warning);
    }

    .stat-card .stat-icon.approved {
        background: rgba(67, 181, 129, 0.12);
        color: var(--success);
    }

    .stat-card .stat-icon.rejected {
        background: rgba(240, 71, 71, 0.12);
        color: var(--danger);
    }

    .stat-card .stat-icon.total {
        background: var(--gold-dim);
        color: var(--gold);
    }

    .stat-card .label {
        color: var(--text-muted);
        font-size: 0.8rem;
        margin-bottom: 4px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .stat-card .value {
        font-size: 1.9rem;
        font-weight: 700;
    }

    .stat-card .value.pending {
        color: var(--warning);
    }

    .stat-card .value.approved {
        color: var(--success);
    }

    .stat-card .value.rejected {
        color: var(--danger);
    }

    .stat-card .value.total {
        color: var(--gold);
    }

    /* Tabs */
    .donate-tabs {
        display: flex;
        gap: 6px;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }

    .d-tab {
        padding: 9px 20px;
        border-radius: 12px;
        border: 1px solid var(--card-border);
        background: rgba(0, 0, 0, 0.2);
        color: var(--text-muted);
        cursor: pointer;
        font-size: 0.88rem;
        transition: all 0.2s;
        font-weight: 500;
    }

    .d-tab:hover {
        border-color: rgba(255, 255, 255, 0.15);
        color: #fff;
    }

    .d-tab.active {
        background: var(--gold-dim);
        border-color: var(--gold-border);
        color: var(--gold);
    }

    /* Donation Cards */
    .donation-list {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .donation-card {
        background: var(--card-bg);
        border: 1px solid var(--card-border);
        border-radius: 14px;
        padding: 16px 20px;
        display: flex;
        align-items: center;
        gap: 16px;
        transition: all 0.2s;
        position: relative;
    }

    .donation-card:hover {
        border-color: rgba(255, 255, 255, 0.1);
        background: rgba(22, 22, 30, 0.9);
    }

    .donation-card.pending-card {
        border-left: 3px solid var(--warning);
    }

    .donation-card.approved-card {
        border-left: 3px solid var(--success);
    }

    .donation-card.rejected-card {
        border-left: 3px solid var(--danger);
    }

    .dc-avatar {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        font-weight: 700;
        flex-shrink: 0;
    }

    .dc-avatar.pending {
        background: rgba(250, 166, 26, 0.1);
        color: var(--warning);
    }

    .dc-avatar.approved {
        background: rgba(67, 181, 129, 0.1);
        color: var(--success);
    }

    .dc-avatar.rejected {
        background: rgba(240, 71, 71, 0.1);
        color: var(--danger);
    }

    .dc-info {
        flex: 1;
        min-width: 0;
    }

    .dc-info .dc-name {
        font-weight: 600;
        font-size: 0.95rem;
        color: var(--text-primary);
    }

    .dc-info .dc-meta {
        font-size: 0.78rem;
        color: var(--text-muted);
        margin-top: 3px;
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
    }

    .dc-info .dc-msg {
        font-size: 0.82rem;
        color: var(--text-muted);
        margin-top: 4px;
        font-style: italic;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        max-width: 300px;
    }

    .dc-amount {
        font-weight: 700;
        font-size: 1.15rem;
        color: var(--gold);
        white-space: nowrap;
    }

    .dc-slip {
        width: 48px;
        height: 48px;
        border-radius: 10px;
        object-fit: cover;
        cursor: pointer;
        border: 1px solid rgba(255, 255, 255, 0.08);
        transition: transform 0.2s;
        flex-shrink: 0;
    }

    .dc-slip:hover {
        transform: scale(1.15);
    }

    .badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.72rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }

    .badge-pending {
        background: rgba(250, 166, 26, 0.1);
        color: var(--warning);
    }

    .badge-approved {
        background: rgba(67, 181, 129, 0.1);
        color: var(--success);
    }

    .badge-rejected {
        background: rgba(240, 71, 71, 0.1);
        color: var(--danger);
    }

    .badge-verified {
        background: rgba(67, 181, 129, 0.1);
        color: var(--success);
    }

    .badge-auto {
        background: var(--gold-dim);
        color: var(--gold);
    }

    .dc-actions {
        display: flex;
        gap: 6px;
        flex-shrink: 0;
    }

    .dc-actions button {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        border: none;
        cursor: pointer;
        font-size: 0.85rem;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .btn-approve {
        background: rgba(67, 181, 129, 0.12);
        color: var(--success);
    }

    .btn-approve:hover {
        background: rgba(67, 181, 129, 0.25);
    }

    .btn-reject {
        background: rgba(240, 71, 71, 0.12);
        color: var(--danger);
    }

    .btn-reject:hover {
        background: rgba(240, 71, 71, 0.25);
    }

    .btn-discord {
        background: rgba(88, 101, 242, 0.12);
        color: var(--info);
    }

    .btn-discord:hover {
        background: rgba(88, 101, 242, 0.25);
    }

    .btn-delete {
        background: rgba(255, 255, 255, 0.04);
        color: #555;
    }

    .btn-delete:hover {
        background: rgba(240, 71, 71, 0.12);
        color: var(--danger);
    }

    /* Settings */
    .settings-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
    }

    .settings-grid .full {
        grid-column: 1 / -1;
    }

    @media (max-width: 768px) {
        .settings-grid {
            grid-template-columns: 1fr;
        }

        .donation-card {
            flex-wrap: wrap;
        }

        .dc-info .dc-msg {
            max-width: 160px;
        }
    }

    .settings-input {
        width: 100%;
        background: rgba(0, 0, 0, 0.35);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 12px;
        padding: 11px 16px;
        color: #fff;
        font-size: 0.93rem;
        outline: none;
        transition: border-color 0.2s;
    }

    .settings-input:focus {
        border-color: var(--gold);
        box-shadow: 0 0 0 3px rgba(197, 160, 89, 0.08);
    }

    .settings-label {
        display: block;
        color: var(--text-muted);
        font-size: 0.82rem;
        margin-bottom: 6px;
        font-weight: 500;
    }

    .section-divider {
        margin: 20px 0 16px;
        padding-top: 18px;
        border-top: 1px solid var(--card-border);
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--gold);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    /* Modal */
    .slip-modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.88);
        z-index: 9999;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        backdrop-filter: blur(8px);
    }

    .slip-modal-overlay.active {
        display: flex;
    }

    .slip-modal-overlay img {
        max-width: 90%;
        max-height: 90vh;
        border-radius: 14px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
    }

    .empty-state {
        text-align: center;
        padding: 50px 20px;
        color: var(--text-muted);
    }

    .empty-state i {
        font-size: 2.5rem;
        opacity: 0.2;
        display: block;
        margin-bottom: 12px;
    }

    .empty-state p {
        font-size: 0.9rem;
    }
</style>

<div class="donate-admin">
    <div class="page-header">
        <h2><i class="fas fa-hand-holding-heart"></i> Donation Manager</h2>
        <a href="../donate" target="_blank" class="btn-view">
            <i class="fas fa-external-link-alt"></i> View Donate Page
        </a>
    </div>

    <!-- Stats -->
    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-icon pending"><i class="fas fa-clock"></i></div>
            <div class="label">Pending</div>
            <div class="value pending"><?= $stats['pending'] ?? 0 ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon approved"><i class="fas fa-check-circle"></i></div>
            <div class="label">Approved</div>
            <div class="value approved"><?= $stats['approved'] ?? 0 ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon rejected"><i class="fas fa-times-circle"></i></div>
            <div class="label">Rejected</div>
            <div class="value rejected"><?= $stats['rejected'] ?? 0 ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon total"><i class="fas fa-coins"></i></div>
            <div class="label">Total Donated</div>
            <div class="value total">฿<?= number_format($stats['total_amount'] ?? 0, 0) ?></div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="donate-tabs">
        <button class="d-tab active" onclick="switchDonateTab('donations', this)"><i class="fas fa-list"></i>
            Donations</button>
        <button class="d-tab" onclick="switchDonateTab('settings', this)"><i class="fas fa-cog"></i> Settings</button>
    </div>

    <!-- Donations Tab -->
    <div id="donateTab-donations" class="glass-panel">
        <div style="display:flex; gap:6px; margin-bottom:18px; flex-wrap:wrap;">
            <button class="d-tab active" onclick="filterDonations('pending', this)" id="filterPending">
                <i class="fas fa-clock"></i> Pending <span
                    style="background:rgba(250,166,26,0.2);padding:2px 8px;border-radius:10px;font-size:0.75rem;margin-left:4px;"><?= $stats['pending'] ?? 0 ?></span>
            </button>
            <button class="d-tab" onclick="filterDonations('approved', this)" id="filterApproved">
                <i class="fas fa-check"></i> Approved
            </button>
            <button class="d-tab" onclick="filterDonations('rejected', this)" id="filterRejected">
                <i class="fas fa-times"></i> Rejected
            </button>
            <button class="d-tab" onclick="filterDonations('', this)" id="filterAll">
                <i class="fas fa-layer-group"></i> All
            </button>
        </div>

        <div class="donation-list" id="donationsBody">
            <div class="empty-state"><i class="fas fa-circle-notch fa-spin"></i>
                <p>Loading...</p>
            </div>
        </div>
    </div>

    <!-- Settings Tab -->
    <div id="donateTab-settings" class="glass-panel" style="display:none;">
        <div class="section-divider" style="margin-top:0; padding-top:0; border:none;"><i class="fas fa-cog"></i> Donate
            Settings</div>

        <form id="donateSettingsForm" onsubmit="saveDonateSettings(event)">
            <div class="settings-grid">
                <div>
                    <label class="settings-label">Page Title</label>
                    <input type="text" name="page_title" class="settings-input"
                        value="<?= htmlspecialchars($settings['page_title'] ?? '') ?>">
                </div>
                <div>
                    <label class="settings-label">Goal Amount (0 = disabled)</label>
                    <input type="number" name="goal_amount" class="settings-input"
                        value="<?= $settings['goal_amount'] ?? 0 ?>" min="0" step="1">
                </div>
                <div class="full">
                    <label class="settings-label">Page Description</label>
                    <textarea name="page_description" class="settings-input"
                        rows="2"><?= htmlspecialchars($settings['page_description'] ?? '') ?></textarea>
                </div>
                <div>
                    <label class="settings-label">PromptPay Number</label>
                    <input type="text" name="promptpay_number" class="settings-input"
                        value="<?= htmlspecialchars($settings['promptpay_number'] ?? '') ?>" placeholder="08XXXXXXXX">
                </div>
                <div>
                    <label class="settings-label">Bank Account Number</label>
                    <input type="text" name="bank_account" class="settings-input"
                        value="<?= htmlspecialchars($settings['bank_account'] ?? '') ?>">
                </div>
                <div>
                    <label class="settings-label">Bank Name</label>
                    <select name="bank_name" class="settings-input">
                        <option value="">-- เลือกธนาคาร --</option>
                        <?php
                        $banks = [
                            'ธนาคารกสิกรไทย (KBANK)',
                            'ธนาคารไทยพาณิชย์ (SCB)',
                            'ธนาคารกรุงเทพ (BBL)',
                            'ธนาคารกรุงไทย (KTB)',
                            'ธนาคารกรุงศรีอยุธยา (BAY)',
                            'ธนาคารทหารไทยธนชาต (TTB)',
                            'ธนาคารออมสิน (GSB)',
                            'ธนาคารเกียรตินาคินภัทร (KKP)',
                            'ธนาคารซีไอเอ็มบีไทย (CIMBT)',
                            'ธนาคารทิสโก้ (TISCO)',
                            'ธนาคารยูโอบี (UOB)',
                            'ธนาคารแลนด์ แอนด์ เฮ้าส์ (LHFG)',
                            'ธนาคารไอซีบีซี (ICBC)',
                            'PromptPay',
                            'TrueMoney Wallet',
                        ];
                        foreach ($banks as $bank):
                            $selected = ($settings['bank_name'] ?? '') === $bank ? 'selected' : '';
                            ?>
                            <option value="<?= htmlspecialchars($bank) ?>" <?= $selected ?>><?= htmlspecialchars($bank) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="settings-label">Account Name</label>
                    <input type="text" name="account_name" class="settings-input"
                        value="<?= htmlspecialchars($settings['account_name'] ?? '') ?>">
                </div>
                <div>
                    <label class="settings-label">Goal Label</label>
                    <input type="text" name="goal_label" class="settings-input"
                        value="<?= htmlspecialchars($settings['goal_label'] ?? '') ?>"
                        placeholder="e.g. ค่าเซิร์ฟเวอร์">
                </div>
                <div>
                    <label class="settings-label">Min Amount (฿)</label>
                    <input type="number" name="min_amount" class="settings-input"
                        value="<?= $settings['min_amount'] ?? 1 ?>" min="1">
                </div>
                <div class="full">
                    <label class="settings-label">Preset Amounts (comma separated)</label>
                    <input type="text" name="preset_amounts" class="settings-input"
                        value="<?= htmlspecialchars($settings['preset_amounts'] ?? '20,50,100,200,500,1000') ?>">
                </div>
                <div class="full">
                    <label class="settings-label">Discord Notification Channel</label>
                    <select name="discord_webhook" class="settings-input">
                        <option value="">-- เลือกช่องแจ้งเตือน --</option>
                        <?php
                        $currentChannel = $settings['discord_webhook'] ?? '';
                        if (is_array($channels)):
                            $lastCat = '';
                            foreach ($channels as $ch):
                                if (($ch['category'] ?? '') !== $lastCat):
                                    if ($lastCat !== '')
                                        echo '</optgroup>';
                                    $lastCat = $ch['category'] ?? 'Uncategorized';
                                    echo '<optgroup label="' . htmlspecialchars($lastCat) . '">';
                                endif;
                                $sel = ($currentChannel === $ch['id']) ? 'selected' : '';
                                ?>
                                <option value="<?= htmlspecialchars($ch['id']) ?>" <?= $sel ?>>
                                    #<?= htmlspecialchars($ch['name']) ?></option>
                                <?php
                            endforeach;
                            if ($lastCat !== '')
                                echo '</optgroup>';
                        endif;
                        ?>
                    </select>
                </div>
                <div class="full">
                    <label class="settings-label">Thank You Message</label>
                    <textarea name="thank_you_message" class="settings-input" rows="2"
                        placeholder="ขอบคุณสำหรับการสนับสนุน!"><?= htmlspecialchars($settings['thank_you_message'] ?? '') ?></textarea>
                </div>
                <div class="full">
                    <div class="section-divider"><i class="fas fa-shield-alt"></i> Thunder API Auto-Verify</div>
                    <p style="color:var(--text-muted); font-size:0.8rem; margin:0 0 14px;">สมัครได้ที่ <a
                            href="https://developer.thunder.in.th" target="_blank"
                            style="color:var(--gold);">developer.thunder.in.th</a> — ตรวจสลิปจริง/ปลอม, สลิปซ้ำ,
                        ยอดเงินตรง อนุมัติอัตโนมัติ</p>
                </div>
                <div class="full">
                    <label class="settings-label">Thunder API Key</label>
                    <input type="password" name="thunder_api_key" class="settings-input"
                        value="<?= htmlspecialchars($settings['thunder_api_key'] ?? '') ?>"
                        placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx">
                </div>
            </div>

            <div
                style="margin-top:24px; padding-top:18px; border-top:1px solid var(--card-border); display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
                <button type="submit" class="btn primary"
                    style="background:linear-gradient(135deg, var(--gold), #d4b76a); color:#000; font-weight:700; border:none; padding:11px 28px; border-radius:12px; font-size:0.93rem; cursor:pointer;"><i
                        class="fas fa-save"></i> Save Settings</button>
                <button type="button"
                    style="background:rgba(88,101,242,0.12); color:#5865F2; border:1px solid rgba(88,101,242,0.2); padding:11px 22px; border-radius:12px; font-size:0.88rem; cursor:pointer; font-weight:600;"
                    onclick="testDiscordNotification()"><i class="fab fa-discord"></i> Test Notification</button>
                <span id="settingsStatus" style="font-weight:500;"></span>
            </div>
        </form>
    </div>
</div>

<!-- Slip Preview Modal -->
<div class="slip-modal-overlay" id="slipModal" onclick="this.classList.remove('active')">
    <img id="slipModalImg" src="" alt="Slip">
</div>

<script>
    const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 2000,
    timerProgressBar: true,
    background: 'rgba(20,20,20,0.95)',
    color: '#fff',
    didOpen: (toast) => {
        toast.addEventListener('mouseenter', Swal.stopTimer);
        toast.addEventListener('mouseleave', Swal.resumeTimer);
    }
});
async function testDiscordNotification() {
        const ch = document.querySelector('select[name="discord_webhook"]').value;
        if (!ch) { document.getElementById('settingsStatus').innerHTML = '<span style="color:#f04747;">เลือกช่องแจ้งเตือนก่อน</span>'; setTimeout(() => document.getElementById('settingsStatus').innerHTML = '', 3000); return; }
        const status = document.getElementById('settingsStatus');
        status.innerHTML = '<i class="fas fa-spinner fa-spin" style="color:#5865F2;"></i> Sending...';
        try {
            const fd = new FormData();
            fd.append('action', 'test_notification');
            fd.append('channel_id', ch);
            const resp = await fetch('approve.php', { method: 'POST', body: fd });
            const data = await resp.json();
            if (data.success) {
                status.innerHTML = '<span style="color:#43b581;"><i class="fas fa-check"></i> Sent!</span>';
            } else {
                status.innerHTML = '<span style="color:#f04747;">' + (data.error || 'Failed') + '</span>';
            }
        } catch (err) {
            status.innerHTML = '<span style="color:#f04747;">' + err.message + '</span>';
        }
        setTimeout(() => status.innerHTML = '', 4000);
    }
    if (typeof Swal !== 'undefined') {
        Object.assign(Toast, Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 2500 }));
    }

    function switchDonateTab(tab, btn) {
        document.querySelectorAll('[id^="donateTab-"]').forEach(el => el.style.display = 'none');
        document.getElementById('donateTab-' + tab).style.display = 'block';
        btn.parentElement.querySelectorAll('.d-tab').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        if (tab === 'donations') filterDonations('pending', document.getElementById('filterPending'));
    }

    let currentFilter = 'pending';

    async function filterDonations(status, btn) {
        currentFilter = status;
        if (btn) {
            btn.parentElement.querySelectorAll('.d-tab').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
        }
        await loadDonations(status);
    }

    async function loadDonations(status) {
        const body = document.getElementById('donationsBody');
        body.innerHTML = '<div class="empty-state"><i class="fas fa-circle-notch fa-spin"></i><p>Loading...</p></div>';

        try {
            const resp = await fetch('approve.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=list_donations&status=' + encodeURIComponent(status)
            });
            const data = await resp.json();

            if (!data.success || !data.donations || data.donations.length === 0) {
                body.innerHTML = '<div class="empty-state"><i class="fas fa-inbox"></i><p>ไม่พบรายการ</p></div>';
                return;
            }

            body.innerHTML = data.donations.map(d => {
                const name = escH(d.donor_name || 'Anonymous');
                const initial = name.charAt(0).toUpperCase();
                const amt = Number(d.amount).toLocaleString();
                const date = d.created_at ? new Date(d.created_at).toLocaleString('th-TH', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '';
                const statusIcon = d.status === 'pending' ? '⏳' : d.status === 'approved' ? '✅' : '❌';

                let verifyBadge = '';
                if (d.verify_status === 'verified') verifyBadge = '<span class="badge badge-auto"><i class="fas fa-shield-alt"></i> Auto</span>';
                else if (d.verify_status === 'amount_mismatch') verifyBadge = '<span class="badge badge-pending"><i class="fas fa-exclamation-triangle"></i> ยอดไม่ตรง</span>';
                else if (d.verify_status === 'invalid') verifyBadge = '<span class="badge badge-rejected"><i class="fas fa-ban"></i> Invalid</span>';

                return `<div class="donation-card ${d.status}-card">
                <div class="dc-avatar ${d.status}">${initial}</div>
                <div class="dc-info">
                    <div class="dc-name">${name} <span class="badge badge-${d.status}">${statusIcon} ${d.status}</span> ${verifyBadge}</div>
                    <div class="dc-meta">
                        <span><i class="fas fa-hashtag"></i> ${d.id}</span>
                        <span><i class="far fa-clock"></i> ${date}</span>
                        ${d.transaction_ref ? `<span><i class="fas fa-link"></i> ${escH(d.transaction_ref).substring(0, 16)}...</span>` : ''}
                    </div>
                    ${d.message ? `<div class="dc-msg">"${escH(d.message)}"</div>` : ''}
                </div>
                <div class="dc-amount">฿${amt}</div>
                ${d.slip_image ? `<img src="/assets/uploads/donate/${escH(d.slip_image)}" class="dc-slip" onclick="viewSlip(this.src)">` : ''}
                <div class="dc-actions">
                    ${d.status === 'pending' ? `
                        <button class="btn-approve" onclick="approveDonation(${d.id})" title="Approve"><i class="fas fa-check"></i></button>
                        <button class="btn-reject" onclick="rejectDonation(${d.id})" title="Reject"><i class="fas fa-times"></i></button>
                    ` : ''}
                    ${d.status === 'approved' ? `<button class="btn-discord" onclick="resendNotification(${d.id})" title="ส่ง Discord อีกครั้ง"><i class="fab fa-discord"></i></button>` : ''}
                    <button class="btn-delete" onclick="deleteDonation(${d.id})" title="Delete"><i class="fas fa-trash"></i></button>
                </div>
            </div>`;
            }).join('');

        } catch (e) {
            body.innerHTML = '<div class="empty-state"><i class="fas fa-exclamation-triangle"></i><p style="color:var(--danger);">Failed to load</p></div>';
        }
    }

    function escH(s) { const d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }

    function viewSlip(src) {
        document.getElementById('slipModalImg').src = src;
        document.getElementById('slipModal').classList.add('active');
    }

    async function approveDonation(id) {
        if (!confirm('Approve this donation?')) return;
        const fd = new FormData();
        fd.append('action', 'approve');
        fd.append('id', id);
        const resp = await fetch('approve.php', { method: 'POST', body: fd });
        const data = await resp.json();
        if (data.success) {
            Toast.fire({ icon: 'success', title: 'Approved!' });
            loadDonations(currentFilter);
        } else {
            Toast.fire({ icon: 'error', title: data.error || 'Failed' });
        }
    }

    async function rejectDonation(id) {
        const note = prompt('Reason for rejection (optional):');
        if (note === null) return;
        const fd = new FormData();
        fd.append('action', 'reject');
        fd.append('id', id);
        fd.append('admin_note', note);
        const resp = await fetch('approve.php', { method: 'POST', body: fd });
        const data = await resp.json();
        if (data.success) {
            Toast.fire({ icon: 'success', title: 'Rejected' });
            loadDonations(currentFilter);
        }
    }

    async function resendNotification(id) {
        const fd = new FormData();
        fd.append('action', 'resend_notification');
        fd.append('id', id);
        try {
            const resp = await fetch('approve.php', { method: 'POST', body: fd });
            const data = await resp.json();
            if (data.success) {
                Toast.fire({ icon: 'success', title: 'ส่งแจ้งเตือน Discord แล้ว!' });
            } else {
                Toast.fire({ icon: 'error', title: data.error || 'Failed' });
            }
        } catch (err) {
            Toast.fire({ icon: 'error', title: err.message });
        }
    }

    async function deleteDonation(id) {
        if (!confirm('Delete this donation permanently?')) return;
        const fd = new FormData();
        fd.append('action', 'delete');
        fd.append('id', id);
        const resp = await fetch('approve.php', { method: 'POST', body: fd });
        const data = await resp.json();
        if (data.success) {
            Toast.fire({ icon: 'success', title: 'Deleted' });
            loadDonations(currentFilter);
        }
    }

    async function saveDonateSettings(e) {
        e.preventDefault();
        const form = document.getElementById('donateSettingsForm');
        const fd = new FormData(form);
        fd.append('action', 'save_settings');

        const status = document.getElementById('settingsStatus');
        status.innerHTML = '<i class="fas fa-spinner fa-spin" style="color:#5865F2;"></i>';

        try {
            const resp = await fetch('approve.php', { method: 'POST', body: fd });
            const data = await resp.json();
            if (data.success) {
                status.innerHTML = '<span style="color:#43b581;"><i class="fas fa-check"></i> Saved!</span>';
                Toast.fire({ icon: 'success', title: 'Settings saved!' });
            } else {
                status.innerHTML = '<span style="color:#f04747;">' + (data.error || 'Failed') + '</span>';
            }
        } catch (err) {
            status.innerHTML = '<span style="color:#f04747;">' + err.message + '</span>';
        }
        setTimeout(() => status.innerHTML = '', 3000);
    }

    // Initial load
    document.addEventListener('DOMContentLoaded', () => {
        loadDonations('pending');
    });
</script>

</div>
</body>

</html>

