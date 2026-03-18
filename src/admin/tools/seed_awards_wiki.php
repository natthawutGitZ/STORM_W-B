<?php
$host = 'db';
$db = getenv('MYSQL_DATABASE') ?: 'appdb';
$user = getenv('MYSQL_USER') ?: 'appuser';
$pass = getenv('MYSQL_PASSWORD') ?: 'apppass';
$charset = 'utf8mb4';
$dsn = "mysql:host=$host;port=3306;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];
$pdo = new PDO($dsn, $user, $pass, $options);

$awardsToFetch = [
    // Personal Decorations (Category 1)
    [
        'name' => 'Medal of Honor',
        'wiki_file' => 'Medal_of_Honor_ribbon.svg',
        'category_id' => 1,
        'order' => 1
    ],
    [
        'name' => 'Distinguished Service Cross',
        'wiki_file' => 'Distinguished_Service_Cross_ribbon.svg',
        'category_id' => 1,
        'order' => 2
    ],
    [
        'name' => 'Silver Star',
        'wiki_file' => 'Silver_Star_ribbon.svg',
        'category_id' => 1,
        'order' => 3
    ],
    [
        'name' => 'Bronze Star',
        'wiki_file' => 'Bronze_Star_ribbon.svg',
        'category_id' => 1,
        'order' => 4
    ],
    [
        'name' => 'Purple Heart',
        'wiki_file' => 'Purple_Heart_ribbon.svg',
        'category_id' => 1,
        'order' => 5
    ],
    [
        'name' => 'Meritorious Service Medal',
        'wiki_file' => 'Meritorious_Service_Medal_ribbon.svg',
        'category_id' => 1,
        'order' => 6
    ],
    [
        'name' => 'Army Commendation Medal',
        'wiki_file' => 'Army_Commendation_Medal_ribbon.svg',
        'category_id' => 1,
        'order' => 7
    ],
    [
        'name' => 'Army Achievement Medal',
        'wiki_file' => 'Army_Achievement_Medal_ribbon.svg',
        'category_id' => 1,
        'order' => 8
    ],
    // Service Awards (Category 2)
    [
        'name' => 'Army Good Conduct Medal',
        'wiki_file' => 'Army_Good_Conduct_Medal_ribbon.svg',
        'category_id' => 2,
        'order' => 1
    ],
    [
        'name' => 'National Defense Service Medal',
        'wiki_file' => 'National_Defense_Service_Medal_ribbon.svg',
        'category_id' => 2,
        'order' => 2
    ],
    [
        'name' => 'Global War on Terrorism Service Medal',
        'wiki_file' => 'Global_War_on_Terrorism_Service_Medal_ribbon.svg',
        'category_id' => 2,
        'order' => 3
    ],
    // Service & Training Awards (Category 3)
    [
        'name' => 'Army Service Ribbon',
        'wiki_file' => 'Army_Service_Ribbon.svg',
        'category_id' => 3,
        'order' => 1
    ],
    [
        'name' => 'Overseas Service Ribbon',
        'wiki_file' => 'Overseas_Service_Ribbon.svg',
        'category_id' => 3,
        'order' => 2
    ]
];

$dir = __DIR__ . '/../assets/images/awards';
if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
}

echo "Starting Wikipedia Awards Download...\n";

foreach ($awardsToFetch as $award) {
    $filename = $award['wiki_file'];
    $url = "https://en.wikipedia.org/w/api.php?action=query&titles=File:" . urlencode($filename) . "&prop=imageinfo&iiprop=url&iiurlwidth=150&format=json";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, 'STORM_Milsim_Agent/1.0');
    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);
    $pages = $data['query']['pages'] ?? [];
    $page = reset($pages);

    if (isset($page['imageinfo'][0])) {
        $info = $page['imageinfo'][0];
        $imgUrl = isset($info['thumburl']) ? $info['thumburl'] : $info['url'];

        $chImg = curl_init();
        curl_setopt($chImg, CURLOPT_URL, $imgUrl);
        curl_setopt($chImg, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($chImg, CURLOPT_USERAGENT, 'STORM_Milsim_Agent/1.0');
        $imgData = curl_exec($chImg);
        curl_close($chImg);

        if ($imgData) {
            $saveName = str_replace('.svg', '.png', $filename);
            file_put_contents("$dir/$saveName", $imgData);
            echo "Downloaded Image: $saveName\n";

            // Check if award already exists
            $stmt = $pdo->prepare("SELECT id FROM awards WHERE name = ?");
            $stmt->execute([$award['name']]);
            if (!$stmt->fetch()) {
                // Insert into database
                $stmt = $pdo->prepare("INSERT INTO awards (category_id, name, description, image, order_index) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([
                    $award['category_id'],
                    $award['name'],
                    "Official " . $award['name'] . " awarded for service and contributions.",
                    $saveName,
                    $award['order']
                ]);
                echo "Inserted into DB: {$award['name']}\n";
            } else {
                echo "Already in DB: {$award['name']}\n";
            }
        }
    } else {
        echo "Failed to find image on Wikipedia API for: $filename\n";
    }
}

echo "Seeding completed!\n";
?>