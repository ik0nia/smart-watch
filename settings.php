<?php
declare(strict_types=1);

$config = require __DIR__ . '/lib/bootstrap.php';

function findSetting(array $sections, string $settingId): ?array
{
    foreach ($sections as $section) {
        $items = $section['items'] ?? [];
        foreach ($items as $item) {
            if (($item['id'] ?? '') === $settingId) {
                return $item;
            }
        }
    }

    return null;
}

function collectDefaultValues(array $fields): array
{
    $values = [];
    foreach ($fields as $field) {
        $name = $field['name'] ?? '';
        if ($name === '') {
            continue;
        }
        if (array_key_exists('default', $field)) {
            $values[$name] = (string) $field['default'];
        }
    }

    return $values;
}

function resolveFieldValue(array $field, array $postedValues): string
{
    $name = $field['name'] ?? '';
    if ($name === '') {
        return '';
    }
    if (array_key_exists($name, $postedValues)) {
        return (string) $postedValues[$name];
    }
    if (array_key_exists('default', $field)) {
        return (string) $field['default'];
    }

    return '';
}

function buildPayload(
    string $template,
    array $fields,
    array $values,
    array &$errors,
    bool $validate = true
): string {
    $payload = $template;
    foreach ($fields as $field) {
        $name = $field['name'] ?? '';
        if ($name === '') {
            continue;
        }
        $type = $field['type'] ?? 'text';
        if ($type === 'checkbox') {
            $value = array_key_exists($name, $values)
                ? (string) ($field['value_on'] ?? '1')
                : (string) ($field['value_off'] ?? '0');
        } else {
            $value = trim((string) ($values[$name] ?? ''));
        }
        if ($validate && !empty($field['required']) && $value === '') {
            $errors[] = 'Campul "' . ($field['label'] ?? $name) . '" este obligatoriu.';
        }
        $payload = str_replace('{' . $name . '}', $value, $payload);
    }

    return $payload;
}

$commandConfig = $config['command'] ?? [];
$settingsSections = $config['settings']['sections'] ?? [];
$deviceId = trim((string) ($config['device_id'] ?? ''));
$channelId = trim((string) ($config['channel_id'] ?? ''));

$errors = [];
$notices = [];
$commandResult = null;
$submittedSettingId = null;
$submittedValues = [];

