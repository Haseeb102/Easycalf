<?php
class BatchesController {
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

        $batches = $this->db->fetchAll("
            SELECT b.*, 
                   COUNT(c.id) as current_count,
                   CASE 
                       WHEN COUNT(c.id) <= 5 THEN 'green'
                       WHEN COUNT(c.id) <= 9 THEN 'orange' 
                       ELSE 'red'
                   END as capacity_color
            FROM batches b
            LEFT JOIN calves c ON b.id = c.batch_id AND c.status = 'active'
            WHERE b.is_active = 1
            GROUP BY b.id
            ORDER BY b.created_at DESC
        ");

        $this->renderBatchList($batches);
    }

    public function add() {
        if (!$this->auth->isLoggedIn()) {
            header('Location: /public/login');
            exit;
        }

        $success = false;
        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_batch'])) {
            try {
                $name = trim($_POST['name']);
                $type = $_POST['type'];
                $capacity = intval($_POST['capacity']);
                $location = trim($_POST['location']);

                if (empty($name) || empty($type) || $capacity < 1) {
                    throw new Exception("Batch name, type, and valid capacity are required");
                }

                $this->db->query(
                    "INSERT INTO batches (name, type, capacity, location, created_by, created_at) 
                     VALUES (?, ?, ?, ?, ?, NOW())",
                    [$name, $type, $capacity, $location, $_SESSION['user_id']]
                );

                $success = true;

            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }

        $this->renderAddBatchForm($success, $error);
    }

    public function viewCalves($id = null) {
        if (!$this->auth->isLoggedIn()) {
            header('Location: /public/login');
            exit;
        }

        if (!$id) {
            header('Location: /public/batches');
            exit;
        }

        // Get batch details
        $batch = $this->db->fetch("
            SELECT b.*, 
                   COUNT(c.id) as current_count,
                   CASE 
                       WHEN COUNT(c.id) <= 5 THEN 'low'
                       WHEN COUNT(c.id) <= 9 THEN 'medium' 
                       ELSE 'high'
                   END as capacity_level
            FROM batches b
            LEFT JOIN calves c ON b.id = c.batch_id AND c.status = 'active'
            WHERE b.id = ? AND b.is_active = 1
            GROUP BY b.id",
            [$id]
        );

        if (!$batch) {
            $_SESSION['error_message'] = "Batch not found";
            header('Location: /public/batches');
            exit;
        }

        // Get calves in this batch
        $calves = $this->db->fetchAll("
            SELECT c.*, 
                   DATEDIFF(NOW(), c.birth_date) as age_days
            FROM calves c
            WHERE c.batch_id = ? AND c.status = 'active'
            ORDER BY c.birth_date DESC",
            [$id]
        );

        $this->renderBatchCalves($batch, $calves);
    }

    public function edit($id = null) {
        if (!$this->auth->isLoggedIn()) {
            header('Location: /public/login');
            exit;
        }

        if (!$id) {
            header('Location: /public/batches');
            exit;
        }

        $success = false;
        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_batch'])) {
            try {
                $name = trim($_POST['name']);
                $type = $_POST['type'];
                $capacity = intval($_POST['capacity']);
                $location = trim($_POST['location']);

                if (empty($name) || empty($type) || $capacity < 1) {
                    throw new Exception("Batch name, type, and valid capacity are required");
                }

                $this->db->query(
                    "UPDATE batches 
                     SET name = ?, type = ?, capacity = ?, location = ? 
                     WHERE id = ?",
                    [$name, $type, $capacity, $location, $id]
                );

                $success = true;
                $_SESSION['success_message'] = "Batch updated successfully!";
                header('Location: /public/batches');
                exit;

            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }

        // Get batch details
        $batch = $this->db->fetch("SELECT * FROM batches WHERE id = ? AND is_active = 1", [$id]);

        if (!$batch) {
            $_SESSION['error_message'] = "Batch not found";
            header('Location: /public/batches');
            exit;
        }

        $this->renderEditBatchForm($batch, $success, $error);
    }

