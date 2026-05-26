<?php
/**
 * BotAPI Helper Class
 * Handles communication with the Python Discord Bot via HTTP API.
 */
class BotAPI
{
    private $baseUrl;

    public function __construct()
    {
        // defined in db.php or config, usually http://bot:5000 inside Docker
        $this->baseUrl = defined('DISCORD_BOT_API_URL') ? DISCORD_BOT_API_URL : 'http://bot:5000';
    }

    /**
     * Send a request to the bot
     * @param int $timeout Connection timeout in seconds. Default 15s for stability.
     */
    public function request($endpoint, $data = [], $method = 'POST', $timeout = 15)
    {
        $url = $this->baseUrl . $endpoint;
        $ch = curl_init($url);

        $payload = json_encode($data);

        // Build headers including API Key
        $headers = ['Content-Type: application/json'];
        if (defined('BOT_API_KEY') && BOT_API_KEY) {
            $headers[] = 'X-API-Key: ' . BOT_API_KEY;
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        } elseif ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }

        // Timeout customization (default 3s to prevent UI freezes, can pass higher for operations like DMs)
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        // Also set connect timeout so it fails fast if bot is completely offline
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if ($response === false) {
            return ['success' => false, 'error' => 'cURL Error: ' . $error, 'code' => 0];
        }

        $decoded = json_decode($response, true);
        $jsonError = json_last_error();

        if ($jsonError !== JSON_ERROR_NONE) {
            // Failed to decode JSON. This likely means the bot returned an HTML error page or valid 500/502/503 text.
            // Return a snippet of the raw response to help debugging.
            $rawSnippet = substr(strip_tags($response), 0, 200);
            return [
                'success' => false,
                'error' => "Invalid JSON response (HTTP $httpCode). Raw: $rawSnippet...",
                'code' => $httpCode,
                'raw_response' => $response
            ];
        }

        if ($httpCode >= 200 && $httpCode < 300) {
            return ['success' => true, 'data' => $decoded];
        } else {
            // API returned a JSON error response
            $errorMessage = $decoded['error'] ?? 'Unknown API error';
            if (is_array($errorMessage)) {
                $errorMessage = json_encode($errorMessage);
            }
            return ['success' => false, 'error' => $errorMessage, 'code' => $httpCode];
        }
    }

    /**
     * Check if bot is online
     * Uses a simple ping to the root or a health endpoint
     * Since bot.py doesn't have a specific health endpoint, we'll try a GET to a verifiable route or just check connectability
     * For now, we assume if we can connect to /webhook with empty data and get 400, it's up.
     * Or better, let's implement a simple health check if possible, but for now we'll rely on connection check.
     */
    public function getStatus()
    {
        // Just try to open a socket to the port
        $host = parse_url($this->baseUrl, PHP_URL_HOST);
        $port = parse_url($this->baseUrl, PHP_URL_PORT);

        if (empty($port)) {
            $port = 80;
        }

        $connection = @fsockopen($host, $port, $errno, $errstr, 2);

        if (is_resource($connection)) {
            fclose($connection);
            return true;
        } else {
            return false;
        }
    }

    /**
     * Send a custom embed
     */
    public function sendEmbed($channelId, $embedData)
    {
        $payload = array_merge(['channel_id' => $channelId], $embedData);
        return $this->request('/embed/send', $payload);
    }

    /**
     * Update an existing embed
     */
    public function updateEmbed($channelId, $messageId, $embedData)
    {
        $payload = array_merge(['channel_id' => $channelId, 'message_id' => $messageId], $embedData);
        return $this->request('/embed/edit', $payload);
    }

    /**
     * Create an event (Apollo Style)
     */
    public function createEvent($eventData)
    {
        return $this->request('/event/create', $eventData);
    }

    /**
     * Get accessible channels
     */
    public function getChannels()
    {
        $response = $this->request('/channels', [], 'GET');
        if ($response['success']) {
            return $response['data']['channels'];
        }
        return [];
    }

    /**
     * Get roles from Discord server
     */
    public function getRoles()
    {
        $response = $this->request('/roles', [], 'GET');
        if ($response['success']) {
            return $response['data']['roles'];
        }
        return [];
    }

    /**
     * Get members from Discord server for mention autocomplete
     */
    public function getMembers()
    {
        $response = $this->request('/members', [], 'GET');
        if ($response['success']) {
            return $response['data']['members'];
        }
        return [];
    }
    /**
     * Get active events
     */
    public function getActiveEvents()
    {
        $response = $this->request('/events/active', [], 'GET');
        if ($response['success']) {
            return $response['data']['events'];
        }
        return [];
    }

    /**
     * Cancel an event
     */
    public function cancelEvent($eventId)
    {
        $response = $this->request('/event/cancel', ['event_id' => $eventId]);
        return $response['success'];
    }

    /**
     * Get bot status information
     */
    public function getBotStatus()
    {
        $response = $this->request('/bot/status', [], 'GET');
        if ($response['success']) {
            return $response['data']['data'] ?? [];
        }
        return null;
    }

    public function updateBotSettings($settings)
    {
        return $this->request('/bot/settings', $settings);
    }

    /**
     * Get voice channels from Discord server
     */
    public function getVoiceChannels()
    {
        $response = $this->request('/voice_channels', [], 'GET');
        if ($response['success']) {
            return $response['data']['channels'];
        }
        return [];
    }

    /**
     * Join a voice channel
     */
    public function joinVoice($channelId)
    {
        return $this->request('/bot/join', ['channel_id' => $channelId]);
    }

    /**
     * Leave voice channel
     */
    public function leaveVoice()
    {
        return $this->request('/bot/leave', []);
    }

    /**
     * Lookup Discord user by username and return their ID/mention
     */
    public function lookupUser($username)
    {
        $response = $this->request('/users/lookup?username=' . urlencode($username), [], 'GET');
        if ($response['success'] && isset($response['data'])) {
            return $response['data'];
        }
        return ['found' => false];
    }

    /**
     * Assign a Discord role to a user
     */
    public function assignRole($userId, $roleId)
    {
        return $this->request('/roles/assign', [
            'user_id' => $userId,
            'role_id' => $roleId
        ]);
    }

    /**
     * Get filtered Discord users for form applicant selection
     * Default: returns users with NO roles
     * Optional: additional_roles parameter to include users with specific roles
     */
    public function getFilteredUsers($additionalRoles = '')
    {
        $endpoint = '/users/filtered';
        if (!empty($additionalRoles)) {
            $endpoint .= '?additional_roles=' . urlencode($additionalRoles);
        }
        $response = $this->request($endpoint, [], 'GET');
        if ($response['success'] && isset($response['data'])) {
            return $response['data'];
        }
        return ['success' => false, 'users' => []];
    }
    // ========== ROLE PANELS ==========

    /**
     * Get all role panels
     */
    public function getRolePanels()
    {
        $response = $this->request('/role-panels', [], 'GET');
        if ($response['success'] && isset($response['data']['panels'])) {
            return $response['data']['panels'];
        }
        return [];
    }

    /**
     * Save (create/update) a role panel
     */
    public function saveRolePanel($data)
    {
        return $this->request('/role-panels', $data);
    }

    /**
     * Delete a role panel
     */
    public function deleteRolePanel($panelId)
    {
        return $this->request('/role-panels', ['panel_id' => $panelId], 'DELETE');
    }

    /**
     * Send a role panel to a Discord channel
     */
    public function sendRolePanel($panelId, $channelId)
    {
        return $this->request('/role-panels/send', [
            'panel_id' => $panelId,
            'channel_id' => $channelId
        ]);
    }
}
?>