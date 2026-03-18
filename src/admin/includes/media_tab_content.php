<!-- Cleaned up structure with CSS Grid and modern styling -->
<link rel="stylesheet" href="/assets/css/admin_media.css">

<div class="media-manager">

    <!-- HEADER -->
    <div class="manager-header">
        <div class="manager-title">
            <h2><i class="fas fa-layer-group"></i> Media Library</h2>
        </div>
        <div class="manager-actions">
            <a href="manage_categories_tags.php" class="action-btn">
                <i class="fas fa-tags"></i> Manage Tags
            </a>
            <button class="action-btn primary" onclick="toggleUpload()">
                <i class="fas fa-cloud-upload-alt"></i> Upload Media
            </button>
        </div>
    </div>

    <!-- UPLOAD SECTION (Collapsible) -->
    <div id="uploadSection" class="upload-section">
        <h3 style="color: var(--accent); margin-bottom: 1.5rem;">Upload New Content</h3>

        <!-- Tab Navigation -->
        <div class="upload-tabs"
            style="display: flex; gap: 1rem; margin-bottom: 2rem; border-bottom: 2px solid var(--border);">
            <button type="button" class="upload-tab active" data-tab="files" onclick="switchUploadTab('files')">
                <i class="fas fa-images"></i> Upload Files
            </button>
            <button type="button" class="upload-tab" data-tab="video" onclick="switchUploadTab('video')">
                <i class="fas fa-video"></i> Add Video Link
            </button>
        </div>

        <!-- FILE UPLOAD TAB -->
        <form id="uploadForm" enctype="multipart/form-data" class="upload-tab-content" data-content="files"
            style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem;">
            <!-- DROP ZONE -->
            <div>
                <div id="dropZone">
                    <i class="fas fa-images" style="font-size: 3rem; color: #555; margin-bottom: 1rem;"></i>
                    <h4 style="color: #ddd; margin-bottom: 0.5rem;">Drag & Drop files here</h4>
                    <p style="color: #777; font-size: 0.9rem;">or click to browse</p>
                    <input type="file" id="fileInput" name="media_file[]" accept="image/*" multiple
                        style="display: none;">
                </div>

                <div id="previewContainer" style="display: none;">
                    <p style="color: #aaa; margin-top: 1rem; font-size: 0.9rem;">Ready to upload <strong id="fileCount"
                            style="color: #fff;">0</strong> files</p>
                    <div id="previewGrid" class="preview-grid"></div>
                </div>
            </div>

            <!-- OPTIONS -->
            <div
                style="background: rgba(0,0,0,0.2); padding: 1.5rem; border-radius: var(--radius); border: 1px solid var(--border);">
                <!-- Album Selection -->
                <div style="margin-bottom: 1.5rem;">
                    <label style="display: block; color: var(--accent); font-weight: 600; margin-bottom: 0.5rem;">
                        <i class="fas fa-folder"></i> Album
                    </label>
                    <select id="albumSelect" name="album_id" onchange="toggleNewAlbum()" class="filter-select">
                        <option value="">-- No Album (Individual Files) --</option>
                        <option value="new">+ Create New Album</option>
                        <?php if (!empty($albums)): ?>
                            <optgroup label="Existing Albums">
                                <?php foreach ($albums as $album): ?>
                                    <option value="<?php echo $album['id']; ?>"><?php echo htmlspecialchars($album['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endif; ?>
                    </select>

                    <div id="newAlbumFields"
                        style="display: none; margin-top: 1rem; padding-top: 1rem; border-top: 1px dashed rgba(255,255,255,0.1);">
                        <input type="text" name="new_album_title" placeholder="New Album Title" class="filter-input"
                            style="margin-bottom: 0.75rem;">
                        <textarea name="new_album_desc" rows="2" placeholder="Description (Optional)"
                            class="filter-input" style="font-family: inherit;"></textarea>
                    </div>

                    <!-- Standalone Media Title/Description (shown when no album selected) -->
                    <div id="standaloneMediaFields"
                        style="margin-top: 1rem; padding-top: 1rem; border-top: 1px dashed rgba(255,255,255,0.1); display: block;">
                        <label style="display: block; color: var(--accent); font-weight: 600; margin-bottom: 0.5rem;">
                            <i class="fas fa-heading"></i> Media Title
                        </label>
                        <input type="text" name="media_title" placeholder="Title for standalone media (optional)"
                            class="filter-input" style="margin-bottom: 0.75rem;">

                        <label style="display: block; color: #aaa; margin-bottom: 0.5rem;">Description</label>
                        <textarea name="media_description" rows="2" placeholder="Description (optional)"
                            class="filter-input" style="font-family: inherit;"></textarea>
                    </div>
                </div>

                <!-- Category -->
                <div style="margin-bottom: 1.5rem;">
                    <label style="display: block; color: #aaa; margin-bottom: 0.5rem;">Category</label>
                    <select name="category_id" class="filter-select">
                        <option value="">Uncategorized</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Tags -->
                <div style="margin-bottom: 1.5rem;">
                    <label style="display: block; color: #aaa; margin-bottom: 0.5rem;">Tags</label>
                    <select name="tags[]" multiple class="filter-select" style="height: 100px;">
                        <?php foreach ($tags as $tag): ?>
                            <option value="<?php echo $tag['id']; ?>"><?php echo htmlspecialchars($tag['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small style="color: #555; display: block; margin-top: 5px;">Hold Ctrl to select multiple</small>
                </div>

                <button type="submit" id="uploadBtn" class="action-btn primary"
                    style="width: 100%; justify-content: center; padding: 1rem;">
                    <i class="fas fa-upload"></i> Start Upload
                </button>
            </div>
        </form>

        <!-- VIDEO LINK TAB -->
        <form id="videoLinkForm" class="upload-tab-content" data-content="video" style="display: none;">
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem;">
                <!-- VIDEO PREVIEW AREA -->
                <div>
                    <div id="videoInputArea"
                        style="background: rgba(0,0,0,0.2); padding: 2rem; border-radius: var(--radius); border: 1px solid var(--border); text-align: center;">
                        <i class="fas fa-video" style="font-size: 3rem; color: #555; margin-bottom: 1rem;"></i>
                        <h4 style="color: #ddd; margin-bottom: 1rem;">Add Video from Link</h4>

                        <div style="max-width: 500px; margin: 0 auto;">
                            <input type="url" id="videoUrlInput" name="video_url"
                                placeholder="Paste YouTube, Vimeo, or video URL here..." class="filter-input"
                                style="margin-bottom: 1rem; text-align: center;" required>

                            <div
                                style="display: flex; gap: 0.5rem; flex-wrap: wrap; justify-content: center; margin-bottom: 1rem;">
                                <span
                                    style="background: rgba(255,0,0,0.1); color: #ff6b6b; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.8rem; border: 1px solid rgba(255,0,0,0.2);">
                                    <i class="fab fa-youtube"></i> YouTube
                                </span>
                                <span
                                    style="background: rgba(26,183,234,0.1); color: #1ab7ea; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.8rem; border: 1px solid rgba(26,183,234,0.2);">
                                    <i class="fab fa-vimeo"></i> Vimeo
                                </span>
                                <span
                                    style="background: rgba(197,160,89,0.1); color: var(--accent); padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.8rem; border: 1px solid rgba(197,160,89,0.2);">
                                    <i class="fas fa-link"></i> Direct Link
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- VIDEO PREVIEW -->
                    <div id="videoPreview"
                        style="display: none; margin-top: 1.5rem; background: rgba(0,0,0,0.3); padding: 1.5rem; border-radius: var(--radius); border: 1px solid var(--border);">
                        <div
                            style="position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; border-radius: 8px; margin-bottom: 1rem;">
                            <iframe id="videoPreviewFrame"
                                style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: none;"
                                allowfullscreen></iframe>
                        </div>
                        <div style="display: flex; align-items: center; gap: 0.75rem; color: #aaa; font-size: 0.9rem;">
                            <i class="fas fa-check-circle" style="color: #4ade80;"></i>
                            <span id="videoPlatformBadge"></span>
                        </div>
                    </div>
                </div>

                <!-- VIDEO OPTIONS -->
                <div
                    style="background: rgba(0,0,0,0.2); padding: 1.5rem; border-radius: var(--radius); border: 1px solid var(--border);">
                    <!-- Title -->
                    <div style="margin-bottom: 1.5rem;">
                        <label style="display: block; color: var(--accent); font-weight: 600; margin-bottom: 0.5rem;">
                            <i class="fas fa-heading"></i> Title
                        </label>
                        <input type="text" id="videoTitle" name="title" placeholder="Video title (auto-detected)"
                            class="filter-input">
                    </div>

                    <!-- Description -->
                    <div style="margin-bottom: 1.5rem;">
                        <label style="display: block; color: #aaa; margin-bottom: 0.5rem;">Description</label>
                        <textarea id="videoDescription" name="description" rows="3"
                            placeholder="Optional description..." class="filter-input"
                            style="font-family: inherit;"></textarea>
                    </div>

                    <!-- Album Selection -->
                    <div style="margin-bottom: 1.5rem;">
                        <label style="display: block; color: #aaa; margin-bottom: 0.5rem;">
                            <i class="fas fa-folder"></i> Album
                        </label>
                        <select id="videoAlbumSelect" name="album_id" onchange="toggleNewAlbumVideo()"
                            class="filter-select">
                            <option value="">-- No Album --</option>
                            <option value="new">+ Create New Album</option>
                            <?php if (!empty($albums)): ?>
                                <optgroup label="Existing Albums">
                                    <?php foreach ($albums as $album): ?>
                                        <option value="<?php echo $album['id']; ?>">
                                            <?php echo htmlspecialchars($album['title']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endif; ?>
                        </select>

                        <div id="newAlbumFieldsVideo"
                            style="display: none; margin-top: 1rem; padding-top: 1rem; border-top: 1px dashed rgba(255,255,255,0.1);">
                            <input type="text" name="new_album_title" placeholder="New Album Title" class="filter-input"
                                style="margin-bottom: 0.75rem;">
                            <textarea name="new_album_desc" rows="2" placeholder="Description (Optional)"
                                class="filter-input" style="font-family: inherit;"></textarea>
                        </div>
                    </div>

                    <!-- Category -->
                    <div style="margin-bottom: 1.5rem;">
                        <label style="display: block; color: #aaa; margin-bottom: 0.5rem;">Category</label>
                        <select name="category_id" class="filter-select">
                            <option value="">Uncategorized</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Tags -->
                    <div style="margin-bottom: 1.5rem;">
                        <label style="display: block; color: #aaa; margin-bottom: 0.5rem;">Tags</label>
                        <select name="tags[]" multiple class="filter-select" style="height: 80px;">
                            <?php foreach ($tags as $tag): ?>
                                <option value="<?php echo $tag['id']; ?>"><?php echo htmlspecialchars($tag['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small style="color: #555; display: block; margin-top: 5px;">Hold Ctrl to select
                            multiple</small>
                    </div>

                    <button type="submit" id="videoUploadBtn" class="action-btn primary"
                        style="width: 100%; justify-content: center; padding: 1rem;">
                        <i class="fas fa-plus-circle"></i> Add Video
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- FILTERS -->
    <div class="filter-bar">
        <form method="GET" style="display: contents;">
            <!-- Search -->
            <div class="filter-group" style="flex: 2;">
                <i class="fas fa-search" style="color: #555;"></i>
                <input type="text" name="search" class="filter-input" placeholder="Search library..."
                    value="<?php echo htmlspecialchars($search); ?>">
            </div>

            <!-- Filters -->
            <div class="filter-group">
                <select name="category" class="filter-select">
                    <option value="0">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo $filter_category == $cat['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <select name="album" class="filter-select">
                    <option value="0">All Albums</option>
                    <?php foreach ($albums as $album): ?>
                        <option value="<?php echo $album['id']; ?>" <?php echo $filter_album == $album['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($album['title']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="action-btn">Filter</button>
        </form>
    </div>

    <!-- LISTING -->
    <?php if (empty($media_items)): ?>
        <div style="text-align: center; padding: 5rem; color: var(--text-muted); opacity: 0.5;">
            <i class="fas fa-folder-open" style="font-size: 4rem; margin-bottom: 1rem;"></i>
            <p>No content found matching your filters.</p>
        </div>
    <?php else: ?>
        <div class="media-grid">
            <?php foreach ($media_items as $item): ?>
                <?php $isAlbum = ($item['type'] === 'album'); ?>
                <?php $isVideo = !$isAlbum && isset($item['is_video']) && $item['is_video']; ?>

                <!-- If item is Album, trigger openAlbum(), if Video trigger viewVideo(), else viewMedia() -->
                <div class="media-card <?php echo $isAlbum ? 'album-stack' : ''; ?>" onclick="<?php
                       if ($isAlbum) {
                           echo "openAlbum({$item['id']})";
                       } elseif ($isVideo) {
                           // Create explicit JavaScript object for video
                           $videoData = [
                               'id' => $item['id'],
                               'title' => $item['title'],
                               'description' => $item['description'] ?? '',
                               'video_platform' => $item['video_platform'] ?? '',
                               'video_id' => $item['video_id'] ?? '',
                               'video_url' => $item['video_url'] ?? ''
                           ];
                           echo "viewVideo(" . htmlspecialchars(json_encode($videoData), ENT_QUOTES, 'UTF-8') . ")";
                       } else {
                           echo "viewMedia('{$item['filename']}')";
                       }
                       ?>">

                    <div class="card-thumb">
                        <?php if ($item['filename'] && file_exists(ROOT_PATH . "/assets/images/gallery/thumbs/{$item['filename']}")): ?>
                            <!-- Adjust path for admin -->
                            <img src="/assets/images/gallery/thumbs/<?php echo $item['filename']; ?>" loading="lazy" alt="Cover">
                        <?php else: ?>
                            <div
                                style="display: flex; align-items: center; justify-content: center; height: 100%; background: linear-gradient(135deg, #1a1a1a 0%, #2a2a2a 100%); color: #666;">
                                <?php if ($isVideo): ?>
                                    <i class="fas fa-video" style="font-size: 3rem;"></i>
                                <?php else: ?>
                                    <i class="fas fa-image" style="font-size: 3rem;"></i>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <div class="card-type-badge <?php echo $isAlbum ? 'album' : ''; ?>">
                            <?php if ($isAlbum): ?>
                                <i class="fas fa-images"></i> ALBUM
                            <?php elseif ($isVideo): ?>
                                <i class="fas fa-video"></i> VIDEO
                            <?php else: ?>
                                <i class="fas fa-image"></i> MEDIA
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card-body">
                        <div class="card-title"><?php echo htmlspecialchars($item['title'] ?: 'Untitled'); ?></div>

                        <div class="card-meta">
                            <i class="far fa-calendar-alt"></i> <?php echo date('M d, Y', strtotime($item['sort_date'])); ?>
                            <?php if ($isAlbum): ?>
                                &bull; <?php echo $item['item_count']; ?> Items
                            <?php endif; ?>
                        </div>

                        <?php if ($item['category_name']): ?>
                            <span
                                style="display: inline-block; background: rgba(255,255,255,0.05); padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; color: #aaa; margin-bottom: 1rem; width: fit-content;">
                                <?php echo htmlspecialchars($item['category_name']); ?>
                            </span>
                        <?php endif; ?>

                        <div class="card-actions">
                            <?php if (!$isAlbum): ?>
                                <button class="action-btn btn-sm" style="flex: 1; justify-content: center;" onclick="event.stopPropagation(); <?php echo $isVideo ? 'viewVideo(' . htmlspecialchars(json_encode([
                                    'id' => $item['id'],
                                    'title' => $item['title'],
                                    'description' => $item['description'] ?? '',
                                    'video_platform' => $item['video_platform'] ?? '',
                                    'video_id' => $item['video_id'] ?? '',
                                    'video_url' => $item['video_url'] ?? ''
                                ]), ENT_QUOTES, 'UTF-8') . ')' : "viewMedia('{$item['filename']}')"; ?>">
                                    <i class="fas fa-eye"></i> View
                                </button>
                                <button class="action-btn btn-sm"
                                    style="color: #ff4d4d; border-color: rgba(255, 77, 77, 0.3); flex: 0 0 auto; padding: 0.4rem 0.8rem;"
                                    onclick="event.stopPropagation(); deleteMedia(<?php echo $item['id']; ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            <?php else: ?>
                                <button class="action-btn btn-sm" style="flex: 1; justify-content: center;"
                                    onclick="event.stopPropagation(); openAlbum(<?php echo $item['id']; ?>)">
                                    View
                                </button>
                                <button class="action-btn btn-sm"
                                    style="color: #ff4d4d; border-color: rgba(255, 77, 77, 0.3); flex: 0 0 auto; padding: 0.4rem 0.8rem;"
                                    onclick="event.stopPropagation(); deleteAlbum(<?php echo $item['id']; ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

<!-- UPLOAD PROGRESS MODAL -->
<div id="uploadProgressOverlay" class="upload-progress-overlay">
    <div class="upload-progress-modal">
        <div class="upload-progress-header">
            <h3 class="upload-progress-title">
                <i class="fas fa-cloud-upload-alt"></i>
                Uploading...
            </h3>
        </div>
        <div class="upload-progress-body">
            <!-- File Preview -->
            <div class="upload-file-preview">
                <div id="uploadFileIcon" class="upload-file-icon">
                    <i class="fas fa-file-image"></i>
                </div>
                <div class="upload-file-info">
                    <div id="uploadFileName" class="upload-file-name">Project_Brief.txt</div>
                    <div id="uploadFileSize" class="upload-file-size">17.45 KB</div>
                </div>
            </div>

            <!-- Progress Bar -->
            <div class="upload-progress-bar-container">
                <div class="upload-progress-bar-wrapper">
                    <div id="uploadProgressBar" class="upload-progress-bar"></div>
                </div>
                <div class="upload-progress-text">
                    <span id="uploadProgressPercentage" class="upload-progress-percentage">0%</span>
                    <span class="upload-progress-status">Uploading files...</span>
                </div>
            </div>

            <!-- Upload Stats -->
            <div class="upload-stats">
                <div class="upload-stat">
                    <div class="upload-stat-label">Upload Speed</div>
                    <div id="uploadSpeed" class="upload-stat-value">Calculating...</div>
                </div>
                <div class="upload-stat">
                    <div class="upload-stat-label">Time Remaining</div>
                    <div id="uploadTimeRemaining" class="upload-stat-value">Calculating...</div>
                </div>
            </div>
        </div>
        <div class="upload-progress-footer">
            <button class="upload-cancel-btn" onclick="cancelUpload()">
                <i class="fas fa-times"></i> Cancel Upload
            </button>
        </div>
    </div>
</div>

<!-- ALBUM MODAL -->

<div id="albumModal" class="album-modal-overlay" onclick="if(event.target === this) closeAlbumModal()">
    <div class="album-modal">
        <div class="album-modal-header">
            <div class="album-info">
                <h2 id="modalAlbumTitle">Album Title</h2>
                <p id="modalAlbumDesc">Album Description</p>
            </div>
            <button class="close-modal-btn" onclick="closeAlbumModal()">&times;</button>
        </div>
        <div class="album-modal-body">
            <div id="albumGrid" class="album-grid">
                <!-- Content injected via JS -->
            </div>
            <div id="albumLoading" style="text-align: center; padding: 2rem; display: none;">
                <i class="fas fa-spinner fa-spin" style="font-size: 2rem;"></i>
            </div>
        </div>
    </div>
</div>

<!-- CUSTOM ALERT/CONFIRM MODAL -->
<div id="customModalOverlay" class="custom-modal-overlay">
    <div class="custom-modal">
        <div class="custom-modal-header">
            <h3 id="customModalTitle" class="custom-modal-title">Title</h3>
        </div>
        <div id="customModalBody" class="custom-modal-body">
            Message
        </div>
        <div id="customModalFooter" class="custom-modal-footer">
            <button id="customModalCancel" class="c-btn c-btn-cancel">Cancel</button>
            <button id="customModalConfirm" class="c-btn c-btn-confirm">OK</button>
        </div>
    </div>
</div>

<!-- LIGHTBOX -->
<div id="lightbox" class="lightbox" onclick="if(event.target === this) closeLightbox()">
    <div class="lightbox-close" onclick="closeLightbox()">&times;</div>
    <div class="lightbox-nav lightbox-prev" onclick="changeMedia(-1)"><i class="fas fa-chevron-left"></i></div>
    <div class="lightbox-nav lightbox-next" onclick="changeMedia(1)"><i class="fas fa-chevron-right"></i></div>

    <div class="lightbox-content">
        <img id="lightbox-img" src="" alt="" style="display: none;">
        <div id="lightbox-video"></div>

    </div>

    <div class="lightbox-info">
        <div id="lightbox-title" class="lightbox-title"></div>
        <div id="lightbox-desc" class="lightbox-desc"></div>
    </div>
</div>

<script>
    // Toggle Upload Section
    function toggleUpload() {
        const section = document.getElementById('uploadSection');
        section.classList.toggle('active');
    }

    // Toggle New Album Fields
    function toggleNewAlbum() {
        const select = document.getElementById('albumSelect');
        const newFields = document.getElementById('newAlbumFields');
        const standaloneFields = document.getElementById('standaloneMediaFields');

        if (select.value === 'new') {
            // Creating new album - show album fields, hide standalone fields
            newFields.style.display = 'block';
            newFields.querySelector('input').required = true;
            standaloneFields.style.display = 'none';
        } else if (select.value === '') {
            // No album selected - hide album fields, show standalone fields
            newFields.style.display = 'none';
            newFields.querySelector('input').required = false;
            standaloneFields.style.display = 'block';
        } else {
            // Existing album selected - hide both
            newFields.style.display = 'none';
            newFields.querySelector('input').required = false;
            standaloneFields.style.display = 'none';
        }
    }

    // Initialize standalone fields visibility on page load
    toggleNewAlbum();

    // Drag & Drop & Form Logic
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('fileInput');
    const previewContainer = document.getElementById('previewContainer');
    const previewGrid = document.getElementById('previewGrid');
    const fileCount = document.getElementById('fileCount');

    if (dropZone) {
        dropZone.addEventListener('click', () => fileInput.click());

        ['dragover', 'dragenter'].forEach(type => {
            dropZone.addEventListener(type, (e) => {
                e.preventDefault();
                dropZone.classList.add('dragover');
            });
        });

        ['dragleave', 'dragend', 'drop'].forEach(type => {
            dropZone.addEventListener(type, () => {
                dropZone.classList.remove('dragover');
            });
        });

        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            const files = e.dataTransfer.files;
            if (files.length) {
                fileInput.files = files;
                handleFiles(files);
            }
        });
    }

    if (fileInput) {
        fileInput.addEventListener('change', (e) => handleFiles(e.target.files));
    }


    function handleFiles(files) {
        if (!files.length) return;

        // Check file count limit
        if (files.length > 20) {
            showAlert('Warning', `You selected ${files.length} files. Please upload a maximum of 20 files at a time.`);
            fileInput.value = '';
            previewContainer.style.display = 'none';
            return;
        }

        // Check per-file size limit (10MB)
        const maxPerFile = 10 * 1024 * 1024; // 10 MB
        const oversizedFiles = Array.from(files).filter(f => f.size > maxPerFile);
        if (oversizedFiles.length > 0) {
            const names = oversizedFiles.map(f => `${f.name} (${(f.size / 1024 / 1024).toFixed(1)} MB)`).join(', ');
            showAlert('File Too Large', `The following files exceed the 10 MB limit per image:\n\n${names}\n\nPlease resize or compress them before uploading.`);
            fileInput.value = '';
            previewContainer.style.display = 'none';
            return;
        }

        previewGrid.innerHTML = '';
        fileCount.textContent = files.length;
        previewContainer.style.display = 'block';

        Array.from(files).forEach(file => {
            if (!file.type.startsWith('image/')) return;
            const reader = new FileReader();
            reader.onload = (e) => {
                const div = document.createElement('div');
                div.className = 'preview-item';
                div.innerHTML = `<img src="${e.target.result}">`;
                previewGrid.appendChild(div);
            };
            reader.readAsDataURL(file);
        });
    }

    // Upload Progress Modal Variables
    let uploadXHR = null;
    let uploadStartTime = 0;

    // Upload Logic with Progress Tracking
    const uploadForm = document.getElementById('uploadForm');
    if (uploadForm) {
        uploadForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const btn = document.getElementById('uploadBtn');

            // Get files directly from fileInput element
            const fileInput = document.getElementById('fileInput');
            const files = fileInput ? fileInput.files : null;

            if (!files || files.length === 0) {
                showAlert('Error', 'Please select files to upload.');
                return;
            }

            // Disable upload button
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Preparing...';

            // Get first file for preview
            const firstFile = files[0];
            const totalSize = Array.from(files).reduce((sum, file) => sum + file.size, 0);

            // Check 64MB Batch Limit (matches PHP post_max_size)
            const maxSize = 64 * 1024 * 1024; // 64 MB
            if (totalSize > maxSize) {
                showAlert('Upload Limit Warning', 'Your upload has exceeded the 64 MB per-batch limit. Please select fewer files or upload them in multiple rounds.');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-upload"></i> Start Upload';
                return;
            }

            // Show progress modal
            showUploadProgress(firstFile, files.length, totalSize);

            // Prepare form data
            const formData = new FormData(e.target);

            // Create XMLHttpRequest for progress tracking
            uploadXHR = new XMLHttpRequest();
            uploadStartTime = Date.now();

            // Progress event
            uploadXHR.upload.addEventListener('progress', (e) => {
                if (e.lengthComputable) {
                    const percentComplete = (e.loaded / e.total) * 100;
                    const elapsed = (Date.now() - uploadStartTime) / 1000; // seconds
                    const uploadSpeed = e.loaded / elapsed; // bytes per second
                    const timeRemaining = (e.total - e.loaded) / uploadSpeed; // seconds

                    updateUploadProgress(percentComplete, e.loaded, e.total, uploadSpeed, timeRemaining);
                }
            });

            // Load event (completion)
            uploadXHR.addEventListener('load', () => {
                if (uploadXHR.status === 200) {
                    try {
                        const result = JSON.parse(uploadXHR.responseText);
                        if (result.success) {
                            updateUploadProgress(100, totalSize, totalSize, 0, 0);
                            setTimeout(() => {
                                hideUploadProgress();
                                showAlert('Success', 'Upload Complete!', () => {
                                    location.reload();
                                });
                            }, 500);
                        } else {
                            hideUploadProgress();
                            showAlert('Error', 'Upload Failed: ' + result.message);
                            btn.disabled = false;
                            btn.innerHTML = '<i class="fas fa-upload"></i> Start Upload';
                        }
                    } catch (err) {
                        console.error('Parse error:', err);
                        hideUploadProgress();
                        showAlert('Error', 'Invalid server response.');
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fas fa-upload"></i> Start Upload';
                    }
                } else {
                    hideUploadProgress();
                    showAlert('Error', 'Upload failed with status: ' + uploadXHR.status);
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-upload"></i> Start Upload';
                }
            });

            // Error event
            uploadXHR.addEventListener('error', () => {
                hideUploadProgress();
                showAlert('Error', 'Network error occurred during upload.');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-upload"></i> Start Upload';
            });

            // Abort event
            uploadXHR.addEventListener('abort', () => {
                hideUploadProgress();
                showAlert('Info', 'Upload cancelled.');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-upload"></i> Start Upload';
            });

            // Send request
            uploadXHR.open('POST', 'upload_media.php', true);
            uploadXHR.send(formData);
        });
    }

    // Upload Progress Functions
    function showUploadProgress(file, fileCount, totalSize) {
        const overlay = document.getElementById('uploadProgressOverlay');
        const fileName = document.getElementById('uploadFileName');
        const fileSize = document.getElementById('uploadFileSize');
        const fileIcon = document.getElementById('uploadFileIcon');

        // Set file info
        fileName.textContent = fileCount > 1 ? `${fileCount} files` : file.name;
        fileSize.textContent = formatFileSize(totalSize);

        // Set file preview
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = (e) => {
                fileIcon.innerHTML = `<img src="${e.target.result}" alt="Preview">`;
            };
            reader.readAsDataURL(file);
        } else {
            fileIcon.innerHTML = '<i class="fas fa-file-image"></i>';
        }

        // Show modal
        overlay.classList.add('active');
    }

    function updateUploadProgress(percentage, loaded, total, speed, timeRemaining) {
        const progressBar = document.getElementById('uploadProgressBar');
        const progressPercentage = document.getElementById('uploadProgressPercentage');
        const uploadSpeed = document.getElementById('uploadSpeed');
        const uploadTimeRemaining = document.getElementById('uploadTimeRemaining');

        progressBar.style.width = percentage + '%';
        progressPercentage.textContent = Math.round(percentage) + '%';
        uploadSpeed.textContent = speed > 0 ? formatFileSize(speed) + '/s' : 'Calculating...';
        uploadTimeRemaining.textContent = timeRemaining > 0 ? formatTime(timeRemaining) : 'Calculating...';
    }

    function hideUploadProgress() {
        const overlay = document.getElementById('uploadProgressOverlay');
        overlay.classList.remove('active');

        // Reset progress
        setTimeout(() => {
            document.getElementById('uploadProgressBar').style.width = '0%';
            document.getElementById('uploadProgressPercentage').textContent = '0%';
            document.getElementById('uploadSpeed').textContent = 'Calculating...';
            document.getElementById('uploadTimeRemaining').textContent = 'Calculating...';
        }, 300);
    }

    function cancelUpload() {
        if (uploadXHR) {
            uploadXHR.abort();
            uploadXHR = null;
        }
    }

    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i];
    }

    function formatTime(seconds) {
        if (seconds < 60) {
            return Math.round(seconds) + 's';
        } else if (seconds < 3600) {
            const minutes = Math.floor(seconds / 60);
            const secs = Math.round(seconds % 60);
            return minutes + 'm ' + secs + 's';
        } else {
            const hours = Math.floor(seconds / 3600);
            const minutes = Math.floor((seconds % 3600) / 60);
            return hours + 'h ' + minutes + 'm';
        }
    }

    // === VIDEO LINK TAB FUNCTIONS ===

    // Tab Switching
    function switchUploadTab(tabName) {
        // Update tab buttons
        document.querySelectorAll('.upload-tab').forEach(tab => {
            tab.classList.remove('active');
        });
        document.querySelector(`.upload-tab[data-tab="${tabName}"]`).classList.add('active');

        // Update content
        document.querySelectorAll('.upload-tab-content').forEach(content => {
            content.style.display = 'none';
        });
        document.querySelector(`.upload-tab-content[data-content="${tabName}"]`).style.display =
            tabName === 'files' ? 'grid' : 'block';
    }

    // Toggle New Album for Video
    function toggleNewAlbumVideo() {
        const select = document.getElementById('videoAlbumSelect');
        const fields = document.getElementById('newAlbumFieldsVideo');
        if (select.value === 'new') {
            fields.style.display = 'block';
            fields.querySelector('input').required = true;
        } else {
            fields.style.display = 'none';
            fields.querySelector('input').required = false;
        }
    }

    // Video URL Preview
    const videoUrlInput = document.getElementById('videoUrlInput');
    const videoPreview = document.getElementById('videoPreview');
    const videoPreviewFrame = document.getElementById('videoPreviewFrame');
    const videoPlatformBadge = document.getElementById('videoPlatformBadge');
    let videoPreviewTimeout;

    if (videoUrlInput) {
        videoUrlInput.addEventListener('input', function () {
            clearTimeout(videoPreviewTimeout);

            videoPreviewTimeout = setTimeout(() => {
                const url = this.value.trim();
                if (!url) {
                    videoPreview.style.display = 'none';
                    return;
                }

                const videoData = detectVideoFromUrl(url);
                if (videoData.platform !== 'unknown') {
                    showVideoPreview(videoData);
                } else {
                    videoPreview.style.display = 'none';
                }
            }, 500);
        });
    }

    function detectVideoFromUrl(url) {
        const urlLower = url.toLowerCase();
        let platform = 'unknown';
        let videoId = null;
        let embedUrl = null;

        // YouTube
        if (urlLower.includes('youtube.com') || urlLower.includes('youtu.be')) {
            platform = 'youtube';
            const match = url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]+)/);
            if (match) {
                videoId = match[1];
                embedUrl = `https://www.youtube.com/embed/${videoId}`;
            }
        }
        // Vimeo
        else if (urlLower.includes('vimeo.com')) {
            platform = 'vimeo';
            const match = url.match(/vimeo\.com\/(?:video\/)?(\d+)/);
            if (match) {
                videoId = match[1];
                embedUrl = `https://player.vimeo.com/video/${videoId}`;
            }
        }
        // Dailymotion
        else if (urlLower.includes('dailymotion.com') || urlLower.includes('dai.ly')) {
            platform = 'dailymotion';
            const match = url.match(/(?:dailymotion\.com\/video\/|dai\.ly\/)([a-zA-Z0-9]+)/);
            if (match) {
                videoId = match[1];
                embedUrl = `https://www.dailymotion.com/embed/video/${videoId}`;
            }
        }

        return { platform, videoId, embedUrl };
    }

    function showVideoPreview(videoData) {
        if (videoData.embedUrl) {
            videoPreviewFrame.src = videoData.embedUrl;
            videoPreview.style.display = 'block';

            const platformNames = {
                'youtube': '<i class="fab fa-youtube"></i> YouTube Video',
                'vimeo': '<i class="fab fa-vimeo"></i> Vimeo Video',
                'dailymotion': '<i class="fas fa-video"></i> Dailymotion Video'
            };

            videoPlatformBadge.innerHTML = platformNames[videoData.platform] || 'Video';
        }
    }

    // Video Link Form Submission
    const videoLinkForm = document.getElementById('videoLinkForm');
    if (videoLinkForm) {
        videoLinkForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = document.getElementById('videoUploadBtn');
            const originalText = btn.innerHTML;

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding Video...';

            try {
                const formData = new FormData(e.target);
                const response = await fetch('upload_video_link.php', { method: 'POST', body: formData });
                const result = await response.json();

                if (result.success) {
                    showAlert('Success', 'Video link added successfully!', () => {
                        location.reload();
                    });
                } else {
                    showAlert('Error', 'Failed to add video: ' + result.message);
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                }
            } catch (err) {
                console.error(err);
                showAlert('Error', 'An error occurred while adding the video.');
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        });
    }

    function deleteMedia(id) {
        showConfirm('Delete Media', 'Are you sure you want to delete this item? This action cannot be undone.', () => {
            fetch('delete_media.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    id: id
                })
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) location.reload();
                    else showAlert('Error', data.message);
                });
        }, 'Delete', 'danger');
    }

    function deleteAlbum(id) {
        showConfirm('Delete Album', 'WARNING: Deleting this album will also <b>DELETE ALL FILES</b> inside it.<br><br>Are you sure you want to proceed?', () => {
            fetch('delete_album.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    id: id
                })
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showAlert('Deleted', 'Album Deleted successfully', () => {
                            location.reload();
                        });
                        // Auto-reload backup in case user doesn't click
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showAlert('Error', data.message);
                    }
                });
        }, 'Delete Album', 'danger');
    }

    function viewMedia(filename) {
        // Open lightbox for standalone image
        const lightbox = document.getElementById('lightbox');
        const lightboxImgEl = document.getElementById('lightbox-img');
        const lightboxVideoEl = document.getElementById('lightbox-video');
        const lightboxTitle = document.getElementById('lightbox-title');
        const lightboxDesc = document.getElementById('lightbox-desc');

        // Hide video, show image
        lightboxVideoEl.style.display = 'none';
        lightboxImgEl.style.display = 'block';

        // Set image source
        lightboxImgEl.src = '/assets/images/gallery/full/' + filename;

        // Clear title and description for standalone view
        lightboxTitle.textContent = '';
        lightboxDesc.textContent = '';

        lightbox.classList.add('active');
    }





    // === ALBUM MODAL LOGIC ===
    const albumModal = document.getElementById('albumModal');
    const albumGrid = document.getElementById('albumGrid');
    const albumLoading = document.getElementById('albumLoading');
    const modalTitle = document.getElementById('modalAlbumTitle');
    const modalDesc = document.getElementById('modalAlbumDesc');
    let albumMediaItems = [];
    let albumIdToIndex = {};

    function openAlbum(id) {
        // Show Modal & Loading
        albumModal.style.display = 'flex';
        // Force reflow for fade in
        setTimeout(() => albumModal.classList.add('active'), 10);
        document.body.style.overflow = 'hidden';

        albumGrid.innerHTML = '';
        albumLoading.style.display = 'block';
        modalTitle.textContent = 'Loading...';
        modalDesc.textContent = '';

        // Fetch Data using relative path to includes (Assuming we are in admin/)
        fetch(`../includes/get_album_details.php?id=${id}`)
            .then(response => response.json())
            .then(data => {
                albumLoading.style.display = 'none';
                if (data.success) {
                    // Populate Header
                    modalTitle.textContent = data.album.title;
                    modalDesc.textContent = data.album.description;

                    // Populate Grid
                    albumMediaItems = data.media; // Assign to global scope var

                    // rebuild index map
                    albumIdToIndex = {};
                    albumMediaItems.forEach((item, index) => {
                        albumIdToIndex[item.id] = index;
                    });

                    if (albumMediaItems.length === 0) {
                        albumGrid.innerHTML = '<p style="grid-column: 1/-1; text-align: center; color: #888;">No items in this album.</p>';
                    } else {
                        // Render Items
                        albumMediaItems.forEach(item => {
                            const div = document.createElement('div');
                            div.className = 'album-grid-item';
                            // Note: 'item' properties come from PHP API: src, title, description, thumb, etc.
                            // We need to pass the ID to openLightbox
                            div.onclick = () => openLightbox(item.id);

                            // Fix path to be root-relative
                            let thumbPath = item.thumb.startsWith('/') ? item.thumb : '/' + item.thumb;

                            div.innerHTML = `
                                <img src="${thumbPath}" alt="${item.title}" loading="lazy">
                            `;
                            albumGrid.appendChild(div);
                        });
                    }
                } else {
                    modalTitle.textContent = 'Error';
                    modalDesc.textContent = data.message;
                }
            })
            .catch(err => {
                albumLoading.style.display = 'none';
                modalTitle.textContent = 'Error';
                modalDesc.textContent = 'Failed to load album.';
                console.error(err);
            });
    }

    function closeAlbumModal() {
        albumModal.classList.remove('active');
        setTimeout(() => {
            albumModal.style.display = 'none';
            document.body.style.overflow = '';
        }, 300);
    }

    // === LIGHTBOX LOGIC ===
    const lightbox = document.getElementById('lightbox');
    const lightboxImg = document.getElementById('lightbox-img');
    const lightboxTitle = document.getElementById('lightbox-title');
    const lightboxDesc = document.getElementById('lightbox-desc');
    let currentIndex = 0;

    function openLightbox(id) {
        let index = albumIdToIndex[id];
        if (index === undefined) return;

        currentIndex = index;
        updateLightbox();
        lightbox.classList.add('active');
    }

    function closeLightbox() {
        lightbox.classList.remove('active');
        setTimeout(() => {
            lightboxImg.src = '';
            // Safely clear video content to stop playback
            const lightboxVideoEl = document.getElementById('lightbox-video');
            if (lightboxVideoEl) {
                lightboxVideoEl.innerHTML = '';
                lightboxVideoEl.style.display = 'none';
            }
            document.getElementById('lightbox-img').style.display = 'none';
        }, 300);
    }

    function changeMedia(direction) {
        currentIndex += direction;

        // Loop
        if (currentIndex < 0) currentIndex = albumMediaItems.length - 1;
        if (currentIndex >= albumMediaItems.length) currentIndex = 0;

        // Add fade effect
        const lightboxContent = document.querySelector('.lightbox-content');
        lightboxContent.style.opacity = 0;
        setTimeout(() => {
            updateLightbox();
            lightboxContent.style.opacity = 1;
        }, 200);
    }

    function updateLightbox() {
        const item = albumMediaItems[currentIndex];
        const lightboxImgEl = document.getElementById('lightbox-img');
        const lightboxVideoEl = document.getElementById('lightbox-video');

        if (item.is_video) {
            viewVideo(item);
        } else {
            // Hide video, show image
            lightboxVideoEl.style.display = 'none';
            lightboxVideoEl.innerHTML = ''; // Ensure video is gone
            lightboxImgEl.style.display = 'block';

            // Adjust path for Admin view
            let fullPath = item.src;
            if (!fullPath.startsWith('/')) {
                fullPath = '/' + fullPath;
            }

            lightboxImgEl.src = fullPath;

            const lightboxTitle = document.getElementById('lightbox-title');
            const lightboxDesc = document.getElementById('lightbox-desc');
            lightboxTitle.textContent = item.title;
            lightboxDesc.textContent = item.description;
        }
    }

    // Keyboard Navigation
    document.addEventListener('keydown', function (e) {
        if (!lightbox.classList.contains('active')) return;

        if (e.key === 'Escape') closeLightbox();
        if (e.key === 'ArrowLeft') changeMedia(-1);
        if (e.key === 'ArrowRight') changeMedia(1);
    });
    // === CUSTOM MODAL LOGIC ===
    const customModalOverlay = document.getElementById('customModalOverlay');
    const customModalTitle = document.getElementById('customModalTitle');
    const customModalBody = document.getElementById('customModalBody');
    const customModalCancel = document.getElementById('customModalCancel');
    const customModalConfirm = document.getElementById('customModalConfirm');

    let currentConfirmCallback = null;

    function showModal(title, message, isConfirm = false, onConfirm = null, confirmText = 'OK', type = 'normal') {
        customModalTitle.textContent = title;
        customModalBody.innerHTML = message; // Allow HTML

        customModalCancel.style.display = isConfirm ? 'block' : 'none';

        customModalConfirm.textContent = confirmText;
        if (type === 'danger') {
            customModalConfirm.className = 'c-btn c-btn-danger';
        } else {
            customModalConfirm.className = 'c-btn c-btn-confirm';
        }

        currentConfirmCallback = onConfirm;

        customModalOverlay.style.display = 'flex';
        // Force reflow
        setTimeout(() => customModalOverlay.classList.add('active'), 10);
    }

    function closeModal() {
        customModalOverlay.classList.remove('active');
        setTimeout(() => {
            customModalOverlay.style.display = 'none';
            currentConfirmCallback = null;
        }, 300);
    }

    customModalCancel.onclick = closeModal;
    customModalConfirm.onclick = () => {
        if (currentConfirmCallback) currentConfirmCallback();
        closeModal();
    };

    // Convenience wrappers
    function showAlert(title, message, callback) {
        showModal(title, message, false, callback, 'OK', 'normal');
    }

    function showConfirm(title, message, callback, confirmText = 'Confirm', type = 'normal') {
        showModal(title, message, true, callback, confirmText, type);
    }

    // === STANDALONE MEDIA VIEWING ===
    function viewVideo(videoData) {
        // Debug: Log video data to console
        console.log('viewVideo called with:', videoData);

        // Validate video data
        if (!videoData.video_platform || !videoData.video_id) {
            console.error('Missing video platform or ID:', videoData);
            showAlert('Error', 'Video information is incomplete. Please re-upload the video.');
            return;
        }

        // TikTok doesn't allow embedding in most cases, open in new tab
        if (videoData.video_platform === 'tiktok') {
            window.open(videoData.video_url, '_blank');
            return;
        }

        const lightbox = document.getElementById('lightbox');
        const lightboxImgEl = document.getElementById('lightbox-img');
        const lightboxVideoEl = document.getElementById('lightbox-video');
        const lightboxTitle = document.getElementById('lightbox-title');
        const lightboxDesc = document.getElementById('lightbox-desc');

        // Hide image, show video container
        lightboxImgEl.style.display = 'none';
        lightboxVideoEl.style.display = 'block';


        let playerHtml = '';

        if (videoData.video_platform === 'youtube') {
            const embedUrl = `https://www.youtube.com/embed/${videoData.video_id}?autoplay=1`;
            playerHtml = `<iframe src="${embedUrl}" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: none;" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>`;
        } else if (videoData.video_platform === 'vimeo') {
            const embedUrl = `https://player.vimeo.com/video/${videoData.video_id}?autoplay=1`;
            playerHtml = `<iframe src="${embedUrl}" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: none;" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>`;
        } else if (videoData.video_platform === 'dailymotion') {
            const embedUrl = `https://www.dailymotion.com/embed/video/${videoData.video_id}?autoplay=1`;
            playerHtml = `<iframe src="${embedUrl}" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: none;" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>`;
        } else if (videoData.video_platform === 'direct') {
            // IMPORTANT: position: absolute is REQUIRED because parent has height:0 and padding-bottom
            playerHtml = `<video controls autoplay style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: contain;"><source src="${videoData.video_url}" type="video/mp4">Your browser does not support the video tag.</video>`;
        }

        // Inject player
        lightboxVideoEl.innerHTML = playerHtml;

        // Set Text
        lightboxTitle.textContent = videoData.title || 'Untitled Video';
        lightboxDesc.textContent = videoData.description || '';

        lightbox.classList.add('active');
    }





</script>