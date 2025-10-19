<?php
// Database Configuration
// Uses environment variables for security. Set these in .env file.

// Load from environment variables or use defaults (for backward compatibility)
define('DB_HOST', $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: 'sql308.infinityfree.com');
define('DB_NAME', $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: 'if0_40088584_easycalf');
define('DB_USER', $_ENV['DB_USER'] ?? getenv('DB_USER') ?: 'if0_40088584');
define('DB_PASS', $_ENV['DB_PASS'] ?? getenv('DB_PASS') ?: 'WuAKQnERk2H');
define('DB_CHARSET', $_ENV['DB_CHARSET'] ?? getenv('DB_CHARSET') ?: 'utf8mb4');

// Application Settings
define('APP_NAME', $_ENV['APP_NAME'] ?? getenv('APP_NAME') ?: 'EasyCalf');
define('APP_VERSION', $_ENV['APP_VERSION'] ?? getenv('APP_VERSION') ?: '1.0');
define('BASE_URL', $_ENV['BASE_URL'] ?? getenv('BASE_URL') ?: 'http://easycalf.free.nf');
define('UPLOAD_PATH', $_ENV['UPLOAD_PATH'] ?? getenv('UPLOAD_PATH') ?: __DIR__ . '/../storage/uploads/');
?>
