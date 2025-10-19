<?php
// Error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if already installed
$config_file = '../app/config/database.php';
if (file_exists($config_file)) {
    echo "<script>alert('EasyCalf is already installed. Redirecting to main app...'); window.location.href = '../';</script>";
    exit;
}

$error = null;
$success = false;
$debug_info = [];

// Create necessary directories
$debug_info[] = "Creating directory structure...";
$dirs_to_create = [
    '../app/config',
    '../app/storage/uploads', 
    '../app/storage/backups',
    '../app/core',
    '../app/modules'
];

foreach ($dirs_to_create as $dir) {
    if (!is_dir($dir)) {
        if (mkdir($dir, 0755, true)) {
            $debug_info[] = "✅ Created directory: $dir";
        } else {
            $debug_info[] = "❌ Failed to create directory: $dir";
        }
    } else {
        $debug_info[] = "✅ Directory exists: $dir";
    }
}

if ($_POST['install']) {
    $db_host = 'sql308.infinityfree.com';
    $db_name = 'if0_40088584_easycalf';
    $db_user = 'if0_40088584';
    $db_pass = 'WuAKQnERk2H';
    
    try {
        $debug_info[] = "Testing database connection...";
        
        // Test database connection
        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $debug_info[] = "✅ Database connection successful";
        
        // Import schema
        $debug_info[] = "Reading schema file...";
        $schema_file = '../database/schema.sql';
        if (!file_exists($schema_file)) {
            throw new Exception("Schema file not found: " . $schema_file);
        }
        
        $schema = file_get_contents($schema_file);
        $debug_info[] = "✅ Schema file loaded (" . strlen($schema) . " bytes)";
        
        // Split schema into individual queries
        $queries = array_filter(array_map('trim', explode(';', $schema)));
        $debug_info[] = "Executing " . count($queries) . " SQL queries...";
        
        $tables_created = 0;
        foreach ($queries as $query) {
            if (!empty($query)) {
                $pdo->exec($query);
                if (strpos($query, 'CREATE TABLE') !== false) {
                    $tables_created++;
                }
            }
        }
        $debug_info[] = "✅ Database schema imported ($tables_created tables created)";
        
        // Create config file
        $debug_info[] = "Creating configuration file...";
        $configContent = "<?php
// Database Configuration for InfinityFree
define('DB_HOST', 'sql308.infinityfree.com');
define('DB_NAME', 'if0_40088584_easycalf');
define('DB_USER', 'if0_40088584');
define('DB_PASS', 'WuAKQnERk2H');
define('DB_CHARSET', 'utf8mb4');

// Application Settings
define('APP_NAME', 'EasyCalf');
define('APP_VERSION', '1.0');
define('BASE_URL', 'http://easycalf.free.nf');
define('UPLOAD_PATH', __DIR__ . '/../storage/uploads/');
?>
";
        
        if (file_put_contents($config_file, $configContent) === false) {
            throw new Exception("Failed to write config file: " . $config_file);
        }
        $debug_info[] = "✅ Configuration file created";
        
        $success = true;
        $debug_info[] = "🎉 Installation completed successfully!";
        
    } catch (PDOException $e) {
        $error = "Database connection failed: " . $e->getMessage();
        $debug_info[] = "❌ Database error: " . $e->getMessage();
    } catch (Exception $e) {
        $error = "Installation failed: " . $e->getMessage();
        $debug_info[] = "❌ Installation error: " . $e->getMessage();
    }
}

