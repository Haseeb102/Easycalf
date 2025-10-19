<?php
class TasksController {
    private $db;
    private $auth;

    public function __construct() {
        $this->db = new Database();
        $this->auth = new Auth();
    }

    public function list() {
        if (!$this->auth->isLoggedIn()) {
            header('Location: /public/login');
            exit;
        }

        require_once BASE_PATH . '/app/modules/Tasks/model.php';
        require_once BASE_PATH . '/app/modules/Tasks/helpers.php';
        
        $model = new TasksModel();
        $tasks = $model->getTasksSummary();
        
        // This was the bug - it was including controller.php instead of the view
        require_once BASE_PATH . '/app/core/ModernNavbar.php';
        $navbar = new ModernNavbar();
        
        $totalDue = 0;
        $totalUpcoming = 0;
        foreach ($tasks as $task) {
            $totalDue += $task['due_count'];
            $totalUpcoming += $task['upcoming_count'];
        }
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Task Management - EasyCalf</title>
            <link rel="stylesheet" href="/public/assets/css/style.css">
            <style>
                .tasks-container { max-width: 1200px; margin: 0 auto; padding: 2rem; padding-top: 100px; }
                .task-summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
                .summary-card { background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); text-align: center; transition: transform 0.3s ease; }
                .summary-card:hover { transform: translateY(-5px); }
                .summary-value { font-size: 3rem; font-weight: 700; margin-bottom: 0.5rem; }
                .summary-label { color: var(--text-secondary); font-weight: 600; }
                .task-list { background: white; border-radius: 12px; padding: 2rem; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
                .task-item { display: flex; justify-content: space-between; align-items: center; padding: 1.5rem; border-bottom: 1px solid #eee; transition: background 0.2s ease; }
                .task-item:hover { background: rgba(234, 246, 255, 0.5); }
                .task-info { flex: 1; }
                .task-name { font-size: 1.2rem; font-weight: 700; margin-bottom: 0.5rem; }
                .task-counts { display: flex; gap: 1rem; }
                .info-banner { background: #d1ecf1; color: #0c5460; padding: 1rem; border-radius: 8px; margin-bottom: 2rem; border-left: 4px solid #17a2b8; }
            </style>
        </head>
        <body>
            <?php $navbar->render('tasks'); ?>
            <div class="tasks-container">
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div style="background: #d4edda; color: #155724; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; border-left: 4px solid #28a745;">
                        ✅ <?php echo htmlspecialchars($_SESSION['success_message']); ?>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error_message'])): ?>
                    <div style="background: #f8d7da; color: #721c24; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; border-left: 4px solid #dc3545;">
                        ⚠️ <?php echo htmlspecialchars($_SESSION['error_message']); ?>
                    </div>
                    <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>

                <div class="info-banner">
                    <strong>ℹ️ Note:</strong> Tasks for sold, deceased, or deleted calves are automatically hidden from this view.
                    View individual calf passports to see their complete task history.
                </div>

                <h1 style="text-align: center; margin-bottom: 2rem;">📋 Task Management</h1>

                <div class="task-summary">
                    <div class="summary-card">
                        <div class="summary-value" style="color: #E53935;"><?php echo $totalDue; ?></div>
                        <div class="summary-label">Tasks Due</div>
                        <a href="/public/tasks/all-due" class="btn btn-primary" style="margin-top: 1rem;">View All</a>
                    </div>
                    
                    <div class="summary-card">
                        <div class="summary-value" style="color: #1976D2;"><?php echo $totalUpcoming; ?></div>
                        <div class="summary-label">Upcoming Tasks</div>
                        <a href="/public/tasks/all-upcoming" class="btn btn-primary" style="margin-top: 1rem;">View All</a>
                    </div>

                    <div class="summary-card">
                        <div class="summary-value" style="color: #43A047;"><?php echo count($tasks); ?></div>
                        <div class="summary-label">Task Types</div>
                        <a href="/public/tasks/calendar" class="btn btn-primary" style="margin-top: 1rem;">Calendar</a>
                    </div>
                </div>

                <?php if ($totalDue > 0): ?>
                    <div style="text-align: center; margin-bottom: 2rem;">
                        <a href="/public/tasks/complete-all-due" class="btn btn-primary" style="background: #28a745; font-size: 1.1rem; padding: 1rem 2rem;">
                            ✅ Complete All Due Tasks (<?php echo $totalDue; ?>)
                        </a>
                    </div>
                <?php endif; ?>

                <div class="task-list">
                    <h2 style="margin-bottom: 1.5rem;">Tasks by Type</h2>
                    
                    <?php if (empty($tasks)): ?>
                        <div style="text-align: center; padding: 3rem;">
                            <div style="font-size: 3rem; margin-bottom: 1rem;">✅</div>
                            <h3>All Tasks Complete!</h3>
                            <p>Great job! No pending tasks at this time.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($tasks as $task): ?>
                            <div class="task-item">
                                <div class="task-info">
                                    <div class="task-name"><?php echo htmlspecialchars($task['event_name']); ?></div>
                                    <div style="color: var(--text-secondary); font-size: 0.9rem;">
                                        <?php echo ucfirst($task['event_type']); ?> • 
                                        Earliest: <?php echo date('M j, Y', strtotime($task['earliest_due'])); ?>
                                    </div>
                                </div>
                                
                                <div class="task-counts">
                                    <?php if ($task['due_count'] > 0): ?>
                                        <?php echo renderTaskCountBadge($task['due_count'], 'due'); ?>
                                    <?php endif; ?>
                                    
                                    <?php if ($task['upcoming_count'] > 0): ?>
                                        <?php echo renderTaskCountBadge($task['upcoming_count'], 'upcoming'); ?>
                                    <?php endif; ?>
                                </div>
                                
                                <a href="/public/tasks/details?task=<?php echo urlencode($task['event_name']); ?>&type=due" 
                                   class="btn btn-primary" style="margin-left: 1rem;">View Details</a>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </body>
        </html>
        <?php
    }

    public function listAllDue() {
        if (!$this->auth->isLoggedIn()) {
            header('Location: /public/login');
            exit;
        }

        require_once BASE_PATH . '/app/modules/Tasks/model.php';
        require_once BASE_PATH . '/app/modules/Tasks/helpers.php';
        
        $model = new TasksModel();
        $tasks = $model->getAllDueTasks();
        
        include BASE_PATH . '/app/modules/Tasks/due_tasks.php';
    }

    public function listAllUpcoming() {
        if (!$this->auth->isLoggedIn()) {
            header('Location: /public/login');
            exit;
        }

        require_once BASE_PATH . '/app/modules/Tasks/model.php';
        require_once BASE_PATH . '/app/modules/Tasks/helpers.php';
        
        $model = new TasksModel();
        $tasks = $model->getAllUpcomingTasks();
        
        include BASE_PATH . '/app/modules/Tasks/upcoming_tasks.php';
    }

    public function calendar() {
        if (!$this->auth->isLoggedIn()) {
            header('Location: /public/login');
            exit;
        }

        $month = isset($_GET['month']) ? $_GET['month'] : date('m');
        $year = isset($_GET['year']) ? $_GET['year'] : date('Y');
        
        $startDate = "$year-$month-01";
        $endDate = date('Y-m-t', strtotime($startDate));
        
        require_once BASE_PATH . '/app/modules/Tasks/model.php';
        require_once BASE_PATH . '/app/modules/Tasks/helpers.php';
        
        $model = new TasksModel();
        $tasks = $model->getTasksForMonth($startDate, $endDate);
        
        include BASE_PATH . '/app/modules/Tasks/calendar.php';
    }

    public function details() {
        if (!$this->auth->isLoggedIn()) {
            header('Location: /public/login');
            exit;
        }

        $taskName = isset($_GET['task']) ? $_GET['task'] : '';
        $type = isset($_GET['type']) ? $_GET['type'] : 'due';
        
        if (empty($taskName)) {
            header('Location: /public/tasks');
            exit;
        }

        require_once BASE_PATH . '/app/modules/Tasks/model.php';
        require_once BASE_PATH . '/app/modules/Tasks/helpers.php';
        
        $model = new TasksModel();
        $tasks = $model->getTaskDetailsByEvent($taskName, $type);
        
        include BASE_PATH . '/app/modules/Tasks/views/task_details.php';
    }

    public function complete() {
        if (!$this->auth->isLoggedIn()) {
            header('Location: /public/login');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['task_id'])) {
            try {
                $taskId = intval($_POST['task_id']);
                $notes = isset($_POST['notes']) ? $_POST['notes'] : null;
                
                require_once BASE_PATH . '/app/modules/Tasks/model.php';
                $model = new TasksModel();
                $model->completeTask($taskId, $_SESSION['user_id'], $notes);
                
                $_SESSION['success_message'] = "✅ Task completed successfully!";
            } catch (Exception $e) {
                $_SESSION['error_message'] = "Error completing task: " . $e->getMessage();
            }
        }

        $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/public/tasks';
        header('Location: ' . $referer);
        exit;
    }

    public function completeBulk() {
        if (!$this->auth->isLoggedIn()) {
            header('Location: /public/login');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['task_ids'])) {
            try {
                $taskIds = $_POST['task_ids'];
                
                if (empty($taskIds) || !is_array($taskIds)) {
                    throw new Exception("No tasks selected");
                }

                require_once BASE_PATH . '/app/modules/Tasks/model.php';
                $model = new TasksModel();
                
                $completed = 0;
                foreach ($taskIds as $taskId) {
                    $model->completeTask(intval($taskId), $_SESSION['user_id']);
                    $completed++;
                }
                
                $_SESSION['success_message'] = "✅ $completed tasks completed successfully!";
            } catch (Exception $e) {
                $_SESSION['error_message'] = "Error: " . $e->getMessage();
            }
        }

        header('Location: /public/tasks');
        exit;
    }

