<artifact identifier="calendar-view" type="application/vnd.ant.code" language="php" title="Calendar View">
<?php
require_once BASE_PATH . '/app/core/ModernNavbar.php';
$navbar = new ModernNavbar();
prevMonth=date(′m′,strtotime(′−1month′,strtotime("prevMonth = date('m', strtotime('-1 month', strtotime("
prevMonth=date(′m′,strtotime(′−1month′,strtotime("year-$month-01")));
prevYear=date(′Y′,strtotime(′−1month′,strtotime("prevYear = date('Y', strtotime('-1 month', strtotime("
prevYear=date(′Y′,strtotime(′−1month′,strtotime("year-$month-01")));
nextMonth=date(′m′,strtotime(′+1month′,strtotime("nextMonth = date('m', strtotime('+1 month', strtotime("
nextMonth=date(′m′,strtotime(′+1month′,strtotime("year-$month-01")));
nextYear=date(′Y′,strtotime(′+1month′,strtotime("nextYear = date('Y', strtotime('+1 month', strtotime("
nextYear=date(′Y′,strtotime(′+1month′,strtotime("year-$month-01")));
?>

<!DOCTYPE html>
<html>
<head>
    <title>Task Calendar - EasyCalf</title>
    <link rel="stylesheet" href="/public/assets/css/style.css">
</head>
<body>
    <?php $navbar->render('tasks'); ?>
<div style="max-width: 1200px; margin: 0 auto; padding: 2rem; padding-top: 100px;">
    <h1>📅 Task Calendar - <?= date('F Y', strtotime("$year-$month-01")) ?></h1>
    
    <div style="display: flex; justify-content: space-between; margin: 2rem 0;">
        <a href="/public/tasks/calendar?month=<?= $prevMonth ?>&year=<?= $prevYear ?>" class="btn btn-secondary">← Previous</a>
        <a href="/public/tasks" class="btn btn-primary">Back to Tasks</a>
        <a href="/public/tasks/calendar?month=<?= $nextMonth ?>&year=<?= $nextYear ?>" class="btn btn-secondary">Next →</a>
    </div>
    
    <div style="background: white; padding: 2rem; border-radius: 12px;">
        <p style="text-align: center; color: #666;">Calendar view - <?= count($tasks) ?> days with tasks</p>
        
        <?php if (!empty($tasks)): ?>
            <div style="margin-top: 2rem;">
                <?php foreach ($tasks as $task): ?>
                    <div style="padding: 1rem; margin: 0.5rem 0; background: #f5f5f5; border-radius: 8px;">
                        <strong><?= date('M j, Y', strtotime($task['due_date'])) ?>:</strong> <?= $task['task_count'] ?> tasks
                        <?php if ($task['overdue_count'] > 0): ?>
                            <span style="color: #dc3545; margin-left: 1rem;">⚠️ <?= $task['overdue_count'] ?> overdue</span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
</artifact>