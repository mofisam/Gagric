<?php
header("Content-Type: application/json");

$domain = $_GET['domain'] ?? '';

if (!$domain) {
    echo json_encode(["error" => "No domain provided"]);
    exit;
}

$apiKey = "YOUR_API_KEY_HERE";

$url = "https://www.whoisxmlapi.com/whoisserver/WhoisService"
     . "?apiKey=$apiKey"
     . "&domainName=$domain"
     . "&outputFormat=JSON";

$response = file_get_contents($url);

if (!$response) {
    echo json_encode(["error" => "WHOIS lookup failed"]);
    exit;
}

$data = json_decode($response, true);

if (!isset($data['WhoisRecord'])) {
    echo json_encode(["available" => true]);
    exit;
}

$record = $data['WhoisRecord'];

echo json_encode([
    "available" => false,
    "registrar" => $record['registrarName'] ?? "Unknown",
    "created"   => $record['createdDate'] ?? "Unknown",
    "expires"   => $record['expiresDate'] ?? "Unknown"
]);
