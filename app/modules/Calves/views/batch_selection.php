<?php
require_once BASE_PATH . '/app/core/ModernNavbar.php';
$navbar = new ModernNavbar();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Change Batch - EasyCalf</title>
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <style>
        .form-container { max-width: 500px; margin: 0 auto; padding: 2rem; padding-top: 100px; }
        .form-section { background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
    <?php $navbar->render('calves'); ?>
    
    <div class="form-container">
        <h1>Change Batch for <?php echo htmlspecialchars($calf['calf_id']); ?></h1>

        <div class="form-section">
            <form method="post">
                <div class="form-group">
                    <label class="form-label">Select New Batch</label>
                    <select name="batch_id" class="form-control" required>
                        <option value="">No Batch</option>
                        <?php foreach ($batches as $batch): ?>
                            <option value="<?php echo $batch['id']; ?>" <?php echo $calf['batch_id'] == $batch['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($batch['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%;">Update Batch</button>
                <a href="/public/calves/passport/<?php echo $calf['id']; ?>" class="btn" style="width: 100%; margin-top: 0.5rem; text-align: center; display: block;">Cancel</a>
            </form>
        </div>
    </div>
</body>
</html>