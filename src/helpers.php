<?php

/**
 * Get environment variable with optional default value
 *
 * @param string $key
 * @param mixed $default
 * @return mixed
 */
function env(string $key, $default = null)
{
    return $_ENV[$key] ?? $default;
}

/**
 * Generate CSRF token
 *
 * @param string $key
 * @return string
 */
function csrf_token(string $key): string
{
    if (!isset($_SESSION['csrf_tokens'][$key])) {
        $_SESSION['csrf_tokens'][$key] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_tokens'][$key];
}

/**
 * Validate CSRF token
 *
 * @param string $key
 * @param string|null $token
 * @return bool
 */
function csrf_validate(string $key, ?string $token): bool
{
    if (!$token || !isset($_SESSION['csrf_tokens'][$key])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_tokens'][$key], $token);
}

/**
 * Get database configuration from environment
 *
 * @return array
 */
function db_config(): array
{
    return [
        'host' => env('DB_HOST', 'localhost'),
        'name' => env('DB_NAME'),
        'user' => env('DB_USER'),
        'pass' => env('DB_PASS'),
        'charset' => env('DB_CHARSET', 'utf8mb4'),
    ];
}

/**
 * Get payment configuration from environment
 *
 * @return array
 */
function payment_config(): array
{
    return [
        'iban' => env('PAYMENT_IBAN'),
        'currency' => env('PAYMENT_CURRENCY', 'CZK'),
        'message' => env('PAYMENT_MESSAGE', 'Improtresk 2026'),
        'default_fee' => env('DEFAULT_WORKSHOP_FEE', 1200.00),
    ];
}
