<?php
require_once BASE_PATH . '/app/core/ModernNavbar.php';
$navbar = new ModernNavbar();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Add New Calf - EasyCalf</title>
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <style>
        .form-container { max-width: 600px; margin: 0 auto; padding: 2rem; padding-top: 100px; }
        .form-section { background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success-message { background: #d4edda; color: #155724; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; }
        .error-message { background: #f8d7da; color: #721c24; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    </style>
</head>
<body>
    <?php $navbar->render('calves'); ?>
    
    <div class="form-container">
        <h1>Add New Calf</h1>

        <?php if ($success): ?>
            <div class="success-message">
                <h3>✅ Calf Added Successfully!</h3>
                <div style="margin-top: 1rem;">
                    <a href="/public/calves/add" class="btn btn-primary">Add Another</a>
                    <a href="/public/calves" class="btn">View All Calves</a>
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
                <input type="hidden" name="add_calf" value="1">
                
                <div class="form-group">
                    <label class="form-label">Calf ID *</label>
                    <input type="text" name="calf_id" class="form-control" required 
                           value="<?php echo htmlspecialchars($suggestedId); ?>">
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Birth Date *</label>
                        <input type="date" name="birth_date" class="form-control" required 
                               value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Sex *</label>
                        <select name="sex" class="form-control" required>
                            <option value="">Select Sex</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                        </select>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Dam ID</label>
                        <input type="text" name="dam_id" class="form-control">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Birth Weight (kg)</label>
                        <input type="number" name="birth_weight" class="form-control" step="0.1" min="0">
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Health Status *</label>
                        <select name="health_status" class="form-control" required>
                            <option value="healthy" selected>Healthy</option>
                            <option value="needs_attention">Needs Attention</option>
                            <option value="sick">Sick</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Batch</label>
                        <select name="batch_id" class="form-control">
                            <option value="">No Batch</option>
                            <?php foreach ($batches as $batch): ?>
                                <option value="<?php echo $batch['id']; ?>">
                                    <?php echo htmlspecialchars($batch['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">
                    ✅ Add Calf
                </button>
                <a href="/public/calves" class="btn" style="width: 100%; margin-top: 0.5rem; text-align: center; display: block;">
                    Cancel
                </a>
            </form>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>