<?php
// Simple CSRF helper. Use Csrf::input() in your HTML forms,
// and call Csrf::validate() in POST handlers before processing.
//
// - Session is automatically started if not already.
// - Tokens are single-use by default (consumed on validate).

class Csrf
{
    private const TOKEN_KEY = '_csrf_token';

    // Ensure session started
    private static function ensureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    // Generate and store a token (returns token)
    public static function generate(): string
    {
        self::ensureSession();
        $token = bin2hex(random_bytes(32));
        // Store token (allow multiple tokens if needed by storing array)
        $_SESSION[self::TOKEN_KEY] = $token;
        return $token;
    }

    // Output a hidden input for a form
    public static function input(): string
    {
        $token = self::generate();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    // Validate a token and consume it (prevents replay)
    public static function validate(?string $token): bool
    {
        self::ensureSession();
        if (empty($token) || empty($_SESSION[self::TOKEN_KEY])) {
            return false;
        }
        $valid = hash_equals($_SESSION[self::TOKEN_KEY], $token);
        // Consume token
        unset($_SESSION[self::TOKEN_KEY]);
        return $valid;
    }

    // Convenience: validate using $_POST
    public static function validatePost(): bool
    {
        $token = $_POST['csrf_token'] ?? null;
        return self::validate($token);
    }
}
?>