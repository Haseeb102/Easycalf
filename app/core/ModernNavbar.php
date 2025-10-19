<?php
class ModernNavbar {
    public function render($activePage = 'dashboard') {
        try {
            // Start session if not already started
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            $auth = new Auth();
            $user = $auth->getUser();
            
            if (!$user) {
                error_log("Navbar: No user data available - using fallback");
                $this->renderFallbackNavbar();
                return;
            }
            
            $this->renderNavbar($user, $auth, $activePage);
            
        } catch (Exception $e) {
            error_log("Navbar Error: " . $e->getMessage());
            $this->renderFallbackNavbar();
        }
    }
    
    private function renderNavbar($user, $auth, $activePage) {
        ?>
        <nav class="modern-navbar" id="modernNavbar">
            <div class="modern-navbar-brand">
                <span class="navbar-icon">🐄</span>
                <span class="navbar-title">EasyCalf</span>
                <?php if ($auth->isPublicAccess()): ?>
                <span class="navbar-badge">Public Mode</span>
                <?php endif; ?>
            </div>
            
            <div class="modern-nav-links">
                <a href="/public/" class="modern-nav-item <?= $activePage === 'dashboard' ? 'modern-nav-active' : '' ?>">
                    <span class="nav-icon">📊</span>
                    <span class="nav-text">Dashboard</span>
                </a>
                
                <a href="/public/calves" class="modern-nav-item <?= $activePage === 'calves' ? 'modern-nav-active' : '' ?>">
                    <span class="nav-icon">🐮</span>
                    <span class="nav-text">Calves</span>
                </a>
                
                <a href="/public/tasks" class="modern-nav-item <?= $activePage === 'tasks' ? 'modern-nav-active' : '' ?>">
                    <span class="nav-icon">✅</span>
                    <span class="nav-text">Tasks</span>
                </a>
                
                <a href="/public/milk/calculator" class="modern-nav-item <?= $activePage === 'milk' ? 'modern-nav-active' : '' ?>">
                    <span class="nav-icon">🥛</span>
                    <span class="nav-text">Milk Calculator</span>
                </a>

                <a href="/public/treatment" class="modern-nav-item <?= $activePage === 'treatment' ? 'modern-nav-active' : '' ?>">
                    <span class="nav-icon">💊</span>
                    <span class="nav-text">Treatments</span>
                </a>

                <a href="/public/batches" class="modern-nav-item <?= $activePage === 'batches' ? 'modern-nav-active' : '' ?>">
                    <span class="nav-icon">🏠</span>
                    <span class="nav-text">Batches</span>
                </a>

                <?php if ($user['role'] === 'admin' && !$auth->isPublicAccess()): ?>
                <a href="/public/settings" class="modern-nav-item <?= $activePage === 'settings' ? 'modern-nav-active' : '' ?>">
                    <span class="nav-icon">⚙️</span>
                    <span class="nav-text">Settings</span>
                </a>
                <?php endif; ?>
            </div>
            
            <div class="modern-user-menu">
                <span class="user-name"><?= htmlspecialchars($user['name']) ?></span>
                <?php if ($auth->isPublicAccess()): ?>
                    <span class="user-badge">Public</span>
                <?php endif; ?>
                <a href="/public/profile" class="modern-nav-item" style="padding: 0.5rem; margin-left: 0.5rem;">
                    <span class="nav-icon">👤</span>
                </a>
                <a href="/public/logout" class="modern-nav-item" style="padding: 0.5rem;">
                    <span class="nav-icon">🚪</span>
                </a>
            </div>
        </nav>

        <style>
            .modern-navbar {
                background: linear-gradient(135deg, #1E88E5, #A1C349);
                padding: 1rem 2rem;
                box-shadow: 0 4px 12px rgba(0,0,0,0.1);
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                z-index: 1000;
                display: flex;
                align-items: center;
                justify-content: space-between;
                min-height: 70px;
            }
            
            .modern-navbar-brand {
                display: flex;
                align-items: center;
                gap: 0.75rem;
                color: white;
                font-weight: 700;
                font-size: 1.5rem;
            }
            
            .modern-nav-links {
                display: flex;
                gap: 0.5rem;
                align-items: center;
            }
            
            .modern-nav-item {
                display: flex;
                align-items: center;
                gap: 0.5rem;
                color: white;
                text-decoration: none;
                padding: 0.75rem 1rem;
                border-radius: 8px;
                transition: all 0.3s ease;
                font-weight: 500;
            }
            
            .modern-nav-item:hover {
                background: rgba(255,255,255,0.15);
                transform: translateY(-1px);
            }
            
            .modern-nav-active {
                background: rgba(255,255,255,0.2);
            }
            
            .modern-user-menu {
                display: flex;
                align-items: center;
                gap: 0.75rem;
                color: white;
            }
            
            .navbar-badge, .user-badge {
                background: rgba(255,255,255,0.2);
                padding: 0.25rem 0.75rem;
                border-radius: 20px;
                font-size: 0.8rem;
                font-weight: 600;
                border: 1px solid rgba(255,255,255,0.3);
            }

            /* Ensure content doesn't hide behind navbar */
            .main-content {
                margin-top: 90px;
                padding: 1.5rem;
                min-height: calc(100vh - 90px);
            }
        </style>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const navbar = document.getElementById('modernNavbar');
                if (navbar) {
                    console.log('✅ Modern Navbar loaded successfully');
                }
            });
        </script>
        <?php
    }
    
    private function renderFallbackNavbar() {
        ?>
        <nav style="background: linear-gradient(135deg, #1E88E5, #A1C349); padding: 1rem 2rem; color: white; position: fixed; top: 0; left: 0; right: 0; z-index: 1000; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
            <div style="font-weight: bold; font-size: 1.5rem;">
                🐄 EasyCalf
            </div>
            <div style="display: flex; gap: 1rem; align-items: center;">
                <a href="/public/" style="color: white; text-decoration: none; padding: 0.5rem 1rem; border-radius: 8px;">Dashboard</a>
                <a href="/public/calves" style="color: white; text-decoration: none; padding: 0.5rem 1rem; border-radius: 8px;">Calves</a>
                <span style="color: white;">Public Viewer</span>
            </div>
        </nav>
        <div style="margin-top: 80px;"></div>
        <?php
    }
}
?>