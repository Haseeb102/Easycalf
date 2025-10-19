<artifact identifier="due-tasks-view" type="application/vnd.ant.code" language="php" title="Due Tasks View">
<?php
require_once BASE_PATH . '/app/core/ModernNavbar.php';
$navbar = new ModernNavbar();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Due Tasks - EasyCalf</title>
    <link rel="stylesheet" href="/public/assets/css/style.css">
</head>
<body>
    <?php $navbar->render('tasks'); ?>
<div style="max-width: 1200px; margin: 0 auto; padding: 2rem; padding-top: 100px;">
    <a href="/public/tasks" class="btn btn-secondary" style="margin-bottom: 2rem;">← Back to Tasks</a>
    
    <h1>⚠️ All Due Tasks (<?= count($tasks) ?>)</h1>
    
    <?php if (empty($tasks)): ?>
        <div style="text-align: center; padding: 3rem; background: white; border-radius: 12px;">
            <div style="font-size: 3rem; margin-bottom: 1rem;">✅</div>
            <h3>No Overdue Tasks!</h3>
            <p>All tasks are up to date.</p>
        </div>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Calf ID</th>
                    <th>Task</th>
                    <th>Due Date</th>
                    <th>Age</th>
                    <th>Batch</th>
                    <th>Health</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tasks as $task): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($task['calf_identifier']) ?></strong>
                        </td>
                        <td><?= htmlspecialchars($task['event_name']) ?></td>
                        <td style="color: <?= getTaskPriorityColor($task['due_date']) ?>;">
                            <?= date('M j, Y', strtotime($task['due_date'])) ?>
                            <br><small><?= getDueStatusLabel($task['due_date'], 'pending') ?></small>
                        </td>
                        <td><?= $task['age_days'] ?> days</td>
                        <td><?= htmlspecialchars($task['batch_name'] ?? 'No Batch') ?></td>
                        <td>
                            <span class="health-<?= $task['health_status'] ?>">
                                <?= ucfirst(str_replace('_', ' ', $task['health_status'])) ?>
                            </span>
                        </td>
                        <td>
                            <form method="post" action="/public/tasks/complete" style="display: inline;">
                                <input type="hidden" name="task_id" value="<?= $task['task_id'] ?>"><artifact identifier="due-tasks-view" type="application/vnd.ant.code" language="php" title="Due Tasks View">
<?php
require_once BASE_PATH . '/app/core/ModernNavbar.php';
$navbar = new ModernNavbar();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Due Tasks - EasyCalf</title>
    <link rel="stylesheet" href="/public/assets/css/style.css">
</head>
<body>
    <?php $navbar->render('tasks'); ?>
<div style="max-width: 1200px; margin: 0 auto; padding: 2rem; padding-top: 100px;">
    <a href="/public/tasks" class="btn btn-secondary" style="margin-bottom: 2rem;">← Back to Tasks</a>
    
    <h1>⚠️ All Due Tasks (<?= count($tasks) ?>)</h1>
    
    <?php if (empty($tasks)): ?>
        <div style="text-align: center; padding: 3rem; background: white; border-radius: 12px;">
            <div style="font-size: 3rem; margin-bottom: 1rem;">✅</div>
            <h3>No Overdue Tasks!</h3>
            <p>All tasks are up to date.</p>
        </div>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Calf ID</th>
                    <th>Task</th>
                    <th>Due Date</th>
                    <th>Age</th>
                    <th>Batch</th>
                    <th>Health</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tasks as $task): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($task['calf_identifier']) ?></strong>
                        </td>
                        <td><?= htmlspecialchars($task['event_name']) ?></td>
                        <td style="color: <?= getTaskPriorityColor($task['due_date']) ?>;">
                            <?= date('M j, Y', strtotime($task['due_date'])) ?>
                            <br><small><?= getDueStatusLabel($task['due_date'], 'pending') ?></small>
                        </td>
                        <td><?= $task['age_days'] ?> days</td>
                        <td><?= htmlspecialchars($task['batch_name'] ?? 'No Batch') ?></td>
                        <td>
                            <span class="health-<?= $task['health_status'] ?>">
                                <?= ucfirst(str_replace('_', ' ', $task['health_status'])) ?>
                            </span>
                        </td>
                        <td>
                            <form method="post" action="/public/tasks/complete" style="display: inline;">
                                <input type="hidden" name="task_id" value="<?= $task['task_id'] ?>">
                                <button type="submit" class="btn btn-primary btn-sm">✅ Complete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
</body>
</html>
</artifact>