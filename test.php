<?php

require __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Setup the output file
$file = fopen(__DIR__ . '/log.txt', 'w');

// Get access token
$clientId = $_ENV['CLIENT_ID'];
$refreshToken = $_ENV['REFRESH_TOKEN'];
$guzzle = new \GuzzleHttp\Client();
$response = $guzzle->post('https://id.planday.com/connect/token', [
    'form_params' => [
        'grant_type' => 'refresh_token',
        'client_id' => $clientId,
        'refresh_token' => $refreshToken,
    ],
]);
$data = json_decode((string) $response->getBody(), false, 512, JSON_THROW_ON_ERROR);
$accessToken = $data->access_token;

// Try 10000 requests, reinitializing the client every time.
for ($i=0; $i < 10000; $i++) {
    $guzzle = new \GuzzleHttp\Client();
    try {
        $start = hrtime(true);
        $guzzle->get('https://openapi.planday.com/hr/v1/employees/deactivated', [
            'query' => [
                'offset' => 0,
                'limit' => 50,
            ],
            'headers' => [
                'X-ClientId' => $clientId,
                'Authorization' => 'Bearer '.$accessToken,
                'Accept' => 'application/json',
            ],
        ]);
        $stop = hrtime(true);
        $duration = round(($stop - $start) / 1e9, 4);
        fwrite($file, "Request $i successful " . (new DateTime())->format(DateTime::ATOM) . " ({$duration} seconds)" . PHP_EOL);
    } catch (\GuzzleHttp\Exception\RequestException $e) {
        fwrite($file, "Request $i failed " . (new DateTime())->format(DateTime::ATOM) . PHP_EOL);
        if ($e->getResponse()->getBody()) {
            fwrite($file, $e->getResponse()->getBody()->getContents() . PHP_EOL);
        }
    }
}
fclose($file);
