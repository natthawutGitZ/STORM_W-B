<?php include ROOT_PATH . '/includes/header.php'; ?>

<style>
    /* Modern UI & Glassmorphism */
    :root {
        --glass-bg: rgba(20, 20, 20, 0.7);
        --glass-border: rgba(255, 255, 255, 0.08);
        --glass-blur: blur(12px);
    }



    .media-hero {
        background: linear-gradient(135deg, rgba(10, 10, 10, 0.95) 0%, rgba(20, 20, 20, 0.9) 100%);
        padding: 6rem 2rem 2rem;
        text-align: center;
        margin-bottom: 2rem;
        border-bottom: 1px solid var(--glass-border);
        position: relative;
        backdrop-filter: blur(10px);
    }

    .media-hero h1 {
        font-size: 2.5rem;
        margin-bottom: 0.5rem;
        background: linear-gradient(180deg, #fff 0%, rgba(255, 255, 255, 0.7) 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        font-weight: 700;
        letter-spacing: -0.02em;
        /* Tighter letter spacing for Next.js aesthetic */
    }

    .filter-bar {
        max-width: 1200px;
        margin: 2rem auto 0;
        display: grid;
        grid-template-columns: 2fr 1fr 1fr auto;
        gap: 1rem;
        padding: 0 1rem;
    }

    .filter-input,
    .filter-select {
        background: rgba(0, 0, 0, 0.4);
        border: 1px solid rgba(255, 255, 255, 0.12);
        border-radius: 8px;
        padding: 0.75rem 1rem;
        color: #fff;
        font-size: 0.95rem;
        transition: all 0.2s ease;
        backdrop-filter: blur(10px);
    }

    .filter-input:focus,
    .filter-select:focus {
        outline: none;
        border-color: rgba(255, 255, 255, 0.3);
        background: rgba(0, 0, 0, 0.6);
        box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.1);
    }

    .filter-input::placeholder {
        color: rgba(255, 255, 255, 0.4);
    }

    .filter-select option {
        background: #1a1a1a;
        color: #fff;
    }

    .filter-btn {
        background: #fff;
        color: #000;
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 8px;
        padding: 0.75rem 1.5rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        letter-spacing: 0.5px;
        font-size: 0.9rem;
    }

    .filter-btn:hover {
        background: #e6e6e6;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(255, 255, 255, 0.1);
    }

    @media (max-width: 768px) {
        .filter-bar {
            grid-template-columns: 1fr;
        }

        .media-hero h1 {
            font-size: 2rem;
        }
    }

    .media-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 2rem;
        padding: 2rem 4rem;
        max-width: 1600px;
        margin: 0 auto;
    }

    .media-item {
        position: relative;
        border-radius: 12px;
        overflow: hidden;
        background: var(--secondary-color);
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
        transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
        cursor: pointer;
        border: 1px solid rgba(255, 255, 255, 0.08);
        aspect-ratio: 16/10;
        display: block;
        text-decoration: none;
    }

    .media-item:hover {
        transform: translateY(-6px);
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4),
            0 0 20px rgba(255, 255, 255, 0.05);
        border-color: rgba(255, 255, 255, 0.2);
    }

    .media-item img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
        transition: transform 0.6s cubic-bezier(0.25, 0.8, 0.25, 1);
    }

    .media-item:hover img {
        transform: scale(1.08);
    }

    .media-overlay {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        background: linear-gradient(to top, rgba(0, 0, 0, 0.9) 0%, rgba(0, 0, 0, 0.4) 60%, transparent 100%);
        padding: 2.5rem 1.5rem 1.5rem;
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .media-item:hover .media-overlay {
        opacity: 1;
    }

    .media-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: #fff;
        margin-bottom: 0.5rem;
        letter-spacing: 0.3px;
        transform: translateY(10px);
        opacity: 0;
        transition: all 0.3s ease 0.05s;
    }

    .media-description {
        font-size: 0.9rem;
        color: rgba(255, 255, 255, 0.7);
        line-height: 1.5;
        transform: translateY(10px);
        opacity: 0;
        transition: all 0.3s ease 0.1s;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .media-item:hover .media-title,
    .media-item:hover .media-description {
        transform: translateY(0);
        opacity: 1;
    }

    .media-badge {
        position: absolute;
        top: 1rem;
        right: 1rem;
        background: rgba(0, 0, 0, 0.5);
        color: #fff;
        padding: 0.4rem 0.8rem;
        border-radius: 6px;
        font-size: 0.7rem;
        font-weight: 600;
        letter-spacing: 1px;
        backdrop-filter: blur(8px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        z-index: 10;
        transition: all 0.3s ease;
    }

    .media-item:hover .media-badge {
        background: rgba(255, 255, 255, 0.1);
        border-color: rgba(255, 255, 255, 0.3);
    }

    .video-badge {
        color: #fff;
        /* Neutral white instead of bright green for a cleaner look */
    }

    .album-badge {
        color: #fff;
        /* Neutral white */
    }

    .media-count {
        position: absolute;
        bottom: 1.5rem;
        right: 1.5rem;
        font-size: 0.9rem;
        color: rgba(255, 255, 255, 0.8);
        z-index: 10;
        font-weight: 600;
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .media-item:hover .media-count {
        opacity: 1;
    }

    /* Modal Styles (Matches Login Modal) */
    .album-modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.4);
        /* Lighter dark overlay */
        backdrop-filter: blur(5px);
        z-index: 9000;
        display: none;
        justify-content: center;
        align-items: center;
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .album-modal-overlay.active {
        opacity: 1;
    }

    .album-modal {
        background: rgba(20, 20, 20, 0.3);
        /* High transparency */
        backdrop-filter: blur(25px);
        /* Strong blur */
        -webkit-backdrop-filter: blur(25px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 24px;
        width: 100%;
        max-width: 1400px;
        /* Wider for gallery feel */
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 40px 80px rgba(0, 0, 0, 0.6), inset 0 0 0 1px rgba(255, 255, 255, 0.05);
        display: flex;
        flex-direction: column;
        color: #fff;
        transform: scale(0.95);
        transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
    }

    .album-modal-overlay.active .album-modal {
        transform: scale(1);
    }

    .album-modal-header {
        padding: 2rem 3rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        position: sticky;
        top: 0;
        background: rgba(20, 20, 20, 0.4);
        /* Slight tint for legibility */
        backdrop-filter: blur(15px);
        z-index: 10;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .album-info h2 {
        font-size: 2rem;
        margin: 0;
        background: linear-gradient(90deg, #fff, #ccc);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        font-weight: 700;
        letter-spacing: -0.5px;
    }

    /* User requested to remove text - Hiding description */
    .album-info p {
        display: none;
    }

    .close-modal-btn {
        background: rgba(255, 255, 255, 0.1);
        border: none;
        color: #fff;
        font-size: 1.2rem;
        cursor: pointer;
        transition: all 0.2s ease;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .close-modal-btn:hover {
        background: var(--accent-color);
        color: #000;
        transform: rotate(90deg);
    }

    .album-modal-body {
        padding: 3rem;
    }

    .album-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 2rem;
    }

    .album-grid-item {
        position: relative;
        aspect-ratio: 16/9;
        border-radius: 16px;
        overflow: hidden;
        cursor: pointer;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
        border: 1px solid rgba(255, 255, 255, 0.05);
    }

    .album-grid-item:hover {
        transform: translateY(-5px) scale(1.02);
        z-index: 2;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
        border-color: rgba(255, 255, 255, 0.2);
    }

    .album-grid-item img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.6s ease;
    }

    .album-grid-item:hover img {
        transform: scale(1.05);
    }

    /* Lightbox Styles */
    .lightbox {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.98);
        /* Darker backdrop for lightbox on top of modal */
        backdrop-filter: blur(20px);
        z-index: 10000;
        display: none;
        justify-content: center;
        align-items: center;
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .lightbox.active {
        display: flex;
        opacity: 1;
    }

    .lightbox-content {
        max-width: 90%;
        max-height: 85vh;
        position: relative;
        border-radius: 8px;
        box-shadow: 0 0 50px rgba(0, 0, 0, 0.5);
        animation: zoomIn 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
    }

    @keyframes zoomIn {
        from {
            transform: scale(0.9);
            opacity: 0;
        }

        to {
            transform: scale(1);
            opacity: 1;
        }
    }

    /* Video Container Styles */
    #lightbox-video {
        position: relative;
        width: 80vw;
        max-width: 1200px;
        aspect-ratio: 16 / 9;
        height: auto;
        background-color: #000;
        border-radius: 8px;
        margin: 0 auto;
        display: none;
        z-index: 50;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
    }

    #lightbox-video iframe,
    #lightbox-video video {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        border: none;
        border-radius: 8px;
    }

    /* Fallback for aspect-ratio support */
    @supports not (aspect-ratio: 16 / 9) {
        #lightbox-video {
            height: 0;
            padding-bottom: 56.25%;
        }
    }

    .lightbox img {
        max-width: 100%;
        max-height: 85vh;
        display: block;
        border-radius: 4px;
        border: 1px solid rgba(255, 255, 255, 0.1);
    }

    .lightbox-close {
        position: absolute;
        top: 2rem;
        right: 2rem;
        color: #fff;
        font-size: 2rem;
        cursor: pointer;
        transition: all 0.3s ease;
        z-index: 10001;
        width: 50px;
        height: 50px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 50%;
    }

    .lightbox-close:hover {
        background: rgba(255, 255, 255, 0.2);
        transform: rotate(90deg);
        color: var(--accent-color);
    }

    .lightbox-nav {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        color: #fff;
        font-size: 2rem;
        cursor: pointer;
        padding: 1.5rem;
        transition: all 0.3s ease;
        background: rgba(0, 0, 0, 0.3);
        border-radius: 50%;
        width: 60px;
        height: 60px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .lightbox-nav:hover {
        background: var(--accent-color);
        color: #000;
    }

    .lightbox-prev {
        left: 2rem;
    }

    .lightbox-next {
        right: 2rem;
    }

    .lightbox-info {
        position: absolute;
        bottom: 2rem;
        left: 0;
        right: 0;
        text-align: center;
        color: #fff;
        pointer-events: none;
    }

    .lightbox-title {
        font-size: 1.5rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.8);
    }

    .lightbox-desc {
        font-size: 1rem;
        color: #ddd;
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.8);
    }

    @media (max-width: 768px) {
        .media-hero h1 {
            font-size: 2.5rem;
        }

        .media-grid {
            grid-template-columns: 1fr;
            gap: 1.5rem;
            padding: 1rem;
        }

        .media-item {
            aspect-ratio: 16/9;
        }

        .lightbox-nav {
            width: 40px;
            height: 40px;
            font-size: 1.2rem;
            padding: 0.5rem;
        }

        .lightbox-prev {
            left: 1rem;
        }

        .lightbox-next {
            right: 1rem;
        }
    }
