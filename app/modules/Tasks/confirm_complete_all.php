<artifact identifier="confirm-complete-all-view" type="application/vnd.ant.code" language="php" title="Confirm Complete All View">
<?php
require_once BASE_PATH . '/app/core/ModernNavbar.php';
$navbar = new ModernNavbar();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Confirm Complete All - EasyCalf</title>
    <link rel="stylesheet" href="/public/assets/css/style.css">
</head>
<body>
    <?php $navbar->render('tasks'); ?>
<div style="max-width: 600px; margin: 0 auto; padding: 2rem; padding-top: 100px;">
    <div style="background: white; padding: 2rem; border-radius: 12px; text-align: center; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
        <div style="font-size: 4rem; margin-bottom: 1rem;">⚠️</div>
        <h1>Complete All Due Tasks?</h1>
        <p style="font-size: 1.2rem; margin: 1rem 0;"><?= $dueCount ?> tasks will be marked complete</p>
        <div style="margin-top: 2rem; display: flex; gap: 1rem; justify-content: center;">
            <a href="/public/tasks/complete-all-due?confirm=1" class="btn btn-primary" style="background: #28a745;">✅ Yes, Complete All</a>
            <a href="/public/tasks" class="btn btn-secondary">❌ Cancel</a>
        </div>
    </div>
</div>
</body>
</html>
</artifact>