if (($config['token'] ?? '') === '') {
    $errors[] = 'Tokenul Flespi lipseste. Adauga-l in config.local.php.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send_setting') {
    $submittedSettingId = trim((string) ($_POST['setting_id'] ?? ''));
    $submittedValues = $_POST['fields'][$submittedSettingId] ?? [];

    if (($config['token'] ?? '') === '') {
        $errors[] = 'Nu pot trimite setarea fara token.';
    } else {
        $setting = findSetting($settingsSections, $submittedSettingId);
        if (!$setting) {
            $errors[] = 'Setarea solicitata nu exista.';
        } else {
            $fields = $setting['fields'] ?? [];
            $template = (string) ($setting['payload_template'] ?? '');
            $payloadErrors = [];
            $payload = buildPayload($template, $fields, $submittedValues, $payloadErrors, true);
            $errors = array_merge($errors, $payloadErrors);

            if ($template === '') {
                $errors[] = 'Setarea nu are definit un payload_template.';
            }

            if (!$errors) {
                $payload = normalizePayload(
                    $payload,
                    (string) ($setting['format'] ?? 'ascii'),
                    !empty($setting['append_crlf'])
                );
                $commandInput = [
                    'command_mode' => (string) ($setting['command_mode'] ?? ($commandConfig['mode_default'] ?? 'custom')),
                    'queue_mode' => (string) ($setting['queue_mode'] ?? ($commandConfig['queue_default'] ?? 'immediate')),
                    'timeout' => $setting['timeout'] ?? ($commandConfig['timeout_default'] ?? null),
                    'payload_text' => $payload,
                ];
                $commandResult = sendCommand($commandInput, $config);
                if ($commandResult['ok'] ?? false) {
                    $notices[] = 'Setarea a fost trimisa catre ceas.';
                } else {
                    $errors[] = 'Trimiterea comenzii a esuat (HTTP ' . ($commandResult['status'] ?? 0) . ').';
                }
            }
        }
    }
}
?>
<!doctype html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Setari ceas RF-V48</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header>
    <div class="container header-row">
        <div class="title-group">
            <h1>Setari ceas RF-V48</h1>
            <p>Configureaza rapid ceasul seniorilor, in stilul aplicatiei Setracker 2.</p>
        </div>
        <div class="header-actions">
            <nav class="nav">
                <a class="nav-link" href="index.php">Dashboard</a>
                <a class="nav-link" href="commands.php">Command Center</a>
                <a class="nav-link active" href="settings.php">Setari ceas</a>
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

    <section class="panel settings-intro">
        <h2>Ghid rapid</h2>
        <p class="helper">
            Fiecare setare genereaza o comanda ReachFar care este trimisa prin API-ul flespi.
            Ajusteaza sabloanele in <strong>config.local.php</strong> conform documentatiei:
            <a href="https://flespi.com/protocols/reachfar#parameters" target="_blank" rel="noopener">flespi ReachFar</a>.
        </p>
        <div class="settings-info-grid">
            <div class="info-card">
                <div class="label">Target API</div>
                <div class="value">Device commands</div>
            </div>
            <div class="info-card">
                <div class="label">Device ID</div>
                <div class="value"><?= h($deviceId !== '' ? $deviceId : '—') ?></div>
            </div>
            <div class="info-card">
                <div class="label">Channel ID</div>
                <div class="value"><?= h($channelId !== '' ? $channelId : '—') ?></div>
            </div>
            <div class="info-card">
                <div class="label">Queue implicit</div>
                <div class="value"><?= h((string) ($commandConfig['queue_default'] ?? 'immediate')) ?></div>
            </div>
            <div class="info-card">
                <div class="label">Mode implicit</div>
                <div class="value"><?= h((string) ($commandConfig['mode_default'] ?? 'custom')) ?></div>
            </div>
        </div>
    </section>

    <?php foreach ($settingsSections as $section): ?>
        <?php
        $sectionItems = $section['items'] ?? [];
        $sectionIcon = $section['icon'] ?? 'settings';
        ?>
        <section class="panel settings-section">
            <div class="section-header">
                <span class="icon"><?= iconSvg($sectionIcon) ?></span>
                <div>
                    <h2><?= h($section['title'] ?? 'Setari') ?></h2>
                    <?php if (!empty($section['description'])): ?>
                        <p class="helper"><?= h($section['description']) ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="settings-grid">
                <?php foreach ($sectionItems as $item): ?>
                    <?php
                    $itemId = (string) ($item['id'] ?? '');
                    $itemFields = $item['fields'] ?? [];
                    $template = (string) ($item['payload_template'] ?? '');
                    $defaultValues = collectDefaultValues($itemFields);
                    $currentValues = $submittedSettingId === $itemId ? $submittedValues : $defaultValues;
                    $previewErrors = [];
                    $payloadPreview = $template !== ''
                        ? buildPayload($template, $itemFields, $currentValues, $previewErrors, false)
                        : '';
                    ?>
                    <article class="setting-item">
                        <div class="setting-item-header">
                            <span class="icon"><?= iconSvg($item['icon'] ?? 'settings') ?></span>
                            <div>
                                <h3><?= h($item['label'] ?? 'Setare') ?></h3>
                                <?php if (!empty($item['description'])): ?>
                                    <p class="helper"><?= h($item['description']) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <form method="post" class="setting-form"
                              data-template="<?= h($template) ?>"
                              data-preview="#preview-<?= h($itemId) ?>">
                            <input type="hidden" name="action" value="send_setting">
                            <input type="hidden" name="setting_id" value="<?= h($itemId) ?>">
                            <div class="field-grid">
                                <?php foreach ($itemFields as $field): ?>
                                    <?php
                                    $fieldName = (string) ($field['name'] ?? '');
                                    if ($fieldName === '') {
                                        continue;
                                    }
                                    $fieldId = $itemId . '_' . $fieldName;
                                    $fieldType = (string) ($field['type'] ?? 'text');
                                    $fieldLabel = (string) ($field['label'] ?? $fieldName);
                                    $fieldValue = resolveFieldValue($field, $currentValues);
                                    ?>
                                    <div class="field">
                                        <label for="<?= h($fieldId) ?>"><?= h($fieldLabel) ?></label>
                                        <?php if ($fieldType === 'select'): ?>
                                            <select id="<?= h($fieldId) ?>"
                                                    name="fields[<?= h($itemId) ?>][<?= h($fieldName) ?>]">
                                                <?php foreach (($field['options'] ?? []) as $option): ?>
                                                    <?php
                                                    $optionValue = (string) ($option['value'] ?? '');
                                                    $optionLabel = (string) ($option['label'] ?? $optionValue);
                                                    ?>
                                                    <option value="<?= h($optionValue) ?>"
                                                        <?= $optionValue === $fieldValue ? 'selected' : '' ?>>
                                                        <?= h($optionLabel) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        <?php elseif ($fieldType === 'checkbox'): ?>
                                            <?php
                                            $checked = array_key_exists($fieldName, $currentValues)
                                                ? true
                                                : !empty($field['default']);
                                            ?>
                                            <label class="checkbox-row">
                                                <input
                                                    id="<?= h($fieldId) ?>"
                                                    type="checkbox"
                                                    name="fields[<?= h($itemId) ?>][<?= h($fieldName) ?>]"
                                                    value="1"
                                                    data-value-on="<?= h((string) ($field['value_on'] ?? '1')) ?>"
                                                    data-value-off="<?= h((string) ($field['value_off'] ?? '0')) ?>"
                                                    <?= $checked ? 'checked' : '' ?>
                                                >
                                                <span><?= h($fieldLabel) ?></span>
                                            </label>
                                        <?php else: ?>
                                            <input
                                                id="<?= h($fieldId) ?>"
                                                name="fields[<?= h($itemId) ?>][<?= h($fieldName) ?>]"
                                                type="<?= h($fieldType) ?>"
                                                value="<?= h($fieldValue) ?>"
                                                placeholder="<?= h((string) ($field['placeholder'] ?? '')) ?>"
                                                <?= isset($field['min']) ? 'min="' . h((string) $field['min']) . '"' : '' ?>
                                                <?= isset($field['max']) ? 'max="' . h((string) $field['max']) . '"' : '' ?>
                                                <?= isset($field['step']) ? 'step="' . h((string) $field['step']) . '"' : '' ?>
                                                <?= !empty($field['required']) ? 'required' : '' ?>
                                            >
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="payload-preview" id="preview-<?= h($itemId) ?>">
                                <span class="label">Payload generat</span>
                                <code><?= h($payloadPreview !== '' ? $payloadPreview : '—') ?></code>
                                <div class="template-note">
                                    Sablon: <code><?= h($template !== '' ? $template : 'nedefinit') ?></code>
                                </div>
                            </div>

                            <div class="setting-actions">
                                <button class="button" type="submit">Trimite setarea</button>
                                <span class="helper">
                                    Format: <?= h(strtoupper((string) ($item['format'] ?? 'ascii'))) ?>
                                    <?= !empty($item['append_crlf']) ? '• CRLF' : '' ?>
                                </span>
                            </div>
                        </form>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>

    <?php if ($commandResult): ?>
        <section class="panel">
            <h2>Raspuns API pentru setari</h2>
            <pre><?= h(json_encode($commandResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre>
        </section>
    <?php endif; ?>
</main>

<footer class="container footer">
    <p>
        Ajusteaza comenzile in <strong>config.local.php</strong> pentru a corespunde exact
        dispozitivului RF-V48.
    </p>
</footer>

<script src="assets/app.js"></script>
</body>
</html>
