<?php

require __DIR__ . '/../services/vendor-management/vendor/autoload.php';

use GuzzleHttp\Client;

$client = new Client();
$url = 'http://auth-service:8000/api/internal/verify-token';

echo "Testing connection to Auth Service at $url...\n";

try {
    $response = $client->post($url, [
        'json' => ['token' => 'invalid-test-token'],
        'headers' => ['Accept' => 'application/json']
    ]);
    
    echo "Response Status: " . $response->getStatusCode() . "\n";
    echo "Response Body: " . $response->getBody() . "\n";
} catch (\GuzzleHttp\Exception\ClientException $e) {
    echo "Caught expected ClientException (Unauthorized):\n";
    echo "Status: " . $e->getResponse()->getStatusCode() . "\n";
    echo "Body: " . $e->getResponse()->getBody() . "\n";
} catch (\Exception $e) {
    echo "Connection failed: " . $e->getMessage() . "\n";
}
