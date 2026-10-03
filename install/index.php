<?php
// Verbose errors stay off unless APP_DEBUG is explicitly enabled.
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');

$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/app/config/env.php';
easycalf_load_env();
easycalf_configure_error_display();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['install_token'])) {
    $_SESSION['install_token'] = bin2hex(random_bytes(16));
}

$error = null;
$success = false;
$configPath = null;
$debugInfo = [];

function install_debug(array &$debugInfo, string $line): void
{
    if (easycalf_debug_enabled()) {
        $debugInfo[] = $line;
    }
}

function install_valid_identifier(string $value): bool
{
    return $value !== '' && (bool) preg_match('/^[A-Za-z0-9._:-]+$/', $value);
}

function install_run_sql_file(PDO $pdo, string $path): void
{
    if (!is_file($path)) {
        throw new RuntimeException('A required SQL file is missing.');
    }

    $sql = file_get_contents($path);
    if ($sql === false) {
        throw new RuntimeException('Could not read a required SQL file.');
    }

    $statements = array_filter(array_map('trim', explode(';', $sql)), function ($query) {
        foreach (preg_split('/\R/', $query) as $line) {
            $line = trim($line);
            if ($line !== '' && !str_starts_with($line, '--')) {
                return true;
            }
        }
        return false;
    });

    foreach ($statements as $query) {
        $pdo->exec($query);
    }
}

if (easycalf_config_is_complete()) {
    $alreadyInstalled = true;
} else {
    $alreadyInstalled = false;
}

