<?php
class EventsController {
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

        $events = $this->db->fetchAll("
            SELECT e.*, 
                   u.name as created_by_name,
                   COUNT(ce.id) as scheduled_tasks
            FROM events e
            LEFT JOIN users u ON e.created_by = u.id
            LEFT JOIN calf_events ce ON e.id = ce.event_id AND ce.status = 'pending'
            GROUP BY e.id
            ORDER BY e.age_start ASC, e.name ASC
        ");

        $this->renderEventsList($events);
    }

    public function add() {
        if (!$this->auth->isLoggedIn()) {
            header('Location: /public/login');
            exit;
        }

        $success = false;
        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_event'])) {
            try {
                $name = trim($_POST['name']);
                $type = $_POST['type'];
                $ageStart = intval($_POST['age_start']);
                $ageEnd = intval($_POST['age_end']);
                $preferredDay = $_POST['preferred_day'] ?: null;
                $reminderDays = intval($_POST['reminder_days']);

                if (empty($name) || $ageStart < 0 || $ageEnd < $ageStart) {
                    throw new Exception("Invalid event parameters. Age end must be greater than or equal to age start.");
                }

                $this->db->query("
                    INSERT INTO events (name, type, age_start, age_end, preferred_day, reminder_days, is_active, created_by, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, 1, ?, NOW())",
                    [$name, $type, $ageStart, $ageEnd, $preferredDay, $reminderDays, $_SESSION['user_id']]
                );

                $_SESSION['success_message'] = "✅ Event template created successfully!";
                header('Location: /public/events');
                exit;

            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }

        $this->renderAddEventForm($success, $error);
    }

    public function update() {
        if (!$this->auth->isLoggedIn()) {
            header('Location: /public/login');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_event'])) {
            try {
                $eventId = intval($_POST['event_id']);
                $name = trim($_POST['name']);
                $type = $_POST['type'];
                $ageStart = intval($_POST['age_start']);
                $ageEnd = intval($_POST['age_end']);
                $preferredDay = $_POST['preferred_day'] ?: null;
                $reminderDays = intval($_POST['reminder_days']);

                if (empty($name) || $ageStart < 0 || $ageEnd < $ageStart) {
                    throw new Exception("Invalid event parameters");
                }

                $this->db->query("
                    UPDATE events 
                    SET name = ?, type = ?, age_start = ?, age_end = ?, 
                        preferred_day = ?, reminder_days = ?
                    WHERE id = ?",
                    [$name, $type, $ageStart, $ageEnd, $preferredDay, $reminderDays, $eventId]
                );

                $_SESSION['success_message'] = "✅ Event template updated successfully!";

            } catch (Exception $e) {
                $_SESSION['error_message'] = "Error: " . $e->getMessage();
            }
        }

        header('Location: /public/events');
        exit;
    }

    public function delete() {
        if (!$this->auth->isLoggedIn()) {
            header('Location: /public/login');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['event_id'])) {
            try {
                $eventId = intval($_POST['event_id']);
                
                // Check if event has scheduled tasks
                $scheduledTasks = $this->db->fetch(
                    "SELECT COUNT(*) as count FROM calf_events WHERE event_id = ? AND status = 'pending'",
                    [$eventId]
                );

                if ($scheduledTasks['count'] > 0) {
                    throw new Exception("Cannot delete event with " . $scheduledTasks['count'] . " pending tasks. Complete or cancel tasks first.");
                }

                $this->db->query("DELETE FROM events WHERE id = ?", [$eventId]);
                $_SESSION['success_message'] = "✅ Event template deleted successfully!";

            } catch (Exception $e) {
                $_SESSION['error_message'] = "Error: " . $e->getMessage();
            }
        }

