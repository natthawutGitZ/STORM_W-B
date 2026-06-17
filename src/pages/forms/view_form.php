<?php
// src/view_form.php
require_once ROOT_PATH . '/includes/db.php';
require_once ROOT_PATH . '/includes/functions.php'; // Required for auth
require_once ROOT_PATH . '/includes/track_pageview.php';
trackPageView($pdo, 'Application Form');

// Auto Sign In Check (Separate Google Login)
if (session_status() === PHP_SESSION_NONE)
    session_start();

$user = $_SESSION['google_form_user'] ?? null;

$user = $_SESSION['google_form_user'] ?? null;

// Optional Login: We no longer force redirect
// if (!$user) { ... }
// Use $user from session (it mirrors the DB structure so existing code works)

$id = $_GET['id'] ?? 0;
if (!$id)
    die("Form not found");

// Fetch Form
$stmt = $pdo->prepare("SELECT * FROM forms WHERE id = ?");
$stmt->execute([$id]);
$form = $stmt->fetch();

if (!$form)
    die("Form not found");

// Fetch Questions
$qStmt = $pdo->prepare("SELECT * FROM form_questions WHERE form_id = ? ORDER BY sort_order ASC");
$qStmt->execute([$id]);
$questions = $qStmt->fetchAll();

/**
 * Convert [ URL ] patterns to clickable buttons
 * Pattern: [ https://example.com ] or [ http://example.com/path ]
 */
