<?php
// Resume Modal - Initialize resume data
// Determine base path prefix for assets (handles both root and admin contexts)
$resumeBasePath = '/';
$resume = isset($_SESSION['user']['resume_data']) ? json_decode($_SESSION['user']['resume_data'], true) : [];

// Default values (empty for new users)
$r_header_l1 = $resume['header_l1'] ?? '';
$r_header_l2 = $resume['header_l2'] ?? '';
$r_header_l3 = $resume['header_l3'] ?? '';
$r_header_l4 = $resume['header_l4'] ?? '';
$r_header_logo_left = $resume['header_logo_left'] ?? 'unit_1.png';
$r_header_logo_right = $resume['header_logo_right'] ?? 'unit_1.png';

$r_subject = $resume['subject'] ?? '';
$r_remarks = $resume['remarks'] ?? '';
$r_asoc_id = $resume['asoc_id'] ?? ($_SESSION['user']['steamid'] ?? '');
$r_rank = $resume['rank'] ?? '';
$r_name = $resume['name'] ?? strtoupper($_SESSION['user']['personaname'] ?? '');
$r_dob = $resume['dob'] ?? '';
$r_age = $resume['age'] ?? '';
$r_unit = $resume['unit'] ?? '';
$r_position = $resume['position'] ?? '';
$r_mos = $resume['mos'] ?? '';
$r_vmet = $resume['vmet'] ?? '';
$r_background = $resume['background'] ?? '';
$r_sig_style = $resume['sig_style'] ?? '1';
$r_sig_name = $resume['sig_name'] ?? '';
$r_sig_rank = $resume['sig_rank'] ?? '';
$r_sig_title = $resume['sig_title'] ?? '';