    public function completeCalf() {
        if (!$this->auth->isLoggedIn()) {
            header('Location: /public/login');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['calf_id'])) {
            try {
                $calfId = intval($_POST['calf_id']);
                
                $result = $this->db->query("
                    UPDATE calf_events 
                    SET status = 'completed', 
                        completed_date = NOW(),
                        completed_by = ?
                    WHERE calf_id = ? 
                    AND status = 'pending'
                    AND due_date <= CURDATE()
                ", array($_SESSION['user_id'], $calfId));
                
                $count = $result->rowCount();
                $_SESSION['success_message'] = "✅ $count tasks completed for calf!";
            } catch (Exception $e) {
                $_SESSION['error_message'] = "Error: " . $e->getMessage();
            }
        }

        $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/public/tasks';
        header('Location: ' . $referer);
        exit;
    }

    public function completeAllDue() {
        if (!$this->auth->isLoggedIn()) {
            header('Location: /public/login');
            exit;
        }

        if (!isset($_GET['confirm'])) {
            require_once BASE_PATH . '/app/modules/Tasks/model.php';
            $model = new TasksModel();
            $dueCount = $model->getDueTaskCount();
            
            include BASE_PATH . '/app/modules/Tasks/confirm_complete_all.php';
            exit;
        }

        try {
            require_once BASE_PATH . '/app/modules/Tasks/model.php';
            $model = new TasksModel();
            $result = $model->completeAllDueTasks($_SESSION['user_id']);
            
            $count = $result->rowCount();
            $_SESSION['success_message'] = "✅ $count tasks completed successfully!";
        } catch (Exception $e) {
            $_SESSION['error_message'] = "Error: " . $e->getMessage();
        }

        header('Location: /public/tasks');
        exit;
    }
}
?>