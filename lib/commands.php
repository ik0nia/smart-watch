<?php
declare(strict_types=1);

require_once __DIR__ . '/flespi.php';
require_once __DIR__ . '/storage.php';

function normalizePayload(string $payload, ?string $format = null, bool $appendCrlf = false): string
{
    $output = $payload;
    if ($appendCrlf) {
        $output .= "\r\n";
    }
    if ($format === 'hex') {
        $output = preg_replace('/[^0-9a-fA-F]/', '', $output) ?? $output;
    }

    return $output;
}

function buildCommand(array $input, array $config): array
{
    $mode = (string) ($input['command_mode'] ?? ($config['command']['mode_default'] ?? 'custom'));
    $queueMode = (string) ($input['queue_mode'] ?? ($config['command']['queue_default'] ?? 'immediate'));
    $payloadText = trim((string) ($input['payload_text'] ?? ''));
    $timeoutRaw = $input['timeout'] ?? ($config['command']['timeout_default'] ?? null);

    if ($payloadText === '') {
        return [
            'ok' => false,
            'error' => 'Payload-ul este gol.',
        ];
    }

    $deviceId = trim((string) ($config['device_id'] ?? ''));
    if ($deviceId === '') {
        return [
            'ok' => false,
            'error' => 'Device ID lipseste in config.',
        ];
    }

    $commandName = $mode === 'reachfar_raw' ? 'reachfar_raw' : 'custom';
    // ReachFar RF-V48 expects payload in properties.payload; older payload formats caused HTTP 400.
    $command = [
        'name' => $commandName,
        'properties' => [
            'payload' => $payloadText,
        ],
    ];
    if ($timeoutRaw !== null && $timeoutRaw !== '') {
        $command['timeout'] = max(0, (int) $timeoutRaw);
    }

    $endpoint = $queueMode === 'queue'
        ? "/gw/devices/{$deviceId}/commands-queue"
        : "/gw/devices/{$deviceId}/commands";

    return [
        'ok' => true,
        'command' => $command,
        'endpoint' => $endpoint,
        'payload' => $payloadText,
        'queue_mode' => $queueMode,
        'command_mode' => $mode,
    ];
}

function sendCommand(array $input, array $config): array
{
    $built = buildCommand($input, $config);
    if (!($built['ok'] ?? false)) {
        return [
            'ok' => false,
            'status' => 0,
            'data' => null,
            'raw' => null,
            'error' => $built['error'] ?? 'Eroare comanda.',
        ];
    }

    $result = flespiRequest('POST', $built['endpoint'], [$built['command']]);

    appendJsonLine('commands.log', [
        'timestamp' => date('c'),
        'payload' => $built['payload'],
        'endpoint' => $built['endpoint'],
        'status' => $result['status'] ?? 0,
        'response' => $result['raw'] ?? null,
        'ok' => $result['ok'] ?? false,
        'command_mode' => $built['command_mode'],
        'queue_mode' => $built['queue_mode'],
    ]);

    return $result + [
        'endpoint' => $built['endpoint'],
        'command' => $built['command'],
    ];
}

function readCommandLog(int $limit = 20): array
{
    return array_reverse(readJsonLines('commands.log', $limit));
}

function isDangerousPayload(string $payload, array $keywords): bool
{
    $upper = strtoupper($payload);
    foreach ($keywords as $keyword) {
        if ($keyword !== '' && strpos($upper, strtoupper($keyword)) !== false) {
            return true;
        }
    }

    return false;
}
