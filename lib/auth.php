<?php
declare(strict_types=1);

function parseBasicAuth(): array
{
    $user = $_SERVER['PHP_AUTH_USER'] ?? null;
    $pass = $_SERVER['PHP_AUTH_PW'] ?? null;

    if ($user !== null && $pass !== null) {
        return [$user, $pass];
    }

    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (stripos($header, 'basic ') !== 0) {
        return [null, null];
    }

    $decoded = base64_decode(substr($header, 6));
    if ($decoded === false || strpos($decoded, ':') === false) {
        return [null, null];
    }

    [$user, $pass] = explode(':', $decoded, 2);
    return [$user, $pass];
}

function requireAuth(array $config): void
{
    $auth = $config['auth'] ?? [];
    $user = (string) ($auth['user'] ?? '');
    $pass = (string) ($auth['pass'] ?? '');
    $token = (string) ($auth['token'] ?? '');

    $tokenHeader = $_SERVER['HTTP_X_APP_TOKEN'] ?? '';
    $tokenQuery = $_GET['token'] ?? '';
    if ($token !== '' && hash_equals($token, (string) $tokenHeader ?: (string) $tokenQuery)) {
        return;
    }

    if ($user !== '' || $pass !== '') {
        [$sentUser, $sentPass] = parseBasicAuth();
        if ($sentUser !== null && hash_equals($user, (string) $sentUser) && hash_equals($pass, (string) $sentPass)) {
            return;
        }
        header('WWW-Authenticate: Basic realm="RF-V48"');
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode([
            'ok' => false,
            'error' => 'Autentificare necesara.',
        ], JSON_UNESCAPED_SLASHES);
        exit;
    }

    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode([
        'ok' => false,
        'error' => 'Configureaza auth in config.local.php.',
    ], JSON_UNESCAPED_SLASHES);
    exit;
}
