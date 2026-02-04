<?php
declare(strict_types=1);

$config = require __DIR__ . '/../lib/bootstrap.php';
requireAuth($config);

$telemetryConfig = $config['telemetry'] ?? [];
$source = (string) ($_GET['source'] ?? ($telemetryConfig['source'] ?? 'channel'));
$limit = (int) ($_GET['limit'] ?? ($telemetryConfig['limit'] ?? 30));
if ($source === 'channel' && empty($config['channel_id']) && !empty($config['device_id'])) {
    $source = 'device';
}

$telemetry = buildTelemetrySnapshot($config, $limit, $source);

header('Content-Type: application/json');
echo json_encode([
    'ok' => $telemetry['ok'] ?? false,
    'snapshot' => $telemetry['snapshot'] ?? [],
    'error' => $telemetry['error'] ?? null,
], JSON_UNESCAPED_SLASHES);
