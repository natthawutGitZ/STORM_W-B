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

    private function doCurlWithDoH($url, $postData = null)
    {
        $parsed = parse_url($url);
        $host = $parsed['host'];
        $port = isset($parsed['port']) ? $parsed['port'] : ($parsed['scheme'] === 'https' ? 443 : 80);

        // Fetch real IP via Cloudflare DoH to bypass ISP poisoning
        $dohUrl = "https://cloudflare-dns.com/dns-query?name=" . urlencode($host) . "&type=A";
        
        $ch_doh = curl_init($dohUrl);
        curl_setopt($ch_doh, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch_doh, CURLOPT_HTTPHEADER, ["accept: application/dns-json"]);
        curl_setopt($ch_doh, CURLOPT_TIMEOUT, 5);
        $dohResponse = curl_exec($ch_doh);
        curl_close($ch_doh);

        $ip = null;
        if ($dohResponse) {
            $dohData = json_decode($dohResponse, true);
            if (isset($dohData['Answer']) && is_array($dohData['Answer'])) {
                foreach ($dohData['Answer'] as $record) {
                    if ($record['type'] === 1) { // A record
                        $ip = $record['data'];
                        break;
                    }
                }
            }
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 

        if ($ip) {
            curl_setopt($ch, CURLOPT_RESOLVE, ["{$host}:{$port}:{$ip}"]);
        }

        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        if ($postData !== null) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Accept-language: en",
                "Content-type: application/x-www-form-urlencoded",
                "Content-Length: " . strlen($postData)
            ]);
        }

        $result = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($result === false) {
            error_log("Steam Curl Error: " . $curlError);
        }
        file_put_contents('/var/www/html/login_debug.txt', "doCurlWithDoH: ip=$ip, httpCode=$httpCode, curlError=[$curlError], resultLen=" . strlen($result) . "\n", FILE_APPEND);
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
        file_put_contents('/var/www/html/login_debug.txt', "Validate POST data: " . $data . "\n", FILE_APPEND);
        $result = $this->doCurlWithDoH('https://steamcommunity.com/openid/login', $data);
        file_put_contents('/var/www/html/login_debug.txt', "Validate raw result: [" . var_export($result, true) . "]\n", FILE_APPEND);

        if ($result && preg_match("#is_valid:true#i", $result)) {
            preg_match('#^https://steamcommunity.com/openid/id/([0-9]{17,25})#', $_GET['openid_claimed_id'], $matches);
            $steamID64 = is_numeric($matches[1]) ? $matches[1] : 0;
            file_put_contents('/var/www/html/login_debug.txt', "Validate steamID64: " . $steamID64 . "\n", FILE_APPEND);
            return $steamID64;
        } else {
            file_put_contents('/var/www/html/login_debug.txt', "Validate FAILED - result was falsy or no is_valid:true\n", FILE_APPEND);
            return false;
        }
    }

    public function getUserInfo($steamid)
    {
        $url = "https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/?key={$this->apikey}&steamids={$steamid}";
        $json = $this->doCurlWithDoH($url);
        
        if (!$json) {
            return null;
        }
        
        $data = json_decode($json, true);
        return isset($data['response']['players'][0]) ? $data['response']['players'][0] : null;
    }
}
?>