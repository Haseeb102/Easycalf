<?php
require_once BASE_PATH . '/app/core/ModernNavbar.php';
$navbar = new ModernNavbar();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Calf - EasyCalf</title>
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <style>
        .form-container { max-width: 600px; margin: 0 auto; padding: 2rem; padding-top: 100px; }
        .form-section { background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .error-message { background: #f8d7da; color: #721c24; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; }
    </style>
</head>
<body>
    <?php $navbar->render('calves'); ?>
    
    <div class="form-container">
        <h1>Edit Calf: <?php echo htmlspecialchars($calf['calf_id']); ?></h1>

        <?php if ($error): ?>
            <div class="error-message">
                <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="form-section">
            <form method="post">
                <input type="hidden" name="update_calf" value="1">
                
                <div class="form-group">
                    <label class="form-label">Health Status *</label>
                    <select name="health_status" class="form-control" required>
                        <option value="healthy" <?php echo $calf['health_status'] === 'healthy' ? 'selected' : ''; ?>>Healthy</option>
                        <option value="needs_attention" <?php echo $calf['health_status'] === 'needs_attention' ? 'selected' : ''; ?>>Needs Attention</option>
                        <option value="sick" <?php echo $calf['health_status'] === 'sick' ? 'selected' : ''; ?>>Sick</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Status *</label>
                    <select name="status" class="form-control" required>
                        <option value="active" <?php echo $calf['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="sold" <?php echo $calf['status'] === 'sold' ? 'selected' : ''; ?>>Sold</option>
                        <option value="deceased" <?php echo $calf['status'] === 'deceased' ? 'selected' : ''; ?>>Deceased</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Batch</label>
                    <select name="batch_id" class="form-control">
                        <option value="">No Batch</option>
                        <?php foreach ($batches as $batch): ?>
                            <option value="<?php echo $batch['id']; ?>" <?php echo $calf['batch_id'] == $batch['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($batch['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Birth Weight (kg)</label>
                    <input type="number" name="birth_weight" class="form-control" 
                           value="<?php echo $calf['birth_weight'] ?? ''; ?>" step="0.1" min="0">
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%;">Update Calf</button>
                <a href="/public/calves/passport/<?php echo $calf['id']; ?>" class="btn" style="width: 100%; margin-top: 0.5rem; text-align: center; display: block;">Cancel</a>
            </form>
        </div>
    </div>
</body>
</html>