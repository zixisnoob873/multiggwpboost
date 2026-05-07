<?php

if ($argc < 2) {
    fwrite(STDERR, "Usage: php scripts/validate-production-env.php /path/to/.env.production\n");
    exit(2);
}

$path = $argv[1];

if (! is_file($path) || ! is_readable($path)) {
    fwrite(STDERR, "Environment file is not readable: {$path}\n");
    exit(2);
}

$values = [];

foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
    $line = trim($line);

    if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
        continue;
    }

    [$key, $value] = explode('=', $line, 2);
    $values[trim($key)] = trim(trim($value), "\"'");
}

$errors = [];

if (($values['APP_ENV'] ?? '') === 'local') {
    $errors[] = 'APP_ENV must not be local for production.';
}

if (strtolower($values['APP_DEBUG'] ?? '') === 'true') {
    $errors[] = 'APP_DEBUG must be false for production.';
}

if (strtolower($values['SESSION_SECURE_COOKIE'] ?? '') === 'false') {
    $errors[] = 'SESSION_SECURE_COOKIE must be true for production.';
}

foreach ($values as $key => $value) {
    $normalized = strtolower($value);

    if (
        str_contains($normalized, 'local-change-me-secret')
        || str_contains($normalized, 'replace_with')
        || str_contains($normalized, 'placeholder')
    ) {
        $errors[] = "{$key} still contains a placeholder value.";
    }
}

if ($errors !== []) {
    fwrite(STDERR, "Production environment validation failed:\n- ".implode("\n- ", $errors)."\n");
    exit(1);
}

fwrite(STDOUT, "Production environment validation passed.\n");
