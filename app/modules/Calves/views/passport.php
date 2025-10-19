<artifact identifier="calves-passport-view" type="application/vnd.ant.code" language="php" title="Enhanced Calf Passport with Timeline">
<?php
require_once BASE_PATH . '/app/core/ModernNavbar.php';
$navbar = new ModernNavbar();
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo htmlspecialchars($calf['calf_id']); ?> - Calf Passport - EasyCalf</title>
    <link rel="stylesheet" href="/public/assets/css/style.css">
    <style>
        .passport-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
            padding-top: 100px;
        }
    .back-button {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.75rem 1.5rem;
        background: var(--mid-blue);
        color: white;
        text-decoration: none;
        border-radius: 8px;
        margin-bottom: 2rem;
        font-weight: 600;
        transition: all 0.3s ease;
    }
    
    .back-button:hover {
        background: var(--navy);
        transform: translateY(-2px);
    }
    
    /* Calf Summary Header */
    .calf-header {
        background: linear-gradient(135deg, #1E88E5, #A1C349);
        color: white;
        padding: 2.5rem;
        border-radius: 16px;
        margin-bottom: 2rem;
        box-shadow: 0 8px 24px rgba(30, 136, 229, 0.3);
        position: sticky;
        top: 90px;
        z-index: 100;
    }
    
    .calf-id-title {
        font-size: 2.5rem;
        font-weight: 800;
        margin-bottom: 0.5rem;
        display: flex;
        align-items: center;
        gap: 1rem;
    }
    
    .status-badge {
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-size: 0.9rem;
        font-weight: 600;
        background: rgba(255, 255, 255, 0.2);
        border: 1px solid rgba(255, 255, 255, 0.3);
    }
    
    .info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
        margin-top: 1.5rem;
    }
    
    .info-card {
        background: rgba(255, 255, 255, 0.15);
        padding: 1.25rem;
        border-radius: 12px;
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.2);
    }
    
    .info-icon {
        font-size: 1.5rem;
        margin-bottom: 0.5rem;
    }
    
    .info-label {
        font-size: 0.85rem;
        opacity: 0.9;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-weight: 600;
    }
    
    .info-value {
        font-size: 1.3rem;
        font-weight: 700;
        margin-top: 0.25rem;
    }
    
    /* Action Buttons in Header */
    .header-actions {
        display: flex;
        gap: 0.75rem;
        margin-top: 1.5rem;
        flex-wrap: wrap;
    }
    
    .header-btn {
        background: rgba(255, 255, 255, 0.2);
        color: white;
        border: 1px solid rgba(255, 255, 255, 0.3);
        padding: 0.75rem 1.5rem;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .header-btn:hover {
        background: rgba(255, 255, 255, 0.3);
        transform: translateY(-2px);
    }
    
    /* Timeline Section */
    .timeline-section {
        background: white;
        padding: 2rem;
        border-radius: 16px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }
    
    .timeline-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid rgba(0, 0, 0, 0.1);
    }
    
    .timeline-title {
        font-size: 1.8rem;
        font-weight: 700;
        color: var(--text-dark);
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .timeline {
        position: relative;
        padding-left: 3rem;
    }
    
    .timeline::before {
        content: '';
        position: absolute;
        left: 0.75rem;
        top: 0;
        bottom: 0;
        width: 3px;
        background: linear-gradient(180deg, #1E88E5, #A1C349);
        border-radius: 2px;
    }
    
    .timeline-item {
        position: relative;
        padding: 1.5rem;
        background: #F7F9FC;
        border-radius: 12px;
        margin-bottom: 1.5rem;
        border-left: 4px solid #1E88E5;
        transition: all 0.3s ease;
    }
    
    .timeline-item:hover {
        transform: translateX(8px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }
    
    .timeline-item::before {
        content: '';
        position: absolute;
        left: -3.5rem;
        top: 1.5rem;
        width: 1.5rem;
        height: 1.5rem;
        background: white;
        border: 3px solid #1E88E5;
        border-radius: 50%;
        z-index: 1;
    }
    
    .timeline-item.completed::before {
        background: #43A047;
        border-color: #43A047;
    }
    
    .timeline-item.completed {
        border-left-color: #43A047;
        opacity: 0.8;
    }
    
    .timeline-item.treatment::before {
        border-color: #2196F3;
    }
    
    .timeline-item.treatment {
        border-left-color: #2196F3;
        background: #E3F2FD;
    }
    
    .event-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.75rem;
    }
    
    .event-type {
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: var(--text-secondary);
        background: white;
        padding: 0.25rem 0.75rem;
        border-radius: 12px;
    }
    
    .event-date {
        font-size: 0.9rem;
        color: var(--text-secondary);
        font-weight: 600;
    }
    
    .event-title {
        font-size: 1.2rem;
        font-weight: 700;
        color: var(--text-dark);
        margin-bottom: 0.5rem;
    }
    
    .event-description {
        color: var(--text-secondary);
        line-height: 1.6;
    }
    
    .event-meta {
        margin-top: 0.75rem;
        padding-top: 0.75rem;
        border-top: 1px solid rgba(0, 0, 0, 0.1);
        font-size: 0.85rem;
        color: var(--text-secondary);
    }
    
    .empty-timeline {
        text-align: center;
        padding: 4rem 2rem;
        color: var(--text-secondary);
    }
    
    .empty-icon {
        font-size: 4rem;
        margin-bottom: 1rem;
        opacity: 0.5;
    }
    
    @media (max-width: 768px) {
        .passport-container {
            padding: 1rem;
            padding-top: 80px;
        }
        
        .calf-header {
            padding: 1.5rem;
            position: relative;
            top: 0;
        }
        
        .calf-id-title {
            font-size: 1.8rem;
            flex-direction: column;
            align-items: flex-start;
        }
        
        .info-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .timeline {
            padding-left: 2rem;
        }
        
        .timeline::before {
            left: 0.5rem;
        }
        
        .timeline-item::before {
            left: -2.5rem;
        }
    }
</style>
</head>
<body>
    <?php $navbar->render('calves'); ?>
<div class="passport-container">
    <a href="/public/calves" class="back-button">
        <span>←</span>
        Back to Calves
    </a>

    <!-- Calf Summary Header -->
    <div class="calf-header">
        <div class="calf-id-title">
            <span>🐮 <?php echo htmlspecialchars($calf['calf_id']); ?></span>
            <span class="status-badge">
                <?php echo ucfirst($calf['status']); ?>
            </span>
        </div>
        
        <div class="info-grid">
            <div class="info-card">
                <div class="info-icon">📅</div>
                <div class="info-label">Birth Date</div>
                <div class="info-value"><?php echo date('d/m/Y', strtotime($calf['birth_date'])); ?></div>
            </div>
            
            <div class="info-card">
                <div class="info-icon">⏱️</div>
                <div class="info-label">Age</div>
                <div class="info-value">
                    <?php echo $calf['age_days']; ?> days
                    <span style="font-size: 0.8rem; opacity: 0.9;">(<?php echo $calf['age_weeks']; ?> weeks)</span>
                </div>
            </div>
            
            <div class="info-card">
                <div class="info-icon">🚺</div>
                <div class="info-label">Sex</div>
                <div class="info-value"><?php echo ucfirst($calf['sex']); ?></div>
            </div>
            
            <div class="info-card">
                <div class="info-icon">🐄</div>
                <div class="info-label">Dam/Mother</div>
                <div class="info-value"><?php echo htmlspecialchars($calf['dam_id'] ?? 'Unknown'); ?></div>
            </div>
            
            <?php if ($calf['breed']): ?>
            <div class="info-card">
                <div class="info-icon">🧬</div>
                <div class="info-label">Breed</div>
                <div class="info-value"><?php echo htmlspecialchars($calf['breed']); ?></div>
            </div>
            <?php endif; ?>
            
            <div class="info-card">
                <div class="info-icon">⚖️</div>
                <div class="info-label">Birth Weight</div>
                <div class="info-value"><?php echo $calf['birth_weight'] ? $calf['birth_weight'] . ' kg' : 'Not recorded'; ?></div>
            </div>
            
            <div class="info-card">
                <div class="info-icon">💚</div>
                <div class="info-label">Health Status</div>
                <div class="info-value health-<?php echo $calf['health_status']; ?>">
                    <?php echo ucfirst(str_replace('_', ' ', $calf['health_status'])); ?>
                </div>
            </div>
            
            <div class="info-card">
                <div class="info-icon">🏠</div>
                <div class="info-label">Current Batch</div>
                <div class="info-value"><?php echo htmlspecialchars($calf['batch_name'] ?? 'No Batch'); ?></div>
            </div>
            
            <?php if ($calf['pen_location'] || $calf['pen_location']): ?>
            <div class="info-card">
                <div class="info-icon">📍</div>
                <div class="info-label">Pen/Location</div>
                <div class="info-value"><?php echo htmlspecialchars($calf['pen_location'] ?? $calf['pen_location'] ?? 'Not specified'); ?></div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Action Buttons -->
        <div class="header-actions">
            <a href="/public/calves/edit/<?php echo $calf['id']; ?>" class="header-btn">
                ✏️ Edit Details
            </a>
            <button onclick="window.print()" class="header-btn">
                🖨️ Print Passport
            </button>
            <a href="/public/calves/export" class="header-btn">
                💾 Export Data
            </a>
        </div>
    </div>

    <!-- Life Events Timeline -->
    <div class="timeline-section">
        <div class="timeline-header">
            <h2 class="timeline-title">
                <span>📋</span>
                Life Events Timeline
            </h2>
            <span style="color: var(--text-secondary); font-size: 0.9rem;">
                <?php echo count($events) + count($treatments); ?> total events
            </span>
        </div>
        
        <?php if (empty($events) && empty($treatments)): ?>
            <div class="empty-timeline">
                <div class="empty-icon">📭</div>
                <h3>No Events Recorded Yet</h3>
                <p>Life events and treatments will appear here as they are recorded.</p>
            </div>
        <?php else: ?>
            <div class="timeline">
                <!-- Combine events and treatments, sort by date -->
                <?php
                $allEvents = [];
                
                // Add scheduled events
                foreach ($events as $event) {
                    $allEvents[] = [
                        'date' => $event['due_date'],
                        'type' => 'event',
                        'status' => $event['status'],
                        'data' => $event
                    ];
                }
                
                // Add treatments
                foreach ($treatments as $treatment) {
                    $allEvents[] = [
                        'date' => $treatment['start_date'],
                        'type' => 'treatment',
                        'status' => $treatment['status'],
                        'data' => $treatment
                    ];
                }
                
                // Sort by date (most recent first)
                usort($allEvents, function($a, $b) {
                    return strtotime($b['date']) - strtotime($a['date']);
                });
                
                foreach ($allEvents as $item):
                    $isCompleted = in_array($item['status'], ['completed', 'cancelled']);
                    $itemClass = $item['type'] . ($isCompleted ? ' completed' : '');
                ?>
                
                <div class="timeline-item <?php echo $itemClass; ?>">
                    <div class="event-header">
                        <span class="event-type">
                            <?php 
                            if ($item['type'] === 'treatment') {
                                echo '💊 TREATMENT';
                            } else {
                                echo '📌 ' . strtoupper($item['data']['event_type']);
                            }
                            ?>
                        </span>
                        <span class="event-date">
                            <?php echo date('d M Y', strtotime($item['date'])); ?>
                            <?php if ($item['data']['completed_date'] ?? false): ?>
                                <span style="color: #43A047; margin-left: 0.5rem;">✓ Completed</span>
                            <?php endif; ?>
                        </span>
                    </div>
                    
                    <div class="event-title">
                        <?php 
                        if ($item['type'] === 'treatment') {
                            echo htmlspecialchars($item['data']['treatment_name']);
                        } else {
                            echo htmlspecialchars($item['data']['event_name']);
                        }
                        ?>
                    </div>
                    
                    <div class="event-description">
                        <?php 
                        if ($item['type'] === 'treatment') {
                            echo "Duration: " . $item['data']['duration_days'] . " days";
                            if ($item['status'] === 'active') {
                                echo " • Day " . $item['data']['current_day'] . " of " . $item['data']['duration_days'];
                            }
                            if ($item['data']['notes']) {
                                echo "<br>" . htmlspecialchars($item['data']['notes']);
                            }
                        } else {
                            if ($item['data']['completed_notes'] ?? false) {
                                echo htmlspecialchars($item['data']['completed_notes']);
                            } else {
                                echo "Scheduled " . ($item['status'] === 'pending' ? 'for' : 'on') . " " . date('d M Y', strtotime($item['date']));
                            }
                        }
                        ?>
                    </div>
                    
                    <div class="event-meta">
                        <?php 
                        if ($item['type'] === 'treatment') {
                            echo "Started by: " . htmlspecialchars($item['data']['created_by_name'] ?? 'System');
                        } else {
                            if ($item['data']['completed_by_name'] ?? false) {
                                echo "Completed by: " . htmlspecialchars($item['data']['completed_by_name']);
                                if ($item['data']['completed_date']) {
                                    echo " on " . date('d M Y H:i', strtotime($item['data']['completed_date']));
                                }
                            } else {
                                echo "Status: " . ucfirst($item['status']);
                            }
                        }
                        ?>
                    </div>
                </div>
                
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<style media="print">
    .modern-navbar, .back-button, .header-actions {
        display: none !important;
    }
    
    .calf-header {
        position: relative !important;
        top: 0 !important;
    }
    
    .passport-container {
        padding-top: 0 !important;
    }
</style>
</body>
</html>
</artifact>