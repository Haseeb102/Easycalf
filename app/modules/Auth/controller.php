<?php
class AuthController {
    private $db;
    private $auth;

    public function __construct() {
        $this->db = new Database();
        $this->auth = new Auth();
    }

    public function login() {
        // If already logged in, redirect to dashboard
        if ($this->auth->isLoggedIn()) {
            header('Location: /public/');
            exit;
        }

        $error = null;
        
        // Handle login form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
            $email = $_POST['email'] ?? '';
            $password = $_POST['password'] ?? '';
            
            if (empty($email) || empty($password)) {
                $error = "Please enter both email and password";
            } else {
                if ($this->auth->login($email, $password)) {
                    // Login successful - redirect to dashboard
                    header('Location: /public/');
                    exit;
                } else {
                    $error = "Invalid email or password";
                }
            }
        }

        // Show login form
        $this->showLoginForm($error);
    }

    private function showLoginForm($error = null) {
        echo '<!DOCTYPE html>
        <html>
        <head>
            <title>Login - EasyCalf</title>
            <link rel="stylesheet" href="/public/assets/css/style.css">
            <style>
                .login-container {
                    min-height: 100vh;
                    background: linear-gradient(135deg, #1A365D, #2C5282);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 1rem;
                }
                .login-box {
                    background: white;
                    padding: 2.5rem;
                    border-radius: 16px;
                    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
                    width: 100%;
                    max-width: 400px;
                    border: 1px solid #E2E8F0;
                }
                .form-group {
                    margin-bottom: 1.5rem;
                }
                .form-label {
                    display: block;
                    margin-bottom: 0.5rem;
                    font-weight: 600;
                    color: #2D3748;
                    font-size: 0.9rem;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                }
                .form-control {
                    width: 100%;
                    padding: 0.875rem 1rem;
                    border: 1px solid #E2E8F0;
                    border-radius: 8px;
                    font-size: 1rem;
                    font-family: inherit;
                    transition: all 0.2s ease;
                    background: #F7FAFC;
                }
                .form-control:focus {
                    outline: none;
                    border-color: #3182CE;
                    box-shadow: 0 0 0 3px rgba(49, 130, 206, 0.1);
                    background: white;
                }
                .btn-login {
                    background: linear-gradient(135deg, #3182CE, #2C5282);
                    color: white;
                    border: none;
                    padding: 1rem 1.5rem;
                    border-radius: 8px;
                    font-size: 1rem;
                    font-weight: 600;
                    cursor: pointer;
                    width: 100%;
                    transition: all 0.2s ease;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                }
                .btn-login:hover {
                    background: linear-gradient(135deg, #2C5282, #1A365D);
                    transform: translateY(-1px);
                    box-shadow: 0 4px 12px rgba(49, 130, 206, 0.3);
                }
                .error-message {
                    background: #FED7D7;
                    color: #C53030;
                    padding: 1rem;
                    border-radius: 8px;
                    margin-bottom: 1.5rem;
                    border: 1px solid #FEB2B2;
                    font-weight: 500;
                }
                .login-header {
                    text-align: center;
                    margin-bottom: 2rem;
                }
                .login-logo {
                    font-size: 3rem;
                    margin-bottom: 1rem;
                }
                .login-title {
                    font-size: 1.75rem;
                    font-weight: 800;
                    color: #1A365D;
                    margin-bottom: 0.5rem;
                }
                .login-subtitle {
                    color: #718096;
                    font-size: 1rem;
                }
                .register-link {
                    text-align: center;
                    margin-top: 1.5rem;
                    padding-top: 1.5rem;
                    border-top: 1px solid #E2E8F0;
                }
                .register-link a {
                    color: #3182CE;
                    text-decoration: none;
                    font-weight: 600;
                }
                .register-link a:hover {
                    color: #2C5282;
                    text-decoration: underline;
                }
            </style>
        </head>
        <body>
            <div class="login-container">
                <div class="login-box">
                    <div class="login-header">
                        <div class="login-logo">🐄</div>
                        <h1 class="login-title">EASYCALF</h1>
                        <p class="login-subtitle">Farm Management System</p>
                    </div>
                    
                    ' . ($error ? '<div class="error-message">' . htmlspecialchars($error) . '</div>' : '') . '
                    
                    <form method="post">
                        <input type="hidden" name="login" value="1">
                        
                        <div class="form-group">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="email" class="form-control" required 
                                   placeholder="Enter your email address">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required 
                                   placeholder="Enter your password">
                        </div>
                        
                        <button type="submit" class="btn-login">Sign In</button>
                    </form>
                    
                    <div class="register-link">
                        <p>Don\'t have an account? <a href="/public/register">Create account</a></p>
                    </div>
                </div>
            </div>
            
            <!-- Debug Info -->
            <div style="position: fixed; bottom: 10px; right: 10px; background: rgba(0,0,0,0.8); color: white; padding: 0.5rem; border-radius: 4px; font-size: 0.8rem;">
                Session: ' . (session_id() ? 'Active' : 'Not started') . '
            </div>
        </body>
        </html>';
    }

    public function logout() {
        $this->auth->logout();
        header('Location: /public/login');
        exit;
    }

    public function register() {
        if ($this->auth->isLoggedIn()) {
            header('Location: /public/');
            exit;
        }

        $success = false;
        $error = null;

        if ($_POST['register']) {
            try {
                if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
                    throw new Exception("Invalid email format");
                }

                // Check if email exists
                $existing = $this->db->fetch(
                    "SELECT id FROM users WHERE email = ?",
                    [$_POST['email']]
                );
                if ($existing) {
                    throw new Exception("Email already registered");
                }

                // Hash password
                $hashedPassword = password_hash($_POST['password'], PASSWORD_BCRYPT);

                // Insert user
                $this->db->query(
                    "INSERT INTO users (name, email, password, role, status, created_at) 
                     VALUES (?, ?, ?, 'user', 'pending', NOW())",
                    [$_POST['name'], $_POST['email'], $hashedPassword]
                );

                $success = true;
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }

        echo '<!DOCTYPE html>
        <html>
        <head>
            <title>Register - EasyCalf</title>
            <link rel="stylesheet" href="/public/assets/css/style.css">
        </head>
        <body>
            <div class="navbar">
                <div class="navbar-brand">EasyCalf</div>
            </div>
            <div class="container">
                <div style="max-width: 400px; margin: 50px auto;">
                    <h2>Create Account</h2>
                    ' . ($success ? '<div style="color: green; margin: 1rem 0; padding: 1rem; background: #f0fff0; border-radius: 8px;">Registration successful! Please wait for admin approval.</div>' : '') . '
                    ' . ($error ? '<div style="color: red; margin: 1rem 0; padding: 1rem; background: #fff0f0; border-radius: 8px;">' . $error . '</div>' : '') . '
                    ' . (!$success ? '
                    <form method="post">
                        <input type="hidden" name="register" value="1">
                        <div class="form-group">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required minlength="6">
                        </div>
                        <button type="submit" class="btn btn-primary" style="width: 100%;">Register</button>
                    </form>
                    ' : '') . '
                    <p style="text-align: center; margin-top: 1rem;">
                        <a href="/public/login">Back to Login</a>
                    </p>
                </div>
            </div>
        </body>
        </html>';
    }
}
?>