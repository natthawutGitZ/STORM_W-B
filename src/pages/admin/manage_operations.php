<?php
require_once ROOT_PATH . '/includes/db.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/bot_api.php';
require_once ROOT_PATH . '/includes/admin_log.php';

if (!isAdmin()) {
    redirect('../login.php');
}

$pageTitle = "Operations Manager";
$api = new BotAPI();
$channels = $api->getChannels();
$roles = $api->getRoles();
$message = '';
$error = '';

/**
 * Helper function to check if URL is local and convert to base64
 */
function convertLocalImageToBase64($imageUrl)
{
    if (empty($imageUrl)) {
        return ['base64' => '', 'filename' => ''];
    }

    $isLocal = (
        strpos($imageUrl, 'localhost') !== false ||
        strpos($imageUrl, '127.0.0.1') !== false ||
        strpos($imageUrl, '/assets/uploads/') !== false ||
        !preg_match('/^https?:\/\//', $imageUrl)
    );

    if (!$isLocal) {
        return ['base64' => '', 'filename' => ''];
    }

    if (strpos($imageUrl, '/assets/uploads/') !== false) {
        $filename = basename($imageUrl);
        $directPath = dirname(__DIR__) . '/assets/uploads/' . $filename;

        if (file_exists($directPath)) {
            return [
                'base64' => base64_encode(file_get_contents($directPath)),
                'filename' => $filename
            ];
        }
    }

    $urlPath = parse_url($imageUrl, PHP_URL_PATH);
    if (!$urlPath) {
        $urlPath = $imageUrl;
    }

    $possiblePaths = [
        $_SERVER['DOCUMENT_ROOT'] . $urlPath,
        '/var/www/html' . $urlPath,
        dirname(__DIR__) . $urlPath,
    ];

    foreach ($possiblePaths as $localPath) {
        if (file_exists($localPath)) {
            return [
                'base64' => base64_encode(file_get_contents($localPath)),
                'filename' => basename($localPath)
            ];
        }
    }

    return ['base64' => '', 'filename' => ''];
}

/**
 * Helper function to fetch dynamic ngrok public URL
 */
function getNgrokPublicUrl()
{
    static $cachedUrl = null;

    if ($cachedUrl !== null) {
        return $cachedUrl;
    }

    try {
        $apiUrl = 'http://ngrok:4040/api/tunnels';
        $context = stream_context_create([
            'http' => [
                'timeout' => 2,
                'ignore_errors' => true
            ]
        ]);

        $response = @file_get_contents($apiUrl, false, $context);

        if ($response === false) {
            return null;
        }

        $data = json_decode($response, true);

        if (!empty($data['tunnels'])) {
            foreach ($data['tunnels'] as $tunnel) {
                if ($tunnel['proto'] === 'https') {
                    $cachedUrl = $tunnel['public_url'];
                    return $cachedUrl;
                }
            }
        }
        return null;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Convert image URL to publicly accessible URL
 */
function saveEventImageToPublic($imageUrl)
{
    if (empty($imageUrl)) {
        return '';
    }

    if (strpos($imageUrl, 'http') === 0) {
        return $imageUrl;
    }

    $hostname = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $isDocker = (
        strpos($hostname, 'localhost') !== false ||
        strpos($hostname, '127.0.0.1') !== false ||
        strpos($hostname, 'ngrok-free.dev') !== false ||
        strpos($hostname, 'ngrok.io') !== false ||
        getenv('DOCKER_CONTAINER') !== false ||
        file_exists('/.dockerenv')
    );

    if ($isDocker) {
        $ngrokUrl = getNgrokPublicUrl();
        if ($ngrokUrl) {
            return $ngrokUrl . $imageUrl;
        }
        return ''; 
    } else {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        return "$protocol://$host" . $imageUrl;
    }
}

// Generate UUID
function generate_uuid()
{
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

// Handle POST Requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'create_event') {
            $eventImageUrl = $_POST['event_image'] ?? '';
            $publicImageUrl = '';

            if (!empty($eventImageUrl)) {
                $publicImageUrl = saveEventImageToPublic($eventImageUrl);
            }

            $eventId = !empty($_POST['event_id']) ? $_POST['event_id'] : generate_uuid();
            $reminderMinutes = [];
            if (!empty($_POST['reminder_15'])) $reminderMinutes[] = '15';
            if (!empty($_POST['reminder_30'])) $reminderMinutes[] = '30';
            if (!empty($_POST['reminder_60'])) $reminderMinutes[] = '60';
            $reminderStr = implode(',', $reminderMinutes);

            $eventData = [
                'event_id' => $eventId,
                'title' => $_POST['event_title'],
                'story' => $_POST['event_story'] ?? '',
                'type' => $_POST['event_type'] ?? 'other',
                'location' => [
                    'platform' => 'Discord',
                    'detail' => $_POST['event_location'] ?? ''
                ],
                'start_time' => ($_POST['event_date'] ?? date('Y-m-d')) . 'T' . ($_POST['event_time'] ?? '00:00') . ':00+07:00',
                'category' => $_POST['event_type'] ?? 'other',
                'requirements' => [
                    'mods' => $_POST['event_requirements'] ?? ''
                ],
                'image' => $publicImageUrl,
                'visibility' => [
                    'channel_id' => $_POST['target_channel'],
                    'pin_message' => !empty($_POST['pin_message'])
                ],
                'reminder_minutes' => $reminderStr,
                'ping_role_id' => $_POST['ping_role_id'] ?? ''
            ];

            $result = $api->createEvent($eventData);
            if ($result['success']) {
                $message = "Operation saved! ID: " . $result['data']['event_id'];
                logAdminAction($pdo, 'create_event', 'event', null, ['title' => $_POST['event_title'] ?? '', 'event_id' => $result['data']['event_id']]);
            } else {
                $error = "Failed to create operation: " . $result['error'];
            }

        } elseif ($_POST['action'] === 'cancel_event') {
            $eventId = $_POST['event_id'];
            if ($api->cancelEvent($eventId)) {
                $message = "Operation cancelled successfully.";
                logAdminAction($pdo, 'cancel_event', 'event', null, ['event_id' => $eventId]);
            } else {
                $error = "Failed to cancel operation.";
            }
        }
    }
}