</style>

<?php
require_once ROOT_PATH . '/includes/db.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/track_pageview.php';
trackPageView($pdo, 'Media Gallery');

$page_title = "Media Gallery";
$page_desc = "Exclusive footage from operations, training exercises, and tactical demonstrations";

// === ROOT VIEW QUERY ===
$sql = "
    SELECT 
        'album' as item_type,
        a.id, 
        a.title, 
        a.description, 
        m.filename, 
        a.created_at as sort_date,
        (SELECT COUNT(*) FROM media_gallery WHERE album_id = a.id) as item_count,
        0 as is_video,
        NULL as video_id,
        NULL as video_platform,
        NULL as video_url
    FROM media_albums a
    LEFT JOIN media_gallery m ON a.cover_image_id = m.id
    WHERE a.status = 'published'
    
    UNION ALL 
    
    SELECT 
        'media' as item_type,
        m.id, 
        m.title, 
        m.description, 
        m.filename, 
        m.uploaded_at as sort_date,
        0 as item_count,
        m.is_video,
        m.video_id,
        m.video_platform,
        m.video_url
    FROM media_gallery m
    WHERE m.album_id IS NULL 
    
    ORDER BY sort_date DESC
";

$stmt = $pdo->query($sql);
$items = $stmt->fetchAll();

