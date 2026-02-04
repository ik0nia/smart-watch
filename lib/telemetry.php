<?php
declare(strict_types=1);

require_once __DIR__ . '/flespi.php';
require_once __DIR__ . '/ui.php';

function getValue(array $data, string $path)
{
    if (array_key_exists($path, $data)) {
        return $data[$path];
    }

    $segments = explode('.', $path);
    $value = $data;
    foreach ($segments as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return null;
        }
        $value = $value[$segment];
    }

    return $value;
}

function pickLatestMessage(array $messages): ?array
{
    $latest = null;
    $latestTs = null;
    foreach ($messages as $message) {
        if (!is_array($message)) {
            continue;
        }
        $ts = getValue($message, 'server.timestamp');
        if (!is_numeric($ts)) {
            $ts = getValue($message, 'timestamp');
        }
        if (!is_numeric($ts)) {
            continue;
        }
        $ts = (float) $ts;
        if ($latestTs === null || $ts > $latestTs) {
            $latestTs = $ts;
            $latest = $message;
        }
    }

    return $latest;
}

function fetchMessages(array $config, int $limit, string $source = 'channel'): array
{
    $source = $source === 'device' ? 'device' : 'channel';
    $deviceId = trim((string) ($config['device_id'] ?? ''));
    $channelId = trim((string) ($config['channel_id'] ?? ''));

    if ($source === 'device') {
        if ($deviceId === '') {
            return ['ok' => false, 'messages' => [], 'error' => 'Device ID lipseste.'];
        }
        $path = "/gw/devices/{$deviceId}/messages?limit={$limit}";
    } else {
        if ($channelId === '') {
            return ['ok' => false, 'messages' => [], 'error' => 'Channel ID lipseste.'];
        }
        $path = "/gw/channels/{$channelId}/messages?limit={$limit}";
    }

    $response = flespiRequest('GET', $path, null);
    if (!($response['ok'] ?? false)) {
        return [
            'ok' => false,
            'messages' => [],
            'error' => $response['error'] ?? 'Eroare API.',
        ];
    }

    $messages = $response['data']['result'] ?? [];
    if (!is_array($messages)) {
        $messages = [];
    }

    return [
        'ok' => true,
        'messages' => $messages,
        'error' => null,
    ];
}

function fetchTelemetryAll(array $config): ?array
{
    $deviceId = trim((string) ($config['device_id'] ?? ''));
    if ($deviceId === '') {
        return null;
    }
    $response = flespiRequest('GET', "/gw/devices/{$deviceId}/telemetry/all", null);
    if (!($response['ok'] ?? false)) {
        return null;
    }
    $result = $response['data']['result'] ?? null;
    return is_array($result) ? $result : null;
}

