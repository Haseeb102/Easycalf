<?php
// app/modules/Settings/views/index.php
// Settings control panel view (module-local). Expects $title, $settings, $categories, $stats supplied by controller.

if (session_status() === PHP_SESSION_NONE) session_start();
require_once BASE_PATH . '/app/core/ModernNavbar.php';
$navbar = new ModernNavbar();

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($title ?? 'Settings Control Panel') ?></title>
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <style>
        .settings-container { max-width: 1100px; margin: 0 auto; padding: 2rem; padding-top: 100px; }
        .section { background: #fff; padding: 16px; border-radius: 8px; margin-bottom: 1rem; }
        .btn { background:#1976D2; color:#fff; padding:8px 12px; border-radius:6px; text-decoration:none; display:inline-block; }
        .btn-danger { background:#dc3545; }
        .table { width:100%; border-collapse: collapse; }
        .table th, .table td { padding:8px 10px; border-bottom:1px solid #eee; text-align:left; }
        .backup-actions { display:flex; gap:12px; align-items:center; }
        .muted { color:#666; font-size:0.95rem; }
        .import-area { display:flex; gap:12px; align-items:center; flex-wrap:wrap; }
    </style>
</head>
<body>
    <?php $navbar->render('settings'); ?>

    <div class="settings-container">
        <?php if (!empty($_SESSION['success_message'])): ?>
            <div style="background:#d4edda;color:#155724;padding:12px;border-radius:8px;margin-bottom:12px;">
                <?= $_SESSION['success_message'] ?>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (!empty($_SESSION['error_message'])): ?>
            <div style="background:#f8d7da;color:#721c24;padding:12px;border-radius:8px;margin-bottom:12px;">
                <?= $_SESSION['error_message'] ?>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <h1>⚙️ Settings Control Panel</h1>

        <div class="section">
            <div style="display:flex;align-items:center;gap:12px;">
                <a href="/public/settings/create" class="btn">➕ Add New Setting</a>
                <a href="/public/settings/export" class="btn">📤 Export Settings (JSON)</a>

                <form action="/public/settings/import" method="post" enctype="multipart/form-data" style="margin-left:auto;">
                    <div class="import-area">
                        <input type="file" name="file" accept=".json" required>
                        <button type="submit" class="btn">📥 Import Settings</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="section">
            <h3>🔎 Search Settings</h3>
            <form method="get" action="/public/settings/search" style="display:flex; gap:8px; align-items:center;">
                <input type="text" name="q" placeholder="Search key or value..." value="<?= htmlspecialchars($_GET['q'] ?? '') ?>" style="flex:1; padding:8px;">
                <select name="category" style="min-width:160px; padding:8px;">
                    <option value="">All categories</option>
                    <?php if (!empty($categories)): foreach ($categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                    <?php endforeach; endif; ?>
                </select>
                <button class="btn" type="submit">Search</button>
            </form>
        </div>

        <div class="section">
            <h3>📝 Settings</h3>
            <?php if (empty($settings)): ?>
                <div class="muted">No settings found.</div>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Key</th>
                            <th>Value</th>
                            <th>Category</th>
                            <th>Type</th>
                            <th>Active</th>
                            <th style="width:160px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($settings as $s): ?>
                            <tr>
                                <td><?= htmlspecialchars($s['setting_key']) ?></td>
                                <td style="max-width:420px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?= htmlspecialchars($s['setting_value']) ?></td>
                                <td><?= htmlspecialchars($s['category']) ?></td>
                                <td><?= htmlspecialchars($s['setting_type'] ?? 'text') ?></td>
                                <td><?= (!empty($s['is_active']) && $s['is_active'] == 1) ? 'Yes' : 'No' ?></td>
                                <td>
                                    <a href="/public/settings/edit/<?= (int)$s['id'] ?>" class="btn" style="background:#1976D2; padding:6px 8px;">Edit</a>
                                    <form method="post" action="/public/settings/delete/<?= (int)$s['id'] ?>" style="display:inline;">
                                        <button type="submit" class="btn btn-danger" onclick="return confirm('Move setting to trash?')">Trash</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- BACKUP SECTION -->
        <div class="section" id="backup-section">
            <h3>💾 Backup & Restore</h3>
            <p class="muted">Create a downloadable SQL backup of the full database (recommended before major changes). Restoring a backup will overwrite current data — proceed with caution. Only admins can use these tools.</p>

            <div class="backup-actions" style="margin-top:12px;">
                <!-- Download backup: simple GET to public/backup.php?action=download -->
                <a href="/public/backup.php?action=download" class="btn" style="background:#28a745;">⬇️ Download Full Database Backup</a>

                <!-- Upload backup: POST file to public/backup.php?action=upload -->
                <form method="post" action="/public/backup.php?action=upload" enctype="multipart/form-data" style="display:inline-block; margin-left:12px;">
                    <input type="file" name="backup_file" accept=".sql,.gz" required style="display:inline-block;">
                    <button type="submit" class="btn" style="background:#1976D2; margin-left:8px;" onclick="return confirm('Restoring a backup will overwrite current database. Are you sure?')">⬆️ Upload & Restore Backup</button>
                </form>
            </div>

            <div style="margin-top:12px;">
                <strong>Notes:</strong>
                <ul style="margin:8px 0 0 1rem;">
                    <li>If your server doesn't have <code>mysqldump</code> or <code>mysql</code> CLI, backup/restore will not work and you'll see an error message. Please install the MySQL client utilities or run backups from your DB host.</li>
                    <li>We recommend storing backups off-site (your PC or cloud storage).</li>
                    <li>For very large databases, prefer gzipped backups (.gz) to save space. The upload supports .gz files.</li>
                </ul>
            </div>
        </div>

        <div style="margin-top:24px; color:#666; font-size:0.9rem;">
            <strong>Support:</strong> If backup/restore fails due to server configuration (no shell access), I can implement a PHP-only SQL exporter that streams table-by-table data (slower but works without mysqldump). Ask me to enable that fallback if needed.
        </div>
    </div>
</body>
</html>