// Fetch categories for filter
$categoriesStmt = $pdo->query("SELECT * FROM media_categories ORDER BY name");
$categories = $categoriesStmt->fetchAll();

// Fetch albums for filter
$albumsStmt = $pdo->query("SELECT id, title FROM media_albums WHERE status = 'published' ORDER BY title");
$albums = $albumsStmt->fetchAll();
?>

<div class="media-hero">
    <h1>MEDIA GALLERY</h1>

    <div class="filter-bar">
        <input type="text" id="searchInput" class="filter-input" placeholder="🔍 Search media...">

        <select id="categoryFilter" class="filter-select">
            <option value="">All Categories</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
            <?php endforeach; ?>
        </select>

        <select id="albumFilter" class="filter-select">
            <option value="">All Items</option>
            <option value="no-album">No Album</option>
            <?php foreach ($albums as $album): ?>
                <option value="<?php echo $album['id']; ?>"><?php echo htmlspecialchars($album['title']); ?></option>
            <?php endforeach; ?>
        </select>

        <button class="filter-btn" onclick="applyFilters()">
            <i class="fas fa-filter"></i> Filter
        </button>
    </div>
</div>

<!-- MAIN GRID (Root View) -->
<div class="media-grid">
    <?php foreach ($items as $index => $item): ?>
        <?php
        $isAlbum = ($item['item_type'] === 'album');
        $filename = $item['filename'] ?: 'default_album.jpg';
        $badgeClass = $isAlbum ? 'album-badge' : ($item['is_video'] ? 'video-badge' : '');
        $badgeText = $isAlbum ? 'ALBUM' : ($item['is_video'] ? 'VIDEO' : 'PHOTO');
        ?>

        <?php if ($isAlbum): ?>
            <!-- ALBUM ITEM (Opens Modal) -->
            <div class="media-item" onclick="openAlbum(<?php echo $item['id']; ?>)">
                <div class="media-badge <?php echo $badgeClass; ?>">
                    <?php echo $badgeText; ?>
                </div>

                <img src="/assets/images/gallery/thumbs/<?php echo htmlspecialchars($filename); ?>"
                    alt="<?php echo htmlspecialchars($item['title']); ?>" loading="lazy">

                <div class="media-count">
                    <i class="fas fa-images"></i> <?php echo $item['item_count']; ?> Items
                </div>

                <div class="media-overlay">
                    <div class="media-title"><?php echo htmlspecialchars($item['title']); ?></div>
                    <div class="media-description"><?php echo htmlspecialchars($item['description']); ?></div>
                </div>
            </div>

        <?php else: ?>
            <!-- STANDALONE MEDIA ITEM (Opens Lightbox from Root List) -->
            <div class="media-item" onclick="openLightbox('<?php echo $item['id']; ?>', 'root')">
                <div class="media-badge <?php echo $badgeClass; ?>">
                    <?php echo $badgeText; ?>
                </div>

                <img src="/assets/images/gallery/thumbs/<?php echo htmlspecialchars($filename); ?>"
                    alt="<?php echo htmlspecialchars($item['title']); ?>" loading="lazy">

                <div class="media-overlay">
                    <div class="media-title"><?php echo htmlspecialchars($item['title']); ?></div>
                    <div class="media-description"><?php echo htmlspecialchars($item['description']); ?></div>
                </div>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
