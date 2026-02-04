<?php
declare(strict_types=1);

$config = require __DIR__ . '/lib/bootstrap.php';

$debugConfig = $config['debug'] ?? [];
$source = (string) ($_GET['source'] ?? ($debugConfig['source'] ?? 'channel'));
$limit = (int) ($_GET['limit'] ?? ($debugConfig['limit'] ?? 200));
$typeFilter = trim((string) ($_GET['type'] ?? ''));

if ($source === 'channel' && empty($config['channel_id']) && !empty($config['device_id'])) {
    $source = 'device';
}

$result = fetchMessages($config, $limit, $source);
$messages = $result['messages'] ?? [];
$types = collectMessageTypes($messages);
$examples = collectExamplesByType($messages);

$filteredMessages = $messages;
if ($typeFilter !== '') {
    $filteredMessages = array_values(array_filter($messages, static function ($message) use ($typeFilter) {
        return is_array($message) && (string) (getValue($message, 'message.type') ?? '') === $typeFilter;
    }));
}

$keyCounts = collectKeyCounts($filteredMessages);
$exportLink = 'api/messages.php?limit=' . $limit
    . '&source=' . rawurlencode($source)
    . ($typeFilter !== '' ? '&type=' . urlencode($typeFilter) : '')
    . '&download=1';
?>
<!doctype html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Debug RF-V48</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header>
    <div class="container header-row">
        <div class="title-group">
            <h1>Debug mesaje</h1>
            <p>Distribuire message.type si campuri raportate.</p>
        </div>
        <div class="header-actions">
            <nav class="nav">
                <a class="nav-link" href="index.php">Dashboard</a>
                <a class="nav-link" href="commands.php">Command Center</a>
                <a class="nav-link" href="settings.php">Setari ceas</a>
                <a class="nav-link active" href="debug.php">Debug</a>
            </nav>
            <a class="button secondary" href="<?= h($exportLink) ?>">Export JSON</a>
        </div>
    </div>
</header>

<main class="container">
    <?php if (!empty($result['error'])): ?>
        <div class="alert error" role="alert"><?= h($result['error']) ?></div>
    <?php endif; ?>

    <section class="panel">
        <h2>Filtru</h2>
        <form method="get" class="filter-form">
            <div class="form-row">
                <div class="field">
                    <label for="type_filter">message.type</label>
                    <select id="type_filter" name="type">
                        <option value="">Toate</option>
                        <?php foreach ($types as $type => $count): ?>
                            <option value="<?= h($type) ?>" <?= $typeFilter === $type ? 'selected' : '' ?>>
                                <?= h($type) ?> (<?= (int) $count ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="source">Sursa</label>
                    <select id="source" name="source">
                        <option value="channel" <?= $source === 'channel' ? 'selected' : '' ?>>channel</option>
                        <option value="device" <?= $source === 'device' ? 'selected' : '' ?>>device</option>
                    </select>
                </div>
                <div class="field">
                    <label for="limit">Limit</label>
                    <input id="limit" name="limit" type="number" min="10" max="500" step="10" value="<?= (int) $limit ?>">
                </div>
            </div>
            <button class="button" type="submit">Aplica</button>
        </form>
    </section>

    <section class="panel">
        <h2>Distribuire message.type</h2>
        <?php if (!$types): ?>
            <p class="helper">Nu exista mesaje.</p>
        <?php else: ?>
            <div class="chip-grid">
                <?php foreach ($types as $type => $count): ?>
                    <span class="chip"><?= h($type) ?> (<?= (int) $count ?>)</span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="panel">
        <h2>Chei distincte (<?= count($keyCounts) ?>)</h2>
        <?php if (!$keyCounts): ?>
            <p class="helper">Nu exista chei.</p>
        <?php else: ?>
            <table class="data-table">
                <thead>
                <tr>
                    <th>Cheie</th>
                    <th>Apariții</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($keyCounts as $key => $count): ?>
                    <tr>
                        <td><?= h($key) ?></td>
                        <td><?= (int) $count ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <section class="panel">
        <h2>Exemple pe message.type</h2>
        <?php if (!$examples): ?>
            <p class="helper">Nu exista exemple.</p>
        <?php else: ?>
            <?php foreach ($examples as $type => $example): ?>
                <details class="panel-details">
                    <summary><?= h($type) ?></summary>
                    <pre><?= h(json_encode($example, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre>
                </details>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
</main>

<footer class="container footer">
    <p>Debug RF-V48 • foloseste export JSON pentru analiza completa.</p>
</footer>
</body>
</html>
