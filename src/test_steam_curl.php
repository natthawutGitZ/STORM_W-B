<?php
// Test using shell exec cURL instead of PHP cURL
$ip = trim(shell_exec("curl -sS 'https://cloudflare-dns.com/dns-query?name=steamcommunity.com&type=A' -H 'accept: application/dns-json' 2>/dev/null"));
$dohData = json_decode($ip, true);
$resolvedIp = null;
if (isset($dohData['Answer'])) {
    foreach ($dohData['Answer'] as $rec) {
        if ($rec['type'] === 1) { $resolvedIp = $rec['data']; break; }
    }
}
echo "DoH IP: $resolvedIp\n";

$postData = "openid.mode=check_authentication&openid.ns=http://specs.openid.net/auth/2.0";
$cmd = "curl -sS --max-time 10 --resolve 'steamcommunity.com:443:$resolvedIp' -X POST -d " . escapeshellarg($postData) . " -A 'Mozilla/5.0' -k 'https://steamcommunity.com/openid/login' 2>&1";
echo "CMD: $cmd\n";
$result = shell_exec($cmd);
echo "Result: $result\n";
