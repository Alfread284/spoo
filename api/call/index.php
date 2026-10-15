<?php
/**
 * Namecheap / cPanel PHP API Proxy for SpoofWave API
 * Automatically executed when /api/call is requested on Apache/Namecheap hosting
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Accept');
header('Access-Control-Allow-Methods: POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$rawInput = file_get_contents('php://input');
$body = json_decode($rawInput, true);

if (!$body) {
    $body = $_POST;
}

$username = trim($body['username'] ?? 'adam87');
$password = $body['password'] ?? 'Japan@2022';
$callerid = preg_replace('/\D/', '', $body['callerid'] ?? '');
$sip_id   = preg_replace('/\D/', '', $body['sip_account_id'] ?? '');

if (empty($callerid)) {
    http_response_code(400);
    echo json_encode([
        'status' => 400,
        'data' => [
            'success' => false,
            'message' => 'Caller ID is required and must contain digits only.'
        ]
    ]);
    exit;
}

$payload = [
    'username' => $username,
    'password' => $password,
    'callerid' => $callerid,
];

if (!empty($sip_id)) {
    $payload['sip_account_id'] = (int)$sip_id;
}

$ch = curl_init('https://spoofwave.com/api/call');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json',
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36'
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($response === false) {
    http_response_code(500);
    echo json_encode([
        'status' => 500,
        'data' => [
            'success' => false,
            'message' => 'Failed to connect to SpoofWave API: ' . $curlError
        ]
    ]);
    exit;
}

$responseData = json_decode($response, true);
if (!$responseData) {
    $responseData = [
        'success' => ($httpCode >= 200 && $httpCode < 300),
        'message' => substr($response, 0, 300) ?: 'Response received'
    ];
}

if (is_array($responseData) && isset($responseData['password'])) {
    $responseData['password'] = '[hidden]';
}

http_response_code($httpCode ?: 200);
echo json_encode([
    'status' => $httpCode ?: 200,
    'data' => $responseData
]);
