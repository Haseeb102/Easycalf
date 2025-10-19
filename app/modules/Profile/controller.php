<?php
class ProfileController {
    private $db;
    private $auth;

    public function __construct() {
        $this->db = new Database();
        $this->auth = new Auth();
    }

    public function view() {
        if (!$this->auth->isLoggedIn()) {
            header('Location: /public/login');
            exit;
        }

        $user = $this->getUserDetails();
        $this->renderProfilePage($user);
    }

    public function update() {
        if (!$this->auth->isLoggedIn()) {
            header('Location: /public/login');
            exit;
        }

        $success = false;
        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
            try {
                $userId = $_SESSION['user_id'];
                
                // Validate inputs
                $firstName = trim($_POST['first_name'] ?? '');
                $lastName = trim($_POST['last_name'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $phone = trim($_POST['phone'] ?? '');
                $title = trim($_POST['title'] ?? '');
                $position = trim($_POST['position'] ?? '');

                if (empty($firstName) || empty($lastName) || empty($email)) {
                    throw new Exception("First name, last name, and email are required");
                }

                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception("Invalid email format");
                }

                // Check if email is already taken by another user
                $existingUser = $this->db->fetch(
                    "SELECT id FROM users WHERE email = ? AND id != ?",
                    [$email, $userId]
                );

                if ($existingUser) {
                    throw new Exception("This email is already in use by another account");
                }

                // Combine first and last name
                $fullName = $firstName . ' ' . $lastName;

                // Update user profile
                $this->db->query(
                    "UPDATE users SET 
                        name = ?,
                        email = ?,
                        phone = ?,
                        title = ?,
                        position = ?,
                        updated_at = NOW()
                    WHERE id = ?",
                    [$fullName, $email, $phone, $title, $position, $userId]
                );

                // Update session data
                $_SESSION['user_name'] = $fullName;
                $_SESSION['user_email'] = $email;

                $success = true;
                $_SESSION['success_message'] = "Profile updated successfully!";
                
                header('Location: /public/profile');
                exit;

            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }

        // If there was an error, show the form again
        $user = $this->getUserDetails();
        $this->renderProfilePage($user, $error);
    }

    public function changePassword() {
        if (!$this->auth->isLoggedIn()) {
            header('Location: /public/login');
            exit;
        }

        $success = false;
        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
            try {
                $userId = $_SESSION['user_id'];
                $currentPassword = $_POST['current_password'] ?? '';
                $newPassword = $_POST['new_password'] ?? '';
                $confirmPassword = $_POST['confirm_password'] ?? '';

                if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
                    throw new Exception("All password fields are required");
                }

                // Get current user password
                $user = $this->db->fetch(
                    "SELECT password FROM users WHERE id = ?",
                    [$userId]
                );

                if (!$user || !password_verify($currentPassword, $user['password'])) {
                    throw new Exception("Current password is incorrect");
                }

                if ($newPassword !== $confirmPassword) {
                    throw new Exception("New passwords do not match");
                }

                if (strlen($newPassword) < 6) {
                    throw new Exception("New password must be at least 6 characters long");
                }

                // Hash and update password
                $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
                
                $this->db->query(
                    "UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?",
                    [$hashedPassword, $userId]
                );

                $_SESSION['success_message'] = "Password changed successfully!";
                
                header('Location: /public/profile');
                exit;

            } catch (Exception $e) {
                $_SESSION['error_message'] = $e->getMessage();
                header('Location: /public/profile');
                exit;
            }
        }
    }

    private function getUserDetails() {
        $userId = $_SESSION['user_id'];
        $user = $this->db->fetch(
            "SELECT id, name, email, phone, title, position, role, status, created_at, last_login FROM users WHERE id = ?",
            [$userId]
        );

        if (!$user) {
            throw new Exception("User not found");
        }

        // Split name into first and last
        $nameParts = explode(' ', $user['name'], 2);
        $user['first_name'] = $nameParts[0] ?? '';
        $user['last_name'] = $nameParts[1] ?? '';

        return $user;
    }

    private function renderProfilePage($user, $error = null) {
        $successMessage = $_SESSION['success_message'] ?? null;
        $errorMessage = $_SESSION['error_message'] ?? $error;
        
        unset($_SESSION['success_message']);
        unset($_SESSION['error_message']);

        ?>
<!DOCTYPE html>
<html>
<head>
    <title>My Profile - EasyCalf</title>
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <style>
        .profile-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 2rem;
            padding-top: 140px;
        }

        .profile-header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, var(--mid-blue), var(--navy));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: white;
            margin: 0 auto 1rem;
            box-shadow: 0 8px 25px rgba(30, 111, 191, 0.3);
            font-weight: 700;
        }

        .profile-name {
            font-size: 2rem;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
        }

        .profile-role {
            color: var(--text-secondary);
            font-size: 1.1rem;
            font-weight: 600;
        }

        .profile-grid {
            display: grid;
            gap: 2rem;
        }

        .profile-section {
            background: var(--glass-card);
            backdrop-filter: var(--glass-blur);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-lg);
            padding: 2rem;
            box-shadow: var(--glass-shadow);
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--light-accent);
        }

        .section-icon {
            font-size: 1.5rem;
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--mid-blue), var(--navy));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-dark);
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }

        .form-group-full {
            grid-column: 1 / -1;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--text-dark);
            font-size: 0.9rem;
        }

        .form-control {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 1px solid var(--glass-border);
            border-radius: 8px;
            font-size: 1rem;
            font-family: inherit;
            background: var(--glass-card);
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--mid-blue);
            box-shadow: 0 0 0 3px rgba(30, 111, 191, 0.1);
        }

        .form-control:disabled {
            background: #f8f9fa;
            color: #6c757d;
            cursor: not-allowed;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--mid-blue), var(--navy));
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 1rem;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(30, 111, 191, 0.3);
        }

        .btn-secondary {
            background: var(--glass-card);
            color: var(--text-dark);
            border: 1px solid var(--glass-border);
            padding: 1rem 2rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            font-size: 1rem;
        }

        .btn-secondary:hover {
            background: var(--light-accent);
            transform: translateY(-2px);
        }

        .success-message {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            border-left: 4px solid #28a745;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .error-message {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            color: #721c24;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            border-left: 4px solid #dc3545;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .info-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .info-card {
            background: var(--light-accent);
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
        }

        .info-card-label {
            font-size: 0.8rem;
            color: var(--text-secondary);
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 0.5rem;
        }

        .info-card-value {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-dark);
        }

        .password-strength {
            height: 4px;
            background: #e9ecef;
            border-radius: 2px;
            margin-top: 0.5rem;
            overflow: hidden;
        }

        .password-strength-bar {
            height: 100%;
            width: 0;
            transition: all 0.3s ease;
            border-radius: 2px;
        }

        .strength-weak { width: 33%; background: #dc3545; }
        .strength-medium { width: 66%; background: #ffc107; }
        .strength-strong { width: 100%; background: #28a745; }

        @media (max-width: 768px) {
            .profile-container {
                padding: 1rem;
                padding-top: 100px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .profile-avatar {
                width: 100px;
                height: 100px;
                font-size: 2.5rem;
            }

            .profile-name {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <?php
    require_once BASE_PATH . '/app/core/ModernNavbar.php';
$navbar = new ModernNavbar();
    $navbar->render('profile');
    ?>
    
    <div class="profile-container">
        <!-- Profile Header -->
        <div class="profile-header">
            <div class="profile-avatar">
                <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
            </div>
            <h1 class="profile-name"><?php echo htmlspecialchars($user['name']); ?></h1>
            <p class="profile-role">
                <?php echo ucfirst(htmlspecialchars($user['role'])); ?>
                <?php if (!empty($user['position'])): ?>
                    • <?php echo htmlspecialchars($user['position']); ?>
                <?php endif; ?>
            </p>
        </div>

        <!-- Success/Error Messages -->
        <?php if ($successMessage): ?>
            <div class="success-message">
                <span style="font-size: 1.5rem;">✅</span>
                <?php echo htmlspecialchars($successMessage); ?>
            </div>
        <?php endif; ?>

        <?php if ($errorMessage): ?>
            <div class="error-message">
                <span style="font-size: 1.5rem;">⚠️</span>
                <?php echo htmlspecialchars($errorMessage); ?>
            </div>
        <?php endif; ?>

        <!-- Account Stats -->
        <div class="info-cards">
            <div class="info-card">
                <div class="info-card-label">Account Status</div>
                <div class="info-card-value" style="color: <?php echo $user['status'] == 'active' ? '#28a745' : '#ffc107'; ?>">
                    <?php echo ucfirst($user['status']); ?>
                </div>
            </div>
            <div class="info-card">
                <div class="info-card-label">Member Since</div>
                <div class="info-card-value">
                    <?php echo date('M Y', strtotime($user['created_at'])); ?>
                </div>
            </div>
            <div class="info-card">
                <div class="info-card-label">Last Login</div>
                <div class="info-card-value">
                    <?php echo $user['last_login'] ? date('M j, g:i A', strtotime($user['last_login'])) : 'Never'; ?>
                </div>
            </div>
        </div>

        <div class="profile-grid">
            <!-- Personal Information Section -->
            <div class="profile-section">
                <div class="section-header">
                    <div class="section-icon">👤</div>
                    <h2 class="section-title">Personal Information</h2>
                </div>

                <form method="post" action="/public/profile/update">
                    <input type="hidden" name="update_profile" value="1">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">First Name *</label>
                            <input type="text" name="first_name" class="form-control" required
                                   value="<?php echo htmlspecialchars($user['first_name']); ?>"
                                   placeholder="Enter your first name">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Last Name *</label>
                            <input type="text" name="last_name" class="form-control" required
                                   value="<?php echo htmlspecialchars($user['last_name']); ?>"
                                   placeholder="Enter your last name">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Email Address *</label>
                            <input type="email" name="email" class="form-control" required
                                   value="<?php echo htmlspecialchars($user['email']); ?>"
                                   placeholder="your.email@example.com">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" name="phone" class="form-control"
                                   value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                                   placeholder="+61 4XX XXX XXX">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Title</label>
                            <input type="text" name="title" class="form-control"
                                   value="<?php echo htmlspecialchars($user['title'] ?? ''); ?>"
                                   placeholder="e.g., Farm Manager, Owner">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Position</label>
                            <input type="text" name="position" class="form-control"
                                   value="<?php echo htmlspecialchars($user['position'] ?? ''); ?>"
                                   placeholder="e.g., Senior Manager">
                        </div>
                    </div>

                    <div style="margin-top: 2rem; display: flex; gap: 1rem;">
                        <button type="submit" class="btn-primary">
                            💾 Save Changes
                        </button>
                        <a href="/public/" class="btn-secondary">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>

            <!-- Security Section -->
            <div class="profile-section">
                <div class="section-header">
                    <div class="section-icon">🔒</div>
                    <h2 class="section-title">Security Settings</h2>
                </div>

                <form method="post" action="/public/profile/change-password" id="passwordForm">
                    <input type="hidden" name="change_password" value="1">
                    
                    <div class="form-group-full">
                        <label class="form-label">Current Password *</label>
                        <input type="password" name="current_password" class="form-control" required
                               placeholder="Enter your current password">
                    </div>

                    <div class="form-group-full">
                        <label class="form-label">New Password *</label>
                        <input type="password" name="new_password" id="newPassword" class="form-control" required
                               placeholder="Enter new password (min. 6 characters)"
                               minlength="6">
                        <div class="password-strength">
                            <div class="password-strength-bar" id="strengthBar"></div>
                        </div>
                    </div>

                    <div class="form-group-full">
                        <label class="form-label">Confirm New Password *</label>
                        <input type="password" name="confirm_password" id="confirmPassword" class="form-control" required
                               placeholder="Re-enter new password">
                    </div>

                    <div style="margin-top: 2rem;">
                        <button type="submit" class="btn-primary">
                            🔐 Change Password
                        </button>
                    </div>
                </form>

                <!-- Account Info -->
                <div style="margin-top: 2rem; padding-top: 2rem; border-top: 1px solid var(--glass-border);">
                    <h3 style="margin-bottom: 1rem; color: var(--text-dark); font-size: 1.1rem;">Account Role</h3>
                    <div style="background: var(--light-accent); padding: 1rem; border-radius: 8px;">
                        <strong>Role:</strong> <?php echo ucfirst(htmlspecialchars($user['role'])); ?><br>
                        <small style="color: var(--text-secondary);">
                            <?php if ($user['role'] === 'admin'): ?>
                                You have full administrative access to the system.
                            <?php else: ?>
                                You have standard user access. Contact an administrator for role changes.
                            <?php endif; ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Password strength meter
        const newPasswordInput = document.getElementById('newPassword');
        const strengthBar = document.getElementById('strengthBar');

        newPasswordInput.addEventListener('input', function() {
            const password = this.value;
            let strength = 0;

            // Calculate strength
            if (password.length >= 6) strength++;
            if (password.length >= 10) strength++;
            if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
            if (/\d/.test(password)) strength++;
            if (/[^a-zA-Z0-9]/.test(password)) strength++;

            // Update bar
            strengthBar.className = 'password-strength-bar';
            if (strength <= 2) {
                strengthBar.classList.add('strength-weak');
            } else if (strength <= 4) {
                strengthBar.classList.add('strength-medium');
            } else {
                strengthBar.classList.add('strength-strong');
            }
        });

        // Password confirmation validation
        const confirmPasswordInput = document.getElementById('confirmPassword');
        const passwordForm = document.getElementById('passwordForm');

        passwordForm.addEventListener('submit', function(e) {
            if (newPasswordInput.value !== confirmPasswordInput.value) {
                e.preventDefault();
                alert('New passwords do not match!');
                confirmPasswordInput.focus();
            }
        });
    </script>
</body>
</html>
        <?php
    }
}
?>