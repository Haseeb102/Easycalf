<?php
require_once BASE_PATH . '/app/core/ModernNavbar.php';
$navbar = new ModernNavbar();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Move Calves to Batch - EasyCalf</title>
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <style>
        .form-container { max-width: 500px; margin: 0 auto; padding: 2rem; padding-top: 100px; }
        .form-section { background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
    <?php $navbar->render('calves'); ?>
    
    <div class="form-container">
        <h1>Move <?php echo count($ids); ?> Calves to Batch</h1>

        <div class="form-section">
            <form method="post" action="/public/calves/bulk-batch">
                <input type="hidden" name="move_batch" value="1">
                <?php foreach ($ids as $id): ?>
                    <input type="hidden" name="calf_ids[]" value="<?php echo $id; ?>">
                <?php endforeach; ?>
                
                <div class="form-group">
                    <label class="form-label">Select Batch</label>
                    <select name="batch_id" class="form-control" required>
                        <option value="">No Batch</option>
                        <?php foreach ($batches as $batch): ?>
                            <option value="<?php echo $batch['id']; ?>">
                                <?php echo htmlspecialchars($batch['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">
                    Move Calves
                </button>
                <a href="/public/calves" class="btn" style="width: 100%; margin-top: 0.5rem; text-align: center; display: block;">
                    Cancel
                </a>
            </form>
        </div>
    </div>
</body>
</html>