// Display current PHP info for debugging
$debug_info[] = "PHP Version: " . PHP_VERSION;
$debug_info[] = "Current directory: " . getcwd();
$debug_info[] = "Config file path: " . realpath($config_file);
$debug_info[] = "Config file exists: " . (file_exists($config_file) ? 'Yes' : 'No');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Install EasyCalf</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #007AFF, #5856D6);
            min-height: 100vh;
            padding: 1rem;
        }
        .install-container {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            max-width: 800px;
            margin: 0 auto;
        }
        h1 { 
            color: #007AFF; 
            margin-bottom: 1rem;
            text-align: center;
        }
        .success { 
            background: #d4edda; 
            color: #155724; 
            padding: 1rem; 
            border-radius: 8px; 
            margin: 1rem 0; 
        }
        .error { 
            background: #f8d7da; 
            color: #721c24; 
            padding: 1rem; 
            border-radius: 8px; 
            margin: 1rem 0; 
        }
        .info { 
            background: #d1ecf1; 
            color: #0c5460; 
            padding: 1rem; 
            border-radius: 8px; 
            margin: 1rem 0; 
        }
        .debug { 
            background: #f8f9fa; 
            color: #333; 
            padding: 1rem; 
            border-radius: 8px; 
            margin: 1rem 0;
            font-family: monospace;
            font-size: 0.9rem;
            max-height: 300px;
            overflow-y: auto;
        }
        .btn {
            background: #007AFF;
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 8px;
            font-size: 1rem;
            cursor: pointer;
            width: 100%;
            margin-top: 1rem;
        }
        .btn:hover { background: #0056cc; }
        .db-info {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            margin: 1rem 0;
        }
        .debug-line {
            margin: 0.25rem 0;
            padding: 0.25rem;
            border-left: 4px solid transparent;
        }
        .debug-line.success { border-left-color: #28a745; background: #f8fff9; }
        .debug-line.error { border-left-color: #dc3545; background: #fff5f5; }
        .debug-line.info { border-left-color: #17a2b8; background: #f8fdff; }
    </style>
</head>
<body>
    <div class="install-container">
        <h1>EasyCalf Installation</h1>
        
        <!-- Debug Information -->
        <div class="debug">
            <h3>Installation Progress:</h3>
            <?php foreach ($debug_info as $line): ?>
                <div class="debug-line <?php 
                    if (strpos($line, '✅') !== false) echo 'success';
                    elseif (strpos($line, '❌') !== false) echo 'error';
                    else echo 'info';
                ?>"><?= htmlspecialchars($line) ?></div>
            <?php endforeach; ?>
        </div>
        
        <?php if ($success): ?>
            <div class="success">
                <h3>✅ Installation Successful!</h3>
                <p>EasyCalf has been successfully installed.</p>
                <div class="db-info">
                    <strong>Default Admin Login:</strong><br>
                    Email: <code>admin@easycalf.com</code><br>
                    Password: <code>admin123</code>
                </div>
                <p><strong>Important:</strong> 
                   <br>• Change the admin password after first login!
                   <br>• Delete the install directory for security!
                </p>
            </div>
            <div style="display: flex; gap: 1rem;">
                <a href="../" class="btn" style="flex: 1;">Go to EasyCalf</a>
                <a href="../public/" class="btn" style="flex: 1; background: #28a745;">Go to Public App</a>
            </div>
            
        <?php else: ?>
            <?php if ($error): ?>
                <div class="error">
                    <strong>Installation Error:</strong><br>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <div class="info">
                <strong>Ready to Install</strong><br>
                Click the button below to install EasyCalf with your database settings.
            </div>
            
            <div class="db-info">
                <strong>Database Details:</strong><br>
                Host: <code>sql308.infinityfree.com</code><br>
                Database: <code>if0_40088584_easycalf</code><br>
                User: <code>if0_40088584</code>
            </div>
            
            <form method="post">
                <input type="hidden" name="install" value="1">
                <button type="submit" class="btn">Install EasyCalf Now</button>
            </form>
            
            <div style="margin-top: 2rem; padding: 1rem; background: #fff3cd; border-radius: 8px;">
                <strong>Troubleshooting:</strong>
                <ul style="margin: 0.5rem 0 0 1rem;">
                    <li>Make sure database exists and user has permissions</li>
                    <li>Check that all directories are writable</li>
                    <li>Remove any .htaccess files if causing redirect issues</li>
                </ul>
            </div>
            
        <?php endif; ?>
    </div>
</body>
</html>