<?php
/**
 * Database Configuration
 * Optimized for Docker and Production (InfinityFree)
 * Auto-detects environment with improved reliability
 */

// Detect environment with multiple checks for reliability
$hostname = $_SERVER['HTTP_HOST'] ?? 'localhost';

// Enhanced Docker detection
// Check 1: Environment variables (most reliable)
// Check 2: Docker-specific files
// Check 3: Hostname resolution
$isDocker = (
    getenv('MYSQL_DATABASE') !== false ||
    getenv('DOCKER_CONTAINER') !== false ||
    file_exists('/.dockerenv') ||
    (function_exists('gethostbyname') && @gethostbyname('db') !== 'db')
);

// Check if local development (not Docker, not production)
$isLocal = !$isDocker && (
    strpos($hostname, 'localhost') !== false ||
    strpos($hostname, '127.0.0.1') !== false ||
    $hostname === 'lvh.me'
);

// Determine if production environment (InfinityFree Legacy)
// We prioritize Docker (EC2 All-in-One) over this check if $isDocker is true.
$isLegacyProduction = !$isDocker && (strpos($hostname, 'ct.ws') !== false || strpos($hostname, 'infinityfree') !== false);
$isProduction = $isLegacyProduction; // For backward compatibility if used elsewhere

// Set database credentials based on environment
if ($isDocker) {
    // Docker environment (EC2 All-in-One or Local Docker)
    // Uses internal network for DB and Bot
    $host = 'db';
    $db = getenv('MYSQL_DATABASE') ?: 'appdb';
    $user = getenv('MYSQL_USER') ?: 'appuser';
    $pass = getenv('MYSQL_PASSWORD') ?: 'apppass';
    $port = 3306;

    // Bot API in Docker Network (Internal)
    define('DISCORD_BOT_API_URL', getenv('DISCORD_BOT_API_URL') ?: 'http://bot:5000');
    define('BOT_API_KEY', getenv('BOT_API_KEY') ?: '');

} elseif ($isLegacyProduction) {
    // Legacy Production (InfinityFree) - External EC2 Bot
    $host = 'sql100.infinityfree.com';
    $db = 'if0_40531677_storm_db';
    $user = 'if0_40531677';
    $pass = 'QNRTRkWjgSGzrjN';
    $port = 3306;

    // Bot API (External EC2 IP)
    define('DISCORD_BOT_API_URL', 'http://52.64.92.164:5000');
    // API Key from EC2
    define('BOT_API_KEY', 'f68196c14bce65ad6f1dec4b581e45a3e7139c9514dc2eec3b959ef62588e852');

} else {
    // Local XAMPP/WAMP (No Docker)
    $host = 'localhost';
    $db = 'sac_milsim';
    $user = 'root';
    $pass = '';
    $port = 3306;

    define('DISCORD_BOT_API_URL', 'http://localhost:5000');
    define('BOT_API_KEY', '');
}

$charset = 'utf8mb4';

// Build DSN with port
$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";

// PDO options optimized for production
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,

    // Production optimizations
    PDO::ATTR_PERSISTENT => $isProduction, // Connection pooling for production
    PDO::ATTR_TIMEOUT => 10, // 10 second timeout
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
];

// Connection retry logic (important for production)
$maxRetries = $isProduction ? 3 : 1;
$retryDelay = 1; // seconds
$lastException = null;

for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
    try {
        $pdo = new PDO($dsn, $user, $pass, $options);

        // Disable strict group by mode for legacy queries
        $pdo->exec("SET sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))");

        // Set timezone
        $pdo->exec("SET time_zone = '+07:00'"); // Thailand timezone

        // Connection successful
        break;

    } catch (\PDOException $e) {
        $lastException = $e;

        // If not the last attempt, wait and retry
        if ($attempt < $maxRetries) {
            sleep($retryDelay);
            $retryDelay *= 2; // Exponential backoff
            continue;
        }

        // All retries failed - handle error
        if ($isProduction) {
            // Production: Log error securely, show generic message
            error_log("Database Connection Failed: " . $e->getMessage());
            error_log("Host: $host | Database: $db | User: $user");

            die("<div style='background:#1a1a1a;color:#fff;padding:40px;font-family:system-ui,-apple-system,sans-serif;text-align:center;'>
                 <h2 style='color:#f44336;margin:0 0 10px 0;'>⚠️ Database Connection Error</h2>
                 <p style='color:#aaa;margin:0;'>Unable to connect to the database. Please try again later.</p>
                 <p style='color:#666;margin:10px 0 0 0;font-size:12px;'>Error ID: " . substr(md5(time()), 0, 8) . "</p>
                 </div>");
        } else {
            // Development: Show detailed error for debugging
            $errorMsg = "Database Connection Failed<br>";
            $errorMsg .= "Error: " . $e->getMessage() . "<br>";
            $errorMsg .= "Host: $host:$port<br>";
            $errorMsg .= "Database: $db<br>";
            $errorMsg .= "User: $user<br>";
            $errorMsg .= "Environment: " . ($isDocker ? 'Docker' : ($isLocal ? 'Local' : 'Production')) . "<br>";
            $errorMsg .= "Attempts: $attempt/$maxRetries";

            die("<div style='background:#1a1a1a;color:#fff;padding:20px;font-family:monospace;'>
                 <h3 style='color:#f44336;margin:0 0 15px 0;'>🔴 Database Connection Failed</h3>
                 <div style='background:#2a2a2a;padding:15px;border-left:3px solid #f44336;'>
                     $errorMsg
                 </div>
                 </div>");
        }
    }
}