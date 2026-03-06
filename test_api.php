<?php
echo "<h3>Testing Alternative Holiday APIs</h3>";

// Test 1: Nager.Date API
echo "<h4>Test 1: Nager.Date API</h4>";
$url1 = "https://date.nager.at/api/v3/PublicHolidays/2026/ID";
echo "<p>URL: $url1</p>";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url1);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "<p>HTTP Code: $httpCode</p>";
if ($error) {
    echo "<p style='color:red'>cURL Error: $error</p>";
} else if ($response) {
    $data = json_decode($response, true);
    if (is_array($data) && count($data) > 0) {
        echo "<p style='color:green'>Success! Got " . count($data) . " holidays</p>";
        echo "<ul>";
        foreach ($data as $h) {
            echo "<li>" . htmlspecialchars($h['localName']) . " (" . htmlspecialchars($h['name']) . ") - " . $h['date'] . "</li>";
        }
        echo "</ul>";
    } else {
        echo "<p style='color:red'>Unexpected response: " . htmlspecialchars(substr($response, 0, 500)) . "</p>";
    }
} else {
    echo "<p style='color:red'>No response</p>";
}
?>