// Fetch Active Events
$activeEvents = $api->getActiveEvents();

include ROOT_PATH . '/admin/includes/admin_header.php';
?>

<!-- Flatpickr -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<!-- Add sweet alert for modals if needed, though header might have it -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="/assets/css/bot_controls.css?v=<?php echo time(); ?>">

<div class="dashboard-container">
    <div style="display:flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <h1 style="color: #c5a059; font-family: 'Teko', sans-serif; font-size: 2rem; margin: 0;"><i class="fas fa-calendar-alt"></i> Operations Manager</h1>
            <p style="color: #aaa; margin: 0;">Manage and schedule live server operations and events.</p>
        </div>
        <button onclick="toggleEventForm()" style="background: transparent; color: #fff; border: 1px solid #c5a059; padding: 5px 15px; cursor: pointer; border-radius: 4px; transition: all 0.3s;" onmouseover="this.style.background='#c5a059'; this.style.color='#000';" onmouseout="this.style.background='transparent'; this.style.color='#fff';">
            + New Operation
        </button>
    </div>

    <?php if ($message): ?>
        <script>Swal.fire({ title: 'Success!', text: '<?php echo addslashes($message); ?>', icon: 'success', background: '#151515', color: '#fff' });</script>
    <?php endif; ?>
    <?php if ($error): ?>
        <script>Swal.fire({ title: 'Error!', text: '<?php echo addslashes($error); ?>', icon: 'error', background: '#151515', color: '#fff' });</script>
    <?php endif; ?>

    <!-- Active Events List -->
    <div id="active-events-list">
        <div class="loading-state" style="text-align: center; padding: 40px; color: #b5bac1;">
            <i class="fas fa-spinner fa-spin" style="font-size: 2rem;"></i>
            <p style="margin-top: 10px;">Loading live operations...</p>
        </div>
    </div>
</div>

