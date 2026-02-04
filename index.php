<?php
declare(strict_types=1);

$config = require __DIR__ . '/lib/bootstrap.php';

$telemetryConfig = $config['telemetry'] ?? [];
$telemetrySource = (string) ($telemetryConfig['source'] ?? 'channel');
$telemetryLimit = (int) ($telemetryConfig['limit'] ?? 30);
if ($telemetrySource === 'channel' && empty($config['channel_id']) && !empty($config['device_id'])) {
    $telemetrySource = 'device';
}

$telemetry = buildTelemetrySnapshot($config, $telemetryLimit, $telemetrySource);
$snapshot = $telemetry['snapshot'] ?? [];
$errors = [];

if (($config['token'] ?? '') === '') {
    $errors[] = 'Tokenul Flespi lipseste. Adauga-l in config.local.php.';
}
if (($config['channel_id'] ?? '') === '' && ($config['device_id'] ?? '') === '') {
    $errors[] = 'Seteaza channel_id sau device_id in config.local.php.';
}
if (!empty($telemetry['error'])) {
    $errors[] = (string) $telemetry['error'];
}

$hasGps = isset($snapshot['gps']['lat'], $snapshot['gps']['lon'])
    && is_numeric($snapshot['gps']['lat'])
    && is_numeric($snapshot['gps']['lon']);

$stepsValue = $snapshot['steps'] ?? null;
$stepsMissing = $stepsValue === null || (is_numeric($stepsValue) && (int) $stepsValue === 0);

$refreshDefault = (string) ($telemetryConfig['refresh_default'] ?? '30');
?>
<!doctype html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>RF-V48 Dashboard</title>
    <link rel="stylesheet" href="assets/style.css">
    <?php if ($hasGps): ?>
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <?php endif; ?>
</head>
<body>
<header>
    <div class="container header-row">
        <div class="title-group">
            <h1>RF-V48 Watch Dashboard</h1>
            <p>Monitorizare ReachFar RF-V48 via flespi.</p>
        </div>
        <div class="header-actions">
            <nav class="nav">
                <a class="nav-link active" href="index.php">Dashboard</a>
                <a class="nav-link" href="commands.php">Command Center</a>
                <a class="nav-link" href="settings.php">Setari ceas</a>
                <a class="nav-link" href="debug.php">Debug</a>
            </nav>
            <div class="refresh-control">
                <label class="sr-only" for="refresh_rate">Refresh</label>
                <select id="refresh_rate" data-refresh-select>
                    <option value="10">Auto 10s</option>
                    <option value="30">Auto 30s</option>
                    <option value="0">Manual</option>
                </select>
            </div>
            <span class="badge <?= !empty($snapshot['is_online']) ? 'online' : 'offline' ?>"
                  data-telemetry="status_badge">
                <?= !empty($snapshot['is_online']) ? 'Online' : 'Ultima activitate: ' . h($snapshot['last_seen_human'] ?? '—') ?>
            </span>
            <button class="button secondary" type="button" onclick="location.reload()">Reincarca</button>
        </div>
    </div>
</header>

