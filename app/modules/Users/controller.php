<?php
class UsersController {
    private $db;
    private $auth;

    public function __construct() {
        $this->db = new Database();
        $this->auth = new Auth();
    }

    public function list() {
        if (!$this->auth->isLoggedIn() || !$this->auth->isAdmin()) {
            header('Location: /login');
            exit;
        }

        $users = $this->db->fetchAll("
            SELECT id, name, email, role, status, created_at, approved_at, last_login
            FROM users 
            ORDER BY created_at DESC
        ");

        echo '<!DOCTYPE html>
        <html>
        <head>
            <title>User Management - EasyCalf</title>
            <link rel="stylesheet" href="/assets/css/style.css">
        </head>
        <body>
            <div class="navbar">
                <div class="navbar-brand">EasyCalf</div>
                <div style="margin-left: auto; display: flex; align-items: center; gap: 1rem;">
                    <a href="/" class="btn">Dashboard</a>
                    <span>Welcome, ' . htmlspecialchars($_SESSION['user_name']) . '</span>
                    <a href="/logout" class="btn" style="background: rgba(255,255,255,0.2);">Logout</a>
                </div>
            </div>
            
            <div class="container">
                <h1>User Management</h1>
                
                <div style="background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="background: #f8f9fa;">
                                <th style="padding: 1rem; text-align: left; border-bottom: 1px solid #eee;">Name</th>
                                <th style="padding: 1rem; text-align: left; border-bottom: 1px solid #eee;">Email</th>
                                <th style="padding: 1rem; text-align: left; border-bottom: 1px solid #eee;">Role</th>
                                <th style="padding: 1rem; text-align: left; border-bottom: 1px solid #eee;">Status</th>
                                <th style="padding: 1rem; text-align: left; border-bottom: 1px solid #eee;">Created</th>
                                <th style="padding: 1rem; text-align: left; border-bottom: 1px solid #eee;">Last Login</th>
                                <th style="padding: 1rem; text-align: left; border-bottom: 1px solid #eee;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            ' . $this->renderUsersTable($users) . '
                        </tbody>
                    </table>
                </div>
            </div>
        </body>
        </html>';
    }

    private function renderUsersTable($users) {
        $html = '';
        foreach ($users as $user) {
            $statusColor = $user['status'] == 'active' ? '#28a745' : 
                          ($user['status'] == 'pending' ? '#ffc107' : '#dc3545');
            
            $roleColor = $user['role'] == 'admin' ? '#007AFF' : '#6c757d';
            
            $html .= '
            <tr>
                <td style="padding: 1rem; border-bottom: 1px solid #eee;">
                    <strong>' . htmlspecialchars($user['name']) . '</strong>
                </td>
                <td style="padding: 1rem; border-bottom: 1px solid #eee;">' . htmlspecialchars($user['email']) . '</td>
                <td style="padding: 1rem; border-bottom: 1px solid #eee;">
                    <span style="color: ' . $roleColor . '; font-weight: 600;">
                        ' . ucfirst($user['role']) . '
                    </span>
                </td>
                <td style="padding: 1rem; border-bottom: 1px solid #eee;">
                    <span style="color: ' . $statusColor . '; font-weight: 600;">
                        ' . ucfirst($user['status']) . '
                    </span>
                </td>
                <td style="padding: 1rem; border-bottom: 1px solid #eee;">' . date('M j, Y', strtotime($user['created_at'])) . '</td>
                <td style="padding: 1rem; border-bottom: 1px solid #eee;">' . ($user['last_login'] ? date('M j, Y g:i A', strtotime($user['last_login'])) : 'Never') . '</td>
                <td style="padding: 1rem; border-bottom: 1px solid #eee;">
                    ' . $this->renderUserActions($user) . '
                </td>
            </tr>';
        }
        return $html;
    }

    private function renderUserActions($user) {
        $actions = '';
        
        // Only show approve action for pending users
        if ($user['status'] == 'pending') {
            $actions .= '
            <form method="post" action="/admin/approve" style="display: inline;">
                <input type="hidden" name="user_id" value="' . $user['id'] . '">
                <button type="submit" class="btn btn-success" style="padding: 0.5rem 1rem; font-size: 0.8rem;">
                    Approve
                </button>
            </form>';
        }
        
        // Don't allow deleting your own account or the main admin
        if ($user['id'] != $_SESSION['user_id'] && $user['email'] != 'admin@easycalf.com') {
            $actions .= '
            <form method="post" action="/admin/delete" style="display: inline; margin-left: 0.5rem;">
                <input type="hidden" name="user_id" value="' . $user['id'] . '">
                <button type="submit" class="btn btn-danger" style="padding: 0.5rem 1rem; font-size: 0.8rem;" 
                        onclick="return confirm(\'Are you sure you want to delete this user?\')">
                    Delete
                </button>
            </form>';
        }
        
        return $actions ?: '<span style="color: #666;">No actions</span>';
    }

    public function approve() {
        if (!$this->auth->isLoggedIn() || !$this->auth->isAdmin()) {
            header('Location: /login');
            exit;
        }

        if ($_POST['user_id']) {
            $this->db->query(
                "UPDATE users SET status = 'active', approved_at = NOW() WHERE id = ? AND status = 'pending'",
                [$_POST['user_id']]
            );
        }

        header('Location: /admin/users');
        exit;
    }

    public function delete() {
        if (!$this->auth->isLoggedIn() || !$this->auth->isAdmin()) {
            header('Location: /login');
            exit;
        }

        if ($_POST['user_id']) {
            $user = $this->db->fetch("SELECT email FROM users WHERE id = ?", [$_POST['user_id']]);
            
            // Prevent deleting the main admin account
            if ($user && $user['email'] != 'admin@easycalf.com') {
                $this->db->query("DELETE FROM users WHERE id = ?", [$_POST['user_id']]);
            }
        }

        header('Location: /admin/users');
        exit;
    }
}
?>