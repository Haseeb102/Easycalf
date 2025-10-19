<?php
require_once BASE_PATH . '/app/core/ModernNavbar.php';
$navbar = new ModernNavbar();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Update Health Status - EasyCalf</title>
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <style>
        .form-container { max-width: 500px; margin: 0 auto; padding: 2rem; padding-top: 100px; }
        .form-section { background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .health-option { padding: 1rem; margin: 0.5rem 0; border: 2px solid #e9ecef; border-radius: 8px; cursor: pointer; transition: all 0.3s; }
        .health-option:hover { border-color: #007AFF; background: #f8f9fa; }
    </style>
</head>
<body>
    <?php $navbar->render('calves'); ?>
    
    <div class="form-container">
        <h1>Update Health Status</h1>
        <p>Update health status for <?php echo count($ids); ?> selected calves</p>

        <div class="form-section">
            <form method="post" action="/public/calves/bulk-health">
                <input type="hidden" name="update_health" value="1">
                <?php foreach ($ids as $id): ?>
                    <input type="hidden" name="calf_ids[]" value="<?php echo $id; ?>">
                <?php endforeach; ?>
                
                <div class="form-group">
                    <label class="form-label">Select New Health Status</label>
                    
                    <label class="health-option">
                        <input type="radio" name="health_status" value="healthy" required>
                        <strong style="color: #28a745;">💚 Healthy</strong><br>
                        <small style="color: #666;">Calf is in good health</small>
                    </label>
                    
                    <label class="health-option">
                        <input type="radio" name="health_status" value="needs_attention" required>
                        <strong style="color: #ffc107;">⚠️ Needs Attention</strong><br>
                        <small style="color: #666;">Calf requires monitoring</small>
                    </label>
                    
                    <label class="health-option">
                        <input type="radio" name="health_status" value="sick" required>
                        <strong style="color: #dc3545;">🤒 Sick</strong><br>
                        <small style="color: #666;">Requires immediate treatment</small>
                    </label>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">
                    Update Health Status
                </button>
                <a href="/public/calves" class="btn" style="width: 100%; margin-top: 0.5rem; text-align: center; display: block;">
                    Cancel
                </a>
            </form>
        </div>
    </div>
</body>
</html>