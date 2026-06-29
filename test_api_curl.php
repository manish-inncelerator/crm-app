<?php
$url = "https://sheetdb.io/api/v1/3ygnp4vnvd4z4";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
curl_close($ch);
echo "Response: " . substr($response, 0, 500);
