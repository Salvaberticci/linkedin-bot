<?php

define('CRON_RUN', true);

require_once __DIR__ . '/index.php';

$allowedTokens = ['6p8Zz3jLPgvWY5lrusJwKD2M0E7fIabQ'];

$token = $_GET['token'] ?? '';

if (!in_array($token, $allowedTokens, true)) {
    http_response_code(403);
    echo json_encode(['error' => 'Token inválido']);
    exit;
}

$bot = new LinkedInBot();
ob_start();
$bot->run();
$output = ob_get_clean();

echo json_encode(['status' => 'ok', 'output' => $output]);
