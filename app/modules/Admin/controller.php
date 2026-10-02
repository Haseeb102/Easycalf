<?php
class AdminController {
    private $db;
    private $auth;

    public function __construct() {
        $this->db = new Database();
        $this->auth = new Auth();
    }

    public function publicAccessToggle() {
        if (!$this->auth->isLoggedIn() || !$this->auth->isAdmin()) {
            header('Location: /public/login');
            exit;
        }

        $success = false;
        $error = null;

        $displayEnabled = defined('PUBLIC_ACCESS_ENABLED') && PUBLIC_ACCESS_ENABLED;
        $displayCode = defined('PUBLIC_ACCESS_CODE') ? (string) PUBLIC_ACCESS_CODE : '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                require_once BASE_PATH . '/app/config/env.php';
                $enabled = isset($_POST['public_access']);
                $accessCode = trim($_POST['access_code'] ?? '');

                // Keep this value in the local configuration file, not in committed PHP.
                easycalf_env_set([
                    'PUBLIC_ACCESS_ENABLED' => $enabled ? 'true' : 'false',
                    'PUBLIC_ACCESS_CODE' => $accessCode,
                ]);

                $displayEnabled = $enabled;
                $displayCode = $accessCode;
                $success = true;
                $_SESSION['success_message'] = "Public access settings updated successfully!";
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }

        $this->renderPublicAccessSettings($success, $error, $displayEnabled, $displayCode);
    }

    private function renderPublicAccessSettings($success, $error, $displayEnabled = null, $displayCode = null) {
        if ($displayEnabled === null) {
            $displayEnabled = defined('PUBLIC_ACCESS_ENABLED') && PUBLIC_ACCESS_ENABLED;
        }
        if ($displayCode === null) {
            $displayCode = defined('PUBLIC_ACCESS_CODE') ? (string) PUBLIC_ACCESS_CODE : '';
        }
        require_once BASE_PATH . '/app/core/ModernNavbar.php';
        $navbar = new ModernNavbar();
        ?>
<!DOCTYPE html>
<html>
<head>
    <title>Public Access Settings - EasyCalf</title>
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <style>
        .settings-container { max-width: 600px; margin: 0 auto; padding: 2rem; padding-top: 100px; }
        .toggle-switch { display: flex; align-items: center; gap: 1rem; margin: 1.5rem 0; }
        .switch { position: relative; display: inline-block; width: 60px; height: 34px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 34px; }
        .slider:before { position: absolute; content: ""; height: 26px; width: 26px; left: 4px; bottom: 4px; background-color: white; transition: .4s; border-radius: 50%; }
        input:checked + .slider { background-color: #2196F3; }
        input:checked + .slider:before { transform: translateX(26px); }
        .access-code { margin: 1.5rem 0; }
    </style>
</head>
<body>
    <?php $navbar->render('admin'); ?>
    
    <div class="settings-container">
        <h1>🌐 Public Access Settings</h1>
        
        <?php if ($success): ?>
            <div style="background: #d4edda; color: #155724; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                ✅ Settings updated successfully!
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div style="background: #f8d7da; color: #721c24; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                ⚠️ Error: <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="glass-card">
            <form method="post">
                <div class="toggle-switch">
                    <label class="switch">
                        <input type="checkbox" name="public_access" <?php echo $displayEnabled ? 'checked' : ''; ?>>
                        <span class="slider"></span>
                    </label>
                    <span style="font-weight: 600; font-size: 1.1rem;">
                        <?php echo $displayEnabled ? 'Public Access: ON' : 'Public Access: OFF'; ?>
                    </span>
                </div>
                
                <div class="access-code">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">
                        Access Code (optional):
                    </label>
                    <input type="text" name="access_code" class="form-control" 
                           value="<?php echo htmlspecialchars($displayCode); ?>" 
                           placeholder="Leave empty for fully public access">
                    <small style="color: #666; display: block; margin-top: 0.5rem;">
                        If set, users will need to enter this code to access the system
                    </small>
                </div>
                
                <div style="background: #e7f3ff; padding: 1rem; border-radius: 8px; margin: 1.5rem 0;">
                    <strong>Current Status:</strong><br>
                    <?php if ($displayEnabled): ?>
                        ✅ System is <strong>PUBLIC</strong> - Anyone can access without login
                        <?php if (!empty($displayCode)): ?>
                            <br>🔐 Access code required: <code><?php echo htmlspecialchars($displayCode); ?></code>
                        <?php else: ?>
                            <br>🌍 Fully open - No access code required
                        <?php endif; ?>
                    <?php else: ?>
                        🔒 System is <strong>PRIVATE</strong> - Login required
                    <?php endif; ?>
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%;">
                    💾 Save Settings
                </button>
            </form>
        </div>
        
        <div style="margin-top: 2rem; padding: 1.5rem; background: #fff3cd; border-radius: 12px;">
            <h3>📖 How It Works</h3>
            <ul style="margin: 0.5rem 0 0 1.5rem; line-height: 1.6;">
                <li><strong>Public Access OFF:</strong> Users must login with valid credentials</li>
                <li><strong>Public Access ON + No Code:</strong> Anyone can access immediately</li>
                <li><strong>Public Access ON + With Code:</strong> Users enter code once to access</li>
                <li><strong>Public User Restrictions:</strong> Public users cannot access admin features</li>
            </ul>
        </div>
    </div>
</body>
</html>
        <?php
    }
}
?>