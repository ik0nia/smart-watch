<?php
declare(strict_types=1);

$config = require __DIR__ . '/../config.php';
if (file_exists(__DIR__ . '/../config.local.php')) {
    $localConfig = require __DIR__ . '/../config.local.php';
    if (is_array($localConfig)) {
        $config = array_replace_recursive($config, $localConfig);
    }
}

$timezone = $config['timezone'] ?? 'UTC';
date_default_timezone_set($timezone);

$GLOBALS['app_config'] = $config;
$GLOBALS['flespi_config'] = [
    'base_url' => $config['base_url'] ?? 'https://flespi.io',
    'token' => $config['token'] ?? '',
    'api_timeout' => $config['api_timeout'] ?? 20,
];

require_once __DIR__ . '/ui.php';
require_once __DIR__ . '/storage.php';
require_once __DIR__ . '/flespi.php';
require_once __DIR__ . '/commands.php';
require_once __DIR__ . '/telemetry.php';
require_once __DIR__ . '/auth.php';

return $config;