        header('Location: /public/events');
        exit;
    }

    public function toggle() {
        if (!$this->auth->isLoggedIn()) {
            header('Location: /public/login');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['event_id'])) {
            try {
                $eventId = intval($_POST['event_id']);
                $newStatus = intval($_POST['new_status']);

                $this->db->query("UPDATE events SET is_active = ? WHERE id = ?", [$newStatus, $eventId]);
                
                $statusText = $newStatus ? 'activated' : 'deactivated';
                $_SESSION['success_message'] = "✅ Event template $statusText successfully!";

            } catch (Exception $e) {
                $_SESSION['error_message'] = "Error: " . $e->getMessage();
            }
        }

        header('Location: /public/events');
        exit;
    }

    public function viewCalves($id = null) {
        if (!$this->auth->isLoggedIn()) {
            header('Location: /public/login');
            exit;
        }

        if (!$id) {
            header('Location: /public/events');
            exit;
        }

        $event = $this->db->fetch("SELECT * FROM events WHERE id = ? AND is_active = 1", [$id]);

        if (!$event) {
            $_SESSION['error_message'] = "Event not found";
            header('Location: /public/events');
            exit;
        }

        $calves = $this->db->fetchAll("
            SELECT 
                ce.id as task_id,
                ce.due_date,
                ce.status,
                ce.completed_date,
                c.id as calf_id,
                c.calf_id as calf_identifier,
                c.birth_date,
                DATEDIFF(NOW(), c.birth_date) as age_days,
                c.health_status,
                b.name as batch_name,
                u.name as completed_by_name
            FROM calf_events ce
            JOIN calves c ON ce.calf_id = c.id
            LEFT JOIN batches b ON c.batch_id = b.id
            LEFT JOIN users u ON ce.completed_by = u.id
            WHERE ce.event_id = ? AND c.status = 'active'
            ORDER BY ce.due_date ASC, c.calf_id ASC
        ", [$id]);

        $this->renderEventCalves($event, $calves);
    }

    private function renderEventsList($events) {
        require_once BASE_PATH . '/app/core/ModernNavbar.php';
        $navbar = new ModernNavbar();
        
        $successMessage = $_SESSION['success_message'] ?? null;
        $errorMessage = $_SESSION['error_message'] ?? null;
        unset($_SESSION['success_message'], $_SESSION['error_message']);
        ?>
<!DOCTYPE html>
<html>
<head>
    <title>Event Templates - EasyCalf</title>
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <style>
        .events-container { max-width: 1400px; margin: 0 auto; padding: 2rem; padding-top: 100px; }
        .events-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        .event-card { background: white; padding: 1.5rem; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); margin-bottom: 1rem; transition: all 0.3s ease; }
        .event-card:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,0.15); }
        .event-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
        .event-name { font-size: 1.3rem; font-weight: 700; color: var(--text-dark); }
        .event-type-badge { padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.8rem; font-weight: 600; }
        .type-vaccination { background: #E3F2FD; color: #1976D2; }
        .type-treatment { background: #F3E5F5; color: #7B1FA2; }
        .type-management { background: #E8F5E9; color: #388E3C; }
        .type-health { background: #FFF3E0; color: #F57C00; }
        .event-details { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin: 1rem 0; }
        .detail-item { background: var(--light-accent); padding: 0.75rem; border-radius: 8px; }
        .detail-label { font-size: 0.8rem; color: var(--text-secondary); font-weight: 600; text-transform: uppercase; }
        .detail-value { font-size: 1.1rem; font-weight: 700; color: var(--text-dark); margin-top: 0.25rem; }
        .event-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .btn-sm { padding: 0.5rem 1rem; font-size: 0.8rem; }
        .status-active { color: #28a745; font-weight: 600; }
        .status-inactive { color: #6c757d; font-weight: 600; }
        .edit-modal { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center; }
        .edit-modal.show { display: flex; }
        .modal-content { background: white; padding: 2rem; border-radius: 12px; max-width: 600px; width: 90%; max-height: 90vh; overflow-y: auto; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        @media (max-width: 768px) { .events-container { padding: 1rem; padding-top: 80px; } .form-row { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <?php $navbar->render('events'); ?>
    
    <div class="events-container">
        <div class="events-header">
            <div>
                <h1>📋 Event Templates</h1>
                <p style="color: var(--text-secondary);">Manage scheduled events for all calves</p>
            </div>
            <a href="#" onclick="showAddModal(); return false;" class="btn btn-primary">+ Add New Event</a>
        </div>

        <?php if ($successMessage): ?>
            <div style="background: #d4edda; color: #155724; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
                <?php echo htmlspecialchars($successMessage); ?>
            </div>
        <?php endif; ?>

        <?php if ($errorMessage): ?>
            <div style="background: #f8d7da; color: #721c24; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
                <?php echo htmlspecialchars($errorMessage); ?>
            </div>
        <?php endif; ?>

        <?php if (empty($events)): ?>
            <div style="text-align: center; padding: 3rem; background: white; border-radius: 12px;">
                <div style="font-size: 3rem; margin-bottom: 1rem;">📋</div>
                <h3>No Event Templates</h3>
                <p>Create your first event template to schedule tasks for calves</p>
                <a href="#" onclick="showAddModal(); return false;" class="btn btn-primary" style="margin-top: 1rem;">Create First Event</a>
            </div>
        <?php else: ?>
            <?php foreach ($events as $event): ?>
            <div class="event-card">
                <div class="event-header">
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <span class="event-name"><?php echo htmlspecialchars($event['name']); ?></span>
                        <span class="event-type-badge type-<?php echo $event['type']; ?>">
                            <?php echo ucfirst($event['type']); ?>
                        </span>
                        <?php if ($event['is_active']): ?>
                            <span class="status-active">✓ Active</span>
                        <?php else: ?>
                            <span class="status-inactive">○ Inactive</span>
                        <?php endif; ?>
                    </div>
                    <div style="font-size: 0.9rem; color: var(--text-secondary);">
                        <?php echo $event['scheduled_tasks']; ?> pending tasks
                    </div>
                </div>

                <div class="event-details">
                    <div class="detail-item">
                        <div class="detail-label">Age Start</div>
                        <div class="detail-value"><?php echo $event['age_start']; ?> days</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Age End</div>
                        <div class="detail-value"><?php echo $event['age_end']; ?> days</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Preferred Day</div>
                        <div class="detail-value"><?php echo $event['preferred_day'] ? ucfirst($event['preferred_day']) : 'Any'; ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Reminder</div>
                        <div class="detail-value"><?php echo $event['reminder_days']; ?> days before</div>
                    </div>
                </div>

                <div class="event-actions">
                    <button onclick='editEvent(<?php echo json_encode($event); ?>)' class="btn btn-primary btn-sm">
                        ✏️ Edit
                    </button>
                    <a href="/public/events/calves?id=<?php echo $event['id']; ?>" class="btn btn-secondary btn-sm">
                        👥 View Calves
                    </a>
                    <form method="post" action="/public/events/toggle" style="display: inline;">
                        <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                        <input type="hidden" name="new_status" value="<?php echo $event['is_active'] ? 0 : 1; ?>">
                        <button type="submit" class="btn btn-secondary btn-sm">
                            <?php echo $event['is_active'] ? '⏸️ Deactivate' : '▶️ Activate'; ?>
                        </button>
                    </form>
                    <?php if ($event['scheduled_tasks'] == 0): ?>
                    <form method="post" action="/public/events/delete" style="display: inline;">
                        <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                        <button type="submit" class="btn btn-secondary btn-sm" 
                                onclick="return confirm('Delete this event template?')" 
                                style="background: #dc3545;">
                            🗑️ Delete
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="edit-modal">
        <div class="modal-content">
            <h2 id="modalTitle">Edit Event Template</h2>
            <form method="post" action="/public/events/update" id="editForm">
                <input type="hidden" name="update_event" value="1">
                <input type="hidden" name="event_id" id="edit_event_id">

                <div class="form-group">
                    <label class="form-label">Event Name *</label>
                    <input type="text" name="name" id="edit_name" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Event Type *</label>
                    <select name="type" id="edit_type" class="form-control" required>
                        <option value="vaccination">Vaccination</option>
                        <option value="treatment">Treatment</option>
                        <option value="management">Management</option>
                        <option value="health">Health Check</option>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Age Start (days) *</label>
                        <input type="number" name="age_start" id="edit_age_start" class="form-control" min="0" max="365" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Age End (days) *</label>
                        <input type="number" name="age_end" id="edit_age_end" class="form-control" min="0" max="365" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Preferred Day of Week</label>
                    <select name="preferred_day" id="edit_preferred_day" class="form-control">
                        <option value="">Any Day</option>
                        <option value="monday">Monday</option>
                        <option value="tuesday">Tuesday</option>
                        <option value="wednesday">Wednesday</option>
                        <option value="thursday">Thursday</option>
                        <option value="friday">Friday</option>
                        <option value="saturday">Saturday</option>
                        <option value="sunday">Sunday</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Reminder (days before)</label>
                    <input type="number" name="reminder_days" id="edit_reminder_days" class="form-control" min="0" max="30" value="0">
                </div>

                <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">Save Changes</button>
                    <button type="button" onclick="closeModal()" class="btn btn-secondary" style="flex: 1;">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function editEvent(event) {
            document.getElementById('modalTitle').textContent = 'Edit Event Template';
            document.getElementById('editForm').action = '/public/events/update';
            document.getElementById('edit_event_id').value = event.id;
            document.getElementById('edit_name').value = event.name;
            document.getElementById('edit_type').value = event.type;
            document.getElementById('edit_age_start').value = event.age_start;
            document.getElementById('edit_age_end').value = event.age_end;
            document.getElementById('edit_preferred_day').value = event.preferred_day || '';
            document.getElementById('edit_reminder_days').value = event.reminder_days;
            document.getElementById('editModal').classList.add('show');
        }

        function showAddModal() {
            document.getElementById('modalTitle').textContent = 'Add New Event Template';
            document.getElementById('editForm').action = '/public/events/add';
            document.getElementById('editForm').reset();
            document.querySelector('[name="update_event"]').name = 'add_event';
            document.getElementById('edit_event_id').removeAttribute('name');
            document.getElementById('editModal').classList.add('show');
        }

        function closeModal() {
            document.getElementById('editModal').classList.remove('show');
            document.querySelector('[name="add_event"]').name = 'update_event';
            document.getElementById('edit_event_id').setAttribute('name', 'event_id');
        }

        document.getElementById('editModal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });
    </script>
</body>
</html>
        <?php
    }

    private function renderAddEventForm($success, $error) {
        // This is handled by the modal in renderEventsList
    }

    private function renderEventCalves($event, $calves) {
        require_once BASE_PATH . '/app/core/ModernNavbar.php';
        $navbar = new ModernNavbar();
        
        $pending = array_filter($calves, fn($c) => $c['status'] === 'pending');
        $completed = array_filter($calves, fn($c) => $c['status'] === 'completed');
        ?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo htmlspecialchars($event['name']); ?> - Calves - EasyCalf</title>
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <style>
        .calves-container { max-width: 1400px; margin: 0 auto; padding: 2rem; padding-top: 100px; }
        .back-button { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1.5rem; background: var(--mid-blue); color: white; text-decoration: none; border-radius: 8px; margin-bottom: 2rem; }
        .event-info { background: linear-gradient(135deg, var(--mid-blue), var(--navy)); color: white; padding: 2rem; border-radius: 16px; margin-bottom: 2rem; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-top: 1rem; }
        .stat-box { background: rgba(255,255,255,0.2); padding: 1rem; border-radius: 8px; text-align: center; }
        .section { background: white; padding: 2rem; border-radius: 12px; margin-bottom: 2rem; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
    <?php $navbar->render('events'); ?>
    
    <div class="calves-container">
        <a href="/public/events" class="back-button">← Back to Events</a>

        <div class="event-info">
            <h1><?php echo htmlspecialchars($event['name']); ?></h1>
            <p><?php echo ucfirst($event['type']); ?> Event • Age Range: <?php echo $event['age_start']; ?>-<?php echo $event['age_end']; ?> days</p>
            
            <div class="stats-grid">
                <div class="stat-box">
                    <div style="font-size: 2rem; font-weight: 700;"><?php echo count($calves); ?></div>
                    <div style="font-size: 0.9rem;">Total Calves</div>
                </div>
                <div class="stat-box">
                    <div style="font-size: 2rem; font-weight: 700;"><?php echo count($pending); ?></div>
                    <div style="font-size: 0.9rem;">Pending</div>
                </div>
                <div class="stat-box">
                    <div style="font-size: 2rem; font-weight: 700;"><?php echo count($completed); ?></div>
                    <div style="font-size: 0.9rem;">Completed</div>
                </div>
                <div class="stat-box">
                    <div style="font-size: 2rem; font-weight: 700;"><?php echo count($calves) > 0 ? round((count($completed) / count($calves)) * 100) : 0; ?>%</div>
                    <div style="font-size: 0.9rem;">Completion</div>
                </div>
            </div>
        </div>

        <?php if (empty($pending)): ?>
            <div class="section" style="text-align: center;">
                <div style="font-size: 3rem; margin-bottom: 1rem;">✅</div>
                <h3>All Tasks Completed!</h3>
                <p>Great job! All calves have completed this event.</p>
            </div>
        <?php else: ?>
            <div class="section">
                <h2>⏳ Pending Tasks (<?php echo count($pending); ?>)</h2>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Calf ID</th>
                            <th>Age</th>
                            <th>Batch</th>
                            <th>Health</th>
                            <th>Due Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending as $calf): ?>
                        <tr>
                            <td><a href="/public/calves/passport/<?php echo $calf['calf_id']; ?>" style="color: var(--mid-blue); font-weight: 600;"><?php echo htmlspecialchars($calf['calf_identifier']); ?></a></td>
                            <td><?php echo $calf['age_days']; ?> days</td>
                            <td><?php echo htmlspecialchars($calf['batch_name'] ?? 'No Batch'); ?></td>
                            <td><span class="health-<?php echo $calf['health_status']; ?>"><?php echo ucfirst(str_replace('_', ' ', $calf['health_status'])); ?></span></td>
                            <td><?php echo date('M j, Y', strtotime($calf['due_date'])); ?></td>
                            <td>
                                <form method="post" action="/public/tasks/complete" style="display: inline;">
                                    <input type="hidden" name="task_id" value="<?php echo $calf['task_id']; ?>">
                                    <button type="submit" class="btn btn-primary btn-sm">✅ Complete</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
        <?php
    }
}
?>