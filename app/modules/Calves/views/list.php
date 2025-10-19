<?php
require_once BASE_PATH . '/app/core/ModernNavbar.php';
$navbar = new ModernNavbar();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Calf Management - EasyCalf</title>
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <style>
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
    
    .page-title {
        font-size: 2rem;
        font-weight: 800;
        color: var(--primary-dark);
    }
    
    .action-buttons {
        display: flex;
        gap: 1rem;
    }
    
    .bulk-actions-bar {
        background: linear-gradient(135deg, #667eea, #764ba2);
        padding: 1rem 1.5rem;
        border-radius: 12px;
        margin-bottom: 1.5rem;
        display: none;
        align-items: center;
        gap: 1rem;
        color: white;
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    }
    
    .bulk-actions-bar.active {
        display: flex;
    }
    
    .bulk-count {
        font-weight: 700;
        font-size: 1.1rem;
    }
    
    .bulk-actions {
        display: flex;
        gap: 0.5rem;
        margin-left: auto;
    }
    
    .bulk-btn {
        background: rgba(255, 255, 255, 0.2);
        color: white;
        border: 1px solid rgba(255, 255, 255, 0.3);
        padding: 0.5rem 1rem;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        font-size: 0.9rem;
    }
    
    .bulk-btn:hover {
        background: rgba(255, 255, 255, 0.3);
        transform: translateY(-2px);
    }
    
    .bulk-btn.danger {
        background: rgba(239, 68, 68, 0.9);
        border-color: rgba(220, 38, 38, 1);
    }
    
    .bulk-btn.danger:hover {
        background: rgba(220, 38, 38, 1);
    }
    
    .calf-checkbox {
        width: 18px;
        height: 18px;
        cursor: pointer;
    }
    
    .treatment-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        padding: 0.25rem 0.5rem;
        border-radius: 12px;
        font-size: 0.7rem;
        font-weight: 600;
        margin: 0.1rem;
    }
    
    .badge-electrolyte { 
        background: #E3F2FD; 
        color: #1976D2; 
        border: 1px solid #BBDEFB; 
    }
    
    .btn-sm {
        padding: 0.5rem 1rem;
        font-size: 0.8rem;
    }
    
    .btn-electrolyte {
        background: linear-gradient(135deg, #2196F3, #1976D2);
        color: white;
        border: none;
        padding: 0.4rem 0.75rem;
        border-radius: 6px;
        font-size: 0.7rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    
    .btn-electrolyte:hover {
        background: linear-gradient(135deg, #1976D2, #1565C0);
        transform: translateY(-1px);
    }
    
    .btn-undo-electrolyte {
        background: linear-gradient(135deg, #FF9500, #FF7300);
        color: white;
        border: none;
        padding: 0.4rem 0.75rem;
        border-radius: 6px;
        font-size: 0.7rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    
    .btn-undo-electrolyte:hover {
        background: linear-gradient(135deg, #FF7300, #E55C00);
        transform: translateY(-1px);
    }
    
    .btn-delete {
        background: linear-gradient(135deg, #EF4444, #DC2626);
        color: white;
        border: none;
        padding: 0.4rem 0.75rem;
        border-radius: 6px;
        font-size: 0.7rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    
    .btn-delete:hover {
        background: linear-gradient(135deg, #DC2626, #B91C1C);
        transform: translateY(-1px);
    }
    
    .success-message {
        background: #d4edda;
        color: #155724;
        padding: 1rem 1.5rem;
        border-radius: 8px;
        margin-bottom: 1.5rem;
        border-left: 4px solid #28a745;
        font-weight: 500;
    }

    .error-message {
        background: #f8d7da;
        color: #721c24;
        padding: 1rem 1.5rem;
        border-radius: 8px;
        margin-bottom: 1.5rem;
        border-left: 4px solid #dc3545;
        font-weight: 500;
    }
    
    @media (max-width: 768px) {
        .bulk-actions-bar {
            flex-wrap: wrap;
        }
        
        .bulk-actions {
            width: 100%;
            margin-left: 0;
            margin-top: 0.5rem;
        }
    }
</style>
</head>
<body>
    <?php $navbar->render('calves'); ?>
<div class="main-content">
    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">🐮 Calf Management</h1>
            <div class="action-buttons">
                <a href="/public/calves/add" class="btn btn-primary">+ Add New Calf</a>
                <a href="/public/calves/import" class="btn btn-secondary">📤 Bulk Import</a>
                <a href="/public/calves/export" class="btn btn-secondary">💾 Export CSV</a>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="success-message">
                <span style="font-size: 1.2rem; margin-right: 0.5rem;">✅</span>
                <?php echo htmlspecialchars($_SESSION['success_message']); ?>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="error-message">
                <span style="font-size: 1.2rem; margin-right: 0.5rem;">⚠️</span>
                <?php echo htmlspecialchars($_SESSION['error_message']); ?>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>
        
        <!-- Bulk Actions Bar -->
        <div class="bulk-actions-bar" id="bulkActionsBar">
            <span class="bulk-count">
                <span id="selectedCount">0</span> calves selected
            </span>
            <div class="bulk-actions">
                <button class="bulk-btn" onclick="bulkAssignBatch()">
                    📦 Assign to Batch
                </button>
                <button class="bulk-btn" onclick="bulkUpdateHealth()">
                    💚 Update Health
                </button>
                <button class="bulk-btn" onclick="bulkElectrolyte()">
                    🧪 Electrolyte Treatment
                </button>
                <button class="bulk-btn danger" onclick="bulkDelete()">
                    🗑️ Delete Selected
                </button>
                <button class="bulk-btn" onclick="clearSelection()">
                    ✖ Clear Selection
                </button>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <div class="filter-group">
                <label class="filter-label">Search Calves</label>
                <input type="text" id="searchCalves" placeholder="Search by Calf ID..." 
                       class="form-control" style="max-width: 300px;">
            </div>
            
            <div class="filter-group">
                <label class="filter-label">Health Status</label>
                <select id="filterHealth" class="form-control" style="max-width: 200px;">
                    <option value="">All Health Status</option>
                    <option value="healthy">Healthy</option>
                    <option value="needs_attention">Needs Attention</option>
                    <option value="sick">Sick</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label class="filter-label">Batch</label>
                <select id="filterBatch" class="form-control" style="max-width: 200px;">
                    <option value="">All Batches</option>
                    <?php foreach ($batches as $batch): ?>
                        <option value="<?php echo htmlspecialchars($batch['name']); ?>">
                            <?php echo htmlspecialchars($batch['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <button onclick="clearFilters()" class="btn btn-secondary" style="align-self: end;">
                Clear Filters
            </button>
        </div>

        <?php if (empty($calves)): ?>
        <div class="card" style="text-align: center; padding: 3rem;">
            <h3>No Calves Yet</h3>
            <p>Get started by adding your first calf!</p>
            <a href="/public/calves/add" class="btn btn-primary">Add Your First Calf</a>
        </div>
        <?php else: ?>
        <div class="card">
            <div class="card-body">
                <div style="overflow-x: auto;">
                    <table class="table">
                        <thead>
                            <tr>
                                <th style="width: 40px;">
                                    <input type="checkbox" id="selectAll" class="calf-checkbox" 
                                           onchange="toggleSelectAll(this)">
                                </th>
                                <th>Calf ID</th>
                                <th>Birth Date</th>
                                <th>Age</th>
                                <th>Sex</th>
                                <th>Health</th>
                                <th>Batch</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($calves as $calf): 
                                $healthClass = 'health-' . $calf['health_status'];
                                $hasActiveElectrolyte = in_array($calf['calf_id'], $electrolyteCalfIds);
                            ?>
                            <tr>
                                <td>
                                    <input type="checkbox" class="calf-checkbox calf-select" 
                                           value="<?php echo $calf['id']; ?>"
                                           onchange="updateBulkActions()">
                                </td>
                                <td>
                                    <a href="/public/calves/passport/<?php echo $calf['id']; ?>" 
                                       style="color: var(--primary-blue); text-decoration: none; font-weight: 600;">
                                        <?php echo htmlspecialchars($calf['calf_id']); ?>
                                    </a>
                                    <?php if ($hasActiveElectrolyte): ?>
                                        <span class="treatment-badge badge-electrolyte" title="Active Electrolyte Treatment">🧪 Electrolyte</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('d/m/Y', strtotime($calf['birth_date'])); ?></td>
                                <td><?php echo $calf['age_days']; ?> days</td>
                                <td><?php echo ucfirst($calf['sex']); ?></td>
                                <td>
                                    <span class="<?php echo $healthClass; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $calf['health_status'])); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($calf['batch_name'] ?? 'No Batch'); ?></td>
                                <td>
                                    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                        <?php if ($hasActiveElectrolyte): ?>
                                            <form method="post" action="/public/treatment/cancel-electrolyte" style="display: inline;">
                                                <input type="hidden" name="calf_id" value="<?php echo htmlspecialchars($calf['calf_id']); ?>">
                                                <button type="submit" class="btn-undo-electrolyte" onclick="return confirm('Cancel electrolyte treatment for <?php echo htmlspecialchars($calf['calf_id']); ?>?')">
                                                    ↩️ Undo
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <form method="post" action="/public/treatment/quick-electrolyte" style="display: inline;">
                                                <input type="hidden" name="calf_id" value="<?php echo htmlspecialchars($calf['calf_id']); ?>">
                                                <button type="submit" class="btn-electrolyte" onclick="return confirm('Start 3-day electrolyte treatment for <?php echo htmlspecialchars($calf['calf_id']); ?>?')">
                                                    🧪 Electrolyte
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <a href="/public/calves/edit/<?php echo $calf['id']; ?>" class="btn btn-primary btn-sm">✏️ Edit</a>
                                        <form method="post" action="/public/calves/delete" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete <?php echo htmlspecialchars($calf['calf_id']); ?>? This action cannot be undone.')">
                                            <input type="hidden" name="calf_id" value="<?php echo $calf['id']; ?>">
                                            <button type="submit" class="btn-delete">🗑️ Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
    // Selection management
    function toggleSelectAll(checkbox) {
        const checkboxes = document.querySelectorAll('.calf-select');
        checkboxes.forEach(cb => cb.checked = checkbox.checked);
        updateBulkActions();
    }
    
    function updateBulkActions() {
        const selected = document.querySelectorAll('.calf-select:checked');
        const count = selected.length;
        const bulkBar = document.getElementById('bulkActionsBar');
        const countSpan = document.getElementById('selectedCount');
        const selectAllCheckbox = document.getElementById('selectAll');
        
        countSpan.textContent = count;
        
        if (count > 0) {
            bulkBar.classList.add('active');
        } else {
            bulkBar.classList.remove('active');
        }
        
        // Update select all checkbox state
        const allCheckboxes = document.querySelectorAll('.calf-select');
        selectAllCheckbox.checked = count === allCheckboxes.length && count > 0;
        selectAllCheckbox.indeterminate = count > 0 && count < allCheckboxes.length;
    }
    
    function getSelectedIds() {
        const selected = document.querySelectorAll('.calf-select:checked');
        return Array.from(selected).map(cb => cb.value);
    }
    
    function clearSelection() {
        const checkboxes = document.querySelectorAll('.calf-select');
        checkboxes.forEach(cb => cb.checked = false);
        document.getElementById('selectAll').checked = false;
        updateBulkActions();
    }
    
    // Bulk actions
    function bulkAssignBatch() {
        const ids = getSelectedIds();
        if (ids.length === 0) {
            alert('Please select at least one calf');
            return;
        }
        window.location.href = '/public/calves/bulk-batch?ids=' + ids.join(',');
    }
    
    function bulkUpdateHealth() {
        const ids = getSelectedIds();
        if (ids.length === 0) {
            alert('Please select at least one calf');
            return;
        }
        window.location.href = '/public/calves/bulk-health?ids=' + ids.join(',');
    }
    
    function bulkElectrolyte() {
        const ids = getSelectedIds();
        if (ids.length === 0) {
            alert('Please select at least one calf');
            return;
        }
        
        const duration = prompt('Enter treatment duration in days (default: 3):', '3');
        if (duration === null) return;
        
        const durationDays = parseInt(duration) || 3;
        if (durationDays < 1 || durationDays > 30) {
            alert('Duration must be between 1 and 30 days');
            return;
        }
        
        // Create form and submit with proper array handling
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '/public/calves/bulk-electrolyte';
        
        // Add each ID as separate input with array notation
        ids.forEach(id => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'calf_ids[]';
            input.value = id;
            form.appendChild(input);
        });
        
        const durationInput = document.createElement('input');
        durationInput.type = 'hidden';
        durationInput.name = 'duration_days';
        durationInput.value = durationDays;
        
        form.appendChild(durationInput);
        document.body.appendChild(form);
        form.submit();
    }
    
    function bulkDelete() {
        const ids = getSelectedIds();
        if (ids.length === 0) {
            alert('Please select at least one calf');
            return;
        }
        
        if (confirm(`Are you sure you want to delete ${ids.length} selected calves? This action cannot be undone.`)) {
            // Create form and submit with proper array handling
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '/public/calves/bulk-delete';
            
            // Add each ID as separate input with array notation
            ids.forEach(id => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'calf_ids[]';
                input.value = id;
                form.appendChild(input);
            });
            
            document.body.appendChild(form);
            form.submit();
        }
    }
    
    // Filter functionality
    document.getElementById('searchCalves').addEventListener('input', filterTable);
    document.getElementById('filterHealth').addEventListener('change', filterTable);
    document.getElementById('filterBatch').addEventListener('change', filterTable);

    function filterTable() {
        const search = document.getElementById('searchCalves').value.toLowerCase();
        const health = document.getElementById('filterHealth').value;
        const batch = document.getElementById('filterBatch').value;
        
        const rows = document.querySelectorAll('tbody tr');
        
        rows.forEach(row => {
            const calfId = row.cells[1].textContent.toLowerCase();
            const healthStatus = row.cells[5].textContent.toLowerCase();
            const batchName = row.cells[6].textContent.toLowerCase();
            
            const matchSearch = calfId.includes(search);
            const matchHealth = !health || healthStatus.includes(health.replace('_', ' '));
            const matchBatch = !batch || batchName.includes(batch.toLowerCase());
            
            if (matchSearch && matchHealth && matchBatch) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }
    
    function clearFilters() {
        document.getElementById('searchCalves').value = '';
        document.getElementById('filterHealth').value = '';
        document.getElementById('filterBatch').value = '';
        filterTable();
    }

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        filterTable();
        updateBulkActions();
    });
</script>
</body>
</html>