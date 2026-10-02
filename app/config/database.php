<?php
require_once __DIR__ . '/env.php';

easycalf_load_env();
easycalf_require_settings(['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS']);

if (!defined('APP_DEBUG')) {
    define('APP_DEBUG', easycalf_env_flag('APP_DEBUG'));
}

define('DB_HOST', easycalf_env('DB_HOST'));
define('DB_NAME', easycalf_env('DB_NAME'));
define('DB_USER', easycalf_env('DB_USER'));
define('DB_PASS', easycalf_env('DB_PASS'));
define('DB_CHARSET', easycalf_env('DB_CHARSET', 'utf8mb4'));

define('APP_NAME', easycalf_env('APP_NAME', 'EasyCalf'));
define('APP_VERSION', easycalf_env('APP_VERSION', '1.0'));
define('BASE_URL', easycalf_env('BASE_URL', ''));
define('UPLOAD_PATH', easycalf_env('UPLOAD_PATH', __DIR__ . '/../storage/uploads/'));
