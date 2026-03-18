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
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Accept-language: en\r\n" .
                    "Content-type: application/x-www-form-urlencoded\r\n" .
                    "Content-Length: " . strlen($data) . "\r\n",
                'content' => $data,
            ],
        ]);

        $result = file_get_contents('https://steamcommunity.com/openid/login', false, $context);

        if (preg_match("#is_valid:true#i", $result)) {
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
        $json = file_get_contents($url);
        $data = json_decode($json, true);
        return isset($data['response']['players'][0]) ? $data['response']['players'][0] : null;
    }
}
?>