    private function renderBatchList($batches) {
        require_once BASE_PATH . '/app/core/ModernNavbar.php';
        $navbar = new ModernNavbar();
        ?>
<!DOCTYPE html>
<html>
<head>
    <title>Batch Management - EasyCalf</title>
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <style>
        .batch-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
            padding-top: 100px;
        }

        .batch-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 2rem;
        }

        .batch-card {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            border-left: 4px solid;
        }

        .batch-card.capacity-low { border-left-color: #28a745; }
        .batch-card.capacity-medium { border-left-color: #ffc107; }
        .batch-card.capacity-high { border-left-color: #dc3545; }

        .batch-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
        }

        .batch-name {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
        }

        .batch-info {
            display: grid;
            gap: 0.5rem;
            margin: 1rem 0;
            font-size: 0.9rem;
            color: var(--text-secondary);
        }

        .capacity-bar {
            width: 100%;
            height: 8px;
            background: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
            margin: 1rem 0;
        }

        .capacity-fill {
            height: 100%;
            transition: width 0.3s ease;
        }

        .capacity-fill.low { background: linear-gradient(90deg, #28a745, #20c997); }
        .capacity-fill.medium { background: linear-gradient(90deg, #ffc107, #ff9800); }
        .capacity-fill.high { background: linear-gradient(90deg, #dc3545, #c82333); }

        .batch-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
        }

        @media (max-width: 768px) {
            .batch-container {
                padding: 1rem;
                padding-top: 80px;
            }
            
            .batch-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php $navbar->render('batches'); ?>
    
    <div class="batch-container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <h1>Batch Management</h1>
            <a href="/public/batches/add" class="btn btn-primary">Create New Batch</a>
        </div>

        <?php if (empty($batches)): ?>
        <div style="text-align: center; padding: 3rem; background: white; border-radius: 8px;">
            <h3>No Batches Yet</h3>
            <p>Create your first batch to organize your calves!</p>
            <a href="/public/batches/add" class="btn btn-primary">Create First Batch</a>
        </div>
        <?php else: ?>
        <div class="batch-grid">
            <?php foreach ($batches as $batch): 
                $capacityPercent = ($batch['current_count'] / $batch['capacity']) * 100;
                $capacityClass = $batch['capacity_color'];
                if ($capacityClass === 'green') $capacityClass = 'low';
                if ($capacityClass === 'orange') $capacityClass = 'medium';
                if ($capacityClass === 'red') $capacityClass = 'high';
            ?>
            <div class="batch-card capacity-<?php echo $capacityClass; ?>">
                <div class="batch-name"><?php echo htmlspecialchars($batch['name']); ?></div>
                
                <div class="batch-info">
                    <div><strong>Type:</strong> <?php echo ucfirst(str_replace('_', ' ', $batch['type'])); ?></div>
                    <div><strong>Location:</strong> <?php echo htmlspecialchars($batch['location'] ?? 'Not specified'); ?></div>
                    <div><strong>Capacity:</strong> <?php echo $batch['current_count']; ?> / <?php echo $batch['capacity']; ?> calves</div>
                </div>

                <div class="capacity-bar">
                    <div class="capacity-fill <?php echo $capacityClass; ?>" 
                         style="width: <?php echo min($capacityPercent, 100); ?>%"></div>
                </div>

                <div class="batch-actions">
                    <a href="/public/batches/view/<?php echo $batch['id']; ?>" class="btn btn-primary btn-sm">
                        View Calves (<?php echo $batch['current_count']; ?>)
                    </a>
                    <a href="/public/batches/edit?id=<?php echo $batch['id']; ?>" class="btn btn-secondary btn-sm">
                        Edit
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
        <?php
    }

    private function renderAddBatchForm($success, $error) {
        require_once BASE_PATH . '/app/core/ModernNavbar.php';
        $navbar = new ModernNavbar();
        ?>
<!DOCTYPE html>
<html>
<head>
    <title>Add New Batch - EasyCalf</title>
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <style>
        .form-container {
            max-width: 600px;
            margin: 0 auto;
            padding: 2rem;
            padding-top: 100px;
        }
        .form-section {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <?php $navbar->render('batches'); ?>
    
    <div class="form-container">
        <h1>Create New Batch</h1>

        <?php if ($success): ?>
            <div class="success-message">
                <h3>✅ Batch Created Successfully!</h3>
                <p>Your new batch has been created and is ready for calves.</p>
                <div style="margin-top: 1rem;">
                    <a href="/public/batches/add" class="btn btn-primary">Create Another Batch</a>
                    <a href="/public/batches" class="btn">View All Batches</a>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="error-message">
                <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if (!$success): ?>
        <div class="form-section">
            <form method="post">
                <input type="hidden" name="add_batch" value="1">
                
                <div class="form-group">
                    <label class="form-label">Batch Name *</label>
                    <input type="text" name="name" class="form-control" required 
                           placeholder="e.g., Spring Calves 2024">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Batch Type *</label>
                    <select name="type" class="form-control" required>
                        <option value="normal">Normal</option>
                        <option value="sick_pen">Sick Pen</option>
                        <option value="weaning_group">Weaning Group</option>
                        <option value="isolation">Isolation</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Capacity *</label>
                    <input type="number" name="capacity" class="form-control" value="10" min="1" max="50" required>
                    <small style="color: #666;">Recommended: 5-10 calves per batch</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Location/Pen *</label>
                    <input type="text" name="location" class="form-control" required 
                           placeholder="e.g., North Barn, Pen A">
                </div>
                
                <button type="submit" class="btn btn-primary">Create Batch</button>
                <a href="/public/batches" class="btn">Cancel</a>
            </form>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
        <?php
    }

    private function renderBatchCalves($batch, $calves) {
        require_once BASE_PATH . '/app/core/ModernNavbar.php';
        $navbar = new ModernNavbar();
        ?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo htmlspecialchars($batch['name']); ?> - Calves - EasyCalf</title>
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <style>
        .calves-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
            padding-top: 100px;
        }

        .batch-header {
            background: linear-gradient(135deg, var(--mid-blue), var(--navy));
            color: white;
            padding: 2rem;
            border-radius: 16px;
            margin-bottom: 2rem;
        }

        .batch-title {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
        }

        .batch-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .stat-item {
            background: rgba(255,255,255,0.2);
            padding: 1rem;
            border-radius: 8px;
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            display: block;
        }

        .stat-label {
            font-size: 0.8rem;
            opacity: 0.9;
        }

        .calves-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .calves-table th {
            background: linear-gradient(135deg, var(--light-accent), #e3f2fd);
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: var(--text-dark);
            border-bottom: 1px solid var(--glass-border);
        }

        .calves-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--glass-border);
        }

        .calves-table tr:hover td {
            background: rgba(234, 246, 255, 0.5);
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: var(--mid-blue);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            margin-bottom: 2rem;
            transition: all 0.3s ease;
        }

        .back-button:hover {
            background: var(--navy);
            transform: translateY(-2px);
        }

        @media (max-width: 768px) {
            .calves-container {
                padding: 1rem;
                padding-top: 80px;
            }
            
            .batch-stats {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <?php $navbar->render('batches'); ?>
    
    <div class="calves-container">
        <a href="/public/batches" class="back-button">
            <span>←</span>
            Back to Batches
        </a>

        <div class="batch-header">
            <h1 class="batch-title"><?php echo htmlspecialchars($batch['name']); ?></h1>
            <div class="batch-stats">
                <div class="stat-item">
                    <span class="stat-value"><?php echo $batch['current_count']; ?></span>
                    <span class="stat-label">Calves</span>
                </div>
                <div class="stat-item">
                    <span class="stat-value"><?php echo $batch['capacity']; ?></span>
                    <span class="stat-label">Capacity</span>
                </div>
                <div class="stat-item">
                    <span class="stat-value"><?php echo ucfirst(str_replace('_', ' ', $batch['type'])); ?></span>
                    <span class="stat-label">Type</span>
                </div>
                <div class="stat-item">
                    <span class="stat-value"><?php echo htmlspecialchars($batch['location'] ?? 'N/A'); ?></span>
                    <span class="stat-label">Location</span>
                </div>
            </div>
        </div>

        <?php if (empty($calves)): ?>
            <div style="text-align: center; padding: 3rem; background: white; border-radius: 12px;">
                <div style="font-size: 3rem; margin-bottom: 1rem;">🐄</div>
                <h3>No Calves in This Batch</h3>
                <p>Assign calves to this batch from the calves list.</p>
                <a href="/public/calves" class="btn btn-primary">Go to Calves</a>
            </div>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table class="calves-table">
                    <thead>
                        <tr>
                            <th>Calf ID</th>
                            <th>Birth Date</th>
                            <th>Age</th>
                            <th>Sex</th>
                            <th>Health Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($calves as $calf): ?>
                            <tr>
                                <td>
                                    <a href="/public/calves/passport/<?php echo $calf['id']; ?>" 
                                       style="color: var(--mid-blue); text-decoration: none; font-weight: 600;">
                                        <?php echo htmlspecialchars($calf['calf_id']); ?>
                                    </a>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($calf['birth_date'])); ?></td>
                                <td><?php echo $calf['age_days']; ?> days</td>
                                <td><?php echo ucfirst($calf['sex']); ?></td>
                                <td>
                                    <span class="health-<?php echo $calf['health_status']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $calf['health_status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="/public/calves/edit/<?php echo $calf['id']; ?>" 
                                       class="btn btn-primary btn-sm">Edit</a>
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

    private function renderEditBatchForm($batch, $success, $error) {
        require_once BASE_PATH . '/app/core/ModernNavbar.php';
        $navbar = new ModernNavbar();
        ?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Batch - EasyCalf</title>
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <style>
        .form-container {
            max-width: 600px;
            margin: 0 auto;
            padding: 2rem;
            padding-top: 100px;
        }
        .form-section {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <?php $navbar->render('batches'); ?>
    
    <div class="form-container">
        <h1>Edit Batch: <?php echo htmlspecialchars($batch['name']); ?></h1>

        <?php if ($error): ?>
            <div class="error-message">
                <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="form-section">
            <form method="post">
                <input type="hidden" name="update_batch" value="1">
                
                <div class="form-group">
                    <label class="form-label">Batch Name *</label>
                    <input type="text" name="name" class="form-control" required 
                           value="<?php echo htmlspecialchars($batch['name']); ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Batch Type *</label>
                    <select name="type" class="form-control" required>
                        <option value="normal" <?php echo $batch['type'] === 'normal' ? 'selected' : ''; ?>>Normal</option>
                        <option value="sick_pen" <?php echo $batch['type'] === 'sick_pen' ? 'selected' : ''; ?>>Sick Pen</option>
                        <option value="weaning_group" <?php echo $batch['type'] === 'weaning_group' ? 'selected' : ''; ?>>Weaning Group</option>
                        <option value="isolation" <?php echo $batch['type'] === 'isolation' ? 'selected' : ''; ?>>Isolation</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Capacity *</label>
                    <input type="number" name="capacity" class="form-control" 
                           value="<?php echo $batch['capacity']; ?>" min="1" max="50" required>
                    <small style="color: #666;">Recommended: 5-10 calves per batch</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Location/Pen *</label>
                    <input type="text" name="location" class="form-control" required 
                           value="<?php echo htmlspecialchars($batch['location'] ?? ''); ?>">
                </div>
                
                <button type="submit" class="btn btn-primary">Update Batch</button>
                <a href="/public/batches" class="btn">Cancel</a>
            </form>
        </div>
    </div>
</body>
</html>
        <?php
    }
}
?>