if (!$alreadyInstalled && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['install_token'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['install_token'], $token)) {
        $error = 'The installation form expired. Reload the page and try again.';
    } else {
        $dbHost = trim((string) ($_POST['db_host'] ?? ''));
        $dbName = trim((string) ($_POST['db_name'] ?? ''));
        $dbUser = trim((string) ($_POST['db_user'] ?? ''));
        $dbPass = (string) ($_POST['db_pass'] ?? '');
        $baseUrl = trim((string) ($_POST['base_url'] ?? ''));
        $adminName = trim((string) ($_POST['admin_name'] ?? ''));
        $adminEmail = trim((string) ($_POST['admin_email'] ?? ''));
        $adminPassword = (string) ($_POST['admin_password'] ?? '');
        $adminPasswordConfirm = (string) ($_POST['admin_password_confirm'] ?? '');

        if (!install_valid_identifier($dbHost) || !install_valid_identifier($dbName) || !install_valid_identifier($dbUser)) {
            $error = 'Enter a database host, name, and user. Use only letters, numbers, dots, hyphens, and underscores.';
        } elseif ($dbPass === '') {
            $error = 'Enter the database password. It is not stored in the application code.';
        } elseif ($adminName === '' || strlen($adminName) > 100) {
            $error = 'Enter an administrator name.';
        } elseif (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            $error = 'Enter a valid administrator email address.';
        } elseif (strlen($adminPassword) < 8) {
            $error = 'Choose an administrator password of at least 8 characters.';
        } elseif (!hash_equals($adminPassword, $adminPasswordConfirm)) {
            $error = 'The administrator passwords do not match.';
        } else {
            try {
                install_debug($debugInfo, 'Testing database connection...');
                $dsn = 'mysql:host=' . $dbHost . ';dbname=' . $dbName . ';charset=utf8mb4';
                $pdo = new PDO($dsn, $dbUser, $dbPass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ]);
                install_debug($debugInfo, 'Database connection succeeded.');

                foreach ([$projectRoot . '/app/storage/uploads', $projectRoot . '/app/storage/backups'] as $dir) {
                    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
                        throw new RuntimeException('Could not create a storage directory.');
                    }
                }

                install_debug($debugInfo, 'Importing database schema...');
                install_run_sql_file($pdo, $projectRoot . '/database/schema.sql');

                $passwordHash = password_hash($adminPassword, PASSWORD_DEFAULT);
                if ($passwordHash === false) {
                    throw new RuntimeException('Could not hash the administrator password.');
                }

                $insert = $pdo->prepare(
                    'INSERT INTO `users` (`name`, `email`, `password`, `role`, `status`, `approved_at`)
                     VALUES (?, ?, ?, \'admin\', \'active\', NOW())'
                );
                $insert->execute([$adminName, $adminEmail, $passwordHash]);
                unset($adminPassword, $adminPasswordConfirm, $passwordHash);

                install_debug($debugInfo, 'Importing default records...');
                install_run_sql_file($pdo, $projectRoot . '/database/seed.sql');

                $configPath = easycalf_env_set([
                    'DB_HOST' => $dbHost,
                    'DB_NAME' => $dbName,
                    'DB_USER' => $dbUser,
                    'DB_PASS' => $dbPass,
                    'DB_CHARSET' => 'utf8mb4',
                    'APP_NAME' => 'EasyCalf',
                    'APP_VERSION' => '1.0',
                    'BASE_URL' => $baseUrl,
                    'APP_DEBUG' => 'false',
                ]);
                unset($dbPass);

                $success = true;
                $_SESSION['install_token'] = bin2hex(random_bytes(16));
                install_debug($debugInfo, 'Installation finished.');
            } catch (EasyCalfConfigException $e) {
                $error = $e->getMessage();
                install_debug($debugInfo, $e->getMessage());
            } catch (PDOException $e) {
                error_log('EasyCalf install database error: ' . $e->getMessage());
                $error = 'Database setup failed. Check the host, database name, user, and password, and that the database already exists.';
                if (easycalf_debug_enabled()) {
                    $error .= ' ' . $e->getMessage();
                }
                install_debug($debugInfo, $e->getMessage());
            } catch (Throwable $e) {
                error_log('EasyCalf install error: ' . $e->getMessage());
                $error = 'Installation failed. ' . $e->getMessage();
                install_debug($debugInfo, $e->getMessage());
            }
        }

        unset($dbPass, $adminPassword, $adminPasswordConfirm);
    }
}
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
        h1 { color: #007AFF; margin-bottom: 1rem; text-align: center; }
        .success, .error, .info {
            padding: 1rem;
            border-radius: 8px;
            margin: 1rem 0;
        }
        .success { background: #d4edda; color: #155724; }
        .error { background: #f8d7da; color: #721c24; }
        .info { background: #d1ecf1; color: #0c5460; }
        label { display: block; font-weight: 600; margin: 0.8rem 0 0.3rem; }
        input[type="text"], input[type="password"], input[type="email"] {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 1rem;
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
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        .btn:hover { background: #0056cc; }
        .debug {
            background: #f8f9fa;
            color: #333;
            padding: 1rem;
            border-radius: 8px;
            margin: 1rem 0;
            font-family: monospace;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="install-container">
        <h1>EasyCalf Installation</h1>

        <?php if (easycalf_debug_enabled() && $debugInfo): ?>
            <div class="debug">
                <?php foreach ($debugInfo as $line): ?>
                    <div><?= htmlspecialchars($line) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($alreadyInstalled): ?>
            <div class="info">
                <strong>Already configured.</strong><br>
                Database settings are already present in the environment or the local configuration file.
                Delete the install directory, then sign in with the administrator account you created.
            </div>
        <?php elseif ($success): ?>
            <div class="success">
                <h3>Installation successful</h3>
                <p>EasyCalf saved the database settings outside the web application code<?php if ($configPath): ?> (<code><?= htmlspecialchars($configPath) ?></code>)<?php endif; ?>.</p>
                <p>Sign in with the administrator email and password you just chose. There is no default password.</p>
                <p><strong>Next:</strong> delete or block the install directory. If this file is inside a directory the web server can read, move it further outside the web root and set <code>EASYCALF_ENV_FILE</code> to that path.</p>
            </div>
            <a href="../public/" class="btn">Go to EasyCalf</a>
        <?php else: ?>
            <?php if ($error): ?>
                <div class="error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="info">
                Enter the database you already created, and choose the first administrator account.
                These values are written to a configuration file outside git. They are not saved in the application source.
            </div>

            <form method="post" autocomplete="off">
                <input type="hidden" name="install_token" value="<?= htmlspecialchars($_SESSION['install_token']) ?>">

                <label for="db_host">Database host</label>
                <input type="text" id="db_host" name="db_host" required value="<?= htmlspecialchars($_POST['db_host'] ?? '') ?>">

                <label for="db_name">Database name</label>
                <input type="text" id="db_name" name="db_name" required value="<?= htmlspecialchars($_POST['db_name'] ?? '') ?>">

                <label for="db_user">Database user</label>
                <input type="text" id="db_user" name="db_user" required value="<?= htmlspecialchars($_POST['db_user'] ?? '') ?>">

                <label for="db_pass">Database password</label>
                <input type="password" id="db_pass" name="db_pass" required autocomplete="new-password">

                <label for="base_url">Base URL (optional)</label>
                <input type="text" id="base_url" name="base_url" value="<?= htmlspecialchars($_POST['base_url'] ?? '') ?>" placeholder="https://example.com">

                <label for="admin_name">Administrator name</label>
                <input type="text" id="admin_name" name="admin_name" required value="<?= htmlspecialchars($_POST['admin_name'] ?? '') ?>">

                <label for="admin_email">Administrator email</label>
                <input type="email" id="admin_email" name="admin_email" required value="<?= htmlspecialchars($_POST['admin_email'] ?? '') ?>">

                <label for="admin_password">Administrator password</label>
                <input type="password" id="admin_password" name="admin_password" required minlength="8" autocomplete="new-password">

                <label for="admin_password_confirm">Confirm administrator password</label>
                <input type="password" id="admin_password_confirm" name="admin_password_confirm" required minlength="8" autocomplete="new-password">

                <button type="submit" class="btn">Install EasyCalf</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
