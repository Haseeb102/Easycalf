<?php
// =====================================================
// EASYCALF - CENTRAL ROUTER / public/index.php
// =====================================================

// Simple autoloader: converts namespaces like "App\Module\Class" -> app/Module/Class.php
// Simple autoloader: converts namespaces like "App\Module\Class" -> app/Module/Class.php
spl_autoload_register(function ($class) {
    // Only map App\ namespace to app/ directory
    $classPath = str_replace('App\\', '', $class); // Remove App\ prefix
    $classPath = str_replace('\\', '/', $classPath);
    
    // Special handling for Core namespace - convert to lowercase for case-sensitive file systems
    $classPath = preg_replace('/^Core\//', 'core/', $classPath);
    
    $candidates = [
        __DIR__ . '/../app/' . $classPath . '.php',        // For classes like App\Core\Controller -> app/core/Controller.php
        __DIR__ . '/../app/modules/' . $classPath . '.php', // For classes in app/modules/
    ];

    foreach ($candidates as $file) {
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});


// Enable verbose errors during development (remove or toggle in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Base path points to project root (one level above public/)
define('BASE_PATH', dirname(__DIR__));

try {
    // =====================================================
    // AUTOLOAD CORE FILES (these files must exist in app/config and app/core)
    // =====================================================
    require_once BASE_PATH . '/app/config/database.php';
    require_once BASE_PATH . '/app/config/public_access.php';
    require_once BASE_PATH . '/app/core/Database.php';
    require_once BASE_PATH . '/app/core/Auth.php';
    require_once BASE_PATH . '/app/core/ModernNavbar.php';

    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // If public access is enabled and there's no logged-in user, set a default public session
    if (!isset($_SESSION['user_id']) && defined('PUBLIC_ACCESS_ENABLED') && PUBLIC_ACCESS_ENABLED) {
        $_SESSION['user_id']     = defined('PUBLIC_USER_ID') ? PUBLIC_USER_ID : null;
        $_SESSION['user_name']   = defined('PUBLIC_USER_NAME') ? PUBLIC_USER_NAME : 'Public';
        $_SESSION['user_email']  = defined('PUBLIC_USER_EMAIL') ? PUBLIC_USER_EMAIL : '';
        $_SESSION['user_role']   = defined('PUBLIC_USER_ROLE') ? PUBLIC_USER_ROLE : 'viewer';
        $_SESSION['logged_in']   = true;
        $_SESSION['public_access'] = true;
    }

    // =====================================================
    // REQUEST HANDLING
    // =====================================================
    // Get the path part of the request URI and normalize it relative to the script directory
    $request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $script_dir = dirname($_SERVER['SCRIPT_NAME']);

    // If app is served from a subfolder, strip the script directory prefix
    if ($script_dir !== '/' && $script_dir !== '\\' && strpos($request_uri, $script_dir) === 0) {
        $request_uri = substr($request_uri, strlen($script_dir));
    }

    $path = trim($request_uri, '/');
    if ($path === '') {
        $path = '/';
    }

    // =====================================================
    // ROUTER - map normalized $path to controllers/actions
    // =====================================================
    switch ($path) {

        // DASHBOARD
        case '/':
            require_once BASE_PATH . '/app/modules/Dashboard/controller.php';
            $dashboard = new DashboardController();
            $dashboard->home();
            break;

        // AUTHENTICATION
        case 'login':
            require_once BASE_PATH . '/app/modules/Auth/controller.php';
            (new AuthController())->login();
            break;

        case 'logout':
            require_once BASE_PATH . '/app/modules/Auth/controller.php';
            (new AuthController())->logout();
            break;

        case 'register':
            require_once BASE_PATH . '/app/modules/Auth/controller.php';
            (new AuthController())->register();
            break;

        // PROFILE
        case 'profile':
            require_once BASE_PATH . '/app/modules/Profile/controller.php';
            (new ProfileController())->view();
            break;

        case 'profile/update':
            require_once BASE_PATH . '/app/modules/Profile/controller.php';
            (new ProfileController())->update();
            break;

        case 'profile/change-password':
            require_once BASE_PATH . '/app/modules/Profile/controller.php';
            (new ProfileController())->changePassword();
            break;

        // CALVES MANAGEMENT
        case 'calves':
            require_once BASE_PATH . '/app/modules/Calves/controller.php';
            (new CalvesController())->list();
            break;

        case 'calves/add':
            require_once BASE_PATH . '/app/modules/Calves/controller.php';
            (new CalvesController())->add();
            break;

        case 'calves/edit':
            require_once BASE_PATH . '/app/modules/Calves/controller.php';
            $id = $_GET['id'] ?? null;
            (new CalvesController())->edit($id);
            break;

        case (preg_match('#^calves/edit/(\d+)$#', $path, $matches) ? true : false):
            require_once BASE_PATH . '/app/modules/Calves/controller.php';
            (new CalvesController())->edit($matches[1]);
            break;

        case 'calves/passport':
            require_once BASE_PATH . '/app/modules/Calves/controller.php';
            $id = $_GET['id'] ?? null;
            (new CalvesController())->passport($id);
            break;

        case (preg_match('#^calves/passport/(\d+)$#', $path, $matches) ? true : false):
            require_once BASE_PATH . '/app/modules/Calves/controller.php';
            (new CalvesController())->passport($matches[1]);
            break;

        case 'calves/batch':
            require_once BASE_PATH . '/app/modules/Calves/controller.php';
            (new CalvesController())->batch($_GET['id'] ?? null);
            break;

        case 'calves/delete':
            require_once BASE_PATH . '/app/modules/Calves/controller.php';
            (new CalvesController())->delete();
            break;

        case 'calves/bulk-delete':
            require_once BASE_PATH . '/app/modules/Calves/controller.php';
            (new CalvesController())->bulkDelete();
            break;

        case 'calves/bulk-batch':
            require_once BASE_PATH . '/app/modules/Calves/controller.php';
            (new CalvesController())->bulkBatch();
            break;

        case 'calves/bulk-health':
            require_once BASE_PATH . '/app/modules/Calves/controller.php';
            (new CalvesController())->bulkHealth();
            break;

        case 'calves/export':
            require_once BASE_PATH . '/app/modules/Calves/controller.php';
            (new CalvesController())->export();
            break;

        case 'calves/import':
            require_once BASE_PATH . '/app/modules/Calves/controller.php';
            (new CalvesController())->import();
            break;

        // Debug routes (remove in production)
        case 'calves/debug-bulk-delete':
            require_once BASE_PATH . '/app/modules/Calves/controller.php';
            (new CalvesController())->debugBulkDelete();
            break;

        case 'calves/verify-deletes':
            require_once BASE_PATH . '/app/modules/Calves/controller.php';
            (new CalvesController())->verifyDeletes();
            break;

        // BATCHES
        case 'batches':
            require_once BASE_PATH . '/app/modules/Batches/controller.php';
            (new BatchesController())->list();
            break;

        case 'batches/add':
            require_once BASE_PATH . '/app/modules/Batches/controller.php';
            (new BatchesController())->add();
            break;

        case 'batches/view':
            require_once BASE_PATH . '/app/modules/Batches/controller.php';
            $id = $_GET['id'] ?? null;
            (new BatchesController())->viewCalves($id);
            break;

        case (preg_match('#^batches/view/(\d+)$#', $path, $matches) ? true : false):
            require_once BASE_PATH . '/app/modules/Batches/controller.php';
            (new BatchesController())->viewCalves($matches[1]);
            break;

        case 'batches/edit':
            require_once BASE_PATH . '/app/modules/Batches/controller.php';
            $id = $_GET['id'] ?? null;
            (new BatchesController())->edit($id);
            break;

        // EVENTS
        case 'events':
            require_once BASE_PATH . '/app/modules/Events/controller.php';
            (new EventsController())->list();
            break;

        case 'events/add':
            require_once BASE_PATH . '/app/modules/Events/controller.php';
            (new EventsController())->add();
            break;

        case 'events/update':
            require_once BASE_PATH . '/app/modules/Events/controller.php';
            (new EventsController())->update();
            break;

        case 'events/delete':
            require_once BASE_PATH . '/app/modules/Events/controller.php';
            (new EventsController())->delete();
            break;

        case 'events/toggle':
            require_once BASE_PATH . '/app/modules/Events/controller.php';
            (new EventsController())->toggle();
            break;

        case 'events/calves':
            require_once BASE_PATH . '/app/modules/Events/controller.php';
            $id = $_GET['id'] ?? null;
            (new EventsController())->viewCalves($id);
            break;

        // TASKS
        case 'tasks':
            require_once BASE_PATH . '/app/modules/Tasks/controller.php';
            (new TasksController())->list();
            break;

        case 'tasks/all-due':
            require_once BASE_PATH . '/app/modules/Tasks/controller.php';
            (new TasksController())->listAllDue();
            break;

        case 'tasks/all-upcoming':
            require_once BASE_PATH . '/app/modules/Tasks/controller.php';
            (new TasksController())->listAllUpcoming();
            break;

        case 'tasks/calendar':
            require_once BASE_PATH . '/app/modules/Tasks/controller.php';
            (new TasksController())->calendar();
            break;

        case 'tasks/details':
            require_once BASE_PATH . '/app/modules/Tasks/controller.php';
            (new TasksController())->details();
            break;

        case 'tasks/complete-bulk':
            require_once BASE_PATH . '/app/modules/Tasks/controller.php';
            (new TasksController())->completeBulk();
            break;

        case 'tasks/complete-calf':
            require_once BASE_PATH . '/app/modules/Tasks/controller.php';
            (new TasksController())->completeCalf();
            break;

        case 'tasks/complete-all-due':
            require_once BASE_PATH . '/app/modules/Tasks/controller.php';
            (new TasksController())->completeAllDue();
            break;

        case 'tasks/complete':
            require_once BASE_PATH . '/app/modules/Tasks/controller.php';
            (new TasksController())->complete();
            break;

        // MILK CALCULATOR / ALLOWANCES
        case 'milk/calculator':
            require_once BASE_PATH . '/app/modules/Milk/controller.php';
            (new MilkController())->calculator();
            break;

        case 'milk/allowances':
            require_once BASE_PATH . '/app/modules/Settings/controller.php';
            (new SettingsController())->index();
            break;

        case 'milk/allowances/update':
            require_once BASE_PATH . '/app/modules/Milk/controller.php';
            (new MilkController())->updateAllowances();
            break;

        // TREATMENT
        case 'treatment':
            require_once BASE_PATH . '/app/modules/Treatment/controller.php';
            (new TreatmentController())->list();
            break;

        case 'treatment/add':
            require_once BASE_PATH . '/app/modules/Treatment/controller.php';
            (new TreatmentController())->add();
            break;

        case 'treatment/quick-electrolyte':
            require_once BASE_PATH . '/app/modules/Treatment/controller.php';
            (new TreatmentController())->quickElectrolyte();
            break;

        case 'treatment/cancel-electrolyte':
            require_once BASE_PATH . '/app/modules/Treatment/controller.php';
            (new TreatmentController())->cancelElectrolyte();
            break;

        case 'treatment/bulk-electrolyte':
            require_once BASE_PATH . '/app/modules/Treatment/controller.php';
            (new TreatmentController())->bulkElectrolyte();
            break;

        case 'treatment/complete-day':
            require_once BASE_PATH . '/app/modules/Treatment/controller.php';
            (new TreatmentController())->completeDay();
            break;

        case 'treatment/history':
            require_once BASE_PATH . '/app/modules/Treatment/controller.php';
            (new TreatmentController())->history();
            break;

        case 'treatment/cancel':
            require_once BASE_PATH . '/app/modules/Treatment/controller.php';
            (new TreatmentController())->cancel();
            break;

        // ELECTROLYTES (legacy routes -> treatment)
        case 'electrolytes/quick':
            require_once BASE_PATH . '/app/modules/Treatment/controller.php';
            (new TreatmentController())->quickElectrolyte();
            break;

        case 'electrolytes/bulk':
            require_once BASE_PATH . '/app/modules/Treatment/controller.php';
            (new TreatmentController())->bulkElectrolyte();
            break;

        case 'electrolytes/undo':
            require_once BASE_PATH . '/app/modules/Treatment/controller.php';
            (new TreatmentController())->cancelElectrolyte();
            break;

        // SETTINGS
case 'settings':
    require_once BASE_PATH . '/app/modules/Settings/controller.php';
    (new \App\Modules\Settings\SettingsController())->index();
    break;

case 'settings/update-ratio':
    require_once BASE_PATH . '/app/modules/Settings/controller.php';
    (new \App\Modules\Settings\SettingsController())->updateRatio();
    break;

case 'settings/update-allowances':
    require_once BASE_PATH . '/app/modules/Settings/controller.php';
    (new \App\Modules\Settings\SettingsController())->updateAllowances();
    break;

case 'settings/add-allowance':
    require_once BASE_PATH . '/app/modules/Settings/controller.php';
    (new \App\Modules\Settings\SettingsController())->addAllowance();
    break;

case 'settings/delete-allowance':
    require_once BASE_PATH . '/app/modules/Settings/controller.php';
    (new \App\Modules\Settings\SettingsController())->deleteAllowance();
    break;

case 'settings/update-weaning':
    require_once BASE_PATH . '/app/modules/Settings/controller.php';
    (new \App\Modules\Settings\SettingsController())->updateWeaningSettings();
    break;

case 'settings/update-feeding-times':
    require_once BASE_PATH . '/app/modules/Settings/controller.php';
    (new \App\Modules\Settings\SettingsController())->updateFeedingTimes();
    break;

case 'settings/add-event':
    require_once BASE_PATH . '/app/modules/Settings/controller.php';
    (new \App\Modules\Settings\SettingsController())->addEvent();
    break;

case 'settings/update-event':
    require_once BASE_PATH . '/app/modules/Settings/controller.php';
    (new \App\Modules\Settings\SettingsController())->updateEvent();
    break;

case 'settings/delete-event':
    require_once BASE_PATH . '/app/modules/Settings/controller.php';
    (new \App\Modules\Settings\SettingsController())->deleteEvent();
    break;

case 'settings/toggle-event':
    require_once BASE_PATH . '/app/modules/Settings/controller.php';
    (new \App\Modules\Settings\SettingsController())->toggleEvent();
    break;

// Hard delete management
case 'settings/hard-delete-management':
    require_once BASE_PATH . '/app/modules/Settings/controller.php';
    (new \App\Modules\Settings\SettingsController())->hardDeleteManagement();
    break;

case 'settings/permanent-delete':
    require_once BASE_PATH . '/app/modules/Settings/controller.php';
    (new \App\Modules\Settings\SettingsController())->permanentDelete();
    break;

        // ADMIN / USERS
        case 'admin/users':
            require_once BASE_PATH . '/app/modules/Users/controller.php';
            (new UsersController())->list();
            break;

        case 'admin/approve':
            require_once BASE_PATH . '/app/modules/Users/controller.php';
            (new UsersController())->approve();
            break;

        case 'admin/delete':
            require_once BASE_PATH . '/app/modules/Users/controller.php';
            (new UsersController())->delete();
            break;

        case 'admin/public-access':
            require_once BASE_PATH . '/app/modules/Admin/controller.php';
            (new AdminController())->publicAccessToggle();
            break;

        // MIGRATION and DEBUG routes (for maintenance; remove/disable in production)
        case 'migrate-profile':
            require_once BASE_PATH . '/public/migrate_profile.php';
            break;

        case 'migrate-feeding-times':
            require_once BASE_PATH . '/public/migrate_feeding_times.php';
            break;

        case 'migrate-calf-passport':
            require_once BASE_PATH . '/public/migrate_calf_passport.php';
            break;

        case 'migrate-weaning-settings':
            require_once BASE_PATH . '/public/migrate_weaning_settings.php';
            break;

        case 'test-design':
            require_once BASE_PATH . '/public/test-design.php';
            break;

        case 'debug-navbar':
            require_once BASE_PATH . '/public/debug-navbar.php';
            break;

        case 'test-delete':
            require_once BASE_PATH . '/public/test-delete.php';
            break;

        case 'fix_calf_status':
            require_once BASE_PATH . '/public/fix_calf_status.php';
            break;

        // DEFAULT / FALLBACK - allow static files and show custom 404 for missing routes
        default:
            // Allow static assets to be served directly (works with PHP built-in server)
            if (strpos($path, 'assets/') === 0) {
                return false;
            }

            // Pass through common static file extensions
            if (preg_match('/\.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$/', $path)) {
                return false;
            }

            // 404 - show a friendly page
            http_response_code(404);
            require_once BASE_PATH . '/app/core/ModernNavbar.php';
            $navbar = new ModernNavbar();
            ?>
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="utf-8">
                <title>404 - Page Not Found - EasyCalf</title>
                <link rel="stylesheet" href="/public/assets/css/style.css">
                <style>
                    .error-container {
                        max-width: 600px;
                        margin: 100px auto;
                        padding: 2.5rem;
                        text-align: center;
                        background: #fff;
                        border-radius: 12px;
                        box-shadow: 0 6px 18px rgba(0,0,0,0.08);
                    }
                    .error-icon { font-size: 4rem; margin-bottom: 0.75rem; }
                    .error-title { color: #E53935; margin-bottom: 0.75rem; font-weight: 700; font-size: 1.75rem; }
                    .error-path { background: #f5f5f5; padding: 0.8rem; border-radius: 8px; font-family: monospace; margin: 1rem 0; display: inline-block; }
                    .btn { display:inline-block; padding:0.6rem 1rem; border-radius:8px; text-decoration:none; margin:0.25rem; }
                    .btn-primary { background:#1976D2; color:#fff; }
                    .btn-secondary { background:#e0e0e0; color:#000; }
                </style>
            </head>
            <body>
                <?php $navbar->render(); ?>
                <div class="error-container">
                    <div class="error-icon">🔍</div>
                    <h1 class="error-title">404 - Page Not Found</h1>
                    <p>The page you're looking for doesn't exist.</p>
                    <div class="error-path"><?php echo htmlspecialchars($path); ?></div>
                    <div style="margin-top: 1.5rem;">
                        <a href="/public/" class="btn btn-primary">🏠 Dashboard</a>
                        <a href="/public/calves" class="btn btn-secondary">🐮 Calves</a>
                    </div>
                </div>
            </body>
            </html>
            <?php
            exit;
    }

} catch (Throwable $e) {
    // GLOBAL ERROR HANDLER - show a friendly debug page during development
    http_response_code(500);

    // Try to display path if available; fall back safely
    $displayPath = isset($path) ? htmlspecialchars($path) : 'unknown';

    echo "<!DOCTYPE html>
    <html>
    <head>
        <meta charset='utf-8'>
        <title>Error - EasyCalf</title>
        <link rel='stylesheet' href='/public/assets/css/style.css'>
        <style>
            .error-container { max-width:900px; margin:80px auto; padding:1.5rem; background:#fff; border-radius:12px; box-shadow:0 8px 24px rgba(0,0,0,0.08); }
            .error-icon { font-size:3rem; text-align:center; margin-bottom:0.5rem; }
            .error-title { color:#E53935; text-align:center; font-weight:700; margin-bottom:0.5rem; }
            .error-message { background:rgba(229,57,53,0.06); color:#C53030; padding:0.8rem; border-radius:8px; margin:0.75rem 0; border-left:4px solid #E53935; }
            .debug-info { background:#f5f5f5; padding:0.75rem; border-radius:8px; font-family:monospace; font-size:0.85rem; white-space:pre-wrap; }
        </style>
    </head>
    <body>
        <div class='error-container'>
            <div class='error-icon'>⚠️</div>
            <h1 class='error-title'>Application Error</h1>
            <p style='text-align:center;'>Sorry, something went wrong. Path: {$displayPath}</p>";

    if (ini_get('display_errors')) {
        echo "<div class='error-message'>
                <strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "
              </div>";
        echo "<div class='debug-info'>
                <strong>File:</strong> " . htmlspecialchars($e->getFile()) . " (Line " . $e->getLine() . ")\n\n"
             . htmlspecialchars($e->getTraceAsString()) . "
              </div>";
    } else {
        echo "<p style='text-align:center;'>Enable display_errors to see details during development.</p>";
    }

    echo "<div style='text-align:center; margin-top:1rem;'>
            <a href='/public/' class='btn btn-primary'>🏠 Dashboard</a>
            <a href='/public/login' class='btn btn-secondary'>🔐 Login</a>
          </div>";

    echo "</div></body></html>";

    // Log full error to server logs
    error_log("EasyCalf Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    error_log("Request Path: " . ($path ?? 'unknown'));
    // Do not rethrow
}