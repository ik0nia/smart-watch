<?php
declare(strict_types=1);

$config = require __DIR__ . '/../lib/bootstrap.php';
requireAuth($config);

$debugConfig = $config['debug'] ?? [];
$source = (string) ($_GET['source'] ?? ($debugConfig['source'] ?? 'channel'));
$limit = (int) ($_GET['limit'] ?? ($debugConfig['limit'] ?? 200));
$typeFilter = trim((string) ($_GET['type'] ?? ''));
$download = isset($_GET['download']);

if ($source === 'channel' && empty($config['channel_id']) && !empty($config['device_id'])) {
    $source = 'device';
}

$result = fetchMessages($config, $limit, $source);
$messages = $result['messages'] ?? [];
if ($typeFilter !== '') {
    $messages = array_values(array_filter($messages, static function ($message) use ($typeFilter) {
        return is_array($message) && (string) (getValue($message, 'message.type') ?? '') === $typeFilter;
    }));
}

if ($download) {
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="messages.json"');
} else {
    header('Content-Type: application/json');
}

echo json_encode([
    'ok' => $result['ok'] ?? false,
    'source' => $source,
    'messages' => $messages,
    'error' => $result['error'] ?? null,
], JSON_UNESCAPED_SLASHES);
