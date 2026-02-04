<?php
declare(strict_types=1);

function ensureStorageDir(): string
{
    $dir = __DIR__ . '/../storage';
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }

    return $dir;
}

function storagePath(string $file): string
{
    $dir = ensureStorageDir();
    $clean = ltrim($file, '/');
    return $dir . '/' . $clean;
}

function appendJsonLine(string $file, array $data): void
{
    $path = storagePath($file);
    $line = json_encode($data, JSON_UNESCAPED_SLASHES);
    if ($line === false) {
        return;
    }
    file_put_contents($path, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function readJsonLines(string $file, int $limit = 0): array
{
    $path = storagePath($file);
    if (!is_file($path)) {
        return [];
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return [];
    }
    if ($limit > 0) {
        $lines = array_slice($lines, -$limit);
    }
    $rows = [];
    foreach ($lines as $line) {
        $decoded = json_decode($line, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $rows[] = $decoded;
        }
    }

    return $rows;
}
