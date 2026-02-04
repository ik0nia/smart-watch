<?php
declare(strict_types=1);

require_once __DIR__ . '/storage.php';

function flespiRequest(string $method, string $path, ?array $payload = null): array
{
    $config = $GLOBALS['flespi_config'] ?? [];
    $token = trim((string) ($config['token'] ?? ''));
    $baseUrl = rtrim((string) ($config['base_url'] ?? 'https://flespi.io'), '/');
    $timeout = (int) ($config['api_timeout'] ?? 20);

    if ($token === '') {
        return [
            'ok' => false,
            'status' => 0,
            'data' => null,
            'raw' => null,
            'error' => 'Tokenul Flespi lipseste.',
        ];
    }

    $url = $baseUrl . $path;
    $ch = curl_init($url);
    if ($ch === false) {
        return [
            'ok' => false,
            'status' => 0,
            'data' => null,
            'raw' => null,
            'error' => 'Nu pot initializa conexiunea.',
        ];
    }

    $headers = [
        'Accept: application/json',
        'Authorization: FlespiToken ' . $token,
    ];

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);

    if ($payload !== null) {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return [
                'ok' => false,
                'status' => 0,
                'data' => null,
                'raw' => null,
                'error' => 'Payload JSON invalid.',
            ];
        }
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
    }

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        $result = [
            'ok' => false,
            'status' => $status,
            'data' => null,
            'raw' => null,
            'error' => $error ?: 'Eroare necunoscuta.',
        ];
    } else {
        $decoded = json_decode($response, true);
        $jsonError = json_last_error();
        $ok = $status >= 200 && $status < 300;
        $result = [
            'ok' => $ok,
            'status' => $status,
            'data' => $jsonError === JSON_ERROR_NONE ? $decoded : null,
            'raw' => $response,
            'error' => $jsonError === JSON_ERROR_NONE ? null : 'Raspuns invalid din API.',
        ];
        if (!$ok && $result['error'] === null) {
            $result['error'] = 'HTTP ' . $status;
        }
    }

    if (!($result['ok'] ?? false)) {
        appendJsonLine('api_errors.log', [
            'timestamp' => date('c'),
            'method' => strtoupper($method),
            'path' => $path,
            'status' => $status,
            'payload' => $payload,
            'error' => $result['error'] ?? null,
            'response' => $result['raw'] ?? null,
        ]);
    }

    return $result;
}
