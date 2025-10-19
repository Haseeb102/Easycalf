<?php
class Auth {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
        $this->initSession();
    }

    private function initSession() {
        if (session_status() === PHP_SESSION_NONE) {
            // Set session settings BEFORE starting session
            ini_set('session.use_strict_mode', 1);
            ini_set('session.use_only_cookies', 1);
            ini_set('session.cookie_httponly', 1);
            
            session_start();
        } else {
            // Session already started, just update activity
            $_SESSION['last_activity'] = time();
        }
        
        // Check session timeout (20 minutes) - only for real users, not public access
        if (isset($_SESSION['last_activity']) && 
            (time() - $_SESSION['last_activity'] > 1200) && 
            !$this->isPublicAccess()) {
            $this->logout();
            return;
        }
        $_SESSION['last_activity'] = time();
        
        // Auto-login for public access if enabled
        $this->handlePublicAccess();
    }
    
    private function handlePublicAccess() {
        if (PUBLIC_ACCESS_ENABLED && !$this->isLoggedIn()) {
            // Check for access code if required
            if (!empty(PUBLIC_ACCESS_CODE)) {
                $providedCode = $_GET['access_code'] ?? $_POST['access_code'] ?? $_SESSION['public_access_code'] ?? '';
                
                if (empty($providedCode)) {
                    // Show access code form
                    $this->showAccessCodeForm();
                    exit;
                }
                
                if ($providedCode !== PUBLIC_ACCESS_CODE) {
                    // Invalid code
                    $this->showAccessCodeForm('Invalid access code');
                    exit;
                }
                
                // Store valid code in session
                $_SESSION['public_access_code'] = $providedCode;
            }
            
            // Log in as public user
            $this->loginAsPublicUser();
        }
    }
    
    private function showAccessCodeForm($error = null) {
        echo '<!DOCTYPE html>
        <html>
        <head>
            <title>Access Required - EasyCalf</title>
            <style>
                body { font-family: Arial, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
                .access-form { background: white; padding: 2rem; border-radius: 10px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); text-align: center; max-width: 400px; width: 90%; }
                .error { color: #e74c3c; margin-bottom: 1rem; }
                input { width: 100%; padding: 12px; margin: 10px 0; border: 1px solid #ddd; border-radius: 5px; font-size: 16px; }
                button { background: #3498db; color: white; border: none; padding: 12px 30px; border-radius: 5px; cursor: pointer; font-size: 16px; width: 100%; }
                button:hover { background: #2980b9; }
            </style>
        </head>
        <body>
            <div class="access-form">
                <h2>🔒 Access Required</h2>
                <p>Please enter the access code to continue:</p>
                ' . ($error ? '<div class="error">' . htmlspecialchars($error) . '</div>' : '') . '
                <form method="post">
                    <input type="password" name="access_code" placeholder="Enter access code" required>
                    <button type="submit">Access System</button>
                </form>
            </div>
        </body>
        </html>';
    }
    
    private function loginAsPublicUser() {
        // Set session data for public user
        $_SESSION['user_id'] = PUBLIC_USER_ID;
        $_SESSION['user_role'] = PUBLIC_USER_ROLE;
        $_SESSION['user_name'] = PUBLIC_USER_NAME;
        $_SESSION['user_email'] = PUBLIC_USER_EMAIL;
        $_SESSION['last_activity'] = time();
        $_SESSION['logged_in'] = true;
        $_SESSION['public_access'] = true; // Mark as public access session
    }

    public function login($email, $password) {
        // Clear any existing session data but preserve the session
        $_SESSION = [];
        
        $user = $this->db->fetch(
            "SELECT * FROM users WHERE email = ? AND status = 'active'",
            [$email]
        );
        
        if ($user && password_verify($password, $user['password'])) {
            // Regenerate session ID for security
            session_regenerate_id(true);
            
            // Set session data
            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['last_activity'] = time();
            $_SESSION['logged_in'] = true;
            $_SESSION['public_access'] = false; // Mark as real user session
            
            // Update last login
            $this->db->query(
                "UPDATE users SET last_login = NOW() WHERE id = ?",
                [$user['id']]
            );
            
            return true;
        }
        
        return false;
    }

    public function logout() {
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        
        // Redirect to appropriate page after logout
        if (PUBLIC_ACCESS_ENABLED) {
            header('Location: /public/');
        } else {
            header('Location: /public/login');
        }
        exit;
    }

    public function isLoggedIn() {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }

    public function isAdmin() {
        if ($this->isPublicAccess()) {
            return false; // Public users are never admins
        }
        return $this->isLoggedIn() && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
    }
    
    public function isPublicAccess() {
        return isset($_SESSION['public_access']) && $_SESSION['public_access'] === true;
    }
    
    public function canAccess($permission) {
        if ($this->isPublicAccess()) {
            // Check public access restrictions
            return !(PUBLIC_ACCESS_RESTRICTIONS[$permission] ?? false);
        }
        return true; // Real users have full access (subject to their role)
    }

    public function getUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        return [
            'id' => $_SESSION['user_id'] ?? null,
            'name' => $_SESSION['user_name'] ?? null,
            'email' => $_SESSION['user_email'] ?? null,
            'role' => $_SESSION['user_role'] ?? null,
            'is_public' => $this->isPublicAccess()
        ];
    }
}
?>