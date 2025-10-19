<?php
require_once BASE_PATH . '/app/core/ModernNavbar.php';
$navbar = new ModernNavbar();

$supportedFormats = ['csv', 'txt'];
?>
<!DOCTYPE html>
<html>
<head>
    <title>Bulk Import Calves - EasyCalf</title>
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <style>
        .import-container { max-width: 900px; margin: 0 auto; padding: 2rem; padding-top: 100px; }
        .import-section { background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); margin-bottom: 2rem; }
        .upload-area { border: 3px dashed var(--primary-blue); border-radius: 12px; padding: 3rem; text-align: center; background: rgba(234, 246, 255, 0.3); cursor: pointer; transition: all 0.3s ease; }
        .upload-area:hover { background: rgba(234, 246, 255, 0.5); border-color: var(--secondary-green); }
        .upload-area.drag-over { background: rgba(161, 195, 73, 0.2); border-color: var(--secondary-green); }
        .upload-icon { font-size: 4rem; margin-bottom: 1rem; }
        .file-input { display: none; }
        .format-badges { display: flex; gap: 0.5rem; justify-content: center; flex-wrap: wrap; margin: 1rem 0; }
        .format-badge { background: #e3f2fd; color: #1976d2; padding: 0.5rem 1rem; border-radius: 20px; font-size: 0.9rem; font-weight: 600; }
        .info-box { background: #e7f3ff; padding: 1.5rem; border-radius: 8px; border-left: 4px solid #1976d2; margin: 1rem 0; }
        .duplicate-options { display: flex; gap: 1rem; margin: 1.5rem 0; }
        .duplicate-option { flex: 1; padding: 1rem; border: 2px solid #e0e0e0; border-radius: 8px; cursor: pointer; transition: all 0.3s ease; }
        .duplicate-option:hover { border-color: var(--primary-blue); background: rgba(234, 246, 255, 0.3); }
        .duplicate-option.selected { border-color: var(--primary-blue); background: rgba(234, 246, 255, 0.5); }
        .duplicate-option input[type="radio"] { margin-right: 0.5rem; }
    </style>
</head>
<body>
    <?php $navbar->render('calves'); ?>
    
    <div class="import-container">
        <a href="/public/calves" class="btn btn-secondary" style="margin-bottom: 2rem;">← Back to Calves</a>

        <h1 style="text-align: center; margin-bottom: 0.5rem;">📤 Smart Bulk Import</h1>
        <p style="text-align: center; color: var(--text-secondary); margin-bottom: 2rem;">
            Import calves from CSV files with intelligent column detection
        </p>

        <?php if (isset($_SESSION['import_results'])): 
            $results = $_SESSION['import_results'];
            unset($_SESSION['import_results']);
        ?>
            <div class="import-section">
                <h2 style="color: var(--primary-blue); margin-bottom: 1rem;">📊 Import Results</h2>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
                    <div style="text-align: center; padding: 1rem; background: #e8f5e9; border-radius: 8px;">
                        <div style="font-size: 2rem; font-weight: 700; color: #2e7d32;"><?= $results['successful'] ?></div>
                        <div style="font-size: 0.9rem; color: #666;">Imported</div>
                    </div>
                    
                    <div style="text-align: center; padding: 1rem; background: #fff3e0; border-radius: 8px;">
                        <div style="font-size: 2rem; font-weight: 700; color: #ef6c00;"><?= $results['duplicates'] ?></div>
                        <div style="font-size: 0.9rem; color: #666;">Duplicates</div>
                    </div>
                    
                    <?php if ($results['updated'] > 0): ?>
                    <div style="text-align: center; padding: 1rem; background: #e3f2fd; border-radius: 8px;">
                        <div style="font-size: 2rem; font-weight: 700; color: #1976d2;"><?= $results['updated'] ?></div>
                        <div style="font-size: 0.9rem; color: #666;">Updated</div>
                    </div>
                    <?php endif; ?>
                    
                    <div style="text-align: center; padding: 1rem; background: #ffebee; border-radius: 8px;">
                        <div style="font-size: 2rem; font-weight: 700; color: #c62828;"><?= $results['failed'] ?></div>
                        <div style="font-size: 0.9rem; color: #666;">Failed</div>
                    </div>
                </div>

                <?php if (!empty($results['successful_imports'])): ?>
                    <details style="margin: 1rem 0;">
                        <summary style="cursor: pointer; font-weight: 600; color: #2e7d32; padding: 0.5rem;">
                            ✅ Successfully Imported (<?= count($results['successful_imports']) ?>)
                        </summary>
                        <div style="margin-top: 1rem; max-height: 200px; overflow-y: auto;">
                            <?php foreach ($results['successful_imports'] as $success): ?>
                                <div style="padding: 0.5rem; border-bottom: 1px solid #e0e0e0;">
                                    Row <?= $success['row'] ?>: <strong><?= htmlspecialchars($success['calf_id']) ?></strong>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </details>
                <?php endif; ?>

                <?php if (!empty($results['duplicate_list'])): ?>
                    <details style="margin: 1rem 0;">
                        <summary style="cursor: pointer; font-weight: 600; color: #ef6c00; padding: 0.5rem;">
                            ⚠️ Duplicates (<?= count($results['duplicate_list']) ?>)
                        </summary>
                        <div style="margin-top: 1rem; max-height: 200px; overflow-y: auto;">
                            <?php foreach ($results['duplicate_list'] as $dup): ?>
                                <div style="padding: 0.5rem; border-bottom: 1px solid #e0e0e0;">
                                    Row <?= $dup['row'] ?>: <strong><?= htmlspecialchars($dup['calf_id']) ?></strong> - <?= $dup['action'] ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </details>
                <?php endif; ?>

                <?php if (!empty($results['failed_list'])): ?>
                    <details style="margin: 1rem 0;">
                        <summary style="cursor: pointer; font-weight: 600; color: #c62828; padding: 0.5rem;">
                            ❌ Failed Imports (<?= count($results['failed_list']) ?>)
                        </summary>
                        <div style="margin-top: 1rem; max-height: 200px; overflow-y: auto;">
                            <?php foreach ($results['failed_list'] as $fail): ?>
                                <div style="padding: 0.5rem; border-bottom: 1px solid #e0e0e0; background: #ffebee;">
                                    <strong>Row <?= $fail['row'] ?>: <?= htmlspecialchars($fail['calf_id']) ?></strong><br>
                                    <?php foreach ($fail['errors'] as $error): ?>
                                        <small style="color: #c62828;">• <?= htmlspecialchars($error) ?></small><br>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </details>
                <?php endif; ?>

                <div style="text-align: center; margin-top: 2rem;">
                    <a href="/public/calves" class="btn btn-primary">View All Calves</a>
                    <a href="/public/calves/import" class="btn btn-secondary">Import More</a>
                </div>
            </div>
        <?php endif; ?>

        <div class="import-section">
            <h2 style="margin-bottom: 1rem;">📁 Upload File</h2>
            
            <div class="info-box">
                <strong>🎯 Smart Import Features:</strong>
                <ul style="margin: 0.5rem 0 0 1.5rem; line-height: 1.8;">
                    <li>Automatic column detection - No need to match exact template!</li>
                    <li>Flexible date formats: DD-MM-YYYY, DD/MM/YYYY, YYYY-MM-DD</li>
                    <li>Smart field recognition (e.g., "DoB" = "Birth Date")</li>
                    <li>Duplicate handling options</li>
                </ul>
            </div>

            <form method="post" enctype="multipart/form-data" id="importForm">
                <label for="file_upload" class="upload-area" id="uploadArea">
                    <div class="upload-icon">📄</div>
                    <h3>Choose CSV File or Drag & Drop</h3>
                    <p>Supported formats:</p>
                    <div class="format-badges">
                        <?php foreach ($supportedFormats as $format): ?>
                            <span class="format-badge">.<?= strtoupper($format) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <input type="file" name="import_file" id="file_upload" class="file-input" 
                           accept=".csv,.txt" required>
                </label>

                <div id="fileInfo" style="display: none; margin-top: 1rem; padding: 1rem; background: #e7f3ff; border-radius: 8px;">
                    <strong>Selected:</strong> <span id="fileName"></span>
                    <span id="fileSize" style="margin-left: 1rem; color: #666;"></span>
                </div>

                <h3 style="margin-top: 2rem; margin-bottom: 1rem;">🔄 How to Handle Duplicates?</h3>
                <div class="duplicate-options">
                    <label class="duplicate-option selected">
                        <input type="radio" name="duplicate_action" value="skip" checked>
                        <strong>Skip Duplicates</strong><br>
                        <small style="color: #666;">Keep existing data unchanged</small>
                    </label>
                    
                    <label class="duplicate-option">
                        <input type="radio" name="duplicate_action" value="update">
                        <strong>Update Existing</strong><br>
                        <small style="color: #666;">Overwrite with new data</small>
                    </label>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; padding: 1rem; font-size: 1.1rem; margin-top: 1rem;">
                    📤 Start Import
                </button>
            </form>
        </div>

        <div class="import-section">
            <h3>💡 Tips for Best Results</h3>
            <ul style="margin: 1rem 0 0 1.5rem; line-height: 2;">
                <li><strong>Required fields:</strong> Calf ID, Birth Date, Sex (M/F or Male/Female)</li>
                <li><strong>Date formats:</strong> Use DD-MM-YYYY (e.g., 22-02-2025) or DD/MM/YYYY</li>
                <li><strong>Sex values:</strong> M, Male, Bull OR F, Female, Heifer</li>
                <li><strong>Column names:</strong> System will recognize variations automatically</li>
                <li><strong>Empty rows:</strong> Automatically skipped - no need to remove them</li>
            </ul>
        </div>
    </div>

    <script>
        // Simple file upload handling
        document.getElementById('file_upload').addEventListener('change', function() {
            if (this.files.length > 0) {
                const file = this.files[0];
                const fileExt = file.name.split('.').pop().toLowerCase();
                
                if (!['csv', 'txt'].includes(fileExt)) {
                    alert('Please select a CSV file (.csv or .txt)');
                    this.value = '';
                    return;
                }
                
                document.getElementById('fileName').textContent = file.name;
                document.getElementById('fileSize').textContent = (file.size / 1024).toFixed(2) + ' KB';
                document.getElementById('fileInfo').style.display = 'block';
            }
        });

        // Duplicate option selection
        document.querySelectorAll('.duplicate-option').forEach(option => {
            option.addEventListener('click', function() {
                document.querySelectorAll('.duplicate-option').forEach(opt => {
                    opt.classList.remove('selected');
                });
                this.classList.add('selected');
                this.querySelector('input[type="radio"]').checked = true;
            });
        });

        // Form submission
        document.getElementById('importForm').addEventListener('submit', function() {
            const btn = this.querySelector('button[type="submit"]');
            btn.innerHTML = '⏳ Processing Import...';
            btn.disabled = true;
        });
    </script>
</body>
</html>