<?php
class MilkController {
    private $db;
    private $auth;

    public function __construct() {
        $this->db = new Database();
        $this->auth = new Auth();
    }

    public function calculator() {
        if (!$this->auth->isLoggedIn()) {
            header('Location: /public/login');
            exit;
        }

        // Get all active calves with their ages and batches
        $calves = $this->db->fetchAll("
            SELECT c.id, c.calf_id, c.birth_date, 
                   DATEDIFF(NOW(), c.birth_date) as age_days,
                   c.health_status, 
                   b.id as batch_id,
                   b.name as batch_name,
                   b.capacity as batch_capacity
            FROM calves c
            LEFT JOIN batches b ON c.batch_id = b.id
            WHERE c.status = 'active'
            ORDER BY b.name IS NULL, b.name ASC, c.birth_date DESC
        ");

        // Get milk allowances
        $allowances = $this->db->fetchAll("SELECT * FROM milk_allowances ORDER BY age_start ASC");

        // Get current milk powder ratio
        $ratio = $this->db->fetch("SELECT * FROM milk_powder_ratio ORDER BY id DESC LIMIT 1");
        if (!$ratio) {
            $ratio = ['powder_amount' => 150, 'water_amount' => 1.0];
        }

        // Calculate milk requirements for each calf (PER SHIFT)
        $totalMilkPerShift = 0;
        $batchMilkData = [];
        
        foreach ($calves as &$calf) {
            $milkAmount = $this->getMilkAllowanceForAge($calf['age_days'], $allowances);
            $calf['milk_per_feed'] = $milkAmount; // This is already per shift
            $totalMilkPerShift += $calf['milk_per_feed'];
            
            // Group by batch for batch-wise calculations
            $batchId = $calf['batch_id'] ?: 'no_batch';
            $batchName = $calf['batch_name'] ?: 'No Batch';
            
            if (!isset($batchMilkData[$batchId])) {
                $batchMilkData[$batchId] = [
                    'batch_name' => $batchName,
                    'calf_count' => 0,
                    'total_milk_per_shift' => 0,
                    'calves' => []
                ];
            }
            
            $batchMilkData[$batchId]['calf_count']++;
            $batchMilkData[$batchId]['total_milk_per_shift'] += $calf['milk_per_feed'];
            $batchMilkData[$batchId]['calves'][] = $calf;
        }

        // Calculate powder needed (PER SHIFT)
        $powderNeededPerShift = ($totalMilkPerShift / $ratio['water_amount']) * $ratio['powder_amount'];

        $this->renderCalculator($calves, $totalMilkPerShift, $powderNeededPerShift, $ratio, $batchMilkData);
    }

    private function getMilkAllowanceForAge($ageDays, $allowances) {
        foreach ($allowances as $allowance) {
            if ($ageDays >= $allowance['age_start'] && $ageDays <= $allowance['age_end']) {
                return floatval($allowance['milk_amount']);
            }
        }
        return 1.0; // Default fallback
    }

    private function renderCalculator($calves, $totalMilkPerShift, $powderNeededPerShift, $ratio, $batchMilkData) {
        require_once BASE_PATH . '/app/core/ModernNavbar.php';
        $navbar = new ModernNavbar();
        ?>
<!DOCTYPE html>
<html>
<head>
    <title>Milk Calculator - EasyCalf</title>
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <style>
        .calculator-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
            padding-top: 100px;
        }

        .calculator-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .calculator-title {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
        }

        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .summary-card {
            background: var(--glass-card);
            backdrop-filter: var(--glass-blur);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-lg);
            padding: 2rem;
            text-align: center;
            box-shadow: var(--glass-shadow);
            transition: all 0.3s ease;
        }