function convertLinksToButtons($text) {
    // Pattern: [ url ] - URL wrapped in square brackets with optional spaces
    $pattern = '/\[\s*(https?:\/\/[^\]\s]+)\s*\]/i';
    
    return preg_replace_callback($pattern, function($matches) {
        $url = $matches[1]; // Already escaped from input
        // Extract domain or use generic label
        $parsed = parse_url($matches[1]);
        $domain = $parsed['host'] ?? 'Link';
        // Make friendly label
        $label = str_replace('www.', '', $domain);
        if (strlen($label) > 30) {
            $label = substr($label, 0, 27) . '...';
        }
        return '<a href="' . $url . '" target="_blank" class="question-link-btn"><i class="fas fa-external-link-alt"></i> ' . ucfirst($label) . '</a>';
    }, $text);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($form['title']); ?></title>
    <!-- Favicon -->
    <link rel="icon" href="assets/images/logo.png">
    <!-- Use same fonts/styles as main site or standalone -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Flatpickr -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="assets/css/forms.css">
    <style>
        /* ----------------------------------------------------------- */
        /* S3 STYLE FLATPICKR CSS (Dark Glassmorphism) */
        /* ----------------------------------------------------------- */
        .flatpickr-calendar.custom-flatpickr-glass {
            background: rgba(10, 10, 12, 0.8) !important;
            backdrop-filter: blur(40px) !important;
            -webkit-backdrop-filter: blur(40px) !important;
            border: 1px solid rgba(255, 215, 0, 0.15) !important;
            box-shadow: 0 30px 80px rgba(0, 0, 0, 0.9), 0 0 30px rgba(197, 160, 89, 0.15) !important;
            border-radius: 24px !important;
            font-family: inherit !important;
            padding: 25px !important;
            width: 360px !important;
            animation: calendarSlideIn 0.3s cubic-bezier(0.34, 1.56, 0.64, 1) !important;
            margin: 0 !important;
        }

        @keyframes calendarSlideIn {
            from {
                opacity: 0;
                transform: translateY(-10px) scale(0.95);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .flatpickr-calendar.custom-flatpickr-glass .flatpickr-months {
            margin-bottom: 20px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;
            position: relative !important;
            padding: 10px 10px !important;
        }

        .flatpickr-calendar.custom-flatpickr-glass .flatpickr-month {
            color: #c5a059 !important;
            fill: #c5a059 !important;
        }

        .flatpickr-calendar.custom-flatpickr-glass .flatpickr-current-month {
            font-size: 1.5rem !important;
            text-transform: uppercase;
            font-family: inherit !important;
            letter-spacing: 2px !important;
            color: #eecfa1 !important;
            text-shadow: 0 0 15px rgba(238, 207, 161, 0.4) !important;
            padding: 5px 0 !important;
        }

        .flatpickr-calendar.custom-flatpickr-glass .flatpickr-monthDropdown-months {
            appearance: none !important;
            background: transparent !important;
            border: none !important;
            color: #eecfa1 !important;
            font-weight: 600 !important;
            font-size: 1.5rem !important;
            font-family: inherit !important;
            cursor: pointer !important;
            padding: 0 !important;
            margin: 0 5px !important;
            text-align: right !important;
        }

        .flatpickr-calendar.custom-flatpickr-glass .flatpickr-current-month:hover {
            background: rgba(197, 160, 89, 0.2) !important;
        }

        .flatpickr-calendar.custom-flatpickr-glass .flatpickr-prev-month,
        .flatpickr-calendar.custom-flatpickr-glass .flatpickr-next-month {
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            width: 30px !important;
            height: 30px !important;
            border-radius: 50% !important;
            background: rgba(255, 255, 255, 0.1) !important;
            color: #eecfa1 !important;
            position: static !important;
            z-index: 10 !important;
            margin: 0 !important;
        }

        .flatpickr-calendar.custom-flatpickr-glass .flatpickr-prev-month:hover,
        .flatpickr-calendar.custom-flatpickr-glass .flatpickr-next-month:hover {
            background: rgba(197, 160, 89, 0.3) !important;
            transform: scale(1.1) !important;
        }

        .flatpickr-calendar.custom-flatpickr-glass .flatpickr-prev-month svg,
        .flatpickr-calendar.custom-flatpickr-glass .flatpickr-next-month svg {
            width: 14px !important;
            height: 14px !important;
            fill: #eecfa1 !important;
        }

        .flatpickr-calendar.custom-flatpickr-glass .flatpickr-current-month .numInputWrapper span.arrowUp,
        .flatpickr-calendar.custom-flatpickr-glass .flatpickr-current-month .numInputWrapper span.arrowDown {
            display: none !important;
        }

        .flatpickr-calendar.custom-flatpickr-glass .flatpickr-weekday {
            color: #aaa !important;
            font-weight: 600 !important;
            font-size: 1rem !important;
            letter-spacing: 1px;
        }

        .flatpickr-calendar.custom-flatpickr-glass .flatpickr-day {
            color: #ddd !important;
            border-radius: 50% !important;
            border: 1px solid transparent !important;
            height: 44px !important;
            line-height: 44px !important;
            margin: 2px !important;
            font-size: 1rem !important;
            transition: all 0.2s !important;
        }

        .flatpickr-calendar.custom-flatpickr-glass .flatpickr-day:hover,
        .flatpickr-calendar.custom-flatpickr-glass .flatpickr-day:focus {
            background: rgba(197, 160, 89, 0.2) !important;
            transform: scale(1.2) !important;
            box-shadow: 0 0 10px rgba(197, 160, 89, 0.4) !important;
            z-index: 2;
        }

        .flatpickr-calendar.custom-flatpickr-glass .flatpickr-day.selected,
        .flatpickr-calendar.custom-flatpickr-glass .flatpickr-day.startRange,
        .flatpickr-calendar.custom-flatpickr-glass .flatpickr-day.endRange {
            background: linear-gradient(135deg, #c5a059, #8a6e35) !important;
            border: none !important;
            color: #fff !important;
            font-weight: bold !important;
            box-shadow: 0 0 15px rgba(197, 160, 89, 0.5) !important;
            transform: scale(1.1) !important;
        }

        /* Time Picker Styling */
        .flatpickr-calendar.custom-flatpickr-glass .flatpickr-time {
            border-top: 1px solid rgba(197, 160, 89, 0.2) !important;
            margin-top: 15px !important;
            padding-top: 20px !important;
            padding-bottom: 10px !important;
            /* Add bottom padding */
            max-height: none !important;
            /* Allow growth */
            height: auto !important;
        }

        .flatpickr-calendar.custom-flatpickr-glass .flatpickr-time .numInputWrapper {
            background: rgba(0, 0, 0, 0.3) !important;
            border-radius: 12px !important;
            transition: all 0.3s ease !important;
            height: auto !important;
            /* Allow height to fit content */
        }

        .flatpickr-calendar.custom-flatpickr-glass .flatpickr-time input {
            color: #fff !important;
            font-family: inherit !important;
            font-size: 1.5rem !important;
            /* Reduced from 2rem */
            font-weight: 600 !important;
            line-height: normal !important;
            background: transparent !important;
            /* Ensure no white bg on focus */
            border: none !important;
            box-shadow: none !important;
        }

        .flatpickr-calendar.custom-flatpickr-glass .flatpickr-time input:focus,
        .flatpickr-calendar.custom-flatpickr-glass .flatpickr-time input:hover {
            background: rgba(255, 255, 255, 0.1) !important;
            border: none !important;
        }

        .flatpickr-calendar.custom-flatpickr-glass .flatpickr-time-separator {
            color: #fff !important;
            font-size: 1.5rem !important;
            /* Match input size */
        }

        .flatpickr-calendar.custom-flatpickr-glass .flatpickr-current-month .numInputWrapper {
            width: 60px !important;
            display: inline-block !important;
        }
    </style>
    <style>
        body {
            background-color: var(--form-bg);
            background-image: radial-gradient(circle at 50% 50%, rgba(197, 160, 89, 0.1), rgba(15, 15, 15, 0));
            color: var(--text-main);
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            zoom: 0.8;
        }

        .form-viewer-container {
            max-width: 640px;
            margin: 40px auto;
            padding: 0 20px;
            width: 100%;
        }

        .form-header-public {
            background: var(--form-card-bg);
            backdrop-filter: blur(12px);
            border-radius: 12px;
            border-top: 4px solid var(--action-color);
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            border: var(--form-border);
            border-top-width: 4px;
            border-top-color: var(--action-color);
        }

        .question-card {
            background: var(--form-card-bg);
            backdrop-filter: blur(12px);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 16px;
            border: var(--form-border);
        }

        .question-title {
            font-size: 1.1rem;
            margin-bottom: 16px;
            display: block;
            color: var(--text-main);
        }

        .required-star {
            color: var(--danger);
            margin-left: 4px;
        }

        /* Link Button in Question Text */
        .question-link-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, rgba(197, 160, 89, 0.2), rgba(197, 160, 89, 0.1));
            border: 1px solid rgba(197, 160, 89, 0.4);
            color: var(--action-color);
            padding: 8px 16px;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            margin: 8px 0;
        }

        .question-link-btn:hover {
            background: linear-gradient(135deg, rgba(197, 160, 89, 0.3), rgba(197, 160, 89, 0.2));
            border-color: var(--action-color);
            box-shadow: 0 4px 12px rgba(197, 160, 89, 0.3);
            transform: translateY(-1px);
            color: #fff;
        }

        .question-link-btn i {
            font-size: 0.75rem;
        }

        input[type="text"],
        input[type="date"],
        textarea,
        select {
            width: 100%;
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 12px;
            border-radius: 4px;
            color: var(--text-main);
            font-family: inherit;
        }

        input:focus,
        textarea:focus,
        select:focus {
            outline: none;
            border-color: var(--action-color);
            background: rgba(0, 0, 0, 0.5);
        }

        .radio-option,
        .checkbox-option {
            display: flex;
            align-items: center;
            padding: 8px 0;
            cursor: pointer;
            color: var(--text-main);
        }

        .radio-option input,
        .checkbox-option input {
            margin-right: 12px;
            accent-color: var(--action-color);
            width: 18px;
            height: 18px;
        }

        .submit-btn {
            background: linear-gradient(135deg, var(--action-color), #8a6e35);
            color: #fff;
            border: none;
            padding: 12px 36px;
            border-radius: 50px;
            /* Pill shape */
            font-size: 1rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(197, 160, 89, 0.4);
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            position: relative;
            overflow: hidden;
            width: auto;
            /* Allow auto width */
            min-width: 140px;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(197, 160, 89, 0.6);
            background: linear-gradient(135deg, #d4af37, #a68442);
        }

        .submit-btn:active {
            transform: translateY(1px);
        }

        .submit-btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none !important;
        }

        /* Modern Full-Screen Loading Overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(10, 10, 12, 0.95);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            animation: fadeIn 0.3s ease;
        }

        .loading-overlay.active {
            display: flex;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        .loading-content {
            text-align: center;
            animation: slideUp 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modern-spinner {
            width: 80px;
            height: 80px;
            margin: 0 auto 30px;
            position: relative;
        }

        .modern-spinner::before,
        .modern-spinner::after {
            content: '';
            position: absolute;
            border-radius: 50%;
            top: 50%;
            left: 50%;
        }

        .modern-spinner::before {
            width: 80px;
            height: 80px;
            margin-top: -40px;
            margin-left: -40px;
            border: 5px solid transparent;
            border-top-color: #c5a059;
            border-right-color: #c5a059;
            animation: spin 1.2s cubic-bezier(0.5, 0, 0.5, 1) infinite;
        }

        .modern-spinner::after {
            width: 50px;
            height: 50px;
            margin-top: -25px;
            margin-left: -25px;
            border: 4px solid transparent;
            border-bottom-color: #d4af37;
            border-left-color: #d4af37;
            animation: spin 0.8s cubic-bezier(0.5, 0, 0.5, 1) infinite reverse;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .loading-text {
            color: #eecfa1;
            font-size: 1.3rem;
            font-weight: 600;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-bottom: 10px;
            text-shadow: 0 0 20px rgba(197, 160, 89, 0.5);
        }

        .loading-subtext {
            color: #94a3b8;
            font-size: 0.9rem;
            font-weight: 400;
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 0.6;
            }

            50% {
                opacity: 1;
            }
        }

        .btn-ghost {
            background: rgba(255, 255, 255, 0.05);
            color: var(--text-muted);
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 12px 32px;
            border-radius: 50px;
            font-size: 0.95rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .btn-ghost:hover {
            background: rgba(255, 255, 255, 0.15);
            color: #fff;
            border-color: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }

        .success-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 15, 15, 0.98);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            display: none;
        }

        /* Discord User Picker Styles */
        .discord-user-picker {
            margin-top: 10px;
        }

        /* Ensure parent question-card has higher z-index when dropdown is open */
        .question-card {
            position: relative;
            z-index: 1;
        }

        .question-card.has-dropdown-open {
            z-index: 9990;
        }

        .discord-dropdown-container {
            position: relative;
            z-index: 1000;
        }

        .discord-dropdown-container.open {
            z-index: 9998;
        }

        .discord-dropdown-selected {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 16px;
            background: rgba(0, 0, 0, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .discord-dropdown-selected:hover {
            border-color: rgba(88, 101, 242, 0.5);
            background: rgba(0, 0, 0, 0.5);
        }

        .discord-selected-content {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .discord-selected-text {
            color: var(--text-muted);
            font-size: 0.95rem;
        }

        .discord-selected-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid rgba(88, 101, 242, 0.5);
        }

        .discord-dropdown-arrow {
            color: var(--text-muted);
            font-size: 0.8rem;
            transition: transform 0.3s ease;
        }

        .discord-dropdown-container.open .discord-dropdown-arrow {
            transform: rotate(180deg);
        }

        .discord-dropdown-menu {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            margin-top: 8px;
            background: rgba(15, 15, 15, 0.98);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(88, 101, 242, 0.3);
            border-radius: 12px;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.7);
            z-index: 9999;
            display: none;
            overflow: hidden;
            animation: dropdownSlide 0.2s ease-out;
        }

        @keyframes dropdownSlide {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .discord-dropdown-container.open .discord-dropdown-menu {
            display: block;
        }

        .discord-dropdown-search {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .discord-dropdown-search i {
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        .discord-dropdown-search input {
            flex: 1;
            background: transparent;
            border: none;
            color: #fff;
            font-size: 0.9rem;
            outline: none;
        }

        .discord-dropdown-list {
            max-height: 280px;
            overflow-y: auto;
            padding: 8px;
        }

        .discord-dropdown-list::-webkit-scrollbar {
            width: 6px;
        }

        .discord-dropdown-list::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.2);
        }

        .discord-dropdown-list::-webkit-scrollbar-thumb {
            background: rgba(88, 101, 242, 0.5);
            border-radius: 3px;
        }

        .discord-user-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .discord-user-item:hover {
            background: rgba(88, 101, 242, 0.2);
        }

        .discord-user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            object-fit: cover;
            background: #36393f;
        }

        .discord-user-info {
            display: flex;
            flex-direction: column;
        }

        .discord-user-name {
            color: #fff;
            font-size: 0.95rem;
            font-weight: 500;
        }

        .discord-user-username {
            color: var(--text-muted);
            font-size: 0.8rem;
        }

        .discord-loading {
            padding: 20px;
            text-align: center;
            color: var(--text-muted);
        }

        .discord-no-results {
            padding: 20px;
            text-align: center;
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        /* SweetAlert2 Custom Storm Gold Theme */
        .storm-swal-popup {
            border-radius: 16px !important;
            border: 1px solid rgba(212, 168, 83, 0.3) !important;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.6), 0 0 40px rgba(212, 168, 83, 0.15) !important;
        }

        .storm-swal-popup .swal2-title {
            font-family: 'Inter', sans-serif !important;
        }

        .storm-swal-popup .swal2-confirm {
            border-radius: 8px !important;
            font-weight: 600 !important;
            padding: 12px 24px !important;
            color: #000 !important;
            transition: all 0.3s ease !important;
        }

        .storm-swal-popup .swal2-confirm:hover {
            background: linear-gradient(135deg, #e8c06e 0%, #d4a853 100%) !important;
            transform: translateY(-2px) !important;
            box-shadow: 0 5px 15px rgba(212, 168, 83, 0.4) !important;
        }

        .storm-progress-bar {
            background: linear-gradient(90deg, #d4a853, #e8c06e) !important;
        }

        /* User Identity Strip (Google Forms Style) */
        .user-identity-strip {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            margin-bottom: 10px;
            font-size: 0.9rem;
            color: var(--text-muted);
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            padding-bottom: 15px;
        }

        .user-info-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-avatar-small {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            border: 1px solid rgba(255, 255, 255, 0.2);
            object-fit: cover;
        }

        .user-text-info {
            display: flex;
            flex-direction: column;
            line-height: 1.2;
        }

        .user-display-name {
            color: var(--text-main);
            font-weight: 500;
            font-size: 0.85rem;
        }

        .user-sub-text {
            font-size: 0.75rem;
            opacity: 0.7;
        }

        .switch-account-link {
            color: var(--action-color);
            text-decoration: none;
            margin-left: 8px;
            font-size: 0.8rem;
        }

        .switch-account-link:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>

    <div class="form-viewer-container">


        <div class="form-header-public" style="padding: 20px 30px;">
            <div id="formMainHeader">

                <?php if (!empty($form['banner_image'])): ?>
                    <img src="<?php echo htmlspecialchars($form['banner_image']); ?>" alt="Form Banner"
                        style="width: 100%; height: auto; max-height: 300px; object-fit: cover; border-radius: 8px; margin-bottom: 20px;">
                <?php endif; ?>
                <h1><?php echo htmlspecialchars($form['title']); ?></h1>
                <p style="color: #94a3b8; margin-top: 8px; white-space: pre-line;">
                    <?php echo htmlspecialchars(trim($form['description'])); ?>
                </p>
                <div style="border-top: 1px solid rgba(255,255,255,0.1); margin-top: 20px; padding-top: 15px;"></div>
            </div>

            <div style="margin-top: 0; padding-top: 0;">
                <div class="user-identity-strip" style="border-bottom: none; margin-bottom: 0; padding-bottom: 0;">
                    <?php if ($user): ?>
                        <!-- Logged In View -->
                        <div class="user-info-group">
                            <img src="<?php echo htmlspecialchars(get_avatar($user['avatar'] ?? '')); ?>"
                                class="user-avatar-small" alt="User">
                            <div class="user-text-info">
                                <span class="user-display-name">
                                    <?php echo htmlspecialchars($user['personaname'] ?: $user['username']); ?>
                                    <span
                                        class="user-sub-text">(<?php echo htmlspecialchars($user['email'] ?? $user['username']); ?>)</span>
                                </span>
                                <span class="user-sub-text text-muted">Record submitted as this user</span>
                            </div>
                            <a href="form_logout.php" class="switch-account-link">Switch Account</a>
                        </div>
                        <div class="save-status" title="Form is linked to your account">
                            <i class="fas fa-cloud text-muted"></i>
                        </div>
                    <?php else: ?>
                        <!-- Guest View -->
                        <div class="user-info-group">
                            <div class="user-text-info">
                                <span class="user-display-name" style="color: var(--text-muted);">
                                    Not signed in
                                </span>
                                <span class="user-sub-text text-muted">Sign in with Google to save your progress</span>
                            </div>
                        </div>
                        <div>
                            <a href="form_login.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>"
                                style="color: var(--action-color); text-decoration: none; font-size: 0.9rem; border: 1px solid var(--action-color); padding: 4px 12px; border-radius: 4px;">
                                Sign In
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <form id="publicForm">
            <input type="hidden" name="form_id" value="<?php echo $id; ?>">
            <input type="hidden" name="action" value="submit_response">

            <!-- Progress Bar -->
            <?php
            // Group Questions by Section
            $steps = [];
            $currentStep = ['title' => 'Start', 'questions' => []];
            foreach ($questions as $q) {
                if ($q['question_type'] === 'section') {
                    if (!empty($currentStep['questions']) || !empty($currentStep['title'])) { // Save previous step
                        $steps[] = $currentStep;
                    }
                    $currentStep = ['title' => $q['question_text'], 'description' => $q['description'] ?? '', 'questions' => []]; // Start new step
                } else {
                    $currentStep['questions'][] = $q;
                }
            }
            $steps[] = $currentStep; // Add last step
            $totalSteps = count($steps);
            ?>

            <?php if ($totalSteps > 1): ?>
                <div class="progress-container mb-5"
                    style="background: linear-gradient(135deg, rgba(197, 160, 89, 0.08) 0%, rgba(0, 0, 0, 0.2) 100%); padding: 24px; border-radius: 16px; border: 1px solid rgba(197, 160, 89, 0.15); box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <span id="stepLabel"
                            style="background: linear-gradient(135deg, #c5a059, #8a6e35); color: #000; padding: 8px 20px; border-radius: 50px; font-size: 0.8rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1.5px; box-shadow: 0 2px 8px rgba(197, 160, 89, 0.4);">
                            Step 1 / <?php echo $totalSteps; ?>
                        </span>
                        <span id="stepTitle"
                            style="color: var(--text-main); font-weight: 700; font-size: 1rem; text-transform: uppercase; letter-spacing: 1px;">
                            <?php echo htmlspecialchars($steps[0]['title']); ?>
                        </span>
                    </div>
                    <div class="progress"
                        style="height: 10px; background: rgba(0,0,0,0.4); border-radius: 50px; overflow: hidden; box-shadow: inset 0 2px 4px rgba(0,0,0,0.3);">
                        <div class="progress-bar" id="progressBar"
                            style="width: <?php echo (1 / $totalSteps) * 100; ?>%; background: linear-gradient(90deg, #c5a059, #d4af37, #c5a059); height: 100%; border-radius: 50px; transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1); box-shadow: 0 0 15px rgba(197, 160, 89, 0.6), 0 0 30px rgba(197, 160, 89, 0.3); animation: shimmer 2s infinite;">
                        </div>
                    </div>
                </div>
                <style>
                    @keyframes shimmer {

                        0%,
                        100% {
                            opacity: 1;
                        }

                        50% {
                            opacity: 0.85;
                        }
                    }
                </style>
            <?php endif; ?>

            <?php foreach ($steps as $index => $step): ?>
                <div class="form-step" id="step-<?php echo $index; ?>"
                    style="<?php echo $index === 0 ? '' : 'display:none;'; ?>">

                    <?php if ($index > 0): // Show Section Title for sub-sections ?>
                        <div class="form-header-public" style="margin-top: 20px; padding: 24px; margin-bottom: 20px;">
                            <h2 style="color: var(--action-color); margin:0; font-size: 1.5rem;">
                                <?php echo htmlspecialchars($step['title']); ?>
                            </h2>
                            <?php if (!empty($step['description'])): ?>
                                <p
                                    style="color: #94a3b8; margin-top: 10px; font-size: 0.95rem; white-space: pre-line; margin-bottom: 0;">
                                    <?php echo htmlspecialchars(trim($step['description'])); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php foreach ($step['questions'] as $q):
                        $options = json_decode($q['options'], true) ?: [];
                        $req = $q['is_required'] ? 'required' : '';
                        ?>
                        <div class="question-card">
                            <label class="question-title">
                                <?php echo convertLinksToButtons(nl2br(htmlspecialchars($q['question_text']))); ?>
                                <?php if ($q['is_required'])
                                    echo '<span class="required-star">*</span>'; ?>
                            </label>

                            <?php if ($q['question_type'] === 'text'): ?>
                                <input type="text" name="answers[<?php echo $q['id']; ?>]" placeholder="Your answer" <?php echo $req; ?>>

                            <?php elseif ($q['question_type'] === 'textarea'): ?>
                                <textarea name="answers[<?php echo $q['id']; ?>]" rows="4" placeholder="Your answer" <?php echo $req; ?>></textarea>

                            <?php elseif ($q['question_type'] === 'date'): ?>
                                <input type="text" class="flatpickr-datetime" name="answers[<?php echo $q['id']; ?>]"
                                    placeholder="Select Date & Time" <?php echo $req; ?>>

                            <?php elseif ($q['question_type'] === 'select'): ?>
                                <select name="answers[<?php echo $q['id']; ?>]" <?php echo $req; ?>>
                                    <option value="">Select an option</option>
                                    <?php foreach ($options as $opt): ?>
                                        <option value="<?php echo htmlspecialchars($opt); ?>"><?php echo htmlspecialchars($opt); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                            <?php elseif ($q['question_type'] === 'radio'): ?>
                                <?php foreach ($options as $opt): ?>
                                    <label class="radio-option">
                                        <input type="radio" name="answers[<?php echo $q['id']; ?>]"
                                            value="<?php echo htmlspecialchars($opt); ?>" <?php echo $req; ?>>
                                        <span><?php echo htmlspecialchars($opt); ?></span>
                                    </label>
                                <?php endforeach; ?>

                                <?php if (!empty($q['allow_other'])): ?>
                                    <div class="form-check mt-2">
                                        <label class="radio-option d-flex align-items-center">
                                            <input type="radio" name="answers[<?php echo $q['id']; ?>]" value="__other__" <?php echo $req; ?>>
                                            <span class="me-2">Other:</span>
                                            <input type="text" name="answers_other[<?php echo $q['id']; ?>]" class="other-input"
                                                placeholder="Type answer here" disabled
                                                style="border:none; border-bottom:1px solid rgba(255,255,255,0.3); background:transparent; padding:4px 0; color:var(--text-main); flex:1; margin-left:8px;">
                                        </label>
                                    </div>
                                <?php endif; ?>

                            <?php elseif ($q['question_type'] === 'checkbox'): ?>
                                <?php foreach ($options as $opt): ?>
                                    <label class="checkbox-option">
                                        <!-- Array handling for checkbox -->
                                        <input type="checkbox" name="answers[<?php echo $q['id']; ?>][]"
                                            value="<?php echo htmlspecialchars($opt); ?>">
                                        <span><?php echo htmlspecialchars($opt); ?></span>
                                    </label>
                                <?php endforeach; ?>

                                <?php if (!empty($q['allow_other'])): ?>
                                    <div class="form-check mt-2">
                                        <label class="checkbox-option d-flex align-items-center">
                                            <input type="checkbox" name="answers[<?php echo $q['id']; ?>][]" value="__other__">
                                            <span class="me-2">Other:</span>
                                            <input type="text" name="answers_other[<?php echo $q['id']; ?>]" class="other-input"
                                                placeholder="Type answer here" disabled
                                                style="border:none; border-bottom:1px solid rgba(255,255,255,0.3); background:transparent; padding:4px 0; color:var(--text-main); flex:1; margin-left:8px;">
                                        </label>
                                    </div>
                                <?php endif; ?>

                            <?php elseif ($q['question_type'] === 'discord_user'): 
                                // Get configured roles from options
                                $configuredRoles = [];
                                if (!empty($q['options'])) {
                                    $parsed = json_decode($q['options'], true);
                                    if (is_array($parsed)) {
                                        // Filter out default "Option 1", "Option 2" values
                                        $configuredRoles = array_filter($parsed, function($r) {
                                            return !preg_match('/^Option \d+$/', $r);
                                        });
                                    }
                                }
                            ?>
                                <!-- Discord User Picker -->
                                <div class="discord-user-picker" id="discord-picker-<?php echo $q['id']; ?>" 
                                     data-roles="<?php echo htmlspecialchars(implode(',', $configuredRoles)); ?>">
                                    <input type="hidden" name="answers[<?php echo $q['id']; ?>]" id="discord-user-<?php echo $q['id']; ?>" <?php echo $req; ?>>
                                    <div class="discord-dropdown-container">
                                        <div class="discord-dropdown-selected" onclick="toggleDiscordDropdown(<?php echo $q['id']; ?>)">
                                            <div class="discord-selected-content">
                                                <i class="fab fa-discord" style="color: #5865F2;"></i>
                                                <span class="discord-selected-text">-- Select your Discord account --</span>
                                            </div>
                                            <i class="fas fa-chevron-down discord-dropdown-arrow"></i>
                                        </div>
                                        <div class="discord-dropdown-menu" id="discord-menu-<?php echo $q['id']; ?>">
                                            <div class="discord-dropdown-search">
                                                <i class="fas fa-search"></i>
                                                <input type="text" placeholder="Search username..." 
                                                       oninput="filterDiscordUsers(<?php echo $q['id']; ?>, this.value)">
                                            </div>
                                            <div class="discord-dropdown-list" id="discord-list-<?php echo $q['id']; ?>">
                                                <div class="discord-loading"><i class="fas fa-spinner fa-spin"></i> Loading Discord users...</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>

                    <!-- Nav Buttons -->
                    <div class="step-actions d-flex justify-content-between mt-4">
                        <?php if ($index > 0): ?>
                            <button type="button" class="btn-ghost"
                                onclick="changeStep(<?php echo $index - 1; ?>)">Back</button>
                        <?php else: ?>
                            <div></div> <!-- Spacer -->
                        <?php endif; ?>

                        <?php if ($index < $totalSteps - 1): ?>
                            <button type="button" class="submit-btn"
                                onclick="validateAndNext(<?php echo $index; ?>)">Next</button>
                        <?php else: ?>
                            <button type="submit" class="submit-btn" id="submitBtn">Submit Application</button>
                        <?php endif; ?>
                    </div>

                </div>
            <?php endforeach; ?>
        </form>
    </div>

    <!-- Step JS -->
    <script>
        const totalSteps = <?php echo $totalSteps; ?>;
        const stepTitles = <?php echo json_encode(array_column($steps, 'title')); ?>;
        let currentStep = 0;

        function changeStep(step) {
            // Hide current
            document.getElementById('step-' + currentStep).style.display = 'none';
            // Show new
            document.getElementById('step-' + step).style.display = 'block';

            // Toggle Header Visibility
            const header = document.getElementById('formMainHeader');
            if (header) {
                if (step === 0) {
                    header.style.display = 'block';
                } else {
                    header.style.display = 'none';
                }
            }

            // Update Progress
            currentStep = step;
            const progress = ((step + 1) / totalSteps) * 100;
            const bar = document.getElementById('progressBar');
            if (bar) {
                bar.style.width = progress + '%';
                document.getElementById('stepLabel').innerText = `Step ${step + 1} / ${totalSteps}`;
                document.getElementById('stepTitle').innerText = stepTitles[step];
            }

            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function validateAndNext(stepIndex) {
            const stepDiv = document.getElementById('step-' + stepIndex);
            const inputs = stepDiv.querySelectorAll('input[required], select[required], textarea[required]');
            let valid = true;

            inputs.forEach(input => {
                if (!input.checkValidity()) {
                    input.reportValidity();
                    valid = false;
                }
            });

            // Validate Discord User pickers (custom validation for hidden inputs)
            const discordPickers = stepDiv.querySelectorAll('.discord-user-picker');
            discordPickers.forEach(picker => {
                const hiddenInput = picker.querySelector('input[type="hidden"][required]');
                if (hiddenInput && !hiddenInput.value) {
                    valid = false;
                    // Highlight the picker and show error
                    const selectedDiv = picker.querySelector('.discord-dropdown-selected');
                    if (selectedDiv) {
                        selectedDiv.style.borderColor = '#ef4444';
                        selectedDiv.style.boxShadow = '0 0 15px rgba(239, 68, 68, 0.4)';
                        
                        // Show modern SweetAlert2 Toast
                        Swal.fire({
                            icon: 'warning',
                            iconColor: '#d4a853',
                            title: '<span style="color: #fff;">กรุณาเลือก Discord User</span>',
                            html: '<span style="color: rgba(255,255,255,0.7);">เลือก Discord account ของคุณก่อนดำเนินการต่อ</span>',
                            background: 'linear-gradient(135deg, rgba(20, 20, 25, 0.98) 0%, rgba(30, 25, 20, 0.98) 100%)',
                            showConfirmButton: true,
                            confirmButtonText: '<i class="fas fa-check"></i> เข้าใจแล้ว',
                            confirmButtonColor: '#d4a853',
                            timer: 5000,
                            timerProgressBar: true,
                            customClass: {
                                popup: 'storm-swal-popup',
                                timerProgressBar: 'storm-progress-bar'
                            }
                        });
                        
                        // Reset border after 3 seconds
                        setTimeout(() => {
                            selectedDiv.style.borderColor = '';
                            selectedDiv.style.boxShadow = '';
                        }, 3000);
                    }
                }
            });

            if (valid) {
                changeStep(stepIndex + 1);
            }
        }
    </script>

    <!-- Modern Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-content">
            <div class="modern-spinner"></div>
            <div class="loading-text">Submitting</div>
            <div class="loading-subtext">Please wait while we process your application...</div>
        </div>
    </div>

    <!-- Success Overlay -->
    <div class="success-overlay" id="successOverlay">
        <i class="fas fa-check-circle" style="font-size: 4rem; color: #22c55e; margin-bottom: 20px;"></i>
        <h2>Thank You!</h2>
        <p>Your response has been recorded.</p>
        <button onclick="window.location.href='index.php'" class="btn-ghost mt-4"
            style="color:white; border-color:white;">BACK TO
            WEBSITE</button>
    </div>

    <script>
        // Auto-Save Logic
        const formId = "<?php echo $id; ?>";
        const userId = "<?php echo $user ? $user['id'] : 'guest'; ?>";
        const storageKey = `form_progress_${formId}_${userId}`;

        function saveProgress() {
            const formData = {};
            const inputs = document.querySelectorAll('#publicForm input, #publicForm textarea, #publicForm select');

            inputs.forEach(input => {
                const name = input.name;
                if (!name) return; // Skip if no name

                if (input.type === 'radio') {
                    if (input.checked) formData[name] = input.value;
                } else if (input.type === 'checkbox') {
                    if (!formData[name]) formData[name] = [];
                    if (input.checked) formData[name].push(input.value);
                } else {
                    formData[name] = input.value;
                }
            });

            localStorage.setItem(storageKey, JSON.stringify(formData));
        }

        function restoreProgress() {
            const savedData = localStorage.getItem(storageKey);
            if (!savedData) return;

            try {
                const formData = JSON.parse(savedData);
                const inputs = document.querySelectorAll('#publicForm input, #publicForm textarea, #publicForm select');

                inputs.forEach(input => {
                    const name = input.name;
                    if (!name || !formData[name]) return;

                    if (input.type === 'radio') {
                        if (formData[name] === input.value) input.checked = true;
                    } else if (input.type === 'checkbox') {
                        if (Array.isArray(formData[name]) && formData[name].includes(input.value)) {
                            input.checked = true;
                        }
                    } else {
                        input.value = formData[name];
                    }
                });
            } catch (e) {
                console.error("Error restoring progress:", e);
                localStorage.removeItem(storageKey); // Clear if corrupted
            }
        }

        // Debounced Save
        let saveTimeout;
        document.getElementById('publicForm').addEventListener('input', () => {
            clearTimeout(saveTimeout);
            saveTimeout = setTimeout(saveProgress, 500);
        });
        document.getElementById('publicForm').addEventListener('change', saveProgress);

        // Restore on Load
        document.addEventListener('DOMContentLoaded', () => {
            restoreProgress();

            // Setup "Other" input toggling
            document.querySelectorAll('input[type=radio][value="__other__"]').forEach(radio => {
                const container = radio.closest('.form-check'); // may assume structure
                if (!container) return;
                const textInput = container.querySelector('.other-input');

                // Initial state
                if (radio.checked) textInput.disabled = false;

                // Listen for changes on ALL radios in this question group
                const name = radio.name;
                document.querySelectorAll(`input[name="${name}"]`).forEach(r => {
                    r.addEventListener('change', () => {
                        const isOther = document.querySelector(`input[name="${name}"][value="__other__"]`)  
                        ;
                        
                        if (isOther && isOther.checked) {
                            textInput.disabled = false;
                            textInput.focus();
                        } else {
                            textInput.disabled = true;
                            // textInput.value = ''; // Optional cleanup
                        }
                    });
                });
            });

            // Make sure checkbox "Other" interaction works
            document.querySelectorAll('input[type=checkbox][value="__other__"]').forEach(box => {
                 const container = box.closest('.form-check');
                 if (!container) return;
                 const textInput = container.querySelector('.other-input');
                 if(!textInput) return;
                 
                 // Initial
                 textInput.disabled = !box.checked;

                 box.addEventListener('change', () => {
                     textInput.disabled = !box.checked;
                     if (box.checked) textInput.focus();
                 });
            });

            // Initialize S3 Style Flatpickr
            flatpickr(".flatpickr-datetime", {
                enableTime: true,
                dateFormat: "Y-m-d H:i",
                time_24hr: true,
                disableMobile: "true", // Force custom picker on mobile
                onOpen: function(selectedDates, dateStr, instance) {
                    instance.calendarContainer.classList.add('custom-flatpickr-glass');
                }
            });
        });


        document.getElementById('publicForm').addEventListener('submit', async function (e) {
            e.preventDefault();
            const btn = document.getElementById('submitBtn');
            const loadingOverlay = document.getElementById('loadingOverlay');
            
            // Check if discord picker is used to get discord ID
            let discordUserId = '';
            const discordInputs = document.querySelectorAll('input[name^="q_"]');
            for (let input of discordInputs) {
                try {
                    const data = JSON.parse(input.value);
                    if (data && data.id) {
                        discordUserId = data.id;
                        break;
                    }
                } catch (e) {
                    // Not a JSON input
                }
            }

            // If we found a discord user ID, initiate verification flow
            if (discordUserId) {
                btn.disabled = true;
                
                // Show a loading alert while initiating request
                Swal.fire({
                    title: 'กำลังเชื่อมต่อ...',
                    text: 'กำลังส่งคำขอยืนยันตัวตนไปยัง Discord',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    },
                    customClass: { popup: 'storm-swal-popup' }
                });

                try {
                    // 1. Request verification
                    const verifyForm = new FormData();
                    verifyForm.append('discord_user_id', discordUserId);
                    verifyForm.append('form_id', formId);
                    verifyForm.append('form_title', "<?php echo addslashes($form['title']); ?>");

                    const reqRes = await fetch('api/discord_verify_request.php', {
                        method: 'POST',
                        body: verifyForm
                    });
                    const reqData = await reqRes.json();

                    if (!reqData.success) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: reqData.error || 'Failed to initiate verification',
                            customClass: { popup: 'storm-swal-popup' }
                        });
                        btn.disabled = false;
                        return;
                    }

                    const verifyId = reqData.verify_id;
                    const targetNumber = reqData.target_number;

                    // 2. Show polling alert to user
                    Swal.fire({
                        title: '🔐 ยืนยันตัวตนใน Discord',
                        html: `
                            <div style="margin: 20px 0;">
                                <p>บอทได้ส่งข้อความ DM ไปหาคุณแล้ว</p>
                                <p style="font-size: 1.1rem; margin-top: 15px;">กรุณากดปุ่มหมายเลข:</p>
                                <div style="font-size: 3.5rem; font-weight: bold; color: #c5a059; letter-spacing: 5px; text-shadow: 0 0 20px rgba(197,160,89,0.5); margin: 10px 0;">
                                    ${targetNumber}
                                </div>
                                <p style="color: #f04747; font-size: 0.9rem; margin-top: 15px;">
                                    <i class="fas fa-exclamation-triangle"></i> หากกดผิดคำร้องของคุณจะถูกยกเลิก
                                </p>
                            </div>
                        `,
                        showConfirmButton: false,
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        didOpen: () => {
                            Swal.showLoading();
                            
                            // 3. Start Polling
                            const pollInterval = setInterval(async () => {
                                try {
                                    const pollRes = await fetch(`api/discord_verify_status.php?verify_id=${verifyId}`);
                                    const pollData = await pollRes.json();
                                    
                                    if (pollData.success) {
                                        if (pollData.status === 'success') {
                                            clearInterval(pollInterval);
                                            Swal.close();
                                            // Proceed to submit form
                                            submitActualForm(this);
                                        } else if (pollData.status === 'failed') {
                                            clearInterval(pollInterval);
                                            Swal.fire({
                                                icon: 'error',
                                                title: '❌ ยืนยันไม่สำเร็จ',
                                                text: 'คุณกดหมายเลขไม่ถูกต้อง กรุณาส่งแบบฟอร์มใหม่อีกครั้ง',
                                                customClass: { popup: 'storm-swal-popup' }
                                            });
                                            btn.disabled = false;
                                        } else if (pollData.status === 'expired') {
                                            clearInterval(pollInterval);
                                            Swal.fire({
                                                icon: 'warning',
                                                title: '⏱️ หมดเวลา',
                                                text: 'หมดเวลายืนยันตัวตน กรุณาส่งแบบฟอร์มใหม่อีกครั้ง',
                                                customClass: { popup: 'storm-swal-popup' }
                                            });
                                            btn.disabled = false;
                                        }
                                    }
                                } catch (e) {
                                    console.error('Polling error:', e);
                                }
                            }, 2000); // Poll every 2 seconds
                        },
                        customClass: { popup: 'storm-swal-popup' }
                    });
                } catch (e) {
                    console.error('Verification flow error:', e);
                    Swal.fire({
                        icon: 'error',
                        title: 'Connection Error',
                        text: 'ไม่สามารถติดต่อเซิร์ฟเวอร์ได้',
                        customClass: { popup: 'storm-swal-popup' }
                    });
                    btn.disabled = false;
                }
            } else {
                // No discord user id found in form, submit normally
                submitActualForm(this);
            }
        });
        
        async function submitActualForm(formElement) {
            const btn = document.getElementById('submitBtn');
            const loadingOverlay = document.getElementById('loadingOverlay');
            
            // Show modern loading overlay
            loadingOverlay.classList.add('active');
            btn.disabled = true;

            const formData = new FormData(formElement);

            try {
                const res = await fetch('admin/form_actions.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();

                if (data.success) {
                    localStorage.removeItem(storageKey); // Clear saved data on success
                    
                    // If login info returned, handle it
                    if (data.login_token) {
                        // We could potentially set a session here via fetch, but the backend handles session creation
                        // So we just show success and redirect
                    }
                    
                    // Hide loading, show success
                    loadingOverlay.classList.remove('active');
                    document.getElementById('successOverlay').style.display = 'flex';
                } else {
                    loadingOverlay.classList.remove('active');
                    alert('Submission failed: ' + (data.message || 'Unknown error'));
                    btn.disabled = false;
                }
            } catch (err) {
                console.error(err);
                loadingOverlay.classList.remove('active');
                alert('An error occurred.');
                btn.disabled = false;
            }
        }

        // ===== DISCORD USER PICKER =====
        let discordUsersCache = null;
        let discordUsersLoaded = false;

        // Load Discord users on page load if there's a discord_user picker
        document.addEventListener('DOMContentLoaded', function() {
            const pickers = document.querySelectorAll('.discord-user-picker');
            if (pickers.length > 0) {
                loadDiscordUsers();
            }
        });

        async function loadDiscordUsers() {
            if (discordUsersLoaded) return;
            
            // Collect all unique roles from all pickers
            let allRoles = new Set();
            document.querySelectorAll('.discord-user-picker').forEach(picker => {
                const rolesAttr = picker.getAttribute('data-roles');
                if (rolesAttr) {
                    rolesAttr.split(',').filter(r => r.trim()).forEach(r => allRoles.add(r.trim()));
                }
            });
            
            // Build API URL with additional_roles parameter
            let apiUrl = 'api/bot_proxy.php?action=get_filtered_users';
            if (allRoles.size > 0) {
                apiUrl += '&additional_roles=' + encodeURIComponent(Array.from(allRoles).join(','));
            }
            
            try {
                const response = await fetch(apiUrl);
                const data = await response.json();
                
                if (data.success && data.users) {
                    discordUsersCache = data.users;
                    discordUsersLoaded = true;
                    
                    // Render users in all discord pickers
                    document.querySelectorAll('.discord-dropdown-list').forEach(list => {
                        const questionId = list.id.replace('discord-list-', '');
                        renderDiscordUsers(questionId, data.users);
                    });
                } else {
                    console.error('Failed to load Discord users:', data.error || 'Unknown error');
                    document.querySelectorAll('.discord-dropdown-list').forEach(list => {
                        list.innerHTML = '<div class="discord-no-results"><i class="fas fa-exclamation-triangle"></i> Failed to load users</div>';
                    });
                }
            } catch (e) {
                console.error('Error loading Discord users:', e);
                document.querySelectorAll('.discord-dropdown-list').forEach(list => {
                    list.innerHTML = '<div class="discord-no-results"><i class="fas fa-exclamation-triangle"></i> Connection error</div>';
                });
            }
        }

        function renderDiscordUsers(questionId, users) {
            const list = document.getElementById(`discord-list-${questionId}`);
            if (!list) return;
            
            if (!users || users.length === 0) {
                list.innerHTML = '<div class="discord-no-results">No users available</div>';
                return;
            }
            
            list.innerHTML = users.map(user => `
                <div class="discord-user-item" onclick="selectDiscordUser(${questionId}, '${user.id}', '${escapeHtml(user.display_name)}', '${user.avatar || ''}', '${escapeHtml(user.username)}')">
                    <img src="${user.avatar || 'assets/images/default_avatar.png'}" class="discord-user-avatar" onerror="this.src='assets/images/default_avatar.png'">
                    <div class="discord-user-info">
                        <span class="discord-user-name">${escapeHtml(user.display_name)}</span>
                        <span class="discord-user-username">@${escapeHtml(user.username)}</span>
                    </div>
                </div>
            `).join('');
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML.replace(/'/g, "\\'").replace(/"/g, '\\"');
        }

        function toggleDiscordDropdown(questionId) {
            const container = document.querySelector(`#discord-picker-${questionId} .discord-dropdown-container`);
            if (!container) return;
            
            // Close other open dropdowns and remove z-index from their parents
            document.querySelectorAll('.discord-dropdown-container.open').forEach(c => {
                if (c !== container) {
                    c.classList.remove('open');
                    const parentCard = c.closest('.question-card');
                    if (parentCard) parentCard.classList.remove('has-dropdown-open');
                }
            });
            
            container.classList.toggle('open');
            
            // Add/remove z-index class to parent question-card
            const parentCard = container.closest('.question-card');
            if (parentCard) {
                if (container.classList.contains('open')) {
                    parentCard.classList.add('has-dropdown-open');
                } else {
                    parentCard.classList.remove('has-dropdown-open');
                }
            }
            
            // Focus search input when opening
            if (container.classList.contains('open')) {
                const searchInput = container.querySelector('.discord-dropdown-search input');
                if (searchInput) {
                    setTimeout(() => searchInput.focus(), 100);
                }
            }
        }

        function filterDiscordUsers(questionId, searchTerm) {
            if (!discordUsersCache) return;
            
            const filtered = discordUsersCache.filter(user => {
                const term = searchTerm.toLowerCase();
                return (user.username && user.username.toLowerCase().includes(term)) ||
                       (user.display_name && user.display_name.toLowerCase().includes(term)) ||
                       (user.global_name && user.global_name.toLowerCase().includes(term));
            });
            
            renderDiscordUsers(questionId, filtered);
        }

        function selectDiscordUser(questionId, userId, displayName, avatar, username) {
            // Set hidden input value (store as JSON with all info)
            const hiddenInput = document.getElementById(`discord-user-${questionId}`);
            if (hiddenInput) {
                hiddenInput.value = JSON.stringify({
                    id: userId,
                    display_name: displayName,
                    username: username,
                    avatar: avatar
                });
            }
            
            // Update selected display
            const selectedContent = document.querySelector(`#discord-picker-${questionId} .discord-selected-content`);
            if (selectedContent) {
                selectedContent.innerHTML = `
                    <img src="${avatar || 'assets/images/default_avatar.png'}" class="discord-selected-avatar" onerror="this.src='assets/images/default_avatar.png'">
                    <span class="discord-selected-text" style="color: #fff;">${displayName} <span style="color: var(--text-muted);">@${username}</span></span>
                `;
            }
            
            // Close dropdown and remove z-index from parent
            const container = document.querySelector(`#discord-picker-${questionId} .discord-dropdown-container`);
            if (container) {
                container.classList.remove('open');
                const parentCard = container.closest('.question-card');
                if (parentCard) parentCard.classList.remove('has-dropdown-open');
            }
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.discord-dropdown-container')) {
                document.querySelectorAll('.discord-dropdown-container.open').forEach(c => {
                    c.classList.remove('open');
                    const parentCard = c.closest('.question-card');
                    if (parentCard) parentCard.classList.remove('has-dropdown-open');
                });
            }
        });
    </script>

</body>

</html>