// Fetch Units from DB
$available_units = [];
if (isset($pdo)) {
    try {
        $available_units = $pdo->query("SELECT * FROM resume_units ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Fallback or empty
    }
}
?>

<!-- League Spartan Font (Resume Only) -->
<link
    href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@400;500;600;700;800&family=Caveat:wght@400;500;600;700&family=Sacramento&display=swap"
    rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

<!-- Resume Modal -->
<div id="resumeModal" class="modal-overlay">
    <div id="resumeZoomWrapper"
        style="width: 100%; height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: flex-start; padding-top: 20px; overflow: hidden; box-sizing: border-box;">
        <div class="modal-content resume-document" id="resumeDocumentEl"
            style="background: #fff; padding: 0; border-radius: 4px; max-width: 800px; width: 95%; position: relative; box-shadow: 0 0 40px rgba(0,0,0,0.6); color: #000; font-family: 'League Spartan', sans-serif; max-height: none; overflow: hidden;">

            <!-- View Mode -->
            <div id="resumeView" style="font-weight: 700;">
                <!-- Header Bar -->
                <div
                    style="background: #fff; color: #000; padding: 15px 25px; display: flex; align-items: center; gap: 15px; border-bottom: 2px solid #000;">
                    <div style="width: 85px; flex-shrink: 0; text-align: center;">
                        <img id="view_header_logo_left"
                            src="<?php echo $resumeBasePath; ?>assets/images/units/<?php echo htmlspecialchars($r_header_logo_left); ?>"
                            alt="Unit Logo" style="width: 75px; height: 75px; object-fit: contain;">
                    </div>
                    <div style="flex: 1; text-align: center;">
                        <div id="view_header_l1"
                            style="font-size: 0.95rem; font-weight: bold; text-transform: uppercase; letter-spacing: 1.5px;">
                            <?php echo htmlspecialchars($r_header_l1); ?>
                        </div>
                        <div id="view_header_l2"
                            style="font-size: 0.85rem; font-weight: bold; text-transform: uppercase; margin-top: 2px;">
                            <?php echo htmlspecialchars($r_header_l2); ?>
                        </div>
                        <div id="view_header_l3"
                            style="font-size: 0.85rem; font-weight: bold; text-transform: uppercase; margin-top: 2px;">
                            <?php echo htmlspecialchars($r_header_l3); ?>
                        </div>
                        <div id="view_header_l4" style="font-size: 0.8rem; text-transform: uppercase; margin-top: 2px;">
                            <?php echo htmlspecialchars($r_header_l4); ?>
                        </div>
                    </div>
                    <div style="width: 85px; flex-shrink: 0; text-align: center;">
                        <img id="view_header_logo_right"
                            src="<?php echo $resumeBasePath; ?>assets/images/units/<?php echo htmlspecialchars($r_header_logo_right); ?>"
                            alt="Unit Logo" style="width: 75px; height: 75px; object-fit: contain;">
                    </div>
                </div>

                <!-- Document Body -->
                <div style="padding: 28px 40px; position: relative; overflow: hidden;">
                    <!-- Watermark -->
                    <div
                        style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-35deg); font-size: 10rem; font-weight: 800; color: rgba(0,0,0,0.07); pointer-events: none; white-space: nowrap; letter-spacing: 16px; z-index: 0; font-family: 'League Spartan', sans-serif;">
                        USASOC
                    </div>

                    <!-- Reference & Date -->
                    <div
                        style="display: flex; justify-content: space-between; margin-bottom: 22px; font-size: 0.9rem; font-weight: bold; position: relative; z-index: 1;">
                        <div id="view_doc_ref"
                            style="font-family: 'League Spartan', sans-serif; font-size: 0.88rem; letter-spacing: 0.5px; font-weight: 800;">
                        </div>
                        <div style="text-transform: uppercase;"><?php echo strtoupper(date('d F Y')); ?></div>
                    </div>

                    <!-- Subject & Remarks -->
                    <div
                        style="margin-bottom: 10px; font-size: 0.95rem; font-weight: bold; position: relative; z-index: 1;">
                        SUBJECT: <span style="font-weight: bold;">PERSONNEL INFORMATION</span>
                    </div>
                    <div
                        style="margin-bottom: 25px; font-size: 0.95rem; font-weight: bold; position: relative; z-index: 1;">
                        REMARKS: <span id="view_remarks"><?php echo htmlspecialchars($r_remarks); ?></span>
                    </div>

                    <!-- Main Content: Photo + Details -->
                    <div style="display: flex; gap: 25px; margin-bottom: 28px; position: relative; z-index: 1;">
                        <!-- Photo -->
                        <div style="width: 160px; flex-shrink: 0;">
                            <div style="border: 2px solid #000; padding: 3px; background: #f0f0f0;">
                                <img id="view_avatar" src="<?php echo get_avatar($_SESSION['user']['avatar']); ?>"
                                    alt="Photo"
                                    style="width: 100%; height: auto; display: block; aspect-ratio: 3/4; object-fit: cover;">
                            </div>
                        </div>

                        <!-- Details Grid -->
                        <div style="flex: 1; font-size: 0.95rem; line-height: 2; font-weight: 700;">
                            <div><span style="margin-right: 8px;">ASOC ID:</span><span
                                    id="view_asoc_id"><?php echo htmlspecialchars($r_asoc_id); ?></span></div>
                            <div><span style="margin-right: 8px;">RANK/PAYGRADE:</span><span
                                    id="view_rank"><?php echo htmlspecialchars($r_rank); ?></span></div>
                            <div><span style="margin-right: 8px;">NAME :</span><span
                                    id="view_name"><?php echo htmlspecialchars($r_name); ?></span></div>
                            <div><span style="margin-right: 8px;">DOB :</span><span
                                    id="view_dob"><?php echo htmlspecialchars($r_dob); ?></span></div>
                            <div><span style="margin-right: 8px;">AGE:</span><span
                                    id="view_age"><?php echo htmlspecialchars($r_age); ?></span></div>
                            <div><span style="margin-right: 8px;">UNIT :</span><span
                                    id="view_unit"><?php echo htmlspecialchars($r_unit); ?></span></div>
                            <div><span style="margin-right: 8px;">POSITION :</span><span
                                    id="view_position"><?php echo htmlspecialchars($r_position); ?></span></div>
                            <div><span style="margin-right: 8px;">MOS :</span><span
                                    id="view_mos"><?php echo htmlspecialchars($r_mos); ?></span></div>
                        </div>
                    </div>

                    <!-- VMET Section -->
                    <div style="margin-bottom: 28px; position: relative; z-index: 1;">
                        <div
                            style="font-weight: bold; font-size: 0.95rem; border-bottom: 2px solid #000; padding-bottom: 5px; margin-bottom: 12px; text-transform: uppercase;">
                            Verification of Military Experience and Trainings [VMET]
                        </div>
                        <div id="view_vmet" class="resume-vmet-list"
                            style="font-size: 0.92rem; padding-left: 5px; font-weight: 700;">
                            <?php echo htmlspecialchars($r_vmet); ?>
                        </div>
                    </div>

                    <!-- Background Section -->
                    <div style="margin-bottom: 20px; position: relative; z-index: 1;">
                        <div
                            style="font-weight: bold; font-size: 0.95rem; border-bottom: 2px solid #000; padding-bottom: 5px; margin-bottom: 12px;">
                            BACKGROUND
                        </div>
                        <p id="view_background"
                            style="font-size: 0.92rem; text-align: justify; line-height: 1.3; text-indent: 0; white-space: pre-line; margin: 0; font-weight: 700; word-wrap: break-word; overflow-wrap: break-word;">
                            <?php echo htmlspecialchars($r_background); ?>
                        </p>
                    </div>

                    <!-- Signature Block -->
                    <div
                        style="margin-top: 20px; display: flex; justify-content: flex-end; position: relative; z-index: 1; padding-right: 30px; padding-bottom: 20px;">
                        <div style="text-align: center;">
                            <div id="view_sig_name" style="font-size: 2.2rem; color: #000; margin-bottom: 2px;"></div>
                            <div id="view_sig_fullname"
                                style="font-weight: bold; font-size: 0.92rem; text-transform: uppercase;"></div>
                            <div id="view_sig_rank"
                                style="font-weight: bold; font-size: 0.92rem; text-transform: uppercase;"></div>
                            <div id="view_sig_title"
                                style="font-weight: bold; font-size: 0.92rem; text-transform: uppercase;"></div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Edit Mode (Dark Theme) -->
            <div id="resumeEdit"
                style="display: none; background: rgba(20, 20, 24, 0.98); color: #f0f0f0; font-family: 'Inter', sans-serif; max-height: 90vh; overflow-y: auto;">
                <!-- Edit Header -->
                <div
                    style="background: linear-gradient(135deg, rgba(197,160,89,0.15), rgba(197,160,89,0.05)); padding: 18px 28px; border-bottom: 1px solid rgba(197,160,89,0.2); display: flex; justify-content: space-between; align-items: center;">
                    <h3 style="margin: 0; font-size: 1.1rem; color: #c5a059; font-weight: 600; letter-spacing: 0.5px;">
                        <i class="fas fa-edit" style="margin-right: 8px;"></i>Edit Resume
                    </h3>
                    <button type="button" onclick="toggleResumeEdit()"
                        style="background: none; border: none; color: #888; font-size: 1.3rem; cursor: pointer; padding: 0; line-height: 1;">&times;</button>
                </div>

                <form id="resumeForm" onsubmit="saveResume(event)" style="padding: 24px 28px;">
                    <input type="hidden" name="target_user_id" id="target_user_id" value="">

                    <!-- Section: Header Information -->
                    <div style="margin-bottom: 24px;">
                        <div
                            style="font-size: 0.78rem; font-weight: 600; color: #c5a059; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 14px; padding-bottom: 8px; border-bottom: 1px solid rgba(255,255,255,0.08);">
                            <i class="fas fa-heading" style="margin-right: 6px;"></i>Header Information
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                            <div>
                                <label class="resume-edit-label">Left Logo</label>
                                <select name="header_logo_left" id="edit_header_logo_left" class="resume-edit-input">
                                    <?php foreach ($available_units as $unit): ?>
                                        <option value="<?php echo htmlspecialchars($unit['image_path']); ?>" <?php echo ($r_header_logo_left == $unit['image_path']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($unit['unit_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="resume-edit-label">Right Logo</label>
                                <select name="header_logo_right" id="edit_header_logo_right" class="resume-edit-input">
                                    <?php foreach ($available_units as $unit): ?>
                                        <option value="<?php echo htmlspecialchars($unit['image_path']); ?>" <?php echo ($r_header_logo_right == $unit['image_path']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($unit['unit_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div style="margin-bottom: 10px;">
                            <label class="resume-edit-label">Line 1 (Command)</label>
                            <input type="text" name="header_l1" id="edit_header_l1"
                                value="<?php echo htmlspecialchars($r_header_l1); ?>" class="resume-edit-input"
                                placeholder="e.g. UNITED STATES ARMY SPECIAL OPERATIONS COMMAND">
                        </div>
                        <div style="margin-bottom: 10px;">
                            <label class="resume-edit-label">Line 2 (Unit)</label>
                            <input type="text" name="header_l2" id="edit_header_l2"
                                value="<?php echo htmlspecialchars($r_header_l2); ?>" class="resume-edit-input"
                                placeholder="e.g. 2ND BRAVO COMPANY, 4TH BATTALION">
                        </div>
                        <div style="margin-bottom: 10px;">
                            <label class="resume-edit-label">Line 3 (Parent Command)</label>
                            <input type="text" name="header_l3" id="edit_header_l3"
                                value="<?php echo htmlspecialchars($r_header_l3); ?>" class="resume-edit-input"
                                placeholder="e.g. 1ST SPECIAL FORCES COMMAND">
                        </div>
                        <div>
                            <label class="resume-edit-label">Line 4 (Location)</label>
                            <input type="text" name="header_l4" id="edit_header_l4"
                                value="<?php echo htmlspecialchars($r_header_l4); ?>" class="resume-edit-input"
                                placeholder="e.g. FORT CARSON, COLORADO 80902-71900">
                        </div>
                    </div>

                    <!-- Section: Personal Details -->
                    <div style="margin-bottom: 24px;">
                        <div
                            style="font-size: 0.78rem; font-weight: 600; color: #c5a059; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 14px; padding-bottom: 8px; border-bottom: 1px solid rgba(255,255,255,0.08);">
                            <i class="fas fa-user-tag" style="margin-right: 6px;"></i>Personal Details
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                            <div>
                                <label class="resume-edit-label">SUBJECT</label>
                                <input type="text" name="subject" id="edit_subject"
                                    value="<?php echo htmlspecialchars($r_subject); ?>" class="resume-edit-input"
                                    placeholder="PERSONNEL INFORMATION">
                            </div>
                            <div>
                                <label class="resume-edit-label">REMARKS</label>
                                <input type="text" name="remarks" id="edit_remarks"
                                    value="<?php echo htmlspecialchars($r_remarks); ?>" class="resume-edit-input"
                                    placeholder="SSG. JOHN DOE INFORMATION">
                            </div>
                            <div>
                                <label class="resume-edit-label">ASOC ID</label>
                                <input type="text" name="asoc_id" id="edit_asoc_id"
                                    value="<?php echo htmlspecialchars($r_asoc_id); ?>" class="resume-edit-input">
                            </div>
                            <div>
                                <label class="resume-edit-label">RANK / PAYGRADE</label>
                                <input type="text" name="rank" id="edit_rank"
                                    value="<?php echo htmlspecialchars($r_rank); ?>" class="resume-edit-input"
                                    placeholder="e.g. STAFF SERGEANT/E6">
                            </div>
                            <div>
                                <label class="resume-edit-label">NAME</label>
                                <input type="text" name="name" id="edit_name"
                                    value="<?php echo htmlspecialchars($r_name); ?>" class="resume-edit-input">
                            </div>
                            <div>
                                <label class="resume-edit-label">DOB</label>
                                <input type="text" name="dob" id="edit_dob"
                                    value="<?php echo htmlspecialchars($r_dob); ?>" class="resume-edit-input"
                                    placeholder="e.g. FAYETTEVILLE, NORTH CAROLINA">
                            </div>
                            <div>
                                <label class="resume-edit-label">AGE</label>
                                <input type="text" name="age" id="edit_age"
                                    value="<?php echo htmlspecialchars($r_age); ?>" class="resume-edit-input"
                                    placeholder="e.g. 26">
                            </div>
                            <div>
                                <label class="resume-edit-label">UNIT</label>
                                <input type="text" name="unit" id="edit_unit"
                                    value="<?php echo htmlspecialchars($r_unit); ?>" class="resume-edit-input"
                                    placeholder="e.g. 2ND BRAVO CO, 4ND BN, 12TH SFG">
                            </div>
                            <div>
                                <label class="resume-edit-label">POSITION</label>
                                <input type="text" name="position" id="edit_position"
                                    value="<?php echo htmlspecialchars($r_position); ?>" class="resume-edit-input"
                                    placeholder="e.g. COMMUNICATIONS SERGEANT">
                            </div>
                            <div>
                                <label class="resume-edit-label">MOS</label>
                                <input type="text" name="mos" id="edit_mos"
                                    value="<?php echo htmlspecialchars($r_mos); ?>" class="resume-edit-input"
                                    placeholder="e.g. 18E">
                            </div>
                        </div>
                    </div>

                    <!-- Section: VMET & Background -->
                    <div style="margin-bottom: 24px;">
                        <div
                            style="font-size: 0.78rem; font-weight: 600; color: #c5a059; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 14px; padding-bottom: 8px; border-bottom: 1px solid rgba(255,255,255,0.08);">
                            <i class="fas fa-certificate" style="margin-right: 6px;"></i>Experience & Background
                        </div>

                        <div style="margin-bottom: 14px;">
                            <label class="resume-edit-label">VMET (One per line)</label>
                            <textarea name="vmet" id="edit_vmet" rows="5" class="resume-edit-input"
                                style="resize: vertical;"
                                placeholder="UNITED STATES ARMY INFANTRY SCHOOL&#10;UNITED STATES ARMY SIGNAL SCHOOL&#10;UNITED STATES ARMY AIRBORNE SCHOOL"><?php echo htmlspecialchars($r_vmet); ?></textarea>
                        </div>

                        <div>
                            <label class="resume-edit-label">BACKGROUND</label>
                            <textarea name="background" id="edit_background" rows="6" class="resume-edit-input"
                                style="resize: vertical;"
                                placeholder="Write the member's background story..."><?php echo htmlspecialchars($r_background); ?></textarea>
                        </div>
                    </div>

                    <!-- Section: Signature -->
                    <div style="margin-bottom: 24px;">
                        <div
                            style="font-size: 0.78rem; font-weight: 600; color: #c5a059; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 14px; padding-bottom: 8px; border-bottom: 1px solid rgba(255,255,255,0.08);">
                            <i class="fas fa-signature" style="margin-right: 6px;"></i>Signature
                        </div>

                        <div style="margin-bottom: 14px;">
                            <label class="resume-edit-label">Signature Style</label>
                            <div style="display: flex; gap: 12px;">
                                <label style="flex: 1; cursor: pointer;">
                                    <input type="radio" name="sig_style" value="1" id="edit_sig_style_1" <?php echo ($r_sig_style == '1') ? 'checked' : ''; ?> style="display: none;"
                                        onchange="updateSigPreview()">
                                    <div class="sig-style-option" data-style="1"
                                        style="border: 2px solid rgba(255,255,255,0.1); border-radius: 8px; padding: 14px; text-align: center; transition: all 0.3s;">
                                        <div
                                            style="font-family: 'Caveat', cursive; font-size: 1.6rem; color: #f0f0f0; margin-bottom: 4px;">
                                            Signature</div>
                                        <div style="font-size: 0.7rem; color: #888;">Style 1 - Casual</div>
                                    </div>
                                </label>
                                <label style="flex: 1; cursor: pointer;">
                                    <input type="radio" name="sig_style" value="2" id="edit_sig_style_2" <?php echo ($r_sig_style == '2') ? 'checked' : ''; ?> style="display: none;"
                                        onchange="updateSigPreview()">
                                    <div class="sig-style-option" data-style="2"
                                        style="border: 2px solid rgba(255,255,255,0.1); border-radius: 8px; padding: 14px; text-align: center; transition: all 0.3s;">
                                        <div
                                            style="font-family: 'Sacramento', cursive; font-size: 1.6rem; color: #f0f0f0; margin-bottom: 4px;">
                                            Signature</div>
                                        <div style="font-size: 0.7rem; color: #888;">Style 2 - Formal</div>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                            <div style="grid-column: 1 / -1;">
                                <label class="resume-edit-label">Signer Name</label>
                                <input type="text" name="sig_name" id="edit_sig_name"
                                    value="<?php echo htmlspecialchars($r_sig_name); ?>" class="resume-edit-input"
                                    placeholder="e.g. AIDEN ANDERSON">
                            </div>
                            <div>
                                <label class="resume-edit-label">Signer Rank</label>
                                <input type="text" name="sig_rank" id="edit_sig_rank"
                                    value="<?php echo htmlspecialchars($r_sig_rank); ?>" class="resume-edit-input"
                                    placeholder="e.g. LIEUTENANT COLONEL">
                            </div>
                            <div>
                                <label class="resume-edit-label">Signer Title</label>
                                <input type="text" name="sig_title" id="edit_sig_title"
                                    value="<?php echo htmlspecialchars($r_sig_title); ?>" class="resume-edit-input"
                                    placeholder="e.g. BATTALION COMMANDING OFFICER">
                            </div>
                        </div>
                    </div>

                </form>
            </div>
        </div>
        <div style="display: flex; gap: 10px; margin-top: 12px;">
            <button id="btnEditResume" onclick="toggleResumeEdit()" class="btn"
                style="background: #1a1a1e; color: #c5a059; padding: 10px 28px; border: 1px solid #c5a059; cursor: pointer; font-weight: 600; text-transform: uppercase; font-size: 0.85rem; border-radius: 4px; letter-spacing: 1px; transition: all 0.3s;">
                <i class="fas fa-edit"></i> Edit Resume
            </button>
            <button id="btnExportResume" onclick="exportResume()" class="btn"
                style="background: #1a1a1e; color: #4caf50; padding: 10px 28px; border: 1px solid #4caf50; cursor: pointer; font-weight: 600; text-transform: uppercase; font-size: 0.85rem; border-radius: 4px; letter-spacing: 1px; transition: all 0.3s;">
                <i class="fas fa-download"></i> Export
            </button>
            <button id="btnSaveResume" onclick="document.getElementById('resumeForm').requestSubmit()" class="btn"
                style="display: none; background: linear-gradient(135deg, #c5a059, #b8944d); color: #0a0a0c; padding: 10px 28px; border: none; cursor: pointer; font-weight: 600; text-transform: uppercase; font-size: 0.85rem; border-radius: 4px; letter-spacing: 1px; transition: all 0.3s;">
                <i class="fas fa-save"></i> Save Changes
            </button>
            <button id="btnCancelEdit" onclick="toggleResumeEdit()" class="btn"
                style="display: none; background: #1a1a1e; color: #888; padding: 10px 28px; border: 1px solid rgba(255,255,255,0.15); cursor: pointer; font-weight: 600; text-transform: uppercase; font-size: 0.85rem; border-radius: 4px; letter-spacing: 1px; transition: all 0.3s;">
                Cancel
            </button>
        </div>
    </div>
</div>

<!-- Resume Edit Form Styles -->
<style>
    .resume-edit-label {
        display: block;
        font-size: 0.72rem;
        font-weight: 600;
        color: #999;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 5px;
    }

    .resume-edit-input {
        width: 100%;
        padding: 10px 14px;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 6px;
        color: #f0f0f0;
        font-family: 'Inter', sans-serif;
        font-size: 0.85rem;
        transition: all 0.25s ease;
        box-sizing: border-box;
    }

    .resume-edit-input:focus {
        outline: none;
        border-color: #c5a059;
        background: rgba(255, 255, 255, 0.08);
        box-shadow: 0 0 0 3px rgba(197, 160, 89, 0.1);
    }

    .resume-edit-input::placeholder {
        color: rgba(255, 255, 255, 0.2);
        font-size: 0.8rem;
    }

    .resume-edit-input option {
        background: #1a1a1e;
        color: #f0f0f0;
    }

    .sig-style-option {
        border: 2px solid rgba(255, 255, 255, 0.1) !important;
    }

    input[type="radio"]:checked+.sig-style-option {
        border-color: #c5a059 !important;
        background: rgba(197, 160, 89, 0.1);
    }

    .modal-content.resume-document {
        max-height: none !important;
        overflow: visible !important;
        overflow-y: visible !important;
    }
</style>

<!-- Success Modal -->
<div id="successModal" class="modal-overlay">
    <div class="glass-panel modal-content"
        style="max-width: 400px; text-align: center; border: 1px solid var(--accent-color);">
        <div
            style="width: 60px; height: 60px; background: rgba(76, 175, 80, 0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
            <i class="fas fa-check" style="font-size: 30px; color: #4caf50;"></i>
        </div>
        <h3 style="color: #fff; margin: 0 0 10px; font-family: 'Inter', sans-serif; font-size: 1.5rem;">Success!</h3>
        <p style="color: #aaa; margin: 0; font-family: 'Inter', sans-serif;">Resume updated successfully.</p>
    </div>
</div>

<script>
    const currentUserId = <?php echo isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 'null'; ?>;
    const isAdmin = <?php echo isAdmin() ? 'true' : 'false'; ?>;
    const resumeBasePath = '<?php echo $resumeBasePath; ?>';

    function openResume() {
        // Open current user's resume
        loadResumeData(currentUserId);
    }
    window.openResume = openResume;

    function closeResume() {
        const modal = document.getElementById('resumeModal');
        modal.classList.remove('show');
        setTimeout(() => {
            modal.style.display = 'none';
        }, 300);

        // Reset to view mode when closing
        setTimeout(() => {
            document.getElementById('resumeView').style.display = 'block';
            document.getElementById('resumeEdit').style.display = 'none';
            // Reset buttons to view mode
            var btnEdit = document.getElementById('btnEditResume');
            var btnExport = document.getElementById('btnExportResume');
            var btnSave = document.getElementById('btnSaveResume');
            var btnCancel = document.getElementById('btnCancelEdit');
            if (btnEdit) btnEdit.innerHTML = '<i class="fas fa-edit"></i> Edit Resume';
            if (btnExport) btnExport.style.display = 'inline-block';
            if (btnSave) btnSave.style.display = 'none';
            if (btnCancel) btnCancel.style.display = 'none';
        }, 300);
    }

    function toggleResumeEdit() {
        var view = document.getElementById('resumeView');
        var edit = document.getElementById('resumeEdit');
        var doc = document.getElementById('resumeDocumentEl');
        var btnEdit = document.getElementById('btnEditResume');
        var btnExport = document.getElementById('btnExportResume');
        var btnSave = document.getElementById('btnSaveResume');
        var btnCancel = document.getElementById('btnCancelEdit');
        if (view.style.display !== 'none') {
            view.style.display = 'none';
            edit.style.display = 'block';
            // Switch buttons: show View Resume + Save + Cancel, hide Export
            if (btnEdit) { btnEdit.innerHTML = '<i class="fas fa-eye"></i> View Resume'; }
            if (btnExport) btnExport.style.display = 'none';
            if (btnSave) btnSave.style.display = 'inline-block';
            if (btnCancel) btnCancel.style.display = 'inline-block';
            // Reset zoom and allow scrolling for edit mode
            if (doc) {
                doc.style.zoom = '1';
                doc.style.transform = 'none';
                doc.style.marginBottom = '0px';
                doc.style.maxHeight = '90vh';
                doc.style.overflowY = 'auto';
            }
        } else {
            view.style.display = 'block';
            edit.style.display = 'none';
            // Switch buttons: show Edit Resume + Export, hide Save + Cancel
            if (btnEdit) { btnEdit.innerHTML = '<i class="fas fa-edit"></i> Edit Resume'; }
            if (btnExport) btnExport.style.display = 'inline-block';
            if (btnSave) btnSave.style.display = 'none';
            if (btnCancel) btnCancel.style.display = 'none';
            // View mode: scale to fit
            if (doc) {
                doc.style.maxHeight = 'none';
                doc.style.overflowY = '';
            }
            setTimeout(fitResumeToViewport, 50);
        }
    }

    function loadResumeData(userId, forceEdit = false) {
        // Determine the correct path to update_resume.php based on current location
        const basePath = window.location.pathname.includes('/admin/') ? '../' : '';

        fetch(basePath + 'update_resume?user_id=' + userId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const r = data.resume_data || {};

                    const fields = ['header_l1', 'header_l2', 'header_l3', 'header_l4', 'subject', 'remarks', 'asoc_id', 'rank', 'name', 'dob', 'age', 'unit', 'position', 'mos', 'background'];
                    fields.forEach(field => {
                        const el = document.getElementById('view_' + field);
                        if (el) el.innerText = r[field] || '';

                        const input = document.getElementById('edit_' + field);
                        if (input) input.value = r[field] || '';
                    });

                    // Render VMET as bullet list
                    const vmetView = document.getElementById('view_vmet');
                    const vmetEdit = document.getElementById('edit_vmet');
                    if (vmetView) {
                        const vmetText = r.vmet || '';
                        const lines = vmetText.split('\n').filter(l => l.trim() !== '');
                        if (lines.length > 0) {
                            vmetView.innerHTML = '<ul style="list-style: disc; padding-left: 20px; margin: 0;">' +
                                lines.map(l => '<li style="margin-bottom: 3px; text-transform: uppercase;">' + l.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</li>').join('') +
                                '</ul>';
                        } else {
                            vmetView.innerHTML = '';
                        }
                    }
                    if (vmetEdit) vmetEdit.value = r.vmet || '';

                    // Generate document reference number
                    const docRef = document.getElementById('view_doc_ref');
                    if (docRef && r.asoc_id) {
                        const hash = r.asoc_id.substring(0, 4).toUpperCase() + '-' +
                            Math.abs((r.asoc_id.split('').reduce((a, c) => a + c.charCodeAt(0), 0)) % 100).toString().padStart(2, '0') +
                            'C-' + String.fromCharCode(65 + Math.floor(Math.random() * 26)) +
                            String.fromCharCode(65 + Math.floor(Math.random() * 26)) +
                            String.fromCharCode(65 + Math.floor(Math.random() * 26));
                        docRef.textContent = hash;
                    }

                    // Update Logos
                    if (r.header_logo_left) {
                        const imgLeft = document.getElementById('view_header_logo_left');
                        if (imgLeft) imgLeft.src = resumeBasePath + 'assets/images/units/' + r.header_logo_left;
                        const selLeft = document.getElementById('edit_header_logo_left');
                        if (selLeft) selLeft.value = r.header_logo_left;
                    }
                    if (r.header_logo_right) {
                        const imgRight = document.getElementById('view_header_logo_right');
                        if (imgRight) imgRight.src = resumeBasePath + 'assets/images/units/' + r.header_logo_right;
                        const selRight = document.getElementById('edit_header_logo_right');
                        if (selRight) selRight.value = r.header_logo_right;
                    }

                    // Update Avatar
                    if (data.avatar) {
                        const avatarEl = document.getElementById('view_avatar');
                        if (avatarEl) avatarEl.src = data.avatar;
                    }

                    // Update Signature
                    const sigStyle = r.sig_style || '1';
                    const sigName = r.sig_name || '';
                    const sigRank = r.sig_rank || '';
                    const sigTitle = r.sig_title || '';
                    const sigFonts = { '1': "'Caveat', cursive", '2': "'Sacramento', cursive" };
                    const viewSigName = document.getElementById('view_sig_name');
                    const viewSigFullname = document.getElementById('view_sig_fullname');
                    const viewSigRank = document.getElementById('view_sig_rank');
                    const viewSigTitle = document.getElementById('view_sig_title');
                    if (viewSigName) {
                        viewSigName.style.fontFamily = sigFonts[sigStyle] || sigFonts['1'];
                        viewSigName.textContent = sigName ? sigName.split(' ')[0] : '';
                    }
                    if (viewSigFullname) viewSigFullname.textContent = sigName;
                    if (viewSigRank) viewSigRank.textContent = sigRank;
                    if (viewSigTitle) viewSigTitle.textContent = sigTitle;

                    // Set edit signature fields
                    const editSigName = document.getElementById('edit_sig_name');
                    const editSigRank = document.getElementById('edit_sig_rank');
                    const editSigTitle = document.getElementById('edit_sig_title');
                    if (editSigName) editSigName.value = sigName;
                    if (editSigRank) editSigRank.value = sigRank;
                    if (editSigTitle) editSigTitle.value = sigTitle;
                    const sigRadio = document.getElementById('edit_sig_style_' + sigStyle);
                    if (sigRadio) sigRadio.checked = true;

                    // Set target user ID
                    document.getElementById('target_user_id').value = userId;

                    // Permission Check for Edit Button
                    const btnEdit = document.getElementById('btnEditResume');
                    if (userId == currentUserId || isAdmin) {
                        if (btnEdit) btnEdit.style.display = 'inline-block';
                    } else {
                        if (btnEdit) btnEdit.style.display = 'none';
                    }

                    // Open modal
                    const modal = document.getElementById('resumeModal');
                    modal.style.display = 'flex';
                    // Small delay for CSS transition
                    setTimeout(() => {
                        modal.classList.add('show');
                        // Auto-fit to viewport
                        setTimeout(fitResumeToViewport, 50);
                    }, 10);

                    if (forceEdit && (userId == currentUserId || isAdmin)) {
                        document.getElementById('resumeView').style.display = 'none';
                        document.getElementById('resumeEdit').style.display = 'block';
                    } else {
                        document.getElementById('resumeView').style.display = 'block';
                        document.getElementById('resumeEdit').style.display = 'none';
                    }

                } else {
                    alert('Error loading resume data: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                if (typeof Swal !== 'undefined') { Swal.fire({ icon: 'error', title: 'Error', text: 'Failed to load resume data', toast: true, position: 'top-end', timer: 3000, showConfirmButton: false, background: 'rgba(20,20,20,0.95)', color: '#fff' }); } else { console.error('Failed to load resume data', error); }
            });
    }

    function saveResume(e) {
        e.preventDefault();
        var form = document.getElementById('resumeForm');
        var formData = new FormData(form);

        // Determine the correct path
        const basePath = window.location.pathname.includes('/admin/') ? '../' : '';

        fetch(basePath + 'update_resume', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show custom success modal
                    const successModal = document.getElementById('successModal');
                    successModal.style.display = 'flex';
                    setTimeout(() => {
                        successModal.classList.add('show');
                    }, 10);

                    setTimeout(() => {
                        location.reload(); // Reload to see changes
                    }, 1500);
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while saving.');
            });
    }

    function openAdminResume(userId) {
        loadResumeData(userId, true);
    }

    // Zoom functionality (uses CSS zoom for layout-correct scaling)
    let resumeZoom = 1;
    const ZOOM_MIN = 0.2;
    const ZOOM_MAX = 2.0;
    const ZOOM_STEP = 0.05;

    function fitResumeToViewport() {
        const doc = document.getElementById('resumeDocumentEl');
        const wrapper = document.getElementById('resumeZoomWrapper');
        if (!doc || !wrapper || doc.closest('#resumeModal').style.display === 'none') return;
        // Reset pan position
        resumePanX = 0;
        resumePanY = 0;
        // Reset to measure true height
        doc.style.zoom = '1';
        doc.style.transform = 'none';
        doc.style.marginBottom = '0';
        doc.style.overflow = 'visible';
        doc.style.maxHeight = 'none';
        // Force reflow so browser recalculates layout
        void doc.offsetHeight;
        const docH = doc.scrollHeight;
        // Use wrapper height minus padding-top(20) and button bar(~80)
        const available = wrapper.clientHeight - 100;
        if (docH > available) {
            resumeZoom = available / docH;
        } else {
            resumeZoom = 1;
        }
        // CSS zoom changes actual layout size — no marginBottom hack needed
        doc.style.zoom = String(resumeZoom);
    }

    // Mouse scroll zoom + drag-to-pan on the resume modal
    let resumePanX = 0, resumePanY = 0;
    let isDragging = false, dragStartX = 0, dragStartY = 0, dragStartPanX = 0, dragStartPanY = 0;

    function applyResumeTransform() {
        const doc = document.getElementById('resumeDocumentEl');
        if (!doc) return;
        doc.style.zoom = String(resumeZoom);
        doc.style.transform = 'translate(' + resumePanX + 'px, ' + resumePanY + 'px)';
    }

    document.addEventListener('DOMContentLoaded', function () {
        const wrapper = document.getElementById('resumeZoomWrapper');
        if (!wrapper) return;

        // Scroll zoom
        wrapper.addEventListener('wheel', function (e) {
            const modal = document.getElementById('resumeModal');
            if (!modal || modal.style.display === 'none') return;
            const editMode = document.getElementById('resumeEdit');
            if (editMode && editMode.style.display !== 'none') return;
            e.preventDefault();
            if (e.deltaY < 0) {
                resumeZoom = Math.min(ZOOM_MAX, resumeZoom + ZOOM_STEP);
            } else {
                resumeZoom = Math.max(ZOOM_MIN, resumeZoom - ZOOM_STEP);
            }
            applyResumeTransform();
        }, { passive: false });

        // Drag to pan
        wrapper.addEventListener('mousedown', function (e) {
            const modal = document.getElementById('resumeModal');
            if (!modal || modal.style.display === 'none') return;
            const editMode = document.getElementById('resumeEdit');
            if (editMode && editMode.style.display !== 'none') return;
            if (e.button !== 0) return;
            isDragging = true;
            dragStartX = e.clientX;
            dragStartY = e.clientY;
            dragStartPanX = resumePanX;
            dragStartPanY = resumePanY;
            wrapper.style.cursor = 'grabbing';
            e.preventDefault();
        });

        document.addEventListener('mousemove', function (e) {
            if (!isDragging) return;
            var dx = (e.clientX - dragStartX) / resumeZoom;
            var dy = (e.clientY - dragStartY) / resumeZoom;
            resumePanX = dragStartPanX + dx;
            resumePanY = dragStartPanY + dy;
            applyResumeTransform();
        });

        document.addEventListener('mouseup', function () {
            if (isDragging) {
                isDragging = false;
                var w = document.getElementById('resumeZoomWrapper');
                if (w) w.style.cursor = '';
            }
        });
    });

    // Signature style preview
    function updateSigPreview() {
        // Visual feedback handled by CSS :checked selector
    }

    // Export Resume as image
    function exportResume() {
        const doc = document.getElementById('resumeDocumentEl');
        const view = document.getElementById('resumeView');
        if (!doc || !view || view.style.display === 'none') {
            alert('Please switch to View mode before exporting.');
            return;
        }
        const btn = document.getElementById('btnExportResume');
        const origBtnText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Exporting...';
        btn.disabled = true;
        const wrapper = document.getElementById('resumeZoomWrapper');
        const origZoom = doc.style.zoom;
        const origTransform = doc.style.transform;
        const origWrapperOverflow = wrapper ? wrapper.style.overflow : '';
        doc.style.zoom = '1';
        doc.style.transform = 'none';
        doc.style.overflow = 'visible';
        if (wrapper) wrapper.style.overflow = 'visible';
        setTimeout(function () {
            html2canvas(doc, {
                scale: 2,
                useCORS: true,
                allowTaint: true,
                backgroundColor: '#ffffff',
                logging: false,
                scrollY: 0,
                windowHeight: doc.scrollHeight
            }).then(function (canvas) {
                doc.style.zoom = origZoom;
                doc.style.transform = origTransform;
                doc.style.overflow = 'visible';
                if (wrapper) wrapper.style.overflow = origWrapperOverflow;
                var link = document.createElement('a');
                var nameEl = document.getElementById('view_name');
                var fileName = nameEl && nameEl.textContent.trim() ? nameEl.textContent.trim().replace(/\s+/g, '_') + '_Resume.png' : 'Resume.png';
                link.download = fileName;
                link.href = canvas.toDataURL('image/png');
                link.click();
                btn.innerHTML = origBtnText;
                btn.disabled = false;
            }).catch(function (err) {
                console.error('Export error:', err);
                doc.style.zoom = origZoom;
                doc.style.transform = origTransform;
                doc.style.overflow = 'visible';
                if (wrapper) wrapper.style.overflow = origWrapperOverflow;
                btn.innerHTML = origBtnText;
                btn.disabled = false;
                alert('Export failed. Please try again.');
            });
        }, 200);
    }

    // Close modal on outside click (but not after dragging)
    let didDrag = false;
    window.addEventListener('mousedown', function (e) {
        didDrag = false;
    });
    window.addEventListener('mousemove', function (e) {
        if (isDragging) didDrag = true;
    });
    window.addEventListener('click', function (event) {
        if (didDrag) { didDrag = false; return; }
        var resumeModal = document.getElementById('resumeModal');
        var successModal = document.getElementById('successModal');
        if (event.target == resumeModal || event.target == document.getElementById('resumeZoomWrapper')) {
            closeResume();
        }
        if (event.target == successModal) {
            successModal.style.display = 'none';
        }
    });
</script>