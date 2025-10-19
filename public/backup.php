<?php
// public/backup.php
// Database backup and restore utility

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security check - only allow logged-in users
if (empty($_SESSION['user_id'])) {
    http_response_code(403);
    die('Access denied. Please log in.');
}

// Check if user is admin - check multiple possible session keys
$isAdmin = false;

if (!empty($_SESSION['role']) && strtolower($_SESSION['role']) === 'admin') {
    $isAdmin = true;
}

if (!empty($_SESSION['user_role']) && strtolower($_SESSION['user_role']) === 'admin') {
    $isAdmin = true;
}

if (!$isAdmin) {
    // Debug info - shows what's in your session
    http_response_code(403);
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Access Denied</title>';
    echo '<style>body{font-family:Arial;padding:40px;background:#f5f5f5;}';
    echo '.container{background:white;padding:30px;border-radius:8px;max-width:800px;margin:0 auto;box-shadow:0 2px 10px rgba(0,0,0,0.1);}';
    echo 'h2{color:#d32f2f;}pre{background:#f5f5f5;padding:15px;border-radius:5px;overflow:auto;}';
    echo '.btn{display:inline-block;padding:10px 20px;background:#1976D2;color:white;text-decoration:none;border-radius:5px;margin-top:20px;}';
    echo '</style></head><body><div class="container">';
    echo '<h2>⛔ Access Denied - Admin Privileges Required</h2>';
    echo '<p>Your account does not have administrator privileges to access backup/restore features.</p>';
    echo '<h3>🔍 Session Debug Info:</h3>';
    echo '<pre>';
    echo 'user_id: ' . ($_SESSION['user_id'] ?? 'not set') . "\n";
    echo 'role: ' . ($_SESSION['role'] ?? 'not set') . "\n";
    echo 'user_role: ' . ($_SESSION['user_role'] ?? 'not set') . "\n\n";
    echo 'All session data:' . "\n";
    print_r($_SESSION);
    echo '</pre>';
    echo '<p><strong>Note:</strong> To use backup/restore, your session must have either <code>role</code> or <code>user_role</code> set to "admin".</p>';
    echo '<a href="/public/settings" class="btn">← Back to Settings</a>';
    echo '</div></body></html>';
    exit;
}

// Load database configuration
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/app/config/database.php';

$action = $_GET['action'] ?? 'download';

if ($action === 'download') {
    downloadBackup();
} elseif ($action === 'upload') {
    uploadBackup();
} else {
    http_response_code(400);
    die('Invalid action');
}

/**
 * Download database backup
 */
function downloadBackup()
{
    try {
        // Create PDO connection
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);

        // Get all tables
        $tables = [];
        $result = $pdo->query("SHOW TABLES");
        while ($row = $result->fetch(PDO::FETCH_NUM)) {
            $tables[] = $row[0];
        }

        if (empty($tables)) {
            http_response_code(500);
            die('No tables found in database');
        }

        // Start building SQL dump
        $sqlDump = "-- EasyCalf Database Backup\n";
        $sqlDump .= "-- Generated: " . date('Y-m-d H:i:s') . " UTC\n";
        $sqlDump .= "-- Database: " . DB_NAME . "\n";
        $sqlDump .= "-- Tables: " . count($tables) . "\n\n";
        $sqlDump .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
        $sqlDump .= "SET time_zone = \"+00:00\";\n";
        $sqlDump .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

        // Loop through each table
        foreach ($tables as $table) {
            // Get CREATE TABLE statement
            $createTableResult = $pdo->query("SHOW CREATE TABLE `{$table}`");
            $createTableRow = $createTableResult->fetch(PDO::FETCH_NUM);
            
            $sqlDump .= "\n-- --------------------------------------------------------\n";
            $sqlDump .= "-- Table structure for table `{$table}`\n";
            $sqlDump .= "-- --------------------------------------------------------\n\n";
            $sqlDump .= "DROP TABLE IF EXISTS `{$table}`;\n";
            $sqlDump .= $createTableRow[1] . ";\n\n";

            // Get table data
            $dataResult = $pdo->query("SELECT * FROM `{$table}`");
            $rows = $dataResult->fetchAll();

            if (!empty($rows)) {
                $sqlDump .= "-- Dumping data for table `{$table}` (" . count($rows) . " rows)\n\n";

                foreach ($rows as $row) {
                    $keys = array_keys($row);
                    $values = array_values($row);

                    // Escape values
                    $escapedValues = array_map(function($value) use ($pdo) {
                        if ($value === null) {
                            return 'NULL';
                        }
                        return $pdo->quote($value);
                    }, $values);

                    $sqlDump .= "INSERT INTO `{$table}` (`" . implode('`, `', $keys) . "`) VALUES (" . implode(', ', $escapedValues) . ");\n";
                }

                $sqlDump .= "\n";
            }
        }

        $sqlDump .= "SET FOREIGN_KEY_CHECKS = 1;\n";
        $sqlDump .= "\n-- Backup completed successfully\n";

        // Set headers for download
        $filename = 'easycalf_backup_' . date('Y-m-d_H-i-s') . '.sql';
        
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($sqlDump));
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');

        echo $sqlDump;
        exit;

    } catch (PDOException $e) {
        http_response_code(500);
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Backup Error</title></head><body>';
        echo '<h1>Backup Failed</h1>';
        echo '<p><strong>Error:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '<p><a href="/public/settings">← Back to Settings</a></p>';
        echo '</body></html>';
        exit;
    }
}