<main class="container dashboard" data-telemetry-endpoint="api/telemetry.php">
    <?php foreach ($errors as $error): ?>
        <div class="alert error" role="alert"><?= h($error) ?></div>
    <?php endforeach; ?>

    <section class="panel status-panel">
        <div class="status-grid">
            <article class="status-card">
                <div class="label">Ultimul mesaj</div>
                <div class="value" data-telemetry="timestamp_human"><?= h($snapshot['timestamp_human'] ?? '—') ?></div>
                <div class="helper">Acum <span data-telemetry="last_seen_human"><?= h($snapshot['last_seen_human'] ?? '—') ?></span></div>
            </article>
            <article class="status-card">
                <div class="label">Baterie</div>
                <div class="value" data-telemetry="battery_level"><?= h($snapshot['battery_level'] ?? '—') ?></div>
                <div class="helper">%</div>
            </article>
            <article class="status-card">
                <div class="label">Semnal GSM</div>
                <div class="value" data-telemetry="signal_level"><?= h($snapshot['signal_level'] ?? '—') ?></div>
                <div class="helper">nivel</div>
            </article>
            <article class="status-card">
                <div class="label">Vendor</div>
                <div class="value" data-telemetry="vendor_code"><?= h($snapshot['vendor_code'] ?? '—') ?></div>
                <div class="helper">message.type: <span data-telemetry="message_type"><?= h($snapshot['message_type'] ?? '—') ?></span></div>
            </article>
        </div>
    </section>

    <div class="dashboard-grid">
        <section class="panel location-panel">
            <div class="panel-header">
                <h2>Locatie</h2>
                <?php if ($hasGps): ?>
                    <span class="badge online">GPS fix</span>
                <?php else: ?>
                    <span class="badge offline">No GPS fix yet</span>
                <?php endif; ?>
            </div>
            <div class="location-body">
                <div class="gps-section" data-section="gps" <?= $hasGps ? '' : 'hidden' ?>>
                    <div id="map" data-map
                         data-lat="<?= h((string) ($snapshot['gps']['lat'] ?? '')) ?>"
                         data-lon="<?= h((string) ($snapshot['gps']['lon'] ?? '')) ?>"></div>
                    <div class="helper">
                        Lat: <span data-telemetry="gps.lat"><?= h($snapshot['gps']['lat'] ?? '—') ?></span>,
                        Lon: <span data-telemetry="gps.lon"><?= h($snapshot['gps']['lon'] ?? '—') ?></span>
                    </div>
                </div>
                <div class="lbs-section" data-section="lbs" <?= $hasGps ? 'hidden' : '' ?>>
                    <p class="helper">Nu exista coordonate GPS in payload. Se afiseaza LBS:</p>
                    <div class="lbs-grid">
                        <div><span class="label">MCC</span><span data-telemetry="lbs.mcc"><?= h($snapshot['lbs']['mcc'] ?? '—') ?></span></div>
                        <div><span class="label">MNC</span><span data-telemetry="lbs.mnc"><?= h($snapshot['lbs']['mnc'] ?? '—') ?></span></div>
                        <div><span class="label">LAC</span><span data-telemetry="lbs.lac"><?= h($snapshot['lbs']['lac'] ?? '—') ?></span></div>
                        <div><span class="label">Cell ID</span><span data-telemetry="lbs.cellid"><?= h($snapshot['lbs']['cellid'] ?? '—') ?></span></div>
                        <div><span class="label">Signal</span><span data-telemetry="lbs.signal"><?= h($snapshot['lbs']['signal'] ?? '—') ?></span></div>
                    </div>
                </div>
            </div>
        </section>

        <section class="panel activity-panel">
            <div class="panel-header">
                <h2>Activitate</h2>
                <a class="link" href="debug.php">Deschide debug</a>
            </div>
            <div class="activity-grid">
                <div class="activity-card">
                    <div class="label">Pasi</div>
                    <div class="value" data-telemetry="steps"><?= h($stepsValue ?? '—') ?></div>
                </div>
                <div class="activity-card">
                    <div class="label">Online</div>
                    <div class="value" data-telemetry="is_online"><?= !empty($snapshot['is_online']) ? 'Da' : 'Nu' ?></div>
                </div>
            </div>
            <div class="helper steps-note" <?= $stepsMissing ? '' : 'hidden' ?>>
                Nu exista pasi raportati in payload pentru acest firmware.
                Verifica <a href="debug.php">debug</a> pentru campuri reale.
            </div>
        </section>
    </div>
</main>

<footer class="container footer">
    <p>Dashboard RF-V48 • date actualizate automat prin API.</p>
</footer>

<script src="assets/app.js"></script>
<?php if ($hasGps): ?>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<?php endif; ?>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const refreshSelect = document.getElementById('refresh_rate');
        if (refreshSelect) {
            refreshSelect.value = '<?= h($refreshDefault) ?>';
        }
    });
</script>
</body>
</html>
