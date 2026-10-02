<?php
/**
 * Public Access Configuration
 * 
 * Set PUBLIC_ACCESS_ENABLED to true to allow anyone with the URL to access the system
 * Set to false to require login (default secure mode)
 * 
 * For security, public access can be configured to auto-disable after 24 hours
 */

if (!function_exists('easycalf_env')) {
    require_once __DIR__ . '/env.php';
    easycalf_load_env();
}

// Toggle public access from the environment or the local configuration file.
$publicAccessEnabled = easycalf_env('PUBLIC_ACCESS_ENABLED', 'false');
define('PUBLIC_ACCESS_ENABLED', $publicAccessEnabled === 'true' || $publicAccessEnabled === '1');

// Optional access code for semi-public access. Leave empty for fully public access.
// Store the value in the environment or the local configuration file, not in git.
define('PUBLIC_ACCESS_CODE', easycalf_env('PUBLIC_ACCESS_CODE', ''));

// Public access user (used for tracking when public access is enabled)
define('PUBLIC_USER_ID', $_ENV['PUBLIC_USER_ID'] ?? getenv('PUBLIC_USER_ID') ?: 1);
define('PUBLIC_USER_NAME', 'Public Viewer');
define('PUBLIC_USER_EMAIL', 'public@easycalf.com');
define('PUBLIC_USER_ROLE', 'user'); // 'user' or 'admin'

// Public access restrictions (what public users can't do)
define('PUBLIC_ACCESS_RESTRICTIONS', [
    'admin_pages' => true, // Block access to admin pages
    'user_management' => true, // Block user management
    'settings' => true, // Block settings access
    'delete_operations' => true, // Block delete operations
    'export_data' => false // Allow export (set to true to block)
]);

// Public access expiration - auto-disable after 24 hours
// Store timestamp when public access was last enabled
$publicAccessFile = __DIR__ . '/../storage/public_access.json';
if (PUBLIC_ACCESS_ENABLED && file_exists($publicAccessFile)) {
    $accessData = json_decode(file_get_contents($publicAccessFile), true);
    if (isset($accessData['enabled_at'])) {
        $enabledTime = strtotime($accessData['enabled_at']);
        $currentTime = time();
        $hoursSinceEnabled = ($currentTime - $enabledTime) / 3600;
        
        // Auto-disable after 24 hours
        if ($hoursSinceEnabled >= 24) {
            // This would need to be handled by a cron job or manual check
            // For now, we just detect it. The actual disabling should be done
            // through the Settings controller
            define('PUBLIC_ACCESS_EXPIRED', true);
        }
    }
}

if (!defined('PUBLIC_ACCESS_EXPIRED')) {
    define('PUBLIC_ACCESS_EXPIRED', false);
}
?>