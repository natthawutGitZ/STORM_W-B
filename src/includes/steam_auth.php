<?php
class SteamAuth
{
    private $apikey;
    private $domain;

    public function __construct($apikey, $domain)
    {
        $this->apikey = $apikey;
        $this->domain = $domain;
    }

    private function doCurlViaBotProxy($url, $postData = null)
    {
        // Define bot API URL (same as bot_api.php)
        $botApiUrl = defined('DISCORD_BOT_API_URL') ? DISCORD_BOT_API_URL : 'http://bot:5000';
        
        $proxyUrl = $botApiUrl . '/proxy/steam?url=' . urlencode($url);

        $ch = curl_init($proxyUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        // Include API key for bot authentication if set
        $headers = [];
        if (defined('BOT_API_KEY') && BOT_API_KEY) {
            $headers[] = 'X-API-Key: ' . BOT_API_KEY;
        }

        if ($postData !== null) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            $headers[] = "Content-type: application/x-www-form-urlencoded";
            $headers[] = "Content-Length: " . strlen($postData);
        }

        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $result = curl_exec($ch);
        if ($result === false) {
            error_log("Steam Proxy Curl Error: " . curl_error($ch));
        }
        curl_close($ch);

        return $result;
    }

    public function loginUrl()
    {
        $params = [
            'openid.ns' => 'http://specs.openid.net/auth/2.0',
            'openid.mode' => 'checkid_setup',
            'openid.return_to' => $this->domain . 'login.php',
            'openid.realm' => $this->domain,
            'openid.identity' => 'http://specs.openid.net/auth/2.0/identifier_select',
            'openid.claimed_id' => 'http://specs.openid.net/auth/2.0/identifier_select',
        ];

        return 'https://steamcommunity.com/openid/login?' . http_build_query($params);
    }

    public function validate()
    {
        $params = [
            'openid.assoc_handle' => $_GET['openid_assoc_handle'],
            'openid.signed' => $_GET['openid_signed'],
            'openid.sig' => $_GET['openid_sig'],
            'openid.ns' => 'http://specs.openid.net/auth/2.0',
            'openid.mode' => 'check_authentication',
        ];

        $signed = explode(',', $_GET['openid_signed']);
        foreach ($signed as $item) {
            $val = $_GET['openid_' . str_replace('.', '_', $item)];
            $params['openid.' . $item] = stripslashes($val);
        }

        $data = http_build_query($params);
        $result = $this->doCurlViaBotProxy('https://steamcommunity.com/openid/login', $data);

        if ($result && preg_match("#is_valid:true#i", $result)) {
            preg_match('#^https://steamcommunity.com/openid/id/([0-9]{17,25})#', $_GET['openid_claimed_id'], $matches);
            $steamID64 = is_numeric($matches[1]) ? $matches[1] : 0;
            return $steamID64;
        } else {
            return false;
        }
    }

    public function getUserInfo($steamid)
    {
        $url = "https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/?key={$this->apikey}&steamids={$steamid}";
        $json = $this->doCurlViaBotProxy($url);
        
        if (!$json) {
            return null;
        }
        
        $data = json_decode($json, true);
        return isset($data['response']['players'][0]) ? $data['response']['players'][0] : null;
    }
}
?>