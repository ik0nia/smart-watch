<?php
declare(strict_types=1);

$config = require __DIR__ . '/../lib/bootstrap.php';
requireAuth($config);

$input = $_POST;
if (!$input) {
    $raw = file_get_contents('php://input');
    if ($raw !== false && $raw !== '') {
        $decoded = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $input = $decoded;
        }
    }
}

$result = sendCommand($input, $config);

header('Content-Type: application/json');
echo json_encode([
    'ok' => $result['ok'] ?? false,
    'status' => $result['status'] ?? 0,
    'data' => $result['data'] ?? null,
    'raw' => $result['raw'] ?? null,
    'error' => $result['error'] ?? null,
    'endpoint' => $result['endpoint'] ?? null,
], JSON_UNESCAPED_SLASHES);