<!-- Create Event Form (Initially Hidden) -->
<div id="event-form-overlay" class="staff-modal-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); backdrop-filter: blur(5px); z-index: 1000; align-items: center; justify-content: center;">
    <div class="staff-modal-content" style="background: rgba(15,15,15,0.7); backdrop-filter: blur(25px); border: 1px solid rgba(197,160,89,0.2); width: 90%; max-width: 600px; border-radius: 16px; max-height: 90vh; overflow-y: auto;">
        
        <div class="staff-modal-header" style="background: rgba(0,0,0,0.3); padding: 20px; border-bottom: 1px solid rgba(255,255,255,0.1); display: flex; justify-content: space-between; align-items: center;">
            <h2 style="margin: 0; color: #c5a059; font-size: 1.2rem; font-family: 'Teko', sans-serif; text-transform: uppercase;">Create New Operation</h2>
            <span class="staff-modal-close" onclick="toggleEventForm()" style="color: #aaa; cursor: pointer; font-size: 1.5rem;">&times;</span>
        </div>

        <div class="staff-modal-body" style="padding: 25px;">
            <form method="POST" action="" class="modern-form" id="createEventForm">
                <input type="hidden" name="action" value="create_event">
                <input type="hidden" name="event_id" id="input_event_id">

                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="color: #c5a059; display: block; margin-bottom: 5px;">Operation Title *</label>
                    <input type="text" name="event_title" class="modern-input" required placeholder="Ex: Operation Salamander" style="width: 100%; padding: 10px; background: rgba(0,0,0,0.5); border: 1px solid rgba(255,255,255,0.1); color: #fff; border-radius: 4px;">
                </div>

                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="color: #c5a059; display: block; margin-bottom: 5px;">Description</label>
                    <textarea name="event_story" class="modern-input" rows="3" placeholder="Briefing details..." style="width: 100%; padding: 10px; background: rgba(0,0,0,0.5); border: 1px solid rgba(255,255,255,0.1); color: #fff; border-radius: 4px;"></textarea>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                    <div class="form-group">
                        <label style="color: #c5a059; display: block; margin-bottom: 5px;">Date *</label>
                        <input type="text" name="event_date" class="modern-input flatpickr-date" placeholder="Select Date" required style="width: 100%; padding: 10px; background: rgba(0,0,0,0.5); border: 1px solid rgba(255,255,255,0.1); color: #fff; border-radius: 4px;">
                    </div>
                    <div class="form-group">
                        <label style="color: #c5a059; display: block; margin-bottom: 5px;">Time *</label>
                        <input type="text" name="event_time" class="modern-input flatpickr-time" placeholder="Select Time" required style="width: 100%; padding: 10px; background: rgba(0,0,0,0.5); border: 1px solid rgba(255,255,255,0.1); color: #fff; border-radius: 4px;">
                    </div>
                    <div class="form-group">
                        <label style="color: #c5a059; display: block; margin-bottom: 5px;">Duration (Hrs)</label>
                        <input type="number" name="event_duration" class="modern-input" placeholder="2" min="0.5" step="0.5" style="width: 100%; padding: 10px; background: rgba(0,0,0,0.5); border: 1px solid rgba(255,255,255,0.1); color: #fff; border-radius: 4px;">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="color: #c5a059; display: block; margin-bottom: 5px;">Target Channel *</label>
                    <select name="target_channel" required style="width: 100%; padding: 10px; background: rgba(0,0,0,0.5); border: 1px solid rgba(255,255,255,0.1); color: #fff; border-radius: 4px;">
                        <option value="">Select Channel</option>
                        <?php foreach($channels as $c): ?>
                            <option value="<?php echo htmlspecialchars($c['id']); ?>">#<?php echo htmlspecialchars($c['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="color: #c5a059; display: block; margin-bottom: 5px;">Event Image URL (Optional)</label>
                    <input type="text" name="event_image" id="input_event_image" placeholder="Image URL..." style="width: 100%; padding: 10px; background: rgba(0,0,0,0.5); border: 1px solid rgba(255,255,255,0.1); color: #fff; border-radius: 4px;">
                </div>

                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="button" onclick="toggleEventForm()" style="flex: 1; padding: 12px; background: rgba(255,255,255,0.1); color: #fff; border: 1px solid rgba(255,255,255,0.2); border-radius: 4px; cursor: pointer;">Cancel</button>
                    <button type="submit" style="flex: 1; padding: 12px; background: #c5a059; color: #000; font-weight: bold; border: none; border-radius: 4px; cursor: pointer;">Save Operation</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        fetchEvents();
        setInterval(fetchEvents, 5000); 

        flatpickr(".flatpickr-date", {
            dateFormat: "Y-m-d",
            minDate: "today",
            theme: "dark"
        });
        flatpickr(".flatpickr-time", {
            enableTime: true,
            noCalendar: true,
            dateFormat: "H:i",
            time_24hr: true,
            theme: "dark"
        });
    });

    function toggleEventForm() {
        const overlay = document.getElementById('event-form-overlay');
        overlay.style.display = overlay.style.display === 'none' ? 'flex' : 'none';
        if(overlay.style.display === 'flex') {
            document.getElementById('createEventForm').reset();
            document.getElementById('input_event_id').value = '';
        }
    }

    async function fetchEvents() {
        try {
            const response = await fetch('api/get_events.php');
            const data = await response.json();
            const container = document.getElementById('active-events-list');

            if (!data.events || data.events.length === 0) {
                container.innerHTML = `
                <div style="grid-column: 1 / -1; text-align: center; padding: 40px; color: #aaa;">
                    <i class="fas fa-calendar-alt" style="font-size: 3rem; margin-bottom: 15px; opacity: 0.5;"></i>
                    <p>No active operations. Create one to get started.</p>
                </div>`;
                return;
            }

            container.innerHTML = `<div class="campaign-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px; margin-top: 20px;">` 
                + data.events.map(event => renderEventCard(event)).join('') 
                + `</div>`;
        } catch (error) {
            console.error('Error fetching events:', error);
        }
    }

    function renderEventCard(event) {
        const startDate = new Date(event.start_time);
        const now = new Date();
        const diffMs = startDate - now;
        const diffHours = Math.floor(diffMs / (1000 * 60 * 60));

        let timeText = '';
        if (diffMs < 0) {
            timeText = `<span style="color: #e74c3c">Ended</span>`;
        } else if (diffHours < 24) {
            timeText = `in ${diffHours} hours`;
        } else {
            const days = Math.floor(diffHours / 24);
            timeText = `in ${days} days`;
        }

        const dateStr = startDate.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
        
        const rsvps = event.rsvps || [];
        const accepted = rsvps.filter(r => r.choice === 'going');
        
        const imageHtml = event.image 
            ? `<img src="${event.image}" style="width: 100%; height: 200px; object-fit: cover; border-bottom: 1px solid rgba(197,160,89,0.2);">`
            : `<div style="width: 100%; height: 200px; background: #222; display: flex; align-items: center; justify-content: center; border-bottom: 1px solid rgba(197,160,89,0.2);"><i class="fas fa-image" style="font-size: 3rem; color: #444;"></i></div>`;

        return `
            <div style="background: rgba(15, 15, 15, 0.7); border: 1px solid rgba(197, 160, 89, 0.2); border-radius: 12px; overflow: hidden; display: flex; flex-direction: column;">
                ${imageHtml}
                <div style="padding: 20px; flex: 1;">
                    <h3 style="margin: 0 0 10px 0; color: #fff; font-size: 1.2rem;">${event.title}</h3>
                    <div style="color: #aaa; font-size: 0.9rem; margin-bottom: 10px;">
                        <i class="far fa-clock"></i> ${dateStr} (${timeText})
                    </div>
                    <p style="color: #ccc; font-size: 0.9rem; margin: 0 0 15px 0; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;">
                        ${event.story || 'No description provided.'}
                    </p>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <span style="background: rgba(67, 181, 129, 0.2); color: #43b581; padding: 4px 8px; border-radius: 4px; font-size: 0.8rem; font-weight: bold;">
                            <i class="fas fa-users"></i> ${accepted.length} attending
                        </span>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <button onclick='editEvent(${JSON.stringify(event).replace(/'/g, "&#39;")})' style="flex: 1; padding: 8px; background: rgba(255,255,255,0.1); color: #fff; border: 1px solid rgba(255,255,255,0.2); border-radius: 4px; cursor: pointer;">Edit</button>
                        <form method="POST" onsubmit="return confirm('Are you sure you want to cancel and delete this operation?');" style="margin: 0; flex: 1;">
                            <input type="hidden" name="action" value="cancel_event">
                            <input type="hidden" name="event_id" value="${event.event_id}">
                            <button type="submit" style="width: 100%; padding: 8px; background: rgba(237, 66, 69, 0.2); color: #ed4245; border: 1px solid rgba(237, 66, 69, 0.3); border-radius: 4px; cursor: pointer;">Delete</button>
                        </form>
                    </div>
                </div>
            </div>`;
    }

    function editEvent(eventData) {
        document.getElementById('event-form-overlay').style.display = 'flex';
        
        const form = document.getElementById('createEventForm');
        form.querySelector('[name="event_title"]').value = eventData.title || '';
        form.querySelector('[name="event_story"]').value = eventData.story || '';
        form.querySelector('#input_event_id').value = eventData.event_id || '';
        form.querySelector('#input_event_image').value = eventData.image || '';

        if (eventData.start_time) {
            const dt = new Date(eventData.start_time);
            const iso = dt.toISOString();
            const parts = iso.split('T');

            if (document.querySelector('[name="event_date"]')._flatpickr) {
                document.querySelector('[name="event_date"]')._flatpickr.setDate(parts[0]);
            }
            if (document.querySelector('[name="event_time"]')._flatpickr) {
                const hours = String(dt.getHours()).padStart(2, '0');
                const minutes = String(dt.getMinutes()).padStart(2, '0');
                document.querySelector('[name="event_time"]')._flatpickr.setDate(`${hours}:${minutes}`);
            }
        }
        
        // Target channel can't be easily pre-filled without mapping ID, maybe skipped for basic edit if not matched perfectly in options.
    }
</script>

</body>
</html>