/**
 * Upload and restore database backup
 */
function uploadBackup()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        die('Method not allowed');
    }

    if (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['error_message'] = 'No file uploaded or upload error occurred.';
        header('Location: /public/settings');
        exit;
    }

    $file = $_FILES['backup_file'];
    $filename = $file['name'];
    $tmpPath = $file['tmp_name'];

    // Check file extension
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (!in_array($ext, ['sql', 'gz'])) {
        $_SESSION['error_message'] = 'Invalid file type. Only .sql or .gz files are allowed.';
        header('Location: /public/settings');
        exit;
    }

    // Check file size (max 50MB)
    if ($file['size'] > 50 * 1024 * 1024) {
        $_SESSION['error_message'] = 'File too large. Maximum size is 50MB.';
        header('Location: /public/settings');
        exit;
    }

    try {
        // Read file content
        if ($ext === 'gz') {
            // Decompress gzip file
            $content = @gzdecode(file_get_contents($tmpPath));
            if ($content === false) {
                throw new Exception('Failed to decompress .gz file. File may be corrupted.');
            }
        } else {
            $content = file_get_contents($tmpPath);
        }

        if (empty($content)) {
            throw new Exception('Backup file is empty.');
        }

        // Create PDO connection
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);

        // Disable foreign key checks temporarily
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        $pdo->exec("SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO'");

        // Split SQL into individual statements
        // Remove comments and empty lines
        $lines = explode("\n", $content);
        $statement = '';
        $executedCount = 0;
        $errors = [];

        foreach ($lines as $line) {
            $line = trim($line);
            
            // Skip comments and empty lines
            if (empty($line) || strpos($line, '--') === 0 || strpos($line, '/*') === 0) {
                continue;
            }

            $statement .= $line . "\n";

            // Check if statement is complete (ends with semicolon)
            if (substr($line, -1) === ';') {
                try {
                    $pdo->exec($statement);
                    $executedCount++;
                } catch (PDOException $e) {
                    $errors[] = 'Error executing statement: ' . $e->getMessage();
                    // Continue with next statement instead of stopping
                }
                $statement = '';
            }
        }

        // Re-enable foreign key checks
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

        if (!empty($errors)) {
            $_SESSION['error_message'] = "Restore completed with errors. Executed {$executedCount} statements. Errors: " . implode('; ', array_slice($errors, 0, 3));
        } else {
            $_SESSION['success_message'] = "✅ Backup restored successfully! Executed {$executedCount} SQL statements.";
        }
        
        header('Location: /public/settings');
        exit;

    } catch (PDOException $e) {
        $_SESSION['error_message'] = '❌ Restore failed: ' . $e->getMessage();
        header('Location: /public/settings');
        exit;
    } catch (Exception $e) {
        $_SESSION['error_message'] = '❌ Error: ' . $e->getMessage();
        header('Location: /public/settings');
        exit;
    }
}