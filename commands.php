<?php
declare(strict_types=1);

$config = require __DIR__ . '/lib/bootstrap.php';

$commandConfig = $config['command'] ?? [];
$presets = $commandConfig['presets'] ?? [];
$dangerousKeywords = $commandConfig['dangerous_keywords'] ?? ['FACTORY', 'RESET'];

$errors = [];
$notices = [];
$commandResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send_command') {
    $payload = trim((string) ($_POST['payload_text'] ?? ''));
    $dangerous = isDangerousPayload($payload, $dangerousKeywords);
    if ($dangerous && empty($_POST['confirm_dangerous'])) {
        $errors[] = 'Comanda este periculoasa. Confirmarea este obligatorie.';
    } else {
        $commandResult = sendCommand($_POST, $config);
        if ($commandResult['ok'] ?? false) {
            $notices[] = 'Comanda a fost trimisa catre ceas.';
        } else {
            $details = $commandResult['error'] ?? '';
            if ($details === '' && !empty($commandResult['raw'])) {
                $details = (string) $commandResult['raw'];
            }
            $errors[] = 'Trimiterea comenzii a esuat (HTTP ' . ($commandResult['status'] ?? 0) . '). ' . $details;
        }
    }
}

$recentCommands = readCommandLog(20);
$presetsJson = json_encode($presets, JSON_UNESCAPED_SLASHES) ?: '[]';
?>
<!doctype html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Command Center RF-V48</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header>
    <div class="container header-row">
        <div class="title-group">
            <h1>Command Center</h1>
            <p>Trimite comenzi catre ceasul RF-V48 prin flespi.</p>
        </div>
        <div class="header-actions">
            <nav class="nav">
                <a class="nav-link" href="index.php">Dashboard</a>
                <a class="nav-link active" href="commands.php">Command Center</a>
                <a class="nav-link" href="settings.php">Setari ceas</a>
                <a class="nav-link" href="debug.php">Debug</a>
            </nav>
            <button class="button secondary" type="button" onclick="location.reload()">Reincarca</button>
        </div>
    </div>
</header>

<main class="container">
    <?php foreach ($errors as $error): ?>
        <div class="alert error" role="alert"><?= h($error) ?></div>
    <?php endforeach; ?>
    <?php foreach ($notices as $notice): ?>
        <div class="alert success" role="status"><?= h($notice) ?></div>
    <?php endforeach; ?>

    <section class="panel">
        <h2>Trimite comanda</h2>
        <form id="command_form" method="post" class="command-form" data-dangerous-keywords="<?= h(json_encode($dangerousKeywords)) ?>">
            <input type="hidden" name="action" value="send_command">
            <div class="field">
                <label for="command_preset">Preset rapid</label>
                <select id="command_preset" data-presets='<?= h($presetsJson) ?>'>
                    <option value="">Selecteaza un preset</option>
                    <?php foreach ($presets as $index => $preset): ?>
                        <option value="<?= (int) $index ?>"><?= h($preset['label'] ?? ('Preset ' . $index)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-row">
                <div class="field">
                    <label for="command_mode">Mode</label>
                    <select id="command_mode" name="command_mode">
                        <option value="custom" <?= ($commandConfig['mode_default'] ?? 'custom') === 'custom' ? 'selected' : '' ?>>custom</option>
                        <option value="reachfar_raw" <?= ($commandConfig['mode_default'] ?? '') === 'reachfar_raw' ? 'selected' : '' ?>>reachfar_raw</option>
                    </select>
                </div>
                <div class="field">
                    <label for="queue_mode">Queue</label>
                    <select id="queue_mode" name="queue_mode">
                        <option value="immediate" <?= ($commandConfig['queue_default'] ?? 'immediate') === 'immediate' ? 'selected' : '' ?>>immediate</option>
                        <option value="queue" <?= ($commandConfig['queue_default'] ?? '') === 'queue' ? 'selected' : '' ?>>queue</option>
                    </select>
                </div>
                <div class="field">
                    <label for="timeout">Timeout (sec)</label>
                    <input id="timeout" name="timeout" type="number" min="0" step="1"
                           value="<?= h((string) ($commandConfig['timeout_default'] ?? 30)) ?>">
                </div>
            </div>

            <div class="field">
                <label for="payload_text">Payload</label>
                <textarea id="payload_text" name="payload_text" placeholder="Ex: UPLOAD,10"></textarea>
                <div class="helper">
                    Accepta payload-uri SMS-like (ex: pw,123456,where#). Se trimit prin properties.payload.
                </div>
            </div>

            <label class="checkbox-row">
                <input id="confirm_dangerous" type="checkbox" name="confirm_dangerous" value="1">
                Confirm comanda periculoasa (FACTORY / RESET)
            </label>

            <button class="button" type="submit">Trimite comanda</button>
        </form>

        <?php if ($commandResult): ?>
            <details class="panel-details" open>
                <summary>Raspuns API</summary>
                <pre><?= h(json_encode($commandResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre>
            </details>
        <?php endif; ?>
    </section>

    <section class="panel">
        <h2>Ultimele comenzi</h2>
        <?php if (!$recentCommands): ?>
            <p class="helper">Nu exista comenzi inregistrate.</p>
        <?php else: ?>
            <table class="data-table">
                <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>Payload</th>
                    <th>Endpoint</th>
                    <th>Status</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($recentCommands as $entry): ?>
                    <tr>
                        <td><?= h($entry['timestamp'] ?? '') ?></td>
                        <td><?= h($entry['payload'] ?? '') ?></td>
                        <td><?= h($entry['endpoint'] ?? '') ?></td>
                        <td><?= h((string) ($entry['status'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>
</main>

<footer class="container footer">
    <p>Comenzile sunt logate in storage/commands.log.</p>
</footer>

<script src="assets/app.js"></script>
</body>
</html>