        .summary-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.15);
        }

        .summary-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .summary-value {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--navy);
            margin-bottom: 0.5rem;
        }

        .summary-label {
            font-size: 0.9rem;
            color: var(--text-secondary);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .preparation-section {
            background: linear-gradient(135deg, #e3f2fd, #bbdefb);
            padding: 2rem;
            border-radius: var(--radius-lg);
            margin-bottom: 2rem;
        }

        .preparation-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--navy);
            margin-bottom: 1rem;
            text-align: center;
        }

        .preparation-steps {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .prep-step {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            text-align: center;
        }

        .step-number {
            width: 40px;
            height: 40px;
            background: var(--mid-blue);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            margin: 0 auto 1rem;
        }

        .batch-section {
            background: white;
            border-radius: var(--radius-lg);
            padding: 2rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .batch-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }

        .batch-card {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 12px;
            border-left: 4px solid var(--mid-blue);
        }

        .batch-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .batch-name {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--text-dark);
        }

        .batch-stats {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
            margin-top: 1rem;
        }

        .batch-stat {
            text-align: center;
            padding: 1rem;
            background: white;
            border-radius: 8px;
            border: 2px solid var(--light-accent);
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 800;
            color: var(--navy);
            display: block;
        }

        .stat-label {
            font-size: 0.9rem;
            color: var(--text-secondary);
            font-weight: 600;
        }

        .calves-list {
            background: white;
            border-radius: var(--radius-lg);
            padding: 2rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .calves-table {
            width: 100%;
            border-collapse: collapse;
        }

        .calves-table th {
            background: var(--light-accent);
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: var(--text-dark);
            border-bottom: 1px solid var(--glass-border);
        }

        .calves-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--glass-border);
        }

        .calves-table tr:hover td {
            background: rgba(234, 246, 255, 0.5);
        }

        .settings-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background: var(--mid-blue);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .settings-link:hover {
            background: var(--navy);
            transform: translateY(-2px);
        }

        .shift-tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            justify-content: center;
        }

        .shift-tab {
            padding: 1rem 2rem;
            background: white;
            border: 2px solid var(--glass-border);
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .shift-tab.active {
            background: var(--mid-blue);
            color: white;
            border-color: var(--mid-blue);
        }

        @media (max-width: 768px) {
            .calculator-container {
                padding: 1rem;
                padding-top: 80px;
            }
            
            .summary-cards {
                grid-template-columns: 1fr;
            }
            
            .preparation-steps {
                grid-template-columns: 1fr;
            }
            
            .batch-grid {
                grid-template-columns: 1fr;
            }
            
            .shift-tabs {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <?php $navbar->render('milk'); ?>
    
    <div class="calculator-container">
        <div class="calculator-header">
            <h1 class="calculator-title">🥛 Milk Preparation Calculator</h1>
            <p style="color: var(--text-secondary); font-size: 1.1rem;">Per-shift feeding requirements for all active calves</p>
        </div>

        <!-- Shift Tabs -->
        <div class="shift-tabs">
            <div class="shift-tab active" data-shift="am">🌅 Morning Shift (6:30-9:00)</div>
            <div class="shift-tab" data-shift="pm">🌇 Evening Shift (4:00-6:00)</div>
        </div>

        <!-- Summary Cards -->
        <div class="summary-cards">
            <div class="summary-card">
                <div class="summary-icon">🐄</div>
                <div class="summary-value"><?php echo count($calves); ?></div>
                <div class="summary-label">Active Calves</div>
            </div>

            <div class="summary-card">
                <div class="summary-icon">🥛</div>
                <div class="summary-value milk-amount"><?php echo number_format($totalMilkPerShift, 1); ?>L</div>
                <div class="summary-label">Milk Per Shift</div>
            </div>

            <div class="summary-card">
                <div class="summary-icon">📊</div>
                <div class="summary-value powder-amount"><?php echo number_format($powderNeededPerShift, 0); ?>g</div>
                <div class="summary-label">Powder Per Shift</div>
            </div>

            <div class="summary-card">
                <div class="summary-icon">🏠</div>
                <div class="summary-value"><?php echo count($batchMilkData); ?></div>
                <div class="summary-label">Active Batches</div>
            </div>
        </div>

        <!-- Batch-wise Milk Calculation -->
        <div class="batch-section">
            <h2 style="margin-bottom: 1.5rem; color: var(--text-dark);">🏠 Batch-wise Milk Requirements (Per Shift)</h2>
            
            <?php if (empty($batchMilkData)): ?>
                <div style="text-align: center; padding: 2rem; color: var(--text-secondary);">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">🏠</div>
                    <h3>No Batches with Calves</h3>
                    <p>Assign calves to batches to see batch-wise milk calculations</p>
                </div>
            <?php else: ?>
                <div class="batch-grid">
                    <?php foreach ($batchMilkData as $batchId => $batch): ?>
                    <div class="batch-card">
                        <div class="batch-header">
                            <div class="batch-name"><?php echo htmlspecialchars($batch['batch_name']); ?></div>
                            <span style="background: var(--mid-blue); color: white; padding: 0.25rem 0.75rem; border-radius: 12px; font-size: 0.8rem; font-weight: 600;">
                                <?php echo $batch['calf_count']; ?> calves
                            </span>
                        </div>
                        
                        <div class="batch-stats">
                            <div class="batch-stat">
                                <span class="stat-value"><?php echo number_format($batch['total_milk_per_shift'], 1); ?>L</span>
                                <span class="stat-label">Milk Required Per Shift</span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Preparation Instructions -->
        <div class="preparation-section">
            <h2 class="preparation-title">📋 Shift Preparation Instructions</h2>
            <div class="preparation-steps">
                <div class="prep-step">
                    <div class="step-number">1</div>
                    <strong>Measure Water</strong>
                    <p style="margin-top: 0.5rem; color: var(--text-secondary);">
                        <span class="water-amount"><?php echo number_format($totalMilkPerShift, 1); ?></span>L of warm water
                    </p>
                </div>

                <div class="prep-step">
                    <div class="step-number">2</div>
                    <strong>Add Powder</strong>
                    <p style="margin-top: 0.5rem; color: var(--text-secondary);">
                        <span class="powder-amount-instruction"><?php echo number_format($powderNeededPerShift, 0); ?></span>g milk powder
                    </p>
                </div>

                <div class="prep-step">
                    <div class="step-number">3</div>
                    <strong>Mix Well</strong>
                    <p style="margin-top: 0.5rem; color: var(--text-secondary);">
                        Stir until fully dissolved
                    </p>
                </div>

                <div class="prep-step">
                    <div class="step-number">4</div>
                    <strong>Feed All Calves</strong>
                    <p style="margin-top: 0.5rem; color: var(--text-secondary);">
                        Distribute according to batch requirements
                    </p>
                </div>
            </div>

            <div style="margin-top: 1.5rem; text-align: center;">
                <p style="font-size: 0.9rem; color: var(--text-secondary);">
                    <strong>Current Ratio:</strong> <?php echo $ratio['powder_amount']; ?>g powder per <?php echo $ratio['water_amount']; ?>L water
                </p>
                <a href="/public/settings" class="settings-link" style="margin-top: 1rem;">
                    ⚙️ Adjust Ratio & Allowances
                </a>
            </div>
        </div>

        <!-- Individual Calves Breakdown -->
        <div class="calves-list">
            <h2 style="margin-bottom: 1.5rem; color: var(--text-dark);">📊 Individual Calf Breakdown (Per Feed)</h2>
            
            <?php if (empty($calves)): ?>
                <div style="text-align: center; padding: 3rem; color: var(--text-secondary);">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">🐄</div>
                    <h3>No Active Calves</h3>
                    <p>Add calves to start calculating milk requirements</p>
                    <a href="/public/calves/add" class="btn btn-primary" style="margin-top: 1rem;">Add First Calf</a>
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table class="calves-table">
                        <thead>
                            <tr>
                                <th>Calf ID</th>
                                <th>Age (Days)</th>
                                <th>Batch</th>
                                <th>Milk Per Feed</th>
                                <th>Health</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($calves as $calf): ?>
                                <tr>
                                    <td>
                                        <a href="/public/calves/passport/<?php echo $calf['id']; ?>" 
                                           style="color: var(--mid-blue); text-decoration: none; font-weight: 600;">
                                            <?php echo htmlspecialchars($calf['calf_id']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo $calf['age_days']; ?> days</td>
                                    <td><?php echo htmlspecialchars($calf['batch_name'] ?? 'No Batch'); ?></td>
                                    <td><strong><?php echo number_format($calf['milk_per_feed'], 2); ?>L</strong></td>
                                    <td>
                                        <span class="health-<?php echo $calf['health_status']; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $calf['health_status'])); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Shift tab functionality
        document.querySelectorAll('.shift-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                // Update active tab
                document.querySelectorAll('.shift-tab').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                
                const shift = this.dataset.shift;
                
                // Update display based on shift (for future enhancements)
                if (shift === 'am') {
                    // Morning shift - use current values
                    document.querySelector('.milk-amount').textContent = '<?php echo number_format($totalMilkPerShift, 1); ?>L';
                    document.querySelector('.powder-amount').textContent = '<?php echo number_format($powderNeededPerShift, 0); ?>g';
                    document.querySelector('.water-amount').textContent = '<?php echo number_format($totalMilkPerShift, 1); ?>';
                    document.querySelector('.powder-amount-instruction').textContent = '<?php echo number_format($powderNeededPerShift, 0); ?>';
                } else {
                    // Evening shift - use current values (same for now, but can be customized)
                    document.querySelector('.milk-amount').textContent = '<?php echo number_format($totalMilkPerShift, 1); ?>L';
                    document.querySelector('.powder-amount').textContent = '<?php echo number_format($powderNeededPerShift, 0); ?>g';
                    document.querySelector('.water-amount').textContent = '<?php echo number_format($totalMilkPerShift, 1); ?>';
                    document.querySelector('.powder-amount-instruction').textContent = '<?php echo number_format($powderNeededPerShift, 0); ?>';
                }
            });
        });
    </script>
</body>
</html>
        <?php
    }
}
?>