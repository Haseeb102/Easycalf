<?php
/**
 * Dashboard Controller - FIXED VERSION
 * FIX: All queries now exclude deleted calves from counts and alerts
 */
class DashboardController {
    private $db;
    private $auth;

    public function __construct() {
        $this->db = new Database();
        $this->auth = new Auth();
    }

    public function home() {
        if (!$this->auth->isLoggedIn()) {
            header('Location: /public/login');
            exit;
        }

        $operationsData = $this->getOperationsData();
        $this->renderModernDashboard($operationsData);
    }

    private function getOperationsData() {
        try {
            // FIX: All queries now explicitly exclude deleted calves
            return $this->db->fetch("
                SELECT 
                    (SELECT COUNT(*) FROM calves WHERE status = 'active') as total_calves,
                    (SELECT COUNT(*) FROM batches WHERE is_active = 1) as total_batches,
                    (SELECT COUNT(*) FROM calves WHERE health_status = 'sick' AND status = 'active') as sick_calves,
                    (SELECT COUNT(*) FROM calves WHERE health_status = 'needs_attention' AND status = 'active') as attention_calves,
                    (SELECT COUNT(*) FROM calf_events ce 
                     JOIN calves c ON ce.calf_id = c.id 
                     WHERE ce.status = 'pending' 
                     AND ce.due_date <= CURDATE() 
                     AND c.status = 'active') as urgent_tasks,
                    (SELECT COUNT(*) FROM calf_events ce 
                     JOIN calves c ON ce.calf_id = c.id 
                     WHERE ce.status = 'pending' 
                     AND ce.due_date = CURDATE() 
                     AND c.status = 'active') as due_today,
                    (SELECT COUNT(*) FROM calves WHERE status = 'active') as feeding_calves
            ");
        } catch (Exception $e) {
            error_log("Operations data error: " . $e->getMessage());
            return [
                'total_calves' => 0, 
                'total_batches' => 0, 
                'sick_calves' => 0,
                'attention_calves' => 0, 
                'urgent_tasks' => 0, 
                'due_today' => 0,
                'feeding_calves' => 0
            ];
        }
    }

    private function getRecentAlerts() {
        try {
            // FIX: Only get alerts for active calves
            return $this->db->fetchAll("
                SELECT 
                    c.calf_id,
                    c.health_status,
                    e.name as event_name,
                    ce.due_date
                FROM calves c
                LEFT JOIN calf_events ce ON c.id = ce.calf_id AND ce.status = 'pending'
                LEFT JOIN events e ON ce.event_id = e.id
                WHERE c.status = 'active'
                AND (c.health_status IN ('sick', 'needs_attention') OR ce.due_date <= CURDATE())
                ORDER BY 
                    CASE 
                        WHEN c.health_status = 'sick' THEN 1
                        WHEN ce.due_date < CURDATE() THEN 2
                        WHEN c.health_status = 'needs_attention' THEN 3
                        ELSE 4
                    END,
                    ce.due_date ASC
                LIMIT 6
            ");
        } catch (Exception $e) {
            error_log("Recent alerts error: " . $e->getMessage());
            return [];
        }
    }

    private function renderModernDashboard($data) {
        $user = $this->auth->getUser();
        $recentAlerts = $this->getRecentAlerts();
        
        require_once BASE_PATH . '/app/core/ModernNavbar.php';
        $navbar = new ModernNavbar();
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Dashboard - EasyCalf</title>
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .dashboard-header {
            text-align: center;
            margin-bottom: 3rem;
            padding: 2rem 0;
        }
        
        .dashboard-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            letter-spacing: -0.5px;
        }
        
        .dashboard-subtitle {
            color: var(--text-secondary);
            font-size: 1.1rem;
            font-weight: 400;
        }
        
        .stats-highlight {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .highlight-card {
            background: var(--accent-white);
            padding: 1.5rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            border-left: 4px solid var(--primary-blue);
            transition: all 0.3s ease;
        }
        
        .highlight-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .highlight-card.warning {
            border-left-color: var(--highlight-yellow);
        }
        
        .highlight-card.danger {
            border-left-color: var(--danger-red);
        }
        
        .highlight-card.success {
            border-left-color: var(--success-green);
        }
    </style>
</head>
<body>
    <?php $navbar->render('dashboard'); ?>
    
    <div class="main-content">
        <div class="container">
            <div class="fade-in">
                <!-- Header Section -->
                <div class="dashboard-header">
                    <h1 class="dashboard-title">🚜 Farm Operations Hub</h1>
                    <p class="dashboard-subtitle">Real-time farm management and monitoring dashboard</p>
                </div>
                
                <!-- Stats Overview -->
                <div class="dashboard-grid">
                    <div class="stat-card">
                        <div class="icon-circle icon-circle-primary">
                            🐄
                        </div>
                        <div class="stat-number"><?= $data['total_calves'] ?></div>
                        <div class="stat-label">Active Calves</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="icon-circle icon-circle-success">
                            🏠
                        </div>
                        <div class="stat-number"><?= $data['total_batches'] ?></div>
                        <div class="stat-label">Active Batches</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="icon-circle icon-circle-warning">
                            ⚠️
                        </div>
                        <div class="stat-number"><?= $data['sick_calves'] + $data['attention_calves'] ?></div>
                        <div class="stat-label">Need Attention</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="icon-circle icon-circle-danger">
                            📋
                        </div>
                        <div class="stat-number"><?= $data['urgent_tasks'] ?></div>
                        <div class="stat-label">Due Tasks</div>
                    </div>
                </div>
                
                <!-- Quick Highlights -->
                <div class="stats-highlight">
                    <div class="highlight-card">
                        <h3 style="margin-bottom: 0.5rem; color: var(--text-primary);">📈 Today's Overview</h3>
                        <p style="color: var(--text-secondary); margin: 0;">
                            <?= $data['due_today'] ?> tasks due today • 
                            <?= $data['feeding_calves'] ?> active calves to feed
                        </p>
                    </div>
                    
                    <?php if ($data['sick_calves'] > 0): ?>
                    <div class="highlight-card danger">
                        <h3 style="margin-bottom: 0.5rem; color: var(--danger-red);">🚨 Health Alert</h3>
                        <p style="color: var(--text-secondary); margin: 0;">
                            <?= $data['sick_calves'] ?> calves need immediate attention
                        </p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($data['attention_calves'] > 0): ?>
                    <div class="highlight-card warning">
                        <h3 style="margin-bottom: 0.5rem; color: var(--highlight-yellow);">👀 Monitoring</h3>
                        <p style="color: var(--text-secondary); margin: 0;">
                            <?= $data['attention_calves'] ?> calves require monitoring
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Operations Grid -->
                <div class="operations-grid">
                    <!-- Quick Actions -->
                    <div class="operation-card">
                        <div class="operation-header">
                            <div class="operation-title">
                                <span>⚡</span>
                                Quick Actions
                            </div>
                        </div>
                        <div class="quick-stats-grid">
                            <div class="quick-stat">
                                <span class="quick-value">+</span>
                                <span class="quick-label">Add Calf</span>
                            </div>
                            <div class="quick-stat">
                                <span class="quick-value">📤</span>
                                <span class="quick-label">Bulk Import</span>
                            </div>
                            <div class="quick-stat">
                                <span class="quick-value">📊</span>
                                <span class="quick-label">Reports</span>
                            </div>
                        </div>
                        <div style="display: flex; gap: 0.5rem; margin-top: 1rem;">
                            <a href="/public/calves/add" class="btn btn-primary" style="flex: 1;">Add New Calf</a>
                            <a href="/public/calves/import" class="btn btn-secondary" style="flex: 1;">Bulk Import</a>
                        </div>
                    </div>
                    
                    <!-- Health Center -->
                    <div class="operation-card">
                        <div class="operation-header">
                            <div class="operation-title">
                                <span>❤️</span>
                                Health Center
                            </div>
                            <a href="/public/calves" class="btn btn-outline" style="padding: 0.5rem 1rem; font-size: 0.8rem;">View All</a>
                        </div>
                        <?php if ($data['sick_calves'] > 0): ?>
                            <div class="alert-item urgent">
                                <div class="alert-icon">🤒</div>
                                <div class="alert-content">
                                    <div class="alert-title">Sick Calves</div>
                                    <div class="alert-desc"><?= $data['sick_calves'] ?> need immediate attention</div>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php if ($data['attention_calves'] > 0): ?>
                            <div class="alert-item warning">
                                <div class="alert-icon">⚠️</div>
                                <div class="alert-content">
                                    <div class="alert-title">Needs Attention</div>
                                    <div class="alert-desc"><?= $data['attention_calves'] ?> require monitoring</div>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php if ($data['sick_calves'] == 0 && $data['attention_calves'] == 0): ?>
                            <div style="background: rgba(67, 160, 71, 0.1); color: var(--success-green); padding: 1.5rem; border-radius: 8px; text-align: center; border: 1px solid rgba(67, 160, 71, 0.2);">
                                <strong>💚 All Systems Normal</strong><br>
                                <span style="font-size: 0.9rem;">All active calves are healthy and thriving</span>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Recent Alerts -->
                    <div class="operation-card">
                        <div class="operation-header">
                            <div class="operation-title">
                                <span>🔔</span>
                                Recent Alerts
                            </div>
                            <a href="/public/tasks" class="btn btn-outline" style="padding: 0.5rem 1rem; font-size: 0.8rem;">View All</a>
                        </div>
                        <?php if (!empty($recentAlerts)): ?>
                            <div style="max-height: 300px; overflow-y: auto;">
                                <?php foreach ($recentAlerts as $alert): ?>
                                    <div class="alert-item <?= $alert['health_status'] === 'sick' ? 'urgent' : 'warning' ?>">
                                        <div class="alert-icon"><?= $alert['health_status'] === 'sick' ? '🤒' : '⚠️' ?></div>
                                        <div class="alert-content">
                                            <div class="alert-title"><?= htmlspecialchars($alert['calf_id']) ?></div>
                                            <div class="alert-desc">
                                                <?= $alert['event_name'] ? htmlspecialchars($alert['event_name']) : ucfirst($alert['health_status']) ?>
                                                <?= $alert['due_date'] ? ' - Due: ' . $alert['due_date'] : '' ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div style="text-align: center; padding: 2rem; color: var(--text-secondary);">
                                <div style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;">💚</div>
                                <p>No recent alerts</p>
                                <small style="opacity: 0.7;">Everything is running smoothly</small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Footer -->
                <div class="footer">
                    <p>© 2025 EasyCalf | Developed by Barrington Dairy | Private Farm Use Only</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.operation-card, .stat-card, .highlight-card');
            cards.forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
                card.classList.add('fade-in');
            });
            
            document.querySelectorAll('.btn').forEach(btn => {
                btn.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-2px)';
                });
                btn.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
            
            console.log('🚜 Modern Dashboard Loaded - Showing active calves only!');
        });
    </script>
</body>
</html>
        <?php
    }
}
?>