function buildTelemetrySnapshot(array $config, int $limit = 30, string $source = 'channel'): array
{
    $messagesResult = fetchMessages($config, $limit, $source);
    $messages = $messagesResult['messages'] ?? [];
    $latestMessage = pickLatestMessage($messages);
    $telemetry = fetchTelemetryAll($config);

    $message = $latestMessage ?? [];
    $timestamp = getValue($message, 'server.timestamp') ?? getValue($message, 'timestamp');
    if (!is_numeric($timestamp) && is_array($telemetry)) {
        $timestamp = $telemetry['server.timestamp'] ?? $telemetry['timestamp'] ?? null;
    }

    $battery = getValue($message, 'battery.level') ?? ($telemetry['battery.level'] ?? null);
    $signal = getValue($message, 'gsm.signal.level')
        ?? getValue($message, 'gsm.signal')
        ?? ($telemetry['gsm.signal.level'] ?? null)
        ?? ($telemetry['gsm.signal'] ?? null);
    $vendor = getValue($message, 'vendor.code') ?? ($telemetry['vendor.code'] ?? null);
    $messageType = getValue($message, 'message.type');
    $steps = getValue($message, 'steps.count')
        ?? getValue($message, 'steps')
        ?? ($telemetry['steps.count'] ?? null)
        ?? ($telemetry['steps'] ?? null);

    $gpsLat = getValue($message, 'position.latitude') ?? ($telemetry['position.latitude'] ?? null);
    $gpsLon = getValue($message, 'position.longitude') ?? ($telemetry['position.longitude'] ?? null);
    $gpsLat = is_numeric($gpsLat) ? (float) $gpsLat : null;
    $gpsLon = is_numeric($gpsLon) ? (float) $gpsLon : null;

    $lbs = [
        'mcc' => getValue($message, 'gsm.mcc') ?? ($telemetry['gsm.mcc'] ?? null),
        'mnc' => getValue($message, 'gsm.mnc') ?? ($telemetry['gsm.mnc'] ?? null),
        'lac' => getValue($message, 'gsm.lac') ?? ($telemetry['gsm.lac'] ?? null),
        'cellid' => getValue($message, 'gsm.cellid') ?? ($telemetry['gsm.cellid'] ?? null),
        'signal' => getValue($message, 'gsm.signal') ?? ($telemetry['gsm.signal'] ?? null),
    ];

    $freshness = (int) ($config['freshness_seconds'] ?? 900);
    $lastSeenSeconds = is_numeric($timestamp) ? max(0, time() - (int) $timestamp) : null;
    $isOnline = $lastSeenSeconds !== null && $lastSeenSeconds <= $freshness;

    return [
        'ok' => $latestMessage !== null || $telemetry !== null,
        'snapshot' => [
            'timestamp' => $timestamp,
            'timestamp_human' => is_numeric($timestamp) ? fmtDate($timestamp, (string) ($config['timezone'] ?? 'UTC')) : '—',
            'last_seen_seconds' => $lastSeenSeconds,
            'last_seen_human' => $lastSeenSeconds !== null ? humanDuration($lastSeenSeconds) : '—',
            'is_online' => $isOnline,
            'battery_level' => $battery,
            'signal_level' => $signal,
            'vendor_code' => $vendor,
            'message_type' => $messageType,
            'steps' => $steps,
            'gps' => [
                'lat' => $gpsLat,
                'lon' => $gpsLon,
            ],
            'lbs' => $lbs,
        ],
        'message' => $latestMessage,
        'telemetry' => $telemetry,
        'error' => $messagesResult['error'] ?? null,
    ];
}

function collectMessageTypes(array $messages): array
{
    $counts = [];
    foreach ($messages as $message) {
        if (!is_array($message)) {
            continue;
        }
        $type = (string) (getValue($message, 'message.type') ?? 'UNKNOWN');
        if ($type === '') {
            $type = 'UNKNOWN';
        }
        $counts[$type] = ($counts[$type] ?? 0) + 1;
    }
    arsort($counts);
    return $counts;
}

function flattenKeys(array $data, string $prefix, array &$counts): void
{
    foreach ($data as $key => $value) {
        $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;
        if (is_array($value)) {
            if ($value === []) {
                $counts[$path] = ($counts[$path] ?? 0) + 1;
                continue;
            }
            flattenKeys($value, $path, $counts);
            continue;
        }
        $counts[$path] = ($counts[$path] ?? 0) + 1;
    }
}

function collectKeyCounts(array $messages): array
{
    $counts = [];
    foreach ($messages as $message) {
        if (!is_array($message)) {
            continue;
        }
        flattenKeys($message, '', $counts);
    }
    arsort($counts);
    return $counts;
}

function collectExamplesByType(array $messages): array
{
    $examples = [];
    foreach ($messages as $message) {
        if (!is_array($message)) {
            continue;
        }
        $type = (string) (getValue($message, 'message.type') ?? 'UNKNOWN');
        if ($type === '') {
            $type = 'UNKNOWN';
        }
        if (!isset($examples[$type])) {
            $examples[$type] = $message;
        }
    }
    ksort($examples);
    return $examples;
}