</div>

<?php if (empty($items)): ?>
    <div style="text-align: center; padding: 6rem 2rem; color: var(--text-muted);">
        <i class="fas fa-images" style="font-size: 4rem; margin-bottom: 1.5rem; opacity: 0.3;"></i>
        <p style="font-size: 1.2rem;">No media found.</p>
    </div>
<?php endif; ?>

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

<!-- LIGHTBOX -->
<div id="lightbox" class="lightbox" onclick="if(event.target === this) closeLightbox()">
    <div class="lightbox-close" onclick="closeLightbox()">&times;</div>
    <div class="lightbox-nav lightbox-prev" onclick="changeMedia(-1)"><i class="fas fa-chevron-left"></i></div>
    <div class="lightbox-nav lightbox-next" onclick="changeMedia(1)"><i class="fas fa-chevron-right"></i></div>

    <div class="lightbox-content">
        <img id="lightbox-img" src="" alt="">
        <div id="lightbox-video"></div>
    </div>

    <div class="lightbox-info">
        <div id="lightbox-title" class="lightbox-title"></div>
        <div id="lightbox-desc" class="lightbox-desc"></div>
    </div>
</div>

<script>
    // === DATA ===
    // Prepare media data for Lightbox
    let rootItems = [];
    let albumItems = {};

    <?php foreach ($items as $item): ?>
        <?php if ($item['item_type'] === 'media'): ?>
            rootItems.push({
                id: '<?php echo $item['id']; ?>',
                src: '/assets/images/gallery/full/<?php echo ($item['filename'] ?: 'default.jpg'); ?>',
                title: <?php echo json_encode($item['title'] ?: 'Untitled'); ?>,
                description: <?php echo json_encode($item['description'] ?: ''); ?>,
                type: '<?php echo $item['is_video'] ? 'video' : 'photo'; ?>',
                video_platform: '<?php echo $item['video_platform'] ?: ''; ?>',
                video_url: '<?php echo $item['video_url'] ?: ''; ?>'
            });
        <?php endif; ?>
    <?php endforeach; ?>
    const rootMediaItems = rootItems;

    // Mapping for Root
    const rootIdToIndex = {};
    rootMediaItems.forEach((item, index) => {
        rootIdToIndex[item.id] = index;
    });

    // Album Media Items (Populated dynamically)
    let albumMediaItems = [];
    let albumIdToIndex = {};

    // Current Scope ('root' or 'album')
    let currentScope = 'root';
    let currentIndex = 0;

    // === ALBUM MODAL LOGIC ===
    const albumModal = document.getElementById('albumModal');
    const albumGrid = document.getElementById('albumGrid');
    const albumLoading = document.getElementById('albumLoading');
    const modalTitle = document.getElementById('modalAlbumTitle');
    const modalDesc = document.getElementById('modalAlbumDesc');

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

        // Fetch Data
        fetch(`includes/get_album_details.php?id=${id}`)
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
                        albumIdToIndex[item.id] = index; // Note: API returns raw DB columns, ensure naming matches
                    });

                    if (albumMediaItems.length === 0) {
                        albumGrid.innerHTML = '<p style="grid-column: 1/-1; text-align: center; color: #888;">No items in this album.</p>';
                    } else {
                        // Render Items
                        albumMediaItems.forEach(item => {
                            const div = document.createElement('div');
                            div.className = 'album-grid-item';
                            // Note: 'item' properties come from PHP API: src, title, description, etc.
                            // We need to pass the ID to openLightbox
                            div.onclick = () => openLightbox(item.id, 'album');

                            div.innerHTML = `
                                <img src="${item.thumb}" alt="${item.title}" loading="lazy">
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

    function openLightbox(id, scope) {
        currentScope = scope;
        let index;

        if (scope === 'root') {
            index = rootIdToIndex[id];
        } else {
            index = albumIdToIndex[id];
        }

        if (index === undefined) return;

        currentIndex = index;
        updateLightbox();
        lightbox.classList.add('active');
        // Body overflow is already hidden if coming from album modal, but ensure it if coming from root
        document.body.style.overflow = 'hidden';
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
        }, 300);

        // If Album Modal is NOT active, restore scrolling. 
        // If Album Modal IS active, keep scrolling hidden.
        if (!albumModal.classList.contains('active')) {
            document.body.style.overflow = '';
        }
    }

    function changeMedia(direction) {
        currentIndex += direction;

        const items = currentScope === 'root' ? rootMediaItems : albumMediaItems;

        // Loop
        if (currentIndex < 0) currentIndex = items.length - 1;
        if (currentIndex >= items.length) currentIndex = 0;

        // Add fade effect
        lightboxImg.style.opacity = 0;
        setTimeout(() => {
            updateLightbox();
            lightboxImg.style.opacity = 1;
        }, 200);
    }
    function updateLightbox() {
        const items = currentScope === 'root' ? rootMediaItems : albumMediaItems;
        const item = items[currentIndex];

        const lightboxImgEl = document.getElementById('lightbox-img');
        const lightboxVideoEl = document.getElementById('lightbox-video');

        // Check if item is video (handle both root and album data structures)
        // Root items rely on 'type' === 'video', Album items might rely on is_video flag
        const isVideo = (item.type === 'video') || (item.is_video == 1);

        if (isVideo) {
            viewVideo(item);
        } else {
            // Hide video, show image
            lightboxVideoEl.style.display = 'none';
            lightboxVideoEl.innerHTML = '';
            lightboxImgEl.style.display = 'block';

            lightboxImgEl.src = item.src;
            lightboxTitle.textContent = item.title || '';
            lightboxDesc.textContent = item.description || '';
        }
    }

    function viewVideo(videoData) {
        const lightboxImgEl = document.getElementById('lightbox-img');
        const lightboxVideoEl = document.getElementById('lightbox-video');
        const lightboxTitle = document.getElementById('lightbox-title');
        const lightboxDesc = document.getElementById('lightbox-desc');

        // Hide image, show video container
        lightboxImgEl.style.display = 'none';
        lightboxVideoEl.style.display = 'block';

        // CSS handles dimensions (80vw), NO INLINE JS SIZING

        let playerHtml = '';

        // Normalize data fields (API vs Root Array)
        const platform = videoData.video_platform;
        const videoId = videoData.video_id;
        const url = videoData.video_url;

        if (platform === 'youtube') {
            const embedUrl = `https://www.youtube.com/embed/${videoId}?autoplay=1`;
            playerHtml = `<iframe src="${embedUrl}" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>`;
        } else if (platform === 'vimeo') {
            const embedUrl = `https://player.vimeo.com/video/${videoId}?autoplay=1`;
            playerHtml = `<iframe src="${embedUrl}" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>`;
        } else if (platform === 'dailymotion') {
            const embedUrl = `https://www.dailymotion.com/embed/video/${videoId}?autoplay=1`;
            playerHtml = `<iframe src="${embedUrl}" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>`;
        } else if (platform === 'direct') {
            playerHtml = `<video controls autoplay style="width: 100%; height: 100%; object-fit: contain;"><source src="${url}" type="video/mp4">Your browser does not support the video tag.</video>`;
        } else if (platform === 'tiktok') {
            window.open(url, '_blank');
            return;
        }

        lightboxVideoEl.innerHTML = playerHtml;
        lightboxTitle.textContent = videoData.title || 'Untitled Video';
        lightboxDesc.textContent = videoData.description || '';
    }

    // === FILTER FUNCTIONALITY ===
    function applyFilters() {
        const search = document.getElementById('searchInput').value;
        const category = document.getElementById('categoryFilter').value;
        const album = document.getElementById('albumFilter').value;

        const params = new URLSearchParams();
        if (search) params.append('search', search);
        if (category) params.append('category_id', category);
        if (album) params.append('album_filter', album);

        const mediaGrid = document.querySelector('.media-grid');
        mediaGrid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; padding: 4rem;"><i class="fas fa-spinner fa-spin" style="font-size: 3rem; color: var(--accent-color);"></i></div>';

        fetch(`includes/filter_media.php?${params.toString()}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    renderMediaGrid(data.items);
                } else {
                    mediaGrid.innerHTML = `<div style="grid-column: 1/-1; text-align: center; padding: 4rem; color: #ff4d4d;"><p>${data.message}</p></div>`;
                }
            })
            .catch(err => {
                console.error(err);
                mediaGrid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; padding: 4rem; color: #ff4d4d;"><p>Failed to load media</p></div>';
            });
    }

    function renderMediaGrid(items) {
        const mediaGrid = document.querySelector('.media-grid');

        if (items.length === 0) {
            mediaGrid.innerHTML = `<div style="grid-column: 1/-1; text-align: center; padding: 6rem 2rem; color: var(--text-muted);"><i class="fas fa-images" style="font-size: 4rem; margin-bottom: 1.5rem; opacity: 0.3;"></i><p style="font-size: 1.2rem;">No media found.</p></div>`;
            return;
        }

        mediaGrid.innerHTML = '';
        items.forEach(item => {
            const isAlbum = item.item_type === 'album';
            const filename = item.filename || 'default_album.jpg';
            const badgeClass = isAlbum ? 'album-badge' : (item.is_video ? 'video-badge' : '');
            const badgeText = isAlbum ? 'ALBUM' : (item.is_video ? 'VIDEO' : 'PHOTO');

            const div = document.createElement('div');
            div.className = 'media-item';

            if (isAlbum) {
                div.onclick = () => openAlbum(item.id);
                div.innerHTML = `<div class="media-badge ${badgeClass}">${badgeText}</div><img src="/assets/images/gallery/thumbs/${filename}" alt="${item.title}" loading="lazy"><div class="media-count"><i class="fas fa-images"></i> ${item.item_count} Items</div><div class="media-overlay"><div class="media-title">${item.title}</div><div class="media-description">${item.description || ''}</div></div>`;
            } else {
                div.onclick = () => openLightbox(item.id, 'root');
                div.innerHTML = `<div class="media-badge ${badgeClass}">${badgeText}</div><img src="/assets/images/gallery/thumbs/${filename}" alt="${item.title}" loading="lazy"><div class="media-overlay"><div class="media-title">${item.title}</div><div class="media-description">${item.description || ''}</div></div>`;
            }

            mediaGrid.appendChild(div);
        });
    }

    document.getElementById('searchInput').addEventListener('keypress', (e) => {
        if (e.key === 'Enter') applyFilters();
    });

    // Keyboard Navigation
    document.addEventListener('keydown', function (e) {
        if (!lightbox.classList.contains('active')) return;

        if (e.key === 'Escape') closeLightbox();
        if (e.key === 'ArrowLeft') changeMedia(-1);
        if (e.key === 'ArrowRight') changeMedia(1);
    });
</script>

<?php include ROOT_PATH . '/includes/footer.php'; ?>
